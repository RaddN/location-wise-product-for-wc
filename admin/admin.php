<?php

if (!defined('ABSPATH')) exit;

class MULOPIMFWC_Admin
{
    public function __construct()
    {
        add_action('init', [$this, 'register_store_location_taxonomy']);

        add_action('admin_enqueue_scripts', [$this, 'mulopimfwc_admin_assets']);

        // // Hook to add the settings page
        add_action('admin_menu', [$this, 'add_settings_page']);

        // Allow adding locations to menus and link pickers.
        add_filter('nav_menu_meta_box_object', [$this, 'filter_nav_menu_meta_box_object'], 10, 1);
        add_action('admin_head-nav-menus.php', [$this, 'register_location_nav_menu_meta_box']);
        add_filter('wp_link_query', [$this, 'add_locations_to_link_query'], 10, 2);
        // Add custom column to orders table
        add_filter('woocommerce_shop_order_list_table_columns', array($this, 'add_location_column'), 20);
        add_filter('manage_woocommerce_page_wc-orders_columns', array($this, 'add_location_column'), 20);
        add_action('manage_woocommerce_page_wc-orders_custom_column', array($this, 'display_location_column_content'), 20, 2);
        add_filter('manage_edit-shop_order_columns', array($this, 'add_location_column'), 20);
        add_filter('manage_shop_order_posts_columns', array($this, 'add_location_column'), 20);
        add_action('manage_shop_order_posts_custom_column', array($this, 'display_location_column_content'), 20, 2);
        
        // Add query filtering for wc-orders page (HPOS)
        add_filter('woocommerce_shop_order_list_table_prepare_items_query_args', array($this, 'filter_orders_by_location'), 20, 1);
        add_filter('woocommerce_orders_table_query_clauses', array($this, 'filter_orders_query_clauses'), 20, 2);
        
        // Add unassigned orders link to subsubsub menu
        add_filter('views_woocommerce_page_wc-orders', array($this, 'add_unassigned_orders_view'), 20, 1);
        
        // Add metabox to order details
        add_action('add_meta_boxes', array($this, 'add_location_metabox'));
        add_action('woocommerce_process_shop_order_meta', array($this, 'save_location_metabox'), 20, 2);
        
        // AJAX handler for quick location assignment
        add_action('wp_ajax_mulopimfwc_quick_assign_location', array($this, 'ajax_quick_assign_location'));

        // Add custom fields to location taxonomy
        add_action('mulopimfwc_store_location_add_form_fields', array($this, 'add_location_fields'));
        add_action('mulopimfwc_store_location_edit_form_fields', array($this, 'edit_location_fields'), 10, 2);
        add_action('created_mulopimfwc_store_location', array($this, 'save_location_fields'), 10, 2);
        add_action('edited_mulopimfwc_store_location', array($this, 'save_location_fields'), 10, 2);

        // Add custom columns to location taxonomy table
        add_filter('manage_edit-mulopimfwc_store_location_columns', array($this, 'add_location_taxonomy_columns'));
        add_filter('manage_mulopimfwc_store_location_custom_column', array($this, 'add_location_taxonomy_column_content'), 10, 3);
        add_filter('manage_edit-mulopimfwc_store_location_sortable_columns', array($this, 'add_location_taxonomy_sortable_columns'));
        
        // Add row actions and modify term query for ordering
        add_filter('get_terms', array($this, 'order_locations_by_display_order'), 10, 4);
        add_filter('tag_row_actions', array($this, 'add_quick_edit_link'), 10, 2);
        add_filter('term_id_list_table_column', array($this, 'add_term_id_to_row'), 10, 2);
        add_action('admin_footer-edit-tags.php', array($this, 'add_location_table_scripts'));
        add_action('wp_ajax_mulopimfwc_update_location_order', array($this, 'ajax_update_location_order'));
        add_action('wp_ajax_mulopimfwc_toggle_location_status', array($this, 'ajax_toggle_location_status'));
        add_action('wp_ajax_mulopimfwc_save_quick_edit', array($this, 'ajax_save_quick_edit'));
        add_action('quick_edit_custom_box', array($this, 'add_quick_edit_fields'), 10, 3);
        add_action('edited_mulopimfwc_store_location', array($this, 'save_quick_edit_fields'), 10, 2);
        add_action('admin_footer-edit-tags.php', array($this, 'save_quick_edit_on_submit'));



        // Add AJAX actions for export and live data
        $dashboard_instance = new MULOPIMFWC_Dashboard();
        add_action('wp_ajax_mulopimfwc_export_dashboard_report', array($dashboard_instance, 'export_dashboard_report'));
        add_action('wp_ajax_mulopimfwc_dashboard_live_data', array($dashboard_instance, 'handle_live_dashboard_data'));

        // Add admin bar notification icon
        add_action('admin_bar_menu', array($this, 'add_admin_bar_notification_icon'), 100);

        // Shortcode for location status
        // [mulopimfwc_location_status id="123"]

        add_shortcode('mulopimfwc_location_status', [$this, 'shortcode_location_status']);
        add_action('update_option_mulopimfwc_display_options', [$this, 'flush_rewrite_on_settings_change'], 10, 2);
    }

    /**
     * Flush rewrite rules when location URL settings change
     */
    public function flush_rewrite_on_settings_change($old_value, $new_value)
    {
        // Check if location URL settings changed
        $old_prefix = isset($old_value['location_url_prefix']) ? $old_value['location_url_prefix'] : 'store-location';
        $new_prefix = isset($new_value['location_url_prefix']) ? $new_value['location_url_prefix'] : 'store-location';

        $old_enabled = isset($old_value['enable_location_urls']) ? $old_value['enable_location_urls'] : 'on';
        $new_enabled = isset($new_value['enable_location_urls']) ? $new_value['enable_location_urls'] : 'on';

        // NEW: Check if URL format changed
        $old_format = isset($old_value['url_location_format']) ? $old_value['url_location_format'] : 'query_param';
        $new_format = isset($new_value['url_location_format']) ? $new_value['url_location_format'] : 'query_param';

        // If prefix, enabled status, or format changed, flush rewrite rules
        if ($old_prefix !== $new_prefix || $old_enabled !== $new_enabled || $old_format !== $new_format) {
            flush_rewrite_rules();
        }
    }

