<?php

/**
 * Plugin Name: Multi Location Product & Inventory Management for WooCommerce Pro
 * Plugin URI: https://plugincy.com/multi-location-product-and-inventory-management
 * Description: Filter WooCommerce products by store locations with a location selector for customers.
 * Version: 1.0.6.20
 * Author: plugincy
 * Author URI: https://plugincy.com/
 * Text Domain: multi-location-product-and-inventory-management
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 4.0
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires Plugins: woocommerce
 */

if (!defined('ABSPATH')) exit;

if (!defined('MULTI_LOCATION_PLUGIN_URL')) {
    define('MULTI_LOCATION_PLUGIN_URL', plugin_dir_url(__FILE__));
}

if (!defined('MULTI_LOCATION_PLUGIN_BASE_NAME')) {
    define('MULTI_LOCATION_PLUGIN_BASE_NAME', plugin_basename(__FILE__));
}

if (!defined('mulopimfwc_VERSION')) {
    define("mulopimfwc_VERSION", "1.0.6.20");
}

if (!function_exists('mulopimfwc_get_location_cookie_expiry_days')) {
    /**
     * Return the configured number of days for location cookies (default: 30).
     *
     * @return int
     */
    function mulopimfwc_get_location_cookie_expiry_days(): int
    {
        $options = get_option('mulopimfwc_display_options', []);
        $value = isset($options['location_cookie_expiry']) && is_numeric($options['location_cookie_expiry'])
            ? (int)$options['location_cookie_expiry']
            : 30;

        if ($value < 1) {
            $value = 1;
        }

        return $value;
    }
}

if (!function_exists('mulopimfwc_get_location_cookie_expiry_seconds')) {
    /**
     * Return the configured cookie expiry interval in seconds, honoring WP's DAY_IN_SECONDS.
     *
     * @return int
     */
    function mulopimfwc_get_location_cookie_expiry_seconds(): int
    {
        $day_in_seconds = defined('DAY_IN_SECONDS') ? DAY_IN_SECONDS : 86400;
        return mulopimfwc_get_location_cookie_expiry_days() * $day_in_seconds;
    }
}

// Check if the free version is installed and deactivate it if active
add_action('init', function () {
    if (is_plugin_active('multi-location-product-and-inventory-management/multi-location-product-and-inventory-management.php')) {
        deactivate_plugins('multi-location-product-and-inventory-management/multi-location-product-and-inventory-management.php');
        add_action('admin_notices', function () {
            echo '<div class="notice notice-warning is-dismissible"><p>';
            esc_html_e('Multi Location Product & Inventory Management for WooCommerce (free version) has been deactivated. Please use only the Pro version.', 'multi-location-product-and-inventory-management-pro');
            echo '</p></div>';
        });
    }
}, 1);

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function () {
        echo '<div class="error"><p>' . esc_html_e('Location Wise Products requires WooCommerce to be installed and active.', 'multi-location-product-and-inventory-management') . '</p></div>';
    });
    return;
}


