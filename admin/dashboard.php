<?php

if (!defined('ABSPATH')) exit;


class MULOPIMFWC_Dashboard
{
    /**
     * Constructor
     */
    public function __construct() {}

    /**
     * Render the dashboard page content
     * 
     * @return void
     */
    public function dashboard_page_content()
    {
        global $mulopimfwc_locations;
        
        // Enqueue necessary scripts and styles
        wp_enqueue_script('chart-js', plugin_dir_url(__FILE__) . '../assets/js/chart.min.js', array(), '3.9.1', true);
        wp_enqueue_script('lwp-dashboard-js', plugin_dir_url(__FILE__) . '../assets/js/dashboard.js', array('jquery', 'chart-js'), "1.0.1", true);
        wp_enqueue_style('lwp-dashboard-css', plugin_dir_url(__FILE__) . '../assets/css/dashboard.css', array(), "1.0.1");

        // Product counts by location
        $product_counts = [];
        $stock_levels = [];
        $location_colors = [];
        $location_border_colors = [];

        // Generate random colors for each location
        foreach ($mulopimfwc_locations as $index => $location) {
            // Generate pastel colors
            $hue = ($index * 47) % 360; // Spread colors evenly
            $location_colors[$location->name] = "hsla({$hue}, 70%, 70%, 0.7)";
            $location_border_colors[$location->name] = "hsla({$hue}, 70%, 60%, 1.0)";

            // Get products in this location
            $products_in_location = new WP_Query([
                'post_type' => 'product',
                'posts_per_page' => -1,
                'tax_query' => [
                    [
                        'taxonomy' => 'mulopimfwc_store_location',
                        'field' => 'term_id',
                        'terms' => $location->term_id,
                    ]
                ]
            ]);

            $product_counts[$location->name] = $products_in_location->found_posts;

            // Calculate total stock for this location
            $total_stock = 0;
            if ($products_in_location->have_posts()) {
                while ($products_in_location->have_posts()) {
                    $products_in_location->the_post();
                    $product_id = get_the_ID();
                    $location_stock = get_post_meta($product_id, '_location_stock_' . $location->term_id, true);
                    $total_stock += !empty($location_stock) ? intval($location_stock) : 0;
                }
            }
            $stock_levels[$location->name] = $total_stock;

            wp_reset_postdata();
        }

        // Get orders by location data
        $orders_by_location = [];
        $location_revenue = [];
        $location_slugs = [];

        // Add default location
        $orders_by_location['Default'] = 0;
        $location_revenue['Default'] = 0;
        $location_slugs['Default'] = 'default';

        // Get slugs for all locations
        foreach ($mulopimfwc_locations as $location) {
            $location_slugs[$location->name] = $location->slug;
            $orders_by_location[$location->name] = 0;
            $location_revenue[$location->name] = 0;
        }

        // Query orders for the last 30 days
        $args = array(
            'status' => ['completed', 'pending', 'processing'], // You can change this to any order status you need
            'date_created' => '>' . gmdate('Y-m-d', strtotime('-30 days')), // Orders from the last 30 days
        );

        $orders = wc_get_orders($args);

        if (!empty($orders)) {
            foreach ($orders as $order) {
                $order_id = $order->get_id();
                $order = wc_get_order($order_id);

                if (!$order) continue;

                // Get store location from order meta
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

                // Increment count and revenue for this location
                if (isset($orders_by_location[$location_name])) {
                    $orders_by_location[$location_name]++;
                    $location_revenue[$location_name] += $order_total;
                } else {
                    $orders_by_location['Default']++;
                    $location_revenue['Default'] += $order_total;
                }
            }
        }
        wp_reset_postdata();

        // Build meta query for all location stock keys
        $location_meta_queries = [];
        if (!empty($mulopimfwc_locations) && !is_wp_error($mulopimfwc_locations)) {
            foreach ($mulopimfwc_locations as $location) {
                $location_meta_queries[] = [
                    'key' => '_location_stock_' . $location->term_id,
                    'value' => 5,
                    'compare' => '<=',
                    'type' => 'NUMERIC'
                ];
            }
        }

        // If we have location queries, use them
        if (!empty($location_meta_queries)) {
            // Add relation OR to match any location with low stock
            $meta_query = [
                'relation' => 'OR',
            ];
            $meta_query = array_merge($meta_query, $location_meta_queries);

            $low_stock_query = new WP_Query([
                'post_type' => 'product',
                'posts_per_page' => 50, // Increased since we might have more results
                'meta_query' => $meta_query
            ]);
        } else {
            // Fallback to original query if no locations found
            $low_stock_query = new WP_Query([
                'post_type' => 'product',
                'posts_per_page' => 10,
                'meta_query' => [
                    [
                        'key' => '_stock',
                        'value' => 5,
                        'compare' => '<=',
                        'type' => 'NUMERIC'
                    ]
                ]
            ]);
        }

        // Prepare data for recent products chart (last 30 days)
        $days = 30;
        $date_counts = [];
        $labels = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = gmdate('Y-m-d', strtotime("-$i days"));
            $labels[] = gmdate('M d', strtotime("-$i days"));

            $args = [
                'post_type' => 'product',
                'posts_per_page' => -1,
                'date_query' => [
                    [
                        'year'  => gmdate('Y', strtotime($date)),
                        'month' => gmdate('m', strtotime($date)),
                        'day'   => gmdate('d', strtotime($date)),
                    ]
                ]
            ];

            $daily_query = new WP_Query($args);
            $date_counts[] = $daily_query->found_posts;
        }

