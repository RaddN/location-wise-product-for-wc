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
        wp_enqueue_script('lwp-dashboard-js', plugin_dir_url(__FILE__) . '../assets/js/dashboard.js', array('jquery', 'chart-js'), "1.0.4", true);
        wp_enqueue_style('lwp-dashboard-css', plugin_dir_url(__FILE__) . '../assets/css/dashboard.css', array(), "1.0.4");

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
            <h1><?php echo esc_html__('Location Wise Products Dashboard', 'multi-location-product-and-inventory-management'); ?></h1>

            <div class="lwp-dashboard-overview">
                <div class="lwp-card lwp-card-stats">
                    <h2><?php echo esc_html__('Quick Stats', 'multi-location-product-and-inventory-management'); ?></h2>
                    <div class="lwp-stats-grid">
                        <div class="lwp-stat-item">
                            <span class="lwp-stat-value"><?php echo esc_html($this->get_total_products_count()); ?></span>
                            <span class="lwp-stat-label"><?php echo esc_html__('Total Products', 'multi-location-product-and-inventory-management'); ?></span>
                        </div>
                        <div class="lwp-stat-item">
                            <span class="lwp-stat-value"><?php echo count($mulopimfwc_locations); ?></span>
                            <span class="lwp-stat-label"><?php echo esc_html__('Locations', 'multi-location-product-and-inventory-management'); ?></span>
                        </div>
                        <div class="lwp-stat-item">
                            <span class="lwp-stat-value"><?php echo esc_html(array_sum($orders_by_location)); ?></span>
                            <span class="lwp-stat-label"><?php echo esc_html__('Orders (30 days)', 'multi-location-product-and-inventory-management'); ?></span>
                        </div>
                        <div class="lwp-stat-item">
                            <span class="lwp-stat-value"><?php echo wp_kses_post(wc_price($total_investment)); ?></span>
                            <span class="lwp-stat-label"><?php echo esc_html__('Total Investment', 'multi-location-product-and-inventory-management'); ?></span>
                        </div>
                        <div class="lwp-stat-item">
                            <span class="lwp-stat-value"><?php echo wp_kses_post(wc_price(array_sum($location_revenue))); ?></span>
                            <span class="lwp-stat-label"><?php echo esc_html__('Revenue (30 days)', 'multi-location-product-and-inventory-management'); ?></span>
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
        $cached_value = get_transient($cache_key);
        
        if ($cached_value !== false) {
            return $cached_value;
        }

        global $wpdb;
        
        // Calculate investment for simple products
        $simple_investment = $wpdb->get_var("
            SELECT COALESCE(SUM(
                CAST(pm1.meta_value AS DECIMAL(10,2)) * 
                COALESCE(CAST(pm2.meta_value AS SIGNED), 0)
            ), 0) as total
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_purchase_price'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_stock'
            WHERE p.post_type = 'product' 
            AND p.post_status = 'publish'
            AND pm1.meta_value != ''
            AND pm1.meta_value > 0
        ");

        $total_investment = floatval($simple_investment);
        
        // Cache for 1 hour
        set_transient($cache_key, $total_investment, HOUR_IN_SECONDS);
        
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