if (!function_exists('mulopimfwc_get_values')) {

    global $mulopimfwc_locations, $mulopimfwc_allowed_tags, $mulopimfwc_options;

    function mulopimfwc_get_values()
    {
        global $mulopimfwc_locations, $mulopimfwc_allowed_tags, $mulopimfwc_options;

        // Check if taxonomy exists
        if (!taxonomy_exists('mulopimfwc_store_location')) {
            return;
        }

        // Check if current user is a location manager
        $is_location_manager = false;
        $assigned_location_slugs = [];

        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            if (in_array('mulopimfwc_location_manager', $user->roles)) {
                $is_location_manager = true;
                $assigned_location_slugs = get_user_meta($user->ID, 'mulopimfwc_assigned_locations', true);

                if (!is_array($assigned_location_slugs)) {
                    $assigned_location_slugs = [];
                }
            }
        }

        // Get locations based on user role
        if ($is_location_manager && !empty($assigned_location_slugs)) {
            // For location managers, only get their assigned locations
            $mulopimfwc_locations = get_terms([
                'taxonomy' => 'mulopimfwc_store_location',
                'hide_empty' => false,
                'slug' => $assigned_location_slugs,
            ]);
        } else {
            // For admins and other users, get all locations
            $mulopimfwc_locations = get_terms([
                'taxonomy' => 'mulopimfwc_store_location',
                'hide_empty' => false,
            ]);
        }

        $mulopimfwc_options = get_option('mulopimfwc_display_options') ?:
            [
                'enable_location_stock' => 'on',
                'enable_location_price' => 'on',
                'enable_location_backorder' => 'on',
                'enable_all_locations' => 'on',
                'location_change_notification' => 'on',
                'display_location_single_product' => 'on',
                'allow_data_share' => 'on',
                'strict_filtering' => 'enabled',
                'location_require_selection' => 'on',
                'enable_product_filter' => 'on',
                'social_notifications' => [
                    'enabled' => 'off',
                    'new_order' => 'on',
                    'low_stock' => 'on',
                    'out_of_stock' => 'on',
                    'daily_digest' => 'off',
                    'site_status' => 'off',
                    'daily_digest_time' => '07:00',
                ],
                'notification_settings' => [
                    'realtime_enabled' => 'on',
                    'floating_enabled' => 'on',
                    'floating_position' => 'top-right',
                    'floating_duration' => '6000',
                    'notification_template' => '[{event}] {message}',
                    'pwa_enabled' => 'off',
                    'show_admin_notice' => 'on',
                ],

            ];

        $mulopimfwc_allowed_tags = array(
            'a' => array(
                'href' => array(),
                'title' => array(),
                'class' => array(),
                'target' => array(), // Allow target attribute for links
                'style' => array(),
                'id' => array(),
            ),
            'strong' => array(
                'class' => array(),
                'style' => array(),
                'id' => array(),
            ),
            'em' => array(
                'class' => array(),
                'style' => array(),
                'id' => array(),
            ),
            'li' => array(
                'class' => array(),
                'style' => array(),
                'id' => array(),
            ),
            'div' => array(
                'class' => array(),
                'id' => array(), // Allow id for divs
                'style' => array(),
            ),
            'img' => array(
                'src' => array(),
                'alt' => array(),
                'class' => array(),
                'width' => array(), // Allow width attribute
                'height' => array(), // Allow height attribute
                'style' => array(),
                'id' => array(),
                'data-src' => array(),
            ),
            'h1' => array(
                'class' => array(),
                'style' => array(),
                'id' => array(),
            ), // Allow h1
            'h2' => array(
                'class' => array(),
                'style' => array(),
                'id' => array(),
            ),
            'h3' => array(
                'class' => array(),
                'style' => array(),
                'id' => array(),
            ), // Allow h3
            'h4' => array(
                'class' => array(),
                'style' => array(),
                'id' => array(),
            ), // Allow h4
            'h5' => array(
                'class' => array(),
                'style' => array(),
                'id' => array(),
            ), // Allow h5
            'h6' => array(
                'class' => array(),
                'style' => array(),
                'id' => array(),
            ), // Allow h6
            'span' => array(
                'class' => array(),
                'style' => array(),
                'id' => array(),
            ),
            'p' => array(
                'class' => array(),
                'style' => array(),
                'id' => array(),
            ),
            'br' => array(
                'style' => array(),
                'class' => array(),
            ), // Allow line breaks
            'blockquote' => array(
                'cite' => array(), // Allow cite attribute for blockquotes
                'class' => array(),
                'style' => array(),
                'id' => array(),
            ),
            'table' => array(
                'class' => array(),
                'style' => array(), // Allow inline styles
                'id' => array(),
            ),
            'tr' => array(
                'class' => array(),
                'style' => array(),
                'id' => array(),
            ),
            'td' => array(
                'class' => array(),
                'colspan' => array(), // Allow colspan attribute
                'rowspan' => array(), // Allow rowspan attribute
                'style' => array(),
                'id' => array(),
            ),
            'th' => array(
                'class' => array(),
                'colspan' => array(),
                'rowspan' => array(),
                'style' => array(),
                'id' => array(),
            ),
            'ul' => array(
                'class' => array(),
                'style' => array(),
                'id' => array(),
            ), // Allow unordered lists
            'ol' => array(
                'class' => array(),
                'style' => array(),
                'id' => array(),
            ), // Allow ordered lists
            'script' => array(
                'type' => array(),
                'src' => array(),
                'async' => array(),
                'defer' => array(),
                'charset' => array(),
            ), // Be cautious with scripts

            // Style and Meta Tags
            'style' => array(
                'type' => array(),
                'media' => array(),
                'scoped' => array(),
            ),
            'link' => array(
                'rel' => array(),
                'href' => array(),
                'type' => array(),
                'media' => array(),
                'sizes' => array(),
                'hreflang' => array(),
                'crossorigin' => array(),
            ),
            'meta' => array(
                'name' => array(),
                'content' => array(),
                'http-equiv' => array(),
                'charset' => array(),
                'property' => array(), // For Open Graph
            ),
            'title' => array(),
            'base' => array(
                'href' => array(),
                'target' => array(),
            ),

            // Document Structure
            'html' => array(
                'lang' => array(),
                'dir' => array(),
                'class' => array(),
                'style' => array(),
            ),
            'head' => array(),
            'body' => array(
                'class' => array(),
                'id' => array(),
                'style' => array(),
                'onload' => array(),
            ),
            'header' => array(
                'class' => array(),
                'id' => array(),
                'style' => array(),
                'role' => array(),
            ),
            'footer' => array(
                'class' => array(),
                'id' => array(),
                'style' => array(),
                'role' => array(),
            ),
            'nav' => array(
                'class' => array(),
                'style' => array(),
                'id' => array(),
                'role' => array(),
            ),
            'main' => array(
                'class' => array(),
                'style' => array(),
                'id' => array(),
                'role' => array(),
            ),
            'section' => array(
                'class' => array(),
                'id' => array(),
                'style' => array(),
                'role' => array(),
            ),
            'article' => array(
                'class' => array(),
                'style' => array(),
                'id' => array(),
                'role' => array(),
            ),
            'aside' => array(
                'class' => array(),
                'id' => array(),
                'style' => array(),
                'role' => array(),
            ),

            // Form Elements
            'form' => array(
                'action' => array(),
                'method' => array(),
                'style' => array(),
                'enctype' => array(),
                'target' => array(),
                'name' => array(),
                'id' => array(),
                'class' => array(),
                'autocomplete' => array(),
                'novalidate' => array(),
                'data-mobile-style' => array(),
                'data-product_show_settings' => array(),
                'data-product_selector' => array(),
                'data-pagination_selector' => array(),
                'data-layout' => array(),
            ),
            'input' => array(
                'type' => array(),
                'name' => array(),
                'value' => array(),
                'style' => array(),
                'placeholder' => array(),
                'id' => array(),
                'class' => array(),
                'required' => array(),
                'disabled' => array(),
                'readonly' => array(),
                'checked' => array(),
                'selected' => array(),
                'multiple' => array(),
                'min' => array(),
                'max' => array(),
                'step' => array(),
                'pattern' => array(),
                'maxlength' => array(),
                'minlength' => array(),
                'size' => array(),
                'autocomplete' => array(),
                'autofocus' => array(),
                'form' => array(),
                'formaction' => array(),
                'formmethod' => array(),
                'formtarget' => array(),
                'formnovalidate' => array(),
                'accept' => array(),
                'alt' => array(),
                'src' => array(),
                'width' => array(),
                'height' => array(),
            ),
            'textarea' => array(
                'name' => array(),
                'id' => array(),
                'class' => array(),
                'placeholder' => array(),
                'rows' => array(),
                'style' => array(),
                'cols' => array(),
                'required' => array(),
                'disabled' => array(),
                'readonly' => array(),
                'maxlength' => array(),
                'minlength' => array(),
                'wrap' => array(),
                'autocomplete' => array(),
                'autofocus' => array(),
                'form' => array(),
            ),
            'select' => array(
                'name' => array(),
                'id' => array(),
                'class' => array(),
                'multiple' => array(),
                'size' => array(),
                'required' => array(),
                'style' => array(),
                'disabled' => array(),
                'autofocus' => array(),
                'form' => array(),
            ),
            'option' => array(
                'value' => array(),
                'selected' => array(),
                'style' => array(),
                'disabled' => array(),
                'label' => array(),
            ),
            'optgroup' => array(
                'label' => array(),
                'style' => array(),
                'disabled' => array(),
            ),
            'button' => array(
                'type' => array(),
                'name' => array(),
                'value' => array(),
                'id' => array(),
                'style' => array(),
                'class' => array(),
                'disabled' => array(),
                'form' => array(),
                'formaction' => array(),
                'formmethod' => array(),
                'formtarget' => array(),
                'formnovalidate' => array(),
                'autofocus' => array(),
            ),
            'label' => array(
                'for' => array(),
                'form' => array(),
                'id' => array(),
                'class' => array(),
                'style' => array(),
            ),
            'fieldset' => array(
                'disabled' => array(),
                'form' => array(),
                'style' => array(),
                'name' => array(),
                'id' => array(),
                'class' => array(),
            ),
            'legend' => array(
                'id' => array(),
                'style' => array(),
                'class' => array(),
            ),
            'datalist' => array(
                'id' => array(),
                'style' => array(),
                'class' => array(),
            ),
            'output' => array(
                'for' => array(),
                'form' => array(),
                'name' => array(),
                'style' => array(),
                'id' => array(),
                'class' => array(),
            ),
            'plugrogress' => array(
                'value' => array(),
                'max' => array(),
                'style' => array(),
                'id' => array(),
                'class' => array(),
            ),
            'meter' => array(
                'value' => array(),
                'min' => array(),
                'max' => array(),
                'low' => array(),
                'style' => array(),
                'high' => array(),
                'optimum' => array(),
                'id' => array(),
                'class' => array(),
            ),

            // Media Elements
            'audio' => array(
                'src' => array(),
                'controls' => array(),
                'autoplay' => array(),
                'style' => array(),
                'loop' => array(),
                'muted' => array(),
                'preload' => array(),
                'crossorigin' => array(),
                'id' => array(),
                'class' => array(),
            ),
            'video' => array(
                'src' => array(),
                'controls' => array(),
                'autoplay' => array(),
                'loop' => array(),
                'muted' => array(),
                'preload' => array(),
                'style' => array(),
                'poster' => array(),
                'width' => array(),
                'height' => array(),
                'crossorigin' => array(),
                'id' => array(),
                'class' => array(),
            ),
            'source' => array(
                'src' => array(),
                'style' => array(),
                'type' => array(),
                'media' => array(),
                'sizes' => array(),
                'srcset' => array(),
            ),
            'track' => array(
                'kind' => array(),
                'src' => array(),
                'style' => array(),
                'srclang' => array(),
                'label' => array(),
                'default' => array(),
            ),
            'embed' => array(
                'src' => array(),
                'type' => array(),
                'width' => array(),
                'height' => array(),
                'style' => array(),
                'id' => array(),
                'class' => array(),
            ),
            'object' => array(
                'data' => array(),
                'type' => array(),
                'style' => array(),
                'name' => array(),
                'width' => array(),
                'height' => array(),
                'form' => array(),
                'id' => array(),
                'class' => array(),
            ),
            'param' => array(
                'name' => array(),
                'value' => array(),
                'style' => array(),
            ),
            'iframe' => array(
                'src' => array(),
                'srcdoc' => array(),
                'name' => array(),
                'width' => array(),
                'style' => array(),
                'height' => array(),
                'sandbox' => array(),
                'allow' => array(),
                'allowfullscreen' => array(),
                'loading' => array(),
                'id' => array(),
                'class' => array(),
            ),

            // Interactive Elements
            'details' => array(
                'open' => array(),
                'id' => array(),
                'class' => array(),
                'style' => array(),
            ),
            'summary' => array(
                'id' => array(),
                'style' => array(),
                'class' => array(),
            ),
            'dialog' => array(
                'open' => array(),
                'style' => array(),
                'id' => array(),
                'class' => array(),
            ),

            // Text Content Elements
            'pre' => array(
                'id' => array(),
                'style' => array(),
                'class' => array(),
            ),
            'code' => array(
                'id' => array(),
                'style' => array(),
                'class' => array(),
            ),
            'kbd' => array(
                'id' => array(),
                'style' => array(),
                'class' => array(),
            ),
            'samp' => array(
                'id' => array(),
                'style' => array(),
                'class' => array(),
            ),
            'var' => array(
                'id' => array(),
                'style' => array(),
                'class' => array(),
            ),
            'small' => array(
                'id' => array(),
                'style' => array(),
                'class' => array(),
            ),
            'sub' => array(
                'id' => array(),
                'style' => array(),
                'class' => array(),
            ),
            'sup' => array(
                'id' => array(),
                'style' => array(),
                'class' => array(),
            ),
            'mark' => array(
                'id' => array(),
                'style' => array(),
                'class' => array(),
            ),
            'del' => array(
                'datetime' => array(),
                'style' => array(),
                'cite' => array(),
                'id' => array(),
                'class' => array(),
            ),
            'ins' => array(
                'datetime' => array(),
                'style' => array(),
                'cite' => array(),
                'id' => array(),
                'class' => array(),
            ),
            'q' => array(
                'cite' => array(),
                'style' => array(),
                'id' => array(),
                'class' => array(),
            ),
            'cite' => array(
                'id' => array(),
                'style' => array(),
                'class' => array(),
            ),
            'abbr' => array(
                'title' => array(),
                'style' => array(),
                'id' => array(),
                'class' => array(),
            ),
            'dfn' => array(
                'title' => array(),
                'style' => array(),
                'id' => array(),
                'class' => array(),
            ),
            'time' => array(
                'datetime' => array(),
                'style' => array(),
                'id' => array(),
                'class' => array(),
            ),
            'data' => array(
                'value' => array(),
                'style' => array(),
                'id' => array(),
                'class' => array(),
            ),
            'address' => array(
                'id' => array(),
                'style' => array(),
                'class' => array(),
            ),

            // Table Elements (Enhanced)
            'caption' => array(
                'id' => array(),
                'style' => array(),
                'class' => array(),
            ),
            'thead' => array(
                'id' => array(),
                'style' => array(),
                'class' => array(),
            ),
            'tbody' => array(
                'id' => array(),
                'style' => array(),
                'class' => array(),
            ),
            'tfoot' => array(
                'id' => array(),
                'style' => array(),
                'class' => array(),
            ),
            'colgroup' => array(
                'span' => array(),
                'style' => array(),
                'id' => array(),
                'class' => array(),
            ),
            'col' => array(
                'span' => array(),
                'style' => array(),
                'id' => array(),
                'class' => array(),
            ),

            // Definition Lists
            'dl' => array(
                'id' => array(),
                'style' => array(),
                'class' => array(),
            ),
            'dt' => array(
                'id' => array(),
                'style' => array(),
                'class' => array(),
            ),
            'dd' => array(
                'id' => array(),
                'style' => array(),
                'class' => array(),
            ),

            // Ruby Annotations
            'ruby' => array(
                'id' => array(),
                'style' => array(),
                'class' => array(),
            ),
            'rt' => array(
                'id' => array(),
                'style' => array(),
                'class' => array(),
            ),
            'rp' => array(
                'id' => array(),
                'style' => array(),
                'class' => array(),
            ),

            // Bidirectional Text
            'bdi' => array(
                'dir' => array(),
                'id' => array(),
                'style' => array(),
                'class' => array(),
            ),
            'bdo' => array(
                'dir' => array(),
                'id' => array(),
                'style' => array(),
                'class' => array(),
            ),

            // Web Components
            'template' => array(
                'id' => array(),
                'style' => array(),
                'class' => array(),
            ),
            'slot' => array(
                'name' => array(),
                'id' => array(),
                'style' => array(),
                'class' => array(),
            ),

            // Math and Science
            'math' => array(
                'display' => array(),
                'xmlns' => array(),
                'id' => array(),
                'style' => array(),
                'class' => array(),
            ),

            // Canvas and Graphics
            'canvas' => array(
                'width' => array(),
                'height' => array(),
                'id' => array(),
                'style' => array(),
                'class' => array(),
            ),

            // Obsolete but sometimes needed
            'center' => array(
                'id' => array(),
                'style' => array(),
                'class' => array(),
            ),
            'font' => array(
                'size' => array(),
                'style' => array(),
                'color' => array(),
                'face' => array(),
                'id' => array(),
                'class' => array(),
            ),

            // SVG Tags
            'svg' => array(
                'xmlns' => array(),
                'viewbox' => array(), // lowercase
                'viewBox' => array(), // camelCase (standard)
                'width' => array(),
                'height' => array(),
                'class' => array(),
                'id' => array(),
                'style' => array(),
                'preserveAspectRatio' => array(),
                'version' => array(),
                'x' => array(),
                'y' => array(),
                'fill' => array(),
            ),
            'g' => array(
                'class' => array(),
                'id' => array(),
                'transform' => array(),
                'style' => array(),
                'fill' => array(),
                'stroke' => array(),
                'opacity' => array(),
            ),
            'path' => array(
                'd' => array(),
                'class' => array(),
                'id' => array(),
                'fill' => array(),
                'stroke' => array(),
                'stroke-width' => array(),
                'stroke-dasharray' => array(),
                'stroke-linecap' => array(),
                'stroke-linejoin' => array(),
                'opacity' => array(),
                'transform' => array(),
                'style' => array(),
            ),
            'circle' => array(
                'cx' => array(),
                'cy' => array(),
                'r' => array(),
                'class' => array(),
                'id' => array(),
                'fill' => array(),
                'stroke' => array(),
                'stroke-width' => array(),
                'opacity' => array(),
                'transform' => array(),
                'style' => array(),
            ),
            'ellipse' => array(
                'cx' => array(),
                'cy' => array(),
                'rx' => array(),
                'ry' => array(),
                'class' => array(),
                'id' => array(),
                'fill' => array(),
                'stroke' => array(),
                'stroke-width' => array(),
                'opacity' => array(),
                'transform' => array(),
                'style' => array(),
            ),
            'rect' => array(
                'x' => array(),
                'y' => array(),
                'width' => array(),
                'height' => array(),
                'rx' => array(),
                'ry' => array(),
                'class' => array(),
                'id' => array(),
                'fill' => array(),
                'stroke' => array(),
                'stroke-width' => array(),
                'opacity' => array(),
                'transform' => array(),
                'style' => array(),
            ),
            'line' => array(
                'x1' => array(),
                'y1' => array(),
                'x2' => array(),
                'y2' => array(),
                'class' => array(),
                'id' => array(),
                'stroke' => array(),
                'stroke-width' => array(),
                'stroke-dasharray' => array(),
                'stroke-linecap' => array(),
                'opacity' => array(),
                'transform' => array(),
                'style' => array(),
            ),
            'polyline' => array(
                'points' => array(),
                'class' => array(),
                'id' => array(),
                'fill' => array(),
                'stroke' => array(),
                'stroke-width' => array(),
                'stroke-dasharray' => array(),
                'stroke-linecap' => array(),
                'stroke-linejoin' => array(),
                'opacity' => array(),
                'transform' => array(),
                'style' => array(),
            ),
            'polygon' => array(
                'points' => array(),
                'class' => array(),
                'id' => array(),
                'fill' => array(),
                'stroke' => array(),
                'stroke-width' => array(),
                'stroke-dasharray' => array(),
                'stroke-linecap' => array(),
                'stroke-linejoin' => array(),
                'opacity' => array(),
                'transform' => array(),
                'style' => array(),
            ),
            'text' => array(
                'x' => array(),
                'y' => array(),
                'dx' => array(),
                'dy' => array(),
                'class' => array(),
                'id' => array(),
                'fill' => array(),
                'stroke' => array(),
                'font-family' => array(),
                'font-size' => array(),
                'font-weight' => array(),
                'text-anchor' => array(),
                'dominant-baseline' => array(),
                'opacity' => array(),
                'transform' => array(),
                'style' => array(),
            ),
            'tspan' => array(
                'x' => array(),
                'y' => array(),
                'dx' => array(),
                'dy' => array(),
                'class' => array(),
                'id' => array(),
                'fill' => array(),
                'stroke' => array(),
                'font-family' => array(),
                'font-size' => array(),
                'font-weight' => array(),
                'text-anchor' => array(),
                'dominant-baseline' => array(),
                'opacity' => array(),
                'style' => array(),
            ),
            'use' => array(
                'href' => array(),
                'xlink:href' => array(),
                'x' => array(),
                'y' => array(),
                'width' => array(),
                'height' => array(),
                'class' => array(),
                'id' => array(),
                'transform' => array(),
                'style' => array(),
            ),
            'defs' => array(
                'class' => array(),
                'id' => array(),
                'style' => array(),
            ),
            'symbol' => array(
                'id' => array(),
                'viewBox' => array(),
                'class' => array(),
                'style' => array(),
                'preserveAspectRatio' => array(),
            ),
            'marker' => array(
                'id' => array(),
                'markerWidth' => array(),
                'markerHeight' => array(),
                'refX' => array(),
                'refY' => array(),
                'style' => array(),
                'orient' => array(),
                'markerUnits' => array(),
                'class' => array(),
            ),
            'linearGradient' => array(
                'id' => array(),
                'x1' => array(),
                'y1' => array(),
                'style' => array(),
                'x2' => array(),
                'y2' => array(),
                'gradientUnits' => array(),
                'gradientTransform' => array(),
                'class' => array(),
            ),
            'lineargradient' => array(
                'id' => array(),
                'x1' => array(),
                'y1' => array(),
                'style' => array(),
                'x2' => array(),
                'y2' => array(),
                'gradientUnits' => array(),
                'gradientTransform' => array(),
                'class' => array(),
            ),
            'radialGradient' => array(
                'id' => array(),
                'cx' => array(),
                'cy' => array(),
                'style' => array(),
                'r' => array(),
                'fx' => array(),
                'fy' => array(),
                'gradientUnits' => array(),
                'gradientTransform' => array(),
                'class' => array(),
            ),
            'radialgradient' => array(
                'id' => array(),
                'cx' => array(),
                'cy' => array(),
                'r' => array(),
                'style' => array(),
                'fx' => array(),
                'fy' => array(),
                'gradientUnits' => array(),
                'gradientTransform' => array(),
                'class' => array(),
            ),
            'stop' => array(
                'offset' => array(),
                'stop-color' => array(),
                'stop-opacity' => array(),
                'class' => array(),
                'style' => array(),
            ),
            'clipPath' => array(
                'id' => array(),
                'class' => array(),
                'style' => array(),
                'clipPathUnits' => array(),
            ),
            'mask' => array(
                'id' => array(),
                'class' => array(),
                'style' => array(),
                'maskUnits' => array(),
                'maskContentUnits' => array(),
                'x' => array(),
                'y' => array(),
                'width' => array(),
                'height' => array(),
            ),
            'pattern' => array(
                'id' => array(),
                'x' => array(),
                'y' => array(),
                'width' => array(),
                'style' => array(),
                'height' => array(),
                'patternUnits' => array(),
                'patternContentUnits' => array(),
                'patternTransform' => array(),
                'viewBox' => array(),
                'class' => array(),
            ),
            'filter' => array(
                'id' => array(),
                'x' => array(),
                'y' => array(),
                'style' => array(),
                'width' => array(),
                'height' => array(),
                'filterUnits' => array(),
                'primitiveUnits' => array(),
                'class' => array(),
            ),
            'feGaussianBlur' => array(
                'in' => array(),
                'style' => array(),
                'stdDeviation' => array(),
                'result' => array(),
            ),
            'feOffset' => array(
                'in' => array(),
                'dx' => array(),
                'style' => array(),
                'dy' => array(),
                'result' => array(),
            ),
            'feDropShadow' => array(
                'dx' => array(),
                'dy' => array(),
                'style' => array(),
                'stdDeviation' => array(),
                'flood-color' => array(),
                'flood-opacity' => array(),
            ),
            'image' => array(
                'x' => array(),
                'y' => array(),
                'width' => array(),
                'style' => array(),
                'height' => array(),
                'href' => array(),
                'xlink:href' => array(),
                'preserveAspectRatio' => array(),
                'class' => array(),
                'id' => array(),
                'opacity' => array(),
                'transform' => array(),
            ),
        );
    }

    add_action('init', 'mulopimfwc_get_values', 11);

    require_once plugin_dir_path(__FILE__) . 'admin/settings.php';
    require_once plugin_dir_path(__FILE__) . 'admin/dashboard.php';
    require_once plugin_dir_path(__FILE__) . 'admin/license-page.php';
    require_once plugin_dir_path(__FILE__) . 'admin/stock-central.php';
    require_once plugin_dir_path(__FILE__) . 'admin/admin.php';
    require_once plugin_dir_path(__FILE__) . 'includes/product-display.php';
    require_once plugin_dir_path(__FILE__) . 'admin/location-based-everythings.php';
    require_once plugin_dir_path(__FILE__) . 'admin/location-managers.php';
    require_once plugin_dir_path(__FILE__) . 'includes/product-location-selector-single.php';
    require_once plugin_dir_path(__FILE__) . 'admin/import-export-settings.php';
    require_once plugin_dir_path(__FILE__) . 'admin/api-settings.php';
    require_once plugin_dir_path(__FILE__) . 'includes/location-based-shipping.php';
    require_once plugin_dir_path(__FILE__) . 'includes/location-wise-shipping-payment-tax.php';
    require_once plugin_dir_path(__FILE__) . 'includes/location-wise-coupons.php';
    require_once plugin_dir_path(__FILE__) . 'includes/location-wise-reviews.php';
    require_once plugin_dir_path(__FILE__) . 'includes/location-wise-seo.php';
    require_once plugin_dir_path(__FILE__) . 'includes/location-wise-email.php';
    require_once plugin_dir_path(__FILE__) . 'includes/frontend-location-information.php';
    require_once plugin_dir_path(__FILE__) . 'includes/location-wise-local-pickup.php';
    require_once plugin_dir_path(__FILE__) . 'includes/location-hours-restriction.php';
    require_once plugin_dir_path(__FILE__) . 'includes/customer-location-insights.php';
    require_once plugin_dir_path(__FILE__) . 'includes/frontend-product-filter.php';
    require_once plugin_dir_path(__FILE__) . 'includes/cash-on-pickup-payment-gateway.php';
    require_once plugin_dir_path(__FILE__) . 'includes/api/inventory-sync-api.php';

    class mulopimfwc_Location_Wise_Products
    {

        private $cart_items_cache = null;
        private $should_group_cache = null;

        public function __construct()
        {
            add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
            add_action('pre_get_posts', [$this, 'filter_products_by_location']);
            add_filter('woocommerce_shortcode_products_query', [$this, 'filter_shortcode_products']);
            add_filter('woocommerce_products_widget_query_args', [$this, 'filter_widget_products']);
            add_filter('woocommerce_related_products_args', [$this, 'filter_related_products']);
            $options = get_option('mulopimfwc_display_options', ['enable_popup' => 'off']);
            if (isset($options['enable_popup']) && $options['enable_popup'] === 'on' && mulopimfwc_premium_feature()) {
                add_action('wp_footer', [$this, 'location_selector_modal']);
            }
            add_action('init', [$this, 'clear_cart_on_location_change']);

            add_shortcode('mulopimfwc_store_location_selector', [$this, 'location_selector_shortcode']);

            add_filter('the_title', [$this, 'add_location_to_product_title'], 10, 2);
            add_filter('woocommerce_product_title', [$this, 'add_location_to_wc_product_title'], 10, 2);
            global $MULOPIMFWC_Admin;
            $MULOPIMFWC_Admin = new MULOPIMFWC_Admin();
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
            add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_location_selection_before_add_to_cart'], 10, 5);

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
            add_action('woocommerce_checkout_order_processed', [$this, 'handle_social_new_order_notification'], 20, 1);

            // Use these specific hooks for HPOS orders table
            add_action('woocommerce_order_list_table_restrict_manage_orders', array($this, 'add_store_location_filter'));
            add_filter('woocommerce_order_query_args', array($this, 'filter_orders_by_location'));
            add_action('woocommerce_order_status_failed', 'mulopimfwc_social_order_failed', 20, 1);
            add_action('woocommerce_order_status_refunded', 'mulopimfwc_social_order_refunded', 20, 1);
            add_action('woocommerce_order_status_cancelled', 'mulopimfwc_social_order_cancelled', 20, 1);
            add_action('woocommerce_order_status_completed', 'mulopimfwc_social_order_completed', 20, 1);

            require_once plugin_dir_path(__FILE__) . 'includes/stock-price-backorder-manage.php';

            add_action('wp_ajax_update_product_location_status', [$this, 'cymulopimfwc_update_product_location_status']);
            add_action('wp_ajax_get_available_locations', [$this, 'cymulopimfwc_get_available_locations']);
            add_action('wp_ajax_save_product_locations', [$this, 'cymulopimfwc_save_product_locations']);
            add_action('wp_ajax_get_product_quick_edit_data', [$this, 'cymulopimfwc_get_product_quick_edit_data']);
            add_action('wp_ajax_save_product_quick_edit_data', [$this, 'cymulopimfwc_save_product_quick_edit_data']);
            add_action('wp_ajax_remove_product_location', [$this, 'cymulopimfwc_remove_product_location']);

            add_action('admin_enqueue_scripts', [$this, 'cymulopimfwc_enqueue_admin_scripts']);

            // Mixed location cart tracking
            add_filter('woocommerce_add_cart_item_data', [$this, 'add_location_to_cart_item'], 10, 3);
            add_filter('woocommerce_get_item_data', [$this, 'display_location_in_cart'], 10, 2);
            add_action('woocommerce_checkout_create_order_line_item', [$this, 'save_location_to_order_item'], 10, 4);

            // Allow location change in cart
            add_action('woocommerce_after_cart_item_name', [$this, 'display_cart_location_selector'], 10, 2);
            add_action('wp_ajax_update_cart_item_location', [$this, 'update_cart_item_location']);
            add_action('wp_ajax_nopriv_update_cart_item_location', [$this, 'update_cart_item_location']);
            add_action('wp_ajax_get_cart_item_locations', [$this, 'get_cart_item_locations']);
            add_action('wp_ajax_nopriv_get_cart_item_locations', [$this, 'get_cart_item_locations']);
            add_action('woocommerce_before_order_itemmeta', [$this, 'display_location_in_order_items'], 10, 3);
            add_action('woocommerce_check_cart_items', [$this, 'validate_mixed_cart_locations']);
            add_action('woocommerce_before_cart_table', [$this, 'display_cart_location_summary']);

            // Cart grouping by location
            add_action('woocommerce_before_cart_table', [$this, 'maybe_override_cart_display'], 5);
            add_action('woocommerce_after_cart_table', [$this, 'maybe_restore_cart_display'], 5);

            // Only add checkout hooks if on checkout page
            add_action('woocommerce_review_order_before_cart_contents', [$this, 'maybe_override_checkout_cart_display'], 5);
            add_action('woocommerce_review_order_after_cart_contents', [$this, 'maybe_restore_checkout_cart_display'], 5);


            // Clear cache when cart changes
            add_action('woocommerce_cart_item_removed', [$this, 'clear_cart_cache']);
            add_action('woocommerce_cart_item_restored', [$this, 'clear_cart_cache']);
            add_action('woocommerce_after_cart_item_quantity_update', [$this, 'clear_cart_cache']);
            add_action('woocommerce_add_to_cart', [$this, 'clear_cart_cache']);

            // inter_location_transfer_costs

            add_action('woocommerce_cart_calculate_fees', [$this, 'add_inter_location_transfer_fees']);
            add_filter('woocommerce_cart_shipping_packages', [$this, 'split_shipping_packages_by_location']);

            // display the notice for transfer cost breakdown
            add_action('woocommerce_before_cart_totals', [$this, 'display_transfer_cost_breakdown']);
            add_action('woocommerce_review_order_before_order_total', [$this, 'display_transfer_cost_breakdown']);
            // Save transfer cost details to order meta
            add_action('woocommerce_checkout_order_processed', [$this, 'save_transfer_costs_to_order'], 10, 1);
            // Display transfer costs in admin order details
            add_action('woocommerce_admin_order_data_after_order_details', [$this, 'display_transfer_costs_in_order'], 10, 1);
            add_filter( 'plugin_row_meta', array( __CLASS__, 'plugin_row_meta' ), 10, 2 );
        }

        /**
         * Clear cart items cache
         */
        public function clear_cart_cache()
        {
            $this->cart_items_cache = null;
        }

        /**
         * Prevent adding location-bound products to the cart when no location is selected.
         *
         * @param bool $passed Whether WooCommerce validations passed.
         * @param int $product_id Product ID being added.
         * @param int $quantity Quantity requested.
         * @param int $variation_id Variation ID if applicable.
         * @param array $variations Variation attributes (unused).
         * @return bool
         */
        public function validate_location_selection_before_add_to_cart($passed, $product_id, $quantity, $variation_id = 0, $variations = [])
        {
            if (!$passed) {
                return $passed;
            }

            if (is_admin() && !wp_doing_ajax()) {
                return $passed;
            }

            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);

            $require_selection = isset($options['location_require_selection']) ? $options['location_require_selection'] : 'off';

            if ($require_selection !== 'on') {
                return $passed;
            }

            $primary_product = $variation_id ? $variation_id : $product_id;

            if (!$this->product_has_assigned_locations($primary_product, $product_id)) {
                return $passed;
            }

            $selected_location = isset($_COOKIE['mulopimfwc_store_location']) ? sanitize_text_field(wp_unslash($_COOKIE['mulopimfwc_store_location'])) : '';

            if ($selected_location === '' || $selected_location === 'all-products') {
                if (function_exists('wc_add_notice')) {
                    wc_add_notice(__('Please select a store location before adding this product to your cart.', 'multi-location-product-and-inventory-management'), 'error');
                }
                return false;
            }

            return $passed;
        }

        /**
         * Determine if a product (or its parent) is assigned to any store location.
         *
         * @param int $product_id Primary product/variation ID to inspect.
         * @param int $fallback_product_id Optional fallback (usually parent product).
         * @return bool
         */
        private function product_has_assigned_locations($product_id, $fallback_product_id = 0)
        {
            $product_ids = array_unique(array_filter(array_map('absint', [$product_id, $fallback_product_id])));

            foreach ($product_ids as $id) {
                $terms = wp_get_object_terms($id, 'mulopimfwc_store_location', ['fields' => 'ids']);

                if (!is_wp_error($terms) && !empty($terms)) {
                    return true;
                }
            }

            return false;
        }

        /**
         * Display transfer costs in order details (admin)
         */
        public function display_transfer_costs_in_order($order)
        {
            $transfer_costs = $order->get_meta('_inter_location_transfer_costs');

            if (empty($transfer_costs) || !is_array($transfer_costs)) {
                return;
            }

            $destination = $order->get_meta('_destination_location');

?>
            <div class="mulopimfwc-order-transfer-costs" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-left: 4px solid #3b82f6; border-radius: 4px;">
                <h4 style="margin-top: 0; color: #1e40af;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 5px;">
                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" fill="currentColor" />
                    </svg>
                    <?php esc_html_e('Inter-Location Transfer Costs', 'multi-location-product-and-inventory-management'); ?>
                </h4>

                <?php if (!empty($destination)): ?>
                    <p style="margin: 5px 0; color: #64748b; font-size: 13px;">
                        <?php echo sprintf(esc_html__('Destination: %s', 'multi-location-product-and-inventory-management'), '<strong>' . esc_html($destination) . '</strong>'); ?>
                    </p>
                <?php endif; ?>

                <table class="widefat" style="margin-top: 10px; border: 1px solid #e2e8f0;">
                    <thead>
                        <tr>
                            <th style="padding: 8px; background: #f1f5f9; border-bottom: 1px solid #e2e8f0;"><?php esc_html_e('From Location', 'multi-location-product-and-inventory-management'); ?></th>
                            <th style="padding: 8px; background: #f1f5f9; border-bottom: 1px solid #e2e8f0;"><?php esc_html_e('To Location', 'multi-location-product-and-inventory-management'); ?></th>
                            <th style="padding: 8px; background: #f1f5f9; border-bottom: 1px solid #e2e8f0; text-align: right;"><?php esc_html_e('Cost', 'multi-location-product-and-inventory-management'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $total = 0;
                        foreach ($transfer_costs as $transfer):
                            $total += $transfer['cost'];
                        ?>
                            <tr>
                                <td style="padding: 8px; border-bottom: 1px solid #e2e8f0;"><?php echo esc_html($transfer['from']); ?></td>
                                <td style="padding: 8px; border-bottom: 1px solid #e2e8f0;"><?php echo esc_html($transfer['to']); ?></td>
                                <td style="padding: 8px; border-bottom: 1px solid #e2e8f0; text-align: right;"><?php echo wc_price($transfer['cost']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td colspan="2" style="padding: 8px; font-weight: bold; background: #f8fafc;"><?php esc_html_e('Total Transfer Cost', 'multi-location-product-and-inventory-management'); ?></td>
                            <td style="padding: 8px; font-weight: bold; text-align: right; background: #f8fafc;"><?php echo wc_price($total); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php
        }

        /**
         * Save transfer cost information to order meta
         */
        public function save_transfer_costs_to_order($order_id)
        {
            global $mulopimfwc_options;

            // Check if mixed location cart is enabled
            $allow_mixed = isset($mulopimfwc_options['allow_mixed_location_cart']) && mulopimfwc_premium_feature()
                ? $mulopimfwc_options['allow_mixed_location_cart']
                : 'off';

            if ($allow_mixed !== 'on') {
                return;
            }

            $order = wc_get_order($order_id);
            if (!$order) {
                return;
            }

            // Get user's selected location
            $selected_location = $this->get_current_location();

            if (empty($selected_location) || $selected_location === 'all-products') {
                return;
            }

            // Get transfer costs settings
            $options = get_option('mulopimfwc_display_options', []);
            $transfer_costs = isset($options['inter_location_transfer_costs']) && mulopimfwc_premium_feature()
                ? $options['inter_location_transfer_costs']
                : [];

            if (empty($transfer_costs)) {
                return;
            }

            // Get selected location term
            $destination_term = get_term_by('slug', $selected_location, 'mulopimfwc_store_location');
            if (!$destination_term || is_wp_error($destination_term)) {
                return;
            }

            $destination_slug = $destination_term->slug;
            $transfer_details = [];

            // Get cart items grouped by location
            $items_by_location = $this->get_cart_items_by_location();

            // Collect transfer cost details
            foreach ($items_by_location as $location_data) {
                $source_slug = $location_data['location_slug'];

                if ($source_slug === $destination_slug || $source_slug === 'unknown') {
                    continue;
                }

                $cost_key = $source_slug . '_to_' . $destination_slug;

                if (isset($transfer_costs[$cost_key]) && !empty($transfer_costs[$cost_key])) {
                    $cost = floatval($transfer_costs[$cost_key]);

                    if ($cost > 0) {
                        $transfer_details[] = [
                            'from' => $location_data['location_name'],
                            'to' => $destination_term->name,
                            'cost' => $cost
                        ];
                    }
                }
            }

            // Save transfer details to order meta
            if (!empty($transfer_details)) {
                $order->update_meta_data('_inter_location_transfer_costs', $transfer_details);
                $order->update_meta_data('_destination_location', $destination_term->name);
                $order->save();
            }
        }


        /**
         * Add inter-location transfer fees to cart
         */
        public function add_inter_location_transfer_fees()
        {
            global $mulopimfwc_options;

            // Check if mixed location cart is enabled
            $allow_mixed = isset($mulopimfwc_options['allow_mixed_location_cart']) && mulopimfwc_premium_feature()
                ? $mulopimfwc_options['allow_mixed_location_cart']
                : 'off';

            if ($allow_mixed !== 'on') {
                return;
            }

            // enabled per location shipping methods
            $enabled_per_location_shipping = isset($mulopimfwc_options['shipping_calculation_method']) && mulopimfwc_premium_feature()
                ? $mulopimfwc_options['shipping_calculation_method']
                : 'per_location';

            if ($enabled_per_location_shipping === 'per_location') {
                return;
            }

            // Get user's selected location (destination)
            $selected_location = $this->get_current_location();

            if (empty($selected_location) || $selected_location === 'all-products') {
                return;
            }

            // Get transfer costs settings
            $options = get_option('mulopimfwc_display_options', []);
            $transfer_costs = isset($options['inter_location_transfer_costs']) && mulopimfwc_premium_feature()
                ? $options['inter_location_transfer_costs']
                : [];

            if (empty($transfer_costs)) {
                return;
            }

            // Get selected location term
            $destination_term = get_term_by('slug', $selected_location, 'mulopimfwc_store_location');
            if (!$destination_term || is_wp_error($destination_term)) {
                return;
            }

            $destination_slug = $destination_term->slug;
            $total_transfer_cost = 0;
            $transfer_details = [];

            // Get cart items grouped by location
            $items_by_location = $this->get_cart_items_by_location();

            // Calculate transfer costs for each source location
            foreach ($items_by_location as $location_data) {
                $source_slug = $location_data['location_slug'];

                // Skip if it's the same location or unknown
                if ($source_slug === $destination_slug || $source_slug === 'unknown') {
                    continue;
                }

                // Build the cost key: source_to_destination
                $cost_key = $source_slug . '_to_' . $destination_slug;

                // Check if transfer cost exists for this route
                if (isset($transfer_costs[$cost_key]) && !empty($transfer_costs[$cost_key])) {
                    $cost = floatval($transfer_costs[$cost_key]);

                    if ($cost > 0) {
                        $total_transfer_cost += $cost;
                        $transfer_details[] = sprintf(
                            __('%s to %s: %s', 'multi-location-product-and-inventory-management'),
                            $location_data['location_name'],
                            $destination_term->name,
                            $cost
                        );
                    }
                }
            }

            // Add transfer cost as a fee if greater than zero
            if ($total_transfer_cost > 0) {
                $fee_label = __('Inter-location Transfer', 'multi-location-product-and-inventory-management');

                // Add detailed breakdown if multiple transfers
                if (count($transfer_details) > 1) {
                    $fee_label .= ' (' . implode(', ', $transfer_details) . ')';
                } elseif (count($transfer_details) === 1) {
                    $fee_label .= ' (' . $transfer_details[0] . ')';
                }

                WC()->cart->add_fee($fee_label, $total_transfer_cost, true);
            }
        }

        /**
         * Split shipping packages by location for accurate shipping calculation
         */
        public function split_shipping_packages_by_location($packages)
        {
            global $mulopimfwc_options;

            // Check if mixed location cart is enabled
            $allow_mixed = isset($mulopimfwc_options['allow_mixed_location_cart']) && mulopimfwc_premium_feature()
                ? $mulopimfwc_options['allow_mixed_location_cart']
                : 'off';

            if ($allow_mixed !== 'on') {
                return $packages;
            }

            // Check if location-based shipping is enabled
            $enable_location_shipping = isset($mulopimfwc_options['enable_location_shipping']) && mulopimfwc_premium_feature()
                ? $mulopimfwc_options['enable_location_shipping']
                : 'off';

            if ($enable_location_shipping !== 'on') {
                return $packages;
            }

            // enabled per location shipping methods
            $enabled_per_location_shipping = isset($mulopimfwc_options['shipping_calculation_method']) && mulopimfwc_premium_feature()
                ? $mulopimfwc_options['shipping_calculation_method']
                : 'per_location';

            if ($enabled_per_location_shipping !== 'per_location') {
                return $packages;
            }

            // Get cart items grouped by location
            $items_by_location = $this->get_cart_items_by_location();

            if (count($items_by_location) <= 1) {
                return $packages;
            }

            // Create separate shipping packages for each location
            $new_packages = [];
            $package_index = 1;

            foreach ($items_by_location as $location_data) {
                $location_slug = $location_data['location_slug'];
                $location_name = $location_data['location_name'];

                // Create package for this location
                $package_contents = [];
                $package_contents_cost = 0;

                foreach ($location_data['items'] as $cart_item) {
                    $package_contents[$cart_item['cart_item_key']] = $cart_item;
                    $package_contents_cost += $cart_item['line_total'];
                }

                $new_packages[] = [
                    'package_key' => 'location_' . $location_slug . '_' . $package_index,
                    'contents' => $package_contents,
                    'contents_cost' => $package_contents_cost,
                    'applied_coupons' => WC()->cart->get_applied_coupons(),
                    'user' => [
                        'ID' => get_current_user_id()
                    ],
                    'destination' => [
                        'country' => WC()->customer->get_shipping_country(),
                        'state' => WC()->customer->get_shipping_state(),
                        'postcode' => WC()->customer->get_shipping_postcode(),
                        'city' => WC()->customer->get_shipping_city(),
                        'address' => WC()->customer->get_shipping_address(),
                        'address_2' => WC()->customer->get_shipping_address_2()
                    ],
                    'location_slug' => $location_slug,
                    'location_name' => $location_name,
                    'package_name' => sprintf(
                        __('Shipping from %s', 'multi-location-product-and-inventory-management'),
                        $location_name
                    )
                ];

                $package_index++;
            }

            return !empty($new_packages) ? $new_packages : $packages;
        }

        /**
         * Display transfer cost breakdown in cart totals
         */
        public function display_transfer_cost_breakdown()
        {
            global $mulopimfwc_options;

            // Check if mixed location cart is enabled
            $allow_mixed = isset($mulopimfwc_options['allow_mixed_location_cart']) && mulopimfwc_premium_feature()
                ? $mulopimfwc_options['allow_mixed_location_cart']
                : 'off';

            if ($allow_mixed !== 'on') {
                return;
            }

            // Get user's selected location
            $selected_location = $this->get_current_location();

            if (empty($selected_location) || $selected_location === 'all-products') {
                return;
            }

            // Get transfer costs settings
            $options = get_option('mulopimfwc_display_options', []);
            $transfer_costs = isset($options['inter_location_transfer_costs']) && mulopimfwc_premium_feature()
                ? $options['inter_location_transfer_costs']
                : [];

            if (empty($transfer_costs)) {
                return;
            }

            // Get selected location term
            $destination_term = get_term_by('slug', $selected_location, 'mulopimfwc_store_location');
            if (!$destination_term || is_wp_error($destination_term)) {
                return;
            }

            $destination_slug = $destination_term->slug;
            $has_transfers = false;

            // Get cart items grouped by location
            $items_by_location = $this->get_cart_items_by_location();

            // Check if there are any transfer costs
            foreach ($items_by_location as $location_data) {
                $source_slug = $location_data['location_slug'];

                if ($source_slug === $destination_slug || $source_slug === 'unknown') {
                    continue;
                }

                $cost_key = $source_slug . '_to_' . $destination_slug;

                if (isset($transfer_costs[$cost_key]) && floatval($transfer_costs[$cost_key]) > 0) {
                    $has_transfers = true;
                    break;
                }
            }

            if (!$has_transfers) {
                return;
            }

        ?>
            <div class="mulopimfwc-transfer-cost-notice woocommerce-info">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M13 7h-2v4H7v2h4v4h2v-4h4v-2h-4z" fill="currentColor" />
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z" fill="currentColor" />
                    </svg>
                    <div>
                        <strong><?php esc_html_e('Transfer Costs Applied', 'multi-location-product-and-inventory-management'); ?></strong>
                        <p style="margin: 5px 0 0 0; font-size: 13px;">
                            <?php
                            echo sprintf(
                                esc_html__('Your order includes products from multiple locations. Transfer costs to %s have been added to your total.', 'multi-location-product-and-inventory-management'),
                                '<strong>' . esc_html($destination_term->name) . '</strong>'
                            );
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php
        }

        /**
         * Add location data to cart item when product is added
         */

        public function add_location_to_cart_item($cart_item_data, $product_id, $variation_id)
        {
            // Always add location data to cart items for proper price and stock management
            // Get current selected location
            $location = $this->get_current_location();

            if (!empty($location) && $location !== 'all-products') {
                // Add location to cart item data
                $cart_item_data['mulopimfwc_location'] = $location;

                // Get location term for display name
                $location_term = get_term_by('slug', $location, 'mulopimfwc_store_location');
                if ($location_term && !is_wp_error($location_term)) {
                    $cart_item_data['mulopimfwc_location_name'] = $location_term->name;
                }
            }

            return $cart_item_data;
        }

        /**
         * Display location information in cart
         */

        public function display_location_in_cart($item_data, $cart_item)
        {
            global $mulopimfwc_options;

            // Check if cart grouping is enabled
            $group_cart = isset($mulopimfwc_options['group_cart_by_location']) && mulopimfwc_premium_feature()
                ? $mulopimfwc_options['group_cart_by_location']
                : 'off';

            // Always show location information when available
            // Check if location data exists
            if (isset($cart_item['mulopimfwc_location_name']) && ($group_cart !== 'on' || is_cart())) {
                $item_data[] = array(
                    'key'     => __('Location', 'multi-location-product-and-inventory-management'),
                    'value'   => esc_html($cart_item['mulopimfwc_location_name']),
                    'display' => '',
                );
            }

            return $item_data;
        }

        /**
         * Save location data to order item meta
         */

        public function save_location_to_order_item($item, $cart_item_key, $values, $order)
        {
            global $mulopimfwc_options;

            // Check if mixed location cart is enabled
            $allow_mixed = isset($mulopimfwc_options['allow_mixed_location_cart']) && mulopimfwc_premium_feature()
                ? $mulopimfwc_options['allow_mixed_location_cart']
                : 'off';

            // Always save location data if available, regardless of mixed cart setting
            // This ensures proper stock management and price tracking
            if (isset($values['mulopimfwc_location'])) {
                $item->add_meta_data('_mulopimfwc_location', $values['mulopimfwc_location'], true);
            }

            if (isset($values['mulopimfwc_location_name'])) {
                $item->add_meta_data(
                    __('Store Location', 'multi-location-product-and-inventory-management'),
                    $values['mulopimfwc_location_name'],
                    true
                );
            }
        }

        /**
         * Display location in order items (admin)
         */

        public function display_location_in_order_items($item_id, $item, $product)
        {
            // Always show location information when available
            // Get location from order item meta
            $location = $item->get_meta('_mulopimfwc_location');

            if (!empty($location)) {
                $location_term = get_term_by('slug', $location, 'mulopimfwc_store_location');
                if ($location_term && !is_wp_error($location_term)) {
                    echo '<div class="mulopimfwc-order-item-location">';
                    echo '<strong>' . esc_html__('Location:', 'multi-location-product-and-inventory-management') . '</strong> ';
                    echo esc_html($location_term->name);
                    echo '</div>';
                }
            }
        }

        /**
         * Validate cart items when location changes (with mixed cart enabled)
         */

        public function validate_mixed_cart_locations()
        {
            global $mulopimfwc_options;

            // Check if mixed location cart is enabled
            $allow_mixed = isset($mulopimfwc_options['allow_mixed_location_cart']) && mulopimfwc_premium_feature()
                ? $mulopimfwc_options['allow_mixed_location_cart']
                : 'off';

            if ($allow_mixed !== 'on') {
                return;
            }

            // Validate each cart item still belongs to its stored location
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                if (isset($cart_item['mulopimfwc_location'])) {
                    $product_id = $cart_item['product_id'];
                    $stored_location = $cart_item['mulopimfwc_location'];

                    // Check if product still belongs to stored location
                    $terms = wp_get_object_terms($product_id, 'mulopimfwc_store_location', ['fields' => 'slugs']);

                    if (!empty($terms) && !in_array($stored_location, $terms)) {
                        // Product no longer available at stored location
                        $product = wc_get_product($product_id);
                        wc_add_notice(
                            sprintf(
                                __(
                                    '"%s" is no longer available at %s location and has been removed from your cart.',
                                    'multi-location-product-and-inventory-management'
                                ),
                                $product->get_name(),
                                $cart_item['mulopimfwc_location_name']
                            ),
                            'error'
                        );
                        WC()->cart->remove_cart_item($cart_item_key);
                    }
                }
            }
        }

        /**
         * Get cart items grouped by location
         */
        public function get_cart_items_by_location()
        {
            global $mulopimfwc_options;

            // Check if mixed location cart is enabled
            $allow_mixed = isset($mulopimfwc_options['allow_mixed_location_cart']) && mulopimfwc_premium_feature()
                ? $mulopimfwc_options['allow_mixed_location_cart']
                : 'off';

            if ($allow_mixed !== 'on') {
                return array();
            }

            $items_by_location = array();

            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                $location = isset($cart_item['mulopimfwc_location'])
                    ? $cart_item['mulopimfwc_location']
                    : 'unknown';

                if (!isset($items_by_location[$location])) {
                    $items_by_location[$location] = array(
                        'location_name' => isset($cart_item['mulopimfwc_location_name'])
                            ? $cart_item['mulopimfwc_location_name']
                            : __('Global', 'multi-location-product-and-inventory-management'),
                        'location_slug' => $location,
                        'items' => array()
                    );
                }

                $items_by_location[$location]['items'][] = array_merge($cart_item, ['cart_item_key' => $cart_item_key]);
            }

            return $items_by_location;
        }

        /**
         * Get cart items grouped by location for grouping display
         */
        public function get_cart_items_by_location_for_grouping()
        {
            // Return cached result if available
            if ($this->cart_items_cache !== null) {
                return $this->cart_items_cache;
            }

            if (!$this->should_apply_cart_grouping()) {
                return array();
            }

            $items_by_location = array();

            // Get cart once and cache it
            $cart_contents = WC()->cart->get_cart();

            if (empty($cart_contents)) {
                $this->cart_items_cache = array();
                return array();
            }

            foreach ($cart_contents as $cart_item_key => $cart_item) {
                $location = isset($cart_item['mulopimfwc_location'])
                    ? $cart_item['mulopimfwc_location']
                    : 'unknown';

                if (!isset($items_by_location[$location])) {
                    $items_by_location[$location] = array(
                        'location_name' => isset($cart_item['mulopimfwc_location_name'])
                            ? $cart_item['mulopimfwc_location_name']
                            : __('Global', 'multi-location-product-and-inventory-management'),
                        'location_slug' => $location,
                        'items' => array()
                    );
                }

                $items_by_location[$location]['items'][] = array_merge($cart_item, ['cart_item_key' => $cart_item_key]);
            }

            // Cache the result
            $this->cart_items_cache = $items_by_location;
            return $items_by_location;
        }

        /**
         * Display cart items grouped by location
         */

        public function display_cart_location_summary()
        {
            global $mulopimfwc_options;

            // Check if mixed location cart is enabled
            $allow_mixed = isset($mulopimfwc_options['allow_mixed_location_cart']) && mulopimfwc_premium_feature()
                ? $mulopimfwc_options['allow_mixed_location_cart']
                : 'off';

            if ($allow_mixed !== 'on') {
                return;
            }

            $items_by_location = $this->get_cart_items_by_location();

            if (count($items_by_location) > 1) {
                echo '<div class="mulopimfwc-cart-location-notice woocommerce-info">';
                echo '<strong>' . esc_html__('Your cart contains items from multiple locations:', 'multi-location-product-and-inventory-management') . '</strong><br>';

                foreach ($items_by_location as $location => $data) {
                    $item_count = count($data['items']);
                    echo sprintf(
                        esc_html__('%s: %d item(s)', 'multi-location-product-and-inventory-management'),
                        '<strong>' . esc_html($data['location_name']) . '</strong>',
                        $item_count
                    ) . '<br>';
                }

                echo '</div>';
            }
        }

        /**
         * Check if product is available in multiple locations
         */
        private function is_product_available_in_multiple_locations($product_id, $variation_id = 0)
        {
            $options = get_option('mulopimfwc_display_options', []);
            
            $product_to_check = $variation_id > 0 ? $variation_id : $product_id;
            $terms = wp_get_object_terms($product_to_check, 'mulopimfwc_store_location', ['fields' => 'all']);
            
            if (is_wp_error($terms) || empty($terms)) {
                // Check if enable_all_locations is on
                $enable_all_locations = isset($options['enable_all_locations']) ? $options['enable_all_locations'] : 'off';
                if ($enable_all_locations === 'on') {
                    // Get all locations
                    global $mulopimfwc_locations;
                    if (empty($mulopimfwc_locations)) {
                        $mulopimfwc_locations = get_terms([
                            'taxonomy' => 'mulopimfwc_store_location',
                            'hide_empty' => false,
                        ]);
                    }
                    return !empty($mulopimfwc_locations) && !is_wp_error($mulopimfwc_locations) && count($mulopimfwc_locations) > 1;
                }
                return false;
            }
            
            // Filter out disabled locations
            $active_locations = array_filter($terms, function($term) use ($product_to_check) {
                $is_disabled = get_post_meta($product_to_check, '_location_disabled_' . $term->term_id, true);
                return empty($is_disabled);
            });
            
            return count($active_locations) > 1;
        }

        /**
         * Display location selector in cart for products available in multiple locations
         */
        public function display_cart_location_selector($cart_item, $cart_item_key)
        {
            // Get options directly to ensure we have the latest value
            $options = get_option('mulopimfwc_display_options', []);
            
            // Check if feature is enabled
            $allow_location_change = isset($options['allow_location_change_in_cart']) 
                ? $options['allow_location_change_in_cart'] 
                : 'off';
            
            if ($allow_location_change !== 'on') {
                return;
            }
            
            $product_id = isset($cart_item['product_id']) ? $cart_item['product_id'] : 0;
            $variation_id = isset($cart_item['variation_id']) ? $cart_item['variation_id'] : 0;
            
            if (!$product_id) {
                return;
            }
            
            // Check if product is available in multiple locations
            if (!$this->is_product_available_in_multiple_locations($product_id, $variation_id)) {
                return;
            }
            
            // Get current location from cart item
            $current_location_slug = isset($cart_item['mulopimfwc_location']) ? $cart_item['mulopimfwc_location'] : '';
            
            // Get all available locations for this product
            $product_to_check = $variation_id > 0 ? $variation_id : $product_id;
            $terms = wp_get_object_terms($product_to_check, 'mulopimfwc_store_location', ['fields' => 'all']);
            
            if (is_wp_error($terms) || empty($terms)) {
                // If product has no specific locations, check if enable_all_locations is on
                $options = get_option('mulopimfwc_display_options', []);
                $enable_all_locations = isset($options['enable_all_locations']) ? $options['enable_all_locations'] : 'off';
                
                if ($enable_all_locations === 'on') {
                    // Get all locations
                    global $mulopimfwc_locations;
                    if (empty($mulopimfwc_locations) || is_wp_error($mulopimfwc_locations)) {
                        $mulopimfwc_locations = get_terms([
                            'taxonomy' => 'mulopimfwc_store_location',
                            'hide_empty' => false,
                        ]);
                    }
                    $terms = !empty($mulopimfwc_locations) && !is_wp_error($mulopimfwc_locations) ? $mulopimfwc_locations : [];
                } else {
                    // Product has no locations assigned and enable_all_locations is off
                    return;
                }
            }
            
            // Filter out disabled locations
            $available_locations = array_filter($terms, function($term) use ($product_to_check) {
                if (is_wp_error($term) || !is_object($term)) {
                    return false;
                }
                $is_disabled = get_post_meta($product_to_check, '_location_disabled_' . $term->term_id, true);
                return empty($is_disabled);
            });
            
            // Reset array keys after filtering
            $available_locations = array_values($available_locations);
            
            if (count($available_locations) <= 1) {
                return;
            }
            
            // Display location selector
            ?>
            <div class="mulopimfwc-cart-location-selector" data-cart-item-key="<?php echo esc_attr($cart_item_key); ?>" data-product-id="<?php echo esc_attr($product_id); ?>" data-variation-id="<?php echo esc_attr($variation_id); ?>">
                <label for="cart-location-<?php echo esc_attr($cart_item_key); ?>" style="font-size: 0.9em; font-weight: 600; display: block; margin-top: 8px; margin-bottom: 4px;">
                    <?php esc_html_e('Change Location:', 'multi-location-product-and-inventory-management'); ?>
                </label>
                <select name="cart_location[<?php echo esc_attr($cart_item_key); ?>]" id="cart-location-<?php echo esc_attr($cart_item_key); ?>" class="mulopimfwc-cart-location-select" style="width: 100%; max-width: 300px; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px;">
                    <?php foreach ($available_locations as $location): ?>
                        <option value="<?php echo esc_attr($location->slug); ?>" <?php selected($current_location_slug, $location->slug); ?>>
                            <?php echo esc_html($location->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php
        }

        /**
         * Update cart item location via AJAX
         */
        public function update_cart_item_location()
        {
            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'mulopimfwc_update_cart_location')) {
                wp_send_json_error(['message' => __('Security check failed.', 'multi-location-product-and-inventory-management')]);
            }
            
            $cart_item_key = isset($_POST['cart_item_key']) ? sanitize_text_field(wp_unslash($_POST['cart_item_key'])) : '';
            $new_location_slug = isset($_POST['location_slug']) ? sanitize_text_field(wp_unslash($_POST['location_slug'])) : '';
            
            if (empty($cart_item_key) || empty($new_location_slug)) {
                wp_send_json_error(['message' => __('Invalid parameters.', 'multi-location-product-and-inventory-management')]);
            }
            
            if (!function_exists('WC') || !WC()->cart) {
                wp_send_json_error(['message' => __('Cart not available.', 'multi-location-product-and-inventory-management')]);
            }
            
            $cart = WC()->cart;
            $cart_item = $cart->get_cart_item($cart_item_key);
            
            if (!$cart_item) {
                wp_send_json_error(['message' => __('Cart item not found.', 'multi-location-product-and-inventory-management')]);
            }
            
            // Get location term
            $location_term = get_term_by('slug', $new_location_slug, 'mulopimfwc_store_location');
            if (!$location_term || is_wp_error($location_term)) {
                wp_send_json_error(['message' => __('Invalid location.', 'multi-location-product-and-inventory-management')]);
            }
            
            // Update cart item data
            $cart_item['mulopimfwc_location'] = $new_location_slug;
            $cart_item['mulopimfwc_location_name'] = $location_term->name;
            
            // Update cart item
            $cart->cart_contents[$cart_item_key] = $cart_item;
            
            // Clear cart cache
            $this->clear_cart_cache();
            
            // Recalculate totals
            $cart->calculate_totals();
            
            // Get updated cart item for response
            $updated_cart_item = $cart->get_cart_item($cart_item_key);
            
            wp_send_json_success([
                'message' => __('Location updated successfully.', 'multi-location-product-and-inventory-management'),
                'location_name' => $location_term->name,
                'cart_item_key' => $cart_item_key,
                'cart_total' => $cart->get_cart_total(),
                'cart_subtotal' => $cart->get_cart_subtotal(),
            ]);
        }

        /**
         * Get available locations for a cart item (for cart blocks)
         */
        public function get_cart_item_locations()
        {
            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'mulopimfwc_update_cart_location')) {
                wp_send_json_error(['message' => __('Security check failed.', 'multi-location-product-and-inventory-management')]);
            }
            
            $cart_item_key = isset($_POST['cart_item_key']) ? sanitize_text_field(wp_unslash($_POST['cart_item_key'])) : '';
            $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
            $variation_id = isset($_POST['variation_id']) ? intval($_POST['variation_id']) : 0;
            
            if (empty($cart_item_key) || !$product_id) {
                wp_send_json_error(['message' => __('Invalid parameters.', 'multi-location-product-and-inventory-management')]);
            }
            
            if (!function_exists('WC') || !WC()->cart) {
                wp_send_json_error(['message' => __('Cart not available.', 'multi-location-product-and-inventory-management')]);
            }
            
            $cart = WC()->cart;
            $cart_item = $cart->get_cart_item($cart_item_key);
            
            if (!$cart_item) {
                wp_send_json_error(['message' => __('Cart item not found.', 'multi-location-product-and-inventory-management')]);
            }
            
            // Get current location
            $current_location_slug = isset($cart_item['mulopimfwc_location']) ? $cart_item['mulopimfwc_location'] : '';
            
            // Check if product is available in multiple locations
            if (!$this->is_product_available_in_multiple_locations($product_id, $variation_id)) {
                wp_send_json_error(['message' => __('Product is not available in multiple locations.', 'multi-location-product-and-inventory-management')]);
            }
            
            // Get all available locations for this product
            $product_to_check = $variation_id > 0 ? $variation_id : $product_id;
            $terms = wp_get_object_terms($product_to_check, 'mulopimfwc_store_location', ['fields' => 'all']);
            
            if (is_wp_error($terms) || empty($terms)) {
                // Check if enable_all_locations is on
                $options = get_option('mulopimfwc_display_options', []);
                $enable_all_locations = isset($options['enable_all_locations']) ? $options['enable_all_locations'] : 'off';
                
                if ($enable_all_locations === 'on') {
                    global $mulopimfwc_locations;
                    if (empty($mulopimfwc_locations) || is_wp_error($mulopimfwc_locations)) {
                        $mulopimfwc_locations = get_terms([
                            'taxonomy' => 'mulopimfwc_store_location',
                            'hide_empty' => false,
                        ]);
                    }
                    $terms = !empty($mulopimfwc_locations) && !is_wp_error($mulopimfwc_locations) ? $mulopimfwc_locations : [];
                } else {
                    wp_send_json_error(['message' => __('Product has no locations assigned.', 'multi-location-product-and-inventory-management')]);
                }
            }
            
            // Filter out disabled locations
            $available_locations = array_filter($terms, function($term) use ($product_to_check) {
                if (is_wp_error($term) || !is_object($term)) {
                    return false;
                }
                $is_disabled = get_post_meta($product_to_check, '_location_disabled_' . $term->term_id, true);
                return empty($is_disabled);
            });
            
            // Reset array keys
            $available_locations = array_values($available_locations);
            
            if (count($available_locations) <= 1) {
                wp_send_json_error(['message' => __('Product is not available in multiple locations.', 'multi-location-product-and-inventory-management')]);
            }
            
            // Format locations for response
            $locations_data = array_map(function($term) {
                return [
                    'slug' => $term->slug,
                    'name' => $term->name,
                    'term_id' => $term->term_id,
                ];
            }, $available_locations);
            
            wp_send_json_success([
                'locations' => $locations_data,
                'current_location' => $current_location_slug,
            ]);
        }

        /**
         * Check if cart grouping is enabled and override cart display
         */
        public function maybe_override_cart_display()
        {
            // Check if this is actually the cart page
            if (!function_exists('is_cart') || !is_cart()) {
                return;
            }

            if (!$this->should_apply_cart_grouping()) {
                return;
            }

            $items_by_location = $this->get_cart_items_by_location_for_grouping();

            // Only group if there are multiple locations
            if (count($items_by_location) <= 1) {
                return;
            }

            // Start buffering ONLY for the cart table
            ob_start();
        }


        /**
         * Restore cart display after grouping
         */
        public function maybe_restore_cart_display()
        {
            // Check if this is actually the cart page
            if (!function_exists('is_cart') || !is_cart()) {
                return;
            }

            if (!$this->should_apply_cart_grouping()) {
                return;
            }

            $items_by_location = $this->get_cart_items_by_location_for_grouping();

            // Only group if there are multiple locations
            if (count($items_by_location) <= 1) {
                return;
            }

            // Clear buffer and display grouped cart
            ob_end_clean();
            $this->display_grouped_cart();
        }

        /**
         * Check if cart grouping should be applied
         */
        private function should_apply_cart_grouping()
        {
            // Return cached result if available
            if ($this->should_group_cache !== null) {
                return $this->should_group_cache;
            }

            global $mulopimfwc_options;

            // Check if cart grouping is enabled
            $group_cart = isset($mulopimfwc_options['group_cart_by_location']) && mulopimfwc_premium_feature()
                ? $mulopimfwc_options['group_cart_by_location']
                : 'off';

            if ($group_cart !== 'on') {
                $this->should_group_cache = false;
                return false;
            }

            // Check if mixed location cart is enabled
            $allow_mixed = isset($mulopimfwc_options['allow_mixed_location_cart']) && mulopimfwc_premium_feature()
                ? $mulopimfwc_options['allow_mixed_location_cart']
                : 'off';

            $this->should_group_cache = ($allow_mixed === 'on');
            return $this->should_group_cache;
        }

        /**
         * Display grouped mini cart
         */
        public function display_grouped_mini_cart()
        {
            $items_by_location = $this->get_cart_items_by_location_for_grouping();

            if (empty($items_by_location)) {
                // Fallback to default mini cart display
                wc_get_template('cart/mini-cart.php');
                return;
            }

            // If only one location, display normally
            if (count($items_by_location) === 1) {
                wc_get_template('cart/mini-cart.php');
                return;
            }

            // Display grouped mini cart
        ?>
            <div class="mulopimfwc-grouped-mini-cart">
                <?php foreach ($items_by_location as $location_data): ?>
                    <div class="mulopimfwc-mini-location-group">
                        <div class="mulopimfwc-mini-location-header">
                            <div class="mulopimfwc-mini-location-icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" fill="currentColor" />
                                </svg>
                            </div>
                            <span class="mulopimfwc-mini-location-name"><?php echo esc_html($location_data['location_name']); ?></span>
                        </div>

                        <div class="mulopimfwc-mini-location-items">
                            <?php foreach ($location_data['items'] as $cart_item): ?>
                                <?php
                                $product = $cart_item['data'];
                                $product_id = $cart_item['product_id'];
                                $variation_id = $cart_item['variation_id'];
                                $cart_item_key = $cart_item['cart_item_key'];

                                if (!$product || !$product->exists() || $cart_item['quantity'] <= 0) {
                                    continue;
                                }
                                ?>
                                <div class="woocommerce-mini-cart-item mini_cart_item">
                                    <?php
                                    echo apply_filters('woocommerce_cart_item_remove_link', sprintf(
                                        '<a href="%s" class="remove remove_from_cart_button" aria-label="%s" data-product_id="%s" data-cart_item_key="%s" data-product_sku="%s">&times;</a>',
                                        esc_url(wc_get_cart_remove_url($cart_item_key)),
                                        esc_html__('Remove this item', 'woocommerce'),
                                        $product_id,
                                        $cart_item_key,
                                        $product->get_sku()
                                    ), $cart_item_key);
                                    ?>

                                    <?php
                                    $thumbnail = apply_filters('woocommerce_cart_item_thumbnail', $product->get_image(), $cart_item, $cart_item_key);
                                    if (!$product->is_visible()) {
                                        echo $thumbnail;
                                    } else {
                                        printf('<a href="%s">%s</a>', esc_url($product->get_permalink($cart_item)), $thumbnail);
                                    }
                                    ?>

                                    <div class="mini_cart_item_details">
                                        <?php
                                        if (!$product->is_visible()) {
                                            echo wp_kses_post(apply_filters('woocommerce_cart_item_name', $product->get_name(), $cart_item, $cart_item_key) . '&nbsp;');
                                        } else {
                                            echo wp_kses_post(apply_filters('woocommerce_cart_item_name', sprintf('<a href="%s">%s</a>', esc_url($product->get_permalink($cart_item)), $product->get_name()), $cart_item, $cart_item_key));
                                        }

                                        do_action('woocommerce_after_cart_item_name', $cart_item, $cart_item_key);

                                        // Meta data
                                        echo wc_get_formatted_cart_item_data($cart_item);

                                        // Backorder notification
                                        if ($product->backorders_require_notification() && $product->is_on_backorder($cart_item['quantity'])) {
                                            echo wp_kses_post(apply_filters('woocommerce_cart_item_backorder_notification', '<p class="backorder_notification">' . esc_html__('Available on backorder', 'woocommerce') . '</p>', $product_id));
                                        }
                                        ?>

                                        <div class="mini_cart_item_quantity">
                                            <?php echo apply_filters('woocommerce_widget_cart_item_quantity', '<span class="quantity">' . sprintf('%s &times; %s', $cart_item['quantity'], $product->get_price_html()) . '</span>', $cart_item, $cart_item_key); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php
        }

        /**
         * Override checkout cart display
         */
        public function maybe_override_checkout_cart_display()
        {
            // Check if this is actually the checkout page
            if (!function_exists('is_checkout') || !is_checkout()) {
                return;
            }

            if (!$this->should_apply_cart_grouping()) {
                return;
            }

            $items_by_location = $this->get_cart_items_by_location_for_grouping();

            if (count($items_by_location) <= 1) {
                return;
            }

            ob_start();
        }

        /**
         * Restore checkout cart display
         */
        public function maybe_restore_checkout_cart_display()
        {
            // Check if this is actually the checkout page
            if (!function_exists('is_checkout') || !is_checkout()) {
                return;
            }

            if (!$this->should_apply_cart_grouping()) {
                return;
            }

            $items_by_location = $this->get_cart_items_by_location_for_grouping();

            if (count($items_by_location) <= 1) {
                return;
            }

            ob_end_clean();
            $this->display_grouped_checkout_cart();
        }

        /**
         * Display grouped checkout cart
         */
        public function display_grouped_checkout_cart()
        {
            $items_by_location = $this->get_cart_items_by_location_for_grouping();

            if (empty($items_by_location)) {
                // Fallback to default checkout cart display
                wc_get_template('checkout/review-order.php');
                return;
            }

            // If only one location, display normally
            if (count($items_by_location) === 1) {
                wc_get_template('checkout/review-order.php');
                return;
            }

            // Display grouped checkout cart
        ?>
            <div class="mulopimfwc-grouped-checkout-cart">
                <?php foreach ($items_by_location as $location_data): ?>
                    <div class="mulopimfwc-location-group">
                        <div class="mulopimfwc-location-header">
                            <div class="mulopimfwc-location-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" fill="currentColor" />
                                </svg>
                            </div>
                            <h3 class="mulopimfwc-location-name"><?php echo esc_html($location_data['location_name']); ?></h3>
                        </div>

                        <div class="mulopimfwc-location-items">
                            <table class="shop_table woocommerce-checkout-review-order-table" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th class="product-name"><?php esc_html_e('Product', 'woocommerce'); ?></th>
                                        <th class="product-total"><?php esc_html_e('Subtotal', 'woocommerce'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($location_data['items'] as $cart_item): ?>
                                        <?php
                                        $product = $cart_item['data'];
                                        $product_id = $cart_item['product_id'];
                                        $variation_id = $cart_item['variation_id'];
                                        $cart_item_key = $cart_item['cart_item_key'];

                                        if (!$product || !$product->exists() || $cart_item['quantity'] <= 0) {
                                            continue;
                                        }
                                        ?>
                                        <tr class="cart_item">
                                            <td class="product-name">
                                                <?php
                                                if (!$product->is_visible()) {
                                                    echo wp_kses_post(apply_filters('woocommerce_cart_item_name', $product->get_name(), $cart_item, $cart_item_key) . '&nbsp;');
                                                } else {
                                                    echo wp_kses_post(apply_filters('woocommerce_cart_item_name', sprintf('<a href="%s">%s</a>', esc_url($product->get_permalink($cart_item)), $product->get_name()), $cart_item, $cart_item_key));
                                                }

                                                do_action('woocommerce_after_cart_item_name', $cart_item, $cart_item_key);

                                                // Meta data
                                                echo wc_get_formatted_cart_item_data($cart_item);

                                                // Backorder notification
                                                if ($product->backorders_require_notification() && $product->is_on_backorder($cart_item['quantity'])) {
                                                    echo wp_kses_post(apply_filters('woocommerce_cart_item_backorder_notification', '<p class="backorder_notification">' . esc_html__('Available on backorder', 'woocommerce') . '</p>', $product_id));
                                                }
                                                ?>
                                                <strong class="product-quantity"><?php echo apply_filters('woocommerce_checkout_cart_item_quantity', '&nbsp;&times;&nbsp;' . $cart_item['quantity'], $cart_item, $cart_item_key); ?></strong>
                                            </td>
                                            <td class="product-total">
                                                <?php
                                                echo apply_filters('woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal($product, $cart_item['quantity']), $cart_item, $cart_item_key);
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php
        }

        /**
         * Display cart grouped by location
         */
        public function display_grouped_cart()
        {
            $items_by_location = $this->get_cart_items_by_location_for_grouping();

            if (empty($items_by_location)) {
                // Fallback to default cart display
                wc_get_template('cart/cart.php');
                return;
            }

            // If only one location, display normally
            if (count($items_by_location) === 1) {
                wc_get_template('cart/cart.php');
                return;
            }

            // Display grouped cart
        ?>
            <form class="woocommerce-cart-form" action="<?php echo esc_url(wc_get_cart_url()); ?>" method="post">
                <?php wp_nonce_field('woocommerce-cart', 'woocommerce-cart-nonce'); ?>
                <div class="mulopimfwc-grouped-cart">
                    <?php foreach ($items_by_location as $location_data): ?>
                        <div class="mulopimfwc-location-group">
                            <div class="mulopimfwc-location-header">
                                <div class="mulopimfwc-location-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" fill="currentColor" />
                                    </svg>
                                </div>
                                <h3 class="mulopimfwc-location-name"><?php echo esc_html($location_data['location_name']); ?></h3>
                            </div>

                            <div class="mulopimfwc-location-items">
                                <table class="shop_table shop_table_responsive cart woocommerce-cart-form__contents" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th class="product-remove">&nbsp;</th>
                                            <th class="product-thumbnail">&nbsp;</th>
                                            <th class="product-name"><?php esc_html_e('Product', 'woocommerce'); ?></th>
                                            <th class="product-price"><?php esc_html_e('Price', 'woocommerce'); ?></th>
                                            <th class="product-quantity"><?php esc_html_e('Quantity', 'woocommerce'); ?></th>
                                            <th class="product-subtotal"><?php esc_html_e('Subtotal', 'woocommerce'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($location_data['items'] as $cart_item): ?>
                                            <?php
                                            $product = $cart_item['data'];
                                            $product_id = $cart_item['product_id'];
                                            $variation_id = $cart_item['variation_id'];
                                            $cart_item_key = $cart_item['cart_item_key'];

                                            if (!$product || !$product->exists() || $cart_item['quantity'] <= 0) {
                                                continue;
                                            }
                                            ?>
                                            <tr class="woocommerce-cart-form__cart-item cart_item">
                                                <td class="product-remove">
                                                    <?php
                                                    echo apply_filters('woocommerce_cart_item_remove_link', sprintf(
                                                        '<a href="%s" class="remove" aria-label="%s" data-product_id="%s" data-product_sku="%s">&times;</a>',
                                                        esc_url(wc_get_cart_remove_url($cart_item_key)),
                                                        esc_html__('Remove this item', 'woocommerce'),
                                                        $product_id,
                                                        $product->get_sku()
                                                    ), $cart_item_key);
                                                    ?>
                                                </td>
                                                <td class="product-thumbnail">
                                                    <?php
                                                    $thumbnail = apply_filters('woocommerce_cart_item_thumbnail', $product->get_image(), $cart_item, $cart_item_key);
                                                    if (!$product->is_visible()) {
                                                        echo $thumbnail;
                                                    } else {
                                                        printf('<a href="%s">%s</a>', esc_url($product->get_permalink($cart_item)), $thumbnail);
                                                    }
                                                    ?>
                                                </td>
                                                <td class="product-name" data-title="<?php esc_attr_e('Product', 'woocommerce'); ?>">
                                                    <?php
                                                    if (!$product->is_visible()) {
                                                        echo wp_kses_post(apply_filters('woocommerce_cart_item_name', $product->get_name(), $cart_item, $cart_item_key) . '&nbsp;');
                                                    } else {
                                                        echo wp_kses_post(apply_filters('woocommerce_cart_item_name', sprintf('<a href="%s">%s</a>', esc_url($product->get_permalink($cart_item)), $product->get_name()), $cart_item, $cart_item_key));
                                                    }

                                                    do_action('woocommerce_after_cart_item_name', $cart_item, $cart_item_key);

                                                    // Meta data
                                                    echo wc_get_formatted_cart_item_data($cart_item);

                                                    // Backorder notification
                                                    if ($product->backorders_require_notification() && $product->is_on_backorder($cart_item['quantity'])) {
                                                        echo wp_kses_post(apply_filters('woocommerce_cart_item_backorder_notification', '<p class="backorder_notification">' . esc_html__('Available on backorder', 'woocommerce') . '</p>', $product_id));
                                                    }
                                                    ?>
                                                </td>
                                                <td class="product-price" data-title="<?php esc_attr_e('Price', 'woocommerce'); ?>">
                                                    <?php
                                                    echo apply_filters('woocommerce_cart_item_price', WC()->cart->get_product_price($product), $cart_item, $cart_item_key);
                                                    ?>
                                                </td>
                                                <td class="product-quantity" data-title="<?php esc_attr_e('Quantity', 'woocommerce'); ?>">
                                                    <?php
                                                    if ($product->is_sold_individually()) {
                                                        $product_quantity = sprintf('1 <input type="hidden" name="cart[%s][qty]" value="1" />', $cart_item_key);
                                                    } else {
                                                        $product_quantity = woocommerce_quantity_input(
                                                            array(
                                                                'input_name'   => "cart[{$cart_item_key}][qty]",
                                                                'input_value'  => $cart_item['quantity'],
                                                                'max_value'    => $product->get_max_purchase_quantity(),
                                                                'min_value'    => '0',
                                                                'product_name' => $product->get_name(),
                                                            ),
                                                            $product,
                                                            false
                                                        );
                                                    }

                                                    echo apply_filters('woocommerce_cart_item_quantity', $product_quantity, $cart_item_key, $cart_item);
                                                    ?>
                                                </td>
                                                <td class="product-subtotal" data-title="<?php esc_attr_e('Subtotal', 'woocommerce'); ?>">
                                                    <?php
                                                    echo apply_filters('woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal($product, $cart_item['quantity']), $cart_item, $cart_item_key);
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="actions">
                    <button type="submit" class="button" name="update_cart" value="<?php esc_attr_e('Update cart', 'woocommerce'); ?>"><?php esc_html_e('Update cart', 'woocommerce'); ?></button>
                    <?php do_action('woocommerce_cart_actions'); ?>
                </div>
            </form>
        <?php
        }

        /**
         * Display grouped cart block
         */
        public function display_grouped_cart_block()
        {
            $items_by_location = $this->get_cart_items_by_location_for_grouping();

            if (empty($items_by_location)) {
                return;
            }

            // If only one location, don't group
            if (count($items_by_location) === 1) {
                return;
            }

        ?>
            <div class="mulopimfwc-grouped-cart-block">
                <?php foreach ($items_by_location as $location_data): ?>
                    <div class="mulopimfwc-location-group">
                        <div class="mulopimfwc-location-header">
                            <div class="mulopimfwc-location-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" fill="currentColor" />
                                </svg>
                            </div>
                            <h3 class="mulopimfwc-location-name"><?php echo esc_html($location_data['location_name']); ?></h3>
                        </div>

                        <div class="mulopimfwc-location-items">
                            <?php foreach ($location_data['items'] as $cart_item): ?>
                                <?php
                                $product = $cart_item['data'];
                                $product_id = $cart_item['product_id'];
                                $variation_id = $cart_item['variation_id'];
                                $cart_item_key = $cart_item['cart_item_key'];

                                if (!$product || !$product->exists() || $cart_item['quantity'] <= 0) {
                                    continue;
                                }
                                ?>
                                <div class="woocommerce-cart-form__cart-item cart_item">
                                    <div class="product-remove">
                                        <?php
                                        echo apply_filters('woocommerce_cart_item_remove_link', sprintf(
                                            '<a href="%s" class="remove" aria-label="%s" data-product_id="%s" data-product_sku="%s">&times;</a>',
                                            esc_url(wc_get_cart_remove_url($cart_item_key)),
                                            esc_html__('Remove this item', 'woocommerce'),
                                            $product_id,
                                            $product->get_sku()
                                        ), $cart_item_key);
                                        ?>
                                    </div>
                                    <div class="product-thumbnail">
                                        <?php
                                        $thumbnail = apply_filters('woocommerce_cart_item_thumbnail', $product->get_image(), $cart_item, $cart_item_key);
                                        if (!$product->is_visible()) {
                                            echo $thumbnail;
                                        } else {
                                            printf('<a href="%s">%s</a>', esc_url($product->get_permalink($cart_item)), $thumbnail);
                                        }
                                        ?>
                                    </div>
                                    <div class="product-name">
                                        <?php
                                        if (!$product->is_visible()) {
                                            echo wp_kses_post(apply_filters('woocommerce_cart_item_name', $product->get_name(), $cart_item, $cart_item_key) . '&nbsp;');
                                        } else {
                                            echo wp_kses_post(apply_filters('woocommerce_cart_item_name', sprintf('<a href="%s">%s</a>', esc_url($product->get_permalink($cart_item)), $product->get_name()), $cart_item, $cart_item_key));
                                        }

                                        do_action('woocommerce_after_cart_item_name', $cart_item, $cart_item_key);

                                        // Meta data
                                        echo wc_get_formatted_cart_item_data($cart_item);

                                        // Backorder notification
                                        if ($product->backorders_require_notification() && $product->is_on_backorder($cart_item['quantity'])) {
                                            echo wp_kses_post(apply_filters('woocommerce_cart_item_backorder_notification', '<p class="backorder_notification">' . esc_html__('Available on backorder', 'woocommerce') . '</p>', $product_id));
                                        }
                                        ?>
                                    </div>
                                    <div class="product-price">
                                        <?php
                                        echo apply_filters('woocommerce_cart_item_price', WC()->cart->get_product_price($product), $cart_item, $cart_item_key);
                                        ?>
                                    </div>
                                    <div class="product-quantity">
                                        <?php
                                        if ($product->is_sold_individually()) {
                                            $product_quantity = sprintf('1 <input type="hidden" name="cart[%s][qty]" value="1" />', $cart_item_key);
                                        } else {
                                            $product_quantity = woocommerce_quantity_input(
                                                array(
                                                    'input_name'   => "cart[{$cart_item_key}][qty]",
                                                    'input_value'  => $cart_item['quantity'],
                                                    'max_value'    => $product->get_max_purchase_quantity(),
                                                    'min_value'    => '0',
                                                    'product_name' => $product->get_name(),
                                                ),
                                                $product,
                                                false
                                            );
                                        }

                                        echo apply_filters('woocommerce_cart_item_quantity', $product_quantity, $cart_item_key, $cart_item);
                                        ?>
                                    </div>
                                    <div class="product-subtotal">
                                        <?php
                                        echo apply_filters('woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal($product, $cart_item['quantity']), $cart_item, $cart_item_key);
                                        ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
<?php
        }

        /**
         * Get available locations for a product via AJAX
         */
        public function cymulopimfwc_get_available_locations()
        {
            global $mulopimfwc_locations;
            // Check nonce
            check_ajax_referer('location_wise_products_nonce', 'security');

            // Check permissions
            if (!current_user_can('manage_woocommerce')) {
                wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'multi-location-product-and-inventory-management')]);
            }

            // Get product ID
            $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
            $location_selected = wp_get_object_terms($product_id, 'mulopimfwc_store_location', array('fields' => 'slugs'));
            if (!$product_id) {
                wp_send_json_error(['message' => __('Invalid product ID.', 'multi-location-product-and-inventory-management')]);
            }

            if (is_wp_error($mulopimfwc_locations)) {
                wp_send_json_error(['message' => $mulopimfwc_locations->get_error_message()]);
            }

            // Format locations for output
            $location_data = [];
            foreach ($mulopimfwc_locations as $location) {
                $location_data[] = [
                    'id' => $location->term_id,
                    'name' => $location->name,
                    'parent' => $location->parent,
                    'selected' => in_array($location->slug, $location_selected),
                ];
            }

            wp_send_json_success(['locations' => $location_data]);
        }

        /**
         * Save product locations via AJAX
         */
        public function cymulopimfwc_save_product_locations()
        {
            // Check nonce
            check_ajax_referer('location_wise_products_nonce', 'security');

            // Check permissions
            if (!current_user_can('manage_woocommerce')) {
                wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'multi-location-product-and-inventory-management')]);
            }

            // Get product ID and location IDs
            $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
            $location_ids = isset($_POST['location_ids']) ? array_map('intval', (array) $_POST['location_ids']) : [];

            if (!$product_id) {
                wp_send_json_error(['message' => __('Invalid product ID.', 'multi-location-product-and-inventory-management')]);
            }

            // Set product locations
            wp_set_object_terms($product_id, $location_ids, 'mulopimfwc_store_location');

            wp_send_json_success([
                'message' => __('Product locations saved successfully.', 'multi-location-product-and-inventory-management'),
            ]);
        }

        /**
         * Update product location status via AJAX
         */
        public function cymulopimfwc_update_product_location_status()
        {
            // Check nonce
            check_ajax_referer('location_wise_products_nonce', 'security');

            // Check permissions
            if (!current_user_can('manage_woocommerce')) {
                wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'multi-location-product-and-inventory-management')]);
            }

            // Get parameters
            $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
            $location_id = isset($_POST['location_id']) ? intval($_POST['location_id']) : 0;
            $action = isset($_POST['status_action']) ? sanitize_text_field(wp_unslash($_POST['status_action'])) : '';

            if (!$product_id || !$location_id || !in_array($action, ['activate', 'deactivate'])) {
                wp_send_json_error(['message' => __('Invalid parameters.', 'multi-location-product-and-inventory-management')]);
            }

            // Update location status
            if ($action === 'activate') {
                // Activate location - remove disabled meta
                delete_post_meta($product_id, '_location_disabled_' . $location_id);
                $message = __('Location activated successfully.', 'multi-location-product-and-inventory-management');
            } else {
                // Deactivate location - add disabled meta
                update_post_meta($product_id, '_location_disabled_' . $location_id, 1);
                $message = __('Location deactivated successfully.', 'multi-location-product-and-inventory-management');
            }

            wp_send_json_success(['message' => $message]);
        }

        /**
         * Get product data for quick edit popup via AJAX
         */
        public function cymulopimfwc_get_product_quick_edit_data()
        {
            // Check nonce
            check_ajax_referer('location_wise_products_nonce', 'security');

            // Check permissions
            if (!current_user_can('manage_woocommerce')) {
                wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'multi-location-product-and-inventory-management')]);
            }

            // Get product ID
            $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
            if (!$product_id) {
                wp_send_json_error(['message' => __('Invalid product ID.', 'multi-location-product-and-inventory-management')]);
            }

            $product = wc_get_product($product_id);
            if (!$product) {
                wp_send_json_error(['message' => __('Product not found.', 'multi-location-product-and-inventory-management')]);
            }

            global $mulopimfwc_locations;
            $product_type = $product->get_type();
            $data = [
                'product_id' => $product_id,
                'product_name' => $product->get_name(),
                'product_type' => $product_type,
                'default' => [],
                'locations' => [],
                'variations' => [],
            ];

            // Get default product data
            $data['default'] = [
                'stock_quantity' => $product->get_stock_quantity(),
                'regular_price' => $product->get_regular_price(),
                'sale_price' => $product->get_sale_price(),
                'backorders' => $product->get_backorders(),
                'purchase_price' => get_post_meta($product_id, '_purchase_price', true),
                'purchase_quantity' => get_post_meta($product_id, '_purchase_quantity', true),
            ];

            // Get location data - batch meta queries for better performance
            if (!is_wp_error($mulopimfwc_locations) && !empty($mulopimfwc_locations)) {
                $location_ids = array_map(function($loc) { return $loc->term_id; }, $mulopimfwc_locations);
                
                // Batch fetch all meta keys at once
                $meta_keys = [];
                foreach ($location_ids as $loc_id) {
                    $meta_keys[] = '_location_stock_' . $loc_id;
                    $meta_keys[] = '_location_regular_price_' . $loc_id;
                    $meta_keys[] = '_location_sale_price_' . $loc_id;
                    $meta_keys[] = '_location_backorders_' . $loc_id;
                }
                
                // Get all meta in one query - optimized batch query
                global $wpdb;
                if (!empty($meta_keys)) {
                    $escaped_keys = array_map(function($key) use ($wpdb) {
                        return $wpdb->prepare('%s', $key);
                    }, $meta_keys);
                    $meta_query = $wpdb->prepare(
                        "SELECT meta_key, meta_value FROM {$wpdb->postmeta} 
                        WHERE post_id = %d AND meta_key IN (" . implode(',', $escaped_keys) . ")",
                        $product_id
                    );
                    $meta_results = $wpdb->get_results($meta_query);
                    $meta_values = [];
                    foreach ($meta_results as $row) {
                        $meta_values[$row->meta_key] = $row->meta_value;
                    }
                } else {
                    $meta_values = [];
                }
                
                // Build location data from cached meta
                foreach ($mulopimfwc_locations as $location) {
                    $location_data = [
                        'id' => $location->term_id,
                        'name' => $location->name,
                        'stock' => isset($meta_values['_location_stock_' . $location->term_id]) ? $meta_values['_location_stock_' . $location->term_id] : '',
                        'regular_price' => isset($meta_values['_location_regular_price_' . $location->term_id]) ? $meta_values['_location_regular_price_' . $location->term_id] : '',
                        'sale_price' => isset($meta_values['_location_sale_price_' . $location->term_id]) ? $meta_values['_location_sale_price_' . $location->term_id] : '',
                        'backorders' => isset($meta_values['_location_backorders_' . $location->term_id]) ? $meta_values['_location_backorders_' . $location->term_id] : '',
                    ];
                    $data['locations'][] = $location_data;
                }
            }

            // Get variation data for variable products - optimized
            if ($product_type === 'variable') {
                // Get variation IDs directly without loading full objects
                $variation_ids = $product->get_children();
                
                if (!empty($variation_ids)) {
                    // Get location IDs if available
                    $loc_ids_for_variations = [];
                    if (!is_wp_error($mulopimfwc_locations) && !empty($mulopimfwc_locations)) {
                        $loc_ids_for_variations = array_map(function($loc) { return $loc->term_id; }, $mulopimfwc_locations);
                    }
                    
                    // Batch load all variation meta at once
                    global $wpdb;
                    $variation_meta_keys = ['_stock', '_regular_price', '_sale_price', '_backorders', '_purchase_price'];
                    foreach ($loc_ids_for_variations as $loc_id) {
                        $variation_meta_keys[] = '_location_stock_' . $loc_id;
                        $variation_meta_keys[] = '_location_regular_price_' . $loc_id;
                        $variation_meta_keys[] = '_location_sale_price_' . $loc_id;
                        $variation_meta_keys[] = '_location_backorders_' . $loc_id;
                    }
                    
                    if (!empty($variation_meta_keys)) {
                        $id_placeholders = implode(',', array_map('intval', $variation_ids));
                        $prepared_meta_keys = array_map(function($key) use ($wpdb) {
                            return $wpdb->prepare('%s', $key);
                        }, $variation_meta_keys);
                        $meta_query = $wpdb->prepare(
                            "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} 
                            WHERE post_id IN ($id_placeholders) AND meta_key IN (" . implode(',', $prepared_meta_keys) . ")"
                        );
                        $all_variation_meta = $wpdb->get_results($meta_query);
                        
                        // Organize meta by variation ID
                        $variation_meta_map = [];
                        foreach ($all_variation_meta as $meta) {
                            if (!isset($variation_meta_map[$meta->post_id])) {
                                $variation_meta_map[$meta->post_id] = [];
                            }
                            $variation_meta_map[$meta->post_id][$meta->meta_key] = $meta->meta_value;
                        }
                    } else {
                        $variation_meta_map = [];
                    }
                    
                    // Get variation attributes efficiently
                    foreach ($variation_ids as $variation_id) {
                        $variation = wc_get_product($variation_id);
                        if (!$variation) {
                            continue;
                        }
                        
                        $meta = isset($variation_meta_map[$variation_id]) ? $variation_meta_map[$variation_id] : [];
                        
                        // Get attributes
                        $attributes = [];
                        foreach ($variation->get_attributes() as $key => $value) {
                            $attributes[$key] = $value;
                        }
                        
                        $variation_info = [
                            'id' => $variation_id,
                            'attributes' => $attributes,
                            'default' => [
                                'stock_quantity' => isset($meta['_stock']) ? $meta['_stock'] : $variation->get_stock_quantity(),
                                'regular_price' => isset($meta['_regular_price']) ? $meta['_regular_price'] : $variation->get_regular_price(),
                                'sale_price' => isset($meta['_sale_price']) ? $meta['_sale_price'] : $variation->get_sale_price(),
                                'backorders' => isset($meta['_backorders']) ? $meta['_backorders'] : $variation->get_backorders(),
                                'purchase_price' => isset($meta['_purchase_price']) ? $meta['_purchase_price'] : '',
                            ],
                            'locations' => [],
                        ];

                        // Get location data for variation from cached meta
                        if (!is_wp_error($mulopimfwc_locations) && !empty($mulopimfwc_locations)) {
                            foreach ($mulopimfwc_locations as $location) {
                                $variation_info['locations'][] = [
                                    'id' => $location->term_id,
                                    'name' => $location->name,
                                    'stock' => isset($meta['_location_stock_' . $location->term_id]) ? $meta['_location_stock_' . $location->term_id] : '',
                                    'regular_price' => isset($meta['_location_regular_price_' . $location->term_id]) ? $meta['_location_regular_price_' . $location->term_id] : '',
                                    'sale_price' => isset($meta['_location_sale_price_' . $location->term_id]) ? $meta['_location_sale_price_' . $location->term_id] : '',
                                    'backorders' => isset($meta['_location_backorders_' . $location->term_id]) ? $meta['_location_backorders_' . $location->term_id] : '',
                                ];
                            }
                        }

                        $data['variations'][] = $variation_info;
                    }
                }
            }

            wp_send_json_success($data);
        }

        /**
         * Save product quick edit data via AJAX
         */
        public function cymulopimfwc_save_product_quick_edit_data()
        {
            // Check nonce
            check_ajax_referer('location_wise_products_nonce', 'security');

            // Check permissions
            if (!current_user_can('manage_woocommerce')) {
                wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'multi-location-product-and-inventory-management')]);
            }

            // Get product ID
            $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
            if (!$product_id) {
                wp_send_json_error(['message' => __('Invalid product ID.', 'multi-location-product-and-inventory-management')]);
            }

            $product = wc_get_product($product_id);
            if (!$product) {
                wp_send_json_error(['message' => __('Product not found.', 'multi-location-product-and-inventory-management')]);
            }

            // Save default product data
            if (isset($_POST['default'])) {
                $default = $_POST['default'];
                if (isset($default['stock_quantity'])) {
                    $product->set_stock_quantity(intval($default['stock_quantity']));
                }
                if (isset($default['regular_price'])) {
                    $product->set_regular_price(wc_format_decimal($default['regular_price']));
                }
                if (isset($default['sale_price'])) {
                    $product->set_sale_price(wc_format_decimal($default['sale_price']));
                }
                if (isset($default['backorders'])) {
                    $product->set_backorders(sanitize_text_field($default['backorders']));
                }
                if (isset($default['purchase_price'])) {
                    update_post_meta($product_id, '_purchase_price', wc_format_decimal($default['purchase_price']));
                }
                if (isset($default['purchase_quantity'])) {
                    update_post_meta($product_id, '_purchase_quantity', intval($default['purchase_quantity']));
                }
                $product->save();
            }

            // Save location data
            if (isset($_POST['locations']) && is_array($_POST['locations'])) {
                foreach ($_POST['locations'] as $location_data) {
                    $location_id = isset($location_data['id']) ? intval($location_data['id']) : 0;
                    if (!$location_id) {
                        continue;
                    }

                    $old_stock = get_post_meta($product_id, '_location_stock_' . $location_id, true);

                    if (isset($location_data['stock'])) {
                        $new_stock = intval($location_data['stock']);
                        update_post_meta($product_id, '_location_stock_' . $location_id, $new_stock);

                        // Notify when crossing thresholds
                        $location_term = get_term($location_id, 'mulopimfwc_store_location');
                        if ($location_term && !is_wp_error($location_term)) {
                            $low_threshold = mulopimfwc_get_location_threshold($location_id, 'low');
                            $out_threshold = mulopimfwc_get_location_threshold($location_id, 'out');

                            $old_stock_int = ($old_stock === '' || $old_stock === null) ? null : (int) $old_stock;

                            // Out of stock alert
                            if ($new_stock <= $out_threshold && ($old_stock_int === null || $old_stock_int > $out_threshold)) {
                                mulopimfwc_send_location_stock_alert($product_id, $location_term, $new_stock, $out_threshold, 'out');
                            }
                            // Low stock alert (only if not already out-of-stock)
                            elseif ($new_stock <= $low_threshold && ($old_stock_int === null || $old_stock_int > $low_threshold)) {
                                mulopimfwc_send_location_stock_alert($product_id, $location_term, $new_stock, $low_threshold, 'low');
                            }

                            // Restocked (back above low threshold)
                            $settings = mulopimfwc_get_social_settings();
                            if (
                                isset($settings['enabled'], $settings['restocked']) &&
                                $settings['enabled'] === 'on' &&
                                $settings['restocked'] === 'on' &&
                                $low_threshold > 0 &&
                                $old_stock_int !== null &&
                                $old_stock_int <= $low_threshold &&
                                $new_stock > $low_threshold
                            ) {
                                $channels = mulopimfwc_collect_social_channels($location_term->slug, $settings);
                                if (!empty($channels)) {
                                    mulopimfwc_send_social_message(
                                        __('Restocked', 'multi-location-product-and-inventory-management'),
                                        sprintf(
                                            __('%s @ %s is back above low threshold. Stock: %d (threshold %d)', 'multi-location-product-and-inventory-management'),
                                            $product->get_name(),
                                            $location_term->name,
                                            (int) $new_stock,
                                            (int) $low_threshold
                                        ),
                                        $channels,
                                        $settings,
                                        admin_url('post.php?post=' . $product_id . '&action=edit')
                                    );
                                }
                            }
                        }
                    }
                    if (isset($location_data['regular_price'])) {
                        update_post_meta($product_id, '_location_regular_price_' . $location_id, wc_format_decimal($location_data['regular_price']));
                    }
                    if (isset($location_data['sale_price'])) {
                        update_post_meta($product_id, '_location_sale_price_' . $location_id, wc_format_decimal($location_data['sale_price']));
                    }
                    if (isset($location_data['backorders'])) {
                        update_post_meta($product_id, '_location_backorders_' . $location_id, sanitize_text_field($location_data['backorders']));
                    }
                }
            }

            // Set assigned locations (queue add/remove)
            if (isset($_POST['location_ids'])) {
                $location_ids = array_map('intval', (array) $_POST['location_ids']);
                wp_set_object_terms($product_id, $location_ids, 'mulopimfwc_store_location');

                $removed_location_ids = isset($_POST['removed_location_ids']) ? array_map('intval', (array) $_POST['removed_location_ids']) : [];
                if (!empty($removed_location_ids)) {
                    $removed_location_ids = array_diff($removed_location_ids, $location_ids);
                    foreach ($removed_location_ids as $location_id) {
                        delete_post_meta($product_id, '_location_stock_' . $location_id);
                        delete_post_meta($product_id, '_location_regular_price_' . $location_id);
                        delete_post_meta($product_id, '_location_sale_price_' . $location_id);
                        delete_post_meta($product_id, '_location_backorders_' . $location_id);
                        delete_post_meta($product_id, '_location_disabled_' . $location_id);

                        if ($product->get_type() === 'variable') {
                            $variation_ids = $product->get_children();
                            foreach ($variation_ids as $variation_id) {
                                delete_post_meta($variation_id, '_location_stock_' . $location_id);
                                delete_post_meta($variation_id, '_location_regular_price_' . $location_id);
                                delete_post_meta($variation_id, '_location_sale_price_' . $location_id);
                                delete_post_meta($variation_id, '_location_backorders_' . $location_id);
                            }
                        }
                    }
                }
            }

            // Save variation data
            if (isset($_POST['variations']) && is_array($_POST['variations'])) {
                foreach ($_POST['variations'] as $variation_data) {
                    $variation_id = isset($variation_data['id']) ? intval($variation_data['id']) : 0;
                    if (!$variation_id) {
                        continue;
                    }

                    $variation = wc_get_product($variation_id);
                    if (!$variation) {
                        continue;
                    }

                    // Save default variation data
                    if (isset($variation_data['default'])) {
                        $default = $variation_data['default'];
                        if (isset($default['stock_quantity'])) {
                            $variation->set_stock_quantity(intval($default['stock_quantity']));
                        }
                        if (isset($default['regular_price'])) {
                            $variation->set_regular_price(wc_format_decimal($default['regular_price']));
                        }
                        if (isset($default['sale_price'])) {
                            $variation->set_sale_price(wc_format_decimal($default['sale_price']));
                        }
                        if (isset($default['backorders'])) {
                            $variation->set_backorders(sanitize_text_field($default['backorders']));
                        }
                        if (isset($default['purchase_price'])) {
                            update_post_meta($variation_id, '_purchase_price', wc_format_decimal($default['purchase_price']));
                        }
                        $variation->save();
                    }

                    // Save location data for variation
                    if (isset($variation_data['locations']) && is_array($variation_data['locations'])) {
                        foreach ($variation_data['locations'] as $location_data) {
                            $location_id = isset($location_data['id']) ? intval($location_data['id']) : 0;
                            if (!$location_id) {
                                continue;
                            }

                            if (isset($location_data['stock'])) {
                                update_post_meta($variation_id, '_location_stock_' . $location_id, intval($location_data['stock']));
                            }
                            if (isset($location_data['regular_price'])) {
                                update_post_meta($variation_id, '_location_regular_price_' . $location_id, wc_format_decimal($location_data['regular_price']));
                            }
                            if (isset($location_data['sale_price'])) {
                                update_post_meta($variation_id, '_location_sale_price_' . $location_id, wc_format_decimal($location_data['sale_price']));
                            }
                            if (isset($location_data['backorders'])) {
                                update_post_meta($variation_id, '_location_backorders_' . $location_id, sanitize_text_field($location_data['backorders']));
                            }
                        }
                    }
                }
            }

            wp_send_json_success(['message' => __('Product data saved successfully.', 'multi-location-product-and-inventory-management')]);
        }

        /**
         * Remove location from product via AJAX
         */
        public function cymulopimfwc_remove_product_location()
        {
            // Check nonce
            check_ajax_referer('location_wise_products_nonce', 'security');

            // Check permissions
            if (!current_user_can('manage_woocommerce')) {
                wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'multi-location-product-and-inventory-management')]);
            }

            // Get product ID and location ID
            $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
            $location_id = isset($_POST['location_id']) ? intval($_POST['location_id']) : 0;

            if (!$product_id || !$location_id) {
                wp_send_json_error(['message' => __('Invalid parameters.', 'multi-location-product-and-inventory-management')]);
            }

            // Remove location from product
            wp_remove_object_terms($product_id, $location_id, 'mulopimfwc_store_location');

            // Delete location-specific meta
            delete_post_meta($product_id, '_location_stock_' . $location_id);
            delete_post_meta($product_id, '_location_regular_price_' . $location_id);
            delete_post_meta($product_id, '_location_sale_price_' . $location_id);
            delete_post_meta($product_id, '_location_backorders_' . $location_id);
            delete_post_meta($product_id, '_location_disabled_' . $location_id);

            // If variable product, remove from variations too
            $product = wc_get_product($product_id);
            if ($product && $product->get_type() === 'variable') {
                $variation_ids = $product->get_children();
                foreach ($variation_ids as $variation_id) {
                    delete_post_meta($variation_id, '_location_stock_' . $location_id);
                    delete_post_meta($variation_id, '_location_regular_price_' . $location_id);
                    delete_post_meta($variation_id, '_location_sale_price_' . $location_id);
                    delete_post_meta($variation_id, '_location_backorders_' . $location_id);
                }
            }

            wp_send_json_success(['message' => __('Location removed successfully.', 'multi-location-product-and-inventory-management')]);
        }

        /**
         * Enqueue admin scripts
         */
        public function cymulopimfwc_enqueue_admin_scripts($hook)
        {
            // Only on product location page
            // if ($hook !== 'multi-location-product-and-inventory-management-settings') {
            //     return;
            // }

            wp_enqueue_script(
                'mulopimfwc-multi-location-product-and-inventory-managements-admin',
                plugin_dir_url(__FILE__) . 'assets/js/admin.js',
                ['jquery'],
                '1.0.6.20',
                true
            );

            wp_localize_script('mulopimfwc-multi-location-product-and-inventory-managements-admin', 'mulopimfwc_locationWiseProducts', [
                'nonce' => wp_create_nonce('location_wise_products_nonce'),
                'i18n' => [
                    'activate' => __('Activate', 'multi-location-product-and-inventory-management'),
                    'deactivate' => __('Deactivate', 'multi-location-product-and-inventory-management'),
                    'selectLocations' => __('Select Locations', 'multi-location-product-and-inventory-management'),
                    'saveLocations' => __('Save Locations', 'multi-location-product-and-inventory-management'),
                    'ajaxError' => __('An error occurred. Please try again.', 'multi-location-product-and-inventory-management'),
                ],
            ]);

            // Add modal styles
            wp_enqueue_style(
                'mulopimfwc-multi-location-product-and-inventory-managements-admin',
                plugin_dir_url(__FILE__) . 'assets/css/admin.css',
                [],
                '1.0.6.20'
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
            $location = isset($_COOKIE['mulopimfwc_store_location']) ? sanitize_text_field(wp_unslash($_COOKIE['mulopimfwc_store_location'])) : '';

            if (!empty($location)) {
                $order = wc_get_order($order_id);
                if ($order) {
                    $order->update_meta_data('_store_location', $location);
                    $order->save();
                }
            }
        }

        /**
         * Trigger social notifications for new orders.
         */
        public function handle_social_new_order_notification($order_id)
        {
            if (empty($order_id)) {
                return;
            }

            // Ensure the order has the location meta saved before notifying
            $this->save_location_to_order_meta($order_id);
            mulopimfwc_handle_social_new_order($order_id);
        }
        private function get_all_store_locations()
        {
            global $mulopimfwc_locations;

            if (is_wp_error($mulopimfwc_locations)) {
                return array();
            }

            return wp_list_pluck($mulopimfwc_locations, 'slug');
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
                $selected_location = isset($_GET['mulopimfwc_store_location']) ? sanitize_text_field(wp_unslash($_GET['mulopimfwc_store_location'])) : '';
            }

            // add nonce for security
            wp_nonce_field('store_location_filter_nonce', 'store_location_filter_nonce');

            echo '<select name="mulopimfwc_store_location" id="mulopimfwc_store_location">';
            echo '<option value="">' . esc_html__('All Locations', 'multi-location-product-and-inventory-management') . '</option>';

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
                $selected_location = isset($_GET['mulopimfwc_store_location']) ? sanitize_text_field(wp_unslash($_GET['mulopimfwc_store_location'])) : '';
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
            $old_links = $links;
            $links = [];
            $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=multi-location-product-and-inventory-management-settings')) . '">' . esc_html__('Settings', 'multi-location-product-and-inventory-management') . '</a>';
            $create_location = '<a href="' . esc_url(admin_url('edit-tags.php?taxonomy=mulopimfwc_store_location&post_type=product')) . '">' . esc_html__('Manage Locations', 'multi-location-product-and-inventory-management') . '</a>';
            $support_link = '<a href="' . esc_url("https://www.plugincy.com/support/") . '">' . esc_html__('Support', 'multi-location-product-and-inventory-management') . '</a>';
            $documentation_link = '<a href="' . esc_url("https://www.plugincy.com/documentation/multi-location-product-and-inventory-management/") . '">' . esc_html__('Documentation', 'multi-location-product-and-inventory-management') . '</a>';
            $our_plugins_link = '<a href="' . esc_url(admin_url('admin.php?page=plugincy-plugins')) . '">' . esc_html__('Our Plugins', 'multi-location-product-and-inventory-management') . '</a>';
            $links[] = $create_location;
            $links[] = $settings_link;
            $links[] = $support_link;
            $links[] = $documentation_link;
            $links[] = $our_plugins_link;
            $links = array_merge($links, $old_links);
            return array_filter($links);
        }

        /**
         * Show row meta on the plugin screen.
         *
         * @param mixed $links Plugin Row Meta.
         * @param mixed $file  Plugin Base file.
         *
         * @return array
         */
        public static function plugin_row_meta( $links, $file ) {
            if ( MULTI_LOCATION_PLUGIN_BASE_NAME !== $file ) {
                return $links;
            }

            $docs_url = 'https://plugincy.com/documentations/multi-location-product-and-inventory-management/';

            $community_support_url = 'https://wordpress.org/support/plugin/multi-location-product-and-inventory-management/';

            $support_url = 'https://www.plugincy.com/support/';

            $row_meta = array(
                'docs'    => '<a href="' . esc_url( $docs_url ) . '" aria-label="' . esc_attr__( 'View documentation', 'multi-location-product-and-inventory-management' ) . '">' . esc_html__( 'Docs', 'multi-location-product-and-inventory-management' ) . '</a>',
                'support' => '<a href="' . esc_url( $support_url ) . '" aria-label="' . esc_attr__( 'Support', 'multi-location-product-and-inventory-management' ) . '">' . esc_html__( 'Support', 'multi-location-product-and-inventory-management' ) . '</a>',
                'community_support' => '<a href="' . esc_url( $community_support_url ) . '" aria-label="' . esc_attr__( 'Visit community forums', 'multi-location-product-and-inventory-management' ) . '">' . esc_html__( 'Community support', 'multi-location-product-and-inventory-management' ) . '</a>',
            );

            return array_merge( $links, $row_meta );
        }

        public function enqueue_scripts()
        {
            global $mulopimfwc_options;

            $cookie_expiry_days = mulopimfwc_get_location_cookie_expiry_days();

            wp_enqueue_style('mulopimfwc_style', plugins_url('assets/css/style.css', __FILE__), [], '1.0.6.20');
            wp_enqueue_style('mulopimfwc_select2', plugins_url('assets/css/select2.min.css', __FILE__), [], '4.1.0');
            wp_enqueue_script('mulopimfwc_script', plugins_url('assets/js/script.js', __FILE__), ['jquery'], '1.0.6.20', true);
            wp_enqueue_script('mulopimfwc_select2', plugins_url('assets/js/select2.min.js', __FILE__), ['jquery'], '4.1.0', true);

            // Check if cart grouping is enabled
            $group_cart = isset($mulopimfwc_options['group_cart_by_location']) && mulopimfwc_premium_feature()
                ? $mulopimfwc_options['group_cart_by_location']
                : 'off';

            $location_require_selection = isset($mulopimfwc_options['location_require_selection']) ? $mulopimfwc_options['location_require_selection'] : 'off';
            $single_product_requires_location = false;

            if ($location_require_selection === 'on' && function_exists('is_product') && is_product()) {
                $product_id = get_queried_object_id();
                if ($product_id) {
                    $single_product_requires_location = $this->product_has_assigned_locations($product_id);
                }
            }

            // Always show location information when available
            // Check if location data exists
            if ($group_cart === 'on') {
                // Cart Block grouping (JS + CSS)
                wp_enqueue_script(
                    'mulopimfwc-cart-block-grouping',
                    plugins_url('assets/js/cart-block-grouping.js', __FILE__),
                    array('wp-hooks'), // important
                    '1.0.6.20',
                    true
                );

                wp_add_inline_style('mulopimfwc_style', '.wc-block-components-product-details__location{display:none !important;}');
            }


            // Check if allow location change in cart is enabled
            $options = get_option('mulopimfwc_display_options', []);
            $allow_location_change_in_cart = isset($options['allow_location_change_in_cart']) 
                ? $options['allow_location_change_in_cart'] 
                : 'off';
            
            if ($allow_location_change_in_cart === 'on' && (is_cart() || is_checkout())) {
                wp_enqueue_script(
                    'mulopimfwc-cart-location-change',
                    plugins_url('assets/js/cart-location-change.js', __FILE__),
                    ['jquery'],
                    '1.0.6.20',
                    true
                );
                
                wp_localize_script('mulopimfwc-cart-location-change', 'mulopimfwcCartLocationChange', [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('mulopimfwc_update_cart_location'),
                    'updatingText' => __('Updating...', 'multi-location-product-and-inventory-management'),
                    'errorText' => __('Error updating location. Please try again.', 'multi-location-product-and-inventory-management'),
                ]);
                
                // Add inline CSS
                wp_add_inline_style('mulopimfwc_style', '
                    .mulopimfwc-cart-location-selector {
                        margin-top: 8px;
                        margin-bottom: 8px;
                    }
                    .mulopimfwc-cart-location-selector label {
                        display: block;
                        font-size: 0.9em;
                        font-weight: 600;
                        margin-bottom: 4px;
                        color: #333;
                    }
                    .mulopimfwc-cart-location-select {
                        width: 100%;
                        max-width: 300px;
                        padding: 6px 10px;
                        border: 1px solid #ddd;
                        border-radius: 4px;
                        font-size: 0.9em;
                        background-color: #fff;
                        cursor: pointer;
                        transition: border-color 0.3s ease;
                    }
                    .mulopimfwc-cart-location-select:hover {
                        border-color: #999;
                    }
                    .mulopimfwc-cart-location-select:focus {
                        outline: none;
                        border-color: #0073aa;
                        box-shadow: 0 0 0 1px #0073aa;
                    }
                    .mulopimfwc-cart-location-selector.updating .mulopimfwc-cart-location-select {
                        opacity: 0.6;
                        cursor: not-allowed;
                    }
                ');
            }

            wp_localize_script('mulopimfwc_script', 'mulopimfwc_locationWiseProducts', [
                'cartHasProducts' => !WC()->cart->is_empty(),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'location_change_notification' => isset($mulopimfwc_options["location_change_notification"]) || (isset($mulopimfwc_options["location_switching_behavior"]) && $mulopimfwc_options["location_switching_behavior"] === "prompt_user"),
                'nonce' => wp_create_nonce('multi-location-product-and-inventory-management'),
                'cookie_expiry' => $cookie_expiry_days,
                'allow_mixed_in_cart' => isset($mulopimfwc_options['allow_mixed_location_cart']) && mulopimfwc_premium_feature()
                    ? $mulopimfwc_options['allow_mixed_location_cart']
                    : 'off',
                'allow_cart_update' => isset($mulopimfwc_options["location_switching_behavior"]) && $mulopimfwc_options["location_switching_behavior"] !== "preserve_cart",
                'location_notification_text' => isset($mulopimfwc_options['location_notification_text']) && mulopimfwc_premium_feature()
                    ? $mulopimfwc_options['location_notification_text']
                    : 'Do you want to change the store location? Your cart will be emptied.',
                'singleProductRequiresLocation' => $single_product_requires_location,
                'selectLocationPrompt' => __('Please select a store location before adding this product to your cart.', 'multi-location-product-and-inventory-management'),
                'locationSelectionEnforced' => ($location_require_selection === 'on')
            ]);

            wp_enqueue_style('leaflet', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.css', array(), '1.7.1');
            wp_enqueue_script('leaflet', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.js', array('jquery'), '1.7.1', true);
        }

        private function get_current_location()
        {
            return isset($_COOKIE['mulopimfwc_store_location']) ? sanitize_text_field(wp_unslash($_COOKIE['mulopimfwc_store_location'])) : '';
        }

        public function filter_shortcode_products($query_args)
        {
            $location = $this->get_current_location();
            global $mulopimfwc_options;
            $enable_all_locations = isset($mulopimfwc_options['enable_all_locations']) ? $mulopimfwc_options['enable_all_locations'] : 'off';
            if (!$location || $location === 'all-products' || $enable_all_locations === 'on') {
                return $query_args;
            }

            // if (!isset($query_args['tax_query'])) {
            //     $query_args['tax_query'] = [];
            // }

            $query_args['tax_query'][] = [
                'taxonomy' => 'mulopimfwc_store_location',
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
            if (!isset($_POST['mulopimfwc_shortcode_selector_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mulopimfwc_shortcode_selector_nonce'])), 'mulopimfwc_shortcode_selector')) {
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
            global $mulopimfwc_locations;
            $is_user_logged_in = is_user_logged_in();
            $current_user = wp_get_current_user();
            $is_admin_or_manager = in_array('administrator', $current_user->roles) || in_array('shop_manager', $current_user->roles);
            // mulopimfwc_display_options[show_popup_admin]
            $options = get_option('mulopimfwc_display_options', []);
            $show_popup_admin = isset($options['show_popup_admin']) ? $options['show_popup_admin'] : 'off';
            $selected_location = $this->get_current_location();
            $locationSelected = isset($_COOKIE['mulopimfwc_store_location']) ? sanitize_text_field(wp_unslash($_COOKIE['mulopimfwc_store_location'])) : '';
            $show_modal = $show_popup_admin === 'on' && empty($selected_location) ? true : (empty($selected_location) && !$is_admin_or_manager);

            $locations = $mulopimfwc_locations;



            include plugin_dir_path(__FILE__) . 'templates/modal.php';
        }

        public function location_selector_shortcode($atts)
        {
            global $mulopimfwc_locations;
            $atts = shortcode_atts([
                'title' => __('Select Store Location', 'multi-location-product-and-inventory-management'),
                'show_title' => 'on',
                'class' => '',
                'show_button' => '',
                'use_select2' => '',
                'herichical' => '',
                'show_count' => '',
                'enable_user_locations' => 'off', // New attribute
                'max_width' => '300',
                'multi_line' => 'off'
            ], $atts);

            $is_user_logged_in = is_user_logged_in();
            $current_user = wp_get_current_user();
            // mulopimfwc_display_options[show_all_products_admin]
            $options = get_option('mulopimfwc_display_options', []);
            $show_all_products_admin = isset($options['show_all_products_admin']) ? $options['show_all_products_admin'] : 'off';
            $is_admin_or_manager = in_array('administrator', $current_user->roles) || in_array('shop_manager', $current_user->roles);
            $selected_location = $this->get_current_location();

            $locations = $mulopimfwc_locations;

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


        private function get_display_options()
        {
            $options = get_option('mulopimfwc_display_options', []);
            return $options;
        }

        private function get_title_with_location($title, $product_id)
        {
            $locations = get_the_terms($product_id, 'mulopimfwc_store_location');
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
            $format = isset($options['display_format']) ? $options['display_format'] : 'none';

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
            global $mulopimfwc_options;
            $enable_all_locations = isset($mulopimfwc_options['enable_all_locations']) ? $mulopimfwc_options['enable_all_locations'] : 'off';

            if (!$location || $location === 'all-products') {
                return true;
            }

            $terms = wp_get_object_terms($product_id, 'mulopimfwc_store_location', ['fields' => 'slugs']);
            if (empty($terms) && $enable_all_locations === 'on') {
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
            global $mulopimfwc_options;
            $enable_all_locations = isset($mulopimfwc_options['enable_all_locations']) ? $mulopimfwc_options['enable_all_locations'] : 'off';

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
                'taxonomy' => 'mulopimfwc_store_location',
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

            // Ensure $cart_contents is an array
            if (!is_array($cart_contents)) {
                return $cart_contents;
            }

            foreach ($cart_contents as $key => $item) {
                // Ensure $item is an array and has product_id
                if (!is_array($item) || !isset($item['product_id'])) {
                    continue;
                }
                
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
                wc_setcookie('woocommerce_recently_viewed', $filtered_cookie, time() + mulopimfwc_get_location_cookie_expiry_seconds());
            }
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
            global $mulopimfwc_options;
            $enable_all_locations = isset($mulopimfwc_options['enable_all_locations']) ? $mulopimfwc_options['enable_all_locations'] : 'off';

            if ($enable_all_locations === 'on') {
                $tax_query[] = [
                    'relation' => 'OR',
                    [
                        'taxonomy' => 'mulopimfwc_store_location',
                        'field' => 'slug',
                        'terms' => $location,
                    ],
                    [
                        'taxonomy' => 'mulopimfwc_store_location',
                        'operator' => 'NOT EXISTS',
                    ],
                ];
            } else {
                $tax_query[] = [
                    'taxonomy' => 'mulopimfwc_store_location',
                    'field' => 'slug',
                    'terms' => $location,
                ];
            }
            $query->set('tax_query', $tax_query);

            // Add custom ordering based on product priority display setting
            $product_priority_display = isset($options['product_priority_display']) ? $options['product_priority_display'] : 'location_first';

            if ($product_priority_display !== 'mixed' && $enable_all_locations === 'on' && mulopimfwc_premium_feature()) {
                add_filter('posts_join', [$this, 'custom_product_join'], 10, 2);
                add_filter('posts_orderby', [$this, 'custom_product_orderby'], 10, 2);
            }
        }

        /**
         * Add custom JOIN clause for location-based ordering
         *
         * @param string $join The JOIN clause
         * @param WP_Query $query The WordPress query object
         * @return string Modified JOIN clause
         */
        public function custom_product_join($join, $query)
        {
            global $wpdb;

            // Only apply to main product queries
            if (!$query->is_main_query() || !is_post_type_archive('product') && !is_shop() && !is_product_taxonomy()) {
                return $join;
            }

            $location = $this->get_current_location();
            if (!$location) {
                return $join;
            }

            $term = get_term_by('slug', $location, 'mulopimfwc_store_location');
            if (!$term || is_wp_error($term)) {
                return $join;
            }

            $term_taxonomy_id = $wpdb->get_var($wpdb->prepare(
                "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND taxonomy = %s",
                $term->term_id,
                'mulopimfwc_store_location'
            ));

            if ($term_taxonomy_id) {
                $join .= " LEFT JOIN {$wpdb->term_relationships} AS location_tr 
                        ON ({$wpdb->posts}.ID = location_tr.object_id AND location_tr.term_taxonomy_id = " . intval($term_taxonomy_id) . ") ";
            }

            // Remove this filter after execution
            remove_filter('posts_join', [$this, 'custom_product_join'], 10);

            return $join;
        }

        /**
         * Add custom ORDER BY clause for location-based ordering
         *
         * @param string $orderby The ORDER BY clause
         * @param WP_Query $query The WordPress query object
         * @return string Modified ORDER BY clause
         */
        public function custom_product_orderby($orderby, $query)
        {
            global $wpdb;

            // Only apply to main product queries
            if (!$query->is_main_query() || !is_post_type_archive('product') && !is_shop() && !is_product_taxonomy()) {
                return $orderby;
            }

            $options = $this->get_display_options();
            $product_priority_display = isset($options['product_priority_display']) ? $options['product_priority_display'] : 'location_first';

            if ($product_priority_display === 'location_first') {
                $priority_value_for_location = 1;
                $priority_value_for_global = 2;
            } else { // global_first
                $priority_value_for_location = 2;
                $priority_value_for_global = 1;
            }

            $custom_orderby = "CASE WHEN location_tr.object_id IS NOT NULL THEN {$priority_value_for_location} ELSE {$priority_value_for_global} END, ";

            // Remove this filter after execution
            remove_filter('posts_orderby', [$this, 'custom_product_orderby'], 10);

            return $custom_orderby . $orderby;
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
                'taxonomy' => 'mulopimfwc_store_location',
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
            wp_enqueue_style('mulopimfwc-custom-admin-style', plugin_dir_url(__FILE__) . 'assets/css/admin-style.css', array(), "1.0.6.20");
        }
    }

    function mulopimfwc_location_wise_products_init()
    {
        new mulopimfwc_Location_Wise_Products();
        
        // Initialize frontend product filter
        if (class_exists('Location_Wise_Products_Filter')) {
            new Location_Wise_Products_Filter();
        }
    }

    add_action('plugins_loaded', 'mulopimfwc_location_wise_products_init', 100);


    // Add this to the main plugin file after the class definition

    // AJAX handler for saving user location
add_action('wp_ajax_mulopimfwc_save_user_location', 'mulopimfwc_save_user_location');
add_action('wp_ajax_nopriv_mulopimfwc_save_user_location', 'mulopimfwc_save_user_location');

function mulopimfwc_save_user_location()
{
        // Check nonce
        check_ajax_referer('mulopimfwc_save_user_location', 'mulopimfwc_save_user_location_nonce');

        // Get form data
        $label = isset($_POST['label']) ? sanitize_text_field($_POST['label']) : '';
        $street = isset($_POST['street']) ? sanitize_text_field($_POST['street']) : '';
        $apartment = isset($_POST['apartment']) ? sanitize_text_field($_POST['apartment']) : '';
        $city = isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '';
        $state = isset($_POST['state']) ? sanitize_text_field($_POST['state']) : '';
        $postal = isset($_POST['postal']) ? sanitize_text_field($_POST['postal']) : '';
        $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';
        $note = isset($_POST['note']) ? sanitize_textarea_field($_POST['note']) : '';
        $lat = isset($_POST['lat']) ? floatval($_POST['lat']) : 0;
        $lng = isset($_POST['lng']) ? floatval($_POST['lng']) : 0;

        // Check if we're editing an existing location
        $location_id = isset($_POST['location_id']) && !empty($_POST['location_id']) ? sanitize_text_field($_POST['location_id']) : uniqid();

        // Prepare location data
        $location_data = array(
            'id' => $location_id,
            'label' => $label,
            'street' => $street,
            'apartment' => $apartment,
            'city' => $city,
            'state' => $state,
            'postal' => $postal,
            'country' => $country,
            'note' => $note,
            'lat' => $lat,
            'lng' => $lng,
            'address' => $street . ', ' . $city . ', ' . $state . ' ' . $postal . ', ' . $country
        );

        $is_logged_in = is_user_logged_in();

        if ($is_logged_in) {
            $user_id = get_current_user_id();
            $user_locations = get_user_meta($user_id, 'mulopimfwc_user_locations', true);
            if (!is_array($user_locations)) {
                $user_locations = array();
            }

            // If editing an existing location, find and update it
            $found = false;
            foreach ($user_locations as $key => $location) {
                if ($location['id'] === $location_id) {
                    $user_locations[$key] = $location_data;
                    $found = true;
                    break;
                }
            }

            // If not found, add new location
            if (!$found) {
                $user_locations[] = $location_data;
            }

            // Update user meta
            update_user_meta($user_id, 'mulopimfwc_user_locations', $user_locations);

            wp_send_json_success(array(
                'logged_in' => true,
                'location_id' => $location_id,
                'label' => $label,
                'address' => $location_data['address']
            ));
        } else {
            // For non-logged-in users, we can't save the location permanently.
            wc_setcookie('mulopimfwc_user_location', $location_data['address'], time() + mulopimfwc_get_location_cookie_expiry_seconds());
            wp_send_json_success(array(
                'logged_in' => false,
                'location_id' => $location_id,
                'label' => $label,
                'address' => $location_data['address']
            ));
        }
    }



    // AJAX handler for deleting user location
    add_action('wp_ajax_mulopimfwc_delete_user_location', 'mulopimfwc_delete_user_location');

    function mulopimfwc_delete_user_location()
    {

        // Get location ID
        $location_id = isset($_POST['location_id']) ? sanitize_text_field($_POST['location_id']) : '';

        if (empty($location_id)) {
            wp_send_json_error(array('message' => 'Invalid location ID'));
        }

        $user_id = get_current_user_id();
        $user_locations = get_user_meta($user_id, 'mulopimfwc_user_locations', true);

        if (!is_array($user_locations)) {
            wp_send_json_error(array('message' => 'No saved locations found'));
        }

        // Find and remove the location
        $found = false;
        foreach ($user_locations as $key => $location) {
            if ($location['id'] === $location_id) {
                unset($user_locations[$key]);
                $found = true;
                break;
            }
        }

        if (!$found) {
            wp_send_json_error(array('message' => 'Location not found'));
        }

        // Re-index array
        $user_locations = array_values($user_locations);

        // Update user meta
        update_user_meta($user_id, 'mulopimfwc_user_locations', $user_locations);

        wp_send_json_success(array('message' => 'Location deleted successfully'));
    }

    /**
     * Get per-location threshold with fallback to global settings.
     */
    function mulopimfwc_get_location_threshold($location_id, $type = 'low')
    {
        $meta_key = $type === 'out' ? 'out_of_stock_threshold' : 'low_stock_threshold';
        $value = get_term_meta($location_id, $meta_key, true);
        $value = ($value === '' || $value === null) ? null : (int) $value;

        if ($value === null) {
            $options = get_option('mulopimfwc_display_options', []);
            if ($type === 'out') {
                $value = isset($options['out_of_stock_threshold']) ? (int) $options['out_of_stock_threshold'] : 0;
            } else {
                $value = isset($options['low_stock_threshold']) ? (int) $options['low_stock_threshold'] : 5;
            }
        }

        return max(0, (int) $value);
    }

    /**
     * Get users assigned to a location slug.
     */
    function mulopimfwc_get_location_managers_for_slug($location_slug)
    {
        $args = [
            'role' => 'mulopimfwc_location_manager',
            'meta_query' => [
                [
                    'key' => 'mulopimfwc_assigned_locations',
                    'value' => $location_slug,
                    'compare' => 'LIKE',
                ],
            ],
            'fields' => 'all',
        ];

        $users = get_users($args);
        return is_array($users) ? $users : [];
    }

    /**
     * Get social notification settings merged with defaults.
     */
    function mulopimfwc_get_social_settings()
    {
        $options = get_option('mulopimfwc_display_options', []);
        $defaults = [
            'enabled' => 'off',
            'new_order' => 'on',
            'low_stock' => 'on',
            'out_of_stock' => 'on',
            'daily_digest' => 'off',
            'site_status' => 'off',
            'daily_digest_time' => '07:00',
            'admin_slack_webhook' => '',
            'admin_custom_webhook' => '',
            'admin_telegram_chat_id' => '',
            'telegram_bot_token' => '',
            'channels' => [],
            'payment_failed' => 'off',
            'order_refunded' => 'off',
            'order_cancelled' => 'off',
            'order_completed' => 'off',
            'high_value_order' => 'off',
            'high_value_threshold' => '500',
            'restocked' => 'off',
            'low_review_alert' => 'off',
            'low_review_threshold' => '3',
            'manager_change_alert' => 'off',
        ];

        if (isset($options['social_notifications']) && is_array($options['social_notifications'])) {
            return wp_parse_args($options['social_notifications'], $defaults);
        }

        return $defaults;
    }

    /**
     * Normalize social channels for a user.
     */
    function mulopimfwc_get_user_social_channels($user_id)
    {
        $channels = get_user_meta($user_id, 'mulopimfwc_social_channels', true);
        if (!is_array($channels)) {
            $channels = [];
        }

        if (empty($channels['slack_webhook'])) {
            $legacy_slack = get_user_meta($user_id, 'mulopimfwc_slack_webhook', true);
            if (!empty($legacy_slack)) {
                $channels['slack_webhook'] = $legacy_slack;
            }
        }

        return [
            'slack_webhook' => isset($channels['slack_webhook']) ? $channels['slack_webhook'] : '',
            'custom_webhook' => isset($channels['custom_webhook']) ? $channels['custom_webhook'] : '',
            'telegram_chat_id' => isset($channels['telegram_chat_id']) ? $channels['telegram_chat_id'] : '',
        ];
    }

    /**
     * Collect unique social channels for a location (managers + admin fallbacks).
     */
    function mulopimfwc_collect_social_channels($location_slug, $settings)
    {
        $channels = [];
        $add_channel = function ($channel) use (&$channels) {
            if (!isset($channel['type'])) {
                return;
            }
            $key = $channel['type'] . '|' . ($channel['type'] === 'telegram' ? ($channel['chat_id'] ?? '') : ($channel['url'] ?? ''));
            if (!empty($key)) {
                $channels[$key] = $channel;
            }
        };

        // Admin/global channels - from new multi-channel list first
        if (isset($settings['channels']) && is_array($settings['channels'])) {
            foreach ($settings['channels'] as $chan) {
                if (!is_array($chan)) {
                    continue;
                }
                $type = isset($chan['type']) ? sanitize_text_field($chan['type']) : '';
                $webhook = isset($chan['webhook']) ? esc_url_raw($chan['webhook']) : '';
                $chat_id = isset($chan['chat_id']) ? sanitize_text_field($chan['chat_id']) : '';
                $bot_token = isset($chan['bot_token']) ? sanitize_text_field($chan['bot_token']) : '';
                if (in_array($type, ['slack', 'teams', 'discord', 'custom'], true) && !empty($webhook)) {
                    $add_channel([
                        'type' => 'webhook',
                        'url' => $webhook,
                        'label' => isset($chan['label']) ? sanitize_text_field($chan['label']) : '',
                    ]);
                }
                if ($type === 'telegram' && !empty($chat_id)) {
                    $add_channel([
                        'type' => 'telegram',
                        'chat_id' => $chat_id,
                        'bot_token' => !empty($bot_token) ? $bot_token : (isset($settings['telegram_bot_token']) ? $settings['telegram_bot_token'] : ''),
                        'label' => isset($chan['label']) ? sanitize_text_field($chan['label']) : '',
                    ]);
                }
            }
        }

        // Admin/global legacy fields
        if (!empty($settings['admin_slack_webhook'])) {
            $add_channel([
                'type' => 'webhook',
                'url' => esc_url_raw($settings['admin_slack_webhook']),
            ]);
        }

        if (!empty($settings['admin_custom_webhook'])) {
            $add_channel([
                'type' => 'webhook',
                'url' => esc_url_raw($settings['admin_custom_webhook']),
            ]);
        }

        if (!empty($settings['admin_telegram_chat_id']) && !empty($settings['telegram_bot_token'])) {
            $add_channel([
                'type' => 'telegram',
                'chat_id' => sanitize_text_field($settings['admin_telegram_chat_id']),
                'bot_token' => sanitize_text_field($settings['telegram_bot_token']),
            ]);
        }

        // Location managers
        if (!empty($location_slug)) {
            $managers = mulopimfwc_get_location_managers_for_slug($location_slug);
            if (!empty($managers)) {
                foreach ($managers as $manager) {
                    $user_channels = mulopimfwc_get_user_social_channels($manager->ID);

                    if (!empty($user_channels['slack_webhook'])) {
                        $add_channel([
                            'type' => 'webhook',
                            'url' => esc_url_raw($user_channels['slack_webhook']),
                        ]);
                    }

                    if (!empty($user_channels['custom_webhook'])) {
                        $add_channel([
                            'type' => 'webhook',
                            'url' => esc_url_raw($user_channels['custom_webhook']),
                        ]);
                    }

                    if (!empty($user_channels['telegram_chat_id']) && !empty($settings['telegram_bot_token'])) {
                        $add_channel([
                            'type' => 'telegram',
                            'chat_id' => sanitize_text_field($user_channels['telegram_chat_id']),
                            'bot_token' => sanitize_text_field($settings['telegram_bot_token']),
                        ]);
                    }
                }
            }
        }

        return array_values($channels);
    }

    /**
     * Send a message to the provided social channels.
     */
    function mulopimfwc_send_social_message($title, $message, $channels, $settings, $link = '')
    {
        if (empty($channels)) {
            return;
        }

        $text_lines = array_filter([$title, $message, $link]);
        $text = wp_strip_all_tags(implode("\n", $text_lines));

        foreach ($channels as $channel) {
            if (!is_array($channel) || empty($channel['type'])) {
                continue;
            }

            if ($channel['type'] === 'webhook' && !empty($channel['url'])) {
                wp_remote_post(
                    esc_url_raw($channel['url']),
                    [
                        'timeout' => 8,
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => wp_json_encode(['text' => $text]),
                    ]
                );
            }

            if ($channel['type'] === 'telegram' && !empty($channel['chat_id']) && !empty($channel['bot_token'])) {
                $telegram_api = 'https://api.telegram.org/bot' . rawurlencode($channel['bot_token']) . '/sendMessage';
                wp_remote_post(
                    $telegram_api,
                    [
                        'timeout' => 8,
                        'body' => [
                            'chat_id' => $channel['chat_id'],
                            'text' => $text,
                            'parse_mode' => 'Markdown',
                            'disable_web_page_preview' => true,
                        ],
                    ]
                );
            }
        }
    }

    /**
     * Send low/out-of-stock notification to assigned location managers (email + optional Slack webhook per user).
     */
    function mulopimfwc_send_location_stock_alert($product_id, $location_term, $current_stock, $threshold, $type = 'low')
    {
        if (!$location_term || is_wp_error($location_term)) {
            return;
        }

        $managers = mulopimfwc_get_location_managers_for_slug($location_term->slug);
        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }

        $product_name = $product->get_name();
        $location_name = $location_term->name;
        $site_name = get_bloginfo('name');
        $type_label = ($type === 'out') ? __('Out of Stock', 'multi-location-product-and-inventory-management') : __('Low Stock', 'multi-location-product-and-inventory-management');
        $subject = sprintf('[%s] %s: %s @ %s', $site_name, $type_label, $product_name, $location_name);

        $admin_link = admin_url('post.php?post=' . $product_id . '&action=edit');
        $message = sprintf(
            "%s\r\n\r\nProduct: %s\r\nLocation: %s\r\nCurrent Stock: %d\r\nThreshold: %d\r\nProduct Admin: %s",
            $type_label,
            $product_name,
            $location_name,
            (int) $current_stock,
            (int) $threshold,
            $admin_link
        );

        if (!empty($managers)) {
            foreach ($managers as $manager) {
                if (!empty($manager->user_email)) {
                    wp_mail($manager->user_email, $subject, $message);
                }

                $webhook = get_user_meta($manager->ID, 'mulopimfwc_slack_webhook', true);
                if (!empty($webhook)) {
                    wp_remote_post(
                        esc_url_raw($webhook),
                        [
                            'timeout' => 5,
                            'headers' => ['Content-Type' => 'application/json'],
                            'body' => wp_json_encode([
                                'text' => sprintf(
                                    '*%s* %s @ %s' . "\n" . 'Stock: %d | Threshold: %d' . "\n" . '<%s|Edit product>',
                                    $site_name,
                                    $type_label . ': ' . $product_name,
                                    $location_name,
                                    (int) $current_stock,
                                    (int) $threshold,
                                    $admin_link
                                ),
                            ]),
                        ]
                    );
                }
            }
        }

        // Social notifications
        $social_settings = mulopimfwc_get_social_settings();
        $event_key = ($type === 'out') ? 'out_of_stock' : 'low_stock';

        if (
            isset($social_settings['enabled']) && $social_settings['enabled'] === 'on' &&
            isset($social_settings[$event_key]) && $social_settings[$event_key] === 'on'
        ) {
            $channels = mulopimfwc_collect_social_channels($location_term->slug, $social_settings);
            if (!empty($channels)) {
                $social_message = sprintf(
                    "%s @ %s\nStock: %d | Threshold: %d",
                    $product_name,
                    $location_name,
                    (int) $current_stock,
                    (int) $threshold
                );
                mulopimfwc_send_social_message($type_label, $social_message, $channels, $social_settings, $admin_link);
            }
        }
    }

    /**
     * Send social notification for a new order (location-aware).
     */
    function mulopimfwc_handle_social_new_order($order_id)
    {
        $settings = mulopimfwc_get_social_settings();
        if (
            empty($order_id) ||
            !isset($settings['enabled'], $settings['new_order']) ||
            $settings['enabled'] !== 'on' ||
            $settings['new_order'] !== 'on'
        ) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order instanceof WC_Order) {
            return;
        }

        $location_slug = (string) $order->get_meta('_store_location');
        $location_term = !empty($location_slug) ? get_term_by('slug', $location_slug, 'mulopimfwc_store_location') : null;
        $location_name = $location_term && !is_wp_error($location_term) ? $location_term->name : __('Unassigned location', 'multi-location-product-and-inventory-management');

        $items = [];
        foreach ($order->get_items() as $item) {
            $items[] = $item->get_name();
        }

        $order_total = wp_strip_all_tags($order->get_formatted_order_total());
        $status_label = wc_get_order_status_name($order->get_status());
        $customer_name = trim($order->get_formatted_billing_full_name());
        $title = sprintf(__('New order #%s at %s', 'multi-location-product-and-inventory-management'), $order->get_order_number(), $location_name);

        $lines = [];
        $lines[] = sprintf(__('Total: %s | Status: %s | Items: %d', 'multi-location-product-and-inventory-management'), $order_total, $status_label, $order->get_item_count());
        if (!empty($customer_name)) {
            $lines[] = sprintf(__('Customer: %s', 'multi-location-product-and-inventory-management'), $customer_name);
        }
        if (!empty($items)) {
            $lines[] = sprintf(__('Items: %s', 'multi-location-product-and-inventory-management'), implode(', ', array_slice($items, 0, 3)));
        }

        $channels = mulopimfwc_collect_social_channels($location_slug, $settings);
        if (!empty($channels)) {
            mulopimfwc_send_social_message($title, implode("\n", $lines), $channels, $settings, $order->get_edit_order_url());
        }

        // High value order alert
        if (
            isset($settings['high_value_order']) && $settings['high_value_order'] === 'on' &&
            isset($settings['high_value_threshold']) && floatval($order->get_total()) >= floatval($settings['high_value_threshold'])
        ) {
            mulopimfwc_send_social_message(
                __('High value order', 'multi-location-product-and-inventory-management'),
                sprintf(
                    __('Order #%s | Total: %s | Location: %s', 'multi-location-product-and-inventory-management'),
                    $order->get_order_number(),
                    $order_total,
                    $location_name
                ),
                $channels,
                $settings,
                $order->get_edit_order_url()
            );
        }
    }

    /**
     * Calculate the next daily digest timestamp based on store timezone.
     */
    function mulopimfwc_get_next_digest_timestamp($time_string)
    {
        $now = current_time('timestamp');
        $parts = explode(':', $time_string);
        $hour = isset($parts[0]) ? max(0, min(23, (int) $parts[0])) : 7;
        $minute = isset($parts[1]) ? max(0, min(59, (int) $parts[1])) : 0;

        $next = mktime($hour, $minute, 0, (int) wp_date('n', $now), (int) wp_date('j', $now), (int) wp_date('Y', $now));
        if ($next <= $now) {
            $next = strtotime('+1 day', $next);
        }

        return $next;
    }

    /**
     * Send the daily performance digest per location.
     */
    function mulopimfwc_send_daily_social_digest()
    {
        $settings = mulopimfwc_get_social_settings();
        if (
            !isset($settings['enabled'], $settings['daily_digest']) ||
            $settings['enabled'] !== 'on' ||
            $settings['daily_digest'] !== 'on'
        ) {
            return;
        }

        $now = current_time('timestamp');
        $start = strtotime('today', $now);
        $end = strtotime('tomorrow', $now);

        $orders = wc_get_orders([
            'limit' => -1,
            'status' => array_keys(wc_get_order_statuses()),
            'date_created' => [
                'after' => gmdate('Y-m-d H:i:s', $start),
                'before' => gmdate('Y-m-d H:i:s', $end),
                'inclusive' => true,
            ],
            'meta_query' => [
                [
                    'key' => '_store_location',
                    'compare' => 'EXISTS',
                ],
            ],
        ]);

        if (empty($orders)) {
            return;
        }

        $stats = [];
        foreach ($orders as $order) {
            if (!$order instanceof WC_Order) {
                continue;
            }

            $slug = (string) $order->get_meta('_store_location');
            if (empty($slug)) {
                $slug = 'unassigned';
            }

            if (!isset($stats[$slug])) {
                $term = $slug === 'unassigned' ? null : get_term_by('slug', $slug, 'mulopimfwc_store_location');
                $stats[$slug] = [
                    'name' => ($term && !is_wp_error($term)) ? $term->name : __('Unassigned location', 'multi-location-product-and-inventory-management'),
                    'orders' => 0,
                    'items' => 0,
                    'revenue' => 0,
                ];
            }

            $stats[$slug]['orders']++;
            $stats[$slug]['items'] += $order->get_item_count();
            $stats[$slug]['revenue'] += (float) $order->get_total();
        }

        // Per-location notifications for managers
        foreach ($stats as $slug => $data) {
            if ($slug === 'unassigned') {
                continue;
            }
            $channels = mulopimfwc_collect_social_channels($slug, $settings);
            if (empty($channels)) {
                continue;
            }

            $title = sprintf(__("Today's performance - %s", 'multi-location-product-and-inventory-management'), $data['name']);
            $message = sprintf(
                __('Orders: %d | Items: %d | Revenue: %s', 'multi-location-product-and-inventory-management'),
                $data['orders'],
                $data['items'],
                wp_strip_all_tags(wc_price($data['revenue']))
            );
            $orders_link = admin_url('edit.php?post_type=shop_order&filter_by_location=' . urlencode($slug));
            mulopimfwc_send_social_message($title, $message, $channels, $settings, $orders_link);
        }

        // Admin summary across all locations
        $admin_channels = mulopimfwc_collect_social_channels('', $settings);
        if (!empty($admin_channels)) {
            $lines = [];
            foreach ($stats as $data) {
                $lines[] = sprintf(
                    '%s — %d %s, %d %s, %s',
                    $data['name'],
                    $data['orders'],
                    __('orders', 'multi-location-product-and-inventory-management'),
                    $data['items'],
                    __('items', 'multi-location-product-and-inventory-management'),
                    wp_strip_all_tags(wc_price($data['revenue']))
                );
            }

            mulopimfwc_send_social_message(
                __("Today's location performance", 'multi-location-product-and-inventory-management'),
                implode("\n", $lines),
                $admin_channels,
                $settings,
                admin_url('edit.php?post_type=shop_order')
            );
        }
    }

    function mulopimfwc_social_order_failed($order_id)
    {
        mulopimfwc_social_order_status_alert($order_id, 'payment_failed', __('Payment failed', 'multi-location-product-and-inventory-management'));
    }
    function mulopimfwc_social_order_refunded($order_id)
    {
        mulopimfwc_social_order_status_alert($order_id, 'order_refunded', __('Order refunded', 'multi-location-product-and-inventory-management'));
    }
    function mulopimfwc_social_order_cancelled($order_id)
    {
        mulopimfwc_social_order_status_alert($order_id, 'order_cancelled', __('Order cancelled', 'multi-location-product-and-inventory-management'));
    }
    function mulopimfwc_social_order_completed($order_id)
    {
        mulopimfwc_social_order_status_alert($order_id, 'order_completed', __('Order completed', 'multi-location-product-and-inventory-management'));
    }

    function mulopimfwc_social_order_status_alert($order_id, $setting_key, $title)
    {
        $settings = mulopimfwc_get_social_settings();
        if (
            empty($order_id) ||
            empty($setting_key) ||
            !isset($settings['enabled']) ||
            $settings['enabled'] !== 'on' ||
            !isset($settings[$setting_key]) ||
            $settings[$setting_key] !== 'on'
        ) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order instanceof WC_Order) {
            return;
        }

        $location_slug = (string) $order->get_meta('_store_location');
        $location_term = !empty($location_slug) ? get_term_by('slug', $location_slug, 'mulopimfwc_store_location') : null;
        $location_name = $location_term && !is_wp_error($location_term) ? $location_term->name : __('Unassigned location', 'multi-location-product-and-inventory-management');
        $order_total = wp_strip_all_tags($order->get_formatted_order_total());
        $status_label = wc_get_order_status_name($order->get_status());

        $lines = [
            sprintf(__('Order #%s', 'multi-location-product-and-inventory-management'), $order->get_order_number()),
            sprintf(__('Status: %s', 'multi-location-product-and-inventory-management'), $status_label),
            sprintf(__('Total: %s', 'multi-location-product-and-inventory-management'), $order_total),
            sprintf(__('Location: %s', 'multi-location-product-and-inventory-management'), $location_name),
        ];

        $channels = mulopimfwc_collect_social_channels($location_slug, $settings);
        if (!empty($channels)) {
            mulopimfwc_send_social_message($title, implode("\n", $lines), $channels, $settings, $order->get_edit_order_url());
        }
    }

    /**
     * Simple uptime monitor to announce down/up events.
     */
    function mulopimfwc_check_site_status_for_social()
    {
        $settings = mulopimfwc_get_social_settings();
        if (
            !isset($settings['enabled'], $settings['site_status']) ||
            $settings['enabled'] !== 'on' ||
            $settings['site_status'] !== 'on'
        ) {
            return;
        }

        $last_state = get_option('mulopimfwc_social_site_state', 'up');
        $response = wp_remote_get(home_url(), ['timeout' => 5]);
        $is_down = is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) >= 500;
        $current_state = $is_down ? 'down' : 'up';

        if ($current_state === $last_state) {
            return;
        }

        update_option('mulopimfwc_social_site_state', $current_state);

        $channels = mulopimfwc_collect_social_channels('', $settings);
        $site_name = get_bloginfo('name');

        if ($current_state === 'down') {
            mulopimfwc_send_social_message(
                __('Site down alert', 'multi-location-product-and-inventory-management'),
                sprintf(__('We could not reach %s at %s.', 'multi-location-product-and-inventory-management'), $site_name, home_url()),
                $channels,
                $settings,
                home_url()
            );
        } else {
            mulopimfwc_send_social_message(
                __('Site back online', 'multi-location-product-and-inventory-management'),
                sprintf(__('Monitoring shows %s is reachable again.', 'multi-location-product-and-inventory-management'), $site_name),
                $channels,
                $settings,
                home_url()
            );
        }
    }

    // Cron schedules & hooks for social notifications
    add_filter('cron_schedules', function ($schedules) {
        $schedules['mulopimfwc_five_minutes'] = [
            'interval' => 300,
            'display' => __('Every 5 Minutes', 'multi-location-product-and-inventory-management')
        ];
        return $schedules;
    });

    add_action('init', function () {
        $settings = mulopimfwc_get_social_settings();

        // Daily digest
        if (isset($settings['enabled'], $settings['daily_digest']) && $settings['enabled'] === 'on' && $settings['daily_digest'] === 'on') {
            if (!wp_next_scheduled('mulopimfwc_social_daily_digest')) {
                wp_schedule_event(mulopimfwc_get_next_digest_timestamp($settings['daily_digest_time']), 'daily', 'mulopimfwc_social_daily_digest');
            }
        } else {
            wp_clear_scheduled_hook('mulopimfwc_social_daily_digest');
        }

        // Site status monitor
        if (isset($settings['enabled'], $settings['site_status']) && $settings['enabled'] === 'on' && $settings['site_status'] === 'on') {
            if (!wp_next_scheduled('mulopimfwc_social_site_check')) {
                wp_schedule_event(time() + 60, 'mulopimfwc_five_minutes', 'mulopimfwc_social_site_check');
            }
        } else {
            wp_clear_scheduled_hook('mulopimfwc_social_site_check');
        }
    });

    add_action('mulopimfwc_social_daily_digest', 'mulopimfwc_send_daily_social_digest');
    add_action('mulopimfwc_social_site_check', 'mulopimfwc_check_site_status_for_social');
    add_action('wp_ajax_mulopimfwc_test_social_channel', 'mulopimfwc_test_social_channel');
    add_action('comment_post', 'mulopimfwc_social_low_review_alert', 20, 3);

    /**
     * AJAX: Test a social channel connection.
     */
    function mulopimfwc_test_social_channel()
    {
        check_ajax_referer('mulopimfwc_test_social_channel', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Permission denied', 'multi-location-product-and-inventory-management')]);
        }

        $type = isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : '';
        $webhook = isset($_POST['webhook']) ? esc_url_raw(wp_unslash($_POST['webhook'])) : '';
        $chat_id = isset($_POST['chat_id']) ? sanitize_text_field(wp_unslash($_POST['chat_id'])) : '';
        $bot_token = isset($_POST['bot_token']) ? sanitize_text_field(wp_unslash($_POST['bot_token'])) : '';
        $settings = mulopimfwc_get_social_settings();

        if (empty($type)) {
            wp_send_json_error(['message' => __('Select a platform to test.', 'multi-location-product-and-inventory-management')]);
        }

        if ($type === 'telegram' && empty($chat_id) && empty($webhook)) {
            wp_send_json_error(['message' => __('Telegram requires a chat ID (and bot token).', 'multi-location-product-and-inventory-management')]);
        }

        if ($type !== 'telegram' && empty($webhook)) {
            wp_send_json_error(['message' => __('Webhook URL is required for this platform.', 'multi-location-product-and-inventory-management')]);
        }

        $channel = [];
        if ($type === 'telegram') {
            $channel = [
                'type' => 'telegram',
                'chat_id' => $chat_id,
                'bot_token' => !empty($bot_token) ? $bot_token : (isset($settings['telegram_bot_token']) ? $settings['telegram_bot_token'] : ''),
            ];
        } else {
            $channel = [
                'type' => 'webhook',
                'url' => $webhook,
            ];
        }

        mulopimfwc_send_social_message(
            __('Test notification', 'multi-location-product-and-inventory-management'),
            __('This is a test from Multi Location Product & Inventory Management.', 'multi-location-product-and-inventory-management'),
            [$channel],
            $settings,
            home_url()
        );

        wp_send_json_success(['message' => __('Test sent (check your channel).', 'multi-location-product-and-inventory-management')]);
    }

    /**
     * Alert on low-rating product reviews.
     */
    function mulopimfwc_social_low_review_alert($comment_id, $comment_approved, $commentdata)
    {
        $settings = mulopimfwc_get_social_settings();
        if (
            !isset($settings['enabled'], $settings['low_review_alert']) ||
            $settings['enabled'] !== 'on' ||
            $settings['low_review_alert'] !== 'on'
        ) {
            return;
        }

        // Only run on approved reviews
        if ($comment_approved === 'spam' || intval($comment_approved) === 0) {
            return;
        }

        $comment = get_comment($comment_id);
        if (!$comment || 'review' !== get_comment_type($comment)) {
            return;
        }

        $post_id = $comment->comment_post_ID;
        if (get_post_type($post_id) !== 'product') {
            return;
        }

        $rating = get_comment_meta($comment_id, 'rating', true);
        $rating = $rating !== '' ? floatval($rating) : 0;
        $threshold = isset($settings['low_review_threshold']) ? floatval($settings['low_review_threshold']) : 3.0;

        if ($rating >= $threshold || $rating <= 0) {
            return;
        }

        $product = wc_get_product($post_id);
        $product_name = $product ? $product->get_name() : __('Product', 'multi-location-product-and-inventory-management');

        $channels = mulopimfwc_collect_social_channels('', $settings);
        if (!empty($channels)) {
            mulopimfwc_send_social_message(
                __('Low rating review', 'multi-location-product-and-inventory-management'),
                sprintf(
                    __('%s received a %s★ review: "%s"', 'multi-location-product-and-inventory-management'),
                    $product_name,
                    $rating,
                    wp_trim_words(wp_strip_all_tags($comment->comment_content), 20, '...')
                ),
                $channels,
                $settings,
                get_edit_post_link($post_id, '')
            );
        }
    }

    require_once plugin_dir_path(__FILE__) . 'includes/analytics.php';

    class mulopimfwc_analytics_main
    {
        private $analytics;

        public function __construct()
        {
            global $mulopimfwc_options;
            // Initialize analytics with the correct plugin file path
            $this->analytics = new mulopimfwc_anaylytics(
                '04',
                'https://plugincy.com/wp-json/product-analytics/v1',
                "1.0.6.20",
                'Multi Location Product & Inventory Management for WooCommerce',
                __FILE__ // Pass the main plugin file
            );

            add_action('admin_footer',  array($this->analytics, "add_deactivation_feedback_form"));

            // Plugin hooks
            add_action('init', array($this, 'init'));
            if (!isset($mulopimfwc_options["allow_data_share"]) || (isset($mulopimfwc_options["allow_data_share"])  && $mulopimfwc_options["allow_data_share"] === 'on')) {
                add_action('admin_init', array($this, 'admin_init'));
            }

            // Handle deactivation feedback AJAX
            add_action('wp_ajax_mulopimfwc_send_deactivation_feedback', array($this, 'handle_deactivation_feedback'));
        }

        public function init()
        {
            // Any initialization code
        }

        public function admin_init()
        {
            // Send analytics data on first activation or weekly
            $this->maybe_send_analytics();
        }

        private function maybe_send_analytics()
        {
            $last_sent = get_option('onepaquc_analytics_last_sent', 0);
            $week_ago = strtotime('-1 week');

            if ($last_sent < $week_ago) {
                $this->analytics->send_tracking_data();
                update_option('onepaquc_analytics_last_sent', time());
            }
        }

        public function handle_deactivation_feedback()
        {
            check_ajax_referer('deactivation_feedback', 'nonce');

            $reason = sanitize_text_field(wp_unslash($_POST['reason'] ?? ''));
            $this->analytics->send_deactivation_data($reason);

            wp_die();
        }
    }

    new mulopimfwc_analytics_main();
}

