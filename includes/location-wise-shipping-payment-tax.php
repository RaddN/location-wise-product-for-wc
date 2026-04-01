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

        // WooCommerce cart access before wp_loaded triggers wc_doing_it_wrong notices.
        if (!did_action('wp_loaded') && !doing_action('wp_loaded')) {
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
        $debug = static function (string $reason, array $context = []) {
            static $count = 0;
            if ($count >= 25) {
                return;
            }
            $count++;
            if (function_exists('mulopimfwc_log_location_currency_debug')) {
                $context['event_count'] = $count;
                $context['reason'] = $reason;
                mulopimfwc_log_location_currency_debug('effective_runtime_location', $context);
            }
        };

        $cookie_location = function_exists('mulopimfwc_get_store_location_cookie')
            ? sanitize_title(rawurldecode((string) mulopimfwc_get_store_location_cookie()))
            : '';

        if ($cookie_location !== '') {
            $debug('cookie_location', [
                'resolved_location' => $cookie_location,
            ]);
            return $cookie_location;
        }

        $cart_locations = mulopimfwc_get_runtime_cart_location_slugs();
        if (count($cart_locations) === 1) {
            $debug('single_cart_location', [
                'resolved_location' => (string) $cart_locations[0],
                'cart_locations' => $cart_locations,
            ]);
            return (string) $cart_locations[0];
        }

        if (!is_array($options)) {
            global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
        }

        if (function_exists('mulopimfwc_is_manual_assignment_strict_mode') && mulopimfwc_is_manual_assignment_strict_mode($options)) {
            $debug('manual_assignment_mode', [
                'resolved_location' => '',
            ]);
            return '';
        }

        $enable_popup = isset($options['enable_popup']) ? (string) $options['enable_popup'] : 'off';
        if ($enable_popup !== 'off' || !function_exists('mulopimfwc_get_default_location_value')) {
            $debug('popup_enabled_or_missing_default_helper', [
                'resolved_location' => '',
                'enable_popup' => $enable_popup,
                'has_default_helper' => function_exists('mulopimfwc_get_default_location_value'),
                'cart_locations' => $cart_locations,
            ]);
            return '';
        }

        $default_location = sanitize_title(rawurldecode((string) mulopimfwc_get_default_location_value($options)));
        if ($default_location === '') {
            $debug('default_location_empty', [
                'resolved_location' => '',
                'cart_locations' => $cart_locations,
            ]);
            return '';
        }

        if ($default_location === 'all-products') {
            $debug('default_location_all_products', [
                'resolved_location' => $default_location,
            ]);
            return $default_location;
        }

        if (function_exists('mulopimfwc_validate_location_slug')) {
            $term = mulopimfwc_validate_location_slug($default_location, false);
            if ($term && !is_wp_error($term)) {
                $resolved = sanitize_title(rawurldecode((string) $term->slug));
                $debug('default_location_validated', [
                    'resolved_location' => $resolved,
                    'default_location' => $default_location,
                    'term_id' => (int) $term->term_id,
                ]);
                return $resolved;
            }
            $debug('default_location_invalid', [
                'resolved_location' => '',
                'default_location' => $default_location,
            ]);
            return '';
        }

        $debug('default_location_unvalidated', [
            'resolved_location' => $default_location,
            'default_location' => $default_location,
        ]);
        return $default_location;
    }
}

