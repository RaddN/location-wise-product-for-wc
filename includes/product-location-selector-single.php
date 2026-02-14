<?php

/**
 * Product Location Selector
 * 
 * Handles the display and management of store location selectors on WooCommerce product pages.
 * Supports multiple display positions and layouts with secure AJAX handling.
 * 
 * @package Multi_Location_Product_Inventory
 * @version 1.1.3.45
 * @author Your Name
 * @since 1.1.3.45
 */

if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

/**
 * Class MULOPIMFWC_Product_Location_Selector
 * 
 * Manages location selector display and functionality on product pages
 */
class MULOPIMFWC_Product_Location_Selector
{
    /**
     * Plugin version
     */
    const VERSION = '1.1.3.45';

    /**
     * Available display positions
     */
    const POSITIONS = [
        'after_title',
        'after_price',
        'before_add_to_cart',
        'after_add_to_cart',
        'product_meta'
    ];

    /**
     * Available layout types
     */
    const LAYOUTS = [
        'list',
        'buttons',
        'select'
    ];

    /**
     * Taxonomy name for store locations
     */
    const TAXONOMY = 'mulopimfwc_store_location';

    /**
     * Cookie name for storing selected location
     */
    const COOKIE_NAME = 'mulopimfwc_store_location';

    /**
     * AJAX action name
     */
    const AJAX_ACTION = 'mulopimfwc_change_product_location';

    /**
     * Nonce action name
     */
    const NONCE_ACTION = 'mulopimfwc_change_location_nonce';

    /**
     * @var bool Whether the selector has been displayed
     */
    private $is_displayed = false;

    /**
     * @var string Current display position setting
     */
    private $position = 'after_price';

    /**
     * @var array Plugin display options
     */
    private $options = [];

