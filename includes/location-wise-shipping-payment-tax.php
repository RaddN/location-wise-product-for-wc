<?php
if (! defined('ABSPATH')) exit;

if (!function_exists('mulopimfwc_get_effective_runtime_location_slug')) {
    /**
     * Get unique location slugs currently present in cart/shipping packages.
     *
     * @return string[]
     */
    function mulopimfwc_get_runtime_cart_location_slugs(): array
    {
        if (!function_exists('WC') || !WC()) {
            return [];
        }

        $slugs = [];

        if (isset(WC()->cart) && is_object(WC()->cart) && method_exists(WC()->cart, 'get_cart')) {
            foreach ((array) WC()->cart->get_cart() as $cart_item) {
                $slug = isset($cart_item['mulopimfwc_location']) ? (string) $cart_item['mulopimfwc_location'] : '';
                $slug = sanitize_title(rawurldecode($slug));
                if ($slug !== '' && $slug !== 'all-products' && $slug !== 'unknown') {
                    $slugs[] = $slug;
                }
            }
        }

        if (isset(WC()->shipping) && is_object(WC()->shipping) && method_exists(WC()->shipping, 'get_packages')) {
            foreach ((array) WC()->shipping()->get_packages() as $package) {
                $slug = isset($package['location_slug']) ? (string) $package['location_slug'] : '';
                $slug = sanitize_title(rawurldecode($slug));
                if ($slug !== '' && $slug !== 'all-products' && $slug !== 'unknown') {
                    $slugs[] = $slug;
                }
            }
        }

        return array_values(array_unique($slugs));
    }

    /**
     * Resolve the effective location slug used by runtime filters.
     *
     * Priority:
     * - Selected location cookie (including "all-products")
     * - Single location detected from cart/package location data
     * - Default location when popup is disabled
     *
     * @param array|null $options Optional plugin options.
     * @return string
     */
    function mulopimfwc_get_effective_runtime_location_slug($options = null): string
    {
        $cookie_location = function_exists('mulopimfwc_get_store_location_cookie')
            ? sanitize_title(rawurldecode((string) mulopimfwc_get_store_location_cookie()))
            : '';

        if ($cookie_location !== '') {
            return $cookie_location;
        }

        $cart_locations = mulopimfwc_get_runtime_cart_location_slugs();
        if (count($cart_locations) === 1) {
            return (string) $cart_locations[0];
        }

        if (!is_array($options)) {
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
        }

        if (function_exists('mulopimfwc_is_manual_assignment_strict_mode') && mulopimfwc_is_manual_assignment_strict_mode($options)) {
            return '';
        }

        $enable_popup = isset($options['enable_popup']) ? (string) $options['enable_popup'] : 'off';
        if ($enable_popup !== 'off' || !function_exists('mulopimfwc_get_default_location_value')) {
            return '';
        }

        $default_location = sanitize_title(rawurldecode((string) mulopimfwc_get_default_location_value($options)));
        if ($default_location === '') {
            return '';
        }

        if ($default_location === 'all-products') {
            return $default_location;
        }

        if (function_exists('mulopimfwc_validate_location_slug')) {
            $term = mulopimfwc_validate_location_slug($default_location, false);
            if ($term && !is_wp_error($term)) {
                return sanitize_title(rawurldecode((string) $term->slug));
            }
            return '';
        }

        return $default_location;
    }
}

/**
 * Runtime filters to enforce location-scoped shipping, payments & tax class
 */
class MULOPIMFWC_Runtime_Filters
{

    /** Cache of current location config for this request */
    protected static $loc_cache = null;

