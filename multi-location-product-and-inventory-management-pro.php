<?php

/**
 * Plugin Name: Multi Location Product & Inventory Management for WooCommerce Pro
 * Plugin URI: https://plugincy.com/multi-location-product-and-inventory-management
 * Description: Filter WooCommerce products by store locations with a location selector for customers.
 * Version: 1.1.4.11
 * Author: plugincy
 * Author URI: https://plugincy.com/
 * Text Domain: multi-location-product-and-inventory-management
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 4.0
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 */

if (!defined('ABSPATH')) exit;


// Serve service worker at the very beginning to avoid any redirects
// Check BEFORE WordPress loads to prevent any redirects
// SECURITY: Added path validation and plugin activation check
if (isset($_SERVER['REQUEST_URI'])) {
    $request_uri = filter_var($_SERVER['REQUEST_URI'], FILTER_SANITIZE_URL);
    if (preg_match('/\/mulopimfwc-sw\.js(\?.*)?$/i', $request_uri)) {
        // SECURITY: Verify plugin is actually active by checking if plugin file exists
        // This prevents serving service worker if plugin is deactivated
        $plugin_file = __DIR__ . '/multi-location-product-and-inventory-management-pro.php';
        if (!file_exists($plugin_file)) {
            http_response_code(404);
            exit;
        }
        
        // SECURITY: Validate file path to prevent path traversal
        $sw_file = __DIR__ . '/assets/js/service-worker.js';
        $plugin_dir = realpath(__DIR__);
        $sw_file_real = realpath($sw_file);
        
        // Ensure the service worker file is within the plugin directory
        if (!$sw_file_real || strpos($sw_file_real, $plugin_dir) !== 0) {
            http_response_code(404);
            exit;
        }
        
        // Disable any output buffering
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Set headers for service worker using native PHP
        http_response_code(200);
        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: /');
        header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('X-Content-Type-Options: nosniff');

        // Read and serve service worker file
        if (file_exists($sw_file) && is_readable($sw_file)) {
            readfile($sw_file);
        } else {
            http_response_code(404);
            header('Content-Type: application/javascript; charset=utf-8');
            echo '// Service worker file not found';
        }
        exit;
    }
}

if (!defined('MULTI_LOCATION_PLUGIN_URL')) {
    define('MULTI_LOCATION_PLUGIN_URL', plugin_dir_url(__FILE__));
}

if (!defined('MULTI_LOCATION_PLUGIN_BASE_NAME')) {
    define('MULTI_LOCATION_PLUGIN_BASE_NAME', plugin_basename(__FILE__));
}

if (!defined('mulopimfwc_VERSION')) {
    define("mulopimfwc_VERSION", "1.1.4.11");
}

if (!function_exists('mulopimfwc_get_location_cookie_expiry_days')) {
    /**
     * Return the configured number of days for location cookies (default: 30).
     *
     * @return int
     */
    function mulopimfwc_get_location_cookie_expiry_days(): int
    {
        if (function_exists('mulopimfwc_is_manual_assignment_strict_mode') && mulopimfwc_is_manual_assignment_strict_mode()) {
            return 30;
        }

        global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
        $value = isset($options['location_cookie_expiry']) && is_numeric($options['location_cookie_expiry'])
            ? (int)$options['location_cookie_expiry']
            : 30;

        if ($value < 1) {
            $value = 1;
        }

        return $value;
    }
}

if (!function_exists('mulopimfwc_get_branding_css')) {
    /**
     * Generate custom CSS based on branding settings
     *
     * @return string Custom CSS
     */
    function mulopimfwc_get_branding_css(): string
    {
        global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
        $css = ':root{';
        $css .= '--lwp-background:' . (isset($options['branding_popup_background']) && !empty($options['branding_popup_background']) ? sanitize_hex_color($options['branding_popup_background']) : '#ffffff') . ';';
        $css .= '--lwp-ink:' . (isset($options['branding_popup_text']) && !empty($options['branding_popup_text']) ? sanitize_hex_color($options['branding_popup_text']) : '#000000') . ';';
        $css .= '--lwp-primary:' . (isset($options['branding_primary_color']) && !empty($options['branding_primary_color']) ? sanitize_hex_color($options['branding_primary_color']) : '#667eea') . ';';
        $css .= '--lwp-secondary:' . (isset($options['branding_secondary_color']) && !empty($options['branding_secondary_color']) ? sanitize_hex_color($options['branding_secondary_color']) : '#764ba2') . ';';
        $css .= '--lwp-border:' . (isset($options['branding_border_color']) && !empty($options['branding_border_color']) ? sanitize_hex_color($options['branding_border_color']) : '#e7e0d6') . ';';
        $css .= '--lwp-button-text:' . (isset($options['branding_button_text']) && !empty($options['branding_button_text']) ? sanitize_hex_color($options['branding_button_text']) : '#ffffff') . ';';
        $css .= '--lwp-font-size:' . (isset($options['branding_font_size']) && !empty($options['branding_font_size']) ? sanitize_text_field($options['branding_font_size']) : '16px') . ';';
        $css .= '--lwp-font-family:' . (isset($options['branding_font_family']) && !empty($options['branding_font_family']) ? "'" . esc_attr($options['branding_font_family']) . "'" : "'Trebuchet MS', sans-serif") . ';';
        $css .= '--lwp-box-shadow:' . (isset($options['branding_box_shadow']) && !empty($options['branding_box_shadow']) ? sanitize_text_field($options['branding_box_shadow']) : '0 4px 12px rgba(0, 0, 0, 0.15)') . ';';
        $css .= '--lwp-border-radius:' . (isset($options['branding_border_radius']) && !empty($options['branding_border_radius']) ? sanitize_text_field($options['branding_border_radius']) : '8px') . ';';
        $css .= '}' . "\n";

        // Popup/Modal Colors
        if (!empty($options['branding_popup_background'])) {
            $css .= ".lwp-modern-popup { --lwp-modern-bg: {$options['branding_popup_background']}; }\n";
            $css .= ".lwp-classic-popup { --lwp-classic-bg: {$options['branding_popup_background']}; }\n";
            $css .= ".lwp-location-info-popup { --lwp-layout-bg: {$options['branding_popup_background']}; }\n";
        }

        if (!empty($options['branding_popup_text'])) {
            $css .= ".lwp-modern-popup { --lwp-modern-ink: {$options['branding_popup_text']}; }\n";
            $css .= ".lwp-classic-popup { --lwp-classic-ink: {$options['branding_popup_text']}; }\n";
            $css .= ".lwp-location-info-popup { --lwp-layout-ink: {$options['branding_popup_text']}; }\n";
        }

        if (!empty($options['branding_popup_overlay'])) {
            $css .= "#lwp-store-selector-modal.lwp-modern-popup,\n";
            $css .= ".lwp-store-selector-modal.lwp-modern-popup { background: {$options['branding_popup_overlay']}; }\n";
            $css .= "#lwp-store-selector-modal.lwp-classic-popup,\n";
            $css .= ".lwp-store-selector-modal.lwp-classic-popup { background: {$options['branding_popup_overlay']}; }\n";
            $css .= "#lwp-store-selector-modal.lwp-location-info-popup,\n";
            $css .= ".lwp-store-selector-modal.lwp-location-info-popup { background: {$options['branding_popup_overlay']}; }\n";
        }

        // Primary & Secondary Colors (replaces button background/hover)
        $primary_color = !empty($options['branding_primary_color']) ? $options['branding_primary_color'] : '#2a9d8f';
        $secondary_color = !empty($options['branding_secondary_color']) ? $options['branding_secondary_color'] : '#f4a261';
        $border_color = !empty($options['branding_border_color']) ? $options['branding_border_color'] : '#e7e0d6';

        // Button Colors (using primary/secondary)
        $css .= ".lwp-modern-button { background: {$primary_color} !important; }\n";
        $css .= "#lwp-store-selector-submit { background: {$primary_color} !important; }\n";
        $css .= ".lwp-classic-popup__footer #lwp-store-selector-submit { background: {$primary_color} !important; }\n";
        $css .= ".lwp-location-info-popup__footer #lwp-store-selector-submit { background: {$primary_color} !important; }\n";
        $css .= ".lwp-classic-popup__status[data-state='ready'] { color: {$primary_color} !important; }\n";
        $css .= ".lwp-classic-location.selected { background: {$primary_color}1c !important; border-color: {$primary_color} !important; }\n";

        if (!empty($options['branding_button_text'])) {
            $css .= ".lwp-modern-button { color: {$options['branding_button_text']} !important; }\n";
            $css .= "#lwp-store-selector-submit { color: {$options['branding_button_text']} !important; }\n";
            $css .= ".lwp-classic-popup__footer #lwp-store-selector-submit { color: {$options['branding_button_text']} !important; }\n";
            $css .= ".lwp-location-info-popup__footer #lwp-store-selector-submit { color: {$options['branding_button_text']} !important; }\n";
        }

        // Button Hover (using secondary color)
        $css .= ".lwp-modern-button:hover { background: {$secondary_color} !important; }\n";
        $css .= "#lwp-store-selector-submit:hover { background: {$secondary_color} !important; }\n";
        $css .= ".lwp-classic-popup__footer #lwp-store-selector-submit:hover { background: {$secondary_color} !important; }\n";
        $css .= ".lwp-location-info-popup__footer #lwp-store-selector-submit:hover { background: {$secondary_color} !important; }\n";

        // Gradient Background (using primary & secondary)
        $css .= ".lwp-modern-popup__hero { background: linear-gradient(135deg, {$primary_color} 0%, {$secondary_color} 100%) !important; }\n";

        // Border Color
        $css .= ".lwp-modern-popup { --lwp-modern-border: {$border_color}; }\n";
        $css .= ".lwp-classic-popup { --lwp-classic-border: {$border_color}; }\n";
        $css .= ".lwp-modern-search-row input { border-color: {$border_color} !important; }\n";
        $css .= ".lwp-classic-popup__search input { border-color: {$border_color} !important; }\n";
        $css .= ".lwp-classic-popup__list { border-color: {$border_color} !important; }\n";
        $css .= ".lwp-modern-ghost-button { border-color: {$border_color} !important; }\n";
        
        // Card Logo (using primary color)
        $css .= ".lwp-modern-card__logo { color: {$primary_color} !important; }\n";
        
        // Location Select (using primary color)
        $css .= ".lwp-modern-popup:not(.lwp-modern-popup--simple) .lwp-modern-location-select { background: {$primary_color} !important; }\n";
        
        // Classic Location Logo (using primary color)
        $css .= ".lwp-classic-location__logo { background: {$primary_color}47 !important; color: {$primary_color} !important; }\n";
        
        // Classic Location Distance (using primary color)
        $css .= ".lwp-classic-location__distance.has-distance { color: {$primary_color} !important; }\n";
        
        // Classic Select (using primary color)
        $css .= ".lwp-classic-select { color: {$primary_color} !important; }\n";
        
        // Directions Button (using primary color)
        $css .= ".mulopimfwc-btn-directions { background: {$primary_color} !important; color: #fff !important; }\n";
        
        // Overlay Item SVG (using primary color)
        $css .= ".mulopimfwc-overlay-item svg { color: {$primary_color} !important; }\n";
        
        // Compact Item SVG and Links (using primary color)
        $css .= ".mulopimfwc-compact-item svg { color: {$primary_color} !important; }\n";
        $css .= ".mulopimfwc-compact-item a { color: {$primary_color} !important; }\n";
        
        // Primary Button (using primary color)
        $css .= ".mulopimfwc-btn-primary { background: {$primary_color} !important; color: #fff !important; }\n";
        
        // Status Badge Colors (using secondary color variations)
        if (function_exists('mulopimfwc_hex_to_rgb') && function_exists('mulopimfwc_rgb_to_hex') && function_exists('mulopimfwc_darken_color')) {
            $secondary_rgb = mulopimfwc_hex_to_rgb($secondary_color);
            if (is_array($secondary_rgb) && count($secondary_rgb) === 3) {
                $secondary_light = mulopimfwc_rgb_to_hex($secondary_rgb[0], $secondary_rgb[1], $secondary_rgb[2], 0.15);
                $secondary_dark = mulopimfwc_darken_color($secondary_color, 20);
                $css .= ".lwp-modern-status-badge--open { background: {$secondary_light} !important; color: {$secondary_dark} !important; }\n";
                
                // Classic Location Status Open (using secondary color)
                $css .= ".lwp-classic-location__status--open { background: {$secondary_light} !important; color: {$secondary_dark} !important; }\n";
                
                // Status Open (using secondary color)
                $css .= ".lwp-modern-popup .mulopimfwc-status-open, .lwp-classic-popup .mulopimfwc-status-open, .lwp-location-info-popup .mulopimfwc-status-open { background: {$secondary_light} !important; color: {$secondary_dark} !important; }\n";
                
                // Classic Location Hover (using secondary color with opacity)
                $secondary_hover_bg = mulopimfwc_rgb_to_hex($secondary_rgb[0], $secondary_rgb[1], $secondary_rgb[2], 0.05);
                $css .= ".lwp-classic-location:hover { background: {$secondary_hover_bg} !important; }\n";
            }
            
            $primary_rgb = mulopimfwc_hex_to_rgb($primary_color);
            if (is_array($primary_rgb) && count($primary_rgb) === 3) {
                $primary_dark = mulopimfwc_darken_color($primary_color, 20);
                
                // Featured Card (using primary color)
                $css .= ".lwp-modern-card--featured { border-color: rgba({$primary_rgb[0]}, {$primary_rgb[1]}, {$primary_rgb[2]}, 0.4) !important; box-shadow: 0 16px 34px rgba({$primary_rgb[0]}, {$primary_rgb[1]}, {$primary_rgb[2]}, 0.18) !important; }\n";
                
                // Status Ready (using primary dark)
                $css .= ".lwp-modern-status[data-state=\"ready\"] { color: {$primary_dark} !important; }\n";
            }
            
            // Logo Placeholder (using border color with opacity)
            $border_rgb = mulopimfwc_hex_to_rgb($border_color);
            if (is_array($border_rgb) && count($border_rgb) === 3) {
                $logo_bg = mulopimfwc_rgb_to_hex($border_rgb[0], $border_rgb[1], $border_rgb[2], 0.1);
                $css .= ".lwp-modern-card__logo--placeholder { background: {$logo_bg} !important; }\n";
                
                // Chip Background (using border color with opacity)
                $chip_bg = mulopimfwc_rgb_to_hex($border_rgb[0], $border_rgb[1], $border_rgb[2], 0.15);
                $css .= ".lwp-modern-chip { background: {$chip_bg} !important; }\n";
            }
            
            // Classic Popup Status Approximate (using secondary color dark)
            $secondary_dark = mulopimfwc_darken_color($secondary_color, 30);
            if ($secondary_dark) {
                $css .= ".lwp-classic-popup__status[data-state=\"approximate\"] { color: {$secondary_dark} !important; }\n";
            }
        }
        
        // Location Info Popup Tab Active/Selected (using secondary color)
        $css .= ".lwp-location-info-popup .mulopimfwc-tab-item.active { border-left-color: {$secondary_color} !important; }\n";
        $css .= ".lwp-location-info-popup .mulopimfwc-tab-item.is-selected { box-shadow: inset 4px 0 0 {$secondary_color} !important; }\n";
        $css .= ".lwp-location-info-popup .mulopimfwc-tab-item::before { background: {$secondary_color} !important; }\n";

        // Typography
        if (!empty($options['branding_font_size'])) {
            $css .= ".lwp-modern-popup__body,\n";
            $css .= ".lwp-classic-popup__panel,\n";
            $css .= ".lwp-location-info-popup__panel { font-size: {$options['branding_font_size']}; }\n";
        }

        if (!empty($options['branding_font_family'])) {
            $font_family = esc_attr($options['branding_font_family']);
            $css .= ".lwp-modern-popup { --lwp-modern-font: '{$font_family}', 'Trebuchet MS', sans-serif; }\n";
            $css .= ".lwp-classic-popup { --lwp-classic-font-body: '{$font_family}', 'Trebuchet MS', sans-serif; }\n";
            $css .= ".lwp-location-info-popup { --lwp-layout-font: '{$font_family}', 'Trebuchet MS', sans-serif; }\n";
        }

        // Box Shadow
        if (!empty($options['branding_box_shadow'])) {
            $css .= ".lwp-modern-popup__panel { box-shadow: {$options['branding_box_shadow']} !important; }\n";
            $css .= ".lwp-classic-popup__panel { box-shadow: {$options['branding_box_shadow']} !important; }\n";
            $css .= ".lwp-location-info-popup__panel { box-shadow: {$options['branding_box_shadow']} !important; }\n";
        }

        // Border Radius
        if (!empty($options['branding_border_radius'])) {
            $css .= ".lwp-modern-popup__panel { border-radius: {$options['branding_border_radius']} !important; }\n";
            $css .= ".lwp-classic-popup__panel { border-radius: {$options['branding_border_radius']} !important; }\n";
            $css .= ".lwp-location-info-popup__panel { border-radius: {$options['branding_border_radius']} !important; }\n";
            $css .= ".lwp-modern-button { border-radius: {$options['branding_border_radius']} !important; }\n";
            $css .= "#lwp-store-selector-submit { border-radius: {$options['branding_border_radius']} !important; }\n";
        }

        return $css;
    }
}

if (!function_exists('mulopimfwc_get_effective_order_assignment_method')) {
    /**
     * Resolve effective order assignment method.
     *
     * When location-wise currency is enabled, manual assignment is forced to proximity-based.
     *
     * @param array|null $options Optional settings array.
     * @return string
     */
    function mulopimfwc_get_effective_order_assignment_method($options = null): string
    {
        if (!is_array($options)) {
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
        }

        $assignment_method = isset($options['order_assignment_method'])
            ? sanitize_key((string) $options['order_assignment_method'])
            : 'customer_selection';
        $allowed_methods = ['customer_selection', 'inventory_based', 'proximity_based', 'manual'];
        if (!in_array($assignment_method, $allowed_methods, true)) {
            $assignment_method = 'customer_selection';
        }

        $location_wise_currency_enabled = function_exists('mulopimfwc_is_location_wise_currency_enabled')
            ? mulopimfwc_is_location_wise_currency_enabled($options)
            : (
                isset($options['enable_location_price'], $options['location_wise_currency']) &&
                $options['enable_location_price'] === 'on' &&
                $options['location_wise_currency'] === 'on'
            );

        if ($location_wise_currency_enabled && $assignment_method === 'manual') {
            return 'proximity_based';
        }

        return $assignment_method;
    }
}

if (!function_exists('mulopimfwc_is_manual_assignment_mode')) {
    /**
     * Check whether effective order assignment is manual.
     *
     * @param array|null $options Optional settings array.
     * @return bool
     */
    function mulopimfwc_is_manual_assignment_mode($options = null): bool
    {
        return mulopimfwc_get_effective_order_assignment_method($options) === 'manual';
    }
}

if (!function_exists('mulopimfwc_is_manual_optional_location_selection_enabled')) {
    /**
     * Check whether optional location selection is enabled for manual, inventory-based, or proximity-based assignment.
     *
     * @param array|null $options Optional options array.
     * @return bool
     */
    function mulopimfwc_is_manual_optional_location_selection_enabled($options = null): bool
    {
        if (!is_array($options)) {
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
        }

        $assignment_method = mulopimfwc_get_effective_order_assignment_method($options);

        if (!in_array($assignment_method, ['manual', 'inventory_based', 'proximity_based'], true)) {
            return false;
        }

        return isset($options['manual_optional_location_selection'])
            && $options['manual_optional_location_selection'] === 'on';
    }
}

if (!function_exists('mulopimfwc_is_manual_assignment_strict_mode')) {
    /**
     * Check whether assignment mode should disable location-based features.
     *
     * @param array|null $options Optional options array.
     * @return bool
     */
    function mulopimfwc_is_manual_assignment_strict_mode($options = null): bool
    {
        if (!is_array($options)) {
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
        }

        $assignment_method = mulopimfwc_get_effective_order_assignment_method($options);

        if (!in_array($assignment_method, ['manual', 'inventory_based', 'proximity_based'], true)) {
            return false;
        }

        return empty($options['manual_optional_location_selection'])
            || $options['manual_optional_location_selection'] !== 'on';
    }
}

if (!function_exists('mulopimfwc_is_location_wise_currency_enabled')) {
    /**
     * Check whether location-wise currency is enabled.
     *
     * Location-wise currency depends on location-wise pricing so this helper
     * returns false unless both settings are enabled.
     *
     * @param array|null $options Optional options array.
     * @return bool
     */
    function mulopimfwc_is_location_wise_currency_enabled($options = null): bool
    {
        if (!is_array($options)) {
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
        }

        if (!isset($options['enable_location_price']) || $options['enable_location_price'] !== 'on') {
            return false;
        }

        return isset($options['location_wise_currency']) && $options['location_wise_currency'] === 'on';
    }
}

if (!function_exists('mulopimfwc_is_mixed_location_cart_enabled')) {
    /**
     * Check whether mixed-location cart is enabled, respecting manual mode.
     *
     * @param array|null $options Optional options array.
     * @return bool
     */
    function mulopimfwc_is_mixed_location_cart_enabled($options = null): bool
    {
        if (!is_array($options)) {
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
        }

        if (mulopimfwc_is_manual_assignment_strict_mode($options)) {
            return false;
        }

        if (!mulopimfwc_premium_feature()) {
            return false;
        }

        if (mulopimfwc_is_location_wise_currency_enabled($options)) {
            return false;
        }

        return isset($options['allow_mixed_location_cart']) && $options['allow_mixed_location_cart'] === 'on';
    }
}

if (!function_exists('mulopimfwc_is_location_change_in_cart_enabled')) {
    /**
     * Check whether location change in cart is enabled, respecting manual mode and location-wise currency.
     *
     * @param array|null $options Optional options array.
     * @return bool
     */
    function mulopimfwc_is_location_change_in_cart_enabled($options = null): bool
    {
        if (!is_array($options)) {
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
        }

        if (mulopimfwc_is_manual_assignment_strict_mode($options)) {
            return false;
        }

        if (mulopimfwc_is_location_wise_currency_enabled($options)) {
            return false;
        }

        return isset($options['allow_location_change_in_cart']) && $options['allow_location_change_in_cart'] === 'on';
    }
}

if (!function_exists('mulopimfwc_is_group_cart_enabled')) {
    /**
     * Check whether cart grouping is enabled, respecting manual mode.
     *
     * @param array|null $options Optional options array.
     * @return bool
     */
    function mulopimfwc_is_group_cart_enabled($options = null): bool
    {
        if (!is_array($options)) {
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
        }

        if (mulopimfwc_is_manual_assignment_strict_mode($options)) {
            return false;
        }

        if (!mulopimfwc_premium_feature()) {
            return false;
        }

        return isset($options['group_cart_by_location']) && $options['group_cart_by_location'] === 'on';
    }
}

if (!function_exists('mulopimfwc_is_split_order_enabled')) {
    /**
     * Check whether split order by location is enabled, respecting manual mode and mixed cart.
     *
     * @param array|null $options Optional options array.
     * @return bool
     */
    function mulopimfwc_is_split_order_enabled($options = null): bool
    {
        if (!is_array($options)) {
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
        }

        if (mulopimfwc_is_manual_assignment_strict_mode($options)) {
            return false;
        }

        if (!mulopimfwc_premium_feature()) {
            return false;
        }

        if (mulopimfwc_is_location_wise_currency_enabled($options)) {
            return false;
        }

        if (!isset($options['allow_mixed_location_cart']) || $options['allow_mixed_location_cart'] !== 'on') {
            return false;
        }

        return isset($options['split_order_by_location']) && $options['split_order_by_location'] === 'on';
    }
}

if (!function_exists('mulopimfwc_get_location_manager_frontend_assigned_locations')) {
    /**
     * Get assigned location slugs for location managers when frontend location restrictions apply.
     *
     * Returns null when restrictions are not active for the current user.
     *
     * @return array|null
     */
    function mulopimfwc_get_location_manager_frontend_assigned_locations()
    {
        if (!is_user_logged_in() || !class_exists('MULOPIMFWC_Location_Managers')) {
            return null;
        }

        $user = wp_get_current_user();
        if (empty($user) || empty($user->ID)) {
            return null;
        }

        if (!in_array('mulopimfwc_location_manager', (array) $user->roles, true)) {
            return null;
        }

        if (!MULOPIMFWC_Location_Managers::user_has_capability('location_specific_products_frontend', $user->ID)) {
            return null;
        }

        $assigned_locations = get_user_meta($user->ID, 'mulopimfwc_assigned_locations', true);
        if (!is_array($assigned_locations)) {
            return [];
        }

        $assigned_locations = array_map(function ($location_slug) {
            return sanitize_title(rawurldecode((string) $location_slug));
        }, $assigned_locations);

        $assigned_locations = array_values(array_filter($assigned_locations, function ($location_slug) {
            return is_string($location_slug) && $location_slug !== '';
        }));

        return array_values(array_unique($assigned_locations));
    }
}

if (!function_exists('mulopimfwc_get_location_manager_frontend_default_location')) {
    /**
     * Get manager-specific default location for frontend restriction mode.
     *
     * @param array|null $allowed_locations Optional assigned location slugs to validate against.
     * @return string
     */
    function mulopimfwc_get_location_manager_frontend_default_location($allowed_locations = null): string
    {
        if (!is_array($allowed_locations)) {
            $allowed_locations = mulopimfwc_get_location_manager_frontend_assigned_locations();
        }

        if (!is_array($allowed_locations) || empty($allowed_locations) || !is_user_logged_in()) {
            return '';
        }

        $user = wp_get_current_user();
        if (empty($user) || empty($user->ID)) {
            return '';
        }

        $default_location = get_user_meta($user->ID, 'mulopimfwc_manager_default_location', true);
        $default_location = sanitize_title(rawurldecode((string) $default_location));

        if ($default_location === '' || !in_array($default_location, $allowed_locations, true)) {
            return '';
        }

        return $default_location;
    }
}

if (!function_exists('mulopimfwc_get_default_location_value')) {
    /**
     * Get default location setting, disabled in manual mode.
     *
     * @param array|null $options Optional options array.
     * @return string
     */
    function mulopimfwc_get_default_location_value($options = null): string
    {
        if (!is_array($options)) {
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
        }

        if (mulopimfwc_is_manual_assignment_strict_mode($options)) {
            return '';
        }

        $default_location = isset($options['default_location']) ? trim((string) $options['default_location']) : '';
        $manager_locations = mulopimfwc_get_location_manager_frontend_assigned_locations();

        if (is_array($manager_locations)) {
            if (empty($manager_locations)) {
                return '';
            }

            $manager_default_location = mulopimfwc_get_location_manager_frontend_default_location($manager_locations);
            if ($manager_default_location !== '') {
                return $manager_default_location;
            }

            return (string) $manager_locations[0];
        }

        return $default_location;
    }
}

if (!function_exists('mulopimfwc_is_show_all_products_admin_enabled')) {
    /**
     * Check whether "show all products in admin" is enabled, respecting manual mode.
     *
     * @param array|null $options Optional options array.
     * @return bool
     */
    function mulopimfwc_is_show_all_products_admin_enabled($options = null): bool
    {
        if (!is_array($options)) {
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
        }

        if (mulopimfwc_is_manual_assignment_strict_mode($options)) {
            return false;
        }

        return isset($options['show_all_products_admin']) && $options['show_all_products_admin'] === 'on';
    }
}

if (!function_exists('mulopimfwc_is_location_shipping_enabled')) {
    /**
     * Check whether location-based shipping is enabled, respecting manual mode.
     *
     * @param array|null $options Optional options array.
     * @return bool
     */
    function mulopimfwc_is_location_shipping_enabled($options = null): bool
    {
        if (!is_array($options)) {
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
        }

        if (mulopimfwc_is_manual_assignment_strict_mode($options)) {
            return false;
        }

        if (!mulopimfwc_premium_feature()) {
            return false;
        }

        return isset($options['enable_location_shipping']) && $options['enable_location_shipping'] === 'on';
    }
}

if (!function_exists('mulopimfwc_is_location_payment_methods_enabled')) {
    /**
     * Check whether location-based payment methods are enabled, respecting manual mode.
     *
     * @param array|null $options Optional options array.
     * @return bool
     */
    function mulopimfwc_is_location_payment_methods_enabled($options = null): bool
    {
        if (!is_array($options)) {
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
        }

        if (mulopimfwc_is_manual_assignment_strict_mode($options)) {
            return false;
        }

        if (!mulopimfwc_premium_feature()) {
            return false;
        }

        return isset($options['enable_location_payment_methods']) && $options['enable_location_payment_methods'] === 'on';
    }
}

if (!function_exists('mulopimfwc_is_location_taxes_enabled')) {
    /**
     * Check whether location-based taxes are enabled, respecting manual mode.
     *
     * @param array|null $options Optional options array.
     * @return bool
     */
    function mulopimfwc_is_location_taxes_enabled($options = null): bool
    {
        if (!is_array($options)) {
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
        }

        if (mulopimfwc_is_manual_assignment_strict_mode($options)) {
            return false;
        }

        if (!mulopimfwc_premium_feature()) {
            return false;
        }

        return isset($options['enable_location_taxes']) && $options['enable_location_taxes'] === 'on';
    }
}

if (!function_exists('mulopimfwc_is_location_discounts_enabled')) {
    /**
     * Check whether location-based discounts are enabled, respecting manual mode.
     *
     * @param array|null $options Optional options array.
     * @return bool
     */
    function mulopimfwc_is_location_discounts_enabled($options = null): bool
    {
        if (!is_array($options)) {
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
        }

        if (mulopimfwc_is_manual_assignment_strict_mode($options)) {
            return false;
        }

        if (!mulopimfwc_premium_feature()) {
            return false;
        }

        return isset($options['enable_location_discounts']) && $options['enable_location_discounts'] === 'on';
    }
}

if (!function_exists('mulopimfwc_is_location_reviews_enabled')) {
    /**
     * Check whether location-specific reviews are enabled, respecting manual mode.
     *
     * @param array|null $options Optional options array.
     * @return bool
     */
    function mulopimfwc_is_location_reviews_enabled($options = null): bool
    {
        if (!is_array($options)) {
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
        }

        if (mulopimfwc_is_manual_assignment_strict_mode($options)) {
            return false;
        }

        if (!mulopimfwc_premium_feature()) {
            return false;
        }

        return isset($options['location_specific_reviews']) && $options['location_specific_reviews'] === 'on';
    }
}

if (!function_exists('mulopimfwc_is_all_locations_enabled')) {
    /**
     * Check whether "enable all locations" is enabled, respecting manual mode.
     *
     * @param array|null $options Optional options array.
     * @return bool
     */
    function mulopimfwc_is_all_locations_enabled($options = null): bool
    {
        if (!is_array($options)) {
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
        }

        if (mulopimfwc_is_manual_assignment_strict_mode($options)) {
            return false;
        }

        return isset($options['enable_all_locations']) && $options['enable_all_locations'] === 'on';
    }
}

if (!function_exists('mulopimfwc_get_strict_filtering_value')) {
    /**
     * Get strict filtering mode, disabled in manual mode.
     *
     * @param array|null $options Optional options array.
     * @return string
     */
    function mulopimfwc_get_strict_filtering_value($options = null): string
    {
        if (!is_array($options)) {
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
        }

        if (mulopimfwc_is_manual_assignment_strict_mode($options)) {
            return 'disabled';
        }

        $value = isset($options['strict_filtering']) ? $options['strict_filtering'] : 'enabled';

        return $value === 'disabled' ? 'disabled' : 'enabled';
    }
}

if (!function_exists('mulopimfwc_get_product_priority_display_value')) {
    /**
     * Get product priority display setting, disabled in manual mode.
     *
     * @param array|null $options Optional options array.
     * @return string
     */
    function mulopimfwc_get_product_priority_display_value($options = null): string
    {
        if (!is_array($options)) {
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
        }

        if (mulopimfwc_is_manual_assignment_strict_mode($options)) {
            return 'mixed';
        }

        $value = isset($options['product_priority_display']) ? $options['product_priority_display'] : 'location_first';
        $valid = ['location_first', 'global_first', 'mixed'];

        return in_array($value, $valid, true) ? $value : 'location_first';
    }
}

if (!function_exists('mulopimfwc_get_single_product_unavailable_behavior')) {
    /**
     * Get single product unavailable behavior, defaulting to show_404 in manual mode.
     *
     * @param array|null $options Optional options array.
     * @return string
     */
    function mulopimfwc_get_single_product_unavailable_behavior($options = null): string
    {
        if (!is_array($options)) {
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
        }

        if (mulopimfwc_is_manual_assignment_strict_mode($options)) {
            return 'show_404';
        }

        $value = isset($options['single_product_unavailable_behavior'])
            ? $options['single_product_unavailable_behavior']
            : 'show_404';

        return in_array($value, ['show_404', 'show_message'], true) ? $value : 'show_404';
    }
}

if (!function_exists('mulopimfwc_is_location_information_enabled')) {
    /**
     * Check whether location-specific information is enabled, respecting manual mode.
     *
     * @param array|null $options Optional options array.
     * @return bool
     */
    function mulopimfwc_is_location_information_enabled($options = null): bool
    {
        if (!is_array($options)) {
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
        }

        if (mulopimfwc_is_manual_assignment_strict_mode($options)) {
            return false;
        }

        return isset($options['enable_location_information']) && $options['enable_location_information'] === 'on';
    }
}

/**
 * Helper function to convert hex to RGB
 */
if (!function_exists('mulopimfwc_hex_to_rgb')) {
    function mulopimfwc_hex_to_rgb($hex) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) == 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2))
        ];
    }
}

/**
 * Helper function to convert RGB to hex with opacity
 */
if (!function_exists('mulopimfwc_rgb_to_hex')) {
    function mulopimfwc_rgb_to_hex($r, $g, $b, $opacity = 1) {
        if ($opacity < 1) {
            return "rgba({$r}, {$g}, {$b}, {$opacity})";
        }
        return sprintf("#%02x%02x%02x", $r, $g, $b);
    }
}

/**
 * Helper function to darken a color
 */
if (!function_exists('mulopimfwc_darken_color')) {
    function mulopimfwc_darken_color($hex, $percent) {
        $rgb = mulopimfwc_hex_to_rgb($hex);
        $r = max(0, min(255, round($rgb[0] * (1 - $percent / 100))));
        $g = max(0, min(255, round($rgb[1] * (1 - $percent / 100))));
        $b = max(0, min(255, round($rgb[2] * (1 - $percent / 100))));
        return sprintf("#%02x%02x%02x", $r, $g, $b);
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

if (!function_exists('mulopimfwc_validate_location_slug')) {
    /**
     * FIXED: Validate location slug exists before use (Issue #13)
     * 
     * Validates that a location slug exists and returns the term object if valid.
     * Uses caching to improve performance for repeated lookups.
     *
     * @param string $location_slug The location slug to validate
     * @param bool $use_cache Whether to use cached results (default: true)
     * @return WP_Term|false Term object if valid, false otherwise
     */
    function mulopimfwc_validate_location_slug($location_slug, $use_cache = true)
    {
        if (empty($location_slug) || !is_string($location_slug)) {
            return false;
        }
        
        // Preserve original input for alias resolver fallback.
        $raw_location_slug = trim($location_slug);

        // Sanitize the slug for deterministic slug validation.
        $location_slug = sanitize_title($raw_location_slug);
        $cache_lookup_key = $location_slug !== ''
            ? $location_slug
            : strtolower($raw_location_slug);
        
        // Check cache first (Issue #12 - Performance optimization)
        if ($use_cache) {
            $cache_key = 'mulopimfwc_location_' . md5($cache_lookup_key);
            $cached = wp_cache_get($cache_key, 'mulopimfwc_locations');
            if ($cached !== false) {
                return $cached === 'invalid' ? false : $cached;
            }
        }
        
        // Validate location exists
        $term = false;
        if ($location_slug !== '') {
            $term = get_term_by('slug', $location_slug, 'mulopimfwc_store_location');
        }

        // Alias fallback: allow deterministic resolver matches for known synonyms.
        if ((!$term || is_wp_error($term)) && class_exists('MULOPIMFWC_Location_Resolver')) {
            $resolver = new MULOPIMFWC_Location_Resolver();
            $resolved = $resolver->resolve_store_location(
                ['query' => $raw_location_slug],
                [
                    'enable_geo' => false,
                    'allow_fuzzy' => false,
                    'allow_alias' => true,
                ]
            );

            if (
                is_array($resolved)
                && isset($resolved['status'], $resolved['slug'])
                && $resolved['status'] === 'matched'
                && !empty($resolved['slug'])
            ) {
                $resolved_slug = sanitize_title((string) $resolved['slug']);
                if ($resolved_slug !== '') {
                    $term = get_term_by('slug', $resolved_slug, 'mulopimfwc_store_location');
                }
            }
        }

        if (!$term || is_wp_error($term)) {
            // Cache invalid result to avoid repeated queries
            if ($use_cache) {
                wp_cache_set($cache_key, 'invalid', 'mulopimfwc_locations', HOUR_IN_SECONDS);
            }
            return false;
        }
        
        // Cache valid result (Issue #12 - Performance optimization)
        if ($use_cache) {
            wp_cache_set($cache_key, $term, 'mulopimfwc_locations', HOUR_IN_SECONDS);
        }
        
        return $term;
    }
}

if (!function_exists('mulopimfwc_get_location_cookie_name')) {
    /**
     * Get the cookie name used to store the selected location.
     *
     * @return string
     */
    function mulopimfwc_get_location_cookie_name(): string
    {
        return (string) apply_filters('mulopimfwc_location_cookie_name', 'mulopimfwc_store_location');
    }
}

if (!function_exists('mulopimfwc_is_location_currency_debug_enabled')) {
    /**
     * Check if location-wise currency debug logging is enabled.
     *
     * @param string $event
     * @param array $context
     * @return bool
     */
    function mulopimfwc_is_location_currency_debug_enabled(string $event = '', array $context = []): bool
    {
        $enabled = defined('MULOPIMFWC_LOCATION_CURRENCY_DEBUG') && (bool) MULOPIMFWC_LOCATION_CURRENCY_DEBUG;
        $enabled = (bool) apply_filters('mulopimfwc_location_currency_debug_log', $enabled, $event, $context);
        $enabled = (bool) apply_filters('mulopimfwc_currency_debug_log', $enabled, $event, $context);

        return $enabled && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;
    }
}

if (!function_exists('mulopimfwc_log_location_currency_debug')) {
    /**
     * Write structured debug logs for location-wise currency resolution.
     *
     * @param string $event
     * @param array $context
     * @return void
     */
    function mulopimfwc_log_location_currency_debug(string $event, array $context = []): void
    {
        if (!mulopimfwc_is_location_currency_debug_enabled($event, $context)) {
            return;
        }

        $sanitize = static function ($value) use (&$sanitize) {
            if (is_null($value) || is_scalar($value)) {
                return $value;
            }

            if (is_array($value)) {
                $normalized = [];
                foreach ($value as $key => $item) {
                    $normalized[(string) $key] = $sanitize($item);
                }
                return $normalized;
            }

            if (is_object($value)) {
                if ($value instanceof WP_Term) {
                    return [
                        'term_id' => (int) $value->term_id,
                        'slug' => (string) $value->slug,
                        'taxonomy' => (string) $value->taxonomy,
                    ];
                }

                if (method_exists($value, '__toString')) {
                    return (string) $value;
                }

                return get_class($value);
            }

            return (string) $value;
        };

        $payload = [
            'event' => $event,
            'context' => $sanitize($context),
            'request' => [
                'method' => isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '',
                'uri' => isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '',
                'is_ajax' => wp_doing_ajax(),
                'is_admin' => is_admin(),
            ],
        ];

        error_log('[MulopimFWC Currency] ' . wp_json_encode($payload));
    }
}

if (!function_exists('mulopimfwc_get_frontend_locations')) {
    /**
     * Get locations for frontend display, filtered by is_active status and ordered by display_order.
     * 
     * @param array $args Optional arguments to pass to get_terms
     * @return array|WP_Error Array of location terms or WP_Error on failure
     */
    function mulopimfwc_get_frontend_locations()
    {
        $terms_args = [
            'taxonomy' => 'mulopimfwc_store_location',
            'hide_empty' => false,
        ];
        $manager_locations = mulopimfwc_get_location_manager_frontend_assigned_locations();

        if (is_array($manager_locations)) {
            if (empty($manager_locations)) {
                return [];
            }
            $terms_args['slug'] = $manager_locations;
        }

        // Get all frontend-visible locations
        $locations = get_terms($terms_args);
        
        if (is_wp_error($locations) || empty($locations)) {
            return $locations;
        }
        
        // Filter by is_active status and order by display_order
        $filtered_locations = [];
        foreach ($locations as $location) {
            $is_active = get_term_meta($location->term_id, 'is_active', true);
            
            // Only include active locations (treat unset is_active as active by default)
            if ($is_active === '' || $is_active === 'on' || $is_active === '1' || $is_active === true || $is_active === 'yes') {
                $display_order = get_term_meta($location->term_id, 'display_order', true);
                $display_order = !empty($display_order) ? intval($display_order) : 999;
                
                $filtered_locations[] = [
                    'location' => $location,
                    'display_order' => $display_order,
                ];
            }
        }
        
        // Sort by display_order (ascending)
        usort($filtered_locations, function($a, $b) {
            return $a['display_order'] <=> $b['display_order'];
        });
        
        // Extract just the location objects
        $result = array_map(function($item) {
            return $item['location'];
        }, $filtered_locations);
        
        return $result;
    }
}

if (!function_exists('mulopimfwc_get_store_location_cookie')) {
    /**
     * Read the selected store location from the cookie using the filtered name.
     *
     * @return string
     */
    function mulopimfwc_get_store_location_cookie(): string
    {
        $log_currency_cookie = static function (string $event, array $context = []) {
            static $count = 0;
            if ($count >= 20) {
                return;
            }
            $count++;
            $context['event_count'] = $count;
            if (function_exists('mulopimfwc_log_location_currency_debug')) {
                mulopimfwc_log_location_currency_debug($event, $context);
            }
        };

        $cookie_name = mulopimfwc_get_location_cookie_name();
        if (empty($cookie_name) || !isset($_COOKIE[$cookie_name])) {
            return '';
        }

        $raw_value = wp_unslash($_COOKIE[$cookie_name]);
        $decoded_value = is_string($raw_value) ? rawurldecode($raw_value) : $raw_value;
        $location_slug = sanitize_text_field((string) $decoded_value);

        if ($location_slug === '') {
            $log_currency_cookie('cookie_read_rejected', [
                'reason' => 'empty_cookie_value',
                'cookie_name' => $cookie_name,
            ]);
            return '';
        }

        $normalized_location = sanitize_title(rawurldecode($location_slug));
        if ($normalized_location === '') {
            $log_currency_cookie('cookie_read_rejected', [
                'reason' => 'empty_normalized_slug',
                'cookie_name' => $cookie_name,
                'location_slug' => $location_slug,
            ]);
            return '';
        }

        if ($normalized_location !== 'all-products') {
            $resolved_term = false;
            if (function_exists('mulopimfwc_validate_location_slug')) {
                // Use uncached resolution here so stale invalid caches do not break runtime location.
                $resolved_term = mulopimfwc_validate_location_slug($location_slug, false);
            } else {
                $resolved_term = get_term_by('slug', $normalized_location, 'mulopimfwc_store_location');
            }

            if ($resolved_term && !is_wp_error($resolved_term) && isset($resolved_term->slug)) {
                $normalized_location = sanitize_title(rawurldecode((string) $resolved_term->slug));
            } else {
                // Keep normalized raw value as fallback; currency resolver has its own term lookup fallbacks.
                $log_currency_cookie('cookie_read_unverified', [
                    'reason' => 'location_term_not_resolved',
                    'cookie_name' => $cookie_name,
                    'location_slug' => $location_slug,
                    'normalized_location' => $normalized_location,
                ]);
            }
        }

        $manager_locations = mulopimfwc_get_location_manager_frontend_assigned_locations();
        if (!is_array($manager_locations)) {
            $log_currency_cookie('cookie_read', [
                'cookie_name' => $cookie_name,
                'location_slug' => $normalized_location,
                'raw_location_slug' => $location_slug,
                'restricted_mode' => false,
            ]);
            return $normalized_location;
        }

        if ($normalized_location === 'all-products') {
            $log_currency_cookie('cookie_read', [
                'cookie_name' => $cookie_name,
                'location_slug' => $normalized_location,
                'restricted_mode' => true,
                'is_all_products' => true,
            ]);
            return $normalized_location;
        }

        $is_allowed = in_array($normalized_location, $manager_locations, true);

        if (!$is_allowed) {
            $log_currency_cookie('cookie_read_rejected', [
                'reason' => 'manager_restriction',
                'cookie_name' => $cookie_name,
                'location_slug' => $normalized_location,
                'allowed_locations' => $manager_locations,
            ]);
            return '';
        }

        $log_currency_cookie('cookie_read', [
            'cookie_name' => $cookie_name,
            'location_slug' => $normalized_location,
            'restricted_mode' => true,
            'is_allowed' => true,
        ]);

        return $normalized_location;
    }
}

if (!function_exists('mulopimfwc_set_location_cookie')) {
    /**
     * Set location cookie with standardized, filterable arguments.
     * 
     * This function ensures consistent cookie settings across the plugin:
     * - Path: /
     * - SameSite: Lax
     * - Secure: auto based on is_ssl()
     * - HttpOnly: true (filterable)
     * - Domain: filterable
     * - Expiry: filterable
     * 
     * Also sets $_COOKIE[$name] for same-request behavior.
     *
     * @param string $location_slug The location slug to store
     * @param string|null $cookie_name Optional cookie name (defaults to filtered value)
     * @param object|null $location_obj Optional location term object for filters
     * @return bool True on success, false on failure
     */
    function mulopimfwc_set_location_cookie(string $location_slug, ?string $cookie_name = null, ?object $location_obj = null): bool
    {
        // Sanitize location slug
        $location_slug = sanitize_title($location_slug);
        
        if (empty($location_slug)) {
            if (function_exists('mulopimfwc_log_location_currency_debug')) {
                mulopimfwc_log_location_currency_debug('cookie_set_rejected', [
                    'reason' => 'empty_location',
                    'cookie_name' => $cookie_name,
                ]);
            }
            return false;
        }

        // FIXED: Validate location exists before setting cookie
        if ($location_obj === null && $location_slug !== 'all-products') {
            $location_obj = get_term_by('slug', $location_slug, 'mulopimfwc_store_location');
            if (!$location_obj || is_wp_error($location_obj)) {
                // Invalid location - don't set cookie
                if (function_exists('mulopimfwc_log_location_currency_debug')) {
                    mulopimfwc_log_location_currency_debug('cookie_set_rejected', [
                        'reason' => 'invalid_location',
                        'location_slug' => $location_slug,
                        'cookie_name' => $cookie_name,
                    ]);
                }
                return false;
            }
        }

        // Get cookie name (filterable)
        if ($cookie_name === null) {
            $cookie_name = mulopimfwc_get_location_cookie_name();
        }

        // Allow filtering the cookie value
        $cookie_value = apply_filters('mulopimfwc_location_cookie_value', $location_slug, $location_obj);
        
        // Get expiry
        $expiry = time() + mulopimfwc_get_location_cookie_expiry_seconds();
        $expiry = apply_filters('mulopimfwc_location_cookie_expiry', $expiry, $location_slug);

        // Build cookie arguments
        $cookie_args = [
            'expires' => $expiry,
            'path' => '/',
            'domain' => apply_filters('mulopimfwc_location_cookie_domain', COOKIE_DOMAIN),
            'secure' => is_ssl(),
          // JS reads this cookie for UI state; keep HttpOnly off by default.
          'httponly' => apply_filters('mulopimfwc_location_cookie_httponly', false),
            'samesite' => 'Lax',
        ];

        // Allow full customization of cookie args
        $cookie_args = apply_filters('mulopimfwc_location_cookie_args', $cookie_args, $location_slug);

        // Set cookie using appropriate method based on PHP version
        $set = false;
        if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
            // PHP 7.3+ supports array syntax
            $set = setcookie($cookie_name, $cookie_value, $cookie_args);
        } else {
            // PHP < 7.3 uses individual parameters
            $set = setcookie(
                $cookie_name,
                $cookie_value,
                $cookie_args['expires'],
                $cookie_args['path'],
                $cookie_args['domain'],
                $cookie_args['secure'],
                $cookie_args['httponly']
            );
        }

        // Set $_COOKIE for same-request access
        if ($set) {
            $_COOKIE[$cookie_name] = $cookie_value;
        }

        if (function_exists('mulopimfwc_log_location_currency_debug')) {
            mulopimfwc_log_location_currency_debug('cookie_set', [
                'cookie_name' => $cookie_name,
                'location_slug' => $location_slug,
                'cookie_value' => $cookie_value,
                'setcookie_result' => (bool) $set,
                'headers_sent' => headers_sent(),
                'term_id' => ($location_obj instanceof WP_Term) ? (int) $location_obj->term_id : null,
            ]);
        }

        return $set;
    }
}

if (!function_exists('mulopimfwc_clear_location_cookie')) {
    /**
     * Clear the selected store location cookie using standardized, filterable arguments.
     *
     * @param string|null $cookie_name Optional cookie name (defaults to filtered value)
     * @return bool True on success, false on failure
     */
    function mulopimfwc_clear_location_cookie(?string $cookie_name = null): bool
    {
        if ($cookie_name === null) {
            $cookie_name = mulopimfwc_get_location_cookie_name();
        }

        if (empty($cookie_name)) {
            return false;
        }

        $cookie_args = [
            'expires' => time() - HOUR_IN_SECONDS,
            'path' => '/',
            'domain' => apply_filters('mulopimfwc_location_cookie_domain', COOKIE_DOMAIN),
            'secure' => is_ssl(),
            // JS reads this cookie for UI state; keep HttpOnly off by default.
            'httponly' => apply_filters('mulopimfwc_location_cookie_httponly', false),
            'samesite' => 'Lax',
        ];

        // Allow full customization of cookie args (keep same filters as set)
        $cookie_args = apply_filters('mulopimfwc_location_cookie_args', $cookie_args, '');
        // Ensure expiry is in the past even if filters override
        $cookie_args['expires'] = time() - HOUR_IN_SECONDS;

        $cleared = false;
        if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
            $cleared = setcookie($cookie_name, '', $cookie_args);
        } else {
            $cleared = setcookie(
                $cookie_name,
                '',
                $cookie_args['expires'],
                $cookie_args['path'],
                $cookie_args['domain'],
                $cookie_args['secure'],
                $cookie_args['httponly']
            );
        }

        if (isset($_COOKIE[$cookie_name])) {
            unset($_COOKIE[$cookie_name]);
        }

        return $cleared;
    }
}

if (!function_exists('mulopimfwc_maybe_clear_location_cookie_for_manual_assignment')) {
    /**
     * Remove stale selected location cookies when manual assignment disables location selection.
     */
    function mulopimfwc_maybe_clear_location_cookie_for_manual_assignment(): void
    {
        if (!function_exists('mulopimfwc_is_manual_assignment_strict_mode')) {
            return;
        }

        if (!mulopimfwc_is_manual_assignment_strict_mode()) {
            return;
        }

        $cookie_name = mulopimfwc_get_location_cookie_name();
        if (empty($cookie_name) || !isset($_COOKIE[$cookie_name])) {
            return;
        }

        if (headers_sent()) {
            unset($_COOKIE[$cookie_name]);
            return;
        }

        mulopimfwc_clear_location_cookie($cookie_name);
    }
}

add_action('init', 'mulopimfwc_maybe_clear_location_cookie_for_manual_assignment', 2);

if (!function_exists('mulopimfwc_flush_location_resolver_cache')) {
    /**
     * Flush cached location resolver index when location terms change.
     *
     * @param int $term_id
     * @param int $tt_id
     * @param string $taxonomy
     * @return void
     */
    function mulopimfwc_flush_location_resolver_cache($term_id = 0, $tt_id = 0, $taxonomy = '')
    {
        if ($taxonomy !== '' && $taxonomy !== 'mulopimfwc_store_location') {
            return;
        }

        if (class_exists('MULOPIMFWC_Location_Resolver')) {
            MULOPIMFWC_Location_Resolver::flush_index_cache();
        }
    }
}

add_action('created_term', 'mulopimfwc_flush_location_resolver_cache', 10, 3);
add_action('edited_term', 'mulopimfwc_flush_location_resolver_cache', 10, 3);
add_action('delete_term', 'mulopimfwc_flush_location_resolver_cache', 10, 3);

if (!function_exists('mulopimfwc_flush_location_resolver_cache_on_term_meta')) {
    /**
     * Flush resolver cache when store-location term meta changes.
     *
     * @param int $meta_id
     * @param int $object_id
     * @param string $meta_key
     * @param mixed $meta_value
     * @return void
     */
    function mulopimfwc_flush_location_resolver_cache_on_term_meta($meta_id, $object_id, $meta_key = '', $meta_value = null)
    {
        $term = get_term((int) $object_id);
        if (!$term || is_wp_error($term) || !isset($term->taxonomy) || $term->taxonomy !== 'mulopimfwc_store_location') {
            return;
        }

        if (class_exists('MULOPIMFWC_Location_Resolver')) {
            MULOPIMFWC_Location_Resolver::flush_index_cache();
        }
    }
}

add_action('added_term_meta', 'mulopimfwc_flush_location_resolver_cache_on_term_meta', 10, 4);
add_action('updated_term_meta', 'mulopimfwc_flush_location_resolver_cache_on_term_meta', 10, 4);
add_action('deleted_term_meta', 'mulopimfwc_flush_location_resolver_cache_on_term_meta', 10, 4);

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

// Enhanced WooCommerce dependency check
// Check if WooCommerce is active (including network-activated in multisite)
function mulopimfwc_is_woocommerce_active() {
    // Check regular active plugins
    if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        return true;
    }
    
    // Check network-activated plugins (multisite)
    if (is_multisite()) {
        $network_plugins = get_site_option('active_sitewide_plugins', array());
        if (isset($network_plugins['woocommerce/woocommerce.php'])) {
            return true;
        }
    }
    
    // Check if WooCommerce class exists (actually loaded)
    if (class_exists('WooCommerce')) {
        return true;
    }
    
    return false;
}

// Check WooCommerce version compatibility
// FIXED: Use multiple methods to get WooCommerce version reliably
function mulopimfwc_check_woocommerce_version() {
    $wc_version = null;
    
    // Method 1: Check WC_VERSION constant (most reliable, defined by WooCommerce)
    if (defined('WC_VERSION')) {
        $wc_version = WC_VERSION;
    } 
    // Method 2: Get from WooCommerce plugin data (works even if constant not defined yet)
    elseif (function_exists('get_plugin_data')) {
        $wc_plugin_path = WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
        if (file_exists($wc_plugin_path)) {
            $wc_plugin_data = get_plugin_data($wc_plugin_path, false, false);
            if (isset($wc_plugin_data['Version']) && !empty($wc_plugin_data['Version'])) {
                $wc_version = $wc_plugin_data['Version'];
            }
        }
    }
    // Method 3: Try to get from WooCommerce class instance
    if (empty($wc_version) && class_exists('WooCommerce')) {
        if (function_exists('WC')) {
            $wc_instance = WC();
            if (is_object($wc_instance) && property_exists($wc_instance, 'version')) {
                $wc_version = $wc_instance->version;
            }
        }
    }
    
    // If we still don't have a version, return true to avoid false positives
    // (better to allow plugin to work than block it incorrectly)
    if (empty($wc_version)) {
        return true; // Assume compatible if we can't determine version
    }
    
    // Ensure we have a valid version string
    if (!is_string($wc_version)) {
        return true; // Assume compatible if version is not a string
    }
    
    $min_version = '4.0';
    
    // Use version_compare to check compatibility
    $is_compatible = version_compare($wc_version, $min_version, '>=');
    
    return $is_compatible;
}

// Check if WooCommerce is active first
if (!mulopimfwc_is_woocommerce_active()) {
    add_action('admin_notices', function () {
        echo '<div class="error"><p>' . esc_html__('Location Wise Products requires WooCommerce to be installed and active.', 'multi-location-product-and-inventory-management') . '</p></div>';
    });
    return;
}

// FIXED: Check version after plugins_loaded to ensure WooCommerce constants are defined
// The version check now runs after WooCommerce loads, so WC_VERSION will be available
// We don't block plugin loading, just show a warning if version is truly incompatible
add_action('plugins_loaded', function() {
    // Only check version if WooCommerce is actually loaded
    if (!class_exists('WooCommerce') && !defined('WC_VERSION')) {
        // WooCommerce not loaded yet, skip check
        return;
    }
    
    if (!mulopimfwc_check_woocommerce_version()) {
        add_action('admin_notices', function () {
            $wc_version = 'unknown';
            // Try multiple methods to get actual version for display
            if (defined('WC_VERSION')) {
                $wc_version = WC_VERSION;
            } elseif (function_exists('get_plugin_data')) {
                $wc_plugin_path = WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
                if (file_exists($wc_plugin_path)) {
                    $wc_plugin_data = get_plugin_data($wc_plugin_path, false, false);
                    $wc_version = isset($wc_plugin_data['Version']) ? $wc_plugin_data['Version'] : 'unknown';
                }
            } elseif (class_exists('WooCommerce') && function_exists('WC')) {
                $wc_instance = WC();
                if (is_object($wc_instance) && property_exists($wc_instance, 'version')) {
                    $wc_version = $wc_instance->version;
                }
            }
            
            echo '<div class="error"><p>';
            echo esc_html__('Location Wise Products requires WooCommerce version 4.0 or higher. ', 'multi-location-product-and-inventory-management');
            if ($wc_version !== 'unknown') {
                echo esc_html(sprintf(__('Detected WooCommerce version: %s', 'multi-location-product-and-inventory-management'), $wc_version));
            }
            echo '</p></div>';
        }, 10);
    }
}, 20); // Priority 20 to ensure WooCommerce has fully loaded and defined all constants


if (!function_exists('mulopimfwc_get_values')) {

    global $mulopimfwc_locations, $mulopimfwc_allowed_tags, $mulopimfwc_options;

    function mulopimfwc_get_values()
    {
        global $mulopimfwc_locations, $mulopimfwc_allowed_tags, $mulopimfwc_options;

        // Check if taxonomy exists
        if (!taxonomy_exists('mulopimfwc_store_location')) {
            return;
        }

        $manager_frontend_locations = mulopimfwc_get_location_manager_frontend_assigned_locations();

        // Get locations based on current frontend restrictions
        if (is_array($manager_frontend_locations)) {
            if (empty($manager_frontend_locations)) {
                $mulopimfwc_locations = [];
            } else {
                $mulopimfwc_locations = get_terms([
                    'taxonomy' => 'mulopimfwc_store_location',
                    'hide_empty' => false,
                    'slug' => $manager_frontend_locations,
                ]);
            }
        } else {
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

    require_once plugin_dir_path(__FILE__) . 'includes/text-management.php';
    require_once plugin_dir_path(__FILE__) . 'admin/settings.php';
    require_once plugin_dir_path(__FILE__) . 'admin/dashboard.php';
    require_once plugin_dir_path(__FILE__) . 'admin/license-page.php';
    require_once plugin_dir_path(__FILE__) . 'admin/stock-central.php';
    require_once plugin_dir_path(__FILE__) . 'admin/admin.php';
    require_once plugin_dir_path(__FILE__) . 'includes/location-resolver.php';
    require_once plugin_dir_path(__FILE__) . 'includes/product-display.php';
    require_once plugin_dir_path(__FILE__) . 'admin/location-based-everythings.php';
    require_once plugin_dir_path(__FILE__) . 'admin/location-managers.php';
    require_once plugin_dir_path(__FILE__) . 'includes/product-location-selector-single.php';
    require_once plugin_dir_path(__FILE__) . 'admin/import-export-settings.php';
    require_once plugin_dir_path(__FILE__) . 'admin/api-settings.php';
    require_once plugin_dir_path(__FILE__) . 'includes/location-based-shipping.php';
    require_once plugin_dir_path(__FILE__) . 'includes/location-wise-shipping-payment-tax.php';
    require_once plugin_dir_path(__FILE__) . 'includes/order-split-by-location.php';
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
    require_once plugin_dir_path(__FILE__) . 'includes/class-mulopimfwc-cache-compat.php';

    class mulopimfwc_Location_Wise_Products
    {

        private $cart_items_cache = null;
        private $should_group_cache = null;
        private $transfer_destination_cache = [];
        private $location_currency_runtime_settings = [];
        private $location_currency_debug_counts = [];
        
        /**
         * Cache for product location relationships (per request)
         * Key: product_id, Value: array of location slugs
         * 
         * @var array|null
         */
        private static $product_locations_cache = null;
        
        /**
         * Set of product IDs that have been requested in this request
         * Used to batch load location relationships
         * 
         * @var array
         */
        private static $requested_product_ids = [];
        
        /**
         * Flag to track if batch loading has been performed
         * 
         * @var bool
         */
        private static $batch_loaded = false;

        public function __construct()
        {
            add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
            add_action('pre_get_posts', [$this, 'filter_products_by_location']);
            add_filter('woocommerce_currency', [$this, 'filter_currency_by_selected_location'], 999);
            add_filter('woocommerce_currency_pos', [$this, 'filter_currency_position_by_selected_location'], 999);
            add_filter('wc_price_args', [$this, 'filter_wc_price_args_by_selected_location'], 999);
            add_filter('woocommerce_price_format', [$this, 'filter_price_format_by_selected_location'], 999, 2);
            add_filter('pre_option_woocommerce_currency', [$this, 'filter_pre_option_currency_by_selected_location'], 999, 3);
            add_filter('pre_option_woocommerce_currency_pos', [$this, 'filter_pre_option_currency_position_by_selected_location'], 999, 3);
            
            // FIXED: Comprehensive product filtering for all display methods
            add_filter('woocommerce_shortcode_products_query', [$this, 'filter_shortcode_products']);
            add_filter('woocommerce_products_widget_query_args', [$this, 'filter_widget_products']);
            add_filter('woocommerce_related_products_args', [$this, 'filter_related_products']);
            
            // FIXED: WooCommerce Blocks (Gutenberg) support
            add_filter('woocommerce_blocks_product_grid_query_args', [$this, 'filter_shortcode_products'], 10, 1);
            
            // FIXED: Add location-first ordering and filtering for ALL product queries
            add_filter('posts_clauses', [$this, 'add_location_filtering_and_ordering_to_all_queries'], 10, 2);
            add_filter('woocommerce_rest_product_query', [$this, 'filter_rest_api_products'], 10, 2);
            
            // Handle single product pages when product is not available in selected location
            // Hook into pre_get_posts to prevent 404 when setting is show_message
            add_action('pre_get_posts', [$this, 'handle_single_product_query'], 20);
            // Also hook into template_redirect as fallback
            add_action('template_redirect', [$this, 'handle_single_product_unavailable'], 5);
            
            // OPTIMIZED: Clear product locations cache when products or locations are updated
            add_action('save_post_product', [$this, 'clear_cache_on_product_update'], 10, 2);
            add_action('edited_term', [$this, 'clear_cache_on_term_update'], 10, 3);
            add_action('created_term', [$this, 'clear_cache_on_term_update'], 10, 3);
            add_action('delete_term', [$this, 'clear_cache_on_term_delete'], 10, 4);
            add_action('set_object_terms', [$this, 'clear_cache_on_object_terms_update'], 10, 6);
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', ['enable_popup' => 'off']);
            $is_manual_mode = mulopimfwc_is_manual_assignment_strict_mode($options);
            if (isset($options['enable_popup']) && $options['enable_popup'] === 'on' && mulopimfwc_premium_feature() && !$is_manual_mode) {
                add_action('wp_footer', [$this, 'location_selector_modal']);
            }
            add_action('init', [$this, 'maybe_set_default_location_cookie'], 999);
            add_action('init', [$this, 'clear_cart_on_location_change']);

            add_shortcode('mulopimfwc_store_location_selector', [$this, 'location_selector_shortcode']);
            add_shortcode('mulopimfwc_display_popup', [$this, 'display_popup_shortcode']);

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
            add_action('wp_ajax_mulopimfwc_switch_location', [$this, 'ajax_switch_location']);
            add_action('wp_ajax_nopriv_mulopimfwc_switch_location', [$this, 'ajax_switch_location']);

            add_action('admin_enqueue_scripts', [$this, 'custom_admin_styles']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_order_location_scripts']);

            // add settings button after deactivate button in plugins page

            add_action('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_settings_link']);
            add_action('admin_init', [$this, 'add_settings_link']);

            // Save location to order meta (must be available before WC emails are generated)
            add_action('woocommerce_checkout_update_order_meta', [$this, 'save_location_to_order_meta'], 5, 1);
            add_action('woocommerce_thankyou', array($this, 'save_location_to_order_meta'), 10, 1);
            add_action('woocommerce_checkout_order_processed', [$this, 'handle_social_new_order_notification'], 20, 1);
            add_filter('woocommerce_payment_complete_order_status', [$this, 'maybe_hold_manual_unassigned_order_status'], 10, 3);

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
            add_filter('woocommerce_hidden_order_itemmeta', [$this, 'hide_location_order_itemmeta'], 10, 1);
            add_action('wp_ajax_mulopimfwc_update_order_item_location', [$this, 'update_order_item_location']);
            add_action('woocommerce_before_save_order_item', [$this, 'store_order_item_old_quantity'], 5, 1);
            add_action('woocommerce_after_order_object_save', [$this, 'process_order_items_stock_update'], 10, 1);
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
            add_filter('plugin_row_meta', array(__CLASS__, 'plugin_row_meta'), 10, 2);
        }

        /**
         * Clear cart items cache
         */
        public function clear_cart_cache()
        {
            $this->cart_items_cache = null;
        }

        /**
         * Log currency-debug events with per-request event throttling.
         *
         * @param string $event
         * @param array $context
         * @param int $limit_per_event
         * @return void
         */
        private function currency_debug_log(string $event, array $context = [], int $limit_per_event = 12): void
        {
            if (!function_exists('mulopimfwc_log_location_currency_debug')) {
                return;
            }

            $count = isset($this->location_currency_debug_counts[$event])
                ? (int) $this->location_currency_debug_counts[$event]
                : 0;

            if ($count >= $limit_per_event) {
                return;
            }

            $count++;
            $this->location_currency_debug_counts[$event] = $count;
            $context['event_count'] = $count;

            mulopimfwc_log_location_currency_debug($event, $context);
        }

        /**
         * Resolve store-location term by slug directly from DB.
         * Used as fallback when taxonomy registration timing makes get_term_by unavailable.
         *
         * @param string $location_slug
         * @return object|null
         */
        private function get_location_term_by_slug_db_fallback(string $location_slug): ?object
        {
            $location_slug = sanitize_title(rawurldecode(trim($location_slug)));
            if ($location_slug === '') {
                return null;
            }

            global $wpdb;

            $term_id = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT t.term_id
                     FROM {$wpdb->terms} AS t
                     INNER JOIN {$wpdb->term_taxonomy} AS tt
                        ON tt.term_id = t.term_id
                     WHERE tt.taxonomy = %s
                       AND t.slug = %s
                     LIMIT 1",
                    'mulopimfwc_store_location',
                    $location_slug
                )
            );

            if ($term_id <= 0) {
                return null;
            }

            return (object) [
                'term_id' => $term_id,
                'slug' => $location_slug,
                'taxonomy' => 'mulopimfwc_store_location',
            ];
        }

        /**
         * Return the only assigned location slug for the current location manager.
         *
         * @return string Empty string when not applicable.
         */
        private function get_single_assigned_location_for_currency(): string
        {
            if (!class_exists('MULOPIMFWC_Location_Managers') || !is_user_logged_in()) {
                return '';
            }

            $user = wp_get_current_user();
            if (!in_array('mulopimfwc_location_manager', (array) $user->roles, true)) {
                return '';
            }

            $assigned_locations = MULOPIMFWC_Location_Managers::get_user_assigned_locations();
            if (!is_array($assigned_locations)) {
                return '';
            }

            $assigned_locations = array_map(function ($location_slug) {
                return sanitize_title(rawurldecode((string) $location_slug));
            }, $assigned_locations);
            $assigned_locations = array_values(array_filter($assigned_locations, function ($location_slug) {
                return is_string($location_slug) && $location_slug !== '';
            }));
            $assigned_locations = array_values(array_unique($assigned_locations));

            if (count($assigned_locations) !== 1) {
                return '';
            }

            return (string) $assigned_locations[0];
        }

        /**
         * Determine if admin non-AJAX requests should still use location-wise currency runtime.
         *
         * @return bool
         */
        private function should_enable_admin_currency_runtime(): bool
        {
            if (!is_admin() || wp_doing_ajax()) {
                return true;
            }

            if ($this->get_single_assigned_location_for_currency() !== '') {
                return true;
            }

            $request_keys = [
                'mulopimfwc_loc',
                'location',
                'mulopimfwc_store_location',
                'location_filter',
                'store_location',
            ];

            foreach ($request_keys as $request_key) {
                if (isset($_REQUEST[$request_key])) {
                    return true;
                }
            }

            return false;
        }

        private function get_runtime_location_slug_for_currency(): string
        {
            if (is_admin() && wp_doing_ajax()) {
                $ajax_action = isset($_REQUEST['action'])
                    ? sanitize_key((string) wp_unslash($_REQUEST['action']))
                    : '';

                if ($ajax_action === 'mulopimfwc_dashboard_live_data') {
                    $has_explicit_dashboard_location = isset($_REQUEST['location'])
                        || isset($_REQUEST['location_filter'])
                        || isset($_REQUEST['mulopimfwc_loc'])
                        || isset($_REQUEST['mulopimfwc_store_location']);

                    if (!$has_explicit_dashboard_location) {
                        $single_assigned_location = $this->get_single_assigned_location_for_currency();
                        if ($single_assigned_location !== '') {
                            $this->currency_debug_log('currency_location_selected', [
                                'source' => 'location_manager_single_assigned',
                                'resolved_location' => $single_assigned_location,
                                'reason' => 'dashboard_live_single_assigned_location',
                            ], 25);
                            return $single_assigned_location;
                        }

                        $this->currency_debug_log('currency_location_selected', [
                            'source' => 'dashboard_live_default_scope',
                            'resolved_location' => '',
                            'reason' => 'dashboard_live_no_explicit_location',
                        ], 25);
                        return '';
                    }
                }
            }

            $candidates = [];

            if (isset($_REQUEST['mulopimfwc_loc'])) {
                $candidates['request_mulopimfwc_loc'] = (string) wp_unslash($_REQUEST['mulopimfwc_loc']);
            }

            if (isset($_REQUEST['location'])) {
                $candidates['request_location'] = (string) wp_unslash($_REQUEST['location']);
            }

            if (isset($_REQUEST['mulopimfwc_store_location'])) {
                $candidates['request_store_location'] = (string) wp_unslash($_REQUEST['mulopimfwc_store_location']);
            }

            if (isset($_REQUEST['location_filter'])) {
                $candidates['request_location_filter'] = (string) wp_unslash($_REQUEST['location_filter']);
            }

            $single_assigned_location = $this->get_single_assigned_location_for_currency();
            if ($single_assigned_location !== '') {
                $candidates['location_manager_single_assigned'] = $single_assigned_location;
            }

            if (function_exists('mulopimfwc_get_effective_runtime_location_slug')) {
                $candidates['effective_runtime'] = (string) mulopimfwc_get_effective_runtime_location_slug();
            }

            $candidates['current_location'] = (string) $this->get_current_location();

            if (function_exists('mulopimfwc_get_store_location_cookie')) {
                $candidates['store_cookie'] = (string) mulopimfwc_get_store_location_cookie();
            }

            $this->currency_debug_log('currency_location_candidates', [
                'candidates' => $candidates,
            ]);

            foreach ($candidates as $source => $candidate) {
                $candidate_raw = trim((string) $candidate);
                $candidate_slug = sanitize_title(rawurldecode($candidate_raw));
                if ($candidate_slug === '') {
                    $this->currency_debug_log('currency_location_candidate_skipped', [
                        'source' => $source,
                        'raw' => $candidate,
                        'reason' => 'empty_after_sanitize',
                    ], 25);
                    continue;
                }

                if (
                    in_array($source, ['request_location', 'request_location_filter'], true) &&
                    in_array($candidate_slug, ['all', 'default', 'none'], true)
                ) {
                    $this->currency_debug_log('currency_location_selected', [
                        'source' => $source,
                        'resolved_location' => '',
                        'reason' => 'explicit_default_scope',
                        'raw' => $candidate,
                    ], 25);
                    return '';
                }

                if ($candidate_slug === 'all-products') {
                    $this->currency_debug_log('currency_location_selected', [
                        'source' => $source,
                        'resolved_location' => $candidate_slug,
                        'reason' => 'all_products',
                    ], 25);
                    return $candidate_slug;
                }

                $location_resolution_method = 'validator';
                $location_term = function_exists('mulopimfwc_validate_location_slug')
                    // Use uncached validation to avoid stale invalid-cache blocking currency resolution.
                    ? mulopimfwc_validate_location_slug($candidate_raw, false)
                    : get_term_by('slug', $candidate_slug, 'mulopimfwc_store_location');

                if ((!$location_term || is_wp_error($location_term)) && $candidate_slug !== '') {
                    $location_term = $this->get_location_term_by_slug_db_fallback($candidate_slug);
                    if ($location_term && !is_wp_error($location_term)) {
                        $location_resolution_method = 'db_fallback';
                    }
                }

                if ($location_term && !is_wp_error($location_term)) {
                    $resolved_slug = sanitize_title(rawurldecode((string) $location_term->slug));
                    $this->currency_debug_log('currency_location_selected', [
                        'source' => $source,
                        'resolved_location' => $resolved_slug,
                        'reason' => 'validated_term',
                        'term_id' => (int) $location_term->term_id,
                        'resolution_method' => $location_resolution_method,
                    ], 25);
                    return $resolved_slug;
                }

                $this->currency_debug_log('currency_location_candidate_skipped', [
                    'source' => $source,
                    'raw' => $candidate,
                    'candidate_slug' => $candidate_slug,
                    'reason' => 'term_not_found',
                ], 25);
            }

            $this->currency_debug_log('currency_location_selected', [
                'source' => '',
                'resolved_location' => '',
                'reason' => 'no_valid_candidate',
            ], 25);

            return '';
        }

        private function get_location_currency_runtime_settings(): array
        {
            $empty_settings = [
                'currency' => '',
                'position' => '',
            ];

            if (is_admin() && !wp_doing_ajax() && !$this->should_enable_admin_currency_runtime()) {
                $this->currency_debug_log('currency_runtime_settings_skipped', [
                    'reason' => 'admin_non_ajax_no_context',
                ], 25);
                return $empty_settings;
            }

            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);

            if (!mulopimfwc_is_location_wise_currency_enabled($options)) {
                $this->currency_debug_log('currency_runtime_settings_skipped', [
                    'reason' => 'location_wise_currency_disabled',
                    'location_wise_currency_option' => isset($options['location_wise_currency'])
                        ? (string) $options['location_wise_currency']
                        : '',
                ], 25);
                return $empty_settings;
            }

            $location_slug = $this->get_runtime_location_slug_for_currency();
            $cache_key = $location_slug === '' ? '__none' : $location_slug;

            if (isset($this->location_currency_runtime_settings[$cache_key])) {
                $this->currency_debug_log('currency_runtime_settings_cache_hit', [
                    'cache_key' => $cache_key,
                    'location_slug' => $location_slug,
                    'settings' => $this->location_currency_runtime_settings[$cache_key],
                ], 25);
                return $this->location_currency_runtime_settings[$cache_key];
            }

            $settings = $empty_settings;

            if ($location_slug === '' || $location_slug === 'all-products') {
                $this->location_currency_runtime_settings[$cache_key] = $settings;
                $this->currency_debug_log('currency_runtime_settings_empty', [
                    'reason' => $location_slug === 'all-products' ? 'all_products_selected' : 'location_missing',
                    'location_slug' => $location_slug,
                    'cache_key' => $cache_key,
                ], 25);
                return $settings;
            }

            $location_resolution_method = 'validator';
            $location_term = function_exists('mulopimfwc_validate_location_slug')
                // Use uncached lookup for currency settings to avoid stale invalid slug cache.
                ? mulopimfwc_validate_location_slug($location_slug, false)
                : get_term_by('slug', $location_slug, 'mulopimfwc_store_location');
            if ((!$location_term || is_wp_error($location_term)) && $location_slug !== '') {
                $location_term = $this->get_location_term_by_slug_db_fallback($location_slug);
                if ($location_term && !is_wp_error($location_term)) {
                    $location_resolution_method = 'db_fallback';
                }
            }
            if (!$location_term || is_wp_error($location_term)) {
                $this->location_currency_runtime_settings[$cache_key] = $settings;
                $this->currency_debug_log('currency_runtime_settings_empty', [
                    'reason' => 'location_term_not_found',
                    'location_slug' => $location_slug,
                    'cache_key' => $cache_key,
                ], 25);
                return $settings;
            }

            $configured_currency = strtoupper(trim((string) get_term_meta((int) $location_term->term_id, 'location_currency', true)));
            $currency_is_valid = false;
            if ($configured_currency !== '' && function_exists('get_woocommerce_currencies')) {
                $available_currencies = (array) get_woocommerce_currencies();
                if (isset($available_currencies[$configured_currency])) {
                    $settings['currency'] = $configured_currency;
                    $currency_is_valid = true;
                }
            }

            $configured_position = sanitize_key((string) get_term_meta((int) $location_term->term_id, 'location_currency_position', true));
            $position_is_valid = false;
            if (in_array($configured_position, ['left', 'right', 'left_space', 'right_space'], true)) {
                $settings['position'] = $configured_position;
                $position_is_valid = true;
            }

            $this->location_currency_runtime_settings[$cache_key] = $settings;
            $this->currency_debug_log('currency_runtime_settings_resolved', [
                'location_slug' => $location_slug,
                'cache_key' => $cache_key,
                'term_id' => (int) $location_term->term_id,
                'raw_term_currency' => $configured_currency,
                'raw_term_position' => $configured_position,
                'currency_is_valid' => $currency_is_valid,
                'position_is_valid' => $position_is_valid,
                'resolution_method' => $location_resolution_method,
                'default_currency' => (string) get_option('woocommerce_currency', ''),
                'default_position' => (string) get_option('woocommerce_currency_pos', ''),
                'settings' => $settings,
            ], 25);
            return $settings;
        }

        public function filter_currency_by_selected_location($currency)
        {
            $settings = $this->get_location_currency_runtime_settings();
            $resolved_currency = !empty($settings['currency']) ? $settings['currency'] : $currency;

            $this->currency_debug_log('hook_woocommerce_currency', [
                'incoming_currency' => $currency,
                'resolved_currency' => $resolved_currency,
                'settings' => $settings,
            ], 20);

            return $resolved_currency;
        }

        public function filter_currency_position_by_selected_location($position)
        {
            $settings = $this->get_location_currency_runtime_settings();
            $resolved_position = !empty($settings['position']) ? $settings['position'] : $position;

            $this->currency_debug_log('hook_woocommerce_currency_pos', [
                'incoming_position' => $position,
                'resolved_position' => $resolved_position,
                'settings' => $settings,
            ], 20);

            return $resolved_position;
        }

        private function get_price_format_for_currency_position(string $position): string
        {
            switch ($position) {
                case 'right':
                    return '%2$s%1$s';
                case 'left_space':
                    return '%1$s&nbsp;%2$s';
                case 'right_space':
                    return '%2$s&nbsp;%1$s';
                case 'left':
                default:
                    return '%1$s%2$s';
            }
        }

        public function filter_wc_price_args_by_selected_location($args)
        {
            $settings = $this->get_location_currency_runtime_settings();
            if (empty($settings['currency']) && empty($settings['position'])) {
                $this->currency_debug_log('hook_wc_price_args', [
                    'settings' => $settings,
                    'args_type' => gettype($args),
                    'changed' => false,
                ], 12);
                return $args;
            }

            if (!is_array($args)) {
                $args = [];
            }

            if (!empty($settings['currency'])) {
                $args['currency'] = $settings['currency'];
            }

            if (!empty($settings['position'])) {
                $args['price_format'] = $this->get_price_format_for_currency_position((string) $settings['position']);
            }

            $this->currency_debug_log('hook_wc_price_args', [
                'settings' => $settings,
                'changed' => true,
                'args_after' => $args,
            ], 12);

            return $args;
        }

        public function filter_price_format_by_selected_location($format, $currency_pos = '')
        {
            $settings = $this->get_location_currency_runtime_settings();
            if (!empty($settings['position'])) {
                $resolved_format = $this->get_price_format_for_currency_position((string) $settings['position']);
                $this->currency_debug_log('hook_woocommerce_price_format', [
                    'incoming_format' => $format,
                    'incoming_currency_pos' => $currency_pos,
                    'resolved_format' => $resolved_format,
                    'settings' => $settings,
                ], 20);
                return $resolved_format;
            }

            $this->currency_debug_log('hook_woocommerce_price_format', [
                'incoming_format' => $format,
                'incoming_currency_pos' => $currency_pos,
                'resolved_format' => $format,
                'settings' => $settings,
            ], 20);

            return $format;
        }

        public function filter_pre_option_currency_by_selected_location($pre_value, $option, $default_value)
        {
            $settings = $this->get_location_currency_runtime_settings();
            if (!empty($settings['currency'])) {
                $this->currency_debug_log('hook_pre_option_woocommerce_currency', [
                    'incoming_pre_value' => $pre_value,
                    'option' => $option,
                    'default_value' => $default_value,
                    'resolved_pre_value' => $settings['currency'],
                    'settings' => $settings,
                ], 20);
                return $settings['currency'];
            }

            $this->currency_debug_log('hook_pre_option_woocommerce_currency', [
                'incoming_pre_value' => $pre_value,
                'option' => $option,
                'default_value' => $default_value,
                'resolved_pre_value' => $pre_value,
                'settings' => $settings,
            ], 20);

            return $pre_value;
        }

        public function filter_pre_option_currency_position_by_selected_location($pre_value, $option, $default_value)
        {
            $settings = $this->get_location_currency_runtime_settings();
            if (!empty($settings['position'])) {
                $this->currency_debug_log('hook_pre_option_woocommerce_currency_pos', [
                    'incoming_pre_value' => $pre_value,
                    'option' => $option,
                    'default_value' => $default_value,
                    'resolved_pre_value' => $settings['position'],
                    'settings' => $settings,
                ], 20);
                return $settings['position'];
            }

            $this->currency_debug_log('hook_pre_option_woocommerce_currency_pos', [
                'incoming_pre_value' => $pre_value,
                'option' => $option,
                'default_value' => $default_value,
                'resolved_pre_value' => $pre_value,
                'settings' => $settings,
            ], 20);

            return $pre_value;
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

            $assignment_method = function_exists('mulopimfwc_get_effective_order_assignment_method')
                ? mulopimfwc_get_effective_order_assignment_method($options)
                : (isset($options['order_assignment_method']) ? $options['order_assignment_method'] : 'customer_selection');
            if (in_array($assignment_method, ['manual', 'inventory_based', 'proximity_based'], true)) {
                return $passed;
            }

            $require_selection = isset($options['location_require_selection']) ? $options['location_require_selection'] : 'off';

            if ($require_selection !== 'on') {
                return $passed;
            }

            $primary_product = $variation_id ? $variation_id : $product_id;

            if (!$this->product_has_assigned_locations($primary_product, $product_id)) {
                return $passed;
            }

            // Use get_current_location method which includes default location logic
            $selected_location = $this->get_current_location();

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
            if ($order instanceof WC_Order) {
                if ($order->get_meta('_mulopimfwc_split_parent') === 'yes' || $order->get_meta('_mulopimfwc_split_child') === 'yes') {
                    return;
                }
            }

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

            if (function_exists('mulopimfwc_is_split_order_enabled') && mulopimfwc_is_split_order_enabled($mulopimfwc_options)) {
                return;
            }

            // Check if mixed location cart is enabled
            $allow_mixed = mulopimfwc_is_mixed_location_cart_enabled($mulopimfwc_options) ? 'on' : 'off';

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

            $order = wc_get_order($order_id);
            if (!$order) {
                return;
            }

            // Get transfer costs settings
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
            $transfer_costs = isset($options['inter_location_transfer_costs']) && mulopimfwc_premium_feature()
                ? $options['inter_location_transfer_costs']
                : [];

            if (empty($transfer_costs)) {
                return;
            }

            // Resolve destination location using shipping/billing proximity with selected location fallback.
            $destination_term = $this->resolve_transfer_destination_location_term($order);
            if (!$destination_term) {
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
                    $base_cost = floatval($transfer_costs[$cost_key]);
                    $cost = function_exists('mulopimfwc_convert_base_amount_to_runtime_currency')
                        ? (float) mulopimfwc_convert_base_amount_to_runtime_currency($base_cost, (int) $destination_term->term_id)
                        : $base_cost;

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

            if (function_exists('mulopimfwc_is_split_order_enabled') && mulopimfwc_is_split_order_enabled($mulopimfwc_options)) {
                return;
            }

            // Check if mixed location cart is enabled
            $allow_mixed = mulopimfwc_is_mixed_location_cart_enabled($mulopimfwc_options) ? 'on' : 'off';

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

            // Get transfer costs settings
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
            $transfer_costs = isset($options['inter_location_transfer_costs']) && mulopimfwc_premium_feature()
                ? $options['inter_location_transfer_costs']
                : [];

            if (empty($transfer_costs)) {
                return;
            }

            // Resolve destination location using shipping/billing proximity with selected location fallback.
            $destination_term = $this->resolve_transfer_destination_location_term();
            if (!$destination_term) {
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
                    $base_cost = floatval($transfer_costs[$cost_key]);
                    $cost = function_exists('mulopimfwc_convert_base_amount_to_runtime_currency')
                        ? (float) mulopimfwc_convert_base_amount_to_runtime_currency($base_cost, (int) $destination_term->term_id)
                        : $base_cost;

                    if ($cost > 0) {
                        $total_transfer_cost += $cost;

                        $cost_label = function_exists('wc_price')
                            ? wp_strip_all_tags(wc_price($cost))
                            : (string) $cost;

                        $transfer_details[] = sprintf(
                            __('%s to %s: %s', 'multi-location-product-and-inventory-management'),
                            $location_data['location_name'],
                            $destination_term->name,
                            $cost_label
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
            $allow_mixed = mulopimfwc_is_mixed_location_cart_enabled($mulopimfwc_options) ? 'on' : 'off';

            if ($allow_mixed !== 'on') {
                return $packages;
            }

            // Check if location-based shipping is enabled
            $enable_location_shipping = mulopimfwc_is_location_shipping_enabled($mulopimfwc_options) ? 'on' : 'off';

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

            if (function_exists('mulopimfwc_is_split_order_enabled') && mulopimfwc_is_split_order_enabled($mulopimfwc_options)) {
                return;
            }

            // Check if mixed location cart is enabled
            $allow_mixed = mulopimfwc_is_mixed_location_cart_enabled($mulopimfwc_options) ? 'on' : 'off';

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

            // Get transfer costs settings
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
            $transfer_costs = isset($options['inter_location_transfer_costs']) && mulopimfwc_premium_feature()
                ? $options['inter_location_transfer_costs']
                : [];

            if (empty($transfer_costs)) {
                return;
            }

            // Resolve destination location using shipping/billing proximity with selected location fallback.
            $destination_term = $this->resolve_transfer_destination_location_term();
            if (!$destination_term) {
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
         * Resolve transfer destination location term.
         * Uses nearest warehouse by shipping/billing address first, then falls back
         * to currently selected location.
         *
         * @param WC_Order|int|null $order Optional order for address context.
         * @return WP_Term|null
         */
        private function resolve_transfer_destination_location_term($order = null)
        {
            $order_object = null;
            $cache_key = '';
            $can_use_cache = false;

            if ($order instanceof WC_Order) {
                $order_object = $order;
                $cache_key = 'order_' . (int) $order->get_id();
                $can_use_cache = true;
            } elseif (is_numeric($order) && (int) $order > 0) {
                $order_object = wc_get_order((int) $order);
                $cache_key = 'order_' . (int) $order;
                $can_use_cache = true;
            }

            if ($can_use_cache && array_key_exists($cache_key, $this->transfer_destination_cache)) {
                return $this->transfer_destination_cache[$cache_key];
            }

            $nearest_slug = $this->resolve_nearest_transfer_destination_slug($order_object);
            if ($nearest_slug !== '') {
                $nearest_term = mulopimfwc_validate_location_slug($nearest_slug);
                if ($nearest_term && !is_wp_error($nearest_term)) {
                    if ($can_use_cache) {
                        $this->transfer_destination_cache[$cache_key] = $nearest_term;
                    }
                    return $nearest_term;
                }
            }

            $selected_term = $this->get_selected_location_term_for_transfer();
            if ($can_use_cache) {
                $this->transfer_destination_cache[$cache_key] = $selected_term;
            }

            return $selected_term;
        }

        /**
         * Resolve nearest destination location slug for transfer-cost destination.
         *
         * @param WC_Order|null $order Optional order context.
         * @return string
         */
        private function resolve_nearest_transfer_destination_slug(?WC_Order $order = null): string
        {
            $locations = function_exists('mulopimfwc_get_frontend_locations')
                ? mulopimfwc_get_frontend_locations()
                : get_terms([
                    'taxonomy' => 'mulopimfwc_store_location',
                    'hide_empty' => false,
                ]);

            if (is_wp_error($locations) || empty($locations)) {
                return '';
            }

            $address = '';
            $address_country = '';

            if ($order instanceof WC_Order) {
                $address = $this->get_order_shipping_address_string($order);
                $address_country = $this->get_order_country_for_assignment($order);
            } else {
                $customer_address = $this->get_customer_address_for_transfer_destination();
                $address = isset($customer_address['address']) ? (string) $customer_address['address'] : '';
                $address_country = isset($customer_address['country']) ? (string) $customer_address['country'] : '';
            }

            $candidate_locations = array_values(array_filter($locations, static function ($location) {
                return isset($location->term_id, $location->slug);
            }));

            if (empty($candidate_locations)) {
                return '';
            }

            $fallback_slug = $this->get_location_fallback_by_display_order($candidate_locations);

            if ($address === '') {
                return $fallback_slug;
            }

            $coords = $this->geocode_address($address, $address_country);
            if (empty($coords) || !isset($coords['lat'], $coords['lng'])) {
                return $fallback_slug;
            }

            $best_slug = '';
            $best_distance = null;
            $best_display_order = null;

            foreach ($candidate_locations as $location) {
                $location_slug = rawurldecode((string) $location->slug);
                if ($location_slug === '') {
                    continue;
                }

                $location_id = (int) $location->term_id;
                $lat = $this->normalize_location_coordinate_value(get_term_meta($location_id, 'latitude', true));
                $lng = $this->normalize_location_coordinate_value(get_term_meta($location_id, 'longitude', true));

                if ($lat === null || $lng === null) {
                    continue;
                }

                if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                    continue;
                }

                $distance = $this->calculate_haversine_distance_km(
                    (float) $coords['lat'],
                    (float) $coords['lng'],
                    $lat,
                    $lng
                );

                $display_order = get_term_meta($location_id, 'display_order', true);
                $display_order = $display_order !== '' ? (int) $display_order : 999;

                if ($best_distance === null || $distance < $best_distance - 0.000001) {
                    $best_slug = $location_slug;
                    $best_distance = $distance;
                    $best_display_order = $display_order;
                    continue;
                }

                if (abs($distance - $best_distance) <= 0.000001) {
                    if ($best_display_order === null || $display_order < $best_display_order) {
                        $best_slug = $location_slug;
                        $best_display_order = $display_order;
                    } elseif ($display_order === $best_display_order && strcmp($location_slug, $best_slug) < 0) {
                        $best_slug = $location_slug;
                    }
                }
            }

            return $best_slug !== '' ? $best_slug : $fallback_slug;
        }

        /**
         * Get the currently selected location term for transfer fallback.
         *
         * @return WP_Term|null
         */
        private function get_selected_location_term_for_transfer()
        {
            $selected_location = $this->get_current_location();
            if ($selected_location === '' || $selected_location === 'all-products') {
                return null;
            }

            $selected_term = mulopimfwc_validate_location_slug($selected_location);
            if (!$selected_term || is_wp_error($selected_term)) {
                return null;
            }

            return $selected_term;
        }

        /**
         * Build customer shipping/billing address and country for transfer resolution.
         *
         * @return array{address: string, country: string, city: string}
         */
        private function get_customer_address_for_transfer_destination(): array
        {
            $posted_address = $this->get_checkout_posted_address_for_transfer_destination();
            if ($posted_address['address'] !== '') {
                return $posted_address;
            }

            if (!function_exists('WC') || !WC()) {
                return ['address' => '', 'country' => '', 'city' => ''];
            }

            if (!WC()->customer && function_exists('wc_load_cart')) {
                wc_load_cart();
            }

            if (!WC()->customer) {
                return ['address' => '', 'country' => '', 'city' => ''];
            }

            $customer = WC()->customer;

            $shipping_country = $this->normalize_country_for_geocoding($customer->get_shipping_country());
            $shipping_state = $this->normalize_state_for_geocoding($shipping_country, $customer->get_shipping_state());
            $shipping_city = trim((string) $customer->get_shipping_city());
            $shipping_parts = $this->sanitize_address_parts([
                $customer->get_shipping_address_1(),
                $customer->get_shipping_address_2(),
                $shipping_city,
                $shipping_state,
                $customer->get_shipping_postcode(),
                $shipping_country,
            ]);

            if (!empty($shipping_parts)) {
                return [
                    'address' => implode(', ', $shipping_parts),
                    'country' => $shipping_country,
                    'city' => $shipping_city,
                ];
            }

            $billing_country = $this->normalize_country_for_geocoding($customer->get_billing_country());
            $billing_state = $this->normalize_state_for_geocoding($billing_country, $customer->get_billing_state());
            $billing_city = trim((string) $customer->get_billing_city());
            $billing_parts = $this->sanitize_address_parts([
                $customer->get_billing_address_1(),
                $customer->get_billing_address_2(),
                $billing_city,
                $billing_state,
                $customer->get_billing_postcode(),
                $billing_country,
            ]);

            if (!empty($billing_parts)) {
                return [
                    'address' => implode(', ', $billing_parts),
                    'country' => $billing_country,
                    'city' => $billing_city,
                ];
            }

            return ['address' => '', 'country' => '', 'city' => ''];
        }

        /**
         * Read shipping/billing address from checkout POST payload when available.
         *
         * @return array{address: string, country: string, city: string}
         */
        private function get_checkout_posted_address_for_transfer_destination(): array
        {
            $posted = [];

            if (isset($_POST['post_data']) && !is_array($_POST['post_data'])) {
                $post_data_raw = wp_unslash((string) $_POST['post_data']);
                if ($post_data_raw !== '') {
                    $parsed = [];
                    parse_str($post_data_raw, $parsed);
                    if (is_array($parsed)) {
                        $posted = $parsed;
                    }
                }
            }

            if (empty($posted) && !empty($_POST) && is_array($_POST)) {
                $posted = $_POST;
            }

            if (empty($posted)) {
                return ['address' => '', 'country' => '', 'city' => ''];
            }

            $get_posted_value = static function (array $source, string $key): string {
                if (!array_key_exists($key, $source)) {
                    return '';
                }

                $value = $source[$key];
                if (!is_scalar($value)) {
                    return '';
                }

                return trim(sanitize_text_field(wp_unslash((string) $value)));
            };

            $shipping_country = $this->normalize_country_for_geocoding($get_posted_value($posted, 'shipping_country'));
            $shipping_state = $this->normalize_state_for_geocoding($shipping_country, $get_posted_value($posted, 'shipping_state'));
            $shipping_parts = $this->sanitize_address_parts([
                $get_posted_value($posted, 'shipping_address_1'),
                $get_posted_value($posted, 'shipping_address_2'),
                $get_posted_value($posted, 'shipping_city'),
                $shipping_state,
                $get_posted_value($posted, 'shipping_postcode'),
                $shipping_country,
            ]);

            $billing_country = $this->normalize_country_for_geocoding($get_posted_value($posted, 'billing_country'));
            $billing_state = $this->normalize_state_for_geocoding($billing_country, $get_posted_value($posted, 'billing_state'));
            $billing_parts = $this->sanitize_address_parts([
                $get_posted_value($posted, 'billing_address_1'),
                $get_posted_value($posted, 'billing_address_2'),
                $get_posted_value($posted, 'billing_city'),
                $billing_state,
                $get_posted_value($posted, 'billing_postcode'),
                $billing_country,
            ]);

            $ship_to_different = strtolower($get_posted_value($posted, 'ship_to_different_address'));
            if ($ship_to_different === '') {
                $ship_to_different = strtolower($get_posted_value($posted, 'ship_to_different'));
            }

            $use_shipping = in_array($ship_to_different, ['1', 'yes', 'on', 'true'], true);

            if ($use_shipping && !empty($shipping_parts)) {
                return [
                    'address' => implode(', ', $shipping_parts),
                    'country' => $shipping_country,
                    'city' => $get_posted_value($posted, 'shipping_city'),
                ];
            }

            if (!empty($billing_parts)) {
                return [
                    'address' => implode(', ', $billing_parts),
                    'country' => $billing_country,
                    'city' => $get_posted_value($posted, 'billing_city'),
                ];
            }

            if (!empty($shipping_parts)) {
                return [
                    'address' => implode(', ', $shipping_parts),
                    'country' => $shipping_country,
                    'city' => $get_posted_value($posted, 'shipping_city'),
                ];
            }

            return ['address' => '', 'country' => '', 'city' => ''];
        }

        /**
         * Normalize stored coordinate values and tolerate comma decimals.
         *
         * @param mixed $value
         * @return float|null
         */
        private function normalize_location_coordinate_value($value): ?float
        {
            if (!is_scalar($value)) {
                return null;
            }

            $value = trim((string) $value);
            if ($value === '') {
                return null;
            }

            $value = str_replace(',', '.', $value);
            if (!is_numeric($value)) {
                return null;
            }

            return (float) $value;
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
            $group_cart = mulopimfwc_is_group_cart_enabled($mulopimfwc_options) ? 'on' : 'off';

            // Ensure location data is available for WooCommerce Blocks/Store API requests
            $is_store_api_request = false;
            if (function_exists('wc_is_store_api_request')) {
                $is_store_api_request = wc_is_store_api_request();
            } elseif (defined('REST_REQUEST') && REST_REQUEST) {
                $request_uri = isset($_SERVER['REQUEST_URI'])
                    ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']))
                    : '';
                $rest_route = isset($_GET['rest_route'])
                    ? sanitize_text_field(wp_unslash($_GET['rest_route']))
                    : '';
                $is_store_api_request = (
                    strpos($request_uri, '/wc/store/') !== false ||
                    strpos($rest_route, '/wc/store/') !== false
                );
            }

            $location_name = isset($cart_item['mulopimfwc_location_name'])
                ? $cart_item['mulopimfwc_location_name']
                : '';

            if ($location_name === '' && !empty($cart_item['mulopimfwc_location']) && $cart_item['mulopimfwc_location'] !== 'unknown') {
                $location_term = get_term_by('slug', $cart_item['mulopimfwc_location'], 'mulopimfwc_store_location');
                if ($location_term && !is_wp_error($location_term)) {
                    $location_name = $location_term->name;
                }
            }

            // Always show location information when available
            // Check if location data exists
            if ($location_name !== '' && ($group_cart !== 'on' || is_cart() || $is_store_api_request)) {
                $item_data[] = array(
                    'key'     => __('Location', 'multi-location-product-and-inventory-management'),
                    'value'   => esc_html($location_name),
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
            $allow_mixed = mulopimfwc_is_mixed_location_cart_enabled($mulopimfwc_options) ? 'on' : 'off';
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
            $assignment_method = function_exists('mulopimfwc_get_effective_order_assignment_method')
                ? mulopimfwc_get_effective_order_assignment_method($options)
                : (isset($options['order_assignment_method']) ? $options['order_assignment_method'] : 'customer_selection');

            // Always save location data if available, regardless of mixed cart setting
            // This ensures proper stock management and price tracking
            // Only save the internal meta field - display is handled by display_location_in_order_items()
            if (isset($values['mulopimfwc_location']) && $values['mulopimfwc_location'] !== '') {
                $item->add_meta_data('_mulopimfwc_location', $values['mulopimfwc_location'], true);
                return;
            }

            // Fallback: if product has exactly one assigned location, store it automatically
            $product_id = isset($values['product_id']) ? (int) $values['product_id'] : 0;
            $variation_id = isset($values['variation_id']) ? (int) $values['variation_id'] : 0;
            if (!$product_id && is_object($item) && method_exists($item, 'get_product_id')) {
                $product_id = (int) $item->get_product_id();
            }
            if (!$variation_id && is_object($item) && method_exists($item, 'get_variation_id')) {
                $variation_id = (int) $item->get_variation_id();
            }

            if ($product_id) {
                if ($assignment_method === 'manual') {
                    static $manual_shared_single_slug = null;
                    if ($manual_shared_single_slug === null) {
                        $manual_shared_single_slug = $this->get_cart_shared_single_assigned_location_slug();
                    }

                    // In manual mode, auto-assignment is allowed only when all cart items
                    // are single-location items and they share the exact same location.
                    if ($manual_shared_single_slug === '') {
                        return;
                    }
                }

                $location_slug = $this->get_single_assigned_location_slug_for_item($product_id, $variation_id);
                if ($location_slug !== '') {
                    $item->add_meta_data('_mulopimfwc_location', $location_slug, true);
                }
            }
        }

        /**
         * Display location in order items (admin) with location selector
         */

        public function display_location_in_order_items($item_id, $item, $product)
        {
            // Only handle product line items
            if (!$item || !method_exists($item, 'is_type') || !$item->is_type('line_item')) {
                return;
            }

            // Get current location from order item meta
            $current_location = $item->get_meta('_mulopimfwc_location');
            
            // Get product ID (variation or simple)
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $target_id = $variation_id ? $variation_id : $product_id;
            
            // Get all available locations for this product
            $product_obj = $variation_id ? wc_get_product($variation_id) : wc_get_product($product_id);
            if (!$product_obj) {
                return;
            }
            
            // Get product locations
            $product_locations = get_the_terms($product_id, 'mulopimfwc_store_location');
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
            $enable_all_locations = mulopimfwc_is_all_locations_enabled($options) ? 'on' : 'off';
            $is_currency_locked = function_exists('mulopimfwc_is_location_wise_currency_enabled')
                ? mulopimfwc_is_location_wise_currency_enabled($options)
                : (
                    isset($options['enable_location_price'], $options['location_wise_currency']) &&
                    $options['enable_location_price'] === 'on' &&
                    $options['location_wise_currency'] === 'on'
                );
            
            // If product has specific locations, use those; otherwise use all locations if enabled
            if (!empty($product_locations) && !is_wp_error($product_locations)) {
                $available_locations = $product_locations;
            } elseif ($enable_all_locations === 'on') {
                global $mulopimfwc_locations;
                if (empty($mulopimfwc_locations)) {
                    $mulopimfwc_locations = get_terms([
                        'taxonomy' => 'mulopimfwc_store_location',
                        'hide_empty' => false,
                    ]);
                }
                $available_locations = $mulopimfwc_locations;
            } else {
                $available_locations = [];
            }
            
            if (empty($available_locations) || is_wp_error($available_locations)) {
                // Fallback: just show current location if available
                if (!empty($current_location)) {
                    $location_term = get_term_by('slug', $current_location, 'mulopimfwc_store_location');
                    if ($location_term && !is_wp_error($location_term)) {
                        echo '<div class="mulopimfwc-order-item-location">';
                        echo '<strong>' . esc_html__('Location:', 'multi-location-product-and-inventory-management') . '</strong> ';
                        echo esc_html($location_term->name);
                        echo '</div>';
                    }
                }
                return;
            }
            
            // Get order to check if it's editable
            // Try multiple methods to get order ID for compatibility
            $order_id = 0;
            if (method_exists($item, 'get_order_id')) {
                $order_id = $item->get_order_id();
            } elseif (isset($item->order_id)) {
                $order_id = $item->order_id;
            } elseif (function_exists('wp_get_post_parent_id')) {
                $order_id = wp_get_post_parent_id($item_id);
            }
            
            $is_editable = false;
            if ($order_id) {
                $order = wc_get_order($order_id);
                $is_editable = $order && method_exists($order, 'is_editable') && $order->is_editable();
            }

            $can_manage_order = true;
            if (class_exists('MULOPIMFWC_Location_Managers')) {
                $can_manage_order = !MULOPIMFWC_Location_Managers::current_user_is_view_only_order_manager();
            }
            
            // Get order ID for data attribute
            $order_id_for_attr = 0;
            if ($order_id) {
                $order_id_for_attr = $order_id;
            } elseif (method_exists($item, 'get_order_id')) {
                $order_id_for_attr = $item->get_order_id();
            } elseif (isset($item->order_id)) {
                $order_id_for_attr = $item->order_id;
            }
            
            echo '<div class="mulopimfwc-order-item-location-selector" data-item-id="' . esc_attr($item_id) . '" data-order-id="' . esc_attr($order_id_for_attr) . '" data-product-id="' . esc_attr($product_id) . '" data-variation-id="' . esc_attr($variation_id) . '" data-quantity="' . esc_attr($item->get_quantity()) . '">';
            echo '<strong>' . esc_html__('Location:', 'multi-location-product-and-inventory-management') . '</strong> ';
            
            $show_selector = !$is_currency_locked && $can_manage_order && $is_editable && (count($available_locations) > 1 || empty($current_location));
            if ($show_selector) {
                // Show dropdown selector for editable orders with multiple locations
                $current_quantity = $item->get_quantity();
                echo '<select class="mulopimfwc-order-item-location-select" data-item-id="' . esc_attr($item_id) . '" style="margin-left: 5px; min-width: 200px;">';
                // Option to unassign location (remove assignment)
                echo '<option value=""';
                if (empty($current_location)) {
                    echo ' selected';
                }
                echo '>' . esc_html__('No location assigned', 'multi-location-product-and-inventory-management') . '</option>';
                foreach ($available_locations as $location) {
                    $location_slug = rawurldecode($location->slug);
                    $selected = ($current_location === $location_slug) ? 'selected' : '';
                    
                    // Get stock for this location
                    $location_stock = get_post_meta($target_id, '_location_stock_' . $location->term_id, true);
                    $location_backorders = get_post_meta($target_id, '_location_backorders_' . $location->term_id, true);
                    
                    // Format stock display
                    $stock_display = '';
                    $format = function_exists('mulopimfwc_get_stock_display_format')
                        ? mulopimfwc_get_stock_display_format()
                        : 'exact_count';
                    if ($format !== 'hide_stock') {
                        if ($location_stock !== '') {
                            $stock_qty = (int) $location_stock;
                            $stock_data = function_exists('mulopimfwc_build_stock_display_label')
                                ? mulopimfwc_build_stock_display_label([
                                    'stock_qty' => $stock_qty,
                                    'backorders' => $location_backorders,
                                    'location_id' => (int) $location->term_id,
                                    'count_format' => 'paren',
                                    'backorder_label' => 'backorder',
                                ])
                                : ['show' => true, 'label' => sprintf(mulopimfwc_get_text_value('text_variation_in_stock'), $stock_qty)];

                            if (!empty($stock_data['show']) && $stock_data['label'] !== '') {
                                $stock_display = ' (' . $stock_data['label'];
                                if ($stock_qty < $current_quantity && $location_backorders === 'off') {
                                    $stock_display .= ' - ' . __('Insufficient', 'multi-location-product-and-inventory-management');
                                }
                                $stock_display .= ')';
                            }
                        } else {
                            $stock_display = ' (' . __('No stock set', 'multi-location-product-and-inventory-management') . ')';
                        }
                    }
                    
                    // Disable option if insufficient stock and backorders not allowed
                    $disabled = '';
                    if ($location_stock !== '' && (int) $location_stock < $current_quantity && $location_backorders === 'off') {
                        $disabled = 'disabled';
                    }
                    
                    echo '<option value="' . esc_attr($location_slug) . '" ' . $selected . ' ' . $disabled . '>' . esc_html($location->name) . $stock_display . '</option>';
                }
                echo '</select>';
                echo '<span class="mulopimfwc-location-updating" style="display:none; margin-left: 10px; color: #2271b1;">' . esc_html__('Updating...', 'multi-location-product-and-inventory-management') . '</span>';
            } else {
                // Show text only for non-editable orders or single location
                if (!empty($current_location)) {
                    $location_term = get_term_by('slug', $current_location, 'mulopimfwc_store_location');
                    if ($location_term && !is_wp_error($location_term)) {
                        echo esc_html($location_term->name);
                    } else {
                        echo esc_html($current_location);
                    }
                } else {
                    echo '<span style="color: #999;">' . esc_html__('Not set', 'multi-location-product-and-inventory-management') . '</span>';
                }

                if ($is_currency_locked) {
                    echo '<span style="display:block;margin-top:4px;color:#666;font-size:11px;">' . esc_html__('Locked while location-wise currency is enabled.', 'multi-location-product-and-inventory-management') . '</span>';
                }
            }
            
            echo '</div>';
        }

        /**
         * Hide location meta fields from order item meta display
         * Prevents duplicate location information from showing
         */
        public function hide_location_order_itemmeta($hidden_meta)
        {
            // Hide internal location meta field and any "Store Location" meta
            // We display location via display_location_in_order_items() instead
            $hidden_meta[] = '_mulopimfwc_location';
            $hidden_meta[] = __('Store Location', 'multi-location-product-and-inventory-management');
            // Hide price meta field (used internally, not needed in display)
            $hidden_meta[] = '_price';
            return $hidden_meta;
        }

        /**
         * AJAX handler to update order item location and adjust stock
         */
        public function update_order_item_location()
        {
            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mulopimfwc_update_order_item_location')) {
                wp_send_json_error(['message' => __('Security check failed', 'multi-location-product-and-inventory-management')]);
            }

            // Check user permissions
            if (!current_user_can('edit_shop_orders')) {
                wp_send_json_error(['message' => __('You do not have permission to edit orders', 'multi-location-product-and-inventory-management')]);
            }

            if (
                class_exists('MULOPIMFWC_Location_Managers') &&
                MULOPIMFWC_Location_Managers::current_user_is_view_only_order_manager()
            ) {
                wp_send_json_error(['message' => __('You do not have permission to edit orders', 'multi-location-product-and-inventory-management')]);
            }

            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
            if (
                function_exists('mulopimfwc_is_location_wise_currency_enabled') &&
                mulopimfwc_is_location_wise_currency_enabled($options)
            ) {
                wp_send_json_error(['message' => __('Location updates are locked when location-wise currency is enabled.', 'multi-location-product-and-inventory-management')]);
            }

            // Get and validate input
            $item_id = isset($_POST['item_id']) ? absint($_POST['item_id']) : 0;
            $new_location_slug = isset($_POST['location_slug']) ? sanitize_text_field(rawurldecode($_POST['location_slug'])) : '';

            if (!$item_id || empty($new_location_slug)) {
                wp_send_json_error(['message' => __('Invalid item ID or location', 'multi-location-product-and-inventory-management')]);
            }

            // Get order - try to get order ID from POST first (from data attribute), then fallback methods
            $order = null;
            $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
            
            // If order ID not in POST, try to get from item
            if (!$order_id) {
                if (function_exists('wp_get_post_parent_id')) {
                    $order_id = wp_get_post_parent_id($item_id);
                }
            }
            
            if ($order_id) {
                $order = wc_get_order($order_id);
            }
            
            if (!$order) {
                wp_send_json_error(['message' => __('Order not found', 'multi-location-product-and-inventory-management')]);
            }

            $item = $order->get_item($item_id);
            if (!$item) {
                wp_send_json_error(['message' => __('Order item not found', 'multi-location-product-and-inventory-management')]);
            }

            // Check if order is editable
            if (!$order->is_editable()) {
                wp_send_json_error(['message' => __('This order is no longer editable', 'multi-location-product-and-inventory-management')]);
            }

            // Get current location
            $old_location_slug = $item->get_meta('_mulopimfwc_location');
            
            // If location hasn't changed, return success
            if ($old_location_slug === $new_location_slug) {
                wp_send_json_success(['message' => __('Location unchanged', 'multi-location-product-and-inventory-management')]);
            }

            // Validate new location exists
            $new_location_term = get_term_by('slug', $new_location_slug, 'mulopimfwc_store_location');
            if (!$new_location_term || is_wp_error($new_location_term)) {
                wp_send_json_error(['message' => __('Location not found', 'multi-location-product-and-inventory-management')]);
            }

            // Get product details
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $quantity = $item->get_quantity();
            $target_id = $variation_id ? $variation_id : $product_id;

            // Validate stock availability for new location before allowing change
            $enable_location_stock = isset($options['enable_location_stock']) && $options['enable_location_stock'] === 'on';
            
            if ($enable_location_stock) {
                $new_location_id = $new_location_term->term_id;
                $new_location_stock = get_post_meta($target_id, '_location_stock_' . $new_location_id, true);
                $new_location_backorders = get_post_meta($target_id, '_location_backorders_' . $new_location_id, true);
                
                // Check if new location has enough stock
                if ($new_location_stock !== '') {
                    $available_stock = (int) $new_location_stock;
                    
                    // If backorders are not allowed and we don't have enough stock
                    if ($new_location_backorders === 'off' && $available_stock < $quantity) {
                        $product = wc_get_product($target_id);
                        $product_name = $product ? $product->get_name() : __('Product', 'multi-location-product-and-inventory-management');
                        
                        wp_send_json_error([
                            'message' => sprintf(
                                __('Cannot change location. Insufficient stock at %s location. Available: %d, Required: %d', 'multi-location-product-and-inventory-management'),
                                $new_location_term->name,
                                $available_stock,
                                $quantity
                            )
                        ]);
                    }
                }
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
                $new_location_id = $new_location_term->term_id;
                $new_stock = get_post_meta($target_id, '_location_stock_' . $new_location_id, true);
                
                if ($new_stock !== '') {
                    $current_stock_int = (int) $new_stock;
                    $updated_stock = max(0, $current_stock_int - (int) $quantity);
                    update_post_meta($target_id, '_location_stock_' . $new_location_id, $updated_stock);
                }
            }

            // Update order item price if location pricing is enabled
            $enable_location_price = isset($options['enable_location_price']) && $options['enable_location_price'] === 'on';
            $old_price = $item->get_subtotal();
            $new_price = $old_price;
            
            if ($enable_location_price) {
                $new_location_id = $new_location_term->term_id;
                
                // Get location-specific price
                $location_sale_price = get_post_meta($target_id, '_location_sale_price_' . $new_location_id, true);
                $location_regular_price = get_post_meta($target_id, '_location_regular_price_' . $new_location_id, true);
                
                // Use sale price if configured (including zero), otherwise regular price.
                if ($location_sale_price !== '' && $location_sale_price !== null) {
                    $new_price_per_unit = floatval($location_sale_price);
                } elseif ($location_regular_price !== '' && $location_regular_price !== null) {
                    $new_price_per_unit = floatval($location_regular_price);
                } else {
                    // No location-specific price, use product's default price
                    $product_obj = wc_get_product($target_id);
                    if ($product_obj) {
                        // Get the actual price (sale price if on sale, otherwise regular price)
                        $sale_price = $product_obj->get_sale_price();
                        $regular_price = $product_obj->get_regular_price();
                        $new_price_per_unit = ($sale_price !== '' && $sale_price !== null)
                            ? floatval($sale_price)
                            : floatval($regular_price);
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
            }
            
            // Update order item meta
            $item->update_meta_data('_mulopimfwc_location', $new_location_slug);
            $item->save();

            // Update order location if all line items share the same location
            $order_location_updated = false;
            $order_location_slug = '';
            $line_items = $order->get_items('line_item');
            if (!empty($line_items)) {
                $shared_location = null;
                $all_same_location = true;

                foreach ($line_items as $line_item) {
                    $item_location = (string) $line_item->get_meta('_mulopimfwc_location');
                    if ($item_location === '') {
                        $all_same_location = false;
                        break;
                    }
                    if ($shared_location === null) {
                        $shared_location = $item_location;
                        continue;
                    }
                    if ($shared_location !== $item_location) {
                        $all_same_location = false;
                        break;
                    }
                }

                if ($all_same_location && $shared_location !== null) {
                    $current_order_location = (string) $order->get_meta('_store_location');
                    if ($current_order_location !== $shared_location) {
                        $order->update_meta_data('_store_location', $shared_location);
                        $order_location_updated = true;
                        $order_location_slug = $shared_location;
                    }
                }
            }
            
            // Recalculate order totals
            $order->calculate_totals();
            $order->save();

            // Log the change
            $old_location_name = $old_location_slug ? (get_term_by('slug', $old_location_slug, 'mulopimfwc_store_location') ? get_term_by('slug', $old_location_slug, 'mulopimfwc_store_location')->name : $old_location_slug) : __('Not set', 'multi-location-product-and-inventory-management');
            
            $note_message = sprintf(
                __('Order item location changed from %s to %s', 'multi-location-product-and-inventory-management'),
                $old_location_name,
                $new_location_term->name
            );
            
            // Add price change to note if price changed
            if ($enable_location_price && $old_price != $new_price) {
                $note_message .= sprintf(
                    __(' | Price updated from %s to %s', 'multi-location-product-and-inventory-management'),
                    wc_price($old_price),
                    wc_price($new_price)
                );
            }

            if ($order_location_updated) {
                $order_location_term = get_term_by('slug', $order_location_slug, 'mulopimfwc_store_location');
                $order_location_name = ($order_location_term && !is_wp_error($order_location_term)) ? $order_location_term->name : $order_location_slug;
                $note_message .= sprintf(
                    __(' | Order location set to %s', 'multi-location-product-and-inventory-management'),
                    $order_location_name
                );
            }
            
            $order->add_order_note($note_message);

            wp_send_json_success([
                'message' => __('Location and price updated successfully', 'multi-location-product-and-inventory-management'),
                'location_name' => $new_location_term->name,
                'price_changed' => $enable_location_price && $old_price != $new_price,
                'order_location_updated' => $order_location_updated,
                'order_location_slug' => $order_location_slug,
                'old_price' => $old_price,
                'new_price' => $new_price
            ]);
        }

        /**
         * Store old quantities before order items are saved (for stock validation and update)
         */
        private static $old_quantities = [];

        /**
         * Store old quantity before order item is saved
         */
        public function store_order_item_old_quantity($item)
        {
            // Only process line items
            if (!$item->is_type('line_item')) {
                return;
            }

            $item_id = $item->get_id();
            if (!$item_id) {
                return;
            }

            // Get old quantity directly from database to avoid issues with already-modified item object
            // WooCommerce stores order item quantity in order_itemmeta table
            $old_quantity = wc_get_order_item_meta($item_id, '_qty', true);
            
            if ($old_quantity !== false && $old_quantity !== '') {
                self::$old_quantities[$item_id] = floatval($old_quantity);
            } else {
                // Fallback: try to get from order
                $order_id = 0;
                if (method_exists($item, 'get_order_id')) {
                    $order_id = $item->get_order_id();
                } elseif (isset($item->order_id)) {
                    $order_id = $item->order_id;
                }

                if ($order_id) {
                    $order = wc_get_order($order_id);
                    if ($order) {
                        $old_item = $order->get_item($item_id);
                        if ($old_item) {
                            self::$old_quantities[$item_id] = $old_item->get_quantity();
                        }
                    }
                }
            }
        }

        /**
         * Process stock updates for all order items after order is saved
         */
        public function process_order_items_stock_update($order)
        {
            // Only process if we have stored old quantities (meaning items were updated)
            if (empty(self::$old_quantities)) {
                return;
            }

            // Check if location stock management is enabled
            global $mulopimfwc_options;
            $enable_location_stock = isset($mulopimfwc_options['enable_location_stock']) && $mulopimfwc_options['enable_location_stock'] === 'on';

            if (!$enable_location_stock) {
                self::$old_quantities = [];
                return;
            }

            // Process each item that was changed
            foreach ($order->get_items() as $item_id => $item) {
                if (!isset(self::$old_quantities[$item_id])) {
                    continue;
                }

                $this->validate_and_update_order_item_stock($item, $item_id, $order);
            }

            // Clear all stored quantities
            self::$old_quantities = [];
        }

        /**
         * Validate stock and update location stock when order item quantity changes
         */
        private function validate_and_update_order_item_stock($item, $item_id, $order)
        {
            // Only process line items
            if (!$item->is_type('line_item')) {
                return;
            }

            // Check if location stock management is enabled
            global $mulopimfwc_options;
            $enable_location_stock = isset($mulopimfwc_options['enable_location_stock']) && $mulopimfwc_options['enable_location_stock'] === 'on';

            if (!$enable_location_stock) {
                return;
            }

            // Get location from order item
            $location_slug = $item->get_meta('_mulopimfwc_location');
            if (empty($location_slug)) {
                return; // No location set, skip
            }

            // Get product details
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $target_id = $variation_id ? $variation_id : $product_id;
            $new_quantity = $item->get_quantity();

            // Get old quantity from stored data
            $old_quantity = isset(self::$old_quantities[$item_id]) ? self::$old_quantities[$item_id] : null;

            // If no old quantity stored, this might be a new item - skip stock update
            // (new items are handled by woocommerce_reduce_order_stock hook)
            if ($old_quantity === null) {
                unset(self::$old_quantities[$item_id]);
                return;
            }

            // If quantity hasn't changed, skip
            if ($old_quantity == $new_quantity) {
                unset(self::$old_quantities[$item_id]);
                return;
            }

            // Get location ID
            $location_id = mulopimfwc_get_location_term_id($location_slug);
            if (!$location_id) {
                unset(self::$old_quantities[$item_id]);
                return;
            }

            // Get location stock
            $location_stock = get_post_meta($target_id, '_location_stock_' . $location_id, true);
            if ($location_stock === '') {
                unset(self::$old_quantities[$item_id]);
                return; // No location stock set, skip
            }

            // Get backorder setting
            $location_backorders = get_post_meta($target_id, '_location_backorders_' . $location_id, true);

            // Calculate quantity difference
            $quantity_difference = $new_quantity - $old_quantity;

            // If quantity increased, validate stock availability
            if ($quantity_difference > 0) {
                $available_stock = (int) $location_stock;
                
                // If backorders are not allowed and we don't have enough stock
                if ($location_backorders === 'off' && $available_stock < $quantity_difference) {
                    $location_term = get_term($location_id, 'mulopimfwc_store_location');
                    $location_name = $location_term && !is_wp_error($location_term) ? $location_term->name : $location_slug;
                    $product = wc_get_product($target_id);
                    $product_name = $product ? $product->get_name() : __('Product', 'multi-location-product-and-inventory-management');

                    // Revert quantity to old value and save
                    $item->set_quantity($old_quantity);
                    $item->save();
                    
                    // Recalculate order totals after reverting
                    $order->calculate_totals();
                    $order->save();

                    // Add error notice via order note and admin notice
                    $error_message = sprintf(
                        __('Cannot increase quantity for %s. Insufficient stock at %s location. Available: %d, Required: %d', 'multi-location-product-and-inventory-management'),
                        $product_name,
                        $location_name,
                        $available_stock,
                        $quantity_difference
                    );
                    
                    $order->add_order_note($error_message, 0, false);
                    
                    // Show admin notice
                    add_action('admin_notices', function() use ($error_message) {
                        echo '<div class="error notice is-dismissible">';
                        echo '<p>' . esc_html($error_message) . '</p>';
                        echo '</div>';
                    });

                    unset(self::$old_quantities[$item_id]);
                    return;
                }
            }

            // Update stock based on quantity difference
            $current_stock_int = (int) $location_stock;
            $new_stock = max(0, $current_stock_int - $quantity_difference);
            update_post_meta($target_id, '_location_stock_' . $location_id, $new_stock);

            // Get order for logging
            $order_id = method_exists($item, 'get_order_id') ? $item->get_order_id() : 0;
            if ($order_id) {
                $order = wc_get_order($order_id);
                if ($order) {
                    // Log the change
                    $location_term = get_term($location_id, 'mulopimfwc_store_location');
                    $location_name = $location_term && !is_wp_error($location_term) ? $location_term->name : $location_slug;
                    $product = wc_get_product($target_id);
                    $product_name = $product ? $product->get_name() : __('Product', 'multi-location-product-and-inventory-management');

                    $order->add_order_note(sprintf(
                        __('Order item quantity changed for %s at %s location: %d → %d (Stock: %d → %d)', 'multi-location-product-and-inventory-management'),
                        $product_name,
                        $location_name,
                        $old_quantity,
                        $new_quantity,
                        $current_stock_int,
                        $new_stock
                    ));
                }
            }

            // Clean up stored quantity
            unset(self::$old_quantities[$item_id]);
        }

        /**
         * Validate cart items when location changes (with mixed cart enabled)
         */

        public function validate_mixed_cart_locations()
        {
            global $mulopimfwc_options;

            // Check if mixed location cart is enabled
            $allow_mixed = mulopimfwc_is_mixed_location_cart_enabled($mulopimfwc_options) ? 'on' : 'off';

            if ($allow_mixed !== 'on') {
                return;
            }

            // Validate each cart item still belongs to its stored location
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                if (isset($cart_item['mulopimfwc_location'])) {
                    $product_id = $cart_item['product_id'];
                    $stored_location = $cart_item['mulopimfwc_location'];

                    // Check if product still belongs to stored location
                    $terms = array_map('rawurldecode',wp_get_object_terms($product_id, 'mulopimfwc_store_location', ['fields' => 'slugs']));

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
            $allow_mixed = mulopimfwc_is_mixed_location_cart_enabled($mulopimfwc_options) ? 'on' : 'off';

            if ($allow_mixed !== 'on') {
                return array();
            }

            $items_by_location = array();
            $location_name_cache = array();

            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                $location = isset($cart_item['mulopimfwc_location'])
                    ? $cart_item['mulopimfwc_location']
                    : 'unknown';

                $location_name = isset($cart_item['mulopimfwc_location_name'])
                    ? $cart_item['mulopimfwc_location_name']
                    : '';

                if ($location_name === '' && $location !== 'unknown') {
                    if (!isset($location_name_cache[$location])) {
                        $location_term = get_term_by('slug', $location, 'mulopimfwc_store_location');
                        $location_name_cache[$location] = ($location_term && !is_wp_error($location_term)) ? $location_term->name : '';
                    }
                    $location_name = $location_name_cache[$location];
                }

                if (!isset($items_by_location[$location])) {
                    $items_by_location[$location] = array(
                        'location_name' => $location_name !== ''
                            ? $location_name
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
            $location_name_cache = array();

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

                $location_name = isset($cart_item['mulopimfwc_location_name'])
                    ? $cart_item['mulopimfwc_location_name']
                    : '';

                if ($location_name === '' && $location !== 'unknown') {
                    if (!isset($location_name_cache[$location])) {
                        $location_term = get_term_by('slug', $location, 'mulopimfwc_store_location');
                        $location_name_cache[$location] = ($location_term && !is_wp_error($location_term)) ? $location_term->name : '';
                    }
                    $location_name = $location_name_cache[$location];
                }

                if (!isset($items_by_location[$location])) {
                    $items_by_location[$location] = array(
                        'location_name' => $location_name !== ''
                            ? $location_name
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
            $allow_mixed = mulopimfwc_is_mixed_location_cart_enabled($mulopimfwc_options) ? 'on' : 'off';

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
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);

            $product_to_check = $variation_id > 0 ? $variation_id : $product_id;
            $terms = wp_get_object_terms($product_to_check, 'mulopimfwc_store_location', ['fields' => 'all']);

            if (is_wp_error($terms) || empty($terms)) {
                // Check if enable_all_locations is on
                $enable_all_locations = mulopimfwc_is_all_locations_enabled($options) ? 'on' : 'off';
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
            $active_locations = array_filter($terms, function ($term) use ($product_to_check) {
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
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);

            if (mulopimfwc_is_manual_assignment_strict_mode($options)) {
                return;
            }

            if (!mulopimfwc_is_location_change_in_cart_enabled($options)) {
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
                global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
                $enable_all_locations = mulopimfwc_is_all_locations_enabled($options) ? 'on' : 'off';

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
            $available_locations = array_filter($terms, function ($term) use ($product_to_check) {
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
                        <option value="<?php echo esc_attr(rawurldecode($location->slug)); ?>" <?php selected($current_location_slug, rawurldecode($location->slug)); ?>>
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

            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
            if (!mulopimfwc_is_location_change_in_cart_enabled($options)) {
                wp_send_json_error(['message' => __('Location change in cart is disabled.', 'multi-location-product-and-inventory-management')]);
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

            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
            if (!mulopimfwc_is_location_change_in_cart_enabled($options)) {
                wp_send_json_error(['message' => __('Location change in cart is disabled.', 'multi-location-product-and-inventory-management')]);
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
                global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
                $enable_all_locations = mulopimfwc_is_all_locations_enabled($options) ? 'on' : 'off';

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
            $available_locations = array_filter($terms, function ($term) use ($product_to_check) {
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
            $locations_data = array_map(function ($term) {
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
            $group_cart = mulopimfwc_is_group_cart_enabled($mulopimfwc_options) ? 'on' : 'off';

            if ($group_cart !== 'on') {
                $this->should_group_cache = false;
                return false;
            }

            // Check if mixed location cart is enabled
            $allow_mixed = mulopimfwc_is_mixed_location_cart_enabled($mulopimfwc_options) ? 'on' : 'off';

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
                                            echo wp_kses_post(apply_filters('woocommerce_cart_item_backorder_notification', '<p class="backorder_notification">' . mulopimfwc_get_text_value('text_variation_backorder') . '</p>', $product_id));
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
                                                    echo wp_kses_post(apply_filters('woocommerce_cart_item_backorder_notification', '<p class="backorder_notification">' . mulopimfwc_get_text_value('text_variation_backorder') . '</p>', $product_id));
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
                                                        echo wp_kses_post(apply_filters('woocommerce_cart_item_backorder_notification', '<p class="backorder_notification">' . mulopimfwc_get_text_value('text_variation_backorder') . '</p>', $product_id));
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
                                            echo wp_kses_post(apply_filters('woocommerce_cart_item_backorder_notification', '<p class="backorder_notification">' . mulopimfwc_get_text_value('text_variation_backorder') . '</p>', $product_id));
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
         * Check if the current user can manage product data for locations
         */
        private function current_user_can_manage_products()
        {
            if (class_exists('MULOPIMFWC_Location_Managers')) {
                return MULOPIMFWC_Location_Managers::user_has_capability('manage_products');
            }

            return current_user_can('manage_woocommerce');
        }

        private function get_location_manager_allowed_slugs()
        {
            if (!class_exists('MULOPIMFWC_Location_Managers') || !is_user_logged_in()) {
                return null;
            }

            $user = wp_get_current_user();
            if (!in_array('mulopimfwc_location_manager', $user->roles, true)) {
                return null;
            }

            if (MULOPIMFWC_Location_Managers::user_has_capability('all_products')) {
                return null;
            }

            $assigned_locations = MULOPIMFWC_Location_Managers::get_user_assigned_locations();
            return is_array($assigned_locations) ? array_values(array_filter($assigned_locations)) : [];
        }

        private function get_location_manager_allowed_ids()
        {
            $allowed_slugs = $this->get_location_manager_allowed_slugs();
            if ($allowed_slugs === null) {
                return null;
            }

            if (empty($allowed_slugs)) {
                return [];
            }

            $terms = get_terms([
                'taxonomy' => 'mulopimfwc_store_location',
                'hide_empty' => false,
                'slug' => $allowed_slugs,
                'fields' => 'ids',
            ]);

            if (is_wp_error($terms)) {
                return [];
            }

            return array_map('intval', (array) $terms);
        }

        private function filter_locations_for_manager($locations)
        {
            $allowed_slugs = $this->get_location_manager_allowed_slugs();
            if (!is_array($allowed_slugs)) {
                return $locations;
            }

            if (!is_array($locations)) {
                return [];
            }

            if (empty($allowed_slugs)) {
                return [];
            }

            return array_values(array_filter($locations, function ($location) use ($allowed_slugs) {
                if (!is_object($location) || !isset($location->slug)) {
                    return false;
                }

                return in_array(rawurldecode($location->slug), $allowed_slugs, true);
            }));
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
            if (!$this->current_user_can_manage_products()) {
                wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'multi-location-product-and-inventory-management')]);
            }

            // Get product ID
            $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
            $location_selected = array_map('rawurldecode',wp_get_object_terms($product_id, 'mulopimfwc_store_location', ['fields' => 'slugs']));
            if (!$product_id) {
                wp_send_json_error(['message' => __('Invalid product ID.', 'multi-location-product-and-inventory-management')]);
            }

            if (is_wp_error($mulopimfwc_locations)) {
                wp_send_json_error(['message' => $mulopimfwc_locations->get_error_message()]);
            }

            $available_locations = $this->filter_locations_for_manager($mulopimfwc_locations);

            // Format locations for output
            $location_data = [];
            foreach ($available_locations as $location) {
                $location_data[] = [
                    'id' => $location->term_id,
                    'name' => $location->name,
                    'parent' => $location->parent,
                    'selected' => in_array(rawurldecode($location->slug), $location_selected),
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
            if (!$this->current_user_can_manage_products()) {
                wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'multi-location-product-and-inventory-management')]);
            }

            // Get product ID and location IDs
            $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
            $location_ids = isset($_POST['location_ids']) ? array_map('intval', (array) $_POST['location_ids']) : [];

            if (!$product_id) {
                wp_send_json_error(['message' => __('Invalid product ID.', 'multi-location-product-and-inventory-management')]);
            }

            $allowed_location_ids = $this->get_location_manager_allowed_ids();
            if (is_array($allowed_location_ids)) {
                $location_ids = array_values(array_intersect($location_ids, $allowed_location_ids));
                $existing_ids = wp_get_object_terms($product_id, 'mulopimfwc_store_location', ['fields' => 'ids']);
                $existing_ids = is_wp_error($existing_ids) ? [] : array_map('intval', $existing_ids);
                $preserved_ids = array_diff($existing_ids, $allowed_location_ids);
                $location_ids = array_values(array_unique(array_merge($preserved_ids, $location_ids)));
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
            if (!$this->current_user_can_manage_products()) {
                wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'multi-location-product-and-inventory-management')]);
            }

            // Get parameters
            $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
            $location_id = isset($_POST['location_id']) ? intval($_POST['location_id']) : 0;
            $action = isset($_POST['status_action']) ? sanitize_text_field(wp_unslash($_POST['status_action'])) : '';

            if (!$product_id || !$location_id || !in_array($action, ['activate', 'deactivate'])) {
                wp_send_json_error(['message' => __('Invalid parameters.', 'multi-location-product-and-inventory-management')]);
            }

            $allowed_location_ids = $this->get_location_manager_allowed_ids();
            if (is_array($allowed_location_ids) && !in_array($location_id, $allowed_location_ids, true)) {
                wp_send_json_error(['message' => __('You do not have permission to modify this location.', 'multi-location-product-and-inventory-management')]);
            }

            // Update location status
            if ($action === 'activate') {
                // Activate location - remove disabled meta
                delete_post_meta($product_id, '_location_disabled_' . $location_id);
                $message = __('Location activated successfully.', 'multi-location-product-and-inventory-management');
            } else {
                // Deactivate location - remove location from product
                // Remove the taxonomy term relationship
                wp_remove_object_terms($product_id, $location_id, 'mulopimfwc_store_location');
                
                // Clean up location-specific meta data
                delete_post_meta($product_id, '_location_disabled_' . $location_id);
                delete_post_meta($product_id, '_location_stock_' . $location_id);
                delete_post_meta($product_id, '_location_regular_price_' . $location_id);
                delete_post_meta($product_id, '_location_sale_price_' . $location_id);
                delete_post_meta($product_id, '_location_backorders_' . $location_id);
                
                // Also clean up for variations if it's a variable product
                $product = wc_get_product($product_id);
                if ($product && $product->is_type('variable')) {
                    $variation_ids = $product->get_children();
                    foreach ($variation_ids as $variation_id) {
                        delete_post_meta($variation_id, '_location_stock_' . $location_id);
                        delete_post_meta($variation_id, '_location_regular_price_' . $location_id);
                        delete_post_meta($variation_id, '_location_sale_price_' . $location_id);
                        delete_post_meta($variation_id, '_location_backorders_' . $location_id);
                    }
                }
                
                $message = __('Location removed from product successfully.', 'multi-location-product-and-inventory-management');
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
            if (!$this->current_user_can_manage_products()) {
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

            if (!current_user_can('edit_post', $product_id)) {
                wp_send_json_error(['message' => __('You do not have permission to edit this product.', 'multi-location-product-and-inventory-management')]);
            }

            global $mulopimfwc_locations;
            $available_locations = [];
            if (!is_wp_error($mulopimfwc_locations) && !empty($mulopimfwc_locations)) {
                $available_locations = $this->filter_locations_for_manager($mulopimfwc_locations);
            }

            $assigned_locations = wp_get_object_terms($product_id, 'mulopimfwc_store_location');
            if (is_wp_error($assigned_locations)) {
                $assigned_locations = [];
            } else {
                $assigned_locations = $this->filter_locations_for_manager($assigned_locations);
            }

            $assigned_location_ids = array_map('intval', (array) wp_list_pluck($assigned_locations, 'term_id'));
            $assigned_location_lookup = array_fill_keys($assigned_location_ids, true);
            $location_lookup = [];
            foreach ($available_locations as $location_term) {
                $location_lookup[(int) $location_term->term_id] = $location_term;
            }
            foreach ($assigned_locations as $location_term) {
                $location_lookup[(int) $location_term->term_id] = $location_term;
            }

            $product_type = $product->get_type();
            $variation_ids = $product_type === 'variable' ? array_map('intval', (array) $product->get_children()) : [];
            $meta_cache_ids = array_values(array_unique(array_merge([$product_id], $variation_ids)));
            if (!empty($meta_cache_ids)) {
                update_meta_cache('post', $meta_cache_ids);
            }

            $meta_rows_by_post = [];
            $get_cached_meta = static function ($post_id, $meta_key) use (&$meta_rows_by_post) {
                $post_id = (int) $post_id;
                $meta_key = (string) $meta_key;
                if ($post_id <= 0 || $meta_key === '') {
                    return '';
                }

                if (!isset($meta_rows_by_post[$post_id])) {
                    $meta_rows_by_post[$post_id] = get_post_meta($post_id);
                }

                if (!isset($meta_rows_by_post[$post_id][$meta_key][0])) {
                    return '';
                }

                return maybe_unserialize($meta_rows_by_post[$post_id][$meta_key][0]);
            };

            $resolve_currency_data = static function ($location_term_id = 0) {
                $location_term_id = (int) $location_term_id;
                $settings = function_exists('mulopimfwc_get_currency_settings_for_location')
                    ? (array) mulopimfwc_get_currency_settings_for_location($location_term_id > 0 ? $location_term_id : null)
                    : [
                        'currency' => strtoupper((string) get_option('woocommerce_currency', 'USD')),
                        'position' => (string) get_option('woocommerce_currency_pos', 'left'),
                    ];

                $currency_code = strtoupper(trim((string) ($settings['currency'] ?? '')));
                if ($currency_code === '') {
                    $currency_code = strtoupper((string) get_option('woocommerce_currency', 'USD'));
                }
                if ($currency_code === '') {
                    $currency_code = 'USD';
                }

                $currency_position = sanitize_key((string) ($settings['position'] ?? 'left'));
                if (!in_array($currency_position, ['left', 'right', 'left_space', 'right_space'], true)) {
                    $currency_position = 'left';
                }

                $currency_symbol = function_exists('get_woocommerce_currency_symbol')
                    ? (string) get_woocommerce_currency_symbol($currency_code)
                    : '';
                if ($currency_symbol === '') {
                    $currency_symbol = $currency_code;
                }

                $conversion_context = ($location_term_id > 0 && function_exists('mulopimfwc_get_location_currency_conversion_context'))
                    ? (array) mulopimfwc_get_location_currency_conversion_context($location_term_id)
                    : [
                        'should_convert' => false,
                        'rate' => 1.0,
                    ];

                $currency_rate = 1.0;
                if (isset($conversion_context['rate']) && is_numeric($conversion_context['rate']) && (float) $conversion_context['rate'] > 0) {
                    $currency_rate = (float) $conversion_context['rate'];
                }
                $currency_should_convert = !empty($conversion_context['should_convert']) && $currency_rate > 0;

                return [
                    'currency_code' => $currency_code,
                    'currency_symbol' => $currency_symbol,
                    'currency_position' => $currency_position,
                    'currency_rate' => $currency_rate,
                    'currency_should_convert' => $currency_should_convert,
                ];
            };

            $default_currency_data = $resolve_currency_data(0);

            $data = [
                'product_id' => $product_id,
                'id' => $product_id,
                'product_name' => $product->get_name(),
                'name' => $product->get_name(),
                'product_type' => $product_type,
                'type' => $product_type,
                'currency_code' => $default_currency_data['currency_code'],
                'currency_symbol' => $default_currency_data['currency_symbol'],
                'currency_position' => $default_currency_data['currency_position'],
                'default' => [],
                'locations' => [],
                'variations' => [],
                'all_locations' => [],
            ];

            // Get default product data
            $data['default'] = [
                'manage_stock' => $product->get_manage_stock() ? 'yes' : 'no',
                'stock_quantity' => $product->get_stock_quantity(),
                'regular_price' => $product->get_regular_price(),
                'sale_price' => $product->get_sale_price(),
                'backorders' => $product->get_backorders(),
                'purchase_price' => $get_cached_meta($product_id, '_purchase_price'),
                'purchase_quantity' => $get_cached_meta($product_id, '_purchase_quantity'),
            ];

            if (!empty($available_locations)) {
                foreach ($available_locations as $location) {
                    $location_id = (int) $location->term_id;
                    $location_currency_data = $resolve_currency_data($location_id);
                    $data['all_locations'][] = [
                        'id' => $location_id,
                        'name' => (string) $location->name,
                        'parent' => (int) $location->parent,
                        'selected' => isset($assigned_location_lookup[$location_id]),
                        'currency_code' => $location_currency_data['currency_code'],
                        'currency_symbol' => $location_currency_data['currency_symbol'],
                        'currency_position' => $location_currency_data['currency_position'],
                        'currency_rate' => $location_currency_data['currency_rate'],
                        'currency_should_convert' => $location_currency_data['currency_should_convert'],
                    ];
                }
            }

            foreach ($assigned_location_ids as $location_id) {
                if (!isset($location_lookup[$location_id])) {
                    continue;
                }

                $location = $location_lookup[$location_id];
                $location_currency_data = $resolve_currency_data($location_id);
                $data['locations'][] = [
                    'id' => $location_id,
                    'name' => (string) $location->name,
                    'stock' => $get_cached_meta($product_id, '_location_stock_' . $location_id),
                    'regular_price' => $get_cached_meta($product_id, '_location_regular_price_' . $location_id),
                    'sale_price' => $get_cached_meta($product_id, '_location_sale_price_' . $location_id),
                    'backorders' => $get_cached_meta($product_id, '_location_backorders_' . $location_id),
                    'currency_code' => $location_currency_data['currency_code'],
                    'currency_symbol' => $location_currency_data['currency_symbol'],
                    'currency_position' => $location_currency_data['currency_position'],
                    'currency_rate' => $location_currency_data['currency_rate'],
                    'currency_should_convert' => $location_currency_data['currency_should_convert'],
                ];
            }

            if ($product_type === 'variable' && !empty($variation_ids)) {
                foreach ($variation_ids as $variation_id) {
                    $variation = wc_get_product($variation_id);
                    if (!$variation) {
                        continue;
                    }

                    $attributes = [];
                    foreach ((array) $variation->get_attributes() as $key => $value) {
                        $attributes[$key] = $value;
                    }

                    $variation_info = [
                        'id' => $variation_id,
                        'attributes' => $attributes,
                        'default' => [
                            'manage_stock' => $variation->get_manage_stock() ? 'yes' : 'no',
                            'stock_quantity' => $variation->get_stock_quantity(),
                            'regular_price' => $variation->get_regular_price(),
                            'sale_price' => $variation->get_sale_price(),
                            'backorders' => $variation->get_backorders(),
                            'purchase_price' => $get_cached_meta($variation_id, '_purchase_price'),
                            'purchase_quantity' => $get_cached_meta($variation_id, '_purchase_quantity'),
                        ],
                        'locations' => [],
                    ];

                    foreach ($assigned_location_ids as $location_id) {
                        if (!isset($location_lookup[$location_id])) {
                            continue;
                        }

                        $location = $location_lookup[$location_id];
                        $location_currency_data = $resolve_currency_data($location_id);
                        $variation_info['locations'][] = [
                            'id' => $location_id,
                            'name' => (string) $location->name,
                            'stock' => $get_cached_meta($variation_id, '_location_stock_' . $location_id),
                            'regular_price' => $get_cached_meta($variation_id, '_location_regular_price_' . $location_id),
                            'sale_price' => $get_cached_meta($variation_id, '_location_sale_price_' . $location_id),
                            'backorders' => $get_cached_meta($variation_id, '_location_backorders_' . $location_id),
                            'currency_code' => $location_currency_data['currency_code'],
                            'currency_symbol' => $location_currency_data['currency_symbol'],
                            'currency_position' => $location_currency_data['currency_position'],
                            'currency_rate' => $location_currency_data['currency_rate'],
                            'currency_should_convert' => $location_currency_data['currency_should_convert'],
                        ];
                    }

                    $data['variations'][] = $variation_info;
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
            if (!$this->current_user_can_manage_products()) {
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

            $allowed_location_ids = $this->get_location_manager_allowed_ids();
            $restrict_locations = is_array($allowed_location_ids);
            $product_type = $product->get_type();
            $store_manage_stock_enabled = get_option('woocommerce_manage_stock', 'no') === 'yes';
            $product_supports_manage_stock = $store_manage_stock_enabled && !in_array($product_type, ['grouped', 'external'], true);
            $validated_variation_payloads = [];
            $normalize_location_backorders = static function ($value) {
                $raw = is_string($value) ? strtolower(trim($value)) : '';
                if (function_exists('mulopimfwc_normalize_backorder_value')) {
                    return mulopimfwc_normalize_backorder_value($raw, 'location');
                }

                if ($raw === 'yes' || $raw === 'on') {
                    return 'on';
                }
                if ($raw === 'notify') {
                    return 'notify';
                }
                if ($raw === 'no' || $raw === 'off') {
                    return 'off';
                }

                return '';
            };

            // Pre-validate variation payload ownership before any writes happen.
            if (isset($_POST['variations'])) {
                if (!is_array($_POST['variations'])) {
                    wp_send_json_error(['message' => __('Invalid variation payload.', 'multi-location-product-and-inventory-management')]);
                }

                if (!empty($_POST['variations'])) {
                    if ($product_type !== 'variable') {
                        wp_send_json_error(['message' => __('Variations can only be saved for variable products.', 'multi-location-product-and-inventory-management')]);
                    }

                    $product_variation_ids = array_map('intval', (array) $product->get_children());
                    $allowed_variation_lookup = array_fill_keys($product_variation_ids, true);

                    foreach ($_POST['variations'] as $variation_data) {
                        if (!is_array($variation_data)) {
                            wp_send_json_error(['message' => __('Invalid variation payload.', 'multi-location-product-and-inventory-management')]);
                        }

                        $variation_id = isset($variation_data['id']) ? intval($variation_data['id']) : 0;
                        if ($variation_id <= 0 || !isset($allowed_variation_lookup[$variation_id])) {
                            wp_send_json_error(['message' => __('One or more submitted variations do not belong to this product.', 'multi-location-product-and-inventory-management')]);
                        }

                        $variation_post = get_post($variation_id);
                        if (
                            !$variation_post ||
                            $variation_post->post_type !== 'product_variation' ||
                            (int) $variation_post->post_parent !== (int) $product_id
                        ) {
                            wp_send_json_error(['message' => __('One or more submitted variations do not belong to this product.', 'multi-location-product-and-inventory-management')]);
                        }

                        // Keep one payload per variation ID.
                        $validated_variation_payloads[$variation_id] = $variation_data;
                    }
                }
            }

            // Save default product data
            if (isset($_POST['default'])) {
                $default = $_POST['default'];
                $product_manage_stock = $product_supports_manage_stock ? $product->get_manage_stock() : false;
                if ($product_supports_manage_stock && isset($default['manage_stock'])) {
                    $manage_stock_raw = strtolower(sanitize_text_field($default['manage_stock']));
                    $product_manage_stock = in_array($manage_stock_raw, ['yes', 'on', '1', 'true'], true);
                    $product->set_manage_stock($product_manage_stock);
                }
                if (isset($default['stock_quantity']) && $product_supports_manage_stock && $product_manage_stock) {
                    $product->set_stock_quantity(intval($default['stock_quantity']));
                }
                if (isset($default['regular_price'])) {
                    $product->set_regular_price(wc_format_decimal($default['regular_price']));
                }
                if (isset($default['sale_price'])) {
                    $product->set_sale_price(wc_format_decimal($default['sale_price']));
                }
                if (isset($default['backorders']) && $product_supports_manage_stock && $product_manage_stock) {
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
                    if ($restrict_locations && !in_array($location_id, $allowed_location_ids, true)) {
                        continue;
                    }

                    $old_stock = get_post_meta($product_id, '_location_stock_' . $location_id, true);

                    if (isset($location_data['stock']) && $product_supports_manage_stock) {
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
                    if (isset($location_data['backorders']) && $product_supports_manage_stock) {
                        $normalized_location_backorders = $normalize_location_backorders($location_data['backorders']);
                        if ($normalized_location_backorders !== '') {
                            update_post_meta($product_id, '_location_backorders_' . $location_id, $normalized_location_backorders);
                        }
                    }
                }
            }

            // Set assigned locations (queue add/remove)
            if (isset($_POST['location_ids'])) {
                $submitted_location_ids = array_map('intval', (array) $_POST['location_ids']);
                if ($restrict_locations) {
                    $submitted_location_ids = array_values(array_intersect($submitted_location_ids, $allowed_location_ids));
                    $existing_ids = wp_get_object_terms($product_id, 'mulopimfwc_store_location', ['fields' => 'ids']);
                    $existing_ids = is_wp_error($existing_ids) ? [] : array_map('intval', $existing_ids);
                    $preserved_ids = array_diff($existing_ids, $allowed_location_ids);
                    $location_ids = array_values(array_unique(array_merge($preserved_ids, $submitted_location_ids)));
                } else {
                    $location_ids = $submitted_location_ids;
                }

                wp_set_object_terms($product_id, $location_ids, 'mulopimfwc_store_location');

                $removed_location_ids = isset($_POST['removed_location_ids']) ? array_map('intval', (array) $_POST['removed_location_ids']) : [];
                if ($restrict_locations) {
                    $removed_location_ids = array_values(array_intersect($removed_location_ids, $allowed_location_ids));
                }
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
            if (!empty($validated_variation_payloads)) {
                foreach ($validated_variation_payloads as $variation_data) {
                    $variation_id = isset($variation_data['id']) ? intval($variation_data['id']) : 0;
                    if (!$variation_id) {
                        continue;
                    }

                    $variation = wc_get_product($variation_id);
                    if (!$variation || $variation->get_type() !== 'variation' || (int) $variation->get_parent_id() !== (int) $product_id) {
                        wp_send_json_error(['message' => __('One or more submitted variations do not belong to this product.', 'multi-location-product-and-inventory-management')]);
                    }

                    // Save default variation data
                    if (isset($variation_data['default'])) {
                        $default = $variation_data['default'];
                        $variation_manage_stock = $variation->get_manage_stock();
                        if ($store_manage_stock_enabled && isset($default['manage_stock'])) {
                            $manage_stock_raw = strtolower(sanitize_text_field($default['manage_stock']));
                            $variation_manage_stock = in_array($manage_stock_raw, ['yes', 'on', '1', 'true'], true);
                            $variation->set_manage_stock($variation_manage_stock);
                        }
                        if (isset($default['stock_quantity']) && $store_manage_stock_enabled && $variation_manage_stock) {
                            $variation->set_stock_quantity(intval($default['stock_quantity']));
                        }
                        if (isset($default['regular_price'])) {
                            $variation->set_regular_price(wc_format_decimal($default['regular_price']));
                        }
                        if (isset($default['sale_price'])) {
                            $variation->set_sale_price(wc_format_decimal($default['sale_price']));
                        }
                        if (isset($default['backorders']) && $store_manage_stock_enabled && $variation_manage_stock) {
                            $variation->set_backorders(sanitize_text_field($default['backorders']));
                        }
                        if (isset($default['purchase_price'])) {
                            update_post_meta($variation_id, '_purchase_price', wc_format_decimal($default['purchase_price']));
                        }
                        if (isset($default['purchase_quantity'])) {
                            update_post_meta($variation_id, '_purchase_quantity', intval($default['purchase_quantity']));
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
                            if ($restrict_locations && !in_array($location_id, $allowed_location_ids, true)) {
                                continue;
                            }

                            if (isset($location_data['stock']) && $store_manage_stock_enabled) {
                                update_post_meta($variation_id, '_location_stock_' . $location_id, intval($location_data['stock']));
                            }
                            if (isset($location_data['regular_price'])) {
                                update_post_meta($variation_id, '_location_regular_price_' . $location_id, wc_format_decimal($location_data['regular_price']));
                            }
                            if (isset($location_data['sale_price'])) {
                                update_post_meta($variation_id, '_location_sale_price_' . $location_id, wc_format_decimal($location_data['sale_price']));
                            }
                            if (isset($location_data['backorders']) && $store_manage_stock_enabled) {
                                $normalized_location_backorders = $normalize_location_backorders($location_data['backorders']);
                                if ($normalized_location_backorders !== '') {
                                    update_post_meta($variation_id, '_location_backorders_' . $location_id, $normalized_location_backorders);
                                }
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
            if (!$this->current_user_can_manage_products()) {
                wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'multi-location-product-and-inventory-management')]);
            }

            // Get product ID and location ID
            $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
            $location_id = isset($_POST['location_id']) ? intval($_POST['location_id']) : 0;

            if (!$product_id || !$location_id) {
                wp_send_json_error(['message' => __('Invalid parameters.', 'multi-location-product-and-inventory-management')]);
            }

            $allowed_location_ids = $this->get_location_manager_allowed_ids();
            if (is_array($allowed_location_ids) && !in_array($location_id, $allowed_location_ids, true)) {
                wp_send_json_error(['message' => __('You do not have permission to modify this location.', 'multi-location-product-and-inventory-management')]);
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

            $admin_js_version = '1.1.4.11';
            $admin_js_path = plugin_dir_path(__FILE__) . 'assets/js/admin.js';
            if (file_exists($admin_js_path)) {
                $admin_js_version = (string) filemtime($admin_js_path);
            }

            $admin_css_version = '1.1.4.11';
            $admin_css_path = plugin_dir_path(__FILE__) . 'assets/css/admin.css';
            if (file_exists($admin_css_path)) {
                $admin_css_version = (string) filemtime($admin_css_path);
            }

            wp_enqueue_script(
                'mulopimfwc-multi-location-product-and-inventory-managements-admin',
                plugin_dir_url(__FILE__) . 'assets/js/admin.js',
                ['jquery'],
                $admin_js_version,
                true
            );

            $default_currency_code = strtoupper((string) get_option('woocommerce_currency', 'USD'));
            if ($default_currency_code === '') {
                $default_currency_code = 'USD';
            }
            $default_currency_symbol = function_exists('get_woocommerce_currency_symbol')
                ? (string) get_woocommerce_currency_symbol($default_currency_code)
                : '';
            if ($default_currency_symbol === '') {
                $default_currency_symbol = $default_currency_code;
            }

            wp_localize_script('mulopimfwc-multi-location-product-and-inventory-managements-admin', 'mulopimfwc_locationWiseProducts', [
                'nonce' => wp_create_nonce('location_wise_products_nonce'),
                'currencySymbol' => $default_currency_symbol,
                'currencyCode' => $default_currency_code,
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
                $admin_css_version
            );
        }

        /**
         * Save location from cookie to order meta
         *
         * @param WC_Order $order Order object
         * @param array $data Order data
         */
        public function save_location_to_order_meta($order_id, $data = null)
        {

            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
            $order = wc_get_order($order_id);
            if (!$order) {
                return;
            }
            
            $assignment_method = function_exists('mulopimfwc_get_effective_order_assignment_method')
                ? mulopimfwc_get_effective_order_assignment_method($options)
                : (isset($options['order_assignment_method']) ? $options['order_assignment_method'] : 'customer_selection');
            $shared_single_location = $this->get_shared_single_assigned_location_for_order($order);
            
            // Skip automatic assignment if manual mode is enabled
            if ($assignment_method === 'manual') {
                if (mulopimfwc_is_manual_optional_location_selection_enabled($options)) {
                    $selected_location = mulopimfwc_get_store_location_cookie();
                    if ($selected_location !== 'all-products' && mulopimfwc_validate_location_slug($selected_location)) {
                        if (!$this->is_location_slug_eligible_for_order($order, $selected_location, $options)) {
                            $selected_location = '';
                        }
                    } else {
                        $selected_location = '';
                    }

                    if ($selected_location !== '') {
                        $existing_location = (string) $order->get_meta('_store_location');
                        if ($existing_location === '') {
                            $order->update_meta_data('_store_location', $selected_location);
                            $order->save();
                            return;
                        }
                    }
                }

                // Manual mode exception: if every ordered item has exactly one assigned location
                // and all of them point to the same slug, assign that location to the order.
                if ($shared_single_location !== '') {
                    $existing_location = (string) $order->get_meta('_store_location');
                    if ($existing_location === '') {
                        $order->update_meta_data('_store_location', $shared_single_location);
                        $order->save();
                    }
                    return;
                }

                $existing_location = (string) $order->get_meta('_store_location');
                if ($existing_location === '') {
                    $current_status = $order->get_status();
                    if ($current_status !== 'on-hold' && !in_array($current_status, ['cancelled', 'refunded', 'failed'], true)) {
                        $order->update_status(
                            'on-hold',
                            __('Awaiting manual location assignment.', 'multi-location-product-and-inventory-management')
                        );
                    }
                }
                return; // Don't save location automatically
            }

            if ($assignment_method === 'inventory_based') {
                $selected_location = '';
                if (mulopimfwc_is_manual_optional_location_selection_enabled($options)) {
                    $selected_location = mulopimfwc_get_store_location_cookie();
                    if ($selected_location === 'all-products' || !mulopimfwc_validate_location_slug($selected_location)) {
                        $selected_location = '';
                    } elseif (!$this->is_location_slug_eligible_for_order($order, $selected_location, $options)) {
                        $selected_location = '';
                    }
                }

                $location = $selected_location !== ''
                    ? $selected_location
                    : $this->assign_location_by_inventory($order);

                if ($location === '' && $shared_single_location !== '') {
                    $location = $shared_single_location;
                }

                if (!empty($location)) {
                    $order->update_meta_data('_store_location', $location);
                    $order->save();
                }

                return;
            }

            if ($assignment_method === 'proximity_based') {
                $selected_location = '';
                if (mulopimfwc_is_manual_optional_location_selection_enabled($options)) {
                    $selected_location = mulopimfwc_get_store_location_cookie();
                    if ($selected_location === 'all-products' || !mulopimfwc_validate_location_slug($selected_location)) {
                        $selected_location = '';
                    } elseif (!$this->is_location_slug_eligible_for_order($order, $selected_location, $options)) {
                        $selected_location = '';
                    }
                }

                $location = $selected_location !== ''
                    ? $selected_location
                    : $this->assign_location_by_proximity($order, is_array($data) ? $data : null);

                if ($location === '' && $shared_single_location !== '') {
                    $location = $shared_single_location;
                }

                if (!empty($location)) {
                    $order->update_meta_data('_store_location', $location);
                    $order->save();
                }

                return;
            }

            // Use get_current_location method which includes default location logic
            $location = $this->get_current_location();

            if (!empty($location)) {
                $order->update_meta_data('_store_location', $location);
                $order->save();
            }
        }

        /**
         * Resolve exactly one assigned location slug for an item (variation first, then parent product).
         *
         * @param int $product_id Product ID.
         * @param int $variation_id Variation ID.
         * @return string Location slug when exactly one is assigned, otherwise empty string.
         */
        private function get_single_assigned_location_slug_for_item($product_id, $variation_id = 0): string
        {
            $ids_to_check = array_values(array_filter([absint($variation_id), absint($product_id)]));
            if (empty($ids_to_check)) {
                return '';
            }

            foreach ($ids_to_check as $id) {
                $terms = wp_get_object_terms($id, 'mulopimfwc_store_location', ['fields' => 'slugs']);
                if (is_wp_error($terms) || empty($terms)) {
                    continue;
                }

                $slugs = array_values(array_unique(array_map('rawurldecode', $terms)));
                if (count($slugs) === 1 && $slugs[0] !== '') {
                    return (string) $slugs[0];
                }

                return '';
            }

            return '';
        }

        /**
         * Determine if all cart line items are single-location items sharing the same location.
         *
         * @return string Shared location slug, or empty string when not resolvable.
         */
        private function get_cart_shared_single_assigned_location_slug(): string
        {
            if (!function_exists('WC') || !WC() || !WC()->cart) {
                return '';
            }

            $shared_slug = null;
            $processed_items = false;

            foreach (WC()->cart->get_cart() as $cart_item) {
                $product_id = isset($cart_item['product_id']) ? (int) $cart_item['product_id'] : 0;
                $variation_id = isset($cart_item['variation_id']) ? (int) $cart_item['variation_id'] : 0;

                if (!$product_id) {
                    continue;
                }

                $processed_items = true;
                $single_slug = $this->get_single_assigned_location_slug_for_item($product_id, $variation_id);
                if ($single_slug === '') {
                    return '';
                }

                if ($shared_slug === null) {
                    $shared_slug = $single_slug;
                    continue;
                }

                if ($shared_slug !== $single_slug) {
                    return '';
                }
            }

            if (!$processed_items || $shared_slug === null) {
                return '';
            }

            return $shared_slug;
        }

        /**
         * Determine if all order line items are single-location items sharing the same location.
         *
         * @param WC_Order|int $order Order object or ID.
         * @return string Shared location slug, or empty string when not resolvable.
         */
        private function get_shared_single_assigned_location_for_order($order): string
        {
            $order = $order instanceof WC_Order ? $order : wc_get_order($order);
            if (!$order) {
                return '';
            }

            $shared_slug = null;
            $processed_items = false;

            foreach ($order->get_items('line_item') as $item) {
                if (!$item->is_type('line_item')) {
                    continue;
                }

                $product_id = (int) $item->get_product_id();
                $variation_id = (int) $item->get_variation_id();
                if (!$product_id) {
                    continue;
                }

                $processed_items = true;
                $single_slug = $this->get_single_assigned_location_slug_for_item($product_id, $variation_id);
                if ($single_slug === '') {
                    return '';
                }

                if ($shared_slug === null) {
                    $shared_slug = $single_slug;
                    continue;
                }

                if ($shared_slug !== $single_slug) {
                    return '';
                }
            }

            if (!$processed_items || $shared_slug === null) {
                return '';
            }

            return $shared_slug;
        }

        /**
         * Assign a location to an order based on combined inventory across items.
         *
         * @param WC_Order|int $order Order object or ID.
         * @return string Location slug or empty string if none found.
         */
        private function assign_location_by_inventory($order): string
        {
            $order = $order instanceof WC_Order ? $order : wc_get_order($order);
            if (!$order) {
                return '';
            }

            $order_items = $order->get_items();
            if (empty($order_items)) {
                return '';
            }

            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);

            $locations = function_exists('mulopimfwc_get_frontend_locations')
                ? mulopimfwc_get_frontend_locations()
                : get_terms([
                    'taxonomy' => 'mulopimfwc_store_location',
                    'hide_empty' => false,
                ]);

            if (is_wp_error($locations) || empty($locations)) {
                return '';
            }

            $location_map = [];
            foreach ($locations as $location) {
                if (!isset($location->slug, $location->term_id)) {
                    continue;
                }

                $display_order = get_term_meta($location->term_id, 'display_order', true);
                $display_order = $display_order !== '' ? (int) $display_order : 999;

                $location_map[$location->slug] = [
                    'term_id' => (int) $location->term_id,
                    'display_order' => $display_order,
                    'total_stock' => 0,
                ];
            }

            if (empty($location_map)) {
                return '';
            }

            $all_location_slugs = array_keys($location_map);
            $enable_all_locations = mulopimfwc_is_all_locations_enabled($options) ? 'on' : 'off';
            $processed_items = false;

            foreach ($order_items as $item) {
                if (!$item->is_type('line_item')) {
                    continue;
                }

                $product_id = $item->get_product_id();
                $variation_id = $item->get_variation_id();

                if (!$product_id) {
                    continue;
                }

                $processed_items = true;

                $assigned_slugs = $this->get_item_assigned_location_slugs(
                    $product_id,
                    $variation_id,
                    $all_location_slugs,
                    $enable_all_locations
                );

                if (empty($assigned_slugs)) {
                    return '';
                }

                foreach (array_keys($location_map) as $location_slug) {
                    if (!in_array($location_slug, $assigned_slugs, true)) {
                        unset($location_map[$location_slug]);
                        continue;
                    }

                    $location_id = $location_map[$location_slug]['term_id'];
                    if ($this->is_location_disabled_for_item($product_id, $variation_id, $location_id)) {
                        unset($location_map[$location_slug]);
                        continue;
                    }

                    $location_map[$location_slug]['total_stock'] += $this->get_item_location_stock_quantity(
                        $product_id,
                        $variation_id,
                        $location_id
                    );
                }

                if (empty($location_map)) {
                    return '';
                }
            }

            if (!$processed_items) {
                return '';
            }

            $best_slug = '';
            $best_total = null;
            $best_display_order = null;

            foreach ($location_map as $location_slug => $data) {
                $total_stock = (int) $data['total_stock'];
                $display_order = (int) $data['display_order'];

                if ($best_total === null || $total_stock > $best_total) {
                    $best_slug = $location_slug;
                    $best_total = $total_stock;
                    $best_display_order = $display_order;
                    continue;
                }

                if ($total_stock === $best_total) {
                    if ($best_display_order === null || $display_order < $best_display_order) {
                        $best_slug = $location_slug;
                        $best_display_order = $display_order;
                    } elseif ($display_order === $best_display_order && strcmp($location_slug, $best_slug) < 0) {
                        $best_slug = $location_slug;
                    }
                }
            }

            return $best_slug;
        }

        /**
         * Build order-eligible location map by intersecting line-item assignments.
         *
         * @param WC_Order $order Order object.
         * @param array $locations Candidate locations.
         * @param array|null $options Optional plugin options.
         * @return array
         */
        private function get_order_eligible_location_map(WC_Order $order, array $locations, ?array $options = null): array
        {
            if (!is_array($options)) {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', []);
            }

            $location_map = [];
            foreach ($locations as $location) {
                if (!isset($location->slug, $location->term_id)) {
                    continue;
                }

                $location_slug = rawurldecode((string) $location->slug);
                if ($location_slug === '') {
                    continue;
                }

                $display_order = get_term_meta($location->term_id, 'display_order', true);
                $display_order = $display_order !== '' ? (int) $display_order : 999;

                $location_map[$location_slug] = [
                    'term_id' => (int) $location->term_id,
                    'display_order' => $display_order,
                    'location' => $location,
                ];
            }

            if (empty($location_map)) {
                return [];
            }

            $all_location_slugs = array_keys($location_map);
            $enable_all_locations = mulopimfwc_is_all_locations_enabled($options) ? 'on' : 'off';
            $processed_items = false;

            foreach ($order->get_items() as $item) {
                if (!$item->is_type('line_item')) {
                    continue;
                }

                $product_id = (int) $item->get_product_id();
                $variation_id = (int) $item->get_variation_id();
                if (!$product_id) {
                    continue;
                }

                $processed_items = true;

                $assigned_slugs = $this->get_item_assigned_location_slugs(
                    $product_id,
                    $variation_id,
                    $all_location_slugs,
                    $enable_all_locations
                );

                if (empty($assigned_slugs)) {
                    return [];
                }

                $assigned_lookup = array_fill_keys(array_map('rawurldecode', $assigned_slugs), true);

                foreach (array_keys($location_map) as $location_slug) {
                    if (!isset($assigned_lookup[$location_slug])) {
                        unset($location_map[$location_slug]);
                        continue;
                    }

                    $location_id = $location_map[$location_slug]['term_id'];
                    if ($this->is_location_disabled_for_item($product_id, $variation_id, $location_id)) {
                        unset($location_map[$location_slug]);
                    }
                }

                if (empty($location_map)) {
                    return [];
                }
            }

            if (!$processed_items) {
                return [];
            }

            return $location_map;
        }

        /**
         * Check whether a location is eligible for every line item in an order.
         *
         * @param WC_Order $order Order object.
         * @param string $location_slug Location slug.
         * @param array|null $options Optional plugin options.
         * @return bool
         */
        private function is_location_slug_eligible_for_order(WC_Order $order, string $location_slug, ?array $options = null): bool
        {
            $location_slug = rawurldecode(trim($location_slug));
            if ($location_slug === '') {
                return false;
            }

            $location_term = mulopimfwc_validate_location_slug($location_slug);
            if (!$location_term || !isset($location_term->term_id, $location_term->slug)) {
                return false;
            }

            $resolved_location_slug = rawurldecode((string) $location_term->slug);
            $location_id = (int) $location_term->term_id;

            $is_active = get_term_meta($location_id, 'is_active', true);
            $is_active_for_frontend = $is_active === '' || $is_active === 'on' || $is_active === '1' || $is_active === true || $is_active === 'yes';
            if (!$is_active_for_frontend) {
                return false;
            }

            if (!is_array($options)) {
                global $mulopimfwc_options;
                $options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', []);
            }

            $enable_all_locations = mulopimfwc_is_all_locations_enabled($options) ? 'on' : 'off';
            $processed_items = false;

            foreach ($order->get_items() as $item) {
                if (!$item->is_type('line_item')) {
                    continue;
                }

                $product_id = (int) $item->get_product_id();
                $variation_id = (int) $item->get_variation_id();
                if (!$product_id) {
                    continue;
                }

                $processed_items = true;

                $assigned_slugs = $this->get_item_assigned_location_slugs(
                    $product_id,
                    $variation_id,
                    [$resolved_location_slug],
                    $enable_all_locations
                );

                if (empty($assigned_slugs)) {
                    return false;
                }

                $assigned_lookup = array_fill_keys(array_map('rawurldecode', $assigned_slugs), true);
                if (!isset($assigned_lookup[$resolved_location_slug])) {
                    return false;
                }

                if ($this->is_location_disabled_for_item($product_id, $variation_id, $location_id)) {
                    return false;
                }
            }

            return $processed_items;
        }

        /**
         * Assign a location to an order based on proximity to the shipping address.
         *
         * @param WC_Order|int $order Order object or ID.
         * @return string Location slug or empty string if none found.
         */
        private function assign_location_by_proximity($order, ?array $checkout_data = null): string
        {
            $order = $order instanceof WC_Order ? $order : wc_get_order($order);
            if (!$order) {
                return '';
            }

            $locations = function_exists('mulopimfwc_get_frontend_locations')
                ? mulopimfwc_get_frontend_locations()
                : get_terms([
                    'taxonomy' => 'mulopimfwc_store_location',
                    'hide_empty' => false,
                ]);

            if (is_wp_error($locations) || empty($locations)) {
                return '';
            }

            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);

            $location_map = $this->get_order_eligible_location_map($order, $locations, $options);
            if (empty($location_map)) {
                return '';
            }

            $order_country_variants = $this->get_country_match_variants(
                $this->get_order_country_for_assignment($order, $checkout_data)
            );
            if (!empty($order_country_variants)) {
                $same_country_location_map = [];
                $country_unknown_location_map = [];
                foreach ($location_map as $location_slug => $location_data) {
                    $location_country = get_term_meta((int) $location_data['term_id'], 'country', true);
                    $location_country_variants = $this->get_country_match_variants($location_country);
                    if (empty($location_country_variants)) {
                        $country_unknown_location_map[$location_slug] = $location_data;
                        continue;
                    }

                    if (!empty(array_intersect_key($location_country_variants, $order_country_variants))) {
                        $same_country_location_map[$location_slug] = $location_data;
                    }
                }

                if (!empty($same_country_location_map)) {
                    $location_map = $same_country_location_map + $country_unknown_location_map;
                }
            }

            $eligible_locations = array_values(array_map(static function ($location_data) {
                return $location_data['location'];
            }, $location_map));

            $fallback_slug = $this->get_location_fallback_by_display_order($eligible_locations);

            $address = $this->get_order_shipping_address_string($order, $checkout_data);
            if ($address === '') {
                return $fallback_slug;
            }

            $coords = $this->geocode_address(
                $address,
                $this->get_order_country_for_assignment($order, $checkout_data)
            );
            if (empty($coords) || !isset($coords['lat'], $coords['lng'])) {
                return $fallback_slug;
            }

            $best_slug = '';
            $best_distance = null;
            $best_display_order = null;

            foreach ($location_map as $location_slug => $location_data) {
                $location = $location_data['location'];
                $location_id = (int) $location_data['term_id'];

                $lat = $this->normalize_location_coordinate_value(get_term_meta($location_id, 'latitude', true));
                $lng = $this->normalize_location_coordinate_value(get_term_meta($location_id, 'longitude', true));

                if ($lat === null || $lng === null) {
                    continue;
                }

                if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                    continue;
                }

                $distance = $this->calculate_haversine_distance_km(
                    $coords['lat'],
                    $coords['lng'],
                    $lat,
                    $lng
                );

                $display_order = (int) $location_data['display_order'];

                if ($best_distance === null || $distance < $best_distance - 0.000001) {
                    $best_slug = $location_slug;
                    $best_distance = $distance;
                    $best_display_order = $display_order;
                    continue;
                }

                if (abs($distance - $best_distance) <= 0.000001) {
                    if ($best_display_order === null || $display_order < $best_display_order) {
                        $best_slug = $location_slug;
                        $best_display_order = $display_order;
                    } elseif ($display_order === $best_display_order && strcmp($location_slug, $best_slug) < 0) {
                        $best_slug = $location_slug;
                    }
                }
            }

            return $best_slug !== '' ? $best_slug : $fallback_slug;
        }

        /**
         * Build a normalized shipping address string for geocoding.
         *
         * @param WC_Order $order
         * @param array|null $checkout_data
         * @return string
         */
        private function get_order_shipping_address_string(WC_Order $order, ?array $checkout_data = null): string
        {
            $shipping_country = $this->normalize_country_for_geocoding($order->get_shipping_country());
            $shipping_state = $this->normalize_state_for_geocoding($shipping_country, $order->get_shipping_state());
            $address_parts = [
                $order->get_shipping_address_1(),
                $order->get_shipping_address_2(),
                $order->get_shipping_city(),
                $shipping_state,
                $order->get_shipping_postcode(),
                $shipping_country,
            ];

            $address = $this->sanitize_address_parts($address_parts);

            if (empty($address) && is_array($checkout_data)) {
                $shipping_country = $this->normalize_country_for_geocoding($checkout_data['shipping_country'] ?? '');
                $shipping_state = $this->normalize_state_for_geocoding($shipping_country, $checkout_data['shipping_state'] ?? '');
                $address_parts = [
                    $checkout_data['shipping_address_1'] ?? '',
                    $checkout_data['shipping_address_2'] ?? '',
                    $checkout_data['shipping_city'] ?? '',
                    $shipping_state,
                    $checkout_data['shipping_postcode'] ?? '',
                    $shipping_country,
                ];
                $address = $this->sanitize_address_parts($address_parts);
            }

            if (empty($address)) {
                $billing_country = $this->normalize_country_for_geocoding($order->get_billing_country());
                $billing_state = $this->normalize_state_for_geocoding($billing_country, $order->get_billing_state());
                $address_parts = [
                    $order->get_billing_address_1(),
                    $order->get_billing_address_2(),
                    $order->get_billing_city(),
                    $billing_state,
                    $order->get_billing_postcode(),
                    $billing_country,
                ];
                $address = $this->sanitize_address_parts($address_parts);
            }

            if (empty($address) && is_array($checkout_data)) {
                $billing_country = $this->normalize_country_for_geocoding($checkout_data['billing_country'] ?? '');
                $billing_state = $this->normalize_state_for_geocoding($billing_country, $checkout_data['billing_state'] ?? '');
                $address_parts = [
                    $checkout_data['billing_address_1'] ?? '',
                    $checkout_data['billing_address_2'] ?? '',
                    $checkout_data['billing_city'] ?? '',
                    $billing_state,
                    $checkout_data['billing_postcode'] ?? '',
                    $billing_country,
                ];
                $address = $this->sanitize_address_parts($address_parts);
            }

            if (empty($address)) {
                return '';
            }

            return implode(', ', $address);
        }

        /**
         * Resolve preferred order country string for assignment checks.
         *
         * @param WC_Order $order
         * @param array|null $checkout_data
         * @return string
         */
        private function get_order_country_for_assignment(WC_Order $order, ?array $checkout_data = null): string
        {
            $candidates = [
                $order->get_shipping_country(),
                is_array($checkout_data) ? ($checkout_data['shipping_country'] ?? '') : '',
                $order->get_billing_country(),
                is_array($checkout_data) ? ($checkout_data['billing_country'] ?? '') : '',
            ];

            foreach ($candidates as $country_value) {
                $country = $this->normalize_country_for_geocoding($country_value);
                if ($country !== '') {
                    return $country;
                }
            }

            return '';
        }

        /**
         * Build normalized country variants for matching location country metadata.
         *
         * @param mixed $country
         * @return array
         */
        private function get_country_match_variants($country): array
        {
            if (!is_scalar($country)) {
                return [];
            }

            $country = trim(sanitize_text_field((string) $country));
            if ($country === '') {
                return [];
            }

            $variants = [];
            $country_upper = strtoupper($country);
            $variants[$country_upper] = true;

            if (function_exists('WC') && WC() && isset(WC()->countries)) {
                $countries = WC()->countries->get_countries();
                if (is_array($countries)) {
                    if (strlen($country_upper) === 2 && isset($countries[$country_upper])) {
                        $country_name = trim(sanitize_text_field((string) $countries[$country_upper]));
                        if ($country_name !== '') {
                            $variants[strtoupper($country_name)] = true;
                        }
                    } else {
                        foreach ($countries as $country_code => $country_name) {
                            if (!is_string($country_name)) {
                                continue;
                            }

                            if (strcasecmp(trim($country_name), $country) === 0) {
                                $variants[strtoupper((string) $country_code)] = true;
                                $variants[strtoupper(trim($country_name))] = true;
                                break;
                            }
                        }
                    }
                }
            }

            return $variants;
        }

        /**
         * Expand ISO country codes for more reliable geocoding queries.
         *
         * @param mixed $country Country code or name.
         * @return string
         */
        private function normalize_country_for_geocoding($country): string
        {
            if (!is_scalar($country)) {
                return '';
            }

            $country = trim(sanitize_text_field((string) $country));
            if ($country === '') {
                return '';
            }

            $country_code = strtoupper($country);
            if (strlen($country_code) === 2 && function_exists('WC') && WC() && isset(WC()->countries)) {
                $countries = WC()->countries->get_countries();
                if (is_array($countries) && isset($countries[$country_code])) {
                    return (string) $countries[$country_code];
                }
            }

            return $country;
        }

        /**
         * Resolve a two-letter country code for geocoding filters.
         *
         * @param mixed $country
         * @return string
         */
        private function get_country_code_for_geocoding($country): string
        {
            if (!is_scalar($country)) {
                return '';
            }

            $country = trim(sanitize_text_field((string) $country));
            if ($country === '') {
                return '';
            }

            $country_upper = strtoupper($country);
            if (!function_exists('WC') || !WC() || !isset(WC()->countries)) {
                return strlen($country_upper) === 2 ? $country_upper : '';
            }

            $countries = WC()->countries->get_countries();
            if (!is_array($countries)) {
                return strlen($country_upper) === 2 ? $country_upper : '';
            }

            if (strlen($country_upper) === 2 && isset($countries[$country_upper])) {
                return $country_upper;
            }

            foreach ($countries as $country_code => $country_name) {
                if (!is_string($country_name)) {
                    continue;
                }

                if (strcasecmp(trim($country_name), $country) === 0) {
                    return strtoupper((string) $country_code);
                }
            }

            return '';
        }

        /**
         * Expand state/region code into human-readable name for geocoding.
         *
         * @param mixed $country Country code or name.
         * @param mixed $state State/region code or name.
         * @return string
         */
        private function normalize_state_for_geocoding($country, $state): string
        {
            if (!is_scalar($state)) {
                return '';
            }

            $state = trim(sanitize_text_field((string) $state));
            if ($state === '') {
                return '';
            }

            if (!function_exists('WC') || !WC() || !isset(WC()->countries)) {
                return $state;
            }

            $country_code = $this->get_country_code_for_geocoding($country);
            if ($country_code === '') {
                return $state;
            }

            $states = WC()->countries->get_states($country_code);
            if (!is_array($states) || empty($states)) {
                return $state;
            }

            if (isset($states[$state]) && is_string($states[$state])) {
                return trim(sanitize_text_field((string) $states[$state]));
            }

            $state_upper = strtoupper($state);
            if (isset($states[$state_upper]) && is_string($states[$state_upper])) {
                return trim(sanitize_text_field((string) $states[$state_upper]));
            }

            foreach ($states as $state_code => $state_name) {
                if (!is_string($state_name)) {
                    continue;
                }

                if (strcasecmp((string) $state_code, $state) === 0 || strcasecmp(trim($state_name), $state) === 0) {
                    return trim(sanitize_text_field((string) $state_name));
                }
            }

            return $state;
        }

        /**
         * Get a fallback location slug based on lowest display order.
         *
         * @param array $locations
         * @return string
         */
        private function get_location_fallback_by_display_order(array $locations): string
        {
            $best_slug = '';
            $best_display_order = null;

            foreach ($locations as $location) {
                if (!isset($location->term_id, $location->slug)) {
                    continue;
                }

                $location_slug = rawurldecode((string) $location->slug);
                if ($location_slug === '') {
                    continue;
                }

                $display_order = get_term_meta($location->term_id, 'display_order', true);
                $display_order = $display_order !== '' ? (int) $display_order : 999;

                if ($best_display_order === null || $display_order < $best_display_order) {
                    $best_display_order = $display_order;
                    $best_slug = $location_slug;
                    continue;
                }

                if ($display_order === $best_display_order && strcmp($location_slug, $best_slug) < 0) {
                    $best_slug = $location_slug;
                }
            }

            return $best_slug;
        }

        /**
         * Sanitize address parts for geocoding.
         *
         * @param array $parts
         * @return array
         */
        private function sanitize_address_parts(array $parts): array
        {
            $cleaned = array_map(static function ($value) {
                if (!is_scalar($value)) {
                    return '';
                }
                return trim(sanitize_text_field((string) $value));
            }, $parts);

            return array_values(array_filter($cleaned, static function ($value) {
                return $value !== '';
            }));
        }

        /**
         * Geocode an address using OpenStreetMap Nominatim.
         *
         * @param string $address
         * @param string $country Optional country (code or name) to scope query.
         * @return array{lat: float, lng: float}|array
         */
        private function geocode_address(string $address, string $country = ''): array
        {
            $address = trim($address);
            if ($address === '') {
                return [];
            }

            $country_code = $this->get_country_code_for_geocoding($country);
            $cache_scope = strtolower($address . '|' . $country_code);
            $cache_key = 'mulopimfwc_geocode_' . md5($cache_scope);
            $cached = get_transient($cache_key);
            if (is_array($cached) && isset($cached['lat'], $cached['lng'])) {
                return $cached;
            }
            if ($cached === 'miss') {
                return [];
            }

            $query_args = [
                'q' => $address,
                'format' => 'json',
                'limit' => 1,
                'addressdetails' => 0,
            ];

            if ($country_code !== '') {
                $query_args['countrycodes'] = strtolower($country_code);
            }

            $request_url = add_query_arg($query_args, 'https://nominatim.openstreetmap.org/search');

            $user_agent = sprintf('MulopimFWC/%s (%s)', defined('mulopimfwc_VERSION') ? mulopimfwc_VERSION : '1.0.0', home_url('/'));

            $response = wp_remote_get($request_url, [
                'timeout' => 8,
                'redirection' => 2,
                'user-agent' => $user_agent,
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Language' => get_locale(),
                ],
            ]);

            $failure_ttl = (int) apply_filters('mulopimfwc_geocode_failure_ttl', HOUR_IN_SECONDS, $address);

            if (is_wp_error($response)) {
                set_transient($cache_key, 'miss', $failure_ttl);
                return [];
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                set_transient($cache_key, 'miss', $failure_ttl);
                return [];
            }

            $body = wp_remote_retrieve_body($response);
            if ($body === '') {
                set_transient($cache_key, 'miss', $failure_ttl);
                return [];
            }

            $data = json_decode($body, true);
            if (!is_array($data) || empty($data[0])) {
                set_transient($cache_key, 'miss', $failure_ttl);
                return [];
            }

            $lat = isset($data[0]['lat']) ? (float) $data[0]['lat'] : null;
            $lng = isset($data[0]['lon']) ? (float) $data[0]['lon'] : null;

            if (!is_numeric($lat) || !is_numeric($lng)) {
                set_transient($cache_key, 'miss', $failure_ttl);
                return [];
            }

            $lat = (float) $lat;
            $lng = (float) $lng;

            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                set_transient($cache_key, 'miss', $failure_ttl);
                return [];
            }

            $result = [
                'lat' => $lat,
                'lng' => $lng,
            ];

            $success_ttl = (int) apply_filters('mulopimfwc_geocode_cache_ttl', DAY_IN_SECONDS * 30, $address, $result);
            if ($success_ttl < 1) {
                $success_ttl = DAY_IN_SECONDS;
            }
            set_transient($cache_key, $result, $success_ttl);

            return $result;
        }

        /**
         * Calculate Haversine distance between two coordinates in kilometers.
         *
         * @param float $lat1
         * @param float $lng1
         * @param float $lat2
         * @param float $lng2
         * @return float
         */
        private function calculate_haversine_distance_km(float $lat1, float $lng1, float $lat2, float $lng2): float
        {
            $earth_radius = 6371;

            $lat1_rad = deg2rad($lat1);
            $lat2_rad = deg2rad($lat2);
            $delta_lat = deg2rad($lat2 - $lat1);
            $delta_lng = deg2rad($lng2 - $lng1);

            $a = sin($delta_lat / 2) ** 2
                + cos($lat1_rad) * cos($lat2_rad) * sin($delta_lng / 2) ** 2;

            $c = 2 * asin(min(1, sqrt($a)));

            return $earth_radius * $c;
        }

        /**
         * Resolve assigned location slugs for an order item.
         *
         * @param int $product_id Product ID.
         * @param int $variation_id Variation ID.
         * @param array $fallback_slugs All location slugs.
         * @param string $enable_all_locations Whether fallback should include all locations.
         * @return array
         */
        private function get_item_assigned_location_slugs($product_id, $variation_id, array $fallback_slugs, string $enable_all_locations): array
        {
            $ids_to_check = array_values(array_filter([$variation_id, $product_id]));
            $assigned_slugs = [];

            foreach ($ids_to_check as $id) {
                $terms = wp_get_object_terms($id, 'mulopimfwc_store_location', ['fields' => 'slugs']);
                if (!is_wp_error($terms) && !empty($terms)) {
                    $assigned_slugs = array_map('rawurldecode', $terms);
                    break;
                }
            }

            if (empty($assigned_slugs)) {
                return $enable_all_locations === 'on' ? $fallback_slugs : [];
            }

            return array_values(array_unique($assigned_slugs));
        }

        /**
         * Check if a location is disabled for an order item.
         *
         * @param int $product_id Product ID.
         * @param int $variation_id Variation ID.
         * @param int $location_id Location term ID.
         * @return bool
         */
        private function is_location_disabled_for_item($product_id, $variation_id, $location_id): bool
        {
            $ids_to_check = array_values(array_filter([$variation_id, $product_id]));

            foreach ($ids_to_check as $id) {
                $is_disabled = get_post_meta($id, '_location_disabled_' . $location_id, true);
                if (!empty($is_disabled)) {
                    return true;
                }
            }

            return false;
        }

        /**
         * Get stock quantity for an order item at a specific location.
         *
         * @param int $product_id Product ID.
         * @param int $variation_id Variation ID.
         * @param int $location_id Location term ID.
         * @return int
         */
        private function get_item_location_stock_quantity($product_id, $variation_id, $location_id): int
        {
            $ids_to_check = array_values(array_filter([$variation_id, $product_id]));

            foreach ($ids_to_check as $id) {
                $stock = get_post_meta($id, '_location_stock_' . $location_id, true);
                if ($stock !== '') {
                    return max(0, (int) $stock);
                }
            }

            static $global_stock_cache = [];
            $cache_key = $variation_id ? $variation_id : $product_id;
            if (isset($global_stock_cache[$cache_key])) {
                return $global_stock_cache[$cache_key];
            }

            foreach ($ids_to_check as $id) {
                $product = wc_get_product($id);
                if ($product && $product->managing_stock()) {
                    $qty = $product->get_stock_quantity();
                    if ($qty !== null) {
                        $global_stock_cache[$cache_key] = max(0, (int) $qty);
                        return $global_stock_cache[$cache_key];
                    }
                }
            }

            $global_stock_cache[$cache_key] = 0;
            return 0;
        }

        public function maybe_hold_manual_unassigned_order_status($status, $order_id, $order)
        {
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);

            $assignment_method = function_exists('mulopimfwc_get_effective_order_assignment_method')
                ? mulopimfwc_get_effective_order_assignment_method($options)
                : (isset($options['order_assignment_method']) ? $options['order_assignment_method'] : 'customer_selection');

            if ($assignment_method !== 'manual') {
                return $status;
            }

            $order = $order instanceof WC_Order ? $order : wc_get_order($order_id);
            if (!$order) {
                return $status;
            }

            $existing_location = (string) $order->get_meta('_store_location');
            if ($existing_location !== '') {
                return $status;
            }

            return 'on-hold';
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
         * Add filter dropdown in the WooCommerce orders list table
         */
        public function add_store_location_filter()
        {
            $locations = $this->get_all_store_locations();
            $options = get_option('mulopimfwc_display_options', []);
            $is_manual_mode = function_exists('mulopimfwc_get_effective_order_assignment_method')
                ? mulopimfwc_get_effective_order_assignment_method($options) === 'manual'
                : (isset($options['order_assignment_method']) && $options['order_assignment_method'] === 'manual');
            
            if (empty($locations) && !$is_manual_mode) {
                return;
            }
            
            $current_filter = isset($_GET['store_location_filter']) 
                ? sanitize_text_field($_GET['store_location_filter']) 
                : '';

            echo '<select name="store_location_filter" id="store_location_filter" class="postform">';
            echo '<option value="">' . esc_html__('All Locations', 'multi-location-product-and-inventory-management') . '</option>';
            
            if ($is_manual_mode) {
                $unassigned_count = $this->get_unassigned_orders_count();
                $selected = ($current_filter === 'unassigned') ? 'selected' : '';
                echo '<option value="unassigned" ' . esc_attr($selected) . '>';
                echo esc_html__('Unassigned', 'multi-location-product-and-inventory-management');
                if ($unassigned_count > 0) {
                    echo ' (' . esc_html($unassigned_count) . ')';
                }
                echo '</option>';
            }
            
            foreach ($locations as $location) {
                $selected = ($location === $current_filter) ? 'selected' : '';
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

        // add_settings_link
        public function add_settings_link($links)
        {
            if (!is_array($links)) {
                $links = [];
            }
            $old_links = $links;
            $links = [];
            $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=multi-location-product-and-inventory-management-settings')) . '">' . esc_html__('Settings', 'multi-location-product-and-inventory-management') . '</a>';
            $create_location = '<a href="' . esc_url(admin_url('edit-tags.php?taxonomy=mulopimfwc_store_location&post_type=product&orderby=display_order&order=asc')) . '">' . esc_html__('Manage Locations', 'multi-location-product-and-inventory-management') . '</a>';
            $support_link = '<a href="' . esc_url("https://www.plugincy.com/support/") . '">' . esc_html__('Support', 'multi-location-product-and-inventory-management') . '</a>';
            $documentation_link = '<a href="' . esc_url("https://plugincy.com/documentations/multi-location-product-inventory-management-for-woocommerce/") . '">' . esc_html__('Documentation', 'multi-location-product-and-inventory-management') . '</a>';
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
        public static function plugin_row_meta($links, $file)
        {
            if (MULTI_LOCATION_PLUGIN_BASE_NAME !== $file) {
                return $links;
            }

            $docs_url = 'https://plugincy.com/documentations/multi-location-product-inventory-management-for-woocommerce/';

            $community_support_url = 'https://wordpress.org/support/plugin/multi-location-product-and-inventory-management/';

            $support_url = 'https://www.plugincy.com/support/';

            $row_meta = array(
                'docs'    => '<a href="' . esc_url($docs_url) . '" aria-label="' . esc_attr__('View documentation', 'multi-location-product-and-inventory-management') . '">' . esc_html__('Docs', 'multi-location-product-and-inventory-management') . '</a>',
                'support' => '<a href="' . esc_url($support_url) . '" aria-label="' . esc_attr__('Support', 'multi-location-product-and-inventory-management') . '">' . esc_html__('Support', 'multi-location-product-and-inventory-management') . '</a>',
                'community_support' => '<a href="' . esc_url($community_support_url) . '" aria-label="' . esc_attr__('Visit community forums', 'multi-location-product-and-inventory-management') . '">' . esc_html__('Community support', 'multi-location-product-and-inventory-management') . '</a>',
            );

            return array_merge($links, $row_meta);
        }

        public function enqueue_scripts()
        {
            global $mulopimfwc_options;

            $cookie_expiry_days = mulopimfwc_get_location_cookie_expiry_days();
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);

            $assignment_method = function_exists('mulopimfwc_get_effective_order_assignment_method')
                ? mulopimfwc_get_effective_order_assignment_method($options)
                : (isset($options['order_assignment_method']) ? $options['order_assignment_method'] : 'customer_selection');
            $is_optional_assignment_mode = in_array($assignment_method, ['manual', 'inventory_based', 'proximity_based'], true);

            wp_enqueue_style('mulopimfwc_style', plugins_url('assets/css/style.css', __FILE__), [], '1.1.4.11');
            wp_enqueue_style('mulopimfwc_select2', plugins_url('assets/css/select2.min.css', __FILE__), [], '4.1.0');
            
            // Add custom branding CSS
            $branding_css = mulopimfwc_get_branding_css();
            if (!empty($branding_css)) {
                wp_add_inline_style('mulopimfwc_style', $branding_css);
            }
            wp_enqueue_script('mulopimfwc_script', plugins_url('assets/js/script.js', __FILE__), ['jquery'], '1.1.4.11', true);
            wp_enqueue_script('mulopimfwc_select2', plugins_url('assets/js/select2.min.js', __FILE__), ['jquery'], '4.1.0', true);
            wp_add_inline_script('mulopimfwc_select2', 'jQuery.fn.select2&&jQuery.fn.select2.defaults&&jQuery.fn.select2.defaults.set("language",{noResults:function(){return"' . esc_js(mulopimfwc_get_text_value('text_popup_msg_no_results')) . '";}});', 'after');
            $template_selection = isset($options['template_selection']) ? $options['template_selection'] : 'default';
            if (in_array($template_selection, ['modern', 'modern-simple'], true)) {
                wp_enqueue_script(
                    'mulopimfwc-modern-popup',
                    plugins_url('assets/js/modern-popup.js', __FILE__),
                    ['jquery'],
                    '1.1.4.11',
                    true
                );
            } elseif ($template_selection === 'classic') {
                wp_enqueue_script(
                    'mulopimfwc-classic-popup',
                    plugins_url('assets/js/classic-popup.js', __FILE__),
                    ['jquery'],
                    '1.1.4.11',
                    true
                );
            } elseif (in_array($template_selection, ['tabs', 'compact', 'grid'], true)) {
                wp_enqueue_script(
                    'mulopimfwc-popup-layouts',
                    plugins_url('assets/js/popup-layouts.js', __FILE__),
                    ['jquery'],
                    '1.1.4.11',
                    true
                );
            }

            // Check if cart grouping is enabled
            $group_cart = mulopimfwc_is_group_cart_enabled($mulopimfwc_options) ? 'on' : 'off';

            $location_require_selection = isset($mulopimfwc_options['location_require_selection']) ? $mulopimfwc_options['location_require_selection'] : 'off';
            if ($is_optional_assignment_mode) {
                $location_require_selection = 'off';
            }
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
                    '1.1.4.11',
                    true
                );

                wp_add_inline_style('mulopimfwc_style', '.wc-block-components-product-details__location{display:none !important;}');
            }


            // Check if allow location change in cart is enabled
            $allow_location_change_in_cart = mulopimfwc_is_location_change_in_cart_enabled($options) ? 'on' : 'off';

            if ($allow_location_change_in_cart === 'on' && (is_cart() || is_checkout())) {
                wp_enqueue_script(
                    'mulopimfwc-cart-location-change',
                    plugins_url('assets/js/cart-location-change.js', __FILE__),
                    ['jquery'],
                    '1.1.4.11',
                    true
                );

                wp_localize_script('mulopimfwc-cart-location-change', 'mulopimfwcCartLocationChange', [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('mulopimfwc_update_cart_location'),
                    'changeLocationLabel' => mulopimfwc_get_text_value('text_cart_change_location_label'),
                    'updatingText' => mulopimfwc_get_text_value('text_cart_updating'),
                    'errorText' => mulopimfwc_get_text_value('text_cart_update_error'),
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
                        border-color: var(--lwp-primary, #667eea);
                        box-shadow: 0 0 0 1px var(--lwp-primary, #667eea);
                    }
                    .mulopimfwc-cart-location-selector.updating .mulopimfwc-cart-location-select {
                        opacity: 0.6;
                        cursor: not-allowed;
                    }
                ');
            }

            $auto_populate_addresses = isset($options['auto_populate_customer_addresses'])
                ? $options['auto_populate_customer_addresses']
                : 'off';
            $is_checkout_page = function_exists('is_checkout') ? is_checkout() : false;
            $location_switching_behavior = isset($options['location_switching_behavior'])
                ? $options['location_switching_behavior']
                : 'update_cart';
            if (mulopimfwc_is_location_wise_currency_enabled($options) && $location_switching_behavior === 'preserve_cart') {
                $location_switching_behavior = 'update_cart';
            }

            wp_localize_script('mulopimfwc_script', 'mulopimfwc_locationWiseProducts', [
                'cartHasProducts' => !WC()->cart->is_empty(),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'ajax_url' => admin_url('admin-ajax.php'),
                'location_change_notification' => isset($mulopimfwc_options["location_change_notification"]) || $location_switching_behavior === "prompt_user",
                'nonce' => wp_create_nonce('multi-location-product-and-inventory-management'),
                'cookie_expiry' => $cookie_expiry_days,
                'cookieExpiryDays' => $cookie_expiry_days,
                'cookieName' => mulopimfwc_get_location_cookie_name(),
                'cookieDomain' => apply_filters('mulopimfwc_location_cookie_domain', defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : ''),
                'cookiePath' => '/',
                'cookieSameSite' => 'Lax',
                'cookieSecure' => is_ssl(),
                'currentLocation' => $this->get_current_location(),
                'allow_mixed_in_cart' => mulopimfwc_is_mixed_location_cart_enabled($mulopimfwc_options) ? 'on' : 'off',
                'allow_cart_update' => $location_switching_behavior !== "preserve_cart",
                'location_switching_behavior' => $location_switching_behavior,
                'location_notification_text' => mulopimfwc_premium_feature()
                    ? mulopimfwc_get_text_value('location_notification_text')
                    : __('Do you want to change the store location? Your cart will be updated.', 'multi-location-product-and-inventory-management'),
                'singleProductRequiresLocation' => $single_product_requires_location,
                'selectLocationPrompt' => mulopimfwc_get_text_value('text_alert_location_required'),
                'locationSelectionEnforced' => ($location_require_selection === 'on'),
                'autoPopulateAddresses' => $auto_populate_addresses,
                'autoPopulateNonce' => wp_create_nonce('mulopimfwc_update_customer_address'),
                'isLoggedIn' => is_user_logged_in(),
                'isCheckout' => $is_checkout_page,
                'i18n' => [
                    'selectStore' => mulopimfwc_get_text_value('text_alert_select_store'),
                    'selectStoreLocation' => mulopimfwc_get_text_value('text_alert_select_store_location'),
                    'cartClearError' => mulopimfwc_get_text_value('text_cart_clear_error'),
                    'cartRemovedItems' => mulopimfwc_get_text_value('text_cart_removed_items'),
                    'cartUnableChange' => mulopimfwc_get_text_value('text_cart_unable_change'),
                    'cartUnableChangeNow' => mulopimfwc_get_text_value('text_cart_unable_change_now'),
                ],
                'variation' => [
                    'infoHeading' => mulopimfwc_get_text_value('text_variation_info_heading'),
                    'stockLabel' => mulopimfwc_get_text_value('text_variation_stock_label'),
                    'inStock' => mulopimfwc_get_text_value('text_variation_in_stock'),
                    'outOfStock' => mulopimfwc_get_text_value('text_alert_out_of_stock_badge'),
                    'backorder' => mulopimfwc_get_text_value('text_variation_backorder'),
                    'priceLabel' => mulopimfwc_get_text_value('text_variation_price_label'),
                ],
            ]);

            wp_enqueue_style('leaflet', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.css', array(), '1.7.1');
            wp_enqueue_script('leaflet', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.js', array('jquery'), '1.7.1', true);
        }

        private function get_current_location()
        {
            // First check if cookie is set
            $cookie_location = mulopimfwc_get_store_location_cookie();
            
            if (!empty($cookie_location)) {
                return $cookie_location;
            }
            
            // If cookie is empty, check if popup is enabled
            global $mulopimfwc_options;
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
            
            return '';
        }

        private function get_location_manager_frontend_locations()
        {
            $assigned_locations = mulopimfwc_get_location_manager_frontend_assigned_locations();
            return is_array($assigned_locations) ? $assigned_locations : null;
        }

        private function build_location_tax_query($locations, $enable_all_locations, $strict = false)
        {
            $location_clause = [
                'taxonomy' => 'mulopimfwc_store_location',
                'field' => 'slug',
                'terms' => $locations,
                'operator' => 'IN',
            ];

            if ($strict) {
                return $location_clause;
            }

            if ($enable_all_locations === 'on') {
                return [
                    'relation' => 'OR',
                    $location_clause,
                    [
                        'taxonomy' => 'mulopimfwc_store_location',
                        'operator' => 'NOT EXISTS',
                    ],
                ];
            }

            return $location_clause;
        }

        /**
         * FIXED: Enhanced product filtering to respect visibility settings and handle all scenarios
         * FIXED: Always filters [products] shortcode when strict_filtering is enabled
         */
        public function filter_shortcode_products($query_args)
        {
            // Check if filtering should be applied based on settings
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
            $strict_filtering = mulopimfwc_get_strict_filtering_value($options);
            
            // If strict filtering is disabled, don't filter
            if ($strict_filtering === 'disabled') {
                return $query_args;
            }
            
            $filtered_sections = ['shop', 'search', 'related', 'recently_viewed', 'cross_sells', 'upsells', 'widgets', 'blocks', 'rest_api'];
            
            // Determine which section this is
            $current_section = $this->detect_current_section($query_args);
            
            // FIXED: Always filter shortcodes/blocks if strict_filtering is enabled
            // The woocommerce_shortcode_products_query filter is ONLY called for shortcodes
            // So if we're in this filter, it's definitely a shortcode query
            $is_shortcode_query = true; // This filter is only called for shortcodes
            
            // Always filter shortcodes when strict_filtering is enabled
            if ($current_section !== 'blocks' && !in_array($current_section, (array)$filtered_sections, true)) {
                return $query_args;
            }
            
            $location = $this->get_current_location();
            global $mulopimfwc_options;
            $options_for_all_locations = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
            $enable_all_locations = mulopimfwc_is_all_locations_enabled($options_for_all_locations) ? 'on' : 'off';
            $manager_locations = $this->get_location_manager_frontend_locations();
            
            if (is_array($manager_locations)) {
                if (empty($manager_locations)) {
                    $query_args['post__in'] = [0];
                    return $query_args;
                }

                $target_locations = $manager_locations;
                if (!empty($location) && $location !== 'all-products') {
                    if (!in_array($location, $manager_locations, true)) {
                        $query_args['post__in'] = [0];
                        return $query_args;
                    }
                    $target_locations = [$location];
                }

                $query_args['tax_query'][] = $this->build_location_tax_query($target_locations, $enable_all_locations, true);
                
                // FIXED: Also filter out-of-stock products if setting requires it
                $this->apply_stock_filtering($query_args, $target_locations);
                
                // FIXED: Mark this query for location-first ordering
                if (!empty($location) && $location !== 'all-products') {
                    $query_args['mulopimfwc_location_priority'] = true;
                    $query_args['mulopimfwc_location_slug'] = $location;
                }
                
                return $query_args;
            }

            // FIXED: When strict_filtering is enabled, always filter by location if one is selected
            // Only skip filtering if location is empty or 'all-products'
            if (!$location || $location === 'all-products') {
                // No location selected - don't filter
                return $query_args;
            }
            
            // FIXED: enable_all_locations means "show products without location assignment in all locations"
            // But we still need to filter products that HAVE location assignments
            // So we always apply location filtering when a location is selected and strict_filtering is enabled

            // Initialize tax_query if needed
            if (!isset($query_args['tax_query'])) {
                $query_args['tax_query'] = [];
            }

            // FIXED: Validate location exists before filtering
            $location_term = mulopimfwc_validate_location_slug($location);
            if (!$location_term) {
                // Invalid location - return empty results
                $query_args['post__in'] = [0];
                return $query_args;
            }

            // FIXED: Use build_location_tax_query to handle enable_all_locations properly
            // When enable_all_locations is 'on', it will include products without location assignments
            // When it's 'off', it will only show products assigned to the selected location
            $query_args['tax_query'][] = $this->build_location_tax_query([$location], $enable_all_locations, false);
            
            // FIXED: Also filter out-of-stock products if setting requires it
            $this->apply_stock_filtering($query_args, [$location]);
            
            // FIXED: Mark this query for location-first ordering
            // We'll use this flag in posts_clauses filter
            $query_args['mulopimfwc_location_priority'] = true;
            $query_args['mulopimfwc_location_slug'] = $location;

            return $query_args;
        }
        
        /**
         * FIXED: Add location filtering and ordering to ALL frontend product queries
         * This ensures ALL database product queries respect location filtering and ordering
         */
        public function add_location_filtering_and_ordering_to_all_queries($clauses, $query)
        {
            global $wpdb;
            
            // Only apply to frontend product queries
            if (is_admin() || 
                !isset($query->query_vars['post_type']) || 
                $query->query_vars['post_type'] !== 'product') {
                return $clauses;
            }

            if ($query->is_tax('mulopimfwc_store_location') || $query->get('mulopimfwc_store_location') || $query->get('taxonomy') === 'mulopimfwc_store_location') {
                return $clauses;
            }
            
            // Get settings
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
            $strict_filtering = mulopimfwc_get_strict_filtering_value($options);
            $product_priority_display = mulopimfwc_get_product_priority_display_value($options);
            $enable_all_locations = mulopimfwc_is_all_locations_enabled($options) ? 'on' : 'off';
            $single_product_behavior = mulopimfwc_get_single_product_unavailable_behavior($options);
            
            // Check if we should skip location filtering for this query
            // This flag is set in handle_single_product_query when setting is "show_message"
            $skip_location_filtering = false;
            if (isset($query->query_vars['mulopimfwc_skip_location_filter']) && $query->query_vars['mulopimfwc_skip_location_filter']) {
                $skip_location_filtering = true;
            }

            // Fallback: if this is a single product query and behavior is show_message, skip filtering
            $has_single_identifier = !empty($query->get('p')) || !empty($query->get('name')) || !empty($query->get('product'));
            $is_single_product_query = $query->is_main_query() && (
                $query->is_singular('product') ||
                ($query->query_vars['post_type'] === 'product' && $has_single_identifier)
            );
            if ($single_product_behavior === 'show_message' && $is_single_product_query) {
                $skip_location_filtering = true;
            }
            
            // Get current location
            $location_slug = '';
            if (isset($query->query_vars['mulopimfwc_location_slug'])) {
                $location_slug = $query->query_vars['mulopimfwc_location_slug'];
            } else {
                $location_slug = $this->get_current_location();
            }
            
            // Check location manager restrictions
            $manager_locations = $this->get_location_manager_frontend_locations();
            if (is_array($manager_locations)) {
                if (empty($manager_locations)) {
                    // Manager has no locations - return empty results
                    $clauses['where'] .= " AND {$wpdb->posts}.ID = 0";
                    return $clauses;
                }
                if (!empty($location_slug) && $location_slug !== 'all-products') {
                    if (!in_array($location_slug, $manager_locations, true)) {
                        // Manager doesn't have access to this location
                        $clauses['where'] .= " AND {$wpdb->posts}.ID = 0";
                        return $clauses;
                    }
                }
            }
            
            // Apply location filtering if strict_filtering is enabled
            // BUT skip if this is a single product query with show_message setting
            if ($strict_filtering === 'enabled' && !empty($location_slug) && $location_slug !== 'all-products' && !$skip_location_filtering) {
                // Get location term
                $location_term = get_term_by('slug', $location_slug, 'mulopimfwc_store_location');
                if ($location_term && !is_wp_error($location_term)) {
                    // Get term_taxonomy_id for the location
                    $term_taxonomy_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND taxonomy = %s",
                        $location_term->term_id,
                        'mulopimfwc_store_location'
                    ));
                    
                    if ($term_taxonomy_id) {
                        // Check if location filtering is already applied via tax_query
                        $has_location_tax_query = false;
                        if (isset($query->query_vars['tax_query']) && is_array($query->query_vars['tax_query'])) {
                            foreach ($query->query_vars['tax_query'] as $tax_query) {
                                if (isset($tax_query['taxonomy']) && $tax_query['taxonomy'] === 'mulopimfwc_store_location') {
                                    $has_location_tax_query = true;
                                    break;
                                }
                            }
                        }
                        
                        // Apply location filtering if not already applied
                        if (!$has_location_tax_query) {
                            // Add JOIN to check location assignment
                            $clauses['join'] .= " LEFT JOIN {$wpdb->term_relationships} AS location_filter_tr 
                                    ON ({$wpdb->posts}.ID = location_filter_tr.object_id AND location_filter_tr.term_taxonomy_id = " . intval($term_taxonomy_id) . ") ";
                            
                            if ($enable_all_locations === 'on') {
                                // Show products assigned to location OR products without location assignment
                                $clauses['where'] .= " AND (location_filter_tr.object_id IS NOT NULL OR 
                                    NOT EXISTS (
                                        SELECT 1 FROM {$wpdb->term_relationships} tr2
                                        INNER JOIN {$wpdb->term_taxonomy} tt2 ON tr2.term_taxonomy_id = tt2.term_taxonomy_id
                                        WHERE tr2.object_id = {$wpdb->posts}.ID 
                                        AND tt2.taxonomy = 'mulopimfwc_store_location'
                                    ))";
                            } else {
                                // Only show products assigned to location
                                $clauses['where'] .= " AND location_filter_tr.object_id IS NOT NULL";
                            }
                            
                            // Store term_taxonomy_id for ordering
                            $query->query_vars['_mulopimfwc_term_taxonomy_id'] = $term_taxonomy_id;
                        } else {
                            // Get term_taxonomy_id from existing tax_query for ordering
                            $query->query_vars['_mulopimfwc_term_taxonomy_id'] = $term_taxonomy_id;
                        }
                    }
                }
            } elseif ($skip_location_filtering && !empty($location_slug) && $location_slug !== 'all-products') {
                // We're skipping filtering but still need term_taxonomy_id for potential ordering
                $location_term = get_term_by('slug', $location_slug, 'mulopimfwc_store_location');
                if ($location_term && !is_wp_error($location_term)) {
                    $term_taxonomy_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND taxonomy = %s",
                        $location_term->term_id,
                        'mulopimfwc_store_location'
                    ));
                    if ($term_taxonomy_id) {
                        $query->query_vars['_mulopimfwc_term_taxonomy_id'] = $term_taxonomy_id;
                    }
                }
            }
            
            // Apply location-first ordering if enabled
            if ($product_priority_display === 'location_first' || $product_priority_display === 'global_first') {
                $term_taxonomy_id = isset($query->query_vars['_mulopimfwc_term_taxonomy_id']) ? $query->query_vars['_mulopimfwc_term_taxonomy_id'] : null;
                
                if ($term_taxonomy_id || !empty($location_slug)) {
                    // Check if JOIN already exists (from filtering)
                    $join_alias = 'location_priority_tr';
                    if (strpos($clauses['join'], 'location_filter_tr') !== false) {
                        $join_alias = 'location_filter_tr';
                    } elseif (strpos($clauses['join'], $join_alias) === false) {
                        if ($term_taxonomy_id === null && !empty($location_slug) && $location_slug !== 'all-products') {
                            $location_term = get_term_by('slug', $location_slug, 'mulopimfwc_store_location');
                            if ($location_term && !is_wp_error($location_term)) {
                                $term_taxonomy_id = $wpdb->get_var($wpdb->prepare(
                                    "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND taxonomy = %s",
                                    $location_term->term_id,
                                    'mulopimfwc_store_location'
                                ));
                            }
                        }
                        
                        if ($term_taxonomy_id) {
                            // Add LEFT JOIN to check if product is assigned to location
                            $clauses['join'] .= " LEFT JOIN {$wpdb->term_relationships} AS {$join_alias} 
                                    ON ({$wpdb->posts}.ID = {$join_alias}.object_id AND {$join_alias}.term_taxonomy_id = " . intval($term_taxonomy_id) . ") ";
                        } else {
                            return $clauses;
                        }
                    }
                    
                    // Determine priority values
                    if ($product_priority_display === 'location_first') {
                        $priority_value_for_location = 1;
                        $priority_value_for_global = 2;
                    } else { // global_first
                        $priority_value_for_location = 2;
                        $priority_value_for_global = 1;
                    }
                    
                    // Add custom ORDER BY clause
                    $priority_orderby = "CASE WHEN {$join_alias}.object_id IS NOT NULL THEN {$priority_value_for_location} ELSE {$priority_value_for_global} END";
                    
                    // Prepend priority ordering to existing orderby
                    if (!empty($clauses['orderby'])) {
                        $clauses['orderby'] = $priority_orderby . ', ' . $clauses['orderby'];
                    } else {
                        $clauses['orderby'] = $priority_orderby . ', ' . $wpdb->posts . '.post_date DESC';
                    }
                }
            }
            
            return $clauses;
        }
        
        /**
         * Detect which section the current query is for
         * FIXED: Better detection for [products] shortcode and other display methods
         */
        private function detect_current_section($query_args)
        {
            // Check if it's a widget query (has highest priority for detection)
            if (isset($query_args['widget']) || doing_action('woocommerce_products_widget_query_args')) {
                return 'widgets';
            }
            
            // Check for related products
            if (isset($query_args['related']) || doing_action('woocommerce_output_related_products_args')) {
                return 'related';
            }
            
            // Check for upsells
            if (isset($query_args['upsells']) || doing_action('woocommerce_upsell_display_args')) {
                return 'upsells';
            }
            
            // Check for cross-sells
            if (isset($query_args['cross_sells']) || doing_action('woocommerce_cross_sell_display_args')) {
                return 'cross_sells';
            }
            
            // Check for recently viewed
            if (isset($query_args['recently_viewed']) || doing_action('woocommerce_recently_viewed_products_widget_query_args')) {
                return 'recently_viewed';
            }
            
            // Check for REST API
            if (defined('REST_REQUEST') && REST_REQUEST) {
                return 'rest_api';
            }
            
            // FIXED: Check if it's a shortcode query (woocommerce_shortcode_products_query filter)
            // This filter is used by [products], [recent_products], [sale_products], etc.
            if (doing_filter('woocommerce_shortcode_products_query') || 
                isset($query_args['shortcode']) || 
                (isset($query_args['orderby']) && !isset($query_args['post__in']))) {
                // Check if it's a Gutenberg block (has shortcode key) or regular shortcode
                if (isset($query_args['shortcode'])) {
                    return 'blocks'; // Gutenberg blocks
                }
                // Regular shortcodes like [products]
                return 'blocks'; // Treat shortcodes as blocks for filtering purposes
            }
            
            // Default to blocks/shortcodes (most common case for woocommerce_shortcode_products_query)
            return 'blocks';
        }
        
        /**
         * Filter main WooCommerce product query
         * FIXED: Added to handle product queries that don't go through shortcode/widget filters
         */
        public function filter_product_query($query_args, $query)
        {
            // Only apply to frontend product queries
            if (is_admin() || !isset($query_args['post_type']) || $query_args['post_type'] !== 'product') {
                return $query_args;
            }
            
            // Use the same filtering logic as shortcodes
            return $this->filter_shortcode_products($query_args);
        }
        
        /**
         * Apply stock filtering based on location and settings
         * FIXED: Properly filters out-of-stock products based on location-specific stock
         */
        private function apply_stock_filtering(&$query_args, $locations)
        {
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
            $show_out_of_stock = isset($options['show_out_of_stock_products']) ? $options['show_out_of_stock_products'] : 'none';
            
            // If setting is to hide out-of-stock, add meta query
            if ($show_out_of_stock === 'hide') {
                if (!isset($query_args['meta_query'])) {
                    $query_args['meta_query'] = [];
                }
                
                // Build stock status query for each location
                $stock_queries = [];
                foreach ($locations as $location_slug) {
                    $location_term = mulopimfwc_validate_location_slug($location_slug);
                    if (!$location_term) {
                        continue;
                    }
                    
                    $location_id = $location_term->term_id;
                    
                    // Product is in stock if:
                    // 1. Location stock > 0, OR
                    // 2. Backorders are enabled (not 'off'), OR
                    // 3. No location stock meta exists (use global stock)
                    $stock_queries[] = [
                        'relation' => 'OR',
                        [
                            'key' => '_location_stock_' . $location_id,
                            'value' => '0',
                            'compare' => '>',
                            'type' => 'NUMERIC',
                        ],
                        [
                            'key' => '_location_backorders_' . $location_id,
                            'value' => 'off',
                            'compare' => '!=',
                        ],
                        [
                            'key' => '_location_stock_' . $location_id,
                            'compare' => 'NOT EXISTS',
                        ],
                    ];
                }
                
                if (!empty($stock_queries)) {
                    // If multiple locations, combine with OR (product can be in stock at any location)
                    if (count($stock_queries) > 1) {
                        $query_args['meta_query'][] = [
                            'relation' => 'OR',
                            $stock_queries,
                        ];
                    } else {
                        $query_args['meta_query'][] = $stock_queries[0];
                    }
                }
            }
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

        public function maybe_set_default_location_cookie()
        {
            if (is_admin() && !wp_doing_ajax()) {
                return;
            }

            if (headers_sent()) {
                return;
            }

            if (mulopimfwc_is_manual_assignment_strict_mode()) {
                return;
            }

            $current_location = mulopimfwc_get_store_location_cookie();
            if (!empty($current_location)) {
                return;
            }

            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);

            $enable_popup = isset($options['enable_popup']) ? $options['enable_popup'] : 'off';
            if ($enable_popup === 'on' && mulopimfwc_premium_feature()) {
                return;
            }

            $default_location = mulopimfwc_get_default_location_value($options);

            if ($default_location === '') {
                return;
            }

            $location_obj = null;
            if ($default_location !== 'all-products') {
                $location_obj = get_term_by('slug', $default_location, 'mulopimfwc_store_location');
                if (!$location_obj || is_wp_error($location_obj)) {
                    return;
                }
            }

            mulopimfwc_set_location_cookie($default_location, null, $location_obj);
        }

        public function location_selector_modal()
        {
            if (mulopimfwc_is_manual_assignment_strict_mode()) {
                return;
            }

            // Check if page has shortcode with on_click_button=false
            if ($this->has_popup_shortcode_without_button()) {
                return; // Don't show global popup if shortcode with on_click_button=false exists
            }

            global $mulopimfwc_locations;
            $is_user_logged_in = is_user_logged_in();
            $current_user = wp_get_current_user();
             $is_admin_or_manager = in_array('administrator', $current_user->roles) || in_array('shop_manager', $current_user->roles) || in_array('mulopimfwc_location_manager', $current_user->roles);
            // mulopimfwc_display_options[show_popup_admin]
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
            $show_popup_admin = isset($options['show_popup_admin']) ? $options['show_popup_admin'] : 'off';
            $selected_location = $this->get_current_location();
            $locationSelected = mulopimfwc_get_store_location_cookie();
            $show_modal = $show_popup_admin === 'on' && empty($selected_location) ? true : (empty($selected_location) && !$is_admin_or_manager);

            $locations = $mulopimfwc_locations;

            $template_selection = isset($options['template_selection']) ? $options['template_selection'] : 'default';
            if ($template_selection === 'modern') {
                $template_file = 'templates/modern-modal.php';
            } elseif ($template_selection === 'modern-simple') {
                $template_file = 'templates/modern-simple-modal.php';
            } elseif ($template_selection === 'classic') {
                $template_file = 'templates/classic-modal.php';
            } elseif (in_array($template_selection, ['tabs', 'compact', 'grid'], true)) {
                $template_file = 'templates/location-info-modal.php';
                $popup_layout = $template_selection;
            } else {
                $template_file = 'templates/modal.php';
            }
            $template_path = plugin_dir_path(__FILE__) . $template_file;
            if (!file_exists($template_path)) {
                $template_path = plugin_dir_path(__FILE__) . 'templates/modal.php';
            }

            include $template_path;
        }

        /**
         * Check if current page has popup shortcode with on_click_button=false
         * 
         * @return bool True if shortcode with on_click_button=false exists
         */
        private function has_popup_shortcode_without_button()
        {
            global $post;
            
            // Check global shortcode instances first (set during shortcode execution)
            if (isset($GLOBALS['mulopimfwc_popup_shortcodes']) && is_array($GLOBALS['mulopimfwc_popup_shortcodes'])) {
                foreach ($GLOBALS['mulopimfwc_popup_shortcodes'] as $shortcode_info) {
                    if (isset($shortcode_info['on_click_button']) && !$shortcode_info['on_click_button']) {
                        return true;
                    }
                }
            }

            // Fallback: Check post content for shortcode
            if ($post && isset($post->post_content)) {
                // Check if shortcode exists in content
                if (has_shortcode($post->post_content, 'mulopimfwc_display_popup')) {
                    // Parse shortcode attributes
                    $pattern = get_shortcode_regex(['mulopimfwc_display_popup']);
                    if (preg_match_all('/' . $pattern . '/s', $post->post_content, $matches)) {
                        foreach ($matches[3] as $atts_string) {
                            $atts = shortcode_parse_atts($atts_string);
                            if (isset($atts['on_click_button'])) {
                                $on_click_button = filter_var($atts['on_click_button'], FILTER_VALIDATE_BOOLEAN);
                                if (!$on_click_button) {
                                    return true;
                                }
                            }
                        }
                    }
                }
            }

            return false;
        }

        public function location_selector_shortcode($atts)
        {
            if (mulopimfwc_is_manual_assignment_strict_mode()) {
                if (is_user_logged_in() && current_user_can('administrator')) {
                    return '<p>When manual assignment strict mode is enabled, the location selector shortcode will not be displayed (Admin Only).</p>';
                }
                return '';
            }

            global $mulopimfwc_locations;
            $atts = shortcode_atts([
                'title' => __('Select Store Location', 'multi-location-product-and-inventory-management'),
                'show_title' => 'on',
                'class' => '',
                'show_button' => 'off',
                'use_select2' => 'off',
                'herichical' => 'off',
                'show_count' => 'off',
                'enable_user_locations' => 'off', // New attribute
                'max_width' => '300',
                'multi_line' => 'off'
            ], $atts);

            $is_user_logged_in = is_user_logged_in();
            $current_user = wp_get_current_user();
            // mulopimfwc_display_options[show_all_products_admin]
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
            $show_all_products_admin = mulopimfwc_is_show_all_products_admin_enabled($options) ? 'on' : 'off';
             $is_admin_or_manager = in_array('administrator', $current_user->roles) || in_array('shop_manager', $current_user->roles) || in_array('mulopimfwc_location_manager', $current_user->roles);
            $selected_location = $this->get_current_location();
            
            // Filter locations for frontend display (active only, ordered by display_order)
            $locations = mulopimfwc_get_frontend_locations();

            ob_start();
            include plugin_dir_path(__FILE__) . 'templates/shortcode-selector.php';
            return ob_get_clean();
        }

        /**
         * Display popup shortcode handler
         * 
         * Usage: [mulopimfwc_display_popup layout="default/modern/modern-simple/classic/tabs/compact/grid" on_click_button="true/false" button_title="open modal"]
         * 
         * @param array $atts Shortcode attributes
         * @return string Shortcode output
         */
        public function display_popup_shortcode($atts)
        {
            if (mulopimfwc_is_manual_assignment_strict_mode()) {
                if (is_user_logged_in() && current_user_can('administrator')) {
                    return '<p>When manual assignment strict mode is enabled, the popup shortcode will not be displayed (Admin Only).</p>';
                }
                return '';
            }

            // Check if premium feature is enabled
            if (!mulopimfwc_premium_feature()) {
                return '';
            }

            global $mulopimfwc_locations;
            
            $atts = shortcode_atts([
                'layout' => 'default',
                'on_click_button' => 'true',
                'button_title' => __('Open Modal', 'multi-location-product-and-inventory-management')
            ], $atts);

            // Normalize boolean values
            $on_click_button = filter_var($atts['on_click_button'], FILTER_VALIDATE_BOOLEAN);
            $layout = sanitize_text_field($atts['layout']);
            $button_title = sanitize_text_field($atts['button_title']);

            // Validate layout
            $valid_layouts = ['default', 'modern', 'modern-simple', 'classic', 'tabs', 'compact', 'grid'];
            if (!in_array($layout, $valid_layouts, true)) {
                $layout = 'default';
            }

            // Store shortcode instance ID for unique identification
            static $shortcode_instance = 0;
            $shortcode_instance++;
            $instance_id = 'mulopimfwc-popup-' . $shortcode_instance;

            // Store shortcode info globally to check in modal function
            if (!isset($GLOBALS['mulopimfwc_popup_shortcodes'])) {
                $GLOBALS['mulopimfwc_popup_shortcodes'] = [];
            }
            $GLOBALS['mulopimfwc_popup_shortcodes'][] = [
                'instance_id' => $instance_id,
                'layout' => $layout,
                'on_click_button' => $on_click_button,
                'button_title' => $button_title
            ];

            ob_start();
            
            if ($on_click_button) {
                // Show button that opens popup
                echo '<div class="mulopimfwc-popup-shortcode-wrapper" data-instance-id="' . esc_attr($instance_id) . '" data-layout="' . esc_attr($layout) . '">';
                echo '<button type="button" class="mulopimfwc-popup-trigger-button button" data-instance-id="' . esc_attr($instance_id) . '" data-layout="' . esc_attr($layout) . '">';
                echo esc_html($button_title);
                echo '</button>';
                echo '</div>';
                
                // Render popup in footer but hidden (will be shown on button click)
                add_action('wp_footer', function() use ($instance_id, $layout) {
                    $this->render_popup_modal($instance_id, $layout, false);
                }, 20);
            } else {
                // Render popup directly (will be shown if conditions are met)
                echo '<div class="mulopimfwc-popup-shortcode-wrapper mulopimfwc-popup-auto-show" data-instance-id="' . esc_attr($instance_id) . '" data-layout="' . esc_attr($layout) . '">';
                // Popup will be rendered in footer via action hook
                echo '</div>';
                
                // Add popup to footer - will be shown automatically if conditions are met
                add_action('wp_footer', function() use ($instance_id, $layout) {
                    $this->render_popup_modal($instance_id, $layout, true);
                }, 20);
            }

            // Enqueue scripts and styles if not already enqueued
            $this->enqueue_popup_assets();

            return ob_get_clean();
        }

        /**
         * Render popup modal (reusable function)
         * 
         * @param string $instance_id Unique instance ID
         * @param string $layout Layout type
         * @param bool $show_modal Whether to show modal initially
         */
        private function render_popup_modal($instance_id = '', $layout = 'default', $show_modal = false)
        {
            if (mulopimfwc_is_manual_assignment_strict_mode()) {
                return;
            }

            global $mulopimfwc_locations;
            
            $is_user_logged_in = is_user_logged_in();
            $current_user = wp_get_current_user();
            $is_admin_or_manager = in_array('administrator', $current_user->roles) || in_array('shop_manager', $current_user->roles) || in_array('mulopimfwc_location_manager', $current_user->roles);
            
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
            $show_popup_admin = isset($options['show_popup_admin']) ? $options['show_popup_admin'] : 'off';
            $selected_location = $this->get_current_location();
            
            // Determine if modal should be shown
            $should_show = $show_modal || ($show_popup_admin === 'on' && empty($selected_location)) || (empty($selected_location) && !$is_admin_or_manager);
            
            // Filter locations for frontend display (active only, ordered by display_order)
            $locations = mulopimfwc_get_frontend_locations();

            // Use provided layout or fallback to options
            $template_selection = !empty($layout) && $layout !== 'default' ? $layout : (isset($options['template_selection']) ? $options['template_selection'] : 'default');
            
            if ($template_selection === 'modern') {
                $template_file = 'templates/modern-modal.php';
            } elseif ($template_selection === 'modern-simple') {
                $template_file = 'templates/modern-simple-modal.php';
            } elseif ($template_selection === 'classic') {
                $template_file = 'templates/classic-modal.php';
            } elseif (in_array($template_selection, ['tabs', 'compact', 'grid'], true)) {
                $template_file = 'templates/location-info-modal.php';
                $popup_layout = $template_selection;
            } else {
                $template_file = 'templates/modal.php';
            }
            
            $template_path = plugin_dir_path(__FILE__) . $template_file;
            if (!file_exists($template_path)) {
                $template_path = plugin_dir_path(__FILE__) . 'templates/modal.php';
            }

            // Set instance-specific variables
            $show_modal = $should_show;
            $modal_id = !empty($instance_id) ? 'lwp-store-selector-modal-' . sanitize_html_class($instance_id) : 'lwp-store-selector-modal';

            $allow_backdrop_close = false;
            if (!empty($instance_id) && !empty($GLOBALS['mulopimfwc_popup_shortcodes']) && is_array($GLOBALS['mulopimfwc_popup_shortcodes'])) {
                foreach ($GLOBALS['mulopimfwc_popup_shortcodes'] as $shortcode_info) {
                    if (!empty($shortcode_info['instance_id']) && $shortcode_info['instance_id'] === $instance_id) {
                        $allow_backdrop_close = !empty($shortcode_info['on_click_button']);
                        break;
                    }
                }
            }
            
            // Temporarily override modal ID for template
            $GLOBALS['mulopimfwc_modal_id'] = $modal_id;
            $GLOBALS['mulopimfwc_modal_instance_id'] = $instance_id;
            $GLOBALS['mulopimfwc_modal_layout'] = $layout;
            $GLOBALS['mulopimfwc_modal_allow_backdrop_close'] = $allow_backdrop_close;
            
            include $template_path;
            
            // Clean up
            unset($GLOBALS['mulopimfwc_modal_id']);
            unset($GLOBALS['mulopimfwc_modal_instance_id']);
            unset($GLOBALS['mulopimfwc_modal_layout']);
            unset($GLOBALS['mulopimfwc_modal_allow_backdrop_close']);
        }

        /**
         * Enqueue popup assets
         */
        private function enqueue_popup_assets()
        {
            static $enqueued = false;
            if ($enqueued) {
                return;
            }
            $enqueued = true;

            // Always enqueue all popup scripts and styles when shortcodes are used to support different layouts
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
            
            // Enqueue main style if not already enqueued
            if (!wp_style_is('mulopimfwc_style', 'enqueued')) {
                wp_enqueue_style('mulopimfwc_style', plugins_url('assets/css/style.css', __FILE__), [], '1.1.4.11');
            }
            
            // Enqueue modern popup script
            if (!wp_script_is('mulopimfwc-modern-popup', 'enqueued')) {
                wp_enqueue_script(
                    'mulopimfwc-modern-popup',
                    plugins_url('assets/js/modern-popup.js', __FILE__),
                    ['jquery'],
                    '1.1.4.11',
                    true
                );
            }
            
            // Enqueue classic popup script
            if (!wp_script_is('mulopimfwc-classic-popup', 'enqueued')) {
                wp_enqueue_script(
                    'mulopimfwc-classic-popup',
                    plugins_url('assets/js/classic-popup.js', __FILE__),
                    ['jquery'],
                    '1.1.4.11',
                    true
                );
            }
            
            // Enqueue popup layouts script
            if (!wp_script_is('mulopimfwc-popup-layouts', 'enqueued')) {
                wp_enqueue_script(
                    'mulopimfwc-popup-layouts',
                    plugins_url('assets/js/popup-layouts.js', __FILE__),
                    ['jquery'],
                    '1.1.4.11',
                    true
                );
            }
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
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
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

        /**
         * OPTIMIZED: Batch load product location relationships to prevent N+1 queries
         * Loads all requested product locations in a single database query
         * 
         * @param array $product_ids Array of product IDs to load
         * @return void
         */
        private static function batch_load_product_locations($product_ids)
        {
            // Initialize cache if needed
            if (self::$product_locations_cache === null) {
                self::$product_locations_cache = [];
            }
            
            // Filter out already cached product IDs
            $product_ids = array_unique(array_map('intval', $product_ids));
            $uncached_ids = array_filter($product_ids, function($id) {
                return $id > 0 && !isset(self::$product_locations_cache[$id]);
            });
            
            if (empty($uncached_ids)) {
                return;
            }
            
            global $wpdb;
            
            // Build placeholders for IN clause
            $placeholders = implode(',', array_fill(0, count($uncached_ids), '%d'));
            
            // Single query to get all product location relationships
            // This replaces N individual queries with 1 batch query
            // We use taxonomy name directly instead of term_taxonomy_id for better compatibility
            $query = $wpdb->prepare(
                "SELECT tr.object_id, t.slug
                FROM {$wpdb->term_relationships} tr
                INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                WHERE tr.object_id IN ($placeholders)
                AND tt.taxonomy = %s",
                array_merge($uncached_ids, ['mulopimfwc_store_location'])
            );
            
            $results = $wpdb->get_results($query, ARRAY_A);
            
            // Initialize all products with empty arrays
            foreach ($uncached_ids as $id) {
                self::$product_locations_cache[$id] = [];
            }
            
            // Group results by product ID and decode slugs
            if (!empty($results)) {
                foreach ($results as $row) {
                    $product_id = (int) $row['object_id'];
                    $slug = rawurldecode($row['slug']);
                    
                    if (!isset(self::$product_locations_cache[$product_id])) {
                        self::$product_locations_cache[$product_id] = [];
                    }
                    
                    if (!in_array($slug, self::$product_locations_cache[$product_id], true)) {
                        self::$product_locations_cache[$product_id][] = $slug;
                    }
                }
            }
        }
        
        /**
         * Get product location slugs from cache (with batch loading)
         * 
         * @param int $product_id Product ID
         * @return array Array of location slugs
         */
        private function get_product_location_slugs($product_id)
        {
            $product_id = (int) $product_id;
            
            if ($product_id <= 0) {
                return [];
            }
            
            // Track requested product IDs for batch loading
            if (!in_array($product_id, self::$requested_product_ids, true)) {
                self::$requested_product_ids[] = $product_id;
            }
            
            // Initialize cache if needed
            if (self::$product_locations_cache === null) {
                self::$product_locations_cache = [];
            }
            
            // If not cached yet, perform batch load
            if (!isset(self::$product_locations_cache[$product_id])) {
                // Load this product along with any other pending products
                self::batch_load_product_locations(self::$requested_product_ids);
            }
            
            // Return cached result (or empty array if not found)
            return isset(self::$product_locations_cache[$product_id]) 
                ? self::$product_locations_cache[$product_id] 
                : [];
        }
        
        /**
         * OPTIMIZED: Check if product belongs to location using cached data
         * Prevents N+1 queries by batch loading all product locations
         * 
         * @param int $product_id Product ID
         * @return bool True if product belongs to current location
         */
        private function product_belongs_to_location($product_id)
        {
            $location = $this->get_current_location();
            global $mulopimfwc_options;
            $enable_all_locations = mulopimfwc_is_all_locations_enabled($mulopimfwc_options) ? 'on' : 'off';
            $manager_locations = $this->get_location_manager_frontend_locations();

            // Get product location slugs from cache (batch loaded)
            $terms = $this->get_product_location_slugs($product_id);

            if (is_array($manager_locations)) {
                if (empty($manager_locations)) {
                    return false;
                }

                $allowed_locations = $manager_locations;
                if (!empty($location) && $location !== 'all-products') {
                    if (!in_array($location, $manager_locations, true)) {
                        return false;
                    }
                    $allowed_locations = [$location];
                }

                if (empty($terms)) {
                    return false;
                }
                return !empty(array_intersect($allowed_locations, $terms));
            }

            if (!$location || $location === 'all-products') {
                return true;
            }

            if (empty($terms) && $enable_all_locations === 'on') {
                return true; // Product is available in all locations
            }
            return in_array($location, $terms, true);
        }

        /**
         * Check if out-of-stock locations should be treated as unavailable
         *
         * @param array $options
         * @return bool
         */
        private function should_hide_out_of_stock_locations($options): bool
        {
            return isset($options['hide_out_of_stock_locations']) && $options['hide_out_of_stock_locations'] === 'on';
        }

        /**
         * Check if product is out of stock for the currently selected location
         *
         * @param int $product_id
         * @param array $options
         * @return bool
         */
        private function is_product_out_of_stock_for_selected_location($product_id, $options): bool
        {
            if (!$this->should_hide_out_of_stock_locations($options)) {
                return false;
            }

            $location = $this->get_current_location();
            if (empty($location) || $location === 'all-products') {
                return false;
            }

            if (!function_exists('mulopimfwc_is_product_out_of_stock_for_location')) {
                return false;
            }

            return mulopimfwc_is_product_out_of_stock_for_location($product_id);
        }

        /**
         * Check if product is available for the current location context
         *
         * @param int $product_id
         * @param array $options
         * @return bool
         */
        private function product_is_available_in_current_location($product_id, $options): bool
        {
            if (!$this->product_belongs_to_location($product_id)) {
                return false;
            }

            if ($this->is_product_out_of_stock_for_selected_location($product_id, $options)) {
                return false;
            }

            return true;
        }
        
        /**
         * Preload product locations for a batch of product IDs
         * Call this method before looping through products to prevent N+1 queries
         * 
         * @param array $product_ids Array of product IDs to preload
         * @return void
         */
        public function preload_product_locations($product_ids)
        {
            if (empty($product_ids) || !is_array($product_ids)) {
                return;
            }
            
            // Add to requested IDs
            $product_ids = array_unique(array_map('intval', $product_ids));
            self::$requested_product_ids = array_unique(array_merge(
                self::$requested_product_ids,
                $product_ids
            ));
            
            // Load immediately
            self::batch_load_product_locations($product_ids);
        }
        
        /**
         * Clear the product locations cache
         * Useful when products are updated or locations are changed
         * 
         * @return void
         */
        public static function clear_product_locations_cache()
        {
            self::$product_locations_cache = null;
            self::$requested_product_ids = [];
            self::$batch_loaded = false;
        }
        
        /**
         * Clear cache when a product is updated
         * 
         * @param int $post_id Post ID
         * @param WP_Post $post Post object
         * @return void
         */
        public function clear_cache_on_product_update($post_id, $post)
        {
            // Only clear cache for products
            if (isset($post->post_type) && $post->post_type === 'product') {
                // Clear only the specific product from cache if it exists
                if (self::$product_locations_cache !== null && isset(self::$product_locations_cache[$post_id])) {
                    unset(self::$product_locations_cache[$post_id]);
                }
            }
        }
        
        /**
         * Clear cache when a location term is updated
         * 
         * @param int $term_id Term ID
         * @param int $tt_id Term taxonomy ID
         * @param string $taxonomy Taxonomy name
         * @return void
         */
        public function clear_cache_on_term_update($term_id, $tt_id, $taxonomy)
        {
            // Only clear cache for location taxonomy
            if ($taxonomy === 'mulopimfwc_store_location') {
                // Clear entire cache since term relationships may have changed
                self::clear_product_locations_cache();
            }
        }
        
        /**
         * Clear cache when a location term is deleted
         * 
         * @param int $term_id Term ID
         * @param int $tt_id Term taxonomy ID
         * @param string $taxonomy Taxonomy name
         * @param WP_Term $deleted_term Deleted term object
         * @return void
         */
        public function clear_cache_on_term_delete($term_id, $tt_id, $taxonomy, $deleted_term)
        {
            // Only clear cache for location taxonomy
            if ($taxonomy === 'mulopimfwc_store_location') {
                // Clear entire cache since term relationships have changed
                self::clear_product_locations_cache();
            }
        }
        
        /**
         * Clear cache when object terms are set (product location assignments changed)
         * 
         * @param int $object_id Object ID
         * @param array $terms Array of term IDs
         * @param array $tt_ids Array of term taxonomy IDs
         * @param string $taxonomy Taxonomy name
         * @param bool $append Whether to append terms
         * @param array $old_tt_ids Old term taxonomy IDs
         * @return void
         */
        public function clear_cache_on_object_terms_update($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids)
        {
            // Only clear cache for location taxonomy and products
            if ($taxonomy === 'mulopimfwc_store_location') {
                $post_type = get_post_type($object_id);
                if ($post_type === 'product') {
                    // Clear only the specific product from cache
                    if (self::$product_locations_cache !== null && isset(self::$product_locations_cache[$object_id])) {
                        unset(self::$product_locations_cache[$object_id]);
                    }
                }
            }
        }

        /**
         * FIXED: Enhanced product blocks filtering to respect visibility and stock settings
         */
        public function filter_product_blocks($html, $data, $product)
        {
            // Check if filtering should be applied based on settings
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
            $strict_filtering = mulopimfwc_get_strict_filtering_value($options);
            $filtered_sections = ['shop', 'search', 'related', 'recently_viewed', 'cross_sells', 'upsells', 'widgets', 'blocks', 'rest_api'];
            
            // Check if blocks filtering is enabled
            if ($strict_filtering === 'disabled' || !in_array('blocks', $filtered_sections, true)) {
                return $html;
            }
            
            $product_id = $product->get_id();
            
            // Check location
            if (!$this->product_belongs_to_location($product_id)) {
                return '';
            }
            
            // Also check stock status if setting requires it
            $show_out_of_stock = isset($options['show_out_of_stock_products']) ? $options['show_out_of_stock_products'] : 'none';
            if ($show_out_of_stock === 'hide' && mulopimfwc_is_product_out_of_stock_for_location($product_id)) {
                return '';
            }
            
            return $html;
        }

        public function filter_ajax_searched_products($products)
        {
            $location = $this->get_current_location();
            global $mulopimfwc_options;
            $enable_all_locations = mulopimfwc_is_all_locations_enabled($mulopimfwc_options) ? 'on' : 'off';
            $manager_locations = $this->get_location_manager_frontend_locations();

            if ($manager_locations === null && (!$location || $location === 'all-products')) {
                return $products;
            }

            // OPTIMIZED: Preload all product locations in one query
            $product_ids = array_keys($products);
            if (!empty($product_ids)) {
                $this->preload_product_locations($product_ids);
            }

            foreach ($products as $id => $product) {
                if (!$this->product_belongs_to_location($id)) {
                    unset($products[$id]);
                }
            }

            return $products;
        }

        /**
         * FIXED: Enhanced REST API products filtering to respect visibility settings
         */
        public function filter_rest_api_products($args, $request)
        {
            // Check if filtering should be applied based on settings
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
            $strict_filtering = mulopimfwc_get_strict_filtering_value($options);
            $filtered_sections = ['shop', 'search', 'related', 'recently_viewed', 'cross_sells', 'upsells', 'widgets', 'blocks', 'rest_api'];
            
            // Check if REST API filtering is enabled
            if ($strict_filtering === 'disabled' || !in_array('rest_api', $filtered_sections, true)) {
                return $args;
            }
            
            global $mulopimfwc_options;
            $enable_all_locations = mulopimfwc_is_all_locations_enabled($mulopimfwc_options) ? 'on' : 'off';
            $manager_locations = $this->get_location_manager_frontend_locations();
            
            if (is_array($manager_locations)) {
                if (empty($manager_locations)) {
                    $args['post__in'] = [0];
                    return $args;
                }

                $current_location = $this->get_current_location();
                $target_locations = $manager_locations;
                if (!empty($current_location) && $current_location !== 'all-products') {
                    if (!in_array($current_location, $manager_locations, true)) {
                        $args['post__in'] = [0];
                        return $args;
                    }
                    $target_locations = [$current_location];
                }

                $args['tax_query'][] = $this->build_location_tax_query($target_locations, $enable_all_locations, true);
                
                // FIXED: Also filter out-of-stock products if setting requires it
                $this->apply_stock_filtering($args, $target_locations);
                
                return $args;
            }

            $location = $this->get_current_location();

            if (!$location || $location === 'all-products' || $enable_all_locations === 'on') {
                // Still apply stock filtering even if location filtering is off
                if ($location && $location !== 'all-products') {
                    $this->apply_stock_filtering($args, [$location]);
                }
                return $args;
            }

            // Initialize tax_query if needed
            if (!isset($args['tax_query'])) {
                $args['tax_query'] = [];
            }

            // FIXED: Validate location exists before filtering
            $location_term = mulopimfwc_validate_location_slug($location);
            if (!$location_term) {
                // Invalid location - return empty results
                $args['post__in'] = [0];
                return $args;
            }

            $args['tax_query'][] = [
                'taxonomy' => 'mulopimfwc_store_location',
                'field' => 'slug',
                'terms' => $location,
            ];
            
            // FIXED: Also filter out-of-stock products if setting requires it
            $this->apply_stock_filtering($args, [$location]);

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

            // OPTIMIZED: Preload all product locations in one query
            $product_ids = [];
            foreach ($cart_contents as $item) {
                if (is_array($item) && isset($item['product_id'])) {
                    $product_ids[] = (int) $item['product_id'];
                }
            }
            if (!empty($product_ids)) {
                $this->preload_product_locations($product_ids);
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

            // OPTIMIZED: Preload all product locations in one query
            $product_ids = array_filter(array_map('intval', $viewed_products));
            if (!empty($product_ids)) {
                $this->preload_product_locations($product_ids);
            }

            $filtered_products = [];
            foreach ($viewed_products as $product_id) {
                $product_id = (int) $product_id;
                if ($product_id > 0 && $this->product_belongs_to_location($product_id)) {
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

            $strict_filtering = function_exists('mulopimfwc_get_strict_filtering_value')
                ? mulopimfwc_get_strict_filtering_value($options)
                : (isset($options['strict_filtering']) ? $options['strict_filtering'] : 'enabled');

            if ($strict_filtering === 'disabled') {
                return false;
            }

            $filtered_sections = ['shop', 'search', 'related', 'recently_viewed', 'cross_sells', 'upsells', 'widgets', 'blocks', 'rest_api'];
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

            if ($query->is_tax('mulopimfwc_store_location') || $query->get('mulopimfwc_store_location') || $query->get('taxonomy') === 'mulopimfwc_store_location') {
                return;
            }

            $options = $this->get_display_options();

            $section = '';
            if (is_shop() || is_product_category() || is_product_tag() || is_post_type_archive('product') || is_product_taxonomy()) {
                $section = 'shop';
            } elseif (is_search()) {
                $section = 'search';
            } else {
                return;
            }

            $tax_query = (array) $query->get('tax_query');
            global $mulopimfwc_options;
            $enable_all_locations = mulopimfwc_is_all_locations_enabled($mulopimfwc_options) ? 'on' : 'off';
            $manager_locations = $this->get_location_manager_frontend_locations();
            if (is_array($manager_locations)) {
                if (empty($manager_locations)) {
                    $query->set('post__in', [0]);
                    return;
                }

                $current_location = $this->get_current_location();
                $target_locations = $manager_locations;
                if (!empty($current_location) && $current_location !== 'all-products') {
                    if (!in_array($current_location, $manager_locations, true)) {
                        $query->set('post__in', [0]);
                        return;
                    }
                    $target_locations = [$current_location];
                }

                $tax_query[] = $this->build_location_tax_query($target_locations, $enable_all_locations, true);
                $query->set('tax_query', $tax_query);
                return;
            }

            $location = $this->get_filtered_location($section);
            if (!$location) {
                return;
            }

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
            $product_priority_display = mulopimfwc_get_product_priority_display_value($options);

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
            $product_priority_display = mulopimfwc_get_product_priority_display_value($options);

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

        /**
         * Handle single product query to prevent 404 when setting is show_message
         * This runs early in pre_get_posts to ensure product is included in query
         * 
         * @param WP_Query $query The WordPress query object
         * @return void
         */
        public function handle_single_product_query($query)
        {
            // Only run on frontend main query
            if (is_admin() || !$query->is_main_query()) {
                return;
            }

            // CRITICAL FIX: Only run on SINGLE product pages, not shop/archive pages
            // Use query-specific checks (global is_singular can be unreliable in pre_get_posts)
            $post_type = $query->get('post_type');
            $has_single_identifier = !empty($query->get('p')) || !empty($query->get('name')) || !empty($query->get('product'));
            $is_product_query = (
                $post_type === 'product' ||
                (is_array($post_type) && in_array('product', $post_type, true)) ||
                empty($post_type)
            );
            $is_single_product_query = $query->is_singular('product') || ($is_product_query && $has_single_identifier);

            if (!$is_single_product_query) {
                return;
            }

            // Get the product ID from query
            $product_id = 0;
            if (isset($query->query_vars['p']) && $query->query_vars['p'] > 0) {
                $product_id = (int) $query->query_vars['p'];
                // Verify it's actually a product
                $post_type_check = get_post_type($product_id);
                if ($post_type_check !== 'product') {
                    return;
                }
            } elseif (isset($query->query_vars['name'])) {
                $product = get_page_by_path($query->query_vars['name'], OBJECT, 'product');
                if ($product) {
                    $product_id = $product->ID;
                } else {
                    return;
                }
            } elseif (isset($query->query_vars['product'])) {
                $product = get_page_by_path($query->query_vars['product'], OBJECT, 'product');
                if ($product) {
                    $product_id = $product->ID;
                } else {
                    return;
                }
            } else {
                return;
            }

            if ($product_id <= 0) {
                return;
            }

            // Get settings
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
            $behavior = mulopimfwc_get_single_product_unavailable_behavior($options);
            
            // If behavior is show_404, let WordPress handle it normally (don't interfere)
            if ($behavior === 'show_404') {
                return;
            }
            
            // Check if strict filtering is enabled
            $strict_filtering = mulopimfwc_get_strict_filtering_value($options);
            if ($strict_filtering === 'disabled') {
                return; // Don't filter if strict filtering is disabled
            }
            
            // Check if product belongs to current location
            if ($this->product_is_available_in_current_location($product_id, $options)) {
                return; // Product is available, no action needed
            }
            
            // Product is not available, but we want to show it with message
            // Ensure the product is included in the query by forcing it
            // Store original query vars to restore if needed
            $query->set('post__in', [$product_id]);
            $query->set('post_type', 'product');
            // Remove any tax_query that might filter it out
            $existing_tax_query = $query->get('tax_query');
            if (!empty($existing_tax_query)) {
                // Remove location-based tax queries but keep others
                $filtered_tax_query = [];
                foreach ($existing_tax_query as $tax_item) {
                    if (isset($tax_item['taxonomy']) && $tax_item['taxonomy'] === 'mulopimfwc_store_location') {
                        continue; // Skip location tax queries
                    }
                    $filtered_tax_query[] = $tax_item;
                }
                $query->set('tax_query', $filtered_tax_query);
            }
            
            // Mark that we've handled this product - this flag will be checked in posts_clauses filter
            $query->set('mulopimfwc_force_show_product', true);
            $query->set('mulopimfwc_skip_location_filter', true);
        }

        /**
         * Handle single product pages when product is not available in selected location
         * Checks the setting and either shows 404 or displays product with unavailable message
         * This runs on template_redirect as a fallback
         * 
         * @return void
         */
        public function handle_single_product_unavailable()
        {
            // Only run on frontend single product pages
            if (is_admin() || !is_singular('product')) {
                return;
            }

            global $post, $wp_query;
            if (!$post || $post->post_type !== 'product') {
                return;
            }

            // If 404 is already set, check if we should override it
            if ($wp_query->is_404()) {
                // Get settings
                global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
                $behavior = mulopimfwc_get_single_product_unavailable_behavior($options);
                
                // If behavior is show_message, unset 404 and show product
                if ($behavior === 'show_message') {
                    // Check if strict filtering is enabled
                    $strict_filtering = mulopimfwc_get_strict_filtering_value($options);
                    if ($strict_filtering !== 'disabled') {
                        // Check if product doesn't belong to location
                        if (!$this->product_is_available_in_current_location($post->ID, $options)) {
                            // Unset 404 and restore the post
                            $wp_query->is_404 = false;
                            $wp_query->is_singular = true;
                            $wp_query->queried_object = $post;
                            $wp_query->queried_object_id = $post->ID;
                            status_header(200);
                            
                            // Add notice to display on product page
                            add_action('woocommerce_single_product_summary', [$this, 'display_product_unavailable_notice'], 1);
                            // Disable add to cart button
                            add_filter('woocommerce_is_purchasable', [$this, 'disable_purchasable_for_unavailable_product'], 10, 2);
                            add_filter('woocommerce_variation_is_purchasable', [$this, 'disable_purchasable_for_unavailable_product'], 10, 2);
                            return;
                        }
                    }
                }
                // If we get here and 404 is set, keep it
                return;
            }

            $product_id = $post->ID;
            
            // Get settings
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
            $behavior = mulopimfwc_get_single_product_unavailable_behavior($options);
            
            // Check if strict filtering is enabled
            $strict_filtering = mulopimfwc_get_strict_filtering_value($options);
            if ($strict_filtering === 'disabled') {
                return; // Don't filter if strict filtering is disabled
            }
            
            // Check if product belongs to current location
            if ($this->product_is_available_in_current_location($product_id, $options)) {
                return; // Product is available, no action needed
            }
            
            // Product is not available in selected location
            if ($behavior === 'show_404') {
                // Show 404 page (current behavior)
                $wp_query->set_404();
                status_header(404);
                nocache_headers();
            } else {
                // Show product with unavailable message
                // Add notice to display on product page
                add_action('woocommerce_single_product_summary', [$this, 'display_product_unavailable_notice'], 1);
                // Disable add to cart button
                add_filter('woocommerce_is_purchasable', [$this, 'disable_purchasable_for_unavailable_product'], 10, 2);
                add_filter('woocommerce_variation_is_purchasable', [$this, 'disable_purchasable_for_unavailable_product'], 10, 2);
            }
        }

        /**
         * Display notice when product is not available in selected location
         * 
         * @return void
         */
        public function display_product_unavailable_notice()
        {
            $location = $this->get_current_location();
            $location_name = '';
            
            if (!empty($location) && $location !== 'all-products') {
                $location_term = get_term_by('slug', $location, 'mulopimfwc_store_location');
                if ($location_term && !is_wp_error($location_term)) {
                    $location_name = $location_term->name;
                }
            }
            
            $message = __('This product is not available in your selected location.', 'multi-location-product-and-inventory-management');
            if (!empty($location_name)) {
                $message = sprintf(
                    __('This product is not available at %s. Please select a different location to view this product.', 'multi-location-product-and-inventory-management'),
                    esc_html($location_name)
                );
            }
            
            echo '<div class="woocommerce-info mulopimfwc-product-unavailable-notice" style="background-color: #fff3cd; border: 1px solid #ffc107; border-left: 4px solid #ffc107; border-radius: 4px; padding: 12px 16px; margin: 20px 0;">';
            echo '<div style="display: flex; align-items: start; gap: 12px;">';
            echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" style="flex-shrink: 0; margin-top: 2px;" fill="#856404">';
            echo '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z"/>';
            echo '</svg>';
            echo '<div>';
            echo '<p style="margin: 0; color: #856404; font-weight: 500;">' . esc_html($message) . '</p>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }

        /**
         * Disable purchasable status for products not available in selected location
         * 
         * @param bool $is_purchasable Whether product is purchasable
         * @param WC_Product $product Product object
         * @return bool
         */
        public function disable_purchasable_for_unavailable_product($is_purchasable, $product)
        {
            if (!$product) {
                return $is_purchasable;
            }
            
            $product_id = $product->get_id();
            
            // Check if product belongs to current location
            if (!$this->product_belongs_to_location($product_id)) {
                return false;
            }
            
            return $is_purchasable;
        }

        /**
         * FIXED: Enhanced related products filtering to respect visibility and stock settings
         */
        public function filter_related_products_by_location($related_products, $product_id, $args)
        {
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
            $strict_filtering = mulopimfwc_get_strict_filtering_value($options);
            $filtered_sections = ['shop', 'search', 'related', 'recently_viewed', 'cross_sells', 'upsells', 'widgets', 'blocks', 'rest_api'];
            
            // Check if filtering should be applied
            if ($strict_filtering === 'disabled' || !in_array('related', $filtered_sections, true)) {
                return $related_products;
            }
            
            $location = $this->get_filtered_location('related');

            if (!$location) {
                return $related_products;
            }

            // OPTIMIZED: Preload all product locations in one query
            if (!empty($related_products)) {
                $this->preload_product_locations($related_products);
            }
            
            // Filter by location and stock status
            $filtered = array_filter($related_products, function($product_id) use ($location) {
                if (!$this->product_belongs_to_location($product_id)) {
                    return false;
                }
                
                // Also check stock status if setting requires it
                global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
                $show_out_of_stock = isset($options['show_out_of_stock_products']) ? $options['show_out_of_stock_products'] : 'none';
                if ($show_out_of_stock === 'hide') {
                    return !mulopimfwc_is_product_out_of_stock_for_location($product_id);
                }
                
                return true;
            });

            return $filtered;
        }

        /**
         * FIXED: Enhanced cross-sells filtering to respect visibility and stock settings
         */
        public function filter_cross_sells_by_location($cross_sells)
        {
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
            $strict_filtering = mulopimfwc_get_strict_filtering_value($options);
            $filtered_sections = ['shop', 'search', 'related', 'recently_viewed', 'cross_sells', 'upsells', 'widgets', 'blocks', 'rest_api'];
            
            // Check if filtering should be applied
            if ($strict_filtering === 'disabled' || !in_array('cross_sells', $filtered_sections, true)) {
                return $cross_sells;
            }
            
            $location = $this->get_filtered_location('cross_sells');

            if (!$location) {
                return $cross_sells;
            }

            // OPTIMIZED: Preload all product locations in one query
            if (!empty($cross_sells)) {
                $this->preload_product_locations($cross_sells);
            }
            
            // Filter by location and stock status
            $filtered = array_filter($cross_sells, function($product_id) use ($location) {
                if (!$this->product_belongs_to_location($product_id)) {
                    return false;
                }
                
                // Also check stock status if setting requires it
                global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
                $show_out_of_stock = isset($options['show_out_of_stock_products']) ? $options['show_out_of_stock_products'] : 'none';
                if ($show_out_of_stock === 'hide') {
                    return !mulopimfwc_is_product_out_of_stock_for_location($product_id);
                }
                
                return true;
            });

            return $filtered;
        }

        /**
         * FIXED: Enhanced upsells filtering to respect visibility and stock settings
         */
        public function filter_upsells_by_location($upsell_ids, $product_id)
        {
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
            $strict_filtering = mulopimfwc_get_strict_filtering_value($options);
            $filtered_sections = ['shop', 'search', 'related', 'recently_viewed', 'cross_sells', 'upsells', 'widgets', 'blocks', 'rest_api'];
            
            // Check if filtering should be applied
            if ($strict_filtering === 'disabled' || !in_array('upsells', $filtered_sections, true)) {
                return $upsell_ids;
            }
            
            $location = $this->get_filtered_location('upsells');

            if (!$location) {
                return $upsell_ids;
            }

            // OPTIMIZED: Preload all product locations in one query
            if (!empty($upsell_ids)) {
                $this->preload_product_locations($upsell_ids);
            }
            
            // Filter by location and stock status
            $filtered = array_filter($upsell_ids, function($product_id) use ($location) {
                if (!$this->product_belongs_to_location($product_id)) {
                    return false;
                }
                
                // Also check stock status if setting requires it
                global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
                $show_out_of_stock = isset($options['show_out_of_stock_products']) ? $options['show_out_of_stock_products'] : 'none';
                if ($show_out_of_stock === 'hide') {
                    return !mulopimfwc_is_product_out_of_stock_for_location($product_id);
                }
                
                return true;
            });

            return $filtered;
        }

        /**
         * FIXED: Enhanced widget products filtering to respect settings
         */
        public function filter_widget_products_by_location($query_args)
        {
            // Use the enhanced filtering method that respects settings
            return $this->filter_shortcode_products($query_args);
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

        public function ajax_switch_location()
        {
            // Validate request
            check_ajax_referer('multi-location-product-and-inventory-management', 'nonce');

            $location = isset($_POST['location']) ? sanitize_text_field(wp_unslash(rawurldecode($_POST['location']))) : '';

            if (empty($location)) {
                $this->currency_debug_log('ajax_switch_location_rejected', [
                    'reason' => 'empty_location',
                    'posted_location' => isset($_POST['location']) ? wp_unslash($_POST['location']) : null,
                ], 25);
                wp_send_json_error(['message' => __('Invalid location.', 'multi-location-product-and-inventory-management')]);
            }

            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);

            $allow_mixed = mulopimfwc_is_mixed_location_cart_enabled($options) ? 'on' : 'off';

            $behavior = isset($options['location_switching_behavior']) ? $options['location_switching_behavior'] : 'update_cart';
            $initial_behavior = $behavior;
            if (mulopimfwc_is_location_wise_currency_enabled($options) && $behavior === 'preserve_cart') {
                $behavior = 'update_cart';
            }

            $this->currency_debug_log('ajax_switch_location_request', [
                'location' => $location,
                'allow_mixed' => $allow_mixed,
                'initial_behavior' => $initial_behavior,
                'effective_behavior' => $behavior,
                'location_wise_currency_enabled' => mulopimfwc_is_location_wise_currency_enabled($options),
                'cookie_before' => function_exists('mulopimfwc_get_store_location_cookie')
                    ? mulopimfwc_get_store_location_cookie()
                    : '',
            ], 25);

            // Store the selected location cookie immediately
            $this->set_store_location_cookie($location);

            $removed_items = [];

            if ($allow_mixed !== 'on' && $behavior !== 'preserve_cart') {
                $removed_items = $this->remove_unavailable_cart_items($location);
            }

            $this->currency_debug_log('ajax_switch_location_response', [
                'location' => $location,
                'behavior' => $behavior,
                'allow_mixed' => $allow_mixed,
                'removed_count' => count($removed_items),
                'cookie_after' => function_exists('mulopimfwc_get_store_location_cookie')
                    ? mulopimfwc_get_store_location_cookie()
                    : '',
            ], 25);

            wp_send_json_success([
                'location' => $location,
                'removed_items' => $removed_items,
                'removed_count' => count($removed_items),
                'behavior' => $behavior,
                'allow_mixed' => $allow_mixed,
            ]);
        }

        private function set_store_location_cookie($location)
        {
            // Sanitize location slug
            $location = sanitize_title($location);
            
            if (empty($location)) {
                $this->currency_debug_log('set_store_location_cookie_skipped', [
                    'reason' => 'empty_location',
                ], 25);
                return;
            }

            // Get location term object for hooks
            $location_obj = null;
            if ($location !== 'all-products') {
                if (function_exists('mulopimfwc_validate_location_slug')) {
                    // Resolve using uncached validator so aliases and fresh term updates work immediately.
                    $location_obj = mulopimfwc_validate_location_slug($location, false);
                } else {
                    $location_obj = get_term_by('slug', $location, 'mulopimfwc_store_location');
                }

                if ($location_obj && !is_wp_error($location_obj) && isset($location_obj->slug)) {
                    $location = sanitize_title(rawurldecode((string) $location_obj->slug));
                } else {
                    $location_obj = null;
                    $this->currency_debug_log('set_store_location_cookie_unverified', [
                        'reason' => 'location_term_not_resolved',
                        'location' => $location,
                    ], 25);
                }
            }

            // Set cookie using standardized helper function
            $cookie_set = mulopimfwc_set_location_cookie($location, null, $location_obj);

            $this->currency_debug_log('set_store_location_cookie', [
                'location' => $location,
                'cookie_set' => (bool) $cookie_set,
                'cookie_name' => function_exists('mulopimfwc_get_location_cookie_name')
                    ? mulopimfwc_get_location_cookie_name()
                    : '',
                'cookie_after_set' => function_exists('mulopimfwc_get_store_location_cookie')
                    ? mulopimfwc_get_store_location_cookie()
                    : '',
                'term_id' => ($location_obj instanceof WP_Term) ? (int) $location_obj->term_id : null,
                'term_found' => ($location === 'all-products') ? true : (bool) ($location_obj && !is_wp_error($location_obj)),
            ], 25);

            // Fire action hook after location is selected
            do_action('mulopimfwc_location_selected', $location, $location_obj);
        }

        private function remove_unavailable_cart_items(string $location_slug): array
        {
            if (!function_exists('WC') || !WC()->cart) {
                return [];
            }

            $removed_items = [];
            $cart = WC()->cart;

            foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
                if (!$this->cart_item_available_for_location($cart_item, $location_slug)) {
                    $product_name = '';

                    if (isset($cart_item['data']) && is_object($cart_item['data'])) {
                        $product_name = $cart_item['data']->get_name();
                    } elseif (isset($cart_item['product_id'])) {
                        $product_name = get_the_title($cart_item['product_id']);
                    }

                    $removed_items[] = $product_name;
                    $cart->remove_cart_item($cart_item_key);
                }
            }

            if (!empty($removed_items)) {
                $cart->calculate_totals();
                $this->clear_cart_cache();
            }

            return array_filter($removed_items);
        }

        private function cart_item_available_for_location(array $cart_item, string $location_slug): bool
        {
            if (empty($location_slug) || $location_slug === 'all-products') {
                return true;
            }

            $product_ids_to_check = [];

            if (!empty($cart_item['variation_id'])) {
                $product_ids_to_check[] = (int) $cart_item['variation_id'];
            }

            if (!empty($cart_item['product_id'])) {
                $product_ids_to_check[] = (int) $cart_item['product_id'];
            }

            foreach ($product_ids_to_check as $product_id) {
                if ($this->product_available_for_location($product_id, $location_slug)) {
                    return true;
                }
            }

            return false;
        }

        private function product_available_for_location(int $product_id, string $location_slug): bool
        {
            if (empty($location_slug) || $location_slug === 'all-products') {
                return true;
            }

            global $mulopimfwc_options;

            if (!isset($mulopimfwc_options) || !is_array($mulopimfwc_options)) {
                $mulopimfwc_options = is_array($mulopimfwc_options ?? null)
                    ? $mulopimfwc_options
                    : get_option('mulopimfwc_display_options', []);
            }

            $enable_all_locations = mulopimfwc_is_all_locations_enabled($mulopimfwc_options) ? 'on' : 'off';

            $terms = array_map('rawurldecode',wp_get_object_terms($product_id, 'mulopimfwc_store_location', ['fields' => 'slugs']));

            if (empty($terms)) {
                return $enable_all_locations === 'on';
            }

            if (is_wp_error($terms) || !in_array($location_slug, $terms, true)) {
                return false;
            }

            $location_term = get_term_by('slug', $location_slug, 'mulopimfwc_store_location');
            if ($location_term && !is_wp_error($location_term)) {
                $is_disabled = get_post_meta($product_id, '_location_disabled_' . $location_term->term_id, true);
                if (!empty($is_disabled)) {
                    return false;
                }
            }

            return true;
        }

        function custom_admin_styles()
        {
            wp_enqueue_style('mulopimfwc-custom-admin-style', plugin_dir_url(__FILE__) . 'assets/css/admin-style.css', array(), "1.1.4.11");
        }

        /**
         * Enqueue scripts for order location selector
         */
        public function enqueue_order_location_scripts($hook)
        {
            // Check if we're on an order edit page
            $is_order_page = false;
            
            // For regular orders (non-HPOS)
            if (in_array($hook, ['post.php', 'post-new.php'])) {
                global $post;
                if ($post && $post->post_type === 'shop_order') {
                    $is_order_page = true;
                }
            }
            
            // For HPOS orders
            if ($hook === 'woocommerce_page_wc-orders' || (isset($_GET['page']) && $_GET['page'] === 'wc-orders')) {
                $is_order_page = true;
            }
            
            if (!$is_order_page) {
                return;
            }

            // Enqueue inline script for order location updates
            $script = "
            jQuery(document).ready(function($) {
                $(document).on('change', '.mulopimfwc-order-item-location-select', function() {
                    var \$select = $(this);
                    var \$container = \$select.closest('.mulopimfwc-order-item-location-selector');
                    var \$updating = \$container.find('.mulopimfwc-location-updating');
                    var itemId = \$select.data('item-id');
                    var newLocation = \$select.val();
                    var originalValue = \$select.data('original-value') || \$select.val();
                    
                    // Prevent multiple simultaneous requests
                    if (\$select.prop('disabled')) {
                        \$select.val(originalValue);
                        return;
                    }
                    
                    // Store original value
                    \$select.data('original-value', originalValue);
                    
                    // Disable select and show updating message
                    \$select.prop('disabled', true);
                    \$updating.show();
                    
                    // Get order ID from container
                    var orderId = \$container.data('order-id') || 0;
                    
                    // Make AJAX request
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'mulopimfwc_update_order_item_location',
                            item_id: itemId,
                            order_id: orderId,
                            location_slug: newLocation,
                            nonce: '" . wp_create_nonce('mulopimfwc_update_order_item_location') . "'
                        },
                        success: function(response) {
                            if (response.success) {
                                // Update original value
                                \$select.data('original-value', newLocation);
                                
                                // Check if price changed
                                var priceChanged = response.data && response.data.price_changed;
                                var orderLocationUpdated = response.data && response.data.order_location_updated;
                                var orderLocationSlug = response.data && response.data.order_location_slug ? response.data.order_location_slug : newLocation;
                                
                                if (orderLocationUpdated) {
                                    var \$orderLocationSelect = $('#mulopimfwc_store_location');
                                    if (\$orderLocationSelect.length) {
                                        \$orderLocationSelect.val(orderLocationSlug);
                                        \$orderLocationSelect.data('current-location', orderLocationSlug);
                                        \$orderLocationSelect.attr('data-current-location', orderLocationSlug);
                                        \$orderLocationSelect.trigger('change');
                                    }
                                }
                                
                                if (priceChanged) {
                                    // Price changed - reload page to show updated totals
                                    \$updating.text('" . esc_js(__('Price updated! Reloading...', 'multi-location-product-and-inventory-management')) . "').css('color', '#00a32a');
                                    setTimeout(function() {
                                        window.location.reload();
                                    }, 1000);
                                } else {
                                    // Only location changed - show success message
                                    \$updating.text('" . esc_js(__('Updated!', 'multi-location-product-and-inventory-management')) . "').css('color', '#00a32a');
                                    
                                    // Hide message after 2 seconds
                                    setTimeout(function() {
                                        \$updating.hide().text('" . esc_js(__('Updating...', 'multi-location-product-and-inventory-management')) . "').css('color', '#2271b1');
                                    }, 2000);
                                    
                                    // Trigger WooCommerce order update if needed
                                    if (typeof woocommerce_admin !== 'undefined') {
                                        $(document.body).trigger('wc_order_update');
                                    }
                                }
                            } else {
                                // Revert selection on error
                                \$select.val(originalValue);
                                var errorMessage = response.data && response.data.message ? response.data.message : '" . esc_js(__('Failed to update location', 'multi-location-product-and-inventory-management')) . "';
                                
                                // Show error message
                                \$updating.text(errorMessage).css('color', '#d63638').show();
                                
                                // Hide error message after 5 seconds
                                setTimeout(function() {
                                    \$updating.hide().text('" . esc_js(__('Updating...', 'multi-location-product-and-inventory-management')) . "').css('color', '#2271b1');
                                }, 5000);
                                
                                // Also show alert for important errors
                                alert(errorMessage);
                            }
                        },
                        error: function() {
                            // Revert selection on error
                            \$select.val(originalValue);
                            alert('" . esc_js(__('An error occurred while updating the location', 'multi-location-product-and-inventory-management')) . "');
                        },
                        complete: function() {
                            // Re-enable select
                            \$select.prop('disabled', false);
                            \$updating.hide();
                        }
                    });
                });
            });
            ";

            wp_add_inline_script('jquery', $script);
        }
    }

    function mulopimfwc_location_wise_products_init()
    {
        new mulopimfwc_Location_Wise_Products();

        // Initialize frontend product filter
        if (class_exists('Location_Wise_Products_Filter')) {
            new Location_Wise_Products_Filter();
        }

        // Initialize cache compatibility handler
        if (class_exists('MULOPIMFWC_Cache_Compat')) {
            new MULOPIMFWC_Cache_Compat();
        }
    }

    // FIXED: Changed priority from 100 to 20 to ensure proper initialization timing
    // Priority 20 is early enough to avoid conflicts but late enough for WooCommerce to load
    add_action('plugins_loaded', 'mulopimfwc_location_wise_products_init', 20);

    /**
     * Dashboard cache versioning helpers
     */
    if (!function_exists('mulopimfwc_get_dashboard_cache_version')) {
        function mulopimfwc_get_dashboard_cache_version(): int
        {
            $version = (int) get_option('mulopimfwc_dashboard_cache_version', 1);
            return $version > 0 ? $version : 1;
        }
    }

    if (!function_exists('mulopimfwc_bump_dashboard_cache_version')) {
        function mulopimfwc_bump_dashboard_cache_version(): int
        {
            $version = mulopimfwc_get_dashboard_cache_version() + 1;
            update_option('mulopimfwc_dashboard_cache_version', $version, false);
            return $version;
        }
    }

    if (!function_exists('mulopimfwc_clear_dashboard_cache')) {
        /**
         * Clear dashboard-related caches (transients + cache version bump).
         * Accepts variadic args to stay compatible with WP hooks.
         */
        function mulopimfwc_clear_dashboard_cache(...$args): void
        {
            static $cleared = false;
            if ($cleared) {
                return;
            }
            $cleared = true;

            delete_transient('mulopimfwc_total_investment');
            delete_transient('mulopimfwc_monthly_investment');
            delete_transient('mulopimfwc_dashboard_live_data');

            mulopimfwc_bump_dashboard_cache_version();

            do_action('mulopimfwc_dashboard_cache_cleared', $args);
        }
    }

    /**
     * Internal helpers for cache invalidation triggers
     */
    if (!function_exists('mulopimfwc_is_product_like_post')) {
        function mulopimfwc_is_product_like_post($post_id, $post = null): bool
        {
            if (!empty($post) && isset($post->post_type)) {
                $post_type = $post->post_type;
            } else {
                $post_type = get_post_type($post_id);
            }
            return in_array($post_type, ['product', 'product_variation'], true);
        }
    }

    if (!function_exists('mulopimfwc_dashboard_meta_affects_cache')) {
        function mulopimfwc_dashboard_meta_affects_cache(string $meta_key): bool
        {
            if ($meta_key === '_purchase_price' || $meta_key === '_purchase_quantity') {
                return true;
            }
            if ($meta_key === '_stock' || $meta_key === '_stock_status') {
                return true;
            }
            return (strpos($meta_key, '_location_stock_') === 0);
        }
    }

    if (!function_exists('mulopimfwc_clear_dashboard_cache_on_product_save')) {
        function mulopimfwc_clear_dashboard_cache_on_product_save($post_id, $post = null, $update = null): void
        {
            if (!$post_id || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
                return;
            }
            if (!mulopimfwc_is_product_like_post($post_id, $post)) {
                return;
            }
            mulopimfwc_clear_dashboard_cache('product_save', $post_id);
        }
    }

    if (!function_exists('mulopimfwc_clear_dashboard_cache_on_product_meta')) {
        function mulopimfwc_clear_dashboard_cache_on_product_meta($meta_id, $post_id, $meta_key, $meta_value): void
        {
            if (!$post_id || !mulopimfwc_is_product_like_post($post_id)) {
                return;
            }
            if (!mulopimfwc_dashboard_meta_affects_cache((string) $meta_key)) {
                return;
            }
            mulopimfwc_clear_dashboard_cache('product_meta', $post_id, $meta_key);
        }
    }

    if (!function_exists('mulopimfwc_clear_dashboard_cache_on_order_save')) {
        function mulopimfwc_clear_dashboard_cache_on_order_save($post_id, $post = null, $update = null): void
        {
            if (!$post_id || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
                return;
            }
            $post_type = !empty($post) && isset($post->post_type) ? $post->post_type : get_post_type($post_id);
            if ($post_type !== 'shop_order') {
                return;
            }
            mulopimfwc_clear_dashboard_cache('order_save', $post_id);
        }
    }

    if (!function_exists('mulopimfwc_clear_dashboard_cache_on_order_event')) {
        function mulopimfwc_clear_dashboard_cache_on_order_event($order_id = 0, ...$args): void
        {
            if (!$order_id) {
                return;
            }
            mulopimfwc_clear_dashboard_cache('order_event', $order_id);
        }
    }

    if (!function_exists('mulopimfwc_clear_dashboard_cache_on_post_delete')) {
        function mulopimfwc_clear_dashboard_cache_on_post_delete($post_id): void
        {
            if (!$post_id) {
                return;
            }
            $post_type = get_post_type($post_id);
            if (!in_array($post_type, ['product', 'product_variation', 'shop_order'], true)) {
                return;
            }
            mulopimfwc_clear_dashboard_cache('post_delete', $post_id);
        }
    }

    // Register cache invalidation hooks for products and orders.
    add_action('save_post_product', 'mulopimfwc_clear_dashboard_cache_on_product_save', 10, 3);
    add_action('save_post_product_variation', 'mulopimfwc_clear_dashboard_cache_on_product_save', 10, 3);
    add_action('added_post_meta', 'mulopimfwc_clear_dashboard_cache_on_product_meta', 10, 4);
    add_action('updated_post_meta', 'mulopimfwc_clear_dashboard_cache_on_product_meta', 10, 4);
    add_action('deleted_post_meta', 'mulopimfwc_clear_dashboard_cache_on_product_meta', 10, 4);

    add_action('save_post_shop_order', 'mulopimfwc_clear_dashboard_cache_on_order_save', 10, 3);
    add_action('woocommerce_new_order', 'mulopimfwc_clear_dashboard_cache_on_order_event', 10, 1);
    add_action('woocommerce_update_order', 'mulopimfwc_clear_dashboard_cache_on_order_event', 10, 1);
    add_action('woocommerce_order_status_changed', 'mulopimfwc_clear_dashboard_cache_on_order_event', 10, 4);
    add_action('woocommerce_order_refunded', 'mulopimfwc_clear_dashboard_cache_on_order_event', 10, 2);

    add_action('trashed_post', 'mulopimfwc_clear_dashboard_cache_on_post_delete', 10, 1);
    add_action('deleted_post', 'mulopimfwc_clear_dashboard_cache_on_post_delete', 10, 1);
    add_action('untrashed_post', 'mulopimfwc_clear_dashboard_cache_on_post_delete', 10, 1);


    // Add this to the main plugin file after the class definition

    // AJAX handler for saving user location
    add_action('wp_ajax_mulopimfwc_save_user_location', 'mulopimfwc_save_user_location');
    
    // FIXED: Add AJAX handler to validate location cookies set by JavaScript
    add_action('wp_ajax_mulopimfwc_validate_location', 'mulopimfwc_validate_location');
    add_action('wp_ajax_nopriv_mulopimfwc_validate_location', 'mulopimfwc_validate_location');

    // Location resolver endpoint for saved-address selection.
    add_action('wp_ajax_mulopimfwc_resolve_store_location', 'mulopimfwc_resolve_store_location');
    add_action('wp_ajax_nopriv_mulopimfwc_resolve_store_location', 'mulopimfwc_resolve_store_location');

    if (!function_exists('mulopimfwc_resolver_rate_limit_allowed')) {
        /**
         * Basic resolver endpoint rate limiting by user/ip key.
         *
         * @return bool
         */
        function mulopimfwc_resolver_rate_limit_allowed()
        {
            $window_seconds = (int) apply_filters('mulopimfwc_location_resolver_rate_window', MINUTE_IN_SECONDS * 3);
            $max_requests = (int) apply_filters('mulopimfwc_location_resolver_rate_max_requests', 45);

            $window_seconds = max(30, $window_seconds);
            $max_requests = max(5, $max_requests);

            if (is_user_logged_in()) {
                $identity = 'u_' . get_current_user_id();
            } else {
                $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '0.0.0.0';
                $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
                $identity = 'g_' . md5($ip . '|' . $ua);
            }

            $key = 'mulopimfwc_resolve_rate_' . $identity;
            $bucket = get_transient($key);
            if (!is_array($bucket)) {
                $bucket = [
                    'count' => 0,
                    'started' => time(),
                ];
            }

            $bucket['count'] = isset($bucket['count']) ? (int) $bucket['count'] : 0;
            $bucket['started'] = isset($bucket['started']) ? (int) $bucket['started'] : time();

            if ($bucket['count'] >= $max_requests) {
                return false;
            }

            $bucket['count']++;
            set_transient($key, $bucket, $window_seconds);

            return true;
        }
    }

    if (!function_exists('mulopimfwc_log_location_resolution')) {
        /**
         * Optional resolver decision logging for tuning.
         *
         * @param array $input
         * @param array $result
         * @return void
         */
        function mulopimfwc_log_location_resolution(array $input, array $result)
        {
            do_action('mulopimfwc_location_resolver_decision', $result, $input);

            $enable_debug_log = (bool) apply_filters('mulopimfwc_location_resolver_debug_log', false, $result, $input);
            if (!$enable_debug_log || !defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
                return;
            }

            $payload = [
                'reason' => isset($result['reason']) ? $result['reason'] : 'none',
                'status' => isset($result['status']) ? $result['status'] : 'not_found',
                'confidence' => isset($result['confidence']) ? (float) $result['confidence'] : 0.0,
                'slug' => isset($result['slug']) ? $result['slug'] : null,
                'input' => [
                    'query' => isset($input['query']) ? $input['query'] : '',
                    'city' => isset($input['city']) ? $input['city'] : '',
                    'state' => isset($input['state']) ? $input['state'] : '',
                    'country' => isset($input['country']) ? $input['country'] : '',
                    'lat' => isset($input['lat']) ? $input['lat'] : null,
                    'lng' => isset($input['lng']) ? $input['lng'] : null,
                ],
            ];

            error_log('[MulopimFWC Resolver] ' . wp_json_encode($payload));
        }
    }

    if (!function_exists('mulopimfwc_resolve_store_location')) {
        /**
         * AJAX: Resolve address/coordinates/query into a store slug.
         *
         * @return void
         */
        function mulopimfwc_resolve_store_location()
        {
            check_ajax_referer('mulopimfwc_shortcode_selector', 'nonce');

            if (!class_exists('MULOPIMFWC_Location_Resolver')) {
                wp_send_json_error(['message' => __('Resolver service is unavailable.', 'multi-location-product-and-inventory-management')]);
                return;
            }

            if (!mulopimfwc_resolver_rate_limit_allowed()) {
                wp_send_json_error([
                    'message' => __('Too many location resolve requests. Please try again shortly.', 'multi-location-product-and-inventory-management'),
                    'code' => 'rate_limited',
                ]);
                return;
            }

            $input = [
                'query' => isset($_POST['query']) ? sanitize_text_field(wp_unslash($_POST['query'])) : '',
                'address' => isset($_POST['address']) ? sanitize_text_field(wp_unslash($_POST['address'])) : '',
                'city' => isset($_POST['city']) ? sanitize_text_field(wp_unslash($_POST['city'])) : '',
                'state' => isset($_POST['state']) ? sanitize_text_field(wp_unslash($_POST['state'])) : '',
                'country' => isset($_POST['country']) ? sanitize_text_field(wp_unslash($_POST['country'])) : '',
                'lat' => isset($_POST['lat']) && is_numeric($_POST['lat']) ? (float) wp_unslash($_POST['lat']) : null,
                'lng' => isset($_POST['lng']) && is_numeric($_POST['lng']) ? (float) wp_unslash($_POST['lng']) : null,
            ];

            $resolver = new MULOPIMFWC_Location_Resolver();
            $result = $resolver->resolve_store_location($input);

            mulopimfwc_log_location_resolution($input, $result);

            wp_send_json_success($result);
        }
    }
    
    function mulopimfwc_validate_location() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'mulopimfwc_location_validation')) {
            wp_send_json_error(array('message' => __('Invalid security token.', 'multi-location-product-and-inventory-management')));
            return;
        }
        
        $location_slug = isset($_POST['location_slug']) ? sanitize_text_field(wp_unslash($_POST['location_slug'])) : '';
        
        if (empty($location_slug)) {
            wp_send_json_error(array('message' => __('Location slug is required.', 'multi-location-product-and-inventory-management')));
            return;
        }
        
        // Validate location exists (supports alias fallback through shared validator)
        $term = function_exists('mulopimfwc_validate_location_slug')
            ? mulopimfwc_validate_location_slug($location_slug, false)
            : get_term_by('slug', $location_slug, 'mulopimfwc_store_location');
        if (!$term || is_wp_error($term)) {
            wp_send_json_error(array('message' => __('Invalid location.', 'multi-location-product-and-inventory-management')));
            return;
        }
        
        wp_send_json_success(array('message' => __('Location validated.', 'multi-location-product-and-inventory-management')));
    }
    add_action('wp_ajax_nopriv_mulopimfwc_save_user_location', 'mulopimfwc_save_user_location');

    function mulopimfwc_save_user_location()
    {
        // Check nonce
        check_ajax_referer('mulopimfwc_save_user_location', 'mulopimfwc_save_user_location_nonce');

        // Get form data
        $label = isset($_POST['label']) ? sanitize_text_field(wp_unslash($_POST['label'])) : '';
        $street = isset($_POST['street']) ? sanitize_text_field(wp_unslash($_POST['street'])) : '';
        $apartment = isset($_POST['apartment']) ? sanitize_text_field(wp_unslash($_POST['apartment'])) : '';
        $city = isset($_POST['city']) ? sanitize_text_field(wp_unslash($_POST['city'])) : '';
        $state = isset($_POST['state']) ? sanitize_text_field(wp_unslash($_POST['state'])) : '';
        $postal = isset($_POST['postal']) ? sanitize_text_field(wp_unslash($_POST['postal'])) : '';
        $country = isset($_POST['country']) ? sanitize_text_field(wp_unslash($_POST['country'])) : '';
        $note = isset($_POST['note']) ? sanitize_textarea_field(wp_unslash($_POST['note'])) : '';
        $lat = isset($_POST['lat']) ? floatval(wp_unslash($_POST['lat'])) : 0;
        $lng = isset($_POST['lng']) ? floatval(wp_unslash($_POST['lng'])) : 0;

        // Check if we're editing an existing location
        $location_id = isset($_POST['location_id']) && !empty($_POST['location_id']) ? sanitize_text_field(wp_unslash($_POST['location_id'])) : uniqid();

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
                'address' => $location_data['address'],
                'location' => $location_data,
            ));
        } else {
            // For non-logged-in users, we can't save the location permanently.
            wc_setcookie('mulopimfwc_user_location', $location_data['address'], time() + mulopimfwc_get_location_cookie_expiry_seconds());
            wp_send_json_success(array(
                'logged_in' => false,
                'location_id' => $location_id,
                'label' => $label,
                'address' => $location_data['address'],
                'location' => $location_data,
            ));
        }
    }

    // AJAX handler for updating customer checkout address
    add_action('wp_ajax_mulopimfwc_update_customer_address', 'mulopimfwc_update_customer_address');
    add_action('wp_ajax_nopriv_mulopimfwc_update_customer_address', 'mulopimfwc_update_customer_address');

    function mulopimfwc_update_customer_address()
    {
        check_ajax_referer('mulopimfwc_update_customer_address', 'nonce');

        $options = get_option('mulopimfwc_display_options', []);
        if (!isset($options['auto_populate_customer_addresses']) || $options['auto_populate_customer_addresses'] !== 'on') {
            wp_send_json_error(['message' => __('Auto-populate addresses is disabled.', 'multi-location-product-and-inventory-management')]);
            return;
        }

        if (!function_exists('WC')) {
            wp_send_json_error(['message' => __('WooCommerce is not available.', 'multi-location-product-and-inventory-management')]);
            return;
        }

        if (!WC()->customer && function_exists('wc_load_cart')) {
            wc_load_cart();
        }

        if (!WC()->customer) {
            wp_send_json_error(['message' => __('Customer session unavailable.', 'multi-location-product-and-inventory-management')]);
            return;
        }

        $address = [
            'address_1' => isset($_POST['street']) ? sanitize_text_field(wp_unslash($_POST['street'])) : '',
            'address_2' => isset($_POST['address_2']) ? sanitize_text_field(wp_unslash($_POST['address_2'])) : '',
            'city' => isset($_POST['city']) ? sanitize_text_field(wp_unslash($_POST['city'])) : '',
            'state' => isset($_POST['state']) ? sanitize_text_field(wp_unslash($_POST['state'])) : '',
            'postcode' => isset($_POST['postcode']) ? sanitize_text_field(wp_unslash($_POST['postcode'])) : '',
            'country' => isset($_POST['country']) ? sanitize_text_field(wp_unslash($_POST['country'])) : '',
        ];

        $has_data = false;
        foreach ($address as $value) {
            if (!empty($value)) {
                $has_data = true;
                break;
            }
        }
        if (!$has_data) {
            wp_send_json_error(['message' => __('No address data provided.', 'multi-location-product-and-inventory-management')]);
            return;
        }

        // Normalize country/state to WooCommerce codes when possible.
        if (WC()->countries) {
            $countries = WC()->countries->get_countries();
            $country_input = $address['country'];
            if (!empty($country_input)) {
                $country_upper = strtoupper($country_input);
                if (isset($countries[$country_upper])) {
                    $address['country'] = $country_upper;
                } else {
                    foreach ($countries as $code => $name) {
                        if (strcasecmp($name, $country_input) === 0) {
                            $address['country'] = $code;
                            break;
                        }
                    }
                }
            }

            if (!empty($address['country'])) {
                $states = WC()->countries->get_states($address['country']);
                if (!empty($states) && !empty($address['state'])) {
                    $state_input = $address['state'];
                    $state_upper = strtoupper($state_input);
                    if (isset($states[$state_upper])) {
                        $address['state'] = $state_upper;
                    } else {
                        foreach ($states as $code => $name) {
                            if (strcasecmp($name, $state_input) === 0) {
                                $address['state'] = $code;
                                break;
                            }
                        }
                    }
                }
            }
        }

        $use_shipping = false;
        if (function_exists('wc_shipping_enabled') && wc_shipping_enabled()) {
            $use_shipping = true;
            if (WC()->cart) {
                $use_shipping = WC()->cart->needs_shipping_address();
            }
        }

        $customer = WC()->customer;
        if ($use_shipping) {
            $customer->set_shipping_address_1($address['address_1']);
            $customer->set_shipping_address_2($address['address_2']);
            $customer->set_shipping_city($address['city']);
            $customer->set_shipping_state($address['state']);
            $customer->set_shipping_postcode($address['postcode']);
            $customer->set_shipping_country($address['country']);
        } else {
            $customer->set_billing_address_1($address['address_1']);
            $customer->set_billing_address_2($address['address_2']);
            $customer->set_billing_city($address['city']);
            $customer->set_billing_state($address['state']);
            $customer->set_billing_postcode($address['postcode']);
            $customer->set_billing_country($address['country']);
        }

        $customer->save();

        wp_send_json_success([
            'address' => $address,
            'updated' => $use_shipping ? 'shipping' : 'billing',
        ]);
    }



    // AJAX handler for deleting user location
    add_action('wp_ajax_mulopimfwc_delete_user_location', 'mulopimfwc_delete_user_location');

    function mulopimfwc_delete_user_location()
    {
        // FIXED: Add nonce verification (Issue #33)
        check_ajax_referer('mulopimfwc_shortcode_selector', 'nonce');

        // FIXED: Add capability check (Issue #40)
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in to delete locations.', 'multi-location-product-and-inventory-management')));
            return;
        }

        // Get location ID
        $location_id = isset($_POST['location_id']) ? sanitize_text_field(wp_unslash($_POST['location_id'])) : '';

        if (empty($location_id)) {
            wp_send_json_error(array('message' => __('Invalid location ID', 'multi-location-product-and-inventory-management')));
            return;
        }

        // FIXED: Validate location ID format (Issue #37)
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $location_id)) {
            wp_send_json_error(array('message' => __('Invalid location ID format', 'multi-location-product-and-inventory-management')));
            return;
        }

        $user_id = get_current_user_id();
        $user_locations = get_user_meta($user_id, 'mulopimfwc_user_locations', true);

        if (!is_array($user_locations)) {
            wp_send_json_error(array('message' => __('No saved locations found', 'multi-location-product-and-inventory-management')));
            return;
        }

        // FIXED: Validate location exists and belongs to current user before attempting deletion (Issue #37)
        $found = false;
        foreach ($user_locations as $key => $location) {
            // FIXED: Validate location belongs to current user
            if (isset($location['id']) && $location['id'] === $location_id) {
                unset($user_locations[$key]);
                $found = true;
                break;
            }
        }

        if (!$found) {
            wp_send_json_error(array('message' => __('Location not found', 'multi-location-product-and-inventory-management')));
            return;
        }

        // Re-index array
        $user_locations = array_values($user_locations);

        // Update user meta
        update_user_meta($user_id, 'mulopimfwc_user_locations', $user_locations);

        wp_send_json_success(array('message' => __('Location deleted successfully', 'multi-location-product-and-inventory-management')));
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
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
            if ($type === 'out') {
                $value = isset($options['out_of_stock_threshold']) ? (int) $options['out_of_stock_threshold'] : 0;
            } else {
                $value = isset($options['low_stock_threshold']) ? (int) $options['low_stock_threshold'] : 5;
            }
        }

        return max(0, (int) $value);
    }

    /**
     * Get global stock threshold with fallback defaults.
     */
    function mulopimfwc_get_global_stock_threshold($type = 'low')
    {
        global $mulopimfwc_options;
        $options = is_array($mulopimfwc_options ?? null)
            ? $mulopimfwc_options
            : get_option('mulopimfwc_display_options', []);

        if ($type === 'out') {
            $value = isset($options['out_of_stock_threshold']) ? (int) $options['out_of_stock_threshold'] : 0;
        } else {
            $value = isset($options['low_stock_threshold']) ? (int) $options['low_stock_threshold'] : 5;
        }

        return max(0, (int) $value);
    }

    /**
     * Get stock display format from options with validation.
     */
    function mulopimfwc_get_stock_display_format($options = null)
    {
        if ($options === null) {
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
        }

        $format = isset($options['stock_display_format']) ? sanitize_text_field($options['stock_display_format']) : 'exact_count';
        $valid = ['exact_count', 'availability_only', 'stock_levels', 'hide_stock'];

        return in_array($format, $valid, true) ? $format : 'exact_count';
    }

    /**
     * Build stock display label and status based on format.
     *
     * @param array $args
     * @return array{show:bool,label:string,status:string,level:string,class:string,quantity:int|null}
     */
    function mulopimfwc_build_stock_display_label($args = [])
    {
        $defaults = [
            'stock_qty' => null,
            'stock_status' => '',
            'backorders' => 'off',
            'format' => null,
            'location_id' => 0,
            'count_format' => 'paren', // paren|phrase|short
            'backorder_label' => 'backorder', // backorder|available
        ];
        $args = array_merge($defaults, is_array($args) ? $args : []);

        $format = $args['format'] ? sanitize_text_field($args['format']) : mulopimfwc_get_stock_display_format();
        if ($format === 'hide_stock') {
            return [
                'show' => false,
                'label' => '',
                'status' => 'hidden',
                'level' => '',
                'class' => 'hidden',
                'quantity' => null,
            ];
        }

        $stock_qty = ($args['stock_qty'] === '' || $args['stock_qty'] === null) ? null : (int) $args['stock_qty'];
        $raw_backorders = is_string($args['backorders']) ? $args['backorders'] : '';
        $backorders = $raw_backorders !== '' ? strtolower(trim($raw_backorders)) : 'off';
        $backorders_allowed = function_exists('mulopimfwc_is_backorder_allowed')
            ? mulopimfwc_is_backorder_allowed($backorders)
            : in_array($backorders, ['on', 'yes', 'notify'], true);

        $status = '';
        if ($stock_qty !== null) {
            if ($stock_qty > 0) {
                $status = 'instock';
            } else {
                $status = $backorders_allowed ? 'onbackorder' : 'outofstock';
            }
        } else {
            $status = sanitize_text_field($args['stock_status']);
            if ($status === 'onbackorder' || $status === 'backorder') {
                $status = $backorders_allowed ? 'onbackorder' : 'outofstock';
            } elseif ($status === 'outofstock') {
                $status = 'outofstock';
            } else {
                $status = 'instock';
            }
        }

        $label = '';
        $level = '';
        $class = $status;

        $backorder_label = ($args['backorder_label'] === 'available')
            ? mulopimfwc_get_text_value('text_variation_backorder')
            : __('Backorder', 'multi-location-product-and-inventory-management');

        if ($format === 'availability_only' || $stock_qty === null) {
            if ($status === 'instock') {
                $label = __('In stock', 'multi-location-product-and-inventory-management');
            } elseif ($status === 'onbackorder') {
                $label = $backorder_label;
            } else {
                $label = mulopimfwc_get_text_value('text_alert_out_of_stock_badge') ? mulopimfwc_get_text_value('text_alert_out_of_stock_badge')
                    : __('Out of stock', 'multi-location-product-and-inventory-management');
            }
        } elseif ($format === 'stock_levels') {
            if ($status === 'onbackorder') {
                $label = $backorder_label;
            } elseif ($status === 'outofstock') {
                $label = mulopimfwc_get_text_value('text_alert_out_of_stock_badge') ? mulopimfwc_get_text_value('text_alert_out_of_stock_badge')
                    : __('Out of stock', 'multi-location-product-and-inventory-management');
            } else {
                $location_id = (int) $args['location_id'];
                if ($location_id > 0 && function_exists('mulopimfwc_get_location_threshold')) {
                    $low_threshold = mulopimfwc_get_location_threshold($location_id, 'low');
                    $out_threshold = mulopimfwc_get_location_threshold($location_id, 'out');
                } else {
                    $low_threshold = mulopimfwc_get_global_stock_threshold('low');
                    $out_threshold = mulopimfwc_get_global_stock_threshold('out');
                }

                if ($stock_qty <= $out_threshold) {
                    $label = mulopimfwc_get_text_value('text_alert_out_of_stock_badge') ? mulopimfwc_get_text_value('text_alert_out_of_stock_badge')
                        : __('Out of stock', 'multi-location-product-and-inventory-management');
                    $status = 'outofstock';
                } elseif ($stock_qty <= $low_threshold) {
                    $label = __('Low stock', 'multi-location-product-and-inventory-management');
                    $level = 'low';
                    $class = 'low';
                } else {
                    $default_medium = max($low_threshold * 2, $low_threshold + 1);
                    $medium_threshold = (int) apply_filters('mulopimfwc_stock_medium_threshold', $default_medium, $low_threshold, $location_id, $stock_qty);
                    if ($stock_qty <= $medium_threshold) {
                        $label = __('Medium stock', 'multi-location-product-and-inventory-management');
                        $level = 'medium';
                        $class = 'medium';
                    } else {
                        $label = __('High stock', 'multi-location-product-and-inventory-management');
                        $level = 'high';
                        $class = 'high';
                    }
                }
            }
        } else {
            if ($status === 'instock') {
                if ($args['count_format'] === 'phrase') {
                    $label = sprintf(_n(mulopimfwc_get_text_value('text_variation_in_stock'), mulopimfwc_get_text_value('text_variation_in_stock'), $stock_qty, 'multi-location-product-and-inventory-management'), $stock_qty);
                } elseif ($args['count_format'] === 'short') {
                    $label = sprintf(mulopimfwc_get_text_value('text_variation_in_stock'), $stock_qty);
                } else {
                    $label = sprintf(mulopimfwc_get_text_value('text_variation_in_stock'), $stock_qty);
                }
            } elseif ($status === 'onbackorder') {
                $label = $backorder_label;
            } else {
                $label = mulopimfwc_get_text_value('text_alert_out_of_stock_badge') ? mulopimfwc_get_text_value('text_alert_out_of_stock_badge')
                    : __('Out of stock', 'multi-location-product-and-inventory-management');
            }
        }

        $label = apply_filters(
            'mulopimfwc_stock_display_label',
            $label,
            [
                'status' => $status,
                'level' => $level,
                'format' => $format,
                'quantity' => $stock_qty,
                'location_id' => (int) $args['location_id'],
            ]
        );

        return [
            'show' => true,
            'label' => $label,
            'status' => $status,
            'level' => $level,
            'class' => $class,
            'quantity' => $stock_qty,
        ];
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
     * Get order statuses that should count toward revenue/sales metrics.
     */
    function mulopimfwc_get_revenue_order_statuses()
    {
        // Revenue is paid-only and must match WooCommerce paid semantics used by this plugin.
        $default_statuses = ['processing', 'completed'];

        $statuses = apply_filters('mulopimfwc_revenue_order_statuses', $default_statuses);
        if (!is_array($statuses)) {
            $statuses = $default_statuses;
        }

        $statuses = array_map(function ($status) {
            $status = (string) $status;
            if (strpos($status, 'wc-') === 0) {
                $status = substr($status, 3);
            }
            return $status;
        }, $statuses);

        $statuses = array_values(array_unique(array_filter($statuses, 'strlen')));
        return $statuses;
    }

    /**
     * Convert an order amount into store base currency.
     *
     * Uses order location currency configuration (target currency + rate) when available.
     *
     * @param mixed    $amount
     * @param WC_Order $order
     * @return float
     */
    function mulopimfwc_convert_order_amount_to_base_currency($amount, $order)
    {
        $normalized_amount = null;
        if (function_exists('mulopimfwc_normalize_price_amount')) {
            $normalized_amount = mulopimfwc_normalize_price_amount($amount);
        } elseif (is_numeric($amount)) {
            $normalized_amount = (float) $amount;
        } elseif (function_exists('wc_format_decimal')) {
            $formatted = wc_format_decimal($amount, 6, false);
            if ($formatted !== '' && is_numeric($formatted)) {
                $normalized_amount = (float) $formatted;
            }
        }

        if ($normalized_amount === null) {
            return 0.0;
        }

        if (!$order instanceof WC_Order) {
            return (float) $normalized_amount;
        }

        $base_currency = function_exists('mulopimfwc_get_store_base_currency_code_raw')
            ? strtoupper(trim((string) mulopimfwc_get_store_base_currency_code_raw()))
            : strtoupper(trim((string) get_option('woocommerce_currency', 'USD')));
        if ($base_currency === '') {
            $base_currency = 'USD';
        }

        $order_currency = strtoupper(trim((string) $order->get_currency()));
        if ($order_currency === '' || $order_currency === $base_currency) {
            return (float) $normalized_amount;
        }

        $location_slug = sanitize_title(rawurldecode((string) $order->get_meta('_store_location')));
        if ($location_slug === '') {
            foreach ($order->get_items('line_item') as $item) {
                if (!$item instanceof WC_Order_Item_Product) {
                    continue;
                }
                $item_location = sanitize_title(rawurldecode((string) $item->get_meta('_mulopimfwc_location')));
                if ($item_location !== '') {
                    $location_slug = $item_location;
                    break;
                }
            }
        }

        if ($location_slug === '') {
            return (float) $normalized_amount;
        }

        $location_term = function_exists('mulopimfwc_validate_location_slug')
            ? mulopimfwc_validate_location_slug($location_slug, false)
            : get_term_by('slug', $location_slug, 'mulopimfwc_store_location');

        if (!$location_term || is_wp_error($location_term)) {
            return (float) $normalized_amount;
        }

        $location_term_id = (int) $location_term->term_id;
        if ($location_term_id <= 0) {
            return (float) $normalized_amount;
        }

        $target_currency = '';
        if (function_exists('mulopimfwc_get_currency_settings_for_location')) {
            $currency_settings = (array) mulopimfwc_get_currency_settings_for_location($location_term_id);
            $target_currency = strtoupper(trim((string) ($currency_settings['currency'] ?? '')));
        }
        if ($target_currency === '') {
            $target_currency = strtoupper(trim((string) get_term_meta($location_term_id, 'location_currency', true)));
        }

        $rate_raw = get_term_meta($location_term_id, 'location_currency_rate', true);
        $rate = (is_numeric($rate_raw) && (float) $rate_raw > 0) ? (float) $rate_raw : 0.0;

        if ($target_currency === '' || $rate <= 0 || $target_currency !== $order_currency || $target_currency === $base_currency) {
            return (float) $normalized_amount;
        }

        $converted_amount = $normalized_amount / $rate;
        if (function_exists('wc_format_decimal')) {
            $converted_amount = (float) wc_format_decimal($converted_amount, 6, false);
        }

        return (float) apply_filters(
            'mulopimfwc_order_amount_base_currency',
            $converted_amount,
            $amount,
            $order,
            [
                'base_currency' => $base_currency,
                'order_currency' => $order_currency,
                'target_currency' => $target_currency,
                'rate' => $rate,
                'location_slug' => $location_slug,
            ]
        );
    }

    /**
     * Calculate order revenue based on purchase and selling prices.
     *
     * Revenue = sum of (sell price - purchase price) * quantity for each line item.
     * Uses line-item subtotal (pre-discount) as the sell price, falling back to line total
     * or product pricing when needed.
     *
     * @param WC_Order $order
     * @return float
     */
    function mulopimfwc_calculate_order_revenue($order)
    {
        if (!$order instanceof WC_Order) {
            return 0.0;
        }

        $revenue = 0.0;

        foreach ($order->get_items('line_item') as $item) {
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }

            $quantity = (float) $item->get_quantity();
            if ($quantity <= 0) {
                continue;
            }

            $product = $item->get_product();
            $purchase_price_raw = '';

            if ($product) {
                $purchase_price_raw = get_post_meta($product->get_id(), '_purchase_price', true);

                // Fallback to parent purchase price if variation value is missing.
                if (($purchase_price_raw === '' || $purchase_price_raw === null) && $product->is_type('variation')) {
                    $parent_id = $product->get_parent_id();
                    if ($parent_id) {
                        $purchase_price_raw = get_post_meta($parent_id, '_purchase_price', true);
                    }
                }
            } else {
                $product_id = $item->get_variation_id() ?: $item->get_product_id();
                if ($product_id) {
                    $purchase_price_raw = get_post_meta($product_id, '_purchase_price', true);
                    if (($purchase_price_raw === '' || $purchase_price_raw === null) && $item->get_variation_id()) {
                        $parent_id = wp_get_post_parent_id($product_id);
                        if ($parent_id) {
                            $purchase_price_raw = get_post_meta($parent_id, '_purchase_price', true);
                        }
                    }
                }
            }

            $purchase_price = 0.0;
            if ($purchase_price_raw !== '' && $purchase_price_raw !== null) {
                $purchase_price = is_numeric($purchase_price_raw)
                    ? (float) $purchase_price_raw
                    : (function_exists('wc_format_decimal') ? (float) wc_format_decimal($purchase_price_raw) : 0.0);
            }
            if ($purchase_price <= 0) {
                // Cannot compute revenue without purchase price.
                continue;
            }

            // Use line subtotal (pre-discount) as the sell price; fall back as needed.
            $sell_total = $item->get_subtotal();
            if ($sell_total === '' || $sell_total === null) {
                $sell_total = $item->get_total();
            }

            $sell_total = is_numeric($sell_total) ? (float) $sell_total : 0.0;

            if ($sell_total <= 0 && $product) {
                $sell_price_raw = $product->get_sale_price();
                if ($sell_price_raw === '' || $sell_price_raw === null) {
                    $sell_price_raw = $product->get_regular_price();
                }
                $sell_price = is_numeric($sell_price_raw)
                    ? (float) $sell_price_raw
                    : (function_exists('wc_format_decimal') ? (float) wc_format_decimal($sell_price_raw) : 0.0);
                $sell_total = $sell_price * $quantity;
            }
            if ($sell_total <= 0) {
                continue;
            }

            $sell_total_base = function_exists('mulopimfwc_convert_order_amount_to_base_currency')
                ? (float) mulopimfwc_convert_order_amount_to_base_currency($sell_total, $order)
                : $sell_total;
            $line_revenue = $sell_total_base - ($purchase_price * $quantity);

            $line_revenue = apply_filters('mulopimfwc_line_revenue', $line_revenue, $item, $order, [
                'quantity' => $quantity,
                'sell_total' => $sell_total_base,
                'purchase_total' => $purchase_price * $quantity,
                'purchase_price' => $purchase_price,
            ]);

            $revenue += $line_revenue;
        }

        return (float) apply_filters('mulopimfwc_order_revenue', $revenue, $order);
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
    function mulopimfwc_collect_social_channels($location_slug, $settings, $include_all_orders = false)
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

        if ($include_all_orders && class_exists('MULOPIMFWC_Location_Managers')) {
            $all_managers = get_users([
                'role' => 'mulopimfwc_location_manager',
                'fields' => 'all',
            ]);

            if (!empty($all_managers)) {
                foreach ($all_managers as $manager) {
                    if (!MULOPIMFWC_Location_Managers::user_has_capability('all_orders', $manager->ID)) {
                        continue;
                    }

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

        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }

        $check_id = $product->is_type('variation') ? $product->get_parent_id() : $product_id;
        if ($check_id && !has_term($location_term->term_id, 'mulopimfwc_store_location', $check_id)) {
            return;
        }

        global $mulopimfwc_options;
        $options = is_array($mulopimfwc_options ?? null)
            ? $mulopimfwc_options
            : get_option('mulopimfwc_display_options', ['low_stock_notification_recipients' => 'admin']);
        $mode = isset($options['low_stock_notification_recipients']) ? $options['low_stock_notification_recipients'] : 'admin';

        $include_admin = ($mode === 'admin' || $mode === 'both');
        $include_manager = ($mode === 'location_manager' || $mode === 'both');

        $managers = $include_manager ? mulopimfwc_get_location_managers_for_slug($location_term->slug) : [];

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

        // Admin email
        if ($include_admin) {
            $admin_email = sanitize_email(get_option('admin_email'));
            if (!empty($admin_email) && is_email($admin_email)) {
                wp_mail($admin_email, $subject, $message);
            }
        }

        // Assigned location managers
        if ($include_manager && !empty($managers)) {
            foreach ($managers as $manager) {
                if (!empty($manager->user_email) && is_email($manager->user_email)) {
                    wp_mail(sanitize_email($manager->user_email), $subject, $message);
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

        $channels = mulopimfwc_collect_social_channels($location_slug, $settings, true);
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
        if (is_array($time_string)) {
            $time_string = reset($time_string);
        }
        if (!is_string($time_string)) {
            $time_string = '07:00';
        }
        $time_string = trim($time_string);
        if (!preg_match('/^\d{1,2}:\d{2}$/', $time_string)) {
            $time_string = '07:00';
        }

        $now = current_time('timestamp');
        $parts = explode(':', $time_string);
        $hour = isset($parts[0]) ? max(0, min(23, (int) $parts[0])) : 7;
        $minute = isset($parts[1]) ? max(0, min(59, (int) $parts[1])) : 0;

        $next = mktime($hour, $minute, 0, (int) wp_date('n', $now), (int) wp_date('j', $now), (int) wp_date('Y', $now));
        if ($next <= $now) {
            $next += DAY_IN_SECONDS;
        }

        return $next;
    }

    /**
     * Build daily digest stats for today's orders.
     */
    function mulopimfwc_get_daily_social_digest_stats()
    {
        $revenue_statuses = mulopimfwc_get_revenue_order_statuses();
        $now = current_time('timestamp');
        $start = strtotime('today', $now);
        $end = strtotime('tomorrow', $now);

        // Process orders in batches to prevent memory exhaustion.
        // For daily digest, we only need orders from today.
        $batch_size = 1000;
        $page = 1;
        $all_orders = [];

        do {
            $date_range = gmdate('Y-m-d H:i:s', $start) . '...' . gmdate('Y-m-d H:i:s', max($start, $end - 1));
            $orders = wc_get_orders([
                'limit' => $batch_size,
                'offset' => ($page - 1) * $batch_size,
                'status' => $revenue_statuses,
                // Use HPOS-safe range syntax; array date queries can trigger type errors in some WC versions.
                'date_created' => $date_range,
                'meta_query' => [
                    [
                        'key' => '_store_location',
                        'compare' => 'EXISTS',
                    ],
                ],
            ]);

            if (empty($orders)) {
                break;
            }

            $all_orders = array_merge($all_orders, $orders);

            // Safety check: limit to 10 batches (10,000 orders max per day).
            if ($page >= 10) {
                break;
            }

            $page++;
        } while (count($orders) === $batch_size);

        $stats = [];
        $totals = [
            'orders' => 0,
            'items' => 0,
            'revenue' => 0.0,
        ];

        foreach ($all_orders as $order) {
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
                    'revenue' => 0.0,
                ];
            }

            $order_revenue = function_exists('mulopimfwc_calculate_order_revenue')
                ? (float) mulopimfwc_calculate_order_revenue($order)
                : (float) $order->get_total();

            $stats[$slug]['orders']++;
            $stats[$slug]['items'] += (int) $order->get_item_count();
            $stats[$slug]['revenue'] += $order_revenue;

            $totals['orders']++;
            $totals['items'] += (int) $order->get_item_count();
            $totals['revenue'] += $order_revenue;
        }

        return [
            'stats' => $stats,
            'totals' => $totals,
        ];
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

        $digest = mulopimfwc_get_daily_social_digest_stats();
        $stats = isset($digest['stats']) && is_array($digest['stats']) ? $digest['stats'] : [];
        if (empty($stats)) {
            return;
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
    add_action('wp_ajax_mulopimfwc_test_social_digest_channel', 'mulopimfwc_test_social_digest_channel');
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
     * AJAX: Send a digest preview to one social channel.
     */
    function mulopimfwc_test_social_digest_channel()
    {
        check_ajax_referer('mulopimfwc_test_social_digest_channel', 'nonce');

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

        $digest = mulopimfwc_get_daily_social_digest_stats();
        $stats = isset($digest['stats']) && is_array($digest['stats']) ? $digest['stats'] : [];
        $totals = isset($digest['totals']) && is_array($digest['totals']) ? $digest['totals'] : ['orders' => 0, 'items' => 0, 'revenue' => 0.0];
        $date_format = get_option('date_format');
        if (empty($date_format)) {
            $date_format = 'Y-m-d';
        }
        $date_label = wp_date($date_format, current_time('timestamp'));
        $title = sprintf(__('Digest preview - %s', 'multi-location-product-and-inventory-management'), $date_label);

        if (empty($stats)) {
            $message = __('No orders found for today yet. This is a digest preview test.', 'multi-location-product-and-inventory-management');
        } else {
            $lines = [];
            $lines[] = sprintf(
                __('Totals - Orders: %d | Items: %d | Revenue: %s', 'multi-location-product-and-inventory-management'),
                intval($totals['orders']),
                intval($totals['items']),
                wp_strip_all_tags(wc_price((float) $totals['revenue']))
            );

            foreach ($stats as $data) {
                $lines[] = sprintf(
                    '%s - %d %s, %d %s, %s',
                    isset($data['name']) ? $data['name'] : __('Location', 'multi-location-product-and-inventory-management'),
                    intval(isset($data['orders']) ? $data['orders'] : 0),
                    __('orders', 'multi-location-product-and-inventory-management'),
                    intval(isset($data['items']) ? $data['items'] : 0),
                    __('items', 'multi-location-product-and-inventory-management'),
                    wp_strip_all_tags(wc_price((float) (isset($data['revenue']) ? $data['revenue'] : 0)))
                );
            }

            $message = implode("\n", $lines);
        }

        mulopimfwc_send_social_message(
            $title,
            $message,
            [$channel],
            $settings,
            admin_url('edit.php?post_type=shop_order')
        );

        wp_send_json_success(['message' => __('Digest test sent (check your channel).', 'multi-location-product-and-inventory-management')]);
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
                "1.1.4.11",
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

    global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
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

    // Get site favicon URL (WordPress site icon)
    $icon_url = get_site_icon_url(192);
    if (!$icon_url) {
        $icon_url = get_site_icon_url(); // Try default size
    }
    if (!$icon_url) {
        $icon_url = home_url('/favicon.ico'); // Fallback to default favicon
    }
    
    wp_localize_script('mulopimfwc-admin-notifications', 'mulopimfwcNotificationConfig', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'adminurl' => admin_url(),
        'nonce' => wp_create_nonce('mulopimfwc_dashboard_realtime_nonce'),
        'test_nonce' => wp_create_nonce('mulopimfwc_notifications_test_nonce'),
        'realtime_enabled' => $notification_settings['realtime_enabled'],
        'floating_enabled' => $notification_settings['floating_enabled'],
        'floating_position' => $notification_settings['floating_position'],
        'floating_size' => isset($notification_settings['floating_size']) ? $notification_settings['floating_size'] : 'comfy',
        'floating_duration' => $notification_settings['floating_duration'],
        'notification_template' => $notification_settings['notification_template'],
        'pwa_enabled' => $notification_settings['pwa_enabled'],
        'show_admin_notice' => $notification_settings['show_admin_notice'],
        'pwa_sw_url' => home_url('/mulopimfwc-sw.js'),
        'pwa_sw_url_rest' => rest_url('mulopimfwc/v1/sw.js'),
        'manifest_url' => home_url('/mulopimfwc-manifest.json'),
        'pwa_icon' => $icon_url,
        'pwa_badge' => $icon_url,
        'poll_interval' => isset($notification_settings['poll_interval']) ? $notification_settings['poll_interval'] : '30000',
        'notification_style' => isset($notification_settings['notification_style']) ? $notification_settings['notification_style'] : 'modern',
        'sound_enabled' => isset($notification_settings['sound_enabled']) ? $notification_settings['sound_enabled'] : 'off',
    ]);
}

/**
 * Generate dynamic manifest with correct paths
 */
add_action('init', 'mulopimfwc_register_manifest_endpoint');
function mulopimfwc_register_manifest_endpoint()
{
    add_rewrite_rule(
        '^mulopimfwc-manifest\.json$',
        'index.php?mulopimfwc_manifest=1',
        'top'
    );
}

add_filter('query_vars', 'mulopimfwc_add_manifest_query_var');
function mulopimfwc_add_manifest_query_var($vars)
{
    $vars[] = 'mulopimfwc_manifest';
    return $vars;
}

add_action('template_redirect', 'mulopimfwc_serve_manifest');
function mulopimfwc_serve_manifest()
{
    if (!get_query_var('mulopimfwc_manifest')) {
        return;
    }

    $home_url = home_url('/');
    
    // Get site favicon URL (WordPress site icon)
    $site_icon_url = get_site_icon_url(512);
    if (!$site_icon_url) {
        $site_icon_url = get_site_icon_url(192);
    }
    if (!$site_icon_url) {
        $site_icon_url = get_site_icon_url();
    }
    
    $manifest = [
        'name' => 'Multi Location Product & Inventory Management',
        'short_name' => 'Location Manager',
        'description' => 'Manage products and inventory across multiple locations',
        'start_url' => admin_url('admin.php?page=multi-location-product-and-inventory-management'),
        'scope' => $home_url,
        'display' => 'standalone',
        'background_color' => '#ffffff',
        'theme_color' => '#2563eb',
        'orientation' => 'portrait-primary',
        'icons' => []
    ];

    // Use site favicon for PWA icons
    if ($site_icon_url) {
        // Add site icon - browser will use appropriate size
        $manifest['icons'][] = [
            'src' => $site_icon_url,
            'sizes' => '192x192 512x512',
            'type' => 'image/png',
            'purpose' => 'any'
        ];
    } else {
        // Fallback to default favicon if site icon not set
        $manifest['icons'][] = [
            'src' => $home_url . 'favicon.ico',
            'sizes' => '192x192',
            'type' => 'image/x-icon',
            'purpose' => 'any'
        ];
    }

    header('Content-Type: application/json');
    header('Service-Worker-Allowed: /');
    echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// Add favicon and manifest link for PWA
add_action('admin_head', 'mulopimfwc_add_favicon');
add_action('wp_head', 'mulopimfwc_add_favicon');
function mulopimfwc_add_favicon()
{
    global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
    $notification_settings = isset($options['notification_settings']) && is_array($options['notification_settings'])
        ? $options['notification_settings']
        : [];
    
    // Only add manifest if PWA is enabled (don't override site favicon)
    if (isset($notification_settings['pwa_enabled']) && $notification_settings['pwa_enabled'] === 'on') {
        $manifest_url = home_url('/mulopimfwc-manifest.json');
        
        echo '<link rel="manifest" href="' . esc_url($manifest_url) . '">' . "\n";
        echo '<meta name="theme-color" content="#2563eb">' . "\n";
        echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
        echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
        echo '<meta name="apple-mobile-web-app-status-bar-style" content="default">' . "\n";
        echo '<meta name="apple-mobile-web-app-title" content="Location Manager">' . "\n";
    }
}

// FIXED: Combine activation hooks into single function to prevent conflicts
function mulopimfwc_activation()
{
    mulopimfwc_register_manifest_endpoint();
    mulopimfwc_register_sw_endpoint();
    flush_rewrite_rules();
}

register_activation_hook(__FILE__, 'mulopimfwc_activation');


/**
 * Send test notification (local only via service worker)
 */
add_action('wp_ajax_mulopimfwc_test_push_notification', 'mulopimfwc_test_push_notification');
function mulopimfwc_test_push_notification()
{
    check_ajax_referer('mulopimfwc_dashboard_realtime_nonce', 'nonce');

    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(['message' => 'Unauthorized']);
        return;
    }

    wp_send_json_success([
        'message' => 'Test notification will be shown locally via service worker.',
        'local_notification' => true
    ]);
}

/**
 * Fetch location managers for a location slug (used by Test Notifications UI).
 */
add_action('wp_ajax_mulopimfwc_get_location_managers', 'mulopimfwc_get_location_managers_ajax');
function mulopimfwc_get_location_managers_ajax()
{
    check_ajax_referer('mulopimfwc_notifications_test_nonce', 'nonce');

    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(['message' => 'Unauthorized']);
        return;
    }

    $location_slug = isset($_POST['location_slug']) ? sanitize_text_field(wp_unslash($_POST['location_slug'])) : '';
    if (empty($location_slug)) {
        wp_send_json_success(['managers' => []]);
        return;
    }

    if (function_exists('mulopimfwc_get_location_managers_for_slug')) {
        $users = mulopimfwc_get_location_managers_for_slug($location_slug);
    } else {
        $users = get_users([
            'role' => 'mulopimfwc_location_manager',
            'meta_query' => [
                [
                    'key' => 'mulopimfwc_assigned_locations',
                    'value' => $location_slug,
                    'compare' => 'LIKE',
                ],
            ],
            'fields' => 'all',
        ]);
    }

    $managers = [];
    if (is_array($users)) {
        foreach ($users as $u) {
            if (!is_object($u)) {
                continue;
            }
            $email = isset($u->user_email) ? sanitize_email($u->user_email) : '';
            $name = isset($u->display_name) ? $u->display_name : (isset($u->user_login) ? $u->user_login : 'Manager');
            $managers[] = [
                'id' => (int) $u->ID,
                'label' => trim($name . (!empty($email) ? ' — ' . $email : '')),
                'email' => $email,
            ];
        }
    }

    wp_send_json_success(['managers' => $managers]);
}

/**
 * Send a simple test email for validating notification delivery/routing.
 */
add_action('wp_ajax_mulopimfwc_send_test_notification_email', 'mulopimfwc_send_test_notification_email_ajax');
function mulopimfwc_send_test_notification_email_ajax()
{
    check_ajax_referer('mulopimfwc_notifications_test_nonce', 'nonce');

    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(['message' => 'Unauthorized']);
        return;
    }

    $type = isset($_POST['recipient_type']) ? sanitize_text_field(wp_unslash($_POST['recipient_type'])) : 'admin_email';
    $location_slug = isset($_POST['location_slug']) ? sanitize_text_field(wp_unslash($_POST['location_slug'])) : '';
    $manager_user_id = isset($_POST['manager_user_id']) ? sanitize_text_field(wp_unslash($_POST['manager_user_id'])) : 'all';
    $custom_email = isset($_POST['custom_email']) ? sanitize_email(wp_unslash($_POST['custom_email'])) : '';

    $recipients = [];
    $location_name = '';

    if (($type === 'location_contact' || $type === 'location_manager') && empty($location_slug)) {
        wp_send_json_error(['message' => __('Please select a location.', 'multi-location-product-and-inventory-management')]);
        return;
    }

    if ($type === 'admin_email') {
        $admin_email = sanitize_email(get_option('admin_email'));
        if ($admin_email && is_email($admin_email)) {
            $recipients[] = $admin_email;
        }
    } elseif ($type === 'custom') {
        if ($custom_email && is_email($custom_email)) {
            $recipients[] = $custom_email;
        } else {
            wp_send_json_error(['message' => __('Please enter a valid email address.', 'multi-location-product-and-inventory-management')]);
            return;
        }
    } elseif ($type === 'location_contact') {
        $term = get_term_by('slug', $location_slug, 'mulopimfwc_store_location');
        if ($term && !is_wp_error($term)) {
            $location_name = $term->name;
            $loc_email = sanitize_email((string) get_term_meta($term->term_id, 'email', true));
            if ($loc_email && is_email($loc_email)) {
                $recipients[] = $loc_email;
            }
        }
    } elseif ($type === 'location_manager') {
        $term = get_term_by('slug', $location_slug, 'mulopimfwc_store_location');
        if ($term && !is_wp_error($term)) {
            $location_name = $term->name;
        }

        $users = function_exists('mulopimfwc_get_location_managers_for_slug')
            ? mulopimfwc_get_location_managers_for_slug($location_slug)
            : get_users([
                'role' => 'mulopimfwc_location_manager',
                'meta_query' => [
                    [
                        'key' => 'mulopimfwc_assigned_locations',
                        'value' => $location_slug,
                        'compare' => 'LIKE',
                    ],
                ],
                'fields' => 'all',
            ]);

        if ($manager_user_id !== 'all') {
            $target_id = (int) $manager_user_id;
            $users = array_filter(is_array($users) ? $users : [], function ($u) use ($target_id) {
                return is_object($u) && (int) $u->ID === $target_id;
            });
        }

        if (is_array($users)) {
            foreach ($users as $u) {
                $email = isset($u->user_email) ? sanitize_email($u->user_email) : '';
                if ($email && is_email($email)) {
                    $recipients[] = $email;
                }
            }
        }
    }

    $recipients = array_values(array_unique(array_filter($recipients, 'is_email')));

    if (empty($recipients)) {
        wp_send_json_error(['message' => __('No valid recipient email could be resolved for this test.', 'multi-location-product-and-inventory-management')]);
        return;
    }

    $site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
    $subject = sprintf('[%s] %s', $site_name, __('Test notification email', 'multi-location-product-and-inventory-management'));

    $lines = [];
    $lines[] = __('This is a test email sent from Multi Location Product & Inventory Management.', 'multi-location-product-and-inventory-management');
    $lines[] = '';
    $lines[] = sprintf(__('Recipient type: %s', 'multi-location-product-and-inventory-management'), $type);
    if (!empty($location_slug)) {
        $lines[] = sprintf(__('Location: %s (%s)', 'multi-location-product-and-inventory-management'), $location_name ? $location_name : $location_slug, $location_slug);
    }
    $lines[] = sprintf(__('Time: %s', 'multi-location-product-and-inventory-management'), wp_date('Y-m-d H:i:s'));

    $sent_any = false;
    foreach ($recipients as $to) {
        $ok = wp_mail($to, $subject, implode("\n", $lines));
        if ($ok) {
            $sent_any = true;
        }
    }

    if (!$sent_any) {
        wp_send_json_error(['message' => __('wp_mail() reported failure. Please check your SMTP/mail configuration.', 'multi-location-product-and-inventory-management')]);
        return;
    }

    wp_send_json_success([
        'recipients' => $recipients,
    ]);
}

// FIXED: Removed duplicate activation hook - already handled in mulopimfwc_activation()
// FIXED: Add cleanup on deactivation
register_deactivation_hook(__FILE__, 'mulopimfwc_deactivation');
function mulopimfwc_deactivation() {
    // Flush rewrite rules
    flush_rewrite_rules();
    
    // Clean up tracking data if option is enabled
    $cleanup_on_deactivate = apply_filters('mulopimfwc_cleanup_on_deactivate', false);
    if ($cleanup_on_deactivate) {
        // Delete tracking options to prevent database bloat
        delete_option('mulopimfwc_customer_location_tracking');
        delete_option('mulopimfwc_customer_location_popularity');
        delete_option('mulopimfwc_customer_location_stats');
        
        // Clear transients
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_mulopimfwc_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_mulopimfwc_%'");
    }
}


// Serve service worker directly before WordPress processes the request
// This must happen very early to avoid redirects
add_action('plugins_loaded', 'mulopimfwc_serve_sw_direct', 1);
function mulopimfwc_serve_sw_direct()
{
    // Check if this is the service worker request
    if (!isset($_SERVER['REQUEST_URI'])) {
        return;
    }
    
    $request_uri = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']));
    
    // Check if request is for service worker (case insensitive)
    if (preg_match('/\/mulopimfwc-sw\.js(\?.*)?$/i', $request_uri)) {
        // Disable any output buffering
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Set headers for service worker
        status_header(200);
        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: /');
        header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('X-Content-Type-Options: nosniff');

        // Read and serve service worker file
        $sw_file = plugin_dir_path(__FILE__) . 'assets/js/service-worker.js';
        if (file_exists($sw_file) && is_readable($sw_file)) {
            readfile($sw_file);
        } else {
            // Log error but don't break the page
            error_log('Service worker file not found: ' . $sw_file);
            status_header(404);
            header('Content-Type: application/javascript; charset=utf-8');
            echo '// Service worker file not found';
        }
        exit;
    }
}

// Register REST API endpoint as alternative (REST API doesn't redirect)
add_action('rest_api_init', 'mulopimfwc_register_sw_rest_endpoint');
function mulopimfwc_register_sw_rest_endpoint()
{
    register_rest_route('mulopimfwc/v1', '/sw.js', array(
        'methods' => 'GET',
        'callback' => 'mulopimfwc_serve_sw_rest',
        'permission_callback' => '__return_true',
    ));
}

function mulopimfwc_serve_sw_rest()
{
    // Set headers for service worker
    status_header(200);
    header('Content-Type: application/javascript; charset=utf-8');
    header('Service-Worker-Allowed: /');
    header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-Content-Type-Options: nosniff');

    // Disable any output buffering
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Read and serve service worker file
    $sw_file = plugin_dir_path(__FILE__) . 'assets/js/service-worker.js';
    if (file_exists($sw_file) && is_readable($sw_file)) {
        readfile($sw_file);
    } else {
        error_log('Service worker file not found: ' . $sw_file);
        status_header(404);
        header('Content-Type: application/javascript; charset=utf-8');
        echo '// Service worker file not found';
    }
    exit;
}

// Also register rewrite rule as fallback (but we serve directly above)
add_action('init', 'mulopimfwc_register_sw_endpoint');
function mulopimfwc_register_sw_endpoint()
{
    add_rewrite_rule(
        '^mulopimfwc-sw\.js$',
        'index.php?mulopimfwc_sw=1',
        'top'
    );
}

add_filter('query_vars', 'mulopimfwc_add_sw_query_var');
function mulopimfwc_add_sw_query_var($vars)
{
    $vars[] = 'mulopimfwc_sw';
    return $vars;
}

// Fallback handler via template_redirect (shouldn't be needed with direct serving above)
add_action('template_redirect', 'mulopimfwc_serve_sw');
function mulopimfwc_serve_sw()
{
    if (!get_query_var('mulopimfwc_sw')) {
        return;
    }

    // Set headers for service worker
    status_header(200);
    header('Content-Type: application/javascript; charset=utf-8');
    header('Service-Worker-Allowed: /');
    header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-Content-Type-Options: nosniff');

    // Disable any output buffering
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Read and serve service worker file
    $sw_file = plugin_dir_path(__FILE__) . 'assets/js/service-worker.js';
    if (file_exists($sw_file) && is_readable($sw_file)) {
        readfile($sw_file);
    } else {
        // Log error but don't break the page
        error_log('Service worker file not found: ' . $sw_file);
        status_header(404);
        echo '// Service worker file not found';
    }
    exit;
}

/**
 * Register service worker with proper scope
 */
add_action('admin_footer', 'mulopimfwc_add_sw_registration_script');
function mulopimfwc_add_sw_registration_script()
{
    if (!current_user_can('manage_woocommerce')) {
        return;
    }

    global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
    $notification_settings = isset($options['notification_settings']) && is_array($options['notification_settings'])
        ? $options['notification_settings']
        : [];

    if (!isset($notification_settings['pwa_enabled']) || $notification_settings['pwa_enabled'] !== 'on') {
        return;
    }

    // Use REST API endpoint for service worker (more reliable, no redirects)
    // Fallback to rewrite endpoint if REST API fails
    $sw_url_rest = rest_url('mulopimfwc/v1/sw.js');
    $sw_url_rewrite = home_url('/mulopimfwc-sw.js');
    $manifest_url = home_url('/mulopimfwc-manifest.json');

?>
    <script>
        if ('serviceWorker' in navigator && window.mulopimfwcNotificationConfig) {
            // Try REST API endpoint first (more reliable), fallback to rewrite endpoint
            window.mulopimfwcNotificationConfig.pwa_sw_url = <?php echo json_encode($sw_url_rest); ?>;
            window.mulopimfwcNotificationConfig.pwa_sw_url_fallback = <?php echo json_encode($sw_url_rewrite); ?>;
            window.mulopimfwcNotificationConfig.manifest_url = <?php echo json_encode($manifest_url); ?>;
        }
    </script>
<?php
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

function mulopimfwc_svg_icon($icon_name){
    $icons = [
        'location' => '<svg aria-hidden="true" class="address-label-icon" width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12.322 2a8.322 8.322 0 0 1 7.234 12.44l-1.085 1.451q-.897.938-3.566 3.57l-1.884 1.852a1 1 0 0 1-1.4 0l-3.703-3.656-1.322-1.329q-.466-.477-1.509-1.89A8.322 8.322 0 0 1 12.322 2m0 1.5a6.822 6.822 0 0 0-6.083 9.914l.133.246.592.884.218.236.59.605c.462.469 1.11 1.116 1.93 1.93l2.215 2.185.123.12a.4.4 0 0 0 .561 0l.074-.072 2.627-2.59 1.903-1.912.329-.342.153-.17.585-.875.133-.243a6.8 6.8 0 0 0 .732-2.767l.008-.327A6.82 6.82 0 0 0 12.322 3.5m0 3.25a3.75 3.75 0 1 1 0 7.5 3.75 3.75 0 0 1 0-7.5m0 1.5a2.25 2.25 0 1 0 0 4.5 2.25 2.25 0 0 0 0-4.5"></path>
                </svg>',
        'pencil' => '<svg aria-hidden="true" class="edit-icon" width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zm18.37-11.06c.39-.39.39-1.02 0-1.41l-2.34-2.34a.9959.9959 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"></path>
                </svg>',
        'trash' => '<svg aria-hidden="true" class="trash-icon" width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"></path>
                </svg>',
        'save' => '<svg width="24" height="24" viewBox="0 0 0.72 0.72" xmlns="http://www.w3.org/2000/svg"><path d="M.71.185.535.01A.03.03 0 0 0 .512 0H.033A.033.033 0 0 0 0 .033v.655c0 .018.015.033.033.033h.655A.033.033 0 0 0 .721.688v-.48a.03.03 0 0 0-.01-.023zM.196.065h.196V.24H.196zm0 .589V.479h.327v.175zm.458 0H.588V.447A.033.033 0 0 0 .555.414H.164a.033.033 0 0 0-.033.033v.207H.065V.065H.13v.208c0 .018.015.033.033.033h.262A.033.033 0 0 0 .458.273V.065h.04l.156.156z"/></svg>',
        'left_arrow' => '<svg aria-hidden="true" class="left-arrow-icon" width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"></path>
                </svg>',
        'right_arrow' => '<svg aria-hidden="true" class="right-arrow-icon" width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M8.59 16.59L10 18l6-6-6-6-1.41 1.41L13.17 12z"></path>
                </svg>',
        'info' => '<svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="10" fill="#3498db"/><rect x="11" y="10" width="2" height="7" fill="#fff"/><rect x="11" y="7" width="2" height="2" fill="#fff"/></svg>',
    ];

    return isset($icons[$icon_name]) ? $icons[$icon_name] : '';
}




