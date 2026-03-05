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
        // Convert fixed coupon values to the active location currency at runtime.
        add_filter('woocommerce_coupon_get_amount', [$this, 'filter_coupon_amount_by_location_currency'], 20, 2);
        // Convert coupon spend thresholds to runtime currency for validation/notices.
        add_filter('woocommerce_coupon_get_minimum_amount', [$this, 'filter_coupon_threshold_by_location_currency'], 20, 2);
        add_filter('woocommerce_coupon_get_maximum_amount', [$this, 'filter_coupon_threshold_by_location_currency'], 20, 2);

        $options = get_option('mulopimfwc_display_options', []);
        if (function_exists('mulopimfwc_is_location_discounts_enabled') && !mulopimfwc_is_location_discounts_enabled($options)) {
            return;
        }

        // Admin UI fields
        add_action('woocommerce_coupon_options_usage_restriction', [$this, 'add_usage_restriction_fields'], 20, 1);
        add_action('woocommerce_coupon_options_save', [$this, 'save_usage_restriction_fields'], 10, 2);

        // Frontend validation (product-level and cart-level)
        add_filter('woocommerce_coupon_is_valid_for_product', [$this, 'validate_product_level'], 10, 3);
        add_filter('woocommerce_coupon_is_valid', [$this, 'validate_cart_level'], 10, 2);
        add_filter('woocommerce_coupon_get_items_to_apply', [$this, 'filter_items_to_apply'], 10, 3);

        // Improve error message (optional)
        add_filter('woocommerce_coupon_error', [$this, 'friendly_error_message'], 10, 3);
    }

    /**
     * Convert fixed coupon amounts from base currency to selected location currency.
     *
     * Percentage coupons are untouched.
     *
     * @param mixed     $amount
     * @param WC_Coupon $coupon
     * @return mixed
     */
    public function filter_coupon_amount_by_location_currency($amount, $coupon)
    {
        if (is_admin() && !wp_doing_ajax()) {
            return $amount;
        }

        if (!$coupon instanceof WC_Coupon) {
            return $amount;
        }

        $discount_type = (string) $coupon->get_discount_type();
        if (!in_array($discount_type, ['fixed_cart', 'fixed_product'], true)) {
            return $amount;
        }

        if (!function_exists('mulopimfwc_convert_base_amount_to_runtime_currency')) {
            return $amount;
        }

        $converted_amount = mulopimfwc_convert_base_amount_to_runtime_currency($amount);
        return is_numeric($converted_amount) ? (float) $converted_amount : $amount;
    }

    /**
     * Convert coupon min/max spend thresholds from base currency to runtime location currency.
     *
     * @param mixed     $amount
     * @param WC_Coupon $coupon
     * @return mixed
     */
    public function filter_coupon_threshold_by_location_currency($amount, $coupon)
    {
        if (is_admin() && !wp_doing_ajax()) {
            return $amount;
        }

        if (!$coupon instanceof WC_Coupon) {
            return $amount;
        }

        if (!function_exists('mulopimfwc_convert_base_amount_to_runtime_currency')) {
            return $amount;
        }

        $converted_amount = mulopimfwc_convert_base_amount_to_runtime_currency($amount);
        return is_numeric($converted_amount) ? (float) $converted_amount : $amount;
    }

    /** -------------------------
     * Admin: render fields
     * ------------------------*/
    public function add_usage_restriction_fields($coupon_id)
    {
        $include_selected = (array) get_post_meta($coupon_id, self::META_INCLUDE, true);
        $exclude_selected = (array) get_post_meta($coupon_id, self::META_EXCLUDE, true);

        $term_args = [
            'taxonomy'   => self::TAX,
            'hide_empty' => false,
        ];

        $allowed_term_ids = $this->get_location_manager_allowed_term_ids();
        if (is_array($allowed_term_ids)) {
            if (empty($allowed_term_ids)) {
                $terms = [];
            } else {
                $term_args['include'] = $allowed_term_ids;
            }
        }

        if (!isset($terms)) {
            $terms = get_terms($term_args);
        }

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
            'name'              => self::META_INCLUDE . '[]',
            'label'             => __('Product locations', 'woocommerce'),
            'description'       => __('Coupon applies only to products that have at least one of the selected locations. Leave empty for no location-based inclusion.', 'woocommerce'),
            'desc_tip'          => true,
            'value'             => $include_selected,
            'options'           => $options,
            'custom_attributes' => [
                'multiple' => 'multiple',
                'data-placeholder' => __('Select locations…', 'woocommerce'),
            ],
            'class'             => 'wc-enhanced-select',
            'style'             => 'width:50%;',
        ]);

        // Exclude
        woocommerce_wp_select([
            'id'                => self::META_EXCLUDE,
            'name'              => self::META_EXCLUDE . '[]',
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
            'style'             => 'width:50%;',
        ]);

        $this->render_include_exclude_sync_script();
        echo '</div>';
    }

    private function render_include_exclude_sync_script()
    {
        $include_id = esc_js(self::META_INCLUDE);
        $exclude_id = esc_js(self::META_EXCLUDE);
?>
        <script type="text/javascript">
            jQuery(function($) {
                var $include = $('#<?php echo $include_id; ?>');
                var $exclude = $('#<?php echo $exclude_id; ?>');
                var syncing = false;

                if (!$include.length || !$exclude.length) {
                    return;
                }

                function asArray(values) {
                    if (!values) {
                        return [];
                    }

                    return Array.isArray(values) ? values : [values];
                }

                function removeOverlap(primaryValues, secondaryValues) {
                    var blocked = {};

                    $.each(primaryValues, function(_, value) {
                        blocked[String(value)] = true;
                    });

                    return $.grep(secondaryValues, function(value) {
                        return !blocked[String(value)];
                    });
                }

                function syncOneWay($source, $target) {
                    var sourceValues = asArray($source.val());
                    var targetValues = asArray($target.val());
                    var cleanTargetValues = removeOverlap(sourceValues, targetValues);
                    var blocked = {};

                    $.each(sourceValues, function(_, value) {
                        blocked[String(value)] = true;
                    });

                    if (cleanTargetValues.length !== targetValues.length) {
                        $target.val(cleanTargetValues);
                    }

                    $target.find('option').each(function() {
                        var value = String($(this).val());
                        var shouldDisable = !!blocked[value] && !$(this).prop('selected');
                        $(this).prop('disabled', shouldDisable);
                    });
                }

                function syncBoth(preferred) {
                    if (syncing) {
                        return;
                    }

                    syncing = true;

                    if (preferred === 'exclude') {
                        syncOneWay($exclude, $include);
                        syncOneWay($include, $exclude);
                    } else {
                        syncOneWay($include, $exclude);
                        syncOneWay($exclude, $include);
                    }

                    // Refresh Select2 UI after changing selected/disabled options.
                    $include.trigger('change.select2');
                    $exclude.trigger('change.select2');

                    syncing = false;
                }

                $include.on('change select2:select select2:unselect', function() {
                    syncBoth('include');
                });

                $exclude.on('change select2:select select2:unselect', function() {
                    syncBoth('exclude');
                });

                // Initial load: if overlap already exists, keep include values and clean exclude.
                syncBoth('include');
            });
        </script>
<?php
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

    private function get_location_manager_allowed_term_ids()
    {
        if (!is_user_logged_in() || !class_exists('MULOPIMFWC_Location_Managers')) {
            return null;
        }

        $user = wp_get_current_user();
        if (!in_array('mulopimfwc_location_manager', $user->roles, true)) {
            return null;
        }

        $assigned_locations = get_user_meta($user->ID, 'mulopimfwc_assigned_locations', true);
        if (!is_array($assigned_locations)) {
            $assigned_locations = [];
        }

        $assigned_locations = array_values(array_filter($assigned_locations));
        if (empty($assigned_locations)) {
            return [];
        }

        $terms = get_terms([
            'taxonomy'   => self::TAX,
            'hide_empty' => false,
            'slug'       => $assigned_locations,
            'fields'     => 'ids',
        ]);

        if (is_wp_error($terms)) {
            return [];
        }

        return array_values(array_map('absint', $terms));
    }

    /** -------------------------
     * Admin: save fields
     * ------------------------*/
    public function save_usage_restriction_fields($post_id, $coupon)
    {
        $include = isset($_POST[self::META_INCLUDE]) ? wp_unslash($_POST[self::META_INCLUDE]) : [];
        $exclude = isset($_POST[self::META_EXCLUDE]) ? wp_unslash($_POST[self::META_EXCLUDE]) : [];

        // Keep compatibility with both scalar and array request payloads.
        $include = is_array($include) ? $include : [$include];
        $exclude = is_array($exclude) ? $exclude : [$exclude];

        $include = array_values(array_unique(array_filter(array_map('absint', $include))));
        $exclude = array_values(array_unique(array_filter(array_map('absint', $exclude))));
        if (! empty($include) && ! empty($exclude)) {
            // Never allow the same term in both include and exclude.
            $exclude = array_values(array_diff($exclude, $include));
        }

        $allowed_term_ids = $this->get_location_manager_allowed_term_ids();
        if (is_array($allowed_term_ids)) {
            $include = array_values(array_intersect($include, $allowed_term_ids));
            $exclude = array_values(array_intersect($exclude, $allowed_term_ids));

            $existing_include = (array) get_post_meta($post_id, self::META_INCLUDE, true);
            $existing_exclude = (array) get_post_meta($post_id, self::META_EXCLUDE, true);

            $existing_include = array_values(array_unique(array_filter(array_map('absint', $existing_include))));
            $existing_exclude = array_values(array_unique(array_filter(array_map('absint', $existing_exclude))));

            $include = array_values(array_unique(array_merge($include, array_diff($existing_include, $allowed_term_ids))));
            $exclude = array_values(array_unique(array_merge($exclude, array_diff($existing_exclude, $allowed_term_ids))));
        }

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

        $behavior = $this->get_cross_location_coupon_behavior();
        if ($behavior === 'full_cart') {
            // In "apply on full cart" mode we don't block any line items; cart-level check will ensure at least one qualifies.
            return true;
        }

        $coupon_id = $coupon instanceof WC_Coupon ? $coupon->get_id() : absint($coupon);
        $include = (array) get_post_meta($coupon_id, self::META_INCLUDE, true);
        $exclude = (array) get_post_meta($coupon_id, self::META_EXCLUDE, true);

        // If neither include nor exclude is set, we do nothing
        if (empty($include) && empty($exclude)) {
            return $valid;
        }

        $product_id = $product instanceof WC_Product ? ($product->is_type('variation') ? $product->get_parent_id() : $product->get_id()) : absint($product);
        $loc_ids = $this->get_location_ids_for_product($product_id);

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
        $behavior = $this->get_cross_location_coupon_behavior();

        if (empty($include) && empty($exclude)) {
            return true; // no location rules to enforce
        }

        if (is_null(WC()->cart)) {
            return true; // nothing to validate (be lenient)
        }

        if ($behavior === 'restrict' && ! empty($exclude)) {
            $selected_location_ids = $this->get_selected_location_term_ids();
            if (! empty($selected_location_ids) && array_intersect($exclude, $selected_location_ids)) {
                wc_add_notice(__('This coupon is not valid for your selected store location.', 'woocommerce'), 'error');
                return false;
            }
        }

        $has_qualifying_line = false;

        foreach (WC()->cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            if (! $product instanceof WC_Product) continue;

            $pid = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
            $loc_ids = $this->get_location_ids_for_product($pid);

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

    /**
     * Filter the list of line items a coupon should apply to when only eligible products should be discounted.
     */
    public function filter_items_to_apply($items_to_apply, $coupon, $discounts)
    {
        $behavior = $this->get_cross_location_coupon_behavior();
        if ($behavior !== 'applicable_products') {
            return $items_to_apply;
        }

        $coupon_id = $coupon instanceof WC_Coupon ? $coupon->get_id() : absint($coupon);
        $include = (array) get_post_meta($coupon_id, self::META_INCLUDE, true);
        $exclude = (array) get_post_meta($coupon_id, self::META_EXCLUDE, true);

        if (empty($include) && empty($exclude)) {
            return $items_to_apply;
        }

        $filtered = [];

        foreach ($items_to_apply as $item) {
            if (! isset($item->product) || ! $item->product instanceof WC_Product) {
                continue;
            }

            $pid = $item->product->is_type('variation') ? $item->product->get_parent_id() : $item->product->get_id();
            $loc_ids = $this->get_location_ids_for_product($pid);

            $hits_exclusion = (! empty($exclude) && array_intersect($exclude, $loc_ids));
            $hits_inclusion = (empty($include) || array_intersect($include, $loc_ids));

            if (! $hits_exclusion && $hits_inclusion) {
                $filtered[] = $item;
            }
        }

        return $filtered;
    }

    /**
     * Fetch assigned location IDs for a product, including ancestor terms for hierarchical matches.
     */
    private function get_location_ids_for_product($product_id)
    {
        $terms = wp_get_object_terms($product_id, self::TAX, ['fields' => 'ids']);
        if (is_wp_error($terms)) {
            $terms = [];
        }

        return $this->expand_with_ancestors($terms);
    }

    /**
     * Return selected location (cookie-driven) term IDs including ancestors for exclusion checks.
     */
    private function get_selected_location_term_ids()
    {
        $slug = mulopimfwc_get_store_location_cookie();
        if ($slug === '' && isset($_COOKIE['mulopimfwc_user_location'])) {
            $slug = sanitize_text_field(wp_unslash($_COOKIE['mulopimfwc_user_location']));
        }

        if ($slug === '') {
            return [];
        }

        $term = get_term_by('slug', $slug, self::TAX);
        if (! $term || is_wp_error($term)) {
            return [];
        }

        return $this->expand_with_ancestors([$term->term_id]);
    }

    /**
     * Determine saved behavior for mixed-location coupons.
     */
    private function get_cross_location_coupon_behavior()
    {
        global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
        $behavior = isset($options['cross_location_coupon_behavior']) ? $options['cross_location_coupon_behavior'] : 'restrict';
        $allowed = ['restrict', 'applicable_products', 'full_cart'];

        return in_array($behavior, $allowed, true) ? $behavior : 'restrict';
    }

    /**
     * Given term IDs, append ancestors and de-duplicate.
     */
    private function expand_with_ancestors($term_ids)
    {
        $all_ids = [];

        foreach ((array) $term_ids as $term_id) {
            $term_id = absint($term_id);
            if (! $term_id) {
                continue;
            }

            $all_ids[] = $term_id;

            $ancestors = get_ancestors($term_id, self::TAX, 'taxonomy');
            if (! empty($ancestors) && is_array($ancestors)) {
                foreach ($ancestors as $ancestor_id) {
                    $ancestor_id = absint($ancestor_id);
                    if ($ancestor_id) {
                        $all_ids[] = $ancestor_id;
                    }
                }
            }
        }

        return array_values(array_unique($all_ids));
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
