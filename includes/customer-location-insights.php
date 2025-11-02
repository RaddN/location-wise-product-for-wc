<?php

/**
 * Customer Location Insights & Recommendations (Options-Based)
 * 
 * Tracks customer location preferences using WordPress options
 * Much faster and simpler than database tables
 * 
 * @package Multi Location Product & Inventory Management
 * @since 1.0.4
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mulopimfwc_Customer_Location_Insights
{
    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Option keys
     */
    const TRACKING_OPTION = 'mulopimfwc_customer_tracking';
    const POPULARITY_OPTION = 'mulopimfwc_location_popularity';
    const STATS_OPTION = 'mulopimfwc_location_stats';

    /**
     * Maximum entries to keep (prevent bloat)
     */
    const MAX_TRACKING_ENTRIES = 1000;
    const MAX_PRODUCTS_PER_LOCATION = 100;

    /**
     * Get singleton instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks()
    {
        // Track location selection
        add_action('wp_footer', [$this, 'track_location_selection']);

        // Track product views
        add_action('woocommerce_after_single_product', [$this, 'track_product_view']);

        // Track purchases
        add_action('woocommerce_thankyou', [$this, 'track_purchase'], 10, 1);

        // Register shortcode
        add_shortcode('mulopimfwc_location_recommendations', [$this, 'recommendations_shortcode']);

        // AJAX handlers
        add_action('wp_ajax_mulopimfwc_get_recommendations', [$this, 'ajax_get_recommendations']);
        add_action('wp_ajax_nopriv_mulopimfwc_get_recommendations', [$this, 'ajax_get_recommendations']);

        // Enqueue scripts
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);

        // Admin analytics dashboard
        add_action('admin_menu', [$this, 'add_analytics_menu'], 99);

        // Cleanup old data daily
        add_action('mulopimfwc_daily_cleanup', [$this, 'cleanup_old_data']);
        if (!wp_next_scheduled('mulopimfwc_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'mulopimfwc_daily_cleanup');
        }
    }

    /**
     * Get or create session ID for tracking
     */
    private function get_session_id()
    {
        if (!session_id()) {
            @session_start();
        }

        if (!isset($_SESSION['mulopimfwc_session_id'])) {
            $_SESSION['mulopimfwc_session_id'] = uniqid('mlp_', true);
        }

        return $_SESSION['mulopimfwc_session_id'];
    }

    /**
     * Check if tracking is enabled
     */
    private function is_tracking_enabled()
    {
        $options = get_option('mulopimfwc_display_options', []);
        return isset($options['enable_customer_location_tracking']) &&
            $options['enable_customer_location_tracking'] === 'on';
    }

    /**
     * Get current location from cookie
     */
    private function get_current_location()
    {
        if (!isset($_COOKIE['mulopimfwc_store_location'])) {
            return null;
        }

        $location_slug = sanitize_text_field(wp_unslash($_COOKIE['mulopimfwc_store_location']));

        if (empty($location_slug) || $location_slug === 'all-products') {
            return null;
        }

        $location = get_term_by('slug', $location_slug, 'mulopimfwc_store_location');

        if (!$location || is_wp_error($location)) {
            return null;
        }

        return [
            'slug' => $location->slug,
            'name' => $location->name,
            'id' => $location->term_id
        ];
    }

    /**
     * Track location selection via JavaScript
     */
    public function track_location_selection()
    {
        if (!$this->is_tracking_enabled()) {
            return;
        }

?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $(document).on('change', '.mulopimfwc-location-selector, #mulopimfwc_store_location', function() {
                    var locationSlug = $(this).val();
                    var locationName = $(this).find('option:selected').text();

                    if (locationSlug && locationSlug !== 'all-products') {
                        $.ajax({
                            url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                            type: 'POST',
                            data: {
                                action: 'mulopimfwc_track_location_selection',
                                location_slug: locationSlug,
                                location_name: locationName,
                                nonce: '<?php echo esc_js(wp_create_nonce('mulopimfwc_tracking')); ?>'
                            }
                        });
                    }
                });
            });
        </script>
    <?php
    }

    /**
     * Track product view
     */
    public function track_product_view()
    {
        if (!$this->is_tracking_enabled()) {
            return;
        }

        global $product;

        if (!$product) {
            return;
        }

        $location = $this->get_current_location();

        if (!$location) {
            return;
        }

        $this->log_action('view', $location, $product->get_id());
        $this->update_popularity($location['slug'], $product->get_id(), 'view');
        $this->update_stats($location['slug'], 'view');
    }

    /**
     * Track purchase
     */
    public function track_purchase($order_id)
    {
        if (!$this->is_tracking_enabled()) {
            return;
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        $location_slug = $order->get_meta('_store_location');

        if (empty($location_slug)) {
            $location = $this->get_current_location();
            if (!$location) {
                return;
            }
            $location_slug = $location['slug'];
            $location_name = $location['name'];
        } else {
            $location_term = get_term_by('slug', $location_slug, 'mulopimfwc_store_location');
            $location_name = $location_term ? $location_term->name : $location_slug;
        }

        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();

            $this->log_action('purchase', [
                'slug' => $location_slug,
                'name' => $location_name
            ], $product_id, $order_id);

            $this->update_popularity($location_slug, $product_id, 'purchase');
        }

        $this->update_stats($location_slug, 'purchase');
    }

    /**
     * Log tracking action to options
     */
    private function log_action($action_type, $location, $product_id = null, $order_id = null)
    {
        $options = get_option('mulopimfwc_display_options', []);
        $history_setting = isset($options['customer_location_history']) ?
            $options['customer_location_history'] : 'latest';

        if ($history_setting === 'none') {
            return;
        }

        $tracking_data = get_option(self::TRACKING_OPTION, []);

        $user_id = get_current_user_id();
        $session_id = $this->get_session_id();

        // If 'latest' only, remove previous entries for this user/session and action
        if ($history_setting === 'latest' && $action_type === 'selection') {
            $tracking_data = array_filter($tracking_data, function ($entry) use ($user_id, $session_id, $action_type) {
                return !($entry['user_id'] == $user_id &&
                    $entry['session_id'] == $session_id &&
                    $entry['action_type'] == $action_type);
            });
        }

        // Add new entry
        $tracking_data[] = [
            'user_id' => $user_id ?: null,
            'session_id' => $session_id,
            'location_slug' => $location['slug'],
            'location_name' => $location['name'],
            'action_type' => $action_type,
            'product_id' => $product_id,
            'order_id' => $order_id,
            'timestamp' => current_time('timestamp')
        ];

        // Keep only last MAX_TRACKING_ENTRIES
        if (count($tracking_data) > self::MAX_TRACKING_ENTRIES) {
            $tracking_data = array_slice($tracking_data, -self::MAX_TRACKING_ENTRIES);
        }

        update_option(self::TRACKING_OPTION, $tracking_data, false);
    }

    /**
     * Update product popularity for location
     */
    private function update_popularity($location_slug, $product_id, $type = 'view')
    {
        $popularity_data = get_option(self::POPULARITY_OPTION, []);

        if (!isset($popularity_data[$location_slug])) {
            $popularity_data[$location_slug] = [];
        }

        if (!isset($popularity_data[$location_slug][$product_id])) {
            $popularity_data[$location_slug][$product_id] = [
                'product_id' => $product_id,
                'view_count' => 0,
                'purchase_count' => 0,
                'last_viewed' => null,
                'last_purchased' => null,
                'popularity_score' => 0
            ];
        }

        // Update counts
        if ($type === 'view') {
            $popularity_data[$location_slug][$product_id]['view_count']++;
            $popularity_data[$location_slug][$product_id]['last_viewed'] = current_time('timestamp');
        } else {
            $popularity_data[$location_slug][$product_id]['purchase_count']++;
            $popularity_data[$location_slug][$product_id]['last_purchased'] = current_time('timestamp');
        }

        // Calculate popularity score (purchases = 10x views)
        $views = $popularity_data[$location_slug][$product_id]['view_count'];
        $purchases = $popularity_data[$location_slug][$product_id]['purchase_count'];
        $popularity_data[$location_slug][$product_id]['popularity_score'] = ($purchases * 10) + $views;

        // Keep only top products per location
        if (count($popularity_data[$location_slug]) > self::MAX_PRODUCTS_PER_LOCATION) {
            // Sort by popularity score
            uasort($popularity_data[$location_slug], function ($a, $b) {
                return $b['popularity_score'] - $a['popularity_score'];
            });

            // Keep only top products
            $popularity_data[$location_slug] = array_slice(
                $popularity_data[$location_slug],
                0,
                self::MAX_PRODUCTS_PER_LOCATION,
                true
            );
        }

        update_option(self::POPULARITY_OPTION, $popularity_data, false);
    }

    /**
     * Update location statistics
     */
    private function update_stats($location_slug, $type)
    {
        $stats = get_option(self::STATS_OPTION, []);

        if (!isset($stats[$location_slug])) {
            $stats[$location_slug] = [
                'unique_users' => [],
                'unique_sessions' => [],
                'total_views' => 0,
                'total_purchases' => 0,
                'last_updated' => current_time('timestamp')
            ];
        }

        $user_id = get_current_user_id();
        $session_id = $this->get_session_id();

        // Track unique users and sessions
        if ($user_id && !in_array($user_id, $stats[$location_slug]['unique_users'])) {
            $stats[$location_slug]['unique_users'][] = $user_id;
        }

        if (!in_array($session_id, $stats[$location_slug]['unique_sessions'])) {
            $stats[$location_slug]['unique_sessions'][] = $session_id;
        }

        // Update counts
        if ($type === 'view') {
            $stats[$location_slug]['total_views']++;
        } else {
            $stats[$location_slug]['total_purchases']++;
        }

        $stats[$location_slug]['last_updated'] = current_time('timestamp');

        update_option(self::STATS_OPTION, $stats, false);
    }

    /**
     * Get popular products for a location
     */
    public function get_popular_products($location_slug, $limit = 10)
    {
        $popularity_data = get_option(self::POPULARITY_OPTION, []);

        if (!isset($popularity_data[$location_slug])) {
            return [];
        }

        $products = $popularity_data[$location_slug];

        // Sort by popularity score
        uasort($products, function ($a, $b) {
            return $b['popularity_score'] - $a['popularity_score'];
        });

        return array_slice($products, 0, $limit, true);
    }

    /**
     * Get location statistics
     */
    public function get_location_stats($location_slug)
    {
        $stats = get_option(self::STATS_OPTION, []);

        if (!isset($stats[$location_slug])) {
            return [
                'unique_users' => 0,
                'unique_sessions' => 0,
                'total_views' => 0,
                'total_purchases' => 0
            ];
        }

        return [
            'unique_users' => count($stats[$location_slug]['unique_users']),
            'unique_sessions' => count($stats[$location_slug]['unique_sessions']),
            'total_views' => $stats[$location_slug]['total_views'],
            'total_purchases' => $stats[$location_slug]['total_purchases']
        ];
    }

    /**
     * Cleanup old data
     */
    public function cleanup_old_data()
    {
        // Clean tracking data older than 90 days
        $tracking_data = get_option(self::TRACKING_OPTION, []);
        $ninety_days_ago = strtotime('-90 days');

        $tracking_data = array_filter($tracking_data, function ($entry) use ($ninety_days_ago) {
            return $entry['timestamp'] > $ninety_days_ago;
        });

        update_option(self::TRACKING_OPTION, array_values($tracking_data), false);

        // Clean stats - keep only user/session IDs from last 30 days
        $stats = get_option(self::STATS_OPTION, []);
        $tracking_recent = array_filter($tracking_data, function ($entry) {
            return $entry['timestamp'] > strtotime('-30 days');
        });

        foreach ($stats as $location_slug => &$location_stats) {
            $recent_users = [];
            $recent_sessions = [];

            foreach ($tracking_recent as $entry) {
                if ($entry['location_slug'] === $location_slug) {
                    if ($entry['user_id']) {
                        $recent_users[] = $entry['user_id'];
                    }
                    $recent_sessions[] = $entry['session_id'];
                }
            }

            $location_stats['unique_users'] = array_unique($recent_users);
            $location_stats['unique_sessions'] = array_unique($recent_sessions);
        }

        update_option(self::STATS_OPTION, $stats, false);
    }

    /**
     * Clear all tracking data for a user (GDPR compliance)
     */
    public function clear_user_data($user_id)
    {
        $tracking_data = get_option(self::TRACKING_OPTION, []);

        $tracking_data = array_filter($tracking_data, function ($entry) use ($user_id) {
            return $entry['user_id'] != $user_id;
        });

        update_option(self::TRACKING_OPTION, array_values($tracking_data), false);

        // Remove from stats
        $stats = get_option(self::STATS_OPTION, []);

        foreach ($stats as &$location_stats) {
            $location_stats['unique_users'] = array_diff(
                $location_stats['unique_users'],
                [$user_id]
            );
        }

        update_option(self::STATS_OPTION, $stats, false);
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts()
    {
        if (!$this->is_tracking_enabled()) {
            return;
        }

        $options = get_option('mulopimfwc_display_options', []);
        $recommendations_enabled = isset($options['location_based_recommendations']) &&
            $options['location_based_recommendations'] === 'on';

        if ($recommendations_enabled) {
            wp_enqueue_style(
                'mulopimfwc-recommendations',
                MULTI_LOCATION_PLUGIN_URL . 'assets/css/recommendations.css',
                [],
                '1.0.4'
            );

            wp_enqueue_script(
                'mulopimfwc-recommendations',
                MULTI_LOCATION_PLUGIN_URL . 'assets/js/recommendations.js',
                ['jquery'],
                '1.0.4',
                true
            );

            wp_localize_script('mulopimfwc-recommendations', 'mulopimfwcRecommendations', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mulopimfwc_recommendations')
            ]);
        }
    }

    /**
     * Recommendations shortcode
     */
    public function recommendations_shortcode($atts)
    {
        $options = get_option('mulopimfwc_display_options', []);
        $recommendations_enabled = isset($options['location_based_recommendations']) &&
            $options['location_based_recommendations'] === 'on';

        if (!$recommendations_enabled) {
            return '';
        }

        $location = $this->get_current_location();

        if (!$location) {
            return '<div class="mulopimfwc-recommendations-notice">' .
                esc_html__('Please select a location to see recommendations.', 'multi-location-product-and-inventory-management') .
                '</div>';
        }

        $atts = shortcode_atts([
            'limit' => 8,
            'columns' => 4,
            'title' => sprintf(__('Popular at %s', 'multi-location-product-and-inventory-management'), '{location}'),
            'show_title' => 'yes',
            'orderby' => 'popularity',
            'show_badge' => 'yes'
        ], $atts);

        // Replace {location} placeholder with actual location name
        $title = str_replace('{location}', $location['name'], $atts['title']);

        $popular_products = $this->get_popular_products($location['slug'], intval($atts['limit']));

        if (empty($popular_products)) {
            return '<div class="mulopimfwc-recommendations-notice">' .
                esc_html__('No recommendations available yet for this location.', 'multi-location-product-and-inventory-management') .
                '</div>';
        }

        $product_ids = array_keys($popular_products);

        $args = [
            'post_type' => 'product',
            'post__in' => $product_ids,
            'posts_per_page' => intval($atts['limit']),
            'orderby' => 'post__in'
        ];

        $query = new WP_Query($args);

        ob_start();
    ?>
        <div class="mulopimfwc-recommendations-container" data-columns="<?php echo esc_attr($atts['columns']); ?>">
            <?php if ($atts['show_title'] === 'yes'): ?>
                <h2 class="mulopimfwc-recommendations-title">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" fill="currentColor" />
                    </svg>
                    <?php echo esc_html($title); ?>
                </h2>
            <?php endif; ?>

            <div class="mulopimfwc-recommendations-grid columns-<?php echo esc_attr($atts['columns']); ?>">
                <?php
                while ($query->have_posts()) {
                    $query->the_post();
                    global $product;

                    $product_id = $product->get_id();
                    $popularity_data = isset($popular_products[$product_id]) ? $popular_products[$product_id] : null;
                ?>
                    <div class="mulopimfwc-recommendation-item">
                        <?php if ($atts['show_badge'] === 'yes' && $popularity_data): ?>
                            <div class="mulopimfwc-popularity-badge" title="<?php echo esc_attr(sprintf(__('Views: %d | Purchases: %d', 'multi-location-product-and-inventory-management'), $popularity_data['view_count'], $popularity_data['purchase_count'])); ?>">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" fill="currentColor" />
                                </svg>
                                <?php esc_html_e('Popular', 'multi-location-product-and-inventory-management'); ?>
                            </div>
                        <?php endif; ?>

                        <a href="<?php echo esc_url(get_permalink()); ?>" class="mulopimfwc-recommendation-link">
                            <div class="mulopimfwc-recommendation-image">
                                <?php echo wp_kses_post($product->get_image('woocommerce_thumbnail')); ?>
                            </div>

                            <div class="mulopimfwc-recommendation-details">
                                <h3 class="mulopimfwc-recommendation-product-title">
                                    <?php echo esc_html($product->get_name()); ?>
                                </h3>

                                <div class="mulopimfwc-recommendation-price">
                                    <?php echo wp_kses_post($product->get_price_html()); ?>
                                </div>

                                <?php if ($popularity_data && $popularity_data['purchase_count'] > 0): ?>
                                    <div class="mulopimfwc-recommendation-stats">
                                        <?php echo esc_html(sprintf(_n('%d purchase', '%d purchases', $popularity_data['purchase_count'], 'multi-location-product-and-inventory-management'), $popularity_data['purchase_count'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </a>

                        <div class="mulopimfwc-recommendation-actions">
                            <?php woocommerce_template_loop_add_to_cart(); ?>
                        </div>
                    </div>
                <?php
                }
                wp_reset_postdata();
                ?>
            </div>
        </div>
    <?php
        return ob_get_clean();
    }

    /**
     * AJAX handler for getting recommendations
     */
    public function ajax_get_recommendations()
    {
        check_ajax_referer('mulopimfwc_recommendations', 'nonce');

        $location_slug = isset($_POST['location']) ? sanitize_text_field(wp_unslash($_POST['location'])) : '';
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 8;

        if (empty($location_slug)) {
            wp_send_json_error(['message' => __('Location not specified', 'multi-location-product-and-inventory-management')]);
        }

        $products = $this->get_popular_products($location_slug, $limit);

        wp_send_json_success([
            'products' => $products,
            'count' => count($products)
        ]);
    }

    /**
     * Add analytics menu to admin
     */
    public function add_analytics_menu()
    {
        add_submenu_page(
            'multi-location-product-and-inventory-management',
            __('Location Analytics', 'multi-location-product-and-inventory-management'),
            __('Analytics', 'multi-location-product-and-inventory-management'),
            'manage_woocommerce',
            'mulopimfwc-analytics',
            [$this, 'render_analytics_page']
        );
    }

    /**
     * Render analytics dashboard page
     */
   /**
     * Render analytics dashboard page
     */
    public function render_analytics_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $locations = get_terms([
            'taxonomy' => 'mulopimfwc_store_location',
            'hide_empty' => false
        ]);

        // Calculate global statistics
        $global_stats = [
            'total_views' => 0,
            'total_purchases' => 0,
            'total_users' => 0,
            'total_sessions' => 0,
            'top_location' => null,
            'top_location_score' => 0,
            'top_product' => null,
            'top_product_score' => 0,
            'top_product_location' => null
        ];

        $location_scores = [];
        $all_products = [];

        foreach ($locations as $location) {
            $stats = $this->get_location_stats($location->slug);
            $global_stats['total_views'] += $stats['total_views'];
            $global_stats['total_purchases'] += $stats['total_purchases'];
            $global_stats['total_users'] += $stats['unique_users'];
            $global_stats['total_sessions'] += $stats['unique_sessions'];

            $location_score = ($stats['total_purchases'] * 10) + $stats['total_views'];
            $location_scores[$location->slug] = [
                'name' => $location->name,
                'score' => $location_score,
                'stats' => $stats
            ];

            // Get products for this location
            $products = $this->get_popular_products($location->slug, 100);
            foreach ($products as $product_id => $product_data) {
                if (!isset($all_products[$product_id])) {
                    $all_products[$product_id] = [
                        'score' => 0,
                        'views' => 0,
                        'purchases' => 0,
                        'locations' => []
                    ];
                }
                $all_products[$product_id]['score'] += $product_data['popularity_score'];
                $all_products[$product_id]['views'] += $product_data['view_count'];
                $all_products[$product_id]['purchases'] += $product_data['purchase_count'];
                $all_products[$product_id]['locations'][] = $location->name;
            }
        }

        // Find top performing location
        if (!empty($location_scores)) {
            uasort($location_scores, function ($a, $b) {
                return $b['score'] - $a['score'];
            });
            $top_location_data = reset($location_scores);
            $global_stats['top_location'] = $top_location_data['name'];
            $global_stats['top_location_stats'] = $top_location_data['stats'];
        }

        // Find top selling product globally
        if (!empty($all_products)) {
            uasort($all_products, function ($a, $b) {
                return $b['score'] - $a['score'];
            });
            $top_product_id = key($all_products);
            $top_product = wc_get_product($top_product_id);
            if ($top_product) {
                $global_stats['top_product'] = $top_product->get_name();
                $global_stats['top_product_id'] = $top_product_id;
                $global_stats['top_product_data'] = $all_products[$top_product_id];
            }
        }

        // Calculate conversion rate
        $conversion_rate = $global_stats['total_views'] > 0 
            ? ($global_stats['total_purchases'] / $global_stats['total_views']) * 100 
            : 0;

    ?>
        <div class="wrap mulopimfwc-analytics-wrap">
            <h1>
                <span class="dashicons dashicons-chart-area"></span>
                <?php esc_html_e('Location Analytics Dashboard', 'multi-location-product-and-inventory-management'); ?>
            </h1>

            <!-- Global Overview Section -->
            <div class="mulopimfwc-global-overview">
                <h2><?php esc_html_e('Global Overview', 'multi-location-product-and-inventory-management'); ?></h2>
                
                <div class="mulopimfwc-overview-grid">
                    <div class="overview-card primary">
                        <div class="card-icon">
                            <span class="dashicons dashicons-visibility"></span>
                        </div>
                        <div class="card-content">
                            <span class="card-label"><?php esc_html_e('Total Views', 'multi-location-product-and-inventory-management'); ?></span>
                            <span class="card-value"><?php echo esc_html(number_format($global_stats['total_views'])); ?></span>
                        </div>
                    </div>

                    <div class="overview-card success">
                        <div class="card-icon">
                            <span class="dashicons dashicons-cart"></span>
                        </div>
                        <div class="card-content">
                            <span class="card-label"><?php esc_html_e('Total Purchases', 'multi-location-product-and-inventory-management'); ?></span>
                            <span class="card-value"><?php echo esc_html(number_format($global_stats['total_purchases'])); ?></span>
                        </div>
                    </div>

                    <div class="overview-card info">
                        <div class="card-icon">
                            <span class="dashicons dashicons-groups"></span>
                        </div>
                        <div class="card-content">
                            <span class="card-label"><?php esc_html_e('Total Users', 'multi-location-product-and-inventory-management'); ?></span>
                            <span class="card-value"><?php echo esc_html(number_format($global_stats['total_users'])); ?></span>
                        </div>
                    </div>

                    <div class="overview-card warning">
                        <div class="card-icon">
                            <span class="dashicons dashicons-chart-line"></span>
                        </div>
                        <div class="card-content">
                            <span class="card-label"><?php esc_html_e('Conversion Rate', 'multi-location-product-and-inventory-management'); ?></span>
                            <span class="card-value"><?php echo esc_html(number_format($conversion_rate, 2)); ?>%</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Performers Section -->
            <div class="mulopimfwc-top-performers">
                <div class="top-performer-card">
                    <div class="performer-header">
                        <span class="dashicons dashicons-location-alt"></span>
                        <h3><?php esc_html_e('Top Performing Location', 'multi-location-product-and-inventory-management'); ?></h3>
                    </div>
                    <?php if ($global_stats['top_location']): ?>
                        <div class="performer-content">
                            <div class="performer-name"><?php echo esc_html($global_stats['top_location']); ?></div>
                            <div class="performer-stats">
                                <div class="performer-stat">
                                    <span class="stat-label"><?php esc_html_e('Views', 'multi-location-product-and-inventory-management'); ?></span>
                                    <span class="stat-value"><?php echo esc_html(number_format($global_stats['top_location_stats']['total_views'])); ?></span>
                                </div>
                                <div class="performer-stat">
                                    <span class="stat-label"><?php esc_html_e('Purchases', 'multi-location-product-and-inventory-management'); ?></span>
                                    <span class="stat-value"><?php echo esc_html(number_format($global_stats['top_location_stats']['total_purchases'])); ?></span>
                                </div>
                                <div class="performer-stat">
                                    <span class="stat-label"><?php esc_html_e('Users', 'multi-location-product-and-inventory-management'); ?></span>
                                    <span class="stat-value"><?php echo esc_html(number_format($global_stats['top_location_stats']['unique_users'])); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="no-data"><?php esc_html_e('No data available yet', 'multi-location-product-and-inventory-management'); ?></p>
                    <?php endif; ?>
                </div>

                <div class="top-performer-card">
                    <div class="performer-header">
                        <span class="dashicons dashicons-products"></span>
                        <h3><?php esc_html_e('Top Selling Product', 'multi-location-product-and-inventory-management'); ?></h3>
                    </div>
                    <?php if ($global_stats['top_product']): ?>
                        <div class="performer-content">
                            <div class="performer-name">
                                <a href="<?php echo esc_url(get_edit_post_link($global_stats['top_product_id'])); ?>">
                                    <?php echo esc_html($global_stats['top_product']); ?>
                                </a>
                            </div>
                            <div class="performer-stats">
                                <div class="performer-stat">
                                    <span class="stat-label"><?php esc_html_e('Total Views', 'multi-location-product-and-inventory-management'); ?></span>
                                    <span class="stat-value"><?php echo esc_html(number_format($global_stats['top_product_data']['views'])); ?></span>
                                </div>
                                <div class="performer-stat">
                                    <span class="stat-label"><?php esc_html_e('Total Purchases', 'multi-location-product-and-inventory-management'); ?></span>
                                    <span class="stat-value"><?php echo esc_html(number_format($global_stats['top_product_data']['purchases'])); ?></span>
                                </div>
                                <div class="performer-stat">
                                    <span class="stat-label"><?php esc_html_e('Locations', 'multi-location-product-and-inventory-management'); ?></span>
                                    <span class="stat-value"><?php echo esc_html(count($global_stats['top_product_data']['locations'])); ?></span>
                                </div>
                            </div>
                            <div class="performer-locations">
                                <small><?php echo esc_html(implode(', ', array_slice($global_stats['top_product_data']['locations'], 0, 3))); ?></small>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="no-data"><?php esc_html_e('No data available yet', 'multi-location-product-and-inventory-management'); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Location Performance Rankings -->
            <div class="mulopimfwc-location-rankings">
                <h2><?php esc_html_e('Location Performance Rankings', 'multi-location-product-and-inventory-management'); ?></h2>
                <div class="rankings-table-wrapper">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th class="rank-column"><?php esc_html_e('Rank', 'multi-location-product-and-inventory-management'); ?></th>
                                <th><?php esc_html_e('Location', 'multi-location-product-and-inventory-management'); ?></th>
                                <th class="num-column"><?php esc_html_e('Views', 'multi-location-product-and-inventory-management'); ?></th>
                                <th class="num-column"><?php esc_html_e('Purchases', 'multi-location-product-and-inventory-management'); ?></th>
                                <th class="num-column"><?php esc_html_e('Users', 'multi-location-product-and-inventory-management'); ?></th>
                                <th class="num-column"><?php esc_html_e('Conversion', 'multi-location-product-and-inventory-management'); ?></th>
                                <th class="num-column"><?php esc_html_e('Score', 'multi-location-product-and-inventory-management'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rank = 1;
                            foreach ($location_scores as $slug => $data): 
                                $location_conversion = $data['stats']['total_views'] > 0 
                                    ? ($data['stats']['total_purchases'] / $data['stats']['total_views']) * 100 
                                    : 0;
                            ?>
                                <tr>
                                    <td class="rank-column">
                                        <?php if ($rank === 1): ?>
                                            <span class="rank-badge gold">🥇 <?php echo esc_html($rank); ?></span>
                                        <?php elseif ($rank === 2): ?>
                                            <span class="rank-badge silver">🥈 <?php echo esc_html($rank); ?></span>
                                        <?php elseif ($rank === 3): ?>
                                            <span class="rank-badge bronze">🥉 <?php echo esc_html($rank); ?></span>
                                        <?php else: ?>
                                            <span class="rank-badge"><?php echo esc_html($rank); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo esc_html($data['name']); ?></strong></td>
                                    <td class="num-column"><?php echo esc_html(number_format($data['stats']['total_views'])); ?></td>
                                    <td class="num-column"><?php echo esc_html(number_format($data['stats']['total_purchases'])); ?></td>
                                    <td class="num-column"><?php echo esc_html(number_format($data['stats']['unique_users'])); ?></td>
                                    <td class="num-column"><?php echo esc_html(number_format($location_conversion, 2)); ?>%</td>
                                    <td class="num-column"><strong><?php echo esc_html(number_format($data['score'])); ?></strong></td>
                                </tr>
                            <?php 
                                $rank++;
                            endforeach; 
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Individual Location Details -->
            <div class="mulopimfwc-location-details">
                <h2><?php esc_html_e('Location Details', 'multi-location-product-and-inventory-management'); ?></h2>
                
                <div class="mulopimfwc-analytics-dashboard">
                    <?php foreach ($locations as $location): ?>
                        <?php
                        $stats = $this->get_location_stats($location->slug);
                        $top_products = $this->get_popular_products($location->slug, 5);
                        $location_conversion = $stats['total_views'] > 0 
                            ? ($stats['total_purchases'] / $stats['total_views']) * 100 
                            : 0;
                        ?>

                        <div class="mulopimfwc-location-analytics-card">
                            <div class="card-header">
                                <h3><?php echo esc_html($location->name); ?></h3>
                                <button type="button" class="button button-secondary export-location-btn" data-location="<?php echo esc_attr($location->slug); ?>">
                                    <span class="dashicons dashicons-download"></span>
                                    <?php esc_html_e('Export', 'multi-location-product-and-inventory-management'); ?>
                                </button>
                            </div>

                            <div class="mulopimfwc-analytics-stats">
                                <div class="stat-box">
                                    <div class="stat-icon"><span class="dashicons dashicons-groups"></span></div>
                                    <div class="stat-info">
                                        <span class="stat-label"><?php esc_html_e('Unique Users', 'multi-location-product-and-inventory-management'); ?></span>
                                        <span class="stat-value"><?php echo esc_html(number_format($stats['unique_users'])); ?></span>
                                    </div>
                                </div>

                                <div class="stat-box">
                                    <div class="stat-icon"><span class="dashicons dashicons-admin-page"></span></div>
                                    <div class="stat-info">
                                        <span class="stat-label"><?php esc_html_e('Sessions', 'multi-location-product-and-inventory-management'); ?></span>
                                        <span class="stat-value"><?php echo esc_html(number_format($stats['unique_sessions'])); ?></span>
                                    </div>
                                </div>

                                <div class="stat-box">
                                    <div class="stat-icon"><span class="dashicons dashicons-visibility"></span></div>
                                    <div class="stat-info">
                                        <span class="stat-label"><?php esc_html_e('Product Views', 'multi-location-product-and-inventory-management'); ?></span>
                                        <span class="stat-value"><?php echo esc_html(number_format($stats['total_views'])); ?></span>
                                    </div>
                                </div>

                                <div class="stat-box">
                                    <div class="stat-icon"><span class="dashicons dashicons-cart"></span></div>
                                    <div class="stat-info">
                                        <span class="stat-label"><?php esc_html_e('Purchases', 'multi-location-product-and-inventory-management'); ?></span>
                                        <span class="stat-value"><?php echo esc_html(number_format($stats['total_purchases'])); ?></span>
                                    </div>
                                </div>

                                <div class="stat-box highlight">
                                    <div class="stat-icon"><span class="dashicons dashicons-chart-line"></span></div>
                                    <div class="stat-info">
                                        <span class="stat-label"><?php esc_html_e('Conversion Rate', 'multi-location-product-and-inventory-management'); ?></span>
                                        <span class="stat-value"><?php echo esc_html(number_format($location_conversion, 2)); ?>%</span>
                                    </div>
                                </div>

                                <div class="stat-box highlight">
                                    <div class="stat-icon"><span class="dashicons dashicons-star-filled"></span></div>
                                    <div class="stat-info">
                                        <span class="stat-label"><?php esc_html_e('Performance Score', 'multi-location-product-and-inventory-management'); ?></span>
                                        <span class="stat-value"><?php echo esc_html(number_format(($stats['total_purchases'] * 10) + $stats['total_views'])); ?></span>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($top_products)): ?>
                                <div class="mulopimfwc-top-products">
                                    <h4><?php esc_html_e('Top 5 Products', 'multi-location-product-and-inventory-management'); ?></h4>
                                    <table class="widefat">
                                        <thead>
                                            <tr>
                                                <th class="rank-col">#</th>
                                                <th><?php esc_html_e('Product', 'multi-location-product-and-inventory-management'); ?></th>
                                                <th class="num-col"><?php esc_html_e('Views', 'multi-location-product-and-inventory-management'); ?></th>
                                                <th class="num-col"><?php esc_html_e('Purchases', 'multi-location-product-and-inventory-management'); ?></th>
                                                <th class="num-col"><?php esc_html_e('Score', 'multi-location-product-and-inventory-management'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $product_rank = 1;
                                            foreach ($top_products as $product_id => $product_data): 
                                                $product = wc_get_product($product_id);
                                                if (!$product) continue;
                                            ?>
                                                <tr>
                                                    <td class="rank-col"><?php echo esc_html($product_rank); ?></td>
                                                    <td>
                                                        <a href="<?php echo esc_url(get_edit_post_link($product_id)); ?>">
                                                            <?php echo esc_html($product->get_name()); ?>
                                                        </a>
                                                    </td>
                                                    <td class="num-col"><?php echo esc_html($product_data['view_count']); ?></td>
                                                    <td class="num-col"><strong><?php echo esc_html($product_data['purchase_count']); ?></strong></td>
                                                    <td class="num-col"><?php echo esc_html(number_format($product_data['popularity_score'])); ?></td>
                                                </tr>
                                            <?php 
                                                $product_rank++;
                                            endforeach; 
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="no-products-message">
                                    <span class="dashicons dashicons-info"></span>
                                    <p><?php esc_html_e('No product data available yet for this location.', 'multi-location-product-and-inventory-management'); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <style>
                .mulopimfwc-analytics-wrap {
                    margin-right: 20px;
                }

                .mulopimfwc-analytics-wrap h1 {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    margin-bottom: 20px;
                }

                .mulopimfwc-analytics-wrap h1 .dashicons {
                    font-size: 32px;
                    width: 32px;
                    height: 32px;
                }

                /* Global Overview */
                .mulopimfwc-global-overview {
                    background: #fff;
                    border: 1px solid #ddd;
                    border-radius: 8px;
                    padding: 25px;
                    margin-bottom: 20px;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
                }

                .mulopimfwc-global-overview h2 {
                    margin-top: 0;
                    margin-bottom: 20px;
                    font-size: 20px;
                    color: #1d2327;
                }

                .mulopimfwc-overview-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 20px;
                }

                .overview-card {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    border-radius: 10px;
                    padding: 20px;
                    color: #fff;
                    display: flex;
                    align-items: center;
                    gap: 15px;
                    transition: transform 0.2s, box-shadow 0.2s;
                }

                .overview-card:hover {
                    transform: translateY(-5px);
                    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
                }

                .overview-card.primary {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                }

                .overview-card.success {
                    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
                }

                .overview-card.info {
                    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
                }

                .overview-card.warning {
                    background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
                }

                .overview-card .card-icon {
                    font-size: 40px;
                    opacity: 0.9;
                }

                .overview-card .card-icon .dashicons {
                    width: 40px;
                    height: 40px;
                    font-size: 40px;
                }

                .overview-card .card-content {
                    display: flex;
                    flex-direction: column;
                    gap: 5px;
                }

                .overview-card .card-label {
                    font-size: 13px;
                    opacity: 0.9;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }

                .overview-card .card-value {
                    font-size: 28px;
                    font-weight: bold;
                    line-height: 1;
                }

                /* Top Performers */
                .mulopimfwc-top-performers {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
                    gap: 20px;
                    margin-bottom: 20px;
                }

                .top-performer-card {
                    background: #fff;
                    border: 1px solid #ddd;
                    border-radius: 8px;
                    padding: 20px;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
                }

                .performer-header {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    margin-bottom: 15px;
                    padding-bottom: 15px;
                    border-bottom: 2px solid #f0f0f1;
                }

                .performer-header .dashicons {
                    font-size: 24px;
                    width: 24px;
                    height: 24px;
                    color: #2271b1;
                }

                .performer-header h3 {
                    margin: 0;
                    font-size: 16px;
                    color: #1d2327;
                }

                .performer-name {
                    font-size: 20px;
                    font-weight: bold;
                    color: #1d2327;
                    margin-bottom: 15px;
                }

                .performer-name a {
                    color: #2271b1;
                    text-decoration: none;
                }

                .performer-name a:hover {
                    color: #135e96;
                    text-decoration: underline;
                }

                .performer-stats {
                    display: grid;
                    grid-template-columns: repeat(3, 1fr);
                    gap: 15px;
                    margin-bottom: 10px;
                }

                .performer-stat {
                    display: flex;
                    flex-direction: column;
                    gap: 5px;
                }

                .performer-stat .stat-label {
                    font-size: 12px;
                    color: #646970;
                    text-transform: uppercase;
                }

                .performer-stat .stat-value {
                    font-size: 22px;
                    font-weight: bold;
                    color: #1d2327;
                }

                .performer-locations {
                    margin-top: 10px;
                    padding-top: 10px;
                    border-top: 1px solid #f0f0f1;
                }

                .performer-locations small {
                    color: #646970;
                }

                .no-data {
                    color: #646970;
                    font-style: italic;
                    text-align: center;
                    padding: 20px;
                }

                /* Location Rankings */
                .mulopimfwc-location-rankings {
                    background: #fff;
                    border: 1px solid #ddd;
                    border-radius: 8px;
                    padding: 25px;
                    margin-bottom: 20px;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
                }

                .mulopimfwc-location-rankings h2 {
                    margin-top: 0;
                    margin-bottom: 20px;
                    font-size: 20px;
                    color: #1d2327;
                }

                .rankings-table-wrapper {
                    overflow-x: auto;
                }

                .mulopimfwc-location-rankings table {
                    width: 100%;
                }

                .rank-column {
                    width: 80px;
                    text-align: left;
                }

                .num-column {
                    text-align: center !important;
                    width: 100px;
                }

                .rank-badge {
                    display: inline-block;
                    padding: 5px 10px;
                    border-radius: 5px;
                    font-weight: bold;
                    font-size: 14px;
                }

                .rank-badge.gold {
                    background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
                    color: #1d2327;
                }

                .rank-badge.silver {
                    background: linear-gradient(135deg, #c0c0c0 0%, #e8e8e8 100%);
                    color: #1d2327;
                }

                .rank-badge.bronze {
                    background: linear-gradient(135deg, #cd7f32 0%, #e8a87c 100%);
                    color: #fff;
                }

                /* Location Details */
                .mulopimfwc-location-details {
                    margin-bottom: 20px;
                }

                .mulopimfwc-location-details > h2 {
                    font-size: 20px;
                    color: #1d2327;
                    margin-bottom: 20px;
                }

                .mulopimfwc-analytics-dashboard {
                    display: grid;
                    gap: 20px;
                }

                .mulopimfwc-location-analytics-card {
                    background: #fff;
                    border: 1px solid #ddd;
                    border-radius: 8px;
                    padding: 20px;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
                }

                .mulopimfwc-location-analytics-card .card-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 20px;
                    padding-bottom: 15px;
                    border-bottom: 2px solid #f0f0f1;
                }

                .mulopimfwc-location-analytics-card .card-header h3 {
                    margin: 0;
                    font-size: 18px;
                    color: #1d2327;
                }

                .export-location-btn {
                    display: flex;
                    align-items: center;
                    gap: 5px;
                }

                .export-location-btn .dashicons {
                    font-size: 16px;
                    width: 16px;
                    height: 16px;
                }

                .mulopimfwc-analytics-stats {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                    gap: 15px;
                    margin-bottom: 20px;
                }

                .stat-box {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    padding: 15px;
                    background: #f9f9f9;
                    border-radius: 8px;
                    border: 1px solid #e5e5e5;
                    transition: all 0.2s;
                }

                .stat-box:hover {
                    background: #f0f0f1;
                    border-color: #2271b1;
                }

                .stat-box.highlight {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: #fff;
                    border: none;
                }

                .stat-box.highlight .stat-icon .dashicons {
                    color: #fff;
                }

                .stat-box.highlight .stat-label {
                    color: rgba(255, 255, 255, 0.9);
                }

                .stat-box.highlight .stat-value {
                    color: #fff;
                }

                .stat-icon {
                    font-size: 32px;
                }

                .stat-icon .dashicons {
                    width: 32px;
                    height: 32px;
                    font-size: 32px;
                    color: #2271b1;
                }

                .stat-info {
                    display: flex;
                    flex-direction: column;
                    gap: 5px;
                }

                .stat-label {
                    font-size: 11px;
                    color: #646970;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    font-weight: 600;
                }

                .stat-value {
                    font-size: 24px;
                    font-weight: bold;
                    color: #1d2327;
                    line-height: 1;
                }

                /* Top Products */
                .mulopimfwc-top-products {
                    margin-top: 20px;
                }

                .mulopimfwc-top-products h4 {
                    margin: 0 0 15px 0;
                    font-size: 16px;
                    color: #1d2327;
                }

                .mulopimfwc-top-products table {
                    width: 100%;
                    border-collapse: collapse;
                }

                .mulopimfwc-top-products table thead {
                    background: #f9f9f9;
                }

                .mulopimfwc-top-products table th {
                    padding: 12px;
                    text-align: left;
                    font-weight: 600;
                    font-size: 13px;
                    color: #646970;
                    border-bottom: 2px solid #e5e5e5;
                }

                .mulopimfwc-top-products table td {
                    padding: 12px;
                    border-bottom: 1px solid #f0f0f1;
                }

                .mulopimfwc-top-products table tbody tr:hover {
                    background: #f9f9f9;
                }

                .mulopimfwc-top-products .rank-col {
                    width: 40px;
                    text-align: center;
                    font-weight: bold;
                    color: #2271b1;
                }

                .mulopimfwc-top-products .num-col {
                    text-align: right;
                    width: 80px;
                }

                .mulopimfwc-top-products table a {
                    color: #2271b1;
                    text-decoration: none;
                }

                .mulopimfwc-top-products table a:hover {
                    color: #135e96;
                    text-decoration: underline;
                }

                .no-products-message {
                    text-align: center;
                    padding: 40px 20px;
                    color: #646970;
                }

                .no-products-message .dashicons {
                    font-size: 48px;
                    width: 48px;
                    height: 48px;
                    opacity: 0.3;
                    margin-bottom: 10px;
                }

                .no-products-message p {
                    margin: 0;
                    font-style: italic;
                }

                /* Responsive Design */
                @media (max-width: 782px) {
                    .mulopimfwc-overview-grid {
                        grid-template-columns: 1fr;
                    }

                    .mulopimfwc-top-performers {
                        grid-template-columns: 1fr;
                    }

                    .mulopimfwc-analytics-stats {
                        grid-template-columns: 1fr;
                    }

                    .performer-stats {
                        grid-template-columns: 1fr;
                    }

                    .overview-card .card-value {
                        font-size: 24px;
                    }

                    .stat-value {
                        font-size: 20px;
                    }
                }

                @media (max-width: 600px) {
                    .mulopimfwc-location-analytics-card .card-header {
                        flex-direction: column;
                        align-items: flex-start;
                        gap: 10px;
                    }

                    .rankings-table-wrapper {
                        overflow-x: scroll;
                    }
                }
            </style>
        </div>
    <?php
    }

    /**
     * Export analytics data
     */
    public function export_analytics_data($location_slug = null)
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $popularity_data = get_option(self::POPULARITY_OPTION, []);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="location-analytics-' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');

        // Add BOM for UTF-8
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($output, ['Location', 'Product ID', 'Product Name', 'Views', 'Purchases', 'Popularity Score']);

        foreach ($popularity_data as $loc_slug => $products) {
            if ($location_slug && $loc_slug !== $location_slug) {
                continue;
            }

            $location_term = get_term_by('slug', $loc_slug, 'mulopimfwc_store_location');
            $location_name = $location_term ? $location_term->name : $loc_slug;

            foreach ($products as $product_id => $data) {
                $product = wc_get_product($product_id);
                $product_name = $product ? $product->get_name() : 'Unknown Product';

                fputcsv($output, [
                    $location_name,
                    $product_id,
                    $product_name,
                    $data['view_count'],
                    $data['purchase_count'],
                    $data['popularity_score']
                ]);
            }
        }

        fclose($output);
        exit;
    }

    /**
     * Get data size for admin display
     */
    public function get_data_size_info()
    {
        $tracking_data = get_option(self::TRACKING_OPTION, []);
        $popularity_data = get_option(self::POPULARITY_OPTION, []);
        $stats_data = get_option(self::STATS_OPTION, []);

        $total_products = 0;
        foreach ($popularity_data as $location => $products) {
            $total_products += count($products);
        }

        return [
            'tracking_entries' => count($tracking_data),
            'total_locations' => count($popularity_data),
            'total_products' => $total_products,
            'total_stats_locations' => count($stats_data)
        ];
    }
}

// AJAX handler for tracking location selection
add_action('wp_ajax_mulopimfwc_track_location_selection', 'mulopimfwc_ajax_track_location_selection');
add_action('wp_ajax_nopriv_mulopimfwc_track_location_selection', 'mulopimfwc_ajax_track_location_selection');

function mulopimfwc_ajax_track_location_selection()
{
    check_ajax_referer('mulopimfwc_tracking', 'nonce');

    $location_slug = isset($_POST['location_slug']) ? sanitize_text_field(wp_unslash($_POST['location_slug'])) : '';
    $location_name = isset($_POST['location_name']) ? sanitize_text_field(wp_unslash($_POST['location_name'])) : '';

    if (empty($location_slug) || empty($location_name)) {
        wp_send_json_error(['message' => __('Invalid location data', 'multi-location-product-and-inventory-management')]);
    }

    $instance = Mulopimfwc_Customer_Location_Insights::get_instance();

    // Use reflection to call private method
    $reflection = new ReflectionClass($instance);
    $method = $reflection->getMethod('log_action');
    $method->setAccessible(true);
    $method->invoke($instance, 'selection', [
        'slug' => $location_slug,
        'name' => $location_name
    ]);

    wp_send_json_success(['message' => __('Location tracked', 'multi-location-product-and-inventory-management')]);
}

// AJAX handler for exporting analytics
add_action('wp_ajax_mulopimfwc_export_analytics', 'mulopimfwc_ajax_export_analytics');

function mulopimfwc_ajax_export_analytics()
{
    check_ajax_referer('mulopimfwc_analytics_export', 'nonce');

    if (!current_user_can('manage_woocommerce')) {
        wp_die(__('You do not have permission to export analytics.'));
    }

    $location_slug = isset($_POST['location']) ? sanitize_text_field(wp_unslash($_POST['location'])) : null;

    $instance = Mulopimfwc_Customer_Location_Insights::get_instance();
    $instance->export_analytics_data($location_slug);
}

// Hook for GDPR user data deletion
add_action('delete_user', 'mulopimfwc_delete_user_tracking_data');

function mulopimfwc_delete_user_tracking_data($user_id)
{
    $instance = Mulopimfwc_Customer_Location_Insights::get_instance();
    $instance->clear_user_data($user_id);
}

// Add export button to analytics page
add_action('admin_footer-toplevel_page_multi-location-product-and-inventory-management', 'mulopimfwc_add_export_button_script');

function mulopimfwc_add_export_button_script()
{
    $screen = get_current_screen();
    if ($screen && $screen->id === 'multi-location-product-and-inventory-management_page_mulopimfwc-analytics') {
    ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Add export button to each location card
                $('.mulopimfwc-location-analytics-card h2').each(function() {
                    var locationName = $(this).text();
                    var locationSlug = $(this).closest('.mulopimfwc-location-analytics-card')
                        .find('table tbody tr:first a').attr('href');

                    if (locationSlug) {
                        // Extract location slug from URL if possible
                        var match = locationSlug.match(/post=(\d+)/);
                        if (match) {
                            var exportBtn = $('<button type="button" class="button button-secondary" style="float: right; margin-top: -5px;">Export CSV</button>');
                            exportBtn.on('click', function() {
                                var form = $('<form>', {
                                    'method': 'POST',
                                    'action': ajaxurl
                                });

                                form.append($('<input>', {
                                    'type': 'hidden',
                                    'name': 'action',
                                    'value': 'mulopimfwc_export_analytics'
                                }));

                                form.append($('<input>', {
                                    'type': 'hidden',
                                    'name': 'nonce',
                                    'value': '<?php echo esc_js(wp_create_nonce('mulopimfwc_analytics_export')); ?>'
                                }));

                                $('body').append(form);
                                form.submit();
                            });

                            $(this).append(exportBtn);
                        }
                    }
                });

                // Add global export button
                $('.wrap h1').append(' <button type="button" class="button button-primary mulopimfwc-export-all">Export All Data</button>');

                $('.mulopimfwc-export-all').on('click', function() {
                    var form = $('<form>', {
                        'method': 'POST',
                        'action': ajaxurl
                    });

                    form.append($('<input>', {
                        'type': 'hidden',
                        'name': 'action',
                        'value': 'mulopimfwc_export_analytics'
                    }));

                    form.append($('<input>', {
                        'type': 'hidden',
                        'name': 'nonce',
                        'value': '<?php echo esc_js(wp_create_nonce('mulopimfwc_analytics_export')); ?>'
                    }));

                    $('body').append(form);
                    form.submit();
                });
            });
        </script>
    <?php
    }
}

