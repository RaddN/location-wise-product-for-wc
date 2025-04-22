<?php 

if (!defined('ABSPATH')) exit;
// Template for the store location selector shortcode
?>
<div class="lwp-shortcode-store-selector <?php echo esc_attr($atts['class']); ?>">
    <?php if ($atts['show_title'] === 'yes'): ?>
        <h3 class="lwp-shortcode-title"><?php echo esc_html($atts['title']); ?></h3>
    <?php endif; ?>
    
    <form id="lwp-shortcode-selector-form" class="lwp-selector-form">
        <?php 
                wp_nonce_field('plugincylwp_shortcode_selector', 'plugincylwp_shortcode_selector_nonce');
            ?>
        <select id="lwp-shortcode-selector" class="lwp-location-dropdown">
            <option value=""><?php esc_html_e('-- Select a Store --', 'location-wise-products-for-woocommerce'); ?></option>
            
            <?php if ($is_admin_or_manager): ?>
                <?php $selected = ($selected_location === 'all-products') ? 'selected' : ''; ?>
                <option value="all-products" <?php echo esc_attr($selected); ?>><?php esc_html_e('All Products', 'location-wise-products-for-woocommerce'); ?></option>
            <?php endif; ?>
            
            <?php if (!empty($locations) && !is_wp_error($locations)): ?>
                <?php foreach ($locations as $location): ?>
                    <?php $selected = ($location->slug === $selected_location) ? 'selected' : ''; ?>
                    <option value="<?php echo esc_attr($location->slug); ?>" <?php echo esc_attr($selected); ?>>
                        <?php echo esc_html($location->name); ?>
                    </option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>
        
        <!-- <button type="button" class="button lwp-shortcode-submit">
            <?php 
            // esc_html_e('Change Location', 'location-wise-products-for-woocommerce');
             ?>
        </button> -->
    </form>
</div>