if (!function_exists('mulopimfwc_get_runtime_currency_conversion_context')) {
    /**
     * Resolve runtime currency conversion context for the active location.
     *
     * @param int|null $preferred_location_term_id Optional explicit location term ID.
     * @return array{location_id: int, should_convert: bool, rate: float}
     */
    function mulopimfwc_get_runtime_currency_conversion_context($preferred_location_term_id = null): array
    {
        static $cache = [];

        $term_id = absint($preferred_location_term_id);
        $cache_key = $term_id > 0 ? 'term_' . $term_id : 'runtime';

        if (isset($cache[$cache_key])) {
            return $cache[$cache_key];
        }

        if ($term_id <= 0) {
            $runtime_slug = function_exists('mulopimfwc_get_effective_runtime_location_slug')
                ? sanitize_title(rawurldecode((string) mulopimfwc_get_effective_runtime_location_slug()))
                : '';

            if ($runtime_slug === '' || $runtime_slug === 'all-products') {
                $cache[$cache_key] = [
                    'location_id' => 0,
                    'should_convert' => false,
                    'rate' => 1.0,
                ];
                return $cache[$cache_key];
            }

            $location_term = function_exists('mulopimfwc_validate_location_slug')
                ? mulopimfwc_validate_location_slug($runtime_slug, false)
                : get_term_by('slug', $runtime_slug, 'mulopimfwc_store_location');

            if (!$location_term || is_wp_error($location_term)) {
                $cache[$cache_key] = [
                    'location_id' => 0,
                    'should_convert' => false,
                    'rate' => 1.0,
                ];
                return $cache[$cache_key];
            }

            $term_id = (int) $location_term->term_id;
            if ($term_id <= 0) {
                $cache[$cache_key] = [
                    'location_id' => 0,
                    'should_convert' => false,
                    'rate' => 1.0,
                ];
                return $cache[$cache_key];
            }
        }

        $context = function_exists('mulopimfwc_get_location_currency_conversion_context')
            ? (array) mulopimfwc_get_location_currency_conversion_context($term_id)
            : [
                'should_convert' => false,
                'rate' => 1.0,
            ];

        $rate = (isset($context['rate']) && is_numeric($context['rate']) && (float) $context['rate'] > 0)
            ? (float) $context['rate']
            : 1.0;

        $cache[$cache_key] = [
            'location_id' => $term_id,
            'should_convert' => !empty($context['should_convert']) && $rate > 0,
            'rate' => $rate,
        ];

        return $cache[$cache_key];
    }
}

if (!function_exists('mulopimfwc_convert_base_amount_to_runtime_currency')) {
    /**
     * Convert a base-currency amount into the active runtime location currency.
     *
     * @param mixed    $amount Base-currency amount.
     * @param int|null $preferred_location_term_id Optional explicit location term ID.
     * @return mixed
     */
    function mulopimfwc_convert_base_amount_to_runtime_currency($amount, $preferred_location_term_id = null)
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
            return $amount;
        }

        $context = mulopimfwc_get_runtime_currency_conversion_context($preferred_location_term_id);
        if (empty($context['should_convert']) || !isset($context['rate']) || (float) $context['rate'] <= 0) {
            return $normalized_amount;
        }

        $converted = $normalized_amount * (float) $context['rate'];
        if (function_exists('wc_format_decimal')) {
            return (float) wc_format_decimal($converted, 6, false);
        }

        return round($converted, 6);
    }
}

/**
 * Runtime filters to enforce location-scoped shipping, payments & tax class
 */
class MULOPIMFWC_Runtime_Filters
{

    /** Cache of current location config for this request */
    protected static $loc_cache = null;
    protected $converted_package_rate_hashes = [];
    protected $shipping_zone_ids_by_instance = [];
    protected $matching_zone_ids_by_package = [];

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

        $currency_enabled = function_exists('mulopimfwc_is_location_wise_currency_enabled')
            ? mulopimfwc_is_location_wise_currency_enabled($options)
            : false;

        if ($currency_enabled) {
            // Convert runtime shipping amounts from base currency into location currency.
            add_filter('woocommerce_package_rates', [$this, 'convert_package_rate_amounts_for_currency'], 80, 2);
        }

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
     *   'shipping_zones' => [ 0, 6, 9, ... ],   // zone_id ints
     *   'shipping_methods_by_zone' => [ 6 => [ 12 ], 9 => [ 34, 35 ] ],
     *   'shipping_instances' => [ 12, 34, ... ], // flattened instance_id ints
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

