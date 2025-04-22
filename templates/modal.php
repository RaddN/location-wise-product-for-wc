<?php 
if (!defined('ABSPATH')) exit;
?>
<div id="lwp-store-selector-modal" style="display: <?php echo $show_modal ? 'flex' : 'none'; ?>;">
    <div class="lwp-store-selector-content">
        <h2><?php esc_html_e('Select Your Store', 'location-wise-products-for-woocommerce'); ?></h2>
        <form id="lwp-store-selector-form-modal">            
            <?php wp_nonce_field('plugincylwp_modal_selector', 'plugincylwp_modal_selector_nonce'); ?>
            <select id="lwp-store-selector-modal-dropdown">
                <option value=""><?php esc_html_e('-- Select a Store --', 'location-wise-products-for-woocommerce'); ?></option>
                <?php
                if (!empty($locations) && !is_wp_error($locations)) {
                    foreach ($locations as $location) {
                        echo '<option value="' . esc_attr($location->slug) . '">' . esc_html($location->name) . '</option>';
                    }
                }
                ?>
            </select>
            <button type="button" id="lwp-store-selector-submit" class="button"><?php esc_html_e('Confirm', 'location-wise-products-for-woocommerce'); ?></button>
        </form>
    </div>
</div>
