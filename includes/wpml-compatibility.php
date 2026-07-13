<?php
/**
 * WPML compatibility for shared location inventory.
 *
 * WooCommerce stores each product translation as a separate post. Location
 * inventory, however, represents physical stock and must be shared by every
 * translation of the same product or variation. This file keeps the original
 * WPML product as the canonical owner of location stock, prices, backorders,
 * and location availability.
 *
 * @package MultiLocationProductInventoryManagementPro
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mulopimfwc_is_wpml_location_product_meta_key')) {
    /**
     * Check whether a post meta key belongs to per-location product inventory.
     *
     * @param mixed $meta_key Meta key.
     * @return bool
     */
    function mulopimfwc_is_wpml_location_product_meta_key($meta_key)
    {
        if (!is_string($meta_key)) {
            return false;
        }

        return (bool) preg_match(
            '/^_location_(?:stock|regular_price|sale_price|backorders|disabled)_\d+$/',
            $meta_key
        );
    }
}

if (!function_exists('mulopimfwc_get_wpml_canonical_inventory_product_id')) {
    /**
     * Resolve the original WPML product or variation that owns location data.
     *
     * @param int $object_id Product or variation ID.
     * @return int
     */
    function mulopimfwc_get_wpml_canonical_inventory_product_id($object_id)
    {
        $object_id = absint($object_id);
        if ($object_id <= 0 || !defined('ICL_SITEPRESS_VERSION')) {
            return $object_id;
        }

        $post_type = get_post_type($object_id);
        if (!in_array($post_type, ['product', 'product_variation'], true)) {
            return $object_id;
        }

        $original_id = apply_filters(
            'wpml_original_element_id',
            null,
            $object_id,
            'post_' . $post_type
        );

        return $original_id ? absint($original_id) : $object_id;
    }
}

if (!function_exists('mulopimfwc_wpml_get_location_product_meta')) {
    /**
     * Read location inventory from the canonical WPML product.
     *
     * @param mixed  $value     Short-circuit value.
     * @param int    $object_id Product or variation ID.
     * @param string $meta_key  Meta key.
     * @param bool   $single    Whether one value was requested.
     * @return mixed
     */
    function mulopimfwc_wpml_get_location_product_meta($value, $object_id, $meta_key, $single)
    {
        if (!mulopimfwc_is_wpml_location_product_meta_key($meta_key)) {
            return $value;
        }

        $canonical_id = mulopimfwc_get_wpml_canonical_inventory_product_id($object_id);
        if ($canonical_id === absint($object_id)) {
            return $value;
        }

        return get_post_meta($canonical_id, $meta_key, (bool) $single);
    }

    add_filter('get_post_metadata', 'mulopimfwc_wpml_get_location_product_meta', 10, 4);
}

if (!function_exists('mulopimfwc_wpml_add_location_product_meta')) {
    /**
     * Store newly added location inventory on the canonical WPML product.
     *
     * @param mixed  $check      Short-circuit value.
     * @param int    $object_id  Product or variation ID.
     * @param string $meta_key   Meta key.
     * @param mixed  $meta_value Meta value.
     * @param bool   $unique     Whether the key must be unique.
     * @return mixed
     */
    function mulopimfwc_wpml_add_location_product_meta($check, $object_id, $meta_key, $meta_value, $unique)
    {
        if (!mulopimfwc_is_wpml_location_product_meta_key($meta_key)) {
            return $check;
        }

        $canonical_id = mulopimfwc_get_wpml_canonical_inventory_product_id($object_id);
        if ($canonical_id === absint($object_id)) {
            return $check;
        }

        return add_post_meta($canonical_id, $meta_key, $meta_value, (bool) $unique);
    }

    add_filter('add_post_metadata', 'mulopimfwc_wpml_add_location_product_meta', 10, 5);
}