        // Shipping zones (array of zone IDs).
        $zone_ids = array_map('absint', (array) get_term_meta($loc_id, 'shipping_zones', true));
        $zone_ids = array_values(array_unique(array_filter($zone_ids, static function ($zone_id) {
            return $zone_id >= 0;
        })));

        // Shipping: stored as ["zoneId:instanceId", ...].
        $raw_methods = (array) get_term_meta($loc_id, 'shipping_methods', true);
        $methods_by_zone = [];
        $instance_ids = [];
        foreach ($raw_methods as $pair) {
            // keep digits and colon; then split
            $pair = preg_replace('/[^0-9:]/', '', (string) $pair);
            if (preg_match('/^(\d+):(\d+)$/', $pair, $m)) {
                $zone_id = absint($m[1]);
                $instance_id = absint($m[2]);

                if (!isset($methods_by_zone[$zone_id])) {
                    $methods_by_zone[$zone_id] = [];
                }

                $methods_by_zone[$zone_id][] = $instance_id;
                $instance_ids[] = $instance_id;
            }
        }

        foreach ($methods_by_zone as $zone_id => $zone_instance_ids) {
            $zone_instance_ids = array_map('absint', (array) $zone_instance_ids);
            $zone_instance_ids = array_values(array_unique(array_filter($zone_instance_ids, static function ($instance_id) {
                return $instance_id >= 0;
            })));

            if (empty($zone_instance_ids)) {
                unset($methods_by_zone[$zone_id]);
                continue;
            }

            $methods_by_zone[(int) $zone_id] = $zone_instance_ids;
        }

        $instance_ids = array_values(array_unique($instance_ids));

        if (!empty($zone_ids)) {
            $allowed_zone_lookup = array_fill_keys($zone_ids, true);
            $methods_by_zone = array_filter($methods_by_zone, static function ($zone_instance_ids, $zone_id) use ($allowed_zone_lookup) {
                return isset($allowed_zone_lookup[(int) $zone_id]);
            }, ARRAY_FILTER_USE_BOTH);
        } elseif (!empty($methods_by_zone)) {
            // If zone meta is missing but method pairs exist, infer the relevant zones from the pairs.
            $zone_ids = array_values(array_map('intval', array_keys($methods_by_zone)));
        }

        // Tax class (slug or '' for Standard)
        $tax_class = (string) get_term_meta($loc_id, 'tax_class', true);

