<?php
if (!defined('ABSPATH')) exit;



/**
 * Add Purchase Price field to WooCommerce product general tab
 */

// Add the Purchase Price field to the General tab
add_action('woocommerce_product_options_general_product_data', 'mulopimfwc_add_purchase_price_field');

function mulopimfwc_add_purchase_price_field()
{
    echo '<div class="options_group pricing">';

    woocommerce_wp_text_input(
        array(
            'id'          => '_purchase_price',
            'label'       => __('Purchase Price', 'multi-location-product-and-inventory-management') . ' (' . get_woocommerce_currency_symbol() . ')',
            'desc_tip'    => true,
            'description' => __('Enter the purchase price for this product.', 'multi-location-product-and-inventory-management'),
            'type'        => 'number',
            'custom_attributes' => array(
                'step' => 'any',
                'min'  => '0'
            )
        )
    );

    echo '</div>';
}

// Save the Purchase Price field value
add_action('woocommerce_process_product_meta', 'mulopimfwc_save_purchase_price_field');

function mulopimfwc_save_purchase_price_field($post_id)
{
    // Verify nonce
    if (!isset($_POST['location_stock_price_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['location_stock_price_nonce'])), 'location_stock_price_nonce_action')) {
        return;
    }
    $purchase_price = isset($_POST['_purchase_price']) ? wc_clean(sanitize_text_field(wp_unslash($_POST['_purchase_price']))) : '';
    update_post_meta($post_id, '_purchase_price', $purchase_price);
}

// Add Purchase Price to variable products (if needed)
add_action('woocommerce_variation_options_pricing', 'mulopimfwc_add_variation_purchase_price_field', 10, 3);

function mulopimfwc_add_variation_purchase_price_field($loop, $variation_data, $variation)
{
    woocommerce_wp_text_input(
        array(
            'id'            => '_purchase_price[' . $loop . ']',
            'label'         => __('Purchase Price', 'multi-location-product-and-inventory-management') . ' (' . get_woocommerce_currency_symbol() . ')',
            'desc_tip'      => true,
            'description'   => __('Enter the purchase price for this variation.', 'multi-location-product-and-inventory-management'),
            'value'         => get_post_meta($variation->ID, '_purchase_price', true),
            'type'          => 'number',
            'custom_attributes' => array(
                'step' => 'any',
                'min'  => '0'
            ),
            'wrapper_class' => 'form-row form-row-first'
        )
    );
}

// Save the Purchase Price field value for variable products
add_action('woocommerce_save_product_variation', 'mulopimfwc_save_variation_purchase_price_field', 10, 2);

function mulopimfwc_save_variation_purchase_price_field($variation_id, $loop)
{
    // Verify nonce
    if (!isset($_POST['location_stock_price_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['location_stock_price_nonce'])), 'location_stock_price_nonce_action')) {
        return;
    }
    $purchase_price = isset($_POST['_purchase_price'][$loop]) ? wc_clean(sanitize_text_field(wp_unslash($_POST['_purchase_price'][$loop]))) : '';
    update_post_meta($variation_id, '_purchase_price', $purchase_price);
}

// stock manage, price manage, backorder manage

// Add a new product data tab for location-specific settings
add_filter('woocommerce_product_data_tabs', function ($tabs) {
    $tabs['location_stock_price'] = array(
        'label'    => __('Location Settings', 'multi-location-product-and-inventory-management'),
        'target'   => 'location_stock_price_options',
        'class'    => array('show_if_simple', 'hide_if_variable', 'show_if_external'),
        'priority' => 21
    );
    return $tabs;
});

// Add location-specific fields to the product data panel
add_action('woocommerce_product_data_panels', function () {
    global $post;
    global $mulopimfwc_locations;
    $product = wc_get_product($post->ID);
    $is_stock_management_enabled = get_option('woocommerce_manage_stock');
?>
    <div id="location_stock_price_options" class="panel woocommerce_options_panel" style="padding: 0 20px;">
        <div class="options_group">
            <h3><?php echo esc_html_e('Location Specific Stock & Price Settings', 'multi-location-product-and-inventory-management'); ?></h3>
            <?php wp_nonce_field('location_stock_price_nonce_action', 'location_stock_price_nonce'); ?>
            <?php if (!empty($mulopimfwc_locations) && !is_wp_error($mulopimfwc_locations)) : ?>
                <table class="widefat">

                    <thead>
                        <tr>
                            <th><?php echo esc_html_e('Location', 'multi-location-product-and-inventory-management'); ?></th>
                            <?php if ($is_stock_management_enabled === "yes") : ?>
                                <th><?php echo esc_html_e('Stock Quantity', 'multi-location-product-and-inventory-management'); ?></th>
                            <?php endif; ?>
                            <th><?php echo esc_html_e('Regular Price', 'multi-location-product-and-inventory-management'); ?></th>
                            <th><?php echo esc_html_e('Sale Price', 'multi-location-product-and-inventory-management'); ?></th>
                            <th><?php echo esc_html_e('Backorders', 'multi-location-product-and-inventory-management'); ?></th>

                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="<?php echo $is_stock_management_enabled === "yes" ? 5 : 4; ?>">
                                <div id="plugincy_message" style="display: none; color: red;">Please select a location first. <span id="highlightButton" style="cursor:pointer;">Highlight Locations</span></div>
                            </td>
                        </tr>
                        <?php
                        $regular_price = $product->get_regular_price();
                        $sale_price = $product->get_sale_price();
                        foreach ($mulopimfwc_locations as $location) :
                            $location_stock = get_post_meta($post->ID, '_location_stock_' . $location->term_id, true);
                            $location_regular_price = get_post_meta($post->ID, '_location_regular_price_' . $location->term_id, true);
                            $location_sale_price = get_post_meta($post->ID, '_location_sale_price_' . $location->term_id, true);

                            $location_backorders = get_post_meta($post->ID, '_location_backorders_' . $location->term_id, true);
                        ?>
                        
                            <tr id="location-<?php echo esc_attr($location->term_id); ?>">
                                <td><?php echo esc_html($location->name); ?></td>
                                <?php if ($is_stock_management_enabled === "yes") : ?>
                                    <td>
                                        <input type="number" name="location_stock[<?php echo esc_attr($location->term_id); ?>]"
                                            value="<?php echo esc_attr($location_stock); ?>" step="1" min="0">
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <input type="text" name="location_regular_price[<?php echo esc_attr($location->term_id); ?>]"
                                        value="<?php echo esc_attr($location_regular_price ? $location_regular_price : ($location_regular_price === '' ? $regular_price : '')); ?>" class="wc_input_price">
                                </td>
                                <td>
                                    <input type="text" name="location_sale_price[<?php echo esc_attr($location->term_id); ?>]"
                                        value="<?php echo esc_attr($location_sale_price ? $location_sale_price : ($location_sale_price === '' ? $sale_price : '')); ?>" class="wc_input_price">
                                </td>

                                <td>
                                    <select name="location_backorders[<?php echo esc_attr($location->term_id); ?>]">
                                        <option value="no" <?php selected($location_backorders, 'no'); ?>><?php echo esc_html_e('No backorders', 'multi-location-product-and-inventory-management'); ?></option>
                                        <option value="notify" <?php selected($location_backorders, 'notify'); ?>><?php echo esc_html_e('Allow, but notify customer', 'multi-location-product-and-inventory-management'); ?></option>
                                        <option value="yes" <?php selected($location_backorders, 'yes'); ?>><?php echo esc_html_e('Allow', 'multi-location-product-and-inventory-management'); ?></option>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php echo esc_html_e('No store locations found. Please add locations first.', 'multi-location-product-and-inventory-management'); ?></p>
            <?php endif; ?>
        </div>
    </div>
<?php
});


// Save location-specific data for simple products
add_action('woocommerce_process_product_meta', function ($post_id) {
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
add_action('woocommerce_product_after_variable_attributes', function ($loop, $variation_data, $variation) {
    global $mulopimfwc_locations;

    if (empty($mulopimfwc_locations) || is_wp_error($mulopimfwc_locations)) {
        return;
    }
    $is_stock_management_enabled = get_option('woocommerce_manage_stock');
?>
    <div class="variable_location_pricing">
        <p class="form-row form-row-full"><strong><?php echo esc_html_e('Location Specific Settings', 'multi-location-product-and-inventory-management'); ?></strong></p>
        <?php wp_nonce_field('location_stock_price_nonce_action', 'location_stock_price_nonce'); ?>
        <div class="location_variation_data">
            <table class="location_variation_table">
                <thead>
                    <tr>
                        <th><?php echo esc_html_e('Location', 'multi-location-product-and-inventory-management'); ?></th>
                        <?php if ($is_stock_management_enabled === "yes") : ?>
                            <th><?php echo esc_html_e('Stock', 'multi-location-product-and-inventory-management'); ?></th>
                        <?php endif; ?>
                        <th><?php echo esc_html_e('Regular Price', 'multi-location-product-and-inventory-management'); ?></th>
                        <th><?php echo esc_html_e('Sale Price', 'multi-location-product-and-inventory-management'); ?></th>
                        <th><?php echo esc_html_e('Backorders', 'multi-location-product-and-inventory-management'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mulopimfwc_locations as $location) :
                        $location_stock = get_post_meta($variation->ID, '_location_stock_' . $location->term_id, true);
                        $location_regular_price = get_post_meta($variation->ID, '_location_regular_price_' . $location->term_id, true);
                        $location_sale_price = get_post_meta($variation->ID, '_location_sale_price_' . $location->term_id, true);
                        $location_backorders = get_post_meta($variation->ID, '_location_backorders_' . $location->term_id, true);
                    ?>
                        <tr>
                            <td><?php echo esc_html($location->name); ?></td>
                            <?php if ($is_stock_management_enabled === "yes") : ?>
                                <td>
                                    <input type="number"
                                        name="variation_location_stock[<?php echo esc_attr($loop); ?>][<?php echo esc_attr($location->term_id); ?>]"
                                        value="<?php echo esc_attr($location_stock); ?>"
                                        class="short" step="1" min="0">
                                </td>
                            <?php endif; ?>
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
                                    <option value="no" <?php selected($location_backorders, 'no'); ?>><?php echo esc_html_e('No backorders', 'multi-location-product-and-inventory-management'); ?></option>
                                    <option value="notify" <?php selected($location_backorders, 'notify'); ?>><?php echo esc_html_e('Allow, but notify customer', 'multi-location-product-and-inventory-management'); ?></option>
                                    <option value="yes" <?php selected($location_backorders, 'yes'); ?>><?php echo esc_html_e('Allow', 'multi-location-product-and-inventory-management'); ?></option>
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
add_action('woocommerce_save_product_variation', function ($variation_id, $loop) {
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
function mulopimfwc_get_current_store_location()
{
    return isset($_COOKIE['mulopimfwc_store_location']) ? sanitize_text_field(wp_unslash($_COOKIE['mulopimfwc_store_location'])) : '';
}

// Get location term ID from slug
function mulopimfwc_get_location_term_id($location_slug)
{
    if (empty($location_slug)) {
        return false;
    }

    $location = get_term_by('slug', $location_slug, 'mulopimfwc_store_location');
    return $location ? $location->term_id : false;
}

if ((get_option('mulopimfwc_display_options', ['enable_location_price' => 'yes'])['enable_location_price'] ?? 'yes') === 'yes' && !is_admin()) {
    // Override regular price for simple products
    add_filter('woocommerce_product_get_regular_price', function ($price, $product) {
        if ($product->is_type('variation')) {
            return $price; // Handle variations separately
        }
        $options = get_option('mulopimfwc_display_options', []);
        $enable_all_locations = isset($options['enable_all_locations']) ? $options['enable_all_locations'] : 'yes';
        $terms = wp_get_object_terms($product->get_id(), 'mulopimfwc_store_location', array('fields' => 'slugs'));
        if ($enable_all_locations === 'yes' && empty($terms)) {
            return $price; // Use default WooCommerce price
        }

        $location_slug = mulopimfwc_get_current_store_location();
        $location_id = mulopimfwc_get_location_term_id($location_slug);

        if (!$location_id) {
            return $price;
        }

        $location_price = get_post_meta($product->get_id(), '_location_regular_price_' . $location_id, true);

        return !empty($location_price) ? $location_price : $price;
    }, 10, 2);
    // Override sale price for simple products
    add_filter('woocommerce_product_get_sale_price', function ($price, $product) {
        if ($product->is_type('variation')) {
            return $price; // Handle variations separately
        }

        $options = get_option('mulopimfwc_display_options', []);
        $enable_all_locations = isset($options['enable_all_locations']) ? $options['enable_all_locations'] : 'yes';

        $terms = wp_get_object_terms($product->get_id(), 'mulopimfwc_store_location', array('fields' => 'slugs'));

        if ($enable_all_locations === 'yes' && empty($terms)) {
            return $price; // Use default WooCommerce price
        }

        $location_slug = mulopimfwc_get_current_store_location();
        $location_id = mulopimfwc_get_location_term_id($location_slug);

        if (!$location_id) {
            return $price;
        }

        $location_price = get_post_meta($product->get_id(), '_location_sale_price_' . $location_id, true);

        return !empty($location_price) ? $location_price : $price;
    }, 10, 2);
}

if ((get_option('mulopimfwc_display_options', ['enable_location_stock' => 'yes'])['enable_location_stock'] ?? 'yes') === 'yes' && !is_admin()) {
    // Override stock quantity for simple products
    add_filter('woocommerce_product_get_stock_quantity', function ($quantity, $product) {
        if ($product->is_type('variation')) {
            return $quantity; // Handle variations separately
        }

        $options = get_option('mulopimfwc_display_options', []);
        $enable_all_locations = isset($options['enable_all_locations']) ? $options['enable_all_locations'] : 'yes';

        $terms = wp_get_object_terms($product->get_id(), 'mulopimfwc_store_location', array('fields' => 'slugs'));
        if ($enable_all_locations === 'yes' && empty($terms)) {
            return $quantity; // Use default WooCommerce stock quantity
        }

        $location_slug = mulopimfwc_get_current_store_location();
        $location_id = mulopimfwc_get_location_term_id($location_slug);

        if (!$location_id) {
            return $quantity;
        }

        $location_stock = get_post_meta($product->get_id(), '_location_stock_' . $location_id, true);

        return $location_stock !== '' ? $location_stock : $quantity;
    }, 10, 2);
}

if ((get_option('mulopimfwc_display_options', ['enable_location_backorder' => 'yes'])['enable_location_backorder'] ?? 'yes') === 'yes' && !is_admin()) {

    // Override backorder setting for simple products
    add_filter('woocommerce_product_get_backorders', function ($backorders, $product) {
        if ($product->is_type('variation')) {
            return $backorders; // Handle variations separately
        }
        $options = get_option('mulopimfwc_display_options', []);
        $enable_all_locations = isset($options['enable_all_locations']) ? $options['enable_all_locations'] : 'yes';

        $terms = wp_get_object_terms($product->get_id(), 'mulopimfwc_store_location', array('fields' => 'slugs'));
        if ($enable_all_locations === 'yes' && empty($terms)) {
            return $backorders; // Use default WooCommerce backorder setting
        }

        $location_slug = mulopimfwc_get_current_store_location();
        $location_id = mulopimfwc_get_location_term_id($location_slug);

        if (!$location_id) {
            return $backorders;
        }

        $location_backorders = get_post_meta($product->get_id(), '_location_backorders_' . $location_id, true);

        return !empty($location_backorders) ? $location_backorders : $backorders;
    }, 10, 2);
}
if ((get_option('mulopimfwc_display_options', ['enable_location_stock' => 'yes'])['enable_location_stock'] ?? 'yes') === 'yes' && !is_admin()) {
    // Override product stock status based on location stock
    add_filter('woocommerce_product_get_stock_status', function ($status, $product) {
        if ($product->is_type('variation')) {
            return $status; // Handle variations separately
        }

        $location_slug = mulopimfwc_get_current_store_location();
        $location_id = mulopimfwc_get_location_term_id($location_slug);
        $product_id = $product->get_id();
        $options = get_option('mulopimfwc_display_options', []);
        $enable_all_locations = isset($options['enable_all_locations']) ? $options['enable_all_locations'] : 'yes';

        if (!$location_id) {
            return $status;
        }

        $location_stock = get_post_meta($product->get_id(), '_location_stock_' . $location_id, true);

        if ($location_stock === '') {
            return $status;
        }
        $terms = wp_get_object_terms($product_id, 'mulopimfwc_store_location', ['fields' => 'slugs']);

        if ($enable_all_locations === 'yes' && empty($terms)) {
            return $status; // Use default WooCommerce price
        }

        // if all products is selected
        if ($location_slug === 'all-products') {
            return $status; // Use default WooCommerce stock status
        }

        if ($enable_all_locations === 'yes' && empty($terms)) {
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

if ((get_option('mulopimfwc_display_options', ['enable_location_stock' => 'yes'])['enable_location_stock'] ?? 'yes') === 'yes' && !is_admin()) {

    // Override variation stock
    add_filter('woocommerce_product_variation_get_stock_quantity', function ($quantity, $variation) {
        $location_slug = mulopimfwc_get_current_store_location();
        $location_id = mulopimfwc_get_location_term_id($location_slug);

        if (!$location_id) {
            return $quantity;
        }

        $location_stock = get_post_meta($variation->get_id(), '_location_stock_' . $location_id, true);

        return $location_stock !== '' ? $location_stock : $quantity;
    }, 10, 2);
}

if ((get_option('mulopimfwc_display_options', ['enable_location_backorder' => 'yes'])['enable_location_backorder'] ?? 'yes') === 'yes' && !is_admin()) {
    // Override variation backorders
    add_filter('woocommerce_product_variation_get_backorders', function ($backorders, $variation) {
        $location_slug = mulopimfwc_get_current_store_location();
        $location_id = mulopimfwc_get_location_term_id($location_slug);

        if (!$location_id) {
            return $backorders;
        }

        $location_backorders = get_post_meta($variation->get_id(), '_location_backorders_' . $location_id, true);

        return !empty($location_backorders) ? $location_backorders : $backorders;
    }, 10, 2);
}

if ((get_option('mulopimfwc_display_options', ['enable_location_stock' => 'yes'])['enable_location_stock'] ?? 'yes') === 'yes' && !is_admin()) {
    // Handle stock reduction when order is placed
    add_action('woocommerce_reduce_order_stock', function ($order) {
        $location_slug = mulopimfwc_get_current_store_location();
        $location_id = mulopimfwc_get_location_term_id($location_slug);

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
    add_action('woocommerce_restore_order_stock', function ($order) {
        $location_slug = mulopimfwc_get_current_store_location();
        $location_id = mulopimfwc_get_location_term_id($location_slug);

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
add_filter('woocommerce_add_to_cart_validation', function ($passed, $product_id, $quantity, $variation_id = 0, $variations = array()) {
    $location_slug = mulopimfwc_get_current_store_location();
    $location_id = mulopimfwc_get_location_term_id($location_slug);

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
            (!$variation_id && $product_id == $cart_item['product_id'])
        ) {
            $qty_in_cart += $cart_item['quantity'];
        }
    }

    $total_required = $qty_in_cart + $quantity;

    // If backorders are not allowed and we don't have enough stock
    if ($location_backorders === 'no' && $location_stock < $total_required) {
        wc_add_notice(
            sprintf(
                esc_html('Sorry, "%s" has only %d left in stock at your selected location. Please adjust your quantity.', 'multi-location-product-and-inventory-management'),
                $product->get_name(),
                $location_stock
            ),
            'error'
        );
        return false;
    }

    return $passed;
}, 10, 5);

if ((get_option('mulopimfwc_display_options', ['enable_location_price' => 'yes'])['enable_location_price'] ?? 'yes') === 'yes') {
    // Override the final price for simple products
    add_filter('woocommerce_product_get_price', function ($price, $product) {
        if ($product->is_type('variation')) {
            return $price; // Handle variations separately
        }

        $location_slug = mulopimfwc_get_current_store_location();
        $location_id = mulopimfwc_get_location_term_id($location_slug);

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
    add_filter('woocommerce_product_variation_get_price', function ($price, $variation) {
        $location_slug = mulopimfwc_get_current_store_location();
        $location_id = mulopimfwc_get_location_term_id($location_slug);

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
    add_filter('woocommerce_variation_prices', function ($prices, $product, $for_display) {
        $location_slug = mulopimfwc_get_current_store_location();
        $location_id = mulopimfwc_get_location_term_id($location_slug);

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
add_action('woocommerce_single_product_summary', function () {
    global $product;
    $options = get_option('mulopimfwc_display_options', []);
    $enable_all_locations = isset($options['enable_all_locations']) ? $options['enable_all_locations'] : 'yes';
    if (!is_object($product)) {
        $product = wc_get_product(get_the_ID());
    }

    if (!$product) {
        return;
    }

    $location_slug = mulopimfwc_get_current_store_location();

    // If no location is selected or "all products" is selected, don't show the notice
    if (!$location_slug || $location_slug === 'all-products') {
        return;
    }

    // Check if the product belongs to the current location
    $terms = wp_get_object_terms($product->get_id(), 'mulopimfwc_store_location', ['fields' => 'slugs']);

    if ($enable_all_locations === 'yes' && empty($terms)) {
        return; // Show default WooCommerce notice
    }

    if (is_wp_error($terms) || !in_array($location_slug, $terms)) {
        // Product is not available in the current location - display a prominent notice
        echo '<div class="product-location-unavailable">';
        echo '<p class="unavailable-notice">' . esc_html_e('This product isn\'t available for your current location.', 'multi-location-product-and-inventory-management') . '</p>';
        echo '</div>';
    }
}, 5); // Priority 5 to show it near the top

// disable purchase

// Also prevent adding to cart through direct URLs or AJAX
// add_filter('woocommerce_add_to_cart_validation', function($valid, $product_id, $quantity) {
//     $location_slug = mulopimfwc_get_current_store_location();

//     // If no location is selected or "all products" is selected, keep default validation
//     if (!$location_slug || $location_slug === 'all-products') {
//         return $valid;
//     }

//     // Check if the product belongs to the current location
//     $terms = wp_get_object_terms($product_id, 'mulopimfwc_store_location', ['fields' => 'slugs']);

//     if (is_wp_error($terms) || !in_array($location_slug, $terms)) {
//         // Product is not available in the current location
//         wc_add_notice(__('This product isn\'t available for your current location and cannot be purchased.', 'multi-location-product-and-inventory-management'), 'error');
//         return false;
//     }

//     return $valid;
// }, 10, 3);

// Hide add to cart button on shop/archive pages for unavailable products
// add_filter('woocommerce_loop_add_to_cart_link', function($html, $product) {
//     $location_slug = mulopimfwc_get_current_store_location();

//     // If no location is selected or "all products" is selected, show normal button
//     if (!$location_slug || $location_slug === 'all-products') {
//         return $html;
//     }

//     // Check if the product belongs to the current location
//     $terms = wp_get_object_terms($product->get_id(), 'mulopimfwc_store_location', ['fields' => 'slugs']);

//     if (is_wp_error($terms) || !in_array($location_slug, $terms)) {
//         // Replace add to cart button with unavailable text
//         return '<span class="button unavailable-product">' . __('Unavailable at your location', 'multi-location-product-and-inventory-management') . '</span>';
//     }

//     return $html;
// }, 10, 2);

// if variation product & product is not available in current location hide add to cart button form.variations_form.cart { display: none; }
add_action('wp_footer', function () {
    if (is_product()) {
        global $product;
        $options = get_option('mulopimfwc_display_options', []);
        $enable_all_locations = isset($options['enable_all_locations']) ? $options['enable_all_locations'] : 'yes';

        if ($product->is_type('variable')) {
            $location_slug = mulopimfwc_get_current_store_location();

            // If no location is selected or "all products" is selected, show normal button
            if (! $location_slug || 'all-products' === $location_slug) {
                return;
            }

            // Check if the product belongs to the current location
            $terms = wp_get_object_terms($product->get_id(), 'mulopimfwc_store_location', array('fields' => 'slugs'));

            if ($enable_all_locations === 'yes' && empty($terms)) {
                return; // Show default WooCommerce notice
            }

            if (is_wp_error($terms) || ! in_array($location_slug, $terms, true)) {
                // Register a dummy stylesheet to attach inline styles
                wp_register_style('mulopimfwc-custom-woocommerce-style', false, array(), '1.0.4');
                wp_enqueue_style('mulopimfwc-custom-woocommerce-style');
                wp_add_inline_style('mulopimfwc-custom-woocommerce-style', '.variations_form.cart { display: none; }');
            }
        } else {
            $location_slug = mulopimfwc_get_current_store_location();

            // If no location is selected or "all products" is selected, show normal button
            if (! $location_slug || 'all-products' === $location_slug) {
                return;
            }

            // Check if the product belongs to the current location
            $terms = wp_get_object_terms($product->get_id(), 'mulopimfwc_store_location', array('fields' => 'slugs'));

            if ($enable_all_locations === 'yes' && empty($terms)) {
                return; // Show default WooCommerce notice
            }
            if (is_wp_error($terms) || ! in_array($location_slug, $terms, true)) {
                // Register a dummy stylesheet to attach inline styles
                wp_register_style('mulopimfwc-custom-woocommerce-style', false, array(), '1.0.4');
                wp_enqueue_style('mulopimfwc-custom-woocommerce-style');
                wp_add_inline_style('mulopimfwc-custom-woocommerce-style', 'form.cart { display: none; }');
            }
        }
    }
});


// add stock & price details in product pages
$options = get_option('mulopimfwc_display_options', ['enable_location_by_user_role' => []]);
$selected_roles = isset($options['enable_location_by_user_role']) ? $options['enable_location_by_user_role'] : [];
$current_user = wp_get_current_user();
$user_roles = $current_user->roles;

// Check if the current user role has permission
if (array_intersect($user_roles, $selected_roles)) {
    if ((get_option('mulopimfwc_display_options', ['enable_location_information' => 'yes'])['enable_location_information'] ?? 'yes') === 'yes') {
        // Add location-specific stock and price display on product pages
        add_action('woocommerce_single_product_summary', 'mulopimfwc_display_location_specific_stock_info', 25);
        add_action('woocommerce_shop_loop_item_title', 'mulopimfwc_display_location_specific_stock_info_loop', 15);
    }
}
/**
 * Display location-specific stock and price information on single product pages
 */
function mulopimfwc_display_location_specific_stock_info()
{
    global $product;
    $options = get_option('mulopimfwc_display_options', []);
    $enable_all_locations = isset($options['enable_all_locations']) ? $options['enable_all_locations'] : 'yes';
    // Get current location
    $location_slug = mulopimfwc_get_current_store_location();
    if (empty($location_slug) || $location_slug === 'all-products') {
        return; // No specific location selected
    }

    // Get location term
    $location = get_term_by('slug', $location_slug, 'mulopimfwc_store_location');
    if (!$location) {
        return;
    }

    $terms = wp_get_object_terms($product->get_id(), 'mulopimfwc_store_location', array('fields' => 'slugs'));
    if ($enable_all_locations === 'yes' && empty($terms)) {
        return; // Show default WooCommerce notice
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
    echo '<h4>' . sprintf(esc_html('Information for %s location', 'multi-location-product-and-inventory-management'), esc_attr($location->name)) . '</h4>';

    // Display stock status
    if ($location_stock !== '') {
        echo '<p class="location-stock">';
        echo '<strong>' . esc_html_e('Stock:', 'multi-location-product-and-inventory-management') . '</strong> ';

        if ($location_stock > 0) {
            echo '<span class="in-stock">' . sprintf(esc_html('%d item in stock', '%d items in stock', $location_stock, 'multi-location-product-and-inventory-management'), esc_attr($location_stock)) . '</span>';
        } else {
            if ($location_backorders === 'no') {
                echo '<span class="out-of-stock">' . esc_html('Out of stock', 'multi-location-product-and-inventory-management') . '</span>';
            } else {
                echo '<span class="on-backorder">' . esc_html('Available on backorder', 'multi-location-product-and-inventory-management') . '</span>';
            }
        }
        echo '</p>';
    }

    // Display location-specific prices if they exist
    if (!empty($location_regular_price)) {
        echo '<p class="location-price">';
        echo '<strong>' . esc_html_e('Price at this location:', 'multi-location-product-and-inventory-management') . '</strong> ';

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
function mulopimfwc_display_location_specific_stock_info_loop()
{
    global $product;
    $options = get_option('mulopimfwc_display_options', []);
    $enable_all_locations = isset($options['enable_all_locations']) ? $options['enable_all_locations'] : 'yes';
    // Get current location
    $location_slug = mulopimfwc_get_current_store_location();
    if (empty($location_slug) || $location_slug === 'all-products') {
        return; // No specific location selected
    }

    // Get location term
    $location = get_term_by('slug', $location_slug, 'mulopimfwc_store_location');
    if (!$location) {
        return;
    }

    $options = get_option('mulopimfwc_display_options', []);
    $enable_all_locations = isset($options['enable_all_locations']) ? $options['enable_all_locations'] : 'yes';
    $terms = wp_get_object_terms($product->get_id(), 'mulopimfwc_store_location', ['fields' => 'slugs']);
    if ($enable_all_locations === 'yes' && empty($terms)) {
        return; // Show default WooCommerce notice
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
            echo '<span class="in-stock">' . sprintf(esc_html('%d in stock', 'multi-location-product-and-inventory-management'), esc_attr($location_stock)) . '</span>';
        } else {
            if ($location_backorders === 'no') {
                echo '<span class="out-of-stock">' . esc_html('Out of stock', 'multi-location-product-and-inventory-management') . '</span>';
            } else {
                echo '<span class="on-backorder">' . esc_html('Backorder', 'multi-location-product-and-inventory-management') . '</span>';
            }
        }
        echo '</span>';
    }

    echo '</div>';
}
/**
 * Handle variable products - show location info for the selected variation
 */
add_action('woocommerce_available_variation', 'mulopimfwc_add_location_data_to_variations', 10, 3);
function mulopimfwc_add_location_data_to_variations($variation_data, $product, $variation)
{
    // Get current location
    $location_slug = mulopimfwc_get_current_store_location();
    if (empty($location_slug) || $location_slug === 'all-products') {
        return $variation_data; // No specific location selected
    }
    $options = get_option('mulopimfwc_display_options', []);
    $enable_all_locations = isset($options['enable_all_locations']) ? $options['enable_all_locations'] : 'yes';
    $terms = wp_get_object_terms($product->get_id(), 'mulopimfwc_store_location', ['fields' => 'slugs']);
    if ($enable_all_locations === 'yes' && empty($terms)) {
        return $variation_data;
    }
    // Get location term
    $location = get_term_by('slug', $location_slug, 'mulopimfwc_store_location');
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



if (array_intersect($user_roles, $selected_roles) && (get_option('mulopimfwc_display_options', ['enable_location_information' => 'yes'])['enable_location_information'] ?? 'yes') === 'yes') {

    /**
     * Add stock status to product category/archive pages
     */
    add_action('woocommerce_after_shop_loop_item', 'mulopimfwc_display_location_stock_status_in_loop', 9);
}
function mulopimfwc_display_location_stock_status_in_loop()
{
    global $product;
    $options = get_option('mulopimfwc_display_options', []);
    $enable_all_locations = isset($options['enable_all_locations']) ? $options['enable_all_locations'] : 'yes';
    // Get current location
    $location_slug = mulopimfwc_get_current_store_location();
    if (empty($location_slug) || $location_slug === 'all-products') {
        return; // No specific location selected
    }

    $terms = wp_get_object_terms($product->get_id(), 'mulopimfwc_store_location', ['fields' => 'slugs']);
    if ($enable_all_locations === 'yes' && empty($terms)) {
        return; // Show default WooCommerce notice
    }

    // Get location term
    $location = get_term_by('slug', $location_slug, 'mulopimfwc_store_location');
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
            echo '<span class="stock-badge in-stock">' . esc_html_e('In Stock', 'multi-location-product-and-inventory-management') . '</span>';
        } else {
            if ($location_backorders === 'no') {
                echo '<span class="stock-badge out-of-stock">' . esc_html_e('Out of Stock', 'multi-location-product-and-inventory-management') . '</span>';
            } else {
                echo '<span class="stock-badge on-backorder">' . esc_html_e('Backorder', 'multi-location-product-and-inventory-management') . '</span>';
            }
        }

        echo '</div>';
    }

    // Display location-specific price if available
    if (!empty($location_regular_price)) {
        echo '<div class="location-price-loop">';
        echo '<small>' . sprintf(esc_html('%s price:', 'multi-location-product-and-inventory-management'), esc_attr($location->name)) . '</small> ';

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

add_filter('manage_product_posts_columns', 'mulopimfwc_add_location_column_to_product_list', 20);
function mulopimfwc_add_location_column_to_product_list($columns)
{
    $new_columns = array();

    // Insert columns before the Locations column
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;

        // Insert the Locations column after the Name column
        if ($key === 'name') {
            $new_columns['locations'] = __('Stock & Price', 'multi-location-product-and-inventory-management');
        }
    }

    return $new_columns;
}

add_action('manage_product_posts_custom_column', 'mulopimfwc_populate_locations_column_in_product_list', 10, 2);
function mulopimfwc_populate_locations_column_in_product_list($column, $post_id)
{
    global $mulopimfwc_locations;
    if ($column === 'locations') {
        $product = wc_get_product($post_id);
        if (!$product) {
            echo '—';
            return;
        }

        if (!is_wp_error($mulopimfwc_locations) && !empty($mulopimfwc_locations)) {
            $output = '';

            // Handle variable products
            if ($product->is_type('variable')) {
                $variation_ids = $product->get_children();
                foreach ($variation_ids as $variation_id) {
                    $variation = new WC_Product_Variation($variation_id);
                    $variation_title = $variation->get_attributes(); // Get variation attributes
                    $variation_name = implode(', ', $variation_title); // Format variation name

                    $output .= '<b>' . esc_html($variation_name) . '</b>'; // Display variation name

                    // Show location-wise info
                    foreach ($mulopimfwc_locations as $location) {
                        $location_price = get_post_meta($variation_id, '_location_regular_price_' . $location->term_id, true);
                        $location_stock = get_post_meta($variation_id, '_location_stock_' . $location->term_id, true);

                        // Build output for this location
                        if ($location_stock !== '') {
                            $output .= '<div>' . esc_html($location->name) . ': ';
                            $output .= ($location_stock > 0) ?
                                '<mark class="instock">' . __('In stock', 'multi-location-product-and-inventory-management') . ' (' . $location_stock . ')</mark>' :
                                '<mark class="outofstock">' . __('Out of stock', 'multi-location-product-and-inventory-management') . '</mark>';

                            if ($location_price) {
                                $output .= ' - ' . wc_price($location_price);
                            }
                            $output .= '</div>'; // New line for each location
                        }
                    }

                    // Add default stock and price for variation
                    $default_stock_quantity = $variation->get_stock_quantity();
                    $default_stock_status = $variation->get_stock_status();
                    $default_price = $variation->get_regular_price();

                    $output .= '<div style="margin-top: 5px;"><strong>' . __('Default', 'multi-location-product-and-inventory-management') . ': </strong>';
                    
                    if ($default_stock_status === 'instock') {
                        $output .= '<mark class="instock">' . __('In stock', 'multi-location-product-and-inventory-management');
                        if ($default_stock_quantity) {
                            $output .= ' (' . $default_stock_quantity . ')';
                        }
                        $output .= '</mark>';
                    } else {
                        $output .= '<mark class="outofstock">' . __('Out of stock', 'multi-location-product-and-inventory-management') . '</mark>';
                    }

                    if ($default_price) {
                        $output .= ' - ' . wc_price($default_price);
                    }
                    $output .= '</div><br>';
                }
            } else {
                // For simple products - show location-wise info first
                foreach ($mulopimfwc_locations as $location) {
                    $location_price = get_post_meta($product->get_id(), '_location_regular_price_' . $location->term_id, true);
                    $location_stock = get_post_meta($product->get_id(), '_location_stock_' . $location->term_id, true);

                    if ($location_stock !== '') {
                        $output .= '<div>' . esc_html($location->name) . ': ';
                        $output .= ($location_stock > 0) ?
                            '<mark class="instock">' . __('In stock', 'multi-location-product-and-inventory-management') . ' (' . $location_stock . ')</mark>' :
                            '<mark class="outofstock">' . __('Out of stock', 'multi-location-product-and-inventory-management') . '</mark>';

                        if ($location_price) {
                            $output .= ' - ' . wc_price($location_price);
                        }
                        $output .= '</div>';
                    }
                }

                // Add default stock and price for simple product
                $default_stock_quantity = $product->get_stock_quantity();
                $default_stock_status = $product->get_stock_status();
                $default_price = $product->get_regular_price();

                $output .= '<div style="margin-top: 5px;"><strong>' . __('Default', 'multi-location-product-and-inventory-management') . ': </strong>';
                
                if ($default_stock_status === 'instock') {
                    $output .= '<mark class="instock">' . __('In stock', 'multi-location-product-and-inventory-management');
                    if ($default_stock_quantity) {
                        $output .= ' (' . $default_stock_quantity . ')';
                    }
                    $output .= '</mark>';
                } else {
                    $output .= '<mark class="outofstock">' . __('Out of stock', 'multi-location-product-and-inventory-management') . '</mark>';
                }

                if ($default_price) {
                    $output .= ' - ' . wc_price($default_price);
                }
                $output .= '</div>';
            }

            echo wp_kses_post($output) ?: '<span class="na">—</span>';
        } else {
            // If no locations are set, show only default info
            if ($product->is_type('variable')) {
                $variation_ids = $product->get_children();
                $output = '';
                foreach ($variation_ids as $variation_id) {
                    $variation = new WC_Product_Variation($variation_id);
                    $variation_title = $variation->get_attributes();
                    $variation_name = implode(', ', $variation_title);

                    $output .= '<b>' . esc_html($variation_name) . '</b>';
                    
                    $default_stock_quantity = $variation->get_stock_quantity();
                    $default_stock_status = $variation->get_stock_status();
                    $default_price = $variation->get_regular_price();

                    $output .= '<div><strong>' . __('Default', 'multi-location-product-and-inventory-management') . ': </strong>';
                    
                    if ($default_stock_status === 'instock') {
                        $output .= '<mark class="instock">' . __('In stock', 'multi-location-product-and-inventory-management');
                        if ($default_stock_quantity) {
                            $output .= ' (' . $default_stock_quantity . ')';
                        }
                        $output .= '</mark>';
                    } else {
                        $output .= '<mark class="outofstock">' . __('Out of stock', 'multi-location-product-and-inventory-management') . '</mark>';
                    }

                    if ($default_price) {
                        $output .= ' - ' . wc_price($default_price);
                    }
                    $output .= '</div><br>';
                }
                echo wp_kses_post($output);
            } else {
                $default_stock_quantity = $product->get_stock_quantity();
                $default_stock_status = $product->get_stock_status();
                $default_price = $product->get_regular_price();

                $output = '<div><strong>' . __('Default', 'multi-location-product-and-inventory-management') . ': </strong>';
                
                if ($default_stock_status === 'instock') {
                    $output .= '<mark class="instock">' . __('In stock', 'multi-location-product-and-inventory-management');
                    if ($default_stock_quantity) {
                        $output .= ' (' . $default_stock_quantity . ')';
                    }
                    $output .= '</mark>';
                } else {
                    $output .= '<mark class="outofstock">' . __('Out of stock', 'multi-location-product-and-inventory-management') . '</mark>';
                }

                if ($default_price) {
                    $output .= ' - ' . wc_price($default_price);
                }
                $output .= '</div>';
                
                echo wp_kses_post($output);
            }
        }
    }
}

// hide stock & price column
add_filter('manage_edit-product_columns', 'mulopimfwc_remove_default_product_columns', 20);
function mulopimfwc_remove_default_product_columns($columns)
{
    // Unset the default stock and price columns
    unset($columns['is_in_stock']);
    unset($columns['price']);
    return $columns;
}