<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Canonical full product + location import/export service for Stock Central.
 */
class MULOPIMFWC_Stock_Central_Import_Export_Service
{
    const SCHEMA_VERSION = '1.0.0';
    const SUPPORTED_SCHEMA_MAJOR = 1;
    const OPTION_JOB_HISTORY = 'mulopimfwc_import_export_job_history';
    const MAX_JOB_HISTORY = 25;
    const SNAPSHOT_TRANSIENT_PREFIX = 'mulopimfwc_ie_snapshot_';

    private $meta_blacklist = array(
        '_edit_lock',
        '_edit_last',
        '_wp_old_slug',
        '_thumbnail_id',
        '_product_attributes',
        '_default_attributes',
        '_downloadable_files',
        '_upsell_ids',
        '_crosssell_ids',
        '_children',
        '_stock_status',
        '_stock',
        '_regular_price',
        '_sale_price',
        '_price',
        '_manage_stock',
        '_backorders',
        '_sold_individually',
        '_tax_status',
        '_tax_class',
        '_product_image_gallery',
        '_download_limit',
        '_download_expiry',
    );

    private $safe_location_term_meta_keys = array(
        'street_address',
        'city',
        'state',
        'postal_code',
        'country',
        'location_aliases',
        'email',
        'phone',
        'low_stock_threshold',
        'out_of_stock_threshold',
        'latitude',
        'longitude',
        'logo_id',
        'gallery_ids',
        'business_hours',
        'shipping_zones',
        'shipping_methods',
        'payment_methods',
        'pickup_locations',
        'location_currency',
        'location_currency_position',
        'location_currency_rate',
        'location_currency_rate_mode',
        'tax_class',
        'display_order',
        'is_active',
    );

