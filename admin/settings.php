<?php

if (!defined('ABSPATH')) exit;

class Location_Wise_Products_Settings
{
    public function __construct()
    {
        add_action('admin_init', [$this, 'register_settings']);
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
            <textarea style="height: 10rem;" name="lwp_display_options[lwp_popup_custom_css]" class="regular-text" placeholder="div#lwp-store-selector-modal{}">
            <?php echo esc_attr($lwp_popup_custom_css ?? null); ?>
            </textarea>
        <?php
            },
            'location-popup-shortcode-settings',
            'popup_shortcode_manage_section'
        );
        // Add new Inventory Management section
        add_settings_section(
            'lwp_inventory_management_section',
            __('Inventory Management (Coming Soon)', 'location-wise-products-for-woocommerce'),
            function () {
                echo '<p>' . esc_html__('Configure how inventory is managed across multiple locations.', 'location-wise-products-for-woocommerce') . '</p>';
            },
            'location-stock-settings'
        );

        // Add "Inventory Sync Mode" field
        add_settings_field(
            'inventory_sync_mode',
            __('Inventory Sync Mode', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['inventory_sync_mode' => 'independent']);
                $value = isset($options['inventory_sync_mode']) ? $options['inventory_sync_mode'] : 'independent';
        ?>
            <select name="lwp_display_options[inventory_sync_mode]">
                <option value="independent" <?php selected($value, 'independent'); ?>><?php esc_html_e('Independent (Each location manages its own inventory)', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="centralized" <?php selected($value, 'centralized'); ?>><?php esc_html_e('Centralized (Main inventory with location allocations)', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="synchronized" <?php selected($value, 'synchronized'); ?>><?php esc_html_e('Synchronized (Changes in one location affect all)', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Choose how inventory is managed across multiple locations.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_inventory_management_section'
        );

        // Add "Low Stock Threshold Method" field
        add_settings_field(
            'low_stock_threshold_method',
            __('Low Stock Threshold Method', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['low_stock_threshold_method' => 'per_location']);
                $value = isset($options['low_stock_threshold_method']) ? $options['low_stock_threshold_method'] : 'per_location';
        ?>
            <select name="lwp_display_options[low_stock_threshold_method]">
                <option value="per_location" <?php selected($value, 'per_location'); ?>><?php esc_html_e('Per Location (Each location has its own threshold)', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="global" <?php selected($value, 'global'); ?>><?php esc_html_e('Global (Use WooCommerce default threshold for all locations)', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Choose how low stock thresholds are determined.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_inventory_management_section'
        );

        // Add "Low Stock Notification Recipients" field
        add_settings_field(
            'low_stock_notification_recipients',
            __('Low Stock Notification Recipients', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['low_stock_notification_recipients' => 'admin']);
                $value = isset($options['low_stock_notification_recipients']) ? $options['low_stock_notification_recipients'] : 'admin';
        ?>
            <select name="lwp_display_options[low_stock_notification_recipients]">
                <option value="admin" <?php selected($value, 'admin'); ?>><?php esc_html_e('Admin Only', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="location_manager" <?php selected($value, 'location_manager'); ?>><?php esc_html_e('Location Manager', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="both" <?php selected($value, 'both'); ?>><?php esc_html_e('Both Admin and Location Manager', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Who should receive low stock notifications.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_inventory_management_section'
        );
        // Add Order Fulfillment section
        add_settings_section(
            'lwp_order_fulfillment_section',
            __('Order Fulfillment', 'location-wise-products-for-woocommerce'),
            function () {
                echo '<p>' . esc_html__('Configure how orders are processed and fulfilled from different locations.', 'location-wise-products-for-woocommerce') . '</p>';
            },
            'location-stock-settings'
        );

        // Add "Order Assignment Method" field
        add_settings_field(
            'order_assignment_method',
            __('Order Assignment Method', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['order_assignment_method' => 'customer_selection']);
                $value = isset($options['order_assignment_method']) ? $options['order_assignment_method'] : 'customer_selection';
        ?>
            <select name="lwp_display_options[order_assignment_method]">
                <option value="customer_selection" <?php selected($value, 'customer_selection'); ?>><?php esc_html_e('Customer Selection (Based on selected location)', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="inventory_based" <?php selected($value, 'inventory_based'); ?>><?php esc_html_e('Inventory Based (Location with highest stock)', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="proximity_based" <?php selected($value, 'proximity_based'); ?>><?php esc_html_e('Proximity Based (Nearest location to shipping address)', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="manual" <?php selected($value, 'manual'); ?>><?php esc_html_e('Manual Assignment (Admin assigns after order)', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('How orders are assigned to locations for fulfillment.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_order_fulfillment_section'
        );

        // Add "Split Orders by Location" field
        add_settings_field(
            'split_orders_by_location',
            __('Split Orders by Location', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['split_orders_by_location' => 'no']);
                $value = isset($options['split_orders_by_location']) ? $options['split_orders_by_location'] : 'no';
        ?>
            <select name="lwp_display_options[split_orders_by_location]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('When enabled, orders containing products from multiple locations will be split into separate orders.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_order_fulfillment_section'
        );

        // Add "Order Notification Recipients" field
        add_settings_field(
            'order_notification_recipients',
            __('Order Notification Recipients', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['order_notification_recipients' => 'admin']);
                $value = isset($options['order_notification_recipients']) ? $options['order_notification_recipients'] : 'admin';
        ?>
            <select name="lwp_display_options[order_notification_recipients]">
                <option value="admin" <?php selected($value, 'admin'); ?>><?php esc_html_e('Admin Only', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="location_manager" <?php selected($value, 'location_manager'); ?>><?php esc_html_e('Location Manager', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="both" <?php selected($value, 'both'); ?>><?php esc_html_e('Both Admin and Location Manager', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Who should receive order notifications.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_order_fulfillment_section'
        );

        // Add Location Display section
        add_settings_section(
            'lwp_location_display_section',
            __('Location Selection Display (Coming Soon)', 'location-wise-products-for-woocommerce'),
            function () {
                echo '<p>' . esc_html__('Configure how the location selector appears to customers.', 'location-wise-products-for-woocommerce') . '</p>';
            },
            'location-popup-shortcode-settings'
        );

        // Add "Display Location on Single Product" field
        add_settings_field(
            'display_location_single_product',
            __('Display Location on Single Product', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['display_location_single_product' => 'yes']);
                $value = isset($options['display_location_single_product']) ? $options['display_location_single_product'] : 'yes';
        ?>
            <select name="lwp_display_options[display_location_single_product]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Show current location on single product pages.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-popup-shortcode-settings',
            'lwp_location_display_section'
        );

        // Add "Location Display Position" field
        add_settings_field(
            'location_display_position',
            __('Location Display Position', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['location_display_position' => 'after_price']);
                $value = isset($options['location_display_position']) ? $options['location_display_position'] : 'after_price';
        ?>
            <select name="lwp_display_options[location_display_position]">
                <option value="after_title" <?php selected($value, 'after_title'); ?>><?php esc_html_e('After Product Title', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="after_price" <?php selected($value, 'after_price'); ?>><?php esc_html_e('After Product Price', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="before_add_to_cart" <?php selected($value, 'before_add_to_cart'); ?>><?php esc_html_e('Before Add to Cart Button', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="after_add_to_cart" <?php selected($value, 'after_add_to_cart'); ?>><?php esc_html_e('After Add to Cart Button', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="product_meta" <?php selected($value, 'product_meta'); ?>><?php esc_html_e('In Product Meta Section', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Where to display the current location on single product pages.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-popup-shortcode-settings',
            'lwp_location_display_section'
        );

        // Add "Show Stock by Location" field
        add_settings_field(
            'show_stock_by_location',
            __('Show Stock by Location', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['show_stock_by_location' => 'no']);
                $value = isset($options['show_stock_by_location']) ? $options['show_stock_by_location'] : 'no';
        ?>
            <select name="lwp_display_options[show_stock_by_location]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Show available stock for each location on single product pages.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-popup-shortcode-settings',
            'lwp_location_display_section'
        );

        // Add "Store Locator Integration" section
        add_settings_section(
            'lwp_store_locator_section',
            __('Store Locator', 'location-wise-products-for-woocommerce'),
            function () {
                echo '<p>' . esc_html__('Configure store locator functionality and integration.', 'location-wise-products-for-woocommerce') . '</p>';
            },
            'location-popup-shortcode-settings'
        );

        // Add "Enable Store Locator" field
        add_settings_field(
            'enable_store_locator',
            __('Enable Store Locator', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['enable_store_locator' => 'no']);
                $value = isset($options['enable_store_locator']) ? $options['enable_store_locator'] : 'no';
        ?>
            <select name="lwp_display_options[enable_store_locator]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Enable store locator with map functionality.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-popup-shortcode-settings',
            'lwp_store_locator_section'
        );

        // Add "Map Provider" field
        add_settings_field(
            'map_provider',
            __('Map Provider', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['map_provider' => 'google_maps']);
                $value = isset($options['map_provider']) ? $options['map_provider'] : 'google_maps';
        ?>
            <select name="lwp_display_options[map_provider]">
                <option value="google_maps" <?php selected($value, 'google_maps'); ?>><?php esc_html_e('Google Maps', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="openstreetmap" <?php selected($value, 'openstreetmap'); ?>><?php esc_html_e('OpenStreetMap', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="mapbox" <?php selected($value, 'mapbox'); ?>><?php esc_html_e('Mapbox', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Select which map provider to use for the store locator.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-popup-shortcode-settings',
            'lwp_store_locator_section'
        );

        // Add "Map API Key" field
        add_settings_field(
            'map_api_key',
            __('Map API Key', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['map_api_key' => '']);
                $value = isset($options['map_api_key']) ? $options['map_api_key'] : '';
        ?>
            <input type="text" name="lwp_display_options[map_api_key]" value="<?php echo esc_attr($value); ?>" class="regular-text">
            <p class="description"><?php esc_html_e('Enter your API key for the selected map provider.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-popup-shortcode-settings',
            'lwp_store_locator_section'
        );

        // Add "Default Map Zoom Level" field
        add_settings_field(
            'default_map_zoom',
            __('Default Map Zoom Level', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['default_map_zoom' => '10']);
                $value = isset($options['default_map_zoom']) ? $options['default_map_zoom'] : '10';
        ?>
            <input type="number" name="lwp_display_options[default_map_zoom]" value="<?php echo esc_attr($value); ?>" min="1" max="20" class="small-text">
            <p class="description"><?php esc_html_e('Default zoom level for the store locator map (1-20).', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-popup-shortcode-settings',
            'lwp_store_locator_section'
        );

        // Add "Product Shipping" section
        add_settings_section(
            'lwp_shipping_section',
            __('Location-Based Shipping', 'location-wise-products-for-woocommerce'),
            function () {
                echo '<p>' . esc_html__('Configure shipping options based on product locations.', 'location-wise-products-for-woocommerce') . '</p>';
            },
            'location-stock-settings'
        );

        // Add "Enable Location-Based Shipping" field
        add_settings_field(
            'enable_location_shipping',
            __('Enable Location-Based Shipping', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['enable_location_shipping' => 'no']);
                $value = isset($options['enable_location_shipping']) ? $options['enable_location_shipping'] : 'no';
        ?>
            <select name="lwp_display_options[enable_location_shipping]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Enable different shipping options based on product location.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_shipping_section'
        );

        // Add "Shipping Calculation Method" field
        add_settings_field(
            'shipping_calculation_method',
            __('Shipping Calculation Method', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['shipping_calculation_method' => 'per_location']);
                $value = isset($options['shipping_calculation_method']) ? $options['shipping_calculation_method'] : 'per_location';
        ?>
            <select name="lwp_display_options[shipping_calculation_method]">
                <option value="per_location" <?php selected($value, 'per_location'); ?>><?php esc_html_e('Per Location (Each location has its own rates)', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="nearest_location" <?php selected($value, 'nearest_location'); ?>><?php esc_html_e('Nearest Location (Calculate from closest store)', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="combined" <?php selected($value, 'combined'); ?>><?php esc_html_e('Combined (Calculate all shipping rates separately)', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('How shipping rates are calculated for multi-location orders.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_shipping_section'
        );

        // Add "Local Pickup Priority" field
        add_settings_field(
            'local_pickup_priority',
            __('Local Pickup Priority', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['local_pickup_priority' => 'normal']);
                $value = isset($options['local_pickup_priority']) ? $options['local_pickup_priority'] : 'normal';
        ?>
            <select name="lwp_display_options[local_pickup_priority]">
                <option value="normal" <?php selected($value, 'normal'); ?>><?php esc_html_e('Normal (Show with other shipping options)', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="highlighted" <?php selected($value, 'highlighted'); ?>><?php esc_html_e('Highlighted (Emphasize local pickup option)', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="preferred" <?php selected($value, 'preferred'); ?>><?php esc_html_e('Preferred (Show at top of shipping options)', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('How to prioritize local pickup options at checkout.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_shipping_section'
        );
        // Add Advanced Settings section
        add_settings_section(
            'lwp_advanced_settings_section',
            __('Advanced Settings', 'location-wise-products-for-woocommerce'),
            function () {
                echo '<p>' . esc_html__('Advanced configuration options for location management.', 'location-wise-products-for-woocommerce') . '</p>';
            },
            'location-stock-settings'
        );

        add_settings_field(
            'location_cookie_expiry',
            __('Location Cookie Expiry (Days)', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['location_cookie_expiry' => '30']);
                $value = isset($options['location_cookie_expiry']) ? $options['location_cookie_expiry'] : '30';
        ?>
            <input type="number" name="lwp_display_options[location_cookie_expiry]" value="<?php echo esc_attr($value); ?>" min="1" max="365" class="small-text">
            <p class="description"><?php esc_html_e('Number of days to remember user\'s location choice (1-365).', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_advanced_settings_section'
        );

        add_settings_field(
            'location_detection_method',
            __('Location Detection Method', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['location_detection_method' => 'manual']);
                $value = isset($options['location_detection_method']) ? $options['location_detection_method'] : 'manual';
        ?>
            <select name="lwp_display_options[location_detection_method]">
                <option value="manual" <?php selected($value, 'manual'); ?>><?php esc_html_e('Manual Selection Only', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="geolocation" <?php selected($value, 'geolocation'); ?>><?php esc_html_e('Browser Geolocation', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="ip_based" <?php selected($value, 'ip_based'); ?>><?php esc_html_e('IP-Based Detection', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="user_profile" <?php selected($value, 'user_profile'); ?>><?php esc_html_e('User Profile Address', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('How to automatically detect customer location.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_advanced_settings_section'
        );
        // Add Product Visibility section
        add_settings_section(
            'lwp_product_visibility_section',
            __('Product Visibility Rules', 'location-wise-products-for-woocommerce'),
            function () {
                echo '<p>' . esc_html__('Configure advanced rules for product visibility based on locations.', 'location-wise-products-for-woocommerce') . '</p>';
            },
            'location-stock-settings'
        );

        // Add "Out of Stock Behavior" field
        add_settings_field(
            'out_of_stock_behavior',
            __('Out of Stock Behavior', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['out_of_stock_behavior' => 'hide']);
                $value = isset($options['out_of_stock_behavior']) ? $options['out_of_stock_behavior'] : 'hide';
        ?>
            <select name="lwp_display_options[out_of_stock_behavior]">
                <option value="hide" <?php selected($value, 'hide'); ?>><?php esc_html_e('Hide Product', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="show_unavailable" <?php selected($value, 'show_unavailable'); ?>><?php esc_html_e('Show as Unavailable', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="suggest_other_location" <?php selected($value, 'suggest_other_location'); ?>><?php esc_html_e('Suggest Other Location with Stock', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('How to handle products that are out of stock at the current location.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_product_visibility_section'
        );

        // Add "Show Global Products" field
        add_settings_field(
            'show_global_products',
            __('Show Global Products', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['show_global_products' => 'yes']);
                $value = isset($options['show_global_products']) ? $options['show_global_products'] : 'yes';
        ?>
            <select name="lwp_display_options[show_global_products]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Show products that are not assigned to any specific location.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_product_visibility_section'
        );

        // Add "Product Priority Display" field
        add_settings_field(
            'product_priority_display',
            __('Product Priority Display', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['product_priority_display' => 'location_first']);
                $value = isset($options['product_priority_display']) ? $options['product_priority_display'] : 'location_first';
        ?>
            <select name="lwp_display_options[product_priority_display]">
                <option value="location_first" <?php selected($value, 'location_first'); ?>><?php esc_html_e('Location Products First', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="global_first" <?php selected($value, 'global_first'); ?>><?php esc_html_e('Global Products First', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="mixed" <?php selected($value, 'mixed'); ?>><?php esc_html_e('No Priority (Mixed)', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Set display priority for location-specific vs. global products.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_product_visibility_section'
        );

        // Add "Exclude Categories" field
        add_settings_field(
            'exclude_categories',
            __('Exclude Categories', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['exclude_categories' => []]);
                $excluded_cats = isset($options['exclude_categories']) ? $options['exclude_categories'] : [];

                // Get all product categories
                $categories = get_terms([
                    'taxonomy' => 'product_cat',
                    'hide_empty' => false,
                ]);

                if (!is_wp_error($categories) && !empty($categories)) {
                    echo '<select name="lwp_display_options[exclude_categories][]" multiple="multiple" class="lwp-multiselect" style="width: 400px; max-width: 100%;">';

                    foreach ($categories as $category) {
                        $selected = in_array($category->term_id, $excluded_cats) ? 'selected="selected"' : '';
                        echo '<option value="' . esc_attr($category->term_id) . '" ' . $selected . '>' . esc_html($category->name) . '</option>';
                    }

                    echo '</select>';
                } else {
                    echo '<p>' . esc_html__('No product categories found.', 'location-wise-products-for-woocommerce') . '</p>';
                }

                echo '<p class="description">' . esc_html__('Products in these categories will not be filtered by location.', 'location-wise-products-for-woocommerce') . '</p>';
            },
            'location-stock-settings',
            'lwp_product_visibility_section'
        );

        // Add new section for Location Manager Settings
        add_settings_section(
            'lwp_location_manager_section',
            __('Location Manager Settings  (Unnecessary)', 'location-wise-products-for-woocommerce'),
            function () {
                echo '<p>' . esc_html__('Configure permissions and capabilities for location managers.', 'location-wise-products-for-woocommerce') . '</p>';
            },
            'location-stock-settings'
        );

        // Add "Enable Location Manager Role" field
        add_settings_field(
            'enable_location_manager_role',
            __('Enable Location Manager Role', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['enable_location_manager_role' => 'yes']);
                $value = isset($options['enable_location_manager_role']) ? $options['enable_location_manager_role'] : 'yes';
        ?>
            <select name="lwp_display_options[enable_location_manager_role]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Create a dedicated user role for managing specific store locations.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_location_manager_section'
        );

        // Add "Location Manager Capabilities" field
        add_settings_field(
            'location_manager_capabilities',
            __('Location Manager Capabilities', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['location_manager_capabilities' => ['manage_inventory', 'view_orders', 'manage_orders', 'edit_products']]);
                $capabilities = isset($options['location_manager_capabilities']) ? $options['location_manager_capabilities'] : ['manage_inventory', 'view_orders', 'manage_orders', 'edit_products'];
        ?>
            <label><input type="checkbox" name="lwp_display_options[location_manager_capabilities][]" value="manage_inventory" <?php checked(in_array('manage_inventory', $capabilities), true); ?>> <?php esc_html_e('Manage Inventory', 'location-wise-products-for-woocommerce'); ?></label><br>
            <label><input type="checkbox" name="lwp_display_options[location_manager_capabilities][]" value="view_orders" <?php checked(in_array('view_orders', $capabilities), true); ?>> <?php esc_html_e('View Orders', 'location-wise-products-for-woocommerce'); ?></label><br>
            <label><input type="checkbox" name="lwp_display_options[location_manager_capabilities][]" value="manage_orders" <?php checked(in_array('manage_orders', $capabilities), true); ?>> <?php esc_html_e('Manage Orders', 'location-wise-products-for-woocommerce'); ?></label><br>
            <label><input type="checkbox" name="lwp_display_options[location_manager_capabilities][]" value="edit_products" <?php checked(in_array('edit_products', $capabilities), true); ?>> <?php esc_html_e('Edit Products', 'location-wise-products-for-woocommerce'); ?></label><br>
            <label><input type="checkbox" name="lwp_display_options[location_manager_capabilities][]" value="edit_prices" <?php checked(in_array('edit_prices', $capabilities), true); ?>> <?php esc_html_e('Edit Prices', 'location-wise-products-for-woocommerce'); ?></label><br>
            <label><input type="checkbox" name="lwp_display_options[location_manager_capabilities][]" value="run_reports" <?php checked(in_array('run_reports', $capabilities), true); ?>> <?php esc_html_e('Run Reports', 'location-wise-products-for-woocommerce'); ?></label><br>
            <p class="description"><?php esc_html_e('Select which capabilities location managers should have for their assigned locations.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_location_manager_section'
        );

        // Add "Dashboard Access Level" field
        add_settings_field(
            'location_manager_dashboard_access',
            __('Dashboard Access Level', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['location_manager_dashboard_access' => 'limited']);
                $value = isset($options['location_manager_dashboard_access']) ? $options['location_manager_dashboard_access'] : 'limited';
        ?>
            <select name="lwp_display_options[location_manager_dashboard_access]">
                <option value="full" <?php selected($value, 'full'); ?>><?php esc_html_e('Full (Same as Shop Manager)', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="limited" <?php selected($value, 'limited'); ?>><?php esc_html_e('Limited (Location-specific areas only)', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="custom" <?php selected($value, 'custom'); ?>><?php esc_html_e('Custom (Based on capabilities)', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Control location managers\' access to the WordPress admin dashboard.', 'location-wise-products-for-woocommerce'); ?></p>
            <?php
            },
            'location-stock-settings',
            'lwp_location_manager_section'
        );

        // Add section for Customer Location Settings
        add_settings_section(
            'lwp_customer_location_section',
            __('Customer Location Settings', 'location-wise-products-for-woocommerce'),
            function () {
                echo '<p>' . esc_html__('Configure how customer locations are determined and remembered.', 'location-wise-products-for-woocommerce') . '</p>';
            },
            'location-wise-products_settings'
        );

        // Add "Default Store Location" field
        add_settings_field(
            'default_store_location',
            __('Default Store Location', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['default_store_location' => '']);
                $value = isset($options['default_store_location']) ? $options['default_store_location'] : '';

                // Get all locations
                $locations = get_terms(array(
                    'taxonomy' => 'location', // Assuming your taxonomy is called 'location'
                    'hide_empty' => false,
                ));

                if (!is_wp_error($locations) && !empty($locations)) {
            ?>
                <select name="lwp_display_options[default_store_location]">
                    <option value="" <?php selected($value, ''); ?>><?php esc_html_e('No default (ask customer to select)', 'location-wise-products-for-woocommerce'); ?></option>
                    <?php foreach ($locations as $location) : ?>
                        <option value="<?php echo esc_attr($location->term_id); ?>" <?php selected($value, $location->term_id); ?>><?php echo esc_html($location->name); ?></option>
                    <?php endforeach; ?>
                </select>
            <?php
                } else {
                    echo '<p>' . esc_html__('No locations found. Please create locations first.', 'location-wise-products-for-woocommerce') . '</p>';
                }
            ?>
            <p class="description"><?php esc_html_e('Select the default location to show when a customer first visits your store.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-wise-products_settings',
            'lwp_customer_location_section'
        );

        // Add "Remember Customer Location" field
        add_settings_field(
            'remember_customer_location',
            __('Remember Customer Location', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['remember_customer_location' => 'yes']);
                $value = isset($options['remember_customer_location']) ? $options['remember_customer_location'] : 'yes';
        ?>
            <select name="lwp_display_options[remember_customer_location]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Remember a customer\'s location between visits.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-wise-products_settings',
            'lwp_customer_location_section'
        );

        // Add "Link Location to User Account" field
        add_settings_field(
            'link_location_to_user',
            __('Link Location to User Account', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['link_location_to_user' => 'yes']);
                $value = isset($options['link_location_to_user']) ? $options['link_location_to_user'] : 'yes';
        ?>
            <select name="lwp_display_options[link_location_to_user]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Store selected location as part of the user\'s account preferences.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-wise-products_settings',
            'lwp_customer_location_section'
        );

        // Add section for Product Allocation Settings
        add_settings_section(
            'lwp_product_allocation_section',
            __('Product Allocation Settings', 'location-wise-products-for-woocommerce'),
            function () {
                echo '<p>' . esc_html__('Configure how products are allocated to different locations.', 'location-wise-products-for-woocommerce') . '</p>';
            },
            'location-stock-settings'
        );

        // Add "Bulk Location Assignment" field
        add_settings_field(
            'enable_bulk_location_assignment',
            __('Enable Bulk Location Assignment', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['enable_bulk_location_assignment' => 'yes']);
                $value = isset($options['enable_bulk_location_assignment']) ? $options['enable_bulk_location_assignment'] : 'yes';
        ?>
            <select name="lwp_display_options[enable_bulk_location_assignment]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Enable bulk assignment of products to locations.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_product_allocation_section'
        );

        // Add "Category-Based Location Assignment" field
        add_settings_field(
            'enable_category_based_location',
            __('Category-Based Location Assignment', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['enable_category_based_location' => 'no']);
                $value = isset($options['enable_category_based_location']) ? $options['enable_category_based_location'] : 'no';
        ?>
            <select name="lwp_display_options[enable_category_based_location]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Automatically assign products to locations based on their categories.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_product_allocation_section'
        );

        // Add "Default Product Allocation" field
        add_settings_field(
            'default_product_allocation',
            __('Default Product Allocation', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['default_product_allocation' => 'all_locations']);
                $value = isset($options['default_product_allocation']) ? $options['default_product_allocation'] : 'all_locations';
        ?>
            <select name="lwp_display_options[default_product_allocation]">
                <option value="all_locations" <?php selected($value, 'all_locations'); ?>><?php esc_html_e('All Locations', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="main_location" <?php selected($value, 'main_location'); ?>><?php esc_html_e('Main Location Only', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no_location" <?php selected($value, 'no_location'); ?>><?php esc_html_e('No Location (Manually Assign)', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('How new products are allocated to locations by default.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_product_allocation_section'
        );

        // Add section for Import/Export Settings
        add_settings_section(
            'lwp_import_export_section',
            __('Import & Export Settings', 'location-wise-products-for-woocommerce'),
            function () {
                echo '<p>' . esc_html__('Configure options for importing and exporting location-based product data.', 'location-wise-products-for-woocommerce') . '</p>';
            },
            'location-stock-settings'
        );


        // Add section for Location-based Discounts
        add_settings_section(
            'lwp_location_discounts_section',
            __('Location-based Discounts', 'location-wise-products-for-woocommerce'),
            function () {
                echo '<p>' . esc_html__('Configure discount rules specific to each store location.', 'location-wise-products-for-woocommerce') . '</p>';
            },
            'location-stock-settings'
        );

        // Add "Enable Location Discounts" field
        add_settings_field(
            'enable_location_discounts',
            __('Enable Location Discounts', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['enable_location_discounts' => 'no']);
                $value = isset($options['enable_location_discounts']) ? $options['enable_location_discounts'] : 'no';
        ?>
            <select name="lwp_display_options[enable_location_discounts]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Allow different discount rules for each store location.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_location_discounts_section'
        );

        // Add "Location-Specific Coupon Codes" field
        add_settings_field(
            'location_specific_coupons',
            __('Location-Specific Coupon Codes', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['location_specific_coupons' => 'no']);
                $value = isset($options['location_specific_coupons']) ? $options['location_specific_coupons'] : 'no';
        ?>
            <select name="lwp_display_options[location_specific_coupons]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Allow coupon codes to be restricted to specific store locations.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_location_discounts_section'
        );

        // Add "Location-Specific Sale Dates" field
        add_settings_field(
            'location_specific_sales',
            __('Location-Specific Sale Dates', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['location_specific_sales' => 'no']);
                $value = isset($options['location_specific_sales']) ? $options['location_specific_sales'] : 'no';
        ?>
            <select name="lwp_display_options[location_specific_sales]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Allow products to have different sale start/end dates per location.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_location_discounts_section'
        );

        // Add section for Multi-Location Cart Settings
        add_settings_section(
            'lwp_cart_checkout_section',
            __('Multi-Location Cart & Checkout Settings', 'location-wise-products-for-woocommerce'),
            function () {
                echo '<p>' . esc_html__('Configure how the cart and checkout handle products from multiple locations.', 'location-wise-products-for-woocommerce') . '</p>';
            },
            'location-stock-settings'
        );

        // Add "Allow Mixed-Location Cart" field
        add_settings_field(
            'allow_mixed_location_cart',
            __('Allow Mixed-Location Cart', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['allow_mixed_location_cart' => 'yes']);
                $value = isset($options['allow_mixed_location_cart']) ? $options['allow_mixed_location_cart'] : 'yes';
        ?>
            <select name="lwp_display_options[allow_mixed_location_cart]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Allow customers to add products from different locations to their cart.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_cart_checkout_section'
        );

        // Add "Mixed Cart Warning Message" field
        add_settings_field(
            'mixed_cart_warning',
            __('Mixed Cart Warning Message', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['mixed_cart_warning' => __('Your cart contains products from multiple store locations.', 'location-wise-products-for-woocommerce')]);
                $value = isset($options['mixed_cart_warning']) ? $options['mixed_cart_warning'] : __('Your cart contains products from multiple store locations.', 'location-wise-products-for-woocommerce');
        ?>
            <textarea name="lwp_display_options[mixed_cart_warning]" rows="3" class="large-text"><?php echo esc_textarea($value); ?></textarea>
            <p class="description"><?php esc_html_e('Warning message to display when cart contains products from multiple locations.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_cart_checkout_section'
        );

        // Add "Group Cart Items by Location" field
        add_settings_field(
            'group_cart_by_location',
            __('Group Cart Items by Location', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['group_cart_by_location' => 'yes']);
                $value = isset($options['group_cart_by_location']) ? $options['group_cart_by_location'] : 'yes';
        ?>
            <select name="lwp_display_options[group_cart_by_location]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Group cart items by their store location for better visibility.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_cart_checkout_section'
        );

        // Add section for Location-based Taxes
        add_settings_section(
            'lwp_location_tax_section',
            __('Location-based Tax Settings', 'location-wise-products-for-woocommerce'),
            function () {
                echo '<p>' . esc_html__('Configure tax settings specific to each store location.', 'location-wise-products-for-woocommerce') . '</p>';
            },
            'location-stock-settings'
        );

        // Add "Enable Location-based Taxes" field
        add_settings_field(
            'enable_location_taxes',
            __('Enable Location-based Taxes', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['enable_location_taxes' => 'no']);
                $value = isset($options['enable_location_taxes']) ? $options['enable_location_taxes'] : 'no';
        ?>
            <select name="lwp_display_options[enable_location_taxes]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Apply different tax rates based on the product location.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_location_tax_section'
        );

        // Add "Tax Calculation for Mixed Cart" field
        add_settings_field(
            'tax_calculation_mixed_cart',
            __('Tax Calculation for Mixed Cart', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['tax_calculation_mixed_cart' => 'separate']);
                $value = isset($options['tax_calculation_mixed_cart']) ? $options['tax_calculation_mixed_cart'] : 'separate';
        ?>
            <select name="lwp_display_options[tax_calculation_mixed_cart]">
                <option value="separate" <?php selected($value, 'separate'); ?>><?php esc_html_e('Calculate Separately by Location', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="shipping" <?php selected($value, 'shipping'); ?>><?php esc_html_e('Based on Shipping Location', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="billing" <?php selected($value, 'billing'); ?>><?php esc_html_e('Based on Billing Location', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="store" <?php selected($value, 'store'); ?>><?php esc_html_e('Based on Store Location', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('How taxes are calculated when cart contains products from multiple locations.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_location_tax_section'
        );

        // Add section for Location Hours and Availability
        add_settings_section(
            'lwp_location_hours_section',
            __('Location Hours & Availability', 'location-wise-products-for-woocommerce'),
            function () {
                echo '<p>' . esc_html__('Configure business hours and availability for each store location.', 'location-wise-products-for-woocommerce') . '</p>';
            },
            'location-stock-settings'
        );

        // Add "Enable Business Hours" field
        add_settings_field(
            'enable_business_hours',
            __('Enable Business Hours', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['enable_business_hours' => 'no']);
                $value = isset($options['enable_business_hours']) ? $options['enable_business_hours'] : 'no';
        ?>
            <select name="lwp_display_options[enable_business_hours]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Enable management of business hours for each location.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_location_hours_section'
        );

        // Add "Display Hours on Product Page" field
        add_settings_field(
            'display_hours_product_page',
            __('Display Hours on Product Page', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['display_hours_product_page' => 'no']);
                $value = isset($options['display_hours_product_page']) ? $options['display_hours_product_page'] : 'no';
        ?>
            <select name="lwp_display_options[display_hours_product_page]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Show store hours on product pages next to location information.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_location_hours_section'
        );

        // Add "Restrict Purchasing to Open Hours" field
        add_settings_field(
            'restrict_purchase_to_open_hours',
            __('Restrict Purchasing to Open Hours', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['restrict_purchase_to_open_hours' => 'no']);
                $value = isset($options['restrict_purchase_to_open_hours']) ? $options['restrict_purchase_to_open_hours'] : 'no';
        ?>
            <select name="lwp_display_options[restrict_purchase_to_open_hours]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Only allow purchases when the store location is open.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_location_hours_section'
        );

        // Add section for Location-based Product Variations
        add_settings_section(
            'lwp_location_variations_section',
            __('Location-based Product Variations', 'location-wise-products-for-woocommerce'),
            function () {
                echo '<p>' . esc_html__('Configure how product variations are managed across different locations.', 'location-wise-products-for-woocommerce') . '</p>';
            },
            'location-stock-settings'
        );

        // Add "Location-Specific Variations" field
        add_settings_field(
            'location_specific_variations',
            __('Location-Specific Variations', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['location_specific_variations' => 'no']);
                $value = isset($options['location_specific_variations']) ? $options['location_specific_variations'] : 'no';
        ?>
            <select name="lwp_display_options[location_specific_variations]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Allow different variation sets for products at different locations.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_location_variations_section'
        );

        // Add "Variation Stock Management" field
        add_settings_field(
            'variation_stock_management',
            __('Variation Stock Management', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['variation_stock_management' => 'independent']);
                $value = isset($options['variation_stock_management']) ? $options['variation_stock_management'] : 'independent';
        ?>
            <select name="lwp_display_options[variation_stock_management]">
                <option value="independent" <?php selected($value, 'independent'); ?>><?php esc_html_e('Independent (Each location manages variation stock)', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="synchronized" <?php selected($value, 'synchronized'); ?>><?php esc_html_e('Synchronized (Variations share stock settings across locations)', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('How variation stock is managed across different locations.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_location_variations_section'
        );

        // Add section for Location-based Email Notifications
        add_settings_section(
            'lwp_location_email_section',
            __('Location-based Email Notifications', 'location-wise-products-for-woocommerce'),
            function () {
                echo '<p>' . esc_html__('Configure email notifications based on store locations.', 'location-wise-products-for-woocommerce') . '</p>';
            },
            'location-stock-settings'
        );

        // Add "Location-Specific Email Templates" field
        add_settings_field(
            'location_specific_emails',
            __('Location-Specific Email Templates', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['location_specific_emails' => 'no']);
                $value = isset($options['location_specific_emails']) ? $options['location_specific_emails'] : 'no';
        ?>
            <select name="lwp_display_options[location_specific_emails]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Use different email templates for different store locations.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_location_email_section'
        );

        // Add "Include Location Logo in Emails" field
        add_settings_field(
            'include_location_logo_emails',
            __('Include Location Logo in Emails', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['include_location_logo_emails' => 'yes']);
                $value = isset($options['include_location_logo_emails']) ? $options['include_location_logo_emails'] : 'yes';
        ?>
            <select name="lwp_display_options[include_location_logo_emails]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Include the store location logo in order emails.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_location_email_section'
        );

        // Add "Location-Specific Email Recipients" field
        add_settings_field(
            'location_specific_email_recipients',
            __('Location-Specific Email Recipients', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['location_specific_email_recipients' => 'yes']);
                $value = isset($options['location_specific_email_recipients']) ? $options['location_specific_email_recipients'] : 'yes';
        ?>
            <select name="lwp_display_options[location_specific_email_recipients]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Send order notifications to location-specific email addresses.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_location_email_section'
        );

        /**
         * Additional Settings for Location Wise Products for WooCommerce
         */

        // Add Customer Experience Section
        add_settings_section(
            'lwp_customer_experience_section',
            __('Customer Experience Settings', 'location-wise-products-for-woocommerce'),
            function () {
                echo '<p>' . esc_html__('Configure how customers interact with location-based features.', 'location-wise-products-for-woocommerce') . '</p>';
            },
            'location-stock-settings'
        );

        // Add Location Switching Behavior field
        add_settings_field(
            'location_switching_behavior',
            __('Location Switching Behavior', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['location_switching_behavior' => 'preserve_cart']);
                $value = isset($options['location_switching_behavior']) ? $options['location_switching_behavior'] : 'preserve_cart';
        ?>
            <select name="lwp_display_options[location_switching_behavior]">
                <option value="preserve_cart" <?php selected($value, 'preserve_cart'); ?>><?php esc_html_e('Preserve Cart (Keep all products regardless of availability)', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="update_cart" <?php selected($value, 'update_cart'); ?>><?php esc_html_e('Update Cart (Remove unavailable products)', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="prompt_user" <?php selected($value, 'prompt_user'); ?>><?php esc_html_e('Prompt User (Ask before updating cart)', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('How to handle cart contents when a customer changes their location.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_customer_experience_section'
        );

        // Add Location Change Notification field
        add_settings_field(
            'location_change_notification',
            __('Location Change Notification', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['location_change_notification' => 'yes']);
                $value = isset($options['location_change_notification']) ? $options['location_change_notification'] : 'yes';
        ?>
            <select name="lwp_display_options[location_change_notification]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Display a notification when a customer changes their location.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_customer_experience_section'
        );

        // Add Location Notification Text field
        add_settings_field(
            'location_notification_text',
            __('Location Notification Text', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['location_notification_text' => 'Your shopping location has been updated to: %location%']);
                $value = isset($options['location_notification_text']) ? $options['location_notification_text'] : 'Your shopping location has been updated to: %location%';
        ?>
            <textarea name="lwp_display_options[location_notification_text]" rows="2" class="large-text"><?php echo esc_textarea($value); ?></textarea>
            <p class="description"><?php esc_html_e('Text shown when location is changed. Use %location% as a placeholder for the location name.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_customer_experience_section'
        );

        // Add Inventory Reservation Section
        add_settings_section(
            'lwp_inventory_reservation_section',
            __('Inventory Reservation Settings', 'location-wise-products-for-woocommerce'),
            function () {
                echo '<p>' . esc_html__('Configure how inventory is reserved during checkout process.', 'location-wise-products-for-woocommerce') . '</p>';
            },
            'location-stock-settings'
        );

        // Add "Enable Inventory Reservation" field
        add_settings_field(
            'enable_inventory_reservation',
            __('Enable Inventory Reservation', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['enable_inventory_reservation' => 'yes']);
                $value = isset($options['enable_inventory_reservation']) ? $options['enable_inventory_reservation'] : 'yes';
        ?>
            <select name="lwp_display_options[enable_inventory_reservation]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Reserve inventory when products are added to cart.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_inventory_reservation_section'
        );

        // Add "Reservation Duration (Minutes)" field
        add_settings_field(
            'reservation_duration',
            __('Reservation Duration (Minutes)', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['reservation_duration' => '60']);
                $value = isset($options['reservation_duration']) ? $options['reservation_duration'] : '60';
        ?>
            <input type="number" name="lwp_display_options[reservation_duration]" value="<?php echo esc_attr($value); ?>" min="1" max="1440" class="small-text">
            <p class="description"><?php esc_html_e('How long to reserve inventory items in cart before releasing (1-1440 minutes).', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_inventory_reservation_section'
        );

        // Add "Reservation Handling" field
        add_settings_field(
            'reservation_handling',
            __('Reservation Handling', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['reservation_handling' => 'soft_reserve']);
                $value = isset($options['reservation_handling']) ? $options['reservation_handling'] : 'soft_reserve';
        ?>
            <select name="lwp_display_options[reservation_handling]">
                <option value="soft_reserve" <?php selected($value, 'soft_reserve'); ?>><?php esc_html_e('Soft Reserve (Display as reserved but allow overselling)', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="hard_reserve" <?php selected($value, 'hard_reserve'); ?>><?php esc_html_e('Hard Reserve (Prevent others from purchasing reserved items)', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('How strictly to enforce inventory reservations.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_inventory_reservation_section'
        );

        // Add Location URL Settings Section
        add_settings_section(
            'lwp_location_url_section',
            __('Location URL Settings', 'location-wise-products-for-woocommerce'),
            function () {
                echo '<p>' . esc_html__('Configure how location information appears in URLs.', 'location-wise-products-for-woocommerce') . '</p>';
            },
            'location-stock-settings'
        );

        // Add "Enable Location in URLs" field
        add_settings_field(
            'enable_location_urls',
            __('Enable Location in URLs', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['enable_location_urls' => 'no']);
                $value = isset($options['enable_location_urls']) ? $options['enable_location_urls'] : 'no';
        ?>
            <select name="lwp_display_options[enable_location_urls]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Include location information in product and category URLs.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_location_url_section'
        );

        // Add "URL Location Format" field
        add_settings_field(
            'url_location_format',
            __('URL Location Format', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['url_location_format' => 'query_param']);
                $value = isset($options['url_location_format']) ? $options['url_location_format'] : 'query_param';
        ?>
            <select name="lwp_display_options[url_location_format]">
                <option value="query_param" <?php selected($value, 'query_param'); ?>><?php esc_html_e('Query Parameter (?location=store-name)', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="path_prefix" <?php selected($value, 'path_prefix'); ?>><?php esc_html_e('Path Prefix (/store-name/product-slug)', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="subdomain" <?php selected($value, 'subdomain'); ?>><?php esc_html_e('Subdomain (store-name.example.com)', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('How to format location information in URLs.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_location_url_section'
        );

        // Add "Location URL Prefix" field
        add_settings_field(
            'location_url_prefix',
            __('Location URL Prefix', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['location_url_prefix' => 'store']);
                $value = isset($options['location_url_prefix']) ? $options['location_url_prefix'] : 'store';
        ?>
            <input type="text" name="lwp_display_options[location_url_prefix]" value="<?php echo esc_attr($value); ?>" class="regular-text">
            <p class="description"><?php esc_html_e('Prefix used in URLs for location (e.g., "store" for store-name.example.com).', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_location_url_section'
        );

        // Add Mobile App Integration Section
        add_settings_section(
            'lwp_mobile_app_section',
            __('Mobile App Integration (Coming Soon)', 'location-wise-products-for-woocommerce'),
            function () {
                echo '<p>' . esc_html__('Configure settings for mobile app integration.', 'location-wise-products-for-woocommerce') . '</p>';
            },
            'location-stock-settings'
        );

        // Add "Enable Mobile App API" field
        add_settings_field(
            'enable_mobile_api',
            __('Enable Mobile App API', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['enable_mobile_api' => 'no']);
                $value = isset($options['enable_mobile_api']) ? $options['enable_mobile_api'] : 'no';
        ?>
            <select name="lwp_display_options[enable_mobile_api]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Enable API endpoints for mobile app integration.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_mobile_app_section'
        );

        // Add "Mobile App Authentication Method" field
        add_settings_field(
            'mobile_api_auth_method',
            __('Mobile App Authentication Method', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['mobile_api_auth_method' => 'jwt']);
                $value = isset($options['mobile_api_auth_method']) ? $options['mobile_api_auth_method'] : 'jwt';
        ?>
            <select name="lwp_display_options[mobile_api_auth_method]">
                <option value="jwt" <?php selected($value, 'jwt'); ?>><?php esc_html_e('JWT Authentication', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="oauth" <?php selected($value, 'oauth'); ?>><?php esc_html_e('OAuth 2.0', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="api_key" <?php selected($value, 'api_key'); ?>><?php esc_html_e('API Key', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Authentication method for mobile app API requests.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_mobile_app_section'
        );

        // Add "Location-based Push Notifications" field
        add_settings_field(
            'location_push_notifications',
            __('Location-based Push Notifications', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['location_push_notifications' => 'no']);
                $value = isset($options['location_push_notifications']) ? $options['location_push_notifications'] : 'no';
        ?>
            <select name="lwp_display_options[location_push_notifications]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Send location-specific push notifications to mobile app users.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_mobile_app_section'
        );

        // Add Batch Processing Section
        add_settings_section(
            'lwp_batch_processing_section',
            __('Batch Processing  (Unnecessary)', 'location-wise-products-for-woocommerce'),
            function () {
                echo '<p>' . esc_html__('Configure settings for batch processing operations.', 'location-wise-products-for-woocommerce') . '</p>';
            },
            'location-stock-settings'
        );

        // Add "Enable Batch Processing" field
        add_settings_field(
            'enable_batch_processing',
            __('Enable Batch Processing', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['enable_batch_processing' => 'yes']);
                $value = isset($options['enable_batch_processing']) ? $options['enable_batch_processing'] : 'yes';
        ?>
            <select name="lwp_display_options[enable_batch_processing]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Enable batch processing for inventory and product updates.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_batch_processing_section'
        );

        // Add "Batch Size" field
        add_settings_field(
            'batch_size',
            __('Batch Size', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['batch_size' => '50']);
                $value = isset($options['batch_size']) ? $options['batch_size'] : '50';
        ?>
            <input type="number" name="lwp_display_options[batch_size]" value="<?php echo esc_attr($value); ?>" min="10" max="500" class="small-text">
            <p class="description"><?php esc_html_e('Number of products to process in each batch (10-500).', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_batch_processing_section'
        );

        // Add "Processing Interval (Seconds)" field
        add_settings_field(
            'processing_interval',
            __('Processing Interval (Seconds)', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['processing_interval' => '5']);
                $value = isset($options['processing_interval']) ? $options['processing_interval'] : '5';
        ?>
            <input type="number" name="lwp_display_options[processing_interval]" value="<?php echo esc_attr($value); ?>" min="1" max="60" class="small-text">
            <p class="description"><?php esc_html_e('Seconds between processing batches (1-60).', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_batch_processing_section'
        );

        // Add Location-based PDF Invoice Section
        add_settings_section(
            'lwp_pdf_invoice_section',
            __('Location-based PDF Invoices', 'location-wise-products-for-woocommerce'),
            function () {
                echo '<p>' . esc_html__('Configure settings for location-specific PDF invoices.', 'location-wise-products-for-woocommerce') . '</p>';
            },
            'location-stock-settings'
        );

        // Add "Enable Location-based PDF Invoices" field
        add_settings_field(
            'enable_location_invoices',
            __('Enable Location-based PDF Invoices', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['enable_location_invoices' => 'no']);
                $value = isset($options['enable_location_invoices']) ? $options['enable_location_invoices'] : 'no';
        ?>
            <select name="lwp_display_options[enable_location_invoices]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Generate location-specific PDF invoices for orders.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_pdf_invoice_section'
        );

        // Advanced Location Pickup Settings
        add_settings_section(
            'lwp_location_pickup_section',
            __('Advanced Location Pickup Settings', 'location-wise-products-for-woocommerce'),
            function () {
                echo '<p>' . esc_html__('Configure advanced settings for in-store pickup functionality.', 'location-wise-products-for-woocommerce') . '</p>';
            },
            'location-stock-settings'
        );

        // Add "Enable Location Pickup" field
        add_settings_field(
            'enable_location_pickup',
            __('Enable Location Pickup', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['enable_location_pickup' => 'yes']);
                $value = isset($options['enable_location_pickup']) ? $options['enable_location_pickup'] : 'yes';
        ?>
            <select name="lwp_display_options[enable_location_pickup]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Enable in-store pickup option for products at specific locations.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_location_pickup_section'
        );

        // Add "Pickup Instructions" field
        add_settings_field(
            'pickup_instructions',
            __('Pickup Instructions', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['pickup_instructions' => '']);
                $value = isset($options['pickup_instructions']) ? $options['pickup_instructions'] : '';
        ?>
            <textarea name="lwp_display_options[pickup_instructions]" rows="3" class="large-text"><?php echo esc_textarea($value); ?></textarea>
            <p class="description"><?php esc_html_e('Default pickup instructions shown to customers (can be customized per location).', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_location_pickup_section'
        );

        // Add "Pickup Notification Recipients" field
        add_settings_field(
            'pickup_notification_recipients',
            __('Pickup Notification Recipients', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['pickup_notification_recipients' => 'both']);
                $value = isset($options['pickup_notification_recipients']) ? $options['pickup_notification_recipients'] : 'both';
        ?>
            <select name="lwp_display_options[pickup_notification_recipients]">
                <option value="admin" <?php selected($value, 'admin'); ?>><?php esc_html_e('Admin Only', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="location_manager" <?php selected($value, 'location_manager'); ?>><?php esc_html_e('Location Manager', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="both" <?php selected($value, 'both'); ?>><?php esc_html_e('Both Admin and Location Manager', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Who should receive notifications when an order is ready for pickup.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_location_pickup_section'
        );

        // Add "Pickup Preparation Time" field
        add_settings_field(
            'pickup_preparation_time',
            __('Pickup Preparation Time (Hours)', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['pickup_preparation_time' => '24']);
                $value = isset($options['pickup_preparation_time']) ? $options['pickup_preparation_time'] : '24';
        ?>
            <input type="number" name="lwp_display_options[pickup_preparation_time]" value="<?php echo esc_attr($value); ?>" min="1" max="168" class="small-text">
            <p class="description"><?php esc_html_e('Default preparation time in hours before an order is ready for pickup.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_location_pickup_section'
        );

        // Location-based Customer Insights
        add_settings_section(
            'lwp_customer_insights_section',
            __('Location-based Customer Insights', 'location-wise-products-for-woocommerce'),
            function () {
                echo '<p>' . esc_html__('Configure customer analytics and insights based on location data.', 'location-wise-products-for-woocommerce') . '</p>';
            },
            'location-stock-settings'
        );

        // Add "Enable Customer Location Tracking" field
        add_settings_field(
            'enable_customer_location_tracking',
            __('Enable Customer Location Tracking', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['enable_customer_location_tracking' => 'yes']);
                $value = isset($options['enable_customer_location_tracking']) ? $options['enable_customer_location_tracking'] : 'yes';
        ?>
            <select name="lwp_display_options[enable_customer_location_tracking]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Track and analyze customer preferences by location.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_customer_insights_section'
        );

        // Add "Customer Location History" field
        add_settings_field(
            'customer_location_history',
            __('Customer Location History', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['customer_location_history' => 'latest']);
                $value = isset($options['customer_location_history']) ? $options['customer_location_history'] : 'latest';
        ?>
            <select name="lwp_display_options[customer_location_history]">
                <option value="latest" <?php selected($value, 'latest'); ?>><?php esc_html_e('Store Latest Only', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="all" <?php selected($value, 'all'); ?>><?php esc_html_e('Store Full History', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="none" <?php selected($value, 'none'); ?>><?php esc_html_e('Do Not Store', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('How to store customer location selection history.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_customer_insights_section'
        );

        // Add "Location-based Recommendations" field
        add_settings_field(
            'location_based_recommendations',
            __('Location-based Recommendations', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['location_based_recommendations' => 'yes']);
                $value = isset($options['location_based_recommendations']) ? $options['location_based_recommendations'] : 'yes';
        ?>
            <select name="lwp_display_options[location_based_recommendations]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Show product recommendations based on location popularity.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_customer_insights_section'
        );

        // Location-based Product Bundle Settings
        add_settings_section(
            'lwp_product_bundle_section',
            __('Location-based Product Bundles', 'location-wise-products-for-woocommerce'),
            function () {
                echo '<p>' . esc_html__('Configure product bundles that are specific to store locations.', 'location-wise-products-for-woocommerce') . '</p>';
            },
            'location-stock-settings'
        );

        // Add "Enable Location Bundles" field
        add_settings_field(
            'enable_location_bundles',
            __('Enable Location Bundles', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['enable_location_bundles' => 'yes']);
                $value = isset($options['enable_location_bundles']) ? $options['enable_location_bundles'] : 'yes';
        ?>
            <select name="lwp_display_options[enable_location_bundles]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Enable location-specific product bundles.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_product_bundle_section'
        );

        // Add "Bundle Stock Management" field
        add_settings_field(
            'bundle_stock_management',
            __('Bundle Stock Management', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['bundle_stock_management' => 'components']);
                $value = isset($options['bundle_stock_management']) ? $options['bundle_stock_management'] : 'components';
        ?>
            <select name="lwp_display_options[bundle_stock_management]">
                <option value="components" <?php selected($value, 'components'); ?>><?php esc_html_e('Component Based (Check stock of each item)', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="bundle" <?php selected($value, 'bundle'); ?>><?php esc_html_e('Bundle Based (Treat as a single product)', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('How to manage stock for bundled products.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_product_bundle_section'
        );

        // Add "Bundle Pricing Display" field
        add_settings_field(
            'bundle_pricing_display',
            __('Bundle Pricing Display', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['bundle_pricing_display' => 'itemized']);
                $value = isset($options['bundle_pricing_display']) ? $options['bundle_pricing_display'] : 'itemized';
        ?>
            <select name="lwp_display_options[bundle_pricing_display]">
                <option value="itemized" <?php selected($value, 'itemized'); ?>><?php esc_html_e('Itemized (Show individual prices)', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="bundle_only" <?php selected($value, 'bundle_only'); ?>><?php esc_html_e('Bundle Only (Show only total price)', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="both" <?php selected($value, 'both'); ?>><?php esc_html_e('Both (Show total and savings)', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('How to display pricing for product bundles.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_product_bundle_section'
        );

        // Cross-Location Order Management
        add_settings_section(
            'lwp_cross_location_order_section',
            __('Cross-Location Order Management', 'location-wise-products-for-woocommerce'),
            function () {
                echo '<p>' . esc_html__('Configure how orders containing products from multiple locations are handled.', 'location-wise-products-for-woocommerce') . '</p>';
            },
            'location-stock-settings'
        );

        // Add "Multi-Location Order Handling" field
        add_settings_field(
            'multi_location_order_handling',
            __('Multi-Location Order Handling', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['multi_location_order_handling' => 'split']);
                $value = isset($options['multi_location_order_handling']) ? $options['multi_location_order_handling'] : 'split';
        ?>
            <select name="lwp_display_options[multi_location_order_handling]">
                <option value="split" <?php selected($value, 'split'); ?>><?php esc_html_e('Split into Separate Orders', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="primary" <?php selected($value, 'primary'); ?>><?php esc_html_e('Assign to Primary Location', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="largest" <?php selected($value, 'largest'); ?>><?php esc_html_e('Assign to Location with Most Items', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="manual" <?php selected($value, 'manual'); ?>><?php esc_html_e('Manual Assignment', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('How to handle orders containing products from multiple locations.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_cross_location_order_section'
        );

        // Add "Cross-Location Shipping" field
        add_settings_field(
            'cross_location_shipping',
            __('Cross-Location Shipping', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['cross_location_shipping' => 'combined']);
                $value = isset($options['cross_location_shipping']) ? $options['cross_location_shipping'] : 'combined';
        ?>
            <select name="lwp_display_options[cross_location_shipping]">
                <option value="separate" <?php selected($value, 'separate'); ?>><?php esc_html_e('Separate Shipping Charges', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="combined" <?php selected($value, 'combined'); ?>><?php esc_html_e('Combined Shipping Charge', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="highest" <?php selected($value, 'highest'); ?>><?php esc_html_e('Highest Location Rate', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('How to calculate shipping for orders from multiple locations.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_cross_location_order_section'
        );

        // Add "Cross-Location Order Status Sync" field
        add_settings_field(
            'cross_location_status_sync',
            __('Cross-Location Order Status Sync', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['cross_location_status_sync' => 'independent']);
                $value = isset($options['cross_location_status_sync']) ? $options['cross_location_status_sync'] : 'independent';
        ?>
            <select name="lwp_display_options[cross_location_status_sync]">
                <option value="independent" <?php selected($value, 'independent'); ?>><?php esc_html_e('Independent (Each location manages own status)', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="synchronized" <?php selected($value, 'synchronized'); ?>><?php esc_html_e('Synchronized (Status changes apply to all related orders)', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="parent_child" <?php selected($value, 'parent_child'); ?>><?php esc_html_e('Parent-Child (Main order controls sub-orders)', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('How order status changes are synchronized across split orders.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_cross_location_order_section'
        );
        // Add section for Location-wise Payment Methods
        add_settings_section(
            'lwp_location_payment_section',
            __('Location-wise Payment Methods', 'location-wise-products-for-woocommerce'),
            function () {
                echo '<p>' . esc_html__('Configure which payment methods are available for each store location.', 'location-wise-products-for-woocommerce') . '</p>';
            },
            'location-stock-settings'
        );

        // Add "Enable Location-wise Payment Methods" field
        add_settings_field(
            'enable_location_payment_methods',
            __('Enable Location-wise Payment Methods', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['enable_location_payment_methods' => 'no']);
                $value = isset($options['enable_location_payment_methods']) ? $options['enable_location_payment_methods'] : 'no';
        ?>
            <select name="lwp_display_options[enable_location_payment_methods]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Enable or disable payment method restrictions by location.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_location_payment_section'
        );
        // Add Location-based Product Reviews section
        add_settings_section(
            'lwp_reviews_section',
            __('Location-based Product Reviews', 'location-wise-products-for-woocommerce'),
            function () {
                echo '<p>' . esc_html__('Configure how product reviews are handled across different locations.', 'location-wise-products-for-woocommerce') . '</p>';
            },
            'location-stock-settings'
        );

        // Add "Location-Specific Reviews" field
        add_settings_field(
            'location_specific_reviews',
            __('Location-Specific Reviews', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['location_specific_reviews' => 'no']);
                $value = isset($options['location_specific_reviews']) ? $options['location_specific_reviews'] : 'no';
        ?>
            <select name="lwp_display_options[location_specific_reviews]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Allow products to have different reviews based on location.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_reviews_section'
        );

        // Add "Show Location in Reviews" field
        add_settings_field(
            'show_location_in_reviews',
            __('Show Location in Reviews', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['show_location_in_reviews' => 'yes']);
                $value = isset($options['show_location_in_reviews']) ? $options['show_location_in_reviews'] : 'yes';
        ?>
            <select name="lwp_display_options[show_location_in_reviews]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Display location information in product reviews.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_reviews_section'
        );

        // Add "Filter Reviews by Location" field
        add_settings_field(
            'filter_reviews_by_location',
            __('Filter Reviews by Location', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['filter_reviews_by_location' => 'yes']);
                $value = isset($options['filter_reviews_by_location']) ? $options['filter_reviews_by_location'] : 'yes';
        ?>
            <select name="lwp_display_options[filter_reviews_by_location]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Allow customers to filter reviews by store location.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_reviews_section'
        );

        // Add Location SEO section
        add_settings_section(
            'lwp_seo_section',
            __('Location SEO Settings', 'location-wise-products-for-woocommerce'),
            function () {
                echo '<p>' . esc_html__('Configure SEO settings for location-based product pages.', 'location-wise-products-for-woocommerce') . '</p>';
            },
            'location-stock-settings'
        );

        // Add "Location in Meta Title" field
        add_settings_field(
            'location_in_meta_title',
            __('Location in Meta Title', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['location_in_meta_title' => 'yes']);
                $value = isset($options['location_in_meta_title']) ? $options['location_in_meta_title'] : 'yes';
        ?>
            <select name="lwp_display_options[location_in_meta_title]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Include location name in product page meta titles.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_seo_section'
        );

        // Add "Location in Meta Description" field
        add_settings_field(
            'location_in_meta_description',
            __('Location in Meta Description', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['location_in_meta_description' => 'yes']);
                $value = isset($options['location_in_meta_description']) ? $options['location_in_meta_description'] : 'yes';
        ?>
            <select name="lwp_display_options[location_in_meta_description]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Include location information in product meta descriptions.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_seo_section'
        );

        // Add "Location Structured Data" field
        add_settings_field(
            'location_structured_data',
            __('Location Structured Data', 'location-wise-products-for-woocommerce'),
            function () {
                $options = get_option('lwp_display_options', ['location_structured_data' => 'yes']);
                $value = isset($options['location_structured_data']) ? $options['location_structured_data'] : 'yes';
        ?>
            <select name="lwp_display_options[location_structured_data]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php esc_html_e('Yes', 'location-wise-products-for-woocommerce'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php esc_html_e('No', 'location-wise-products-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Add location information to product structured data for SEO.', 'location-wise-products-for-woocommerce'); ?></p>
        <?php
            },
            'location-stock-settings',
            'lwp_seo_section'
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
            'shop',
            'search',
            'related',
            'recently_viewed',
            'cross_sells',
            'upsells',
            'widgets',
            'blocks',
            'rest_api'
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
}
