<?php

if (!defined('ABSPATH')) exit;

class MULOPIMFWC_Admin
{
    public function __construct()
    {
        add_action('init', [$this, 'register_store_location_taxonomy']);

        add_action('admin_enqueue_scripts', [$this, 'mulopimfwc_admin_assets']);

        // Hook to add the settings page
        add_action('admin_menu', [$this, 'add_settings_page']);
        // Add custom column to orders table
        add_filter('manage_woocommerce_page_wc-orders_columns', array($this, 'add_location_column'), 20);
        add_action('manage_woocommerce_page_wc-orders_custom_column', array($this, 'display_location_column_content'), 20, 2);
        // Add metabox to order details
        add_action('add_meta_boxes', array($this, 'add_location_metabox'));

        // Add custom fields to location taxonomy
        add_action('mulopimfwc_store_location_add_form_fields', array($this, 'add_location_fields'));
        add_action('mulopimfwc_store_location_edit_form_fields', array($this, 'edit_location_fields'), 10, 2);
        add_action('created_mulopimfwc_store_location', array($this, 'save_location_fields'), 10, 2);
        add_action('edited_mulopimfwc_store_location', array($this, 'save_location_fields'), 10, 2);

        // Add custom columns to location taxonomy table
        add_filter('manage_edit-mulopimfwc_store_location_columns', array($this, 'add_location_taxonomy_columns'));
        add_filter('manage_mulopimfwc_store_location_custom_column', array($this, 'add_location_taxonomy_column_content'), 10, 3);
        add_filter('manage_edit-mulopimfwc_store_location_sortable_columns', array($this, 'add_location_taxonomy_sortable_columns'));



        // Add AJAX actions for export
        add_action('wp_ajax_mulopimfwc_export_dashboard_report', array(new MULOPIMFWC_Dashboard(), 'export_dashboard_report'));

        // Shortcode for location status
        // [mulopimfwc_location_status id="123"]

        add_shortcode('mulopimfwc_location_status', [$this, 'shortcode_location_status']);
    }

    public function shortcode_location_status($atts)
    {
        $atts = shortcode_atts(['id' => 0], $atts, 'mulopimfwc_location_status');
        $term_id = absint($atts['id']);
        if (!$term_id || !get_term($term_id, 'mulopimfwc_store_location')) return '';

        $badge = $this->render_status_badge($term_id);
        return '<div class="mulopimfwc-location-status">' . $badge . '</div>';
    }


    public function mulopimfwc_admin_assets($hook)
    {
        // Only load on our taxonomy screens
        $screen = get_current_screen();
        if (empty($screen) || $screen->taxonomy !== 'mulopimfwc_store_location') {
            return;
        }

        // WordPress media library
        wp_enqueue_media();

        // Small inline script (no separate file needed)
        $js = '
        (function($){
            function openMedia(callback, multiple, title){
            const frame = wp.media({
                title: title || "Select media",
                multiple: !!multiple,
                library: { type: "image" }
            });
            frame.on("select", function(){
                const selection = frame.state().get("selection");
                callback(selection);
            });
            frame.open();
            }

            $(document).on("click",".mulopimfwc-upload-logo", function(e){
            e.preventDefault();
            const $wrap = $(this).closest(".mulopimfwc-media-wrap");
            openMedia(function(sel){
                const item = sel.first().toJSON();
                $wrap.find(".mulopimfwc-logo-id").val(item.id);
                $wrap.find(".mulopimfwc-logo-preview").html("<img src=\'"+(item.sizes?.thumbnail?.url || item.url)+"\' style=\'max-width:80px;height:auto;border:1px solid #ddd;border-radius:4px;\'>");
            }, false, "Select Logo");
            });

            $(document).on("click",".mulopimfwc-remove-logo", function(e){
            e.preventDefault();
            const $wrap = $(this).closest(".mulopimfwc-media-wrap");
            $wrap.find(".mulopimfwc-logo-id").val("");
            $wrap.find(".mulopimfwc-logo-preview").empty();
            });

            $(document).on("click",".mulopimfwc-upload-gallery", function(e){
            e.preventDefault();
            const $wrap = $(this).closest(".mulopimfwc-media-wrap");
            openMedia(function(sel){
                const ids = [];
                const thumbs = [];
                sel.each(function(m){
                const j = m.toJSON();
                ids.push(j.id);
                thumbs.push("<img src=\'"+(j.sizes?.thumbnail?.url || j.url)+"\' style=\'width:60px;height:auto;margin:2px;border:1px solid #ddd;border-radius:3px;\'>");
                });
                // merge with existing ids if any
                const prev = ($wrap.find(".mulopimfwc-gallery-ids").val() || "").split(",").filter(Boolean);
                const all = prev.concat(ids).filter((v,i,a)=>a.indexOf(v)===i);
                $wrap.find(".mulopimfwc-gallery-ids").val(all.join(","));
                $wrap.find(".mulopimfwc-gallery-preview").html(thumbs.join(""));
            }, true, "Select Gallery Images");
            });

            $(document).on("click",".mulopimfwc-clear-gallery", function(e){
            e.preventDefault();
            const $wrap = $(this).closest(".mulopimfwc-media-wrap");
            $wrap.find(".mulopimfwc-gallery-ids").val("");
            $wrap.find(".mulopimfwc-gallery-preview").empty();
            });
        })(jQuery);
        ';
        wp_add_inline_script('jquery', $js);
    }

    /** =========================================
     *  Helpers: WooCommerce data sources
     *  =========================================*/
    public function get_shipping_zones_options()
    {
        if (!class_exists('WC_Shipping_Zones')) return array();
        $zones = \WC_Shipping_Zones::get_zones(); // excludes "Locations not covered by your other zones"
        // Add "Rest of the world" pseudo zone
        $default_zone = new \WC_Shipping_Zone(0);
        $zones[0] = array(
            'id' => 0,
            'zone_name' => $default_zone->get_zone_name(),
            'shipping_methods' => $default_zone->get_shipping_methods()
        );

        $out = array();
        foreach ($zones as $z) {
            $out[(int)$z['id']] = $z['zone_name'];
        }
        return $out;
    }

