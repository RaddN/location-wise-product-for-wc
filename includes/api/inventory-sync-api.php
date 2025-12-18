<?php
/**
 * Inventory Sync API
 * 
 * REST API endpoints for bulk CSV/API sync and webhook endpoints for WMS/POS systems
 * to push inventory updates in real-time
 * 
 * @package Multi Location Product & Inventory Management for WooCommerce
 * @since 1.0.6.16
 */

if (!defined('ABSPATH')) {
    exit;
}

class MULOPIMFWC_Inventory_Sync_API {
    
    /**
     * API namespace
     */
    private $namespace = 'mulopimfwc/v1';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Bulk inventory sync endpoint
        register_rest_route($this->namespace, '/inventory/bulk-sync', array(
            'methods' => 'POST',
            'callback' => array($this, 'bulk_sync_inventory'),
            'permission_callback' => array($this, 'check_permissions'),
        ));
        
        // Single product inventory update endpoint
        register_rest_route($this->namespace, '/inventory/update', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_inventory'),
            'permission_callback' => array($this, 'check_permissions'),
        ));
        
        // Bulk inventory export endpoint
        register_rest_route($this->namespace, '/inventory/export', array(
            'methods' => 'GET',
            'callback' => array($this, 'export_inventory'),
            'permission_callback' => array($this, 'check_permissions'),
        ));
        
        // Webhook endpoint for WMS/POS systems
        register_rest_route($this->namespace, '/webhook/inventory-update', array(
            'methods' => 'POST',
            'callback' => array($this, 'webhook_inventory_update'),
            'permission_callback' => array($this, 'check_webhook_permissions'),
        ));
        
        // Get locations endpoint
        register_rest_route($this->namespace, '/locations', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_locations'),
            'permission_callback' => array($this, 'check_permissions'),
        ));
        
        // Get products endpoint (for mapping)
        register_rest_route($this->namespace, '/products', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_products'),
            'permission_callback' => array($this, 'check_permissions'),
        ));
    }
    
    /**
     * Check API permissions
     */
    public function check_permissions($request) {
        // Check for API key authentication
        $api_key = $request->get_header('X-API-Key');
        if ($api_key && $this->validate_api_key($api_key)) {
            return true;
        }
        
        // Fallback to WordPress user authentication
        if (current_user_can('manage_woocommerce')) {
            return true;
        }
        
        return new WP_Error('rest_forbidden', __('You do not have permission to access this endpoint.', 'multi-location-product-and-inventory-management'), array('status' => 403));
    }
    
    /**
     * Check webhook permissions (can use separate API key)
     */
    public function check_webhook_permissions($request) {
        // Check for webhook secret
        $webhook_secret = $request->get_header('X-Webhook-Secret');
        if ($webhook_secret && $this->validate_webhook_secret($webhook_secret)) {
            return true;
        }
        
        // Also allow API key
        $api_key = $request->get_header('X-API-Key');
        if ($api_key && $this->validate_api_key($api_key)) {
            return true;
        }
        
        return new WP_Error('rest_forbidden', __('Invalid webhook secret or API key.', 'multi-location-product-and-inventory-management'), array('status' => 403));
    }
    
    /**
     * Validate API key
     */
    private function validate_api_key($api_key) {
        $stored_key = get_option('mulopimfwc_api_key', '');
        return !empty($stored_key) && hash_equals($stored_key, $api_key);
    }
    
    /**
     * Validate webhook secret
     */
    private function validate_webhook_secret($secret) {
        $stored_secret = get_option('mulopimfwc_webhook_secret', '');
        return !empty($stored_secret) && hash_equals($stored_secret, $secret);
    }
    
    /**
     * Bulk sync inventory (CSV/API format)
     */
    public function bulk_sync_inventory($request) {
        $data = $request->get_json_params();
        
        if (empty($data) || !isset($data['items'])) {
            return new WP_Error('invalid_data', __('Invalid data format. Expected "items" array.', 'multi-location-product-and-inventory-management'), array('status' => 400));
        }
        
        $items = $data['items'];
        $results = array(
            'success' => 0,
            'failed' => 0,
            'errors' => array(),
        );
        
        foreach ($items as $item) {
            $result = $this->process_inventory_item($item);
            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = $result['error'];
            }
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => sprintf(__('Processed %d items. %d succeeded, %d failed.', 'multi-location-product-and-inventory-management'), count($items), $results['success'], $results['failed']),
            'results' => $results,
        ));
    }
    
    /**
     * Update single product inventory
     */
    public function update_inventory($request) {
        $data = $request->get_json_params();
        
        $result = $this->process_inventory_item($data);
        
        if ($result['success']) {
            return rest_ensure_response(array(
                'success' => true,
                'message' => __('Inventory updated successfully.', 'multi-location-product-and-inventory-management'),
                'data' => $result['data'],
            ));
        } else {
            return new WP_Error('update_failed', $result['error'], array('status' => 400));
        }
    }
    
    /**
     * Process inventory item
     */
    public function process_inventory_item($item) {
        // Validate required fields
        if (empty($item['product_id']) && empty($item['sku'])) {
            return array('success' => false, 'error' => __('Product ID or SKU is required.', 'multi-location-product-and-inventory-management'));
        }
        
        if (empty($item['location_id']) && empty($item['location_slug'])) {
            return array('success' => false, 'error' => __('Location ID or slug is required.', 'multi-location-product-and-inventory-management'));
        }
        
        // Get product
        $product = null;
        if (!empty($item['product_id'])) {
            $product = wc_get_product($item['product_id']);
        } elseif (!empty($item['sku'])) {
            $product_id = wc_get_product_id_by_sku($item['sku']);
            if ($product_id) {
                $product = wc_get_product($product_id);
            }
        }
        
        if (!$product) {
            return array('success' => false, 'error' => __('Product not found.', 'multi-location-product-and-inventory-management'));
        }
        
        // Get location
        $location_id = null;
        if (!empty($item['location_id'])) {
            $location_id = intval($item['location_id']);
            $term = get_term($location_id, 'mulopimfwc_store_location');
            if (!$term || is_wp_error($term)) {
                return array('success' => false, 'error' => __('Location not found.', 'multi-location-product-and-inventory-management'));
            }
        } elseif (!empty($item['location_slug'])) {
            $term = get_term_by('slug', sanitize_text_field($item['location_slug']), 'mulopimfwc_store_location');
            if (!$term || is_wp_error($term)) {
                return array('success' => false, 'error' => __('Location not found.', 'multi-location-product-and-inventory-management'));
            }
            $location_id = $term->term_id;
        }
        
        $product_id = $product->get_id();
        $is_variation = $product->is_type('variation');
        
        // Update stock if provided
        if (isset($item['stock'])) {
            $stock = floatval($item['stock']);
            update_post_meta($product_id, '_location_stock_' . $location_id, $stock);
            
            // Update main stock if this is the primary location
            $primary_location = get_option('mulopimfwc_primary_location', '');
            if ($primary_location == $location_id || empty($primary_location)) {
                wc_update_product_stock($product_id, $stock);
            }
        }
        
        // Update regular price if provided
        if (isset($item['regular_price'])) {
            $price = wc_format_decimal($item['regular_price']);
            update_post_meta($product_id, '_location_regular_price_' . $location_id, $price);
        }
        
        // Update sale price if provided
        if (isset($item['sale_price'])) {
            $price = wc_format_decimal($item['sale_price']);
            update_post_meta($product_id, '_location_sale_price_' . $location_id, $price);
        }
        
        // Update backorder setting if provided
        if (isset($item['backorders'])) {
            $backorders = sanitize_text_field($item['backorders']);
            update_post_meta($product_id, '_location_backorders_' . $location_id, $backorders);
        }
        
        // Update disabled status if provided
        if (isset($item['disabled'])) {
            $disabled = $item['disabled'] ? 'yes' : 'no';
            update_post_meta($product_id, '_location_disabled_' . $location_id, $disabled);
        }
        
        // Clear product cache
        wc_delete_product_transients($product_id);
        
        return array(
            'success' => true,
            'data' => array(
                'product_id' => $product_id,
                'location_id' => $location_id,
                'sku' => $product->get_sku(),
            ),
        );
    }
    
    /**
     * Export inventory data
     */
    public function export_inventory($request) {
        $location_id = $request->get_param('location_id');
        $format = $request->get_param('format') ?: 'json'; // json or csv
        
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'fields' => 'ids',
        );
        
        $product_ids = get_posts($args);
        $data = array();
        
        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product) {
                continue;
            }
            
            $locations = get_terms(array(
                'taxonomy' => 'mulopimfwc_store_location',
                'hide_empty' => false,
            ));
            
            if (is_wp_error($locations)) {
                continue;
            }
            
            foreach ($locations as $location) {
                // Filter by location if specified
                if ($location_id && $location->term_id != $location_id) {
                    continue;
                }
                
                $item = array(
                    'product_id' => $product_id,
                    'sku' => $product->get_sku(),
                    'product_name' => $product->get_name(),
                    'location_id' => $location->term_id,
                    'location_slug' => $location->slug,
                    'location_name' => $location->name,
                    'stock' => get_post_meta($product_id, '_location_stock_' . $location->term_id, true),
                    'regular_price' => get_post_meta($product_id, '_location_regular_price_' . $location->term_id, true),
                    'sale_price' => get_post_meta($product_id, '_location_sale_price_' . $location->term_id, true),
                    'backorders' => get_post_meta($product_id, '_location_backorders_' . $location->term_id, true),
                    'disabled' => get_post_meta($product_id, '_location_disabled_' . $location->term_id, true) === 'yes',
                );
                
                // Handle variations
                if ($product->is_type('variable')) {
                    $variations = $product->get_children();
                    foreach ($variations as $variation_id) {
                        $variation = wc_get_product($variation_id);
                        if (!$variation) {
                            continue;
                        }
                        
                        $variation_item = $item;
                        $variation_item['product_id'] = $variation_id;
                        $variation_item['sku'] = $variation->get_sku();
                        $variation_item['product_name'] = $variation->get_name();
                        $variation_item['stock'] = get_post_meta($variation_id, '_location_stock_' . $location->term_id, true);
                        $variation_item['regular_price'] = get_post_meta($variation_id, '_location_regular_price_' . $location->term_id, true);
                        $variation_item['sale_price'] = get_post_meta($variation_id, '_location_sale_price_' . $location->term_id, true);
                        $variation_item['backorders'] = get_post_meta($variation_id, '_location_backorders_' . $location->term_id, true);
                        $variation_item['disabled'] = get_post_meta($variation_id, '_location_disabled_' . $location->term_id, true) === 'yes';
                        
                        $data[] = $variation_item;
                    }
                } else {
                    $data[] = $item;
                }
            }
        }
        
        if ($format === 'csv') {
            return $this->send_csv_response($data);
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'count' => count($data),
            'data' => $data,
        ));
    }
    
    /**
     * Send CSV response
     */
    private function send_csv_response($data) {
        if (empty($data)) {
            return new WP_Error('no_data', __('No data to export.', 'multi-location-product-and-inventory-management'), array('status' => 404));
        }
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=inventory-export-' . date('Y-m-d-His') . '.csv');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Headers
        $headers = array('product_id', 'sku', 'product_name', 'location_id', 'location_slug', 'location_name', 'stock', 'regular_price', 'sale_price', 'backorders', 'disabled');
        fputcsv($output, $headers);
        
        // Data rows
        foreach ($data as $row) {
            fputcsv($output, array(
                $row['product_id'],
                $row['sku'],
                $row['product_name'],
                $row['location_id'],
                $row['location_slug'],
                $row['location_name'],
                $row['stock'],
                $row['regular_price'],
                $row['sale_price'],
                $row['backorders'],
                $row['disabled'] ? 'yes' : 'no',
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Webhook endpoint for WMS/POS systems
     */
    public function webhook_inventory_update($request) {
        $data = $request->get_json_params();
        
        // Log webhook request (optional)
        if (get_option('mulopimfwc_log_webhooks', 'no') === 'yes') {
            $this->log_webhook($data);
        }
        
        // Process the update
        if (isset($data['product_id']) || isset($data['sku'])) {
            $result = $this->process_inventory_item($data);
            
            if ($result['success']) {
                // Trigger action for other plugins to hook into
                do_action('mulopimfwc_webhook_inventory_updated', $result['data'], $data);
                
                return rest_ensure_response(array(
                    'success' => true,
                    'message' => __('Inventory updated via webhook.', 'multi-location-product-and-inventory-management'),
                    'data' => $result['data'],
                ));
            } else {
                return new WP_Error('webhook_failed', $result['error'], array('status' => 400));
            }
        }
        
        return new WP_Error('invalid_data', __('Invalid webhook data format.', 'multi-location-product-and-inventory-management'), array('status' => 400));
    }
    
    /**
     * Get locations
     */
    public function get_locations($request) {
        $locations = get_terms(array(
            'taxonomy' => 'mulopimfwc_store_location',
            'hide_empty' => false,
        ));
        
        if (is_wp_error($locations)) {
            return new WP_Error('locations_error', __('Failed to retrieve locations.', 'multi-location-product-and-inventory-management'), array('status' => 500));
        }
        
        $data = array();
        foreach ($locations as $location) {
            $data[] = array(
                'id' => $location->term_id,
                'slug' => $location->slug,
                'name' => $location->name,
                'description' => $location->description,
            );
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'count' => count($data),
            'data' => $data,
        ));
    }
    
    /**
     * Get products (for mapping)
     */
    public function get_products($request) {
        $per_page = $request->get_param('per_page') ?: 100;
        $page = $request->get_param('page') ?: 1;
        $search = $request->get_param('search');
        
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'post_status' => 'publish',
            'fields' => 'ids',
        );
        
        if ($search) {
            $args['s'] = sanitize_text_field($search);
        }
        
        $product_ids = get_posts($args);
        $data = array();
        
        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product) {
                continue;
            }
            
            $data[] = array(
                'id' => $product_id,
                'sku' => $product->get_sku(),
                'name' => $product->get_name(),
                'type' => $product->get_type(),
            );
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'count' => count($data),
            'page' => $page,
            'per_page' => $per_page,
            'data' => $data,
        ));
    }
    
    /**
     * Log webhook request
     */
    private function log_webhook($data) {
        $log_file = WP_CONTENT_DIR . '/uploads/mulopimfwc-webhook-log-' . date('Y-m-d') . '.log';
        $log_entry = date('Y-m-d H:i:s') . ' - ' . json_encode($data) . PHP_EOL;
        @file_put_contents($log_file, $log_entry, FILE_APPEND);
    }
}

// Initialize the API
new MULOPIMFWC_Inventory_Sync_API();