// Add data size info to settings page
// add_action('mulopimfwc_after_customer_insights_settings', 'mulopimfwc_display_data_size_info');

function mulopimfwc_display_data_size_info()
{
    $instance = Mulopimfwc_Customer_Location_Insights::get_instance();
    $info = $instance->get_data_size_info();
    ?>
    <div class="mulopimfwc-data-info" style="margin-top: 20px; padding: 15px; background: #f0f8ff; border-left: 4px solid #0073aa; border-radius: 4px;">
        <h3 style="margin-top: 0;"><?php esc_html_e('Analytics Data Summary', 'multi-location-product-and-inventory-management'); ?></h3>
        <ul style="margin: 0; list-style: none; padding: 0;">
            <li><strong><?php esc_html_e('Tracking Entries:', 'multi-location-product-and-inventory-management'); ?></strong> <?php echo esc_html($info['tracking_entries']); ?> / <?php echo esc_html(Mulopimfwc_Customer_Location_Insights::MAX_TRACKING_ENTRIES); ?></li>
            <li><strong><?php esc_html_e('Locations Tracked:', 'multi-location-product-and-inventory-management'); ?></strong> <?php echo esc_html($info['total_locations']); ?></li>
            <li><strong><?php esc_html_e('Products with Data:', 'multi-location-product-and-inventory-management'); ?></strong> <?php echo esc_html($info['total_products']); ?></li>
            <li style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd;">
                <em><?php esc_html_e('Data is automatically cleaned every 24 hours. Old entries (90+ days) are removed.', 'multi-location-product-and-inventory-management'); ?></em>
            </li>
        </ul>

        <button type="button" class="button button-secondary" id="mulopimfwc-clear-analytics" style="margin-top: 15px;">
            <?php esc_html_e('Clear All Analytics Data', 'multi-location-product-and-inventory-management'); ?>
        </button>

        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#mulopimfwc-clear-analytics').on('click', function() {
                    if (confirm('<?php echo esc_js(__('Are you sure you want to clear all analytics data? This action cannot be undone.', 'multi-location-product-and-inventory-management')); ?>')) {
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'mulopimfwc_clear_analytics_data',
                                nonce: '<?php echo esc_js(wp_create_nonce('mulopimfwc_clear_analytics')); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    alert('<?php echo esc_js(__('Analytics data cleared successfully.', 'multi-location-product-and-inventory-management')); ?>');
                                    location.reload();
                                } else {
                                    alert('<?php echo esc_js(__('Failed to clear analytics data.', 'multi-location-product-and-inventory-management')); ?>');
                                }
                            }
                        });
                    }
                });
            });
        </script>
    </div>
