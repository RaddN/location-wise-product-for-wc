<?php 

?>
<div id="lwp-store-selector-modal" style="display: <?php echo $show_modal ? 'flex' : 'none'; ?>;">
    <div class="lwp-store-selector-content">
        <h2><?php _e('Select Your Store', 'location-wise-product'); ?></h2>
        <form id="lwp-store-selector-form-modal">
            <select id="lwp-store-selector-modal-dropdown">
                <option value=""><?php _e('-- Select a Store --', 'location-wise-product'); ?></option>
                <?php
                if (!empty($locations) && !is_wp_error($locations)) {
                    foreach ($locations as $location) {
                        echo '<option value="' . esc_attr($location->slug) . '">' . esc_html($location->name) . '</option>';
                    }
                }
                ?>
            </select>
            <button type="button" id="lwp-store-selector-submit" class="button"><?php _e('Confirm', 'location-wise-product'); ?></button>
        </form>
    </div>
</div>
