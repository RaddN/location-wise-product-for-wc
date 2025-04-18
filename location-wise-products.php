<?php

/**
 * Plugin Name: Location Wise Products for WooCommerce
 * Plugin URI: https://plugincy.com/location-wise-product
 * Description: Filter WooCommerce products by store locations with a location selector for customers.
 * Version: 1.0.0
 * Author: plugincy
 * Author URI: https://plugincy.com/
 * Text Domain: location-wise-product
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 4.0
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) exit;

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function () {
        echo '<div class="error"><p>' . esc_html_e('Location Wise Products requires WooCommerce to be installed and active.', 'location-wise-product') . '</p></div>';
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
                $new_columns['store_location'] = __('Store Location', 'location-wise-product');
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
            __('Store Location', 'location-wise-product'),
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
            echo '<p>' . esc_html__('No location data available', 'location-wise-product') . '</p>';
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
        }else{
            $selected_location = isset($_GET['store_location']) ? sanitize_text_field(wp_unslash($_GET['store_location'])) : '';
        }

        // add nonce for security
        wp_nonce_field('store_location_filter_nonce', 'store_location_filter_nonce');

        echo '<select name="store_location" id="store_location">';
        echo '<option value="">' . esc_html__('All Locations', 'location-wise-product') . '</option>';

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
        }else{
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
        $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=location-wise-product')) . '">' . esc_html__('Settings', 'location-wise-product') . '</a>';
        $pro_link = '<a href="https://plugincy.com/location-wise-product" style="color: #ff5722; font-weight: bold;" target="_blank">' . esc_html__('Get Pro', 'location-wise-product') . '</a>';
        $links[] = $settings_link;
        $links[] = $pro_link;
        return $links;
    }

    public function register_store_location_taxonomy()
    {
        register_taxonomy('store_location', 'product', [
            'labels' => [
                'name' => __('locations', 'location-wise-product'),
                'singular_name' => __('Store Location', 'location-wise-product'),
                'search_items' => __('Search Store Locations', 'location-wise-product'),
                'all_items' => __('All Store Locations', 'location-wise-product'),
                'edit_item' => __('Edit Store Location', 'location-wise-product'),
                'update_item' => __('Update Store Location', 'location-wise-product'),
                'add_new_item' => __('Add New Store Location', 'location-wise-product'),
                'new_item_name' => __('New Store Location Name', 'location-wise-product'),
                'menu_name' => __('Store Locations', 'location-wise-product'),
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
        wp_enqueue_style('location-wise-product', plugins_url('assets/css/style.css', __FILE__), [], '1.0.0');
        wp_enqueue_script('location-wise-product', plugins_url('assets/js/script.js', __FILE__), ['jquery'], '1.0.0', true);

        wp_localize_script('location-wise-product', 'locationWiseProducts', [
            'cartHasProducts' => !WC()->cart->is_empty(),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('location-wise-product')
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
            'title' => __('Select Store Location', 'location-wise-product'),
            'show_title' => 'yes',
            'class' => '',
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
        add_submenu_page(
            'woocommerce',
            __('Location Wise Products Settings', 'location-wise-product'),
            __('Store Locations Settings', 'location-wise-product'),
            'manage_options',
            'location-wise-product',
            [$this, 'settings_page_content']
        );
    }

    public function register_settings()
    {
        register_setting('location_wise_products_settings', 'lwp_display_options', 'sanitize_settings');

        add_settings_section(
            'lwp_display_settings_section',
            __('Product Title Display Settings', 'location-wise-product'),
            [$this, 'settings_section_callback'],
            'location-wise-product'
        );

        add_settings_field(
            'lwp_display_format',
            __('Location Display Format', 'location-wise-product'),
            [$this, 'display_format_field_callback'],
            'location-wise-product',
            'lwp_display_settings_section'
        );

        add_settings_field(
            'lwp_separator',
            __('Title-Location Separator', 'location-wise-product'),
            [$this, 'separator_field_callback'],
            'location-wise-product',
            'lwp_display_settings_section'
        );

        add_settings_field(
            'lwp_enabled_pages',
            __('Show Location On', 'location-wise-product'),
            [$this, 'enabled_pages_field_callback'],
            'location-wise-product',
            'lwp_display_settings_section'
        );

        add_settings_section(
            'lwp_filter_settings_section',
            __('Location Filtering Settings', 'location-wise-product'),
            [$this, 'filter_settings_section_callback'],
            'location-wise-product'
        );

        add_settings_field(
            'lwp_strict_filtering',
            __('Strict Location Filtering', 'location-wise-product'),
            [$this, 'strict_filtering_field_callback'],
            'location-wise-product',
            'lwp_filter_settings_section'
        );

        add_settings_field(
            'lwp_filtered_sections',
            __('Apply Location Filtering To', 'location-wise-product'),
            [$this, 'filtered_sections_field_callback'],
            'location-wise-product',
            'lwp_filter_settings_section'
        );

        // register_setting(
        //     'location_stock_settings',
        //     'lwp_display_options',
        //     'sanitize_location_stock_options' // Add sanitization callback
        // );

        // Add settings section
        add_settings_section(
            'location_stock_general_section',
            __('General Settings', 'location-wise-product'),
            function () {
                echo '<p>' . esc_html_e('Configure general settings for location-based stock and price management.', 'location-wise-product') . '</p>';
            },
            'location-stock-settings'
        );

        // Add "Enable Location Stock" field
        add_settings_field(
            'enable_location_stock',
            __('Enable Location Stock', 'location-wise-product'),
            function () {
                $options = get_option('lwp_display_options', ['enable_location_stock' => 'yes']);
                $value = isset($options['enable_location_stock']) ? $options['enable_location_stock'] : 'yes';
?>
            <select name="lwp_display_options[enable_location_stock]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-product'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-product'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Enable or disable location-specific stock management.', 'location-wise-product'); ?></p>
        <?php
            },
            'location-stock-settings',
            'location_stock_general_section'
        );

        // Add "Enable Location Pricing" field
        add_settings_field(
            'enable_location_price',
            __('Enable Location Pricing', 'location-wise-product'),
            function () {
                $options = get_option('lwp_display_options', ['enable_location_price' => 'yes']);
                $value = isset($options['enable_location_price']) ? $options['enable_location_price'] : 'yes';
        ?>
            <select name="lwp_display_options[enable_location_price]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-product'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-product'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Enable or disable location-specific pricing.', 'location-wise-product'); ?></p>
        <?php
            },
            'location-stock-settings',
            'location_stock_general_section'
        );

        // Add "Enable Location Backorder" field
        add_settings_field(
            'enable_location_backorder',
            __('Enable Location Backorder', 'location-wise-product'),
            function () {
                $options = get_option('lwp_display_options', ['enable_location_backorder' => 'yes']);
                $value = isset($options['enable_location_backorder']) ? $options['enable_location_backorder'] : 'yes';
        ?>
            <select name="lwp_display_options[enable_location_backorder]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-product'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-product'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Enable or disable location-specific backorder management.', 'location-wise-product'); ?></p>
        <?php
            },
            'location-stock-settings',
            'location_stock_general_section'
        );

        // add "Enable Information for location"
        add_settings_field(
            'enable_location_information',
            __('Enable Location Information', 'location-wise-product'),
            function () {
                $options = get_option('lwp_display_options', ['enable_location_information' => 'yes']);
                $value = isset($options['enable_location_information']) ? $options['enable_location_information'] : 'yes';
        ?>
            <select name="lwp_display_options[enable_location_information]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-product'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-product'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Enable or disable location-specific information management.', 'location-wise-product'); ?></p>
        <?php
            },
            'location-stock-settings',
            'location_stock_general_section'
        );

        add_settings_field(
            'enable_location_by_user_role',
            __('Enable Location by User Role', 'location-wise-product'),
            function () {
                $roles = wp_roles()->roles;
                $options = get_option('lwp_display_options', ['enable_location_by_user_role' => []]);
                $selected_roles = isset($options['enable_location_by_user_role']) ? $options['enable_location_by_user_role'] : [];
                foreach ($roles as $role_key => $role) {
                    $checked = in_array($role_key, $selected_roles) ? 'checked' : '';
                    echo "<label><input type='checkbox' name='lwp_display_options[enable_location_by_user_role][]' value='" . esc_attr($role_key) . "' " . esc_attr($checked) . "> " . esc_html($role['name']) . "</label><br>";
                }
        ?>
            <p class="description"><?php esc_html_e('Select user roles for which location-specific information is enabled.', 'location-wise-product'); ?></p>
        <?php
            },
            'location-stock-settings',
            'location_stock_general_section'
        );
        add_settings_field(
            'enable_popup',
            __('Enable Popup', 'location-wise-product'),
            function () {
                $options = get_option('lwp_display_options', ['enable_popup' => 'yes']);
                $value = isset($options['enable_popup']) ? $options['enable_popup'] : 'yes';
        ?>
            <select name="lwp_display_options[enable_popup]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-product'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-product'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Enable or disable popup management.', 'location-wise-product'); ?></p>
        <?php
            },
            'location-stock-settings',
            'location_stock_general_section'
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
        echo '<p>' . esc_html_e('Configure how store locations appear with product titles.', 'location-wise-product') . '</p>';
    }

    public function display_format_field_callback()
    {
        $options = $this->get_display_options();
        $format = isset($options['display_format']) ? $options['display_format'] : 'append';
        ?>
        <select name="lwp_display_options[display_format]">
            <option value="append" <?php selected($format, 'append'); ?>><?php esc_html_e('Append to title (Title - Location)', 'location-wise-product'); ?></option>
            <option value="prepend" <?php selected($format, 'prepend'); ?>><?php esc_html_e('Prepend to title (Location - Title)', 'location-wise-product'); ?></option>
            <option value="brackets" <?php selected($format, 'brackets'); ?>><?php esc_html_e('In brackets (Title [Location])', 'location-wise-product'); ?></option>
        </select>
    <?php
    }

    public function separator_field_callback()
    {
        $options = $this->get_display_options();
        $separator = isset($options['separator']) ? $options['separator'] : ' - ';
    ?>
        <input type="text" name="lwp_display_options[separator]" value="<?php echo esc_attr($separator); ?>" class="regular-text">
        <p class="description"><?php esc_html_e('The character(s) used to separate the title and location.', 'location-wise-product'); ?></p>
    <?php
    }

    public function enabled_pages_field_callback()
    {
        $options = $this->get_display_options();
        $enabled_pages = isset($options['enabled_pages']) ? $options['enabled_pages'] : ['shop', 'single', 'cart'];
        $pages = [
            'shop' => __('Shop/Archive Pages', 'location-wise-product'),
            'single' => __('Single Product Pages', 'location-wise-product'),
            'cart' => __('Cart & Checkout', 'location-wise-product'),
            'related' => __('Related Products', 'location-wise-product'),
            'search' => __('Search Results', 'location-wise-product'),
            'widgets' => __('Widgets', 'location-wise-product')
        ];

        foreach ($pages as $value => $label) {
            $checked = in_array($value, $enabled_pages) ? 'checked' : '';
            echo "<label><input type='checkbox' name='lwp_display_options[enabled_pages][]' value='" . esc_attr($value) . "' " . esc_attr($checked) . "> " . esc_html($label) . "</label><br>";
        }
    }

    public function settings_page_content()
    {
    ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('location_wise_products_settings');
                do_settings_sections('location-wise-product');
                do_settings_sections('location-stock-settings');
                submit_button();
                ?>
            </form>
        </div>
        <p><?php esc_html_e('Use this shortcode to show location selector on any page', 'location-wise-product'); ?> <strong>[store_location_selector]</strong></p>
    <?php
    }

    private function get_display_options()
    {
        $defaults = [
            'display_format' => 'append',
            'separator' => ' - ',
            'enabled_pages' => ['shop', 'single', 'cart']
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
            case 'append':
            default:
                return $title . $separator . $location_text;
        }
    }

    private function product_belongs_to_location($product_id)
    {
        $location = $this->get_current_location();

        if (!$location || $location === 'all-products') {
            return true;
        }

        $terms = wp_get_object_terms($product_id, 'store_location', ['fields' => 'slugs']);
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
        echo '<p>' . esc_html_e('Configure how strictly products are filtered by location throughout your store.', 'location-wise-product') . '</p>';
    }

    public function strict_filtering_field_callback()
    {
        $options = $this->get_filter_options();
        $strict = isset($options['strict_filtering']) ? $options['strict_filtering'] : 'enabled';
    ?>
        <select name="lwp_display_options[strict_filtering]">
            <option value="enabled" <?php selected($strict, 'enabled'); ?>><?php esc_html_e('Enabled (Only show products from selected location)', 'location-wise-product'); ?></option>
            <option value="disabled" <?php selected($strict, 'disabled'); ?>><?php esc_html_e('Disabled (Show all products regardless of location)', 'location-wise-product'); ?></option>
        </select>
        <p class="description"><?php esc_html_e('When enabled, users will only see products from their selected location. When disabled, all products will be visible.', 'location-wise-product'); ?></p>
    <?php
    }

    public function filtered_sections_field_callback()
    {
        $options = $this->get_filter_options();
        $sections = isset($options['filtered_sections']) ? $options['filtered_sections'] : [
            'shop',
            'search',
            'related',
            'recently_viewed',
            'cross_sells',
            'upsells'
        ];

        $all_sections = [
            'shop' => __('Main Shop & Category Pages', 'location-wise-product'),
            'search' => __('Search Results', 'location-wise-product'),
            'related' => __('Related Products', 'location-wise-product'),
            'recently_viewed' => __('Recently Viewed Products', 'location-wise-product'),
            'cross_sells' => __('Cross-Sells', 'location-wise-product'),
            'upsells' => __('Upsells', 'location-wise-product'),
            'widgets' => __('Product Widgets', 'location-wise-product'),
            'blocks' => __('Product Blocks (Gutenberg)', 'location-wise-product'),
            'rest_api' => __('REST API & AJAX Responses', 'location-wise-product'),
        ];

        foreach ($all_sections as $value => $label) {
            $checked = in_array($value, $sections) ? 'checked' : '';
            echo "<label><input type='checkbox' name='lwp_display_options[filtered_sections][]' value='" . esc_attr($value) . "' " . esc_attr($checked) . "> " . esc_html($label) . "</label><br>";
        }
    ?>
        <p class="description"><?php esc_html_e('Select which parts of your store should have location-based filtering applied.', 'location-wise-product'); ?></p>
<?php
    }

    private function get_filter_options()
    {
        $defaults = [
            'strict_filtering' => 'enabled',
            'filtered_sections' => ['shop', 'search', 'related', 'recently_viewed', 'cross_sells', 'upsells']
        ];
        $options = get_option('lwp_display_options', []);
        return wp_parse_args($options, $defaults);
    }

    private function should_apply_filtering($section)
    {
        $options = $this->get_filter_options();
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
        wp_enqueue_style('custom-admin-style', plugin_dir_url(__FILE__) . 'assets/css/admin-style.css', array(), "1.0.0");
    }
}

function Plugincylwp_location_wise_products_init()
{
    new Plugincylwp_Location_Wise_Products();
}

add_action('plugins_loaded', 'Plugincylwp_location_wise_products_init');