<?php
}

// AJAX handler for clearing analytics data
add_action('wp_ajax_mulopimfwc_clear_analytics_data', 'mulopimfwc_ajax_clear_analytics_data');

function mulopimfwc_ajax_clear_analytics_data()
{
    check_ajax_referer('mulopimfwc_clear_analytics', 'nonce');

    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(['message' => __('You do not have permission to clear analytics data.', 'multi-location-product-and-inventory-management')]);
    }

    // Clear all analytics options
    delete_option(Mulopimfwc_Customer_Location_Insights::TRACKING_OPTION);
    delete_option(Mulopimfwc_Customer_Location_Insights::POPULARITY_OPTION);
    delete_option(Mulopimfwc_Customer_Location_Insights::STATS_OPTION);

    wp_send_json_success(['message' => __('Analytics data cleared successfully.', 'multi-location-product-and-inventory-management')]);
}

// Initialize the class
function mulopimfwc_init_customer_insights()
{
    return Mulopimfwc_Customer_Location_Insights::get_instance();
}
add_action('plugins_loaded', 'mulopimfwc_init_customer_insights', 100);

// Add settings section hook (for data size info display)
function mulopimfwc_customer_insights_settings_hook()
{
    do_action('mulopimfwc_after_customer_insights_settings');
}
add_action('admin_init', 'mulopimfwc_customer_insights_settings_hook');
