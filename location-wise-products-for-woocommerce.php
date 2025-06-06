<?php

/**
 * Plugin Name: Location Wise Products for WooCommerce
 * Plugin URI: https://plugincy.com/location-wise-products-for-woocommerce
 * Description: Filter WooCommerce products by store locations with a location selector for customers.
 * Version: 2.0.1
 * Author: plugincy
 * Author URI: https://plugincy.com/
 * Text Domain: location-wise-products-for-woocommerce
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 4.0
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires Plugins: woocommerce
 */

if (!defined('ABSPATH')) exit;

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function () {
        echo '<div class="error"><p>' . esc_html_e('Location Wise Products requires WooCommerce to be installed and active.', 'location-wise-products-for-woocommerce') . '</p></div>';
    });
    return;
}

require_once plugin_dir_path(__FILE__) . 'admin/settings.php';
require_once plugin_dir_path(__FILE__) . 'admin/dashboard.php';
require_once plugin_dir_path(__FILE__) . 'admin/stock-central.php';
require_once plugin_dir_path(__FILE__) . 'admin/admin.php';

class Plugincylwp_Location_Wise_Products
{
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('pre_get_posts', [$this, 'filter_products_by_location']);
        add_filter('woocommerce_shortcode_products_query', [$this, 'filter_shortcode_products']);
        add_filter('woocommerce_products_widget_query_args', [$this, 'filter_widget_products']);
        add_filter('woocommerce_related_products_args', [$this, 'filter_related_products']);
        $options = get_option('lwp_display_options', ['enable_popup' => 'yes']);
        if (isset($options['enable_popup']) && $options['enable_popup'] === 'yes') {
            add_action('wp_footer', [$this, 'location_selector_modal']);
        }
        add_action('init', [$this, 'clear_cart_on_location_change']);

        add_shortcode('store_location_selector', [$this, 'location_selector_shortcode']);

        add_filter('the_title', [$this, 'add_location_to_product_title'], 10, 2);
        add_filter('woocommerce_product_title', [$this, 'add_location_to_wc_product_title'], 10, 2);
        new LWP_Admin();
        add_filter('woocommerce_related_products', [$this, 'filter_related_products_by_location'], 10, 3);
        add_filter('woocommerce_recently_viewed_products_widget_query_args', [$this, 'filter_widget_products_by_location']);
        add_filter('woocommerce_cross_sells_products', [$this, 'filter_cross_sells_by_location'], 10, 1);
        add_filter('woocommerce_upsells_products', [$this, 'filter_upsells_by_location'], 10, 2);
        add_filter('woocommerce_blocks_product_grid_item_html', [$this, 'filter_product_blocks'], 10, 3);
        add_filter('woocommerce_json_search_found_products', [$this, 'filter_ajax_searched_products']);
        add_filter('woocommerce_rest_product_object_query', [$this, 'filter_rest_api_products'], 10, 2);
        add_filter('woocommerce_rest_prepare_product_object', [$this, 'modify_product_rest_response'], 10, 3);
        add_filter('woocommerce_cart_contents', [$this, 'filter_cart_contents'], 10, 1);
        add_action('template_redirect', [$this, 'filter_recently_viewed_products']);

        add_action('wp_ajax_clear_cart', [$this, 'clear_cart']);
        add_action('wp_ajax_nopriv_clear_cart', [$this, 'clear_cart']);

        add_action('wp_ajax_check_cart_products', [$this, 'check_cart_products']);
        add_action('wp_ajax_nopriv_check_cart_products', [$this, 'check_cart_products']);

        add_action('admin_enqueue_scripts', [$this, 'custom_admin_styles']);

        // add settings button after deactivate button in plugins page

