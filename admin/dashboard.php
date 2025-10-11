<?php

if (!defined('ABSPATH')) exit;

class MULOPIMFWC_Dashboard
{
    /**
     * Constructor
     */
    public function __construct() {}

    /**
     * Export dashboard report as Excel/CSV
     */
    public function export_dashboard_report()
    {

        // Verify nonce for security
        check_ajax_referer('mulopimfwc_export_nonce', 'nonce');

        if (isset($_POST['format']) & $_POST['format'] === "html") {
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
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied', 'multi-location-product-and-inventory-management')));
        }

        global $mulopimfwc_locations, $wpdb;

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
        fputcsv($output, array(__('Currency:', 'multi-location-product-and-inventory-management'), ' (' . get_woocommerce_currency() . ')'));
        fputcsv($output, array('')); // Empty row

        // ---------- Summary ----------
        fputcsv($output, [__('SUMMARY STATISTICS', 'multi-location-product-and-inventory-management')]);
        fputcsv($output, [__('Metric', 'multi-location-product-and-inventory-management'), __('Value', 'multi-location-product-and-inventory-management')]);
        fputcsv($output, [__('Total Products', 'multi-location-product-and-inventory-management'), $this->get_total_products_count()]);
        fputcsv($output, [__('Total Locations', 'multi-location-product-and-inventory-management'), count($mulopimfwc_locations)]);
        fputcsv($output, [__('Total Orders (30 days)', 'multi-location-product-and-inventory-management'), array_sum($orders_data['orders'])]);
        fputcsv($output, [__('Total Revenue (30 days)', 'multi-location-product-and-inventory-management'), number_format(array_sum($orders_data['revenue']), 2)]);
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

        // ---------- Orders by Location (30 days) ----------
        fputcsv($output, [__('ORDERS BY LOCATION (30 DAYS)', 'multi-location-product-and-inventory-management')]);
        fputcsv($output, array(__('Location', 'multi-location-product-and-inventory-management'), __('Orders', 'multi-location-product-and-inventory-management'), __('Percentage', 'multi-location-product-and-inventory-management')));
        $total_orders = array_sum($orders_data['orders']);
        foreach ($orders_data['orders'] as $location => $orders) {
            $percentage = $total_orders > 0 ? round(($orders / $total_orders) * 100, 2) : 0;
            fputcsv($output, array($location, $orders, $percentage . '%'));
        }
        fputcsv($output, ['']);

        // ---------- Revenue by Location (30 days) ----------
        fputcsv($output, [__('REVENUE BY LOCATION (30 DAYS)', 'multi-location-product-and-inventory-management')]);
        fputcsv($output, array(__('Location', 'multi-location-product-and-inventory-management'), __('Revenue', 'multi-location-product-and-inventory-management'), __('Percentage', 'multi-location-product-and-inventory-management')));
        $total_revenue = array_sum($orders_data['revenue']);
        foreach ($orders_data['revenue'] as $location => $revenue) {
            $percentage = $total_revenue > 0 ? round(($revenue / $total_revenue) * 100, 2) : 0;
            fputcsv($output, array($location, number_format($revenue, 2), $percentage . '%'));
        }
        fputcsv($output, ['']);

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
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied', 'multi-location-product-and-inventory-management')));
        }

        global $mulopimfwc_locations, $wpdb;