    public function get_shipping_methods_grouped_by_zone()
    {
        if (!class_exists('WC_Shipping_Zones')) return array();
        $zones = \WC_Shipping_Zones::get_zones();
        $default_zone = new \WC_Shipping_Zone(0);
        $zones[0] = array(
            'id' => 0,
            'zone_name' => $default_zone->get_zone_name(),
            'shipping_methods' => $default_zone->get_shipping_methods()
        );

        $out = array(); // [zone_id => [instance_id => label]]
        foreach ($zones as $z) {
            $zone_id = (int) $z['id'];
            $out[$zone_id] = array();
            foreach ($z['shipping_methods'] as $method) {
                // Show only enabled instances
                if (isset($method->enabled) && $method->enabled === 'yes') {
                    $label = $method->get_title() . ' (' . $method->id . ')';
                    $out[$zone_id][$method->instance_id] = $label;
                }
            }
        }
        return $out;
    }

    public function get_payment_method_options()
    {
        if (!class_exists('WC_Payment_Gateways')) return array();
        $gateways = \WC_Payment_Gateways::instance()->payment_gateways();
        $out = array();
        foreach ($gateways as $gw) {
            if ($gw->enabled === 'yes') {
                $out[$gw->id] = $gw->get_title();
            }
        }
        return $out;
    }

    private function get_tax_class_options()
    {
        // WC_Tax::get_tax_classes() returns array of class names (no "Standard")
        $classes = \WC_Tax::get_tax_classes();
        $out = array('' => __('Standard rate', 'multi-location-product-and-inventory-management'));
        foreach ($classes as $class) {
            $out[sanitize_title($class)] = $class;
        }
        return $out;
    }