    public function __construct()
    {

        global $mulopimfwc_options;

        if (function_exists('mulopimfwc_is_manual_assignment_strict_mode') && mulopimfwc_is_manual_assignment_strict_mode()) {
            return;
        }

        $options = is_array($mulopimfwc_options ?? null)
            ? $mulopimfwc_options
            : get_option('mulopimfwc_display_options', []);

        $shipping_enabled = function_exists('mulopimfwc_is_location_shipping_enabled')
            ? mulopimfwc_is_location_shipping_enabled($options)
            : (isset($options['enable_location_shipping']) && $options['enable_location_shipping'] === 'on');

        if ($shipping_enabled && $this->is_per_location_shipping_calculation($options)) {
            // Shipping: remove disallowed shipping method instances
            add_filter('woocommerce_package_rates', [$this, 'filter_package_rates'], 50, 2);
        }

        if (function_exists('mulopimfwc_is_location_payment_methods_enabled')
            ? mulopimfwc_is_location_payment_methods_enabled($options)
            : (isset($options['enable_location_payment_methods']) && $options['enable_location_payment_methods'] === 'on' && mulopimfwc_premium_feature())
        ) {
            // Payment: remove disallowed gateways
            add_filter('woocommerce_available_payment_gateways', [$this, 'filter_payment_gateways'], 50);
        }

        if (function_exists('mulopimfwc_is_location_taxes_enabled')
            ? mulopimfwc_is_location_taxes_enabled($options)
            : (isset($options['enable_location_taxes']) && $options['enable_location_taxes'] === 'on' && mulopimfwc_premium_feature())
        ) {
            // Tax class: set default tax class from location (product + variations)
            add_filter('woocommerce_product_get_tax_class', [$this, 'filter_product_tax_class'], 50, 2);
            add_filter('woocommerce_product_variation_get_tax_class', [$this, 'filter_product_tax_class'], 50, 2);
        }
    }

    /**
     * Get the active location ID for the current user/session.
     * Priority:
     *  - session (e.g., a selector you set elsewhere)
     *  - first ID from user meta 'mulopimfwc_user_locations'
     *  - fallback: null (do nothing)
     */
    protected function get_active_location_id()
    {
        $location = $this->get_current_location();

        if (!empty($location) && $location !== 'all-products') {
            $term = get_term_by('slug', $location, 'mulopimfwc_store_location');
            if ($term) {
                return $term->term_id;
            }
        }

        return null;
    }

    private function get_current_location()
    {
        if (function_exists('mulopimfwc_get_effective_runtime_location_slug')) {
            return mulopimfwc_get_effective_runtime_location_slug();
        }

        return mulopimfwc_get_store_location_cookie();
    }

    /**
     * Load config for a location term (cached per request).
     * Returns:
     * [
     *   'payments' => [ 'cod', 'bacs', ... ],
     *   'shipping_instances' => [ 12, 34, ... ], // instance_id ints
     *   'tax_class' => 'reduced-rate' or '' (standard)
     * ]
     */
    protected function get_location_config()
    {
        if (self::$loc_cache !== null) {
            return self::$loc_cache;
        }

        $loc_id = $this->get_active_location_id();
        if (! $loc_id) {
            self::$loc_cache = [];
            return self::$loc_cache;
        }

        // Gateways (array of IDs)
        $payments = (array) get_term_meta($loc_id, 'payment_methods', true);

        // Shipping: stored as ["zoneId:instanceId", ...] -> reduce to instance IDs
        $raw_methods = (array) get_term_meta($loc_id, 'shipping_methods', true);
        $instance_ids = [];
        foreach ($raw_methods as $pair) {
            // keep digits and colon; then split
            $pair = preg_replace('/[^0-9:]/', '', (string) $pair);
            if (preg_match('/^\d+:(\d+)$/', $pair, $m)) {
                $instance_ids[] = absint($m[1]);
            }
        }
        $instance_ids = array_values(array_unique($instance_ids));

        // Tax class (slug or '' for Standard)
        $tax_class = (string) get_term_meta($loc_id, 'tax_class', true);

        self::$loc_cache = [
            'payments'           => $payments,
            'shipping_instances' => $instance_ids,
            'tax_class'          => $tax_class,
            'location_id'        => $loc_id,
        ];
        return self::$loc_cache;
    }