        // Get monthly investment data
        $monthly_investment_data = $this->get_monthly_investment_data();

        wp_localize_script('lwp-dashboard-js', 'mulopimfwc_DashboardData', [
            'productCounts' => $product_counts,
            'stockLevels' => $stock_levels,
            'locationColors' => $location_colors,
            'locationBorderColors' => $location_border_colors,
            'dateLabels' => $labels,
            'dateCounts' => $date_counts,
            'ordersByLocation' => $orders_by_location,
            'revenueByLocation' => $location_revenue,
            'monthlyInvestmentLabels' => $monthly_investment_data['labels'],
            'monthlyInvestmentData' => $monthly_investment_data['data'],
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
            <h1><?php echo esc_attr_e('Location Wise Products Dashboard', 'multi-location-product-and-inventory-management'); ?></h1>

            <div class="lwp-dashboard-overview">
                <div class="lwp-card lwp-card-stats">
                    <h2><?php echo esc_html_e('Quick Stats', 'multi-location-product-and-inventory-management'); ?></h2>
                    <div class="lwp-stats-grid">
                        <div class="lwp-stat-item">
                            <span class="lwp-stat-value"><?php echo esc_html(wp_count_posts('product')->publish); ?></span>
                            <span class="lwp-stat-label"><?php echo esc_html_e('Total Products', 'multi-location-product-and-inventory-management'); ?></span>
                        </div>
                        <div class="lwp-stat-item">
                            <span class="lwp-stat-value"><?php echo count($mulopimfwc_locations); ?></span>
                            <span class="lwp-stat-label"><?php echo esc_html_e('Locations', 'multi-location-product-and-inventory-management'); ?></span>
                        </div>
                        <div class="lwp-stat-item">
                            <span class="lwp-stat-value"><?php echo esc_html(array_sum($orders_by_location)); ?></span>
                            <span class="lwp-stat-label"><?php echo esc_html_e('Orders (30 days)', 'multi-location-product-and-inventory-management'); ?></span>
                        </div>
                        <div class="lwp-stat-item">
                            <span class="lwp-stat-value"><?php echo wp_kses_post(wc_price($this->calculate_total_purchase_value())); ?></span>
                            <span class="lwp-stat-label"><?php echo esc_html_e('Total Investment', 'multi-location-product-and-inventory-management'); ?></span>
                        </div>
                        <div class="lwp-stat-item">
                            <span class="lwp-stat-value"><?php echo wp_kses_post(wc_price(array_sum($location_revenue))); ?></span>
                            <span class="lwp-stat-label"><?php echo esc_html_e('Revenue (30 days)', 'multi-location-product-and-inventory-management'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lwp-dashboard-charts">
                <div class="lwp-row">
                    <div class="lwp-col">
                        <div class="lwp-card">
                            <h2><?php echo esc_html_e('Products by Location', 'multi-location-product-and-inventory-management'); ?></h2>
                            <div class="lwp-chart-container">
                                <canvas id="locationProductsChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="lwp-col">
                        <div class="lwp-card">
                            <h2><?php echo esc_html_e('Stock Levels by Location', 'multi-location-product-and-inventory-management'); ?></h2>
                            <div class="lwp-chart-container">
                                <canvas id="locationStockChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lwp-row">
                    <div class="lwp-col">
                        <div class="lwp-card">
                            <h2><?php echo esc_html_e('Orders by Location (30 days)', 'multi-location-product-and-inventory-management'); ?></h2>
                            <div class="lwp-chart-container">
                                <canvas id="ordersByLocationChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="lwp-col">
                        <div class="lwp-card">
                            <h2><?php echo esc_html_e('Revenue by Location (30 days)', 'multi-location-product-and-inventory-management'); ?></h2>
                            <div class="lwp-chart-container">
                                <canvas id="revenueByLocationChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lwp-row">
                    <div class="lwp-col">
                        <div class="lwp-card">
                            <h2><?php echo esc_html_e('New Products (Last 30 Days)', 'multi-location-product-and-inventory-management'); ?></h2>
                            <div class="lwp-chart-container">
                                <canvas id="newProductsChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="lwp-col">
                        <div class="lwp-card">
                            <h2><?php echo esc_html_e('Investment', 'multi-location-product-and-inventory-management'); ?></h2>
                            <div class="lwp-chart-container">
                                <canvas id="investment-30day"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lwp-row">
                    <div class="lwp-col">
                        <div class="lwp-card">
                            <h2><?php esc_html_e('Low Stock Products by Location', 'multi-location-product-and-inventory-management'); ?></h2>
                            <?php if ($low_stock_query->have_posts()) : ?>
                                <table class="lwp-low-stock-table">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('Product', 'multi-location-product-and-inventory-management'); ?></th>
                                            <th><?php esc_html_e('Location', 'multi-location-product-and-inventory-management'); ?></th>
                                            <th><?php esc_html_e('Stock', 'multi-location-product-and-inventory-management'); ?></th>
                                            <th><?php esc_html_e('Status', 'multi-location-product-and-inventory-management'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $displayed_combinations = []; // To avoid duplicate entries
                                        while ($low_stock_query->have_posts()) :
                                            $low_stock_query->the_post();
                                            $product_id = get_the_ID();
                                            $product = wc_get_product($product_id);

                                            // Check stock for each location for this product
                                            if (!empty($mulopimfwc_locations) && !is_wp_error($mulopimfwc_locations)) {
                                                foreach ($mulopimfwc_locations as $location) {
                                                    $location_stock_key = '_location_stock_' . $location->term_id;
                                                    $location_stock = get_post_meta($product_id, $location_stock_key, true);

                                                    // Only show if stock exists and is low (≤ 5)
                                                    if ($location_stock !== '' && is_numeric($location_stock) && $location_stock <= 5) {
                                                        $combination_key = $product_id . '_' . $location->term_id;

                                                        // Avoid duplicate entries
                                                        if (!in_array($combination_key, $displayed_combinations)) {
                                                            $displayed_combinations[] = $combination_key;
                                        ?>
                                                            <tr>
                                                                <td>
                                                                    <a href="<?php echo esc_url(get_edit_post_link($product_id)); ?>">
                                                                        <?php echo esc_html(get_the_title()); ?>
                                                                    </a>
                                                                </td>
                                                                <td><?php echo esc_html($location->name); ?></td>
                                                                <td>
                                                                    <span class="stock-quantity <?php echo $location_stock == 0 ? 'out-of-stock' : 'low-stock'; ?>">
                                                                        <?php echo esc_html($location_stock); ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <span class="stock-status <?php echo $location_stock == 0 ? 'out-of-stock' : 'low-stock'; ?>">
                                                                        <?php
                                                                        if ($location_stock == 0) {
                                                                            esc_html_e('Out of Stock', 'multi-location-product-and-inventory-management');
                                                                        } else {
                                                                            esc_html_e('Low Stock', 'multi-location-product-and-inventory-management');
                                                                        }
                                                                        ?>
                                                                    </span>
                                                                </td>
                                                            </tr>
                                        <?php
                                                        }
                                                    }
                                                }
                                            }
                                        endwhile; ?>
                                    </tbody>
                                </table>

                                <?php if (empty($displayed_combinations)) : ?>
                                    <p><?php esc_html_e('No low stock products found for any location.', 'multi-location-product-and-inventory-management'); ?></p>
                                <?php endif; ?>

                            <?php else: ?>
                                <p><?php esc_html_e('No products found.', 'multi-location-product-and-inventory-management'); ?></p>
                            <?php endif;
                            wp_reset_postdata(); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
<?php
    }
    /**
     * Calculate monthly investment data for the past 12 months
     * 
     * @return array Monthly investment data
     */
    private function get_monthly_investment_data()
    {
        $months = 12; // Past 12 months
        $monthly_data = [];
        $labels = [];

        // Generate data for each month
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = gmdate('Y-m', strtotime("-$i months"));
            $labels[] = gmdate('M Y', strtotime("-$i months"));

            $start_date = $date . '-01 00:00:00';
            $end_date = gmdate('Y-m-t 23:59:59', strtotime($start_date));

            // Calculate investment for this month
            $monthly_investment = $this->calculate_monthly_investment($start_date, $end_date);
            $monthly_data[] = $monthly_investment;
        }

        return [
            'labels' => $labels,
            'data' => $monthly_data
        ];
    }

    /**
     * Calculate investment for a specific month
     * 
     * @param string $start_date Start date in Y-m-d H:i:s format
     * @param string $end_date End date in Y-m-d H:i:s format
     * @return float Total investment for the month
     */
    private function calculate_monthly_investment($start_date, $end_date)
    {
        $monthly_investment = 0;

        // Get products with status changes during this month
        $args = array(
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'date_query'     => array(
                array(
                    'after'     => $start_date,
                    'before'    => $end_date,
                    'inclusive' => true,
                ),
            ),
        );

        $products = new WP_Query($args);

        if ($products->have_posts()) {
            while ($products->have_posts()) {
                $products->the_post();
                $product_id = get_the_ID();
                $product = wc_get_product($product_id);

                if (!$product) {
                    continue;
                }

                // Get orders containing this product in the date range
                $order_count = $this->get_product_order_count($product_id, $start_date, $end_date);

                if ($product->is_type('simple')) {
                    $purchase_price = get_post_meta($product_id, '_purchase_price', true);
                    $stock_quantity = $product->get_stock_quantity() ?: 0;

                    if (is_numeric($purchase_price)) {
                        // Investment = purchase_price * (current_stock + number_of_orders)
                        $monthly_investment += floatval($purchase_price) * ($stock_quantity + $order_count);
                    }
                } elseif ($product->is_type('variable')) {
                    $variations = $product->get_available_variations();

                    foreach ($variations as $variation) {
                        $variation_id = $variation['variation_id'];
                        $variation_obj = wc_get_product($variation_id);

                        if (!$variation_obj) {
                            continue;
                        }

                        $purchase_price = get_post_meta($variation_id, '_purchase_price', true);
                        $stock_quantity = $variation_obj->get_stock_quantity() ?: 0;
                        $variation_order_count = $this->get_product_order_count($variation_id, $start_date, $end_date);

                        if (is_numeric($purchase_price)) {
                            // Investment = purchase_price * (current_stock + number_of_orders)
                            $monthly_investment += floatval($purchase_price) * ($stock_quantity + $variation_order_count);
                        }
                    }
                }
            }
        }

        wp_reset_postdata();
        return $monthly_investment;
    }

    /**
     * Get order count for a specific product in a date range
     * 
     * @param int $product_id Product ID or variation ID
     * @param string $start_date Start date in Y-m-d H:i:s format
     * @param string $end_date End date in Y-m-d H:i:s format
     * @return int Number of orders containing this product
     */
    private function get_product_order_count($product_id, $start_date, $end_date)
    {
        $order_count = 0;

        $orders = wc_get_orders(array(
            'limit'        => -1,
            'type'         => 'shop_order',
            'status'       => array('wc-completed', 'wc-processing', 'wc-on-hold'),
            'date_created' => strtotime($start_date) . '...' . strtotime($end_date),
        ));

        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $item_product_id = $item->get_product_id();
                $item_variation_id = $item->get_variation_id();

                $check_id = $item_variation_id ? $item_variation_id : $item_product_id;

                if ($check_id == $product_id) {
                    $order_count += $item->get_quantity();
                }
            }
        }

        return $order_count;
    }

