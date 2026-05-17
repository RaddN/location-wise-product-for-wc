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
 * Hook: woocommerce_before_main_content.
 *
 * Uses the standard WooCommerce archive wrapper so themes and extensions can
 * initialize product archive scripts, layout containers, breadcrumbs, and data.
 */
do_action('woocommerce_before_main_content');

$mulopimfwc_location_archive_sidebar = '';

if (apply_filters('mulopimfwc_location_archive_show_sidebar', false, 'plugin_template')) {
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

    <?php
    /**
     * Hook: woocommerce_shop_loop_header.
     *
     * Keep the standard WooCommerce archive hook available for themes and
     * extensions. The plugin renders its richer location header separately, so
     * the default WooCommerce taxonomy title is suppressed unless requested.
     */
    $mulopimfwc_location_archive_default_header_priority = false;

    if (
        !apply_filters('mulopimfwc_location_archive_show_default_woocommerce_header', false)
        && function_exists('woocommerce_product_taxonomy_archive_header')
    ) {
        $mulopimfwc_location_archive_default_header_priority = has_action(
            'woocommerce_shop_loop_header',
            'woocommerce_product_taxonomy_archive_header'
        );

        if ($mulopimfwc_location_archive_default_header_priority !== false) {
            remove_action('woocommerce_shop_loop_header', 'woocommerce_product_taxonomy_archive_header', $mulopimfwc_location_archive_default_header_priority);
        }
    }

    do_action('woocommerce_shop_loop_header');

    if ($mulopimfwc_location_archive_default_header_priority !== false) {
        add_action('woocommerce_shop_loop_header', 'woocommerce_product_taxonomy_archive_header', $mulopimfwc_location_archive_default_header_priority);
    }
    ?>
    
    <?php if (woocommerce_product_loop()) : ?>

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
 * Hook: woocommerce_after_main_content.
 *
 * @hooked woocommerce_output_content_wrapper_end - 10
 */
do_action('woocommerce_after_main_content');

get_footer('shop');