if (!function_exists('mulopimfwc_wpml_update_location_product_meta')) {
    /**
     * Store updated location inventory on the canonical WPML product.
     *
     * @param mixed  $check      Short-circuit value.
     * @param int    $object_id  Product or variation ID.
     * @param string $meta_key   Meta key.
     * @param mixed  $meta_value Meta value.
     * @param mixed  $prev_value Optional previous value.
     * @return mixed
     */
    function mulopimfwc_wpml_update_location_product_meta($check, $object_id, $meta_key, $meta_value, $prev_value)
    {
        if (!mulopimfwc_is_wpml_location_product_meta_key($meta_key)) {
            return $check;
        }

        $canonical_id = mulopimfwc_get_wpml_canonical_inventory_product_id($object_id);
        if ($canonical_id === absint($object_id)) {
            return $check;
        }

        return update_post_meta($canonical_id, $meta_key, $meta_value, $prev_value);
    }

    add_filter('update_post_metadata', 'mulopimfwc_wpml_update_location_product_meta', 10, 5);
}

if (!function_exists('mulopimfwc_wpml_delete_location_product_meta')) {
    /**
     * Delete location inventory from the canonical WPML product.
     *
     * @param mixed  $delete     Short-circuit value.
     * @param int    $object_id  Product or variation ID.
     * @param string $meta_key   Meta key.
     * @param mixed  $meta_value Optional value to match.
     * @param bool   $delete_all Whether to delete matching metadata for all objects.
     * @return mixed
     */
    function mulopimfwc_wpml_delete_location_product_meta($delete, $object_id, $meta_key, $meta_value, $delete_all)
    {
        if (!mulopimfwc_is_wpml_location_product_meta_key($meta_key)) {
            return $delete;
        }

        $canonical_id = mulopimfwc_get_wpml_canonical_inventory_product_id($object_id);
        if ($canonical_id === absint($object_id)) {
            return $delete;
        }

        return delete_metadata('post', $canonical_id, $meta_key, $meta_value, (bool) $delete_all);
    }

    add_filter('delete_post_metadata', 'mulopimfwc_wpml_delete_location_product_meta', 10, 5);
}

if (!function_exists('mulopimfwc_wpml_include_shared_location_terms')) {
    /**
     * Keep physical store locations visible in every WPML language.
     *
     * @param array $args       Term query arguments.
     * @param array $taxonomies Queried taxonomies.
     * @return array
     */
    function mulopimfwc_wpml_include_shared_location_terms($args, $taxonomies)
    {
        if (
            defined('ICL_SITEPRESS_VERSION') &&
            in_array('mulopimfwc_store_location', (array) $taxonomies, true)
        ) {
            $args['lang'] = 'all';
        }

        return $args;
    }

    add_filter('get_terms_args', 'mulopimfwc_wpml_include_shared_location_terms', 10, 2);
}

if (!function_exists('mulopimfwc_wpml_fallback_to_original_product_locations')) {
    /**
     * Use original-product location assignments for existing translations.
     *
     * This keeps older translated products working without a bulk re-save.
     * Explicit assignments on a translated product continue to win.
     *
     * @param mixed $terms      Retrieved terms.
     * @param array $object_ids Object IDs.
     * @param array $taxonomies Taxonomies.
     * @param array $args       Query arguments.
     * @return mixed
     */
    function mulopimfwc_wpml_fallback_to_original_product_locations($terms, $object_ids, $taxonomies, $args)
    {
        static $resolving = false;

        if (
            $resolving ||
            !defined('ICL_SITEPRESS_VERSION') ||
            is_wp_error($terms) ||
            !empty($terms) ||
            count((array) $object_ids) !== 1 ||
            !in_array('mulopimfwc_store_location', (array) $taxonomies, true)
        ) {
            return $terms;
        }

        $object_id = absint(reset($object_ids));
        $canonical_id = mulopimfwc_get_wpml_canonical_inventory_product_id($object_id);
        if ($canonical_id === $object_id) {
            return $terms;
        }

        $resolving = true;
        $source_terms = wp_get_object_terms($canonical_id, $taxonomies, $args);
        $resolving = false;

        return is_wp_error($source_terms) ? $terms : $source_terms;
    }

    add_filter('get_object_terms', 'mulopimfwc_wpml_fallback_to_original_product_locations', 20, 4);
}
