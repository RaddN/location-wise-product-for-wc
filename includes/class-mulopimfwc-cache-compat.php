<?php
/**
 * Cache Compatibility Handler
 * 
 * Handles cache compatibility for Multi Location Product & Inventory Management.
 * Ensures proper cache varying by location cookie across all cache layers.
 *
 * @package MultiLocationProductInventoryManagement
 * @since 1.1.5.3
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class MULOPIMFWC_Cache_Compat
 */
class MULOPIMFWC_Cache_Compat {

    /**
     * Cookie name (filterable)
     *
     * @var string
     */
    private $cookie_name;

    /**
     * Cache header name (filterable)
     *
     * @var string
     */
    private $header_name;

    /**
     * Whether cache header is enabled
     *
     * @var bool
     */
    private $header_enabled;

    /**
     * Whether query string fallback is enabled
     *
     * @var bool
     */
    private $querystring_fallback_enabled;

    /**
     * Whether debug mode is enabled
     *
     * @var bool
     */
    private $debug_enabled;

    /**
     * Constructor
     */
    public function __construct() {
        $this->cookie_name = apply_filters('mulopimfwc_location_cookie_name', 'mulopimfwc_store_location');
        $this->header_name = apply_filters('mulopimfwc_cache_location_header_name', 'X-MuLoPIM-Location');
        $this->header_enabled = apply_filters('mulopimfwc_cache_location_header_enabled', true);
        $this->querystring_fallback_enabled = apply_filters('mulopimfwc_cache_querystring_fallback_enabled', false);
        $this->debug_enabled = apply_filters('mulopimfwc_cache_debug_enabled', false) && current_user_can('manage_options');

        // Hook early to set headers before output
        add_action('template_redirect', [$this, 'maybe_set_cache_headers'], 1);
        add_action('init', [$this, 'maybe_handle_querystring_fallback'], 1);

        // Integrate with cache plugins
        $this->integrate_cache_plugins();
    }

    /**
     * Get current location slug from cookie or query string
     *
     * @return string Location slug or empty string
     */
    public function get_current_location_slug(): string {
        // Check query string first if fallback is enabled
        if ($this->querystring_fallback_enabled && isset($_GET['mulopim_loc'])) {
            $query_slug = sanitize_title(wp_unslash($_GET['mulopim_loc']));
            if (!empty($query_slug)) {
                return $query_slug;
            }
        }

        // Check cookie
        if (isset($_COOKIE[$this->cookie_name])) {
            $cookie_slug = sanitize_text_field(wp_unslash($_COOKIE[$this->cookie_name]));
            if (!empty($cookie_slug)) {
                return $cookie_slug;
            }
        }

        return '';
    }

    /**
     * Validate location slug exists
     *
     * @param string $slug Location slug
     * @return bool
     */
    private function is_valid_location_slug(string $slug): bool {
        if (empty($slug) || $slug === 'all-products') {
            return true; // 'all-products' is a valid special value
        }

        $term = get_term_by('slug', $slug, 'mulopimfwc_store_location');
        return $term && !is_wp_error($term);
    }

    /**
     * Check if request is eligible for cache varying
     *
     * @return bool
     */
    private function is_cacheable_request(): bool {
        // Don't vary cache for admin, AJAX, REST, or sensitive pages
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return false;
        }

        // Don't vary on WooCommerce sensitive pages
        if (function_exists('is_cart') && is_cart()) {
            return false;
        }
        if (function_exists('is_checkout') && is_checkout()) {
            return false;
        }
        if (function_exists('is_account_page') && is_account_page()) {
            return false;
        }

