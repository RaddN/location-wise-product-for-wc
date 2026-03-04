<?php
if (!defined('ABSPATH')) exit;


if (!function_exists('mulopimfwc_get_currency_symbol_for_admin_input')) {
    /**
     * Resolve a human-readable currency symbol for admin input prefix display.
     *
     * @param int|null $location_term_id Null means default store currency.
     * @return string
     */
    function mulopimfwc_get_currency_symbol_for_admin_input($location_term_id = null)
    {
        $currency_code = strtoupper((string) get_option('woocommerce_currency', 'USD'));
        if ($currency_code === '') {
            $currency_code = 'USD';
        }

        if (function_exists('mulopimfwc_get_currency_settings_for_location')) {
            $settings = (array) mulopimfwc_get_currency_settings_for_location($location_term_id);
            $resolved_code = strtoupper(trim((string) ($settings['currency'] ?? '')));
            if ($resolved_code !== '') {
                $currency_code = $resolved_code;
            }
        }

        $currency_symbol = function_exists('get_woocommerce_currency_symbol')
            ? (string) get_woocommerce_currency_symbol($currency_code)
            : '';

        if ($currency_symbol === '') {
            $currency_symbol = $currency_code;
        }

        $currency_symbol = html_entity_decode($currency_symbol, ENT_QUOTES, get_bloginfo('charset'));
        return $currency_symbol !== '' ? $currency_symbol : $currency_code;
    }
}

if (!function_exists('mulopimfwc_get_currency_conversion_for_admin_input')) {
    /**
     * Resolve conversion metadata for admin price validation/presentation.
     *
     * @param int|null $location_term_id Null means default/base currency context.
     * @return array{rate: string, should_convert: string}
     */
    function mulopimfwc_get_currency_conversion_for_admin_input($location_term_id = null)
    {
        $term_id = (int) $location_term_id;
        $rate = 1.0;
        $should_convert = false;

        if ($term_id > 0 && function_exists('mulopimfwc_get_location_currency_conversion_context')) {
            $context = (array) mulopimfwc_get_location_currency_conversion_context($term_id);
            if (isset($context['rate']) && is_numeric($context['rate']) && (float) $context['rate'] > 0) {
                $rate = (float) $context['rate'];
            }
            $should_convert = !empty($context['should_convert']) && $rate > 0;
        }

        if (function_exists('wc_format_decimal')) {
            $formatted = wc_format_decimal($rate, 6, false);
            if ($formatted !== '' && is_numeric($formatted)) {
                return [
                    'rate' => (string) $formatted,
                    'should_convert' => $should_convert ? 'yes' : 'no',
                ];
            }
        }

        $fallback = number_format($rate, 6, '.', '');
        $fallback = rtrim(rtrim($fallback, '0'), '.');
        if ($fallback === '') {
            $fallback = '1';
        }

        return [
            'rate' => $fallback,
            'should_convert' => $should_convert ? 'yes' : 'no',
        ];
    }
}



/**
 * Add Purchase Price & Purchase Quantity field to WooCommerce product general tab
 */

// Add the Purchase Price field to the General tab
add_action('woocommerce_product_options_general_product_data', 'mulopimfwc_add_purchase_price_field');

