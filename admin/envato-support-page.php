<?php
if (!defined('ABSPATH')) {
    exit;
}

function mulopimfwc_render_envato_updates_support_page()
{
    $downloads_url = function_exists('mulopimfwc_get_envato_downloads_url')
        ? mulopimfwc_get_envato_downloads_url()
        : 'https://codecanyon.net/downloads';

    $support_url = function_exists('mulopimfwc_get_support_url')
        ? mulopimfwc_get_support_url()
        : 'https://codecanyon.net/user/plugincypro';
    ?>
    <div class="wrap mulopimfwc-envato-support-page">
        <h2><?php echo esc_html__('Updates & Support', 'multi-location-product-and-inventory-management-pro'); ?></h2>
        <p>
            <?php echo esc_html__('This package is ready to use after installation. Download updates from your Envato account and upload the latest ZIP in WordPress.', 'multi-location-product-and-inventory-management-pro'); ?>
        </p>

        <div class="postbox">
            <div class="inside">
                <h3><?php echo esc_html__('Manual Updates', 'multi-location-product-and-inventory-management-pro'); ?></h3>
                <p>
                    <?php echo esc_html__('Download the latest plugin ZIP from your Envato downloads page, then upload the ZIP from WordPress Admin > Plugins > Add New > Upload Plugin.', 'multi-location-product-and-inventory-management-pro'); ?>
                </p>
                <p>
                    <a class="button button-primary" href="<?php echo esc_url($downloads_url); ?>" target="_blank" rel="noopener noreferrer">
                        <?php echo esc_html__('Open Envato Downloads', 'multi-location-product-and-inventory-management-pro'); ?>
                    </a>
                </p>
            </div>
        </div>

        <div class="postbox">
            <div class="inside">
                <h3><?php echo esc_html__('Support', 'multi-location-product-and-inventory-management-pro'); ?></h3>
                <p>
                    <?php echo esc_html__('Use the support channel published for the CodeCanyon item.', 'multi-location-product-and-inventory-management-pro'); ?>
                </p>
                <p>
                    <a class="button" href="<?php echo esc_url($support_url); ?>" target="_blank" rel="noopener noreferrer">
                        <?php echo esc_html__('Open Support Page', 'multi-location-product-and-inventory-management-pro'); ?>
                    </a>
                </p>
            </div>
        </div>
    </div>
    <?php
}