add_action('admin_enqueue_scripts', 'mulopimfwc_enqueue_admin_notifications_assets');

function mulopimfwc_enqueue_admin_notifications_assets()
{
    if (!current_user_can('manage_woocommerce')) {
        return;
    }

    wp_enqueue_script(
        'mulopimfwc-admin-notifications',
        plugin_dir_url(__FILE__) . 'assets/js/admin-notifications.js',
        ['jquery'],
        mulopimfwc_VERSION,
        true
    );

    $options = get_option('mulopimfwc_display_options', []);
    $defaults = [
        'realtime_enabled' => 'on',
        'floating_enabled' => 'on',
        'floating_position' => 'top-right',
        'floating_size' => 'comfy',
        'floating_duration' => '6000',
        'notification_template' => '[{event}] {message}',
        'pwa_enabled' => 'off',
        'show_admin_notice' => 'on',
        'poll_interval' => '30000',
        'notification_style' => 'modern',
        'sound_enabled' => 'off',
    ];
    $notification_settings = isset($options['notification_settings']) && is_array($options['notification_settings']) ? wp_parse_args($options['notification_settings'], $defaults) : $defaults;

    // Get plugin icon URL
    $icon_url = plugin_dir_url(__FILE__) . 'assets/images/icon-192x192.png';
    if (!file_exists(plugin_dir_path(__FILE__) . 'assets/images/icon-192x192.png')) {
        $icon_url = '';
    }

    wp_localize_script('mulopimfwc-admin-notifications', 'mulopimfwcNotificationConfig', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'adminurl' => admin_url(),
        'nonce' => wp_create_nonce('mulopimfwc_dashboard_realtime_nonce'),
        'realtime_enabled' => $notification_settings['realtime_enabled'],
        'floating_enabled' => $notification_settings['floating_enabled'],
        'floating_position' => $notification_settings['floating_position'],
        'floating_size' => isset($notification_settings['floating_size']) ? $notification_settings['floating_size'] : 'comfy',
        'floating_duration' => $notification_settings['floating_duration'],
        'notification_template' => $notification_settings['notification_template'],
        'pwa_enabled' => $notification_settings['pwa_enabled'],
        'show_admin_notice' => $notification_settings['show_admin_notice'],
        'pwa_sw_url' => plugin_dir_url(__FILE__) . 'assets/js/service-worker.js',
        'manifest_url' => plugin_dir_url(__FILE__) . 'assets/manifest.json',
        'pwa_icon' => $icon_url,
        'pwa_badge' => $icon_url,
        'poll_interval' => isset($notification_settings['poll_interval']) ? $notification_settings['poll_interval'] : '30000',
        'notification_style' => isset($notification_settings['notification_style']) ? $notification_settings['notification_style'] : 'modern',
        'sound_enabled' => isset($notification_settings['sound_enabled']) ? $notification_settings['sound_enabled'] : 'off',
    ]);
}

