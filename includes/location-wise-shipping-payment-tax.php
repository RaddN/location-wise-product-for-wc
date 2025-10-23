<?php
if (! defined('ABSPATH')) exit;

/**
 * Runtime filters to enforce location-scoped shipping, payments & tax class
 */
class MULOPIMFWC_Runtime_Filters
{

    /** Cache of current location config for this request */
    protected static $loc_cache = null;

    public function __construct()
    {
        // Shipping: remove disallowed shipping method instances
        add_filter('woocommerce_package_rates', [$this, 'filter_package_rates'], 50, 2);

        // Payment: remove disallowed gateways
        add_filter('woocommerce_available_payment_gateways', [$this, 'filter_payment_gateways'], 50);

        // Tax class: set default tax class from location (product + variations)
        add_filter('woocommerce_product_get_tax_class', [$this, 'filter_product_tax_class'], 50, 2);
        add_filter('woocommerce_product_variation_get_tax_class', [$this, 'filter_product_tax_class'], 50, 2);
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
        return isset($_COOKIE['mulopimfwc_store_location']) ? sanitize_text_field(wp_unslash($_COOKIE['mulopimfwc_store_location'])) : '';
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
        error_log('Active location ID: ' . json_encode($loc_id));
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
     * SHIPPING: Keep only rates whose instance_id is allowed for the location.
     */
    public function filter_package_rates($rates, $package)
    {
        $cfg = $this->get_location_config();
        if (empty($cfg['shipping_instances'])) {
            // No restriction configured -> leave as is
            return $rates;
        }

        foreach ($rates as $key => $rate) {
            // $rate is WC_Shipping_Rate
            $instance_id = is_callable([$rate, 'get_instance_id']) ? (int) $rate->get_instance_id() : 0;

            // Some methods can have instance_id 0 (legacy table rate etc). If 0, require explicit '0' in allowlist.
            if (! in_array($instance_id, $cfg['shipping_instances'], true)) {
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
        error_log('Payment gateways before filter: ' . print_r(array_keys($gateways), true));
        error_log('Location config payments: ' . json_encode($cfg));
        if (empty($cfg['payments'])) {
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
