<?php

/**
 * Customer Location Insights & Recommendations (Options-Based)
 * 
 * Tracks customer location preferences using WordPress options
 * Much faster and simpler than database tables
 * 
 * @package Multi Location Product & Inventory Management
 * @since 1.1.3.87
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
     * Per-request cache for order stats (reduces repeat queries during rendering & AJAX).
     *
     * @var array|null
     */
    private $order_stats_cache = null;

    /**
     * Whether features are disabled due to assignment settings.
     *
     * @var bool
     */
    private $manual_disabled = false;

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
     * Check whether customer insights are disabled.
     *
     * @return bool
     */
    private function is_disabled()
    {
        return $this->manual_disabled;
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
        add_action('wp_ajax_mulopimfwc_analytics_live_data', [$this, 'handle_analytics_live_data']);

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
        if ($this->is_disabled()) {
            return false;
        }

        global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
        return isset($options['enable_customer_location_tracking']) &&
            $options['enable_customer_location_tracking'] === 'on' && mulopimfwc_premium_feature();
    }

    /**
     * Get current location from cookie
     */
    private function get_current_location()
    {
        // First check cookie (may be URL-encoded)
        $location_slug = '';

        $location_slug = mulopimfwc_get_store_location_cookie();
        if (!empty($location_slug)) {
            // Trim whitespace
            $location_slug = trim($location_slug);
            // Decode URL-encoded cookie value (in case it was encoded)
            $decoded = rawurldecode($location_slug);
            // Only use decoded if it's different and not empty
            if ($decoded !== $location_slug && !empty($decoded)) {
                $location_slug = trim($decoded);
            }
        }
        
        // If no cookie, check if there's a default location set
        if (empty($location_slug) || $location_slug === 'all-products') {
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
            $enable_popup = isset($options['enable_popup']) ? $options['enable_popup'] : 'off';
            
            // If popup is disabled, use default location
            if ($enable_popup === 'off') {
                $default_location = mulopimfwc_get_default_location_value($options);
                if (!empty($default_location)) {
                    $location_slug = trim($default_location);
                }
            }
        }
        
        // Still empty or 'all-products', return null
        if (empty($location_slug) || $location_slug === 'all-products') {
            return null;
        }

        // Try multiple methods to find the location term
        $location = null;
        
        // Normalize the slug for comparison
        $location_slug_normalized = strtolower(trim($location_slug));
        
        // Method 1: Try exact slug match (case-sensitive first, as WordPress stores them)
        $location = get_term_by('slug', $location_slug, 'mulopimfwc_store_location');
        
        // Method 2: If not found, try with normalized (lowercase) version
        if (!$location || is_wp_error($location)) {
            $location = get_term_by('slug', $location_slug_normalized, 'mulopimfwc_store_location');
        }
        
        // Method 3: If still not found, try by term ID (in case slug is actually an ID)
        if ((!$location || is_wp_error($location)) && is_numeric($location_slug)) {
            $term = get_term(absint($location_slug), 'mulopimfwc_store_location');
            // Verify it's the correct taxonomy and not an error
            if ($term && !is_wp_error($term) && $term->taxonomy === 'mulopimfwc_store_location') {
                $location = $term;
            }
        }
        
        // Method 4: Get all terms and search manually (most reliable - handles all edge cases)
        if (!$location || is_wp_error($location)) {
            $terms = get_terms([
                'taxonomy' => 'mulopimfwc_store_location',
                'hide_empty' => false,
                'number' => 0, // Get all terms
            ]);
            
            if (!is_wp_error($terms) && !empty($terms)) {
                foreach ($terms as $term) {
                    // Normalize term slug for comparison
                    $term_slug_normalized = strtolower(trim($term->slug));
                    
                    // Exact match (case-insensitive)
                    if ($term_slug_normalized === $location_slug_normalized) {
                        $location = $term;
                        break;
                    }
                    
                    // Try URL-decoded versions
                    $decoded_term_slug = strtolower(trim(rawurldecode($term->slug)));
                    $decoded_location_slug = strtolower(trim(rawurldecode($location_slug)));
                    
                    if ($decoded_term_slug === $decoded_location_slug || 
                        $decoded_term_slug === $location_slug_normalized ||
                        $term_slug_normalized === $decoded_location_slug) {
                        $location = $term;
                        break;
                    }
                    
                    // Match 3: Try matching slug with name (in case cookie has name instead of slug)
                    $term_name_normalized = strtolower(trim($term->name));
                    // Convert name to slug-like format for comparison
                    $term_name_as_slug = sanitize_title($term_name_normalized);
                    if ($term_name_as_slug === $location_slug_normalized || 
                        $term_name_normalized === $location_slug_normalized) {
                        $location = $term;
                        break;
                    }
                }
            }
        }

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
        if ($this->is_disabled()) {
            return;
        }

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
        if ($this->is_disabled()) {
            return;
        }

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
        if ($this->is_disabled()) {
            return;
        }

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
    // FIXED: Changed from private to protected to allow proper access without Reflection
    protected function log_action($action_type, $location, $product_id = null, $order_id = null)
    {
        if ($this->is_disabled()) {
            return;
        }

        global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
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

        $tracked = isset($stats[$location_slug]) ? $stats[$location_slug] : [
            'unique_users' => [],
            'unique_sessions' => [],
            'total_views' => 0,
            'total_purchases' => 0
        ];

        $fallback = $this->get_order_fallback_stats($location_slug);

        $unique_users = max(count($tracked['unique_users']), $fallback['unique_customers']);
        $unique_sessions = max(count($tracked['unique_sessions']), $fallback['unique_sessions']);
        $total_purchases = max($tracked['total_purchases'], $fallback['purchases']);

        // If we never tracked views but we do have purchases, assume at least that many views
        $total_views = (int) $tracked['total_views'];
        if ($total_views < $total_purchases) {
            $total_views = $total_purchases;
        }

        return [
            'unique_users' => $unique_users,
            'unique_sessions' => $unique_sessions,
            'total_views' => $total_views,
            'total_purchases' => $total_purchases
        ];
    }

    /**
     * Get fallback stats from WooCommerce orders to cover data created before tracking was enabled.
     */
    private function get_order_fallback_stats($location_slug)
    {
        $order_stats = $this->get_wc_order_stats_cache();

        return isset($order_stats[$location_slug]) ? $order_stats[$location_slug] : [
            'purchases' => 0,
            'unique_customers' => 0,
            'unique_sessions' => 0
        ];
    }

    /**
     * Build (and cache) order-derived stats keyed by location slug.
     */
    private function get_wc_order_stats_cache()
    {
        if ($this->order_stats_cache !== null) {
            return $this->order_stats_cache;
        }

        $this->order_stats_cache = [];

        if (!function_exists('wc_get_orders')) {
            return $this->order_stats_cache;
        }

        $all_statuses = function_exists('wc_get_order_statuses') ? array_keys(wc_get_order_statuses()) : ['wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending'];

        // Process orders in batches to prevent memory exhaustion
        // Use a reasonable batch size (1000 orders per batch)
        $batch_size = 1000;
        $page = 1;
        $all_order_ids = [];
        
        do {
            $order_ids = wc_get_orders([
                'limit' => $batch_size,
                'offset' => ($page - 1) * $batch_size,
                'status' => $all_statuses,
                'type' => ['shop_order'],
                'return' => 'ids'
            ]);
            
            if (empty($order_ids)) {
                break;
            }
            
            $all_order_ids = array_merge($all_order_ids, $order_ids);
            
            // Safety check: limit total batches
            if ($page > 100) { // Max 100,000 orders
                break;
            }
            
            $page++;
            
        } while (count($order_ids) === $batch_size);

        foreach ($all_order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) {
                continue;
            }

            if (is_callable([$order, 'get_type']) && $order->get_type() === 'shop_order_refund') {
                continue;
            }

            $slug = (string) $order->get_meta('_store_location');
            if ($slug === '') {
                $slug = 'default';
            }

            if (!isset($this->order_stats_cache[$slug])) {
                $this->order_stats_cache[$slug] = [
                    'purchases' => 0,
                    'unique_customers' => [],
                    'unique_sessions' => []
                ];
            }

            $this->order_stats_cache[$slug]['purchases']++;

            $customer_id = null;
            if (is_callable([$order, 'get_customer_id'])) {
                $customer_id = $order->get_customer_id();
            } elseif (is_callable([$order, 'get_user_id'])) {
                $customer_id = $order->get_user_id();
            }
            if ($customer_id) {
                $this->order_stats_cache[$slug]['unique_customers'][$customer_id] = true;
            }

            // Use order key as a pseudo-session identifier to avoid zero counts.
            $order_key = is_callable([$order, 'get_order_key']) ? $order->get_order_key() : '';
            if ($order_key) {
                $this->order_stats_cache[$slug]['unique_sessions'][$order_key] = true;
            }
        }

        foreach ($this->order_stats_cache as $slug => $row) {
            $this->order_stats_cache[$slug] = [
                'purchases' => $row['purchases'],
                'unique_customers' => count($row['unique_customers']),
                'unique_sessions' => count($row['unique_sessions'])
            ];
        }

        return $this->order_stats_cache;
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

        global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
        $recommendations_enabled = !$this->is_disabled() &&
            isset($options['location_based_recommendations']) &&
            $options['location_based_recommendations'] === 'on' && mulopimfwc_premium_feature();

        if ($recommendations_enabled) {
            wp_enqueue_style(
                'mulopimfwc-recommendations',
                MULTI_LOCATION_PLUGIN_URL . 'assets/css/recommendations.css',
                [],
                '1.1.3.87'
            );

            wp_enqueue_script(
                'mulopimfwc-recommendations',
                MULTI_LOCATION_PLUGIN_URL . 'assets/js/recommendations.js',
                ['jquery'],
                '1.1.3.87',
                true
            );

            wp_localize_script('mulopimfwc-recommendations', 'mulopimfwcRecommendations', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mulopimfwc_recommendations'),
                'i18n' => [
                    'loading' => mulopimfwc_get_text_value('text_recommendations_loading'),
                    'noResults' => mulopimfwc_get_text_value('text_recommendations_none'),
                    'error' => mulopimfwc_get_text_value('text_recommendations_error'),
                    'selectLocation' => mulopimfwc_get_text_value('text_recommendations_select_location'),
                    'added' => mulopimfwc_get_text_value('text_recommendations_added'),
                ],
            ]);
        }
    }

    /**
     * Recommendations shortcode
     */
    public function recommendations_shortcode($atts)
    {
        if ($this->is_disabled()) {
            return '';
        }

        global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
        $recommendations_enabled = isset($options['location_based_recommendations']) &&
            $options['location_based_recommendations'] === 'on' && mulopimfwc_premium_feature();

        if (!$recommendations_enabled) {
            return '';
        }

        $location = $this->get_current_location();

        // Debug: Log the location detection (remove in production if needed)
        if (!$location && defined('WP_DEBUG') && WP_DEBUG) {
            $cookie_value = mulopimfwc_get_store_location_cookie();
            if ($cookie_value === '') {
                $cookie_value = 'not set';
            }
            error_log('Mulopimfwc Recommendations: Cookie value = ' . $cookie_value);
            error_log('Mulopimfwc Recommendations: Location not found for slug: ' . $cookie_value);
        }

        if (!$location) {
            return '<div class="mulopimfwc-recommendations-notice">' .
                esc_html(mulopimfwc_get_text_value('text_recommendations_select_location')) .
                '</div>';
        }

        $atts = shortcode_atts([
            'limit' => 8,
            'columns' => 4,
            'title' => sprintf(mulopimfwc_get_text_value('text_recommendations_title'), '{location}'),
            'show_title' => 'yes',
            'orderby' => 'popularity',
            'show_badge' => 'yes'
        ], $atts);

        // Replace {location} placeholder with actual location name
        $title = str_replace('{location}', $location['name'], $atts['title']);

        $popular_products = $this->get_popular_products($location['slug'], intval($atts['limit']));

        if (empty($popular_products)) {
            return '<div class="mulopimfwc-recommendations-notice">' .
                esc_html(mulopimfwc_get_text_value('text_recommendations_none')) .
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
                    <div class="mulopimfwc-recommendation-item" style="transform: translateY(-20px);">
                        <?php if ($atts['show_badge'] === 'yes' && $popularity_data): ?>
                            <div class="mulopimfwc-popularity-badge" title="<?php echo esc_attr(sprintf(mulopimfwc_get_text_value('text_recommendations_badge_title'), $popularity_data['view_count'], $popularity_data['purchase_count'])); ?>">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" fill="currentColor" />
                                </svg>
                                <?php echo esc_html(mulopimfwc_get_text_value('text_recommendations_badge')); ?>
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

        if ($this->is_disabled()) {
            wp_send_json_error(['message' => __('Recommendations are disabled while Manual or Inventory-Based assignment is enabled without optional selection.', 'multi-location-product-and-inventory-management')]);
        }

        global $mulopimfwc_options;
        $options = is_array($mulopimfwc_options ?? null)
            ? $mulopimfwc_options
            : get_option('mulopimfwc_display_options', []);
        $recommendations_enabled = isset($options['location_based_recommendations']) &&
            $options['location_based_recommendations'] === 'on' && mulopimfwc_premium_feature();

        if (!$recommendations_enabled) {
            wp_send_json_error(['message' => __('Location recommendations are disabled.', 'multi-location-product-and-inventory-management')]);
        }

        $location_slug = isset($_POST['location']) ? sanitize_text_field(wp_unslash(rawurldecode($_POST['location']))) : '';
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
        if ($this->is_disabled()) {
            return;
        }

        add_submenu_page(
            'multi-location-product-and-inventory-management',
            __('Location Analytics', 'multi-location-product-and-inventory-management'),
            __('Analytics', 'multi-location-product-and-inventory-management'),
            'manage_woocommerce',
            'mulopimfwc-analytics',
            [$this, 'render_analytics_page']
        );

        add_submenu_page(
            'multi-location-product-and-inventory-management',
            __('Our Plugins', 'multi-location-product-and-inventory-management'),
            __('Our Plugins', 'multi-location-product-and-inventory-management'),
            'install_plugins',
            'plugincy-plugins',
            array($this, 'render_plugincy_plugins_page')
        );

    }

    public function render_plugincy_plugins_page()
    {
        if (!current_user_can('install_plugins')) {
            wp_die(esc_html__('You do not have sufficient permissions to install plugins on this site.', 'multi-location-product-and-inventory-management'));
        }

        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        wp_enqueue_style('plugin-install');
        wp_enqueue_script('plugin-install');
        wp_enqueue_script('updates');
        add_thickbox();

        $api = plugins_api('query_plugins', array(
            'author' => 'plugincy',
            'page' => 1,
            'per_page' => 30,
            'fields' => array(
                'short_description' => true,
                'icons' => true,
                'active_installs' => true,
                'sections' => false,
            ),
        ));

        echo '<div class="wrap plugin-install-php">';
        echo '<h1>' . esc_html__('Plugincy Plugins', 'multi-location-product-and-inventory-management') . '</h1>';

        if (is_wp_error($api)) {
            echo '<div class="notice notice-error"><p>' . esc_html($api->get_error_message()) . '</p></div></div>';
            return;
        }

        $plugins = !empty($api->plugins) ? $api->plugins : array();

        if (empty($plugins)) {
            echo '<p>' . esc_html__('No plugins found for this author.', 'multi-location-product-and-inventory-management') . '</p></div>';
            return;
        }

        echo '<div id="the-list" class="wp-list-table widefat plugin-install-grid">';

        foreach ($plugins as $plugin) {
            $plugin_obj = is_array($plugin) ? (object) $plugin : $plugin;

            $status = install_plugin_install_status($plugin_obj);
            $action_class = 'button';
            $action_url = '';
            $action_text = '';
            $action_disabled = false;

            switch ($status['status']) {
                case 'install':
                    $action_class = 'install-now button button-primary';
                    $action_text = esc_html__('Install Now', 'multi-location-product-and-inventory-management');
                    $action_url = $status['url'];
                    break;
                case 'update_available':
                    $action_class = 'update-now button';
                    $action_text = esc_html__('Update Now', 'multi-location-product-and-inventory-management');
                    $action_url = $status['url'];
                    break;
                default:
                    if (!empty($status['file']) && is_plugin_active($status['file'])) {
                        $action_class = 'button disabled';
                        $action_text = esc_html__('Active', 'multi-location-product-and-inventory-management');
                        $action_disabled = true;
                    } elseif (!empty($status['file']) && current_user_can('activate_plugin', $status['file'])) {
                        $action_class = 'activate-now button button-primary';
                        $action_text = esc_html__('Activate', 'multi-location-product-and-inventory-management');
                        $action_url = wp_nonce_url(self_admin_url('plugins.php?action=activate&plugin=' . $status['file']), 'activate-plugin_' . $status['file']);
                    } else {
                        $action_class = 'button disabled';
                        $action_text = esc_html__('Installed', 'multi-location-product-and-inventory-management');
                        $action_disabled = true;
                    }
            }

            $icon = '';
            $icons = (!empty($plugin_obj->icons) && is_array($plugin_obj->icons)) ? $plugin_obj->icons : array();
            if (!empty($icons)) {
                $preferred = array('svg', '2x', '1x', 'default');
                foreach ($preferred as $size) {
                    if (!empty($icons[$size])) {
                        $icon = esc_url($icons[$size]);
                        break;
                    }
                }
            }

            $name = isset($plugin_obj->name) ? $plugin_obj->name : '';
            $short_description = isset($plugin_obj->short_description) ? $plugin_obj->short_description : '';
            $version = isset($plugin_obj->version) ? $plugin_obj->version : '';
            $active_installs = isset($plugin_obj->active_installs) ? $plugin_obj->active_installs : null;
            $author = isset($plugin_obj->author) ? $plugin_obj->author : '';
            $slug = !empty($plugin_obj->slug) ? sanitize_title($plugin_obj->slug) : sanitize_title($name);
            $details_url = $slug ? self_admin_url('plugin-install.php?tab=plugin-information&plugin=' . $slug . '&TB_iframe=true&width=600&height=550') : '';

            if ($action_url && !$action_disabled) {
                $action_html = '<a class="' . esc_attr($action_class) . '" href="' . esc_url($action_url) . '" data-slug="' . esc_attr($slug) . '" data-name="' . esc_attr($name) . '">' . esc_html($action_text) . '</a>';
            } else {
                $action_html = '<span class="' . esc_attr($action_class) . '" aria-disabled="true">' . esc_html($action_text) . '</span>';
            }

            echo '<div class="plugin-card plugin-card-' . esc_attr($slug) . '">';
            echo '<div class="plugin-card-top">';
            echo '<div class="name column-name">';
            if ($icon) {
                echo '<img class="plugin-icon" src="' . $icon . '" alt="" />';
            }
            if ($details_url) {
                echo '<h3><a class="thickbox open-plugin-details-modal" href="' . esc_url($details_url) . '" aria-label="' . esc_attr(sprintf(__('More details about %s', 'multi-location-product-and-inventory-management'), $name)) . '">' . esc_html($name) . '</a></h3>';
            } else {
                echo '<h3>' . esc_html($name) . '</h3>';
            }
            if (!empty($author)) {
                echo '<p class="author">' . sprintf(esc_html__('By %s', 'multi-location-product-and-inventory-management'), wp_kses_post($author)) . '</p>';
            }
            echo '</div>';
            echo '<div class="action-links"><ul class="plugin-action-buttons"><li>' . $action_html . '</li></ul></div>';
            echo '<div class="desc column-description"><p>' . wp_kses_post($short_description) . '</p></div>';
            echo '</div>';

            echo '<div class="plugin-card-bottom">';
            echo '<div class="vers column-rating">';
            echo '<span>' . sprintf(esc_html__('Version %s', 'multi-location-product-and-inventory-management'), esc_html($version)) . '</span>';
            if ($active_installs !== null) {
                $installs = number_format_i18n((int) $active_installs);
                echo '<span style="margin-left:10px;">' . sprintf(esc_html__('%s+ active installs', 'multi-location-product-and-inventory-management'), esc_html($installs)) . '</span>';
            }
            echo '</div>';
            echo '<div class="column-compatibility"><span class="compatibility-compatible">' . esc_html__('Compatible with your version of WordPress', 'multi-location-product-and-inventory-management') . '</span></div>';
            echo '</div>';
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';
    }

    /**
     * Build a reusable analytics snapshot for rendering & AJAX.
     */
    private function get_analytics_snapshot()
    {
        $locations = get_terms([
            'taxonomy' => 'mulopimfwc_store_location',
            'hide_empty' => false
        ]);
        // Always include a default bucket for orders without a stored location.
        $locations[] = (object) [
            'slug' => 'default',
            'name' => __('Default', 'multi-location-product-and-inventory-management'),
            'term_id' => 0,
        ];

        $visible_location_slugs = null;
        if (is_user_logged_in() && class_exists('MULOPIMFWC_Location_Managers')) {
            $user = wp_get_current_user();
            if (
                in_array('mulopimfwc_location_manager', $user->roles, true) &&
                !MULOPIMFWC_Location_Managers::user_has_capability('all_products')
            ) {
                $assigned = MULOPIMFWC_Location_Managers::get_user_assigned_locations();
                $visible_location_slugs = is_array($assigned) ? $assigned : [];
            }
        }

        $global_stats = [
            'total_views' => 0,
            'total_purchases' => 0,
            'total_users' => 0,
            'total_sessions' => 0
        ];

        $location_scores = [];
        $location_details = [];
        $all_products = [];

        foreach ($locations as $location) {
            $location_slug = rawurldecode($location->slug);
            if (is_array($visible_location_slugs) && !in_array($location_slug, $visible_location_slugs, true)) {
                continue;
            }

            $stats = $this->get_location_stats($location_slug);
            $global_stats['total_views'] += $stats['total_views'];
            $global_stats['total_purchases'] += $stats['total_purchases'];
            $global_stats['total_users'] += $stats['unique_users'];
            $global_stats['total_sessions'] += $stats['unique_sessions'];

            $location_score = ($stats['total_purchases'] * 10) + $stats['total_views'];

            $top_products_raw = $this->get_popular_products($location_slug, 5);
            $top_products = [];
            foreach ($top_products_raw as $product_id => $product_data) {
                $product = wc_get_product($product_id);
                $top_products[] = [
                    'id' => $product_id,
                    'name' => $product ? $product->get_name() : __('Unknown Product', 'multi-location-product-and-inventory-management'),
                    'view_count' => isset($product_data['view_count']) ? (int) $product_data['view_count'] : 0,
                    'purchase_count' => isset($product_data['purchase_count']) ? (int) $product_data['purchase_count'] : 0,
                    'popularity_score' => isset($product_data['popularity_score']) ? (int) $product_data['popularity_score'] : 0
                ];
            }

            $location_conversion = $stats['total_views'] > 0
                ? ($stats['total_purchases'] / $stats['total_views']) * 100
                : 0;

            $location_details[] = [
                'slug' => $location_slug,
                'name' => $location->name,
                'stats' => $stats,
                'conversion' => $location_conversion,
                'score' => $location_score,
                'top_products' => $top_products
            ];

            $location_scores[] = [
                'slug' => $location_slug,
                'name' => $location->name,
                'score' => $location_score,
                'stats' => $stats,
                'conversion' => $location_conversion,
            ];

            // Track products to find global top performer
            $products = $this->get_popular_products($location_slug, 100);
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
        $top_location_data = null;
        if (!empty($location_scores)) {
            usort($location_scores, function ($a, $b) {
                return $b['score'] <=> $a['score'];
            });
            $top_location_data = $location_scores[0];
        }

        // Find top selling product globally
        $top_product_payload = null;
        if (!empty($all_products)) {
            uasort($all_products, function ($a, $b) {
                return $b['score'] <=> $a['score'];
            });
            $top_product_id = key($all_products);
            $top_product = wc_get_product($top_product_id);
            $top_product_payload = [
                'id' => $top_product_id,
                'name' => $top_product ? $top_product->get_name() : __('Unknown Product', 'multi-location-product-and-inventory-management'),
                'views' => $all_products[$top_product_id]['views'],
                'purchases' => $all_products[$top_product_id]['purchases'],
                'locations' => $all_products[$top_product_id]['locations'],
                'locations_count' => count($all_products[$top_product_id]['locations'])
            ];
        }

        $conversion_rate = $global_stats['total_views'] > 0
            ? ($global_stats['total_purchases'] / $global_stats['total_views']) * 100
            : 0;

        return [
            'locations' => $location_details,
            'location_rankings' => $location_scores,
            'global_stats' => array_merge($global_stats, [
                'conversion_rate' => $conversion_rate
            ]),
            'top_location' => $top_location_data,
            'top_product' => $top_product_payload,
            'processing_count' => $this->get_processing_order_count()
        ];
    }

    /**
     * Get count of processing orders for the admin bubble indicator.
     */
    private function get_processing_order_count()
    {
        if (!function_exists('wc_get_orders')) {
            return 0;
        }

        // Use a count query instead of loading all orders into memory
        // This is much more memory-efficient
        $orders = wc_get_orders([
            'limit' => 1, // We only need the count
            'status' => ['processing'],
            'return' => 'ids'
        ]);
        
        // Get total count using a more efficient method
        $count = 0;
        $page = 1;
        $batch_size = 1000;
        
        do {
            $batch = wc_get_orders([
                'limit' => $batch_size,
                'offset' => ($page - 1) * $batch_size,
                'status' => ['processing'],
                'return' => 'ids'
            ]);
            
            if (empty($batch)) {
                break;
            }
            
            $count += count($batch);
            $page++;
            
            // Safety limit
            if ($page > 100) {
                break;
            }
        } while (count($batch) === $batch_size);

        return $count;
    }

    /**
     * AJAX: Live analytics payload for the admin analytics page.
     */
    public function handle_analytics_live_data()
    {
        check_ajax_referer('mulopimfwc_analytics_live', 'nonce');

        if ($this->is_disabled()) {
            wp_send_json_error(['message' => __('Analytics is disabled while Manual or Inventory-Based assignment is enabled without optional selection.', 'multi-location-product-and-inventory-management')]);
        }

        if (!mulopimfwc_user_can_run_reports()) {
            wp_send_json_error(['message' => __('Permission denied', 'multi-location-product-and-inventory-management')]);
        }

        $analytics = $this->get_analytics_snapshot();

        wp_send_json_success($analytics);
    }

    /**
     * Render analytics dashboard page
     */
    /**
     * Render analytics dashboard page
     */
    public function render_analytics_page()
    {
        if ($this->is_disabled()) {
            wp_die(__('Location analytics is disabled while Manual or Inventory-Based assignment is enabled without optional selection.', 'multi-location-product-and-inventory-management'));
        }

        if (!mulopimfwc_user_can_run_reports()) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $analytics = $this->get_analytics_snapshot();
        $global_stats = $analytics['global_stats'];
        $location_scores = $analytics['location_rankings'];
        $locations = $analytics['locations'];
        $location_map = [];
        foreach ($locations as $loc) {
            $location_map[$loc['slug']] = $loc;
        }
        $top_location_data = $analytics['top_location'];
        $top_product_data = $analytics['top_product'];
        $processing_count = $analytics['processing_count'];
        $conversion_rate = isset($global_stats['conversion_rate']) ? $global_stats['conversion_rate'] : 0;

    ?>
        <div class="wrap mulopimfwc-analytics-wrap <?php echo mulopimfwc_premium_feature() ? '' : ' mulopimfwc_pro_only_blur mulopimfwc_pro_only'; ?>">
        <h1 style="display: none !important;"><?php echo esc_html__('Location Analytics Dashboard', 'multi-location-product-and-inventory-management'); ?></h1>
        <h1>
                <span class="dashicons dashicons-chart-area"></span>
                <?php esc_html_e('Location Analytics Dashboard', 'multi-location-product-and-inventory-management'); ?>
                <span id="mulopimfwc-processing-bubble" class="awaiting-mod update-plugins count-<?php echo esc_attr($processing_count); ?>" title="<?php esc_attr_e('Processing orders', 'multi-location-product-and-inventory-management'); ?>">
                    <span class="processing-count"><?php echo esc_html($processing_count); ?></span>
                </span>
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
                            <span class="card-value" data-analytics-metric="total_views"><?php echo esc_html(number_format($global_stats['total_views'])); ?></span>
                        </div>
                    </div>

                    <div class="overview-card success">
                        <div class="card-icon">
                            <span class="dashicons dashicons-cart"></span>
                        </div>
                        <div class="card-content">
                            <span class="card-label"><?php esc_html_e('Total Purchases', 'multi-location-product-and-inventory-management'); ?></span>
                            <span class="card-value" data-analytics-metric="total_purchases"><?php echo esc_html(number_format($global_stats['total_purchases'])); ?></span>
                        </div>
                    </div>

                    <div class="overview-card info">
                        <div class="card-icon">
                            <span class="dashicons dashicons-groups"></span>
                        </div>
                        <div class="card-content">
                            <span class="card-label"><?php esc_html_e('Total Users', 'multi-location-product-and-inventory-management'); ?></span>
                            <span class="card-value" data-analytics-metric="total_users"><?php echo esc_html(number_format($global_stats['total_users'])); ?></span>
                        </div>
                    </div>

                    <div class="overview-card warning">
                        <div class="card-icon">
                            <span class="dashicons dashicons-chart-line"></span>
                        </div>
                        <div class="card-content">
                            <span class="card-label"><?php esc_html_e('Conversion Rate', 'multi-location-product-and-inventory-management'); ?></span>
                            <span class="card-value" data-analytics-metric="conversion_rate"><?php echo esc_html(number_format($conversion_rate, 2)); ?>%</span>
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
                    <?php
                    $top_location_stats = !empty($top_location_data) ? $top_location_data['stats'] : [
                        'total_views' => 0,
                        'total_purchases' => 0,
                        'unique_users' => 0
                    ];
                    $top_location_name = !empty($top_location_data['name'])
                        ? $top_location_data['name']
                        : __('No data available yet', 'multi-location-product-and-inventory-management');
                    ?>
                    <div class="performer-content">
                        <div class="performer-name" id="mulopimfwc-top-location-name"><?php echo esc_html($top_location_name); ?></div>
                        <div class="performer-stats">
                            <div class="performer-stat">
                                <span class="stat-label"><?php esc_html_e('Views', 'multi-location-product-and-inventory-management'); ?></span>
                                <span class="stat-value" data-top-location="views"><?php echo esc_html(number_format($top_location_stats['total_views'])); ?></span>
                            </div>
                            <div class="performer-stat">
                                <span class="stat-label"><?php esc_html_e('Purchases', 'multi-location-product-and-inventory-management'); ?></span>
                                <span class="stat-value" data-top-location="purchases"><?php echo esc_html(number_format($top_location_stats['total_purchases'])); ?></span>
                            </div>
                            <div class="performer-stat">
                                <span class="stat-label"><?php esc_html_e('Users', 'multi-location-product-and-inventory-management'); ?></span>
                                <span class="stat-value" data-top-location="users"><?php echo esc_html(number_format($top_location_stats['unique_users'])); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="top-performer-card">
                    <div class="performer-header">
                        <span class="dashicons dashicons-products"></span>
                        <h3><?php esc_html_e('Top Selling Product', 'multi-location-product-and-inventory-management'); ?></h3>
                    </div>
                    <?php
                    $top_product_id = !empty($top_product_data['id']) ? $top_product_data['id'] : 0;
                    $top_product_name = !empty($top_product_data['name']) ? $top_product_data['name'] : __('No data available yet', 'multi-location-product-and-inventory-management');
                    $top_product_views = !empty($top_product_data['views']) ? $top_product_data['views'] : 0;
                    $top_product_purchases = !empty($top_product_data['purchases']) ? $top_product_data['purchases'] : 0;
                    $top_product_locations = !empty($top_product_data['locations']) ? $top_product_data['locations'] : [];
                    $top_product_locations_count = !empty($top_product_data['locations_count']) ? $top_product_data['locations_count'] : 0;
                    ?>
                    <div class="performer-content">
                        <div class="performer-name">
                            <a id="mulopimfwc-top-product-link" href="<?php echo $top_product_id ? esc_url(get_edit_post_link($top_product_id)) : '#'; ?>">
                                <span id="mulopimfwc-top-product-name"><?php echo esc_html($top_product_name); ?></span>
                            </a>
                        </div>
                        <div class="performer-stats">
                            <div class="performer-stat">
                                <span class="stat-label"><?php esc_html_e('Total Views', 'multi-location-product-and-inventory-management'); ?></span>
                                <span class="stat-value" data-top-product="views"><?php echo esc_html(number_format($top_product_views)); ?></span>
                            </div>
                            <div class="performer-stat">
                                <span class="stat-label"><?php esc_html_e('Total Purchases', 'multi-location-product-and-inventory-management'); ?></span>
                                <span class="stat-value" data-top-product="purchases"><?php echo esc_html(number_format($top_product_purchases)); ?></span>
                            </div>
                            <div class="performer-stat">
                                <span class="stat-label"><?php esc_html_e('Locations', 'multi-location-product-and-inventory-management'); ?></span>
                                <span class="stat-value" data-top-product="locations-count"><?php echo esc_html(number_format($top_product_locations_count)); ?></span>
                            </div>
                        </div>
                        <div class="performer-locations">
                            <small id="mulopimfwc-top-product-locations"><?php echo esc_html(implode(', ', array_slice($top_product_locations, 0, 3))); ?></small>
                        </div>
                    </div>
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
                        <tbody id="mulopimfwc-rankings-body">
                            <?php
                            $rank = 1;
                            foreach ($location_scores as $data):
                                $location_conversion = isset($data['conversion']) ? $data['conversion'] : 0;
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
                        $stats = $location['stats'];
                        $top_products = $location['top_products'];
                        $location_conversion = $location['conversion'];
                        $performance_score = isset($location['score']) ? $location['score'] : (($stats['total_purchases'] * 10) + $stats['total_views']);
                        ?>

                        <div class="mulopimfwc-location-analytics-card" data-location-card="<?php echo esc_attr($location['slug']); ?>">
                            <div class="card-header">
                                <h3><?php echo esc_html($location['name']); ?></h3>
                                <?php if (mulopimfwc_user_can_export_reports()) : ?>
                                    <button type="button" class="button button-secondary export-location-btn" data-location="<?php echo esc_attr($location['slug']); ?>">
                                        <span class="dashicons dashicons-download"></span>
                                        <?php esc_html_e('Export', 'multi-location-product-and-inventory-management'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>

                            <div class="mulopimfwc-analytics-stats">
                                <div class="stat-box">
                                    <div class="stat-icon"><span class="dashicons dashicons-groups"></span></div>
                                    <div class="stat-info">
                                        <span class="stat-label"><?php esc_html_e('Unique Users', 'multi-location-product-and-inventory-management'); ?></span>
                                        <span class="stat-value" data-loc-metric="unique_users"><?php echo esc_html(number_format($stats['unique_users'])); ?></span>
                                    </div>
                                </div>

                                <div class="stat-box">
                                    <div class="stat-icon"><span class="dashicons dashicons-admin-page"></span></div>
                                    <div class="stat-info">
                                        <span class="stat-label"><?php esc_html_e('Sessions', 'multi-location-product-and-inventory-management'); ?></span>
                                        <span class="stat-value" data-loc-metric="unique_sessions"><?php echo esc_html(number_format($stats['unique_sessions'])); ?></span>
                                    </div>
                                </div>

                                <div class="stat-box">
                                    <div class="stat-icon"><span class="dashicons dashicons-visibility"></span></div>
                                    <div class="stat-info">
                                        <span class="stat-label"><?php esc_html_e('Product Views', 'multi-location-product-and-inventory-management'); ?></span>
                                        <span class="stat-value" data-loc-metric="total_views"><?php echo esc_html(number_format($stats['total_views'])); ?></span>
                                    </div>
                                </div>

                                <div class="stat-box">
                                    <div class="stat-icon"><span class="dashicons dashicons-cart"></span></div>
                                    <div class="stat-info">
                                        <span class="stat-label"><?php esc_html_e('Purchases', 'multi-location-product-and-inventory-management'); ?></span>
                                        <span class="stat-value" data-loc-metric="total_purchases"><?php echo esc_html(number_format($stats['total_purchases'])); ?></span>
                                    </div>
                                </div>

                                <div class="stat-box highlight">
                                    <div class="stat-icon"><span class="dashicons dashicons-chart-line"></span></div>
                                    <div class="stat-info">
                                        <span class="stat-label"><?php esc_html_e('Conversion Rate', 'multi-location-product-and-inventory-management'); ?></span>
                                        <span class="stat-value" data-loc-metric="conversion"><?php echo esc_html(number_format($location_conversion, 2)); ?>%</span>
                                    </div>
                                </div>

                                <div class="stat-box highlight">
                                    <div class="stat-icon"><span class="dashicons dashicons-star-filled"></span></div>
                                    <div class="stat-info">
                                        <span class="stat-label"><?php esc_html_e('Performance Score', 'multi-location-product-and-inventory-management'); ?></span>
                                        <span class="stat-value" data-loc-metric="score"><?php echo esc_html(number_format($performance_score)); ?></span>
                                    </div>
                                </div>
                            </div>

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
                                    <tbody data-loc-top-products="<?php echo esc_attr($location['slug']); ?>">
                                        <?php
                                        if (!empty($top_products)) {
                                            $product_rank = 1;
                                            foreach ($top_products as $product_data):
                                                $product_id = $product_data['id'];
                                                $product_name = isset($product_data['name']) && !empty($product_data['name']) ? $product_data['name'] : __('Unknown Product', 'multi-location-product-and-inventory-management');
                                        ?>
                                                <tr>
                                                    <td class="rank-col"><?php echo esc_html($product_rank); ?></td>
                                                    <td>
                                                        <?php $edit_link = get_edit_post_link($product_id); ?>
                                                        <a href="<?php echo $edit_link ? esc_url($edit_link) : '#'; ?>">
                                                            <?php echo esc_html($product_name); ?>
                                                        </a>
                                                    </td>
                                                    <td class="num-col"><?php echo esc_html($product_data['view_count']); ?></td>
                                                    <td class="num-col"><strong><?php echo esc_html($product_data['purchase_count']); ?></strong></td>
                                                    <td class="num-col"><?php echo esc_html(number_format($product_data['popularity_score'])); ?></td>
                                                </tr>
                                            <?php
                                                $product_rank++;
                                            endforeach;
                                        } else {
                                            ?>
                                                <tr>
                                                    <td colspan="5" style="text-align:center;"><?php esc_html_e('No product data available yet for this location.', 'multi-location-product-and-inventory-management'); ?></td>
                                                </tr>
                                            <?php
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
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

                .mulopimfwc-location-details>h2 {
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
            <script type="text/javascript">
                (function ($) {
                    const analyticsConfig = {
                        ajaxurl: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                        nonce: '<?php echo esc_js(wp_create_nonce('mulopimfwc_analytics_live')); ?>',
                        pollInterval: <?php echo absint(apply_filters('mulopimfwc_analytics_poll_interval', 30000)); ?>
                    };

                    function formatNumber(value, decimals = 0) {
                        const num = Number(value || 0);
                        return num.toLocaleString(undefined, { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
                    }

                    function updateProcessingBubble(count) {
                        const bubble = document.getElementById('mulopimfwc-processing-bubble');
                        if (!bubble) return;
                        bubble.classList.forEach(cls => {
                            if (cls.startsWith('count-')) {
                                bubble.classList.remove(cls);
                            }
                        });
                        bubble.classList.add('count-' + count);
                        const span = bubble.querySelector('.processing-count');
                        if (span) {
                            span.textContent = formatNumber(count);
                        }
                    }

                    function updateGlobalMetrics(globalStats) {
                        if (!globalStats) return;
                        const mapping = {
                            total_views: 'total_views',
                            total_purchases: 'total_purchases',
                            total_users: 'total_users',
                            conversion_rate: 'conversion_rate'
                        };
                        Object.keys(mapping).forEach(key => {
                            const el = document.querySelector('[data-analytics-metric="' + mapping[key] + '"]');
                            if (!el) return;
                            if (key === 'conversion_rate') {
                                el.textContent = formatNumber(globalStats[key], 2) + '%';
                            } else {
                                el.textContent = formatNumber(globalStats[key]);
                            }
                        });
                    }

                    function updateTopLocation(data) {
                        const nameEl = document.getElementById('mulopimfwc-top-location-name');
                        if (!data || !data.stats) {
                            if (nameEl) {
                                nameEl.textContent = '<?php echo esc_js(__('No data available yet', 'multi-location-product-and-inventory-management')); ?>';
                            }
                            return;
                        }
                        if (nameEl) nameEl.textContent = data.name || '';
                        const mappings = {
                            views: data.stats.total_views,
                            purchases: data.stats.total_purchases,
                            users: data.stats.unique_users
                        };
                        Object.keys(mappings).forEach(key => {
                            const el = document.querySelector('[data-top-location="' + key + '"]');
                            if (el) {
                                el.textContent = formatNumber(mappings[key]);
                            }
                        });
                    }

                    function updateTopProduct(data) {
                        const nameEl = document.getElementById('mulopimfwc-top-product-name');
                        const linkEl = document.getElementById('mulopimfwc-top-product-link');
                        const locationsEl = document.getElementById('mulopimfwc-top-product-locations');
                        if (!data) {
                            if (nameEl) nameEl.textContent = '<?php echo esc_js(__('No data available yet', 'multi-location-product-and-inventory-management')); ?>';
                            return;
                        }
                        if (nameEl) nameEl.textContent = data.name || '';
                        if (linkEl && data.id) {
                            linkEl.href = '<?php echo esc_url(get_admin_url(null, 'post.php')); ?>?post=' + data.id + '&action=edit';
                        }
                        const mappings = {
                            'views': data.views,
                            'purchases': data.purchases,
                            'locations-count': data.locations_count
                        };
                        Object.keys(mappings).forEach(key => {
                            const el = document.querySelector('[data-top-product="' + key + '"]');
                            if (el) {
                                el.textContent = formatNumber(mappings[key]);
                            }
                        });
                        if (locationsEl) {
                            const locs = Array.isArray(data.locations) ? data.locations.slice(0, 3) : [];
                            locationsEl.textContent = locs.join(', ');
                        }
                    }

                    function renderRankings(rankings) {
                        const tbody = document.getElementById('mulopimfwc-rankings-body');
                        if (!tbody || !Array.isArray(rankings)) return;
                        let html = '';
                        rankings.forEach((row, index) => {
                            const rank = index + 1;
                            let badgeClass = '';
                            if (rank === 1) badgeClass = 'gold';
                            else if (rank === 2) badgeClass = 'silver';
                            else if (rank === 3) badgeClass = 'bronze';
                            html += '<tr>' +
                                '<td class="rank-column"><span class="rank-badge' + (badgeClass ? ' ' + badgeClass : '') + '">' + formatNumber(rank) + '</span></td>' +
                                '<td><strong>' + (row.name || '') + '</strong></td>' +
                                '<td class="num-column">' + formatNumber(row.stats ? row.stats.total_views : 0) + '</td>' +
                                '<td class="num-column">' + formatNumber(row.stats ? row.stats.total_purchases : 0) + '</td>' +
                                '<td class="num-column">' + formatNumber(row.stats ? row.stats.unique_users : 0) + '</td>' +
                                '<td class="num-column">' + formatNumber(row.conversion || 0, 2) + '%</td>' +
                                '<td class="num-column"><strong>' + formatNumber(row.score || 0) + '</strong></td>' +
                                '</tr>';
                        });
                        tbody.innerHTML = html;
                    }

                    function buildTopProductsRows(products) {
                        if (!Array.isArray(products) || products.length === 0) {
                            return '<tr><td colspan="5"><?php echo esc_js(__('No product data available yet for this location.', 'multi-location-product-and-inventory-management')); ?></td></tr>';
                        }
                        let html = '';
                        products.forEach((product, idx) => {
                            const rank = idx + 1;
                            const productId = product.id ? parseInt(product.id, 10) : 0;
                            const editLink = productId ? ('<?php echo esc_url(get_admin_url(null, 'post.php')); ?>?post=' + productId + '&action=edit') : '#';
                            html += '<tr>' +
                                '<td class="rank-col">' + formatNumber(rank) + '</td>' +
                                '<td><a href="' + editLink + '">' + (product.name || '') + '</a></td>' +
                                '<td class="num-col">' + formatNumber(product.view_count || 0) + '</td>' +
                                '<td class="num-col"><strong>' + formatNumber(product.purchase_count || 0) + '</strong></td>' +
                                '<td class="num-col">' + formatNumber(product.popularity_score || 0) + '</td>' +
                                '</tr>';
                        });
                        return html;
                    }

                    function updateLocationCards(locations) {
                        if (!Array.isArray(locations)) return;
                        const container = document.querySelector('.mulopimfwc-analytics-dashboard');
                        if (!container) return;

                        locations.forEach(loc => {
                            const card = container.querySelector('.mulopimfwc-location-analytics-card[data-location-card="' + loc.slug + '"]');
                            if (!card) {
                                return;
                            }
                            const stats = loc.stats || {};
                            const metrics = {
                                unique_users: stats.unique_users,
                                unique_sessions: stats.unique_sessions,
                                total_views: stats.total_views,
                                total_purchases: stats.total_purchases,
                                conversion: loc.conversion,
                                score: loc.score
                            };
                            Object.keys(metrics).forEach(key => {
                                const el = card.querySelector('[data-loc-metric="' + key + '"]');
                                if (!el) return;
                                if (key === 'conversion') {
                                    el.textContent = formatNumber(metrics[key] || 0, 2) + '%';
                                } else {
                                    el.textContent = formatNumber(metrics[key] || 0);
                                }
                            });

                            const tbody = card.querySelector('tbody[data-loc-top-products="' + loc.slug + '"]');
                            if (tbody) {
                                tbody.innerHTML = buildTopProductsRows(loc.top_products || []);
                            }
                        });
                    }

                    function applyAnalyticsPayload(payload) {
                        if (!payload || typeof payload !== 'object') return;
                        updateGlobalMetrics(payload.global_stats || payload.globalStats);
                        updateTopLocation(payload.top_location || payload.topLocation);
                        updateTopProduct(payload.top_product || payload.topProduct);
                        renderRankings(payload.location_rankings || payload.locationRankings || []);
                        updateLocationCards(payload.locations || []);
                        if (payload.processing_count !== undefined) {
                            updateProcessingBubble(payload.processing_count);
                        }
                    }

                    function fetchLiveAnalytics() {
                        $.post(analyticsConfig.ajaxurl, {
                            action: 'mulopimfwc_analytics_live_data',
                            nonce: analyticsConfig.nonce
                        }).done(function (response) {
                            if (response && response.success) {
                                applyAnalyticsPayload(response.data);
                            }
                        });
                    }

                    $(document).ready(function () {
                        fetchLiveAnalytics();
                        setInterval(fetchLiveAnalytics, analyticsConfig.pollInterval);
                    });
                })(jQuery);
            </script>
        </div>
    <?php
    }

    /**
     * Export analytics data
     */
    public function export_analytics_data($location_slug = null)
    {
        if ($this->is_disabled()) {
            return;
        }

        if (!mulopimfwc_user_can_export_reports()) {
            return;
        }

        $popularity_data = get_option(self::POPULARITY_OPTION, []);
        if (!is_array($popularity_data)) {
            $popularity_data = [];
        }

        $visible_location_slugs = null;
        if (is_user_logged_in() && class_exists('MULOPIMFWC_Location_Managers')) {
            $user = wp_get_current_user();
            if (
                in_array('mulopimfwc_location_manager', $user->roles, true) &&
                !MULOPIMFWC_Location_Managers::user_has_capability('all_products')
            ) {
                $assigned = MULOPIMFWC_Location_Managers::get_user_assigned_locations();
                $visible_location_slugs = is_array($assigned) ? array_values(array_filter($assigned)) : [];
            }
        }

        if (is_array($visible_location_slugs)) {
            $popularity_data = array_intersect_key($popularity_data, array_flip($visible_location_slugs));
        }

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

    if (function_exists('mulopimfwc_is_manual_assignment_strict_mode') && mulopimfwc_is_manual_assignment_strict_mode()) {
        wp_send_json_error(['message' => __('Location tracking is disabled while Manual or Inventory-Based assignment is enabled without optional selection.', 'multi-location-product-and-inventory-management')]);
        return;
    }

    // FIXED: Add rate limiting to prevent DoS attacks
    $rate_limit_key = 'mulopimfwc_track_rate_' . (is_user_logged_in() ? get_current_user_id() : $_SERVER['REMOTE_ADDR']);
    $rate_limit_count = get_transient($rate_limit_key);
    $rate_limit_max = apply_filters('mulopimfwc_track_rate_limit', 60); // 60 requests per minute
    $rate_limit_window = 60; // 1 minute
    
    if ($rate_limit_count !== false && $rate_limit_count >= $rate_limit_max) {
        wp_send_json_error(['message' => __('Rate limit exceeded. Please try again later.', 'multi-location-product-and-inventory-management')]);
        return;
    }
    
    // Increment rate limit counter
    if ($rate_limit_count === false) {
        set_transient($rate_limit_key, 1, $rate_limit_window);
    } else {
        set_transient($rate_limit_key, $rate_limit_count + 1, $rate_limit_window);
    }

    $location_slug = isset($_POST['location_slug']) ? sanitize_text_field(wp_unslash($_POST['location_slug'])) : '';
    $location_name = isset($_POST['location_name']) ? sanitize_text_field(wp_unslash($_POST['location_name'])) : '';

    if (empty($location_slug) || empty($location_name)) {
        wp_send_json_error(['message' => __('Invalid location data', 'multi-location-product-and-inventory-management')]);
        return;
    }

    // FIXED: Validate location exists before tracking
    $location_term = get_term_by('slug', $location_slug, 'mulopimfwc_store_location');
    if (!$location_term || is_wp_error($location_term)) {
        wp_send_json_error(['message' => __('Invalid location. Location does not exist.', 'multi-location-product-and-inventory-management')]);
        return;
    }
    
    // Use validated location name
    $location_name = $location_term->name;
    
    $instance = Mulopimfwc_Customer_Location_Insights::get_instance();

    // FIXED: No longer need Reflection - method is now protected
    $instance->log_action('selection', [
        'slug' => $location_slug,
        'name' => $location_name
    ]);

    wp_send_json_success(['message' => __('Location tracked', 'multi-location-product-and-inventory-management')]);
}

function mulopimfwc_user_can_run_reports()
{
    if (class_exists('MULOPIMFWC_Location_Managers')) {
        return MULOPIMFWC_Location_Managers::user_has_capability('run_reports');
    }

    return current_user_can('manage_woocommerce');
}

function mulopimfwc_user_can_export_reports()
{
    if (!mulopimfwc_user_can_run_reports()) {
        return false;
    }

    if (class_exists('MULOPIMFWC_Location_Managers')) {
        return MULOPIMFWC_Location_Managers::user_has_capability('export_report');
    }

    return current_user_can('manage_woocommerce');
}

// AJAX handler for exporting analytics
add_action('wp_ajax_mulopimfwc_export_analytics', 'mulopimfwc_ajax_export_analytics');

function mulopimfwc_ajax_export_analytics()
{
    check_ajax_referer('mulopimfwc_analytics_export', 'nonce');

    if (function_exists('mulopimfwc_is_manual_assignment_strict_mode') && mulopimfwc_is_manual_assignment_strict_mode()) {
        wp_die(__('Analytics export is disabled while Manual or Inventory-Based assignment is enabled without optional selection.', 'multi-location-product-and-inventory-management'));
    }

    if (!mulopimfwc_user_can_export_reports()) {
        wp_die(__('You do not have permission to export analytics.'));
    }

    $location_slug = isset($_POST['location']) ? sanitize_text_field(wp_unslash(rawurldecode($_POST['location']))) : null;

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

// Add export button to analytics page (cover parent, submenu, and fallback footer)
add_action('admin_footer', 'mulopimfwc_add_export_button_script');

function mulopimfwc_add_export_button_script()
{
    if (function_exists('mulopimfwc_is_manual_assignment_strict_mode') && mulopimfwc_is_manual_assignment_strict_mode()) {
        return;
    }

    $screen = get_current_screen();
    if ($screen && $screen->id === 'location-manage_page_mulopimfwc-analytics' && mulopimfwc_user_can_export_reports()) {
    ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                const exportNonce = '<?php echo esc_js(wp_create_nonce('mulopimfwc_analytics_export')); ?>';

                function submitExport(locationSlug) {
                    const form = $('<form>', { method: 'POST', action: ajaxurl, style: 'display:none;' });
                    form.append($('<input>', { type: 'hidden', name: 'action', value: 'mulopimfwc_export_analytics' }));
                    form.append($('<input>', { type: 'hidden', name: 'nonce', value: exportNonce }));
                    if (locationSlug) {
                        form.append($('<input>', { type: 'hidden', name: 'location', value: locationSlug }));
                    }
                    $('body').append(form);
                    form.trigger('submit');
                    setTimeout(function () { form.remove(); }, 1000);
                }

                // Wire per-location export buttons
                $(document).on('click', '.export-location-btn', function (e) {
                    e.preventDefault();
                    const slug = $(this).data('location');
                    submitExport(slug);
                });

                // Add global export button if not present
                if ($('.mulopimfwc-export-all').length === 0) {
                    const $btn = $('<button type="button" class="button button-primary mulopimfwc-export-all" style="margin-left:10px;">Export All Data</button>');
                    $('.wrap h1').append($btn);
                }

                $(document).on('click', '.mulopimfwc-export-all', function (e) {
                    e.preventDefault();
                    submitExport('');
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
    <div class="mulopimfwc-data-info" style="margin-top: 20px; padding: 15px; background: #f0f8ff; border-left: 4px solid var(--lwp-primary, #667eea); border-radius: 4px;">
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

    if (function_exists('mulopimfwc_is_manual_assignment_strict_mode') && mulopimfwc_is_manual_assignment_strict_mode()) {
        wp_send_json_error(['message' => __('Analytics data cannot be cleared while Manual or Inventory-Based assignment is enabled without optional selection.', 'multi-location-product-and-inventory-management')]);
    }

    if (!mulopimfwc_user_can_run_reports()) {
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
