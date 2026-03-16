<?php

if (!defined('ABSPATH')) exit;

class mulopimfwc_settings
{

    public function __construct()
    {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_init', [$this, 'handle_reset_settings']);
        add_action('admin_init', [$this, 'handle_reset_text_management']);
        add_action('admin_init', [$this, 'maybe_flush_rewrite_rules_on_settings_save'], 30);
    }

    /**
     * Handle reset settings form submission
     */
    public function handle_reset_settings()
    {
        // Check if reset form was submitted
        if (!isset($_POST['mulopimfwc_reset_settings'])) {
            return;
        }

        // Verify nonce
        if (
            !isset($_POST['mulopimfwc_reset_settings_nonce']) ||
            !wp_verify_nonce($_POST['mulopimfwc_reset_settings_nonce'], 'mulopimfwc_reset_settings_action')
        ) {
            wp_die(esc_html(__('Security check failed. Please go back and try again.', 'multi-location-product-and-inventory-management-pro')));
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html(__('You do not have permission to perform this action.', 'multi-location-product-and-inventory-management-pro')));
        }

        // Delete the settings option
        $deleted = delete_option('mulopimfwc_display_options');

        // Add admin notice
        if ($deleted) {
            add_settings_error(
                'mulopimfwc_messages',
                'mulopimfwc_reset_success',
                esc_html(__('Settings have been reset to default values successfully.', 'multi-location-product-and-inventory-management-pro')),
                'success'
            );
        } else {
            add_settings_error(
                'mulopimfwc_messages',
                'mulopimfwc_reset_error',
                esc_html(__('No settings were found to reset or reset failed. Settings may already be at default values.', 'multi-location-product-and-inventory-management-pro')),
                'warning'
            );
        }

        // Set transient for redirect
        set_transient('mulopimfwc_settings_reset', true, 30);

        // Redirect to avoid form resubmission
        wp_redirect(add_query_arg('settings-updated', 'true', wp_get_referer()));
        exit;
    }

    /**
     * Handle reset text management form submission
     */
    public function handle_reset_text_management()
    {
        if (!isset($_POST['mulopimfwc_reset_text_management'])) {
            return;
        }

        if (
            !isset($_POST['mulopimfwc_reset_text_management_nonce']) ||
            !wp_verify_nonce($_POST['mulopimfwc_reset_text_management_nonce'], 'mulopimfwc_reset_text_management_action')
        ) {
            wp_die(esc_html(__('Security check failed. Please go back and try again.', 'multi-location-product-and-inventory-management-pro')));
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html(__('You do not have permission to perform this action.', 'multi-location-product-and-inventory-management-pro')));
        }

        if (!function_exists('mulopimfwc_get_text_management_defaults')) {
            add_settings_error(
                'mulopimfwc_messages',
                'mulopimfwc_text_reset_error',
                __('Text management defaults are unavailable. Please reload the page and try again.', 'multi-location-product-and-inventory-management-pro'),
                'error'
            );
            wp_redirect(add_query_arg('settings-updated', 'true', wp_get_referer()));
            exit;
        }

        $options = get_option('mulopimfwc_display_options', []);
        if (!is_array($options)) {
            $options = [];
        }

        $defaults = mulopimfwc_get_text_management_defaults();
        if (empty($defaults)) {
            add_settings_error(
                'mulopimfwc_messages',
                'mulopimfwc_text_reset_empty',
                __('No text defaults were found to reset.', 'multi-location-product-and-inventory-management-pro'),
                'warning'
            );
            wp_redirect(add_query_arg('settings-updated', 'true', wp_get_referer()));
            exit;
        }

        $updated = update_option('mulopimfwc_display_options', array_merge($options, $defaults));
        if ($updated) {
            add_settings_error(
                'mulopimfwc_messages',
                'mulopimfwc_text_reset_success',
                __('Text management settings have been reset to default values successfully.', 'multi-location-product-and-inventory-management-pro'),
                'success'
            );
        } else {
            add_settings_error(
                'mulopimfwc_messages',
                'mulopimfwc_text_reset_warning',
                __('Text management settings may already be at default values.', 'multi-location-product-and-inventory-management-pro'),
                'warning'
            );
        }

        wp_redirect(add_query_arg('settings-updated', 'true', wp_get_referer()));
        exit;
    }

    public function register_settings()
    {
        register_setting('mulopimfwc_settings', 'mulopimfwc_display_options', [$this, 'sanitize_settings']);
        add_settings_section(
            'mulopimfwc_display_settings_section',
            sprintf(
                '<svg xmlns="http://www.w3.org/2000/svg" 
     viewBox="0 0 24 24" 
     width="20" height="20" 
     style="margin-right:6px;vertical-align:middle;background-color:#dbeafe;padding:10px;border-radius:6px">
  <path fill="#2563eb" d="M12 2C8.7 2 6 4.7 6 8c0 5.2 6 11.1 6 11.1s6-6 6-11.1c0-3.3-2.7-6-6-6m0 3.9c1.1 0 2.1 1 2.1 2.1 0 1.2-.9 2.1-2.1 2.1S9.9 9.1 9.9 8c0-1.2 1-2.1 2.1-2.1m-5.2 9.2c-1.3.3-2.3.6-3.2 1-.4.2-.8.5-1.1.8s-.5.9-.5 1.4c0 .8.5 1.4 1.1 1.8s1.3.7 2.2 1c1.8.6 4.1.9 6.7.9s4.9-.3 6.7-.8c.9-.3 1.6-.6 2.2-1s1.1-1 1.1-1.8c0-1-.8-1.7-1.6-2.2s-1.9-.8-3.2-1l-.3 2c1.1.2 2 .5 2.6.8.4.2.5.4.6.4l-.2.2c-.3.2-.9.5-1.6.7-1.7.4-3.9.7-6.3.7s-4.6-.3-6.1-.8c-.7-.2-1.3-.5-1.6-.7l-.2-.2c.1-.1.2-.2.5-.4.6-.3 1.5-.6 2.7-.8z"/>
</svg> %s',
                __('Display Location in Product Title Settings', 'multi-location-product-and-inventory-management-pro')
            ),
            [$this, 'settings_section_callback'],
            'multi-location-product-and-inventory-management-pro'
        );

        add_settings_field(
            'mulopimfwc_display_format',
            __('Location Display Format', 'multi-location-product-and-inventory-management-pro'),
            [$this, 'display_format_field_callback'],
            'multi-location-product-and-inventory-management-pro',
            'mulopimfwc_display_settings_section'
        );

        add_settings_field(
            'mulopimfwc_separator',
            __('Title-Location Separator', 'multi-location-product-and-inventory-management-pro'),
            [$this, 'separator_field_callback'],
            'multi-location-product-and-inventory-management-pro',
            'mulopimfwc_display_settings_section'
        );

        add_settings_field(
            'mulopimfwc_enabled_pages',
            __('Show Location On', 'multi-location-product-and-inventory-management-pro'),
            [$this, 'enabled_pages_field_callback'],
            'multi-location-product-and-inventory-management-pro',
            'mulopimfwc_display_settings_section'
        );

        // Add settings section
        add_settings_section(
            'location_stock_general_section',
            sprintf(
                '<svg xmlns="http://www.w3.org/2000/svg" 
     viewBox="0 0 486.493 486.493" 
     xml:space="preserve"
     width="20" height="20" 
     style="margin-right:6px;vertical-align:middle;background-color:#dcfce7;padding:10px;border-radius:6px">
  <path fill="#16a34a" d="m20.086 142.379 22.4 10.5c-5.4 21-6.6 43.2-3 65.5l-23.2 8.4c-8.7 3.2-13.2 12.8-10.1 21.6l8.9 24.4c3.2 8.7 12.8 13.2 21.6 10.1l23.2-8.4c11.5 19.4 26.7 35.7 44.3 48.3l-10.5 22.4c-3.9 8.4-.3 18.4 8.1 22.4l23.6 11c8.4 3.9 18.4.3 22.4-8.1l10.5-22.5c15.5 3.9 31.7 5.6 48.1 4.6l.3-6.5c.4-9.2 4.4-17.7 11.2-23.9 6.1-5.6 13.9-8.8 22.2-9 1.4-3.5 3-6.9 4.8-10.3-2.2-2.8-3.9-5.9-5.1-9.2-9.1 3.7-18.8 6.3-29 7.5-59.9 7-114.1-35.8-121.1-95.7s35.8-114.1 95.7-121.1 114.1 35.8 121.1 95.7c2.4 20.8-1.1 40.9-9.4 58.6 3.9 1.6 7.4 3.9 10.5 6.8 3.5-1.4 7-2.7 10.7-3.9 2.1-17.7 17.6-31.1 35.7-30.3l2.4.1c1.7-14.5 1.4-29.3-1-44.1l24.2-8.8c8.7-3.2 13.2-12.8 10.1-21.6l-8.9-24.4c-3.2-8.7-12.8-13.2-21.6-10.1l-24.2 8.8c-11.3-19.1-26.3-35.3-43.7-47.8l10.9-23.2c3.9-8.4.3-18.4-8.1-22.4l-23.6-11c-8.4-3.9-18.4-.3-22.4 8.1l-10.8 23.1c-20.8-5.4-42.9-6.6-64.9-3.1l-8.6-23.8c-3.2-8.7-12.8-13.2-21.6-10.1l-24.4 8.9c-8.7 3.2-13.2 12.8-10.1 21.6l8.6 23.6c-19.3 11.4-35.7 26.4-48.3 43.9l-22.5-10.5c-8.4-3.9-18.4-.3-22.4 8.1l-11 23.6c-4.1 8.2-.5 18.2 8 22.2"/>
  <path fill="#16a34a" d="M379.286 246.779v-.5c0-5.6-4.4-10.3-10.1-10.6l-16.4-.7c-5.9-.3-10.8 4.3-11.1 10.1l-.7 15.6c-13.5 1.9-26.8 6.6-38.8 14l-10.5-11.5c-4-4.3-10.7-4.6-15-.7l-12.1 11.1c-2.3 2.1-3.4 5-3.4 7.8 0 2.6.9 5.1 2.8 7.2l10.5 11.5c-8.5 11.3-14.3 24.1-17.4 37.4l-15.6-.7c-5.9-.3-10.8 4.3-11.1 10.1l-.7 16.4v.5c0 5.6 4.4 10.3 10.1 10.6l15.7.7c2 13.5 6.7 26.7 14.2 38.6l-11.7 10.7c-2.3 2.1-3.4 5-3.4 7.8 0 2.6.9 5.1 2.8 7.2l11.1 12.1c4 4.3 10.7 4.6 15 .7l11.8-10.8c11.3 8.4 24 14.1 37.2 17.2l-.7 16.1v.5c0 5.6 4.4 10.3 10.1 10.6l16.4.7c5.9.3 10.8-4.3 11.1-10.1l.7-16.1c13.4-1.9 26.4-6.6 38.3-14l10.9 12c4 4.3 10.7 4.6 15 .7l12.1-11.1c-2.3-2.1-3.4-5-3.4-7.8 0-2.6.9-5.1 2.8-7.2l-10.9-12c8.4-11.2 14.2-23.8 17.3-37l16.1.7c5.9.3 10.8-4.3 11.1-10.1l.7-16.4v-.5c0-5.6-4.4-10.3-10.1-10.6l-16.1-.7c-1.9-13.4-6.5-26.6-13.9-38.5l11.8-10.8c2.3-2.1 3.4-5 3.4-7.8 0-2.6-.9-5.1-2.8-7.2l-11.1-12.1c-4-4.3-10.7-4.6-15-.7l-11.7 10.7c-11.3-8.5-24-14.4-37.2-17.5zm16.5 77.1c9.6 10.5 14.3 23.7 14.3 36.9 0 14.8-6 29.6-17.8 40.3-22.3 20.4-56.8 18.8-77.2-3.4-9.6-10.5-14.3-23.7-14.3-36.9 0-14.8 6-29.6 17.8-40.3 22.2-20.4 56.8-18.9 77.2 3.4m-197.9-212.2c-44.7 0-81 36.3-81 81s36.3 81 81 81 81-36.3 81-81-36.3-81-81-81m0 54.6c-14.6 0-26.4 11.8-26.4 26.4 0 6.7-5.4 12.1-12.1 12.1s-12.1-5.4-12.1-12.1c0-27.9 22.7-50.6 50.6-50.6 6.7 0 12.1 5.4 12.1 12.1s-5.5 12.1-12.1 12.1"/>
</svg> %s',
                __('General Settings', 'multi-location-product-and-inventory-management-pro')
            ),
            function () {
                echo '<p>' . esc_html__('Configure general settings for location-based stock and price management.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            },
            'lwp-general-settings'
        );

        // Add "Enable Location Stock" field
        add_settings_field(
            'enable_location_stock',
            __('Enable Location Stock', 'multi-location-product-and-inventory-management-pro'),
            function () {
                $this->render_advance_checkbox("enable_location_stock", __("Enable or disable location-specific stock management.", 'multi-location-product-and-inventory-management-pro'));
            },
            'lwp-general-settings',
            'location_stock_general_section'
        );

        // Add "Enable Location Pricing" field
        add_settings_field(
            'enable_location_price',
            __('Enable Location Pricing', 'multi-location-product-and-inventory-management-pro'),
            function () {
                $this->render_advance_checkbox("enable_location_price", __("Enable or disable location-specific pricing.", 'multi-location-product-and-inventory-management-pro'));
            },
            'lwp-general-settings',
            'location_stock_general_section'
        );

        // Add "Enable Location Wise Currency" field
        add_settings_field(
            'location_wise_currency',
            __('Enable Location Wise Currency', 'multi-location-product-and-inventory-management-pro'),
            function () {
                $is_manual_mode = $this->is_manual_assignment_strict_mode();
                $options = $this->get_display_options();
                $is_location_price_enabled = isset($options['enable_location_price']) && $options['enable_location_price'] === 'on';
                $disabled = $is_manual_mode || !$is_location_price_enabled;

                if ($is_manual_mode) {
                    $hidden_currency_value = $is_location_price_enabled
                        ? ($options['location_wise_currency'] ?? 'off')
                        : 'off';
                    $this->render_manual_hidden_input('location_wise_currency', $hidden_currency_value);
                }
                $message = __('Enable location-specific currency and currency position based on selected location. Requires Enable Location Pricing. This option is automatically turned off when Enable Location Pricing is disabled. When enabled, mixed-location cart and location change in cart are disabled.', 'multi-location-product-and-inventory-management-pro');
                $message = $this->append_manual_disabled_note($message);
                $this->render_advance_checkbox("location_wise_currency", $message, $disabled);
?>
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    const locationPriceInput = document.querySelector('input[name="mulopimfwc_display_options[enable_location_price]"]');
                    const locationCurrencyInput = document.querySelector('input[name="mulopimfwc_display_options[location_wise_currency]"]');
                    if (!locationPriceInput || !locationCurrencyInput) {
                        return;
                    }

                    const isManualMode = <?php echo $is_manual_mode ? 'true' : 'false'; ?>;
                    const currencySwitch = locationCurrencyInput.closest('.mulopimfwc_switch');

                    function syncLocationCurrencyState() {
                        const shouldDisable = isManualMode || !locationPriceInput.checked;
                        locationCurrencyInput.disabled = shouldDisable;
                        if (shouldDisable) {
                            locationCurrencyInput.checked = false;
                        }
                        locationCurrencyInput.classList.toggle('mulopimfwc-setting-disabled', shouldDisable);

                        if (currencySwitch) {
                            currencySwitch.classList.toggle('mulopimfwc-setting-disabled', shouldDisable);
                        }
                    }

                    syncLocationCurrencyState();
                    locationPriceInput.addEventListener('change', syncLocationCurrencyState);
                });
            </script>
        <?php
            },
            'lwp-general-settings',
            'location_stock_general_section'
        );

        // Add "Enable Location Backorder" field
        add_settings_field(
            'enable_location_backorder',
            __('Enable Location Backorder', 'multi-location-product-and-inventory-management-pro'),
            function () {
                $this->render_advance_checkbox("enable_location_backorder", __("Enable or disable location-specific backorder management.", 'multi-location-product-and-inventory-management-pro'));
            },
            'lwp-general-settings',
            'location_stock_general_section'
        );

        // add "Enable Information for location"
        add_settings_field(
            'enable_location_information',
            __('Enable Location Information', 'multi-location-product-and-inventory-management-pro'),
            function () {
                $is_manual_mode = $this->is_manual_assignment_strict_mode();
                $options = $this->get_display_options();
                if ($is_manual_mode) {
                    $this->render_manual_hidden_input('enable_location_information', $options['enable_location_information'] ?? 'off');
                }
                $message = $this->append_manual_disabled_note(__("Enable or disable location-specific information management.", 'multi-location-product-and-inventory-management-pro'));
                $this->render_advance_checkbox("enable_location_information", $message, $is_manual_mode);
            },
            'lwp-general-settings',
            'location_stock_general_section'
        );

        // Add Branding Settings Section (in General tab)
        add_settings_section(
            'mulopimfwc_branding_settings_section',
            sprintf('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" style="margin-right:6px;vertical-align:middle;background-color:#fef3c7;padding:10px;border-radius:6px">
  <path fill="#f59e0b" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
</svg> %s', __('Branding & Styling Settings', 'multi-location-product-and-inventory-management-pro')),

            [$this, 'branding_settings_section_callback'],
            'multi-location-product-and-inventory-management-pro'
        );

        // Popup/Modal Colors
        add_settings_field(
            'branding_popup_background',
            __('Popup Background Color', 'multi-location-product-and-inventory-management-pro'),
            [$this, 'branding_popup_background_field_callback'],
            'multi-location-product-and-inventory-management-pro',
            'mulopimfwc_branding_settings_section'
        );

        add_settings_field(
            'branding_popup_text',
            __('Popup Text Color', 'multi-location-product-and-inventory-management-pro'),
            [$this, 'branding_popup_text_field_callback'],
            'multi-location-product-and-inventory-management-pro',
            'mulopimfwc_branding_settings_section'
        );

        add_settings_field(
            'branding_popup_overlay',
            __('Popup Overlay Background', 'multi-location-product-and-inventory-management-pro'),
            [$this, 'branding_popup_overlay_field_callback'],
            'multi-location-product-and-inventory-management-pro',
            'mulopimfwc_branding_settings_section'
        );

        // Primary & Secondary Colors
        add_settings_field(
            'branding_primary_color',
            __('Primary Color', 'multi-location-product-and-inventory-management-pro'),
            [$this, 'branding_primary_color_field_callback'],
            'multi-location-product-and-inventory-management-pro',
            'mulopimfwc_branding_settings_section'
        );

        add_settings_field(
            'branding_secondary_color',
            __('Secondary Color', 'multi-location-product-and-inventory-management-pro'),
            [$this, 'branding_secondary_color_field_callback'],
            'multi-location-product-and-inventory-management-pro',
            'mulopimfwc_branding_settings_section'
        );

        add_settings_field(
            'branding_border_color',
            __('Border Color', 'multi-location-product-and-inventory-management-pro'),
            [$this, 'branding_border_color_field_callback'],
            'multi-location-product-and-inventory-management-pro',
            'mulopimfwc_branding_settings_section'
        );

        // Button Colors
        add_settings_field(
            'branding_button_text',
            __('Button Text Color', 'multi-location-product-and-inventory-management-pro'),
            [$this, 'branding_button_text_field_callback'],
            'multi-location-product-and-inventory-management-pro',
            'mulopimfwc_branding_settings_section'
        );

        // Typography
        add_settings_field(
            'branding_font_size',
            __('Base Font Size', 'multi-location-product-and-inventory-management-pro'),
            [$this, 'branding_font_size_field_callback'],
            'multi-location-product-and-inventory-management-pro',
            'mulopimfwc_branding_settings_section'
        );

        add_settings_field(
            'branding_font_family',
            __('Font Family', 'multi-location-product-and-inventory-management-pro'),
            [$this, 'branding_font_family_field_callback'],
            'multi-location-product-and-inventory-management-pro',
            'mulopimfwc_branding_settings_section'
        );

        // Box Shadow
        add_settings_field(
            'branding_box_shadow',
            __('Box Shadow', 'multi-location-product-and-inventory-management-pro'),
            [$this, 'branding_box_shadow_field_callback'],
            'multi-location-product-and-inventory-management-pro',
            'mulopimfwc_branding_settings_section'
        );

        // Border Radius
        add_settings_field(
            'branding_border_radius',
            __('Border Radius', 'multi-location-product-and-inventory-management-pro'),
            [$this, 'branding_border_radius_field_callback'],
            'multi-location-product-and-inventory-management-pro',
            'mulopimfwc_branding_settings_section'
        );

        add_settings_field(
            'enable_location_by_user_role',
            __('Enable Location by User Role', 'multi-location-product-and-inventory-management-pro'),
            function () {
                $roles = wp_roles()->roles;
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['enable_location_by_user_role' => []]);
                $selected_roles = isset($options['enable_location_by_user_role']) ? $options['enable_location_by_user_role'] : [];
                foreach ($roles as $role_key => $role) {
                    $checked = in_array($role_key, $selected_roles) ? 'checked' : '';
                    if ($role_key === 'customer') {
                        continue;
                    } else if ($role_key === 'mulopimfwc_location_manager' && !mulopimfwc_premium_feature()) {
                        continue;
                    }
                    echo "<label><input type='checkbox' name='mulopimfwc_display_options[enable_location_by_user_role][]' value='" . esc_attr($role_key) . "' " . esc_attr($checked) . "> " . esc_html($role['name'] ?? $role_key) . "</label><br>";
                }
        ?>
            <p class="description"><?php echo esc_html__('Select user roles for which location-specific information is enabled.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            <?php
            },
            'lwp-general-settings',
            'location_stock_general_section'
        );

        add_settings_section(
            'popup_shortcode_manage_section',
            sprintf(
                '<svg xmlns="http://www.w3.org/2000/svg" 
     viewBox="0 0 512 512" 
     width="16" height="16" 
     style="margin-right:6px;vertical-align:middle;background-color:#dbeafe;padding:10px;border-radius:6px">
  <path fill="#2563eb" d="M432 64H208c-8.8 0-16 7.2-16 16v16h-64V80c0-44.2 35.8-80 80-80h224c44.2 0 80 35.8 80 80v224c0 44.2-35.8 80-80 80h-16v-64h16c8.8 0 16-7.2 16-16V80c0-8.8-7.2-16-16-16M0 192c0-35.3 28.7-64 64-64h256c35.3 0 64 28.7 64 64v256c0 35.3-28.7 64-64 64H64c-35.3 0-64-28.7-64-64zm64 32c0 17.7 14.3 32 32 32h192c17.7 0 32-14.3 32-32s-14.3-32-32-32H96c-17.7 0-32 14.3-32 32"/>
</svg> %s',
                __('Popup Settings', 'multi-location-product-and-inventory-management-pro'),
            ),
            function () {
                echo '<p>' . esc_html__('Configure Popup settings for location-based stock and price management.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            },
            'location-popup-shortcode-settings'
        );

        add_settings_field(
            'enable_popup',
            __('Enable Popup', 'multi-location-product-and-inventory-management-pro'),
            function () {
                if (mulopimfwc_premium_feature()) {
                    $is_manual_mode = $this->is_manual_assignment_strict_mode();
                    $message = $this->append_manual_disabled_note(__("Enable or disable popup management.", 'multi-location-product-and-inventory-management-pro'));
                    $options = $this->get_display_options();
                    if ($is_manual_mode) {
                        $this->render_manual_hidden_input('enable_popup', $options['enable_popup'] ?? 'off');
                    }
                    $this->render_advance_checkbox('enable_popup', $message, $is_manual_mode);
                } else {
                    $this->render_advance_checkbox('pro', __("Enable or disable popup management.", 'multi-location-product-and-inventory-management-pro'), true, false);
                }
            },
            'location-popup-shortcode-settings',
            'popup_shortcode_manage_section'
        );

        // add template selection field
        add_settings_field(
            'template_selection',
            __('Template Selection', 'multi-location-product-and-inventory-management-pro'),
            function () {
                $options = $this->get_display_options();
                $is_manual_mode = $this->is_manual_assignment_strict_mode();
                $current_template = isset($options['template_selection']) ? $options['template_selection'] : 'default';
                if (mulopimfwc_premium_feature()) {
                    if ($is_manual_mode) {
                        $this->render_manual_hidden_input('template_selection', $current_template);
                    }
                    echo '<select id="template_selection" name="mulopimfwc_display_options[template_selection]"' . disabled( $is_manual_mode, true, false ) . '>';
                    $template_options = [
                        "default" => __("Default", 'multi-location-product-and-inventory-management-pro'),
                        "modern" => __("Modern", 'multi-location-product-and-inventory-management-pro'),
                        "modern-simple" => __("Modern Simple", 'multi-location-product-and-inventory-management-pro'),
                        "classic" => __("Classic", 'multi-location-product-and-inventory-management-pro'),
                        "tabs" => __("Tabs", 'multi-location-product-and-inventory-management-pro'),
                        "compact" => __("Compact", 'multi-location-product-and-inventory-management-pro'),
                        "grid" => __("Grid", 'multi-location-product-and-inventory-management-pro'),
                    ];
                    foreach ($template_options as $value => $label) {
                        echo "<option value='" . esc_attr($value) . "' " . selected( $current_template, $value, false ) . ">" . esc_html($label) . "</option>";
                    }
                    echo '</select>';
                    $description = $this->append_manual_disabled_note(__("Select the template for location selector.", 'multi-location-product-and-inventory-management-pro'));
                    echo "<p class='description' style='max-width: 800px;'>" . esc_html($description) . "</p>";
                } else {
                    $this->render_advance_select('pro', __("Select the template for location selector.", 'multi-location-product-and-inventory-management-pro'), [
                        "default" => "Default",
                        "modern" => "Modern",
                        "modern-simple" => "Modern Simple",
                        "classic" => "Classic",
                        "tabs" => "Tabs",
                        "compact" => "Compact",
                        "grid" => "Grid"
                    ], true, false);
                }
            },
            'location-popup-shortcode-settings',
            'popup_shortcode_manage_section'
        );

        add_settings_field(
            'use_select2',
            __('Use Select2', 'multi-location-product-and-inventory-management-pro'),
            function () {
                $options = $this->get_display_options();
                $is_manual_mode = $this->is_manual_assignment_strict_mode();
                $current_template = isset($options['template_selection']) ? $options['template_selection'] : 'default';
                $is_default = ($current_template === 'default');
                $style_attr = $is_default ? '' : 'display: none;';
                if (mulopimfwc_premium_feature()) {
                    echo '<div data-field="use_select2" class="mulopimfwc-default-template-only"' . ( $style_attr ? ' style="' . esc_attr( $style_attr ) . '"' : '' ) . '>';
                    if ($is_manual_mode) {
                        $this->render_manual_hidden_input('use_select2', $options['use_select2'] ?? 'off');
                    }
                    $message = $this->append_manual_disabled_note(__("Use select2 instead of normal select", 'multi-location-product-and-inventory-management-pro'));
                    $this->render_advance_checkbox("use_select2", $message, $is_manual_mode);
                    echo '</div>';
                } else {
                    echo '<div data-field="use_select2" class="mulopimfwc-default-template-only"' . ( $style_attr ? ' style="' . esc_attr( $style_attr ) . '"' : '' ) . '>';
                    $this->render_advance_checkbox('pro', __("Use select2 instead of normal select", 'multi-location-product-and-inventory-management-pro'), true, false);
                    echo '</div>';
                }
            },
            'location-popup-shortcode-settings',
            'popup_shortcode_manage_section'
        );

        add_settings_field(
            'title_show_popup',
            __('Title Show in Popup', 'multi-location-product-and-inventory-management-pro'),
            function () {
                if (mulopimfwc_premium_feature()) {
                    $is_manual_mode = $this->is_manual_assignment_strict_mode();
                    $options = $this->get_display_options();
                    if ($is_manual_mode) {
                        $this->render_manual_hidden_input('title_show_popup', $options['title_show_popup'] ?? 'off');
                    }
                    $message = $this->append_manual_disabled_note(__("Show title in popup modal", 'multi-location-product-and-inventory-management-pro'));
                    $this->render_advance_checkbox("title_show_popup", $message, $is_manual_mode);
                } else {
                    $this->render_advance_checkbox('pro', __("Show title in popup modal", 'multi-location-product-and-inventory-management-pro'), true, false);
                }
            },
            'location-popup-shortcode-settings',
            'popup_shortcode_manage_section'
        );


        add_settings_field(
            'herichical',
            __('Hierarchical Option', 'multi-location-product-and-inventory-management-pro'),
            function () {
                $options = $this->get_display_options();
                $is_manual_mode = $this->is_manual_assignment_strict_mode();
                $current_template = isset($options['template_selection']) ? $options['template_selection'] : 'default';
                $is_default = ($current_template === 'default');
                $style_attr = $is_default ? '' : 'display: none;';
                $value = isset($options['herichical']) ? $options['herichical'] : 'off';
                echo '<div data-field="herichical" class="mulopimfwc-default-template-only"' . ( $style_attr ? ' style="' . esc_attr( $style_attr ) . '"' : '' ) . '>';
                if (mulopimfwc_premium_feature()) {
                    if ($is_manual_mode) {
                        $this->render_manual_hidden_input('herichical', $value);
                    }
            ?>
                <select id="herichical" name="mulopimfwc_display_options[herichical]" <?php disabled( $is_manual_mode ); ?>>
                    <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html__('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html__('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="seperately" <?php selected($value, 'seperately'); ?>><?php echo esc_html__('Seperately', 'multi-location-product-and-inventory-management-pro'); ?></option>
                </select>
                <?php if ($is_manual_mode) : ?>
                    <p class="description"><?php echo esc_html__('Disabled while Manual or Inventory-Based assignment is enabled without optional selection.', 'multi-location-product-and-inventory-management-pro'); ?></p>
                <?php endif; ?>
            <?php } else { ?>
                <label class="mulopimfwc_pro_only">
                    <select disabled id="herichical" name="_pro[pro]">
                        <option value="on"><?php echo esc_html__('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="off" selected><?php echo esc_html__('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="seperately"><?php echo esc_html__('Seperately', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    </select>
                </label>
            <?php }
                echo '</div>';
            },
            'location-popup-shortcode-settings',
            'popup_shortcode_manage_section'
        );

        add_settings_field(
            'show_count',
            __('Show Count', 'multi-location-product-and-inventory-management-pro'),
            function () {
                $options = $this->get_display_options();
                $is_manual_mode = $this->is_manual_assignment_strict_mode();
                $current_template = isset($options['template_selection']) ? $options['template_selection'] : 'default';
                $is_default = ($current_template === 'default');
                $style_attr = $is_default ? '' : 'display: none;';
                echo '<div data-field="show_count" class="mulopimfwc-default-template-only"' . ( $style_attr ? ' style="' . esc_attr( $style_attr ) . '"' : '' ) . '>';
                if (mulopimfwc_premium_feature()) {
                    if ($is_manual_mode) {
                        $this->render_manual_hidden_input('show_count', $options['show_count'] ?? 'off');
                    }
                    $message = $this->append_manual_disabled_note(__("Show count in popup", 'multi-location-product-and-inventory-management-pro'));
                    $this->render_advance_checkbox("show_count", $message, $is_manual_mode);
                } else {
                    $this->render_advance_checkbox('pro', __("Show count in popup", 'multi-location-product-and-inventory-management-pro'), true, false);
                }
                echo '</div>';
            },
            'location-popup-shortcode-settings',
            'popup_shortcode_manage_section'
        );

        add_settings_field(
            'show_popup_admin',
            __('Show Popup for Admins', 'multi-location-product-and-inventory-management-pro'),
            function () {
                if (mulopimfwc_premium_feature()) {
                    $is_manual_mode = $this->is_manual_assignment_strict_mode();
                    $options = $this->get_display_options();
                    if ($is_manual_mode) {
                        $this->render_manual_hidden_input('show_popup_admin', $options['show_popup_admin'] ?? 'off');
                    }
                    $message = $this->append_manual_disabled_note(__("Show popup for admin users", 'multi-location-product-and-inventory-management-pro'));
                    $this->render_advance_checkbox("show_popup_admin", $message, $is_manual_mode);
                } else {
                    $this->render_advance_checkbox('pro', __("Show popup for admin users", 'multi-location-product-and-inventory-management-pro'), true, false);
                }
            },
            'location-popup-shortcode-settings',
            'popup_shortcode_manage_section'
        );

        add_settings_field(
            'mulopimfwc_popup_custom_css',
            __('Popup Custom Css', 'multi-location-product-and-inventory-management-pro'),
            function () {
                $options = $this->get_display_options();
                $mulopimfwc_popup_custom_css = isset($options['mulopimfwc_popup_custom_css']) ? $options['mulopimfwc_popup_custom_css'] : null;
                $is_manual_mode = $this->is_manual_assignment_strict_mode();

                if (mulopimfwc_premium_feature()) {
                    if ($is_manual_mode) {
                        $this->render_manual_hidden_input('mulopimfwc_popup_custom_css', trim($mulopimfwc_popup_custom_css ?? ''));
                    }
            ?>
                <textarea style="height: 10rem;" name="mulopimfwc_display_options[mulopimfwc_popup_custom_css]" class="regular-text" placeholder="div#lwp-store-selector-modal{}" <?php disabled( $is_manual_mode ); ?>><?php echo esc_attr(trim($mulopimfwc_popup_custom_css ?? '')); ?>
            </textarea>
                <?php if ($is_manual_mode) : ?>
                    <p class="description"><?php echo esc_html__('Disabled while Manual or Inventory-Based assignment is enabled without optional selection.', 'multi-location-product-and-inventory-management-pro'); ?></p>
                <?php endif; ?>
            <?php } else { ?>
                <label class="mulopimfwc_pro_only">
                    <textarea disabled style="height: 10rem;" name="_pro[pro]" class="regular-text" placeholder="div#lwp-store-selector-modal{}"></textarea>
                </label>
            <?php }
            },
            'location-popup-shortcode-settings',
            'popup_shortcode_manage_section'
        );

        // Add new Inventory Management section
        add_settings_section(
            'mulopimfwc_inventory_management_section',
            sprintf(
                '<svg version="1.2" 
     baseProfile="tiny" 
     xmlns="http://www.w3.org/2000/svg" 
     viewBox="0 0 256 230" 
     xml:space="preserve"
     width="20" height="20" 
     style="margin-right:6px;vertical-align:middle;background-color:#dbeafe;padding:10px;border-radius:6px">
  <path fill="#2563eb" d="M61.2 106h37.4v31.2H61.2zm0 72.7h37.4v-31.2H61.2zm0 41.4h37.4v-31.2H61.2zm48.5-41.4H147v-31.2h-37.4v31.2zm0 41.4H147v-31.2h-37.4v31.2zm48.5-31.2v31.2h37.4v-31.2zM255 67.2 128.3 7.6 1.7 67.4l7.9 16.5 16.1-7.7v144h18.2V75.6h169v144.8h18.2v-144l16.1 7.5z"/>
</svg> %s',
                __('Inventory Management', 'multi-location-product-and-inventory-management-pro')
            ),
            function () {
                echo '<p>' . esc_html__('Configure how inventory is managed across multiple locations.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            },
            'location-inventory-settings'
        );

        // Add "Inventory Sync Mode" field
        add_settings_field(
            'inventory_sync_mode',
            __('Inventory Sync Mode', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['inventory_sync_mode' => 'centralized']);
                $value = isset($options['inventory_sync_mode']) ? $options['inventory_sync_mode'] : 'centralized';
            ?>
            <select name="mulopimfwc_display_options[inventory_sync_mode]">
                <option value="independent" <?php selected($value, 'independent'); ?>><?php echo esc_html_e('Independent (Each location manages its own inventory)', 'multi-location-product-and-inventory-management-pro'); ?></option>
                <option value="centralized" <?php selected($value, 'centralized'); ?>><?php echo esc_html_e('Centralized (Main inventory with location allocations)', 'multi-location-product-and-inventory-management-pro'); ?></option>
                <option value="synchronized" <?php selected($value, 'synchronized'); ?>><?php echo esc_html_e('Synchronized (Changes in one location affect all)', 'multi-location-product-and-inventory-management-pro'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Choose how inventory is managed across multiple locations.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            <?php
            },
            'location-inventory-settings',
            'mulopimfwc_inventory_management_section'
        );

        // Add "Low Stock Threshold" field (number input)
        add_settings_field(
            'low_stock_threshold',
            __('Low Stock Threshold', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['low_stock_threshold' => 5]);
                $value = isset($options['low_stock_threshold']) ? $options['low_stock_threshold'] : 5;

                if (mulopimfwc_premium_feature()) {
            ?>
                <input type="number" name="mulopimfwc_display_options[low_stock_threshold]" value="<?php echo esc_attr($value); ?>" min="0" step="1" class="small-text">
            <?php } else { ?>
                <label class="mulopimfwc_pro_only">
                    <input disabled type="number" name="_pro[pro]" value="5" min="0" step="1" class="small-text">
                </label>
            <?php } ?>
            <p class="description"><?php echo esc_html_e('Trigger low-stock alert when location stock is less than or equal to this number (units).', 'multi-location-product-and-inventory-management-pro'); ?></p>
        <?php
            },
            'location-inventory-settings',
            'mulopimfwc_inventory_management_section'
        );

        // Add "Low Stock Notification Recipients" field
        add_settings_field(
            'low_stock_notification_recipients',
            __('Low Stock Notification Recipients', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['low_stock_notification_recipients' => 'admin']);
                $value = isset($options['low_stock_notification_recipients']) ? $options['low_stock_notification_recipients'] : 'admin';
        ?>
            <select name="mulopimfwc_display_options[low_stock_notification_recipients]">
                <option value="admin" <?php selected($value, 'admin'); ?>><?php echo esc_html_e('Admin Only', 'multi-location-product-and-inventory-management-pro'); ?></option>
                <option value="location_manager" <?php selected($value, 'location_manager'); ?>><?php echo esc_html_e('Location Manager', 'multi-location-product-and-inventory-management-pro'); ?></option>
                <option value="both" <?php selected($value, 'both'); ?>><?php echo esc_html_e('Both Admin and Location Manager', 'multi-location-product-and-inventory-management-pro'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('Who should receive low stock notifications.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            <?php
            },
            'location-inventory-settings',
            'mulopimfwc_inventory_management_section'
        );

        // Add "Out of Stock Threshold" field
        add_settings_field(
            'out_of_stock_threshold',
            __('Out of Stock Threshold', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['out_of_stock_threshold' => 0]);
                $value = isset($options['out_of_stock_threshold']) ? $options['out_of_stock_threshold'] : 0;
                if (mulopimfwc_premium_feature()) {
            ?>
                <input type="number" name="mulopimfwc_display_options[out_of_stock_threshold]" value="<?php echo esc_attr($value); ?>" min="0" step="1" class="small-text">
            <?php } else { ?>
                <label class="mulopimfwc_pro_only">
                    <input disabled type="number" name="_pro[out_of_stock_threshold]" value="0" min="0" step="1" class="small-text">
                </label>
            <?php } ?>
            <p class="description"><?php echo esc_html_e('Trigger out-of-stock alert when location stock is less than or equal to this number (units).', 'multi-location-product-and-inventory-management-pro'); ?></p>
            <?php
            },
            'location-inventory-settings',
            'mulopimfwc_inventory_management_section'
        );
        // Add "Product Shipping" section
        add_settings_section(
            'mulopimfwc_shipping_section',
            sprintf(
                '<svg viewBox="0 0 14 14" 
     aria-hidden="true" 
     xmlns="http://www.w3.org/2000/svg"
     width="20" height="20" 
     style="margin-right:6px;vertical-align:middle;background-color:#dbeafe;padding:10px;border-radius:6px">
  <path fill="#2563eb" d="M13 7.118v-.007a.275.275 0 0 0-.277-.27h-.072l-1.12-2.166a.28.28 0 0 0-.246-.148H9.373l.073-.579a.8.8 0 0 0-.202-.614.8.8 0 0 0-.59-.256H2.716a.293.293 0 0 0-.29.264l-.06.477H5.75c.22 0 .393.178.387.398a.41.41 0 0 1-.408.397H4.6v.002H1.29a.29.29 0 0 0-.29.281.27.27 0 0 0 .274.281h4.385a.41.41 0 0 1 .35.421.44.44 0 0 1-.433.426H2.552a.293.293 0 0 0-.291.284.275.275 0 0 0 .277.284h2.987a.41.41 0 0 1 .357.421.44.44 0 0 1-.438.426H1.73a.293.293 0 0 0-.292.285.275.275 0 0 0 .277.284h.248l-.097 1.017c-.02.231.05.45.201.615a.8.8 0 0 0 .591.255h.215a1.24 1.24 0 0 0 1.226 1.026c.618 0 1.147-.442 1.28-1.026h2.675a.9.9 0 0 0 .582-.218.8.8 0 0 0 .555.218h.044a1.24 1.24 0 0 0 1.226 1.026c.618 0 1.147-.442 1.28-1.026h.176c.46 0 .868-.374.91-.834L13 7.145v-.02zm-8.887 3.236a.69.69 0 0 1-.692-.71.734.734 0 0 1 .729-.71c.391 0 .702.318.691.71a.734.734 0 0 1-.728.71m6.362 0a.69.69 0 0 1-.692-.71.734.734 0 0 1 .729-.71c.391 0 .701.318.691.71a.734.734 0 0 1-.728.71m1.785-1.328a.34.34 0 0 1-.33.302h-.19a1.24 1.24 0 0 0-1.213-.962c-.596 0-1.109.41-1.264.962h-.059a.24.24 0 0 1-.181-.076.24.24 0 0 1-.06-.19l.358-3.967h.823l-.13 1.444c-.022.231.05.45.2.615s.36.255.592.255h1.6z"/>
</svg> %s',
                __('Location-Based Shipping', 'multi-location-product-and-inventory-management-pro'),
            ),
            function () {
                echo '<p>' . esc_html__('Configure shipping options based on product locations.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            },
            'lwp-location-shipping-settings'
        );

        // Add "Enable Location-Based Shipping" field
        add_settings_field(
            'enable_location_shipping',
            __('Enable Location-Based Shipping', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['enable_location_shipping' => 'off']);
                $value = isset($options['enable_location_shipping']) ? $options['enable_location_shipping'] : 'off';
                $is_manual_mode = $this->is_manual_assignment_strict_mode();
                if ($is_manual_mode) {
                    $this->render_manual_hidden_input('enable_location_shipping', $value);
                }

                global $MULOPIMFWC_Admin;

                $zones_methods = $MULOPIMFWC_Admin->get_shipping_methods_grouped_by_zone();
                if (mulopimfwc_premium_feature()) {
            ?>
                <select <?php echo disabled($zones_methods && count($zones_methods) <= 1); ?><?php echo $is_manual_mode ? ' disabled' : ''; ?> name="mulopimfwc_display_options[enable_location_shipping]">
                    <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                </select>
            <?php } else { ?>
                <label class="mulopimfwc_pro_only">
                    <select disabled name="_pro[enable_location_shipping]">
                        <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    </select>
                </label>
            <?php } ?>
            <p class="description"><?php echo esc_html($this->append_manual_disabled_note(__('Enable different shipping options based on product location.', 'multi-location-product-and-inventory-management-pro'))); ?></p>

            <?php


                if ($zones_methods && count($zones_methods) <= 1) {
                    echo '<div class="lwp-notice lwp-notice-warning" style="background-color: #fff3cd; border: 1px solid #ffeeba; border-left: 4px solid #ffc107; border-radius: 4px; padding: 12px 16px; margin: 10px 0;">
                        <div style="display: flex; align-items: start; gap: 12px;">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" style="flex-shrink: 0; margin-top: 2px;">
                                <path fill="#856404" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z"/>
                            </svg>
                            <div>
                                <p style="margin: 0; color: #856404;">
                                    ' . esc_html__('Important: Ensure that shipping methods are properly configured in WooCommerce settings for location-based shipping to function correctly.', 'multi-location-product-and-inventory-management-pro') . '
                                </p>
                            </div>
                        </div>
                    </div>';
                }
            },
            'lwp-location-shipping-settings',
            'mulopimfwc_shipping_section'
        );

        // Add "Shipping Calculation Method" field
        add_settings_field(
            'shipping_calculation_method',
            __('Shipping Calculation Method', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['shipping_calculation_method' => 'per_location']);
                $value = isset($options['shipping_calculation_method']) ? $options['shipping_calculation_method'] : 'per_location';

                if (mulopimfwc_premium_feature()) {
            ?>
                <select name="mulopimfwc_display_options[shipping_calculation_method]" id="mulopimfwc_shipping_calculation_method">
                    <option value="per_location" <?php selected($value, 'per_location'); ?>><?php echo esc_html_e('Per Location (Each location has its own rates)', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="nearest_with_transfer" <?php selected($value, 'nearest_with_transfer'); ?>><?php echo esc_html_e('Nearest with inter-hub transfer cost', 'multi-location-product-and-inventory-management-pro'); ?></option>
                </select>
            <?php } else { ?>
                <label class="mulopimfwc_pro_only">
                    <select disabled name="_pro[shipping_calculation_method]">
                        <option value="per_location" <?php selected($value, 'per_location'); ?>><?php echo esc_html_e('Per Location (Each location has its own rates)', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="nearest_with_transfer" <?php selected($value, 'nearest_with_transfer'); ?>><?php echo esc_html_e('Nearest with inter-hub transfer cost', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    </select>
                </label>
            <?php } ?>
            <p class="description"><?php echo esc_html_e('How shipping rates are calculated for multi-location orders.', 'multi-location-product-and-inventory-management-pro'); ?></p>
        <?php
            },
            'lwp-location-shipping-settings',
            'mulopimfwc_shipping_section'
        );

        // Location-to-Location Transfer Cost Matrix
        add_settings_section(
            'inter_location_transfer_costs',
            __('Inter-Location Transfer Costs', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', []);
                $transfer_costs = isset($options['inter_location_transfer_costs']) ? $options['inter_location_transfer_costs'] : [];

                // Get all locations
                $locations = get_terms([
                    'taxonomy' => 'mulopimfwc_store_location',
                    'hide_empty' => false,
                    'orderby' => 'name',
                    'order' => 'ASC'
                ]);

                if (empty($locations) || is_wp_error($locations)) {
                    echo '<p class="description" style="color: #d97706;">' . esc_html__('Please create store locations first to configure transfer costs.', 'multi-location-product-and-inventory-management-pro') . '</p>';
                    return;
                }
        ?>
            <div class="mulopimfwc-transfer-cost-matrix <?php echo mulopimfwc_premium_feature() ? '' : 'mulopimfwc_pro_only'; ?>">
                <p class="description" style="margin-bottom: 15px;">
                    <?php echo esc_html__('Configure shipping costs for transferring products from one location to another. Leave empty or 0 for no transfer cost.', 'multi-location-product-and-inventory-management-pro'); ?>
                </p>
                <div style="max-width: 100%; overflow-x: auto;">
                    <div class="mulopimfwc-matrix-container" style="overflow-x: auto; background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 15px;">
                        <table class="mulopimfwc-cost-table" style="width: 100%; border-collapse: collapse; min-width: 600px;">
                            <thead>
                                <tr style="background: #f8fafc;">
                                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0; font-weight: 600; color: #475569; min-width: 70px;">
                                        <?php echo esc_html__('From → To', 'multi-location-product-and-inventory-management-pro'); ?>
                                    </th>
                                    <?php foreach ($locations as $to_location): ?>
                                        <th style="padding: 12px; text-align: center; border-bottom: 2px solid #e2e8f0; font-weight: 600; color: #475569; min-width: 120px;">
                                            <?php echo esc_html($to_location->name); ?>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($locations as $from_location): ?>
                                    <tr style="border-bottom: 1px solid #e2e8f0;">
                                        <td style="padding: 12px; font-weight: 600; color: #475569; background: #f8fafc;">
                                            <?php echo esc_html($from_location->name); ?>
                                        </td>
                                        <?php foreach ($locations as $to_location): ?>
                                            <td style="padding: 8px; text-align: center;">
                                                <?php if ($from_location->term_id === $to_location->term_id): ?>
                                                    <span style="color: #94a3b8; font-size: 12px; font-weight: 500;">
                                                        <?php echo esc_html__('Same Location', 'multi-location-product-and-inventory-management-pro'); ?>
                                                    </span>
                                                <?php else:
                                                    $cost_key = $from_location->slug . '_to_' . $to_location->slug;
                                                    $current_cost = isset($transfer_costs[$cost_key]) ? $transfer_costs[$cost_key] : '';
                                                ?>
                                                    <div style="position: relative;">
                                                        <span style="position: absolute; left: 8px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 14px;">
                                                            <?php echo esc_html(get_woocommerce_currency_symbol()); ?>
                                                        </span>
                                                        <input
                                                            type="number"
                                                            <?php echo mulopimfwc_premium_feature() ? '' : 'disabled'; ?>
                                                            name="<?php echo mulopimfwc_premium_feature() ? 'mulopimfwc_display_options' : '_pro'; ?>[inter_location_transfer_costs][<?php echo esc_attr($cost_key); ?>]"
                                                            value="<?php echo esc_attr($current_cost); ?>"
                                                            min="0"
                                                            step="0.01"
                                                            placeholder="0.00"
                                                            style="width: 100%; padding: 6px 8px 6px 24px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 13px;"
                                                            title="<?php echo esc_attr(sprintf(/* translators: 1: source location name, 2: destination location name */ __('Cost from %1$s to %2$s', 'multi-location-product-and-inventory-management-pro'), $from_location->name, $to_location->name)); ?>">
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mulopimfwc-matrix-info" style="margin-top: 15px; padding: 12px; background: #eff6ff; border-left: 4px solid #3b82f6; border-radius: 4px;">
                    <div style="display: flex; align-items: start; gap: 10px;">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" style="flex-shrink: 0; margin-top: 2px;">
                            <path fill="#3b82f6" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z" />
                        </svg>
                        <div style="flex: 1;">
                            <p style="margin: 0 0 8px 0; font-weight: 600; color: #1e40af;">
                                <?php echo esc_html__('How Transfer Costs Work:', 'multi-location-product-and-inventory-management-pro'); ?>
                            </p>
                            <ul style="margin: 0; padding-left: 20px; color: #475569; font-size: 13px; line-height: 1.6;">
                                <li><?php echo esc_html__('Each cell represents the cost to transfer products FROM one location TO another', 'multi-location-product-and-inventory-management-pro'); ?></li>
                                <li><?php echo esc_html__('Costs can be different in each direction (e.g., Location A → B may differ from B → A)', 'multi-location-product-and-inventory-management-pro'); ?></li>
                                <li><?php echo esc_html__('Leave empty or set to 0 for no transfer cost between specific locations', 'multi-location-product-and-inventory-management-pro'); ?></li>
                                <li><?php echo esc_html__('These costs are added to shipping when products need to be transferred between locations', 'multi-location-product-and-inventory-management-pro'); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="mulopimfwc-quick-actions" style="margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="button" class="button button-secondary mulopimfwc-fill-default" style="font-size: 12px;">
                        <span class="dashicons dashicons-admin-generic" style="margin-top: 3px;"></span>
                        <?php echo esc_html__('Set Default Cost for All', 'multi-location-product-and-inventory-management-pro'); ?>
                    </button>
                    <button type="button" class="button button-secondary mulopimfwc-clear-all" style="font-size: 12px;">
                        <span class="dashicons dashicons-trash" style="margin-top: 3px;"></span>
                        <?php echo esc_html__('Clear All Costs', 'multi-location-product-and-inventory-management-pro'); ?>
                    </button>
                </div>
            </div>

            <style>
                .mulopimfwc-transfer-cost-matrix input[type="number"]:focus {
                    outline: none;
                    border-color: #3b82f6;
                    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
                }

                .mulopimfwc-cost-table tbody tr:hover {
                    background: #f8fafc;
                }

                @media (max-width: 768px) {
                    .mulopimfwc-matrix-container {
                        font-size: 12px;
                    }

                    .mulopimfwc-cost-table th,
                    .mulopimfwc-cost-table td {
                        padding: 8px 4px !important;
                        min-width: 100px !important;
                    }
                }
            </style>

            <script>
                jQuery(document).ready(function($) {
                    // Helper: Enable or disable cost inputs based on shipping calculation method
                    function toggleTransferCostInputs() {
                        var $select = $('#mulopimfwc_shipping_calculation_method');
                        var $inputs = $('.mulopimfwc-cost-table input[type="number"]');
                        if ($select.length && $select.val() !== 'nearest_with_transfer') {
                            $inputs.prop('disabled', true);
                        } else {
                            $inputs.prop('disabled', false);
                        }
                    }

                    // Initial check on page load
                    toggleTransferCostInputs();

                    // Watch for changes to the calculation method dropdown
                    $(document).on('change', '#mulopimfwc_shipping_calculation_method', function() {
                        toggleTransferCostInputs();
                    });

                    // Fill default cost for all empty and enabled fields only
                    $('.mulopimfwc-fill-default').on('click', function() {
                        const defaultCost = prompt('<?php echo esc_js(__('Enter default transfer cost for all locations:', 'multi-location-product-and-inventory-management-pro')); ?>', '0');
                        if (defaultCost !== null && !isNaN(defaultCost)) {
                            $('.mulopimfwc-cost-table input[type="number"]:enabled').each(function() {
                                if (!$(this).val() || $(this).val() === '0') {
                                    $(this).val(parseFloat(defaultCost).toFixed(2));
                                }
                            });
                        }
                    });

                    // Clear all costs (on enabled fields only)
                    $('.mulopimfwc-clear-all').on('click', function() {
                        if (confirm('<?php echo esc_js(__('Are you sure you want to clear all transfer costs?', 'multi-location-product-and-inventory-management-pro')); ?>')) {
                            $('.mulopimfwc-cost-table input[type="number"]:enabled').val('');
                        }
                    });
                });
            </script>
            <?php
            },
            'lwp-location-shipping-settings',
            'mulopimfwc_shipping_section'
        );
        // Add section for Location-wise Payment Methods
        add_settings_section(
            'mulopimfwc_location_payment_section',
            sprintf(
                '<svg xmlns="http://www.w3.org/2000/svg" 
     viewBox="0 0 24 24"
     width="20" height="20" 
     style="margin-right:6px;vertical-align:middle;background-color:#dbeafe;padding:10px;border-radius:6px">
  <path fill="#2563eb" d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2m0 14H4v-6h16zm0-10H4V6h16z"/>
</svg> %s',
                __('Location-wise Payment Methods', 'multi-location-product-and-inventory-management-pro'),
            ),
            function () {
                echo '<p>' . esc_html__('Configure which payment methods are available for each store location.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            },
            'lwp-location-payment-settings'
        );

        // Add "Enable Location-wise Payment" field
        add_settings_field(
            'enable_location_payment_methods',
            __('Enable Location-wise Payment', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['enable_location_payment_methods' => 'off']);
                $value = isset($options['enable_location_payment_methods']) ? $options['enable_location_payment_methods'] : 'off';
                $is_manual_mode = $this->is_manual_assignment_strict_mode();
                if ($is_manual_mode) {
                    $this->render_manual_hidden_input('enable_location_payment_methods', $value);
                }
                global $MULOPIMFWC_Admin;
                $payments = $MULOPIMFWC_Admin->get_payment_method_options();
                if (mulopimfwc_premium_feature()) {
            ?>
                <select <?php echo disabled(empty($payments)); ?><?php echo $is_manual_mode ? ' disabled' : ''; ?> name="mulopimfwc_display_options[enable_location_payment_methods]">
                    <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                </select>
            <?php } else { ?>
                <label class="mulopimfwc_pro_only">
                    <select disabled name="_pro[enable_location_payment_methods]">
                        <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    </select>
                </label>
            <?php } ?>
            <p class="description"><?php echo esc_html($this->append_manual_disabled_note(__('Enable or disable payment method restrictions by location.', 'multi-location-product-and-inventory-management-pro'))); ?></p>
            <?php
                if (empty($payments)) {
                    echo '<div class="lwp-notice lwp-notice-warning" style="background-color: #fff3cd; border: 1px solid #ffeeba; border-left: 4px solid #ffc107; border-radius: 4px; padding: 12px 16px; margin: 10px 0;">
                            <div style="display: flex; align-items: start; gap: 12px;">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" style="flex-shrink: 0; margin-top: 2px;">
                                    <path fill="#856404" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z"/>
                                </svg>
                                <div>
                                    <p style="margin: 0; color: #856404;">
                                        ' . esc_html__('Important: No payment methods are currently enabled in WooCommerce. Please enable at least one payment method for location-wise payment settings to function correctly.', 'multi-location-product-and-inventory-management-pro') . '
                                    </p>
                                </div>
                            </div>
                        </div>';
                }
            },
            'lwp-location-payment-settings',
            'mulopimfwc_location_payment_section'
        );

        // Add section for Location-based Taxes
        add_settings_section(
            'mulopimfwc_location_tax_section',
            sprintf(
                '<svg viewBox="0 0 1024 1024" 
     xmlns="http://www.w3.org/2000/svg"
     width="20" height="20" 
     style="margin-right:6px;vertical-align:middle;background-color:#dbeafe;padding:10px;border-radius:6px">
  <path fill="#2563eb" d="M441.71 414.154c0-23.138-17.983-41.656-39.864-41.656-21.875 0-39.864 18.522-39.864 41.656s17.989 41.656 39.864 41.656c21.881 0 39.864-18.518 39.864-41.656m40.96 0c0 45.495-36.048 82.616-80.824 82.616-44.769 0-80.824-37.124-80.824-82.616s36.055-82.616 80.824-82.616c44.776 0 80.824 37.121 80.824 82.616m176.274 192.62c0-23.138-17.983-41.656-39.864-41.656-21.875 0-39.864 18.522-39.864 41.656s17.989 41.656 39.864 41.656c21.881 0 39.864-18.518 39.864-41.656m40.96 0c0 45.495-36.048 82.616-80.824 82.616-44.769 0-80.824-37.124-80.824-82.616s36.055-82.616 80.824-82.616c44.776 0 80.824 37.121 80.824 82.616m-95.515-225.529L363.022 629.79c-7.88 8.114-7.69 21.08.424 28.96s21.08 7.69 28.96-.424l241.367-248.545c7.88-8.114 7.69-21.08-.424-28.96s-21.08-7.69-28.96.424"/>
  <path fill="#2563eb" d="M829.44 911.36c45.245 0 81.92-36.675 81.92-81.92V194.56c0-45.245-36.675-81.92-81.92-81.92H194.56c-45.245 0-81.92 36.675-81.92 81.92v634.88c0 45.245 36.675 81.92 81.92 81.92zm0 40.96H194.56c-67.866 0-122.88-55.014-122.88-122.88V194.56c0-67.866 55.014-122.88 122.88-122.88h634.88c67.866 0 122.88 55.014 122.88 122.88v634.88c0 67.866-55.014 122.88-122.88 122.88"/>
</svg> %s',
                __('Location-based Tax Settings', 'multi-location-product-and-inventory-management-pro'),
            ),
            function () {
                echo '<p>' . esc_html__('Configure tax settings specific to each store location.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            },
            'lwp-location-tax-settings'
        );

        // Add "Enable Location-based Taxes" field
        add_settings_field(
            'enable_location_taxes',
            __('Enable Location-based Taxes', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['enable_location_taxes' => 'off']);
                $value = isset($options['enable_location_taxes']) ? $options['enable_location_taxes'] : 'off';
                $is_manual_mode = $this->is_manual_assignment_strict_mode();

                if ($is_manual_mode) {
                    $this->render_manual_hidden_input('enable_location_taxes', $value);
                }

                // Check if WooCommerce taxes are enabled
                $wc_tax_enabled = wc_tax_enabled();

                if (mulopimfwc_premium_feature()) {
            ?>
                <select <?php echo (!$wc_tax_enabled || $is_manual_mode) ? 'disabled' : ''; ?> name="mulopimfwc_display_options[enable_location_taxes]">
                    <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                </select>
            <?php } else { ?>
                <label class="mulopimfwc_pro_only">
                    <select disabled name="_pro[enable_location_taxes]">
                        <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    </select>
                </label>
            <?php } ?>
            <p class="description"><?php echo esc_html($this->append_manual_disabled_note(__('Apply different tax rates based on the product location.', 'multi-location-product-and-inventory-management-pro'))); ?></p>
            <?php
                if (!$wc_tax_enabled) {
                    echo '<div class="lwp-notice lwp-notice-warning" style="background-color: #fff3cd; border: 1px solid #ffeeba; border-left: 4px solid #ffc107; border-radius: 4px; padding: 12px 16px; margin: 10px 0;">
                        <div style="display: flex; align-items: start; gap: 12px;">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" style="flex-shrink: 0; margin-top: 2px;">
                                <path fill="#856404" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z"/>
                            </svg>
                            <div>
                                <p style="margin: 0; color: #856404;">
                                    ' . wp_kses(
                        sprintf(
                            /* translators: %s: URL to WooCommerce general settings */
                            __('WooCommerce taxes are currently disabled. To use location-based taxes, please <a href="%s" target="_blank" style="color: #856404; text-decoration: underline;">enable taxes</a> in WooCommerce settings first.', 'multi-location-product-and-inventory-management-pro'),
                            esc_url(admin_url('admin.php?page=wc-settings&tab=general#:~:text=Shop%20country/region-,Enable%20taxes,-Enable%20taxes'))
                        ),
                        array(
                            'a' => array(
                                'href'   => true,
                                'target' => true,
                                'style'  => true,
                            ),
                        )
                    ) . '
                                </p>
                            </div>
                        </div>
                    </div>';
                }
            },
            'lwp-location-tax-settings',
            'mulopimfwc_location_tax_section'
        );

        // Add "Tax Calculation for Mixed Cart" field
        add_settings_field(
            'tax_calculation_mixed_cart',
            __('Tax Calculation for Mixed Cart', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['tax_calculation_mixed_cart' => 'separate']);
                $value = isset($options['tax_calculation_mixed_cart']) ? $options['tax_calculation_mixed_cart'] : 'separate';
            ?>
            <select disabled name="mulopimfwc_display_options[tax_calculation_mixed_cart]">
                <option value="separate" <?php selected($value, 'separate'); ?>><?php echo esc_html_e('Calculate Separately by Location', 'multi-location-product-and-inventory-management-pro'); ?></option>
                <option value="shipping" <?php selected($value, 'shipping'); ?>><?php echo esc_html_e('Based on Shipping Location', 'multi-location-product-and-inventory-management-pro'); ?></option>
                <option value="billing" <?php selected($value, 'billing'); ?>><?php echo esc_html_e('Based on Billing Location', 'multi-location-product-and-inventory-management-pro'); ?></option>
                <option value="store" <?php selected($value, 'store'); ?>><?php echo esc_html_e('Based on Store Location', 'multi-location-product-and-inventory-management-pro'); ?></option>
            </select>
            <p class="description"><?php echo esc_html_e('How taxes are calculated when cart contains products from multiple locations.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            <?php
            },
            'lwp-location-tax-settings',
            'mulopimfwc_location_tax_section'
        );


        // Add section for Location-based Discounts
        add_settings_section(
            'mulopimfwc_location_discounts_section',
            sprintf(
                '<svg viewBox="0 0 20 20" 
     xmlns="http://www.w3.org/2000/svg"
     width="20" height="20" 
     style="margin-right:6px;vertical-align:middle;background-color:#dbeafe;padding:10px;border-radius:6px">
  <path fill="#2563eb" fill-rule="evenodd" d="M11.566.66a2.19 2.19 0 0 0-3.132 0l-.962.985a2.2 2.2 0 0 1-1.592.66l-1.377-.017a2.19 2.19 0 0 0-2.215 2.215l.016 1.377a2.2 2.2 0 0 1-.66 1.592l-.984.962a2.19 2.19 0 0 0 0 3.132l.985.962c.428.418.667.994.66 1.592l-.017 1.377a2.19 2.19 0 0 0 2.215 2.215l1.377-.016a2.2 2.2 0 0 1 1.592.66l.962.984c.859.88 2.273.88 3.132 0l.962-.985a2.2 2.2 0 0 1 1.592-.66l1.377.017a2.19 2.19 0 0 0 2.215-2.215l-.016-1.377a2.2 2.2 0 0 1 .66-1.592l.984-.962c.88-.859.88-2.273 0-3.132l-.985-.962a2.2 2.2 0 0 1-.66-1.592l.017-1.377a2.19 2.19 0 0 0-2.215-2.215l-1.377.016a2.2 2.2 0 0 1-1.592-.66zM7 8.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3m6 6a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3m.778-8.278a1.1 1.1 0 0 1 0 1.556l-6 6a1.1 1.1 0 1 1-1.556-1.556l6-6a1.1 1.1 0 0 1 1.556 0"/>
</svg> %s',
                __('Location-based Discounts', 'multi-location-product-and-inventory-management-pro'),
            ),
            function () {
                echo '<p>' . esc_html__('Configure discount rules specific to each store location.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            },
            'lwp-location-discount-settings'
        );

        // Add "Enable Location Discounts" field
        add_settings_field(
            'enable_location_discounts',
            __('Enable Location Discounts', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['enable_location_discounts' => 'off']);
                $value = isset($options['enable_location_discounts']) ? $options['enable_location_discounts'] : 'off';
                $is_manual_mode = $this->is_manual_assignment_strict_mode();

                if ($is_manual_mode) {
                    $this->render_manual_hidden_input('enable_location_discounts', $value);
                }

                // Check if any coupons exist
                $args = array(
                    'posts_per_page' => -1,
                    'orderby' => 'title',
                    'order' => 'asc',
                    'post_type' => 'shop_coupon',
                    'post_status' => 'publish',
                );
                $coupons = get_posts($args);

                if (mulopimfwc_premium_feature()) {
            ?>
                <select <?php echo disabled(empty($coupons)) ? 'disabled' : ''; ?><?php echo $is_manual_mode ? ' disabled' : ''; ?> name="mulopimfwc_display_options[enable_location_discounts]">
                    <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                </select>
            <?php } else { ?>
                <label class="mulopimfwc_pro_only">
                    <select disabled name="mulopimfwc_display_options[enable_location_discounts]">
                        <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    </select>
                </label>
            <?php } ?>
            <p class="description"><?php echo esc_html($this->append_manual_disabled_note(__('Allow different discount rules for each store location.', 'multi-location-product-and-inventory-management-pro'))); ?></p>

            <?php
                if (empty($coupons)) {
                    echo '<div class="lwp-notice lwp-notice-warning" style="background-color: #fff3cd; border: 1px solid #ffeeba; border-left: 4px solid #ffc107; border-radius: 4px; padding: 12px 16px; margin: 10px 0;">
                <div style="display: flex; align-items: start; gap: 12px;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" style="flex-shrink: 0; margin-top: 2px;">
                    <path fill="#856404" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z"/>
                    </svg>
                    <div>
                    <p style="margin: 0; color: #856404;">
                        ' . esc_html__('Important: No coupons found in WooCommerce. Please create at least one coupon for location-based discounts to work properly.', 'multi-location-product-and-inventory-management-pro') . '
                    </p>
                    </div>
                </div>
                </div>';
                }
            ?>
            <?php
            },
            'lwp-location-discount-settings',
            'mulopimfwc_location_discounts_section'
        );

        // Add "Location-Specific Coupon Codes" field
        add_settings_field(
            'location_specific_coupons',
            __('Location-Specific Coupon Codes', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['location_specific_coupons' => 'off']);
                $value = isset($options['location_specific_coupons']) ? $options['location_specific_coupons'] : 'off';

                if (mulopimfwc_premium_feature()) {
            ?>
                <select name="mulopimfwc_display_options[location_specific_coupons]">
                    <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option disabled value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                </select>
            <?php } else { ?>
                <label class="mulopimfwc_pro_only">
                    <select disabled name="_pro[location_specific_coupons]">
                        <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option disabled value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    </select>
                </label>
            <?php } ?>
            <p class="description"><?php echo esc_html_e('Allow coupon codes to be restricted to specific store locations.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            <?php
            },
            'lwp-location-discount-settings',
            'mulopimfwc_location_discounts_section'
        );

        // Add coupon behavior toggle for mixed-location carts
        add_settings_field(
            'cross_location_coupon_behavior',
            __('Coupon Behavior for Cross Location', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['cross_location_coupon_behavior' => 'restrict']);
                $value = isset($options['cross_location_coupon_behavior']) ? $options['cross_location_coupon_behavior'] : 'restrict';
                $choices = [
                    'restrict' => __('Totally restrict (default)', 'multi-location-product-and-inventory-management-pro'),
                    'applicable_products' => __('Apply coupon only applicable product', 'multi-location-product-and-inventory-management-pro'),
                    'full_cart' => __('Apply on full cart', 'multi-location-product-and-inventory-management-pro'),
                ];

                if (mulopimfwc_premium_feature()) {
            ?>
                <select name="mulopimfwc_display_options[cross_location_coupon_behavior]">
                    <?php foreach ($choices as $key => $label) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($value, $key); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            <?php } else { ?>
                <label class="mulopimfwc_pro_only">
                    <select disabled name="_pro[cross_location_coupon_behavior]">
                        <?php foreach ($choices as $key => $label) : ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($value, $key); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php } ?>
            <p class="description"><?php echo esc_html__('Control how coupons behave when carts contain products from multiple locations.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            <?php
            },
            'lwp-location-discount-settings',
            'mulopimfwc_location_discounts_section'
        );

        // Add Location-based Product Reviews section
        add_settings_section(
            'mulopimfwc_reviews_section',
            sprintf(
                '<svg xmlns="http://www.w3.org/2000/svg" 
     viewBox="0 0 330 330" 
     xml:space="preserve"
     width="20" height="20" 
     style="margin-right:6px;vertical-align:middle;background-color:#dbeafe;padding:10px;border-radius:6px">
  <path fill="#2563eb" d="M165 0C74.019 0 0 74.019 0 165s74.019 165 165 165 165-74.019 165-165S255.981 0 165 0m0 300c-74.439 0-135-60.561-135-135S90.561 30 165 30s135 60.561 135 135-60.561 135-135 135"/>
  <path fill="#2563eb" d="m247.157 128.196-47.476-6.9-21.23-43.019a15 15 0 0 0-26.902 0l-21.23 43.019-47.476 6.9a14.998 14.998 0 0 0-8.312 25.585l34.353 33.486-8.109 47.282a15 15 0 0 0 21.765 15.813L165 228.039l42.462 22.323a15 15 0 0 0 6.979 1.723h.05c8.271-.015 14.972-6.725 14.972-15 0-1.152-.13-2.274-.375-3.352l-7.97-46.466 34.352-33.486a15 15 0 0 0-8.313-25.585"/>
</svg> %s',
                __('Location-based Product Reviews', 'multi-location-product-and-inventory-management-pro'),
            ),
            function () {
                echo '<p>' . esc_html__('Configure how product reviews are handled across different locations.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            },
            'lwp-location-reviews-settings'
        );

        // Add "Location-Specific Reviews" field
        add_settings_field(
            'location_specific_reviews',
            __('Location-Specific Reviews', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['location_specific_reviews' => 'off']);
                $value = isset($options['location_specific_reviews']) ? $options['location_specific_reviews'] : 'off';
                $is_manual_mode = $this->is_manual_assignment_strict_mode();

                if ($is_manual_mode) {
                    $this->render_manual_hidden_input('location_specific_reviews', $value);
                }

                if (mulopimfwc_premium_feature()) {
            ?>
                <select <?php echo $is_manual_mode ? ' disabled' : ''; ?> name="mulopimfwc_display_options[location_specific_reviews]">
                    <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                </select>
            <?php } else { ?>
                <label class="mulopimfwc_pro_only">
                    <select disabled name="_pro[location_specific_reviews]">
                        <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    </select>
                </label>
            <?php } ?>
            <p class="description"><?php echo esc_html($this->append_manual_disabled_note(__('Allow products to have different reviews based on location.', 'multi-location-product-and-inventory-management-pro'))); ?></p>
            <?php
            },
            'lwp-location-reviews-settings',
            'mulopimfwc_reviews_section'
        );

        // Add "Show Location in Reviews" field
        add_settings_field(
            'show_location_in_reviews',
            __('Show Location in Reviews', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['show_location_in_reviews' => 'on']);
                $value = isset($options['show_location_in_reviews']) ? $options['show_location_in_reviews'] : 'on';

                if (mulopimfwc_premium_feature()) {
            ?>
                <select name="mulopimfwc_display_options[show_location_in_reviews]">
                    <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                </select>
            <?php } else { ?>
                <label class="mulopimfwc_pro_only">
                    <select disabled name="_pro[show_location_in_reviews]">
                        <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    </select>
                </label>
            <?php } ?>
            <p class="description"><?php echo esc_html_e('Display location information in product reviews.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            <?php
            },
            'lwp-location-reviews-settings',
            'mulopimfwc_reviews_section'
        );

        // Add Location SEO section
        add_settings_section(
            'mulopimfwc_seo_section',
            sprintf(
                '<svg viewBox="0 0 24 24" 
     xmlns="http://www.w3.org/2000/svg"
     width="20" height="20" 
     style="margin-right:6px;vertical-align:middle;background-color:#dbeafe;padding:10px;border-radius:6px">
  <path fill="#2563eb" d="M2.293 18.707a1 1 0 0 1 0-1.414l3-3a1 1 0 0 1 1.262-.125l2.318 1.545 2.42-2.42a1 1 0 0 1 1.414 1.414l-3 3a1 1 0 0 1-1.262.125l-2.318-1.545-2.42 2.42a1 1 0 0 1-1.414 0M22 3v18a1 1 0 0 1-1 1H3a1 1 0 0 1 0-2h17V8H4v4a1 1 0 0 1-2 0V3a1 1 0 0 1 1-1h18a1 1 0 0 1 1 1M4 6h16V4H4Zm10.707 6.707 1-1a1 1 0 0 0-1.414-1.414l-1 1a1 1 0 1 0 1.414 1.414"/>
</svg> %s',
                __('Location SEO Settings', 'multi-location-product-and-inventory-management-pro'),
            ),
            function () {
                echo '<p>' . esc_html__('Configure SEO settings for location-based product pages.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            },
            'lwp-location-seo-settings'
        );

        // Add "Location in Meta Title" field
        add_settings_field(
            'location_in_meta_title',
            __('Location in Meta Title', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['location_in_meta_title' => 'on']);
                $value = isset($options['location_in_meta_title']) ? $options['location_in_meta_title'] : 'on';

                if (mulopimfwc_premium_feature()) {
            ?>
                <select name="mulopimfwc_display_options[location_in_meta_title]">
                    <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                </select>
            <?php } else { ?>
                <label class="mulopimfwc_pro_only">
                    <select disabled name="_pro[location_in_meta_title]">
                        <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    </select>
                </label>
            <?php } ?>
            <p class="description"><?php echo esc_html_e('Include location name in product page meta titles.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            <?php
            },
            'lwp-location-seo-settings',
            'mulopimfwc_seo_section'
        );

        // Add "Location in Meta Description" field
        add_settings_field(
            'location_in_meta_description',
            __('Location in Meta Description', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['location_in_meta_description' => 'on']);
                $value = isset($options['location_in_meta_description']) ? $options['location_in_meta_description'] : 'on';

                if (mulopimfwc_premium_feature()) {
            ?>
                <select name="mulopimfwc_display_options[location_in_meta_description]">
                    <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                </select>
            <?php } else { ?>
                <label class="mulopimfwc_pro_only">
                    <select disabled name="_pro[location_in_meta_description]">
                        <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    </select>
                </label>
            <?php } ?>
            <p class="description"><?php echo esc_html_e('Include location information in product meta descriptions.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            <?php
            },
            'lwp-location-seo-settings',
            'mulopimfwc_seo_section'
        );

        // Add "Location Structured Data" field
        add_settings_field(
            'location_structured_data',
            __('Location Structured Data', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['location_structured_data' => 'on']);
                $value = isset($options['location_structured_data']) ? $options['location_structured_data'] : 'on';

                if (mulopimfwc_premium_feature()) {
            ?>
                <select name="mulopimfwc_display_options[location_structured_data]">
                    <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                </select>
            <?php } else { ?>
                <label class="mulopimfwc_pro_only">
                    <select disabled name="_pro[location_structured_data]">
                        <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    </select>
                </label>
            <?php } ?>
            <p class="description"><?php echo esc_html_e('Add location information to product structured data for SEO.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            <?php
            },
            'lwp-location-seo-settings',
            'mulopimfwc_seo_section'
        );
        // Add section for Location-based Email Notifications
        add_settings_section(
            'mulopimfwc_location_email_section',
            sprintf(
                '<svg xmlns="http://www.w3.org/2000/svg" 
     viewBox="0 0 24 24"
     width="20" height="20" 
     style="margin-right:6px;vertical-align:middle;background-color:#dbeafe;padding:10px;border-radius:6px">
  <path fill="#2563eb" d="M11.5 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2m6.5-6v-5.5c0-3.07-2.13-5.64-5-6.32V3.5c0-.83-.67-1.5-1.5-1.5S10 2.67 10 3.5v.68c-2.87.68-5 3.25-5 6.32V16l-2 2v1h17v-1z"/>
</svg> %s',
                __('Location-based Email Notifications', 'multi-location-product-and-inventory-management-pro'),
            ),
            function () {
                echo '<p>' . esc_html__('Configure email notifications based on store locations.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            },
            'lwp-location-notifications-settings'
        );

        // Add "Location-Specific Email Templates" field
        add_settings_field(
            'location_specific_emails',
            __('Location-Specific Email Templates', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['location_specific_emails' => 'off']);
                $value = isset($options['location_specific_emails']) ? $options['location_specific_emails'] : 'off';

                if (mulopimfwc_premium_feature()) {
            ?>
                <select name="mulopimfwc_display_options[location_specific_emails]">
                    <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                </select>
            <?php } else { ?>
                <label class="mulopimfwc_pro_only">
                    <select disabled name="_pro[location_specific_emails]">
                        <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    </select>
                </label>
            <?php } ?>
            <p class="description"><?php echo esc_html_e('Use different email templates for different store locations.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            <?php
            },
            'lwp-location-notifications-settings',
            'mulopimfwc_location_email_section'
        );

        // Add "Include Location Logo in Emails" field
        add_settings_field(
            'include_location_logo_emails',
            __('Include Location Logo in Emails', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['include_location_logo_emails' => 'on']);
                $value = isset($options['include_location_logo_emails']) ? $options['include_location_logo_emails'] : 'on';

                if (mulopimfwc_premium_feature()) {
            ?>
                <select name="mulopimfwc_display_options[include_location_logo_emails]">
                    <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                </select>
            <?php } else { ?>
                <label class="mulopimfwc_pro_only">
                    <select disabled name="_pro[include_location_logo_emails]">
                        <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    </select>
                </label>
            <?php } ?>
            <p class="description"><?php echo esc_html_e('Include the store location logo in order emails.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            <?php
            },
            'lwp-location-notifications-settings',
            'mulopimfwc_location_email_section'
        );

        // Add "Location-Specific Email Recipients" field
        add_settings_field(
            'location_specific_email_recipients',
            __('Location-Specific Email Recipients', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['location_specific_email_recipients' => 'on']);
                $value = isset($options['location_specific_email_recipients']) ? $options['location_specific_email_recipients'] : 'on';

                if (mulopimfwc_premium_feature()) {
            ?>
                <select name="mulopimfwc_display_options[location_specific_email_recipients]">
                    <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                </select>
            <?php } else { ?>
                <label class="mulopimfwc_pro_only">
                    <select disabled name="_pro[location_specific_email_recipients]">
                        <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    </select>
                </label>
            <?php } ?>
            <p class="description"><?php echo esc_html_e('Send order notifications to location-specific email addresses.', 'multi-location-product-and-inventory-management-pro'); ?></p>
        <?php
            },
            'lwp-location-notifications-settings',
            'mulopimfwc_location_email_section'
        );

        // Add "Notes" field
        add_settings_field(
            'location_email_notes',
            '',
            function () {
        ?>
            <div style="display:flex;gap:16px;align-items:flex-start;background:#f8fafc;border:1px solid #e6eef8;padding:16px;border-radius:10px;max-width:820px;">
                <div style="flex-shrink:0;">
                    <svg width="40" height="40" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false" style="display:block;">
                        <path fill="#2563eb" d="M12 2C8.7 2 6 4.7 6 8c0 5.2 6 11.1 6 11.1s6-6 6-11.1c0-3.3-2.7-6-6-6m0 3.9c1.1 0 2.1 1 2.1 2.1 0 1.2-.9 2.1-2.1 2.1S9.9 9.1 9.9 8c0-1.2 1-2.1 2.1-2.1z" />
                    </svg>
                </div>
                <div style="flex:1;">
                    <h4 style="margin:0 0 8px 0;color:#0f172a;font-weight:700;font-size:15px;"><?php echo esc_html__('Email Notification — Important Notes', 'multi-location-product-and-inventory-management-pro'); ?></h4>

                    <p style="margin:0 0 10px 0;color:#475569;line-height:1.5;">
                        <?php echo esc_html__('When enabled, order and pickup notifications will be sent to the location manager (if assigned) and the location contact email. Admin recipients depend on your notification settings.', 'multi-location-product-and-inventory-management-pro'); ?>
                    </p>

                    <p style="margin:0 0 8px 0;color:#475569;font-weight:600;"><?php echo esc_html__('Available placeholders for email templates:', 'multi-location-product-and-inventory-management-pro'); ?></p>

                    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
                        <code style="background:#0f172a;color:#fff;padding:5px 8px;border-radius:6px;font-size:13px;"><?php echo esc_html('{order_store_location}'); ?></code>
                        <code style="background:#0f172a;color:#fff;padding:5px 8px;border-radius:6px;font-size:13px;"><?php echo esc_html('{store_location_address}'); ?></code>
                        <code style="background:#0f172a;color:#fff;padding:5px 8px;border-radius:6px;font-size:13px;"><?php echo esc_html('{store_location_phone}'); ?></code>
                        <code style="background:#0f172a;color:#fff;padding:5px 8px;border-radius:6px;font-size:13px;"><?php echo esc_html('{store_location_email}'); ?></code>
                        <code style="background:#0f172a;color:#fff;padding:5px 8px;border-radius:6px;font-size:13px;"><?php echo esc_html('{store_location_logo}'); ?></code>
                        <code style="background:#0f172a;color:#fff;padding:5px 8px;border-radius:6px;font-size:13px;"><?php echo esc_html('{store_manager_name}'); ?></code>
                    </div>

                    <p style="margin:0 0 8px 0;color:#475569;font-weight:600;"><?php echo esc_html__('Example usage in an email template:', 'multi-location-product-and-inventory-management-pro'); ?></p>

                    <pre style="background:#0b1220;color:#e6f0ff;padding:10px;border-radius:6px;overflow:auto;font-size:13px;margin:0 0 10px 0;max-width:100%;">
    <?php echo esc_html("Order received for: {order_store_location}\nAddress: {store_location_address}\nContact: {store_location_phone} / {store_location_email}\nManager: {store_manager_name}\n\n{store_location_logo}"); ?>
                </pre>

                    <p style="margin:8px 0 0 0;color:#475569;line-height:1.4;">
                        <?php
                        printf(
                            wp_kses(
                                /* translators: %s: documentation URL */
                                __('If a location logo is not available the site/store default will be used. For full integration details and more placeholders, see the <a href="%s" target="_blank">plugin documentation</a>.', 'multi-location-product-and-inventory-management-pro'),
                                array(
                                    'a' => array(
                                        'href' => array(),
                                        'target' => array()
                                    )
                                )
                            ),
                            esc_url('https://plugincy.com/documentations/multi-location-product-inventory-management-for-woocommerce/email-notification/location-specific-info-in-email/')
                        );
                        ?>
                    </p>
                </div>
            </div>
            <?php
            },
            'lwp-location-notifications-settings',
            'mulopimfwc_location_email_section'
        );

        // Add section for Location Hours and Availability
        add_settings_section(
            'mulopimfwc_location_hours_section',
            sprintf(
                '<svg xmlns="http://www.w3.org/2000/svg" 
     viewBox="0 0 24 24" 
     xml:space="preserve"
     width="20" height="20" 
     style="margin-right:6px;vertical-align:middle;background-color:#f3e8ff;padding:10px;border-radius:6px">
  <path fill="#9333ea" d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm0 18a8 8 0 1 1 8-8 8 8 0 0 1-8 8zm1-13h-2v6l5 3 1-1.73-4-2.27z"/>
</svg> %s',
                __('Location Hours & Availability', 'multi-location-product-and-inventory-management-pro'),
            ),
            function () {
                echo '<p>' . esc_html__('Configure business hours and availability for each store location.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            },
            'lwp-business-hour-settings'
        );

        // Add "Enable Business Hours" field
        add_settings_field(
            'enable_business_hours',
            __('Enable Business Hours', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['enable_business_hours' => 'off']);
                $value = isset($options['enable_business_hours']) ? $options['enable_business_hours'] : 'off';

                if (mulopimfwc_premium_feature()) {
            ?>
                <select name="mulopimfwc_display_options[enable_business_hours]">
                    <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                </select>
            <?php } else { ?>
                <label class="mulopimfwc_pro_only">
                    <select disabled name="_pro[enable_business_hours]">
                        <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    </select>
                </label>
            <?php } ?>
            <p class="description"><?php echo esc_html_e('Enable management of business hours for each location.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            <?php
            },
            'lwp-business-hour-settings',
            'mulopimfwc_location_hours_section'
        );

        // Add "Display Hours on Location Archive Page" field
        add_settings_field(
            'display_hours_archive_page',
            __('Display Hours on Location Archive', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['display_hours_archive_page' => 'off']);
                $value = isset($options['display_hours_archive_page']) ? $options['display_hours_archive_page'] : 'off';

                if (mulopimfwc_premium_feature()) {
            ?>
                <select name="mulopimfwc_display_options[display_hours_archive_page]">
                    <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                </select>
            <?php } else { ?>
                <label class="mulopimfwc_pro_only">
                    <select disabled name="_pro[display_hours_archive_page]">
                        <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    </select>
                </label>
            <?php } ?>
            <p class="description"><?php echo esc_html_e('Show store hours on Location Archive pages next to location information.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            <?php
            },
            'lwp-business-hour-settings',
            'mulopimfwc_location_hours_section'
        );

        // Add "Restrict Purchasing to Open Hours" field
        add_settings_field(
            'restrict_purchase_to_open_hours',
            __('Restrict Purchasing to Open Hours', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['restrict_purchase_to_open_hours' => 'off']);
                $value = isset($options['restrict_purchase_to_open_hours']) ? $options['restrict_purchase_to_open_hours'] : 'off';

                if (mulopimfwc_premium_feature()) {
            ?>
                <select name="mulopimfwc_display_options[restrict_purchase_to_open_hours]">
                    <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                </select>
            <?php } else { ?>
                <label class="mulopimfwc_pro_only">
                    <select disabled name="_pro[restrict_purchase_to_open_hours]">
                        <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    </select>
                </label>
            <?php } ?>
            <p class="description"><?php echo esc_html_e('Only allow purchases when the store location is open.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            <?php
            },
            'lwp-business-hour-settings',
            'mulopimfwc_location_hours_section'
        );

        // Add Location URL Settings Section
        add_settings_section(
            'mulopimfwc_location_url_section',
            sprintf(
                '<svg viewBox="0 0 24 24" 
     xml:space="preserve" 
     xmlns="http://www.w3.org/2000/svg"
     width="20" height="20" 
     style="margin-right:6px;vertical-align:middle;background-color:#cffafe;padding:10px;border-radius:6px">
  <g/>
  <path fill="#0891b2" d="m20.7 19.3-1-1c-.4-.4-1-.4-1.4 0s-.4 1 0 1.4l1 1c.2.2.5.3.7.3s.5-.1.7-.3c.4-.4.4-1 0-1.4M14 22c0 .6.4 1 1 1s1-.4 1-1v-2c0-.6-.4-1-1-1s-1 .4-1 1zm8-8h-2c-.6 0-1 .4-1 1s.4 1 1 1h2c.6 0 1-.4 1-1s-.4-1-1-1m-1.3-5.6q0-2.1-1.5-3.6t-3.6-1.5c-2.1 0-2.6.5-3.6 1.5L9.8 7c-.4.4-.4 1 0 1.4s1 .4 1.4 0l2.2-2.2c1.2-1.2 3.2-1.2 4.4 0 .6.6.9 1.4.9 2.2s-.3 1.6-.9 2.2l-2.2 2.2c-.4.4-.4 1 0 1.4.2.2.5.3.7.3s.5-.1.7-.3l2.2-2.2q1.5-1.5 1.5-3.6M3.3 15.6q0 2.1 1.5 3.6t3.6 1.5c2.1 0 2.6-.5 3.6-1.5l2.2-2.2c.4-.4.4-1 0-1.4s-1-.4-1.4 0l-2.2 2.2c-1.2 1.2-3.2 1.2-4.4 0-.6-.6-.9-1.4-.9-2.2s.3-1.6.9-2.2l2.2-2.2c.4-.4.4-1 0-1.4s-1-.4-1.4 0L4.8 12q-1.5 1.5-1.5 3.6M5.7 4.3l-1-1c-.4-.4-1-.4-1.4 0s-.4 1 0 1.4l1 1c.2.2.4.3.7.3s.5-.1.7-.3c.4-.4.4-1 0-1.4M10 4V2c0-.6-.4-1-1-1s-1 .4-1 1v2c0 .6.4 1 1 1s1-.4 1-1m-6 6c.6 0 1-.4 1-1s-.4-1-1-1H2c-.6 0-1 .4-1 1s.4 1 1 1z"/>
</svg> %s',
                __('Location URL Settings', 'multi-location-product-and-inventory-management-pro'),
            ),
            function () {
                echo '<p>' . esc_html__('Configure how location information appears in URLs.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            },
            'lwp-url-management-settings'
        );

        // Add "Enable Location in URLs" field
        add_settings_field(
            'enable_location_urls',
            __('Enable Location in URLs', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['enable_location_urls' => 'off']);
                $value = isset($options['enable_location_urls']) ? $options['enable_location_urls'] : 'off';

                if (mulopimfwc_premium_feature()) {
            ?>
                <select name="mulopimfwc_display_options[enable_location_urls]">
                    <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                </select>
            <?php } else { ?>
                <label class="mulopimfwc_pro_only">
                    <select disabled name="_pro[enable_location_urls]">
                        <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    </select>
                </label>
            <?php } ?>
            <p class="description"><?php echo esc_html_e('Include location information in product and category URLs.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            <?php
            },
            'lwp-url-management-settings',
            'mulopimfwc_location_url_section'
        );

        // Add "URL Location Format" field
        add_settings_field(
            'url_location_format',
            __('URL Location Format', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['url_location_format' => 'query_param']);
                $value = isset($options['url_location_format']) ? $options['url_location_format'] : 'query_param';
                $location_url_prefix = isset($options['location_url_prefix']) ? $options['location_url_prefix'] : 'store-location';

                if (mulopimfwc_premium_feature()) {
            ?>
                <select name="mulopimfwc_display_options[url_location_format]">
                    <option value="query_param" <?php selected($value, 'query_param'); ?>><?php printf( /* translators: %s: URL parameter or path prefix name */ esc_html__('Query Parameter (?%s=location-name)', 'multi-location-product-and-inventory-management-pro'), esc_html($location_url_prefix) );                                                                                            ?>
                    </option>
                    <option value="path_prefix" <?php selected($value, 'path_prefix'); ?>><?php printf( /* translators: %s: URL path prefix */ esc_html__('Path Prefix (/%s/location-name)', 'multi-location-product-and-inventory-management-pro'), esc_html($location_url_prefix) ); ?></option>
                </select>
            <?php } else { ?>
                <label class="mulopimfwc_pro_only">
                    <select disabled name="_pro[url_location_format]">
                        <option value="query_param" <?php selected($value, 'query_param'); ?>><?php
                                                                                                printf(
                                                                                                    /* translators: %s: URL parameter name */
                                                                                                    esc_html__('Query Parameter (?%s=location-name)', 'multi-location-product-and-inventory-management-pro'),
                                                                                                    esc_html($location_url_prefix)
                                                                                                );
                                                                                                ?>
                        </option>
                        <option value="path_prefix" <?php selected($value, 'path_prefix'); ?>><?php
                                                                                                printf(
                                                                                                    /* translators: %s: URL path prefix */
                                                                                                    esc_html__('Path Prefix (/%s/location-name)', 'multi-location-product-and-inventory-management-pro'),
                                                                                                    esc_html($location_url_prefix)
                                                                                                );
                                                                                                ?>
                        </option>
                    </select>
                </label>
            <?php } ?>
            <p class="description"><?php echo esc_html_e('How to format location information in URLs.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            <?php
            },
            'lwp-url-management-settings',
            'mulopimfwc_location_url_section'
        );

        // Add "Location URL Prefix" field
        add_settings_field(
            'location_url_prefix',
            __('Location URL Prefix', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['location_url_prefix' => 'store-location']);
                $value = isset($options['location_url_prefix']) ? $options['location_url_prefix'] : 'store-location';

                if (mulopimfwc_premium_feature()) {
            ?>
                <input type="text" name="mulopimfwc_display_options[location_url_prefix]" value="<?php echo esc_attr($value); ?>" class="regular-text">
            <?php } else { ?>
                <label class="mulopimfwc_pro_only">
                    <input disabled type="text" name="_pro[location_url_prefix]" value="<?php echo esc_attr($value); ?>" class="regular-text">
                </label>
            <?php } ?>
            <p class="description"><?php echo esc_html_e('URL Prefix (e.g., "store" for store-name.example.com):', 'multi-location-product-and-inventory-management-pro'); ?></p>
            <p class="description" style="color: #4a4a4a; display: flex; align-items: center; background: #e0f7fa; border-radius: 5px; gap: 10px; font-size: 12px; font-weight: bold; padding: 10px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" style="fill: #00796b; width: 15%;">
                    <path d="M10 0C4.48 0 0 4.48 0 10s4.48 10 10 10 10-4.48 10-10S15.52 0 10 0zm0 18C5.58 18 2 14.42 2 10S5.58 2 10 2s8 3.58 8 8-3.58 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z" />
                </svg>
                <?php echo esc_html_e('After changes, visit Settings > Permalinks and click "Save Changes" to flush rewrite rules.', 'multi-location-product-and-inventory-management-pro'); ?>
            </p>

        <?php
            },
            'lwp-url-management-settings',
            'mulopimfwc_location_url_section'
        );
        // Add Location Display section
        add_settings_section(
            'mulopimfwc_location_display_section',
            sprintf(
                '<svg viewBox="0 0 24 24" 
     xmlns="http://www.w3.org/2000/svg"
     width="20" height="20" 
     style="margin-right:6px;vertical-align:middle;background-color:#dbeafe;padding:10px;border-radius:6px">
  <path fill="#2563eb" fill-rule="evenodd" d="M3.055 13H1v-2h2.055A9.004 9.004 0 0 1 11 3.055V1h2v2.055A9.004 9.004 0 0 1 20.945 11H23v2h-2.055A9.004 9.004 0 0 1 13 20.945V23h-2v-2.055A9.004 9.004 0 0 1 3.055 13M12 5a7 7 0 1 0 0 14 7 7 0 0 0 0-14m0 3a4 4 0 1 1 0 8 4 4 0 0 1 0-8m0 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4"/>
</svg> %s', __('Location Selection Display', 'multi-location-product-and-inventory-management-pro'),
),            function () {
                echo '<p>' . esc_html__('Configure how the location selector appears to customers.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            },
            'lwp-location-selection-settings'
        );

        // Add "Display Location on Single Product" field
        add_settings_field(
            'display_location_single_product',
            __('Display Location on Single Product', 'multi-location-product-and-inventory-management-pro'),
            function () {
                $is_manual_mode = $this->is_manual_assignment_strict_mode();
                $options = $this->get_display_options();
                if ($is_manual_mode) {
                    $this->render_manual_hidden_input('display_location_single_product', $options['display_location_single_product'] ?? 'off');
                }
                $message = $this->append_manual_disabled_note(__("Show current location on single product pages.", 'multi-location-product-and-inventory-management-pro'));
                $this->render_advance_checkbox("display_location_single_product", $message, $is_manual_mode);
            },
            'lwp-location-selection-settings',
            'mulopimfwc_location_display_section'
        );

        // Add "Location Display Position" field
        add_settings_field(
            'location_display_position',
            __('Location Display Position', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['location_display_position' => 'after_price']);
                $value = isset($options['location_display_position']) ? $options['location_display_position'] : 'after_price';
                $is_manual_mode = $this->is_manual_assignment_strict_mode();
                if ($is_manual_mode) {
                    $this->render_manual_hidden_input('location_display_position', $value);
                }
        ?>
            <select name="mulopimfwc_display_options[location_display_position]" <?php disabled( $is_manual_mode ); ?>>
                <option value="after_title" <?php selected($value, 'after_title'); ?>><?php echo esc_html_e('After Product Title', 'multi-location-product-and-inventory-management-pro'); ?></option>
                <option value="after_price" <?php selected($value, 'after_price'); ?>><?php echo esc_html_e('After Product Price', 'multi-location-product-and-inventory-management-pro'); ?></option>
                <option value="before_add_to_cart" <?php selected($value, 'before_add_to_cart'); ?>><?php echo esc_html_e('Before Add to Cart Button', 'multi-location-product-and-inventory-management-pro'); ?></option>
                <option value="after_add_to_cart" <?php selected($value, 'after_add_to_cart'); ?>><?php echo esc_html_e('After Add to Cart Button', 'multi-location-product-and-inventory-management-pro'); ?></option>
                <option value="product_meta" <?php selected($value, 'product_meta'); ?>><?php echo esc_html_e('In Product Meta Section', 'multi-location-product-and-inventory-management-pro'); ?></option>
            </select>
            <p class="description"><?php echo esc_html($this->append_manual_disabled_note(__('Where to display the current location on single product pages.', 'multi-location-product-and-inventory-management-pro'))); ?></p>
        <?php
            },
            'lwp-location-selection-settings',
            'mulopimfwc_location_display_section'
        );

        // Add "Location Selector Layout" field
        add_settings_field(
            'location_selector_layout',
            __('Location Selector Layout', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['location_selector_layout' => 'list']);
                $value = isset($options['location_selector_layout']) ? $options['location_selector_layout'] : 'list';
                $is_manual_mode = $this->is_manual_assignment_strict_mode();
                if ($is_manual_mode) {
                    $this->render_manual_hidden_input('location_selector_layout', $value);
                }
                $label_class = $is_manual_mode ? ' mulopimfwc-setting-disabled' : '';
        ?>
            <label class="<?php echo esc_attr(mulopimfwc_get_pro_class(false) . $label_class); ?>">
                <select <?php echo esc_attr(mulopimfwc_get_pro_class(false, '', ' disabled ')); ?> name="mulopimfwc_display_options[location_selector_layout]" <?php disabled( $is_manual_mode ); ?>>
                    <option value="list" <?php selected($value, 'list'); ?>><?php echo esc_html_e('List View', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="buttons" <?php selected($value, 'buttons'); ?>><?php echo esc_html_e('Button Style', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="select" <?php selected($value, 'select'); ?>><?php echo esc_html_e('Select Dropdown', 'multi-location-product-and-inventory-management-pro'); ?></option>
                </select>
                <p class="description"><?php echo esc_html($this->append_manual_disabled_note(__('Choose the layout style for the location selector on single product pages.', 'multi-location-product-and-inventory-management-pro'))); ?></p>
            </label>
        <?php
            },
            'lwp-location-selection-settings',
            'mulopimfwc_location_display_section'
        );

        // Add "Hide Out of Stock Locations" field
        add_settings_field(
            'hide_out_of_stock_locations',
            __('Hide Out of Stock Locations', 'multi-location-product-and-inventory-management-pro'),
            function () {
                $is_manual_mode = $this->is_manual_assignment_strict_mode();
                $options = $this->get_display_options();
                if ($is_manual_mode) {
                    $this->render_manual_hidden_input('hide_out_of_stock_locations', $options['hide_out_of_stock_locations'] ?? 'off');
                }
                $message = $this->append_manual_disabled_note(__('Hide locations that are out of stock for the current product in the selector.', 'multi-location-product-and-inventory-management-pro'));
                $this->render_advance_checkbox("hide_out_of_stock_locations", $message, $is_manual_mode);
            },
            'lwp-location-selection-settings',
            'mulopimfwc_location_display_section'
        );

        // Add Product Filter section
        add_settings_section(
            'mulopimfwc_product_filter_section',
sprintf(
        '<svg xmlns="http://www.w3.org/2000/svg" 
     viewBox="0 0 24 24" 
     width="20" height="20" 
     style="margin-right:6px;vertical-align:middle;background-color:#e0f2fe;padding:10px;border-radius:6px">
  <path fill="#0284c7" d="M3 4h18l-7 8v6l-4 2v-8z"/>
</svg> %s',
        __('Product Filter', 'multi-location-product-and-inventory-management-pro')
    ),
            function () {
                echo '<p>' . esc_html__('Control the location-based product filter display on shop and archive pages.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            },
            'lwp-product-filter-settings'
        );

        // Add Enable Product Filter field
        add_settings_field(
            'enable_product_filter',
            __('Enable Product Filter', 'multi-location-product-and-inventory-management-pro'),
            function () {
                if (mulopimfwc_premium_feature()) {
                    $is_allowed = $this->is_manual_assignment_strict_mode();
                    $options = $this->get_display_options();
                    if (!$is_allowed) {
                        echo '<input type="hidden" data-manual-hidden="true" data-manual-for="mulopimfwc_display_options[enable_product_filter]" name="mulopimfwc_display_options[enable_product_filter]" value="' . esc_attr($options['enable_product_filter'] ?? 'off') . '">';
                    }
                    $message = __("Enable or disable automatic product filter display on shop/archive pages. Use shortcode [mulopimfwc_product_filter] to place it manually.", 'multi-location-product-and-inventory-management-pro');
                    $message .= ' ' . __('Available only when Manual, Inventory-Based, or Proximity-Based assignment is enabled and Optional Location Selection is disabled.', 'multi-location-product-and-inventory-management-pro');
                    $this->render_advance_checkbox('enable_product_filter', $message, !$is_allowed);
                } else {
                    $this->render_advance_checkbox('pro', __("Enable or disable automatic product filter display on shop/archive pages. Use shortcode [mulopimfwc_product_filter] to place it manually.", 'multi-location-product-and-inventory-management-pro'), true, false);
                }
            },
            'lwp-product-filter-settings',
            'mulopimfwc_product_filter_section'
        );

        // recommended Dynamic AJAX Product Filters for WooCommerce plugins section
        add_settings_section(
            'recommended_plugins_section',
            '',
            function () {
                $recommendation_url = 'https://plugincy.com/dynamic-ajax-product-filters-for-woocommerce/';
                echo '<div class="mulopimfwc-highlight-card" style="display:none">';
                echo '<div class="mulopimfwc-highlight-card-header">';
                echo '<span class="mulopimfwc-highlight-card-icon" aria-hidden="true">';
                echo '<svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="24" height="24" viewBox="0 0 24 24">';
                echo '<path d="M0 0h24v24H0z" fill="#941F74"></path>';
                echo '<path d="m8.087 5.761 0.594 -0.006h0.64l0.66 -0.003h1.381q1.056 0 2.114 -0.009h1.341l0.639 -0.006 0.597 0.003h0.523c0.753 0.108 1.252 0.444 1.758 1.002 0.357 0.889 0.312 1.763 0.309 2.712l0.003 0.631v1.32q0 1.008 0.009 2.016v1.284l0.006 0.608c-0.011 1.141 -0.083 1.82 -0.849 2.686 -0.572 0.407 -1.044 0.417 -1.734 0.399l-0.507 -0.011 -0.384 -0.013v-0.563l0.438 -0.063 0.57 -0.09 0.567 -0.086c0.549 -0.098 0.549 -0.098 0.862 -0.699 0.057 -0.44 0.057 -0.44 0.048 -0.933l0.003 -0.575 -0.003 -0.619 0.002 -0.636 -0.002 -1.333q-0.002 -1.02 0.003 -2.045l-0.002 -1.294 0.003 -0.619 -0.005 -0.575 -0.002 -0.506c-0.019 -0.48 -0.019 -0.48 -0.422 -0.99 -0.538 -0.18 -0.894 -0.214 -1.455 -0.223l-0.547 -0.009q-1.237 -0.013 -2.477 -0.019a141 141 0 0 1 -1.306 -0.015 157.5 157.5 0 0 0 -1.884 -0.017l-0.59 -0.013c-0.837 0.003 -1.352 0.028 -2.083 0.456 -0.515 0.879 -0.477 1.659 -0.464 2.661l-0.002 0.608q0 0.634 0.007 1.269 0.007 0.969 0 1.941l0.006 1.234 -0.003 0.585c0.013 0.836 0.026 1.344 0.461 2.073 0.482 0.42 0.75 0.476 1.383 0.5l0.507 0.027 0.384 0.006v0.563c-1.857 0.117 -1.857 0.117 -2.637 -0.329 -0.597 -0.693 -0.784 -1.203 -0.794 -2.117l-0.007 -0.569 -0.002 -0.61 -0.005 -0.631 -0.003 -1.323q-0.003 -1.01 -0.015 -2.021l-0.003 -1.287 -0.007 -0.609c0.011 -1.145 0.072 -1.83 0.847 -2.692 0.618 -0.413 0.806 -0.423 1.524 -0.426" fill="#F4E5EF"></path>';
                echo '<path d="M10.875 14.25c0.599 -0.035 0.599 -0.035 1.125 0l0.375 0.75h4.5v0.563h-4.5l-0.375 0.75h-1.313l-0.375 -0.75H7.5V15l0.596 -0.021 0.776 -0.038 0.393 -0.013c0.754 -0.028 0.754 -0.028 1.383 -0.404z" fill="#FBF8FA"></path>';
                echo '<path d="M13.875 11.063c0.679 -0.035 0.679 -0.035 1.313 0l0.375 0.75h1.313v0.563h-1.313l-0.375 0.75c-0.633 0.036 -0.633 0.036 -1.313 0l-0.375 -0.563a10.5 10.5 0 0 0 -1.349 -0.096l-0.402 -0.011 -1.272 -0.022 -0.862 -0.019Q8.558 12.39 7.5 12.375v-0.563l0.544 -0.007q0.999 -0.017 1.998 -0.041l0.865 -0.017q0.621 -0.011 1.244 -0.027l0.392 -0.005c0.404 -0.005 0.404 -0.005 0.957 -0.091z" fill="#FCF8FA"></path>';
                echo '<path d="M9.738 7.817c0.575 0.058 0.575 0.058 0.806 0.338 0.507 0.435 1.059 0.373 1.698 0.38l0.402 0.011q0.633 0.015 1.266 0.022l0.859 0.019q1.053 0.024 2.106 0.039v0.563l-0.541 0.007q-0.996 0.017 -1.99 0.041l-0.861 0.017q-0.618 0.011 -1.239 0.027l-0.389 0.005c-0.716 0.015 -0.716 0.015 -1.309 0.375l-0.232 0.279c-0.563 0.071 -0.563 0.071 -1.125 0q-0.286 -0.276 -0.563 -0.563c-0.584 -0.137 -0.584 -0.137 -1.125 -0.188v-0.563l0.633 -0.164c0.684 -0.211 0.918 -0.572 1.605 -0.645" fill="#FCFAFC"></path>';
                echo '<path d="M9.375 8.438q0.471 0.08 0.938 0.188v0.563l-0.938 0.188c-0.105 -0.363 -0.105 -0.363 -0.188 -0.75z" fill="#9A4289"></path>';
                echo '<path d="m11.063 14.813 0.75 0.188 -0.188 0.75 -0.75 -0.188z" fill="#95297A"></path>';
                echo '<path d="m14.25 11.625 0.75 0.188 -0.188 0.75 -0.75 -0.188z" fill="#A52679"></path>';
                echo '<path d="M12.75 17.813h1.125v0.563H12.75z" fill="#EEDBE8"></path>';
                echo '</svg>';
                echo '</span>';
                echo '<div class="mulopimfwc-highlight-card-title-wrap">';
                echo '<span class="mulopimfwc-highlight-card-badge">' . esc_html__('Recommended', 'multi-location-product-and-inventory-management-pro') . '</span>';
                echo '<span class="mulopimfwc-highlight-card-title">' . esc_html__('Dynamic AJAX Product Filters for WooCommerce', 'multi-location-product-and-inventory-management-pro') . '</span>';
                echo '</div>';
                echo '</div>';
                echo '<p class="mulopimfwc-highlight-card-text">' . esc_html__('For the best filtering experience, use our Dynamic AJAX Product Filters for WooCommerce. It is fast, works with all themes and builders, and delivers smooth filtering without full page reloads.', 'multi-location-product-and-inventory-management-pro') . '</p>';
                echo '<div class="mulopimfwc-highlight-card-tags">';
                echo '<span class="mulopimfwc-highlight-card-tag">' . esc_html__('Fast', 'multi-location-product-and-inventory-management-pro') . '</span>';
                echo '<span class="mulopimfwc-highlight-card-tag">' . esc_html__('Theme Friendly', 'multi-location-product-and-inventory-management-pro') . '</span>';
                echo '<span class="mulopimfwc-highlight-card-tag">' . esc_html__('Builder Ready', 'multi-location-product-and-inventory-management-pro') . '</span>';
                echo '</div>';
                echo '<a class="mulopimfwc-highlight-card-button" href="' . esc_url($recommendation_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Learn More', 'multi-location-product-and-inventory-management-pro') . '</a>';
                echo '</div>';
            },
            'lwp-product-filter-settings'
        );

        // Add "Default Location/Warehouse" section (separate panel)
        add_settings_section(
            'mulopimfwc_default_location_section',
            sprintf(
        '<svg viewBox="0 0 24 24" 
            xmlns="http://www.w3.org/2000/svg"
            width="20" height="20" 
            style="margin-right:6px;vertical-align:middle;background-color:#fef3c7;padding:10px;border-radius:6px">
            <path fill="#f59e0b" d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
        </svg> %s',
        __('Default Location/Warehouse', 'multi-location-product-and-inventory-management-pro')
    ),
            function () {
                echo '<p>' . esc_html__('Set a default location/warehouse that will be used when popup is not enabled and no location is selected.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            },
            'lwp-default-location-settings'
        );

        // Add "Default Location/Warehouse" field
        add_settings_field(
            'default_location',
            __('Default Location/Warehouse', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['default_location' => '', 'enable_popup' => 'off']);
                $default_location = isset($options['default_location']) ? $options['default_location'] : '';
                $enable_popup = isset($options['enable_popup']) ? $options['enable_popup'] : 'off';
                $show_popup_priority_notice = ($enable_popup === 'on' && !empty($default_location));
                $is_manual_mode = $this->is_manual_assignment_strict_mode();
                if ($is_manual_mode) {
                    $this->render_manual_hidden_input('default_location', $default_location);
                }
                // Get all locations
                $locations = get_terms([
                    'taxonomy' => 'mulopimfwc_store_location',
                    'hide_empty' => false,
                ]);

                if (is_wp_error($locations)) {
                    $locations = [];
                }
        ?>
            <select name="mulopimfwc_display_options[default_location]" id="default_location_select" <?php disabled( $is_manual_mode ); ?>>
                <option value=""><?php echo esc_html__('None', 'multi-location-product-and-inventory-management-pro'); ?></option>
                <?php
                if (!empty($locations)) {
                    foreach ($locations as $location) {
                        echo '<option value="' . esc_attr($location->slug) . '" ' . selected( $default_location, $location->slug, false ) . '>' . esc_html($location->name) . '</option>';
                    }
                }
                ?>
            </select>
            <p class="description"><?php echo esc_html($this->append_manual_disabled_note(__('Select a default location/warehouse. This location will be used automatically when popup is disabled and no location cookie is set.', 'multi-location-product-and-inventory-management-pro'))); ?></p>
            <?php
                $popup_notice_classes = 'mulopimfwc-default-location-popup-info' . ($show_popup_priority_notice ? ' is-active' : '');
            ?>
            <div
                id="mulopimfwc-default-location-popup-info"
                class="<?php echo esc_attr($popup_notice_classes); ?>"
                aria-live="polite"
                aria-hidden="<?php echo esc_attr($show_popup_priority_notice ? 'false' : 'true'); ?>">
                <span class="mulopimfwc-default-location-popup-info__title"><?php echo esc_html__('Popup Priority Active', 'multi-location-product-and-inventory-management-pro'); ?></span>
                <p><?php echo esc_html__('Popup is currently enabled and a default location/warehouse is selected. Customers will follow popup-based location selection first. This default location is applied when popup is turned off.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            </div>
            <?php
            },
            'lwp-default-location-settings',
            'mulopimfwc_default_location_section'
        );



        // Add "Store Locator Integration" section
        add_settings_section(
            'mulopimfwc_store_locator_section',
sprintf(
        '<svg viewBox="0 0 48 48" 
     xmlns="http://www.w3.org/2000/svg"
     width="20" height="20" 
     style="margin-right:6px;vertical-align:middle;background-color:#dcfce7;padding:10px;border-radius:6px">
  <g data-name="Layer 2">
    <g data-name="Health Icons">
      <path fill="#16a34a" d="M45.8 16.4v-.3l-5-10.9A2 2 0 0 0 39 4H9a2 2 0 0 0-1.8 1.2L2.3 16.1v.3a6 6 0 0 0 1 5.2 6.9 6.9 0 0 0 2.8 2V41a2.9 2.9 0 0 0 3 3H39a2.9 2.9 0 0 0 3-3V23.6a6.9 6.9 0 0 0 2.8-2 6 6 0 0 0 1-5.2M6 17.6 10.3 8h27.4l4.3 9.6a1.9 1.9 0 0 1-.4 1.5 2.1 2.1 0 0 1-1.8.9h-.9a2.2 2.2 0 0 1-2.3-2.1 2 2 0 0 0-4 0 2.1 2.1 0 0 1-2.2 2.1h-2.2a2.1 2.1 0 0 1-2.2-2.1 2 2 0 0 0-4 0 2.1 2.1 0 0 1-2.2 2.1h-2.2a2.1 2.1 0 0 1-2.2-2.1 2 2 0 0 0-4 0A2.2 2.2 0 0 1 9.1 20h-.9a2.1 2.1 0 0 1-1.8-.9 1.9 1.9 0 0 1-.4-1.5M35 40V27h-7v13H10V23.9a5.9 5.9 0 0 0 3.4-1.5 6.3 6.3 0 0 0 4.2 1.6h2.2a6.3 6.3 0 0 0 4.2-1.6 6.3 6.3 0 0 0 4.2 1.6h2.2a6.3 6.3 0 0 0 4.2-1.6 5.9 5.9 0 0 0 3.4 1.5V40Z"/>
      <path fill="#16a34a" d="M13 27h11v10H13z"/>
    </g>
  </g>
</svg> %s',
        __('Store Locator', 'multi-location-product-and-inventory-management-pro')
    ),
            function () {
                echo '<p>' . esc_html__('Configure store locator functionality and integration.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            },
            'lwp-store-locator-settings'
        );

        // Add "Enable Store Locator" field
        add_settings_field(
            'enable_store_locator',
            __('Enable Store Locator', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['enable_store_locator' => 'off']);
                $value = isset($options['enable_store_locator']) ? $options['enable_store_locator'] : 'off';

                if (mulopimfwc_premium_feature()) {
            ?>
                <select name="mulopimfwc_display_options[enable_store_locator]">
                    <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                </select>
            <?php } else { ?>
                <label class="mulopimfwc_pro_only">
                    <select disabled name="_pro[enable_store_locator]">
                        <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    </select>
                </label>
            <?php } ?>
            <p class="description"><?php echo esc_html_e('Enable store locator with map functionality.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            <?php
            },
            'lwp-store-locator-settings',
            'mulopimfwc_store_locator_section'
        );

        // Add "Map Provider" field
        add_settings_field(
            'map_provider',
            __('Map Provider', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['map_provider' => 'openstreetmap']);
                $value = isset($options['map_provider']) ? $options['map_provider'] : 'openstreetmap';

                if (mulopimfwc_premium_feature()) {
            ?>
                <select name="mulopimfwc_display_options[map_provider]">
                    <!-- <option disabled value="google_maps" <?php //selected($value, 'google_maps'); 
                                                                ?>><?php //echo esc_html_e('Google Maps', 'multi-location-product-and-inventory-management-pro'); 
                                                                    ?></option> -->
                    <option value="openstreetmap" <?php selected($value, 'openstreetmap'); ?>><?php echo esc_html_e('OpenStreetMap', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <!-- <option disabled value="mapbox" <?php //selected($value, 'mapbox'); 
                                                            ?>><?php //echo esc_html_e('Mapbox', 'multi-location-product-and-inventory-management-pro'); 
                                                                ?></option> -->
                </select>
            <?php } else { ?>
                <label class="mulopimfwc_pro_only">
                    <select disabled name="_pro[map_provider]">
                        <!-- <option disabled value="google_maps" <?php //selected($value, 'google_maps'); 
                                                                    ?>><?php //echo esc_html_e('Google Maps', 'multi-location-product-and-inventory-management-pro'); 
                                                                        ?></option> -->
                        <option value="openstreetmap" <?php selected($value, 'openstreetmap'); ?>><?php echo esc_html_e('OpenStreetMap', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <!-- <option disabled value="mapbox" <?php //selected($value, 'mapbox'); 
                                                                ?>><?php //echo esc_html_e('Mapbox', 'multi-location-product-and-inventory-management-pro'); 
                                                                    ?></option> -->
                    </select>
                </label>
            <?php } ?>
            <p class="description"><?php echo esc_html_e('Select which map provider to use for the store locator.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            <?php
            },
            'lwp-store-locator-settings',
            'mulopimfwc_store_locator_section'
        );

        /*
        add_settings_field(
            'map_api_key',
            __('Map API Key', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', ['map_api_key' => '']);
                $value = isset($options['map_api_key']) ? $options['map_api_key'] : '';
        ?>
                <input disabled type="text" name="mulopimfwc_display_options[map_api_key]"
                    value="<?php echo esc_attr($value); ?>" class="regular-text">
                <p class="description">
                    <?php echo esc_html_e('Enter your API key for the selected map provider.', 'multi-location-product-and-inventory-management-pro'); ?>
                </p>
        <?php
            },
            'lwp-store-locator-settings',
            'mulopimfwc_store_locator_section'
        );
        */

        // Add "Default Map Zoom Level" field
        add_settings_field(
            'default_map_zoom',
            __('Default Map Zoom Level', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['default_map_zoom' => '15']);
                $value = isset($options['default_map_zoom']) ? $options['default_map_zoom'] : '15';

                if (mulopimfwc_premium_feature()) {
            ?>
                <input type="number" name="mulopimfwc_display_options[default_map_zoom]" value="<?php echo esc_attr($value); ?>" min="1" max="19" class="small-text">
            <?php } else { ?>
                <label class="mulopimfwc_pro_only">
                    <input disabled type="number" name="_pro[default_map_zoom]" value="<?php echo esc_attr($value); ?>" min="1" max="19" class="small-text">
                </label>
            <?php } ?>
            <p class="description"><?php echo esc_html_e('Default zoom level for the store locator map (1-19).', 'multi-location-product-and-inventory-management-pro'); ?></p>
            <?php
            },
            'lwp-store-locator-settings',
            'mulopimfwc_store_locator_section'
        );

        // Add "Map Animation Type" field
        add_settings_field(
            'map_animation_type',
            __('Map Animation Type', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['map_animation_type' => 'setview']);
                $value = isset($options['map_animation_type']) ? $options['map_animation_type'] : 'setview';

                if (mulopimfwc_premium_feature()) {
            ?>
                <select name="mulopimfwc_display_options[map_animation_type]">
                    <option value="flyto" <?php selected($value, 'flyto'); ?>><?php echo esc_html_e('Fly To (Smooth Animation)', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="setview" <?php selected($value, 'setview'); ?>><?php echo esc_html_e('Set View (Animated)', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="setview_instant" <?php selected($value, 'setview_instant'); ?>><?php echo esc_html_e('Set View (Instant)', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="none" <?php selected($value, 'none'); ?>><?php echo esc_html_e('No Animation', 'multi-location-product-and-inventory-management-pro'); ?></option>
                </select>
            <?php } else { ?>
                <label class="mulopimfwc_pro_only">
                    <select disabled name="_pro[map_animation_type]">
                        <option value="flyto" <?php selected($value, 'flyto'); ?>><?php echo esc_html_e('Fly To (Smooth Animation)', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="setview" <?php selected($value, 'setview'); ?>><?php echo esc_html_e('Set View (Animated)', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="setview_instant" <?php selected($value, 'setview_instant'); ?>><?php echo esc_html_e('Set View (Instant)', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="none" <?php selected($value, 'none'); ?>><?php echo esc_html_e('No Animation', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    </select>
                </label>
            <?php } ?>
            <p class="description"><?php echo esc_html_e('Choose how the map animates when switching between locations. Fly To provides smooth camera movement, Set View offers animated transitions, and Instant provides immediate updates.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            <?php
            },
            'lwp-store-locator-settings',
            'mulopimfwc_store_locator_section'
        );

        // Add "Map Animation Duration" field
        add_settings_field(
            'map_animation_duration',
            __('Map Animation Duration (seconds)', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['map_animation_duration' => '1.5']);
                $value = isset($options['map_animation_duration']) ? $options['map_animation_duration'] : '1.5';

                if (mulopimfwc_premium_feature()) {
            ?>
                <input type="number" name="mulopimfwc_display_options[map_animation_duration]" value="<?php echo esc_attr($value); ?>" min="0" max="5" step="0.1" class="small-text">
            <?php } else { ?>
                <label class="mulopimfwc_pro_only">
                    <input disabled type="number" name="_pro[map_animation_duration]" value="<?php echo esc_attr($value); ?>" min="0" max="5" step="0.1" class="small-text">
                </label>
            <?php } ?>
            <p class="description"><?php echo esc_html_e('Duration of the map animation in seconds (0-5). Only applies when animation is enabled.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            <?php
            },
            'lwp-store-locator-settings',
            'mulopimfwc_store_locator_section'
        );

        // Add "Store Locator Display Position" section
        add_settings_section(
            'mulopimfwc_store_locator_display_position',
            sprintf(
                '<svg xmlns="http://www.w3.org/2000/svg" 
         viewBox="0 0 24 24"
         width="20" height="20" 
         style="margin-right:6px;vertical-align:middle;background-color:#fef3c7;padding:10px;border-radius:6px">
      <path fill="#d97706" d="M11 17h2v-6h-2v6zm1-15C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zM11 9h2V7h-2v2z"/>
    </svg> %s',
    __('Store Locator Display Position', 'multi-location-product-and-inventory-management-pro'),
        ),    function () {
                echo '<p>' . esc_html__('Configure where to display the store locator on different pages.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            },
            'lwp-store-locator-display-settings'
        );

        // Add "Single Product Page Position" field
        add_settings_field(
            'store_locator_single_product_position',
            __('Single Product Page Position', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['store_locator_single_product_position' => 'in_tabs']);
                $value = isset($options['store_locator_single_product_position']) ? $options['store_locator_single_product_position'] : 'in_tabs';

                if (mulopimfwc_premium_feature()) {
            ?>
                <select name="mulopimfwc_display_options[store_locator_single_product_position]">
                    <option value="before_product" <?php selected($value, 'before_product'); ?>><?php echo esc_html_e('Before Product', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="before_related" <?php selected($value, 'before_related'); ?>><?php echo esc_html_e('Before Related Products', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="after_related" <?php selected($value, 'after_related'); ?>><?php echo esc_html_e('After Related Products', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="after_summary" <?php selected($value, 'after_summary'); ?>><?php echo esc_html_e('After Product Summary', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="in_tabs" <?php selected($value, 'in_tabs'); ?>><?php echo esc_html_e('In Product Tabs', 'multi-location-product-and-inventory-management-pro'); ?></option>
                </select>
            <?php } else { ?>
                <label class="mulopimfwc_pro_only">
                    <select disabled name="_pro[store_locator_single_product_position]">
                        <option value="before_product" <?php selected($value, 'before_product'); ?>><?php echo esc_html_e('Before Product', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="before_related" <?php selected($value, 'before_related'); ?>><?php echo esc_html_e('Before Related Products', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="after_related" <?php selected($value, 'after_related'); ?>><?php echo esc_html_e('After Related Products', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="after_summary" <?php selected($value, 'after_summary'); ?>><?php echo esc_html_e('After Product Summary', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="in_tabs" <?php selected($value, 'in_tabs'); ?>><?php echo esc_html_e('In Product Tabs', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    </select>
                </label>
            <?php } ?>
            <p class="description"><?php echo esc_html_e('Choose where to display the store locator on single product pages.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            <?php
            },
            'lwp-store-locator-display-settings',
            'mulopimfwc_store_locator_display_position'
        );

        // Add "Archive Page Position" field
        add_settings_field(
            'store_locator_archive_position',
            __('Location Archive Page Position', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['store_locator_archive_position' => 'before_shop_loop']);
                $value = isset($options['store_locator_archive_position']) ? $options['store_locator_archive_position'] : 'before_shop_loop';

                if (mulopimfwc_premium_feature()) {
            ?>
                <select name="mulopimfwc_display_options[store_locator_archive_position]">
                    <option value="before_shop_loop" <?php selected($value, 'before_shop_loop'); ?>><?php echo esc_html_e('Before Product Loop', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="after_shop_loop" <?php selected($value, 'after_shop_loop'); ?>><?php echo esc_html_e('After Product Loop', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="before_breadcrumbs" <?php selected($value, 'before_breadcrumbs'); ?>><?php echo esc_html_e('Before Breadcrumbs', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="after_breadcrumbs" <?php selected($value, 'after_breadcrumbs'); ?>><?php echo esc_html_e('After Breadcrumbs', 'multi-location-product-and-inventory-management-pro'); ?></option>
                </select>
            <?php } else { ?>
                <label class="mulopimfwc_pro_only">
                    <select disabled name="_pro[store_locator_archive_position]">
                        <option value="before_shop_loop" <?php selected($value, 'before_shop_loop'); ?>><?php echo esc_html_e('Before Product Loop', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="after_shop_loop" <?php selected($value, 'after_shop_loop'); ?>><?php echo esc_html_e('After Product Loop', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="before_breadcrumbs" <?php selected($value, 'before_breadcrumbs'); ?>><?php echo esc_html_e('Before Breadcrumbs', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="after_breadcrumbs" <?php selected($value, 'after_breadcrumbs'); ?>><?php echo esc_html_e('After Breadcrumbs', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    </select>
                </label>
            <?php } ?>
            <p class="description"><?php echo esc_html_e('Choose where to display the store locator on location archive pages.', 'multi-location-product-and-inventory-management-pro'); ?></p>
        <?php
            },
            'lwp-store-locator-display-settings',
            'mulopimfwc_store_locator_display_position'
        );

        // Add Advanced Settings section
        add_settings_section(
            'mulopimfwc_advanced_settings_section',
            sprintf(
                '<svg xmlns="http://www.w3.org/2000/svg" 
     viewBox="0 0 508.963 508.963" 
     xml:space="preserve" 
     width="20" height="20" 
     style="margin-right:6px;vertical-align:middle;background-color:#dbeafe;padding:10px;border-radius:6px">
  <path fill="#2563eb" d="m248.777 293.557 29.595-29.66-30.242-30.156-29.552 29.574c-12.36-8.413-26.51-14.237-41.718-17.17v-41.912h-42.71v41.934c-15.164 2.955-29.293 8.758-41.675 17.17l-29.638-29.574-30.156 30.156 29.509 29.66c-8.305 12.295-14.172 26.424-17.127 41.718H3.151v42.689h41.912c2.955 15.164 8.822 29.293 17.127 41.718l-29.509 29.574 30.156 30.156 29.617-29.574c12.36 8.369 26.51 14.237 41.696 17.192v41.912h42.689v-41.955c15.207-2.955 29.315-8.801 41.718-17.192l29.552 29.53 30.242-30.134-29.595-29.574c8.413-12.382 14.215-26.51 17.127-41.739h41.998v-42.645h-41.977c-2.912-15.274-8.714-29.338-17.127-41.698m-93.25 109.622c-25.669 0-46.593-20.859-46.593-46.593s20.924-46.571 46.593-46.571c25.712 0 46.528 20.837 46.528 46.571 0 25.735-20.795 46.593-46.528 46.593"/>
  <path fill="#2563eb" d="m495.633 334.175.302-.216-207.727-208.827-.324-98.255L187.365 0l-10.699 10.721 53.452 53.323-52.072 52.18-53.409-53.366-10.742 10.656 26.963 100.412 95.602.324-.626.518L444.92 384.952l.28-.259c13.935 13.633 36.411 13.633 50.13-.237 13.914-13.827 13.936-36.196.303-50.281M482.13 371.19c-6.212 6.191-16.243 6.212-22.412 0-6.148-6.191-6.212-16.157-.065-22.369 6.234-6.169 16.264-6.169 22.477-.022 6.147 6.148 6.212 16.157 0 22.391"/>
  <g/><g/><g/><g/><g/><g/><g/><g/><g/><g/><g/><g/><g/><g/><g/>
</svg> %s',
__('Advanced Settings', 'multi-location-product-and-inventory-management-pro'),
    ),        function () {
                echo '<p>' . esc_html__('Advanced configuration options for location management.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            },
            'location-advance-settings'
        );

        add_settings_field(
            'location_cookie_expiry',
            __('Location Cookie Expiry (Days)', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['location_cookie_expiry' => '30']);
                $value = isset($options['location_cookie_expiry']) ? $options['location_cookie_expiry'] : '30';
                $is_manual_mode = $this->is_manual_assignment_strict_mode();

                if ($is_manual_mode) {
                    $this->render_manual_hidden_input('location_cookie_expiry', $value);
                }
        ?>
            <input type="number" name="mulopimfwc_display_options[location_cookie_expiry]" value="<?php echo esc_attr($value); ?>" min="1" max="365" class="small-text" <?php echo $is_manual_mode ? ' disabled' : ''; ?>>
            <p class="description"><?php echo esc_html($this->append_manual_disabled_note(__('Number of days to remember user\'s location choice (1-365).', 'multi-location-product-and-inventory-management-pro'))); ?></p>
        <?php
            },
            'location-advance-settings',
            'mulopimfwc_advanced_settings_section'
        );

        // );

        // Add "Contribute to Plugincy" field
        add_settings_field(
            'allow_data_share',
            __('Contribute to Plugincy', 'multi-location-product-and-inventory-management-pro'),
            function () {
                $this->render_advance_checkbox("allow_data_share", __("We collect non-sensitive technical details from your website, like the PHP version and features usage, to help us troubleshoot issues faster, make informed development decisions, and build features that truly benefit you.", 'multi-location-product-and-inventory-management-pro'));
            },
            'location-advance-settings',
            'mulopimfwc_advanced_settings_section'
        );

        add_settings_field(
            'mulopimfwc_clear_cache',
            __('Cache Cleanup', 'multi-location-product-and-inventory-management-pro'),
            function () {
        ?>
            <button type="button" id="mulopimfwc_clear_cache" class="button button-secondary">
                <span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
                <?php echo esc_html_e('Clear Plugin Cache', 'multi-location-product-and-inventory-management-pro'); ?>
            </button>
            <p class="description"><?php echo esc_html__('Clear cached dashboard metrics, investment/revenue charts, and filter data. Useful after price or stock updates.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            <div id="mulopimfwc_clear_cache_status" style="margin-top: 10px;"></div>
        <?php
            },
            'location-advance-settings',
            'mulopimfwc_advanced_settings_section'
        );

        // Add section for Import/Export Settings
        add_settings_section(
            'mulopimfwc_import_export_section',
            sprintf(
                '<svg xmlns="http://www.w3.org/2000/svg" 
     viewBox="0 0 367 367" 
     xml:space="preserve"
     width="20" height="20" 
     style="margin-right:6px;vertical-align:middle;background-color:#dcfce7;padding:10px;border-radius:6px">
  <path fill="#16a34a" d="M175 252.501c-8.285 0-15-6.716-15-15s6.716-15 15-15l65 .001h30V158.5H145c-8.283 0-15-6.716-15-15v-125H15c-8.283 0-15 6.716-15 15v300c0 8.284 6.717 15 15 15h240c8.285 0 15-6.716 15-15v-80.998h-30zm191.925-16.478c-.022-.225-.064-.442-.096-.664-.038-.263-.068-.526-.12-.786-.051-.254-.119-.499-.182-.747-.058-.226-.107-.453-.175-.677-.073-.242-.164-.477-.249-.713-.081-.225-.155-.452-.246-.674-.092-.221-.199-.432-.301-.646-.107-.23-.209-.46-.329-.684-.11-.205-.235-.4-.355-.6-.132-.221-.257-.443-.4-.658-.146-.219-.31-.425-.467-.635-.136-.182-.262-.368-.406-.544q-.45-.547-.948-1.049c-.016-.016-.028-.033-.045-.05l-37.499-37.501c-5.857-5.857-15.355-5.857-21.213 0s-5.858 15.355-.001 21.213l11.893 11.895H270v30h45.787l-11.893 11.893c-5.858 5.858-5.858 15.355 0 21.213a14.95 14.95 0 0 0 10.606 4.394c3.84 0 7.678-1.464 10.607-4.394l37.498-37.499q.011-.013.022-.023.512-.514.972-1.075c.146-.177.272-.364.409-.547.156-.209.318-.414.465-.632.145-.216.27-.441.402-.662.117-.198.242-.392.352-.596.121-.225.223-.458.332-.688.101-.213.207-.423.298-.643.093-.223.167-.451.248-.678.085-.234.175-.467.247-.708.068-.225.119-.454.176-.683.063-.246.132-.49.182-.741.052-.261.082-.524.12-.788.032-.221.073-.438.096-.663q.072-.729.073-1.46l.002-.02-.002-.025a15 15 0 0 0-.074-1.455"/>
  <path fill="#16a34a" d="M261.214 128.5 160 27.287V128.5h58.787z"/>
</svg> %s',
__('Import & Export Settings', 'multi-location-product-and-inventory-management-pro'),
    ),        function () {
                echo '<p>' . esc_html__('Configure options for importing and exporting location-based product data.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            },
            'lwp-import-export-settings'
        );
        // Add "Export Plugin Settings" field
        add_settings_field(
            'export_plugin_settings',
            __('Export Plugin Settings', 'multi-location-product-and-inventory-management-pro'),
            function () {
        ?>
            <button type="button" id="mulopimfwc_export_settings" class="button button-secondary">
                <span class="dashicons dashicons-download" style="margin-top: 3px;"></span>
                <?php echo esc_html_e('Export Settings', 'multi-location-product-and-inventory-management-pro'); ?>
            </button>
            <p class="description"><?php echo esc_html_e('Export all plugin settings as a JSON file for backup or migration.', 'multi-location-product-and-inventory-management-pro'); ?></p>
        <?php
            },
            'lwp-import-export-settings',
            'mulopimfwc_import_export_section'
        );

        // Add "Import Plugin Settings" field
        add_settings_field(
            'import_plugin_settings',
            __('Import Plugin Settings', 'multi-location-product-and-inventory-management-pro'),
            function () {
        ?>
            <input type="file" id="mulopimfwc_import_settings" accept=".json" style="display: none;">
            <button type="button" id="mulopimfwc_import_settings_btn" class="button button-secondary">
                <span class="dashicons dashicons-upload" style="margin-top: 3px;"></span>
                <?php echo esc_html_e('Import Settings', 'multi-location-product-and-inventory-management-pro'); ?>
            </button>
            <p class="description"><?php echo esc_html_e('Import plugin settings from a previously exported JSON file.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            <div id="import-status" style="margin-top: 10px;"></div>
        <?php
            },
            'lwp-import-export-settings',
            'mulopimfwc_import_export_section'
        );

        // Add section for API & Webhooks Settings
        add_settings_section(
            'mulopimfwc_api_webhooks_section',
            sprintf(
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" style="margin-right:6px;vertical-align:middle;background-color:#e0e7ff;padding:10px;border-radius:6px"><path fill="#6366f1" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg> %s',__('API & Webhooks', 'multi-location-product-and-inventory-management-pro'),
          ),  function () {
                echo '<p>' . esc_html__('Configure REST API endpoints and webhooks for syncing inventory data from external systems like WMS or POS.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            },
            'lwp-api-webhooks-settings'
        );

        // Add API & Webhooks Settings field
        add_settings_section(
            'api_webhooks_settings',
            __('API & Webhooks Configuration', 'multi-location-product-and-inventory-management-pro'),
            function () {
                $api_settings = new MULOPIMFWC_API_Settings();
                $api_settings->render_api_settings_field();
            },
            'lwp-api-webhooks-settings',
            'mulopimfwc_api_webhooks_section'
        );

        // Admin & browser notification settings
        add_settings_section(
            'mulopimfwc_admin_notifications_section',
            sprintf(
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" style="margin-right:6px;vertical-align:middle;background-color:#f0f9ff;padding:10px;border-radius:6px"><path fill="#1d4ed8" d="M12 3a5 5 0 0 0-5 5v3.586L5.293 13.293a1 1 0 0 0 1.414 1.414L9 12.414V12a3 3 0 1 1 6 0v.414l2.293 2.293a1 1 0 1 0 1.414-1.414L17 11.586V8a5 5 0 0 0-5-5zm0 17a3 3 0 0 0 3-3H9a3 3 0 0 0 3 3z"/></svg> %s', __('Admin Notifications', 'multi-location-product-and-inventory-management-pro'),
            ),
            function () {
                echo '<p>' . esc_html__('Keep admins and browsers in sync with live status alerts, PWA notices, and floating summaries.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            },
            'lwp-admin-notification-settings'
        );

        add_settings_field(
            'notification_realtime_enabled',
            __('Real-time alerts', 'multi-location-product-and-inventory-management-pro'),
            function () {
                $options = $this->get_display_options();
                $notif = isset($options['notification_settings']) ? $options['notification_settings'] : [];
                $value = isset($notif['realtime_enabled']) ? $notif['realtime_enabled'] : '';
                $disabled = !mulopimfwc_premium_feature();
        ?>
            <?php $this->render_checkbox_unchecked_input('mulopimfwc_display_options[notification_settings][realtime_enabled]', $disabled); ?>
            <label class="mulopimfwc_switch <?php echo $disabled ? 'mulopimfwc_pro_only' : ''; ?>">
                <input <?php echo $disabled ? 'disabled' : ''; ?> type="checkbox" name="mulopimfwc_display_options[notification_settings][realtime_enabled]" value="on" <?php checked($value, 'on'); ?>>
                <span class="mulopimfwc_slider round"></span>
                <span class="mulopimfwc_switch-on"><?php echo esc_html__('On', 'multi-location-product-and-inventory-management-pro'); ?></span>
                <span class="mulopimfwc_switch-off"><?php echo esc_html__('Off', 'multi-location-product-and-inventory-management-pro'); ?></span>
            </label>
            <p class="description"><?php echo esc_html__('Enable background polling for live dashboard metrics and alerts.', 'multi-location-product-and-inventory-management-pro'); ?></p>
        <?php
            },
            'lwp-admin-notification-settings',
            'mulopimfwc_admin_notifications_section'
        );

        add_settings_field(
            'notification_floating_enabled',
            __('Floating notifications', 'multi-location-product-and-inventory-management-pro'),
            function () {
                $options = $this->get_display_options();
                $notif = isset($options['notification_settings']) ? $options['notification_settings'] : [];
                $value = isset($notif['floating_enabled']) ? $notif['floating_enabled'] : '';
                $disabled = !mulopimfwc_premium_feature();
        ?>
            <?php $this->render_checkbox_unchecked_input('mulopimfwc_display_options[notification_settings][floating_enabled]', $disabled); ?>
            <label class="mulopimfwc_switch <?php echo $disabled ? 'mulopimfwc_pro_only' : ''; ?>">
                <input <?php echo $disabled ? 'disabled' : ''; ?> type="checkbox" name="mulopimfwc_display_options[notification_settings][floating_enabled]" value="on" <?php checked($value, 'on'); ?>>
                <span class="mulopimfwc_slider round"></span>
                <span class="mulopimfwc_switch-on"><?php echo esc_html__('On', 'multi-location-product-and-inventory-management-pro'); ?></span>
                <span class="mulopimfwc_switch-off"><?php echo esc_html__('Off', 'multi-location-product-and-inventory-management-pro'); ?></span>
            </label>
            <p class="description"><?php echo esc_html__('Show brief floating toasts in the admin header when new alerts arrive.', 'multi-location-product-and-inventory-management-pro'); ?></p>
        <?php
            },
            'lwp-admin-notification-settings',
            'mulopimfwc_admin_notifications_section'
        );

        add_settings_field(
            'notification_floating_position',
            __('Floating position', 'multi-location-product-and-inventory-management-pro'),
            function () {
                $options = $this->get_display_options();
                $notif = isset($options['notification_settings']) ? $options['notification_settings'] : [];
                $value = isset($notif['floating_position']) ? $notif['floating_position'] : 'top-right';
                $positions = [
                    'bottom-right' => __('Bottom right', 'multi-location-product-and-inventory-management-pro'),
                    'top-right' => __('Top right', 'multi-location-product-and-inventory-management-pro'),
                    'bottom-left' => __('Bottom left', 'multi-location-product-and-inventory-management-pro'),
                    'top-left' => __('Top left', 'multi-location-product-and-inventory-management-pro'),
                ];
        ?>
            <div class="mulopimfwc-position-selector <?php echo !mulopimfwc_premium_feature() ? 'mulopimfwc_pro_only' : ''; ?>">
                <div class="mulopimfwc-position-grid">
                    <?php foreach ($positions as $pos_key => $pos_label): ?>
                        <label class="mulopimfwc-position-card <?php echo $value === $pos_key ? 'selected' : ''; ?> <?php echo !mulopimfwc_premium_feature() ? 'disabled' : ''; ?>">
                            <input <?php echo !mulopimfwc_premium_feature() ? 'disabled' : ''; ?> type="radio" name="mulopimfwc_display_options[notification_settings][floating_position]" value="<?php echo esc_attr($pos_key); ?>" <?php checked($value, $pos_key); ?>>
                            <div class="mulopimfwc-position-visual">
                                <div class="mulopimfwc-screen-preview">
                                    <?php
                                    $is_top = strpos($pos_key, 'top') !== false;
                                    $is_right = strpos($pos_key, 'right') !== false;
                                    $is_bottom = strpos($pos_key, 'bottom') !== false;
                                    $is_left = strpos($pos_key, 'left') !== false;
                                    ?>
                                    <div class="mulopimfwc-notification-preview" style="
                                            <?php echo $is_top ? 'top: 8px;' : 'bottom: 8px;'; ?>
                                            <?php echo $is_right ? 'right: 8px;' : 'left: 8px;'; ?>
                                        "></div>
                                </div>
                            </div>
                            <div class="mulopimfwc-position-label"><?php echo esc_html($pos_label); ?></div>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="description"><?php echo esc_html__('Choose where floating alerts should appear.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            </div>
            <style>
                .mulopimfwc-position-selector {
                    margin: 15px 0;
                }

                .mulopimfwc-position-grid {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: 15px;
                    margin-bottom: 15px;
                }

                .mulopimfwc-position-card {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    padding: 15px;
                    border: 2px solid #ddd;
                    border-radius: 8px;
                    cursor: pointer;
                    transition: all 0.2s;
                    background: #fff;
                    position: relative;
                }

                .mulopimfwc-position-card:hover {
                    border-color: #2271b1;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                }

                .mulopimfwc-position-card.selected {
                    border-color: #2271b1;
                    background: #f0f6ff;
                }

                .mulopimfwc-position-card input[type="radio"] {
                    position: absolute;
                    opacity: 0;
                    pointer-events: none;
                }

                .mulopimfwc-position-visual {
                    width: 100%;
                    margin-bottom: 10px;
                }

                .mulopimfwc-screen-preview {
                    width: 100%;
                    height: 80px;
                    background: #f5f5f5;
                    border-radius: 4px;
                    position: relative;
                    border: 1px solid #ddd;
                }

                .mulopimfwc-notification-preview {
                    width: 30px;
                    height: 20px;
                    background: <?php echo $value === 'bottom-right' ? '#2271b1' : '#999'; ?>;
                    border-radius: 3px;
                    position: absolute;
                    transition: background 0.2s;
                }

                .mulopimfwc-position-card.selected .mulopimfwc-notification-preview {
                    background: #2271b1;
                }

                .mulopimfwc-position-label {
                    font-size: 13px;
                    font-weight: 500;
                    color: #1d2327;
                    text-align: center;
                }

                .mulopimfwc-position-card.selected .mulopimfwc-position-label {
                    color: #2271b1;
                    font-weight: 600;
                }
            </style>
            <script>
                jQuery(document).ready(function($) {
                    $('.mulopimfwc-position-card').on('click', function() {
                        if ($(this).hasClass('disabled')) {
                            return;
                        }
                        $('.mulopimfwc-position-card').removeClass('selected');
                        $(this).addClass('selected');
                        $(this).find('input[type="radio"]').prop('checked', true);
                        // Update preview colors
                        $('.mulopimfwc-notification-preview').css('background', '#999');
                        $('.mulopimfwc-position-card.selected .mulopimfwc-notification-preview').css('background', '#2271b1');
                    });
                });
            </script>
        <?php
            },
            'lwp-admin-notification-settings',
            'mulopimfwc_admin_notifications_section'
        );

        add_settings_field(
            'notification_floating_size',
            __('Size', 'multi-location-product-and-inventory-management-pro'),
            function () {
                $options = $this->get_display_options();
                $notif = isset($options['notification_settings']) ? $options['notification_settings'] : [];
                $value = isset($notif['floating_size']) ? $notif['floating_size'] : 'comfy';
                $sizes = [
                    'comfy' => __('Comfy', 'multi-location-product-and-inventory-management-pro'),
                    'compact' => __('Compact', 'multi-location-product-and-inventory-management-pro'),
                ];
        ?>
            <div class="mulopimfwc-size-selector <?php echo !mulopimfwc_premium_feature() ? 'mulopimfwc_pro_only' : ''; ?>">
                <div class="mulopimfwc-size-grid">
                    <?php foreach ($sizes as $size_key => $size_label): ?>
                        <label class="mulopimfwc-size-card <?php echo $value === $size_key ? 'selected' : ''; ?> <?php echo !mulopimfwc_premium_feature() ? 'disabled' : ''; ?>">
                            <input <?php echo !mulopimfwc_premium_feature() ? 'disabled' : ''; ?> type="radio" name="mulopimfwc_display_options[notification_settings][floating_size]" value="<?php echo esc_attr($size_key); ?>" <?php checked($value, $size_key); ?>>
                            <div class="mulopimfwc-size-visual">
                                <div class="mulopimfwc-size-preview <?php echo esc_attr( $size_key ); ?>">
                                    <div class="mulopimfwc-size-icon"></div>
                                    <div class="mulopimfwc-size-lines">
                                        <div class="mulopimfwc-size-line line-1"></div>
                                        <div class="mulopimfwc-size-line line-2"></div>
                                        <?php if ($size_key === 'comfy'): ?>
                                            <div class="mulopimfwc-size-line line-3"></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="mulopimfwc-size-label"><?php echo esc_html($size_label); ?></div>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="description"><?php echo esc_html__('Choose the size of floating notifications.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            </div>
            <style>
                .mulopimfwc-size-selector {
                    margin: 15px 0;
                }

                .mulopimfwc-size-grid {
                    display: grid;
                    grid-template-columns: repeat(2, 1fr);
                    gap: 15px;
                    margin-bottom: 15px;
                    max-width: 400px;
                }

                .mulopimfwc-size-card {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    padding: 15px;
                    border: 2px solid #ddd;
                    border-radius: 8px;
                    cursor: pointer;
                    transition: all 0.2s;
                    background: #fff;
                    position: relative;
                }

                .mulopimfwc-size-card:hover {
                    border-color: #2271b1;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                }

                .mulopimfwc-size-card.selected {
                    border-color: #2271b1;
                    background: #f0f6ff;
                }

                .mulopimfwc-size-card input[type="radio"] {
                    position: absolute;
                    opacity: 0;
                    pointer-events: none;
                }

                .mulopimfwc-size-visual {
                    width: 100%;
                    margin-bottom: 10px;
                }

                .mulopimfwc-size-preview {
                    width: 100%;
                    height: 60px;
                    background: <?php echo $value === 'comfy' ? '#e8f0fe' : '#f5f5f5'; ?>;
                    border-radius: 4px;
                    display: flex;
                    align-items: center;
                    padding: 10px;
                    gap: 10px;
                    transition: all 0.2s;
                    box-sizing: border-box;
                }

                .mulopimfwc-size-card.selected .mulopimfwc-size-preview {
                    background: #e8f0fe;
                }

                .mulopimfwc-size-icon {
                    width: <?php echo $value === 'comfy' ? '24px' : '16px'; ?>;
                    height: <?php echo $value === 'comfy' ? '24px' : '16px'; ?>;
                    background: <?php echo $value === 'comfy' ? '#2271b1' : '#999'; ?>;
                    border-radius: 50%;
                    flex-shrink: 0;
                    transition: all 0.2s;
                }

                .mulopimfwc-size-card.selected .mulopimfwc-size-icon {
                    background: #2271b1;
                }

                .mulopimfwc-size-lines {
                    flex: 1;
                    display: flex;
                    flex-direction: column;
                    gap: 4px;
                }

                .mulopimfwc-size-line {
                    height: <?php echo $value === 'comfy' ? '6px' : '4px'; ?>;
                    background: <?php echo $value === 'comfy' ? '#a0c4ff' : '#ccc'; ?>;
                    border-radius: 2px;
                    transition: all 0.2s;
                }

                .mulopimfwc-size-card.selected .mulopimfwc-size-line {
                    background: #a0c4ff;
                }

                .mulopimfwc-size-preview.comfy .mulopimfwc-size-line.line-1 {
                    width: 90%;
                }

                .mulopimfwc-size-preview.comfy .mulopimfwc-size-line.line-2 {
                    width: 70%;
                }

                .mulopimfwc-size-preview.comfy .mulopimfwc-size-line.line-3 {
                    width: 50%;
                }

                .mulopimfwc-size-preview.compact .mulopimfwc-size-line.line-1 {
                    width: 80%;
                }

                .mulopimfwc-size-preview.compact .mulopimfwc-size-line.line-2 {
                    width: 60%;
                }

                .mulopimfwc-size-preview.compact .mulopimfwc-size-line.line-3 {
                    width: 40%;
                }

                .mulopimfwc-size-label {
                    font-size: 13px;
                    font-weight: 500;
                    color: #1d2327;
                    text-align: center;
                }

                .mulopimfwc-size-card.selected .mulopimfwc-size-label {
                    color: #2271b1;
                    font-weight: 600;
                }
            </style>
            <script>
                jQuery(document).ready(function($) {
                    $('.mulopimfwc-size-card').on('click', function() {
                        if ($(this).hasClass('disabled')) {
                            return;
                        }
                        $('.mulopimfwc-size-card').removeClass('selected');
                        $(this).addClass('selected');
                        $(this).find('input[type="radio"]').prop('checked', true);
                        // Update preview colors
                        const isComfy = $(this).find('input').val() === 'comfy';
                        $('.mulopimfwc-size-preview').css('background', '#f5f5f5');
                        $('.mulopimfwc-size-icon').css('background', '#999');
                        $('.mulopimfwc-size-line').css('background', '#ccc');
                        if (isComfy) {
                            $('.mulopimfwc-size-card.selected .mulopimfwc-size-preview').css('background', '#e8f0fe');
                            $('.mulopimfwc-size-card.selected .mulopimfwc-size-icon').css('background', '#2271b1');
                            $('.mulopimfwc-size-card.selected .mulopimfwc-size-line').css('background', '#a0c4ff');
                        } else {
                            $('.mulopimfwc-size-card.selected .mulopimfwc-size-preview').css('background', '#f5f5f5');
                            $('.mulopimfwc-size-card.selected .mulopimfwc-size-icon').css('background', '#999');
                            $('.mulopimfwc-size-card.selected .mulopimfwc-size-line').css('background', '#ccc');
                        }
                    });
                });
            </script>
        <?php
            },
            'lwp-admin-notification-settings',
            'mulopimfwc_admin_notifications_section'
        );

        add_settings_field(
            'notification_floating_duration',
            __('Floating duration (milliseconds)', 'multi-location-product-and-inventory-management-pro'),
            function () {
                $options = $this->get_display_options();
                $notif = isset($options['notification_settings']) ? $options['notification_settings'] : [];
                $value = isset($notif['floating_duration']) ? $notif['floating_duration'] : '6000';
        ?>
            <div class="<?php echo !mulopimfwc_premium_feature() ? 'mulopimfwc_pro_only' : ''; ?>">
                <input <?php echo !mulopimfwc_premium_feature() ? 'disabled' : ''; ?> type="number" min="2000" step="500" name="mulopimfwc_display_options[notification_settings][floating_duration]" value="<?php echo esc_attr($value); ?>">
                <p class="description"><?php echo esc_html__('How long floating alerts remain visible.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            </div>
        <?php
            },
            'lwp-admin-notification-settings',
            'mulopimfwc_admin_notifications_section'
        );

        add_settings_field(
            'notification_template',
            __('Notification template', 'multi-location-product-and-inventory-management-pro'),
            function () {
                $options = $this->get_display_options();
                $notif = isset($options['notification_settings']) ? $options['notification_settings'] : [];
                $value = isset($notif['notification_template']) ? $notif['notification_template'] : '[{event}] {message}';
        ?>
            <div class="<?php echo !mulopimfwc_premium_feature() ? 'mulopimfwc_pro_only' : ''; ?>">
                <textarea <?php echo !mulopimfwc_premium_feature() ? 'disabled' : ''; ?>
                    name="mulopimfwc_display_options[notification_settings][notification_template]" rows="3" class="large-text"><?php echo esc_textarea($value); ?></textarea>
                <p class="description"><?php echo esc_html__('Use {event} and {message} placeholders to customize floating and PWA text.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            <?php
            },
            'lwp-admin-notification-settings',
            'mulopimfwc_admin_notifications_section'
        );

        add_settings_field(
            'notification_pwa_enabled',
            __('Browser push (PWA)', 'multi-location-product-and-inventory-management-pro'),
            function () {
                $options = $this->get_display_options();
                $notif = isset($options['notification_settings']) ? $options['notification_settings'] : [];
                $value = isset($notif['pwa_enabled']) ? $notif['pwa_enabled'] : 'off';
                $disabled = !mulopimfwc_premium_feature();
            ?>
                <?php $this->render_checkbox_unchecked_input('mulopimfwc_display_options[notification_settings][pwa_enabled]', $disabled); ?>
                <label class="mulopimfwc_switch <?php echo $disabled ? 'mulopimfwc_pro_only' : ''; ?>">
                    <input <?php echo $disabled ? 'disabled' : ''; ?> type="checkbox" name="mulopimfwc_display_options[notification_settings][pwa_enabled]" value="on" <?php checked($value, 'on'); ?>>
                    <span class="mulopimfwc_slider round"></span>
                    <span class="mulopimfwc_switch-on"><?php echo esc_html__('On', 'multi-location-product-and-inventory-management-pro'); ?></span>
                    <span class="mulopimfwc_switch-off"><?php echo esc_html__('Off', 'multi-location-product-and-inventory-management-pro'); ?></span>
                </label>
                <p class="description"><?php echo esc_html__('Send desktop/mobile notifications through a registered service worker.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            <?php
            },
            'lwp-admin-notification-settings',
            'mulopimfwc_admin_notifications_section'
        );

        // add test PWA notification field
        add_settings_field(
            'test_pwa_notification',
            __('Test PWA Notification', 'multi-location-product-and-inventory-management-pro'),
            function () {
                // In your settings page
                echo '<button ' . (!mulopimfwc_premium_feature() ? 'disabled' : '') . ' type="button" id="test-push-notification" class="button">' . esc_html(__('Test Push Notification', 'multi-location-product-and-inventory-management-pro')) . '</button>';
            },
            'lwp-admin-notification-settings',
            'mulopimfwc_admin_notifications_section'
        );

        // Test Notifications panel (floating + email)
        add_settings_field(
            'mulopimfwc_test_notifications_panel',
            __('Test Notifications', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_locations;

                $locations = (!empty($mulopimfwc_locations) && !is_wp_error($mulopimfwc_locations)) ? $mulopimfwc_locations : [];
            ?>
                <div id="mulopimfwc-test-notifications-panel <?php echo !mulopimfwc_premium_feature() ? 'mulopimfwc_pro_only' : ''; ?>" style="max-width: 900px; background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:14px;">
                    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; justify-content:space-between;">
                        <div>
                            <strong style="display:block; margin-bottom:2px;"><?php echo esc_html__('Quick tests', 'multi-location-product-and-inventory-management-pro'); ?></strong>
                            <span class="description"><?php echo esc_html__('Use this to verify floating notifications and email delivery/routing.', 'multi-location-product-and-inventory-management-pro'); ?></span>
                        </div>
                        <button <?php echo !mulopimfwc_premium_feature() ? 'disabled' : ''; ?> type="button" id="mulopimfwc-test-floating-notification" class="button button-secondary">
                            <?php echo esc_html__('Test Floating Notification', 'multi-location-product-and-inventory-management-pro'); ?>
                        </button>
                    </div>

                    <hr style="margin:14px 0; border:0; border-top:1px solid #e5e7eb;">

                    <div style="display:grid; grid-template-columns: 220px 1fr; gap:10px; align-items:center;">
                        <label for="mulopimfwc-test-email-recipient-type" style="font-weight:600;"><?php echo esc_html__('Send test email to', 'multi-location-product-and-inventory-management-pro'); ?></label>
                        <select <?php echo !mulopimfwc_premium_feature() ? 'disabled' : ''; ?> id="mulopimfwc-test-email-recipient-type">
                            <option value="admin_email"><?php echo esc_html__('Admin email', 'multi-location-product-and-inventory-management-pro'); ?></option>
                            <option value="location_contact"><?php echo esc_html__('Location contact email', 'multi-location-product-and-inventory-management-pro'); ?></option>
                            <option value="location_manager"><?php echo esc_html__('Location manager (assigned)', 'multi-location-product-and-inventory-management-pro'); ?></option>
                            <option value="custom"><?php echo esc_html__('Custom email address', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        </select>

                        <label for="mulopimfwc-test-email-location" class="mulopimfwc-test-email-location-label" style="font-weight:600;"><?php echo esc_html__('Location', 'multi-location-product-and-inventory-management-pro'); ?></label>
                        <select <?php echo !mulopimfwc_premium_feature() ? 'disabled' : ''; ?> id="mulopimfwc-test-email-location" class="mulopimfwc-test-email-location">
                            <option value=""><?php echo esc_html__('Select a location…', 'multi-location-product-and-inventory-management-pro'); ?></option>
                            <?php foreach ($locations as $loc) : ?>
                                <option value="<?php echo esc_attr(rawurldecode($loc->slug)); ?>"><?php echo esc_html($loc->name); ?></option>
                            <?php endforeach; ?>
                        </select>

                        <label for="mulopimfwc-test-email-manager" class="mulopimfwc-test-email-manager-label" style="font-weight:600;"><?php echo esc_html__('Location manager', 'multi-location-product-and-inventory-management-pro'); ?></label>
                        <select <?php echo !mulopimfwc_premium_feature() ? 'disabled' : ''; ?> id="mulopimfwc-test-email-manager" class="mulopimfwc-test-email-manager">
                            <option value="all"><?php echo esc_html__('All managers for selected location', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        </select>

                        <label for="mulopimfwc-test-email-custom" class="mulopimfwc-test-email-custom-label" style="font-weight:600;"><?php echo esc_html__('Email address', 'multi-location-product-and-inventory-management-pro'); ?></label>
                        <input type="email" <?php echo !mulopimfwc_premium_feature() ? 'disabled' : ''; ?> id="mulopimfwc-test-email-custom" class="mulopimfwc-test-email-custom" placeholder="<?php echo esc_attr__('name@example.com', 'multi-location-product-and-inventory-management-pro'); ?>" />
                    </div>

                    <div style="margin-top:12px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <button <?php echo !mulopimfwc_premium_feature() ? 'disabled' : ''; ?> type="button" id="mulopimfwc-send-test-email" class="button button-primary"><?php echo esc_html__('Send Test Email', 'multi-location-product-and-inventory-management-pro'); ?></button>
                        <span class="description"><?php echo esc_html__('This sends a simple test email and reports the resolved recipient(s).', 'multi-location-product-and-inventory-management-pro'); ?></span>
                    </div>

                    <div id="mulopimfwc-test-email-result" style="margin-top:10px;"></div>
                </div>
            <?php
            },
            'lwp-admin-notification-settings',
            'mulopimfwc_admin_notifications_section'
        );

        add_settings_field(
            'notification_poll_interval',
            __('Poll interval (milliseconds)', 'multi-location-product-and-inventory-management-pro'),
            function () {
                $options = $this->get_display_options();
                $notif = isset($options['notification_settings']) ? $options['notification_settings'] : [];
                $value = isset($notif['poll_interval']) ? $notif['poll_interval'] : '30000';
            ?>
                <div class="<?php echo !mulopimfwc_premium_feature() ? 'mulopimfwc_pro_only' : ''; ?>">
                    <input <?php echo !mulopimfwc_premium_feature() ? 'disabled' : ''; ?> type="number" min="5000" step="5000" name="mulopimfwc_display_options[notification_settings][poll_interval]" value="<?php echo esc_attr($value); ?>">
                    <p class="description"><?php echo esc_html__('How often to check for new alerts (minimum 5000ms). Lower values increase server load.', 'multi-location-product-and-inventory-management-pro'); ?></p>
                </div>
            <?php
            },
            'lwp-admin-notification-settings',
            'mulopimfwc_admin_notifications_section'
        );

        add_settings_field(
            'notification_style',
            __('Notification style', 'multi-location-product-and-inventory-management-pro'),
            function () {
                $options = $this->get_display_options();
                $notif = isset($options['notification_settings']) ? $options['notification_settings'] : [];
                $value = isset($notif['notification_style']) ? $notif['notification_style'] : 'modern';
            ?>
                <div class="<?php echo !mulopimfwc_premium_feature() ? 'mulopimfwc_pro_only' : ''; ?>">
                    <select <?php echo !mulopimfwc_premium_feature() ? 'disabled' : ''; ?>
                        name="mulopimfwc_display_options[notification_settings][notification_style]">
                        <option value="modern" <?php selected($value, 'modern'); ?>><?php echo esc_html__('Modern', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="minimal" <?php selected($value, 'minimal'); ?>><?php echo esc_html__('Minimal', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="classic" <?php selected($value, 'classic'); ?>><?php echo esc_html__('Classic', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    </select>
                    <p class="description"><?php echo esc_html__('Choose the visual style for floating notifications.', 'multi-location-product-and-inventory-management-pro'); ?></p>
                </div>
            <?php
            },
            'lwp-admin-notification-settings',
            'mulopimfwc_admin_notifications_section'
        );

        add_settings_field(
            'notification_sound_enabled',
            __('Enable notification sound', 'multi-location-product-and-inventory-management-pro'),
            function () {
                $options = $this->get_display_options();
                $notif = isset($options['notification_settings']) ? $options['notification_settings'] : [];
                $value = isset($notif['sound_enabled']) ? $notif['sound_enabled'] : 'off';
                $disabled = !mulopimfwc_premium_feature();
            ?>
                <?php $this->render_checkbox_unchecked_input('mulopimfwc_display_options[notification_settings][sound_enabled]', $disabled); ?>
                <label class="mulopimfwc_switch <?php echo $disabled ? 'mulopimfwc_pro_only' : ''; ?>">
                    <input <?php echo $disabled ? 'disabled' : ''; ?> type="checkbox" name="mulopimfwc_display_options[notification_settings][sound_enabled]" value="on" <?php checked($value, 'on'); ?>>
                    <span class="mulopimfwc_slider round"></span>
                    <span class="mulopimfwc_switch-on"><?php echo esc_html__('On', 'multi-location-product-and-inventory-management-pro'); ?></span>
                <span class="mulopimfwc_switch-off"><?php echo esc_html__('Off', 'multi-location-product-and-inventory-management-pro'); ?></span>
                </label>
                <p class="description"><?php echo esc_html__('Play a sound when new notifications arrive.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            <?php
            },
            'lwp-admin-notification-settings',
            'mulopimfwc_admin_notifications_section'
        );

        // Social Notifications (dedicated section after Import/Export)
        add_settings_section(
            'mulopimfwc_social_notifications_section',
            sprintf(
                '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="#0284c7" style="margin-right:6px;vertical-align:middle;background-color:#def4ff;padding:10px;border-radius:6px" viewBox="0 0 20 20" xml:space="preserve"><path d="M14 11.4c-.96 0-1.78.68-1.96 1.6H9.4c-.12 0-.2.1-.2.22v.18c0 .2-.02.38-.06.56-.02.12.08.24.2.24h2.82c.3.7 1.02 1.2 1.84 1.2 1.1 0 2-.9 2-2s-.9-2-2-2M8.66 9.1C8.42 9 8.2 8.86 8 8.7c-.1-.08-.24-.04-.3.06l-1.42 2.66c-.08-.02-.18-.02-.28-.02-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2c0-.58-.24-1.08-.62-1.46l1.38-2.56c.04-.1 0-.22-.1-.28M10 8.2c.2 0 .38-.02.56-.08l1.38 2.54c.06.1.18.14.28.08.22-.14.44-.26.68-.34a.19.19 0 0 0 .1-.28l-1.44-2.68c.26-.34.44-.78.44-1.24 0-1.1-.9-2-2-2s-2 .9-2 2 .9 2 2 2"/></svg> %s', 
             __('Social Notifications', 'multi-location-product-and-inventory-management-pro'),
            ),
            function () {
                echo '<p>' . esc_html__('Manage social/webhook alerts per platform. Add multiple destinations and test instantly.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            },
            'lwp-social-notifications-settings'
        );

        add_settings_section(
            'social_notifications',
            "",
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', []);
                $defaults = [
                    'enabled' => 'off',
                    'new_order' => 'on',
                    'low_stock' => 'on',
                    'out_of_stock' => 'on',
                    'daily_digest' => 'off',
                    'site_status' => 'off',
                    'daily_digest_time' => '07:00',
                    'telegram_bot_token' => '',
                    'channels' => [],
                ];
                $social = isset($options['social_notifications']) && is_array($options['social_notifications'])
                    ? wp_parse_args($options['social_notifications'], $defaults)
                    : $defaults;
                $test_nonce = wp_create_nonce('mulopimfwc_test_social_channel');
                $test_digest_nonce = wp_create_nonce('mulopimfwc_test_social_digest_channel');
                $social_disabled = !mulopimfwc_premium_feature();
            ?>
                <div class="mulopimfwc-social-card <?php echo $social_disabled ? 'mulopimfwc_pro_only' : ''; ?>" style="background:#f8fafc;border:1px solid #e2e8f0;padding:16px;border-radius:10px;max-width:1100px;">
                    <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:center;justify-content:space-between;">
                        <div>
                            <h3 style="margin:0;"><?php echo esc_html__('Social Notifications', 'multi-location-product-and-inventory-management-pro'); ?></h3>
                            <p class="description" style="margin:4px 0 0 0;"><?php echo esc_html__('Enable alerts and choose which events to send to your social/webhook destinations.', 'multi-location-product-and-inventory-management-pro'); ?></p>
                        </div>
                        <?php $this->render_checkbox_unchecked_input('mulopimfwc_display_options[social_notifications][enabled]', $social_disabled); ?>
                        <label class="mulopimfwc_switch social-master-toggle">
                            <input <?php echo $social_disabled ? 'disabled' : ''; ?> type="checkbox" name="mulopimfwc_display_options[social_notifications][enabled]" value="on" <?php checked($social['enabled'], 'on'); ?>>
                            <span class="mulopimfwc_slider round"></span>
                            <span class="mulopimfwc_switch-on"><?php echo esc_html__('On', 'multi-location-product-and-inventory-management-pro'); ?></span>
                <span class="mulopimfwc_switch-off"><?php echo esc_html__('Off', 'multi-location-product-and-inventory-management-pro'); ?></span>
                        </label>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-top:14px;">
                        <?php $this->render_checkbox_unchecked_input('mulopimfwc_display_options[social_notifications][new_order]', $social_disabled); ?><label><input <?php echo $social_disabled ? 'disabled' : ''; ?> type="checkbox" name="mulopimfwc_display_options[social_notifications][new_order]" value="on" <?php checked($social['new_order'], 'on'); ?>> <?php echo esc_html__('New order alerts', 'multi-location-product-and-inventory-management-pro'); ?></label>
                        <?php $this->render_checkbox_unchecked_input('mulopimfwc_display_options[social_notifications][low_stock]', $social_disabled); ?><label><input <?php echo $social_disabled ? 'disabled' : ''; ?> type="checkbox" name="mulopimfwc_display_options[social_notifications][low_stock]" value="on" <?php checked($social['low_stock'], 'on'); ?>> <?php echo esc_html__('Low stock alerts', 'multi-location-product-and-inventory-management-pro'); ?></label>
                        <?php $this->render_checkbox_unchecked_input('mulopimfwc_display_options[social_notifications][out_of_stock]', $social_disabled); ?><label><input <?php echo $social_disabled ? 'disabled' : ''; ?> type="checkbox" name="mulopimfwc_display_options[social_notifications][out_of_stock]" value="on" <?php checked($social['out_of_stock'], 'on'); ?>> <?php echo esc_html__('Out of stock alerts', 'multi-location-product-and-inventory-management-pro'); ?></label>
                        <?php $this->render_checkbox_unchecked_input('mulopimfwc_display_options[social_notifications][daily_digest]', $social_disabled); ?><label><input <?php echo $social_disabled ? 'disabled' : ''; ?> type="checkbox" name="mulopimfwc_display_options[social_notifications][daily_digest]" value="on" <?php checked($social['daily_digest'], 'on'); ?>> <?php echo esc_html__("Today's performance digest", 'multi-location-product-and-inventory-management-pro'); ?></label>
                        <?php $this->render_checkbox_unchecked_input('mulopimfwc_display_options[social_notifications][site_status]', $social_disabled); ?><label><input <?php echo $social_disabled ? 'disabled' : ''; ?> type="checkbox" name="mulopimfwc_display_options[social_notifications][site_status]" value="on" <?php checked($social['site_status'], 'on'); ?>> <?php echo esc_html__('Site down / up monitor', 'multi-location-product-and-inventory-management-pro'); ?></label>
                        <?php $this->render_checkbox_unchecked_input('mulopimfwc_display_options[social_notifications][payment_failed]', $social_disabled); ?><label><input <?php echo $social_disabled ? 'disabled' : ''; ?> type="checkbox" name="mulopimfwc_display_options[social_notifications][payment_failed]" value="on" <?php checked(isset($social['payment_failed']) ? $social['payment_failed'] : 'off', 'on'); ?>> <?php echo esc_html__('Payment failed', 'multi-location-product-and-inventory-management-pro'); ?></label>
                        <?php $this->render_checkbox_unchecked_input('mulopimfwc_display_options[social_notifications][order_refunded]', $social_disabled); ?><label><input <?php echo $social_disabled ? 'disabled' : ''; ?> type="checkbox" name="mulopimfwc_display_options[social_notifications][order_refunded]" value="on" <?php checked(isset($social['order_refunded']) ? $social['order_refunded'] : 'off', 'on'); ?>> <?php echo esc_html__('Order refunded', 'multi-location-product-and-inventory-management-pro'); ?></label>
                        <?php $this->render_checkbox_unchecked_input('mulopimfwc_display_options[social_notifications][order_cancelled]', $social_disabled); ?><label><input <?php echo $social_disabled ? 'disabled' : ''; ?> type="checkbox" name="mulopimfwc_display_options[social_notifications][order_cancelled]" value="on" <?php checked(isset($social['order_cancelled']) ? $social['order_cancelled'] : 'off', 'on'); ?>> <?php echo esc_html__('Order cancelled', 'multi-location-product-and-inventory-management-pro'); ?></label>
                        <?php $this->render_checkbox_unchecked_input('mulopimfwc_display_options[social_notifications][order_completed]', $social_disabled); ?><label><input <?php echo $social_disabled ? 'disabled' : ''; ?> type="checkbox" name="mulopimfwc_display_options[social_notifications][order_completed]" value="on" <?php checked(isset($social['order_completed']) ? $social['order_completed'] : 'off', 'on'); ?>> <?php echo esc_html__('Order completed', 'multi-location-product-and-inventory-management-pro'); ?></label>
                        <?php $this->render_checkbox_unchecked_input('mulopimfwc_display_options[social_notifications][high_value_order]', $social_disabled); ?><label><input <?php echo $social_disabled ? 'disabled' : ''; ?> type="checkbox" name="mulopimfwc_display_options[social_notifications][high_value_order]" value="on" <?php checked(isset($social['high_value_order']) ? $social['high_value_order'] : 'off', 'on'); ?>> <?php echo esc_html__('High-value order', 'multi-location-product-and-inventory-management-pro'); ?></label>
                        <?php $this->render_checkbox_unchecked_input('mulopimfwc_display_options[social_notifications][restocked]', $social_disabled); ?><label><input <?php echo $social_disabled ? 'disabled' : ''; ?> type="checkbox" name="mulopimfwc_display_options[social_notifications][restocked]" value="on" <?php checked(isset($social['restocked']) ? $social['restocked'] : 'off', 'on'); ?>> <?php echo esc_html__('Restocked (back above low threshold)', 'multi-location-product-and-inventory-management-pro'); ?></label>
                        <?php $this->render_checkbox_unchecked_input('mulopimfwc_display_options[social_notifications][low_review_alert]', $social_disabled); ?><label><input <?php echo $social_disabled ? 'disabled' : ''; ?> type="checkbox" name="mulopimfwc_display_options[social_notifications][low_review_alert]" value="on" <?php checked(isset($social['low_review_alert']) ? $social['low_review_alert'] : 'off', 'on'); ?>> <?php echo esc_html__('Low-rating product review', 'multi-location-product-and-inventory-management-pro'); ?></label>
                        <?php $this->render_checkbox_unchecked_input('mulopimfwc_display_options[social_notifications][manager_change_alert]', $social_disabled); ?><label><input <?php echo $social_disabled ? 'disabled' : ''; ?> type="checkbox" name="mulopimfwc_display_options[social_notifications][manager_change_alert]" value="on" <?php checked(isset($social['manager_change_alert']) ? $social['manager_change_alert'] : 'off', 'on'); ?>> <?php echo esc_html__('Manager change (added/updated)', 'multi-location-product-and-inventory-management-pro'); ?></label>
                    </div>
                    <div style="margin-top:12px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                        <?php
                        $tz_string = get_option('timezone_string');
                        $gmt_offset = floatval(get_option('gmt_offset'));
                        $tz_label = $tz_string ? $tz_string : 'UTC' . ($gmt_offset ? ' ' . $gmt_offset : '');
                        $now_utc = current_time('timestamp', true);
                        ?>
                        <label style="font-weight:600;">
                            <?php echo esc_html__('Digest time (Current time: ', 'multi-location-product-and-inventory-management-pro'); ?>
                            <span id="mulopimfwc-digest-clock" data-start-utc="<?php echo esc_attr($now_utc); ?>" data-gmt-offset="<?php echo esc_attr($gmt_offset); ?>" data-tz-label="<?php echo esc_attr($tz_label); ?>">--:--:--</span>
                            <?php echo esc_html(')'); ?>
                        </label>
                        <input <?php echo !mulopimfwc_premium_feature() ? 'disabled' : ''; ?> type="time" name="mulopimfwc_display_options[social_notifications][daily_digest_time]" value="<?php echo esc_attr($social['daily_digest_time']); ?>" />
                        <label style="font-weight:600;"><?php echo esc_html__('Default Telegram bot token', 'multi-location-product-and-inventory-management-pro'); ?></label>
                        <input <?php echo !mulopimfwc_premium_feature() ? 'disabled' : ''; ?> type="text" name="mulopimfwc_display_options[social_notifications][telegram_bot_token]" value="<?php echo esc_attr($social['telegram_bot_token']); ?>" placeholder="<?php echo esc_attr__('123456:ABCDEF-TOKEN (optional global fallback)', 'multi-location-product-and-inventory-management-pro'); ?>" style="min-width:260px;">
                        <label style="font-weight:600;"><?php echo esc_html__('High value threshold', 'multi-location-product-and-inventory-management-pro'); ?></label>
                        <input <?php echo !mulopimfwc_premium_feature() ? 'disabled' : ''; ?> type="number" step="0.01" min="0" name="mulopimfwc_display_options[social_notifications][high_value_threshold]" value="<?php echo esc_attr(isset($social['high_value_threshold']) ? $social['high_value_threshold'] : '500'); ?>" style="width:120px;">
                        <label style="font-weight:600;"><?php echo esc_html__('Low review rating threshold', 'multi-location-product-and-inventory-management-pro'); ?></label>
                        <input <?php echo !mulopimfwc_premium_feature() ? 'disabled' : ''; ?> type="number" step="0.1" min="1" max="5" name="mulopimfwc_display_options[social_notifications][low_review_threshold]" value="<?php echo esc_attr(isset($social['low_review_threshold']) ? $social['low_review_threshold'] : '3'); ?>" style="width:80px;">
                    </div>
                </div>

                <div class="lwp-settings-section" style="margin-top:16px;">
                    <div class="lwp-settings-box">
                        <div class="lwp-filter-settings">
                            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                                <div>
                                    <h4 style="margin:0;"><?php echo esc_html__('Social Channels', 'multi-location-product-and-inventory-management-pro'); ?></h4>
                                    <p class="description" style="margin:4px 0 0 0;"><?php echo esc_html__('Add one or more destinations. Use webhook URL for Slack/Teams/Discord/Custom; use chat ID/token for Telegram.', 'multi-location-product-and-inventory-management-pro'); ?></p>
                                </div>
                                <button <?php echo !mulopimfwc_premium_feature() ? 'disabled' : ''; ?> type="button" class="button button-primary" id="mulopimfwc-add-social-channel"><?php echo esc_html__('Add Social Media', 'multi-location-product-and-inventory-management-pro'); ?></button>
                            </div>
                            <div id="mulopimfwc-social-empty" class="description" style="margin-top:10px;<?php echo empty($social['channels']) ? '' : 'display:none;'; ?>">
                                <?php echo esc_html__('No social media added yet. Click “Add Social Media” to create one.', 'multi-location-product-and-inventory-management-pro'); ?>
                            </div>
                            <div id="mulopimfwc-social-channels" style="margin-top:10px;">
                                <?php
                                $channels = isset($social['channels']) && is_array($social['channels']) ? $social['channels'] : [];
                                foreach ($channels as $index => $channel) :
                                    $type = isset($channel['type']) ? $channel['type'] : '';
                                    $label = isset($channel['label']) ? $channel['label'] : '';
                                    $webhook = isset($channel['webhook']) ? $channel['webhook'] : '';
                                    $chat_id = isset($channel['chat_id']) ? $channel['chat_id'] : '';
                                    $bot_token = isset($channel['bot_token']) ? $channel['bot_token'] : '';
                                ?>
                                    <div class="mulopimfwc-social-channel-row" style="border:1px solid #e2e8f0;border-radius:10px;padding:12px;margin-bottom:10px;background:#fff;">
                                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;align-items:center;">
                                            <div>
                                                <label style="font-weight:600;"><?php echo esc_html__('Platform', 'multi-location-product-and-inventory-management-pro'); ?></label>
                                                <select <?php echo !mulopimfwc_premium_feature() ? 'disabled' : ''; ?> name="mulopimfwc_display_options[social_notifications][channels][<?php echo esc_attr($index); ?>][type]" class="mulopimfwc-channel-type" style="width:100%;">
                                                    <option value=""><?php echo esc_html__('Select...', 'multi-location-product-and-inventory-management-pro'); ?></option>
                                                    <option value="slack" <?php selected($type, 'slack'); ?>>Slack</option>
                                                    <option value="teams" <?php selected($type, 'teams'); ?>>Microsoft Teams</option>
                                                    <option value="discord" <?php selected($type, 'discord'); ?>>Discord</option>
                                                    <option value="telegram" <?php selected($type, 'telegram'); ?>>Telegram</option>
                                                    <option value="custom" <?php selected($type, 'custom'); ?>>Custom Webhook</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label style="font-weight:600;"><?php echo esc_html__('Label (optional)', 'multi-location-product-and-inventory-management-pro'); ?></label>
                                                <input <?php echo !mulopimfwc_premium_feature() ? 'disabled' : ''; ?> type="text" name="mulopimfwc_display_options[social_notifications][channels][<?php echo esc_attr($index); ?>][label]" value="<?php echo esc_attr($label); ?>" placeholder="<?php echo esc_attr__('e.g., Admin alerts', 'multi-location-product-and-inventory-management-pro'); ?>" style="width:100%;">
                                            </div>
                                            <div class="mulopimfwc-field-webhook">
                                                <label style="font-weight:600;"><?php echo esc_html__('Webhook URL', 'multi-location-product-and-inventory-management-pro'); ?></label>
                                                <input <?php echo !mulopimfwc_premium_feature() ? 'disabled' : ''; ?> type="url" name="mulopimfwc_display_options[social_notifications][channels][<?php echo esc_attr($index); ?>][webhook]" value="<?php echo esc_attr($webhook); ?>" placeholder="https://hooks.slack.com/..." style="width:100%;">
                                            </div>
                                            <div class="mulopimfwc-field-telegram">
                                                <label style="font-weight:600;"><?php echo esc_html__('Telegram Chat ID', 'multi-location-product-and-inventory-management-pro'); ?></label>
                                                <input <?php echo !mulopimfwc_premium_feature() ? 'disabled' : ''; ?> type="text" name="mulopimfwc_display_options[social_notifications][channels][<?php echo esc_attr($index); ?>][chat_id]" value="<?php echo esc_attr($chat_id); ?>" placeholder="<?php echo esc_attr__('@username or 123456789', 'multi-location-product-and-inventory-management-pro'); ?>" style="width:100%;">
                                            </div>
                                            <div class="mulopimfwc-field-telegram">
                                                <label style="font-weight:600;"><?php echo esc_html__('Bot Token (Telegram override)', 'multi-location-product-and-inventory-management-pro'); ?></label>
                                                <input <?php echo !mulopimfwc_premium_feature() ? 'disabled' : ''; ?> type="text" name="mulopimfwc_display_options[social_notifications][channels][<?php echo esc_attr($index); ?>][bot_token]" value="<?php echo esc_attr($bot_token); ?>" placeholder="<?php echo esc_attr__('Optional: token for this channel', 'multi-location-product-and-inventory-management-pro'); ?>" style="width:100%;">
                                            </div>
                                            <div style="display:flex;gap:8px;justify-content:flex-end;align-items:center;">
                                                <button <?php echo !mulopimfwc_premium_feature() ? 'disabled' : ''; ?> type="button" class="button test-social-channel" data-nonce="<?php echo esc_attr($test_nonce); ?>"><?php echo esc_html__('Test', 'multi-location-product-and-inventory-management-pro'); ?></button>
                                                <button <?php echo !mulopimfwc_premium_feature() ? 'disabled' : ''; ?> type="button" class="button test-social-digest-channel" data-nonce="<?php echo esc_attr($test_digest_nonce); ?>"><?php echo esc_html__('Test Digest', 'multi-location-product-and-inventory-management-pro'); ?></button>
                                                <button <?php echo !mulopimfwc_premium_feature() ? 'disabled' : ''; ?> type="button" class="button button-link-delete remove-social-channel"><?php echo esc_html__('Remove', 'multi-location-product-and-inventory-management-pro'); ?></button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <script type="text/template" id="mulopimfwc-social-channel-template">
                                <div class="mulopimfwc-social-channel-row" style="border:1px solid #e2e8f0;border-radius:10px;padding:12px;margin-bottom:10px;background:#fff;">
                                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;align-items:center;">
                                    <div>
                                        <label style="font-weight:600;"><?php echo esc_html__('Platform', 'multi-location-product-and-inventory-management-pro'); ?></label>
                                        <select name="mulopimfwc_display_options[social_notifications][channels][__INDEX__][type]" class="mulopimfwc-channel-type" style="width:100%;">
                                            <option value=""><?php echo esc_html__('Select...', 'multi-location-product-and-inventory-management-pro'); ?></option>
                                            <option value="slack">Slack</option>
                                            <option value="teams">Microsoft Teams</option>
                                            <option value="discord">Discord</option>
                                            <option value="telegram">Telegram</option>
                                            <option value="custom">Custom Webhook</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="font-weight:600;"><?php echo esc_html__('Label (optional)', 'multi-location-product-and-inventory-management-pro'); ?></label>
                                        <input type="text" name="mulopimfwc_display_options[social_notifications][channels][__INDEX__][label]" value="" placeholder="<?php echo esc_attr__('e.g., Admin alerts', 'multi-location-product-and-inventory-management-pro'); ?>" style="width:100%;">
                                    </div>
                                    <div class="mulopimfwc-field-webhook">
                                        <label style="font-weight:600;"><?php echo esc_html__('Webhook URL', 'multi-location-product-and-inventory-management-pro'); ?></label>
                                        <input type="url" name="mulopimfwc_display_options[social_notifications][channels][__INDEX__][webhook]" value="" placeholder="https://hooks.slack.com/..." style="width:100%;">
                                    </div>
                                    <div class="mulopimfwc-field-telegram">
                                        <label style="font-weight:600;"><?php echo esc_html__('Telegram Chat ID', 'multi-location-product-and-inventory-management-pro'); ?></label>
                                        <input type="text" name="mulopimfwc_display_options[social_notifications][channels][__INDEX__][chat_id]" value="" placeholder="<?php echo esc_attr__('@username or 123456789', 'multi-location-product-and-inventory-management-pro'); ?>" style="width:100%;">
                                    </div>
                                    <div class="mulopimfwc-field-telegram">
                                        <label style="font-weight:600;"><?php echo esc_html__('Bot Token (Telegram override)', 'multi-location-product-and-inventory-management-pro'); ?></label>
                                        <input type="text" name="mulopimfwc_display_options[social_notifications][channels][__INDEX__][bot_token]" value="" placeholder="<?php echo esc_attr__('Optional: token for this channel', 'multi-location-product-and-inventory-management-pro'); ?>" style="width:100%;">
                                    </div>
                                    <div style="display:flex;gap:8px;justify-content:flex-end;align-items:center;">
                                        <button type="button" class="button test-social-channel" data-nonce="<?php echo esc_attr($test_nonce); ?>"><?php echo esc_html__('Test', 'multi-location-product-and-inventory-management-pro'); ?></button>
                                        <button type="button" class="button test-social-digest-channel" data-nonce="<?php echo esc_attr($test_digest_nonce); ?>"><?php echo esc_html__('Test Digest', 'multi-location-product-and-inventory-management-pro'); ?></button>
                                        <button type="button" class="button button-link-delete remove-social-channel"><?php echo esc_html__('Remove', 'multi-location-product-and-inventory-management-pro'); ?></button>
                                    </div>
                                </div>
                            </div>
                        </script>
                            <script>
                                jQuery(function($) {
                                    const container = $('#mulopimfwc-social-channels');
                                    const tmpl = $('#mulopimfwc-social-channel-template').html();
                                    const emptyRow = $('#mulopimfwc-social-empty');
                                    const ajaxAction = 'mulopimfwc_test_social_channel';
                                    const digestAjaxAction = 'mulopimfwc_test_social_digest_channel';
                                    const digestClock = $('#mulopimfwc-digest-clock');
                                    if (digestClock.length) {
                                        const startUtcMs = parseInt(digestClock.data('start-utc'), 10) * 1000;
                                        const offsetHours = parseFloat(digestClock.data('gmt-offset')) || 0;
                                        const offsetMs = offsetHours * 3600 * 1000;
                                        const startClient = Date.now();
                                        const tzLabel = digestClock.data('tz-label') || 'UTC';

                                        const pad = (n) => (n < 10 ? '0' + n : n);
                                        const tick = () => {
                                            const nowMs = startUtcMs + offsetMs + (Date.now() - startClient);
                                            const d = new Date(nowMs);
                                            const label = pad(d.getUTCHours()) + ':' + pad(d.getUTCMinutes()) + ':' + pad(d.getUTCSeconds()) + ' ' + tzLabel;
                                            digestClock.text(label);
                                        };
                                        tick();
                                        setInterval(tick, 1000);
                                    }

                                    function toggleChannelFields(row) {
                                        const type = row.find('.mulopimfwc-channel-type').val();
                                        const isTelegram = type === 'telegram';
                                        row.find('.mulopimfwc-field-webhook')[isTelegram ? 'hide' : 'show']();
                                        row.find('.mulopimfwc-field-telegram')[isTelegram ? 'show' : 'hide']();
                                    }

                                    function refreshEmptyState() {
                                        if (container.children('.mulopimfwc-social-channel-row').length === 0) {
                                            emptyRow.show();
                                        } else {
                                            emptyRow.hide();
                                        }
                                    }

                                    $('#mulopimfwc-add-social-channel').on('click', function(e) {
                                        e.preventDefault();
                                        const index = container.children('.mulopimfwc-social-channel-row').length;
                                        container.append(tmpl.replace(/__INDEX__/g, index));
                                        const row = container.children('.mulopimfwc-social-channel-row').last();
                                        toggleChannelFields(row);
                                        refreshEmptyState();
                                    });

                                    container.on('click', '.remove-social-channel', function(e) {
                                        e.preventDefault();
                                        $(this).closest('.mulopimfwc-social-channel-row').remove();
                                        refreshEmptyState();
                                    });

                                    container.on('change', '.mulopimfwc-channel-type', function() {
                                        const row = $(this).closest('.mulopimfwc-social-channel-row');
                                        toggleChannelFields(row);
                                    });

                                    container.on('click', '.test-social-channel', function(e) {
                                        e.preventDefault();
                                        const row = $(this).closest('.mulopimfwc-social-channel-row');
                                        const data = {
                                            action: ajaxAction,
                                            nonce: $(this).data('nonce'),
                                            type: row.find('.mulopimfwc-channel-type').val(),
                                            label: row.find('input[name*=\"[label]\"]').val(),
                                            webhook: row.find('input[name*=\"[webhook]\"]').val(),
                                            chat_id: row.find('input[name*=\"[chat_id]\"]').val(),
                                            bot_token: row.find('input[name*=\"[bot_token]\"]').val()
                                        };
                                        const btn = $(this);
                                        btn.prop('disabled', true).text('<?php echo esc_js(__('Testing...', 'multi-location-product-and-inventory-management-pro')); ?>');
                                        $.post(ajaxurl, data, function(res) {
                                            const ok = res && res.success;
                                            alert(ok ? '<?php echo esc_js(__('Test sent! Check your channel.', 'multi-location-product-and-inventory-management-pro')); ?>' : (res?.data?.message || '<?php echo esc_js(__('Test failed. Check the details and try again.', 'multi-location-product-and-inventory-management-pro')); ?>'));
                                        }).fail(function() {
                                            alert('<?php echo esc_js(__('Test failed. Check the details and try again.', 'multi-location-product-and-inventory-management-pro')); ?>');
                                        }).always(function() {
                                            btn.prop('disabled', false).text('<?php echo esc_js(__('Test', 'multi-location-product-and-inventory-management-pro')); ?>');
                                        });
                                    });

                                    container.on('click', '.test-social-digest-channel', function(e) {
                                        e.preventDefault();
                                        const row = $(this).closest('.mulopimfwc-social-channel-row');
                                        const data = {
                                            action: digestAjaxAction,
                                            nonce: $(this).data('nonce'),
                                            type: row.find('.mulopimfwc-channel-type').val(),
                                            label: row.find('input[name*=\"[label]\"]').val(),
                                            webhook: row.find('input[name*=\"[webhook]\"]').val(),
                                            chat_id: row.find('input[name*=\"[chat_id]\"]').val(),
                                            bot_token: row.find('input[name*=\"[bot_token]\"]').val()
                                        };
                                        const btn = $(this);
                                        btn.prop('disabled', true).text('<?php echo esc_js(__('Testing digest...', 'multi-location-product-and-inventory-management-pro')); ?>');
                                        $.post(ajaxurl, data, function(res) {
                                            const ok = res && res.success;
                                            alert(ok ? '<?php echo esc_js(__('Digest test sent! Check your channel.', 'multi-location-product-and-inventory-management-pro')); ?>' : (res?.data?.message || '<?php echo esc_js(__('Digest test failed. Check the details and try again.', 'multi-location-product-and-inventory-management-pro')); ?>'));
                                        }).fail(function() {
                                            alert('<?php echo esc_js(__('Digest test failed. Check the details and try again.', 'multi-location-product-and-inventory-management-pro')); ?>');
                                        }).always(function() {
                                            btn.prop('disabled', false).text('<?php echo esc_js(__('Test Digest', 'multi-location-product-and-inventory-management-pro')); ?>');
                                        });
                                    });

                                    // Initial toggle for existing rows
                                    container.find('.mulopimfwc-social-channel-row').each(function() {
                                        toggleChannelFields($(this));
                                    });

                                    refreshEmptyState();
                                });
                            </script>
                        </div>
                    </div>
                </div>

                <div class="lwp-settings-section" style="margin-top:12px;">
                    <div class="lwp-settings-box">
                        <div class="lwp-filter-settings">
                            <?php
                            $guides = [
                                'teams' => [
                                    'title' => __('How to connect Microsoft Teams', 'multi-location-product-and-inventory-management-pro'),
                                    'steps' => [
                                        __('In Teams, open the channel → More options (...) → Connectors/Apps → search “Incoming Webhook”.', 'multi-location-product-and-inventory-management-pro'),
                                        __('Click Add/Create, name it (e.g., “Store Alerts”), optionally upload an icon, then click Create.', 'multi-location-product-and-inventory-management-pro'),
                                        __('Copy the webhook URL, paste it into the Webhook URL field above, and save settings.', 'multi-location-product-and-inventory-management-pro'),
                                        __('Enable the events you want (orders, stock alerts, digest, site monitor), then click Test.', 'multi-location-product-and-inventory-management-pro'),
                                    ],
                                    'links' => [
                                        ['https://www.youtube.com/embed/VIDEO_ID_TEAMS', __('Watch: Teams setup', 'multi-location-product-and-inventory-management-pro')],
                                        ['https://learn.microsoft.com/microsoftteams/platform/webhooks-and-connectors/how-to/add-incoming-webhook', __('Microsoft Docs: Incoming Webhook', 'multi-location-product-and-inventory-management-pro')],
                                    ],
                                ],
                                'slack' => [
                                    'title' => __('How to connect Slack', 'multi-location-product-and-inventory-management-pro'),
                                    'steps' => [
                                        __('In Slack, go to Settings & administration → Manage apps → search “Incoming WebHooks” and click Add.', 'multi-location-product-and-inventory-management-pro'),
                                        __('Choose the channel for alerts and click Add Incoming Webhook Integration (or Add Configuration).', 'multi-location-product-and-inventory-management-pro'),
                                        __('Copy the webhook URL, paste it into the Webhook URL field above, and save.', 'multi-location-product-and-inventory-management-pro'),
                                        __('Enable desired events and click Test.', 'multi-location-product-and-inventory-management-pro'),
                                    ],
                                    'links' => [
                                        ['https://api.slack.com/messaging/webhooks', __('Slack Docs: Incoming Webhooks', 'multi-location-product-and-inventory-management-pro')],
                                    ],
                                ],
                                'discord' => [
                                    'title' => __('How to connect Discord', 'multi-location-product-and-inventory-management-pro'),
                                    'steps' => [
                                        __('In Discord, Server Settings → Integrations → Webhooks → New Webhook.', 'multi-location-product-and-inventory-management-pro'),
                                        __('Select the target channel and copy the webhook URL.', 'multi-location-product-and-inventory-management-pro'),
                                        __('Paste the URL into the Webhook field above and save settings.', 'multi-location-product-and-inventory-management-pro'),
                                        __('Enable events and click Test.', 'multi-location-product-and-inventory-management-pro'),
                                    ],
                                    'links' => [
                                        ['https://support.discord.com/hc/en-us/articles/228383668-Intro-to-Webhooks', __('Discord Docs: Webhooks', 'multi-location-product-and-inventory-management-pro')],
                                    ],
                                ],
                                'telegram' => [
                                    'title' => __('How to connect Telegram', 'multi-location-product-and-inventory-management-pro'),
                                    'steps' => [
                                        __('Create a bot via @BotFather and copy the bot token.', 'multi-location-product-and-inventory-management-pro'),
                                        __('Start a chat with @userinfobot (or add the bot to a group) to get chat ID.', 'multi-location-product-and-inventory-management-pro'),
                                        __('Enter chat ID and bot token above (or use the global token), then save.', 'multi-location-product-and-inventory-management-pro'),
                                        __('Enable events and click Test.', 'multi-location-product-and-inventory-management-pro'),
                                    ],
                                    'links' => [['https://core.telegram.org/bots/api', __('Telegram Docs: Bots API', 'multi-location-product-and-inventory-management-pro')],],
                                ],
                                'custom' => [
                                    'title' => __('How to connect a Custom Webhook', 'multi-location-product-and-inventory-management-pro'),
                                    'steps' => [
                                        __('Use any HTTPS endpoint that accepts a JSON POST payload with a “text” field.', 'multi-location-product-and-inventory-management-pro'),
                                        __('Paste the endpoint URL above and save.', 'multi-location-product-and-inventory-management-pro'),
                                        __('Enable events and click Test.', 'multi-location-product-and-inventory-management-pro'),
                                    ],
                                    'links' => [
                                        ['https://plugincy.com/documentations/multi-location-product-inventory-management-for-woocommerce/email-notification/social-channel-real-time-notifications/', __('Example: Custom Webhook setup guide', 'multi-location-product-and-inventory-management-pro')]
                                    ],
                                ],
                            ];

                            foreach ($guides as $platform => $guide) :
                            ?>
                                <details class="mulopimfwc-accordion mulopimfwc-guide" data-platform="<?php echo esc_attr($platform); ?>" style="margin-top:10px;">
                                    <summary style="font-weight:700;"><?php echo esc_html($guide['title']); ?></summary>
                                    <ol style="margin-left:18px; color:#334155;">
                                        <?php foreach ($guide['steps'] as $step) : ?>
                                            <li><?php echo esc_html($step); ?></li>
                                        <?php endforeach; ?>
                                    </ol>
                                    <?php if (!empty($guide['links'])) : ?>
                                        <p class="description">
                                            <?php
                                            $links_html = [];
                                            foreach ($guide['links'] as $link) {
                                                $links_html[] = '<a href="' . esc_url($link[0]) . '" target="_blank" rel="noopener">' . esc_html($link[1]) . '</a>';
                                            }
                                            echo wp_kses_post(implode(' &nbsp;|&nbsp; ', $links_html));
                                            ?>
                                        </p>
                                    <?php endif; ?>
                                </details>
                            <?php endforeach; ?>
                            <script>
                                jQuery(function($) {
                                    function openGuideFor(type) {
                                        $('.mulopimfwc-guide').each(function() {
                                            const isMatch = $(this).data('platform') === type;
                                            $(this).attr('open', isMatch ? 'open' : null);
                                        });
                                    }
                                    $('#mulopimfwc-social-channels').on('change', '.mulopimfwc-channel-type', function() {
                                        const type = $(this).val();
                                        if (type) {
                                            openGuideFor(type);
                                        }
                                    });
                                    // Open guide for first existing selection
                                    const firstType = $('#mulopimfwc-social-channels .mulopimfwc-channel-type').first().val();
                                    if (firstType) {
                                        openGuideFor(firstType);
                                    }
                                });
                            </script>
                        </div>
                    </div>
                </div>
            <?php
            },
            'lwp-social-notifications-settings',
            'mulopimfwc_social_notifications_section'
        );

        // Add "Export Products with Location Data" field
        add_settings_field(
            'export_products_csv',
            __('Export Products with Location Data', 'multi-location-product-and-inventory-management-pro'),
            function () {
            ?>
                <button type="button" id="mulopimfwc_export_products" class="button button-secondary mulopimfwc_export_products" data-format="csv">
                    <span class="dashicons dashicons-media-spreadsheet" style="margin-top: 3px;"></span>
                    <?php echo esc_html_e('Export Full CSV', 'multi-location-product-and-inventory-management-pro'); ?>
                </button>
                <button type="button" id="mulopimfwc_import_products_btn" class="button button-secondary mulopimfwc_import_products_btn" style="margin-left:8px;">
                    <span class="dashicons dashicons-upload" style="margin-top: 3px;"></span>
                    <?php echo esc_html_e('Import Full CSV', 'multi-location-product-and-inventory-management-pro'); ?>
                </button>
                <input type="file" id="mulopimfwc_import_products_file" accept=".csv,.zip" style="display:none;" />
                <p class="description"><?php echo esc_html_e('Export/import versioned canonical CSV for full WooCommerce product data plus location-wise data. Import runs dry-run first, then asks for confirmation.', 'multi-location-product-and-inventory-management-pro'); ?></p>
                <p class="description">
                    <label for="mulopimfwc_import_mode"><?php echo esc_html_e('Import mode:', 'multi-location-product-and-inventory-management-pro'); ?></label>
                    <select id="mulopimfwc_import_mode">
                        <option value="create_update"><?php echo esc_html_e('Create + Update', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="update_only"><?php echo esc_html_e('Update Only', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    </select>
                    <label style="margin-left:12px;"><input type="checkbox" id="mulopimfwc_import_auto_create_terms" checked="checked" /> <?php echo esc_html_e('Auto-create terms/locations', 'multi-location-product-and-inventory-management-pro'); ?></label>
                    <label style="margin-left:12px;"><input type="checkbox" id="mulopimfwc_import_sync_location_profile" checked="checked" /> <?php echo esc_html_e('Sync location profile', 'multi-location-product-and-inventory-management-pro'); ?></label>
                    <label style="margin-left:12px;"><input type="checkbox" id="mulopimfwc_import_media" /> <?php echo esc_html_e('Sideload media', 'multi-location-product-and-inventory-management-pro'); ?></label>
                </p>
                <p class="description">
                    <label for="mulopimfwc_import_meta_whitelist"><?php echo esc_html_e('Custom meta whitelist:', 'multi-location-product-and-inventory-management-pro'); ?></label>
                    <input type="text" id="mulopimfwc_import_meta_whitelist" style="width:360px;" placeholder="_yoast_wpseo_title,_yoast_wpseo_metadesc" />
                </p>
                <div id="export-progress" style="margin-top: 10px; display: none;">
                    <progress id="export-progress-bar" max="100" value="0" style="width: 100%;"></progress>
                    <p id="export-status-text"></p>
                </div>
                <?php
            },
            'lwp-import-export-settings',
            'mulopimfwc_import_export_section'
        );

        // Add section for Customer Location Settings
        add_settings_section(
            'mulopimfwc_customer_location_section',
            __('Customer Location Settings', 'multi-location-product-and-inventory-management-pro'),
            function () {
                echo '<p>' . esc_html__('Configure how customer locations are determined and remembered.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            },
            'location-wise-products_settings'
        );

        // Add "Default Store Location" field
        add_settings_field(
            'default_store_location',
            __('Default Store Location', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['default_store_location' => '']);
                $value = isset($options['default_store_location']) ? $options['default_store_location'] : '';

                // Get all locations
                $locations = get_terms(array(
                    'taxonomy' => 'location', // Assuming your taxonomy is called 'location'
                    'hide_empty' => false,
                ));

                if (!is_wp_error($locations) && !empty($locations)) {
                ?>
                    <select name="mulopimfwc_display_options[default_store_location]">
                        <option value="" <?php selected($value, ''); ?>><?php echo esc_html_e('No default (ask customer to select)', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <?php foreach ($locations as $location) : ?>
                            <option value="<?php echo esc_attr($location->term_id); ?>" <?php selected($value, $location->term_id); ?>><?php echo esc_html($location->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php
                } else {
                    echo '<p>' . esc_html__('No locations found. Please create locations first.', 'multi-location-product-and-inventory-management-pro') . '</p>';
                }
                ?>
                <p class="description"><?php echo esc_html_e('Select the default location to show when a customer first visits your store.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            <?php
            },
            'location-wise-products_settings',
            'mulopimfwc_customer_location_section'
        );

        // Add "Remember Customer Location" field
        add_settings_field(
            'remember_customer_location',
            __('Remember Customer Location', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['remember_customer_location' => 'on']);
                $value = isset($options['remember_customer_location']) ? $options['remember_customer_location'] : 'on';
            ?>
                <select name="mulopimfwc_display_options[remember_customer_location]">
                    <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                </select>
                <p class="description"><?php echo esc_html_e('Remember a customer\'s location between visits.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            <?php
            },
            'location-wise-products_settings',
            'mulopimfwc_customer_location_section'
        );

        // Add "Link Location to User Account" field
        add_settings_field(
            'link_location_to_user',
            __('Link Location to User Account', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['link_location_to_user' => 'on']);
                $value = isset($options['link_location_to_user']) ? $options['link_location_to_user'] : 'on';
            ?>
                <select name="mulopimfwc_display_options[link_location_to_user]">
                    <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                </select>
                <p class="description"><?php echo esc_html_e('Store selected location as part of the user\'s account preferences.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            <?php
            },
            'location-wise-products_settings',
            'mulopimfwc_customer_location_section'
        );

        // Add section for Product Allocation Settings
        add_settings_section(
            'mulopimfwc_product_allocation_section',
            sprintf(
                '<svg xmlns="http://www.w3.org/2000/svg" 
     viewBox="0 0 512 512" 
     xml:space="preserve" 
     width="20" height="20" 
     style="margin-right:6px;vertical-align:middle;background-color:#cffafe;padding:10px;border-radius:6px">
  <path fill="#0891b2" d="m324.386 198.993-68.385-68.385-68.344 68.344c-18.293 18.272-28.367 42.571-28.368 68.426.001 53.326 43.386 96.711 96.711 96.711 53.326 0 96.711-43.385 96.711-96.711 0-25.829-10.059-50.115-28.325-68.385M256 329.956c-34.504 0-62.577-28.072-62.578-62.576.001-16.73 6.519-32.455 18.364-44.283l44.216-44.216 44.248 44.248c11.818 11.823 18.327 27.536 18.327 44.249.001 34.505-28.072 62.578-62.577 62.578M102.4 34.133V0H0v102.4h34.133v17.067h34.133V102.4H102.4V68.267h17.067V34.133zM68.267 68.267H34.133V34.133h34.133zM409.6 0v34.133h-17.067v34.133H409.6V102.4h34.133v17.067h34.133V102.4H512V0zm68.267 68.267h-34.133V34.133h34.133zM307.2 34.133V0H204.8v34.133h-17.067v34.133H204.8V102.4h102.4V68.267h17.067V34.133zm-34.133 34.134h-34.133V34.133h34.133zM307.2 443.733V409.6H204.8v34.133h-17.067v34.133H204.8V512h102.4v-34.133h17.067v-34.133H307.2zm-34.133 34.134h-34.133v-34.133h34.133zM102.4 443.733V409.6H68.267v-17.067H34.133V409.6H0V512h102.4v-34.133h17.067v-34.133H102.4zm-34.133 34.134H34.133v-34.133h34.133zm68.266-443.734h34.133v34.133h-34.133zm204.8 0h34.133v34.133h-34.133zm-204.8 409.6h34.133v34.133h-34.133zm204.8 0h34.133v34.133h-34.133zM68.267 204.8v-17.067H34.133V204.8H0v102.4h34.133v17.067h34.133V307.2H102.4V204.8zm0 68.267H34.133v-34.133h34.133zm-34.134 68.266h34.133v34.133H34.133zm0-204.8h34.133v34.133H34.133zm409.6 204.8h34.133v34.133h-34.133zm34.134 68.267v-17.067h-34.133V409.6H409.6v34.133h-17.067v34.133H409.6V512H512V409.6zm0 68.267h-34.133v-34.133h34.133zm-34.134-341.334h34.133v34.133h-34.133zm34.134 68.267v-17.067h-34.133V204.8H409.6v102.4h34.133v17.067h34.133V307.2H512V204.8zm0 68.267h-34.133v-34.133h34.133z"/>
</svg> %s',
__('Product Allocation Settings', 'multi-location-product-and-inventory-management-pro'),
    ),        function () {
                echo '<p>' . esc_html__('Configure how products are allocated to different locations.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            },
            'lwp-location-allocation-settings'
        );

        // Add "Bulk Location Assignment" field
        add_settings_field(
            'enable_bulk_location_assignment',
            __('Enable Bulk Location Assignment', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['enable_bulk_location_assignment' => 'on']);
                $value = isset($options['enable_bulk_location_assignment']) ? $options['enable_bulk_location_assignment'] : 'on';
            ?>
                <select name="mulopimfwc_display_options[enable_bulk_location_assignment]">
                    <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option disabled value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                </select>
                <p class="description"><?php echo esc_html_e('Enable bulk assignment of products to locations.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            <?php
            },
            'lwp-location-allocation-settings',
            'mulopimfwc_product_allocation_section'
        );

        // Add "Category-Based Location Assignment" field
        add_settings_field(
            'enable_category_based_location',
            __('Category-Based Location Assignment', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['enable_category_based_location' => 'off']);
                $value = isset($options['enable_category_based_location']) ? $options['enable_category_based_location'] : 'off';
            ?>
                <select disabled name="mulopimfwc_display_options[enable_category_based_location]">
                    <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                </select>
                <p class="description"><?php echo esc_html_e('Automatically assign products to locations based on their categories.', 'multi-location-product-and-inventory-management-pro'); ?></p>
                <?php
            },
            'lwp-location-allocation-settings',
            'mulopimfwc_product_allocation_section'
        );

        // Add Product Visibility section
        add_settings_section(
            'mulopimfwc_product_visibility_section',
sprintf(
        '<svg xmlns="http://www.w3.org/2000/svg" 
     viewBox="0 0 32 32" 
     xml:space="preserve"
     width="20" height="20" 
     style="margin-right:6px;vertical-align:middle;background-color:#dbeafe;padding:10px;border-radius:6px">
  <path fill="#2563eb" d="M14.5 11h13L23 4.7V1c0-.6-.4-1-1-1H10c-.6 0-1 .4-1 1v2.7zM13 13H4v14h9zm2 14h13V13H15zm-3-16L8.2 5.9 4.5 11zm3 18v3h12c.6 0 1-.4 1-1v-2zm-2 0H4v2c0 .6.4 1 1 1h8z"/>
</svg> %s',
        __('Product Visibility Rules', 'multi-location-product-and-inventory-management-pro')
    ),
            function () {
                echo '<p>' . esc_html__('Configure advanced rules for product visibility based on locations.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            },
            'location-product-visibility-settings'
        );

        // Add "Show Global Products" field
        add_settings_field(
            'show_global_products',
            __('Show Global Products', 'multi-location-product-and-inventory-management-pro'),
            function () {
                $options = $this->get_display_options();
                $value = isset($options['enable_all_locations']) ? $options['enable_all_locations'] : 'off';
                $is_manual_mode = $this->is_manual_assignment_strict_mode();
                if ($is_manual_mode) {
                    $this->render_manual_hidden_input('enable_all_locations', $value);
                }
                $message = $this->append_manual_disabled_note(__("Show products that are not assigned to any specific location.", 'multi-location-product-and-inventory-management-pro'));
                $this->render_advance_checkbox("enable_all_locations", $message, $is_manual_mode);
            },
            'location-product-visibility-settings',
            'mulopimfwc_product_visibility_section'
        );

        // Add "Product Priority Display" field
        add_settings_field(
            'product_priority_display',
            __('Product Priority Display', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['product_priority_display' => 'location_first']);
                $value = isset($options['product_priority_display']) ? $options['product_priority_display'] : 'location_first';
                $is_manual_mode = $this->is_manual_assignment_strict_mode();

                if ($is_manual_mode) {
                    $this->render_manual_hidden_input('product_priority_display', $value);
                }

                if (mulopimfwc_premium_feature()) {
                ?>
                    <select <?php echo $is_manual_mode ? ' disabled' : ''; ?> name="mulopimfwc_display_options[product_priority_display]">
                        <option value="location_first" <?php selected($value, 'location_first'); ?>><?php echo esc_html_e('Location Products First', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="global_first" <?php selected($value, 'global_first'); ?>><?php echo esc_html_e('Global Products First', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="mixed" <?php selected($value, 'mixed'); ?>><?php echo esc_html_e('No Priority (Mixed)', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    </select>
                <?php } else { ?>
                    <label class="mulopimfwc_pro_only">
                        <select disabled name="_pro[pro]">
                            <option value="location_first"><?php echo esc_html_e('Location Products First', 'multi-location-product-and-inventory-management-pro'); ?></option>
                            <option value="global_first"><?php echo esc_html_e('Global Products First', 'multi-location-product-and-inventory-management-pro'); ?></option>
                            <option value="mixed" selected><?php echo esc_html_e('No Priority (Mixed)', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        </select>
                    </label>
                <?php } ?>
                <p class="description"><?php echo esc_html($this->append_manual_disabled_note(__('Set display priority for location-specific vs. global products.', 'multi-location-product-and-inventory-management-pro'))); ?></p>
                <?php
            },
            'location-product-visibility-settings',
            'mulopimfwc_product_visibility_section'
        );

        // Add "Single Product Unavailable Behavior" field
        add_settings_field(
            'single_product_unavailable_behavior',
            __('Single Product Unavailable Behavior', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['single_product_unavailable_behavior' => 'show_404']);
                $value = isset($options['single_product_unavailable_behavior']) ? $options['single_product_unavailable_behavior'] : 'show_404';
                $is_manual_mode = $this->is_manual_assignment_strict_mode();

                if ($is_manual_mode) {
                    $this->render_manual_hidden_input('single_product_unavailable_behavior', $value);
                }

                if (mulopimfwc_premium_feature()) {
                ?>
                    <select <?php echo $is_manual_mode ? ' disabled' : ''; ?> name="mulopimfwc_display_options[single_product_unavailable_behavior]">
                        <option value="show_404" <?php selected($value, 'show_404'); ?>><?php echo esc_html__('Show 404 Page', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="show_message" <?php selected($value, 'show_message'); ?>><?php echo esc_html__('Show Product with Unavailable Message', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    </select>
                <?php } else { ?>
                    <label class="mulopimfwc_pro_only">
                        <select disabled name="_pro[pro]">
                            <option value="show_404"><?php echo esc_html__('Show 404 Page', 'multi-location-product-and-inventory-management-pro'); ?></option>
                            <option value="show_message"><?php echo esc_html__('Show Product with Unavailable Message', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        </select>
                    </label>
                <?php } ?>
                <p class="description"><?php echo esc_html($this->append_manual_disabled_note(__('Choose what happens when a customer visits a single product page URL but the product is not available in their selected location.', 'multi-location-product-and-inventory-management-pro'))); ?></p>
            <?php
            },
            'location-product-visibility-settings',
            'mulopimfwc_product_visibility_section'
        );
        // Add Location-Based Product Display section
        add_settings_section(
            'mulopimfwc_location_product_display_section',
            sprintf(
                '<svg xmlns="http://www.w3.org/2000/svg" 
     viewBox="0 0 512 512" 
     width="16" height="16" 
     style="margin-right:6px;vertical-align:middle;background-color:#dcfce7;padding:10px;border-radius:6px">
  <path fill="#16a34a" d="M486.4 0c-14.114 0-25.6 11.486-25.6 25.6v93.867h-25.6V53.504c0-10.684-8.695-19.371-19.371-19.371H352.17c-10.675 0-19.371 8.687-19.371 19.371v65.963h-25.6V53.504c0-10.684-8.695-19.371-19.371-19.371h-63.659c-10.675 0-19.371 8.687-19.371 19.371v65.963h-25.6V53.504c0-10.684-8.695-19.371-19.371-19.371H96.171c-10.675 0-19.371 8.687-19.371 19.371v65.963H51.2V25.6C51.2 11.486 39.714 0 25.6 0S0 11.486 0 25.6V512h51.2v-51.2h409.6V512H512V25.6C512 11.486 500.514 0 486.4 0m-25.6 443.733h-25.6V377.77c0-10.684-8.695-19.371-19.371-19.371H352.17c-10.675 0-19.371 8.687-19.371 19.371v65.963h-25.6V377.77c0-10.684-8.695-19.371-19.371-19.371h-63.659c-10.675 0-19.371 8.687-19.371 19.371v65.963h-25.6V377.77c0-10.684-8.695-19.371-19.371-19.371H96.171c-10.675 0-19.371 8.687-19.371 19.371v65.963H51.2V298.667h409.6zm0-162.133h-25.6v-65.963c0-10.684-8.695-19.371-19.371-19.371H352.17c-10.675 0-19.371 8.687-19.371 19.371V281.6h-25.6v-65.963c0-10.684-8.695-19.371-19.371-19.371h-63.659c-10.675 0-19.371 8.687-19.371 19.371V281.6h-25.6v-65.963c0-10.684-8.695-19.371-19.371-19.371H96.171c-10.675 0-19.371 8.687-19.371 19.371V281.6H51.2V136.533h409.6z"/>
</svg> %s',
__('Out of Stock Product Display', 'multi-location-product-and-inventory-management-pro'),
            ),
            function () {
                echo '<p>' . esc_html__('Configure how products are displayed based on location availability and stock.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            },
            'lwp-outstock-product-settings'
        );

        // Add "Show Out of Stock Products" field
        add_settings_field(
            'show_out_of_stock_products',
            __('Show Out of Stock Products', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['show_out_of_stock_products' => 'none']);
                $value = isset($options['show_out_of_stock_products']) ? $options['show_out_of_stock_products'] : 'none';
            ?>
                <select name="mulopimfwc_display_options[show_out_of_stock_products]">
                    <option value="none" <?php selected($value, 'none'); ?>><?php echo esc_html_e('Default', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="hide" <?php selected($value, 'hide'); ?>><?php echo esc_html_e('Hide Completely', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="show_with_badge" <?php selected($value, 'show_with_badge'); ?>><?php echo esc_html_e('Show with "Out of Stock" Badge', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="show_grayed_out" <?php selected($value, 'show_grayed_out'); ?>><?php echo esc_html_e('Show Grayed Out', 'multi-location-product-and-inventory-management-pro'); ?></option>
                </select>
                <p class="description"><?php echo esc_html_e('How to display products that are out of stock at the selected location.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            <?php
            },
            'lwp-outstock-product-settings',
            'mulopimfwc_location_product_display_section'
        );

        // Add "Stock Display Format" field
        add_settings_field(
            'stock_display_format',
            __('Stock Display Format', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['stock_display_format' => 'exact_count']);
                $value = isset($options['stock_display_format']) ? $options['stock_display_format'] : 'exact_count';
            ?>
                <select name="mulopimfwc_display_options[stock_display_format]">
                    <option value="exact_count" <?php selected($value, 'exact_count'); ?>><?php echo esc_html_e('Show Exact Stock Count', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="availability_only" <?php selected($value, 'availability_only'); ?>><?php echo esc_html_e('Show Only Availability (In Stock/Out of Stock)', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="stock_levels" <?php selected($value, 'stock_levels'); ?>><?php echo esc_html_e('Show Stock Levels (High/Medium/Low)', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="hide_stock" <?php selected($value, 'hide_stock'); ?>><?php echo esc_html_e('Hide Stock Information', 'multi-location-product-and-inventory-management-pro'); ?></option>
                </select>
                <p class="description"><?php echo esc_html_e('How to display stock information for location-specific products.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            <?php
            },
            'lwp-outstock-product-settings',
            'mulopimfwc_location_product_display_section'
        );

        // Add Admin Visibility Controls section
        add_settings_section(
            'mulopimfwc_admin_visibility_section',
            sprintf(
'<svg xmlns="http://www.w3.org/2000/svg" 
     viewBox="0 0 52 52" 
     xml:space="preserve"
     width="20" height="20" 
     style="margin-right:6px;vertical-align:middle;background-color:#f3e8ff;padding:10px;border-radius:6px">
  <path fill="#9333ea" d="M50 43v2.2c0 2.6-2.2 4.8-4.8 4.8H6.8C4.2 50 2 47.8 2 45.2V43c0-5.8 6.8-9.4 13.2-12.2l.6-.3c.5-.2 1-.2 1.5.1 2.6 1.7 5.5 2.6 8.6 2.6s6.1-1 8.6-2.6c.5-.3 1-.3 1.5-.1l.6.3C43.2 33.6 50 37.1 50 43M26 2c6.6 0 11.9 5.9 11.9 13.2S32.6 28.4 26 28.4s-11.9-5.9-11.9-13.2S19.4 2 26 2"/>
</svg> %s',
__('Admin Visibility Controls', 'multi-location-product-and-inventory-management-pro'),
),
            function () {
                echo '<p>' . esc_html__('Configure admin-specific visibility and management options.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            },
            'lwp-admin-product-visibility-settings'
        );

        // Add "Show All Products in Admin" field
        add_settings_field(
            'show_all_products_admin',
            __('Show All Products in Admin', 'multi-location-product-and-inventory-management-pro'),
            function () {
                $is_manual_mode = $this->is_manual_assignment_strict_mode();
                $options = $this->get_display_options();
                if ($is_manual_mode) {
                    $this->render_manual_hidden_input('show_all_products_admin', $options['show_all_products_admin'] ?? 'off');
                }
                $message = $this->append_manual_disabled_note(__("Whether admins can see all products regardless of location restrictions.", 'multi-location-product-and-inventory-management-pro'));
                $this->render_advance_checkbox("show_all_products_admin", $message, $is_manual_mode);
            },
            'lwp-admin-product-visibility-settings',
            'mulopimfwc_admin_visibility_section'
        );

        add_settings_section(
            'mulopimfwc_filter_settings_section',
            sprintf(
                '<svg viewBox="0 0 48 48" 
     data-name="Layer 1" 
     xmlns="http://www.w3.org/2000/svg"
     width="20" height="20" 
     style="margin-right:6px;vertical-align:middle;background-color:#cffafe;padding:10px;border-radius:6px">
  <path fill="#0891b2" d="M47 12a2 2 0 0 0-2-2H24a2 2 0 0 0 0 4h21a2 2 0 0 0 2-2M3 14h5.35a6 6 0 1 0 0-4H3a2 2 0 0 0 0 4m11-4a2 2 0 1 1-2 2 2 2 0 0 1 2-2m31 12h-7.35a6 6 0 1 0 0 4H45a2 2 0 0 0 0-4m-13 4a2 2 0 1 1 2-2 2 2 0 0 1-2 2m-10-4H3a2 2 0 0 0 0 4h19a2 2 0 0 0 0-4m23 12H28a2 2 0 0 0 0 4h17a2 2 0 0 0 0-4m-27-4a6 6 0 0 0-5.65 4H3a2 2 0 0 0 0 4h9.35A6 6 0 1 0 18 30m0 8a2 2 0 1 1 2-2 2 2 0 0 1-2 2"/>
</svg> %s',
__('Location Filtering Settings', 'multi-location-product-and-inventory-management-pro'),
),
            [$this, 'filter_settings_section_callback'],
            'lwp-product-filtering-settings'
        );

        add_settings_field(
            'mulopimfwc_strict_filtering',
            __('Strict Location Filtering', 'multi-location-product-and-inventory-management-pro'),
            [$this, 'strict_filtering_field_callback'],
            'lwp-product-filtering-settings',
            'mulopimfwc_filter_settings_section'
        );

        // add_settings_field(
        //     'mulopimfwc_filtered_sections',
        //     __('Apply Location Filtering To', 'multi-location-product-and-inventory-management-pro'),
        //     [$this, 'filtered_sections_field_callback'],
        //     'lwp-product-filtering-settings',
        //     'mulopimfwc_filter_settings_section'
        // );

        // Add Order Fulfillment section
        add_settings_section(
            'mulopimfwc_order_fulfillment_section',
            sprintf(
                '<svg viewBox="0 0 24 24" 
     fill="none" 
     xmlns="http://www.w3.org/2000/svg"
     width="20" height="20" 
     style="margin-right:6px;vertical-align:middle;background-color:#dbeafe;padding:10px;border-radius:6px">
  <path fill="#2563eb" fill-rule="evenodd" clip-rule="evenodd" d="M0 4.6A2.6 2.6 0 0 1 2.6 2h18.8A2.6 2.6 0 0 1 24 4.6v.8A2.6 2.6 0 0 1 21.4 8H21v10.6c0 1.33-1.07 2.4-2.4 2.4H5.4C4.07 21 3 19.93 3 18.6V8h-.4A2.6 2.6 0 0 1 0 5.4zM2.6 4a.6.6 0 0 0-.6.6v.8a.6.6 0 0 0 .6.6h18.8a.6.6 0 0 0 .6-.6v-.8a.6.6 0 0 0-.6-.6zM8 10a1 1 0 1 0 0 2h8a1 1 0 1 0 0-2z" fill="#000"/>
</svg> %s',
__('Order Fulfillment', 'multi-location-product-and-inventory-management-pro'),
    ),        function () {
                echo '<p>' . esc_html__('Configure how orders are processed and fulfilled from different locations.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            },
            'lwp-order-fullfill-settings'
        );

        // Add "Order Assignment Method" field
        add_settings_field(
            'order_assignment_method',
            __('Order Assignment Method', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['order_assignment_method' => 'customer_selection']);
                $value = isset($options['order_assignment_method']) ? sanitize_key((string) $options['order_assignment_method']) : 'customer_selection';
                $location_wise_currency_enabled = function_exists('mulopimfwc_is_location_wise_currency_enabled')
                    ? mulopimfwc_is_location_wise_currency_enabled($options)
                    : (
                        isset($options['enable_location_price'], $options['location_wise_currency']) &&
                        $options['enable_location_price'] === 'on' &&
                        $options['location_wise_currency'] === 'on'
                    );
                if (function_exists('mulopimfwc_get_effective_order_assignment_method')) {
                    $value = mulopimfwc_get_effective_order_assignment_method($options);
                }
            ?>
                <select name="mulopimfwc_display_options[order_assignment_method]">
                    <option value="customer_selection" <?php selected($value, 'customer_selection'); ?>><?php echo esc_html_e('Customer Selection (Based on selected location)', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="inventory_based" <?php selected($value, 'inventory_based'); ?>><?php echo esc_html_e('Inventory Based (Location with highest stock)', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="proximity_based" <?php selected($value, 'proximity_based'); ?>><?php echo esc_html_e('Proximity Based (Nearest location to shipping address)', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="manual" <?php selected($value, 'manual'); ?> <?php disabled($location_wise_currency_enabled, true); ?>><?php echo esc_html_e('Manual Assignment (Admin assigns after order)', 'multi-location-product-and-inventory-management-pro'); ?></option>
                </select>
                <p class="description">
                    <?php
                    echo esc_html__('Choose how orders are assigned to fulfillment locations. In Manual Assignment mode, orders placed without a selected location are set to On Hold until a location is assigned.', 'multi-location-product-and-inventory-management-pro');
                    if ($location_wise_currency_enabled) {
                        echo ' ' . esc_html__('Manual Assignment is disabled while Location Wise Currency is enabled. If Manual was previously selected, Proximity Based will be used.', 'multi-location-product-and-inventory-management-pro');
                    }
                    ?>
                </p>
                <?php
            },
            'lwp-order-fullfill-settings',
            'mulopimfwc_order_fulfillment_section'
        );

        add_settings_field(
            'manual_optional_location_selection',
            __('Optional Location Selection (Manual/Inventory/Proximity)', 'multi-location-product-and-inventory-management-pro'),
            function () {
                $message = __("Allow customers to optionally select a location when Manual, Inventory-Based, or Proximity-Based assignment is enabled. Location selection remains optional.", 'multi-location-product-and-inventory-management-pro');
                $this->render_advance_checkbox('manual_optional_location_selection', $message);
            },
            'lwp-order-fullfill-settings',
            'mulopimfwc_order_fulfillment_section'
        );

        // Add "Order Notification Recipients" field
        add_settings_field(
            'order_notification_recipients',
            __('Order Notification Recipients', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['order_notification_recipients' => 'admin']);
                $value = isset($options['order_notification_recipients']) ? $options['order_notification_recipients'] : 'admin';

                if (mulopimfwc_premium_feature()) {
                ?>
                    <select name="mulopimfwc_display_options[order_notification_recipients]">
                        <option value="admin" <?php selected($value, 'admin'); ?>><?php echo esc_html_e('Admin Only', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="location_manager" <?php selected($value, 'location_manager'); ?>><?php echo esc_html_e('Location Manager', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="both" <?php selected($value, 'both'); ?>><?php echo esc_html_e('Both Admin and Location Manager', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    </select>
                <?php } else { ?>
                    <label class="mulopimfwc_pro_only">
                        <select disabled name="_pro[pro]">
                            <option value="admin" <?php selected($value, 'admin'); ?>><?php echo esc_html_e('Admin Only', 'multi-location-product-and-inventory-management-pro'); ?></option>
                            <option value="location_manager" <?php selected($value, 'location_manager'); ?>><?php echo esc_html_e('Location Manager', 'multi-location-product-and-inventory-management-pro'); ?></option>
                            <option value="both" <?php selected($value, 'both'); ?>><?php echo esc_html_e('Both Admin and Location Manager', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        </select>
                    </label>

                <?php } ?>
                <p class="description"><?php echo esc_html_e('Who should receive order notifications.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            <?php
            },
            'lwp-order-fullfill-settings',
            'mulopimfwc_order_fulfillment_section'
        );
        // Cross-Location Order Management
        add_settings_section(
            'mulopimfwc_cross_location_order_section',
            sprintf(
                '<svg xmlns="http://www.w3.org/2000/svg" 
     viewBox="0 0 297 297"
     width="20" height="20" 
     style="margin-right:6px;vertical-align:middle;background-color:#dcfce7;padding:10px;border-radius:6px">
  <path fill="#16a34a" d="m112.632 185.074 6.88-3.972a5.864 5.864 0 0 0 2.146-8.01l-13.036-22.579a5.86 5.86 0 0 0-8.009-2.146l-6.88 3.972a5.8 5.8 0 0 1-2.923.794c-3.063 0-5.872-2.449-5.872-5.872v-7.944a5.864 5.864 0 0 0-5.864-5.864H53.001a5.864 5.864 0 0 0-5.864 5.864v7.944c0 3.423-2.81 5.872-5.872 5.872a5.8 5.8 0 0 1-2.923-.794l-6.88-3.972a5.86 5.86 0 0 0-8.009 2.146l-13.036 22.579a5.864 5.864 0 0 0 2.146 8.01l6.88 3.972c3.909 2.257 3.909 7.899 0 10.156l-6.88 3.972a5.863 5.863 0 0 0-2.146 8.01l13.036 22.579a5.86 5.86 0 0 0 8.009 2.146l6.88-3.972a5.8 5.8 0 0 1 2.923-.794c3.063 0 5.872 2.449 5.872 5.872v7.944a5.864 5.864 0 0 0 5.864 5.864h26.072a5.864 5.864 0 0 0 5.864-5.864v-7.944c0-3.423 2.81-5.872 5.872-5.872.976 0 1.978.249 2.923.794l6.88 3.972a5.86 5.86 0 0 0 8.009-2.146l13.036-22.579a5.864 5.864 0 0 0-2.146-8.01l-6.88-3.972c-3.908-2.257-3.908-7.9.001-10.156m-46.594 22.474c-9.608 0-17.396-7.789-17.396-17.396s7.789-17.396 17.396-17.396 17.396 7.789 17.396 17.396-7.789 17.396-17.396 17.396m42.071-183.889A8.053 8.053 0 1 0 96.72 35.048l14.39 14.389c-52.889 2.619-95.701 44.162-100.334 96.506l1.19-2.062a19.18 19.18 0 0 1 16.57-9.564c.144 0 .287.013.431.017 9.074-37.721 41.965-66.251 81.815-68.729L96.72 79.666a8.053 8.053 0 0 0 11.388 11.389l28.004-28.004a8.055 8.055 0 0 0 0-11.388zm101.759 41.198c17.881 0 32.428-14.547 32.428-32.428C242.296 14.547 227.749 0 209.868 0S177.44 14.547 177.44 32.428s14.547 32.429 32.428 32.429m63.171 87.419v-44.58c0-12.34-7.93-23.283-19.657-27.124l-.054-.018-17.152-2.84a2.875 2.875 0 0 0-3.545 1.764l-19.462 53.399c-1.123 3.081-5.48 3.081-6.602 0l-19.462-53.399a2.875 2.875 0 0 0-2.698-1.892c-.279 0-17.999 2.964-17.999 2.964-11.823 3.94-19.723 14.9-19.723 27.294v44.432c0 6.659 5.398 12.056 12.056 12.056h102.241c6.66 0 12.057-5.398 12.057-12.056m14.331 10.657c-.673 9.215-8.233 14.858-17.45 15.526-7.062 40.425-41.207 71.64-82.979 74.237l14.061-14.061a8.053 8.053 0 1 0-11.389-11.389L161.61 255.25a8.053 8.053 0 0 0 0 11.389l28.003 28.003c1.573 1.572 3.633 2.358 5.694 2.358s4.122-.786 5.694-2.358a8.053 8.053 0 0 0 0-11.389l-14.389-14.389c56.028-2.774 100.758-49.227 100.758-105.931"/>
  <path fill="#16a34a" d="M216.936 77.105c-.747-.814-1.84-1.224-2.946-1.224h-8.245c-1.105 0-2.198.41-2.946 1.224a3.825 3.825 0 0 0-.504 4.505l4.407 6.644-2.063 17.405 4.063 10.808c.396 1.087 1.933 1.087 2.33 0l4.063-10.808-2.063-17.405 4.407-6.644a3.82 3.82 0 0 0-.503-4.505"/>
</svg> %s',
__('Cross-Location Order Management', 'multi-location-product-and-inventory-management-pro'),
    ),       
     function () {
                echo '<p>' . esc_html__('Configure how orders containing products from multiple locations are handled.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            },
            'location-cross-order-settings'
        );


        // Add "Allow Mixed-Location Cart" field
        add_settings_field(
            'allow_mixed_location_cart',
            __('Allow Mixed-Location Cart', 'multi-location-product-and-inventory-management-pro'),
            function () {
                if (mulopimfwc_premium_feature()) {
                    $is_manual_mode = $this->is_manual_assignment_strict_mode();
                    $options = $this->get_display_options();
                    $location_wise_currency_enabled = function_exists('mulopimfwc_is_location_wise_currency_enabled')
                        ? mulopimfwc_is_location_wise_currency_enabled($options)
                        : (isset($options['location_wise_currency']) && $options['location_wise_currency'] === 'on');
                    $disabled = $is_manual_mode || $location_wise_currency_enabled;

                    if ($is_manual_mode) {
                        $this->render_manual_hidden_input('allow_mixed_location_cart', $options['allow_mixed_location_cart'] ?? 'off');
                    } elseif ($location_wise_currency_enabled) {
                        echo '<input type="hidden" name="mulopimfwc_display_options[allow_mixed_location_cart]" value="off">';
                    }
                    $message = __("Allow customers to add products from different locations to their cart. When Enable Location Wise Currency is enabled, this option will be disabled.", 'multi-location-product-and-inventory-management-pro');
                    $message = $this->append_manual_disabled_note($message);
                    $this->render_advance_checkbox("allow_mixed_location_cart", $message, $disabled);
                } else {
                    $this->render_advance_checkbox("_pro", __("Allow customers to add products from different locations to their cart.", 'multi-location-product-and-inventory-management-pro'), true, false);
                }
            },
            'location-cross-order-settings',
            'mulopimfwc_cross_location_order_section'
        );

        // Add "Mixed Cart Warning Message" field
        /* add_settings_field(
            'mixed_cart_warning',
            __('Mixed Cart Warning Message', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', ['mixed_cart_warning' => __('Your cart contains products from multiple store locations.', 'multi-location-product-and-inventory-management-pro')]);
                $value = isset($options['mixed_cart_warning']) ? $options['mixed_cart_warning'] : __('Your cart contains products from multiple store locations.', 'multi-location-product-and-inventory-management-pro');
        
        ?>
             <textarea disabled name="mulopimfwc_display_options[mixed_cart_warning]" rows="3" class="large-text"><?php echo esc_textarea($value); 
                                                                                                                            ?></textarea>
             <p class="description"><?php echo esc_html_e('Warning message to display when cart contains products from multiple locations.', 'multi-location-product-and-inventory-management-pro'); 
                                        ?></p>
        <?php
            },
            'location-cross-order-settings',
            'mulopimfwc_cross_location_order_section'
        );
        */

        // Add "Group Cart Items by Location" field
        add_settings_field(
            'group_cart_by_location',
            __('Group Cart Items by Location', 'multi-location-product-and-inventory-management-pro'),
            function () {
                if (mulopimfwc_premium_feature()) {
                    $is_manual_mode = $this->is_manual_assignment_strict_mode();
                    $options = $this->get_display_options();
                    if ($is_manual_mode) {
                        $this->render_manual_hidden_input('group_cart_by_location', $options['group_cart_by_location'] ?? 'off');
                    }
                    $message = $this->append_manual_disabled_note(__("Group cart items by their store location for better visibility.", 'multi-location-product-and-inventory-management-pro'));
                    $this->render_advance_checkbox("group_cart_by_location", $message, $is_manual_mode);
                } else {
                    $this->render_advance_checkbox("group_cart_by_location", __("Group cart items by their store location for better visibility.", 'multi-location-product-and-inventory-management-pro'), true, false);
                }
            },
            'location-cross-order-settings',
            'mulopimfwc_cross_location_order_section'
        );

        // Cart Settings
        add_settings_section(
            'mulopimfwc_cart_settings_section',
           sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" 
     viewBox="0 0 24 24" 
     width="20" height="20" 
     style="margin-right:6px;vertical-align:middle;background-color:#ffedd5;padding:10px;border-radius:6px">
  <path fill="#ea580c" d="M7 4H3V2h5.2l1.3 4H21a1 1 0 0 1 .96 1.274l-2.5 8.5A1 1 0 0 1 18.5 17H8a1 1 0 0 1-.96-.726L4 4zm3 15a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm8 0a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3z"/>
</svg> %s',
__('Cart Settings', 'multi-location-product-and-inventory-management-pro'),
    ),        function () {
                echo '<p>' . esc_html__('Configure cart behavior for location-based shopping.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            },
            'lwp-cart-settings'
        );

        // Add Allow Location Change in Cart field
        add_settings_field(
            'allow_location_change_in_cart',
            __('Allow Location Change in Cart', 'multi-location-product-and-inventory-management-pro'),
            function () {
                $is_manual_mode = $this->is_manual_assignment_strict_mode();
                $options = $this->get_display_options();
                $location_wise_currency_enabled = function_exists('mulopimfwc_is_location_wise_currency_enabled')
                    ? mulopimfwc_is_location_wise_currency_enabled($options)
                    : (isset($options['location_wise_currency']) && $options['location_wise_currency'] === 'on');
                $disabled = $is_manual_mode || $location_wise_currency_enabled;

                if ($is_manual_mode) {
                    $this->render_manual_hidden_input('allow_location_change_in_cart', $options['allow_location_change_in_cart'] ?? 'off');
                } elseif ($location_wise_currency_enabled) {
                    echo '<input type="hidden" name="mulopimfwc_display_options[allow_location_change_in_cart]" value="off">';
                }
                $message = __("Allow customers to change the location for products in their cart if the product is available in multiple locations. When Enable Location Wise Currency is enabled, this option will be disabled.", 'multi-location-product-and-inventory-management-pro');
                $message = $this->append_manual_disabled_note($message);
                $this->render_advance_checkbox("allow_location_change_in_cart", $message, $disabled);
            },
            'lwp-cart-settings',
            'mulopimfwc_cart_settings_section'
        );

        // Checkout Settings
        add_settings_section(
            'mulopimfwc_checkout_settings_section',
           sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" style="margin-right:6px;vertical-align:middle;background-color:#e0f2fe;padding:10px;border-radius:6px">
  <path fill="#0284c7" d="M7 4h10a2 2 0 0 1 2 2v3h-2V6H7v12h10v-3h2v3a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2zm7 6h7v2h-7v-2zm0 4h5v2h-5v-2z"/>
</svg> %s',
__('Checkout Settings', 'multi-location-product-and-inventory-management-pro'),
    ),        function () {
                echo '<p>' . esc_html__('Configure checkout address behavior for location-based shopping.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            },
            'lwp-checkout-settings'
        );

        add_settings_field(
            'split_order_by_location',
            __('Split Order by Location', 'multi-location-product-and-inventory-management-pro'),
            function () {
                $options = $this->get_display_options();
                $is_manual_mode = $this->is_manual_assignment_strict_mode();
                $allow_mixed = function_exists('mulopimfwc_is_mixed_location_cart_enabled')
                    ? mulopimfwc_is_mixed_location_cart_enabled($options)
                    : (isset($options['allow_mixed_location_cart']) && $options['allow_mixed_location_cart'] === 'on');
                $is_premium = mulopimfwc_premium_feature();
                $disabled = $is_manual_mode || !$allow_mixed || !$is_premium;

                if ($disabled) {
                    echo '<input type="hidden" name="mulopimfwc_display_options[split_order_by_location]" value="' . esc_attr($options['split_order_by_location'] ?? 'off') . '">';
                }

                $message = __("Create a separate order for each location when the cart contains items from multiple locations.", 'multi-location-product-and-inventory-management-pro');
                if (!$allow_mixed) {
                    $message .= ' ' . __('Requires Allow Mixed-Location Cart to be enabled.', 'multi-location-product-and-inventory-management-pro');
                }
                $message = $this->append_manual_disabled_note($message);

                if ($is_premium) {
                    $this->render_advance_checkbox("split_order_by_location", $message, $disabled);
                } else {
                    $this->render_advance_checkbox("_pro", $message, true, false);
                }
            },
            'lwp-checkout-settings',
            'mulopimfwc_checkout_settings_section'
        );

        add_settings_field(
            'split_order_unknown_items',
            __('Unknown Location Items', 'multi-location-product-and-inventory-management-pro'),
            function () {
                $options = $this->get_display_options();
                $is_manual_mode = $this->is_manual_assignment_strict_mode();
                $allow_mixed = function_exists('mulopimfwc_is_mixed_location_cart_enabled')
                    ? mulopimfwc_is_mixed_location_cart_enabled($options)
                    : (isset($options['allow_mixed_location_cart']) && $options['allow_mixed_location_cart'] === 'on');
                $split_enabled = isset($options['split_order_by_location']) && $options['split_order_by_location'] === 'on';
                $is_premium = mulopimfwc_premium_feature();
                $disabled = $is_manual_mode || !$allow_mixed || !$split_enabled || !$is_premium;

                if ($disabled) {
                    echo '<input type="hidden" name="mulopimfwc_display_options[split_order_unknown_items]" value="' . esc_attr($options['split_order_unknown_items'] ?? 'block_checkout') . '">';
                }

                $select_options = [
                    'block_checkout' => __('Block checkout', 'multi-location-product-and-inventory-management-pro'),
                    'unassigned_child' => __('Create unassigned child order', 'multi-location-product-and-inventory-management-pro'),
                    'keep_in_parent' => __('Keep items in parent order', 'multi-location-product-and-inventory-management-pro'),
                ];

                $message = __("Choose how to handle items without a valid location when orders are split by location.", 'multi-location-product-and-inventory-management-pro');
                if (!$split_enabled) {
                    $message .= ' ' . __('Enable Split Order by Location to configure this option.', 'multi-location-product-and-inventory-management-pro');
                }
                $message = $this->append_manual_disabled_note($message);

                if ($is_premium) {
                    $this->render_advance_select('split_order_unknown_items', $message, $select_options, $disabled);
                } else {
                    echo '<label class="mulopimfwc_pro_only">';
                    $this->render_advance_select('split_order_unknown_items', $message, $select_options, true, false);
                    echo '</label>';
                }

                $notes = [
                    'block_checkout' => __('Blocks checkout if any item lacks a valid location. Customers must update the cart or choose a valid location before placing the order.', 'multi-location-product-and-inventory-management-pro'),
                    'unassigned_child' => __('Moves unknown-location items into a separate unassigned child order while other locations are split into their own orders.', 'multi-location-product-and-inventory-management-pro'),
                    'keep_in_parent' => __('Keeps unknown-location items in the parent order while location-assigned items are split into child orders.', 'multi-location-product-and-inventory-management-pro'),
                ];

                echo '<p class="description mulopimfwc-split-unknown-items-note" data-notes="' . esc_attr(wp_json_encode($notes)) . '"></p>';
            },
            'lwp-checkout-settings',
            'mulopimfwc_checkout_settings_section'
        );

        add_settings_field(
            'auto_populate_customer_addresses',
            __('Auto-Populate Customer Addresses', 'multi-location-product-and-inventory-management-pro'),
            function () {
                $message = __("Automatically fill in customer addresses based on their detected location for a faster checkout process.", 'multi-location-product-and-inventory-management-pro');
                $this->render_advance_checkbox("auto_populate_customer_addresses", $message, false);
            },
            'lwp-checkout-settings',
            'mulopimfwc_checkout_settings_section'
        );


        /**
         * Additional Settings for Multi Location Product & Inventory Management for WooCommerce
         */
        // Add User Experience Section
        add_settings_section(
            'mulopimfwc_customer_experience_section',
sprintf(
        '<svg viewBox="-0.5 0 33 33" 
     xmlns="http://www.w3.org/2000/svg"
     width="20" height="20" 
     style="margin-right:6px;vertical-align:middle;background-color:#dbeafe;padding:10px;border-radius:6px">
  <path fill="#2563eb" d="M16.5 0a9.5 9.5 0 0 1 4.581 17.825C27.427 19.947 32 25.94 32 33H0c0-7.3 4.888-13.458 11.57-15.379A9.5 9.5 0 0 1 16.5 0" fill="#1C1C1F"/>
</svg> %s',
        __('User Experience Settings', 'multi-location-product-and-inventory-management-pro')
    ),
            function () {
                echo '<p>' . esc_html__('Configure how customers interact with location-based features.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            },
            'location-customer-experience-settings'
        );

        // Location require settings
        add_settings_field(
            'location_require_selection',
            __('Require Location Selection', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', []);
                $is_manual_mode = $this->is_manual_assignment_mode();
                $message = __("Require customers to select a location before adding products to the cart.", 'multi-location-product-and-inventory-management-pro');
                if ($is_manual_mode) {
                    $message .= ' ' . __('Disabled while Manual or Inventory-Based assignment is enabled.', 'multi-location-product-and-inventory-management-pro');
                }
                if ($is_manual_mode) {
                    echo '<input type="hidden" data-manual-hidden="true" data-manual-for="mulopimfwc_display_options[location_require_selection]" name="mulopimfwc_display_options[location_require_selection]" value="' . esc_attr($options['location_require_selection'] ?? 'off') . '">';
                }
                $this->render_advance_checkbox("location_require_selection", $message, $is_manual_mode);
            },
            'location-customer-experience-settings',
            'mulopimfwc_customer_experience_section'
        );

        // Add Location Switching Behavior field
        add_settings_field(
            'location_switching_behavior',
            __('Location Switching Behavior', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['location_switching_behavior' => 'update_cart']);
                $value = isset($options['location_switching_behavior']) ? $options['location_switching_behavior'] : 'update_cart';
                $is_manual_mode = $this->is_manual_assignment_strict_mode();
                $allow_mixed = function_exists('mulopimfwc_is_mixed_location_cart_enabled')
                    ? mulopimfwc_is_mixed_location_cart_enabled($options)
                    : (isset($options['allow_mixed_location_cart']) && $options['allow_mixed_location_cart'] === 'on');
                $disabled = $is_manual_mode || $allow_mixed;
                $default_message = __('How to handle cart contents when a customer changes their location.', 'multi-location-product-and-inventory-management-pro');
                $mixed_message = __('Disabled because Allow Mixed-Location Cart is enabled. Mixed-location carts keep items from multiple locations, so this behavior is not applied.', 'multi-location-product-and-inventory-management-pro');
                $manual_note = __('Disabled while Manual, Inventory-Based, or Proximity-Based assignment is enabled without optional selection.', 'multi-location-product-and-inventory-management-pro');
                $description = $allow_mixed ? $mixed_message : $default_message;
                $description = $this->append_manual_disabled_note($description);

                if ($is_manual_mode) {
                    $this->render_manual_hidden_input('location_switching_behavior', $value);
                }
                if (!$is_manual_mode && $allow_mixed) {
                    echo '<input type="hidden" name="mulopimfwc_display_options[location_switching_behavior]" value="' . esc_attr($value) . '">';
                }

            ?>
                <select name="mulopimfwc_display_options[location_switching_behavior]" <?php disabled( $disabled ); ?>>
                    <option value="preserve_cart" <?php selected($value, 'preserve_cart'); ?>><?php echo esc_html_e('Preserve Cart (Keep all products regardless of availability)', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="update_cart" <?php selected($value, 'update_cart'); ?>><?php echo esc_html_e('Update Cart (Remove unavailable products)', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="prompt_user" <?php selected($value, 'prompt_user'); ?>><?php echo esc_html_e('Prompt User (Ask before updating cart)', 'multi-location-product-and-inventory-management-pro'); ?></option>
                </select>
                <p
                    class="description mulopimfwc-location-switching-behavior-description"
                    data-default-message="<?php echo esc_attr($default_message); ?>"
                    data-mixed-message="<?php echo esc_attr($mixed_message); ?>"
                    data-manual-note="<?php echo esc_attr($manual_note); ?>"><?php echo esc_html($description); ?></p>
            <?php
            },
            'location-customer-experience-settings',
            'mulopimfwc_customer_experience_section'
        );

        // Add Location Change Notification field
        add_settings_field(
            'location_change_notification',
            __('Location Change Notification', 'multi-location-product-and-inventory-management-pro'),
            function () {
                $is_manual_mode = $this->is_manual_assignment_strict_mode();
                $options = $this->get_display_options();
                $allow_mixed = function_exists('mulopimfwc_is_mixed_location_cart_enabled')
                    ? mulopimfwc_is_mixed_location_cart_enabled($options)
                    : (isset($options['allow_mixed_location_cart']) && $options['allow_mixed_location_cart'] === 'on');
                $disabled = $is_manual_mode || $allow_mixed;
                $default_message = __("Display a notification when a customer changes their location.", 'multi-location-product-and-inventory-management-pro');
                $mixed_message = __('Disabled because Allow Mixed-Location Cart is enabled. Customers can keep items from multiple locations, so no location-change notification is shown.', 'multi-location-product-and-inventory-management-pro');
                $manual_note = __('Disabled while Manual, Inventory-Based, or Proximity-Based assignment is enabled without optional selection.', 'multi-location-product-and-inventory-management-pro');
                if ($is_manual_mode) {
                    $this->render_manual_hidden_input('location_change_notification', $options['location_change_notification'] ?? 'off');
                }
                if (!$is_manual_mode && $allow_mixed) {
                    echo '<input type="hidden" name="mulopimfwc_display_options[location_change_notification]" value="' . esc_attr($options['location_change_notification'] ?? 'off') . '">';
                }

                $message = $allow_mixed ? $mixed_message : $default_message;
                $message = $this->append_manual_disabled_note($message);
                $this->render_advance_checkbox("location_change_notification", $message, $disabled);
                echo '<span class="mulopimfwc-location-change-notification-meta" style="display:none;" data-default-message="' . esc_attr($default_message) . '" data-mixed-message="' . esc_attr($mixed_message) . '" data-manual-note="' . esc_attr($manual_note) . '"></span>';
            },
            'location-customer-experience-settings',
            'mulopimfwc_customer_experience_section'
        );

        // Add Location Notification Note field
        add_settings_section(
            'location_notification_note',
            '',
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', []);
                $allow_mixed = function_exists('mulopimfwc_is_mixed_location_cart_enabled')
                    ? mulopimfwc_is_mixed_location_cart_enabled($options)
                    : (isset($options['allow_mixed_location_cart']) && $options['allow_mixed_location_cart'] === 'on');
            ?>
                <?php if ($allow_mixed) : ?>
                    <div style="display:flex;gap:16px;align-items:flex-start;background:#f8fafc;border:1px solid #e6eef8;padding:16px;border-radius:10px;max-width:820px;">
                        <div style="flex-shrink:0;">
                            <svg width="40" height="40" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false" style="display:block;">
                                <path fill="#2563eb" d="M12 2C8.7 2 6 4.7 6 8c0 5.2 6 11.1 6 11.1s6-6 6-11.1c0-3.3-2.7-6-6-6m0 3.9c1.1 0 2.1 1 2.1 2.1 0 1.2-.9 2.1-2.1 2.1S9.9 9.1 9.9 8c0-1.2 1-2.1 2.1-2.1z" />
                            </svg>
                        </div>
                        <div style="flex:1;">
                            <h4 style="margin:0 0 8px 0;color:#0f172a;font-weight:700;font-size:15px;"><?php echo esc_html__('Location Change Notice', 'multi-location-product-and-inventory-management-pro'); ?></h4>

                            <p style="margin:0 0 10px 0;color:#475569;line-height:1.5;">
                                <?php echo esc_html__('When a customer changes their selected location, the plugin can notify and update the cart based on configured behavior.', 'multi-location-product-and-inventory-management-pro'); ?>
                            </p>
                            <div style="margin-top:8px;padding:12px;background:#fff7ed;border-left:4px solid #f59e0b;border-radius:6px;color:#92400e;">
                                <strong><?php echo esc_html__('Important:', 'multi-location-product-and-inventory-management-pro'); ?></strong>
                                <span style="display:block;margin-top:6px;">
                                    <?php echo esc_html__('Allow Mixed-Location Cart is enabled. The cart will NOT be automatically updated when the customer changes location, and no update/notification will be shown. Customers may keep items from multiple locations.', 'multi-location-product-and-inventory-management-pro'); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <?php
            },
            'location-customer-experience-settings',
            'mulopimfwc_customer_experience_section'
        );
        // Advanced Location Pickup Settings
        add_settings_section(
            'mulopimfwc_location_pickup_section',
            sprintf(
                '<svg viewBox="0 0 1024 1024" 
     xmlns="http://www.w3.org/2000/svg"
     width="20" height="20" 
     style="margin-right:6px;vertical-align:middle;background-color:#ffedd5;padding:10px;border-radius:6px">
  <path fill="#ea580c" d="M128.896 736H96a32 32 0 0 1-32-32V224a32 32 0 0 1 32-32h576a32 32 0 0 1 32 32v96h164.544a32 32 0 0 1 31.616 27.136l54.144 352A32 32 0 0 1 922.688 736h-91.52a144 144 0 1 1-286.272 0H415.104a144 144 0 1 1-286.272 0zm23.36-64a143.872 143.872 0 0 1 239.488 0H568.32c17.088-25.6 42.24-45.376 71.744-55.808V256H128v416zm655.488 0h77.632l-19.648-128H704v64.896A144 144 0 0 1 807.744 672m48.128-192-14.72-96H704v96zM688 832a80 80 0 1 0 0-160 80 80 0 0 0 0 160m-416 0a80 80 0 1 0 0-160 80 80 0 0 0 0 160"/>
</svg> %s',
__('Advanced Location Pickup Settings', 'multi-location-product-and-inventory-management-pro'),
    ),        function () {
                echo '<p>' . esc_html__('Configure advanced settings for in-store pickup functionality.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            },
            'lwp-location-pickup-settings'
        );

        // Add "Enable Location Pickup" field
        add_settings_field(
            'enable_location_pickup',
            __('Enable Location Pickup', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['enable_location_pickup' => 'on']);
                $value = isset($options['enable_location_pickup']) ? $options['enable_location_pickup'] : 'on';

                // Check if local pickup is enabled in WooCommerce settings
                $pickup_settings = get_option('woocommerce_pickup_location_settings');

                if (mulopimfwc_premium_feature()) {
                ?>
                    <select name="mulopimfwc_display_options[enable_location_pickup]">
                        <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    </select>
                <?php } else { ?>
                    <label class="mulopimfwc_pro_only">
                        <select disabled name="_pro[enable_location_pickup]">
                            <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                            <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        </select>
                    </label>
                <?php } ?>
                <p class="description"><?php echo esc_html_e('Enable in-store pickup option for products at specific locations.', 'multi-location-product-and-inventory-management-pro'); ?></p>
                <?php
                if (isset($pickup_settings['enabled']) && $pickup_settings['enabled'] === 'yes') {
                    // Local pickup is enabled
                } else {
                    echo '<div class="lwp-notice lwp-notice-warning" style="background-color: #fff3cd; border: 1px solid #ffeeba; border-left: 4px solid #ffc107; border-radius: 4px; padding: 12px 16px; margin: 10px 0;">
                    <div style="display: flex; align-items: start; gap: 12px;">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" style="flex-shrink: 0; margin-top: 2px;">
                            <path fill="#856404" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z"/>
                        </svg>
                        <div>
                            <p style="margin: 0; color: #856404;">' . esc_html__('Local pickup is not enabled in WooCommerce settings. Please enable it to use this feature.', 'multi-location-product-and-inventory-management-pro') . '</p>
                        </div>
                    </div>
                </div>';
                }
            },
            'lwp-location-pickup-settings',
            'mulopimfwc_location_pickup_section'
        );

        // Add "Pickup Preparation Time" field
        add_settings_field(
            'pickup_preparation_time',
            __('Pickup Preparation Time (Hours)', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['pickup_preparation_time' => '24']);
                $value = isset($options['pickup_preparation_time']) ? $options['pickup_preparation_time'] : '24';
                ?>
                <input disabled type="number" name="mulopimfwc_display_options[pickup_preparation_time]" value="<?php echo esc_attr($value); ?>" min="1" max="168" class="small-text">
                <p class="description"><?php echo esc_html_e('Default preparation time in hours before an order is ready for pickup.', 'multi-location-product-and-inventory-management-pro'); ?></p>
                <?php
            },
            'lwp-location-pickup-settings',
            'mulopimfwc_location_pickup_section'
        );
        // Location-based Customer Insights
        add_settings_section(
            'mulopimfwc_customer_insights_section',
            sprintf(
                '<svg viewBox="0 0 24 24" 
     fill="none" 
     xmlns="http://www.w3.org/2000/svg"
     width="20" height="20" 
     style="margin-right:6px;vertical-align:middle;background-color:#dcfce7;padding:10px;border-radius:6px">
  <path fill="#16a34a" fill-rule="evenodd" clip-rule="evenodd" d="M4 3a1 1 0 0 0-2 0v17.2A1.8 1.8 0 0 0 3.8 22H21a1 1 0 1 0 0-2H4zm17.707 4.707a1 1 0 0 0-1.414-1.414L14 12.586l-3.293-3.293a1 1 0 0 0-1.414 0l-4 4a1 1 0 1 0 1.414 1.414L10 11.414l3.293 3.293a1 1 0 0 0 1.414 0z" fill="#000"/>
</svg> %s',__('Location-based Customer Insights', 'multi-location-product-and-inventory-management-pro'),
),
            function () {
                echo '<p>' . esc_html__('Configure customer analytics and insights based on location data.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            },
            'lwp-customer-insights-settings'
        );

        // Add "Enable Customer Location Tracking" field
        add_settings_field(
            'enable_customer_location_tracking',
            __('Enable Customer Location Tracking', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['enable_customer_location_tracking' => 'on']);
                $value = isset($options['enable_customer_location_tracking']) ? $options['enable_customer_location_tracking'] : 'on';
                $is_manual_mode = $this->is_manual_assignment_strict_mode();
                if ($is_manual_mode) {
                    $this->render_manual_hidden_input('enable_customer_location_tracking', $value);
                }
                if (mulopimfwc_premium_feature()) {
                ?>
                    <select name="mulopimfwc_display_options[enable_customer_location_tracking]" <?php disabled( $is_manual_mode ); ?>>
                        <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    </select>
                <?php } else { ?>
                    <label class="mulopimfwc_pro_only">
                        <select disabled name="_pro[enable_customer_location_tracking]">
                            <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                            <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        </select>
                    </label>
                <?php } ?>
                <p class="description"><?php echo esc_html($this->append_manual_disabled_note(__('Track and analyze customer preferences by location.', 'multi-location-product-and-inventory-management-pro'))); ?></p>
            <?php
            },
            'lwp-customer-insights-settings',
            'mulopimfwc_customer_insights_section'
        );

        // Add "Customer Location History" field
        add_settings_field(
            'customer_location_history',
            __('Customer Location History', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['customer_location_history' => 'latest']);
                $value = isset($options['customer_location_history']) ? $options['customer_location_history'] : 'latest';
                $is_manual_mode = $this->is_manual_assignment_strict_mode();
                if ($is_manual_mode) {
                    $this->render_manual_hidden_input('customer_location_history', $value);
                }
            ?>
                <select name="mulopimfwc_display_options[customer_location_history]" <?php disabled( $is_manual_mode ); ?>>
                    <option value="latest" <?php selected($value, 'latest'); ?>><?php echo esc_html_e('Store Latest Only', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="all" <?php selected($value, 'all'); ?>><?php echo esc_html_e('Store Full History', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="none" <?php selected($value, 'none'); ?>><?php echo esc_html_e('Do Not Store', 'multi-location-product-and-inventory-management-pro'); ?></option>
                </select>
                <p class="description"><?php echo esc_html($this->append_manual_disabled_note(__('How to store customer location selection history.', 'multi-location-product-and-inventory-management-pro'))); ?></p>
                <?php
            },
            'lwp-customer-insights-settings',
            'mulopimfwc_customer_insights_section'
        );

        // Add "Location-based Recommendations" field
        add_settings_field(
            'location_based_recommendations',
            __('Location-based Recommendations', 'multi-location-product-and-inventory-management-pro'),
            function () {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', ['location_based_recommendations' => 'on']);
                $value = isset($options['location_based_recommendations']) ? $options['location_based_recommendations'] : 'on';
                $is_manual_mode = $this->is_manual_assignment_strict_mode();
                if ($is_manual_mode) {
                    $this->render_manual_hidden_input('location_based_recommendations', $value);
                }
                if (mulopimfwc_premium_feature()) {
                ?>
                    <select name="mulopimfwc_display_options[location_based_recommendations]" <?php disabled( $is_manual_mode ); ?>>
                        <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    </select>
                <?php } else { ?>
                    <label class="mulopimfwc_pro_only">
                        <select disabled name="_pro[location_based_recommendations]">
                            <option value="on" <?php selected($value, 'on'); ?>><?php echo esc_html_e('On', 'multi-location-product-and-inventory-management-pro'); ?></option>
                            <option value="off" <?php selected($value, 'off'); ?>><?php echo esc_html_e('Off', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        </select>
                    </label>
                <?php } ?>
                <p class="description"><?php echo esc_html($this->append_manual_disabled_note(__('Show product recommendations based on location popularity.', 'multi-location-product-and-inventory-management-pro'))); ?></p>
            <?php
            },
            'lwp-customer-insights-settings',
            'mulopimfwc_customer_insights_section'
        );
    }

    private function sanitize_generic_setting_value($value)
    {
        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $key => $item) {
                $sanitized_key = is_string($key) ? sanitize_key($key) : $key;
                $sanitized[$sanitized_key] = $this->sanitize_generic_setting_value($item);
            }

            return $sanitized;
        }

        if (is_scalar($value)) {
            return sanitize_textarea_field((string) $value);
        }

        return '';
    }

    private function sanitize_on_off_select(array $input, array $existing_options, string $key, string $default = 'off'): string
    {
        $fallback = isset($existing_options[$key]) ? (string) $existing_options[$key] : $default;
        $value = isset($input[$key]) ? sanitize_text_field((string) $input[$key]) : $fallback;

        return $value === 'on' ? 'on' : 'off';
    }

    private function sanitize_nested_on_off_value(array $input, array $existing_settings, string $key, string $default = 'off'): string
    {
        $fallback = isset($existing_settings[$key]) ? (string) $existing_settings[$key] : $default;
        $value = isset($input[$key]) ? sanitize_text_field((string) $input[$key]) : $fallback;

        return $value === 'on' ? 'on' : 'off';
    }

    private function sanitize_enum_select(array $input, array $existing_options, string $key, array $allowed_values, string $default): string
    {
        $fallback = isset($existing_options[$key]) ? (string) $existing_options[$key] : $default;
        $value = isset($input[$key]) ? sanitize_key((string) $input[$key]) : $fallback;

        if (in_array($value, $allowed_values, true)) {
            return $value;
        }

        return in_array($fallback, $allowed_values, true) ? $fallback : $default;
    }

    public function sanitize_settings($input)
    {
        global $mulopimfwc_options;
        $existing_options = is_array($mulopimfwc_options ?? null)
            ? $mulopimfwc_options
            : get_option('mulopimfwc_display_options', []);

        $sanitized = is_array($existing_options) ? $existing_options : [];

        if (is_array($input)) {
            foreach ($input as $key => $value) {
                if (!is_string($key)) {
                    continue;
                }

                $sanitized[sanitize_key($key)] = $this->sanitize_generic_setting_value($value);
            }
        }

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

        $sanitized['enable_location_shipping'] = $this->sanitize_on_off_select($input, $existing_options, 'enable_location_shipping', 'off');
        $sanitized['enable_location_payment_methods'] = $this->sanitize_on_off_select($input, $existing_options, 'enable_location_payment_methods', 'off');
        $sanitized['enable_location_taxes'] = $this->sanitize_on_off_select($input, $existing_options, 'enable_location_taxes', 'off');
        $sanitized['enable_location_pickup'] = $this->sanitize_on_off_select($input, $existing_options, 'enable_location_pickup', 'on');
        $sanitized['shipping_calculation_method'] = $this->sanitize_enum_select(
            $input,
            $existing_options,
            'shipping_calculation_method',
            ['per_location', 'nearest_with_transfer'],
            'per_location'
        );

        if (isset($input['pickup_preparation_time'])) {
            $pickup_preparation_time = absint($input['pickup_preparation_time']);
            if ($pickup_preparation_time < 1) {
                $pickup_preparation_time = 1;
            } elseif ($pickup_preparation_time > 168) {
                $pickup_preparation_time = 168;
            }
            $sanitized['pickup_preparation_time'] = (string) $pickup_preparation_time;
        }

        // Handle order assignment method.
        $valid_assignment_methods = ['customer_selection', 'inventory_based', 'proximity_based', 'manual'];
        $order_assignment_method = isset($input['order_assignment_method'])
            ? sanitize_key((string) $input['order_assignment_method'])
            : 'customer_selection';
        if (!in_array($order_assignment_method, $valid_assignment_methods, true)) {
            $order_assignment_method = 'customer_selection';
        }

        // Handle manual optional location selection (checkbox)
        if (isset($input['manual_optional_location_selection']) && $input['manual_optional_location_selection'] === 'on') {
            $sanitized['manual_optional_location_selection'] = 'on';
        } else {
            $sanitized['manual_optional_location_selection'] = 'off';
        }

        // Handle filtered_sections
        $sanitized['filtered_sections'] = [];
        if (isset($input['filtered_sections']) && is_array($input['filtered_sections'])) {
            foreach ($input['filtered_sections'] as $section) {
                $sanitized['filtered_sections'][] = sanitize_text_field($section);
            }
        }

        // Handle location access by user role.
        $sanitized['enable_location_by_user_role'] = [];
        if (isset($input['enable_location_by_user_role']) && is_array($input['enable_location_by_user_role'])) {
            foreach ($input['enable_location_by_user_role'] as $role) {
                $sanitized['enable_location_by_user_role'][] = sanitize_key((string) $role);
            }
        }

        // Handle product_priority_display option
        if (isset($input['product_priority_display'])) {
            $valid_values = ['location_first', 'global_first', 'mixed'];
            $value = sanitize_text_field($input['product_priority_display']);
            $sanitized['product_priority_display'] = in_array($value, $valid_values, true) ? $value : 'location_first';
        }

        // Handle single_product_unavailable_behavior option
        if (isset($input['single_product_unavailable_behavior'])) {
            $valid_values = ['show_404', 'show_message'];
            $value = sanitize_text_field($input['single_product_unavailable_behavior']);
            $sanitized['single_product_unavailable_behavior'] = in_array($value, $valid_values, true) ? $value : 'show_404';
        }
        // Handle hide_out_of_stock_locations option (checkbox)
        if (isset($input['hide_out_of_stock_locations']) && $input['hide_out_of_stock_locations'] === 'on') {
            $sanitized['hide_out_of_stock_locations'] = 'on';
        } else {
            $sanitized['hide_out_of_stock_locations'] = 'off';
        }
        // Handle stock_display_format option
        if (isset($input['stock_display_format'])) {
            $valid_values = ['exact_count', 'availability_only', 'stock_levels', 'hide_stock'];
            $value = sanitize_text_field($input['stock_display_format']);
            $sanitized['stock_display_format'] = in_array($value, $valid_values, true) ? $value : 'exact_count';
        }
        // Handle enable_location_stock option
        if (isset($input['enable_location_stock'])) {
            $sanitized['enable_location_stock'] = sanitize_text_field($input['enable_location_stock']);
        }

        // Handle enable_location_price option
        if (isset($input['enable_location_price'])) {
            $sanitized['enable_location_price'] = sanitize_text_field($input['enable_location_price']);
        }
        $is_location_price_enabled = isset($input['enable_location_price']) && $input['enable_location_price'] === 'on';

        // Handle location_wise_currency option (checkbox)
        if (isset($input['location_wise_currency']) && $input['location_wise_currency'] === 'on') {
            $sanitized['location_wise_currency'] = 'on';
        } else {
            $sanitized['location_wise_currency'] = 'off';
        }
        if (!$is_location_price_enabled) {
            $sanitized['location_wise_currency'] = 'off';
        }
        if ($sanitized['location_wise_currency'] === 'on' && $order_assignment_method === 'manual') {
            $order_assignment_method = 'proximity_based';
        }
        $sanitized['order_assignment_method'] = $order_assignment_method;

        // Handle allow_mixed_location_cart option (checkbox)
        if (
            isset($input['allow_mixed_location_cart']) &&
            $input['allow_mixed_location_cart'] === 'on' &&
            $sanitized['location_wise_currency'] !== 'on'
        ) {
            $sanitized['allow_mixed_location_cart'] = 'on';
        } else {
            $sanitized['allow_mixed_location_cart'] = 'off';
        }

        // Handle allow_location_change_in_cart option (checkbox)
        if (
            isset($input['allow_location_change_in_cart']) &&
            $input['allow_location_change_in_cart'] === 'on' &&
            $sanitized['location_wise_currency'] !== 'on'
        ) {
            $sanitized['allow_location_change_in_cart'] = 'on';
        } else {
            $sanitized['allow_location_change_in_cart'] = 'off';
        }

        // Handle auto_populate_customer_addresses option (checkbox)
        if (isset($input['auto_populate_customer_addresses']) && $input['auto_populate_customer_addresses'] === 'on') {
            $sanitized['auto_populate_customer_addresses'] = 'on';
        } else {
            $sanitized['auto_populate_customer_addresses'] = 'off';
        }

        // Handle split_order_by_location option (checkbox)
        if (
            isset($input['split_order_by_location']) &&
            $input['split_order_by_location'] === 'on' &&
            $sanitized['allow_mixed_location_cart'] === 'on'
        ) {
            $sanitized['split_order_by_location'] = 'on';
        } else {
            $sanitized['split_order_by_location'] = 'off';
        }

        // Handle split_order_unknown_items option
        if (isset($input['split_order_unknown_items'])) {
            $value = sanitize_text_field($input['split_order_unknown_items']);
            $valid_values = ['block_checkout', 'unassigned_child', 'keep_in_parent'];
            $sanitized['split_order_unknown_items'] = in_array($value, $valid_values, true)
                ? $value
                : 'block_checkout';
        } else {
            $sanitized['split_order_unknown_items'] = 'block_checkout';
        }

        // Handle enable_product_filter option (checkbox - when unchecked, it's not in input, so default to 'off')
        if (isset($input['enable_product_filter']) && $input['enable_product_filter'] === 'on') {
            $sanitized['enable_product_filter'] = 'on';
        } else {
            // Checkbox unchecked or not present - set to 'off'
            $sanitized['enable_product_filter'] = 'off';
        }

        // Handle text management master toggle.
        if (isset($input['enable_text_management']) && $input['enable_text_management'] === 'on') {
            $sanitized['enable_text_management'] = 'on';
        } else {
            $sanitized['enable_text_management'] = 'off';
        }

        // Handle social notifications
        if (isset($input['social_notifications']) && is_array($input['social_notifications'])) {
            $social_input = $input['social_notifications'];
            $existing_social = isset($existing_options['social_notifications']) && is_array($existing_options['social_notifications'])
                ? $existing_options['social_notifications']
                : [];
            $social = [];
            $checkbox_keys = [
                'enabled',
                'new_order',
                'low_stock',
                'out_of_stock',
                'daily_digest',
                'site_status',
                'payment_failed',
                'order_refunded',
                'order_cancelled',
                'order_completed',
                'restocked',
                'low_review_alert',
                'manager_change_alert',
                'high_value_order',
            ];
            foreach ($checkbox_keys as $key) {
                $social[$key] = $this->sanitize_nested_on_off_value($social_input, $existing_social, $key, 'off');
            }
            if (!empty($social_input['admin_slack_webhook'])) {
                $social['admin_slack_webhook'] = esc_url_raw($social_input['admin_slack_webhook']);
            }
            if (!empty($social_input['admin_custom_webhook'])) {
                $social['admin_custom_webhook'] = esc_url_raw($social_input['admin_custom_webhook']);
            }
            if (!empty($social_input['admin_telegram_chat_id'])) {
                $social['admin_telegram_chat_id'] = sanitize_text_field($social_input['admin_telegram_chat_id']);
            }
            if (!empty($social_input['telegram_bot_token'])) {
                $social['telegram_bot_token'] = sanitize_text_field($social_input['telegram_bot_token']);
            }
            if (isset($social_input['daily_digest_time'])) {
                $digest_time_raw = $social_input['daily_digest_time'];
                if (is_array($digest_time_raw)) {
                    $digest_time_raw = reset($digest_time_raw);
                }
                $digest_time = is_string($digest_time_raw) ? sanitize_text_field($digest_time_raw) : '';
                if (preg_match('/^\d{1,2}:\d{2}$/', $digest_time)) {
                    $social['daily_digest_time'] = $digest_time;
                }
            }

            if (isset($social_input['high_value_threshold'])) {
                $social['high_value_threshold'] = sanitize_text_field($social_input['high_value_threshold']);
            }
            if (isset($social_input['low_review_threshold'])) {
                $social['low_review_threshold'] = sanitize_text_field($social_input['low_review_threshold']);
            }

            if (isset($social_input['channels']) && is_array($social_input['channels'])) {
                $channels = [];
                foreach ($social_input['channels'] as $channel) {
                    if (!is_array($channel)) {
                        continue;
                    }
                    $type = isset($channel['type']) ? sanitize_text_field($channel['type']) : '';
                    $webhook = isset($channel['webhook']) ? esc_url_raw($channel['webhook']) : '';
                    $label = isset($channel['label']) ? sanitize_text_field($channel['label']) : '';
                    $chat_id = isset($channel['chat_id']) ? sanitize_text_field($channel['chat_id']) : '';
                    $bot_token = isset($channel['bot_token']) ? sanitize_text_field($channel['bot_token']) : '';

                    if (empty($type) && empty($webhook) && empty($chat_id)) {
                        continue;
                    }

                    $channels[] = [
                        'type' => $type,
                        'label' => $label,
                        'webhook' => $webhook,
                        'chat_id' => $chat_id,
                        'bot_token' => $bot_token,
                    ];
                }
                $social['channels'] = $channels;
            }

            $sanitized['social_notifications'] = $social;
        }

        if (isset($input['notification_settings']) && is_array($input['notification_settings'])) {
            $notification_input = $input['notification_settings'];
            $existing_notification = isset($existing_options['notification_settings']) && is_array($existing_options['notification_settings'])
                ? $existing_options['notification_settings']
                : [];
            $notification = [];
            $checkbox_keys = [
                'realtime_enabled',
                'floating_enabled',
                'pwa_enabled',
                'show_admin_notice',
                'sound_enabled',
            ];
            foreach ($checkbox_keys as $key) {
                $notification[$key] = $this->sanitize_nested_on_off_value(
                    $notification_input,
                    $existing_notification,
                    $key,
                    $key === 'show_admin_notice' ? 'on' : 'off'
                );
            }

            $position = sanitize_text_field(
                $notification_input['floating_position']
                    ?? ($existing_notification['floating_position'] ?? 'top-right')
            );
            $valid_positions = ['top-right', 'top-left', 'bottom-right', 'bottom-left'];
            if (!in_array($position, $valid_positions, true)) {
                $position = 'top-right';
            }
            $notification['floating_position'] = $position;

            $size = sanitize_text_field(
                $notification_input['floating_size']
                    ?? ($existing_notification['floating_size'] ?? 'comfy')
            );
            $valid_sizes = ['comfy', 'compact'];
            if (!in_array($size, $valid_sizes, true)) {
                $size = 'comfy';
            }
            $notification['floating_size'] = $size;

            $duration_raw = isset($notification_input['floating_duration'])
                ? (string) $notification_input['floating_duration']
                : (string) ($existing_notification['floating_duration'] ?? '6000');
            $duration = preg_replace('/[^0-9]/', '', $duration_raw);
            $notification['floating_duration'] = (string) max(2000, absint($duration !== '' ? $duration : '6000'));

            $notification['notification_template'] = sanitize_textarea_field(
                $notification_input['notification_template']
                    ?? ($existing_notification['notification_template'] ?? '[{event}] {message}')
            );

            $poll_interval_raw = isset($notification_input['poll_interval'])
                ? (string) $notification_input['poll_interval']
                : (string) ($existing_notification['poll_interval'] ?? '30000');
            $poll_interval = preg_replace('/[^0-9]/', '', $poll_interval_raw);
            $notification['poll_interval'] = (string) max(5000, absint($poll_interval !== '' ? $poll_interval : '30000'));

            $notification_style = sanitize_key(
                (string) ($notification_input['notification_style'] ?? ($existing_notification['notification_style'] ?? 'modern'))
            );
            $valid_notification_styles = ['modern', 'minimal', 'classic'];
            if (!in_array($notification_style, $valid_notification_styles, true)) {
                $notification_style = 'modern';
            }
            $notification['notification_style'] = $notification_style;

            $sanitized['notification_settings'] = $notification;
        }

        // Handle enable_location_backorder option
        if (isset($input['enable_location_backorder'])) {
            $sanitized['enable_location_backorder'] = sanitize_text_field($input['enable_location_backorder']);
        }
        // Handle enable_location_information option
        if (isset($input['enable_location_information'])) {
            $sanitized['enable_location_information'] = sanitize_text_field($input['enable_location_information']);
        }

        // Handle branding options
        if (isset($input['branding_popup_background'])) {
            $sanitized['branding_popup_background'] = sanitize_text_field($input['branding_popup_background']);
        }
        if (isset($input['branding_popup_text'])) {
            $sanitized['branding_popup_text'] = sanitize_text_field($input['branding_popup_text']);
        }
        if (isset($input['branding_popup_overlay'])) {
            $sanitized['branding_popup_overlay'] = sanitize_text_field($input['branding_popup_overlay']);
        }
        if (isset($input['branding_primary_color'])) {
            $sanitized['branding_primary_color'] = sanitize_text_field($input['branding_primary_color']);
        }
        if (isset($input['branding_secondary_color'])) {
            $sanitized['branding_secondary_color'] = sanitize_text_field($input['branding_secondary_color']);
        }
        if (isset($input['branding_border_color'])) {
            $sanitized['branding_border_color'] = sanitize_text_field($input['branding_border_color']);
        }
        if (isset($input['branding_button_text'])) {
            $sanitized['branding_button_text'] = sanitize_text_field($input['branding_button_text']);
        }
        if (isset($input['branding_font_size'])) {
            $sanitized['branding_font_size'] = sanitize_text_field($input['branding_font_size']);
        }
        if (isset($input['branding_font_family'])) {
            $sanitized['branding_font_family'] = sanitize_text_field($input['branding_font_family']);
        }
        if (isset($input['branding_box_shadow'])) {
            $sanitized['branding_box_shadow'] = sanitize_text_field($input['branding_box_shadow']);
        }
        if (isset($input['branding_border_radius'])) {
            $sanitized['branding_border_radius'] = sanitize_text_field($input['branding_border_radius']);
        }

        return $sanitized;
    }

    /**
     * Flush rewrite rules after settings are saved (options.php redirect).
     */
    public function maybe_flush_rewrite_rules_on_settings_save()
    {
        if (!is_admin()) {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ($page !== 'multi-location-product-and-inventory-management-settings') {
            return;
        }

        $updated = isset($_GET['settings-updated']) ? sanitize_text_field(wp_unslash($_GET['settings-updated'])) : '';
        if ($updated !== 'true' && $updated !== '1') {
            return;
        }

        static $did_flush = false;
        if ($did_flush) {
            return;
        }
        $did_flush = true;

        global $MULOPIMFWC_Admin;
        if ($MULOPIMFWC_Admin instanceof MULOPIMFWC_Admin) {
            $MULOPIMFWC_Admin->register_store_location_taxonomy();
        }

        delete_option('mulopimfwc_flush_rewrite_pending');
        flush_rewrite_rules();
    }

    public function settings_section_callback()
    {
        echo '<p>' . esc_html__('Configure how store locations appear with product titles.', 'multi-location-product-and-inventory-management-pro') . '</p>';
    }

    public function display_format_field_callback()
    {
        $options = $this->get_display_options();
        $format = isset($options['display_format']) ? $options['display_format'] : 'none';

            ?>
            <select id="mulopimfwc_display_title" name="mulopimfwc_display_options[display_format]">
                <option value="append" <?php selected($format, 'append'); ?>><?php echo esc_html_e('Append to title (Title - Location)', 'multi-location-product-and-inventory-management-pro'); ?></option>
                <option value="prepend" <?php selected($format, 'prepend'); ?>><?php echo esc_html_e('Prepend to title (Location - Title)', 'multi-location-product-and-inventory-management-pro'); ?></option>
                <option value="brackets" <?php selected($format, 'brackets'); ?>><?php echo esc_html_e('In brackets (Title [Location])', 'multi-location-product-and-inventory-management-pro'); ?></option>
                <option value="none" <?php selected($format, 'none'); ?>><?php echo esc_html_e('Do not display location', 'multi-location-product-and-inventory-management-pro'); ?></option>
            </select>
        <?php
    }

    public function separator_field_callback()
    {
        $options = $this->get_display_options();
        $separator = isset($options['separator']) ? $options['separator'] : ' - ';
        ?>
            <input type="text" name="mulopimfwc_display_options[separator]" value="<?php echo esc_attr($separator); ?>" class="regular-text">
            <p class="description"><?php echo esc_html_e('The character(s) used to separate the title and location.', 'multi-location-product-and-inventory-management-pro'); ?></p>
        <?php
    }

    public function enabled_pages_field_callback()
    {
        $options = $this->get_display_options();
        $enabled_pages = isset($options['enabled_pages']) ? $options['enabled_pages'] : ['shop', 'single', 'cart'];
        $pages = [
            'shop' => __('Shop/Archive Pages', 'multi-location-product-and-inventory-management-pro'),
            'single' => __('Single Product Pages', 'multi-location-product-and-inventory-management-pro'),
            'Shortcode' => __('Others pages', 'multi-location-product-and-inventory-management-pro')
        ];

        foreach ($pages as $value => $label) {
            $checked = in_array($value, $enabled_pages) ? 'checked' : '';
            echo "<label><input type='checkbox' name='mulopimfwc_display_options[enabled_pages][]' value='" . esc_attr($value) . "' " . esc_attr($checked) . "> " . esc_html($label) . "</label><br>";
        }
    }

    public function mls_nav_tabs($href, $active_class, $svg, $title)
    {
        return '<a href="' . $href . '" class="nav-tab ' . $active_class . '">' . $svg . $title . '</a>';
    }

    public function settings_page_content()
    {
        ?>
            <div class="wrap">
                <h1 style="display: none !important;"><?php echo esc_html__('Settings', 'multi-location-product-and-inventory-management-pro'); ?></h1>
                <!-- welcome box here -->
                <div class="plugincy-filter-welcome-container">
                    <div class="welcome-header">
                        <div class="plugincy-plugin-icon">
                            <span class="dashicons dashicons-location-alt"></span>
                        </div>
                        <div class="header-content">
                            <div><?php echo esc_html__('Multi Location Product & Inventory Management for WooCommerce Pro', 'multi-location-product-and-inventory-management-pro'); ?></div>
                            <p class="tagline"><?php echo esc_html__('Manage products, inventory, and pricing across multiple store locations effortlessly', 'multi-location-product-and-inventory-management-pro'); ?></p>
                        </div>
                        <div class="version-badge">
                            <span><?php echo esc_html__('Pro Version', 'multi-location-product-and-inventory-management-pro'); ?></span>
                        </div>
                    </div>

                    <div class="welcome-content">
                        <div class="quick-actions">
                            <h3><?php echo esc_html__('Quick Start Guide:', 'multi-location-product-and-inventory-management-pro'); ?></h3>
                            <div class="action-steps">
                                <div class="step">
                                    <span class="step-number"><?php echo esc_html__('1', 'multi-location-product-and-inventory-management-pro'); ?></span>
                                    <div class="step-content">
                                        <h4><?php echo esc_html__('Create Locations', 'multi-location-product-and-inventory-management-pro'); ?></h4>
                                        <p><?php echo esc_html__('Set up your store locations with names, addresses, and contact details', 'multi-location-product-and-inventory-management-pro'); ?></p>
                                    </div>
                                </div>
                                <div class="step">
                                    <span class="step-number"><?php echo esc_html__('2', 'multi-location-product-and-inventory-management-pro'); ?></span>
                                    <div class="step-content">
                                        <h4><?php echo esc_html__('Assign Products', 'multi-location-product-and-inventory-management-pro'); ?></h4>
                                        <p><?php echo esc_html__('Link products to locations and manage stock, pricing, and availability', 'multi-location-product-and-inventory-management-pro'); ?></p>
                                    </div>
                                </div>
                                <div class="step">
                                    <span class="step-number"><?php echo esc_html__('3', 'multi-location-product-and-inventory-management-pro'); ?></span>
                                    <div class="step-content">
                                        <h4><?php echo esc_html__('Enable Location Selector', 'multi-location-product-and-inventory-management-pro'); ?></h4>
                                        <p><?php echo esc_html__('Let customers choose their preferred location and see relevant products', 'multi-location-product-and-inventory-management-pro'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="cta-section">
                            <div class="cta-buttons">
                                <a href="https://demo.plugincy.com/multi-location-product-and-inventory-management/" target="_blank" class="btn btn-accent">
                                    <span class="dashicons dashicons-visibility"></span>
                                    <?php echo esc_html__('View Demo', 'multi-location-product-and-inventory-management-pro'); ?>
                                </a>
                                <a href="https://plugincy.com/documentations/multi-location-product-inventory-management-for-woocommerce/"
                                    target="_blank" class="btn btn-primary">
                                    <span class="dashicons dashicons-book"></span>
                                    <?php echo esc_html__('View Documentation', 'multi-location-product-and-inventory-management-pro'); ?>
                                </a>
                                <a href="https://www.plugincy.com/support/"
                                    target="_blank" class="btn btn-secondary">
                                    <span class="dashicons dashicons-sos"></span>
                                    <?php echo esc_html__('Get Support', 'multi-location-product-and-inventory-management-pro'); ?>
                                </a>
                            </div>

                            <div class="support-info">
                                <div class="support-item">
                                    <span class="dashicons dashicons-location"></span>
                                    <span><?php echo esc_html__('Unlimited Locations', 'multi-location-product-and-inventory-management-pro'); ?></span>
                                </div>
                                <div class="support-item">
                                    <span class="dashicons dashicons-update"></span>
                                    <span><?php echo esc_html__('Regular Updates', 'multi-location-product-and-inventory-management-pro'); ?></span>
                                </div>
                                <div class="support-item">
                                    <span class="dashicons dashicons-shield"></span>
                                    <span><?php echo esc_html__('Premium Support', 'multi-location-product-and-inventory-management-pro'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <style>
                    .plugincy-filter-welcome-container {
                        margin: 20px auto;
                        background: #fff;
                        border-radius: 12px;
                        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 10px 20px rgba(0, 0, 0, 0.1);
                        overflow: hidden;
                        animation: slideIn 0.6s ease-out;
                    }

                    @keyframes slideIn {
                        from {
                            opacity: 0;
                            transform: translateY(20px);
                        }

                        to {
                            opacity: 1;
                            transform: translateY(0);
                        }
                    }

                    .plugincy-filter-welcome-container .welcome-header {
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        color: white;
                        padding: 25px 20px;
                        display: flex;
                        align-items: center;
                        position: relative;
                        overflow: hidden;
                    }

                    .plugincy-filter-welcome-container .welcome-header::before {
                        content: '';
                        position: absolute;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.05)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
                        opacity: 0.3;
                    }

                    .plugincy-filter-welcome-container .plugincy-plugin-icon {
                        font-size: 48px;
                        margin-right: 20px;
                        opacity: 0.9;
                        position: relative;
                        z-index: 2;
                    }

                    .plugincy-filter-welcome-container .plugincy-plugin-icon .dashicons {
                        font-size: 48px;
                        width: 48px;
                        height: 48px;
                    }

                    .plugincy-filter-welcome-container .header-content {
                        flex: 1;
                        position: relative;
                        z-index: 2;
                    }

                    .plugincy-filter-welcome-container .header-content div {
                        font-size: 28px;
                        font-weight: 700;
                        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                        color: #fff;
                        padding: 0 0 10px;
                        line-height: 1.2;
                    }

                    .plugincy-filter-welcome-container .tagline {
                        font-size: 16px;
                        opacity: 0.9;
                        font-weight: 400;
                        margin: 0;
                    }

                    .plugincy-filter-welcome-container .version-badge {
                        position: relative;
                        z-index: 2;
                    }

                    .plugincy-filter-welcome-container .version-badge span {
                        background: rgba(255, 255, 255, 0.2);
                        padding: 6px 16px;
                        border-radius: 20px;
                        font-size: 12px;
                        font-weight: 600;
                        text-transform: uppercase;
                        letter-spacing: 0.5px;
                        backdrop-filter: blur(10px);
                        border: 1px solid rgba(255, 255, 255, 0.3);
                    }

                    .plugincy-filter-welcome-container .welcome-content {
                        padding: 20px;
                    }

                    .plugincy-filter-welcome-container .quick-actions {
                        background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
                        padding: 20px;
                        border-radius: 10px;
                        margin-bottom: 20px;
                    }

                    .plugincy-filter-welcome-container .quick-actions h3 {
                        color: #2d3748;
                        margin-bottom: 20px;
                        font-size: 20px;
                        margin-top: 0;
                    }

                    .plugincy-filter-welcome-container .quick-actions h3 .dashicons {
                        color: #667eea;
                        font-size: 20px;
                        width: 20px;
                        height: 20px;
                    }

                    .plugincy-filter-welcome-container .action-steps {
                        display: flex;
                        gap: 20px;
                        flex-wrap: wrap;
                    }

                    .plugincy-filter-welcome-container .step {
                        flex: 1;
                        min-width: 200px;
                        display: flex;
                        align-items: flex-start;
                        gap: 15px;
                    }

                    .plugincy-filter-welcome-container .step-number {
                        background: #667eea;
                        color: white;
                        width: 30px;
                        height: 30px;
                        border-radius: 50%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-weight: 600;
                        font-size: 14px;
                        flex-shrink: 0;
                    }

                    .plugincy-filter-welcome-container .step-content h4 {
                        color: #2d3748;
                        margin-bottom: 4px;
                        font-size: 16px;
                        margin: 0;
                    }

                    .plugincy-filter-welcome-container .step-content p {
                        color: #718096;
                        font-size: 14px;
                    }

                    .plugincy-filter-welcome-container .cta-section {
                        text-align: center;
                        border-top: 1px solid #e2e8f0;
                        padding-top: 20px;
                    }

                    .plugincy-filter-welcome-container .cta-buttons {
                        display: flex;
                        gap: 15px;
                        justify-content: center;
                        flex-wrap: wrap;
                        margin-bottom: 20px;
                    }

                    .plugincy-filter-welcome-container .btn {
                        display: inline-flex;
                        align-items: center;
                        gap: 8px;
                        padding: 12px 24px;
                        border-radius: 6px;
                        text-decoration: none;
                        font-weight: 600;
                        font-size: 14px;
                        transition: all 0.3s ease;
                        cursor: pointer;
                        border: none;
                        box-sizing: border-box;
                    }

                    .plugincy-filter-welcome-container .btn-primary {
                        background: #667eea;
                        color: white;
                    }

                    .plugincy-filter-welcome-container .btn-primary:hover {
                        background: #5a67d8;
                        transform: translateY(-1px);
                        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
                    }

                    .plugincy-filter-welcome-container .btn-secondary {
                        background: #48bb78;
                        color: white;
                    }

                    .plugincy-filter-welcome-container .btn-secondary:hover {
                        background: #38a169;
                        transform: translateY(-1px);
                        box-shadow: 0 4px 12px rgba(72, 187, 120, 0.4);
                    }

                    .plugincy-filter-welcome-container .btn-accent {
                        background: #ed8936;
                        color: white;
                    }

                    .plugincy-filter-welcome-container .btn-accent:hover {
                        background: #dd6b20;
                        transform: translateY(-1px);
                        box-shadow: 0 4px 12px rgba(237, 137, 54, 0.4);
                    }

                    .plugincy-filter-welcome-container .btn .dashicons {
                        font-size: 16px;
                        width: 16px;
                        height: 16px;
                    }

                    .plugincy-filter-welcome-container .support-info {
                        display: flex;
                        justify-content: center;
                        gap: 30px;
                        flex-wrap: wrap;
                    }

                    .plugincy-filter-welcome-container .support-item {
                        display: flex;
                        align-items: center;
                        gap: 8px;
                        color: #718096;
                        font-size: 14px;
                    }

                    .plugincy-filter-welcome-container .support-item .dashicons {
                        color: #48bb78;
                        font-size: 16px;
                        width: 16px;
                        height: 16px;
                    }

                    @media (max-width: 768px) {
                        .plugincy-filter-welcome-container .welcome-header {
                            flex-direction: column;
                            text-align: center;
                            padding: 20px;
                        }

                        .plugincy-filter-welcome-container .plugincy-plugin-icon {
                            margin-right: 0;
                            margin-bottom: 15px;
                        }

                        .plugincy-filter-welcome-container .header-content div {
                            font-size: 24px;
                        }

                        .plugincy-filter-welcome-container .welcome-content {
                            padding: 20px;
                        }

                        .plugincy-filter-welcome-container .action-steps {
                            flex-direction: column;
                        }

                        .plugincy-filter-welcome-container .cta-buttons {
                            flex-direction: column;
                            align-items: center;
                        }

                        .plugincy-filter-welcome-container .btn {
                            width: 100%;
                            max-width: 300px;
                            justify-content: center;
                        }

                        .plugincy-filter-welcome-container .support-info {
                            flex-direction: column;
                            align-items: center;
                            gap: 15px;
                        }

                        .version-badge {
                            margin-top: 15px;
                        }
                    }
                </style>
                <div class="lwp-settings-main-container">
                    <h1 class="wrap lwp-settings-heading">

                        <div class="lwp-settings-icon">

                            <svg class="svg-inline--fa fa-gear" aria-hidden="true" data-prefix="fas" data-icon="gear" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="16" height="16">
                                <path fill="currentColor" d="M15.497 5.206c.1.272.016.575-.2.769l-1.353 1.231a6 6 0 0 1 0 1.588l1.353 1.231c.216.194.3.497.2.769a8 8 0 0 1-.494 1.072l-.147.253a8 8 0 0 1-.691.975.71.71 0 0 1-.766.212l-1.741-.553a6 6 0 0 1-1.375.794l-.391 1.784a.71.71 0 0 1-.569.556 8 8 0 0 1-2.656 0 .71.71 0 0 1-.569-.556l-.391-1.784a6 6 0 0 1-1.375-.794l-1.738.556a.72.72 0 0 1-.766-.212 8 8 0 0 1-.691-.975l-.147-.253a8 8 0 0 1-.494-1.072.71.71 0 0 1 .2-.769l1.353-1.231Q1.997 8.403 1.996 8c-.001-.403.019-.534.053-.794L.696 5.975a.71.71 0 0 1-.2-.769A8 8 0 0 1 .99 4.134l.147-.253q.31-.516.691-.975a.71.71 0 0 1 .766-.212l1.741.553a6 6 0 0 1 1.375-.794L6.101.669A.71.71 0 0 1 6.67.113Q7.32 0 8 0c.68 0 .897.037 1.328.109a.71.71 0 0 1 .569.556l.391 1.784c.494.203.956.472 1.375.794l1.741-.553a.72.72 0 0 1 .766.212q.38.459.691.975l.147.253q.287.515.494 1.072zM8 10.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 1 0 0 5" />
                            </svg>
                        </div>
                        <div>
                            <span><?php echo esc_html(get_admin_page_title()); ?></span>
                        </div>

                    </h1>

                    <div class="lwp-settings-inner-container">

                        <div class="lwp-settings-left">
                            <div class="nav-tab-wrapper lwp-nav-tabs">
                                <?php
                                echo $this->mls_nav_tabs("#lwp-display-settings", "nav-tab-active", '<svg class="svg-inline--fa fa-sliders" aria-hidden="true" data-prefix="fas" data-icon="sliders" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="16" height="16"><path fill="#2563eb" d="M0 13a1 1 0 0 0 1 1h1.709c.384.884 1.266 1.5 2.291 1.5s1.906-.616 2.291-1.5H15a1 1 0 1 0 0-2H7.291c-.384-.884-1.266-1.5-2.291-1.5s-1.906.616-2.291 1.5H1a1 1 0 0 0-1 1m4 0a1 1 0 1 1 2 0 1 1 0 1 1-2 0m6-5a1 1 0 1 1 2 0 1 1 0 1 1-2 0m1-2.5A2.5 2.5 0 0 0 8.709 7H1a1 1 0 1 0 0 2h7.709c.384.884 1.266 1.5 2.291 1.5s1.906-.616 2.291-1.5H15a1 1 0 1 0 0-2h-1.709A2.5 2.5 0 0 0 11 5.5M6 4a1 1 0 1 1 0-2 1 1 0 1 1 0 2m2.291-2C7.906 1.116 7.025.5 6 .5S4.094 1.116 3.709 2H1a1 1 0 1 0 0 2h2.709C4.093 4.884 4.975 5.5 6 5.5S7.906 4.884 8.291 4H15a1 1 0 1 0 0-2z"/></svg>', esc_html__('General', 'multi-location-product-and-inventory-management-pro'));
                                echo $this->mls_nav_tabs("#inventory-settings", "nav-tab", '<svg class="svg-inline--fa fa-chart-bar" aria-hidden="true" data-prefix="fas" data-icon="chart-bar" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="16" height="16"><path fill="#22c55e" d="M1 1a1 1 0 0 1 1 1v10.5c0 .275.225.5.5.5H15a1 1 0 1 1 0 2H2.5A2.5 2.5 0 0 1 0 12.5V2a1 1 0 0 1 1-1m3 3a1 1 0 0 1 1-1h6a1 1 0 1 1 0 2H5a1 1 0 0 1-1-1m1 2h4a1 1 0 1 1 0 2H5a1 1 0 1 1 0-2m0 3h8a1 1 0 1 1 0 2H5a1 1 0 1 1 0-2"/></svg>', esc_html__('Inventory', 'multi-location-product-and-inventory-management-pro'));
                                echo $this->mls_nav_tabs("#product-visibility-settings", "nav-tab", '<svg class="svg-inline--fa fa-eye" aria-hidden="true" data-prefix="fas" data-icon="eye" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 18 16" width="18" height="16"><path fill="#14b8a6" d="M9 1C6.475 1 4.453 2.15 2.981 3.519 1.519 4.875.541 6.5.078 7.616a1 1 0 0 0 0 .769c.463 1.115 1.441 2.74 2.903 4.096C4.453 13.85 6.475 15 9 15s4.547-1.15 6.019-2.519c1.462-1.359 2.441-2.981 2.906-4.097a1 1 0 0 0 0-.769c-.466-1.116-1.444-2.741-2.906-4.097C13.547 2.15 11.525 1 9 1M4.5 8a4.5 4.5 0 1 1 9 0 4.5 4.5 0 1 1-9 0M9 6a2.002 2.002 0 0 1-2.634 1.897c-.172-.056-.372.05-.366.231a3.002 3.002 0 0 0 3.775 2.769A3.002 3.002 0 0 0 9.128 5c-.181-.006-.287.191-.231.366q.101.3.103.634"/></svg>', esc_html__('Product Visibility', 'multi-location-product-and-inventory-management-pro'));
                                echo $this->mls_nav_tabs("#popup-shortcode-settings", "nav-tab", '<svg class="svg-inline--fa fa-window-restore" aria-hidden="true" data-prefix="fas" data-icon="window-restore" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="16" height="16"><path fill="#a855f7" d="M13.5 2h-7c-.275 0-.5.225-.5.5V3H4v-.5A2.5 2.5 0 0 1 6.5 0h7A2.5 2.5 0 0 1 16 2.5v7a2.5 2.5 0 0 1-2.5 2.5H13v-2h.5c.275 0 .5-.225.5-.5v-7c0-.275-.225-.5-.5-.5M0 6c0-1.103.897-2 2-2h8c1.103 0 2 .897 2 2v8c0 1.103-.897 2-2 2H2c-1.103 0-2-.897-2-2zm2 1a1 1 0 0 0 1 1h6a1 1 0 1 0 0-2H3a1 1 0 0 0-1 1"/></svg>', esc_html__('Popup', 'multi-location-product-and-inventory-management-pro'));
                                echo $this->mls_nav_tabs("#cross-order-settings", "nav-tab", '<svg class="svg-inline--fa fa-truck" aria-hidden="true" data-prefix="fas" data-icon="truck" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 16" width="20" height="16"><path fill="#f97316" d="M1.5 0A1.5 1.5 0 0 0 0 1.5v10A1.5 1.5 0 0 0 1.5 13H2a3.001 3.001 0 0 0 6 0h4a3.001 3.001 0 0 0 6 0h1a1 1 0 1 0 0-2V7.416c0-.531-.209-1.041-.584-1.416L16 3.584A2 2 0 0 0 14.584 3H13V1.5A1.5 1.5 0 0 0 11.5 0zM13 5h1.584L17 7.416V8h-4zm-9.5 8a1.5 1.5 0 1 1 3 0 1.5 1.5 0 1 1-3 0M15 11.5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 1 1 0-3"/></svg>', esc_html__('Order & Cart', 'multi-location-product-and-inventory-management-pro'));
                                echo $this->mls_nav_tabs("#notification-settings", "nav-tab", '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" style="margin-right:6px;vertical-align:middle"><path fill="#f97316" d="M12 2a5 5 0 0 0-5 5v5.586L5.293 14.293a1 1 0 0 0 1.414 1.414L9 13.414V12a3 3 0 1 1 6 0v1.414l2.293 2.293a1 1 0 0 0 1.414-1.414L17 12.586V7a5 5 0 0 0-5-5zm0 18a3 3 0 0 0 3-3H9a3 3 0 0 0 3 3z"/></svg>', esc_html__('Notifications', 'multi-location-product-and-inventory-management-pro'));
                                echo $this->mls_nav_tabs("#location-wise-everything", "nav-tab", '<svg class="svg-inline--fa fa-map-location-dot" aria-hidden="true" data-prefix="fas" data-icon="map-location-dot" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 18 16" width="18" height="16"><path fill="#ef4444" d="M12.75 3.75c0 1.706-2.284 4.747-3.288 6-.241.3-.688.3-.925 0-1.003-1.253-3.287-4.294-3.287-6C5.25 1.678 6.928 0 9 0s3.75 1.678 3.75 3.75M13 6.263q.163-.324.3-.644l.047-.116 3.625-1.45A.75.75 0 0 1 18 4.75v8.463a.755.755 0 0 1-.472.697L13 15.719zM4.3 4.322c.075.441.225.884.4 1.297q.136.319.3.644v7.856l-3.972 1.59A.75.75 0 0 1 0 15.012V6.55c0-.306.188-.581.472-.697l3.831-1.531zm5.944 6.053A33 33 0 0 0 12 7.969v7.791l-6-1.716V7.969a33 33 0 0 0 1.756 2.406c.641.8 1.847.8 2.487 0M9 4.75a1.25 1.25 0 1 0 0-2.5 1.25 1.25 0 1 0 0 2.5"/></svg>', esc_html__('Location Wise Everything', 'multi-location-product-and-inventory-management-pro'));
                                echo $this->mls_nav_tabs("#customer-experience", "nav-tab", '<svg class="svg-inline--fa fa-users" aria-hidden="true" data-prefix="fas" data-icon="users" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 16" width="20" height="16"><path fill="#06b6d4" d="M4.5 0a2.5 2.5 0 1 1 0 5 2.5 2.5 0 1 1 0-5M16 0a2.5 2.5 0 1 1 0 5 2.5 2.5 0 1 1 0-5M0 9.334A3.336 3.336 0 0 1 3.334 6h1.334c.497 0 .969.109 1.394.303A4 4 0 0 0 7.356 10H.666A.67.67 0 0 1 0 9.334M12.666 10h-.022a4 4 0 0 0 1.353-3q-.002-.355-.059-.697A3.3 3.3 0 0 1 15.332 6h1.334A3.335 3.335 0 0 1 20 9.334c0 .369-.3.666-.666.666zM7 7a3 3 0 1 1 6 0 3 3 0 1 1-6 0m-3 8.166C4 12.866 5.866 11 8.166 11h3.669c2.3 0 4.166 1.866 4.166 4.166a.834.834 0 0 1-.834.834H4.834A.834.834 0 0 1 4 15.166"/></svg>', esc_html__('User Experience', 'multi-location-product-and-inventory-management-pro'));
                                echo $this->mls_nav_tabs("#extensions", "nav-tab", '<svg width="16" height="16" viewBox="0 0 0.48 0.48" xmlns="http://www.w3.org/2000/svg"><path fill="#f59e0b" d="M.04.418V.329h.044A.044.044 0 0 0 .128.277.046.046 0 0 0 .082.24H.04V.151A.02.02 0 0 1 .062.129h.089V.084A.044.044 0 0 1 .203.04.046.046 0 0 1 .24.087v.042h.089a.02.02 0 0 1 .022.022V.24h.042a.046.046 0 0 1 .046.037.044.044 0 0 1-.044.052H.351v.089A.02.02 0 0 1 .329.44H.262V.396A.044.044 0 0 0 .21.352a.046.046 0 0 0-.037.046V.44H.062A.02.02 0 0 1 .04.418"/></svg>', esc_html__('Location Info Management', 'multi-location-product-and-inventory-management-pro'));
                                echo $this->mls_nav_tabs("#text-management-settings", "nav-tab", '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" style="margin-right:6px;vertical-align:middle"><path fill="#0ea5e9" d="M4 4h16v2H4V4zm0 5h16v2H4V9zm0 5h10v2H4v-2z"/></svg>', esc_html__('Text Management', 'multi-location-product-and-inventory-management-pro'));
                                echo $this->mls_nav_tabs("#advance-settings", "nav-tab", '<svg class="svg-inline--fa fa-gear" aria-hidden="true" data-prefix="fas" data-icon="gear" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="16" height="16"><path fill="#6366f1" d="M15.497 5.206c.1.272.016.575-.2.769l-1.353 1.231a6 6 0 0 1 0 1.588l1.353 1.231c.216.194.3.497.2.769a8 8 0 0 1-.494 1.072l-.147.253a8 8 0 0 1-.691.975.71.71 0 0 1-.766.212l-1.741-.553a6 6 0 0 1-1.375.794l-.391 1.784a.71.71 0 0 1-.569.556 8 8 0 0 1-2.656 0 .71.71 0 0 1-.569-.556l-.391-1.784a6 6 0 0 1-1.375-.794l-1.738.556a.72.72 0 0 1-.766-.212 8 8 0 0 1-.691-.975l-.147-.253a8 8 0 0 1-.494-1.072.71.71 0 0 1 .2-.769l1.353-1.231Q1.997 8.403 1.996 8c-.001-.403.019-.534.053-.794L.696 5.975a.71.71 0 0 1-.2-.769A8 8 0 0 1 .99 4.134l.147-.253q.31-.516.691-.975a.71.71 0 0 1 .766-.212l1.741.553a6 6 0 0 1 1.375-.794L6.101.669A.71.71 0 0 1 6.67.113Q7.32 0 8 0c.68 0 .897.037 1.328.109a.71.71 0 0 1 .569.556l.391 1.784c.494.203.956.472 1.375.794l1.741-.553a.72.72 0 0 1 .766.212q.38.459.691.975l.147.253q.287.515.494 1.072zM8 10.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 1 0 0 5"/></svg>', esc_html__('Advanced', 'multi-location-product-and-inventory-management-pro'));
                                echo $this->mls_nav_tabs("#license-settings", "nav-tab", '<svg width="16" height="16" viewBox="-0.026 0 0.943 0.943" xmlns="http://www.w3.org/2000/svg"><path data-name="19" d="M.528.447.571.404.505.338.462.381.393.312A.158.158 0 1 0 .23.062a.126.126 0 1 0-.175.18.158.158 0 1 0 .257.157l.066.066-.037.036.066.066L.444.53l.175.175-.096.096.033.033a.049.049 0 1 1 .068.068l.04.04L.76.846l.047.047.084-.084ZM.355.081a.077.077 0 1 1-.077.077.077.077 0 0 1 .077-.077M.309.309.308.31.307.308ZM.132.081A.062.062 0 1 1 .07.143.06.06 0 0 1 .132.081m.026.357A.077.077 0 1 1 .235.361a.077.077 0 0 1-.077.077" fill="#59bdff"/></svg>', esc_html__('Plugin License', 'multi-location-product-and-inventory-management-pro'));
                                ?>
                            </div>

                            <form method="post" action="options.php" class="mulopimfwc_settings">
                                <?php settings_fields('mulopimfwc_settings'); ?>

                                <div id="lwp-display-settings" class="lwp-tab-content">
                                    <div class="lwp-settings-section">
                                        <div class="lwp-settings-box">
                                            <div class="lwp-filter-settings lwp-location-show-title">
                                                <?php do_settings_sections('multi-location-product-and-inventory-management-pro'); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="lwp-settings-section">
                                        <div class="lwp-settings-box">
                                            <style>
                                                .mulopimfwc-color-picker {
                                                    width: 80px;
                                                    height: 40px;
                                                    border: 1px solid #ddd;
                                                    border-radius: 4px;
                                                    cursor: pointer;
                                                    padding: 2px;
                                                }

                                                .mulopimfwc-branding-preview {
                                                    background: #f9fafb;
                                                    border: 1px solid #e5e7eb;
                                                    border-radius: 8px;
                                                    padding: 20px;
                                                    margin-top: 20px;
                                                }

                                                .mulopimfwc-branding-preview h3 {
                                                    margin-top: 0;
                                                    color: #1f2937;
                                                }

                                                .mulopimfwc-preview-box {
                                                    background: #fff;
                                                    border-radius: 12px;
                                                    padding: 20px;
                                                    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                                                    margin-top: 15px;
                                                }

                                                .mulopimfwc-preview-button {
                                                    display: inline-block;
                                                    padding: 10px 20px;
                                                    border-radius: 6px;
                                                    margin-top: 10px;
                                                    cursor: default;
                                                }
                                            </style>
                                            <?php
                                            $options = $this->get_display_options();
                                            ?>
                                            <div class="mulopimfwc-branding-preview">
                                                <h3><?php echo esc_html__('Branding Preview', 'multi-location-product-and-inventory-management-pro'); ?></h3>
                                                <p class="description"><?php echo esc_html__('Your branding settings will be applied to all popups, buttons, and frontend elements. Changes take effect immediately after saving.', 'multi-location-product-and-inventory-management-pro'); ?></p>
                                                <div class="mulopimfwc-preview-box">
                                                    <p><strong><?php echo esc_html__('Sample Button:', 'multi-location-product-and-inventory-management-pro'); ?></strong></p>
                                                    <button type="button" class="mulopimfwc-preview-button" style="background: <?php echo esc_attr(isset($options['branding_primary_color']) ? $options['branding_primary_color'] : '#2a9d8f'); ?>; color: <?php echo esc_attr(isset($options['branding_button_text']) ? $options['branding_button_text'] : '#ffffff'); ?>; border: none; padding: 10px 20px; border-radius: <?php echo esc_attr(isset($options['branding_border_radius']) ? $options['branding_border_radius'] : '20px'); ?>;">
                                                        <?php echo esc_html__('Sample Button', 'multi-location-product-and-inventory-management-pro'); ?>
                                                    </button>
                                                </div>
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
                                <div id="text-management-settings" class="lwp-tab-content" style="display:none;">
                                    <?php $this->render_text_management_tab(); ?>
                                </div>
                                <div id="product-visibility-settings" class="lwp-tab-content" style="display:none;">
                                    <div class="lwp-settings-section">
                                        <div class="lwp-settings-box">
                                            <?php do_settings_sections('location-product-visibility-settings'); ?>
                                        </div>
                                    </div>
                                    <div class="lwp-settings-section">
                                        <div class="lwp-settings-box">
                                            <?php do_settings_sections('lwp-outstock-product-settings'); ?>
                                        </div>
                                    </div>
                                    <div class="lwp-settings-section">
                                        <div class="lwp-settings-box">
                                            <?php do_settings_sections('lwp-admin-product-visibility-settings'); ?>
                                        </div>
                                    </div>
                                    <div class="lwp-settings-section">
                                        <div class="lwp-settings-box">
                                            <?php do_settings_sections('lwp-product-filtering-settings'); ?>
                                        </div>
                                    </div>
                                </div>
                                <div id="cross-order-settings" class="lwp-tab-content" style="display:none;">
                                    <div class="lwp-settings-section">
                                        <div class="lwp-settings-box">
                                            <?php do_settings_sections('lwp-order-fullfill-settings'); ?>
                                        </div>
                                    </div>
                                    <div class="lwp-settings-section">
                                        <div class="lwp-settings-box">
                                            <?php do_settings_sections('location-cross-order-settings'); ?>
                                        </div>
                                    </div>
                                    <div class="lwp-settings-section">
                                        <div class="lwp-settings-box">
                                            <?php do_settings_sections('lwp-cart-settings'); ?>
                                        </div>
                                    </div>
                                    <div class="lwp-settings-section">
                                        <div class="lwp-settings-box">
                                            <?php do_settings_sections('lwp-checkout-settings'); ?>
                                        </div>
                                    </div>
                                </div>
                                <div id="inventory-settings" class="lwp-tab-content" style="display:none;">
                                    <div class="lwp-settings-section">
                                        <div class="lwp-settings-box">
                                            <?php do_settings_sections('location-inventory-settings'); ?>
                                        </div>
                                    </div>
                                    <div class="lwp-settings-section">
                                        <div class="lwp-settings-box">
                                            <div class="lwp-filter-settings lwp-location-show-title">
                                                <?php do_settings_sections('lwp-general-settings'); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div id="location-wise-everything" class="lwp-tab-content" style="display:none;">
                                    <div class="lwp-settings-section">
                                        <div class="lwp-settings-box">
                                            <div class="lwp-subtab-wrapper" style="display: flex; gap: 1rem; ">
                                                <a href="#lwp-subtab-shipping" class="lwp-subtab lwp-subtab-active"><svg viewBox="0 0 14 14" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="20" height="20" style="margin-right:6px;vertical-align:middle">
                                                        <path d="M13 7.118v-.007a.275.275 0 0 0-.277-.27h-.072l-1.12-2.166a.28.28 0 0 0-.246-.148H9.373l.073-.579a.8.8 0 0 0-.202-.614.8.8 0 0 0-.59-.256H2.716a.293.293 0 0 0-.29.264l-.06.477H5.75c.22 0 .393.178.387.398a.41.41 0 0 1-.408.397H4.6v.002H1.29a.29.29 0 0 0-.29.281.27.27 0 0 0 .274.281h4.385a.41.41 0 0 1 .35.421.44.44 0 0 1-.433.426H2.552a.293.293 0 0 0-.291.284.275.275 0 0 0 .277.284h2.987a.41.41 0 0 1 .357.421.44.44 0 0 1-.438.426H1.73a.293.293 0 0 0-.292.285.275.275 0 0 0 .277.284h.248l-.097 1.017c-.02.231.05.45.201.615a.8.8 0 0 0 .591.255h.215a1.24 1.24 0 0 0 1.226 1.026c.618 0 1.147-.442 1.28-1.026h2.675a.9.9 0 0 0 .582-.218.8.8 0 0 0 .555.218h.044a1.24 1.24 0 0 0 1.226 1.026c.618 0 1.147-.442 1.28-1.026h.176c.46 0 .868-.374.91-.834L13 7.145v-.02zm-8.887 3.236a.69.69 0 0 1-.692-.71.734.734 0 0 1 .729-.71c.391 0 .702.318.691.71a.734.734 0 0 1-.728.71m6.362 0a.69.69 0 0 1-.692-.71.734.734 0 0 1 .729-.71c.391 0 .701.318.691.71a.734.734 0 0 1-.728.71m1.785-1.328a.34.34 0 0 1-.33.302h-.19a1.24 1.24 0 0 0-1.213-.962c-.596 0-1.109.41-1.264.962h-.059a.24.24 0 0 1-.181-.076.24.24 0 0 1-.06-.19l.358-3.967h.823l-.13 1.444c-.022.231.05.45.2.615s.36.255.592.255h1.6z"></path>
                                                    </svg><?php echo esc_html_e('Shipping', 'multi-location-product-and-inventory-management-pro'); ?></a>
                                                <a href="#lwp-subtab-payments" class="lwp-subtab"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" style="margin-right:6px;vertical-align:middle">
                                                        <path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2m0 14H4v-6h16zm0-10H4V6h16z"></path>
                                                    </svg><?php echo esc_html_e('Payments', 'multi-location-product-and-inventory-management-pro'); ?></a>
                                                <a href="#lwp-subtab-tax" class="lwp-subtab"><svg viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg" width="16" height="16" style="margin-right:6px;vertical-align:middle">
                                                        <path d="M441.71 414.154c0-23.138-17.983-41.656-39.864-41.656-21.875 0-39.864 18.522-39.864 41.656s17.989 41.656 39.864 41.656c21.881 0 39.864-18.518 39.864-41.656m40.96 0c0 45.495-36.048 82.616-80.824 82.616-44.769 0-80.824-37.124-80.824-82.616s36.055-82.616 80.824-82.616c44.776 0 80.824 37.121 80.824 82.616m176.274 192.62c0-23.138-17.983-41.656-39.864-41.656-21.875 0-39.864 18.522-39.864 41.656s17.989 41.656 39.864 41.656c21.881 0 39.864-18.518 39.864-41.656m40.96 0c0 45.495-36.048 82.616-80.824 82.616-44.769 0-80.824-37.124-80.824-82.616s36.055-82.616 80.824-82.616c44.776 0 80.824 37.121 80.824 82.616m-95.515-225.529L363.022 629.79c-7.88 8.114-7.69 21.08.424 28.96s21.08 7.69 28.96-.424l241.367-248.545c7.88-8.114 7.69-21.08-.424-28.96s-21.08-7.69-28.96.424"></path>
                                                        <path d="M829.44 911.36c45.245 0 81.92-36.675 81.92-81.92V194.56c0-45.245-36.675-81.92-81.92-81.92H194.56c-45.245 0-81.92 36.675-81.92 81.92v634.88c0 45.245 36.675 81.92 81.92 81.92zm0 40.96H194.56c-67.866 0-122.88-55.014-122.88-122.88V194.56c0-67.866 55.014-122.88 122.88-122.88h634.88c67.866 0 122.88 55.014 122.88 122.88v634.88c0 67.866-55.014 122.88-122.88 122.88"></path>
                                                    </svg><?php echo esc_html_e('Tax', 'multi-location-product-and-inventory-management-pro'); ?></a>
                                                <a href="#lwp-subtab-discounts" class="lwp-subtab"><svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" width="16" height="16" style="margin-right:6px;vertical-align:middle">
                                                        <path fill-rule="evenodd" d="M11.566.66a2.19 2.19 0 0 0-3.132 0l-.962.985a2.2 2.2 0 0 1-1.592.66l-1.377-.017a2.19 2.19 0 0 0-2.215 2.215l.016 1.377a2.2 2.2 0 0 1-.66 1.592l-.984.962a2.19 2.19 0 0 0 0 3.132l.985.962c.428.418.667.994.66 1.592l-.017 1.377a2.19 2.19 0 0 0 2.215 2.215l1.377-.016a2.2 2.2 0 0 1 1.592.66l.962.984c.859.88 2.273.88 3.132 0l.962-.985a2.2 2.2 0 0 1 1.592-.66l1.377.017a2.19 2.19 0 0 0 2.215-2.215l-.016-1.377a2.2 2.2 0 0 1 .66-1.592l.984-.962c.88-.859.88-2.273 0-3.132l-.985-.962a2.2 2.2 0 0 1-.66-1.592l.017-1.377a2.19 2.19 0 0 0-2.215-2.215l-1.377.016a2.2 2.2 0 0 1-1.592-.66zM7 8.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3m6 6a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3m.778-8.278a1.1 1.1 0 0 1 0 1.556l-6 6a1.1 1.1 0 1 1-1.556-1.556l6-6a1.1 1.1 0 0 1 1.556 0"></path>
                                                    </svg><?php echo esc_html_e('Discounts', 'multi-location-product-and-inventory-management-pro'); ?></a>
                                                <a href="#lwp-subtab-reviews" class="lwp-subtab"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 330 330" xml:space="preserve" width="16" height="16" style="margin-right:6px;vertical-align:middle">
                                                        <path d="M165 0C74.019 0 0 74.019 0 165s74.019 165 165 165 165-74.019 165-165S255.981 0 165 0m0 300c-74.439 0-135-60.561-135-135S90.561 30 165 30s135 60.561 135 135-60.561 135-135 135"></path>
                                                        <path d="m247.157 128.196-47.476-6.9-21.23-43.019a15 15 0 0 0-26.902 0l-21.23 43.019-47.476 6.9a14.998 14.998 0 0 0-8.312 25.585l34.353 33.486-8.109 47.282a15 15 0 0 0 21.765 15.813L165 228.039l42.462 22.323a15 15 0 0 0 6.979 1.723h.05c8.271-.015 14.972-6.725 14.972-15 0-1.152-.13-2.274-.375-3.352l-7.97-46.466 34.352-33.486a15 15 0 0 0-8.313-25.585"></path>
                                                    </svg><?php echo esc_html_e('Reviews', 'multi-location-product-and-inventory-management-pro'); ?></a>
                                                <a href="#lwp-subtab-seo" class="lwp-subtab"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="16" height="16" style="margin-right:6px;vertical-align:middle">
                                                        <path d="M2.293 18.707a1 1 0 0 1 0-1.414l3-3a1 1 0 0 1 1.262-.125l2.318 1.545 2.42-2.42a1 1 0 0 1 1.414 1.414l-3 3a1 1 0 0 1-1.262.125l-2.318-1.545-2.42 2.42a1 1 0 0 1-1.414 0M22 3v18a1 1 0 0 1-1 1H3a1 1 0 0 1 0-2h17V8H4v4a1 1 0 0 1-2 0V3a1 1 0 0 1 1-1h18a1 1 0 0 1 1 1M4 6h16V4H4Zm10.707 6.707 1-1a1 1 0 0 0-1.414-1.414l-1 1a1 1 0 1 0 1.414 1.414"></path>
                                                    </svg><?php echo esc_html_e('SEO', 'multi-location-product-and-inventory-management-pro'); ?></a>
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
                                            <div id="lwp-subtab-others" class="lwp-subtab-content" style="display:none;">
                                                <?php do_settings_sections('lwp-location-others-settings'); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div id="notification-settings" class="lwp-tab-content" style="display:none;">
                                    <div class="lwp-settings-section">
                                        <div class="lwp-settings-box">
                                            <?php do_settings_sections('lwp-admin-notification-settings'); ?>
                                        </div>
                                    </div>
                                    <div class="lwp-settings-section">
                                        <div class="lwp-settings-box">
                                            <?php do_settings_sections('lwp-location-notifications-settings'); ?>
                                        </div>
                                    </div>
                                    <div class="lwp-settings-section">
                                        <div class="lwp-settings-box">
                                            <?php do_settings_sections('lwp-social-notifications-settings'); ?>
                                        </div>
                                    </div>
                                </div>
                                <div id="customer-experience" class="lwp-tab-content" style="display:none;">
                                    <div class="lwp-settings-section">
                                        <div class="lwp-settings-box">
                                            <?php do_settings_sections('location-customer-experience-settings'); ?>
                                        </div>
                                    </div>
                                    <div class="lwp-settings-section">
                                        <div class="lwp-settings-box">
                                            <?php do_settings_sections('lwp-customer-insights-settings'); ?>
                                        </div>
                                    </div>
                                </div>
                                <div id="extensions" class="lwp-tab-content" style="display:none;">
                                    <div class="lwp-settings-section">
                                        <div class="lwp-settings-box">
                                            <?php do_settings_sections('lwp-location-selection-settings'); ?>
                                        </div>
                                    </div>
                                    <div class="lwp-settings-section">
                                        <div class="lwp-settings-box">
                                            <?php do_settings_sections('lwp-product-filter-settings'); ?>
                                        </div>
                                    </div>
                                    <div class="lwp-settings-section">
                                        <div class="lwp-settings-box">
                                            <?php do_settings_sections('lwp-default-location-settings'); ?>
                                        </div>
                                    </div>
                                    <div class="lwp-settings-section">
                                        <div class="lwp-settings-box">
                                            <?php do_settings_sections('lwp-store-locator-settings'); ?>
                                        </div>
                                    </div>
                                    <div class="lwp-settings-section">
                                        <div class="lwp-settings-box">
                                            <?php do_settings_sections('lwp-store-locator-display-settings'); ?>
                                        </div>
                                    </div>
                                    <div class="lwp-settings-section">
                                        <div class="lwp-settings-box">
                                            <?php do_settings_sections('lwp-business-hour-settings'); ?>
                                        </div>
                                    </div>
                                    <div class="lwp-settings-section">
                                        <div class="lwp-settings-box">
                                            <?php do_settings_sections('lwp-url-management-settings'); ?>
                                        </div>
                                    </div>
                                    <div class="lwp-settings-section">
                                        <div class="lwp-settings-box">
                                            <?php do_settings_sections('lwp-location-pickup-settings'); ?>
                                        </div>
                                    </div>
                                    <!-- <div class="lwp-settings-section">
                                    <div class="lwp-settings-box">
                                        <?php //do_settings_sections('lwp-location-allocation-settings'); 
                                        ?>
                                    </div>
                                </div> -->
                                </div>
                                <div id="advance-settings" class="lwp-tab-content" style="display:none;">
                                    <div class="lwp-settings-section">
                                        <div class="lwp-settings-box">
                                            <?php do_settings_sections('location-advance-settings'); ?>
                                        </div>
                                    </div>
                                    <div class="lwp-settings-section">
                                        <div class="lwp-settings-box">
                                            <?php do_settings_sections('lwp-import-export-settings'); ?>
                                        </div>
                                    </div>
                                    <div class="lwp-settings-section">
                                        <div class="lwp-settings-box">
                                            <?php do_settings_sections('lwp-api-webhooks-settings'); ?>
                                        </div>
                                    </div>
                                </div>
                                <?php submit_button(); ?>
                            </form>


                            <div id="license-settings" class="lwp-tab-content" style="display:none;">
                                <?php
                                global $mulopimfwc_License_Manager;
                                $mulopimfwc_License_Manager->render_license_form();
                                ?></div>

                            <!-- Reset Settings Form -->
                            <form method="post" action="" class="lwp-reset-settings-form" style="margin-top: 30px;">
                                <?php wp_nonce_field('mulopimfwc_reset_settings_action', 'mulopimfwc_reset_settings_nonce'); ?>
                                <input type="hidden" name="mulopimfwc_reset_settings" value="1">

                                <div class="lwp-reset-settings-section" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
                                    <h3 style="margin: 0 0 10px 0; color: #dc2626; font-size: 16px; font-weight: 600;">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" style="vertical-align: middle; margin-right: 8px;">
                                            <path fill="#dc2626" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z" />
                                        </svg>
                                        <?php echo esc_html__('Reset Settings', 'multi-location-product-and-inventory-management-pro'); ?>
                                    </h3>
                                    <p class="description" style="margin: 0 0 15px 0; max-width: 800px;">
                                        <?php echo esc_html__('This will reset all plugin settings to their default values. This action cannot be undone. Please make sure you have exported your settings if you want to restore them later.', 'multi-location-product-and-inventory-management-pro'); ?>
                                    </p>
                                    <button type="submit" class="button button-secondary lwp-reset-button" style="background: #dc2626; color: white; border-color: #b91c1c;" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to reset all settings to their default values? This action cannot be undone!', 'multi-location-product-and-inventory-management-pro')); ?>');">
                                        <span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
                                        <?php echo esc_html__('Reset All Settings', 'multi-location-product-and-inventory-management-pro'); ?>
                                    </button>
                                </div>
                            </form>

                            <style>
                                .lwp-reset-settings-form {
                                    margin: 0 20px 20px;
                                }

                                .lwp-reset-button:hover {
                                    background: #b91c1c !important;
                                    border-color: #991b1b !important;
                                    color: white !important;
                                }

                                .lwp-reset-button:focus {
                                    background: #dc2626 !important;
                                    border-color: #b91c1c !important;
                                    box-shadow: 0 0 0 1px #dc2626 !important;
                                }

                                @media (max-width: 600px) {
                                    .lwp-reset-settings-form {
                                        margin: 0;
                                    }
                                }
                            </style>

                        </div>

                        <div class="lwp-settings-right">

                            <div class="lwp-admin-notice">
                                <div class="notice-header"><svg viewBox="0 -2 20 20"
                                        xmlns="http://www.w3.org/2000/svg"
                                        width="20" height="20"
                                        style="margin-right:6px;vertical-align:middle;background-color:#3b82f6;padding:10px;border-radius:6px">
                                        <path fill="#ffffff" d="M12.736.064c.52.2.787.805.598 1.353L8.546 15.305c-.19.548-.763.83-1.282.631-.52-.2-.787-.805-.598-1.353L11.454.695c.19-.548.763-.83 1.282-.631M2.414 8.256 5.95 11.99c.39.412.39 1.08 0 1.492a.963.963 0 0 1-1.414 0L.293 9.003a1.1 1.1 0 0 1 0-1.493l4.243-4.48a.963.963 0 0 1 1.414 0 1.1 1.1 0 0 1 0 1.494zm15.172 0L14.05 4.524a1.1 1.1 0 0 1 0-1.493.963.963 0 0 1 1.414 0l4.243 4.479c.39.412.39 1.08 0 1.493l-4.243 4.478a.963.963 0 0 1-1.414 0 1.1 1.1 0 0 1 0-1.492z" />
                                    </svg>
                                    <h3 class="notice-title" style="font-size: 15px;"><?php echo esc_html_e('Location Selector Shortcode', 'multi-location-product-and-inventory-management-pro'); ?></h3>
                                </div>
                                <div class="notice-content">
                                    <p class="notice-description">
                                        <?php echo esc_html_e('Use this shortcode to display the location selector on any page or post. Copy and paste it into the WordPress editor or use it in your template files.', 'multi-location-product-and-inventory-management-pro'); ?>
                                    </p>

                                    <div class="shortcode-section">
                                        <div class="shortcode-header">
                                            <span class="shortcode-label"><?php echo esc_html_e('Shortcode', 'multi-location-product-and-inventory-management-pro'); ?></span>
                                            <button class="copy-btn" onclick="copyToClipboard(event, '[mulopimfwc_store_location_selector<?php echo esc_attr(mulopimfwc_get_pro_class(false, ' enable_user_locations="on"', '')); ?>]')">
                                                <svg class="copy-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                                </svg>
                                                <span class="btn-text"><?php echo esc_html_e('Copy Code', 'multi-location-product-and-inventory-management-pro'); ?></span>
                                            </button>
                                        </div>
                                        <div class="shortcode-body">
                                            <code id="shortcode-text">[mulopimfwc_store_location_selector<?php echo esc_attr(mulopimfwc_get_pro_class(false, ' enable_user_locations="on"', '')); ?>]</code>
                                        </div>
                                    </div>

                                    <div class="params-section">
                                        <h4 class="params-title">
                                            <svg class="info-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="12" cy="12" r="10"></circle>
                                                <line x1="12" y1="16" x2="12" y2="12"></line>
                                                <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                            </svg>
                                        <?php echo esc_html_e('Available Parameters', 'multi-location-product-and-inventory-management-pro'); ?>
                                        </h4>
                                        <div class="params-grid">
                                            <div class="param-item">
                                                <div class="param-content">
                                                    <div class="param-name">title</div>
                                                    <div class="param-value"><?php echo esc_html_e('Set custom selector title', 'multi-location-product-and-inventory-management-pro'); ?></div>
                                                </div>
                                                <span class="param-info">
                                                    !
                                                    <span class="param-tooltip">[mulopimfwc_store_location_selector title="Select Your Location"]</span>
                                                </span>
                                            </div>
                                            <div class="param-item">
                                                <div class="param-content">
                                                    <div class="param-name">show_title</div>
                                                    <div class="param-value">on / off <?php echo esc_html_e('- Display title', 'multi-location-product-and-inventory-management-pro'); ?></div>
                                                </div>
                                                <span class="param-info">
                                                    !
                                                    <span class="param-tooltip">[mulopimfwc_store_location_selector show_title="on"]</span>
                                                </span>
                                            </div>
                                            <div class="param-item">
                                                <div class="param-content">
                                                    <div class="param-name">use_select2</div>
                                                    <div class="param-value">on / off <?php echo esc_html_e('- Enhanced dropdown', 'multi-location-product-and-inventory-management-pro'); ?></div>
                                                </div>
                                                <span class="param-info">
                                                    !
                                                    <span class="param-tooltip">[mulopimfwc_store_location_selector use_select2="on"]</span>
                                                </span>
                                            </div>
                                            <div class="param-item">
                                                <div class="param-content">
                                                    <div class="param-name">herichical</div>
                                                    <div class="param-value">on / off / seperately</div>
                                                </div>
                                                <span class="param-info">
                                                    !
                                                    <span class="param-tooltip">[mulopimfwc_store_location_selector herichical="on"]</span>
                                                </span>
                                            </div>
                                            <div class="param-item">
                                                <div class="param-content">
                                                    <div class="param-name">show_count</div>
                                                    <div class="param-value">on / off <?php echo esc_html_e('- Show item count', 'multi-location-product-and-inventory-management-pro'); ?></div>
                                                </div>
                                                <span class="param-info">
                                                    !
                                                    <span class="param-tooltip">[mulopimfwc_store_location_selector show_count="on"]</span>
                                                </span>
                                            </div>
                                            <div class="param-item">
                                                <div class="param-content">
                                                    <div class="param-name">class</div>
                                                    <div class="param-value"><?php echo esc_html_e('Custom CSS class name', 'multi-location-product-and-inventory-management-pro'); ?></div>
                                                </div>
                                                <span class="param-info">
                                                    !
                                                    <span class="param-tooltip">[mulopimfwc_store_location_selector class="my-custom-class"]</span>
                                                </span>
                                            </div>
                                            <div class="param-item">
                                                <div class="param-content">
                                                    <div class="param-name">enable_user_locations
                                                        <?php echo mulopimfwc_get_pro_class(false, '', '<span style="background: #ff5a36;color: #fff;padding: 3px 6px;border-radius: 4px;">Paid</span>'); ?>
                                                    </div>
                                                    <div class="param-value">on / off <?php echo esc_html_e('- User locations', 'multi-location-product-and-inventory-management-pro'); ?></div>
                                                </div>
                                                <span class="param-info">
                                                    !
                                                    <span class="param-tooltip">[mulopimfwc_store_location_selector enable_user_locations="off"]</span>
                                                </span>
                                            </div>
                                            <div class="param-item">
                                                <div class="param-content">
                                                    <div class="param-name">max_width</div>
                                                    <div class="param-value"><?php echo esc_html_e('Maximum width in pixels', 'multi-location-product-and-inventory-management-pro'); ?></div>
                                                </div>
                                                <span class="param-info">
                                                    !
                                                    <span class="param-tooltip">[mulopimfwc_store_location_selector max_width="300"]</span>
                                                </span>
                                            </div>
                                            <div class="param-item">
                                                <div class="param-content">
                                                    <div class="param-name">multi_line</div>
                                                    <div class="param-value">on / off <?php echo esc_html_e('- Multi-line display', 'multi-location-product-and-inventory-management-pro'); ?></div>
                                                </div>
                                                <span class="param-info">
                                                    !
                                                    <span class="param-tooltip">[mulopimfwc_store_location_selector multi_line="on"]</span>
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="usage-hint">
                                        <p>
                                            <svg class="hint-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                                                <path d="M2 17l10 5 10-5M2 12l10 5 10-5"></path>
                                            </svg>
                                            <span><strong><?php echo esc_html_e('Pro Tip:', 'multi-location-product-and-inventory-management-pro'); ?></strong> <?php echo esc_html_e('You can modify the parameter values directly in the shortcode to customize the location selector appearance and behavior.', 'multi-location-product-and-inventory-management-pro'); ?></span>
                                        </p>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <script>
                            var copyCopiedLabel = <?php echo wp_json_encode(__('Copied!', 'multi-location-product-and-inventory-management-pro')); ?>;
                            function copyToClipboard(event, shortcode) {
                                const shortcodeText = shortcode;
                                const button = event.currentTarget;
                                const buttonText = (button && typeof button.querySelector === 'function') ? button.querySelector('.btn-text') : null;
                                const originalText = buttonText ? buttonText.textContent : '';

                                // Fallback function for older browsers
                                function fallbackCopy(text) {
                                    const textArea = document.createElement('textarea');
                                    textArea.value = text;
                                    textArea.style.position = 'fixed';
                                    textArea.style.left = '-999999px';
                                    textArea.style.top = '-999999px';
                                    document.body.appendChild(textArea);
                                    textArea.focus();
                                    textArea.select();

                                    try {
                                        document.execCommand('copy');
                                        textArea.remove();
                                        return true;
                                    } catch (err) {
                                        console.error('Fallback: Could not copy text', err);
                                        textArea.remove();
                                        return false;
                                    }
                                }

                                // Try modern clipboard API first, fallback to execCommand
                                const copyPromise = navigator.clipboard && navigator.clipboard.writeText ?
                                    navigator.clipboard.writeText(shortcodeText) :
                                    Promise.resolve(fallbackCopy(shortcodeText));

                                copyPromise.then(() => {
                                    button.classList.add('copied');
                                    if (originalText) {
                                        button.innerHTML = `
                    <svg class="copy-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span class="btn-text">${copyCopiedLabel}</span>
                `;
                                    } else {
                                        button.innerHTML = `
                    <svg class="copy-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                `;
                                    }

                                    setTimeout(() => {
                                        button.classList.remove('copied');
                                        button.innerHTML = `
                        <svg class="copy-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                        </svg>
                        <span class="btn-text">${originalText}</span>
                    `;
                                    }, 2000);
                                }).catch(err => {
                                    console.error('Failed to copy:', err);
                                    alert('Failed to copy. Please select and copy manually.');
                                });
                            }
                        </script>


                    </div>



                    <!-- <div class="lwp-footer">
                <p><?php //echo esc_html_e('Thank you for using Multi Location Product & Inventory Management for WooCommerce', 'multi-location-product-and-inventory-management-pro'); 
                    ?></p>
            </div> -->
                </div>

                <!-- Tutorial section -->
                <?php
                // Tutorial Section with Professional UI and Relevant Content
                ?>
                <div class="lwp-tutorial-section">
                    <?php

                    $tutorial_videos = [
                        [
                            'number' => 1,
                            'title' => __('Plugin Installation & Setup', 'multi-location-product-and-inventory-management-pro'),
                            'description' => __('Learn how to install the plugin, activate your license, and configure basic settings. This tutorial covers the initial setup process and activation.', 'multi-location-product-and-inventory-management-pro'),
                            'url' => 'https://www.youtube.com/embed/VIDEO_ID_1',
                            'duration' => '4:30',
                            'difficulty' => 'beginner',
                            'topics' => ['Installation', 'License Activation', 'Initial Setup'],
                        ],
                        [
                            'number' => 2,
                            'title' => __('Creating Store Locations', 'multi-location-product-and-inventory-management-pro'),
                            'description' => __('Step-by-step guide on creating and configuring store locations with addresses, contact details, business hours, and geographical coordinates for maps.', 'multi-location-product-and-inventory-management-pro'),
                            'url' => 'https://www.youtube.com/embed/VIDEO_ID_2',
                            'duration' => '6:15',
                            'difficulty' => 'beginner',
                            'shortcode' => '[mulopimfwc_store_location_selector]',
                        ],
                        [
                            'number' => 3,
                            'title' => __('Managing Location-Based Inventory', 'multi-location-product-and-inventory-management-pro'),
                            'description' => __('Master inventory management across multiple locations. Learn to set stock levels, manage backorders, configure low-stock alerts, and sync inventory between locations.', 'multi-location-product-and-inventory-management-pro'),
                            'url' => 'https://www.youtube.com/embed/VIDEO_ID_3',
                            'duration' => '8:45',
                            'difficulty' => 'intermediate',
                            'topics' => ['Stock Management', 'Low Stock Alerts', 'Backorders', 'Inventory Sync'],
                        ],
                        [
                            'number' => 4,
                            'title' => __('Location-Based Pricing Setup', 'multi-location-product-and-inventory-management-pro'),
                            'description' => __('Configure different prices for products at different locations. Set up location-specific discounts, sale prices, and promotional offers to optimize revenue.', 'multi-location-product-and-inventory-management-pro'),
                            'url' => 'https://www.youtube.com/embed/VIDEO_ID_4',
                            'duration' => '5:30',
                            'difficulty' => 'intermediate',
                            'topics' => ['Location Pricing', 'Discounts', 'Sale Prices', 'Promotions'],
                        ],
                        [
                            'number' => 5,
                            'title' => __('Shipping & Pickup Configuration', 'multi-location-product-and-inventory-management-pro'),
                            'description' => __('Set up location-based shipping methods, configure pickup locations, and manage delivery options. Learn to optimize shipping costs and customer convenience.', 'multi-location-product-and-inventory-management-pro'),
                            'url' => 'https://www.youtube.com/embed/VIDEO_ID_5',
                            'duration' => '7:20',
                            'difficulty' => 'intermediate',
                            'topics' => ['Shipping Methods', 'Local Pickup', 'Delivery Options', 'Fulfillment'],
                        ],
                        [
                            'number' => 6,
                            'title' => __('Location Selector Anywhere', 'multi-location-product-and-inventory-management-pro'),
                            'url' => 'https://www.youtube.com/embed/VIDEO_ID_6',
                            'duration' => '6:50',
                            'difficulty' => 'intermediate',
                            'shortcode' => '[mulopimfwc_store_location_selector enable_user_locations="on"]',
                            'topics' => ['Location Selector', 'Popup Modal', 'Shortcodes', 'Customization'],
                        ],
                        [
                            'number' => 7,
                            'title' => __('Location Manager Roles & Permissions', 'multi-location-product-and-inventory-management-pro'),
                            'description' => __('Configure location manager roles, assign staff to specific locations, manage permissions, and set up multi-user workflows for decentralized inventory control.', 'multi-location-product-and-inventory-management-pro'),
                            'url' => 'https://www.youtube.com/embed/VIDEO_ID_7',
                            'duration' => '5:45',
                            'difficulty' => 'advanced',
                            'topics' => ['User Roles', 'Permissions', 'Staff Management', 'Access Control'],
                        ],
                        [
                            'number' => 8,
                            'title' => __('Reports, Analytics & Order Management', 'multi-location-product-and-inventory-management-pro'),
                            'description' => __('Generate location-based reports, analyze sales data, track inventory movements, manage orders by location, and optimize your multi-location operations.', 'multi-location-product-and-inventory-management-pro'),
                            'url' => 'https://www.youtube.com/embed/VIDEO_ID_9',
                            'duration' => '9:15',
                            'difficulty' => 'advanced',
                            'topics' => ['Reports', 'Analytics', 'Order Management', 'Performance Tracking'],
                        ],
                        [
                            'number' => 9,
                            'title' => __('Location Information Display & Maps', 'multi-location-product-and-inventory-management-pro'),
                            'url' => 'https://www.youtube.com/embed/VIDEO_ID_8',
                            'duration' => '7:45',
                            'difficulty' => 'advanced',
                            'shortcode' => '[mulopimfwc_location_info layout="tabs" search="yes"]',
                            'parameters' => [
                                [
                                    'key' => 'id',
                                    'desc' => __('Specific location ID(s)', 'multi-location-product-and-inventory-management-pro'),
                                    'value' => 'id="123" or id="123,456,789"'
                                ],
                                [
                                    'key' => 'slug',
                                    'desc' => __('Location slug(s)', 'multi-location-product-and-inventory-management-pro'),
                                    'value' => 'slug="downtown,uptown"'
                                ],
                                [
                                    'key' => 'layout',
                                    'desc' => __('Display layout style', 'multi-location-product-and-inventory-management-pro'),
                                    'value' => 'auto | tabs | compact | grid'
                                ],
                                [
                                    'key' => 'search',
                                    'desc' => __('Enable location search', 'multi-location-product-and-inventory-management-pro'),
                                    'value' => 'yes | no'
                                ],
                                [
                                    'key' => 'compact',
                                    'desc' => __('Compact view for single location', 'multi-location-product-and-inventory-management-pro'),
                                    'value' => 'yes | no'
                                ],
                                [
                                    'key' => 'limit',
                                    'desc' => __('Limit number of locations', 'multi-location-product-and-inventory-management-pro'),
                                    'value' => 'limit="5"'
                                ],
                                [
                                    'key' => 'orderby',
                                    'desc' => __('Sort locations by', 'multi-location-product-and-inventory-management-pro'),
                                    'value' => 'name | id | count'
                                ],
                                [
                                    'key' => 'order',
                                    'desc' => __('Sort order', 'multi-location-product-and-inventory-management-pro'),
                                    'value' => 'ASC | DESC'
                                ]
                            ],
                        ],
                        [
                            'number' => 10,
                            'title' => __('Location-Based Product Recommendations', 'multi-location-product-and-inventory-management-pro'),
                            'description' => __('Display popular products for each location using customer insights and analytics. Learn to track customer preferences, show location-specific recommendations, and boost sales with personalized product suggestions.', 'multi-location-product-and-inventory-management-pro'),
                            'url' => 'https://www.youtube.com/embed/VIDEO_ID_10',
                            'duration' => '6:30',
                            'difficulty' => 'intermediate',
                            'shortcode' => '[mulopimfwc_location_recommendations limit="8" columns="4" title="Popular at {location}"]',
                            'parameters' => [
                                [
                                    'key' => 'limit',
                                    'desc' => __('Number of products to display', 'multi-location-product-and-inventory-management-pro'),
                                    'value' => 'limit="8"'
                                ],
                                [
                                    'key' => 'columns',
                                    'desc' => __('Grid columns layout', 'multi-location-product-and-inventory-management-pro'),
                                    'value' => 'columns="4"'
                                ],
                                [
                                    'key' => 'title',
                                    'desc' => __('Section title (use {location} placeholder)', 'multi-location-product-and-inventory-management-pro'),
                                    'value' => 'title="Popular at {location}"'
                                ],
                                [
                                    'key' => 'show_title',
                                    'desc' => __('Display section title', 'multi-location-product-and-inventory-management-pro'),
                                    'value' => 'yes | no'
                                ],
                                [
                                    'key' => 'show_badge',
                                    'desc' => __('Show popularity badge', 'multi-location-product-and-inventory-management-pro'),
                                    'value' => 'yes | no'
                                ],
                                [
                                    'key' => 'orderby',
                                    'desc' => __('Sort products by', 'multi-location-product-and-inventory-management-pro'),
                                    'value' => 'popularity'
                                ]
                            ],
                        ],
                        [
                            'number' => 11,
                            'title' => __('Location Selector for specific Product', 'multi-location-product-and-inventory-management-pro'),
                            'description' => __('Customize the location selector display on product pages and throughout your store. Learn to use shortcodes, and enhance customer experience with multiple layout options.', 'multi-location-product-and-inventory-management-pro'),
                            'url' => 'https://www.youtube.com/embed/VIDEO_ID_6',
                            'duration' => '6:50',
                            'difficulty' => 'intermediate',
                            'shortcode' => '[mulopimfwc_location_selector product_id="123" layout="buttons" label="Select Location:"]',
                            'topics' => ['Location Selector', 'Shortcodes', 'Customization'],
                            'parameters' => [
                                [
                                    'key' => 'product_id',
                                    'desc' => __('Specific product ID (auto-detects if not provided)', 'multi-location-product-and-inventory-management-pro'),
                                    'value' => 'product_id="123"'
                                ],
                                [
                                    'key' => 'layout',
                                    'desc' => __('Selector layout style', 'multi-location-product-and-inventory-management-pro'),
                                    'value' => 'list | buttons | select'
                                ],
                                [
                                    'key' => 'label',
                                    'desc' => __('Custom label text', 'multi-location-product-and-inventory-management-pro'),
                                    'value' => 'label="Choose Store:"'
                                ]
                            ],
                            'additional_shortcode' => '[mulopimfwc_store_location_selector enable_user_locations="on"]'
                        ],
                        [
                            'number' => 12,
                            'title' => __('AJAX Product Filter by Location & Stock', 'multi-location-product-and-inventory-management-pro'),
                            'description' => __('Learn how to use the AJAX-powered product filter to allow customers to filter products by location and stock status without page reload. Includes caching, pagination support, and shortcode usage.', 'multi-location-product-and-inventory-management-pro'),
                            'url' => 'https://www.youtube.com/embed/VIDEO_ID_12',
                            'duration' => '7:30',
                            'difficulty' => 'intermediate',
                            'shortcode' => '[mulopimfwc_product_filter]',
                            'topics' => ['Product Filter', 'AJAX Filtering', 'Location Filtering', 'Stock Filtering', 'Shortcodes'],
                            'parameters' => [
                                [
                                    'key' => 'location',
                                    'desc' => __('Show location filter dropdown', 'multi-location-product-and-inventory-management-pro'),
                                    'value' => 'yes | no'
                                ],
                                [
                                    'key' => 'stock',
                                    'desc' => __('Show stock status filter dropdown', 'multi-location-product-and-inventory-management-pro'),
                                    'value' => 'yes | no'
                                ]
                            ]
                        ],
                        [
                            'number' => 13,
                            'title' => __('Social Notifications', 'multi-location-product-and-inventory-management-pro'),
                            'description' => __('Configure social alerts (orders, stock, digests) using a Microsoft Teams incoming webhook. Covers creating the webhook in Teams and pasting it into this plugin.', 'multi-location-product-and-inventory-management-pro'),
                            'url' => 'https://www.youtube.com/embed/VIDEO_ID_TEAMS',
                            'duration' => '4:10',
                            'difficulty' => 'intermediate',
                            'topics' => ['Teams', 'Webhooks', 'Notifications', 'Slack/Discord/Teams'],
                        ],
                        [
                            'number' => 14,
                            'title' => __('API & Webhook Integration: Bulk Inventory Sync', 'multi-location-product-and-inventory-management-pro'),
                            'description' => __('Complete guide to integrating your WMS, POS, or ERP systems with the plugin using REST API endpoints and webhooks. Learn how to sync inventory in bulk via CSV or API, set up real-time webhook updates, generate API keys, configure authentication, and automate inventory management across multiple locations. This tutorial covers bulk operations, single product updates, CSV import/export, webhook configuration, and best practices for external system integration.', 'multi-location-product-and-inventory-management-pro'),
                            'url' => 'https://www.youtube.com/embed/VIDEO_ID_API_WEBHOOK',
                            'duration' => '12:45',
                            'difficulty' => 'advanced',
                            'topics' => ['REST API', 'Webhooks', 'WMS Integration', 'POS Integration', 'Bulk Sync', 'CSV Import', 'Inventory Automation', 'API Authentication'],
                            'example_requests' => [
                                'get_api_keys' => [
                                    'title' => __('How to Get API Key & Webhook Secret', 'multi-location-product-and-inventory-management-pro'),
                                    'method' => 'GET',
                                    'url' => 'WordPress Admin → Multi Location Products → API & Webhooks',
                                    'headers' => null,
                                    'body' => [
                                        'step_1' => __('Navigate to WordPress Admin Dashboard', 'multi-location-product-and-inventory-management-pro'),
                                        'step_2' => __('Go to: Multi Location Products → API & Webhooks', 'multi-location-product-and-inventory-management-pro'),
                                        'step_3' => __('Scroll to "API Authentication" section', 'multi-location-product-and-inventory-management-pro'),
                                        'step_4' => __('Click "Generate API Key" button (if no key exists)', 'multi-location-product-and-inventory-management-pro'),
                                        'step_5' => __('Copy the generated API key immediately', 'multi-location-product-and-inventory-management-pro'),
                                        'step_6' => __('Click "Generate Webhook Secret" button (if no secret exists)', 'multi-location-product-and-inventory-management-pro'),
                                        'step_7' => __('Copy the generated webhook secret immediately', 'multi-location-product-and-inventory-management-pro'),
                                        'step_8' => __('Store both keys securely (they are shown only once)', 'multi-location-product-and-inventory-management-pro'),
                                        'important_note' => __('If you lose your keys, you must generate new ones. Old keys will no longer work.', 'multi-location-product-and-inventory-management-pro'),
                                    ],
                                    'response' => [
                                        'api_key_location' => __('Displayed in "API Key" field after generation', 'multi-location-product-and-inventory-management-pro'),
                                        'webhook_secret_location' => __('Displayed in "Webhook Secret" field after generation', 'multi-location-product-and-inventory-management-pro'),
                                        'security_warning' => __('Keys are displayed only once for security. Copy them immediately.', 'multi-location-product-and-inventory-management-pro'),
                                        'usage_api_key' => __('Use in X-API-Key header for REST API requests', 'multi-location-product-and-inventory-management-pro'),
                                        'usage_webhook_secret' => __('Use in X-Webhook-Secret header for webhook requests', 'multi-location-product-and-inventory-management-pro'),
                                    ]
                                ],
                                'bulk_sync' => [
                                    'title' => __('Bulk Sync Example', 'multi-location-product-and-inventory-management-pro'),
                                    'method' => 'POST',
                                    'url' => '/mulopimfwc/v1/inventory/bulk-sync',
                                    'headers' => [
                                        'Content-Type: application/json',
                                        'X-API-Key: your-api-key-here'
                                    ],
                                    'body' => [
                                        'items' => [
                                            [
                                                'sku' => 'PROD-001',
                                                'location_slug' => 'main-store',
                                                'stock' => 100,
                                                'regular_price' => '29.99',
                                                'sale_price' => '24.99'
                                            ],
                                            [
                                                'product_id' => 123,
                                                'location_id' => 5,
                                                'stock' => 50,
                                                'regular_price' => '19.99'
                                            ]
                                        ]
                                    ],
                                    'response' => [
                                        'success' => true,
                                        'message' => 'Processed 2 items. 2 succeeded, 0 failed.',
                                        'results' => [
                                            'success' => 2,
                                            'failed' => 0,
                                            'errors' => []
                                        ]
                                    ]
                                ],
                                'single_update' => [
                                    'title' => __('Single Product Update Example', 'multi-location-product-and-inventory-management-pro'),
                                    'method' => 'POST',
                                    'url' => '/mulopimfwc/v1/inventory/update',
                                    'headers' => [
                                        'Content-Type: application/json',
                                        'X-API-Key: your-api-key-here'
                                    ],
                                    'body' => [
                                        'product_id' => 456,
                                        'location_id' => 3,
                                        'stock' => 25,
                                        'regular_price' => '39.99',
                                        'sale_price' => '34.99',
                                        'backorders' => 'no',
                                        'disabled' => false
                                    ],
                                    'response' => [
                                        'success' => true,
                                        'message' => 'Inventory updated successfully.',
                                        'data' => [
                                            'product_id' => 456,
                                            'location_id' => 3,
                                            'sku' => 'PROD-456'
                                        ]
                                    ]
                                ],
                                'export_csv' => [
                                    'title' => __('Export Inventory CSV Example', 'multi-location-product-and-inventory-management-pro'),
                                    'method' => 'GET',
                                    'url' => '/mulopimfwc/v1/inventory/export?format=csv&location_id=5',
                                    'headers' => [
                                        'X-API-Key: your-api-key-here'
                                    ],
                                    'body' => null,
                                    'response' => [
                                        'note' => 'Returns CSV file download with headers: product_id, sku, product_name, location_id, location_slug, location_name, stock, regular_price, sale_price, backorders, disabled'
                                    ]
                                ],
                                'export_json' => [
                                    'title' => __('Export Inventory JSON Example', 'multi-location-product-and-inventory-management-pro'),
                                    'method' => 'GET',
                                    'url' => '/mulopimfwc/v1/inventory/export?format=json',
                                    'headers' => [
                                        'X-API-Key: your-api-key-here'
                                    ],
                                    'body' => null,
                                    'response' => [
                                        'success' => true,
                                        'count' => 150,
                                        'data' => [
                                            [
                                                'product_id' => 123,
                                                'sku' => 'PROD-001',
                                                'product_name' => 'Sample Product',
                                                'location_id' => 5,
                                                'location_slug' => 'main-store',
                                                'location_name' => 'Main Store',
                                                'stock' => 100,
                                                'regular_price' => '29.99',
                                                'sale_price' => '24.99',
                                                'backorders' => 'no',
                                                'disabled' => false
                                            ]
                                        ]
                                    ]
                                ],
                                'webhook' => [
                                    'title' => __('Webhook Example', 'multi-location-product-and-inventory-management-pro'),
                                    'method' => 'POST',
                                    'url' => '/mulopimfwc/v1/webhook/inventory-update',
                                    'headers' => [
                                        'Content-Type: application/json',
                                        'X-Webhook-Secret: your-webhook-secret-here'
                                    ],
                                    'body' => [
                                        'sku' => 'PROD-001',
                                        'location_slug' => 'main-store',
                                        'stock' => 75
                                    ],
                                    'response' => [
                                        'success' => true,
                                        'message' => 'Inventory updated via webhook.',
                                        'data' => [
                                            'product_id' => 123,
                                            'location_id' => 5,
                                            'sku' => 'PROD-001'
                                        ]
                                    ]
                                ],
                                'get_locations' => [
                                    'title' => __('Get Locations Example', 'multi-location-product-and-inventory-management-pro'),
                                    'method' => 'GET',
                                    'url' => '/mulopimfwc/v1/locations',
                                    'headers' => [
                                        'X-API-Key: your-api-key-here'
                                    ],
                                    'body' => null,
                                    'response' => [
                                        'success' => true,
                                        'count' => 3,
                                        'data' => [
                                            [
                                                'id' => 5,
                                                'slug' => 'main-store',
                                                'name' => 'Main Store',
                                                'description' => 'Our primary retail location'
                                            ],
                                            [
                                                'id' => 6,
                                                'slug' => 'warehouse',
                                                'name' => 'Warehouse',
                                                'description' => 'Distribution center'
                                            ]
                                        ]
                                    ]
                                ],
                                'products' => [
                                    'title' => __('Get Products Example', 'multi-location-product-and-inventory-management-pro'),
                                    'method' => 'GET',
                                    'url' => '/mulopimfwc/v1/products',
                                    'headers' => [
                                        'X-API-Key: your-api-key-here'
                                    ],
                                ],
                            ],
                        ],
                        [
                            'number' => 15,
                            'title' => __('Location Status Badge Shortcode', 'multi-location-product-and-inventory-management-pro'),
                            'description' => __('Show the open/closed status badge for a single location anywhere on your site. Useful for headers, sidebars, or location detail pages.', 'multi-location-product-and-inventory-management-pro'),
                            'url' => 'https://www.youtube.com/embed/VIDEO_ID_LOCATION_STATUS',
                            'duration' => '3:20',
                            'difficulty' => 'beginner',
                            'shortcode' => '[mulopimfwc_location_status slug="new-york"]',
                            'topics' => ['Shortcodes', 'Location Status', 'Status Badge'],
                            'parameters' => [
                                [
                                    'key' => 'id',
                                    'desc' => __('Location term ID', 'multi-location-product-and-inventory-management-pro'),
                                    'value' => 'id="123"'
                                ],
                                [
                                    'key' => 'slug',
                                    'desc' => __('Location term slug', 'multi-location-product-and-inventory-management-pro'),
                                    'value' => 'slug="new-york"'
                                ],
                            ],
                        ],
                    ];

                    // Calculate total minutes from tutorial durations (safely handle missing or different formats)
                    $total_minutes = 0;
                    foreach ($tutorial_videos as $tv) {
                        if (!empty($tv['duration'])) {
                            $parts = explode(':', $tv['duration']);
                            if (count($parts) === 3) { // hh:mm:ss
                                $hours = intval($parts[0]);
                                $minutes = intval($parts[1]);
                                $seconds = intval($parts[2]);
                            } elseif (count($parts) === 2) { // mm:ss
                                $hours = 0;
                                $minutes = intval($parts[0]);
                                $seconds = intval($parts[1]);
                            } else {
                                // unknown format, skip
                                continue;
                            }
                            // Add total minutes, round seconds to nearest minute (>=30s -> +1)
                            $total_minutes += ($hours * 60) + $minutes + ($seconds >= 30 ? 1 : 0);
                        }
                    }

                    ?>
                    <!-- Header -->
                    <div class="lwp-tutorial-header">
                        <div class="lwp-tutorial-header-content">
                            <svg class="lwp-tutorial-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z" />
                            </svg>
                            <div>
                                <h2 class="lwp-tutorial-title">
                                    <?php echo esc_html__('Video Tutorials & Documentation', 'multi-location-product-and-inventory-management-pro'); ?>
                                </h2>
                                <p class="lwp-tutorial-subtitle">
                                    <?php echo esc_html__('Step-by-step guides to master location-based inventory management', 'multi-location-product-and-inventory-management-pro'); ?>
                                </p>
                            </div>
                        </div>
                        <div class="lwp-tutorial-stats">
                            <div class="lwp-stat-item">
                                <span class="lwp-stat-number"><?php echo esc_html(isset($tutorial_videos) && is_array($tutorial_videos) ? count($tutorial_videos) : 0); ?></span>
                                <span class="lwp-stat-label"><?php echo esc_html__('Tutorials', 'multi-location-product-and-inventory-management-pro'); ?></span>
                            </div>
                            <div class="lwp-stat-item">
                                <span class="lwp-stat-number"><?php echo esc_html($total_minutes); ?>+</span>
                                <span class="lwp-stat-label"><?php echo esc_html__('Minutes', 'multi-location-product-and-inventory-management-pro'); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Tutorials Grid -->
                    <div class="lwp-tutorials-container" id="lwp-tutorials-container">
                        <?php

                        foreach ($tutorial_videos as $tutorial) {
                            $difficulty_class = isset($tutorial['difficulty']) ? 'lwp-difficulty-' . $tutorial['difficulty'] : 'lwp-difficulty-beginner';
                        ?>
                            <div class="lwp-tutorial-card">
                                <!-- Video Container -->
                                <div class="lwp-tutorial-video-wrapper">
                                    <iframe
                                        class="lwp-tutorial-iframe"
                                        src="<?php echo esc_url($tutorial['url']); ?>"
                                        title="<?php echo esc_attr($tutorial['title']); ?>"
                                        frameborder="0"
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                        allowfullscreen
                                        loading="lazy">
                                    </iframe>
                                    <div class="lwp-tutorial-badge">
                                        <span class="lwp-tutorial-number"><?php echo esc_html($tutorial['number']); ?></span>
                                    </div>
                                    <?php if (isset($tutorial['duration'])): ?>
                                        <div class="lwp-tutorial-duration">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                                                <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2zm4.2 14.2L11 13V7h1.5v5.2l4.5 2.7-.8 1.3z" fill="currentColor" />
                                            </svg>
                                            <?php echo esc_html($tutorial['duration']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="lwp-tutorial-difficulty <?php echo esc_attr($difficulty_class); ?>">
                                        <?php echo esc_html(ucfirst($tutorial['difficulty'])); ?>
                                    </div>
                                </div>

                                <!-- Content Container -->
                                <div class="lwp-tutorial-content">
                                    <!-- Title -->
                                    <h3 class="lwp-tutorial-card-title"><?php echo esc_html($tutorial['title'] ?? ''); ?></h3>

                                    <!-- Description -->
                                    <p class="lwp-tutorial-description"><?php echo esc_html($tutorial['description'] ?? ''); ?></p>

                                    <!-- Topics Covered -->
                                    <?php if (isset($tutorial['topics']) && !empty($tutorial['topics'])): ?>
                                        <div class="lwp-tutorial-topics-section">
                                            <div class="lwp-tutorial-section-label">
                                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="14" height="14">
                                                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z" fill="currentColor" />
                                                </svg>
                                                <?php echo esc_html__('Topics Covered', 'multi-location-product-and-inventory-management-pro'); ?>
                                            </div>
                                            <div class="lwp-topics-tags">
                                                <?php foreach ($tutorial['topics'] as $topic): ?>
                                                    <span class="lwp-topic-tag"><?php echo esc_html($topic ?? ''); ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Shortcode Section -->
                                    <?php if (isset($tutorial['shortcode']) && !empty($tutorial['shortcode'])): ?>
                                        <div class="lwp-tutorial-shortcode-section">
                                            <div class="lwp-tutorial-section-label">
                                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="14" height="14">
                                                    <path d="M9.4 16.6L4.8 12l4.6-4.6M14.6 16.6l4.6-4.6-4.6-4.6" fill="none" stroke="currentColor" stroke-width="2" />
                                                </svg>
                                                <?php echo esc_html__('Related Shortcode', 'multi-location-product-and-inventory-management-pro'); ?>
                                            </div>
                                            <div class="lwp-shortcode-block">
                                                <code><?php echo esc_html($tutorial['shortcode']); ?></code>
                                                <button class="copy-btn" onclick="copyToClipboard (event, '<?php echo esc_attr($tutorial['shortcode']); ?>')" title="<?php echo esc_attr__('Copy shortcode', 'multi-location-product-and-inventory-management-pro'); ?>">
                                                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="14" height="14">
                                                        <path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z" fill="currentColor" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Parameters Section -->
                                    <?php if (isset($tutorial['parameters']) && !empty($tutorial['parameters'])) { ?>
                                        <div class="lwp-tutorial-params-section">
                                            <div class="lwp-tutorial-section-label">
                                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14zm-5.04-6.71l-2.75 3.54h3.02l4.25-5.7h-3.02l2.75-3.54h-3.02l-4.25 5.7h3.02z" />
                                                </svg>
                                                <?php echo esc_html__('Parameters', 'multi-location-product-and-inventory-management-pro'); ?>
                                            </div>
                                            <div class="lwp-params-list">
                                                <?php foreach ($tutorial['parameters'] as $param) { ?>
                                                    <div class="lwp-param-item">
                                                        <div class="lwp-param-key"><?php echo esc_html($param['key']); ?></div>
                                                        <div class="lwp-param-desc"><?php echo esc_html($param['desc']); ?></div>
                                                        <div class="lwp-param-value"><?php echo esc_html($param['value']); ?></div>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                        </div>
                                    <?php } ?>

                                    <!-- Usage Examples Section -->
                                    <?php if (isset($tutorial['example_requests']) && !empty($tutorial['example_requests'])) { ?>
                                        <div class="lwp-tutorial-examples-section">
                                            <div class="lwp-tutorial-section-label">
                                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="14" height="14">
                                                    <path d="M9.4 16.6L4.8 12l4.6-4.6M14.6 16.6l4.6-4.6-4.6-4.6" fill="none" stroke="currentColor" stroke-width="2" />
                                                </svg>
                                                <?php echo esc_html__('Usage Examples', 'multi-location-product-and-inventory-management-pro'); ?>
                                            </div>
                                            <?php foreach ($tutorial['example_requests'] as $example_key => $example) { ?>
                                                <div class="lwp-example-item">
                                                    <div class="lwp-example-header" onclick="toggleExample(this)">
                                                        <div class="lwp-example-title">
                                                            <strong><?php echo esc_html($example['title'] ?? ''); ?></strong>
                                                            <span class="lwp-example-method"><?php echo esc_html($example['method'] ?? 'POST'); ?></span>
                                                        </div>
                                                        <div class="lwp-example-toggle">
                                                            <svg class="lwp-example-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <polyline points="6 9 12 15 18 9"></polyline>
                                                            </svg>
                                                        </div>
                                                    </div>
                                                    <div class="lwp-example-content">
                                                        <div class="lwp-example-url">
                                                            <span class="lwp-example-label"><?php echo esc_html__('Endpoint:', 'multi-location-product-and-inventory-management-pro'); ?></span>
                                                            <code><?php echo esc_html($example['url'] ?? ''); ?></code>
                                                        </div>
                                                        <?php if (isset($example['headers']) && !empty($example['headers'])) { ?>
                                                            <div class="lwp-example-headers">
                                                                <span class="lwp-example-label"><?php echo esc_html__('Headers:', 'multi-location-product-and-inventory-management-pro'); ?></span>
                                                                <pre><code><?php echo esc_html(implode("\n", $example['headers'])); ?></code></pre>
                                                            </div>
                                                        <?php } ?>
                                                        <?php if (isset($example['body']) && !empty($example['body']) && $example['body'] !== null) { ?>
                                                            <div class="lwp-example-body">
                                                                <span class="lwp-example-label"><?php echo esc_html__('Steps / Request Body:', 'multi-location-product-and-inventory-management-pro'); ?></span>
                                                                <?php
                                                                if (is_array($example['body']) && isset($example['body']['step_1'])) {
                                                                    echo '<div class="lwp-example-steps">';
                                                                    foreach ($example['body'] as $key => $value) {
                                                                        if (strpos($key, 'step_') === 0 || strpos($key, 'important_') === 0) {
                                                                            echo '<div class="lwp-example-step-item">';
                                                                            echo '<strong>' . esc_html(ucfirst(str_replace('_', ' ', $key))) . ':</strong>';
                                                                            echo '<span>' . esc_html($value) . '</span>';
                                                                            echo '</div>';
                                                                        }
                                                                    }
                                                                    echo '</div>';
                                                                } else {
                                                                    echo '<pre><code>' . esc_html(json_encode($example['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</code></pre>';
                                                                }
                                                                ?>
                                                            </div>
                                                        <?php } ?>
                                                        <?php if (isset($example['response']) && !empty($example['response'])) { ?>
                                                            <div class="lwp-example-response">
                                                                <span class="lwp-example-label"><?php echo esc_html__('Response:', 'multi-location-product-and-inventory-management-pro'); ?></span>
                                                                <pre><code><?php echo esc_html(json_encode($example['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></code></pre>
                                                            </div>
                                                        <?php } ?>
                                                    </div>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>
                    </div>

                    <!-- CTA Section -->
                    <div class="lwp-tutorial-cta">
                        <div class="lwp-tutorial-cta-content">
                            <div class="lwp-cta-icon">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="48" height="48">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" fill="currentColor" />
                                </svg>
                            </div>
                            <div>
                                <h3 style="color: #fff;"><?php echo esc_html__('Need Additional Help?', 'multi-location-product-and-inventory-management-pro'); ?></h3>
                                <p><?php echo esc_html__('Access our comprehensive documentation, knowledge base articles, and get direct support from our expert team', 'multi-location-product-and-inventory-management-pro'); ?></p>
                            </div>
                        </div>
                        <div class="lwp-tutorial-cta-buttons">
                            <a href="https://plugincy.com/documentations/multi-location-product-inventory-management-for-woocommerce/" target="_blank" class="lwp-btn lwp-btn-primary">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="18" height="18">
                                    <path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z" fill="currentColor" />
                                </svg>
                                <?php echo esc_html__('View Documentation', 'multi-location-product-and-inventory-management-pro'); ?>
                            </a>
                            <a href="https://www.plugincy.com/support/" target="_blank" class="lwp-btn lwp-btn-secondary">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="18" height="18">
                                    <path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-7 12h-2v-2h2v2zm0-4h-2V6h2v4z" fill="currentColor" />
                                </svg>
                                <?php echo esc_html__('Contact Support', 'multi-location-product-and-inventory-management-pro'); ?>
                            </a>
                            <a href="https://demo.plugincy.com/multi-location-product-and-inventory-management/" target="_blank" class="lwp-btn lwp-btn-accent">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="18" height="18">
                                    <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z" fill="currentColor" />
                                </svg>
                                <?php echo esc_html__('Try Live Demo', 'multi-location-product-and-inventory-management-pro'); ?>
                            </a>
                        </div>
                        <div class="lwp-support-features">
                            <div class="lwp-support-item">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="20" height="20">
                                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z" fill="currentColor" />
                                </svg>
                                <span><?php echo esc_html__('Detailed KB Articles', 'multi-location-product-and-inventory-management-pro'); ?></span>
                            </div>
                            <div class="lwp-support-item">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="20" height="20">
                                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z" fill="currentColor" />
                                </svg>
                                <span><?php echo esc_html__('Priority Email Support', 'multi-location-product-and-inventory-management-pro'); ?></span>
                            </div>
                            <div class="lwp-support-item">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="20" height="20">
                                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z" fill="currentColor" />
                                </svg>
                                <span><?php echo esc_html__('Regular Updates', 'multi-location-product-and-inventory-management-pro'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Enhanced Styles -->
                <style>
                    /* Tutorial Section */
                    .lwp-tutorial-section {
                        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
                        padding: 40px 20px;
                        border-radius: 12px;
                        margin-top: 40px;
                    }

                    /* Header */
                    .lwp-tutorial-header {
                        display: flex;
                        justify-content: space-between;
                        align-items: flex-start;
                        margin-bottom: 32px;
                        gap: 20px;
                        flex-wrap: wrap;
                    }

                    .lwp-tutorial-header-content {
                        display: flex;
                        align-items: center;
                        gap: 16px;
                        flex: 1;
                        min-width: 300px;
                    }

                    .lwp-tutorial-icon {
                        width: 48px;
                        height: 48px;
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        padding: 8px;
                        fill: white;
                        border-radius: 8px;
                        color: white;
                        flex-shrink: 0;
                    }

                    .lwp-tutorial-title {
                        font-size: 24px;
                        font-weight: 700;
                        color: #0f172a;
                        margin: 0;
                        line-height: 1.2;
                    }

                    .lwp-tutorial-subtitle {
                        font-size: 14px;
                        color: #64748b;
                        margin: 4px 0 0 0;
                    }

                    /* Stats */
                    .lwp-tutorial-stats {
                        display: flex;
                        gap: 24px;
                    }

                    .lwp-stat-item {
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        gap: 4px;
                        padding: 12px 20px;
                        background: white;
                        border-radius: 8px;
                        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
                    }

                    .lwp-stat-number {
                        font-size: 24px;
                        font-weight: 700;
                        color: #667eea;
                        line-height: 1;
                    }

                    .lwp-stat-label {
                        font-size: 12px;
                        color: #64748b;
                        text-transform: uppercase;
                        letter-spacing: 0.5px;
                    }

                    /* Container */
                    .lwp-tutorials-container {
                        display: grid;
                        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
                        gap: 24px;
                        margin-bottom: 32px;
                    }

                    /* Card */
                    .lwp-tutorial-card {
                        background: white;
                        border-radius: 10px;
                        overflow: hidden;
                        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
                        border: 1px solid #e2e8f0;
                        transition: all 0.3s ease;
                        display: flex;
                        flex-direction: column;
                        height: 100%;
                    }

                    .lwp-tutorial-card:hover {
                        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
                        border-color: #cbd5e1;
                        transform: translateY(-4px);
                    }

                    /* Video Wrapper */
                    .lwp-tutorial-video-wrapper {
                        position: relative;
                        width: 100%;
                        padding-bottom: 56.25%;
                        background: #1a1a1a;
                        overflow: hidden;
                        display: none;
                    }

                    .lwp-tutorial-iframe {
                        position: absolute;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        border: none;
                    }

                    .lwp-tutorial-badge {
                        position: absolute;
                        top: 12px;
                        left: 12px;
                        background: #2563eb;
                        color: white;
                        width: 36px;
                        height: 36px;
                        border-radius: 50%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-weight: 700;
                        font-size: 14px;
                        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
                    }

                    .lwp-tutorial-duration {
                        position: absolute;
                        bottom: 12px;
                        right: 12px;
                        background: rgba(0, 0, 0, 0.8);
                        color: white;
                        padding: 4px 8px;
                        border-radius: 4px;
                        font-size: 12px;
                        font-weight: 600;
                        display: flex;
                        align-items: center;
                        gap: 4px;
                    }

                    .lwp-tutorial-difficulty {
                        position: absolute;
                        top: 12px;
                        right: 12px;
                        padding: 4px 12px;
                        border-radius: 12px;
                        font-size: 11px;
                        font-weight: 600;
                        text-transform: uppercase;
                        letter-spacing: 0.5px;
                    }

                    .lwp-difficulty-beginner {
                        background: rgba(16, 185, 129, 0.9);
                        color: white;
                    }

                    .lwp-difficulty-intermediate {
                        background: rgba(59, 130, 246, 0.9);
                        color: white;
                    }

                    .lwp-difficulty-advanced {
                        background: rgba(245, 158, 11, 0.9);
                        color: white;
                    }

                    /* Content */
                    .lwp-tutorial-content {
                        padding: 20px;
                        flex: 1;
                        display: flex;
                        flex-direction: column;
                        gap: 12px;
                    }

                    .lwp-tutorial-card-title {
                        font-size: 16px;
                        font-weight: 600;
                        color: #0f172a;
                        margin: 0;
                        line-height: 1.4;
                    }

                    .lwp-tutorial-description {
                        font-size: 13px;
                        color: #64748b;
                        line-height: 1.6;
                        margin: 0;
                    }

                    /* Section Label */
                    .lwp-tutorial-section-label {
                        display: flex;
                        align-items: center;
                        gap: 6px;
                        font-size: 11px;
                        font-weight: 600;
                        color: #64748b;
                        text-transform: uppercase;
                        letter-spacing: 0.5px;
                        margin-bottom: 8px;
                    }

                    .lwp-tutorial-section-label svg {
                        fill: #64748b;
                        width: 16px;
                        height: 16px;
                    }

                    /* Topics */
                    .lwp-tutorial-topics-section {
                        margin-top: auto;
                    }

                    .lwp-topics-tags {
                        display: flex;
                        flex-wrap: wrap;
                        gap: 6px;
                    }

                    .lwp-topic-tag {
                        display: inline-block;
                        background: #f1f5f9;
                        color: #475569;
                        padding: 4px 10px;
                        border-radius: 4px;
                        font-size: 11px;
                        font-weight: 500;
                        border: 1px solid #e2e8f0;
                    }

                    /* Shortcode */
                    .lwp-tutorial-shortcode-section {
                        margin-top: auto;
                    }

                    .lwp-shortcode-block {
                        background: #f1f5f9;
                        border: 1px solid #e2e8f0;
                        border-radius: 6px;
                        padding: 10px 12px;
                        font-family: 'Monaco', 'Courier New', monospace;
                        font-size: 11px;
                        color: #0f172a;
                        position: relative;
                        word-break: break-all;
                        line-height: 1.4;
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        gap: 8px;
                    }



                    /* Parameters */
                    .lwp-tutorial-params-section {
                        margin-top: auto;
                    }

                    .lwp-params-list {
                        display: grid;
                        grid-template-columns: 1fr 1fr;
                        gap: 8px;
                    }

                    .lwp-param-item {
                        background: #f8fafc;
                        border-left: 3px solid #2563eb;
                        padding: 8px 10px;
                        border-radius: 4px;
                    }

                    .lwp-param-key {
                        font-weight: 600;
                        color: #0f172a;
                        font-family: 'Monaco', 'Courier New', monospace;
                        font-size: 11px;
                    }

                    .lwp-param-desc {
                        font-size: 12px;
                        color: #64748b;
                        margin-top: 2px;
                    }

                    .lwp-param-value {
                        font-size: 11px;
                        color: #475569;
                        font-family: 'Monaco', 'Courier New', monospace;
                        margin-top: 2px;
                        background: white;
                        padding: 2px 4px;
                        border-radius: 3px;
                        display: inline-block;
                    }

                    /* CTA Section */
                    .lwp-tutorial-cta {
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        border-radius: 10px;
                        padding: 32px;
                        color: white;
                    }

                    .lwp-tutorial-cta-content {
                        display: flex;
                        align-items: center;
                        gap: 20px;
                        margin-bottom: 24px;
                    }

                    .lwp-cta-icon {
                        width: 64px;
                        height: 64px;
                        background: rgba(255, 255, 255, 0.2);
                        border-radius: 12px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        flex-shrink: 0;
                    }

                    .lwp-cta-icon svg {
                        fill: white;
                    }

                    .lwp-tutorial-cta-content h3 {
                        font-size: 22px;
                        font-weight: 700;
                        margin: 0 0 8px 0;
                    }

                    .lwp-tutorial-cta-content p {
                        font-size: 14px;
                        opacity: 0.95;
                        margin: 0;
                    }

                    .lwp-tutorial-cta-buttons {
                        display: flex;
                        gap: 12px;
                        flex-wrap: wrap;
                        margin-bottom: 24px;
                    }

                    .lwp-btn {
                        display: inline-flex;
                        align-items: center;
                        gap: 8px;
                        padding: 12px 24px;
                        border-radius: 6px;
                        font-weight: 600;
                        font-size: 14px;
                        text-decoration: none;
                        border: none;
                        cursor: pointer;
                        transition: all 0.3s ease;
                    }

                    .lwp-btn-primary {
                        background: white;
                        color: #667eea;
                    }

                    .lwp-btn-primary:hover {
                        background: #f8fafc;
                        transform: translateY(-1px);
                    }

                    .lwp-btn-secondary {
                        background: rgba(255, 255, 255, 0.2);
                        color: white;
                        border: 1px solid rgba(255, 255, 255, 0.3);
                    }

                    .lwp-btn-secondary:hover {
                        background: rgba(255, 255, 255, 0.3);
                        border-color: rgba(255, 255, 255, 0.5);
                        transform: translateY(-1px);
                    }

                    .lwp-btn-accent {
                        background: #f59e0b;
                        color: white;
                    }

                    .lwp-btn-accent:hover {
                        background: #d97706;
                        transform: translateY(-1px);
                    }

                    /* Support Features */
                    .lwp-support-features {
                        display: flex;
                        gap: 24px;
                        flex-wrap: wrap;
                    }

                    .lwp-support-item {
                        display: flex;
                        align-items: center;
                        gap: 8px;
                        font-size: 14px;
                    }

                    .lwp-support-item svg {
                        flex-shrink: 0;
                    }

                    /* Usage Examples Section */
                    .lwp-tutorial-examples-section {
                        margin-top: 20px;
                        border-top: 1px solid #e2e8f0;
                        padding-top: 20px;
                    }

                    .lwp-example-item {
                        margin-bottom: 16px;
                        border: 1px solid #e2e8f0;
                        border-radius: 8px;
                        overflow: hidden;
                        background: #fff;
                        transition: all 0.3s ease;
                    }

                    .lwp-example-item:hover {
                        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                    }

                    .lwp-example-header {
                        padding: 14px 16px;
                        cursor: pointer;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        background: #f8fafc;
                        transition: background 0.2s ease;
                    }

                    .lwp-example-header:hover {
                        background: #f1f5f9;
                    }

                    .lwp-example-header.active {
                        background: #e0f2fe;
                    }

                    .lwp-example-title {
                        display: flex;
                        align-items: center;
                        gap: 12px;
                        flex: 1;
                    }

                    .lwp-example-method {
                        padding: 4px 8px;
                        background: #3b82f6;
                        color: #fff;
                        border-radius: 4px;
                        font-size: 11px;
                        font-weight: 600;
                        text-transform: uppercase;
                    }

                    .lwp-example-toggle {
                        display: flex;
                        align-items: center;
                        gap: 8px;
                        color: #64748b;
                        font-size: 13px;
                    }

                    .lwp-example-preview {
                        font-family: monospace;
                        color: #64748b;
                    }

                    .lwp-example-arrow {
                        width: 16px;
                        height: 16px;
                        transition: transform 0.3s ease;
                    }

                    .lwp-example-header.active .lwp-example-arrow {
                        transform: rotate(180deg);
                    }

                    .lwp-example-content {
                        max-height: 0;
                        overflow: hidden;
                        transition: max-height 0.3s ease;
                        background: #fff;
                    }

                    .lwp-example-header.active+.lwp-example-content {
                        max-height: 2000px;
                    }

                    .lwp-example-content>div {
                        padding: 16px;
                        border-top: 1px solid #e2e8f0;
                    }

                    .lwp-example-content>div:first-child {
                        border-top: none;
                    }

                    .lwp-example-label {
                        display: block;
                        font-weight: 600;
                        color: #334155;
                        margin-bottom: 8px;
                        font-size: 13px;
                    }

                    .lwp-example-url code {
                        display: block;
                        padding: 10px;
                        background: #f1f5f9;
                        border-radius: 6px;
                        color: #1e293b;
                        font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
                        font-size: 13px;
                        word-break: break-all;
                    }

                    .lwp-example-headers pre,
                    .lwp-example-body pre,
                    .lwp-example-response pre {
                        margin: 0;
                        padding: 12px;
                        background: #1e293b;
                        border-radius: 6px;
                        overflow-x: auto;
                    }

                    .lwp-example-headers code,
                    .lwp-example-body code,
                    .lwp-example-response code {
                        color: #e2e8f0;
                        font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
                        font-size: 12px;
                        line-height: 1.6;
                    }

                    .lwp-example-steps {
                        padding: 12px;
                        background: #f8fafc;
                        border-radius: 6px;
                        border-left: 3px solid #3b82f6;
                    }

                    .lwp-example-step-item {
                        padding: 8px 0;
                        border-bottom: 1px solid #e2e8f0;
                        display: flex;
                        flex-direction: column;
                        gap: 4px;
                    }

                    .lwp-example-step-item:last-child {
                        border-bottom: none;
                    }

                    .lwp-example-step-item strong {
                        color: #1e293b;
                        font-size: 13px;
                    }

                    .lwp-example-step-item span {
                        color: #64748b;
                        font-size: 13px;
                        padding-left: 12px;
                    }
                </style>

                <script>
                    function toggleExample(header) {
                        const item = header.closest('.lwp-example-item');
                        const content = item.querySelector('.lwp-example-content');
                        const isActive = header.classList.contains('active');

                        // Close all other examples in the same section
                        const section = item.closest('.lwp-tutorial-examples-section');
                        if (section) {
                            const allItems = section.querySelectorAll('.lwp-example-item');
                            allItems.forEach(otherItem => {
                                if (otherItem !== item) {
                                    otherItem.querySelector('.lwp-example-header').classList.remove('active');
                                }
                            });
                        }

                        // Toggle current example
                        if (isActive) {
                            header.classList.remove('active');
                        } else {
                            header.classList.add('active');
                        }
                    }
                </script>

                <script>
                    jQuery(document).ready(function($) {
                        // Function to toggle visibility of default template only fields
                        function toggleDefaultTemplateFields() {
                            var templateSelection = $('#template_selection').val();
                            var isDefault = (templateSelection === 'default');

                            // Find all fields that should only show for default template
                            $('.mulopimfwc-default-template-only').each(function() {
                                var $field = $(this);
                                var $row = $field.closest('tr');

                                if (isDefault) {
                                    $row.show();
                                    $field.show();
                                } else {
                                    $row.hide();
                                    $field.hide();
                                }
                            });
                        }

                        // Run on page load
                        setTimeout(function() {
                            toggleDefaultTemplateFields();
                        }, 2000);

                        // Run when template selection changes
                        $('#template_selection').on('change', function() {
                            toggleDefaultTemplateFields();
                        });
                    });
                </script>
            </div>

        <?php
    }

    private function get_display_options()
    {

        global $mulopimfwc_options;
        $options = is_array($mulopimfwc_options ?? null)
            ? $mulopimfwc_options
            : get_option('mulopimfwc_display_options', []);
        return $options;
    }

    private function is_manual_assignment_mode(): bool
    {
        $options = $this->get_display_options();
        $assignment_method = function_exists('mulopimfwc_get_effective_order_assignment_method')
            ? mulopimfwc_get_effective_order_assignment_method($options)
            : (isset($options['order_assignment_method']) ? $options['order_assignment_method'] : 'customer_selection');
        return in_array($assignment_method, ['manual', 'inventory_based', 'proximity_based'], true);
    }

    private function is_manual_optional_location_enabled(): bool
    {
        $options = $this->get_display_options();
        $assignment_method = function_exists('mulopimfwc_get_effective_order_assignment_method')
            ? mulopimfwc_get_effective_order_assignment_method($options)
            : (isset($options['order_assignment_method']) ? $options['order_assignment_method'] : 'customer_selection');
        if (!in_array($assignment_method, ['manual', 'inventory_based', 'proximity_based'], true)) {
            return false;
        }

        return isset($options['manual_optional_location_selection'])
            && $options['manual_optional_location_selection'] === 'on';
    }

    private function is_manual_assignment_strict_mode(): bool
    {
        return $this->is_manual_assignment_mode() && !$this->is_manual_optional_location_enabled();
    }

    private function append_manual_disabled_note(string $message): string
    {
        if ($this->is_manual_assignment_strict_mode()) {
            $message .= ' ' . __('Disabled while Manual, Inventory-Based, or Proximity-Based assignment is enabled without optional selection.', 'multi-location-product-and-inventory-management-pro');
        }
        return $message;
    }

    private function render_manual_hidden_input(string $key, $value): void
    {
        if (!$this->is_manual_assignment_strict_mode()) {
            return;
        }

        echo '<input type="hidden" data-manual-hidden="true" data-manual-for="mulopimfwc_display_options[' . esc_attr($key) . ']" name="mulopimfwc_display_options[' . esc_attr($key) . ']" value="' . esc_attr($value) . '">';
    }

    private function render_checkbox_unchecked_input(string $name, bool $disabled = false, string $value = 'off'): void
    {
        if ($disabled) {
            return;
        }

        echo '<input type="hidden" data-checkbox-fallback="true" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '">';
    }

    public function filter_settings_section_callback()
    {
        echo '<p>' . esc_html_e('Configure how strictly products are filtered by location throughout your store.', 'multi-location-product-and-inventory-management-pro') . '</p>';
    }
    public function strict_filtering_field_callback()
    {
        $options = $this->get_display_options();
        $strict = isset($options['strict_filtering']) ? $options['strict_filtering'] : 'enabled';
        $is_manual_mode = $this->is_manual_assignment_strict_mode();

        if ($is_manual_mode) {
            $this->render_manual_hidden_input('strict_filtering', $strict);
        }
        ?>
            <select id="strict_filtering" name="mulopimfwc_display_options[strict_filtering]" <?php echo $is_manual_mode ? ' disabled' : ''; ?>>
                <option value="enabled" <?php selected($strict, 'enabled'); ?>><?php echo esc_html_e('Enabled (Only show products from selected location)', 'multi-location-product-and-inventory-management-pro'); ?></option>
                <option value="disabled" <?php selected($strict, 'disabled'); ?>><?php echo esc_html_e('Disabled (Show all products regardless of location)', 'multi-location-product-and-inventory-management-pro'); ?></option>
            </select>
            <p class="description"><?php echo esc_html($this->append_manual_disabled_note(__('When enabled, users will only see products from their selected location. When disabled, all products will be visible.', 'multi-location-product-and-inventory-management-pro'))); ?></p>
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
            'shop' => __('Main Shop & Archive Pages', 'multi-location-product-and-inventory-management-pro'),
            'search' => __('Search Results', 'multi-location-product-and-inventory-management-pro'),
            'related' => __('Related Products', 'multi-location-product-and-inventory-management-pro'),
            'recently_viewed' => __('Recently Viewed Products', 'multi-location-product-and-inventory-management-pro'),
            'cross_sells' => __('Cross-Sells', 'multi-location-product-and-inventory-management-pro'),
            'upsells' => __('Upsells', 'multi-location-product-and-inventory-management-pro'),
            'widgets' => __('Product Widgets', 'multi-location-product-and-inventory-management-pro'),
            'blocks' => __('Product Blocks (Gutenberg)', 'multi-location-product-and-inventory-management-pro'),
            'rest_api' => __('REST API & AJAX Responses', 'multi-location-product-and-inventory-management-pro'),
        ];

        foreach ($all_sections as $value => $label) {
            $checked = in_array($value, $sections) ? 'checked' : '';
            echo "<label><input type='checkbox' name='mulopimfwc_display_options[filtered_sections][]' value='" . esc_attr($value) . "' " . esc_attr($checked) . "> " . esc_html($label) . "</label><br>";
        }
        ?>
            <p class="description"><?php echo esc_html_e('Select which parts of your store should have location-based filtering applied.', 'multi-location-product-and-inventory-management-pro'); ?></p>
        <?php
    }

    private function render_text_management_tab(): void
    {
        if (!function_exists('mulopimfwc_get_text_management_fields')) {
            echo '<p>' . esc_html__('Text management configuration is not available.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            return;
        }

        $options = $this->get_display_options();
        $is_premium = function_exists('mulopimfwc_premium_feature') ? mulopimfwc_premium_feature() : false;
        $is_text_management_enabled = isset($options['enable_text_management']) && $options['enable_text_management'] === 'on';
        $current_template = isset($options['template_selection']) ? $options['template_selection'] : 'default';
        $is_manual_mode = $this->is_manual_assignment_strict_mode();
        $groups = mulopimfwc_get_text_management_fields();
        $translation_data = [];
        $translation_locales = [];
        $translation_file = plugin_dir_path(__DIR__) . 'assets/i18n/text-management.json';
        if (file_exists($translation_file)) {
            $raw_translation = file_get_contents($translation_file);
            $decoded_translation = json_decode($raw_translation, true);
            if (is_array($decoded_translation)) {
                $translation_data = $decoded_translation;
                if (!empty($decoded_translation['locales']) && is_array($decoded_translation['locales'])) {
                    $translation_locales = $decoded_translation['locales'];
                }
            }
        }
        $text_toggle_disabled_class = !$is_text_management_enabled ? ' mulopimfwc-setting-disabled' : '';
        $text_management_base_disabled = $is_premium ? '0' : '1';

        echo '<style>
            .mulopimfwc-text-management-intro {
                background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
                border: 1px solid #e2e8f0;
                border-radius: 16px;
                padding: 22px 24px;
                margin-bottom: 22px;
                box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
            }
            .mulopimfwc-text-management-intro__header {
                display: flex;
                flex-direction: column;
                gap: 6px;
                max-width: 720px;
            }
            .mulopimfwc-text-management-intro__eyebrow {
                font-size: 12px;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                font-weight: 700;
                color: #6366f1;
            }
            .mulopimfwc-text-management-intro h2 {
                margin: 0;
                font-size: 20px;
                font-weight: 700;
                color: #0f172a;
            }
            .mulopimfwc-text-management-intro p {
                margin: 0;
                color: #475569;
                line-height: 1.6;
            }
            .mulopimfwc-text-management-toggle {
                margin-top: 16px;
                padding: 14px 16px;
                border: 1px dashed #cbd5e1;
                border-radius: 12px;
                background: #ffffff;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 14px;
            }
            .mulopimfwc-text-management-toggle__title {
                margin: 0;
                font-size: 14px;
                font-weight: 700;
                color: #0f172a;
            }
            .mulopimfwc-text-management-toggle__desc {
                margin: 2px 0 0;
                color: #64748b;
                font-size: 12px;
            }
            .mulopimfwc-text-management-intro__actions {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
                gap: 16px;
                margin-top: 16px;
            }
            .mulopimfwc-text-action-card {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 14px;
                padding: 16px;
                box-shadow: 0 8px 20px rgba(15, 23, 42, 0.05);
                display: flex;
                flex-direction: column;
                gap: 12px;
            }
            .mulopimfwc-text-action-card__header {
                display: flex;
                gap: 12px;
                align-items: center;
            }
            .mulopimfwc-text-action-card__icon {
                width: 38px;
                height: 38px;
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #e0f2fe;
                color: #0284c7;
                flex-shrink: 0;
            }
            .mulopimfwc-text-action-card--reset .mulopimfwc-text-action-card__icon {
                background: #fee2e2;
                color: #dc2626;
            }
            .mulopimfwc-text-action-title {
                margin: 0;
                font-size: 14px;
                font-weight: 700;
                color: #0f172a;
            }
            .mulopimfwc-text-action-subtitle {
                margin: 2px 0 0 0;
                font-size: 12px;
                color: #64748b;
            }
            .mulopimfwc-text-action-row {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }
            .mulopimfwc-text-translate-select {
                flex: 1;
                min-width: 180px;
            }
            .mulopimfwc-text-management-intro__note {
                margin: 0;
                font-size: 12px;
                color: #64748b;
            }
            .mulopimfwc-text-reset-button {
                background: #fef2f2;
                color: #b91c1c;
                border-color: #fecaca;
            }
            .mulopimfwc-text-reset-button:hover {
                background: #fee2e2;
                border-color: #fca5a5;
                color: #991b1b;
            }
            .mulopimfwc-text-reset-button:focus {
                box-shadow: 0 0 0 1px #fecaca;
            }
            @media (max-width: 900px) {
                .mulopimfwc-text-action-row {
                    flex-direction: column;
                    align-items: stretch;
                }
            }
            .mulopimfwc-text-group + .mulopimfwc-text-group {
                margin-top: 20px;
            }
            .mulopimfwc-text-group__header {
                display: flex;
                gap: 12px;
                align-items: center;
                margin-bottom: 16px;
            }
            .mulopimfwc-text-group__title {
                margin: 0;
                font-size: 16px;
                font-weight: 700;
                color: #0f172a;
            }
            .mulopimfwc-text-group__desc {
                margin: 4px 0 0 0;
                color: #64748b;
            }
            .mulopimfwc-text-fields {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
                gap: 16px;
            }
            .mulopimfwc-text-field {
                background: #f9fafb;
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                padding: 12px 14px;
            }
            .mulopimfwc-text-field__header {
                display: flex;
                justify-content: space-between;
                gap: 8px;
                margin-bottom: 8px;
            }
            .mulopimfwc-text-field label {
                font-weight: 600;
                color: #111827;
            }
            .mulopimfwc-text-field__tags {
                display: flex;
                gap: 6px;
                flex-wrap: wrap;
            }
            .mulopimfwc-text-field__tag {
                font-size: 11px;
                padding: 2px 6px;
                border-radius: 999px;
                background: #e2e8f0;
                color: #475569;
            }
            .mulopimfwc-text-field__tag--pro {
                background: #fee2e2;
                color: #b91c1c;
            }
            .mulopimfwc-text-field input[type="text"],
            .mulopimfwc-text-field textarea {
                width: 100%;
                max-width: 100%;
            }
            .mulopimfwc-text-field__default {
                margin: 6px 0 0;
                font-size: 12px;
                color: #6b7280;
                line-height: 1.5;
            }
            .mulopimfwc-text-field__default strong {
                color: #374151;
                font-weight: 600;
            }
        </style>';

        echo '<div class="mulopimfwc-text-management-intro">
                <div class="mulopimfwc-text-management-intro__header">
                    <span class="mulopimfwc-text-management-intro__eyebrow">' . esc_html__('Text Management', 'multi-location-product-and-inventory-management-pro') . '</span>
                    <h2>' . esc_html__('Text Management Overview', 'multi-location-product-and-inventory-management-pro') . '</h2>
                    <p>' . esc_html__('Update every customer-facing label, prompt, and message from one place. Each section groups related text so it is clear where the copy appears on the storefront.', 'multi-location-product-and-inventory-management-pro') . '</p>
                </div>
                <div class="mulopimfwc-text-management-toggle">
                    <div>
                        <p class="mulopimfwc-text-management-toggle__title">' . esc_html__('Enable Text Management', 'multi-location-product-and-inventory-management-pro') . '</p>
                        <p class="mulopimfwc-text-management-toggle__desc">' . esc_html__('When disabled, custom text overrides are ignored and plugin defaults are used.', 'multi-location-product-and-inventory-management-pro') . '</p>
                    </div>
                    ' . (!$is_premium ? '' : '<input type="hidden" data-checkbox-fallback="true" name="mulopimfwc_display_options[enable_text_management]" value="off">') . '
                    <label class="mulopimfwc_switch ' . (!$is_premium ? 'mulopimfwc_pro_only mulopimfwc-setting-disabled' : '') . '">
                        <input ' . (!$is_premium ? 'disabled ' : '') . 'type="checkbox" id="mulopimfwc-enable-text-management" name="mulopimfwc_display_options[enable_text_management]" value="on" data-text-base-disabled="' . esc_attr($text_management_base_disabled) . '" ' . checked($is_text_management_enabled, true, false) . '>
                        <span class="mulopimfwc_slider round"></span>
                        <span class="mulopimfwc_switch-on">' . esc_html__('On', 'multi-location-product-and-inventory-management-pro') . '</span>
                        <span class="mulopimfwc_switch-off">' . esc_html__('Off', 'multi-location-product-and-inventory-management-pro') . '</span>
                    </label>
                </div>
                <div class="mulopimfwc-text-management-intro__actions">';

        if (!empty($translation_locales)) {
            echo '<div class="mulopimfwc-text-action-card mulopimfwc-text-action-card--translate ' . (!$is_premium ? 'mulopimfwc_pro_only' : '') . '">
                    <div class="mulopimfwc-text-action-card__header">
                        <div class="mulopimfwc-text-action-card__icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                                <path fill="currentColor" d="M12 3l8 4v6c0 5-3.4 8-8 8s-8-3-8-8V7l8-4m0 2.2L6 7.3v5.7c0 3.8 2.5 6 6 6s6-2.2 6-6V7.3l-6-2.1M9 9h2.6l1.4 4.2 1.1-3.2h2.1l-2.4 6h-1.6l-1.4-4.2L9.5 16H8l-2.4-6h2.1l1.1 3.2L9 9z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="mulopimfwc-text-action-title">' . esc_html__('Quick Translate', 'multi-location-product-and-inventory-management-pro') . '</p>
                            <p class="mulopimfwc-text-action-subtitle">' . esc_html__('Apply a preset language pack to all text fields.', 'multi-location-product-and-inventory-management-pro') . '</p>
                        </div>
                    </div>
                    <div class="mulopimfwc-text-action-row ">
                        <select ' . (!$is_premium || !$is_text_management_enabled ? 'disabled' : '') . ' data-text-base-disabled="' . esc_attr($text_management_base_disabled) . '" id="mulopimfwc-text-translate-select" class="mulopimfwc-text-translate-select mulopimfwc-text-management-toggle-target' . esc_attr($text_toggle_disabled_class) . '">
                            <option value="">' . esc_html__('Select language', 'multi-location-product-and-inventory-management-pro') . '</option>';
            foreach ($translation_locales as $locale_code => $locale_data) {
                $label = $locale_data['label'] ?? $locale_code;
                $native = $locale_data['nativeLabel'] ?? '';
                $display = $label;
                if (!empty($native) && $native !== $label) {
                    $display .= ' (' . $native . ')';
                }
                echo '<option value="' . esc_attr($locale_code) . '">' . esc_html($display) . '</option>';
            }
            echo '</select>
                        <button ' . (!$is_premium || !$is_text_management_enabled ? 'disabled' : '') . ' data-text-base-disabled="' . esc_attr($text_management_base_disabled) . '" type="button" id="mulopimfwc-text-translate-apply" class="button button-primary mulopimfwc-text-management-toggle-target' . esc_attr($text_toggle_disabled_class) . '">' . esc_html__('Apply Translation', 'multi-location-product-and-inventory-management-pro') . '</button>
                    </div>
                    <p class="mulopimfwc-text-management-intro__note">' . esc_html__('Applying a language will replace current text values. Save changes to keep them.', 'multi-location-product-and-inventory-management-pro') . '</p>
                </div>';
        }

        echo '<div class="mulopimfwc-text-action-card mulopimfwc-text-action-card--reset ' . (!$is_premium ? 'mulopimfwc_pro_only' : '') . '">
                <div class="mulopimfwc-text-action-card__header">
                    <div class="mulopimfwc-text-action-card__icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                            <path fill="currentColor" d="M12 4a8 8 0 1 1-7.5 11h2.1A6 6 0 1 0 12 6V2l5 4-5 4V6a6 6 0 0 0-5.5 9H4A8 8 0 0 1 12 4z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="mulopimfwc-text-action-title">' . esc_html__('Reset Text', 'multi-location-product-and-inventory-management-pro') . '</p>
                        <p class="mulopimfwc-text-action-subtitle">' . esc_html__('Restore the original defaults for all text fields.', 'multi-location-product-and-inventory-management-pro') . '</p>
                    </div>
                </div>';
        wp_nonce_field('mulopimfwc_reset_text_management_action', 'mulopimfwc_reset_text_management_nonce');
        echo '<button ' . (!$is_premium || !$is_text_management_enabled ? 'disabled' : '') . ' data-text-base-disabled="' . esc_attr($text_management_base_disabled) . '" type="submit" name="mulopimfwc_reset_text_management" value="1" class="button mulopimfwc-text-reset-button mulopimfwc-text-management-toggle-target' . esc_attr($text_toggle_disabled_class) . '" formaction="' . esc_url(menu_page_url('multi-location-product-and-inventory-management-settings', false)) . '" onclick="return confirm(\'' . esc_js(__('Reset all text fields to their default values? This action cannot be undone.', 'multi-location-product-and-inventory-management-pro')) . '\');">
                    ' . esc_html__('Reset Text to Defaults', 'multi-location-product-and-inventory-management-pro') . '
                </button>
                <p class="mulopimfwc-text-management-intro__note">' . esc_html__('Reset only the Text Management fields to their defaults.', 'multi-location-product-and-inventory-management-pro') . '</p>
            </div>
        </div>
    </div>';

        if (!empty($translation_data)) {
            echo '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    const translations = ' . wp_json_encode($translation_data) . ';
                    window.mulopimfwcTextTranslations = translations;
                    const locales = translations.locales || {};
                    const select = document.getElementById("mulopimfwc-text-translate-select");
                    const apply = document.getElementById("mulopimfwc-text-translate-apply");
                    if (!select || !apply || !Object.keys(locales).length) {
                        return;
                    }

                    function applyTranslation() {
                        const localeCode = select.value;
                        if (!localeCode || !locales[localeCode] || !locales[localeCode].values) {
                            window.alert("' . esc_js(__('Please choose a language first.', 'multi-location-product-and-inventory-management-pro')) . '");
                            return;
                        }
                        const locale = locales[localeCode];
                        const label = locale.nativeLabel || locale.label || localeCode;
                        const template = translations.meta && translations.meta.applyConfirm
                            ? translations.meta.applyConfirm
                            : "Apply %s translations to all Text Management fields? This will overwrite your current values.";
                        const message = template.replace("%s", label);
                        if (!window.confirm(message)) {
                            return;
                        }

                        Object.keys(locale.values).forEach(function(key) {
                            const value = locale.values[key];
                            const field = document.getElementById(key);
                            if (field) {
                                field.value = value;
                            }
                            const hidden = document.querySelector(\'[data-manual-hidden=\"true\"][data-manual-for=\"mulopimfwc_display_options[\' + key + \']\"]\');
                            if (hidden) {
                                hidden.value = value;
                            }
                        });

                        select.value = "";
                    }

                    apply.addEventListener("click", applyTranslation);
                    select.addEventListener("change", applyTranslation);
                });
            </script>';
        }

        echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                const tab = document.getElementById("text-management-settings");
                const toggle = document.getElementById("mulopimfwc-enable-text-management");
                if (!tab || !toggle) {
                    return;
                }

                const targets = tab.querySelectorAll(".mulopimfwc-text-management-toggle-target");
                function syncHiddenMirror(field, shouldMirror) {
                    const tagName = field.tagName ? field.tagName.toUpperCase() : "";
                    const inputType = typeof field.type === "string" ? field.type.toLowerCase() : "";
                    if (
                        tagName === "BUTTON" ||
                        (tagName === "INPUT" && ["submit", "button", "reset", "file"].includes(inputType))
                    ) {
                        return;
                    }

                    const name = field.getAttribute("name");
                    if (!name) {
                        return;
                    }

                    function findHiddenMirror(selector, attributeName, attributeValue) {
                        const candidates = tab.querySelectorAll(selector);
                        for (let index = 0; index < candidates.length; index++) {
                            if (candidates[index].getAttribute(attributeName) === attributeValue) {
                                return candidates[index];
                            }
                        }

                        return null;
                    }

                    if (findHiddenMirror(\'input[type="hidden"][data-manual-hidden="true"]\', "data-manual-for", name)) {
                        return;
                    }

                    let hidden = findHiddenMirror(\'input[type="hidden"][data-text-toggle-hidden="true"]\', "data-text-toggle-for", name);

                    if (shouldMirror) {
                        if (!hidden) {
                            hidden = document.createElement("input");
                            hidden.type = "hidden";
                            hidden.name = name;
                            hidden.setAttribute("data-text-toggle-hidden", "true");
                            hidden.setAttribute("data-text-toggle-for", name);
                            field.insertAdjacentElement("afterend", hidden);
                        }
                        hidden.value = field.value || "";
                    } else if (hidden) {
                        hidden.remove();
                    }
                }

                function syncTextManagementState() {
                    const isEnabled = !!toggle.checked;
                    targets.forEach(function(field) {
                        const baseDisabled = field.getAttribute("data-text-base-disabled") === "1";
                        const shouldDisable = !isEnabled || baseDisabled;
                        field.disabled = shouldDisable;
                        field.classList.toggle("mulopimfwc-setting-disabled", !isEnabled);
                        syncHiddenMirror(field, !isEnabled);
                    });
                }

                syncTextManagementState();
                toggle.addEventListener("change", syncTextManagementState);
            });
        </script>';

        foreach ($groups as $group) {
            $title = $group['title'] ?? '';
            $description = $group['description'] ?? '';
            $icon = $group['icon'] ?? '';
            $fields = $group['fields'] ?? [];

            echo '<div class="lwp-settings-section ' . (!$is_premium ? 'mulopimfwc_pro_only' : '') . '"><div class="lwp-settings-box">';
            echo '<div class="mulopimfwc-text-group__header">';
            if (!empty($icon)) {
                echo '<div class="mulopimfwc-text-group__icon">' . $icon . '</div>';
            }
            echo '<div>';
            echo '<h3 class="mulopimfwc-text-group__title">' . esc_html($title) . '</h3>';
            if (!empty($description)) {
                echo '<p class="mulopimfwc-text-group__desc">' . esc_html($description) . '</p>';
            }
            echo '</div></div>';

            if (!empty($fields)) {
                echo '<div class="mulopimfwc-text-fields">';
                foreach ($fields as $field) {
                    if (!is_array($field) || empty($field['key'])) {
                        continue;
                    }
                    $this->render_text_management_field($field, $options, $is_manual_mode, $current_template, $is_text_management_enabled);
                }
                echo '</div>';
            }
            echo '</div></div>';
        }
    }

    private function render_text_management_field(array $field, array $options, bool $is_manual_mode, string $current_template, bool $is_text_management_enabled): void
    {
        $key = $field['key'];
        $label = $field['label'] ?? $key;
        $type = $field['type'] ?? 'text';
        $rows = isset($field['rows']) ? (int) $field['rows'] : 2;
        $default = $field['default'] ?? '';
        $value = isset($options[$key]) ? $options[$key] : $default;
        if (is_string($value) && trim($value) === '') {
            $value = $default;
        }

        $template_only = $field['template_only'] ?? '';
        $pro_only = !empty($field['pro_only']);
        $manual_disable = !empty($field['manual_disable']);
        $is_premium = function_exists('mulopimfwc_premium_feature') ? mulopimfwc_premium_feature() : false;
        $premium_locked = !$is_premium;
        $pro_locked = $pro_only && !$is_premium;
        $manual_locked = $manual_disable && $is_manual_mode;
        $text_management_locked = !$is_text_management_enabled;
        $base_disabled = $premium_locked || $pro_locked || $manual_locked;
        $base_disabled_flag = $base_disabled ? '1' : '0';
        $toggle_disabled_class = $text_management_locked ? ' mulopimfwc-setting-disabled' : '';
        $wrapper_class = 'mulopimfwc-text-field';
        $wrapper_style = '';
        if ($template_only === 'default') {
            $wrapper_class .= ' mulopimfwc-default-template-only';
            $wrapper_style = ($current_template === 'default') ? '' : ' style="display:none;"';
        }

        if ($manual_locked) {
            $this->render_manual_hidden_input($key, $value);
        }
        if ($text_management_locked && !$manual_locked) {
            echo '<input type="hidden" data-text-toggle-hidden="true" data-text-toggle-for="mulopimfwc_display_options[' . esc_attr($key) . ']" name="mulopimfwc_display_options[' . esc_attr($key) . ']" value="' . esc_attr($value) . '">';
        }

        $description = $field['description'] ?? '';
        if ($manual_locked) {
            $description = $description ? $this->append_manual_disabled_note($description) : ltrim($this->append_manual_disabled_note(''));
        }
        if ($text_management_locked) {
            $text_management_note = __('Disabled because Text Management is turned off. Frontend text will use defaults.', 'multi-location-product-and-inventory-management-pro');
            $description = $description ? $description . ' ' . $text_management_note : $text_management_note;
        }

        $tags = [];
        if (!empty($template_only)) {
            $tags[] = sprintf(/* translators: %s: template name */ __('Template: %s', 'multi-location-product-and-inventory-management-pro'), ucfirst(str_replace('-', ' ', $template_only)));
        }
        if ($pro_only) {
            $tags[] = __('Pro', 'multi-location-product-and-inventory-management-pro');
        }

        echo '<div class="' . esc_attr($wrapper_class) . '"' . $wrapper_style . '>';
        echo '<div class="mulopimfwc-text-field__header">';
        echo '<label for="' . esc_attr($key) . '">' . esc_html($label) . '</label>';
        if (!empty($tags)) {
            echo '<div class="mulopimfwc-text-field__tags">';

            echo '</div>';
        }
        echo '</div>';

        if ($pro_locked) {
            echo '<label class="mulopimfwc_pro_only">';
        }

        if ($type === 'textarea') {
            $rows = $rows > 0 ? $rows : 2;
            echo '<textarea class="mulopimfwc-text-management-toggle-target' . esc_attr($toggle_disabled_class) . '" data-text-base-disabled="' . esc_attr($base_disabled_flag) . '" id="' . esc_attr($key) . '" name="mulopimfwc_display_options[' . esc_attr($key) . ']" rows="' . esc_attr($rows) . '"' . disabled( ($base_disabled || $text_management_locked), true, false ) . ' placeholder="' . esc_attr($default) . '">' . esc_textarea($value) . '</textarea>';
        } else {
            echo '<input type="text" id="' . esc_attr($key) . '" name="mulopimfwc_display_options[' . esc_attr($key) . ']" value="' . esc_attr($value) . '" class="regular-text mulopimfwc-text-management-toggle-target' . esc_attr($toggle_disabled_class) . '" data-text-base-disabled="' . esc_attr($base_disabled_flag) . '"' . disabled( ($base_disabled || $text_management_locked), true, false ) . ' placeholder="' . esc_attr($default) . '">';
        }

        if ($pro_locked) {
            echo '</label>';
        }

        if (!empty($description)) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }

        echo '</div>';
    }

    public function render_advance_checkbox($key, $message = null, $disabled = false, $is_free = true)
    {
        global $mulopimfwc_allowed_tags, $mulopimfwc_options;
        $disabled_class = ($disabled && !in_array($key, ['pro', '_pro'], true)) ? ' mulopimfwc-setting-disabled' : '';
        $input_class_attr = ($disabled && !in_array($key, ['pro', '_pro'], true))
            ? ' class="mulopimfwc-setting-disabled"'
            : '';
        $is_checked = isset($mulopimfwc_options[$key]) && $mulopimfwc_options[$key] === "on";
        if (
            $key === 'location_wise_currency'
            && function_exists('mulopimfwc_is_location_wise_currency_enabled')
        ) {
            $is_checked = mulopimfwc_is_location_wise_currency_enabled($mulopimfwc_options);
        }
        if (!in_array($key, ['pro', '_pro'], true)) {
            $this->render_checkbox_unchecked_input('mulopimfwc_display_options[' . $key . ']', $disabled);
        }
        ?>
            <label class="mulopimfwc_switch <?php echo esc_attr($key);
                                            echo $is_free ? '' : ' mulopimfwc_pro_only';
                                            echo $disabled_class; ?>">
                <input <?php echo $disabled ? 'disabled' : ''; ?><?php echo $input_class_attr; ?> type='checkbox' name='mulopimfwc_display_options[<?php echo esc_attr($key); ?>]' value='on' <?php checked($is_checked); ?>>
                <span class="mulopimfwc_slider round"></span>
                <span class="mulopimfwc_switch-on"><?php echo esc_html__('On', 'multi-location-product-and-inventory-management-pro'); ?></span>
                <span class="mulopimfwc_switch-off"><?php echo esc_html__('Off', 'multi-location-product-and-inventory-management-pro'); ?></span>
            </label>
            <?php
            if (isset($message) && !empty($message)) {
                echo "<p class='description' style='max-width: 800px;'>" . wp_kses($message, $mulopimfwc_allowed_tags) . "</p>";
            }
        }

        public function render_advance_select($key, $message = null, $options = [], $disabled = false, $is_free = true)
        {
            global $mulopimfwc_allowed_tags, $mulopimfwc_options;
            ?> <select <?php echo $disabled ? 'disabled' : ''; ?> name='mulopimfwc_display_options[<?php echo esc_attr($key); ?>]'>
                <?php
                foreach ($options as $value => $label) {
                    echo "<option value='" . esc_attr($value) . "' " . selected( $mulopimfwc_options[$key] ?? '', $value, false ) . ">" . esc_html($label) . "</option>";
                }
                ?>
            </select>
            <?php
            if (isset($message) && !empty($message)) {
                echo "<p class='description' style='max-width: 800px;'>" . wp_kses($message, $mulopimfwc_allowed_tags) . "</p>";
            }
        }

        /**
         * Branding Settings Section Callback
         */
        public function branding_settings_section_callback()
        {
            echo '<p>' . esc_html__('Customize the appearance and styling of popups, buttons, and other frontend elements to match your brand identity.', 'multi-location-product-and-inventory-management-pro') . '</p>';
        }

        /**
         * Color Picker UI Helper
         */
        private function render_color_picker($field_name, $current_value, $default_value, $recommended_colors = [])
        {
            $field_id = 'branding_' . $field_name;
            $input_name = 'mulopimfwc_display_options[branding_' . $field_name . ']';
            $value = !empty($current_value) ? $current_value : $default_value;

            // Default recommended colors if not provided
            if (empty($recommended_colors)) {
                $recommended_colors = ['#000000', '#ffffff', '#f5f5f5', '#e0e0e0', '#808080', '#6366f1'];
            }
            ?>
            <div class="mulopimfwc-color-picker-wrapper <?php echo !mulopimfwc_premium_feature() ? 'mulopimfwc_pro_only' : ''; ?>" data-field-id="<?php echo esc_attr($field_id); ?>" style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-bottom: 10px;">
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                    <div class="mulopimfwc-color-preview" style="width: 48px; height: 48px; border-radius: 8px; background: <?php echo esc_attr($value); ?>; border: 2px solid #d1d5db; flex-shrink: 0; cursor: pointer;"></div>
                    <input <?php echo !mulopimfwc_premium_feature() ? 'disabled' : ''; ?> type="color" id="<?php echo esc_attr($field_id); ?>_color" name="<?php echo esc_attr($input_name); ?>" value="<?php echo esc_attr($value); ?>" style="display: none;" />
                    <input <?php echo !mulopimfwc_premium_feature() ? 'disabled' : ''; ?> type="text" id="<?php echo esc_attr($field_id); ?>_hex" value="<?php echo esc_attr($value); ?>" style="flex: 1; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-family: monospace; font-size: 13px;" placeholder="#000000" />
                    <button <?php echo !mulopimfwc_premium_feature() ? 'disabled' : ''; ?> type="button" class="button mulopimfwc-color-reset" data-default="<?php echo esc_attr($default_value); ?>" style="padding: 8px 16px; background: #f3f4f6; border: 1px solid #d1d5db; border-radius: 6px; cursor: pointer;"><?php echo esc_html__('Reset', 'multi-location-product-and-inventory-management-pro'); ?></button>
                </div>
                <div class="mulopimfwc-color-swatches" style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <?php foreach ($recommended_colors as $rec_color) : ?>
                        <div class="mulopimfwc-color-swatch <?php echo !mulopimfwc_premium_feature() ? 'disabled' : ''; ?>" data-color="<?php echo esc_attr($rec_color); ?>" style="width: 32px; height: 32px; border-radius: 50%; background: <?php echo esc_attr($rec_color); ?>; border: 2px solid <?php echo ($rec_color === $value) ? '#6366f1' : '#d1d5db'; ?>; cursor: pointer; transition: all 0.2s;" title="<?php echo esc_attr($rec_color); ?>"></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <script>
                (function() {
                    var wrapper = document.querySelector('[data-field-id="<?php echo esc_js($field_id); ?>"]');
                    if (!wrapper) return;

                    var colorInput = wrapper.querySelector('#<?php echo esc_js($field_id); ?>_color');
                    var hexInput = wrapper.querySelector('#<?php echo esc_js($field_id); ?>_hex');
                    var preview = wrapper.querySelector('.mulopimfwc-color-preview');
                    var resetBtn = wrapper.querySelector('.mulopimfwc-color-reset');
                    var swatches = wrapper.querySelectorAll('.mulopimfwc-color-swatch:not(.disabled)');

                    // Update preview and hex input when color input changes
                    colorInput.addEventListener('change', function() {
                        preview.style.background = this.value;
                        hexInput.value = this.value;
                        updateSwatchBorders();
                    });

                    // Update color input and preview when hex input changes
                    hexInput.addEventListener('change', function() {
                        var val = this.value.trim();
                        if (/^#[0-9A-F]{6}$/i.test(val)) {
                            colorInput.value = val;
                            preview.style.background = val;
                            updateSwatchBorders();
                        }
                    });

                    // Click preview to open color picker
                    preview.addEventListener('click', function() {
                        colorInput.click();
                    });

                    // Reset button
                    if (resetBtn) {
                        resetBtn.addEventListener('click', function() {
                            var defaultVal = this.getAttribute('data-default');
                            colorInput.value = defaultVal;
                            hexInput.value = defaultVal;
                            preview.style.background = defaultVal;
                            updateSwatchBorders();
                        });
                    }

                    // Swatch selection
                    swatches.forEach(function(swatch) {
                        swatch.addEventListener('click', function() {
                            var color = this.getAttribute('data-color');
                            colorInput.value = color;
                            hexInput.value = color;
                            preview.style.background = color;
                            updateSwatchBorders();
                        });
                    });

                    // Update swatch borders based on current value
                    function updateSwatchBorders() {
                        var currentVal = colorInput.value.toLowerCase();
                        swatches.forEach(function(swatch) {
                            var swatchColor = swatch.getAttribute('data-color').toLowerCase();
                            swatch.style.borderColor = (swatchColor === currentVal) ? '#6366f1' : '#d1d5db';
                        });
                    }
                })();
            </script>
        <?php
        }

        /**
         * Branding Field Callbacks
         */
        public function branding_popup_background_field_callback()
        {
            $options = $this->get_display_options();
            $value = isset($options['branding_popup_background']) ? $options['branding_popup_background'] : '#ffffff';
            $recommended = ['#ffffff', '#f9fafb', '#f5f5f5', '#fef3c7', '#e0f2fe', '#fce7f3'];
            $this->render_color_picker('popup_background', $value, '#ffffff', $recommended);
        ?>
            <p class="description"><?php echo esc_html__('Background color for popup/modal panels.', 'multi-location-product-and-inventory-management-pro'); ?></p>
        <?php
        }

        public function branding_popup_text_field_callback()
        {
            $options = $this->get_display_options();
            $value = isset($options['branding_popup_text']) ? $options['branding_popup_text'] : '#1d1b16';
            $recommended = ['#1d1b16', '#000000', '#1f2937', '#374151', '#4b5563', '#6b7280'];
            $this->render_color_picker('popup_text', $value, '#1d1b16', $recommended);
        ?>
            <p class="description"><?php echo esc_html__('Text color for popup/modal content.', 'multi-location-product-and-inventory-management-pro'); ?></p>
        <?php
        }

        public function branding_popup_overlay_field_callback()
        {
            $options = $this->get_display_options();
            $value = isset($options['branding_popup_overlay']) ? $options['branding_popup_overlay'] : 'rgba(15, 23, 42, 0.65)';
        ?>
            <div class="<?php echo !mulopimfwc_premium_feature() ? 'mulopimfwc_pro_only' : ''; ?>">
                <input <?php echo !mulopimfwc_premium_feature() ? 'disabled' : ''; ?> type="text" name="mulopimfwc_display_options[branding_popup_overlay]" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="rgba(15, 23, 42, 0.65)" />
                <p class="description"><?php echo esc_html__('Background color for popup overlay (supports rgba format).', 'multi-location-product-and-inventory-management-pro'); ?></p>
            </div>
        <?php
        }

        public function branding_primary_color_field_callback()
        {
            $options = $this->get_display_options();
            $value = isset($options['branding_primary_color']) ? $options['branding_primary_color'] : '#2a9d8f';
            $recommended = ['#2a9d8f', '#2563eb', '#059669', '#0d9488', '#14b8a6', '#6366f1'];
            $this->render_color_picker('primary_color', $value, '#2a9d8f', $recommended);
        ?>
            <p class="description"><?php echo esc_html__('Primary color used for buttons, gradients, and main interactive elements. This replaces the button background color.', 'multi-location-product-and-inventory-management-pro'); ?></p>
        <?php
        }

        public function branding_secondary_color_field_callback()
        {
            $options = $this->get_display_options();
            $value = isset($options['branding_secondary_color']) ? $options['branding_secondary_color'] : '#f4a261';
            $recommended = ['#f4a261', '#f26419', '#f59e0b', '#ea580c', '#dc2626', '#ef4444'];
            $this->render_color_picker('secondary_color', $value, '#f4a261', $recommended);
        ?>
            <p class="description"><?php echo esc_html__('Secondary color used for button hover states, gradients, and complementary elements. This replaces the button hover background color.', 'multi-location-product-and-inventory-management-pro'); ?></p>
        <?php
        }

        public function branding_border_color_field_callback()
        {
            $options = $this->get_display_options();
            $value = isset($options['branding_border_color']) ? $options['branding_border_color'] : '#e7e0d6';
            $recommended = ['#e7e0d6', '#e5e7eb', '#d1d5db', '#9ca3af', '#6b7280', '#4b5563'];
            $this->render_color_picker('border_color', $value, '#e7e0d6', $recommended);
        ?>
            <p class="description"><?php echo esc_html__('Border color for popups, cards, inputs, and other bordered elements.', 'multi-location-product-and-inventory-management-pro'); ?></p>
        <?php
        }

        public function branding_button_text_field_callback()
        {
            $options = $this->get_display_options();
            $value = isset($options['branding_button_text']) ? $options['branding_button_text'] : '#ffffff';
            $recommended = ['#ffffff', '#f9fafb', '#f3f4f6', '#e5e7eb', '#ffffff', '#ffffff'];
            $this->render_color_picker('button_text', $value, '#ffffff', $recommended);
        ?>
            <p class="description"><?php echo esc_html__('Text color for buttons.', 'multi-location-product-and-inventory-management-pro'); ?></p>
        <?php
        }

        public function branding_font_size_field_callback()
        {
            $options = $this->get_display_options();
            $value = isset($options['branding_font_size']) ? $options['branding_font_size'] : '15px';
        ?>
            <div class="<?php echo !mulopimfwc_premium_feature() ? 'mulopimfwc_pro_only' : ''; ?>">
                <input <?php echo !mulopimfwc_premium_feature() ? 'disabled' : ''; ?> type="text" name="mulopimfwc_display_options[branding_font_size]" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="15px" />
                <p class="description"><?php echo esc_html__('Base font size (e.g., 15px, 16px, 1rem).', 'multi-location-product-and-inventory-management-pro'); ?></p>
            </div>
        <?php
        }

        public function branding_font_family_field_callback()
        {
            $options = $this->get_display_options();
            $value = isset($options['branding_font_family']) ? $options['branding_font_family'] : 'Space Grotesk';
            $font_options = [
                'Space Grotesk' => 'Space Grotesk',
                'Libre Baskerville' => 'Libre Baskerville',
                'Arial' => 'Arial',
                'Helvetica' => 'Helvetica',
                'Georgia' => 'Georgia',
                'Times New Roman' => 'Times New Roman',
                'Verdana' => 'Verdana',
                'Roboto' => 'Roboto',
                'Open Sans' => 'Open Sans',
                'Lato' => 'Lato',
                'Montserrat' => 'Montserrat',
                'Poppins' => 'Poppins',
            ];
        ?>
            <div class="<?php echo !mulopimfwc_premium_feature() ? 'mulopimfwc_pro_only' : ''; ?>">

                <select <?php echo !mulopimfwc_premium_feature() ? 'disabled' : ''; ?> name="mulopimfwc_display_options[branding_font_family]" class="regular-text">
                    <?php foreach ($font_options as $font => $label) : ?>
                        <option value="<?php echo esc_attr($font); ?>" <?php selected($value, $font); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php echo esc_html__('Font family for popup and frontend elements.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            </div>
        <?php
        }

        public function branding_box_shadow_field_callback()
        {
            $options = $this->get_display_options();
            $value = isset($options['branding_box_shadow']) ? $options['branding_box_shadow'] : '0 18px 45px rgba(24, 24, 24, 0.18)';
        ?>
            <div class="<?php echo !mulopimfwc_premium_feature() ? 'mulopimfwc_pro_only' : ''; ?>">
                <input <?php echo !mulopimfwc_premium_feature() ? 'disabled' : ''; ?> type="text" name="mulopimfwc_display_options[branding_box_shadow]" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="0 18px 45px rgba(24, 24, 24, 0.18)" />
                <p class="description"><?php echo esc_html__('Box shadow for popups and cards (e.g., 0 18px 45px rgba(24, 24, 24, 0.18)).', 'multi-location-product-and-inventory-management-pro'); ?></p>
            </div>
        <?php
        }

        public function branding_border_radius_field_callback()
        {
            $options = $this->get_display_options();
            $value = isset($options['branding_border_radius']) ? $options['branding_border_radius'] : '20px';
        ?>
            <div class="<?php echo !mulopimfwc_premium_feature() ? 'mulopimfwc_pro_only' : ''; ?>">
                <input <?php echo !mulopimfwc_premium_feature() ? 'disabled' : ''; ?> type="text" name="mulopimfwc_display_options[branding_border_radius]" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="20px" />
                <p class="description"><?php echo esc_html__('Border radius for popups and buttons (e.g., 20px, 10px, 5px).', 'multi-location-product-and-inventory-management-pro'); ?></p>
            </div>
    <?php
        }
    }
