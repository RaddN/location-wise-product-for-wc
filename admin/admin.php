<?php

if (!defined('ABSPATH')) exit;

class MULOPIMFWC_Admin
{
    private $stock_central;

    public function __construct()
    {
        $this->stock_central = new mulopimfwc_Stock_Central();

        add_action('init', [$this, 'register_store_location_taxonomy']);
        add_action('init', [$this, 'maybe_flush_rewrite_rules'], 20);
        add_filter('request', [$this, 'normalize_store_location_hierarchical_request']);

        add_action('admin_enqueue_scripts', [$this, 'mulopimfwc_admin_assets']);

        // // Hook to add the settings page
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_filter('set-screen-option', [$this, 'save_stock_central_screen_options'], 10, 3);

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

        // Bulk assignment for orders
        add_filter('bulk_actions-edit-shop_order', array($this, 'add_bulk_location_assignment'), 20, 1);
        add_filter('bulk_actions-woocommerce_page_wc-orders', array($this, 'add_bulk_location_assignment'), 20, 1);
        add_action('wp_ajax_mulopimfwc_get_bulk_assignment_data', array($this, 'ajax_get_bulk_assignment_data'));
        add_action('wp_ajax_mulopimfwc_bulk_assign_location', array($this, 'ajax_bulk_assign_location'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_bulk_assignment_assets'));
        add_action('admin_footer', array($this, 'render_bulk_assignment_modal'));

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
        add_action('admin_enqueue_scripts', array($this, 'add_location_table_scripts'));
        add_action('wp_ajax_mulopimfwc_update_location_order', array($this, 'ajax_update_location_order'));
        add_action('wp_ajax_mulopimfwc_toggle_location_status', array($this, 'ajax_toggle_location_status'));
        add_action('wp_ajax_mulopimfwc_save_quick_edit', array($this, 'ajax_save_quick_edit'));
        add_action('wp_ajax_mulopimfwc_update_location_rate', array($this, 'ajax_update_location_rate'));
        add_action('wp_ajax_mulopimfwc_sync_location_rate', array($this, 'ajax_sync_location_rate'));
        add_action('wp_ajax_mulopimfwc_sync_currency_rate', array($this, 'ajax_sync_currency_rate'));
        add_action('quick_edit_custom_box', array($this, 'add_quick_edit_fields'), 10, 3);
        add_action('edited_mulopimfwc_store_location', array($this, 'save_quick_edit_fields'), 10, 2);
        add_action('admin_enqueue_scripts', array($this, 'save_quick_edit_on_submit'));

        // Add AJAX actions for export and live data
        $dashboard_instance = new MULOPIMFWC_Dashboard();
        add_action('wp_ajax_mulopimfwc_export_dashboard_report', array($dashboard_instance, 'export_dashboard_report'));
        add_action('wp_ajax_mulopimfwc_dashboard_live_data', array($dashboard_instance, 'handle_live_dashboard_data'));
        add_action('wp_ajax_mulopimfwc_admin_bar_notifications', array($dashboard_instance, 'handle_admin_bar_notifications'));

        // Add admin bar notification icon
        add_action('admin_bar_menu', array($this, 'add_admin_bar_notification_icon'), 100);

        // Shortcode for location status
        // [mulopimfwc_location_status id="123"]

        add_shortcode('mulopimfwc_location_status', [$this, 'shortcode_location_status']);
        add_action('update_option_mulopimfwc_display_options', [$this, 'flush_rewrite_on_settings_change'], 10, 2);
    }

    /**
     * Flush rewrite rules after plugin settings change.
     */
    public function flush_rewrite_on_settings_change($old_value, $new_value)
    {
        $should_flush = apply_filters('mulopimfwc_flush_rewrite_on_settings_save', true, $old_value, $new_value);
        if ($should_flush) {
            update_option('mulopimfwc_flush_rewrite_pending', time(), false);

            if (did_action('init')) {
                // Re-register taxonomy with the latest settings before flushing.
                $this->register_store_location_taxonomy();
                flush_rewrite_rules();
                delete_option('mulopimfwc_flush_rewrite_pending');
            }
        }
    }

    /**
     * Flush rewrite rules on the next init after settings save.
     */
    public function maybe_flush_rewrite_rules()
    {
        if (!is_admin() && !wp_doing_ajax()) {
            return;
        }

        $pending = (int) get_option('mulopimfwc_flush_rewrite_pending', 0);
        if ($pending <= 0) {
            return;
        }

        static $did_flush = false;
        if ($did_flush) {
            return;
        }
        $did_flush = true;

        delete_option('mulopimfwc_flush_rewrite_pending');
        flush_rewrite_rules();
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
        $raw_id   = trim((string) $atts['id']);
        $raw_slug = trim((string) $atts['slug']);
        $term_id  = absint($raw_id);
        $slug     = sanitize_title($raw_slug);

        $term = null;

        if (
            $taxonomy === 'mulopimfwc_store_location' &&
            (in_array(strtolower($raw_id), ['current', 'selected'], true) || in_array(strtolower($raw_slug), ['current', 'selected'], true)) &&
            function_exists('mulopimfwc_resolve_location_term')
        ) {
            $selected_mode = in_array(strtolower($raw_id), ['selected'], true) || in_array(strtolower($raw_slug), ['selected'], true);
            $term = mulopimfwc_resolve_location_term([
                'allow_native' => !$selected_mode,
                'allow_request' => !$selected_mode,
                'allow_cookie' => $selected_mode,
            ]);

            if (!$term || is_wp_error($term)) {
                return '';
            }

            $term_id = (int) $term->term_id;
        }
        // 1) Prefer ID if provided
        elseif ($term_id > 0) {
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
        wp_enqueue_style(
            'mulopimfwc-location-taxonomy-table',
            plugin_dir_url(__FILE__) . '../assets/css/location-taxonomy-table.css',
            array(),
            MULOPIMFWC_VERSION
        );
        wp_enqueue_script(
            'mulopimfwc-business-hours',
            plugin_dir_url(__FILE__) . '../assets/js/business-hours.js',
            array('jquery'),
            MULOPIMFWC_VERSION,
            true
        );

        // Leaflet for location map picker
        wp_enqueue_style('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', [], '1.9.4');
        wp_enqueue_script('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', ['jquery'], '1.9.4', true);

        wp_enqueue_style(
            'mulopimfwc-admin-location-map',
            plugin_dir_url(__FILE__) . '../assets/css/admin-location-map.css',
            ['leaflet'],
            MULOPIMFWC_VERSION
        );
        wp_enqueue_script(
            'mulopimfwc-admin-location-map',
            plugin_dir_url(__FILE__) . '../assets/js/admin-location-map.js',
            ['jquery', 'leaflet'],
            MULOPIMFWC_VERSION,
            true
        );

        wp_register_script(
            'mulopimfwc-location-taxonomy-inline',
            false,
            array('jquery', 'jquery-ui-sortable'),
            MULOPIMFWC_VERSION,
            true
        );
        wp_enqueue_script('mulopimfwc-location-taxonomy-inline');

        $display_options = get_option('mulopimfwc_display_options', []);
        $default_map_zoom = isset($display_options['default_map_zoom']) ? intval($display_options['default_map_zoom']) : 15;
        if ($default_map_zoom < 1 || $default_map_zoom > 19) {
            $default_map_zoom = 15;
        }

        wp_localize_script('mulopimfwc-admin-location-map', 'mulopimfwcAdminLocationMap', [
            'defaultLat' => 0,
            'defaultLng' => 0,
            'defaultZoom' => $default_map_zoom,
            'tileUrl' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            'tileAttribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            'nominatimUrl' => 'https://nominatim.openstreetmap.org',
        ]);

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
                var select2NoResults = ' . wp_json_encode(mulopimfwc_get_text_value('text_popup_msg_no_results')) . ';
                function initSelect2($select){
                    var placeholder = $select.data("placeholder") || "";
                    $select.select2({
                        width: "100%",
                        placeholder: placeholder,
                        allowClear: true,
                        language: { noResults: function() { return select2NoResults; } }
                    });
                }

                if(typeof $.fn.select2 !== "undefined"){
                    $(".mulopimfwc-select2").each(function(){
                        initSelect2($(this));
                    });
                }

                // Filter shipping methods based on selected zones
                var $zones = $("#shipping_zones");
                var $methods = $("#shipping_methods");
                var rebuildShippingMethods = null;
                if ($zones.length && $methods.length) {
                    var methodOptions = [];
                    $methods.find("option").each(function(){
                        var val = $(this).val();
                        if (!val) {
                            return;
                        }
                        var parts = val.toString().split(":");
                        var zoneId = parts[0];
                        var $group = $(this).closest("optgroup");
                        var groupLabel = $group.attr("label") || "";
                        methodOptions.push({
                            value: val.toString(),
                            label: $(this).text(),
                            zoneId: zoneId.toString(),
                            groupLabel: groupLabel.toString()
                        });
                    });

                    rebuildShippingMethods = function(){
                        var selectedZones = ($zones.val() || []).map(function(v){ return v.toString(); });
                        var selectedMethods = ($methods.val() || []).map(function(v){ return v.toString(); });
                        var allowed = {};
                        selectedZones.forEach(function(zoneId){ allowed[zoneId] = true; });

                        $methods.empty();

                        if (selectedZones.length) {
                            var grouped = {};
                            methodOptions.forEach(function(option){
                                if (!allowed[option.zoneId]) {
                                    return;
                                }
                                if (!grouped[option.zoneId]) {
                                    grouped[option.zoneId] = {
                                        label: option.groupLabel || ("Zone " + option.zoneId),
                                        options: []
                                    };
                                }
                                grouped[option.zoneId].options.push(option);
                            });

                            Object.keys(grouped).forEach(function(zoneId){
                                var group = grouped[zoneId];
                                var $optgroup = $("<optgroup>").attr("label", group.label);
                                group.options.forEach(function(option){
                                    var $opt = $("<option>").val(option.value).text(option.label);
                                    if (selectedMethods.indexOf(option.value) !== -1) {
                                        $opt.prop("selected", true);
                                    }
                                    $optgroup.append($opt);
                                });
                                $methods.append($optgroup);
                            });
                        }

                        if (typeof $.fn.select2 !== "undefined" && $methods.data("select2")) {
                            $methods.select2("destroy");
                            initSelect2($methods);
                        }

                        $methods.trigger("change");
                    }

                    $zones.on("change", rebuildShippingMethods);
                    rebuildShippingMethods();
                }

                function clearSelectValue($select){
                    if (!$select || !$select.length) {
                        return;
                    }
                    if ($select.prop("multiple")) {
                        $select.val([]);
                    } else {
                        $select.val("");
                    }
                    $select.trigger("change");
                }

                function resetLocationAddForm(){
                    clearSelectValue($("#shipping_zones"));
                    clearSelectValue($("#payment_methods"));
                    clearSelectValue($("#pickup_locations"));
                    clearSelectValue($("#location_currency"));
                    clearSelectValue($("#location_currency_position"));
                    if (typeof rebuildShippingMethods === "function") {
                        clearSelectValue($methods);
                        rebuildShippingMethods();
                    }
                }

                $(document).ajaxComplete(function(event, xhr, settings){
                    if (!settings || !settings.data) {
                        return;
                    }

                    var action = "";
                    var taxonomy = "";

                    if (typeof settings.data === "string") {
                        try {
                            var params = new URLSearchParams(settings.data);
                            action = params.get("action") || "";
                            taxonomy = params.get("taxonomy") || "";
                        } catch (e) {
                            action = "";
                            taxonomy = "";
                        }
                    } else if (typeof settings.data === "object") {
                        action = settings.data.action || "";
                        taxonomy = settings.data.taxonomy || "";
                    }

                    if (action === "add-tag" && taxonomy === "mulopimfwc_store_location") {
                        resetLocationAddForm();
                    }
                });

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
                            var truncated = fullText.substring(0, 60);
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

        $rate_sync_config = array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mulopimfwc_sync_currency_rate'),
            'defaultCurrency' => $this->get_default_currency_code(),
            'messages' => array(
                'syncing' => __('Syncing rate...', 'multi-location-product-and-inventory-management-pro'),
                'syncFailed' => __('Unable to sync rate. Please try again.', 'multi-location-product-and-inventory-management-pro'),
            ),
        );

        $rate_sync_js = <<<'JS'
(function($){
    var config = __MULOPIMFWC_RATE_SYNC_CONFIG__;
    if (!config || typeof config !== "object") {
        return;
    }

    function parseAjaxPayload(settings){
        var payload = { action: "", taxonomy: "" };
        if (!settings || !settings.data) {
            return payload;
        }

        if (typeof settings.data === "string") {
            try {
                var params = new URLSearchParams(settings.data);
                payload.action = params.get("action") || "";
                payload.taxonomy = params.get("taxonomy") || "";
            } catch (error) {
                payload.action = "";
                payload.taxonomy = "";
            }
        } else if (typeof settings.data === "object") {
            payload.action = settings.data.action || "";
            payload.taxonomy = settings.data.taxonomy || "";
        }

        return payload;
    }

    function setRateStatus($wrap, message, isError){
        var $status = $wrap.find(".mulopimfwc-currency-rate-status").first();
        if (!$status.length) {
            return;
        }

        if (!message) {
            $status.text("").hide().css("color", "");
            return;
        }

        $status
            .text(message)
            .css("color", isError ? "#b32d2e" : "#2271b1")
            .show();
    }

    function toggleRateModeState($wrap){
        var $mode = $wrap.find(".mulopimfwc-currency-rate-mode").first();
        var $rateInput = $wrap.find(".mulopimfwc-currency-rate-value").first();
        var $syncButton = $wrap.find(".mulopimfwc-sync-rate").first();

        if (!$mode.length || !$rateInput.length || !$syncButton.length) {
            return;
        }

        var modeValue = ($mode.val() || "auto").toString().toLowerCase();
        var isAuto = modeValue === "auto";
        var isSyncing = !!$syncButton.data("mulopimfwcSyncing");

        $rateInput.prop("readonly", isAuto);
        $syncButton.toggle(isAuto);

        if (isSyncing) {
            $syncButton.prop("disabled", true);
        } else {
            $syncButton.prop("disabled", !isAuto);
        }
    }

    function syncRate($wrap){
        var $form = $wrap.closest("form");
        var $currencySelect = $form.find("#location_currency").first();
        var $syncButton = $wrap.find(".mulopimfwc-sync-rate").first();
        var $rateInput = $wrap.find(".mulopimfwc-currency-rate-value").first();

        if (!$syncButton.length || !$rateInput.length) {
            return;
        }

        var fromCurrency = (config.defaultCurrency || "USD").toString().toUpperCase();
        var toCurrency = (($currencySelect.val() || fromCurrency) + "").toUpperCase();

        $syncButton.data("mulopimfwcSyncing", true).addClass("is-busy").prop("disabled", true);
        setRateStatus($wrap, config.messages.syncing || "", false);

        $.ajax({
            url: config.ajaxUrl,
            method: "POST",
            dataType: "json",
            data: {
                action: "mulopimfwc_sync_currency_rate",
                nonce: config.nonce,
                from_currency: fromCurrency,
                to_currency: toCurrency
            }
        }).done(function(response){
            if (response && response.success && response.data && response.data.rate) {
                var parsedRate = parseFloat(response.data.rate);
                if (isFinite(parsedRate) && parsedRate > 0) {
                    var normalized = parsedRate.toFixed(6).replace(/0+$/, "").replace(/\.$/, "");
                    $rateInput.val(normalized || "1");
                    setRateStatus($wrap, response.data.message || "", false);
                    return;
                }
            }

            var failedMessage = config.messages.syncFailed || "Unable to sync rate.";
            if (response && response.data && response.data.message) {
                failedMessage = response.data.message;
            }
            setRateStatus($wrap, failedMessage, true);
        }).fail(function(xhr){
            var failedMessage = config.messages.syncFailed || "Unable to sync rate.";
            if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                failedMessage = xhr.responseJSON.data.message;
            }
            setRateStatus($wrap, failedMessage, true);
        }).always(function(){
            $syncButton.data("mulopimfwcSyncing", false).removeClass("is-busy");
            toggleRateModeState($wrap);
        });
    }

    function initRateWrap($wrap){
        if (!$wrap || !$wrap.length || $wrap.data("mulopimfwcRateInit")) {
            return;
        }
        $wrap.data("mulopimfwcRateInit", true);

        toggleRateModeState($wrap);

        $wrap.on("change", ".mulopimfwc-currency-rate-mode", function(){
            toggleRateModeState($wrap);
        });

        $wrap.on("click", ".mulopimfwc-sync-rate", function(event){
            event.preventDefault();
            syncRate($wrap);
        });

        $wrap.closest("form").on("change", "#location_currency", function(){
            var $mode = $wrap.find(".mulopimfwc-currency-rate-mode").first();
            var modeValue = ($mode.val() || "auto").toString().toLowerCase();

            if (modeValue !== "auto") {
                setRateStatus($wrap, "", false);
                return;
            }

            syncRate($wrap);
        });
    }

    function initAllRateFields(){
        $(".mulopimfwc-currency-rate-wrap").each(function(){
            initRateWrap($(this));
        });
    }

    $(document).ready(function(){
        initAllRateFields();
    });

    $(document).ajaxComplete(function(event, xhr, settings){
        initAllRateFields();

        var payload = parseAjaxPayload(settings);
        if (payload.action === "add-tag" && payload.taxonomy === "mulopimfwc_store_location") {
            $("#addtag .mulopimfwc-currency-rate-wrap").each(function(){
                var $wrap = $(this);
                $wrap.find(".mulopimfwc-currency-rate-value").val("1");
                $wrap.find(".mulopimfwc-currency-rate-mode").val("auto");
                setRateStatus($wrap, "", false);
                toggleRateModeState($wrap);
            });
        }
    });
})(jQuery);
JS;
        $rate_sync_js = str_replace('__MULOPIMFWC_RATE_SYNC_CONFIG__', wp_json_encode($rate_sync_config), $rate_sync_js);
        wp_add_inline_script('jquery', $rate_sync_js);
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
                'info' => __('Location', 'multi-location-product-and-inventory-management-pro'),
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
        $out = array('' => __('Standard rate', 'multi-location-product-and-inventory-management-pro'));
        foreach ($classes as $class) {
            $out[sanitize_title($class)] = $class;
        }
        return $out;
    }

    private function get_currency_options()
    {
        if (!function_exists('get_woocommerce_currencies')) {
            return array();
        }

        return (array) get_woocommerce_currencies();
    }

    private function get_unfiltered_currency_symbol(string $currency_code): string
    {
        $currency_code = strtoupper(trim($currency_code));
        if ($currency_code === '') {
            return '';
        }

        if (function_exists('mulopimfwc_get_unfiltered_currency_symbol')) {
            return (string) mulopimfwc_get_unfiltered_currency_symbol($currency_code);
        }

        return $currency_code;
    }

    private function get_currency_position_options()
    {
        return array(
            'left' => __('Left', 'multi-location-product-and-inventory-management-pro'),
            'right' => __('Right', 'multi-location-product-and-inventory-management-pro'),
            'left_space' => __('Left With Space', 'multi-location-product-and-inventory-management-pro'),
            'right_space' => __('Right With Space', 'multi-location-product-and-inventory-management-pro'),
        );
    }

    private function get_raw_option_value(string $option_name): string
    {
        global $wpdb;

        if (!isset($wpdb) || !is_object($wpdb) || empty($wpdb->options)) {
            return '';
        }

        $raw_value = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
                $option_name
            )
        );

        if (!is_string($raw_value)) {
            return '';
        }

