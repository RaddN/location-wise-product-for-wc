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

$mulopimfwc_location_archive_sidebar = '';

if (apply_filters('mulopimfwc_location_archive_show_sidebar', true)) {
    ob_start();
    do_action('woocommerce_sidebar');
    $mulopimfwc_location_archive_sidebar = trim(ob_get_clean());
}

$mulopimfwc_location_archive_has_sidebar = $mulopimfwc_location_archive_sidebar !== '';
$mulopimfwc_location_archive_layout_classes = [
    'mulopimfwc-location-shop-layout',
    $mulopimfwc_location_archive_has_sidebar ? 'mulopimfwc-location-shop-layout--has-sidebar' : 'mulopimfwc-location-shop-layout--no-sidebar',
];

?>

<div class="mulopimfwc-location-archive-wrapper woocommerce">
    
    <?php if (have_posts()) : ?>

        <header class="mulopimfwc-location-archive-header">
            <?php
            /**
             * Hook: mulopimfwc_store_location_description_before_loop
             * 
             * @hooked MULOPIMFWC_Frontend_Location_Information::display_location_archive_info - 10
             */
            do_action('mulopimfwc_store_location_description_before_loop');
            ?>
        </header>

        <?php
        /**
         * Hook: mulopimfwc_before_location_products
         */
        do_action('mulopimfwc_before_location_products');
        ?>

        <div class="<?php echo esc_attr(implode(' ', $mulopimfwc_location_archive_layout_classes)); ?>">

            <?php if ($mulopimfwc_location_archive_has_sidebar) : ?>
                <aside class="mulopimfwc-location-shop-sidebar" aria-label="<?php echo esc_attr__('Shop sidebar', 'multi-location-product-and-inventory-management-pro'); ?>">
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sidebar widgets are rendered by registered WooCommerce/theme callbacks.
                    echo $mulopimfwc_location_archive_sidebar;
                    ?>
                </aside>
            <?php endif; ?>

            <div class="mulopimfwc-location-shop-main">

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

            </div>

        </div>

    <?php else : ?>

        <div class="<?php echo esc_attr(implode(' ', $mulopimfwc_location_archive_layout_classes)); ?>">

            <?php if ($mulopimfwc_location_archive_has_sidebar) : ?>
                <aside class="mulopimfwc-location-shop-sidebar" aria-label="<?php echo esc_attr__('Shop sidebar', 'multi-location-product-and-inventory-management-pro'); ?>">
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sidebar widgets are rendered by registered WooCommerce/theme callbacks.
                    echo $mulopimfwc_location_archive_sidebar;
                    ?>
                </aside>
            <?php endif; ?>

            <div class="mulopimfwc-location-shop-main">
                <div class="mulopimfwc-location-products mulopimfwc-location-products--empty">
                    <?php
                    /**
                     * Hook: woocommerce_no_products_found
                     *
                     * @hooked wc_no_products_found - 10
                     */
                    do_action('woocommerce_no_products_found');
                    ?>
                </div>
            </div>

        </div>

    <?php endif; ?>

</div>

<?php

/**
 * Hook: mulopimfwc_after_main_content
 */
do_action('mulopimfwc_after_main_content');

get_footer('shop');
