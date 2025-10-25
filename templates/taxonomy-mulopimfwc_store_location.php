<?php
/**
 * Location Archive Template
 * Template for displaying location taxonomy archive with business hours
 * 
 * @package Multi_Location_Product_Inventory_Management
 */

if (!defined('ABSPATH')) exit;

get_header('shop');

/**
 * Hook: mulopimfwc_before_main_content
 */
do_action('mulopimfwc_before_main_content');

?>

<div class="mulopimfwc-location-archive-wrapper">
    
    <?php if (have_posts()) : ?>

        <header class="mulopimfwc-location-archive-header">
            <?php
            /**
             * Hook: mulopimfwc_store_location_description
             * 
             * @hooked MULOPIMFWC_Frontend_Location_Information::display_location_archive_info - 10
             */
            do_action('mulopimfwc_store_location_description');
            ?>
        </header>

        <?php
        /**
         * Hook: mulopimfwc_before_location_products
         */
        do_action('mulopimfwc_before_location_products');
        ?>

        <div class="mulopimfwc-location-products">
            
            <?php
            /**
             * Hook: woocommerce_before_shop_loop
             * 
             * @hooked woocommerce_result_count - 20
             * @hooked woocommerce_catalog_ordering - 30
             */
            do_action('woocommerce_before_shop_loop');
            ?>

            <?php
            woocommerce_product_loop_start();

            while (have_posts()) :
                the_post();

                /**
                 * Hook: woocommerce_shop_loop
                 */
                do_action('woocommerce_shop_loop');

                wc_get_template_part('content', 'product');

            endwhile;

            woocommerce_product_loop_end();
            ?>

            <?php
            /**
             * Hook: woocommerce_after_shop_loop
             * 
             * @hooked woocommerce_pagination - 10
             */
            do_action('woocommerce_after_shop_loop');
            ?>

        </div>

        <?php
        /**
         * Hook: mulopimfwc_after_location_products
         */
        do_action('mulopimfwc_after_location_products');
        ?>

    <?php else : ?>

        <?php
        /**
         * Hook: woocommerce_no_products_found
         * 
         * @hooked wc_no_products_found - 10
         */
        do_action('woocommerce_no_products_found');
        ?>

    <?php endif; ?>

</div>

<?php

/**
 * Hook: mulopimfwc_after_main_content
 */
do_action('mulopimfwc_after_main_content');

/**
 * Hook: woocommerce_sidebar
 */
do_action('woocommerce_sidebar');

get_footer('shop');