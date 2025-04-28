<?php

/**
 * Plugin Name: Location Wise Products for WooCommerce
 * Plugin URI: https://plugincy.com/location-wise-products-for-woocommerce
 * Description: Filter WooCommerce products by store locations with a location selector for customers.
 * Version: 2.0.0
 * Author: plugincy
 * Author URI: https://plugincy.com/
 * Text Domain: location-wise-products-for-woocommerce
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 4.0
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) exit;

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function () {
        echo '<div class="error"><p>' . esc_html_e('Location Wise Products requires WooCommerce to be installed and active.', 'location-wise-products-for-woocommerce') . '</p></div>';
    });
    return;
}

class Plugincylwp_Location_Wise_Products
{
    public function __construct()
    {
        add_action('init', [$this, 'register_store_location_taxonomy']);
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

        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);

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

        // Add custom column to orders table
        add_filter('manage_woocommerce_page_wc-orders_columns', array($this, 'add_location_column'), 20);
        add_action('manage_woocommerce_page_wc-orders_custom_column', array($this, 'display_location_column_content'), 20, 2);

        // Add metabox to order details
        add_action('add_meta_boxes', array($this, 'add_location_metabox'));

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
            '2.0.0',
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
            '2.0.0'
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

    /**
     * Add location column to orders table
     *
     * @param array $columns Order list columns
     * @return array Modified columns
     */
    public function add_location_column($columns)
    {
        $new_columns = array();

        foreach ($columns as $column_name => $column_info) {
            $new_columns[$column_name] = $column_info;

            if ('order_status' === $column_name) {
                $new_columns['store_location'] = __('Store Location', 'location-wise-products-for-woocommerce');
            }
        }

        return $new_columns;
    }

    /**
     * Display location in orders table column
     *
     * @param string $column Column name
     * @param WC_Order $order Order object
     */
    public function display_location_column_content($column, $order)
    {
        if ($column == 'store_location') {
            $location = $order->get_meta('_store_location');
            echo esc_html($location ? ucfirst(strtolower($location)) : '—');
        }
    }

    /**
     * Add location metabox to order details
     */
    public function add_location_metabox()
    {
        $screen = $this->get_order_screen_id();

        add_meta_box(
            'wc_store_location_metabox',
            __('Store Location', 'location-wise-products-for-woocommerce'),
            array($this, 'render_location_metabox'),
            $screen,
            'side',
            'high'
        );
    }

    /**
     * Get appropriate screen ID based on WooCommerce version
     *
     * @return string Screen ID
     */
    private function get_order_screen_id()
    {
        // Check if we're using the HPOS (High-Performance Order Storage)
        if (class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController')) {
            $controller = wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class);

            if (
                method_exists($controller, 'custom_orders_table_usage_is_enabled') &&
                $controller->custom_orders_table_usage_is_enabled()
            ) {
                return wc_get_page_screen_id('shop-order');
            }
        }

        return 'shop_order';
    }

    /**
     * Render location metabox content
     *
     * @param mixed $object Post or order object
     */
    public function render_location_metabox($object)
    {
        // Get the WC_Order object
        $order = is_a($object, 'WP_Post') ? wc_get_order($object->ID) : $object;

        if (!$order) {
            return;
        }

        $location = $order->get_meta('_store_location');

        echo '<div class="wc-store-location-container">';

        if (!empty($location)) {
            echo '<p>' . esc_html(ucfirst(strtolower($location))) . '</p>';
        } else {
            echo '<p>' . esc_html__('No location data available', 'location-wise-products-for-woocommerce') . '</p>';
        }

        echo '</div>';
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

    public function register_store_location_taxonomy()
    {
        register_taxonomy('store_location', 'product', [
            'labels' => [
                'name' => __('locations', 'location-wise-products-for-woocommerce'),
                'singular_name' => __('Store Location', 'location-wise-products-for-woocommerce'),
                'search_items' => __('Search Store Locations', 'location-wise-products-for-woocommerce'),
                'all_items' => __('All Store Locations', 'location-wise-products-for-woocommerce'),
                'edit_item' => __('Edit Store Location', 'location-wise-products-for-woocommerce'),
                'update_item' => __('Update Store Location', 'location-wise-products-for-woocommerce'),
                'add_new_item' => __('Add New Store Location', 'location-wise-products-for-woocommerce'),
                'new_item_name' => __('New Store Location Name', 'location-wise-products-for-woocommerce'),
                'menu_name' => __('Store Locations', 'location-wise-products-for-woocommerce'),
            ],
            'hierarchical' => true,
            'show_ui' => true,
            // 'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'store-location'],
        ]);
    }

    public function enqueue_scripts()
    {
        wp_enqueue_style('location-wise-products-for-woocommerce', plugins_url('assets/css/style.css', __FILE__), [], '2.0.0');
        wp_enqueue_script('location-wise-products-for-woocommerce', plugins_url('assets/js/script.js', __FILE__), ['jquery'], '2.0.0', true);

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

    public function add_settings_page()
    {
        // Add main menu page
        add_menu_page(
            __('Location Manage', 'location-wise-products-for-woocommerce'),
            __('Location Manage', 'location-wise-products-for-woocommerce'),
            'manage_options',
            'location-wise-products-for-woocommerce',
            [$this, 'dashboard_page_content'],
            'dashicons-location-alt',
            56
        );

        // Add Dashboard submenu
        add_submenu_page(
            'location-wise-products-for-woocommerce',
            __('Dashboard', 'location-wise-products-for-woocommerce'),
            __('Dashboard', 'location-wise-products-for-woocommerce'),
            'manage_options',
            'location-wise-products-for-woocommerce',
            [$this, 'dashboard_page_content']
        );

        // Add Locations submenu
        add_submenu_page(
            'location-wise-products-for-woocommerce',
            __('Locations', 'location-wise-products-for-woocommerce'),
            __('Locations', 'location-wise-products-for-woocommerce'),
            'manage_options',
            'edit-tags.php?taxonomy=store_location&post_type=product',
            null,
            56
        );

        // Ensure the menu is expanded and active when this page is active
        add_filter('parent_file', function ($parent_file) {
            global $pagenow, $taxonomy;

            if ($pagenow === 'edit-tags.php' && $taxonomy === 'store_location') {
                $parent_file = 'location-wise-products-for-woocommerce';
            }

            return $parent_file;
        });

        // Add current class to the active menu item
        add_filter('submenu_file', function ($submenu_file) {
            global $pagenow, $taxonomy;

            if ($pagenow === 'edit-tags.php' && $taxonomy === 'store_location') {
                $submenu_file = 'edit-tags.php?taxonomy=store_location&post_type=product';
            }

            return $submenu_file;
        });

        // add Stock Central submenu
        add_submenu_page(
            'location-wise-products-for-woocommerce',
            __('Stock Central', 'location-wise-products-for-woocommerce'),
            __('Stock Central', 'location-wise-products-for-woocommerce'),
            'manage_options',
            'location-stock-management',
            [$this, 'location_stock_page_content']
        );

        // Add Settings submenu
        add_submenu_page(
            'location-wise-products-for-woocommerce',
            __('Settings', 'location-wise-products-for-woocommerce'),
            __('Settings', 'location-wise-products-for-woocommerce'),
            'manage_options',
            'location-wise-products-for-woocommerce-settings',
            [$this, 'settings_page_content']
        );
    }


    public function location_stock_page_content()
    {
        // Include required file for WP_List_Table
        if (!class_exists('WP_List_Table')) {
            require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
        }

        // Include our custom table class
        require_once plugin_dir_path(__FILE__) . 'includes/class-product-location-table.php';

        // Create an instance of our table class
        $product_table = new Plugincylwp_Product_Location_Table();

        // Prepare the items to display in the table
        $product_table->prepare_items();

?>
        <div class="wrap">
            <h1><?php esc_html_e('Location Wise Products Stock Management', 'location-wise-products-for-woocommerce'); ?></h1>
            <p><?php esc_html_e('Manage stock levels and prices for each product by location.', 'location-wise-products-for-woocommerce'); ?></p>

            <form method="post">
                <?php $product_table->search_box('Search Products', 'search_products'); ?>
                <?php $product_table->display(); ?>
            </form>
        </div>
    <?php
    }

    /**
     * Render the dashboard page content
     * 
     * @return void
     */
    public function dashboard_page_content()
    {
        // Enqueue necessary scripts and styles
        wp_enqueue_script('chart-js', plugin_dir_url(__FILE__) . 'assets/js/chart.min.js', array(), '3.9.1', true);
        wp_enqueue_script('lwp-dashboard-js', plugin_dir_url(__FILE__) . 'assets/js/dashboard.js', array('jquery', 'chart-js'), "2.0.0", true);
        wp_enqueue_style('lwp-dashboard-css', plugin_dir_url(__FILE__) . 'assets/css/dashboard.css', array(), "2.0.0");

        // Get all locations
        $locations = get_terms([
            'taxonomy' => 'store_location',
            'hide_empty' => false,
        ]);

        // Product counts by location
        $product_counts = [];
        $stock_levels = [];
        $location_colors = [];
        $location_border_colors = [];

        // Generate random colors for each location
        foreach ($locations as $index => $location) {
            // Generate pastel colors
            $hue = ($index * 47) % 360; // Spread colors evenly
            $location_colors[$location->name] = "hsla({$hue}, 70%, 70%, 0.7)";
            $location_border_colors[$location->name] = "hsla({$hue}, 70%, 60%, 1.0)";

            // Get products in this location
            $products_in_location = new WP_Query([
                'post_type' => 'product',
                'posts_per_page' => -1,
                'tax_query' => [
                    [
                        'taxonomy' => 'store_location',
                        'field' => 'term_id',
                        'terms' => $location->term_id,
                    ]
                ]
            ]);

            $product_counts[$location->name] = $products_in_location->found_posts;

            // Calculate total stock for this location
            $total_stock = 0;
            if ($products_in_location->have_posts()) {
                while ($products_in_location->have_posts()) {
                    $products_in_location->the_post();
                    $product_id = get_the_ID();
                    $location_stock = get_post_meta($product_id, '_location_stock_' . $location->term_id, true);
                    $total_stock += !empty($location_stock) ? intval($location_stock) : 0;
                }
            }
            $stock_levels[$location->name] = $total_stock;

            wp_reset_postdata();
        }

        // Get orders by location data
        $orders_by_location = [];
        $location_revenue = [];
        $location_slugs = [];

        // Add default location
        $orders_by_location['Default'] = 0;
        $location_revenue['Default'] = 0;
        $location_slugs['Default'] = 'default';

        // Get slugs for all locations
        foreach ($locations as $location) {
            $location_slugs[$location->name] = $location->slug;
            $orders_by_location[$location->name] = 0;
            $location_revenue[$location->name] = 0;
        }

        // Query orders for the last 30 days
        $args = array(
            'status' => ['completed', 'pending', 'processing'], // You can change this to any order status you need
            'date_created' => '>' . gmdate('Y-m-d', strtotime('-30 days')), // Orders from the last 30 days
        );

        $orders = wc_get_orders($args);

        if (!empty($orders)) {
            foreach ($orders as $order) {
                $order_id = $order->get_id();
                $order = wc_get_order($order_id);

                if (!$order) continue;

                // Get store location from order meta
                $order_location = $order->get_meta('_store_location');
                $order_total = $order->get_total();

                // Find location name from slug
                $location_name = 'Default';
                foreach ($location_slugs as $name => $slug) {
                    if ($slug === $order_location) {
                        $location_name = $name;
                        break;
                    }
                }

                // Increment count and revenue for this location
                if (isset($orders_by_location[$location_name])) {
                    $orders_by_location[$location_name]++;
                    $location_revenue[$location_name] += $order_total;
                } else {
                    $orders_by_location['Default']++;
                    $location_revenue['Default'] += $order_total;
                }
            }
        }
        wp_reset_postdata();

        // Get low stock products (less than 5 in stock) across locations
        $low_stock_query = new WP_Query([
            'post_type' => 'product',
            'posts_per_page' => 10,
            'meta_query' => [
                [
                    'key' => '_stock',
                    'value' => 5,
                    'compare' => '<=',
                    'type' => 'NUMERIC'
                ]
            ]
        ]);

        // Prepare data for recent products chart (last 30 days)
        $days = 30;
        $date_counts = [];
        $labels = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = gmdate('Y-m-d', strtotime("-$i days"));
            $labels[] = gmdate('M d', strtotime("-$i days"));

            $args = [
                'post_type' => 'product',
                'posts_per_page' => -1,
                'date_query' => [
                    [
                        'year'  => gmdate('Y', strtotime($date)),
                        'month' => gmdate('m', strtotime($date)),
                        'day'   => gmdate('d', strtotime($date)),
                    ]
                ]
            ];

            $daily_query = new WP_Query($args);
            $date_counts[] = $daily_query->found_posts;
        }

        // Get monthly investment data
        $monthly_investment_data = $this->get_monthly_investment_data();

        wp_localize_script('lwp-dashboard-js', 'lwpDashboardData', [
            'productCounts' => $product_counts,
            'stockLevels' => $stock_levels,
            'locationColors' => $location_colors,
            'locationBorderColors' => $location_border_colors,
            'dateLabels' => $labels,
            'dateCounts' => $date_counts,
            'ordersByLocation' => $orders_by_location,
            'revenueByLocation' => $location_revenue,
            'monthlyInvestmentLabels' => $monthly_investment_data['labels'],
            'monthlyInvestmentData' => $monthly_investment_data['data'],
            'i18n' => [
                'totalStock' => __('Total Stock', 'location-wise-products-for-woocommerce'),
                'newProducts' => __('New Products', 'location-wise-products-for-woocommerce'),
                'investment' => __('Investment', 'location-wise-products-for-woocommerce'),
                'orders' => __('Orders', 'location-wise-products-for-woocommerce'),
                'revenue' => __('Revenue', 'location-wise-products-for-woocommerce')
            ]
        ]);

    ?>
        <div class="wrap lwp-dashboard">
            <h1><?php esc_attr_e('Location Wise Products Dashboard', 'location-wise-products-for-woocommerce'); ?></h1>

            <div class="lwp-dashboard-overview">
                <div class="lwp-card lwp-card-stats">
                    <h2><?php esc_html_e('Quick Stats', 'location-wise-products-for-woocommerce'); ?></h2>
                    <div class="lwp-stats-grid">
                        <div class="lwp-stat-item">
                            <span class="lwp-stat-value"><?php echo esc_html(wp_count_posts('product')->publish); ?></span>
                            <span class="lwp-stat-label"><?php esc_html_e('Total Products', 'location-wise-products-for-woocommerce'); ?></span>
                        </div>
                        <div class="lwp-stat-item">
                            <span class="lwp-stat-value"><?php echo count($locations); ?></span>
                            <span class="lwp-stat-label"><?php esc_html_e('Locations', 'location-wise-products-for-woocommerce'); ?></span>
                        </div>
                        <div class="lwp-stat-item">
                            <span class="lwp-stat-value"><?php echo esc_html(array_sum($orders_by_location)); ?></span>
                            <span class="lwp-stat-label"><?php esc_html_e('Orders (30 days)', 'location-wise-products-for-woocommerce'); ?></span>
                        </div>
                        <div class="lwp-stat-item">
                            <span class="lwp-stat-value"><?php echo wp_kses_post(wc_price($this->calculate_total_purchase_value())); ?></span>
                            <span class="lwp-stat-label"><?php esc_html_e('Total Investment', 'location-wise-products-for-woocommerce'); ?></span>
                        </div>
                        <div class="lwp-stat-item">
                            <span class="lwp-stat-value"><?php echo wp_kses_post(wc_price(array_sum($location_revenue))); ?></span>
                            <span class="lwp-stat-label"><?php esc_html_e('Revenue (30 days)', 'location-wise-products-for-woocommerce'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lwp-dashboard-charts">
                <div class="lwp-row">
                    <div class="lwp-col">
                        <div class="lwp-card">
                            <h2><?php esc_html_e('Products by Location', 'location-wise-products-for-woocommerce'); ?></h2>
                            <div class="lwp-chart-container">
                                <canvas id="locationProductsChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="lwp-col">
                        <div class="lwp-card">
                            <h2><?php esc_html_e('Stock Levels by Location', 'location-wise-products-for-woocommerce'); ?></h2>
                            <div class="lwp-chart-container">
                                <canvas id="locationStockChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lwp-row">
                    <div class="lwp-col">
                        <div class="lwp-card">
                            <h2><?php esc_html_e('Orders by Location (30 days)', 'location-wise-products-for-woocommerce'); ?></h2>
                            <div class="lwp-chart-container">
                                <canvas id="ordersByLocationChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="lwp-col">
                        <div class="lwp-card">
                            <h2><?php esc_html_e('Revenue by Location (30 days)', 'location-wise-products-for-woocommerce'); ?></h2>
                            <div class="lwp-chart-container">
                                <canvas id="revenueByLocationChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lwp-row">
                    <div class="lwp-col">
                        <div class="lwp-card">
                            <h2><?php esc_html_e('New Products (Last 30 Days)', 'location-wise-products-for-woocommerce'); ?></h2>
                            <div class="lwp-chart-container">
                                <canvas id="newProductsChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="lwp-col">
                        <div class="lwp-card">
                            <h2><?php esc_html_e('Investment', 'location-wise-products-for-woocommerce'); ?></h2>
                            <div class="lwp-chart-container">
                                <canvas id="investment-30day"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lwp-row">
                    <div class="lwp-col">
                        <div class="lwp-card">
                            <h2><?php esc_html_e('Low Stock Products', 'location-wise-products-for-woocommerce'); ?></h2>
                            <?php if ($low_stock_query->have_posts()) : ?>
                                <table class="lwp-low-stock-table">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('Product', 'location-wise-products-for-woocommerce'); ?></th>
                                            <th><?php esc_html_e('Stock', 'location-wise-products-for-woocommerce'); ?></th>
                                            <th><?php esc_html_e('Location', 'location-wise-products-for-woocommerce'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($low_stock_query->have_posts()) : $low_stock_query->the_post();
                                            $product_id = get_the_ID();
                                            $product = wc_get_product($product_id);
                                            $product_locations = wp_get_object_terms($product_id, 'store_location');
                                        ?>
                                            <tr>
                                                <td>
                                                    <a href="<?php echo esc_url(get_edit_post_link($product_id)); ?>">
                                                        <?php echo esc_html(get_the_title()); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo esc_html($product->get_stock_quantity()); ?></td>
                                                <td>
                                                    <?php
                                                    if (!empty($product_locations) && !is_wp_error($product_locations)) {
                                                        $location_names = array_map(function ($term) {
                                                            return $term->name;
                                                        }, $product_locations);
                                                        echo esc_html(implode(', ', $location_names));
                                                    } else {
                                                        esc_html_e('Default', 'location-wise-products-for-woocommerce');
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p><?php esc_html_e('No low stock products found.', 'location-wise-products-for-woocommerce'); ?></p>
                            <?php endif;
                            wp_reset_postdata(); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Calculate total purchase value of all products
     *
     * @return float The total purchase value
     */
    public function calculate_total_purchase_value()
    {
        $total_value = 0;

        $args = array(
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        );

        $products = new WP_Query($args);

        if ($products->have_posts()) {
            while ($products->have_posts()) {
                $products->the_post();
                $product_id = get_the_ID();
                $product = wc_get_product($product_id);

                if (!$product) {
                    continue;
                }

                // Get orders containing this product in the last 30 days
                $order_count = $this->get_product_order_count(
                    $product_id,
                    gmdate('Y-m-d H:i:s', strtotime('-30 days')),
                    gmdate('Y-m-d H:i:s')
                );

                if ($product->is_type('simple')) {
                    $purchase_price = get_post_meta($product_id, '_purchase_price', true);
                    $stock_quantity = $product->get_stock_quantity() ?: 0;

                    if (is_numeric($purchase_price)) {
                        // Value = purchase_price * (current_stock + number_of_orders)
                        $total_value += floatval($purchase_price) * ($stock_quantity + $order_count);
                    }
                } elseif ($product->is_type('variable')) {
                    $variations = $product->get_available_variations();

                    foreach ($variations as $variation) {
                        $variation_id = $variation['variation_id'];
                        $variation_obj = wc_get_product($variation_id);

                        if (!$variation_obj) {
                            continue;
                        }

                        $purchase_price = get_post_meta($variation_id, '_purchase_price', true);
                        $stock_quantity = $variation_obj->get_stock_quantity() ?: 0;
                        $variation_order_count = $this->get_product_order_count(
                            $variation_id,
                            gmdate('Y-m-d H:i:s', strtotime('-30 days')),
                            gmdate('Y-m-d H:i:s')
                        );

                        if (is_numeric($purchase_price)) {
                            // Value = purchase_price * (current_stock + number_of_orders)
                            $total_value += floatval($purchase_price) * ($stock_quantity + $variation_order_count);
                        }
                    }
                }
            }
        }

        wp_reset_postdata();
        return $total_value;
    }

    public function register_settings()
    {
        register_setting('location_wise_products_settings', 'lwp_display_options', 'sanitize_settings');

        add_settings_section(
            'lwp_display_settings_section',
            __('Product Title Display Settings', 'location-wise-products-for-woocommerce'),
            [$this, 'settings_section_callback'],
            'location-wise-products-for-woocommerce'
        );

        add_settings_field(
            'lwp_display_format',
            __('Location Display Format', 'location-wise-products-for-woocommerce'),
            [$this, 'display_format_field_callback'],
            'location-wise-products-for-woocommerce',
            'lwp_display_settings_section'
        );

        add_settings_field(
            'lwp_separator',
            __('Title-Location Separator', 'location-wise-products-for-woocommerce'),
            [$this, 'separator_field_callback'],
            'location-wise-products-for-woocommerce',
            'lwp_display_settings_section'
        );

        add_settings_field(
            'lwp_enabled_pages',
            __('Show Location On', 'location-wise-products-for-woocommerce'),
            [$this, 'enabled_pages_field_callback'],
            'location-wise-products-for-woocommerce',
            'lwp_display_settings_section'
        );

        add_settings_section(
            'lwp_filter_settings_section',
            __('Location Filtering Settings', 'location-wise-products-for-woocommerce'),
            [$this, 'filter_settings_section_callback'],
            'location-wise-products-for-woocommerce'
        );

        add_settings_field(
            'lwp_strict_filtering',
            __('Strict Location Filtering', 'location-wise-products-for-woocommerce'),
            [$this, 'strict_filtering_field_callback'],
            'location-wise-products-for-woocommerce',
            'lwp_filter_settings_section'
        );

        add_settings_field(
            'lwp_filtered_sections',
            __('Apply Location Filtering To', 'location-wise-products-for-woocommerce'),
            [$this, 'filtered_sections_field_callback'],
            'location-wise-products-for-woocommerce',
            'lwp_filter_settings_section'
        );

        // Add settings section
        add_settings_section(
            'location_stock_general_section',
            __('General Settings', 'location-wise-products-for-woocommerce'),
            function () {
                echo '<p>' . esc_html_e('Configure general settings for location-based stock and price management.', 'location-wise-products-for-woocommerce') . '</p>';
            },
            'location-stock-settings'
        );

        // Add "Enable Location Stock" field
        add_settings_field(
            'enable_location_stock',
            __('Enable Location Stock', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['enable_location_stock' => 'yes']);
                $value = isset($options['enable_location_stock']) ? $options['enable_location_stock'] : 'yes';
        ?>
            <select name="lwp_display_options[enable_location_stock]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Enable or disable location-specific stock management.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'location_stock_general_section'
        );

        // Add "Enable Location Pricing" field
        add_settings_field(
            'enable_location_price',
            __('Enable Location Pricing', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['enable_location_price' => 'yes']);
                $value = isset($options['enable_location_price']) ? $options['enable_location_price'] : 'yes';
        ?>
            <select name="lwp_display_options[enable_location_price]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Enable or disable location-specific pricing.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'location_stock_general_section'
        );

        // Add "Enable Location Backorder" field
        add_settings_field(
            'enable_location_backorder',
            __('Enable Location Backorder', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['enable_location_backorder' => 'yes']);
                $value = isset($options['enable_location_backorder']) ? $options['enable_location_backorder'] : 'yes';
        ?>
            <select name="lwp_display_options[enable_location_backorder]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Enable or disable location-specific backorder management.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'location_stock_general_section'
        );

        // add "Enable Information for location"
        add_settings_field(
            'enable_location_information',
            __('Enable Location Information', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['enable_location_information' => 'no']);
                $value = isset($options['enable_location_information']) ? $options['enable_location_information'] : 'no';
        ?>
            <select id="enable_location_information" name="lwp_display_options[enable_location_information]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Enable or disable location-specific information management.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'location_stock_general_section'
        );

        add_settings_field(
            'enable_location_by_user_role',
            __('Enable Location by User Role', 'location-wise-products-for-woocommerce'),
            function () {
                $roles = wp_roles()->roles;
                $options = get_option('lwp_display_options', ['enable_location_by_user_role' => []]);
                $selected_roles = isset($options['enable_location_by_user_role']) ? $options['enable_location_by_user_role'] : [];
                foreach ($roles as $role_key => $role) {
                    $checked = in_array($role_key, $selected_roles) ? 'checked' : '';
                    echo "<label><input type='checkbox' name='lwp_display_options[enable_location_by_user_role][]' value='" . esc_attr($role_key) . "' " . esc_attr($checked) . "> " . esc_html($role['name']) . "</label><br>";
                }
        ?>
            <p class="description"><?php esc_html_e('Select user roles for which location-specific information is enabled.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'location_stock_general_section'
        );

        // Add settings for if no location is selected for a product available in all locations or not
        add_settings_field(
            'enable_all_locations',
            __('No Location Product Enable for All', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['enable_all_locations' => 'yes']);
                $value = isset($options['enable_all_locations']) ? $options['enable_all_locations'] : 'yes';
        ?>
            <select name="lwp_display_options[enable_all_locations]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('if no location is selected for a product available in all locations or not', 'location-wise-products-for-woocommerce'); ?></p>

        <?php
            },
            'location-stock-settings',
            'location_stock_general_section'
        );

        add_settings_section(
            'popup_shortcode_manage_section',
            __('Popup Settings', 'location-wise-products-for-woocommerce'),
            function () {
                echo '<p>' . esc_html_e('Configure Popup settings for location-based stock and price management.', 'location-wise-products-for-woocommerce') . '</p>';
            },
            'location-popup-shortcode-settings'
        );

        add_settings_field(
            'enable_popup',
            __('Enable Popup', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['enable_popup' => 'yes']);
                $value = isset($options['enable_popup']) ? $options['enable_popup'] : 'yes';
        ?>
            <select id="enable_popup" name="lwp_display_options[enable_popup]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Enable or disable popup management.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-popup-shortcode-settings',
            'popup_shortcode_manage_section'
        );

        add_settings_field(
            'use_select2',
            __('Use Select2', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['use_select2' => 'no']);
                $value = isset($options['use_select2']) ? $options['use_select2'] : 'no';
        ?>
            <select name="lwp_display_options[use_select2]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Use select2 instead of normal select', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-popup-shortcode-settings',
            'popup_shortcode_manage_section'
        );
        add_settings_field(
            'title_show_popup',
            __('Title Show in Popup', 'location-wise-products-for-woocommerce'),
            function () {
                $options = $this->get_display_options();
                $value = isset($options['title_show_popup']) ? $options['title_show_popup'] : 'yes';
        ?>
            <select name="lwp_display_options[title_show_popup]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Show title in popup modal', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-popup-shortcode-settings',
            'popup_shortcode_manage_section'
        );
        add_settings_field(
            'lwp_popup_title',
            __('Popup Title', 'location-wise-products-for-woocommerce'),
            function () {
                $options = $this->get_display_options();
                $lwp_popup_title = isset($options['lwp_popup_title']) ? $options['lwp_popup_title'] : 'Select Your Store';
        ?>
            <input type="text" name="lwp_display_options[lwp_popup_title]" value="<?php echo esc_attr($lwp_popup_title); ?>" class="regular-text">
        <?php
            },
            'location-popup-shortcode-settings',
            'popup_shortcode_manage_section'
        );
        add_settings_field(
            'lwp_popup_placeholder',
            __('Popup Placeholder', 'location-wise-products-for-woocommerce'),
            function () {
                $options = $this->get_display_options();
                $lwp_popup_placeholder = isset($options['lwp_popup_placeholder']) ? $options['lwp_popup_placeholder'] : ' -- Select a Store -- ';
        ?>
            <input type="text" name="lwp_display_options[lwp_popup_placeholder]" value="<?php echo esc_attr($lwp_popup_placeholder); ?>" class="regular-text">
        <?php
            },
            'location-popup-shortcode-settings',
            'popup_shortcode_manage_section'
        );
        add_settings_field(
            'lwp_popup_btn_txt',
            __('Popup Button Text', 'location-wise-products-for-woocommerce'),
            function () {
                $options = $this->get_display_options();
                $lwp_popup_btn_txt = isset($options['lwp_popup_btn_txt']) ? $options['lwp_popup_btn_txt'] : ' ';
        ?>
            <input type="text" name="lwp_display_options[lwp_popup_btn_txt]" value="<?php echo esc_attr($lwp_popup_btn_txt); ?>" class="regular-text">
        <?php
            },
            'location-popup-shortcode-settings',
            'popup_shortcode_manage_section'
        );
        add_settings_field(
            'herichical',
            __('Herichical Option', 'location-wise-products-for-woocommerce'),
            function () {
                $options = $this->get_display_options();
                $value = isset($options['herichical']) ? $options['herichical'] : 'no';
        ?>
            <select id="herichical" name="lwp_display_options[herichical]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="seperately" <?php selected($value, 'seperately'); ?>><?php esc_html_e('Seperately', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
        <?php
            },
            'location-popup-shortcode-settings',
            'popup_shortcode_manage_section'
        );
        add_settings_field(
            'show_count',
            __('Show Count', 'location-wise-products-for-woocommerce'),
            function () {
                $options = $this->get_display_options();
                $value = isset($options['show_count']) ? $options['show_count'] : 'yes';
        ?>
            <select name="lwp_display_options[show_count]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
        <?php
            },
            'location-popup-shortcode-settings',
            'popup_shortcode_manage_section'
        );
        add_settings_field(
            'lwp_popup_custom_css',
            __('Popup Custom Css', 'location-wise-products-for-woocommerce'),
            function () {
                $options = $this->get_display_options();
                $lwp_popup_custom_css = isset($options['lwp_popup_custom_css']) ? $options['lwp_popup_custom_css'] : null;
        ?>
            <textarea style="height: 10rem;"  name="lwp_display_options[lwp_popup_custom_css]" class="regular-text" placeholder="div#lwp-store-selector-modal{}">
            <?php echo esc_attr($lwp_popup_custom_css??null); ?>
            </textarea>
        <?php
            },
            'location-popup-shortcode-settings',
            'popup_shortcode_manage_section'
        );
    }

    public function sanitize_settings($input)
    {
        $sanitized = [];

        // Handle display options
        if (isset($input['display_format'])) {
            $sanitized['display_format'] = sanitize_text_field($input['display_format']);
        }

        if (isset($input['separator'])) {
            $sanitized['separator'] = sanitize_text_field($input['separator']);
        }

        // Handle enabled_pages
        $sanitized['enabled_pages'] = [];
        if (isset($input['enabled_pages']) && is_array($input['enabled_pages'])) {
            foreach ($input['enabled_pages'] as $page) {
                $sanitized['enabled_pages'][] = sanitize_text_field($page);
            }
        }

        // Handle strict_filtering option
        if (isset($input['strict_filtering'])) {
            $sanitized['strict_filtering'] = sanitize_text_field($input['strict_filtering']);
        }

        // Handle filtered_sections
        $sanitized['filtered_sections'] = [];
        if (isset($input['filtered_sections']) && is_array($input['filtered_sections'])) {
            foreach ($input['filtered_sections'] as $section) {
                $sanitized['filtered_sections'][] = sanitize_text_field($section);
            }
        }
        // Handle enable_location_stock option
        if (isset($input['enable_location_stock'])) {
            $sanitized['enable_location_stock'] = sanitize_text_field($input['enable_location_stock']);
        }

        // Handle enable_location_price option
        if (isset($input['enable_location_price'])) {
            $sanitized['enable_location_price'] = sanitize_text_field($input['enable_location_price']);
        }

        // Handle enable_location_backorder option
        if (isset($input['enable_location_backorder'])) {
            $sanitized['enable_location_backorder'] = sanitize_text_field($input['enable_location_backorder']);
        }
        // Handle enable_location_information option
        if (isset($input['enable_location_information'])) {
            $sanitized['enable_location_information'] = sanitize_text_field($input['enable_location_information']);
        }


        return $sanitized;
    }

    public function settings_section_callback()
    {
        echo '<p>' . esc_html_e('Configure how store locations appear with product titles.', 'location-wise-products-for-woocommerce') . '</p>';
    }

    public function display_format_field_callback()
    {
        $options = $this->get_display_options();
        $format = isset($options['display_format']) ? $options['display_format'] : 'none';
        ?>
        <select id="lwp_display_title" name="lwp_display_options[display_format]">
            <option value="append" <?php selected($format, 'append'); ?>><?php esc_html_e('Append to title (Title - Location)', 'location-wise-products-for-woocommerce'); ?></option>
            <option value="prepend" <?php selected($format, 'prepend'); ?>><?php esc_html_e('Prepend to title (Location - Title)', 'location-wise-products-for-woocommerce'); ?></option>
            <option value="brackets" <?php selected($format, 'brackets'); ?>><?php esc_html_e('In brackets (Title [Location])', 'location-wise-products-for-woocommerce'); ?></option>
            <option value="none" <?php selected($format, 'none'); ?>><?php esc_html_e('Do not display location', 'location-wise-products-for-woocommerce'); ?></option>
        </select>
    <?php
    }

    public function separator_field_callback()
    {
        $options = $this->get_display_options();
        $separator = isset($options['separator']) ? $options['separator'] : ' - ';
    ?>
        <input type="text" name="lwp_display_options[separator]" value="<?php echo esc_attr($separator); ?>" class="regular-text">
        <p class="description"><?php esc_html_e('The character(s) used to separate the title and location.', 'location-wise-products-for-woocommerce'); ?></p>
    <?php
    }

    public function enabled_pages_field_callback()
    {
        $options = $this->get_display_options();
        $enabled_pages = isset($options['enabled_pages']) ? $options['enabled_pages'] : ['shop', 'single', 'cart'];
        $pages = [
            'shop' => __('Shop/Archive Pages', 'location-wise-products-for-woocommerce'),
            'single' => __('Single Product Pages', 'location-wise-products-for-woocommerce'),
            'cart' => __('Cart & Checkout', 'location-wise-products-for-woocommerce'),
            'related' => __('Related Products', 'location-wise-products-for-woocommerce'),
            'search' => __('Search Results', 'location-wise-products-for-woocommerce'),
            'widgets' => __('Widgets', 'location-wise-products-for-woocommerce'),
            'Shortcode' => __('Shortcode used pages', 'location-wise-products-for-woocommerce')
        ];

        foreach ($pages as $value => $label) {
            $checked = in_array($value, $enabled_pages) ? 'checked' : '';
            echo "<label><input type='checkbox' name='lwp_display_options[enabled_pages][]' value='" . esc_attr($value) . "' " . esc_attr($checked) . "> " . esc_html($label) . "</label><br>";
        }
    }

    public function settings_page_content()
    {
    ?>
        <div class="wrap lwp-settings-container">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="lwp-admin-notice">
                <p><?php esc_html_e('Use this shortcode to show location selector on any page', 'location-wise-products-for-woocommerce'); ?> <code>[store_location_selector title ="Select Your Store" show_title = "yes" use_select2 = 'yes/no' herichical = 'yes/no/seperately' show_count = 'yes/no' class = ""]</code></p>
            </div>

            <div class="nav-tab-wrapper lwp-nav-tabs">
                <a href="#lwp-display-settings" class="nav-tab nav-tab-active"><?php esc_html_e('Display Settings', 'location-wise-products-for-woocommerce'); ?></a>
                <a href="#lwp-stock-settings" class="nav-tab"><?php esc_html_e('General', 'location-wise-products-for-woocommerce'); ?></a>
                <a href="#popup-shortcode-settings" class="nav-tab"><?php esc_html_e('Popup Manage', 'location-wise-products-for-woocommerce'); ?></a>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('location_wise_products_settings'); ?>

                <div id="lwp-display-settings" class="lwp-tab-content">
                    <div class="lwp-settings-section">
                        <div class="lwp-settings-box">
                            <div class="lwp-filter-settings lwp-location-show-title">
                                <?php do_settings_sections('location-wise-products-for-woocommerce'); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="lwp-stock-settings" class="lwp-tab-content" style="display:none;">
                    <div class="lwp-settings-section">
                        <div class="lwp-settings-box">
                            <?php do_settings_sections('location-stock-settings'); ?>
                        </div>
                    </div>
                </div>
                <div id="popup-shortcode-settings" class="lwp-tab-content" style="display:none;">
                    <div class="lwp-settings-section">
                        <div class="lwp-settings-box">
                            <?php do_settings_sections('location-popup-shortcode-settings'); ?>
                        </div>
                    </div>
                </div>


                <?php submit_button(); ?>
            </form>

            <div class="lwp-footer">
                <p><?php esc_html_e('Thank you for using Location Wise Products for WooCommerce', 'location-wise-products-for-woocommerce'); ?></p>
            </div>
        </div>
    <?php
    }

    private function get_display_options()
    {
        $defaults = [
            'display_format' => 'none',
            'separator' => ' - ',
            'enabled_pages' => [],
            'strict_filtering' => "enabled",
            'filtered_sections' => ['shop','search','related','recently_viewed','cross_sells','upsells','widgets','blocks','rest_api'],
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

    public function filter_settings_section_callback()
    {
        echo '<p>' . esc_html_e('Configure how strictly products are filtered by location throughout your store.', 'location-wise-products-for-woocommerce') . '</p>';
    }

    public function strict_filtering_field_callback()
    {
        $options = $this->get_display_options();
        $strict = isset($options['strict_filtering']) ? $options['strict_filtering'] : 'enabled';
    ?>
        <select id="strict_filtering" name="lwp_display_options[strict_filtering]">
            <option value="enabled" <?php selected($strict, 'enabled'); ?>><?php esc_html_e('Enabled (Only show products from selected location)', 'location-wise-products-for-woocommerce'); ?></option>
            <option value="disabled" <?php selected($strict, 'disabled'); ?>><?php esc_html_e('Disabled (Show all products regardless of location)', 'location-wise-products-for-woocommerce'); ?></option>
        </select>
        <p class="description"><?php esc_html_e('When enabled, users will only see products from their selected location. When disabled, all products will be visible.', 'location-wise-products-for-woocommerce'); ?></p>
    <?php
    }

    public function filtered_sections_field_callback()
    {
        $options = $this->get_display_options();
        $sections = isset($options['filtered_sections']) ? $options['filtered_sections'] : [
            'shop','search','related','recently_viewed','cross_sells','upsells','widgets','blocks','rest_api'
        ];

        $all_sections = [
            'shop' => __('Main Shop & Category Pages', 'location-wise-products-for-woocommerce'),
            'search' => __('Search Results', 'location-wise-products-for-woocommerce'),
            'related' => __('Related Products', 'location-wise-products-for-woocommerce'),
            'recently_viewed' => __('Recently Viewed Products', 'location-wise-products-for-woocommerce'),
            'cross_sells' => __('Cross-Sells', 'location-wise-products-for-woocommerce'),
            'upsells' => __('Upsells', 'location-wise-products-for-woocommerce'),
            'widgets' => __('Product Widgets', 'location-wise-products-for-woocommerce'),
            'blocks' => __('Product Blocks (Gutenberg)', 'location-wise-products-for-woocommerce'),
            'rest_api' => __('REST API & AJAX Responses', 'location-wise-products-for-woocommerce'),
        ];

        foreach ($all_sections as $value => $label) {
            $checked = in_array($value, $sections) ? 'checked' : '';
            echo "<label><input type='checkbox' name='lwp_display_options[filtered_sections][]' value='" . esc_attr($value) . "' " . esc_attr($checked) . "> " . esc_html($label) . "</label><br>";
        }
    ?>
        <p class="description"><?php esc_html_e('Select which parts of your store should have location-based filtering applied.', 'location-wise-products-for-woocommerce'); ?></p>
<?php
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
        wp_enqueue_style('custom-admin-style', plugin_dir_url(__FILE__) . 'assets/css/admin-style.css', array(), "2.0.0");
    }

    /**
     * Calculate monthly investment data for the past 12 months
     * 
     * @return array Monthly investment data
     */
    private function get_monthly_investment_data()
    {
        $months = 12; // Past 12 months
        $monthly_data = [];
        $labels = [];

        // Generate data for each month
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = gmdate('Y-m', strtotime("-$i months"));
            $labels[] = gmdate('M Y', strtotime("-$i months"));

            $start_date = $date . '-01 00:00:00';
            $end_date = gmdate('Y-m-t 23:59:59', strtotime($start_date));

            // Calculate investment for this month
            $monthly_investment = $this->calculate_monthly_investment($start_date, $end_date);
            $monthly_data[] = $monthly_investment;
        }

        return [
            'labels' => $labels,
            'data' => $monthly_data
        ];
    }

    /**
     * Calculate investment for a specific month
     * 
     * @param string $start_date Start date in Y-m-d H:i:s format
     * @param string $end_date End date in Y-m-d H:i:s format
     * @return float Total investment for the month
     */
    private function calculate_monthly_investment($start_date, $end_date)
    {
        $monthly_investment = 0;

        // Get products with status changes during this month
        $args = array(
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'date_query'     => array(
                array(
                    'after'     => $start_date,
                    'before'    => $end_date,
                    'inclusive' => true,
                ),
            ),
        );

        $products = new WP_Query($args);

        if ($products->have_posts()) {
            while ($products->have_posts()) {
                $products->the_post();
                $product_id = get_the_ID();
                $product = wc_get_product($product_id);

                if (!$product) {
                    continue;
                }

                // Get orders containing this product in the date range
                $order_count = $this->get_product_order_count($product_id, $start_date, $end_date);

                if ($product->is_type('simple')) {
                    $purchase_price = get_post_meta($product_id, '_purchase_price', true);
                    $stock_quantity = $product->get_stock_quantity() ?: 0;

                    if (is_numeric($purchase_price)) {
                        // Investment = purchase_price * (current_stock + number_of_orders)
                        $monthly_investment += floatval($purchase_price) * ($stock_quantity + $order_count);
                    }
                } elseif ($product->is_type('variable')) {
                    $variations = $product->get_available_variations();

                    foreach ($variations as $variation) {
                        $variation_id = $variation['variation_id'];
                        $variation_obj = wc_get_product($variation_id);

                        if (!$variation_obj) {
                            continue;
                        }

                        $purchase_price = get_post_meta($variation_id, '_purchase_price', true);
                        $stock_quantity = $variation_obj->get_stock_quantity() ?: 0;
                        $variation_order_count = $this->get_product_order_count($variation_id, $start_date, $end_date);

                        if (is_numeric($purchase_price)) {
                            // Investment = purchase_price * (current_stock + number_of_orders)
                            $monthly_investment += floatval($purchase_price) * ($stock_quantity + $variation_order_count);
                        }
                    }
                }
            }
        }

        wp_reset_postdata();
        return $monthly_investment;
    }

    /**
     * Get order count for a specific product in a date range
     * 
     * @param int $product_id Product ID or variation ID
     * @param string $start_date Start date in Y-m-d H:i:s format
     * @param string $end_date End date in Y-m-d H:i:s format
     * @return int Number of orders containing this product
     */
    private function get_product_order_count($product_id, $start_date, $end_date)
    {
        $order_count = 0;

        $orders = wc_get_orders(array(
            'limit'        => -1,
            'type'         => 'shop_order',
            'status'       => array('wc-completed', 'wc-processing', 'wc-on-hold'),
            'date_created' => strtotime($start_date) . '...' . strtotime($end_date),
        ));

        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $item_product_id = $item->get_product_id();
                $item_variation_id = $item->get_variation_id();

                $check_id = $item_variation_id ? $item_variation_id : $item_product_id;

                if ($check_id == $product_id) {
                    $order_count += $item->get_quantity();
                }
            }
        }

        return $order_count;
    }
}

function Plugincylwp_location_wise_products_init()
{
    new Plugincylwp_Location_Wise_Products();
}

add_action('plugins_loaded', 'Plugincylwp_location_wise_products_init');



register_uninstall_hook(__FILE__, 'lwp_settings_remove');

register_activation_hook(__FILE__, 'lwp_settings_remove');

function lwp_settings_remove() {
    // Check if the option exists and delete it
    if (get_option('lwp_display_options') !== false) {
        delete_option('lwp_display_options');
    }
}



