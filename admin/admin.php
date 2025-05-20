<?php

if (!defined('ABSPATH')) exit;

class LWP_Admin
{
    public function __construct()
    {
        add_action('init', [$this, 'register_store_location_taxonomy']);
        // Hook to add the settings page
        add_action('admin_menu', [$this, 'add_settings_page']);
        // Add custom column to orders table
        add_filter('manage_woocommerce_page_wc-orders_columns', array($this, 'add_location_column'), 20);
        add_action('manage_woocommerce_page_wc-orders_custom_column', array($this, 'display_location_column_content'), 20, 2);
        // Add metabox to order details
        add_action('add_meta_boxes', array($this, 'add_location_metabox'));
    }

    public function add_settings_page()
    {
        // Add main menu page
        add_menu_page(
            __('Location Manage', 'location-wise-products-for-woocommerce'),
            __('Location Manage', 'location-wise-products-for-woocommerce'),
            'manage_options',
            'location-wise-products-for-woocommerce',
            [new LWP_Dashboard(), 'dashboard_page_content'],
            'dashicons-location-alt',
            56
        );

        // Add Dashboard submenu (just label, points to same page, no callback)
        add_submenu_page(
            'location-wise-products-for-woocommerce',
            __('Dashboard', 'location-wise-products-for-woocommerce'),
            __('Dashboard', 'location-wise-products-for-woocommerce'),
            'manage_options',
            'location-wise-products-for-woocommerce'
            // No callback here, so it won't render twice
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
            [new Plugincylwp_Stock_Central(), 'location_stock_page_content']
        );

        // Add Settings submenu
        add_submenu_page(
            'location-wise-products-for-woocommerce',
            __('Settings', 'location-wise-products-for-woocommerce'),
            __('Settings', 'location-wise-products-for-woocommerce'),
            'manage_options',
            'location-wise-products-for-woocommerce-settings',
            [new Location_Wise_Products_Settings(), 'settings_page_content']
        );
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
}