<?php
/**
 * Cash on Pickup Payment Gateway
 * 
 * Allows customers to pay in cash when picking up their order from the store location
 * 
 * @package Multi Location Product & Inventory Management for WooCommerce
 * @since 1.1.7.12
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mulopimfwc_is_gateway_allowed_for_active_location')) {
    /**
     * Check whether a payment gateway is allowed for the currently selected location.
     *
     * Follows the same behavior as location payment filtering:
     * - If location-based payment filtering is disabled, allow.
     * - If no location (or all-products) is selected, allow.
     * - If location has no payment list configured, allow.
     * - Otherwise require gateway ID to be in location payment_methods.
     *
     * @param string $gateway_id Gateway ID.
     * @return bool
     */
    function mulopimfwc_is_gateway_allowed_for_active_location(string $gateway_id): bool
    {
        $gateway_id = sanitize_key($gateway_id);
        if ($gateway_id === '') {
            return false;
        }

        if (function_exists('mulopimfwc_is_manual_assignment_strict_mode') && mulopimfwc_is_manual_assignment_strict_mode()) {
            return true;
        }

        $location_payment_filter_active = function_exists('mulopimfwc_is_location_payment_methods_enabled')
            ? mulopimfwc_is_location_payment_methods_enabled()
            : false;

        if (!$location_payment_filter_active) {
            return true;
        }

        if (!function_exists('mulopimfwc_get_store_location_cookie')) {
            return true;
        }

        $candidate_locations = [];

        if (function_exists('mulopimfwc_get_effective_runtime_location_slug')) {
            $location_slug = sanitize_title(rawurldecode((string) mulopimfwc_get_effective_runtime_location_slug()));
        } else {
            $location_slug = sanitize_title(rawurldecode((string) mulopimfwc_get_store_location_cookie()));
        }

        if ($location_slug !== '' && $location_slug !== 'all-products' && $location_slug !== 'unknown') {
            $candidate_locations[] = $location_slug;
        }

        if (function_exists('mulopimfwc_get_runtime_cart_location_slugs')) {
            $candidate_locations = array_merge($candidate_locations, mulopimfwc_get_runtime_cart_location_slugs());
        }

        $candidate_locations = array_values(array_unique(array_filter(array_map(static function ($slug) {
            return sanitize_title(rawurldecode((string) $slug));
        }, $candidate_locations), static function ($slug) {
            return $slug !== '' && $slug !== 'all-products' && $slug !== 'unknown';
        })));

        if (empty($candidate_locations)) {
            return !$location_payment_filter_active;
        }

        $evaluated_location = false;

        foreach ($candidate_locations as $candidate_location) {
            if (function_exists('mulopimfwc_validate_location_slug')) {
                $term = mulopimfwc_validate_location_slug($candidate_location, false);
            } else {
                $term = get_term_by('slug', $candidate_location, 'mulopimfwc_store_location');
            }

            if (!$term || is_wp_error($term)) {
                continue;
            }

            $evaluated_location = true;

            $payments = (array) get_term_meta((int) $term->term_id, 'payment_methods', true);
            $payments = array_values(array_filter(array_map('sanitize_key', $payments), static function ($value) {
                return $value !== '';
            }));

            // Empty payment list means unrestricted for this location.
            if (empty($payments)) {
                continue;
            }

            if (!in_array($gateway_id, $payments, true)) {
                return false;
            }
        }

        if (!$evaluated_location && $location_payment_filter_active) {
            return false;
        }

        return true;
    }
}

// Only load payment gateway after WooCommerce is loaded
add_action('plugins_loaded', 'mulopimfwc_init_cash_on_pickup_gateway', 0);

