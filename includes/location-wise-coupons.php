<?php
if (! defined('ABSPATH')) exit;

/**
 * Coupon usage restriction: Product Locations (include/exclude) for taxonomy mulopimfwc_store_location
 */
class MULOPIMFWC_Coupon_Location_Restrictions
{

    const TAX = 'mulopimfwc_store_location';
    const META_INCLUDE = '_mulopimfwc_coupon_locations_include';
    const META_EXCLUDE = '_mulopimfwc_coupon_locations_exclude';

    public function __construct()
    {
        // Admin UI fields
        add_action('woocommerce_coupon_options_usage_restriction', [$this, 'add_usage_restriction_fields'], 20, 1);
        add_action('woocommerce_coupon_options_save', [$this, 'save_usage_restriction_fields'], 10, 2);

        // Frontend validation (product-level and cart-level)
        add_filter('woocommerce_coupon_is_valid_for_product', [$this, 'validate_product_level'], 10, 3);
        add_filter('woocommerce_coupon_is_valid', [$this, 'validate_cart_level'], 10, 2);

        // Improve error message (optional)
        add_filter('woocommerce_coupon_error', [$this, 'friendly_error_message'], 10, 3);
    }

    /** -------------------------
     * Admin: render fields
     * ------------------------*/
    public function add_usage_restriction_fields($coupon_id)
    {
        $include_selected = (array) get_post_meta($coupon_id, self::META_INCLUDE, true);
        $exclude_selected = (array) get_post_meta($coupon_id, self::META_EXCLUDE, true);

        $terms = get_terms([
            'taxonomy'   => self::TAX,
            'hide_empty' => false,
        ]);

        $options = [];
        if (! is_wp_error($terms)) {
            foreach ($terms as $t) {
                $label = $t->name;
                if ($t->parent) {
                    // add a breadcrumb-ish label if hierarchical
                    $label = $this->get_term_breadcrumb($t);
                }
                $options[(string) $t->term_id] = $label;
            }
        }

        // Include
        echo '<div class="options_group">';
        echo '<div class="hr-section hr-section-coupon_restrictions">And</div>';
        woocommerce_wp_select([
            'id'                => self::META_INCLUDE,
            'label'             => __('Product locations', 'woocommerce'),
            'description'       => __('Coupon applies only to products that have at least one of the selected locations. Leave empty for no location-based inclusion.', 'woocommerce'),
            'desc_tip'          => true,
            'value'             => $include_selected,
            'options'           => $options,
            'custom_attributes' => [
                'multiple' => 'multiple',
                'data-placeholder' => __('Select locations…', 'woocommerce'),
                'style' => 'width: 50%',
            ],
            'class'             => 'wc-enhanced-select',
        ]);

        // Exclude
        woocommerce_wp_select([
            'id'                => self::META_EXCLUDE,
            'label'             => __('Exclude locations', 'woocommerce'),
            'description'       => __('Products with these locations will NOT qualify for this coupon.', 'woocommerce'),
            'desc_tip'          => true,
            'value'             => $exclude_selected,
            'options'           => $options,
            'custom_attributes' => [
                'multiple' => 'multiple',
                'data-placeholder' => __('Select locations to exclude…', 'woocommerce'),
            ],
            'class'             => 'wc-enhanced-select',
        ]);
        echo '</div>';
    }

    private function get_term_breadcrumb(WP_Term $term)
    {
        $names = [$term->name];
        $walker = $term;
        while ($walker->parent) {
            $walker = get_term($walker->parent, self::TAX);
            if ($walker && ! is_wp_error($walker)) {
                array_unshift($names, $walker->name);
            } else {
                break;
            }
        }
        return implode(' › ', $names);
    }

