<?php
if (!defined('ABSPATH')) exit;
$options = $this->get_display_options();

$show_title = isset($options['title_show_popup']) && $options['title_show_popup'] === 'on';
$popup_title = $options['mulopimfwc_popup_title'] ?? 'Select Your Location';
$button_text = isset($options['mulopimfwc_popup_btn_txt']) && trim((string) $options['mulopimfwc_popup_btn_txt']) !== ''
    ? $options['mulopimfwc_popup_btn_txt']
    : __('Select Location', 'multi-location-product-and-inventory-management');

$layout = isset($popup_layout) ? $popup_layout : 'tabs';
$layout_class = sanitize_html_class($layout, 'tabs');

$subtitle = __('Choose a store location to continue shopping with accurate availability.', 'multi-location-product-and-inventory-management');
$renderer = class_exists('MULOPIMFWC_Frontend_Location_Information')
    ? MULOPIMFWC_Frontend_Location_Information::get_instance()
    : null;
?>
<div id="lwp-store-selector-modal"
    class="lwp-location-info-popup lwp-location-info-popup--<?php echo esc_attr($layout_class); ?>"
    data-layout="<?php echo esc_attr($layout_class); ?>"
    style="display: <?php echo $show_modal ? 'flex' : 'none'; ?>;">
    <div class="lwp-location-info-popup__panel">
        <div class="lwp-location-info-popup__header">
            <?php if ($show_title) { ?>
                <h2 class="lwp-location-info-popup__title"><?php echo esc_html($popup_title); ?></h2>
            <?php } ?>
            <p class="lwp-location-info-popup__subtitle"><?php echo esc_html($subtitle); ?></p>
        </div>

        <div class="lwp-location-info-popup__content">
            <?php
            if ($renderer && method_exists($renderer, 'render_popup_locations')) {
                $renderer->render_popup_locations($locations, $layout_class);
            } else {
                echo '<p class="lwp-location-info-popup__empty">' .
                    esc_html__('Location layouts are unavailable.', 'multi-location-product-and-inventory-management') .
                    '</p>';
            }
            ?>
        </div>

        <div class="lwp-location-info-popup__footer">
            <input type="hidden" id="lwp-selected-store" name="mulopimfwc_selected_store" value="">
            <button type="button" id="lwp-store-selector-submit" class="button">
                <?php echo esc_html($button_text); ?>
            </button>
        </div>
    </div>
</div>

<?php
if (!empty($options["mulopimfwc_popup_custom_css"])) {
    if (!wp_style_is('mulopimfwc_custom_style', 'registered')) {
        wp_register_style('mulopimfwc_custom_style', false);
    }
    wp_enqueue_style('mulopimfwc_custom_style');
    wp_add_inline_style('mulopimfwc_custom_style', $options["mulopimfwc_popup_custom_css"]);
}
?>
