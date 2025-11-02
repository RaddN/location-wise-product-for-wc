<?php
/**
 * Location Business Hours Purchase Restriction
 * Add this code to your main plugin file or create a new file in the includes folder
 */

class MULOPIMFWC_Business_Hours_Restriction {
    
    public function __construct() {
        // Hook into product purchasable check
        add_filter('woocommerce_is_purchasable', array($this, 'check_location_hours'), 10, 2);
        
        // Add notice when products are not purchasable due to closed location
        add_action('woocommerce_single_product_summary', array($this, 'display_closed_location_notice'), 15);
        
        // Add notice on shop pages
        add_action('woocommerce_before_shop_loop', array($this, 'display_shop_closed_notice'), 5);
        
        // Prevent adding to cart when location is closed
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_add_to_cart'), 10, 3);
        
        // Hide add to cart button with custom message
        add_filter('woocommerce_product_add_to_cart_text', array($this, 'custom_add_to_cart_text'), 10, 2);
        add_filter('woocommerce_product_single_add_to_cart_text', array($this, 'custom_add_to_cart_text'), 10, 2);
    }
    
    /**
     * Check if current selected location is open
     */
    private function is_current_location_open() {
        // Get current selected location
        $location_slug = isset($_COOKIE['mulopimfwc_store_location']) 
            ? sanitize_text_field(wp_unslash($_COOKIE['mulopimfwc_store_location'])) 
            : '';
        
        // If no location selected or "all-products", allow purchase
        if (empty($location_slug) || $location_slug === 'all-products') {
            return true;
        }
        
        // Get location term
        $location_term = get_term_by('slug', $location_slug, 'mulopimfwc_store_location');
        
        if (!$location_term || is_wp_error($location_term)) {
            return true; // If location not found, allow purchase
        }
        
        // Use the existing is_location_open_now method from MULOPIMFWC_Admin class
        global $MULOPIMFWC_Admin;
        
        if (!$MULOPIMFWC_Admin) {
            return true;
        }
        
        $status = $MULOPIMFWC_Admin->is_location_open_now($location_term->term_id);
        
        return $status['open'];
    }
    
    /**
     * Get current location information
     */
    private function get_current_location_info() {
        $location_slug = isset($_COOKIE['mulopimfwc_store_location']) 
            ? sanitize_text_field(wp_unslash($_COOKIE['mulopimfwc_store_location'])) 
            : '';
        
        if (empty($location_slug) || $location_slug === 'all-products') {
            return null;
        }
        
        $location_term = get_term_by('slug', $location_slug, 'mulopimfwc_store_location');
        
        if (!$location_term || is_wp_error($location_term)) {
            return null;
        }
        
        global $MULOPIMFWC_Admin;
        
        if (!$MULOPIMFWC_Admin) {
            return null;
        }
        
        $status = $MULOPIMFWC_Admin->is_location_open_now($location_term->term_id);
        
        return array(
            'name' => $location_term->name,
            'is_open' => $status['open'],
            'next_change' => $status['next_change'],
            'timezone' => $status['now']->getTimezone()->getName()
        );
    }
    
    /**
     * Make products unpurchasable when location is closed
     */
    public function check_location_hours($is_purchasable, $product) {
        global $mulopimfwc_options;
        
        // Check if restriction is enabled 
        $restrict_hours = isset($mulopimfwc_options['restrict_purchase_to_open_hours']) && mulopimfwc_premium_feature()
            ? $mulopimfwc_options['restrict_purchase_to_open_hours'] 
            : 'off';
        
        if ($restrict_hours !== 'on') {
            return $is_purchasable;
        }
        
        // Check if current location is open
        if (!$this->is_current_location_open()) {
            return false;
        }
        
        return $is_purchasable;
    }
    
