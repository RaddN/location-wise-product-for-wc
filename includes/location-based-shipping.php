<?php
/**
 * Location-Based Shipping Management
 * 
 * Handles shipping zone and method filtering based on selected store location
 * 
 * @package Multi Location Product & Inventory Management for WooCommerce
 * @since 1.1.2.27
 */

if (!defined('ABSPATH')) {
    exit;
}

class MULOPIMFWC_Location_Based_Shipping
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // Check if location-based shipping is enabled
        if (!$this->is_enabled()) {
            return;
        }

        // Add location settings to shipping zones
        add_action('woocommerce_shipping_zone_after_method_settings', array($this, 'add_location_settings_to_method'), 10, 3);
        
        // Save location settings for shipping methods
        add_action('woocommerce_shipping_zone_method_added', array($this, 'save_method_location_settings'), 10, 3);
        add_action('woocommerce_shipping_zone_method_status_toggled', array($this, 'save_method_location_settings'), 10, 3);
        
        // Filter available shipping methods based on location
        add_filter('woocommerce_package_rates', array($this, 'filter_shipping_methods_by_location'), 100, 2);
        
        // Add location info to shipping zones list
        add_filter('woocommerce_shipping_zone_methods_html', array($this, 'add_location_info_to_zone_methods'), 10, 2);
        
        // Add location column to shipping zones table
        add_filter('woocommerce_shipping_zone_methods_table_headers', array($this, 'add_location_column_header'));
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers for saving method locations
        add_action('wp_ajax_mulopimfwc_save_shipping_method_locations', array($this, 'ajax_save_method_locations'));
        
        // Refresh shipping methods when location changes
        add_action('wp_footer', array($this, 'add_location_change_shipping_refresh'));
        
        // Add bulk location assignment for shipping zones
        add_action('woocommerce_shipping_zones_after_zones_list', array($this, 'add_bulk_location_settings'));
    }

    /**
     * Check if location-based shipping is enabled
     * 
     * @return bool
     */
    private function is_enabled()
    {
        global $mulopimfwc_options;
        $options = is_array($mulopimfwc_options ?? null)
            ? $mulopimfwc_options
            : get_option('mulopimfwc_display_options', []);
        return mulopimfwc_is_location_shipping_enabled($options);
    }

    /**
     * Get current selected location
     * 
     * @return string|false
     */
    private function get_current_location()
    {
        // First check if cookie is set
        $cookie_location = mulopimfwc_get_store_location_cookie();
        if (!empty($cookie_location)) {
            return $cookie_location;
        }
        
        // If cookie is empty, check if popup is enabled
        global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
        $enable_popup = isset($options['enable_popup']) ? $options['enable_popup'] : 'off';
        
        // If popup is disabled, use default location
        if ($enable_popup === 'off') {
            $default_location = mulopimfwc_get_default_location_value($options);
            if (!empty($default_location)) {
                return $default_location;
            }
        }
        
        return false;
    }

    /**
     * Get all store locations
     * 
     * @return array
     */
    private function get_all_locations()
    {
        $locations = get_terms(array(
            'taxonomy' => 'mulopimfwc_store_location',
            'hide_empty' => false,
        ));

        if (is_wp_error($locations)) {
            return array();
        }

        return $locations;
    }

    /**
     * Add location settings to shipping method settings
     * 
     * @param int $instance_id Method instance ID
     * @param object $method Shipping method
     * @param int $zone_id Zone ID
     */
    public function add_location_settings_to_method($instance_id, $method, $zone_id)
    {
        $locations = $this->get_all_locations();
        
        if (empty($locations)) {
            return;
        }

        $selected_locations = $this->get_method_locations($instance_id);
        
        ?>
        <tr valign="top" class="mulopimfwc-shipping-locations-row">
            <th scope="row" class="titledesc">
                <?php esc_html_e('Available Locations', 'multi-location-product-and-inventory-management'); ?>
                <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('Select which store locations can use this shipping method. Leave empty to allow all locations.', 'multi-location-product-and-inventory-management'); ?>"></span>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text">
                        <span><?php esc_html_e('Store Locations', 'multi-location-product-and-inventory-management'); ?></span>
                    </legend>
                    
                    <label>
                        <input type="checkbox" 
                               name="mulopimfwc_shipping_locations_all" 
                               id="mulopimfwc_shipping_locations_all_<?php echo esc_attr($instance_id); ?>"
                               value="1"
                               <?php checked(empty($selected_locations)); ?>>
                        <strong><?php esc_html_e('All Locations', 'multi-location-product-and-inventory-management'); ?></strong>
                    </label>
                    <br><br>
                    
                    <div id="mulopimfwc_shipping_locations_list_<?php echo esc_attr($instance_id); ?>" 
                         style="<?php echo empty($selected_locations) ? 'display:none;' : ''; ?>">
                        <?php $this->render_location_checkboxes($locations, $selected_locations, $instance_id); ?>
                    </div>
                    
                    <input type="hidden" 
                           name="mulopimfwc_shipping_method_instance_id" 
                           value="<?php echo esc_attr($instance_id); ?>">
                </fieldset>
            </td>
        </tr>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#mulopimfwc_shipping_locations_all_<?php echo esc_js($instance_id); ?>').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#mulopimfwc_shipping_locations_list_<?php echo esc_js($instance_id); ?>').slideUp();
                    $('#mulopimfwc_shipping_locations_list_<?php echo esc_js($instance_id); ?> input[type="checkbox"]').prop('checked', false);
                } else {
                    $('#mulopimfwc_shipping_locations_list_<?php echo esc_js($instance_id); ?>').slideDown();
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Render location checkboxes with hierarchical structure
     * 
     * @param array $locations All locations
     * @param array $selected_locations Selected location slugs
     * @param int $instance_id Method instance ID
     * @param int $parent_id Parent location ID
     * @param int $level Indentation level
     */
    private function render_location_checkboxes($locations, $selected_locations, $instance_id, $parent_id = 0, $level = 0)
    {
        foreach ($locations as $location) {
            if ($location->parent != $parent_id) {
                continue;
            }

            $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);
            $checked = in_array(rawurldecode($location->slug), $selected_locations);
            
            ?>
            <label style="display: block; margin: 5px 0;">
                <?php echo wp_kses_post($indent); ?>
                <input type="checkbox" 
                       name="mulopimfwc_shipping_locations[]" 
                       value="<?php echo esc_attr(rawurldecode($location->slug)); ?>"
                       <?php checked($checked); ?>>
                <?php echo esc_html($location->name); ?>
                <span class="description">(<?php echo esc_html($location->count); ?> <?php esc_html_e('products', 'multi-location-product-and-inventory-management'); ?>)</span>
            </label>
            <?php
            
            // Recursively render child locations
            $this->render_location_checkboxes($locations, $selected_locations, $instance_id, $location->term_id, $level + 1);
        }
    }

    /**
     * Save location settings for shipping method
     * 
     * @param int $instance_id Method instance ID
     * @param string $type Method type
     * @param int $zone_id Zone ID
     */
    public function save_method_location_settings($instance_id, $type = '', $zone_id = 0)
    {
        // Check if this is a settings save request
        if (!isset($_POST['mulopimfwc_shipping_method_instance_id'])) {
            return;
        }

        $submitted_instance_id = intval($_POST['mulopimfwc_shipping_method_instance_id']);
        
        if ($submitted_instance_id !== $instance_id) {
            return;
        }

        // Check nonce
        if (!isset($_POST['woocommerce_shipping_method_nonce']) || 
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['woocommerce_shipping_method_nonce'])), 'woocommerce_shipping_method')) {
            return;
        }

        $locations = array();
        
        // Check if "All Locations" is selected
        $all_locations = isset($_POST['mulopimfwc_shipping_locations_all']) ? 1 : 0;
        
        if (!$all_locations && isset($_POST['mulopimfwc_shipping_locations'])) {
            $locations = array_map('sanitize_text_field', wp_unslash($_POST['mulopimfwc_shipping_locations']));
        }

        // Save to database
        update_option('mulopimfwc_shipping_method_locations_' . $instance_id, $locations);
    }

    /**
     * Get locations assigned to a shipping method
     * 
     * @param int $instance_id Method instance ID
     * @return array Array of location slugs
     */
    private function get_method_locations($instance_id)
    {
        $locations = get_option('mulopimfwc_shipping_method_locations_' . $instance_id, array());
        
        if (!is_array($locations)) {
            return array();
        }

        return $locations;
    }

    /**
     * Filter shipping methods based on selected location
     * 
     * @param array $rates Available shipping rates
     * @param array $package Shipping package
     * @return array Filtered shipping rates
     */
    public function filter_shipping_methods_by_location($rates, $package)
    {
        $current_location = $this->get_current_location();

        // If no location selected, don't filter
        if (!$current_location || $current_location === 'all-products') {
            return $rates;
        }

        $filtered_rates = array();

        foreach ($rates as $rate_id => $rate) {
            // Get instance ID from rate ID
            $instance_id = $this->get_instance_id_from_rate($rate);
            
            if (!$instance_id) {
                // If we can't get instance ID, include the rate
                $filtered_rates[$rate_id] = $rate;
                continue;
            }

            // Get locations for this method
            $method_locations = $this->get_method_locations($instance_id);

            // If no locations set, method is available for all locations
            if (empty($method_locations)) {
                $filtered_rates[$rate_id] = $rate;
                continue;
            }

            // Check if current location is in the method's locations
            if (in_array($current_location, $method_locations)) {
                $filtered_rates[$rate_id] = $rate;
            }
        }

        return $filtered_rates;
    }

    /**
     * Extract instance ID from shipping rate
     * 
     * @param object $rate Shipping rate object
     * @return int|false Instance ID or false
     */
    private function get_instance_id_from_rate($rate)
    {
        // Rate ID format is typically: method_id:instance_id
        if (isset($rate->instance_id)) {
            return intval($rate->instance_id);
        }

        // Try to extract from rate ID
        $rate_id_parts = explode(':', $rate->id);
        if (count($rate_id_parts) >= 2) {
            return intval($rate_id_parts[1]);
        }

        return false;
    }

    /**
     * Add location information to zone methods HTML
     * 
     * @param string $html Existing HTML
     * @param object $method Shipping method
     * @return string Modified HTML
     */
    public function add_location_info_to_zone_methods($html, $method)
    {
        $instance_id = $method->instance_id;
        $locations = $this->get_method_locations($instance_id);

        if (empty($locations)) {
            $location_html = '<span class="mulopimfwc-shipping-all-locations" style="color: #2ea2cc; font-size: 11px;">(' . esc_html__('All locations', 'multi-location-product-and-inventory-management') . ')</span>';
        } else {
            $location_names = array();
            foreach ($locations as $location_slug) {
                // OPTIMIZED: Use cached method instead of direct get_term_by
                $term = get_term_by('slug', $location_slug, 'mulopimfwc_store_location');
                if ($term && !is_wp_error($term)) {
                    $location_names[] = $term->name;
                }
            }
            
            $location_count = count($location_names);
            if ($location_count > 3) {
                $display_names = array_slice($location_names, 0, 3);
                $location_text = implode(', ', $display_names) . ' ' . sprintf(esc_html__('and %d more', 'multi-location-product-and-inventory-management'), $location_count - 3);
            } else {
                $location_text = implode(', ', $location_names);
            }
            
            $location_html = '<span class="mulopimfwc-shipping-locations" style="color: #666; font-size: 11px;" title="' . esc_attr(implode(', ', $location_names)) . '">📍 ' . esc_html($location_text) . '</span>';
        }

        return $html . ' ' . $location_html;
    }

    /**
     * Add location column header to shipping zones table
     * 
     * @param array $headers Existing headers
     * @return array Modified headers
     */
    public function add_location_column_header($headers)
    {
        // This would require modifying WooCommerce's shipping zones table structure
        // For now, we display location info inline with the method name
        return $headers;
    }

    /**
     * Enqueue admin scripts
     * 
     * @param string $hook Current admin page
     */
    public function enqueue_admin_scripts($hook)
    {
        // Only on shipping settings page
        if ($hook !== 'woocommerce_page_wc-settings' || !isset($_GET['tab']) || $_GET['tab'] !== 'shipping') {
            return;
        }

        wp_enqueue_style(
            'mulopimfwc-shipping-admin',
            plugin_dir_url(__FILE__) . '../assets/css/shipping-admin.css',
            array(),
            '1.1.2.27'
        );

        wp_enqueue_script(
            'mulopimfwc-shipping-admin',
            plugin_dir_url(__FILE__) . '../assets/js/shipping-admin.js',
            array('jquery'),
            '1.1.2.27',
            true
        );

        wp_localize_script('mulopimfwc-shipping-admin', 'mulopimfwcShipping', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mulopimfwc_shipping_nonce'),
            'i18n' => array(
                'selectLocations' => __('Select Locations', 'multi-location-product-and-inventory-management'),
                'allLocations' => __('All Locations', 'multi-location-product-and-inventory-management'),
                'noLocations' => __('No locations assigned', 'multi-location-product-and-inventory-management'),
                'saveSuccess' => __('Locations saved successfully', 'multi-location-product-and-inventory-management'),
                'saveError' => __('Error saving locations', 'multi-location-product-and-inventory-management'),
            )
        ));
    }

    /**
     * AJAX handler for saving method locations
     */
    public function ajax_save_method_locations()
    {
        check_ajax_referer('mulopimfwc_shipping_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied', 'multi-location-product-and-inventory-management')));
        }

        $instance_id = isset($_POST['instance_id']) ? intval($_POST['instance_id']) : 0;
        $locations = isset($_POST['locations']) ? array_map('sanitize_text_field', wp_unslash($_POST['locations'])) : array();

        if (!$instance_id) {
            wp_send_json_error(array('message' => __('Invalid instance ID', 'multi-location-product-and-inventory-management')));
        }

        update_option('mulopimfwc_shipping_method_locations_' . $instance_id, $locations);

        wp_send_json_success(array(
            'message' => __('Locations saved successfully', 'multi-location-product-and-inventory-management'),
            'locations' => $locations
        ));
    }

    /**
     * Add JavaScript to refresh shipping methods when location changes
     */
    public function add_location_change_shipping_refresh()
    {
        if (!is_checkout()) {
            return;
        }

        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Listen for location changes
            $(document.body).on('mulopimfwc_location_changed', function(e, newLocation) {
                // Trigger shipping calculation update
                $('body').trigger('update_checkout');
                
                // Show notice
                var notice = '<div class="woocommerce-message">' +
                    '<?php esc_html_e('Shipping methods updated based on your location selection.', 'multi-location-product-and-inventory-management'); ?>' +
                    '</div>';
                
                $('.woocommerce-notices-wrapper').first().html(notice);
                
                // Scroll to shipping methods
                $('html, body').animate({
                    scrollTop: $('#shipping_method').offset().top - 100
                }, 500);
            });
        });
        </script>
        <?php
    }

    /**
     * Add bulk location settings for shipping zones
     */
    public function add_bulk_location_settings()
    {
        $locations = $this->get_all_locations();
        
        if (empty($locations)) {
            return;
        }

        ?>
        <div class="mulopimfwc-bulk-shipping-locations" style="margin-top: 20px; padding: 20px; background: #f9f9f9; border: 1px solid #ddd;">
            <h3><?php esc_html_e('Bulk Location Assignment', 'multi-location-product-and-inventory-management'); ?></h3>
            <p class="description">
                <?php esc_html_e('Quickly assign locations to all shipping methods in a zone.', 'multi-location-product-and-inventory-management'); ?>
            </p>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e('Select Zone', 'multi-location-product-and-inventory-management'); ?></label>
                    </th>
                    <td>
                        <select id="mulopimfwc_bulk_zone_select" style="min-width: 200px;">
                            <option value=""><?php esc_html_e('Select a zone...', 'multi-location-product-and-inventory-management'); ?></option>
                            <?php
                            $zones = WC_Shipping_Zones::get_zones();
                            foreach ($zones as $zone_data) {
                                $zone = new WC_Shipping_Zone($zone_data['id']);
                                echo '<option value="' . esc_attr($zone->get_id()) . '">' . esc_html($zone->get_zone_name()) . '</option>';
                            }
                            ?>
                        </select>
                    </td>
                </tr>
                <tr id="mulopimfwc_bulk_locations_row" style="display: none;">
                    <th scope="row">
                        <label><?php esc_html_e('Assign Locations', 'multi-location-product-and-inventory-management'); ?></label>
                    </th>
                    <td>
                        <fieldset>
                            <?php foreach ($locations as $location): ?>
                                <label style="display: block; margin: 5px 0;">
                                    <input type="checkbox" 
                                           name="mulopimfwc_bulk_locations[]" 
                                           value="<?php echo esc_attr(rawurldecode($location->slug)); ?>">
                                    <?php echo esc_html($location->name); ?>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                        <p class="description">
                            <?php esc_html_e('Selected locations will be assigned to all shipping methods in the zone.', 'multi-location-product-and-inventory-management'); ?>
                        </p>
                    </td>
                </tr>
                <tr id="mulopimfwc_bulk_apply_row" style="display: none;">
                    <th></th>
                    <td>
                        <button type="button" 
                                id="mulopimfwc_bulk_apply_btn" 
                                class="button button-primary">
                            <?php esc_html_e('Apply to All Methods in Zone', 'multi-location-product-and-inventory-management'); ?>
                        </button>
                        <span class="spinner" style="float: none; margin: 0 10px;"></span>
                    </td>
                </tr>
            </table>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#mulopimfwc_bulk_zone_select').on('change', function() {
                if ($(this).val()) {
                    $('#mulopimfwc_bulk_locations_row, #mulopimfwc_bulk_apply_row').show();
                } else {
                    $('#mulopimfwc_bulk_locations_row, #mulopimfwc_bulk_apply_row').hide();
                }
            });

            $('#mulopimfwc_bulk_apply_btn').on('click', function() {
                var $btn = $(this);
                var $spinner = $btn.next('.spinner');
                var zoneId = $('#mulopimfwc_bulk_zone_select').val();
                var locations = [];
                
                $('input[name="mulopimfwc_bulk_locations[]"]:checked').each(function() {
                    locations.push($(this).val());
                });

                if (!zoneId) {
                    alert('<?php esc_html_e('Please select a zone', 'multi-location-product-and-inventory-management'); ?>');
                    return;
                }

                $btn.prop('disabled', true);
                $spinner.addClass('is-active');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mulopimfwc_bulk_assign_locations',
                        nonce: '<?php echo esc_js(wp_create_nonce('mulopimfwc_shipping_nonce')); ?>',
                        zone_id: zoneId,
                        locations: locations
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert(response.data.message);
                        }
                    },
                    error: function() {
                        alert('<?php esc_html_e('An error occurred', 'multi-location-product-and-inventory-management'); ?>');
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                        $spinner.removeClass('is-active');
                    }
                });
            });
        });
        </script>
        <?php
    }
}