function mulopimfwc_add_purchase_price_field()
{
    global $post;
    $product = ($post && !empty($post->ID)) ? wc_get_product($post->ID) : null;
    $default_currency_symbol = mulopimfwc_get_currency_symbol_for_admin_input(null);

    // Variable products should use variation-level purchase fields, not parent-level fields.
    if ($product && $product->is_type('variable')) {
        return;
    }

    echo '<div class="options_group pricing">';

    woocommerce_wp_text_input(
        array(
            'id'          => '_purchase_price',
            'label'       => __('Purchase Price', 'multi-location-product-and-inventory-management'),
            'desc_tip'    => true,
            'description' => __('Enter the purchase price for this product.', 'multi-location-product-and-inventory-management'),
            'type'        => 'number',
            'class'       => 'wc_input_price mulopimfwc-currency-prefix-source',
            'custom_attributes' => array(
                'step' => 'any',
                'min'  => '0',
                'data-currency-symbol' => $default_currency_symbol,
            ),
            'wrapper_class' => 'mulopimfwc-currency-prefix-field',
        )
    );

    woocommerce_wp_text_input(
        array(
            'id'          => '_purchase_quantity',
            'label'       => __('Total Quantity Purchase', 'multi-location-product-and-inventory-management'),
            'desc_tip'    => true,
            'description' => __('Enter the total quantity purchase for this product.', 'multi-location-product-and-inventory-management'),
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
    $purchase_quantity =  isset($_POST['_purchase_quantity']) ? wc_clean(sanitize_text_field(wp_unslash($_POST['_purchase_quantity']))) : '';
    update_post_meta($post_id, '_purchase_price', $purchase_price);
    update_post_meta($post_id, '_purchase_quantity', $purchase_quantity);
}

// Add Purchase Price to variable products (if needed)
add_action('woocommerce_variation_options_pricing', 'mulopimfwc_add_variation_purchase_price_field', 10, 3);

function mulopimfwc_add_variation_purchase_price_field($loop, $variation_data, $variation)
{
    $default_currency_symbol = mulopimfwc_get_currency_symbol_for_admin_input(null);

    woocommerce_wp_text_input(
        array(
            'id'            => '_purchase_price[' . $loop . ']',
            'label'         => __('Purchase Price', 'multi-location-product-and-inventory-management'),
            'desc_tip'      => true,
            'description'   => __('Enter the purchase price for this variation.', 'multi-location-product-and-inventory-management'),
            'value'         => get_post_meta($variation->ID, '_purchase_price', true),
            'type'          => 'number',
            'class'         => 'wc_input_price mulopimfwc-currency-prefix-source',
            'custom_attributes' => array(
                'step' => 'any',
                'min'  => '0',
                'data-currency-symbol' => $default_currency_symbol,
            ),
            'wrapper_class' => 'form-row form-row-first mulopimfwc-currency-prefix-field',
        )
    );

    woocommerce_wp_text_input(
        array(
            'id'            => '_purchase_quantity[' . $loop . ']',
            'label'         => __('Purchase Quantity', 'multi-location-product-and-inventory-management'),
            'desc_tip'      => true,
            'description'   => __('Enter the purchase quantiy for this variation.', 'multi-location-product-and-inventory-management'),
            'value'         => get_post_meta($variation->ID, '_purchase_quantity', true),
            'type'          => 'number',
            'custom_attributes' => array(
                'step' => 'any',
                'min'  => '0'
            ),
            'wrapper_class' => 'form-row form-row-last'
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
    $purchase_quantity = isset($_POST['_purchase_quantity'][$loop]) ? wc_clean(sanitize_text_field(wp_unslash($_POST['_purchase_quantity'][$loop]))) : '';
    update_post_meta($variation_id, '_purchase_price', $purchase_price);
    update_post_meta($variation_id, '_purchase_quantity', $purchase_quantity);
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
                            <th><?php echo esc_html_e('Stock Quantity', 'multi-location-product-and-inventory-management'); ?></th>
                            <th><?php echo esc_html_e('Regular Price', 'multi-location-product-and-inventory-management'); ?></th>
                            <th><?php echo esc_html_e('Sale Price', 'multi-location-product-and-inventory-management'); ?></th>
                            <th><?php echo esc_html_e('Backorders', 'multi-location-product-and-inventory-management'); ?></th>

                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="4">
                                <div id="plugincy_message" style="display: none; color: red;">Please select a location first. <span id="highlightButton" style="cursor:pointer;">Highlight Locations</span></div>
                            </td>
                        </tr>
                        <?php
                        foreach ($mulopimfwc_locations as $location) :
                            $location_currency_symbol = mulopimfwc_get_currency_symbol_for_admin_input((int) $location->term_id);
                            $location_currency_conversion = mulopimfwc_get_currency_conversion_for_admin_input((int) $location->term_id);
                            $location_stock = get_post_meta($post->ID, '_location_stock_' . $location->term_id, true);
                            $location_regular_price = get_post_meta($post->ID, '_location_regular_price_' . $location->term_id, true);
                            $location_sale_price = get_post_meta($post->ID, '_location_sale_price_' . $location->term_id, true);

                            $location_backorders = get_post_meta($post->ID, '_location_backorders_' . $location->term_id, true);
                        ?>

                            <tr id="location-<?php echo esc_attr($location->term_id); ?>" data-currency-symbol="<?php echo esc_attr($location_currency_symbol); ?>" data-currency-rate="<?php echo esc_attr($location_currency_conversion['rate']); ?>" data-currency-should-convert="<?php echo esc_attr($location_currency_conversion['should_convert']); ?>">
                                <td><?php echo esc_html($location->name); ?></td>
                                <td class="location-stock-quantity">
                                    <input type="number" name="location_stock[<?php echo esc_attr($location->term_id); ?>]"
                                        value="<?php echo esc_attr($location_stock); ?>" step="1" min="0">
                                </td>
                                <td>
                                    <input type="text" name="location_regular_price[<?php echo esc_attr($location->term_id); ?>]"
                                        value="<?php echo esc_attr($location_regular_price); ?>" class="wc_input_price mulopimfwc-currency-prefix-source" data-currency-symbol="<?php echo esc_attr($location_currency_symbol); ?>" data-currency-rate="<?php echo esc_attr($location_currency_conversion['rate']); ?>" data-currency-should-convert="<?php echo esc_attr($location_currency_conversion['should_convert']); ?>">
                                </td>
                                <td>
                                    <input type="text" name="location_sale_price[<?php echo esc_attr($location->term_id); ?>]"
                                        value="<?php echo esc_attr($location_sale_price); ?>" class="wc_input_price mulopimfwc-currency-prefix-source" data-currency-symbol="<?php echo esc_attr($location_currency_symbol); ?>" data-currency-rate="<?php echo esc_attr($location_currency_conversion['rate']); ?>" data-currency-should-convert="<?php echo esc_attr($location_currency_conversion['should_convert']); ?>">
                                </td>

                                <td>
                                    <select name="location_backorders[<?php echo esc_attr($location->term_id); ?>]">
                                        <option value="off" <?php selected($location_backorders, 'off'); ?>><?php echo esc_html_e('No backorders', 'multi-location-product-and-inventory-management'); ?></option>
                                        <option value="notify" <?php selected($location_backorders, 'notify'); ?>><?php echo esc_html_e('Allow, but notify customer', 'multi-location-product-and-inventory-management'); ?></option>
                                        <option value="on" <?php selected($location_backorders, 'on'); ?>><?php echo esc_html_e('Allow', 'multi-location-product-and-inventory-management'); ?></option>
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
            if (!is_numeric($location_id)) {
                continue;
            }

            $price = trim((string) $price);
            $meta_key = '_location_regular_price_' . intval($location_id);

            if ($price === '') {
                delete_post_meta($post_id, $meta_key);
                continue;
            }

            if (is_numeric($price)) {
                update_post_meta($post_id, $meta_key, wc_format_decimal($price));
            }
        }
    }

    // Save location sale prices
    if (isset($_POST['location_sale_price']) && is_array($_POST['location_sale_price'])) {
        foreach (array_map('sanitize_text_field', wp_unslash($_POST['location_sale_price'])) as $location_id => $price) {
            if (!is_numeric($location_id)) {
                continue;
            }

            $price = trim((string) $price);
            $meta_key = '_location_sale_price_' . intval($location_id);

            if ($price === '') {
                delete_post_meta($post_id, $meta_key);
                continue;
            }

            if (is_numeric($price)) {
                update_post_meta($post_id, $meta_key, wc_format_decimal($price));
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
                        <th><?php echo esc_html_e('Stock', 'multi-location-product-and-inventory-management'); ?></th>
                        <th><?php echo esc_html_e('Regular Price', 'multi-location-product-and-inventory-management'); ?></th>
                        <th><?php echo esc_html_e('Sale Price', 'multi-location-product-and-inventory-management'); ?></th>
                        <th><?php echo esc_html_e('Backorders', 'multi-location-product-and-inventory-management'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mulopimfwc_locations as $location) :
                        $location_currency_symbol = mulopimfwc_get_currency_symbol_for_admin_input((int) $location->term_id);
                        $location_currency_conversion = mulopimfwc_get_currency_conversion_for_admin_input((int) $location->term_id);
                        $location_stock = get_post_meta($variation->ID, '_location_stock_' . $location->term_id, true);
                        $location_regular_price = get_post_meta($variation->ID, '_location_regular_price_' . $location->term_id, true);
                        $location_sale_price = get_post_meta($variation->ID, '_location_sale_price_' . $location->term_id, true);
                        $location_backorders = get_post_meta($variation->ID, '_location_backorders_' . $location->term_id, true);
                    ?>
                        <tr id="location-<?php echo esc_attr($location->term_id); ?>" data-currency-symbol="<?php echo esc_attr($location_currency_symbol); ?>" data-currency-rate="<?php echo esc_attr($location_currency_conversion['rate']); ?>" data-currency-should-convert="<?php echo esc_attr($location_currency_conversion['should_convert']); ?>">
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
                                    class="wc_input_price short mulopimfwc-currency-prefix-source"
                                    data-currency-symbol="<?php echo esc_attr($location_currency_symbol); ?>"
                                    data-currency-rate="<?php echo esc_attr($location_currency_conversion['rate']); ?>"
                                    data-currency-should-convert="<?php echo esc_attr($location_currency_conversion['should_convert']); ?>">
                            </td>
                            <td>
                                <input type="text"
                                    name="variation_location_sale_price[<?php echo esc_attr($loop); ?>][<?php echo esc_attr($location->term_id); ?>]"
                                    value="<?php echo esc_attr($location_sale_price); ?>"
                                    class="wc_input_price short mulopimfwc-currency-prefix-source"
                                    data-currency-symbol="<?php echo esc_attr($location_currency_symbol); ?>"
                                    data-currency-rate="<?php echo esc_attr($location_currency_conversion['rate']); ?>"
                                    data-currency-should-convert="<?php echo esc_attr($location_currency_conversion['should_convert']); ?>">
                            </td>
                            <td>
                                <select name="variation_location_backorders[<?php echo esc_attr($loop); ?>][<?php echo esc_attr($location->term_id); ?>]">
                                    <option value="off" <?php selected($location_backorders, 'off'); ?>><?php echo esc_html_e('No backorders', 'multi-location-product-and-inventory-management'); ?></option>
                                    <option value="notify" <?php selected($location_backorders, 'notify'); ?>><?php echo esc_html_e('Allow, but notify customer', 'multi-location-product-and-inventory-management'); ?></option>
                                    <option value="on" <?php selected($location_backorders, 'on'); ?>><?php echo esc_html_e('Allow', 'multi-location-product-and-inventory-management'); ?></option>
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
            if (!is_numeric($location_id)) {
                continue;
            }

            $price = trim((string) $price);
            $meta_key = '_location_regular_price_' . intval($location_id);

            if ($price === '') {
                delete_post_meta($variation_id, $meta_key);
                continue;
            }

            if (is_numeric($price)) {
                update_post_meta($variation_id, $meta_key, wc_format_decimal($price));
            }
        }
    }

    // Save variation location sale prices
    if (isset($_POST['variation_location_sale_price'][$loop]) && is_array($_POST['variation_location_sale_price'][$loop])) {
        foreach (array_map('sanitize_text_field', wp_unslash($_POST['variation_location_sale_price'][$loop])) as $location_id => $price) {
            if (!is_numeric($location_id)) {
                continue;
            }

            $price = trim((string) $price);
            $meta_key = '_location_sale_price_' . intval($location_id);

            if ($price === '') {
                delete_post_meta($variation_id, $meta_key);
                continue;
            }

            if (is_numeric($price)) {
                update_post_meta($variation_id, $meta_key, wc_format_decimal($price));
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
    // First check if cookie is set
    $cookie_location = mulopimfwc_get_store_location_cookie();

    if (!empty($cookie_location)) {
        return $cookie_location;
    }

    // If cookie is empty, check if popup is enabled
    global $mulopimfwc_options;
    $options = is_array($mulopimfwc_options ?? null)
        ? $mulopimfwc_options
        : get_option('mulopimfwc_display_options', []);
    $enable_popup = isset($options['enable_popup']) ? $options['enable_popup'] : 'off';

    // If popup is disabled, use default location
    if ($enable_popup === 'off') {
        $default_location = mulopimfwc_get_default_location_value($options);
        if (!empty($default_location)) {
            return $default_location;
        }
    }

    return '';
}

// Get location term ID from slug
// OPTIMIZED: Uses cached method to prevent repeated database queries
function mulopimfwc_get_location_term_id($location_slug)
{
    if (empty($location_slug)) {
        return false;
    }

    // OPTIMIZED: Use cached method instead of direct get_term_by
    // This prevents N+1 queries when called multiple times per request
    $location = get_term_by('slug', $location_slug, 'mulopimfwc_store_location');
    return $location ? $location->term_id : false;
}


/**
 * Normalize backorder values between plugin and WooCommerce formats.
 *
 * Plugin location values: off|notify|on
 * WooCommerce values:     no|notify|yes
 *
 * @param mixed  $value  Input value to normalize.
 * @param string $target Target format: woocommerce|location.
 * @return string
 */
function mulopimfwc_normalize_backorder_value($value, $target = 'woocommerce')
{
    $value = is_string($value) ? strtolower(trim($value)) : '';

    if ($target === 'location') {
        if ($value === 'on' || $value === 'yes') {
            return 'on';
        }
        if ($value === 'notify') {
            return 'notify';
        }
        if ($value === 'off' || $value === 'no') {
            return 'off';
        }

        return '';
    }

    if ($value === 'on' || $value === 'yes') {
        return 'yes';
    }
    if ($value === 'notify') {
        return 'notify';
    }
    if ($value === 'off' || $value === 'no') {
        return 'no';
    }

    return '';
}

/**
 * Check whether backorders are allowed for a raw backorder value.
 *
 * @param mixed $value Raw backorder value.
 * @return bool
 */
function mulopimfwc_is_backorder_allowed($value)
{
    $normalized = mulopimfwc_normalize_backorder_value($value, 'woocommerce');
    return in_array($normalized, ['yes', 'notify'], true);
}

if (!function_exists('mulopimfwc_is_product_assigned_to_location_for_info')) {
    /**
     * Check whether a product is currently assigned to a specific location slug.
     *
     * Used by frontend "location information" blocks so stale post meta does not
     * render after a location is unassigned from a product.
     *
     * @param int    $product_id
     * @param string $location_slug
     * @param string $enable_all_locations 'on'|'off'
     * @return bool
     */
    function mulopimfwc_is_product_assigned_to_location_for_info($product_id, $location_slug, $enable_all_locations = 'off')
    {
        if (empty($product_id) || empty($location_slug) || $location_slug === 'all-products') {
            return false;
        }

        $terms = wp_get_object_terms((int) $product_id, 'mulopimfwc_store_location', ['fields' => 'slugs']);
        if (is_wp_error($terms)) {
            return false;
        }

        $terms = array_map('rawurldecode', (array) $terms);

        // In "all locations" mode, products with no explicit terms use default Woo behavior.
        if ($enable_all_locations === 'on' && empty($terms)) {
            return false;
        }

        return in_array($location_slug, $terms, true);
    }
}

// Helper function to get cart item location for a specific product
if (!function_exists('mulopimfwc_get_item_assigned_location_slugs')) {
    /**
     * Get assigned location slugs for an item (variation first, then parent product).
     *
     * @param int $product_id
     * @param int $variation_id
     * @return array
     */
    function mulopimfwc_get_item_assigned_location_slugs($product_id, $variation_id = 0)
    {
        $ids_to_check = array_values(array_filter([absint($variation_id), absint($product_id)]));

        foreach ($ids_to_check as $id) {
            $terms = wp_get_object_terms($id, 'mulopimfwc_store_location', ['fields' => 'slugs']);
            if (is_wp_error($terms) || empty($terms)) {
                continue;
            }

            $slugs = array_values(array_unique(array_map('rawurldecode', (array) $terms)));
            if (!empty($slugs)) {
                return $slugs;
            }
        }

        return [];
    }
}

if (!function_exists('mulopimfwc_get_single_assigned_location_slug')) {
    /**
     * Get a single assigned location slug for an item.
     *
     * Returns a slug only when exactly one location is assigned.
     *
     * @param int $product_id
     * @param int $variation_id
     * @return string|null
     */
    function mulopimfwc_get_single_assigned_location_slug($product_id, $variation_id = 0)
    {
        $slugs = mulopimfwc_get_item_assigned_location_slugs($product_id, $variation_id);
        if (count($slugs) === 1 && !empty($slugs[0])) {
            return (string) $slugs[0];
        }

        return null;
    }
}

function mulopimfwc_get_cart_item_location($product_id, $variation_id = 0)
{
    $product_id = absint($product_id);
    $variation_id = absint($variation_id);
    $single_slug = mulopimfwc_get_single_assigned_location_slug($product_id, $variation_id);

    if (!function_exists('WC') || !WC()->cart) {
        return $single_slug;
    }

    foreach (WC()->cart->get_cart() as $cart_item) {
        $cart_product_id = isset($cart_item['product_id']) ? absint($cart_item['product_id']) : 0;
        $cart_variation_id = isset($cart_item['variation_id']) ? absint($cart_item['variation_id']) : 0;

        if (($variation_id && $variation_id === $cart_variation_id) ||
            (!$variation_id && $product_id === $cart_product_id)
        ) {
            $resolved_variation_id = $variation_id ? $variation_id : $cart_variation_id;
            $assigned_slugs = mulopimfwc_get_item_assigned_location_slugs($product_id, $resolved_variation_id);
            $single_slug = mulopimfwc_get_single_assigned_location_slug($product_id, $resolved_variation_id);

            // Check if location exists in cart item
            if (!isset($cart_item['mulopimfwc_location']) || $cart_item['mulopimfwc_location'] === '') {
                // Fallback for single-location products/variations when location
                // is not explicitly stored on the cart item.
                return $single_slug;
            }

            $cart_location = rawurldecode((string) $cart_item['mulopimfwc_location']);

            // Check if product/variation has the location in terms
            if (empty($assigned_slugs)) {
                return $single_slug;
            }

            // Return location only if it exists in both cart item AND product terms
            if (in_array($cart_location, $assigned_slugs, true)) {
                return $cart_location;
            }

            return $single_slug;
        }
    }

    return $single_slug;
}

if (!function_exists('mulopimfwc_is_cart_checkout_runtime_context')) {
    /**
     * Detect cart/checkout runtime, including Store API and checkout-related AJAX.
     *
     * @return bool
     */
    function mulopimfwc_is_cart_checkout_runtime_context()
    {
        if (is_cart() || is_checkout()) {
            return true;
        }

        if (function_exists('wc_is_store_api_request') && wc_is_store_api_request()) {
            return true;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            $request_uri = isset($_SERVER['REQUEST_URI'])
                ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']))
                : '';
            $rest_route = isset($_REQUEST['rest_route'])
                ? sanitize_text_field(wp_unslash($_REQUEST['rest_route']))
                : '';

            if (strpos($request_uri, '/wc/store/') !== false || strpos($rest_route, '/wc/store/') !== false) {
                return true;
            }
        }

        if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
            $wc_ajax_action = isset($_REQUEST['wc-ajax'])
                ? sanitize_text_field(wp_unslash($_REQUEST['wc-ajax']))
                : '';

            if (in_array($wc_ajax_action, ['update_order_review', 'checkout'], true)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('mulopimfwc_resolve_runtime_item_location')) {
    /**
     * Resolve location for runtime stock/price checks.
     *
     * Uses cart item location in cart/checkout runtime contexts and falls back to
     * the currently selected location elsewhere.
     *
     * @param int $product_id
     * @param int $variation_id
     * @return string
     */
    function mulopimfwc_resolve_runtime_item_location($product_id, $variation_id = 0)
    {
        if (mulopimfwc_is_cart_checkout_runtime_context()) {
            $cart_item_location = mulopimfwc_get_cart_item_location($product_id, $variation_id);
            if (!empty($cart_item_location)) {
                return $cart_item_location;
            }
        }

        return mulopimfwc_get_current_store_location();
    }
}

if (!function_exists('mulopimfwc_get_order_item_location_slug')) {
    /**
     * Resolve the store location slug for an order item, with safe fallbacks.
     *
     * Order item meta is preferred; order meta is the secondary source; current
     * location is the final fallback to avoid empty restores.
     *
     * @param WC_Order_Item|null $item
     * @param WC_Order|null $order
     * @return string
     */
    function mulopimfwc_get_order_item_location_slug($item, $order = null): string
    {
        $location_slug = '';

        if ($item && is_object($item) && is_callable([$item, 'get_meta'])) {
            $location_slug = (string) $item->get_meta('_mulopimfwc_location');
        }

        if (!$location_slug && $order && is_object($order) && is_callable([$order, 'get_meta'])) {
            $location_slug = (string) $order->get_meta('_store_location');
        }

        if (!$location_slug) {
            $location_slug = mulopimfwc_get_current_store_location();
        }

        return $location_slug;
    }
}

if (!is_admin()) {
    // Override regular price for simple products
    add_filter('woocommerce_product_get_regular_price', function ($price, $product) {
        global $mulopimfwc_options;
        if ($product->is_type('variation') || !isset($mulopimfwc_options['enable_location_price']) || (isset($mulopimfwc_options['enable_location_price']) && $mulopimfwc_options['enable_location_price'] !== 'on')) {
            return $price; // Handle variations separately
        }
        $enable_all_locations = mulopimfwc_is_all_locations_enabled($mulopimfwc_options) ? 'on' : 'off';
        $terms = array_map('rawurldecode', wp_get_object_terms($product->get_id(), 'mulopimfwc_store_location', ['fields' => 'slugs']));
        if ($enable_all_locations === 'on' && empty($terms)) {
            return $price; // Use default WooCommerce price
        }

        $location_slug = mulopimfwc_get_current_store_location();
        $location_id = mulopimfwc_get_location_term_id($location_slug);

        if (!$location_id || !isset($mulopimfwc_options['enable_location_price']) || (isset($mulopimfwc_options['enable_location_price']) && $mulopimfwc_options['enable_location_price'] !== 'on')) {
            return $price;
        }

        $location_price = get_post_meta($product->get_id(), '_location_regular_price_' . $location_id, true);
        if (mulopimfwc_has_price_value($location_price)) {
            return $location_price;
        }

        return mulopimfwc_convert_price_amount_for_location($price, $location_id);
    }, 10, 2);
    // Override sale price for simple products
    add_filter('woocommerce_product_get_sale_price', function ($price, $product) {
        global $mulopimfwc_options;
        if ($product->is_type('variation') || !isset($mulopimfwc_options['enable_location_price']) || (isset($mulopimfwc_options['enable_location_price']) && $mulopimfwc_options['enable_location_price'] !== 'on')) {
            return $price; // Handle variations separately
        }

        global $mulopimfwc_options;
        $enable_all_locations = mulopimfwc_is_all_locations_enabled($mulopimfwc_options) ? 'on' : 'off';
        $terms = array_map('rawurldecode', wp_get_object_terms($product->get_id(), 'mulopimfwc_store_location', ['fields' => 'slugs']));

        if ($enable_all_locations === 'on' && empty($terms)) {
            return $price; // Use default WooCommerce price
        }

        $location_slug = mulopimfwc_get_current_store_location();
        $location_id = mulopimfwc_get_location_term_id($location_slug);

        if (!$location_id) {
            return $price;
        }

        $location_price = get_post_meta($product->get_id(), '_location_sale_price_' . $location_id, true);
        if (mulopimfwc_has_price_value($location_price)) {
            return $location_price;
        }

        return mulopimfwc_convert_price_amount_for_location($price, $location_id);
    }, 10, 2);
}



if (!is_admin()) {
    // Override stock quantity for simple products
    add_filter('woocommerce_product_get_stock_quantity', function ($quantity, $product) {
        global $mulopimfwc_options;

        if ($product->is_type('variation') || !isset($mulopimfwc_options['enable_location_stock']) || (isset($mulopimfwc_options['enable_location_stock']) && $mulopimfwc_options['enable_location_stock'] !== 'on')) {
            return $quantity; // Handle variations separately
        }

        $enable_all_locations = mulopimfwc_is_all_locations_enabled($mulopimfwc_options) ? 'on' : 'off';

        $terms = array_map('rawurldecode', wp_get_object_terms($product->get_id(), 'mulopimfwc_store_location', ['fields' => 'slugs']));
        if ($enable_all_locations === 'on' && empty($terms)) {
            return $quantity; // Use default WooCommerce stock quantity
        }

        $location_slug = mulopimfwc_resolve_runtime_item_location($product->get_id());

        $location_id = mulopimfwc_get_location_term_id($location_slug);

        if (!$location_id) {
            return $quantity;
        }

        $location_stock = get_post_meta($product->get_id(), '_location_stock_' . $location_id, true);

        return $location_stock !== '' ? $location_stock : $quantity;
    }, 10, 2);
}

if (!is_admin()) {

    // Override backorder setting for simple products
    add_filter('woocommerce_product_get_backorders', function ($backorders, $product) {
        global $mulopimfwc_options;
        if ($product->is_type('variation') || !isset($mulopimfwc_options['enable_location_backorder']) || (isset($mulopimfwc_options['enable_location_backorder']) && $mulopimfwc_options['enable_location_backorder'] !== 'on')) {
            return $backorders; // Handle variations separately
        }

        $enable_all_locations = mulopimfwc_is_all_locations_enabled($mulopimfwc_options) ? 'on' : 'off';

        $terms = array_map('rawurldecode', wp_get_object_terms($product->get_id(), 'mulopimfwc_store_location', ['fields' => 'slugs']));
        if ($enable_all_locations === 'on' && empty($terms)) {
            return $backorders; // Use default WooCommerce backorder setting
        }

        $location_slug = mulopimfwc_resolve_runtime_item_location($product->get_id());
        $location_id = mulopimfwc_get_location_term_id($location_slug);

        if (!$location_id) {
            return $backorders;
        }

        $location_backorders = get_post_meta($product->get_id(), '_location_backorders_' . $location_id, true);
        $normalized_backorders = mulopimfwc_normalize_backorder_value($location_backorders, 'woocommerce');


        return $normalized_backorders !== '' ? $normalized_backorders : $backorders;
    }, 10, 2);
}
if (!is_admin()) {
    // Override product stock status based on location stock
    add_filter('woocommerce_product_get_stock_status', function ($status, $product) {
        global $mulopimfwc_options;
        if ($product->is_type('variation') || !isset($mulopimfwc_options['enable_location_stock']) || (isset($mulopimfwc_options['enable_location_stock']) && $mulopimfwc_options['enable_location_stock'] !== 'on')) {
            return $status; // Handle variations separately
        }

        $product_id = $product->get_id();
        $enable_all_locations = mulopimfwc_is_all_locations_enabled($mulopimfwc_options) ? 'on' : 'off';

        $location_slug = mulopimfwc_resolve_runtime_item_location($product_id);
        $location_id = mulopimfwc_get_location_term_id($location_slug);

        if (!$location_id) {
            return $status;
        }

        $location_stock = get_post_meta($product_id, '_location_stock_' . $location_id, true);

        if ($location_stock === '') {
            return $status;
        }
        $terms = array_map('rawurldecode', wp_get_object_terms($product->get_id(), 'mulopimfwc_store_location', ['fields' => 'slugs']));

        if ($enable_all_locations === 'on' && empty($terms)) {
            return $status; // Use default WooCommerce price
        }

        // if all products is selected
        if ($location_slug === 'all-products') {
            return $status; // Use default WooCommerce stock status
        }

        if ($enable_all_locations === 'on' && empty($terms)) {
            return $status; // Use default WooCommerce stock status
        }

        if (!in_array($location_slug, $terms)) {
            return 'outofstock'; // Product is not available in the current location
        }

        // Get backorder setting
        $location_backorders = get_post_meta($product_id, '_location_backorders_' . $location_id, true);
        $normalized_location_backorders = mulopimfwc_normalize_backorder_value($location_backorders, 'woocommerce');
        $effective_backorders = $normalized_location_backorders !== '' ? $normalized_location_backorders : $product->get_backorders();


        // Determine stock status based on quantity and backorder setting
        if ($location_stock <= 0 && !mulopimfwc_is_backorder_allowed($effective_backorders)) {
            return 'outofstock';
        } elseif ($location_stock <= 0 && mulopimfwc_is_backorder_allowed($effective_backorders)) {
            return 'onbackorder';
        } else {
            return 'instock';
        }
    }, 10, 2);
}


if (!is_admin()) {

    // Override variation stock
    add_filter('woocommerce_product_variation_get_stock_quantity', function ($quantity, $variation) {
        global $mulopimfwc_options;

        if (!isset($mulopimfwc_options['enable_location_stock']) || (isset($mulopimfwc_options['enable_location_stock']) && $mulopimfwc_options['enable_location_stock'] !== 'on')) {
            return $quantity;
        }

        $location_slug = mulopimfwc_resolve_runtime_item_location($variation->get_parent_id(), $variation->get_id());
        $location_id = mulopimfwc_get_location_term_id($location_slug);

        if (!$location_id) {
            return $quantity;
        }

        $location_stock = get_post_meta($variation->get_id(), '_location_stock_' . $location_id, true);

        return $location_stock !== '' ? $location_stock : $quantity;
    }, 10, 2);
}

if (!is_admin()) {
    // Override variation backorders
    add_filter('woocommerce_product_variation_get_backorders', function ($backorders, $variation) {
        global $mulopimfwc_options;

        if (!isset($mulopimfwc_options['enable_location_backorder']) || (isset($mulopimfwc_options['enable_location_backorder']) && $mulopimfwc_options['enable_location_backorder'] !== 'on')) {
            return $backorders;
        }

        $location_slug = mulopimfwc_resolve_runtime_item_location($variation->get_parent_id(), $variation->get_id());
        $location_id = mulopimfwc_get_location_term_id($location_slug);

        if (!$location_id) {
            return $backorders;
        }

        $location_backorders = get_post_meta($variation->get_id(), '_location_backorders_' . $location_id, true);
        $normalized_backorders = mulopimfwc_normalize_backorder_value($location_backorders, 'woocommerce');

        return $normalized_backorders !== '' ? $normalized_backorders : $backorders;
    }, 10, 2);
}

// Handle stock reduction when order is placed
add_action('woocommerce_reduce_order_stock', function ($order) {
    global $mulopimfwc_options;

    if (!isset($mulopimfwc_options['enable_location_stock']) || (isset($mulopimfwc_options['enable_location_stock']) && $mulopimfwc_options['enable_location_stock'] !== 'on')) {
        return;
    }

    if ($order instanceof WC_Order && $order->get_meta('_mulopimfwc_split_parent_stock_exempt') === 'yes') {
        return;
    }

    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        $variation_id = $item->get_variation_id();
        $quantity = (int) $item->get_quantity();

        $target_id = $variation_id ? $variation_id : $product_id;

        // Get location from order item meta (stored during checkout), with order fallback
        $location_slug = mulopimfwc_get_order_item_location_slug($item, $order);

        $location_id = mulopimfwc_get_location_term_id($location_slug);
        if (!$location_id) {
            continue;
        }

        $current_stock = get_post_meta($target_id, '_location_stock_' . $location_id, true);

        if ($current_stock !== '') {
            $old_stock_int = (int) $current_stock;
            $new_stock = max(0, $old_stock_int - (int) $quantity);
            update_post_meta($target_id, '_location_stock_' . $location_id, $new_stock);

            // Trigger low/out-of-stock alerts when crossing thresholds (order-time stock reduction)
            if (function_exists('mulopimfwc_send_location_stock_alert')) {
                $location_term = get_term($location_id, 'mulopimfwc_store_location');
                if ($location_term && !is_wp_error($location_term)) {
                    $low_threshold = function_exists('mulopimfwc_get_location_threshold')
                        ? mulopimfwc_get_location_threshold($location_id, 'low')
                        : 5;
                    $out_threshold = function_exists('mulopimfwc_get_location_threshold')
                        ? mulopimfwc_get_location_threshold($location_id, 'out')
                        : 0;

                    // Out of stock alert
                    if ($new_stock <= $out_threshold && $old_stock_int > $out_threshold) {
                        mulopimfwc_send_location_stock_alert($target_id, $location_term, $new_stock, $out_threshold, 'out');
                    }
                    // Low stock alert (only if not already out-of-stock)
                    elseif ($new_stock <= $low_threshold && $old_stock_int > $low_threshold) {
                        mulopimfwc_send_location_stock_alert($target_id, $location_term, $new_stock, $low_threshold, 'low');
                    }
                }
            }
        }
    }
});

// Handle location-stock restoration when WooCommerce restores stock per order item.
add_action('woocommerce_restore_order_item_stock', function ($item, $new_stock, $old_stock, $order) {
    global $mulopimfwc_options;

    if (!isset($mulopimfwc_options['enable_location_stock']) || (isset($mulopimfwc_options['enable_location_stock']) && $mulopimfwc_options['enable_location_stock'] !== 'on')) {
        return;
    }

    if ($order instanceof WC_Order && $order->get_meta('_mulopimfwc_split_parent_stock_exempt') === 'yes') {
        return;
    }

    if (!$item instanceof WC_Order_Item_Product) {
        return;
    }

    $restore_qty = function_exists('wc_stock_amount')
        ? wc_stock_amount((float) $new_stock - (float) $old_stock)
        : ((float) $new_stock - (float) $old_stock);
    $restore_qty = absint($restore_qty);

    if ($restore_qty <= 0) {
        return;
    }

    $product_id = $item->get_product_id();
    $variation_id = $item->get_variation_id();
    $target_id = $variation_id ? $variation_id : $product_id;

    if (!$target_id) {
        return;
    }

    // Get location from order item meta (stored during checkout), with order fallback.
    $location_slug = mulopimfwc_get_order_item_location_slug($item, $order);
    $location_id = mulopimfwc_get_location_term_id($location_slug);
    if (!$location_id) {
        return;
    }

    $current_stock = get_post_meta($target_id, '_location_stock_' . $location_id, true);
    if ($current_stock === '') {
        return;
    }

    $updated_stock = (int) $current_stock + $restore_qty;
    update_post_meta($target_id, '_location_stock_' . $location_id, $updated_stock);
}, 10, 4);

// Fallback restore path for items that do not use WooCommerce core stock management.
add_action('woocommerce_restore_order_stock', function ($order) {
    global $mulopimfwc_options;

    if (!isset($mulopimfwc_options['enable_location_stock']) || (isset($mulopimfwc_options['enable_location_stock']) && $mulopimfwc_options['enable_location_stock'] !== 'on')) {
        return;
    }

    if ($order instanceof WC_Order && $order->get_meta('_mulopimfwc_split_parent_stock_exempt') === 'yes') {
        return;
    }

    foreach ($order->get_items() as $item) {
        if (!$item instanceof WC_Order_Item_Product) {
            continue;
        }

        $product = $item->get_product();
        if ($product && $product->managing_stock()) {
            continue;
        }

        $qty = (int) $item->get_quantity();
        if ($qty <= 0) {
            continue;
        }

        $product_id = $item->get_product_id();
        $variation_id = $item->get_variation_id();
        $target_id = $variation_id ? $variation_id : $product_id;

        if (!$target_id) {
            continue;
        }

        $location_slug = mulopimfwc_get_order_item_location_slug($item, $order);
        $location_id = mulopimfwc_get_location_term_id($location_slug);
        if (!$location_id) {
            continue;
        }

        $current_stock = get_post_meta($target_id, '_location_stock_' . $location_id, true);
        if ($current_stock === '') {
            continue;
        }

        $updated_stock = (int) $current_stock + $qty;
        update_post_meta($target_id, '_location_stock_' . $location_id, $updated_stock);
    }
});

// Handle stock restoration when items are refunded (Restock refunded items)
add_action('woocommerce_create_refund', function ($refund, $args) {
    global $mulopimfwc_options;

    if (!isset($mulopimfwc_options['enable_location_stock']) || (isset($mulopimfwc_options['enable_location_stock']) && $mulopimfwc_options['enable_location_stock'] !== 'on')) {
        return;
    }

    if (!empty($args['order_id'])) {
        $order = wc_get_order((int) $args['order_id']);
        if ($order instanceof WC_Order && $order->get_meta('_mulopimfwc_split_parent_stock_exempt') === 'yes') {
            return;
        }
    }

    if (empty($args['restock_items']) || empty($args['line_items']) || empty($args['order_id'])) {
        return;
    }

    $order = wc_get_order($args['order_id']);
    if (!$order) {
        return;
    }

    foreach ($args['line_items'] as $item_id => $line_item) {
        if (empty($line_item['qty'])) {
            continue;
        }

        $qty = function_exists('wc_stock_amount')
            ? wc_stock_amount($line_item['qty'])
            : (int) $line_item['qty'];
        $qty = absint($qty);

        if ($qty <= 0) {
            continue;
        }

        $item = $order->get_item($item_id);
        if (!$item || !$item->is_type('line_item')) {
            continue;
        }

        $reduced_qty = absint($item->get_meta('_reduced_stock', true));
        if ($reduced_qty > 0) {
            $qty = min($qty, $reduced_qty);
            if ($qty <= 0) {
                continue;
            }
        }

        $product_id = $item->get_product_id();
        $variation_id = $item->get_variation_id();
        $target_id = $variation_id ? $variation_id : $product_id;

        if (!$target_id) {
            continue;
        }

        $location_slug = mulopimfwc_get_order_item_location_slug($item, $order);

        $location_id = mulopimfwc_get_location_term_id($location_slug);
        if (!$location_id) {
            continue;
        }

        $current_stock = get_post_meta($target_id, '_location_stock_' . $location_id, true);
        if ($current_stock === '') {
            continue;
        }

        $new_stock = (int) $current_stock + $qty;
        update_post_meta($target_id, '_location_stock_' . $location_id, $new_stock);
    }
}, 10, 2);

// Validate cart items against location stock
add_filter('woocommerce_add_to_cart_validation', function ($passed, $product_id, $quantity, $variation_id = 0, $variations = array()) {
    global $mulopimfwc_options;

    // Check if mixed location cart is enabled
    $allow_mixed = mulopimfwc_is_mixed_location_cart_enabled($mulopimfwc_options) ? 'on' : 'off';

    // Get the location for this specific product being added
    $location_slug = mulopimfwc_get_current_store_location();

    // If mixed cart is enabled, we need to check if this product is already in cart with a different location
    if ($allow_mixed === 'on') {
        foreach (WC()->cart->get_cart() as $cart_item) {
            if (($variation_id && $variation_id == $cart_item['variation_id']) ||
                (!$variation_id && $product_id == $cart_item['product_id'])
            ) {
                // Product already in cart, use its location for validation
                if (isset($cart_item['mulopimfwc_location'])) {
                    $location_slug = $cart_item['mulopimfwc_location'];
                }
                break;
            }
        }
    }

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
            // Only count items from the same location
            if ($allow_mixed === 'on' && isset($cart_item['mulopimfwc_location'])) {
                if ($cart_item['mulopimfwc_location'] === $location_slug) {
                    $qty_in_cart += $cart_item['quantity'];
                }
            } else {
                $qty_in_cart += $cart_item['quantity'];
            }
        }
    }

    $total_required = $qty_in_cart + $quantity;


    $normalized_location_backorders = mulopimfwc_normalize_backorder_value($location_backorders, 'woocommerce');
    $effective_backorders = $normalized_location_backorders !== '' ? $normalized_location_backorders : ($product ? $product->get_backorders() : '');


    // If backorders are not allowed and we don't have enough stock
    if (!mulopimfwc_is_backorder_allowed($effective_backorders) && $location_stock < $total_required) {
        $location_term = get_term_by('slug', $location_slug, 'mulopimfwc_store_location');
        $location_name = $location_term ? $location_term->name : $location_slug;

        wc_add_notice(
            sprintf(
                esc_html('Sorry, "%s" has only %d left in stock at %s location. Please adjust your quantity.', 'multi-location-product-and-inventory-management'),
                $product->get_name(),
                $location_stock,
                $location_name
            ),
            'error'
        );
        return false;
    }

    return $passed;
}, 10, 5);
if (!is_admin()) {
    // Override the final price for simple products
    add_filter('woocommerce_product_get_price', function ($price, $product) {
        global $mulopimfwc_options;
        if ($product->is_type('variation') || !isset($mulopimfwc_options['enable_location_price']) || (isset($mulopimfwc_options['enable_location_price']) && $mulopimfwc_options['enable_location_price'] !== 'on')) {
            return $price; // Handle variations separately
        }

        // Get raw price from database
        $raw_sale_price = get_post_meta($product->get_id(), '_sale_price', true);
        $raw_regular_price = get_post_meta($product->get_id(), '_regular_price', true);
        $raw_price = $raw_sale_price ? $raw_sale_price : $raw_regular_price;

        // If incoming price differs from raw price, another plugin modified it
        // In that case, respect the other plugin's price
        if ($price != $raw_price && !empty($price)) {
            return $price; // Another plugin has already modified the price
        }

        $location_slug = mulopimfwc_resolve_runtime_item_location($product->get_id());

        $location_id = mulopimfwc_get_location_term_id($location_slug);

        if (!$location_id) {
            return $price;
        }

        // First check if there's a location-specific sale price
        $location_sale_price = get_post_meta($product->get_id(), '_location_sale_price_' . $location_id, true);

        // If there's a valid sale price (including zero), use it
        if (mulopimfwc_has_price_value($location_sale_price)) {
            return $location_sale_price;
        }

        // Otherwise, check for location-specific regular price
        $location_regular_price = get_post_meta($product->get_id(), '_location_regular_price_' . $location_id, true);

        // If there's a location-specific regular price (including zero), use it
        if (mulopimfwc_has_price_value($location_regular_price)) {
            return $location_regular_price;
        }

        // If no location-specific prices, convert fallback/base price when required.
        return mulopimfwc_convert_price_amount_for_location($price, $location_id);
    }, 10, 2);

    // Override the final price for variation products
    add_filter('woocommerce_product_variation_get_price', function ($price, $variation) {
        global $mulopimfwc_options;

        if (!isset($mulopimfwc_options['enable_location_price']) || (isset($mulopimfwc_options['enable_location_price']) && $mulopimfwc_options['enable_location_price'] !== 'on')) {
            return $price;
        }

        // Get raw price from database
        $raw_sale_price = get_post_meta($variation->get_id(), '_sale_price', true);
        $raw_regular_price = get_post_meta($variation->get_id(), '_regular_price', true);
        $raw_price = $raw_sale_price ? $raw_sale_price : $raw_regular_price;

        // If incoming price differs from raw price, another plugin modified it
        // In that case, respect the other plugin's price
        if ($price != $raw_price && !empty($price)) {
            return $price; // Another plugin has already modified the price
        }

        $location_slug = mulopimfwc_resolve_runtime_item_location($variation->get_parent_id(), $variation->get_id());

        $location_id = mulopimfwc_get_location_term_id($location_slug);

        if (!$location_id) {
            return $price;
        }

        // First check if there's a location-specific sale price
        $location_sale_price = get_post_meta($variation->get_id(), '_location_sale_price_' . $location_id, true);

        // If there's a valid sale price (including zero), use it
        if (mulopimfwc_has_price_value($location_sale_price)) {
            return $location_sale_price;
        }

        // Otherwise, check for location-specific regular price
        $location_regular_price = get_post_meta($variation->get_id(), '_location_regular_price_' . $location_id, true);

        // If there's a location-specific regular price (including zero), use it
        if (mulopimfwc_has_price_value($location_regular_price)) {
            return $location_regular_price;
        }

        // If no location-specific prices, convert fallback/base price when required.
        return mulopimfwc_convert_price_amount_for_location($price, $location_id);
    }, 10, 2);

    // We also need to ensure variation price sync works correctly
    add_filter('woocommerce_variation_prices', function ($prices, $product, $for_display) {
        global $mulopimfwc_options;

        if (!isset($mulopimfwc_options['enable_location_price']) || (isset($mulopimfwc_options['enable_location_price']) && $mulopimfwc_options['enable_location_price'] !== 'on')) {
            return $prices;
        }


        $location_slug = mulopimfwc_resolve_runtime_item_location($product->get_id());

        $location_id = mulopimfwc_get_location_term_id($location_slug);

        if (!$location_id) {
            return $prices;
        }

        if (!empty($prices['regular_price']) && !empty($prices['sale_price']) && !empty($prices['price'])) {
            $variation_ids = array_keys($prices['regular_price']);

            foreach ($variation_ids as $variation_id) {
                // Update regular price
                $location_regular_price = get_post_meta($variation_id, '_location_regular_price_' . $location_id, true);
                if (mulopimfwc_has_price_value($location_regular_price)) {
                    $prices['regular_price'][$variation_id] = $location_regular_price;
                }

                // Update sale price
                $location_sale_price = get_post_meta($variation_id, '_location_sale_price_' . $location_id, true);
                if (mulopimfwc_has_price_value($location_sale_price)) {
                    if (mulopimfwc_has_price_value($location_regular_price)) {
                        $prices['regular_price'][$variation_id] = $location_regular_price;
                    } else {
                        $prices['regular_price'][$variation_id] = mulopimfwc_convert_price_amount_for_location(
                            $prices['regular_price'][$variation_id] ?? '',
                            $location_id
                        );
                    }
                    $prices['sale_price'][$variation_id] = $location_sale_price;
                    // Also update the final price when sale price exists
                    $prices['price'][$variation_id] = $location_sale_price;
                } elseif (mulopimfwc_has_price_value($location_regular_price)) {
                    // If no sale price but has location regular price, update the final price
                    $prices['price'][$variation_id] = $location_regular_price;
                    $prices['regular_price'][$variation_id] = $location_regular_price;
                } else {
                    $prices['regular_price'][$variation_id] = mulopimfwc_convert_price_amount_for_location(
                        $prices['regular_price'][$variation_id] ?? '',
                        $location_id
                    );
                    $prices['sale_price'][$variation_id] = mulopimfwc_convert_price_amount_for_location(
                        $prices['sale_price'][$variation_id] ?? '',
                        $location_id
                    );
                    $prices['price'][$variation_id] = mulopimfwc_convert_price_amount_for_location(
                        $prices['price'][$variation_id] ?? '',
                        $location_id
                    );
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
    global $mulopimfwc_options;
    $enable_all_locations = mulopimfwc_is_all_locations_enabled($mulopimfwc_options) ? 'on' : 'off';
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
    $terms = array_map('rawurldecode', wp_get_object_terms($product->get_id(), 'mulopimfwc_store_location', ['fields' => 'slugs']));

    if ($enable_all_locations === 'on' && empty($terms)) {
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
// $terms = array_map('rawurldecode',wp_get_object_terms($product_id, 'mulopimfwc_store_location', ['fields' => 'slugs']));


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
// $terms = array_map('rawurldecode',wp_get_object_terms($product->get_id(), 'mulopimfwc_store_location', ['fields' => 'slugs']));


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
        global $mulopimfwc_options;
        $enable_all_locations = mulopimfwc_is_all_locations_enabled($mulopimfwc_options) ? 'on' : 'off';

        if ($product->is_type('variable')) {
            $location_slug = mulopimfwc_get_current_store_location();

            // If no location is selected or "all products" is selected, show normal button
            if (! $location_slug || 'all-products' === $location_slug) {
                return;
            }

            // Check if the product belongs to the current location
            $terms = array_map('rawurldecode', wp_get_object_terms($product->get_id(), 'mulopimfwc_store_location', ['fields' => 'slugs']));

            if ($enable_all_locations === 'on' && empty($terms)) {
                return; // Show default WooCommerce notice
            }

            if (is_wp_error($terms) || ! in_array($location_slug, $terms, true)) {
                // Register a dummy stylesheet to attach inline styles
                wp_register_style('mulopimfwc-custom-woocommerce-style', false, array(), '1.1.3.85');
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
            $terms = array_map('rawurldecode', wp_get_object_terms($product->get_id(), 'mulopimfwc_store_location', ['fields' => 'slugs']));


            if ($enable_all_locations === 'on' && empty($terms)) {
                return; // Show default WooCommerce notice
            }
            if (is_wp_error($terms) || ! in_array($location_slug, $terms, true)) {
                // Register a dummy stylesheet to attach inline styles
                wp_register_style('mulopimfwc-custom-woocommerce-style', false, array(), '1.1.1.121');
                wp_enqueue_style('mulopimfwc-custom-woocommerce-style');
                wp_add_inline_style('mulopimfwc-custom-woocommerce-style', 'form.cart { display: none; }');
            }
        }
    }
});


// add stock & price details in product pages
$options = is_array($mulopimfwc_options ?? null)
    ? $mulopimfwc_options
    : get_option('mulopimfwc_display_options', ['enable_location_by_user_role' => []]);
$selected_roles = isset($options['enable_location_by_user_role']) ? $options['enable_location_by_user_role'] : [];
$location_info_enabled = mulopimfwc_is_location_information_enabled($options);
$current_user = wp_get_current_user();
$user_roles = $current_user->roles;

// Check if the current user role has permission
if (array_intersect($user_roles, $selected_roles) && $location_info_enabled) {
    // Add location-specific stock and price display on product pages
    add_action('woocommerce_single_product_summary', 'mulopimfwc_display_location_specific_stock_info', 25);
    add_action('woocommerce_shop_loop_item_title', 'mulopimfwc_display_location_specific_stock_info_loop', 15);
}
/**
 * Display location-specific stock and price information on single product pages
 */
function mulopimfwc_display_location_specific_stock_info()
{
    global $product;
    global $mulopimfwc_options;
    $enable_all_locations = mulopimfwc_is_all_locations_enabled($mulopimfwc_options) ? 'on' : 'off';
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

    // Verify current location is still assigned to this product.
    if (!mulopimfwc_is_product_assigned_to_location_for_info($product->get_id(), $location_slug, $enable_all_locations)) {
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
    $default_regular_price = get_post_meta($target_id, '_regular_price', true);
    $default_sale_price = get_post_meta($target_id, '_sale_price', true);

    $display_regular_price = '';
    $display_sale_price = '';
    if (mulopimfwc_has_price_value($location_sale_price)) {
        $display_regular_price = mulopimfwc_has_price_value($location_regular_price)
            ? $location_regular_price
            : mulopimfwc_convert_price_amount_for_location($default_regular_price, (int) $location->term_id);
        $display_sale_price = $location_sale_price;
    } elseif (mulopimfwc_has_price_value($location_regular_price)) {
        $display_regular_price = $location_regular_price;
    } else {
        $display_regular_price = mulopimfwc_convert_price_amount_for_location($default_regular_price, (int) $location->term_id);
        $display_sale_price = mulopimfwc_convert_price_amount_for_location($default_sale_price, (int) $location->term_id);
    }

    // Get backorder setting
    $location_backorders = get_post_meta($target_id, '_location_backorders_' . $location->term_id, true);

    echo '<div class="location-specific-info">';
    echo '<h4>' . sprintf(mulopimfwc_get_text_value('text_variation_info_heading'), esc_attr($location->name)) . '</h4>';

    // Display stock status
    if ($location_stock !== '') {
        $stock_data = function_exists('mulopimfwc_build_stock_display_label')
            ? mulopimfwc_build_stock_display_label([
                'stock_qty' => $location_stock,
                'backorders' => !mulopimfwc_is_backorder_allowed($location_backorders) ? 'outofstock' : $location_backorders,
                'location_id' => (int) $location->term_id,
                'count_format' => 'phrase',
                'backorder_label' => 'available',
            ])
            : ['show' => true, 'label' => ($location_stock > 0 ? sprintf(esc_html(mulopimfwc_get_text_value('text_variation_in_stock'), mulopimfwc_get_text_value('text_variation_in_stock'), $location_stock, 'multi-location-product-and-inventory-management'), esc_attr($location_stock)) : __('Out of stock', 'multi-location-product-and-inventory-management')), 'status' => ($location_stock > 0 ? 'instock' : 'outofstock'), 'level' => '', 'class' => ''];

        if (!empty($stock_data['show']) && $stock_data['label'] !== '') {
            $status_class = ($stock_data['status'] === 'outofstock') ? 'out-of-stock' : (($stock_data['status'] === 'onbackorder') ? 'on-backorder' : 'in-stock');
            $level_class = !empty($stock_data['level']) ? ' stock-level-' . $stock_data['level'] : '';
            echo '<p class="location-stock">';
            echo '<strong>' . mulopimfwc_get_text_value('text_variation_stock_label') . '</strong> ';
            echo '<span class="' . esc_attr($status_class . $level_class) . '">' . esc_html($stock_data['label']) . '</span>';
            echo '</p>';
        }
    }

    // Display location price (location-specific first, converted fallback when needed).
    $normalized_regular = mulopimfwc_normalize_price_amount($display_regular_price);
    $normalized_sale = mulopimfwc_normalize_price_amount($display_sale_price);
    if ($normalized_regular !== null || $normalized_sale !== null) {
        echo '<p class="location-price">';
        echo '<strong>' . mulopimfwc_get_text_value('text_variation_price_label') . '</strong> ';

        if ($normalized_sale !== null && $normalized_regular !== null && abs((float) $normalized_regular - (float) $normalized_sale) > 0.0001) {
            echo '<del>' . wp_kses_post(wc_price($display_regular_price)) . '</del> <ins>' . wp_kses_post(wc_price($display_sale_price)) . '</ins>';
        } elseif ($normalized_sale !== null) {
            echo wp_kses_post(wc_price($display_sale_price));
        } else {
            echo wp_kses_post(wc_price($display_regular_price));
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
    global $mulopimfwc_options;
    $enable_all_locations = mulopimfwc_is_all_locations_enabled($mulopimfwc_options) ? 'on' : 'off';
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

    // Verify current location is still assigned to this product.
    if (!mulopimfwc_is_product_assigned_to_location_for_info($product->get_id(), $location_slug, $enable_all_locations)) {
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
        $stock_data = function_exists('mulopimfwc_build_stock_display_label')
            ? mulopimfwc_build_stock_display_label([
                'stock_qty' => $location_stock,
                'backorders' => !mulopimfwc_is_backorder_allowed($location_backorders) ? 'outofstock' : $location_backorders,
                'location_id' => (int) $location->term_id,
                'count_format' => 'short',
                'backorder_label' => 'backorder',
            ])
            : ['show' => true, 'label' => ($location_stock > 0 ? sprintf(esc_html('%d in stock', 'multi-location-product-and-inventory-management'), esc_attr($location_stock)) : __('Out of stock', 'multi-location-product-and-inventory-management')), 'status' => ($location_stock > 0 ? 'instock' : 'outofstock'), 'level' => '', 'class' => ''];

        if (!empty($stock_data['show']) && $stock_data['label'] !== '') {
            $status_class = ($stock_data['status'] === 'outofstock') ? 'out-of-stock' : (($stock_data['status'] === 'onbackorder') ? 'on-backorder' : 'in-stock');
            $level_class = !empty($stock_data['level']) ? ' stock-level-' . $stock_data['level'] : '';
            echo '<span class="location-stock-loop">';
            echo '<span class="' . esc_attr($status_class . $level_class) . '">' . esc_html($stock_data['label']) . '</span>';
            echo '</span>';
        }
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
    global $mulopimfwc_options;
    $enable_all_locations = mulopimfwc_is_all_locations_enabled($mulopimfwc_options) ? 'on' : 'off';

    // Verify current location is still assigned to this product.
    if (!mulopimfwc_is_product_assigned_to_location_for_info($product->get_id(), $location_slug, $enable_all_locations)) {
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
    $default_regular_price = get_post_meta($variation_id, '_regular_price', true);
    $default_sale_price = get_post_meta($variation_id, '_sale_price', true);

    $display_regular_price = '';
    $display_sale_price = '';
    if (mulopimfwc_has_price_value($location_sale_price)) {
        $display_regular_price = mulopimfwc_has_price_value($location_regular_price)
            ? $location_regular_price
            : mulopimfwc_convert_price_amount_for_location($default_regular_price, (int) $location->term_id);
        $display_sale_price = $location_sale_price;
    } elseif (mulopimfwc_has_price_value($location_regular_price)) {
        $display_regular_price = $location_regular_price;
    } else {
        $display_regular_price = mulopimfwc_convert_price_amount_for_location($default_regular_price, (int) $location->term_id);
        $display_sale_price = mulopimfwc_convert_price_amount_for_location($default_sale_price, (int) $location->term_id);
    }

    // Get backorder setting
    $location_backorders = get_post_meta($variation_id, '_location_backorders_' . $location->term_id, true);

    if ($location_backorders === '') {
        $location_backorders = $variation->get_backorders();
    }

    $stock_display = function_exists('mulopimfwc_build_stock_display_label')
        ? mulopimfwc_build_stock_display_label([
            'stock_qty' => $location_stock,
            'backorders' => $location_backorders,
            'location_id' => (int) $location->term_id,
            'count_format' => 'short',
            'backorder_label' => 'available',
        ])
        : ['show' => true, 'label' => ($location_stock > 0 ? sprintf(esc_html('%d in stock', 'multi-location-product-and-inventory-management'), esc_attr($location_stock)) : __('Out of stock', 'multi-location-product-and-inventory-management')), 'status' => ($location_stock > 0 ? 'instock' : 'outofstock'), 'level' => '', 'class' => ''];

    // Add location data to variation data
    $normalized_display_regular = mulopimfwc_normalize_price_amount($display_regular_price);
    $normalized_display_sale = mulopimfwc_normalize_price_amount($display_sale_price);
    $variation_data['location_data'] = [
        'location_name' => $location->name,
        'location_stock' => $location_stock,
        'location_regular_price' => ($normalized_display_regular !== null)
            ? wc_price($display_regular_price)
            : '',
        'location_sale_price' => ($normalized_display_sale !== null)
            ? wc_price($display_sale_price)
            : '',
        'location_backorders' => mulopimfwc_normalize_backorder_value($location_backorders, 'location'),
        'stock_display' => [
            'show' => !empty($stock_display['show']),
            'label' => $stock_display['label'],
            'status' => $stock_display['status'],
            'level' => $stock_display['level'],
            'class' => $stock_display['class'],
        ],
    ];

    return $variation_data;
}



if (array_intersect($user_roles, $selected_roles) && $location_info_enabled) {

    /**
     * Add stock status to product category/archive pages
     */
    add_action('woocommerce_after_shop_loop_item', 'mulopimfwc_display_location_stock_status_in_loop', 9);
}
function mulopimfwc_display_location_stock_status_in_loop()
{
    global $product;
    global $mulopimfwc_options;
    $enable_all_locations = mulopimfwc_is_all_locations_enabled($mulopimfwc_options) ? 'on' : 'off';
    // Get current location
    $location_slug = mulopimfwc_get_current_store_location();
    if (empty($location_slug) || $location_slug === 'all-products') {
        return; // No specific location selected
    }

    // Verify current location is still assigned to this product.
    if (!mulopimfwc_is_product_assigned_to_location_for_info($product->get_id(), $location_slug, $enable_all_locations)) {
        return;
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
    $default_regular_price = get_post_meta($product_id, '_regular_price', true);
    $default_sale_price = get_post_meta($product_id, '_sale_price', true);
    $location_backorders = get_post_meta($product_id, '_location_backorders_' . $location->term_id, true);

    $display_regular_price = '';
    $display_sale_price = '';
    if (mulopimfwc_has_price_value($location_sale_price)) {
        $display_regular_price = mulopimfwc_has_price_value($location_regular_price)
            ? $location_regular_price
            : mulopimfwc_convert_price_amount_for_location($default_regular_price, (int) $location->term_id);
        $display_sale_price = $location_sale_price;
    } elseif (mulopimfwc_has_price_value($location_regular_price)) {
        $display_regular_price = $location_regular_price;
    } else {
        $display_regular_price = mulopimfwc_convert_price_amount_for_location($default_regular_price, (int) $location->term_id);
        $display_sale_price = mulopimfwc_convert_price_amount_for_location($default_sale_price, (int) $location->term_id);
    }

    echo '<div class="location-loop-details">';

    // Display stock status badge
    if ($location_stock !== '') {
        $stock_data = function_exists('mulopimfwc_build_stock_display_label')
            ? mulopimfwc_build_stock_display_label([
                'stock_qty' => $location_stock,
                'backorders' => !mulopimfwc_is_backorder_allowed($location_backorders) ? 'outofstock' : $location_backorders,
                'location_id' => (int) $location->term_id,
                'count_format' => 'short',
                'backorder_label' => 'backorder',
            ])
            : ['show' => true, 'label' => (intval($location_stock) > 0 ? __('In Stock', 'multi-location-product-and-inventory-management') : __('Out of Stock', 'multi-location-product-and-inventory-management')), 'status' => (intval($location_stock) > 0 ? 'instock' : 'outofstock'), 'level' => '', 'class' => ''];

        if (!empty($stock_data['show']) && $stock_data['label'] !== '') {
            $status_class = ($stock_data['status'] === 'outofstock') ? 'out-of-stock' : (($stock_data['status'] === 'onbackorder') ? 'on-backorder' : 'in-stock');
            $level_class = !empty($stock_data['level']) ? ' stock-level-' . $stock_data['level'] : '';
            echo '<div class="location-stock-badge">';
            echo '<span class="stock-badge ' . esc_attr($status_class . $level_class) . '">' . esc_html($stock_data['label']) . '</span>';
            echo '</div>';
        }
    }

    // Display location price (location-specific first, converted fallback when needed).
    $normalized_regular = mulopimfwc_normalize_price_amount($display_regular_price);
    $normalized_sale = mulopimfwc_normalize_price_amount($display_sale_price);
    if ($normalized_regular !== null || $normalized_sale !== null) {
        echo '<div class="location-price-loop">';
        echo '<small>' . sprintf(esc_html('%s price:', 'multi-location-product-and-inventory-management'), esc_attr($location->name)) . '</small> ';

        if ($normalized_sale !== null && $normalized_regular !== null && abs((float) $normalized_regular - (float) $normalized_sale) > 0.0001) {
            echo '<del>' . wp_kses_post(wc_price($display_regular_price)) . '</del> <ins>' . wp_kses_post(wc_price($display_sale_price)) . '</ins>';
        } elseif ($normalized_sale !== null) {
            echo wp_kses_post(wc_price($display_sale_price));
        } else {
            echo wp_kses_post(wc_price($display_regular_price));
        }

        echo '</div>';
    }

    echo '</div>';
}
if (!function_exists('mulopimfwc_get_price_format_for_currency_position')) {
    /**
     * Map WooCommerce currency position into a price format string.
     *
     * @param string $position Currency position key.
     * @return string
     */
    function mulopimfwc_get_price_format_for_currency_position($position)
    {
        switch ((string) $position) {
            case 'right':
                return '%2$s%1$s';
            case 'left_space':
                return '%1$s&nbsp;%2$s';
            case 'right_space':
                return '%2$s&nbsp;%1$s';
            case 'left':
            default:
                return '%1$s%2$s';
        }
    }
}

if (!function_exists('mulopimfwc_get_currency_settings_for_location')) {
    /**
     * Resolve currency and position for a location term (or Woo defaults).
     *
     * @param int|null $location_term_id Location term ID. Null means default store settings.
     * @return array{currency: string, position: string}
     */
    function mulopimfwc_get_currency_settings_for_location($location_term_id = null)
    {
        static $defaults = null;
        static $settings_cache = [];

        if ($defaults === null) {
            $default_currency = strtoupper((string) get_option('woocommerce_currency', 'USD'));
            if ($default_currency === '') {
                $default_currency = 'USD';
            }

            $default_position = (string) get_option('woocommerce_currency_pos', 'left');
            if (!in_array($default_position, ['left', 'right', 'left_space', 'right_space'], true)) {
                $default_position = 'left';
            }

            $defaults = [
                'currency' => $default_currency,
                'position' => $default_position,
            ];
        }

        $term_id = (int) $location_term_id;
        $cache_key = $term_id > 0 ? 'term_' . $term_id : 'default';

        if (isset($settings_cache[$cache_key])) {
            return $settings_cache[$cache_key];
        }

        $settings = $defaults;

        if ($term_id > 0) {
            $configured_currency = strtoupper(trim((string) get_term_meta($term_id, 'location_currency', true)));
            if ($configured_currency !== '' && function_exists('get_woocommerce_currencies')) {
                $available_currencies = (array) get_woocommerce_currencies();
                if (isset($available_currencies[$configured_currency])) {
                    $settings['currency'] = $configured_currency;
                }
            }

            $configured_position = sanitize_key((string) get_term_meta($term_id, 'location_currency_position', true));
            if (in_array($configured_position, ['left', 'right', 'left_space', 'right_space'], true)) {
                $settings['position'] = $configured_position;
            }
        }

        $settings_cache[$cache_key] = $settings;
        return $settings;
    }
}

if (!function_exists('mulopimfwc_normalize_price_amount')) {
    /**
     * Normalize mixed price input to float when possible.
     *
     * @param mixed $amount
     * @return float|null
     */
    function mulopimfwc_normalize_price_amount($amount)
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        if (is_numeric($amount)) {
            return (float) $amount;
        }

        if (function_exists('wc_format_decimal')) {
            $formatted = wc_format_decimal($amount, 6, false);
            if ($formatted !== '' && is_numeric($formatted)) {
                return (float) $formatted;
            }
        }

        return null;
    }
}

if (!function_exists('mulopimfwc_has_price_value')) {
    /**
     * Check whether a price value is explicitly set (including numeric zero).
     *
     * @param mixed $amount
     * @return bool
     */
    function mulopimfwc_has_price_value($amount)
    {
        return mulopimfwc_normalize_price_amount($amount) !== null;
    }
}

if (!function_exists('mulopimfwc_get_store_base_currency_code_raw')) {
    /**
     * Read the configured WooCommerce base currency directly from DB.
     *
     * This bypasses runtime currency filters to keep conversion source stable.
     *
     * @return string
     */
    function mulopimfwc_get_store_base_currency_code_raw()
    {
        static $base_currency = null;

        if ($base_currency !== null) {
            return $base_currency;
        }

        $base_currency = 'USD';

        global $wpdb;
        if (isset($wpdb) && !empty($wpdb->options)) {
            $raw = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
                    'woocommerce_currency'
                )
            );

            if (is_string($raw) && $raw !== '') {
                $candidate = strtoupper(trim($raw));
                if ($candidate !== '') {
                    $base_currency = $candidate;
                    return $base_currency;
                }
            }
        }

        $fallback = strtoupper((string) get_option('woocommerce_currency', 'USD'));
        if ($fallback !== '') {
            $base_currency = $fallback;
        }

        return $base_currency;
    }
}

if (!function_exists('mulopimfwc_get_location_currency_conversion_context')) {
    /**
     * Build conversion context (base/target/rate) for a location.
     *
     * @param int $location_term_id
     * @return array{should_convert: bool, base_currency: string, target_currency: string, rate: float}
     */
    function mulopimfwc_get_location_currency_conversion_context($location_term_id)
    {
        static $cache = [];

        $term_id = absint($location_term_id);
        if ($term_id <= 0) {
            return [
                'should_convert' => false,
                'base_currency' => '',
                'target_currency' => '',
                'rate' => 1.0,
            ];
        }

        if (isset($cache[$term_id])) {
            return $cache[$term_id];
        }

        global $mulopimfwc_options;
        $options = is_array($mulopimfwc_options ?? null)
            ? $mulopimfwc_options
            : get_option('mulopimfwc_display_options', []);

        if (!mulopimfwc_is_location_wise_currency_enabled($options)) {
            $cache[$term_id] = [
                'should_convert' => false,
                'base_currency' => '',
                'target_currency' => '',
                'rate' => 1.0,
            ];
            return $cache[$term_id];
        }

        $base_currency = mulopimfwc_get_store_base_currency_code_raw();
        $currency_settings = mulopimfwc_get_currency_settings_for_location($term_id);
        $target_currency = strtoupper(trim((string) ($currency_settings['currency'] ?? '')));

        $rate = 1.0;
        $rate_raw = get_term_meta($term_id, 'location_currency_rate', true);
        if (is_numeric($rate_raw) && (float) $rate_raw > 0) {
            $rate = (float) $rate_raw;
        }

        $should_convert = (
            $base_currency !== '' &&
            $target_currency !== '' &&
            $base_currency !== $target_currency &&
            $rate > 0
        );

        $cache[$term_id] = [
            'should_convert' => $should_convert,
            'base_currency' => $base_currency,
            'target_currency' => $target_currency,
            'rate' => $rate,
        ];

        return $cache[$term_id];
    }
}

if (!function_exists('mulopimfwc_convert_price_amount_for_location')) {
    /**
     * Convert a base-currency amount into selected location currency when needed.
     *
     * @param mixed $amount
     * @param int   $location_term_id
     * @return mixed
     */
    function mulopimfwc_convert_price_amount_for_location($amount, $location_term_id)
    {
        $normalized_amount = mulopimfwc_normalize_price_amount($amount);
        if ($normalized_amount === null) {
            return $amount;
        }

        $context = mulopimfwc_get_location_currency_conversion_context($location_term_id);
        if (empty($context['should_convert'])) {
            return $amount;
        }

        $converted = $normalized_amount * (float) $context['rate'];
        if (function_exists('wc_format_decimal')) {
            return wc_format_decimal($converted, 6, false);
        }

        return round($converted, 6);
    }
}

if (!function_exists('mulopimfwc_format_price_by_location_in_admin_list')) {
    /**
     * Format a price value using location-specific currency settings.
     *
     * @param mixed    $price            Price amount.
     * @param int|null $location_term_id Location term ID. Null means default currency settings.
     * @return string
     */
    function mulopimfwc_format_price_by_location_in_admin_list($price, $location_term_id = null)
    {
        $currency_settings = mulopimfwc_get_currency_settings_for_location($location_term_id);
        return wc_price($price, [
            'currency' => $currency_settings['currency'],
            'price_format' => mulopimfwc_get_price_format_for_currency_position($currency_settings['position']),
        ]);
    }
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
                            $stock_data = function_exists('mulopimfwc_build_stock_display_label')
                                ? mulopimfwc_build_stock_display_label([
                                    'stock_qty' => $location_stock,
                                    'backorders' => get_post_meta($variation_id, '_location_backorders_' . $location->term_id, true),
                                    'location_id' => (int) $location->term_id,
                                    'count_format' => 'paren',
                                ])
                                : ['show' => true, 'label' => ($location_stock > 0 ? __('In stock', 'multi-location-product-and-inventory-management') . ' (' . $location_stock . ')' : __('Out of stock', 'multi-location-product-and-inventory-management')), 'status' => ($location_stock > 0 ? 'instock' : 'outofstock'), 'level' => '', 'class' => ''];
                            if (!empty($stock_data['show']) && $stock_data['label'] !== '') {
                                $status_class = ($stock_data['status'] === 'outofstock') ? 'outofstock' : 'instock';
                                $level_class = !empty($stock_data['level']) ? ' stock-level-' . $stock_data['level'] : '';
                                $output .= '<mark class="' . esc_attr($status_class . $level_class) . '">' . esc_html($stock_data['label']) . '</mark>';
                            }

                            if ($location_price) {
                                $output .= ' - ' . mulopimfwc_format_price_by_location_in_admin_list($location_price, (int) $location->term_id);
                            }
                            $output .= '</div>'; // New line for each location
                        }
                    }

                    // Add default stock and price for variation
                    $default_stock_quantity = $variation->get_stock_quantity();
                    $default_stock_status = $variation->get_stock_status();
                    $default_price = $variation->get_regular_price();

                    $output .= '<div style="margin-top: 5px;"><strong>' . __('Default', 'multi-location-product-and-inventory-management') . ': </strong>';

                    $stock_data = function_exists('mulopimfwc_build_stock_display_label')
                        ? mulopimfwc_build_stock_display_label([
                            'stock_qty' => $default_stock_quantity,
                            'stock_status' => $default_stock_status,
                            'backorders' => $variation->get_backorders(),
                            'count_format' => 'paren',
                        ])
                        : ['show' => true, 'label' => ($default_stock_status === 'instock' ? __('In stock', 'multi-location-product-and-inventory-management') . ($default_stock_quantity ? ' (' . $default_stock_quantity . ')' : '') : __('Out of stock', 'multi-location-product-and-inventory-management')), 'status' => ($default_stock_status === 'instock' ? 'instock' : 'outofstock'), 'level' => '', 'class' => ''];
                    if (!empty($stock_data['show']) && $stock_data['label'] !== '') {
                        $status_class = ($stock_data['status'] === 'outofstock') ? 'outofstock' : 'instock';
                        $level_class = !empty($stock_data['level']) ? ' stock-level-' . $stock_data['level'] : '';
                        $output .= '<mark class="' . esc_attr($status_class . $level_class) . '">' . esc_html($stock_data['label']) . '</mark>';
                    }

                    if ($default_price) {
                        $output .= ' - ' . mulopimfwc_format_price_by_location_in_admin_list($default_price);
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
                        $stock_data = function_exists('mulopimfwc_build_stock_display_label')
                            ? mulopimfwc_build_stock_display_label([
                                'stock_qty' => $location_stock,
                                'backorders' => get_post_meta($product->get_id(), '_location_backorders_' . $location->term_id, true),
                                'location_id' => (int) $location->term_id,
                                'count_format' => 'paren',
                            ])
                            : ['show' => true, 'label' => ($location_stock > 0 ? __('In stock', 'multi-location-product-and-inventory-management') . ' (' . $location_stock . ')' : __('Out of stock', 'multi-location-product-and-inventory-management')), 'status' => ($location_stock > 0 ? 'instock' : 'outofstock'), 'level' => '', 'class' => ''];
                        if (!empty($stock_data['show']) && $stock_data['label'] !== '') {
                            $status_class = ($stock_data['status'] === 'outofstock') ? 'outofstock' : 'instock';
                            $level_class = !empty($stock_data['level']) ? ' stock-level-' . $stock_data['level'] : '';
                            $output .= '<mark class="' . esc_attr($status_class . $level_class) . '">' . esc_html($stock_data['label']) . '</mark>';
                        }

                        if ($location_price) {
                            $output .= ' - ' . mulopimfwc_format_price_by_location_in_admin_list($location_price, (int) $location->term_id);
                        }
                        $output .= '</div>';
                    }
                }

                // Add default stock and price for simple product
                $default_stock_quantity = $product->get_stock_quantity();
                $default_stock_status = $product->get_stock_status();
                $default_price = $product->get_regular_price();

                $output .= '<div style="margin-top: 5px;"><strong>' . __('Default', 'multi-location-product-and-inventory-management') . ': </strong>';

                $stock_data = function_exists('mulopimfwc_build_stock_display_label')
                    ? mulopimfwc_build_stock_display_label([
                        'stock_qty' => $default_stock_quantity,
                        'stock_status' => $default_stock_status,
                        'backorders' => $product->get_backorders(),
                        'count_format' => 'paren',
                    ])
                    : ['show' => true, 'label' => ($default_stock_status === 'instock' ? __('In stock', 'multi-location-product-and-inventory-management') . ($default_stock_quantity ? ' (' . $default_stock_quantity . ')' : '') : __('Out of stock', 'multi-location-product-and-inventory-management')), 'status' => ($default_stock_status === 'instock' ? 'instock' : 'outofstock'), 'level' => '', 'class' => ''];
                if (!empty($stock_data['show']) && $stock_data['label'] !== '') {
                    $status_class = ($stock_data['status'] === 'outofstock') ? 'outofstock' : 'instock';
                    $level_class = !empty($stock_data['level']) ? ' stock-level-' . $stock_data['level'] : '';
                    $output .= '<mark class="' . esc_attr($status_class . $level_class) . '">' . esc_html($stock_data['label']) . '</mark>';
                }

                if ($default_price) {
                    $output .= ' - ' . mulopimfwc_format_price_by_location_in_admin_list($default_price);
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

                    $stock_data = function_exists('mulopimfwc_build_stock_display_label')
                        ? mulopimfwc_build_stock_display_label([
                            'stock_qty' => $default_stock_quantity,
                            'stock_status' => $default_stock_status,
                            'backorders' => $variation->get_backorders(),
                            'count_format' => 'paren',
                        ])
                        : ['show' => true, 'label' => ($default_stock_status === 'instock' ? __('In stock', 'multi-location-product-and-inventory-management') . ($default_stock_quantity ? ' (' . $default_stock_quantity . ')' : '') : __('Out of stock', 'multi-location-product-and-inventory-management')), 'status' => ($default_stock_status === 'instock' ? 'instock' : 'outofstock'), 'level' => '', 'class' => ''];
                    if (!empty($stock_data['show']) && $stock_data['label'] !== '') {
                        $status_class = ($stock_data['status'] === 'outofstock') ? 'outofstock' : 'instock';
                        $level_class = !empty($stock_data['level']) ? ' stock-level-' . $stock_data['level'] : '';
                        $output .= '<mark class="' . esc_attr($status_class . $level_class) . '">' . esc_html($stock_data['label']) . '</mark>';
                    }

                    if ($default_price) {
                        $output .= ' - ' . mulopimfwc_format_price_by_location_in_admin_list($default_price);
                    }
                    $output .= '</div><br>';
                }
                echo wp_kses_post($output);
            } else {
                $default_stock_quantity = $product->get_stock_quantity();
                $default_stock_status = $product->get_stock_status();
                $default_price = $product->get_regular_price();

                $output = '<div><strong>' . __('Default', 'multi-location-product-and-inventory-management') . ': </strong>';

                $stock_data = function_exists('mulopimfwc_build_stock_display_label')
                    ? mulopimfwc_build_stock_display_label([
                        'stock_qty' => $default_stock_quantity,
                        'stock_status' => $default_stock_status,
                        'backorders' => $product->get_backorders(),
                        'count_format' => 'paren',
                    ])
                    : ['show' => true, 'label' => ($default_stock_status === 'instock' ? __('In stock', 'multi-location-product-and-inventory-management') . ($default_stock_quantity ? ' (' . $default_stock_quantity . ')' : '') : __('Out of stock', 'multi-location-product-and-inventory-management')), 'status' => ($default_stock_status === 'instock' ? 'instock' : 'outofstock'), 'level' => '', 'class' => ''];
                if (!empty($stock_data['show']) && $stock_data['label'] !== '') {
                    $status_class = ($stock_data['status'] === 'outofstock') ? 'outofstock' : 'instock';
                    $level_class = !empty($stock_data['level']) ? ' stock-level-' . $stock_data['level'] : '';
                    $output .= '<mark class="' . esc_attr($status_class . $level_class) . '">' . esc_html($stock_data['label']) . '</mark>';
                }

                if ($default_price) {
                    $output .= ' - ' . mulopimfwc_format_price_by_location_in_admin_list($default_price);
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
