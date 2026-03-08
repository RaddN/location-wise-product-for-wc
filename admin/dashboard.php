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
    }

    /**
     * Normalize a location slug for consistent comparisons.
     */
    private function normalize_location_slug($location_slug): string
    {
        return sanitize_title(rawurldecode((string) $location_slug));
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
            wp_send_json_error(array('message' => __('Permission denied', 'multi-location-product-and-inventory-management')));
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
        fputcsv($output, array(__('LOCATION WISE PRODUCT & INVENTORY DASHBOARD REPORT', 'multi-location-product-and-inventory-management')));
        fputcsv($output, array(__('Generated on:', 'multi-location-product-and-inventory-management'), gmdate('l, F d, Y - H:i:s')));
        fputcsv($output, array(__('Store:', 'multi-location-product-and-inventory-management'), get_bloginfo('name')));
        fputcsv($output, array(__('Currency:', 'multi-location-product-and-inventory-management'), ' (' . $this->get_dashboard_reporting_currency_code() . ')'));
        fputcsv($output, array('')); // Empty row

        // ---------- Summary ----------
        fputcsv($output, [__('SUMMARY STATISTICS', 'multi-location-product-and-inventory-management')]);
        fputcsv($output, [__('Metric', 'multi-location-product-and-inventory-management'), __('Value', 'multi-location-product-and-inventory-management')]);
        fputcsv($output, [__('Total Products', 'multi-location-product-and-inventory-management'), $this->get_total_products_count()]);
        fputcsv($output, [__('Total Locations', 'multi-location-product-and-inventory-management'), count($mulopimfwc_locations)]);
        fputcsv($output, [__('Total Orders', 'multi-location-product-and-inventory-management'), array_sum($orders_data['orders'])]);
        fputcsv($output, [__('Total Revenue', 'multi-location-product-and-inventory-management'), number_format(array_sum($orders_data['revenue']), 2)]);
        fputcsv($output, [__('Total Investment', 'multi-location-product-and-inventory-management'), number_format($total_investment, 2)]);
        fputcsv($output, [__('Total Stock', 'multi-location-product-and-inventory-management'), array_sum($stock_levels)]);
        fputcsv($output, ['']);

        // ---------- Products by Location (incl. Default) ----------
        fputcsv($output, [__('PRODUCTS BY LOCATION', 'multi-location-product-and-inventory-management')]);
        fputcsv($output, array(__('Location', 'multi-location-product-and-inventory-management'), __('Product Count', 'multi-location-product-and-inventory-management'), __('Percentage', 'multi-location-product-and-inventory-management')));
        $total_products = array_sum($product_counts);
        foreach ($locations_with_default as $loc_name) {
            $count = isset($product_counts[$loc_name]) ? $product_counts[$loc_name] : 0;
            $percentage = $total_products > 0 ? round(($count / $total_products) * 100, 2) : 0;
            fputcsv($output, array($loc_name, $count, $percentage . '%'));
        }
        fputcsv($output, ['']);

        // ---------- Stock Levels by Location (incl. Default) ----------
        fputcsv($output, [__('STOCK LEVELS BY LOCATION', 'multi-location-product-and-inventory-management')]);
        fputcsv($output, array(__('Location', 'multi-location-product-and-inventory-management'), __('Stock Level', 'multi-location-product-and-inventory-management'), __('Percentage', 'multi-location-product-and-inventory-management')));

        $total_stocks = array_sum($stock_levels);
        foreach ($locations_with_default as $loc_name) {
            $count = isset($stock_levels[$loc_name]) ? $stock_levels[$loc_name] : 0;
            $percentage = $total_stocks > 0 ? round(($count / $total_stocks) * 100, 2) : 0;
            fputcsv($output, array($loc_name, $count, $percentage . '%'));
        }
        fputcsv($output, ['']);

        // ---------- Orders by Location ----------
        fputcsv($output, [__('Orders by Location', 'multi-location-product-and-inventory-management')]);
        fputcsv($output, array(__('Location', 'multi-location-product-and-inventory-management'), __('Orders', 'multi-location-product-and-inventory-management'), __('Percentage', 'multi-location-product-and-inventory-management')));
        $total_orders = array_sum($orders_data['orders']);
        foreach ($orders_data['orders'] as $location => $orders) {
            $percentage = $total_orders > 0 ? round(($orders / $total_orders) * 100, 2) : 0;
            fputcsv($output, array($location, $orders, $percentage . '%'));
        }
        fputcsv($output, ['']);

        // ---------- Revenue by Location ----------
        fputcsv($output, [__('Revenue by Location', 'multi-location-product-and-inventory-management')]);
        fputcsv($output, array(__('Location', 'multi-location-product-and-inventory-management'), __('Revenue', 'multi-location-product-and-inventory-management'), __('Percentage', 'multi-location-product-and-inventory-management')));
        $total_revenue = array_sum($orders_data['revenue']);
        foreach ($orders_data['revenue'] as $location => $revenue) {
            $percentage = $total_revenue > 0 ? round(($revenue / $total_revenue) * 100, 2) : 0;
            fputcsv($output, array($location, number_format($revenue, 2), $percentage . '%'));
        }
        fputcsv($output, ['']);

        $profitability_data = $this->get_location_profitability_data();
        if (!empty($profitability_data)) {
            fputcsv($output, [__('PROFITABILITY & AGING BY LOCATION', 'multi-location-product-and-inventory-management')]);
            fputcsv($output, [
                __('Location', 'multi-location-product-and-inventory-management'),
                __('Inventory Value', 'multi-location-product-and-inventory-management'),
                __('Margin Value', 'multi-location-product-and-inventory-management'),
                __('Margin %', 'multi-location-product-and-inventory-management'),
                __('Dead Stock Value', 'multi-location-product-and-inventory-management'),
                __('Dead Stock Units', 'multi-location-product-and-inventory-management'),
                __('Avg Age (days)', 'multi-location-product-and-inventory-management'),
                __('Shrinkage %', 'multi-location-product-and-inventory-management')
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
            fputcsv($output, [__('LOW STOCK PRODUCTS', 'multi-location-product-and-inventory-management')]);
            fputcsv($output, [
                __('Product', 'multi-location-product-and-inventory-management'),
                __('Location', 'multi-location-product-and-inventory-management'),
                __('Stock', 'multi-location-product-and-inventory-management'),
                __('Status', 'multi-location-product-and-inventory-management')
            ]);
            foreach ($low_stock_products as $item) {
                $status = $item['stock'] == 0 ? __('⚠ Out of Stock', 'multi-location-product-and-inventory-management') : __('⚡ Low Stock', 'multi-location-product-and-inventory-management');
                fputcsv($output, [$item['product_title'], $item['location_name'], $item['stock'], $status]);
            }
            fputcsv($output, ['']);
        }

        // ---------- New Products (Last 30 Days) — location-wise ----------
        // Header row: Date | <each location> | Default | Total Added
        fputcsv($output, [__('NEW PRODUCTS (LAST 30 DAYS) — LOCATION-WISE', 'multi-location-product-and-inventory-management')]);
        $header = array_merge([__('Date', 'multi-location-product-and-inventory-management')], $locations, ['Default', __('Total Added', 'multi-location-product-and-inventory-management')]);
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
        fputcsv($output, array(__('End of Report', 'multi-location-product-and-inventory-management')));
        fputcsv($output, array(__('Thank you for using Multi Location Product & Inventory Management for WooCommerce Pro!', 'multi-location-product-and-inventory-management')));
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
            wp_send_json_error(array('message' => __('Permission denied', 'multi-location-product-and-inventory-management')));
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

        // Start HTML output with UTF-8 BOM
        echo chr(0xEF) . chr(0xBB) . chr(0xBF);

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
                            <?php echo esc_html__('LOCATION WISE PRODUCT & INVENTORY DASHBOARD REPORT', 'multi-location-product-and-inventory-management'); ?>
                        </h1>
                        <div style="font-size: 10pt; color: #ffffff; line-height: 1.5;">
                            <div><strong><?php echo esc_html__('Generated:', 'multi-location-product-and-inventory-management'); ?></strong> <?php echo gmdate('l, F d, Y - H:i:s'); ?></div>
                            <div><strong><?php echo esc_html__('Store:', 'multi-location-product-and-inventory-management'); ?></strong> <?php echo esc_html(get_bloginfo('name')); ?></div>
                            <div><strong><?php echo esc_html__('Currency:', 'multi-location-product-and-inventory-management'); ?></strong> <?php echo esc_html($this->get_dashboard_reporting_currency_code()); ?></div>
                        </div>
                    </td>
                </tr>
            </table>

            <!-- Summary Statistics -->
            <table style="width: 100%; margin-bottom: 10px; border-collapse: collapse;">
                <tr></tr>
                <tr>
                    <td style="background-color: #5b21b6; color: #ffffff; padding: 10px 15px; font-weight: bold; font-size: 12pt;">
                        <?php echo esc_html__('SUMMARY STATISTICS', 'multi-location-product-and-inventory-management'); ?>
                    </td>
                </tr>
            </table>
            <table class="summary-table">
                <tr>
                    <th><?php echo esc_html__('Metric', 'multi-location-product-and-inventory-management'); ?></th>
                    <th><?php echo esc_html__('Value', 'multi-location-product-and-inventory-management'); ?></th>
                </tr>
                <tr>
                    <td><?php echo esc_html__('Total Products', 'multi-location-product-and-inventory-management'); ?></td>
                    <td><?php echo esc_html(number_format($this->get_total_products_count())); ?></td>
                </tr>
                <tr class="even-row">
                    <td><?php echo esc_html__('Total Locations', 'multi-location-product-and-inventory-management'); ?></td>
                    <td><?php echo esc_html(count($mulopimfwc_locations)); ?></td>
                </tr>
                <tr>
                    <td><?php echo esc_html__('Total Orders', 'multi-location-product-and-inventory-management'); ?></td>
                    <td><?php echo esc_html(number_format(array_sum($orders_data['orders']))); ?></td>
                </tr>
                <tr class="even-row">
                    <td><?php echo esc_html__('Total Revenue', 'multi-location-product-and-inventory-management'); ?></td>
                    <td><?php echo esc_html($this->get_dashboard_reporting_currency_symbol() . number_format(array_sum($orders_data['revenue']), 2)); ?></td>
                </tr>
                <tr>
                    <td><?php echo esc_html__('Total Investment', 'multi-location-product-and-inventory-management'); ?></td>
                    <td><?php echo esc_html($this->get_dashboard_reporting_currency_symbol() . number_format($total_investment, 2)); ?></td>
                </tr>
                <tr class="even-row">
                    <td><?php echo esc_html__('Total Stock', 'multi-location-product-and-inventory-management'); ?></td>
                    <td><?php echo esc_html(number_format(array_sum($stock_levels))); ?></td>
                </tr>
            </table>

            <!-- Products by Location -->
            <table style="width: 100%; margin-bottom: 10px; border-collapse: collapse;">
                <tr></tr>
                <tr>
                    <td style="background-color: #5b21b6; color: #ffffff; padding: 10px 15px; font-weight: bold; font-size: 12pt;">
                        <?php echo esc_html__('PRODUCTS BY LOCATION', 'multi-location-product-and-inventory-management'); ?>
                    </td>
                </tr>
            </table>
            <table>
                <tr>
                    <th><?php echo esc_html__('Location', 'multi-location-product-and-inventory-management'); ?></th>
                    <th><?php echo esc_html__('Product Count', 'multi-location-product-and-inventory-management'); ?></th>
                    <th><?php echo esc_html__('Percentage', 'multi-location-product-and-inventory-management'); ?></th>
                </tr>
                <?php
                $total_products = array_sum($product_counts);
                $row_count = 0;
                foreach ($locations_with_default as $loc_name) {
                    $count = isset($product_counts[$loc_name]) ? $product_counts[$loc_name] : 0;
                    $percentage = $total_products > 0 ? round(($count / $total_products) * 100, 2) : 0;
                    $row_class = ($row_count % 2 == 1) ? ' class="even-row"' : '';
                    echo '<tr' . $row_class . '>';
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
                        <?php echo esc_html__('STOCK LEVELS BY LOCATION', 'multi-location-product-and-inventory-management'); ?>
                    </td>
                </tr>
            </table>
            <table>
                <tr>
                    <th><?php echo esc_html__('Location', 'multi-location-product-and-inventory-management'); ?></th>
                    <th><?php echo esc_html__('Stock Level', 'multi-location-product-and-inventory-management'); ?></th>
                    <th><?php echo esc_html__('Percentage', 'multi-location-product-and-inventory-management'); ?></th>
                </tr>
                <?php
                $total_stocks = array_sum($stock_levels);
                $row_count = 0;
                foreach ($locations_with_default as $loc_name) {
                    $count = isset($stock_levels[$loc_name]) ? $stock_levels[$loc_name] : 0;
                    $percentage = $total_stocks > 0 ? round(($count / $total_stocks) * 100, 2) : 0;
                    $row_class = ($row_count % 2 == 1) ? ' class="even-row"' : '';
                    echo '<tr' . $row_class . '>';
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
                        <?php echo esc_html__('ORDERS BY LOCATION', 'multi-location-product-and-inventory-management'); ?>
                    </td>
                </tr>
            </table>
            <table>
                <tr>
                    <th><?php echo esc_html__('Location', 'multi-location-product-and-inventory-management'); ?></th>
                    <th><?php echo esc_html__('Orders', 'multi-location-product-and-inventory-management'); ?></th>
                    <th><?php echo esc_html__('Percentage', 'multi-location-product-and-inventory-management'); ?></th>
                </tr>
                <?php
                $total_orders = array_sum($orders_data['orders']);
                $row_count = 0;
                foreach ($orders_data['orders'] as $location => $orders) {
                    $percentage = $total_orders > 0 ? round(($orders / $total_orders) * 100, 2) : 0;
                    $row_class = ($row_count % 2 == 1) ? ' class="even-row"' : '';
                    echo '<tr' . $row_class . '>';
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
                        <?php echo esc_html__('Revenue by Location', 'multi-location-product-and-inventory-management'); ?>
                    </td>
                </tr>
            </table>
            <table>
                <tr>
                    <th><?php echo esc_html__('Location', 'multi-location-product-and-inventory-management'); ?></th>
                    <th><?php echo esc_html__('Revenue', 'multi-location-product-and-inventory-management'); ?></th>
                    <th><?php echo esc_html__('Percentage', 'multi-location-product-and-inventory-management'); ?></th>
                </tr>
                <?php
                $total_revenue = array_sum($orders_data['revenue']);
                $row_count = 0;
                foreach ($orders_data['revenue'] as $location => $revenue) {
                    $percentage = $total_revenue > 0 ? round(($revenue / $total_revenue) * 100, 2) : 0;
                    $row_class = ($row_count % 2 == 1) ? ' class="even-row"' : '';
                    echo '<tr' . $row_class . '>';
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
                            <?php echo esc_html__('PROFITABILITY & AGING BY LOCATION', 'multi-location-product-and-inventory-management'); ?>
                        </td>
                    </tr>
                </table>
                <table>
                    <tr>
                        <th><?php echo esc_html__('Location', 'multi-location-product-and-inventory-management'); ?></th>
                        <th><?php echo esc_html__('Inventory Value', 'multi-location-product-and-inventory-management'); ?></th>
                        <th><?php echo esc_html__('Margin Value', 'multi-location-product-and-inventory-management'); ?></th>
                        <th><?php echo esc_html__('Margin %', 'multi-location-product-and-inventory-management'); ?></th>
                        <th><?php echo esc_html__('Dead Stock Value', 'multi-location-product-and-inventory-management'); ?></th>
                        <th><?php echo esc_html__('Dead Stock Units', 'multi-location-product-and-inventory-management'); ?></th>
                        <th><?php echo esc_html__('Avg Age (days)', 'multi-location-product-and-inventory-management'); ?></th>
                        <th><?php echo esc_html__('Shrinkage %', 'multi-location-product-and-inventory-management'); ?></th>
                    </tr>
                    <?php
                    $row_count = 0;
                    foreach ($profitability_data_export as $summary) :
                        $row_class = ($row_count % 2 == 1) ? ' class="even-row"' : '';
                    ?>
                        <tr<?php echo $row_class; ?>>
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
                            <?php echo esc_html__('LOW STOCK PRODUCTS', 'multi-location-product-and-inventory-management'); ?>
                        </td>
                    </tr>
                </table>
                <table>
                    <tr>
                        <th><?php echo esc_html__('Product', 'multi-location-product-and-inventory-management'); ?></th>
                        <th><?php echo esc_html__('Location', 'multi-location-product-and-inventory-management'); ?></th>
                        <th><?php echo esc_html__('Stock', 'multi-location-product-and-inventory-management'); ?></th>
                        <th><?php echo esc_html__('Status', 'multi-location-product-and-inventory-management'); ?></th>
                    </tr>
                    <?php
                    $row_count = 0;
                    foreach ($low_stock_products as $item) :
                        $row_class = ($row_count % 2 == 1) ? ' class="even-row"' : '';
                    ?>
                        <tr<?php echo $row_class; ?>>
                            <td><?php echo esc_html($item['product_title']); ?></td>
                            <td><?php echo esc_html($item['location_name']); ?></td>
                            <td class="<?php echo $item['stock'] == 0 ? 'stock-out' : 'stock-low'; ?>">
                                <?php echo esc_html($item['stock']); ?>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $item['stock'] == 0 ? 'status-out' : 'status-low'; ?>">
                                    <?php echo $item['stock'] == 0 ? esc_html__('Out of Stock', 'multi-location-product-and-inventory-management') : esc_html__('Low Stock', 'multi-location-product-and-inventory-management'); ?>
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
                        <?php echo esc_html__('NEW PRODUCTS (LAST 30 DAYS) - LOCATION-WISE', 'multi-location-product-and-inventory-management'); ?>
                    </td>
                </tr>
            </table>
            <table>
                <tr>
                    <th><?php echo esc_html__('Date', 'multi-location-product-and-inventory-management'); ?></th>
                    <?php foreach ($locations as $loc_name) : ?>
                        <th><?php echo esc_html($loc_name); ?></th>
                    <?php endforeach; ?>
                    <th><?php echo esc_html__('Default', 'multi-location-product-and-inventory-management'); ?></th>
                    <th><?php echo esc_html__('Total Added', 'multi-location-product-and-inventory-management'); ?></th>
                </tr>
                <?php
                $row_count = 0;
                foreach ($recent_matrix['labels'] as $i => $label) :
                    $row_class = ($row_count % 2 == 1) ? ' class="even-row"' : '';
                ?>
                    <tr<?php echo $row_class; ?>>
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
            'message' => __('An error occurred while generating the export. Please try again.', 'multi-location-product-and-inventory-management'),
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
        wp_enqueue_script('chart-js', plugin_dir_url(__FILE__) . '../assets/js/chart.min.js', array(), '3.9.1', true);
        wp_enqueue_script('lwp-dashboard-js', plugin_dir_url(__FILE__) . '../assets/js/dashboard.js', array('jquery', 'chart-js'), "1.1.4.18", true);
        wp_enqueue_style('lwp-dashboard-css', plugin_dir_url(__FILE__) . '../assets/css/dashboard.css', array(), "1.1.4.18");

        $payload = $this->build_dashboard_payload();

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

        wp_localize_script('lwp-dashboard-js', 'mulopimfwc_DashboardData', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'export_nonce' => wp_create_nonce('mulopimfwc_export_nonce'),
            'dashboard_nonce' => wp_create_nonce('mulopimfwc_dashboard_nonce'),
            'realtime_nonce' => wp_create_nonce('mulopimfwc_dashboard_realtime_nonce'),
            'poll_interval' => $poll_interval,
            'productCounts' => $payload['product_counts'],
            'stockLevels' => $payload['stock_levels'],
            'locationColors' => $payload['location_colors'],
            'locationBorderColors' => $payload['location_border_colors'],
            'dateLabels' => $payload['recent_products_data']['labels'],
            'dateCounts' => mulopimfwc_get_pro_class(false, $payload['recent_products_data']['counts'], array_map(fn() => random_int(1, 100), range(1, 30))),
            'ordersByLocation' => mulopimfwc_get_pro_class(false, $payload['orders_by_location'], $dummydata),
            'revenueByLocation' => mulopimfwc_get_pro_class(false, $payload['revenue_by_location'], $dummydata),
            'monthlyInvestmentLabels' => $payload['monthly_investment_data']['labels'],
            'monthlyInvestmentData' => mulopimfwc_get_pro_class(
                false,
                $payload['monthly_investment_data']['data'],
                array_fill(0, count($payload['monthly_investment_data']['data']), 0)
            ),
            'profitabilityByLocation' => $payload['profitability_by_location'],
            'deadStockDays' => $payload['dead_stock_days'],
            'currency' => $this->get_dashboard_reporting_currency_symbol(),
            'currency_code' => $this->get_dashboard_reporting_currency_code(),
            'summary' => $payload['summary'],
            'lowStock' => array_slice($payload['low_stock_products'], 0, 8),
            'i18n' => [
                'totalStock' => __('Total Stock', 'multi-location-product-and-inventory-management'),
                'newProducts' => __('New Products', 'multi-location-product-and-inventory-management'),
                'investment' => __('Investment', 'multi-location-product-and-inventory-management'),
                'orders' => __('Orders', 'multi-location-product-and-inventory-management'),
                'revenue' => __('Revenue', 'multi-location-product-and-inventory-management'),
                'previousPeriod' => __('Previous period', 'multi-location-product-and-inventory-management'),
                'noOrders' => __('No orders yet', 'multi-location-product-and-inventory-management')
            ]
        ]);

        $orders_data          = $this->get_orders_data_efficiently();
        $total_investment     = $this->calculate_total_investment_efficiently();
        
        // Extract profitability data for template
        $profitability_by_location = isset($payload['profitability_by_location']) ? $payload['profitability_by_location'] : [];

    ?>
        <div class="wrap lwp-dashboard">
            <h1 style="display: none !important;"><?php echo esc_html__('Location Wise Products Dashboard', 'multi-location-product-and-inventory-management'); ?></h1>
            <div class="lwp-dashboard-overview">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <h1><?php echo esc_html__('Location Wise Products Dashboard', 'multi-location-product-and-inventory-management'); ?></h1>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <!-- Filter Toggle Button -->
                        <button class="mulopimfwc-btn-secondary filter_toggle_btn" style="padding: 10px 20px !important;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                            </svg>
                            <?php echo esc_html__('Filters', 'multi-location-product-and-inventory-management'); ?>
                        </button>

                        <!-- Export Dropdown -->
                        <?php if ($can_export_report) : ?>
                        <div class="export_report_dropdown <?php echo esc_attr(mulopimfwc_get_pro_class(false)); ?>">
                            <button class="mulopimfwc-btn-primary export_toggle_btn" style="padding: 10px 30px !important;">
                                <svg width="16" height="16" viewBox="0 0 0.48 0.48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M.226.046a.02.02 0 0 1 .028 0l.08.08a.02.02 0 0 1-.028.028L.26.108V.32a.02.02 0 1 1-.04 0V.108L.174.154A.02.02 0 0 1 .146.126zM.1.34a.02.02 0 0 1 .02.02V.4h.24V.36a.02.02 0 1 1 .04 0V.4a.04.04 0 0 1-.04.04H.12A.04.04 0 0 1 .08.4V.36A.02.02 0 0 1 .1.34" />
                                </svg>
                                <?php echo esc_html__('Export Report', 'multi-location-product-and-inventory-management'); ?>
                                <span class="dropdown_icon">▾</span>
                            </button>

                            <div class="dropdown_menu">
                                <button class="<?php echo esc_attr(mulopimfwc_get_pro_class(false, 'export_report')); ?>" id="export_report_csv" data-format="csv">
                                    <?php echo esc_html__('Export in CSV', 'multi-location-product-and-inventory-management'); ?>
                                </button>

                                <button class="<?php echo esc_attr(mulopimfwc_get_pro_class(false, 'export_report')); ?>" id="export_report_html" data-format="html">
                                    <?php echo esc_html__('Export in Excel (HTML)', 'multi-location-product-and-inventory-management'); ?>
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
                            <?php echo esc_html__('Filter Dashboard', 'multi-location-product-and-inventory-management'); ?>
                        </h3>

                        <div class="lwp-filters-wrapper">
                            <div class="lwp-filters-grid">
                                <!-- Date Range Filter -->
                                <div class="lwp-filter-group">
                                    <label for="filter-date-from">
                                        <?php echo esc_html__('Date From', 'multi-location-product-and-inventory-management'); ?>
                                    </label>
                                    <input type="date" id="filter-date-from" class="lwp-filter-input" />
                                </div>

                                <div class="lwp-filter-group">
                                    <label for="filter-date-to">
                                        <?php echo esc_html__('Date To', 'multi-location-product-and-inventory-management'); ?>
                                    </label>
                                    <input type="date" id="filter-date-to" class="lwp-filter-input" />
                                </div>

                                <!-- Location Filter -->
                                <div class="lwp-filter-group">
                                    <label for="filter-location">
                                        <?php echo esc_html__('Location', 'multi-location-product-and-inventory-management'); ?>
                                    </label>
                                    <select id="filter-location" class="lwp-filter-input">
                                        <option value="all"><?php echo esc_html__('All Locations', 'multi-location-product-and-inventory-management'); ?></option>
                                        <?php foreach ($mulopimfwc_locations as $location): ?>
                                            <option value="<?php echo esc_attr(rawurldecode($location->slug)); ?>">
                                                <?php echo esc_html($location->name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                        <?php if (!$is_location_manager): ?>
                                            <option value="default"><?php echo esc_html__('Default', 'multi-location-product-and-inventory-management'); ?></option>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <!-- Order Status Filter -->
                                <div class="lwp-filter-group">
                                    <label for="filter-status">
                                        <?php echo esc_html__('Order Status', 'multi-location-product-and-inventory-management'); ?>
                                    </label>
                                    <select id="filter-status" class="lwp-filter-input">
                                        <option value="all"><?php echo esc_html__('All Statuses', 'multi-location-product-and-inventory-management'); ?></option>
                                        <option value="completed"><?php echo esc_html__('Completed', 'multi-location-product-and-inventory-management'); ?></option>
                                        <option value="processing"><?php echo esc_html__('Processing', 'multi-location-product-and-inventory-management'); ?></option>
                                        <option value="pending"><?php echo esc_html__('Pending', 'multi-location-product-and-inventory-management'); ?></option>
                                        <option value="on-hold"><?php echo esc_html__('On Hold', 'multi-location-product-and-inventory-management'); ?></option>
                                        <option value="cancelled"><?php echo esc_html__('Cancelled', 'multi-location-product-and-inventory-management'); ?></option>
                                        <option value="refunded"><?php echo esc_html__('Refunded', 'multi-location-product-and-inventory-management'); ?></option>
                                        <option value="failed"><?php echo esc_html__('Failed', 'multi-location-product-and-inventory-management'); ?></option>
                                    </select>
                                </div>
                            </div>

                            <!-- Quick Date Range Buttons -->
                            <div class="lwp-filter-group lwp-quick-filters">
                                <label><?php echo esc_html__('Quick Select', 'multi-location-product-and-inventory-management'); ?></label>
                                <div class="lwp-quick-buttons">
                                    <button type="button" class="lwp-quick-btn" data-days="7">
                                        <?php echo esc_html__('Last 7 Days', 'multi-location-product-and-inventory-management'); ?>
                                    </button>
                                    <button type="button" class="lwp-quick-btn" data-days="30">
                                        <?php echo esc_html__('Last 30 Days', 'multi-location-product-and-inventory-management'); ?>
                                    </button>
                                    <button type="button" class="lwp-quick-btn" data-days="90">
                                        <?php echo esc_html__('Last 90 Days', 'multi-location-product-and-inventory-management'); ?>
                                    </button>
                                    <button type="button" class="lwp-quick-btn" data-period="this-month">
                                        <?php echo esc_html__('This Month', 'multi-location-product-and-inventory-management'); ?>
                                    </button>
                                    <button type="button" class="lwp-quick-btn" data-period="last-month">
                                        <?php echo esc_html__('Last Month', 'multi-location-product-and-inventory-management'); ?>
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
                                <?php echo esc_html__('Apply Filters', 'multi-location-product-and-inventory-management'); ?>
                            </button>
                            <button type="button" id="reset-filters" class="lwp-btn-secondary">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path>
                                    <path d="M21 3v5h-5"></path>
                                    <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path>
                                    <path d="M3 21v-5h5"></path>
                                </svg>
                                <?php echo esc_html__('Reset', 'multi-location-product-and-inventory-management'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <div id="lwp-loading-overlay" style="display: none;">
                    <div class="lwp-spinner"></div>
                    <p><?php echo esc_html__('Loading data...', 'multi-location-product-and-inventory-management'); ?></p>
                </div>
                <div class="lwp-card-stats">
                    <div class="lwp-stats-grid">
                        <?php
                        $products_tag = $products_link ? 'a' : 'div';
                        $products_attrs = $products_link ? ' href="' . esc_url($products_link) . '"' : '';
                        $locations_tag = $locations_link ? 'a' : 'div';
                        $locations_attrs = $locations_link ? ' href="' . esc_url($locations_link) . '"' : '';
                        $orders_tag = $orders_link ? 'a' : 'div';
                        $orders_attrs = $orders_link ? ' href="' . esc_url($orders_link) . '"' : '';
                        ?>
                        <<?php echo $products_tag; ?> class="lwp-stat-item"<?php echo $products_attrs; ?>>
                            <div class="lwp-stat-item-icon">

                                <svg class="svg-inline--fa fa-box" aria-hidden="true" data-prefix="fas" data-icon="box" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" width="18" height="18">
                                    <path fill="#2563eb" d="M50.7 58.5 0 160h208V32H93.7c-18.2 0-34.8 10.3-43 26.5M240 160h208L397.3 58.5c-8.2-16.2-24.8-26.5-43-26.5H240zm208 32H0v224c0 35.3 28.7 64 64 64h320c35.3 0 64-28.7 64-64z" />
                                </svg>
                            </div>
                            <div>
                                <span class="lwp-stat-progress" data-metric="products"></span>
                                <span class="lwp-stat-label"><?php echo esc_html__('Total Products', 'multi-location-product-and-inventory-management'); ?></span>
                                <span class="lwp-stat-value"><?php echo esc_html($this->get_total_products_count()); ?></span>
                            </div>
                        </<?php echo $products_tag; ?>>
                        <<?php echo $locations_tag; ?> class="lwp-stat-item"<?php echo $locations_attrs; ?>>
                            <div class="lwp-stat-item-icon" style="background-color: #dcfce7;">

                                <svg class="svg-inline--fa fa-location-dot" aria-hidden="true" data-prefix="fas" data-icon="location-dot" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512" width="18" height="18">
                                    <path fill="#16a34a" d="M215.7 499.2C267 435 384 279.4 384 192 384 86 298 0 192 0S0 86 0 192c0 87.4 117 243 168.3 307.2 12.3 15.3 35.1 15.3 47.4 0M192 128a64 64 0 1 1 0 128 64 64 0 1 1 0-128" />
                                </svg>
                            </div>
                            <div>
                                <span class="lwp-stat-progress" data-metric="locations"></span>
                                <span class="lwp-stat-label"><?php echo esc_html__('Locations', 'multi-location-product-and-inventory-management'); ?></span>
                                <span class="lwp-stat-value"><?php echo count($mulopimfwc_locations); ?></span>

                            </div>

                        </<?php echo $locations_tag; ?>>
                        <<?php echo $orders_tag; ?> class="lwp-stat-item <?php echo esc_attr(mulopimfwc_get_pro_class()); ?>"<?php echo $orders_attrs; ?>>
                            <div class="lwp-stat-item-icon" style="background-color: #f3e8ff;">

                                <svg class="svg-inline--fa fa-cart-shopping" aria-hidden="true" data-prefix="fas" data-icon="cart-shopping" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512" width="18" height="18">
                                    <path fill="#9333ea" d="M0 24C0 10.7 10.7 0 24 0h45.5c22 0 41.5 12.8 50.6 32h411c26.3 0 45.5 25 38.6 50.4l-41 152.3c-8.5 31.4-37 53.3-69.5 53.3H170.7l5.4 28.5c2.2 11.3 12.1 19.5 23.6 19.5H488c13.3 0 24 10.7 24 24s-10.7 24-24 24H199.7c-34.6 0-64.3-24.6-70.7-58.5l-51.6-271c-.7-3.8-4-6.5-7.9-6.5H24C10.7 48 0 37.3 0 24m128 440a48 48 0 1 1 96 0 48 48 0 1 1-96 0m336-48a48 48 0 1 1 0 96 48 48 0 1 1 0-96" />
                                </svg>
                            </div>
                            <div>
                                <span class="lwp-stat-progress" data-metric="orders"></span>
                                <span class="lwp-stat-label"><?php echo esc_html__('Orders', 'multi-location-product-and-inventory-management'); ?></span>
                                <span class="lwp-stat-value"><?php echo esc_html(mulopimfwc_get_pro_class(false, array_sum($orders_data["orders"]), rand(1, 100))); ?></span>

                            </div>

                        </<?php echo $orders_tag; ?>>
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
                                <span class="lwp-stat-label"><?php echo esc_html__('Total Investment', 'multi-location-product-and-inventory-management'); ?></span>
                                <span class="lwp-stat-value"><?php echo wp_kses_post($this->format_dashboard_reporting_price($total_investment)); ?></span>

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
                                <span class="lwp-stat-label"><?php echo esc_html__('Revenue', 'multi-location-product-and-inventory-management'); ?></span>
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
                            <h2><?php echo esc_html__('Products by Location', 'multi-location-product-and-inventory-management'); ?></h2>
                            <div class="lwp-chart-container">
                                <canvas id="locationProductsChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="lwp-col">
                        <div class="lwp-card">
                            <h2><?php echo esc_html__('Stock Levels by Location', 'multi-location-product-and-inventory-management'); ?></h2>
                            <div class="lwp-chart-container">
                                <canvas id="locationStockChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lwp-row">
                    <div class="lwp-col">
                        <div class="lwp-card">
                            <h2><?php echo esc_html__('Orders by Location', 'multi-location-product-and-inventory-management'); ?></h2>
                            <div class="lwp-chart-container <?php echo esc_attr(mulopimfwc_get_pro_class()); ?>">
                                <canvas id="ordersByLocationChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="lwp-col">
                        <div class="lwp-card">
                            <h2><?php echo esc_html__('Revenue by Location', 'multi-location-product-and-inventory-management'); ?></h2>
                            <div class="lwp-chart-container <?php echo esc_attr(mulopimfwc_get_pro_class()); ?>">
                                <canvas id="revenueByLocationChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lwp-row">
                    <div class="lwp-col">
                        <div class="lwp-card">
                            <h2><?php echo esc_html__('New Products (Last 30 Days)', 'multi-location-product-and-inventory-management'); ?></h2>
                            <div class="lwp-chart-container <?php echo esc_attr(mulopimfwc_get_pro_class()); ?>">
                                <canvas id="newProductsChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="lwp-col">
                        <div class="lwp-card">
                            <h2><?php echo esc_html__('Investment', 'multi-location-product-and-inventory-management'); ?></h2>
                            <div class="lwp-chart-container <?php echo esc_attr(mulopimfwc_get_pro_class()); ?>">
                                <canvas id="investment-30day"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lwp-row">
                    <div class="lwp-col">
                        <div class="lwp-card">
                            <h2><?php esc_html_e('Stock Alerts by Location', 'multi-location-product-and-inventory-management'); ?></h2>
                            <?php
                            global $mulopimfwc_options;
                            $low_stock_products   = $this->get_low_stock_products_efficiently();
                            $low_stock_sample_products = [
                                [
                                    "product_id" => 1,
                                    "product_title" => "Apple Laptop",
                                    "location_name" => "New York",
                                    "stock" => 0
                                ],
                                [
                                    "product_id" => 2,
                                    "product_title" => "Samsung Galaxy Phone",
                                    "location_name" => "Los Angeles",
                                    "stock" => 2
                                ],
                                [
                                    "product_id" => 3,
                                    "product_title" => "Dell XPS 15",
                                    "location_name" => "Chicago",
                                    "stock" => 1
                                ],
                                [
                                    "product_id" => 4,
                                    "product_title" => "Sony Headphones",
                                    "location_name" => "Miami",
                                    "stock" => 0
                                ],
                                [
                                    "product_id" => 5,
                                    "product_title" => "LG OLED TV",
                                    "location_name" => "Houston",
                                    "stock" => 3
                                ],
                                [
                                    "product_id" => 6,
                                    "product_title" => "Amazon Echo",
                                    "location_name" => "Seattle",
                                    "stock" => 1
                                ],
                                [
                                    "product_id" => 7,
                                    "product_title" => "Microsoft Surface Pro",
                                    "location_name" => "San Francisco",
                                    "stock" => 2
                                ],
                                [
                                    "product_id" => 8,
                                    "product_title" => "Bose SoundLink Speaker",
                                    "location_name" => "Boston",
                                    "stock" => 0
                                ],
                                [
                                    "product_id" => 9,
                                    "product_title" => "iPad Pro",
                                    "location_name" => "Atlanta",
                                    "stock" => 1
                                ],
                                [
                                    "product_id" => 10,
                                    "product_title" => "Fitbit Charge 5",
                                    "location_name" => "Denver",
                                    "stock" => 0
                                ]
                            ];

                            $low_stock_products = mulopimfwc_get_pro_class(false, $low_stock_products, $low_stock_sample_products);
                            $out_of_stock_threshold = isset($mulopimfwc_options['out_of_stock_threshold']) ? (int) $mulopimfwc_options['out_of_stock_threshold'] : 0;

                            if (!empty($low_stock_products)) : ?>
                                <table class="lwp-low-stock-table <?php echo esc_attr(mulopimfwc_get_pro_class()); ?>">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('Product', 'multi-location-product-and-inventory-management'); ?></th>
                                            <th><?php esc_html_e('Location', 'multi-location-product-and-inventory-management'); ?></th>
                                            <th><?php esc_html_e('Stock', 'multi-location-product-and-inventory-management'); ?></th>
                                            <th><?php esc_html_e('Status', 'multi-location-product-and-inventory-management'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ((object)$low_stock_products as $item) : ?>
                                            <tr>
                                                <td>
                                                    <?php if ($can_manage_products) : ?>
                                                        <a href="<?php echo esc_url(get_edit_post_link($item['product_id'])); ?>">
                                                            <?php echo esc_html($item['product_title']); ?>
                                                        </a>
                                                    <?php else : ?>
                                                        <?php echo esc_html($item['product_title']); ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo esc_html($item['location_name']); ?></td>
                                                <td>
                                                    <span class="stock-quantity <?php echo (int) $item['stock'] <= $out_of_stock_threshold ? 'out-of-stock' : 'low-stock'; ?>">
                                                        <?php echo esc_html($item['stock']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="stock-status <?php echo (int) $item['stock'] <= $out_of_stock_threshold ? 'out-of-stock' : 'low-stock'; ?>">
                                                        <?php
                                                        if ((int) $item['stock'] <= $out_of_stock_threshold) {
                                                            esc_html_e('Out of Stock', 'multi-location-product-and-inventory-management');
                                                        } else {
                                                            esc_html_e('Low Stock', 'multi-location-product-and-inventory-management');
                                                        }
                                                        ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p><?php esc_html_e('No low stock products found for any location.', 'multi-location-product-and-inventory-management'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="lwp-row">
                    <div class="lwp-col">
                        <div class="lwp-card">
                            <h2><?php esc_html_e('Profitability & Aging by Location', 'multi-location-product-and-inventory-management'); ?></h2>
                            <p class="lwp-profitability-description">
                                <?php
                                $dead_stock_days = 90;
                                printf(
                                    esc_html__('Dead stock in this table represents inventory that has not sold in the last %s days. Shrinkage reflects any sold quantity that could not be matched to current stock records.', 'multi-location-product-and-inventory-management'),
                                    esc_html($dead_stock_days)
                                );
                                ?>
                            </p>
                            <?php if (!empty($profitability_by_location)) : ?>
                                <div class="lwp-table-responsive">
                                    <table class="lwp-profitability-table">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e('Location', 'multi-location-product-and-inventory-management'); ?></th>
                                                <th><?php esc_html_e('Inventory Value', 'multi-location-product-and-inventory-management'); ?></th>
                                                <th><?php esc_html_e('Margin Value', 'multi-location-product-and-inventory-management'); ?></th>
                                                <th><?php esc_html_e('Margin %', 'multi-location-product-and-inventory-management'); ?></th>
                                                <th><?php esc_html_e('Dead Stock Value', 'multi-location-product-and-inventory-management'); ?></th>
                                                <th><?php esc_html_e('Dead Stock Units', 'multi-location-product-and-inventory-management'); ?></th>
                                                <th><?php esc_html_e('Avg. Age (days)', 'multi-location-product-and-inventory-management'); ?></th>
                                                <th><?php esc_html_e('Shrinkage %', 'multi-location-product-and-inventory-management'); ?></th>
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
                                                    <td><?php echo esc_html(number_format($summary['dead_stock_units'], 0)); ?></td>
                                                    <td><?php echo esc_html(number_format((float) $summary['average_age_days'], 1)); ?></td>
                                                    <td><?php echo esc_html(number_format((float) $summary['shrinkage_rate'], 1)); ?>%</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else : ?>
                                <p><?php esc_html_e('No profitability data available yet.', 'multi-location-product-and-inventory-management'); ?></p>
                            <?php endif; ?>
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
            wp_send_json_error(array('message' => __('Permission denied', 'multi-location-product-and-inventory-management')));
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
        if (is_array($location_raw)) {
            $location_raw = reset($location_raw);
        }
        $location_filter_raw = is_string($location_raw) ? sanitize_text_field(rawurldecode($location_raw)) : 'all';
        $location_filter = $this->normalize_location_slug($location_filter_raw);
        if ($location_filter === '') {
            $location_filter = 'all';
        }
        $status_raw = isset($_POST['status']) ? wp_unslash($_POST['status']) : 'all';
        if (is_array($status_raw)) {
            $status_raw = reset($status_raw);
        }
        $status_filter = is_string($status_raw) ? sanitize_text_field($status_raw) : 'all';
        $manager_location_slugs = $this->get_dashboard_manager_assigned_location_slugs();

        if (is_array($manager_location_slugs)) {
            if (empty($manager_location_slugs)) {
                $location_filter = '__none__';
            } elseif ($location_filter === 'default') {
                $location_filter = '__none__';
            } elseif ($location_filter !== 'all' && !in_array($location_filter, $manager_location_slugs, true)) {
                $location_filter = '__none__';
            }
        }

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

            $current_products_total = array_sum($recent_products_data['counts']);
            $previous_products_total = array_sum($previous_products_data['counts']);
            $current_locations_total = $this->count_active_locations($orders_data['orders'], $include_default);
            $previous_locations_total = $this->count_active_locations($previous_orders_data['orders'], $include_default);
            $current_investment_total = array_sum($investment_data['totals']);
            $previous_investment_total = array_sum($previous_investment_data['totals']);

            $comparison['products'] = $this->build_comparison_metric($current_products_total, $previous_products_total);
            $comparison['locations'] = $this->build_comparison_metric($current_locations_total, $previous_locations_total);
            $comparison['investment'] = $this->build_comparison_metric($current_investment_total, $previous_investment_total);
        }

        $summary = array(
            'total_orders' => array_sum($orders_data['orders']),
            'total_revenue' => array_sum($orders_data['revenue']),
        );

        if (!empty($period)) {
            $summary['total_products'] = array_sum($recent_products_data['counts']);
            $summary['total_locations'] = $this->count_active_locations($orders_data['orders'], $include_default);
            $summary['total_investment'] = array_sum($investment_data['totals']);
        } else {
            if (empty($mulopimfwc_locations) || is_wp_error($mulopimfwc_locations)) {
                $mulopimfwc_locations = [];
            }
            $summary['total_products'] = $this->get_total_products_count();
            $summary['total_locations'] = count($mulopimfwc_locations);
            $summary['total_investment'] = $this->calculate_total_investment_efficiently();
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
     * Build comparison metrics for the previous period.
     */
    private function build_period_comparison(array $period, array $current_orders_data, array $previous_orders_data)
    {
        $current_orders_total = array_sum($current_orders_data['orders']);
        $current_revenue_total = array_sum($current_orders_data['revenue']);
        $previous_orders_total = array_sum($previous_orders_data['orders']);
        $previous_revenue_total = array_sum($previous_orders_data['revenue']);

        return [
            'label' => $period['label'],
            'period_days' => $period['days'],
            'previous_range' => [
                'from' => $period['previous_start']->format('Y-m-d'),
                'to' => $period['previous_end']->format('Y-m-d'),
            ],
            'orders' => $this->build_comparison_metric($current_orders_total, $previous_orders_total),
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
            _n('vs previous %d day', 'vs previous %d days', $days, 'multi-location-product-and-inventory-management'),
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
     * Get orders data efficiently
     */
    private function get_orders_data_efficiently($date_from = '', $date_to = '', $location_filter = 'all', $status_filter = 'all')
    {
        global $mulopimfwc_locations;

        $normalized_location_filter = $this->normalize_location_slug($location_filter);
        $manager_location_slugs = $this->get_dashboard_manager_assigned_location_slugs();
        $is_manager_scope = is_array($manager_location_slugs);
        $orders_by_location = $is_manager_scope ? [] : ['Default' => 0];
        $location_revenue = $is_manager_scope ? [] : ['Default' => 0];
        $location_slugs = $is_manager_scope ? [] : ['Default' => 'default'];

        if ($is_manager_scope) {
            if (empty($manager_location_slugs)) {
                return [
                    'orders' => $orders_by_location,
                    'revenue' => $location_revenue,
                ];
            }

            if ($normalized_location_filter === 'default') {
                return [
                    'orders' => $orders_by_location,
                    'revenue' => $location_revenue,
                ];
            }

            if (
                $normalized_location_filter !== 'all' &&
                !in_array($normalized_location_filter, $manager_location_slugs, true)
            ) {
                return [
                    'orders' => $orders_by_location,
                    'revenue' => $location_revenue,
                ];
            }
        }

        $revenue_statuses = function_exists('mulopimfwc_get_revenue_order_statuses')
            ? mulopimfwc_get_revenue_order_statuses()
            : ['processing', 'completed'];
        $calculate_revenue = function_exists('mulopimfwc_calculate_order_revenue') ? 'mulopimfwc_calculate_order_revenue' : null;

        foreach ($mulopimfwc_locations as $location) {
            $location_slugs[$location->name] = $this->normalize_location_slug($location->slug);
            $orders_by_location[$location->name] = 0;
            $location_revenue[$location->name] = 0;
        }

        // Build order query args with pagination to prevent memory exhaustion
        // Process orders in batches (1000 per batch)
        $batch_size = 1000;
        $page = 1;
        $all_order_ids = [];
        
        // Increase memory and time limits for dashboard operations
        if (function_exists('ini_set')) {
            @ini_set('memory_limit', '512M');
        }
        @set_time_limit(300);
        
        do {
            $args = array(
                'limit' => $batch_size,
                'offset' => ($page - 1) * $batch_size,
                'return' => 'ids'
            );

            // Status filter
            if ($status_filter === 'all') {
                $args['status'] = ['completed', 'pending', 'processing', 'on-hold'];
            } else {
                $args['status'] = $status_filter;
            }

            // Date filter
            if (!empty($date_from) && !empty($date_to)) {
                $args['date_created'] = $date_from . '...' . $date_to;
            } elseif (!empty($date_from)) {
                $args['date_created'] = '>=' . $date_from;
            } elseif (!empty($date_to)) {
                $args['date_created'] = '<=' . $date_to;
            }

            if ($is_manager_scope) {
                if ($normalized_location_filter !== 'all') {
                    $args['meta_query'] = [
                        [
                            'key' => '_store_location',
                            'value' => $normalized_location_filter,
                            'compare' => '=',
                        ],
                    ];
                } else {
                    $args['meta_query'] = [
                        [
                            'key' => '_store_location',
                            'value' => $manager_location_slugs,
                            'compare' => 'IN',
                        ],
                    ];
                }
            }

            $order_ids = wc_get_orders($args);
            
            if (empty($order_ids)) {
                break;
            }
            
            $all_order_ids = array_merge($all_order_ids, $order_ids);
            
            // Safety check: limit total batches to prevent infinite loops
            if ($page > 200) { // Max 200,000 orders (1000 * 200)
                break;
            }
            
            $page++;
            
        } while (count($order_ids) === $batch_size);

        foreach ($all_order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) continue;

            if ($order->get_meta('_mulopimfwc_split_parent') === 'yes') {
                continue;
            }

            $order_location = $order->get_meta('_store_location');
            $order_location_slug = $this->normalize_location_slug($order_location);
            $order_status = $order->get_status();

            if ($is_manager_scope && !in_array($order_location_slug, $manager_location_slugs, true)) {
                continue;
            }

            // Location filter
            if ($normalized_location_filter !== 'all' && $order_location_slug !== $normalized_location_filter) {
                continue;
            }

            $location_name = $is_manager_scope ? '' : 'Default';
            foreach ($location_slugs as $name => $slug) {
                if ($slug === $order_location_slug) {
                    $location_name = $name;
                    break;
                }
            }

            if ($is_manager_scope && $location_name === '') {
                continue;
            }

            $orders_by_location[$location_name]++;
            if (in_array($order_status, $revenue_statuses, true)) {
                $order_revenue = $calculate_revenue
                    ? (float) $calculate_revenue($order)
                    : (function_exists('mulopimfwc_convert_order_amount_to_base_currency')
                        ? (float) mulopimfwc_convert_order_amount_to_base_currency($order->get_total(), $order)
                        : (float) $order->get_total());
                $location_revenue[$location_name] += $order_revenue;
            }
        }

        return [
            'orders' => $orders_by_location,
            'revenue' => $location_revenue
        ];
    }


    /**
     * Get low stock products efficiently with limit
     */
    private function get_low_stock_products_efficiently($location_filter = 'all')
    {
        global $wpdb, $mulopimfwc_locations, $mulopimfwc_options;

        if (empty($mulopimfwc_locations)) {
            return [];
        }

        $low_stock_products = [];

        $locations_to_check = $mulopimfwc_locations;

        // Filter by specific location if needed
        if ($location_filter !== 'all') {
            $locations_to_check = array_filter($mulopimfwc_locations, function ($loc) use ($location_filter) {
                return $loc->slug === $location_filter;
            });
        }

        foreach ($locations_to_check as $location) {
            $meta_key = '_location_stock_' . $location->term_id;
            $threshold = mulopimfwc_get_location_threshold($location->term_id, 'low');
            if ($threshold < 0) {
                $threshold = 0;
            }

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
            SELECT p.ID, p.post_title, pm.meta_value as stock
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
            INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id AND tr.term_taxonomy_id = %d
            WHERE p.post_type = 'product' 
            AND p.post_status = 'publish'
            AND CAST(pm.meta_value AS SIGNED) <= %d
            AND pm.meta_value != ''
            ORDER BY CAST(pm.meta_value AS SIGNED) ASC
            LIMIT 20
        ", $meta_key, $term_taxonomy_id, $threshold);

            $results = $wpdb->get_results($query);

            foreach ($results as $result) {
                $low_stock_products[] = [
                    'product_id' => $result->ID,
                    'location_id' => $location->term_id,
                    'product_title' => $result->post_title,
                    'location_name' => $location->name,
                    'stock' => (int) $result->stock
                ];
            }
        }

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
        $counts = [];

        if (is_array($manager_location_term_ids)) {
            if (empty($manager_location_term_ids) || $normalized_location_filter === 'default') {
                for ($i = 0; $i < $days; $i++) {
                    $date_ts = $start_ts + ($i * DAY_IN_SECONDS);
                    $date = gmdate('Y-m-d', $date_ts);
                    $labels[] = gmdate('M d', $date_ts);
                    $counts[] = 0;
                }

                return [
                    'labels' => $labels,
                    'counts' => $counts,
                ];
            }

            if ($normalized_location_filter !== 'all') {
                $selected_term = get_term_by('slug', $normalized_location_filter, 'mulopimfwc_store_location');
                if (!$selected_term || is_wp_error($selected_term)) {
                    for ($i = 0; $i < $days; $i++) {
                        $date_ts = $start_ts + ($i * DAY_IN_SECONDS);
                        $date = gmdate('Y-m-d', $date_ts);
                        $labels[] = gmdate('M d', $date_ts);
                        $counts[] = 0;
                    }

                    return [
                        'labels' => $labels,
                        'counts' => $counts,
                    ];
                }

                $selected_manager_term_id = (int) $selected_term->term_id;
                if (!in_array($selected_manager_term_id, $manager_location_term_ids, true)) {
                    for ($i = 0; $i < $days; $i++) {
                        $date_ts = $start_ts + ($i * DAY_IN_SECONDS);
                        $date = gmdate('Y-m-d', $date_ts);
                        $labels[] = gmdate('M d', $date_ts);
                        $counts[] = 0;
                    }

                    return [
                        'labels' => $labels,
                        'counts' => $counts,
                    ];
                }
            }
        }

        for ($i = 0; $i < $days; $i++) {
            $date_ts = $start_ts + ($i * DAY_IN_SECONDS);
            $date = gmdate('Y-m-d', $date_ts);
            $labels[] = gmdate('M d', $date_ts);

            if (is_array($manager_location_term_ids)) {
                if ($selected_manager_term_id > 0) {
                    $query = $wpdb->prepare("
                        SELECT COUNT(DISTINCT p.ID)
                        FROM {$wpdb->posts} p
                        INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                        INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                        WHERE p.post_type = 'product'
                        AND p.post_status = 'publish'
                        AND DATE(p.post_date) = %s
                        AND tt.taxonomy = 'mulopimfwc_store_location'
                        AND tt.term_id = %d
                    ", $date, $selected_manager_term_id);
                } else {
                    $term_placeholders = implode(',', array_fill(0, count($manager_location_term_ids), '%d'));
                    $query = $wpdb->prepare(
                        "
                        SELECT COUNT(DISTINCT p.ID)
                        FROM {$wpdb->posts} p
                        INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                        INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                        WHERE p.post_type = 'product'
                        AND p.post_status = 'publish'
                        AND DATE(p.post_date) = %s
                        AND tt.taxonomy = 'mulopimfwc_store_location'
                        AND tt.term_id IN ({$term_placeholders})
                        ",
                        ...array_merge([$date], $manager_location_term_ids)
                    );
                }
            } elseif ($location_filter === 'all') {
                $query = $wpdb->prepare("
                SELECT COUNT(*) 
                FROM {$wpdb->posts} 
                WHERE post_type = 'product' 
                AND post_status = 'publish'
                AND DATE(post_date) = %s
            ", $date);
            } else {
                $term = get_term_by('slug', $location_filter, 'mulopimfwc_store_location');
                if ($term) {
                    $query = $wpdb->prepare("
                    SELECT COUNT(DISTINCT p.ID) 
                    FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                    INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    WHERE p.post_type = 'product' 
                    AND p.post_status = 'publish'
                    AND DATE(p.post_date) = %s
                    AND tt.taxonomy = 'mulopimfwc_store_location'
                    AND tt.term_id = %d
                ", $date, $term->term_id);
                } else {
                    $query = $wpdb->prepare("
                    SELECT COUNT(*) 
                    FROM {$wpdb->posts} 
                    WHERE post_type = 'product' 
                    AND post_status = 'publish'
                    AND DATE(post_date) = %s
                ", $date);
                }
            }

            $counts[] = (int) $wpdb->get_var($query);
        }

        return [
            'labels' => $labels,
            'counts' => $counts
        ];
    }

    /**
     * Get investment data for products created in a date range.
     */
    private function get_investment_data($date_from = '', $date_to = '', $location_filter = 'all')
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
        $totals = [];

        if (is_array($manager_location_term_ids)) {
            if (empty($manager_location_term_ids) || $normalized_location_filter === 'default') {
                for ($i = 0; $i < $days; $i++) {
                    $date_ts = $start_ts + ($i * DAY_IN_SECONDS);
                    $date = gmdate('Y-m-d', $date_ts);
                    $labels[] = gmdate('M d', $date_ts);
                    $totals[] = 0.0;
                }

                return [
                    'labels' => $labels,
                    'totals' => $totals,
                ];
            }

            if ($normalized_location_filter !== 'all') {
                $selected_term = get_term_by('slug', $normalized_location_filter, 'mulopimfwc_store_location');
                if (!$selected_term || is_wp_error($selected_term)) {
                    for ($i = 0; $i < $days; $i++) {
                        $date_ts = $start_ts + ($i * DAY_IN_SECONDS);
                        $date = gmdate('Y-m-d', $date_ts);
                        $labels[] = gmdate('M d', $date_ts);
                        $totals[] = 0.0;
                    }

                    return [
                        'labels' => $labels,
                        'totals' => $totals,
                    ];
                }

                $selected_manager_term_id = (int) $selected_term->term_id;
                if (!in_array($selected_manager_term_id, $manager_location_term_ids, true)) {
                    for ($i = 0; $i < $days; $i++) {
                        $date_ts = $start_ts + ($i * DAY_IN_SECONDS);
                        $date = gmdate('Y-m-d', $date_ts);
                        $labels[] = gmdate('M d', $date_ts);
                        $totals[] = 0.0;
                    }

                    return [
                        'labels' => $labels,
                        'totals' => $totals,
                    ];
                }
            }
        }

        for ($i = 0; $i < $days; $i++) {
            $date_ts = $start_ts + ($i * DAY_IN_SECONDS);
            $date = gmdate('Y-m-d', $date_ts);
            $labels[] = gmdate('M d', $date_ts);

            if (is_array($manager_location_term_ids)) {
                if ($selected_manager_term_id > 0) {
                    $query = $wpdb->prepare("
                        SELECT COALESCE(SUM(
                            CAST(pm_price.meta_value AS DECIMAL(10,2)) *
                            COALESCE(CAST(pm_qty.meta_value AS SIGNED), 0)
                        ), 0)
                        FROM {$wpdb->posts} p
                        INNER JOIN {$wpdb->postmeta} pm_price ON pm_price.post_id = p.ID AND pm_price.meta_key = '_purchase_price'
                        INNER JOIN {$wpdb->postmeta} pm_qty ON pm_qty.post_id = p.ID AND pm_qty.meta_key = '_purchase_quantity'
                        WHERE p.post_type IN ('product', 'product_variation')
                        AND p.post_status IN ('publish', 'private')
                        AND DATE(p.post_date) = %s
                        AND EXISTS (
                            SELECT 1
                            FROM {$wpdb->term_relationships} tr
                            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                            WHERE tr.object_id = (
                                CASE
                                    WHEN p.post_type = 'product_variation' AND p.post_parent > 0 THEN p.post_parent
                                    ELSE p.ID
                                END
                            )
                            AND tt.taxonomy = 'mulopimfwc_store_location'
                            AND tt.term_id = %d
                        )
                        AND pm_price.meta_value != ''
                        AND pm_price.meta_value > 0
                        AND pm_qty.meta_value != ''
                        AND pm_qty.meta_value > 0
                    ", $date, $selected_manager_term_id);
                } else {
                    $term_placeholders = implode(',', array_fill(0, count($manager_location_term_ids), '%d'));
                    $query = $wpdb->prepare(
                        "
                        SELECT COALESCE(SUM(
                            CAST(pm_price.meta_value AS DECIMAL(10,2)) *
                            COALESCE(CAST(pm_qty.meta_value AS SIGNED), 0)
                        ), 0)
                        FROM {$wpdb->posts} p
                        INNER JOIN {$wpdb->postmeta} pm_price ON pm_price.post_id = p.ID AND pm_price.meta_key = '_purchase_price'
                        INNER JOIN {$wpdb->postmeta} pm_qty ON pm_qty.post_id = p.ID AND pm_qty.meta_key = '_purchase_quantity'
                        WHERE p.post_type IN ('product', 'product_variation')
                        AND p.post_status IN ('publish', 'private')
                        AND DATE(p.post_date) = %s
                        AND EXISTS (
                            SELECT 1
                            FROM {$wpdb->term_relationships} tr
                            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                            WHERE tr.object_id = (
                                CASE
                                    WHEN p.post_type = 'product_variation' AND p.post_parent > 0 THEN p.post_parent
                                    ELSE p.ID
                                END
                            )
                            AND tt.taxonomy = 'mulopimfwc_store_location'
                            AND tt.term_id IN ({$term_placeholders})
                        )
                        AND pm_price.meta_value != ''
                        AND pm_price.meta_value > 0
                        AND pm_qty.meta_value != ''
                        AND pm_qty.meta_value > 0
                        ",
                        ...array_merge([$date], $manager_location_term_ids)
                    );
                }
            } elseif ($location_filter === 'all') {
                $query = $wpdb->prepare("
                SELECT COALESCE(SUM(
                    CAST(pm_price.meta_value AS DECIMAL(10,2)) * 
                    COALESCE(CAST(pm_qty.meta_value AS SIGNED), 0)
                ), 0)
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm_price ON pm_price.post_id = p.ID AND pm_price.meta_key = '_purchase_price'
                INNER JOIN {$wpdb->postmeta} pm_qty ON pm_qty.post_id = p.ID AND pm_qty.meta_key = '_purchase_quantity'
                WHERE p.post_type IN ('product', 'product_variation')
                AND p.post_status IN ('publish', 'private')
                AND DATE(p.post_date) = %s
                AND pm_price.meta_value != ''
                AND pm_price.meta_value > 0
                AND pm_qty.meta_value != ''
                AND pm_qty.meta_value > 0
            ", $date);
            } elseif ($location_filter === 'default') {
                $query = $wpdb->prepare("
                SELECT COALESCE(SUM(
                    CAST(pm_price.meta_value AS DECIMAL(10,2)) * 
                    COALESCE(CAST(pm_qty.meta_value AS SIGNED), 0)
                ), 0)
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm_price ON pm_price.post_id = p.ID AND pm_price.meta_key = '_purchase_price'
                INNER JOIN {$wpdb->postmeta} pm_qty ON pm_qty.post_id = p.ID AND pm_qty.meta_key = '_purchase_quantity'
                WHERE p.post_type IN ('product', 'product_variation')
                AND p.post_status IN ('publish', 'private')
                AND DATE(p.post_date) = %s
                AND pm_price.meta_value != ''
                AND pm_price.meta_value > 0
                AND pm_qty.meta_value != ''
                AND pm_qty.meta_value > 0
                AND NOT EXISTS (
                    SELECT 1
                    FROM {$wpdb->term_relationships} tr
                    INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    WHERE tr.object_id = (
                        CASE
                            WHEN p.post_type = 'product_variation' AND p.post_parent > 0 THEN p.post_parent
                            ELSE p.ID
                        END
                    )
                    AND tt.taxonomy = 'mulopimfwc_store_location'
                )
            ", $date);
            } else {
                $term = get_term_by('slug', $location_filter, 'mulopimfwc_store_location');
                if ($term) {
                    $query = $wpdb->prepare("
                    SELECT COALESCE(SUM(
                        CAST(pm_price.meta_value AS DECIMAL(10,2)) * 
                        COALESCE(CAST(pm_qty.meta_value AS SIGNED), 0)
                    ), 0)
                    FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->postmeta} pm_price ON pm_price.post_id = p.ID AND pm_price.meta_key = '_purchase_price'
                    INNER JOIN {$wpdb->postmeta} pm_qty ON pm_qty.post_id = p.ID AND pm_qty.meta_key = '_purchase_quantity'
                    INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = (
                        CASE
                            WHEN p.post_type = 'product_variation' AND p.post_parent > 0 THEN p.post_parent
                            ELSE p.ID
                        END
                    )
                    INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    WHERE p.post_type IN ('product', 'product_variation')
                    AND p.post_status IN ('publish', 'private')
                    AND DATE(p.post_date) = %s
                    AND tt.taxonomy = 'mulopimfwc_store_location'
                    AND tt.term_id = %d
                    AND pm_price.meta_value != ''
                    AND pm_price.meta_value > 0
                    AND pm_qty.meta_value != ''
                    AND pm_qty.meta_value > 0
                ", $date, $term->term_id);
                } else {
                    $query = $wpdb->prepare("
                    SELECT COALESCE(SUM(
                        CAST(pm_price.meta_value AS DECIMAL(10,2)) * 
                        COALESCE(CAST(pm_qty.meta_value AS SIGNED), 0)
                    ), 0)
                    FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->postmeta} pm_price ON pm_price.post_id = p.ID AND pm_price.meta_key = '_purchase_price'
                    INNER JOIN {$wpdb->postmeta} pm_qty ON pm_qty.post_id = p.ID AND pm_qty.meta_key = '_purchase_quantity'
                    WHERE p.post_type IN ('product', 'product_variation')
                    AND p.post_status IN ('publish', 'private')
                    AND DATE(p.post_date) = %s
                    AND pm_price.meta_value != ''
                    AND pm_price.meta_value > 0
                    AND pm_qty.meta_value != ''
                    AND pm_qty.meta_value > 0
                ", $date);
                }
            }

            $totals[] = (float) $wpdb->get_var($query);
        }

        return [
            'labels' => $labels,
            'totals' => $totals
        ];
    }

    /**
     * Get total products count efficiently
     */
    private function get_total_products_count()
    {
        global $wpdb;

        $assigned_location_slugs = $this->get_dashboard_manager_assigned_location_slugs();

        // For location managers, always scope by assigned locations
        if (is_array($assigned_location_slugs)) {
            if (empty($assigned_location_slugs)) {
                return 0;
            }

            // Get term IDs from slugs
            $term_ids = [];
            foreach ($assigned_location_slugs as $slug) {
                $term = get_term_by('slug', $slug, 'mulopimfwc_store_location');
                if ($term && !is_wp_error($term)) {
                    $term_ids[] = $term->term_id;
                }
            }

            if (empty($term_ids)) {
                return 0;
            }

            // Build query to count products in assigned locations
            $term_ids_placeholder = implode(',', array_fill(0, count($term_ids), '%d'));

            $query = $wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID) 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            WHERE p.post_type = 'product' 
            AND p.post_status NOT IN ('trash', 'auto-draft')
            AND tt.taxonomy = 'mulopimfwc_store_location'
            AND tt.term_id IN ({$term_ids_placeholder})",
                ...$term_ids
            );

            return (int) $wpdb->get_var($query);
        }

        // For admins and other users, return all products
        $query = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status NOT IN ('trash', 'auto-draft')";
        return (int) $wpdb->get_var($query);
    }

    /**
     * Fetch recent sales data for a location (or default bucket) since a timestamp.
     */
    private function get_location_recent_sales_info(string $location_slug, int $since_timestamp): array
    {
        $since_date = gmdate('Y-m-d', $since_timestamp);
        $revenue_statuses = function_exists('mulopimfwc_get_revenue_order_statuses')
            ? mulopimfwc_get_revenue_order_statuses()
            : ['processing', 'completed'];

        // Process orders in batches to prevent memory exhaustion
        $batch_size = 1000;
        $page = 1;
        $all_order_ids = [];
        
        do {
            $args = [
                'limit' => $batch_size,
                'offset' => ($page - 1) * $batch_size,
                'status' => $revenue_statuses,
                'date_created' => '>=' . $since_date,
                'return' => 'ids'
            ];
            
            $order_ids = wc_get_orders($args);
            
            if (empty($order_ids)) {
                break;
            }
            
            $all_order_ids = array_merge($all_order_ids, $order_ids);
            
            // Safety check
            if ($page > 200) {
                break;
            }
            
            $page++;
            
        } while (count($order_ids) === $batch_size);
        
        $args = [
            'order_ids' => $all_order_ids, // Use collected IDs
        ];

        $meta_query = [];

        if ($location_slug === 'default') {
            $meta_query['relation'] = 'OR';
            $meta_query[] = [
                'key' => '_store_location',
                'compare' => 'NOT EXISTS'
            ];
            $meta_query[] = [
                'key' => '_store_location',
                'value' => '',
                'compare' => '='
            ];
            $meta_query[] = [
                'key' => '_store_location',
                'value' => 'default',
                'compare' => '='
            ];
        } else {
            $meta_query[] = [
                'key' => '_store_location',
                'value' => $location_slug,
                'compare' => '='
            ];
        }

        // Filter collected order IDs by location meta query
        $filtered_order_ids = [];
        
        foreach ($all_order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) {
                continue;
            }

            if ($order->get_meta('_mulopimfwc_split_parent') === 'yes') {
                continue;
            }
            
            $order_location = $order->get_meta('_store_location');
            
            // Apply location filter
            if ($location_slug === 'default') {
                if (empty($order_location) || $order_location === 'default') {
                    $filtered_order_ids[] = $order_id;
                }
            } else {
                if ($order_location === $location_slug) {
                    $filtered_order_ids[] = $order_id;
                }
            }
        }

        $last_sale_dates = [];
        $sold_quantities = [];

        foreach ($filtered_order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) {
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

                $sold_quantities[$product_id] = ($sold_quantities[$product_id] ?? 0) + $quantity;
                if (!isset($last_sale_dates[$product_id]) || $timestamp > $last_sale_dates[$product_id]) {
                    $last_sale_dates[$product_id] = $timestamp;
                }
            }
        }

        return [
            'last_sale_dates' => $last_sale_dates,
            'sold_quantities' => $sold_quantities
        ];
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
                'message' => __('Error fetching inventory records. Please try again or contact support.', 'multi-location-product-and-inventory-management'),
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
     * Build profitability, dead stock, shrinkage, and aging summaries for every location.
     */
    private function get_location_profitability_data(int $dead_stock_days = 90): array
    {
        global $mulopimfwc_locations;
        $manager_location_slugs = $this->get_dashboard_manager_assigned_location_slugs();

        $locations = [];
        if (!empty($mulopimfwc_locations) && !is_wp_error($mulopimfwc_locations)) {
            foreach ($mulopimfwc_locations as $location) {
                $locations[] = [
                    'name' => $location->name,
                    'slug' => rawurldecode($location->slug),
                    'term_id' => $location->term_id
                ];
            }
        }

        if (!is_array($manager_location_slugs)) {
            $locations[] = [
                'name' => __('Default', 'multi-location-product-and-inventory-management'),
                'slug' => 'default',
                'term_id' => null
            ];
        }

        $results = [];
        $now = time();
        $threshold_timestamp = $now - ($dead_stock_days * DAY_IN_SECONDS);

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
            $sales_info = $this->get_location_recent_sales_info($entry['slug'], $threshold_timestamp);
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

        return $results;
    }

    /**
     * Calculate total investment efficiently
     */
    private function calculate_total_investment_efficiently()
    {
        global $wpdb;
        $manager_location_term_ids = $this->get_dashboard_manager_assigned_location_term_ids();
        $cache_version = function_exists('mulopimfwc_get_dashboard_cache_version')
            ? mulopimfwc_get_dashboard_cache_version()
            : 1;
        $cache_scope = is_array($manager_location_term_ids)
            ? md5(wp_json_encode($manager_location_term_ids))
            : 'global';
        $cache_key = 'mulopimfwc_total_investment_v' . $cache_version . '_' . $cache_scope;
        $cached_value = get_transient($cache_key);

        if ($cached_value !== false) {
            return (float) $cached_value;
        }

        if (is_array($manager_location_term_ids) && empty($manager_location_term_ids)) {
            set_transient($cache_key, 0.0, HOUR_IN_SECONDS);
            return 0.0;
        }

        if (is_array($manager_location_term_ids)) {
            $term_placeholders = implode(',', array_fill(0, count($manager_location_term_ids), '%d'));
            $total_investment = $wpdb->get_var(
                $wpdb->prepare(
                    "
                    SELECT COALESCE(SUM(
                        CAST(pm1.meta_value AS DECIMAL(10,2)) *
                        COALESCE(CAST(pm2.meta_value AS SIGNED), 0)
                    ), 0) as total
                    FROM {$wpdb->postmeta} pm1
                    INNER JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id
                    INNER JOIN {$wpdb->posts} p ON pm1.post_id = p.ID
                    WHERE pm1.meta_key = %s
                    AND pm2.meta_key = %s
                    AND p.post_type IN ('product', 'product_variation')
                    AND p.post_status IN ('publish', 'private')
                    AND EXISTS (
                        SELECT 1
                        FROM {$wpdb->term_relationships} tr
                        INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                        WHERE tr.object_id = (
                            CASE
                                WHEN p.post_type = 'product_variation' AND p.post_parent > 0 THEN p.post_parent
                                ELSE p.ID
                            END
                        )
                        AND tt.taxonomy = 'mulopimfwc_store_location'
                        AND tt.term_id IN ({$term_placeholders})
                    )
                    AND pm1.meta_value != ''
                    AND pm1.meta_value > 0
                    AND pm2.meta_value != ''
                    AND pm2.meta_value > 0
                    ",
                    ...array_merge(['_purchase_price', '_purchase_quantity'], $manager_location_term_ids)
                )
            );
        } else {
            $total_investment = $wpdb->get_var($wpdb->prepare("
                SELECT COALESCE(SUM(
                    CAST(pm1.meta_value AS DECIMAL(10,2)) *
                    COALESCE(CAST(pm2.meta_value AS SIGNED), 0)
                ), 0) as total
                FROM {$wpdb->postmeta} pm1
                INNER JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id
                INNER JOIN {$wpdb->posts} p ON pm1.post_id = p.ID
                WHERE pm1.meta_key = %s
                AND pm2.meta_key = %s
                AND p.post_type IN ('product', 'product_variation')
                AND p.post_status IN ('publish', 'private')
                AND pm1.meta_value != ''
                AND pm1.meta_value > 0
                AND pm2.meta_value != ''
                AND pm2.meta_value > 0
            ", '_purchase_price', '_purchase_quantity'));
        }

        $total_investment = floatval($total_investment);

        // Cache for 1 hour
        set_transient($cache_key, $total_investment, HOUR_IN_SECONDS);

        return $total_investment;
    }

    /**
     * Get monthly investment data with caching
     */
    private function get_monthly_investment_data_cached()
    {
        $manager_location_term_ids = $this->get_dashboard_manager_assigned_location_term_ids();
        $cache_version = function_exists('mulopimfwc_get_dashboard_cache_version')
            ? mulopimfwc_get_dashboard_cache_version()
            : 1;
        $cache_scope = is_array($manager_location_term_ids)
            ? md5(wp_json_encode($manager_location_term_ids))
            : 'global';
        $cache_key = 'mulopimfwc_monthly_investment_v' . $cache_version . '_' . $cache_scope;
        $cached_data = get_transient($cache_key);

        global $wpdb;

        $now = new DateTimeImmutable('first day of this month 00:00:00', new DateTimeZone('UTC'));
        $start = $now->modify('-11 months');
        $end = $now->modify('+1 month');

        $labels = [];
        $cursor = $start;
        for ($i = 0; $i < 12; $i++) {
            $labels[] = $cursor->format('M Y');
            $cursor = $cursor->modify('+1 month');
        }

        if (is_array($manager_location_term_ids) && empty($manager_location_term_ids)) {
            return [
                'labels' => $labels,
                'data' => array_fill(0, 12, 0.0),
            ];
        }

        // If no purchase prices exist in the scoped dataset, investment is zero across the board.
        if (is_array($manager_location_term_ids)) {
            $term_placeholders = implode(',', array_fill(0, count($manager_location_term_ids), '%d'));
            $has_purchase_price = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "
                    SELECT 1
                    FROM {$wpdb->postmeta} pm
                    INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                    WHERE pm.meta_key = '_purchase_price'
                    AND pm.meta_value != ''
                    AND pm.meta_value > 0
                    AND p.post_type IN ('product', 'product_variation')
                    AND p.post_status IN ('publish', 'private')
                    AND EXISTS (
                        SELECT 1
                        FROM {$wpdb->term_relationships} tr
                        INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                        WHERE tr.object_id = (
                            CASE
                                WHEN p.post_type = 'product_variation' AND p.post_parent > 0 THEN p.post_parent
                                ELSE p.ID
                            END
                        )
                        AND tt.taxonomy = 'mulopimfwc_store_location'
                        AND tt.term_id IN ({$term_placeholders})
                    )
                    LIMIT 1
                    ",
                    ...$manager_location_term_ids
                )
            );
        } else {
            $has_purchase_price = (int) $wpdb->get_var("
                SELECT 1
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE pm.meta_key = '_purchase_price'
                AND pm.meta_value != ''
                AND pm.meta_value > 0
                AND p.post_type IN ('product', 'product_variation')
                AND p.post_status IN ('publish', 'private')
                LIMIT 1
            ");
        }

        if (!$has_purchase_price) {
            $result = [
                'labels' => $labels,
                'data' => array_fill(0, 12, 0.0),
            ];
            set_transient($cache_key, $result, 6 * HOUR_IN_SECONDS);
            return $result;
        }

        if ($cached_data !== false) {
            return $cached_data;
        }

        if (is_array($manager_location_term_ids)) {
            $term_placeholders = implode(',', array_fill(0, count($manager_location_term_ids), '%d'));
            $query = $wpdb->prepare(
                "
                SELECT DATE_FORMAT(p.post_date, '%%Y-%%m') AS ym,
                       COALESCE(SUM(
                           CAST(pm_price.meta_value AS DECIMAL(10,2)) *
                           COALESCE(CAST(pm_qty.meta_value AS SIGNED), 0)
                       ), 0) AS total
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm_price ON pm_price.post_id = p.ID AND pm_price.meta_key = '_purchase_price'
                INNER JOIN {$wpdb->postmeta} pm_qty ON pm_qty.post_id = p.ID AND pm_qty.meta_key = '_purchase_quantity'
                WHERE p.post_type IN ('product', 'product_variation')
                AND p.post_status IN ('publish', 'private')
                AND p.post_date >= %s
                AND p.post_date < %s
                AND EXISTS (
                    SELECT 1
                    FROM {$wpdb->term_relationships} tr
                    INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    WHERE tr.object_id = (
                        CASE
                            WHEN p.post_type = 'product_variation' AND p.post_parent > 0 THEN p.post_parent
                            ELSE p.ID
                        END
                    )
                    AND tt.taxonomy = 'mulopimfwc_store_location'
                    AND tt.term_id IN ({$term_placeholders})
                )
                AND pm_price.meta_value != ''
                AND pm_price.meta_value > 0
                AND pm_qty.meta_value != ''
                AND pm_qty.meta_value > 0
                GROUP BY ym
                ",
                ...array_merge([$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')], $manager_location_term_ids)
            );
        } else {
            $query = $wpdb->prepare("
                SELECT DATE_FORMAT(p.post_date, '%%Y-%%m') AS ym,
                       COALESCE(SUM(
                           CAST(pm_price.meta_value AS DECIMAL(10,2)) *
                           COALESCE(CAST(pm_qty.meta_value AS SIGNED), 0)
                       ), 0) AS total
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm_price ON pm_price.post_id = p.ID AND pm_price.meta_key = '_purchase_price'
                INNER JOIN {$wpdb->postmeta} pm_qty ON pm_qty.post_id = p.ID AND pm_qty.meta_key = '_purchase_quantity'
                WHERE p.post_type IN ('product', 'product_variation')
                AND p.post_status IN ('publish', 'private')
                AND p.post_date >= %s
                AND p.post_date < %s
                AND pm_price.meta_value != ''
                AND pm_price.meta_value > 0
                AND pm_qty.meta_value != ''
                AND pm_qty.meta_value > 0
                GROUP BY ym
            ", $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'));
        }

        $rows = $wpdb->get_results($query);
        $totals_by_month = [];
        foreach ($rows as $row) {
            if (isset($row->ym)) {
                $totals_by_month[$row->ym] = isset($row->total) ? (float) $row->total : 0.0;
            }
        }

        $data = [];
        $cursor = $start;
        for ($i = 0; $i < 12; $i++) {
            $key = $cursor->format('Y-m');
            $data[] = isset($totals_by_month[$key]) ? (float) $totals_by_month[$key] : 0.0;
            $cursor = $cursor->modify('+1 month');
        }

        $result = [
            'labels' => $labels,
            'data' => $data
        ];

        // Cache for 6 hours
        set_transient($cache_key, $result, 6 * HOUR_IN_SECONDS);

        return $result;
    }

    /**
     * Build a reusable payload for the dashboard and live APIs.
     */
    private function build_dashboard_payload(): array
    {
        global $mulopimfwc_locations;
        $mulopimfwc_locations = $this->get_dashboard_scoped_locations();

        $product_counts = [];
        $stock_levels = [];
        $location_colors = [];
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

        if (empty($mulopimfwc_locations) || is_wp_error($mulopimfwc_locations)) {
            $mulopimfwc_locations = [];
        }

        foreach ($mulopimfwc_locations as $index => $location) {
            $base_index = $index % count($base_colors);
            $cycle = floor($index / count($base_colors));

            if ($cycle === 0) {
                $location_colors[$location->name] = $base_colors[$base_index]['fill'];
                $location_border_colors[$location->name] = $base_colors[$base_index]['border'];
            } else {
                $adjust = ($cycle * 10) % 30;
                $location_colors[$location->name] = $this->adjustColorLightness($base_colors[$base_index]['fill'], $adjust);
                $location_border_colors[$location->name] = $this->adjustColorLightness($base_colors[$base_index]['border'], $adjust);
            }

            $product_counts[$location->name] = $this->get_location_product_count($location->term_id);
            $stock_levels[$location->name] = $this->get_location_stock_level($location->term_id);
        }

        $orders_data = $this->get_orders_data_efficiently();
        $recent_products_data = $this->get_recent_products_data();
        $monthly_investment_data = $this->get_monthly_investment_data_cached();
        $total_investment = $this->calculate_total_investment_efficiently();
        $dead_stock_days = 90;
        $profitability = $this->get_location_profitability_data($dead_stock_days);
        $low_stock_products = $this->get_low_stock_products_efficiently();

        return [
            'product_counts' => $product_counts,
            'stock_levels' => $stock_levels,
            'location_colors' => $location_colors,
            'location_border_colors' => $location_border_colors,
            'orders_by_location' => $orders_data['orders'],
            'revenue_by_location' => $orders_data['revenue'],
            'recent_products_data' => $recent_products_data,
            'monthly_investment_data' => $monthly_investment_data,
            'profitability_by_location' => $profitability,
            'dead_stock_days' => $dead_stock_days,
            'total_investment' => $total_investment,
            'low_stock_products' => $low_stock_products,
            'summary' => [
                'total_orders' => array_sum($orders_data['orders']),
                'total_revenue' => array_sum($orders_data['revenue']),
                'total_stock' => array_sum($stock_levels),
                'total_investment' => $total_investment,
            ],
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
                'message' => __('Permission denied', 'multi-location-product-and-inventory-management'),
            ]);
            return;
        }

        // FIXED: Add rate limiting (max 1 request per 5 seconds per user) (Issue #32)
        $rate_limit_key = 'mulopimfwc_dashboard_live_rate_' . get_current_user_id();
        $last_request = get_transient($rate_limit_key);
        if ($last_request !== false && (time() - $last_request) < 5) {
            wp_send_json_error([
                'message' => __('Please wait before requesting another update.', 'multi-location-product-and-inventory-management'),
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

        $payload = $this->build_dashboard_payload();
        $last_check = (int) get_option('mulopimfwc_dashboard_last_check', 0);
        $site_status = $this->resolve_site_status();
        $alerts = $this->collect_live_alerts($last_check, $payload, $site_status);

        update_option('mulopimfwc_dashboard_last_check', current_time('timestamp', true));

        $response_data = [
            'productCounts' => $payload['product_counts'],
            'stockLevels' => $payload['stock_levels'],
            'locationColors' => $payload['location_colors'],
            'locationBorderColors' => $payload['location_border_colors'],
            'orders' => $payload['orders_by_location'],
            'revenue' => $payload['revenue_by_location'],
            'summary' => $payload['summary'],
            'dateLabels' => $payload['recent_products_data']['labels'],
            'dateCounts' => $payload['recent_products_data']['counts'],
            'monthlyInvestmentLabels' => $payload['monthly_investment_data']['labels'],
            'monthlyInvestmentData' => $payload['monthly_investment_data']['data'],
            'profitabilityByLocation' => $payload['profitability_by_location'],
            'deadStockDays' => $payload['dead_stock_days'],
            'totalInvestment' => $payload['total_investment'],
            // Return the full low stock list so the live sync view matches initial render
            'low_stock' => $payload['low_stock_products'],
            'alerts' => $alerts,
            'site_status' => $site_status,
            'currency' => $this->get_dashboard_reporting_currency_symbol(),
            'currency_code' => $this->get_dashboard_reporting_currency_code(),
        ];

        // Cache for 30 seconds
        set_transient($cache_key, $response_data, 30);

        wp_send_json_success($response_data);
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
                    sprintf(__('New order #%s placed - %s', 'multi-location-product-and-inventory-management'), $order_number, $formatted_price),
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

        $low_stock_items = array_filter($payload['low_stock_products'], fn($item) => isset($item['stock']));
        if (!empty($low_stock_items)) {
            // Create individual alerts for each low stock product
            foreach (array_slice($low_stock_items, 0, 5) as $item) {
                $product_id = isset($item['product_id']) ? $item['product_id'] : 0;
                $product_url = $fallback_product_url;
                if ($can_manage_products && $product_id) {
                    $product_url = get_edit_post_link($product_id);
                }
                $alerts[] = $this->format_alert(
                    'low_stock',
                    sprintf(__('Low stock: %s (%s)', 'multi-location-product-and-inventory-management'), $item['product_title'], $item['location_name']),
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

        $out_threshold = isset($options['out_of_stock_threshold']) ? (int) $options['out_of_stock_threshold'] : 0;
        $out_of_stock = array_filter($payload['low_stock_products'], fn($item) => isset($item['stock']) && (int) $item['stock'] <= $out_threshold);
        if (!empty($out_of_stock)) {
            // Create individual alerts for each out of stock product
            foreach (array_slice($out_of_stock, 0, 5) as $item) {
                $product_id = isset($item['product_id']) ? $item['product_id'] : 0;
                $product_url = $fallback_product_url;
                if ($can_manage_products && $product_id) {
                    $product_url = get_edit_post_link($product_id);
                }
                $alerts[] = $this->format_alert(
                    'out_of_stock',
                    sprintf(__('Out of stock: %s (%s)', 'multi-location-product-and-inventory-management'), $item['product_title'], $item['location_name']),
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
                    sprintf(__('%s orders %s', 'multi-location-product-and-inventory-management'), ucwords(str_replace('_', ' ', $key)), $count),
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
                sprintf(__('High-value order: #%s (%s)', 'multi-location-product-and-inventory-management'), $order->get_order_number(), $formatted_price),
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

        $previous_snapshot = get_option('mulopimfwc_live_low_stock_snapshot', []);
        $current_snapshot = [];
        foreach ($payload['low_stock_products'] as $item) {
            if (isset($item['product_id'])) {
                $current_snapshot[$item['product_id']] = intval($item['stock']);
            }
        }
        $restocked = [];
        foreach ($previous_snapshot as $product_id => $stock) {
            if (!isset($current_snapshot[$product_id])) {
                $restocked[] = $product_id;
            }
        }
        update_option('mulopimfwc_live_low_stock_snapshot', $current_snapshot);
        if (!empty($restocked)) {
            sort($restocked, SORT_NUMERIC);
            $titles = [];
            foreach ($restocked as $product_id) {
                $product = wc_get_product($product_id);
                if ($product) {
                    $titles[] = $product->get_name();
                }
            }
            if (!empty($titles)) {
                $alerts[] = $this->format_alert(
                    'restocked',
                    sprintf(__('Restocked: %s', 'multi-location-product-and-inventory-management'), implode(', ', array_slice($titles, 0, 3))),
                    'info',
                    [
                        'product_ids' => $restocked,
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
                sprintf(__('Low rating review for %s', 'multi-location-product-and-inventory-management'), $product ? $product->get_name() : __('Product', 'multi-location-product-and-inventory-management')),
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
                    sprintf(__('Manager updated: %s', 'multi-location-product-and-inventory-management'), $user->display_name),
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
                    ? __('Site went down. Monitoring could not reach the server.', 'multi-location-product-and-inventory-management')
                    : __('Site is reachable again.', 'multi-location-product-and-inventory-management'))
                : __('Site monitoring is stable.', 'multi-location-product-and-inventory-management'),
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