    public function add_location_fields()
    {
?>
        <div class="form-field">
            <label for="street_address"><?php _e('Street Address', 'multi-location-product-and-inventory-management'); ?></label>
            <input type="text" name="street_address" id="street_address" value="" />
            <p class="description"><?php _e('Enter street address for this location', 'multi-location-product-and-inventory-management'); ?></p>
        </div>

        <div class="form-field">
            <label for="city"><?php _e('City', 'multi-location-product-and-inventory-management'); ?></label>
            <input type="text" name="city" id="city" value="" />
            <p class="description"><?php _e('Enter city for this location', 'multi-location-product-and-inventory-management'); ?></p>
        </div>

        <div class="form-field">
            <label for="state"><?php _e('State', 'multi-location-product-and-inventory-management'); ?></label>
            <input type="text" name="state" id="state" value="" />
            <p class="description"><?php _e('Enter state for this location', 'multi-location-product-and-inventory-management'); ?></p>
        </div>

        <div class="form-field">
            <label for="postal_code"><?php _e('Postal Code', 'multi-location-product-and-inventory-management'); ?></label>
            <input type="text" name="postal_code" id="postal_code" value="" />
            <p class="description"><?php _e('Enter postal code for this location', 'multi-location-product-and-inventory-management'); ?></p>
        </div>

        <div class="form-field">
            <label for="country"><?php _e('Country', 'multi-location-product-and-inventory-management'); ?></label>
            <input type="text" name="country" id="country" value="" />
            <p class="description"><?php _e('Enter country for this location', 'multi-location-product-and-inventory-management'); ?></p>
        </div>

        <div class="form-field">
            <label for="email"><?php _e('Email', 'multi-location-product-and-inventory-management'); ?></label>
            <input type="email" name="email" id="email" value="" />
            <p class="description"><?php _e('Enter email for this location', 'multi-location-product-and-inventory-management'); ?></p>
        </div>

        <div class="form-field">
            <label for="phone"><?php _e('Phone', 'multi-location-product-and-inventory-management'); ?></label>
            <input type="tel" name="phone" id="phone" value="" />
            <p class="description"><?php _e('Enter phone for this location', 'multi-location-product-and-inventory-management'); ?></p>
        </div>

        <!-- Latitude / Longitude -->
        <div class="form-field">
            <label for="latitude"><?php _e('Latitude', 'multi-location-product-and-inventory-management'); ?></label>
            <input type="text" name="latitude" id="latitude" value="" />
            <p class="description"><?php _e('Decimal latitude (e.g. 23.7808)', 'multi-location-product-and-inventory-management'); ?></p>
        </div>

        <div class="form-field">
            <label for="longitude"><?php _e('Longitude', 'multi-location-product-and-inventory-management'); ?></label>
            <input type="text" name="longitude" id="longitude" value="" />
            <p class="description"><?php _e('Decimal longitude (e.g. 90.2792)', 'multi-location-product-and-inventory-management'); ?></p>
        </div>

        <!-- Logo -->
        <div class="form-field mulopimfwc-media-wrap">
            <label><?php _e('Logo', 'multi-location-product-and-inventory-management'); ?></label>
            <input type="hidden" name="logo_id" class="mulopimfwc-logo-id" value="">
            <div class="mulopimfwc-logo-preview" style="margin:6px 0;"></div>
            <p>
                <span class="button mulopimfwc-upload-logo"><?php _e('Upload/Choose Logo', 'multi-location-product-and-inventory-management'); ?></span>
                <span class="button button-link-delete mulopimfwc-remove-logo"><?php _e('Remove', 'multi-location-product-and-inventory-management'); ?></span>
            </p>
        </div>

        <!-- Gallery -->
        <div class="form-field mulopimfwc-media-wrap">
            <label><?php _e('Gallery', 'multi-location-product-and-inventory-management'); ?></label>
            <input type="hidden" name="gallery_ids" class="mulopimfwc-gallery-ids" value="">
            <div class="mulopimfwc-gallery-preview" style="margin:6px 0;display:flex;flex-wrap:wrap;gap:4px;"></div>
            <p>
                <span class="button mulopimfwc-upload-gallery"><?php _e('Add Images', 'multi-location-product-and-inventory-management'); ?></span>
                <span class="button button-link-delete mulopimfwc-clear-gallery"><?php _e('Clear', 'multi-location-product-and-inventory-management'); ?></span>
            </p>
        </div>

        <!-- Business Hours -->
        <?php
        $def = $this->get_default_business_hours();
        $tzs = timezone_identifiers_list(); // basic list
        $days_labels = [
            'mon' => __('Monday', 'multi-location-product-and-inventory-management'),
            'tue' => __('Tuesday', 'multi-location-product-and-inventory-management'),
            'wed' => __('Wednesday', 'multi-location-product-and-inventory-management'),
            'thu' => __('Thursday', 'multi-location-product-and-inventory-management'),
            'fri' => __('Friday', 'multi-location-product-and-inventory-management'),
            'sat' => __('Saturday', 'multi-location-product-and-inventory-management'),
            'sun' => __('Sunday', 'multi-location-product-and-inventory-management'),
        ];
        ?>
        <div class="form-field">
            <label><?php _e('Business Hours', 'multi-location-product-and-inventory-management'); ?></label>
            <div style="border:1px solid #ddd;border-radius:6px;padding:10px;max-width:660px;">
                <p class="description" style="margin-top:0;"><?php _e('Set opening hours for each day. Use “Closed” for off days or “24 hours” for round-the-clock.', 'multi-location-product-and-inventory-management'); ?></p>

                <p>
                    <strong><?php _e('Timezone', 'multi-location-product-and-inventory-management'); ?>:</strong>
                    <select name="bh[timezone]" style="min-width:280px;">
                        <?php foreach ($tzs as $tz): ?>
                            <option value="<?php echo esc_attr($tz); ?>" <?php selected($tz, $def['timezone']); ?>>
                                <?php echo esc_html($tz); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>

                <table class="form-table" style="width:auto;border-collapse:collapse;">
                    <tbody>
                        <?php foreach ($def['days'] as $key => $vals): ?>
                            <tr>
                                <th style="text-align:left;padding:6px 8px;width:140px;"><?php echo esc_html($days_labels[$key]); ?></th>
                                <td style="padding:6px 8px;">
                                    <label style="margin-right:10px;">
                                        <input type="checkbox" name="bh[days][<?php echo esc_attr($key); ?>][closed]" value="1">
                                        <?php _e('Closed', 'multi-location-product-and-inventory-management'); ?>
                                    </label>
                                    <label style="margin-right:10px;">
                                        <input type="checkbox" name="bh[days][<?php echo esc_attr($key); ?>][all_day]" value="1">
                                        <?php _e('24 hours', 'multi-location-product-and-inventory-management'); ?>
                                    </label>
                                    <span style="margin-left:10px;">
                                        <input type="time" name="bh[days][<?php echo esc_attr($key); ?>][open]" value="<?php echo esc_attr($vals['open']); ?>">
                                        &nbsp;–&nbsp;
                                        <input type="time" name="bh[days][<?php echo esc_attr($key); ?>][close]" value="<?php echo esc_attr($vals['close']); ?>">
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Shipping Zones -->
        <?php $zones = $this->get_shipping_zones_options(); ?>
        <div class="form-field">
            <label for="shipping_zones"><?php _e('Shipping Zones', 'multi-location-product-and-inventory-management'); ?></label>
            <select name="shipping_zones[]" id="shipping_zones" multiple style="min-width: 320px;">
                <?php foreach ($zones as $zid => $zname): ?>
                    <option value="<?php echo esc_attr($zid); ?>"><?php echo esc_html($zname); ?></option>
                <?php endforeach; ?>
            </select>
            <p class="description"><?php _e('Choose the shipping zones served by this location.', 'multi-location-product-and-inventory-management'); ?></p>
        </div>

        <!-- Shipping Methods (instances) -->
        <?php $zone_methods = $this->get_shipping_methods_grouped_by_zone(); ?>
        <div class="form-field">
            <label for="shipping_methods"><?php _e('Shipping Methods', 'multi-location-product-and-inventory-management'); ?></label>
            <select name="shipping_methods[]" id="shipping_methods" multiple style="min-width: 420px;">
                <?php foreach ($zone_methods as $zid => $methods): ?>
                    <?php if (!empty($methods)): ?>
                        <optgroup label="<?php echo esc_attr(sprintf(__('Zone: %s', 'multi-location-product-and-inventory-management'), $zones[$zid] ?? $zid)); ?>">
                            <?php foreach ($methods as $instance_id => $label): ?>
                                <option value="<?php echo esc_attr($zid . ':' . $instance_id); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
            <p class="description"><?php _e('Select enabled shipping method instances (grouped by zone).', 'multi-location-product-and-inventory-management'); ?></p>
        </div>

        <!-- Payment Methods -->
        <?php $payments = $this->get_payment_method_options(); ?>
        <div class="form-field">
            <label for="payment_methods"><?php _e('Payment Methods', 'multi-location-product-and-inventory-management'); ?></label>
            <select name="payment_methods[]" id="payment_methods" multiple style="min-width: 320px;">
                <?php foreach ($payments as $pid => $ptitle): ?>
                    <option value="<?php echo esc_attr($pid); ?>"><?php echo esc_html($ptitle); ?></option>
                <?php endforeach; ?>
            </select>
            <p class="description"><?php _e('Choose allowed payment gateways for this location.', 'multi-location-product-and-inventory-management'); ?></p>
        </div>

        <!-- Tax Class -->
        <?php $tax_classes = $this->get_tax_class_options(); ?>
        <div class="form-field">
            <label for="tax_class"><?php _e('Tax Class', 'multi-location-product-and-inventory-management'); ?></label>
            <select name="tax_class" id="tax_class" style="min-width: 220px;">
                <?php foreach ($tax_classes as $key => $label): ?>
                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
            <p class="description"><?php _e('Select default tax class for this location.', 'multi-location-product-and-inventory-management'); ?></p>
        </div>

        <div class="form-field">
            <label for="display_order"><?php _e('Display Order', 'multi-location-product-and-inventory-management'); ?></label>
            <input type="number" name="display_order" id="display_order" value="" min="0" step="1" />
            <p class="description"><?php _e('Enter a number to control the order of this location (smaller numbers appear first)', 'multi-location-product-and-inventory-management'); ?></p>
        </div>
    <?php
    }
    /**
     * Add custom fields when editing a location
     */
    private static $edit_location_fields_called = false;

