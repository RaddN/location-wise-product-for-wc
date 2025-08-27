<?php

/**
 * Product display functionality for location-wise products
 *
 * @package Location_Wise_Products
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}


/**
 * WooCommerce Product Priority Display by Store Location
 * Add this code to your theme's functions.php file or create a custom plugin
 */
/**
 * WooCommerce Product Priority Display by Store Location
 * Add this code to your theme's functions.php file or create a custom plugin
 */

$display_option = get_option('mulopimfwc_display_options',[]);

if ($display_option === 'location_first') {
    // Hook to modify the main query
    add_action('pre_get_posts', 'mulopimfwc_prioritize_products_with_store_location');
    add_filter('posts_orderby', 'mulopimfwc_add_store_location_orderby', 10, 2);
}

function mulopimfwc_prioritize_products_with_store_location($query)
{
    // Only apply to main query on shop/product archive pages
    if (!is_admin() && $query->is_main_query() && (is_shop() || is_product_category() || is_product_tag())) {
        // Set a flag to indicate we want custom ordering
        $query->set('store_location_custom_order', 'with_location_first');
    }
}

// Add custom orderby for store location priority
function mulopimfwc_add_store_location_orderby($orderby, $query)
{
    global $wpdb;

    // Check if this query should use custom ordering
    $custom_order = $query->get('store_location_custom_order');

    if (!is_admin() && $query->is_main_query() && !empty($custom_order) && (is_shop() || is_product_category() || is_product_tag())) {

        // Ensure we have a valid orderby string
        if (empty($orderby)) {
            $orderby = "{$wpdb->posts}.post_date DESC";
        }

        $orderby_custom = "
            CASE 
                WHEN EXISTS (
                    SELECT 1 
                    FROM {$wpdb->term_relationships} tr 
                    INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id 
                    WHERE tr.object_id = {$wpdb->posts}.ID 
                    AND tt.taxonomy = 'mulopimfwc_store_location'
                ) THEN 1 
                ELSE 0 
            END DESC, 
            {$orderby}
        ";
        return $orderby_custom;
    }

    return $orderby;
}




/**
 * WooCommerce Out of Stock Product Display Handler
 * Handles different display options for out-of-stock products
 */

// Get the saved option value
function mulopimfwc_get_out_of_stock_display_option()
{
    $options = get_option('mulopimfwc_display_options', array());
    return isset($options['show_out_of_stock_products']) ? $options['show_out_of_stock_products'] : 'none';
}

// Option 1: Hide out-of-stock products completely
function mulopimfwc_hide_out_of_stock_products($query)
{
    if (!is_admin() && $query->is_main_query() && (is_shop() || is_product_category() || is_product_tag())) {
        $display_option = mulopimfwc_get_out_of_stock_display_option();

        if ($display_option === 'hide') {
            // Get current meta query
            $meta_query = $query->get('meta_query');
            if (!is_array($meta_query)) {
                $meta_query = array();
            }

            // Add meta query to hide out of stock products
            $meta_query[] = array(
                'key' => '_stock_status',
                'value' => 'outofstock',
                'compare' => '!='
            );

            $query->set('meta_query', $meta_query);
        }
    }
}
add_action('pre_get_posts', 'mulopimfwc_hide_out_of_stock_products');



// Option 2: Show with "Out of Stock" Badge
function mulopimfwc_add_out_of_stock_badge()
{
    $display_option = mulopimfwc_get_out_of_stock_display_option();

    if ($display_option === 'show_with_badge') {
        add_action('woocommerce_before_shop_loop_item_title', 'mulopimfwc_display_out_of_stock_badge', 10);
        add_action('woocommerce_before_single_product_summary', 'mulopimfwc_display_out_of_stock_badge', 25);
    }
}
add_action('init', 'mulopimfwc_add_out_of_stock_badge');

function mulopimfwc_display_out_of_stock_badge()
{
    global $product;

    if (!$product->is_in_stock()) {
        echo '<span class="badge-text ast-shop-product-out-of-stock">Out of Stock</span>';
    }
}


// Option 3: Show grayed out (when enabled)
function mulopimfwc_add_out_of_stock_grayed_out_styles() {
    $display_option = mulopimfwc_get_out_of_stock_display_option();
    $grayed_out_style = ".product.out-of-stock {
        opacity: 0.5;
        position: relative;
    }
    
    .product.out-of-stock::after {
        content: 'Out of Stock';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(0, 0, 0, 0.8);
        color: white;
        padding: 5px 10px;
        border-radius: 3px;
        font-size: 12px;
        z-index: 10;
        pointer-events: none;
    }
    
    .product.out-of-stock .add_to_cart_button,
    .product.out-of-stock .product_type_simple {
        display: none !important;
    }";
    
    if ($display_option === 'show_grayed_out') {
        wp_add_inline_style('mulopimfwc_style', $grayed_out_style);
        add_filter('post_class', 'mulopimfwc_add_out_of_stock_class');
    }
}
add_action('init', 'mulopimfwc_add_out_of_stock_grayed_out_styles');

function mulopimfwc_add_out_of_stock_class($classes) {
    global $product;
    
    if (is_a($product, 'WC_Product') && !$product->is_in_stock()) {
        $classes[] = 'out-of-stock';
    }
    
    return $classes;
}

// CSS for the out-of-stock badge
function mulopimfwc_out_of_stock_badge_css() {
    $display_option = mulopimfwc_get_out_of_stock_display_option();
    
    if ($display_option === 'show_with_badge') {
        wp_add_inline_style('woocommerce-general', '
            .out-of-stock-badge {
                position: absolute;
                top: 10px;
                right: 10px;
                z-index: 10;
                background: #e74c3c;
                color: white;
                padding: 5px 8px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: bold;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .woocommerce ul.products li.product {
                position: relative;
            }
            
            .out-of-stock-badge .badge-text {
                display: block;
            }
            
            @media (max-width: 768px) {
                .out-of-stock-badge {
                    top: 5px;
                    right: 5px;
                    padding: 3px 6px;
                    font-size: 10px;
                }
            }
        ');
    }
}
add_action('wp_enqueue_scripts', 'mulopimfwc_out_of_stock_badge_css');
