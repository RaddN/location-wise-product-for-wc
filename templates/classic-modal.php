<?php
if (!defined('ABSPATH')) exit;
$options = $this->get_display_options();
global $MULOPIMFWC_Admin;

$show_title = isset($options['title_show_popup']) && $options['title_show_popup'] === 'on';
$popup_title = mulopimfwc_get_text_value('mulopimfwc_popup_title');
$button_text = mulopimfwc_get_text_value('mulopimfwc_popup_btn_txt');

$popup_data = [
    'i18n' => [
        'detecting' => mulopimfwc_get_text_value('text_popup_msg_detecting'),
        'detectFailed' => mulopimfwc_get_text_value('text_popup_msg_detect_failed'),
        'distanceFromYou' => mulopimfwc_get_text_value('text_popup_msg_distance_from_you'),
        'distanceApproximate' => mulopimfwc_get_text_value('text_popup_msg_distance_approximate'),
        'distanceAway' => mulopimfwc_get_text_value('text_popup_msg_distance_away'),
        'distanceUnit' => mulopimfwc_get_text_value('text_popup_msg_distance_unit'),
    ],
];

$modal_id = isset($GLOBALS['mulopimfwc_modal_id']) ? $GLOBALS['mulopimfwc_modal_id'] : 'lwp-store-selector-modal';
$instance_id = isset($GLOBALS['mulopimfwc_modal_instance_id']) ? $GLOBALS['mulopimfwc_modal_instance_id'] : '';
$modal_class = 'lwp-classic-popup';
if (!empty($instance_id)) {
    $modal_class .= ' mulopimfwc-popup-instance-' . sanitize_html_class($instance_id);
}
$allow_backdrop_close = !empty($GLOBALS['mulopimfwc_modal_allow_backdrop_close']);

// Store popup data in data attribute to avoid conflicts with multiple modals
$popup_data_json = wp_json_encode($popup_data);
?>
<div id="<?php echo esc_attr($modal_id); ?>" 
     class="<?php echo esc_attr($modal_class); ?>" 
     data-popup-data="<?php echo esc_attr($popup_data_json); ?>"
     data-allow-backdrop-close="<?php echo $allow_backdrop_close ? '1' : '0'; ?>"
     style="display: <?php echo $show_modal ? 'flex' : 'none'; ?>;">
    <div class="lwp-classic-popup__panel">
        <div class="lwp-classic-popup__header">
            <?php if ($show_title) { ?>
                <h2 class="lwp-classic-popup__title"><?php echo esc_html($popup_title); ?></h2>
            <?php } ?>
            <p class="lwp-classic-popup__subtitle"><?php echo esc_html(mulopimfwc_get_text_value('text_popup_subtitle')); ?></p>
        </div>

        <div class="lwp-classic-popup__search">
            <label for="lwp-classic-search"><?php echo esc_html(mulopimfwc_get_text_value('text_popup_label_search_locations')); ?></label>
            <input type="text" id="lwp-classic-search" placeholder="<?php echo esc_attr(mulopimfwc_get_text_value('text_popup_placeholder_search')); ?>" autocomplete="off">
        </div>
        <div id="lwp-classic-location-status" class="lwp-classic-popup__status" data-state="idle"></div>

        <div class="lwp-classic-popup__list">
            <?php
            if (!empty($locations) && !is_wp_error($locations)) {
                foreach ($locations as $location) {
                    $term_id = $location->term_id;
                    $street = get_term_meta($term_id, 'street_address', true);
                    $city = get_term_meta($term_id, 'city', true);
                    $state = get_term_meta($term_id, 'state', true);
                    $postal = get_term_meta($term_id, 'postal_code', true);
                    $country = get_term_meta($term_id, 'country', true);
                    $lat = get_term_meta($term_id, 'latitude', true);
                    $lng = get_term_meta($term_id, 'longitude', true);
                    $logo_id = get_term_meta($term_id, 'logo_id', true);
                    $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'thumbnail') : '';
                    $status = ['open' => false];
                    if (!empty($MULOPIMFWC_Admin) && method_exists($MULOPIMFWC_Admin, 'is_location_open_now')) {
                        $status = $MULOPIMFWC_Admin->is_location_open_now($term_id);
                    }
                    $status_label = $status['open']
                        ? mulopimfwc_get_text_value('text_status_open_now')
                        : mulopimfwc_get_text_value('text_status_closed');

                    $address_line = trim(implode(', ', array_filter([$street, $city, $state, $postal, $country])));
                    $search_text = strtolower($location->name . ' ' . $address_line);
            ?>
                    <div class="lwp-classic-location"
                        data-slug="<?php echo esc_attr(rawurldecode($location->slug)); ?>"
                        data-search="<?php echo esc_attr($search_text); ?>"
                        data-lat="<?php echo esc_attr($lat); ?>"
                        data-lng="<?php echo esc_attr($lng); ?>">
                        <div class="lwp-classic-location__logo">
                            <?php if ($logo_url) { ?>
                                <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($location->name); ?> logo" loading="lazy">
                            <?php } else { ?>
                                <span><?php echo esc_html(strtoupper(substr($location->name, 0, 1))); ?></span>
                            <?php } ?>
                        </div>
                        <div class="lwp-classic-location__info">
                            <div class="lwp-classic-location__title">
                                <h3><?php echo esc_html($location->name); ?></h3>
                                <span class="lwp-classic-location__status lwp-classic-location__status--<?php echo $status['open'] ? 'open' : 'closed'; ?>">
                                    <?php echo esc_html($status_label); ?>
                                </span>
                                <span class="lwp-classic-location__distance" data-distance></span>
                            </div>
                            <?php if ($address_line) { ?>
                                <p class="lwp-classic-location__address"><?php echo esc_html($address_line); ?></p>
                            <?php } ?>
                        </div>
                        <button type="button" class="lwp-classic-select" data-slug="<?php echo esc_attr(rawurldecode($location->slug)); ?>">
                            <?php echo esc_html(mulopimfwc_get_text_value('mulopimfwc_popup_btn_txt')); ?>
                        </button>
                    </div>
            <?php
                }
            } else {
                echo '<div class="lwp-classic-empty">' . esc_html(mulopimfwc_get_text_value('text_popup_msg_no_locations')) . '</div>';
            }
            ?>
            <div class="lwp-classic-empty" style="display:none;"><?php echo esc_html(mulopimfwc_get_text_value('text_popup_msg_no_results')); ?></div>
        </div>

        <div class="lwp-classic-popup__footer">
            <input type="hidden" id="lwp-selected-store" name="mulopimfwc_selected_store" value="">
            <button type="button" id="lwp-store-selector-submit" class="button"><?php echo esc_html($button_text); ?></button>
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