    /**
     * Calculate total purchase value of all products
     *
     * @return float The total purchase value
     */
    public function calculate_total_purchase_value()
    {
        $total_value = 0;

        $args = array(
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        );

        $products = new WP_Query($args);

        if ($products->have_posts()) {
            while ($products->have_posts()) {
                $products->the_post();
                $product_id = get_the_ID();
                $product = wc_get_product($product_id);

                if (!$product) {
                    continue;
                }

                // Get orders containing this product in the last 30 days
                $order_count = $this->get_product_order_count(
                    $product_id,
                    gmdate('Y-m-d H:i:s', strtotime('-30 days')),
                    gmdate('Y-m-d H:i:s')
                );

                if ($product->is_type('simple')) {
                    $purchase_price = get_post_meta($product_id, '_purchase_price', true);
                    $stock_quantity = $product->get_stock_quantity() ?: 0;

                    if (is_numeric($purchase_price)) {
                        // Value = purchase_price * (current_stock + number_of_orders)
                        $total_value += floatval($purchase_price) * ($stock_quantity + $order_count);
                    }
                } elseif ($product->is_type('variable')) {
                    $variations = $product->get_available_variations();

                    foreach ($variations as $variation) {
                        $variation_id = $variation['variation_id'];
                        $variation_obj = wc_get_product($variation_id);

                        if (!$variation_obj) {
                            continue;
                        }

                        $purchase_price = get_post_meta($variation_id, '_purchase_price', true);
                        $stock_quantity = $variation_obj->get_stock_quantity() ?: 0;
                        $variation_order_count = $this->get_product_order_count(
                            $variation_id,
                            gmdate('Y-m-d H:i:s', strtotime('-30 days')),
                            gmdate('Y-m-d H:i:s')
                        );

                        if (is_numeric($purchase_price)) {
                            // Value = purchase_price * (current_stock + number_of_orders)
                            $total_value += floatval($purchase_price) * ($stock_quantity + $variation_order_count);
                        }
                    }
                }
            }
        }

        wp_reset_postdata();
        return $total_value;
    }
}