    public function edit_location_fields($term, $taxonomy)
    {
        // Prevent multiple calls to this function
        if (self::$edit_location_fields_called) {
            return;
        }
        self::$edit_location_fields_called = true;

        // Get existing values
        $street_address = get_term_meta($term->term_id, 'street_address', true);
        $city = get_term_meta($term->term_id, 'city', true);
        $state = get_term_meta($term->term_id, 'state', true);
        $postal_code = get_term_meta($term->term_id, 'postal_code', true);
        $country = get_term_meta($term->term_id, 'country', true);
        $email = get_term_meta($term->term_id, 'email', true);
        $phone = get_term_meta($term->term_id, 'phone', true);
        $display_order = get_term_meta($term->term_id, 'display_order', true);

        $latitude      = get_term_meta($term->term_id, 'latitude', true);
        $longitude     = get_term_meta($term->term_id, 'longitude', true);
        $logo_id       = get_term_meta($term->term_id, 'logo_id', true);
        $gallery_ids   = get_term_meta($term->term_id, 'gallery_ids', true); // stored as CSV
        $sel_zones     = (array) get_term_meta($term->term_id, 'shipping_zones', true);
        $sel_methods   = (array) get_term_meta($term->term_id, 'shipping_methods', true); // array of "zoneId:instanceId"
        $sel_payments  = (array) get_term_meta($term->term_id, 'payment_methods', true);
        $sel_tax_class = (string) get_term_meta($term->term_id, 'tax_class', true);

        $zones        = $this->get_shipping_zones_options();
        $zone_methods = $this->get_shipping_methods_grouped_by_zone();
        $payments     = $this->get_payment_method_options();
        $tax_classes  = $this->get_tax_class_options();

        $logo_src = $logo_id ? wp_get_attachment_image_url($logo_id, 'thumbnail') : '';
        $gallery_ids_csv = is_array($gallery_ids) ? implode(',', $gallery_ids) : (string) $gallery_ids;
        $gallery_thumbs = '';
        if ($gallery_ids_csv) {
            foreach (array_filter(array_map('absint', explode(',', $gallery_ids_csv))) as $gid) {
                $src = wp_get_attachment_image_url($gid, 'thumbnail');
                if ($src) $gallery_thumbs .= '<img src="' . esc_url($src) . '" style="width:60px;height:auto;margin:2px;border:1px solid #ddd;border-radius:3px;">';
            }
        }
    ?>
        <tr class="form-field">
            <th scope="row"><label for="street_address"><?php _e('Street Address', 'multi-location-product-and-inventory-management'); ?></label></th>
            <td>
                <input type="text" name="street_address" id="street_address" value="<?php echo esc_attr($street_address); ?>" />
                <p class="description"><?php _e('Enter street address for this location', 'multi-location-product-and-inventory-management'); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="city"><?php _e('City', 'multi-location-product-and-inventory-management'); ?></label></th>
            <td>
                <input type="text" name="city" id="city" value="<?php echo esc_attr($city); ?>" />
                <p class="description"><?php _e('Enter city for this location', 'multi-location-product-and-inventory-management'); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="state"><?php _e('State', 'multi-location-product-and-inventory-management'); ?></label></th>
            <td>
                <input type="text" name="state" id="state" value="<?php echo esc_attr($state); ?>" />
                <p class="description"><?php _e('Enter state for this location', 'multi-location-product-and-inventory-management'); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="postal_code"><?php _e('Postal Code', 'multi-location-product-and-inventory-management'); ?></label></th>
            <td>
                <input type="text" name="postal_code" id="postal_code" value="<?php echo esc_attr($postal_code); ?>" />
                <p class="description"><?php _e('Enter postal code for this location', 'multi-location-product-and-inventory-management'); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="country"><?php _e('Country', 'multi-location-product-and-inventory-management'); ?></label></th>
            <td>
                <input type="text" name="country" id="country" value="<?php echo esc_attr($country); ?>" />
                <p class="description"><?php _e('Enter country for this location', 'multi-location-product-and-inventory-management'); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="email"><?php _e('Email', 'multi-location-product-and-inventory-management'); ?></label></th>
            <td>
                <input type="email" name="email" id="email" value="<?php echo esc_attr($email); ?>" />
                <p class="description"><?php _e('Enter email for this location', 'multi-location-product-and-inventory-management'); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="phone"><?php _e('Phone', 'multi-location-product-and-inventory-management'); ?></label></th>
            <td>
                <input type="tel" name="phone" id="phone" value="<?php echo esc_attr($phone); ?>" />
                <p class="description"><?php _e('Enter phone for this location', 'multi-location-product-and-inventory-management'); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="latitude"><?php _e('Latitude', 'multi-location-product-and-inventory-management'); ?></label></th>
            <td>
                <input type="text" name="latitude" id="latitude" value="<?php echo esc_attr($latitude); ?>" />
                <p class="description"><?php _e('Decimal latitude (e.g. 23.7808)', 'multi-location-product-and-inventory-management'); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="longitude"><?php _e('Longitude', 'multi-location-product-and-inventory-management'); ?></label></th>
            <td>
                <input type="text" name="longitude" id="longitude" value="<?php echo esc_attr($longitude); ?>" />
                <p class="description"><?php _e('Decimal longitude (e.g. 90.2792)', 'multi-location-product-and-inventory-management'); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label><?php _e('Logo', 'multi-location-product-and-inventory-management'); ?></label></th>
            <td class="mulopimfwc-media-wrap">
                <input type="hidden" name="logo_id" class="mulopimfwc-logo-id" value="<?php echo esc_attr($logo_id); ?>">
                <div class="mulopimfwc-logo-preview" style="margin:6px 0;"><?php
                                                                            if ($logo_src) echo '<img src="' . esc_url($logo_src) . '" style="max-width:80px;height:auto;border:1px solid #ddd;border-radius:4px;">';
                                                                            ?></div>
                <p>
                    <span class="button mulopimfwc-upload-logo"><?php _e('Upload/Choose Logo', 'multi-location-product-and-inventory-management'); ?></span>
                    <span class="button button-link-delete mulopimfwc-remove-logo"><?php _e('Remove', 'multi-location-product-and-inventory-management'); ?></span>
                </p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label><?php _e('Gallery', 'multi-location-product-and-inventory-management'); ?></label></th>
            <td class="mulopimfwc-media-wrap">
                <input type="hidden" name="gallery_ids" class="mulopimfwc-gallery-ids" value="<?php echo esc_attr($gallery_ids_csv); ?>">
                <div class="mulopimfwc-gallery-preview" style="margin:6px 0;display:flex;flex-wrap:wrap;gap:4px;"><?php echo $gallery_thumbs; ?></div>
                <p>
                    <span class="button mulopimfwc-upload-gallery"><?php _e('Add Images', 'multi-location-product-and-inventory-management'); ?></span>
                    <span class="button button-link-delete mulopimfwc-clear-gallery"><?php _e('Clear', 'multi-location-product-and-inventory-management'); ?></span>
                </p>
            </td>
        </tr>

        <?php
        $bh = $this->get_business_hours($term->term_id);
        $tzs = timezone_identifiers_list();
        $days_labels = [
            'mon' => __('Monday', 'multi-location-product-and-inventory-management'),
            'tue' => __('Tuesday', 'multi-location-product-and-inventory-management'),
            'wed' => __('Wednesday', 'multi-location-product-and-inventory-management'),
            'thu' => __('Thursday', 'multi-location-product-and-inventory-management'),
            'fri' => __('Friday', 'multi-location-product-and-inventory-management'),
            'sat' => __('Saturday', 'multi-location-product-and-inventory-management'),
            'sun' => __('Sunday', 'multi-location-product-and-inventory-management'),
        ];
        ?>
        <tr class="form-field">
            <th scope="row"><label><?php _e('Business Hours', 'multi-location-product-and-inventory-management'); ?></label></th>
            <td>
                <div style="border:1px solid #ddd;border-radius:6px;padding:10px;max-width:660px;">
                    <p class="description" style="margin-top:0;"><?php _e('Set opening hours for each day. Use “Closed” for off days or “24 hours” for round-the-clock.', 'multi-location-product-and-inventory-management'); ?></p>

                    <p>
                        <strong><?php _e('Timezone', 'multi-location-product-and-inventory-management'); ?>:</strong>
                        <select name="bh[timezone]" style="min-width:280px;">
                            <?php foreach ($tzs as $tz): ?>
                                <option value="<?php echo esc_attr($tz); ?>" <?php selected($tz, $bh['timezone']); ?>>
                                    <?php echo esc_html($tz); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </p>

                    <table class="form-table" style="width:auto;border-collapse:collapse;">
                        <tbody>
                            <?php foreach ($bh['days'] as $key => $vals): ?>
                                <tr>
                                    <th style="text-align:left;padding:6px 8px;width:140px;"><?php echo esc_html($days_labels[$key]); ?></th>
                                    <td style="padding:6px 8px;">
                                        <label style="margin-right:10px;">
                                            <input type="checkbox" name="bh[days][<?php echo esc_attr($key); ?>][closed]" value="1" <?php checked(!empty($vals['closed'])); ?>>
                                            <?php _e('Closed', 'multi-location-product-and-inventory-management'); ?>
                                        </label>
                                        <label style="margin-right:10px;">
                                            <input type="checkbox" name="bh[days][<?php echo esc_attr($key); ?>][all_day]" value="1" <?php checked(!empty($vals['all_day'])); ?>>
                                            <?php _e('24 hours', 'multi-location-product-and-inventory-management'); ?>
                                        </label>
                                        <span style="margin-left:10px;">
                                            <input type="time" name="bh[days][<?php echo esc_attr($key); ?>][open]" value="<?php echo esc_attr($vals['open']); ?>">
                                            &nbsp;–&nbsp;
                                            <input type="time" name="bh[days][<?php echo esc_attr($key); ?>][close]" value="<?php echo esc_attr($vals['close']); ?>">
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="shipping_zones"><?php _e('Shipping Zones', 'multi-location-product-and-inventory-management'); ?></label></th>
            <td>
                <select name="shipping_zones[]" id="shipping_zones" multiple style="min-width: 320px;">
                    <?php foreach ($zones as $zid => $zname): ?>
                        <option value="<?php echo esc_attr($zid); ?>" <?php selected(in_array((string)$zid, array_map('strval', (array)$sel_zones), true)); ?>>
                            <?php echo esc_html($zname); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php _e('Choose the shipping zones served by this location.', 'multi-location-product-and-inventory-management'); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="shipping_methods"><?php _e('Shipping Methods', 'multi-location-product-and-inventory-management'); ?></label></th>
            <td>
                <select name="shipping_methods[]" id="shipping_methods" multiple style="min-width: 420px;">
                    <?php foreach ($zone_methods as $zid => $methods): if (empty($methods)) continue; ?>
                        <optgroup label="<?php echo esc_attr(sprintf(__('Zone: %s', 'multi-location-product-and-inventory-management'), $zones[$zid] ?? $zid)); ?>">
                            <?php foreach ($methods as $instance_id => $label):
                                $val = $zid . ':' . $instance_id;
                            ?>
                                <option value="<?php echo esc_attr($val); ?>" <?php selected(in_array($val, (array)$sel_methods, true)); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php _e('Select enabled shipping method instances (grouped by zone).', 'multi-location-product-and-inventory-management'); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="payment_methods"><?php _e('Payment Methods', 'multi-location-product-and-inventory-management'); ?></label></th>
            <td>
                <select name="payment_methods[]" id="payment_methods" multiple style="min-width: 320px;">
                    <?php foreach ($payments as $pid => $ptitle): ?>
                        <option value="<?php echo esc_attr($pid); ?>" <?php selected(in_array($pid, (array)$sel_payments, true)); ?>>
                            <?php echo esc_html($ptitle); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php _e('Choose allowed payment gateways for this location.', 'multi-location-product-and-inventory-management'); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="tax_class"><?php _e('Tax Class', 'multi-location-product-and-inventory-management'); ?></label></th>
            <td>
                <select name="tax_class" id="tax_class" style="min-width: 220px;">
                    <?php foreach ($tax_classes as $key => $label): ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected((string)$sel_tax_class === (string)$key); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php _e('Select default tax class for this location.', 'multi-location-product-and-inventory-management'); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="display_order"><?php _e('Display Order', 'multi-location-product-and-inventory-management'); ?></label></th>
            <td>
                <input type="number" name="display_order" id="display_order" value="<?php echo esc_attr($display_order); ?>" min="0" step="1" />
                <p class="description"><?php _e('Enter a number to control the order of this location (smaller numbers appear first)', 'multi-location-product-and-inventory-management'); ?></p>
            </td>
        </tr>
<?php
    }