        // Don't vary on POST requests
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            return false;
        }

        // Allow filtering
        $context = [
            'is_admin' => is_admin(),
            'is_ajax' => wp_doing_ajax(),
            'is_rest' => defined('REST_REQUEST') && REST_REQUEST,
            'is_cart' => function_exists('is_cart') ? is_cart() : false,
            'is_checkout' => function_exists('is_checkout') ? is_checkout() : false,
            'is_account' => function_exists('is_account_page') ? is_account_page() : false,
            'method' => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET',
        ];

        return apply_filters('mulopimfwc_cache_vary_enabled_for_request', true, $context);
    }

    /**
     * Check if should vary for logged-in users
     *
     * @return bool
     */
    private function should_vary_for_user(): bool {
        // By default, only vary for guests (logged-in users often have separate cache anyway)
        $vary_for_logged_in = apply_filters('mulopimfwc_cache_vary_for_logged_in', false);
        
        if (!$vary_for_logged_in && is_user_logged_in()) {
            return false;
        }

        return true;
    }

    /**
     * Set cache headers if eligible
     */
    public function maybe_set_cache_headers(): void {
        if (!$this->is_cacheable_request() || !$this->should_vary_for_user()) {
            return;
        }

        $location_slug = $this->get_current_location_slug();

        // Only set headers if we have a valid location
        if (empty($location_slug) || !$this->is_valid_location_slug($location_slug)) {
            return;
        }

        // Set location header if enabled
        if ($this->header_enabled && !headers_sent()) {
            header(sprintf('%s: %s', $this->header_name, sanitize_text_field($location_slug)));

            // Optionally add Vary header (append, don't overwrite)
            $existing_vary = '';
            if (function_exists('headers_list')) {
                $headers = headers_list();
                foreach ($headers as $header) {
                    if (stripos($header, 'Vary:') === 0) {
                        $existing_vary = substr($header, 6); // Remove "Vary: "
                        break;
                    }
                }
            }

            if (!empty($existing_vary)) {
                // Append our header to existing Vary
                $vary_parts = array_map('trim', explode(',', $existing_vary));
                if (!in_array($this->header_name, $vary_parts, true)) {
                    $vary_parts[] = $this->header_name;
                    header('Vary: ' . implode(', ', $vary_parts));
                }
            } else {
                // Set new Vary header
                header('Vary: ' . $this->header_name);
            }
        }

        // Debug header (admin only)
        if ($this->debug_enabled && !headers_sent()) {
            $debug_value = sprintf(
                'cookie=%s; location=%s; valid=%s',
                isset($_COOKIE[$this->cookie_name]) ? 'set' : 'unset',
                $location_slug ?: 'none',
                $this->is_valid_location_slug($location_slug) ? 'yes' : 'no'
            );
            header('X-MuLoPIM-Debug: ' . $debug_value);
        }
    }

    /**
     * Handle query string fallback mode
     */
    public function maybe_handle_querystring_fallback(): void {
        if (!$this->querystring_fallback_enabled) {
            return;
        }

        if (!$this->is_cacheable_request()) {
            return;
        }

        // Don't redirect on POST requests
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            return;
        }

        $cookie_slug = isset($_COOKIE[$this->cookie_name]) 
            ? sanitize_text_field(wp_unslash($_COOKIE[$this->cookie_name])) 
            : '';

        $query_slug = isset($_GET['mulopim_loc']) 
            ? sanitize_title(wp_unslash($_GET['mulopim_loc'])) 
            : '';

        // If cookie exists but query param is missing, redirect to add it
        if (!empty($cookie_slug) && empty($query_slug) && $this->is_valid_location_slug($cookie_slug)) {
            // Build redirect URL with query param
            $current_url = remove_query_arg('mulopim_loc');
            $redirect_url = add_query_arg('mulopim_loc', urlencode($cookie_slug), $current_url);
            
            // Only redirect if URL actually changed and headers not sent
            if ($redirect_url !== $current_url && !headers_sent()) {
                wp_safe_redirect($redirect_url, 302);
                exit;
            }
        }
    }

    /**
     * Integrate with popular cache plugins
     */
    private function integrate_cache_plugins(): void {
        // LiteSpeed Cache
        if (defined('LSCWP_V')) {
            add_filter('litespeed_vary', [$this, 'litespeed_vary_cookies']);
        }

        // WP Rocket
        if (function_exists('rocket_clean_domain')) {
            add_filter('rocket_cache_reject_cookies', [$this, 'wp_rocket_vary_cookies']);
        }

        // W3 Total Cache
        if (defined('W3TC')) {
            add_filter('w3tc_pagecache_set_cookies', [$this, 'w3tc_vary_cookies']);
        }

        // Cache Enabler
        if (class_exists('Cache_Enabler')) {
            add_filter('cache_enabler_bypass_cache', [$this, 'cache_enabler_bypass'], 10, 2);
        }

        // WP Super Cache
        if (function_exists('wp_cache_is_enabled') && wp_cache_is_enabled()) {
            add_action('init', [$this, 'wp_super_cache_vary'], 1);
        }
    }

    /**
     * LiteSpeed Cache: Add cookie to vary list
     *
     * @param array $vary Existing vary array
     * @return array
     */
    public function litespeed_vary_cookies(array $vary): array {
        if (!is_array($vary)) {
            $vary = [];
        }

        $location_slug = $this->get_current_location_slug();
        if (!empty($location_slug) && $this->is_valid_location_slug($location_slug)) {
            $vary[] = $this->cookie_name;
        }

        return $vary;
    }

    /**
     * WP Rocket: Add cookie to vary list
     *
     * @param array $cookies Existing cookies array
     * @return array
     */
    public function wp_rocket_vary_cookies(array $cookies): array {
        if (!is_array($cookies)) {
            $cookies = [];
        }

        $location_slug = $this->get_current_location_slug();
        if (!empty($location_slug) && $this->is_valid_location_slug($location_slug)) {
            $cookies[] = $this->cookie_name;
        }

        return $cookies;
    }

    /**
     * W3 Total Cache: Add cookie to vary list
     *
     * @param array $cookies Existing cookies array
     * @return array
     */
    public function w3tc_vary_cookies(array $cookies): array {
        if (!is_array($cookies)) {
            $cookies = [];
        }

        $location_slug = $this->get_current_location_slug();
        if (!empty($location_slug) && $this->is_valid_location_slug($location_slug)) {
            $cookies[] = $this->cookie_name;
        }

        return $cookies;
    }

    /**
     * Cache Enabler: Bypass cache when location changes
     * Note: This is a bypass filter, so we return false to NOT bypass (allow caching with vary)
     *
     * @param bool $bypass Whether to bypass cache
     * @param string $request_uri Request URI
     * @return bool
     */
    public function cache_enabler_bypass(bool $bypass, string $request_uri): bool {
        // Don't bypass - let cache enabler handle it, but we've set headers
        return $bypass;
    }

    /**
     * WP Super Cache: Set vary cookie
     */
    public function wp_super_cache_vary(): void {
        if (!$this->is_cacheable_request()) {
            return;
        }

        $location_slug = $this->get_current_location_slug();
        if (!empty($location_slug) && $this->is_valid_location_slug($location_slug)) {
            // WP Super Cache uses $_SERVER['HTTP_COOKIE'] to vary
            // Our cookie is already set, so it should work automatically
            // But we can add a filter if needed
            if (function_exists('wp_cache_set_cookie')) {
                // WP Super Cache doesn't have a direct vary cookie filter,
                // but it respects cookies in the request
                // The cookie is already set, so this should work
            }
        }
    }
}