// Initialize the class
function mulopimfwc_init_location_based_shipping()
{
    new MULOPIMFWC_Location_Based_Shipping();
}
add_action('plugins_loaded', 'mulopimfwc_init_location_based_shipping');

// AJAX handler for bulk location assignment
add_action('wp_ajax_mulopimfwc_bulk_assign_locations', 'mulopimfwc_bulk_assign_locations');
function mulopimfwc_bulk_assign_locations()
{
    check_ajax_referer('mulopimfwc_shipping_nonce', 'nonce');

    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(array('message' => __('Permission denied', 'multi-location-product-and-inventory-management')));
    }

    $zone_id = isset($_POST['zone_id']) ? intval($_POST['zone_id']) : 0;
    $locations = isset($_POST['locations']) ? array_map('sanitize_text_field', wp_unslash($_POST['locations'])) : array();

    if (!$zone_id) {
        wp_send_json_error(array('message' => __('Invalid zone ID', 'multi-location-product-and-inventory-management')));
    }

    $zone = new WC_Shipping_Zone($zone_id);
    $methods = $zone->get_shipping_methods();
    $count = 0;

    foreach ($methods as $method) {
        $instance_id = $method->instance_id;
        update_option('mulopimfwc_shipping_method_locations_' . $instance_id, $locations);
        $count++;
    }

    wp_send_json_success(array(
        'message' => sprintf(
            __('Locations assigned to %d shipping method(s) successfully', 'multi-location-product-and-inventory-management'),
            $count
        )
    ));
}