    public function shortcode_location_status($atts)
    {
        $atts = shortcode_atts([
            'id'       => 0,
            'slug'     => '',
            'taxonomy' => 'mulopimfwc_store_location',
            'class'    => '',
        ], $atts, 'mulopimfwc_location_status');

        $taxonomy = sanitize_key($atts['taxonomy']);
        $term_id  = absint($atts['id']);
        $slug     = sanitize_title($atts['slug']);

        $term = null;

        // 1) Prefer ID if provided
        if ($term_id > 0) {
            $term = get_term($term_id, $taxonomy);
            if (!$term || is_wp_error($term)) {
                return '';
            }
        }
        // 2) Fallback to slug
        elseif (!empty($slug)) {
            $term = get_term_by('slug', $slug, $taxonomy);
            if (!$term || is_wp_error($term)) {
                return '';
            }
            $term_id = (int) $term->term_id;
        } else {
            // neither id nor slug provided
            return '';
        }

        // Render
        $badge = $this->render_status_badge($term_id);

        $classes = array_filter([
            'mulopimfwc-location-status',
            sanitize_html_class($atts['class']),
        ]);

        return sprintf(
            '<div class="%s">%s</div>',
            esc_attr(implode(' ', $classes)),
            $badge
        );
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

        // Enqueue Select2
        wp_enqueue_style('select2', plugin_dir_url(__FILE__) . '../assets/css/select2.min.css', array(), '4.0.13');
        wp_enqueue_script('select2', plugin_dir_url(__FILE__) . '../assets/js/select2.min.js', array('jquery'), '4.0.13', true);

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

            // Initialize Select2 for all select fields with common class
            $(document).ready(function(){
                if(typeof $.fn.select2 !== "undefined"){
                    $(".mulopimfwc-select2").each(function(){
                        var $select = $(this);
                        var placeholder = $select.data("placeholder") || "";
                        $select.select2({
                            width: "100%",
                            placeholder: placeholder,
                            allowClear: true
                        });
                    });
                }

                // Handle description truncation and popup
                function truncateDescriptions() {
                    $(".taxonomy-mulopimfwc_store_location td.description.column-description p").each(function() {
                        var $p = $(this);
                        var fullText = $p.text().trim();
                        var fullHTML = $p.html();
                        
                        // Store full content in data attributes if not already stored
                        if (!$p.data("full-text")) {
                            $p.data("full-text", fullText);
                            $p.data("full-html", fullHTML);
                        }
                        
                        // Only truncate if text is longer than 20 characters
                        if (fullText.length > 20) {
                            var truncated = fullText.substring(0, 20);
                            $p.text(truncated + ".....").addClass("mulopimfwc-truncated-description");
                        }
                    });
                }

                // Initialize truncation
                truncateDescriptions();

                // Handle click on truncated description
                $(document).on("click", ".taxonomy-mulopimfwc_store_location td.description.column-description p", function(e) {
                    e.preventDefault();
                    var $p = $(this);
                    var fullText = $p.data("full-text") || $p.text().trim();
                    var fullHTML = $p.data("full-html") || $p.html();
                    
                    // Only show popup if text was truncated
                    if (fullText.length > 20) {
                        showDescriptionPopup(fullHTML);
                    }
                });

                // Function to show description popup
                function showDescriptionPopup(htmlContent) {
                    // Remove existing popup if any
                    $(".mulopimfwc-description-popup").remove();
                    
                    // Create popup HTML - WordPress already sanitizes taxonomy descriptions
                    var $popup = $(\'<div class="mulopimfwc-description-popup active">\' +
                        \'<div class="mulopimfwc-description-popup-content">\' +
                        \'<div class="mulopimfwc-description-popup-header">\' +
                        \'<h3>Description</h3>\' +
                        \'<button type="button" class="mulopimfwc-description-popup-close" aria-label="Close">&times;</button>\' +
                        \'</div>\' +
                        \'<div class="mulopimfwc-description-popup-body"></div>\' +
                        \'</div>\' +
                        \'</div>\');
                    
                    // Set content safely
                    $popup.find(".mulopimfwc-description-popup-body").html(htmlContent);
                    
                    // Append to body
                    $("body").append($popup);
                    
                    // Handle close button click
                    $popup.find(".mulopimfwc-description-popup-close").on("click", function() {
                        $popup.removeClass("active").fadeOut(200, function() {
                            $(this).remove();
                        });
                    });
                    
                    // Handle click outside popup
                    $popup.on("click", function(e) {
                        if ($(e.target).hasClass("mulopimfwc-description-popup")) {
                            $popup.removeClass("active").fadeOut(200, function() {
                                $(this).remove();
                            });
                        }
                    });
                    
                    // Handle ESC key
                    var escHandler = function(e) {
                        if (e.keyCode === 27) { // ESC key
                            $popup.removeClass("active").fadeOut(200, function() {
                                $(this).remove();
                            });
                            $(document).off("keydown.mulopimfwcDescriptionPopup", escHandler);
                        }
                    };
                    $(document).on("keydown.mulopimfwcDescriptionPopup", escHandler);
                }

                // Re-truncate after AJAX operations (for dynamic content)
                $(document).ajaxComplete(function() {
                    setTimeout(truncateDescriptions, 100);
                });
            });
        })(jQuery);
        ';
        wp_add_inline_script('jquery', $js);
    }

    public function filter_nav_menu_meta_box_object($object)
    {
        if ($object instanceof WP_Taxonomy && $object->name === 'mulopimfwc_store_location') {
            return false;
        }

        return $object;
    }

    public function register_location_nav_menu_meta_box()
    {
        if (!taxonomy_exists('mulopimfwc_store_location')) {
            return;
        }

        if (!function_exists('wp_nav_menu_item_taxonomy_meta_box')) {
            return;
        }

        $taxonomy = get_taxonomy('mulopimfwc_store_location');
        if (!$taxonomy) {
            return;
        }

        $this->maybe_unhide_location_menu_box();

        global $wp_meta_boxes;
        if (isset($wp_meta_boxes['nav-menus']['side']['default']['add-' . $taxonomy->name])) {
            return;
        }

        add_meta_box(
            'add-' . $taxonomy->name,
            $taxonomy->labels->name,
            'wp_nav_menu_item_taxonomy_meta_box',
            'nav-menus',
            'side',
            'default',
            $taxonomy
        );
    }

    private function maybe_unhide_location_menu_box()
    {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }

        $flag_key = 'mulopimfwc_location_menu_box_unhidden';
        if (get_user_meta($user_id, $flag_key, true)) {
            return;
        }

        $hidden = get_user_meta($user_id, 'metaboxhidden_nav-menus', true);
        if (!is_array($hidden)) {
            return;
        }

        $meta_box_id = 'add-mulopimfwc_store_location';
        if (!in_array($meta_box_id, $hidden, true)) {
            return;
        }

        $hidden = array_values(array_diff($hidden, [$meta_box_id]));
        update_user_meta($user_id, 'metaboxhidden_nav-menus', $hidden);
        update_user_meta($user_id, $flag_key, 1);
    }

    public function add_locations_to_link_query($results, $query)
    {
        if (!taxonomy_exists('mulopimfwc_store_location')) {
            return $results;
        }

        if (empty($query['s'])) {
            return $results;
        }

        $search = sanitize_text_field($query['s']);
        $per_page = isset($query['per_page']) ? max(1, (int) $query['per_page']) : 20;
        $page = isset($query['page']) ? max(1, (int) $query['page']) : 1;
        $offset = ($page - 1) * $per_page;

        $terms = get_terms([
            'taxonomy' => 'mulopimfwc_store_location',
            'hide_empty' => false,
            'number' => $per_page,
            'offset' => $offset,
            'search' => $search,
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            return $results;
        }

        foreach ($terms as $term) {
            $link = get_term_link($term);
            if (is_wp_error($link)) {
                continue;
            }

            $results[] = [
                'ID' => 'mulopimfwc_store_location-' . $term->term_id,
                'title' => $term->name,
                'permalink' => $link,
                'info' => __('Location', 'multi-location-product-and-inventory-management'),
            ];
        }

        return $results;
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

        <div class="form-field">
            <label for="low_stock_threshold"><?php _e('Low Stock Threshold', 'multi-location-product-and-inventory-management'); ?></label>
            <input type="number" name="low_stock_threshold" id="low_stock_threshold" value="" min="0" step="1" />
            <p class="description"><?php _e('Alert threshold for low stock at this location (overrides global default).', 'multi-location-product-and-inventory-management'); ?></p>
        </div>

        <div class="form-field">
            <label for="out_of_stock_threshold"><?php _e('Out of Stock Threshold', 'multi-location-product-and-inventory-management'); ?></label>
            <input type="number" name="out_of_stock_threshold" id="out_of_stock_threshold" value="" min="0" step="1" />
            <p class="description"><?php _e('Alert threshold for out-of-stock at this location (overrides global default).', 'multi-location-product-and-inventory-management'); ?></p>
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
                        <?php foreach ($def['days'] as $key => $vals): 
                            // Determine default selection (custom is default if neither closed nor all_day)
                            $default_mode = (!empty($vals['closed']) ? 'closed' : (!empty($vals['all_day']) ? 'all_day' : 'custom'));
                        ?>
                            <tr>
                                <th style="text-align:left;padding:6px 8px;width:140px;"><?php echo esc_html($days_labels[$key]); ?></th>
                                <td style="padding:6px 8px;">
                                    <div style="display:flex;align-items:center;gap:15px;flex-wrap:wrap;">
                                        <label style="display:flex;align-items:center;gap:4px;cursor:pointer;">
                                            <input type="radio" name="bh[days][<?php echo esc_attr($key); ?>][mode]" value="closed" class="mulopimfwc-bh-mode" data-day="<?php echo esc_attr($key); ?>" <?php checked($default_mode, 'closed'); ?>>
                                            <span><?php _e('Closed', 'multi-location-product-and-inventory-management'); ?></span>
                                        </label>
                                        <label style="display:flex;align-items:center;gap:4px;cursor:pointer;">
                                            <input type="radio" name="bh[days][<?php echo esc_attr($key); ?>][mode]" value="all_day" class="mulopimfwc-bh-mode" data-day="<?php echo esc_attr($key); ?>" <?php checked($default_mode, 'all_day'); ?>>
                                            <span><?php _e('24 Hours', 'multi-location-product-and-inventory-management'); ?></span>
                                        </label>
                                        <label style="display:flex;align-items:center;gap:4px;cursor:pointer;">
                                            <input type="radio" name="bh[days][<?php echo esc_attr($key); ?>][mode]" value="custom" class="mulopimfwc-bh-mode" data-day="<?php echo esc_attr($key); ?>" <?php checked($default_mode, 'custom'); ?>>
                                            <span><?php _e('Custom Hours', 'multi-location-product-and-inventory-management'); ?></span>
                                        </label>
                                        <span class="mulopimfwc-bh-times" data-day="<?php echo esc_attr($key); ?>" style="margin-left:10px;<?php echo ($default_mode !== 'custom') ? 'display:none;' : ''; ?>">
                                            <input type="time" name="bh[days][<?php echo esc_attr($key); ?>][open]" value="<?php echo esc_attr($vals['open']); ?>" style="margin-right:4px;">
                                            <span style="margin:0 4px;">–</span>
                                            <input type="time" name="bh[days][<?php echo esc_attr($key); ?>][close]" value="<?php echo esc_attr($vals['close']); ?>">
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <script type="text/javascript">
                (function($){
                    $(document).ready(function(){
                        $('.mulopimfwc-bh-mode').on('change', function(){
                            var day = $(this).data('day');
                            var mode = $(this).val();
                            var $times = $('.mulopimfwc-bh-times[data-day="' + day + '"]');
                            
                            if(mode === 'custom'){
                                $times.show();
                            } else {
                                $times.hide();
                            }
                        });
                        
                        // Trigger on page load to set initial state
                        $('.mulopimfwc-bh-mode:checked').trigger('change');
                    });
                })(jQuery);
                </script>
            </div>
        </div>

        <!-- Shipping Zones -->
        <?php $zones = $this->get_shipping_zones_options(); ?>
        <div class="form-field">
            <label for="shipping_zones"><?php _e('Shipping Zones', 'multi-location-product-and-inventory-management'); ?></label>
            <select name="shipping_zones[]" id="shipping_zones" class="mulopimfwc-select2" multiple style="min-width: 320px;" data-placeholder="<?php esc_attr_e('Select shipping zones...', 'multi-location-product-and-inventory-management'); ?>">
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
            <select name="shipping_methods[]" id="shipping_methods" class="mulopimfwc-select2" multiple style="min-width: 420px;" data-placeholder="<?php esc_attr_e('Select shipping methods...', 'multi-location-product-and-inventory-management'); ?>">
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
            <select name="payment_methods[]" id="payment_methods" class="mulopimfwc-select2" multiple style="min-width: 320px;" data-placeholder="<?php esc_attr_e('Select payment methods...', 'multi-location-product-and-inventory-management'); ?>">
                <?php foreach ($payments as $pid => $ptitle): ?>
                    <option value="<?php echo esc_attr($pid); ?>"><?php echo esc_html($ptitle); ?></option>
                <?php endforeach; ?>
            </select>
            <p class="description"><?php _e('Choose allowed payment gateways for this location.', 'multi-location-product-and-inventory-management'); ?></p>
        </div>

        <!-- Pickup Locations -->
        <?php $pickup_locations = $this->get_pickup_locations(); ?>
        <?php if (!empty($pickup_locations)): ?>
            <div class="form-field">
                <label for="pickup_locations"><?php _e('Pickup Locations', 'multi-location-product-and-inventory-management'); ?></label>
                <select name="pickup_locations[]" id="pickup_locations" class="mulopimfwc-select2" multiple style="min-width: 320px;" data-placeholder="<?php esc_attr_e('Select pickup locations...', 'multi-location-product-and-inventory-management'); ?>">
                    <?php foreach ($pickup_locations as $pid => $ptitle): ?>
                        <option value="<?php echo esc_attr($pid); ?>"><?php echo esc_html($ptitle); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php _e('Choose allowed pickup locations for this store location.', 'multi-location-product-and-inventory-management'); ?></p>
            </div>
        <?php endif; ?>

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

        <div class="form-field">
            <label for="is_active"><?php _e('Status', 'multi-location-product-and-inventory-management'); ?></label>
            <select name="is_active" id="is_active">
                <option value="1" selected><?php _e('Active', 'multi-location-product-and-inventory-management'); ?></option>
                <option value="0"><?php _e('Inactive', 'multi-location-product-and-inventory-management'); ?></option>
            </select>
            <p class="description"><?php _e('Set whether this location is active or inactive', 'multi-location-product-and-inventory-management'); ?></p>
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
        $low_stock_threshold = get_term_meta($term->term_id, 'low_stock_threshold', true);
        $out_of_stock_threshold = get_term_meta($term->term_id, 'out_of_stock_threshold', true);

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
            <th scope="row"><label for="low_stock_threshold"><?php _e('Low Stock Threshold', 'multi-location-product-and-inventory-management'); ?></label></th>
            <td>
                <input type="number" name="low_stock_threshold" id="low_stock_threshold" value="<?php echo esc_attr($low_stock_threshold); ?>" min="0" step="1" />
                <p class="description"><?php _e('Alert threshold for low stock at this location (overrides global default).', 'multi-location-product-and-inventory-management'); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="out_of_stock_threshold"><?php _e('Out of Stock Threshold', 'multi-location-product-and-inventory-management'); ?></label></th>
            <td>
                <input type="number" name="out_of_stock_threshold" id="out_of_stock_threshold" value="<?php echo esc_attr($out_of_stock_threshold); ?>" min="0" step="1" />
                <p class="description"><?php _e('Alert threshold for out-of-stock at this location (overrides global default).', 'multi-location-product-and-inventory-management'); ?></p>
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
                            <?php foreach ($bh['days'] as $key => $vals): 
                                // Determine current selection (closed, all_day, or custom)
                                $current_mode = (!empty($vals['closed']) ? 'closed' : (!empty($vals['all_day']) ? 'all_day' : 'custom'));
                            ?>
                                <tr>
                                    <th style="text-align:left;padding:6px 8px;width:140px;"><?php echo esc_html($days_labels[$key]); ?></th>
                                    <td style="padding:6px 8px;">
                                        <div style="display:flex;align-items:center;gap:15px;flex-wrap:wrap;">
                                            <label style="display:flex;align-items:center;gap:4px;cursor:pointer;">
                                                <input type="radio" name="bh[days][<?php echo esc_attr($key); ?>][mode]" value="closed" class="mulopimfwc-bh-mode" data-day="<?php echo esc_attr($key); ?>" <?php checked($current_mode, 'closed'); ?>>
                                                <span><?php _e('Closed', 'multi-location-product-and-inventory-management'); ?></span>
                                            </label>
                                            <label style="display:flex;align-items:center;gap:4px;cursor:pointer;">
                                                <input type="radio" name="bh[days][<?php echo esc_attr($key); ?>][mode]" value="all_day" class="mulopimfwc-bh-mode" data-day="<?php echo esc_attr($key); ?>" <?php checked($current_mode, 'all_day'); ?>>
                                                <span><?php _e('24 Hours', 'multi-location-product-and-inventory-management'); ?></span>
                                            </label>
                                            <label style="display:flex;align-items:center;gap:4px;cursor:pointer;">
                                                <input type="radio" name="bh[days][<?php echo esc_attr($key); ?>][mode]" value="custom" class="mulopimfwc-bh-mode" data-day="<?php echo esc_attr($key); ?>" <?php checked($current_mode, 'custom'); ?>>
                                                <span><?php _e('Custom Hours', 'multi-location-product-and-inventory-management'); ?></span>
                                            </label>
                                            <span class="mulopimfwc-bh-times" data-day="<?php echo esc_attr($key); ?>" style="margin-left:10px;<?php echo ($current_mode !== 'custom') ? 'display:none;' : ''; ?>">
                                                <input type="time" name="bh[days][<?php echo esc_attr($key); ?>][open]" value="<?php echo esc_attr($vals['open']); ?>" style="margin-right:4px;">
                                                <span style="margin:0 4px;">–</span>
                                                <input type="time" name="bh[days][<?php echo esc_attr($key); ?>][close]" value="<?php echo esc_attr($vals['close']); ?>">
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <script type="text/javascript">
                    (function($){
                        $(document).ready(function(){
                            $('.mulopimfwc-bh-mode').on('change', function(){
                                var day = $(this).data('day');
                                var mode = $(this).val();
                                var $times = $('.mulopimfwc-bh-times[data-day="' + day + '"]');
                                
                                if(mode === 'custom'){
                                    $times.show();
                                } else {
                                    $times.hide();
                                }
                            });
                            
                            // Trigger on page load to set initial state
                            $('.mulopimfwc-bh-mode:checked').trigger('change');
                        });
                    })(jQuery);
                    </script>
                </div>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="shipping_zones"><?php _e('Shipping Zones', 'multi-location-product-and-inventory-management'); ?></label></th>
            <td>
                <select name="shipping_zones[]" id="shipping_zones" class="mulopimfwc-select2" multiple style="min-width: 320px;" data-placeholder="<?php esc_attr_e('Select shipping zones...', 'multi-location-product-and-inventory-management'); ?>">
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
                <select name="shipping_methods[]" id="shipping_methods" class="mulopimfwc-select2" multiple style="min-width: 420px;" data-placeholder="<?php esc_attr_e('Select shipping methods...', 'multi-location-product-and-inventory-management'); ?>">
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
                <select name="payment_methods[]" id="payment_methods" class="mulopimfwc-select2" multiple style="min-width: 320px;" data-placeholder="<?php esc_attr_e('Select payment methods...', 'multi-location-product-and-inventory-management'); ?>">
                    <?php foreach ($payments as $pid => $ptitle): ?>
                        <option value="<?php echo esc_attr($pid); ?>" <?php selected(in_array($pid, (array)$sel_payments, true)); ?>>
                            <?php echo esc_html($ptitle); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php _e('Choose allowed payment gateways for this location.', 'multi-location-product-and-inventory-management'); ?></p>
            </td>
        </tr>

        <!-- Pickup Locations -->
        <?php
        $pickup_locations = $this->get_pickup_locations();
        $sel_pickup = (array) get_term_meta($term->term_id, 'pickup_locations', true);
        ?>
        <?php if (!empty($pickup_locations)): ?>
            <tr class="form-field">
                <th scope="row"><label for="pickup_locations"><?php _e('Pickup Locations', 'multi-location-product-and-inventory-management'); ?></label></th>
                <td>
                    <select name="pickup_locations[]" id="pickup_locations" class="mulopimfwc-select2" multiple style="min-width: 320px;" data-placeholder="<?php esc_attr_e('Select pickup locations...', 'multi-location-product-and-inventory-management'); ?>">
                        <?php foreach ($pickup_locations as $pid => $ptitle): ?>
                            <option value="<?php echo esc_attr($pid); ?>" <?php selected(in_array($pid, (array)$sel_pickup, true)); ?>>
                                <?php echo esc_html($ptitle); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php _e('Choose allowed pickup locations for this store location.', 'multi-location-product-and-inventory-management'); ?></p>
                </td>
            </tr>
        <?php endif; ?>

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
        $is_active = get_term_meta($term->term_id, 'is_active', true);
        $is_active = ($is_active === '' || $is_active === '1' || $is_active === true) ? '1' : '0';
        ?>
        <tr class="form-field">
            <th scope="row"><label for="is_active"><?php _e('Status', 'multi-location-product-and-inventory-management'); ?></label></th>
            <td>
                <select name="is_active" id="is_active">
                    <option value="1" <?php selected($is_active, '1'); ?>><?php _e('Active', 'multi-location-product-and-inventory-management'); ?></option>
                    <option value="0" <?php selected($is_active, '0'); ?>><?php _e('Inactive', 'multi-location-product-and-inventory-management'); ?></option>
                </select>
                <p class="description"><?php _e('Set whether this location is active or inactive', 'multi-location-product-and-inventory-management'); ?></p>
            </td>
        </tr>
<?php
    }

    /**
     * Save custom fields when location is created or edited
     */
    public function save_location_fields($term_id, $tt_id)
    {
        // Security: Check user capabilities first
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        // Validate term_id
        if (empty($term_id) || !is_numeric($term_id)) {
            return;
        }

        // Security: Verify nonce (WordPress taxonomy forms use _wpnonce)
        // For new terms: 'add-tag', for existing terms: 'update-tag_' . $term_id
        $nonce_action = 'add-tag';
        if ($term_id > 0) {
            $nonce_action = 'update-tag_' . $term_id;
        }

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), $nonce_action)) {
            // Also check for add-tag in case it's a new term
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'add-tag')) {
                return;
            }
        }

        if (isset($_POST['street_address'])) {
            update_term_meta($term_id, 'street_address', sanitize_text_field(wp_unslash($_POST['street_address'])));
        }

        if (isset($_POST['city'])) {
            update_term_meta($term_id, 'city', sanitize_text_field(wp_unslash($_POST['city'])));
        }

        if (isset($_POST['state'])) {
            update_term_meta($term_id, 'state', sanitize_text_field(wp_unslash($_POST['state'])));
        }

        if (isset($_POST['postal_code'])) {
            update_term_meta($term_id, 'postal_code', sanitize_text_field(wp_unslash($_POST['postal_code'])));
        }

        if (isset($_POST['country'])) {
            update_term_meta($term_id, 'country', sanitize_text_field(wp_unslash($_POST['country'])));
        }

        if (isset($_POST['email'])) {
            $email = sanitize_email(wp_unslash($_POST['email']));
            if (!empty($email) && is_email($email)) {
                update_term_meta($term_id, 'email', $email);
            }
        }

        if (isset($_POST['phone'])) {
            update_term_meta($term_id, 'phone', sanitize_text_field(wp_unslash($_POST['phone'])));
        }

        if (isset($_POST['low_stock_threshold'])) {
            $threshold = absint($_POST['low_stock_threshold']);
            update_term_meta($term_id, 'low_stock_threshold', $threshold);
        }

        if (isset($_POST['out_of_stock_threshold'])) {
            $threshold = absint($_POST['out_of_stock_threshold']);
            update_term_meta($term_id, 'out_of_stock_threshold', $threshold);
        }

        // Latitude / Longitude - validate numeric ranges
        if (isset($_POST['latitude'])) {
            $latitude = sanitize_text_field(wp_unslash($_POST['latitude']));
            // Validate latitude range (-90 to 90)
            if (is_numeric($latitude) && $latitude >= -90 && $latitude <= 90) {
                update_term_meta($term_id, 'latitude', $latitude);
            }
        }
        if (isset($_POST['longitude'])) {
            $longitude = sanitize_text_field(wp_unslash($_POST['longitude']));
            // Validate longitude range (-180 to 180)
            if (is_numeric($longitude) && $longitude >= -180 && $longitude <= 180) {
                update_term_meta($term_id, 'longitude', $longitude);
            }
        }

        // Logo (attachment ID) - validate attachment exists
        if (isset($_POST['logo_id'])) {
            $logo_id = absint($_POST['logo_id']);
            if ($logo_id > 0 && wp_attachment_is_image($logo_id)) {
                update_term_meta($term_id, 'logo_id', $logo_id);
            } elseif ($logo_id === 0) {
                delete_term_meta($term_id, 'logo_id');
            }
        }

        // Gallery (CSV of IDs) - validate attachments exist and limit count
        if (isset($_POST['gallery_ids'])) {
            $ids = array_filter(array_map('absint', explode(',', (string) wp_unslash($_POST['gallery_ids']))));
            // Validate each ID is a valid attachment and limit to 50 images
            $valid_ids = [];
            foreach ($ids as $id) {
                if ($id > 0 && wp_attachment_is_image($id) && count($valid_ids) < 50) {
                    $valid_ids[] = $id;
                }
            }
            if (!empty($valid_ids)) {
                update_term_meta($term_id, 'gallery_ids', $valid_ids);
            } else {
                delete_term_meta($term_id, 'gallery_ids');
            }
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
                
                // Get the mode from radio button (closed, all_day, or custom)
                $mode = isset($row['mode']) ? sanitize_text_field($row['mode']) : 'custom';
                
                // Set closed and all_day based on mode
                $closed = ($mode === 'closed') ? 1 : 0;
                $all_day = ($mode === 'all_day') ? 1 : 0;

                // Time strings like 09:00, 18:00
                $open  = isset($row['open'])  ? preg_replace('/[^0-9:]/', '', (string) $row['open'])  : '09:00';
                $close = isset($row['close']) ? preg_replace('/[^0-9:]/', '', (string) $row['close']) : '18:00';

                // Normalize: if all_day, ignore times; if closed, ignore times.
                if ($all_day) {
                    $open = '00:00';
                    $close = '23:59';
                } elseif ($closed) {
                    // If closed, set default times (they won't be used but keep structure consistent)
                    $open = '00:00';
                    $close = '00:00';
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
            $zones = array_map('absint', (array) wp_unslash($_POST['shipping_zones']));
            $zones = array_filter($zones); // Remove zeros
            if (!empty($zones)) {
                update_term_meta($term_id, 'shipping_zones', array_values(array_unique($zones)));
            } else {
                delete_term_meta($term_id, 'shipping_zones');
            }
        } else {
            delete_term_meta($term_id, 'shipping_zones');
        }

        // Shipping Methods (array of "zoneId:instanceId")
        if (isset($_POST['shipping_methods'])) {
            $methods = array();
            foreach ((array) wp_unslash($_POST['shipping_methods']) as $val) {
                // keep "zoneId:instanceId" pattern safe
                $val = preg_replace('/[^0-9:]/', '', (string) $val);
                if (preg_match('/^\d+:\d+$/', $val)) {
                    $methods[] = $val;
                }
            }
            if (!empty($methods)) {
                $methods = array_values(array_unique($methods));
                update_term_meta($term_id, 'shipping_methods', $methods);
            } else {
                delete_term_meta($term_id, 'shipping_methods');
            }
        } else {
            delete_term_meta($term_id, 'shipping_methods');
        }

        // Payment Methods (gateway IDs)
        if (isset($_POST['payment_methods'])) {
            $payments = array_map('sanitize_text_field', array_map('wp_unslash', (array) $_POST['payment_methods']));
            $payments = array_filter($payments); // Remove empty values
            if (!empty($payments)) {
                update_term_meta($term_id, 'payment_methods', array_values(array_unique($payments)));
            } else {
                delete_term_meta($term_id, 'payment_methods');
            }
        } else {
            delete_term_meta($term_id, 'payment_methods');
        }

        // Pickup Locations
        if (isset($_POST['pickup_locations']) && is_array($_POST['pickup_locations'])) {
            $pickup_locs = array();
            foreach (wp_unslash($_POST['pickup_locations']) as $pickup_loc) {
                $sanitized = sanitize_text_field($pickup_loc);
                if (!empty($sanitized) && strlen($sanitized) <= 255) { // Limit length
                    $pickup_locs[] = $sanitized;
                }
            }
            if (!empty($pickup_locs)) {
                $pickup_locs = array_values(array_unique($pickup_locs));
                update_term_meta($term_id, 'pickup_locations', $pickup_locs);
            } else {
                delete_term_meta($term_id, 'pickup_locations');
            }
        } else {
            delete_term_meta($term_id, 'pickup_locations');
        }

        // Tax Class (slug or empty for Standard)
        if (isset($_POST['tax_class'])) {
            update_term_meta($term_id, 'tax_class', sanitize_title((string) $_POST['tax_class']));
        }

        if (isset($_POST['display_order'])) {
            $display_order = absint($_POST['display_order']);
            update_term_meta($term_id, 'display_order', $display_order);
        } else {
            // If no display_order is set, assign the next available order
            $existing = get_term_meta($term_id, 'display_order', true);
            if ($existing === '') {
                // Get the highest display_order and add 1
                $terms = get_terms(array(
                    'taxonomy' => 'mulopimfwc_store_location',
                    'hide_empty' => false,
                    'fields' => 'ids',
                ));
                $max_order = 0;
                if (!is_wp_error($terms) && !empty($terms)) {
                    foreach ($terms as $tid) {
                        $order = (int) get_term_meta($tid, 'display_order', true);
                        if ($order > $max_order) {
                            $max_order = $order;
                        }
                    }
                }
                update_term_meta($term_id, 'display_order', $max_order + 1);
            }
        }

        // Save active/inactive status
        if (isset($_POST['is_active'])) {
            $is_active = sanitize_text_field(wp_unslash($_POST['is_active']));
            $is_active = ($is_active === '1' || $is_active === 'yes' || $is_active === 'on') ? '1' : '0';
            update_term_meta($term_id, 'is_active', $is_active);
        } else {
            // Default to active if not set
            $existing = get_term_meta($term_id, 'is_active', true);
            if ($existing === '') {
                update_term_meta($term_id, 'is_active', '1');
            }
        }
    }

    /**
     * Add custom columns to location taxonomy table
     */
    public function add_location_taxonomy_columns($columns)
    {
        $new_columns = array();

        // Add checkbox column first
        if (isset($columns['cb'])) {
            $new_columns['cb'] = $columns['cb'];
        }

        // Add drag handle column
        $new_columns['drag_handle'] = '<span class="dashicons dashicons-menu-alt" style="cursor:move;" title="' . esc_attr__('Drag to reorder', 'multi-location-product-and-inventory-management') . '"></span>';

        // Add other columns
        foreach ($columns as $key => $value) {
            if ($key === 'cb') {
                continue; // Already added
            }
            if ($key === 'name') {
                $new_columns[$key] = $value;
                $new_columns['is_active'] = __('Status', 'multi-location-product-and-inventory-management');
            } elseif ($key === 'slug') {
                $new_columns['status'] = __('Open Status', 'multi-location-product-and-inventory-management');
                $new_columns['display_order'] = __('Order', 'multi-location-product-and-inventory-management');
                $new_columns['city'] = __('City', 'multi-location-product-and-inventory-management');
                $new_columns[$key] = $value;
            } else {
                $new_columns[$key] = $value;
            }
        }

        return $new_columns;
    }

    /**
     * Add content to custom columns in location taxonomy table
     */
    public function add_location_taxonomy_column_content($content, $column_name, $term_id)
    {
        switch ($column_name) {
            case 'drag_handle':
                echo '<span class="mulopimfwc-drag-handle dashicons dashicons-menu-alt" data-term-id="' . esc_attr($term_id) . '" style="cursor:move;color:#646970;font-size:18px;vertical-align:middle;" title="' . esc_attr__('Drag to reorder', 'multi-location-product-and-inventory-management') . '"></span>';
                break;
            case 'is_active':
                $is_active = get_term_meta($term_id, 'is_active', true);
                $is_active = ($is_active === '' || $is_active === '1' || $is_active === true) ? '1' : '0';
                $status_class = $is_active === '1' ? 'active' : 'inactive';
                $status_text = $is_active === '1' ? __('Active', 'multi-location-product-and-inventory-management') : __('Inactive', 'multi-location-product-and-inventory-management');
                echo '<span class="mulopimfwc-status-toggle ' . esc_attr($status_class) . '" data-term-id="' . esc_attr($term_id) . '" data-status="' . esc_attr($is_active) . '" style="cursor:pointer;display:inline-block;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:500;color:#fff;background:' . ($is_active === '1' ? '#16a34a' : '#dc2626') . ';">' . esc_html($status_text) . '</span>';
                break;
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

    /**
     * Add quick edit link to row actions
     */
    public function add_quick_edit_link($actions, $term)
    {
        if (!isset($term->taxonomy) || $term->taxonomy !== 'mulopimfwc_store_location') {
            return $actions;
        }

        // Add quick edit link if not already present
        if (!isset($actions['inline hide-if-no-js'])) {
            $actions['inline hide-if-no-js'] = '<a href="#" class="editinline" aria-label="' . esc_attr__('Quick edit', 'multi-location-product-and-inventory-management') . '">' . __('Quick&nbsp;Edit', 'multi-location-product-and-inventory-management') . '</a>';
        }

        return $actions;
    }

    /**
     * Add term ID data attribute to table rows (via JavaScript injection)
     * This is handled in the JavaScript, but we keep this for potential future use
     */
    public function add_term_id_to_row($content, $term_id)
    {
        // This is handled via JavaScript in add_location_table_scripts
        return $content;
    }

    /**
     * Order locations by display_order by default
     */
    public function order_locations_by_display_order($terms, $taxonomies, $args, $term_query)
    {
        // Only apply to our taxonomy
        if (!in_array('mulopimfwc_store_location', (array) $taxonomies, true)) {
            return $terms;
        }

        // Only apply if no custom orderby is set
        if (isset($args['orderby']) && $args['orderby'] !== 'display_order' && $args['orderby'] !== 'term_order') {
            return $terms;
        }

        // Sort terms by display_order
        if (!empty($terms) && !is_wp_error($terms)) {
            usort($terms, function($a, $b) {
                $order_a = (int) get_term_meta($a->term_id, 'display_order', true);
                $order_b = (int) get_term_meta($b->term_id, 'display_order', true);
                
                if ($order_a === $order_b) {
                    return strcmp($a->name, $b->name);
                }
                
                return ($order_a < $order_b) ? -1 : 1;
            });
        }

        return $terms;
    }

    /**
     * Add scripts and styles for drag & drop and quick edit
     */
    public function add_location_table_scripts()
    {
        global $taxonomy;
        if ($taxonomy !== 'mulopimfwc_store_location') {
            return;
        }

        // Enqueue jQuery UI Sortable
        wp_enqueue_script('jquery-ui-sortable');
        
        // Add inline CSS
        $css = '
        <style>
        .mulopimfwc-drag-handle {
            cursor: move !important;
            color: #646970;
            font-size: 18px;
            vertical-align: middle;
        }
        .mulopimfwc-drag-handle:hover {
            color: #2271b1;
        }
        .mulopimfwc-status-toggle {
            cursor: pointer;
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            color: #fff;
            transition: all 0.2s ease;
        }
        .mulopimfwc-status-toggle:hover {
            opacity: 0.9;
            transform: scale(1.05);
        }
        .mulopimfwc-status-toggle.active {
            background: #16a34a;
        }
        .mulopimfwc-status-toggle.inactive {
            background: #dc2626;
        }
        #the-list.ui-sortable tr {
            cursor: move;
        }
        #the-list.ui-sortable tr.ui-sortable-helper {
            background: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            border: 1px solid #c3c4c7;
        }
        #the-list.ui-sortable tr.ui-sortable-placeholder {
            height: 40px;
            background: #f0f0f1;
            border: 2px dashed #c3c4c7;
            visibility: visible !important;
        }
        /* Custom fields in WordPress quick edit */
        .inline-edit-col-right .inline-edit-col {
            margin-bottom: 10px;
        }
        .inline-edit-col-right .inline-edit-col label {
            display: block;
            margin-bottom: 5px;
        }
        .inline-edit-col-right .inline-edit-col .title {
            font-weight: 600;
            display: block;
            margin-bottom: 5px;
        }
        .inline-edit-col-right #inline-edit-is-active,
        .inline-edit-col-right #inline-edit-display-order {
            width: 100%;
            max-width: 200px;
        }
        </style>
        ';
        echo $css;

        // Add inline JavaScript
        $ajax_nonce = wp_create_nonce('mulopimfwc_location_ajax');
        $js = '
        <script type="text/javascript">
        (function($) {
            $(document).ready(function() {
                var $tbody = $("#the-list");
                
                // Add data-term-id to all rows for easier access
                $tbody.find("tr").each(function() {
                    var $row = $(this);
                    var rowId = $row.attr("id");
                    if (rowId && rowId.indexOf("tag-") === 0) {
                        var termId = rowId.replace("tag-", "");
                        $row.attr("data-term-id", termId);
                        // Also add to drag handle if it exists
                        $row.find(".mulopimfwc-drag-handle").attr("data-term-id", termId);
                    }
                });
                
                // Initialize sortable
                if ($tbody.length) {
                    $tbody.sortable({
                        handle: ".mulopimfwc-drag-handle, td.column-drag_handle",
                        items: "tr:not(.no-items):not(.mulopimfwc-quick-edit-row)",
                        placeholder: "ui-sortable-placeholder",
                        helper: function(e, tr) {
                            var $originals = tr.children();
                            var $helper = tr.clone();
                            $helper.children().each(function(index) {
                                $(this).width($originals.eq(index).width());
                            });
                            return $helper;
                        },
                        start: function(e, ui) {
                            ui.placeholder.height(ui.item.height());
                        },
                        update: function(e, ui) {
                            var termIds = [];
                            $tbody.find("tr:not(.mulopimfwc-quick-edit-row)").each(function() {
                                var $row = $(this);
                                var termId = $row.find(".mulopimfwc-drag-handle").data("term-id");
                                if (!termId) {
                                    // Try to get from row ID or data attribute
                                    var rowId = $row.attr("id");
                                    if (rowId) {
                                        termId = rowId.replace("tag-", "");
                                    } else {
                                        termId = $row.attr("data-term-id");
                                    }
                                }
                                if (termId) {
                                    termIds.push(termId);
                                }
                            });
                            
                            if (termIds.length > 0) {
                                $.ajax({
                                    url: ajaxurl,
                                    type: "POST",
                                    data: {
                                        action: "mulopimfwc_update_location_order",
                                        term_ids: termIds,
                                        nonce: "' . esc_js($ajax_nonce) . '"
                                    },
                                    success: function(response) {
                                        if (response.success) {
                                            // Update order values in the table immediately
                                            $tbody.find("tr:not(.mulopimfwc-quick-edit-row)").each(function(index) {
                                                var $row = $(this);
                                                var $orderCell = $row.find("td.column-display_order");
                                                if ($orderCell.length) {
                                                    // Update the displayed order number (1-based index)
                                                    $orderCell.text(index + 1);
                                                }
                                            });
                                            
                                            // Show success message
                                            var notice = $("<div class=\"notice notice-success is-dismissible\"><p>" + response.data.message + "</p></div>");
                                            $(".wrap h1").after(notice);
                                            setTimeout(function() {
                                                notice.fadeOut(function() {
                                                    $(this).remove();
                                                });
                                            }, 3000);
                                        } else {
                                            // Show error message
                                            var notice = $("<div class=\"notice notice-error is-dismissible\"><p>" + (response.data.message || "Error updating order") + "</p></div>");
                                            $(".wrap h1").after(notice);
                                        }
                                    },
                                    error: function() {
                                        // Show error message
                                        var notice = $("<div class=\"notice notice-error is-dismissible\"><p>Error updating order. Please refresh the page.</p></div>");
                                        $(".wrap h1").after(notice);
                                    }
                                });
                            }
                        }
                    });
                }

                // Toggle active/inactive status
                $(document).on("click", ".mulopimfwc-status-toggle", function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var $toggle = $(this);
                    var termId = $toggle.data("term-id");
                    var currentStatus = String($toggle.data("status") || $toggle.attr("data-status") || "1");
                    // Toggle: if current is "1", make it "0", otherwise make it "1"
                    var newStatus = (currentStatus === "1" || currentStatus === 1) ? "0" : "1";
                    
                    if (!termId) {
                        console.error("Term ID not found");
                        return;
                    }
                    
                    $.ajax({
                        url: ajaxurl,
                        type: "POST",
                        data: {
                            action: "mulopimfwc_toggle_location_status",
                            term_id: termId,
                            status: newStatus,
                            nonce: "' . esc_js($ajax_nonce) . '"
                        },
                        beforeSend: function() {
                            $toggle.css("opacity", "0.5").css("pointer-events", "none");
                        },
                        success: function(response) {
                            if (response.success) {
                                // Update the data attribute
                                $toggle.attr("data-status", newStatus).data("status", newStatus);
                                
                                if (newStatus === "1") {
                                    $toggle.removeClass("inactive").addClass("active");
                                    $toggle.css("background", "#16a34a");
                                    $toggle.text("' . esc_js(__('Active', 'multi-location-product-and-inventory-management')) . '");
                                } else {
                                    $toggle.removeClass("active").addClass("inactive");
                                    $toggle.css("background", "#dc2626");
                                    $toggle.text("' . esc_js(__('Inactive', 'multi-location-product-and-inventory-management')) . '");
                                }
                            } else {
                                alert(response.data.message || "Error updating status");
                            }
                            $toggle.css("opacity", "1").css("pointer-events", "auto");
                        },
                        error: function(xhr, status, error) {
                            console.error("AJAX error:", error);
                            alert("Error updating status. Please try again.");
                            $toggle.css("opacity", "1").css("pointer-events", "auto");
                        }
                    });
                });

                // Populate our custom fields when WordPress quick edit opens
                var inlineEditTax = inlineEditTax || {};
                var originalEdit = inlineEditTax.edit;
                
                // Override WordPress quick edit function
                inlineEditTax.edit = function(id) {
                    // Call original WordPress function
                    if (typeof originalEdit === "function") {
                        originalEdit.apply(this, arguments);
                    }
                    
                    var termId = 0;
                    if (typeof(id) === "object") {
                        termId = parseInt(id, 10);
                    } else {
                        termId = parseInt(id.replace("tag-", ""), 10);
                    }
                    
                    if (!termId) {
                        return;
                    }
                    
                    // Get current values from the row
                    var $row = $("#tag-" + termId);
                    var $statusToggle = $row.find(".mulopimfwc-status-toggle");
                    // Get status from data attribute or HTML attribute (handle "0" properly)
                    var isActive = $statusToggle.attr("data-status") || $statusToggle.data("status");
                    // If status is undefined, null, or empty, default to "1", otherwise use the actual value
                    if (isActive === undefined || isActive === null || isActive === "") {
                        isActive = "1";
                    } else {
                        isActive = String(isActive);
                    }
                    
                    var displayOrder = "";
                    var $orderCell = $row.find("td.column-display_order");
                    if ($orderCell.length) {
                        var text = $orderCell.text().trim();
                        if (text !== "—" && text !== "") {
                            displayOrder = text;
                        }
                    }
                    
                    // Wait for WordPress quick edit row to be ready, then populate our fields
                    setTimeout(function() {
                        var $quickEditRow = $("#edit-" + termId);
                        if ($quickEditRow.length) {
                            // Populate our custom fields
                            var $statusField = $quickEditRow.find("#inline-edit-is-active");
                            var $orderField = $quickEditRow.find("#inline-edit-display-order");
                            
                            if ($statusField.length) {
                                $statusField.val(isActive);
                            }
                            if ($orderField.length) {
                                $orderField.val(displayOrder);
                            }
                        }
                    }, 200);
                };
                
                // Also handle click event as fallback
                $(document).on("click", ".editinline", function(e) {
                    var $row = $(this).closest("tr");
                    var termId = $row.find(".mulopimfwc-drag-handle").data("term-id");
                    if (!termId) {
                        var rowId = $row.attr("id");
                        if (rowId) {
                            termId = rowId.replace("tag-", "");
                        }
                    }
                    
                    if (!termId) {
                        return;
                    }
                    
                    // Get current values
                    var $statusToggle = $row.find(".mulopimfwc-status-toggle");
                    // Get status from data attribute or HTML attribute (handle "0" properly)
                    var isActive = $statusToggle.attr("data-status") || $statusToggle.data("status");
                    // If status is undefined, null, or empty, default to "1", otherwise use the actual value
                    if (isActive === undefined || isActive === null || isActive === "") {
                        isActive = "1";
                    } else {
                        isActive = String(isActive);
                    }
                    
                    var displayOrder = "";
                    var $orderCell = $row.find("td.column-display_order");
                    if ($orderCell.length) {
                        var text = $orderCell.text().trim();
                        if (text !== "—" && text !== "") {
                            displayOrder = text;
                        }
                    }
                    
                    // Wait and populate
                    setTimeout(function() {
                        var $quickEditRow = $("#edit-" + termId);
                        if ($quickEditRow.length) {
                            $quickEditRow.find("#inline-edit-is-active").val(isActive);
                            $quickEditRow.find("#inline-edit-display-order").val(displayOrder);
                        }
                    }, 200);
                });
            });
        })(jQuery);
        </script>
        ';
        echo $js;
    }

    /**
     * AJAX handler for updating location order
     */
    public function ajax_update_location_order()
    {
        check_ajax_referer('mulopimfwc_location_ajax', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied', 'multi-location-product-and-inventory-management')));
        }

        if (!isset($_POST['term_ids']) || !is_array($_POST['term_ids'])) {
            wp_send_json_error(array('message' => __('Invalid data', 'multi-location-product-and-inventory-management')));
        }

        $term_ids = array_map('absint', $_POST['term_ids']);
        $order = 0;

        foreach ($term_ids as $term_id) {
            $order++;
            update_term_meta($term_id, 'display_order', $order);
        }

        wp_send_json_success(array('message' => __('Order updated successfully', 'multi-location-product-and-inventory-management')));
    }

    /**
     * AJAX handler for toggling location status
     */
    public function ajax_toggle_location_status()
    {
        check_ajax_referer('mulopimfwc_location_ajax', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied', 'multi-location-product-and-inventory-management')));
        }

        if (!isset($_POST['term_id']) || !isset($_POST['status'])) {
            wp_send_json_error(array('message' => __('Invalid data', 'multi-location-product-and-inventory-management')));
        }

        $term_id = absint($_POST['term_id']);
        $status = sanitize_text_field($_POST['status']);
        $status = ($status === '1' || $status === 'yes' || $status === 'on') ? '1' : '0';

        update_term_meta($term_id, 'is_active', $status);

        $status_text = $status === '1' ? __('Active', 'multi-location-product-and-inventory-management') : __('Inactive', 'multi-location-product-and-inventory-management');
        wp_send_json_success(array('message' => sprintf(__('Location status updated to %s', 'multi-location-product-and-inventory-management'), $status_text)));
    }

    /**
     * Add quick edit fields to WordPress default quick edit
     */
    public function add_quick_edit_fields($column_name, $screen, $name)
    {
        if ($name !== 'mulopimfwc_store_location' || $screen !== 'edit-tags') {
            return;
        }

        // Add our custom fields after the slug field
        static $printed = false;
        if ($printed) {
            return;
        }
        $printed = true;
        ?>
        <fieldset class="inline-edit-col-right">
            <div class="inline-edit-col">
                <label>
                    <span class="title"><?php _e('Status', 'multi-location-product-and-inventory-management'); ?></span>
                    <select name="is_active" id="inline-edit-is-active">
                        <option value="1"><?php _e('Active', 'multi-location-product-and-inventory-management'); ?></option>
                        <option value="0"><?php _e('Inactive', 'multi-location-product-and-inventory-management'); ?></option>
                    </select>
                </label>
            </div>
            <div class="inline-edit-col">
                <label>
                    <span class="title"><?php _e('Order', 'multi-location-product-and-inventory-management'); ?></span>
                    <input type="number" name="display_order" id="inline-edit-display-order" value="" min="0" step="1" />
                </label>
            </div>
        </fieldset>
        <?php
    }

    /**
     * AJAX handler for saving quick edit
     */
    public function ajax_save_quick_edit()
    {
        check_ajax_referer('mulopimfwc_location_ajax', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied', 'multi-location-product-and-inventory-management')));
        }

        if (!isset($_POST['term_id'])) {
            wp_send_json_error(array('message' => __('Invalid data', 'multi-location-product-and-inventory-management')));
        }

        $term_id = absint($_POST['term_id']);

        if (isset($_POST['is_active'])) {
            $is_active = sanitize_text_field(wp_unslash($_POST['is_active']));
            $is_active = ($is_active === '1' || $is_active === 'yes' || $is_active === 'on') ? '1' : '0';
            update_term_meta($term_id, 'is_active', $is_active);
        }

        if (isset($_POST['display_order'])) {
            $display_order = absint($_POST['display_order']);
            update_term_meta($term_id, 'display_order', $display_order);
        }

        wp_send_json_success(array('message' => __('Location updated successfully', 'multi-location-product-and-inventory-management')));
    }

    /**
     * Save quick edit fields when WordPress quick edit form is submitted
     */
    public function save_quick_edit_on_submit()
    {
        global $taxonomy;
        if ($taxonomy !== 'mulopimfwc_store_location') {
            return;
        }
        
        $ajax_nonce = wp_create_nonce('mulopimfwc_location_ajax');
        ?>
        <script type="text/javascript">
        (function($) {
            // Override WordPress quick edit save function
            var inlineEditTax = inlineEditTax || {};
            var originalSave = inlineEditTax.save;
            
            inlineEditTax.save = function(id) {
                // Get term ID
                var termId = 0;
                if (typeof(id) === 'object') {
                    termId = parseInt(id, 10);
                } else {
                    termId = parseInt(id.replace('tag-', ''), 10);
                }
                
                // Get our custom field values before WordPress saves
                var $quickEditRow = $("#edit-" + termId);
                var isActive = '1';
                var displayOrder = '';
                
                if ($quickEditRow.length) {
                    var $statusField = $quickEditRow.find("#inline-edit-is-active");
                    var $orderField = $quickEditRow.find("#inline-edit-display-order");
                    
                    if ($statusField.length) {
                        isActive = $statusField.val() || '1';
                    }
                    if ($orderField.length) {
                        displayOrder = $orderField.val() || '';
                    }
                }
                
                // Call original WordPress save function
                if (typeof originalSave === 'function') {
                    var result = originalSave.apply(this, arguments);
                }
                
                // Save our custom fields via AJAX
                if (termId > 0) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'mulopimfwc_save_quick_edit',
                            term_id: termId,
                            is_active: isActive,
                            display_order: displayOrder,
                            nonce: '<?php echo esc_js($ajax_nonce); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                // Reload to show updated values
                                location.reload();
                            }
                        }
                    });
                }
                
                return result;
            };
        })(jQuery);
        </script>
        <?php
    }

    /**
     * Save quick edit fields (for form submissions)
     */
    public function save_quick_edit_fields($term_id, $tt_id)
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        // Save our custom fields when term is edited
        if (isset($_POST['is_active'])) {
            $is_active = sanitize_text_field(wp_unslash($_POST['is_active']));
            $is_active = ($is_active === '1' || $is_active === 'yes' || $is_active === 'on') ? '1' : '0';
            update_term_meta($term_id, 'is_active', $is_active);
        }

        if (isset($_POST['display_order'])) {
            $display_order = absint($_POST['display_order']);
            update_term_meta($term_id, 'display_order', $display_order);
        }
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
            'edit-tags.php?taxonomy=mulopimfwc_store_location&post_type=product&orderby=display_order&order=asc',
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
                $submenu_file = 'edit-tags.php?taxonomy=mulopimfwc_store_location&post_type=product&orderby=display_order&order=asc';
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

        global $MULOPIMFWC_Location_Managers;

        add_submenu_page(
            'multi-location-product-and-inventory-management',
            __('Location Managers', 'multi-location-product-and-inventory-management'),
            __('Location Managers', 'multi-location-product-and-inventory-management'),
            'manage_options',
            'location-managers',
            array($MULOPIMFWC_Location_Managers, 'admin_page')
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
        if (isset($columns['mulopimfwc_store_location'])) {
            return $columns;
        }

        $new_columns = array();
        $inserted = false;

        foreach ($columns as $column_name => $column_info) {
            $new_columns[$column_name] = $column_info;

            if (!$inserted && in_array($column_name, array('order_status', 'status'), true)) {
                $new_columns['mulopimfwc_store_location'] = __('Store Location', 'multi-location-product-and-inventory-management');
                $inserted = true;
            }
        }

        if (!$inserted) {
            $new_columns['mulopimfwc_store_location'] = __('Store Location', 'multi-location-product-and-inventory-management');
        }

        return $new_columns;
    }

    private function get_location_label($location_slug)
    {
        $location_slug = (string) $location_slug;

        if ($location_slug === '') {
            return '';
        }

        if ($location_slug === 'all-products') {
            return __('All Products', 'multi-location-product-and-inventory-management');
        }

        if ($location_slug === 'unassigned') {
            return __('Unassigned location', 'multi-location-product-and-inventory-management');
        }

        $term = get_term_by('slug', $location_slug, 'mulopimfwc_store_location');
        if ($term && !is_wp_error($term)) {
            return $term->name;
        }

        return str_replace(array('_', '-'), ' ', ucwords($location_slug));
    }
    /**
     * Display location in orders table column
     *
     * @param string $column Column name
     * @param WC_Order|int $order Order object or order ID
     */
    public function display_location_column_content($column, $order)
    {
        if ($column !== 'mulopimfwc_store_location') {
            return;
        }

        $order_obj = $order instanceof WC_Order ? $order : wc_get_order($order);
        if (!$order_obj) {
            echo esc_html('—');
            return;
        }

        $location_slug = (string) $order_obj->get_meta('_store_location');
        
        // Check if manual assignment mode is active
        $options = get_option('mulopimfwc_display_options', []);
        $is_manual_mode = isset($options['order_assignment_method']) 
            && $options['order_assignment_method'] === 'manual';
        
        if (empty($location_slug) && $is_manual_mode) {
            // Show quick assignment dropdown for unassigned orders
            echo $this->render_quick_assignment_dropdown($order_obj);
        } else {
            $location_label = $this->get_location_label($location_slug);
            echo esc_html($location_label !== '' ? $location_label : '—');
        }
    }
    /**
     * Render quick assignment dropdown for unassigned orders
     *
     * @param WC_Order $order Order object
     * @return string HTML output
     */
    private function render_quick_assignment_dropdown($order)
    {
        if (!$order || !current_user_can('edit_shop_order', $order->get_id())) {
            return '<span class="mulopimfwc-unassigned-badge">⚠️ ' . esc_html__('Unassigned', 'multi-location-product-and-inventory-management') . '</span>';
        }

        $order_id = $order->get_id();
        $locations = $this->get_all_store_locations();
        
        ob_start();
        ?>
        <div class="mulopimfwc-quick-assignment-wrapper">
            <span class="mulopimfwc-unassigned-badge">🔴 <?php echo esc_html__('Needs Assignment', 'multi-location-product-and-inventory-management'); ?></span>
            <select class="mulopimfwc-quick-assignment-select" data-order-id="<?php echo esc_attr($order_id); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('mulopimfwc_quick_assign_' . $order_id)); ?>">
                <option value=""><?php echo esc_html__('Select Location...', 'multi-location-product-and-inventory-management'); ?></option>
                <?php foreach ($locations as $location): ?>
                    <option value="<?php echo esc_attr($location->slug); ?>"><?php echo esc_html($location->name); ?></option>
                <?php endforeach; ?>
            </select>
            <span class="mulopimfwc-assignment-spinner" style="display: none;">⏳</span>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get all store locations
     *
     * @return array Array of location term objects
     */
    private function get_all_store_locations()
    {
        $locations = get_terms(array(
            'taxonomy' => 'mulopimfwc_store_location',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ));

        if (is_wp_error($locations)) {
            return [];
        }

        return $locations;
    }

    /**
     * Get count of unassigned orders
     *
     * @return int Count of unassigned orders
     */
    private function get_unassigned_orders_count()
    {
        $args = array(
            'limit' => -1,
            'return' => 'ids',
            'meta_query' => array(
                array(
                    'key' => '_store_location',
                    'compare' => 'NOT EXISTS',
                ),
            ),
        );

        // Also count orders with empty location
        $args2 = array(
            'limit' => -1,
            'return' => 'ids',
            'meta_query' => array(
                array(
                    'key' => '_store_location',
                    'value' => '',
                    'compare' => '=',
                ),
            ),
        );

        $orders1 = wc_get_orders($args);
        $orders2 = wc_get_orders($args2);
        
        // Combine and get unique count
        $all_order_ids = array_unique(array_merge($orders1, $orders2));
        
        return count($all_order_ids);
    }

    /**
     * Filter orders by location in query args
     *
     * @param array $query_args Query arguments
     * @return array Modified query arguments
     */
    public function filter_orders_by_location($query_args)
    {
        if (!isset($_GET['store_location_filter']) || empty($_GET['store_location_filter'])) {
            return $query_args;
        }

        $filter = sanitize_text_field($_GET['store_location_filter']);
        
        if (!isset($query_args['meta_query'])) {
            $query_args['meta_query'] = array();
        }
        
        // If there are existing meta queries, we need to wrap them properly
        $has_existing = !empty($query_args['meta_query']);
        
        if ($filter === 'unassigned') {
            // Filter for unassigned orders
            $unassigned_query = array(
                'relation' => 'OR',
                array(
                    'key' => '_store_location',
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key' => '_store_location',
                    'value' => '',
                    'compare' => '=',
                ),
            );
            
            if ($has_existing) {
                // Wrap existing queries and add our filter
                $query_args['meta_query'] = array(
                    'relation' => 'AND',
                    $query_args['meta_query'],
                    $unassigned_query,
                );
            } else {
                $query_args['meta_query'] = $unassigned_query;
            }
        } else {
            // Filter by specific location
            $location_query = array(
                'key' => '_store_location',
                'value' => $filter,
                'compare' => '=',
            );
            
            if ($has_existing) {
                // Wrap existing queries and add our filter
                $query_args['meta_query'] = array(
                    'relation' => 'AND',
                    $query_args['meta_query'],
                    $location_query,
                );
            } else {
                $query_args['meta_query'] = $location_query;
            }
        }

        return $query_args;
    }

    /**
     * Filter orders query clauses for HPOS
     *
     * @param array $clauses Query clauses
     * @param array $args Query arguments
     * @return array Modified clauses
     */
    public function filter_orders_query_clauses($clauses, $args)
    {
        if (!isset($_GET['store_location_filter']) || empty($_GET['store_location_filter'])) {
            return $clauses;
        }

        global $wpdb;
        $filter = sanitize_text_field($_GET['store_location_filter']);
        
        if ($filter === 'unassigned') {
            // Filter for unassigned orders using LEFT JOIN and WHERE
            $meta_table = $wpdb->prefix . 'wc_orders_meta';
            $orders_table = $wpdb->prefix . 'wc_orders';
            
            $clauses['join'] .= " LEFT JOIN {$meta_table} AS location_meta ON {$orders_table}.id = location_meta.order_id AND location_meta.meta_key = '_store_location'";
            $clauses['where'] .= " AND (location_meta.meta_value IS NULL OR location_meta.meta_value = '')";
        } else {
            // Filter by specific location
            $meta_table = $wpdb->prefix . 'wc_orders_meta';
            $orders_table = $wpdb->prefix . 'wc_orders';
            
            $clauses['join'] .= " INNER JOIN {$meta_table} AS location_meta ON {$orders_table}.id = location_meta.order_id AND location_meta.meta_key = '_store_location'";
            $clauses['where'] .= $wpdb->prepare(" AND location_meta.meta_value = %s", $filter);
        }

        return $clauses;
    }

    /**
     * Add unassigned orders view to subsubsub menu
     *
     * @param array $views Existing views
     * @return array Modified views
     */
    public function add_unassigned_orders_view($views)
    {
        // Only show on wc-orders page
        if (!isset($_GET['page']) || $_GET['page'] !== 'wc-orders') {
            return $views;
        }

        $options = get_option('mulopimfwc_display_options', []);
        $is_manual_mode = isset($options['order_assignment_method']) 
            && $options['order_assignment_method'] === 'manual';
        
        if (!$is_manual_mode) {
            return $views;
        }

        $unassigned_count = $this->get_unassigned_orders_count();
        $current_filter = isset($_GET['store_location_filter']) ? sanitize_text_field($_GET['store_location_filter']) : '';
        $is_current = ($current_filter === 'unassigned');
        
        $base_url = admin_url('admin.php?page=wc-orders');
        
        // Preserve all query parameters except store_location_filter
        $url_params = $_GET;
        unset($url_params['store_location_filter']);
        $url_params['store_location_filter'] = 'unassigned';
        
        $url = add_query_arg($url_params, $base_url);
        
        $class = $is_current ? 'current' : '';
        $count_html = $unassigned_count > 0 ? ' <span class="count">(' . $unassigned_count . ')</span>' : '';
        
        // Insert after draft if it exists, otherwise at the end
        $new_views = array();
        $inserted = false;
        
        foreach ($views as $key => $view) {
            $new_views[$key] = $view;
            
            // Insert after draft
            if ($key === 'wc-checkout-draft' && !$inserted) {
                $new_views['unassigned-location'] = sprintf(
                    '<a href="%s" class="%s">%s%s</a>',
                    esc_url($url),
                    esc_attr($class),
                    esc_html__('Unassigned', 'multi-location-product-and-inventory-management'),
                    $count_html
                );
                $inserted = true;
            }
        }
        
        // If draft doesn't exist, add at the end
        if (!$inserted) {
            $new_views['unassigned-location'] = sprintf(
                '<a href="%s" class="%s">%s%s</a>',
                esc_url($url),
                esc_attr($class),
                esc_html__('Unassigned', 'multi-location-product-and-inventory-management'),
                $count_html
            );
        }
        
        return $new_views;
    }

    /**
     * AJAX handler for quick location assignment
     */
    public function ajax_quick_assign_location()
    {
        check_ajax_referer('mulopimfwc_quick_assign_' . (isset($_POST['order_id']) ? intval($_POST['order_id']) : 0), 'nonce');

        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'multi-location-product-and-inventory-management')));
        }

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $location_slug = isset($_POST['location_slug']) ? sanitize_text_field($_POST['location_slug']) : '';

        if (!$order_id) {
            wp_send_json_error(array('message' => __('Invalid order ID', 'multi-location-product-and-inventory-management')));
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(array('message' => __('Order not found', 'multi-location-product-and-inventory-management')));
        }

        // Update order location
        if (empty($location_slug)) {
            $order->delete_meta_data('_store_location');
        } else {
            $order->update_meta_data('_store_location', $location_slug);
        }
        
        $order->save();

        $location_label = $this->get_location_label($location_slug);
        
        wp_send_json_success(array(
            'message' => __('Location assigned successfully', 'multi-location-product-and-inventory-management'),
            'location_label' => $location_label !== '' ? $location_label : '—',
            'location_slug' => $location_slug,
        ));
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

        $location_slug = (string) $order->get_meta('_store_location');
        $locations = get_terms(array(
            'taxonomy' => 'mulopimfwc_store_location',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ));
        $can_edit = current_user_can('edit_shop_order', $order->get_id()) || current_user_can('manage_woocommerce');

        echo '<div class="wc-store-location-container">';

        wp_nonce_field('mulopimfwc_store_location_metabox', 'mulopimfwc_store_location_nonce');

        if (!is_wp_error($locations) && !empty($locations)) {
            echo '<p>';
            echo '<label for="mulopimfwc_store_location" class="screen-reader-text">' . esc_html__('Store Location', 'multi-location-product-and-inventory-management') . '</label>';
            echo '<select name="mulopimfwc_store_location" id="mulopimfwc_store_location"' . disabled(!$can_edit, true, false) . '>';
            echo '<option value="">' . esc_html__('Unassigned location', 'multi-location-product-and-inventory-management') . '</option>';

            $has_current = false;
            foreach ($locations as $location_term) {
                if ($location_term->slug === $location_slug) {
                    $has_current = true;
                    break;
                }
            }

            if (!$has_current && $location_slug !== '') {
                echo '<option value="' . esc_attr($location_slug) . '" selected="selected">' . esc_html($this->get_location_label($location_slug)) . '</option>';
            }

            foreach ($locations as $location_term) {
                echo '<option value="' . esc_attr($location_term->slug) . '" ' . selected($location_slug, $location_term->slug, false) . '>';
                echo esc_html($location_term->name);
                echo '</option>';
            }

            echo '</select>';
            echo '</p>';
            echo '<p class="description">' . esc_html__('Update user selected store location for this order.', 'multi-location-product-and-inventory-management') . '</p>';
        } else {
            $location_label = $this->get_location_label($location_slug);
            if ($location_label !== '') {
                echo '<p>' . esc_html($location_label) . '</p>';
            }
            echo '<p class="description">' . esc_html__('No locations found. Add a location to enable changes.', 'multi-location-product-and-inventory-management') . '</p>';
        }

        if (!$can_edit) {
            echo '<p class="description">' . esc_html__('You do not have permission to change the location.', 'multi-location-product-and-inventory-management') . '</p>';
        }

        echo '</div>';
    }

    public function save_location_metabox($order_id, $post_or_order)
    {
        if (!isset($_POST['mulopimfwc_store_location_nonce'])) {
            return;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST['mulopimfwc_store_location_nonce']));
        if (!wp_verify_nonce($nonce, 'mulopimfwc_store_location_metabox')) {
            return;
        }

        if (!current_user_can('edit_shop_order', $order_id) && !current_user_can('manage_woocommerce')) {
            return;
        }

        if (!isset($_POST['mulopimfwc_store_location'])) {
            return;
        }

        $new_location = sanitize_text_field(wp_unslash($_POST['mulopimfwc_store_location']));

        $order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $current_location = (string) $order->get_meta('_store_location');
        if ($new_location === '') {
            if ($current_location === '') {
                return;
            }
            $order->delete_meta_data('_store_location');
        } else {
            if ($new_location === $current_location) {
                return;
            }
            $order->update_meta_data('_store_location', $new_location);
        }

        $order->save();
    }
    public function register_store_location_taxonomy()
    {
        global $mulopimfwc_options;
        $mulopimfwc_options = get_option('mulopimfwc_display_options');

        $enable_location_urls = isset($mulopimfwc_options['enable_location_urls']) ? $mulopimfwc_options['enable_location_urls'] : 'on';
        $location_url_prefix = isset($mulopimfwc_options['location_url_prefix']) ? $mulopimfwc_options['location_url_prefix'] : 'store-location';

        // NEW: Get URL format setting
        $url_format = isset($mulopimfwc_options['url_location_format']) ? $mulopimfwc_options['url_location_format'] : 'query_param';

        // Configure taxonomy settings based on URL format
        $taxonomy_args = [
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
            'publicly_queryable' => $enable_location_urls === 'on' ? true : false,
            'hierarchical' => true,
            'show_ui' => true,
            'show_in_menu' => $enable_location_urls === 'on' ? true : false,
            'show_in_nav_menus' => $enable_location_urls === 'on' ? true : false,
            'show_in_rest' => $enable_location_urls === 'on' ? true : false,
            'show_tagcloud' => false,
            'show_in_quick_edit' => true,
            'show_admin_column' => true,
            'capabilities' => [
                'manage_terms' => 'manage_woocommerce',
                'edit_terms' => 'manage_woocommerce',
                'delete_terms' => 'manage_woocommerce',
                'assign_terms' => 'edit_products',
            ],
            'sort' => true,
        ];

        // Configure based on URL format
        switch ($url_format) {
            case 'path_prefix':
                // URL format: /location/location-name
                $taxonomy_args['query_var'] = false;
                $taxonomy_args['rewrite'] = [
                    'slug' => $location_url_prefix,
                    'with_front' => false,
                    'hierarchical' => true,
                ];
                break;

            case 'query_param':
            default:
                // URL format: ?location=location-name
                $taxonomy_args['query_var'] = 'location';
                $taxonomy_args['rewrite'] = false;
                break;
        }

        register_taxonomy('mulopimfwc_store_location', 'product', $taxonomy_args);
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

    public function get_pickup_locations()
    {
        $pickup_settings = get_option('woocommerce_pickup_location_settings');

        if (empty($pickup_settings['enabled']) || $pickup_settings['enabled'] !== 'yes') {
            return array();
        }

        // Get pickup locations from the correct option
        $pickup_locations = get_option('pickup_location_pickup_locations', array());
        $out = array();

        if (!empty($pickup_locations) && is_array($pickup_locations)) {
            foreach ($pickup_locations as $index => $location) {
                // Check if location is enabled (it's stored as boolean true, not 'yes')
                if (!empty($location['enabled']) && $location['enabled'] === true) {
                    // Use name as both key and value for consistent storage
                    $out[$location['name']] = $location['name'];
                }
            }
        }

        return $out;
    }

    /**
     * Add notification icon to admin bar
     */
    public function add_admin_bar_notification_icon($wp_admin_bar)
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        // Add main notification menu item to top-secondary (right side of admin bar)
        $wp_admin_bar->add_node(array(
            'id' => 'mulopimfwc-notifications',
            'parent' => 'top-secondary',
            'title' => '<span class="ab-icon dashicons-bell" aria-hidden="true"></span><span class="ab-label mulopimfwc-notification-count" data-count="0">0</span>',
            'meta' => array(
                'class' => 'mulopimfwc-admin-bar-notification',
                'title' => __('Notifications', 'multi-location-product-and-inventory-management'),
            ),
        ));

        // Add dropdown container as child
        $wp_admin_bar->add_node(array(
            'parent' => 'mulopimfwc-notifications',
            'id' => 'mulopimfwc-notifications-dropdown',
            'title' => '<div id="mulopimfwc-notifications-list" class="mulopimfwc-notifications-dropdown-content"><div class="mulopimfwc-notifications-loading">' . __('Loading notifications...', 'multi-location-product-and-inventory-management') . '</div></div>',
            'meta' => array(
                'class' => 'mulopimfwc-notifications-dropdown',
            ),
        ));
    }
}
