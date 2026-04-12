<?php

if (!defined('ABSPATH')) exit;

class MULOPIMFWC_Dashboard
{
    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('wp_ajax_mulopimfwc_apply_filters', array($this, 'apply_dashboard_filters'));
        add_action('wp_ajax_mulopimfwc_dashboard_profitability', array($this, 'handle_profitability_panel_request'));
        add_action('wp_ajax_mulopimfwc_dashboard_deferred_data', array($this, 'handle_deferred_dashboard_data'));
        add_action('wp_ajax_mulopimfwc_dashboard_investment_data', array($this, 'handle_deferred_dashboard_investment_data'));
    }

    /**
     * Normalize a location slug for consistent comparisons.
     */
    private function normalize_location_slug($location_slug): string
    {
        return sanitize_title(rawurldecode((string) $location_slug));
    }

    /**
     * AJAX endpoint for deferred (heavy) dashboard data loaded after page render.
     */
    public function handle_deferred_dashboard_data()
    {
        check_ajax_referer('mulopimfwc_dashboard_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Permission denied', 'multi-location-product-and-inventory-management-pro')]);
            return;
        }

        global $mulopimfwc_locations;
        $mulopimfwc_locations = $this->get_dashboard_scoped_locations();

        if (function_exists('ini_set')) {
            ini_set('memory_limit', '512M');
        }
        set_time_limit(300);

        $payload = $this->get_dashboard_orders_payload_cached();
        $summary = isset($payload['summary']) && is_array($payload['summary']) ? $payload['summary'] : [];
        $recent_products = isset($payload['recent_products_data']) && is_array($payload['recent_products_data'])
            ? $payload['recent_products_data']
            : ['labels' => [], 'counts' => []];

        $response = [
            'orders_by_location'     => $payload['orders_by_location'] ?? [],
            'revenue_by_location'    => $payload['revenue_by_location'] ?? [],
            'recent_products'        => $recent_products,
            'summary_orders'         => $summary['total_orders'] ?? 0,
            'summary_sell'           => $summary['total_sell'] ?? 0,
            'summary_revenue'        => $summary['total_revenue'] ?? 0,
            'currency'               => $this->get_dashboard_reporting_currency_symbol(),
            'currency_code'          => $this->get_dashboard_reporting_currency_code(),
        ];

        wp_send_json_success($response);
    }

    /**
     * AJAX endpoint for investment and stock-alert data loaded after fast dashboard widgets.
     */
    public function handle_deferred_dashboard_investment_data()
    {
        check_ajax_referer('mulopimfwc_dashboard_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Permission denied', 'multi-location-product-and-inventory-management-pro')]);
            return;
        }

        global $mulopimfwc_locations;
        $mulopimfwc_locations = $this->get_dashboard_scoped_locations();

        if (function_exists('ini_set')) {
            ini_set('memory_limit', '512M');
        }
        set_time_limit(300);

        $payload = $this->get_dashboard_investment_payload_cached();
        $summary = isset($payload['summary']) && is_array($payload['summary']) ? $payload['summary'] : [];
        $monthly_investment = isset($payload['monthly_investment_data']) && is_array($payload['monthly_investment_data'])
            ? [
                'labels' => $payload['monthly_investment_data']['labels'] ?? [],
                'data' => $payload['monthly_investment_data']['data'] ?? [],
            ]
            : ['labels' => [], 'data' => []];
        $low_stock_products = isset($payload['low_stock_products']) && is_array($payload['low_stock_products'])
            ? array_slice($payload['low_stock_products'], 0, 8)
            : [];

        wp_send_json_success([
            'low_stock_products' => $low_stock_products,
            'total_investment' => $payload['total_investment'] ?? 0,
            'monthly_investment' => $monthly_investment,
            'summary_investment' => $summary['total_investment'] ?? 0,
            'currency' => $this->get_dashboard_reporting_currency_symbol(),
            'currency_code' => $this->get_dashboard_reporting_currency_code(),
        ]);
    }

    /**
     * Resolve dashboard reporting currency code (store base currency).
     */
    private function get_dashboard_reporting_currency_code(): string
    {
        $currency_code = function_exists('mulopimfwc_get_store_base_currency_code_raw')
            ? strtoupper(trim((string) mulopimfwc_get_store_base_currency_code_raw()))
            : strtoupper(trim((string) get_option('woocommerce_currency', 'USD')));

        if ($currency_code === '') {
            $currency_code = 'USD';
        }

        return $currency_code;
    }

    /**
     * Resolve dashboard reporting currency symbol.
     */
    private function get_dashboard_reporting_currency_symbol(): string
    {
        $currency_code = $this->get_dashboard_reporting_currency_code();
        $symbol = function_exists('get_woocommerce_currency_symbol')
            ? (string) get_woocommerce_currency_symbol($currency_code)
            : '';

        if ($symbol === '') {
            $symbol = $currency_code;
        }

        return html_entity_decode($symbol, ENT_QUOTES, get_bloginfo('charset'));
    }

    /**
     * Format a value as dashboard reporting currency.
     */
    private function format_dashboard_reporting_price($amount): string
    {
        return wc_price($amount, [
            'currency' => $this->get_dashboard_reporting_currency_code(),
        ]);
    }

    /**
     * Get assigned location slugs for the current location manager.
     *
     * Returns null for non-location-manager users.
     *
     * @return array|null
     */
    private function get_dashboard_manager_assigned_location_slugs()
    {
        if (!is_user_logged_in() || !class_exists('MULOPIMFWC_Location_Managers')) {
            return null;
        }

        $user = wp_get_current_user();
        if (!in_array('mulopimfwc_location_manager', (array) $user->roles, true)) {
            return null;
        }

        $assigned_locations = MULOPIMFWC_Location_Managers::get_user_assigned_locations();
        if (!is_array($assigned_locations)) {
            return [];
        }

        $assigned_locations = array_map([$this, 'normalize_location_slug'], $assigned_locations);
        $assigned_locations = array_values(array_filter($assigned_locations, function ($slug) {
            return is_string($slug) && $slug !== '';
        }));

        return array_values(array_unique($assigned_locations));
    }

    /**
     * Get assigned location term IDs for the current location manager.
     *
     * Returns null for non-location-manager users.
     *
     * @return array|null
     */
    private function get_dashboard_manager_assigned_location_term_ids()
    {
        $assigned_slugs = $this->get_dashboard_manager_assigned_location_slugs();
        if (!is_array($assigned_slugs)) {
            return null;
        }

        if (empty($assigned_slugs)) {
            return [];
        }

        $terms = get_terms([
            'taxonomy' => 'mulopimfwc_store_location',
            'hide_empty' => false,
            'slug' => $assigned_slugs,
            'fields' => 'ids',
        ]);

        if (is_wp_error($terms)) {
            return [];
        }

        return array_values(array_map('intval', (array) $terms));
    }

    /**
     * Normalize and scope a dashboard location filter value from request input.
     */
    private function resolve_dashboard_location_filter($location_raw): string
    {
        if (is_array($location_raw)) {
            $location_raw = reset($location_raw);
        }

        $location_filter = is_string($location_raw)
            ? sanitize_text_field(rawurldecode($location_raw))
            : 'all';
        $location_filter = $this->normalize_location_slug($location_filter);

        if ($location_filter === '') {
            $location_filter = 'all';
        }

        $manager_location_slugs = $this->get_dashboard_manager_assigned_location_slugs();
        if (is_array($manager_location_slugs)) {
            if (empty($manager_location_slugs)) {
                return '__none__';
            }

            if ($location_filter === 'default') {
                return '__none__';
            }

            if ($location_filter !== 'all' && !in_array($location_filter, $manager_location_slugs, true)) {
                return '__none__';
            }
        }

        return $location_filter;
    }

    /**
     * Return current dashboard locations scoped to assigned locations for location managers.
     *
     * @return array
     */
    private function get_dashboard_scoped_locations(): array
    {
        global $mulopimfwc_locations;

        $locations = is_array($mulopimfwc_locations) && !is_wp_error($mulopimfwc_locations)
            ? $mulopimfwc_locations
            : [];

        if (empty($locations)) {
            $terms = get_terms([
                'taxonomy' => 'mulopimfwc_store_location',
                'hide_empty' => false,
            ]);
            if (!is_wp_error($terms) && is_array($terms)) {
                $locations = $terms;
            }
        }

        $assigned_slugs = $this->get_dashboard_manager_assigned_location_slugs();
        if (!is_array($assigned_slugs)) {
            return $locations;
        }

        if (empty($assigned_slugs)) {
            return [];
        }

        return array_values(array_filter($locations, function ($location) use ($assigned_slugs) {
            if (!is_object($location) || !isset($location->slug)) {
                return false;
            }

            return in_array($this->normalize_location_slug($location->slug), $assigned_slugs, true);
        }));
    }

    /**
     * Export dashboard report as Excel/CSV
     */
    public function export_dashboard_report()
    {

        // Verify nonce for security
        check_ajax_referer('mulopimfwc_export_nonce', 'nonce');

        if (isset($_POST['format']) && $_POST['format'] === "html") {
            $this->export_dashboard_report_html();
        } else {
            $this->export_dashboard_report_csv();
        }
    }

    public function export_dashboard_report_csv()
    {
        // Verify nonce for security
        check_ajax_referer('mulopimfwc_export_nonce', 'nonce');

        // Check user permissions
        if (!MULOPIMFWC_Location_Managers::user_has_capability('export_report')) {
            wp_send_json_error(array('message' => __('Permission denied', 'multi-location-product-and-inventory-management-pro')));
        }

        global $mulopimfwc_locations, $wpdb;
        $mulopimfwc_locations = $this->get_dashboard_scoped_locations();

        if (function_exists('ini_set')) {
            ini_set('memory_limit', '512M');
        }
        set_time_limit(300);

        // ---------- Build location lists (names & IDs) ----------
        $locations = [];
        $location_ids = [];
        if (!empty($mulopimfwc_locations) && !is_wp_error($mulopimfwc_locations)) {
            foreach ($mulopimfwc_locations as $l) {
                $locations[]    = $l->name;     // human label (column header)
                $location_ids[] = (int) $l->term_id;
            }
        }
        // Always include "Default"
        $locations_with_default = array_merge($locations, ['Default']);

        // ---------- Products by location (incl. Default) ----------
        $product_counts = [];
        foreach ($mulopimfwc_locations as $location) {
            $product_counts[$location->name] = $this->get_location_product_count($location->term_id);
        }
        // Default = products without any mulopimfwc_store_location term
        $product_counts['Default'] = $this->get_default_product_count();

        // ---------- Stock level by location (incl. Default) ----------
        $stock_levels = [];
        foreach ($mulopimfwc_locations as $location) {
            $stock_levels[$location->name] = $this->get_location_stock_level($location->term_id);
        }
        // Default = fall back to global _stock for products with NO location term
        $stock_levels['Default'] = $this->get_default_stock_level();

        // ---------- Orders / revenue / low stock / totals ----------
        $orders_data          = $this->get_orders_data_efficiently();
        $low_stock_products   = $this->get_low_stock_products_efficiently();
        $total_investment     = $this->calculate_total_investment_efficiently();

        // ---------- New products (last 30 days) — location-wise matrix ----------
        // Returns: ['labels'=>[dates...], 'columns'=>[ 'Cumilla'=>[], 'Kandirpar'=>[], 'Default'=>[], ...], 'totals'=>[]]
        $recent_matrix = $this->get_recent_products_data_by_location($location_ids, $locations);

        // ---------- CSV headers ----------
        $filename = 'location-wise-report-' . gmdate('Y-m-d-H-i-s') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        // UTF-8 BOM
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // ---------- Report header ----------
        fputcsv($output, array(__('LOCATION WISE PRODUCT & INVENTORY DASHBOARD REPORT', 'multi-location-product-and-inventory-management-pro')));
        fputcsv($output, array(__('Generated on:', 'multi-location-product-and-inventory-management-pro'), gmdate('l, F d, Y - H:i:s')));
        fputcsv($output, array(__('Store:', 'multi-location-product-and-inventory-management-pro'), get_bloginfo('name')));
        fputcsv($output, array(__('Currency:', 'multi-location-product-and-inventory-management-pro'), ' (' . $this->get_dashboard_reporting_currency_code() . ')'));
        fputcsv($output, array('')); // Empty row

        // ---------- Summary ----------
        fputcsv($output, [__('SUMMARY STATISTICS', 'multi-location-product-and-inventory-management-pro')]);
        fputcsv($output, [__('Metric', 'multi-location-product-and-inventory-management-pro'), __('Value', 'multi-location-product-and-inventory-management-pro')]);
        fputcsv($output, [__('Total Products', 'multi-location-product-and-inventory-management-pro'), $this->get_total_products_count()]);
        fputcsv($output, [__('Total Locations', 'multi-location-product-and-inventory-management-pro'), count($mulopimfwc_locations)]);
        fputcsv($output, [__('Total Orders', 'multi-location-product-and-inventory-management-pro'), array_sum($orders_data['orders'])]);
        fputcsv($output, [__('Total Revenue', 'multi-location-product-and-inventory-management-pro'), number_format(array_sum($orders_data['revenue']), 2)]);
        fputcsv($output, [__('Total Investment', 'multi-location-product-and-inventory-management-pro'), number_format($total_investment, 2)]);
        fputcsv($output, [__('Total Stock', 'multi-location-product-and-inventory-management-pro'), array_sum($stock_levels)]);
        fputcsv($output, ['']);

        // ---------- Products by Location (incl. Default) ----------
        fputcsv($output, [__('PRODUCTS BY LOCATION', 'multi-location-product-and-inventory-management-pro')]);
        fputcsv($output, array(__('Location', 'multi-location-product-and-inventory-management-pro'), __('Product Count', 'multi-location-product-and-inventory-management-pro'), __('Percentage', 'multi-location-product-and-inventory-management-pro')));
        $total_products = array_sum($product_counts);
        foreach ($locations_with_default as $loc_name) {
            $count = isset($product_counts[$loc_name]) ? $product_counts[$loc_name] : 0;
            $percentage = $total_products > 0 ? round(($count / $total_products) * 100, 2) : 0;
            fputcsv($output, array($loc_name, $count, $percentage . '%'));
        }
        fputcsv($output, ['']);

        // ---------- Stock Levels by Location (incl. Default) ----------
        fputcsv($output, [__('STOCK LEVELS BY LOCATION', 'multi-location-product-and-inventory-management-pro')]);
        fputcsv($output, array(__('Location', 'multi-location-product-and-inventory-management-pro'), __('Stock Level', 'multi-location-product-and-inventory-management-pro'), __('Percentage', 'multi-location-product-and-inventory-management-pro')));

        $total_stocks = array_sum($stock_levels);
        foreach ($locations_with_default as $loc_name) {
            $count = isset($stock_levels[$loc_name]) ? $stock_levels[$loc_name] : 0;
            $percentage = $total_stocks > 0 ? round(($count / $total_stocks) * 100, 2) : 0;
            fputcsv($output, array($loc_name, $count, $percentage . '%'));
        }
        fputcsv($output, ['']);

        // ---------- Orders by Location ----------
        fputcsv($output, [__('Orders by Location', 'multi-location-product-and-inventory-management-pro')]);
        fputcsv($output, array(__('Location', 'multi-location-product-and-inventory-management-pro'), __('Orders', 'multi-location-product-and-inventory-management-pro'), __('Percentage', 'multi-location-product-and-inventory-management-pro')));
        $total_orders = array_sum($orders_data['orders']);
        foreach ($orders_data['orders'] as $location => $orders) {
            $percentage = $total_orders > 0 ? round(($orders / $total_orders) * 100, 2) : 0;
            fputcsv($output, array($location, $orders, $percentage . '%'));
        }
        fputcsv($output, ['']);

        // ---------- Revenue by Location ----------
        fputcsv($output, [__('Revenue by Location', 'multi-location-product-and-inventory-management-pro')]);
        fputcsv($output, array(__('Location', 'multi-location-product-and-inventory-management-pro'), __('Revenue', 'multi-location-product-and-inventory-management-pro'), __('Percentage', 'multi-location-product-and-inventory-management-pro')));
        $total_revenue = array_sum($orders_data['revenue']);
        foreach ($orders_data['revenue'] as $location => $revenue) {
            $percentage = $total_revenue > 0 ? round(($revenue / $total_revenue) * 100, 2) : 0;
            fputcsv($output, array($location, number_format($revenue, 2), $percentage . '%'));
        }
        fputcsv($output, ['']);

        $profitability_data = $this->get_location_profitability_data();
        if (!empty($profitability_data)) {
            fputcsv($output, [__('PROFITABILITY & AGING BY LOCATION', 'multi-location-product-and-inventory-management-pro')]);
            fputcsv($output, [
                __('Location', 'multi-location-product-and-inventory-management-pro'),
                __('Inventory Value', 'multi-location-product-and-inventory-management-pro'),
                __('Margin Value', 'multi-location-product-and-inventory-management-pro'),
                __('Margin %', 'multi-location-product-and-inventory-management-pro'),
                __('Dead Stock Value', 'multi-location-product-and-inventory-management-pro'),
                __('Dead Stock Units', 'multi-location-product-and-inventory-management-pro'),
                __('Avg Age (days)', 'multi-location-product-and-inventory-management-pro'),
                __('Shrinkage %', 'multi-location-product-and-inventory-management-pro')
            ]);

            foreach ($profitability_data as $summary) {
                fputcsv($output, [
                    $summary['location_name'],
                    number_format($summary['inventory_value'], 2),
                    number_format($summary['margin_value'], 2),
                    number_format($summary['margin_rate'], 2) . '%',
                    number_format($summary['dead_stock_value'], 2),
                    number_format($summary['dead_stock_units'], 2),
                    number_format($summary['average_age_days'], 1),
                    number_format($summary['shrinkage_rate'], 2) . '%'
                ]);
            }

            fputcsv($output, ['']);
        }

        // ---------- Low Stock ----------
        if (!empty($low_stock_products)) {
            fputcsv($output, [__('LOW STOCK PRODUCTS', 'multi-location-product-and-inventory-management-pro')]);
            fputcsv($output, [
                __('Product', 'multi-location-product-and-inventory-management-pro'),
                __('Location', 'multi-location-product-and-inventory-management-pro'),
                __('Stock', 'multi-location-product-and-inventory-management-pro'),
                __('Status', 'multi-location-product-and-inventory-management-pro')
            ]);
            foreach ($low_stock_products as $item) {
                $status = $item['stock'] == 0 ? __('⚠ Out of Stock', 'multi-location-product-and-inventory-management-pro') : __('⚡ Low Stock', 'multi-location-product-and-inventory-management-pro');
                $status = isset($item['status_label']) ? $item['status_label'] : $status;
                fputcsv($output, [$item['product_title'], $item['location_name'], $item['stock'], $status]);
            }
            fputcsv($output, ['']);
        }

        // ---------- New Products (Last 30 Days) — location-wise ----------
        // Header row: Date | <each location> | Default | Total Added
        fputcsv($output, [__('NEW PRODUCTS (LAST 30 DAYS) — LOCATION-WISE', 'multi-location-product-and-inventory-management-pro')]);
        $header = array_merge([__('Date', 'multi-location-product-and-inventory-management-pro')], $locations, ['Default', __('Total Added', 'multi-location-product-and-inventory-management-pro')]);
        fputcsv($output, $header);

        foreach ($recent_matrix['labels'] as $i => $label) {
            $row = [$label];
            // each physical location in order
            foreach ($locations as $loc_name) {
                $row[] = isset($recent_matrix['columns'][$loc_name][$i]) ? $recent_matrix['columns'][$loc_name][$i] : 0;
            }
            // Default
            $row[] = isset($recent_matrix['columns']['Default'][$i]) ? $recent_matrix['columns']['Default'][$i] : 0;
            // Totals (per date)
            $row[] = $recent_matrix['totals'][$i];
            fputcsv($output, $row);
        }

        // Report Footer
        fputcsv($output, array('═══════════════════════════════════════════════════════════════'));
        fputcsv($output, array(__('End of Report', 'multi-location-product-and-inventory-management-pro')));
        fputcsv($output, array(__('Thank you for using Multi Location Product & Inventory Management for WooCommerce Pro!', 'multi-location-product-and-inventory-management-pro')));
        fputcsv($output, array('═══════════════════════════════════════════════════════════════'));

        fclose($output);
        exit;
    }

    public function export_dashboard_report_html()
    {
        // Verify nonce for security
        check_ajax_referer('mulopimfwc_export_nonce', 'nonce');

        // Check user permissions
        if (!MULOPIMFWC_Location_Managers::user_has_capability('export_report')) {
            wp_send_json_error(array('message' => __('Permission denied', 'multi-location-product-and-inventory-management-pro')));
        }

        global $mulopimfwc_locations, $wpdb;
        $mulopimfwc_locations = $this->get_dashboard_scoped_locations();

        if (function_exists('ini_set')) {
            ini_set('memory_limit', '512M');
        }
        set_time_limit(300);

        // FIXED: Add error handling wrapper
        try {

            // ---------- Build location lists (names & IDs) ----------
            $locations = [];
            $location_ids = [];
            if (!empty($mulopimfwc_locations) && !is_wp_error($mulopimfwc_locations)) {
                foreach ($mulopimfwc_locations as $l) {
                    $locations[]    = $l->name;
                    $location_ids[] = (int) $l->term_id;
                }
            }
            $locations_with_default = array_merge($locations, ['Default']);

            // ---------- Products by location (incl. Default) ----------
            $product_counts = [];
            foreach ($mulopimfwc_locations as $location) {
                $product_counts[$location->name] = $this->get_location_product_count($location->term_id);
            }
            $product_counts['Default'] = $this->get_default_product_count();

            // ---------- Stock level by location (incl. Default) ----------
            $stock_levels = [];
            foreach ($mulopimfwc_locations as $location) {
                $stock_levels[$location->name] = $this->get_location_stock_level($location->term_id);
            }
            $stock_levels['Default'] = $this->get_default_stock_level();

            // ---------- Orders / revenue / low stock / totals ----------
            $orders_data          = $this->get_orders_data_efficiently();
            $low_stock_products   = $this->get_low_stock_products_efficiently();
            $total_investment     = $this->calculate_total_investment_efficiently();

            // ---------- New products (last 30 days) — location-wise matrix ----------
            $recent_matrix = $this->get_recent_products_data_by_location($location_ids, $locations);

            // ---------- HTML/Excel headers ----------
            $filename = 'location-wise-report-' . gmdate('Y-m-d-H-i-s') . '.xls';
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');

            // Start HTML output with UTF-8 BOM.
            echo esc_html("\xEF\xBB\xBF");

?>
            <!DOCTYPE html>
            <html>

            <head>
                <meta charset="UTF-8">
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        font-size: 11pt;
                    }

                    table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-bottom: 20px;
                        background-color: #ffffff;
                    }

                    th {
                        background-color: #5b21b6;
                        color: #ffffff;
                        padding: 12px 10px;
                        text-align: left;
                        font-weight: bold;
                        font-size: 11pt;
                        border: 1px solid #4c1d95;
                    }

                    td {
                        padding: 10px;
                        border: 1px solid #d1d5db;
                        font-size: 10pt;
                        color: #000000;
                        background-color: #ffffff;
                    }

                    tr.even-row td {
                        background-color: #f3f4f6;
                    }

                    .summary-table td:first-child {
                        font-weight: bold;
                        color: #374151;
                        background-color: #f9fafb;
                    }

                    .percentage {
                        color: #5b21b6;
                        font-weight: bold;
                    }

                    .stock-low {
                        color: #d97706;
                        font-weight: bold;
                    }

                    .stock-out {
                        color: #dc2626;
                        font-weight: bold;
                    }

                    .status-badge {
                        padding: 4px 10px;
                        font-size: 9pt;
                        font-weight: bold;
                    }

                    .status-low {
                        background-color: #fef3c7;
                        color: #92400e;
                    }

                    .status-out {
                        background-color: #fee2e2;
                        color: #991b1b;
                    }
                </style>
            </head>

            <body>
                <!-- Report Header as Table -->
                <table style="width: 100%; margin-bottom: 20px; border-collapse: collapse;">
                    <tr>
                        <td style="background-color: #5b21b6; color: #ffffff; padding: 20px; text-align: center;" colspan="10">
                            <h1 style="margin: 0 0 10px 0; font-size: 18pt; font-weight: bold; color: #ffffff;">
                                <?php echo esc_html__('LOCATION WISE PRODUCT & INVENTORY DASHBOARD REPORT', 'multi-location-product-and-inventory-management-pro'); ?>
                            </h1>
                            <div style="font-size: 10pt; color: #ffffff; line-height: 1.5;">
                                <div><strong><?php echo esc_html__('Generated:', 'multi-location-product-and-inventory-management-pro'); ?></strong> <?php echo esc_html(gmdate('l, F d, Y - H:i:s')); ?></div>
                                <div><strong><?php echo esc_html__('Store:', 'multi-location-product-and-inventory-management-pro'); ?></strong> <?php echo esc_html(get_bloginfo('name')); ?></div>
                                <div><strong><?php echo esc_html__('Currency:', 'multi-location-product-and-inventory-management-pro'); ?></strong> <?php echo esc_html($this->get_dashboard_reporting_currency_code()); ?></div>
                            </div>
                        </td>
                    </tr>
                </table>

                <!-- Summary Statistics -->
                <table style="width: 100%; margin-bottom: 10px; border-collapse: collapse;">
                    <tr></tr>
                    <tr>
                        <td style="background-color: #5b21b6; color: #ffffff; padding: 10px 15px; font-weight: bold; font-size: 12pt;">
                            <?php echo esc_html__('SUMMARY STATISTICS', 'multi-location-product-and-inventory-management-pro'); ?>
                        </td>
                    </tr>
                </table>
                <table class="summary-table">
                    <tr>
                        <th><?php echo esc_html__('Metric', 'multi-location-product-and-inventory-management-pro'); ?></th>
                        <th><?php echo esc_html__('Value', 'multi-location-product-and-inventory-management-pro'); ?></th>
                    </tr>
                    <tr>
                        <td><?php echo esc_html__('Total Products', 'multi-location-product-and-inventory-management-pro'); ?></td>
                        <td><?php echo esc_html(number_format($this->get_total_products_count())); ?></td>
                    </tr>
                    <tr class="even-row">
                        <td><?php echo esc_html__('Total Locations', 'multi-location-product-and-inventory-management-pro'); ?></td>
                        <td><?php echo esc_html(count($mulopimfwc_locations)); ?></td>
                    </tr>
                    <tr>
                        <td><?php echo esc_html__('Total Orders', 'multi-location-product-and-inventory-management-pro'); ?></td>
                        <td><?php echo esc_html(number_format(array_sum($orders_data['orders']))); ?></td>
                    </tr>
                    <tr class="even-row">
                        <td><?php echo esc_html__('Total Revenue', 'multi-location-product-and-inventory-management-pro'); ?></td>
                        <td><?php echo esc_html($this->get_dashboard_reporting_currency_symbol() . number_format(array_sum($orders_data['revenue']), 2)); ?></td>
                    </tr>
                    <tr>
                        <td><?php echo esc_html__('Total Investment', 'multi-location-product-and-inventory-management-pro'); ?></td>
                        <td><?php echo esc_html($this->get_dashboard_reporting_currency_symbol() . number_format($total_investment, 2)); ?></td>
                    </tr>
                    <tr class="even-row">
                        <td><?php echo esc_html__('Total Stock', 'multi-location-product-and-inventory-management-pro'); ?></td>
                        <td><?php echo esc_html(number_format(array_sum($stock_levels))); ?></td>
                    </tr>
                </table>

                <!-- Products by Location -->
                <table style="width: 100%; margin-bottom: 10px; border-collapse: collapse;">
                    <tr></tr>
                    <tr>
                        <td style="background-color: #5b21b6; color: #ffffff; padding: 10px 15px; font-weight: bold; font-size: 12pt;">
                            <?php echo esc_html__('PRODUCTS BY LOCATION', 'multi-location-product-and-inventory-management-pro'); ?>
                        </td>
                    </tr>
                </table>
                <table>
                    <tr>
                        <th><?php echo esc_html__('Location', 'multi-location-product-and-inventory-management-pro'); ?></th>
                        <th><?php echo esc_html__('Product Count', 'multi-location-product-and-inventory-management-pro'); ?></th>
                        <th><?php echo esc_html__('Percentage', 'multi-location-product-and-inventory-management-pro'); ?></th>
                    </tr>
                    <?php
                    $total_products = array_sum($product_counts);
                    $row_count = 0;
                    foreach ($locations_with_default as $loc_name) {
                        $count = isset($product_counts[$loc_name]) ? $product_counts[$loc_name] : 0;
                        $percentage = $total_products > 0 ? round(($count / $total_products) * 100, 2) : 0;
                        $row_class = ($row_count % 2 == 1) ? 'even-row' : '';
                        echo '<tr' . (!empty($row_class) ? ' class="' . esc_attr($row_class) . '"' : '') . '>';
                        echo '<td>' . esc_html($loc_name) . '</td>';
                        echo '<td>' . esc_html(number_format($count)) . '</td>';
                        echo '<td class="percentage">' . esc_html($percentage) . '%</td>';
                        echo '</tr>';
                        $row_count++;
                    }
                    ?>
                </table>

                <!-- Stock Levels by Location -->
                <table style="width: 100%; margin-bottom: 10px; border-collapse: collapse;">
                    <tr></tr>
                    <tr>
                        <td style="background-color: #5b21b6; color: #ffffff; padding: 10px 15px; font-weight: bold; font-size: 12pt;">
                            <?php echo esc_html__('STOCK LEVELS BY LOCATION', 'multi-location-product-and-inventory-management-pro'); ?>
                        </td>
                    </tr>
                </table>
                <table>
                    <tr>
                        <th><?php echo esc_html__('Location', 'multi-location-product-and-inventory-management-pro'); ?></th>
                        <th><?php echo esc_html__('Stock Level', 'multi-location-product-and-inventory-management-pro'); ?></th>
                        <th><?php echo esc_html__('Percentage', 'multi-location-product-and-inventory-management-pro'); ?></th>
                    </tr>
                    <?php
                    $total_stocks = array_sum($stock_levels);
                    $row_count = 0;
                    foreach ($locations_with_default as $loc_name) {
                        $count = isset($stock_levels[$loc_name]) ? $stock_levels[$loc_name] : 0;
                        $percentage = $total_stocks > 0 ? round(($count / $total_stocks) * 100, 2) : 0;
                        $row_class = ($row_count % 2 == 1) ? 'even-row' : '';
                        echo '<tr' . (!empty($row_class) ? ' class="' . esc_attr($row_class) . '"' : '') . '>';
                        echo '<td>' . esc_html($loc_name) . '</td>';
                        echo '<td>' . esc_html(number_format($count)) . '</td>';
                        echo '<td class="percentage">' . esc_html($percentage) . '%</td>';
                        echo '</tr>';
                        $row_count++;
                    }
                    ?>
                </table>

                <!-- Orders by Location -->
                <table style="width: 100%; margin-bottom: 10px; border-collapse: collapse;">
                    <tr></tr>
                    <tr>
                        <td style="background-color: #5b21b6; color: #ffffff; padding: 10px 15px; font-weight: bold; font-size: 12pt;">
                            <?php echo esc_html__('ORDERS BY LOCATION', 'multi-location-product-and-inventory-management-pro'); ?>
                        </td>
                    </tr>
                </table>
                <table>
                    <tr>
                        <th><?php echo esc_html__('Location', 'multi-location-product-and-inventory-management-pro'); ?></th>
                        <th><?php echo esc_html__('Orders', 'multi-location-product-and-inventory-management-pro'); ?></th>
                        <th><?php echo esc_html__('Percentage', 'multi-location-product-and-inventory-management-pro'); ?></th>
                    </tr>
                    <?php
                    $total_orders = array_sum($orders_data['orders']);
                    $row_count = 0;
                    foreach ($orders_data['orders'] as $location => $orders) {
                        $percentage = $total_orders > 0 ? round(($orders / $total_orders) * 100, 2) : 0;
                        $row_class = ($row_count % 2 == 1) ? 'even-row' : '';
                        echo '<tr' . (!empty($row_class) ? ' class="' . esc_attr($row_class) . '"' : '') . '>';
                        echo '<td>' . esc_html($location) . '</td>';
                        echo '<td>' . esc_html(number_format($orders)) . '</td>';
                        echo '<td class="percentage">' . esc_html($percentage) . '%</td>';
                        echo '</tr>';
                        $row_count++;
                    }
                    ?>
                </table>

                <!-- Revenue by Location -->
                <table style="width: 100%; margin-bottom: 10px; border-collapse: collapse;">
                    <tr></tr>
                    <tr>
                        <td style="background-color: #5b21b6; color: #ffffff; padding: 10px 15px; font-weight: bold; font-size: 12pt;">
                            <?php echo esc_html__('Revenue by Location', 'multi-location-product-and-inventory-management-pro'); ?>
                        </td>
                    </tr>
                </table>
                <table>
                    <tr>
                        <th><?php echo esc_html__('Location', 'multi-location-product-and-inventory-management-pro'); ?></th>
                        <th><?php echo esc_html__('Revenue', 'multi-location-product-and-inventory-management-pro'); ?></th>
                        <th><?php echo esc_html__('Percentage', 'multi-location-product-and-inventory-management-pro'); ?></th>
                    </tr>
                    <?php
                    $total_revenue = array_sum($orders_data['revenue']);
                    $row_count = 0;
                    foreach ($orders_data['revenue'] as $location => $revenue) {
                        $percentage = $total_revenue > 0 ? round(($revenue / $total_revenue) * 100, 2) : 0;
                        $row_class = ($row_count % 2 == 1) ? 'even-row' : '';
                        echo '<tr' . (!empty($row_class) ? ' class="' . esc_attr($row_class) . '"' : '') . '>';
                        echo '<td>' . esc_html($location) . '</td>';
                        echo '<td>' . esc_html($this->get_dashboard_reporting_currency_symbol() . number_format($revenue, 2)) . '</td>';
                        echo '<td class="percentage">' . esc_html($percentage) . '%</td>';
                        echo '</tr>';
                        $row_count++;
                    }
                    ?>
                </table>

                <?php $profitability_data_export = $this->get_location_profitability_data(); ?>
                <?php if (!empty($profitability_data_export)) : ?>
                    <table style="width: 100%; margin-bottom: 10px; border-collapse: collapse;">
                        <tr></tr>
                        <tr>
                            <td style="background-color: #5b21b6; color: #ffffff; padding: 10px 15px; font-weight: bold; font-size: 12pt;">
                                <?php echo esc_html__('PROFITABILITY & AGING BY LOCATION', 'multi-location-product-and-inventory-management-pro'); ?>
                            </td>
                        </tr>
                    </table>
                    <table>
                        <tr>
                            <th><?php echo esc_html__('Location', 'multi-location-product-and-inventory-management-pro'); ?></th>
                            <th><?php echo esc_html__('Inventory Value', 'multi-location-product-and-inventory-management-pro'); ?></th>
                            <th><?php echo esc_html__('Margin Value', 'multi-location-product-and-inventory-management-pro'); ?></th>
                            <th><?php echo esc_html__('Margin %', 'multi-location-product-and-inventory-management-pro'); ?></th>
                            <th><?php echo esc_html__('Dead Stock Value', 'multi-location-product-and-inventory-management-pro'); ?></th>
                            <th><?php echo esc_html__('Dead Stock Units', 'multi-location-product-and-inventory-management-pro'); ?></th>
                            <th><?php echo esc_html__('Avg Age (days)', 'multi-location-product-and-inventory-management-pro'); ?></th>
                            <th><?php echo esc_html__('Shrinkage %', 'multi-location-product-and-inventory-management-pro'); ?></th>
                        </tr>
                        <?php
                        $row_count = 0;
                        foreach ($profitability_data_export as $summary) :
                            $row_class = ($row_count % 2 == 1) ? 'even-row' : '';
                        ?>
                            <tr<?php echo !empty($row_class) ? ' class="' . esc_attr($row_class) . '"' : ''; ?>>
                                <td><?php echo esc_html($summary['location_name']); ?></td>
                                <td><?php echo esc_html($this->get_dashboard_reporting_currency_symbol() . number_format($summary['inventory_value'], 2)); ?></td>
                                <td><?php echo esc_html($this->get_dashboard_reporting_currency_symbol() . number_format($summary['margin_value'], 2)); ?></td>
                                <td class="percentage"><?php echo esc_html(number_format($summary['margin_rate'], 2)); ?>%</td>
                                <td><?php echo esc_html($this->get_dashboard_reporting_currency_symbol() . number_format($summary['dead_stock_value'], 2)); ?></td>
                                <td><?php echo esc_html(number_format($summary['dead_stock_units'], 2)); ?></td>
                                <td><?php echo esc_html(number_format($summary['average_age_days'], 1)); ?></td>
                                <td class="percentage"><?php echo esc_html(number_format($summary['shrinkage_rate'], 2)); ?>%</td>
                                </tr>
                            <?php
                            $row_count++;
                        endforeach;
                            ?>
                    </table>
                <?php endif; ?>

                <?php if (!empty($low_stock_products)) : ?>
                    <!-- Low Stock Products -->
                    <table style="width: 100%; margin-bottom: 10px; border-collapse: collapse;">
                        <tr></tr>
                        <tr>
                            <td style="background-color: #5b21b6; color: #ffffff; padding: 10px 15px; font-weight: bold; font-size: 12pt;">
                                <?php echo esc_html__('LOW STOCK PRODUCTS', 'multi-location-product-and-inventory-management-pro'); ?>
                            </td>
                        </tr>
                    </table>
                    <table>
                        <tr>
                            <th><?php echo esc_html__('Product', 'multi-location-product-and-inventory-management-pro'); ?></th>
                            <th><?php echo esc_html__('Location', 'multi-location-product-and-inventory-management-pro'); ?></th>
                            <th><?php echo esc_html__('Stock', 'multi-location-product-and-inventory-management-pro'); ?></th>
                            <th><?php echo esc_html__('Status', 'multi-location-product-and-inventory-management-pro'); ?></th>
                        </tr>
                        <?php
                        $row_count = 0;
                        foreach ($low_stock_products as $item) :
                            $row_class = ($row_count % 2 == 1) ? 'even-row' : '';
                            $is_out_of_stock = isset($item['status']) && $item['status'] === 'out_of_stock';
                        ?>
                            <tr<?php echo !empty($row_class) ? ' class="' . esc_attr($row_class) . '"' : ''; ?>>
                                <td><?php echo esc_html($item['product_title']); ?></td>
                                <td><?php echo esc_html($item['location_name']); ?></td>
                                <td class="<?php echo $is_out_of_stock ? 'stock-out' : 'stock-low'; ?>">
                                    <?php echo esc_html($item['stock']); ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $is_out_of_stock ? 'status-out' : 'status-low'; ?>">
                                        <?php echo esc_html(isset($item['status_label']) ? $item['status_label'] : __('Low Stock', 'multi-location-product-and-inventory-management-pro')); ?>
                                    </span>
                                </td>
                                </tr>
                            <?php
                            $row_count++;
                        endforeach;
                            ?>
                    </table>
                <?php endif; ?>

                <!-- New Products (Last 30 Days) -->
                <table style="width: 100%; margin-bottom: 10px; border-collapse: collapse;">
                    <tr></tr>
                    <tr>
                        <td style="background-color: #5b21b6; color: #ffffff; padding: 10px 15px; font-weight: bold; font-size: 12pt;">
                            <?php echo esc_html__('NEW PRODUCTS (LAST 30 DAYS) - LOCATION-WISE', 'multi-location-product-and-inventory-management-pro'); ?>
                        </td>
                    </tr>
                </table>
                <table>
                    <tr>
                        <th><?php echo esc_html__('Date', 'multi-location-product-and-inventory-management-pro'); ?></th>
                        <?php foreach ($locations as $loc_name) : ?>
                            <th><?php echo esc_html($loc_name); ?></th>
                        <?php endforeach; ?>
                        <th><?php echo esc_html__('Default', 'multi-location-product-and-inventory-management-pro'); ?></th>
                        <th><?php echo esc_html__('Total Added', 'multi-location-product-and-inventory-management-pro'); ?></th>
                    </tr>
                    <?php
                    $row_count = 0;
                    foreach ($recent_matrix['labels'] as $i => $label) :
                        $row_class = ($row_count % 2 == 1) ? 'even-row' : '';
                    ?>
                        <tr<?php echo !empty($row_class) ? ' class="' . esc_attr($row_class) . '"' : ''; ?>>
                            <td><?php echo esc_html($label); ?></td>
                            <?php foreach ($locations as $loc_name) : ?>
                                <td><?php echo esc_html(isset($recent_matrix['columns'][$loc_name][$i]) ? $recent_matrix['columns'][$loc_name][$i] : 0); ?></td>
                            <?php endforeach; ?>
                            <td><?php echo esc_html(isset($recent_matrix['columns']['Default'][$i]) ? $recent_matrix['columns']['Default'][$i] : 0); ?></td>
                            <td><strong><?php echo esc_html($recent_matrix['totals'][$i]); ?></strong></td>
                            </tr>
                        <?php
                        $row_count++;
                    endforeach;
                        ?>
                </table>

                <!-- Footer -->
                <div class="footer">
                    <h2>End of Report</h2>
                    <p>Thank you for using Multi Location Product & Inventory Management for WooCommerce Pro!</p>
                    <p>Generated by Multi Location Product & Inventory Management for WooCommerce Pro</p>
                </div>
            </body>

            </html>
        <?php
            exit;
        } catch (Exception $e) {
            // FIXED: Added catch block for error handling
            error_log('Mulopimfwc: Error in export_dashboard_report_html - ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => __('An error occurred while generating the export. Please try again.', 'multi-location-product-and-inventory-management-pro'),
                'error' => defined('WP_DEBUG') && WP_DEBUG ? $e->getMessage() : ''
            ));
        }
    }

    /**
     * Products with NO mulopimfwc_store_location term.
     */
    private function get_default_product_count()
    {
        global $wpdb;
        $sql = "
        SELECT COUNT(p.ID)
        FROM {$wpdb->posts} p
        WHERE p.post_type='product' 
          AND p.post_status='publish'
          AND NOT EXISTS (
            SELECT 1
            FROM {$wpdb->term_relationships} tr
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id=tt.term_taxonomy_id
            WHERE tr.object_id=p.ID
              AND tt.taxonomy='mulopimfwc_store_location'
          )
    ";
        return (int) $wpdb->get_var($sql);
    }

    /**
     * Sum _stock for products with NO location term (global stock).
     */
    private function get_default_stock_level()
    {
        global $wpdb;
        $sql = "
        SELECT COALESCE(SUM(CAST(pm.meta_value AS SIGNED)),0)
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON pm.post_id=p.ID AND pm.meta_key='_stock'
        WHERE p.post_type IN ('product', 'product_variation')
          AND p.post_status IN ('publish', 'private')
          AND pm.meta_value IS NOT NULL AND pm.meta_value!=''
          AND (
            (
              p.post_type='product'
              AND NOT EXISTS (
                SELECT 1
                FROM {$wpdb->term_relationships} tr
                INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id=tt.term_taxonomy_id
                WHERE tr.object_id=p.ID
                  AND tt.taxonomy='mulopimfwc_store_location'
              )
            )
            OR
            (
              p.post_type='product_variation'
              AND p.post_parent > 0
              AND NOT EXISTS (
                SELECT 1
                FROM {$wpdb->term_relationships} tr
                INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id=tt.term_taxonomy_id
                WHERE tr.object_id=p.post_parent
                  AND tt.taxonomy='mulopimfwc_store_location'
              )
            )
          )
    ";
        return (int) $wpdb->get_var($sql);
    }

    /**
     * Build a date x location matrix for last 30 days.
     * @param array $location_ids Numeric term_ids
     * @param array $location_names Names (same order as $location_ids)
     * @return array ['labels'=>[], 'columns'=> [ 'LocA'=>[...], 'Default'=>[...], ... ], 'totals'=>[]]
     */
    private function get_recent_products_data_by_location(array $location_ids, array $location_names)
    {
        global $wpdb;

        $days   = 30;
        $labels = [];
        $date_index_map = []; // 'Y-m-d' => idx
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = gmdate('Y-m-d', strtotime("-$i days"));
            $labels[] = gmdate('M d', strtotime($d));
            $date_index_map[$d] = count($labels) - 1;
        }

        // Initialize columns for each location and Default
        $columns = [];
        foreach ($location_names as $ln) {
            $columns[$ln] = array_fill(0, $days, 0);
        }
        $columns['Default'] = array_fill(0, $days, 0);

        $totals = array_fill(0, $days, 0);

        // Pull all products created in last 30 days once
        $from = gmdate('Y-m-d 00:00:00', strtotime('-29 days')); // inclusive
        $sql_products = $wpdb->prepare("
        SELECT ID, DATE(post_date) AS d
        FROM {$wpdb->posts}
        WHERE post_type='product'
          AND post_status='publish'
          AND post_date >= %s
    ", $from);

        $rows = $wpdb->get_results($sql_products, ARRAY_A);
        if (empty($rows)) {
            return ['labels' => $labels, 'columns' => $columns, 'totals' => $totals];
        }

        // Map product IDs -> date (Y-m-d)
        $ids  = array_map('intval', array_column($rows, 'ID'));
        $pid_date = [];
        foreach ($rows as $r) {
            $pid_date[(int)$r['ID']] = $r['d'];
            if (isset($date_index_map[$r['d']])) {
                $totals[$date_index_map[$r['d']]]++; // total added per date
            }
        }

        // Fetch location terms for these IDs in one query
        $ids_in = implode(',', array_map('intval', $ids));
        $term_rows = $wpdb->get_results("
        SELECT tr.object_id AS pid, t.name AS lname
        FROM {$wpdb->term_relationships} tr
        INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id=tt.term_taxonomy_id
        INNER JOIN {$wpdb->terms} t ON tt.term_id=t.term_id
        WHERE tt.taxonomy='mulopimfwc_store_location'
          AND tr.object_id IN ($ids_in)
    ", ARRAY_A);

        // Build pid -> [loc names...]
        $pid_locs = [];
        foreach ($term_rows as $tr) {
            $pid = (int) $tr['pid'];
            $pid_locs[$pid][] = $tr['lname'];
        }

        // Tally counts
        foreach ($pid_date as $pid => $d) {
            if (!isset($date_index_map[$d])) {
                continue;
            }
            $idx = $date_index_map[$d];

            if (empty($pid_locs[$pid])) {
                // Products with NO location term → Default
                $columns['Default'][$idx]++;
            } else {
                // If a product has multiple locations, it will increment each assigned location
                foreach ($pid_locs[$pid] as $lname) {
                    // Only count known columns to avoid unexpected new names
                    if (isset($columns[$lname])) {
                        $columns[$lname][$idx]++;
                    }
                }
            }
        }

        return [
            'labels'  => $labels,
            'columns' => $columns,
            'totals'  => $totals,
        ];
    }





    /**
     * Render the dashboard page content
     * 
     * @return void
     */

    public function adjustColorLightness($hex, $adjust)
    {
        // Remove # if present
        if (!is_string($hex) || empty(trim($hex))) {
            return '#000000';
        }
        $hex = ltrim($hex, '#');

        // Convert to RGB
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        // Adjust lightness
        $r = max(0, min(255, $r + $adjust));
        $g = max(0, min(255, $g + $adjust));
        $b = max(0, min(255, $b + $adjust));

        // Convert back to hex
        return sprintf("#%02x%02x%02x", $r, $g, $b);
    }
    public function dashboard_page_content()
    {
        global $mulopimfwc_locations;
        $mulopimfwc_locations = $this->get_dashboard_scoped_locations();

        // Increase memory limit for dashboard operations
        if (function_exists('ini_set')) {
            ini_set('memory_limit', '512M');
        }

        // Set max execution time
        set_time_limit(300);

        // Enqueue necessary scripts and styles
        $dashboard_js_path = plugin_dir_path(__FILE__) . '../assets/js/dashboard.js';
        $dashboard_css_path = plugin_dir_path(__FILE__) . '../assets/css/dashboard.css';
        $dashboard_js_version = file_exists($dashboard_js_path) ? (string) filemtime($dashboard_js_path) : '1.1.6.8';
        $dashboard_css_version = file_exists($dashboard_css_path) ? (string) filemtime($dashboard_css_path) : '1.1.6.8';

        wp_enqueue_script('chart-js', plugin_dir_url(__FILE__) . '../assets/js/chart.min.js', array(), '3.9.1', true);
        wp_enqueue_script('lwp-dashboard-js', plugin_dir_url(__FILE__) . '../assets/js/dashboard.js', array('jquery', 'chart-js'), $dashboard_js_version, true);
        wp_enqueue_style('lwp-dashboard-css', plugin_dir_url(__FILE__) . '../assets/css/dashboard.css', array(), $dashboard_css_version);

        $payload = $this->get_lightweight_dashboard_payload();

        $dummydata = [];
        foreach ($payload['orders_by_location'] as $location => $_) {
            $dummydata[$location] = random_int(1, 100); // or rand(1, 100)
        }

        // Get notification settings for poll interval
        global $mulopimfwc_options;
        $options = is_array($mulopimfwc_options ?? null)
            ? $mulopimfwc_options
            : get_option('mulopimfwc_display_options', []);
        $notification_settings = isset($options['notification_settings']) && is_array($options['notification_settings']) ? $options['notification_settings'] : [];
        $poll_interval = isset($notification_settings['poll_interval']) ? $notification_settings['poll_interval'] : '30000';

        $can_manage_products = MULOPIMFWC_Location_Managers::user_has_capability('manage_products');
        $can_view_products = MULOPIMFWC_Location_Managers::user_has_capability('view_products');
        $can_view_orders = MULOPIMFWC_Location_Managers::user_has_capability('view_orders');
        $can_manage_orders = MULOPIMFWC_Location_Managers::user_has_capability('manage_orders');
        $can_export_report = MULOPIMFWC_Location_Managers::user_has_capability('export_report');
        $current_user = wp_get_current_user();
        $is_location_manager = is_user_logged_in() && in_array('mulopimfwc_location_manager', (array) $current_user->roles, true);
        $products_link = '';
        if ($can_manage_products) {
            $products_link = admin_url('edit.php?post_type=product');
        } elseif ($can_view_products) {
            $products_link = admin_url('admin.php?page=location-stock-management');
        }
        $orders_link = (!$is_location_manager || $can_view_orders || $can_manage_orders)
            ? admin_url('admin.php?page=wc-orders')
            : '';
        $locations_link = $is_location_manager
            ? ''
            : admin_url('edit-tags.php?taxonomy=mulopimfwc_store_location&post_type=product&orderby=display_order&order=asc');

        $dashboard_summary = isset($payload['summary']) && is_array($payload['summary']) ? $payload['summary'] : [];
        $dead_stock_days = isset($payload['dead_stock_days']) ? (int) $payload['dead_stock_days'] : 90;

        // Lightweight data only — heavy data loaded deferred via AJAX
        wp_localize_script('lwp-dashboard-js', 'mulopimfwc_DashboardData', [
            'ajaxurl'          => admin_url('admin-ajax.php'),
            'export_nonce'     => wp_create_nonce('mulopimfwc_export_nonce'),
            'dashboard_nonce'  => wp_create_nonce('mulopimfwc_dashboard_nonce'),
            'realtime_nonce'   => wp_create_nonce('mulopimfwc_dashboard_realtime_nonce'),
            'poll_interval'    => $poll_interval,
            // Lightweight — computed fast
            'productCounts'    => $payload['product_counts'],
            'stockLevels'      => $payload['stock_levels'],
            'locationColors'   => $payload['location_colors'],
            'locationBorderColors' => $payload['location_border_colors'],
            // Zeroed placeholders — filled after deferred load
            'dateLabels'       => $payload['recent_products_data']['labels'],  // just date strings, cheap
            'dateCounts'       => array_fill(0, count($payload['recent_products_data']['labels']), 0),
            'ordersByLocation' => array_map(fn() => 0, $payload['orders_by_location']),
            'revenueByLocation' => array_map(fn() => 0, $payload['revenue_by_location']),
            'monthlyInvestmentLabels' => $payload['monthly_investment_data']['labels'],
            'monthlyInvestmentData'   => array_fill(0, count($payload['monthly_investment_data']['data']), 0),
            'deadStockDays'    => $dead_stock_days,
            'currency'         => $this->get_dashboard_reporting_currency_symbol(),
            'currency_code'    => $this->get_dashboard_reporting_currency_code(),
            'summary'          => [
                'total_products'       => $dashboard_summary['total_products'] ?? 0,
                'total_locations'      => $dashboard_summary['total_locations'] ?? 0,
                'total_orders'         => 0,   // deferred
                'total_sell'          => 0,   // deferred
                'total_revenue'        => 0,   // deferred
                'total_stock'          => $dashboard_summary['total_stock'] ?? 0,
                'total_investment'     => 0,   // deferred
                'location_card_label'  => $dashboard_summary['location_card_label'] ?? __('Locations', 'multi-location-product-and-inventory-management-pro'),
                'location_card_value'  => $dashboard_summary['location_card_value'] ?? 0,
            ],
            'lowStock'         => [],          // deferred
            'deferred'         => true,        // signal to JS to trigger deferred load
            'i18n'             => [
                'totalStock'              => __('Total Stock', 'multi-location-product-and-inventory-management-pro'),
                'newProducts'             => __('New Products', 'multi-location-product-and-inventory-management-pro'),
                'investment'              => __('Investment', 'multi-location-product-and-inventory-management-pro'),
                'orders'                  => __('Orders', 'multi-location-product-and-inventory-management-pro'),
                'revenue'                 => __('Revenue', 'multi-location-product-and-inventory-management-pro'),
                'previousPeriod'          => __('Previous period', 'multi-location-product-and-inventory-management-pro'),
                'noOrders'                => __('No orders yet', 'multi-location-product-and-inventory-management-pro'),
                'loadingProfitability'    => __('Loading profitability data...', 'multi-location-product-and-inventory-management-pro'),
                'profitabilityLoadError'  => __('Unable to load profitability data right now.', 'multi-location-product-and-inventory-management-pro'),
                'loadingDeferredData'     => __('Loading dashboard data...', 'multi-location-product-and-inventory-management-pro'),
            ],
        ]);

        $orders_data        = ['orders' => [], 'sales' => [], 'revenue' => []]; // loaded deferred
        $total_investment   = 0;                                  // loaded deferred
        $low_stock_products = [];                                 // loaded deferred

        ?>
        <div class="wrap lwp-dashboard">
            <h1 style="display: none !important;"><?php echo esc_html__('Location Wise Products Dashboard', 'multi-location-product-and-inventory-management-pro'); ?></h1>
            <div class="lwp-dashboard-overview">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <h1><?php echo esc_html__('Location Wise Products Dashboard', 'multi-location-product-and-inventory-management-pro'); ?></h1>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <!-- Filter Toggle Button -->
                        <button class="mulopimfwc-btn-secondary filter_toggle_btn" style="padding: 10px 20px !important;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                            </svg>
                            <?php echo esc_html__('Filters', 'multi-location-product-and-inventory-management-pro'); ?>
                        </button>

                        <!-- Export Dropdown -->
                        <?php if ($can_export_report) : ?>
                            <div class="export_report_dropdown <?php echo esc_attr(mulopimfwc_get_pro_class(false)); ?>">
                                <button class="mulopimfwc-btn-primary export_toggle_btn" style="padding: 10px 30px !important;">
                                    <svg width="16" height="16" viewBox="0 0 0.48 0.48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M.226.046a.02.02 0 0 1 .028 0l.08.08a.02.02 0 0 1-.028.028L.26.108V.32a.02.02 0 1 1-.04 0V.108L.174.154A.02.02 0 0 1 .146.126zM.1.34a.02.02 0 0 1 .02.02V.4h.24V.36a.02.02 0 1 1 .04 0V.4a.04.04 0 0 1-.04.04H.12A.04.04 0 0 1 .08.4V.36A.02.02 0 0 1 .1.34" />
                                    </svg>
                                    <?php echo esc_html__('Export Report', 'multi-location-product-and-inventory-management-pro'); ?>
                                    <span class="dropdown_icon">▾</span>
                                </button>

                                <div class="dropdown_menu">
                                    <button class="<?php echo esc_attr(mulopimfwc_get_pro_class(false, 'export_report')); ?>" id="export_report_csv" data-format="csv">
                                        <?php echo esc_html__('Export in CSV', 'multi-location-product-and-inventory-management-pro'); ?>
                                    </button>

                                    <button class="<?php echo esc_attr(mulopimfwc_get_pro_class(false, 'export_report')); ?>" id="export_report_html" data-format="html">
                                        <?php echo esc_html__('Export in Excel (HTML)', 'multi-location-product-and-inventory-management-pro'); ?>
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <style>
                        .export_report_dropdown {
                            position: relative;
                            display: inline-block;
                        }

                        .dropdown_icon {
                            font-size: 12px;
                            margin-left: 5px;
                            transition: transform 0.2s ease;
                        }

                        .export_report_dropdown.active .dropdown_icon {
                            transform: rotate(180deg);
                        }

                        .dropdown_menu {
                            position: absolute;
                            top: calc(100% + 8px);
                            right: 0;
                            background: #fff;
                            border: 1px solid #e2e8f0;
                            border-radius: 8px;
                            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
                            display: none;
                            min-width: 220px;
                            overflow: hidden;
                            z-index: 9999;
                            animation: fadeIn 0.2s ease;
                        }

                        .dropdown_menu button {
                            width: 100%;
                            padding: 12px 20px;
                            background: transparent;
                            border: none;
                            text-align: left;
                            font-size: 14px;
                            color: #334155;
                            cursor: pointer;
                            transition: all 0.2s ease;
                        }

                        .dropdown_menu button:hover {
                            background-color: #f3f4f6;
                            color: #1e40af;
                        }

                        @keyframes fadeIn {
                            from {
                                opacity: 0;
                                transform: translateY(-5px);
                            }

                            to {
                                opacity: 1;
                                transform: translateY(0);
                            }
                        }
                    </style>

                    <script>
                        jQuery(document).ready(function($) {
                            const dropdown = $('.export_report_dropdown');
                            const toggleBtn = dropdown.find('.export_toggle_btn');
                            const menu = dropdown.find('.dropdown_menu');

                            // Toggle dropdown
                            toggleBtn.on('click', function(e) {
                                e.stopPropagation();
                                dropdown.toggleClass('active');
                                menu.slideToggle(150);
                            });

                            // Close dropdown when clicking outside
                            $(document).on('click', function(e) {
                                if (!dropdown.is(e.target) && dropdown.has(e.target).length === 0) {
                                    dropdown.removeClass('active');
                                    menu.slideUp(150);
                                }
                            });
                        });
                    </script>


                </div>
                <div class="lwp-dashboard-filters">
                    <div class="lwp-filters-container">
                        <h3>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                            </svg>
                            <?php echo esc_html__('Filter Dashboard', 'multi-location-product-and-inventory-management-pro'); ?>
                        </h3>

                        <div class="lwp-filters-wrapper">
                            <div class="lwp-filters-grid">
                                <!-- Date Range Filter -->
                                <div class="lwp-filter-group">
                                    <label for="filter-date-from">
                                        <?php echo esc_html__('Date From', 'multi-location-product-and-inventory-management-pro'); ?>
                                    </label>
                                    <input type="date" id="filter-date-from" class="lwp-filter-input" />
                                </div>

                                <div class="lwp-filter-group">
                                    <label for="filter-date-to">
                                        <?php echo esc_html__('Date To', 'multi-location-product-and-inventory-management-pro'); ?>
                                    </label>
                                    <input type="date" id="filter-date-to" class="lwp-filter-input" />
                                </div>

                                <!-- Location Filter -->
                                <div class="lwp-filter-group">
                                    <label for="filter-location">
                                        <?php echo esc_html__('Location', 'multi-location-product-and-inventory-management-pro'); ?>
                                    </label>
                                    <select id="filter-location" class="lwp-filter-input">
                                        <option value="all"><?php echo esc_html__('All Locations', 'multi-location-product-and-inventory-management-pro'); ?></option>
                                        <?php foreach ($mulopimfwc_locations as $location): ?>
                                            <option value="<?php echo esc_attr(rawurldecode($location->slug)); ?>">
                                                <?php echo esc_html($location->name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                        <?php if (!$is_location_manager): ?>
                                            <option value="default"><?php echo esc_html__('Default', 'multi-location-product-and-inventory-management-pro'); ?></option>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <!-- Order Status Filter -->
                                <div class="lwp-filter-group">
                                    <label for="filter-status">
                                        <?php echo esc_html__('Order Status', 'multi-location-product-and-inventory-management-pro'); ?>
                                    </label>
                                    <select id="filter-status" class="lwp-filter-input">
                                        <option value="all"><?php echo esc_html__('All Statuses', 'multi-location-product-and-inventory-management-pro'); ?></option>
                                        <option value="completed"><?php echo esc_html__('Completed', 'multi-location-product-and-inventory-management-pro'); ?></option>
                                        <option value="processing"><?php echo esc_html__('Processing', 'multi-location-product-and-inventory-management-pro'); ?></option>
                                        <option value="pending"><?php echo esc_html__('Pending', 'multi-location-product-and-inventory-management-pro'); ?></option>
                                        <option value="on-hold"><?php echo esc_html__('On Hold', 'multi-location-product-and-inventory-management-pro'); ?></option>
                                        <option value="cancelled"><?php echo esc_html__('Cancelled', 'multi-location-product-and-inventory-management-pro'); ?></option>
                                        <option value="refunded"><?php echo esc_html__('Refunded', 'multi-location-product-and-inventory-management-pro'); ?></option>
                                        <option value="failed"><?php echo esc_html__('Failed', 'multi-location-product-and-inventory-management-pro'); ?></option>
                                    </select>
                                </div>
                            </div>

                            <!-- Quick Date Range Buttons -->
                            <div class="lwp-filter-group lwp-quick-filters">
                                <label><?php echo esc_html__('Quick Select', 'multi-location-product-and-inventory-management-pro'); ?></label>
                                <div class="lwp-quick-buttons">
                                    <button type="button" class="lwp-quick-btn" data-days="1">
                                        <?php echo esc_html__('Today', 'multi-location-product-and-inventory-management-pro'); ?>
                                    </button>
                                    <button type="button" class="lwp-quick-btn" data-days="7">
                                        <?php echo esc_html__('Last 7 Days', 'multi-location-product-and-inventory-management-pro'); ?>
                                    </button>
                                    <button type="button" class="lwp-quick-btn" data-days="30">
                                        <?php echo esc_html__('Last 30 Days', 'multi-location-product-and-inventory-management-pro'); ?>
                                    </button>
                                    <button type="button" class="lwp-quick-btn" data-period="this-month">
                                        <?php echo esc_html__('This Month', 'multi-location-product-and-inventory-management-pro'); ?>
                                    </button>
                                    <button type="button" class="lwp-quick-btn" data-period="last-month">
                                        <?php echo esc_html__('Last Month', 'multi-location-product-and-inventory-management-pro'); ?>
                                    </button>
                                </div>
                            </div>

                        </div>

                        <!-- Action Buttons -->
                        <div class="lwp-filter-actions" style="margin-top: 20px;">
                            <button type="button" id="apply-filters" class="mulopimfwc-btn-primary">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M5 13l4 4L19 7"></path>
                                </svg>
                                <?php echo esc_html__('Apply Filters', 'multi-location-product-and-inventory-management-pro'); ?>
                            </button>
                            <button type="button" id="reset-filters" class="lwp-btn-secondary">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path>
                                    <path d="M21 3v5h-5"></path>
                                    <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path>
                                    <path d="M3 21v-5h5"></path>
                                </svg>
                                <?php echo esc_html__('Reset', 'multi-location-product-and-inventory-management-pro'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <div id="lwp-loading-overlay" style="display: none;">
                    <div class="lwp-spinner"></div>
                    <p><?php echo esc_html__('Loading data...', 'multi-location-product-and-inventory-management-pro'); ?></p>
                </div>
                <div class="lwp-card-stats">
                    <div class="lwp-stats-grid">
                        <?php if ($products_link) : ?>
                            <a class="lwp-stat-item" href="<?php echo esc_url($products_link); ?>">
                            <?php else : ?>
                                <div class="lwp-stat-item">
                                <?php endif; ?>
                                <div class="lwp-stat-item-icon">

                                    <svg class="svg-inline--fa fa-box" aria-hidden="true" data-prefix="fas" data-icon="box" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" width="18" height="18">
                                        <path fill="#2563eb" d="M50.7 58.5 0 160h208V32H93.7c-18.2 0-34.8 10.3-43 26.5M240 160h208L397.3 58.5c-8.2-16.2-24.8-26.5-43-26.5H240zm208 32H0v224c0 35.3 28.7 64 64 64h320c35.3 0 64-28.7 64-64z" />
                                    </svg>
                                </div>
                                <div>
                                    <span class="lwp-stat-progress" data-metric="products"></span>
                                    <span class="lwp-stat-label"><?php echo esc_html__('Total Products', 'multi-location-product-and-inventory-management-pro'); ?></span>
                                    <span class="lwp-stat-value"><?php echo esc_html((int) ($dashboard_summary['total_products'] ?? 0)); ?></span>
                                </div>
                                <?php if ($products_link) : ?>
                            </a>
                        <?php else : ?>
                    </div>
                <?php endif; ?>
                <?php if ($locations_link) : ?>
                    <a class="lwp-stat-item lwp-stat-item--location" href="<?php echo esc_url($locations_link); ?>">
                    <?php else : ?>
                        <div class="lwp-stat-item lwp-stat-item--location">
                        <?php endif; ?>
                        <div class="lwp-stat-item-icon" style="background-color: #dcfce7;">

                            <svg class="svg-inline--fa fa-location-dot" aria-hidden="true" data-prefix="fas" data-icon="location-dot" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512" width="18" height="18">
                                <path fill="#16a34a" d="M215.7 499.2C267 435 384 279.4 384 192 384 86 298 0 192 0S0 86 0 192c0 87.4 117 243 168.3 307.2 12.3 15.3 35.1 15.3 47.4 0M192 128a64 64 0 1 1 0 128 64 64 0 1 1 0-128" />
                            </svg>
                        </div>
                        <div class="lwp-stat-content">
                            <span class="lwp-stat-progress" data-metric="locations"></span>
                            <span class="lwp-stat-label"><?php echo esc_html((string) ($dashboard_summary['location_card_label'] ?? __('Locations', 'multi-location-product-and-inventory-management-pro'))); ?></span>
                            <span class="lwp-stat-value lwp-stat-add-location" title="<?php echo esc_attr((string) ($dashboard_summary['location_card_value'] ?? 0)); ?>"><?php echo esc_html((string) ($dashboard_summary['location_card_value'] ?? 0)); ?></span>
                        </div>

                        <?php if ($locations_link) : ?>
                    </a>
                <?php else : ?>
                </div>
            <?php endif; ?>
            <?php if ($orders_link) : ?>
                <a class="lwp-stat-item <?php echo esc_attr(mulopimfwc_get_pro_class()); ?>" href="<?php echo esc_url($orders_link); ?>">
                <?php else : ?>
                    <div class="lwp-stat-item <?php echo esc_attr(mulopimfwc_get_pro_class()); ?>">
                    <?php endif; ?>
                    <div class="lwp-stat-item-icon" style="background-color: #f3e8ff;">

                        <svg class="svg-inline--fa fa-cart-shopping" aria-hidden="true" data-prefix="fas" data-icon="cart-shopping" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512" width="18" height="18">
                            <path fill="#9333ea" d="M0 24C0 10.7 10.7 0 24 0h45.5c22 0 41.5 12.8 50.6 32h411c26.3 0 45.5 25 38.6 50.4l-41 152.3c-8.5 31.4-37 53.3-69.5 53.3H170.7l5.4 28.5c2.2 11.3 12.1 19.5 23.6 19.5H488c13.3 0 24 10.7 24 24s-10.7 24-24 24H199.7c-34.6 0-64.3-24.6-70.7-58.5l-51.6-271c-.7-3.8-4-6.5-7.9-6.5H24C10.7 48 0 37.3 0 24m128 440a48 48 0 1 1 96 0 48 48 0 1 1-96 0m336-48a48 48 0 1 1 0 96 48 48 0 1 1 0-96" />
                        </svg>
                    </div>
                    <div>
                        <span class="lwp-stat-progress" data-metric="orders"></span>
                        <span class="lwp-stat-label"><?php echo esc_html__('Orders', 'multi-location-product-and-inventory-management-pro'); ?></span>
                        <span class="lwp-stat-value"><?php echo esc_html(mulopimfwc_get_pro_class(false, array_sum($orders_data['orders']), rand(1, 100))); ?></span>

                    </div>

                    <?php if ($orders_link) : ?>
                </a>
            <?php else : ?>
            </div>
        <?php endif; ?>
        <div class="lwp-stat-item">
            <div class="lwp-stat-item-icon" style="background-color: #cffafe;">

                <svg class="svg-inline--fa fa-money-bag" aria-hidden="true" data-prefix="fas" data-icon="money-bag" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="18" height="18">
                    <g class="missing" fill="#0891b2">
                        <path d="m156.5 447.7-12.6 29.5c-18.7-9.5-35.9-21.2-51.5-34.9l22.7-22.7c12.5 10.9 26.4 20.4 41.4 28.1M40.6 272H8.5c1.4 21.2 5.4 41.7 11.7 61.1L50 321.2c-4.9-15.7-8.2-32.2-9.4-49.2m0-32c1.4-18.8 5.2-37 11.1-54.1l-29.5-12.6c-7.5 21-12.2 43.4-13.7 66.7zm23.7-83.5c7.8-14.9 17.2-28.8 28.1-41.5L69.7 92.3c-13.7 15.6-25.5 32.8-34.9 51.5zM397 419.6c-13.9 12-29.4 22.3-46.1 30.4l11.9 29.8c20.7-9.9 39.8-22.6 56.9-37.6zM115 92.4c13.9-12 29.4-22.3 46.1-30.4l-11.9-29.8c-20.7 9.9-39.8 22.6-56.8 37.6zm332.7 263.1c-7.8 14.9-17.2 28.8-28.1 41.5l22.7 22.7c13.7-15.6 25.5-32.9 34.9-51.5zm23.7-83.5c-1.4 18.8-5.2 37-11.1 54.1l29.5 12.6c7.5-21.1 12.2-43.5 13.6-66.8h-32zM321.2 462c-15.7 5-32.2 8.2-49.2 9.4v32.1c21.2-1.4 41.7-5.4 61.1-11.7zm-81.2 9.4c-18.8-1.4-37-5.2-54.1-11.1l-12.6 29.5c21.1 7.5 43.5 12.2 66.8 13.6v-32zm222-280.6c5 15.7 8.2 32.2 9.4 49.2h32.1c-1.4-21.2-5.4-41.7-11.7-61.1zM92.4 397c-12-13.9-22.3-29.4-30.4-46.1l-29.8 11.9c9.9 20.7 22.6 39.8 37.6 56.9zM272 40.6c18.8 1.4 36.9 5.2 54.1 11.1l12.6-29.5c-21-7.5-43.4-12.2-66.7-13.7zM190.8 50c15.7-5 32.2-8.2 49.2-9.4V8.5c-21.2 1.4-41.7 5.4-61.1 11.7zm251.5 42.3L419.6 115c12 13.9 22.3 29.4 30.5 46.1l29.8-11.9c-9.9-20.7-22.6-39.8-37.6-56.9m-45.3.1 22.7-22.7c-15.6-13.7-32.8-25.5-51.5-34.9l-12.6 29.5c14.8 7.8 28.8 17.2 41.4 28.1" />
                        <circle cx="256" cy="364" r="28">
                            <animate attributeType="XML" repeatCount="indefinite" dur="2s" attributeName="r" values="28;14;28;28;14;28;" />
                            <animate attributeType="XML" repeatCount="indefinite" dur="2s" attributeName="opacity" values="1;0;1;1;0;1;" />
                        </circle>
                        <path d="M263.7 312h-16c-6.6 0-12-5.4-12-12 0-71 77.4-63.9 77.4-107.8 0-20-17.8-40.2-57.4-40.2-29.1 0-44.3 9.6-59.2 28.7-3.9 5-11.1 6-16.2 2.4l-13.1-9.2c-5.6-3.9-6.9-11.8-2.6-17.2 21.2-27.2 46.4-44.7 91.2-44.7 52.3 0 97.4 29.8 97.4 80.2 0 67.6-77.4 63.5-77.4 107.8-.1 6.6-5.5 12-12.1 12">
                            <animate attributeType="XML" repeatCount="indefinite" dur="2s" attributeName="opacity" values="1;0;0;0;0;1;" />
                        </path>
                    </g>
                </svg>
            </div>
            <div>
                <span class="lwp-stat-progress" data-metric="investment"></span>
                <span class="lwp-stat-label"><?php echo esc_html__('Total Investment', 'multi-location-product-and-inventory-management-pro'); ?></span>
                <span class="lwp-stat-value"><?php echo wp_kses_post($this->format_dashboard_reporting_price($total_investment)); ?></span>

            </div>

        </div>
        <div class="lwp-stat-item">
            <div class="lwp-stat-item-icon" style="background-color: #dcfce7;">

                <svg class="svg-inline--fa fa-sack-dollar" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="18" height="18">
                    <path fill="#16a34a" d="M345.7 53.9c22.5-24.6 38.5-53.9 38.5-53.9H256 127.8s16.1 29.3 38.5 53.9c13.4 14.6 29.6 27.8 47.7 37.6-5.2 4.8-10.8 9.2-16.8 13.1C114.7 157.8 48 247.8 48 352c0 88.4 92.3 160 208 160s208-71.6 208-160c0-104.2-66.7-194.2-149.2-247.4-6-3.9-11.6-8.2-16.8-13.1 18.2-9.8 34.4-23 47.7-37.6M256 224c17.7 0 32 14.3 32 32v16h16c17.7 0 32 14.3 32 32s-14.3 32-32 32H288v48h8c22.1 0 40 17.9 40 40s-17.9 40-40 40h-8v8c0 17.7-14.3 32-32 32s-32-14.3-32-32v-8H176c-17.7 0-32-14.3-32-32s14.3-32 32-32h48v-48h-8c-22.1 0-40-17.9-40-40s17.9-40 40-40h8v-16c0-17.7 14.3-32 32-32" />
                </svg>
            </div>
            <div>
                <span class="lwp-stat-progress" data-metric="sell"></span>
                <span class="lwp-stat-label"><?php echo esc_html__('Total Sell', 'multi-location-product-and-inventory-management-pro'); ?></span>
                <span class="lwp-stat-value"><?php echo wp_kses_post($this->format_dashboard_reporting_price(array_sum($orders_data['sales'] ?? []))); ?></span>

            </div>

        </div>
        <div class="lwp-stat-item">
            <div class="lwp-stat-item-icon" style="background-color: #ffedd5;">

                <svg class="svg-inline--fa fa-chart-line" aria-hidden="true" data-prefix="fas" data-icon="chart-line" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="18" height="18">
                    <path fill="#ea580c" d="M64 64c0-17.7-14.3-32-32-32S0 46.3 0 64v336c0 44.2 35.8 80 80 80h400c17.7 0 32-14.3 32-32s-14.3-32-32-32H80c-8.8 0-16-7.2-16-16zm406.6 86.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L320 210.7l-57.4-57.4c-12.5-12.5-32.8-12.5-45.3 0l-112 112c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0l89.4-89.3 57.4 57.4c12.5 12.5 32.8 12.5 45.3 0l128-128z" />
                </svg>
            </div>
            <div>
                <span class="lwp-stat-progress" data-metric="revenue"></span>
                <span class="lwp-stat-label"><?php echo esc_html__('Revenue', 'multi-location-product-and-inventory-management-pro'); ?></span>
                <span class="lwp-stat-value"><?php echo wp_kses_post($this->format_dashboard_reporting_price(array_sum($orders_data["revenue"]))); ?></span>

            </div>

        </div>
        </div>
        </div>
        </div>

        <div class="lwp-dashboard-charts">
            <div class="lwp-row">
                <div class="lwp-col">
                    <div class="lwp-card">
                        <h2><?php echo esc_html__('Products by Location', 'multi-location-product-and-inventory-management-pro'); ?></h2>
                        <div class="lwp-chart-container">
                            <canvas id="locationProductsChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="lwp-col">
                    <div class="lwp-card">
                        <h2><?php echo esc_html__('Stock Levels by Location', 'multi-location-product-and-inventory-management-pro'); ?></h2>
                        <div class="lwp-chart-container">
                            <canvas id="locationStockChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lwp-row">
                <div class="lwp-col">
                    <div class="lwp-card">
                        <h2><?php echo esc_html__('Orders by Location', 'multi-location-product-and-inventory-management-pro'); ?></h2>
                        <div class="lwp-chart-container <?php echo esc_attr(mulopimfwc_get_pro_class()); ?>">
                            <canvas id="ordersByLocationChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="lwp-col">
                    <div class="lwp-card">
                        <h2><?php echo esc_html__('Revenue by Location', 'multi-location-product-and-inventory-management-pro'); ?></h2>
                        <div class="lwp-chart-container <?php echo esc_attr(mulopimfwc_get_pro_class()); ?>">
                            <canvas id="revenueByLocationChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lwp-row">
                <div class="lwp-col">
                    <div class="lwp-card">
                        <h2><?php echo esc_html__('New Products (Last 30 Days)', 'multi-location-product-and-inventory-management-pro'); ?></h2>
                        <div class="lwp-chart-container <?php echo esc_attr(mulopimfwc_get_pro_class()); ?>">
                            <canvas id="newProductsChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="lwp-col">
                    <div class="lwp-card">
                        <h2><?php echo esc_html__('Investment', 'multi-location-product-and-inventory-management-pro'); ?></h2>
                        <div class="lwp-chart-container <?php echo esc_attr(mulopimfwc_get_pro_class()); ?>">
                            <canvas id="investment-30day"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lwp-row">
                <div class="lwp-col">
                    <div class="lwp-card">
                        <h2><?php esc_html_e('Stock Alerts by Location', 'multi-location-product-and-inventory-management-pro'); ?></h2>
                        <?php
                        $low_stock_sample_products = array_map([$this, 'normalize_stock_alert_item'], [
                            [
                                "product_id" => 1,
                                "product_title" => "Apple Laptop",
                                "location_name" => "New York",
                                "location_id" => 1,
                                "stock" => 0,
                                "status" => "out_of_stock"
                            ],
                            [
                                "product_id" => 2,
                                "product_title" => "Samsung Galaxy Phone",
                                "location_name" => "Los Angeles",
                                "location_id" => 2,
                                "stock" => 2,
                                "status" => "low_stock"
                            ],
                            [
                                "product_id" => 3,
                                "product_title" => "Dell XPS 15",
                                "location_name" => "Chicago",
                                "location_id" => 3,
                                "stock" => 1,
                                "status" => "low_stock"
                            ],
                            [
                                "product_id" => 4,
                                "product_title" => "Sony Headphones",
                                "location_name" => "Miami",
                                "location_id" => 4,
                                "stock" => 0,
                                "status" => "out_of_stock"
                            ],
                            [
                                "product_id" => 5,
                                "product_title" => "LG OLED TV",
                                "location_name" => "Houston",
                                "location_id" => 5,
                                "stock" => 3,
                                "status" => "low_stock"
                            ],
                            [
                                "product_id" => 6,
                                "product_title" => "Amazon Echo",
                                "location_name" => "Seattle",
                                "location_id" => 6,
                                "stock" => 1,
                                "status" => "low_stock"
                            ],
                            [
                                "product_id" => 7,
                                "product_title" => "Microsoft Surface Pro",
                                "location_name" => "San Francisco",
                                "location_id" => 7,
                                "stock" => 2,
                                "status" => "low_stock"
                            ],
                            [
                                "product_id" => 8,
                                "product_title" => "Bose SoundLink Speaker",
                                "location_name" => "Boston",
                                "location_id" => 8,
                                "stock" => 0,
                                "status" => "out_of_stock"
                            ],
                            [
                                "product_id" => 9,
                                "product_title" => "iPad Pro",
                                "location_name" => "Atlanta",
                                "location_id" => 9,
                                "stock" => 1,
                                "status" => "low_stock"
                            ],
                            [
                                "product_id" => 10,
                                "product_title" => "Fitbit Charge 5",
                                "location_name" => "Denver",
                                "location_id" => 10,
                                "stock" => 0,
                                "status" => "out_of_stock"
                            ]
                        ]);

                        $low_stock_products = mulopimfwc_get_pro_class(false, $low_stock_products, $low_stock_sample_products);
                        if (is_array($low_stock_products)) {
                            $low_stock_products = array_values(array_map([$this, 'normalize_stock_alert_item'], $low_stock_products));
                        }

                        if (!empty($low_stock_products)) : ?>
                            <table class="lwp-low-stock-table <?php echo esc_attr(mulopimfwc_get_pro_class()); ?>">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Product', 'multi-location-product-and-inventory-management-pro'); ?></th>
                                        <th><?php esc_html_e('Location', 'multi-location-product-and-inventory-management-pro'); ?></th>
                                        <th><?php esc_html_e('Stock', 'multi-location-product-and-inventory-management-pro'); ?></th>
                                        <th><?php esc_html_e('Status', 'multi-location-product-and-inventory-management-pro'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ((object)$low_stock_products as $item) : ?>
                                        <tr>
                                            <td>
                                                <?php if ($can_manage_products) : ?>
                                                    <a href="<?php echo esc_url(get_edit_post_link((int) ($item['edit_post_id'] ?? $item['product_id']))); ?>">
                                                        <?php echo esc_html($item['product_title']); ?>
                                                    </a>
                                                <?php else : ?>
                                                    <?php echo esc_html($item['product_title']); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo esc_html($item['location_name']); ?></td>
                                            <td>
                                                <span class="stock-quantity <?php echo esc_attr($item['status_class'] ?? 'low-stock'); ?>">
                                                    <?php echo esc_html($item['stock']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="stock-status <?php echo esc_attr($item['status_class'] ?? 'low-stock'); ?>">
                                                    <?php echo esc_html($item['status_label'] ?? __('Low Stock', 'multi-location-product-and-inventory-management-pro')); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p><?php esc_html_e('No low stock products found for any location.', 'multi-location-product-and-inventory-management-pro'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="lwp-row">
                <div class="lwp-col">
                    <div class="lwp-card">
                        <div
                            id="lwp-profitability-panel"
                            class="lwp-profitability-panel"
                            data-dead-stock-days="<?php echo esc_attr($dead_stock_days); ?>">
                            <div class="lwp-profitability-state is-loading">
                                <?php esc_html_e('Loading profitability data...', 'multi-location-product-and-inventory-management-pro'); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>
    <?php
    }

    /**
     * Get product count for a specific location efficiently
     */
    private function get_location_product_count($location_id)
    {
        global $wpdb;

        $query = $wpdb->prepare("
            SELECT COUNT(DISTINCT p.ID) 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            WHERE p.post_type = 'product' 
            AND p.post_status = 'publish'
            AND tt.taxonomy = 'mulopimfwc_store_location'
            AND tt.term_id = %d
        ", $location_id);

        return (int) $wpdb->get_var($query);
    }

    /**
     * Get stock level for a specific location efficiently
     */
    private function get_location_stock_level($location_id)
    {
        global $wpdb;

        $meta_key = '_location_stock_' . $location_id;

        $query = $wpdb->prepare("
            SELECT COALESCE(SUM(CAST(pm.meta_value AS SIGNED)), 0) as total_stock
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type IN ('product', 'product_variation')
            AND p.post_status IN ('publish', 'private')
            AND pm.meta_key = %s
            AND pm.meta_value != ''
            AND pm.meta_value IS NOT NULL
        ", $meta_key);

        return (int) $wpdb->get_var($query);
    }

    /**
     * Get product counts by location, optionally within a date range.
     */
    private function get_product_counts_by_location($date_from = '', $date_to = '', $location_filter = 'all')
    {
        global $wpdb, $mulopimfwc_locations;

        if (empty($mulopimfwc_locations) || is_wp_error($mulopimfwc_locations)) {
            $mulopimfwc_locations = [];
        }

        $has_range = !empty($date_from) && !empty($date_to);
        $counts = [];
        $locations_to_include = $mulopimfwc_locations;
        $include_default = ($location_filter === 'default');

        if ($location_filter === 'default') {
            $locations_to_include = [];
        } elseif ($location_filter !== 'all') {
            $locations_to_include = array_filter($mulopimfwc_locations, function ($loc) use ($location_filter) {
                return $loc->slug === $location_filter;
            });
        }

        // FIXED: Add caching and memory optimization (Issues #12, #20)
        $cache_version = function_exists('mulopimfwc_get_dashboard_cache_version')
            ? mulopimfwc_get_dashboard_cache_version()
            : 1;
        foreach ($locations_to_include as $location) {
            // Validate location exists (Issue #13)
            if (!$location || !isset($location->term_id) || !isset($location->name)) {
                continue;
            }

            // FIXED: Use cache for repeated queries (Issue #12)
            $cache_key = 'mulopimfwc_product_count_v' . $cache_version . '_' . $location->term_id . '_' . ($has_range ? md5($date_from . $date_to) : 'all');
            $cached_count = wp_cache_get($cache_key, 'mulopimfwc_dashboard');

            if ($cached_count !== false) {
                $counts[$location->name] = (int) $cached_count;
            } else {
                if ($has_range) {
                    $query = $wpdb->prepare("
                        SELECT COUNT(DISTINCT p.ID) 
                        FROM {$wpdb->posts} p
                        INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                        INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                        WHERE p.post_type = 'product' 
                        AND p.post_status = 'publish'
                        AND DATE(p.post_date) BETWEEN %s AND %s
                        AND tt.taxonomy = 'mulopimfwc_store_location'
                        AND tt.term_id = %d
                    ", $date_from, $date_to, $location->term_id);
                    $counts[$location->name] = (int) $wpdb->get_var($query);
                } else {
                    $counts[$location->name] = $this->get_location_product_count($location->term_id);
                }

                // Cache result for 5 minutes (Issue #12)
                wp_cache_set($cache_key, $counts[$location->name], 'mulopimfwc_dashboard', 300);
            }

            // FIXED: Clear variables to prevent memory leaks (Issue #20)
            unset($query);
        }

        if ($include_default) {
            if ($has_range) {
                $query = $wpdb->prepare("
                    SELECT COUNT(p.ID)
                    FROM {$wpdb->posts} p
                    WHERE p.post_type='product' 
                      AND p.post_status='publish'
                      AND DATE(p.post_date) BETWEEN %s AND %s
                      AND NOT EXISTS (
                        SELECT 1
                        FROM {$wpdb->term_relationships} tr
                        INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id=tt.term_taxonomy_id
                        WHERE tr.object_id=p.ID
                          AND tt.taxonomy='mulopimfwc_store_location'
                      )
                ", $date_from, $date_to);
                $counts['Default'] = (int) $wpdb->get_var($query);
            } else {
                $counts['Default'] = $this->get_default_product_count();
            }
        }

        return $counts;
    }

    /**
     * Get stock levels by location, optionally within a date range.
     */
    private function get_stock_levels_by_location($date_from = '', $date_to = '', $location_filter = 'all')
    {
        global $wpdb, $mulopimfwc_locations;

        if (empty($mulopimfwc_locations) || is_wp_error($mulopimfwc_locations)) {
            $mulopimfwc_locations = [];
        }

        $has_range = !empty($date_from) && !empty($date_to);
        $levels = [];
        $locations_to_include = $mulopimfwc_locations;
        $include_default = ($location_filter === 'default');

        if ($location_filter === 'default') {
            $locations_to_include = [];
        } elseif ($location_filter !== 'all') {
            $locations_to_include = array_filter($mulopimfwc_locations, function ($loc) use ($location_filter) {
                return $loc->slug === $location_filter;
            });
        }

        // FIXED: Add caching and memory optimization (Issues #12, #20)
        $cache_version = function_exists('mulopimfwc_get_dashboard_cache_version')
            ? mulopimfwc_get_dashboard_cache_version()
            : 1;
        foreach ($locations_to_include as $location) {
            // Validate location exists (Issue #13)
            if (!$location || !isset($location->term_id) || !isset($location->name)) {
                continue;
            }

            // FIXED: Use cache for repeated queries (Issue #12)
            $cache_key = 'mulopimfwc_stock_level_v' . $cache_version . '_' . $location->term_id . '_' . ($has_range ? md5($date_from . $date_to) : 'all');
            $cached_level = wp_cache_get($cache_key, 'mulopimfwc_dashboard');

            if ($cached_level !== false) {
                $levels[$location->name] = (int) $cached_level;
            } else {
                if ($has_range) {
                    $meta_key = '_location_stock_' . $location->term_id;
                    $query = $wpdb->prepare("
                        SELECT COALESCE(SUM(CAST(pm.meta_value AS SIGNED)), 0) as total_stock
                        FROM {$wpdb->posts} p
                        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                        WHERE p.post_type IN ('product', 'product_variation')
                        AND p.post_status IN ('publish', 'private')
                        AND pm.meta_key = %s
                        AND pm.meta_value != ''
                        AND pm.meta_value IS NOT NULL
                        AND DATE(p.post_date) BETWEEN %s AND %s
                    ", $meta_key, $date_from, $date_to);
                    $levels[$location->name] = (int) $wpdb->get_var($query);
                } else {
                    $levels[$location->name] = $this->get_location_stock_level($location->term_id);
                }

                // Cache result for 5 minutes (Issue #12)
                wp_cache_set($cache_key, $levels[$location->name], 'mulopimfwc_dashboard', 300);
            }

            // FIXED: Clear variables to prevent memory leaks (Issue #20)
            unset($query, $meta_key);
        }

        if ($include_default) {
            if ($has_range) {
                $query = $wpdb->prepare("
                    SELECT COALESCE(SUM(CAST(pm.meta_value AS SIGNED)),0)
                    FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->postmeta} pm ON pm.post_id=p.ID AND pm.meta_key='_stock'
                    WHERE p.post_type IN ('product', 'product_variation')
                      AND p.post_status IN ('publish', 'private')
                      AND pm.meta_value IS NOT NULL AND pm.meta_value!=''
                      AND DATE(p.post_date) BETWEEN %s AND %s
                      AND (
                        (
                          p.post_type='product'
                          AND NOT EXISTS (
                            SELECT 1
                            FROM {$wpdb->term_relationships} tr
                            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id=tt.term_taxonomy_id
                            WHERE tr.object_id=p.ID
                              AND tt.taxonomy='mulopimfwc_store_location'
                          )
                        )
                        OR
                        (
                          p.post_type='product_variation'
                          AND p.post_parent > 0
                          AND NOT EXISTS (
                            SELECT 1
                            FROM {$wpdb->term_relationships} tr
                            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id=tt.term_taxonomy_id
                            WHERE tr.object_id=p.post_parent
                              AND tt.taxonomy='mulopimfwc_store_location'
                          )
                        )
                      )
                ", $date_from, $date_to);
                $levels['Default'] = (int) $wpdb->get_var($query);
            } else {
                $levels['Default'] = $this->get_default_stock_level();
            }
        }

        return $levels;
    }

    public function apply_dashboard_filters()
    {
        check_ajax_referer('mulopimfwc_dashboard_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied', 'multi-location-product-and-inventory-management-pro')));
        }

        global $mulopimfwc_locations;
        $mulopimfwc_locations = $this->get_dashboard_scoped_locations();

        $date_from_raw = isset($_POST['date_from']) ? wp_unslash($_POST['date_from']) : '';
        $date_to_raw = isset($_POST['date_to']) ? wp_unslash($_POST['date_to']) : '';
        if (is_array($date_from_raw)) {
            $date_from_raw = reset($date_from_raw);
        }
        if (is_array($date_to_raw)) {
            $date_to_raw = reset($date_to_raw);
        }
        $date_from = is_string($date_from_raw) ? sanitize_text_field($date_from_raw) : '';
        $date_to = is_string($date_to_raw) ? sanitize_text_field($date_to_raw) : '';

        $location_raw = isset($_POST['location']) ? wp_unslash($_POST['location']) : 'all';
        $location_filter = $this->resolve_dashboard_location_filter($location_raw);
        $status_raw = isset($_POST['status']) ? wp_unslash($_POST['status']) : 'all';
        if (is_array($status_raw)) {
            $status_raw = reset($status_raw);
        }
        $status_filter = is_string($status_raw) ? sanitize_text_field($status_raw) : 'all';

        // Get filtered data
        $orders_data = $this->get_orders_data_efficiently($date_from, $date_to, $location_filter, $status_filter);
        $recent_products_data = $this->get_recent_products_data($date_from, $date_to, $location_filter);
        $low_stock_products = $this->get_low_stock_products_efficiently($location_filter);
        $product_counts = $this->get_product_counts_by_location($date_from, $date_to, $location_filter);
        $stock_levels = $this->get_stock_levels_by_location($date_from, $date_to, $location_filter);

        $period = null;
        $comparison = null;
        $previous_orders_data = null;
        $previous_products_data = null;
        $previous_product_counts = null;
        $previous_stock_levels = null;
        $investment_data = null;
        $previous_investment_data = null;
        $include_default = ($location_filter === 'default');

        if (!empty($date_from) && !empty($date_to)) {
            $period = $this->get_period_window($date_from, $date_to);
        }

        if (!empty($period)) {
            $previous_orders_data = $this->get_orders_data_efficiently(
                $period['previous_start']->format('Y-m-d'),
                $period['previous_end']->format('Y-m-d'),
                $location_filter,
                $status_filter
            );

            $comparison = $this->build_period_comparison($period, $orders_data, $previous_orders_data);

            $previous_products_data = $this->get_recent_products_data(
                $period['previous_start']->format('Y-m-d'),
                $period['previous_end']->format('Y-m-d'),
                $location_filter
            );

            $previous_product_counts = $this->get_product_counts_by_location(
                $period['previous_start']->format('Y-m-d'),
                $period['previous_end']->format('Y-m-d'),
                $location_filter
            );

            $previous_stock_levels = $this->get_stock_levels_by_location(
                $period['previous_start']->format('Y-m-d'),
                $period['previous_end']->format('Y-m-d'),
                $location_filter
            );

            $investment_data = $this->get_investment_data($date_from, $date_to, $location_filter);
            $previous_investment_data = $this->get_investment_data(
                $period['previous_start']->format('Y-m-d'),
                $period['previous_end']->format('Y-m-d'),
                $location_filter
            );

            $current_products_total = $this->get_total_products_count($location_filter, $date_from, $date_to);
            $previous_products_total = $this->get_total_products_count(
                $location_filter,
                $period['previous_start']->format('Y-m-d'),
                $period['previous_end']->format('Y-m-d')
            );
            $current_locations_total = $this->count_active_locations($orders_data['orders'], $include_default);
            $previous_locations_total = $this->count_active_locations($previous_orders_data['orders'], $include_default);
            $current_investment_total = array_sum($investment_data['totals']);
            $previous_investment_total = array_sum($previous_investment_data['totals']);

            $comparison['products'] = $this->build_comparison_metric($current_products_total, $previous_products_total);
            $comparison['locations'] = $this->build_comparison_metric($current_locations_total, $previous_locations_total);
            $comparison['investment'] = $this->build_comparison_metric($current_investment_total, $previous_investment_total);
        }

        $location_card = $this->get_location_card_summary($location_filter);
        $summary = array(
            'total_orders' => array_sum($orders_data['orders']),
            'total_sell' => array_sum($orders_data['sales'] ?? []),
            'total_revenue' => array_sum($orders_data['revenue']),
        );

        if (!empty($period)) {
            $summary['total_products'] = $this->get_total_products_count($location_filter, $date_from, $date_to);
            $summary['total_locations'] = ($location_card['mode'] ?? 'count') === 'selected'
                ? (int) ($location_card['count'] ?? 0)
                : $this->count_active_locations($orders_data['orders'], $include_default);
            $summary['total_investment'] = array_sum($investment_data['totals']);
        } else {
            if (empty($mulopimfwc_locations) || is_wp_error($mulopimfwc_locations)) {
                $mulopimfwc_locations = [];
            }
            $summary['total_products'] = $this->get_total_products_count($location_filter);
            $summary['total_locations'] = (int) ($location_card['count'] ?? 0);
            $summary['total_investment'] = $this->calculate_total_investment_efficiently($location_filter);
        }

        $summary['location_card_label'] = isset($location_card['label']) ? (string) $location_card['label'] : __('Locations', 'multi-location-product-and-inventory-management-pro');
        $summary['location_card_value'] = $location_card['mode'] === 'selected'
            ? (string) ($location_card['value'] ?? '')
            : (int) $summary['total_locations'];

        if (!empty($comparison) && ($location_card['mode'] ?? 'count') === 'selected') {
            $comparison['locations'] = null;
        }

        $response = array(
            'orders' => $orders_data['orders'],
            'revenue' => $orders_data['revenue'],
            'productCounts' => $product_counts,
            'stockLevels' => $stock_levels,
            'recent_products' => $recent_products_data,
            'dateLabels' => $recent_products_data['labels'],
            'dateCounts' => $recent_products_data['counts'],
            'low_stock' => $low_stock_products,
            'summary' => $summary,
            'currency' => $this->get_dashboard_reporting_currency_symbol(),
            'currency_code' => $this->get_dashboard_reporting_currency_code(),
        );

        if (!empty($investment_data)) {
            $response['monthlyInvestmentLabels'] = $investment_data['labels'];
            $response['monthlyInvestmentData'] = $investment_data['totals'];
        } else {
            $monthly_investment_data = $this->get_monthly_investment_data_cached($location_filter);
            $response['monthlyInvestmentLabels'] = $monthly_investment_data['labels'];
            $response['monthlyInvestmentData'] = $monthly_investment_data['data'];
        }

        if (!empty($previous_products_data)) {
            $response['previousDateCounts'] = $previous_products_data['counts'];
        }

        if (!empty($previous_product_counts)) {
            $response['previousProductCounts'] = $previous_product_counts;
        }

        if (!empty($previous_stock_levels)) {
            $response['previousStockLevels'] = $previous_stock_levels;
        }

        if (!empty($previous_investment_data)) {
            $response['previousInvestmentData'] = $previous_investment_data['totals'];
        }

        if (!empty($previous_orders_data)) {
            $response['previousOrders'] = $previous_orders_data['orders'];
            $response['previousRevenue'] = $previous_orders_data['revenue'];
        }

        if (!empty($comparison)) {
            $response['comparison'] = $comparison;
        }

        wp_send_json_success($response);
    }

    /**
     * AJAX endpoint for deferred profitability panel loading.
     */
    public function handle_profitability_panel_request()
    {
        check_ajax_referer('mulopimfwc_dashboard_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error([
                'message' => __('Permission denied', 'multi-location-product-and-inventory-management-pro'),
            ]);
            return;
        }

        $location_raw = isset($_POST['location']) ? wp_unslash($_POST['location']) : 'all';
        $location_filter = $this->resolve_dashboard_location_filter($location_raw);
        $dead_stock_days = isset($_POST['dead_stock_days']) ? absint(wp_unslash($_POST['dead_stock_days'])) : 90;
        if ($dead_stock_days <= 0) {
            $dead_stock_days = 90;
        }

        $profitability_data = $this->get_location_profitability_data($dead_stock_days, $location_filter);

        wp_send_json_success([
            'html' => $this->render_profitability_panel_html($profitability_data, $dead_stock_days),
        ]);
    }

    /**
     * Build comparison metrics for the previous period.
     */
    private function build_period_comparison(array $period, array $current_orders_data, array $previous_orders_data)
    {
        $current_orders_total = array_sum($current_orders_data['orders']);
        $current_sell_total = array_sum($current_orders_data['sales'] ?? []);
        $current_revenue_total = array_sum($current_orders_data['revenue']);
        $previous_orders_total = array_sum($previous_orders_data['orders']);
        $previous_sell_total = array_sum($previous_orders_data['sales'] ?? []);
        $previous_revenue_total = array_sum($previous_orders_data['revenue']);

        return [
            'label' => $period['label'],
            'period_days' => $period['days'],
            'previous_range' => [
                'from' => $period['previous_start']->format('Y-m-d'),
                'to' => $period['previous_end']->format('Y-m-d'),
            ],
            'orders' => $this->build_comparison_metric($current_orders_total, $previous_orders_total),
            'sell' => $this->build_comparison_metric($current_sell_total, $previous_sell_total),
            'revenue' => $this->build_comparison_metric($current_revenue_total, $previous_revenue_total),
        ];
    }

    /**
     * Resolve current and previous date windows for comparisons.
     */
    private function get_period_window($date_from, $date_to)
    {
        $start_date = DateTimeImmutable::createFromFormat('Y-m-d', $date_from);
        $end_date = DateTimeImmutable::createFromFormat('Y-m-d', $date_to);

        if (!$start_date || !$end_date || $end_date < $start_date) {
            return null;
        }

        $days = (int) $start_date->diff($end_date)->days + 1;
        if ($days <= 0) {
            return null;
        }

        $interval = new DateInterval('P' . $days . 'D');
        $previous_start = $start_date->sub($interval);
        $previous_end = $end_date->sub($interval);

        $label = sprintf(
            /* translators: %d: number of days */
            _n('vs previous %d day', 'vs previous %d days', $days, 'multi-location-product-and-inventory-management-pro'),
            $days
        );

        return [
            'start' => $start_date,
            'end' => $end_date,
            'previous_start' => $previous_start,
            'previous_end' => $previous_end,
            'days' => $days,
            'label' => $label,
        ];
    }

    /**
     * Count locations with activity in the current period.
     */
    private function count_active_locations(array $orders_by_location, $include_default = false)
    {
        $count = 0;

        foreach ($orders_by_location as $location => $orders) {
            if (!$include_default && $location === 'Default') {
                continue;
            }

            if ((int) $orders > 0) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Normalize comparison metrics.
     */
    private function build_comparison_metric($current, $previous)
    {
        $current_value = (float) $current;
        $previous_value = (float) $previous;
        $diff = $current_value - $previous_value;

        $direction = 'flat';
        if ($diff > 0) {
            $direction = 'up';
        } elseif ($diff < 0) {
            $direction = 'down';
        }

        $percent = null;
        if ($previous_value > 0) {
            $percent = round(abs(($diff / $previous_value) * 100), 1);
        } elseif ($current_value > 0) {
            $direction = 'new';
        } else {
            $percent = 0.0;
        }

        return [
            'current' => $current_value,
            'previous' => $previous_value,
            'diff' => $diff,
            'percent' => $percent,
            'direction' => $direction,
        ];
    }

    /**
     * Check whether WooCommerce HPOS is authoritative for orders.
     */
    private function is_dashboard_hpos_enabled(): bool
    {
        if (class_exists('Automattic\\WooCommerce\\Utilities\\OrderUtil')) {
            return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        }

        if (function_exists('wc_get_order_datastore')) {
            $datastore = wc_get_order_datastore();
            return is_a($datastore, 'Automattic\\WooCommerce\\Internal\\DataStores\\Orders\\OrdersTableDataStore');
        }

        if (function_exists('wc_get_container') && class_exists('Automattic\\WooCommerce\\Internal\\DataStores\\Orders\\CustomOrdersTableController')) {
            try {
                $controller = wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class);
                if (method_exists($controller, 'custom_orders_table_usage_is_enabled')) {
                    return (bool) $controller->custom_orders_table_usage_is_enabled();
                }
            } catch (Exception $e) {
                return false;
            }
        }

        return false;
    }

    /**
     * Normalize dashboard status values for direct SQL queries.
     */
    private function get_dashboard_order_query_statuses($status_filter): array
    {
        $status_values = $status_filter === 'all'
            ? ['completed', 'pending', 'processing', 'on-hold']
            : (array) $status_filter;

        $statuses = [];
        foreach ($status_values as $status) {
            if (is_array($status)) {
                continue;
            }

            $normalized_status = sanitize_key((string) $status);
            if ($normalized_status === '' || $normalized_status === 'all') {
                continue;
            }

            if (strpos($normalized_status, 'wc-') !== 0) {
                $normalized_status = 'wc-' . $normalized_status;
            }

            $statuses[] = $normalized_status;
        }

        if (empty($statuses)) {
            return ['wc-completed', 'wc-pending', 'wc-processing', 'wc-on-hold'];
        }

        return array_values(array_unique($statuses));
    }

    /**
     * Strip the WooCommerce status prefix for comparisons.
     */
    private function normalize_dashboard_order_status(string $status): string
    {
        $normalized_status = sanitize_key($status);
        if (strpos($normalized_status, 'wc-') === 0) {
            $normalized_status = substr($normalized_status, 3);
        }

        return $normalized_status;
    }

    /**
     * Build local and UTC date ranges for direct order queries.
     */
    private function get_dashboard_order_date_range($date_from = '', $date_to = ''): array
    {
        $local_timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        $utc_timezone = new DateTimeZone('UTC');

        $range = [
            'local_from' => '',
            'local_to' => '',
            'gmt_from' => '',
            'gmt_to' => '',
        ];

        try {
            if (is_string($date_from) && $date_from !== '') {
                $from_local = new DateTimeImmutable($date_from . ' 00:00:00', $local_timezone);
                $range['local_from'] = $from_local->format('Y-m-d H:i:s');
                $range['gmt_from'] = $from_local->setTimezone($utc_timezone)->format('Y-m-d H:i:s');
            }

            if (is_string($date_to) && $date_to !== '') {
                $to_local = new DateTimeImmutable($date_to . ' 00:00:00', $local_timezone);
                $to_local = $to_local->modify('+1 day');
                $range['local_to'] = $to_local->format('Y-m-d H:i:s');
                $range['gmt_to'] = $to_local->setTimezone($utc_timezone)->format('Y-m-d H:i:s');
            }
        } catch (Exception $e) {
            return [
                'local_from' => '',
                'local_to' => '',
                'gmt_from' => '',
                'gmt_to' => '',
            ];
        }

        return $range;
    }

    /**
     * Map dashboard location slugs to labels.
     */
    private function get_dashboard_location_label_map(array $locations): array
    {
        $label_map = [
            'default' => 'Default',
        ];

        foreach ($locations as $location) {
            if (!is_object($location)) {
                continue;
            }

            $slug = $this->normalize_location_slug($location->slug ?? '');
            if ($slug === '') {
                continue;
            }

            $label_map[$slug] = (string) ($location->name ?? $slug);
        }

        return $label_map;
    }

    /**
     * Build location currency metadata used for aggregate revenue conversion.
     */
    private function get_dashboard_location_currency_map(array $locations): array
    {
        $currency_map = [];

        foreach ($locations as $location) {
            if (!is_object($location)) {
                continue;
            }

            $slug = $this->normalize_location_slug($location->slug ?? '');
            $term_id = isset($location->term_id) ? (int) $location->term_id : 0;
            if ($slug === '' || $term_id <= 0) {
                continue;
            }

            $target_currency = '';
            if (function_exists('mulopimfwc_get_currency_settings_for_location')) {
                $currency_settings = (array) mulopimfwc_get_currency_settings_for_location($term_id);
                $target_currency = strtoupper(trim((string) ($currency_settings['currency'] ?? '')));
            }

            if ($target_currency === '') {
                $target_currency = strtoupper(trim((string) get_term_meta($term_id, 'location_currency', true)));
            }

            $rate_raw = get_term_meta($term_id, 'location_currency_rate', true);
            $rate = (is_numeric($rate_raw) && (float) $rate_raw > 0) ? (float) $rate_raw : 0.0;

            $currency_map[$slug] = [
                'target_currency' => $target_currency,
                'rate' => $rate,
            ];
        }

        return $currency_map;
    }

    /**
     * Convert an aggregate order amount into the dashboard reporting currency.
     */
    private function convert_dashboard_aggregate_amount_to_base_currency(float $amount, string $order_currency, string $location_slug, array $location_currency_map, string $base_currency): float
    {
        $normalized_currency = strtoupper(trim($order_currency));
        if ($normalized_currency === '' || $normalized_currency === $base_currency) {
            return $amount;
        }

        $location_slug = $this->normalize_location_slug($location_slug);
        if ($location_slug === '' || !isset($location_currency_map[$location_slug])) {
            return $amount;
        }

        $target_currency = strtoupper(trim((string) ($location_currency_map[$location_slug]['target_currency'] ?? '')));
        $rate = (float) ($location_currency_map[$location_slug]['rate'] ?? 0);

        if ($target_currency === '' || $rate <= 0 || $target_currency !== $normalized_currency || $target_currency === $base_currency) {
            return $amount;
        }

        $converted_amount = $amount / $rate;
        if (function_exists('wc_format_decimal')) {
            return (float) wc_format_decimal($converted_amount, 6, false);
        }

        return round($converted_amount, 6);
    }

    /**
     * Namespace dashboard order caches so metric-definition changes do not reuse
     * payloads built with older revenue logic.
     */
    private function get_dashboard_orders_cache_namespace(): string
    {
        return 'line_margin_sales_v2';
    }

    /**
     * Get orders data efficiently.
     */
    private function get_orders_data_efficiently($date_from = '', $date_to = '', $location_filter = 'all', $status_filter = 'all')
    {
        global $mulopimfwc_locations, $wpdb;

        $normalized_location_filter = $this->normalize_location_slug($location_filter);
        $manager_location_slugs = $this->get_dashboard_manager_assigned_location_slugs();
        $is_manager_scope = is_array($manager_location_slugs);
        $locations = is_array($mulopimfwc_locations) ? $mulopimfwc_locations : [];

        $orders_by_location = $is_manager_scope ? [] : ['Default' => 0];
        $location_sales = $is_manager_scope ? [] : ['Default' => 0];
        $location_revenue = $is_manager_scope ? [] : ['Default' => 0];

        if ($is_manager_scope) {
            if (empty($manager_location_slugs)) {
                return [
                    'orders' => $orders_by_location,
                    'sales' => $location_sales,
                    'revenue' => $location_revenue,
                ];
            }

            if ($normalized_location_filter === 'default') {
                return [
                    'orders' => $orders_by_location,
                    'sales' => $location_sales,
                    'revenue' => $location_revenue,
                ];
            }

            if (
                $normalized_location_filter !== 'all' &&
                !in_array($normalized_location_filter, $manager_location_slugs, true)
            ) {
                return [
                    'orders' => $orders_by_location,
                    'sales' => $location_sales,
                    'revenue' => $location_revenue,
                ];
            }
        }

        $cache_version = function_exists('mulopimfwc_get_dashboard_cache_version')
            ? mulopimfwc_get_dashboard_cache_version()
            : 1;
        $cache_scope = $this->get_live_dashboard_cache_scope_hash();
        $cache_key = 'mulopimfwc_dashboard_orders_' . $this->get_dashboard_orders_cache_namespace() . '_v' . $cache_version . '_' . $cache_scope . '_' . md5(wp_json_encode([
            'date_from' => (string) $date_from,
            'date_to' => (string) $date_to,
            'location_filter' => $normalized_location_filter,
            'status_filter' => (string) $status_filter,
        ]));
        $cached_data = get_transient($cache_key);
        if (is_array($cached_data) && isset($cached_data['orders'], $cached_data['sales'], $cached_data['revenue'])) {
            return $cached_data;
        }

        $query_statuses = $this->get_dashboard_order_query_statuses($status_filter);
        $revenue_query_statuses = array_values(array_intersect(
            $query_statuses,
            $this->get_dashboard_order_query_statuses(
                function_exists('mulopimfwc_get_revenue_order_statuses')
                    ? (array) mulopimfwc_get_revenue_order_statuses()
                    : ['processing', 'completed']
            )
        ));
        $date_range = $this->get_dashboard_order_date_range($date_from, $date_to);
        $label_map = $this->get_dashboard_location_label_map($locations);
        $currency_map = $this->get_dashboard_location_currency_map($locations);
        $base_currency = function_exists('mulopimfwc_get_store_base_currency_code_raw')
            ? strtoupper(trim((string) mulopimfwc_get_store_base_currency_code_raw()))
            : strtoupper(trim((string) get_option('woocommerce_currency', 'USD')));

        foreach ($label_map as $slug => $label) {
            if ($slug === 'default') {
                continue;
            }

            $orders_by_location[$label] = 0;
            $location_sales[$label] = 0;
            $location_revenue[$label] = 0;
        }

        $status_placeholders = implode(',', array_fill(0, count($query_statuses), '%s'));
        $date_clauses = [];
        $date_params = [];
        $location_clause = '';
        $location_params = [];

        if ($this->is_dashboard_hpos_enabled()) {
            $orders_table = $wpdb->prefix . 'wc_orders';
            $meta_table = $wpdb->prefix . 'wc_orders_meta';

            if ($date_range['gmt_from'] !== '') {
                $date_clauses[] = "orders.date_created_gmt >= %s";
                $date_params[] = $date_range['gmt_from'];
            }
            if ($date_range['gmt_to'] !== '') {
                $date_clauses[] = "orders.date_created_gmt < %s";
                $date_params[] = $date_range['gmt_to'];
            }

            if ($is_manager_scope) {
                if ($normalized_location_filter === 'all') {
                    $placeholders = implode(',', array_fill(0, count($manager_location_slugs), '%s'));
                    $location_clause = " AND location_meta.meta_value IN ({$placeholders})";
                    $location_params = $manager_location_slugs;
                } else {
                    $location_clause = " AND location_meta.meta_value = %s";
                    $location_params[] = $normalized_location_filter;
                }
            } elseif ($normalized_location_filter === 'default') {
                $location_clause = " AND (location_meta.meta_value IS NULL OR location_meta.meta_value = '' OR location_meta.meta_value = 'default')";
            } elseif ($normalized_location_filter !== 'all') {
                $location_clause = " AND location_meta.meta_value = %s";
                $location_params[] = $normalized_location_filter;
            }

            $date_sql = empty($date_clauses) ? '' : ' AND ' . implode(' AND ', $date_clauses);
            $sql = "
                SELECT COALESCE(location_meta.meta_value, '') AS location_slug,
                       COUNT(DISTINCT orders.id) AS order_count
                FROM {$orders_table} orders
                LEFT JOIN {$meta_table} location_meta
                    ON location_meta.order_id = orders.id
                    AND location_meta.meta_key = '_store_location'
                LEFT JOIN {$meta_table} split_meta
                    ON split_meta.order_id = orders.id
                    AND split_meta.meta_key = '_mulopimfwc_split_parent'
                WHERE orders.type = 'shop_order'
                    AND orders.status IN ({$status_placeholders})
                    AND (split_meta.meta_value IS NULL OR split_meta.meta_value != 'yes')
                    {$date_sql}
                    {$location_clause}
                GROUP BY location_slug
            ";

            $query = $wpdb->prepare($sql, array_merge($query_statuses, $date_params, $location_params));
        } else {
            if ($date_range['local_from'] !== '') {
                $date_clauses[] = "orders.post_date >= %s";
                $date_params[] = $date_range['local_from'];
            }
            if ($date_range['local_to'] !== '') {
                $date_clauses[] = "orders.post_date < %s";
                $date_params[] = $date_range['local_to'];
            }

            if ($is_manager_scope) {
                if ($normalized_location_filter === 'all') {
                    $placeholders = implode(',', array_fill(0, count($manager_location_slugs), '%s'));
                    $location_clause = " AND location_meta.meta_value IN ({$placeholders})";
                    $location_params = $manager_location_slugs;
                } else {
                    $location_clause = " AND location_meta.meta_value = %s";
                    $location_params[] = $normalized_location_filter;
                }
            } elseif ($normalized_location_filter === 'default') {
                $location_clause = " AND (location_meta.meta_value IS NULL OR location_meta.meta_value = '' OR location_meta.meta_value = 'default')";
            } elseif ($normalized_location_filter !== 'all') {
                $location_clause = " AND location_meta.meta_value = %s";
                $location_params[] = $normalized_location_filter;
            }

            $date_sql = empty($date_clauses) ? '' : ' AND ' . implode(' AND ', $date_clauses);
            $sql = "
                SELECT COALESCE(location_meta.meta_value, '') AS location_slug,
                       COUNT(DISTINCT orders.ID) AS order_count
                FROM {$wpdb->posts} orders
                LEFT JOIN {$wpdb->postmeta} location_meta
                    ON location_meta.post_id = orders.ID
                    AND location_meta.meta_key = '_store_location'
                LEFT JOIN {$wpdb->postmeta} split_meta
                    ON split_meta.post_id = orders.ID
                    AND split_meta.meta_key = '_mulopimfwc_split_parent'
                WHERE orders.post_type = 'shop_order'
                    AND orders.post_status IN ({$status_placeholders})
                    AND (split_meta.meta_value IS NULL OR split_meta.meta_value != 'yes')
                    {$date_sql}
                    {$location_clause}
                GROUP BY location_slug
            ";

            $query = $wpdb->prepare($sql, array_merge($query_statuses, $date_params, $location_params));
        }

        $rows = $wpdb->get_results($query);
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $location_slug = $this->normalize_location_slug((string) ($row->location_slug ?? ''));
                if ($location_slug === '') {
                    $location_slug = 'default';
                }

                if ($is_manager_scope && !in_array($location_slug, $manager_location_slugs, true)) {
                    continue;
                }

                $location_label = $label_map[$location_slug] ?? ($is_manager_scope ? '' : 'Default');
                if ($location_label === '') {
                    continue;
                }

                $order_count = isset($row->order_count) ? (int) $row->order_count : 0;
                $orders_by_location[$location_label] = (int) ($orders_by_location[$location_label] ?? 0) + $order_count;
            }
        }

        if (!empty($revenue_query_statuses)) {
            $resolved_location_sql = "LOWER(TRIM(COALESCE(NULLIF(item_location_meta.meta_value, ''), NULLIF(order_location_meta.meta_value, ''), '')))";
            $product_id_sql = "
                CASE
                    WHEN CAST(COALESCE(variation_id_meta.meta_value, '0') AS UNSIGNED) > 0
                        THEN CAST(variation_id_meta.meta_value AS UNSIGNED)
                    ELSE CAST(COALESCE(product_id_meta.meta_value, '0') AS UNSIGNED)
                END
            ";
            $ordered_quantity_sql = "GREATEST(CAST(COALESCE(qty_meta.meta_value, '0') AS DECIMAL(24,6)), 0)";
            $net_quantity_sql = "
                CASE
                    WHEN reduced_stock_meta.meta_value IS NOT NULL AND reduced_stock_meta.meta_value != ''
                        THEN GREATEST(
                            LEAST(
                                ABS(CAST(reduced_stock_meta.meta_value AS DECIMAL(24,6))),
                                {$ordered_quantity_sql}
                            ),
                            0
                        )
                    ELSE {$ordered_quantity_sql}
                END
            ";
            $line_total_sql = "GREATEST(CAST(COALESCE(line_total_meta.meta_value, '0') AS DECIMAL(24,6)), 0)";
            $net_line_total_sql = "
                CASE
                    WHEN {$ordered_quantity_sql} > 0
                        THEN {$line_total_sql} * ({$net_quantity_sql} / {$ordered_quantity_sql})
                    ELSE 0
                END
            ";

            $revenue_status_placeholders = implode(',', array_fill(0, count($revenue_query_statuses), '%s'));
            $revenue_date_clauses = [];
            $revenue_date_params = [];
            $revenue_location_clause = '';
            $revenue_location_params = [];

            if ($this->is_dashboard_hpos_enabled()) {
                $revenue_orders_table = $wpdb->prefix . 'wc_orders';
                $revenue_order_meta_table = $wpdb->prefix . 'wc_orders_meta';
                $revenue_order_id_column = 'orders.id';
                $revenue_order_status_column = 'orders.status';
                $revenue_order_type_clause = "orders.type = 'shop_order'";
                $revenue_order_currency_sql = "COALESCE(orders.currency, '')";
                $revenue_meta_join_key = 'order_id';
                $revenue_prepare_prefix = [];

                if ($date_range['gmt_from'] !== '') {
                    $revenue_date_clauses[] = "orders.date_created_gmt >= %s";
                    $revenue_date_params[] = $date_range['gmt_from'];
                }
                if ($date_range['gmt_to'] !== '') {
                    $revenue_date_clauses[] = "orders.date_created_gmt < %s";
                    $revenue_date_params[] = $date_range['gmt_to'];
                }
            } else {
                $revenue_orders_table = $wpdb->posts;
                $revenue_order_meta_table = $wpdb->postmeta;
                $revenue_order_id_column = 'orders.ID';
                $revenue_order_status_column = 'orders.post_status';
                $revenue_order_type_clause = "orders.post_type = 'shop_order'";
                $revenue_order_currency_sql = "COALESCE(currency_meta.meta_value, %s)";
                $revenue_meta_join_key = 'post_id';
                $revenue_prepare_prefix = [$base_currency];

                if ($date_range['local_from'] !== '') {
                    $revenue_date_clauses[] = "orders.post_date >= %s";
                    $revenue_date_params[] = $date_range['local_from'];
                }
                if ($date_range['local_to'] !== '') {
                    $revenue_date_clauses[] = "orders.post_date < %s";
                    $revenue_date_params[] = $date_range['local_to'];
                }
            }

            if ($is_manager_scope) {
                if ($normalized_location_filter === 'all') {
                    $placeholders = implode(',', array_fill(0, count($manager_location_slugs), '%s'));
                    $revenue_location_clause = " AND {$resolved_location_sql} IN ({$placeholders})";
                    $revenue_location_params = $manager_location_slugs;
                } else {
                    $revenue_location_clause = " AND {$resolved_location_sql} = %s";
                    $revenue_location_params[] = $normalized_location_filter;
                }
            } elseif ($normalized_location_filter === 'default') {
                $revenue_location_clause = " AND ({$resolved_location_sql} = '' OR {$resolved_location_sql} = 'default')";
            } elseif ($normalized_location_filter !== 'all') {
                $revenue_location_clause = " AND {$resolved_location_sql} = %s";
                $revenue_location_params[] = $normalized_location_filter;
            }

            $revenue_date_sql = empty($revenue_date_clauses) ? '' : ' AND ' . implode(' AND ', $revenue_date_clauses);
            $order_items_table = $wpdb->prefix . 'woocommerce_order_items';
            $order_itemmeta_table = $wpdb->prefix . 'woocommerce_order_itemmeta';
            $currency_join_sql = $this->is_dashboard_hpos_enabled()
                ? ''
                : "
                LEFT JOIN {$wpdb->postmeta} currency_meta
                    ON currency_meta.post_id = orders.ID
                    AND currency_meta.meta_key = '_order_currency'
            ";

            $revenue_sql = "
                SELECT {$resolved_location_sql} AS location_slug,
                       {$revenue_order_currency_sql} AS order_currency,
                       {$product_id_sql} AS product_id,
                       SUM({$net_quantity_sql}) AS quantity,
                       SUM({$net_line_total_sql}) AS sales_total
                FROM {$revenue_orders_table} orders
                INNER JOIN {$order_items_table} order_items
                    ON order_items.order_id = {$revenue_order_id_column}
                    AND order_items.order_item_type = 'line_item'
                LEFT JOIN {$order_itemmeta_table} product_id_meta
                    ON product_id_meta.order_item_id = order_items.order_item_id
                    AND product_id_meta.meta_key = '_product_id'
                LEFT JOIN {$order_itemmeta_table} variation_id_meta
                    ON variation_id_meta.order_item_id = order_items.order_item_id
                    AND variation_id_meta.meta_key = '_variation_id'
                LEFT JOIN {$order_itemmeta_table} qty_meta
                    ON qty_meta.order_item_id = order_items.order_item_id
                    AND qty_meta.meta_key = '_qty'
                LEFT JOIN {$order_itemmeta_table} line_total_meta
                    ON line_total_meta.order_item_id = order_items.order_item_id
                    AND line_total_meta.meta_key = '_line_total'
                LEFT JOIN {$order_itemmeta_table} reduced_stock_meta
                    ON reduced_stock_meta.order_item_id = order_items.order_item_id
                    AND reduced_stock_meta.meta_key = '_reduced_stock'
                LEFT JOIN {$order_itemmeta_table} item_location_meta
                    ON item_location_meta.order_item_id = order_items.order_item_id
                    AND item_location_meta.meta_key = '_mulopimfwc_location'
                LEFT JOIN {$revenue_order_meta_table} order_location_meta
                    ON order_location_meta.{$revenue_meta_join_key} = {$revenue_order_id_column}
                    AND order_location_meta.meta_key = '_store_location'
                LEFT JOIN {$revenue_order_meta_table} split_meta
                    ON split_meta.{$revenue_meta_join_key} = {$revenue_order_id_column}
                    AND split_meta.meta_key = '_mulopimfwc_split_parent'
                {$currency_join_sql}
                WHERE {$revenue_order_type_clause}
                    AND {$revenue_order_status_column} IN ({$revenue_status_placeholders})
                    AND (split_meta.meta_value IS NULL OR split_meta.meta_value != 'yes')
                    AND {$product_id_sql} > 0
                    AND {$net_quantity_sql} > 0
                    {$revenue_date_sql}
                    {$revenue_location_clause}
                GROUP BY location_slug, order_currency, {$product_id_sql}
            ";

            $revenue_query = $wpdb->prepare(
                $revenue_sql,
                array_merge($revenue_prepare_prefix, $revenue_query_statuses, $revenue_date_params, $revenue_location_params)
            );
            $revenue_rows = $wpdb->get_results($revenue_query);

            if (is_array($revenue_rows)) {
                foreach ($revenue_rows as $row) {
                    $location_slug = $this->normalize_location_slug((string) ($row->location_slug ?? ''));
                    if ($location_slug === '') {
                        $location_slug = 'default';
                    }

                    if ($is_manager_scope && !in_array($location_slug, $manager_location_slugs, true)) {
                        continue;
                    }

                    $location_label = $label_map[$location_slug] ?? ($is_manager_scope ? '' : 'Default');
                    if ($location_label === '') {
                        continue;
                    }

                    $product_id = isset($row->product_id) ? (int) $row->product_id : 0;
                    $quantity = isset($row->quantity) ? (float) $row->quantity : 0.0;
                    if ($product_id <= 0 || $quantity <= 0) {
                        continue;
                    }

                    $sales_total = isset($row->sales_total) ? (float) $row->sales_total : 0.0;
                    $order_currency = (string) ($row->order_currency ?? '');
                    $sales_total_in_base = $this->convert_dashboard_aggregate_amount_to_base_currency(
                        $sales_total,
                        $order_currency,
                        $location_slug,
                        $currency_map,
                        $base_currency
                    );
                    $purchase_price = $this->get_investment_product_purchase_price($product_id);

                    $location_sales[$location_label] = (float) ($location_sales[$location_label] ?? 0)
                        + $sales_total_in_base;
                    $location_revenue[$location_label] = (float) ($location_revenue[$location_label] ?? 0)
                        + ($sales_total_in_base - ($purchase_price * $quantity));
                }
            }
        }

        $location_sales = array_map(static function ($amount) {
            if (function_exists('wc_format_decimal')) {
                return (float) wc_format_decimal($amount, 6, false);
            }

            return round((float) $amount, 6);
        }, $location_sales);

        $location_revenue = array_map(static function ($amount) {
            if (function_exists('wc_format_decimal')) {
                return (float) wc_format_decimal($amount, 6, false);
            }

            return round((float) $amount, 6);
        }, $location_revenue);

        $result = [
            'orders' => $orders_by_location,
            'sales' => $location_sales,
            'revenue' => $location_revenue,
        ];
        set_transient($cache_key, $result, 2 * MINUTE_IN_SECONDS);

        return $result;
    }


    /**
     * Normalize stock alert rows so every renderer consumes the same shape.
     */
    private function normalize_stock_alert_item(array $item): array
    {
        $product_id = isset($item['product_id']) ? absint($item['product_id']) : 0;
        $location_id = isset($item['location_id']) ? absint($item['location_id']) : 0;
        $status = isset($item['status']) && $item['status'] === 'out_of_stock' ? 'out_of_stock' : 'low_stock';

        $item['product_id'] = $product_id;
        $item['location_id'] = $location_id;
        $item['edit_post_id'] = isset($item['edit_post_id']) ? absint($item['edit_post_id']) : $product_id;
        $item['stock'] = isset($item['stock']) ? (int) $item['stock'] : 0;
        $item['status'] = $status;
        $item['status_class'] = isset($item['status_class']) && $item['status_class'] !== ''
            ? sanitize_html_class($item['status_class'])
            : ($status === 'out_of_stock' ? 'out-of-stock' : 'low-stock');
        $item['status_label'] = isset($item['status_label']) && $item['status_label'] !== ''
            ? (string) $item['status_label']
            : ($status === 'out_of_stock'
                ? __('Out of Stock', 'multi-location-product-and-inventory-management-pro')
                : __('Low Stock', 'multi-location-product-and-inventory-management-pro'));
        $item['alert_key'] = isset($item['alert_key']) && is_string($item['alert_key']) && $item['alert_key'] !== ''
            ? $item['alert_key']
            : sprintf('%d:%d', $product_id, $location_id);

        return $item;
    }

    /**
     * Build a normalized stock alert row from a location stock record.
     */
    private function build_stock_alert_item($location, $result)
    {
        $product_id = isset($result->ID) ? absint($result->ID) : 0;
        $location_id = isset($location->term_id) ? absint($location->term_id) : 0;
        if ($product_id <= 0 || $location_id <= 0) {
            return null;
        }

        $stock = isset($result->stock) ? (int) $result->stock : 0;
        $stock_state = function_exists('mulopimfwc_get_stock_alert_state')
            ? mulopimfwc_get_stock_alert_state($stock, $product_id, $location_id)
            : [
                'is_alert' => true,
                'status' => $stock <= 0 ? 'out_of_stock' : 'low_stock',
                'status_class' => $stock <= 0 ? 'out-of-stock' : 'low-stock',
                'status_label' => $stock <= 0
                    ? __('Out of Stock', 'multi-location-product-and-inventory-management-pro')
                    : __('Low Stock', 'multi-location-product-and-inventory-management-pro'),
                'low_threshold' => 0,
                'out_threshold' => 0,
                'low_source' => '',
                'out_source' => '',
            ];

        if (empty($stock_state['is_alert']) || !in_array($stock_state['status'], ['low_stock', 'out_of_stock'], true)) {
            return null;
        }

        $product_title = isset($result->post_title) ? (string) $result->post_title : '';
        $edit_post_id = $product_id;

        if (function_exists('wc_get_product')) {
            $product = wc_get_product($product_id);
            if ($product) {
                $product_title = wp_strip_all_tags($product->get_formatted_name());
                if ($product->is_type('variation') && $product->get_parent_id() > 0) {
                    $edit_post_id = (int) $product->get_parent_id();
                }
            }
        }

        if ($product_title === '') {
            $product_title = !empty($result->parent_title) ? (string) $result->parent_title : ('#' . $product_id);
        }

        return $this->normalize_stock_alert_item([
            'alert_key' => sprintf('%d:%d', $product_id, $location_id),
            'product_id' => $product_id,
            'edit_post_id' => $edit_post_id,
            'product_title' => $product_title,
            'product_type' => isset($result->post_type) ? (string) $result->post_type : 'product',
            'location_id' => $location_id,
            'location_name' => isset($location->name) ? $location->name : '',
            'stock' => $stock,
            'status' => $stock_state['status'],
            'status_class' => $stock_state['status_class'] ?? '',
            'status_label' => $stock_state['status_label'] ?? '',
            'low_threshold' => (int) ($stock_state['low_threshold'] ?? 0),
            'out_threshold' => (int) ($stock_state['out_threshold'] ?? 0),
            'low_threshold_source' => (string) ($stock_state['low_source'] ?? ''),
            'out_threshold_source' => (string) ($stock_state['out_source'] ?? ''),
        ]);
    }

    /**
     * Get low stock products efficiently with limit.
     */
    private function get_low_stock_products_efficiently($location_filter = 'all')
    {
        global $wpdb, $mulopimfwc_locations;

        if (empty($mulopimfwc_locations)) {
            return [];
        }

        $normalized_location_filter = $this->normalize_location_slug($location_filter);
        $cache_version = function_exists('mulopimfwc_get_dashboard_cache_version')
            ? mulopimfwc_get_dashboard_cache_version()
            : 1;
        $cache_key = 'mulopimfwc_dashboard_low_stock_v' . $cache_version . '_' . $this->get_live_dashboard_cache_scope_hash() . '_' . md5($normalized_location_filter);
        $cached_data = get_transient($cache_key);
        if (is_array($cached_data)) {
            return $cached_data;
        }

        $low_stock_products = [];
        $locations_to_check = $mulopimfwc_locations;

        if ($normalized_location_filter !== 'all') {
            $locations_to_check = array_filter($mulopimfwc_locations, function ($loc) use ($normalized_location_filter) {
                return $loc->slug === $normalized_location_filter;
            });
        }

        foreach ($locations_to_check as $location) {
            $meta_key = '_location_stock_' . $location->term_id;
            $baseline_thresholds = function_exists('mulopimfwc_resolve_stock_alert_thresholds')
                ? mulopimfwc_resolve_stock_alert_thresholds(0, (int) $location->term_id)
                : [
                    'low_threshold' => mulopimfwc_get_location_threshold($location->term_id, 'low'),
                    'out_threshold' => mulopimfwc_get_location_threshold($location->term_id, 'out'),
                ];
            $baseline_low_threshold = max(0, (int) ($baseline_thresholds['low_threshold'] ?? 0));
            $baseline_out_threshold = max(0, (int) ($baseline_thresholds['out_threshold'] ?? 0));

            $term_taxonomy_id = isset($location->term_taxonomy_id) ? (int) $location->term_taxonomy_id : 0;
            if ($term_taxonomy_id <= 0) {
                $term_taxonomy_id = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND taxonomy = %s",
                    $location->term_id,
                    'mulopimfwc_store_location'
                ));
            }

            if ($term_taxonomy_id <= 0) {
                continue;
            }

            $query = $wpdb->prepare("
                SELECT DISTINCT p.ID, p.post_parent, p.post_type, p.post_title, parent.post_title AS parent_title,
                       pm.meta_value AS stock,
                       low_pm.meta_value AS product_low_threshold,
                       parent_low_pm.meta_value AS parent_low_threshold
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
                INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = %d
                    AND (
                        tr.object_id = p.ID
                        OR (p.post_type = 'product_variation' AND p.post_parent > 0 AND tr.object_id = p.post_parent)
                    )
                LEFT JOIN {$wpdb->posts} parent ON parent.ID = p.post_parent
                LEFT JOIN {$wpdb->postmeta} low_pm ON low_pm.post_id = p.ID AND low_pm.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} parent_low_pm ON parent_low_pm.post_id = p.post_parent AND parent_low_pm.meta_key = %s
                WHERE p.post_type IN ('product', 'product_variation')
                    AND p.post_status = 'publish'
                    AND pm.meta_value IS NOT NULL
                    AND pm.meta_value != ''
                ORDER BY CAST(pm.meta_value AS SIGNED) ASC, p.ID ASC
            ", $meta_key, $term_taxonomy_id, '_low_stock_amount', '_low_stock_amount');

            $results = $wpdb->get_results($query);
            $matches_for_location = 0;

            foreach ($results as $result) {
                $stock = (int) $result->stock;
                $product_low_threshold = null;

                if ($result->product_low_threshold !== '' && $result->product_low_threshold !== null) {
                    $product_low_threshold = max(0, (int) $result->product_low_threshold);
                } elseif ($result->post_type === 'product_variation' && $result->parent_low_threshold !== '' && $result->parent_low_threshold !== null) {
                    $product_low_threshold = max(0, (int) $result->parent_low_threshold);
                }

                $effective_low_threshold = $product_low_threshold !== null ? $product_low_threshold : $baseline_low_threshold;
                $effective_alert_threshold = max($effective_low_threshold, $baseline_out_threshold);
                if ($stock > $effective_alert_threshold) {
                    continue;
                }

                $item = $this->build_stock_alert_item($location, $result);
                if ($item === null) {
                    continue;
                }

                $low_stock_products[] = $item;
                $matches_for_location++;

                if ($matches_for_location >= 20) {
                    break;
                }
            }
        }

        set_transient($cache_key, $low_stock_products, 2 * MINUTE_IN_SECONDS);

        return $low_stock_products;
    }

    /**
     * Get recent products data efficiently
     */
    private function get_recent_products_data($date_from = '', $date_to = '', $location_filter = 'all')
    {
        global $wpdb;
        $normalized_location_filter = $this->normalize_location_slug($location_filter);
        $manager_location_term_ids = $this->get_dashboard_manager_assigned_location_term_ids();
        $selected_manager_term_id = 0;
        if (is_array($date_from)) {
            $date_from = reset($date_from);
        }
        if (is_array($date_to)) {
            $date_to = reset($date_to);
        }
        $date_from = is_string($date_from) ? sanitize_text_field($date_from) : '';
        $date_to = is_string($date_to) ? sanitize_text_field($date_to) : '';

        // Determine date range
        if (!empty($date_from) && !empty($date_to)) {
            $start_date = $date_from;
            $end_date = $date_to;
            $start_ts = strtotime($start_date);
            $end_ts = strtotime($end_date);
            if ($start_ts === false || $end_ts === false || $end_ts < $start_ts) {
                $days = 30;
                $start_ts = strtotime('-29 days');
                $start_date = gmdate('Y-m-d', $start_ts);
                $end_date = gmdate('Y-m-d');
            } else {
                $days = (int) floor(($end_ts - $start_ts) / DAY_IN_SECONDS) + 1;
            }
        } else {
            $days = 30;
            $start_ts = strtotime('-29 days');
            $start_date = gmdate('Y-m-d', $start_ts);
            $end_date = gmdate('Y-m-d');
        }

        $labels = [];
        $date_keys = [];
        $counts_by_date = [];

        $build_empty_result = function () use ($days, $start_ts) {
            $labels = [];
            $counts = [];

            for ($i = 0; $i < $days; $i++) {
                $date_ts = $start_ts + ($i * DAY_IN_SECONDS);
                $labels[] = gmdate('M d', $date_ts);
                $counts[] = 0;
            }

            return [
                'labels' => $labels,
                'counts' => $counts,
            ];
        };

        if (is_array($manager_location_term_ids)) {
            if (empty($manager_location_term_ids) || $normalized_location_filter === 'default') {
                return $build_empty_result();
            }

            if ($normalized_location_filter !== 'all') {
                $selected_term = get_term_by('slug', $normalized_location_filter, 'mulopimfwc_store_location');
                if (!$selected_term || is_wp_error($selected_term)) {
                    return $build_empty_result();
                }

                $selected_manager_term_id = (int) $selected_term->term_id;
                if (!in_array($selected_manager_term_id, $manager_location_term_ids, true)) {
                    return $build_empty_result();
                }
            }
        }

        for ($i = 0; $i < $days; $i++) {
            $date_ts = $start_ts + ($i * DAY_IN_SECONDS);
            $date_key = gmdate('Y-m-d', $date_ts);
            $labels[] = gmdate('M d', $date_ts);
            $date_keys[] = $date_key;
            $counts_by_date[$date_key] = 0;
        }

        $cache_version = function_exists('mulopimfwc_get_dashboard_cache_version')
            ? mulopimfwc_get_dashboard_cache_version()
            : 1;
        $cache_key = 'mulopimfwc_dashboard_recent_products_v' . $cache_version . '_' . $this->get_live_dashboard_cache_scope_hash() . '_' . md5(wp_json_encode([
            'date_from' => $start_date,
            'date_to' => $end_date,
            'location_filter' => $normalized_location_filter,
            'manager_terms' => is_array($manager_location_term_ids) ? $manager_location_term_ids : null,
            'selected_manager_term_id' => $selected_manager_term_id,
        ]));
        $cached_data = get_transient($cache_key);
        if (is_array($cached_data) && isset($cached_data['labels'], $cached_data['counts'])) {
            return $cached_data;
        }

        $range_start = gmdate('Y-m-d 00:00:00', $start_ts);
        $range_end = gmdate('Y-m-d 00:00:00', strtotime($end_date . ' +1 day'));

        if (is_array($manager_location_term_ids)) {
            if ($selected_manager_term_id > 0) {
                $query = $wpdb->prepare(
                    "
                    SELECT DATE(p.post_date) AS date_key, COUNT(DISTINCT p.ID) AS total
                    FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                    INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    WHERE p.post_type = 'product'
                    AND p.post_status = 'publish'
                    AND p.post_date >= %s
                    AND p.post_date < %s
                    AND tt.taxonomy = 'mulopimfwc_store_location'
                    AND tt.term_id = %d
                    GROUP BY DATE(p.post_date)
                    ",
                    $range_start,
                    $range_end,
                    $selected_manager_term_id
                );
            } else {
                $term_placeholders = implode(',', array_fill(0, count($manager_location_term_ids), '%d'));
                $query = $wpdb->prepare(
                    "
                    SELECT DATE(p.post_date) AS date_key, COUNT(DISTINCT p.ID) AS total
                    FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                    INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    WHERE p.post_type = 'product'
                    AND p.post_status = 'publish'
                    AND p.post_date >= %s
                    AND p.post_date < %s
                    AND tt.taxonomy = 'mulopimfwc_store_location'
                    AND tt.term_id IN ({$term_placeholders})
                    GROUP BY DATE(p.post_date)
                    ",
                    ...array_merge([$range_start, $range_end], $manager_location_term_ids)
                );
            }
        } elseif ($normalized_location_filter === 'all') {
            $query = $wpdb->prepare(
                "
                SELECT DATE(post_date) AS date_key, COUNT(*) AS total
                FROM {$wpdb->posts}
                WHERE post_type = 'product'
                AND post_status = 'publish'
                AND post_date >= %s
                AND post_date < %s
                GROUP BY DATE(post_date)
                ",
                $range_start,
                $range_end
            );
        } else {
            $term = get_term_by('slug', $normalized_location_filter, 'mulopimfwc_store_location');
            if ($term && !is_wp_error($term)) {
                $query = $wpdb->prepare(
                    "
                    SELECT DATE(p.post_date) AS date_key, COUNT(DISTINCT p.ID) AS total
                    FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                    INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    WHERE p.post_type = 'product'
                    AND p.post_status = 'publish'
                    AND p.post_date >= %s
                    AND p.post_date < %s
                    AND tt.taxonomy = 'mulopimfwc_store_location'
                    AND tt.term_id = %d
                    GROUP BY DATE(p.post_date)
                    ",
                    $range_start,
                    $range_end,
                    (int) $term->term_id
                );
            } else {
                $query = $wpdb->prepare(
                    "
                    SELECT DATE(post_date) AS date_key, COUNT(*) AS total
                    FROM {$wpdb->posts}
                    WHERE post_type = 'product'
                    AND post_status = 'publish'
                    AND post_date >= %s
                    AND post_date < %s
                    GROUP BY DATE(post_date)
                    ",
                    $range_start,
                    $range_end
                );
            }
        }

        $rows = $wpdb->get_results($query);
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $date_key = isset($row->date_key) ? (string) $row->date_key : '';
                if ($date_key !== '' && array_key_exists($date_key, $counts_by_date)) {
                    $counts_by_date[$date_key] = isset($row->total) ? (int) $row->total : 0;
                }
            }
        }

        $result = [
            'labels' => $labels,
            'counts' => array_map(static function ($date_key) use ($counts_by_date) {
                return (int) ($counts_by_date[$date_key] ?? 0);
            }, $date_keys)
        ];
        set_transient($cache_key, $result, 10 * MINUTE_IN_SECONDS);

        return $result;
    }

    /**
     * Get daily investment data for the requested dashboard scope.
     *
     * Investment is calculated from the purchase cost of net stock-affecting
     * order quantities, plus the current on-hand stock cost on the current day.
     */
    private function get_investment_data($date_from = '', $date_to = '', $location_filter = 'all')
    {
        if (is_array($date_from)) {
            $date_from = reset($date_from);
        }
        if (is_array($date_to)) {
            $date_to = reset($date_to);
        }

        $date_from = is_string($date_from) ? sanitize_text_field($date_from) : '';
        $date_to = is_string($date_to) ? sanitize_text_field($date_to) : '';

        if ($date_from !== '' && $date_to !== '') {
            $start_date = $date_from;
            $end_date = $date_to;
            $start_ts = strtotime($start_date);
            $end_ts = strtotime($end_date);
            if ($start_ts === false || $end_ts === false || $end_ts < $start_ts) {
                $start_ts = strtotime('-29 days');
                $start_date = gmdate('Y-m-d', $start_ts);
                $end_date = gmdate('Y-m-d');
                $days = 30;
            } else {
                $days = (int) floor(($end_ts - $start_ts) / DAY_IN_SECONDS) + 1;
            }
        } else {
            $start_ts = strtotime('-29 days');
            $start_date = gmdate('Y-m-d', $start_ts);
            $end_date = gmdate('Y-m-d');
            $days = 30;
        }

        $labels = [];
        $totals = [];
        $date_keys = [];

        for ($i = 0; $i < $days; $i++) {
            $date_ts = $start_ts + ($i * DAY_IN_SECONDS);
            $labels[] = gmdate('M d', $date_ts);
            $totals[] = 0.0;
            $date_keys[] = gmdate('Y-m-d', $date_ts);
        }

        $scope = $this->build_investment_scope((string) $location_filter);
        $cache_version = function_exists('mulopimfwc_get_dashboard_cache_version')
            ? mulopimfwc_get_dashboard_cache_version()
            : 1;
        $cache_scope = $this->get_investment_scope_cache_fragment($scope);
        $cache_key = 'mulopimfwc_dashboard_range_investment_' . $this->get_investment_cache_namespace() . '_v' . $cache_version . '_' . md5(wp_json_encode([
            'scope' => $cache_scope,
            'date_from' => $start_date,
            'date_to' => $end_date,
            'days' => $days,
        ]));

        $cached_data = get_transient($cache_key);
        if (is_array($cached_data) && isset($cached_data['labels'], $cached_data['totals'])) {
            return $cached_data;
        }

        if (($scope['mode'] ?? 'empty') === 'empty') {
            $empty_result = [
                'labels' => $labels,
                'totals' => $totals,
            ];
            set_transient($cache_key, $empty_result, 10 * MINUTE_IN_SECONDS);
            return $empty_result;
        }

        $daily_quantities = $this->collect_investment_order_quantities($scope, $start_date, $end_date, 'day');
        foreach ($date_keys as $index => $date_key) {
            $totals[$index] = round(
                $this->calculate_investment_amount_from_product_quantities($daily_quantities[$date_key] ?? []),
                2
            );
        }

        $today_key = gmdate('Y-m-d');
        if ($today_key >= $start_date && $today_key <= $end_date) {
            $today_index = array_search($today_key, $date_keys, true);
            if ($today_index !== false) {
                $totals[$today_index] = round(
                    $totals[$today_index] + $this->calculate_current_stock_investment_total($scope),
                    2
                );
            }
        }

        $result = [
            'labels' => $labels,
            'totals' => $totals,
        ];
        set_transient($cache_key, $result, 10 * MINUTE_IN_SECONDS);

        return $result;
    }

    /**
     * Get total products count efficiently
     */
    private function get_total_products_count($location_filter = 'all', $date_from = '', $date_to = '')
    {
        global $wpdb;

        if (is_array($date_from)) {
            $date_from = reset($date_from);
        }
        if (is_array($date_to)) {
            $date_to = reset($date_to);
        }

        $date_from = is_string($date_from) ? sanitize_text_field($date_from) : '';
        $date_to = is_string($date_to) ? sanitize_text_field($date_to) : '';
        $normalized_location_filter = $this->normalize_location_slug((string) $location_filter);
        if ($normalized_location_filter === '') {
            $normalized_location_filter = 'all';
        }

        $manager_location_term_ids = $this->get_dashboard_manager_assigned_location_term_ids();
        $has_range = ($date_from !== '' && $date_to !== '');
        $where = [
            "p.post_type = 'product'",
        ];
        $params = [];

        if ($has_range) {
            $where[] = "p.post_status = 'publish'";
            $where[] = "DATE(p.post_date) BETWEEN %s AND %s";
            $params[] = $date_from;
            $params[] = $date_to;
        } else {
            $where[] = "p.post_status NOT IN ('trash', 'auto-draft')";
        }

        if (is_array($manager_location_term_ids)) {
            if (empty($manager_location_term_ids) || $normalized_location_filter === 'default') {
                return 0;
            }

            if ($normalized_location_filter !== 'all') {
                $selected_term = get_term_by('slug', $normalized_location_filter, 'mulopimfwc_store_location');
                if (!$selected_term || is_wp_error($selected_term)) {
                    return 0;
                }

                $selected_term_id = (int) $selected_term->term_id;
                if (!in_array($selected_term_id, $manager_location_term_ids, true)) {
                    return 0;
                }

                $where[] = "EXISTS (
                    SELECT 1
                    FROM {$wpdb->term_relationships} tr
                    INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    WHERE tr.object_id = p.ID
                    AND tt.taxonomy = 'mulopimfwc_store_location'
                    AND tt.term_id = %d
                )";
                $params[] = $selected_term_id;
            } else {
                $term_placeholders = implode(',', array_fill(0, count($manager_location_term_ids), '%d'));
                $where[] = "EXISTS (
                    SELECT 1
                    FROM {$wpdb->term_relationships} tr
                    INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    WHERE tr.object_id = p.ID
                    AND tt.taxonomy = 'mulopimfwc_store_location'
                    AND tt.term_id IN ({$term_placeholders})
                )";
                $params = array_merge($params, array_map('intval', $manager_location_term_ids));
            }
        } else {
            if ($normalized_location_filter === 'default') {
                $where[] = "NOT EXISTS (
                    SELECT 1
                    FROM {$wpdb->term_relationships} tr
                    INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    WHERE tr.object_id = p.ID
                    AND tt.taxonomy = 'mulopimfwc_store_location'
                )";
            } elseif ($normalized_location_filter !== 'all') {
                $selected_term = get_term_by('slug', $normalized_location_filter, 'mulopimfwc_store_location');
                if (!$selected_term || is_wp_error($selected_term)) {
                    return 0;
                }

                $where[] = "EXISTS (
                    SELECT 1
                    FROM {$wpdb->term_relationships} tr
                    INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    WHERE tr.object_id = p.ID
                    AND tt.taxonomy = 'mulopimfwc_store_location'
                    AND tt.term_id = %d
                )";
                $params[] = (int) $selected_term->term_id;
            }
        }

        $query = "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p WHERE " . implode(' AND ', $where);
        if (empty($params)) {
            return (int) $wpdb->get_var($query);
        }

        return (int) $wpdb->get_var($wpdb->prepare($query, ...$params));
    }

    /**
     * Resolve the label/value shown in the locations summary card.
     */
    private function get_location_card_summary($location_filter = 'all'): array
    {
        global $mulopimfwc_locations;

        if (empty($mulopimfwc_locations) || is_wp_error($mulopimfwc_locations)) {
            $mulopimfwc_locations = [];
        }

        $plural_label = __('Locations', 'multi-location-product-and-inventory-management-pro');
        $singular_label = __('Location', 'multi-location-product-and-inventory-management-pro');
        $normalized_location_filter = $this->normalize_location_slug((string) $location_filter);
        if ($normalized_location_filter === '') {
            $normalized_location_filter = 'all';
        }

        $manager_location_slugs = $this->get_dashboard_manager_assigned_location_slugs();
        $accessible_location_count = is_array($manager_location_slugs)
            ? count($manager_location_slugs)
            : count($mulopimfwc_locations);

        if ($normalized_location_filter === 'default') {
            return [
                'mode' => 'selected',
                'label' => $singular_label,
                'value' => __('Default', 'multi-location-product-and-inventory-management-pro'),
                'count' => 1,
            ];
        }

        if ($normalized_location_filter !== 'all') {
            if (is_array($manager_location_slugs) && !in_array($normalized_location_filter, $manager_location_slugs, true)) {
                return [
                    'mode' => 'count',
                    'label' => $plural_label,
                    'value' => 0,
                    'count' => 0,
                ];
            }

            $selected_term = get_term_by('slug', $normalized_location_filter, 'mulopimfwc_store_location');
            if ($selected_term && !is_wp_error($selected_term)) {
                return [
                    'mode' => 'selected',
                    'label' => $singular_label,
                    'value' => (string) $selected_term->name,
                    'count' => 1,
                ];
            }

            return [
                'mode' => 'count',
                'label' => $plural_label,
                'value' => 0,
                'count' => 0,
            ];
        }

        return [
            'mode' => 'count',
            'label' => $plural_label,
            'value' => $accessible_location_count,
            'count' => $accessible_location_count,
        ];
    }

    /**
     * Fetch recent sales data for a location (or default bucket) since a timestamp.
     */
    private function get_location_recent_sales_info(string $location_slug, int $since_timestamp): array
    {
        $grouped_sales = $this->get_recent_sales_info_by_location([$location_slug], $since_timestamp);
        $normalized_location_slug = $this->normalize_location_slug($location_slug);
        if ($normalized_location_slug === '') {
            $normalized_location_slug = 'default';
        }

        return $grouped_sales[$normalized_location_slug] ?? [
            'last_sale_dates' => [],
            'sold_quantities' => [],
        ];
    }

    /**
     * Fetch recent sales data grouped by location in a single order pass.
     */
    private function get_recent_sales_info_by_location(array $location_slugs, int $since_timestamp): array
    {
        $normalized_slugs = array_values(array_unique(array_filter(array_map(function ($slug) {
            $normalized = $this->normalize_location_slug($slug);
            return $normalized === '' ? 'default' : $normalized;
        }, $location_slugs))));

        if (empty($normalized_slugs)) {
            return [];
        }

        $results = [];
        foreach ($normalized_slugs as $slug) {
            $results[$slug] = [
                'last_sale_dates' => [],
                'sold_quantities' => [],
            ];
        }

        $since_date = gmdate('Y-m-d', $since_timestamp);
        $revenue_statuses = function_exists('mulopimfwc_get_revenue_order_statuses')
            ? mulopimfwc_get_revenue_order_statuses()
            : ['processing', 'completed'];

        $batch_size = 250;
        $page = 1;
        $max_pages = 1;

        do {
            $orders_query = wc_get_orders([
                'paginate' => true,
                'page' => $page,
                'limit' => $batch_size,
                'status' => $revenue_statuses,
                'date_created' => '>=' . $since_date,
            ]);

            if (
                !is_object($orders_query)
                || empty($orders_query->orders)
                || !is_array($orders_query->orders)
            ) {
                break;
            }

            $max_pages = max(1, (int) ($orders_query->max_num_pages ?? 1));

            foreach ($orders_query->orders as $order) {
                if (!is_object($order) || !method_exists($order, 'get_meta')) {
                    continue;
                }

                if ($order->get_meta('_mulopimfwc_split_parent') === 'yes') {
                    continue;
                }

                $order_location = $this->normalize_location_slug((string) $order->get_meta('_store_location'));
                if ($order_location === '') {
                    $order_location = 'default';
                }

                if (!isset($results[$order_location])) {
                    continue;
                }

                $order_date = $order->get_date_created();
                $timestamp = $order_date ? $order_date->getTimestamp() : time();

                foreach ($order->get_items() as $item) {
                    $product_id = $item->get_variation_id() ?: $item->get_product_id();

                    if (empty($product_id)) {
                        continue;
                    }

                    $quantity = (float) $item->get_quantity();
                    if ($quantity <= 0) {
                        continue;
                    }

                    $results[$order_location]['sold_quantities'][$product_id] = ($results[$order_location]['sold_quantities'][$product_id] ?? 0) + $quantity;
                    if (
                        !isset($results[$order_location]['last_sale_dates'][$product_id])
                        || $timestamp > $results[$order_location]['last_sale_dates'][$product_id]
                    ) {
                        $results[$order_location]['last_sale_dates'][$product_id] = $timestamp;
                    }
                }
            }

            $page++;
        } while ($page <= $max_pages && $page <= 200);

        return $results;
    }

    /**
     * Load inventory records tied to a location stock meta.
     */
    private function get_location_inventory_records(?int $location_id = null, int $limit = 0, int $offset = 0): array
    {
        global $wpdb;

        $stock_meta_key = $location_id ? '_location_stock_' . $location_id : '_stock';
        $prepare_params = [];

        $location_sale_key = $location_id ? '_location_sale_price_' . $location_id : '';
        $location_regular_key = $location_id ? '_location_regular_price_' . $location_id : '';

        // FIXED: Add limit and offset for pagination to prevent memory exhaustion (Issue #38)
        $limit_clause = '';
        if ($limit > 0) {
            $limit_clause = ' LIMIT %d OFFSET %d';
        }

        $query = "
            SELECT pm_stock.post_id AS product_id,
                   pm_stock.meta_value AS stock,
                   pm_purchase.meta_value AS purchase_price,
                   " . ($location_id ? "pm_location_sale.meta_value AS location_sale_price," : "NULL AS location_sale_price,") . "
                   " . ($location_id ? "pm_location_regular.meta_value AS location_regular_price," : "NULL AS location_regular_price,") . "
                   pm_sale.meta_value AS sale_price,
                   pm_regular.meta_value AS regular_price,
                   pm_price.meta_value AS price
            FROM {$wpdb->postmeta} pm_stock
            INNER JOIN {$wpdb->posts} p ON p.ID = pm_stock.post_id
                AND p.post_type IN ('product', 'product_variation')
                AND p.post_status IN ('publish', 'private')
            LEFT JOIN {$wpdb->postmeta} pm_purchase ON pm_purchase.post_id = pm_stock.post_id AND pm_purchase.meta_key = '_purchase_price'
            LEFT JOIN {$wpdb->postmeta} pm_sale ON pm_sale.post_id = pm_stock.post_id AND pm_sale.meta_key = '_sale_price'
            LEFT JOIN {$wpdb->postmeta} pm_regular ON pm_regular.post_id = pm_stock.post_id AND pm_regular.meta_key = '_regular_price'
            LEFT JOIN {$wpdb->postmeta} pm_price ON pm_price.post_id = pm_stock.post_id AND pm_price.meta_key = '_price'
            " . ($location_id ? "LEFT JOIN {$wpdb->postmeta} pm_location_sale ON pm_location_sale.post_id = pm_stock.post_id AND pm_location_sale.meta_key = %s" : "") . "
            " . ($location_id ? "LEFT JOIN {$wpdb->postmeta} pm_location_regular ON pm_location_regular.post_id = pm_stock.post_id AND pm_location_regular.meta_key = %s" : "") . "
            WHERE pm_stock.meta_key = %s
            AND pm_stock.meta_value != ''
            AND pm_stock.meta_value IS NOT NULL
            AND (
                CAST(pm_stock.meta_value AS DECIMAL(20,6)) > 0
                OR CAST(REPLACE(pm_stock.meta_value, ',', '.') AS DECIMAL(20,6)) > 0
            )
            " . $limit_clause . "
        ";

        if ($location_id) {
            $prepare_params[] = $location_sale_key;
            $prepare_params[] = $location_regular_key;
        }

        $prepare_params[] = $stock_meta_key;

        if ($limit > 0) {
            $prepare_params[] = $limit;
            $prepare_params[] = $offset;
        }

        // Prepare query with all parameters
        if (!empty($prepare_params)) {
            $results = $wpdb->get_results($wpdb->prepare($query, ...$prepare_params));
        } else {
            $results = $wpdb->get_results($query);
        }

        // FIXED: Enhanced error handling with user feedback
        if ($wpdb->last_error) {
            error_log('Mulopimfwc: Error fetching inventory records - ' . $wpdb->last_error);

            // Log detailed error information for debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Mulopimfwc: Query was - ' . $query);
                error_log('Mulopimfwc: Prepare params - ' . print_r($prepare_params, true));
            }

            // Return error information instead of silently failing
            // This allows calling code to handle errors appropriately
            return array(
                'error' => true,
                'message' => __('Error fetching inventory records. Please try again or contact support.', 'multi-location-product-and-inventory-management-pro'),
                'data' => array()
            );
        }

        return $results ? $results : [];
    }

    /**
     * Normalize any price-like value to a float.
     */
    private function normalize_price_value($value): float
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
     * Normalize stock/quantity-like values to a float.
     */
    private function normalize_quantity_value($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (function_exists('wc_format_decimal')) {
            $formatted = wc_format_decimal($value, 6);
            if (is_numeric($formatted)) {
                return (float) $formatted;
            }
        }

        $sanitized = preg_replace('/[^0-9,.\-]/', '', (string) $value);
        if ($sanitized === null || $sanitized === '') {
            return 0.0;
        }

        // Fallback for locale values like "0,5".
        $normalized = str_replace(',', '.', $sanitized);

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    /**
     * Determine the best available sale price for a record.
     */
    private function resolve_sale_price_from_record($record): float
    {
        $candidates = [
            $record->location_sale_price ?? '',
            $record->sale_price ?? '',
            $record->location_regular_price ?? '',
            $record->regular_price ?? '',
            $record->price ?? ''
        ];

        foreach ($candidates as $candidate) {
            $value = $this->normalize_price_value($candidate);
            if ($value > 0) {
                return $value;
            }
        }

        return 0.0;
    }

    /**
     * Render profitability panel markup.
     */
    private function render_profitability_panel_html(array $profitability_by_location, int $dead_stock_days = 90): string
    {
        ob_start();
    ?>
        <h2><?php esc_html_e('Profitability & Aging by Location', 'multi-location-product-and-inventory-management-pro'); ?></h2>
        <p class="lwp-profitability-description">
            <?php
            printf(
                /* translators: %s: number of days */
                esc_html__('Dead stock in this table represents inventory that has not sold in the last %s days. Shrinkage reflects any sold quantity that could not be matched to current stock records.', 'multi-location-product-and-inventory-management-pro'),
                esc_html($dead_stock_days)
            );
            ?>
        </p>
        <?php if (!empty($profitability_by_location)) : ?>
            <div class="lwp-table-responsive">
                <table class="lwp-profitability-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Location', 'multi-location-product-and-inventory-management-pro'); ?></th>
                            <th><?php esc_html_e('Inventory Value', 'multi-location-product-and-inventory-management-pro'); ?></th>
                            <th><?php esc_html_e('Margin Value', 'multi-location-product-and-inventory-management-pro'); ?></th>
                            <th><?php esc_html_e('Margin %', 'multi-location-product-and-inventory-management-pro'); ?></th>
                            <th><?php esc_html_e('Dead Stock Value', 'multi-location-product-and-inventory-management-pro'); ?></th>
                            <th><?php esc_html_e('Dead Stock Units', 'multi-location-product-and-inventory-management-pro'); ?></th>
                            <th><?php esc_html_e('Avg. Age (days)', 'multi-location-product-and-inventory-management-pro'); ?></th>
                            <th><?php esc_html_e('Shrinkage %', 'multi-location-product-and-inventory-management-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($profitability_by_location as $summary) : ?>
                            <tr>
                                <td><?php echo esc_html($summary['location_name']); ?></td>
                                <td><?php echo wp_kses_post($this->format_dashboard_reporting_price($summary['inventory_value'])); ?></td>
                                <td><?php echo wp_kses_post($this->format_dashboard_reporting_price($summary['margin_value'])); ?></td>
                                <td><?php echo esc_html(number_format((float) $summary['margin_rate'], 1)); ?>%</td>
                                <td><?php echo wp_kses_post($this->format_dashboard_reporting_price($summary['dead_stock_value'])); ?></td>
                                <td><?php echo esc_html(number_format((float) $summary['dead_stock_units'], 0)); ?></td>
                                <td><?php echo esc_html(number_format((float) $summary['average_age_days'], 1)); ?></td>
                                <td><?php echo esc_html(number_format((float) $summary['shrinkage_rate'], 1)); ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else : ?>
            <p><?php esc_html_e('No profitability data available yet.', 'multi-location-product-and-inventory-management-pro'); ?></p>
        <?php endif; ?>
<?php

        return trim((string) ob_get_clean());
    }

    /**
     * Resolve the location list used by the profitability panel.
     */
    private function get_profitability_location_entries(string $location_filter = 'all'): array
    {
        $normalized_location_filter = $location_filter === '__none__'
            ? '__none__'
            : $this->normalize_location_slug($location_filter);

        if ($normalized_location_filter === '__none__') {
            return [];
        }

        if ($normalized_location_filter === '') {
            $normalized_location_filter = 'all';
        }

        $locations = [];
        $scoped_locations = $this->get_dashboard_scoped_locations();
        if (!empty($scoped_locations) && !is_wp_error($scoped_locations)) {
            foreach ($scoped_locations as $location) {
                $locations[] = [
                    'name' => (string) $location->name,
                    'slug' => $this->normalize_location_slug($location->slug),
                    'term_id' => isset($location->term_id) ? (int) $location->term_id : 0,
                ];
            }
        }

        $is_location_manager = is_array($this->get_dashboard_manager_assigned_location_slugs());
        if (!$is_location_manager) {
            $locations[] = [
                'name' => __('Default', 'multi-location-product-and-inventory-management-pro'),
                'slug' => 'default',
                'term_id' => null,
            ];
        }

        if ($normalized_location_filter === 'all') {
            return $locations;
        }

        return array_values(array_filter($locations, function ($location) use ($normalized_location_filter) {
            return isset($location['slug']) && $location['slug'] === $normalized_location_filter;
        }));
    }

    /**
     * Build profitability, dead stock, shrinkage, and aging summaries for every location.
     */
    private function get_location_profitability_data(int $dead_stock_days = 90, string $location_filter = 'all'): array
    {
        $dead_stock_days = max(1, $dead_stock_days);
        $normalized_location_filter = $location_filter === '__none__'
            ? '__none__'
            : $this->normalize_location_slug($location_filter);

        if ($normalized_location_filter === '') {
            $normalized_location_filter = 'all';
        }

        $cache_version = function_exists('mulopimfwc_get_dashboard_cache_version')
            ? mulopimfwc_get_dashboard_cache_version()
            : 1;
        $cache_scope = [
            'scope_hash' => $this->get_live_dashboard_cache_scope_hash(),
            'location_filter' => $normalized_location_filter,
            'dead_stock_days' => $dead_stock_days,
        ];
        $cache_scope_json = wp_json_encode($cache_scope);
        if (!is_string($cache_scope_json) || $cache_scope_json === '') {
            $cache_scope_json = serialize($cache_scope);
        }
        $cache_key = 'mulopimfwc_profitability_v' . $cache_version . '_' . md5($cache_scope_json);
        $cached_results = get_transient($cache_key);
        if (is_array($cached_results)) {
            return $cached_results;
        }

        $locations = $this->get_profitability_location_entries($normalized_location_filter);
        if (empty($locations)) {
            set_transient($cache_key, [], 5 * MINUTE_IN_SECONDS);
            return [];
        }

        $results = [];
        $now = time();
        $threshold_timestamp = $now - ($dead_stock_days * DAY_IN_SECONDS);
        $sales_info_by_location = $this->get_recent_sales_info_by_location(array_column($locations, 'slug'), $threshold_timestamp);

        foreach ($locations as $entry) {
            // FIXED: Process records in batches to prevent memory exhaustion (Issue #38)
            $all_records = [];
            $batch_size = 1000;
            $offset = 0;

            do {
                $records = $this->get_location_inventory_records($entry['term_id'], $batch_size, $offset);
                if (!empty($records) && !isset($records['error'])) {
                    $all_records = array_merge($all_records, $records);
                } elseif (isset($records['error'])) {
                    // Error occurred, break and log
                    error_log('Mulopimfwc: Error fetching inventory records for location ' . $entry['term_id']);
                    break;
                }
                $offset += $batch_size;

                // Safety check to prevent infinite loops
                if ($offset > 50000) {
                    break;
                }
            } while (count($records) === $batch_size);

            $records = $all_records;
            $sales_info = $sales_info_by_location[$entry['slug']] ?? [
                'last_sale_dates' => [],
                'sold_quantities' => [],
            ];
            $last_sale_dates = $sales_info['last_sale_dates'];
            $sold_quantities = $sales_info['sold_quantities'];

            $summary = [
                'location_name' => $entry['name'],
                'location_slug' => $entry['slug'],
                'inventory_units' => 0,
                'inventory_value' => 0.0,
                'potential_revenue' => 0.0,
                'margin_value' => 0.0,
                'margin_rate' => 0.0,
                'dead_stock_units' => 0.0,
                'dead_stock_value' => 0.0,
                'shrinkage_value' => 0.0,
                'shrinkage_rate' => 0.0,
                'average_age_days' => 0.0,
                'oldest_stock_days' => 0.0,
                'dead_stock_days' => $dead_stock_days
            ];

            $age_samples = [];

            foreach ($records as $record) {
                $stock = $this->normalize_quantity_value($record->stock ?? '');
                if ($stock <= 0) {
                    continue;
                }

                $purchase_price = $this->normalize_price_value($record->purchase_price);
                $sell_price = $this->resolve_sale_price_from_record($record);

                $summary['inventory_units'] += $stock;
                $summary['inventory_value'] += $stock * $purchase_price;
                $summary['potential_revenue'] += $stock * $sell_price;
                $summary['margin_value'] += $stock * ($sell_price - $purchase_price);

                $last_sale_ts = $last_sale_dates[$record->product_id] ?? 0;

                if ($last_sale_ts > 0) {
                    $age_days = max(0, ($now - $last_sale_ts) / DAY_IN_SECONDS);
                    $age_samples[] = $age_days;
                    $summary['oldest_stock_days'] = max($summary['oldest_stock_days'], $age_days);
                } else {
                    $summary['oldest_stock_days'] = max($summary['oldest_stock_days'], $dead_stock_days);
                }

                if ($last_sale_ts === 0 || $last_sale_ts < $threshold_timestamp) {
                    $summary['dead_stock_units'] += $stock;
                    $summary['dead_stock_value'] += $stock * $purchase_price;
                }

                $sold_qty = $sold_quantities[$record->product_id] ?? 0;
                $shrinkage_units = max(0, $sold_qty - $stock);
                if ($purchase_price > 0 && $shrinkage_units > 0) {
                    $summary['shrinkage_value'] += $shrinkage_units * $purchase_price;
                }
            }

            if (!empty($age_samples)) {
                $summary['average_age_days'] = array_sum($age_samples) / count($age_samples);
            }

            if ($summary['inventory_value'] > 0) {
                $summary['margin_rate'] = ($summary['margin_value'] / $summary['inventory_value']) * 100;
                $summary['shrinkage_rate'] = ($summary['shrinkage_value'] / $summary['inventory_value']) * 100;
            }

            $summary['inventory_value'] = round($summary['inventory_value'], 2);
            $summary['potential_revenue'] = round($summary['potential_revenue'], 2);
            $summary['margin_value'] = round($summary['margin_value'], 2);
            $summary['margin_rate'] = round($summary['margin_rate'], 2);
            $summary['dead_stock_value'] = round($summary['dead_stock_value'], 2);
            $summary['dead_stock_units'] = round($summary['dead_stock_units'], 2);
            $summary['average_age_days'] = round($summary['average_age_days'], 2);
            $summary['oldest_stock_days'] = round($summary['oldest_stock_days'], 2);
            $summary['shrinkage_value'] = round($summary['shrinkage_value'], 2);
            $summary['shrinkage_rate'] = round($summary['shrinkage_rate'], 2);

            $results[] = $summary;
        }

        set_transient($cache_key, $results, 5 * MINUTE_IN_SECONDS);

        return $results;
    }

    /**
     * Build a normalized scope descriptor for investment calculations.
     *
     * Scope modes:
     * - all: all products/orders
     * - default: products/orders without a location bucket
     * - terms: one or more specific store locations
     * - empty: no accessible scope
     */
    private function build_investment_scope(string $location_filter = 'all'): array
    {
        $normalized_location_filter = $this->normalize_location_slug($location_filter);
        $manager_location_term_ids = $this->get_dashboard_manager_assigned_location_term_ids();
        $manager_location_slugs = $this->get_dashboard_manager_assigned_location_slugs();
        $empty_scope = [
            'mode' => 'empty',
            'term_ids' => [],
            'term_slugs' => [],
        ];

        if (is_array($manager_location_term_ids)) {
            if (empty($manager_location_term_ids) || $normalized_location_filter === 'default') {
                return $empty_scope;
            }

            if ($normalized_location_filter !== '' && $normalized_location_filter !== 'all') {
                $selected_term = get_term_by('slug', $normalized_location_filter, 'mulopimfwc_store_location');
                if (!$selected_term || is_wp_error($selected_term)) {
                    return $empty_scope;
                }

                $selected_term_id = (int) $selected_term->term_id;
                if (!in_array($selected_term_id, $manager_location_term_ids, true)) {
                    return $empty_scope;
                }

                return [
                    'mode' => 'terms',
                    'term_ids' => [$selected_term_id],
                    'term_slugs' => [$this->normalize_location_slug((string) $selected_term->slug)],
                ];
            }

            $term_ids = array_values(array_unique(array_map('intval', $manager_location_term_ids)));
            sort($term_ids, SORT_NUMERIC);

            $term_slugs = is_array($manager_location_slugs)
                ? array_values(array_unique(array_map([$this, 'normalize_location_slug'], $manager_location_slugs)))
                : [];
            sort($term_slugs, SORT_STRING);

            return [
                'mode' => 'terms',
                'term_ids' => $term_ids,
                'term_slugs' => $term_slugs,
            ];
        }

        if ($normalized_location_filter === 'default') {
            return [
                'mode' => 'default',
                'term_ids' => [],
                'term_slugs' => [],
            ];
        }

        if ($normalized_location_filter !== '' && $normalized_location_filter !== 'all') {
            $selected_term = get_term_by('slug', $normalized_location_filter, 'mulopimfwc_store_location');
            if (!$selected_term || is_wp_error($selected_term)) {
                return $empty_scope;
            }

            return [
                'mode' => 'terms',
                'term_ids' => [(int) $selected_term->term_id],
                'term_slugs' => [$this->normalize_location_slug((string) $selected_term->slug)],
            ];
        }

        return [
            'mode' => 'all',
            'term_ids' => [],
            'term_slugs' => [],
        ];
    }

    /**
     * Build a stable cache fragment for investment scope-aware caches.
     */
    private function get_investment_scope_cache_fragment(array $scope): string
    {
        $term_ids = isset($scope['term_ids']) && is_array($scope['term_ids'])
            ? array_values(array_unique(array_map('intval', $scope['term_ids'])))
            : [];
        $term_slugs = isset($scope['term_slugs']) && is_array($scope['term_slugs'])
            ? array_values(array_unique(array_map([$this, 'normalize_location_slug'], $scope['term_slugs'])))
            : [];

        sort($term_ids, SORT_NUMERIC);
        sort($term_slugs, SORT_STRING);

        $payload = [
            'mode' => isset($scope['mode']) ? (string) $scope['mode'] : 'all',
            'term_ids' => $term_ids,
            'term_slugs' => $term_slugs,
        ];

        $json = wp_json_encode($payload);
        if (!is_string($json) || $json === '') {
            $json = serialize($payload);
        }

        return md5($json);
    }

    /**
     * Namespace investment-related transients so behavior fixes do not reuse
     * payloads built by older query rules.
     */
    private function get_investment_cache_namespace(): string
    {
        return 'resolved_location_v1';
    }

    /**
     * Resolve order statuses that represent stock-reduced orders.
     */
    private function get_investment_stock_affecting_statuses(): array
    {
        $statuses = apply_filters('mulopimfwc_split_stock_reduction_statuses', ['processing', 'completed', 'on-hold']);
        if (!is_array($statuses)) {
            $statuses = ['processing', 'completed', 'on-hold'];
        }

        $statuses = array_map(function ($status) {
            $status = sanitize_key((string) $status);
            if (strpos($status, 'wc-') === 0) {
                $status = substr($status, 3);
            }
            return $status;
        }, $statuses);

        return array_values(array_unique(array_filter($statuses, 'strlen')));
    }

    /**
     * Build an in-request snapshot of product inventory meta needed for investment calculations.
     */
    private function get_investment_product_snapshot_map(): array
    {
        static $snapshot = null;

        if (is_array($snapshot)) {
            return $snapshot;
        }

        global $wpdb;

        $snapshot = [];
        $product_rows = $wpdb->get_results("
            SELECT ID, post_parent, post_type
            FROM {$wpdb->posts}
            WHERE post_type IN ('product', 'product_variation')
            AND post_status IN ('publish', 'private')
            ORDER BY ID ASC
        ");

        if (!is_array($product_rows) || empty($product_rows)) {
            return $snapshot;
        }

        $product_ids = [];
        foreach ($product_rows as $row) {
            $product_id = isset($row->ID) ? (int) $row->ID : 0;
            if ($product_id <= 0) {
                continue;
            }

            $parent_id = isset($row->post_parent) ? (int) $row->post_parent : 0;
            $post_type = isset($row->post_type) ? (string) $row->post_type : 'product';
            $reference_id = ($post_type === 'product_variation' && $parent_id > 0) ? $parent_id : $product_id;

            $snapshot[$product_id] = [
                'post_type' => $post_type,
                'parent_id' => $parent_id,
                'reference_id' => $reference_id,
                'purchase_price' => 0.0,
                'default_stock' => 0.0,
                'location_stock' => [],
                'has_location_stock_meta' => false,
            ];
            $product_ids[] = $product_id;
        }

        foreach (array_chunk($product_ids, 500) as $product_ids_chunk) {
            if (empty($product_ids_chunk)) {
                continue;
            }

            $placeholders = implode(',', array_fill(0, count($product_ids_chunk), '%d'));
            $meta_query = "
                SELECT post_id, meta_key, meta_value
                FROM {$wpdb->postmeta}
                WHERE post_id IN ({$placeholders})
                AND (
                    meta_key IN ('_purchase_price', '_stock')
                    OR meta_key LIKE '\\_location\\_stock\\_%' ESCAPE '\\\\'
                )
            ";
            $meta_rows = $wpdb->get_results($wpdb->prepare($meta_query, ...$product_ids_chunk));

            if (!is_array($meta_rows)) {
                continue;
            }

            foreach ($meta_rows as $meta_row) {
                $product_id = isset($meta_row->post_id) ? (int) $meta_row->post_id : 0;
                if ($product_id <= 0 || !isset($snapshot[$product_id])) {
                    continue;
                }

                $meta_key = isset($meta_row->meta_key) ? (string) $meta_row->meta_key : '';
                $meta_value = $meta_row->meta_value ?? null;

                if ($meta_key === '_purchase_price') {
                    $snapshot[$product_id]['purchase_price'] = $this->normalize_price_value($meta_value);
                    continue;
                }

                if ($meta_key === '_stock') {
                    $snapshot[$product_id]['default_stock'] = $this->normalize_quantity_value($meta_value);
                    continue;
                }

                if (strpos($meta_key, '_location_stock_') === 0) {
                    $snapshot[$product_id]['has_location_stock_meta'] = true;
                    $term_id = (int) substr($meta_key, strlen('_location_stock_'));
                    if ($term_id <= 0) {
                        continue;
                    }

                    $snapshot[$product_id]['location_stock'][$term_id] = $this->normalize_quantity_value($meta_value);
                }
            }
        }

        foreach ($snapshot as $product_id => $product_snapshot) {
            if (
                (float) $product_snapshot['purchase_price'] > 0
                || $product_snapshot['post_type'] !== 'product_variation'
                || (int) $product_snapshot['parent_id'] <= 0
            ) {
                continue;
            }

            $parent_id = (int) $product_snapshot['parent_id'];
            if (isset($snapshot[$parent_id])) {
                $snapshot[$product_id]['purchase_price'] = (float) $snapshot[$parent_id]['purchase_price'];
            }
        }

        return $snapshot;
    }

    /**
     * Return product/variation IDs that may contribute to investment.
     */
    private function get_investment_product_ids(): array
    {
        return array_keys($this->get_investment_product_snapshot_map());
    }

    /**
     * Build an in-request map of assigned location term IDs for product references.
     */
    private function get_investment_reference_location_term_map(): array
    {
        static $location_map = null;

        if (is_array($location_map)) {
            return $location_map;
        }

        global $wpdb;

        $location_map = [];
        $reference_ids = array_values(array_unique(array_filter(array_map(static function ($snapshot) {
            return isset($snapshot['reference_id']) ? (int) $snapshot['reference_id'] : 0;
        }, $this->get_investment_product_snapshot_map()))));

        foreach (array_chunk($reference_ids, 500) as $reference_ids_chunk) {
            if (empty($reference_ids_chunk)) {
                continue;
            }

            $placeholders = implode(',', array_fill(0, count($reference_ids_chunk), '%d'));
            $term_query = "
                SELECT tr.object_id, tt.term_id
                FROM {$wpdb->term_relationships} tr
                INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                WHERE tt.taxonomy = 'mulopimfwc_store_location'
                AND tr.object_id IN ({$placeholders})
            ";
            $term_rows = $wpdb->get_results($wpdb->prepare($term_query, ...$reference_ids_chunk));

            if (!is_array($term_rows)) {
                continue;
            }

            foreach ($term_rows as $term_row) {
                $reference_id = isset($term_row->object_id) ? (int) $term_row->object_id : 0;
                $term_id = isset($term_row->term_id) ? (int) $term_row->term_id : 0;
                if ($reference_id <= 0 || $term_id <= 0) {
                    continue;
                }

                if (!isset($location_map[$reference_id])) {
                    $location_map[$reference_id] = [];
                }
                $location_map[$reference_id][$term_id] = $term_id;
            }
        }

        foreach ($location_map as $reference_id => $term_ids) {
            $term_ids = array_values(array_unique(array_map('intval', $term_ids)));
            sort($term_ids, SORT_NUMERIC);
            $location_map[$reference_id] = $term_ids;
        }

        return $location_map;
    }

    /**
     * Get the parent product ID for variations, otherwise the product ID itself.
     */
    private function get_investment_product_reference_id(int $product_id): int
    {
        $snapshot = $this->get_investment_product_snapshot_map();
        if (!isset($snapshot[$product_id])) {
            return $product_id;
        }

        return (int) ($snapshot[$product_id]['reference_id'] ?? $product_id);
    }

    /**
     * Return assigned location term IDs for a product or variation.
     */
    private function get_investment_product_location_term_ids(int $product_id): array
    {
        $reference_id = $this->get_investment_product_reference_id($product_id);
        $location_map = $this->get_investment_reference_location_term_map();

        return $location_map[$reference_id] ?? [];
    }

    /**
     * Resolve purchase price, falling back from variation to parent when needed.
     */
    private function get_investment_product_purchase_price(int $product_id): float
    {
        $snapshot = $this->get_investment_product_snapshot_map();
        if (!isset($snapshot[$product_id])) {
            return 0.0;
        }

        return max(0.0, (float) ($snapshot[$product_id]['purchase_price'] ?? 0.0));
    }

    /**
     * Get WooCommerce default stock quantity for a product or variation.
     */
    private function get_investment_default_stock_quantity(int $product_id): float
    {
        $snapshot = $this->get_investment_product_snapshot_map();
        if (!isset($snapshot[$product_id])) {
            return 0.0;
        }

        return (float) ($snapshot[$product_id]['default_stock'] ?? 0.0);
    }

    /**
     * Summarize location stock meta for a product or variation.
     */
    private function get_investment_location_stock_snapshot(int $product_id, ?array $allowed_term_ids = null): array
    {
        $snapshot = $this->get_investment_product_snapshot_map();
        if (!isset($snapshot[$product_id])) {
            return [
                'sum' => 0.0,
                'has_any' => false,
            ];
        }

        $location_stock = isset($snapshot[$product_id]['location_stock']) && is_array($snapshot[$product_id]['location_stock'])
            ? $snapshot[$product_id]['location_stock']
            : [];
        $allowed_lookup = null;

        if (is_array($allowed_term_ids)) {
            $allowed_term_ids = array_values(array_unique(array_map('intval', $allowed_term_ids)));
            $allowed_lookup = array_fill_keys($allowed_term_ids, true);
        }

        $sum = 0.0;
        $has_any = !empty($snapshot[$product_id]['has_location_stock_meta']);

        foreach ($location_stock as $term_id => $quantity) {
            $term_id = (int) $term_id;
            if (is_array($allowed_lookup) && !isset($allowed_lookup[$term_id])) {
                continue;
            }

            $sum += (float) $quantity;
        }

        return [
            'sum' => $sum,
            'has_any' => $has_any,
        ];
    }

    /**
     * Resolve the current scoped stock quantity for a product or variation.
     */
    private function get_investment_current_stock_quantity(int $product_id, array $scope): float
    {
        $mode = isset($scope['mode']) ? (string) $scope['mode'] : 'all';
        if ($mode === 'empty') {
            return 0.0;
        }

        $location_snapshot = $this->get_investment_location_stock_snapshot($product_id);
        $assigned_term_ids = $this->get_investment_product_location_term_ids($product_id);

        if ($mode === 'terms') {
            $term_ids = isset($scope['term_ids']) && is_array($scope['term_ids']) ? $scope['term_ids'] : [];
            return $this->get_investment_location_stock_snapshot($product_id, $term_ids)['sum'];
        }

        if ($mode === 'default') {
            if ($location_snapshot['has_any'] || !empty($assigned_term_ids)) {
                return 0.0;
            }

            return $this->get_investment_default_stock_quantity($product_id);
        }

        if ($location_snapshot['has_any']) {
            return $location_snapshot['sum'];
        }

        return $this->get_investment_default_stock_quantity($product_id);
    }

    /**
     * Calculate the current stock investment total for a scope.
     */
    private function calculate_current_stock_investment_total(array $scope): float
    {
        static $totals_cache = [];

        $scope_key = $this->get_investment_scope_cache_fragment($scope);
        if (isset($totals_cache[$scope_key])) {
            return $totals_cache[$scope_key];
        }

        $total = 0.0;
        foreach ($this->get_investment_product_ids() as $product_id) {
            $purchase_price = $this->get_investment_product_purchase_price((int) $product_id);
            if ($purchase_price <= 0) {
                continue;
            }

            $quantity = $this->get_investment_current_stock_quantity((int) $product_id, $scope);
            if ($quantity == 0.0) {
                continue;
            }

            $total += $purchase_price * $quantity;
        }

        $totals_cache[$scope_key] = $total;
        return $totals_cache[$scope_key];
    }

    /**
     * Convert product quantity aggregates into an investment amount.
     */
    private function calculate_investment_amount_from_product_quantities(array $product_quantities): float
    {
        $total = 0.0;

        foreach ($product_quantities as $product_id => $quantity) {
            $quantity = (float) $quantity;
            if ($quantity <= 0) {
                continue;
            }

            $purchase_price = $this->get_investment_product_purchase_price((int) $product_id);
            if ($purchase_price <= 0) {
                continue;
            }

            $total += $purchase_price * $quantity;
        }

        return $total;
    }

    /**
     * Resolve the final location slug for an order item.
     */
    private function get_investment_order_item_location_slug(WC_Order_Item_Product $item, string $order_location_slug = ''): string
    {
        $item_location_slug = $this->normalize_location_slug((string) $item->get_meta('_mulopimfwc_location'));
        if ($item_location_slug !== '') {
            return $item_location_slug;
        }

        return $this->normalize_location_slug($order_location_slug);
    }

    /**
     * Check whether an order item location belongs to a requested scope.
     */
    private function investment_order_location_matches_scope(string $location_slug, array $scope): bool
    {
        $mode = isset($scope['mode']) ? (string) $scope['mode'] : 'all';
        $location_slug = $this->normalize_location_slug($location_slug);

        if ($mode === 'all') {
            return true;
        }

        if ($mode === 'default') {
            return $location_slug === '' || $location_slug === 'default';
        }

        if ($mode === 'terms') {
            $term_slugs = isset($scope['term_slugs']) && is_array($scope['term_slugs']) ? $scope['term_slugs'] : [];
            return $location_slug !== '' && in_array($location_slug, $term_slugs, true);
        }

        return false;
    }

    /**
     * Get the net stock-reduced quantity for an order item.
     *
     * WooCommerce keeps `_reduced_stock` up to date for core-managed stock. For
     * location-only stock we fall back to refunded quantity as a best-effort
     * approximation when no core stock markers exist.
     */
    private function get_investment_net_order_item_quantity(WC_Order $order, WC_Order_Item_Product $item): float
    {
        $ordered_qty = max(0.0, (float) $item->get_quantity());
        if ($ordered_qty <= 0) {
            return 0.0;
        }

        $reduced_qty = $item->get_meta('_reduced_stock', true);
        if ($reduced_qty !== '' && $reduced_qty !== null && is_numeric($reduced_qty)) {
            return max(0.0, min($ordered_qty, abs((float) $reduced_qty)));
        }

        $refunded_qty = abs((float) $order->get_qty_refunded_for_item($item->get_id()));
        if ($refunded_qty > 0) {
            $product = $item->get_product();
            $uses_core_stock = $product && method_exists($product, 'managing_stock') && $product->managing_stock();
            if (!$uses_core_stock) {
                return max(0.0, $ordered_qty - min($ordered_qty, $refunded_qty));
            }
        }

        return $ordered_qty;
    }

    /**
     * Collect scoped order quantities grouped by product, day, or month.
     */
    private function collect_investment_order_quantities(array $scope, string $date_from = '', string $date_to = '', string $bucket = ''): array
    {
        static $quantities_cache = [];

        $scope_key = $this->get_investment_scope_cache_fragment($scope);
        $cache_key = md5(wp_json_encode([
            'scope' => $scope_key,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'bucket' => $bucket,
        ]));

        if (isset($quantities_cache[$cache_key])) {
            return $quantities_cache[$cache_key];
        }

        if (($scope['mode'] ?? 'empty') === 'empty') {
            $quantities_cache[$cache_key] = [];
            return [];
        }

        global $wpdb;

        $bucket = in_array($bucket, ['day', 'month'], true) ? $bucket : '';
        $statuses = $this->get_dashboard_order_query_statuses($this->get_investment_stock_affecting_statuses());
        if (empty($statuses)) {
            $quantities_cache[$cache_key] = [];
            return [];
        }

        $date_range = $this->get_dashboard_order_date_range($date_from, $date_to);
        $location_clause = '';
        $location_params = [];
        $location_slugs = isset($scope['term_slugs']) && is_array($scope['term_slugs'])
            ? array_values(array_unique(array_filter(array_map([$this, 'normalize_location_slug'], $scope['term_slugs']), 'strlen')))
            : [];

        $resolved_location_sql = "LOWER(TRIM(COALESCE(NULLIF(item_location_meta.meta_value, ''), NULLIF(order_location_meta.meta_value, ''), '')))";
        $product_id_sql = "
            CASE
                WHEN CAST(COALESCE(variation_id_meta.meta_value, '0') AS UNSIGNED) > 0
                    THEN CAST(variation_id_meta.meta_value AS UNSIGNED)
                ELSE CAST(COALESCE(product_id_meta.meta_value, '0') AS UNSIGNED)
            END
        ";
        $quantity_sql = "
            CASE
                WHEN reduced_stock_meta.meta_value IS NOT NULL AND reduced_stock_meta.meta_value != ''
                    THEN GREATEST(
                        LEAST(
                            ABS(CAST(reduced_stock_meta.meta_value AS DECIMAL(24,6))),
                            GREATEST(CAST(COALESCE(qty_meta.meta_value, '0') AS DECIMAL(24,6)), 0)
                        ),
                        0
                    )
                ELSE GREATEST(CAST(COALESCE(qty_meta.meta_value, '0') AS DECIMAL(24,6)), 0)
            END
        ";

        $scope_mode = isset($scope['mode']) ? (string) $scope['mode'] : 'all';
        if ($scope_mode === 'default') {
            // Orders without an explicit resolved location are excluded from
            // investment history because they do not map to a reliable stock
            // movement bucket and can double count against unchanged inventory.
            $quantities_cache[$cache_key] = [];
            return [];
        }

        $location_clause = " AND {$resolved_location_sql} != '' AND {$resolved_location_sql} != 'default'";

        if ($scope_mode === 'terms') {
            if (empty($location_slugs)) {
                $quantities_cache[$cache_key] = [];
                return [];
            }

            $placeholders = implode(',', array_fill(0, count($location_slugs), '%s'));
            $location_clause .= " AND {$resolved_location_sql} IN ({$placeholders})";
            $location_params = $location_slugs;
        }

        $status_placeholders = implode(',', array_fill(0, count($statuses), '%s'));
        $date_clauses = [];
        $date_params = [];
        $bucket_select = '';
        $group_by = $product_id_sql;

        if ($this->is_dashboard_hpos_enabled()) {
            $orders_table = $wpdb->prefix . 'wc_orders';
            $order_meta_table = $wpdb->prefix . 'wc_orders_meta';
            $order_id_column = 'orders.id';
            $order_status_column = 'orders.status';
            $order_date_column = 'orders.date_created_gmt';
            $order_type_clause = "orders.type = 'shop_order'";

            if ($date_range['gmt_from'] !== '') {
                $date_clauses[] = "{$order_date_column} >= %s";
                $date_params[] = $date_range['gmt_from'];
            }
            if ($date_range['gmt_to'] !== '') {
                $date_clauses[] = "{$order_date_column} < %s";
                $date_params[] = $date_range['gmt_to'];
            }
        } else {
            $orders_table = $wpdb->posts;
            $order_meta_table = $wpdb->postmeta;
            $order_id_column = 'orders.ID';
            $order_status_column = 'orders.post_status';
            $order_date_column = 'orders.post_date';
            $order_type_clause = "orders.post_type = 'shop_order'";

            if ($date_range['local_from'] !== '') {
                $date_clauses[] = "{$order_date_column} >= %s";
                $date_params[] = $date_range['local_from'];
            }
            if ($date_range['local_to'] !== '') {
                $date_clauses[] = "{$order_date_column} < %s";
                $date_params[] = $date_range['local_to'];
            }
        }

        if ($bucket === 'day') {
            $bucket_expression = "DATE({$order_date_column})";
            $bucket_select = "{$bucket_expression} AS bucket_key,";
            $group_by = "{$bucket_expression}, {$product_id_sql}";
        } elseif ($bucket === 'month') {
            $bucket_expression = "DATE_FORMAT({$order_date_column}, '%%Y-%%m')";
            $bucket_select = "{$bucket_expression} AS bucket_key,";
            $group_by = "{$bucket_expression}, {$product_id_sql}";
        }

        $date_sql = empty($date_clauses) ? '' : ' AND ' . implode(' AND ', $date_clauses);
        $order_items_table = $wpdb->prefix . 'woocommerce_order_items';
        $order_itemmeta_table = $wpdb->prefix . 'woocommerce_order_itemmeta';
        $meta_join_key = $this->is_dashboard_hpos_enabled() ? 'order_id' : 'post_id';

        $sql = "
            SELECT {$bucket_select}
                   {$product_id_sql} AS product_id,
                   SUM({$quantity_sql}) AS quantity
            FROM {$orders_table} orders
            INNER JOIN {$order_items_table} order_items
                ON order_items.order_id = {$order_id_column}
                AND order_items.order_item_type = 'line_item'
            LEFT JOIN {$order_itemmeta_table} product_id_meta
                ON product_id_meta.order_item_id = order_items.order_item_id
                AND product_id_meta.meta_key = '_product_id'
            LEFT JOIN {$order_itemmeta_table} variation_id_meta
                ON variation_id_meta.order_item_id = order_items.order_item_id
                AND variation_id_meta.meta_key = '_variation_id'
            LEFT JOIN {$order_itemmeta_table} qty_meta
                ON qty_meta.order_item_id = order_items.order_item_id
                AND qty_meta.meta_key = '_qty'
            LEFT JOIN {$order_itemmeta_table} reduced_stock_meta
                ON reduced_stock_meta.order_item_id = order_items.order_item_id
                AND reduced_stock_meta.meta_key = '_reduced_stock'
            LEFT JOIN {$order_itemmeta_table} item_location_meta
                ON item_location_meta.order_item_id = order_items.order_item_id
                AND item_location_meta.meta_key = '_mulopimfwc_location'
            LEFT JOIN {$order_meta_table} order_location_meta
                ON order_location_meta.{$meta_join_key} = {$order_id_column}
                AND order_location_meta.meta_key = '_store_location'
            LEFT JOIN {$order_meta_table} split_meta
                ON split_meta.{$meta_join_key} = {$order_id_column}
                AND split_meta.meta_key = '_mulopimfwc_split_parent'
            WHERE {$order_type_clause}
                AND {$order_status_column} IN ({$status_placeholders})
                AND (split_meta.meta_value IS NULL OR split_meta.meta_value != 'yes')
                AND {$product_id_sql} > 0
                AND {$quantity_sql} > 0
                {$date_sql}
                {$location_clause}
            GROUP BY {$group_by}
        ";

        $query = $wpdb->prepare($sql, array_merge($statuses, $date_params, $location_params));
        $rows = $wpdb->get_results($query);
        $quantities = [];

        if (is_array($rows)) {
            foreach ($rows as $row) {
                $product_id = isset($row->product_id) ? (int) $row->product_id : 0;
                $quantity = isset($row->quantity) ? (float) $row->quantity : 0.0;
                if ($product_id <= 0 || $quantity <= 0) {
                    continue;
                }

                if ($bucket === '') {
                    $quantities[$product_id] = ($quantities[$product_id] ?? 0) + $quantity;
                    continue;
                }

                $bucket_key = isset($row->bucket_key) ? (string) $row->bucket_key : '';
                if ($bucket_key === '') {
                    continue;
                }

                if (!isset($quantities[$bucket_key])) {
                    $quantities[$bucket_key] = [];
                }

                $quantities[$bucket_key][$product_id] = ($quantities[$bucket_key][$product_id] ?? 0) + $quantity;
            }
        }

        $quantities_cache[$cache_key] = $quantities;
        return $quantities_cache[$cache_key];
    }

    /**
     * Calculate total investment from current stock plus historical order quantities.
     */
    private function calculate_total_investment_efficiently($location_filter = 'all')
    {
        $scope = $this->build_investment_scope((string) $location_filter);
        $cache_version = function_exists('mulopimfwc_get_dashboard_cache_version')
            ? mulopimfwc_get_dashboard_cache_version()
            : 1;
        $cache_scope = $this->get_investment_scope_cache_fragment($scope);
        $cache_key = 'mulopimfwc_total_investment_' . $this->get_investment_cache_namespace() . '_v' . $cache_version . '_' . $cache_scope;
        $cached_value = get_transient($cache_key);

        if ($cached_value !== false) {
            return (float) $cached_value;
        }

        if (($scope['mode'] ?? 'empty') === 'empty') {
            set_transient($cache_key, 0.0, HOUR_IN_SECONDS);
            return 0.0;
        }

        $current_stock_total = $this->calculate_current_stock_investment_total($scope);
        $ordered_quantities = $this->collect_investment_order_quantities($scope);
        $ordered_total = $this->calculate_investment_amount_from_product_quantities($ordered_quantities);
        $total_investment = round($current_stock_total + $ordered_total, 2);

        set_transient($cache_key, $total_investment, HOUR_IN_SECONDS);

        return $total_investment;
    }

    /**
     * Get monthly investment trend data with caching.
     */
    private function get_monthly_investment_data_cached($location_filter = 'all')
    {
        $scope = $this->build_investment_scope((string) $location_filter);
        $cache_version = function_exists('mulopimfwc_get_dashboard_cache_version')
            ? mulopimfwc_get_dashboard_cache_version()
            : 1;
        $cache_scope = $this->get_investment_scope_cache_fragment($scope);
        $cache_key = 'mulopimfwc_monthly_investment_' . $this->get_investment_cache_namespace() . '_v' . $cache_version . '_' . $cache_scope;
        $cached_data = get_transient($cache_key);

        $now = new DateTimeImmutable('first day of this month 00:00:00', new DateTimeZone('UTC'));
        $start = $now->modify('-11 months');
        $end = $now->modify('+1 month');

        $labels = [];
        $cursor = $start;
        for ($i = 0; $i < 12; $i++) {
            $labels[] = $cursor->format('M Y');
            $cursor = $cursor->modify('+1 month');
        }

        if (($scope['mode'] ?? 'empty') === 'empty') {
            return [
                'labels' => $labels,
                'data' => array_fill(0, 12, 0.0),
            ];
        }

        if ($cached_data !== false) {
            return $cached_data;
        }

        $bucketed_quantities = $this->collect_investment_order_quantities(
            $scope,
            $start->format('Y-m-d'),
            $end->modify('-1 day')->format('Y-m-d'),
            'month'
        );
        $current_stock_total = $this->calculate_current_stock_investment_total($scope);
        $current_month_key = $now->format('Y-m');

        $data = [];
        $cursor = $start;
        for ($i = 0; $i < 12; $i++) {
            $month_key = $cursor->format('Y-m');
            $month_total = $this->calculate_investment_amount_from_product_quantities($bucketed_quantities[$month_key] ?? []);
            if ($month_key === $current_month_key) {
                $month_total += $current_stock_total;
            }
            $data[] = round($month_total, 2);
            $cursor = $cursor->modify('+1 month');
        }

        $result = [
            'labels' => $labels,
            'data' => $data,
        ];

        set_transient($cache_key, $result, 6 * HOUR_IN_SECONDS);

        return $result;
    }

    /**
     * Cache and return the fast order/revenue/recent-product payload.
     */
    private function get_dashboard_orders_payload_cached(): array
    {
        $cache_version = function_exists('mulopimfwc_get_dashboard_cache_version')
            ? mulopimfwc_get_dashboard_cache_version()
            : 1;
        $cache_key = 'mulopimfwc_dashboard_orders_payload_' . $this->get_dashboard_orders_cache_namespace() . '_v' . $cache_version . '_' . $this->get_live_dashboard_cache_scope_hash();
        $cached_payload = get_transient($cache_key);

        if (is_array($cached_payload)) {
            return $cached_payload;
        }

        $payload = $this->build_dashboard_orders_payload();
        set_transient($cache_key, $payload, 2 * MINUTE_IN_SECONDS);

        return $payload;
    }

    /**
     * Build the fast dashboard payload that should always return before heavy calculations.
     */
    private function build_dashboard_orders_payload(): array
    {
        global $mulopimfwc_locations;
        $mulopimfwc_locations = $this->get_dashboard_scoped_locations();

        $orders_data = $this->get_orders_data_efficiently();
        $recent_products_data = $this->get_recent_products_data();

        return [
            'orders_by_location' => $orders_data['orders'] ?? [],
            'sales_by_location' => $orders_data['sales'] ?? [],
            'revenue_by_location' => $orders_data['revenue'] ?? [],
            'recent_products_data' => $recent_products_data,
            'summary' => [
                'total_orders' => array_sum($orders_data['orders'] ?? []),
                'total_sell' => array_sum($orders_data['sales'] ?? []),
                'total_revenue' => array_sum($orders_data['revenue'] ?? []),
            ],
        ];
    }

    /**
     * Cache and return the heavy investment and stock-alert payload.
     */
    private function get_dashboard_investment_payload_cached(bool $allow_build = true): array
    {
        $cache_version = function_exists('mulopimfwc_get_dashboard_cache_version')
            ? mulopimfwc_get_dashboard_cache_version()
            : 1;
        $cache_key = 'mulopimfwc_dashboard_investment_payload_' . $this->get_investment_cache_namespace() . '_v' . $cache_version . '_' . $this->get_live_dashboard_cache_scope_hash();
        $cached_payload = get_transient($cache_key);

        if (is_array($cached_payload)) {
            return $cached_payload;
        }

        if (!$allow_build) {
            return [];
        }

        $payload = $this->build_dashboard_investment_payload();
        set_transient($cache_key, $payload, 10 * MINUTE_IN_SECONDS);

        return $payload;
    }

    /**
     * Build the heavy dashboard payload separately so the UI can lazy-load it.
     */
    private function build_dashboard_investment_payload(): array
    {
        global $mulopimfwc_locations;
        $mulopimfwc_locations = $this->get_dashboard_scoped_locations();

        $total_investment = $this->calculate_total_investment_efficiently();

        return [
            'monthly_investment_data' => $this->get_monthly_investment_data_cached(),
            'dead_stock_days' => 90,
            'total_investment' => $total_investment,
            'low_stock_products' => $this->get_low_stock_products_efficiently(),
            'summary' => [
                'total_investment' => $total_investment,
            ],
        ];
    }

    /**
     * Build a reusable payload for the dashboard and live APIs.
     */
    private function get_dashboard_payload_cached(): array
    {
        $cache_version = function_exists('mulopimfwc_get_dashboard_cache_version')
            ? mulopimfwc_get_dashboard_cache_version()
            : 1;
        $cache_key = 'mulopimfwc_dashboard_payload_' . $this->get_dashboard_orders_cache_namespace() . '_v' . $cache_version . '_' . $this->get_live_dashboard_cache_scope_hash();
        $cached_payload = get_transient($cache_key);

        if (is_array($cached_payload)) {
            return $cached_payload;
        }

        $payload = $this->build_dashboard_payload();
        set_transient($cache_key, $payload, 2 * MINUTE_IN_SECONDS);

        return $payload;
    }

    private function build_dashboard_payload(): array
    {
        $lightweight_payload = $this->get_lightweight_dashboard_payload();
        $orders_payload = $this->get_dashboard_orders_payload_cached();
        $investment_payload = $this->get_dashboard_investment_payload_cached();

        $summary = array_merge(
            $lightweight_payload['summary'] ?? [],
            $orders_payload['summary'] ?? [],
            $investment_payload['summary'] ?? []
        );

        return [
            'product_counts' => $lightweight_payload['product_counts'] ?? [],
            'stock_levels' => $lightweight_payload['stock_levels'] ?? [],
            'location_colors' => $lightweight_payload['location_colors'] ?? [],
            'location_border_colors' => $lightweight_payload['location_border_colors'] ?? [],
            'orders_by_location' => $orders_payload['orders_by_location'] ?? [],
            'revenue_by_location' => $orders_payload['revenue_by_location'] ?? [],
            'recent_products_data' => $orders_payload['recent_products_data'] ?? ($lightweight_payload['recent_products_data'] ?? ['labels' => [], 'counts' => []]),
            'monthly_investment_data' => $investment_payload['monthly_investment_data'] ?? ($lightweight_payload['monthly_investment_data'] ?? ['labels' => [], 'data' => []]),
            'dead_stock_days' => $investment_payload['dead_stock_days'] ?? ($lightweight_payload['dead_stock_days'] ?? 90),
            'total_investment' => $investment_payload['total_investment'] ?? ($lightweight_payload['total_investment'] ?? 0),
            'low_stock_products' => $investment_payload['low_stock_products'] ?? ($lightweight_payload['low_stock_products'] ?? []),
            'summary' => $summary,
        ];
    }

    /**
     * Build a cache scope hash for live dashboard payloads.
     * This prevents location-scoped data from leaking between users.
     */
    private function get_live_dashboard_cache_scope_hash(): string
    {
        $scope = [
            'user_id' => (int) get_current_user_id(),
            'role' => 'default',
        ];

        if (is_user_logged_in() && class_exists('MULOPIMFWC_Location_Managers')) {
            $user = wp_get_current_user();
            if (in_array('mulopimfwc_location_manager', (array) $user->roles, true)) {
                $assigned_locations = MULOPIMFWC_Location_Managers::get_user_assigned_locations();
                if (!is_array($assigned_locations)) {
                    $assigned_locations = [];
                }
                $assigned_locations = array_values(array_unique(array_filter(array_map('strval', $assigned_locations))));
                sort($assigned_locations, SORT_STRING);

                $scope['role'] = 'location_manager';
                $scope['all_products'] = MULOPIMFWC_Location_Managers::user_has_capability('all_products') ? 1 : 0;
                $scope['all_orders'] = MULOPIMFWC_Location_Managers::user_has_capability('all_orders') ? 1 : 0;
                $scope['assigned_locations'] = $assigned_locations;
            }
        }

        $scope_json = wp_json_encode($scope);
        if (!is_string($scope_json) || $scope_json === '') {
            $scope_json = serialize($scope);
        }

        return md5($scope_json);
    }

    /**
     * AJAX endpoint for live dashboard updates.
     */
    public function handle_live_dashboard_data()
    {
        check_ajax_referer('mulopimfwc_dashboard_realtime_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error([
                'message' => __('Permission denied', 'multi-location-product-and-inventory-management-pro'),
            ]);
            return;
        }

        // FIXED: Add rate limiting (max 1 request per 5 seconds per user) (Issue #32)
        $rate_limit_key = 'mulopimfwc_dashboard_live_rate_' . get_current_user_id();
        $last_request = get_transient($rate_limit_key);
        if ($last_request !== false && (time() - $last_request) < 5) {
            wp_send_json_error([
                'message' => __('Please wait before requesting another update.', 'multi-location-product-and-inventory-management-pro'),
            ]);
            return;
        }
        set_transient($rate_limit_key, time(), 10); // 10 second expiry

        // FIXED: Use scoped caching to reduce database load without cross-user data leakage.
        $cache_version = function_exists('mulopimfwc_get_dashboard_cache_version')
            ? mulopimfwc_get_dashboard_cache_version()
            : 1;
        $cache_key = 'mulopimfwc_dashboard_live_data_v' . $cache_version . '_' . $this->get_live_dashboard_cache_scope_hash();
        $cached_data = get_transient($cache_key);
        if ($cached_data !== false) {
            wp_send_json_success($cached_data);
            return;
        }

        $lightweight_payload = $this->get_lightweight_dashboard_payload();
        $orders_payload = $this->get_dashboard_orders_payload_cached();
        $investment_payload = $this->get_dashboard_investment_payload_cached(false);
        $summary = array_merge(
            $lightweight_payload['summary'] ?? [],
            $orders_payload['summary'] ?? []
        );
        unset($summary['total_investment']);
        if (!empty($investment_payload['summary']) && is_array($investment_payload['summary'])) {
            $summary = array_merge($summary, $investment_payload['summary']);
        }

        $last_check = (int) get_option('mulopimfwc_dashboard_last_check', 0);
        $site_status = $this->resolve_site_status();
        $alerts = $this->collect_live_alerts($last_check, [
            'low_stock_products' => $investment_payload['low_stock_products'] ?? [],
        ], $site_status);

        update_option('mulopimfwc_dashboard_last_check', current_time('timestamp', true));

        $response_data = [
            'productCounts' => $lightweight_payload['product_counts'] ?? [],
            'stockLevels' => $lightweight_payload['stock_levels'] ?? [],
            'locationColors' => $lightweight_payload['location_colors'] ?? [],
            'locationBorderColors' => $lightweight_payload['location_border_colors'] ?? [],
            'orders' => $orders_payload['orders_by_location'] ?? [],
            'revenue' => $orders_payload['revenue_by_location'] ?? [],
            'summary' => $summary,
            'dateLabels' => $orders_payload['recent_products_data']['labels'] ?? [],
            'dateCounts' => $orders_payload['recent_products_data']['counts'] ?? [],
            'alerts' => $alerts,
            'site_status' => $site_status,
            'currency' => $this->get_dashboard_reporting_currency_symbol(),
            'currency_code' => $this->get_dashboard_reporting_currency_code(),
        ];

        if (!empty($investment_payload)) {
            $response_data['monthlyInvestmentLabels'] = $investment_payload['monthly_investment_data']['labels'] ?? [];
            $response_data['monthlyInvestmentData'] = $investment_payload['monthly_investment_data']['data'] ?? [];
            $response_data['deadStockDays'] = $investment_payload['dead_stock_days'] ?? 90;
            $response_data['totalInvestment'] = $investment_payload['total_investment'] ?? 0;
            $response_data['low_stock'] = array_slice($investment_payload['low_stock_products'] ?? [], 0, 8);
        }

        // Cache for 30 seconds
        set_transient($cache_key, $response_data, 30);

        wp_send_json_success($response_data);
    }

    /**
     * Lightweight dashboard payload — only fast queries, no orders/investment.
     */
    private function get_lightweight_dashboard_payload(): array
    {
        global $mulopimfwc_locations;
        $mulopimfwc_locations = $this->get_dashboard_scoped_locations();

        $cache_version = function_exists('mulopimfwc_get_dashboard_cache_version')
            ? mulopimfwc_get_dashboard_cache_version()
            : 1;
        $cache_key = 'mulopimfwc_lightweight_payload_v' . $cache_version . '_' . $this->get_live_dashboard_cache_scope_hash();
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $product_counts       = [];
        $stock_levels         = [];
        $location_colors      = [];
        $location_border_colors = [];
        $base_colors = [
            ['fill' => '#ef4444', 'border' => '#f87171'],
            ['fill' => '#f59e0b', 'border' => '#fbbf24'],
            ['fill' => '#10b981', 'border' => '#34d399'],
            ['fill' => '#06b6d4', 'border' => '#22d3ee'],
            ['fill' => '#8b5cf6', 'border' => '#a78bfa'],
            ['fill' => '#ec4899', 'border' => '#f472b6'],
            ['fill' => '#6366f1', 'border' => '#818cf8'],
        ];

        foreach ($mulopimfwc_locations as $index => $location) {
            $base_index = $index % count($base_colors);
            $cycle      = (int) floor($index / count($base_colors));
            if ($cycle === 0) {
                $location_colors[$location->name]       = $base_colors[$base_index]['fill'];
                $location_border_colors[$location->name] = $base_colors[$base_index]['border'];
            } else {
                $adjust = ($cycle * 10) % 30;
                $location_colors[$location->name]       = $this->adjustColorLightness($base_colors[$base_index]['fill'], $adjust);
                $location_border_colors[$location->name] = $this->adjustColorLightness($base_colors[$base_index]['border'], $adjust);
            }
            $product_counts[$location->name] = $this->get_location_product_count($location->term_id);
            $stock_levels[$location->name]   = $this->get_location_stock_level($location->term_id);
        }

        $location_card   = $this->get_location_card_summary('all');
        $total_products  = $this->get_total_products_count();

        // Date labels only — no counts (cheap)
        $date_labels = [];
        for ($i = 29; $i >= 0; $i--) {
            $date_labels[] = gmdate('M d', strtotime("-$i days"));
        }

        // Month labels only
        $month_labels = [];
        $cursor = new DateTimeImmutable('first day of -11 months 00:00:00', new DateTimeZone('UTC'));
        for ($i = 0; $i < 12; $i++) {
            $month_labels[] = $cursor->format('M Y');
            $cursor = $cursor->modify('+1 month');
        }

        $payload = [
            'product_counts'        => $product_counts,
            'stock_levels'          => $stock_levels,
            'location_colors'       => $location_colors,
            'location_border_colors' => $location_border_colors,
            'orders_by_location'    => array_map(fn() => 0, $product_counts), // placeholder shape
            'revenue_by_location'   => array_map(fn() => 0, $product_counts),
            'recent_products_data'  => [
                'labels' => $date_labels,
                'counts' => array_fill(0, 30, 0),
            ],
            'monthly_investment_data' => [
                'labels' => $month_labels,
                'data'   => array_fill(0, 12, 0),
            ],
            'dead_stock_days'  => 90,
            'total_investment' => 0,
            'low_stock_products' => [],
            'summary' => [
                'total_products'      => $total_products,
                'total_locations'     => (int) ($location_card['count'] ?? count($mulopimfwc_locations)),
                'total_orders'        => 0,
                'total_revenue'       => 0,
                'total_stock'         => array_sum($stock_levels),
                'total_investment'    => 0,
                'location_card_label' => (string) ($location_card['label'] ?? __('Locations', 'multi-location-product-and-inventory-management-pro')),
                'location_card_value' => (string) ($location_card['value'] ?? count($mulopimfwc_locations)),
            ],
        ];

        set_transient($cache_key, $payload, 120);
        return $payload;
    }

    /**
     * Collect alert information for live dashboards.
     */
    private function collect_live_alerts($since_timestamp, $payload, $site_status): array
    {
        if (!function_exists('wc_get_orders')) {
            return [];
        }

        $alerts = [];
        $since = $since_timestamp ?: (current_time('timestamp', true) - 300);
        $date_from = gmdate('Y-m-d H:i:s', $since);
        $manager_locations = null;
        if (is_user_logged_in() && class_exists('MULOPIMFWC_Location_Managers')) {
            $user = wp_get_current_user();
            if (
                in_array('mulopimfwc_location_manager', $user->roles, true) &&
                !MULOPIMFWC_Location_Managers::user_has_capability('all_orders')
            ) {
                $manager_locations = MULOPIMFWC_Location_Managers::get_user_assigned_locations();
                if (!is_array($manager_locations)) {
                    $manager_locations = [];
                }
            }
        }

        $can_manage_products = MULOPIMFWC_Location_Managers::user_has_capability('manage_products');
        $can_view_products = MULOPIMFWC_Location_Managers::user_has_capability('view_products');
        $can_view_dashboard = MULOPIMFWC_Location_Managers::user_has_capability('view_dashboard');
        $products_list_url = admin_url('edit.php?post_type=product');
        $stock_central_url = admin_url('admin.php?page=location-stock-management');
        $dashboard_url = $can_view_dashboard
            ? admin_url('admin.php?page=multi-location-product-and-inventory-management')
            : admin_url('index.php');
        if ($can_manage_products) {
            $fallback_product_url = $products_list_url;
        } elseif ($can_view_products) {
            $fallback_product_url = $stock_central_url;
        } else {
            $fallback_product_url = $dashboard_url;
        }

        $order_args = [
            'limit' => 25,
            'status' => ['pending', 'processing', 'on-hold', 'completed', 'failed', 'cancelled', 'refunded'],
            'date_created' => '>=' . $date_from,
            'return' => 'ids',
        ];

        if (is_array($manager_locations)) {
            if (empty($manager_locations)) {
                return [];
            }
            $order_args['meta_query'] = [
                [
                    'key' => '_store_location',
                    'value' => $manager_locations,
                    'compare' => 'IN',
                ],
            ];
        }

        $order_ids = wc_get_orders($order_args);

        $new_orders = [];
        $orders_by_status = [
            'failed' => [],
            'refunded' => [],
            'cancelled' => [],
            'completed' => [],
        ];
        $high_value_orders = [];
        global $mulopimfwc_options;
        $options = is_array($mulopimfwc_options ?? null)
            ? $mulopimfwc_options
            : get_option('mulopimfwc_display_options', []);
        $social = isset($options['social_notifications']) ? $options['social_notifications'] : [];
        $high_value_threshold = floatval($social['high_value_threshold'] ?? 500);

        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) {
                continue;
            }

            if ($order->get_meta('_mulopimfwc_split_parent') === 'yes') {
                continue;
            }

            $status = $order->get_status();
            if (in_array($status, ['pending', 'processing', 'on-hold'], true)) {
                $new_orders[] = $order;
            }

            if (isset($orders_by_status[$status])) {
                $orders_by_status[$status][] = $order;
            }

            if ($high_value_threshold > 0 && floatval($order->get_total()) >= $high_value_threshold) {
                $high_value_orders[] = $order;
            }
        }

        if (!empty($new_orders)) {
            // Create individual alerts for each new order to ensure unique notifications
            foreach ($new_orders as $order) {
                $order_id = $order->get_id();
                $order_number = $order->get_order_number();
                $order_total = $order->get_total();
                $order_date = $order->get_date_created();

                // Format price and decode HTML entities for plain text notification
                $formatted_price = wp_strip_all_tags(wc_price($order_total));
                $formatted_price = html_entity_decode($formatted_price, ENT_QUOTES, 'UTF-8');

                $alerts[] = $this->format_alert(
                    'new_order',
                    sprintf(/* translators: 1: order number, 2: formatted order total */__('New order #%1$s placed - %2$s', 'multi-location-product-and-inventory-management-pro'), $order_number, $formatted_price),
                    'info',
                    [
                        'order_id' => $order_id,
                        'order_number' => $order_number,
                        'order_total' => $order_total,
                        'url' => $order->get_edit_order_url() ?: admin_url('post.php?post=' . $order_id . '&action=edit'),
                    ]
                );
            }
        }

        $low_stock_products = isset($payload['low_stock_products']) && is_array($payload['low_stock_products'])
            ? $payload['low_stock_products']
            : [];

        $low_stock_items = array_values(array_filter($low_stock_products, static function ($item) {
            return isset($item['stock']) && (($item['status'] ?? '') === 'low_stock');
        }));
        if (!empty($low_stock_items)) {
            // Create individual alerts for each low stock product
            foreach (array_slice($low_stock_items, 0, 5) as $item) {
                $product_id = isset($item['product_id']) ? $item['product_id'] : 0;
                $edit_post_id = isset($item['edit_post_id']) ? (int) $item['edit_post_id'] : $product_id;
                $product_url = $fallback_product_url;
                if ($can_manage_products && $product_id) {
                    $product_url = get_edit_post_link($edit_post_id) ?: $fallback_product_url;
                }
                $alerts[] = $this->format_alert(
                    'low_stock',
                    sprintf(/* translators: 1: product title, 2: location name */__('Low stock: %1$s (%2$s)', 'multi-location-product-and-inventory-management-pro'), $item['product_title'], $item['location_name']),
                    'warning',
                    [
                        'product_id' => $product_id,
                        'location_id' => $item['location_id'] ?? null,
                        'location_name' => $item['location_name'] ?? '',
                        'stock' => $item['stock'] ?? null,
                        'url' => $product_url,
                    ]
                );
            }
        }

        $out_of_stock = array_values(array_filter($low_stock_products, static function ($item) {
            return isset($item['stock']) && (($item['status'] ?? '') === 'out_of_stock');
        }));
        if (!empty($out_of_stock)) {
            // Create individual alerts for each out of stock product
            foreach (array_slice($out_of_stock, 0, 5) as $item) {
                $product_id = isset($item['product_id']) ? $item['product_id'] : 0;
                $edit_post_id = isset($item['edit_post_id']) ? (int) $item['edit_post_id'] : $product_id;
                $product_url = $fallback_product_url;
                if ($can_manage_products && $product_id) {
                    $product_url = get_edit_post_link($edit_post_id) ?: $fallback_product_url;
                }
                $alerts[] = $this->format_alert(
                    'out_of_stock',
                    sprintf(/* translators: 1: product title, 2: location name */__('Out of stock: %1$s (%2$s)', 'multi-location-product-and-inventory-management-pro'), $item['product_title'], $item['location_name']),
                    'critical',
                    [
                        'product_id' => $product_id,
                        'location_id' => $item['location_id'] ?? null,
                        'location_name' => $item['location_name'] ?? '',
                        'stock' => $item['stock'] ?? null,
                        'url' => $product_url,
                    ]
                );
            }
        }

        foreach (['failed', 'refunded', 'cancelled', 'completed'] as $key) {
            if (!empty($orders_by_status[$key])) {
                $count = count($orders_by_status[$key]);
                $order_ids = array_map(static function ($order) {
                    return $order ? $order->get_id() : 0;
                }, $orders_by_status[$key]);
                $order_ids = array_values(array_filter($order_ids));
                sort($order_ids, SORT_NUMERIC);
                $alerts[] = $this->format_alert(
                    "order_{$key}",
                    sprintf(/* translators: 1: order status label (e.g. Completed), 2: count */__('%1$s orders %2$s', 'multi-location-product-and-inventory-management-pro'), ucwords(str_replace('_', ' ', $key)), $count),
                    'info',
                    [
                        'order_ids' => $order_ids,
                        'count' => $count,
                    ]
                );
            }
        }

        if (!empty($high_value_orders)) {
            $order = reset($high_value_orders);
            // Format price and decode HTML entities for plain text notification
            $formatted_price = wp_strip_all_tags(wc_price($order->get_total()));
            $formatted_price = html_entity_decode($formatted_price, ENT_QUOTES, 'UTF-8');

            $alerts[] = $this->format_alert(
                'high_value_order',
                sprintf(/* translators: 1: order number, 2: formatted order total */__('High-value order: #%1$s (%2$s)', 'multi-location-product-and-inventory-management-pro'), $order->get_order_number(), $formatted_price),
                'info',
                [
                    'order_id' => $order->get_id(),
                    'order_number' => $order->get_order_number(),
                    'order_total' => $order->get_total(),
                    'order_date' => $order->get_date_created() ? $order->get_date_created()->getTimestamp() : null,
                    'url' => $order->get_edit_order_url(),
                ]
            );
        }

        $raw_previous_snapshot = get_option('mulopimfwc_live_low_stock_snapshot', []);
        $previous_snapshot = [];
        $has_legacy_snapshot = false;
        if (is_array($raw_previous_snapshot)) {
            foreach ($raw_previous_snapshot as $snapshot_key => $snapshot_item) {
                if (!is_array($snapshot_item) || empty($snapshot_item['product_title']) || empty($snapshot_item['location_name'])) {
                    $has_legacy_snapshot = true;
                    continue;
                }

                $previous_snapshot[(string) $snapshot_key] = $snapshot_item;
            }
        }

        $current_snapshot = [];
        foreach ($low_stock_products as $item) {
            if (!empty($item['alert_key'])) {
                $current_snapshot[(string) $item['alert_key']] = [
                    'product_id' => absint($item['product_id'] ?? 0),
                    'edit_post_id' => absint($item['edit_post_id'] ?? ($item['product_id'] ?? 0)),
                    'product_title' => (string) ($item['product_title'] ?? ''),
                    'location_id' => absint($item['location_id'] ?? 0),
                    'location_name' => (string) ($item['location_name'] ?? ''),
                    'stock' => (int) ($item['stock'] ?? 0),
                    'status' => (string) ($item['status'] ?? ''),
                ];
            }
        }

        $restocked = [];
        if (!$has_legacy_snapshot) {
            foreach ($previous_snapshot as $alert_key => $snapshot_item) {
                if (!isset($current_snapshot[$alert_key])) {
                    $restocked[$alert_key] = $snapshot_item;
                }
            }
        }
        update_option('mulopimfwc_live_low_stock_snapshot', $current_snapshot);
        if (!empty($restocked)) {
            $titles = [];
            $product_ids = [];
            foreach ($restocked as $snapshot_item) {
                $product_id = absint($snapshot_item['product_id'] ?? 0);
                if ($product_id > 0) {
                    $product_ids[] = $product_id;
                }

                $product_title = trim((string) ($snapshot_item['product_title'] ?? ''));
                $location_name = trim((string) ($snapshot_item['location_name'] ?? ''));
                if ($product_title !== '') {
                    $titles[] = $location_name !== '' ? sprintf('%s (%s)', $product_title, $location_name) : $product_title;
                }
            }
            if (!empty($titles)) {
                $product_ids = array_values(array_unique($product_ids));
                sort($product_ids, SORT_NUMERIC);
                $alerts[] = $this->format_alert(
                    'restocked',
                    sprintf(/* translators: %s: comma-separated list of product/location titles */__('Restocked: %s', 'multi-location-product-and-inventory-management-pro'), implode(', ', array_slice($titles, 0, 3))),
                    'info',
                    [
                        'product_ids' => $product_ids,
                        'count' => count($restocked),
                    ]
                );
            }
        }

        $review_threshold = floatval($social['low_review_threshold'] ?? 3);
        $low_reviews = get_comments([
            'status' => 'approve',
            'number' => 3,
            'date_query' => [
                [
                    'after' => $date_from,
                ],
            ],
            'type' => 'review',
            'meta_key' => 'rating',
            'meta_value' => $review_threshold,
            'meta_compare' => '<',
        ]);
        if (!empty($low_reviews)) {
            $review = reset($low_reviews);
            $product = wc_get_product($review->comment_post_ID);
            $alerts[] = $this->format_alert(
                'low_review_alert',
                sprintf(/* translators: %s: product name */__('Low rating review for %s', 'multi-location-product-and-inventory-management-pro'), $product ? $product->get_name() : __('Product', 'multi-location-product-and-inventory-management-pro')),
                'info',
                [
                    'review_id' => $review->comment_ID,
                    'product_id' => $product ? $product->get_id() : null,
                    'url' => get_edit_comment_link($review->comment_ID),
                ]
            );
        }

        $manager_event = get_option('mulopimfwc_manager_change_pending');
        if (!empty($manager_event) && isset($manager_event['timestamp']) && $manager_event['timestamp'] > $since_timestamp) {
            $user = get_userdata($manager_event['user_id']);
            if ($user) {
                $alerts[] = $this->format_alert(
                    'manager_change',
                    sprintf(/* translators: %s: manager display name */__('Manager updated: %s', 'multi-location-product-and-inventory-management-pro'), $user->display_name),
                    'info',
                    [
                        'user_id' => $user->ID,
                        'changed_at' => $manager_event['timestamp'] ?? null,
                        'url' => admin_url('user-edit.php?user_id=' . $user->ID),
                    ]
                );
            }
            delete_option('mulopimfwc_manager_change_pending');
        }

        if (!empty($site_status['changed'])) {
            $alerts[] = $this->format_alert(
                'site_status',
                $site_status['message'],
                $site_status['status'] === 'down' ? 'critical' : 'info',
                [
                    'status' => $site_status['status'] ?? '',
                    'changed_at' => $site_status['changed_at'] ?? null,
                ]
            );
        }

        return array_slice($alerts, 0, 6);
    }

    /**
     * Resolve site status without overloading the monitor.
     */
    private function resolve_site_status(): array
    {
        $now = current_time('timestamp', true);
        $last_check = (int) get_option('mulopimfwc_site_status_last_check', 0);
        $state = get_option('mulopimfwc_site_status_state', 'up');
        $changed = false;
        $changed_at = null;

        if ($now - $last_check >= 300) {
            $response = wp_remote_get(home_url(), ['timeout' => 5, 'blocking' => true]);
            $is_down = is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) >= 500;
            $new_state = $is_down ? 'down' : 'up';

            if ($new_state !== $state) {
                $state = $new_state;
                update_option('mulopimfwc_site_status_state', $state);
                $changed = true;
                $changed_at = $now;
                update_option('mulopimfwc_site_status_changed_at', $changed_at);
            }

            update_option('mulopimfwc_site_status_last_check', $now);
        }

        return [
            'status' => $state,
            'changed' => $changed,
            'changed_at' => $changed ? $changed_at : null,
            'message' => $changed
                ? ($state === 'down'
                    ? __('Site went down. Monitoring could not reach the server.', 'multi-location-product-and-inventory-management-pro')
                    : __('Site is reachable again.', 'multi-location-product-and-inventory-management-pro'))
                : __('Site monitoring is stable.', 'multi-location-product-and-inventory-management-pro'),
        ];
    }

    /**
     * Normalize alert payloads.
     */
    private function format_alert(string $type, string $message, string $severity = 'info', array $data = []): array
    {
        // Strip HTML tags and decode HTML entities for plain text notifications
        $message = wp_strip_all_tags($message);
        $message = html_entity_decode($message, ENT_QUOTES, 'UTF-8');

        // Create unique ID based on type and relevant identifiers
        $unique_id = $type;
        $unique_suffix = '';

        if (!empty($data['event_id'])) {
            $unique_suffix = sanitize_key((string) $data['event_id']);
        } elseif (!empty($data['order_id'])) {
            $unique_suffix = 'order-' . absint($data['order_id']);
        } elseif (!empty($data['order_ids']) && is_array($data['order_ids'])) {
            $order_ids = array_values(array_filter(array_map('absint', $data['order_ids'])));
            if (!empty($order_ids)) {
                sort($order_ids, SORT_NUMERIC);
                $unique_suffix = 'orders-' . md5(implode(',', $order_ids));
            }
        } elseif (!empty($data['product_id'])) {
            $unique_suffix = 'product-' . absint($data['product_id']);
            if (!empty($data['location_id'])) {
                $unique_suffix .= '-loc-' . absint($data['location_id']);
            } elseif (!empty($data['location_name'])) {
                $unique_suffix .= '-loc-' . sanitize_title($data['location_name']);
            }
        } elseif (!empty($data['product_ids']) && is_array($data['product_ids'])) {
            $product_ids = array_values(array_filter(array_map('absint', $data['product_ids'])));
            if (!empty($product_ids)) {
                sort($product_ids, SORT_NUMERIC);
                $unique_suffix = 'products-' . md5(implode(',', $product_ids));
            }
        } elseif (!empty($data['review_id'])) {
            $unique_suffix = 'review-' . absint($data['review_id']);
        } elseif (!empty($data['user_id'])) {
            $unique_suffix = 'user-' . absint($data['user_id']);
            if (!empty($data['changed_at'])) {
                $unique_suffix .= '-at-' . absint($data['changed_at']);
            }
        } elseif ($type === 'site_status' && !empty($data['status'])) {
            $unique_suffix = 'status-' . sanitize_key((string) $data['status']);
            if (!empty($data['changed_at'])) {
                $unique_suffix .= '-at-' . absint($data['changed_at']);
            }
        } elseif (!empty($data['changed_at'])) {
            $unique_suffix = 'at-' . absint($data['changed_at']);
        }

        if (!empty($unique_suffix)) {
            $unique_id .= '-' . $unique_suffix;
        } else {
            // Fallback to unique ID with timestamp if no stable identifier is available
            $unique_id .= '-' . wp_unique_id() . '-' . current_time('timestamp', true);
        }

        return [
            'id' => $unique_id,
            'type' => $type,
            'label' => ucfirst(str_replace('_', ' ', $type)),
            'message' => $message,
            'severity' => $severity,
            'data' => $data,
            'timestamp' => current_time('timestamp', true),
        ];
    }
}
