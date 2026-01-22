<?php
// Add this to your class or in a separate file that's included in your plugin

class mulopimfwc_Import_Export
{

    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_mulopimfwc_export_settings', [$this, 'export_settings']);
        add_action('wp_ajax_mulopimfwc_import_settings', [$this, 'import_settings']);
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

        wp_enqueue_script(
            'mulopimfwc-import-export',
            MULTI_LOCATION_PLUGIN_URL . 'assets/js/import-export.js',
            ['jquery'],
            '1.1.1.14',
            true
        );

        wp_localize_script('mulopimfwc-import-export', 'mulopimfwcImportExport', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mulopimfwc_import_export_nonce'),
            'strings' => [
                'exporting' => __('Exporting settings...', 'multi-location-product-and-inventory-management'),
                'export_success' => __('Settings exported successfully!', 'multi-location-product-and-inventory-management'),
                'export_error' => __('Error exporting settings. Please try again.', 'multi-location-product-and-inventory-management'),
                'importing' => __('Importing settings...', 'multi-location-product-and-inventory-management'),
                'import_success' => __('Settings imported successfully! Please refresh the page.', 'multi-location-product-and-inventory-management'),
                'import_error' => __('Error importing settings. Please ensure you selected a valid JSON file.', 'multi-location-product-and-inventory-management'),
                'invalid_file' => __('Please select a valid JSON file.', 'multi-location-product-and-inventory-management'),
                'confirm_import' => __('This will overwrite your current settings. Are you sure you want to continue?', 'multi-location-product-and-inventory-management')
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
                'message' => __('You do not have permission to export settings.', 'multi-location-product-and-inventory-management')
            ]);
        }

        // Get all plugin settings
        $settings = get_option('mulopimfwc_display_options', []);

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
                'message' => __('You do not have permission to import settings.', 'multi-location-product-and-inventory-management')
            ]);
        }

        // Get the JSON data from POST
        $json_data = isset($_POST['import_data']) ? stripslashes($_POST['import_data']) : '';

        if (empty($json_data)) {
            wp_send_json_error([
                'message' => __('No data received.', 'multi-location-product-and-inventory-management')
            ]);
        }

        // Decode JSON
        $import_data = json_decode($json_data, true);

        // Validate JSON
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error([
                'message' => __('Invalid JSON file. Error: ', 'multi-location-product-and-inventory-management') . json_last_error_msg()
            ]);
        }

        // Validate data structure - check if it's the expected format
        if (
            !isset($import_data['plugin']) ||
            $import_data['plugin'] !== 'Multi Location Product & Inventory Management for WooCommerce'
        ) {
            wp_send_json_error([
                'message' => __('This file does not appear to be a valid settings export from this plugin.', 'multi-location-product-and-inventory-management')
            ]);
        }

        if (!isset($import_data['settings']) || !is_array($import_data['settings'])) {
            wp_send_json_error([
                'message' => __('Invalid settings file format. Settings data not found.', 'multi-location-product-and-inventory-management')
            ]);
        }

        // Check if settings array is empty
        if (empty($import_data['settings'])) {
            wp_send_json_error([
                'message' => __('The settings file contains no data to import.', 'multi-location-product-and-inventory-management')
            ]);
        }

        // Sanitize imported settings
        $sanitized_settings = $this->sanitize_imported_settings($import_data['settings']);

        // Backup current settings before importing
        $current_settings = get_option('mulopimfwc_display_options', []);
        update_option('mulopimfwc_display_options_backup_' . time(), $current_settings);

        // Update settings
        $updated = update_option('mulopimfwc_display_options', $sanitized_settings);

        if ($updated || $sanitized_settings === $current_settings) {
            wp_send_json_success([
                'message' => __('Settings imported successfully!', 'multi-location-product-and-inventory-management'),
                'imported_count' => count($sanitized_settings)
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to update settings. Please try again.', 'multi-location-product-and-inventory-management')
            ]);
        }
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



add_action('wp_ajax_mulopimfwc_export_products_csv', 'mulopimfwc_export_products_csv_handler');
add_action('wp_ajax_mulopimfwc_import_inventory_csv', 'mulopimfwc_import_inventory_csv_handler');