        $unserialized = maybe_unserialize($raw_value);
        return is_string($unserialized) ? trim($unserialized) : '';
    }

    private function get_default_currency_code(): string
    {
        // Use raw DB option first so location-wise currency filters do not alter the base store currency.
        $currency = strtoupper($this->get_raw_option_value('woocommerce_currency'));

        if ($currency === '') {
            $currency = strtoupper(trim((string) get_option('woocommerce_currency', '')));
        }

        if ($currency === '' && function_exists('get_woocommerce_currency')) {
            $currency = strtoupper(trim((string) get_woocommerce_currency()));
        }

        $currency_options = $this->get_currency_options();
        if ($currency !== '' && !empty($currency_options) && !isset($currency_options[$currency])) {
            $currency = '';
        }

        return $currency !== '' ? $currency : 'USD';
    }

    private function get_default_currency_position(): string
    {
        $default_position = sanitize_key($this->get_raw_option_value('woocommerce_currency_pos'));
        if ($default_position === '') {
            $default_position = sanitize_key((string) get_option('woocommerce_currency_pos', ''));
        }

        $positions = $this->get_currency_position_options();

        return isset($positions[$default_position]) ? $default_position : 'left';
    }

    private function get_location_rate_mode_options(): array
    {
        return array(
            'auto' => __('Auto', 'multi-location-product-and-inventory-management-pro'),
            'fixed' => __('Fixed', 'multi-location-product-and-inventory-management-pro'),
        );
    }

    private function normalize_location_rate_mode($mode): string
    {
        $normalized = sanitize_key((string) $mode);
        $allowed_modes = array_keys($this->get_location_rate_mode_options());

        return in_array($normalized, $allowed_modes, true) ? $normalized : 'auto';
    }

    private function format_location_currency_rate_input($rate): string
    {
        if (!is_numeric($rate)) {
            return '';
        }

        $rate_value = (float) $rate;
        if ($rate_value <= 0) {
            return '';
        }

        $formatted = number_format($rate_value, 6, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted !== '' ? $formatted : '';
    }

    private function get_effective_location_currency_settings(int $term_id): array
    {
        $currencies = $this->get_currency_options();

        $currency = $this->get_default_currency_code();
        $configured_currency = strtoupper(trim((string) get_term_meta($term_id, 'location_currency', true)));
        if ($configured_currency !== '' && isset($currencies[$configured_currency])) {
            $currency = $configured_currency;
        }

        $position = $this->get_default_currency_position();
        $configured_position = sanitize_key((string) get_term_meta($term_id, 'location_currency_position', true));
        if (in_array($configured_position, array_keys($this->get_currency_position_options()), true)) {
            $position = $configured_position;
        }

        $symbol = $this->get_unfiltered_currency_symbol($currency);

        return array(
            'currency' => $currency,
            'position' => $position,
            'symbol' => $symbol,
        );
    }

    private function get_location_currency_rate_value_for_term(int $term_id): string
    {
        $rate = $this->format_location_currency_rate_input(get_term_meta($term_id, 'location_currency_rate', true));

        return $rate !== '' ? $rate : '1';
    }

    private function parse_location_currency_rate($raw_rate): ?string
    {
        $normalized = str_replace(',', '.', sanitize_text_field((string) $raw_rate));
        if (!is_numeric($normalized)) {
            return null;
        }

        $rate_value = (float) $normalized;
        if ($rate_value <= 0) {
            return null;
        }

        $formatted = $this->format_location_currency_rate_input($rate_value);
        return $formatted !== '' ? $formatted : null;
    }

    private function get_location_rate_table_payload(int $term_id): array
    {
        $currencies = $this->get_currency_options();
        $configured_currency = strtoupper(trim((string) get_term_meta($term_id, 'location_currency', true)));
        $currency_selected = ($configured_currency !== '' && isset($currencies[$configured_currency])) ? $configured_currency : '';

        $currency_settings = $this->get_effective_location_currency_settings($term_id);
        $mode = $this->normalize_location_rate_mode(get_term_meta($term_id, 'location_currency_rate_mode', true));
        $position = (string) $currency_settings['position'];
        $symbol = (string) $currency_settings['symbol'];

        $prefix = $symbol !== '' ? $symbol : (string) $currency_settings['currency'];

        return array(
            'term_id' => $term_id,
            'rate' => $this->get_location_currency_rate_value_for_term($term_id),
            'mode' => $mode,
            'is_auto' => $mode === 'auto',
            'currency' => (string) $currency_settings['currency'],
            'currency_selected' => $currency_selected,
            'position' => $position,
            'symbol_prefix' => $prefix,
            'symbol_suffix' => '',
        );
    }

    private function fetch_currency_rate_from_remote(string $from_currency, string $to_currency): ?float
    {
        $from_currency = strtoupper(trim($from_currency));
        $to_currency = strtoupper(trim($to_currency));

        if ($from_currency === '' || $to_currency === '') {
            return null;
        }

        if ($from_currency === $to_currency) {
            return 1.0;
        }

        $cache_key = 'mulopimfwc_currency_rate_' . strtolower($from_currency . '_' . $to_currency);
        $cached = get_transient($cache_key);
        if (is_numeric($cached) && (float) $cached > 0) {
            return (float) $cached;
        }

        $request_args = array(
            'timeout' => 12,
            'user-agent' => 'MULOPIMFWC/' . (defined('MULOPIMFWC_VERSION') ? MULOPIMFWC_VERSION : '1.0'),
        );

        $sources = array(
            array(
                'url' => 'https://open.er-api.com/v6/latest/' . rawurlencode($from_currency),
                'parser' => static function ($body) use ($to_currency) {
                    if (!is_array($body) || empty($body['rates']) || !is_array($body['rates'])) {
                        return null;
                    }
                    if (!isset($body['rates'][$to_currency]) || !is_numeric($body['rates'][$to_currency])) {
                        return null;
                    }

                    $rate = (float) $body['rates'][$to_currency];
                    return $rate > 0 ? $rate : null;
                },
            ),
            array(
                'url' => 'https://www.floatrates.com/daily/' . strtolower($from_currency) . '.json',
                'parser' => static function ($body) use ($to_currency) {
                    $target_key = strtolower($to_currency);
                    if (!is_array($body) || empty($body[$target_key]) || !is_array($body[$target_key])) {
                        return null;
                    }
                    if (!isset($body[$target_key]['rate']) || !is_numeric($body[$target_key]['rate'])) {
                        return null;
                    }

                    $rate = (float) $body[$target_key]['rate'];
                    return $rate > 0 ? $rate : null;
                },
            ),
        );

        foreach ($sources as $source) {
            $response = wp_safe_remote_get($source['url'], $request_args);
            if (is_wp_error($response)) {
                continue;
            }

            $http_code = (int) wp_remote_retrieve_response_code($response);
            if ($http_code < 200 || $http_code >= 300) {
                continue;
            }

            $body = wp_remote_retrieve_body($response);
            if (!is_string($body) || $body === '') {
                continue;
            }

            $decoded = json_decode($body, true);
            if (!is_array($decoded)) {
                continue;
            }

            $rate = $source['parser']($decoded);
            if (!is_numeric($rate) || (float) $rate <= 0) {
                continue;
            }

            $rate = (float) $rate;
            set_transient($cache_key, $rate, HOUR_IN_SECONDS);
            return $rate;
        }

        return null;
    }

    public function ajax_sync_currency_rate()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to sync exchange rates.', 'multi-location-product-and-inventory-management-pro'),
            ), 403);
        }

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'mulopimfwc_sync_currency_rate')) {
            wp_send_json_error(array(
                'message' => __('Invalid request. Please refresh the page and try again.', 'multi-location-product-and-inventory-management-pro'),
            ), 403);
        }

        $currencies = $this->get_currency_options();
        $default_currency = $this->get_default_currency_code();

        $from_currency = isset($_POST['from_currency']) ? strtoupper(sanitize_text_field(wp_unslash($_POST['from_currency']))) : $default_currency;
        $to_currency = isset($_POST['to_currency']) ? strtoupper(sanitize_text_field(wp_unslash($_POST['to_currency']))) : $default_currency;

        if ($from_currency === '' || !isset($currencies[$from_currency])) {
            $from_currency = $default_currency;
        }

        if ($to_currency === '' || !isset($currencies[$to_currency])) {
            wp_send_json_error(array(
                'message' => __('Please select a valid target currency first.', 'multi-location-product-and-inventory-management-pro'),
            ), 400);
        }

        if ($from_currency === $to_currency) {
            wp_send_json_success(array(
                'rate' => '1',
                'from_currency' => $from_currency,
                'to_currency' => $to_currency,
                'message' => sprintf(
                    /* translators: 1: source currency code, 2: target currency code */
                    __('Rate synced for %1$s -> %2$s.', 'multi-location-product-and-inventory-management-pro'),
                    $from_currency,
                    $to_currency
                ),
            ));
        }

        $rate = $this->fetch_currency_rate_from_remote($from_currency, $to_currency);
        if (!is_numeric($rate) || (float) $rate <= 0) {
            wp_send_json_error(array(
                'message' => __('Unable to fetch the latest exchange rate right now. Please try again.', 'multi-location-product-and-inventory-management-pro'),
            ), 500);
        }

        wp_send_json_success(array(
            'rate' => $this->format_location_currency_rate_input($rate),
            'from_currency' => $from_currency,
            'to_currency' => $to_currency,
            'message' => sprintf(
                /* translators: 1: source currency code, 2: target currency code */
                __('Rate synced for %1$s -> %2$s.', 'multi-location-product-and-inventory-management-pro'),
                $from_currency,
                $to_currency
            ),
        ));
    }

    /**
     * Normalize aliases from textarea/json/meta into a unique string list.
     *
     * @param mixed $raw_aliases
     * @return array
     */
    private function normalize_location_aliases($raw_aliases)
    {
        $items = [];

        if (is_array($raw_aliases)) {
            array_walk_recursive($raw_aliases, function ($value) use (&$items) {
                if (is_scalar($value)) {
                    $items[] = (string) $value;
                }
            });
        } elseif (is_string($raw_aliases)) {
            $raw_aliases = trim($raw_aliases);
            if ($raw_aliases !== '') {
                $decoded = json_decode($raw_aliases, true);
                if (is_array($decoded)) {
                    return $this->normalize_location_aliases($decoded);
                }
                $parts = preg_split('/[\r\n,;|]+/', $raw_aliases);
                if (is_array($parts)) {
                    $items = $parts;
                }
            }
        }

        $items = array_map(function ($value) {
            return sanitize_text_field((string) $value);
        }, $items);

        $items = array_values(array_filter(array_unique($items), function ($value) {
            return trim($value) !== '';
        }));

        return $items;
    }

    /**
     * Convert stored aliases to textarea display string.
     *
     * @param mixed $raw_aliases
     * @return string
     */
    private function aliases_to_textarea($raw_aliases)
    {
        $aliases = $this->normalize_location_aliases($raw_aliases);
        if (empty($aliases)) {
            return '';
        }

        return implode("\n", $aliases);
    }

    public function add_location_fields()
    {
?>
        <!-- Location Map -->
        <div class="form-field mulopimfwc-location-map-wrap">
            <label><?php esc_html_e('Location Map', 'multi-location-product-and-inventory-management-pro'); ?></label>
            <div class="mulopimfwc-location-map-controls">
                <input type="text" class="mulopimfwc-location-search" placeholder="<?php esc_attr_e('Search address...', 'multi-location-product-and-inventory-management-pro'); ?>" />
                <button type="button" class="button mulopimfwc-location-search-btn"><?php esc_html_e('Search', 'multi-location-product-and-inventory-management-pro'); ?></button>
            </div>
            <div class="mulopimfwc-location-map" aria-label="<?php esc_attr_e('Location map', 'multi-location-product-and-inventory-management-pro'); ?>"></div>
            <p class="description"><?php esc_html_e('Click on the map or drag the pin to set the warehouse location. Address fields update automatically.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            <p class="description mulopimfwc-location-map-feedback" style="display:none;"></p>
        </div>

        <div class="form-field">
            <label for="street_address"><?php esc_html_e('Street Address', 'multi-location-product-and-inventory-management-pro'); ?></label>
            <input type="text" name="street_address" id="street_address" value="" />
            <p class="description"><?php esc_html_e('Enter street address for this location', 'multi-location-product-and-inventory-management-pro'); ?></p>
        </div>

        <div class="form-field">
            <label for="city"><?php esc_html_e('City', 'multi-location-product-and-inventory-management-pro'); ?></label>
            <input type="text" name="city" id="city" value="" />
            <p class="description"><?php esc_html_e('Enter city for this location', 'multi-location-product-and-inventory-management-pro'); ?></p>
        </div>

        <div class="form-field">
            <label for="state"><?php esc_html_e('State', 'multi-location-product-and-inventory-management-pro'); ?></label>
            <input type="text" name="state" id="state" value="" />
            <p class="description"><?php esc_html_e('Enter state for this location', 'multi-location-product-and-inventory-management-pro'); ?></p>
        </div>

        <div class="form-field">
            <label for="postal_code"><?php esc_html_e('Postal Code', 'multi-location-product-and-inventory-management-pro'); ?></label>
            <input type="text" name="postal_code" id="postal_code" value="" />
            <p class="description"><?php esc_html_e('Enter postal code for this location', 'multi-location-product-and-inventory-management-pro'); ?></p>
        </div>

        <div class="form-field">
            <label for="country"><?php esc_html_e('Country', 'multi-location-product-and-inventory-management-pro'); ?></label>
            <input type="text" name="country" id="country" value="" />
            <p class="description"><?php esc_html_e('Enter country for this location', 'multi-location-product-and-inventory-management-pro'); ?></p>
        </div>

        <div class="form-field">
            <label for="email"><?php esc_html_e('Email', 'multi-location-product-and-inventory-management-pro'); ?></label>
            <input type="email" name="email" id="email" value="" />
            <p class="description"><?php esc_html_e('Enter email for this location', 'multi-location-product-and-inventory-management-pro'); ?></p>
        </div>

        <div class="form-field">
            <label for="phone"><?php esc_html_e('Phone', 'multi-location-product-and-inventory-management-pro'); ?></label>
            <input type="tel" name="phone" id="phone" value="" />
            <p class="description"><?php esc_html_e('Enter phone for this location', 'multi-location-product-and-inventory-management-pro'); ?></p>
        </div>

        <div class="form-field">
            <label for="low_stock_threshold"><?php esc_html_e('Low Stock Threshold', 'multi-location-product-and-inventory-management-pro'); ?></label>
            <input type="number" name="low_stock_threshold" id="low_stock_threshold" value="" min="0" step="1" />
            <p class="description"><?php esc_html_e('Alert threshold for low stock at this location (overrides global default).', 'multi-location-product-and-inventory-management-pro'); ?></p>
        </div>

        <div class="form-field">
            <label for="out_of_stock_threshold"><?php esc_html_e('Out of Stock Threshold', 'multi-location-product-and-inventory-management-pro'); ?></label>
            <input type="number" name="out_of_stock_threshold" id="out_of_stock_threshold" value="" min="0" step="1" />
            <p class="description"><?php esc_html_e('Alert threshold for out-of-stock at this location (overrides global default).', 'multi-location-product-and-inventory-management-pro'); ?></p>
        </div>

        <!-- Latitude / Longitude -->
        <div class="form-field">
            <label for="latitude"><?php esc_html_e('Latitude', 'multi-location-product-and-inventory-management-pro'); ?></label>
            <input type="text" name="latitude" id="latitude" value="" />
            <p class="description"><?php esc_html_e('Decimal latitude (e.g. 23.7808)', 'multi-location-product-and-inventory-management-pro'); ?></p>
        </div>

        <div class="form-field">
            <label for="longitude"><?php esc_html_e('Longitude', 'multi-location-product-and-inventory-management-pro'); ?></label>
            <input type="text" name="longitude" id="longitude" value="" />
            <p class="description"><?php esc_html_e('Decimal longitude (e.g. 90.2792)', 'multi-location-product-and-inventory-management-pro'); ?></p>
        </div>

        <!-- Logo -->
        <div class="form-field mulopimfwc-media-wrap">
            <label><?php esc_html_e('Logo', 'multi-location-product-and-inventory-management-pro'); ?></label>
            <input type="hidden" name="logo_id" class="mulopimfwc-logo-id" value="">
            <div class="mulopimfwc-logo-preview" style="margin:6px 0;"></div>
            <p>
                <span class="button mulopimfwc-upload-logo"><?php esc_html_e('Upload/Choose Logo', 'multi-location-product-and-inventory-management-pro'); ?></span>
                <span class="button button-link-delete mulopimfwc-remove-logo"><?php esc_html_e('Remove', 'multi-location-product-and-inventory-management-pro'); ?></span>
            </p>
        </div>

        <!-- Gallery -->
        <div class="form-field mulopimfwc-media-wrap">
            <label><?php esc_html_e('Gallery', 'multi-location-product-and-inventory-management-pro'); ?></label>
            <input type="hidden" name="gallery_ids" class="mulopimfwc-gallery-ids" value="">
            <div class="mulopimfwc-gallery-preview" style="margin:6px 0;display:flex;flex-wrap:wrap;gap:4px;"></div>
            <p>
                <span class="button mulopimfwc-upload-gallery"><?php esc_html_e('Add Images', 'multi-location-product-and-inventory-management-pro'); ?></span>
                <span class="button button-link-delete mulopimfwc-clear-gallery"><?php esc_html_e('Clear', 'multi-location-product-and-inventory-management-pro'); ?></span>
            </p>
        </div>

        <!-- Business Hours -->
        <?php
        $def = $this->get_default_business_hours();
        $tzs = timezone_identifiers_list(); // basic list
        $days_labels = [
            'mon' => __('Monday', 'multi-location-product-and-inventory-management-pro'),
            'tue' => __('Tuesday', 'multi-location-product-and-inventory-management-pro'),
            'wed' => __('Wednesday', 'multi-location-product-and-inventory-management-pro'),
            'thu' => __('Thursday', 'multi-location-product-and-inventory-management-pro'),
            'fri' => __('Friday', 'multi-location-product-and-inventory-management-pro'),
            'sat' => __('Saturday', 'multi-location-product-and-inventory-management-pro'),
            'sun' => __('Sunday', 'multi-location-product-and-inventory-management-pro'),
        ];
        ?>
        <div class="form-field">
            <label><?php esc_html_e('Business Hours', 'multi-location-product-and-inventory-management-pro'); ?></label>
            <div style="border:1px solid #ddd;border-radius:6px;padding:10px;max-width:660px;">
                <p class="description" style="margin-top:0;"><?php esc_html_e('Set opening hours for each day. Use “Closed” for off days or “24 hours” for round-the-clock.', 'multi-location-product-and-inventory-management-pro'); ?></p>

                <p>
                    <strong><?php esc_html_e('Timezone', 'multi-location-product-and-inventory-management-pro'); ?>:</strong>
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
                                            <span><?php esc_html_e('Closed', 'multi-location-product-and-inventory-management-pro'); ?></span>
                                        </label>
                                        <label style="display:flex;align-items:center;gap:4px;cursor:pointer;">
                                            <input type="radio" name="bh[days][<?php echo esc_attr($key); ?>][mode]" value="all_day" class="mulopimfwc-bh-mode" data-day="<?php echo esc_attr($key); ?>" <?php checked($default_mode, 'all_day'); ?>>
                                            <span><?php esc_html_e('24 Hours', 'multi-location-product-and-inventory-management-pro'); ?></span>
                                        </label>
                                        <label style="display:flex;align-items:center;gap:4px;cursor:pointer;">
                                            <input type="radio" name="bh[days][<?php echo esc_attr($key); ?>][mode]" value="custom" class="mulopimfwc-bh-mode" data-day="<?php echo esc_attr($key); ?>" <?php checked($default_mode, 'custom'); ?>>
                                            <span><?php esc_html_e('Custom Hours', 'multi-location-product-and-inventory-management-pro'); ?></span>
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
            </div>
        </div>

        <!-- Shipping Zones -->
        <?php $zones = $this->get_shipping_zones_options(); ?>
        <div class="form-field">
            <label for="shipping_zones"><?php esc_html_e('Shipping Zones', 'multi-location-product-and-inventory-management-pro'); ?></label>
            <select name="shipping_zones[]" id="shipping_zones" class="mulopimfwc-select2" multiple style="min-width: 320px;" data-placeholder="<?php esc_attr_e('Select shipping zones...', 'multi-location-product-and-inventory-management-pro'); ?>">
                <?php foreach ($zones as $zid => $zname): ?>
                    <option value="<?php echo esc_attr($zid); ?>"><?php echo esc_html($zname); ?></option>
                <?php endforeach; ?>
            </select>
            <p class="description"><?php esc_html_e('Choose the shipping zones served by this location.', 'multi-location-product-and-inventory-management-pro'); ?></p>
        </div>

        <!-- Shipping Methods (instances) -->
        <?php $zone_methods = $this->get_shipping_methods_grouped_by_zone(); ?>
        <div class="form-field">
            <label for="shipping_methods"><?php esc_html_e('Shipping Methods', 'multi-location-product-and-inventory-management-pro'); ?></label>
            <select name="shipping_methods[]" id="shipping_methods" class="mulopimfwc-select2" multiple style="min-width: 420px;" data-placeholder="<?php esc_attr_e('Select shipping methods...', 'multi-location-product-and-inventory-management-pro'); ?>">
                <?php foreach ($zone_methods as $zid => $methods): ?>
                    <?php if (!empty($methods)): ?>
                        <optgroup label="<?php echo esc_attr(sprintf(/* translators: %s: shipping zone name or ID */__('Zone: %s', 'multi-location-product-and-inventory-management-pro'), $zones[$zid] ?? $zid)); ?>">
                            <?php foreach ($methods as $instance_id => $label): ?>
                                <option value="<?php echo esc_attr($zid . ':' . $instance_id); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
            <p class="description"><?php esc_html_e('Select enabled shipping method instances (grouped by zone).', 'multi-location-product-and-inventory-management-pro'); ?></p>
        </div>

        <!-- Payment Methods -->
        <?php $payments = $this->get_payment_method_options(); ?>
        <div class="form-field">
            <label for="payment_methods"><?php esc_html_e('Payment Methods', 'multi-location-product-and-inventory-management-pro'); ?></label>
            <select name="payment_methods[]" id="payment_methods" class="mulopimfwc-select2" multiple style="min-width: 320px;" data-placeholder="<?php esc_attr_e('Select payment methods...', 'multi-location-product-and-inventory-management-pro'); ?>">
                <?php foreach ($payments as $pid => $ptitle): ?>
                    <option value="<?php echo esc_attr($pid); ?>"><?php echo esc_html($ptitle); ?></option>
                <?php endforeach; ?>
            </select>
            <p class="description"><?php esc_html_e('Choose allowed payment gateways for this location.', 'multi-location-product-and-inventory-management-pro'); ?></p>
        </div>

        <!-- Pickup Locations -->
        <?php $pickup_locations = $this->get_pickup_locations(); ?>
        <?php if (!empty($pickup_locations)): ?>
            <div class="form-field">
                <label for="pickup_locations"><?php esc_html_e('Pickup Locations', 'multi-location-product-and-inventory-management-pro'); ?></label>
                <select name="pickup_locations[]" id="pickup_locations" class="mulopimfwc-select2" multiple style="min-width: 320px;" data-placeholder="<?php esc_attr_e('Select pickup locations...', 'multi-location-product-and-inventory-management-pro'); ?>">
                    <?php foreach ($pickup_locations as $pid => $ptitle): ?>
                        <option value="<?php echo esc_attr($pid); ?>"><?php echo esc_html($ptitle); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e('Choose allowed pickup locations for this store location.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            </div>
        <?php endif; ?>

        <?php
        $currencies = $this->get_currency_options();
        $currency_positions = $this->get_currency_position_options();
        $default_currency_code = $this->get_default_currency_code();
        $default_currency_label = isset($currencies[$default_currency_code])
            ? ($default_currency_code . ' - ' . $currencies[$default_currency_code])
            : $default_currency_code;
        $default_currency_position = $this->get_default_currency_position();
        $rate_mode_options = $this->get_location_rate_mode_options();
        ?>
        <div class="form-field">
            <label for="location_currency"><?php esc_html_e('Currency', 'multi-location-product-and-inventory-management-pro'); ?></label>
            <select name="location_currency" id="location_currency" class="mulopimfwc-select2" style="min-width: 320px;" data-placeholder="<?php esc_attr_e('Search currency...', 'multi-location-product-and-inventory-management-pro'); ?>">
                <option value="" selected><?php echo esc_html(sprintf(/* translators: %s: default currency label (e.g. "USD - US Dollar") */__('Default Value (%s) - No Changes', 'multi-location-product-and-inventory-management-pro'), $default_currency_label)); ?></option>
                <?php foreach ($currencies as $currency_code => $currency_name): ?>
                    <?php
                    $currency_code = strtoupper((string) $currency_code);
                    $currency_symbol = $this->get_unfiltered_currency_symbol($currency_code);
                    $currency_label = $currency_code . ' - ' . $currency_name;
                    if ($currency_symbol !== '') {
                        $currency_label .= ' (' . $currency_symbol . ')';
                    }
                    ?>
                    <option value="<?php echo esc_attr($currency_code); ?>"><?php echo esc_html($currency_label); ?></option>
                <?php endforeach; ?>
            </select>
            <p class="description"><?php esc_html_e('Select currency for this location. ', 'multi-location-product-and-inventory-management-pro'); ?></p>
        </div>

        <div class="form-field">
            <label for="location_currency_position"><?php esc_html_e('Currency Position', 'multi-location-product-and-inventory-management-pro'); ?></label>
            <select name="location_currency_position" id="location_currency_position" style="min-width: 220px;">
                <option value=""><?php echo esc_html(sprintf(/* translators: %s: currency position label */__('Default (%s)', 'multi-location-product-and-inventory-management-pro'), $currency_positions[$default_currency_position])); ?></option>
                <?php foreach ($currency_positions as $position_key => $position_label): ?>
                    <option value="<?php echo esc_attr($position_key); ?>"><?php echo esc_html($position_label); ?></option>
                <?php endforeach; ?>
            </select>
            <p class="description"><?php esc_html_e('Set where currency symbol appears for this location.', 'multi-location-product-and-inventory-management-pro'); ?></p>
        </div>

        <div class="form-field">
            <label for="location_currency_rate"><?php esc_html_e('Rate', 'multi-location-product-and-inventory-management-pro'); ?></label>
            <div class="mulopimfwc-currency-rate-wrap" style="display:flex;align-items:center;gap:8px;max-width:430px;">
                <input
                    type="number"
                    name="location_currency_rate"
                    id="location_currency_rate"
                    value="1"
                    min="0.000001"
                    step="0.000001"
                    class="mulopimfwc-currency-rate-value"
                    style="width:140px;" />
                <select name="location_currency_rate_mode" id="location_currency_rate_mode" class="mulopimfwc-currency-rate-mode" style="width:110px;">
                    <?php foreach ($rate_mode_options as $mode_key => $mode_label): ?>
                        <option value="<?php echo esc_attr($mode_key); ?>" <?php selected($mode_key, 'auto'); ?>><?php echo esc_html($mode_label); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="button mulopimfwc-sync-rate" title="<?php esc_attr_e('Sync latest rate', 'multi-location-product-and-inventory-management-pro'); ?>" aria-label="<?php esc_attr_e('Sync latest rate', 'multi-location-product-and-inventory-management-pro'); ?>" style="min-width:36px;padding:0 8px;">
                    <span class="dashicons dashicons-update" style="margin-top:3px;"></span>
                </button>
            </div>
            <p class="description">
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: %s: WooCommerce default currency code */
                        __('Rate from WooCommerce currency (%s) to selected currency. Use Sync when Auto is selected.', 'multi-location-product-and-inventory-management-pro'),
                        $default_currency_code
                    )
                );
                ?>
            </p>
            <p class="description mulopimfwc-currency-rate-status" style="display:none;"></p>
        </div>

        <!-- Tax Class -->
        <?php $tax_classes = $this->get_tax_class_options(); ?>
        <div class="form-field">
            <label for="tax_class"><?php esc_html_e('Tax Class', 'multi-location-product-and-inventory-management-pro'); ?></label>
            <select name="tax_class" id="tax_class" style="min-width: 220px;">
                <?php foreach ($tax_classes as $key => $label): ?>
                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
            <p class="description"><?php esc_html_e('Select default tax class for this location.', 'multi-location-product-and-inventory-management-pro'); ?></p>
        </div>

        <div class="form-field">
            <label for="display_order"><?php esc_html_e('Display Order', 'multi-location-product-and-inventory-management-pro'); ?></label>
            <input type="number" name="display_order" id="display_order" value="" min="0" step="1" />
            <p class="description"><?php esc_html_e('Enter a number to control the order of this location (smaller numbers appear first)', 'multi-location-product-and-inventory-management-pro'); ?></p>
        </div>

        <div class="form-field">
            <label for="is_active"><?php esc_html_e('Status', 'multi-location-product-and-inventory-management-pro'); ?></label>
            <select name="is_active" id="is_active">
                <option value="1" selected><?php esc_html_e('Active', 'multi-location-product-and-inventory-management-pro'); ?></option>
                <option value="0"><?php esc_html_e('Inactive', 'multi-location-product-and-inventory-management-pro'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Set whether this location is active or inactive', 'multi-location-product-and-inventory-management-pro'); ?></p>
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
        $location_aliases = get_term_meta($term->term_id, 'location_aliases', true);
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
        $location_currency = (string) get_term_meta($term->term_id, 'location_currency', true);
        $location_currency_position = (string) get_term_meta($term->term_id, 'location_currency_position', true);
        $location_currency_rate = $this->format_location_currency_rate_input(get_term_meta($term->term_id, 'location_currency_rate', true));
        if ($location_currency_rate === '') {
            $location_currency_rate = '1';
        }
        $location_currency_rate_mode = $this->normalize_location_rate_mode(get_term_meta($term->term_id, 'location_currency_rate_mode', true));

        $zones        = $this->get_shipping_zones_options();
        $zone_methods = $this->get_shipping_methods_grouped_by_zone();
        $payments     = $this->get_payment_method_options();
        $tax_classes  = $this->get_tax_class_options();
        $currencies   = $this->get_currency_options();
        $currency_positions = $this->get_currency_position_options();
        $default_currency_code = $this->get_default_currency_code();
        $default_currency_label = isset($currencies[$default_currency_code])
            ? ($default_currency_code . ' - ' . $currencies[$default_currency_code])
            : $default_currency_code;
        $default_currency_position = $this->get_default_currency_position();
        $rate_mode_options = $this->get_location_rate_mode_options();
        $location_aliases_text = $this->aliases_to_textarea($location_aliases);

        $logo_src = $logo_id ? wp_get_attachment_image_url($logo_id, 'thumbnail') : '';
        $gallery_ids_csv = is_array($gallery_ids) ? implode(',', $gallery_ids) : (string) $gallery_ids;
        $gallery_thumb_urls = array();
        if ($gallery_ids_csv) {
            foreach (array_filter(array_map('absint', explode(',', $gallery_ids_csv))) as $gid) {
                $src = wp_get_attachment_image_url($gid, 'thumbnail');
                if ($src) {
                    $gallery_thumb_urls[] = $src;
                }
            }
        }
    ?>
        <tr class="form-field mulopimfwc-location-map-wrap">
            <th scope="row"><label><?php esc_html_e('Location Map', 'multi-location-product-and-inventory-management-pro'); ?></label></th>
            <td>
                <div class="mulopimfwc-location-map-controls">
                    <input type="text" class="mulopimfwc-location-search" placeholder="<?php esc_attr_e('Search address...', 'multi-location-product-and-inventory-management-pro'); ?>" />
                    <button type="button" class="button mulopimfwc-location-search-btn"><?php esc_html_e('Search', 'multi-location-product-and-inventory-management-pro'); ?></button>
                </div>
                <div class="mulopimfwc-location-map" aria-label="<?php esc_attr_e('Location map', 'multi-location-product-and-inventory-management-pro'); ?>"></div>
                <p class="description"><?php esc_html_e('Click on the map or drag the pin to set the warehouse location. Address fields update automatically.', 'multi-location-product-and-inventory-management-pro'); ?></p>
                <p class="description mulopimfwc-location-map-feedback" style="display:none;"></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="street_address"><?php esc_html_e('Street Address', 'multi-location-product-and-inventory-management-pro'); ?></label></th>
            <td>
                <input type="text" name="street_address" id="street_address" value="<?php echo esc_attr($street_address); ?>" />
                <p class="description"><?php esc_html_e('Enter street address for this location', 'multi-location-product-and-inventory-management-pro'); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="city"><?php esc_html_e('City', 'multi-location-product-and-inventory-management-pro'); ?></label></th>
            <td>
                <input type="text" name="city" id="city" value="<?php echo esc_attr($city); ?>" />
                <p class="description"><?php esc_html_e('Enter city for this location', 'multi-location-product-and-inventory-management-pro'); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="state"><?php esc_html_e('State', 'multi-location-product-and-inventory-management-pro'); ?></label></th>
            <td>
                <input type="text" name="state" id="state" value="<?php echo esc_attr($state); ?>" />
                <p class="description"><?php esc_html_e('Enter state for this location', 'multi-location-product-and-inventory-management-pro'); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="postal_code"><?php esc_html_e('Postal Code', 'multi-location-product-and-inventory-management-pro'); ?></label></th>
            <td>
                <input type="text" name="postal_code" id="postal_code" value="<?php echo esc_attr($postal_code); ?>" />
                <p class="description"><?php esc_html_e('Enter postal code for this location', 'multi-location-product-and-inventory-management-pro'); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="country"><?php esc_html_e('Country', 'multi-location-product-and-inventory-management-pro'); ?></label></th>
            <td>
                <input type="text" name="country" id="country" value="<?php echo esc_attr($country); ?>" />
                <p class="description"><?php esc_html_e('Enter country for this location', 'multi-location-product-and-inventory-management-pro'); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="email"><?php esc_html_e('Email', 'multi-location-product-and-inventory-management-pro'); ?></label></th>
            <td>
                <input type="email" name="email" id="email" value="<?php echo esc_attr($email); ?>" />
                <p class="description"><?php esc_html_e('Enter email for this location', 'multi-location-product-and-inventory-management-pro'); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="phone"><?php esc_html_e('Phone', 'multi-location-product-and-inventory-management-pro'); ?></label></th>
            <td>
                <input type="tel" name="phone" id="phone" value="<?php echo esc_attr($phone); ?>" />
                <p class="description"><?php esc_html_e('Enter phone for this location', 'multi-location-product-and-inventory-management-pro'); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="low_stock_threshold"><?php esc_html_e('Low Stock Threshold', 'multi-location-product-and-inventory-management-pro'); ?></label></th>
            <td>
                <input type="number" name="low_stock_threshold" id="low_stock_threshold" value="<?php echo esc_attr($low_stock_threshold); ?>" min="0" step="1" />
                <p class="description"><?php esc_html_e('Alert threshold for low stock at this location (overrides global default).', 'multi-location-product-and-inventory-management-pro'); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="out_of_stock_threshold"><?php esc_html_e('Out of Stock Threshold', 'multi-location-product-and-inventory-management-pro'); ?></label></th>
            <td>
                <input type="number" name="out_of_stock_threshold" id="out_of_stock_threshold" value="<?php echo esc_attr($out_of_stock_threshold); ?>" min="0" step="1" />
                <p class="description"><?php esc_html_e('Alert threshold for out-of-stock at this location (overrides global default).', 'multi-location-product-and-inventory-management-pro'); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="latitude"><?php esc_html_e('Latitude', 'multi-location-product-and-inventory-management-pro'); ?></label></th>
            <td>
                <input type="text" name="latitude" id="latitude" value="<?php echo esc_attr($latitude); ?>" />
                <p class="description"><?php esc_html_e('Decimal latitude (e.g. 23.7808)', 'multi-location-product-and-inventory-management-pro'); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="longitude"><?php esc_html_e('Longitude', 'multi-location-product-and-inventory-management-pro'); ?></label></th>
            <td>
                <input type="text" name="longitude" id="longitude" value="<?php echo esc_attr($longitude); ?>" />
                <p class="description"><?php esc_html_e('Decimal longitude (e.g. 90.2792)', 'multi-location-product-and-inventory-management-pro'); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label><?php esc_html_e('Logo', 'multi-location-product-and-inventory-management-pro'); ?></label></th>
            <td class="mulopimfwc-media-wrap">
                <input type="hidden" name="logo_id" class="mulopimfwc-logo-id" value="<?php echo esc_attr($logo_id); ?>">
                <div class="mulopimfwc-logo-preview" style="margin:6px 0;"><?php
                                                                            if ($logo_src) echo '<img src="' . esc_url($logo_src) . '" style="max-width:80px;height:auto;border:1px solid #ddd;border-radius:4px;">';
                                                                            ?></div>
                <p>
                    <span class="button mulopimfwc-upload-logo"><?php esc_html_e('Upload/Choose Logo', 'multi-location-product-and-inventory-management-pro'); ?></span>
                    <span class="button button-link-delete mulopimfwc-remove-logo"><?php esc_html_e('Remove', 'multi-location-product-and-inventory-management-pro'); ?></span>
                </p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label><?php esc_html_e('Gallery', 'multi-location-product-and-inventory-management-pro'); ?></label></th>
            <td class="mulopimfwc-media-wrap">
                <input type="hidden" name="gallery_ids" class="mulopimfwc-gallery-ids" value="<?php echo esc_attr($gallery_ids_csv); ?>">
                <div class="mulopimfwc-gallery-preview" style="margin:6px 0;display:flex;flex-wrap:wrap;gap:4px;">
                    <?php foreach ($gallery_thumb_urls as $gallery_thumb_url) : ?>
                        <img src="<?php echo esc_url($gallery_thumb_url); ?>" alt="" style="width:60px;height:auto;margin:2px;border:1px solid #ddd;border-radius:3px;">
                    <?php endforeach; ?>
                </div>
                <p>
                    <span class="button mulopimfwc-upload-gallery"><?php esc_html_e('Add Images', 'multi-location-product-and-inventory-management-pro'); ?></span>
                    <span class="button button-link-delete mulopimfwc-clear-gallery"><?php esc_html_e('Clear', 'multi-location-product-and-inventory-management-pro'); ?></span>
                </p>
            </td>
        </tr>

        <?php
        $bh = $this->get_business_hours($term->term_id);
        $tzs = timezone_identifiers_list();
        $days_labels = [
            'mon' => __('Monday', 'multi-location-product-and-inventory-management-pro'),
            'tue' => __('Tuesday', 'multi-location-product-and-inventory-management-pro'),
            'wed' => __('Wednesday', 'multi-location-product-and-inventory-management-pro'),
            'thu' => __('Thursday', 'multi-location-product-and-inventory-management-pro'),
            'fri' => __('Friday', 'multi-location-product-and-inventory-management-pro'),
            'sat' => __('Saturday', 'multi-location-product-and-inventory-management-pro'),
            'sun' => __('Sunday', 'multi-location-product-and-inventory-management-pro'),
        ];
        ?>
        <tr class="form-field">
            <th scope="row"><label><?php esc_html_e('Business Hours', 'multi-location-product-and-inventory-management-pro'); ?></label></th>
            <td>
                <div style="border:1px solid #ddd;border-radius:6px;padding:10px;max-width:660px;">
                    <p class="description" style="margin-top:0;"><?php esc_html_e('Set opening hours for each day. Use “Closed” for off days or “24 hours” for round-the-clock.', 'multi-location-product-and-inventory-management-pro'); ?></p>

                    <p>
                        <strong><?php esc_html_e('Timezone', 'multi-location-product-and-inventory-management-pro'); ?>:</strong>
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
                                                <span><?php esc_html_e('Closed', 'multi-location-product-and-inventory-management-pro'); ?></span>
                                            </label>
                                            <label style="display:flex;align-items:center;gap:4px;cursor:pointer;">
                                                <input type="radio" name="bh[days][<?php echo esc_attr($key); ?>][mode]" value="all_day" class="mulopimfwc-bh-mode" data-day="<?php echo esc_attr($key); ?>" <?php checked($current_mode, 'all_day'); ?>>
                                                <span><?php esc_html_e('24 Hours', 'multi-location-product-and-inventory-management-pro'); ?></span>
                                            </label>
                                            <label style="display:flex;align-items:center;gap:4px;cursor:pointer;">
                                                <input type="radio" name="bh[days][<?php echo esc_attr($key); ?>][mode]" value="custom" class="mulopimfwc-bh-mode" data-day="<?php echo esc_attr($key); ?>" <?php checked($current_mode, 'custom'); ?>>
                                                <span><?php esc_html_e('Custom Hours', 'multi-location-product-and-inventory-management-pro'); ?></span>
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
                </div>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="shipping_zones"><?php esc_html_e('Shipping Zones', 'multi-location-product-and-inventory-management-pro'); ?></label></th>
            <td>
                <select name="shipping_zones[]" id="shipping_zones" class="mulopimfwc-select2" multiple style="min-width: 320px;" data-placeholder="<?php esc_attr_e('Select shipping zones...', 'multi-location-product-and-inventory-management-pro'); ?>">
                    <?php foreach ($zones as $zid => $zname): ?>
                        <option value="<?php echo esc_attr($zid); ?>" <?php selected(in_array((string)$zid, array_map('strval', (array)$sel_zones), true)); ?>>
                            <?php echo esc_html($zname); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e('Choose the shipping zones served by this location.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="shipping_methods"><?php esc_html_e('Shipping Methods', 'multi-location-product-and-inventory-management-pro'); ?></label></th>
            <td>
                <select name="shipping_methods[]" id="shipping_methods" class="mulopimfwc-select2" multiple style="min-width: 420px;" data-placeholder="<?php esc_attr_e('Select shipping methods...', 'multi-location-product-and-inventory-management-pro'); ?>">
                    <?php foreach ($zone_methods as $zid => $methods): if (empty($methods)) continue; ?>
                        <optgroup label="<?php echo esc_attr(sprintf(/* translators: %s: shipping zone name or ID */__('Zone: %s', 'multi-location-product-and-inventory-management-pro'), $zones[$zid] ?? $zid)); ?>">
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
                <p class="description"><?php esc_html_e('Select enabled shipping method instances (grouped by zone).', 'multi-location-product-and-inventory-management-pro'); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="payment_methods"><?php esc_html_e('Payment Methods', 'multi-location-product-and-inventory-management-pro'); ?></label></th>
            <td>
                <select name="payment_methods[]" id="payment_methods" class="mulopimfwc-select2" multiple style="min-width: 320px;" data-placeholder="<?php esc_attr_e('Select payment methods...', 'multi-location-product-and-inventory-management-pro'); ?>">
                    <?php foreach ($payments as $pid => $ptitle): ?>
                        <option value="<?php echo esc_attr($pid); ?>" <?php selected(in_array($pid, (array)$sel_payments, true)); ?>>
                            <?php echo esc_html($ptitle); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e('Choose allowed payment gateways for this location.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            </td>
        </tr>

        <!-- Pickup Locations -->
        <?php
        $pickup_locations = $this->get_pickup_locations();
        $sel_pickup = (array) get_term_meta($term->term_id, 'pickup_locations', true);
        ?>
        <?php if (!empty($pickup_locations)): ?>
            <tr class="form-field">
                <th scope="row"><label for="pickup_locations"><?php esc_html_e('Pickup Locations', 'multi-location-product-and-inventory-management-pro'); ?></label></th>
                <td>
                    <select name="pickup_locations[]" id="pickup_locations" class="mulopimfwc-select2" multiple style="min-width: 320px;" data-placeholder="<?php esc_attr_e('Select pickup locations...', 'multi-location-product-and-inventory-management-pro'); ?>">
                        <?php foreach ($pickup_locations as $pid => $ptitle): ?>
                            <option value="<?php echo esc_attr($pid); ?>" <?php selected(in_array($pid, (array)$sel_pickup, true)); ?>>
                                <?php echo esc_html($ptitle); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e('Choose allowed pickup locations for this store location.', 'multi-location-product-and-inventory-management-pro'); ?></p>
                </td>
            </tr>
        <?php endif; ?>

        <tr class="form-field">
            <th scope="row"><label for="location_currency"><?php esc_html_e('Currency', 'multi-location-product-and-inventory-management-pro'); ?></label></th>
            <td>
                <select name="location_currency" id="location_currency" class="mulopimfwc-select2" style="min-width: 320px;" data-placeholder="<?php esc_attr_e('Search currency...', 'multi-location-product-and-inventory-management-pro'); ?>">
                    <option value="" <?php selected((string) $location_currency, ''); ?>><?php echo esc_html(sprintf(/* translators: %s: default currency label (e.g. "USD - US Dollar") */__('Default Value (%s) - No Changes', 'multi-location-product-and-inventory-management-pro'), $default_currency_label)); ?></option>
                    <?php foreach ($currencies as $currency_code => $currency_name): ?>
                        <?php
                        $currency_code = strtoupper((string) $currency_code);
                        $currency_symbol = $this->get_unfiltered_currency_symbol($currency_code);
                        $currency_label = $currency_code . ' - ' . $currency_name;
                        if ($currency_symbol !== '') {
                            $currency_label .= ' (' . $currency_symbol . ')';
                        }
                        ?>
                        <option value="<?php echo esc_attr($currency_code); ?>" <?php selected(strtoupper((string) $location_currency), $currency_code); ?>>
                            <?php echo esc_html($currency_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e('Select currency for this location. ', 'multi-location-product-and-inventory-management-pro'); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="location_currency_position"><?php esc_html_e('Currency Position', 'multi-location-product-and-inventory-management-pro'); ?></label></th>
            <td>
                <select name="location_currency_position" id="location_currency_position" style="min-width: 220px;">
                    <option value=""><?php echo esc_html(sprintf(/* translators: %s: currency position label */__('Default (%s)', 'multi-location-product-and-inventory-management-pro'), $currency_positions[$default_currency_position])); ?></option>
                    <?php foreach ($currency_positions as $position_key => $position_label): ?>
                        <option value="<?php echo esc_attr($position_key); ?>" <?php selected($location_currency_position, $position_key); ?>>
                            <?php echo esc_html($position_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e('Set where currency symbol appears for this location.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="location_currency_rate"><?php esc_html_e('Rate', 'multi-location-product-and-inventory-management-pro'); ?></label></th>
            <td>
                <div class="mulopimfwc-currency-rate-wrap" style="display:flex;align-items:center;gap:8px;max-width:430px;">
                    <input
                        type="number"
                        name="location_currency_rate"
                        id="location_currency_rate"
                        value="<?php echo esc_attr($location_currency_rate); ?>"
                        min="0.000001"
                        step="0.000001"
                        class="mulopimfwc-currency-rate-value"
                        style="width:140px;" />
                    <select name="location_currency_rate_mode" id="location_currency_rate_mode" class="mulopimfwc-currency-rate-mode" style="width:110px;">
                        <?php foreach ($rate_mode_options as $mode_key => $mode_label): ?>
                            <option value="<?php echo esc_attr($mode_key); ?>" <?php selected($location_currency_rate_mode, $mode_key); ?>><?php echo esc_html($mode_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="button mulopimfwc-sync-rate" title="<?php esc_attr_e('Sync latest rate', 'multi-location-product-and-inventory-management-pro'); ?>" aria-label="<?php esc_attr_e('Sync latest rate', 'multi-location-product-and-inventory-management-pro'); ?>" style="min-width:36px;padding:0 8px;">
                        <span class="dashicons dashicons-update" style="margin-top:3px;"></span>
                    </button>
                </div>
                <p class="description">
                    <?php
                    echo esc_html(
                        sprintf(
                            /* translators: %s: WooCommerce default currency code */
                            __('Rate from WooCommerce currency (%s) to selected currency. Use Sync when Auto is selected.', 'multi-location-product-and-inventory-management-pro'),
                            $default_currency_code
                        )
                    );
                    ?>
                </p>
                <p class="description mulopimfwc-currency-rate-status" style="display:none;"></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="tax_class"><?php esc_html_e('Tax Class', 'multi-location-product-and-inventory-management-pro'); ?></label></th>
            <td>
                <select name="tax_class" id="tax_class" style="min-width: 220px;">
                    <?php foreach ($tax_classes as $key => $label): ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected((string)$sel_tax_class === (string)$key); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e('Select default tax class for this location.', 'multi-location-product-and-inventory-management-pro'); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="display_order"><?php esc_html_e('Display Order', 'multi-location-product-and-inventory-management-pro'); ?></label></th>
            <td>
                <input type="number" name="display_order" id="display_order" value="<?php echo esc_attr($display_order); ?>" min="0" step="1" />
                <p class="description"><?php esc_html_e('Enter a number to control the order of this location (smaller numbers appear first)', 'multi-location-product-and-inventory-management-pro'); ?></p>
            </td>
        </tr>

        <?php
        $is_active = get_term_meta($term->term_id, 'is_active', true);
        $is_active = ($is_active === '' || $is_active === '1' || $is_active === true) ? '1' : '0';
        ?>
        <tr class="form-field">
            <th scope="row"><label for="is_active"><?php esc_html_e('Status', 'multi-location-product-and-inventory-management-pro'); ?></label></th>
            <td>
                <select name="is_active" id="is_active">
                    <option value="1" <?php selected($is_active, '1'); ?>><?php esc_html_e('Active', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <option value="0" <?php selected($is_active, '0'); ?>><?php esc_html_e('Inactive', 'multi-location-product-and-inventory-management-pro'); ?></option>
                </select>
                <p class="description"><?php esc_html_e('Set whether this location is active or inactive', 'multi-location-product-and-inventory-management-pro'); ?></p>
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

        // Security: Verify nonce (WordPress taxonomy forms use _wpnonce or _wpnonce_add-tag)
        $nonce_valid = false;
        if (isset($_POST['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'update-tag_' . $term_id)) {
            $nonce_valid = true;
        }
        if (!$nonce_valid && isset($_POST['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'add-tag')) {
            $nonce_valid = true;
        }
        if (!$nonce_valid && isset($_POST['_wpnonce_add-tag']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce_add-tag'])), 'add-tag')) {
            $nonce_valid = true;
        }
        if (!$nonce_valid) {
            return;
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

        if (isset($_POST['location_aliases'])) {
            $aliases = $this->normalize_location_aliases(wp_unslash($_POST['location_aliases']));
            if (!empty($aliases)) {
                update_term_meta($term_id, 'location_aliases', $aliases);
            } else {
                delete_term_meta($term_id, 'location_aliases');
            }
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

        // Location currency (empty means inherit global WooCommerce currency)
        if (isset($_POST['location_currency'])) {
            $currency = strtoupper(sanitize_text_field(wp_unslash($_POST['location_currency'])));
            $valid_currency_codes = array_keys($this->get_currency_options());

            if ($currency !== '' && in_array($currency, $valid_currency_codes, true)) {
                update_term_meta($term_id, 'location_currency', $currency);
            } else {
                delete_term_meta($term_id, 'location_currency');
            }
        }

        // Location currency symbol position (empty means inherit global WooCommerce position)
        if (isset($_POST['location_currency_position'])) {
            $currency_position = sanitize_key((string) wp_unslash($_POST['location_currency_position']));
            $valid_positions = array_keys($this->get_currency_position_options());

            if ($currency_position !== '' && in_array($currency_position, $valid_positions, true)) {
                update_term_meta($term_id, 'location_currency_position', $currency_position);
            } else {
                delete_term_meta($term_id, 'location_currency_position');
            }
        }

        if (isset($_POST['location_currency_rate_mode'])) {
            $rate_mode = $this->normalize_location_rate_mode(wp_unslash($_POST['location_currency_rate_mode']));
            update_term_meta($term_id, 'location_currency_rate_mode', $rate_mode);
        }

        if (isset($_POST['location_currency_rate'])) {
            $raw_rate = str_replace(',', '.', sanitize_text_field(wp_unslash($_POST['location_currency_rate'])));
            $rate_value = is_numeric($raw_rate) ? (float) $raw_rate : 0.0;

            if ($rate_value > 0) {
                update_term_meta($term_id, 'location_currency_rate', $this->format_location_currency_rate_input($rate_value));
            } else {
                delete_term_meta($term_id, 'location_currency_rate');
            }
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

        // Add actions column (drag + visibility + sync)
        $new_columns['drag_handle'] = __('Actions', 'multi-location-product-and-inventory-management-pro');

        // Add other columns
        foreach ($columns as $key => $value) {
            if ($key === 'cb') {
                continue; // Already added
            }
            if ($key === 'name') {
                $new_columns[$key] = $value;
            } elseif ($key === 'slug') {
                $new_columns['display_order'] = __('Order', 'multi-location-product-and-inventory-management-pro');
                $new_columns['city'] = __('City', 'multi-location-product-and-inventory-management-pro');
                $new_columns['rate'] = __('Rate', 'multi-location-product-and-inventory-management-pro');
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
                $is_active = get_term_meta($term_id, 'is_active', true);
                $is_active = ($is_active === '' || $is_active === '1' || $is_active === true) ? '1' : '0';
                $toggle_class = $is_active === '1' ? 'is-active' : 'is-inactive';
                $toggle_icon = $is_active === '1' ? 'dashicons-visibility' : 'dashicons-hidden';
                $toggle_label = $is_active === '1'
                    ? __('Mark as inactive', 'multi-location-product-and-inventory-management-pro')
                    : __('Mark as active', 'multi-location-product-and-inventory-management-pro');
                $open_state = $this->is_location_open_now($term_id);
                $open_key = !empty($open_state['open']) ? 'open' : 'closed';

                echo '<div class="mulopimfwc-actions-cell" data-open-state="' . esc_attr($open_key) . '">';
                echo '<span class="mulopimfwc-drag-handle dashicons dashicons-menu-alt" data-term-id="' . esc_attr($term_id) . '" title="' . esc_attr__('Drag to reorder', 'multi-location-product-and-inventory-management-pro') . '"></span>';
                echo '<button type="button" class="button mulopimfwc-action-btn mulopimfwc-visibility-toggle ' . esc_attr($toggle_class) . '" data-term-id="' . esc_attr($term_id) . '" data-status="' . esc_attr($is_active) . '" title="' . esc_attr($toggle_label) . '" aria-label="' . esc_attr($toggle_label) . '" aria-pressed="' . ($is_active === '1' ? 'true' : 'false') . '"><span class="dashicons ' . esc_attr($toggle_icon) . '" aria-hidden="true"></span></button>';
                echo '<button type="button" class="button mulopimfwc-action-btn mulopimfwc-rate-row-sync" data-term-id="' . esc_attr($term_id) . '" title="' . esc_attr__('Sync latest rate', 'multi-location-product-and-inventory-management-pro') . '" aria-label="' . esc_attr__('Sync latest rate', 'multi-location-product-and-inventory-management-pro') . '"><span class="dashicons dashicons-update"></span></button>';
                echo '</div>';
                break;
            case 'display_order':
                $display_order = get_term_meta($term_id, 'display_order', true);
                echo $display_order ? esc_html($display_order) : '&mdash;';
                break;

            case 'city':
                $city = get_term_meta($term_id, 'city', true);
                echo $city ? esc_html($city) : '&mdash;';
                break;

            case 'rate':
                $row_data = $this->get_location_rate_table_payload((int) $term_id);
                $mode_options = $this->get_location_rate_mode_options();
                $currency_options = $this->get_currency_options();
                $default_currency = $this->get_default_currency_code();
                $selected_currency = isset($row_data['currency_selected']) ? (string) $row_data['currency_selected'] : '';
                echo '<div class="mulopimfwc-rate-cell" data-term-id="' . esc_attr((string) $row_data['term_id']) . '" data-currency="' . esc_attr((string) $row_data['currency']) . '" data-position="' . esc_attr((string) $row_data['position']) . '">';
                echo '<div class="mulopimfwc-rate-controls">';
                echo '<button type="button" class="button mulopimfwc-rate-currency-trigger" aria-label="' . esc_attr__('Change currency', 'multi-location-product-and-inventory-management-pro') . '" title="' . esc_attr__('Change currency', 'multi-location-product-and-inventory-management-pro') . '" aria-haspopup="listbox" aria-expanded="false">';
                echo '<span class="mulopimfwc-rate-symbol mulopimfwc-rate-symbol-prefix">' . esc_html((string) $row_data['symbol_prefix']) . '</span>';
                echo '<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>';
                echo '</button>';
                echo '<input type="number" class="mulopimfwc-rate-input" min="0.000001" step="0.000001" value="' . esc_attr((string) $row_data['rate']) . '" />';
                echo '<select class="mulopimfwc-rate-mode">';
                foreach ($mode_options as $mode_key => $mode_label) {
                    echo '<option value="' . esc_attr($mode_key) . '" ' . selected((string) $row_data['mode'], (string) $mode_key, false) . '>' . esc_html($mode_label) . '</option>';
                }
                echo '</select>';
                echo '</div>';
                echo '<div class="mulopimfwc-rate-currency-popover" aria-hidden="true">';
                echo '<select class="mulopimfwc-rate-currency" aria-label="' . esc_attr__('Currency', 'multi-location-product-and-inventory-management-pro') . '" data-placeholder="' . esc_attr__('Search currency...', 'multi-location-product-and-inventory-management-pro') . '">';
                $default_currency_symbol = $this->get_unfiltered_currency_symbol($default_currency);
                $default_currency_label = sprintf(/* translators: %s: default currency code */__('Default (%s)', 'multi-location-product-and-inventory-management-pro'), $default_currency);
                if ($default_currency_symbol !== '') {
                    $default_currency_label .= ' (' . $default_currency_symbol . ')';
                }
                echo '<option value="" ' . selected($selected_currency, '', false) . '>' . esc_html($default_currency_label) . '</option>';
                foreach ($currency_options as $currency_code => $currency_name) {
                    $currency_code = strtoupper((string) $currency_code);
                    $currency_symbol = $this->get_unfiltered_currency_symbol($currency_code);
                    $currency_label = $currency_code . ' - ' . $currency_name;
                    if ($currency_symbol !== '') {
                        $currency_label .= ' (' . $currency_symbol . ')';
                    }
                    echo '<option value="' . esc_attr((string) $currency_code) . '" ' . selected($selected_currency, (string) $currency_code, false) . '>' . esc_html($currency_label) . '</option>';
                }
                echo '</select>';
                echo '</div>';
                echo '<div class="mulopimfwc-rate-inline-status" aria-live="polite"></div>';
                echo '</div>';
                break;

            case 'country':
                $country = get_term_meta($term_id, 'country', true);
                echo $country ? esc_html($country) : '&mdash;';
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
            $actions['inline hide-if-no-js'] = '<a href="#" class="editinline" aria-label="' . esc_attr__('Quick edit', 'multi-location-product-and-inventory-management-pro') . '">' . __('Quick&nbsp;Edit', 'multi-location-product-and-inventory-management-pro') . '</a>';
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
            usort($terms, function ($a, $b) {
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
    public function add_location_table_scripts($hook = '')
    {
        $screen = get_current_screen();
        if (empty($screen) || $screen->taxonomy !== 'mulopimfwc_store_location') {
            return;
        }

        // Enqueue jQuery UI Sortable
        wp_enqueue_script('jquery-ui-sortable');

        // Add inline CSS
    ?>
<?php

        // Add inline JavaScript
        $ajax_nonce = wp_create_nonce('mulopimfwc_location_ajax');
        ?>
        <?php
        ob_start();
        ?>
(function($) {

                'use strict';
$(document).ready(function() {
                    var $tbody = $("#the-list");
                    var openLabel = "<?php echo esc_js(__('Open', 'multi-location-product-and-inventory-management-pro')); ?>";
                    var closedLabel = "<?php echo esc_js(__('Closed', 'multi-location-product-and-inventory-management-pro')); ?>";
                    var markActiveLabel = "<?php echo esc_js(__('Mark as active', 'multi-location-product-and-inventory-management-pro')); ?>";
                    var markInactiveLabel = "<?php echo esc_js(__('Mark as inactive', 'multi-location-product-and-inventory-management-pro')); ?>";

                    function normalizeStatus(value) {
                        var normalized = String(value === undefined || value === null ? "" : value);
                        return normalized === "1" ? "1" : "0";
                    }

                    function updateNameOpenBadge($row) {
                        var $actionsCell = $row.find(".mulopimfwc-actions-cell").first();
                        var openState = String($actionsCell.attr("data-open-state") || "").toLowerCase();
                        if (openState !== "open" && openState !== "closed") {
                            return;
                        }

                        var $title = $row.find("td.column-name .row-title").first();
                        if (!$title.length) {
                            return;
                        }

                        $row.find(".mulopimfwc-name-open-badge").remove();
                        $("<span/>", {
                            "class": "mulopimfwc-name-open-badge " + (openState === "open" ? "is-open" : "is-closed"),
                            text: openState === "open" ? openLabel : closedLabel
                        }).insertAfter($title);
                    }

                    function applyVisibilityState($toggle, statusValue) {
                        var normalized = normalizeStatus(statusValue);
                        var isActive = normalized === "1";
                        var $icon = $toggle.find(".dashicons").first();
                        var $row = $toggle.closest("tr");

                        $toggle.attr("data-status", normalized).data("status", normalized);
                        $toggle.attr("aria-pressed", isActive ? "true" : "false");
                        $toggle.attr("title", isActive ? markInactiveLabel : markActiveLabel);
                        $toggle.attr("aria-label", isActive ? markInactiveLabel : markActiveLabel);
                        $toggle.toggleClass("is-active", isActive).toggleClass("is-inactive", !isActive);

                        if ($icon.length) {
                            $icon.removeClass("dashicons-visibility dashicons-hidden");
                            $icon.addClass(isActive ? "dashicons-visibility" : "dashicons-hidden");
                        }

                        if ($row.length) {
                            $row.toggleClass("mulopimfwc-location-inactive", !isActive);
                        }
                    }

                    function initializeLocationRow($row) {
                        var rowId = $row.attr("id") || "";
                        if (rowId.indexOf("tag-") !== 0) {
                            return;
                        }

                        var termId = rowId.replace("tag-", "");
                        $row.attr("data-term-id", termId);
                        $row.find(".mulopimfwc-drag-handle").attr("data-term-id", termId);

                        var $nameCell = $row.find("td.column-name");
                        var $rowActions = $nameCell.find(".row-actions");
                        if ($nameCell.length && $rowActions.length && !$nameCell.find(".mulopimfwc-location-id").length) {
                            $rowActions.before(" <span class=\"mulopimfwc-location-id\">ID: " + termId + "</span>");
                        }

                        updateNameOpenBadge($row);

                        var $toggle = $row.find(".mulopimfwc-visibility-toggle").first();
                        if ($toggle.length) {
                            applyVisibilityState($toggle, $toggle.attr("data-status"));
                        }
                    }

                    $tbody.find("tr").each(function() {
                        initializeLocationRow($(this));
                    });

                    // Initialize sortable
                    if ($tbody.length) {
                        $tbody.sortable({
                            handle: ".mulopimfwc-drag-handle",
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
                                            nonce: "<?php echo esc_js($ajax_nonce); ?>"
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
                    $(document).on("click", ".mulopimfwc-visibility-toggle", function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        var $toggle = $(this);
                        var termId = $toggle.data("term-id");
                        var currentStatus = normalizeStatus($toggle.data("status") || $toggle.attr("data-status"));
                        var newStatus = currentStatus === "1" ? "0" : "1";

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
                                nonce: "<?php echo esc_js($ajax_nonce); ?>"
                            },
                            beforeSend: function() {
                                $toggle.addClass("is-loading");
                            },
                            success: function(response) {
                                if (response.success) {
                                    applyVisibilityState($toggle, newStatus);
                                } else {
                                    alert(response.data.message || "Error updating status");
                                }
                                $toggle.removeClass("is-loading");
                            },
                            error: function(xhr, status, error) {
                                console.error("AJAX error:", error);
                                alert("Error updating status. Please try again.");
                                $toggle.removeClass("is-loading");
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
                        var $statusToggle = $row.find(".mulopimfwc-visibility-toggle");
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
                            if (text !== "" && !Number.isNaN(parseInt(text, 10))) {
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
                        var $statusToggle = $row.find(".mulopimfwc-visibility-toggle");
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
                            if (text !== "" && !Number.isNaN(parseInt(text, 10))) {
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
        <?php
        $mulopimfwc_admin_inline_script = ob_get_clean();
        wp_add_inline_script('mulopimfwc-location-taxonomy-inline', $mulopimfwc_admin_inline_script, 'after');
        ?>
<?php

        $rate_js_config = array(
            'nonce' => $ajax_nonce,
            'messages' => array(
                'saving' => __('Saving rate...', 'multi-location-product-and-inventory-management-pro'),
                'saved' => __('Saved', 'multi-location-product-and-inventory-management-pro'),
                'syncing' => __('Syncing rate...', 'multi-location-product-and-inventory-management-pro'),
                'synced' => __('Rate synced', 'multi-location-product-and-inventory-management-pro'),
                'saveFailed' => __('Unable to save rate.', 'multi-location-product-and-inventory-management-pro'),
                'syncFailed' => __('Unable to sync rate.', 'multi-location-product-and-inventory-management-pro'),
                'autoOnly' => __('Sync works only in Auto mode.', 'multi-location-product-and-inventory-management-pro'),
                'syncAll' => __('Sync All', 'multi-location-product-and-inventory-management-pro'),
                'noAutoRows' => __('No Auto rows found to sync.', 'multi-location-product-and-inventory-management-pro'),
                'syncAllDone' => __('All Auto rates synced successfully.', 'multi-location-product-and-inventory-management-pro'),
                'syncAllPartial' => __('Some rates could not be synced. Please check row messages.', 'multi-location-product-and-inventory-management-pro'),
            ),
        );
        $rate_js_config_json = wp_json_encode($rate_js_config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        if (!is_string($rate_js_config_json)) {
            $rate_js_config_json = '{}';
        }

        ?>
        <?php
        ob_start();
        ?>
(function($) {

                'use strict';
var config = <?php
                                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON_HEX_* encoded for inline JavaScript.
                                echo $rate_js_config_json;
                                ?>;
                if (!config || typeof config !== 'object') {
                    return;
                }

                function showTopNotice(type, message) {
                    if (!message) {
                        return;
                    }
                    var cssClass = type === 'error' ? 'notice-error' : (type === 'warning' ? 'notice-warning' : 'notice-success');
                    var $notice = $('<div class="notice ' + cssClass + ' is-dismissible"><p></p></div>');
                    $notice.find('p').text(message);
                    $('.wrap h1').first().after($notice);
                    setTimeout(function() {
                        $notice.fadeOut(300, function() {
                            $(this).remove();
                        });
                    }, 3500);
                }

                function setInlineStatus($cell, message, isError) {
                    var $status = $cell.find('.mulopimfwc-rate-inline-status').first();
                    if (!$status.length) {
                        return;
                    }
                    if (!message) {
                        $status.text('').removeClass('is-error');
                        return;
                    }
                    $status.text(message);
                    $status.toggleClass('is-error', !!isError);
                }

                function applyRowPayload($cell, rowData) {
                    if (!rowData || typeof rowData !== 'object') {
                        return;
                    }

                    if (rowData.rate !== undefined) {
                        $cell.find('.mulopimfwc-rate-input').val(rowData.rate);
                    }
                    if (rowData.mode) {
                        $cell.find('.mulopimfwc-rate-mode').val(rowData.mode);
                    }
                    if (Object.prototype.hasOwnProperty.call(rowData, 'currency_selected')) {
                        var $currencySelect = $cell.find('.mulopimfwc-rate-currency');
                        $currencySelect.val(rowData.currency_selected || '');
                        if ($currencySelect.data('select2')) {
                            $currencySelect.trigger('change.select2');
                        }
                    }
                    if (rowData.currency) {
                        $cell.attr('data-currency', rowData.currency);
                    }

                    var prefix = rowData.symbol_prefix || rowData.symbol || rowData.currency || '';
                    var $prefix = $cell.find('.mulopimfwc-rate-symbol-prefix');
                    var $trigger = $cell.find('.mulopimfwc-rate-currency-trigger').first();
                    if ($prefix.length) {
                        $prefix.text(prefix);
                    } else if ($trigger.length) {
                        $('<span class="mulopimfwc-rate-symbol mulopimfwc-rate-symbol-prefix"></span>').text(prefix).prependTo($trigger);
                    } else {
                        $cell.find('.mulopimfwc-rate-controls').prepend(
                            $('<span class="mulopimfwc-rate-symbol mulopimfwc-rate-symbol-prefix"></span>').text(prefix)
                        );
                    }
                }

                function setCurrencyPopoverState($cell, isOpen) {
                    if (!$cell || !$cell.length) {
                        return;
                    }

                    var open = !!isOpen;
                    $cell.toggleClass('is-currency-open', open);
                    $cell.find('.mulopimfwc-rate-currency-popover').attr('aria-hidden', open ? 'false' : 'true');
                    $cell.find('.mulopimfwc-rate-currency-trigger').attr('aria-expanded', open ? 'true' : 'false');
                    if (!open) {
                        var $currency = $cell.find('.mulopimfwc-rate-currency').first();
                        if ($currency.length && $currency.data('select2')) {
                            $currency.select2('close');
                        }
                    }
                }

                function initRateCurrencySelect($cell) {
                    var $currency = $cell.find('.mulopimfwc-rate-currency').first();
                    if (!$currency.length || typeof $.fn.select2 === 'undefined' || $currency.data('select2')) {
                        return $currency;
                    }

                    var $popover = $cell.find('.mulopimfwc-rate-currency-popover').first();
                    var placeholder = $currency.data('placeholder') || '';

                    $currency.select2({
                        width: '100%',
                        dropdownParent: $popover,
                        placeholder: placeholder,
                        allowClear: true,
                        minimumResultsForSearch: 0
                    });

                    return $currency;
                }

                function openRateCurrencySelect($cell) {
                    var $select = initRateCurrencySelect($cell);
                    if (!$select.length) {
                        return;
                    }

                    if (!$select.data('select2') && $select[0] && typeof $select[0].showPicker === 'function') {
                        try {
                            $select[0].showPicker();
                            return;
                        } catch (error) {
                            // Fall through to focus for browsers that block showPicker.
                        }
                    }

                    setTimeout(function() {
                        if (!$cell.hasClass('is-currency-open')) {
                            return;
                        }

                        if ($select.data('select2')) {
                            $select.select2('open');
                            return;
                        }

                        $select.trigger('focus');
                    }, 20);
                }

                function toggleRateCurrencyPopover(trigger, event) {
                    if (event) {
                        event.preventDefault();
                        event.stopPropagation();
                    }

                    var $trigger = $(trigger);
                    var $cell = $trigger.closest('.mulopimfwc-rate-cell');
                    if (!$cell.length || $cell.hasClass('is-saving') || $trigger.prop('disabled')) {
                        return;
                    }

                    var shouldOpen = !$cell.hasClass('is-currency-open');
                    closeCurrencyPopovers(shouldOpen ? $cell : null);
                    setCurrencyPopoverState($cell, shouldOpen);

                    if (shouldOpen) {
                        openRateCurrencySelect($cell);
                    }
                }

                function closeCurrencyPopovers($exceptCell) {
                    $('.mulopimfwc-rate-cell.is-currency-open').each(function() {
                        var $cell = $(this);
                        if ($exceptCell && $exceptCell.length && $cell.is($exceptCell)) {
                            return;
                        }
                        setCurrencyPopoverState($cell, false);
                    });
                }

                function getRowSyncButton($cell) {
                    var termId = parseInt($cell.data('term-id'), 10) || 0;
                    if (termId <= 0) {
                        return $();
                    }

                    var $row = $('#tag-' + termId);
                    if (!$row.length) {
                        $row = $cell.closest('tr');
                    }

                    return $row.find('.mulopimfwc-rate-row-sync').first();
                }

                function toggleSyncButtonState($cell) {
                    var $mode = $cell.find('.mulopimfwc-rate-mode').first();
                    var $sync = getRowSyncButton($cell);
                    if (!$sync.length) {
                        return;
                    }
                    var mode = ($mode.val() || 'auto').toString().toLowerCase();
                    var syncing = !!$sync.data('syncing');
                    var disabled = mode !== 'auto' || syncing;

                    $sync.prop('disabled', disabled);
                    $sync.toggleClass('is-disabled', mode !== 'auto');
                }

                function saveRateCell($cell, options) {
                    options = options || {};

                    var termId = parseInt($cell.data('term-id'), 10) || 0;
                    if (termId <= 0) {
                        return $.Deferred().reject().promise();
                    }

                    var $input = $cell.find('.mulopimfwc-rate-input').first();
                    var $currency = $cell.find('.mulopimfwc-rate-currency').first();
                    var $currencyTrigger = $cell.find('.mulopimfwc-rate-currency-trigger').first();
                    var $mode = $cell.find('.mulopimfwc-rate-mode').first();
                    var $sync = getRowSyncButton($cell);

                    if (!options.silent) {
                        setInlineStatus($cell, config.messages.saving || '', false);
                    }

                    $cell.addClass('is-saving');
                    setCurrencyPopoverState($cell, false);
                    $input.prop('disabled', true);
                    $currency.prop('disabled', true);
                    $currencyTrigger.prop('disabled', true).addClass('is-disabled');
                    $mode.prop('disabled', true);
                    $sync.prop('disabled', true);

                    return $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'mulopimfwc_update_location_rate',
                            term_id: termId,
                            rate: $input.val(),
                            currency: $currency.val(),
                            mode: $mode.val(),
                            nonce: config.nonce
                        }
                    }).done(function(response) {
                        if (response && response.success) {
                            if (response.data && response.data.row) {
                                applyRowPayload($cell, response.data.row);
                            }
                            if (!options.silent) {
                                setInlineStatus($cell, (response.data && response.data.message) ? response.data.message : config.messages.saved, false);
                            }
                            return;
                        }

                        var message = config.messages.saveFailed;
                        if (response && response.data && response.data.message) {
                            message = response.data.message;
                        }
                        if (response && response.data && response.data.row) {
                            applyRowPayload($cell, response.data.row);
                        }
                        setInlineStatus($cell, message, true);
                    }).fail(function(xhr) {
                        var message = config.messages.saveFailed;
                        if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                            message = xhr.responseJSON.data.message;
                        }
                        if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.row) {
                            applyRowPayload($cell, xhr.responseJSON.data.row);
                        }
                        setInlineStatus($cell, message, true);
                    }).always(function() {
                        $cell.removeClass('is-saving');
                        $input.prop('disabled', false);
                        $currency.prop('disabled', false);
                        $currencyTrigger.prop('disabled', false).removeClass('is-disabled');
                        $mode.prop('disabled', false);
                        toggleSyncButtonState($cell);
                    });
                }

                function syncRateCell($cell, options) {
                    options = options || {};

                    var termId = parseInt($cell.data('term-id'), 10) || 0;
                    if (termId <= 0) {
                        return $.Deferred().reject().promise();
                    }

                    var $mode = $cell.find('.mulopimfwc-rate-mode').first();
                    var $sync = getRowSyncButton($cell);
                    var modeValue = ($mode.val() || 'auto').toString().toLowerCase();

                    if (modeValue !== 'auto') {
                        if (!options.silent) {
                            setInlineStatus($cell, config.messages.autoOnly, true);
                        }
                        return $.Deferred().resolve({
                            skipped: true
                        }).promise();
                    }

                    $sync.data('syncing', true).addClass('is-loading');
                    toggleSyncButtonState($cell);

                    if (!options.silent) {
                        setInlineStatus($cell, config.messages.syncing || '', false);
                    }

                    return $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'mulopimfwc_sync_location_rate',
                            term_id: termId,
                            nonce: config.nonce
                        }
                    }).done(function(response) {
                        if (response && response.success) {
                            if (response.data && response.data.row) {
                                applyRowPayload($cell, response.data.row);
                            }
                            if (!options.silent) {
                                setInlineStatus($cell, (response.data && response.data.message) ? response.data.message : config.messages.synced, false);
                            }
                            return;
                        }

                        var message = config.messages.syncFailed;
                        if (response && response.data && response.data.message) {
                            message = response.data.message;
                        }
                        if (response && response.data && response.data.row) {
                            applyRowPayload($cell, response.data.row);
                        }
                        setInlineStatus($cell, message, true);
                    }).fail(function(xhr) {
                        var message = config.messages.syncFailed;
                        if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                            message = xhr.responseJSON.data.message;
                        }
                        if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.row) {
                            applyRowPayload($cell, xhr.responseJSON.data.row);
                        }
                        setInlineStatus($cell, message, true);
                    }).always(function() {
                        $sync.data('syncing', false).removeClass('is-loading');
                        toggleSyncButtonState($cell);
                    });
                }

                function ensureSyncAllButton() {
                    var $header = $('.wp-list-table thead th.column-rate').first();
                    if (!$header.length || $header.find('.mulopimfwc-rate-sync-all').length) {
                        return;
                    }

                    var syncAllLabel = config.messages.syncAll || 'Sync All';
                    var $btn = $('<button type="button" class="button-link mulopimfwc-rate-sync-all"></button>');
                    $btn.attr('title', syncAllLabel);
                    $btn.attr('aria-label', syncAllLabel);
                    $btn.append('<span class="dashicons dashicons-update" aria-hidden="true"></span>');
                    $header.append($btn);
                }

                function initRateCells() {
                    ensureSyncAllButton();
                    $('.mulopimfwc-rate-cell').each(function() {
                        var $cell = $(this);
                        toggleSyncButtonState($cell);
                        setCurrencyPopoverState($cell, false);
                    });
                }

                $(document).on('click', '.mulopimfwc-rate-currency-trigger', function(e) {
                    toggleRateCurrencyPopover(this, e);
                });

                if (document.addEventListener) {
                    document.addEventListener('click', function(event) {
                        var target = event.target;
                        if (!target || target.nodeType !== 1) {
                            target = target && target.parentElement ? target.parentElement : null;
                        }
                        if (!target || typeof target.closest !== 'function') {
                            return;
                        }

                        var trigger = target.closest('.mulopimfwc-rate-currency-trigger');
                        if (!trigger || !document.documentElement.contains(trigger)) {
                            return;
                        }

                        toggleRateCurrencyPopover(trigger, event);
                    }, true);
                }

                $(document).on('click', '.mulopimfwc-rate-currency-popover', function(e) {
                    e.stopPropagation();
                });

                $(document).on('click', function(e) {
                    if (!$(e.target).closest('.mulopimfwc-rate-cell').length) {
                        closeCurrencyPopovers();
                    }
                });

                $(document).on('keydown', function(e) {
                    if (e.key === 'Escape') {
                        closeCurrencyPopovers();
                    }
                });

                $(document).on('change', '.mulopimfwc-rate-input', function() {
                    var $cell = $(this).closest('.mulopimfwc-rate-cell');
                    saveRateCell($cell, {
                        silent: false
                    });
                });

                $(document).on('keydown', '.mulopimfwc-rate-input', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        $(this).trigger('change');
                    }
                });

                $(document).on('change', '.mulopimfwc-rate-currency', function() {
                    var $cell = $(this).closest('.mulopimfwc-rate-cell');
                    setCurrencyPopoverState($cell, false);
                    saveRateCell($cell, {
                        silent: true
                    }).done(function(response) {
                        var mode = '';
                        if (response && response.success && response.data && response.data.row) {
                            mode = (response.data.row.mode || '').toString().toLowerCase();
                        } else {
                            mode = ($cell.find('.mulopimfwc-rate-mode').val() || '').toString().toLowerCase();
                        }

                        if (mode === 'auto') {
                            syncRateCell($cell, {
                                silent: false
                            });
                        } else {
                            setInlineStatus($cell, config.messages.saved || '', false);
                        }
                    });
                });

                $(document).on('change', '.mulopimfwc-rate-mode', function() {
                    var $cell = $(this).closest('.mulopimfwc-rate-cell');
                    toggleSyncButtonState($cell);

                    saveRateCell($cell, {
                        silent: true
                    }).done(function(response) {
                        var mode = '';
                        if (response && response.success && response.data && response.data.row) {
                            mode = (response.data.row.mode || '').toString().toLowerCase();
                        } else {
                            mode = ($cell.find('.mulopimfwc-rate-mode').val() || '').toString().toLowerCase();
                        }

                        if (mode === 'auto') {
                            syncRateCell($cell, {
                                silent: false
                            });
                        }
                    });
                });

                $(document).on('click', '.mulopimfwc-rate-row-sync', function(e) {
                    e.preventDefault();
                    var termId = parseInt($(this).data('term-id'), 10) || 0;
                    var $cell = termId > 0 ?
                        $('#tag-' + termId).find('.mulopimfwc-rate-cell').first() :
                        $(this).closest('tr').find('.mulopimfwc-rate-cell').first();
                    if (!$cell.length) {
                        return;
                    }
                    syncRateCell($cell, {
                        silent: false
                    });
                });

                $(document).on('click', '.mulopimfwc-rate-sync-all', function(e) {
                    e.preventDefault();
                    var $button = $(this);
                    if ($button.hasClass('is-loading')) {
                        return;
                    }

                    var $cells = $('.mulopimfwc-rate-cell').filter(function() {
                        var mode = ($(this).find('.mulopimfwc-rate-mode').val() || '').toString().toLowerCase();
                        return mode === 'auto';
                    });

                    if (!$cells.length) {
                        showTopNotice('warning', config.messages.noAutoRows);
                        return;
                    }

                    var total = $cells.length;
                    var successCount = 0;
                    var failCount = 0;

                    $button.addClass('is-loading').prop('disabled', true);

                    var runAt = function(index) {
                        if (index >= total) {
                            $button.removeClass('is-loading').prop('disabled', false);
                            if (failCount === 0) {
                                showTopNotice('success', config.messages.syncAllDone);
                            } else {
                                showTopNotice('warning', config.messages.syncAllPartial);
                            }
                            return;
                        }

                        syncRateCell($($cells[index]), {
                                silent: true
                            })
                            .done(function(response) {
                                if (response && response.success) {
                                    successCount++;
                                } else {
                                    failCount++;
                                }
                            })
                            .fail(function() {
                                failCount++;
                            })
                            .always(function() {
                                runAt(index + 1);
                            });
                    };

                    runAt(0);
                });

                $(document).ready(function() {
                    initRateCells();
                });
            })(jQuery);
        <?php
        $mulopimfwc_admin_inline_script = ob_get_clean();
        wp_add_inline_script('mulopimfwc-location-taxonomy-inline', $mulopimfwc_admin_inline_script, 'after');
        ?>
    <?php
    }

    /**
     * AJAX handler for updating location order
     */
    public function ajax_update_location_order()
    {
        check_ajax_referer('mulopimfwc_location_ajax', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied', 'multi-location-product-and-inventory-management-pro')));
        }

        if (!isset($_POST['term_ids']) || !is_array($_POST['term_ids'])) {
            wp_send_json_error(array('message' => __('Invalid data', 'multi-location-product-and-inventory-management-pro')));
        }

        $term_ids = array_map('absint', $_POST['term_ids']);
        $order = 0;

        foreach ($term_ids as $term_id) {
            $order++;
            update_term_meta($term_id, 'display_order', $order);
        }

        wp_send_json_success(array('message' => __('Order updated successfully', 'multi-location-product-and-inventory-management-pro')));
    }

    /**
     * AJAX handler for toggling location status
     */
    public function ajax_toggle_location_status()
    {
        check_ajax_referer('mulopimfwc_location_ajax', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied', 'multi-location-product-and-inventory-management-pro')));
        }

        if (!isset($_POST['term_id']) || !isset($_POST['status'])) {
            wp_send_json_error(array('message' => __('Invalid data', 'multi-location-product-and-inventory-management-pro')));
        }

        $term_id = absint($_POST['term_id']);
        $status = sanitize_text_field($_POST['status']);
        $status = ($status === '1' || $status === 'yes' || $status === 'on') ? '1' : '0';

        update_term_meta($term_id, 'is_active', $status);

        $status_text = $status === '1' ? __('Active', 'multi-location-product-and-inventory-management-pro') : __('Inactive', 'multi-location-product-and-inventory-management-pro');
        /* translators: %s: location status (Active or Inactive) */
        wp_send_json_success(array('message' => sprintf(__('Location status updated to %s', 'multi-location-product-and-inventory-management-pro'), $status_text)));
    }

    public function ajax_update_location_rate()
    {
        check_ajax_referer('mulopimfwc_location_ajax', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied', 'multi-location-product-and-inventory-management-pro')), 403);
        }

        $term_id = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
        if ($term_id <= 0) {
            wp_send_json_error(array('message' => __('Invalid location.', 'multi-location-product-and-inventory-management-pro')), 400);
        }

        $term = get_term($term_id, 'mulopimfwc_store_location');
        if (!$term || is_wp_error($term)) {
            wp_send_json_error(array('message' => __('Location not found.', 'multi-location-product-and-inventory-management-pro')), 404);
        }

        $mode = isset($_POST['mode'])
            ? $this->normalize_location_rate_mode(wp_unslash($_POST['mode']))
            : $this->normalize_location_rate_mode(get_term_meta($term_id, 'location_currency_rate_mode', true));
        update_term_meta($term_id, 'location_currency_rate_mode', $mode);

        if (isset($_POST['currency'])) {
            $requested_currency = strtoupper(sanitize_text_field(wp_unslash($_POST['currency'])));
            $available_currencies = $this->get_currency_options();

            if ($requested_currency !== '' && isset($available_currencies[$requested_currency])) {
                update_term_meta($term_id, 'location_currency', $requested_currency);
            } else {
                delete_term_meta($term_id, 'location_currency');
            }
        }

        if (isset($_POST['rate'])) {
            $parsed_rate = $this->parse_location_currency_rate(wp_unslash($_POST['rate']));
            if ($parsed_rate === null) {
                wp_send_json_error(array(
                    'message' => __('Please enter a valid rate greater than 0.', 'multi-location-product-and-inventory-management-pro'),
                    'row' => $this->get_location_rate_table_payload($term_id),
                ), 400);
            }
            update_term_meta($term_id, 'location_currency_rate', $parsed_rate);
        }

        wp_send_json_success(array(
            'message' => __('Rate updated successfully.', 'multi-location-product-and-inventory-management-pro'),
            'row' => $this->get_location_rate_table_payload($term_id),
        ));
    }

    public function ajax_sync_location_rate()
    {
        check_ajax_referer('mulopimfwc_location_ajax', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied', 'multi-location-product-and-inventory-management-pro')), 403);
        }

        $term_id = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
        if ($term_id <= 0) {
            wp_send_json_error(array('message' => __('Invalid location.', 'multi-location-product-and-inventory-management-pro')), 400);
        }

        $term = get_term($term_id, 'mulopimfwc_store_location');
        if (!$term || is_wp_error($term)) {
            wp_send_json_error(array('message' => __('Location not found.', 'multi-location-product-and-inventory-management-pro')), 404);
        }

        $mode = $this->normalize_location_rate_mode(get_term_meta($term_id, 'location_currency_rate_mode', true));
        if ($mode !== 'auto') {
            wp_send_json_error(array(
                'message' => __('Sync is available only when mode is Auto.', 'multi-location-product-and-inventory-management-pro'),
                'row' => $this->get_location_rate_table_payload($term_id),
            ), 400);
        }

        $default_currency = $this->get_default_currency_code();
        $currency_settings = $this->get_effective_location_currency_settings($term_id);
        $target_currency = strtoupper((string) ($currency_settings['currency'] ?? $default_currency));

        $rate = $this->fetch_currency_rate_from_remote($default_currency, $target_currency);
        if (!is_numeric($rate) || (float) $rate <= 0) {
            wp_send_json_error(array(
                'message' => __('Unable to fetch the latest exchange rate right now. Please try again.', 'multi-location-product-and-inventory-management-pro'),
                'row' => $this->get_location_rate_table_payload($term_id),
            ), 500);
        }

        $formatted_rate = $this->format_location_currency_rate_input($rate);
        if ($formatted_rate === '') {
            $formatted_rate = '1';
        }
        update_term_meta($term_id, 'location_currency_rate', $formatted_rate);

        wp_send_json_success(array(
            'message' => sprintf(
                /* translators: 1: source currency code, 2: target currency code */
                __('Rate synced for %1$s -> %2$s.', 'multi-location-product-and-inventory-management-pro'),
                $default_currency,
                $target_currency
            ),
            'row' => $this->get_location_rate_table_payload($term_id),
        ));
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
                    <span class="title"><?php esc_html_e('Status', 'multi-location-product-and-inventory-management-pro'); ?></span>
                    <select name="is_active" id="inline-edit-is-active">
                        <option value="1"><?php esc_html_e('Active', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        <option value="0"><?php esc_html_e('Inactive', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    </select>
                </label>
            </div>
            <div class="inline-edit-col">
                <label>
                    <span class="title"><?php esc_html_e('Order', 'multi-location-product-and-inventory-management-pro'); ?></span>
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
            wp_send_json_error(array('message' => __('Permission denied', 'multi-location-product-and-inventory-management-pro')));
        }

        if (!isset($_POST['term_id'])) {
            wp_send_json_error(array('message' => __('Invalid data', 'multi-location-product-and-inventory-management-pro')));
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

        wp_send_json_success(array('message' => __('Location updated successfully', 'multi-location-product-and-inventory-management-pro')));
    }

    /**
     * Save quick edit fields when WordPress quick edit form is submitted
     */
    public function save_quick_edit_on_submit($hook = '')
    {
        $screen = get_current_screen();
        if (empty($screen) || $screen->taxonomy !== 'mulopimfwc_store_location') {
            return;
        }

        $ajax_nonce = wp_create_nonce('mulopimfwc_location_ajax');
    ?>
        <?php
        ob_start();
        ?>
(function($) {

                'use strict';
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
        <?php
        $mulopimfwc_admin_inline_script = ob_get_clean();
        wp_add_inline_script('mulopimfwc-location-taxonomy-inline', $mulopimfwc_admin_inline_script, 'after');
        ?>
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
            __('Location Manage', 'multi-location-product-and-inventory-management-pro'),
            __('Location Manage', 'multi-location-product-and-inventory-management-pro'),
            'manage_options',
            'multi-location-product-and-inventory-management-pro',
            [new MULOPIMFWC_Dashboard(), 'dashboard_page_content'],
            'dashicons-location-alt',
            56
        );

        // Add Dashboard submenu (just label, points to same page, no callback)
        add_submenu_page(
            'multi-location-product-and-inventory-management-pro',
            __('Dashboard', 'multi-location-product-and-inventory-management-pro'),
            __('Dashboard', 'multi-location-product-and-inventory-management-pro'),
            'manage_options',
            'multi-location-product-and-inventory-management-pro'
            // No callback here, so it won't render twice
        );

        // Add Locations submenu
        add_submenu_page(
            'multi-location-product-and-inventory-management-pro',
            __('Locations', 'multi-location-product-and-inventory-management-pro'),
            __('Locations', 'multi-location-product-and-inventory-management-pro'),
            'manage_options',
            'edit-tags.php?taxonomy=mulopimfwc_store_location&post_type=product&orderby=display_order&order=asc',
            null,
            56
        );

        // Ensure the menu is expanded and active when this page is active
        add_filter('parent_file', function ($parent_file) {
            global $pagenow, $taxonomy;

            if ($pagenow === 'edit-tags.php' && $taxonomy === 'mulopimfwc_store_location') {
                $parent_file = 'multi-location-product-and-inventory-management-pro';
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
        $stock_central_hook = add_submenu_page(
            'multi-location-product-and-inventory-management-pro',
            __('Stock Central', 'multi-location-product-and-inventory-management-pro'),
            __('Stock Central', 'multi-location-product-and-inventory-management-pro'),
            'manage_options',
            'location-stock-management',
            [$this->stock_central, 'location_stock_page_content']
        );
        if ($stock_central_hook) {
            add_action('load-' . $stock_central_hook, [$this, 'register_stock_central_screen_options']);
        }

        global $MULOPIMFWC_Location_Managers;

        add_submenu_page(
            'multi-location-product-and-inventory-management-pro',
            __('Location Managers', 'multi-location-product-and-inventory-management-pro'),
            __('Location Managers', 'multi-location-product-and-inventory-management-pro'),
            'manage_options',
            'location-managers',
            array($MULOPIMFWC_Location_Managers, 'admin_page')
        );

        add_submenu_page(
            'multi-location-product-and-inventory-management-pro',
            __('Addons', 'multi-location-product-and-inventory-management-pro'),
            __('Addons', 'multi-location-product-and-inventory-management-pro'),
            'install_plugins',
            'mulopimfwc-addons',
            class_exists('MULOPIMFWC_Addons_Page') ? [MULOPIMFWC_Addons_Page::instance(), 'render_page'] : '__return_null'
        );

        // Add Settings submenu
        add_submenu_page(
            'multi-location-product-and-inventory-management-pro',
            __('Settings', 'multi-location-product-and-inventory-management-pro'),
            __('Settings', 'multi-location-product-and-inventory-management-pro'),
            'manage_options',
            'multi-location-product-and-inventory-management-settings',
            class_exists('mulopimfwc_settings') ? [new mulopimfwc_settings(), 'settings_page_content'] : '__return_null'
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
                $new_columns['mulopimfwc_store_location'] = __('Store Location', 'multi-location-product-and-inventory-management-pro');
                $inserted = true;
            }
        }

        if (!$inserted) {
            $new_columns['mulopimfwc_store_location'] = __('Store Location', 'multi-location-product-and-inventory-management-pro');
        }

        return $new_columns;
    }

    public function register_stock_central_screen_options()
    {
        if (!$this->stock_central) {
            $this->stock_central = new mulopimfwc_Stock_Central();
        }

        $screen = get_current_screen();
        if ($screen && !empty($screen->id)) {
            $screen_id = $screen->id;
            $classic_locked_columns = [
                'classic_manage_stock',
                'classic_default',
                'classic_location_wise',
                'classic_purchase',
            ];

            $resolve_view_mode = static function () {
                $requested_mode = isset($_REQUEST['stock_central_view'])
                    ? sanitize_text_field(wp_unslash($_REQUEST['stock_central_view']))
                    : '';
                if (!in_array($requested_mode, ['modern', 'classic'], true)) {
                    $requested_mode = '';
                }

                if ($requested_mode === '') {
                    $user_id = get_current_user_id();
                    if ($user_id) {
                        $saved_mode = get_user_meta($user_id, 'mulopimfwc_stock_central_view_mode', true);
                        if (in_array($saved_mode, ['modern', 'classic'], true)) {
                            $requested_mode = $saved_mode;
                        }
                    }
                }

                return $requested_mode !== '' ? $requested_mode : 'modern';
            };

            add_filter('default_hidden_columns', function ($hidden, $current_screen) use ($screen_id, $classic_locked_columns, $resolve_view_mode) {
                if ($current_screen && $current_screen->id === $screen_id) {
                    $default_hidden = [
                        'sku',
                        'type',
                        'categories',
                        'tags',
                        'brands',
                        'short_description',
                        'description',
                        'featured',
                        'dimensions',
                        'date',
                    ];
                    $hidden = array_values(array_unique(array_merge($hidden, $default_hidden)));

                    if ($resolve_view_mode() === 'classic') {
                        $hidden = array_values(array_diff($hidden, $classic_locked_columns));
                    }
                }

                return $hidden;
            }, 10, 2);

            add_filter('manage_' . $screen_id . '_columns', function ($columns) use ($resolve_view_mode, $classic_locked_columns) {
                if ($resolve_view_mode() !== 'classic' || !is_array($columns)) {
                    return $columns;
                }

                foreach ($classic_locked_columns as $column_key) {
                    unset($columns[$column_key]);
                }

                return $columns;
            });
        }

        $this->stock_central->register_screen_options();
    }

    public function save_stock_central_screen_options($status, $option, $value)
    {
        if ($option === 'mulopimfwc_stock_central_per_page') {
            return max(1, (int) $value);
        }

        return $status;
    }

    private function get_location_label($location_slug)
    {
        $location_slug = (string) $location_slug;

        if ($location_slug === '') {
            return '';
        }

        if ($location_slug === 'all-products') {
            return __('All Products', 'multi-location-product-and-inventory-management-pro');
        }

        if ($location_slug === 'unassigned') {
            return __('Unassigned location', 'multi-location-product-and-inventory-management-pro');
        }

        $term = get_term_by('slug', $location_slug, 'mulopimfwc_store_location');
        if ($term && !is_wp_error($term)) {
            return $term->name;
        }

        return str_replace(array('_', '-'), ' ', ucwords($location_slug));
    }

    private function get_order_edit_url_by_id($order_id): string
    {
        $order_id = (int) $order_id;
        if ($order_id <= 0) {
            return '';
        }

        if (class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController')) {
            return admin_url('admin.php?page=wc-orders&action=edit&id=' . $order_id);
        }

        return admin_url('post.php?post=' . $order_id . '&action=edit');
    }

    private function is_current_user_location_manager(): bool
    {
        if (!class_exists('MULOPIMFWC_Location_Managers') || !is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();
        return in_array('mulopimfwc_location_manager', (array) $user->roles, true);
    }

    private function current_user_can_manage_order_operations(): bool
    {
        if (!$this->is_current_user_location_manager()) {
            return current_user_can('edit_shop_orders') || current_user_can('manage_woocommerce');
        }

        if (!class_exists('MULOPIMFWC_Location_Managers')) {
            return false;
        }

        return MULOPIMFWC_Location_Managers::current_user_can_manage_orders();
    }

    private function is_location_currency_lock_enabled($options = null): bool
    {
        if (!is_array($options)) {
            $options = get_option('mulopimfwc_display_options', []);
        }

        if (function_exists('mulopimfwc_is_location_wise_currency_enabled')) {
            return mulopimfwc_is_location_wise_currency_enabled($options);
        }

        return (
            isset($options['enable_location_price'], $options['location_wise_currency']) &&
            $options['enable_location_price'] === 'on' &&
            $options['location_wise_currency'] === 'on'
        );
    }

    private function is_manual_order_assignment_enabled($options = null): bool
    {
        if (!is_array($options)) {
            $options = get_option('mulopimfwc_display_options', []);
        }

        if (function_exists('mulopimfwc_get_effective_order_assignment_method')) {
            return mulopimfwc_get_effective_order_assignment_method($options) === 'manual';
        }

        return isset($options['order_assignment_method'])
            && $options['order_assignment_method'] === 'manual';
    }

    private function get_current_user_allowed_location_slugs()
    {
        if (!class_exists('MULOPIMFWC_Location_Managers')) {
            return null;
        }

        if (!$this->is_current_user_location_manager()) {
            return null;
        }

        if (MULOPIMFWC_Location_Managers::user_has_capability('all_orders')) {
            return null;
        }

        $assigned_locations = MULOPIMFWC_Location_Managers::get_user_assigned_locations();
        return is_array($assigned_locations) ? array_values(array_filter($assigned_locations)) : [];
    }

    private function render_split_parent_location_cell($order): string
    {
        if (!$order instanceof WC_Order) {
            return esc_html__('Split Parent', 'multi-location-product-and-inventory-management-pro');
        }

        $allowed_slugs = $this->get_current_user_allowed_location_slugs();
        $child_ids = array_filter(array_map('absint', (array) $order->get_meta('_mulopimfwc_split_children')));
        $child_links = [];

        foreach ($child_ids as $child_id) {
            $child_order = wc_get_order($child_id);
            if (!$child_order) {
                continue;
            }

            $location_slug = (string) $child_order->get_meta('_store_location');
            if (is_array($allowed_slugs) && !in_array($location_slug, $allowed_slugs, true)) {
                continue;
            }
            $location_label = $this->get_location_label($location_slug);
            if ($location_label === '') {
                $location_label = __('Unassigned', 'multi-location-product-and-inventory-management-pro');
            }

            $edit_url = $this->get_order_edit_url_by_id($child_id);
            $child_links[] = sprintf(
                '<a class="mulopimfwc-order-split-chip" href="%s">#%d<span class="mulopimfwc-order-split-chip-label">%s</span></a>',
                esc_url($edit_url),
                (int) $child_id,
                esc_html($location_label)
            );
        }

        $output = '<div class="mulopimfwc-order-split-cell">';
        $output .= '<span class="mulopimfwc-order-split-badge mulopimfwc-order-split-badge-parent">' . esc_html__('Split Parent', 'multi-location-product-and-inventory-management-pro') . '</span>';

        if (!empty($child_links)) {
            $output .= '<div class="mulopimfwc-order-split-children">' . implode('', $child_links) . '</div>';
        } else {
            $empty_message = is_array($allowed_slugs)
                ? __('No child orders for your locations.', 'multi-location-product-and-inventory-management-pro')
                : __('No child orders yet.', 'multi-location-product-and-inventory-management-pro');
            $output .= '<div class="mulopimfwc-order-split-muted">' . esc_html($empty_message) . '</div>';
        }

        $output .= '</div>';

        return $output;
    }

    private function render_split_child_location_cell($order, $is_manual_mode, $show_parent = true): string
    {
        if (!$order instanceof WC_Order) {
            return esc_html__('Split Child', 'multi-location-product-and-inventory-management-pro');
        }

        $parent_id = (int) $order->get_meta('_mulopimfwc_split_parent_id');
        $parent_link = '';
        if ($parent_id > 0) {
            $parent_edit_url = $this->get_order_edit_url_by_id($parent_id);
            $parent_link = sprintf(
                '<a href="%s">#%d</a>',
                esc_url($parent_edit_url),
                $parent_id
            );
        }

        $location_slug = (string) $order->get_meta('_store_location');
        $location_line = '';
        if ($location_slug === '' && $is_manual_mode) {
            $location_line = '<div class="mulopimfwc-order-split-location-line">' . $this->render_quick_assignment_dropdown($order) . '</div>';
        } else {
            $location_label = $this->get_location_label($location_slug);
            if ($location_label === '') {
                $location_label = __('Unassigned', 'multi-location-product-and-inventory-management-pro');
            }
            $location_line = sprintf(
                '<div class="mulopimfwc-order-split-location-line"><span class="mulopimfwc-order-split-location-label">%s</span><span class="mulopimfwc-order-split-location-value">%s</span></div>',
                esc_html__('Location:', 'multi-location-product-and-inventory-management-pro'),
                esc_html($location_label)
            );
        }

        $output = '<div class="mulopimfwc-order-split-cell">';
        $output .= '<span class="mulopimfwc-order-split-badge mulopimfwc-order-split-badge-child">' . esc_html__('Split Child', 'multi-location-product-and-inventory-management-pro') . '</span>';

        if ($show_parent && $parent_link !== '') {
            $output .= sprintf(
                '<div class="mulopimfwc-order-split-parent-line">%s %s</div>',
                esc_html__('Parent:', 'multi-location-product-and-inventory-management-pro'),
                $parent_link
            );
        }

        $output .= $location_line;
        $output .= '</div>';

        return $output;
    }

    /**
     * Get allowed HTML for generated order location cell markup.
     *
     * @return array
     */
    private function get_order_location_cell_allowed_html(): array
    {
        return array(
            'a'      => array(
                'class' => true,
                'href'  => true,
            ),
            'br'     => array(),
            'div'    => array(
                'class' => true,
                'style' => true,
            ),
            'select' => array(
                'class'         => true,
                'data-order-id' => true,
                'data-nonce'    => true,
            ),
            'span'   => array(
                'aria-hidden' => true,
                'class'       => true,
                'style'       => true,
            ),
            'strong' => array(),
            'option' => array(
                'disabled' => true,
                'selected' => true,
                'value'    => true,
            ),
        );
    }

    /**
     * Get allowed SVG markup for admin icons.
     *
     * @return array
     */
    private function get_svg_icon_allowed_html(): array
    {
        return array(
            'svg'    => array(
                'aria-hidden' => true,
                'class'       => true,
                'height'      => true,
                'style'       => true,
                'viewbox'     => true,
                'width'       => true,
                'xmlns'       => true,
            ),
            'circle' => array(
                'cx'   => true,
                'cy'   => true,
                'fill' => true,
                'r'    => true,
            ),
            'path'   => array(
                'clip-rule' => true,
                'd'         => true,
                'fill-rule' => true,
            ),
            'rect'   => array(
                'fill'   => true,
                'height' => true,
                'width'  => true,
                'x'      => true,
                'y'      => true,
            ),
        );
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
        $is_manual_mode = $this->is_manual_order_assignment_enabled($options);

        $is_split_parent = $order_obj->get_meta('_mulopimfwc_split_parent') === 'yes';
        $is_split_child = $order_obj->get_meta('_mulopimfwc_split_child') === 'yes';
        $show_parent_link = !$this->is_current_user_location_manager();

        if ($is_split_parent) {
            echo wp_kses(
                $this->render_split_parent_location_cell($order_obj),
                $this->get_order_location_cell_allowed_html()
            );
            return;
        }

        if ($is_split_child) {
            echo wp_kses(
                $this->render_split_child_location_cell($order_obj, $is_manual_mode, $show_parent_link),
                $this->get_order_location_cell_allowed_html()
            );
            return;
        }

        if (empty($location_slug) && $is_manual_mode) {
            // Show quick assignment dropdown for unassigned orders
            echo wp_kses(
                $this->render_quick_assignment_dropdown($order_obj),
                $this->get_order_location_cell_allowed_html()
            );
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
        if (!$order || !current_user_can('edit_shop_order', $order->get_id()) || !$this->current_user_can_manage_order_operations()) {
            return '<span class="mulopimfwc-unassigned-badge"><span class="dashicons dashicons-warning" aria-hidden="true"></span>' . esc_html__('Unassigned', 'multi-location-product-and-inventory-management-pro') . '</span>';
        }

        if (!$order->is_editable()) {
            return '<span class="mulopimfwc-unassigned-badge">' . esc_html__('Unassigned', 'multi-location-product-and-inventory-management-pro') . '</span>';
        }

        $order_id = $order->get_id();
        // Get available locations based on order products stock availability
        $locations = $this->get_available_locations_for_order($order);

        ob_start();
    ?>
        <div class="mulopimfwc-quick-assignment-wrapper">
            <span class="mulopimfwc-unassigned-badge"><span class="dashicons dashicons-warning" aria-hidden="true"></span><?php echo esc_html__('Needs Assignment', 'multi-location-product-and-inventory-management-pro'); ?></span>
            <?php if (empty($locations)): ?>
                <?php
                // Get order edit URL
                $order_edit_url = '';
                if (function_exists('wc_get_order')) {
                    // Try HPOS first
                    if (class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController')) {
                        $order_edit_url = admin_url('admin.php?page=wc-orders&action=edit&id=' . $order_id);
                    } else {
                        // Fallback to post-based orders
                        $order_edit_url = admin_url('post.php?post=' . $order_id . '&action=edit');
                    }
                }
                ?>
                <div class="mulopimfwc-no-locations-message" style="margin-top: 5px; padding: 8px; background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; font-size: 12px;">
                    <strong><?php echo esc_html__('No locations available', 'multi-location-product-and-inventory-management-pro'); ?></strong><br>
                    <?php echo esc_html__('Insufficient stock for all products at any location.', 'multi-location-product-and-inventory-management-pro'); ?>
                    <span style="font-style: italic;">
                        <?php echo esc_html__('Please edit the order and set locations for each product individually', 'multi-location-product-and-inventory-management-pro'); ?>
                    </span>
                </div>
            <?php else: ?>
                <select class="mulopimfwc-quick-assignment-select" data-order-id="<?php echo esc_attr($order_id); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('mulopimfwc_quick_assign_' . $order_id)); ?>">
                    <option value=""><?php echo esc_html__('Select Location...', 'multi-location-product-and-inventory-management-pro'); ?></option>
                    <?php foreach ($locations as $location): ?>
                        <option value="<?php echo esc_attr($location->slug); ?>"><?php echo esc_html($location->name); ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="mulopimfwc-assignment-spinner" style="display: none;">⏳</span>
            <?php endif; ?>
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
     * Get assigned location slugs for an order item (variation first, then parent product).
     * In "all locations" mode, items without explicit assignment are treated as available in all locations.
     *
     * @param int $product_id
     * @param int $variation_id
     * @return array
     */
    private function get_order_item_assigned_location_slugs($product_id, $variation_id)
    {
        $product_id = absint($product_id);
        $variation_id = absint($variation_id);

        static $cache = [];
        $cache_key = $product_id . ':' . $variation_id;
        if (isset($cache[$cache_key])) {
            return $cache[$cache_key];
        }

        $ids_to_check = array_values(array_filter([$variation_id, $product_id]));
        foreach ($ids_to_check as $id) {
            $terms = wp_get_object_terms($id, 'mulopimfwc_store_location', ['fields' => 'slugs']);
            if (!is_wp_error($terms) && !empty($terms)) {
                $cache[$cache_key] = array_values(array_unique(array_map('rawurldecode', (array) $terms)));
                return $cache[$cache_key];
            }
        }

        global $mulopimfwc_options;
        $options = is_array($mulopimfwc_options ?? null)
            ? $mulopimfwc_options
            : get_option('mulopimfwc_display_options', []);
        $enable_all_locations = isset($options['enable_all_locations']) && $options['enable_all_locations'] === 'on';

        if ($enable_all_locations) {
            $cache[$cache_key] = array_values(array_filter(array_map(
                'rawurldecode',
                wp_list_pluck($this->get_all_store_locations(), 'slug')
            )));
            return $cache[$cache_key];
        }

        $cache[$cache_key] = [];
        return $cache[$cache_key];
    }

    /**
     * Check whether an order item is assigned to a specific location slug.
     *
     * @param int $product_id
     * @param int $variation_id
     * @param string $location_slug
     * @return bool
     */
    private function is_order_item_assigned_to_location($product_id, $variation_id, $location_slug)
    {
        $location_slug = (string) $location_slug;
        if ($location_slug === '') {
            return false;
        }

        $assigned_slugs = $this->get_order_item_assigned_location_slugs($product_id, $variation_id);
        return in_array($location_slug, $assigned_slugs, true);
    }

    private function build_location_stock_snapshot($order, $location_term)
    {
        $snapshot = [
            'status' => 'unknown',
            'summary' => __('Stock not tracked', 'multi-location-product-and-inventory-management-pro'),
            'items' => [],
        ];

        if (!$order || !$location_term || is_wp_error($location_term)) {
            return $snapshot;
        }

        global $mulopimfwc_options;
        $options = is_array($mulopimfwc_options ?? null)
            ? $mulopimfwc_options
            : get_option('mulopimfwc_display_options', []);
        $enable_location_stock = isset($options['enable_location_stock']) && $options['enable_location_stock'] === 'on';

        $insufficient = 0;
        $backorder = 0;
        $unknown = 0;

        $items = $order->get_items();
        if (empty($items)) {
            $snapshot['summary'] = __('No items in order', 'multi-location-product-and-inventory-management-pro');
            return $snapshot;
        }

        foreach ($items as $item) {
            if (!$item->is_type('line_item')) {
                continue;
            }

            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $quantity = $item->get_quantity();
            $target_id = $variation_id ? $variation_id : $product_id;
            $product_name = $item->get_name();

            $detail = [
                'product' => $product_name,
                'required' => (int) $quantity,
                'available' => null,
                'status' => 'unknown',
            ];

            if ($target_id) {
                $is_assigned = $this->is_order_item_assigned_to_location($product_id, $variation_id, $location_term->slug);

                if (!$is_assigned) {
                    $detail['status'] = 'not-assigned';
                    $insufficient++;
                } elseif ($enable_location_stock) {
                    $location_stock = get_post_meta($target_id, '_location_stock_' . $location_term->term_id, true);
                    $location_backorders = get_post_meta($target_id, '_location_backorders_' . $location_term->term_id, true);

                    if ($location_stock !== '') {
                        $available_stock = (int) $location_stock;
                        $detail['available'] = $available_stock;

                        if ($location_backorders === 'off' && $available_stock < $quantity) {
                            $detail['status'] = 'insufficient';
                            $insufficient++;
                        } elseif ($available_stock < $quantity && $location_backorders !== 'off') {
                            $detail['status'] = 'backorder';
                            $backorder++;
                        } else {
                            $detail['status'] = 'ok';
                        }
                    } else {
                        $detail['status'] = 'unknown';
                        $unknown++;
                    }
                } else {
                    $detail['status'] = 'unknown';
                    $unknown++;
                }
            } else {
                $detail['status'] = 'unknown';
                $unknown++;
            }

            $snapshot['items'][] = $detail;
        }

        if ($insufficient > 0) {
            $snapshot['status'] = 'insufficient';
            $snapshot['summary'] = sprintf(
                /* translators: %d: number of items with insufficient stock */
                __('Insufficient stock for %d item(s)', 'multi-location-product-and-inventory-management-pro'),
                $insufficient
            );
        } elseif ($backorder > 0) {
            $snapshot['status'] = 'backorder';
            $snapshot['summary'] = sprintf(
                /* translators: %d: number of items requiring backorder */
                __('Backorder required for %d item(s)', 'multi-location-product-and-inventory-management-pro'),
                $backorder
            );
        } elseif ($unknown > 0) {
            $snapshot['status'] = 'unknown';
            $snapshot['summary'] = __('Stock not tracked for some items', 'multi-location-product-and-inventory-management-pro');
        } else {
            $snapshot['status'] = 'ok';
            $snapshot['summary'] = __('All items in stock', 'multi-location-product-and-inventory-management-pro');
        }

        return $snapshot;
    }

    /**
     * Get locations that are valid for all provided orders (intersection).
     *
     * @param array $orders Array of WC_Order objects
     * @return array Array of location term objects available for all orders
     */
    private function get_available_locations_for_orders($orders)
    {
        if (empty($orders)) {
            return [];
        }

        $common_slugs = null;

        foreach ($orders as $order) {
            $locations = $this->get_available_locations_for_order($order);
            $slugs = array_values(array_filter(wp_list_pluck($locations, 'slug')));

            if ($common_slugs === null) {
                $common_slugs = $slugs;
            } else {
                $common_slugs = array_values(array_intersect($common_slugs, $slugs));
            }

            if (empty($common_slugs)) {
                return [];
            }
        }

        if ($common_slugs === null || empty($common_slugs)) {
            return [];
        }

        $locations = get_terms(array(
            'taxonomy' => 'mulopimfwc_store_location',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
            'slug' => $common_slugs,
        ));

        return is_wp_error($locations) ? [] : $locations;
    }

    /**
     * Get available locations for an order based on product stock availability.
     * Only returns locations where all products in the order are available in stock.
     *
     * @param WC_Order $order Order object
     * @return array Array of location term objects that have all products in stock
     */
    private function get_available_locations_for_order($order)
    {
        if (!$order) {
            return [];
        }

        $all_locations = $this->get_all_store_locations();
        if (empty($all_locations)) {
            return [];
        }

        global $mulopimfwc_options;
        $enable_location_stock = isset($mulopimfwc_options['enable_location_stock']) && $mulopimfwc_options['enable_location_stock'] === 'on';

        // If location stock is not enabled, return all locations
        if (!$enable_location_stock) {
            return $all_locations;
        }

        $available_locations = [];

        // Get all order items
        $order_items = $order->get_items();
        if (empty($order_items)) {
            return $all_locations; // No items, return all locations
        }

        // Check each location
        foreach ($all_locations as $location) {
            $location_id = $location->term_id;
            $all_products_available = true;

            // Check if all products in order are available at this location
            foreach ($order_items as $item) {
                if (!$item->is_type('line_item')) {
                    continue;
                }

                $product_id = $item->get_product_id();
                $variation_id = $item->get_variation_id();
                $quantity = $item->get_quantity();
                $target_id = $variation_id ? $variation_id : $product_id;

                if (!$target_id) {
                    continue;
                }

                if (!$this->is_order_item_assigned_to_location($product_id, $variation_id, $location->slug)) {
                    $all_products_available = false;
                    break;
                }

                // Get location stock
                $location_stock = get_post_meta($target_id, '_location_stock_' . $location_id, true);
                $location_backorders = get_post_meta($target_id, '_location_backorders_' . $location_id, true);

                // Check stock availability
                if ($location_stock !== '') {
                    $available_stock = (int) $location_stock;

                    // If backorders are not allowed and we don't have enough stock
                    if ($location_backorders === 'off' && $available_stock < $quantity) {
                        $all_products_available = false;
                        break; // This location doesn't have enough stock for this product
                    }
                }
            }

            // If all products are available at this location, add it to available locations
            if ($all_products_available) {
                $available_locations[] = $location;
            }
        }

        return $available_locations;
    }

    /**
     * Update all order items for a specific location
     * Reusable function that updates location, price, and quantity for all items
     *
     * @param WC_Order $order Order object
     * @param string $new_location_slug Location slug to assign
     * @return array Result array with 'success', 'price_changed', 'message', and 'items_updated' count
     */
    private function update_all_order_items_location($order, $new_location_slug)
    {
        if (!$order) {
            return [
                'success' => false,
                'message' => __('Order not found', 'multi-location-product-and-inventory-management-pro')
            ];
        }

        // Check if order is editable
        if (!$order->is_editable()) {
            return [
                'success' => false,
                'message' => __('This order is no longer editable', 'multi-location-product-and-inventory-management-pro')
            ];
        }

        // Validate new location exists
        $new_location_term = get_term_by('slug', $new_location_slug, 'mulopimfwc_store_location');
        if (!$new_location_term || is_wp_error($new_location_term)) {
            return [
                'success' => false,
                'message' => __('Location not found', 'multi-location-product-and-inventory-management-pro')
            ];
        }

        global $mulopimfwc_options;
        $enable_location_stock = isset($mulopimfwc_options['enable_location_stock']) && $mulopimfwc_options['enable_location_stock'] === 'on';
        $enable_location_price = isset($mulopimfwc_options['enable_location_price']) && $mulopimfwc_options['enable_location_price'] === 'on';
        $new_location_id = $new_location_term->term_id;

        $items_updated = 0;
        $price_changed = false;
        $order_items = $order->get_items();

        // Update each order item
        foreach ($order_items as $item) {
            if (!$item->is_type('line_item')) {
                continue;
            }

            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $quantity = $item->get_quantity();
            $target_id = $variation_id ? $variation_id : $product_id;

            if (!$target_id) {
                continue;
            }

            // Get old location for stock restoration
            $old_location_slug = $item->get_meta('_mulopimfwc_location');

            // Skip if location hasn't changed
            if ($old_location_slug === $new_location_slug) {
                continue;
            }

            if (!$this->is_order_item_assigned_to_location($product_id, $variation_id, $new_location_slug)) {
                return [
                    'success' => false,
                    'message' => sprintf(
                        /* translators: 1: location name, 2: product name */
                        __('Location "%1$s" is not assigned to product "%2$s"', 'multi-location-product-and-inventory-management-pro'),
                        $new_location_term->name,
                        $item->get_name()
                    )
                ];
            }

            // Update stock if location stock management is enabled
            if ($enable_location_stock) {
                // Restore stock to old location if it exists
                if (!empty($old_location_slug)) {
                    $old_location_term = get_term_by('slug', $old_location_slug, 'mulopimfwc_store_location');
                    if ($old_location_term && !is_wp_error($old_location_term)) {
                        $old_location_id = $old_location_term->term_id;
                        $old_stock = get_post_meta($target_id, '_location_stock_' . $old_location_id, true);

                        if ($old_stock !== '') {
                            $new_old_stock = (int) $old_stock + (int) $quantity;
                            update_post_meta($target_id, '_location_stock_' . $old_location_id, $new_old_stock);
                        }
                    }
                }

                // Reduce stock from new location
                $new_stock = get_post_meta($target_id, '_location_stock_' . $new_location_id, true);

                if ($new_stock !== '') {
                    $current_stock_int = (int) $new_stock;
                    $updated_stock = max(0, $current_stock_int - (int) $quantity);
                    update_post_meta($target_id, '_location_stock_' . $new_location_id, $updated_stock);
                }
            }

            // Update order item price if location pricing is enabled
            $old_price = $item->get_subtotal();
            $new_price = $old_price;

            if ($enable_location_price) {
                // Get location-specific price
                $location_sale_price = get_post_meta($target_id, '_location_sale_price_' . $new_location_id, true);
                $location_regular_price = get_post_meta($target_id, '_location_regular_price_' . $new_location_id, true);

                // Use sale price if available, otherwise regular price, otherwise keep current price
                if (!empty($location_sale_price)) {
                    $new_price_per_unit = floatval($location_sale_price);
                } elseif (!empty($location_regular_price)) {
                    $new_price_per_unit = floatval($location_regular_price);
                } else {
                    // No location-specific price, use product's default price
                    $product_obj = wc_get_product($target_id);
                    if ($product_obj) {
                        // Get the actual price (sale price if on sale, otherwise regular price)
                        $sale_price = $product_obj->get_sale_price();
                        $regular_price = $product_obj->get_regular_price();
                        $new_price_per_unit = !empty($sale_price) ? floatval($sale_price) : floatval($regular_price);
                    } else {
                        // Fallback: calculate from current item price
                        $new_price_per_unit = floatval($old_price) / floatval($quantity);
                    }
                }

                // Calculate new subtotal and total
                $new_subtotal = $new_price_per_unit * floatval($quantity);
                $new_total = $new_subtotal; // Subtotal and total are the same before taxes

                // Update order item prices
                $item->set_subtotal($new_subtotal);
                $item->set_total($new_total);

                // Update the price per unit in meta (for display purposes)
                $item->update_meta_data('_price', $new_price_per_unit);

                $new_price = $new_subtotal;

                if ($old_price != $new_price) {
                    $price_changed = true;
                }
            }

            // Update order item meta
            $item->update_meta_data('_mulopimfwc_location', $new_location_slug);
            $item->save();

            $items_updated++;
        }

        // Recalculate order totals
        if ($items_updated > 0) {
            $order->calculate_totals();
            $order->save();

            // Add order note
            $note_message = sprintf(
                /* translators: %s: location name */
                __('All order items assigned to location: %s', 'multi-location-product-and-inventory-management-pro'),
                $new_location_term->name
            );

            if ($price_changed) {
                $note_message .= ' ' . __('| Prices updated based on location pricing', 'multi-location-product-and-inventory-management-pro');
            }

            $order->add_order_note($note_message);
        }

        return [
            'success' => true,
            'price_changed' => $price_changed,
            'items_updated' => $items_updated,
            'message' => sprintf(
                /* translators: %d: number of order items updated */
                __('%d item(s) updated successfully', 'multi-location-product-and-inventory-management-pro'),
                $items_updated
            )
        ];
    }

    /**
     * Get count of unassigned orders
     *
     * @return int Count of unassigned orders
     */
    private function get_unassigned_orders_count()
    {
        $split_parent_exclude = array(
            'relation' => 'OR',
            array(
                'key' => '_mulopimfwc_split_parent',
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key' => '_mulopimfwc_split_parent',
                'value' => 'yes',
                'compare' => '!=',
            ),
        );

        $args = array(
            'limit' => -1,
            'return' => 'ids',
            'meta_query' => array(
                array(
                    'key' => '_store_location',
                    'compare' => 'NOT EXISTS',
                ),
                $split_parent_exclude,
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
                $split_parent_exclude,
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

        $split_parent_query = array(
            'relation' => 'OR',
            array(
                'key' => '_mulopimfwc_split_parent',
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key' => '_mulopimfwc_split_parent',
                'value' => 'yes',
                'compare' => '!=',
            ),
        );

        if (!empty($query_args['meta_query'])) {
            $query_args['meta_query'] = array(
                'relation' => 'AND',
                $query_args['meta_query'],
                $split_parent_query,
            );
        } else {
            $query_args['meta_query'] = $split_parent_query;
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
        $meta_table = $wpdb->prefix . 'wc_orders_meta';
        $orders_table = $wpdb->prefix . 'wc_orders';

        if ($filter === 'unassigned') {
            // Filter for unassigned orders using LEFT JOIN and WHERE
            $clauses['join'] .= " LEFT JOIN {$meta_table} AS location_meta ON {$orders_table}.id = location_meta.order_id AND location_meta.meta_key = '_store_location'";
            $clauses['where'] .= " AND (location_meta.meta_value IS NULL OR location_meta.meta_value = '')";
        } else {
            // Filter by specific location
            $clauses['join'] .= " INNER JOIN {$meta_table} AS location_meta ON {$orders_table}.id = location_meta.order_id AND location_meta.meta_key = '_store_location'";
            $clauses['where'] .= $wpdb->prepare(" AND location_meta.meta_value = %s", $filter);
        }

        $clauses['join'] .= " LEFT JOIN {$meta_table} AS split_meta ON {$orders_table}.id = split_meta.order_id AND split_meta.meta_key = '_mulopimfwc_split_parent'";
        $clauses['where'] .= " AND (split_meta.meta_value IS NULL OR split_meta.meta_value != 'yes')";

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
        $is_manual_mode = $this->is_manual_order_assignment_enabled($options);

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
                    esc_html__('Unassigned', 'multi-location-product-and-inventory-management-pro'),
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
                esc_html__('Unassigned', 'multi-location-product-and-inventory-management-pro'),
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

        if (!current_user_can('edit_shop_orders') || !$this->current_user_can_manage_order_operations()) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'multi-location-product-and-inventory-management-pro')));
        }

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $location_slug = isset($_POST['location_slug']) ? sanitize_text_field($_POST['location_slug']) : '';

        if (!$order_id) {
            wp_send_json_error(array('message' => __('Invalid order ID', 'multi-location-product-and-inventory-management-pro')));
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(array('message' => __('Order not found', 'multi-location-product-and-inventory-management-pro')));
        }

        if (empty($location_slug)) {
            wp_send_json_error(array('message' => __('Please select a location', 'multi-location-product-and-inventory-management-pro')));
        }

        // Update all order items for the selected location using reusable function
        $update_result = $this->update_all_order_items_location($order, $location_slug);

        if (!$update_result['success']) {
            wp_send_json_error(array('message' => $update_result['message']));
        }

        // Update order location meta
        $order->update_meta_data('_store_location', $location_slug);
        $order->save();

        $location_label = $this->get_location_label($location_slug);

        wp_send_json_success(array(
            'message' => $update_result['message'],
            'location_label' => $location_label !== '' ? $location_label : '—',
            'location_slug' => $location_slug,
            'price_changed' => $update_result['price_changed'],
            'items_updated' => $update_result['items_updated'],
        ));
    }

    public function add_bulk_location_assignment($actions)
    {
        if (!$this->current_user_can_manage_order_operations()) {
            return $actions;
        }

        $options = get_option('mulopimfwc_display_options', []);
        $is_manual_mode = $this->is_manual_order_assignment_enabled($options);

        if (!$is_manual_mode) {
            return $actions;
        }

        $actions['mulopimfwc_assign_location'] = __('Assign Location', 'multi-location-product-and-inventory-management-pro');

        return $actions;
    }

    public function render_bulk_assignment_modal()
    {
        if (!$this->current_user_can_manage_order_operations()) {
            return;
        }

        if (!$this->is_orders_list_screen()) {
            return;
        }

        $options = get_option('mulopimfwc_display_options', []);
        $is_manual_mode = $this->is_manual_order_assignment_enabled($options);

        if (!$is_manual_mode) {
            return;
        }
    ?>
        <div id="mulopimfwc-bulk-assign-modal" class="mulopimfwc-modal" aria-hidden="true">
            <div class="mulopimfwc-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="mulopimfwc-bulk-assign-title">
                <button type="button" class="mulopimfwc-modal__close" aria-label="<?php echo esc_attr__('Close', 'multi-location-product-and-inventory-management-pro'); ?>">&times;</button>
                <div class="mulopimfwc-modal__header">
                    <h2 id="mulopimfwc-bulk-assign-title"><?php echo esc_html__('Assign Location', 'multi-location-product-and-inventory-management-pro'); ?></h2>
                    <p class="mulopimfwc-modal__subtitle">
                        <?php echo esc_html__('Assign a fulfillment location to all selected orders.', 'multi-location-product-and-inventory-management-pro'); ?>
                    </p>
                </div>

                <div class="mulopimfwc-modal__body">
                    <div class="mulopimfwc-bulk-assign-summary">
                        <span class="mulopimfwc-bulk-assign-count">0</span>
                        <span class="mulopimfwc-bulk-assign-label"><?php echo esc_html__('orders selected', 'multi-location-product-and-inventory-management-pro'); ?></span>
                    </div>

                    <div class="mulopimfwc-bulk-assign-orders">
                        <div class="mulopimfwc-bulk-assign-orders__header">
                            <span><?php echo esc_html__('Orders Preview', 'multi-location-product-and-inventory-management-pro'); ?></span>
                        </div>
                        <div class="mulopimfwc-bulk-assign-orders__list"></div>
                    </div>

                    <div class="mulopimfwc-bulk-assign-location">
                        <label for="mulopimfwc-bulk-location-select"><?php echo esc_html__('Available Locations', 'multi-location-product-and-inventory-management-pro'); ?></label>
                        <select id="mulopimfwc-bulk-location-select">
                            <option value=""><?php echo esc_html__('Select a location', 'multi-location-product-and-inventory-management-pro'); ?></option>
                        </select>
                        <p class="description">
                            <?php echo esc_html__('Only locations with sufficient stock for all items in the selected orders are shown.', 'multi-location-product-and-inventory-management-pro'); ?>
                        </p>
                    </div>

                    <div class="mulopimfwc-bulk-assign-message" style="display:none;"></div>
                </div>

                <div class="mulopimfwc-modal__footer">
                    <button type="button" class="button button-secondary mulopimfwc-modal-cancel">
                        <?php echo esc_html__('Cancel', 'multi-location-product-and-inventory-management-pro'); ?>
                    </button>
                    <button type="button" class="button button-primary mulopimfwc-bulk-assign-confirm" disabled>
                        <?php echo esc_html__('Assign Location', 'multi-location-product-and-inventory-management-pro'); ?>
                    </button>
                </div>
            </div>
        </div>
<?php
    }

    public function ajax_get_bulk_assignment_data()
    {
        check_ajax_referer('mulopimfwc_bulk_assign_location', 'nonce');

        if (!current_user_can('edit_shop_orders') || !$this->current_user_can_manage_order_operations()) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'multi-location-product-and-inventory-management-pro')]);
        }

        $options = get_option('mulopimfwc_display_options', []);
        $is_manual_mode = $this->is_manual_order_assignment_enabled($options);
        if (!$is_manual_mode) {
            wp_send_json_error(['message' => __('Manual assignment is not enabled.', 'multi-location-product-and-inventory-management-pro')]);
        }

        $order_ids = isset($_POST['order_ids']) ? array_map('absint', (array) $_POST['order_ids']) : [];
        $order_ids = array_values(array_filter($order_ids));

        if (empty($order_ids)) {
            wp_send_json_error(['message' => __('No orders selected', 'multi-location-product-and-inventory-management-pro')]);
        }

        $orders = [];
        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $orders[] = $order;
            }
        }

        if (empty($orders)) {
            wp_send_json_error(['message' => __('No valid orders found', 'multi-location-product-and-inventory-management-pro')]);
        }

        $order_available_slugs = [];
        $common_slugs = null;
        foreach ($orders as $order) {
            $locations = $this->get_available_locations_for_order($order);
            $slugs = array_values(array_filter(wp_list_pluck($locations, 'slug')));
            $order_available_slugs[$order->get_id()] = $slugs;

            if ($common_slugs === null) {
                $common_slugs = $slugs;
            } else {
                $common_slugs = array_values(array_intersect($common_slugs, $slugs));
            }
        }

        $available_locations = [];
        if (!empty($common_slugs)) {
            $available_locations = get_terms(array(
                'taxonomy' => 'mulopimfwc_store_location',
                'hide_empty' => false,
                'orderby' => 'name',
                'order' => 'ASC',
                'slug' => $common_slugs,
            ));
            $available_locations = is_wp_error($available_locations) ? [] : $available_locations;
        }

        $locations_payload = array_map(function ($location) {
            return [
                'slug' => $location->slug,
                'name' => $location->name,
            ];
        }, $available_locations);

        $blocking_orders = [];
        if (empty($common_slugs) && count($orders) > 1) {
            foreach ($orders as $order) {
                $order_id = $order->get_id();
                $other_common = null;
                foreach ($orders as $other_order) {
                    if ($order_id === $other_order->get_id()) {
                        continue;
                    }
                    $other_slugs = $order_available_slugs[$other_order->get_id()] ?? [];
                    if ($other_common === null) {
                        $other_common = $other_slugs;
                    } else {
                        $other_common = array_values(array_intersect($other_common, $other_slugs));
                    }
                    if (empty($other_common)) {
                        break;
                    }
                }

                if (!empty($other_common)) {
                    $blocking_orders[$order_id] = true;
                }
            }
        }

        $orders_preview = [];
        $all_editable = true;
        $non_editable_count = 0;
        foreach ($orders as $order) {
            $customer_name = trim($order->get_formatted_billing_full_name());
            $status = $order->get_status();
            $is_editable = $order->is_editable();
            if (!$is_editable) {
                $all_editable = false;
                $non_editable_count++;
            }
            $order_id = $order->get_id();
            $available_slugs = $order_available_slugs[$order_id] ?? [];
            $has_locations = !empty($available_slugs);
            $orders_preview[] = [
                'id' => $order->get_id(),
                'number' => $order->get_order_number(),
                'status' => $status,
                'status_label' => wc_get_order_status_name('wc-' . $status),
                'total' => wp_strip_all_tags($order->get_formatted_order_total()),
                'customer' => $customer_name !== '' ? $customer_name : __('Guest', 'multi-location-product-and-inventory-management-pro'),
                'location' => $this->get_location_label((string) $order->get_meta('_store_location')),
                'editable' => $is_editable,
                'has_locations' => $has_locations,
                'blocker' => !empty($blocking_orders[$order_id]),
            ];
        }

        wp_send_json_success([
            'orders' => $orders_preview,
            'locations' => $locations_payload,
            'all_editable' => $all_editable,
            'non_editable_count' => $non_editable_count,
        ]);
    }

    public function ajax_bulk_assign_location()
    {
        check_ajax_referer('mulopimfwc_bulk_assign_location', 'nonce');

        if (!current_user_can('edit_shop_orders') || !$this->current_user_can_manage_order_operations()) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'multi-location-product-and-inventory-management-pro')]);
        }

        $options = get_option('mulopimfwc_display_options', []);
        $is_manual_mode = $this->is_manual_order_assignment_enabled($options);
        if (!$is_manual_mode) {
            wp_send_json_error(['message' => __('Manual assignment is not enabled.', 'multi-location-product-and-inventory-management-pro')]);
        }

        $order_ids = isset($_POST['order_ids']) ? array_map('absint', (array) $_POST['order_ids']) : [];
        $order_ids = array_values(array_filter($order_ids));
        $location_slug = isset($_POST['location_slug']) ? sanitize_text_field(wp_unslash($_POST['location_slug'])) : '';

        if (empty($order_ids) || empty($location_slug)) {
            wp_send_json_error(['message' => __('Order IDs and location are required', 'multi-location-product-and-inventory-management-pro')]);
        }

        $orders = [];
        $failed = [];
        $non_editable = [];

        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) {
                $failed[] = $order_id;
                continue;
            }

            if (!$order->is_editable()) {
                $non_editable[] = $order_id;
            }

            $orders[] = $order;
        }

        if (!empty($non_editable)) {
            $message = sprintf(
                /* translators: %d: number of non-editable orders */
                _n(
                    '%d selected order is not editable. Remove it to continue.',
                    '%d selected orders are not editable. Remove them to continue.',
                    count($non_editable),
                    'multi-location-product-and-inventory-management-pro'
                ),
                count($non_editable)
            );
            wp_send_json_error([
                'message' => $message,
                'non_editable' => $non_editable,
            ]);
        }

        if (empty($orders)) {
            wp_send_json_error(['message' => __('No valid orders found', 'multi-location-product-and-inventory-management-pro')]);
        }

        $price_changed = false;
        $updated_count = 0;

        foreach ($orders as $order) {
            $order_id = $order->get_id();
            $available_locations = $this->get_available_locations_for_order($order);
            $available_slugs = wp_list_pluck($available_locations, 'slug');
            if (!in_array($location_slug, $available_slugs, true)) {
                $failed[] = $order_id;
                continue;
            }

            $update_result = $this->update_all_order_items_location($order, $location_slug);
            if (!$update_result['success']) {
                $failed[] = $order_id;
                continue;
            }

            $order->update_meta_data('_store_location', $location_slug);
            $order->save();

            $updated_count++;
            if ($update_result['price_changed']) {
                $price_changed = true;
            }
        }

        if ($updated_count === 0) {
            wp_send_json_error(['message' => __('No orders were updated. Please review stock availability and order status.', 'multi-location-product-and-inventory-management-pro')]);
        }

        $message = sprintf(
            /* translators: %d: number of orders updated */
            __('Assigned location to %d order(s).', 'multi-location-product-and-inventory-management-pro'),
            $updated_count
        );

        if (!empty($failed)) {
            $message .= ' ' . sprintf(
                /* translators: %d: number of orders that could not be updated */
                __('%d order(s) could not be updated.', 'multi-location-product-and-inventory-management-pro'),
                count($failed)
            );
        }

        wp_send_json_success([
            'message' => $message,
            'price_changed' => $price_changed,
            'failed' => $failed,
        ]);
    }

    /**
     * Add location metabox to order details
     */
    public function add_location_metabox()
    {
        $screen = $this->get_order_screen_id();

        add_meta_box(
            'wc_store_location_metabox',
            __('Store Location', 'multi-location-product-and-inventory-management-pro'),
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

    private function is_orders_list_screen($screen = null)
    {
        if (!function_exists('get_current_screen')) {
            return false;
        }

        $screen = $screen ?: get_current_screen();
        if (!$screen) {
            return false;
        }

        if (in_array($screen->id, ['woocommerce_page_wc-orders', 'edit-shop_order'], true)) {
            return true;
        }

        if ($screen->post_type === 'shop_order' && $screen->base === 'edit') {
            return true;
        }

        return false;
    }

    public function enqueue_bulk_assignment_assets($hook)
    {
        if (!$this->current_user_can_manage_order_operations()) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$this->is_orders_list_screen($screen)) {
            return;
        }

        $options = get_option('mulopimfwc_display_options', []);
        $is_manual_mode = $this->is_manual_order_assignment_enabled($options);

        if (!$is_manual_mode) {
            return;
        }

        wp_enqueue_script('jquery');

        $data = [
            'nonce' => wp_create_nonce('mulopimfwc_bulk_assign_location'),
            'i18n' => [
                'title' => __('Assign Location', 'multi-location-product-and-inventory-management-pro'),
                'loading' => __('Loading available locations...', 'multi-location-product-and-inventory-management-pro'),
                'selectLocation' => __('Select a location', 'multi-location-product-and-inventory-management-pro'),
                'assignLocation' => __('Assign Location', 'multi-location-product-and-inventory-management-pro'),
                'confirmAssign' => __('Confirm & Assign', 'multi-location-product-and-inventory-management-pro'),
                'cancel' => __('Cancel', 'multi-location-product-and-inventory-management-pro'),
                'noOrders' => __('Please select at least one order.', 'multi-location-product-and-inventory-management-pro'),
                'noOrdersSelected' => __('No orders selected.', 'multi-location-product-and-inventory-management-pro'),
                'noLocations' => __('No locations have sufficient stock for all selected orders. Remove the highlighted orders to continue.', 'multi-location-product-and-inventory-management-pro'),
                'confirmMessage' => __('This will update the location for the selected orders. Continue?', 'multi-location-product-and-inventory-management-pro'),
                'assigning' => __('Assigning...', 'multi-location-product-and-inventory-management-pro'),
                'failed' => __('Bulk assignment failed. Please try again.', 'multi-location-product-and-inventory-management-pro'),
                'unassigned' => __('Unassigned', 'multi-location-product-and-inventory-management-pro'),
                'loadingOrders' => __('Loading orders...', 'multi-location-product-and-inventory-management-pro'),
                'notEditable' => __('Some selected orders are not editable. Remove the highlighted orders to enable location selection.', 'multi-location-product-and-inventory-management-pro'),
                'notEditableTag' => __('Not editable', 'multi-location-product-and-inventory-management-pro'),
                'noLocationsTag' => __('No available locations', 'multi-location-product-and-inventory-management-pro'),
                'noCommonLocationTag' => __('No common location', 'multi-location-product-and-inventory-management-pro'),
                'removeOrder' => __('Remove', 'multi-location-product-and-inventory-management-pro'),
                'removeOrderLabel' => __('Remove order #', 'multi-location-product-and-inventory-management-pro'),
            ],
        ];

        wp_add_inline_script('jquery', 'window.mulopimfwcBulkAssign=' . wp_json_encode($data) . ';', 'before');
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
        $is_unassigned = ($location_slug === '');
        $locations = get_terms(array(
            'taxonomy' => 'mulopimfwc_store_location',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ));
        $options = get_option('mulopimfwc_display_options', []);
        $is_manual_mode = $this->is_manual_order_assignment_enabled($options);
        $is_currency_locked = $this->is_location_currency_lock_enabled($options);
        $can_edit = (current_user_can('edit_shop_order', $order->get_id()) || current_user_can('manage_woocommerce'))
            && $this->current_user_can_manage_order_operations()
            && !$is_currency_locked;

        $location_stock_summaries = [];
        if (!is_wp_error($locations) && !empty($locations)) {
            foreach ($locations as $location_term) {
                $location_stock_summaries[$location_term->slug] = $this->build_location_stock_snapshot($order, $location_term);
            }
        }

        echo '<div class="wc-store-location-container">';

        wp_nonce_field('mulopimfwc_store_location_metabox', 'mulopimfwc_store_location_nonce');

        $is_split_parent = $order->get_meta('_mulopimfwc_split_parent') === 'yes';
        $is_split_child = $order->get_meta('_mulopimfwc_split_child') === 'yes';
        $is_location_manager = $this->is_current_user_location_manager();
        $allowed_slugs = $this->get_current_user_allowed_location_slugs();

        if ($is_split_parent || $is_split_child) {
            echo '<div class="mulopimfwc-order-split-links" style="margin-bottom:10px;padding:10px;border:1px solid #e2e8f0;background:#f8fafc;border-radius:6px;">';
            if ($is_split_parent) {
                $child_ids = (array) $order->get_meta('_mulopimfwc_split_children');
                $child_ids = array_values(array_filter(array_map('absint', $child_ids)));

                echo '<strong>' . esc_html__('Split Parent', 'multi-location-product-and-inventory-management-pro') . '</strong>';

                $links = array();

                foreach ($child_ids as $child_id) {
                    $child_order = wc_get_order($child_id);

                    if (! $child_order) {
                        continue;
                    }

                    $child_location_slug = (string) $child_order->get_meta('_store_location');

                    if (is_array($allowed_slugs) && ! in_array($child_location_slug, $allowed_slugs, true)) {
                        continue;
                    }

                    $url     = $this->get_order_edit_url_by_id($child_id);
                    $links[] = '<a href="' . esc_url($url) . '">#' . esc_html($child_id) . '</a>';
                }

                if (! empty($links)) {
                    echo '<div style="margin-top:6px;">' . esc_html__(
                        'Children:',
                        'multi-location-product-and-inventory-management-pro'
                    ) . ' ' . wp_kses_post(implode(', ', $links)) . '</div>';
                } else {
                    $empty_message = is_array($allowed_slugs)
                        ? __('No child orders for your locations.', 'multi-location-product-and-inventory-management-pro')
                        : __('No child orders yet.', 'multi-location-product-and-inventory-management-pro');

                    echo '<div style="margin-top:6px;">' . esc_html($empty_message) . '</div>';
                }
            }

            if ($is_split_child) {
                $parent_id = (int) $order->get_meta('_mulopimfwc_split_parent_id');
                if ($parent_id > 0) {
                    echo '<div style="margin-top:6px;"><strong>' . esc_html__('Split Child', 'multi-location-product-and-inventory-management-pro') . '</strong>';
                    if (!$is_location_manager) {
                        $url = $this->get_order_edit_url_by_id($parent_id);
                        echo ' ' . esc_html__('Parent:', 'multi-location-product-and-inventory-management-pro') . ' <a href="' . esc_url($url) . '">#' . esc_html($parent_id) . '</a>';
                    }
                    echo '</div>';
                }
            }
            echo '</div>';
        }


        if ($is_unassigned && $is_manual_mode) {
            echo '<div class="notice notice-warning inline mulopimfwc-location-alert">';
            echo '<p><strong><span class="dashicons dashicons-warning" aria-hidden="true"></span> ' . esc_html__('This order needs location assignment', 'multi-location-product-and-inventory-management-pro') . '</strong></p>';
            echo '<p>' . esc_html__('Please select a fulfillment location to continue processing this order.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            echo '</div>';
        }

        if (!is_wp_error($locations) && !empty($locations)) {
            echo '<p>';
            echo '<label for="mulopimfwc_store_location" class="screen-reader-text">' . esc_html__('Store Location', 'multi-location-product-and-inventory-management-pro') . '</label>';
            $select_classes = 'mulopimfwc-location-select' . ($is_unassigned ? ' is-unassigned' : '');
            echo '<select name="mulopimfwc_store_location" id="mulopimfwc_store_location" class="' . esc_attr($select_classes) . '" data-current-location="' . esc_attr($location_slug) . '"' . disabled(!$can_edit, true, false) . '>';
            echo '<option value="" data-stock-status="" data-stock-summary="" data-stock-items="[]">' . esc_html__('Unassigned location', 'multi-location-product-and-inventory-management-pro') . '</option>';

            $has_current = false;
            foreach ($locations as $location_term) {
                if ($location_term->slug === $location_slug) {
                    $has_current = true;
                    break;
                }
            }

            if (!$has_current && $location_slug !== '') {
                echo '<option value="' . esc_attr($location_slug) . '" selected="selected" data-stock-status="" data-stock-summary="" data-stock-items="[]">' . esc_html($this->get_location_label($location_slug)) . '</option>';
            }

            foreach ($locations as $location_term) {
                $summary = $location_stock_summaries[$location_term->slug] ?? [
                    'status' => 'unknown',
                    'summary' => __('Stock not tracked', 'multi-location-product-and-inventory-management-pro'),
                    'items' => [],
                ];
                $summary_text = $summary['summary'] ?? '';
                $label = $location_term->name;
                if ($summary_text !== '') {
                    $label .= ' - ' . $summary_text;
                }

                $is_disabled = false;
                // Disable if stock status is insufficient or location is not assigned.
                if (isset($summary['status']) && in_array($summary['status'], ['insufficient', 'not-assigned'], true)) {
                    $is_disabled = true;
                }
                echo '<option value="' . esc_attr($location_term->slug) . '" ' . selected($location_slug, $location_term->slug, false) .
                    ' data-stock-status="' . esc_attr($summary['status'] ?? '') . '"' .
                    ' data-stock-summary="' . esc_attr($summary_text) . '"' .
                    ' data-stock-items="' . esc_attr(wp_json_encode($summary['items'] ?? [])) . '" ' .
                    disabled($is_disabled, true, false) .
                    '>';
                echo esc_html($label);
                echo '</option>';
            }

            echo '</select>';
            echo '</p>';
            echo '<div class="mulopimfwc-location-stock-panel" data-empty-message="' . esc_attr__('Select a location to view stock availability.', 'multi-location-product-and-inventory-management-pro') . '" style="display:none;">';
            echo '<div class="mulopimfwc-location-stock-summary"></div>';
            echo '<div class="mulopimfwc-location-stock-list"></div>';
            echo '<div class="mulopimfwc-location-stock-message" style="display:none;"></div>';
            echo '</div>';
            echo '<p class="description">' . esc_html__('Update user selected store location for this order.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            // Display a styled info note with svg icon, improved UI
            $info_icon = function_exists('mulopimfwc_svg_icon')
                ? mulopimfwc_svg_icon('info')
                : '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" style="fill: #3498db;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="#3498db"/><rect x="11" y="10" width="2" height="7" fill="#fff"/><rect x="11" y="7" width="2" height="2" fill="#fff"/></svg>';

            echo '<div class="mulopimfwc-info-note" style="display: flex; align-items: flex-start; gap: 8px; background: #f1f5fb; border-left: 4px solid #63b3ed; padding: 10px 12px; margin: 10px 0 0 0; border-radius: 4px;">'
                . '<span style="display: flex; align-items: center; margin-top:2px;">'
                . wp_kses($info_icon, $this->get_svg_icon_allowed_html())
                . '</span>'
                . '<span style="font-size:13px; line-height:1.5;color:#223;">'
                . esc_html__('You can only change the store location if the order is editable (pending or on-hold).', 'multi-location-product-and-inventory-management-pro')
                . '</span>'
                . '</div>';
        } else {
            $location_label = $this->get_location_label($location_slug);
            if ($location_label !== '') {
                echo '<p>' . esc_html($location_label) . '</p>';
            }
            echo '<p class="description">' . esc_html__('No locations found. Add a location to enable changes.', 'multi-location-product-and-inventory-management-pro') . '</p>';
        }

        if (!$can_edit) {
            if ($is_currency_locked) {
                echo '<p class="description">' . esc_html__('Order location changes are disabled while Location Wise Currency is enabled.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            } else {
                echo '<p class="description">' . esc_html__('You do not have permission to change the location.', 'multi-location-product-and-inventory-management-pro') . '</p>';
            }
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

        if (!$this->current_user_can_manage_order_operations()) {
            return;
        }

        $options = get_option('mulopimfwc_display_options', []);
        if ($this->is_location_currency_lock_enabled($options)) {
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
            $order->save();
            return;
        } else {
            if ($new_location === $current_location) {
                return;
            }
            $update_result = $this->update_all_order_items_location($order, $new_location);
            if (!$update_result['success']) {
                $order->add_order_note(sprintf(
                    /* translators: %s: error message explaining why assignment failed */
                    __('Location assignment failed: %s', 'multi-location-product-and-inventory-management-pro'),
                    $update_result['message']
                ));
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
        $mulopimfwc_options = is_array($mulopimfwc_options) ? $mulopimfwc_options : [];

        $enable_location_urls = (isset($mulopimfwc_options['enable_location_urls']) && $mulopimfwc_options['enable_location_urls'] === 'on') ? 'on' : 'off';
        $location_url_prefix = isset($mulopimfwc_options['location_url_prefix']) ? sanitize_title((string) $mulopimfwc_options['location_url_prefix']) : 'store-location';
        if ($location_url_prefix === '') {
            $location_url_prefix = 'store-location';
        }

        $url_format = isset($mulopimfwc_options['url_location_format']) ? sanitize_key((string) $mulopimfwc_options['url_location_format']) : 'query_param';
        if (!in_array($url_format, ['query_param', 'path_prefix'], true)) {
            $url_format = 'query_param';
        }

        $location_urls_enabled = $enable_location_urls === 'on';

        // Configure taxonomy settings based on URL format
        $taxonomy_args = [
            'labels' => [
                'name' => __('Locations', 'multi-location-product-and-inventory-management-pro'),
                'singular_name' => __('Location', 'multi-location-product-and-inventory-management-pro'),
                'search_items' => __('Search Location', 'multi-location-product-and-inventory-management-pro'),
                'all_items' => __('All Locations', 'multi-location-product-and-inventory-management-pro'),
                'parent_item' => __('Parent Location', 'multi-location-product-and-inventory-management-pro'),
                'parent_item_colon' => __('Parent Location:', 'multi-location-product-and-inventory-management-pro'),
                'edit_item' => __('Edit Location', 'multi-location-product-and-inventory-management-pro'),
                'view_item' => __('View Location', 'multi-location-product-and-inventory-management-pro'),
                'update_item' => __('Update Location', 'multi-location-product-and-inventory-management-pro'),
                'add_new_item' => __('Add New Location', 'multi-location-product-and-inventory-management-pro'),
                'new_item_name' => __('New Location Name', 'multi-location-product-and-inventory-management-pro'),
                'separate_items_with_commas' => __('Separate locations with commas', 'multi-location-product-and-inventory-management-pro'),
                'add_or_remove_items' => __('Add or remove locations', 'multi-location-product-and-inventory-management-pro'),
                'choose_from_most_used' => __('Choose from most used locations', 'multi-location-product-and-inventory-management-pro'),
                'not_found' => __('No locations found', 'multi-location-product-and-inventory-management-pro'),
                'no_terms' => __('No locations', 'multi-location-product-and-inventory-management-pro'),
                'menu_name' => __('Locations', 'multi-location-product-and-inventory-management-pro'),
                'popular_items' => __('Popular Locations', 'multi-location-product-and-inventory-management-pro'),
                'back_to_items' => __('Back to Locations', 'multi-location-product-and-inventory-management-pro'),
            ],
            'description' => __('Manage locations for products and inventory tracking', 'multi-location-product-and-inventory-management-pro'),
            'public' => $location_urls_enabled,
            'publicly_queryable' => $location_urls_enabled,
            'hierarchical' => true,
            'show_ui' => true,
            'show_in_menu' => $location_urls_enabled,
            'show_in_nav_menus' => $location_urls_enabled,
            'show_in_rest' => $location_urls_enabled,
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

        if (!$location_urls_enabled) {
            $taxonomy_args['query_var'] = false;
            $taxonomy_args['rewrite'] = false;
            register_taxonomy('mulopimfwc_store_location', 'product', $taxonomy_args);
            return;
        }

        // Configure based on URL format
        switch ($url_format) {
            case 'path_prefix':
                // URL format: /location/location-name
                $taxonomy_args['query_var'] = 'mulopimfwc_store_location';
                $taxonomy_args['rewrite'] = [
                    'slug' => $location_url_prefix,
                    'with_front' => false,
                    'hierarchical' => true,
                ];
                break;

            case 'query_param':
            default:
                // URL format: ?{prefix}=location-name
                $taxonomy_args['query_var'] = $location_url_prefix;
                $taxonomy_args['rewrite'] = false;
                break;
        }

        register_taxonomy('mulopimfwc_store_location', 'product', $taxonomy_args);
    }

    private function resolve_store_location_term_path($term_path)
    {
        $term_path = trim((string) $term_path, " \t\n\r\0\x0B/");
        if ($term_path === '') {
            return false;
        }

        $slugs = array_values(array_filter(array_map(static function ($path_part) {
            return sanitize_title(rawurldecode((string) $path_part));
        }, explode('/', $term_path))));

        if (empty($slugs)) {
            return false;
        }

        $term = get_term_by('slug', end($slugs), 'mulopimfwc_store_location');
        if (!$term || is_wp_error($term)) {
            return false;
        }

        if (count($slugs) === 1) {
            return $term;
        }

        $expected_ancestors = array_reverse(array_slice($slugs, 0, -1));
        $parent_id = (int) $term->parent;

        foreach ($expected_ancestors as $expected_slug) {
            if ($parent_id <= 0) {
                return false;
            }

            $parent = get_term($parent_id, 'mulopimfwc_store_location');
            if (!$parent || is_wp_error($parent)) {
                return false;
            }

            if (sanitize_title(rawurldecode((string) $parent->slug)) !== $expected_slug) {
                return false;
            }

            $parent_id = (int) $parent->parent;
        }

        return $term;
    }

    public function normalize_store_location_hierarchical_request($query_vars)
    {
        if (!is_array($query_vars)) {
            return $query_vars;
        }

        if (
            isset($query_vars['taxonomy'], $query_vars['term']) &&
            $query_vars['taxonomy'] === 'mulopimfwc_store_location' &&
            is_string($query_vars['term']) &&
            strpos($query_vars['term'], '/') !== false
        ) {
            $term = $this->resolve_store_location_term_path($query_vars['term']);
            if ($term && !is_wp_error($term)) {
                $query_vars['term'] = sanitize_title(rawurldecode((string) $term->slug));
            }
        }

        $query_var_keys = ['mulopimfwc_store_location'];
        if (function_exists('mulopimfwc_get_location_url_query_var')) {
            $location_url_query_var = mulopimfwc_get_location_url_query_var();
            if ($location_url_query_var !== '' && !in_array($location_url_query_var, $query_var_keys, true)) {
                $query_var_keys[] = $location_url_query_var;
            }
        }

        foreach ($query_var_keys as $query_var_key) {
            if (
                isset($query_vars[$query_var_key]) &&
                is_string($query_vars[$query_var_key]) &&
                strpos($query_vars[$query_var_key], '/') !== false
            ) {
                $term = $this->resolve_store_location_term_path($query_vars[$query_var_key]);
                if ($term && !is_wp_error($term)) {
                    $query_vars[$query_var_key] = sanitize_title(rawurldecode((string) $term->slug));
                }
            }
        }

        return $query_vars;
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
        $label = $st['open'] ? __('Open', 'multi-location-product-and-inventory-management-pro')
            : __('Closed', 'multi-location-product-and-inventory-management-pro');
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

    private function current_user_can_view_admin_bar_notifications(): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        if (!$this->is_current_user_location_manager()) {
            return current_user_can('manage_woocommerce');
        }

        if (!class_exists('MULOPIMFWC_Location_Managers')) {
            return false;
        }

        return (
            MULOPIMFWC_Location_Managers::user_has_capability('view_dashboard') ||
            MULOPIMFWC_Location_Managers::user_has_capability('view_products') ||
            MULOPIMFWC_Location_Managers::user_has_capability('manage_products') ||
            MULOPIMFWC_Location_Managers::user_has_capability('view_orders') ||
            MULOPIMFWC_Location_Managers::user_has_capability('manage_orders') ||
            MULOPIMFWC_Location_Managers::user_has_capability('run_reports')
        );
    }

    /**
     * Add notification icon to admin bar
     */
    public function add_admin_bar_notification_icon($wp_admin_bar)
    {
        if (!$this->current_user_can_view_admin_bar_notifications()) {
            return;
        }

        // Add main notification menu item to top-secondary (right side of admin bar)
        $notification_icon = '<span class="mulopimfwc-adminbar-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg></span>';
        $wp_admin_bar->add_node(array(
            'id' => 'mulopimfwc-notifications',
            'parent' => 'top-secondary',
            'title' => $notification_icon . '<span class="screen-reader-text">' . esc_html__('Notifications', 'multi-location-product-and-inventory-management-pro') . '</span><span class="ab-label mulopimfwc-notification-count" data-count="0">0</span>',
            'meta' => array(
                'class' => 'mulopimfwc-admin-bar-notification',
                'title' => __('Notifications', 'multi-location-product-and-inventory-management-pro'),
            ),
        ));

        // Add dropdown container as child
        $wp_admin_bar->add_node(array(
            'parent' => 'mulopimfwc-notifications',
            'id' => 'mulopimfwc-notifications-dropdown',
            'title' => '<div id="mulopimfwc-notifications-list" class="mulopimfwc-notifications-dropdown-content"><div class="mulopimfwc-notifications-loading">' . __('Loading notifications...', 'multi-location-product-and-inventory-management-pro') . '</div></div>',
            'meta' => array(
                'class' => 'mulopimfwc-notifications-dropdown',
            ),
        ));
    }
}
