<?php
if (!defined('ABSPATH')) exit;


// stock manage, price manage, backorder manage

// Add a new product data tab for location-specific settings
add_filter('woocommerce_product_data_tabs', function($tabs) {
    $tabs['location_stock_price'] = array(
        'label'    => __('Location Settings', 'location-wise-product'),
        'target'   => 'location_stock_price_options',
        'class'    => array('show_if_simple', 'hide_if_variable','show_if_external'),
        'priority' => 21
    );
    return $tabs;
});

// Add location-specific fields to the product data panel
add_action('woocommerce_product_data_panels', function() {
    global $post;
    $product = wc_get_product($post->ID);
    $locations = get_terms(array(
        'taxonomy' => 'store_location',
        'hide_empty' => false,
    ));
    
    ?>
    <div id="location_stock_price_options" class="panel woocommerce_options_panel">
        <div class="options_group">
            <h3><?php esc_html_e('Location Specific Stock & Price Settings', 'location-wise-product'); ?></h3>
            <?php wp_nonce_field('location_stock_price_nonce_action', 'location_stock_price_nonce'); ?>
            <?php if (!empty($locations) && !is_wp_error($locations)) : ?>
                <table class="widefat">

                    <thead>
                        <tr>
                            <th><?php esc_html_e('Location', 'location-wise-product'); ?></th>
                            <th><?php esc_html_e('Stock Quantity', 'location-wise-product'); ?></th>
                            <th><?php esc_html_e('Regular Price', 'location-wise-product'); ?></th>
                            <th><?php esc_html_e('Sale Price', 'location-wise-product'); ?></th>
                            <th><?php esc_html_e('Backorders', 'location-wise-product'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($locations as $location) : 
                            $location_stock = get_post_meta($post->ID, '_location_stock_' . $location->term_id, true);
                            $location_regular_price = get_post_meta($post->ID, '_location_regular_price_' . $location->term_id, true);
                            $location_sale_price = get_post_meta($post->ID, '_location_sale_price_' . $location->term_id, true);
                            $location_backorders = get_post_meta($post->ID, '_location_backorders_' . $location->term_id, true);
                        ?>
                            <tr>
                                <td><?php echo esc_html($location->name); ?></td>
                                <td>
                                    <input type="number" name="location_stock[<?php echo esc_attr($location->term_id); ?>]" 
                                           value="<?php echo esc_attr($location_stock); ?>" step="1" min="0">
                                </td>
                                <td>
                                    <input type="text" name="location_regular_price[<?php echo esc_attr($location->term_id); ?>]" 
                                           value="<?php echo esc_attr($location_regular_price); ?>" class="wc_input_price">
                                </td>
                                <td>
                                    <input type="text" name="location_sale_price[<?php echo esc_attr($location->term_id); ?>]" 
                                           value="<?php echo esc_attr($location_sale_price); ?>" class="wc_input_price">
                                </td>
                                <td>
                                    <select name="location_backorders[<?php echo esc_attr($location->term_id); ?>]">
                                        <option value="no" <?php selected($location_backorders, 'no'); ?>><?php esc_html_e('No backorders', 'location-wise-product'); ?></option>
                                        <option value="notify" <?php selected($location_backorders, 'notify'); ?>><?php esc_html_e('Allow, but notify customer', 'location-wise-product'); ?></option>
                                        <option value="yes" <?php selected($location_backorders, 'yes'); ?>><?php esc_html_e('Allow', 'location-wise-product'); ?></option>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php esc_html_e('No store locations found. Please add locations first.', 'location-wise-product'); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php
});


// Save location-specific data for simple products
add_action('woocommerce_process_product_meta', function($post_id) {
    // Verify nonce
    if (!isset($_POST['location_stock_price_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['location_stock_price_nonce'])), 'location_stock_price_nonce_action')) {
        return;
    }

    // Save location stock
        if (isset($_POST['location_stock']) && is_array($_POST['location_stock'])) {
            foreach (array_map('sanitize_text_field', wp_unslash($_POST['location_stock'])) as $location_id => $stock) {
                if (is_numeric($location_id) && is_numeric($stock)) {
                    update_post_meta($post_id, '_location_stock_' . intval($location_id), wc_clean($stock));
                }
            }
        }
    
    // Save location regular prices
    if (isset($_POST['location_regular_price']) && is_array($_POST['location_regular_price'])) {
        foreach (array_map('sanitize_text_field', wp_unslash($_POST['location_regular_price'])) as $location_id => $price) {
            if (is_numeric($location_id) && is_numeric($price)) {
                update_post_meta($post_id, '_location_regular_price_' . intval($location_id), wc_format_decimal($price));
            }
        }
    }
    
    // Save location sale prices
    if (isset($_POST['location_sale_price']) && is_array($_POST['location_sale_price'])) {
        foreach (array_map('sanitize_text_field', wp_unslash($_POST['location_sale_price'])) as $location_id => $price) {
            if (is_numeric($location_id) && is_numeric($price)) {
                update_post_meta($post_id, '_location_sale_price_' . intval($location_id), wc_format_decimal($price));
            }
        }
    }
    
    // Save location backorder settings
    if (isset($_POST['location_backorders']) && is_array($_POST['location_backorders'])) {
        foreach (array_map('sanitize_text_field', wp_unslash($_POST['location_backorders'])) as $location_id => $backorders) {
            if (is_numeric($location_id)) {
                update_post_meta($post_id, '_location_backorders_' . intval($location_id), sanitize_text_field($backorders));
            }
        }
    }
});



// Add location fields to each variation
add_action('woocommerce_product_after_variable_attributes', function($loop, $variation_data, $variation) {
    $locations = get_terms(array(
        'taxonomy' => 'store_location',
        'hide_empty' => false,
    ));
    
    if (empty($locations) || is_wp_error($locations)) {
        return;
    }
    
    ?>
    <div class="variable_location_pricing">
        <p class="form-row form-row-full"><strong><?php esc_html_e('Location Specific Settings', 'location-wise-product'); ?></strong></p>
        <?php wp_nonce_field('location_stock_price_nonce_action', 'location_stock_price_nonce'); ?>
        <div class="location_variation_data">
            <table class="location_variation_table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Location', 'location-wise-product'); ?></th>
                        <th><?php esc_html_e('Stock', 'location-wise-product'); ?></th>
                        <th><?php esc_html_e('Regular Price', 'location-wise-product'); ?></th>
                        <th><?php esc_html_e('Sale Price', 'location-wise-product'); ?></th>
                        <th><?php esc_html_e('Backorders', 'location-wise-product'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($locations as $location) : 
                        $location_stock = get_post_meta($variation->ID, '_location_stock_' . $location->term_id, true);
                        $location_regular_price = get_post_meta($variation->ID, '_location_regular_price_' . $location->term_id, true);
                        $location_sale_price = get_post_meta($variation->ID, '_location_sale_price_' . $location->term_id, true);
                        $location_backorders = get_post_meta($variation->ID, '_location_backorders_' . $location->term_id, true);
                    ?>
                        <tr>
                            <td><?php echo esc_html($location->name); ?></td>
                            <td>
                                <input type="number" 
                                       name="variation_location_stock[<?php echo esc_attr($loop); ?>][<?php echo esc_attr($location->term_id); ?>]" 
                                       value="<?php echo esc_attr($location_stock); ?>" 
                                       class="short" step="1" min="0">
                            </td>
                            <td>
                                <input type="text" 
                                       name="variation_location_regular_price[<?php echo esc_attr($loop); ?>][<?php echo esc_attr($location->term_id); ?>]" 
                                       value="<?php echo esc_attr($location_regular_price); ?>" 
                                       class="wc_input_price short">
                            </td>
                            <td>
                                <input type="text" 
                                       name="variation_location_sale_price[<?php echo esc_attr($loop); ?>][<?php echo esc_attr($location->term_id); ?>]" 
                                       value="<?php echo esc_attr($location_sale_price); ?>" 
                                       class="wc_input_price short">
                            </td>
                            <td>
                                <select name="variation_location_backorders[<?php echo esc_attr($loop); ?>][<?php echo esc_attr($location->term_id); ?>]">
                                    <option value="no" <?php selected($location_backorders, 'no'); ?>><?php esc_html_e('No backorders', 'location-wise-product'); ?></option>
                                    <option value="notify" <?php selected($location_backorders, 'notify'); ?>><?php esc_html_e('Allow, but notify customer', 'location-wise-product'); ?></option>
                                    <option value="yes" <?php selected($location_backorders, 'yes'); ?>><?php esc_html_e('Allow', 'location-wise-product'); ?></option>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}, 10, 3);

// Save location data for variations
add_action('woocommerce_save_product_variation', function($variation_id, $loop) {
    // Verify nonce
    if (!isset($_POST['location_stock_price_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['location_stock_price_nonce'])), 'location_stock_price_nonce_action')) {
        return;
    }
    // Save variation location stock
    if (isset($_POST['variation_location_stock'][$loop]) && is_array($_POST['variation_location_stock'][$loop])) {
        foreach (array_map('sanitize_text_field', wp_unslash($_POST['variation_location_stock'][$loop])) as $location_id => $stock) {
            if (is_numeric($location_id) && is_numeric($stock)) {
                update_post_meta($variation_id, '_location_stock_' . intval($location_id), wc_clean($stock));
            }
        }
    }
    
    // Save variation location regular prices
    if (isset($_POST['variation_location_regular_price'][$loop]) && is_array($_POST['variation_location_regular_price'][$loop])) {
        foreach (array_map('sanitize_text_field', wp_unslash($_POST['variation_location_regular_price'][$loop])) as $location_id => $price) {
            if (is_numeric($location_id) && is_numeric($price)) {
                update_post_meta($variation_id, '_location_regular_price_' . intval($location_id), wc_format_decimal($price));
            }
        }
    }
    
    // Save variation location sale prices
    if (isset($_POST['variation_location_sale_price'][$loop]) && is_array($_POST['variation_location_sale_price'][$loop])) {
        foreach (array_map('sanitize_text_field', wp_unslash($_POST['variation_location_sale_price'][$loop])) as $location_id => $price) {
            if (is_numeric($location_id) && is_numeric($price)) {
                update_post_meta($variation_id, '_location_sale_price_' . intval($location_id), wc_format_decimal($price));
            }
        }
    }
    
    // Save variation location backorder settings
    if (isset($_POST['variation_location_backorders'][$loop]) && is_array($_POST['variation_location_backorders'][$loop])) {
        foreach (array_map('sanitize_text_field', wp_unslash($_POST['variation_location_backorders'][$loop])) as $location_id => $backorders) {
            if (is_numeric($location_id)) {
                update_post_meta($variation_id, '_location_backorders_' . intval($location_id), sanitize_text_field($backorders));
            }
        }
    }
}, 10, 2);


// Get current location
function get_current_store_location() {
    return isset($_COOKIE['store_location']) ? sanitize_text_field(wp_unslash($_COOKIE['store_location'])) : '';
}

// Get location term ID from slug
function get_location_term_id($location_slug) {
    if (empty($location_slug)) {
        return false;
    }
    
    $location = get_term_by('slug', $location_slug, 'store_location');
    return $location ? $location->term_id : false;
}

if((get_option('lwp_display_options', ['enable_location_price' => 'yes'])['enable_location_price'] ?? 'yes') === 'yes'){
// Override regular price for simple products
add_filter('woocommerce_product_get_regular_price', function($price, $product) {
    if ($product->is_type('variation')) {
        return $price; // Handle variations separately
    }
    
    $location_slug = get_current_store_location();
    $location_id = get_location_term_id($location_slug);
    
    if (!$location_id) {
        return $price;
    }
    
    $location_price = get_post_meta($product->get_id(), '_location_regular_price_' . $location_id, true);
    
    return !empty($location_price) ? $location_price : $price;
}, 10, 2);
// Override sale price for simple products
add_filter('woocommerce_product_get_sale_price', function($price, $product) {
    if ($product->is_type('variation')) {
        return $price; // Handle variations separately
    }
    
    $location_slug = get_current_store_location();
    $location_id = get_location_term_id($location_slug);
    
    if (!$location_id) {
        return $price;
    }
    
    $location_price = get_post_meta($product->get_id(), '_location_sale_price_' . $location_id, true);
    
    return !empty($location_price) ? $location_price : $price;
}, 10, 2);
}

if((get_option('lwp_display_options', ['enable_location_stock' => 'yes'])['enable_location_stock'] ?? 'yes') === 'yes'){
// Override stock quantity for simple products
add_filter('woocommerce_product_get_stock_quantity', function($quantity, $product) {
    if ($product->is_type('variation')) {
        return $quantity; // Handle variations separately
    }
    
    $location_slug = get_current_store_location();
    $location_id = get_location_term_id($location_slug);
    
    if (!$location_id) {
        return $quantity;
    }
    
    $location_stock = get_post_meta($product->get_id(), '_location_stock_' . $location_id, true);
    
    return $location_stock !== '' ? $location_stock : $quantity;
}, 10, 2);
}

if((get_option('lwp_display_options', ['enable_location_backorder' => 'yes'])['enable_location_backorder'] ?? 'yes') === 'yes'){

// Override backorder setting for simple products
add_filter('woocommerce_product_get_backorders', function($backorders, $product) {
    if ($product->is_type('variation')) {
        return $backorders; // Handle variations separately
    }
    
    $location_slug = get_current_store_location();
    $location_id = get_location_term_id($location_slug);
    
    if (!$location_id) {
        return $backorders;
    }
    
    $location_backorders = get_post_meta($product->get_id(), '_location_backorders_' . $location_id, true);
    
    return !empty($location_backorders) ? $location_backorders : $backorders;
}, 10, 2);
}
if((get_option('lwp_display_options', ['enable_location_stock' => 'yes'])['enable_location_stock'] ?? 'yes') === 'yes'){
// Override product stock status based on location stock
add_filter('woocommerce_product_get_stock_status', function($status, $product) {
    if ($product->is_type('variation')) {
        return $status; // Handle variations separately
    }
    
    $location_slug = get_current_store_location();
    $location_id = get_location_term_id($location_slug);
    $product_id = $product->get_id();
    
    if (!$location_id) {
        return $status;
    }
    
    $location_stock = get_post_meta($product->get_id(), '_location_stock_' . $location_id, true);
    
    if ($location_stock === '') {
        return $status;
    }
    $terms = wp_get_object_terms($product_id, 'store_location', ['fields' => 'slugs']);

    // if all products is selected
    if ($location_slug === 'all-products') {
        return $status; // Use default WooCommerce stock status
    }
    
    if (!in_array($location_slug, $terms)) {
        return 'outofstock'; // Product is not available in the current location
    }
    
    
    // Get backorder setting
    $backorders = wc_get_product_stock_status_options();
    $location_backorders = get_post_meta($product->get_id(), '_location_backorders_' . $location_id, true);
    
    // Determine stock status based on quantity and backorder setting
    if ($location_stock <= 0 && $location_backorders === 'no') {
        return 'outofstock';
    } elseif ($location_stock <= 0 && $location_backorders !== 'no') {
        return 'onbackorder';
    } else {
        return 'instock';
    }
}, 10, 2);

}

if((get_option('lwp_display_options', ['enable_location_stock' => 'yes'])['enable_location_stock'] ?? 'yes') === 'yes'){

// Override variation stock
add_filter('woocommerce_product_variation_get_stock_quantity', function($quantity, $variation) {
    $location_slug = get_current_store_location();
    $location_id = get_location_term_id($location_slug);
    
    if (!$location_id) {
        return $quantity;
    }
    
    $location_stock = get_post_meta($variation->get_id(), '_location_stock_' . $location_id, true);
    
    return $location_stock !== '' ? $location_stock : $quantity;
}, 10, 2);
}

if((get_option('lwp_display_options', ['enable_location_backorder' => 'yes'])['enable_location_backorder'] ?? 'yes') === 'yes'){
// Override variation backorders
add_filter('woocommerce_product_variation_get_backorders', function($backorders, $variation) {
    $location_slug = get_current_store_location();
    $location_id = get_location_term_id($location_slug);
    
    if (!$location_id) {
        return $backorders;
    }
    
    $location_backorders = get_post_meta($variation->get_id(), '_location_backorders_' . $location_id, true);
    
    return !empty($location_backorders) ? $location_backorders : $backorders;
}, 10, 2);
}

if((get_option('lwp_display_options', ['enable_location_stock' => 'yes'])['enable_location_stock'] ?? 'yes') === 'yes'){
// Handle stock reduction when order is placed
add_action('woocommerce_reduce_order_stock', function($order) {
    $location_slug = get_current_store_location();
    $location_id = get_location_term_id($location_slug);
    
    if (!$location_id) {
        return;
    }
    
    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        $variation_id = $item->get_variation_id();
        $quantity = $item->get_quantity();
        
        $target_id = $variation_id ? $variation_id : $product_id;
        
        $current_stock = get_post_meta($target_id, '_location_stock_' . $location_id, true);
        
        if ($current_stock !== '') {
            $new_stock = max(0, (int)$current_stock - $quantity);
            update_post_meta($target_id, '_location_stock_' . $location_id, $new_stock);
        }
    }
});

// Handle stock restoration when order is canceled
add_action('woocommerce_restore_order_stock', function($order) {
    $location_slug = get_current_store_location();
    $location_id = get_location_term_id($location_slug);
    
    if (!$location_id) {
        return;
    }
    
    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        $variation_id = $item->get_variation_id();
        $quantity = $item->get_quantity();
        
        $target_id = $variation_id ? $variation_id : $product_id;
        
        $current_stock = get_post_meta($target_id, '_location_stock_' . $location_id, true);
        
        if ($current_stock !== '') {
            $new_stock = (int)$current_stock + $quantity;
            update_post_meta($target_id, '_location_stock_' . $location_id, $new_stock);
        }
    }
});
}

// Validate cart items against location stock
add_filter('woocommerce_add_to_cart_validation', function($passed, $product_id, $quantity, $variation_id = 0, $variations = array()) {
    $location_slug = get_current_store_location();
    $location_id = get_location_term_id($location_slug);
    
    if (!$location_id) {
        return $passed;
    }
    
    $target_id = $variation_id ? $variation_id : $product_id;
    $product = wc_get_product($target_id);
    
    // Get location specific stock
    $location_stock = get_post_meta($target_id, '_location_stock_' . $location_id, true);
    
    if ($location_stock === '') {
        return $passed; // Use default WooCommerce stock checking
    }
    
    // Get backorder setting
    $location_backorders = get_post_meta($target_id, '_location_backorders_' . $location_id, true);
    
    // Check if we have enough stock
    $qty_in_cart = 0;
    
    foreach (WC()->cart->get_cart() as $cart_item) {
        if (($variation_id && $variation_id == $cart_item['variation_id']) || 
            (!$variation_id && $product_id == $cart_item['product_id'])) {
            $qty_in_cart += $cart_item['quantity'];
        }
    }
    
    $total_required = $qty_in_cart + $quantity;
    
    // If backorders are not allowed and we don't have enough stock
    if ($location_backorders === 'no' && $location_stock < $total_required) {
        wc_add_notice(
            sprintf(
                esc_html('Sorry, "%s" has only %d left in stock at your selected location. Please adjust your quantity.', 'location-wise-product'), 
                $product->get_name(), 
                $location_stock
            ), 
            'error'
        );
        return false;
    }
    
    return $passed;
}, 10, 5);

if((get_option('lwp_display_options', ['enable_location_price' => 'yes'])['enable_location_price'] ?? 'yes') === 'yes'){
// Override the final price for simple products
add_filter('woocommerce_product_get_price', function($price, $product) {
    if ($product->is_type('variation')) {
        return $price; // Handle variations separately
    }
    
    $location_slug = get_current_store_location();
    $location_id = get_location_term_id($location_slug);
    
    if (!$location_id) {
        return $price;
    }
    
    // First check if there's a location-specific sale price
    $location_sale_price = get_post_meta($product->get_id(), '_location_sale_price_' . $location_id, true);
    
    // If there's a valid sale price and it's not empty, use it
    if (!empty($location_sale_price)) {
        return $location_sale_price;
    }
    
    // Otherwise, check for location-specific regular price
    $location_regular_price = get_post_meta($product->get_id(), '_location_regular_price_' . $location_id, true);
    
    // If there's a location-specific regular price, use it
    if (!empty($location_regular_price)) {
        return $location_regular_price;
    }
    
    // If no location-specific prices, return the original price
    return $price;
}, 10, 2);

// Override the final price for variation products
add_filter('woocommerce_product_variation_get_price', function($price, $variation) {
    $location_slug = get_current_store_location();
    $location_id = get_location_term_id($location_slug);
    
    if (!$location_id) {
        return $price;
    }
    
    // First check if there's a location-specific sale price
    $location_sale_price = get_post_meta($variation->get_id(), '_location_sale_price_' . $location_id, true);
    
    // If there's a valid sale price and it's not empty, use it
    if (!empty($location_sale_price)) {
        return $location_sale_price;
    }
    
    // Otherwise, check for location-specific regular price
    $location_regular_price = get_post_meta($variation->get_id(), '_location_regular_price_' . $location_id, true);
    
    // If there's a location-specific regular price, use it
    if (!empty($location_regular_price)) {
        return $location_regular_price;
    }
    
    // If no location-specific prices, return the original price
    return $price;
}, 10, 2);

// We also need to ensure variation price sync works correctly
add_filter('woocommerce_variation_prices', function($prices, $product, $for_display) {
    $location_slug = get_current_store_location();
    $location_id = get_location_term_id($location_slug);
    
    if (!$location_id) {
        return $prices;
    }
    
    if (!empty($prices['regular_price']) && !empty($prices['sale_price']) && !empty($prices['price'])) {
        $variation_ids = array_keys($prices['regular_price']);
        
        foreach ($variation_ids as $variation_id) {
            // Update regular price
            $location_regular_price = get_post_meta($variation_id, '_location_regular_price_' . $location_id, true);
            if (!empty($location_regular_price)) {
                $prices['regular_price'][$variation_id] = $location_regular_price;
            }
            
            // Update sale price
            $location_sale_price = get_post_meta($variation_id, '_location_sale_price_' . $location_id, true);
            if (!empty($location_sale_price)) {
                $prices['sale_price'][$variation_id] = $location_sale_price;
                // Also update the final price when sale price exists
                $prices['price'][$variation_id] = $location_sale_price;
            } elseif (!empty($location_regular_price)) {
                // If no sale price but has location regular price, update the final price
                $prices['price'][$variation_id] = $location_regular_price;
            }
        }
    }
    
    return $prices;
}, 10, 3);
}

// show prevent message for current location

// Add a more prominent notice on the single product page
add_action('woocommerce_single_product_summary', function() {
    global $product;
    
    if (!is_object($product)) {
        $product = wc_get_product(get_the_ID());
    }
    
    if (!$product) {
        return;
    }
    
    $location_slug = get_current_store_location();
    
    // If no location is selected or "all products" is selected, don't show the notice
    if (!$location_slug || $location_slug === 'all-products') {
        return;
    }
    
    // Check if the product belongs to the current location
    $terms = wp_get_object_terms($product->get_id(), 'store_location', ['fields' => 'slugs']);
    
    if (is_wp_error($terms) || !in_array($location_slug, $terms)) {
        // Product is not available in the current location - display a prominent notice
        echo '<div class="product-location-unavailable">';
        echo '<p class="unavailable-notice">' . esc_html_e('This product isn\'t available for your current location.', 'location-wise-product') . '</p>';
        echo '</div>';
    }
}, 5); // Priority 5 to show it near the top

// disable purchase

// Also prevent adding to cart through direct URLs or AJAX
// add_filter('woocommerce_add_to_cart_validation', function($valid, $product_id, $quantity) {
//     $location_slug = get_current_store_location();
    
//     // If no location is selected or "all products" is selected, keep default validation
//     if (!$location_slug || $location_slug === 'all-products') {
//         return $valid;
//     }
    
//     // Check if the product belongs to the current location
//     $terms = wp_get_object_terms($product_id, 'store_location', ['fields' => 'slugs']);
    
//     if (is_wp_error($terms) || !in_array($location_slug, $terms)) {
//         // Product is not available in the current location
//         wc_add_notice(__('This product isn\'t available for your current location and cannot be purchased.', 'location-wise-product'), 'error');
//         return false;
//     }
    
//     return $valid;
// }, 10, 3);

// Hide add to cart button on shop/archive pages for unavailable products
// add_filter('woocommerce_loop_add_to_cart_link', function($html, $product) {
//     $location_slug = get_current_store_location();
    
//     // If no location is selected or "all products" is selected, show normal button
//     if (!$location_slug || $location_slug === 'all-products') {
//         return $html;
//     }
    
//     // Check if the product belongs to the current location
//     $terms = wp_get_object_terms($product->get_id(), 'store_location', ['fields' => 'slugs']);
    
//     if (is_wp_error($terms) || !in_array($location_slug, $terms)) {
//         // Replace add to cart button with unavailable text
//         return '<span class="button unavailable-product">' . __('Unavailable at your location', 'location-wise-product') . '</span>';
//     }
    
//     return $html;
// }, 10, 2);

// if variation product & product is not available in current location hide add to cart button form.variations_form.cart { display: none; }
add_action( 'wp_footer', function() {
    if ( is_product() ) {
        global $product;

        if ( $product->is_type( 'variable' ) ) {
            $location_slug = get_current_store_location();

            // If no location is selected or "all products" is selected, show normal button
            if ( ! $location_slug || 'all-products' === $location_slug ) {
                return;
            }

            // Check if the product belongs to the current location
            $terms = wp_get_object_terms( $product->get_id(), 'store_location', array( 'fields' => 'slugs' ) );

            if ( is_wp_error( $terms ) || ! in_array( $location_slug, $terms, true ) ) {
                // Register a dummy stylesheet to attach inline styles
                wp_register_style('custom-woocommerce-style', false, array(), '1.0.0');
                wp_enqueue_style('custom-woocommerce-style');
                wp_add_inline_style('custom-woocommerce-style', '.variations_form.cart { display: none; }');
            }
        }else{
            $location_slug = get_current_store_location();

            // If no location is selected or "all products" is selected, show normal button
            if ( ! $location_slug || 'all-products' === $location_slug ) {
                return;
            }

            // Check if the product belongs to the current location
            $terms = wp_get_object_terms( $product->get_id(), 'store_location', array( 'fields' => 'slugs' ) );

            if ( is_wp_error( $terms ) || ! in_array( $location_slug, $terms, true ) ) {
                // Register a dummy stylesheet to attach inline styles
                wp_register_style('custom-woocommerce-style', false, array(), '1.0.0');
                wp_enqueue_style('custom-woocommerce-style');
                wp_add_inline_style('custom-woocommerce-style', 'form.cart { display: none; }');
            }
        }
    }
} );


// add stock & price details in product pages
$options = get_option('lwp_display_options', ['enable_location_by_user_role' => []]);
$selected_roles = isset($options['enable_location_by_user_role']) ? $options['enable_location_by_user_role'] : [];
$current_user = wp_get_current_user();
$user_roles = $current_user->roles;

// Check if the current user role has permission
if (array_intersect($user_roles, $selected_roles)) {
    if ((get_option('lwp_display_options', ['enable_location_information' => 'yes'])['enable_location_information'] ?? 'yes') === 'yes') {
        // Add location-specific stock and price display on product pages
        add_action('woocommerce_single_product_summary', 'display_location_specific_stock_info', 25);
        add_action('woocommerce_shop_loop_item_title', 'display_location_specific_stock_info_loop', 15);
    }
}
/**
 * Display location-specific stock and price information on single product pages
 */
function display_location_specific_stock_info() {
    global $product;
    
    // Get current location
    $location_slug = get_current_store_location();
    if (empty($location_slug) || $location_slug === 'all-products') {
        return; // No specific location selected
    }
    
    // Get location term
    $location = get_term_by('slug', $location_slug, 'store_location');
    if (!$location) {
        return;
    }
    
    $product_id = $product->get_id();
    $variation_id = 0;
    
    // If this is a variation, get its ID
    if ($product->is_type('variation')) {
        $variation_id = $product_id;
        $product_id = $product->get_parent_id();
    }
    
    $target_id = $variation_id ? $variation_id : $product_id;
    
    // Get location-specific stock
    $location_stock = get_post_meta($target_id, '_location_stock_' . $location->term_id, true);
    
    // Get location-specific prices
    $location_regular_price = get_post_meta($target_id, '_location_regular_price_' . $location->term_id, true);
    $location_sale_price = get_post_meta($target_id, '_location_sale_price_' . $location->term_id, true);
    
    // Get backorder setting
    $location_backorders = get_post_meta($target_id, '_location_backorders_' . $location->term_id, true);
    
    echo '<div class="location-specific-info">';
    echo '<h4>' . sprintf(esc_html('Information for %s location', 'location-wise-product'), esc_attr($location->name)) . '</h4>';
    
    // Display stock status
    if ($location_stock !== '') {
        echo '<p class="location-stock">';
        echo '<strong>' . esc_html_e('Stock:', 'location-wise-product') . '</strong> ';
        
        if ($location_stock > 0) {
            echo '<span class="in-stock">' . sprintf(esc_html('%d item in stock', '%d items in stock', $location_stock, 'location-wise-product'), esc_attr($location_stock)) . '</span>';
        } else {
            if ($location_backorders === 'no') {
                echo '<span class="out-of-stock">' . esc_html('Out of stock', 'location-wise-product') . '</span>';
            } else {
                echo '<span class="on-backorder">' . esc_html('Available on backorder', 'location-wise-product') . '</span>';
            }
        }
        echo '</p>';
    }
    
    // Display location-specific prices if they exist
    if (!empty($location_regular_price)) {
        echo '<p class="location-price">';
        echo '<strong>' . esc_html_e('Price at this location:', 'location-wise-product') . '</strong> ';
        
        if (!empty($location_sale_price)) {
            echo '<del>' . wp_kses_post(wc_price($location_regular_price)) . '</del> <ins>' . wp_kses_post(wc_price($location_sale_price)) . '</ins>';
        } else {
            echo wp_kses_post(wc_price($location_regular_price));
        }
        echo '</p>';
    }
    
    echo '</div>';
}

/**
 * Display simplified location-specific stock and price information on product loops (shop pages)
 */
function display_location_specific_stock_info_loop() {
    global $product;
    
    // Get current location
    $location_slug = get_current_store_location();
    if (empty($location_slug) || $location_slug === 'all-products') {
        return; // No specific location selected
    }
    
    // Get location term
    $location = get_term_by('slug', $location_slug, 'store_location');
    if (!$location) {
        return;
    }
    
    $product_id = $product->get_id();
    
    // Get location-specific stock
    $location_stock = get_post_meta($product_id, '_location_stock_' . $location->term_id, true);
    
    // Get backorder setting
    $location_backorders = get_post_meta($product_id, '_location_backorders_' . $location->term_id, true);
    
    echo '<div class="location-specific-info-loop">';
    
    // Display stock status in a simplified format for shop pages
    if ($location_stock !== '') {
        echo '<span class="location-stock-loop">';
        
        if ($location_stock > 0) {
            echo '<span class="in-stock">' . sprintf(esc_html('%d in stock', 'location-wise-product'), esc_attr($location_stock)) . '</span>';
        } else {
            if ($location_backorders === 'no') {
                echo '<span class="out-of-stock">' . esc_html('Out of stock', 'location-wise-product') . '</span>';
            } else {
                echo '<span class="on-backorder">' . esc_html('Backorder', 'location-wise-product') . '</span>';
            }
        }
        echo '</span>';
    }
    
    echo '</div>';
}
/**
 * Handle variable products - show location info for the selected variation
 */
add_action('woocommerce_available_variation', 'add_location_data_to_variations', 10, 3);
function add_location_data_to_variations($variation_data, $product, $variation) {
    // Get current location
    $location_slug = get_current_store_location();
    if (empty($location_slug) || $location_slug === 'all-products') {
        return $variation_data; // No specific location selected
    }
    
    // Get location term
    $location = get_term_by('slug', $location_slug, 'store_location');
    if (!$location) {
        return $variation_data;
    }
    
    $variation_id = $variation->get_id();
    
    // Get location-specific stock
    $location_stock = get_post_meta($variation_id, '_location_stock_' . $location->term_id, true);
    
    // Get location-specific prices
    $location_regular_price = get_post_meta($variation_id, '_location_regular_price_' . $location->term_id, true);
    $location_sale_price = get_post_meta($variation_id, '_location_sale_price_' . $location->term_id, true);
    
    // Get backorder setting
    $location_backorders = get_post_meta($variation_id, '_location_backorders_' . $location->term_id, true);
    
    // Add location data to variation data
    $variation_data['location_data'] = [
        'location_name' => $location->name,
        'location_stock' => $location_stock,
        'location_regular_price' => wc_price($location_regular_price),
        'location_sale_price' => wc_price($location_sale_price),
        'location_backorders' => $location_backorders
    ];
    
    return $variation_data;
}



if (array_intersect($user_roles, $selected_roles) && (get_option('lwp_display_options', ['enable_location_information' => 'yes'])['enable_location_information'] ?? 'yes') === 'yes') {

/**
 * Add stock status to product category/archive pages
 */
add_action('woocommerce_after_shop_loop_item', 'display_location_stock_status_in_loop', 9);

}
function display_location_stock_status_in_loop() {
    global $product;
    
    // Get current location
    $location_slug = get_current_store_location();
    if (empty($location_slug) || $location_slug === 'all-products') {
        return; // No specific location selected
    }
    
    // Get location term
    $location = get_term_by('slug', $location_slug, 'store_location');
    if (!$location) {
        return;
    }
    
    $product_id = $product->get_id();
    
    // Get location-specific stock and prices
    $location_stock = get_post_meta($product_id, '_location_stock_' . $location->term_id, true);
    $location_regular_price = get_post_meta($product_id, '_location_regular_price_' . $location->term_id, true);
    $location_sale_price = get_post_meta($product_id, '_location_sale_price_' . $location->term_id, true);
    $location_backorders = get_post_meta($product_id, '_location_backorders_' . $location->term_id, true);
    
    echo '<div class="location-loop-details">';
    
    // Display stock status badge
    if ($location_stock !== '') {
        echo '<div class="location-stock-badge">';
        
        if (intval($location_stock) > 0) {
            echo '<span class="stock-badge in-stock">' . esc_html_e('In Stock', 'location-wise-product') . '</span>';
        } else {
            if ($location_backorders === 'no') {
                echo '<span class="stock-badge out-of-stock">' . esc_html_e('Out of Stock', 'location-wise-product') . '</span>';
            } else {
                echo '<span class="stock-badge on-backorder">' . esc_html_e('Backorder', 'location-wise-product') . '</span>';
            }
        }
        
        echo '</div>';
    }
    
    // Display location-specific price if available
    if (!empty($location_regular_price)) {
        echo '<div class="location-price-loop">';
        echo '<small>' . sprintf(esc_html('%s price:', 'location-wise-product'), esc_attr($location->name)) . '</small> ';
        
        if (!empty($location_sale_price)) {
            echo '<del>' . wp_kses_post(wc_price($location_regular_price)) . '</del> <ins>' . wp_kses_post(wc_price($location_sale_price)) . '</ins>';
        } else {
            echo wp_kses_post(wc_price($location_regular_price));
        }
        
        echo '</div>';
    }
    
    echo '</div>';
}
// add product stock & price status in all product page admin

add_filter('manage_product_posts_columns', 'add_location_column_to_product_list', 20);
function add_location_column_to_product_list($columns) {
    $new_columns = array();
    
    // Insert columns before the Locations column
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        
        // Insert the Locations column after the Name column
        if ($key === 'name') {
            $new_columns['locations'] = __('Stock & Price', 'location-wise-product');
        }
    }
    
    return $new_columns;
}
add_action('manage_product_posts_custom_column', 'populate_locations_column_in_product_list', 10, 2);
function populate_locations_column_in_product_list($column, $post_id) {
    if ($column === 'locations') {
        $product = wc_get_product($post_id);
        if (!$product) {
            echo '—';
            return;
        }

        // Get all store locations
        $locations = get_terms(array(
            'taxonomy' => 'store_location',
            'hide_empty' => false,
        ));

        if (!is_wp_error($locations) && !empty($locations)) {
            $output = '';

            // Handle variable products
            if ($product->is_type('variable')) {
                $variation_ids = $product->get_children();
                foreach ($variation_ids as $variation_id) {
                    $variation = new WC_Product_Variation($variation_id);
                    $variation_title = $variation->get_attributes(); // Get variation attributes
                    $variation_name = implode(', ', $variation_title); // Format variation name

                    $output .= '<b>'. esc_html($variation_name) . '</b>'; // Display variation name

                    foreach ($locations as $location) {
                        $location_price = get_post_meta($variation_id, '_location_regular_price_' . $location->term_id, true);
                        $location_stock = get_post_meta($variation_id, '_location_stock_' . $location->term_id, true);
                        
                        // Build output for this location
                        if ($location_stock !== '') {
                            $output .= '<div>' . esc_html($location->name) . ': ';
                            $output .= ($location_stock > 0) ? 
                        '<mark class="instock">' . __('In stock', 'location-wise-product') . ' (' . $location_stock . ')</mark>' : 
                        '<mark class="outofstock">' . __('Out of stock', 'location-wise-product') . '</mark>';

                            if ($location_price) {
                                $output .= ' - ' . wc_price($location_price);
                            }
                            $output .= '</div>'; // New line for each location
                        }
                    }
                }
            } else {
                // For simple products
                foreach ($locations as $location) {
                    $location_price = get_post_meta($product->get_id(), '_location_regular_price_' . $location->term_id, true);
                    $location_stock = get_post_meta($product->get_id(), '_location_stock_' . $location->term_id, true);

                    if ($location_stock !== '') {
                        $output .= '<div>' . esc_html($location->name) . ': ';
                        $output .= ($location_stock > 0) ? 
                        '<mark class="instock">' . __('In stock', 'location-wise-product') . ' (' . $location_stock . ')</mark>' : 
                        '<mark class="outofstock">' . __('Out of stock', 'location-wise-product') . '</mark>';

                        if ($location_price) {
                            $output .= ' - ' . wc_price($location_price);
                        }
                        $output .= '</div>';
                    }
                }
            }

            echo wp_kses_post($output) ?: '<span class="na">—</span>';
        }
    }
}

// hide stock & price column
add_filter('manage_edit-product_columns', 'remove_default_product_columns', 20);
function remove_default_product_columns($columns) {
    // Unset the default stock and price columns
    unset($columns['is_in_stock']);
    unset($columns['price']);
    return $columns;
}