function mulopimfwc_export_products_csv_handler() {
    // Verify nonce
    check_ajax_referer('mulopimfwc_import_export_nonce', 'nonce');
    
    // Check user permissions
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(['message' => __('You do not have permission to export products.', 'multi-location-product-and-inventory-management')]);
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
    
    // Get all products
    $args = [
        'post_type' => 'product',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'fields' => 'ids',
    ];
    
    $product_ids = get_posts($args);
    $products_data = [];
    
    foreach ($product_ids as $product_id) {
        $product = wc_get_product($product_id);
        
        if (!$product) {
            continue;
        }
        
        $product_type = $product->get_type();
        
        // Get location data for the product
        $location_data = [];
        foreach ($locations as $location) {
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
        
        // Handle variable products
        if ($product_type === 'variable') {
            $available_variations = $product->get_available_variations();
            
            foreach ($available_variations as $variation) {
                $variation_id = $variation['variation_id'];
                $variation_obj = wc_get_product($variation_id);
                
                if (!$variation_obj) {
                    continue;
                }
                
                // Get location data for variation
                $variation_location_data = [];
                foreach ($locations as $location) {
                    $variation_location_data[$location->term_id] = [
                        'stock' => get_post_meta($variation_id, '_location_stock_' . $location->term_id, true),
                        'price' => get_post_meta($variation_id, '_location_sale_price_' . $location->term_id, true),
                        'disabled' => get_post_meta($variation_id, '_location_disabled_' . $location->term_id, true),
                    ];
                }
                
                // Format attributes for display
                $attributes_label = [];
                foreach ($variation['attributes'] as $key => $value) {
                    $attr_name = ucfirst(str_replace('attribute_pa_', '', $key));
                    $attributes_label[] = $attr_name . ': ' . $value;
                }
                
                $product_info['variations'][] = [
                    'id' => $variation_id,
                    'attributes' => $variation['attributes'],
                    'attributes_label' => implode(', ', $attributes_label),
                    'price' => $variation['display_price'],
                    'stock' => $variation['is_in_stock'] ? $variation['max_qty'] : 0,
                    'sku' => $variation_obj->get_sku(),
                    'purchase_price' => get_post_meta($variation_id, '_purchase_price', true),
                    'location_data' => $variation_location_data,
                ];
            }
        }
        
        $products_data[] = $product_info;
    }
    
    // Format locations for frontend
    $locations_formatted = [];
    foreach ($locations as $location) {
        $locations_formatted[] = [
            'id' => $location->term_id,
            'name' => $location->name,
            'slug' => rawurldecode($location->slug),
        ];
    }
    
    wp_send_json_success([
        'products' => $products_data,
        'locations' => $locations_formatted,
        'count' => count($products_data),
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
        wp_send_json_error(['message' => __('You do not have permission to import inventory.', 'multi-location-product-and-inventory-management')]);
        return;
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error(['message' => __('Please select a valid CSV file.', 'multi-location-product-and-inventory-management')]);
        return;
    }
    
    $file = $_FILES['csv_file'];
    $file_path = $file['tmp_name'];
    
    // Validate file type
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($file_ext !== 'csv') {
        wp_send_json_error(['message' => __('Invalid file type. Please upload a CSV file.', 'multi-location-product-and-inventory-management')]);
        return;
    }
    
    // Parse CSV
    $handle = fopen($file_path, 'r');
    if ($handle === false) {
        wp_send_json_error(['message' => __('Failed to read CSV file.', 'multi-location-product-and-inventory-management')]);
        return;
    }
    
    // Read headers
    $headers = fgetcsv($handle);
    if ($headers === false) {
        fclose($handle);
        wp_send_json_error(['message' => __('Invalid CSV format.', 'multi-location-product-and-inventory-management')]);
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
        wp_send_json_error(['message' => __('CSV must contain at least one of: product_id, sku, location_id, or location_slug.', 'multi-location-product-and-inventory-management')]);
        return;
    }
    
    $results = array(
        'success' => 0,
        'failed' => 0,
        'errors' => array(),
    );
    
    $line_number = 1;
    
    // Process rows
    while (($row = fgetcsv($handle)) !== false) {
        $line_number++;
        
        if (count($row) !== count($headers)) {
            $results['failed']++;
            $results['errors'][] = sprintf(__('Line %d: Column count mismatch.', 'multi-location-product-and-inventory-management'), $line_number);
            continue;
        }
        
        // Combine headers with row data
        $data = array_combine($headers, $row);
        
        // Prepare item for API processing
        $item = array();
        
        // Product identification
        if (!empty($data['product_id'])) {
            $item['product_id'] = intval($data['product_id']);
        } elseif (!empty($data['sku'])) {
            $item['sku'] = sanitize_text_field($data['sku']);
        } else {
            $results['failed']++;
            $results['errors'][] = sprintf(__('Line %d: Product ID or SKU is required.', 'multi-location-product-and-inventory-management'), $line_number);
            continue;
        }
        
        // Location identification
        if (!empty($data['location_id'])) {
            $item['location_id'] = intval($data['location_id']);
        } elseif (!empty($data['location_slug'])) {
            $item['location_slug'] = sanitize_text_field($data['location_slug']);
        } else {
            $results['failed']++;
            $results['errors'][] = sprintf(__('Line %d: Location ID or slug is required.', 'multi-location-product-and-inventory-management'), $line_number);
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
            $results['errors'][] = sprintf(__('Line %d: %s', 'multi-location-product-and-inventory-management'), $line_number, $result['error']);
        }
    }
    
    fclose($handle);
    
    wp_send_json_success(array(
        'message' => sprintf(__('Import completed. %d succeeded, %d failed.', 'multi-location-product-and-inventory-management'), $results['success'], $results['failed']),
        'results' => $results,
    ));
}