add_action('user_register', 'mulopimfwc_flag_manager_change');
add_action('profile_update', 'mulopimfwc_flag_manager_change');

function mulopimfwc_flag_manager_change($user_id)
{
    $user = get_userdata($user_id);
    if (!$user || empty($user->roles)) {
        return;
    }

    if (!in_array('mulopimfwc_location_manager', (array) $user->roles, true)) {
        return;
    }

    update_option('mulopimfwc_manager_change_pending', [
        'user_id' => $user_id,
        'timestamp' => current_time('timestamp', true),
    ]);
}






// Add this after your plugin initialization
add_action('plugins_loaded', 'mulopimfwc_init_updater');

function mulopimfwc_init_updater()
{
    if (class_exists('mulopimfwc_License_Manager')) {
        global $mulopimfwc_License_Manager;

        // Hook into WordPress update system
        add_filter('pre_set_site_transient_update_plugins', function ($transient) use ($mulopimfwc_License_Manager) {
            return mulopimfwc_check_for_plugin_updates($transient, $mulopimfwc_License_Manager);
        });

        add_filter('plugins_api', function ($result, $action, $args) use ($mulopimfwc_License_Manager) {
            return mulopimfwc_plugin_api_call($result, $action, $args, $mulopimfwc_License_Manager);
        }, 10, 3);

        add_action('upgrader_process_complete', function ($upgrader_object, $options) use ($mulopimfwc_License_Manager) {
            mulopimfwc_clear_cache_after_update($upgrader_object, $options, $mulopimfwc_License_Manager);
        }, 10, 2);

        add_action('admin_notices', array($mulopimfwc_License_Manager, 'show_license_notices'));
    }
}