    /** -------------------------
     * Admin: save fields
     * ------------------------*/
    public function save_usage_restriction_fields($post_id, $coupon)
    {
        $include = isset($_POST[self::META_INCLUDE]) ? (array) $_POST[self::META_INCLUDE] : [];
        $exclude = isset($_POST[self::META_EXCLUDE]) ? (array) $_POST[self::META_EXCLUDE] : [];

        $include = array_values(array_unique(array_filter(array_map('absint', $include))));
        $exclude = array_values(array_unique(array_filter(array_map('absint', $exclude))));

        update_post_meta($post_id, self::META_INCLUDE, $include);
        update_post_meta($post_id, self::META_EXCLUDE, $exclude);
    }

    /** -------------------------
     * Product-level validation
     * ------------------------*/
    public function validate_product_level($valid, $product, $coupon)
    {
        // If already invalid, keep it
        if (! $valid) return false;

        $coupon_id = $coupon instanceof WC_Coupon ? $coupon->get_id() : absint($coupon);
        $include = (array) get_post_meta($coupon_id, self::META_INCLUDE, true);
        $exclude = (array) get_post_meta($coupon_id, self::META_EXCLUDE, true);

        // If neither include nor exclude is set, we do nothing
        if (empty($include) && empty($exclude)) {
            return $valid;
        }

        $product_id = $product instanceof WC_Product ? ($product->is_type('variation') ? $product->get_parent_id() : $product->get_id()) : absint($product);
        $loc_ids = wp_get_object_terms($product_id, self::TAX, ['fields' => 'ids']);
        if (is_wp_error($loc_ids)) $loc_ids = [];

        // Exclusion first: any intersection => invalid
        if (! empty($exclude) && array_intersect($exclude, $loc_ids)) {
            return false;
        }

        // Inclusion: if set, must intersect
        if (! empty($include) && ! array_intersect($include, $loc_ids)) {
            return false;
        }

        return true;
    }

    /** -------------------------
     * Cart-level validation (ensures at least one qualifying item exists)
     * ------------------------*/
    public function validate_cart_level($valid, $coupon)
    {
        if (! $valid) return false;

        $coupon_id = $coupon instanceof WC_Coupon ? $coupon->get_id() : absint($coupon);
        $include = (array) get_post_meta($coupon_id, self::META_INCLUDE, true);
        $exclude = (array) get_post_meta($coupon_id, self::META_EXCLUDE, true);

        if (empty($include) && empty($exclude)) {
            return true; // no location rules to enforce
        }

        if (is_null(WC()->cart)) {
            return true; // nothing to validate (be lenient)
        }

        $has_qualifying_line = false;

        foreach (WC()->cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            if (! $product instanceof WC_Product) continue;

            $pid = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
            $loc_ids = wp_get_object_terms($pid, self::TAX, ['fields' => 'ids']);
            if (is_wp_error($loc_ids)) $loc_ids = [];

            // Exclusion: if product hits exclusion, it cannot qualify—but it doesn't kill the entire coupon
            $hits_exclusion = (! empty($exclude) && array_intersect($exclude, $loc_ids));

            // Inclusion: if set, product must have intersection to be a qualifying line
            $hits_inclusion = (empty($include) || array_intersect($include, $loc_ids));

            if (! $hits_exclusion && $hits_inclusion) {
                $has_qualifying_line = true;
                break;
            }
        }

        if (! $has_qualifying_line) {
            // Prevent usage if no cart item qualifies
            wc_add_notice(__('This coupon does not apply to the selected product locations in your cart.', 'woocommerce'), 'error');
            return false;
        }

        return true;
    }

    /** Optional nicer error if WooCommerce surfaces a generic one */
    public function friendly_error_message($err, $err_code, $coupon)
    {
        if (in_array($err_code, ['coupon_error', 'invalid_coupon'], true)) {
            // Leave generics alone
            return $err;
        }
        // Custom code is not guaranteed; message above via wc_add_notice is primary.
        return $err;
    }
}

// Bootstrap (after WooCommerce)
add_action('plugins_loaded', function () {
    if (class_exists('WooCommerce')) {
        new MULOPIMFWC_Coupon_Location_Restrictions();
    }
});