        self::$loc_cache = [
            'payments'           => $payments,
            'shipping_zones'     => $zone_ids,
            'shipping_methods_by_zone' => $methods_by_zone,
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
        return function_exists('mulopimfwc_is_location_pickup_enabled')
            ? mulopimfwc_is_location_pickup_enabled($options)
            : (is_array($options) && !empty($options['enable_location_pickup']) && $options['enable_location_pickup'] === 'on' && mulopimfwc_premium_feature());
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
     * Resolve the shipping zone ID for a zone-based shipping rate.
     *
     * Returns null when the rate is not tied to a WooCommerce shipping-zone instance.
     */
    protected function get_shipping_zone_id_for_rate($rate): ?int
    {
        if (!is_object($rate) || $this->is_local_pickup_rate($rate) || !class_exists('WC_Shipping_Zones')) {
            return null;
        }

        $instance_id = is_callable([$rate, 'get_instance_id']) ? (int) $rate->get_instance_id() : 0;
        if ($instance_id <= 0) {
            return null;
        }

        if (array_key_exists($instance_id, $this->shipping_zone_ids_by_instance)) {
            return $this->shipping_zone_ids_by_instance[$instance_id];
        }

        $zone = WC_Shipping_Zones::get_zone_by('instance_id', $instance_id);
        $zone_id = ($zone && is_object($zone) && is_callable([$zone, 'get_id']))
            ? (int) $zone->get_id()
            : null;

        $this->shipping_zone_ids_by_instance[$instance_id] = $zone_id;

        return $zone_id;
    }

    /**
     * Resolve all WooCommerce shipping zones matching the package destination.
     *
     * Unlike WooCommerce core, this returns every matching non-default zone instead of
     * stopping at the first `zone_order` match. Zone `0` is only returned when no
     * non-default zone matches the package.
     *
     * @param array $package Shipping package.
     * @return int[]
     */
    protected function get_matching_zone_ids_for_package($package): array
    {
        if (!function_exists('WC') || !WC() || !WC()->countries) {
            return [];
        }

        $destination = isset($package['destination']) && is_array($package['destination'])
            ? $package['destination']
            : [];

        $country = strtoupper(wc_clean($destination['country'] ?? ''));
        $state = strtoupper(wc_clean($destination['state'] ?? ''));
        $postcode = wc_normalize_postcode(wc_clean($destination['postcode'] ?? ''));
        $continent = strtoupper(wc_clean(WC()->countries->get_continent_code_for_country($country)));

        $cache_key = md5(wp_json_encode([
            'country' => $country,
            'state' => $state,
            'postcode' => $postcode,
            'continent' => $continent,
        ]));

        if (isset($this->matching_zone_ids_by_package[$cache_key])) {
            return $this->matching_zone_ids_by_package[$cache_key];
        }

        if ($country === '') {
            $this->matching_zone_ids_by_package[$cache_key] = [];
            return [];
        }

        global $wpdb;

        $criteria = [];
        $criteria[] = $wpdb->prepare("( ( location_type = 'country' AND location_code = %s )", $country);
        $criteria[] = $wpdb->prepare("OR ( location_type = 'state' AND location_code = %s )", $country . ':' . $state);
        $criteria[] = $wpdb->prepare("OR ( location_type = 'continent' AND location_code = %s )", $continent);
        $criteria[] = 'OR ( location_type IS NULL ) )';

        $postcode_locations = $wpdb->get_results(
            "SELECT zone_id, location_code FROM {$wpdb->prefix}woocommerce_shipping_zone_locations WHERE location_type = 'postcode';"
        );

        if (!empty($postcode_locations)) {
            $zone_ids_with_postcode_rules = array_map('absint', wp_list_pluck($postcode_locations, 'zone_id'));
            $matches = wc_postcode_location_matcher($postcode, $postcode_locations, 'zone_id', 'location_code', $country);
            $do_not_match = array_unique(array_diff($zone_ids_with_postcode_rules, array_keys($matches)));

            if (!empty($do_not_match)) {
                $criteria[] = 'AND zones.zone_id NOT IN (' . implode(',', array_map('absint', $do_not_match)) . ')';
            }
        }

        $query = "SELECT DISTINCT zones.zone_id
            FROM {$wpdb->prefix}woocommerce_shipping_zones as zones
            LEFT OUTER JOIN {$wpdb->prefix}woocommerce_shipping_zone_locations as locations
                ON zones.zone_id = locations.zone_id AND location_type != 'postcode'
            WHERE " . implode(' ', $criteria) . '
            ORDER BY zone_order ASC, zones.zone_id ASC';

        $zone_ids = array_map('absint', (array) $wpdb->get_col($query));
        $zone_ids = array_values(array_unique(array_filter($zone_ids)));

        if (empty($zone_ids)) {
            $zone_ids = [0];
        }

        $this->matching_zone_ids_by_package[$cache_key] = $zone_ids;

        return $zone_ids;
    }

    /**
     * Merge rates from every selected zone that matches the package destination.
     *
     * WooCommerce core calculates only one matching zone. This supplements rates from
     * other matching zones that the active location explicitly allows.
     *
     * @param array $rates Existing package rates.
     * @param array $package Shipping package.
     * @param array $allowed_zone_ids Allowed zone IDs for the active location.
     * @param array $allowed_methods_by_zone Optional method restrictions keyed by zone ID.
     * @return array
     */
    protected function merge_matching_zone_rates($rates, $package, array $allowed_zone_ids, array $allowed_methods_by_zone): array
    {
        if (empty($allowed_zone_ids) || !class_exists('WC_Shipping_Zone')) {
            return $rates;
        }

        $matching_zone_ids = $this->get_matching_zone_ids_for_package($package);
        if (empty($matching_zone_ids)) {
            return $rates;
        }

        $matching_allowed_zone_ids = array_values(array_intersect($matching_zone_ids, $allowed_zone_ids));
        if (empty($matching_allowed_zone_ids)) {
            return $rates;
        }

        $present_zone_ids = [];
        foreach ($rates as $rate) {
            $zone_id = $this->get_shipping_zone_id_for_rate($rate);
            if ($zone_id !== null) {
                $present_zone_ids[$zone_id] = true;
            }
        }

        foreach ($matching_allowed_zone_ids as $zone_id) {
            if (isset($present_zone_ids[$zone_id])) {
                continue;
            }

            $zone = new WC_Shipping_Zone($zone_id);
            $methods = is_callable([$zone, 'get_shipping_methods'])
                ? $zone->get_shipping_methods()
                : [];

            foreach ((array) $methods as $method) {
                if (!is_object($method)) {
                    continue;
                }

                if (isset($method->enabled) && $method->enabled !== 'yes') {
                    continue;
                }

                $instance_id = isset($method->instance_id) ? (int) $method->instance_id : 0;
                if (!empty($allowed_methods_by_zone[$zone_id]) && !in_array($instance_id, $allowed_methods_by_zone[$zone_id], true)) {
                    continue;
                }

                if (!is_callable([$method, 'get_rates_for_package'])) {
                    continue;
                }

                $zone_rates = $method->get_rates_for_package($package);
                foreach ((array) $zone_rates as $rate_id => $rate) {
                    if (!isset($rates[$rate_id])) {
                        $rates[$rate_id] = $rate;
                    }
                }
            }
        }

        return $rates;
    }

    /**
     * SHIPPING: Keep only rates whose zone/instance is allowed for the location.
     *
     * Shipping zones act as the primary allowlist for a location. Shipping methods are
     * an optional narrower allowlist within those zones.
     */
    public function filter_package_rates($rates, $package)
    {
        // nearest_with_transfer should keep default WooCommerce shipping-rate behavior.
        if (!$this->is_per_location_shipping_calculation()) {
            return $rates;
        }

        $cfg = $this->get_location_config();
        $allowed_zone_ids = isset($cfg['shipping_zones']) && is_array($cfg['shipping_zones'])
            ? array_map('intval', $cfg['shipping_zones'])
            : [];
        $allowed_methods_by_zone = isset($cfg['shipping_methods_by_zone']) && is_array($cfg['shipping_methods_by_zone'])
            ? $cfg['shipping_methods_by_zone']
            : [];

        $has_zone_restrictions = !empty($allowed_zone_ids);
        $has_method_restrictions = !empty($allowed_methods_by_zone);

        if (!$has_zone_restrictions && !$has_method_restrictions) {
            // No restriction configured -> leave as is
            return $rates;
        }

        if ($has_zone_restrictions) {
            $rates = $this->merge_matching_zone_rates($rates, $package, $allowed_zone_ids, $allowed_methods_by_zone);
        }

        $allow_pickup = $this->is_location_pickup_enabled();

        foreach ($rates as $key => $rate) {
            if ($this->is_local_pickup_rate($rate)) {
                // Pickup availability is controlled separately by location pickup settings.
                if ($has_method_restrictions && !$allow_pickup) {
                    unset($rates[$key]);
                }
                continue;
            }

            // $rate is WC_Shipping_Rate
            $instance_id = is_callable([$rate, 'get_instance_id']) ? (int) $rate->get_instance_id() : 0;

            if ($has_zone_restrictions) {
                $zone_id = $this->get_shipping_zone_id_for_rate($rate);
                if ($zone_id === null || !in_array($zone_id, $allowed_zone_ids, true)) {
                    unset($rates[$key]);
                    continue;
                }

                // If a zone has explicit method selections, only those methods are allowed there.
                if (!empty($allowed_methods_by_zone[$zone_id]) && !in_array($instance_id, $allowed_methods_by_zone[$zone_id], true)) {
                    unset($rates[$key]);
                }
            } elseif ($has_method_restrictions) {
                // Fallback for malformed data where methods are stored but no zones are defined.
                $zone_id = $this->get_shipping_zone_id_for_rate($rate);
                if ($zone_id === null || empty($allowed_methods_by_zone[$zone_id]) || !in_array($instance_id, $allowed_methods_by_zone[$zone_id], true)) {
                    unset($rates[$key]);
                }
            }
        }

        return $rates;
    }

    /**
     * Convert shipping-rate monetary amounts to active location currency.
     *
     * @param array $rates
     * @param array $package
     * @return array
     */
    public function convert_package_rate_amounts_for_currency($rates, $package)
    {
        if (empty($rates) || !is_array($rates)) {
            return $rates;
        }

        $cfg = $this->get_location_config();
        $preferred_location_id = isset($cfg['location_id']) ? (int) $cfg['location_id'] : 0;
        $context = function_exists('mulopimfwc_get_runtime_currency_conversion_context')
            ? mulopimfwc_get_runtime_currency_conversion_context($preferred_location_id > 0 ? $preferred_location_id : null)
            : [
                'should_convert' => false,
                'rate' => 1.0,
            ];

        if (empty($context['should_convert']) || !isset($context['rate']) || (float) $context['rate'] <= 0) {
            return $rates;
        }

        $location_id = isset($context['location_id']) ? (int) $context['location_id'] : 0;

        foreach ($rates as $rate_key => $rate) {
            if (!is_object($rate)) {
                continue;
            }

            $rate_hash = spl_object_hash($rate);
            if (isset($this->converted_package_rate_hashes[$rate_hash])) {
                continue;
            }

            $raw_cost = is_callable([$rate, 'get_cost'])
                ? $rate->get_cost()
                : (isset($rate->cost) ? $rate->cost : null);

            $converted_cost = function_exists('mulopimfwc_convert_base_amount_to_runtime_currency')
                ? mulopimfwc_convert_base_amount_to_runtime_currency($raw_cost, $location_id > 0 ? $location_id : null)
                : $raw_cost;

            if (is_numeric($converted_cost)) {
                if (is_callable([$rate, 'set_cost'])) {
                    $rate->set_cost((float) $converted_cost);
                } elseif (isset($rate->cost)) {
                    $rate->cost = (float) $converted_cost;
                }
            }

            $taxes = is_callable([$rate, 'get_taxes'])
                ? (array) $rate->get_taxes()
                : ((isset($rate->taxes) && is_array($rate->taxes)) ? $rate->taxes : []);

            if (!empty($taxes)) {
                foreach ($taxes as $tax_id => $tax_amount) {
                    $converted_tax = function_exists('mulopimfwc_convert_base_amount_to_runtime_currency')
                        ? mulopimfwc_convert_base_amount_to_runtime_currency($tax_amount, $location_id > 0 ? $location_id : null)
                        : $tax_amount;

                    if (is_numeric($converted_tax)) {
                        $taxes[$tax_id] = (float) $converted_tax;
                    }
                }

                if (is_callable([$rate, 'set_taxes'])) {
                    $rate->set_taxes($taxes);
                } elseif (isset($rate->taxes)) {
                    $rate->taxes = $taxes;
                }
            }

            $rates[$rate_key] = $rate;
            $this->converted_package_rate_hashes[$rate_hash] = true;
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