    /**
     * Save custom fields when location is created or edited
     */
    public function save_location_fields($term_id, $tt_id)
    {
        if (isset($_POST['street_address'])) {
            update_term_meta($term_id, 'street_address', sanitize_text_field($_POST['street_address']));
        }

        if (isset($_POST['city'])) {
            update_term_meta($term_id, 'city', sanitize_text_field($_POST['city']));
        }

        if (isset($_POST['state'])) {
            update_term_meta($term_id, 'state', sanitize_text_field($_POST['state']));
        }

        if (isset($_POST['postal_code'])) {
            update_term_meta($term_id, 'postal_code', sanitize_text_field($_POST['postal_code']));
        }

        if (isset($_POST['country'])) {
            update_term_meta($term_id, 'country', sanitize_text_field($_POST['country']));
        }

        if (isset($_POST['email'])) {
            update_term_meta($term_id, 'email', sanitize_email($_POST['email']));
        }

        if (isset($_POST['phone'])) {
            update_term_meta($term_id, 'phone', sanitize_text_field($_POST['phone']));
        }

        // Latitude / Longitude
        if (isset($_POST['latitude'])) {
            update_term_meta($term_id, 'latitude', sanitize_text_field($_POST['latitude']));
        }
        if (isset($_POST['longitude'])) {
            update_term_meta($term_id, 'longitude', sanitize_text_field($_POST['longitude']));
        }

        // Logo (attachment ID)
        if (isset($_POST['logo_id'])) {
            update_term_meta($term_id, 'logo_id', absint($_POST['logo_id']));
        }

        // Gallery (CSV of IDs)
        if (isset($_POST['gallery_ids'])) {
            $ids = array_filter(array_map('absint', explode(',', (string) $_POST['gallery_ids'])));
            update_term_meta($term_id, 'gallery_ids', $ids); // store as array for convenience
        }

        // Business Hours
        if (isset($_POST['bh']) && is_array($_POST['bh'])) {
            $raw = wp_unslash($_POST['bh']);

            $tz = isset($raw['timezone']) ? sanitize_text_field($raw['timezone']) : wp_timezone_string();
            if (!in_array($tz, timezone_identifiers_list(), true)) {
                $tz = wp_timezone_string();
            }

            $days_clean = [];
            $keys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
            foreach ($keys as $k) {
                $row = isset($raw['days'][$k]) ? (array) $raw['days'][$k] : [];
                $closed  = empty($row['closed']) ? 0 : 1;
                $all_day = empty($row['all_day']) ? 0 : 1;

                // Time strings like 09:00, 18:00
                $open  = isset($row['open'])  ? preg_replace('/[^0-9:]/', '', (string) $row['open'])  : '09:00';
                $close = isset($row['close']) ? preg_replace('/[^0-9:]/', '', (string) $row['close']) : '18:00';

                // Normalize: if all_day, ignore times; if closed, ignore times.
                if ($all_day) {
                    $open = '00:00';
                    $close = '23:59';
                }

                $days_clean[$k] = [
                    'closed'  => $closed,
                    'all_day' => $all_day,
                    'open'    => $open ?: '09:00',
                    'close'   => $close ?: '18:00',
                ];
            }

            $to_save = [
                'timezone' => $tz,
                'days'     => $days_clean,
            ];
            update_term_meta($term_id, 'business_hours', $to_save);
        }

        // Shipping Zones (array of IDs)
        if (isset($_POST['shipping_zones'])) {
            $zones = array_map('absint', (array) $_POST['shipping_zones']);
            update_term_meta($term_id, 'shipping_zones', $zones);
        } else {
            delete_term_meta($term_id, 'shipping_zones');
        }

        // Shipping Methods (array of "zoneId:instanceId")
        if (isset($_POST['shipping_methods'])) {
            $methods = array();
            foreach ((array) $_POST['shipping_methods'] as $val) {
                // keep "zoneId:instanceId" pattern safe
                $val = preg_replace('/[^0-9:]/', '', (string) $val);
                if (preg_match('/^\d+:\d+$/', $val)) {
                    $methods[] = $val;
                }
            }
            $methods = array_values(array_unique($methods));
            update_term_meta($term_id, 'shipping_methods', $methods);
        } else {
            delete_term_meta($term_id, 'shipping_methods');
        }

        // Payment Methods (gateway IDs)
        if (isset($_POST['payment_methods'])) {
            $payments = array_map('wc_clean', (array) $_POST['payment_methods']);
            $payments = array_values(array_unique($payments));
            update_term_meta($term_id, 'payment_methods', $payments);
        } else {
            delete_term_meta($term_id, 'payment_methods');
        }

        // Tax Class (slug or empty for Standard)
        if (isset($_POST['tax_class'])) {
            update_term_meta($term_id, 'tax_class', sanitize_title((string) $_POST['tax_class']));
        }

        if (isset($_POST['display_order'])) {
            $display_order = intval($_POST['display_order']);
            update_term_meta($term_id, 'display_order', $display_order);
        }
    }

