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
class mulopimfwc_Product_Location_Table extends WP_List_Table
{
    /**
     * Flag to track if we're ordering by location
     */
    private $ordering_by_location = false;

    /**
     * Current table view mode.
     *
     * @var string
     */
    private $view_mode = 'modern';

    /**
     * Cached classic editor context indexed by product ID.
     *
     * @var array<int, array|null>
     */
    private $classic_editor_context_cache = [];

    /**
     * Cached all-locations template for per-row selected-state mapping.
     *
     * @var array<int, array<string, mixed>>|null
     */
    private $all_location_data_template = null;

    /**
     * Cache resolved currency settings by location term id.
     *
     * @var array<string, array{currency: string, position: string}>
     */
    private $location_currency_settings_cache = [];

    /**
     * Cache resolved currency symbols by location key.
     *
     * @var array<string, string>
     */
    private $location_currency_symbol_cache = [];

    /**
     * Constructor
     */
    public function __construct($view_mode = 'modern')
    {
        $this->view_mode = in_array($view_mode, ['modern', 'classic'], true) ? $view_mode : 'modern';

        parent::__construct([
            'singular' => 'product',
            'plural'   => 'products',
            'ajax'     => false,
        ]);
    }

    public function get_view_mode()
    {
        return $this->view_mode;
    }

    private function is_classic_mode()
    {
        return $this->view_mode === 'classic';
    }

