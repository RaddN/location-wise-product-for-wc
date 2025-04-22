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
class Plugincylwp_Product_Location_Table extends WP_List_Table {

    /**
     * Constructor
     */
    public function __construct() {
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
    public function get_columns() {
        return [
            'cb'        => '<input type="checkbox" />',
            'image'     => __('Image', 'location-wise-product'),
            'title'     => __('Product', 'location-wise-product'),
            'stock'     => __('Stock by Location', 'location-wise-product'),
            'price'     => __('Price by Location', 'location-wise-product'),
            'locations' => __('Locations', 'location-wise-product'),
            'actions'   => __('Actions', 'location-wise-product'),
        ];
    }

    /**
     * Default column rendering
     *
     * @param array $item Item data
     * @param string $column_name Column name
     * @return string
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'title':
                return $item['title'];
            case 'stock':
                return $this->get_location_stock_display($item);
            case 'price':
                return $this->get_location_price_display($item);
            case 'locations':
                return $this->get_locations_display($item);
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
    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="product[]" value="%s" />', $item['id']);
    }

    /**
     * Image column
     *
     * @param array $item Item data
     * @return string
     */
    public function column_image($item) {
        return $item['image'];
    }

    /**
     * Title column with action links
     *
     * @param array $item Item data
     * @return string
     */
    public function column_title($item) {
        $title = '<strong><a href="' . esc_url(get_edit_post_link($item['id'])) . '">' . esc_html($item['title']) . '</a></strong>';
        $title .= '<div class="row-actions">';
        $title .= '<span class="edit"><a href="' . esc_url(get_edit_post_link($item['id'])) . '">' . __('Edit', 'location-wise-product') . '</a> | </span>';
        $title .= '<span class="view"><a href="' . esc_url(get_permalink($item['id'])) . '">' . __('View', 'location-wise-product') . '</a></span>';
        $title .= '</div>';
        return $title;
    }

    /**
     * Get stock display for each location
     *
     * @param array $item Item data
     * @return string
     */
    private function get_location_stock_display($item) {
        $output = '<div class="location-stock-container">';
        if ($item['type'] === 'variable' && !empty($item['variations'])) {
            foreach ($item['variations'] as $variation) {
                $variation_title = implode(', ', array_map(function ($key, $value) {
                    return ucfirst(str_replace('attribute_pa_', '', $key)) . ': ' . $value;
                }, array_keys($variation['attributes']), $variation['attributes']));
                $output .= '<div class="variation-stock-item">';
                $output .= '<strong>' . esc_html($variation_title) . '</strong>';
                $output .= '<div class="location-stock-item">';
                $output .= '<span class="location-name">' . __('Default', 'location-wise-product') . ':</span> ';
                $output .= '<span class="stock-value">' . __('In stock', 'location-wise-product') . ' (' . esc_html($variation['stock']) . ')</span>';
                $output .= '</div>';
                if (!empty($item['location_terms'])) {
                    foreach ($item['location_terms'] as $location) {
                        $stock = get_post_meta($variation['id'], '_location_stock_' . $location->term_id, true);
                        $output .= '<div class="location-stock-item">';
                        $output .= '<span class="location-name">' . esc_html($location->name) . ':</span> ';
                        $output .= '<span class="stock-value">' . (!empty($stock) ? __('In stock', 'location-wise-product') . ' (' . esc_html($stock) . ')' : __('Out of stock', 'location-wise-product')) . '</span>';
                        $output .= '</div>';
                    }
                }
                $output .= '</div>';
            }
        } else {
            $default_stock = get_post_meta($item['id'], "_stock", true);
            $output .= '<div class="location-stock-item">';
            $output .= '<span class="location-name">' . __('Default', 'location-wise-product') . ':</span> ';
            $output .= '<span class="stock-value">' . ($default_stock ? __('In stock', 'location-wise-product') . ' (' . esc_html($default_stock) . ')' : __('Out of stock', 'location-wise-product')) . '</span>';
            $output .= '</div>';
            if (!empty($item['location_terms'])) {
                foreach ($item['location_terms'] as $location) {
                    $stock = get_post_meta($item['id'], '_location_stock_' . $location->term_id, true);
                    $output .= '<div class="location-stock-item">';
                    $output .= '<span class="location-name">' . esc_html($location->name) . ':</span> ';
                    $output .= '<span class="stock-value">' . (!empty($stock) ? __('In stock', 'location-wise-product') . ' (' . esc_html($stock) . ')' : __('Out of stock', 'location-wise-product')) . '</span>';
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
    private function get_location_price_display($item) {
        $output = '<div class="location-price-container">';
        if ($item['type'] === 'variable' && !empty($item['variations'])) {
            foreach ($item['variations'] as $variation) {
                $variation_title = implode(', ', array_map(function ($key, $value) {
                    return ucfirst(str_replace('attribute_pa_', '', $key)) . ': ' . $value;
                }, array_keys($variation['attributes']), $variation['attributes']));
                $output .= '<div class="variation-price-item">';
                $output .= '<strong>' . esc_html($variation_title) . '</strong>';
                $output .= '<div class="location-price-item">';
                $output .= '<span class="location-name">' . __('Default', 'location-wise-product') . ':</span> ';
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
            }
        } else {
            $default_price = get_post_meta($item['id'], "_price", true);
            $output .= '<div class="location-price-item">';
            $output .= '<span class="location-name">' . __('Default', 'location-wise-product') . ':</span> ';
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
     * Get locations display
     *
     * @param array $item Item data
     * @return string
     */
    private function get_locations_display($item) {
        $locations = $item['location_terms'];
        if (empty($locations)) {
            return '<span class="no-locations">' . __('N/A', 'location-wise-product') . '</span>';
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
    private function get_actions_display($item) {
        // Create nonce for action buttons
        $nonce = wp_create_nonce('location_product_action_nonce');
        
        $locations = $item['location_terms'];
        if (empty($locations)) {
            return '<a href="#" class="button button-small add-location" data-product-id="' . esc_attr($item['id']) . '" data-nonce="' . esc_attr($nonce) . '">' . __('Add to Location', 'location-wise-product') . '</a>';
        }
        
        $output = '<div class="location-actions">';
        foreach ($locations as $location) {
            $is_active = !get_post_meta($item['id'], '_location_disabled_' . $location->term_id, true);
            $action_class = $is_active ? 'deactivate-location' : 'activate-location';
            $action_text = $is_active ? __('Deactivate', 'location-wise-product') : __('Activate', 'location-wise-product');
            $button_class = $is_active ? 'button-secondary' : 'button-primary';
            
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
        $output .= '</div>';
        return $output;
    }

    /**
     * Prepare table items
     */
    public function prepare_items() {
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
        if (isset($_REQUEST['filter_action']) && $_REQUEST['filter_action'] == __('Filter', 'location-wise-product')) {
            // Verify the nonce
            if (
                isset($_REQUEST['_wpnonce']) && 
                wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])), 'bulk-' . $this->_args['plural'])
            ) {
                // Process filter
                if (isset($_REQUEST['filter-by-location']) && !empty($_REQUEST['filter-by-location'])) {
                    $args['tax_query'] = [
                        [
                            'taxonomy' => 'store_location',
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
                    'taxonomy' => 'store_location',
                    'field'    => 'slug',
                    'terms'    => sanitize_text_field(wp_unslash($_REQUEST['filter-by-location'])),
                ],
            ];
        }

        $query = new WP_Query($args);
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
                $location_terms = wp_get_object_terms($product_id, 'store_location');
                
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

                $this->items[] = [
                    'id' => $product_id,
                    'title' => $product->get_name(),
                    'image' => $thumbnail,
                    'location_terms' => is_wp_error($location_terms) ? [] : $location_terms,
                    'type' => $product_type,
                    'variations' => $product_type === 'variable' ? $variations : [],
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
    public function get_sortable_columns() {
        return [
            'title' => ['title', false],
        ];
    }

    /**
     * Extra controls to be displayed between bulk actions and pagination
     *
     * @param string $which Position (top or bottom)
     */
    protected function extra_tablenav($which) {
        if ($which == 'top') {
            $locations = get_terms([
                'taxonomy' => 'store_location',
                'hide_empty' => false,
            ]);
            
            if (!is_wp_error($locations) && !empty($locations)) {
                echo '<div class="alignleft actions">';
                echo '<select name="filter-by-location">';
                echo '<option value="">' . esc_html__('All Locations', 'location-wise-product') . '</option>';
                
                foreach ($locations as $location) {
                    if(isset($_REQUEST['_wpnonce']) && 
                    wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])), 'bulk-' . $this->_args['plural'])){
                    $selected = isset($_REQUEST['filter-by-location']) && $_REQUEST['filter-by-location'] == $location->slug ? 'selected="selected"' : '';
                    } else {
                        $selected =  '';
                    }
                    echo '<option value="' . esc_attr($location->slug) . '" ' . esc_attr($selected) . '>' . esc_html($location->name) . '</option>';
                }
                
                echo '</select>';
                
                // Add nonce field for the filter form - using the built-in WP_List_Table nonce
                wp_nonce_field('bulk-' . $this->_args['plural']);
                
                echo '<input type="submit" name="filter_action" id="filter-by-location-submit" class="button" value="' . esc_attr__('Filter', 'location-wise-product') . '">';
                echo '</div>';
            }
        }
    }
}