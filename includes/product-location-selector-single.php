<?php

/**
 * Product Location Selector
 * 
 * Handles the display and management of store location selectors on WooCommerce product pages.
 * Supports multiple display positions and layouts with secure AJAX handling.
 * 
 * @package Multi_Location_Product_Inventory
 * @version 1.0.5.14
 * @author Your Name
 * @since 1.0.5.14
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
    const VERSION = '1.0.5.14';

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

        wp_enqueue_script(
            'mulopimfwc-location-selector',
            plugins_url('../assets/js/location-selector.js', __FILE__),
            ['jquery', 'wc-add-to-cart-variation'],
            self::VERSION,
            true
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
        $options = get_option('mulopimfwc_display_options', []);
        return isset($options['display_location_single_product']) &&
            $options['display_location_single_product'] === 'on';
    }

    /**
     * Load plugin options
     */
    private function load_options(): void
    {
        $this->options = get_option('mulopimfwc_display_options', []);
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
            ],
            'after_price' => [
                '/<p[^>]*class="[^"]*price[^"]*"[^>]*>.*?<\/p>/i',
                '/<div[^>]*class="[^"]*price[^"]*"[^>]*>.*?<\/div>/i',
            ],
            'before_add_to_cart' => [
                '/<form[^>]*class="[^"]*cart[^"]*"[^>]*>/i',
                '/<div[^>]*class="[^"]*single_variation_wrap[^"]*"[^>]*>/i',
            ],
            'after_add_to_cart' => [
                '/<\/form>/i',
            ],
            'product_meta' => [
                '/<div[^>]*class="[^"]*product_meta[^"]*"[^>]*>/i',
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
     */
    private function render_location_selector(): void
    {
        $this->current_product = wc_get_product();

        if (!$this->current_product || !is_object($this->current_product)) {
            return;
        }

        $locations = $this->get_available_locations();

        if (empty($locations)) {
            return;
        }

        $this->render_selector_wrapper($locations);
    }

    /**
     * Get available locations for the current product
     * 
     * @return array
     */
    private function get_available_locations(): array
    {
        $all_locations = get_terms([
            'taxonomy' => self::TAXONOMY,
            'hide_empty' => false,
        ]);

        if (empty($all_locations) || is_wp_error($all_locations)) {
            return [];
        }

        $product_locations = get_the_terms($this->current_product->get_id(), self::TAXONOMY);

        // If product has specific locations, filter to only those
        if (!empty($product_locations) && !is_wp_error($product_locations)) {
            $product_slugs = wp_list_pluck($product_locations, 'slug');
            return array_filter($all_locations, function ($location) use ($product_slugs) {
                return in_array($location->slug, $product_slugs, true);
            });
        }

        return $all_locations;
    }

    /**
     * Render the selector wrapper and content
     * 
     * @param array $locations
     */
    private function render_selector_wrapper(array $locations): void
    {
        $current_location = $this->get_current_location();
        $layout = $this->get_layout_type();
        $has_product_locations = $this->current_product_has_locations();

        echo '<div class="mulopimfwc-product-location-selector-wrapper mulopimfwc-position-' . esc_attr($this->position) . '">';
        echo '<div class="mulopimfwc-product-location-selector" data-product-id="' . esc_attr($this->current_product->get_id()) . '" data-position="' . esc_attr($this->position) . '">';

        switch ($layout) {
            case 'buttons':
                $this->render_buttons_layout($locations, $current_location, $has_product_locations);
                break;
            case 'select':
                $this->render_select_layout($locations, $current_location, $has_product_locations);
                break;
            case 'list':
            default:
                $this->render_list_layout($locations, $current_location, $has_product_locations);
                break;
        }

        echo '</div>';
        echo '</div>';
    }

    /**
     * Get current location from cookie
     * 
     * @return string
     */
    private function get_current_location(): string
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
     * @param bool $has_product_locations
     */
    private function render_list_layout(array $locations, string $current_location, bool $has_product_locations): void
    {
?>
        <div class="mulopimfwc-location-list">
            <div class="mulopimfwc-location-label">
                <?php echo esc_html($this->get_selector_label()); ?>
            </div>
            <div class="mulopimfwc-checkbox-list">
                <?php if ($has_product_locations && !empty($locations)): ?>
                    <?php foreach ($locations as $location): ?>
                        <div class="mulopimfwc-checkbox-item">
                            <input
                                type="radio"
                                id="location-<?php echo esc_attr($location->term_id); ?>"
                                name="mulopimfwc_location"
                                class="mulopimfwc-location-checkbox"
                                value="<?php echo esc_attr($location->slug); ?>"
                                <?php checked($current_location, $location->slug); ?>
                                data-location-id="<?php echo esc_attr($location->term_id); ?>" />
                            <label for="location-<?php echo esc_attr($location->term_id); ?>">
                                <?php echo esc_html($location->name); ?>
                                <?php if (!empty($location->description)): ?>
                                    <span class="mulopimfwc-location-description"><?php echo esc_html($location->description); ?></span>
                                <?php endif; ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="mulopimfwc-checkbox-item">
                        <input
                            type="radio"
                            id="location-all-products"
                            name="mulopimfwc_location"
                            class="mulopimfwc-location-checkbox"
                            value="all-products"
                            <?php checked($current_location, 'all-products'); ?> />
                        <label for="location-all-products">
                            <?php esc_html_e('All Products', 'multi-location-product-and-inventory-management'); ?>
                        </label>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php
    }

    /**
     * Render buttons layout
     * 
     * @param array $locations
     * @param string $current_location
     * @param bool $has_product_locations
     */
    private function render_buttons_layout(array $locations, string $current_location, bool $has_product_locations): void
    {
    ?>
        <div class="mulopimfwc-location-buttons">
            <div class="mulopimfwc-location-label">
                <?php echo esc_html($this->get_selector_label()); ?>
            </div>
            <div class="mulopimfwc-buttons-container">
                <?php if ($has_product_locations && !empty($locations)): ?>
                    <?php foreach ($locations as $location): ?>
                        <button
                            type="button"
                            class="mulopimfwc-location-button <?php echo $current_location === $location->slug ? 'active' : ''; ?>"
                            data-location="<?php echo esc_attr($location->slug); ?>"
                            data-location-id="<?php echo esc_attr($location->term_id); ?>"
                            title="<?php echo esc_attr($location->description); ?>">
                            <?php echo esc_html($location->name); ?>
                        </button>
                    <?php endforeach; ?>
                <?php else: ?>
                    <button
                        type="button"
                        class="mulopimfwc-location-button <?php echo $current_location === 'all-products' ? 'active' : ''; ?>"
                        data-location="all-products">
                        <?php esc_html_e('All Products', 'multi-location-product-and-inventory-management'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    <?php
    }

    /**
     * Render select layout
     * 
     * @param array $locations
     * @param string $current_location
     * @param bool $has_product_locations
     */
    private function render_select_layout(array $locations, string $current_location, bool $has_product_locations): void
    {
    ?>
        <div class="mulopimfwc-location-select">
            <div class="mulopimfwc-location-label">
                <?php echo esc_html($this->get_selector_label()); ?>
            </div>
            <select class="mulopimfwc-location-dropdown" data-current-location="<?php echo esc_attr($current_location); ?>">
                <?php if ($has_product_locations && !empty($locations)): ?>
                    <option value=""><?php esc_html_e('Choose a location...', 'multi-location-product-and-inventory-management'); ?></option>
                    <?php foreach ($locations as $location): ?>
                        <option
                            value="<?php echo esc_attr($location->slug); ?>"
                            data-location-id="<?php echo esc_attr($location->term_id); ?>"
                            <?php selected($current_location, $location->slug); ?>>
                            <?php echo esc_html($location->name); ?>
                        </option>
                    <?php endforeach; ?>
                <?php else: ?>
                    <option value="all-products" <?php selected($current_location, 'all-products'); ?>>
                        <?php esc_html_e('All Products', 'multi-location-product-and-inventory-management'); ?>
                    </option>
                <?php endif; ?>
            </select>
        </div>
<?php
    }

    /**
     * Get selector label with filter support
     * 
     * @return string
     */
    private function get_selector_label(): string
    {
        return apply_filters(
            'mulopimfwc_location_selector_label',
            __('Select Location:', 'multi-location-product-and-inventory-management')
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
            throw new Exception(__('Security check failed', 'multi-location-product-and-inventory-management'));
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
        $location = isset($_POST['location']) ? sanitize_text_field($_POST['location']) : '';

        if (empty($location)) {
            throw new Exception(__('Invalid location', 'multi-location-product-and-inventory-management'));
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

        $term = get_term_by('slug', $location, self::TAXONOMY);
        if (!$term) {
            throw new Exception(__('Location not found', 'multi-location-product-and-inventory-management'));
        }
    }

    /**
     * Set location cookie with secure options
     * 
     * @param string $location
     */
    private function set_location_cookie(string $location): void
    {
        $cookie_options = [
            'expires' => time() + (86400 * 30), // 30 days
            'path' => '/',
            'domain' => '',
            'secure' => is_ssl(),
            'httponly' => false, // Need JS access
            'samesite' => 'Lax'
        ];

        if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
            setcookie(self::COOKIE_NAME, $location, $cookie_options);
        } else {
            setcookie(
                self::COOKIE_NAME,
                $location,
                $cookie_options['expires'],
                $cookie_options['path'],
                $cookie_options['domain'],
                $cookie_options['secure'],
                $cookie_options['httponly']
            );
        }
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
                __('Location changed to: %s', 'multi-location-product-and-inventory-management'),
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
    private function get_location_display_name(string $location): string
    {
        if ($location === 'all-products') {
            return __('All Products', 'multi-location-product-and-inventory-management');
        }

        $term = get_term_by('slug', $location, self::TAXONOMY);
        return $term ? $term->name : $location;
    }
}

// Initialize the class
new MULOPIMFWC_Product_Location_Selector();
