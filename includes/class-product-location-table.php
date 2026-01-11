<?php

/**
 * Product Location Table Class
 *
 * @package Location_Wise_Products
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Product Location Table Class
 * Extends the WP_List_Table class to create a custom table for showing products with location data
 */
class mulopimfwc_Product_Location_Table extends WP_List_Table
{
    /**
     * Flag to track if we're ordering by location
     */
    private $ordering_by_location = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct([
            'singular' => 'product',
            'plural'   => 'products',
            'ajax'     => false,
        ]);
    }

    /**
     * Get table columns
     *
     * @return array
     */
    public function get_columns()
    {
        return [
            'cb'            => '<input type="checkbox" />',
            'image'         => __('Image', 'multi-location-product-and-inventory-management'),
            'title'         => __('Product', 'multi-location-product-and-inventory-management'),
            'stock'         => __('Stock by Location', 'multi-location-product-and-inventory-management'),
            'price'         => __('Price by Location', 'multi-location-product-and-inventory-management'),
            'purchase_price' => __('Purchase Info', 'multi-location-product-and-inventory-management'),
            'gross_profit'   => __('Gross Profit', 'multi-location-product-and-inventory-management'),
            'actions'       => __('Actions', 'multi-location-product-and-inventory-management'),
        ];
    }

    /**
     * Default column rendering
     *
     * @param array $item Item data
     * @param string $column_name Column name
     * @return string
     */
    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'title':
                return $item['title'];
            case 'stock':
                return $this->get_location_stock_display($item);
            case 'price':
                return $this->get_location_price_display($item);
            case 'purchase_price':
                return $this->get_purchase_price_display($item);
            case 'gross_profit':
                return $this->get_gross_profit_display($item);
            case 'actions':
                return $this->get_actions_display($item);
            default:
                return '';
        }
    }

    /**
     * Checkbox column
     *
     * @param array $item Item data
     * @return string
     */
    public function column_cb($item)
    {
        return sprintf('<input type="checkbox" name="product[]" value="%s" />', $item['id']);
    }

    /**
     * Image column
     *
     * @param array $item Item data
     * @return string
     */
    public function column_image($item)
    {
        return $item['image'];
    }

    /**
     * Title column with action links
     *
     * @param array $item Item data
     * @return string
     */
    public function column_title($item)
    {
        $edit_link = get_edit_post_link($item['id']);
        $view_link = get_permalink($item['id']);
        $trash_link = get_delete_post_link($item['id'], '', true);
        $duplicate_link = wp_nonce_url(
            admin_url('edit.php?post_type=product&action=duplicate_product&post=' . $item['id']),
            'woocommerce-duplicate-product_' . $item['id']
        );

        // Prepare data for quick edit (Manage Stock) popup
        $nonce = wp_create_nonce('location_product_action_nonce');
        $locations = $item['location_terms'];
        $product_location_slugs = wp_list_pluck($locations, 'slug');

        global $mulopimfwc_locations;
        $all_locations_data = [];
        if (!is_wp_error($mulopimfwc_locations) && !empty($mulopimfwc_locations)) {
            foreach ($mulopimfwc_locations as $location) {
                $all_locations_data[] = [
                    'id' => $location->term_id,
                    'name' => $location->name,
                    'parent' => $location->parent,
                    'selected' => in_array(rawurldecode($location->slug), $product_location_slugs),
                ];
            }
        }

        $quick_edit_data = isset($item['quick_edit_data']) ? $item['quick_edit_data'] : null;

        $title = '<strong><a target="_blank" rel="noopener noreferrer" href="' . esc_url($edit_link) . '">' . esc_html($item['title']) . '</a></strong>';
        $title .= '<div class="row-actions">';
        $title .= '<span class="edit"><a target="_blank" rel="noopener noreferrer" href="' . esc_url($edit_link) . '">' . __('Edit', 'multi-location-product-and-inventory-management') . '</a> | </span>';
        $title .= '<span class="view"><a target="_blank" rel="noopener noreferrer" href="' . esc_url($view_link) . '">' . __('View', 'multi-location-product-and-inventory-management') . '</a> | </span>';

        $manage_attrs = 'class="row-quick-edit manage-product-location" data-product-id="' . esc_attr($item['id']) . '" data-product-type="' . esc_attr($item['type']) . '" data-nonce="' . esc_attr($nonce) . '"';
        if ($quick_edit_data) {
            $manage_attrs .= ' data-product-data="' . esc_attr(wp_json_encode($quick_edit_data)) . '"';
        }
        if (!empty($all_locations_data)) {
            $manage_attrs .= ' data-locations="' . esc_attr(wp_json_encode($all_locations_data)) . '"';
        }

        $title .= '<span class="quick-edit"><a href="#" ' . $manage_attrs . '>' . __('Quick Edit', 'multi-location-product-and-inventory-management') . '</a> | </span>';
        $title .= '<span class="trash"><a href="' . esc_url($trash_link) . '">' . __('Trash', 'multi-location-product-and-inventory-management') . '</a> | </span>';
        $title .= '<span class="duplicate"><a href="' . esc_url($duplicate_link) . '">' . __('Duplicate', 'multi-location-product-and-inventory-management') . '</a></span>';
        $title .= '</div>';
        return $title;
    }

    /**
     * Get stock display for each location
     *
     * @param array $item Item data
     * @return string
     */
    private function get_location_stock_display($item)
    {
        // Show -- for affiliate, external, and grouped products (they don't have stock management)
        if ($item['type'] === 'affiliate' || $item['type'] === 'external' || $item['type'] === 'grouped') {
            return '<span style="color: #9ca3af;">--</span>';
        }

        $output = '<div class="location-stock-container">';
        if ($item['type'] === 'variable' && !empty($item['variations'])) {
            $variation_index = 0;
            foreach ($item['variations'] as $variation) {
                $variation_title = implode(', ', array_map(function ($key, $value) {
                    return ucfirst(str_replace('attribute_pa_', '', $key)) . ': ' . $value;
                }, array_keys($variation['attributes']), $variation['attributes']));
                $is_first = $variation_index === 0;
                $accordion_id = 'variation-stock-' . $item['id'] . '-' . $variation_index;
                $output .= '<div class="variation-stock-item accordion-item' . ($is_first ? ' accordion-expanded' : '') . '">';
                $output .= '<div class="accordion-header" data-accordion-target="' . esc_attr($accordion_id) . '">';
                $output .= '<strong>' . esc_html($variation_title) . '</strong>';
                $output .= '<span class="accordion-icon">' . ($is_first ? '−' : '+') . '</span>';
                $output .= '</div>';
                $output .= '<div class="accordion-content' . ($is_first ? ' accordion-open' : '') . '" id="' . esc_attr($accordion_id) . '">';
                
                // Get variation product to check stock management setting
                $variation_product = wc_get_product($variation['id']);
                $manage_stock = $variation_product ? $variation_product->get_manage_stock() : false;
                
                $output .= '<div class="location-stock-item">';
                $output .= '<span class="location-name">' . __('Default', 'multi-location-product-and-inventory-management') . ':</span> ';
                if ($manage_stock) {
                    // Stock management is enabled - use stock quantity
                    $default_stock = get_post_meta($variation['id'], '_stock', true);
                    $output .= '<span class="stock-value">' . ($default_stock ? __('In stock', 'multi-location-product-and-inventory-management') . ' (' . esc_html($default_stock) . ')' : __('Out of stock', 'multi-location-product-and-inventory-management')) . '</span>';
                } else {
                    // Stock management is disabled - check stock status
                    $stock_status = get_post_meta($variation['id'], '_stock_status', true);
                    $stock_status_text = ($stock_status === 'instock') ? __('In stock', 'multi-location-product-and-inventory-management') : __('Out of stock', 'multi-location-product-and-inventory-management');
                    $output .= '<span class="stock-value">' . esc_html($stock_status_text) . '</span>';
                }
                $output .= '</div>';
                
                if (!empty($item['location_terms'])) {
                    foreach ($item['location_terms'] as $location) {
                        $location_stock = get_post_meta($variation['id'], '_location_stock_' . $location->term_id, true);
                        $output .= '<div class="location-stock-item">';
                        $output .= '<span class="location-name">' . esc_html($location->name) . ':</span> ';
                        
                        // For location stock, check if stock is set (empty string means not set)
                        if ($location_stock !== '') {
                            // Location stock is set - use it
                            $output .= '<span class="stock-value">' . ($location_stock > 0 ? __('In stock', 'multi-location-product-and-inventory-management') . ' (' . esc_html($location_stock) . ')' : __('Out of stock', 'multi-location-product-and-inventory-management')) . '</span>';
                        } else {
                            // Location stock not set - check default stock status
                            if ($manage_stock) {
                                // Use default stock quantity
                                $default_stock = get_post_meta($variation['id'], '_stock', true);
                                $output .= '<span class="stock-value">' . ($default_stock ? __('In stock', 'multi-location-product-and-inventory-management') . ' (' . esc_html($default_stock) . ')' : __('Out of stock', 'multi-location-product-and-inventory-management')) . '</span>';
                            } else {
                                // Use default stock status
                                $stock_status = get_post_meta($variation['id'], '_stock_status', true);
                                $stock_status_text = ($stock_status === 'instock') ? __('In stock', 'multi-location-product-and-inventory-management') : __('Out of stock', 'multi-location-product-and-inventory-management');
                                $output .= '<span class="stock-value">' . esc_html($stock_status_text) . '</span>';
                            }
                        }
                        $output .= '</div>';
                    }
                }
                $output .= '</div>';
                $output .= '</div>';
                $variation_index++;
            }
        } else {
            // Get product to check stock management setting
            $product = wc_get_product($item['id']);
            $manage_stock = $product ? $product->get_manage_stock() : false;
            
            if ($manage_stock) {
                // Stock management is enabled - use stock quantity
                $default_stock = get_post_meta($item['id'], "_stock", true);
                $output .= '<div class="location-stock-item">';
                $output .= '<span class="location-name">' . __('Default', 'multi-location-product-and-inventory-management') . ':</span> ';
                $output .= '<span class="stock-value">' . ($default_stock ? __('In stock', 'multi-location-product-and-inventory-management') . ' (' . esc_html($default_stock) . ')' : __('Out of stock', 'multi-location-product-and-inventory-management')) . '</span>';
                $output .= '</div>';
            } else {
                // Stock management is disabled - check stock status
                $stock_status = get_post_meta($item['id'], "_stock_status", true);
                $stock_status_text = ($stock_status === 'instock') ? __('In stock', 'multi-location-product-and-inventory-management') : __('Out of stock', 'multi-location-product-and-inventory-management');
                $output .= '<div class="location-stock-item">';
                $output .= '<span class="location-name">' . __('Default', 'multi-location-product-and-inventory-management') . ':</span> ';
                $output .= '<span class="stock-value">' . esc_html($stock_status_text) . '</span>';
                $output .= '</div>';
            }
            if (!empty($item['location_terms'])) {
                foreach ($item['location_terms'] as $location) {
                    $location_stock = get_post_meta($item['id'], '_location_stock_' . $location->term_id, true);
                    $output .= '<div class="location-stock-item">';
                    $output .= '<span class="location-name">' . esc_html($location->name) . ':</span> ';
                    
                    // For location stock, check if stock is set (empty string means not set)
                    if ($location_stock !== '') {
                        // Location stock is set - use it
                        $output .= '<span class="stock-value">' . ($location_stock > 0 ? __('In stock', 'multi-location-product-and-inventory-management') . ' (' . esc_html($location_stock) . ')' : __('Out of stock', 'multi-location-product-and-inventory-management')) . '</span>';
                    } else {
                        // Location stock not set - check default stock status
                        if ($manage_stock) {
                            // Use default stock quantity
                            $default_stock = get_post_meta($item['id'], '_stock', true);
                            $output .= '<span class="stock-value">' . ($default_stock ? __('In stock', 'multi-location-product-and-inventory-management') . ' (' . esc_html($default_stock) . ')' : __('Out of stock', 'multi-location-product-and-inventory-management')) . '</span>';
                        } else {
                            // Use default stock status
                            $stock_status = get_post_meta($item['id'], '_stock_status', true);
                            $stock_status_text = ($stock_status === 'instock') ? __('In stock', 'multi-location-product-and-inventory-management') : __('Out of stock', 'multi-location-product-and-inventory-management');
                            $output .= '<span class="stock-value">' . esc_html($stock_status_text) . '</span>';
                        }
                    }
                    $output .= '</div>';
                }
            }
        }
        $output .= '</div>';
        return $output;
    }

    /**
     * Get price display for each location
     *
     * @param array $item Item data
     * @return string
     */
    private function get_location_price_display($item)
    {
        // Show -- for grouped products
        if ($item['type'] === 'grouped') {
            return '<span style="color: #9ca3af;">--</span>';
        }

        $output = '<div class="location-price-container">';
        if ($item['type'] === 'variable' && !empty($item['variations'])) {
            $variation_index = 0;
            foreach ($item['variations'] as $variation) {
                $variation_title = implode(', ', array_map(function ($key, $value) {
                    return ucfirst(str_replace('attribute_pa_', '', $key)) . ': ' . $value;
                }, array_keys($variation['attributes']), $variation['attributes']));
                $is_first = $variation_index === 0;
                $accordion_id = 'variation-price-' . $item['id'] . '-' . $variation_index;
                $output .= '<div class="variation-price-item accordion-item' . ($is_first ? ' accordion-expanded' : '') . '">';
                $output .= '<div class="accordion-header" data-accordion-target="' . esc_attr($accordion_id) . '">';
                $output .= '<strong>' . esc_html($variation_title) . '</strong>';
                $output .= '<span class="accordion-icon">' . ($is_first ? '−' : '+') . '</span>';
                $output .= '</div>';
                $output .= '<div class="accordion-content' . ($is_first ? ' accordion-open' : '') . '" id="' . esc_attr($accordion_id) . '">';
                $output .= '<div class="location-price-item">';
                $output .= '<span class="location-name">' . __('Default', 'multi-location-product-and-inventory-management') . ':</span> ';
                $output .= '<span class="price-value">' . wc_price($variation['price']) . '</span>';
                $output .= '</div>';
                if (!empty($item['location_terms'])) {
                    foreach ($item['location_terms'] as $location) {
                        $price = get_post_meta($variation['id'], '_location_sale_price_' . $location->term_id, true);
                        $output .= '<div class="location-price-item">';
                        $output .= '<span class="location-name">' . esc_html($location->name) . ':</span> ';
                        $output .= '<span class="price-value">' . (!empty($price) ? wc_price($price) : wc_price($variation['price'])) . '</span>';
                        $output .= '</div>';
                    }
                }
                $output .= '</div>';
                $output .= '</div>';
                $variation_index++;
            }
        } else {
            $default_price = get_post_meta($item['id'], "_price", true);
            $output .= '<div class="location-price-item">';
            $output .= '<span class="location-name">' . __('Default', 'multi-location-product-and-inventory-management') . ':</span> ';
            $output .= '<span class="price-value">' . wc_price($default_price) . '</span>';
            $output .= '</div>';
            if (!empty($item['location_terms'])) {
                foreach ($item['location_terms'] as $location) {
                    $price = get_post_meta($item['id'], '_location_sale_price_' . $location->term_id, true);
                    $output .= '<div class="location-price-item">';
                    $output .= '<span class="location-name">' . esc_html($location->name) . ':</span> ';
                    $output .= '<span class="price-value">' . (!empty($price) ? wc_price($price) : wc_price($default_price)) . '</span>';
                    $output .= '</div>';
                }
            }
        }
        $output .= '</div>';
        return $output;
    }

    /**
     * Get purchase price display
     *
     * @param array $item Item data
     * @return string
     */
    private function get_purchase_price_display($item)
    {
        // Show -- for grouped products
        if ($item['type'] === 'grouped') {
            return '<span style="color: #9ca3af;">--</span>';
        }

        $output = '<div class="purchase-price-container">';

        if ($item['type'] === 'variable' && !empty($item['variations'])) {
            foreach ($item['variations'] as $variation) {
                $variation_title = implode(', ', array_map(function ($key, $value) {
                    return ucfirst(str_replace('attribute_pa_', '', $key)) . ': ' . $value;
                }, array_keys($variation['attributes']), $variation['attributes']));

                $purchase_price = get_post_meta($variation['id'], '_purchase_price', true);

                $output .= '<div class="variation-purchase-price-item">';
                $output .= '<strong>' . esc_html($variation_title) . '</strong>';
                $output .= '<div class="purchase-price-item">';
                $output .= '<span class="purchase-price-value">' . (!empty($purchase_price) ? wc_price($purchase_price) : __('Not set', 'multi-location-product-and-inventory-management')) . '</span>';
                $output .= '</div>';
                $output .= '</div>';
            }
        } else {
            $purchase_price = get_post_meta($item['id'], '_purchase_price', true);
            $purchase_quantity = get_post_meta($item['id'], '_purchase_quantity', true);
            $output .= '<div class="purchase-price-item">';
            $output .= '<span class="purchase-price-value"> Price: ' . (!empty($purchase_price) ? wc_price($purchase_price) : __('Not set', 'multi-location-product-and-inventory-management')) . '</span>';
            $output .= '</div>';
            $output .= '<div class="purchase-price-item">';
            $output .= '<span class="purchase-price-value"> Quantity: ' . (!empty($purchase_quantity) ? $purchase_quantity : __('Not set', 'multi-location-product-and-inventory-management')) . '</span>';
            $output .= '</div>';
        }

        $output .= '</div>';
        return $output;
    }

    /**
     * Get gross profit display
     *
     * @param array $item Item data
     * @return string
     */
    private function get_gross_profit_display($item)
    {
        // Show -- for grouped products
        if ($item['type'] === 'grouped') {
            return '<span style="color: #9ca3af;">--</span>';
        }

        $output = '<div class="gross-profit-container '.esc_attr(mulopimfwc_get_pro_class()).'">';

        if ($item['type'] === 'variable' && !empty($item['variations'])) {
            $variation_index = 0;
            foreach ($item['variations'] as $variation) {
                $variation_title = implode(', ', array_map(function ($key, $value) {
                    return ucfirst(str_replace('attribute_pa_', '', $key)) . ': ' . $value;
                }, array_keys($variation['attributes']), $variation['attributes']));

                $purchase_price = get_post_meta($variation['id'], '_purchase_price', true);
                $default_price = $variation['price'];

                $is_first = $variation_index === 0;
                $accordion_id = 'variation-profit-' . $item['id'] . '-' . $variation_index;
                $output .= '<div class="variation-gross-profit-item accordion-item' . ($is_first ? ' accordion-expanded' : '') . '">';
                $output .= '<div class="accordion-header" data-accordion-target="' . esc_attr($accordion_id) . '">';
                $output .= '<strong>' . esc_html($variation_title) . '</strong>';
                $output .= '<span class="accordion-icon">' . ($is_first ? '−' : '+') . '</span>';
                $output .= '</div>';
                $output .= '<div class="accordion-content' . ($is_first ? ' accordion-open' : '') . '" id="' . esc_attr($accordion_id) . '">';

                // Default gross profit
                $output .= '<div class="location-gross-profit-item">';
                $output .= '<span class="location-name">' . __('Default', 'multi-location-product-and-inventory-management') . ':</span> ';
                $output .= $this->calculate_profit_display($default_price, $purchase_price);
                $output .= '</div>';

                // Location-specific gross profit
                if (!empty($item['location_terms'])) {
                    foreach ($item['location_terms'] as $location) {
                        $location_price = get_post_meta($variation['id'], '_location_sale_price_' . $location->term_id, true);
                        $price_to_use = !empty($location_price) ? $location_price : $default_price;

                        $output .= '<div class="location-gross-profit-item">';
                        $output .= '<span class="location-name">' . esc_html($location->name) . ':</span> ';
                        $output .= $this->calculate_profit_display($price_to_use, $purchase_price);
                        $output .= '</div>';
                    }
                }

                $output .= '</div>';
                $output .= '</div>';
                $variation_index++;
            }
        } else {
            $purchase_price = get_post_meta($item['id'], '_purchase_price', true);
            $default_price = get_post_meta($item['id'], "_price", true);

            // Default gross profit
            $output .= '<div class="location-gross-profit-item">';
            $output .= '<span class="location-name">' . __('Default', 'multi-location-product-and-inventory-management') . ':</span> ';
            $output .= $this->calculate_profit_display($default_price, $purchase_price);
            $output .= '</div>';

            // Location-specific gross profit
            if (!empty($item['location_terms'])) {
                foreach ($item['location_terms'] as $location) {
                    $location_price = get_post_meta($item['id'], '_location_sale_price_' . $location->term_id, true);
                    $price_to_use = !empty($location_price) ? $location_price : $default_price;

                    $output .= '<div class="location-gross-profit-item">';
                    $output .= '<span class="location-name">' . esc_html($location->name) . ':</span> ';
                    $output .= $this->calculate_profit_display($price_to_use, $purchase_price);
                    $output .= '</div>';
                }
            }
        }

        $output .= '</div>';
        return $output;
    }

    /**
     * Calculate and format profit display
     *
     * @param float $sale_price Sale price
     * @param float $purchase_price Purchase price
     * @return string Formatted profit display
     */
    private function calculate_profit_display($sale_price, $purchase_price)
    {
        if (!empty($purchase_price) && is_numeric($purchase_price) && $purchase_price > 0 && !empty($sale_price) && is_numeric($sale_price)) {
            $profit = $sale_price - $purchase_price;
            $gross_profit = wc_price($profit);

            // Calculate profit percentage
            $percentage = ($profit / $purchase_price) * 100;
            $gross_profit_percentage = round($percentage, 2) . '%';

            // Determine color based on profit
            $profit_class = $profit > 0 ? 'positive-profit' : ($profit < 0 ? 'negative-profit' : 'zero-profit');

            return '<span class="gross-profit-value ' . $profit_class . '">' .
                $gross_profit . ' <span class="profit-percentage">(' . $gross_profit_percentage . ')</span></span>';
        }

        return '<span class="gross-profit-value no-data">' . __('N/A', 'multi-location-product-and-inventory-management') . '</span>';
    }

    /**
     * Get locations display
     *
     * @param array $item Item data
     * @return string
     */
    private function get_locations_display($item)
    {
        $locations = $item['location_terms'];
        if (empty($locations)) {
            return '<span class="no-locations">' . __('N/A', 'multi-location-product-and-inventory-management') . '</span>';
        }
        $output = '<div class="product-locations">';
        foreach ($locations as $location) {
            $output .= '<span class="location-tag">' . esc_html($location->name) . '</span>';
        }
        $output .= '</div>';
        return $output;
    }

    /**
     * Get actions display
     *
     * @param array $item Item data
     * @return string
     */
    private function get_actions_display($item)
    {
        // Create nonce for action buttons
        $nonce = wp_create_nonce('location_product_action_nonce');

        $locations = $item['location_terms'];
        $product_location_slugs = wp_list_pluck($locations, 'slug');

        global $mulopimfwc_locations;
        $all_locations_data = [];
        if (!is_wp_error($mulopimfwc_locations) && !empty($mulopimfwc_locations)) {
            foreach ($mulopimfwc_locations as $location) {
                $all_locations_data[] = [
                    'id' => $location->term_id,
                    'name' => $location->name,
                    'parent' => $location->parent,
                    'selected' => in_array(rawurldecode($location->slug), $product_location_slugs),
                ];
            }
        }

        if (empty($locations)) {
            $quick_edit_data = isset($item['quick_edit_data']) ? $item['quick_edit_data'] : null;
            $button = '<a href="#" class="button button-small add-location" data-product-id="' . esc_attr($item['id']) . '" data-product-type="' . esc_attr($item['type']) . '" data-nonce="' . esc_attr($nonce) . '"';
            if ($quick_edit_data) {
                $button .= ' data-product-data="' . esc_attr(wp_json_encode($quick_edit_data)) . '"';
            }
            if (!empty($all_locations_data)) {
                $button .= ' data-locations="' . esc_attr(wp_json_encode($all_locations_data)) . '"';
            }
            $button .= '>' . __('Add to Location', 'multi-location-product-and-inventory-management') . '</a>';
            return $button;
        }
        $output = '<div class="location-actions">';
        foreach ($locations as $location) {
            $is_active = !get_post_meta($item['id'], '_location_disabled_' . $location->term_id, true);
            $action_class = $is_active ? 'activate-location' : 'deactivate-location';
            $action_text = $is_active ? __('Activated', 'multi-location-product-and-inventory-management') : __('Deactivated', 'multi-location-product-and-inventory-management');
            $button_class = $is_active ? 'button-primary' : 'button-secondary';

            $output .= '<div class="location-action-item">';
            $output .= '<span class="location-name">' . esc_html($location->name) . ':</span> ';
            $output .= '<a href="#" class="button button-small ' . esc_attr($button_class) . ' ' . esc_attr($action_class) . '" ' .
                'data-product-id="' . esc_attr($item['id']) . '" ' .
                'data-location-id="' . esc_attr($location->term_id) . '" ' .
                'data-action="' . ($is_active ? 'deactivate' : 'activate') . '" ' .
                'data-nonce="' . esc_attr($nonce) . '">' .
                esc_html($action_text) . '</a>';
            $output .= '</div>';
        }
        // Prepare location data for Edit Location popup
        // Store complete product data in data attribute for instant popup
        $quick_edit_data = isset($item['quick_edit_data']) ? $item['quick_edit_data'] : null;
        
        // Single button for both edit location and quick edit
        if ($quick_edit_data) {
            $output .= '<a href="#" class="button button-small manage-product-location" style="margin-top: 5px; display: block;" data-product-id="' . esc_attr($item['id']) . '" data-product-type="' . esc_attr($item['type']) . '" data-product-data="' . esc_attr(wp_json_encode($quick_edit_data)) . '" data-locations="' . esc_attr(wp_json_encode($all_locations_data)) . '" data-nonce="' . esc_attr($nonce) . '">' . __('Manage Stock', 'multi-location-product-and-inventory-management') . '</a>';
        } else {
            $output .= '<a href="#" class="button button-small manage-product-location" style="margin-top: 5px; display: block;" data-product-id="' . esc_attr($item['id']) . '" data-product-type="' . esc_attr($item['type']) . '" data-locations="' . esc_attr(wp_json_encode($all_locations_data)) . '" data-nonce="' . esc_attr($nonce) . '">' . __('Manage Stock', 'multi-location-product-and-inventory-management') . '</a>';
        }
        $output .= '</div>';
        return $output;
    }

    /**
     * Prepare table items
     */
    public function prepare_items()
    {
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        $per_page = 20;
        $current_page = $this->get_pagenum();

        $args = [
            'post_type' => 'product',
            'posts_per_page' => $per_page,
            'paged' => $current_page,
            'post_status' => 'publish',
        ];

        // Add search if set
        if (isset($_REQUEST['s']) && !empty($_REQUEST['s'])) {
            $args['s'] = sanitize_text_field(wp_unslash($_REQUEST['s']));
        }

        // Add location filter if set - verify nonce first if filter action is being submitted
        if (isset($_REQUEST['filter_action']) && $_REQUEST['filter_action'] == __('Filter', 'multi-location-product-and-inventory-management')) {
            // Verify the nonce
            if (
                isset($_REQUEST['_wpnonce']) &&
                wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])), 'bulk-' . $this->_args['plural'])
            ) {
                // Process filter
                if (isset($_REQUEST['filter-by-location']) && !empty($_REQUEST['filter-by-location'])) {
                    $args['tax_query'] = [
                        [
                            'taxonomy' => 'mulopimfwc_store_location',
                            'field'    => 'slug',
                            'terms'    => sanitize_text_field(wp_unslash($_REQUEST['filter-by-location'])),
                        ],
                    ];
                }
            }
        } elseif (isset($_REQUEST['filter-by-location']) && !empty($_REQUEST['filter-by-location'])) {
            // For direct URL access with filters
            $args['tax_query'] = [
                [
                    'taxonomy' => 'mulopimfwc_store_location',
                    'field'    => 'slug',
                    'terms'    => sanitize_text_field(wp_unslash($_REQUEST['filter-by-location'])),
                ],
            ];
        }

        // Enable ordering by location assignment
        $this->ordering_by_location = true;
        add_filter('posts_clauses', [$this, 'order_by_location_assignment'], 10, 2);

        $query = new WP_Query($args);

        // Remove the filter after query
        remove_filter('posts_clauses', [$this, 'order_by_location_assignment'], 10);
        $this->ordering_by_location = false;

        $this->items = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $product_id = get_the_ID();
                $product = wc_get_product($product_id);
                if (!$product) {
                    continue;
                }

                // Get product thumbnail
                $thumbnail = $product->get_image('thumbnail', ['class' => 'product-thumbnail']);

                // Get product locations
                $location_terms = wp_get_object_terms($product_id, 'mulopimfwc_store_location');

                // Get product type
                $product_type = $product->get_type();

                // Handle variable products
                if ($product_type === 'variable') {
                    $variations = [];
                    $available_variations = $product->get_available_variations();
                    foreach ($available_variations as $variation) {
                        $variations[] = [
                            'id' => $variation['variation_id'],
                            'attributes' => $variation['attributes'],
                            'price' => $variation['display_price'],
                            'stock' => $variation['is_in_stock'] ? $variation['max_qty'] : 0,
                        ];
                    }
                }

                // Collect all product data for quick edit popup
                $product_data = [
                    'id' => $product_id,
                    'name' => $product->get_name(),
                    'type' => $product_type,
                    'product_type' => $product_type, // Also include as product_type for JavaScript compatibility
                    'default' => [
                        'stock_quantity' => $product->get_stock_quantity(),
                        'regular_price' => $product->get_regular_price(),
                        'sale_price' => $product->get_sale_price(),
                        'backorders' => $product->get_backorders(),
                        'purchase_price' => get_post_meta($product_id, '_purchase_price', true),
                        'purchase_quantity' => get_post_meta($product_id, '_purchase_quantity', true),
                    ],
                    'locations' => [],
                    'variations' => [],
                ];

                // Get only assigned/activated locations data for this product
                $assigned_location_ids = [];
                if (!is_wp_error($location_terms) && !empty($location_terms)) {
                    $assigned_location_ids = array_map(function($term) {
                        return $term->term_id;
                    }, $location_terms);
                }
                
                if (!empty($assigned_location_ids)) {
                    foreach ($assigned_location_ids as $location_id) {
                        $location_term = get_term($location_id, 'mulopimfwc_store_location');
                        if (!$location_term || is_wp_error($location_term)) {
                            continue;
                        }
                        
                        $product_data['locations'][] = [
                            'id' => $location_id,
                            'name' => $location_term->name,
                            'stock' => get_post_meta($product_id, '_location_stock_' . $location_id, true),
                            'regular_price' => get_post_meta($product_id, '_location_regular_price_' . $location_id, true),
                            'sale_price' => get_post_meta($product_id, '_location_sale_price_' . $location_id, true),
                            'backorders' => get_post_meta($product_id, '_location_backorders_' . $location_id, true),
                        ];
                    }
                }

                // Get variation data for variable products
                if ($product_type === 'variable') {
                    $variation_ids = $product->get_children();
                    if (!empty($variation_ids)) {
                        foreach ($variation_ids as $variation_id) {
                            $variation = wc_get_product($variation_id);
                            if (!$variation) {
                                continue;
                            }

                            // Format attributes for display
                            $attributes = [];
                            foreach ($variation->get_attributes() as $key => $value) {
                                $attributes[$key] = $value;
                            }

                            $variation_data = [
                                'id' => $variation_id,
                                'attributes' => $attributes,
                                'default' => [
                                    'stock_quantity' => $variation->get_stock_quantity(),
                                    'regular_price' => $variation->get_regular_price(),
                                    'sale_price' => $variation->get_sale_price(),
                                    'backorders' => $variation->get_backorders(),
                                    'purchase_price' => get_post_meta($variation_id, '_purchase_price', true),
                                ],
                                'locations' => [],
                            ];

                            // Get location data for variation - only assigned locations
                            if (!empty($assigned_location_ids)) {
                                foreach ($assigned_location_ids as $location_id) {
                                    $location_term = get_term($location_id, 'mulopimfwc_store_location');
                                    if (!$location_term || is_wp_error($location_term)) {
                                        continue;
                                    }
                                    
                                    $variation_data['locations'][] = [
                                        'id' => $location_id,
                                        'name' => $location_term->name,
                                        'stock' => get_post_meta($variation_id, '_location_stock_' . $location_id, true),
                                        'regular_price' => get_post_meta($variation_id, '_location_regular_price_' . $location_id, true),
                                        'sale_price' => get_post_meta($variation_id, '_location_sale_price_' . $location_id, true),
                                        'backorders' => get_post_meta($variation_id, '_location_backorders_' . $location_id, true),
                                    ];
                                }
                            }

                            $product_data['variations'][] = $variation_data;
                        }
                    }
                }

                $this->items[] = [
                    'id' => $product_id,
                    'title' => $product->get_name(),
                    'image' => $thumbnail,
                    'location_terms' => is_wp_error($location_terms) ? [] : $location_terms,
                    'type' => $product_type,
                    'variations' => $product_type === 'variable' ? $variations : [],
                    'purchase_price' => get_post_meta($product_id, '_purchase_price', true),
                    'quick_edit_data' => $product_data, // Store complete data for popup
                ];
            }
            wp_reset_postdata();
        }

        $total_items = $query->found_posts;
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);
    }

    /**
     * Get sortable columns
     *
     * @return array
     */
    public function get_sortable_columns()
    {
        return [
            'title' => ['title', false],
        ];
    }

    /**
     * Extra controls to be displayed between bulk actions and pagination
     *
     * @param string $which Position (top or bottom)
     */
    protected function extra_tablenav($which)
    {
        global $mulopimfwc_locations;
        if ($which == 'top') {
            if (!is_wp_error($mulopimfwc_locations) && !empty($mulopimfwc_locations)) {
                echo '<div class="alignleft actions">';
                echo '<select name="filter-by-location">';
                echo '<option value="">' . esc_html__('All Locations', 'multi-location-product-and-inventory-management') . '</option>';

                foreach ($mulopimfwc_locations as $location) {
                    if (
                        isset($_REQUEST['_wpnonce']) &&
                        wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])), 'bulk-' . $this->_args['plural'])
                    ) {
                        $selected = isset($_REQUEST['filter-by-location']) && $_REQUEST['filter-by-location'] == rawurldecode($location->slug) ? 'selected="selected"' : '';
                    } else {
                        $selected =  '';
                    }
                    echo '<option value="' . esc_attr(rawurldecode($location->slug)) . '" ' . esc_attr($selected) . '>' . esc_html($location->name) . '</option>';
                }

                echo '</select>';

                // Add nonce field for the filter form - using the built-in WP_List_Table nonce
                wp_nonce_field('bulk-' . $this->_args['plural']);

                echo '<input type="submit" name="filter_action" id="filter-by-location-submit" class="button" value="' . esc_attr__('Filter', 'multi-location-product-and-inventory-management') . '">';
                echo '</div>';
            }
        }
    }

    /**
     * Modify SQL query to order by location assignment count
     *
     * @param array $clauses Query clauses
     * @param WP_Query $query The query object
     * @return array Modified clauses
     */
    public function order_by_location_assignment($clauses, $query)
    {
        // Only apply to our specific query
        if (!$this->ordering_by_location) {
            return $clauses;
        }

        global $wpdb;

        // Ensure join clause exists
        if (!isset($clauses['join'])) {
            $clauses['join'] = '';
        }

        // Add LEFT JOIN to count location assignments
        $clauses['join'] .= " LEFT JOIN (
            SELECT tr.object_id, COUNT(tr.term_taxonomy_id) as location_count
            FROM {$wpdb->term_relationships} tr
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            WHERE tt.taxonomy = 'mulopimfwc_store_location'
            GROUP BY tr.object_id
        ) as location_counts ON {$wpdb->posts}.ID = location_counts.object_id";

        // Modify ORDER BY to sort by location count (DESC) then by post title (ASC)
        $orderby = "COALESCE(location_counts.location_count, 0) DESC, {$wpdb->posts}.post_title ASC";
        
        if (!empty($clauses['orderby'])) {
            $clauses['orderby'] = $orderby . ', ' . $clauses['orderby'];
        } else {
            $clauses['orderby'] = $orderby;
        }

        return $clauses;
    }
}