    private function is_location_manager()
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();
        return in_array('mulopimfwc_location_manager', $user->roles, true);
    }

    private function user_can_manage_products()
    {
        if (class_exists('MULOPIMFWC_Location_Managers')) {
            return MULOPIMFWC_Location_Managers::user_has_capability('manage_products');
        }

        return current_user_can('manage_woocommerce');
    }

    private function user_can_view_products()
    {
        if (class_exists('MULOPIMFWC_Location_Managers')) {
            return MULOPIMFWC_Location_Managers::user_has_capability('view_products');
        }

        return current_user_can('manage_woocommerce');
    }

    private function user_has_all_products()
    {
        if (class_exists('MULOPIMFWC_Location_Managers')) {
            return MULOPIMFWC_Location_Managers::user_has_capability('all_products');
        }

        return current_user_can('manage_woocommerce');
    }

    private function should_show_default_details()
    {
        return !$this->is_location_manager() || $this->user_has_all_products();
    }

    private function filter_location_terms_for_user($location_terms)
    {
        if (!$this->is_location_manager() || $this->user_has_all_products()) {
            return $location_terms;
        }

        if (!is_array($location_terms)) {
            return [];
        }

        $assigned_locations = [];
        if (class_exists('MULOPIMFWC_Location_Managers')) {
            $assigned_locations = MULOPIMFWC_Location_Managers::get_user_assigned_locations();
        }

        if (!is_array($assigned_locations) || empty($assigned_locations)) {
            return [];
        }

        return array_values(array_filter($location_terms, function ($term) use ($assigned_locations) {
            if (!is_object($term) || !isset($term->slug)) {
                return false;
            }

            return in_array(rawurldecode($term->slug), $assigned_locations, true);
        }));
    }

    private function get_product_term_list($product_id, $taxonomy)
    {
        if (empty($taxonomy) || !taxonomy_exists($taxonomy)) {
            return '';
        }

        $terms = get_the_terms($product_id, $taxonomy);
        if (is_wp_error($terms) || empty($terms)) {
            return '';
        }

        $names = wp_list_pluck($terms, 'name');
        return implode(', ', $names);
    }

    private function get_stock_central_post_statuses()
    {
        $post_statuses = get_post_stati(['internal' => false], 'names');
        if (!is_array($post_statuses) || empty($post_statuses)) {
            return ['publish', 'future', 'draft', 'pending', 'private'];
        }

        $post_statuses = array_values(array_diff($post_statuses, ['trash', 'auto-draft', 'inherit']));
        if (empty($post_statuses)) {
            return ['publish', 'future', 'draft', 'pending', 'private'];
        }

        return $post_statuses;
    }

    private function get_product_status_badge($post_status)
    {
        $status = sanitize_key((string) $post_status);
        if ($status === '' || $status === 'publish') {
            return '';
        }

        $status_object = get_post_status_object($status);
        $status_label = ($status_object && !empty($status_object->label))
            ? $status_object->label
            : ucwords(str_replace(['-', '_'], ' ', $status));

        return '<span class="mulopimfwc-product-status mulopimfwc-product-status-' . esc_attr($status) . '">' . esc_html($status_label) . '</span>';
    }

    private function get_classic_locked_columns()
    {
        return [
            'classic_manage_stock',
            'classic_default',
            'classic_location_wise',
            'classic_purchase',
        ];
    }

    /**
     * Get table columns
     *
     * @return array
     */
    public function get_columns()
    {
        if ($this->is_classic_mode() && $this->user_can_manage_products()) {
            return [
                'cb' => '<input type="checkbox" />',
                'image' => __('Image', 'multi-location-product-and-inventory-management'),
                'title' => __('Product', 'multi-location-product-and-inventory-management'),
                'classic_manage_stock' => __('Manage Stock', 'multi-location-product-and-inventory-management'),
                'classic_default' => __('Default', 'multi-location-product-and-inventory-management'),
                'classic_location_wise' => __('Location Wise', 'multi-location-product-and-inventory-management'),
                'classic_purchase' => __('Purchase Info', 'multi-location-product-and-inventory-management'),
                'actions' => __('Actions', 'multi-location-product-and-inventory-management'),
            ];
        }

        $columns = [
            'image'         => __('Image', 'multi-location-product-and-inventory-management'),
            'title'         => __('Product', 'multi-location-product-and-inventory-management'),
            'stock'         => __('Stock by Location', 'multi-location-product-and-inventory-management'),
            'price'         => __('Price by Location', 'multi-location-product-and-inventory-management'),
            'sku'           => __('SKU', 'multi-location-product-and-inventory-management'),
            'type'          => __('Type', 'multi-location-product-and-inventory-management'),
            'categories'    => __('Categories', 'multi-location-product-and-inventory-management'),
            'tags'          => __('Tags', 'multi-location-product-and-inventory-management'),
            'brands'        => __('Brands', 'multi-location-product-and-inventory-management'),
            'short_description' => __('Short Description', 'multi-location-product-and-inventory-management'),
            'description'   => __('Description', 'multi-location-product-and-inventory-management'),
            'featured'      => __('Featured', 'multi-location-product-and-inventory-management'),
            'dimensions'    => __('Dimensions', 'multi-location-product-and-inventory-management'),
            'date'          => __('Date', 'multi-location-product-and-inventory-management'),
        ];

        $show_purchase_profit = !($this->is_location_manager() && !$this->user_can_manage_products());
        if ($show_purchase_profit) {
            $columns['purchase_price'] = __('Purchase Info', 'multi-location-product-and-inventory-management');
            $columns['gross_profit'] = __('Gross Profit', 'multi-location-product-and-inventory-management');
        }

        $show_actions = $this->user_can_manage_products() || ($this->is_location_manager() && $this->user_can_view_products());
        if ($show_actions) {
            $columns['actions'] = __('Actions', 'multi-location-product-and-inventory-management');
        }

        if ($this->user_can_manage_products()) {
            $columns = array_merge(['cb' => '<input type="checkbox" />'], $columns);
        }

        return $columns;
    }

    /**
     * Get bulk actions
     *
     * @return array
     */
    protected function get_bulk_actions()
    {
        if (!$this->user_can_manage_products()) {
            return [];
        }

        return [
            'bulk_assign_location' => __('Assign to Location', 'multi-location-product-and-inventory-management'),
            'bulk_remove_location' => __('Remove from Location', 'multi-location-product-and-inventory-management'),
            'trash' => __('Move to Trash', 'multi-location-product-and-inventory-management'),
        ];
    }

    /**
     * Default column rendering
     *
     * @param array $item Item data
     * @param string $column_name Column name
     * @return string
     */
    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'title':
                return $item['title'];
            case 'classic_manage_stock':
                return $this->get_classic_manage_stock_display($item);
            case 'classic_default':
                return $this->get_classic_default_display($item);
            case 'classic_location_wise':
                return $this->get_classic_location_wise_display($item);
            case 'classic_purchase':
                return $this->get_classic_purchase_display($item);
            case 'stock':
                return $this->get_location_stock_display($item);
            case 'price':
                return $this->get_location_price_display($item);
            case 'sku':
                $sku = $item['sku'] ?? '';
                return $sku !== '' ? esc_html($sku) : '-';
            case 'type':
                $type_label = $item['type_label'] ?? '';
                if ($type_label === '' && !empty($item['type'])) {
                    $type_label = ucfirst($item['type']);
                }
                return $type_label !== '' ? esc_html($type_label) : '-';
            case 'categories':
                $categories = $item['categories'] ?? '';
                return $categories !== '' ? esc_html($categories) : '-';
            case 'tags':
                $tags = $item['tags'] ?? '';
                return $tags !== '' ? esc_html($tags) : '-';
            case 'brands':
                $brands = $item['brands'] ?? '';
                return $brands !== '' ? esc_html($brands) : '-';
            case 'short_description':
                $short_description = $item['short_description'] ?? '';
                return $short_description !== '' ? esc_html($short_description) : '-';
            case 'description':
                $description = $item['description'] ?? '';
                return $description !== '' ? esc_html($description) : '-';
            case 'featured':
                $featured = !empty($item['featured']);
                return $featured ? esc_html__('Yes', 'multi-location-product-and-inventory-management') : esc_html__('No', 'multi-location-product-and-inventory-management');
            case 'dimensions':
                $dimensions = $item['dimensions'] ?? '';
                return $dimensions !== '' ? wp_kses_post($dimensions) : '-';
            case 'date':
                $date = $item['date'] ?? '';
                return $date !== '' ? esc_html($date) : '-';
            case 'purchase_price':
                return $this->get_purchase_price_display($item);
            case 'gross_profit':
                return $this->get_gross_profit_display($item);
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
    public function column_cb($item)
    {
        if (!$this->user_can_manage_products()) {
            return '';
        }

        return sprintf('<input type="checkbox" name="product[]" value="%s" />', $item['id']);
    }

    public function single_row($item)
    {
        if (!$this->is_classic_mode() || !$this->user_can_manage_products()) {
            parent::single_row($item);
            return;
        }

        $original_location_ids = [];
        if (isset($item['quick_edit_data']['locations']) && is_array($item['quick_edit_data']['locations'])) {
            $original_location_ids = array_map('intval', wp_list_pluck($item['quick_edit_data']['locations'], 'id'));
        }

        echo '<tr class="mulopimfwc-classic-product-row" data-product-id="' . esc_attr((int) $item['id']) . '" data-product-type="' . esc_attr(isset($item['type']) ? (string) $item['type'] : '') . '" data-original-location-ids="' . esc_attr(implode(',', $original_location_ids)) . '">';
        $this->single_row_columns($item);
        echo '</tr>';
    }

    /**
     * Image column
     *
     * @param array $item Item data
     * @return string
     */
    public function column_image($item)
    {
        return $item['image'];
    }

    /**
     * Title column with action links
     *
     * @param array $item Item data
     * @return string
     */
    public function column_title($item)
    {
        $status_badge = $this->get_product_status_badge(isset($item['post_status']) ? $item['post_status'] : '');

        if (!$this->user_can_manage_products()) {
            $view_link = get_permalink($item['id']);
            $title = '<strong><a target="_blank" rel="noopener noreferrer" href="' . esc_url($view_link) . '">' . esc_html($item['title']) . '</a></strong>';
            $title .= '<span class="mulopimfwc-product-id"> ID: ' . esc_html($item['id']) . '</span>';
            $title .= $status_badge;
            return $title;
        }

        $edit_link = get_edit_post_link($item['id']);
        $view_link = get_permalink($item['id']);
        $trash_link = get_delete_post_link($item['id'], '', true);
        $duplicate_link = wp_nonce_url(
            admin_url('edit.php?post_type=product&action=duplicate_product&post=' . $item['id']),
            'woocommerce-duplicate-product_' . $item['id']
        );

        // Prepare data for quick edit (Manage Stock) popup
        $nonce = wp_create_nonce('location_product_action_nonce');

        $title = '<strong><a target="_blank" rel="noopener noreferrer" href="' . esc_url($edit_link) . '">' . esc_html($item['title']) . '</a></strong>';
        $title .= '<span class="mulopimfwc-product-id"> ID: ' . esc_html($item['id']) . '</span>';
        $title .= $status_badge;
        $title .= '<div class="row-actions">';
        $title .= '<span class="edit"><a target="_blank" rel="noopener noreferrer" href="' . esc_url($edit_link) . '">' . __('Edit', 'multi-location-product-and-inventory-management') . '</a> | </span>';
        $title .= '<span class="view"><a target="_blank" rel="noopener noreferrer" href="' . esc_url($view_link) . '">' . __('View', 'multi-location-product-and-inventory-management') . '</a> | </span>';

        if (!$this->is_classic_mode()) {
            $manage_attrs = 'class="row-quick-edit manage-product-location" data-product-id="' . esc_attr($item['id']) . '" data-product-type="' . esc_attr($item['type']) . '" data-nonce="' . esc_attr($nonce) . '"';
            $title .= '<span class="quick-edit"><a href="#" ' . $manage_attrs . '>' . __('Quick Edit', 'multi-location-product-and-inventory-management') . '</a> | </span>';
        }
        $title .= '<span class="trash"><a href="' . esc_url($trash_link) . '">' . __('Trash', 'multi-location-product-and-inventory-management') . '</a> | </span>';
        $title .= '<span class="duplicate"><a href="' . esc_url($duplicate_link) . '">' . __('Duplicate', 'multi-location-product-and-inventory-management') . '</a></span>';
        $title .= '</div>';
        return $title;
    }

    private function get_all_location_data_for_item($item)
    {
        $locations = isset($item['location_terms']) && is_array($item['location_terms']) ? $item['location_terms'] : [];
        $selected_slugs = array_fill_keys(array_map('rawurldecode', (array) wp_list_pluck($locations, 'slug')), true);

        if ($this->all_location_data_template === null) {
            $this->all_location_data_template = [];

            global $mulopimfwc_locations;
            if (!is_wp_error($mulopimfwc_locations) && !empty($mulopimfwc_locations)) {
                $all_locations = $this->filter_location_terms_for_user($mulopimfwc_locations);
                foreach ($all_locations as $location) {
                    $this->all_location_data_template[] = [
                        'id' => (int) $location->term_id,
                        'name' => (string) $location->name,
                        'parent' => (int) $location->parent,
                        'slug' => rawurldecode((string) $location->slug),
                    ];
                }
            }
        }

        $all_locations_data = [];
        foreach ($this->all_location_data_template as $location_template) {
            $slug = isset($location_template['slug']) ? (string) $location_template['slug'] : '';
            $all_locations_data[] = [
                'id' => (int) $location_template['id'],
                'name' => (string) $location_template['name'],
                'parent' => (int) $location_template['parent'],
                'selected' => isset($selected_slugs[$slug]),
            ];
        }

        return $all_locations_data;
    }

    private function get_classic_variation_label($variation)
    {
        $variation_id = isset($variation['id']) ? (int) $variation['id'] : 0;
        if (empty($variation['attributes']) || !is_array($variation['attributes'])) {
            return sprintf(__('Variation #%d', 'multi-location-product-and-inventory-management'), $variation_id);
        }

        $parts = [];
        foreach ($variation['attributes'] as $attribute_key => $attribute_value) {
            $taxonomy = str_replace('attribute_', '', (string) $attribute_key);
            $label = function_exists('wc_attribute_label') ? wc_attribute_label($taxonomy) : $taxonomy;
            if ($label === '' || $label === $taxonomy) {
                $label = ucwords(str_replace(['pa_', '-', '_'], ['', ' ', ' '], $taxonomy));
            }
            $parts[] = trim($label) . ': ' . $attribute_value;
        }

        return !empty($parts)
            ? implode(', ', $parts)
            : sprintf(__('Variation #%d', 'multi-location-product-and-inventory-management'), $variation_id);
    }

    private function classic_number_input($field, $value, $step = '1', $min = '0', $disabled = false, $extra_class = '')
    {
        $disabled_attr = $disabled ? ' disabled="disabled"' : '';
        $classes = trim('mulopimfwc-classic-field mulopimfwc-classic-number ' . $extra_class);
        $string_value = (string) $value;

        return '<input type="number" class="' . esc_attr($classes) . '" data-field="' . esc_attr($field) . '" data-initial-value="' . esc_attr($string_value) . '" value="' . esc_attr($string_value) . '" min="' . esc_attr($min) . '" step="' . esc_attr($step) . '"' . $disabled_attr . ' />';
    }

    private function classic_price_input($field, $value, $location_term_id = null, $disabled = false, $extra_class = '')
    {
        $currency_symbol = $this->get_currency_symbol_for_location($location_term_id);
        $output = '<div class="mulopimfwc-classic-price-input-wrap" data-currency-symbol="' . esc_attr($currency_symbol) . '">';
        $output .= $this->classic_number_input($field, $value, '0.01', '0', $disabled, $extra_class);
        $output .= '<span class="mulopimfwc-classic-price-suffix" aria-hidden="true">' . esc_html($currency_symbol) . '</span>';
        $output .= '</div>';

        return $output;
    }

    private function classic_select_input($field, $value, $options, $disabled = false, $extra_class = '')
    {
        $disabled_attr = $disabled ? ' disabled="disabled"' : '';
        $classes = trim('mulopimfwc-classic-field mulopimfwc-classic-select ' . $extra_class);
        $string_value = (string) $value;
        $output = '<select class="' . esc_attr($classes) . '" data-field="' . esc_attr($field) . '" data-initial-value="' . esc_attr($string_value) . '"' . $disabled_attr . '>';

        foreach ($options as $option_value => $option_label) {
            $output .= '<option value="' . esc_attr((string) $option_value) . '"' . selected($string_value, (string) $option_value, false) . '>' . esc_html($option_label) . '</option>';
        }

        $output .= '</select>';
        return $output;
    }

    private function classic_checkbox_input($field, $checked = false, $disabled = false, $extra_class = '')
    {
        $disabled_attr = $disabled ? ' disabled="disabled"' : '';
        $checked_attr = $checked ? ' checked="checked"' : '';
        $classes = trim('mulopimfwc-classic-field mulopimfwc-classic-checkbox ' . $extra_class);
        $initial_value = $checked ? 'yes' : 'no';

        return '<input type="checkbox" class="' . esc_attr($classes) . '" data-field="' . esc_attr($field) . '" data-initial-value="' . esc_attr($initial_value) . '" value="yes"' . $checked_attr . $disabled_attr . ' />';
    }

    private function normalize_classic_manage_stock_value($value)
    {
        $manage_stock = strtolower((string) $value);
        if (!in_array($manage_stock, ['yes', 'no'], true)) {
            $manage_stock = 'yes';
        }

        return $manage_stock;
    }

    private function normalize_classic_backorders_value($value, $target = 'woocommerce')
    {
        $value = is_string($value) ? strtolower(trim($value)) : '';

        if ($target === 'location') {
            if ($value === 'yes' || $value === 'on') {
                return 'on';
            }
            if ($value === 'notify') {
                return 'notify';
            }
            if ($value === 'no' || $value === 'off') {
                return 'off';
            }
            return '';
        }

        if ($value === 'yes' || $value === 'on') {
            return 'yes';
        }
        if ($value === 'notify') {
            return 'notify';
        }
        if ($value === 'no' || $value === 'off') {
            return 'no';
        }

        return '';
    }

    private function is_store_manage_stock_enabled()
    {
        return get_option('woocommerce_manage_stock', 'no') === 'yes';
    }

    private function get_classic_editor_context($item)
    {
        $product_id = isset($item['id']) ? (int) $item['id'] : 0;
        if ($product_id > 0 && array_key_exists($product_id, $this->classic_editor_context_cache)) {
            return $this->classic_editor_context_cache[$product_id];
        }

        if (!$this->user_can_manage_products()) {
            if ($product_id > 0) {
                $this->classic_editor_context_cache[$product_id] = null;
            }
            return null;
        }

        $product_data = isset($item['quick_edit_data']) && is_array($item['quick_edit_data']) ? $item['quick_edit_data'] : [];
        if (empty($product_data)) {
            if ($product_id > 0) {
                $this->classic_editor_context_cache[$product_id] = null;
            }
            return null;
        }

        $product_type = isset($item['type']) ? (string) $item['type'] : '';
        $global_manage_stock_enabled = $this->is_store_manage_stock_enabled();
        $product_supports_manage_stock = !in_array($product_type, ['grouped', 'external'], true);
        $supports_manage_stock = $global_manage_stock_enabled && $product_supports_manage_stock;
        $default = isset($product_data['default']) && is_array($product_data['default']) ? $product_data['default'] : [];
        $locations = isset($product_data['locations']) && is_array($product_data['locations']) ? $product_data['locations'] : [];
        $variations = isset($product_data['variations']) && is_array($product_data['variations']) ? $product_data['variations'] : [];
        $manage_stock = $this->normalize_classic_manage_stock_value(isset($default['manage_stock']) ? $default['manage_stock'] : 'yes');
        $default_backorders = isset($default['backorders']) && $default['backorders'] !== '' ? $default['backorders'] : 'no';

        $all_locations_data = $this->get_all_location_data_for_item($item);
        $assigned_location_ids = array_map('intval', wp_list_pluck($locations, 'id'));
        $available_locations = array_values(array_filter($all_locations_data, function ($location) use ($assigned_location_ids) {
            return !in_array((int) $location['id'], $assigned_location_ids, true);
        }));

        $context = [
            'product_type' => $product_type,
            'global_manage_stock_enabled' => $global_manage_stock_enabled,
            'product_supports_manage_stock' => $product_supports_manage_stock,
            'supports_manage_stock' => $supports_manage_stock,
            'default' => $default,
            'locations' => $locations,
            'variations' => $variations,
            'manage_stock' => $manage_stock,
            'default_backorders' => $default_backorders,
            'available_locations' => $available_locations,
        ];

        if ($product_id > 0) {
            $this->classic_editor_context_cache[$product_id] = $context;
        }

        return $context;
    }

    private function render_classic_product_location_rows($locations, $supports_manage_stock, $layout_mode = 'full')
    {
        if (is_bool($layout_mode)) {
            $layout_mode = $layout_mode ? 'location_only' : 'full';
        }

        if (!in_array($layout_mode, ['full', 'price_only', 'location_only'], true)) {
            $layout_mode = 'full';
        }

        $colspan = 6;
        if ($layout_mode === 'price_only') {
            $colspan = 4;
        } elseif ($layout_mode === 'location_only') {
            $colspan = 2;
        }

        if (empty($locations)) {
            return '<tr class="mulopimfwc-classic-no-locations-row"><td colspan="' . esc_attr((string) $colspan) . '">' . esc_html__('No locations assigned yet.', 'multi-location-product-and-inventory-management') . '</td></tr>';
        }

        $output = '';
        foreach ($locations as $location) {
            $location_id = isset($location['id']) ? (int) $location['id'] : 0;
            $location_name = isset($location['name']) ? (string) $location['name'] : '';
            $location_currency_symbol = $this->get_currency_symbol_for_location($location_id > 0 ? $location_id : null);
            $stock = isset($location['stock']) ? $location['stock'] : '';
            $regular_price = isset($location['regular_price']) ? $location['regular_price'] : '';
            $sale_price = isset($location['sale_price']) ? $location['sale_price'] : '';
            $backorders = $this->normalize_classic_backorders_value(isset($location['backorders']) ? $location['backorders'] : '', 'woocommerce');
            if ($backorders === '') {
                $backorders = 'no';
            }

            $output .= '<tr class="mulopimfwc-classic-product-location-row" data-location-id="' . esc_attr($location_id) . '" data-location-name="' . esc_attr($location_name) . '" data-currency-symbol="' . esc_attr($location_currency_symbol) . '">';
            $output .= '<td class="mulopimfwc-classic-location-label">' . esc_html($location_name) . '</td>';
            if ($layout_mode === 'full') {
                $output .= '<td>' . $this->classic_number_input('stock', $stock, '1', '0', !$supports_manage_stock) . '</td>';
                $output .= '<td>' . $this->classic_price_input('regular_price', $regular_price, $location_id > 0 ? $location_id : null) . '</td>';
                $output .= '<td>' . $this->classic_price_input('sale_price', $sale_price, $location_id > 0 ? $location_id : null) . '</td>';
                $output .= '<td>' . $this->classic_select_input('backorders', $backorders, [
                    'no' => __('Do not allow', 'multi-location-product-and-inventory-management'),
                    'notify' => __('Allow, but notify', 'multi-location-product-and-inventory-management'),
                    'yes' => __('Allow', 'multi-location-product-and-inventory-management'),
                ], !$supports_manage_stock) . '</td>';
            } elseif ($layout_mode === 'price_only') {
                $output .= '<td>' . $this->classic_price_input('regular_price', $regular_price, $location_id > 0 ? $location_id : null) . '</td>';
                $output .= '<td>' . $this->classic_price_input('sale_price', $sale_price, $location_id > 0 ? $location_id : null) . '</td>';
            }
            $output .= '<td><button type="button" class="button-link-delete mulopimfwc-classic-remove-location" title="' . esc_attr__('Remove location', 'multi-location-product-and-inventory-management') . '">&#10005;</button></td>';
            $output .= '</tr>';
        }

        return $output;
    }

    private function render_classic_variation_location_rows($locations, $supports_manage_stock = true)
    {
        $colspan = $supports_manage_stock ? 5 : 3;
        if (empty($locations)) {
            return '<tr class="mulopimfwc-classic-no-locations-row"><td colspan="' . esc_attr((string) $colspan) . '">' . esc_html__('No locations assigned yet.', 'multi-location-product-and-inventory-management') . '</td></tr>';
        }

        $output = '';
        foreach ($locations as $location) {
            $location_id = isset($location['id']) ? (int) $location['id'] : 0;
            $location_name = isset($location['name']) ? (string) $location['name'] : '';
            $location_currency_symbol = $this->get_currency_symbol_for_location($location_id > 0 ? $location_id : null);
            $stock = isset($location['stock']) ? $location['stock'] : '';
            $regular_price = isset($location['regular_price']) ? $location['regular_price'] : '';
            $sale_price = isset($location['sale_price']) ? $location['sale_price'] : '';
            $backorders = $this->normalize_classic_backorders_value(isset($location['backorders']) ? $location['backorders'] : '', 'woocommerce');
            if ($backorders === '') {
                $backorders = 'no';
            }

            $output .= '<tr class="mulopimfwc-classic-variation-location-row" data-location-id="' . esc_attr($location_id) . '" data-location-name="' . esc_attr($location_name) . '" data-currency-symbol="' . esc_attr($location_currency_symbol) . '">';
            $output .= '<td class="mulopimfwc-classic-location-label">' . esc_html($location_name) . '</td>';
            if ($supports_manage_stock) {
                $output .= '<td>' . $this->classic_number_input('stock', $stock, '1', '0') . '</td>';
            }
            $output .= '<td>' . $this->classic_price_input('regular_price', $regular_price, $location_id > 0 ? $location_id : null) . '</td>';
            $output .= '<td>' . $this->classic_price_input('sale_price', $sale_price, $location_id > 0 ? $location_id : null) . '</td>';
            if ($supports_manage_stock) {
                $output .= '<td>' . $this->classic_select_input('backorders', $backorders, [
                    'no' => __('Do not allow', 'multi-location-product-and-inventory-management'),
                    'notify' => __('Allow, but notify', 'multi-location-product-and-inventory-management'),
                    'yes' => __('Allow', 'multi-location-product-and-inventory-management'),
                ]) . '</td>';
            }
            $output .= '</tr>';
        }

        return $output;
    }

    private function get_classic_inventory_display($item)
    {
        if (!$this->user_can_manage_products()) {
            return '<span style="color: #9ca3af;">--</span>';
        }

        $output = '<div class="mulopimfwc-classic-editor">';
        $output .= $this->get_classic_manage_stock_display($item);
        $output .= $this->get_classic_default_display($item);
        $output .= $this->get_classic_location_wise_display($item);
        $output .= $this->get_classic_purchase_display($item);
        $output .= '</div>';

        return $output;
    }

    private function get_classic_manage_stock_display($item)
    {
        $context = $this->get_classic_editor_context($item);
        if (!$context) {
            return '<span style="color: #9ca3af;">--</span>';
        }

        if (empty($context['global_manage_stock_enabled'])) {
            $settings_url = 'admin.php?page=wc-settings&tab=products&section=inventory';

            $output = '<div class="mulopimfwc-classic-manage-stock-disabled">';
            $output .= '<p>Disabled in <a href="' . esc_url($settings_url) . '" aria-label="stock management store settings">store settings</a></p>';
            $output .= '<p class="description">' . esc_html__('Enable WooCommerce Manage stock to edit stock quantity and backorders.', 'multi-location-product-and-inventory-management') . '</p>';
            $output .= '</div>';

            return $output;
        }

        if (empty($context['product_supports_manage_stock'])) {
            return '<span style="color: #9ca3af;">--</span>';
        }

        $output = '<div class="mulopimfwc-classic-section">';
        $output .= '<label class="mulopimfwc-classic-checkbox-wrap">';
        $output .= $this->classic_checkbox_input('manage_stock', $context['manage_stock'] === 'yes', false, 'mulopimfwc-classic-default-field');
        $output .= '<span>' . esc_html__('Enable', 'multi-location-product-and-inventory-management') . '</span>';
        $output .= '</label>';
        $output .= '</div>';

        if ($context['product_type'] === 'variable' && !empty($context['variations'])) {
            $output .= $this->render_classic_variation_manage_stock_section($context['variations']);
        }

        return $output;
    }

    private function get_classic_default_display($item)
    {
        $context = $this->get_classic_editor_context($item);
        if (!$context) {
            return '<span style="color: #9ca3af;">--</span>';
        }

        if ($context['product_type'] === 'grouped') {
            return '<span style="color: #9ca3af;">--</span>';
        }

        $default = $context['default'];
        $is_variable_product = $context['product_type'] === 'variable';
        $output = '';
        $default_grid = '';

        if ($context['supports_manage_stock']) {
            $default_grid .= '<div><label>' . esc_html__('Stock Qty', 'multi-location-product-and-inventory-management') . '</label>';
            $default_grid .= $this->classic_number_input('stock_quantity', isset($default['stock_quantity']) ? $default['stock_quantity'] : '', '1', '0', false, 'mulopimfwc-classic-default-field');
            $default_grid .= '</div>';
        }

        if (!$is_variable_product) {
            $default_grid .= '<div><label>' . esc_html__('Regular Price', 'multi-location-product-and-inventory-management') . '</label>';
            $default_grid .= $this->classic_price_input('regular_price', isset($default['regular_price']) ? $default['regular_price'] : '', null, false, 'mulopimfwc-classic-default-field');
            $default_grid .= '</div>';

            $default_grid .= '<div><label>' . esc_html__('Sale Price', 'multi-location-product-and-inventory-management') . '</label>';
            $default_grid .= $this->classic_price_input('sale_price', isset($default['sale_price']) ? $default['sale_price'] : '', null, false, 'mulopimfwc-classic-default-field');
            $default_grid .= '</div>';
        }

        if ($context['supports_manage_stock']) {
            $default_grid .= '<div><label>' . esc_html__('Backorders', 'multi-location-product-and-inventory-management') . '</label>';
            $default_grid .= $this->classic_select_input('backorders', $context['default_backorders'], [
                'no' => __('Do not allow', 'multi-location-product-and-inventory-management'),
                'notify' => __('Allow, but notify', 'multi-location-product-and-inventory-management'),
                'yes' => __('Allow', 'multi-location-product-and-inventory-management'),
            ], false, 'mulopimfwc-classic-default-field');
            $default_grid .= '</div>';
        }

        if ($default_grid !== '') {
            $output .= '<div class="mulopimfwc-classic-section">';
            $output .= '<div class="mulopimfwc-classic-grid">';
            $output .= $default_grid;
            $output .= '</div>';
            $output .= '</div>';
        }

        if ($context['product_type'] === 'variable' && !empty($context['variations'])) {
            $output .= $this->render_classic_variation_default_section($context['variations'], $context['supports_manage_stock']);
        }

        if ($output === '') {
            return '<span style="color: #9ca3af;">--</span>';
        }

        return $output;
    }

    private function get_classic_purchase_display($item)
    {
        $context = $this->get_classic_editor_context($item);
        if (!$context) {
            return '<span style="color: #9ca3af;">--</span>';
        }

        if ($context['product_type'] === 'grouped') {
            return '<span style="color: #9ca3af;">--</span>';
        }

        if ($context['product_type'] === 'variable') {
            if (!empty($context['variations'])) {
                return $this->render_classic_variation_purchase_section($context['variations']);
            }

            return '<span style="color: #9ca3af;">--</span>';
        }

        $default = $context['default'];
        $output = '<div class="mulopimfwc-classic-section">';
        $output .= '<div class="mulopimfwc-classic-grid">';
        $output .= '<div><label>' . esc_html__('Purchase Price', 'multi-location-product-and-inventory-management') . '</label>';
        $output .= $this->classic_price_input('purchase_price', isset($default['purchase_price']) ? $default['purchase_price'] : '', null, false, 'mulopimfwc-classic-default-field');
        $output .= '</div>';

        $output .= '<div><label>' . esc_html__('Purchase Quantity', 'multi-location-product-and-inventory-management') . '</label>';
        $output .= $this->classic_number_input('purchase_quantity', isset($default['purchase_quantity']) ? $default['purchase_quantity'] : '', '1', '0', false, 'mulopimfwc-classic-default-field');
        $output .= '</div>';
        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    private function get_classic_location_wise_display($item)
    {
        $context = $this->get_classic_editor_context($item);
        if (!$context) {
            return '<span style="color: #9ca3af;">--</span>';
        }

        $is_variable_product = $context['product_type'] === 'variable';
        $is_grouped_product = $context['product_type'] === 'grouped';
        $is_external_product = $context['product_type'] === 'external';
        $location_layout_mode = 'full';
        if ($is_variable_product || $is_grouped_product) {
            $location_layout_mode = 'location_only';
        } elseif ($is_external_product || !$context['supports_manage_stock']) {
            $location_layout_mode = 'price_only';
        }

        $has_available_locations = !empty($context['available_locations']);
        $has_assigned_locations = !empty($context['locations']);
        $has_location_catalog = $has_available_locations || $has_assigned_locations;
        $can_manage_locations = current_user_can('manage_woocommerce') || current_user_can('manage_product_terms') || current_user_can('manage_options');
        $create_location_url = admin_url('edit-tags.php?taxonomy=mulopimfwc_store_location&post_type=product&orderby=display_order&order=asc');
        $select_disabled_attr = $has_available_locations ? '' : ' disabled="disabled"';
        $button_disabled_attr = $has_available_locations ? '' : ' disabled="disabled"';
        $no_locations_message = esc_html__('No store locations are available yet.', 'multi-location-product-and-inventory-management');
        $all_assigned_message = esc_html__('All available locations are already assigned to this product.', 'multi-location-product-and-inventory-management');
        $create_location_label = esc_html__('Create your first location', 'multi-location-product-and-inventory-management');

        $output = '<div class="mulopimfwc-classic-section">';
        $output .= '<div class="mulopimfwc-classic-section-head">';
        $output .= '<h4>' . esc_html__('Locations', 'multi-location-product-and-inventory-management') . '</h4>';
        $output .= '<div class="mulopimfwc-classic-add-location-wrap" data-default-currency-symbol="' . esc_attr($this->get_currency_symbol_for_location(null)) . '" data-has-location-catalog="' . esc_attr($has_location_catalog ? 'yes' : 'no') . '" data-no-locations-message="' . esc_attr($no_locations_message) . '" data-all-assigned-message="' . esc_attr($all_assigned_message) . '" data-create-location-label="' . esc_attr($create_location_label) . '" data-create-location-url="' . esc_url($create_location_url) . '" data-can-manage-locations="' . esc_attr($can_manage_locations ? 'yes' : 'no') . '">';
        $output .= '<select class="mulopimfwc-classic-add-location-select"' . $select_disabled_attr . '>';
        $output .= '<option value="">' . esc_html__('Select location', 'multi-location-product-and-inventory-management') . '</option>';
        if (!empty($context['available_locations'])) {
            $output .= '<option value="__all__">' . esc_html__('All locations', 'multi-location-product-and-inventory-management') . '</option>';
        }
        foreach ($context['available_locations'] as $location) {
            $location_id = isset($location['id']) ? (int) $location['id'] : 0;
            $location_symbol = $this->get_currency_symbol_for_location($location_id > 0 ? $location_id : null);
            $output .= '<option value="' . esc_attr($location_id) . '" data-currency-symbol="' . esc_attr($location_symbol) . '">' . esc_html((string) $location['name']) . '</option>';
        }
        $output .= '</select>';
        $output .= '<button type="button" class="button button-secondary mulopimfwc-classic-add-location-btn"' . $button_disabled_attr . '>' . esc_html__('Add', 'multi-location-product-and-inventory-management') . '</button>';
        $output .= '</div>';
        $output .= '<p class="description mulopimfwc-classic-add-location-empty-state' . ($has_available_locations ? ' is-hidden' : '') . '">';
        if (!$has_available_locations) {
            if (!$has_location_catalog) {
                $output .= $no_locations_message;
                if ($can_manage_locations) {
                    $output .= ' <a href="' . esc_url($create_location_url) . '">' . $create_location_label . '</a>.';
                }
            } else {
                $output .= $all_assigned_message;
            }
        }
        $output .= '</p>';
        $output .= '</div>';

        $output .= '<table class="widefat striped mulopimfwc-classic-location-table mulopimfwc-classic-product-location-table">';
        $output .= '<thead><tr>';
        $output .= '<th>' . esc_html__('Location', 'multi-location-product-and-inventory-management') . '</th>';
        if ($location_layout_mode === 'full') {
            $output .= '<th>' . esc_html__('Stock', 'multi-location-product-and-inventory-management') . '</th>';
            $output .= '<th>' . esc_html__('Regular', 'multi-location-product-and-inventory-management') . '</th>';
            $output .= '<th>' . esc_html__('Sale', 'multi-location-product-and-inventory-management') . '</th>';
            $output .= '<th>' . esc_html__('Backorders', 'multi-location-product-and-inventory-management') . '</th>';
        } elseif ($location_layout_mode === 'price_only') {
            $output .= '<th>' . esc_html__('Regular', 'multi-location-product-and-inventory-management') . '</th>';
            $output .= '<th>' . esc_html__('Sale', 'multi-location-product-and-inventory-management') . '</th>';
        }
        $output .= '<th>' . esc_html__('Remove', 'multi-location-product-and-inventory-management') . '</th>';
        $output .= '</tr></thead>';
        $output .= '<tbody>';
        $output .= $this->render_classic_product_location_rows($context['locations'], $context['supports_manage_stock'], $location_layout_mode);
        $output .= '</tbody>';
        $output .= '</table>';
        $output .= '</div>';

        if ($context['product_type'] === 'variable' && !empty($context['variations'])) {
            $output .= $this->render_classic_variation_location_section($context['variations'], $context['supports_manage_stock']);
        }

        return $output;
    }

    private function render_classic_variation_manage_stock_section($variations)
    {
        if (empty($variations)) {
            return '';
        }

        $output = '<div class="mulopimfwc-classic-section">';
        $output .= '<h4>' . esc_html__('Variation Manage Stock', 'multi-location-product-and-inventory-management') . '</h4>';
        $output .= '<div class="mulopimfwc-classic-variation-manage-list">';
        foreach ($variations as $variation) {
            $variation_id = isset($variation['id']) ? (int) $variation['id'] : 0;
            $variation_default = isset($variation['default']) && is_array($variation['default']) ? $variation['default'] : [];
            $variation_manage_stock = $this->normalize_classic_manage_stock_value(isset($variation_default['manage_stock']) ? $variation_default['manage_stock'] : 'yes');

            $output .= '<div class="mulopimfwc-classic-variation-manage-item" data-variation-id="' . esc_attr($variation_id) . '">';
            $output .= '<label class="mulopimfwc-classic-checkbox-wrap">';
            $output .= $this->classic_checkbox_input('manage_stock', $variation_manage_stock === 'yes', false, 'mulopimfwc-classic-variation-default-field');
            $output .= '<span class="mulopimfwc-classic-variation-manage-title">' . esc_html($this->get_classic_variation_label($variation)) . '</span>';
            $output .= '</label>';
            $output .= '</div>';
        }
        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    private function render_classic_variation_default_section($variations, $supports_manage_stock = true)
    {
        if (empty($variations)) {
            return '';
        }

        $output = '<div class="mulopimfwc-classic-section">';
        $output .= '<h4>' . esc_html__('Variation Default Values', 'multi-location-product-and-inventory-management') . '</h4>';

        $variation_index = 0;
        foreach ($variations as $variation) {
            $variation_id = isset($variation['id']) ? (int) $variation['id'] : 0;
            $variation_default = isset($variation['default']) && is_array($variation['default']) ? $variation['default'] : [];
            $variation_backorders = isset($variation_default['backorders']) && $variation_default['backorders'] !== '' ? $variation_default['backorders'] : 'no';

            $output .= '<details class="mulopimfwc-classic-variation" data-variation-id="' . esc_attr($variation_id) . '"' . ($variation_index === 0 ? ' open="open"' : '') . '>';
            $output .= '<summary>' . esc_html($this->get_classic_variation_label($variation)) . '</summary>';

            $output .= '<div class="mulopimfwc-classic-grid mulopimfwc-classic-variation-default-grid">';
            if ($supports_manage_stock) {
                $output .= '<div><label>' . esc_html__('Stock Qty', 'multi-location-product-and-inventory-management') . '</label>';
                $output .= $this->classic_number_input('stock_quantity', isset($variation_default['stock_quantity']) ? $variation_default['stock_quantity'] : '', '1', '0', false, 'mulopimfwc-classic-variation-default-field');
                $output .= '</div>';
            }

            $output .= '<div><label>' . esc_html__('Regular Price', 'multi-location-product-and-inventory-management') . '</label>';
            $output .= $this->classic_price_input('regular_price', isset($variation_default['regular_price']) ? $variation_default['regular_price'] : '', null, false, 'mulopimfwc-classic-variation-default-field');
            $output .= '</div>';

            $output .= '<div><label>' . esc_html__('Sale Price', 'multi-location-product-and-inventory-management') . '</label>';
            $output .= $this->classic_price_input('sale_price', isset($variation_default['sale_price']) ? $variation_default['sale_price'] : '', null, false, 'mulopimfwc-classic-variation-default-field');
            $output .= '</div>';

            if ($supports_manage_stock) {
                $output .= '<div><label>' . esc_html__('Backorders', 'multi-location-product-and-inventory-management') . '</label>';
                $output .= $this->classic_select_input('backorders', $variation_backorders, [
                    'no' => __('Do not allow', 'multi-location-product-and-inventory-management'),
                    'notify' => __('Allow, but notify', 'multi-location-product-and-inventory-management'),
                    'yes' => __('Allow', 'multi-location-product-and-inventory-management'),
                ], false, 'mulopimfwc-classic-variation-default-field');
                $output .= '</div>';
            }
            $output .= '</div>';

            $output .= '</details>';
            $variation_index++;
        }

        $output .= '</div>';

        return $output;
    }

    private function render_classic_variation_purchase_section($variations)
    {
        if (empty($variations)) {
            return '';
        }

        $output = '<div class="mulopimfwc-classic-section">';
        $output .= '<h4>' . esc_html__('Variation Purchase Info', 'multi-location-product-and-inventory-management') . '</h4>';

        $variation_index = 0;
        foreach ($variations as $variation) {
            $variation_id = isset($variation['id']) ? (int) $variation['id'] : 0;
            $variation_default = isset($variation['default']) && is_array($variation['default']) ? $variation['default'] : [];

            $output .= '<details class="mulopimfwc-classic-variation" data-variation-id="' . esc_attr($variation_id) . '"' . ($variation_index === 0 ? ' open="open"' : '') . '>';
            $output .= '<summary>' . esc_html($this->get_classic_variation_label($variation)) . '</summary>';

            $output .= '<div class="mulopimfwc-classic-grid mulopimfwc-classic-variation-default-grid">';
            $output .= '<div><label>' . esc_html__('Purchase Price', 'multi-location-product-and-inventory-management') . '</label>';
            $output .= $this->classic_price_input('purchase_price', isset($variation_default['purchase_price']) ? $variation_default['purchase_price'] : '', null, false, 'mulopimfwc-classic-variation-default-field');
            $output .= '</div>';

            $output .= '<div><label>' . esc_html__('Purchase Quantity', 'multi-location-product-and-inventory-management') . '</label>';
            $output .= $this->classic_number_input('purchase_quantity', isset($variation_default['purchase_quantity']) ? $variation_default['purchase_quantity'] : '', '1', '0', false, 'mulopimfwc-classic-variation-default-field');
            $output .= '</div>';
            $output .= '</div>';
            $output .= '</details>';
            $variation_index++;
        }

        $output .= '</div>';

        return $output;
    }

    private function render_classic_variation_location_section($variations, $supports_manage_stock = true)
    {
        if (empty($variations)) {
            return '';
        }

        $output = '<div class="mulopimfwc-classic-section">';
        $output .= '<h4>' . esc_html__('Variation Locations', 'multi-location-product-and-inventory-management') . '</h4>';

        $variation_index = 0;
        foreach ($variations as $variation) {
            $variation_id = isset($variation['id']) ? (int) $variation['id'] : 0;
            $variation_locations = isset($variation['locations']) && is_array($variation['locations']) ? $variation['locations'] : [];

            $output .= '<details class="mulopimfwc-classic-variation" data-variation-id="' . esc_attr($variation_id) . '"' . ($variation_index === 0 ? ' open="open"' : '') . '>';
            $output .= '<summary>' . esc_html($this->get_classic_variation_label($variation)) . '</summary>';
            $output .= '<table class="widefat striped mulopimfwc-classic-location-table mulopimfwc-classic-variation-location-table" data-variation-id="' . esc_attr($variation_id) . '">';
            $output .= '<thead><tr>';
            $output .= '<th>' . esc_html__('Location', 'multi-location-product-and-inventory-management') . '</th>';
            if ($supports_manage_stock) {
                $output .= '<th>' . esc_html__('Stock', 'multi-location-product-and-inventory-management') . '</th>';
            }
            $output .= '<th>' . esc_html__('Regular', 'multi-location-product-and-inventory-management') . '</th>';
            $output .= '<th>' . esc_html__('Sale', 'multi-location-product-and-inventory-management') . '</th>';
            if ($supports_manage_stock) {
                $output .= '<th>' . esc_html__('Backorders', 'multi-location-product-and-inventory-management') . '</th>';
            }
            $output .= '</tr></thead>';
            $output .= '<tbody>';
            $output .= $this->render_classic_variation_location_rows($variation_locations, $supports_manage_stock);
            $output .= '</tbody>';
            $output .= '</table>';
            $output .= '</details>';
            $variation_index++;
        }
        $output .= '</div>';

        return $output;
    }

    /**
     * Get stock display for each location
     *
     * @param array $item Item data
     * @return string
     */
    private function get_location_stock_display($item)
    {
        // Show -- for external and grouped products (they don't have stock management)
        if ($item['type'] === 'external' || $item['type'] === 'grouped') {
            return '<span style="color: #9ca3af;">--</span>';
        }

        $location_terms = $this->filter_location_terms_for_user($item['location_terms']);
        $show_default = $this->should_show_default_details();
        if (!$show_default && empty($location_terms)) {
            return '<span class="no-locations">' . __('N/A', 'multi-location-product-and-inventory-management') . '</span>';
        }

        $output = '<div class="location-stock-container">';
        if ($item['type'] === 'variable' && !empty($item['variations'])) {
            $variation_index = 0;
            foreach ($item['variations'] as $variation) {
                $variation_title = implode(', ', array_map(function ($key, $value) {
                    return ucfirst(str_replace('attribute_pa_', '', $key)) . ': ' . $value;
                }, array_keys($variation['attributes']), $variation['attributes']));
                $is_first = $variation_index === 0;
                $accordion_id = 'variation-stock-' . $item['id'] . '-' . $variation_index;
                $output .= '<div class="variation-stock-item accordion-item' . ($is_first ? ' accordion-expanded' : '') . '">';
                $output .= '<div class="accordion-header" data-accordion-target="' . esc_attr($accordion_id) . '">';
                $output .= '<strong>' . esc_html($variation_title) . '</strong>';
                $output .= '<span class="accordion-icon">' . ($is_first ? '-' : '+') . '</span>';
                $output .= '</div>';
                $output .= '<div class="accordion-content' . ($is_first ? ' accordion-open' : '') . '" id="' . esc_attr($accordion_id) . '">';
                
                // Get variation product to check stock management setting
                $variation_product = wc_get_product($variation['id']);
                $manage_stock = $variation_product ? $variation_product->get_manage_stock() : false;
                $backorders = $variation_product ? $variation_product->get_backorders() : 'off';
                
                if ($show_default) {
                    $output .= '<div class="location-stock-item">';
                    $output .= '<span class="location-name">' . __('Default', 'multi-location-product-and-inventory-management') . ':</span> ';
                    if ($manage_stock) {
                        $default_stock = get_post_meta($variation['id'], '_stock', true);
                        $stock_data = function_exists('mulopimfwc_build_stock_display_label')
                            ? mulopimfwc_build_stock_display_label([
                                'stock_qty' => $default_stock,
                                'backorders' => $backorders,
                                'count_format' => 'paren',
                            ])
                            : ['show' => true, 'label' => $default_stock ? __('In stock', 'multi-location-product-and-inventory-management') . ' (' . esc_html($default_stock) . ')' : __('Out of stock', 'multi-location-product-and-inventory-management')];
                    } else {
                        $stock_status = get_post_meta($variation['id'], '_stock_status', true);
                        $stock_data = function_exists('mulopimfwc_build_stock_display_label')
                            ? mulopimfwc_build_stock_display_label([
                                'stock_status' => $stock_status,
                                'backorders' => $backorders,
                            ])
                            : ['show' => true, 'label' => ($stock_status === 'instock') ? __('In stock', 'multi-location-product-and-inventory-management') : __('Out of stock', 'multi-location-product-and-inventory-management')];
                    }
                    if (!empty($stock_data['show']) && $stock_data['label'] !== '') {
                        $output .= '<span class="stock-value">' . esc_html($stock_data['label']) . '</span>';
                    }
                    $output .= '</div>';
                }
                
                if (!empty($location_terms)) {
                    foreach ($location_terms as $location) {
                        $location_stock = get_post_meta($variation['id'], '_location_stock_' . $location->term_id, true);
                        $output .= '<div class="location-stock-item">';
                        $output .= '<span class="location-name">' . esc_html($location->name) . ':</span> ';
                        
                        // For location stock, check if stock is set (empty string means not set)
                        if ($location_stock !== '') {
                            $stock_data = function_exists('mulopimfwc_build_stock_display_label')
                                ? mulopimfwc_build_stock_display_label([
                                    'stock_qty' => $location_stock,
                                    'backorders' => get_post_meta($variation['id'], '_location_backorders_' . $location->term_id, true),
                                    'location_id' => (int) $location->term_id,
                                    'count_format' => 'paren',
                                ])
                                : ['show' => true, 'label' => ($location_stock > 0 ? __('In stock', 'multi-location-product-and-inventory-management') . ' (' . esc_html($location_stock) . ')' : __('Out of stock', 'multi-location-product-and-inventory-management'))];
                            if (!empty($stock_data['show']) && $stock_data['label'] !== '') {
                                $output .= '<span class="stock-value">' . esc_html($stock_data['label']) . '</span>';
                            }
                        } else {
                            // Location stock not set - check default stock status
                            if ($manage_stock) {
                                $default_stock = get_post_meta($variation['id'], '_stock', true);
                                $stock_data = function_exists('mulopimfwc_build_stock_display_label')
                                    ? mulopimfwc_build_stock_display_label([
                                        'stock_qty' => $default_stock,
                                        'backorders' => $backorders,
                                        'count_format' => 'paren',
                                    ])
                                    : ['show' => true, 'label' => ($default_stock ? __('In stock', 'multi-location-product-and-inventory-management') . ' (' . esc_html($default_stock) . ')' : __('Out of stock', 'multi-location-product-and-inventory-management'))];
                                if (!empty($stock_data['show']) && $stock_data['label'] !== '') {
                                    $output .= '<span class="stock-value">' . esc_html($stock_data['label']) . '</span>';
                                }
                            } else {
                                $stock_status = get_post_meta($variation['id'], '_stock_status', true);
                                $stock_data = function_exists('mulopimfwc_build_stock_display_label')
                                    ? mulopimfwc_build_stock_display_label([
                                        'stock_status' => $stock_status,
                                        'backorders' => $backorders,
                                    ])
                                    : ['show' => true, 'label' => ($stock_status === 'instock') ? __('In stock', 'multi-location-product-and-inventory-management') : __('Out of stock', 'multi-location-product-and-inventory-management')];
                                if (!empty($stock_data['show']) && $stock_data['label'] !== '') {
                                    $output .= '<span class="stock-value">' . esc_html($stock_data['label']) . '</span>';
                                }
                            }
                        }
                        $output .= '</div>';
                    }
                }
                $output .= '</div>';
                $output .= '</div>';
                $variation_index++;
            }
        } else {
            // Get product to check stock management setting
            $product = wc_get_product($item['id']);
            $manage_stock = $product ? $product->get_manage_stock() : false;
            $backorders = $product ? $product->get_backorders() : 'off';
            
            if ($show_default) {
                if ($manage_stock) {
                    $default_stock = get_post_meta($item['id'], "_stock", true);
                    $output .= '<div class="location-stock-item">';
                    $output .= '<span class="location-name">' . __('Default', 'multi-location-product-and-inventory-management') . ':</span> ';
                    $stock_data = function_exists('mulopimfwc_build_stock_display_label')
                        ? mulopimfwc_build_stock_display_label([
                            'stock_qty' => $default_stock,
                            'backorders' => $backorders,
                            'count_format' => 'paren',
                        ])
                        : ['show' => true, 'label' => ($default_stock ? __('In stock', 'multi-location-product-and-inventory-management') . ' (' . esc_html($default_stock) . ')' : __('Out of stock', 'multi-location-product-and-inventory-management'))];
                    if (!empty($stock_data['show']) && $stock_data['label'] !== '') {
                        $output .= '<span class="stock-value">' . esc_html($stock_data['label']) . '</span>';
                    }
                    $output .= '</div>';
                } else {
                    $stock_status = get_post_meta($item['id'], "_stock_status", true);
                    $backorders = $product ? $product->get_backorders() : 'off';
                    $output .= '<div class="location-stock-item">';
                    $output .= '<span class="location-name">' . __('Default', 'multi-location-product-and-inventory-management') . ':</span> ';
                    $stock_data = function_exists('mulopimfwc_build_stock_display_label')
                        ? mulopimfwc_build_stock_display_label([
                            'stock_status' => $stock_status,
                            'backorders' => $backorders,
                        ])
                        : ['show' => true, 'label' => ($stock_status === 'instock') ? __('In stock', 'multi-location-product-and-inventory-management') : __('Out of stock', 'multi-location-product-and-inventory-management')];
                    if (!empty($stock_data['show']) && $stock_data['label'] !== '') {
                        $output .= '<span class="stock-value">' . esc_html($stock_data['label']) . '</span>';
                    }
                    $output .= '</div>';
                }
            }
            if (!empty($location_terms)) {
                foreach ($location_terms as $location) {
                    $location_stock = get_post_meta($item['id'], '_location_stock_' . $location->term_id, true);
                    $output .= '<div class="location-stock-item">';
                    $output .= '<span class="location-name">' . esc_html($location->name) . ':</span> ';
                    
                    // For location stock, check if stock is set (empty string means not set)
                    if ($location_stock !== '') {
                        $stock_data = function_exists('mulopimfwc_build_stock_display_label')
                            ? mulopimfwc_build_stock_display_label([
                                'stock_qty' => $location_stock,
                                'backorders' => get_post_meta($item['id'], '_location_backorders_' . $location->term_id, true),
                                'location_id' => (int) $location->term_id,
                                'count_format' => 'paren',
                            ])
                            : ['show' => true, 'label' => ($location_stock > 0 ? __('In stock', 'multi-location-product-and-inventory-management') . ' (' . esc_html($location_stock) . ')' : __('Out of stock', 'multi-location-product-and-inventory-management'))];
                        if (!empty($stock_data['show']) && $stock_data['label'] !== '') {
                            $output .= '<span class="stock-value">' . esc_html($stock_data['label']) . '</span>';
                        }
                    } else {
                        // Location stock not set - check default stock status
                        if ($manage_stock) {
                            $default_stock = get_post_meta($item['id'], '_stock', true);
                            $stock_data = function_exists('mulopimfwc_build_stock_display_label')
                                ? mulopimfwc_build_stock_display_label([
                                    'stock_qty' => $default_stock,
                                    'backorders' => $backorders,
                                    'count_format' => 'paren',
                                ])
                                : ['show' => true, 'label' => ($default_stock ? __('In stock', 'multi-location-product-and-inventory-management') . ' (' . esc_html($default_stock) . ')' : __('Out of stock', 'multi-location-product-and-inventory-management'))];
                            if (!empty($stock_data['show']) && $stock_data['label'] !== '') {
                                $output .= '<span class="stock-value">' . esc_html($stock_data['label']) . '</span>';
                            }
                        } else {
                            $stock_status = get_post_meta($item['id'], '_stock_status', true);
                            $stock_data = function_exists('mulopimfwc_build_stock_display_label')
                                ? mulopimfwc_build_stock_display_label([
                                    'stock_status' => $stock_status,
                                    'backorders' => $backorders,
                                ])
                                : ['show' => true, 'label' => ($stock_status === 'instock') ? __('In stock', 'multi-location-product-and-inventory-management') : __('Out of stock', 'multi-location-product-and-inventory-management')];
                            if (!empty($stock_data['show']) && $stock_data['label'] !== '') {
                                $output .= '<span class="stock-value">' . esc_html($stock_data['label']) . '</span>';
                            }
                        }
                    }
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
    private function get_location_price_display($item)
    {
        // Show -- for grouped products
        if ($item['type'] === 'grouped') {
            return '<span style="color: #9ca3af;">--</span>';
        }

        $location_terms = $this->filter_location_terms_for_user($item['location_terms']);
        $show_default = $this->should_show_default_details();
        if (!$show_default && empty($location_terms)) {
            return '<span class="no-locations">' . __('N/A', 'multi-location-product-and-inventory-management') . '</span>';
        }

        $output = '<div class="location-price-container">';
        if ($item['type'] === 'variable' && !empty($item['variations'])) {
            $variation_index = 0;
            foreach ($item['variations'] as $variation) {
                $variation_title = implode(', ', array_map(function ($key, $value) {
                    return ucfirst(str_replace('attribute_pa_', '', $key)) . ': ' . $value;
                }, array_keys($variation['attributes']), $variation['attributes']));
                $is_first = $variation_index === 0;
                $accordion_id = 'variation-price-' . $item['id'] . '-' . $variation_index;
                $output .= '<div class="variation-price-item accordion-item' . ($is_first ? ' accordion-expanded' : '') . '">';
                $output .= '<div class="accordion-header" data-accordion-target="' . esc_attr($accordion_id) . '">';
                $output .= '<strong>' . esc_html($variation_title) . '</strong>';
                $output .= '<span class="accordion-icon">' . ($is_first ? '-' : '+') . '</span>';
                $output .= '</div>';
                $output .= '<div class="accordion-content' . ($is_first ? ' accordion-open' : '') . '" id="' . esc_attr($accordion_id) . '">';
                $default_regular_price = $variation['regular_price'] ?? '';
                $default_sale_price = $variation['sale_price'] ?? '';
                $default_active_price = $variation['price'] ?? '';
                if ($show_default) {
                    $output .= '<div class="location-price-item">';
                    $output .= '<span class="location-name">' . __('Default', 'multi-location-product-and-inventory-management') . ':</span> ';
                    $output .= $this->format_price_by_location_display($default_regular_price, $default_sale_price, $default_active_price, null);
                    $output .= '</div>';
                }
                if (!empty($location_terms)) {
                    foreach ($location_terms as $location) {
                        $location_regular_price = get_post_meta($variation['id'], '_location_regular_price_' . $location->term_id, true);
                        $location_sale_price = get_post_meta($variation['id'], '_location_sale_price_' . $location->term_id, true);

                        if ($this->has_positive_price($location_sale_price)) {
                            $effective_regular_price = $this->has_positive_price($location_regular_price)
                                ? $location_regular_price
                                : $default_regular_price;
                            $effective_sale_price = $location_sale_price;
                        } elseif ($this->has_positive_price($location_regular_price)) {
                            $effective_regular_price = $location_regular_price;
                            $effective_sale_price = '';
                        } else {
                            $effective_regular_price = $default_regular_price;
                            $effective_sale_price = $default_sale_price;
                        }

                        $output .= '<div class="location-price-item">';
                        $output .= '<span class="location-name">' . esc_html($location->name) . ':</span> ';
                        $output .= $this->format_price_by_location_display($effective_regular_price, $effective_sale_price, $default_active_price, (int) $location->term_id);
                        $output .= '</div>';
                    }
                }
                $output .= '</div>';
                $output .= '</div>';
                $variation_index++;
            }
        } else {
            $default_regular_price = get_post_meta($item['id'], '_regular_price', true);
            $default_sale_price = get_post_meta($item['id'], '_sale_price', true);
            $default_price = get_post_meta($item['id'], "_price", true);
            if ($show_default) {
                $output .= '<div class="location-price-item">';
                $output .= '<span class="location-name">' . __('Default', 'multi-location-product-and-inventory-management') . ':</span> ';
                $output .= $this->format_price_by_location_display($default_regular_price, $default_sale_price, $default_price, null);
                $output .= '</div>';
            }
            if (!empty($location_terms)) {
                foreach ($location_terms as $location) {
                    $location_regular_price = get_post_meta($item['id'], '_location_regular_price_' . $location->term_id, true);
                    $location_sale_price = get_post_meta($item['id'], '_location_sale_price_' . $location->term_id, true);

                    if ($this->has_positive_price($location_sale_price)) {
                        $effective_regular_price = $this->has_positive_price($location_regular_price)
                            ? $location_regular_price
                            : $default_regular_price;
                        $effective_sale_price = $location_sale_price;
                    } elseif ($this->has_positive_price($location_regular_price)) {
                        $effective_regular_price = $location_regular_price;
                        $effective_sale_price = '';
                    } else {
                        $effective_regular_price = $default_regular_price;
                        $effective_sale_price = $default_sale_price;
                    }

                    $output .= '<div class="location-price-item">';
                    $output .= '<span class="location-name">' . esc_html($location->name) . ':</span> ';
                    $output .= $this->format_price_by_location_display($effective_regular_price, $effective_sale_price, $default_price, (int) $location->term_id);
                    $output .= '</div>';
                }
            }
        }
        $output .= '</div>';
        return $output;
    }

    /**
     * Normalize a stored price-like value to float.
     *
     * @param mixed $value
     * @return float
     */
    private function normalize_location_price_value($value)
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (function_exists('wc_format_decimal')) {
            $formatted = wc_format_decimal($value);
            if (is_numeric($formatted)) {
                return (float) $formatted;
            }
        }

        return 0.0;
    }

    /**
     * Determine whether a price value is a positive number.
     *
     * @param mixed $value
     * @return bool
     */
    private function has_positive_price($value)
    {
        return $this->normalize_location_price_value($value) > 0;
    }

    /**
     * Get WooCommerce-compatible price format from currency position.
     *
     * @param string $position
     * @return string
     */
    private function get_price_format_for_currency_position($position)
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

    /**
     * Resolve currency settings for a specific location.
     *
     * @param int|null $location_term_id Null means WooCommerce default currency settings.
     * @return array{currency: string, position: string}
     */
    private function get_currency_settings_for_location($location_term_id = null)
    {
        $default_currency = strtoupper((string) get_option('woocommerce_currency', 'USD'));
        if ($default_currency === '') {
            $default_currency = 'USD';
        }

        $default_position = (string) get_option('woocommerce_currency_pos', 'left');
        if (!in_array($default_position, ['left', 'right', 'left_space', 'right_space'], true)) {
            $default_position = 'left';
        }

        $cache_key = ($location_term_id && (int) $location_term_id > 0)
            ? 'term_' . (int) $location_term_id
            : 'default';

        if (isset($this->location_currency_settings_cache[$cache_key])) {
            return $this->location_currency_settings_cache[$cache_key];
        }

        $settings = [
            'currency' => $default_currency,
            'position' => $default_position,
        ];

        $term_id = (int) $location_term_id;
        if (function_exists('mulopimfwc_get_currency_settings_for_location')) {
            $resolved = (array) mulopimfwc_get_currency_settings_for_location($term_id > 0 ? $term_id : null);
            $resolved_currency = strtoupper(trim((string) ($resolved['currency'] ?? '')));
            if ($resolved_currency !== '') {
                $settings['currency'] = $resolved_currency;
            }

            $resolved_position = sanitize_key((string) ($resolved['position'] ?? ''));
            if (in_array($resolved_position, ['left', 'right', 'left_space', 'right_space'], true)) {
                $settings['position'] = $resolved_position;
            }

            $this->location_currency_settings_cache[$cache_key] = $settings;
            return $settings;
        }

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

        $this->location_currency_settings_cache[$cache_key] = $settings;
        return $settings;
    }

    private function get_currency_symbol_for_location($location_term_id = null)
    {
        $cache_key = ($location_term_id && (int) $location_term_id > 0)
            ? 'term_' . (int) $location_term_id
            : 'default';

        if (isset($this->location_currency_symbol_cache[$cache_key])) {
            return $this->location_currency_symbol_cache[$cache_key];
        }

        $currency_settings = $this->get_currency_settings_for_location($location_term_id);
        $currency_code = strtoupper(trim((string) ($currency_settings['currency'] ?? '')));
        if ($currency_code === '') {
            $currency_code = strtoupper((string) get_option('woocommerce_currency', 'USD'));
        }
        if ($currency_code === '') {
            $currency_code = 'USD';
        }

        $currency_symbol = function_exists('get_woocommerce_currency_symbol')
            ? (string) get_woocommerce_currency_symbol($currency_code)
            : '';
        if ($currency_symbol === '') {
            $currency_symbol = $currency_code;
        }

        $currency_symbol = html_entity_decode($currency_symbol, ENT_QUOTES, get_bloginfo('charset'));
        if ($currency_symbol === '') {
            $currency_symbol = $currency_code;
        }

        $this->location_currency_symbol_cache[$cache_key] = $currency_symbol;
        return $currency_symbol;
    }

    /**
     * Format price display so sale price shows with struck-through regular price.
     *
     * @param mixed $regular_price
     * @param mixed $sale_price
     * @param mixed $fallback_price
     * @param int|null $location_term_id
     * @return string
     */
    private function format_price_by_location_display($regular_price, $sale_price, $fallback_price = '', $location_term_id = null)
    {
        $regular = $this->normalize_location_price_value($regular_price);
        $sale = $this->normalize_location_price_value($sale_price);
        $fallback = $this->normalize_location_price_value($fallback_price);
        $currency_settings = $this->get_currency_settings_for_location($location_term_id);
        $price_args = [
            'currency' => $currency_settings['currency'],
            'price_format' => $this->get_price_format_for_currency_position($currency_settings['position']),
        ];

        if ($sale > 0) {
            $base = $regular > 0 ? $regular : $fallback;
            if ($base > 0 && abs($base - $sale) > 0.0001) {
                return '<span class="price-value"><del>' . wc_price($base, $price_args) . '</del> <ins>' . wc_price($sale, $price_args) . '</ins></span>';
            }

            return '<span class="price-value">' . wc_price($sale, $price_args) . '</span>';
        }

        $display = $regular > 0 ? $regular : $fallback;
        return '<span class="price-value">' . wc_price($display, $price_args) . '</span>';
    }

    /**
     * Get purchase price display
     *
     * @param array $item Item data
     * @return string
     */
    private function get_purchase_price_display($item)
    {
        // Show -- for grouped products
        if ($item['type'] === 'grouped') {
            return '<span style="color: #9ca3af;">--</span>';
        }

        $output = '<div class="purchase-price-container">';

        if ($item['type'] === 'variable' && !empty($item['variations'])) {
            foreach ($item['variations'] as $variation) {
                $variation_title = implode(', ', array_map(function ($key, $value) {
                    return ucfirst(str_replace('attribute_pa_', '', $key)) . ': ' . $value;
                }, array_keys($variation['attributes']), $variation['attributes']));

                $purchase_price = get_post_meta($variation['id'], '_purchase_price', true);
                $purchase_quantity = get_post_meta($variation['id'], '_purchase_quantity', true);

                $output .= '<div class="variation-purchase-price-item">';
                $output .= '<strong>' . esc_html($variation_title) . '</strong>';
                $output .= '<div class="purchase-price-item">';
                $output .= '<span class="purchase-price-value">' . esc_html__('Price:', 'multi-location-product-and-inventory-management') . ' ' . (!empty($purchase_price) ? wc_price($purchase_price) : __('Not set', 'multi-location-product-and-inventory-management')) . '</span>';
                $output .= '</div>';
                $output .= '<div class="purchase-price-item">';
                $output .= '<span class="purchase-price-value">' . esc_html__('Quantity:', 'multi-location-product-and-inventory-management') . ' ' . (!empty($purchase_quantity) ? esc_html($purchase_quantity) : __('Not set', 'multi-location-product-and-inventory-management')) . '</span>';
                $output .= '</div>';
                $output .= '</div>';
            }
        } else {
            $purchase_price = get_post_meta($item['id'], '_purchase_price', true);
            $purchase_quantity = get_post_meta($item['id'], '_purchase_quantity', true);
            $output .= '<div class="purchase-price-item">';
            $output .= '<span class="purchase-price-value"> Price: ' . (!empty($purchase_price) ? wc_price($purchase_price) : __('Not set', 'multi-location-product-and-inventory-management')) . '</span>';
            $output .= '</div>';
            $output .= '<div class="purchase-price-item">';
            $output .= '<span class="purchase-price-value"> Quantity: ' . (!empty($purchase_quantity) ? $purchase_quantity : __('Not set', 'multi-location-product-and-inventory-management')) . '</span>';
            $output .= '</div>';
        }

        $output .= '</div>';
        return $output;
    }

    /**
     * Get gross profit display
     *
     * @param array $item Item data
     * @return string
     */
    private function get_gross_profit_display($item)
    {
        // Show -- for grouped products
        if ($item['type'] === 'grouped') {
            return '<span style="color: #9ca3af;">--</span>';
        }

        $location_terms = $this->filter_location_terms_for_user($item['location_terms']);
        $show_default = $this->should_show_default_details();
        if (!$show_default && empty($location_terms)) {
            return '<span class="gross-profit-value no-data">' . __('N/A', 'multi-location-product-and-inventory-management') . '</span>';
        }

        $output = '<div class="gross-profit-container '.esc_attr(mulopimfwc_get_pro_class()).'">';

        if ($item['type'] === 'variable' && !empty($item['variations'])) {
            $variation_index = 0;
            foreach ($item['variations'] as $variation) {
                $variation_title = implode(', ', array_map(function ($key, $value) {
                    return ucfirst(str_replace('attribute_pa_', '', $key)) . ': ' . $value;
                }, array_keys($variation['attributes']), $variation['attributes']));

                $purchase_price = get_post_meta($variation['id'], '_purchase_price', true);
                $default_price = $variation['price'];

                $is_first = $variation_index === 0;
                $accordion_id = 'variation-profit-' . $item['id'] . '-' . $variation_index;
                $output .= '<div class="variation-gross-profit-item accordion-item' . ($is_first ? ' accordion-expanded' : '') . '">';
                $output .= '<div class="accordion-header" data-accordion-target="' . esc_attr($accordion_id) . '">';
                $output .= '<strong>' . esc_html($variation_title) . '</strong>';
                $output .= '<span class="accordion-icon">' . ($is_first ? '-' : '+') . '</span>';
                $output .= '</div>';
                $output .= '<div class="accordion-content' . ($is_first ? ' accordion-open' : '') . '" id="' . esc_attr($accordion_id) . '">';

                // Default gross profit
                if ($show_default) {
                    $output .= '<div class="location-gross-profit-item">';
                    $output .= '<span class="location-name">' . __('Default', 'multi-location-product-and-inventory-management') . ':</span> ';
                    $output .= $this->calculate_profit_display($default_price, $purchase_price);
                    $output .= '</div>';
                }

                // Location-specific gross profit
                if (!empty($location_terms)) {
                    foreach ($location_terms as $location) {
                        $location_price = get_post_meta($variation['id'], '_location_sale_price_' . $location->term_id, true);
                        $price_to_use = !empty($location_price) ? $location_price : $default_price;

                        $output .= '<div class="location-gross-profit-item">';
                        $output .= '<span class="location-name">' . esc_html($location->name) . ':</span> ';
                        $output .= $this->calculate_profit_display($price_to_use, $purchase_price);
                        $output .= '</div>';
                    }
                }

                $output .= '</div>';
                $output .= '</div>';
                $variation_index++;
            }
        } else {
            $purchase_price = get_post_meta($item['id'], '_purchase_price', true);
            $default_price = get_post_meta($item['id'], "_price", true);

            // Default gross profit
            if ($show_default) {
                $output .= '<div class="location-gross-profit-item">';
                $output .= '<span class="location-name">' . __('Default', 'multi-location-product-and-inventory-management') . ':</span> ';
                $output .= $this->calculate_profit_display($default_price, $purchase_price);
                $output .= '</div>';
            }

            // Location-specific gross profit
            if (!empty($location_terms)) {
                foreach ($location_terms as $location) {
                    $location_price = get_post_meta($item['id'], '_location_sale_price_' . $location->term_id, true);
                    $price_to_use = !empty($location_price) ? $location_price : $default_price;

                    $output .= '<div class="location-gross-profit-item">';
                    $output .= '<span class="location-name">' . esc_html($location->name) . ':</span> ';
                    $output .= $this->calculate_profit_display($price_to_use, $purchase_price);
                    $output .= '</div>';
                }
            }
        }

        $output .= '</div>';
        return $output;
    }

    /**
     * Calculate and format profit display
     *
     * @param float $sale_price Sale price
     * @param float $purchase_price Purchase price
     * @return string Formatted profit display
     */
    private function calculate_profit_display($sale_price, $purchase_price)
    {
        if (!empty($purchase_price) && is_numeric($purchase_price) && $purchase_price > 0 && !empty($sale_price) && is_numeric($sale_price)) {
            $profit = $sale_price - $purchase_price;
            $gross_profit = wc_price($profit);

            // Calculate profit percentage
            $percentage = ($profit / $purchase_price) * 100;
            $gross_profit_percentage = round($percentage, 2) . '%';

            // Determine color based on profit
            $profit_class = $profit > 0 ? 'positive-profit' : ($profit < 0 ? 'negative-profit' : 'zero-profit');

            return '<span class="gross-profit-value ' . $profit_class . '">' .
                $gross_profit . ' <span class="profit-percentage">(' . $gross_profit_percentage . ')</span></span>';
        }

        return '<span class="gross-profit-value no-data">' . __('N/A', 'multi-location-product-and-inventory-management') . '</span>';
    }

    /**
     * Get locations display
     *
     * @param array $item Item data
     * @return string
     */
    private function get_locations_display($item)
    {
        $locations = $this->filter_location_terms_for_user($item['location_terms']);
        if (empty($locations)) {
            return '<span class="no-locations">' . __('N/A', 'multi-location-product-and-inventory-management') . '</span>';
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
    private function get_actions_display($item)
    {
        if (!$this->user_can_manage_products()) {
            if (!$this->is_location_manager() || !$this->user_can_view_products()) {
                return '';
            }

            $view_link = get_permalink($item['id']);
            return '<a class="button button-small" target="_blank" rel="noopener noreferrer" href="' . esc_url($view_link) . '">' . esc_html__('View', 'multi-location-product-and-inventory-management') . '</a>';
        }

        if ($this->is_classic_mode()) {
            $output = '<div class="mulopimfwc-classic-actions">';
            $output .= '<button type="button" class="button button-small mulopimfwc-classic-reset-row" title="' . esc_attr__('Reset product row changes', 'multi-location-product-and-inventory-management') . '" disabled="disabled">&#10005;</button>';
            $output .= '<button type="button" class="button button-primary button-small mulopimfwc-classic-save-row" title="' . esc_attr__('Save this product', 'multi-location-product-and-inventory-management') . '" disabled="disabled">&#10003;</button>';
            $output .= '<span class="mulopimfwc-classic-row-status" aria-live="polite"></span>';
            $output .= '</div>';
            return $output;
        }

        // Create nonce for action buttons
        $nonce = wp_create_nonce('location_product_action_nonce');

        $locations = $item['location_terms'];

        if (empty($locations)) {
            return '<a href="#" class="button button-small add-location" data-product-id="' . esc_attr($item['id']) . '" data-product-type="' . esc_attr($item['type']) . '" data-nonce="' . esc_attr($nonce) . '">' . __('Add to Location', 'multi-location-product-and-inventory-management') . '</a>';
        }
        $output = '<div class="location-actions">';
        foreach ($locations as $location) {
            $is_active = !get_post_meta($item['id'], '_location_disabled_' . $location->term_id, true);
            $action_class = $is_active ? 'activate-location' : 'deactivate-location';
            $action_text = $is_active ? __('Activated', 'multi-location-product-and-inventory-management') : __('Deactivated', 'multi-location-product-and-inventory-management');
            $button_class = $is_active ? 'button-primary' : 'button-secondary';

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
        $output .= '<a href="#" class="button button-small manage-product-location" style="margin-top: 5px; display: block;" data-product-id="' . esc_attr($item['id']) . '" data-product-type="' . esc_attr($item['type']) . '" data-nonce="' . esc_attr($nonce) . '">' . __('Manage Stock', 'multi-location-product-and-inventory-management') . '</a>';
        $output .= '</div>';
        return $output;
    }

    /**
     * Process bulk actions
     */
    public function process_bulk_action()
    {
        if (!$this->user_can_manage_products()) {
            return;
        }

        // Check if bulk action is set
        if (!isset($_REQUEST['action']) && !isset($_REQUEST['action2'])) {
            return;
        }

        $action = isset($_REQUEST['action']) && $_REQUEST['action'] != '-1' 
            ? sanitize_text_field(wp_unslash($_REQUEST['action'])) 
            : (isset($_REQUEST['action2']) && $_REQUEST['action2'] != '-1' 
                ? sanitize_text_field(wp_unslash($_REQUEST['action2'])) 
                : '');

        if (empty($action)) {
            return;
        }

        // Verify nonce
        if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])), 'bulk-' . $this->_args['plural'])) {
            wp_die(__('Security check failed', 'multi-location-product-and-inventory-management'));
        }

        // Check if products are selected
        if (!isset($_REQUEST['product']) || !is_array($_REQUEST['product'])) {
            return;
        }

        $product_ids = array_map('intval', $_REQUEST['product']);
        $count = 0;

        switch ($action) {
            case 'bulk_assign_location':
                if (isset($_REQUEST['bulk_location_id']) && !empty($_REQUEST['bulk_location_id'])) {
                    $location_id = intval($_REQUEST['bulk_location_id']);
                    foreach ($product_ids as $product_id) {
                        $term = get_term($location_id, 'mulopimfwc_store_location');
                        if ($term && !is_wp_error($term)) {
                            wp_set_object_terms($product_id, [$location_id], 'mulopimfwc_store_location', true);
                            $count++;
                        }
                    }
                    add_action('admin_notices', function() use ($count) {
                        echo '<div class="notice notice-success is-dismissible"><p>';
                        printf(
                            esc_html__('Successfully assigned %d products to location.', 'multi-location-product-and-inventory-management'),
                            $count
                        );
                        echo '</p></div>';
                    });
                } else {
                    // Store for modal selection
                    set_transient('mulopimfwc_bulk_action_assign_location', $product_ids, 300);
                }
                break;

            case 'bulk_remove_location':
                if (isset($_REQUEST['bulk_location_id']) && !empty($_REQUEST['bulk_location_id'])) {
                    $location_id = intval($_REQUEST['bulk_location_id']);
                    foreach ($product_ids as $product_id) {
                        wp_remove_object_terms($product_id, $location_id, 'mulopimfwc_store_location');
                        $count++;
                    }
                    add_action('admin_notices', function() use ($count) {
                        echo '<div class="notice notice-success is-dismissible"><p>';
                        printf(
                            esc_html__('Successfully removed %d products from location.', 'multi-location-product-and-inventory-management'),
                            $count
                        );
                        echo '</p></div>';
                    });
                } else {
                    // Store for modal selection
                    set_transient('mulopimfwc_bulk_action_remove_location', $product_ids, 300);
                }
                break;

            case 'trash':
                foreach ($product_ids as $product_id) {
                    if (current_user_can('delete_post', $product_id)) {
                        wp_trash_post($product_id);
                        $count++;
                    }
                }
                if ($count > 0) {
                    add_action('admin_notices', function() use ($count) {
                        echo '<div class="notice notice-success is-dismissible"><p>';
                        printf(
                            esc_html__('Successfully moved %d product(s) to trash.', 'multi-location-product-and-inventory-management'),
                            $count
                        );
                        echo '</p></div>';
                    });
                }
                break;
        }
    }

    /**
     * Prepare table items
     */
    public function prepare_items()
    {
        // Process bulk actions first
        $this->process_bulk_action();

        $columns = $this->get_columns();
        $hidden = get_hidden_columns($this->screen);
        if ($this->is_classic_mode() && $this->user_can_manage_products()) {
            $hidden = array_values(array_diff($hidden, $this->get_classic_locked_columns()));
        }
        $sortable = $this->get_sortable_columns();
        $primary = $this->get_primary_column_name();
        $this->_column_headers = [$columns, $hidden, $sortable, $primary];

        $per_page = $this->get_items_per_page('mulopimfwc_stock_central_per_page', 20);
        $per_page = max(1, (int) $per_page);
        $current_page = $this->get_pagenum();

        $args = [
            'post_type' => 'product',
            'posts_per_page' => $per_page,
            'paged' => $current_page,
            'post_status' => $this->get_stock_central_post_statuses(),
            // We'll prefetch term/meta caches in bulk after query with variation IDs included.
            'update_post_term_cache' => false,
            'update_post_meta_cache' => false,
        ];

        // Add search if set
        if (isset($_REQUEST['s']) && !empty($_REQUEST['s'])) {
            $args['s'] = sanitize_text_field(wp_unslash($_REQUEST['s']));
        }

        // Build tax_query array for multiple filters
        $tax_queries = [];

        // Limit products for location managers without all_products capability
        if (is_user_logged_in() && class_exists('MULOPIMFWC_Location_Managers')) {
            $user = wp_get_current_user();
            if (in_array('mulopimfwc_location_manager', $user->roles, true)) {
                if (!MULOPIMFWC_Location_Managers::user_has_capability('all_products')) {
                    $assigned_locations = MULOPIMFWC_Location_Managers::get_user_assigned_locations();
                    if (empty($assigned_locations)) {
                        $args['post__in'] = [0];
                    } else {
                        $tax_queries[] = [
                            'taxonomy' => 'mulopimfwc_store_location',
                            'field'    => 'slug',
                            'terms'    => $assigned_locations,
                            'operator' => 'IN',
                        ];
                    }
                }
            }
        }

        // Add location filter if set
        if (isset($_REQUEST['filter-by-location']) && !empty($_REQUEST['filter-by-location'])) {
            $tax_queries[] = [
                'taxonomy' => 'mulopimfwc_store_location',
                'field'    => 'slug',
                'terms'    => sanitize_text_field(wp_unslash($_REQUEST['filter-by-location'])),
            ];
        }

        // Add category filter if set
        if (isset($_REQUEST['filter-by-category']) && !empty($_REQUEST['filter-by-category'])) {
            $tax_queries[] = [
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => intval($_REQUEST['filter-by-category']),
            ];
        }

        // Add brand filter if set (check for common brand taxonomies)
        $brand_taxonomies = ['product_brand', 'pa_brand', 'pwb-brand'];
        foreach ($brand_taxonomies as $brand_tax) {
            if (taxonomy_exists($brand_tax) && isset($_REQUEST['filter-by-brand']) && !empty($_REQUEST['filter-by-brand'])) {
                $tax_queries[] = [
                    'taxonomy' => $brand_tax,
                    'field'    => 'term_id',
                    'terms'    => intval($_REQUEST['filter-by-brand']),
                ];
                break; // Only use first available brand taxonomy
            }
        }

        // Add product type filter
        if (isset($_REQUEST['filter-by-type']) && !empty($_REQUEST['filter-by-type'])) {
            $product_type = sanitize_text_field(wp_unslash($_REQUEST['filter-by-type']));
            if (in_array($product_type, ['simple', 'variable', 'grouped', 'external'])) {
                // WooCommerce stores product type in taxonomy
                $tax_queries[] = [
                    'taxonomy' => 'product_type',
                    'field'    => 'slug',
                    'terms'    => $product_type,
                ];
            }
        }

        // Add tax_query if we have any tax queries
        if (!empty($tax_queries)) {
            if (count($tax_queries) > 1) {
                $args['tax_query'] = [
                    'relation' => 'AND',
                ];
                $args['tax_query'] = array_merge($args['tax_query'], $tax_queries);
            } else {
                $args['tax_query'] = $tax_queries;
            }
        }

        // Add stock status filter
        if (isset($_REQUEST['filter-by-stock-status']) && !empty($_REQUEST['filter-by-stock-status'])) {
            $stock_status = sanitize_text_field(wp_unslash($_REQUEST['filter-by-stock-status']));
            if (in_array($stock_status, ['instock', 'outofstock', 'onbackorder'])) {
                $args['meta_query'][] = [
                    'key' => '_stock_status',
                    'value' => $stock_status,
                    'compare' => '=',
                ];
            }
        }

        // Handle meta_query relation if we have multiple meta queries
        if (isset($args['meta_query']) && count($args['meta_query']) > 1) {
            $args['meta_query']['relation'] = 'AND';
        }

        // Enable ordering by location assignment
        $this->ordering_by_location = true;
        add_filter('posts_clauses', [$this, 'order_by_location_assignment'], 10, 2);

        $query = new WP_Query($args);

        // Remove the filter after query
        remove_filter('posts_clauses', [$this, 'order_by_location_assignment'], 10);
        $this->ordering_by_location = false;

        $this->items = [];

        if ($query->have_posts()) {
            $brand_taxonomy = null;
            $brand_taxonomies = ['product_brand', 'pa_brand', 'pwb-brand'];
            foreach ($brand_taxonomies as $brand_taxonomy_candidate) {
                if (taxonomy_exists($brand_taxonomy_candidate)) {
                    $brand_taxonomy = $brand_taxonomy_candidate;
                    break;
                }
            }

            $posts = is_array($query->posts) ? $query->posts : [];
            $product_ids = array_map('intval', (array) wp_list_pluck($posts, 'ID'));
            $is_classic_mode = $this->is_classic_mode();
            $include_quick_edit_data = $is_classic_mode && $this->user_can_manage_products();
            $product_types = wc_get_product_types();
            $date_format = get_option('date_format');
            $weight_unit = get_option('woocommerce_weight_unit');
            $dimension_unit = get_option('woocommerce_dimension_unit');

            $show_modern_descriptive_fields = !$is_classic_mode;
            $show_modern_variations = !$is_classic_mode;

            $location_terms_by_product = [];
            if (!empty($product_ids)) {
                // Prime product meta before wc_get_product() calls below.
                update_meta_cache('post', $product_ids);
                update_object_term_cache($product_ids, 'product');

                $bulk_location_terms = wp_get_object_terms($product_ids, 'mulopimfwc_store_location', ['fields' => 'all_with_object_id']);
                if (!is_wp_error($bulk_location_terms)) {
                    foreach ((array) $bulk_location_terms as $location_term) {
                        $object_id = isset($location_term->object_id) ? (int) $location_term->object_id : 0;
                        if ($object_id <= 0) {
                            continue;
                        }
                        if (!isset($location_terms_by_product[$object_id])) {
                            $location_terms_by_product[$object_id] = [];
                        }
                        $location_terms_by_product[$object_id][] = $location_term;
                    }
                }
            }

            $products_by_id = [];
            $variation_ids_by_product = [];
            $all_variation_ids = [];
            foreach ($product_ids as $product_id) {
                $product = wc_get_product($product_id);
                if (!$product) {
                    continue;
                }

                $products_by_id[$product_id] = $product;

                if ($product->get_type() === 'variable') {
                    $variation_ids = array_map('intval', (array) $product->get_children());
                    $variation_ids_by_product[$product_id] = $variation_ids;
                    if (!empty($variation_ids)) {
                        $all_variation_ids = array_merge($all_variation_ids, $variation_ids);
                    }
                }
            }

            $all_variation_ids = array_values(array_unique($all_variation_ids));
            if (!empty($all_variation_ids)) {
                update_meta_cache('post', $all_variation_ids);
            }

            $meta_rows_by_post = [];
            $get_cached_meta = static function ($post_id, $meta_key) use (&$meta_rows_by_post) {
                $post_id = (int) $post_id;
                $meta_key = (string) $meta_key;
                if ($post_id <= 0 || $meta_key === '') {
                    return '';
                }

                if (!isset($meta_rows_by_post[$post_id])) {
                    $meta_rows_by_post[$post_id] = get_post_meta($post_id);
                }

                if (!isset($meta_rows_by_post[$post_id][$meta_key][0])) {
                    return '';
                }

                return maybe_unserialize($meta_rows_by_post[$post_id][$meta_key][0]);
            };

            $location_term_lookup = [];
            global $mulopimfwc_locations;
            if (!is_wp_error($mulopimfwc_locations) && !empty($mulopimfwc_locations)) {
                $filtered_locations = $this->filter_location_terms_for_user($mulopimfwc_locations);
                foreach ($filtered_locations as $location_term) {
                    $location_term_lookup[(int) $location_term->term_id] = $location_term;
                }
            }
            foreach ($location_terms_by_product as $location_terms) {
                foreach ($location_terms as $location_term) {
                    $location_term_lookup[(int) $location_term->term_id] = $location_term;
                }
            }

            foreach ($posts as $post) {
                $product_id = isset($post->ID) ? (int) $post->ID : 0;
                if ($product_id <= 0 || !isset($products_by_id[$product_id])) {
                    continue;
                }

                $product = $products_by_id[$product_id];
                $post_status = isset($post->post_status) ? (string) $post->post_status : get_post_status($product_id);

                $thumbnail = $product->get_image('thumbnail', ['class' => 'product-thumbnail']);
                $raw_location_terms = isset($location_terms_by_product[$product_id]) && is_array($location_terms_by_product[$product_id])
                    ? $location_terms_by_product[$product_id]
                    : [];
                $location_terms = $this->filter_location_terms_for_user($raw_location_terms);

                $product_type = $product->get_type();
                $type_label = isset($product_types[$product_type]) ? $product_types[$product_type] : ucfirst($product_type);

                $variations = [];
                $variation_ids = isset($variation_ids_by_product[$product_id]) ? $variation_ids_by_product[$product_id] : [];
                if ($show_modern_variations && $product_type === 'variable' && !empty($variation_ids)) {
                    foreach ($variation_ids as $variation_id) {
                        $variation_product = wc_get_product($variation_id);
                        if (!$variation_product) {
                            continue;
                        }

                        $variation_attributes = [];
                        foreach ((array) $variation_product->get_attributes() as $attribute_key => $attribute_value) {
                            $normalized_attribute_key = strpos((string) $attribute_key, 'attribute_') === 0
                                ? (string) $attribute_key
                                : 'attribute_' . (string) $attribute_key;
                            $variation_attributes[$normalized_attribute_key] = $attribute_value;
                        }

                        $variation_stock_quantity = $variation_product->get_stock_quantity();
                        $variations[] = [
                            'id' => $variation_id,
                            'attributes' => $variation_attributes,
                            'regular_price' => $variation_product->get_regular_price(),
                            'sale_price' => $variation_product->get_sale_price(),
                            'price' => $variation_product->get_price(),
                            'stock' => $variation_product->is_in_stock()
                                ? (is_numeric($variation_stock_quantity) ? (int) $variation_stock_quantity : 0)
                                : 0,
                        ];
                    }
                }

                $item = [
                    'id' => $product_id,
                    'title' => $product->get_name(),
                    'post_status' => $post_status,
                    'image' => $thumbnail,
                    'location_terms' => $location_terms,
                    'type' => $product_type,
                    'type_label' => $type_label,
                    'variations' => ($show_modern_variations && $product_type === 'variable') ? $variations : [],
                ];

                if ($show_modern_descriptive_fields) {
                    $item['sku'] = (string) $product->get_sku();
                    $item['categories'] = $this->get_product_term_list($product_id, 'product_cat');
                    $item['tags'] = $this->get_product_term_list($product_id, 'product_tag');
                    $item['brands'] = $this->get_product_term_list($product_id, $brand_taxonomy);
                    $item['short_description'] = wp_trim_words(wp_strip_all_tags($product->get_short_description()), 20, '...');
                    $item['description'] = wp_trim_words(wp_strip_all_tags($product->get_description()), 30, '...');
                    $item['featured'] = $product->is_featured();

                    $dimensions_output = [];
                    if ($product->has_weight()) {
                        $weight = $product->get_weight();
                        $dimensions_output[] = esc_html__('Weight:', 'multi-location-product-and-inventory-management') . ' ' . esc_html($weight . ($weight_unit ? ' ' . $weight_unit : ''));
                    }
                    if ($product->has_dimensions()) {
                        $length = $product->get_length();
                        $width = $product->get_width();
                        $height = $product->get_height();
                        $size = trim($length . ' x ' . $width . ' x ' . $height);
                        if ($size !== '') {
                            if ($dimension_unit) {
                                $size .= ' ' . $dimension_unit;
                            }
                            $dimensions_output[] = esc_html__('Size:', 'multi-location-product-and-inventory-management') . ' ' . esc_html($size);
                        }
                    }
                    $item['dimensions'] = !empty($dimensions_output) ? implode('<br>', $dimensions_output) : '';
                    $item['date'] = get_the_date($date_format, $product_id);
                }

                if ($include_quick_edit_data) {
                    $assigned_location_ids = array_map('intval', (array) wp_list_pluck($location_terms, 'term_id'));
                    $product_data = [
                        'id' => $product_id,
                        'name' => $product->get_name(),
                        'type' => $product_type,
                        'product_type' => $product_type,
                        'default' => [
                            'manage_stock' => $product->get_manage_stock() ? 'yes' : 'no',
                            'stock_quantity' => $product->get_stock_quantity(),
                            'regular_price' => $product->get_regular_price(),
                            'sale_price' => $product->get_sale_price(),
                            'backorders' => $product->get_backorders(),
                            'purchase_price' => $get_cached_meta($product_id, '_purchase_price'),
                            'purchase_quantity' => $get_cached_meta($product_id, '_purchase_quantity'),
                        ],
                        'locations' => [],
                        'variations' => [],
                    ];

                    foreach ($assigned_location_ids as $location_id) {
                        if (!isset($location_term_lookup[$location_id])) {
                            continue;
                        }

                        $location_term = $location_term_lookup[$location_id];
                        $product_data['locations'][] = [
                            'id' => $location_id,
                            'name' => $location_term->name,
                            'stock' => $get_cached_meta($product_id, '_location_stock_' . $location_id),
                            'regular_price' => $get_cached_meta($product_id, '_location_regular_price_' . $location_id),
                            'sale_price' => $get_cached_meta($product_id, '_location_sale_price_' . $location_id),
                            'backorders' => $get_cached_meta($product_id, '_location_backorders_' . $location_id),
                        ];
                    }

                    if ($product_type === 'variable' && !empty($variation_ids)) {
                        foreach ($variation_ids as $variation_id) {
                            $variation_product = wc_get_product($variation_id);
                            if (!$variation_product) {
                                continue;
                            }

                            $variation_data = [
                                'id' => $variation_id,
                                'attributes' => (array) $variation_product->get_attributes(),
                                'default' => [
                                    'manage_stock' => $variation_product->get_manage_stock() ? 'yes' : 'no',
                                    'stock_quantity' => $variation_product->get_stock_quantity(),
                                    'regular_price' => $variation_product->get_regular_price(),
                                    'sale_price' => $variation_product->get_sale_price(),
                                    'backorders' => $variation_product->get_backorders(),
                                    'purchase_price' => $get_cached_meta($variation_id, '_purchase_price'),
                                    'purchase_quantity' => $get_cached_meta($variation_id, '_purchase_quantity'),
                                ],
                                'locations' => [],
                            ];

                            foreach ($assigned_location_ids as $location_id) {
                                if (!isset($location_term_lookup[$location_id])) {
                                    continue;
                                }

                                $location_term = $location_term_lookup[$location_id];
                                $variation_data['locations'][] = [
                                    'id' => $location_id,
                                    'name' => $location_term->name,
                                    'stock' => $get_cached_meta($variation_id, '_location_stock_' . $location_id),
                                    'regular_price' => $get_cached_meta($variation_id, '_location_regular_price_' . $location_id),
                                    'sale_price' => $get_cached_meta($variation_id, '_location_sale_price_' . $location_id),
                                    'backorders' => $get_cached_meta($variation_id, '_location_backorders_' . $location_id),
                                ];
                            }

                            $product_data['variations'][] = $variation_data;
                        }
                    }

                    $item['quick_edit_data'] = $product_data;
                }

                $this->items[] = $item;
            }
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
    public function get_sortable_columns()
    {
        return [
            'title' => ['title', false],
        ];
    }

    /**
     * Extra controls to be displayed between bulk actions and pagination
     *
     * @param string $which Position (top or bottom)
     */
    protected function extra_tablenav($which)
    {
        global $mulopimfwc_locations;
        $available_locations = [];
        if (!is_wp_error($mulopimfwc_locations) && !empty($mulopimfwc_locations)) {
            $available_locations = $this->filter_location_terms_for_user($mulopimfwc_locations);
        }

        if ($which == 'top') {
            // Filters section
            echo '<div class="alignleft actions filters-section">';
            
            // Location filter
            if (!empty($available_locations)) {
                $selected_location = isset($_REQUEST['filter-by-location']) ? sanitize_text_field(wp_unslash($_REQUEST['filter-by-location'])) : '';
                echo '<select name="filter-by-location" id="filter-by-location">';
                echo '<option value="">' . esc_html__('All Locations', 'multi-location-product-and-inventory-management') . '</option>';
                foreach ($available_locations as $location) {
                    $location_slug = rawurldecode($location->slug);
                    $selected = ($selected_location == $location_slug) ? 'selected="selected"' : '';
                    echo '<option value="' . esc_attr($location_slug) . '" ' . esc_attr($selected) . '>' . esc_html($location->name) . '</option>';
                }
                echo '</select>';
            }

            // Category filter
            $categories = get_terms([
                'taxonomy' => 'product_cat',
                'hide_empty' => false,
            ]);
            if (!is_wp_error($categories) && !empty($categories)) {
                $selected_category = isset($_REQUEST['filter-by-category']) ? intval($_REQUEST['filter-by-category']) : '';
                echo '<select name="filter-by-category" id="filter-by-category">';
                echo '<option value="">' . esc_html__('All Categories', 'multi-location-product-and-inventory-management') . '</option>';
                foreach ($categories as $category) {
                    $selected = ($selected_category == $category->term_id) ? 'selected="selected"' : '';
                    echo '<option value="' . esc_attr($category->term_id) . '" ' . esc_attr($selected) . '>' . esc_html($category->name) . '</option>';
                }
                echo '</select>';
            }

            // Product type filter
            $selected_type = isset($_REQUEST['filter-by-type']) ? sanitize_text_field(wp_unslash($_REQUEST['filter-by-type'])) : '';
            echo '<select name="filter-by-type" id="filter-by-type">';
            echo '<option value="">' . esc_html__('All Product Types', 'multi-location-product-and-inventory-management') . '</option>';
            echo '<option value="simple" ' . selected($selected_type, 'simple', false) . '>' . esc_html__('Simple', 'multi-location-product-and-inventory-management') . '</option>';
            echo '<option value="variable" ' . selected($selected_type, 'variable', false) . '>' . esc_html__('Variable', 'multi-location-product-and-inventory-management') . '</option>';
            echo '<option value="grouped" ' . selected($selected_type, 'grouped', false) . '>' . esc_html__('Grouped', 'multi-location-product-and-inventory-management') . '</option>';
            echo '<option value="external" ' . selected($selected_type, 'external', false) . '>' . esc_html__('External', 'multi-location-product-and-inventory-management') . '</option>';
            echo '</select>';

            // Stock status filter
            $selected_stock = isset($_REQUEST['filter-by-stock-status']) ? sanitize_text_field(wp_unslash($_REQUEST['filter-by-stock-status'])) : '';
            echo '<select name="filter-by-stock-status" id="filter-by-stock-status">';
            echo '<option value="">' . esc_html__('All Stock Statuses', 'multi-location-product-and-inventory-management') . '</option>';
            echo '<option value="instock" ' . selected($selected_stock, 'instock', false) . '>' . esc_html__('In Stock', 'multi-location-product-and-inventory-management') . '</option>';
            echo '<option value="outofstock" ' . selected($selected_stock, 'outofstock', false) . '>' . esc_html__('Out of Stock', 'multi-location-product-and-inventory-management') . '</option>';
            echo '<option value="onbackorder" ' . selected($selected_stock, 'onbackorder', false) . '>' . esc_html__('On Backorder', 'multi-location-product-and-inventory-management') . '</option>';
            echo '</select>';

            // Brand filter (check for common brand taxonomies)
            $brand_taxonomies = ['product_brand', 'pa_brand', 'pwb-brand'];
            $brand_taxonomy = null;
            foreach ($brand_taxonomies as $tax) {
                if (taxonomy_exists($tax)) {
                    $brand_taxonomy = $tax;
                    break;
                }
            }
            if ($brand_taxonomy) {
                $brands = get_terms([
                    'taxonomy' => $brand_taxonomy,
                    'hide_empty' => false,
                ]);
                if (!is_wp_error($brands) && !empty($brands)) {
                    $selected_brand = isset($_REQUEST['filter-by-brand']) ? intval($_REQUEST['filter-by-brand']) : '';
                    echo '<select name="filter-by-brand" id="filter-by-brand">';
                    echo '<option value="">' . esc_html__('All Brands', 'multi-location-product-and-inventory-management') . '</option>';
                    foreach ($brands as $brand) {
                        $selected = ($selected_brand == $brand->term_id) ? 'selected="selected"' : '';
                        echo '<option value="' . esc_attr($brand->term_id) . '" ' . esc_attr($selected) . '>' . esc_html($brand->name) . '</option>';
                    }
                    echo '</select>';
                }
            }

            // Add nonce field for the filter form
            wp_nonce_field('bulk-' . $this->_args['plural']);

            echo '<input type="submit" name="filter_action" id="filter-submit" class="button" value="' . esc_attr__('Filter', 'multi-location-product-and-inventory-management') . '">';
            echo '</div>';

            // Bulk actions section - location selector for bulk actions
            if ($this->user_can_manage_products()) {
                echo '<div class="alignleft actions bulk-actions-section">';
                if (!empty($available_locations)) {
                    $selected_bulk_location = isset($_REQUEST['bulk_location_id']) ? intval($_REQUEST['bulk_location_id']) : '';
                    echo '<label for="bulk-location-id" style="margin-right: 5px;">' . esc_html__('Location for Bulk Actions:', 'multi-location-product-and-inventory-management') . '</label>';
                    echo '<select name="bulk_location_id" id="bulk-location-id">';
                    echo '<option value="">' . esc_html__('Select Location', 'multi-location-product-and-inventory-management') . '</option>';
                    foreach ($available_locations as $location) {
                        $selected = ($selected_bulk_location == $location->term_id) ? 'selected="selected"' : '';
                        echo '<option value="' . esc_attr($location->term_id) . '" ' . esc_attr($selected) . '>' . esc_html($location->name) . '</option>';
                    }
                    echo '</select>';
                }
                echo '</div>';
            }
        }
    }

    /**
     * Modify SQL query to order by location assignment count
     *
     * @param array $clauses Query clauses
     * @param WP_Query $query The query object
     * @return array Modified clauses
     */
    public function order_by_location_assignment($clauses, $query)
    {
        // Only apply to our specific query
        if (!$this->ordering_by_location) {
            return $clauses;
        }

        global $wpdb;

        // Ensure join clause exists
        if (!isset($clauses['join'])) {
            $clauses['join'] = '';
        }

        // Add LEFT JOIN to count location assignments
        $clauses['join'] .= " LEFT JOIN (
            SELECT tr.object_id, COUNT(tr.term_taxonomy_id) as location_count
            FROM {$wpdb->term_relationships} tr
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            WHERE tt.taxonomy = 'mulopimfwc_store_location'
            GROUP BY tr.object_id
        ) as location_counts ON {$wpdb->posts}.ID = location_counts.object_id";

        // Modify ORDER BY to sort by location count (DESC) then by post title (ASC)
        $orderby = "COALESCE(location_counts.location_count, 0) DESC, {$wpdb->posts}.post_title ASC";
        
        if (!empty($clauses['orderby'])) {
            $clauses['orderby'] = $orderby . ', ' . $clauses['orderby'];
        } else {
            $clauses['orderby'] = $orderby;
        }

        return $clauses;
    }
}
