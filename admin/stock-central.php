<?php

if (!defined('ABSPATH')) exit;

class mulopimfwc_Stock_Central
{
    public function __construct() {}

    public function location_stock_page_content()
    {
        // Include required file for WP_List_Table
        if (!class_exists('WP_List_Table')) {
            require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
        }

        // Include our custom table class
        require_once plugin_dir_path(__FILE__) . '../includes/class-product-location-table.php';

        // Create an instance of our table class
        $product_table = new mulopimfwc_Product_Location_Table();

        // Prepare the items to display in the table
        $product_table->prepare_items();

?>
        <div class="wrap">
            <h1><?php echo esc_html_e('Location Wise Products Stock Management', 'multi-location-product-and-inventory-management'); ?></h1>
            <p><?php echo esc_html_e('Manage stock levels and prices for each product by location.', 'multi-location-product-and-inventory-management'); ?></p>

            <form method="post">
                <?php $product_table->search_box('Search Products', 'search_products'); ?>
                <?php $product_table->display(); ?>
            </form>
        </div>
<?php
    }
}
