<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('MULOPIMFWC_Order_Split_By_Location')) {

    class MULOPIMFWC_Order_Split_By_Location
    {
        private $is_splitting = false;

        public function __construct()
        {
            // Capture shipping package location for later allocation.
            add_action('woocommerce_checkout_create_order_shipping_item', [$this, 'save_package_location_meta'], 10, 4);

            // Split order after checkout is created (classic + blocks).
            add_action('woocommerce_checkout_order_processed', [$this, 'maybe_split_order'], 25, 3);
            add_action('woocommerce_store_api_checkout_order_processed', [$this, 'maybe_split_order'], 25, 2);

            // Validation for unknown locations (classic + blocks).
            add_action('woocommerce_checkout_process', [$this, 'validate_unknown_locations_classic'], 20);
            add_action('woocommerce_store_api_checkout_update_order_from_request', [$this, 'validate_unknown_locations_blocks'], 10, 2);

            // Sync child statuses when parent updates.
            add_action('woocommerce_order_status_changed', [$this, 'sync_child_status_from_parent'], 10, 4);
            add_action('woocommerce_payment_complete', [$this, 'sync_children_on_payment_complete'], 10, 1);

            // Avoid duplicate customer emails on child orders.
            $customer_emails = [
                'customer_processing_order',
                'customer_completed_order',
                'customer_on_hold_order',
                'customer_refunded_order',
                'customer_partially_refunded_order',
                'customer_invoice',
                'customer_note',
            ];
            foreach ($customer_emails as $email_id) {
                add_filter('woocommerce_email_recipient_' . $email_id, [$this, 'maybe_disable_customer_email_for_child'], 20, 2);
            }

            // Optionally suppress parent admin emails when children exist.
            $admin_emails = [
                'new_order',
                'cancelled_order',
                'failed_order',
                'on_hold_order',
            ];
            foreach ($admin_emails as $email_id) {
                add_filter('woocommerce_email_recipient_' . $email_id, [$this, 'maybe_disable_admin_email_for_parent'], 20, 2);
            }

            // Avoid double stock reduction if parent keeps items for gateway compatibility.
            add_filter('woocommerce_can_reduce_order_stock', [$this, 'maybe_skip_parent_stock_reduction'], 10, 2);
            add_filter('woocommerce_can_restore_order_stock', [$this, 'maybe_skip_parent_stock_restoration'], 10, 2);

            // Frontend rendering support for split parent orders that remove parent line items.
            add_action('woocommerce_order_details_before_order_table', [$this, 'render_split_parent_summary_for_customer'], 8);
            add_action('woocommerce_order_details_after_order_table_items', [$this, 'render_split_parent_line_items_for_customer'], 10);
            add_filter('woocommerce_get_order_item_totals', [$this, 'filter_split_parent_totals_for_customer'], 10, 3);
        }

        private function get_options(): array
        {
            global $mulopimfwc_options;
            return is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
        }

        private function is_split_enabled(array $options): bool
        {
            if (function_exists('mulopimfwc_is_split_order_enabled')) {
                return mulopimfwc_is_split_order_enabled($options);
            }

            if (!mulopimfwc_premium_feature()) {
                return false;
            }

            if (function_exists('mulopimfwc_is_manual_assignment_strict_mode') && mulopimfwc_is_manual_assignment_strict_mode($options)) {
                return false;
            }

            return isset($options['allow_mixed_location_cart'], $options['split_order_by_location'])
                && $options['allow_mixed_location_cart'] === 'on'
                && $options['split_order_by_location'] === 'on';
        }

        private function get_unknown_policy(array $options): string
        {
            $value = isset($options['split_order_unknown_items']) ? sanitize_text_field($options['split_order_unknown_items']) : 'block_checkout';
            $allowed = ['block_checkout', 'unassigned_child', 'keep_in_parent'];
            return in_array($value, $allowed, true) ? $value : 'block_checkout';
        }

        private function is_split_parent($order): bool
        {
            return $order instanceof WC_Order && $order->get_meta('_mulopimfwc_split_parent') === 'yes';
        }

        private function is_split_child($order): bool
        {
            return $order instanceof WC_Order && $order->get_meta('_mulopimfwc_split_child') === 'yes';
        }

        private function is_customer_order_details_context(): bool
        {
            $is_ajax = function_exists('wp_doing_ajax') ? wp_doing_ajax() : false;
            if (is_admin() && !$is_ajax) {
                return false;
            }

            if (function_exists('is_order_received_page') && is_order_received_page()) {
                return true;
            }

            if (
                function_exists('is_account_page') &&
                function_exists('is_wc_endpoint_url') &&
                is_account_page() &&
                is_wc_endpoint_url('view-order')
            ) {
                return true;
            }

            return false;
        }

        private function get_split_child_orders($order): array
        {
            if (!$this->is_split_parent($order)) {
                return [];
            }

            $child_ids = array_values(array_unique(array_filter(array_map('absint', (array) $order->get_meta('_mulopimfwc_split_children')))));
            if (empty($child_ids)) {
                return [];
            }

            $children = [];
            foreach ($child_ids as $child_id) {
                $child = wc_get_order($child_id);
                if (!$child instanceof WC_Order) {
                    continue;
                }

                if (!$this->is_split_child($child)) {
                    continue;
                }

                $children[] = $child;
            }

            return $children;
        }

        private function should_render_split_parent_customer_items($order): bool
        {
            if (!$order instanceof WC_Order) {
                return false;
            }

            if (!$this->is_customer_order_details_context()) {
                return false;
            }

            if (!$this->is_split_parent($order)) {
                return false;
            }

            if (!empty($order->get_items('line_item'))) {
                return false;
            }

            return !empty($this->get_split_child_orders($order));
        }

        private function get_location_label_from_slug(string $location_slug): string
        {
            $location_slug = sanitize_title($location_slug);
            if ($location_slug === '') {
                return __('Unassigned', 'multi-location-product-and-inventory-management');
            }

            $term = get_term_by('slug', $location_slug, 'mulopimfwc_store_location');
            if ($term && !is_wp_error($term)) {
                return $term->name;
            }

            return ucwords(str_replace(['-', '_'], ' ', $location_slug));
        }

        public function render_split_parent_summary_for_customer($order)
        {
            if (!$this->should_render_split_parent_customer_items($order)) {
                return;
            }

            $children = $this->get_split_child_orders($order);
            if (empty($children)) {
                return;
            }

            $child_numbers = array_map(static function (WC_Order $child): string {
                return '#' . $child->get_order_number();
            }, $children);

            echo '<div class="woocommerce-info mulopimfwc-split-parent-notice">';
            echo '<p style="margin:0;">' . esc_html(
                sprintf(
                    __('Your order was split by location into child orders: %s. Products are listed below by child order.', 'multi-location-product-and-inventory-management'),
                    implode(', ', $child_numbers)
                )
            ) . '</p>';
            echo '</div>';
        }

        public function render_split_parent_line_items_for_customer($order)
        {
            if (!$this->should_render_split_parent_customer_items($order)) {
                return;
            }

            $children = $this->get_split_child_orders($order);
            if (empty($children)) {
                return;
            }

            foreach ($children as $child) {
                $location_slug = (string) $child->get_meta('_store_location');
                $location_label = $this->get_location_label_from_slug($location_slug);

                echo '<tr class="mulopimfwc-split-order-group">';
                echo '<td colspan="2"><strong>' . esc_html(sprintf(__('Location: %s', 'multi-location-product-and-inventory-management'), $location_label)) . '</strong> ';
                echo '<small>(' . esc_html(sprintf(__('Order %s', 'multi-location-product-and-inventory-management'), '#' . $child->get_order_number())) . ')</small></td>';
                echo '</tr>';

                $show_purchase_note = $child->has_status(apply_filters('woocommerce_purchase_note_order_statuses', ['completed', 'processing']));
                $child_items = $child->get_items(apply_filters('woocommerce_purchase_order_item_types', 'line_item'));

                foreach ($child_items as $item_id => $item) {
                    $product = $item->get_product();
                    wc_get_template(
                        'order/order-details-item.php',
                        [
                            'order' => $child,
                            'item_id' => $item_id,
                            'item' => $item,
                            'show_purchase_note' => $show_purchase_note,
                            'purchase_note' => $product ? $product->get_purchase_note() : '',
                            'product' => $product,
                        ]
                    );
                }
            }
        }

        public function filter_split_parent_totals_for_customer($total_rows, $order, $tax_display)
        {
            if (!$this->should_render_split_parent_customer_items($order)) {
                return $total_rows;
            }

            $children = $this->get_split_child_orders($order);
            if (empty($children)) {
                return $total_rows;
            }

            $subtotal_total = 0.0;
            $discount_total = 0.0;
            $shipping_total = 0.0;
            $order_total = 0.0;

            foreach ($children as $child) {
                $subtotal_total += (float) $child->get_subtotal();
                $discount_total += (float) $child->get_discount_total();
                $shipping_total += (float) $child->get_shipping_total();
                $order_total += (float) $child->get_total();
            }

            $currency = $order->get_currency();

            if (isset($total_rows['cart_subtotal'])) {
                $total_rows['cart_subtotal']['value'] = wc_price($subtotal_total, ['currency' => $currency]);
            } elseif (isset($total_rows['subtotal'])) {
                // Backward compatibility for older/custom total row keys.
                $total_rows['subtotal']['value'] = wc_price($subtotal_total, ['currency' => $currency]);
            }

            if ($discount_total > 0 && isset($total_rows['discount'])) {
                $total_rows['discount']['value'] = '-' . wc_price($discount_total, ['currency' => $currency]);
            }

            if (isset($total_rows['shipping'])) {
                $total_rows['shipping']['value'] = wc_price($shipping_total, ['currency' => $currency]);
            }

            if (isset($total_rows['order_total'])) {
                $total_rows['order_total']['value'] = wc_price($order_total, ['currency' => $currency]);
            }

            return $total_rows;
        }

        public function save_package_location_meta($item, $package_key, $package, $order)
        {
            if (!$item || !is_array($package)) {
                return;
            }

            if (!empty($package['location_slug'])) {
                $item->add_meta_data('_mulopimfwc_package_location', sanitize_title($package['location_slug']), true);
            }
        }

        private function cart_has_unknown_items(): bool
        {
            if (!function_exists('WC') || !WC()->cart) {
                return false;
            }

            foreach (WC()->cart->get_cart() as $cart_item) {
                $location = isset($cart_item['mulopimfwc_location']) ? (string) $cart_item['mulopimfwc_location'] : '';
                $location = sanitize_title($location);

                if ($location === '' || $location === 'unknown' || $location === 'all-products') {
                    return true;
                }

                if (function_exists('mulopimfwc_validate_location_slug')) {
                    if (!mulopimfwc_validate_location_slug($location)) {
                        return true;
                    }
                }
            }

            return false;
        }

        public function validate_unknown_locations_classic()
        {
            $options = $this->get_options();
            if (!$this->is_split_enabled($options)) {
                return;
            }

            if ($this->get_unknown_policy($options) !== 'block_checkout') {
                return;
            }

            if (!$this->cart_has_unknown_items()) {
                return;
            }

            if (function_exists('wc_add_notice')) {
                wc_add_notice(
                    __('Some items in your cart do not have a valid store location. Please select a valid location before checkout.', 'multi-location-product-and-inventory-management'),
                    'error'
                );
            }
        }

        public function validate_unknown_locations_blocks($order, $request)
        {
            $options = $this->get_options();
            if (!$this->is_split_enabled($options)) {
                return;
            }

            if ($this->get_unknown_policy($options) !== 'block_checkout') {
                return;
            }

            if (!$this->cart_has_unknown_items()) {
                return;
            }

            if (class_exists('\Automattic\WooCommerce\StoreApi\Exceptions\RouteException')) {
                throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
                    'mulopimfwc_location_missing',
                    __('Some items in your cart do not have a valid store location. Please select a valid location before checkout.', 'multi-location-product-and-inventory-management'),
                    400
                );
            }

            throw new Exception(__('Some items in your cart do not have a valid store location. Please select a valid location before checkout.', 'multi-location-product-and-inventory-management'));
        }

        public function maybe_split_order($maybe_order, $posted_data = null, $maybe_order_obj = null)
        {
            if ($this->is_splitting) {
                return;
            }

            $order = null;
            if ($maybe_order instanceof WC_Order) {
                $order = $maybe_order;
            } elseif ($maybe_order_obj instanceof WC_Order) {
                $order = $maybe_order_obj;
            } elseif (is_numeric($maybe_order)) {
                $order = wc_get_order((int) $maybe_order);
            }

            if (!$order instanceof WC_Order) {
                return;
            }

            $options = $this->get_options();
            if (!$this->is_split_enabled($options)) {
                return;
            }

            if ($this->is_split_parent($order) || $this->is_split_child($order)) {
                return;
            }

            $line_items = $order->get_items('line_item');
            if (empty($line_items)) {
                return;
            }

            $unknown_policy = $this->get_unknown_policy($options);
            $groups = [];
            $unknown_items = [];

            foreach ($line_items as $item_id => $item) {
                if (!$item instanceof WC_Order_Item_Product) {
                    continue;
                }

                $location_slug = (string) $item->get_meta('_mulopimfwc_location');
                $location_slug = sanitize_title($location_slug);

                $term = false;
                if ($location_slug !== '' && $location_slug !== 'unknown' && $location_slug !== 'all-products' && function_exists('mulopimfwc_validate_location_slug')) {
                    $term = mulopimfwc_validate_location_slug($location_slug);
                }

                if ($term) {
                    $slug = $term->slug;
                    if (!isset($groups[$slug])) {
                        $groups[$slug] = [
                            'location_slug' => $slug,
                            'location_name' => $term->name,
                            'items' => [],
                        ];
                    }
                    $groups[$slug]['items'][$item_id] = $item;
                } else {
                    $unknown_items[$item_id] = $item;
                }
            }

            if (!empty($unknown_items) && $unknown_policy === 'block_checkout') {
                if (function_exists('wc_add_notice')) {
                    wc_add_notice(
                        __('Some items in your cart do not have a valid store location. Please select a valid location before checkout.', 'multi-location-product-and-inventory-management'),
                        'error'
                    );
                }
                throw new Exception(__('Some items in your cart do not have a valid store location. Please select a valid location before checkout.', 'multi-location-product-and-inventory-management'));
            }

            if (!empty($unknown_items) && $unknown_policy === 'unassigned_child') {
                $groups['__unassigned'] = [
                    'location_slug' => '',
                    'location_name' => __('Unassigned', 'multi-location-product-and-inventory-management'),
                    'items' => $unknown_items,
                ];
                $unknown_items = [];
            }

            if (count($groups) < 2) {
                return;
            }

            $this->is_splitting = true;

            try {
                $children_ids = [];
                $shipping_items = $order->get_items('shipping');
                $shipping_by_location = $this->group_shipping_items_by_location($shipping_items);
                $moved_shipping_ids = [];

                foreach ($groups as $group) {
                    $child = $this->create_child_order($order, (string) $group['location_slug']);
                    if (!$child) {
                        continue;
                    }

                    foreach ($group['items'] as $item) {
                        $this->copy_line_item($child, $item);
                    }

                    $location_slug = (string) $group['location_slug'];
                    if (!empty($shipping_by_location[$location_slug])) {
                        foreach ($shipping_by_location[$location_slug] as $shipping_item) {
                            $new_shipping_id = $this->copy_shipping_item($child, $shipping_item);
                            if ($new_shipping_id) {
                                $moved_shipping_ids[$shipping_item->get_id()] = true;
                            }
                        }
                    }

                    $this->apply_location_tax_classes($child, $location_slug);
                    $child->calculate_totals(true);
                    $child->save();

                    $children_ids[] = $child->get_id();

                    $child_note = sprintf(
                        __('Split from parent order: #%s', 'multi-location-product-and-inventory-management'),
                        $order->get_order_number()
                    );
                    $child->add_order_note($child_note);
                }

                if (!empty($children_ids)) {
                    $order->update_meta_data('_mulopimfwc_split_parent', 'yes');
                    $order->update_meta_data('_mulopimfwc_split_children', $children_ids);
                    $order->update_meta_data('_mulopimfwc_split_original_total', $order->get_total());

                    $keep_parent_items = apply_filters('mulopimfwc_split_keep_parent_items', false, $order, $children_ids);

                    if ($keep_parent_items) {
                        $order->update_meta_data('_mulopimfwc_split_parent_stock_exempt', 'yes');
                    } else {
                        foreach ($groups as $group) {
                            foreach ($group['items'] as $item_id => $item) {
                                $order->remove_item($item_id);
                            }
                        }

                        foreach (array_keys($moved_shipping_ids) as $shipping_item_id) {
                            $order->remove_item($shipping_item_id);
                        }
                    }

                    $order->save();

                    $child_numbers = [];
                    foreach ($children_ids as $child_id) {
                        $child_order = wc_get_order($child_id);
                        if ($child_order) {
                            $child_numbers[] = '#' . $child_order->get_order_number();
                        }
                    }
                    $note = sprintf(
                        __('Split into child orders: %s', 'multi-location-product-and-inventory-management'),
                        implode(', ', $child_numbers)
                    );
                    $order->add_order_note($note);
                }
            } finally {
                $this->is_splitting = false;
            }
        }

        private function create_child_order(WC_Order $parent, string $location_slug): ?WC_Order
        {
            $child = wc_create_order([
                'customer_id' => $parent->get_customer_id(),
            ]);

            if (!$child instanceof WC_Order) {
                return null;
            }

            $child->set_created_via('mulopimfwc_split');
            $child->set_currency($parent->get_currency());
            $child->set_payment_method($parent->get_payment_method());
            $child->set_payment_method_title($parent->get_payment_method_title());
            $child->set_customer_note($parent->get_customer_note());

            $child->set_address($parent->get_address('billing'), 'billing');
            $child->set_address($parent->get_address('shipping'), 'shipping');

            if ($location_slug !== '') {
                $child->update_meta_data('_store_location', $location_slug);
            } else {
                $child->delete_meta_data('_store_location');
            }

            $child->update_meta_data('_mulopimfwc_split_child', 'yes');
            $child->update_meta_data('_mulopimfwc_split_parent_id', $parent->get_id());

            $child->set_status($parent->get_status(), '', true);
            $child->save();

            return $child;
        }

        private function copy_line_item(WC_Order $order, WC_Order_Item_Product $item)
        {
            $new_item = new WC_Order_Item_Product();
            $new_item->set_name($item->get_name());
            $new_item->set_product_id($item->get_product_id());
            $new_item->set_variation_id($item->get_variation_id());
            $new_item->set_quantity($item->get_quantity());
            $new_item->set_subtotal($item->get_subtotal());
            $new_item->set_total($item->get_total());
            $new_item->set_subtotal_tax($item->get_subtotal_tax());
            $new_item->set_total_tax($item->get_total_tax());
            $new_item->set_taxes($item->get_taxes());

            $skip_meta = [
                '_qty',
                '_tax_class',
                '_line_subtotal',
                '_line_total',
                '_line_subtotal_tax',
                '_line_tax',
                '_line_tax_data',
                '_product_id',
                '_variation_id',
                '_reduced_stock',
            ];

            foreach ($item->get_meta_data() as $meta) {
                if (in_array($meta->key, $skip_meta, true)) {
                    continue;
                }
                $new_item->add_meta_data($meta->key, $meta->value, true);
            }

            $order->add_item($new_item);
        }

        private function copy_shipping_item(WC_Order $order, WC_Order_Item_Shipping $item): int
        {
            $new_item = new WC_Order_Item_Shipping();
            $new_item->set_method_title($item->get_method_title());
            $new_item->set_method_id($item->get_method_id());
            $new_item->set_instance_id($item->get_instance_id());
            $new_item->set_total($item->get_total());
            $new_item->set_taxes($item->get_taxes());

            foreach ($item->get_meta_data() as $meta) {
                $new_item->add_meta_data($meta->key, $meta->value, true);
            }

            return $order->add_item($new_item);
        }

        private function group_shipping_items_by_location(array $shipping_items): array
        {
            $grouped = [];
            foreach ($shipping_items as $item) {
                if (!$item instanceof WC_Order_Item_Shipping) {
                    continue;
                }

                $location_slug = (string) $item->get_meta('_mulopimfwc_package_location');
                $location_slug = sanitize_title($location_slug);
                if ($location_slug === '') {
                    continue;
                }

                if (!isset($grouped[$location_slug])) {
                    $grouped[$location_slug] = [];
                }
                $grouped[$location_slug][] = $item;
            }

            return $grouped;
        }

        private function apply_location_tax_classes(WC_Order $order, string $location_slug)
        {
            if ($location_slug === '') {
                return;
            }

            $options = $this->get_options();
            $taxes_enabled = function_exists('mulopimfwc_is_location_taxes_enabled')
                ? mulopimfwc_is_location_taxes_enabled($options)
                : (isset($options['enable_location_taxes']) && $options['enable_location_taxes'] === 'on' && mulopimfwc_premium_feature());

            if (!$taxes_enabled) {
                return;
            }

            $term = get_term_by('slug', $location_slug, 'mulopimfwc_store_location');
            if (!$term || is_wp_error($term)) {
                return;
            }

            $tax_class = (string) get_term_meta($term->term_id, 'tax_class', true);
            if ($tax_class === '') {
                return;
            }

            foreach ($order->get_items('line_item') as $item) {
                if (!$item instanceof WC_Order_Item_Product) {
                    continue;
                }

                $item->set_tax_class($tax_class);
            }
        }

        public function sync_children_on_payment_complete($order_id)
        {
            $order = wc_get_order($order_id);
            if (!$this->is_split_parent($order)) {
                return;
            }

            $children_ids = (array) $order->get_meta('_mulopimfwc_split_children');
            if (empty($children_ids)) {
                return;
            }

            $new_status = $order->get_status();
            $paid_date = $order->get_date_paid();

            foreach ($children_ids as $child_id) {
                $child = wc_get_order($child_id);
                if (!$child) {
                    continue;
                }

                if ($child->get_status() !== $new_status) {
                    $child->set_status($new_status, __('Paid via parent order.', 'multi-location-product-and-inventory-management'), true);
                }

                if ($paid_date) {
                    $child->set_date_paid($paid_date);
                }

                $child->save();
            }
        }

        public function sync_child_status_from_parent($order_id, $old_status, $new_status, $order)
        {
            $order = $order instanceof WC_Order ? $order : wc_get_order($order_id);
            if (!$this->is_split_parent($order)) {
                return;
            }

            $sync_statuses = apply_filters(
                'mulopimfwc_split_sync_statuses',
                ['processing', 'completed', 'on-hold', 'cancelled', 'refunded', 'failed']
            );

            if (!in_array($new_status, $sync_statuses, true)) {
                return;
            }

            $children_ids = (array) $order->get_meta('_mulopimfwc_split_children');
            if (empty($children_ids)) {
                return;
            }

            $paid_date = $order->get_date_paid();

            foreach ($children_ids as $child_id) {
                $child = wc_get_order($child_id);
                if (!$child) {
                    continue;
                }

                if ($child->get_status() !== $new_status) {
                    $child->set_status($new_status, sprintf(__('Parent order status changed to %s.', 'multi-location-product-and-inventory-management'), $new_status), true);
                }

                if (in_array($new_status, ['processing', 'completed'], true) && $paid_date) {
                    $child->set_date_paid($paid_date);
                }

                $child->save();
            }
        }

        public function maybe_disable_customer_email_for_child($recipient, $order)
        {
            $order = $order instanceof WC_Order ? $order : wc_get_order($order);
            if (!$this->is_split_child($order)) {
                return $recipient;
            }

            return '';
        }

        public function maybe_disable_admin_email_for_parent($recipient, $order)
        {
            $order = $order instanceof WC_Order ? $order : wc_get_order($order);
            if (!$this->is_split_parent($order)) {
                return $recipient;
            }

            $suppress = apply_filters('mulopimfwc_split_suppress_parent_admin_emails', true, $order);
            return $suppress ? '' : $recipient;
        }

        public function maybe_skip_parent_stock_reduction($can_reduce, $order)
        {
            $order = $order instanceof WC_Order ? $order : wc_get_order($order);
            if (!$this->is_split_parent($order)) {
                return $can_reduce;
            }

            if ($order->get_meta('_mulopimfwc_split_parent_stock_exempt') === 'yes') {
                return false;
            }

            return $can_reduce;
        }

        public function maybe_skip_parent_stock_restoration($can_restore, $order)
        {
            $order = $order instanceof WC_Order ? $order : wc_get_order($order);
            if (!$this->is_split_parent($order)) {
                return $can_restore;
            }

            if ($order->get_meta('_mulopimfwc_split_parent_stock_exempt') === 'yes') {
                return false;
            }

            return $can_restore;
        }
    }
}

add_action('plugins_loaded', function () {
    if (class_exists('WooCommerce')) {
        new MULOPIMFWC_Order_Split_By_Location();
    }
}, 20);