if (!function_exists('mulopimfwc_init_cash_on_pickup_gateway')) {
    function mulopimfwc_init_cash_on_pickup_gateway() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce') || !class_exists('WC_Payment_Gateway')) {
            return;
        }

        /**
         * Cash on Pickup Payment Gateway Class
         */
        class WC_Gateway_Cash_On_Pickup extends WC_Payment_Gateway {

            /**
             * Instructions for the payment method.
             *
             * @var string
             */
            public $instructions;

            /**
             * Shipping methods this gateway is enabled for.
             *
             * @var array
             */
            public $enable_for_methods;

            /**
             * Constructor for the gateway.
             */
            public function __construct() {
                $this->id                 = 'cash_on_pickup';
                $this->icon               = MULTI_LOCATION_PLUGIN_URL . 'assets/images/plugincy-cash.svg';
                $this->has_fields         = false;
                $this->method_title       = __('Cash on Pickup', 'multi-location-product-and-inventory-management-pro');
                $this->method_description = __('Have your customers pay with cash when they pick up their order from your store location. Powered by Plugincy - Multi Location Product & Inventory Management.', 'multi-location-product-and-inventory-management-pro');
                
                // Support for WooCommerce Blocks checkout
                $this->supports = array(
                    'products',
                );

                // Load the settings.
                $this->init_form_fields();
                $this->init_settings();

                // Define user set variables.
                $this->title        = $this->get_option('title');
                $this->description  = $this->get_option('description');
                $this->instructions = $this->get_option('instructions', $this->description);
                $this->enable_for_methods = $this->get_option('enable_for_methods', array());

                // Actions.
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));

                // Customer Emails.
                add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
                
                // Keep gateway visible for checkout contexts, but respect location payment restrictions.
                add_filter('woocommerce_available_payment_gateways', array($this, 'ensure_gateway_available'), 100);
                
                // Support for WooCommerce Blocks checkout
                add_filter('woocommerce_rest_checkout_process_payment_with_context', array($this, 'process_rest_payment'), 10, 3);
            }
            
            /**
             * Get the icon for the gateway.
             * Only show icon in admin area, hide on frontend.
             *
             * @return string
             */
            public function get_icon() {
                // Only show icon in admin area
                if (is_admin()) {
                    return $this->icon ? '<img src="' . esc_url($this->icon) . '" alt="' . esc_attr($this->get_title()) . '" />' : '';
                }
                // Return empty string on frontend
                return '';
            }
            
            /**
             * Process payment for REST API (WooCommerce Blocks checkout)
             */
            public function process_rest_payment($result, $context) {
                if (isset($context->payment_method) && $context->payment_method === $this->id) {
                    if (isset($context->order) && is_object($context->order)) {
                        $order_id = $context->order->get_id();
                        return $this->process_payment($order_id);
                    }
                }
                return $result;
            }
            
            /**
             * Ensure gateway is available even if location filtering tries to remove it
             */
            public function ensure_gateway_available($gateways) {
                if (!mulopimfwc_is_gateway_allowed_for_active_location($this->id)) {
                    unset($gateways[$this->id]);
                    return $gateways;
                }

                // If gateway is enabled and not in the list, add it back
                // This helps checkout contexts where gateway list is loaded before shipping state is ready
                // Works for both classic checkout and blocks checkout
                if ($this->enabled === 'yes' && !isset($gateways[$this->id])) {
                    // Check if gateway should be available
                    if ($this->is_available()) {
                        // Add the gateway back - it's enabled so it should be available
                        $gateways[$this->id] = $this;
                    }
                }
                
                return $gateways;
            }
            
            /**
             * Check if this is a WooCommerce Blocks/AJAX request
             */
            private function is_blocks_or_ajax_request() {
                // Check for REST API request (WooCommerce Blocks)
                if (defined('REST_REQUEST') && REST_REQUEST) {
                    return true;
                }
                
                // Check for AJAX request
                if (defined('DOING_AJAX') && DOING_AJAX) {
                    return true;
                }
                
                // Check for WooCommerce Store API endpoints
                if (isset($_SERVER['REQUEST_URI'])) {
                    $request_uri = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']));
                    if (strpos($request_uri, '/wc/store/v') !== false || strpos($request_uri, '/wp-json/wc/') !== false) {
                        return true;
                    }
                }
                
                return false;
            }

            /**
             * Get the currently chosen shipping rate IDs from the session.
             *
             * @return array
             */
            private function get_selected_shipping_rate_ids() {
                if (!function_exists('WC') || !WC() || !WC()->session) {
                    return array();
                }

                $chosen_methods = (array) WC()->session->get('chosen_shipping_methods', array());
                $chosen_methods = array_values(array_filter(array_map('strval', $chosen_methods)));

                return $chosen_methods;
            }

            /**
             * Extract the shipping method ID from a rate ID such as pickup_location:0.
             *
             * @param string $rate_id
             * @return string
             */
            private function get_method_id_from_rate_id($rate_id) {
                $parts = explode(':', (string) $rate_id);
                return isset($parts[0]) ? (string) $parts[0] : '';
            }

            /**
             * Check whether the current checkout is using pickup for all chosen packages.
             *
             * @return bool
             */
            private function is_pickup_checkout_context() {
                $selected_rate_ids = $this->get_selected_shipping_rate_ids();
                if (empty($selected_rate_ids)) {
                    return false;
                }

                foreach ($selected_rate_ids as $rate_id) {
                    if (!in_array($this->get_method_id_from_rate_id($rate_id), array('local_pickup', 'pickup_location'), true)) {
                        return false;
                    }
                }

                return true;
            }

            /**
             * Check whether the selected shipping methods satisfy the gateway's method rules.
             *
             * @return bool
             */
            private function matches_enabled_shipping_methods() {
                if (empty($this->enable_for_methods) || in_array('all', $this->enable_for_methods, true)) {
                    return true;
                }

                $selected_rate_ids = $this->get_selected_shipping_rate_ids();
                if (empty($selected_rate_ids)) {
                    return false;
                }

                foreach ($selected_rate_ids as $rate_id) {
                    if (!in_array($this->get_method_id_from_rate_id($rate_id), $this->enable_for_methods, true)) {
                        return false;
                    }
                }

                return true;
            }
             

            /**
             * Initialise Gateway Settings Form Fields.
             */
            public function init_form_fields() {
                $shipping_methods = array(
                    'all' => __('All Shipping Methods', 'multi-location-product-and-inventory-management-pro'),
                );

                if (is_admin()) {
                    foreach (WC()->shipping()->get_shipping_methods() as $method) {
                        $shipping_methods[$method->id] = $method->get_method_title();
                    }
                }

                $this->form_fields = array(
                    'enabled' => array(
                        'title'   => __('Enable/Disable', 'multi-location-product-and-inventory-management-pro'),
                        'type'    => 'checkbox',
                        'label'   => __('Enable Cash on Pickup', 'multi-location-product-and-inventory-management-pro'),
                        'default' => 'no',
                    ),
                    'title' => array(
                        'title'       => __('Title', 'multi-location-product-and-inventory-management-pro'),
                        'type'        => 'text',
                        'description' => __('Payment method description that the customer will see on your checkout.', 'multi-location-product-and-inventory-management-pro'),
                        'default'     => __('Cash on Pickup', 'multi-location-product-and-inventory-management-pro'),
                        'desc_tip'    => true,
                    ),
                    'description' => array(
                        'title'       => __('Description', 'multi-location-product-and-inventory-management-pro'),
                        'type'        => 'textarea',
                        'description' => __('Payment method description that the customer will see on your checkout.', 'multi-location-product-and-inventory-management-pro'),
                        'default'     => __('Pay with cash when you pick up your order from the store location. Powered by <a href="https://plugincy.com/multi-location-product-and-inventory-management" target="_blank">Plugincy</a>.', 'multi-location-product-and-inventory-management-pro'),
                        'desc_tip'    => true,
                    ),
                    'instructions' => array(
                        'title'       => __('Instructions', 'multi-location-product-and-inventory-management-pro'),
                        'type'        => 'textarea',
                        'description' => __('Instructions that will be added to the thank you page and emails.', 'multi-location-product-and-inventory-management-pro'),
                        'default'     => __('Please have exact cash ready when you pick up your order from the store location. Powered by Plugincy - Multi Location Product & Inventory Management.', 'multi-location-product-and-inventory-management-pro'),
                        'desc_tip'    => true,
                    ),
                    'enable_for_methods' => array(
                        'title'             => __('Enable for shipping methods', 'multi-location-product-and-inventory-management-pro'),
                        'type'              => 'multiselect',
                        'class'             => 'wc-enhanced-select',
                        'css'               => 'width: 400px;',
                        'default'           => array('all'),
                        'description'       => __('Select "All Shipping Methods" to enable Cash on Pickup for all shipping methods, or select specific shipping methods.', 'multi-location-product-and-inventory-management-pro'),
                        'options'           => $shipping_methods,
                        'desc_tip'          => true,
                        'custom_attributes' => array(
                            'data-placeholder' => __('Select shipping methods', 'multi-location-product-and-inventory-management-pro'),
                        ),
                    ),
                );
            }

            /**
             * Check If The Gateway Is Available For Use.
             *
             * @return bool
             */
            public function is_available() {
                // First check parent availability (checks if enabled)
                if (!parent::is_available()) {
                    return false;
                }

                if (function_exists('mulopimfwc_is_gateway_allowed_for_active_location') && !mulopimfwc_is_gateway_allowed_for_active_location($this->id)) {
                    return false;
                }

                // Always available in admin
                if (is_admin()) {
                    return true;
                }

                // Cash on Pickup should only appear when checkout is actually using pickup.
                if (!$this->is_pickup_checkout_context()) {
                    return false;
                }

                return $this->matches_enabled_shipping_methods();
            }

            /**
             * Process the payment and return the result.
             *
             * @param int $order_id Order ID.
             * @return array
             */
            public function process_payment($order_id) {
                if (function_exists('mulopimfwc_is_gateway_allowed_for_active_location') && !mulopimfwc_is_gateway_allowed_for_active_location($this->id)) {
                    wc_add_notice(
                        __('Cash on Pickup is not available for the selected location.', 'multi-location-product-and-inventory-management-pro'),
                        'error'
                    );
                    return array(
                        'result' => 'failure',
                    );
                }

                $order = wc_get_order($order_id);

                if ($order->get_total() > 0) {
                    // Mark as processing (payment will be taken when customer picks up).
                    $order->update_status(apply_filters('woocommerce_cash_on_pickup_process_payment_order_status', 'on-hold', $order), __('Payment to be collected on pickup.', 'multi-location-product-and-inventory-management-pro'));
                } else {
                    $order->payment_complete();
                }

                // Remove cart.
                WC()->cart->empty_cart();

                // Return thankyou redirect.
                return array(
                    'result'   => 'success',
                    'redirect' => $this->get_return_url($order),
                );
            }

            /**
             * Output for the order received page.
             *
             * @param int $order_id Order ID.
             */
            public function thankyou_page($order_id) {
                if ($this->instructions) {
                    echo wp_kses_post(wpautop(wptexturize($this->instructions)));
                }
                // Add plugincy branding
                echo '<p style="margin-top: 15px; font-size: 12px; color: #666;"><em>' . esc_html__('Payment method powered by Plugincy', 'multi-location-product-and-inventory-management-pro') . '</em></p>';
            }

            /**
             * Add content to the WC emails.
             *
             * @param WC_Order $order Order object.
             * @param bool     $sent_to_admin Sent to admin.
             * @param bool     $plain_text Email format: plain text or HTML.
             */
            public function email_instructions($order, $sent_to_admin, $plain_text = false) {
                if ($this->instructions && !$sent_to_admin && $this->id === $order->get_payment_method() && $order->has_status('on-hold')) {
                    echo wp_kses_post(wpautop(wptexturize($this->instructions)) . PHP_EOL);
                }
            }
        }

        // Register the payment gateway - use priority 20 to ensure it runs after other filters
        add_filter('woocommerce_payment_gateways', 'mulopimfwc_add_cash_on_pickup_gateway', 20);
        if (!function_exists('mulopimfwc_add_cash_on_pickup_gateway')) {
            function mulopimfwc_add_cash_on_pickup_gateway($gateways) {
                // Only add if class exists
                if (class_exists('WC_Gateway_Cash_On_Pickup')) {
                    $gateways[] = 'WC_Gateway_Cash_On_Pickup';
                }
                return $gateways;
            }
        }
        
        // Ensure gateway is available for WooCommerce Blocks checkout
        add_filter('woocommerce_rest_checkout_process_payment_with_context', 'mulopimfwc_ensure_cash_on_pickup_for_blocks', 5, 3);
        if (!function_exists('mulopimfwc_ensure_cash_on_pickup_for_blocks')) {
            function mulopimfwc_ensure_cash_on_pickup_for_blocks($result, $context) {
                // This filter ensures the gateway can process payments via REST API (blocks)
                // The actual processing is handled by the gateway's process_rest_payment method
                return $result;
            }
        }
        
        // Ensure gateway appears in blocks payment methods list (AJAX/REST API)
        // Use very high priority to run after all other filters including location filtering
        add_filter('woocommerce_available_payment_gateways', 'mulopimfwc_ensure_cash_on_pickup_in_blocks', 999);
        if (!function_exists('mulopimfwc_ensure_cash_on_pickup_in_blocks')) {
            function mulopimfwc_ensure_cash_on_pickup_in_blocks($gateways) {
                if (!mulopimfwc_is_gateway_allowed_for_active_location('cash_on_pickup')) {
                    unset($gateways['cash_on_pickup']);
                    return $gateways;
                }

                // Check if this is a REST API or AJAX request (blocks checkout)
                $is_rest = defined('REST_REQUEST') && REST_REQUEST;
                $is_ajax = defined('DOING_AJAX') && DOING_AJAX;
                $is_store_api = false;
                
                if (isset($_SERVER['REQUEST_URI'])) {
                    $request_uri = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']));
                    $is_store_api = (
                        strpos($request_uri, '/wc/store/v') !== false ||
                        strpos($request_uri, '/wp-json/wc/') !== false ||
                        strpos($request_uri, 'wc-ajax') !== false
                    );
                }
                
                // Also check for WooCommerce Blocks checkout page
                $is_checkout_block = isset($_GET['wc-ajax']) || 
                                     (isset($_SERVER['HTTP_REFERER']) && strpos(sanitize_text_field(wp_unslash($_SERVER['HTTP_REFERER'])), 'checkout') !== false);
                
                if ($is_rest || $is_ajax || $is_store_api || $is_checkout_block) {
                    // Get the gateway instance
                    $payment_gateways = WC()->payment_gateways();
                    if ($payment_gateways) {
                        $all_gateways = $payment_gateways->payment_gateways();
                        $gateway = $all_gateways['cash_on_pickup'] ?? null;
                        
                        if ($gateway && $gateway->enabled === 'yes') {
                            if ($gateway->is_available()) {
                                $gateways['cash_on_pickup'] = $gateway;
                            } else {
                                unset($gateways['cash_on_pickup']);
                            }
                        }
                    }
                }
                return $gateways;
            }
        }

        add_action('woocommerce_blocks_payment_method_type_registration', 'mulopimfwc_register_cash_on_pickup_block_payment_method');
        if (!function_exists('mulopimfwc_register_cash_on_pickup_block_payment_method')) {
            function mulopimfwc_register_cash_on_pickup_block_payment_method($registry) {
                if (!class_exists('WC_Gateway_Cash_On_Pickup')) {
                    return;
                }
                if (!is_object($registry) || !method_exists($registry, 'register')) {
                    return;
                }
                if (!class_exists('Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType')) {
                    return;
                }
                if (method_exists($registry, 'is_registered') && $registry->is_registered('cash_on_pickup')) {
                    return;
                }
                if (!class_exists('MULOPIMFWC_Blocks_Cash_On_Pickup_Payment_Method', false)) {
                    class MULOPIMFWC_Blocks_Cash_On_Pickup_Payment_Method extends \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {
                        /**
                         * Payment method identifier (matches the gateway ID).
                         *
                         * @var string
                         */
                        protected $name = 'cash_on_pickup';

                        private const SCRIPT_HANDLE = 'mulopimfwc-blocks-cash-on-pickup-payment-method';

                        /**
                         * Initialize settings for this payment method integration.
                         */
                        public function initialize() {
                            $this->settings = get_option('woocommerce_cash_on_pickup_settings', []);
                        }

                        /**
                         * Returns true when the gateway is enabled.
                         *
                         * @return bool
                         */
                        public function is_active() {
                            $enabled = filter_var($this->get_setting('enabled', false), FILTER_VALIDATE_BOOLEAN);
                            if (!$enabled) {
                                return false;
                            }

                            if (function_exists('mulopimfwc_is_gateway_allowed_for_active_location')) {
                                return mulopimfwc_is_gateway_allowed_for_active_location($this->name);
                            }

                            return true;
                        }

                        /**
                         * Get the configured shipping methods that are allowed for this gateway.
                        *
                         * @return array
                         */
                        private function get_enable_for_methods() {
                            $enable_for_methods = $this->get_setting('enable_for_methods', []);
                            if ('' === $enable_for_methods) {
                                return [];
                            }

                            return $enable_for_methods;
                        }

                        /**
                         * Ensures the block registration script is registered with WP.
                         *
                         * @return string[]
                         */
                        public function get_payment_method_script_handles() {
                            $handle = self::SCRIPT_HANDLE;

                            if (function_exists('wp_register_script') && !wp_script_is($handle, 'registered')) {
                                wp_register_script(
                                    $handle,
                                    MULTI_LOCATION_PLUGIN_URL . 'assets/js/blocks/cash-on-pickup-payment-method.js',
                                    ['wp-element', 'wp-i18n', 'wc-blocks-vendors'],
                                    MULOPIMFWC_VERSION,
                                    true
                                );
                            }

                            return [$handle];
                        }

                        /**
                         * Block scripts are the same in the editor context.
                         *
                         * @return string[]
                         */
                        public function get_payment_method_script_handles_for_admin() {
                            return $this->get_payment_method_script_handles();
                        }

                        /**
                         * Return the data that will be available to the Blocks checkout scripts.
                         *
                         * @return array
                         */
                        public function get_payment_method_data() {
                            return [
                                'title'                    => $this->get_setting('title'),
                                'description'              => $this->get_setting('description'),
                                'enableForShippingMethods' => $this->get_enable_for_methods(),
                                'locationAllowed'          => function_exists('mulopimfwc_is_gateway_allowed_for_active_location')
                                    ? mulopimfwc_is_gateway_allowed_for_active_location($this->name)
                                    : true,
                                'supports'                 => $this->get_supported_features(),
                            ];
                        }
                    }
                }

                $registry->register(new MULOPIMFWC_Blocks_Cash_On_Pickup_Payment_Method());
            }
        }
    }
}

