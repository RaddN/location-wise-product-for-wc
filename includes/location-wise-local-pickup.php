<?php

class MULOPIMFWC_Location_Wise_Local_Pickup
{
    private static $current_location_cache = null;

    public function __construct()
    {
        global $mulopimfwc_options;
        $mulopimfwc_options = get_option('mulopimfwc_display_options');

        $enable_location_pickup = isset($mulopimfwc_options['enable_location_pickup']) && mulopimfwc_premium_feature() ? $mulopimfwc_options['enable_location_pickup'] : 'off';
        if ($enable_location_pickup !== 'on') {
            return;
        }
        add_filter('option_pickup_location_pickup_locations', array($this, 'filter_pickup_locations_by_store'), 999, 1);
    }

    private function norm_pickup($entry)
    {
        $out = ['id' => '', 'name' => ''];
        if (is_array($entry)) {
            $out['id']   = isset($entry['id'])   ? (string)$entry['id']   : '';
            $out['name'] = isset($entry['name']) ? (string)$entry['name'] : '';
        } elseif (is_object($entry)) {
            $out['id']   = isset($entry->id)   ? (string)$entry->id   : '';
            $out['name'] = isset($entry->name) ? (string)$entry->name : '';
        }
        return $out;
    }

    private function get_current_store_location()
    {
        if (self::$current_location_cache !== null) {
            return self::$current_location_cache;
        }

        if (isset($_POST['mulopimfwc_store_location'])) {
            self::$current_location_cache = sanitize_text_field(wp_unslash($_POST['mulopimfwc_store_location']));
            return self::$current_location_cache;
        }

        $cookie_location = mulopimfwc_get_store_location_cookie();
        if (!empty($cookie_location)) {
            self::$current_location_cache = $cookie_location;
            return self::$current_location_cache;
        }

        return '';
    }

    private function get_pickup_locations_for_store($term_id)
    {
        $allowed = get_term_meta($term_id, 'pickup_locations', true);
        if (!is_array($allowed)) $allowed = [];
        $allowed = array_values(array_filter(array_map(function ($v) {
            return is_scalar($v) ? trim((string)$v) : '';
        }, $allowed)));
        return $allowed;
    }

    private function is_pickup_enabled()
    {
        $pickup_settings = get_option('woocommerce_pickup_location_settings');
        return !empty($pickup_settings['enabled']) && $pickup_settings['enabled'] === 'yes';
    }

    public function filter_pickup_locations_by_store($pickup_locations)
    {
        if (!$this->is_pickup_enabled() || !is_array($pickup_locations)) {
            return $pickup_locations;
        }

        $current_location = $this->get_current_store_location();
        if (empty($current_location) || $current_location === 'all-products') {
            return $pickup_locations;
        }

        $location_term = get_term_by('slug', $current_location, 'mulopimfwc_store_location');
        if (!$location_term || is_wp_error($location_term)) {
            return $pickup_locations;
        }

        $allowed = $this->get_pickup_locations_for_store($location_term->term_id);
        if (empty($allowed)) return $pickup_locations;

        $allowed_lookup = array_fill_keys($allowed, true);
        $filtered = [];

        foreach ($pickup_locations as $index => $entry) {
            $norm = $this->norm_pickup($entry);
            $id   = $norm['id'];
            $name = $norm['name'];

            if (($id !== ''   && isset($allowed_lookup[$id])) ||
                ($name !== '' && isset($allowed_lookup[$name]))
            ) {
                $filtered[$index] = $entry;
            }
        }

        return $filtered;
    }
}

new MULOPIMFWC_Location_Wise_Local_Pickup();
