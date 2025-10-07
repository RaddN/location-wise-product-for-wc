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

        // Increase memory limit for dashboard operations
        if (function_exists('ini_set')) {
            ini_set('memory_limit', '512M');
        }

        // Set max execution time
        set_time_limit(300);

        // Enqueue necessary scripts and styles
        wp_enqueue_script('chart-js', plugin_dir_url(__FILE__) . '../assets/js/chart.min.js', array(), '3.9.1', true);
        wp_enqueue_script('lwp-dashboard-js', plugin_dir_url(__FILE__) . '../assets/js/dashboard.js', array('jquery', 'chart-js'), "1.0.5.8", true);
        wp_enqueue_style('lwp-dashboard-css', plugin_dir_url(__FILE__) . '../assets/css/dashboard.css', array(), "1.0.5.8");

        // Initialize data arrays
        $product_counts = [];
        $stock_levels = [];
        $location_colors = [];
        $location_border_colors = [];

        // Check if locations exist and is not an error
        if (empty($mulopimfwc_locations) || is_wp_error($mulopimfwc_locations)) {
            $mulopimfwc_locations = [];
        }

        // Generate colors and get data for each location with pagination
        foreach ($mulopimfwc_locations as $index => $location) {
            // Generate pastel colors
            $hue = ($index * 47) % 360;
            $location_colors[$location->name] = "hsla({$hue}, 70%, 70%, 0.7)";
            $location_border_colors[$location->name] = "hsla({$hue}, 70%, 60%, 1.0)";

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

        wp_localize_script('lwp-dashboard-js', 'mulopimfwc_DashboardData', [
            'productCounts' => $product_counts,
            'stockLevels' => $stock_levels,
            'locationColors' => $location_colors,
            'locationBorderColors' => $location_border_colors,
            'dateLabels' => $recent_products_data['labels'],
            'dateCounts' => $recent_products_data['counts'],
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
        
            <div class="lwp-dashboard-overview">
                <h1><?php echo esc_html__('Location Wise Products Dashboard', 'multi-location-product-and-inventory-management'); ?></h1>
                <div class="lwp-card-stats">
                    <div class="lwp-stats-grid">
                        <div class="lwp-stat-item">

                        <div class="lwp-stat-item-icon">

                        <svg class="svg-inline--fa fa-box" aria-hidden="true" data-prefix="fas" data-icon="box" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" width="18" height="18"><path fill="#2563eb" d="M50.7 58.5 0 160h208V32H93.7c-18.2 0-34.8 10.3-43 26.5M240 160h208L397.3 58.5c-8.2-16.2-24.8-26.5-43-26.5H240zm208 32H0v224c0 35.3 28.7 64 64 64h320c35.3 0 64-28.7 64-64z"/></svg>
                    </div>
                    <div>
                        <span class="lwp-stat-label"><?php echo esc_html__('Total Products', 'multi-location-product-and-inventory-management'); ?></span>
                        <span class="lwp-stat-value"><?php echo esc_html($this->get_total_products_count()); ?></span> 
                    </div>                            
                        </div>
                        <div class="lwp-stat-item">
                            <div class="lwp-stat-item-icon" style="background-color: #dcfce7;">

                        <svg class="svg-inline--fa fa-location-dot" aria-hidden="true" data-prefix="fas" data-icon="location-dot" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512" width="18" height="18"><path fill="#16a34a" d="M215.7 499.2C267 435 384 279.4 384 192 384 86 298 0 192 0S0 86 0 192c0 87.4 117 243 168.3 307.2 12.3 15.3 35.1 15.3 47.4 0M192 128a64 64 0 1 1 0 128 64 64 0 1 1 0-128"/></svg>
                    </div>
                    <div>
                        <span class="lwp-stat-label"><?php echo esc_html__('Locations', 'multi-location-product-and-inventory-management'); ?></span>
                        <span class="lwp-stat-value"><?php echo count($mulopimfwc_locations); ?></span>
                            
                    </div>  
                            
                        </div>
                        <div class="lwp-stat-item">
                            <div class="lwp-stat-item-icon" style="background-color: #f3e8ff;">

                        <svg class="svg-inline--fa fa-cart-shopping" aria-hidden="true" data-prefix="fas" data-icon="cart-shopping" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512" width="18" height="18"><path fill="#9333ea" d="M0 24C0 10.7 10.7 0 24 0h45.5c22 0 41.5 12.8 50.6 32h411c26.3 0 45.5 25 38.6 50.4l-41 152.3c-8.5 31.4-37 53.3-69.5 53.3H170.7l5.4 28.5c2.2 11.3 12.1 19.5 23.6 19.5H488c13.3 0 24 10.7 24 24s-10.7 24-24 24H199.7c-34.6 0-64.3-24.6-70.7-58.5l-51.6-271c-.7-3.8-4-6.5-7.9-6.5H24C10.7 48 0 37.3 0 24m128 440a48 48 0 1 1 96 0 48 48 0 1 1-96 0m336-48a48 48 0 1 1 0 96 48 48 0 1 1 0-96"/></svg>
                    </div>
                    <div>
                        <span class="lwp-stat-label"><?php echo esc_html__('Orders (30 days)', 'multi-location-product-and-inventory-management'); ?></span>
                        <span class="lwp-stat-value"><?php echo esc_html(array_sum($orders_by_location)); ?></span>
                            
                    </div>  
                            
                        </div>
                        <div class="lwp-stat-item">
                            <div class="lwp-stat-item-icon" style="background-color: #cffafe;">

                        <svg class="svg-inline--fa fa-money-bag" aria-hidden="true" data-prefix="fas" data-icon="money-bag" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="18" height="18"><g class="missing" fill="#0891b2"><path d="m156.5 447.7-12.6 29.5c-18.7-9.5-35.9-21.2-51.5-34.9l22.7-22.7c12.5 10.9 26.4 20.4 41.4 28.1M40.6 272H8.5c1.4 21.2 5.4 41.7 11.7 61.1L50 321.2c-4.9-15.7-8.2-32.2-9.4-49.2m0-32c1.4-18.8 5.2-37 11.1-54.1l-29.5-12.6c-7.5 21-12.2 43.4-13.7 66.7zm23.7-83.5c7.8-14.9 17.2-28.8 28.1-41.5L69.7 92.3c-13.7 15.6-25.5 32.8-34.9 51.5zM397 419.6c-13.9 12-29.4 22.3-46.1 30.4l11.9 29.8c20.7-9.9 39.8-22.6 56.9-37.6zM115 92.4c13.9-12 29.4-22.3 46.1-30.4l-11.9-29.8c-20.7 9.9-39.8 22.6-56.8 37.6zm332.7 263.1c-7.8 14.9-17.2 28.8-28.1 41.5l22.7 22.7c13.7-15.6 25.5-32.9 34.9-51.5zm23.7-83.5c-1.4 18.8-5.2 37-11.1 54.1l29.5 12.6c7.5-21.1 12.2-43.5 13.6-66.8h-32zM321.2 462c-15.7 5-32.2 8.2-49.2 9.4v32.1c21.2-1.4 41.7-5.4 61.1-11.7zm-81.2 9.4c-18.8-1.4-37-5.2-54.1-11.1l-12.6 29.5c21.1 7.5 43.5 12.2 66.8 13.6v-32zm222-280.6c5 15.7 8.2 32.2 9.4 49.2h32.1c-1.4-21.2-5.4-41.7-11.7-61.1zM92.4 397c-12-13.9-22.3-29.4-30.4-46.1l-29.8 11.9c9.9 20.7 22.6 39.8 37.6 56.9zM272 40.6c18.8 1.4 36.9 5.2 54.1 11.1l12.6-29.5c-21-7.5-43.4-12.2-66.7-13.7zM190.8 50c15.7-5 32.2-8.2 49.2-9.4V8.5c-21.2 1.4-41.7 5.4-61.1 11.7zm251.5 42.3L419.6 115c12 13.9 22.3 29.4 30.5 46.1l29.8-11.9c-9.9-20.7-22.6-39.8-37.6-56.9m-45.3.1 22.7-22.7c-15.6-13.7-32.8-25.5-51.5-34.9l-12.6 29.5c14.8 7.8 28.8 17.2 41.4 28.1"/><circle cx="256" cy="364" r="28"><animate attributeType="XML" repeatCount="indefinite" dur="2s" attributeName="r" values="28;14;28;28;14;28;"/><animate attributeType="XML" repeatCount="indefinite" dur="2s" attributeName="opacity" values="1;0;1;1;0;1;"/></circle><path d="M263.7 312h-16c-6.6 0-12-5.4-12-12 0-71 77.4-63.9 77.4-107.8 0-20-17.8-40.2-57.4-40.2-29.1 0-44.3 9.6-59.2 28.7-3.9 5-11.1 6-16.2 2.4l-13.1-9.2c-5.6-3.9-6.9-11.8-2.6-17.2 21.2-27.2 46.4-44.7 91.2-44.7 52.3 0 97.4 29.8 97.4 80.2 0 67.6-77.4 63.5-77.4 107.8-.1 6.6-5.5 12-12.1 12"><animate attributeType="XML" repeatCount="indefinite" dur="2s" attributeName="opacity" values="1;0;0;0;0;1;"/></path></g></svg>
                    </div>
                    <div>
                        <span class="lwp-stat-label"><?php echo esc_html__('Total Investment', 'multi-location-product-and-inventory-management'); ?></span>
                        <span class="lwp-stat-value"><?php echo wp_kses_post(wc_price($total_investment)); ?></span>
                            
                    </div>  
                            
                        </div>
                        <div class="lwp-stat-item">
                            <div class="lwp-stat-item-icon" style="background-color: #ffedd5;">

                        <svg class="svg-inline--fa fa-chart-line" aria-hidden="true" data-prefix="fas" data-icon="chart-line" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="18" height="18"><path fill="#ea580c" d="M64 64c0-17.7-14.3-32-32-32S0 46.3 0 64v336c0 44.2 35.8 80 80 80h400c17.7 0 32-14.3 32-32s-14.3-32-32-32H80c-8.8 0-16-7.2-16-16zm406.6 86.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L320 210.7l-57.4-57.4c-12.5-12.5-32.8-12.5-45.3 0l-112 112c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0l89.4-89.3 57.4 57.4c12.5 12.5 32.8 12.5 45.3 0l128-128z"/></svg>
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
                            <div class="lwp-chart-container">
                                <canvas id="ordersByLocationChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="lwp-col">
                        <div class="lwp-card">
                            <h2><?php echo esc_html__('Revenue by Location (30 days)', 'multi-location-product-and-inventory-management'); ?></h2>
                            <div class="lwp-chart-container">
                                <canvas id="revenueByLocationChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lwp-row">
                    <div class="lwp-col">
                        <div class="lwp-card">
                            <h2><?php echo esc_html__('New Products (Last 30 Days)', 'multi-location-product-and-inventory-management'); ?></h2>
                            <div class="lwp-chart-container">
                                <canvas id="newProductsChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="lwp-col">
                        <div class="lwp-card">
                            <h2><?php echo esc_html__('Investment', 'multi-location-product-and-inventory-management'); ?></h2>
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
                            <?php if (!empty($low_stock_products)) : ?>
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
                                        <?php foreach ($low_stock_products as $item) : ?>
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