// Add a small badge beside the gateway title on the Payments settings page.
if (!function_exists('mulopimfwc_cop_should_show_badge')) {
    /**
     * Determine whether we are on a payments settings screen where the badge should render.
     *
     * @return bool
     */
    function mulopimfwc_cop_should_show_badge()
    {
        if (!is_admin()) {
            return false;
        }

        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';

        // New React payments screen.
        if ('wc-admin' === $page) {
            $path = isset($_GET['path']) ? sanitize_text_field(wp_unslash($_GET['path'])) : '';
            if (false !== strpos($path, '/payments/settings')) {
                return true;
            }
        }

        // Classic payments tab.
        if ('wc-settings' === $page) {
            $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
            if ('checkout' === $tab) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('mulopimfwc_render_cop_badge')) {
    /**
     * Inject badge markup for the Cash on Pickup gateway row.
     */
    function mulopimfwc_render_cop_badge()
    {
        if (!mulopimfwc_cop_should_show_badge()) {
            return;
        }

        $badge_logo = MULTI_LOCATION_PLUGIN_URL . 'assets/images/plugincy-badge.svg';
        $tooltip_id = 'mulopimfwc-cop-badge-tooltip';

        $badge_html = sprintf(
            '<span class="components-truncate components-text woocommerce-pill woocommerce-official-extension-badge mulopimfwc-cop-badge"><span class="woocommerce-official-extension-badge__container" tabindex="0" role="img" aria-label="%1$s" data-tooltip="%4$s"><img src="%2$s" alt="%1$s" /><span>%3$s</span></span></span>',
            esc_attr__('Plugincy add-on', 'multi-location-product-and-inventory-management-pro'),
            esc_url($badge_logo),
            esc_html__('Plugincy', 'multi-location-product-and-inventory-management-pro'),
            esc_attr__('Plugincy cash on pickup add-on', 'multi-location-product-and-inventory-management-pro')
        );

        ?>
        <script>
            (function () {
                var badgeHtml = <?php echo wp_json_encode($badge_html); ?>;
                var tooltipId = <?php echo wp_json_encode($tooltip_id); ?>;

                function insertBadge() {
                    var row = document.getElementById('cash_on_pickup');
                    if (!row) {
                        return;
                    }
                    var title = row.querySelector('.woocommerce-list__item-title');
                    if (!title || title.querySelector('.mulopimfwc-cop-badge')) {
                        return;
                    }
                    title.insertAdjacentHTML('beforeend', badgeHtml);
                    attachTooltip(title.querySelector('.mulopimfwc-cop-badge .woocommerce-official-extension-badge__container'));
                }

                function ensureTooltipStyles() {
                    if (document.getElementById(tooltipId + '-style')) {
                        return;
                    }
                    var style = document.createElement('style');
                    style.id = tooltipId + '-style';
                    style.textContent = ''
                        + '.mulopimfwc-cop-tooltip{position:absolute;z-index:1000;background:#1e1e1e;color:#fff;padding:8px 10px;border-radius:4px;box-shadow:0 4px 16px rgba(0,0,0,0.18);font-size:12px;line-height:1.4;max-width:240px;}'
                        + '.mulopimfwc-cop-tooltip:after{content:\"\";position:absolute;top:-6px;left:12px;border:6px solid transparent;border-bottom-color:#1e1e1e;}';
                    document.head.appendChild(style);
                }

                function attachTooltip(target) {
                    if (!target || target.dataset.mulopimfwcTooltipReady) {
                        return;
                    }
                    ensureTooltipStyles();

                    var tooltip;
                    var text = target.getAttribute('data-tooltip');

                    function showTooltip() {
                        if (!text) {
                            return;
                        }
                        if (!tooltip) {
                            tooltip = document.createElement('div');
                            tooltip.id = tooltipId;
                            tooltip.className = 'mulopimfwc-cop-tooltip';
                            tooltip.textContent = text;
                            document.body.appendChild(tooltip);
                        }
                        var rect = target.getBoundingClientRect();
                        tooltip.style.top = (window.scrollY + rect.top - tooltip.offsetHeight - 8) + 'px';
                        tooltip.style.left = (window.scrollX + rect.left) + 'px';
                        tooltip.style.display = 'block';
                    }

                    function hideTooltip() {
                        if (tooltip) {
                            tooltip.style.display = 'none';
                        }
                    }

                    target.addEventListener('mouseenter', showTooltip);
                    target.addEventListener('focus', showTooltip);
                    target.addEventListener('mouseleave', hideTooltip);
                    target.addEventListener('blur', hideTooltip);

                    target.dataset.mulopimfwcTooltipReady = '1';
                }

                if (typeof MutationObserver !== 'undefined') {
                    var observer = new MutationObserver(insertBadge);
                    observer.observe(document.body, {childList: true, subtree: true});
                    window.addEventListener('beforeunload', function () {
                        observer.disconnect();
                    });
                }

                insertBadge();
                document.addEventListener('DOMContentLoaded', insertBadge);
            })();
        </script>
        <?php
    }
}

add_action('admin_footer', 'mulopimfwc_render_cop_badge');
