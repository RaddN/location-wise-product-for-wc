<?php

/**
 * Plugin Name: Location Wise Products for WooCommerce
 * Plugin URI: https://plugincy.com/
 * Description: Filter WooCommerce products by store locations with a location selector for customers.
 * Version: 1.0.0
 * Author: plugincy
 * Author URI: https://plugincy.com/
 * Text Domain: location-wise-product
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 4.0
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) exit;

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function () {
        echo '<div class="error"><p>' . esc_html_e('Location Wise Products requires WooCommerce to be installed and active.', 'location-wise-product') . '</p></div>';
    });
    return;
}

class Plugincylwp_Location_Wise_Products
{
    public function __construct()
    {
        add_action('init', [$this, 'register_store_location_taxonomy']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('pre_get_posts', [$this, 'filter_products_by_location']);
        add_filter('woocommerce_shortcode_products_query', [$this, 'filter_shortcode_products']);
        add_filter('woocommerce_products_widget_query_args', [$this, 'filter_widget_products']);
        add_filter('woocommerce_related_products_args', [$this, 'filter_related_products']);

        add_action('wp_footer', [$this, 'location_selector_modal']);
        add_action('init', [$this, 'clear_cart_on_location_change']);

        add_shortcode('store_location_selector', [$this, 'location_selector_shortcode']);

        add_filter('the_title', [$this, 'add_location_to_product_title'], 10, 2);
        add_filter('woocommerce_product_title', [$this, 'add_location_to_wc_product_title'], 10, 2);

        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);

        add_filter('woocommerce_related_products', [$this, 'filter_related_products_by_location'], 10, 3);
        add_filter('woocommerce_recently_viewed_products_widget_query_args', [$this, 'filter_widget_products_by_location']);
        add_filter('woocommerce_cross_sells_products', [$this, 'filter_cross_sells_by_location'], 10, 1);
        add_filter('woocommerce_upsells_products', [$this, 'filter_upsells_by_location'], 10, 2);
        add_filter('woocommerce_blocks_product_grid_item_html', [$this, 'filter_product_blocks'], 10, 3);
        add_filter('woocommerce_json_search_found_products', [$this, 'filter_ajax_searched_products']);
        add_filter('woocommerce_rest_product_object_query', [$this, 'filter_rest_api_products'], 10, 2);
        add_filter('woocommerce_rest_prepare_product_object', [$this, 'modify_product_rest_response'], 10, 3);
        add_filter('woocommerce_cart_contents', [$this, 'filter_cart_contents'], 10, 1);
        add_action('template_redirect', [$this, 'filter_recently_viewed_products']);

        add_action('wp_ajax_clear_cart', [$this, 'clear_cart']);
        add_action('wp_ajax_nopriv_clear_cart', [$this, 'clear_cart']);

        add_action('wp_ajax_check_cart_products', [$this, 'check_cart_products']);
        add_action('wp_ajax_nopriv_check_cart_products', [$this, 'check_cart_products']);

        add_action('admin_enqueue_scripts', [$this, 'custom_admin_styles']);

        require_once plugin_dir_path(__FILE__) . 'includes/stock-price-backorder-manage.php';
    }

    public function register_store_location_taxonomy()
    {
        register_taxonomy('store_location', 'product', [
            'labels' => [
                'name' => __('locations', 'location-wise-product'),
                'singular_name' => __('Store Location', 'location-wise-product'),
                'search_items' => __('Search Store Locations', 'location-wise-product'),
                'all_items' => __('All Store Locations', 'location-wise-product'),
                'edit_item' => __('Edit Store Location', 'location-wise-product'),
                'update_item' => __('Update Store Location', 'location-wise-product'),
                'add_new_item' => __('Add New Store Location', 'location-wise-product'),
                'new_item_name' => __('New Store Location Name', 'location-wise-product'),
                'menu_name' => __('Store Locations', 'location-wise-product'),
            ],
            'hierarchical' => true,
            'show_ui' => true,
            // 'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'store-location'],
        ]);
    }

    public function enqueue_scripts()
    {
        wp_enqueue_style('location-wise-product', plugins_url('assets/css/style.css', __FILE__), [], '1.0.0');
        wp_enqueue_script('location-wise-product', plugins_url('assets/js/script.js', __FILE__), ['jquery'], '1.0.0', true);

        wp_localize_script('location-wise-product', 'locationWiseProducts', [
            'cartHasProducts' => !WC()->cart->is_empty(),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('location-wise-product')
        ]);
    }

    private function get_current_location()
    {
        return isset($_COOKIE['store_location']) ? sanitize_text_field(wp_unslash($_COOKIE['store_location'])) : '';
    }

    public function filter_shortcode_products($query_args)
    {
        $location = $this->get_current_location();
        if (!$location || $location === 'all-products') {
            return $query_args;
        }

        // if (!isset($query_args['tax_query'])) {
        //     $query_args['tax_query'] = [];
        // }

        $query_args['tax_query'][] = [
            'taxonomy' => 'store_location',
            'field' => 'slug',
            'terms' => $location,
        ];

        return $query_args;
    }

    public function filter_widget_products($query_args)
    {
        return $this->filter_shortcode_products($query_args);
    }

    public function filter_related_products($args)
    {
        return $this->filter_shortcode_products($args);
    }

    public function clear_cart_on_location_change()
    {
        if (!isset($_POST['plugincylwp_shortcode_selector_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['plugincylwp_shortcode_selector_nonce'])), 'plugincylwp_shortcode_selector')) {
            return;
        }
        if (isset($_POST['clear_cart_on_store_change']) && $_POST['clear_cart_on_store_change'] == '1') {
            if (function_exists('WC')) {
                WC()->cart->empty_cart();
                WC()->session->set('cart', []);
            }
        }
    }

    public function location_selector_modal()
    {
        $is_user_logged_in = is_user_logged_in();
        $current_user = wp_get_current_user();
        $is_admin_or_manager = in_array('administrator', $current_user->roles) || in_array('shop_manager', $current_user->roles);
        $selected_location = $this->get_current_location();
        $show_modal = !$is_user_logged_in && empty($selected_location) && !$is_admin_or_manager;

        $locations = get_terms([
            'taxonomy' => 'store_location',
            'hide_empty' => false,
        ]);

        include plugin_dir_path(__FILE__) . 'templates/modal.php';
    }

    public function location_selector_shortcode($atts)
    {
        $atts = shortcode_atts([
            'title' => __('Select Store Location', 'location-wise-product'),
            'show_title' => 'yes',
            'class' => '',
        ], $atts);

        $is_user_logged_in = is_user_logged_in();
        $current_user = wp_get_current_user();
        $is_admin_or_manager = in_array('administrator', $current_user->roles) || in_array('shop_manager', $current_user->roles);
        $selected_location = $this->get_current_location();

        $locations = get_terms([
            'taxonomy' => 'store_location',
            'hide_empty' => false,
        ]);

        ob_start();
        include plugin_dir_path(__FILE__) . 'templates/shortcode-selector.php';
        return ob_get_clean();
    }

    public function add_location_to_product_title($title, $post_id = 0)
    {
        if (!$post_id || get_post_type($post_id) !== 'product') {
            return $title;
        }
        return $this->get_title_with_location($title, $post_id);
    }

    public function add_location_to_wc_product_title($title, $product = null)
    {
        if (!$product) {
            return $title;
        }
        return $this->get_title_with_location($title, $product->get_id());
    }

    public function add_settings_page()
    {
        add_submenu_page(
            'woocommerce',
            __('Location Wise Products Settings', 'location-wise-product'),
            __('Store Locations Settings', 'location-wise-product'),
            'manage_options',
            'location-wise-product',
            [$this, 'settings_page_content']
        );
    }

    public function register_settings()
    {
        register_setting('location_wise_products_settings', 'lwp_display_options', array($this, 'sanitize_settings'));
        register_setting('location_wise_products_settings', 'lwp_filter_options', array($this, 'sanitize_settings')); // Changed from 'sanitize_filter_settings' to 'sanitize_settings'

        add_settings_section(
            'lwp_display_settings_section',
            __('Product Title Display Settings', 'location-wise-product'),
            [$this, 'settings_section_callback'],
            'location-wise-product'
        );

        add_settings_field(
            'lwp_display_format',
            __('Location Display Format', 'location-wise-product'),
            [$this, 'display_format_field_callback'],
            'location-wise-product',
            'lwp_display_settings_section'
        );

        add_settings_field(
            'lwp_separator',
            __('Title-Location Separator', 'location-wise-product'),
            [$this, 'separator_field_callback'],
            'location-wise-product',
            'lwp_display_settings_section'
        );

        add_settings_field(
            'lwp_enabled_pages',
            __('Show Location On', 'location-wise-product'),
            [$this, 'enabled_pages_field_callback'],
            'location-wise-product',
            'lwp_display_settings_section'
        );

        add_settings_section(
            'lwp_filter_settings_section',
            __('Location Filtering Settings', 'location-wise-product'),
            [$this, 'filter_settings_section_callback'],
            'location-wise-product'
        );

        add_settings_field(
            'lwp_strict_filtering',
            __('Strict Location Filtering', 'location-wise-product'),
            [$this, 'strict_filtering_field_callback'],
            'location-wise-product',
            'lwp_filter_settings_section'
        );

        add_settings_field(
            'lwp_filtered_sections',
            __('Apply Location Filtering To', 'location-wise-product'),
            [$this, 'filtered_sections_field_callback'],
            'location-wise-product',
            'lwp_filter_settings_section'
        );
    }

    public function sanitize_settings($input) {
        $sanitized = [];
        
        // Handle display options
        if (isset($input['display_format'])) {
            $sanitized['display_format'] = sanitize_text_field($input['display_format']);
        }
        
        if (isset($input['separator'])) {
            $sanitized['separator'] = sanitize_text_field($input['separator']);
        }
        
        // Handle enabled_pages
        $sanitized['enabled_pages'] = [];
        if (isset($input['enabled_pages']) && is_array($input['enabled_pages'])) {
            foreach ($input['enabled_pages'] as $page) {
                $sanitized['enabled_pages'][] = sanitize_text_field($page);
            }
        }
        
        // Handle strict_filtering option
        if (isset($input['strict_filtering'])) {
            $sanitized['strict_filtering'] = sanitize_text_field($input['strict_filtering']);
        }
        
        // Handle filtered_sections
        $sanitized['filtered_sections'] = [];
        if (isset($input['filtered_sections']) && is_array($input['filtered_sections'])) {
            foreach ($input['filtered_sections'] as $section) {
                $sanitized['filtered_sections'][] = sanitize_text_field($section);
            }
        }
        
        return $sanitized;
    }

    public function settings_section_callback()
    {
        echo '<p>' . esc_html_e('Configure how store locations appear with product titles.', 'location-wise-product') . '</p>';
    }

    public function display_format_field_callback()
    {
        $options = $this->get_display_options();
        $format = isset($options['display_format']) ? $options['display_format'] : 'append';
?>
        <select name="lwp_display_options[display_format]">
            <option value="append" <?php selected($format, 'append'); ?>><?php esc_html_e('Append to title (Title - Location)', 'location-wise-product'); ?></option>
            <option value="prepend" <?php selected($format, 'prepend'); ?>><?php esc_html_e('Prepend to title (Location - Title)', 'location-wise-product'); ?></option>
            <option value="brackets" <?php selected($format, 'brackets'); ?>><?php esc_html_e('In brackets (Title [Location])', 'location-wise-product'); ?></option>
        </select>
    <?php
    }

    public function separator_field_callback()
    {
        $options = $this->get_display_options();
        $separator = isset($options['separator']) ? $options['separator'] : ' - ';
    ?>
        <input type="text" name="lwp_display_options[separator]" value="<?php echo esc_attr($separator); ?>" class="regular-text">
        <p class="description"><?php esc_html_e('The character(s) used to separate the title and location.', 'location-wise-product'); ?></p>
    <?php
    }

    public function enabled_pages_field_callback()
    {
        $options = $this->get_display_options();
        $enabled_pages = isset($options['enabled_pages']) ? $options['enabled_pages'] : ['shop', 'single', 'cart'];
        $pages = [
            'shop' => __('Shop/Archive Pages', 'location-wise-product'),
            'single' => __('Single Product Pages', 'location-wise-product'),
            'cart' => __('Cart & Checkout', 'location-wise-product'),
            'related' => __('Related Products', 'location-wise-product'),
            'search' => __('Search Results', 'location-wise-product'),
            'widgets' => __('Widgets', 'location-wise-product')
        ];

        foreach ($pages as $value => $label) {
            $checked = in_array($value, $enabled_pages) ? 'checked' : '';
            echo "<label><input type='checkbox' name='lwp_display_options[enabled_pages][]' value='" . esc_attr($value) . "' " . checked($checked, true, false) . "> " . esc_html($label) . "</label><br>";
        }
    }

    public function settings_page_content()
    {
    ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('location_wise_products_settings');
                do_settings_sections('location-wise-product');
                submit_button();
                ?>
            </form>
            <form method="post" action="options.php">
                <?php wp_nonce_field('plugincylwp_general_settings', 'plugincylwp_general_settings_nonce'); ?>
                <?php
                settings_fields('location_stock_settings');
                do_settings_sections('location-stock-settings');
                submit_button();
                ?>
            </form>
        </div>
    <?php
    }

    private function get_display_options()
    {
        $defaults = [
            'display_format' => 'append',
            'separator' => ' - ',
            'enabled_pages' => ['shop', 'single', 'cart']
        ];
        $options = get_option('lwp_display_options', []);
        return wp_parse_args($options, $defaults);
    }

    private function get_title_with_location($title, $product_id)
    {
        $locations = get_the_terms($product_id, 'store_location');
        if (!$locations || is_wp_error($locations)) {
            return $title;
        }

        $options = $this->get_display_options();
        $enabled_pages = isset($options['enabled_pages']) ? $options['enabled_pages'] : ['shop', 'single', 'cart'];
        $should_display = false;

        if (in_array('shop', $enabled_pages) && (is_shop() || is_product_category() || is_product_tag() || is_post_type_archive('product'))) {
            $should_display = true;
        } elseif (in_array('single', $enabled_pages) && is_singular('product')) {
            $should_display = true;
        } elseif (in_array('cart', $enabled_pages) && (is_cart() || is_checkout())) {
            $should_display = true;
        } elseif (in_array('search', $enabled_pages) && is_search()) {
            $should_display = true;
        } elseif (in_array('widgets', $enabled_pages) && (is_active_widget(false, false, 'woocommerce_products', true) || is_active_widget(false, false, 'woocommerce_recent_products', true))) {
            $should_display = true;
        }

        if (!$should_display) {
            return $title;
        }

        $location_names = [];
        foreach ($locations as $location) {
            $location_names[] = $location->name;
        }

        $location_text = count($location_names) === 1 ? $location_names[0] : implode(', ', $location_names);
        $separator = isset($options['separator']) ? $options['separator'] : ' - ';
        $format = isset($options['display_format']) ? $options['display_format'] : 'append';

        switch ($format) {
            case 'prepend':
                return $location_text . $separator . $title;
            case 'brackets':
                return $title . ' [' . $location_text . ']';
            case 'append':
            default:
                return $title . $separator . $location_text;
        }
    }

    private function product_belongs_to_location($product_id)
    {
        $location = $this->get_current_location();

        if (!$location || $location === 'all-products') {
            return true;
        }

        $terms = wp_get_object_terms($product_id, 'store_location', ['fields' => 'slugs']);
        return (!is_wp_error($terms) && in_array($location, $terms));
    }

    public function filter_product_blocks($html, $data, $product)
    {
        if (!$this->product_belongs_to_location($product->get_id())) {
            return '';
        }
        return $html;
    }

    public function filter_ajax_searched_products($products)
    {
        $location = $this->get_current_location();

        if (!$location || $location === 'all-products') {
            return $products;
        }

        foreach ($products as $id => $product) {
            if (!$this->product_belongs_to_location($id)) {
                unset($products[$id]);
            }
        }

        return $products;
    }

    public function filter_rest_api_products($args, $request)
    {
        $location = $this->get_current_location();

        if (!$location || $location === 'all-products') {
            return $args;
        }

        // if (!isset($args['tax_query'])) {
        //     $args['tax_query'] = [];
        // }

        $args['tax_query'][] = [
            'taxonomy' => 'store_location',
            'field' => 'slug',
            'terms' => $location,
        ];

        return $args;
    }

    public function modify_product_rest_response($response, $product, $request)
    {
        if (!$this->product_belongs_to_location($product->get_id())) {
            $data = $response->get_data();
            $data['hidden_by_location'] = true;
            $response->set_data($data);
        }
        return $response;
    }

    public function filter_cart_contents($cart_contents)
    {
        $location = $this->get_current_location();

        if (!$location || $location === 'all-products') {
            return $cart_contents;
        }

        foreach ($cart_contents as $key => $item) {
            if (!$this->product_belongs_to_location($item['product_id'])) {
                $cart_contents[$key]['hidden_by_location'] = true;
            }
        }

        return $cart_contents;
    }

    public function filter_recently_viewed_products()
    {
        $location = $this->get_filtered_location('recently_viewed');

        if (!$location) {
            return;
        }

        $viewed_products = isset($_COOKIE['woocommerce_recently_viewed']) ? (array) explode('|', sanitize_text_field(wp_unslash($_COOKIE['woocommerce_recently_viewed']))) : [];

        if (empty($viewed_products)) {
            return;
        }

        $filtered_products = [];
        foreach ($viewed_products as $product_id) {
            if ($this->product_belongs_to_location($product_id)) {
                $filtered_products[] = $product_id;
            }
        }

        if (count($filtered_products) !== count($viewed_products)) {
            $filtered_cookie = implode('|', $filtered_products);
            wc_setcookie('woocommerce_recently_viewed', $filtered_cookie, time() + 60 * 60 * 24 * 30);
        }
    }

    public function filter_settings_section_callback()
    {
        echo '<p>' . esc_html_e('Configure how strictly products are filtered by location throughout your store.', 'location-wise-product') . '</p>';
    }

    public function strict_filtering_field_callback()
    {
        $options = $this->get_filter_options();
        $strict = isset($options['strict_filtering']) ? $options['strict_filtering'] : 'enabled';
    ?>
        <select name="lwp_filter_options[strict_filtering]">
            <option value="enabled" <?php selected($strict, 'enabled'); ?>><?php esc_html_e('Enabled (Only show products from selected location)', 'location-wise-product'); ?></option>
            <option value="disabled" <?php selected($strict, 'disabled'); ?>><?php esc_html_e('Disabled (Show all products regardless of location)', 'location-wise-product'); ?></option>
        </select>
        <p class="description"><?php esc_html_e('When enabled, users will only see products from their selected location. When disabled, all products will be visible.', 'location-wise-product'); ?></p>
    <?php
    }

    public function filtered_sections_field_callback()
    {
        $options = $this->get_filter_options();
        $sections = isset($options['filtered_sections']) ? $options['filtered_sections'] : [
            'shop',
            'search',
            'related',
            'recently_viewed',
            'cross_sells',
            'upsells'
        ];

        $all_sections = [
            'shop' => __('Main Shop & Category Pages', 'location-wise-product'),
            'search' => __('Search Results', 'location-wise-product'),
            'related' => __('Related Products', 'location-wise-product'),
            'recently_viewed' => __('Recently Viewed Products', 'location-wise-product'),
            'cross_sells' => __('Cross-Sells', 'location-wise-product'),
            'upsells' => __('Upsells', 'location-wise-product'),
            'widgets' => __('Product Widgets', 'location-wise-product'),
            'blocks' => __('Product Blocks (Gutenberg)', 'location-wise-product'),
            'rest_api' => __('REST API & AJAX Responses', 'location-wise-product'),
        ];

        foreach ($all_sections as $value => $label) {
            $checked = in_array($value, $sections) ? 'checked' : '';
            echo "<label><input type='checkbox' name='lwp_filter_options[filtered_sections][]' value='" . esc_attr($value) . "' " . checked($checked, true, false) . "> " . esc_html($label) . "</label><br>";
        }
    ?>
        <p class="description"><?php esc_html_e('Select which parts of your store should have location-based filtering applied.', 'location-wise-product'); ?></p>
<?php
    }

    private function get_filter_options()
    {
        $defaults = [
            'strict_filtering' => 'enabled',
            'filtered_sections' => ['shop', 'search', 'related', 'recently_viewed', 'cross_sells', 'upsells']
        ];
        $options = get_option('lwp_filter_options', []);
        return wp_parse_args($options, $defaults);
    }

    private function should_apply_filtering($section)
    {
        $options = $this->get_filter_options();
        $location = $this->get_current_location();
        if (!$location || $location === 'all-products') {
            return false;
        }

        if (isset($options['strict_filtering']) && $options['strict_filtering'] === 'disabled') {
            return false;
        }

        $filtered_sections = isset($options['filtered_sections']) ? $options['filtered_sections'] : [];
        return in_array($section, $filtered_sections);
    }

    private function get_filtered_location($section)
    {
        if (!$this->should_apply_filtering($section)) {
            return false;
        }
        return $this->get_current_location();
    }

    public function filter_products_by_location($query)
    {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }

        $section = '';
        if (is_shop() || is_product_category() || is_product_tag() || is_post_type_archive('product')) {
            $section = 'shop';
        } elseif (is_search()) {
            $section = 'search';
        } else {
            return;
        }

        $location = $this->get_filtered_location($section);

        if (!$location) {
            return;
        }

        $tax_query = (array) $query->get('tax_query');
        $tax_query[] = [
            'taxonomy' => 'store_location',
            'field' => 'slug',
            'terms' => $location,
        ];

        $query->set('tax_query', $tax_query);
    }

    public function filter_related_products_by_location($related_products, $product_id, $args)
    {
        $location = $this->get_filtered_location('related');

        if (!$location) {
            return $related_products;
        }

        return array_filter($related_products, [$this, 'product_belongs_to_location']);
    }

    public function filter_cross_sells_by_location($cross_sells)
    {
        $location = $this->get_filtered_location('cross_sells');

        if (!$location) {
            return $cross_sells;
        }

        return array_filter($cross_sells, [$this, 'product_belongs_to_location']);
    }

    public function filter_upsells_by_location($upsell_ids, $product_id)
    {
        $location = $this->get_filtered_location('upsells');

        if (!$location) {
            return $upsell_ids;
        }

        return array_filter($upsell_ids, [$this, 'product_belongs_to_location']);
    }

    public function filter_widget_products_by_location($query_args)
    {
        $location = $this->get_filtered_location('widgets');

        if (!$location) {
            return $query_args;
        }

        // if (!isset($query_args['tax_query'])) {
        //     $query_args['tax_query'] = [];
        // }

        $query_args['tax_query'][] = [
            'taxonomy' => 'store_location',
            'field' => 'slug',
            'terms' => $location,
        ];

        return $query_args;
    }

    function clear_cart()
    {
        // Check if WooCommerce is active
        if (class_exists('WooCommerce')) {
            WC()->cart->empty_cart(); // Clear the cart
            wp_send_json_success(); // Send a success response
        } else {
            wp_send_json_error(); // Send an error response
        }

        wp_die(); // Always call wp_die() at the end of AJAX functions
    }

    function check_cart_products()
    {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            wp_send_json_error('WooCommerce is not active.');
        }

        // Check if the cart has products
        $cart_has_products = !WC()->cart->is_empty();

        // Return response
        wp_send_json_success(array('cartHasProducts' => $cart_has_products));
    }
    function custom_admin_styles()
    {
        wp_enqueue_style('custom-admin-style', plugin_dir_url(__FILE__) . 'assets/css/admin-style.css', array(), "1.0.0");
    }
}

function Plugincylwp_location_wise_products_init()
{
    new Plugincylwp_Location_Wise_Products();
}

add_action('plugins_loaded', 'Plugincylwp_location_wise_products_init');