    /**
     * Display notice on single product page when location is closed
     */
    public function display_closed_location_notice() {
        global $mulopimfwc_options;
        
        $restrict_hours = isset($mulopimfwc_options['restrict_purchase_to_open_hours'])  && mulopimfwc_premium_feature()
            ? $mulopimfwc_options['restrict_purchase_to_open_hours'] 
            : 'off';
        
        if ($restrict_hours !== 'on') {
            return;
        }
        
        $location_info = $this->get_current_location_info();
        
        if (!$location_info || $location_info['is_open']) {
            return;
        }
        
        $message = sprintf(
            __('%s is currently closed. Products cannot be purchased at this time.', 'multi-location-product-and-inventory-management'),
            '<strong>' . esc_html($location_info['name']) . '</strong>'
        );
        
        // Add next opening time if available
        if ($location_info['next_change']) {
            $next_change = $location_info['next_change'];
            $message .= ' ' . sprintf(
                __('Opens at %s.', 'multi-location-product-and-inventory-management'),
                $next_change->format('g:i A')
            );
        }
        
        echo '<div class="woocommerce-info mulopimfwc-closed-location-notice">';
        echo wp_kses_post($message);
        echo '</div>';
    }
    
    /**
     * Display notice on shop pages when location is closed
     */
    public function display_shop_closed_notice() {
        global $mulopimfwc_options;
        
        $restrict_hours = isset($mulopimfwc_options['restrict_purchase_to_open_hours'])  && mulopimfwc_premium_feature()
            ? $mulopimfwc_options['restrict_purchase_to_open_hours'] 
            : 'off';
        
        if ($restrict_hours !== 'on') {
            return;
        }
        
        $location_info = $this->get_current_location_info();
        
        if (!$location_info || $location_info['is_open']) {
            return;
        }
        
        $message = sprintf(
            __('%s is currently closed. You can browse products but cannot make purchases at this time.', 'multi-location-product-and-inventory-management'),
            '<strong>' . esc_html($location_info['name']) . '</strong>'
        );
        
        if ($location_info['next_change']) {
            $next_change = $location_info['next_change'];
            $message .= ' ' . sprintf(
                __('We open at %s.', 'multi-location-product-and-inventory-management'),
                $next_change->format('g:i A')
            );
        }
        
        wc_print_notice($message, 'notice');
    }
    
    /**
     * Validate add to cart when location is closed
     */
    public function validate_add_to_cart($passed, $product_id, $quantity) {
        global $mulopimfwc_options;
        
        $restrict_hours = isset($mulopimfwc_options['restrict_purchase_to_open_hours']) && mulopimfwc_premium_feature()
            ? $mulopimfwc_options['restrict_purchase_to_open_hours'] 
            : 'off';
        
        if ($restrict_hours !== 'on') {
            return $passed;
        }
        
        $location_info = $this->get_current_location_info();
        
        if (!$location_info) {
            return $passed;
        }
        
        if (!$location_info['is_open']) {
            $message = sprintf(
                __('Cannot add to cart. %s is currently closed.', 'multi-location-product-and-inventory-management'),
                esc_html($location_info['name'])
            );
            
            if ($location_info['next_change']) {
                $next_change = $location_info['next_change'];
                $message .= ' ' . sprintf(
                    __('Opens at %s.', 'multi-location-product-and-inventory-management'),
                    $next_change->format('g:i A')
                );
            }
            
            wc_add_notice($message, 'error');
            return false;
        }
        
        return $passed;
    }
    
    /**
     * Change add to cart button text when location is closed
     */
    public function custom_add_to_cart_text($text, $product) {
        global $mulopimfwc_options;
        
        $restrict_hours = isset($mulopimfwc_options['restrict_purchase_to_open_hours']) && mulopimfwc_premium_feature()
            ? $mulopimfwc_options['restrict_purchase_to_open_hours'] 
            : 'off';
        
        if ($restrict_hours !== 'on') {
            return $text;
        }
        
        if (!$this->is_current_location_open()) {
            return __('Location Closed', 'multi-location-product-and-inventory-management');
        }
        
        return $text;
    }
}

// Initialize the class
new MULOPIMFWC_Business_Hours_Restriction();