// AJAX handler for getting method locations
add_action('wp_ajax_mulopimfwc_get_method_locations', 'mulopimfwc_get_method_locations');
function mulopimfwc_get_method_locations()
{
    check_ajax_referer('mulopimfwc_shipping_nonce', 'nonce');

    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(array('message' => __('Permission denied', 'multi-location-product-and-inventory-management')));
    }

    $instance_id = isset($_POST['instance_id']) ? intval($_POST['instance_id']) : 0;

    if (!$instance_id) {
        wp_send_json_error(array('message' => __('Invalid instance ID', 'multi-location-product-and-inventory-management')));
    }

    // Get all locations
    $all_locations = get_terms(array(
        'taxonomy' => 'mulopimfwc_store_location',
        'hide_empty' => false,
    ));

    if (is_wp_error($all_locations)) {
        wp_send_json_error(array('message' => $all_locations->get_error_message()));
    }

    // Get selected locations for this method
    $selected_locations = get_option('mulopimfwc_shipping_method_locations_' . $instance_id, array());

    // Format locations
    $locations_data = array();
    foreach ($all_locations as $location) {
        $locations_data[] = array(
            'slug' => rawurldecode($location->slug),
            'name' => $location->name,
            'count' => $location->count
        );
    }

    wp_send_json_success(array(
        'locations' => $locations_data,
        'selected' => $selected_locations
    ));
}