function mulopimfwc_check_for_plugin_updates($transient, $license_manager)
{
    if (empty($transient->checked)) {
        return $transient;
    }

    if (!$license_manager->is_license_valid_cached()) {
        return $transient;
    }

    $plugin_file = plugin_basename(__FILE__); // This will automatically get the correct path
    $current_version = defined('mulopimfwc_VERSION') ? mulopimfwc_VERSION : '1.0.0';

    $update_info = $license_manager->check_for_updates();

    if ($update_info && version_compare($current_version, $update_info->new_version, '<')) {
        $transient->response[$plugin_file] = (object) array(
            'slug' => dirname($plugin_file),
            'plugin' => $plugin_file,
            'new_version' => $update_info->new_version,
            'url' => isset($update_info->homepage) ? $update_info->homepage : 'https://plugincy.com/',
            'package' => isset($update_info->download_link) ? $update_info->download_link : '',
            'tested' => /*isset($update_info->tested) ? $update_info->tested :*/ get_bloginfo('version'),
            'requires_php' => isset($update_info->requires_php) ? $update_info->requires_php : '7.0',
            'compatibility' => new stdClass()
        );
    }

    return $transient;
}


function mulopimfwc_plugin_api_call($result, $action, $args, $license_manager)
{
    if ($action !== 'plugin_information') {
        return $result;
    }

    $plugin_slug = dirname(plugin_basename(__FILE__)); // Auto-detect plugin slug

    if (!isset($args->slug) || $args->slug !== $plugin_slug) {
        return $result;
    }

    if (!$license_manager->is_license_valid_cached()) {
        return $result;
    }

    $license_key = get_option('mulopimfwc_license_key', '');
    $version_info = $license_manager->get_version_info($license_key);

    if ($version_info) {
        // Unserialize sections if they exist and are serialized
        $sections = array(
            'description' => 'Professional dynamic AJAX product filtering solution for WooCommerce.',
            'changelog' => 'Various improvements and bug fixes.'
        );

        if (isset($version_info->sections)) {
            if (is_string($version_info->sections)) {
                // If sections is a serialized string, unserialize it
                $unserialized_sections = @unserialize($version_info->sections);
                if ($unserialized_sections !== false && is_array($unserialized_sections)) {
                    $sections = array_merge($sections, $unserialized_sections);
                }
            } elseif (is_object($version_info->sections)) {
                // If sections is already an object, convert to array
                $sections = array_merge($sections, (array)$version_info->sections);
            } elseif (is_array($version_info->sections)) {
                // If sections is already an array
                $sections = array_merge($sections, $version_info->sections);
            }
        }

        // Handle banners
        $banners = array();
        if (isset($version_info->banners)) {
            if (is_string($version_info->banners)) {
                // If banners is a serialized string, unserialize it
                $unserialized_banners = @unserialize($version_info->banners);
                if ($unserialized_banners !== false && is_array($unserialized_banners)) {
                    $banners = $unserialized_banners;
                }
            } elseif (is_object($version_info->banners)) {
                // If banners is already an object, convert to array
                $banners = (array)$version_info->banners;
            } elseif (is_array($version_info->banners)) {
                // If banners is already an array
                $banners = $version_info->banners;
            }
        }

        // Handle screenshots - WordPress expects array of URLs with numeric keys
        $base_url = "https://ps.w.org/dynamic-ajax-product-filters-for-woocommerce/assets/";
        $default_screenshots = array(
            "1" => "Filters Demo 1",
            "2" => "Filters Demo 2",
            "3" => "Filters Demo 3",
            "4" => "Filters Demo 4 - Mobile View",
            "5" => "Filters Demo 5 - Mobile View",
            "6" => "Form Manage Settings",
            "7" => "Form Style Settings",
            "8" => "Plugin Advance Settings"
        );

        // Get captions from server or use defaults
        $screenshot_captions = $default_screenshots;
        if (isset($version_info->screenshots)) {
            if (is_string($version_info->screenshots)) {
                $unserialized_screenshots = @unserialize($version_info->screenshots);
                if ($unserialized_screenshots !== false && is_array($unserialized_screenshots)) {
                    $screenshot_captions = $unserialized_screenshots;
                }
            } elseif (is_object($version_info->screenshots)) {
                $screenshot_captions = (array)$version_info->screenshots;
            } elseif (is_array($version_info->screenshots)) {
                $screenshot_captions = $version_info->screenshots;
            }
        }

        // Also add screenshot captions to sections for better display
        if (!empty($screenshot_captions)) {
            $screenshot_section = "<ol>";
            foreach ($screenshot_captions as $number => $caption) {
                $screenshot_section .= "<li>";
                $screenshot_section .= "<a href='{$base_url}screenshot-{$number}.png' target='_blank'><img class='screenshots' src='{$base_url}screenshot-{$number}.png' alt='{$caption}'></a><p>{$caption}</p>";
                $screenshot_section .= "</li>";
            }
            $screenshot_section .= "</ol>";
            $sections['screenshots'] = $screenshot_section;
        }

        return (object) array(
            'name' => 'Dynamic AJAX Product Filters Pro',
            'slug' => $plugin_slug,
            'version' => $version_info->new_version,
            'author' => '<a href="https://plugincy.com">Plugincy</a>',
            'homepage' => 'https://plugincy.com/',
            'requires' => isset($version_info->requires) ? $version_info->requires : '5.0',
            'tested' => /*isset($version_info->tested) ? $version_info->tested :*/ get_bloginfo('version'),
            'requires_php' => isset($version_info->requires_php) ? $version_info->requires_php : '7.0',
            'contributors' => array(
                'plugincy' => array(
                    'profile' => 'https://profiles.wordpress.org/plugincy/',
                    'avatar' => 'https://secure.gravatar.com/avatar/ee0db1e8766d68a4bc66e91b4098310d9604ca7670ac9662c15915c517662b39',
                    'display_name' => 'Plugincy'
                )
            ),
            'sections' => $sections,
            'banners' => $banners,
            'download_link' => isset($version_info->download_link) ? $version_info->download_link : ''
        );
    }

    return $result;
}

function mulopimfwc_clear_cache_after_update($upgrader_object, $options, $license_manager)
{
    if ($options['action'] === 'update' && $options['type'] === 'plugin') {
        $plugin_file = plugin_basename(__FILE__);

        if (isset($options['plugins']) && in_array($plugin_file, $options['plugins'])) {
            $license_manager->clear_all_cache();
        }
    }
}


// Add this temporarily for testing - remove after testing
add_action('admin_init', function () {
    if (isset($_GET['force_check_updates']) && $_GET['force_check_updates'] === '1') {
        delete_site_transient('update_plugins');
        wp_redirect(admin_url('plugins.php'));
        exit;
    }
});
