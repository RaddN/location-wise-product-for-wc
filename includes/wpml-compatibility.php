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

if (!function_exists('mulopimfwc_wpml_get_product_translation_ids')) {
    /**
     * Get every WPML translation ID for a product.
     *
     * @param int $product_id Product ID in any language.
     * @return int[]
     */
    function mulopimfwc_wpml_get_product_translation_ids($product_id)
    {
        $product_id = absint($product_id);
        if (!$product_id || !defined('ICL_SITEPRESS_VERSION')) {
            return $product_id ? [$product_id] : [];
        }

        $canonical_id = mulopimfwc_get_wpml_canonical_inventory_product_id($product_id);
        if (get_post_type($canonical_id) !== 'product') {
            return [];
        }

        $translation_ids = [$canonical_id];
        $trid = apply_filters('wpml_element_trid', null, $canonical_id, 'post_product');
        if (!$trid) {
            return $translation_ids;
        }

        $translations = apply_filters('wpml_get_element_translations', null, $trid, 'post_product');
        if (!is_array($translations)) {
            return $translation_ids;
        }

        foreach ($translations as $translation) {
            $translation_id = is_object($translation) && isset($translation->element_id)
                ? absint($translation->element_id)
                : 0;

            if ($translation_id && get_post_type($translation_id) === 'product') {
                $translation_ids[] = $translation_id;
            }
        }

        return array_values(array_unique(array_filter($translation_ids)));
    }
}

if (!function_exists('mulopimfwc_wpml_sync_product_location_relationships')) {
    /**
     * Copy canonical location relationships to every product translation.
     *
     * Strict catalog filtering is performed in SQL against term relationships,
     * before the get_object_terms fallback above can run. Persisting the shared
     * physical-location relationships keeps translated shop queries accurate.
     *
     * @param int $product_id Product ID in any language.
     * @return void
     */
    function mulopimfwc_wpml_sync_product_location_relationships($product_id)
    {
        static $syncing = false;

        if ($syncing || !defined('ICL_SITEPRESS_VERSION')) {
            return;
        }

        $canonical_id = mulopimfwc_get_wpml_canonical_inventory_product_id($product_id);
        if (!$canonical_id || get_post_type($canonical_id) !== 'product') {
            return;
        }

        $translation_ids = mulopimfwc_wpml_get_product_translation_ids($canonical_id);
        if (count($translation_ids) < 2) {
            return;
        }

        $term_ids = wp_get_object_terms(
            $canonical_id,
            'mulopimfwc_store_location',
            ['fields' => 'ids', 'lang' => 'all']
        );
        if (is_wp_error($term_ids)) {
            return;
        }

        $term_ids = array_values(array_unique(array_map('absint', (array) $term_ids)));
        $syncing = true;

        foreach ($translation_ids as $translation_id) {
            if ($translation_id === $canonical_id) {
                continue;
            }

            wp_set_object_terms(
                $translation_id,
                $term_ids,
                'mulopimfwc_store_location',
                false
            );
        }

        $syncing = false;
    }
}

if (!function_exists('mulopimfwc_wpml_sync_locations_after_term_change')) {
    /**
     * Keep translations synchronized when product locations change.
     *
     * @param int    $object_id Product ID.
     * @param mixed  $terms     Assigned terms.
     * @param int[]  $tt_ids    Term-taxonomy IDs.
     * @param string $taxonomy  Taxonomy name.
     * @return void
     */
    function mulopimfwc_wpml_sync_locations_after_term_change($object_id, $terms, $tt_ids, $taxonomy)
    {
        if ($taxonomy === 'mulopimfwc_store_location') {
            mulopimfwc_wpml_sync_product_location_relationships($object_id);
        }
    }

    add_action('set_object_terms', 'mulopimfwc_wpml_sync_locations_after_term_change', 20, 4);
}

if (!function_exists('mulopimfwc_wpml_sync_locations_after_product_save')) {
    /**
     * Repair relationships when a translated product is created or updated.
     *
     * @param int $product_id Product ID.
     * @return void
     */
    function mulopimfwc_wpml_sync_locations_after_product_save($product_id)
    {
        if (!wp_is_post_revision($product_id) && !wp_is_post_autosave($product_id)) {
            mulopimfwc_wpml_sync_product_location_relationships($product_id);
        }
    }

    add_action('save_post_product', 'mulopimfwc_wpml_sync_locations_after_product_save', 30, 1);
}

if (!function_exists('mulopimfwc_wpml_schedule_location_relationship_sync')) {
    /**
     * Schedule a bounded one-time migration for pre-existing translations.
     *
     * @return void
     */
    function mulopimfwc_wpml_schedule_location_relationship_sync()
    {
        if (
            !defined('ICL_SITEPRESS_VERSION') ||
            get_option('mulopimfwc_wpml_location_relationship_sync_version') === '1'
        ) {
            return;
        }

        $cursor = absint(get_option('mulopimfwc_wpml_location_relationship_sync_cursor', 0));
        if (!wp_next_scheduled('mulopimfwc_wpml_sync_location_relationships_batch', [$cursor])) {
            wp_schedule_single_event(
                time() + 5,
                'mulopimfwc_wpml_sync_location_relationships_batch',
                [$cursor]
            );
        }
    }

    add_action('admin_init', 'mulopimfwc_wpml_schedule_location_relationship_sync');
}

if (!function_exists('mulopimfwc_wpml_sync_location_relationships_batch')) {
    /**
     * Synchronize one bounded batch of existing translated products.
     *
     * @param int $cursor Last processed product ID.
     * @return void
     */
    function mulopimfwc_wpml_sync_location_relationships_batch($cursor = 0)
    {
        if (
            !defined('ICL_SITEPRESS_VERSION') ||
            get_option('mulopimfwc_wpml_location_relationship_sync_version') === '1' ||
            get_transient('mulopimfwc_wpml_location_relationship_sync_lock')
        ) {
            return;
        }

        set_transient('mulopimfwc_wpml_location_relationship_sync_lock', '1', 5 * MINUTE_IN_SECONDS);

        global $wpdb;
        $batch_size = 50;
        $product_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID
                FROM {$wpdb->posts}
                WHERE post_type = %s
                AND post_status NOT IN (%s, %s)
                AND ID > %d
                ORDER BY ID ASC
                LIMIT %d",
                'product',
                'trash',
                'auto-draft',
                absint($cursor),
                $batch_size
            )
        );

        foreach ((array) $product_ids as $product_id) {
            mulopimfwc_wpml_sync_product_location_relationships(absint($product_id));
        }

        if (count($product_ids) === $batch_size) {
            $next_cursor = absint(end($product_ids));
            update_option('mulopimfwc_wpml_location_relationship_sync_cursor', $next_cursor, false);
            wp_schedule_single_event(
                time() + 5,
                'mulopimfwc_wpml_sync_location_relationships_batch',
                [$next_cursor]
            );
        } else {
            update_option('mulopimfwc_wpml_location_relationship_sync_version', '1', false);
            delete_option('mulopimfwc_wpml_location_relationship_sync_cursor');
        }

        delete_transient('mulopimfwc_wpml_location_relationship_sync_lock');
    }

    add_action(
        'mulopimfwc_wpml_sync_location_relationships_batch',
        'mulopimfwc_wpml_sync_location_relationships_batch',
        10,
        1
    );
}