    /**
     * @var WC_Product Current product instance
     */
    private $current_product;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->init();
    }

    /**
     * Initialize the class
     */
    private function init(): void
    {
        add_action('wp', [$this, 'setup_display_hooks']);
        add_action('wp_ajax_' . self::AJAX_ACTION, [$this, 'handle_location_change']);
        add_action('wp_ajax_nopriv_' . self::AJAX_ACTION, [$this, 'handle_location_change']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * Enqueue necessary scripts and styles
     */
    public function enqueue_scripts(): void
    {
        if (!is_product()) {
            return;
        }

        if (function_exists('mulopimfwc_is_manual_assignment_strict_mode') && mulopimfwc_is_manual_assignment_strict_mode()) {
            return;
        }

        wp_enqueue_script(
            'mulopimfwc-location-selector',
            plugins_url('../assets/js/location-selector.js', __FILE__),
            ['jquery'],
            self::VERSION,
            true
        );

        // Targets for each position (Elementor + classic)
        $targets = [
            'after_title' => [
                'h1.product_title',
                'h1.entry-title',
                '.elementor-widget-woocommerce-product-title h1'
            ],
            'after_price' => [
                '.summary .price',
                '.elementor-widget-woocommerce-product-price .woocommerce-Price-amount',
                '.elementor-widget-woocommerce-product-price'
            ],
            'before_add_to_cart' => [
                'form.cart',
                '.elementor-widget-woocommerce-product-add-to-cart form.cart',
                '.elementor-add-to-cart',
                '.stock.out-of-stock'
            ],
            'after_add_to_cart' => [
                'form.cart',
                '.elementor-widget-woocommerce-product-add-to-cart form.cart',
                '.elementor-add-to-cart',
                '.stock.out-of-stock'
            ],
            'product_meta' => [
                '.product_meta',
                '.elementor-widget-woocommerce-product-meta .product_meta',
                '.elementor-widget-woocommerce-product-meta'
            ]
        ];

        // Get options for localized script
        global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);

        $allow_mixed_in_cart = function_exists('mulopimfwc_is_mixed_location_cart_enabled')
            ? mulopimfwc_is_mixed_location_cart_enabled($options)
            : (isset($options['allow_mixed_location_cart']) && function_exists('mulopimfwc_premium_feature') && mulopimfwc_premium_feature());
        
        $selector_config = [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mulopimfwc_change_location_nonce'),
            'cookieExpiryDays' => mulopimfwc_get_location_cookie_expiry_days(),
            'cookie_expiry' => mulopimfwc_get_location_cookie_expiry_days(),
            'cookieName' => mulopimfwc_get_location_cookie_name(),
            'cookieDomain' => apply_filters('mulopimfwc_location_cookie_domain', defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : ''),
            'cookiePath' => '/',
            'cookieSameSite' => 'Lax',
            'cookieSecure' => is_ssl(),
            'allow_mixed_in_cart' => $allow_mixed_in_cart ? 'on' : 'off',
            'location_switching_behavior' => isset($options['location_switching_behavior']) ? $options['location_switching_behavior'] : 'update_cart',
            'location_change_notification' => isset($options['location_change_notification']) || (isset($options['location_switching_behavior']) && $options['location_switching_behavior'] === 'prompt_user'),
            'location_notification_text' => function_exists('mulopimfwc_premium_feature') && mulopimfwc_premium_feature()
                ? mulopimfwc_get_text_value('location_notification_text')
                : __('Do you want to change the store location? Your cart will be updated.', 'multi-location-product-and-inventory-management'),
        ];

        wp_add_inline_script(
            'mulopimfwc-location-selector',
            'window.mulopimfwc_locationWiseProducts = window.mulopimfwc_locationWiseProducts || {}; Object.assign(window.mulopimfwc_locationWiseProducts, ' . wp_json_encode($selector_config) . ');',
            'before'
        );

        wp_add_inline_script(
            'mulopimfwc-location-selector',
            'window.MULOPIMFWC_LOC_SELECTOR = ' . wp_json_encode([
                'position' => $this->position,
                'targets'  => $targets,
                'cookieExpiryDays' => mulopimfwc_get_location_cookie_expiry_days(),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mulopimfwc_change_location_nonce'),
            ]) . ';',
            'before'
        );
    }



    /**
     * Setup display hooks based on current page and settings
     */
    public function setup_display_hooks(): void
    {
        if (!$this->should_display_selector()) {
            return;
        }

        $this->load_options();
        $this->setup_position_hooks();
        $this->setup_fallback_hooks();
    }

    /**
     * Check if selector should be displayed
     * 
     * @return bool
     */
    private function should_display_selector(): bool
    {
        return is_product() &&
            function_exists('wc_get_product') &&
            $this->is_location_display_enabled();
    }

    /**
     * Check if location display is enabled in settings
     * 
     * @return bool
     */
    private function is_location_display_enabled(): bool
    {
        if (function_exists('mulopimfwc_is_manual_assignment_strict_mode') && mulopimfwc_is_manual_assignment_strict_mode()) {
            return false;
        }

        global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);
        return isset($options['display_location_single_product']) &&
            $options['display_location_single_product'] === 'on';
    }

    /**
     * Load plugin options
     */
    private function load_options(): void
    {
        global $mulopimfwc_options;
        $this->options = is_array($mulopimfwc_options ?? null)
            ? $mulopimfwc_options
            : get_option('mulopimfwc_display_options', []);
        $this->position = $this->options['location_display_position'] ?? 'after_price';

        // Validate position
        if (!in_array($this->position, self::POSITIONS, true)) {
            $this->position = 'after_price';
        }
    }

    /**
     * Setup hooks based on position setting
     */
    private function setup_position_hooks(): void
    {
        $hooks = [
            'after_title' => ['woocommerce_template_single_add_to_cart', 10],
            'after_price' => ['woocommerce_template_single_add_to_cart', 12],
            'before_add_to_cart' => ['woocommerce_template_single_add_to_cart', 12],
            'after_add_to_cart' => ['woocommerce_template_single_add_to_cart', 32],
            'product_meta' => ['woocommerce_template_single_add_to_cart', 42]
        ];

        if (isset($hooks[$this->position])) {
            add_action(
                $hooks[$this->position][0],
                [$this, 'display_location_selector'],
                $hooks[$this->position][1]
            );
        }
    }

    /**
     * Setup fallback hooks for themes that don't cooperate
     */
    private function setup_fallback_hooks(): void
    {
        add_action('woocommerce_before_single_product', [$this, 'start_output_buffering'], 1);
        add_action('woocommerce_after_single_product', [$this, 'end_output_buffering'], 999);
    }

    /**
     * Display location selector if position matches and not already displayed
     */
    public function display_location_selector(): void
    {
        if ($this->is_displayed) {
            return;
        }

        $this->is_displayed = true;
        $this->render_location_selector();
    }

    /**
     * Start output buffering for fallback injection
     */
    public function start_output_buffering(): void
    {
        if (!$this->is_displayed) {
            ob_start();
        }
    }

    /**
     * End output buffering and inject selector if needed
     */
    public function end_output_buffering(): void
    {
        if (!$this->is_displayed && ob_get_level()) {
            $content = ob_get_clean();
            echo $this->inject_selector_in_content($content);
            $this->is_displayed = true;
        } elseif (ob_get_level()) {
            ob_end_flush();
        }
    }

    /**
     * Inject location selector into content using regex patterns
     * 
     * @param string $content The content to modify
     * @return string Modified content
     */
    private function inject_selector_in_content(string $content): string
    {
        $selector_html = $this->get_selector_html();

        if (empty($selector_html)) {
            return $content;
        }

        $patterns = $this->get_injection_patterns();

        foreach ($patterns[$this->position] ?? $patterns['after_price'] as $pattern) {
            if (preg_match($pattern, $content)) {
                $replacement = $this->should_inject_after() ? '$0' . $selector_html : $selector_html . '$0';
                $content = preg_replace($pattern, $replacement, $content, 1);
                break;
            }
        }

        // Fallback injection if no pattern matched
        if (strpos($content, 'mulopimfwc-product-location-selector') === false) {
            $content = $this->fallback_injection($content, $selector_html);
        }

        return $content;
    }

    /**
     * Get injection patterns for different positions
     * 
     * @return array
     */
    private function get_injection_patterns(): array
    {
        return [
            'after_title' => [
                '/<h1[^>]*class="[^"]*product_title[^"]*"[^>]*>.*?<\/h1>/i',
                '/<h1[^>]*class="[^"]*entry-title[^"]*"[^>]*>.*?<\/h1>/i',
                '/<div[^>]*class="[^"]*product_meta[^"]*"[^>]*>/i',
                '/<div[^>]*class="[^"]*elementor-add-to-cart[^"]*"[^>]*>/i',
            ],
            'after_price' => [
                '/<p[^>]*class="[^"]*price[^"]*"[^>]*>.*?<\/p>/i',
                '/<div[^>]*class="[^"]*price[^"]*"[^>]*>.*?<\/div>/i',
                '/<div[^>]*class="[^"]*product_meta[^"]*"[^>]*>/i',
                '/<div[^>]*class="[^"]*elementor-add-to-cart[^"]*"[^>]*>/i',
            ],
            'before_add_to_cart' => [
                '/<form[^>]*class="[^"]*cart[^"]*"[^>]*>/i',
                '/<div[^>]*class="[^"]*single_variation_wrap[^"]*"[^>]*>/i',
                '/<div[^>]*class="[^"]*elementor-add-to-cart[^"]*"[^>]*>/i',
                '/<div[^>]*class="[^"]*product_meta[^"]*"[^>]*>/i',
                '/<h1[^>]*class="[^"]*product_title[^"]*"[^>]*>.*?<\/h1>/i',
                '/<h1[^>]*class="[^"]*entry-title[^"]*"[^>]*>.*?<\/h1>/i',
                '/<div[^>]*class="[^"]*elementor-add-to-cart[^"]*"[^>]*>/i',
            ],
            'after_add_to_cart' => [
                '/<\/form>/i',
                '/<div[^>]*class="[^"]*elementor-add-to-cart[^"]*"[^>]*>/i',
                '/<div[^>]*class="[^"]*product_meta[^"]*"[^>]*>/i',
                '/<div[^>]*class="[^"]*elementor-add-to-cart[^"]*"[^>]*>/i',
            ],
            'product_meta' => [
                '/<div[^>]*class="[^"]*product_meta[^"]*"[^>]*>/i',
                '/<div[^>]*class="[^"]*elementor-add-to-cart[^"]*"[^>]*>/i',
                '/<div[^>]*class="[^"]*elementor-add-to-cart[^"]*"[^>]*>/i',
            ]
        ];
    }

    /**
     * Check if selector should be injected after the matched element
     * 
     * @return bool
     */
    private function should_inject_after(): bool
    {
        return in_array($this->position, ['after_title', 'after_price', 'product_meta'], true);
    }

    /**
     * Perform fallback injection
     * 
     * @param string $content
     * @param string $selector_html
     * @return string
     */
    private function fallback_injection(string $content, string $selector_html): string
    {
        $selector_html = '<div id="mulopimfwc-selector-portal" style="display:none">' . $selector_html . '</div>';
        if (preg_match('/<\/div>(?=[^<]*$)/i', $content)) {
            return preg_replace('/<\/div>(?=[^<]*$)/i', $selector_html . '</div>', $content, 1);
        }

        return $content . $selector_html;
    }

    /**
     * Get location selector HTML
     * 
     * @return string
     */
    private function get_selector_html(): string
    {
        ob_start();
        $this->render_location_selector();
        return ob_get_clean();
    }

    /**
     * Render the location selector
     * 
     * @param int $product_id Optional product ID (for shortcode support)
     * @param string $position Optional position override
     */
    public function render_location_selector(int $product_id = 0, string $position = '', array $atts = []): void
    {
        static $location_selector_added = [];

        if (function_exists('mulopimfwc_is_manual_assignment_strict_mode') && mulopimfwc_is_manual_assignment_strict_mode()) {
            return;
        }

        $this->current_product = $product_id > 0 ? wc_get_product($product_id) : wc_get_product();

        if (!$this->current_product || !is_object($this->current_product)) {
            return;
        }

        $product_id_key = $this->current_product->get_id();

        // Prevent duplicate selectors for the same product
        if (isset($location_selector_added[$product_id_key])) {
            return;
        }

        $locations = $this->get_available_locations($this->current_product);

        

        if (empty($locations)) {
            return;
        }

        $display_position = !empty($position) ? $position : $this->position;
        
        $this->render_selector_wrapper($locations, $display_position, $atts);

        $location_selector_added[$product_id_key] = true;
    }

    /**
     * Get available locations for a product
     * 
     * @param WC_Product $product
     * @return array
     */
    public function get_available_locations(WC_Product $product): array
    {
        // Get all active locations ordered by display_order
        $all_locations = mulopimfwc_get_frontend_locations();

        if (empty($all_locations) || is_wp_error($all_locations)) {
            return [];
        }

        $product_locations = get_the_terms($product->get_id(), self::TAXONOMY);
        $product_slugs = (!empty($product_locations) && !is_wp_error($product_locations))
            ? wp_list_pluck($product_locations, 'slug')
            : [];
        $locations = $all_locations;

        // If product has specific locations, filter to only those (and ensure they're active)
        if (!empty($product_locations) && !is_wp_error($product_locations)) {
            $locations = array_filter($locations, function ($location) use ($product_slugs) {
                return in_array($location->slug, $product_slugs, true);
            });
        }

        if ($this->should_hide_out_of_stock_locations()) {
            $locations = array_filter($locations, function ($location) use ($product, $product_slugs) {
                return !$this->is_product_out_of_stock_for_location($product, $location, $product_slugs);
            });
        }

        return $locations;
    }

    /**
     * Check if out-of-stock locations should be hidden
     *
     * @return bool
     */
    private function should_hide_out_of_stock_locations(): bool
    {
        if (empty($this->options)) {
            $this->load_options();
        }

        return isset($this->options['hide_out_of_stock_locations']) && $this->options['hide_out_of_stock_locations'] === 'on';
    }

    /**
     * Check if a product is out of stock for a specific location
     *
     * @param WC_Product $product
     * @param WP_Term|null $location_term
     * @return bool
     */
    private function is_product_out_of_stock_for_location(WC_Product $product, $location_term = null, array $product_slugs = null): bool
    {
        if (!$location_term) {
            return !$product->is_in_stock();
        }

        global $mulopimfwc_options;
        $enable_all_locations = function_exists('mulopimfwc_is_all_locations_enabled')
            ? (mulopimfwc_is_all_locations_enabled($mulopimfwc_options) ? 'on' : 'off')
            : 'off';
        $terms = $product_slugs;
        if ($terms === null) {
            $terms = wp_get_object_terms($product->get_id(), self::TAXONOMY, ['fields' => 'slugs']);
            if (is_wp_error($terms) || empty($terms)) {
                $terms = [];
            } else {
                $terms = array_map('rawurldecode', $terms);
            }
        }

        if ($enable_all_locations === 'on' && empty($terms)) {
            return !$product->is_in_stock();
        }

        $location_stock = get_post_meta($product->get_id(), '_location_stock_' . $location_term->term_id, true);
        $location_backorders = get_post_meta($product->get_id(), '_location_backorders_' . $location_term->term_id, true);

        if ($location_stock === '') {
            return !$product->is_in_stock();
        }

        return ($location_stock <= 0 && $location_backorders === 'off');
    }

    /**
     * Render the selector wrapper and content
     * 
     * @param array $locations
     * @param string $position
     */
    private function render_selector_wrapper(array $locations, string $position = '', $atts = []): void
    {
        $current_location = $this->get_current_location();
        $layout = isset($atts["layout"]) ? $atts["layout"] : $this->get_layout_type();
        $has_product_locations = $this->current_product_has_locations();
        $display_position = !empty($position) ? $position : $this->position;

        echo '<div class="mulopimfwc-product-location-selector-wrapper mulopimfwc-position-' . esc_attr($display_position) . '">';
        echo '<div class="mulopimfwc-product-location-selector" data-product-id="' . esc_attr($this->current_product->get_id()) . '" data-position="' . esc_attr($display_position) . '">';

        if ($has_product_locations && !empty($locations)) {
            $this->render_layout($layout, $locations, $current_location);
        }

        echo '</div>';
        echo '</div>';
    }

    /**
     * Render layout based on type
     * 
     * @param string $layout
     * @param array $locations
     * @param string $current_location
     */
    private function render_layout(string $layout, array $locations, string $current_location): void
    {
        switch ($layout) {
            case 'buttons':
                $this->render_buttons_layout($locations, $current_location);
                break;
            case 'select':
                $this->render_select_layout($locations, $current_location);
                break;
            case 'list':
            default:
                $this->render_list_layout($locations, $current_location);
                break;
        }
    }

    /**
     * Get current location from cookie
     * 
     * @return string
     */
    public function get_current_location(): string
    {
        return isset($_COOKIE[self::COOKIE_NAME]) ? sanitize_text_field($_COOKIE[self::COOKIE_NAME]) : '';
    }

    /**
     * Get layout type from options
     * 
     * @return string
     */
    private function get_layout_type(): string
    {
        $layout = $this->options['location_selector_layout'] ?? 'list';
        return in_array($layout, self::LAYOUTS, true) ? $layout : 'list';
    }

    /**
     * Check if current product has specific locations
     * 
     * @return bool
     */
    private function current_product_has_locations(): bool
    {
        $product_locations = get_the_terms($this->current_product->get_id(), self::TAXONOMY);
        return !empty($product_locations) && !is_wp_error($product_locations);
    }

    /**
     * Render list layout
     * 
     * @param array $locations
     * @param string $current_location
     * @param string $id_suffix Optional ID suffix for multiple selectors
     */
    private function render_list_layout(array $locations, string $current_location, string $id_suffix = ''): void
    {
        $label = $this->get_selector_label();
        $product_id = $this->current_product->get_id();
        
?>
        <div class="mulopimfwc-location-list">
            <div class="mulopimfwc-location-label">
                <?php echo esc_html($label); ?>
            </div>
            <div class="mulopimfwc-checkbox-list">
                <?php foreach ($locations as $location): ?>
                    <div class="mulopimfwc-checkbox-item">
                        <input
                            type="radio"
                            id="location-<?php echo esc_attr($location->term_id . $id_suffix); ?>"
                            name="mulopimfwc_location<?php echo esc_attr($id_suffix ? '_' . $product_id : ''); ?>"
                            class="mulopimfwc-location-checkbox"
                            value="<?php echo esc_attr(rawurldecode($location->slug)); ?>"
                            <?php checked($current_location, rawurldecode($location->slug)); ?>
                            data-location-id="<?php echo esc_attr($location->term_id); ?>" />
                        <label for="location-<?php echo esc_attr($location->term_id . $id_suffix); ?>">
                            <?php echo esc_html($location->name); ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php
    }

    /**
     * Render buttons layout
     * 
     * @param array $locations
     * @param string $current_location
     */
    private function render_buttons_layout(array $locations, string $current_location): void
    {
        $label = $this->get_selector_label();
    ?>
        <div class="mulopimfwc-location-buttons">
            <div class="mulopimfwc-location-label">
                <?php echo esc_html($label); ?>
            </div>
            <div class="mulopimfwc-buttons-container">
                <?php foreach ($locations as $location): ?>
                    <button
                        type="button"
                        class="mulopimfwc-location-button <?php echo $current_location === rawurldecode($location->slug) ? 'active button-primary btn-primary' : 'button-secondary btn-secondary plugincy-btn-secondary'; ?>"
                        data-location="<?php echo esc_attr(rawurldecode($location->slug)); ?>"
                        data-location-id="<?php echo esc_attr($location->term_id); ?>"
                        title="<?php echo esc_attr($location->description); ?>">
                        <?php echo esc_html($location->name); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    <?php
    }

    /**
     * Render select layout
     * 
     * @param array $locations
     * @param string $current_location
     */
    private function render_select_layout(array $locations, string $current_location): void
    {
        $label = $this->get_selector_label();
    ?>
        <div class="mulopimfwc-location-select">
            <div class="mulopimfwc-location-label">
                <?php echo esc_html($label); ?>
            </div>
            <select class="mulopimfwc-location-dropdown" data-current-location="<?php echo esc_attr($current_location); ?>">
                <option value=""><?php echo esc_html(mulopimfwc_get_text_value('text_selector_placeholder')); ?></option>
                <?php foreach ($locations as $location): ?>
                    <option
                        value="<?php echo esc_attr(rawurldecode($location->slug)); ?>"
                        data-location-id="<?php echo esc_attr($location->term_id); ?>"
                        <?php selected($current_location, rawurldecode($location->slug)); ?>>
                        <?php echo esc_html($location->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
<?php
    }

    /**
     * Get selector label with filter support
     * 
     * @return string
     */
    public function get_selector_label(): string
    {
        return apply_filters(
            'mulopimfwc_location_selector_label',
            mulopimfwc_get_text_value('text_selector_label')
        );
    }

    /**
     * Handle AJAX location change request
     */
    public function handle_location_change(): void
    {
        try {
            $this->validate_location_change_request();
            $location = $this->sanitize_location_input();
            $this->validate_location_exists($location);
            $this->set_location_cookie($location);
            $this->send_success_response($location);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Validate location change request
     * 
     * @throws Exception
     */
    private function validate_location_change_request(): void
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', self::NONCE_ACTION)) {
            throw new Exception(mulopimfwc_get_text_value('text_selector_error_security'));
        }
    }

    /**
     * Sanitize and validate location input
     * 
     * @return string
     * @throws Exception
     */
    private function sanitize_location_input(): string
    {
        $location = isset($_POST['location']) ? sanitize_text_field(rawurldecode($_POST['location'])) : '';

        if (empty($location)) {
            throw new Exception(mulopimfwc_get_text_value('text_selector_error_invalid'));
        }

        return $location;
    }

    /**
     * Validate that location exists
     * 
     * @param string $location
     * @throws Exception
     */
    private function validate_location_exists(string $location): void
    {
        if ($location === 'all-products') {
            return;
        }

        // OPTIMIZED: Use cached method instead of direct get_term_by
        $term = get_term_by('slug', $location, 'mulopimfwc_store_location');
        if (!$term) {
            throw new Exception(mulopimfwc_get_text_value('text_selector_error_not_found'));
        }
    }

    /**
     * Set location cookie with secure options
     * 
     * @param string $location
     */
    private function set_location_cookie(string $location): void
    {
        // Sanitize location slug
        $location = sanitize_title($location);
        
        if (empty($location)) {
            return;
        }

        // Get location term object for hooks
        // OPTIMIZED: Use cached method instead of direct get_term_by
        $location_obj = null;
        if ($location !== 'all-products') {
            $location_obj = get_term_by('slug', $location, 'mulopimfwc_store_location');
        }

        // Set cookie using standardized helper function
        mulopimfwc_set_location_cookie($location, self::COOKIE_NAME, $location_obj);

        // Fire action hook after location is selected
        do_action('mulopimfwc_location_selected', $location, $location_obj);
    }

    /**
     * Send success response with location data
     * 
     * @param string $location
     */
    private function send_success_response(string $location): void
    {
        $location_name = $this->get_location_display_name($location);

        wp_send_json_success([
            'message' => sprintf(
                // translators: %s: Name of the location that has been switched to.
                mulopimfwc_get_text_value('text_selector_changed_to'),
                $location_name
            ),
            'location' => $location,
            'location_name' => $location_name,
            'reload_required' => apply_filters('mulopimfwc_location_change_reload_required', true, $location)
        ]);
    }

    /**
     * Get display name for location
     * 
     * @param string $location
     * @return string
     */
    public function get_location_display_name(string $location): string
    {
        if ($location === 'all-products') {
            return mulopimfwc_get_text_value('text_selector_all_products');
        }

        // OPTIMIZED: Use cached method instead of direct get_term_by
        $term = get_term_by('slug', $location, 'mulopimfwc_store_location');
        return $term ? $term->name : $location;
    }
}