    /**
     * Check if shipping calculation is in per-location mode.
     */
    protected function is_per_location_shipping_calculation($options = null): bool
    {
        if (!is_array($options)) {
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
        }

        $shipping_calculation_method = isset($options['shipping_calculation_method']) && mulopimfwc_premium_feature()
            ? (string) $options['shipping_calculation_method']
            : 'per_location';

        return $shipping_calculation_method === 'per_location';
    }

    /**
     * Check if location-wise pickup feature is enabled.
     */
    protected function is_location_pickup_enabled($options = null): bool
    {
        if (!is_array($options)) {
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
        }

        if (!mulopimfwc_premium_feature()) {
            return false;
        }

        return isset($options['enable_location_pickup']) && $options['enable_location_pickup'] === 'on';
    }

    /**
     * Detect if the given rate is a local pickup method.
     */
    protected function is_local_pickup_rate($rate): bool
    {
        if (!is_object($rate)) {
            return false;
        }

        $method_id = '';
        if (is_callable([$rate, 'get_method_id'])) {
            $method_id = (string) $rate->get_method_id();
        } elseif (isset($rate->method_id)) {
            $method_id = (string) $rate->method_id;
        }

        if ($method_id === '') {
            return false;
        }

        return in_array($method_id, ['local_pickup', 'pickup_location'], true);
    }

    /**
     * SHIPPING: Keep only rates whose instance_id is allowed for the location.
     */
    public function filter_package_rates($rates, $package)
    {
        // nearest_with_transfer should keep default WooCommerce shipping-rate behavior.
        if (!$this->is_per_location_shipping_calculation()) {
            return $rates;
        }

        $cfg = $this->get_location_config();
        if (empty($cfg['shipping_instances'])) {
            // No restriction configured -> leave as is
            return $rates;
        }

        $allow_pickup = $this->is_location_pickup_enabled();

        foreach ($rates as $key => $rate) {
            // $rate is WC_Shipping_Rate
            $instance_id = is_callable([$rate, 'get_instance_id']) ? (int) $rate->get_instance_id() : 0;

            // Some methods can have instance_id 0 (legacy table rate etc). If 0, require explicit '0' in allowlist.
            if (! in_array($instance_id, $cfg['shipping_instances'], true)) {
                if ($allow_pickup && $this->is_local_pickup_rate($rate)) {
                    continue;
                }
                unset($rates[$key]);
            }
        }

        return $rates;
    }

    /**
     * PAYMENTS: Keep only gateways that are in the location allowlist.
     */
    public function filter_payment_gateways($gateways)
    {
        $cfg = $this->get_location_config();
        if (empty($cfg['payments']) || !is_array($cfg['payments']) || count($cfg['payments']) === 0 || $cfg['payments'] === [""]) {
            // No restriction -> do nothing
            return $gateways;
        }

        foreach ($gateways as $id => $gateway) {
            if (! in_array($id, $cfg['payments'], true)) {
                unset($gateways[$id]);
            }
        }

        return $gateways;
    }

    /**
     * TAX CLASS: Override product tax class when a location tax class is set.
     * Return '' for Standard to let WC use its normal "Standard rate".
     */
    public function filter_product_tax_class($tax_class, $product)
    {
        $cfg = $this->get_location_config();
        if (! isset($cfg['tax_class'])) {
            return $tax_class;
        }

        // Only override when a non-empty class is configured; keep Standard logic otherwise.
        if ($cfg['tax_class'] !== '') {
            return (string) $cfg['tax_class'];
        }

        return $tax_class;
    }
}

// Bootstrap (frontend + checkout contexts)
add_action('init', function () {
    if (! is_admin()) {
        // optional: ensure PHP session exists if you plan to use session-based location selection
        if (! session_id()) {
            @session_start();
        }
        new MULOPIMFWC_Runtime_Filters();
    }
});
