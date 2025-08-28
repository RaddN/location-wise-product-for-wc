<?php

if (!defined('ABSPATH')) exit;

class mulopimfwc_settings
{
    public function __construct()
    {
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function register_settings()
    {
        register_setting('mulopimfwc_settings', 'mulopimfwc_display_options', 'sanitize_settings');
        add_settings_section(
            'mulopimfwc_display_settings_section',
            __('Display Location in Product Title Settings', 'multi-location-product-and-inventory-management'),
            [$this, 'settings_section_callback'],
            'multi-location-product-and-inventory-management'
        );

        add_settings_field(
            'mulopimfwc_display_format',
            __('Location Display Format', 'multi-location-product-and-inventory-management'),
            [$this, 'display_format_field_callback'],
            'multi-location-product-and-inventory-management',
            'mulopimfwc_display_settings_section'
        );

        add_settings_field(
            'mulopimfwc_separator',
            __('Title-Location Separator', 'multi-location-product-and-inventory-management'),
            [$this, 'separator_field_callback'],
            'multi-location-product-and-inventory-management',
            'mulopimfwc_display_settings_section'
        );

        add_settings_field(
            'mulopimfwc_enabled_pages',
            __('Show Location On', 'multi-location-product-and-inventory-management'),
            [$this, 'enabled_pages_field_callback'],
            'multi-location-product-and-inventory-management',
            'mulopimfwc_display_settings_section'
        );

        
        // Add settings section
        add_settings_section(
            'location_stock_general_section',
            __('General Settings', 'multi-location-product-and-inventory-management'),
            function () {
                echo '<p>' . esc_html_e('Configure general settings for location-based stock and price management.', 'multi-location-product-and-inventory-management') . '</p>';
            },
            'multi-location-product-and-inventory-management'
        );

        // Add "Enable Location Stock" field
        add_settings_field(
            'enable_location_stock',
            __('Enable Location Stock', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['enable_location_stock' => 'yes']);
                $value = isset($options['enable_location_stock']) ? $options['enable_location_stock'] : 'yes';
?>
            <select name="mulopimfwc_display_options[enable_location_stock]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Enable or disable location-specific stock management.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'multi-location-product-and-inventory-management',
            'location_stock_general_section'
        );

        // Add "Enable Location Pricing" field
        add_settings_field(
            'enable_location_price',
            __('Enable Location Pricing', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['enable_location_price' => 'yes']);
                $value = isset($options['enable_location_price']) ? $options['enable_location_price'] : 'yes';
        ?>
            <select name="mulopimfwc_display_options[enable_location_price]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Enable or disable location-specific pricing.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'multi-location-product-and-inventory-management',
            'location_stock_general_section'
        );

        // Add "Enable Location Backorder" field
        add_settings_field(
            'enable_location_backorder',
            __('Enable Location Backorder', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['enable_location_backorder' => 'yes']);
                $value = isset($options['enable_location_backorder']) ? $options['enable_location_backorder'] : 'yes';
        ?>
            <select name="mulopimfwc_display_options[enable_location_backorder]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Enable or disable location-specific backorder management.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'multi-location-product-and-inventory-management',
            'location_stock_general_section'
        );

        // add "Enable Information for location"
        add_settings_field(
            'enable_location_information',
            __('Enable Location Information', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['enable_location_information' => 'no']);
                $value = isset($options['enable_location_information']) ? $options['enable_location_information'] : 'no';
        ?>
            <select id="enable_location_information" name="mulopimfwc_display_options[enable_location_information]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Enable or disable location-specific information management.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'multi-location-product-and-inventory-management',
            'location_stock_general_section'
        );

        add_settings_field(
            'enable_location_by_user_role',
            __('Enable Location by User Role', 'multi-location-product-and-inventory-management'),
            function () {
                $roles = wp_roles()->roles;
                $options = get_option('mulopimfwc_display_options', ['enable_location_by_user_role' => []]);
                $selected_roles = isset($options['enable_location_by_user_role']) ? $options['enable_location_by_user_role'] : [];
                foreach ($roles as $role_key => $role) {
                    $checked = in_array($role_key, $selected_roles) ? 'checked' : '';
                    echo "<label><input type='checkbox' name='mulopimfwc_display_options[enable_location_by_user_role][]' value='" . esc_attr($role_key) . "' " . esc_attr($checked) . "> " . esc_html($role['name']) . "</label><br>";
                }
        ?>
            <p class="description"><?php echo esc_html_e('Select user roles for which location-specific information is enabled.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'multi-location-product-and-inventory-management',
            'location_stock_general_section'
        );

        add_settings_section(
            'popup_shortcode_manage_section',
            __('Popup Settings', 'multi-location-product-and-inventory-management'),
            function () {
                echo '<p>' . esc_html_e('Configure Popup settings for location-based stock and price management.', 'multi-location-product-and-inventory-management') . '</p>';
            },
            'location-popup-shortcode-settings'
        );

        add_settings_field(
            'enable_popup',
            __('Enable Popup', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['enable_popup' => 'yes']);
                $value = isset($options['enable_popup']) ? $options['enable_popup'] : 'yes';
        ?>
            <select id="enable_popup" name="mulopimfwc_display_options[enable_popup]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Enable or disable popup management.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-popup-shortcode-settings',
            'popup_shortcode_manage_section'
        );

        add_settings_field(
            'use_select2',
            __('Use Select2', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['use_select2' => 'no']);
                $value = isset($options['use_select2']) ? $options['use_select2'] : 'no';
        ?>
            <select name="mulopimfwc_display_options[use_select2]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Use select2 instead of normal select', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-popup-shortcode-settings',
            'popup_shortcode_manage_section'
        );
        add_settings_field(
            'title_show_popup',
            __('Title Show in Popup', 'multi-location-product-and-inventory-management'),
            function () {
                $options = $this->get_display_options();
                $value = isset($options['title_show_popup']) ? $options['title_show_popup'] : 'yes';
        ?>
            <select name="mulopimfwc_display_options[title_show_popup]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Show title in popup modal', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-popup-shortcode-settings',
            'popup_shortcode_manage_section'
        );
        add_settings_field(
            'mulopimfwc_popup_title',
            __('Popup Title', 'multi-location-product-and-inventory-management'),
            function () {
                $options = $this->get_display_options();
                $mulopimfwc_popup_title = isset($options['mulopimfwc_popup_title']) ? $options['mulopimfwc_popup_title'] : 'Select Your Location';
        ?>
            <input type="text" name="mulopimfwc_display_options[mulopimfwc_popup_title]" value="<?php echo esc_attr($mulopimfwc_popup_title); ?>" class="regular-text">
        <?php
            },
            'location-popup-shortcode-settings',
            'popup_shortcode_manage_section'
        );
        add_settings_field(
            'mulopimfwc_popup_placeholder',
            __('Popup Placeholder', 'multi-location-product-and-inventory-management'),
            function () {
                $options = $this->get_display_options();
                $mulopimfwc_popup_placeholder = isset($options['mulopimfwc_popup_placeholder']) ? $options['mulopimfwc_popup_placeholder'] : ' -- Select a Store -- ';
        ?>
            <input type="text" name="mulopimfwc_display_options[mulopimfwc_popup_placeholder]" value="<?php echo esc_attr($mulopimfwc_popup_placeholder); ?>" class="regular-text">
        <?php
            },
            'location-popup-shortcode-settings',
            'popup_shortcode_manage_section'
        );
        add_settings_field(
            'mulopimfwc_popup_btn_txt',
            __('Popup Button Text', 'multi-location-product-and-inventory-management'),
            function () {
                $options = $this->get_display_options();
                $mulopimfwc_popup_btn_txt = isset($options['mulopimfwc_popup_btn_txt']) ? $options['mulopimfwc_popup_btn_txt'] : '';
                if (empty($mulopimfwc_popup_btn_txt) || $mulopimfwc_popup_btn_txt === '' || $mulopimfwc_popup_btn_txt === ' ') {
                    $mulopimfwc_popup_btn_txt = __('Select Location', 'multi-location-product-and-inventory-management');
                }
        ?>
            <input type="text" name="mulopimfwc_display_options[mulopimfwc_popup_btn_txt]" value="<?php echo esc_attr($mulopimfwc_popup_btn_txt); ?>" class="regular-text">
        <?php
            },
            'location-popup-shortcode-settings',
            'popup_shortcode_manage_section'
        );
        add_settings_field(
            'herichical',
            __('Herichical Option', 'multi-location-product-and-inventory-management'),
            function () {
                $options = $this->get_display_options();
                $value = isset($options['herichical']) ? $options['herichical'] : 'no';
        ?>
            <select id="herichical" name="mulopimfwc_display_options[herichical]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="seperately" <?php selected($value, 'seperately'); ?>><?php echo esc_html_e('Seperately', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
        <?php
            },
            'location-popup-shortcode-settings',
            'popup_shortcode_manage_section'
        );
        add_settings_field(
            'show_count',
            __('Show Count', 'multi-location-product-and-inventory-management'),
            function () {
                $options = $this->get_display_options();
                $value = isset($options['show_count']) ? $options['show_count'] : 'yes';
        ?>
            <select name="mulopimfwc_display_options[show_count]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
        <?php
            },
            'location-popup-shortcode-settings',
            'popup_shortcode_manage_section'
        );
        add_settings_field(
            'show_popup_admin',
            __('Show Popup for Admins', 'multi-location-product-and-inventory-management'),
            function () {
                $options = $this->get_display_options();
                $value = isset($options['show_popup_admin']) ? $options['show_popup_admin'] : 'no';
        ?>
            <select name="mulopimfwc_display_options[show_popup_admin]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
        <?php
            },
            'location-popup-shortcode-settings',
            'popup_shortcode_manage_section'
        );
        add_settings_field(
            'mulopimfwc_popup_custom_css',
            __('Popup Custom Css', 'multi-location-product-and-inventory-management'),
            function () {
                $options = $this->get_display_options();
                $mulopimfwc_popup_custom_css = isset($options['mulopimfwc_popup_custom_css']) ? $options['mulopimfwc_popup_custom_css'] : null;
        ?>
            <textarea style="height: 10rem;" name="mulopimfwc_display_options[mulopimfwc_popup_custom_css]" class="regular-text" placeholder="div#lwp-store-selector-modal{}">
            <?php echo esc_attr($mulopimfwc_popup_custom_css ?? null); ?>
            </textarea>
        <?php
            },
            'location-popup-shortcode-settings',
            'popup_shortcode_manage_section'
        );
        // Add new Inventory Management section
        add_settings_section(
            'mulopimfwc_inventory_management_section',
            __('Inventory Management (Coming Soon)', 'multi-location-product-and-inventory-management'),
            function () {
                echo '<p>' . esc_html__('Configure how inventory is managed across multiple locations.', 'multi-location-product-and-inventory-management') . '</p>';
            },
            'location-inventory-settings'
        );

        // Add "Inventory Sync Mode" field
        add_settings_field(
            'inventory_sync_mode',
            __('Inventory Sync Mode', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['inventory_sync_mode' => 'independent']);
                $value = isset($options['inventory_sync_mode']) ? $options['inventory_sync_mode'] : 'independent';
        ?>
            <select disabled name="mulopimfwc_display_options[inventory_sync_mode]">
                <option value="independent" <?php selected($value, 'independent'); ?>><?php echo esc_html_e('Independent (Each location manages its own inventory)', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="centralized" <?php selected($value, 'centralized'); ?>><?php echo esc_html_e('Centralized (Main inventory with location allocations)', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="synchronized" <?php selected($value, 'synchronized'); ?>><?php echo esc_html_e('Synchronized (Changes in one location affect all)', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Choose how inventory is managed across multiple locations.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-inventory-settings',
            'mulopimfwc_inventory_management_section'
        );

        // Add "Low Stock Threshold Method" field
        add_settings_field(
            'low_stock_threshold_method',
            __('Low Stock Threshold Method', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['low_stock_threshold_method' => 'per_location']);
                $value = isset($options['low_stock_threshold_method']) ? $options['low_stock_threshold_method'] : 'per_location';
        ?>
            <select disabled name="mulopimfwc_display_options[low_stock_threshold_method]">
                <option value="per_location" <?php selected($value, 'per_location'); ?>><?php echo esc_html_e('Per Location (Each location has its own threshold)', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="global" <?php selected($value, 'global'); ?>><?php echo esc_html_e('Global (Use WooCommerce default threshold for all locations)', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Choose how low stock thresholds are determined.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-inventory-settings',
            'mulopimfwc_inventory_management_section'
        );

        // Add "Low Stock Notification Recipients" field
        add_settings_field(
            'low_stock_notification_recipients',
            __('Low Stock Notification Recipients', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['low_stock_notification_recipients' => 'admin']);
                $value = isset($options['low_stock_notification_recipients']) ? $options['low_stock_notification_recipients'] : 'admin';
        ?>
            <select disabled name="mulopimfwc_display_options[low_stock_notification_recipients]">
                <option value="admin" <?php selected($value, 'admin'); ?>><?php echo esc_html_e('Admin Only', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="location_manager" <?php selected($value, 'location_manager'); ?>><?php echo esc_html_e('Location Manager', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="both" <?php selected($value, 'both'); ?>><?php echo esc_html_e('Both Admin and Location Manager', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Who should receive low stock notifications.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-inventory-settings',
            'mulopimfwc_inventory_management_section'
        );
        // Add Inventory Reservation Section
        add_settings_section(
            'mulopimfwc_inventory_reservation_section',
            __('Inventory Reservation Settings', 'multi-location-product-and-inventory-management'),
            function () {
                echo '<p>' . esc_html__('Configure how inventory is reserved during checkout process.', 'multi-location-product-and-inventory-management') . '</p>';
            },
            'location-inventory-settings'
        );

        // Add "Enable Inventory Reservation" field
        add_settings_field(
            'enable_inventory_reservation',
            __('Enable Inventory Reservation', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['enable_inventory_reservation' => 'yes']);
                $value = isset($options['enable_inventory_reservation']) ? $options['enable_inventory_reservation'] : 'yes';
        ?>
            <select disabled name="mulopimfwc_display_options[enable_inventory_reservation]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Reserve inventory when products are added to cart.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-inventory-settings',
            'mulopimfwc_inventory_reservation_section'
        );

        // Add "Reservation Duration (Minutes)" field
        add_settings_field(
            'reservation_duration',
            __('Reservation Duration (Minutes)', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['reservation_duration' => '60']);
                $value = isset($options['reservation_duration']) ? $options['reservation_duration'] : '60';
        ?>
            <input disabled type="number" name="mulopimfwc_display_options[reservation_duration]" value="<?php echo esc_attr($value); ?>" min="1" max="1440" class="small-text">
            <p class="description"><?php echo esc_html_e('How long to reserve inventory items in cart before releasing (1-1440 minutes).', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-inventory-settings',
            'mulopimfwc_inventory_reservation_section'
        );

        // Add "Reservation Handling" field
        add_settings_field(
            'reservation_handling',
            __('Reservation Handling', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['reservation_handling' => 'soft_reserve']);
                $value = isset($options['reservation_handling']) ? $options['reservation_handling'] : 'soft_reserve';
        ?>
            <select disabled name="mulopimfwc_display_options[reservation_handling]">
                <option value="soft_reserve" <?php selected($value, 'soft_reserve'); ?>><?php echo esc_html_e('Soft Reserve (Display as reserved but allow overselling)', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="hard_reserve" <?php selected($value, 'hard_reserve'); ?>><?php echo esc_html_e('Hard Reserve (Prevent others from purchasing reserved items)', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('How strictly to enforce inventory reservations.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-inventory-settings',
            'mulopimfwc_inventory_reservation_section'
        );
        // Add "Product Shipping" section
        add_settings_section(
            'mulopimfwc_shipping_section',
            __('Location-Based Shipping', 'multi-location-product-and-inventory-management'),
            function () {
                echo '<p>' . esc_html__('Configure shipping options based on product locations.', 'multi-location-product-and-inventory-management') . '</p>';
            },
            'lwp-location-shipping-settings'
        );

        // Add "Enable Location-Based Shipping" field
        add_settings_field(
            'enable_location_shipping',
            __('Enable Location-Based Shipping', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['enable_location_shipping' => 'no']);
                $value = isset($options['enable_location_shipping']) ? $options['enable_location_shipping'] : 'no';
        ?>
            <select disabled name="mulopimfwc_display_options[enable_location_shipping]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Enable different shipping options based on product location.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'lwp-location-shipping-settings',
            'mulopimfwc_shipping_section'
        );

        // Add "Shipping Calculation Method" field
        add_settings_field(
            'shipping_calculation_method',
            __('Shipping Calculation Method', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['shipping_calculation_method' => 'per_location']);
                $value = isset($options['shipping_calculation_method']) ? $options['shipping_calculation_method'] : 'per_location';
        ?>
            <select disabled name="mulopimfwc_display_options[shipping_calculation_method]">
                <option value="per_location" <?php selected($value, 'per_location'); ?>><?php echo esc_html_e('Per Location (Each location has its own rates)', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="nearest_location" <?php selected($value, 'nearest_location'); ?>><?php echo esc_html_e('Nearest Location (Calculate from closest store)', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="combined" <?php selected($value, 'combined'); ?>><?php echo esc_html_e('Combined (Calculate all shipping rates separately)', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('How shipping rates are calculated for multi-location orders.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'lwp-location-shipping-settings',
            'mulopimfwc_shipping_section'
        );

        // Add "Local Pickup Priority" field
        add_settings_field(
            'local_pickup_priority',
            __('Local Pickup Priority', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['local_pickup_priority' => 'normal']);
                $value = isset($options['local_pickup_priority']) ? $options['local_pickup_priority'] : 'normal';
        ?>
            <select disabled name="mulopimfwc_display_options[local_pickup_priority]">
                <option value="normal" <?php selected($value, 'normal'); ?>><?php echo esc_html_e('Normal (Show with other shipping options)', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="highlighted" <?php selected($value, 'highlighted'); ?>><?php echo esc_html_e('Highlighted (Emphasize local pickup option)', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="preferred" <?php selected($value, 'preferred'); ?>><?php echo esc_html_e('Preferred (Show at top of shipping options)', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('How to prioritize local pickup options at checkout.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'lwp-location-shipping-settings',
            'mulopimfwc_shipping_section'
        );
        // Add section for Location-wise Payment Methods
        add_settings_section(
            'mulopimfwc_location_payment_section',
            __('Location-wise Payment Methods', 'multi-location-product-and-inventory-management'),
            function () {
                echo '<p>' . esc_html__('Configure which payment methods are available for each store location.', 'multi-location-product-and-inventory-management') . '</p>';
            },
            'lwp-location-payment-settings'
        );

        // Add "Enable Location-wise Payment Methods" field
        add_settings_field(
            'enable_location_payment_methods',
            __('Enable Location-wise Payment Methods', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['enable_location_payment_methods' => 'no']);
                $value = isset($options['enable_location_payment_methods']) ? $options['enable_location_payment_methods'] : 'no';
        ?>
            <select disabled name="mulopimfwc_display_options[enable_location_payment_methods]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Enable or disable payment method restrictions by location.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'lwp-location-payment-settings',
            'mulopimfwc_location_payment_section'
        );
        
        // Add section for Location-based Taxes
        add_settings_section(
            'mulopimfwc_location_tax_section',
            __('Location-based Tax Settings', 'multi-location-product-and-inventory-management'),
            function () {
                echo '<p>' . esc_html__('Configure tax settings specific to each store location.', 'multi-location-product-and-inventory-management') . '</p>';
            },
            'lwp-location-tax-settings'
        );

        // Add "Enable Location-based Taxes" field
        add_settings_field(
            'enable_location_taxes',
            __('Enable Location-based Taxes', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['enable_location_taxes' => 'no']);
                $value = isset($options['enable_location_taxes']) ? $options['enable_location_taxes'] : 'no';
        ?>
            <select disabled name="mulopimfwc_display_options[enable_location_taxes]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Apply different tax rates based on the product location.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'lwp-location-tax-settings',
            'mulopimfwc_location_tax_section'
        );

        // Add "Tax Calculation for Mixed Cart" field
        add_settings_field(
            'tax_calculation_mixed_cart',
            __('Tax Calculation for Mixed Cart', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['tax_calculation_mixed_cart' => 'separate']);
                $value = isset($options['tax_calculation_mixed_cart']) ? $options['tax_calculation_mixed_cart'] : 'separate';
        ?>
            <select disabled name="mulopimfwc_display_options[tax_calculation_mixed_cart]">
                <option value="separate" <?php selected($value, 'separate'); ?>><?php echo esc_html_e('Calculate Separately by Location', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="shipping" <?php selected($value, 'shipping'); ?>><?php echo esc_html_e('Based on Shipping Location', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="billing" <?php selected($value, 'billing'); ?>><?php echo esc_html_e('Based on Billing Location', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="store" <?php selected($value, 'store'); ?>><?php echo esc_html_e('Based on Store Location', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('How taxes are calculated when cart contains products from multiple locations.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'lwp-location-tax-settings',
            'mulopimfwc_location_tax_section'
        );

        
        // Add section for Location-based Discounts
        add_settings_section(
            'mulopimfwc_location_discounts_section',
            __('Location-based Discounts', 'multi-location-product-and-inventory-management'),
            function () {
                echo '<p>' . esc_html__('Configure discount rules specific to each store location.', 'multi-location-product-and-inventory-management') . '</p>';
            },
            'lwp-location-discount-settings'
        );

        // Add "Enable Location Discounts" field
        add_settings_field(
            'enable_location_discounts',
            __('Enable Location Discounts', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['enable_location_discounts' => 'no']);
                $value = isset($options['enable_location_discounts']) ? $options['enable_location_discounts'] : 'no';
        ?>
            <select disabled name="mulopimfwc_display_options[enable_location_discounts]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Allow different discount rules for each store location.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'lwp-location-discount-settings',
            'mulopimfwc_location_discounts_section'
        );

        // Add "Location-Specific Coupon Codes" field
        add_settings_field(
            'location_specific_coupons',
            __('Location-Specific Coupon Codes', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['location_specific_coupons' => 'no']);
                $value = isset($options['location_specific_coupons']) ? $options['location_specific_coupons'] : 'no';
        ?>
            <select disabled name="mulopimfwc_display_options[location_specific_coupons]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Allow coupon codes to be restricted to specific store locations.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'lwp-location-discount-settings',
            'mulopimfwc_location_discounts_section'
        );

        // Add "Location-Specific Sale Dates" field
        add_settings_field(
            'location_specific_sales',
            __('Location-Specific Sale Dates', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['location_specific_sales' => 'no']);
                $value = isset($options['location_specific_sales']) ? $options['location_specific_sales'] : 'no';
        ?>
            <select disabled name="mulopimfwc_display_options[location_specific_sales]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Allow products to have different sale start/end dates per location.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'lwp-location-discount-settings',
            'mulopimfwc_location_discounts_section'
        );
        // Add Location-based Product Reviews section
        add_settings_section(
            'mulopimfwc_reviews_section',
            __('Location-based Product Reviews', 'multi-location-product-and-inventory-management'),
            function () {
                echo '<p>' . esc_html__('Configure how product reviews are handled across different locations.', 'multi-location-product-and-inventory-management') . '</p>';
            },
            'lwp-location-reviews-settings'
        );

        // Add "Location-Specific Reviews" field
        add_settings_field(
            'location_specific_reviews',
            __('Location-Specific Reviews', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['location_specific_reviews' => 'no']);
                $value = isset($options['location_specific_reviews']) ? $options['location_specific_reviews'] : 'no';
        ?>
            <select disabled name="mulopimfwc_display_options[location_specific_reviews]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Allow products to have different reviews based on location.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'lwp-location-reviews-settings',
            'mulopimfwc_reviews_section'
        );

        // Add "Show Location in Reviews" field
        add_settings_field(
            'show_location_in_reviews',
            __('Show Location in Reviews', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['show_location_in_reviews' => 'yes']);
                $value = isset($options['show_location_in_reviews']) ? $options['show_location_in_reviews'] : 'yes';
        ?>
            <select disabled name="mulopimfwc_display_options[show_location_in_reviews]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Display location information in product reviews.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'lwp-location-reviews-settings',
            'mulopimfwc_reviews_section'
        );

        // Add "Filter Reviews by Location" field
        add_settings_field(
            'filter_reviews_by_location',
            __('Filter Reviews by Location', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['filter_reviews_by_location' => 'yes']);
                $value = isset($options['filter_reviews_by_location']) ? $options['filter_reviews_by_location'] : 'yes';
        ?>
            <select disabled name="mulopimfwc_display_options[filter_reviews_by_location]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Allow customers to filter reviews by store location.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'lwp-location-reviews-settings',
            'mulopimfwc_reviews_section'
        );
        
        // Location-based Product Bundle Settings
        add_settings_section(
            'mulopimfwc_product_bundle_section',
            __('Location-based Product Bundles', 'multi-location-product-and-inventory-management'),
            function () {
                echo '<p>' . esc_html__('Configure product bundles that are specific to store locations.', 'multi-location-product-and-inventory-management') . '</p>';
            },
            'lwp-location-bundles-settings'
        );

        // Add "Enable Location Bundles" field
        add_settings_field(
            'enable_location_bundles',
            __('Enable Location Bundles', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['enable_location_bundles' => 'yes']);
                $value = isset($options['enable_location_bundles']) ? $options['enable_location_bundles'] : 'yes';
        ?>
            <select disabled name="mulopimfwc_display_options[enable_location_bundles]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Enable location-specific product bundles.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'lwp-location-bundles-settings',
            'mulopimfwc_product_bundle_section'
        );

        // Add "Bundle Stock Management" field
        add_settings_field(
            'bundle_stock_management',
            __('Bundle Stock Management', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['bundle_stock_management' => 'components']);
                $value = isset($options['bundle_stock_management']) ? $options['bundle_stock_management'] : 'components';
        ?>
            <select disabled name="mulopimfwc_display_options[bundle_stock_management]">
                <option value="components" <?php selected($value, 'components'); ?>><?php echo esc_html_e('Component Based (Check stock of each item)', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="bundle" <?php selected($value, 'bundle'); ?>><?php echo esc_html_e('Bundle Based (Treat as a single product)', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('How to manage stock for bundled products.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'lwp-location-bundles-settings',
            'mulopimfwc_product_bundle_section'
        );

        // Add "Bundle Pricing Display" field
        add_settings_field(
            'bundle_pricing_display',
            __('Bundle Pricing Display', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['bundle_pricing_display' => 'itemized']);
                $value = isset($options['bundle_pricing_display']) ? $options['bundle_pricing_display'] : 'itemized';
        ?>
            <select disabled name="mulopimfwc_display_options[bundle_pricing_display]">
                <option value="itemized" <?php selected($value, 'itemized'); ?>><?php echo esc_html_e('Itemized (Show individual prices)', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="bundle_only" <?php selected($value, 'bundle_only'); ?>><?php echo esc_html_e('Bundle Only (Show only total price)', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="both" <?php selected($value, 'both'); ?>><?php echo esc_html_e('Both (Show total and savings)', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('How to display pricing for product bundles.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'lwp-location-bundles-settings',
            'mulopimfwc_product_bundle_section'
        );
        
        // Add Location SEO section
        add_settings_section(
            'mulopimfwc_seo_section',
            __('Location SEO Settings', 'multi-location-product-and-inventory-management'),
            function () {
                echo '<p>' . esc_html__('Configure SEO settings for location-based product pages.', 'multi-location-product-and-inventory-management') . '</p>';
            },
            'lwp-location-seo-settings'
        );

        // Add "Location in Meta Title" field
        add_settings_field(
            'location_in_meta_title',
            __('Location in Meta Title', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['location_in_meta_title' => 'yes']);
                $value = isset($options['location_in_meta_title']) ? $options['location_in_meta_title'] : 'yes';
        ?>
            <select disabled name="mulopimfwc_display_options[location_in_meta_title]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Include location name in product page meta titles.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'lwp-location-seo-settings',
            'mulopimfwc_seo_section'
        );

        // Add "Location in Meta Description" field
        add_settings_field(
            'location_in_meta_description',
            __('Location in Meta Description', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['location_in_meta_description' => 'yes']);
                $value = isset($options['location_in_meta_description']) ? $options['location_in_meta_description'] : 'yes';
        ?>
            <select disabled name="mulopimfwc_display_options[location_in_meta_description]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Include location information in product meta descriptions.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'lwp-location-seo-settings',
            'mulopimfwc_seo_section'
        );

        // Add "Location Structured Data" field
        add_settings_field(
            'location_structured_data',
            __('Location Structured Data', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['location_structured_data' => 'yes']);
                $value = isset($options['location_structured_data']) ? $options['location_structured_data'] : 'yes';
        ?>
            <select disabled name="mulopimfwc_display_options[location_structured_data]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Add location information to product structured data for SEO.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'lwp-location-seo-settings',
            'mulopimfwc_seo_section'
        );
        // Add section for Location-based Email Notifications
        add_settings_section(
            'mulopimfwc_location_email_section',
            __('Location-based Email Notifications', 'multi-location-product-and-inventory-management'),
            function () {
                echo '<p>' . esc_html__('Configure email notifications based on store locations.', 'multi-location-product-and-inventory-management') . '</p>';
            },
            'lwp-location-notifications-settings'
        );

        // Add "Location-Specific Email Templates" field
        add_settings_field(
            'location_specific_emails',
            __('Location-Specific Email Templates', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['location_specific_emails' => 'no']);
                $value = isset($options['location_specific_emails']) ? $options['location_specific_emails'] : 'no';
        ?>
            <select disabled name="mulopimfwc_display_options[location_specific_emails]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Use different email templates for different store locations.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'lwp-location-notifications-settings',
            'mulopimfwc_location_email_section'
        );

        // Add "Include Location Logo in Emails" field
        add_settings_field(
            'include_location_logo_emails',
            __('Include Location Logo in Emails', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['include_location_logo_emails' => 'yes']);
                $value = isset($options['include_location_logo_emails']) ? $options['include_location_logo_emails'] : 'yes';
        ?>
            <select disabled name="mulopimfwc_display_options[include_location_logo_emails]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Include the store location logo in order emails.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'lwp-location-notifications-settings',
            'mulopimfwc_location_email_section'
        );

        // Add "Location-Specific Email Recipients" field
        add_settings_field(
            'location_specific_email_recipients',
            __('Location-Specific Email Recipients', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['location_specific_email_recipients' => 'yes']);
                $value = isset($options['location_specific_email_recipients']) ? $options['location_specific_email_recipients'] : 'yes';
        ?>
            <select disabled name="mulopimfwc_display_options[location_specific_email_recipients]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Send order notifications to location-specific email addresses.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'lwp-location-notifications-settings',
            'mulopimfwc_location_email_section'
        );
        
        // Add Location-based PDF Invoice Section
        add_settings_section(
            'mulopimfwc_pdf_invoice_section',
            __('Location-based PDF Invoices', 'multi-location-product-and-inventory-management'),
            function () {
                echo '<p>' . esc_html__('Configure settings for location-specific PDF invoices.', 'multi-location-product-and-inventory-management') . '</p>';
            },
            'lwp-location-others-settings'
        );

        // Add "Enable Location-based PDF Invoices" field
        add_settings_field(
            'enable_location_invoices',
            __('Enable Location-based PDF Invoices', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['enable_location_invoices' => 'no']);
                $value = isset($options['enable_location_invoices']) ? $options['enable_location_invoices'] : 'no';
        ?>
            <select disabled name="mulopimfwc_display_options[enable_location_invoices]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Generate location-specific PDF invoices for orders.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'lwp-location-others-settings',
            'mulopimfwc_pdf_invoice_section'
        );
        // Add section for Location Hours and Availability
        add_settings_section(
            'mulopimfwc_location_hours_section',
            __('Location Hours & Availability', 'multi-location-product-and-inventory-management'),
            function () {
                echo '<p>' . esc_html__('Configure business hours and availability for each store location.', 'multi-location-product-and-inventory-management') . '</p>';
            },
            'location-customer-experience-settings'
        );

        // Add "Enable Business Hours" field
        add_settings_field(
            'enable_business_hours',
            __('Enable Business Hours', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['enable_business_hours' => 'no']);
                $value = isset($options['enable_business_hours']) ? $options['enable_business_hours'] : 'no';
        ?>
            <select disabled name="mulopimfwc_display_options[enable_business_hours]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Enable management of business hours for each location.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-customer-experience-settings',
            'mulopimfwc_location_hours_section'
        );

        // Add "Display Hours on Product Page" field
        add_settings_field(
            'display_hours_product_page',
            __('Display Hours on Product Page', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['display_hours_product_page' => 'no']);
                $value = isset($options['display_hours_product_page']) ? $options['display_hours_product_page'] : 'no';
        ?>
            <select disabled name="mulopimfwc_display_options[display_hours_product_page]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Show store hours on product pages next to location information.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-customer-experience-settings',
            'mulopimfwc_location_hours_section'
        );

        // Add "Restrict Purchasing to Open Hours" field
        add_settings_field(
            'restrict_purchase_to_open_hours',
            __('Restrict Purchasing to Open Hours', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['restrict_purchase_to_open_hours' => 'no']);
                $value = isset($options['restrict_purchase_to_open_hours']) ? $options['restrict_purchase_to_open_hours'] : 'no';
        ?>
            <select disabled name="mulopimfwc_display_options[restrict_purchase_to_open_hours]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Only allow purchases when the store location is open.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-customer-experience-settings',
            'mulopimfwc_location_hours_section'
        );

// Add Location URL Settings Section
        add_settings_section(
            'mulopimfwc_location_url_section',
            __('Location URL Settings', 'multi-location-product-and-inventory-management'),
            function () {
                echo '<p>' . esc_html__('Configure how location information appears in URLs.', 'multi-location-product-and-inventory-management') . '</p>';
            },
            'location-customer-experience-settings'
        );

        // Add "Enable Location in URLs" field
        add_settings_field(
            'enable_location_urls',
            __('Enable Location in URLs', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['enable_location_urls' => 'no']);
                $value = isset($options['enable_location_urls']) ? $options['enable_location_urls'] : 'no';
        ?>
            <select disabled name="mulopimfwc_display_options[enable_location_urls]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Include location information in product and category URLs.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-customer-experience-settings',
            'mulopimfwc_location_url_section'
        );

        // Add "URL Location Format" field
        add_settings_field(
            'url_location_format',
            __('URL Location Format', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['url_location_format' => 'query_param']);
                $value = isset($options['url_location_format']) ? $options['url_location_format'] : 'query_param';
        ?>
            <select disabled name="mulopimfwc_display_options[url_location_format]">
                <option value="query_param" <?php selected($value, 'query_param'); ?>><?php echo esc_html_e('Query Parameter (?location=store-name)', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="path_prefix" <?php selected($value, 'path_prefix'); ?>><?php echo esc_html_e('Path Prefix (/store-name/product-slug)', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="subdomain" <?php selected($value, 'subdomain'); ?>><?php echo esc_html_e('Subdomain (store-name.example.com)', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('How to format location information in URLs.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-customer-experience-settings',
            'mulopimfwc_location_url_section'
        );

        // Add "Location URL Prefix" field
        add_settings_field(
            'location_url_prefix',
            __('Location URL Prefix', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['location_url_prefix' => 'store']);
                $value = isset($options['location_url_prefix']) ? $options['location_url_prefix'] : 'store';
        ?>
            <input disabled type="text" name="mulopimfwc_display_options[location_url_prefix]" value="<?php echo esc_attr($value); ?>" class="regular-text">
            <p class="description"><?php echo esc_html_e('Prefix used in URLs for location (e.g., "store" for store-name.example.com).', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-customer-experience-settings',
            'mulopimfwc_location_url_section'
        );
// Add Location Display section
        add_settings_section(
            'mulopimfwc_location_display_section',
            __('Location Selection Display (Coming Soon)', 'multi-location-product-and-inventory-management'),
            function () {
                echo '<p>' . esc_html__('Configure how the location selector appears to customers.', 'multi-location-product-and-inventory-management') . '</p>';
            },
            'location-extensions-settings'
        );

        // Add "Display Location on Single Product" field
        add_settings_field(
            'display_location_single_product',
            __('Display Location on Single Product', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['display_location_single_product' => 'yes']);
                $value = isset($options['display_location_single_product']) ? $options['display_location_single_product'] : 'yes';
        ?>
            <select disabled name="mulopimfwc_display_options[display_location_single_product]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Show current location on single product pages.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-extensions-settings',
            'mulopimfwc_location_display_section'
        );

        // Add "Location Display Position" field
        add_settings_field(
            'location_display_position',
            __('Location Display Position', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['location_display_position' => 'after_price']);
                $value = isset($options['location_display_position']) ? $options['location_display_position'] : 'after_price';
        ?>
            <select disabled name="mulopimfwc_display_options[location_display_position]">
                <option value="after_title" <?php selected($value, 'after_title'); ?>><?php echo esc_html_e('After Product Title', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="after_price" <?php selected($value, 'after_price'); ?>><?php echo esc_html_e('After Product Price', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="before_add_to_cart" <?php selected($value, 'before_add_to_cart'); ?>><?php echo esc_html_e('Before Add to Cart Button', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="after_add_to_cart" <?php selected($value, 'after_add_to_cart'); ?>><?php echo esc_html_e('After Add to Cart Button', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="product_meta" <?php selected($value, 'product_meta'); ?>><?php echo esc_html_e('In Product Meta Section', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Where to display the current location on single product pages.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-extensions-settings',
            'mulopimfwc_location_display_section'
        );


        // Add "Store Locator Integration" section
        add_settings_section(
            'mulopimfwc_store_locator_section',
            __('Store Locator', 'multi-location-product-and-inventory-management'),
            function () {
                echo '<p>' . esc_html__('Configure store locator functionality and integration.', 'multi-location-product-and-inventory-management') . '</p>';
            },
            'location-extensions-settings'
        );

        // Add "Enable Store Locator" field
        add_settings_field(
            'enable_store_locator',
            __('Enable Store Locator', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['enable_store_locator' => 'no']);
                $value = isset($options['enable_store_locator']) ? $options['enable_store_locator'] : 'no';
        ?>
            <select disabled name="mulopimfwc_display_options[enable_store_locator]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Enable store locator with map functionality.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-extensions-settings',
            'mulopimfwc_store_locator_section'
        );

        // Add "Map Provider" field
        add_settings_field(
            'map_provider',
            __('Map Provider', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['map_provider' => 'google_maps']);
                $value = isset($options['map_provider']) ? $options['map_provider'] : 'google_maps';
        ?>
            <select disabled name="mulopimfwc_display_options[map_provider]">
                <option value="google_maps" <?php selected($value, 'google_maps'); ?>><?php echo esc_html_e('Google Maps', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="openstreetmap" <?php selected($value, 'openstreetmap'); ?>><?php echo esc_html_e('OpenStreetMap', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="mapbox" <?php selected($value, 'mapbox'); ?>><?php echo esc_html_e('Mapbox', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Select which map provider to use for the store locator.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-extensions-settings',
            'mulopimfwc_store_locator_section'
        );

        // Add "Map API Key" field
        add_settings_field(
            'map_api_key',
            __('Map API Key', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['map_api_key' => '']);
                $value = isset($options['map_api_key']) ? $options['map_api_key'] : '';
        ?>
            <input disabled type="text" name="mulopimfwc_display_options[map_api_key]" value="<?php echo esc_attr($value); ?>" class="regular-text">
            <p class="description"><?php echo esc_html_e('Enter your API key for the selected map provider.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-extensions-settings',
            'mulopimfwc_store_locator_section'
        );

        // Add "Default Map Zoom Level" field
        add_settings_field(
            'default_map_zoom',
            __('Default Map Zoom Level', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['default_map_zoom' => '10']);
                $value = isset($options['default_map_zoom']) ? $options['default_map_zoom'] : '10';
        ?>
            <input disabled type="number" name="mulopimfwc_display_options[default_map_zoom]" value="<?php echo esc_attr($value); ?>" min="1" max="20" class="small-text">
            <p class="description"><?php echo esc_html_e('Default zoom level for the store locator map (1-20).', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-extensions-settings',
            'mulopimfwc_store_locator_section'
        );
        // Add Advanced Settings section
        add_settings_section(
            'mulopimfwc_advanced_settings_section',
            __('Advanced Settings', 'multi-location-product-and-inventory-management'),
            function () {
                echo '<p>' . esc_html__('Advanced configuration options for location management.', 'multi-location-product-and-inventory-management') . '</p>';
            },
            'location-advance-settings'
        );

        add_settings_field(
            'location_cookie_expiry',
            __('Location Cookie Expiry (Days)', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['location_cookie_expiry' => '30']);
                $value = isset($options['location_cookie_expiry']) ? $options['location_cookie_expiry'] : '30';
        ?>
            <input disabled type="number" name="mulopimfwc_display_options[location_cookie_expiry]" value="<?php echo esc_attr($value); ?>" min="1" max="365" class="small-text">
            <p class="description"><?php echo esc_html_e('Number of days to remember user\'s location choice (1-365).', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-advance-settings',
            'mulopimfwc_advanced_settings_section'
        );

        add_settings_field(
            'location_detection_method',
            __('Location Detection Method', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['location_detection_method' => 'manual']);
                $value = isset($options['location_detection_method']) ? $options['location_detection_method'] : 'manual';
        ?>
            <select name="mulopimfwc_display_options[location_detection_method]">
                <option value="manual" <?php selected($value, 'manual'); ?>><?php echo esc_html_e('Manual Selection Only', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="geolocation" <?php selected($value, 'geolocation'); ?>><?php echo esc_html_e('Manual Selection & Browser Geolocation', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="ip_based" <?php selected($value, 'ip_based'); ?>><?php echo esc_html_e('Manual Selection & IP-Based Detection', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="user_profile" <?php selected($value, 'user_profile'); ?>><?php echo esc_html_e('Manual Selection & User Profile Address', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('How to automatically detect customer location.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-advance-settings',
            'mulopimfwc_advanced_settings_section'
        );
        // Add section for Import/Export Settings
        add_settings_section(
            'mulopimfwc_import_export_section',
            __('Import & Export Settings', 'multi-location-product-and-inventory-management'),
            function () {
                echo '<p>' . esc_html__('Configure options for importing and exporting location-based product data.', 'multi-location-product-and-inventory-management') . '</p>';
            },
            'location-advance-settings'
        );
        
        // Add new section for Location Manager Settings
        add_settings_section(
            'mulopimfwc_location_manager_section',
            __('Location Manager Settings  (Unnecessary)', 'multi-location-product-and-inventory-management'),
            function () {
                echo '<p>' . esc_html__('Configure permissions and capabilities for location managers.', 'multi-location-product-and-inventory-management') . '</p>';
            },
            'location-advance-settings'
        );

        // Add "Enable Location Manager Role" field
        add_settings_field(
            'enable_location_manager_role',
            __('Enable Location Manager Role', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['enable_location_manager_role' => 'yes']);
                $value = isset($options['enable_location_manager_role']) ? $options['enable_location_manager_role'] : 'yes';
        ?>
            <select disabled name="mulopimfwc_display_options[enable_location_manager_role]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Create a dedicated user role for managing specific store locations.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-advance-settings',
            'mulopimfwc_location_manager_section'
        );

        // Add "Location Manager Capabilities" field
        add_settings_field(
            'location_manager_capabilities',
            __('Location Manager Capabilities', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['location_manager_capabilities' => ['manage_inventory', 'view_orders', 'manage_orders', 'edit_products']]);
                $capabilities = isset($options['location_manager_capabilities']) ? $options['location_manager_capabilities'] : ['manage_inventory', 'view_orders', 'manage_orders', 'edit_products'];
        ?>
            <label><input disabled type="checkbox" name="mulopimfwc_display_options[location_manager_capabilities][]" value="manage_inventory" <?php checked(in_array('manage_inventory', $capabilities), true); ?>> <?php echo esc_html_e('Manage Inventory', 'multi-location-product-and-inventory-management'); ?></label><br>
            <label><input disabled type="checkbox" name="mulopimfwc_display_options[location_manager_capabilities][]" value="view_orders" <?php checked(in_array('view_orders', $capabilities), true); ?>> <?php echo esc_html_e('View Orders', 'multi-location-product-and-inventory-management'); ?></label><br>
            <label><input disabled type="checkbox" name="mulopimfwc_display_options[location_manager_capabilities][]" value="manage_orders" <?php checked(in_array('manage_orders', $capabilities), true); ?>> <?php echo esc_html_e('Manage Orders', 'multi-location-product-and-inventory-management'); ?></label><br>
            <label><input disabled type="checkbox" name="mulopimfwc_display_options[location_manager_capabilities][]" value="edit_products" <?php checked(in_array('edit_products', $capabilities), true); ?>> <?php echo esc_html_e('Edit Products', 'multi-location-product-and-inventory-management'); ?></label><br>
            <label><input disabled type="checkbox" name="mulopimfwc_display_options[location_manager_capabilities][]" value="edit_prices" <?php checked(in_array('edit_prices', $capabilities), true); ?>> <?php echo esc_html_e('Edit Prices', 'multi-location-product-and-inventory-management'); ?></label><br>
            <label><input disabled type="checkbox" name="mulopimfwc_display_options[location_manager_capabilities][]" value="run_reports" <?php checked(in_array('run_reports', $capabilities), true); ?>> <?php echo esc_html_e('Run Reports', 'multi-location-product-and-inventory-management'); ?></label><br>
            <p class="description"><?php echo esc_html_e('Select which capabilities location managers should have for their assigned locations.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-advance-settings',
            'mulopimfwc_location_manager_section'
        );

        // Add "Dashboard Access Level" field
        add_settings_field(
            'location_manager_dashboard_access',
            __('Dashboard Access Level', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['location_manager_dashboard_access' => 'limited']);
                $value = isset($options['location_manager_dashboard_access']) ? $options['location_manager_dashboard_access'] : 'limited';
        ?>
            <select disabled name="mulopimfwc_display_options[location_manager_dashboard_access]">
                <option value="full" <?php selected($value, 'full'); ?>><?php echo esc_html_e('Full (Same as Shop Manager)', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="limited" <?php selected($value, 'limited'); ?>><?php echo esc_html_e('Limited (Location-specific areas only)', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="custom" <?php selected($value, 'custom'); ?>><?php echo esc_html_e('Custom (Based on capabilities)', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Control location managers\' access to the WordPress admin dashboard.', 'multi-location-product-and-inventory-management'); ?></p>
            <?php
            },
            'location-advance-settings',
            'mulopimfwc_location_manager_section'
        );

        // Add section for Customer Location Settings
        add_settings_section(
            'mulopimfwc_customer_location_section',
            __('Customer Location Settings', 'multi-location-product-and-inventory-management'),
            function () {
                echo '<p>' . esc_html__('Configure how customer locations are determined and remembered.', 'multi-location-product-and-inventory-management') . '</p>';
            },
            'location-wise-products_settings'
        );

        // Add "Default Store Location" field
        add_settings_field(
            'default_store_location',
            __('Default Store Location', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['default_store_location' => '']);
                $value = isset($options['default_store_location']) ? $options['default_store_location'] : '';

                // Get all locations
                $locations = get_terms(array(
                    'taxonomy' => 'location', // Assuming your taxonomy is called 'location'
                    'hide_empty' => false,
                ));

                if (!is_wp_error($locations) && !empty($locations)) {
            ?>
                <select name="mulopimfwc_display_options[default_store_location]">
                    <option value="" <?php selected($value, ''); ?>><?php echo esc_html_e('No default (ask customer to select)', 'multi-location-product-and-inventory-management'); ?></option>
                    <?php foreach ($locations as $location) : ?>
                        <option value="<?php echo esc_attr($location->term_id); ?>" <?php selected($value, $location->term_id); ?>><?php echo esc_html($location->name); ?></option>
                    <?php endforeach; ?>
                </select>
            <?php
                } else {
                    echo '<p>' . esc_html__('No locations found. Please create locations first.', 'multi-location-product-and-inventory-management') . '</p>';
                }
            ?>
            <p class="description"><?php echo esc_html_e('Select the default location to show when a customer first visits your store.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-wise-products_settings',
            'mulopimfwc_customer_location_section'
        );

        // Add "Remember Customer Location" field
        add_settings_field(
            'remember_customer_location',
            __('Remember Customer Location', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['remember_customer_location' => 'yes']);
                $value = isset($options['remember_customer_location']) ? $options['remember_customer_location'] : 'yes';
        ?>
            <select name="mulopimfwc_display_options[remember_customer_location]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Remember a customer\'s location between visits.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-wise-products_settings',
            'mulopimfwc_customer_location_section'
        );

        // Add "Link Location to User Account" field
        add_settings_field(
            'link_location_to_user',
            __('Link Location to User Account', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['link_location_to_user' => 'yes']);
                $value = isset($options['link_location_to_user']) ? $options['link_location_to_user'] : 'yes';
        ?>
            <select name="mulopimfwc_display_options[link_location_to_user]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Store selected location as part of the user\'s account preferences.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-wise-products_settings',
            'mulopimfwc_customer_location_section'
        );

        // Add section for Product Allocation Settings
        add_settings_section(
            'mulopimfwc_product_allocation_section',
            __('Product Allocation Settings', 'multi-location-product-and-inventory-management'),
            function () {
                echo '<p>' . esc_html__('Configure how products are allocated to different locations.', 'multi-location-product-and-inventory-management') . '</p>';
            },
            'location-advance-settings'
        );

        // Add "Bulk Location Assignment" field
        add_settings_field(
            'enable_bulk_location_assignment',
            __('Enable Bulk Location Assignment', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['enable_bulk_location_assignment' => 'yes']);
                $value = isset($options['enable_bulk_location_assignment']) ? $options['enable_bulk_location_assignment'] : 'yes';
        ?>
            <select disabled name="mulopimfwc_display_options[enable_bulk_location_assignment]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Enable bulk assignment of products to locations.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-advance-settings',
            'mulopimfwc_product_allocation_section'
        );

        // Add "Category-Based Location Assignment" field
        add_settings_field(
            'enable_category_based_location',
            __('Category-Based Location Assignment', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['enable_category_based_location' => 'no']);
                $value = isset($options['enable_category_based_location']) ? $options['enable_category_based_location'] : 'no';
        ?>
            <select disabled name="mulopimfwc_display_options[enable_category_based_location]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Automatically assign products to locations based on their categories.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-advance-settings',
            'mulopimfwc_product_allocation_section'
        );

        // Add Product Visibility section
        add_settings_section(
            'mulopimfwc_product_visibility_section',
            __('Product Visibility Rules', 'multi-location-product-and-inventory-management'),
            function () {
                echo '<p>' . esc_html__('Configure advanced rules for product visibility based on locations.', 'multi-location-product-and-inventory-management') . '</p>';
            },
            'location-product-visibility-settings'
        );

        // Add "Show Global Products" field
        add_settings_field(
            'show_global_products',
            __('Show Global Products', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['enable_all_locations' => 'yes']);
                $value = isset($options['enable_all_locations']) ? $options['enable_all_locations'] : 'yes';
        ?>
            <select name="mulopimfwc_display_options[enable_all_locations]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
                <option disabled value="when_location_selected" <?php selected($value, 'when_location_selected'); ?>><?php echo esc_html_e('Only When Location is Selected (coming soon)', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Show products that are not assigned to any specific location.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-product-visibility-settings',
            'mulopimfwc_product_visibility_section'
        );

        // Add "Product Priority Display" field
        add_settings_field(
            'product_priority_display',
            __('Product Priority Display', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['product_priority_display' => 'location_first']);
                $value = isset($options['product_priority_display']) ? $options['product_priority_display'] : 'location_first';
        ?>
            <select name="mulopimfwc_display_options[product_priority_display]">
                <option value="location_first" <?php selected($value, 'location_first'); ?>><?php echo esc_html_e('Location Products First', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="global_first" <?php selected($value, 'global_first'); ?>><?php echo esc_html_e('Global Products First', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="mixed" <?php selected($value, 'mixed'); ?>><?php echo esc_html_e('No Priority (Mixed)', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Set display priority for location-specific vs. global products.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-product-visibility-settings',
            'mulopimfwc_product_visibility_section'
        );

        // Add "Exclude Categories" field
        add_settings_field(
            'exclude_categories',
            __('Exclude Categories (Coming Soon)', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['exclude_categories' => []]);
                $excluded_cats = isset($options['exclude_categories']) ? $options['exclude_categories'] : [];

                // Get all product categories
                $categories = get_terms([
                    'taxonomy' => 'product_cat',
                    'hide_empty' => false,
                ]);

                if (!is_wp_error($categories) && !empty($categories)) {
                    echo '<select disabled name="mulopimfwc_display_options[exclude_categories][]" multiple="multiple" class="lwp-multiselect" style="width: 400px; max-width: 100%;">';

                    foreach ($categories as $category) {
                        $selected = in_array($category->term_id, $excluded_cats) ? 'selected="selected"' : '';
                        echo '<option value="' . esc_attr($category->term_id) . '" ' . $selected . '>' . esc_html($category->name) . '</option>';
                    }

                    echo '</select>';
                } else {
                    echo '<p>' . esc_html__('No product categories found.', 'multi-location-product-and-inventory-management') . '</p>';
                }

                echo '<p class="description">' . esc_html__('Products in these categories will not be filtered by location.', 'multi-location-product-and-inventory-management') . '</p>';
            },
            'location-product-visibility-settings',
            'mulopimfwc_product_visibility_section'
        );        
        // Add Location-Based Product Display section
        add_settings_section(
            'mulopimfwc_location_product_display_section',
            __('Out of Stock Product Display', 'multi-location-product-and-inventory-management'),
            function () {
                echo '<p>' . esc_html__('Configure how products are displayed based on location availability and stock.', 'multi-location-product-and-inventory-management') . '</p>';
            },
            'location-product-visibility-settings'
        );

        // Add "Show Out of Stock Products" field
        add_settings_field(
            'show_out_of_stock_products',
            __('Show Out of Stock Products', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['show_out_of_stock_products' => 'hide']);
                $value = isset($options['show_out_of_stock_products']) ? $options['show_out_of_stock_products'] : 'hide';
        ?>
            <select name="mulopimfwc_display_options[show_out_of_stock_products]">
                <option value="none" <?php selected($value, 'none'); ?>><?php echo esc_html_e('Default', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="hide" <?php selected($value, 'hide'); ?>><?php echo esc_html_e('Hide Completely', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="show_with_badge" <?php selected($value, 'show_with_badge'); ?>><?php echo esc_html_e('Show with "Out of Stock" Badge', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="show_grayed_out" <?php selected($value, 'show_grayed_out'); ?>><?php echo esc_html_e('Show Grayed Out', 'multi-location-product-and-inventory-management'); ?></option>
                <option disabled value="show_with_notification" <?php selected($value, 'show_with_notification'); ?>><?php echo esc_html_e('Show with Alternative Location Notification', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('How to display products that are out of stock at the selected location.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-product-visibility-settings',
            'mulopimfwc_location_product_display_section'
        );

        // Add "Alternative Location Suggestion" field
        add_settings_field(
            'alternative_location_suggestion',
            __('Alternative Location Suggestion', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['alternative_location_suggestion' => 'nearest']);
                $value = isset($options['alternative_location_suggestion']) ? $options['alternative_location_suggestion'] : 'nearest';
        ?>
            <select disabled name="mulopimfwc_display_options[alternative_location_suggestion]">
                <option value="disabled" <?php selected($value, 'disabled'); ?>><?php echo esc_html_e('Disabled', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="nearest" <?php selected($value, 'nearest'); ?>><?php echo esc_html_e('Show Nearest Location with Stock', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="all_available" <?php selected($value, 'all_available'); ?>><?php echo esc_html_e('Show All Locations with Stock', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="lowest_price" <?php selected($value, 'lowest_price'); ?>><?php echo esc_html_e('Show Location with Lowest Price', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Suggest alternative locations when product is unavailable at current location.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-product-visibility-settings',
            'mulopimfwc_location_product_display_section'
        );

        // Add "Stock Display Format" field
        add_settings_field(
            'stock_display_format',
            __('Stock Display Format', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['stock_display_format' => 'exact_count']);
                $value = isset($options['stock_display_format']) ? $options['stock_display_format'] : 'exact_count';
        ?>
            <select disabled name="mulopimfwc_display_options[stock_display_format]">
                <option value="exact_count" <?php selected($value, 'exact_count'); ?>><?php echo esc_html_e('Show Exact Stock Count', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="availability_only" <?php selected($value, 'availability_only'); ?>><?php echo esc_html_e('Show Only Availability (In Stock/Out of Stock)', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="stock_levels" <?php selected($value, 'stock_levels'); ?>><?php echo esc_html_e('Show Stock Levels (High/Medium/Low)', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="hide_stock" <?php selected($value, 'hide_stock'); ?>><?php echo esc_html_e('Hide Stock Information', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('How to display stock information for location-specific products.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-product-visibility-settings',
            'mulopimfwc_location_product_display_section'
        );
        
        // Add Admin Visibility Controls section
        add_settings_section(
            'mulopimfwc_admin_visibility_section',
            __('Admin Visibility Controls', 'multi-location-product-and-inventory-management'),
            function () {
                echo '<p>' . esc_html__('Configure admin-specific visibility and management options.', 'multi-location-product-and-inventory-management') . '</p>';
            },
            'location-product-visibility-settings'
        );

        // Add "Show All Products in Admin" field
        add_settings_field(
            'show_all_products_admin',
            __('Show All Products in Admin', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['show_all_products_admin' => 'yes']);
                $value = isset($options['show_all_products_admin']) ? $options['show_all_products_admin'] : 'yes';
        ?>
            <select name="mulopimfwc_display_options[show_all_products_admin]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
                <option disabled value="based_on_role" <?php selected($value, 'based_on_role'); ?>><?php echo esc_html_e('Based on User Role', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Whether admins can see all products regardless of location restrictions.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-product-visibility-settings',
            'mulopimfwc_admin_visibility_section'
        );

        add_settings_section(
            'mulopimfwc_filter_settings_section',
            __('Location Filtering Settings', 'location-product-visibility-settings'),
            [$this, 'filter_settings_section_callback'],
            'location-product-visibility-settings'
        );

        add_settings_field(
            'mulopimfwc_strict_filtering',
            __('Strict Location Filtering', 'location-product-visibility-settings'),
            [$this, 'strict_filtering_field_callback'],
            'location-product-visibility-settings',
            'mulopimfwc_filter_settings_section'
        );

        add_settings_field(
            'mulopimfwc_filtered_sections',
            __('Apply Location Filtering To', 'location-product-visibility-settings'),
            [$this, 'filtered_sections_field_callback'],
            'location-product-visibility-settings',
            'mulopimfwc_filter_settings_section'
        );

        // Add Order Fulfillment section
        add_settings_section(
            'mulopimfwc_order_fulfillment_section',
            __('Order Fulfillment', 'multi-location-product-and-inventory-management'),
            function () {
                echo '<p>' . esc_html__('Configure how orders are processed and fulfilled from different locations.', 'multi-location-product-and-inventory-management') . '</p>';
            },
            'location-cross-order-settings'
        );

        // Add "Order Assignment Method" field
        add_settings_field(
            'order_assignment_method',
            __('Order Assignment Method', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['order_assignment_method' => 'customer_selection']);
                $value = isset($options['order_assignment_method']) ? $options['order_assignment_method'] : 'customer_selection';
        ?>
            <select disabled name="mulopimfwc_display_options[order_assignment_method]">
                <option value="customer_selection" <?php selected($value, 'customer_selection'); ?>><?php echo esc_html_e('Customer Selection (Based on selected location)', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="inventory_based" <?php selected($value, 'inventory_based'); ?>><?php echo esc_html_e('Inventory Based (Location with highest stock)', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="proximity_based" <?php selected($value, 'proximity_based'); ?>><?php echo esc_html_e('Proximity Based (Nearest location to shipping address)', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="manual" <?php selected($value, 'manual'); ?>><?php echo esc_html_e('Manual Assignment (Admin assigns after order)', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('How orders are assigned to locations for fulfillment.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-cross-order-settings',
            'mulopimfwc_order_fulfillment_section'
        );

        // Add "Order Notification Recipients" field
        add_settings_field(
            'order_notification_recipients',
            __('Order Notification Recipients', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['order_notification_recipients' => 'admin']);
                $value = isset($options['order_notification_recipients']) ? $options['order_notification_recipients'] : 'admin';
        ?>
            <select disabled name="mulopimfwc_display_options[order_notification_recipients]">
                <option value="admin" <?php selected($value, 'admin'); ?>><?php echo esc_html_e('Admin Only', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="location_manager" <?php selected($value, 'location_manager'); ?>><?php echo esc_html_e('Location Manager', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="both" <?php selected($value, 'both'); ?>><?php echo esc_html_e('Both Admin and Location Manager', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Who should receive order notifications.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-cross-order-settings',
            'mulopimfwc_order_fulfillment_section'
        );
        // Cross-Location Order Management
        add_settings_section(
            'mulopimfwc_cross_location_order_section',
            __('Cross-Location Order Management (Coming Soon)', 'multi-location-product-and-inventory-management'),
            function () {
                echo '<p>' . esc_html__('Configure how orders containing products from multiple locations are handled.', 'multi-location-product-and-inventory-management') . '</p>';
            },
            'location-cross-order-settings'
        );

        
        // Add "Allow Mixed-Location Cart" field
        add_settings_field(
            'allow_mixed_location_cart',
            __('Allow Mixed-Location Cart', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['allow_mixed_location_cart' => 'yes']);
                $value = isset($options['allow_mixed_location_cart']) ? $options['allow_mixed_location_cart'] : 'yes';
        ?>
            <select disabled name="mulopimfwc_display_options[allow_mixed_location_cart]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Allow customers to add products from different locations to their cart.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-cross-order-settings',
            'mulopimfwc_cross_location_order_section'
        );

        // Add "Mixed Cart Warning Message" field
        add_settings_field(
            'mixed_cart_warning',
            __('Mixed Cart Warning Message', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['mixed_cart_warning' => __('Your cart contains products from multiple store locations.', 'multi-location-product-and-inventory-management')]);
                $value = isset($options['mixed_cart_warning']) ? $options['mixed_cart_warning'] : __('Your cart contains products from multiple store locations.', 'multi-location-product-and-inventory-management');
        ?>
            <textarea disabled name="mulopimfwc_display_options[mixed_cart_warning]" rows="3" class="large-text"><?php echo esc_textarea($value); ?></textarea>
            <p class="description"><?php echo esc_html_e('Warning message to display when cart contains products from multiple locations.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-cross-order-settings',
            'mulopimfwc_cross_location_order_section'
        );

        // Add "Group Cart Items by Location" field
        add_settings_field(
            'group_cart_by_location',
            __('Group Cart Items by Location', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['group_cart_by_location' => 'yes']);
                $value = isset($options['group_cart_by_location']) ? $options['group_cart_by_location'] : 'yes';
        ?>
            <select disabled name="mulopimfwc_display_options[group_cart_by_location]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Group cart items by their store location for better visibility.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-cross-order-settings',
            'mulopimfwc_cross_location_order_section'
        );

        // Add "Multi-Location Order Handling" field
        add_settings_field(
            'multi_location_order_handling',
            __('Multi-Location Order Handling', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['multi_location_order_handling' => 'split']);
                $value = isset($options['multi_location_order_handling']) ? $options['multi_location_order_handling'] : 'split';
        ?>
            <select disabled name="mulopimfwc_display_options[multi_location_order_handling]">
                <option value="split" <?php selected($value, 'split'); ?>><?php echo esc_html_e('Split into Separate Orders', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="primary" <?php selected($value, 'primary'); ?>><?php echo esc_html_e('Assign to Primary Location', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="largest" <?php selected($value, 'largest'); ?>><?php echo esc_html_e('Assign to Location with Most Items', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="manual" <?php selected($value, 'manual'); ?>><?php echo esc_html_e('Manual Assignment', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('How to handle orders containing products from multiple locations.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-cross-order-settings',
            'mulopimfwc_cross_location_order_section'
        );

        // Add "Cross-Location Shipping" field
        add_settings_field(
            'cross_location_shipping',
            __('Cross-Location Shipping', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['cross_location_shipping' => 'combined']);
                $value = isset($options['cross_location_shipping']) ? $options['cross_location_shipping'] : 'combined';
        ?>
            <select disabled name="mulopimfwc_display_options[cross_location_shipping]">
                <option value="separate" <?php selected($value, 'separate'); ?>><?php echo esc_html_e('Separate Shipping Charges', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="combined" <?php selected($value, 'combined'); ?>><?php echo esc_html_e('Combined Shipping Charge', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="highest" <?php selected($value, 'highest'); ?>><?php echo esc_html_e('Highest Location Rate', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('How to calculate shipping for orders from multiple locations.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-cross-order-settings',
            'mulopimfwc_cross_location_order_section'
        );

        // Add "Cross-Location Order Status Sync" field
        add_settings_field(
            'cross_location_status_sync',
            __('Cross-Location Order Status Sync', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['cross_location_status_sync' => 'independent']);
                $value = isset($options['cross_location_status_sync']) ? $options['cross_location_status_sync'] : 'independent';
        ?>
            <select disabled name="mulopimfwc_display_options[cross_location_status_sync]">
                <option value="independent" <?php selected($value, 'independent'); ?>><?php echo esc_html_e('Independent (Each location manages own status)', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="synchronized" <?php selected($value, 'synchronized'); ?>><?php echo esc_html_e('Synchronized (Status changes apply to all related orders)', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="parent_child" <?php selected($value, 'parent_child'); ?>><?php echo esc_html_e('Parent-Child (Main order controls sub-orders)', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('How order status changes are synchronized across split orders.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-cross-order-settings',
            'mulopimfwc_cross_location_order_section'
        );
        
        

        /**
         * Additional Settings for Multi Location Product & Inventory Management for WooCommerce Pro
         */
        // Add Customer Experience Section
        add_settings_section(
            'mulopimfwc_customer_experience_section',
            __('Customer Experience Settings', 'multi-location-product-and-inventory-management'),
            function () {
                echo '<p>' . esc_html__('Configure how customers interact with location-based features.', 'multi-location-product-and-inventory-management') . '</p>';
            },
            'location-customer-experience-settings'
        );

        // Add Location Switching Behavior field
        add_settings_field(
            'location_switching_behavior',
            __('Location Switching Behavior', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['location_switching_behavior' => 'preserve_cart']);
                $value = isset($options['location_switching_behavior']) ? $options['location_switching_behavior'] : 'preserve_cart';
        ?>
            <select disabled name="mulopimfwc_display_options[location_switching_behavior]">
                <option value="preserve_cart" <?php selected($value, 'preserve_cart'); ?>><?php echo esc_html_e('Preserve Cart (Keep all products regardless of availability)', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="update_cart" <?php selected($value, 'update_cart'); ?>><?php echo esc_html_e('Update Cart (Remove unavailable products)', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="prompt_user" <?php selected($value, 'prompt_user'); ?>><?php echo esc_html_e('Prompt User (Ask before updating cart)', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('How to handle cart contents when a customer changes their location.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-customer-experience-settings',
            'mulopimfwc_customer_experience_section'
        );

        // Add Location Change Notification field
        add_settings_field(
            'location_change_notification',
            __('Location Change Notification', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['location_change_notification' => 'yes']);
                $value = isset($options['location_change_notification']) ? $options['location_change_notification'] : 'yes';
        ?>
            <select disabled name="mulopimfwc_display_options[location_change_notification]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Display a notification when a customer changes their location.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-customer-experience-settings',
            'mulopimfwc_customer_experience_section'
        );

        // Add Location Notification Text field
        add_settings_field(
            'location_notification_text',
            __('Location Notification Text', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['location_notification_text' => 'Your shopping location has been updated to: %location%']);
                $value = isset($options['location_notification_text']) ? $options['location_notification_text'] : 'Your shopping location has been updated to: %location%';
        ?>
            <textarea disabled name="mulopimfwc_display_options[location_notification_text]" rows="2" class="large-text"><?php echo esc_textarea($value); ?></textarea>
            <p class="description"><?php echo esc_html_e('Text shown when location is changed. Use %location% as a placeholder for the location name.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-customer-experience-settings',
            'mulopimfwc_customer_experience_section'
        );
        // Advanced Location Pickup Settings
        add_settings_section(
            'mulopimfwc_location_pickup_section',
            __('Advanced Location Pickup Settings', 'multi-location-product-and-inventory-management'),
            function () {
                echo '<p>' . esc_html__('Configure advanced settings for in-store pickup functionality.', 'multi-location-product-and-inventory-management') . '</p>';
            },
            'location-customer-experience-settings'
        );

        // Add "Enable Location Pickup" field
        add_settings_field(
            'enable_location_pickup',
            __('Enable Location Pickup', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['enable_location_pickup' => 'yes']);
                $value = isset($options['enable_location_pickup']) ? $options['enable_location_pickup'] : 'yes';
        ?>
            <select disabled name="mulopimfwc_display_options[enable_location_pickup]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Enable in-store pickup option for products at specific locations.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-customer-experience-settings',
            'mulopimfwc_location_pickup_section'
        );

        // Add "Pickup Instructions" field
        add_settings_field(
            'pickup_instructions',
            __('Pickup Instructions', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['pickup_instructions' => '']);
                $value = isset($options['pickup_instructions']) ? $options['pickup_instructions'] : '';
        ?>
            <textarea name="mulopimfwc_display_options[pickup_instructions]" rows="3" class="large-text"><?php echo esc_textarea($value); ?></textarea>
            <p class="description"><?php echo esc_html_e('Default pickup instructions shown to customers (can be customized per location).', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-customer-experience-settings',
            'mulopimfwc_location_pickup_section'
        );

        // Add "Pickup Notification Recipients" field
        add_settings_field(
            'pickup_notification_recipients',
            __('Pickup Notification Recipients', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['pickup_notification_recipients' => 'both']);
                $value = isset($options['pickup_notification_recipients']) ? $options['pickup_notification_recipients'] : 'both';
        ?>
            <select disabled name="mulopimfwc_display_options[pickup_notification_recipients]">
                <option value="admin" <?php selected($value, 'admin'); ?>><?php echo esc_html_e('Admin Only', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="location_manager" <?php selected($value, 'location_manager'); ?>><?php echo esc_html_e('Location Manager', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="both" <?php selected($value, 'both'); ?>><?php echo esc_html_e('Both Admin and Location Manager', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Who should receive notifications when an order is ready for pickup.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-customer-experience-settings',
            'mulopimfwc_location_pickup_section'
        );

        // Add "Pickup Preparation Time" field
        add_settings_field(
            'pickup_preparation_time',
            __('Pickup Preparation Time (Hours)', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['pickup_preparation_time' => '24']);
                $value = isset($options['pickup_preparation_time']) ? $options['pickup_preparation_time'] : '24';
        ?>
            <input disabled type="number" name="mulopimfwc_display_options[pickup_preparation_time]" value="<?php echo esc_attr($value); ?>" min="1" max="168" class="small-text">
            <p class="description"><?php echo esc_html_e('Default preparation time in hours before an order is ready for pickup.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-customer-experience-settings',
            'mulopimfwc_location_pickup_section'
        );
        // Location-based Customer Insights
        add_settings_section(
            'mulopimfwc_customer_insights_section',
            __('Location-based Customer Insights', 'multi-location-product-and-inventory-management'),
            function () {
                echo '<p>' . esc_html__('Configure customer analytics and insights based on location data.', 'multi-location-product-and-inventory-management') . '</p>';
            },
            'location-customer-experience-settings'
        );

        // Add "Enable Customer Location Tracking" field
        add_settings_field(
            'enable_customer_location_tracking',
            __('Enable Customer Location Tracking', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['enable_customer_location_tracking' => 'yes']);
                $value = isset($options['enable_customer_location_tracking']) ? $options['enable_customer_location_tracking'] : 'yes';
        ?>
            <select disabled name="mulopimfwc_display_options[enable_customer_location_tracking]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Track and analyze customer preferences by location.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-customer-experience-settings',
            'mulopimfwc_customer_insights_section'
        );

        // Add "Customer Location History" field
        add_settings_field(
            'customer_location_history',
            __('Customer Location History', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['customer_location_history' => 'latest']);
                $value = isset($options['customer_location_history']) ? $options['customer_location_history'] : 'latest';
        ?>
            <select disabled name="mulopimfwc_display_options[customer_location_history]">
                <option value="latest" <?php selected($value, 'latest'); ?>><?php echo esc_html_e('Store Latest Only', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="all" <?php selected($value, 'all'); ?>><?php echo esc_html_e('Store Full History', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="none" <?php selected($value, 'none'); ?>><?php echo esc_html_e('Do Not Store', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('How to store customer location selection history.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-customer-experience-settings',
            'mulopimfwc_customer_insights_section'
        );

        // Add "Location-based Recommendations" field
        add_settings_field(
            'location_based_recommendations',
            __('Location-based Recommendations', 'multi-location-product-and-inventory-management'),
            function () {
                $options = get_option('mulopimfwc_display_options', ['location_based_recommendations' => 'yes']);
                $value = isset($options['location_based_recommendations']) ? $options['location_based_recommendations'] : 'yes';
        ?>
            <select disabled name="mulopimfwc_display_options[location_based_recommendations]">
                <option value="yes" <?php selected($value, 'yes'); ?>><?php echo esc_html_e('Yes', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="no" <?php selected($value, 'no'); ?>><?php echo esc_html_e('No', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Show product recommendations based on location popularity.', 'multi-location-product-and-inventory-management'); ?></p>
        <?php
            },
            'location-customer-experience-settings',
            'mulopimfwc_customer_insights_section'
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
        echo '<p>' . esc_html_e('Configure how store locations appear with product titles.', 'multi-location-product-and-inventory-management') . '</p>';
    }

    public function display_format_field_callback()
    {
        $options = $this->get_display_options();
        $format = isset($options['display_format']) ? $options['display_format'] : 'none';

        ?>
        <select id="mulopimfwc_display_title" name="mulopimfwc_display_options[display_format]">
            <option value="append" <?php selected($format, 'append'); ?>><?php echo esc_html_e('Append to title (Title - Location)', 'multi-location-product-and-inventory-management'); ?></option>
            <option value="prepend" <?php selected($format, 'prepend'); ?>><?php echo esc_html_e('Prepend to title (Location - Title)', 'multi-location-product-and-inventory-management'); ?></option>
            <option value="brackets" <?php selected($format, 'brackets'); ?>><?php echo esc_html_e('In brackets (Title [Location])', 'multi-location-product-and-inventory-management'); ?></option>
            <option value="none" <?php selected($format, 'none'); ?>><?php echo esc_html_e('Do not display location', 'multi-location-product-and-inventory-management'); ?></option>
        </select>
    <?php
    }

    public function separator_field_callback()
    {
        $options = $this->get_display_options();
        $separator = isset($options['separator']) ? $options['separator'] : ' - ';
    ?>
        <input type="text" name="mulopimfwc_display_options[separator]" value="<?php echo esc_attr($separator); ?>" class="regular-text">
        <p class="description"><?php echo esc_html_e('The character(s) used to separate the title and location.', 'multi-location-product-and-inventory-management'); ?></p>
    <?php
    }

    public function enabled_pages_field_callback()
    {
        $options = $this->get_display_options();
        $enabled_pages = isset($options['enabled_pages']) ? $options['enabled_pages'] : ['shop', 'single', 'cart'];
        $pages = [
            'shop' => __('Shop/Archive Pages', 'multi-location-product-and-inventory-management'),
            'single' => __('Single Product Pages', 'multi-location-product-and-inventory-management'),
            'cart' => __('Cart & Checkout', 'multi-location-product-and-inventory-management'),
            'related' => __('Related Products', 'multi-location-product-and-inventory-management'),
            'search' => __('Search Results', 'multi-location-product-and-inventory-management'),
            'widgets' => __('Widgets', 'multi-location-product-and-inventory-management'),
            'Shortcode' => __('Shortcode used pages', 'multi-location-product-and-inventory-management')
        ];

        foreach ($pages as $value => $label) {
            $checked = in_array($value, $enabled_pages) ? 'checked' : '';
            echo "<label><input type='checkbox' name='mulopimfwc_display_options[enabled_pages][]' value='" . esc_attr($value) . "' " . esc_attr($checked) . "> " . esc_html($label) . "</label><br>";
        }
    }

    public function settings_page_content()
    {
    ?>
        <div class="wrap lwp-settings-container">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="lwp-admin-notice">
                <p><?php echo esc_html_e('Use this shortcode to show location selector on any page', 'multi-location-product-and-inventory-management'); ?> <code>[mulopimfwc_store_location_selector title ="Select Your Location" show_title = "yes" use_select2 = 'yes/no' herichical = 'yes/no/seperately' show_count = 'yes/no' class = ""]</code></p>
            </div>

            <div class="nav-tab-wrapper lwp-nav-tabs">
                <a href="#lwp-display-settings" class="nav-tab nav-tab-active"><?php echo esc_html_e('General', 'multi-location-product-and-inventory-management'); ?></a>
                <a href="#popup-shortcode-settings" class="nav-tab"><?php echo esc_html_e('Popup Manage', 'multi-location-product-and-inventory-management'); ?></a>
                <a href="#product-visibility-settings" class="nav-tab"><?php echo esc_html_e('Product Visibility', 'multi-location-product-and-inventory-management'); ?></a>
                <a href="#cross-order-settings" class="nav-tab"><?php echo esc_html_e('Order Fulfill', 'multi-location-product-and-inventory-management'); ?></a>
                <a href="#inventory-settings" class="nav-tab"><?php echo esc_html_e('Inventory', 'multi-location-product-and-inventory-management'); ?></a>
                <a href="#location-wise-everything" class="nav-tab"><?php echo esc_html_e('Location Wise Everything', 'multi-location-product-and-inventory-management'); ?></a>
                <a href="#customer-experience" class="nav-tab"><?php echo esc_html_e('Customer Experience', 'multi-location-product-and-inventory-management'); ?></a>
                <a href="#extensions" class="nav-tab"><?php echo esc_html_e('Extensions', 'multi-location-product-and-inventory-management'); ?></a>
                <a href="#advance-settings" class="nav-tab"><?php echo esc_html_e('Advance Settings', 'multi-location-product-and-inventory-management'); ?></a>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('mulopimfwc_settings'); ?>

                <div id="lwp-display-settings" class="lwp-tab-content">
                    <div class="lwp-settings-section">
                        <div class="lwp-settings-box">
                            <div class="lwp-filter-settings lwp-location-show-title">
                                <?php do_settings_sections('multi-location-product-and-inventory-management'); ?>
                            </div>
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
                <div id="product-visibility-settings" class="lwp-tab-content" style="display:none;">
                    <div class="lwp-settings-section">
                        <div class="lwp-settings-box">
                            <?php do_settings_sections('location-product-visibility-settings'); ?>
                        </div>
                    </div>
                </div>
                <div id="cross-order-settings" class="lwp-tab-content" style="display:none;">
                    <div class="lwp-settings-section">
                        <div class="lwp-settings-box">
                            <?php do_settings_sections('location-cross-order-settings'); ?>
                        </div>
                    </div>
                </div>
                <div id="inventory-settings" class="lwp-tab-content" style="display:none;">
                    <div class="lwp-settings-section">
                        <div class="lwp-settings-box">
                            <?php do_settings_sections('location-inventory-settings'); ?>
                        </div>
                    </div>
                </div>
                <div id="location-wise-everything" class="lwp-tab-content" style="display:none;">
                    <div class="lwp-settings-section">
                        <div class="lwp-settings-box">
                            <div class="lwp-subtab-wrapper" style=" padding-bottom: 20px; display: flex; gap: 1rem; ">
                                <a href="#lwp-subtab-shipping" class="lwp-subtab lwp-subtab-active"><?php echo esc_html_e('Shipping', 'multi-location-product-and-inventory-management'); ?></a>
                                <a href="#lwp-subtab-payments" class="lwp-subtab"><?php echo esc_html_e('Payments', 'multi-location-product-and-inventory-management'); ?></a>
                                <a href="#lwp-subtab-tax" class="lwp-subtab"><?php echo esc_html_e('Tax', 'multi-location-product-and-inventory-management'); ?></a>
                                <a href="#lwp-subtab-discounts" class="lwp-subtab"><?php echo esc_html_e('Discounts', 'multi-location-product-and-inventory-management'); ?></a>
                                <a href="#lwp-subtab-reviews" class="lwp-subtab"><?php echo esc_html_e('Reviews', 'multi-location-product-and-inventory-management'); ?></a>
                                <a href="#lwp-subtab-bundles" class="lwp-subtab"><?php echo esc_html_e('Bundles', 'multi-location-product-and-inventory-management'); ?></a>
                                <a href="#lwp-subtab-seo" class="lwp-subtab"><?php echo esc_html_e('SEO', 'multi-location-product-and-inventory-management'); ?></a>
                                <a href="#lwp-subtab-notifications" class="lwp-subtab"><?php echo esc_html_e('Notifications', 'multi-location-product-and-inventory-management'); ?></a>
                                <a href="#lwp-subtab-others" class="lwp-subtab"><?php echo esc_html_e('Others', 'multi-location-product-and-inventory-management'); ?></a>
                            </div>
                            <div id="lwp-subtab-shipping" class="lwp-subtab-content" style="display:block;">
                                <?php do_settings_sections('lwp-location-shipping-settings'); ?>
                            </div>
                            <div id="lwp-subtab-payments" class="lwp-subtab-content" style="display:none;">
                                <?php do_settings_sections('lwp-location-payment-settings'); ?>
                            </div>
                            <div id="lwp-subtab-tax" class="lwp-subtab-content" style="display:none;">
                                <?php do_settings_sections('lwp-location-tax-settings'); ?>
                            </div>
                            <div id="lwp-subtab-discounts" class="lwp-subtab-content" style="display:none;">
                                <?php do_settings_sections('lwp-location-discount-settings'); ?>
                            </div>
                            <div id="lwp-subtab-reviews" class="lwp-subtab-content" style="display:none;">
                                <?php do_settings_sections('lwp-location-reviews-settings'); ?>
                            </div>
                            <div id="lwp-subtab-bundles" class="lwp-subtab-content" style="display:none;">
                                <?php do_settings_sections('lwp-location-bundles-settings'); ?>
                            </div>
                            <div id="lwp-subtab-seo" class="lwp-subtab-content" style="display:none;">
                                <?php do_settings_sections('lwp-location-seo-settings'); ?>
                            </div>
                            <div id="lwp-subtab-notifications" class="lwp-subtab-content" style="display:none;">
                                <?php do_settings_sections('lwp-location-notifications-settings'); ?>
                            </div>
                            <div id="lwp-subtab-others" class="lwp-subtab-content" style="display:none;">
                                <?php do_settings_sections('lwp-location-others-settings'); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="customer-experience" class="lwp-tab-content" style="display:none;">
                    <div class="lwp-settings-section">
                        <div class="lwp-settings-box">
                            <?php do_settings_sections('location-customer-experience-settings'); ?>
                        </div>
                    </div>
                </div>
                <div id="extensions" class="lwp-tab-content" style="display:none;">
                    <div class="lwp-settings-section">
                        <div class="lwp-settings-box">
                            <?php do_settings_sections('location-extensions-settings'); ?>
                        </div>
                    </div>
                </div>
                <div id="advance-settings" class="lwp-tab-content" style="display:none;">
                    <div class="lwp-settings-section">
                        <div class="lwp-settings-box">
                            <?php do_settings_sections('location-advance-settings'); ?>
                        </div>
                    </div>
                </div>


                <?php submit_button(); ?>
            </form>

            <div class="lwp-footer">
                <p><?php echo esc_html_e('Thank you for using Multi Location Product & Inventory Management for WooCommerce Pro', 'multi-location-product-and-inventory-management'); ?></p>
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
        $options = get_option('mulopimfwc_display_options', []);
        return wp_parse_args($options, $defaults);
    }
    public function filter_settings_section_callback()
    {
        echo '<p>' . esc_html_e('Configure how strictly products are filtered by location throughout your store.', 'multi-location-product-and-inventory-management') . '</p>';
    }
    public function strict_filtering_field_callback()
    {
        $options = $this->get_display_options();
        $strict = isset($options['strict_filtering']) ? $options['strict_filtering'] : 'enabled';
    ?>
        <select id="strict_filtering" name="mulopimfwc_display_options[strict_filtering]">
            <option value="enabled" <?php selected($strict, 'enabled'); ?>><?php echo esc_html_e('Enabled (Only show products from selected location)', 'multi-location-product-and-inventory-management'); ?></option>
            <option value="disabled" <?php selected($strict, 'disabled'); ?>><?php echo esc_html_e('Disabled (Show all products regardless of location)', 'multi-location-product-and-inventory-management'); ?></option>
        </select>
        <p class="description"><?php echo esc_html_e('When enabled, users will only see products from their selected location. When disabled, all products will be visible.', 'multi-location-product-and-inventory-management'); ?></p>
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
            'shop' => __('Main Shop & Category Pages', 'multi-location-product-and-inventory-management'),
            'search' => __('Search Results', 'multi-location-product-and-inventory-management'),
            'related' => __('Related Products', 'multi-location-product-and-inventory-management'),
            'recently_viewed' => __('Recently Viewed Products', 'multi-location-product-and-inventory-management'),
            'cross_sells' => __('Cross-Sells', 'multi-location-product-and-inventory-management'),
            'upsells' => __('Upsells', 'multi-location-product-and-inventory-management'),
            'widgets' => __('Product Widgets', 'multi-location-product-and-inventory-management'),
            'blocks' => __('Product Blocks (Gutenberg)', 'multi-location-product-and-inventory-management'),
            'rest_api' => __('REST API & AJAX Responses', 'multi-location-product-and-inventory-management'),
        ];

        foreach ($all_sections as $value => $label) {
            $checked = in_array($value, $sections) ? 'checked' : '';
            echo "<label><input type='checkbox' name='mulopimfwc_display_options[filtered_sections][]' value='" . esc_attr($value) . "' " . esc_attr($checked) . "> " . esc_html($label) . "</label><br>";
        }
    ?>
        <p class="description"><?php echo esc_html_e('Select which parts of your store should have location-based filtering applied.', 'multi-location-product-and-inventory-management'); ?></p>
<?php
    }
}