// Initialize the main class
$GLOBALS['mulopimfwc_location_selector'] = new MULOPIMFWC_Product_Location_Selector();

/**
 * Location Selector Shortcode Handler
 * 
 * Provides shortcode functionality by reusing the main class methods
 */
class MULOPIMFWC_Product_Location_Selector_Shortcode
{
    /**
     * Plugin version
     */
    const VERSION = '1.1.3.45';
    
    /**
     * @var array Track displayed shortcodes to prevent duplicates
     */
    private static $shortcode_displayed = [];

    /**
     * @var MULOPIMFWC_Product_Location_Selector Main selector instance
     */
    private $selector;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->selector = $GLOBALS['mulopimfwc_location_selector'];
        add_shortcode('mulopimfwc_location_selector', [$this, 'render_shortcode']);
        
        // Ensure scripts are enqueued when shortcode is present
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_shortcode_scripts'], 20);
    }
    
    /**
     * Enqueue scripts if shortcode might be present
     */
    public function maybe_enqueue_shortcode_scripts()
    {
        if (function_exists('mulopimfwc_is_manual_assignment_strict_mode') && mulopimfwc_is_manual_assignment_strict_mode()) {
            return;
        }

        global $post;
        
        // Check if shortcode is in post content
        $has_shortcode = false;
        
        if ($post && is_a($post, 'WP_Post')) {
            // Check main post content
            if (has_shortcode($post->post_content, 'mulopimfwc_location_selector')) {
                $has_shortcode = true;
            }
            
            // Check if using page builders (Elementor, Beaver Builder, etc.)
            if (!$has_shortcode && function_exists('get_post_meta')) {
                $elementor_data = get_post_meta($post->ID, '_elementor_data', true);
                if ($elementor_data && (is_string($elementor_data) || is_array($elementor_data))) {
                    $elementor_content = is_string($elementor_data) ? $elementor_data : wp_json_encode($elementor_data);
                    if (strpos($elementor_content, 'mulopimfwc_location_selector') !== false) {
                        $has_shortcode = true;
                    }
                }
            }
        }
        
        // Also check widgets (for sidebar/widget areas)
        if (!$has_shortcode) {
            $widget_areas = wp_get_sidebars_widgets();
            foreach ($widget_areas as $widgets) {
                if (is_array($widgets)) {
                    foreach ($widgets as $widget_id) {
                        // Check text widgets
                        if (strpos($widget_id, 'text-') === 0) {
                            $widget_num = (int) str_replace('text-', '', $widget_id);
                            $widget_data = get_option('widget_text');
                            if (isset($widget_data[$widget_num]['text']) && 
                                has_shortcode($widget_data[$widget_num]['text'], 'mulopimfwc_location_selector')) {
                                $has_shortcode = true;
                                break 2;
                            }
                        }
                        // Check custom HTML widgets
                        if (strpos($widget_id, 'custom_html-') === 0) {
                            $widget_num = (int) str_replace('custom_html-', '', $widget_id);
                            $widget_data = get_option('widget_custom_html');
                            if (isset($widget_data[$widget_num]['content']) && 
                                has_shortcode($widget_data[$widget_num]['content'], 'mulopimfwc_location_selector')) {
                                $has_shortcode = true;
                                break 2;
                            }
                        }
                    }
                }
            }
        }
        
        if ($has_shortcode) {
            $this->enqueue_shortcode_scripts();
        }
    }
    
    /**
     * Enqueue scripts for shortcode usage
     */
    private function enqueue_shortcode_scripts()
    {
        // Check if already enqueued
        if (wp_script_is('mulopimfwc-location-selector', 'enqueued')) {
            return;
        }
        
        wp_enqueue_script(
            'mulopimfwc-location-selector',
            plugins_url('../assets/js/location-selector.js', __FILE__),
            ['jquery'],
            self::VERSION,
            true
        );
        
        // Get options for localized script
        global $mulopimfwc_options;
            $options = is_array($mulopimfwc_options ?? null)
                ? $mulopimfwc_options
                : get_option('mulopimfwc_display_options', []);

        $allow_mixed_in_cart = function_exists('mulopimfwc_is_mixed_location_cart_enabled')
            ? mulopimfwc_is_mixed_location_cart_enabled($options)
            : (isset($options['allow_mixed_location_cart']) && function_exists('mulopimfwc_premium_feature') && mulopimfwc_premium_feature());
        
        // Inline merge to avoid overwriting existing frontend config.
        // Note: Using 'multi-location-product-and-inventory-management' nonce to match ajax_switch_location handler
        $selector_config = [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('multi-location-product-and-inventory-management'),
            'cookieExpiryDays' => mulopimfwc_get_location_cookie_expiry_days(),
            'cookie_expiry' => mulopimfwc_get_location_cookie_expiry_days(),
            'cookieName' => mulopimfwc_get_location_cookie_name(),
            'cookieDomain' => apply_filters('mulopimfwc_location_cookie_domain', defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : ''),
            'cookiePath' => '/',
            'cookieSameSite' => 'Lax',
            'cookieSecure' => is_ssl(),
            'allow_mixed_in_cart' => $allow_mixed_in_cart ? 'on' : 'off',
            'location_switching_behavior' => isset($options['location_switching_behavior']) ? $options['location_switching_behavior'] : 'update_cart',
            'location_change_notification' => isset($options['location_change_notification']) || (isset($options['location_switching_behavior']) && $options['location_switching_behavior'] === 'prompt_user'),
            'location_notification_text' => function_exists('mulopimfwc_premium_feature') && mulopimfwc_premium_feature()
                ? mulopimfwc_get_text_value('location_notification_text')
                : __('Do you want to change the store location? Your cart will be updated.', 'multi-location-product-and-inventory-management'),
        ];

        wp_add_inline_script(
            'mulopimfwc-location-selector',
            'window.mulopimfwc_locationWiseProducts = window.mulopimfwc_locationWiseProducts || {}; Object.assign(window.mulopimfwc_locationWiseProducts, ' . wp_json_encode($selector_config) . ');',
            'before'
        );
        
        wp_add_inline_script(
            'mulopimfwc-location-selector',
            'window.MULOPIMFWC_LOC_SELECTOR = ' . wp_json_encode([
                'position' => 'shortcode',
                'targets'  => [],
                'cookieExpiryDays' => mulopimfwc_get_location_cookie_expiry_days(),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('multi-location-product-and-inventory-management'),
            ]) . ';',
            'before'
        );
    }

    /**
     * Render location selector shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function render_shortcode($atts)
    {
        if (function_exists('mulopimfwc_is_manual_assignment_strict_mode') && mulopimfwc_is_manual_assignment_strict_mode()) {
            if (is_user_logged_in() && current_user_can('administrator')) {
                return '<p>When manual assignment strict mode is enabled, the location selector shortcode will not be displayed (Admin Only).</p>';
            }
            return '';
        }

        // Check if product_id was explicitly provided BEFORE shortcode_atts merges defaults
        $product_id_provided = isset($atts['product_id']) && !empty($atts['product_id']) && $atts['product_id'] != '0';
        
        $atts = shortcode_atts([
            'product_id' => 0,
            'layout' => 'list',
            'label' => '',
        ], $atts, 'mulopimfwc_location_selector');

        // If product_id was explicitly provided, use it; otherwise show all locations
        if ($product_id_provided) {
            $product_id = absint($atts['product_id']);
            $show_all_locations = false;
        } else {
            // No product_id provided - show all locations
            $product_id = 0;
            $show_all_locations = true;
        }
        
        // Create a unique key for tracking displayed shortcodes
        $display_key = $show_all_locations ? 'all_locations_' . md5(serialize($atts)) : $product_id;
        
        if (isset(self::$shortcode_displayed[$display_key])) {
            return '';
        }

        self::$shortcode_displayed[$display_key] = true;

        // If showing all locations, get all locations directly (filtered by is_active and ordered by display_order)
        if ($show_all_locations) {
            $locations = mulopimfwc_get_frontend_locations();

            if (empty($locations) || is_wp_error($locations)) {
                return '';
            }

            // Override label if provided
            if (!empty($atts['label'])) {
                add_filter('mulopimfwc_location_selector_label', function () use ($atts) {
                    return $atts['label'];
                });
            }

            ob_start();
            // Render selector with all locations (no product context)
            $this->render_all_locations_selector($locations, $atts);
            $output = ob_get_clean();

            // Remove label filter if it was added
            if (!empty($atts['label'])) {
                remove_all_filters('mulopimfwc_location_selector_label');
            }

            return $output;
        }

        // Original logic for product-specific locations
        $product = wc_get_product($product_id);

        if (!$product || !is_object($product)) {
            return '';
        }

        // Reuse main class method to get locations
        $locations = $this->selector->get_available_locations($product);

        if (empty($locations)) {
            return '';
        }

        // Override label if provided
        if (!empty($atts['label'])) {
            add_filter('mulopimfwc_location_selector_label', function () use ($atts) {
                return $atts['label'];
            });
        }

        ob_start();
        // Reuse main class render method with shortcode position
        $this->selector->render_location_selector($product_id, 'shortcode', $atts);
        $output = ob_get_clean();

        // Remove label filter if it was added
        if (!empty($atts['label'])) {
            remove_all_filters('mulopimfwc_location_selector_label');
        }

        return $output;
    }

    /**
     * Get product ID from context
     * 
     * @param int $provided_id
     * @return int
     */
    private function get_product_id($provided_id)
    {
        if ($provided_id > 0) {
            return absint($provided_id);
        }

        global $product;
        if ($product && is_a($product, 'WC_Product')) {
            return $product->get_id();
        }

        if (is_product() || in_the_loop()) {
            return get_the_ID();
        }

        return 0;
    }

    /**
     * Render selector for all locations (no product context)
     * 
     * @param array $locations
     * @param array $atts
     * @return void
     */
    private function render_all_locations_selector($locations, $atts)
    {
        $current_location = $this->selector->get_current_location();
        $layout = isset($atts['layout']) ? $atts['layout'] : 'list';
        $label = !empty($atts['label']) ? $atts['label'] : apply_filters('mulopimfwc_location_selector_label', mulopimfwc_get_text_value('text_selector_label'));

        echo '<div class="mulopimfwc-product-location-selector-wrapper mulopimfwc-position-shortcode">';
        echo '<div class="mulopimfwc-product-location-selector" data-product-id="0" data-position="shortcode">';

        if (!empty($locations)) {
            // Render layout based on type
            $this->render_all_locations_layout($layout, $locations, $current_location, $label);
        }

        echo '</div>';
        echo '</div>';
    }

    /**
     * Render layout for all locations
     * 
     * @param string $layout
     * @param array $locations
     * @param string $current_location
     * @param string $label
     * @return void
     */
    private function render_all_locations_layout($layout, $locations, $current_location, $label)
    {
        switch ($layout) {
            case 'buttons':
                $this->render_all_locations_buttons($locations, $current_location, $label);
                break;
            case 'select':
                $this->render_all_locations_select($locations, $current_location, $label);
                break;
            case 'list':
            default:
                $this->render_all_locations_list($locations, $current_location, $label);
                break;
        }
    }

    /**
     * Render list layout for all locations
     * 
     * @param array $locations
     * @param string $current_location
     * @param string $label
     * @return void
     */
    private function render_all_locations_list($locations, $current_location, $label)
    {
        ?>
        <div class="mulopimfwc-location-list">
            <div class="mulopimfwc-location-label">
                <?php echo esc_html($label); ?>
            </div>
            <div class="mulopimfwc-checkbox-list">
                <?php foreach ($locations as $location): ?>
                    <div class="mulopimfwc-checkbox-item">
                        <input
                            type="radio"
                            id="location-all-<?php echo esc_attr($location->term_id); ?>"
                            name="mulopimfwc_location_all"
                            class="mulopimfwc-location-checkbox"
                            value="<?php echo esc_attr(rawurldecode($location->slug)); ?>"
                            <?php checked($current_location, rawurldecode($location->slug)); ?>
                            data-location-id="<?php echo esc_attr($location->term_id); ?>" />
                        <label for="location-all-<?php echo esc_attr($location->term_id); ?>">
                            <?php echo esc_html($location->name); ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render buttons layout for all locations
     * 
     * @param array $locations
     * @param string $current_location
     * @param string $label
     * @return void
     */
    private function render_all_locations_buttons($locations, $current_location, $label)
    {
        ?>
        <div class="mulopimfwc-location-buttons">
            <div class="mulopimfwc-location-label">
                <?php echo esc_html($label); ?>
            </div>
            <div class="mulopimfwc-buttons-container">
                <?php foreach ($locations as $location): ?>
                    <button
                        type="button"
                        class="mulopimfwc-location-button <?php echo $current_location === rawurldecode($location->slug) ? 'active button-primary btn-primary' : 'button-secondary btn-secondary plugincy-btn-secondary'; ?>"
                        data-location="<?php echo esc_attr(rawurldecode($location->slug)); ?>"
                        data-location-id="<?php echo esc_attr($location->term_id); ?>"
                        title="<?php echo esc_attr($location->description); ?>">
                        <?php echo esc_html($location->name); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render select layout for all locations
     * 
     * @param array $locations
     * @param string $current_location
     * @param string $label
     * @return void
     */
    private function render_all_locations_select($locations, $current_location, $label)
    {
        ?>
        <div class="mulopimfwc-location-select">
            <div class="mulopimfwc-location-label">
                <?php echo esc_html($label); ?>
            </div>
            <select class="mulopimfwc-location-dropdown" data-current-location="<?php echo esc_attr($current_location); ?>">
                <option value=""><?php esc_html_e('Choose a location...', 'multi-location-product-and-inventory-management'); ?></option>
                <?php foreach ($locations as $location): ?>
                    <option
                        value="<?php echo esc_attr(rawurldecode($location->slug)); ?>"
                        data-location-id="<?php echo esc_attr($location->term_id); ?>"
                        <?php selected($current_location, rawurldecode($location->slug)); ?>>
                        <?php echo esc_html($location->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
    }
}

// Initialize shortcode handler
new MULOPIMFWC_Product_Location_Selector_Shortcode();