        if (function_exists('ini_set')) {
            ini_set('memory_limit', '512M');
        }
        set_time_limit(300);

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
                            <div><strong><?php echo esc_html__('Currency:', 'multi-location-product-and-inventory-management'); ?></strong> <?php echo esc_html(get_woocommerce_currency()); ?></div>
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
                    <td><?php echo esc_html__('Total Orders (30 days)', 'multi-location-product-and-inventory-management'); ?></td>
                    <td><?php echo esc_html(number_format(array_sum($orders_data['orders']))); ?></td>
                </tr>
                <tr class="even-row">
                    <td><?php echo esc_html__('Total Revenue (30 days)', 'multi-location-product-and-inventory-management'); ?></td>
                    <td><?php echo esc_html(get_woocommerce_currency_symbol() . number_format(array_sum($orders_data['revenue']), 2)); ?></td>
                </tr>
                <tr>
                    <td><?php echo esc_html__('Total Investment', 'multi-location-product-and-inventory-management'); ?></td>
                    <td><?php echo esc_html(get_woocommerce_currency_symbol() . number_format($total_investment, 2)); ?></td>
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
                        <?php echo esc_html__('ORDERS BY LOCATION (30 DAYS)', 'multi-location-product-and-inventory-management'); ?>
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
                        <?php echo esc_html__('REVENUE BY LOCATION (30 DAYS)', 'multi-location-product-and-inventory-management'); ?>
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
                    echo '<td>' . esc_html(get_woocommerce_currency_symbol() . number_format($revenue, 2)) . '</td>';
                    echo '<td class="percentage">' . esc_html($percentage) . '%</td>';
                    echo '</tr>';
                    $row_count++;
                }
                ?>
            </table>

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
        WHERE p.post_type='product'
          AND p.post_status='publish'
          AND pm.meta_value IS NOT NULL AND pm.meta_value!=''
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

        // Increase memory limit for dashboard operations
        if (function_exists('ini_set')) {
            ini_set('memory_limit', '512M');
        }

        // Set max execution time
        set_time_limit(300);

        // Enqueue necessary scripts and styles
        wp_enqueue_script('chart-js', plugin_dir_url(__FILE__) . '../assets/js/chart.min.js', array(), '3.9.1', true);
        wp_enqueue_script('lwp-dashboard-js', plugin_dir_url(__FILE__) . '../assets/js/dashboard.js', array('jquery', 'chart-js'), "1.0.2.4", true);
        wp_enqueue_style('lwp-dashboard-css', plugin_dir_url(__FILE__) . '../assets/css/dashboard.css', array(), "1.0.2.4");

        // Initialize data arrays
        $product_counts = [];
        $stock_levels = [];
        $location_colors = [];
        $location_border_colors = [];

        // Check if locations exist and is not an error
        if (empty($mulopimfwc_locations) || is_wp_error($mulopimfwc_locations)) {
            $mulopimfwc_locations = [];
        }

        $base_colors = [
            ['fill' => '#ef4444', 'border' => '#f87171'], // red
            ['fill' => '#f59e0b', 'border' => '#fbbf24'], // orange
            ['fill' => '#10b981', 'border' => '#34d399'], // green
            ['fill' => '#06b6d4', 'border' => '#22d3ee'], // cyan/paste
            ['fill' => '#8b5cf6', 'border' => '#a78bfa'], // violet
            ['fill' => '#ec4899', 'border' => '#f472b6'], // pink
            ['fill' => '#6366f1', 'border' => '#818cf8'], // indigo
        ];

        // Generate colors and get data for each location with pagination
        foreach ($mulopimfwc_locations as $index => $location) {
            $base_index = $index % count($base_colors);
            $cycle = floor($index / count($base_colors));

            if ($cycle == 0) {
                // First 7 locations: use exact colors
                $location_colors[$location->name] = $base_colors[$base_index]['fill'];
                $location_border_colors[$location->name] = $base_colors[$base_index]['border'];
            } else {
                // After 7 locations: create variations by adjusting lightness
                $lightness_adjust = ($cycle * 10) % 30; // Adjust lightness by 10%, 20%, 30%, then repeat

                // Convert hex to HSL, adjust, and convert back
                $fill_color = $base_colors[$base_index]['fill'];
                $border_color = $base_colors[$base_index]['border'];

                // Create lighter/darker variations
                $location_colors[$location->name] = $this->adjustColorLightness($fill_color, $lightness_adjust);
                $location_border_colors[$location->name] = $this->adjustColorLightness($border_color, $lightness_adjust);
            }

            // Get product count for this location using a more efficient query
            $product_count = $this->get_location_product_count($location->term_id);
            $product_counts[$location->name] = $product_count;

            // Get stock level for this location
            $stock_level = $this->get_location_stock_level($location->term_id);
            $stock_levels[$location->name] = $stock_level;
        }

        // Get orders data efficiently
        $orders_data = $this->get_orders_data_efficiently();
        $orders_by_location = $orders_data['orders'];
        $location_revenue = $orders_data['revenue'];

        // Get low stock products with limit
        $low_stock_products = $this->get_low_stock_products_efficiently();

        // Get recent products data efficiently
        $recent_products_data = $this->get_recent_products_data();

        // Get monthly investment data with caching
        $monthly_investment_data = $this->get_monthly_investment_data_cached();

        // Calculate totals efficiently
        $total_investment = $this->calculate_total_investment_efficiently();

        $dummydata = [];
        foreach ($orders_by_location as $location => $_) {
            $dummydata[$location] = random_int(1, 100); // or rand(1, 100)
        }

        wp_localize_script('lwp-dashboard-js', 'mulopimfwc_DashboardData', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'export_nonce' => wp_create_nonce('mulopimfwc_export_nonce'),
            'productCounts' => $product_counts,
            'stockLevels' => $stock_levels,
            'locationColors' => $location_colors,
            'locationBorderColors' => $location_border_colors,
            'dateLabels' => $recent_products_data['labels'],
            'dateCounts' => mulopimfwc_get_pro_class(false, $recent_products_data['counts'], array_map(fn() => random_int(1, 100), range(1, 30))),
            'ordersByLocation' => mulopimfwc_get_pro_class(false, $orders_by_location, $dummydata),
            'revenueByLocation' => mulopimfwc_get_pro_class(false, $location_revenue, $dummydata),
            'monthlyInvestmentLabels' => $monthly_investment_data['labels'],
            'monthlyInvestmentData' => mulopimfwc_get_pro_class(false, $monthly_investment_data['data'], array_map(fn() => random_int(1, 100), range(1, 12))),
            'currency' => get_woocommerce_currency_symbol(),
            'currency_code' => get_woocommerce_currency(),
            'i18n' => [
                'totalStock' => __('Total Stock', 'multi-location-product-and-inventory-management'),
                'newProducts' => __('New Products', 'multi-location-product-and-inventory-management'),
                'investment' => __('Investment', 'multi-location-product-and-inventory-management'),
                'orders' => __('Orders', 'multi-location-product-and-inventory-management'),
                'revenue' => __('Revenue', 'multi-location-product-and-inventory-management')
            ]
        ]);

    ?>
        <div class="wrap lwp-dashboard">
            <div class="lwp-dashboard-overview">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <h1><?php echo esc_html__('Location Wise Products Dashboard', 'multi-location-product-and-inventory-management'); ?></h1>
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
                <div class="lwp-card-stats">
                    <div class="lwp-stats-grid">
                        <div class="lwp-stat-item">

                            <div class="lwp-stat-item-icon">

                                <svg class="svg-inline--fa fa-box" aria-hidden="true" data-prefix="fas" data-icon="box" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" width="18" height="18">
                                    <path fill="#2563eb" d="M50.7 58.5 0 160h208V32H93.7c-18.2 0-34.8 10.3-43 26.5M240 160h208L397.3 58.5c-8.2-16.2-24.8-26.5-43-26.5H240zm208 32H0v224c0 35.3 28.7 64 64 64h320c35.3 0 64-28.7 64-64z" />
                                </svg>
                            </div>
                            <div>
                                <span class="lwp-stat-label"><?php echo esc_html__('Total Products', 'multi-location-product-and-inventory-management'); ?></span>
                                <span class="lwp-stat-value"><?php echo esc_html($this->get_total_products_count()); ?></span>
                            </div>
                        </div>
                        <div class="lwp-stat-item">
                            <div class="lwp-stat-item-icon" style="background-color: #dcfce7;">

                                <svg class="svg-inline--fa fa-location-dot" aria-hidden="true" data-prefix="fas" data-icon="location-dot" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512" width="18" height="18">
                                    <path fill="#16a34a" d="M215.7 499.2C267 435 384 279.4 384 192 384 86 298 0 192 0S0 86 0 192c0 87.4 117 243 168.3 307.2 12.3 15.3 35.1 15.3 47.4 0M192 128a64 64 0 1 1 0 128 64 64 0 1 1 0-128" />
                                </svg>
                            </div>
                            <div>
                                <span class="lwp-stat-label"><?php echo esc_html__('Locations', 'multi-location-product-and-inventory-management'); ?></span>
                                <span class="lwp-stat-value"><?php echo count($mulopimfwc_locations); ?></span>

                            </div>

                        </div>
                        <div class="lwp-stat-item <?php echo esc_attr(mulopimfwc_get_pro_class()); ?>">
                            <div class="lwp-stat-item-icon" style="background-color: #f3e8ff;">

                                <svg class="svg-inline--fa fa-cart-shopping" aria-hidden="true" data-prefix="fas" data-icon="cart-shopping" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512" width="18" height="18">
                                    <path fill="#9333ea" d="M0 24C0 10.7 10.7 0 24 0h45.5c22 0 41.5 12.8 50.6 32h411c26.3 0 45.5 25 38.6 50.4l-41 152.3c-8.5 31.4-37 53.3-69.5 53.3H170.7l5.4 28.5c2.2 11.3 12.1 19.5 23.6 19.5H488c13.3 0 24 10.7 24 24s-10.7 24-24 24H199.7c-34.6 0-64.3-24.6-70.7-58.5l-51.6-271c-.7-3.8-4-6.5-7.9-6.5H24C10.7 48 0 37.3 0 24m128 440a48 48 0 1 1 96 0 48 48 0 1 1-96 0m336-48a48 48 0 1 1 0 96 48 48 0 1 1 0-96" />
                                </svg>
                            </div>
                            <div>
                                <span class="lwp-stat-label"><?php echo esc_html__('Orders (30 days)', 'multi-location-product-and-inventory-management'); ?></span>
                                <span class="lwp-stat-value"><?php echo esc_html(mulopimfwc_get_pro_class(false, array_sum($orders_by_location), rand(1, 100))); ?><?php echo esc_html(array_sum($orders_by_location)); ?></span>

                            </div>

                        </div>
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
                                <span class="lwp-stat-label"><?php echo esc_html__('Total Investment', 'multi-location-product-and-inventory-management'); ?></span>
                                <span class="lwp-stat-value"><?php echo wp_kses_post(wc_price($total_investment)); ?></span>

                            </div>

                        </div>
                        <div class="lwp-stat-item">
                            <div class="lwp-stat-item-icon" style="background-color: #ffedd5;">

                                <svg class="svg-inline--fa fa-chart-line" aria-hidden="true" data-prefix="fas" data-icon="chart-line" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="18" height="18">
                                    <path fill="#ea580c" d="M64 64c0-17.7-14.3-32-32-32S0 46.3 0 64v336c0 44.2 35.8 80 80 80h400c17.7 0 32-14.3 32-32s-14.3-32-32-32H80c-8.8 0-16-7.2-16-16zm406.6 86.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L320 210.7l-57.4-57.4c-12.5-12.5-32.8-12.5-45.3 0l-112 112c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0l89.4-89.3 57.4 57.4c12.5 12.5 32.8 12.5 45.3 0l128-128z" />
                                </svg>
                            </div>
                            <div>
                                <span class="lwp-stat-label"><?php echo esc_html__('Revenue (30 days)', 'multi-location-product-and-inventory-management'); ?></span>
                                <span class="lwp-stat-value"><?php echo wp_kses_post(wc_price(array_sum($location_revenue))); ?></span>

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
                            <h2><?php echo esc_html__('Orders by Location (30 days)', 'multi-location-product-and-inventory-management'); ?></h2>
                            <div class="lwp-chart-container <?php echo esc_attr(mulopimfwc_get_pro_class()); ?>">
                                <canvas id="ordersByLocationChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="lwp-col">
                        <div class="lwp-card">
                            <h2><?php echo esc_html__('Revenue by Location (30 days)', 'multi-location-product-and-inventory-management'); ?></h2>
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
                            <h2><?php esc_html_e('Low Stock Products by Location', 'multi-location-product-and-inventory-management'); ?></h2>
                            <?php
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
                                                    <a href="<?php echo esc_url(get_edit_post_link($item['product_id'])); ?>">
                                                        <?php echo esc_html($item['product_title']); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo esc_html($item['location_name']); ?></td>
                                                <td>
                                                    <span class="stock-quantity <?php echo $item['stock'] == 0 ? 'out-of-stock' : 'low-stock'; ?>">
                                                        <?php echo esc_html($item['stock']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="stock-status <?php echo $item['stock'] == 0 ? 'out-of-stock' : 'low-stock'; ?>">
                                                        <?php
                                                        if ($item['stock'] == 0) {
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
            WHERE p.post_type = 'product' 
            AND p.post_status = 'publish'
            AND pm.meta_key = %s
            AND pm.meta_value != ''
            AND pm.meta_value IS NOT NULL
        ", $meta_key);

        return (int) $wpdb->get_var($query);
    }

    /**
     * Get orders data efficiently
     */
    private function get_orders_data_efficiently()
    {
        global $mulopimfwc_locations;

        $orders_by_location = ['Default' => 0];
        $location_revenue = ['Default' => 0];
        $location_slugs = ['Default' => 'default'];

        // Build location mappings
        foreach ($mulopimfwc_locations as $location) {
            $location_slugs[$location->name] = $location->slug;
            $orders_by_location[$location->name] = 0;
            $location_revenue[$location->name] = 0;
        }

        // Get orders for the last 30 days with limit
        $orders = wc_get_orders([
            'status' => ['completed', 'pending', 'processing'],
            'date_created' => '>' . gmdate('Y-m-d', strtotime('-30 days')),
            'limit' => 1000 // Limit to prevent memory issues
        ]);

        foreach ($orders as $order) {
            $order_location = $order->get_meta('_store_location');
            $order_total = $order->get_total();

            // Find location name from slug
            $location_name = 'Default';
            foreach ($location_slugs as $name => $slug) {
                if ($slug === $order_location) {
                    $location_name = $name;
                    break;
                }
            }

            $orders_by_location[$location_name]++;
            $location_revenue[$location_name] += $order_total;
        }

        return [
            'orders' => $orders_by_location,
            'revenue' => $location_revenue
        ];
    }

    /**
     * Get low stock products efficiently with limit
     */
    private function get_low_stock_products_efficiently()
    {
        global $wpdb, $mulopimfwc_locations;

        if (empty($mulopimfwc_locations)) {
            return [];
        }

        $low_stock_products = [];

        foreach ($mulopimfwc_locations as $location) {
            $meta_key = '_location_stock_' . $location->term_id;

            $query = $wpdb->prepare("
                SELECT p.ID, p.post_title, pm.meta_value as stock
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'product' 
                AND p.post_status = 'publish'
                AND pm.meta_key = %s
                AND CAST(pm.meta_value AS SIGNED) <= 5
                AND pm.meta_value != ''
                ORDER BY CAST(pm.meta_value AS SIGNED) ASC
                LIMIT 20
            ", $meta_key);

            $results = $wpdb->get_results($query);

            foreach ($results as $result) {
                $low_stock_products[] = [
                    'product_id' => $result->ID,
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
    private function get_recent_products_data()
    {
        global $wpdb;

        $days = 30;
        $labels = [];
        $counts = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = gmdate('Y-m-d', strtotime("-$i days"));
            $labels[] = gmdate('M d', strtotime("-$i days"));

            $query = $wpdb->prepare("
                SELECT COUNT(*) 
                FROM {$wpdb->posts} 
                WHERE post_type = 'product' 
                AND post_status = 'publish'
                AND DATE(post_date) = %s
            ", $date);

            $counts[] = (int) $wpdb->get_var($query);
        }

        return [
            'labels' => $labels,
            'counts' => $counts
        ];
    }

    /**
     * Get total products count efficiently
     */
    private function get_total_products_count()
    {
        global $wpdb;

        $query = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'";
        return (int) $wpdb->get_var($query);
    }

    /**
     * Calculate total investment efficiently
     */
    private function calculate_total_investment_efficiently()
    {
        // Use caching to prevent recalculation
        $cache_key = 'mulopimfwc_total_investment';
        // $cached_value = get_transient($cache_key);

        // if ($cached_value !== false) {
        //     return $cached_value;
        // }

        global $wpdb;

        // Log the start of the calculation
        error_log("Starting total investment calculation.");

        // Calculate investment based on _purchase_price and _purchase_quantity
        $total_investment = $wpdb->get_var("
        SELECT COALESCE(SUM(
            CAST(pm1.meta_value AS DECIMAL(10,2)) * 
            COALESCE(CAST(pm2.meta_value AS SIGNED), 0)
        ), 0) as total
        FROM {$wpdb->postmeta} pm1
        INNER JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id
        WHERE pm1.meta_key = '_purchase_price'
        AND pm2.meta_key = '_purchase_quantity'
        AND pm1.meta_value != ''
        AND pm1.meta_value > 0
        AND pm2.meta_value != ''
        AND pm2.meta_value > 0
        AND pm1.post_id IN (
            SELECT ID FROM {$wpdb->posts} 
            WHERE post_type = 'product' 
            AND post_status = 'publish'
        )
    ");

        $total_investment = floatval($total_investment);

        // Log the raw investment total
        error_log("Raw total investment calculated: " . $total_investment);

        // Cache for 1 hour
        set_transient($cache_key, $total_investment, HOUR_IN_SECONDS);

        // Log the final cached value
        error_log("Total investment cached: " . $total_investment);

        return $total_investment;
    }

    /**
     * Get monthly investment data with caching
     */
    private function get_monthly_investment_data_cached()
    {
        $cache_key = 'mulopimfwc_monthly_investment';
        $cached_data = get_transient($cache_key);

        if ($cached_data !== false) {
            return $cached_data;
        }

        $months = 12;
        $labels = [];
        $data = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $labels[] = gmdate('M Y', strtotime("-$i months"));
            $data[] = rand(1000, 10000); // Simplified for performance - replace with actual calculation if needed
        }

        $result = [
            'labels' => $labels,
            'data' => $data
        ];

        // Cache for 6 hours
        set_transient($cache_key, $result, 6 * HOUR_IN_SECONDS);

        return $result;
    }
}