    /**
     * Add custom columns to location taxonomy table
     */
    public function add_location_taxonomy_columns($columns)
    {
        $new_columns = array();

        // Add columns before the 'slug' column
        foreach ($columns as $key => $value) {
            if ($key === 'slug') {
                $new_columns['status'] = __('Status', 'multi-location-product-and-inventory-management');
                $new_columns['display_order'] = __('Order', 'multi-location-product-and-inventory-management');
                $new_columns['city'] = __('City', 'multi-location-product-and-inventory-management');
                $new_columns['country'] = __('Country', 'multi-location-product-and-inventory-management');
            }
            $new_columns[$key] = $value;
        }

        return $new_columns;
    }

    /**
     * Add content to custom columns in location taxonomy table
     */
    public function add_location_taxonomy_column_content($content, $column_name, $term_id)
    {
        switch ($column_name) {
            case 'status':
                echo $this->render_status_badge($term_id);
                break;
            case 'display_order':
                $display_order = get_term_meta($term_id, 'display_order', true);
                echo $display_order ? esc_html($display_order) : '—';
                break;

            case 'city':
                $city = get_term_meta($term_id, 'city', true);
                echo $city ? esc_html($city) : '—';
                break;

            case 'country':
                $country = get_term_meta($term_id, 'country', true);
                echo $country ? esc_html($country) : '—';
                break;
        }

        return $content;
    }