    public function handle_export_ajax()
    {
        check_ajax_referer('mulopimfwc_import_export_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to export products.', 'multi-location-product-and-inventory-management'),
            ));
        }

        if (!function_exists('wc_get_product')) {
            wp_send_json_error(array(
                'message' => __('WooCommerce is required for product export.', 'multi-location-product-and-inventory-management'),
            ));
        }

        $options = $this->get_request_options();
        $stats = array();
        $csv = $this->build_canonical_csv_export($options, $stats);

        $filename = 'mulopimfwc-stock-central-full-' . gmdate('Y-m-d-His') . '.csv';
        $file_hash = hash('sha256', $csv);

        $job = $this->record_job('export', 'success', $options, array_merge($stats, array(
            'schema_version' => self::SCHEMA_VERSION,
            'file_hash' => $file_hash,
            'filename' => $filename,
        )));

        wp_send_json_success(array(
            'schema_version' => self::SCHEMA_VERSION,
            'filename' => $filename,
            'file_hash' => $file_hash,
            'csv_base64' => base64_encode($csv),
            'summary' => $stats,
            'job' => $job,
            'job_history' => $this->get_job_history(10),
        ));
    }

    public function handle_import_ajax()
    {
        check_ajax_referer('mulopimfwc_import_export_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to import products.', 'multi-location-product-and-inventory-management'),
            ));
        }

        if (!function_exists('wc_get_product')) {
            wp_send_json_error(array(
                'message' => __('WooCommerce is required for product import.', 'multi-location-product-and-inventory-management'),
            ));
        }

        if (!isset($_FILES['csv_file']) || !isset($_FILES['csv_file']['tmp_name']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(array(
                'message' => __('Please upload a valid CSV file.', 'multi-location-product-and-inventory-management'),
            ));
        }

        $file = $_FILES['csv_file'];
        $max_file_size = (int) apply_filters('mulopimfwc_full_import_max_csv_size', 25 * 1024 * 1024);
        if ((int) $file['size'] > $max_file_size) {
            wp_send_json_error(array(
                'message' => sprintf(
                    __('CSV file is too large. Maximum allowed size is %s.', 'multi-location-product-and-inventory-management'),
                    size_format($max_file_size)
                ),
            ));
        }

        $ext = strtolower((string) pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            wp_send_json_error(array(
                'message' => __('Invalid file type. Please upload a CSV file.', 'multi-location-product-and-inventory-management'),
            ));
        }

        $options = $this->get_request_options();
        $mode = isset($options['mode']) ? (string) $options['mode'] : 'dry_run';
        $confirm = !empty($options['confirmed']);

        if ($mode !== 'dry_run' && !$confirm) {
            wp_send_json_error(array(
                'message' => __('Dry run confirmation is required before executing import.', 'multi-location-product-and-inventory-management'),
            ));
        }

        $csv_parse = $this->parse_csv_file((string) $file['tmp_name']);
        if (!empty($csv_parse['error'])) {
            wp_send_json_error(array('message' => $csv_parse['error']));
        }

        $schema_error = $this->validate_schema($csv_parse['headers'], $csv_parse['rows']);
        if ($schema_error !== '') {
            wp_send_json_error(array('message' => $schema_error));
        }

        $file_contents = file_get_contents((string) $file['tmp_name']);
        $file_hash = $file_contents !== false ? hash('sha256', $file_contents) : '';

        $result = $this->run_import_pipeline($csv_parse['rows'], $options);

        $job_status = empty($result['errors']) ? 'success' : 'completed_with_errors';
        if (!empty($result['fatal_error'])) {
            $job_status = 'failed';
        }

        $job_meta = array(
            'schema_version' => self::SCHEMA_VERSION,
            'file_hash' => $file_hash,
            'mode' => $mode,
            'summary' => $result['summary'],
            'errors' => $result['errors'],
            'warnings' => $result['warnings'],
        );
        if (!empty($result['snapshot_key'])) {
            $job_meta['snapshot_key'] = $result['snapshot_key'];
        }

        $job = $this->record_job('import', $job_status, $options, $job_meta);

        if (!empty($result['fatal_error'])) {
            wp_send_json_error(array(
                'message' => $result['fatal_error'],
                'summary' => $result['summary'],
                'errors' => $result['errors'],
                'warnings' => $result['warnings'],
                'logs' => $result['logs'],
                'failed_rows_csv_base64' => $result['failed_rows_csv_base64'],
                'job' => $job,
                'job_history' => $this->get_job_history(10),
            ));
        }

        wp_send_json_success(array(
            'message' => $mode === 'dry_run'
                ? __('Dry run completed. Review the report before confirming import.', 'multi-location-product-and-inventory-management')
                : __('Import completed.', 'multi-location-product-and-inventory-management'),
            'summary' => $result['summary'],
            'errors' => $result['errors'],
            'warnings' => $result['warnings'],
            'logs' => $result['logs'],
            'failed_rows_csv_base64' => $result['failed_rows_csv_base64'],
            'job' => $job,
            'job_history' => $this->get_job_history(10),
        ));
    }

    public function handle_restore_snapshot_ajax()
    {
        check_ajax_referer('mulopimfwc_import_export_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to restore snapshots.', 'multi-location-product-and-inventory-management'),
            ));
        }

        $job_id = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';
        if ($job_id === '') {
            wp_send_json_error(array(
                'message' => __('Job ID is required.', 'multi-location-product-and-inventory-management'),
            ));
        }

        $snapshot = get_transient(self::SNAPSHOT_TRANSIENT_PREFIX . $job_id);
        if (!is_array($snapshot) || empty($snapshot)) {
            wp_send_json_error(array(
                'message' => __('Snapshot not found or expired.', 'multi-location-product-and-inventory-management'),
            ));
        }

        $result = $this->restore_snapshot($snapshot);
        if (!empty($result['error'])) {
            wp_send_json_error(array('message' => $result['error']));
        }

        wp_send_json_success(array(
            'message' => __('Snapshot restored successfully.', 'multi-location-product-and-inventory-management'),
            'summary' => $result,
        ));
    }

    private function build_canonical_csv_export($options, &$stats)
    {
        $headers = $this->get_canonical_headers();
        $rows = array();
        $exported_at = gmdate('c');
        $source_site = home_url();

        $term_rows_count = 0;
        $product_taxonomies = $this->get_supported_product_taxonomies();
        foreach ($product_taxonomies as $taxonomy) {
            if ($taxonomy === 'mulopimfwc_store_location') {
                continue;
            }
            $terms = get_terms(array(
                'taxonomy' => $taxonomy,
                'hide_empty' => false,
            ));
            if (is_wp_error($terms) || empty($terms)) {
                continue;
            }

            foreach ($terms as $term) {
                if (!$term || is_wp_error($term)) {
                    continue;
                }
                $row = $this->new_row_template();
                $row['schema_version'] = self::SCHEMA_VERSION;
                $row['exported_at'] = $exported_at;
                $row['source_site'] = $source_site;
                $row['row_type'] = 'taxonomy_term';
                $row['row_key'] = 'term:' . $taxonomy . ':' . (string) $term->slug;
                $row['source_term_id'] = (string) $term->term_id;
                $row['taxonomy'] = $taxonomy;
                $row['term_slug'] = (string) $term->slug;
                $row['term_name'] = (string) $term->name;
                $row['term_description'] = (string) $term->description;
                $row['term_parent_slug'] = $term->parent ? (string) $this->get_term_slug($term->parent, $taxonomy) : '';
                $row['term_meta_json'] = wp_json_encode($this->get_sanitized_term_meta($term->term_id));
                $rows[] = $row;
                $term_rows_count++;
            }
        }

        $locations = get_terms(array(
            'taxonomy' => 'mulopimfwc_store_location',
            'hide_empty' => false,
        ));

        $location_rows_count = 0;
        $location_by_id = array();
        if (!is_wp_error($locations) && !empty($locations)) {
            foreach ($locations as $location) {
                if (!$location || is_wp_error($location)) {
                    continue;
                }
                $location_by_id[(int) $location->term_id] = $location;
                $row = $this->new_row_template();
                $row['schema_version'] = self::SCHEMA_VERSION;
                $row['exported_at'] = $exported_at;
                $row['source_site'] = $source_site;
                $row['row_type'] = 'location';
                $row['row_key'] = 'location:' . (string) $location->slug;
                $row['source_term_id'] = (string) $location->term_id;
                $row['location_slug'] = (string) $location->slug;
                $row['location_name'] = (string) $location->name;
                $row['location_description'] = (string) $location->description;
                $row['location_parent_slug'] = $location->parent ? (string) $this->get_term_slug($location->parent, 'mulopimfwc_store_location') : '';
                $row['location_term_meta_json'] = wp_json_encode($this->get_sanitized_term_meta($location->term_id));
                $rows[] = $row;
                $location_rows_count++;
            }
        }

        $batch_size = (int) apply_filters('mulopimfwc_full_export_batch_size', 200);
        if ($batch_size < 25) {
            $batch_size = 25;
        }
        $max_products = (int) apply_filters('mulopimfwc_full_export_max_products', 100000);
        if ($max_products < 1) {
            $max_products = 100000;
        }

        $page = 1;
        $processed_products = 0;
        $product_rows = 0;
        $variation_rows = 0;
        $relationship_rows = 0;
        $location_inventory_rows = 0;
        $media_ref_rows = 0;
        $media_seen = array();

        do {
            $product_ids = get_posts(array(
                'post_type' => 'product',
                'post_status' => array('publish', 'private', 'draft', 'pending', 'future'),
                'fields' => 'ids',
                'orderby' => 'ID',
                'order' => 'ASC',
                'posts_per_page' => $batch_size,
                'paged' => $page,
            ));

            if (empty($product_ids)) {
                break;
            }

            foreach ($product_ids as $product_id) {
                if ($processed_products >= $max_products) {
                    break 2;
                }
                $product = wc_get_product($product_id);
                if (!$product || $product->is_type('variation')) {
                    continue;
                }

                $processed_products++;
                $product_row = $this->build_product_export_row($product, $product_taxonomies, $exported_at, $source_site, $options);
                $rows[] = $product_row;
                $product_rows++;

                $product_key = $product_row['row_key'];
                $product_sku = (string) $product->get_sku();
                $this->append_relationship_rows($rows, $product, $product_key, $exported_at, $source_site, $relationship_rows);
                $this->append_location_inventory_rows($rows, $product->get_id(), $product_sku, $product_key, $location_by_id, $location_inventory_rows, $exported_at, $source_site);
                $this->append_media_rows($rows, $product, 'product', $product_key, $media_seen, $media_ref_rows, $exported_at, $source_site);

                if ($product->is_type('variable')) {
                    foreach ((array) $product->get_children() as $variation_id) {
                        $variation = wc_get_product($variation_id);
                        if (!$variation || !$variation->is_type('variation')) {
                            continue;
                        }
                        $variation_row = $this->build_variation_export_row($variation, $product, $product_key, $exported_at, $source_site, $options);
                        $rows[] = $variation_row;
                        $variation_rows++;

                        $variation_key = $variation_row['row_key'];
                        $variation_sku = (string) $variation->get_sku();
                        $this->append_location_inventory_rows($rows, $variation->get_id(), $variation_sku, $variation_key, $location_by_id, $location_inventory_rows, $exported_at, $source_site);
                        $this->append_media_rows($rows, $variation, 'variation', $variation_key, $media_seen, $media_ref_rows, $exported_at, $source_site);
                    }
                }
            }

            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
            $page++;
        } while (!empty($product_ids));

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            $stats = array();
            return '';
        }
        fputcsv($stream, $headers);
        foreach ($rows as $row) {
            $line = array();
            foreach ($headers as $header) {
                $line[] = isset($row[$header]) ? $row[$header] : '';
            }
            fputcsv($stream, $line);
        }
        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        $stats = array(
            'schema_version' => self::SCHEMA_VERSION,
            'rows_total' => count($rows),
            'products' => $product_rows,
            'variations' => $variation_rows,
            'taxonomy_terms' => $term_rows_count,
            'locations' => $location_rows_count,
            'relationships' => $relationship_rows,
            'location_inventory' => $location_inventory_rows,
            'media_refs' => $media_ref_rows,
            'processed_products' => $processed_products,
        );

        return $csv;
    }

    private function build_product_export_row($product, $product_taxonomies, $exported_at, $source_site, $options)
    {
        $row = $this->new_row_template();
        $product_id = (int) $product->get_id();
        $sku = (string) $product->get_sku();
        $slug = (string) get_post_field('post_name', $product_id);
        $product_key = $sku !== '' ? 'product:' . $sku : 'product:id:' . $product_id;

        $downloads = array();
        if ($product->is_downloadable()) {
            foreach ($product->get_downloads() as $download_id => $download) {
                if (!is_object($download) || !method_exists($download, 'get_file')) {
                    continue;
                }
                $downloads[] = array(
                    'id' => (string) $download_id,
                    'name' => (string) $download->get_name(),
                    'file' => (string) $download->get_file(),
                );
            }
        }

        $taxonomies_payload = array();
        foreach ($product_taxonomies as $taxonomy) {
            if ($taxonomy === 'product_type') {
                continue;
            }
            $term_slugs = wp_get_post_terms($product_id, $taxonomy, array('fields' => 'slugs'));
            if (!is_wp_error($term_slugs)) {
                $taxonomies_payload[$taxonomy] = array_values(array_filter(array_map('strval', (array) $term_slugs)));
            }
        }

        $image_src = '';
        $image_id = (int) $product->get_image_id();
        if ($image_id > 0) {
            $image_url = wp_get_attachment_url($image_id);
            if ($image_url) {
                $image_src = (string) $image_url;
            }
        }

        $gallery_sources = array();
        foreach ((array) $product->get_gallery_image_ids() as $gallery_id) {
            $gallery_id = (int) $gallery_id;
            if ($gallery_id <= 0) {
                continue;
            }
            $url = wp_get_attachment_url($gallery_id);
            if ($url) {
                $gallery_sources[] = (string) $url;
            }
        }

        $custom_meta = $this->collect_custom_meta_for_export($product_id, $options);

        $row['schema_version'] = self::SCHEMA_VERSION;
        $row['exported_at'] = $exported_at;
        $row['source_site'] = $source_site;
        $row['row_type'] = 'product';
        $row['row_key'] = $product_key;
        $row['source_product_id'] = (string) $product_id;
        $row['product_type'] = (string) $product->get_type();
        $row['sku'] = $sku;
        $row['slug'] = $slug;
        $row['name'] = (string) $product->get_name();
        $row['status'] = (string) $product->get_status();
        $row['catalog_visibility'] = (string) $product->get_catalog_visibility();
        $row['featured'] = $product->get_featured() ? 'yes' : 'no';
        $row['menu_order'] = (string) $product->get_menu_order();
        $row['description'] = (string) $product->get_description();
        $row['short_description'] = (string) $product->get_short_description();
        $row['regular_price'] = (string) $product->get_regular_price();
        $row['sale_price'] = (string) $product->get_sale_price();
        $row['price'] = (string) $product->get_price();
        $row['manage_stock'] = $product->get_manage_stock() ? 'yes' : 'no';
        $row['stock'] = $product->get_stock_quantity() !== null ? (string) $product->get_stock_quantity() : '';
        $row['stock_status'] = (string) $product->get_stock_status();
        $row['backorders'] = (string) $product->get_backorders();
        $row['sold_individually'] = $product->get_sold_individually() ? 'yes' : 'no';
        $row['virtual'] = $product->get_virtual() ? 'yes' : 'no';
        $row['downloadable'] = $product->is_downloadable() ? 'yes' : 'no';
        $row['weight'] = (string) $product->get_weight();
        $row['length'] = (string) $product->get_length();
        $row['width'] = (string) $product->get_width();
        $row['height'] = (string) $product->get_height();
        $row['shipping_class'] = (string) $product->get_shipping_class();
        $row['tax_status'] = (string) $product->get_tax_status();
        $row['tax_class'] = (string) $product->get_tax_class();
        $row['purchase_price'] = (string) get_post_meta($product_id, '_purchase_price', true);
        $row['purchase_quantity'] = (string) get_post_meta($product_id, '_purchase_quantity', true);
        $row['attributes_json'] = wp_json_encode($this->normalize_product_attributes_for_export($product));
        $row['default_attributes_json'] = wp_json_encode($product->is_type('variable') ? $product->get_default_attributes() : array());
        $row['taxonomies_json'] = wp_json_encode($taxonomies_payload);
        $row['image_src'] = $image_src;
        $row['gallery_src'] = wp_json_encode($gallery_sources);
        $row['upsell_skus'] = $this->ids_to_sku_csv($product->get_upsell_ids());
        $row['cross_sell_skus'] = $this->ids_to_sku_csv($product->get_cross_sell_ids());

        if ($product->is_type('external')) {
            $row['external_url'] = (string) $product->get_product_url();
            $row['button_text'] = (string) $product->get_button_text();
        }

        if ($product->is_downloadable()) {
            $row['download_limit'] = (string) $product->get_download_limit();
            $row['download_expiry'] = (string) $product->get_download_expiry();
            $row['downloads_json'] = wp_json_encode($downloads);
        }

        if ($product->is_type('grouped')) {
            $row['grouped_child_skus'] = $this->ids_to_sku_csv($product->get_children());
        }

        if (!empty($custom_meta)) {
            $row['custom_meta_json'] = wp_json_encode($custom_meta);
        }

        return $row;
    }

    private function build_variation_export_row($variation, $parent_product, $parent_key, $exported_at, $source_site, $options)
    {
        $row = $this->new_row_template();
        $variation_id = (int) $variation->get_id();
        $parent_id = (int) $parent_product->get_id();
        $sku = (string) $variation->get_sku();
        $variation_key = $sku !== '' ? 'variation:' . $sku : 'variation:id:' . $variation_id;

        $row['schema_version'] = self::SCHEMA_VERSION;
        $row['exported_at'] = $exported_at;
        $row['source_site'] = $source_site;
        $row['row_type'] = 'variation';
        $row['row_key'] = $variation_key;
        $row['parent_key'] = $parent_key;
        $row['source_product_id'] = (string) $parent_id;
        $row['source_variation_id'] = (string) $variation_id;
        $row['product_type'] = 'variation';
        $row['sku'] = $sku;
        $row['name'] = (string) $variation->get_name();
        $row['status'] = $variation->get_status() ? (string) $variation->get_status() : 'publish';
        $row['menu_order'] = (string) $variation->get_menu_order();
        $row['regular_price'] = (string) $variation->get_regular_price();
        $row['sale_price'] = (string) $variation->get_sale_price();
        $row['price'] = (string) $variation->get_price();
        $row['manage_stock'] = $variation->get_manage_stock() ? 'yes' : 'no';
        $row['stock'] = $variation->get_stock_quantity() !== null ? (string) $variation->get_stock_quantity() : '';
        $row['stock_status'] = (string) $variation->get_stock_status();
        $row['backorders'] = (string) $variation->get_backorders();
        $row['virtual'] = $variation->get_virtual() ? 'yes' : 'no';
        $row['downloadable'] = $variation->is_downloadable() ? 'yes' : 'no';
        $row['weight'] = (string) $variation->get_weight();
        $row['length'] = (string) $variation->get_length();
        $row['width'] = (string) $variation->get_width();
        $row['height'] = (string) $variation->get_height();
        $row['tax_status'] = (string) $variation->get_tax_status();
        $row['tax_class'] = (string) $variation->get_tax_class();
        $row['purchase_price'] = (string) get_post_meta($variation_id, '_purchase_price', true);
        $row['attributes_json'] = wp_json_encode($variation->get_attributes());

        $image_id = (int) $variation->get_image_id();
        if ($image_id > 0) {
            $url = wp_get_attachment_url($image_id);
            if ($url) {
                $row['image_src'] = (string) $url;
            }
        }

        if ($variation->is_downloadable()) {
            $downloads = array();
            foreach ($variation->get_downloads() as $download_id => $download) {
                if (!is_object($download) || !method_exists($download, 'get_file')) {
                    continue;
                }
                $downloads[] = array(
                    'id' => (string) $download_id,
                    'name' => (string) $download->get_name(),
                    'file' => (string) $download->get_file(),
                );
            }
            $row['download_limit'] = (string) $variation->get_download_limit();
            $row['download_expiry'] = (string) $variation->get_download_expiry();
            $row['downloads_json'] = wp_json_encode($downloads);
        }

        $custom_meta = $this->collect_custom_meta_for_export($variation_id, $options);
        if (!empty($custom_meta)) {
            $row['custom_meta_json'] = wp_json_encode($custom_meta);
        }

        return $row;
    }

    private function append_relationship_rows(&$rows, $product, $product_key, $exported_at, $source_site, &$counter)
    {
        $relationships = array(
            'upsell' => $product->get_upsell_ids(),
            'cross_sell' => $product->get_cross_sell_ids(),
        );
        if ($product->is_type('grouped')) {
            $relationships['grouped_children'] = $product->get_children();
        }

        foreach ($relationships as $type => $ids) {
            $related_skus = $this->ids_to_skus($ids);
            if (empty($related_skus)) {
                continue;
            }
            $row = $this->new_row_template();
            $row['schema_version'] = self::SCHEMA_VERSION;
            $row['exported_at'] = $exported_at;
            $row['source_site'] = $source_site;
            $row['row_type'] = 'relationship';
            $row['row_key'] = 'relationship:' . $type . ':' . $product_key;
            $row['parent_key'] = $product_key;
            $row['sku'] = (string) $product->get_sku();
            $row['relationship_type'] = $type;
            $row['related_skus'] = implode('|', $related_skus);
            $rows[] = $row;
            $counter++;
        }
    }

    private function append_location_inventory_rows(&$rows, $item_id, $item_sku, $item_row_key, $location_by_id, &$counter, $exported_at, $source_site)
    {
        $meta = get_post_meta((int) $item_id);
        if (!is_array($meta)) {
            $meta = array();
        }

        $location_meta_map = array();
        foreach ($meta as $meta_key => $values) {
            if (!preg_match('/^_location_(.+)_([0-9]+)$/', (string) $meta_key, $matches)) {
                continue;
            }
            $base_key = sanitize_key((string) $matches[1]);
            $location_id = (int) $matches[2];
            if ($base_key === '' || $location_id <= 0) {
                continue;
            }

            if (!isset($location_meta_map[$location_id])) {
                $location_meta_map[$location_id] = array();
            }

            $first = '';
            if (is_array($values) && array_key_exists(0, $values)) {
                $first = maybe_unserialize($values[0]);
            }
            $location_meta_map[$location_id][$base_key] = is_array($first) || is_object($first) ? wp_json_encode($first) : (string) $first;
        }

        $assigned_location_slugs = wp_get_post_terms((int) $item_id, 'mulopimfwc_store_location', array('fields' => 'slugs'));
        if (is_wp_error($assigned_location_slugs)) {
            $assigned_location_slugs = array();
        }
        $location_ids_from_assignment = array();
        foreach ((array) $assigned_location_slugs as $slug) {
            foreach ($location_by_id as $location_id => $location_term) {
                if ((string) $location_term->slug === (string) $slug) {
                    $location_ids_from_assignment[] = (int) $location_id;
                    break;
                }
            }
        }

        $candidate_location_ids = array_unique(array_merge(
            array_map('intval', array_keys($location_meta_map)),
            array_map('intval', $location_ids_from_assignment)
        ));

        foreach ($candidate_location_ids as $location_id) {
            if (!isset($location_by_id[$location_id])) {
                continue;
            }
            $location_term = $location_by_id[$location_id];
            $payload = isset($location_meta_map[$location_id]) ? (array) $location_meta_map[$location_id] : array();

            $row = $this->new_row_template();
            $row['schema_version'] = self::SCHEMA_VERSION;
            $row['exported_at'] = $exported_at;
            $row['source_site'] = $source_site;
            $row['row_type'] = 'location_inventory';
            $row['row_key'] = 'location_inventory:' . $item_row_key . ':' . (string) $location_term->slug;
            $row['parent_key'] = $item_row_key;
            $row['sku'] = (string) $item_sku;
            $row['location_slug'] = (string) $location_term->slug;
            $row['location_inventory_json'] = wp_json_encode($payload);
            $rows[] = $row;
            $counter++;
        }
    }

    private function append_media_rows(&$rows, $product, $context, $parent_key, &$media_seen, &$counter, $exported_at, $source_site)
    {
        $media_items = array();

        $image_id = (int) $product->get_image_id();
        if ($image_id > 0) {
            $url = wp_get_attachment_url($image_id);
            if ($url) {
                $media_items[] = array(
                    'url' => (string) $url,
                    'context' => $context . '_featured',
                    'alt' => (string) get_post_meta($image_id, '_wp_attachment_image_alt', true),
                );
            }
        }

        foreach ((array) $product->get_gallery_image_ids() as $gallery_id) {
            $gallery_id = (int) $gallery_id;
            if ($gallery_id <= 0) {
                continue;
            }
            $url = wp_get_attachment_url($gallery_id);
            if ($url) {
                $media_items[] = array(
                    'url' => (string) $url,
                    'context' => $context . '_gallery',
                    'alt' => (string) get_post_meta($gallery_id, '_wp_attachment_image_alt', true),
                );
            }
        }

        foreach ($product->get_downloads() as $download) {
            if (!is_object($download) || !method_exists($download, 'get_file')) {
                continue;
            }
            $file = (string) $download->get_file();
            if ($file === '') {
                continue;
            }
            $media_items[] = array(
                'url' => $file,
                'context' => $context . '_download',
                'alt' => '',
            );
        }

        foreach ($media_items as $media) {
            $hash = md5((string) $media['url'] . '|' . (string) $media['context']);
            if (isset($media_seen[$hash])) {
                continue;
            }
            $media_seen[$hash] = true;

            $row = $this->new_row_template();
            $row['schema_version'] = self::SCHEMA_VERSION;
            $row['exported_at'] = $exported_at;
            $row['source_site'] = $source_site;
            $row['row_type'] = 'media_ref';
            $row['row_key'] = 'media:' . $hash;
            $row['parent_key'] = $parent_key;
            $row['media_url'] = (string) $media['url'];
            $row['media_context'] = (string) $media['context'];
            $row['media_alt'] = (string) $media['alt'];
            $rows[] = $row;
            $counter++;
        }
    }

    private function parse_csv_file($file_path)
    {
        $handle = fopen($file_path, 'r');
        if ($handle === false) {
            return array(
                'error' => __('Could not read uploaded CSV file.', 'multi-location-product-and-inventory-management'),
                'headers' => array(),
                'rows' => array(),
            );
        }

        $headers = fgetcsv($handle);
        if ($headers === false || !is_array($headers) || empty($headers)) {
            fclose($handle);
            return array(
                'error' => __('Invalid CSV format. Missing header row.', 'multi-location-product-and-inventory-management'),
                'headers' => array(),
                'rows' => array(),
            );
        }

        $headers = array_map(function ($header) {
            $header = str_replace("\xEF\xBB\xBF", '', (string) $header);
            return trim((string) $header);
        }, $headers);

        $max_rows = (int) apply_filters('mulopimfwc_full_import_max_rows', 150000);
        if ($max_rows < 1) {
            $max_rows = 150000;
        }

        $rows = array();
        $line = 1;
        while (($raw_row = fgetcsv($handle)) !== false) {
            $line++;
            if ($line > $max_rows + 1) {
                fclose($handle);
                return array(
                    'error' => sprintf(__('CSV row limit exceeded. Maximum rows allowed: %d.', 'multi-location-product-and-inventory-management'), $max_rows),
                    'headers' => $headers,
                    'rows' => array(),
                );
            }
            if ($raw_row === array(null) || $raw_row === false) {
                continue;
            }
            if (count($raw_row) < count($headers)) {
                $raw_row = array_pad($raw_row, count($headers), '');
            } elseif (count($raw_row) > count($headers)) {
                $raw_row = array_slice($raw_row, 0, count($headers));
            }
            $assoc = array_combine($headers, $raw_row);
            if (!is_array($assoc)) {
                continue;
            }
            $assoc['__line'] = $line;
            $rows[] = $assoc;
        }
        fclose($handle);

        return array(
            'error' => '',
            'headers' => $headers,
            'rows' => $rows,
        );
    }

    private function validate_schema($headers, $rows)
    {
        $required = array('schema_version', 'row_type', 'row_key');
        foreach ($required as $column) {
            if (!in_array($column, $headers, true)) {
                return sprintf(__('Invalid canonical CSV format. Missing required column: %s', 'multi-location-product-and-inventory-management'), $column);
            }
        }

        $version = '';
        foreach ($rows as $row) {
            if (!empty($row['schema_version'])) {
                $version = (string) $row['schema_version'];
                break;
            }
        }

        if ($version === '') {
            return __('Missing schema_version value in CSV rows.', 'multi-location-product-and-inventory-management');
        }
        $major = (int) explode('.', $version)[0];
        if ($major !== (int) self::SUPPORTED_SCHEMA_MAJOR) {
            return sprintf(__('Unsupported schema version %s. Supported major version is %d.', 'multi-location-product-and-inventory-management'), $version, self::SUPPORTED_SCHEMA_MAJOR);
        }
        return '';
    }

    private function run_import_pipeline($rows, $options)
    {
        $start = microtime(true);

        $state = array(
            'summary' => array(
                'rows_total' => count($rows),
                'rows_processed' => 0,
                'rows_failed' => 0,
                'products_created' => 0,
                'products_updated' => 0,
                'variations_created' => 0,
                'variations_updated' => 0,
                'terms_created' => 0,
                'terms_updated' => 0,
                'locations_created' => 0,
                'locations_updated' => 0,
                'categories_created' => 0,
                'tags_created' => 0,
                'brands_created' => 0,
                'attributes_created' => 0,
                'relationships_updated' => 0,
                'location_inventory_updated' => 0,
                'media_sideloaded' => 0,
                'media_mapped' => 0,
                'dry_run' => false,
            ),
            'errors' => array(),
            'warnings' => array(),
            'failed_rows' => array(),
            'row_map' => array(),
            'sku_map' => array(),
            'variation_sku_map' => array(),
            'term_map' => array(),
            'location_map' => array(),
            'media_map' => array(),
            'snapshot' => array(
                'products' => array(),
                'terms' => array(),
            ),
            'snapshot_limit_reached' => false,
            'fatal_error' => '',
            'logs' => array(),
            'log_truncated' => false,
        );

        $runtime = $this->sanitize_import_runtime($options);
        $state['summary']['dry_run'] = $runtime['mode'] === 'dry_run';
        $grouped = $this->group_rows_by_type($rows);
        $row_type_counts = array();
        foreach ($grouped as $row_type => $items) {
            $row_type_counts[$row_type] = count($items);
        }

        $this->push_import_log(
            $state,
            sprintf(
                __('Import pipeline started. Mode: %s', 'multi-location-product-and-inventory-management'),
                str_replace('_', ' ', (string) $runtime['mode'])
            )
        );
        $this->push_import_log(
            $state,
            sprintf(
                __('Total CSV rows: %d', 'multi-location-product-and-inventory-management'),
                (int) $state['summary']['rows_total']
            )
        );
        if (!empty($row_type_counts)) {
            $parts = array();
            foreach ($row_type_counts as $row_type => $count) {
                $parts[] = sanitize_key((string) $row_type) . ': ' . (int) $count;
            }
            $this->push_import_log(
                $state,
                sprintf(
                    __('Row type distribution: %s', 'multi-location-product-and-inventory-management'),
                    implode(', ', $parts)
                )
            );
        }

        try {
            $before = $state['summary'];
            $before_errors = count($state['errors']);
            $before_warnings = count($state['warnings']);
            $this->push_import_log($state, __('Pass 1/6: taxonomies and locations', 'multi-location-product-and-inventory-management'));
            $this->pass_terms_and_locations($grouped, $runtime, $state);

            $this->push_import_log(
                $state,
                sprintf(
                    __('Pass 1/6 complete. Rows +%1$d | Terms +%2$d new, +%3$d updated | Locations +%4$d new, +%5$d updated | Errors +%6$d | Warnings +%7$d', 'multi-location-product-and-inventory-management'),
                    (int) $state['summary']['rows_processed'] - (int) $before['rows_processed'],
                    (int) $state['summary']['terms_created'] - (int) $before['terms_created'],
                    (int) $state['summary']['terms_updated'] - (int) $before['terms_updated'],
                    (int) $state['summary']['locations_created'] - (int) $before['locations_created'],
                    (int) $state['summary']['locations_updated'] - (int) $before['locations_updated'],
                    count($state['errors']) - $before_errors,
                    count($state['warnings']) - $before_warnings
                ),
                (count($state['errors']) - $before_errors) > 0 ? 'warning' : 'success'
            );

            $before = $state['summary'];
            $before_errors = count($state['errors']);
            $before_warnings = count($state['warnings']);
            $this->push_import_log($state, __('Pass 2/6: media reference mapping', 'multi-location-product-and-inventory-management'));
            $this->pass_media_refs($grouped, $runtime, $state);

            $this->push_import_log(
                $state,
                sprintf(
                    __('Pass 2/6 complete. Rows +%1$d | Media mapped +%2$d | Media sideloaded +%3$d | Errors +%4$d | Warnings +%5$d', 'multi-location-product-and-inventory-management'),
                    (int) $state['summary']['rows_processed'] - (int) $before['rows_processed'],
                    (int) $state['summary']['media_mapped'] - (int) $before['media_mapped'],
                    (int) $state['summary']['media_sideloaded'] - (int) $before['media_sideloaded'],
                    count($state['errors']) - $before_errors,
                    count($state['warnings']) - $before_warnings
                ),
                (count($state['errors']) - $before_errors) > 0 ? 'warning' : 'success'
            );

            $before = $state['summary'];
            $before_errors = count($state['errors']);
            $before_warnings = count($state['warnings']);
            $this->push_import_log($state, __('Pass 3/6: parent/simple/grouped/external products', 'multi-location-product-and-inventory-management'));
            $this->pass_products($grouped, $runtime, $state);

            $this->push_import_log(
                $state,
                sprintf(
                    __('Pass 3/6 complete. Rows +%1$d | Products +%2$d created, +%3$d updated | Errors +%4$d | Warnings +%5$d', 'multi-location-product-and-inventory-management'),
                    (int) $state['summary']['rows_processed'] - (int) $before['rows_processed'],
                    (int) $state['summary']['products_created'] - (int) $before['products_created'],
                    (int) $state['summary']['products_updated'] - (int) $before['products_updated'],
                    count($state['errors']) - $before_errors,
                    count($state['warnings']) - $before_warnings
                ),
                (count($state['errors']) - $before_errors) > 0 ? 'warning' : 'success'
            );

            $before = $state['summary'];
            $before_errors = count($state['errors']);
            $before_warnings = count($state['warnings']);
            $this->push_import_log($state, __('Pass 4/6: variations', 'multi-location-product-and-inventory-management'));
            $this->pass_variations($grouped, $runtime, $state);

            $this->push_import_log(
                $state,
                sprintf(
                    __('Pass 4/6 complete. Rows +%1$d | Variations +%2$d created, +%3$d updated | Errors +%4$d | Warnings +%5$d', 'multi-location-product-and-inventory-management'),
                    (int) $state['summary']['rows_processed'] - (int) $before['rows_processed'],
                    (int) $state['summary']['variations_created'] - (int) $before['variations_created'],
                    (int) $state['summary']['variations_updated'] - (int) $before['variations_updated'],
                    count($state['errors']) - $before_errors,
                    count($state['warnings']) - $before_warnings
                ),
                (count($state['errors']) - $before_errors) > 0 ? 'warning' : 'success'
            );

            $before = $state['summary'];
            $before_errors = count($state['errors']);
            $before_warnings = count($state['warnings']);
            $this->push_import_log($state, __('Pass 5/6: relationships and linked product references', 'multi-location-product-and-inventory-management'));
            $this->pass_relationships($grouped, $runtime, $state);

            $this->push_import_log(
                $state,
                sprintf(
                    __('Pass 5/6 complete. Rows +%1$d | Relationships updated +%2$d | Errors +%3$d | Warnings +%4$d', 'multi-location-product-and-inventory-management'),
                    (int) $state['summary']['rows_processed'] - (int) $before['rows_processed'],
                    (int) $state['summary']['relationships_updated'] - (int) $before['relationships_updated'],
                    count($state['errors']) - $before_errors,
                    count($state['warnings']) - $before_warnings
                ),
                (count($state['errors']) - $before_errors) > 0 ? 'warning' : 'success'
            );

            $before = $state['summary'];
            $before_errors = count($state['errors']);
            $before_warnings = count($state['warnings']);
            $this->push_import_log($state, __('Pass 6/6: location inventory and assignment sync', 'multi-location-product-and-inventory-management'));
            $this->pass_location_inventory($grouped, $runtime, $state);

            $this->push_import_log(
                $state,
                sprintf(
                    __('Pass 6/6 complete. Rows +%1$d | Location inventory updated +%2$d | Errors +%3$d | Warnings +%4$d', 'multi-location-product-and-inventory-management'),
                    (int) $state['summary']['rows_processed'] - (int) $before['rows_processed'],
                    (int) $state['summary']['location_inventory_updated'] - (int) $before['location_inventory_updated'],
                    count($state['errors']) - $before_errors,
                    count($state['warnings']) - $before_warnings
                ),
                (count($state['errors']) - $before_errors) > 0 ? 'warning' : 'success'
            );

            if ($runtime['mode'] !== 'dry_run') {
                $this->push_import_log($state, __('Finalizing import and clearing caches...', 'multi-location-product-and-inventory-management'));
                $this->finalize_import($state);
                $this->push_import_log($state, __('Finalization complete.', 'multi-location-product-and-inventory-management'), 'success');
            }
        } catch (Exception $e) {
            $state['fatal_error'] = $e->getMessage();
            $this->push_import_log(
                $state,
                sprintf(
                    __('Fatal import error: %s', 'multi-location-product-and-inventory-management'),
                    $state['fatal_error']
                ),
                'error'
            );
        }

        $state['summary']['duration_seconds'] = round(microtime(true) - $start, 3);
        $this->push_import_log(
            $state,
            sprintf(
                __('Import pipeline finished in %s seconds. Processed rows: %d, failed rows: %d', 'multi-location-product-and-inventory-management'),
                number_format((float) $state['summary']['duration_seconds'], 3, '.', ''),
                (int) $state['summary']['rows_processed'],
                (int) $state['summary']['rows_failed']
            ),
            empty($state['fatal_error']) && empty($state['errors']) ? 'success' : 'warning'
        );
        $failed_rows_csv = $this->build_failed_rows_csv($state['failed_rows']);

        $snapshot_key = '';
        if (($runtime['mode'] !== 'dry_run') && (!empty($state['snapshot']['products']) || !empty($state['snapshot']['terms']))) {
            $snapshot_key = wp_generate_uuid4();
            set_transient(self::SNAPSHOT_TRANSIENT_PREFIX . $snapshot_key, $state['snapshot'], DAY_IN_SECONDS * 7);
        }

        return array(
            'summary' => $state['summary'],
            'errors' => $state['errors'],
            'warnings' => $state['warnings'],
            'logs' => $state['logs'],
            'failed_rows_csv_base64' => $failed_rows_csv !== '' ? base64_encode($failed_rows_csv) : '',
            'snapshot_key' => $snapshot_key,
            'fatal_error' => $state['fatal_error'],
        );
    }

    private function pass_terms_and_locations($grouped, $runtime, &$state)
    {
        $taxonomy_rows = isset($grouped['taxonomy_term']) ? $grouped['taxonomy_term'] : array();
        $location_rows = isset($grouped['location']) ? $grouped['location'] : array();
        $pending_term_parents = array();
        $taxonomy_total = count($taxonomy_rows);
        $location_total = count($location_rows);

        if ($taxonomy_total > 0) {
            $this->push_import_log(
                $state,
                sprintf(
                    __('Taxonomy rows queued: %d', 'multi-location-product-and-inventory-management'),
                    $taxonomy_total
                )
            );
        }

        foreach ($taxonomy_rows as $index => $row) {
            $line = isset($row['__line']) ? (int) $row['__line'] : 0;
            $taxonomy = sanitize_key((string) $row['taxonomy']);
            $slug = sanitize_title((string) $row['term_slug']);
            $name = (string) $row['term_name'];
            $parent_slug = sanitize_title((string) $row['term_parent_slug']);
            $description = (string) $row['term_description'];

            if ($taxonomy === '' || $slug === '') {
                $this->row_error($state, $row, sprintf(__('Line %d: taxonomy and term_slug are required for taxonomy_term rows.', 'multi-location-product-and-inventory-management'), $line));
                continue;
            }
            if (!$this->ensure_import_taxonomy_exists($taxonomy, $runtime, $state)) {
                $this->row_error($state, $row, sprintf(__('Line %d: taxonomy %s does not exist on target site.', 'multi-location-product-and-inventory-management'), $line, $taxonomy));
                continue;
            }

            $term = get_term_by('slug', $slug, $taxonomy);
            $term_id = 0;
            if ($term && !is_wp_error($term)) {
                $term_id = (int) $term->term_id;
                $state['summary']['terms_updated']++;
            } else {
                if ($runtime['mode'] === 'update_only') {
                    $this->row_error($state, $row, sprintf(__('Line %d: term %s in %s not found (update-only mode).', 'multi-location-product-and-inventory-management'), $line, $slug, $taxonomy));
                    continue;
                }

                if ($runtime['mode'] !== 'dry_run') {
                    $created = wp_insert_term($name !== '' ? $name : $slug, $taxonomy, array(
                        'slug' => $slug,
                        'description' => $description,
                    ));
                    if (is_wp_error($created)) {
                        $this->row_error($state, $row, sprintf(__('Line %d: failed to create term %s (%s).', 'multi-location-product-and-inventory-management'), $line, $slug, $created->get_error_message()));
                        continue;
                    }
                    $term_id = (int) $created['term_id'];
                } else {
                    $term_id = -1 * (int) max(1, $state['summary']['terms_created'] + 1);
                }
                $state['summary']['terms_created']++;
                $this->increment_created_term_summary($state, $taxonomy);
            }

            $state['term_map'][$taxonomy . ':' . $slug] = $term_id;
            $state['row_map'][(string) $row['row_key']] = array('entity' => 'term', 'id' => $term_id, 'taxonomy' => $taxonomy, 'slug' => $slug);

            if ($parent_slug !== '') {
                $pending_term_parents[] = array(
                    'taxonomy' => $taxonomy,
                    'slug' => $slug,
                    'parent_slug' => $parent_slug,
                );
            }

            $term_meta = $this->decode_json_field($row['term_meta_json']);
            if (!empty($term_meta) && $runtime['mode'] !== 'dry_run' && $term_id > 0) {
                $this->capture_term_snapshot_once($term_id, $state);
                foreach ($term_meta as $meta_key => $meta_value) {
                    $meta_key = sanitize_key((string) $meta_key);
                    if ($meta_key !== '') {
                        update_term_meta($term_id, $meta_key, $meta_value);
                    }
                }
            }

            $state['summary']['rows_processed']++;

            $position = (int) $index + 1;
            if ($taxonomy_total > 0 && ($position === $taxonomy_total || ($position % 200) === 0)) {
                $this->push_import_log(
                    $state,
                    sprintf(
                        __('Taxonomy progress: %1$d/%2$d', 'multi-location-product-and-inventory-management'),
                        $position,
                        $taxonomy_total
                    )
                );
            }
        }

        foreach ($pending_term_parents as $pending) {
            $taxonomy = $pending['taxonomy'];
            $child_key = $taxonomy . ':' . $pending['slug'];
            $parent_key = $taxonomy . ':' . $pending['parent_slug'];
            if (!isset($state['term_map'][$child_key])) {
                continue;
            }
            $child_id = (int) $state['term_map'][$child_key];
            if ($child_id <= 0) {
                continue;
            }
            $parent_id = 0;
            if (isset($state['term_map'][$parent_key])) {
                $parent_id = (int) $state['term_map'][$parent_key];
            } else {
                $parent_term = get_term_by('slug', $pending['parent_slug'], $taxonomy);
                if ($parent_term && !is_wp_error($parent_term)) {
                    $parent_id = (int) $parent_term->term_id;
                }
            }
            if ($parent_id > 0 && $runtime['mode'] !== 'dry_run') {
                wp_update_term($child_id, $taxonomy, array('parent' => $parent_id));
            }
        }

        $pending_location_parents = array();
        if ($location_total > 0) {
            $this->push_import_log(
                $state,
                sprintf(
                    __('Location rows queued: %d', 'multi-location-product-and-inventory-management'),
                    $location_total
                )
            );
        }

        foreach ($location_rows as $index => $row) {
            $line = isset($row['__line']) ? (int) $row['__line'] : 0;
            $slug = sanitize_title((string) $row['location_slug']);
            if ($slug === '') {
                $slug = sanitize_title((string) $row['slug']);
            }
            $name = (string) $row['location_name'];
            if ($name === '') {
                $name = (string) $row['name'];
            }
            $description = (string) $row['location_description'];
            $parent_slug = sanitize_title((string) $row['location_parent_slug']);

            if ($slug === '') {
                $this->row_error($state, $row, sprintf(__('Line %d: location_slug is required for location rows.', 'multi-location-product-and-inventory-management'), $line));
                continue;
            }

            $location_term = get_term_by('slug', $slug, 'mulopimfwc_store_location');
            $location_id = 0;
            if ($location_term && !is_wp_error($location_term)) {
                $location_id = (int) $location_term->term_id;
                $state['summary']['locations_updated']++;
            } else {
                if ($runtime['mode'] === 'update_only') {
                    $this->row_error($state, $row, sprintf(__('Line %d: location %s not found (update-only mode).', 'multi-location-product-and-inventory-management'), $line, $slug));
                    continue;
                }
                if ($runtime['mode'] !== 'dry_run') {
                    $created = wp_insert_term($name !== '' ? $name : $slug, 'mulopimfwc_store_location', array(
                        'slug' => $slug,
                        'description' => $description,
                    ));
                    if (is_wp_error($created)) {
                        $this->row_error($state, $row, sprintf(__('Line %d: failed to create location %s (%s).', 'multi-location-product-and-inventory-management'), $line, $slug, $created->get_error_message()));
                        continue;
                    }
                    $location_id = (int) $created['term_id'];
                } else {
                    $location_id = -1 * (int) max(1, $state['summary']['locations_created'] + 1);
                }
                $state['summary']['locations_created']++;
            }

            $state['location_map'][$slug] = $location_id;
            $state['term_map']['mulopimfwc_store_location:' . $slug] = $location_id;
            $state['row_map'][(string) $row['row_key']] = array('entity' => 'location', 'id' => $location_id, 'slug' => $slug);

            if ($parent_slug !== '') {
                $pending_location_parents[] = array('slug' => $slug, 'parent_slug' => $parent_slug);
            }

            $location_meta = $this->decode_json_field($row['location_term_meta_json']);
            if ($runtime['sync_location_profile'] && !empty($location_meta) && $runtime['mode'] !== 'dry_run' && $location_id > 0) {
                $this->capture_term_snapshot_once($location_id, $state);
                foreach ($location_meta as $meta_key => $meta_value) {
                    $meta_key = sanitize_key((string) $meta_key);
                    if (in_array($meta_key, $this->safe_location_term_meta_keys, true)) {
                        update_term_meta($location_id, $meta_key, $meta_value);
                    }
                }
            }

            $state['summary']['rows_processed']++;

            $position = (int) $index + 1;
            if ($location_total > 0 && ($position === $location_total || ($position % 200) === 0)) {
                $this->push_import_log(
                    $state,
                    sprintf(
                        __('Location progress: %1$d/%2$d', 'multi-location-product-and-inventory-management'),
                        $position,
                        $location_total
                    )
                );
            }
        }

        foreach ($pending_location_parents as $pending) {
            if (!isset($state['location_map'][$pending['slug']])) {
                continue;
            }
            $child_id = (int) $state['location_map'][$pending['slug']];
            $parent_id = isset($state['location_map'][$pending['parent_slug']]) ? (int) $state['location_map'][$pending['parent_slug']] : 0;
            if ($parent_id <= 0) {
                $parent_term = get_term_by('slug', $pending['parent_slug'], 'mulopimfwc_store_location');
                if ($parent_term && !is_wp_error($parent_term)) {
                    $parent_id = (int) $parent_term->term_id;
                }
            }
            if ($child_id > 0 && $parent_id > 0 && $runtime['mode'] !== 'dry_run') {
                wp_update_term($child_id, 'mulopimfwc_store_location', array('parent' => $parent_id));
            }
        }
    }

    private function pass_media_refs($grouped, $runtime, &$state)
    {
        $media_rows = isset($grouped['media_ref']) ? $grouped['media_ref'] : array();
        $media_total = count($media_rows);
        if ($media_total > 0) {
            $this->push_import_log(
                $state,
                sprintf(
                    __('Media reference rows queued: %d', 'multi-location-product-and-inventory-management'),
                    $media_total
                )
            );
        }

        foreach ($media_rows as $index => $row) {
            $url = isset($row['media_url']) ? esc_url_raw((string) $row['media_url']) : '';
            if ($url === '') {
                continue;
            }
            if (isset($state['media_map'][$url])) {
                continue;
            }
            $attachment_id = $this->resolve_attachment_from_url($url, $runtime, $state);
            if ($attachment_id > 0) {
                $state['media_map'][$url] = $attachment_id;
                $state['summary']['media_mapped']++;
            }
            $state['summary']['rows_processed']++;

            $position = (int) $index + 1;
            if ($media_total > 0 && ($position === $media_total || ($position % 200) === 0)) {
                $this->push_import_log(
                    $state,
                    sprintf(
                        __('Media mapping progress: %1$d/%2$d', 'multi-location-product-and-inventory-management'),
                        $position,
                        $media_total
                    )
                );
            }
        }
    }

    private function pass_products($grouped, $runtime, &$state)
    {
        $product_rows = isset($grouped['product']) ? $grouped['product'] : array();
        $product_total = count($product_rows);
        if ($product_total > 0) {
            $this->push_import_log(
                $state,
                sprintf(
                    __('Product rows queued: %d', 'multi-location-product-and-inventory-management'),
                    $product_total
                )
            );
        }

        foreach ($product_rows as $index => $row) {
            $line = isset($row['__line']) ? (int) $row['__line'] : 0;
            $sku = sanitize_text_field((string) $row['sku']);
            $slug = sanitize_title((string) $row['slug']);
            $type = sanitize_key((string) $row['product_type']);
            if ($type === '') {
                $type = 'simple';
            }

            $match = $this->match_product_by_identity($sku, $slug, 'product');
            if (!empty($match['error'])) {
                $this->row_error($state, $row, sprintf(__('Line %d: %s', 'multi-location-product-and-inventory-management'), $line, $match['error']));
                continue;
            }

            $product_id = isset($match['id']) ? (int) $match['id'] : 0;
            $is_create = $product_id <= 0;
            if ($is_create && $runtime['mode'] === 'update_only') {
                $this->row_error($state, $row, sprintf(__('Line %d: product not found (update-only mode).', 'multi-location-product-and-inventory-management'), $line));
                continue;
            }

            if ($runtime['mode'] !== 'dry_run') {
                $product = $is_create ? $this->create_wc_product_by_type($type) : wc_get_product($product_id);
                if (!$product) {
                    $this->row_error($state, $row, sprintf(__('Line %d: unable to instantiate product object.', 'multi-location-product-and-inventory-management'), $line));
                    continue;
                }
                if (!$is_create) {
                    $this->capture_product_snapshot_once($product_id, $state);
                }

                $this->apply_common_product_fields($product, $row, $runtime);
                $saved_id = (int) $product->save();
                if ($saved_id <= 0) {
                    $this->row_error($state, $row, sprintf(__('Line %d: failed to save product.', 'multi-location-product-and-inventory-management'), $line));
                    continue;
                }
                $product_id = $saved_id;

                $this->apply_product_taxonomies($product_id, $row, $runtime, $state);
                $this->apply_product_attributes($product, $row, $runtime, $state);
                $this->apply_product_media($product, $row, $runtime, $state);
                $this->apply_purchase_meta_fields($product_id, $row, $runtime);
                $this->apply_custom_meta($product_id, $row, $runtime, $state);
                $product->save();
            } else {
                if ($is_create) {
                    $product_id = -1 * (int) max(1, $state['summary']['products_created'] + 1);
                }
            }

            $row_key = (string) $row['row_key'];
            if ($row_key !== '') {
                $state['row_map'][$row_key] = array('entity' => 'product', 'id' => $product_id);
            }
            if ($sku !== '') {
                $state['sku_map'][$sku] = $product_id;
            }
            if ($slug !== '') {
                $state['row_map']['product:slug:' . $slug] = array('entity' => 'product', 'id' => $product_id);
            }

            if ($is_create) {
                $state['summary']['products_created']++;
            } else {
                $state['summary']['products_updated']++;
            }
            $state['summary']['rows_processed']++;

            $position = (int) $index + 1;
            if ($product_total > 0 && ($position === $product_total || ($position % 100) === 0)) {
                $this->push_import_log(
                    $state,
                    sprintf(
                        __('Product import progress: %1$d/%2$d', 'multi-location-product-and-inventory-management'),
                        $position,
                        $product_total
                    )
                );
            }
        }
    }

    private function pass_variations($grouped, $runtime, &$state)
    {
        $variation_rows = isset($grouped['variation']) ? $grouped['variation'] : array();
        $variation_total = count($variation_rows);
        if ($variation_total > 0) {
            $this->push_import_log(
                $state,
                sprintf(
                    __('Variation rows queued: %d', 'multi-location-product-and-inventory-management'),
                    $variation_total
                )
            );
        }

        foreach ($variation_rows as $index => $row) {
            $line = isset($row['__line']) ? (int) $row['__line'] : 0;
            $sku = sanitize_text_field((string) $row['sku']);
            $parent_key = (string) $row['parent_key'];
            $parent_id = $this->resolve_parent_product_id($parent_key, $state);
            if ($parent_id === 0) {
                $this->row_error($state, $row, sprintf(__('Line %d: parent product for variation could not be resolved.', 'multi-location-product-and-inventory-management'), $line));
                continue;
            }

            $attrs = $this->decode_json_field($row['attributes_json']);
            $match = $this->match_variation_by_identity($sku, $parent_id, $attrs);
            if (!empty($match['error'])) {
                $this->row_error($state, $row, sprintf(__('Line %d: %s', 'multi-location-product-and-inventory-management'), $line, $match['error']));
                continue;
            }

            $variation_id = isset($match['id']) ? (int) $match['id'] : 0;
            $is_create = $variation_id <= 0;
            if ($is_create && $runtime['mode'] === 'update_only') {
                $this->row_error($state, $row, sprintf(__('Line %d: variation not found (update-only mode).', 'multi-location-product-and-inventory-management'), $line));
                continue;
            }

            if ($runtime['mode'] !== 'dry_run') {
                if ($is_create) {
                    $variation = new WC_Product_Variation();
                    $variation->set_parent_id($parent_id);
                } else {
                    $variation = wc_get_product($variation_id);
                    if ($variation && !$variation->is_type('variation')) {
                        $variation = null;
                    }
                }

                if (!$variation) {
                    $this->row_error($state, $row, sprintf(__('Line %d: unable to instantiate variation object.', 'multi-location-product-and-inventory-management'), $line));
                    continue;
                }

                if (!$is_create) {
                    $this->capture_product_snapshot_once($variation_id, $state);
                }

                $this->apply_common_product_fields($variation, $row, $runtime);
                $variation->set_parent_id($parent_id);
                if (is_array($attrs) && !empty($attrs)) {
                    $variation->set_attributes($this->normalize_variation_attributes($attrs));
                }
                $saved_id = (int) $variation->save();
                if ($saved_id <= 0) {
                    $this->row_error($state, $row, sprintf(__('Line %d: failed to save variation.', 'multi-location-product-and-inventory-management'), $line));
                    continue;
                }
                $variation_id = $saved_id;

                $this->apply_product_media($variation, $row, $runtime, $state);
                $this->apply_purchase_meta_fields($variation_id, $row, $runtime);
                $this->apply_custom_meta($variation_id, $row, $runtime, $state);
                $variation->save();
            } else {
                if ($is_create) {
                    $variation_id = -1 * (int) max(1, $state['summary']['variations_created'] + 1);
                }
            }

            $row_key = (string) $row['row_key'];
            if ($row_key !== '') {
                $state['row_map'][$row_key] = array('entity' => 'variation', 'id' => $variation_id, 'parent_id' => $parent_id);
            }
            if ($sku !== '') {
                $state['variation_sku_map'][$sku] = $variation_id;
            }

            if ($is_create) {
                $state['summary']['variations_created']++;
            } else {
                $state['summary']['variations_updated']++;
            }
            $state['summary']['rows_processed']++;

            $position = (int) $index + 1;
            if ($variation_total > 0 && ($position === $variation_total || ($position % 100) === 0)) {
                $this->push_import_log(
                    $state,
                    sprintf(
                        __('Variation import progress: %1$d/%2$d', 'multi-location-product-and-inventory-management'),
                        $position,
                        $variation_total
                    )
                );
            }
        }
    }

    private function pass_relationships($grouped, $runtime, &$state)
    {
        $relationship_rows = isset($grouped['relationship']) ? $grouped['relationship'] : array();
        $relationship_total = count($relationship_rows);
        if ($relationship_total > 0) {
            $this->push_import_log(
                $state,
                sprintf(
                    __('Relationship rows queued: %d', 'multi-location-product-and-inventory-management'),
                    $relationship_total
                )
            );
        }

        foreach ($relationship_rows as $index => $row) {
            $line = isset($row['__line']) ? (int) $row['__line'] : 0;
            $source_id = $this->resolve_product_id_from_row($row, $state);
            if ($source_id === 0) {
                $this->row_error($state, $row, sprintf(__('Line %d: relationship source product not found.', 'multi-location-product-and-inventory-management'), $line));
                continue;
            }

            $relation_type = sanitize_key((string) $row['relationship_type']);
            $related_skus = array_values(array_filter(array_map('trim', explode('|', (string) $row['related_skus']))));
            $related_ids = array();
            foreach ($related_skus as $related_sku) {
                $related_id = $this->resolve_product_id_by_sku($related_sku, $state);
                if ($related_id > 0) {
                    $related_ids[] = $related_id;
                }
            }
            $related_ids = array_values(array_unique(array_map('intval', $related_ids)));

            if ($runtime['mode'] !== 'dry_run') {
                $product = wc_get_product($source_id);
                if (!$product) {
                    $this->row_error($state, $row, sprintf(__('Line %d: could not load source product.', 'multi-location-product-and-inventory-management'), $line));
                    continue;
                }
                $this->capture_product_snapshot_once($source_id, $state);
                if ($relation_type === 'upsell') {
                    $product->set_upsell_ids($related_ids);
                } elseif ($relation_type === 'cross_sell') {
                    $product->set_cross_sell_ids($related_ids);
                } elseif ($relation_type === 'grouped_children' && method_exists($product, 'set_children')) {
                    $product->set_children($related_ids);
                }
                $product->save();
            }

            $state['summary']['relationships_updated']++;
            $state['summary']['rows_processed']++;

            $position = (int) $index + 1;
            if ($relationship_total > 0 && ($position === $relationship_total || ($position % 200) === 0)) {
                $this->push_import_log(
                    $state,
                    sprintf(
                        __('Relationship progress: %1$d/%2$d', 'multi-location-product-and-inventory-management'),
                        $position,
                        $relationship_total
                    )
                );
            }
        }
    }

    private function pass_location_inventory($grouped, $runtime, &$state)
    {
        $location_inventory_rows = isset($grouped['location_inventory']) ? $grouped['location_inventory'] : array();
        $location_inventory_total = count($location_inventory_rows);
        if ($location_inventory_total > 0) {
            $this->push_import_log(
                $state,
                sprintf(
                    __('Location inventory rows queued: %d', 'multi-location-product-and-inventory-management'),
                    $location_inventory_total
                )
            );
        }

        foreach ($location_inventory_rows as $index => $row) {
            $line = isset($row['__line']) ? (int) $row['__line'] : 0;
            $location_slug = sanitize_title((string) $row['location_slug']);
            if ($location_slug === '') {
                $this->row_error($state, $row, sprintf(__('Line %d: location_slug is required for location_inventory rows.', 'multi-location-product-and-inventory-management'), $line));
                continue;
            }

            $location_id = $this->resolve_location_id($location_slug, $runtime, $state);
            if ($location_id === 0) {
                $this->row_error($state, $row, sprintf(__('Line %d: location %s could not be resolved.', 'multi-location-product-and-inventory-management'), $line, $location_slug));
                continue;
            }

            $item_id = $this->resolve_product_id_from_row($row, $state, true);
            if ($item_id === 0) {
                $this->row_error($state, $row, sprintf(__('Line %d: target product/variation for location inventory was not found.', 'multi-location-product-and-inventory-management'), $line));
                continue;
            }

            $inventory_payload = $this->decode_json_field($row['location_inventory_json']);
            if (!is_array($inventory_payload)) {
                $inventory_payload = array();
            }
            if (empty($inventory_payload)) {
                foreach (array('stock', 'regular_price', 'sale_price', 'backorders', 'disabled') as $legacy_key) {
                    if (isset($row[$legacy_key]) && $row[$legacy_key] !== '') {
                        $inventory_payload[$legacy_key] = $row[$legacy_key];
                    }
                }
            }

            $assign_target_id = $item_id;
            $target_product = wc_get_product($item_id);
            if ($target_product && $target_product->is_type('variation')) {
                $assign_target_id = (int) $target_product->get_parent_id();
                if ($assign_target_id <= 0) {
                    $assign_target_id = $item_id;
                }
            }

            if ($runtime['mode'] !== 'dry_run') {
                $this->capture_product_snapshot_once($item_id, $state);
                if ($assign_target_id !== $item_id) {
                    $this->capture_product_snapshot_once($assign_target_id, $state);
                }
                if ($assign_target_id > 0) {
                    wp_set_object_terms($assign_target_id, array((int) $location_id), 'mulopimfwc_store_location', true);
                }
                foreach ($inventory_payload as $base_key => $value) {
                    $base_key = sanitize_key((string) $base_key);
                    if ($base_key !== '') {
                        $this->apply_meta_update_rule($item_id, '_location_' . $base_key . '_' . (int) $location_id, $value, $runtime);
                    }
                }
                wc_delete_product_transients($item_id);
            }

            $state['summary']['location_inventory_updated']++;
            $state['summary']['rows_processed']++;

            $position = (int) $index + 1;
            if ($location_inventory_total > 0 && ($position === $location_inventory_total || ($position % 200) === 0)) {
                $this->push_import_log(
                    $state,
                    sprintf(
                        __('Location inventory progress: %1$d/%2$d', 'multi-location-product-and-inventory-management'),
                        $position,
                        $location_inventory_total
                    )
                );
            }
        }
    }

    private function finalize_import(&$state)
    {
        if (function_exists('mulopimfwc_bump_dashboard_cache_version')) {
            mulopimfwc_bump_dashboard_cache_version();
        }
    }

    private function resolve_attachment_from_url($url, $runtime, &$state)
    {
        $url = esc_url_raw((string) $url);
        if ($url === '') {
            return 0;
        }

        $existing_id = (int) attachment_url_to_postid($url);
        if ($existing_id > 0) {
            return $existing_id;
        }

        global $wpdb;
        $meta_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
                '_mulopimfwc_source_url',
                $url
            )
        );
        if ($meta_id > 0) {
            return $meta_id;
        }

        if (empty($runtime['import_media']) || $runtime['mode'] === 'dry_run') {
            return 0;
        }

        if (!function_exists('media_sideload_image')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $attachment_id = media_sideload_image($url, 0, null, 'id');
        if (is_wp_error($attachment_id)) {
            $warning_message = sprintf(__('Media sideload failed for %s: %s', 'multi-location-product-and-inventory-management'), $url, $attachment_id->get_error_message());
            $state['warnings'][] = $warning_message;
            $this->push_import_log($state, $warning_message, 'warning');
            return 0;
        }
        $attachment_id = (int) $attachment_id;
        if ($attachment_id > 0) {
            update_post_meta($attachment_id, '_mulopimfwc_source_url', $url);
            $state['summary']['media_sideloaded']++;
        }
        return $attachment_id;
    }

    private function create_wc_product_by_type($type)
    {
        $type = sanitize_key((string) $type);
        if ($type === 'variable') {
            return new WC_Product_Variable();
        }
        if ($type === 'grouped') {
            return new WC_Product_Grouped();
        }
        if ($type === 'external') {
            return new WC_Product_External();
        }
        return new WC_Product_Simple();
    }

    private function apply_common_product_fields($product, $row, $runtime)
    {
        $this->apply_string_field($product, 'set_name', $row, 'name', $runtime);
        $this->apply_string_field($product, 'set_status', $row, 'status', $runtime, array('publish', 'draft', 'pending', 'private', 'future'));
        $this->apply_string_field($product, 'set_catalog_visibility', $row, 'catalog_visibility', $runtime, array('visible', 'catalog', 'search', 'hidden'));
        $this->apply_bool_field($product, 'set_featured', $row, 'featured', $runtime);
        $this->apply_int_field($product, 'set_menu_order', $row, 'menu_order', $runtime);
        $this->apply_string_field($product, 'set_description', $row, 'description', $runtime);
        $this->apply_string_field($product, 'set_short_description', $row, 'short_description', $runtime);

        $this->apply_decimal_field($product, 'set_regular_price', $row, 'regular_price', $runtime);
        $this->apply_decimal_field($product, 'set_sale_price', $row, 'sale_price', $runtime);
        $this->apply_bool_field($product, 'set_manage_stock', $row, 'manage_stock', $runtime);

        if (isset($row['stock']) && $this->should_write_field((string) $row['stock'], $runtime)) {
            $stock = trim((string) $row['stock']);
            if ($stock === '' && $runtime['field_mode'] === 'set_and_clear') {
                $product->set_stock_quantity(null);
            } elseif (is_numeric($stock)) {
                $product->set_stock_quantity((float) $stock);
            }
        }

        $this->apply_string_field($product, 'set_stock_status', $row, 'stock_status', $runtime, array('instock', 'outofstock', 'onbackorder'));
        $this->apply_string_field($product, 'set_backorders', $row, 'backorders', $runtime, array('no', 'notify', 'yes'));
        $this->apply_bool_field($product, 'set_sold_individually', $row, 'sold_individually', $runtime);
        $this->apply_bool_field($product, 'set_virtual', $row, 'virtual', $runtime);
        $this->apply_bool_field($product, 'set_downloadable', $row, 'downloadable', $runtime);
        $this->apply_string_field($product, 'set_weight', $row, 'weight', $runtime);
        $this->apply_string_field($product, 'set_length', $row, 'length', $runtime);
        $this->apply_string_field($product, 'set_width', $row, 'width', $runtime);
        $this->apply_string_field($product, 'set_height', $row, 'height', $runtime);
        $this->apply_string_field($product, 'set_tax_status', $row, 'tax_status', $runtime, array('taxable', 'shipping', 'none'));
        $this->apply_string_field($product, 'set_tax_class', $row, 'tax_class', $runtime);

        if (isset($row['shipping_class']) && $this->should_write_field((string) $row['shipping_class'], $runtime)) {
            $shipping_class_slug = sanitize_title((string) $row['shipping_class']);
            if ($shipping_class_slug === '' && $runtime['field_mode'] === 'set_and_clear') {
                $product->set_shipping_class_id(0);
            } elseif ($shipping_class_slug !== '') {
                $term = get_term_by('slug', $shipping_class_slug, 'product_shipping_class');
                if ($term && !is_wp_error($term)) {
                    $product->set_shipping_class_id((int) $term->term_id);
                }
            }
        }

        if (isset($row['sku']) && $this->should_write_field((string) $row['sku'], $runtime)) {
            $sku = sanitize_text_field((string) $row['sku']);
            if ($sku !== '') {
                $product->set_sku($sku);
            }
        }

        if ($product->is_type('external')) {
            $this->apply_string_field($product, 'set_product_url', $row, 'external_url', $runtime);
            $this->apply_string_field($product, 'set_button_text', $row, 'button_text', $runtime);
        }

        if ($product->is_downloadable()) {
            if (isset($row['downloads_json']) && $this->should_write_field((string) $row['downloads_json'], $runtime)) {
                $downloads = $this->decode_json_field($row['downloads_json']);
                if (is_array($downloads)) {
                    $download_objects = array();
                    foreach ($downloads as $download_data) {
                        if (!is_array($download_data)) {
                            continue;
                        }
                        $file = isset($download_data['file']) ? esc_url_raw((string) $download_data['file']) : '';
                        if ($file === '') {
                            continue;
                        }
                        $download = new WC_Product_Download();
                        $download->set_name(isset($download_data['name']) ? (string) $download_data['name'] : basename($file));
                        $download->set_file($file);
                        if (!empty($download_data['id'])) {
                            $download->set_id((string) $download_data['id']);
                        }
                        $download_objects[] = $download;
                    }
                    $product->set_downloads($download_objects);
                }
            }

            $this->apply_int_field($product, 'set_download_limit', $row, 'download_limit', $runtime, true);
            $this->apply_int_field($product, 'set_download_expiry', $row, 'download_expiry', $runtime, true);
        }
    }

    private function apply_product_taxonomies($product_id, $row, $runtime, &$state)
    {
        $taxonomies_payload = $this->decode_json_field($row['taxonomies_json']);
        if (!is_array($taxonomies_payload)) {
            return;
        }
        foreach ($taxonomies_payload as $taxonomy => $term_slugs) {
            $taxonomy = sanitize_key((string) $taxonomy);
            if ($taxonomy === '' || !$this->ensure_import_taxonomy_exists($taxonomy, $runtime, $state)) {
                continue;
            }
            if (!is_array($term_slugs)) {
                $term_slugs = array($term_slugs);
            }
            $term_ids = array();
            foreach ($term_slugs as $slug) {
                $slug = sanitize_title((string) $slug);
                if ($slug === '') {
                    continue;
                }
                $term_id = $this->resolve_term_id($taxonomy, $slug, $runtime, $state);
                if ($term_id > 0) {
                    $term_ids[] = $term_id;
                }
            }
            if ($runtime['mode'] !== 'dry_run') {
                if (!empty($term_ids) || $runtime['field_mode'] === 'set_and_clear') {
                    wp_set_object_terms($product_id, $term_ids, $taxonomy, false);
                }
            }
        }
    }

    private function apply_product_attributes($product, $row, $runtime, &$state)
    {
        $attributes_payload = $this->decode_json_field($row['attributes_json']);
        if (!is_array($attributes_payload)) {
            return;
        }

        $attribute_objects = array();
        foreach ($attributes_payload as $attribute_item) {
            if (!is_array($attribute_item)) {
                continue;
            }
            $name = isset($attribute_item['name']) ? wc_sanitize_taxonomy_name((string) $attribute_item['name']) : '';
            if ($name === '') {
                continue;
            }
            $is_taxonomy = !empty($attribute_item['is_taxonomy']);
            $options = isset($attribute_item['options']) && is_array($attribute_item['options']) ? $attribute_item['options'] : array();

            $attr = new WC_Product_Attribute();
            $attr->set_name($name);
            $attr->set_visible(!empty($attribute_item['visible']));
            $attr->set_variation(!empty($attribute_item['variation']));
            $attr->set_position(isset($attribute_item['position']) ? (int) $attribute_item['position'] : 0);

            if ($is_taxonomy && $this->ensure_import_taxonomy_exists($name, $runtime, $state)) {
                $term_ids = array();
                foreach ($options as $option_slug) {
                    $option_slug = sanitize_title((string) $option_slug);
                    if ($option_slug === '') {
                        continue;
                    }
                    $term_id = $this->resolve_term_id($name, $option_slug, $runtime, $state);
                    if ($term_id > 0) {
                        $term_ids[] = $term_id;
                    }
                }
                $attribute_tax_id = function_exists('wc_attribute_taxonomy_id_by_name') ? (int) wc_attribute_taxonomy_id_by_name($name) : 0;
                $attr->set_id($attribute_tax_id);
                $attr->set_options(array_values(array_unique(array_map('intval', $term_ids))));
            } else {
                $clean_options = array();
                foreach ($options as $option_value) {
                    $option_value = wc_clean((string) $option_value);
                    if ($option_value !== '') {
                        $clean_options[] = $option_value;
                    }
                }
                $attr->set_options($clean_options);
            }

            $attribute_objects[] = $attr;
        }

        if (!empty($attribute_objects) || $runtime['field_mode'] === 'set_and_clear') {
            $product->set_attributes($attribute_objects);
        }
        $default_attrs = $this->decode_json_field($row['default_attributes_json']);
        if (is_array($default_attrs) && method_exists($product, 'set_default_attributes')) {
            $normalized_defaults = array();
            foreach ($default_attrs as $k => $v) {
                $key = str_replace('attribute_', '', wc_sanitize_taxonomy_name((string) $k));
                $value = sanitize_title((string) $v);
                if ($key !== '' && $value !== '') {
                    $normalized_defaults[$key] = $value;
                }
            }
            $product->set_default_attributes($normalized_defaults);
        }
    }

    private function apply_product_media($product, $row, $runtime, &$state)
    {
        $image_src = isset($row['image_src']) ? esc_url_raw((string) $row['image_src']) : '';
        if ($image_src !== '' || $runtime['field_mode'] === 'set_and_clear') {
            if ($runtime['mode'] !== 'dry_run') {
                if ($image_src === '' && $runtime['field_mode'] === 'set_and_clear') {
                    $product->set_image_id(0);
                } else {
                    $image_id = $this->resolve_attachment_from_url($image_src, $runtime, $state);
                    if ($image_id > 0) {
                        $product->set_image_id($image_id);
                    }
                }
            }
        }

        $gallery_sources = $this->decode_json_field($row['gallery_src']);
        if (!is_array($gallery_sources)) {
            $gallery_sources = array();
        }
        if (!empty($gallery_sources) || $runtime['field_mode'] === 'set_and_clear') {
            $gallery_ids = array();
            foreach ($gallery_sources as $gallery_url) {
                $gallery_url = esc_url_raw((string) $gallery_url);
                if ($gallery_url === '') {
                    continue;
                }
                $attachment_id = $this->resolve_attachment_from_url($gallery_url, $runtime, $state);
                if ($attachment_id > 0) {
                    $gallery_ids[] = (int) $attachment_id;
                }
            }
            if ($runtime['mode'] !== 'dry_run') {
                $product->set_gallery_image_ids(array_values(array_unique($gallery_ids)));
            }
        }
    }

    private function apply_custom_meta($product_id, $row, $runtime, &$state)
    {
        $payload = $this->decode_json_field($row['custom_meta_json']);
        if (!is_array($payload)) {
            return;
        }
        $whitelist = $runtime['meta_whitelist'];
        foreach ($payload as $meta_key => $meta_value) {
            $meta_key = sanitize_key((string) $meta_key);
            if ($meta_key === '' || in_array($meta_key, $this->meta_blacklist, true)) {
                continue;
            }
            if (!empty($whitelist) && !in_array($meta_key, $whitelist, true)) {
                continue;
            }
            if ($runtime['mode'] === 'dry_run') {
                continue;
            }
            if ($runtime['field_mode'] === 'set_and_clear' && $meta_value === '') {
                delete_post_meta($product_id, $meta_key);
            } else {
                update_post_meta($product_id, $meta_key, $meta_value);
            }
        }
    }

    private function apply_purchase_meta_fields($product_id, $row, $runtime)
    {
        $product_id = (int) $product_id;
        if ($product_id <= 0 || !is_array($row)) {
            return;
        }

        if (array_key_exists('purchase_price', $row)) {
            $this->apply_meta_update_rule($product_id, '_purchase_price', $row['purchase_price'], $runtime);
        }
        if (array_key_exists('purchase_quantity', $row)) {
            $this->apply_meta_update_rule($product_id, '_purchase_quantity', $row['purchase_quantity'], $runtime);
        }
    }

    private function match_product_by_identity($sku, $slug, $target_type)
    {
        if ($sku !== '') {
            $sku_matches = $this->find_product_ids_by_sku($sku, $target_type);
            if (count($sku_matches) > 1) {
                return array('id' => 0, 'error' => sprintf(__('Duplicate SKU detected for %s. Manual resolution required.', 'multi-location-product-and-inventory-management'), $sku));
            }
            if (count($sku_matches) === 1) {
                return array('id' => (int) $sku_matches[0], 'error' => '');
            }
        }

        if ($slug !== '') {
            $post_type = $target_type === 'variation' ? 'product_variation' : 'product';
            $post = get_page_by_path($slug, OBJECT, $post_type);
            if ($post && !empty($post->ID)) {
                return array('id' => (int) $post->ID, 'error' => '');
            }
        }
        return array('id' => 0, 'error' => '');
    }

    private function match_variation_by_identity($sku, $parent_id, $attributes)
    {
        if ($sku !== '') {
            $sku_matches = $this->find_product_ids_by_sku($sku, 'variation');
            if (count($sku_matches) > 1) {
                return array('id' => 0, 'error' => sprintf(__('Duplicate variation SKU detected for %s. Manual resolution required.', 'multi-location-product-and-inventory-management'), $sku));
            }
            if (count($sku_matches) === 1) {
                return array('id' => (int) $sku_matches[0], 'error' => '');
            }
        }

        $parent_id = (int) $parent_id;
        if ($parent_id <= 0 || empty($attributes) || !is_array($attributes)) {
            return array('id' => 0, 'error' => '');
        }

        $normalized_target = $this->normalize_variation_attributes($attributes);
        $parent = wc_get_product($parent_id);
        if (!$parent || !$parent->is_type('variable')) {
            return array('id' => 0, 'error' => '');
        }

        foreach ((array) $parent->get_children() as $variation_id) {
            $variation = wc_get_product($variation_id);
            if (!$variation || !$variation->is_type('variation')) {
                continue;
            }
            $candidate_attrs = $this->normalize_variation_attributes($variation->get_attributes());
            if ($candidate_attrs == $normalized_target) {
                return array('id' => (int) $variation_id, 'error' => '');
            }
        }
        return array('id' => 0, 'error' => '');
    }

    private function resolve_parent_product_id($parent_key, &$state)
    {
        $parent_key = (string) $parent_key;
        if ($parent_key !== '' && isset($state['row_map'][$parent_key])) {
            $entity = $state['row_map'][$parent_key];
            if (isset($entity['id']) && (int) $entity['id'] !== 0) {
                return (int) $entity['id'];
            }
        }

        if ($parent_key !== '' && strpos($parent_key, 'product:') === 0) {
            $parent_sku = substr($parent_key, 8);
            if ($parent_sku !== '') {
                $match = $this->match_product_by_identity($parent_sku, '', 'product');
                if (!empty($match['id'])) {
                    return (int) $match['id'];
                }
            }
        }
        return 0;
    }

    private function resolve_product_id_from_row($row, &$state, $allow_variation = false)
    {
        $parent_key = isset($row['parent_key']) ? (string) $row['parent_key'] : '';
        if ($parent_key !== '' && isset($state['row_map'][$parent_key])) {
            $entity = $state['row_map'][$parent_key];
            if (isset($entity['id']) && (int) $entity['id'] !== 0) {
                return (int) $entity['id'];
            }
        }

        $row_key = isset($row['row_key']) ? (string) $row['row_key'] : '';
        if ($row_key !== '' && isset($state['row_map'][$row_key])) {
            $entity = $state['row_map'][$row_key];
            if (isset($entity['id']) && (int) $entity['id'] !== 0) {
                return (int) $entity['id'];
            }
        }

        $sku = isset($row['sku']) ? sanitize_text_field((string) $row['sku']) : '';
        if ($sku !== '') {
            if ($allow_variation && isset($state['variation_sku_map'][$sku]) && (int) $state['variation_sku_map'][$sku] !== 0) {
                return (int) $state['variation_sku_map'][$sku];
            }
            if (isset($state['sku_map'][$sku]) && (int) $state['sku_map'][$sku] !== 0) {
                return (int) $state['sku_map'][$sku];
            }
            if ($allow_variation) {
                $variation_match = $this->match_product_by_identity($sku, '', 'variation');
                if (!empty($variation_match['id'])) {
                    return (int) $variation_match['id'];
                }
            }
            $product_match = $this->match_product_by_identity($sku, '', 'product');
            if (!empty($product_match['id'])) {
                return (int) $product_match['id'];
            }
        }
        return 0;
    }

    private function resolve_product_id_by_sku($sku, &$state)
    {
        $sku = sanitize_text_field((string) $sku);
        if ($sku === '') {
            return 0;
        }
        if (isset($state['sku_map'][$sku]) && (int) $state['sku_map'][$sku] !== 0) {
            return (int) $state['sku_map'][$sku];
        }
        if (isset($state['variation_sku_map'][$sku]) && (int) $state['variation_sku_map'][$sku] !== 0) {
            return (int) $state['variation_sku_map'][$sku];
        }
        $match = $this->match_product_by_identity($sku, '', 'product');
        if (!empty($match['id'])) {
            return (int) $match['id'];
        }
        $match = $this->match_product_by_identity($sku, '', 'variation');
        if (!empty($match['id'])) {
            return (int) $match['id'];
        }
        return 0;
    }

    private function resolve_location_id($slug, $runtime, &$state)
    {
        $slug = sanitize_title((string) $slug);
        if ($slug === '') {
            return 0;
        }
        if (isset($state['location_map'][$slug]) && (int) $state['location_map'][$slug] !== 0) {
            return (int) $state['location_map'][$slug];
        }

        $term = get_term_by('slug', $slug, 'mulopimfwc_store_location');
        if ($term && !is_wp_error($term)) {
            $term_id = (int) $term->term_id;
            $state['location_map'][$slug] = $term_id;
            return $term_id;
        }

        if (empty($runtime['auto_create_terms']) || $runtime['mode'] === 'update_only') {
            return 0;
        }
        if ($runtime['mode'] === 'dry_run') {
            if (isset($state['location_map'][$slug]) && (int) $state['location_map'][$slug] !== 0) {
                return (int) $state['location_map'][$slug];
            }
            $term_id = -1 * (int) max(1, $state['summary']['locations_created'] + 1);
            $state['location_map'][$slug] = $term_id;
            $state['summary']['locations_created']++;
            return $term_id;
        }

        $created = wp_insert_term($slug, 'mulopimfwc_store_location', array('slug' => $slug));
        if (is_wp_error($created)) {
            return 0;
        }
        $term_id = (int) $created['term_id'];
        $state['location_map'][$slug] = $term_id;
        $state['summary']['locations_created']++;
        return $term_id;
    }

    private function resolve_term_id($taxonomy, $slug, $runtime, &$state)
    {
        $taxonomy = sanitize_key((string) $taxonomy);
        $slug = sanitize_title((string) $slug);
        if ($taxonomy === '' || $slug === '' || !$this->ensure_import_taxonomy_exists($taxonomy, $runtime, $state)) {
            return 0;
        }

        $map_key = $taxonomy . ':' . $slug;
        if (isset($state['term_map'][$map_key]) && (int) $state['term_map'][$map_key] !== 0) {
            return (int) $state['term_map'][$map_key];
        }

        $term = get_term_by('slug', $slug, $taxonomy);
        if ($term && !is_wp_error($term)) {
            $term_id = (int) $term->term_id;
            $state['term_map'][$map_key] = $term_id;
            return $term_id;
        }

        if (empty($runtime['auto_create_terms']) || $runtime['mode'] === 'update_only') {
            return 0;
        }
        if ($runtime['mode'] === 'dry_run') {
            if (isset($state['term_map'][$map_key]) && (int) $state['term_map'][$map_key] !== 0) {
                return (int) $state['term_map'][$map_key];
            }
            $term_id = -1 * (int) max(1, $state['summary']['terms_created'] + 1);
            $state['term_map'][$map_key] = $term_id;
            $state['summary']['terms_created']++;
            $this->increment_created_term_summary($state, $taxonomy);
            return $term_id;
        }

        $created = wp_insert_term($slug, $taxonomy, array('slug' => $slug));
        if (is_wp_error($created)) {
            return 0;
        }
        $term_id = (int) $created['term_id'];
        $state['term_map'][$map_key] = $term_id;
        $state['summary']['terms_created']++;
        $this->increment_created_term_summary($state, $taxonomy);
        return $term_id;
    }

    private function increment_created_term_summary(&$state, $taxonomy)
    {
        $taxonomy = sanitize_key((string) $taxonomy);
        if ($taxonomy === '' || !isset($state['summary']) || !is_array($state['summary'])) {
            return;
        }

        if ($taxonomy === 'product_cat') {
            $state['summary']['categories_created']++;
            return;
        }
        if ($taxonomy === 'product_tag') {
            $state['summary']['tags_created']++;
            return;
        }
        if (strpos($taxonomy, 'pa_') === 0) {
            $state['summary']['attributes_created']++;
            return;
        }
        if ($taxonomy === 'product_brand' || strpos($taxonomy, 'brand') !== false) {
            $state['summary']['brands_created']++;
        }
    }

    private function ensure_import_taxonomy_exists($taxonomy, $runtime, &$state)
    {
        $taxonomy = sanitize_key((string) $taxonomy);
        if ($taxonomy === '') {
            return false;
        }
        if (taxonomy_exists($taxonomy)) {
            return true;
        }

        // Auto-create missing global product attributes (pa_*) when enabled.
        if (strpos($taxonomy, 'pa_') !== 0) {
            return false;
        }
        if (empty($runtime['auto_create_terms']) || $runtime['mode'] === 'update_only') {
            return false;
        }

        $attribute_slug = wc_sanitize_taxonomy_name(substr($taxonomy, 3));
        if ($attribute_slug === '') {
            return false;
        }

        if ($runtime['mode'] !== 'dry_run') {
            $attribute_id = 0;
            if (function_exists('wc_attribute_taxonomy_id_by_name')) {
                $attribute_id = (int) wc_attribute_taxonomy_id_by_name($taxonomy);
                if ($attribute_id <= 0) {
                    $attribute_id = (int) wc_attribute_taxonomy_id_by_name($attribute_slug);
                }
            }

            if ($attribute_id <= 0) {
                if (!function_exists('wc_create_attribute')) {
                    return false;
                }
                $attribute_label = ucwords(str_replace(array('-', '_'), ' ', $attribute_slug));
                $created = wc_create_attribute(array(
                    'name' => $attribute_label,
                    'slug' => $attribute_slug,
                    'type' => 'select',
                    'order_by' => 'menu_order',
                    'has_archives' => false,
                ));
                if (is_wp_error($created)) {
                    return false;
                }
                if (function_exists('delete_transient')) {
                    delete_transient('wc_attribute_taxonomies');
                }
            }
        }

        if (!$this->register_runtime_product_attribute_taxonomy($taxonomy)) {
            return false;
        }
        return taxonomy_exists($taxonomy);
    }

    private function register_runtime_product_attribute_taxonomy($taxonomy)
    {
        $taxonomy = sanitize_key((string) $taxonomy);
        if ($taxonomy === '') {
            return false;
        }
        if (taxonomy_exists($taxonomy)) {
            return true;
        }
        if (strpos($taxonomy, 'pa_') !== 0) {
            return false;
        }

        $objects = apply_filters('woocommerce_taxonomy_objects_' . $taxonomy, array('product'));
        $args = apply_filters('woocommerce_taxonomy_args_' . $taxonomy, array(
            'hierarchical' => false,
            'show_ui' => false,
            'query_var' => true,
            'rewrite' => false,
            'public' => false,
            'show_in_nav_menus' => false,
            'capabilities' => array(
                'manage_terms' => 'manage_product_terms',
                'edit_terms' => 'edit_product_terms',
                'delete_terms' => 'delete_product_terms',
                'assign_terms' => 'assign_product_terms',
            ),
        ));

        register_taxonomy($taxonomy, (array) $objects, (array) $args);
        return taxonomy_exists($taxonomy);
    }

    private function apply_meta_update_rule($post_id, $meta_key, $value, $runtime)
    {
        if ($runtime['mode'] === 'dry_run') {
            return;
        }
        $value_string = (is_scalar($value) || $value === null) ? (string) $value : '';
        if ($value_string === '' && $runtime['field_mode'] === 'set_and_clear') {
            delete_post_meta($post_id, $meta_key);
            return;
        }
        if ($value_string === '' && $runtime['field_mode'] !== 'set_and_clear') {
            return;
        }
        update_post_meta($post_id, $meta_key, $value);
    }

    private function normalize_variation_attributes($attributes)
    {
        $normalized = array();
        if (!is_array($attributes)) {
            return $normalized;
        }
        foreach ($attributes as $key => $value) {
            $key = str_replace('attribute_', '', (string) $key);
            $key = wc_sanitize_taxonomy_name($key);
            if ($key === '') {
                continue;
            }
            if (is_array($value)) {
                $value = reset($value);
            }
            $value = sanitize_title((string) $value);
            if ($value === '') {
                continue;
            }
            $normalized[$key] = $value;
        }
        ksort($normalized);
        return $normalized;
    }

    private function normalize_product_attributes_for_export($product)
    {
        $attributes = array();
        foreach ((array) $product->get_attributes() as $attribute) {
            if (!is_object($attribute) || !method_exists($attribute, 'get_name')) {
                continue;
            }
            $item = array(
                'name' => (string) $attribute->get_name(),
                'is_taxonomy' => method_exists($attribute, 'is_taxonomy') ? (bool) $attribute->is_taxonomy() : false,
                'position' => method_exists($attribute, 'get_position') ? (int) $attribute->get_position() : 0,
                'visible' => method_exists($attribute, 'get_visible') ? (bool) $attribute->get_visible() : false,
                'variation' => method_exists($attribute, 'get_variation') ? (bool) $attribute->get_variation() : false,
                'options' => array(),
            );
            $options = method_exists($attribute, 'get_options') ? (array) $attribute->get_options() : array();
            if (!empty($item['is_taxonomy'])) {
                $taxonomy = $item['name'];
                $slugs = array();
                foreach ($options as $option_id) {
                    $option_id = (int) $option_id;
                    if ($option_id <= 0) {
                        continue;
                    }
                    $term = get_term($option_id, $taxonomy);
                    if ($term && !is_wp_error($term)) {
                        $slugs[] = (string) $term->slug;
                    }
                }
                $item['options'] = $slugs;
            } else {
                $item['options'] = array_values(array_map('strval', $options));
            }
            $attributes[] = $item;
        }
        return $attributes;
    }

    private function collect_custom_meta_for_export($post_id, $options)
    {
        $whitelist = $this->sanitize_meta_whitelist(isset($options['meta_whitelist']) ? $options['meta_whitelist'] : array());
        if (empty($whitelist)) {
            return array();
        }
        $meta_payload = array();
        foreach ($whitelist as $meta_key) {
            $value = get_post_meta($post_id, $meta_key, true);
            if ($value === '' || $value === null) {
                continue;
            }
            $meta_payload[$meta_key] = (is_array($value) || is_object($value)) ? $value : (string) $value;
        }
        return $meta_payload;
    }

    private function sanitize_import_runtime($options)
    {
        $mode = isset($options['mode']) ? sanitize_key((string) $options['mode']) : 'dry_run';
        $allowed_modes = array('dry_run', 'update_only', 'create_update');
        if (!in_array($mode, $allowed_modes, true)) {
            $mode = 'dry_run';
        }
        $field_mode = isset($options['field_mode']) ? sanitize_key((string) $options['field_mode']) : 'set_non_empty';
        if (!in_array($field_mode, array('set_non_empty', 'set_and_clear'), true)) {
            $field_mode = 'set_non_empty';
        }

        return array(
            'mode' => $mode,
            'strict' => !empty($options['strict']),
            'auto_create_terms' => !array_key_exists('auto_create_terms', $options) || !empty($options['auto_create_terms']),
            'sync_location_profile' => !array_key_exists('sync_location_profile', $options) || !empty($options['sync_location_profile']),
            'import_media' => !empty($options['import_media']),
            'field_mode' => $field_mode,
            'meta_whitelist' => $this->sanitize_meta_whitelist(isset($options['meta_whitelist']) ? $options['meta_whitelist'] : array()),
        );
    }

    private function get_request_options()
    {
        $options = array();
        if (isset($_POST['options'])) {
            $raw = wp_unslash($_POST['options']);
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $options = $decoded;
                }
            } elseif (is_array($raw)) {
                $options = $raw;
            }
        }
        return is_array($options) ? $options : array();
    }

    private function get_supported_product_taxonomies()
    {
        $taxonomies = get_object_taxonomies('product', 'names');
        if (!is_array($taxonomies)) {
            return array('product_cat', 'product_tag');
        }
        $filtered = array();
        foreach ($taxonomies as $taxonomy) {
            $taxonomy = sanitize_key((string) $taxonomy);
            if ($taxonomy === '' || !taxonomy_exists($taxonomy) || $taxonomy === 'product_type') {
                continue;
            }
            $filtered[] = $taxonomy;
        }
        if (!in_array('product_cat', $filtered, true)) {
            $filtered[] = 'product_cat';
        }
        if (!in_array('product_tag', $filtered, true)) {
            $filtered[] = 'product_tag';
        }
        if (taxonomy_exists('mulopimfwc_store_location') && !in_array('mulopimfwc_store_location', $filtered, true)) {
            $filtered[] = 'mulopimfwc_store_location';
        }
        return array_values(array_unique($filtered));
    }

    private function get_canonical_headers()
    {
        return array(
            'schema_version', 'exported_at', 'source_site', 'row_type', 'row_key', 'parent_key',
            'source_product_id', 'source_variation_id', 'source_term_id',
            'product_type', 'sku', 'slug', 'name', 'status', 'catalog_visibility', 'featured', 'menu_order',
            'description', 'short_description',
            'regular_price', 'sale_price', 'price', 'manage_stock', 'stock', 'stock_status', 'backorders', 'sold_individually',
            'virtual', 'downloadable', 'weight', 'length', 'width', 'height',
            'shipping_class', 'tax_status', 'tax_class',
            'purchase_price', 'purchase_quantity',
            'external_url', 'button_text',
            'download_limit', 'download_expiry', 'downloads_json',
            'attributes_json', 'default_attributes_json', 'taxonomies_json',
            'image_src', 'gallery_src',
            'upsell_skus', 'cross_sell_skus', 'grouped_child_skus',
            'location_slug', 'location_name', 'location_parent_slug', 'location_description', 'location_term_meta_json',
            'location_inventory_json',
            'taxonomy', 'term_slug', 'term_name', 'term_parent_slug', 'term_description', 'term_meta_json',
            'relationship_type', 'related_skus',
            'custom_meta_json',
            'media_url', 'media_context', 'media_alt',
        );
    }

    private function new_row_template()
    {
        $row = array();
        foreach ($this->get_canonical_headers() as $header) {
            $row[$header] = '';
        }
        return $row;
    }

    private function decode_json_field($raw)
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw)) {
            return array();
        }
        $raw = trim($raw);
        if ($raw === '') {
            return array();
        }
        $decoded = json_decode($raw, true);
        return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : array();
    }

    private function row_error(&$state, $row, $message)
    {
        $state['errors'][] = $message;
        $state['summary']['rows_failed']++;
        $failed_row = is_array($row) ? $row : array();
        $failed_row['error'] = $message;
        $state['failed_rows'][] = $failed_row;
        $this->push_import_log($state, $message, 'error');
    }

    private function push_import_log(&$state, $message, $level = 'info')
    {
        if (!isset($state['logs']) || !is_array($state['logs'])) {
            return;
        }

        $message = trim(wp_strip_all_tags((string) $message));
        if ($message === '') {
            return;
        }

        $max_lines = (int) apply_filters('mulopimfwc_import_log_max_lines', 600);
        if ($max_lines < 100) {
            $max_lines = 100;
        }

        if (count($state['logs']) >= $max_lines) {
            if (empty($state['log_truncated'])) {
                $state['logs'][] = array(
                    'level' => 'warning',
                    'message' => __('Log output truncated to keep response size safe.', 'multi-location-product-and-inventory-management'),
                );
                $state['log_truncated'] = true;
            }
            return;
        }

        $level = strtolower((string) $level);
        if (!in_array($level, array('info', 'success', 'warning', 'error'), true)) {
            $level = 'info';
        }

        $state['logs'][] = array(
            'level' => $level,
            'message' => $message,
        );
    }

    private function group_rows_by_type($rows)
    {
        $grouped = array();
        foreach ($rows as $row) {
            $row_type = isset($row['row_type']) ? sanitize_key((string) $row['row_type']) : '';
            if ($row_type === '') {
                continue;
            }
            if (!isset($grouped[$row_type])) {
                $grouped[$row_type] = array();
            }
            $grouped[$row_type][] = $row;
        }
        return $grouped;
    }

    private function ids_to_skus($ids)
    {
        $skus = array();
        foreach ((array) $ids as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $product = wc_get_product($id);
            if (!$product) {
                continue;
            }
            $sku = (string) $product->get_sku();
            if ($sku !== '') {
                $skus[] = $sku;
            }
        }
        return array_values(array_unique($skus));
    }

    private function ids_to_sku_csv($ids)
    {
        return implode('|', $this->ids_to_skus($ids));
    }

    private function find_product_ids_by_sku($sku, $target_type)
    {
        global $wpdb;
        $sku = (string) $sku;
        if ($sku === '') {
            return array();
        }
        $post_type = $target_type === 'variation' ? 'product_variation' : 'product';
        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT p.ID
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
                 WHERE pm.meta_key = '_sku'
                 AND pm.meta_value = %s
                 AND p.post_type = %s
                 AND p.post_status <> 'trash'",
                $sku,
                $post_type
            )
        );
        return array_values(array_unique(array_map('intval', (array) $ids)));
    }

    private function should_write_field($value, $runtime)
    {
        $value = (string) $value;
        if ($value !== '') {
            return true;
        }
        return $runtime['field_mode'] === 'set_and_clear';
    }

    private function apply_string_field($product, $method, $row, $field, $runtime, $allowed_values = null)
    {
        if (!method_exists($product, $method)) {
            return;
        }
        if (!array_key_exists($field, $row)) {
            return;
        }
        $value = (string) $row[$field];
        if (!$this->should_write_field($value, $runtime)) {
            return;
        }
        if ($value === '' && $runtime['field_mode'] === 'set_and_clear') {
            $product->{$method}('');
            return;
        }
        if (is_array($allowed_values) && !in_array($value, $allowed_values, true)) {
            return;
        }
        $product->{$method}($value);
    }

    private function apply_decimal_field($product, $method, $row, $field, $runtime)
    {
        if (!method_exists($product, $method)) {
            return;
        }
        if (!array_key_exists($field, $row)) {
            return;
        }
        $value = trim((string) $row[$field]);
        if (!$this->should_write_field($value, $runtime)) {
            return;
        }
        if ($value === '' && $runtime['field_mode'] === 'set_and_clear') {
            $product->{$method}('');
            return;
        }
        if (!is_numeric($value)) {
            return;
        }
        $product->{$method}(wc_format_decimal($value));
    }

    private function apply_int_field($product, $method, $row, $field, $runtime, $allow_negative_one = false)
    {
        if (!method_exists($product, $method)) {
            return;
        }
        if (!array_key_exists($field, $row)) {
            return;
        }
        $value = trim((string) $row[$field]);
        if (!$this->should_write_field($value, $runtime)) {
            return;
        }
        if ($value === '' && $runtime['field_mode'] === 'set_and_clear') {
            $product->{$method}(0);
            return;
        }
        if (!is_numeric($value)) {
            return;
        }
        $int_value = (int) $value;
        if (!$allow_negative_one && $int_value < 0) {
            return;
        }
        $product->{$method}($int_value);
    }

    private function apply_bool_field($product, $method, $row, $field, $runtime)
    {
        if (!method_exists($product, $method)) {
            return;
        }
        if (!array_key_exists($field, $row)) {
            return;
        }
        $value = trim((string) $row[$field]);
        if (!$this->should_write_field($value, $runtime)) {
            return;
        }
        if ($value === '' && $runtime['field_mode'] === 'set_and_clear') {
            $product->{$method}(false);
            return;
        }
        $truthy = in_array(strtolower($value), array('1', 'yes', 'true', 'on', 'y'), true);
        $product->{$method}($truthy);
    }

    private function sanitize_meta_whitelist($raw_whitelist)
    {
        if (is_string($raw_whitelist)) {
            $raw_whitelist = explode(',', $raw_whitelist);
        }
        if (!is_array($raw_whitelist)) {
            return array();
        }
        $keys = array();
        foreach ($raw_whitelist as $key) {
            $key = sanitize_key((string) $key);
            if ($key === '' || in_array($key, $this->meta_blacklist, true)) {
                continue;
            }
            $keys[] = $key;
        }
        return array_values(array_unique($keys));
    }

    private function get_sanitized_term_meta($term_id)
    {
        $all_meta = get_term_meta((int) $term_id);
        $clean = array();
        if (!is_array($all_meta)) {
            return $clean;
        }
        foreach ($all_meta as $meta_key => $values) {
            $meta_key = sanitize_key((string) $meta_key);
            if ($meta_key === '') {
                continue;
            }
            if (is_array($values) && count($values) === 1) {
                $clean[$meta_key] = maybe_unserialize($values[0]);
            } else {
                $clean[$meta_key] = array_map('maybe_unserialize', (array) $values);
            }
        }
        return $clean;
    }

    private function get_term_slug($term_id, $taxonomy)
    {
        $term = get_term((int) $term_id, $taxonomy);
        if (!$term || is_wp_error($term)) {
            return '';
        }
        return (string) $term->slug;
    }

    private function capture_product_snapshot_once($product_id, &$state)
    {
        $product_id = (int) $product_id;
        if ($product_id <= 0 || isset($state['snapshot']['products'][$product_id])) {
            return;
        }
        $snapshot_limit = (int) apply_filters('mulopimfwc_import_snapshot_limit', 500);
        if ($snapshot_limit > 0 && count($state['snapshot']['products']) >= $snapshot_limit) {
            $state['snapshot_limit_reached'] = true;
            return;
        }
        $post = get_post($product_id, ARRAY_A);
        if (!$post || !is_array($post)) {
            return;
        }

        $taxonomies = get_object_taxonomies(get_post_type($product_id), 'names');
        $terms = array();
        foreach ((array) $taxonomies as $taxonomy) {
            $slugs = wp_get_object_terms($product_id, $taxonomy, array('fields' => 'slugs'));
            if (!is_wp_error($slugs)) {
                $terms[$taxonomy] = array_values(array_map('strval', (array) $slugs));
            }
        }

        $state['snapshot']['products'][$product_id] = array(
            'post' => $post,
            'meta' => get_post_meta($product_id),
            'terms' => $terms,
        );
    }

    private function capture_term_snapshot_once($term_id, &$state)
    {
        $term_id = (int) $term_id;
        if ($term_id <= 0 || isset($state['snapshot']['terms'][$term_id])) {
            return;
        }
        $term = get_term($term_id);
        if (!$term || is_wp_error($term)) {
            return;
        }
        $state['snapshot']['terms'][$term_id] = array(
            'term' => array(
                'taxonomy' => (string) $term->taxonomy,
                'name' => (string) $term->name,
                'slug' => (string) $term->slug,
                'description' => (string) $term->description,
                'parent' => (int) $term->parent,
            ),
            'meta' => get_term_meta($term_id),
        );
    }

    private function restore_snapshot($snapshot)
    {
        if (!is_array($snapshot)) {
            return array('error' => __('Invalid snapshot payload.', 'multi-location-product-and-inventory-management'));
        }
        $products_restored = 0;
        $terms_restored = 0;

        if (isset($snapshot['products']) && is_array($snapshot['products'])) {
            foreach ($snapshot['products'] as $product_id => $product_snapshot) {
                $product_id = (int) $product_id;
                if ($product_id <= 0 || !is_array($product_snapshot)) {
                    continue;
                }
                if (isset($product_snapshot['post']) && is_array($product_snapshot['post'])) {
                    $post_update = $product_snapshot['post'];
                    $post_update['ID'] = $product_id;
                    wp_update_post($post_update);
                }

                global $wpdb;
                $wpdb->delete($wpdb->postmeta, array('post_id' => $product_id));
                if (isset($product_snapshot['meta']) && is_array($product_snapshot['meta'])) {
                    foreach ($product_snapshot['meta'] as $meta_key => $meta_values) {
                        foreach ((array) $meta_values as $meta_value) {
                            add_post_meta($product_id, $meta_key, maybe_unserialize($meta_value));
                        }
                    }
                }
                if (isset($product_snapshot['terms']) && is_array($product_snapshot['terms'])) {
                    foreach ($product_snapshot['terms'] as $taxonomy => $term_slugs) {
                        if (taxonomy_exists($taxonomy)) {
                            wp_set_object_terms($product_id, (array) $term_slugs, $taxonomy, false);
                        }
                    }
                }
                wc_delete_product_transients($product_id);
                $products_restored++;
            }
        }

        if (isset($snapshot['terms']) && is_array($snapshot['terms'])) {
            foreach ($snapshot['terms'] as $term_id => $term_snapshot) {
                $term_id = (int) $term_id;
                if ($term_id <= 0 || !is_array($term_snapshot) || empty($term_snapshot['term'])) {
                    continue;
                }
                $term_data = $term_snapshot['term'];
                $taxonomy = isset($term_data['taxonomy']) ? sanitize_key((string) $term_data['taxonomy']) : '';
                if ($taxonomy === '' || !taxonomy_exists($taxonomy)) {
                    continue;
                }
                wp_update_term($term_id, $taxonomy, array(
                    'name' => isset($term_data['name']) ? (string) $term_data['name'] : '',
                    'slug' => isset($term_data['slug']) ? (string) $term_data['slug'] : '',
                    'description' => isset($term_data['description']) ? (string) $term_data['description'] : '',
                    'parent' => isset($term_data['parent']) ? (int) $term_data['parent'] : 0,
                ));
                global $wpdb;
                $wpdb->delete($wpdb->termmeta, array('term_id' => $term_id));
                if (isset($term_snapshot['meta']) && is_array($term_snapshot['meta'])) {
                    foreach ($term_snapshot['meta'] as $meta_key => $meta_values) {
                        foreach ((array) $meta_values as $meta_value) {
                            add_term_meta($term_id, $meta_key, maybe_unserialize($meta_value));
                        }
                    }
                }
                $terms_restored++;
            }
        }

        if (function_exists('mulopimfwc_bump_dashboard_cache_version')) {
            mulopimfwc_bump_dashboard_cache_version();
        }

        return array(
            'error' => '',
            'products_restored' => $products_restored,
            'terms_restored' => $terms_restored,
        );
    }

    private function record_job($type, $status, $options, $meta)
    {
        $history = get_option(self::OPTION_JOB_HISTORY, array());
        if (!is_array($history)) {
            $history = array();
        }
        $entry = array(
            'id' => wp_generate_uuid4(),
            'type' => sanitize_key((string) $type),
            'status' => sanitize_key((string) $status),
            'created_at_gmt' => gmdate('c'),
            'created_at_local' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'site_url' => home_url(),
            'schema_version' => self::SCHEMA_VERSION,
            'options' => is_array($options) ? $options : array(),
            'meta' => is_array($meta) ? $meta : array(),
        );
        array_unshift($history, $entry);
        if (count($history) > self::MAX_JOB_HISTORY) {
            $history = array_slice($history, 0, self::MAX_JOB_HISTORY);
        }
        update_option(self::OPTION_JOB_HISTORY, $history, false);
        return $entry;
    }

    public function get_job_history($limit = 10)
    {
        $history = get_option(self::OPTION_JOB_HISTORY, array());
        if (!is_array($history)) {
            $history = array();
        }
        $limit = (int) $limit;
        if ($limit <= 0) {
            $limit = 10;
        }
        return array_slice($history, 0, $limit);
    }

    private function build_failed_rows_csv($failed_rows)
    {
        if (!is_array($failed_rows) || empty($failed_rows)) {
            return '';
        }

        $headers = array_unique(array_reduce($failed_rows, function ($carry, $row) {
            if (!is_array($row)) {
                return $carry;
            }
            return array_merge($carry, array_keys($row));
        }, array()));
        if (!in_array('error', $headers, true)) {
            $headers[] = 'error';
        }

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return '';
        }
        fputcsv($stream, $headers);
        foreach ($failed_rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $line = array();
            foreach ($headers as $header) {
                $line[] = isset($row[$header]) ? (string) $row[$header] : '';
            }
            fputcsv($stream, $line);
        }
        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);
        return $csv;
    }
}
