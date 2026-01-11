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
        <div class="wrap mlsctock-cenral-main">
            <h1 style="display: none !important;"><?php echo esc_html__('Location Wise Products Stock Management', 'multi-location-product-and-inventory-management'); ?></h1>
            <div class="mlsctock-cenral-header">
                <h1><?php echo esc_html__('Location Wise Products Stock Management', 'multi-location-product-and-inventory-management'); ?></h1>
                <p><?php echo esc_html__('Manage stock levels and prices for each product by location.', 'multi-location-product-and-inventory-management'); ?></p>
            </div>

            <form method="post">
                <?php $product_table->search_box(__('Search Products', 'multi-location-product-and-inventory-management'), 'search_products'); ?>
                <?php $product_table->display(); ?>
            </form>
        </div>

        <style>
            .mlsctock-cenral-main {
                border: 2px solid #d1d1d4;
                border-radius: 8px;
                background-color: #f9fafb;
                margin: 20px 20px 0px 0px;
            }

            .mlsctock-cenral-header {
                background-image: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                padding: 25px 25px;
                border-top-left-radius: 8px;
                border-top-right-radius: 8px;
            }

            .mlsctock-cenral-header h1 {
                color: #ffffff;
                font-weight: 700;
                font-size: 30px;
                padding: 0;
            }

            .mlsctock-cenral-header p {
                color: #f3e8ff;
                font-size: 18px;
                margin: 6px 0px 0px;
            }

            .mlsctock-cenral-main form {
                padding: 20px 25px;
                background-color: #ffffff;
            }

            .mlsctock-cenral-main form table {
                border-color: #e5e7eb !important;
            }

            .mlsctock-cenral-main form table thead {
                background-color: #e5e7eb;
            }

            .mlsctock-cenral-main form .widefat thead td,
            .mlsctock-cenral-main form .widefat thead th {
                border-bottom: 1px solid #e5e7eb !important;
            }

            .mlsctock-cenral-main form .alternate,
            .mlsctock-cenral-main form .striped>tbody>:nth-child(odd),
            .mlsctock-cenral-main form ul.striped>:nth-child(odd) {
                background-color: #f9fafb;
            }

            .mlsctock-cenral-main form .widefat td,
            .mlsctock-cenral-main form .widefat th {
                padding: 10px 10px;
            }

            .mlsctock-cenral-main form .widefat td,
            .mlsctock-cenral-main form th.check-column {
                padding: 20px 10px;
            }

            .mlsctock-cenral-main form th#image {
                width: 5%;
            }

            .mlsctock-cenral-main form .product-thumbnail {
                border-radius: 6px;
            }

            .mlsctock-cenral-main form .widefat thead th {
                font-size: 16px;
                font-weight: 500;
            }

            .mlsctock-cenral-main form .deactivate-location {
                background-color: #fef2f2;
                color: #dc2626;
                border-color: #fecaca;
            }

            .mlsctock-cenral-main form .activate-location {
                background-color: #f0fdf4;
                color: #15803d;
                border-color: #bbf7d0;
            }

            .mlsctock-cenral-main form .add-location,
            a.button.button-small.manage-product-location {
                background: #2563eb ! important;
                border-color: #2563eb !important;
                color: #ffffff;
                padding: 5px !important;
                font-weight: 500;
                font-size: 13px !important;
                width: 100%;
                text-align: center;
            }

            .mlsctock-cenral-main form .gross-profit-container,
            .mlsctock-cenral-main form .purchase-price-container {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }

            .mlsctock-cenral-main form .gross-profit-container .amount bdi {
                color: #15803d;
                font-weight: 500;
                font-size: 14px;
                background-color: #f0fdf4;
                padding: 2px;
                margin-right: 4px;
            }

            .location-actions {
                margin-bottom: 0px;
            }

            /* Accordion Styles */
            .variation-stock-item.accordion-item,
            .variation-price-item.accordion-item,
            .variation-gross-profit-item.accordion-item {
                margin-bottom: 10px;
                border: 1px solid #e5e7eb;
                border-radius: 6px;
                overflow: hidden;
                background-color: #ffffff;
            }

            .variation-stock-item .accordion-header,
            .variation-price-item .accordion-header,
            .variation-gross-profit-item .accordion-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 10px 12px;
                background-color: #f9fafb;
                cursor: pointer;
                user-select: none;
                transition: background-color 0.2s ease;
                border-bottom: 1px solid #e5e7eb;
            }

            .variation-stock-item .accordion-header:hover,
            .variation-price-item .accordion-header:hover,
            .variation-gross-profit-item .accordion-header:hover {
                background-color: #f3f4f6;
            }

            .variation-stock-item.accordion-expanded .accordion-header,
            .variation-price-item.accordion-expanded .accordion-header,
            .variation-gross-profit-item.accordion-expanded .accordion-header {
                background-color: #e5e7eb;
            }

            .variation-stock-item .accordion-header strong,
            .variation-price-item .accordion-header strong,
            .variation-gross-profit-item .accordion-header strong {
                font-weight: 600;
                color: #374151;
                flex: 1;
            }

            .accordion-icon {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 24px;
                height: 24px;
                font-size: 18px;
                font-weight: bold;
                color: #6b7280;
                border-radius: 4px;
                background-color: #ffffff;
                transition: transform 0.2s ease;
            }

            .variation-stock-item .accordion-content,
            .variation-price-item .accordion-content,
            .variation-gross-profit-item .accordion-content {
                max-height: 0;
                overflow: hidden;
                transition: max-height 0.3s ease, padding 0.3s ease;
                padding: 0 12px;
            }

            .variation-stock-item .accordion-content.accordion-open,
            .variation-price-item .accordion-content.accordion-open,
            .variation-gross-profit-item .accordion-content.accordion-open {
                max-height: 2000px;
                padding: 10px 12px;
            }

            .variation-stock-item .accordion-content .location-stock-item,
            .variation-price-item .accordion-content .location-price-item,
            .variation-gross-profit-item .accordion-content .location-gross-profit-item {
                margin-bottom: 8px;
            }

            .variation-stock-item .accordion-content .location-stock-item:last-child,
            .variation-price-item .accordion-content .location-price-item:last-child,
            .variation-gross-profit-item .accordion-content .location-gross-profit-item:last-child {
                margin-bottom: 0;
            }
        </style>

        <script>
        (function($) {
            $(document).ready(function() {
                // Initialize accordions - first item expanded
                $('.location-stock-container, .location-price-container, .gross-profit-container').each(function() {
                    var $container = $(this);
                    var $accordionItems = $container.find('.accordion-item');
                    
                    // First item should be expanded
                    $accordionItems.first().addClass('accordion-expanded').find('.accordion-content').addClass('accordion-open');
                    $accordionItems.first().find('.accordion-icon').text('−');
                });

                // Handle accordion toggle
                $(document).on('click', '.accordion-header', function(e) {
                    e.preventDefault();
                    var $header = $(this);
                    var $item = $header.closest('.accordion-item');
                    var $content = $header.siblings('.accordion-content');
                    var $icon = $header.find('.accordion-icon');
                    var targetId = $header.data('accordion-target');

                    // Toggle expanded class
                    $item.toggleClass('accordion-expanded');
                    $content.toggleClass('accordion-open');

                    // Update icon
                    if ($item.hasClass('accordion-expanded')) {
                        $icon.text('−');
                    } else {
                        $icon.text('+');
                    }
                });
            });
        })(jQuery);
        </script>
<?php
    }
}
