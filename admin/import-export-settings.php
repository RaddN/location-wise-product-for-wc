<?php
// Add this to your class or in a separate file that's included in your plugin

if (!defined('ABSPATH')) exit;

class mulopimfwc_Import_Export
{

    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_mulopimfwc_export_settings', [$this, 'export_settings']);
        add_action('wp_ajax_mulopimfwc_import_settings', [$this, 'import_settings']);
        add_action('wp_ajax_mulopimfwc_clear_cache', [$this, 'clear_cache']);
    }

    /**
     * Enqueue scripts for import/export functionality
     */
    public function enqueue_scripts($hook)
    {
        // Only load on your settings page
        // if ($hook !== 'toplevel_page_location-wise-products' && 
        //     $hook !== 'multi-location-product_page_location-wise-products-settings') {
        //     return;
        // }

        $script_version = '2.0.1';
        $script_path = plugin_dir_path(dirname(__FILE__)) . 'assets/js/import-export.js';
        if (file_exists($script_path)) {
            $script_version = (string) filemtime($script_path);
        }

        wp_enqueue_script(
            'mulopimfwc-import-export',
            MULTI_LOCATION_PLUGIN_URL . 'assets/js/import-export.js',
            ['jquery'],
            $script_version,
            true
        );

        $ie_v2_enabled = function_exists('mulopimfwc_get_import_export_v2_service')
            ? (bool) mulopimfwc_get_import_export_v2_service()->is_v2_enabled()
            : false;

        wp_localize_script('mulopimfwc-import-export', 'mulopimfwcImportExport', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mulopimfwc_import_export_nonce'),
            'ie_v2_enabled' => $ie_v2_enabled,
            'full_export_action' => 'mulopimfwc_export_full_products_csv',
            'full_import_action' => 'mulopimfwc_import_full_products_csv',
            'restore_snapshot_action' => 'mulopimfwc_restore_import_snapshot',
            'ie_actions' => [
                'start_export' => 'mulopimfwc_ie_start_export',
                'start_import' => 'mulopimfwc_ie_start_import',
                'upload_chunk' => 'mulopimfwc_ie_upload_chunk',
                'finish_upload' => 'mulopimfwc_ie_finish_upload',
                'start_dry_run' => 'mulopimfwc_ie_start_dry_run',
                'confirm_apply' => 'mulopimfwc_ie_confirm_apply',
                'get_job_status' => 'mulopimfwc_ie_get_job_status',
                'get_job_events' => 'mulopimfwc_ie_get_job_events',
                'get_active_jobs' => 'mulopimfwc_ie_get_active_jobs',
                'pause_job' => 'mulopimfwc_ie_pause_job',
                'resume_job' => 'mulopimfwc_ie_resume_job',
                'cancel_job' => 'mulopimfwc_ie_cancel_job',
                'download_artifact' => 'mulopimfwc_ie_download_artifact',
            ],
            'upload_chunk_size' => 8 * 1024 * 1024,
            'max_upload_bytes' => 2 * 1024 * 1024 * 1024,
            'strings' => [
                'exporting' => __('Exporting settings...', 'multi-location-product-and-inventory-management-pro'),
                'export_success' => __('Settings exported successfully!', 'multi-location-product-and-inventory-management-pro'),
                'export_error' => __('Error exporting settings. Please try again.', 'multi-location-product-and-inventory-management-pro'),
                'importing' => __('Importing settings...', 'multi-location-product-and-inventory-management-pro'),
                'import_success' => __('Settings imported successfully! Please refresh the page.', 'multi-location-product-and-inventory-management-pro'),
                'import_error' => __('Error importing settings. Please ensure you selected a valid JSON file.', 'multi-location-product-and-inventory-management-pro'),
                'invalid_file' => __('Please select a valid JSON file.', 'multi-location-product-and-inventory-management-pro'),
                'invalid_csv_file' => __('Please select a valid CSV file.', 'multi-location-product-and-inventory-management-pro'),
                'confirm_import' => __('This will overwrite your current settings. Are you sure you want to continue?', 'multi-location-product-and-inventory-management-pro'),
                'confirm_dry_run_apply' => __('Dry run completed. Continue with actual import now?', 'multi-location-product-and-inventory-management-pro'),
                'clearing_cache' => __('Clearing cache...', 'multi-location-product-and-inventory-management-pro'),
                'clear_cache_success' => __('Cache cleared successfully.', 'multi-location-product-and-inventory-management-pro'),
                'clear_cache_error' => __('Error clearing cache. Please try again.', 'multi-location-product-and-inventory-management-pro'),
                'confirm_clear_cache' => __('Clear cached plugin data now?', 'multi-location-product-and-inventory-management-pro'),
                'full_exporting' => __('Preparing full product and location export...', 'multi-location-product-and-inventory-management-pro'),
                'full_importing_dry_run' => __('Running dry-run validation...', 'multi-location-product-and-inventory-management-pro'),
                'full_importing_apply' => __('Applying import...', 'multi-location-product-and-inventory-management-pro'),
                'full_import_success' => __('Import completed successfully.', 'multi-location-product-and-inventory-management-pro'),
                'full_import_error' => __('Import failed. Please review the report.', 'multi-location-product-and-inventory-management-pro'),
                'job_queued' => __('Job queued. Processing in background...', 'multi-location-product-and-inventory-management-pro'),
                'uploading_chunks' => __('Uploading file in chunks...', 'multi-location-product-and-inventory-management-pro'),
                'preparing_import' => __('Preparing import package...', 'multi-location-product-and-inventory-management-pro'),
                'awaiting_confirmation' => __('Dry-run completed. Waiting for apply confirmation.', 'multi-location-product-and-inventory-management-pro'),
            ]
        ]);
    }

    /**
     * Handle settings export
     */
    public function export_settings()
    {
        // Verify nonce
        check_ajax_referer('mulopimfwc_import_export_nonce', 'nonce');

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('You do not have permission to export settings.', 'multi-location-product-and-inventory-management-pro')
            ]);
        }

        // Get all plugin settings
        global $mulopimfwc_options;
        $settings = is_array($mulopimfwc_options ?? null)
            ? $mulopimfwc_options
            : get_option('mulopimfwc_display_options', []);

        // Add metadata
        $export_data = [
            'version' => '1.0',
            'plugin' => 'Multi Location Product & Inventory Management for WooCommerce',
            'export_date' => current_time('mysql'),
            'site_url' => get_site_url(),
            'settings' => $settings
        ];

        // Return JSON data
        wp_send_json_success([
            'data' => $export_data,
            'filename' => 'mulopimfwc-settings-' . date('Y-m-d-His') . '.json'
        ]);
    }

    public function import_settings()
    {
        // Verify nonce
        check_ajax_referer('mulopimfwc_import_export_nonce', 'nonce');

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('You do not have permission to import settings.', 'multi-location-product-and-inventory-management-pro')
            ]);
        }

        // Get the JSON data from POST - use wp_unslash instead of stripslashes
        $json_data = isset($_POST['import_data']) ? wp_unslash($_POST['import_data']) : '';

        if (empty($json_data)) {
            wp_send_json_error([
                'message' => __('No data received.', 'multi-location-product-and-inventory-management-pro')
            ]);
        }

        // Decode JSON
        $import_data = json_decode($json_data, true);

        // Validate JSON
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error([
                'message' => __('Invalid JSON file. Error: ', 'multi-location-product-and-inventory-management-pro') . json_last_error_msg()
            ]);
        }

        // Validate data structure - check if it's the expected format
        if (
            !isset($import_data['plugin']) ||
            $import_data['plugin'] !== 'Multi Location Product & Inventory Management for WooCommerce'
        ) {
            wp_send_json_error([
                'message' => __('This file does not appear to be a valid settings export from this plugin.', 'multi-location-product-and-inventory-management-pro')
            ]);
        }

        if (!isset($import_data['settings']) || !is_array($import_data['settings'])) {
            wp_send_json_error([
                'message' => __('Invalid settings file format. Settings data not found.', 'multi-location-product-and-inventory-management-pro')
            ]);
        }

        // Check if settings array is empty
        if (empty($import_data['settings'])) {
            wp_send_json_error([
                'message' => __('The settings file contains no data to import.', 'multi-location-product-and-inventory-management-pro')
            ]);
        }

        // Sanitize imported settings
        $sanitized_settings = $this->sanitize_imported_settings($import_data['settings']);

        // Backup current settings before importing
        $current_settings = is_array($mulopimfwc_options ?? null)
            ? $mulopimfwc_options
            : get_option('mulopimfwc_display_options', []);
        update_option('mulopimfwc_display_options_backup_' . time(), $current_settings);

        // Update settings
        $updated = update_option('mulopimfwc_display_options', $sanitized_settings);

        if ($updated || $sanitized_settings === $current_settings) {
            wp_send_json_success([
                'message' => __('Settings imported successfully!', 'multi-location-product-and-inventory-management-pro'),
                'imported_count' => count($sanitized_settings)
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to update settings. Please try again.', 'multi-location-product-and-inventory-management-pro')
            ]);
        }
    }

    /**
     * Clear plugin cache (transients + object cache).
     */
    public function clear_cache()
    {
        check_ajax_referer('mulopimfwc_import_export_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('You do not have permission to clear cache.', 'multi-location-product-and-inventory-management-pro')
            ]);
        }

        global $wpdb;

        $like_key = $wpdb->esc_like('_transient_mulopimfwc_') . '%';
        $like_timeout = $wpdb->esc_like('_transient_timeout_mulopimfwc_') . '%';

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $like_key,
                $like_timeout
            )
        );

        $deleted = is_numeric($deleted) ? (int) $deleted : 0;
        $cache_flushed = false;
        if (function_exists('wp_cache_flush')) {
            $cache_flushed = (bool) wp_cache_flush();
        }
        if (function_exists('mulopimfwc_bump_dashboard_cache_version')) {
            mulopimfwc_bump_dashboard_cache_version();
        }

        $message = sprintf(
            /* translators: %d: number of cached entries removed */
            __('Cache cleared. Removed %d cached entries.', 'multi-location-product-and-inventory-management-pro'),
            $deleted
        );

        wp_send_json_success([
            'message' => $message,
            'deleted' => $deleted,
            'cache_flushed' => $cache_flushed
        ]);
    }

    /**
     * Sanitize imported settings - improved version
     */
    private function sanitize_imported_settings($settings)
    {
        $sanitized = [];

        foreach ($settings as $key => $value) {
            if (is_array($value)) {
                // Recursively sanitize arrays
                $sanitized[$key] = array_map(function ($item) {
                    return is_string($item) ? sanitize_text_field($item) : $item;
                }, $value);
            } elseif (is_string($value)) {
                // Sanitize strings, but preserve formatting for certain fields
                if ($key === 'mulopimfwc_popup_custom_css') {
                    $sanitized[$key] = wp_strip_all_tags($value);
                } else {
                    $sanitized[$key] = sanitize_text_field($value);
                }
            } else {
                // Keep other types as-is (boolean, numeric, etc.)
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}

// Initialize the class
new mulopimfwc_Import_Export();

require_once plugin_dir_path(__FILE__) . '../includes/class-stock-central-import-export.php';
require_once plugin_dir_path(__FILE__) . '../includes/class-import-export-v2.php';

function mulopimfwc_get_stock_central_import_export_service()
{
    static $service = null;
    if ($service === null) {
        $service = new MULOPIMFWC_Stock_Central_Import_Export_Service();
    }
    return $service;
}

function mulopimfwc_get_import_export_v2_service()
{
    static $service = null;
    if ($service === null) {
        $service = new MULOPIMFWC_Import_Export_V2_Service(mulopimfwc_get_stock_central_import_export_service());
        $service->register_hooks();
    }
    return $service;
}

// Ensure worker hooks and schema checks are registered on every request.
mulopimfwc_get_import_export_v2_service();

add_action('wp_ajax_mulopimfwc_export_full_products_csv', 'mulopimfwc_export_full_products_csv_handler');
add_action('wp_ajax_mulopimfwc_import_full_products_csv', 'mulopimfwc_import_full_products_csv_handler');
add_action('wp_ajax_mulopimfwc_restore_import_snapshot', 'mulopimfwc_restore_import_snapshot_handler');

add_action('wp_ajax_mulopimfwc_ie_start_export', 'mulopimfwc_ie_start_export_handler');
add_action('wp_ajax_mulopimfwc_ie_start_import', 'mulopimfwc_ie_start_import_handler');
add_action('wp_ajax_mulopimfwc_ie_upload_chunk', 'mulopimfwc_ie_upload_chunk_handler');
add_action('wp_ajax_mulopimfwc_ie_finish_upload', 'mulopimfwc_ie_finish_upload_handler');
add_action('wp_ajax_mulopimfwc_ie_start_dry_run', 'mulopimfwc_ie_start_dry_run_handler');
add_action('wp_ajax_mulopimfwc_ie_confirm_apply', 'mulopimfwc_ie_confirm_apply_handler');
add_action('wp_ajax_mulopimfwc_ie_get_job_status', 'mulopimfwc_ie_get_job_status_handler');
add_action('wp_ajax_mulopimfwc_ie_get_job_events', 'mulopimfwc_ie_get_job_events_handler');
add_action('wp_ajax_mulopimfwc_ie_get_active_jobs', 'mulopimfwc_ie_get_active_jobs_handler');
add_action('wp_ajax_mulopimfwc_ie_pause_job', 'mulopimfwc_ie_pause_job_handler');
add_action('wp_ajax_mulopimfwc_ie_resume_job', 'mulopimfwc_ie_resume_job_handler');
add_action('wp_ajax_mulopimfwc_ie_cancel_job', 'mulopimfwc_ie_cancel_job_handler');
add_action('wp_ajax_mulopimfwc_ie_download_artifact', 'mulopimfwc_ie_download_artifact_handler');

function mulopimfwc_export_full_products_csv_handler()
{
    $v2 = mulopimfwc_get_import_export_v2_service();
    if ($v2->is_v2_enabled()) {
        $v2->handle_legacy_export_proxy_ajax();
        return;
    }
    mulopimfwc_get_stock_central_import_export_service()->handle_export_ajax();
}

function mulopimfwc_import_full_products_csv_handler()
{
    $v2 = mulopimfwc_get_import_export_v2_service();
    if ($v2->is_v2_enabled()) {
        $v2->handle_legacy_import_proxy_ajax();
        return;
    }
    mulopimfwc_get_stock_central_import_export_service()->handle_import_ajax();
}

function mulopimfwc_restore_import_snapshot_handler()
{
    mulopimfwc_get_stock_central_import_export_service()->handle_restore_snapshot_ajax();
}

function mulopimfwc_ie_start_export_handler()
{
    mulopimfwc_get_import_export_v2_service()->ajax_start_export();
}

function mulopimfwc_ie_start_import_handler()
{
    mulopimfwc_get_import_export_v2_service()->ajax_start_import();
}

function mulopimfwc_ie_upload_chunk_handler()
{
    mulopimfwc_get_import_export_v2_service()->ajax_upload_chunk();
}

function mulopimfwc_ie_finish_upload_handler()
{
    mulopimfwc_get_import_export_v2_service()->ajax_finish_upload();
}

function mulopimfwc_ie_start_dry_run_handler()
{
    mulopimfwc_get_import_export_v2_service()->ajax_start_dry_run();
}

function mulopimfwc_ie_confirm_apply_handler()
{
    mulopimfwc_get_import_export_v2_service()->ajax_confirm_apply();
}

function mulopimfwc_ie_get_job_status_handler()
{
    mulopimfwc_get_import_export_v2_service()->ajax_get_job_status();
}

function mulopimfwc_ie_get_job_events_handler()
{
    mulopimfwc_get_import_export_v2_service()->ajax_get_job_events();
}

function mulopimfwc_ie_get_active_jobs_handler()
{
    mulopimfwc_get_import_export_v2_service()->ajax_get_active_jobs();
}

function mulopimfwc_ie_pause_job_handler()
{
    mulopimfwc_get_import_export_v2_service()->ajax_pause_job();
}

function mulopimfwc_ie_resume_job_handler()
{
    mulopimfwc_get_import_export_v2_service()->ajax_resume_job();
}

function mulopimfwc_ie_cancel_job_handler()
{
    mulopimfwc_get_import_export_v2_service()->ajax_cancel_job();
}

function mulopimfwc_ie_download_artifact_handler()
{
    mulopimfwc_get_import_export_v2_service()->ajax_download_artifact();
}



add_action('wp_ajax_mulopimfwc_export_products_csv', 'mulopimfwc_export_products_csv_handler');
add_action('wp_ajax_mulopimfwc_import_inventory_csv', 'mulopimfwc_import_inventory_csv_handler');

function mulopimfwc_export_products_csv_handler() {
    // Verify nonce
    check_ajax_referer('mulopimfwc_import_export_nonce', 'nonce');
    
    // Check user permissions
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(['message' => __('You do not have permission to export products.', 'multi-location-product-and-inventory-management-pro')]);
        return;
    }
    
    // Get all locations
    $locations = get_terms([
        'taxonomy' => 'mulopimfwc_store_location',
        'hide_empty' => false,
    ]);
    
    if (is_wp_error($locations)) {
        $locations = [];
    }
    
    // FIXED: Improved memory management with streaming approach (Issue #7)
    // Use a reasonable batch size (250 products per batch to reduce memory usage)
    $batch_size = apply_filters('mulopimfwc_export_batch_size', 250);
    $page = 1;
    $total_processed = 0;
    $max_products = apply_filters('mulopimfwc_export_max_products', 50000); // Limit total exports
    
    // Increase memory limit for export (but more conservatively)
    if (function_exists('ini_set')) {
        $current_limit = ini_get('memory_limit');
        $current_bytes = wp_convert_hr_to_bytes($current_limit);
        $min_bytes = wp_convert_hr_to_bytes('256M');
        if ($current_bytes < $min_bytes) {
            @ini_set('memory_limit', '256M');
        }
    }
    @set_time_limit(600); // Increased timeout for large exports
    
    // FIXED: Use generator pattern to process in chunks and clear memory
    $products_data = [];
    $memory_usage_start = memory_get_usage(true);
    
    do {
        $args = [
            'post_type' => 'product',
            'posts_per_page' => $batch_size,
            'paged' => $page,
            'post_status' => 'publish',
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
        ];
        
        $product_ids = get_posts($args);
        
        if (empty($product_ids)) {
            break;
        }
        
        // Process batch
        $batch_data = [];
        foreach ($product_ids as $product_id) {
            // Check memory usage and break if getting too high
            $memory_usage = memory_get_usage(true);
            $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
            if ($memory_usage > ($memory_limit * 0.8)) { // Stop at 80% of limit
                break 2;
            }
            
            $product = wc_get_product($product_id);
            
            if (!$product) {
                continue;
            }
            
            $product_type = $product->get_type();
            
            // Get location data for the product (only for existing locations)
            $location_data = [];
            foreach ($locations as $location) {
                if (!$location || is_wp_error($location)) {
                    continue;
                }
                $location_data[$location->term_id] = [
                    'stock' => get_post_meta($product_id, '_location_stock_' . $location->term_id, true),
                    'price' => get_post_meta($product_id, '_location_sale_price_' . $location->term_id, true),
                    'disabled' => get_post_meta($product_id, '_location_disabled_' . $location->term_id, true),
                ];
            }
            
            $product_info = [
                'id' => $product_id,
                'title' => $product->get_name(),
                'type' => $product_type,
                'sku' => $product->get_sku(),
                'default_stock' => get_post_meta($product_id, '_stock', true),
                'default_price' => get_post_meta($product_id, '_price', true),
                'purchase_price' => get_post_meta($product_id, '_purchase_price', true),
                'purchase_quantity' => get_post_meta($product_id, '_purchase_quantity', true),
                'location_data' => $location_data,
                'variations' => [],
            ];
            
            // Handle variable products (limit variations to prevent memory issues)
            if ($product_type === 'variable') {
                $variation_ids = $product->get_children();
                $max_variations = apply_filters('mulopimfwc_export_max_variations', 100);
                $variation_ids = array_slice($variation_ids, 0, $max_variations);
                
                foreach ($variation_ids as $variation_id) {
                    $variation_obj = wc_get_product($variation_id);
                    
                    if (!$variation_obj) {
                        continue;
                    }
                    
                    // Get location data for variation
                    $variation_location_data = [];
                    foreach ($locations as $location) {
                        if (!$location || is_wp_error($location)) {
                            continue;
                        }
                        $variation_location_data[$location->term_id] = [
                            'stock' => get_post_meta($variation_id, '_location_stock_' . $location->term_id, true),
                            'price' => get_post_meta($variation_id, '_location_sale_price_' . $location->term_id, true),
                            'disabled' => get_post_meta($variation_id, '_location_disabled_' . $location->term_id, true),
                        ];
                    }
                    
                    // Get variation attributes
                    $attributes = $variation_obj->get_attributes();
                    $attributes_label = [];
                    foreach ($attributes as $key => $value) {
                        $attr_name = wc_attribute_label(str_replace('attribute_', '', $key), $product);
                        $attributes_label[] = $attr_name . ': ' . $value;
                    }
                    
                    $product_info['variations'][] = [
                        'id' => $variation_id,
                        'attributes' => $attributes,
                        'attributes_label' => implode(', ', $attributes_label),
                        'price' => $variation_obj->get_price(),
                        'stock' => $variation_obj->get_stock_quantity(),
                        'sku' => $variation_obj->get_sku(),
                        'purchase_price' => get_post_meta($variation_id, '_purchase_price', true),
                        'location_data' => $variation_location_data,
                    ];
                    
                    // Clear variation object from memory
                    unset($variation_obj);
                }
            }
            
            $batch_data[] = $product_info;
            
            // Clear product object from memory
            unset($product);
        }
        
        // Add batch to main array
        $products_data = array_merge($products_data, $batch_data);
        $total_processed += count($batch_data);
        
        // Clear batch data from memory
        unset($batch_data);
        unset($product_ids);
        
        // Force garbage collection every 5 batches
        if ($page % 5 === 0) {
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }
        
        // Increment page for next batch
        $page++;
        
        // Safety checks: limit total batches and total products
        if ($page > 200 || $total_processed >= $max_products) { // Max 50,000 products (250 * 200)
            break;
        }
        
    } while (count($product_ids ?? []) === $batch_size);
    
    // Format locations for frontend (validate locations exist)
    $locations_formatted = [];
    foreach ($locations as $location) {
        if (!$location || is_wp_error($location)) {
            continue;
        }
        // FIXED: Validate location exists before adding (Issue #13)
        $validated_location = get_term($location->term_id, 'mulopimfwc_store_location');
        if ($validated_location && !is_wp_error($validated_location)) {
            $locations_formatted[] = [
                'id' => $location->term_id,
                'name' => $location->name,
                'slug' => rawurldecode($location->slug),
            ];
        }
    }
    
    wp_send_json_success([
        'products' => $products_data,
        'locations' => $locations_formatted,
        'count' => count($products_data),
        'total_processed' => $total_processed,
        'memory_used' => size_format(memory_get_usage(true) - $memory_usage_start),
    ]);
}

/**
 * Handle CSV import for inventory
 */
function mulopimfwc_import_inventory_csv_handler() {
    // Verify nonce
    check_ajax_referer('mulopimfwc_import_export_nonce', 'nonce');
    
    // Check user permissions
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(['message' => __('You do not have permission to import inventory.', 'multi-location-product-and-inventory-management-pro')]);
        return;
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error(['message' => __('Please select a valid CSV file.', 'multi-location-product-and-inventory-management-pro')]);
        return;
    }
    
    $file = $_FILES['csv_file'];
    
    // FIXED: Validate file size to prevent memory exhaustion
    $max_file_size = apply_filters('mulopimfwc_max_csv_import_size', 10 * 1024 * 1024); // Default 10MB
    if ($file['size'] > $max_file_size) {
        wp_send_json_error([
            'message' => sprintf(
                /* translators: %s: formatted maximum file size (e.g. 10 MB) */
                __('File size exceeds maximum allowed size of %s. Please split your file into smaller chunks.', 'multi-location-product-and-inventory-management-pro'),
                size_format($max_file_size)
            )
        ]);
        return;
    }
    
    // Additional validation: check if file is actually a CSV
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($file_ext !== 'csv') {
        wp_send_json_error(['message' => __('Invalid file type. Please upload a CSV file.', 'multi-location-product-and-inventory-management-pro')]);
        return;
    }
    $file_path = $file['tmp_name'];
    
    // Validate file type
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($file_ext !== 'csv') {
        wp_send_json_error(['message' => __('Invalid file type. Please upload a CSV file.', 'multi-location-product-and-inventory-management-pro')]);
        return;
    }
    
    // Parse CSV
    $handle = fopen($file_path, 'r');
    if ($handle === false) {
        wp_send_json_error(['message' => __('Failed to read CSV file.', 'multi-location-product-and-inventory-management-pro')]);
        return;
    }
    
    // Read headers
    $headers = fgetcsv($handle);
    if ($headers === false) {
        fclose($handle);
        wp_send_json_error(['message' => __('Invalid CSV format.', 'multi-location-product-and-inventory-management-pro')]);
        return;
    }
    
    // Normalize headers (remove BOM if present)
    $headers = array_map(function($header) {
        return trim(str_replace("\xEF\xBB\xBF", '', $header));
    }, $headers);
    
    // Expected columns
    $expected_columns = ['product_id', 'sku', 'location_id', 'location_slug', 'stock', 'regular_price', 'sale_price', 'backorders', 'disabled'];
    
    // Validate headers
    $missing_columns = array_diff(['product_id', 'sku', 'location_id', 'location_slug'], $headers);
    if (count($missing_columns) === 4) {
        fclose($handle);
        wp_send_json_error(['message' => __('CSV must contain at least one of: product_id, sku, location_id, or location_slug.', 'multi-location-product-and-inventory-management-pro')]);
        return;
    }
    
    $results = array(
        'success' => 0,
        'failed' => 0,
        'errors' => array(),
    );
    
    $line_number = 1;
    
    // FIXED: Add validation for location existence before processing
    $validated_locations = array(); // Cache validated locations
    
    // Process rows
    while (($row = fgetcsv($handle)) !== false) {
        $line_number++;
        
        // FIXED: Limit number of rows to prevent memory exhaustion
        $max_rows = apply_filters('mulopimfwc_max_csv_import_rows', 10000);
        if ($line_number > $max_rows) {
            $results['errors'][] = sprintf(/* translators: 1: current row number, 2: maximum rows allowed */ __('Import stopped at row %1$d. Maximum %2$d rows allowed per import.', 'multi-location-product-and-inventory-management-pro'), $line_number, $max_rows);
            break;
        }
        
        if (count($row) !== count($headers)) {
            $results['failed']++;
            $results['errors'][] = sprintf(/* translators: %d: line number in CSV */ __('Line %d: Column count mismatch.', 'multi-location-product-and-inventory-management-pro'), $line_number);
            continue;
        }
        
        // Combine headers with row data
        $data = array_combine($headers, $row);
        
        // FIXED: Validate location exists before processing
        $location_id = null;
        $location_slug = null;
        
        if (!empty($data['location_id'])) {
            $location_id = absint($data['location_id']);
            $term = get_term($location_id, 'mulopimfwc_store_location');
            if (!$term || is_wp_error($term)) {
                $results['failed']++;
                $results['errors'][] = sprintf(/* translators: 1: line number, 2: invalid location ID */ __('Line %1$d: Invalid location ID %2$d.', 'multi-location-product-and-inventory-management-pro'), $line_number, $location_id);
                continue;
            }
        } elseif (!empty($data['location_slug'])) {
            $location_slug = sanitize_text_field($data['location_slug']);
            if (!isset($validated_locations[$location_slug])) {
                $term = get_term_by('slug', $location_slug, 'mulopimfwc_store_location');
                if (!$term || is_wp_error($term)) {
                    $validated_locations[$location_slug] = false;
                    $results['failed']++;
                    $results['errors'][] = sprintf(/* translators: 1: line number, 2: invalid location slug */ __('Line %1$d: Invalid location slug "%2$s".', 'multi-location-product-and-inventory-management-pro'), $line_number, $location_slug);
                    continue;
                }
                $validated_locations[$location_slug] = $term->term_id;
            }
            if ($validated_locations[$location_slug] === false) {
                continue; // Already logged error
            }
            $location_id = $validated_locations[$location_slug];
        } else {
            $results['failed']++;
            $results['errors'][] = sprintf(/* translators: %d: line number in CSV */ __('Line %d: Missing location_id or location_slug.', 'multi-location-product-and-inventory-management-pro'), $line_number);
            continue;
        }
        
        // Prepare item for API processing
        $item = array();
        
        // Product identification
        if (!empty($data['product_id'])) {
            $item['product_id'] = intval($data['product_id']);
        } elseif (!empty($data['sku'])) {
            $item['sku'] = sanitize_text_field($data['sku']);
        } else {
            $results['failed']++;
            $results['errors'][] = sprintf(/* translators: %d: line number in CSV */ __('Line %d: Product ID or SKU is required.', 'multi-location-product-and-inventory-management-pro'), $line_number);
            continue;
        }
        
        // Location identification
        if (!empty($data['location_id'])) {
            $item['location_id'] = intval($data['location_id']);
        } elseif (!empty($data['location_slug'])) {
            $item['location_slug'] = sanitize_text_field($data['location_slug']);
        } else {
            $results['failed']++;
            $results['errors'][] = sprintf(/* translators: %d: line number in CSV */ __('Line %d: Location ID or slug is required.', 'multi-location-product-and-inventory-management-pro'), $line_number);
            continue;
        }
        
        // Stock
        if (isset($data['stock']) && $data['stock'] !== '') {
            $item['stock'] = floatval($data['stock']);
        }
        
        // Prices
        if (isset($data['regular_price']) && $data['regular_price'] !== '') {
            $item['regular_price'] = floatval($data['regular_price']);
        }
        
        if (isset($data['sale_price']) && $data['sale_price'] !== '') {
            $item['sale_price'] = floatval($data['sale_price']);
        }
        
        // Backorders
        if (isset($data['backorders']) && $data['backorders'] !== '') {
            $item['backorders'] = sanitize_text_field($data['backorders']);
        }
        
        // Disabled
        if (isset($data['disabled']) && $data['disabled'] !== '') {
            $item['disabled'] = in_array(strtolower($data['disabled']), ['yes', '1', 'true', 'y'], true);
        }
        
        // Process the item using the API class
        $api_instance = new MULOPIMFWC_Inventory_Sync_API();
        $result = $api_instance->process_inventory_item($item);
        
        if ($result['success']) {
            $results['success']++;
        } else {
            $results['failed']++;
            $results['errors'][] = sprintf(/* translators: 1: line number in CSV, 2: error message */ __('Line %1$d: %2$s', 'multi-location-product-and-inventory-management-pro'), $line_number, $result['error']);
        }
    }
    
    fclose($handle);
    
    wp_send_json_success(array(
        'message' => sprintf(/* translators: 1: number of succeeded rows, 2: number of failed rows */ __('Import completed. %1$d succeeded, %2$d failed.', 'multi-location-product-and-inventory-management-pro'), $results['success'], $results['failed']),
        'results' => $results,
    ));
}

