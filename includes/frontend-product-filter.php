<?php

/**
 * Filter products based on location
 *
 * @package Location_Wise_Products
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Location_Wise_Products_Filter
 */
class Location_Wise_Products_Filter
{
    /**
     * Cache group name
     */
    const CACHE_GROUP = 'mulopimfwc_product_filter';

    /**
     * Cache expiration time in seconds (1 hour)
     */
    const CACHE_EXPIRATION = 3600;

    /**
     * Constructor
     */
    public function __construct()
    {
        // AJAX handlers
        add_action('wp_ajax_mulopimfwc_filter_products', [$this, 'ajax_filter_products']);
        add_action('wp_ajax_nopriv_mulopimfwc_filter_products', [$this, 'ajax_filter_products']);

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);

        // Display filter UI on shop/archive pages (only if enabled)
        add_action('woocommerce_before_shop_loop', [$this, 'render_filter_ui'], 10);
        
        // Register shortcode for manual placement
        add_shortcode('mulopimfwc_product_filter', [$this, 'shortcode_filter_ui']);

        // Clear cache when products or locations are updated
        add_action('save_post_product', [$this, 'clear_cache_on_product_update'], 10, 2);
        add_action('edited_term', [$this, 'clear_cache_on_term_update'], 10, 3);
        add_action('created_term', [$this, 'clear_cache_on_term_update'], 10, 3);
        add_action('delete_term', [$this, 'clear_cache_on_term_delete'], 10, 4);
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts()
    {
        // Load on shop/archive pages or if shortcode is being used
        $load_scripts = is_shop() || is_product_category() || is_product_tag() || is_product_taxonomy();
        
        // Check if shortcode is present in current post/page
        if (!$load_scripts) {
            global $post;
            if ($post && (has_shortcode($post->post_content, 'mulopimfwc_product_filter') || 
                         (function_exists('has_block') && has_block('shortcode', $post->post_content)))) {
                $load_scripts = true;
            }
        }

        if (!$load_scripts) {
            return;
        }

        wp_enqueue_script(
            'mulopimfwc-frontend-filter',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/frontend-filter.js',
            ['jquery'],
            mulopimfwc_VERSION,
            true
        );

        wp_enqueue_style(
            'mulopimfwc-frontend-filter',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/frontend-filter.css',
            [],
            mulopimfwc_VERSION
        );

        // Localize script
        wp_localize_script('mulopimfwc-frontend-filter', 'mulopimfwcFilter', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mulopimfwc_filter_products'),
            'i18n' => [
                'loading' => __('Loading products...', 'multi-location-product-and-inventory-management'),
                'noResults' => __('No products found.', 'multi-location-product-and-inventory-management'),
                'error' => __('An error occurred. Please try again.', 'multi-location-product-and-inventory-management'),
                'allLocations' => __('All Locations', 'multi-location-product-and-inventory-management'),
                'inStock' => __('In Stock', 'multi-location-product-and-inventory-management'),
                'outOfStock' => __('Out of Stock', 'multi-location-product-and-inventory-management'),
                'filterProducts' => __('Filter Products', 'multi-location-product-and-inventory-management'),
                'clearFilters' => __('Clear Filters', 'multi-location-product-and-inventory-management'),
            ],
        ]);
    }

    /**
     * Render filter UI
     */
    public function render_filter_ui()
    {
        // Check if automatic display is enabled
        $options = get_option('mulopimfwc_display_options', []);
        // Get setting value, default to 'on' for backward compatibility if not set
        $enable_filter = isset($options['enable_product_filter']) ? $options['enable_product_filter'] : 'off';
        
        // If explicitly set to 'off', don't render
        if ($enable_filter === 'off' || $enable_filter === false || $enable_filter === '0' || $enable_filter === '') {
            return; // Filter is disabled
        }

        // Only show on shop/archive pages
        if (!is_shop() && !is_product_category() && !is_product_tag() && !is_product_taxonomy()) {
            return;
        }

        $this->output_filter_ui();
    }

    /**
     * Shortcode handler for filter UI
     */
    public function shortcode_filter_ui($atts)
    {
        $atts = shortcode_atts([
            'location' => 'yes',
            'stock' => 'yes',
        ], $atts, 'mulopimfwc_product_filter');

        // Ensure scripts and styles are enqueued for shortcode
        $this->enqueue_scripts();

        ob_start();
        $this->output_filter_ui(true, $atts);
        return ob_get_clean();
    }

    /**
     * Output filter UI HTML
     */
    private function output_filter_ui($is_shortcode = false, $atts = [])
    {
        // Get available locations
        $locations = $this->get_available_locations();
        $show_location = !isset($atts['location']) || $atts['location'] !== 'no';
        $show_stock = !isset($atts['stock']) || $atts['stock'] !== 'no';
        
        if (empty($locations) && (!$this->should_show_stock_filter() || !$show_stock)) {
            if (!$is_shortcode) {
                return; // No filters to show (only return early if not shortcode)
            }
            // For shortcode, show empty div so scripts still work
            echo '<div class="mulopimfwc-product-filter" id="mulopimfwc-product-filter" data-shortcode="true" style="display:none;"></div>';
            return;
        }

        $current_location = ''; //isset($_GET['location']) ? sanitize_text_field($_GET['location']) : '';
        $current_stock = ''; //isset($_GET['stock']) ? sanitize_text_field($_GET['stock']) : '';

        ?>
        <div class="mulopimfwc-product-filter" id="mulopimfwc-product-filter" <?php echo $is_shortcode ? 'data-shortcode="true"' : ''; ?>>
            <div class="mulopimfwc-filter-container">
                <?php if (!empty($locations) && $show_location): ?>
                <div class="mulopimfwc-filter-field mulopimfwc-filter-location">
                    <label for="mulopimfwc-filter-location-select">
                        <?php esc_html_e('Location', 'multi-location-product-and-inventory-management'); ?>
                    </label>
                    <select id="mulopimfwc-filter-location-select" name="location" class="mulopimfwc-filter-select">
                        <option value=""><?php esc_html_e('All Locations', 'multi-location-product-and-inventory-management'); ?></option>
                        <?php foreach ($locations as $location): ?>
                            <option value="<?php echo esc_attr(rawurldecode($location->slug)); ?>" <?php selected($current_location, rawurldecode($location->slug)); ?>>
                                <?php echo esc_html($location->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <?php if ($this->should_show_stock_filter() && $show_stock): ?>
                <div class="mulopimfwc-filter-field mulopimfwc-filter-stock">
                    <label for="mulopimfwc-filter-stock-select">
                        <?php esc_html_e('Stock Status', 'multi-location-product-and-inventory-management'); ?>
                    </label>
                    <select id="mulopimfwc-filter-stock-select" name="stock" class="mulopimfwc-filter-select">
                        <option value=""><?php esc_html_e('All Products', 'multi-location-product-and-inventory-management'); ?></option>
                        <option value="instock" <?php selected($current_stock, 'instock'); ?>>
                            <?php esc_html_e('In Stock', 'multi-location-product-and-inventory-management'); ?>
                        </option>
                        <option value="outofstock" <?php selected($current_stock, 'outofstock'); ?>>
                            <?php esc_html_e('Out of Stock', 'multi-location-product-and-inventory-management'); ?>
                        </option>
                    </select>
                </div>
                <?php endif; ?>

                <div class="mulopimfwc-filter-actions">
                    <button type="button" class="button mulopimfwc-filter-button" id="mulopimfwc-filter-button">
                        <?php esc_html_e('Filter', 'multi-location-product-and-inventory-management'); ?>
                    </button>
                    <?php if ($current_location || $current_stock): ?>
                        <button type="button" class="button mulopimfwc-clear-button" id="mulopimfwc-clear-button">
                            <?php esc_html_e('Clear', 'multi-location-product-and-inventory-management'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Get available locations
     */
    private function get_available_locations()
    {
        if (!taxonomy_exists('mulopimfwc_store_location')) {
            return [];
        }

        // Check for cached locations
        $cache_key = 'available_locations';
        $locations = wp_cache_get($cache_key, self::CACHE_GROUP);

        if (false === $locations) {
            $locations = get_terms([
                'taxonomy' => 'mulopimfwc_store_location',
                'hide_empty' => true,
                'orderby' => 'name',
                'order' => 'ASC',
            ]);

            if (is_wp_error($locations)) {
                $locations = [];
            }

            // Cache for 1 hour
            wp_cache_set($cache_key, $locations, self::CACHE_GROUP, self::CACHE_EXPIRATION);
        }

        return $locations;
    }

    /**
     * Check if stock filter should be shown
     */
    private function should_show_stock_filter()
    {
        $options = get_option('mulopimfwc_display_options', []);
        return isset($options['enable_location_stock']) && $options['enable_location_stock'] === 'on';
    }

    /**
     * AJAX handler for filtering products
     */
    public function ajax_filter_products()
    {
        check_ajax_referer('mulopimfwc_filter_products', 'nonce');

        $location_slug = isset($_POST['location']) ? sanitize_text_field(rawurldecode($_POST['location'])) : '';
        $stock_status = isset($_POST['stock']) ? sanitize_text_field($_POST['stock']) : '';
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
        $tag = isset($_POST['tag']) ? sanitize_text_field($_POST['tag']) : '';
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $manager_locations = null;
        $manager_id = 0;
        if (is_user_logged_in() && class_exists('MULOPIMFWC_Location_Managers')) {
            $user = wp_get_current_user();
            if (
                in_array('mulopimfwc_location_manager', $user->roles, true) &&
                MULOPIMFWC_Location_Managers::user_has_capability('location_specific_products_frontend')
            ) {
                $manager_locations = get_user_meta($user->ID, 'mulopimfwc_assigned_locations', true);
                if (!is_array($manager_locations)) {
                    $manager_locations = [];
                }
                $manager_id = $user->ID;
            }
        }

        // Build cache key
        $cache_key = $this->build_cache_key([
            'location' => $location_slug,
            'stock' => $stock_status,
            'page' => $page,
            'category' => $category,
            'tag' => $tag,
            'search' => $search,
            'manager' => $manager_id,
            'posts_per_page' => wc_get_default_products_per_row() * wc_get_default_product_rows_per_page(),
        ]);

        // Try to get cached result
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
        if (false !== $cached) {
            wp_send_json_success($cached);
            return;
        }

        // Build query args
        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => wc_get_default_products_per_row() * wc_get_default_product_rows_per_page(),
            'paged' => $page,
            'orderby' => isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'menu_order',
            'order' => isset($_POST['order']) ? sanitize_text_field($_POST['order']) : 'ASC',
        ];

        // Add taxonomy query for location
        // When filtering by location, show ONLY products from that specific location
        // Do NOT show products from other locations or products without location assignment
        if (is_array($manager_locations)) {
            if (empty($manager_locations)) {
                $args['post__in'] = [0];
            } elseif (!empty($location_slug)) {
                if (!in_array($location_slug, $manager_locations, true)) {
                    $args['post__in'] = [0];
                }
            } else {
                $args['tax_query'][] = [
                    'taxonomy' => 'mulopimfwc_store_location',
                    'field' => 'slug',
                    'terms' => $manager_locations,
                    'operator' => 'IN',
                ];
            }
        }

        if (!empty($location_slug) && empty($args['post__in'])) {
            $args['tax_query'][] = [
                'taxonomy' => 'mulopimfwc_store_location',
                'field' => 'slug',
                'terms' => $location_slug,
                'operator' => 'IN',
            ];
        }

        // Add category
        if (!empty($category)) {
            if (!isset($args['tax_query'])) {
                $args['tax_query'] = [];
            }
            $args['tax_query'][] = [
                'taxonomy' => 'product_cat',
                'field' => 'slug',
                'terms' => $category,
            ];
        }

        // Add tag
        if (!empty($tag)) {
            if (!isset($args['tax_query'])) {
                $args['tax_query'] = [];
            }
            $args['tax_query'][] = [
                'taxonomy' => 'product_tag',
                'field' => 'slug',
                'terms' => $tag,
            ];
        }

        // Add search
        if (!empty($search)) {
            $args['s'] = $search;
        }

        // Execute query
        $query = new WP_Query($args);

        // Filter by stock status if needed
        // Note: For better performance with stock filtering, we need to query matching products in batches
        // then filter by stock, then paginate. This is necessary because stock is stored in postmeta.
        if (!empty($stock_status)) {
            // Process products in batches to prevent memory exhaustion
            // Use a reasonable batch size (500 products per batch)
            $batch_size = 500;
            $batch_page = 1;
            $filtered_posts = [];
            $location_term = !empty($location_slug) ? get_term_by('slug', $location_slug, 'mulopimfwc_store_location') : null;
            
            // Increase memory limit for stock filtering
            if (function_exists('ini_set')) {
                @ini_set('memory_limit', '256M');
            }
            
            do {
                $all_args = $args;
                $all_args['posts_per_page'] = $batch_size;
                $all_args['paged'] = $batch_page;
                $all_query = new WP_Query($all_args);
                
                if (!$all_query->have_posts()) {
                    break;
                }

                while ($all_query->have_posts()) {
                    $all_query->the_post();
                    $product_id = get_the_ID();
                    $matches_stock = false;

                    if ($stock_status === 'instock') {
                        $matches_stock = !$this->is_product_out_of_stock_for_location($product_id, $location_term);
                    } elseif ($stock_status === 'outofstock') {
                        $matches_stock = $this->is_product_out_of_stock_for_location($product_id, $location_term);
                    }

                    if ($matches_stock) {
                        $filtered_posts[] = $product_id;
                    }
                }

                wp_reset_postdata();
                
                // Safety check: limit total batches to prevent infinite loops
                // Max 10,000 products (500 * 20 batches)
                if ($batch_page >= 20) {
                    break;
                }
                
                $batch_page++;
                
            } while ($all_query->found_posts >= $batch_size);

            // Re-query with filtered IDs and proper pagination
            if (!empty($filtered_posts)) {
                $posts_per_page = $args['posts_per_page'];
                $offset = ($page - 1) * $posts_per_page;
                $paginated_posts = array_slice($filtered_posts, $offset, $posts_per_page);
                
                if (!empty($paginated_posts)) {
                    // Update args for paginated query
                    $args['post__in'] = $paginated_posts;
                    $args['orderby'] = isset($_POST['orderby']) && $_POST['orderby'] !== 'menu_order' 
                        ? sanitize_text_field($_POST['orderby']) 
                        : 'post__in';
                    $args['paged'] = 1; // Already paginated via array_slice
                    
                    $query = new WP_Query($args);
                    // Update found_posts to reflect filtered total
                    $query->found_posts = count($filtered_posts);
                    $query->max_num_pages = ceil(count($filtered_posts) / $posts_per_page);
                } else {
                    // No products on this page
                    $query->posts = [];
                    $query->post_count = 0;
                    $query->found_posts = count($filtered_posts);
                    $query->max_num_pages = ceil(count($filtered_posts) / $posts_per_page);
                }
            } else {
                // No products match stock filter
                $query->posts = [];
                $query->post_count = 0;
                $query->found_posts = 0;
                $query->max_num_pages = 0;
            }
        }

        // Get products HTML - preserve exact WooCommerce structure
        // We need to capture the loop content, not the wrapper
        ob_start();
        if ($query->have_posts()) {
            // Start product loop (this creates the wrapper)
            woocommerce_product_loop_start();

            while ($query->have_posts()) {
                $query->the_post();
                // Use the same template part that WooCommerce uses
                wc_get_template_part('content', 'product');
            }

            // End product loop (closes wrapper)
            woocommerce_product_loop_end();
        } else {
            wc_get_template('loop/no-products-found.php');
        }
        wp_reset_postdata();

        $products_html = ob_get_clean();
        
        // Extract just the wrapper and content to preserve structure
        // The output should already have the correct wrapper (ul.products or similar)

        // Get result count HTML
        ob_start();
        $posts_per_page = wc_get_default_products_per_row() * wc_get_default_product_rows_per_page();
        $total = $query->found_posts;
        $first = ($page - 1) * $posts_per_page + 1;
        $last = min($page * $posts_per_page, $total);
        
        if ($total <= $posts_per_page) {
            $result_count_text = sprintf(
                _n('Showing the single result', 'Showing all %d results', $total, 'woocommerce'),
                $total
            );
        } else {
            $result_count_text = sprintf(
                _nx('Showing the single result', 'Showing %1$d&ndash;%2$d of %3$d results', $total, '%1$d = first, %2$d = last, %3$d = total', 'woocommerce'),
                $first,
                $last,
                $total
            );
        }
        
        echo '<p class="woocommerce-result-count">';
        echo esc_html($result_count_text);
        echo '</p>';
        $result_count_html = ob_get_clean();

        // Get pagination HTML
        ob_start();
        if ($query->max_num_pages > 1) {
            $pagination_args = [
                'base' => esc_url_raw(add_query_arg('paged', '%#%', remove_query_arg('add-to-cart'))),
                'format' => '',
                'current' => $page,
                'total' => $query->max_num_pages,
                'prev_text' => '&larr;',
                'next_text' => '&rarr;',
                'type' => 'list',
                'end_size' => 3,
                'mid_size' => 3,
            ];

            echo '<nav class="woocommerce-pagination">';
            echo paginate_links($pagination_args);
            echo '</nav>';
        }
        $pagination_html = ob_get_clean();

        // Build response
        $response = [
            'products' => $products_html,
            'result_count' => $result_count_html,
            'pagination' => $pagination_html,
            'found_posts' => $query->found_posts,
            'max_pages' => $query->max_num_pages,
            'current_page' => $page,
        ];

        // Cache the response
        wp_cache_set($cache_key, $response, self::CACHE_GROUP, self::CACHE_EXPIRATION);

        wp_send_json_success($response);
    }

    /**
     * Build cache key from parameters
     */
    private function build_cache_key($params)
    {
        // Get cache version for invalidation
        $cache_version = get_transient('mulopimfwc_filter_cache_version');
        if (false === $cache_version) {
            $cache_version = 1;
            set_transient('mulopimfwc_filter_cache_version', $cache_version, self::CACHE_EXPIRATION * 24);
        }
        
        // Sort params for consistent cache keys
        ksort($params);
        $params['cache_version'] = $cache_version;
        return 'filter_' . md5(serialize($params));
    }

    /**
     * Clear cache on product update
     */
    public function clear_cache_on_product_update($post_id, $post)
    {
        if ($post->post_type !== 'product' || $post->post_status !== 'publish') {
            return;
        }

        // Clear all filter cache by deleting cache keys
        // Since we can't flush groups directly, we'll use a version-based approach
        $cache_version = get_transient('mulopimfwc_filter_cache_version');
        if (false === $cache_version) {
            $cache_version = 1;
        } else {
            $cache_version = intval($cache_version) + 1;
        }
        set_transient('mulopimfwc_filter_cache_version', $cache_version, self::CACHE_EXPIRATION * 24);
        
        // Also clear available locations cache
        wp_cache_delete('available_locations', self::CACHE_GROUP);
    }

    /**
     * Clear cache on term update
     */
    public function clear_cache_on_term_update($term_id, $tt_id, $taxonomy)
    {
        if ($taxonomy === 'mulopimfwc_store_location') {
            // Clear available locations cache
            wp_cache_delete('available_locations', self::CACHE_GROUP);
            
            // Invalidate filter cache version
            $cache_version = get_transient('mulopimfwc_filter_cache_version');
            if (false === $cache_version) {
                $cache_version = 1;
            } else {
                $cache_version = intval($cache_version) + 1;
            }
            set_transient('mulopimfwc_filter_cache_version', $cache_version, self::CACHE_EXPIRATION * 24);
        }
    }

    /**
     * Clear cache on term delete
     */
    public function clear_cache_on_term_delete($term_id, $tt_id, $taxonomy, $deleted_term)
    {
        if ($taxonomy === 'mulopimfwc_store_location') {
            // Clear available locations cache
            wp_cache_delete('available_locations', self::CACHE_GROUP);
            
            // Invalidate filter cache version
            $cache_version = get_transient('mulopimfwc_filter_cache_version');
            if (false === $cache_version) {
                $cache_version = 1;
            } else {
                $cache_version = intval($cache_version) + 1;
            }
            set_transient('mulopimfwc_filter_cache_version', $cache_version, self::CACHE_EXPIRATION * 24);
        }
    }

    /**
     * Check if product is out of stock for location
     */
    private function is_product_out_of_stock_for_location($product_id, $location_term = null)
    {
        // If no location, check global stock
        if (!$location_term) {
            $product = wc_get_product($product_id);
            return $product ? !$product->is_in_stock() : false;
        }

        // Get location-specific stock
        global $mulopimfwc_options;
        $enable_all_locations = isset($mulopimfwc_options['enable_all_locations']) ? $mulopimfwc_options['enable_all_locations'] : 'off';
        $terms = array_map('rawurldecode',wp_get_object_terms($product_id, 'mulopimfwc_store_location', ['fields' => 'slugs']));

        // If enable_all_locations is on and product has no location terms, use global stock
        if ($enable_all_locations === 'on' && empty($terms)) {
            $product = wc_get_product($product_id);
            return $product ? !$product->is_in_stock() : false;
        }

        // Get location-specific stock
        $location_stock = get_post_meta($product_id, '_location_stock_' . $location_term->term_id, true);
        $location_backorders = get_post_meta($product_id, '_location_backorders_' . $location_term->term_id, true);

        // If no location stock data, check global stock
        if ($location_stock === '') {
            $product = wc_get_product($product_id);
            return $product ? !$product->is_in_stock() : false;
        }

        // Product is out of stock if stock is 0 or less AND backorders are off
        return ($location_stock <= 0 && $location_backorders === 'off');
    }
}