        add_action('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_settings_link']);
        add_action('admin_init', [$this, 'add_settings_link']);

        // Save location to order meta
        add_action('woocommerce_thankyou', array($this, 'save_location_to_order_meta'), 10, 2);

        // Use these specific hooks for HPOS orders table
        add_action('woocommerce_order_list_table_restrict_manage_orders', array($this, 'add_store_location_filter'));
        add_filter('woocommerce_order_query_args', array($this, 'filter_orders_by_location'));

        require_once plugin_dir_path(__FILE__) . 'includes/stock-price-backorder-manage.php';

        add_action('wp_ajax_update_product_location_status', [$this, 'cylwp_update_product_location_status']);
        add_action('wp_ajax_get_available_locations', [$this, 'cylwp_get_available_locations']);
        add_action('wp_ajax_save_product_locations', [$this, 'cylwp_save_product_locations']);

        add_action('admin_enqueue_scripts', [$this, 'cylwp_enqueue_admin_scripts']);
    }

    /**
     * Get available locations for a product via AJAX
     */
    public function cylwp_get_available_locations()
    {
        // Check nonce
        check_ajax_referer('location_wise_products_nonce', 'security');

        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'location-wise-products-for-woocommerce')]);
        }

        // Get product ID
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $location_selected = wp_get_object_terms($product_id, 'store_location', array('fields' => 'slugs'));
        if (!$product_id) {
            wp_send_json_error(['message' => __('Invalid product ID.', 'location-wise-products-for-woocommerce')]);
        }

        // Get all locations
        $locations = get_terms([
            'taxonomy' => 'store_location',
            'hide_empty' => false,
        ]);

        if (is_wp_error($locations)) {
            wp_send_json_error(['message' => $locations->get_error_message()]);
        }

        // Format locations for output
        $location_data = [];
        foreach ($locations as $location) {
            $location_data[] = [
                'id' => $location->term_id,
                'name' => $location->name,
                'selected' => in_array($location->slug, $location_selected),
            ];
        }

        wp_send_json_success(['locations' => $location_data]);
    }

    /**
     * Save product locations via AJAX
     */
    public function cylwp_save_product_locations()
    {
        // Check nonce
        check_ajax_referer('location_wise_products_nonce', 'security');

        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'location-wise-products-for-woocommerce')]);
        }

        // Get product ID and location IDs
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $location_ids = isset($_POST['location_ids']) ? array_map('intval', (array) $_POST['location_ids']) : [];

        if (!$product_id) {
            wp_send_json_error(['message' => __('Invalid product ID.', 'location-wise-products-for-woocommerce')]);
        }

        // Set product locations
        wp_set_object_terms($product_id, $location_ids, 'store_location');

        wp_send_json_success([
            'message' => __('Product locations saved successfully.', 'location-wise-products-for-woocommerce'),
        ]);
    }

    /**
     * Update product location status via AJAX
     */
    public function cylwp_update_product_location_status()
    {
        // Check nonce
        check_ajax_referer('location_wise_products_nonce', 'security');

        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'location-wise-products-for-woocommerce')]);
        }

        // Get parameters
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $location_id = isset($_POST['location_id']) ? intval($_POST['location_id']) : 0;
        $action = isset($_POST['status_action']) ? sanitize_text_field(wp_unslash($_POST['status_action'])) : '';

        if (!$product_id || !$location_id || !in_array($action, ['activate', 'deactivate'])) {
            wp_send_json_error(['message' => __('Invalid parameters.', 'location-wise-products-for-woocommerce')]);
        }

        // Update location status
        if ($action === 'activate') {
            // Activate location - remove disabled meta
            delete_post_meta($product_id, '_location_disabled_' . $location_id);
            $message = __('Location activated successfully.', 'location-wise-products-for-woocommerce');
        } else {
            // Deactivate location - add disabled meta
            update_post_meta($product_id, '_location_disabled_' . $location_id, 1);
            $message = __('Location deactivated successfully.', 'location-wise-products-for-woocommerce');
        }

        wp_send_json_success(['message' => $message]);
    }
    /**
     * Enqueue admin scripts
     */
    public function cylwp_enqueue_admin_scripts($hook)
    {
        // Only on product location page
        // if ($hook !== 'woocommerce_page_product-locations') {
        //     return;
        // }

        wp_enqueue_script(
            'location-wise-products-for-woocommerces-admin',
            plugin_dir_url(__FILE__) . 'assets/js/admin.js',
            ['jquery'],
            '2.0.1',
            true
        );

        wp_localize_script('location-wise-products-for-woocommerces-admin', 'locationWiseProducts', [
            'nonce' => wp_create_nonce('location_wise_products_nonce'),
            'i18n' => [
                'activate' => __('Activate', 'location-wise-products-for-woocommerce'),
                'deactivate' => __('Deactivate', 'location-wise-products-for-woocommerce'),
                'selectLocations' => __('Select Locations', 'location-wise-products-for-woocommerce'),
                'saveLocations' => __('Save Locations', 'location-wise-products-for-woocommerce'),
                'ajaxError' => __('An error occurred. Please try again.', 'location-wise-products-for-woocommerce'),
            ],
        ]);

        // Add modal styles
        wp_enqueue_style(
            'location-wise-products-for-woocommerces-admin',
            plugin_dir_url(__FILE__) . 'assets/css/admin.css',
            [],
            '2.0.1'
        );
    }

    /**
     * Save location from cookie to order meta
     *
     * @param WC_Order $order Order object
     * @param array $data Order data
     */
    public function save_location_to_order_meta($order_id)
    {
        $location = isset($_COOKIE['store_location']) ? sanitize_text_field(wp_unslash($_COOKIE['store_location'])) : '';

        if (!empty($location)) {
            $order = wc_get_order($order_id);
            if ($order) {
                $order->update_meta_data('_store_location', $location);
                $order->save();
            }
        }
    }
    private function get_all_store_locations()
    {
        $locations = get_terms(array(
            'taxonomy' => 'store_location',
            'hide_empty' => false,
        ));

        if (is_wp_error($locations)) {
            return array();
        }

        return wp_list_pluck($locations, 'slug');
    }

    /**
     * Add filter dropdown in the WooCommerce orders list table
     */
    public function add_store_location_filter()
    {

        $locations = $this->get_all_store_locations();

        if (!isset($_GET['store_location_filter_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['store_location_filter_nonce'])), 'store_location_filter_nonce')) {
            $selected_location = '';
        } else {
            $selected_location = isset($_GET['store_location']) ? sanitize_text_field(wp_unslash($_GET['store_location'])) : '';
        }

        // add nonce for security
        wp_nonce_field('store_location_filter_nonce', 'store_location_filter_nonce');

        echo '<select name="store_location" id="store_location">';
        echo '<option value="">' . esc_html__('All Locations', 'location-wise-products-for-woocommerce') . '</option>';

        foreach ($locations as $location) {
            $selected = ($location === $selected_location) ? 'selected' : '';
            echo '<option value="' . esc_attr($location) . '" ' . esc_attr($selected) . '>' . esc_html(ucfirst(strtolower($location))) . '</option>';
        }

        echo '</select>';
    }

    /**
     * Filter orders by store location
     * 
     * @param array $query_args Query arguments
     * @return array Modified query arguments
     */
    public function filter_orders_by_location($query_args)
    {
        if (!isset($_GET['store_location_filter_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['store_location_filter_nonce'])), 'store_location_filter_nonce')) {
            $selected_location = '';
        } else {
            $selected_location = isset($_GET['store_location']) ? sanitize_text_field(wp_unslash($_GET['store_location'])) : '';
        }

        if (!empty($selected_location)) {
            $query_args['meta_query'][] = [
                'key' => '_store_location',
                'value' => $selected_location,
                'compare' => '='
            ];
        }

        return $query_args;
    }

    // add_settings_link
    public function add_settings_link($links)
    {
        if (!is_array($links)) {
            $links = [];
        }
        $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=location-wise-products-for-woocommerce')) . '">' . esc_html__('Settings', 'location-wise-products-for-woocommerce') . '</a>';
        $pro_link = '<a href="https://plugincy.com/location-wise-products-for-woocommerce" style="color: #ff5722; font-weight: bold;" target="_blank">' . esc_html__('Get Pro', 'location-wise-products-for-woocommerce') . '</a>';
        $links[] = $settings_link;
        $links[] = $pro_link;
        return $links;
    }

    public function enqueue_scripts()
    {
        wp_enqueue_style('location-wise-products-for-woocommerce', plugins_url('assets/css/style.css', __FILE__), [], '2.0.1');
        wp_enqueue_script('location-wise-products-for-woocommerce', plugins_url('assets/js/script.js', __FILE__), ['jquery'], '2.0.1', true);

        wp_localize_script('location-wise-products-for-woocommerce', 'locationWiseProducts', [
            'cartHasProducts' => !WC()->cart->is_empty(),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('location-wise-products-for-woocommerce')
        ]);
    }

    private function get_current_location()
    {
        return isset($_COOKIE['store_location']) ? sanitize_text_field(wp_unslash($_COOKIE['store_location'])) : '';
    }

    public function filter_shortcode_products($query_args)
    {
        $location = $this->get_current_location();
        if (!$location || $location === 'all-products') {
            return $query_args;
        }

        // if (!isset($query_args['tax_query'])) {
        //     $query_args['tax_query'] = [];
        // }

        $query_args['tax_query'][] = [
            'taxonomy' => 'store_location',
            'field' => 'slug',
            'terms' => $location,
        ];

        return $query_args;
    }

    public function filter_widget_products($query_args)
    {
        return $this->filter_shortcode_products($query_args);
    }

    public function filter_related_products($args)
    {
        return $this->filter_shortcode_products($args);
    }

    public function clear_cart_on_location_change()
    {
        if (!isset($_POST['plugincylwp_shortcode_selector_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['plugincylwp_shortcode_selector_nonce'])), 'plugincylwp_shortcode_selector')) {
            return;
        }
        if (isset($_POST['clear_cart_on_store_change']) && $_POST['clear_cart_on_store_change'] == '1') {
            if (function_exists('WC')) {
                WC()->cart->empty_cart();
                WC()->session->set('cart', []);
            }
        }
    }

    public function location_selector_modal()
    {
        $is_user_logged_in = is_user_logged_in();
        $current_user = wp_get_current_user();
        $is_admin_or_manager = in_array('administrator', $current_user->roles) || in_array('shop_manager', $current_user->roles);
        $selected_location = $this->get_current_location();
        $show_modal = !$is_user_logged_in && empty($selected_location) && !$is_admin_or_manager;

        $locations = get_terms([
            'taxonomy' => 'store_location',
            'hide_empty' => false,
        ]);

        include plugin_dir_path(__FILE__) . 'templates/modal.php';
    }

    public function location_selector_shortcode($atts)
    {
        $atts = shortcode_atts([
            'title' => __('Select Store Location', 'location-wise-products-for-woocommerce'),
            'show_title' => 'yes',
            'class' => '',
            'show_button' => '',
            'use_select2' => '',
            'herichical' => '',
            'show_count' => '',
        ], $atts);

        $is_user_logged_in = is_user_logged_in();
        $current_user = wp_get_current_user();
        $is_admin_or_manager = in_array('administrator', $current_user->roles) || in_array('shop_manager', $current_user->roles);
        $selected_location = $this->get_current_location();

        $locations = get_terms([
            'taxonomy' => 'store_location',
            'hide_empty' => false,
        ]);

        ob_start();
        include plugin_dir_path(__FILE__) . 'templates/shortcode-selector.php';
        return ob_get_clean();
    }

    public function add_location_to_product_title($title, $post_id = 0)
    {
        if (!$post_id || get_post_type($post_id) !== 'product') {
            return $title;
        }
        return $this->get_title_with_location($title, $post_id);
    }

    public function add_location_to_wc_product_title($title, $product = null)
    {
        if (!$product) {
            return $title;
        }
        return $this->get_title_with_location($title, $product->get_id());
    }

    
    private function get_display_options()
    {
        $defaults = [
            'display_format' => 'none',
            'separator' => ' - ',
            'enabled_pages' => [],
            'strict_filtering' => "enabled",
            'filtered_sections' => ['shop', 'search', 'related', 'recently_viewed', 'cross_sells', 'upsells', 'widgets', 'blocks', 'rest_api'],
            'enable_location_stock' => 'yes',
            'enable_location_price' => 'yes',
            'enable_location_backorder' => 'yes',
            'enable_all_locations' => 'yes',
            'enable_popup' => 'yes',
            'title_show_popup' => 'yes',
        ];
        $options = get_option('lwp_display_options', []);
        return wp_parse_args($options, $defaults);
    }

    private function get_title_with_location($title, $product_id)
    {
        $locations = get_the_terms($product_id, 'store_location');
        if (!$locations || is_wp_error($locations)) {
            return $title;
        }

        $options = $this->get_display_options();
        $enabled_pages = isset($options['enabled_pages']) ? $options['enabled_pages'] : ['shop', 'single', 'cart'];
        $should_display = false;

        // Check standard WooCommerce pages
        if (in_array('shop', $enabled_pages) && (is_shop() || is_product_category() || is_product_tag() || is_post_type_archive('product'))) {
            $should_display = true;
        } elseif (in_array('single', $enabled_pages) && is_singular('product')) {
            $should_display = true;
        } elseif (in_array('cart', $enabled_pages) && (is_cart() || is_checkout())) {
            $should_display = true;
        } elseif (in_array('search', $enabled_pages) && is_search()) {
            $should_display = true;
        } elseif (in_array('widgets', $enabled_pages) && (is_active_widget(false, false, 'woocommerce_products', true) || is_active_widget(false, false, 'woocommerce_recent_products', true))) {
            $should_display = true;
        } elseif (
            in_array('Shortcode', $enabled_pages) && !is_shop() && !is_product_category() && !is_product_tag() &&
            !is_product() && !is_cart() && !is_checkout() && !is_account_page() && !is_admin()
        ) {
            $should_display = true;
        }

        if (!$should_display) {
            return $title;
        }

        $location_names = [];
        foreach ($locations as $location) {
            $location_names[] = $location->name;
        }

        $location_text = count($location_names) === 1 ? $location_names[0] : implode(', ', $location_names);
        $separator = isset($options['separator']) ? $options['separator'] : ' - ';
        $format = isset($options['display_format']) ? $options['display_format'] : 'append';

        switch ($format) {
            case 'prepend':
                return $location_text . $separator . $title;
            case 'brackets':
                return $title . ' [' . $location_text . ']';
            case 'none':
                return $title;
            case 'append':
            default:
                return $title . $separator . $location_text;
        }
    }

    private function product_belongs_to_location($product_id)
    {
        $location = $this->get_current_location();
        $options = get_option('lwp_display_options', []);
        $enable_all_locations = isset($options['enable_all_locations']) ? $options['enable_all_locations'] : 'yes';

        if (!$location || $location === 'all-products') {
            return true;
        }

        $terms = wp_get_object_terms($product_id, 'store_location', ['fields' => 'slugs']);
        if (empty($terms) && $enable_all_locations === 'yes') {
            return true; // Product is available in all locations
        }
        return (!is_wp_error($terms) && in_array($location, $terms));
    }

    public function filter_product_blocks($html, $data, $product)
    {
        if (!$this->product_belongs_to_location($product->get_id())) {
            return '';
        }
        return $html;
    }

    public function filter_ajax_searched_products($products)
    {
        $location = $this->get_current_location();
        $options = get_option('lwp_display_options', []);
        $enable_all_locations = isset($options['enable_all_locations']) ? $options['enable_all_locations'] : 'yes';

        if (!$location || $location === 'all-products') {
            return $products;
        }

        foreach ($products as $id => $product) {
            if (!$this->product_belongs_to_location($id)) {
                unset($products[$id]);
            }
        }

        return $products;
    }

    public function filter_rest_api_products($args, $request)
    {
        $location = $this->get_current_location();

        if (!$location || $location === 'all-products') {
            return $args;
        }

        // if (!isset($args['tax_query'])) {
        //     $args['tax_query'] = [];
        // }

        $args['tax_query'][] = [
            'taxonomy' => 'store_location',
            'field' => 'slug',
            'terms' => $location,
        ];

        return $args;
    }

    public function modify_product_rest_response($response, $product, $request)
    {
        if (!$this->product_belongs_to_location($product->get_id())) {
            $data = $response->get_data();
            $data['hidden_by_location'] = true;
            $response->set_data($data);
        }
        return $response;
    }

    public function filter_cart_contents($cart_contents)
    {
        $location = $this->get_current_location();

        if (!$location || $location === 'all-products') {
            return $cart_contents;
        }

        foreach ($cart_contents as $key => $item) {
            if (!$this->product_belongs_to_location($item['product_id'])) {
                $cart_contents[$key]['hidden_by_location'] = true;
            }
        }

        return $cart_contents;
    }

    public function filter_recently_viewed_products()
    {
        $location = $this->get_filtered_location('recently_viewed');

        if (!$location) {
            return;
        }

        $viewed_products = isset($_COOKIE['woocommerce_recently_viewed']) ? (array) explode('|', sanitize_text_field(wp_unslash($_COOKIE['woocommerce_recently_viewed']))) : [];

        if (empty($viewed_products)) {
            return;
        }

        $filtered_products = [];
        foreach ($viewed_products as $product_id) {
            if ($this->product_belongs_to_location($product_id)) {
                $filtered_products[] = $product_id;
            }
        }

        if (count($filtered_products) !== count($viewed_products)) {
            $filtered_cookie = implode('|', $filtered_products);
            wc_setcookie('woocommerce_recently_viewed', $filtered_cookie, time() + 60 * 60 * 24 * 30);
        }
    }

    

    

    

    private function should_apply_filtering($section)
    {
        $options = $this->get_display_options();
        $location = $this->get_current_location();
        if (!$location || $location === 'all-products') {
            return false;
        }

        if (isset($options['strict_filtering']) && $options['strict_filtering'] === 'disabled') {
            return false;
        }

        $filtered_sections = isset($options['filtered_sections']) ? $options['filtered_sections'] : [];
        return in_array($section, $filtered_sections);
    }

    private function get_filtered_location($section)
    {
        if (!$this->should_apply_filtering($section)) {
            return false;
        }
        return $this->get_current_location();
    }

    public function filter_products_by_location($query)
    {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }

        $section = '';
        if (is_shop() || is_product_category() || is_product_tag() || is_post_type_archive('product')) {
            $section = 'shop';
        } elseif (is_search()) {
            $section = 'search';
        } else {
            return;
        }

        $location = $this->get_filtered_location($section);

        if (!$location) {
            return;
        }

        $tax_query = (array) $query->get('tax_query');
        $tax_query[] = [
            'taxonomy' => 'store_location',
            'field' => 'slug',
            'terms' => $location,
        ];

        $query->set('tax_query', $tax_query);
    }

    public function filter_related_products_by_location($related_products, $product_id, $args)
    {
        $location = $this->get_filtered_location('related');

        if (!$location) {
            return $related_products;
        }

        return array_filter($related_products, [$this, 'product_belongs_to_location']);
    }

    public function filter_cross_sells_by_location($cross_sells)
    {
        $location = $this->get_filtered_location('cross_sells');

        if (!$location) {
            return $cross_sells;
        }

        return array_filter($cross_sells, [$this, 'product_belongs_to_location']);
    }

    public function filter_upsells_by_location($upsell_ids, $product_id)
    {
        $location = $this->get_filtered_location('upsells');

        if (!$location) {
            return $upsell_ids;
        }

        return array_filter($upsell_ids, [$this, 'product_belongs_to_location']);
    }

    public function filter_widget_products_by_location($query_args)
    {
        $location = $this->get_filtered_location('widgets');

        if (!$location) {
            return $query_args;
        }

        // if (!isset($query_args['tax_query'])) {
        //     $query_args['tax_query'] = [];
        // }

        $query_args['tax_query'][] = [
            'taxonomy' => 'store_location',
            'field' => 'slug',
            'terms' => $location,
        ];

        return $query_args;
    }

    function clear_cart()
    {
        // Check if WooCommerce is active
        if (class_exists('WooCommerce')) {
            WC()->cart->empty_cart(); // Clear the cart
            wp_send_json_success(); // Send a success response
        } else {
            wp_send_json_error(); // Send an error response
        }

        wp_die(); // Always call wp_die() at the end of AJAX functions
    }

    function check_cart_products()
    {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            wp_send_json_error('WooCommerce is not active.');
        }

        // Check if the cart has products
        $cart_has_products = !WC()->cart->is_empty();

        // Return response
        wp_send_json_success(array('cartHasProducts' => $cart_has_products));
    }
    function custom_admin_styles()
    {
        wp_enqueue_style('custom-admin-style', plugin_dir_url(__FILE__) . 'assets/css/admin-style.css', array(), "2.0.1");
    }

    
}

function Plugincylwp_location_wise_products_init()
{
    new Plugincylwp_Location_Wise_Products();
}

add_action('plugins_loaded', 'Plugincylwp_location_wise_products_init');



register_uninstall_hook(__FILE__, 'lwp_settings_remove');

register_activation_hook(__FILE__, 'lwp_settings_remove');

function lwp_settings_remove()
{
    // Check if the option exists and delete it
    if (get_option('lwp_display_options') !== false) {
        delete_option('lwp_display_options');
    }
}