    /**
     * Make display order column sortable
     */
    public function add_location_taxonomy_sortable_columns($columns)
    {
        $columns['display_order'] = 'display_order';
        return $columns;
    }






    public function add_settings_page()
    {
        // Add main menu page
        add_menu_page(
            __('Location Manage', 'multi-location-product-and-inventory-management'),
            __('Location Manage', 'multi-location-product-and-inventory-management'),
            'manage_options',
            'multi-location-product-and-inventory-management',
            [new MULOPIMFWC_Dashboard(), 'dashboard_page_content'],
            'dashicons-location-alt',
            56
        );

        // Add Dashboard submenu (just label, points to same page, no callback)
        add_submenu_page(
            'multi-location-product-and-inventory-management',
            __('Dashboard', 'multi-location-product-and-inventory-management'),
            __('Dashboard', 'multi-location-product-and-inventory-management'),
            'manage_options',
            'multi-location-product-and-inventory-management'
            // No callback here, so it won't render twice
        );

        // Add Locations submenu
        add_submenu_page(
            'multi-location-product-and-inventory-management',
            __('Locations', 'multi-location-product-and-inventory-management'),
            __('Locations', 'multi-location-product-and-inventory-management'),
            'manage_options',
            'edit-tags.php?taxonomy=mulopimfwc_store_location&post_type=product',
            null,
            56
        );

        // Ensure the menu is expanded and active when this page is active
        add_filter('parent_file', function ($parent_file) {
            global $pagenow, $taxonomy;

            if ($pagenow === 'edit-tags.php' && $taxonomy === 'mulopimfwc_store_location') {
                $parent_file = 'multi-location-product-and-inventory-management';
            }

            return $parent_file;
        });

        // Add current class to the active menu item
        add_filter('submenu_file', function ($submenu_file) {
            global $pagenow, $taxonomy;

            if ($pagenow === 'edit-tags.php' && $taxonomy === 'mulopimfwc_store_location') {
                $submenu_file = 'edit-tags.php?taxonomy=mulopimfwc_store_location&post_type=product';
            }

            return $submenu_file;
        });


        // add Stock Central submenu
        add_submenu_page(
            'multi-location-product-and-inventory-management',
            __('Stock Central', 'multi-location-product-and-inventory-management'),
            __('Stock Central', 'multi-location-product-and-inventory-management'),
            'manage_options',
            'location-stock-management',
            [new mulopimfwc_Stock_Central(), 'location_stock_page_content']
        );

        add_submenu_page(
            'multi-location-product-and-inventory-management',
            __('Location Managers', 'multi-location-product-and-inventory-management'),
            __('Location Managers', 'multi-location-product-and-inventory-management'),
            'manage_options',
            'location-managers',
            array(new MULOPIMFWC_Location_Managers(), 'admin_page')
        );

        // Add Settings submenu
        add_submenu_page(
            'multi-location-product-and-inventory-management',
            __('Settings', 'multi-location-product-and-inventory-management'),
            __('Settings', 'multi-location-product-and-inventory-management'),
            'manage_options',
            'multi-location-product-and-inventory-management-settings',
            [new mulopimfwc_settings(), 'settings_page_content']
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
                $new_columns['mulopimfwc_store_location'] = __('Store Location', 'multi-location-product-and-inventory-management');
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
        if ($column == 'mulopimfwc_store_location') {
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
            __('Store Location', 'multi-location-product-and-inventory-management'),
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
            echo '<p>' . esc_html__('No location data available', 'multi-location-product-and-inventory-management') . '</p>';
        }

        echo '</div>';
    }
    public function register_store_location_taxonomy()
    {
        register_taxonomy('mulopimfwc_store_location', 'product', [
            'labels' => [
                'name' => __('Locations', 'multi-location-product-and-inventory-management'),
                'singular_name' => __('Location', 'multi-location-product-and-inventory-management'),
                'search_items' => __('Search Location', 'multi-location-product-and-inventory-management'),
                'all_items' => __('All Locations', 'multi-location-product-and-inventory-management'),
                'parent_item' => __('Parent Location', 'multi-location-product-and-inventory-management'),
                'parent_item_colon' => __('Parent Location:', 'multi-location-product-and-inventory-management'),
                'edit_item' => __('Edit Location', 'multi-location-product-and-inventory-management'),
                'view_item' => __('View Location', 'multi-location-product-and-inventory-management'),
                'update_item' => __('Update Location', 'multi-location-product-and-inventory-management'),
                'add_new_item' => __('Add New Location', 'multi-location-product-and-inventory-management'),
                'new_item_name' => __('New Location Name', 'multi-location-product-and-inventory-management'),
                'separate_items_with_commas' => __('Separate locations with commas', 'multi-location-product-and-inventory-management'),
                'add_or_remove_items' => __('Add or remove locations', 'multi-location-product-and-inventory-management'),
                'choose_from_most_used' => __('Choose from most used locations', 'multi-location-product-and-inventory-management'),
                'not_found' => __('No locations found', 'multi-location-product-and-inventory-management'),
                'no_terms' => __('No locations', 'multi-location-product-and-inventory-management'),
                'menu_name' => __('Locations', 'multi-location-product-and-inventory-management'),
                'popular_items' => __('Popular Locations', 'multi-location-product-and-inventory-management'),
                'back_to_items' => __('Back to Locations', 'multi-location-product-and-inventory-management'),
            ],
            'description' => __('Manage locations for products and inventory tracking', 'multi-location-product-and-inventory-management'),
            'public' => true,
            'publicly_queryable' => true,
            'hierarchical' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_nav_menus' => true,
            'show_in_rest' => true,
            'show_tagcloud' => false,
            'show_in_quick_edit' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => [
                'slug' => 'store-location',
                'with_front' => false,
                'hierarchical' => true,
            ],
            'capabilities' => [
                'manage_terms' => 'manage_woocommerce',
                'edit_terms' => 'manage_woocommerce',
                'delete_terms' => 'manage_woocommerce',
                'assign_terms' => 'edit_products',
            ],
            'sort' => true,
        ]);
    }

    /** ---------- Business Hours Helpers ---------- */

    /**
     * Get business hours structure with sane defaults.
     */
    private function get_default_business_hours()
    {
        $days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $default = [];
        foreach ($days as $d) {
            $default[$d] = [
                'closed' => 0,     // 1 = closed all day
                'all_day' => 0,    // 1 = open 24h
                'open' => '09:00',
                'close' => '18:00',
            ];
        }
        return [
            'timezone' => wp_timezone_string(), // fallback to site timezone
            'days'     => $default,
        ];
    }

    /**
     * Fetch merged business hours for a term (defaults + saved).
     */
    private function get_business_hours($term_id)
    {
        $saved = (array) get_term_meta($term_id, 'business_hours', true);
        $def   = $this->get_default_business_hours();

        // Merge timezone
        $tz = !empty($saved['timezone']) ? $saved['timezone'] : $def['timezone'];

        // Merge days
        $merged_days = $def['days'];
        if (!empty($saved['days']) && is_array($saved['days'])) {
            foreach ($merged_days as $d => $vals) {
                if (!empty($saved['days'][$d]) && is_array($saved['days'][$d])) {
                    $merged_days[$d] = array_merge($vals, $saved['days'][$d]);
                }
            }
        }

        return [
            'timezone' => $tz,
            'days'     => $merged_days,
        ];
    }

    /**
     * Determine if a location is open "now".
     * Returns ['open' => bool, 'now' => DateTimeImmutable, 'next_change' => ?DateTimeImmutable]
     */
    public function is_location_open_now($term_id, $now_ts = null)
    {
        $bh = $this->get_business_hours($term_id);
        $tz = new \DateTimeZone($bh['timezone'] ?: wp_timezone_string());

        $now = $now_ts ? (new \DateTimeImmutable('@' . $now_ts))->setTimezone($tz)
            : (new \DateTimeImmutable('now', $tz));

        // Map PHP day of week (1 Mon .. 7 Sun) to keys
        $map = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $idx = (int) $now->format('N') - 1;
        $today_key = $map[$idx];
        $today = $bh['days'][$today_key];

        // Helper to build DateTimeImmutable from "H:i" today
        $mk = function ($timeStr, \DateTimeImmutable $base) use ($tz) {
            [$H, $i] = array_pad(explode(':', $timeStr), 2, '00');
            return $base->setTime((int)$H, (int)$i, 0);
        };

        // Closed all day
        if (!empty($today['closed'])) {
            return ['open' => false, 'now' => $now, 'next_change' => null];
        }

        // Open 24 hours
        if (!empty($today['all_day'])) {
            // Next change is tomorrow at 00:00 if tomorrow is closed/not 24h; we keep it null for simplicity
            return ['open' => true, 'now' => $now, 'next_change' => null];
        }

        // Normal window
        $open  = $mk($today['open'] ?? '09:00',  $now);
        $close = $mk($today['close'] ?? '18:00', $now);

        $open_ts  = $open->getTimestamp();
        $close_ts = $close->getTimestamp();
        $now_ts2  = $now->getTimestamp();

        // Handle overnight window (e.g., 20:00 -> 02:00)
        $is_overnight = $close_ts <= $open_ts;
        if ($is_overnight) {
            // Open from open_time today until close_time next day
            $tomorrow = $now->modify('+1 day');
            $close = $mk($today['close'] ?? '18:00', $tomorrow);
            $close_ts = $close->getTimestamp();

            $open_now = ($now_ts2 >= $open_ts) || ($now_ts2 < $close_ts);
            $next_change = $open_now
                ? (($now_ts2 >= $open_ts) ? $close : $open) // if before close (past midnight), next change is close; else open
                : $open;
            return ['open' => $open_now, 'now' => $now, 'next_change' => $next_change];
        }

        // Regular same-day window
        $open_now = ($now_ts2 >= $open_ts && $now_ts2 < $close_ts);
        $next_change = $open_now ? $close : ($now_ts2 < $open_ts ? $open : null);

        return ['open' => $open_now, 'now' => $now, 'next_change' => $next_change];
    }

    /** Render a small badge for admin tables/metabox. */
    private function render_status_badge($term_id)
    {
        $st = $this->is_location_open_now($term_id);
        $label = $st['open'] ? __('Open', 'multi-location-product-and-inventory-management')
            : __('Closed', 'multi-location-product-and-inventory-management');
        $color = $st['open'] ? '#16a34a' : '#dc2626';
        return '<span style="display:inline-block;padding:2px 8px;border-radius:12px;color:#fff;background:' . $color . ';font-size:12px;line-height:1.6;">'
            . esc_html($label) . '</span>';
    }
}
