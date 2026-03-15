<?php
// templates/shortcode-selector.php
if (!defined('ABSPATH')) exit;
// Template for the store location selector shortcode
// Read store location cookie via helper to respect filters.
$store_cookie_value = function_exists('mulopimfwc_get_store_location_cookie')
    ? mulopimfwc_get_store_location_cookie()
    : '';
?>
<?php
// If no unit is present, append 'px'
$max_width = trim($atts['max_width']);
if ($max_width !== '' && !preg_match('/(px|em|rem|vw|vh|%|pt|cm|mm|in|ex|ch)$/i', $max_width)) {
    $max_width .= 'px';
}

$shortcode_texts = [
    'enter_address' => mulopimfwc_get_text_value('text_shortcode_enter_your_address'),
    'saved_address' => mulopimfwc_get_text_value('text_shortcode_saved_address'),
    'add_new_address' => mulopimfwc_get_text_value('text_shortcode_add_new_address'),
    'set_your_location' => mulopimfwc_get_text_value('text_shortcode_set_your_location'),
    'continue' => mulopimfwc_get_text_value('text_shortcode_continue'),
    'location_details' => mulopimfwc_get_text_value('text_shortcode_location_details'),
    'label' => mulopimfwc_get_text_value('text_shortcode_label'),
    'home' => mulopimfwc_get_text_value('text_shortcode_home'),
    'work' => mulopimfwc_get_text_value('text_shortcode_work'),
    'partner' => mulopimfwc_get_text_value('text_shortcode_partner'),
    'other' => mulopimfwc_get_text_value('text_shortcode_other'),
    'street_address' => mulopimfwc_get_text_value('text_shortcode_street_address'),
    'apartment_suite' => mulopimfwc_get_text_value('text_shortcode_apartment_suite'),
    'note' => mulopimfwc_get_text_value('text_shortcode_note'),
    'back' => mulopimfwc_get_text_value('text_shortcode_back'),
    'save_location' => mulopimfwc_get_text_value('text_shortcode_save_location'),
];

$saved_location_label_map = [
    'home' => $shortcode_texts['home'],
    'work' => $shortcode_texts['work'],
    'partner' => $shortcode_texts['partner'],
    'other' => $shortcode_texts['other'],
];

$translate_saved_location_label = function ($label) use ($saved_location_label_map) {
    $raw_label = is_string($label) ? trim($label) : '';
    if ($raw_label === '') {
        return '';
    }

    $normalized = strtolower($raw_label);
    if (array_key_exists($normalized, $saved_location_label_map)) {
        return $saved_location_label_map[$normalized];
    }

    return $raw_label;
};
?>
<div class="lwp-shortcode-store-selector <?php echo esc_attr($atts['class']); ?>" style="max-width: <?php echo esc_attr($max_width); ?>;">
    <?php if ($atts['show_title'] === 'on'): ?>
        <h3 class="lwp-shortcode-title"><?php echo esc_html($atts['title']); ?></h3>
    <?php endif; ?>
    <?php if ($atts['enable_user_locations'] === 'on'): ?>
        <div class="lwp-user-location-features">
            <div class="address-content" id="address-trigger">
                <svg aria-hidden="true" class="address-label-icon" width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12.322 2a8.322 8.322 0 0 1 7.234 12.44l-1.085 1.451q-.897.938-3.566 3.57l-1.884 1.852a1 1 0 0 1-1.4 0l-3.703-3.656-1.322-1.329q-.466-.477-1.509-1.89A8.322 8.322 0 0 1 12.322 2m0 1.5a6.822 6.822 0 0 0-6.083 9.914l.133.246.592.884.218.236.59.605c.462.469 1.11 1.116 1.93 1.93l2.215 2.185.123.12a.4.4 0 0 0 .561 0l.074-.072 2.627-2.59 1.903-1.912.329-.342.153-.17.585-.875.133-.243a6.8 6.8 0 0 0 .732-2.767l.008-.327A6.82 6.82 0 0 0 12.322 3.5m0 3.25a3.75 3.75 0 1 1 0 7.5 3.75 3.75 0 0 1 0-7.5m0 1.5a2.25 2.25 0 1 0 0 4.5 2.25 2.25 0 0 0 0-4.5" />
                </svg>
                <span class="address-text">
                    <?php
                    $current_location = '';
                    $location_set = false;

                    // Check if user has a selected location
                    if ($is_user_logged_in) {
                        $user_locations = get_user_meta($current_user->ID, 'mulopimfwc_user_locations', true);
                        $selected_location_id = isset($_COOKIE['mulopimfwc_user_location']) ? $_COOKIE['mulopimfwc_user_location'] : '';



                        if ($selected_location_id === 'all-products') {
                            $current_location = __('All Products', 'multi-location-product-and-inventory-management-pro');
                            $location_set = true;
                        } elseif (!empty($user_locations) && is_array($user_locations) && $selected_location_id) {
                            foreach ($user_locations as $location) {
                                if ($location['id'] === $selected_location_id) {
                                    $current_location = $translate_saved_location_label($location['label'] ?? '') . ' - ' . $location['address'];
                                    $location_set = true;
                                    break;
                                }
                            }
                        }
                    } else {
                        // For non-logged in users, check if location is set in cookie
                        $location_set = true;
                        $selected_location_id = isset($_COOKIE['mulopimfwc_user_location']) ? $_COOKIE['mulopimfwc_user_location'] : ($store_cookie_value ?: '');
                        $current_location = !empty($selected_location_id) ? $selected_location_id : __('Current Location', 'multi-location-product-and-inventory-management-pro');
                    }

                    $store_location = $store_cookie_value;

                    if ($location_set) {
                        echo esc_html($current_location);
                    } else {
                        $display_location = $store_location ? str_replace(['_', '-'], ' ', ucwords($store_location)) : $shortcode_texts['set_your_location'];
                        echo esc_html($display_location);
                    }
                    ?>
                </span>
            </div>
            <div class="tooltip_popup" id="location-tooltip" style="display: none;">
                <div class="search-input-container">
                    <input type="text" id="address-search-input" placeholder="<?php echo esc_attr($shortcode_texts['enter_address']); ?>" aria-label="<?php echo esc_attr($shortcode_texts['enter_address']); ?>" value="" readonly>
                    <label><?php echo esc_html($shortcode_texts['enter_address']); ?></label>
                    <div>
                        <button type="button" aria-label="<?php esc_attr_e('Clear your address', 'multi-location-product-and-inventory-management-pro'); ?>" data-testid="input-clear-icon" id="clear-address-btn">&times;</button>
                    </div>
                </div>
                <?php if ($is_user_logged_in): ?>
                    <div class="saved_locations">
                        <h3 class="title"><?php echo esc_html($shortcode_texts['saved_address']); ?></h3>
                        <div class="saved-locations-list">
                            <?php
                            $user_locations = get_user_meta($current_user->ID, 'mulopimfwc_user_locations', true);
                            if ($is_admin_or_manager && $show_all_products_admin === 'on'):
                                $all_selected = (isset($_COOKIE['mulopimfwc_user_location']) && $_COOKIE['mulopimfwc_user_location'] === 'all-products') || ($store_cookie_value === 'all-products') ? 'selected' : '';
                            ?>
                                <div
                                    class="saved-location-item <?php echo esc_attr($all_selected); ?>"
                                    data-location-id="all-products"
                                    data-label="<?php echo esc_attr__('All Products', 'multi-location-product-and-inventory-management-pro'); ?>"
                                    data-label-display="<?php echo esc_attr__('All Products', 'multi-location-product-and-inventory-management-pro'); ?>"
                                    data-address="all-products"
                                    data-street="all-products"
                                    data-city="all-products"
                                    data-state="all-products"
                                    data-postal="all-products"
                                    data-country="all-products" style="gap:20px;">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" xml:space="preserve" width="16" height="16">
                                        <path d="M3.454 9.188a.3.3 0 0 1-.185.065.3.3 0 0 1-.231-.111l-.041-.051a.3.3 0 0 1-.065-.185.296.296 0 0 1 .527-.186l.041.051a.296.296 0 0 1-.046.416m.842 1.524a.3.3 0 0 0 .231.111.3.3 0 0 0 .185-.065.3.3 0 0 0 .111-.231.3.3 0 0 0-.065-.185l-.041-.051a.296.296 0 0 0-.527.185c0 .065.021.13.065.185zM4.178 9.33a.3.3 0 0 0 .185-.065.3.3 0 0 0 .111-.231.3.3 0 0 0-.065-.185l-.041-.051a.296.296 0 0 0-.462.371l.041.051a.3.3 0 0 0 .231.111m-.75.805a.3.3 0 0 0 .231.111.3.3 0 0 0 .185-.065.3.3 0 0 0 .111-.231.3.3 0 0 0-.065-.185l-.041-.051a.296.296 0 0 0-.527.186.3.3 0 0 0 .065.185zM.256 7.009a1.166 1.166 0 0 1 .181-1.638 1.16 1.16 0 0 1 .859-.249 1.16 1.16 0 0 1 .43-.784 1.166 1.166 0 0 1 1.665 1.605l.522.652.235-.188a.3.3 0 0 1 .23-.062 3 3 0 0 1 .348.076.296.296 0 0 1 .204.365.295.295 0 0 1-.365.204 2 2 0 0 0-.152-.037l-2.01 1.611c-.004.328.064.674.198 1.009a.296.296 0 0 1-.165.385.3.3 0 0 1-.11.021.3.3 0 0 1-.275-.186 3.2 3.2 0 0 1-.234-1.396.3.3 0 0 1 .11-.211l.235-.188-.522-.652A1.17 1.17 0 0 1 .256 7.01m1.268-.281a.3.3 0 0 1 .185-.065.3.3 0 0 1 .231.111l.684.853.827-.663-.684-.853a.296.296 0 0 1 .046-.416.574.574 0 0 0-.717-.895.57.57 0 0 0-.179.648.3.3 0 0 1-.092.335.3.3 0 0 1-.347.017.57.57 0 0 0-.671.033.574.574 0 0 0 .717.895m4.844.504v.512a.296.296 0 0 0 .592 0v-.512a.296.296 0 0 0-.592 0m3.324.207a1.13 1.13 0 0 0-.673-.256h-.01c-.392.006-.782.26-1.098.714-.477.685-.796 1.848-.481 2.745a.3.3 0 0 0 .279.198.296.296 0 0 0 .279-.394c-.237-.677.021-1.655.408-2.211.2-.287.425-.454.617-.46a.56.56 0 0 1 .317.133.296.296 0 0 0 .361-.469m2.071 3.102a.296.296 0 0 0 .303-.289c.021-.867-.269-1.847-.963-2.083l-.009-.003c-.31-.094-.667-.029-1.033.187-.686.406-1.308 1.295-1.48 2.113a.296.296 0 0 0 .58.122c.138-.66.655-1.401 1.202-1.725.213-.126.41-.173.554-.131.336.119.575.765.557 1.506a.296.296 0 0 0 .289.303M2.938 3.633a.3.3 0 0 0 .129.03.3.3 0 0 0 .267-.167c.012-.025.159-.401.171-.43C4.199 1.736 5.529.855 7.154.647c1.694-.216 3.394.367 4.435 1.522a.296.296 0 1 0 .44-.396A5.3 5.3 0 0 0 9.758.314 6.1 6.1 0 0 0 7.079.06C5.257.292 3.761 1.29 2.977 2.797c-.015.029-.169.422-.178.444a.296.296 0 0 0 .139.392m6.153 2.565a.296.296 0 1 1-.43.407.73.73 0 0 0-.389-.216.296.296 0 0 1-.237-.29v-.383H6.731v.383a.296.296 0 0 1-.237.29.74.74 0 0 0-.591.721v3.345a.296.296 0 0 1-.592 0V7.11c0-.545.337-1.029.828-1.23v-.324a.66.66 0 0 1-.23-.5v-.39a.66.66 0 0 1 .659-.659h1.63a.66.66 0 0 1 .659.659v.39c0 .2-.089.379-.23.5v.324a1.3 1.3 0 0 1 .464.318m-.826-1.533a.07.07 0 0 0-.067-.067h-1.63a.07.07 0 0 0-.067.067v.39c0 .028.018.052.042.062l.003.001q.011.004.023.004h1.63l.026-.005a.07.07 0 0 0 .042-.062zm6.386 5.973c-.015.664-.446 1.517-1.182 2.341a8.4 8.4 0 0 1-2.612 1.956v.759a.296.296 0 0 1-.296.296H5.154a.296.296 0 0 1-.296-.296v-.759a8.5 8.5 0 0 1-2.612-1.956c-.736-.824-1.167-1.677-1.182-2.341a.3.3 0 0 1 .166-.273.3.3 0 0 1 .317.037c.129.105.607.371 2.267.571 1.125.135 2.561.21 4.043.21s2.918-.074 4.043-.21c1.66-.2 2.138-.466 2.267-.571a.3.3 0 0 1 .317-.037.3.3 0 0 1 .166.273m-4.385 4.403H5.45v.357h4.815zm3.678-3.877c-1.645.576-5.28.609-6.086.609s-4.441-.033-6.086-.609c.096.26.249.542.45.832q.008.008.015.018c.043.055.135.146.266.146.117 0 .228-.074.305-.203a.296.296 0 0 1 .508.304 1 1 0 0 1-.52.44c.216.23.457.458.72.674a.296.296 0 0 1 .476.348l-.007.012c.381.27.795.514 1.232.714h5.28c.41-.188.8-.414 1.161-.664l-.039-.061a.296.296 0 1 1 .508-.304l.006.01c.289-.234.554-.481.789-.731a1 1 0 0 1-.511-.437.296.296 0 0 1 .508-.304c.077.129.188.203.305.203.097 0 .171-.05.219-.095.226-.314.396-.621.499-.901m.316-6.937a.296.296 0 0 0-.405.104l-.038.064a.296.296 0 0 0 .255.447.3.3 0 0 0 .255-.145l.038-.064a.296.296 0 0 0-.104-.405m-.812.575a.296.296 0 0 0-.405.105l-.037.064a.296.296 0 0 0 .105.405.296.296 0 0 0 .405-.105l.037-.064a.296.296 0 0 0-.105-.405m.111-.988a.296.296 0 0 0-.405.104l-.038.064a.296.296 0 0 0 .104.405.296.296 0 0 0 .405-.104l.038-.064a.296.296 0 0 0-.104-.405m2.426 1.152c-.359 1.094-.911 2.287-1.598 3.452-.293.496-.604.977-.925 1.43a.3.3 0 0 1-.242.125.3.3 0 0 1-.171-.055.296.296 0 0 1-.07-.413c.311-.439.613-.906.897-1.388a18 18 0 0 0 1.365-2.823 4 4 0 0 0-.251.104l-.029.013c-.82.383-1.85 1.178-2.874 2.238l-.091.258a.296.296 0 0 1-.379.178.296.296 0 0 1-.178-.379l.111-.318c.501-1.469.77-2.818.76-3.765v-.034l-.001-.056a21 21 0 0 0-1.694 2.468 23 23 0 0 0-.459.816.296.296 0 0 1-.401.121.296.296 0 0 1-.121-.401 23 23 0 0 1 .471-.837c.677-1.149 1.434-2.21 2.189-3.069a.296.296 0 0 1 .512.12 3 3 0 0 1 .047.225c.864-.732 1.573-.964 2.069-.672.637.375.588 1.397.367 2.352q.201-.058.383-.079c.1-.012.2.029.263.107s.083.184.052.28m-1.366-2.15c-.22-.13-.799.036-1.716.913-.007.755-.161 1.697-.448 2.74.761-.689 1.499-1.217 2.15-1.539.357-1.254.268-1.964.014-2.114m-.29.493a.296.296 0 0 0-.405.105l-.037.064a.296.296 0 0 0 .255.446.3.3 0 0 0 .255-.146l.037-.064a.296.296 0 0 0-.105-.405m-9.048 9.184a.296.296 0 0 0-.508-.304c-.077.129-.188.203-.305.203s-.228-.074-.305-.203a.296.296 0 0 0-.508.304c.184.308.488.491.814.491s.63-.184.814-.491m2.226 0a.296.296 0 0 0-.508-.304c-.077.129-.188.203-.305.203s-.228-.074-.305-.203a.296.296 0 0 0-.508.304c.184.308.488.491.814.491s.63-.184.814-.491m3.638.491c.326 0 .63-.184.814-.491a.296.296 0 0 0-.508-.304c-.077.129-.188.203-.305.203s-.228-.074-.305-.203a.296.296 0 1 0-.508.304c.184.308.488.491.814.491m-4.947.333a.296.296 0 0 0-.406.102c-.077.129-.188.203-.305.203s-.228-.074-.305-.203a.296.296 0 1 0-.508.304c.184.308.488.491.814.491s.63-.184.814-.491a.296.296 0 0 0-.102-.406m1.297.102a.296.296 0 0 0-.508.304c.184.308.488.491.814.491s.63-.184.814-.491a.296.296 0 0 0-.102-.406.296.296 0 0 0-.406.102c-.077.129-.188.203-.305.203s-.228-.074-.305-.203m1.418-.436c.326 0 .63-.184.814-.491a.296.296 0 0 0-.508-.304c-.077.129-.188.203-.305.203s-.228-.074-.305-.203a.296.296 0 0 0-.508.304c.184.308.488.491.814.491m.489.334a.296.296 0 0 0-.102.406c.184.308.488.491.814.491s.63-.184.814-.491a.296.296 0 0 0-.508-.304c-.077.129-.188.203-.305.203s-.228-.074-.305-.203a.296.296 0 0 0-.406-.102" />
                                    </svg>
                                    <div class="location-info">
                                        <span class="location-label">
                                            <?php esc_html_e('All Products', 'multi-location-product-and-inventory-management-pro'); ?>
                                            <span class="location-badge admin-only-badge"><?php esc_html_e('Admin Only', 'multi-location-product-and-inventory-management-pro'); ?></span>
                                        </span>
                                    </div>
                                </div>

                                <?php
                            endif;
                            if (!empty($user_locations) && is_array($user_locations)) {
                                foreach ($user_locations as $location) {
                                    $selected = (isset($_COOKIE['mulopimfwc_user_location']) && $_COOKIE['mulopimfwc_user_location'] === $location['id']) ? 'selected' : '';
                                ?>
                                    <div
                                        class="saved-location-item <?php echo esc_attr($selected); ?>"
                                        data-location-id="<?php echo esc_attr($location['id']); ?>"
                                        data-label="<?php echo esc_attr($location['label']); ?>"
                                        data-label-display="<?php echo esc_attr($translate_saved_location_label($location['label'] ?? '')); ?>"
                                        data-address="<?php echo esc_attr($location['address']); ?>"
                                        data-street="<?php echo esc_attr($location['street'] ?? ''); ?>"
                                        data-apartment="<?php echo esc_attr($location['apartment'] ?? ''); ?>"
                                        data-city="<?php echo esc_attr($location['city'] ?? ''); ?>"
                                        data-state="<?php echo esc_attr($location['state'] ?? ''); ?>"
                                        data-postal="<?php echo esc_attr($location['postal'] ?? ''); ?>"
                                        data-country="<?php echo esc_attr($location['country'] ?? ''); ?>"
                                        data-lat="<?php echo esc_attr($location['lat'] ?? ''); ?>"
                                        data-lng="<?php echo esc_attr($location['lng'] ?? ''); ?>"
                                        data-note="<?php echo esc_attr($location['note'] ?? ''); ?>">

                                        <div class="saved-address-icon">
                                            <?php if ($location['label'] == "Home") { ?>
                                                <svg aria-hidden="true" focusable="false" width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M18.1941 4C18.5738 4 18.8876 4.28215 18.9373 4.64823L18.9441 4.75L18.944 9.431L21.7074 11.6364C22.0312 11.8948 22.0842 12.3667 21.8259 12.6904C21.591 12.9848 21.1797 13.0554 20.8636 12.8717L20.7719 12.8089L20.735 12.779L20.7152 19.2271C20.7123 20.1433 20.0059 20.8932 19.1084 20.9659L18.9652 20.9716H4.99021C4.07204 20.9716 3.31902 20.2645 3.24601 19.3652L3.24021 19.2216L3.24 12.788L3.16041 12.8505C2.85462 13.0509 2.44007 13.0025 2.18974 12.7212C1.9394 12.44 1.93947 12.0226 2.17402 11.7421L2.25141 11.6624L10.3627 4.44395C10.9595 3.91282 11.8337 3.85713 12.4895 4.29021L12.6176 4.38334L14.589 5.956L14.5895 4.75C14.5895 4.3703 14.8716 4.05651 15.2377 4.00685L15.3395 4H18.1941ZM11.4192 5.52522L11.3599 5.56449L4.74 11.454L4.74021 19.2216C4.74021 19.34 4.82244 19.4391 4.93289 19.465L4.99021 19.4716H9.05978C9.24021 19.4716 9.24666 19.3652 9.24389 19.1808C9.24435 19.1698 9.24475 19.1487 9.2451 19.1175L9.24597 18.9933C9.24609 18.9675 9.24619 18.9392 9.24627 18.9084L9.24667 18.5623C9.2438 17.7576 9.24189 17.106 9.24093 16.6074L9.24021 15.9744C9.24021 14.4556 10.4714 13.2244 11.9902 13.2244C13.4527 13.2244 14.6486 14.3661 14.7352 15.8069L14.7402 15.9744C14.7405 16.1549 14.7408 16.328 14.741 16.4936L14.7419 17.1879C14.742 17.2595 14.7421 17.3291 14.7421 17.3969V18.9526C14.7421 18.9791 14.742 19.0037 14.7419 19.0265L14.741 19.1806C14.7408 19.2036 14.7405 19.2191 14.7402 19.2271C14.7352 19.3652 14.8037 19.4716 14.914 19.4716H18.9652C19.0833 19.4716 19.1823 19.3898 19.2084 19.2796L19.2152 19.2224L19.239 11.585L11.682 5.55583C11.6059 5.49508 11.5036 5.48527 11.4192 5.52522ZM11.9902 14.7244C11.343 14.7244 10.8107 15.2163 10.7467 15.8466L10.7402 15.9744L10.7409 16.5728C10.7413 16.8583 10.7416 17.1231 10.742 17.3673L10.7428 17.8283C10.7432 18.0451 10.7436 18.2412 10.744 18.4167L10.7449 18.7403C10.7453 18.8883 10.7457 19.0157 10.7462 19.1225L10.7467 19.2224C10.7469 19.278 10.7365 19.4716 10.8861 19.4716H13.1062C13.2402 19.4716 13.2402 19.3707 13.2402 19.2271V15.9744C13.2402 15.284 12.6806 14.7244 11.9902 14.7244ZM17.4432 5.49937H16.0892L16.089 7.153L17.444 8.234L17.4432 5.49937Z"></path>
                                                </svg>
                                            <?php } else if ($location['label'] == "Work") { ?>
                                                <svg aria-hidden="true" focusable="false" width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M13.7383 4C14.6565 4 15.4095 4.70711 15.4825 5.60647C15.4954 5.83634 15.5019 5.95581 15.5019 5.96487C15.5019 6.0767 15.6017 6.0767 15.6017 6.0767H17.75C18.7165 6.0767 19.5 6.8602 19.5 7.8267V17.8267C19.5 18.7932 18.7165 19.5767 17.75 19.5767H5.75C4.7835 19.5767 4 18.7932 4 17.8267V7.8267C4 6.8602 4.7835 6.0767 5.75 6.0767L7.7285 6.07699C7.72552 6.07596 7.72831 6.07482 7.73755 6.07355C7.82035 6.06224 7.83829 5.98992 7.83829 5.96487V5.75C7.83829 4.83183 8.5454 4.07881 9.44477 4.0058L9.58829 4H13.7383ZM7.68585 14.6492L5.5 14.649V17.8267C5.5 17.945 5.58223 18.0442 5.69268 18.0701L5.75 18.0767H17.75C17.8881 18.0767 18 17.9648 18 17.8267V14.649L9.44477 14.6492C9.38509 14.6492 9.34532 14.7293 9.34494 14.8062C9.34426 14.9465 9.34204 15.2126 9.33829 15.6046C9.33829 16.0188 9.00251 16.3546 8.58829 16.3546C8.2086 16.3546 7.8948 16.0724 7.84514 15.7063L7.83829 15.6046V14.8072C7.83829 14.6944 7.75845 14.6492 7.68585 14.6492ZM15.6017 14.6508C15.5421 14.6508 15.5023 14.7309 15.5019 14.8078C15.5012 14.9481 15.499 15.2142 15.4953 15.6062C15.4953 16.0204 15.1595 16.3562 14.7453 16.3562C14.3656 16.3562 14.0518 16.074 14.0021 15.708L13.9953 15.6062V14.8088C13.9953 14.696 13.9154 14.6508 13.8428 14.6508H15.6017ZM17.75 7.5767H5.75C5.61193 7.5767 5.5 7.68863 5.5 7.8267V13.149H18V7.8267C18 7.70835 17.9178 7.60921 17.8073 7.5833L17.75 7.5767ZM13.7383 5.5H9.58829C9.46995 5.5 9.37081 5.58223 9.3449 5.69268L9.33829 5.75V5.96487C9.33829 6.04728 9.39174 6.07355 9.44477 6.0767H13.8849C13.9883 6.0767 13.9883 6.00433 13.9883 5.96487V5.75C13.9883 5.63165 13.9061 5.53251 13.7956 5.5066L13.7383 5.5Z"></path>
                                                </svg>
                                            <?php } else if ($location['label'] == "Partner") { ?>
                                                <svg aria-hidden="true" focusable="false" width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M18.3384 4.43834C21.1017 5.75361 22.3527 9.19469 21.1327 12.1242C19.5851 15.3674 16.697 17.9595 12.4684 19.9005C12.2142 20.0143 11.925 20.0305 11.6609 19.9491L11.5307 19.9C7.30258 17.9591 4.41482 15.3671 2.86737 12.1242C1.64733 9.19469 2.89838 5.75361 5.66167 4.43834C7.55198 3.53859 9.48847 4.12319 11.0402 5.29285C11.1519 5.37698 11.2861 5.48788 11.443 5.62554L11.7293 5.88324C11.8823 6.02397 12.1176 6.02407 12.2707 5.88348L12.5865 5.60038C12.7311 5.47451 12.8557 5.37218 12.9603 5.29338C14.5155 4.12201 16.45 3.53949 18.3384 4.43834ZM17.6937 5.79274C16.5158 5.23207 15.1885 5.49301 13.8627 6.49154L13.7119 6.61193C13.5969 6.70757 13.4536 6.83367 13.2852 6.98833C13.0777 7.17884 12.7316 7.45514 12.2466 7.81724L12.2467 7.81729C12.1018 7.92549 11.9023 7.92304 11.7601 7.81131C11.3453 7.48537 11.0424 7.24931 10.8513 7.10313L10.7202 6.99308L10.5628 6.84994C10.3818 6.68743 10.2378 6.56641 10.1374 6.49069C8.81398 5.49318 7.485 5.23172 6.30633 5.79274C4.27543 6.75941 3.33513 9.34576 4.22115 11.4782C5.55607 14.2758 8.04261 16.5647 11.7406 18.3415L12 18.463L12.2586 18.3419C15.82 16.631 18.257 14.4472 19.5981 11.8491L19.748 11.5475C20.6363 9.41456 19.7816 6.92068 17.8809 5.88805L17.6937 5.79274Z"></path>
                                                </svg>
                                            <?php } else if ($location['label'] == "Other") { ?>
                                                <svg aria-hidden="true" focusable="false" width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12.3224 2C16.9186 2 20.6446 5.72596 20.6446 10.3222C20.6446 11.8203 20.2487 13.226 19.5559 14.4404L18.4715 15.8911C17.8726 16.5162 16.6838 17.706 14.9052 19.4602L13.0213 21.313C12.6322 21.6947 12.0092 21.6946 11.6203 21.3128L7.91833 17.6571L6.59648 16.3282C6.2846 16.0104 5.78156 15.3801 5.08734 14.4375C4.3955 13.2238 4.00024 11.8192 4.00024 10.3222C4.00024 5.72596 7.72621 2 12.3224 2ZM12.3224 3.5C8.55463 3.5 5.50024 6.55439 5.50024 10.3222C5.50024 11.4141 5.75604 12.466 6.23886 13.4136L6.37241 13.66L6.96356 14.5436L7.18196 14.7804L7.77128 15.385C8.23371 15.8535 8.88147 16.5011 9.70239 17.3151C10.6866 18.2861 11.4247 19.0143 11.9168 19.4998C11.9577 19.5401 11.9986 19.5805 12.0395 19.6209C12.1953 19.7745 12.4456 19.7745 12.6013 19.6209L12.6754 19.5478L15.3017 16.9571L17.2047 15.0461C17.3404 14.9068 17.4503 14.7925 17.5337 14.7039L17.6874 14.534L18.2724 13.659L18.4049 13.4158C18.84 12.5624 19.0911 11.6245 19.1369 10.6487L19.1446 10.3222C19.1446 6.55439 16.0902 3.5 12.3224 3.5ZM12.3224 6.75C14.3935 6.75 16.0724 8.42893 16.0724 10.5C16.0724 12.5711 14.3935 14.25 12.3224 14.25C10.2513 14.25 8.57241 12.5711 8.57241 10.5C8.57241 8.42893 10.2513 6.75 12.3224 6.75ZM12.3224 8.25C11.0798 8.25 10.0724 9.25736 10.0724 10.5C10.0724 11.7426 11.0798 12.75 12.3224 12.75C13.5651 12.75 14.5724 11.7426 14.5724 10.5C14.5724 9.25736 13.5651 8.25 12.3224 8.25Z"></path>
                                                </svg>
                                            <?php } ?>
                                        </div>
                                        <div class="location-info">
                                            <span class="location-label"><?php echo esc_html($translate_saved_location_label($location['label'] ?? '')); ?></span>
                                            <span class="location-address"><?php echo esc_html($location['address']); ?></span>
                                        </div>
                                        <div class="location-actions">
                                            <button type="button" class="edit-location-btn" data-location-id="<?php echo esc_attr($location['id']); ?>" aria-label="<?php esc_attr_e('Edit location', 'multi-location-product-and-inventory-management-pro'); ?>"><?php echo mulopimfwc_svg_icon('pencil'); ?></button>
                                            <button type="button" class="delete-location-btn" data-location-id="<?php echo esc_attr($location['id']); ?>" aria-label="<?php esc_attr_e('Delete location', 'multi-location-product-and-inventory-management-pro'); ?>"><?php echo mulopimfwc_svg_icon('trash'); ?></button>
                                        </div>
                                    </div>
                                <?php
                                }
                            } else {
                                ?>
                                <p class="no-saved-locations"><?php esc_html_e('No saved address yet', 'multi-location-product-and-inventory-management-pro'); ?></p>
                            <?php
                            }
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="location-actions-container">
                    <?php if ($is_user_logged_in): ?>
                        <button type="button" class="button button-primary lwp-add-location-btn">
                            <?php echo esc_html($shortcode_texts['add_new_address']); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <!-- CSS Styles -->
        <style>
            .lwp-user-location-features {
                position: relative;
                margin-bottom: 0px;
            }

            .address-content {
                display: flex;
                align-items: center;
                padding: 12px 16px;
                background: var(--lwp-background, #ffffff);
                border: 1px solid var(--lwp-border, #e9ecef);
                border-radius: 8px;
                cursor: pointer;
                transition: all 0.2s ease;
                font-size: 14px;
            }

            .address-content:hover {
                background: color-mix(in srgb,
                        var(--lwp-primary, #e9ecef) 30%,
                        transparent);
                border-color: var(--lwp-primary, #dee2e6);
            }

            .address-label-icon {
                margin-right: 8px;
                font-size: 16px;
            }

            .address-text {
                color: var(--lwp-ink, #212529);
                font-weight: 500;
                <?php if (!isset($atts['multi_line']) || (isset($atts['multi_line']) && $atts['multi_line'] !== "on")) { ?>width: calc(<?php echo esc_attr($max_width); ?> - (<?php echo esc_attr($max_width); ?> / 3));
                /* Set a fixed width */
                white-space: nowrap;
                /* Prevent text from wrapping */
                overflow: hidden;
                /* Hide the overflowed text */
                text-overflow: ellipsis;
                <?php } ?>
            }

            .address-content:hover .address-text {
                color: var(--lwp-primary, #0071a1);
            }

            .tooltip_popup {
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: var(--lwp-background, #ffffff);
                border: 1px solid var(--lwp-border, #dee2e6);
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                z-index: 1000;
                margin-top: 4px;
                padding: 20px;
                max-height: 400px;
                overflow: hidden;
                min-width: 400px;
            }

            .search-input-container {
                position: relative;
                margin-bottom: 20px;
            }

            .search-input-container input {
                width: 100%;
                padding: 12px 40px 12px 16px;
                border: 1px solid #ced4da;
                border-radius: 6px;
                font-size: 14px;
                transition: border-color 0.2s ease;
            }

            .search-input-container input:focus {
                outline: none;
                border-color: #80bdff;
                box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
            }

            .search-input-container input[readonly] {
                background-color: #f8f9fa;
                color: #6c757d;
            }

            .search-input-container label {
                position: absolute;
                left: 16px;
                top: -8px;
                background: white;
                padding: 0 4px;
                font-size: 12px;
                color: var(--lwp-ink, #6c757d);
                font-weight: 500;
            }

            .search-input-container button {
                position: absolute;
                right: 12px;
                top: 50%;
                transform: translateY(-50%);
                background: none;
                border: none;
                font-size: 18px;
                color: #6c757d;
                cursor: pointer;
                padding: 2px;
            }

            .search-input-container button:hover {
                color: #495057;
            }

            .status-text {
                color: #6c757d;
            }

            .status-text.detecting {
                color: #0d6efd;
            }

            .status-text.success {
                color: #198754;
            }

            .status-text.error {
                color: #dc3545;
            }

            .saved_locations {
                margin-bottom: 20px;
            }

            .saved_locations .title {
                margin: 0 0 12px 0;
                font-size: 16px;
                font-weight: 600;
                color: var(--lwp-ink, #495057);
            }

            .saved-locations-list {
                max-height: 200px;
                overflow-y: auto;
                scrollbar-width: thin;
            }

            .saved-location-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 12px;
                border: 1px solid var(--lwp-border, #e9ecef);
                border-radius: 6px;
                margin-bottom: 8px;
                cursor: pointer;
                transition: all 0.2s ease;
            }

            .saved-location-item .location-actions svg {
                width: 20px;
                height: 20px;
                flex-shrink: 0;
                fill: var(--lwp-ink, #495057);
            }

            .saved-location-item:hover {
                background: color-mix(in srgb,
                        var(--lwp-primary, #e9ecef) 30%,
                        transparent);
                border-color: var(--lwp-primary, #dee2e6)
            }

            .saved-location-item.selected {
                background:
                    color-mix(in srgb, var(--lwp-primary, #e9ecef) 30%, transparent);
                border-color: var(--lwp-primary, #dee2e6);
            }

            .location-info {
                display: flex;
                flex-direction: column;
                flex: 1;
            }

            .location-label {
                font-weight: 600;
                color: var(--lwp-ink, #495057);
                font-size: 14px;
            }

            .location-badge {
                display: inline-block;
                margin-left: 6px;
                padding: 2px 6px;
                border-radius: 999px;
                font-size: 10px;
                font-weight: 600;
                letter-spacing: 0.3px;
                text-transform: uppercase;
                vertical-align: middle;
            }

            .admin-only-badge {
                background: #fff3cd;
                border: 1px solid #ffe2a1;
                color: #8a6d3b;
            }

            .location-address {
                color: #6c757d;
                font-size: 13px;
                margin-top: 2px;
            }

            .location-actions {
                display: flex;
                gap: 8px;
            }

            .edit-location-btn,
            .delete-location-btn {
                background: none;
                border: none;
                padding: 4px;
                cursor: pointer;
                border-radius: 4px;
                transition: background-color 0.2s ease;
            }

            .edit-location-btn:hover {
                background: #e3f2fd;
            }

            .edit-location-btn:hover svg {
                fill: var(--lwp-primary, #667eea);
            }

            .delete-location-btn:hover {
                background: #ffebee;
            }

            .delete-location-btn:hover svg {
                fill: #d32f2f;
            }

            .no-saved-locations {
                text-align: center;
                color: #6c757d;
                font-style: italic;
                margin: 20px 0;
            }

            .location-actions-container {
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
            }

            .location-actions-container .button {
                flex: 1;
                min-width: 120px;
                text-align: center;
            }

            .search-results {
                margin-top: 10px;
                border: 1px solid #e9ecef;
                border-radius: 6px;
                background: white;
                max-height: 200px;
                overflow-y: auto;
                z-index: 1000;
                position: relative;
            }

            .search-result-item {
                padding: 12px 16px;
                cursor: pointer;
                border-bottom: 1px solid #f1f3f4;
                transition: background-color 0.2s ease;
            }

            .search-result-item:last-child {
                border-bottom: none;
            }

            .search-result-item:hover {
                background: #f8f9fa;
            }

            .result-address {
                font-size: 14px;
                color: #495057;
                display: block;
                word-wrap: break-word;
            }

            /* Responsive styles */
            @media (max-width: 768px) {
                .tooltip_popup {
                    left: -10px;
                    right: -10px;
                }

                .location-actions-container {
                    flex-direction: column;
                }

                .location-actions-container .button {
                    flex: none;
                }
            }
        </style>
    <?php endif; ?>
    <form id="lwp-shortcode-selector-form" class="lwp-selector-form">
        <?php wp_nonce_field('mulopimfwc_shortcode_selector', 'mulopimfwc_shortcode_selector_nonce'); ?>
        <?php if ($atts["herichical"] === "seperately"): ?>
            <?php
            // Organize locations into a hierarchical structure for separate selects
            $location_hierarchy = array();
            $parent_children_map = array();
            $child_counts = array();
            $depth_map = array();
            $max_depth = 0;
            $show_count = isset($atts["show_count"]) && $atts["show_count"] === "on";
            // First pass: identify all parent-child relationships and depths
            foreach ($locations as $location) {
                if ($location->parent == 0) {
                    $depth_map[$location->term_id] = 0;
                    $location_hierarchy[0][] = $location;
                    $child_counts[$location->term_id] = 0; // Initialize child count
                } else {
                    $parent_children_map[$location->parent][] = $location;
                    // Increment child count for parent
                    if (!isset($child_counts[$location->parent])) {
                        $child_counts[$location->parent] = 1;
                    } else {
                        $child_counts[$location->parent]++;
                    }
                }
            }
            // Calculate depth for each location
            $calculate_depth = function ($location_id, $current_depth = 0) use (&$calculate_depth, &$depth_map, &$parent_children_map, &$max_depth) {
                $depth_map[$location_id] = $current_depth;
                if ($current_depth > $max_depth) {
                    $max_depth = $current_depth;
                }
                if (isset($parent_children_map[$location_id])) {
                    foreach ($parent_children_map[$location_id] as $child) {
                        $calculate_depth($child->term_id, $current_depth + 1);
                    }
                }
            };
            // Calculate depths starting from root locations
            if (!empty($location_hierarchy[0])) {
                foreach ($location_hierarchy[0] as $root_location) {
                    $calculate_depth($root_location->term_id);
                }
            }
            // Build full hierarchy
            for ($i = 1; $i <= $max_depth; $i++) {
                $location_hierarchy[$i] = array();
                foreach ($parent_children_map as $parent_id => $children) {
                    foreach ($children as $child) {
                        if ($depth_map[$parent_id] == $i - 1) {
                            $location_hierarchy[$i][] = $child;
                        }
                    }
                }
            }
            // Convert locations to JSON for JavaScript
            $locations_json = wp_json_encode($locations);
            $parent_children_json = wp_json_encode($parent_children_map);
            $child_counts_json = wp_json_encode($child_counts);
            // Generate separate dropdowns for each level
            for ($level = 0; $level <= $max_depth; $level++):
                $select_id = "lwp-shortcode-selector-level-{$level}";
                // translators: %s: The name of the location level (e.g., Area, Sub-area)
                $placeholder = $level == 0 ? $atts['placeholder'] : sprintf(__('-- Select %s --', 'multi-location-product-and-inventory-management-pro'), ($level == 1 ? 'Area' : 'Sub-area'));
            ?>
                <div class="lwp-select-container level-<?php echo esc_html($level); ?>" <?php echo $level > 0 ? 'style="display:none;"' : ''; ?>>
                    <select id="<?php echo esc_html($select_id); ?>" class="lwp-shortcode-selector-dropdown" data-level="<?php echo esc_html($level); ?>" style="display: <?php echo $atts['enable_user_locations'] === 'on' ? 'none' : 'block'; ?>;">
                        <option value=""><?php echo esc_html($placeholder); ?></option>
                        <?php if ($level == 0 && $is_admin_or_manager && $show_all_products_admin === 'on'): ?>
                            <option value="all-products" <?php echo ($selected_location === 'all-products') ? 'selected' : ''; ?>>
                                <?php echo esc_html_e('All Products', 'multi-location-product-and-inventory-management-pro'); ?>
                            </option>
                        <?php endif; ?>
                        <?php if ($level == 0 && !empty($location_hierarchy[0])): ?>
                            <?php foreach ($location_hierarchy[0] as $location): ?>
                                <?php
                                $child_count = isset($child_counts[$location->term_id]) ? $child_counts[$location->term_id] : 0;
                                $display_name = esc_html($location->name);
                                if ($show_count && $child_count > 0) {
                                    $display_name .= ' (' . $child_count . ')';
                                }
                                $selected = (rawurldecode($location->slug) === $selected_location) ? 'selected' : '';
                                ?>
                                <option value="<?php echo esc_attr(rawurldecode($location->slug)); ?>"
                                    data-term-id="<?php echo esc_attr($location->term_id); ?>"
                                    <?php echo esc_html($selected); ?>>
                                    <?php echo esc_html($display_name); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
            <?php endfor; ?>
            <input type="hidden" id="lwp-selected-store-shortcode" name="mulopimfwc_selected_store" value="<?php echo esc_attr($selected_location); ?>">
        <?php else: ?>
            <!-- Single dropdown implementation -->
            <select id="lwp-shortcode-selector" class="lwp-location-dropdown" style="display: <?php echo $atts['enable_user_locations'] === 'on' ? 'none' : 'block'; ?>;">
                <option value=""><?php echo esc_html($atts['placeholder'] ?? mulopimfwc_get_text_value('mulopimfwc_popup_placeholder')); ?></option>
                <?php if ($is_admin_or_manager && $show_all_products_admin === 'on'): ?>
                    <?php $selected = ($selected_location === 'all-products') ? 'selected' : ''; ?>
                    <option value="all-products" <?php echo esc_attr($selected); ?>><?php echo esc_html_e('All Products', 'multi-location-product-and-inventory-management-pro'); ?></option>
                <?php endif; ?>
                <?php if (!empty($locations) && !is_wp_error($locations)): ?>
                    <?php
                    if ($atts["herichical"] === "on") {
                        // Organize locations into a hierarchical structure
                        $parent_locations = array();
                        $child_locations = array();
                        $child_counts = array();
                        $show_count = isset($atts["show_count"]) && $atts["show_count"] === "on";
                        foreach ($locations as $location) {
                            if ($location->parent == 0) {
                                $parent_locations[] = $location;
                                $child_counts[$location->term_id] = 0; // Initialize child count
                            } else {
                                $child_locations[$location->parent][] = $location;
                                // Increment child count for parent
                                if (!isset($child_counts[$location->parent])) {
                                    $child_counts[$location->parent] = 1;
                                } else {
                                    $child_counts[$location->parent]++;
                                }
                            }
                        }
                        // Display parent locations and their children
                        foreach ($parent_locations as $parent) {
                            $child_count = isset($child_counts[$parent->term_id]) ? $child_counts[$parent->term_id] : 0;
                            $display_name = esc_html($parent->name);
                            if ($show_count && $child_count > 0) {
                                $display_name .= ' (' . $child_count . ')';
                            }
                            $selected = ($parent->slug === $selected_location) ? 'selected' : '';
                            echo '<option value="' . esc_attr($parent->slug) . '" ' . esc_html($selected) . '>' . $display_name . '</option>';
                            // Check if this parent has children
                            if (isset($child_locations[$parent->term_id])) {
                                foreach ($child_locations[$parent->term_id] as $child) {
                                    $selected = ($child->slug === $selected_location) ? 'selected' : '';
                                    echo '<option value="' . esc_attr($child->slug) . '" ' . esc_html($selected) . '>&nbsp;&nbsp;— ' . esc_html($child->name) . '</option>';
                                }
                            }
                        }
                    } else {
                        // Display locations in flat list
                        foreach ($locations as $location) {
                            $selected = (rawurldecode($location->slug) === $selected_location) ? 'selected' : '';
                            echo '<option value="' . esc_attr(rawurldecode($location->slug)) . '" ' . esc_html($selected) . '>' . esc_html($location->name) . '</option>';
                        }
                    }
                    ?>
                <?php endif; ?>
            </select>
            <input type="hidden" id="lwp-selected-store-shortcode" name="mulopimfwc_selected_store" value="<?php echo esc_attr($selected_location); ?>">
        <?php endif; ?>
        <?php if ($atts['show_button'] === 'on'): ?>
            <button type="button" class="button lwp-shortcode-submit">
                <?php echo esc_html($atts['button_text'] ?? 'Change Location'); ?>
            </button>
        <?php endif; ?>
    </form>
</div>

<!-- Location Popup -->
<div id="lwp-location-popup" class="lwp-location-popup" style="display:none;">
    <div class="lwp-popup-content">
        <div class="lwp-popup-header">
            <h3><?php echo esc_html($shortcode_texts['set_your_location']); ?></h3>
            <button type="button" class="lwp-close-popup">&times;</button>
        </div>
        <div id="lwp-location-step1" class="lwp-location-step">
            <div class="lwp-map-container">
                <div id="lwp-location-map" class="lwp-location-map"></div>
                <div class="lwp-map-controls">
                    <button type="button" class="button button-primary lwp-continue-btn"><?php echo esc_html($shortcode_texts['continue']);
                                                                                            echo mulopimfwc_svg_icon('right_arrow'); ?></button>
                </div>
            </div>
        </div>

        <div id="lwp-location-step2" class="lwp-location-step" style="display:none;">
            <div class="lwp-location-details">
                <div class="lwp-details-header">
                    <h4><?php echo esc_html($shortcode_texts['location_details']); ?></h4>
                    <p class="lwp-address-preview"></p>
                    <span class="edit_location_map" style="cursor: pointer;"><?php echo mulopimfwc_svg_icon('pencil'); ?></span>
                </div>
                <form id="lwp-location-form">
                    <?php wp_nonce_field('mulopimfwc_save_user_location', 'mulopimfwc_save_user_location_nonce'); ?>
                    <input type="hidden" id="lwp-editing-location-id" name="location_id" value="">
                    <div class="lwp-form-group">
                        <label for="lwp-location-label"><?php echo esc_html($shortcode_texts['label']); ?></label>
                        <select id="lwp-location-label" name="label" required>
                            <option value="Home"><?php echo esc_html($shortcode_texts['home']); ?></option>
                            <option value="Work"><?php echo esc_html($shortcode_texts['work']); ?></option>
                            <option value="Partner"><?php echo esc_html($shortcode_texts['partner']); ?></option>
                            <option value="Other"><?php echo esc_html($shortcode_texts['other']); ?></option>
                        </select>
                    </div>
                    <div class="lwp-form-group">
                        <label for="lwp-location-street"><?php echo esc_html($shortcode_texts['street_address']); ?></label>
                        <input type="text" id="lwp-location-street" name="street" required>
                    </div>
                    <div class="lwp-form-group">
                        <label for="lwp-location-apartment"><?php echo esc_html($shortcode_texts['apartment_suite']); ?></label>
                        <input type="text" id="lwp-location-apartment" name="apartment">
                    </div>
                    <div class="lwp-form-group">
                        <label for="lwp-location-note"><?php echo esc_html($shortcode_texts['note']); ?></label>
                        <textarea id="lwp-location-note" name="note"></textarea>
                    </div>
                    <input type="hidden" id="lwp-location-lat" name="lat">
                    <input type="hidden" id="lwp-location-lng" name="lng">
                    <input type="hidden" id="lwp-location-city" name="city">
                    <input type="hidden" id="lwp-location-state" name="state">
                    <input type="hidden" id="lwp-location-postal" name="postal">
                    <input type="hidden" id="lwp-location-country" name="country">
                    <div class="lwp-form-actions">
                        <button type="button" class="button lwp-back-btn"><?php echo mulopimfwc_svg_icon('left_arrow');
                                                                            echo esc_html($shortcode_texts['back']); ?></button>
                        <button type="submit" class="button button-primary"><?php echo mulopimfwc_svg_icon('save');
                                                                            echo esc_html($shortcode_texts['save_location']); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($atts["herichical"] === "seperately"):
    // Prepare JS variables for inline script
    $locations_json = wp_json_encode($locations);
    $parent_children_json = wp_json_encode($parent_children_map);
    $child_counts_json = wp_json_encode($child_counts);
    $show_count_js = $show_count ? 'true' : 'false';
    $auto_submit_js = $atts['show_button'] === 'off' ? 'true' : 'false';
    $max_depth_js = (int)$max_depth;
    $use_select2 = $atts["use_select2"] === "on";
    $select2_init = $use_select2 ? "$('.lwp-shortcode-selector-dropdown').select2();" : "";
    $inline_js = <<<JS
jQuery(document).ready(function($) {
    var locationsData = $locations_json;
    var parentChildrenMap = $parent_children_json;
    var childCounts = $child_counts_json;
    var showCount = $show_count_js;
    var autoSubmit = $auto_submit_js;
    $('.lwp-shortcode-selector-dropdown').on('change', function() {
        var selectedLevel = $(this).data('level');
        var selectedTermId = $(this).find(':selected').data('term-id');
        var selectedValue = $(this).val();
        if (selectedValue) {
            $('#lwp-selected-store-shortcode').val(selectedValue);
        } else {
            $('#lwp-selected-store-shortcode').val('');
        }
        for (var i = selectedLevel + 1; i <= $max_depth_js; i++) {
            $('.lwp-select-container.level-' + i).hide();
            $('#lwp-shortcode-selector-level-' + i).empty().append('<option value="">' + mulopimfwc_selector_i18n.select + '</option>');
        }
        if (selectedValue && selectedTermId && parentChildrenMap[selectedTermId]) {
            var nextLevel = selectedLevel + 1;
            var nextDropdown = $('#lwp-shortcode-selector-level-' + nextLevel);
            nextDropdown.empty().append('<option value="">' + mulopimfwc_selector_i18n.select + '</option>');
            $.each(parentChildrenMap[selectedTermId], function(index, location) {
                var childCount = childCounts[location.term_id] || 0;
                var displayText = location.name;
                if (showCount && childCount > 0) {
                    displayText += ' (' + childCount + ')';
                }
                nextDropdown.append('<option value="' + location.slug + '" data-term-id="' + location.term_id + '">' + displayText + '</option>');
            });
            $('.lwp-select-container.level-' + nextLevel).show();
        } else if (autoSubmit && selectedValue) {
            $('#lwp-shortcode-selector-form').submit();
        }
    });
    $select2_init
    $('.lwp-shortcode-submit').on('click', function() {
        $('#lwp-shortcode-selector-form').submit();
    });
});
JS;
    // Pass translation string for '-- Select --'
    wp_localize_script('mulopimfwc_script', 'mulopimfwc_selector_i18n', array(
        'select' => esc_html__('-- Select --', 'multi-location-product-and-inventory-management-pro'),
    ));
    // Output inline script
    wp_add_inline_script('mulopimfwc_script', $inline_js);
else:
    $use_select2 = ($atts['use_select2'] === 'on') ? 'true' : 'false';
    $show_button = ($atts['show_button'] === 'on') ? 'true' : 'false';
    $auto_submit_js = ($atts['show_button'] === 'off') ? 'true' : 'false';
    $inline_js = <<<JS
(function ($) {
  'use strict';

  $(function () {
    if ($use_select2) {
        $('#lwp-shortcode-selector').select2();
    }
    $('#lwp-shortcode-selector').on('change', function() {
        var selectedValue = $(this).val();
        if (selectedValue) {
            $('#lwp-selected-store-shortcode').val(selectedValue);
            if ($auto_submit_js) {
                $('#lwp-shortcode-selector-form').submit();
            }
        } else {
            $('#lwp-selected-store-shortcode').val('');
        }
    });
    $('.lwp-shortcode-submit').on('click', function() {
        $('#lwp-shortcode-selector-form').submit();
    });
});

})(jQuery);
JS;
    wp_add_inline_script('mulopimfwc_script', $inline_js);
endif;

// Location-Wise Products - User Location Features
// Consolidated JavaScript for location functionality
if (isset($atts['enable_user_locations']) && $atts['enable_user_locations'] === 'on'):
?>
    <script>
        jQuery(document).ready(function($) {

            // ========================================
            // CONFIGURATION & GLOBAL VARIABLES
            // ========================================

            const userLocationCookieExpiryDays = <?php echo esc_js(mulopimfwc_get_location_cookie_expiry_days()); ?>;
            const userLocationCookieExpiryMs = userLocationCookieExpiryDays * 24 * 60 * 60 * 1000;
            const deleteLocationConfirmMessage = <?php echo wp_json_encode(__('Are you sure you want to delete this location?', 'multi-location-product-and-inventory-management-pro')); ?>;

            const LocationFeatures = {
                map: null,
                marker: null,
                selectedLat: null,
                selectedLng: null,
                selectedAddress: {},
                searchTimeout: null,
                pendingAutoPopulateAddress: null,
                autoPopulateInFlight: false,
                autoPopulatePerformed: false,

                // Configuration
                config: {
                    defaultLocation: {
                        lat: 40.7128,
                        lng: -74.0060
                    },
                    geolocation: {
                        highAccuracy: {
                            enableHighAccuracy: true,
                            timeout: 5000,
                            maximumAge: 60000
                        },
                        lowAccuracy: {
                            enableHighAccuracy: false,
                            timeout: 10000,
                            maximumAge: 300000
                        }
                    },
                    search: {
                        debounceDelay: 300,
                        minQueryLength: 2,
                        maxResults: 5
                    }
                },

                // ========================================
                // INITIALIZATION
                // ========================================

                init: function() {
                    this.bindEvents();
                    this.injectStyles();
                    // Auto-detect user location on page load
                    this.detectUserLocationAndSetInput();
                },

                bindEvents: function() {
                    // Location dropdown events
                    $('.lwp-saved-locations').on('change', this.handleSavedLocationChange.bind(this));

                    // Popup control events
                    $('.lwp-add-location-btn, .lwp-use-current-location-btn').on('click', this.openLocationPopupWithDetection.bind(this));
                    $('.lwp-close-popup').on('click', this.closeLocationPopup.bind(this));

                    // Step navigation events
                    $('.lwp-continue-btn').on('click', this.handleContinueToStep2.bind(this));
                    $('.edit_location_map').on('click', this.handleBackToStep1.bind(this));
                    $('.lwp-back-btn').on('click', this.handleBackToStep1.bind(this));

                    // Form submission
                    $('#lwp-location-form').on('submit', this.handleLocationFormSubmit.bind(this));

                    // Tooltip interface events
                    $('#address-trigger').on('click', this.toggleTooltip.bind(this));
                    $(document).on('click', this.closeTooltipOnOutsideClick.bind(this));
                    $('#location-tooltip').on('click', function(e) {
                        e.stopPropagation();
                    });

                    // Search functionality
                    $('#clear-address-btn').on('click', this.clearAddressInput.bind(this));
                    $('#address-search-input').on('input', this.handleAddressSearch.bind(this));

                    // Saved location management
                    $('.saved-location-item').on('click', this.handleSavedLocationSelect.bind(this));
                    $('.edit-location-btn').on('click', this.handleEditLocation.bind(this));
                    $('.delete-location-btn').on('click', this.handleDeleteLocation.bind(this));

                    $('#address-search-input').on('submit', (e) => {
                        const query = $('#address-search-input').val().trim();
                        if (!query) return;
                        this.searchAddresses(query, (first) => {
                            if (!first) return;
                            const lat = parseFloat(first.lat);
                            const lon = parseFloat(first.lon);
                            $('#location-tooltip').hide();
                            this.openLocationPopupWithCoordinates(lat, lon);
                        });
                    });

                    // Pressing Enter in the address input should open the map at the first result
                    $('#address-search-input').on('keydown', (e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            const query = $('#address-search-input').val().trim();
                            if (!query) return;
                            this.searchAddresses(query, (first) => {
                                if (!first) return;
                                const lat = parseFloat(first.lat);
                                const lon = parseFloat(first.lon);
                                $('#location-tooltip').hide();
                                this.openLocationPopupWithCoordinates(lat, lon);
                            });
                        }
                    });


                },

                // ========================================
                // AUTO-POPULATE CUSTOMER ADDRESS
                // ========================================

                isAutoPopulateEnabled: function() {
                    return !!(window.mulopimfwc_locationWiseProducts &&
                        mulopimfwc_locationWiseProducts.autoPopulateAddresses === 'on');
                },

                isLoggedIn: function() {
                    return !!(window.mulopimfwc_locationWiseProducts && mulopimfwc_locationWiseProducts.isLoggedIn);
                },

                isCheckoutContext: function() {
                    return !!(window.mulopimfwc_locationWiseProducts && mulopimfwc_locationWiseProducts.isCheckout);
                },

                isInvalidAddressValue: function(value) {
                    const normalized = (value || '').toString().trim().toLowerCase();
                    return !normalized || normalized === 'all-products';
                },

                buildAddressFromItem: function($item) {
                    if (!$item || !$item.length) {
                        return null;
                    }

                    const locationId = ($item.data('location-id') || '').toString().trim().toLowerCase();
                    if (locationId === 'all-products') {
                        return null;
                    }

                    const address = {
                        street: ($item.data('street') || '').toString(),
                        address_2: ($item.data('apartment') || '').toString(),
                        city: ($item.data('city') || '').toString(),
                        state: ($item.data('state') || '').toString(),
                        postal: ($item.data('postal') || '').toString(),
                        country: ($item.data('country') || '').toString()
                    };

                    const hasData = Object.keys(address).some((key) => !this.isInvalidAddressValue(address[key]));
                    return hasData ? address : null;
                },

                normalizeAddressPayload: function(address) {
                    return {
                        street: (address && address.street) ? address.street : '',
                        address_2: (address && address.address_2) ? address.address_2 : '',
                        city: (address && address.city) ? address.city : '',
                        state: (address && address.state) ? address.state : '',
                        postcode: (address && (address.postcode || address.postal)) ? (address.postcode || address.postal) : '',
                        country: (address && address.country) ? address.country : ''
                    };
                },

                resolveSelectValue: function($select, value) {
                    if (!$select || !$select.length) {
                        return value;
                    }

                    const rawValue = (value || '').toString().trim();
                    if (!rawValue) {
                        return '';
                    }

                    const normalized = rawValue.toLowerCase();
                    let matched = '';
                    $select.find('option').each(function() {
                        const optionValue = ($(this).val() || '').toString();
                        const optionText = ($(this).text() || '').toString();
                        if (!optionValue) {
                            return;
                        }
                        if (optionValue.toLowerCase() === normalized || optionText.toLowerCase() === normalized) {
                            matched = optionValue;
                            return false;
                        }
                        if (!matched && optionText.toLowerCase().includes(normalized)) {
                            matched = optionValue;
                        }
                    });
                    return matched || rawValue;
                },

                setFieldValue: function($field, value, force) {
                    if (!$field || !$field.length) {
                        return;
                    }
                    if (!force && ($field.val() || '').toString().trim() !== '') {
                        return;
                    }

                    $field.val(value).trigger('change');
                },

                applyCheckoutAddress: function(address, force) {
                    const normalized = this.normalizeAddressPayload(address);

                    const $shippingAddress = $('#shipping_address_1');
                    const $billingAddress = $('#billing_address_1');
                    if (!$shippingAddress.length && !$billingAddress.length) {
                        return;
                    }

                    const hasShippingFields = $('#shipping_address_1, #shipping_city, #shipping_postcode, #shipping_country').length > 0;
                    const shipToDifferent = $('#ship-to-different-address-checkbox').length
                        ? $('#ship-to-different-address-checkbox').is(':checked')
                        : hasShippingFields;
                    const prefix = (hasShippingFields && shipToDifferent) ? 'shipping' : 'billing';

                    const $country = $('#' + prefix + '_country');
                    const $state = $('#' + prefix + '_state');
                    const countryValue = this.resolveSelectValue($country, normalized.country);

                    this.setFieldValue($('#' + prefix + '_address_1'), normalized.street, force);
                    this.setFieldValue($('#' + prefix + '_address_2'), normalized.address_2, force);
                    this.setFieldValue($('#' + prefix + '_city'), normalized.city, force);
                    this.setFieldValue($('#' + prefix + '_postcode'), normalized.postcode, force);

                    if ($country.length) {
                        this.setFieldValue($country, countryValue, force);
                    }

                    const applyState = () => {
                        if ($state.length) {
                            const stateValue = this.resolveSelectValue($state, normalized.state);
                            this.setFieldValue($state, stateValue, force);
                        }
                    };

                    applyState();
                    setTimeout(applyState, 300);

                    if (this.isCheckoutContext() || $('#checkout').length) {
                        $('body').trigger('update_checkout');
                    }
                },

                updateCustomerAddress: function(address, done, options) {
                    const settings = options || {};
                    const normalized = this.normalizeAddressPayload(address);
                    const hasData = Object.keys(normalized).some((key) => !this.isInvalidAddressValue(normalized[key]));

                    if (!this.isAutoPopulateEnabled() || !hasData) {
                        if (typeof done === 'function') {
                            done(false);
                        }
                        return;
                    }

                    const payload = {
                        action: 'mulopimfwc_update_customer_address',
                        nonce: (window.mulopimfwc_locationWiseProducts || {}).autoPopulateNonce || '',
                        street: normalized.street,
                        address_2: normalized.address_2,
                        city: normalized.city,
                        state: normalized.state,
                        postcode: normalized.postcode,
                        country: normalized.country
                    };

                    const force = settings.force === true;
                    this.applyCheckoutAddress(normalized, force);

                    const ajaxUrl = (window.mulopimfwc_locationWiseProducts || {}).ajaxUrl;
                    if (!ajaxUrl || !payload.nonce) {
                        if (typeof done === 'function') {
                            done(false);
                        }
                        return;
                    }

                    if (this.autoPopulateInFlight) {
                        if (typeof done === 'function') {
                            done(false);
                        }
                        return;
                    }

                    this.autoPopulateInFlight = true;
                    $.ajax({
                        url: ajaxUrl,
                        type: 'POST',
                        data: payload,
                        success: (res) => {
                            if (res && res.success && res.data && res.data.address) {
                                this.applyCheckoutAddress(res.data.address, force);
                            }
                        },
                        complete: () => {
                            this.autoPopulateInFlight = false;
                            if (typeof done === 'function') {
                                done(true);
                            }
                        }
                    });
                },

                maybeAutoPopulateFromDetected: function(address) {
                    if (!this.isAutoPopulateEnabled() || this.isLoggedIn()) {
                        return;
                    }

                    if (this.autoPopulatePerformed) {
                        return;
                    }

                    this.autoPopulatePerformed = true;
                    this.updateCustomerAddress(address, null, { force: false });
                },

                // ========================================
                // AUTO-DETECT USER LOCATION
                // ========================================

                detectUserLocationAndSetInput: function() {
                    // Only proceed if the address input exists
                    if (!$('#address-search-input').length) return;

                    // Attempt to get user location
                    this.attemptLocationDetection((lat, lng, address) => {
                        // Format the address for display
                        const addressParts = [
                            address.street || '',
                            address.city || '',
                            (address.state || '') + ' ' + (address.postal || ''),
                            address.country || ''
                        ].filter(part => part.trim());

                        const addressString = addressParts.join(', ');

                        // Update the input
                        $('#address-search-input').val(addressString).prop('readonly', false);

                        this.maybeAutoPopulateFromDetected(address);
                    });
                },

                // ========================================
                // OPEN POPUP WITH LOCATION DETECTION
                // ========================================

                openLocationPopupWithDetection: function(e) {
                    e.preventDefault();
                    $('#lwp-location-popup').show();
                    $('#lwp-location-step1').show();
                    $('#lwp-location-step2').hide();

                    this.initMap();
                    setTimeout(() => {
                        if (this.map) this.map.invalidateSize();
                    }, 0);

                    this.attemptLocationDetection((lat, lng) => {
                        this.setMapLocation(lat, lng);
                        if (this.marker) this.marker.setLatLng([lat, lng]);
                    });
                },

                // ========================================
                // LOCATION DROPDOWN FUNCTIONALITY
                // ========================================

                handleSavedLocationChange: function(e) {
                    const selectedLocation = $(e.target).val();
                    if (selectedLocation) {
                        this.setUserLocation(selectedLocation);
                        if (this.isAutoPopulateEnabled() && this.pendingAutoPopulateAddress) {
                            const pendingAddress = this.pendingAutoPopulateAddress;
                            this.pendingAutoPopulateAddress = null;
                            this.updateCustomerAddress(pendingAddress, () => {
                                this.reloadPage();
                            }, { force: true });
                            return;
                        }
                        this.reloadPage();
                    }
                },

                // ========================================
                // POPUP MANAGEMENT
                // ========================================

                openLocationPopup: function(e) {
                    e.preventDefault();
                    $('#lwp-location-popup').show();
                    $('#lwp-location-step1').show();
                    $('#lwp-location-step2').hide();

                    this.initMap();
                    this.attemptLocationDetection();
                },

                closeLocationPopup: function() {
                    $('#lwp-location-popup').hide();
                },

                handleContinueToStep2: function() {
                    if (!this.selectedLat || !this.selectedLng) {
                        this.showAlert('Please select a location on the map');
                        return;
                    }

                    $('#lwp-location-step1').hide();
                    $('#lwp-location-step2').show();
                    this.populateLocationForm();
                },

                handleBackToStep1: function() {
                    $('#lwp-location-step2').hide();
                    $('#lwp-location-step1').show();
                },

                populateLocationForm: function() {
                    $('#lwp-location-street').val(this.selectedAddress.street || '');
                    $('#lwp-location-apartment').val('');
                    $('#lwp-location-note').val('');
                    $('#lwp-location-lat').val(this.selectedLat);
                    $('#lwp-location-lng').val(this.selectedLng);
                    $('#lwp-location-city').val(this.selectedAddress.city || '');
                    $('#lwp-location-state').val(this.selectedAddress.state || '');
                    $('#lwp-location-postal').val(this.selectedAddress.postal || '');
                    $('#lwp-location-country').val(this.selectedAddress.country || '');

                    this.updateAddressPreview();
                },

                updateAddressPreview: function() {
                    const addressParts = [
                        this.selectedAddress.street || '',
                        this.selectedAddress.city || '',
                        (this.selectedAddress.state || '') + ' ' + (this.selectedAddress.postal || ''),
                        this.selectedAddress.country || ''
                    ].filter(part => part.trim());

                    $('.lwp-address-preview').text(addressParts.join(', '));
                },

                // ========================================
                // FORM SUBMISSION
                // ========================================

                handleLocationFormSubmit: function(e) {
                    e.preventDefault();

                    const isEditing = !!$('#lwp-editing-location-id').val();
                    const action = 'mulopimfwc_save_user_location';
                    const payload = $(e.target).serialize() + '&action=' + action;

                    $.ajax({
                        url: mulopimfwc_locationWiseProducts.ajaxUrl,
                        type: 'POST',
                        data: payload,
                        success: (res) => {
                            if (!res || !res.success) {
                                this.showAlert((res && res.data && res.data.message) || 'Error saving location');
                                return;
                            }

                            if (isEditing) {
                                this.handleUpdateSuccess(res);
                            } else {
                                this.handleLocationSaveSuccess(res);
                            }
                        },
                        error: () => this.showAlert('Error saving location')
                    });
                },


                handleLocationSaveSuccess: function(response) {
                    if (response.success) {
                        this.closeLocationPopup();

                        if (response.data.logged_in) {
                            this.addLocationToDropdown(response.data);
                        }

                        const payload = (response.data && response.data.location && typeof response.data.location === 'object')
                            ? response.data.location
                            : ((response.data && typeof response.data === 'object') ? response.data : {});
                        const savedLocationId = (response.data && response.data.location_id)
                            ? response.data.location_id
                            : ((payload && (payload.locationId || payload.id || payload.location_id)) || '');
                        if (savedLocationId) {
                            payload.locationId = savedLocationId;
                            payload.id = savedLocationId;
                        }
                        payload.canForceUserLocation = !!(response.data && response.data.logged_in);
                        this.updateCurrentLocation(payload, null);
                    } else {
                        this.showAlert(response.data.message || 'Error saving location');
                    }
                },


                isGenericSavedLabel: function(label) {
                    const value = (label || '').toString().trim().toLowerCase();
                    if (!value) {
                        return true;
                    }

                    return ['home', 'work', 'partner', 'other'].includes(value);
                },

                buildResolverQueryText: function(label, address, city, state, country) {
                    const cleanLabel = (label || '').toString().trim();
                    const cleanAddress = (address || '').toString().trim();
                    const fallbackAddress = [cleanAddress, city, state, country]
                        .map((part) => (part || '').toString().trim())
                        .filter(Boolean)
                        .join(', ');

                    if (cleanLabel && !this.isGenericSavedLabel(cleanLabel)) {
                        return cleanLabel;
                    }

                    return fallbackAddress;
                },

                buildResolverPayloadFromItem: function($item) {
                    if (!$item || !$item.length) {
                        return null;
                    }

                    const rawLat = parseFloat($item.data('lat'));
                    const rawLng = parseFloat($item.data('lng'));
                    const label = ($item.data('label') || '').toString().trim();
                    const address = ($item.data('address') || '').toString().trim();
                    const locationId = ($item.data('location-id') || '').toString().trim();

                    return {
                        locationId: locationId,
                        canForceUserLocation: true,
                        label: label,
                        address: address,
                        street: ($item.data('street') || '').toString().trim(),
                        city: ($item.data('city') || '').toString().trim(),
                        state: ($item.data('state') || '').toString().trim(),
                        country: ($item.data('country') || '').toString().trim(),
                        lat: Number.isFinite(rawLat) ? rawLat : null,
                        lng: Number.isFinite(rawLng) ? rawLng : null,
                        query: this.buildResolverQueryText(
                            label,
                            address,
                            ($item.data('city') || '').toString().trim(),
                            ($item.data('state') || '').toString().trim(),
                            ($item.data('country') || '').toString().trim()
                        )
                    };
                },

                buildResolverPayloadFromLocationData: function(locationData) {
                    if (!locationData || typeof locationData !== 'object') {
                        return null;
                    }

                    const rawLat = parseFloat(locationData.lat);
                    const rawLng = parseFloat(locationData.lng);
                    const label = (locationData.label || '').toString().trim();
                    const address = (locationData.address || '').toString().trim();
                    const locationId = (locationData.locationId || locationData.id || locationData.location_id || '').toString().trim();

                    return {
                        locationId: locationId,
                        canForceUserLocation: locationData.canForceUserLocation !== false,
                        label: label,
                        address: address,
                        street: (locationData.street || '').toString().trim(),
                        city: (locationData.city || '').toString().trim(),
                        state: (locationData.state || '').toString().trim(),
                        country: (locationData.country || '').toString().trim(),
                        lat: Number.isFinite(rawLat) ? rawLat : null,
                        lng: Number.isFinite(rawLng) ? rawLng : null,
                        query: this.buildResolverQueryText(
                            label,
                            address,
                            (locationData.city || '').toString().trim(),
                            (locationData.state || '').toString().trim(),
                            (locationData.country || '').toString().trim()
                        )
                    };
                },

                setForcedUserLocationSelection: function(locationId, storeSlug) {
                    const cleanLocationId = (locationId || '').toString().trim();
                    if (!cleanLocationId) {
                        this.clearForcedUserLocationSelection();
                        return;
                    }

                    window.mulopimfwcForcedUserLocationSelection = {
                        locationId: cleanLocationId,
                        storeSlug: (storeSlug || '').toString().trim().toLowerCase()
                    };
                },

                clearForcedUserLocationSelection: function() {
                    if (Object.prototype.hasOwnProperty.call(window, 'mulopimfwcForcedUserLocationSelection')) {
                        delete window.mulopimfwcForcedUserLocationSelection;
                    }
                },

                resolveStoreLocation: function(payload, callback) {
                    const ajaxUrl = (window.mulopimfwc_locationWiseProducts || {}).ajaxUrl;
                    const nonce = ($('#mulopimfwc_shortcode_selector_nonce').val() || '').toString();

                    if (!ajaxUrl || !nonce) {
                        if (typeof callback === 'function') {
                            callback(null);
                        }
                        return;
                    }

                    const requestData = {
                        action: 'mulopimfwc_resolve_store_location',
                        nonce: nonce,
                        query: (payload.query || [payload.address, payload.city, payload.state, payload.country].filter(Boolean).join(', ')).toString(),
                        address: (payload.address || '').toString(),
                        city: (payload.city || '').toString(),
                        state: (payload.state || '').toString(),
                        country: (payload.country || '').toString()
                    };

                    if (payload.lat !== null && payload.lat !== undefined && Number.isFinite(payload.lat)) {
                        requestData.lat = payload.lat;
                    }
                    if (payload.lng !== null && payload.lng !== undefined && Number.isFinite(payload.lng)) {
                        requestData.lng = payload.lng;
                    }

                    $.ajax({
                        url: ajaxUrl,
                        type: 'POST',
                        data: requestData
                    }).done((response) => {
                        if (typeof callback === 'function') {
                            callback(response);
                        }
                    }).fail(() => {
                        if (typeof callback === 'function') {
                            callback(null);
                        }
                    });
                },

                setResolvedDropdownSelection: function(slug) {
                    const targetSlug = (slug || '').toString().trim().toLowerCase();
                    if (!targetSlug) {
                        return false;
                    }

                    const $dropdown = $('select#lwp-shortcode-selector, select.lwp-shortcode-selector-dropdown');
                    let found = false;

                    $dropdown.find('option').each(function() {
                        const optionValue = ($(this).val() || '').toString().trim();
                        if (!optionValue) {
                            return;
                        }
                        if (optionValue.toLowerCase() === targetSlug) {
                            $(this).prop('selected', true);
                            found = true;
                            return false;
                        }
                    });

                    if (found) {
                        $('#lwp-selected-store-shortcode').val(targetSlug);
                    }

                    return found;
                },

                tryLegacyAddressSelection: function(address, $item = null, payload = null) {
                    const addrText = (address || '').toString().trim();
                    if (!addrText) {
                        return false;
                    }

                    const addrArray = addrText.split(',').map(s => s.trim().toLowerCase()).filter(Boolean);
                    if (!addrArray.length) {
                        return false;
                    }

                    const $dropdown = $('select#lwp-shortcode-selector, select.lwp-shortcode-selector-dropdown');
                    let found = false;

                    const dropdownValues = [];
                    $dropdown.find('option').each(function() {
                        if (($(this).val() || '').toString().trim() === '') return;
                        dropdownValues.push($(this).val().toLowerCase());
                    });

                    for (let i = 0; i < addrArray.length; i++) {
                        const addr = addrArray[i];
                        if (dropdownValues.includes(addr)) {
                            $dropdown.find('option').each(function() {
                                if ($(this).val().toLowerCase() === addr) {
                                    $(this).prop('selected', true);
                                    found = true;
                                    return false;
                                }
                            });
                            break;
                        }
                    }

                    if (!found) {
                        for (let i = 0; i < addrArray.length; i++) {
                            const addr = addrArray[i];
                            $dropdown.find('option').each(function() {
                                if ($(this).text().trim().toLowerCase().includes(addr)) {
                                    $(this).prop('selected', true);
                                    found = true;
                                    return false;
                                }
                            });
                            if (found) break;
                        }
                    }

                    if (found) {
                        if (payload && payload.canForceUserLocation && payload.locationId) {
                            const selectedSlug = ($dropdown.val() || '').toString().trim().toLowerCase();
                            this.setForcedUserLocationSelection(payload.locationId, selectedSlug);
                            if (selectedSlug) {
                                this.setStoreLocation(selectedSlug);
                            }
                        }
                        if ($item) {
                            $('.saved-location-item').removeClass('selected');
                            $item.addClass('selected');
                            this.updateAddressDisplay($item);
                        }
                        $dropdown.trigger('change');
                        $('#location-tooltip').hide();
                        return true;
                    }

                    return false;
                },

                buildResolverSuggestionMessage: function(candidates) {
                    if (!Array.isArray(candidates) || !candidates.length) {
                        return 'We could not confidently match this address to a store. Please choose your store manually.';
                    }

                    const names = candidates
                        .slice(0, 3)
                        .map((candidate) => (candidate && candidate.name) ? candidate.name : '')
                        .filter(Boolean);

                    if (!names.length) {
                        return 'We could not confidently match this address to a store. Please choose your store manually.';
                    }

                    return 'Multiple nearby matches found: ' + names.join(', ') + '. Please choose your store manually.';
                },

                updateCurrentLocation: function(locationData, $item = null) {
                    if ($item && $item.hasClass('selected')) {
                        $('#location-tooltip').hide();
                        return;
                    }

                    const payload = this.buildResolverPayloadFromLocationData(locationData);
                    if (!payload) {
                        this.clearForcedUserLocationSelection();
                        $('#location-tooltip').hide();
                        this.showAlert('No matching store found for this address.');
                        return;
                    }

                    if (!payload.canForceUserLocation || !payload.locationId) {
                        this.clearForcedUserLocationSelection();
                    }

                    if ((payload.locationId || '').toLowerCase() === 'all-products') {
                        if (this.setResolvedDropdownSelection('all-products')) {
                            this.setForcedUserLocationSelection('all-products', 'all-products');
                            this.setStoreLocation('all-products');
                            if ($item) {
                                $('.saved-location-item').removeClass('selected');
                                $item.addClass('selected');
                                this.updateAddressDisplay($item);
                            }
                            $('select#lwp-shortcode-selector, select.lwp-shortcode-selector-dropdown').trigger('change');
                            $('#location-tooltip').hide();
                            return;
                        }
                    }

                    this.resolveStoreLocation(payload, (resolverResponse) => {
                        let resolverWasAmbiguous = false;

                        if (resolverResponse && resolverResponse.success && resolverResponse.data) {
                            const data = resolverResponse.data;
                            const status = (data.status || '').toString();
                            const matchedSlug = (data.slug || '').toString();

                            if (status === 'matched' && matchedSlug) {
                                if (this.setResolvedDropdownSelection(matchedSlug)) {
                                    if (payload.canForceUserLocation && payload.locationId) {
                                        this.setForcedUserLocationSelection(payload.locationId, matchedSlug);
                                    }
                                    this.setStoreLocation(matchedSlug);
                                    if ($item) {
                                        $('.saved-location-item').removeClass('selected');
                                        $item.addClass('selected');
                                        this.updateAddressDisplay($item);
                                    }

                                    $('select#lwp-shortcode-selector, select.lwp-shortcode-selector-dropdown').trigger('change');
                                    $('#location-tooltip').hide();
                                    return;
                                }
                            }

                            if (status === 'ambiguous') {
                                resolverWasAmbiguous = true;
                                this.showAlert(this.buildResolverSuggestionMessage(data.candidates || []));
                            }
                        }

                        if (this.tryLegacyAddressSelection(payload.address, $item, payload)) {
                            return;
                        }

                        if (resolverWasAmbiguous) {
                            return;
                        }

                        this.showAlert('We don\'t have product for this location. Please choose another location.');
                    });
                },

                handleUpdateSuccess: function(response) {
                    // Reset editing state and close
                    $('#lwp-editing-location-id').val('');
                    this.closeLocationPopup();
                    this.reloadPage();
                },


                addLocationToDropdown: function(locationData) {
                    const newOption = $('<option>', {
                        value: locationData.location_id,
                        text: locationData.label + ' - ' + locationData.address
                    });
                    $('.lwp-saved-locations').append(newOption).val(locationData.location_id);
                },

                // ========================================
                // TOOLTIP INTERFACE
                // ========================================

                toggleTooltip: function(e) {
                    e.stopPropagation();
                    $('#location-tooltip').toggle();
                },

                closeTooltipOnOutsideClick: function(e) {
                    if (!$(e.target).closest('.lwp-user-location-features').length) {
                        $('#location-tooltip').hide();
                    }
                },

                clearAddressInput: function() {
                    $('#address-search-input').val('').focus();
                    $('.search-results').remove();
                },

                handleAddressSearch: function(e) {
                    const query = $(e.target).val();

                    if (query.length > this.config.search.minQueryLength) {
                        clearTimeout(this.searchTimeout);
                        this.searchTimeout = setTimeout(() => {
                            this.searchAddresses(query);
                        }, this.config.search.debounceDelay);
                    } else {
                        $('.search-results').remove();
                    }
                },

                // ========================================
                // SAVED LOCATION MANAGEMENT
                // ========================================

                handleSavedLocationSelect: function(e) {
                    const $item = $(e.currentTarget);
                    const payload = this.buildResolverPayloadFromItem($item);

                    if (payload && payload.locationId) {
                        this.setForcedUserLocationSelection(payload.locationId, '');
                    }

                    if (this.isAutoPopulateEnabled()) {
                        this.pendingAutoPopulateAddress = this.buildAddressFromItem($item);
                    }

                    this.updateCurrentLocation(payload, $item);

                },

                updateAddressDisplay: function($item) {
                    const locationId = ($item.data('location-id') || '').toString().trim();
                    const dataLabel = ($item.data('label') || '').toString().trim();
                    const dataLabelDisplay = ($item.data('label-display') || '').toString().trim();
                    const dataAddress = ($item.data('address') || '').toString().trim();
                    const locationLabel = dataLabelDisplay || dataLabel || $item.find('.location-label').text().trim();
                    const locationAddress = locationId === 'all-products' ?
                        '' :
                        (dataAddress || $item.find('.location-address').text().trim());
                    const displayText = locationAddress ? locationLabel + ' - ' + locationAddress : locationLabel;
                    $('.address-text').text(displayText);
                },

                handleEditLocation: function(e) {
                    e.stopPropagation();

                    const $item = $(e.currentTarget).closest('.saved-location-item');
                    const loc = {
                        id: $item.data('locationId'),
                        label: ($item.data('label') || '').toString(),
                        street: ($item.data('street') || '').toString(),
                        city: ($item.data('city') || '').toString(),
                        state: ($item.data('state') || '').toString(),
                        postal: ($item.data('postal') || '').toString(),
                        country: ($item.data('country') || '').toString(),
                        note: ($item.data('note') || '').toString(),
                        lat: parseFloat($item.data('lat')) || null,
                        lng: parseFloat($item.data('lng')) || null,
                        address: ($item.data('address') || '').toString()
                    };

                    // Fallback: if we don’t have lat/lng yet (older saves), fetch once via AJAX
                    if (!loc.lat || !loc.lng) {
                        $.ajax({
                            url: mulopimfwc_locationWiseProducts.ajaxUrl,
                            type: 'POST',
                            data: {
                                action: 'mulopimfwc_get_user_location',
                                location_id: loc.id,
                                nonce: $('#mulopimfwc_shortcode_selector_nonce').val()
                            }
                        }).done((res) => {
                            if (res && res.success && res.data && res.data.location) {
                                Object.assign(loc, res.data.location);
                            }
                            this.openEditLocationForm(loc);
                        }).fail(() => this.openEditLocationForm(loc));
                        return;
                    }

                    this.openEditLocationForm(loc);
                },

                openEditLocationForm: function(loc) {
                    // Show popup on step 2 with prefilled values
                    $('#lwp-location-popup').show();
                    $('#lwp-location-step1').hide();
                    $('#lwp-location-step2').show();

                    // Track editing state
                    $('#lwp-editing-location-id').val(loc.id);

                    // Map state in case user wants to tweak pin
                    this.initMap();
                    const lat = loc.lat ?? this.config.defaultLocation.lat;
                    const lng = loc.lng ?? this.config.defaultLocation.lng;
                    this.setMapLocation(lat, lng);

                    // Cache for reverse geocode consistency
                    this.selectedLat = lat;
                    this.selectedLng = lng;
                    this.selectedAddress = {
                        street: loc.street || '',
                        city: loc.city || '',
                        state: loc.state || '',
                        postal: loc.postal || '',
                        country: loc.country || ''
                    };

                    // Prefill form
                    $('#lwp-location-label').val(loc.label || 'Other');
                    $('#lwp-location-street').val(loc.street || '');
                    $('#lwp-location-apartment').val(''); // optional: store separately if you support it
                    $('#lwp-location-note').val(loc.note || '');
                    $('#lwp-location-lat').val(lat);
                    $('#lwp-location-lng').val(lng);
                    $('#lwp-location-city').val(loc.city || '');
                    $('#lwp-location-state').val(loc.state || '');
                    $('#lwp-location-postal').val(loc.postal || '');
                    $('#lwp-location-country').val(loc.country || '');

                    $('.lwp-form-actions button[type="submit"]').text(mulopimfwc_locationWiseProducts.i18n_update || 'Update Location');

                    this.updateAddressPreview();
                },


                handleDeleteLocation: function(e) {
                    e.stopPropagation();
                    const locationId = $(e.currentTarget).data('locationId');

                    if (confirm(deleteLocationConfirmMessage)) {
                        this.deleteLocation(locationId);
                    }
                },

                deleteLocation: function(locationId) {
                    $.ajax({
                        url: mulopimfwc_locationWiseProducts.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'mulopimfwc_delete_user_location',
                            location_id: locationId,
                            nonce: $('#mulopimfwc_shortcode_selector_nonce').val()
                        },
                        success: (response) => this.handleDeleteSuccess(response, locationId),
                        error: () => this.showAlert('Error deleting location')
                    });
                },

                handleDeleteSuccess: function(response, locationId) {
                    if (response.success) {
                        $('.saved-location-item[data-location-id="' + locationId + '"]').remove();

                        if ($('.saved-location-item').length === 0) {
                            $('.saved-locations-list').html('<p class="no-saved-locations">No saved locations yet</p>');
                        }

                        if ($('.saved-location-item[data-location-id="' + locationId + '"]').hasClass('selected')) {
                            $('.address-text').text('<?php echo esc_js($shortcode_texts['set_your_location']); ?>');
                            this.clearUserLocation();
                        }
                    } else {
                        this.showAlert(response.data.message || 'Error deleting location');
                    }
                },

                // ========================================
                // LOCATION DETECTION
                // ========================================

                attemptLocationDetection: function(callback) {
                    if (navigator.geolocation) {
                        this.tryGeolocation(callback);
                    } else {
                        this.getLocationByIP(callback);
                    }
                },

                tryGeolocation: function(callback) {
                    navigator.geolocation.getCurrentPosition(
                        (position) => this.handleGeolocationSuccess(position, callback),
                        () => this.tryLowAccuracyGeolocation(callback),
                        this.config.geolocation.highAccuracy
                    );
                },

                tryLowAccuracyGeolocation: function(callback) {
                    navigator.geolocation.getCurrentPosition(
                        (position) => this.handleGeolocationSuccess(position, callback),
                        () => this.getLocationByIP(callback),
                        this.config.geolocation.lowAccuracy
                    );
                },

                handleGeolocationSuccess: function(position, callback) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    this.reverseGeocode(lat, lng, callback);
                },

                // Replace getLocationByIP() implementation with HTTPS provider
                getLocationByIP: function(callback) {
                    // Check cache first (24 hour cache)
                    const cacheKey = 'mulopimfwc_ip_location';
                    const cached = localStorage.getItem(cacheKey);
                    if (cached) {
                        try {
                            const cachedData = JSON.parse(cached);
                            const cacheTime = cachedData.timestamp || 0;
                            const now = Date.now();
                            // Cache valid for 24 hours
                            if (now - cacheTime < 24 * 60 * 60 * 1000 && cachedData.data && cachedData.data.latitude && cachedData.data.longitude) {
                                const data = cachedData.data;
                                this.reverseGeocode(data.latitude, data.longitude, callback);
                                return;
                            }
                        } catch (e) {
                            // Invalid cache, continue to API call
                        }
                    }

                    // Check rate limit cooldown (1 hour cooldown after 429 error)
                    const rateLimitKey = 'mulopimfwc_ip_ratelimit';
                    const rateLimitData = localStorage.getItem(rateLimitKey);
                    if (rateLimitData) {
                        try {
                            const rateLimitInfo = JSON.parse(rateLimitData);
                            const rateLimitTime = rateLimitInfo.timestamp || 0;
                            const now = Date.now();
                            // Cooldown period: 1 hour
                            if (now - rateLimitTime < 60 * 60 * 1000) {
                                // Rate limited, fall back to timezone
                                this.getLocationByTimezone(callback);
                                return;
                            }
                        } catch (e) {
                            // Invalid rate limit data, continue
                        }
                    }

                    // Check if we're already making a request (prevent multiple simultaneous calls)
                    if (window.mulopimfwc_ipRequestInProgress) {
                        // Don't retry - fall back to timezone
                        this.getLocationByTimezone(callback);
                        return;
                    }

                    window.mulopimfwc_ipRequestInProgress = true;

                    $.ajax({
                        url: 'https://ipapi.co/jsonp/', // <- JSONP endpoint (not /json/)
                        dataType: 'jsonp',
                        timeout: 5000
                    }).done((data) => {
                        window.mulopimfwc_ipRequestInProgress = false;

                        if (data && data.latitude && data.longitude) {
                            // Cache the result
                            try {
                                localStorage.setItem(cacheKey, JSON.stringify({
                                    timestamp: Date.now(),
                                    data: data
                                }));
                            } catch (e) {
                                // localStorage might be disabled, ignore
                            }

                            this.reverseGeocode(data.latitude, data.longitude, callback);
                        } else {
                            this.getLocationByTimezone(callback);
                        }
                    }).fail((xhr, status, error) => {
                        window.mulopimfwc_ipRequestInProgress = false;

                        // For JSONP, xhr.status might not be available, but we can check the error message
                        // Rate limit errors often show as "abort" or "timeout" status, or status 429
                        const isRateLimit = (status === 'abort' || status === 'timeout' ||
                            (xhr && (xhr.status === 429 || xhr.status === 0)));

                        if (isRateLimit) {
                            // Store rate limit timestamp for cooldown
                            try {
                                localStorage.setItem(rateLimitKey, JSON.stringify({
                                    timestamp: Date.now()
                                }));
                            } catch (e) {
                                // localStorage might be disabled, ignore
                            }
                        }

                        // Always fall back to timezone on failure
                        this.getLocationByTimezone(callback);
                    });
                },


                getLocationByTimezone: function(callback) {
                    try {
                        const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
                        const coords = this.getTimezoneCoordinates(timezone);
                        this.reverseGeocode(coords[0], coords[1], callback);
                    } catch (error) {
                        if (callback) callback(this.config.defaultLocation.lat, this.config.defaultLocation.lng, {});
                    }
                },

                getTimezoneCoordinates: function(timezone) {
                    const timezoneToLocation = {
                        // Major US timezones
                        'America/New_York': [40.7128, -74.0060],
                        'America/Chicago': [41.8781, -87.6298],
                        'America/Denver': [39.7392, -104.9903],
                        'America/Los_Angeles': [34.0522, -118.2437],
                        'America/Phoenix': [33.4484, -112.0740],
                        'America/Detroit': [42.3314, -83.0458],
                        'America/Anchorage': [61.2181, -149.9003],
                        'Pacific/Honolulu': [21.3099, -157.8581],

                        // Canada
                        'America/Toronto': [43.6532, -79.3832],
                        'America/Vancouver': [49.2827, -123.1207],
                        'America/Montreal': [45.5017, -73.5673],

                        // Major European timezones
                        'Europe/London': [51.5074, -0.1278],
                        'Europe/Paris': [48.8566, 2.3522],
                        'Europe/Berlin': [52.5200, 13.4050],
                        'Europe/Rome': [41.9028, 12.4964],
                        'Europe/Madrid': [40.4168, -3.7038],
                        'Europe/Amsterdam': [52.3676, 4.9041],
                        'Europe/Stockholm': [59.3293, 18.0686],
                        'Europe/Moscow': [55.7558, 37.6173],

                        // Major Asian timezones
                        'Asia/Tokyo': [35.6762, 139.6503],
                        'Asia/Shanghai': [31.2304, 121.4737],
                        'Asia/Beijing': [39.9042, 116.4074],
                        'Asia/Kolkata': [28.7041, 77.1025],
                        'Asia/Dubai': [25.2048, 55.2708],
                        'Asia/Singapore': [1.3521, 103.8198],
                        'Asia/Seoul': [37.5665, 126.9780],
                        'Asia/Bangkok': [13.7563, 100.5018],
                        'Asia/Manila': [14.5995, 120.9842],

                        // Australia/Oceania
                        'Australia/Sydney': [-33.8688, 151.2093],
                        'Australia/Melbourne': [-37.8136, 144.9631],
                        'Australia/Perth': [-31.9505, 115.8605],
                        'Pacific/Auckland': [-36.8485, 174.7633],

                        // South America
                        'America/Sao_Paulo': [-23.5505, -46.6333],
                        'America/Buenos_Aires': [-34.6118, -58.3960],
                        'America/Lima': [-12.0464, -77.0428],

                        // Africa
                        'Africa/Cairo': [30.0444, 31.2357],
                        'Africa/Lagos': [6.5244, 3.3792],
                        'Africa/Johannesburg': [-26.2041, 28.0473]
                    };

                    if (timezoneToLocation[timezone]) {
                        return timezoneToLocation[timezone];
                    }

                    // Try to extract region from timezone
                    const parts = timezone.split('/');
                    if (parts.length >= 2) {
                        const regionDefaults = {
                            'America': [39.8283, -98.5795],
                            'Europe': [54.5260, 15.2551],
                            'Asia': [34.0479, 100.6197],
                            'Africa': [0.0236, 37.9062],
                            'Australia': [-25.2744, 133.7751],
                            'Pacific': [-8.7832, 124.5085]
                        };

                        return regionDefaults[parts[0]] || [this.config.defaultLocation.lat, this.config.defaultLocation.lng];
                    }

                    return [this.config.defaultLocation.lat, this.config.defaultLocation.lng];
                },

                // ========================================
                // MAP FUNCTIONALITY
                // ========================================

                initMap: function() {
                    if (!$('#lwp-location-map').length) return;

                    // Destroy previous instance if it exists
                    if (this.map) {
                        this.map.remove();
                        this.map = null;
                        this.marker = null;
                    }

                    this.map = L.map('lwp-location-map').setView(
                        [this.config.defaultLocation.lat, this.config.defaultLocation.lng], 13
                    );

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                    }).addTo(this.map);

                    this.marker = L.marker(
                        [this.config.defaultLocation.lat, this.config.defaultLocation.lng], {
                            draggable: true
                        }
                    ).addTo(this.map);

                    this.marker.on('dragend', this.handleMarkerDrag.bind(this));
                    this.map.on('click', this.handleMapClick.bind(this));

                    // Fix sizing after popup becomes visible
                    setTimeout(() => {
                        this.map.invalidateSize();
                    }, 0);
                },


                handleMarkerDrag: function(event) {
                    const position = this.marker.getLatLng();
                    this.selectedLat = position.lat;
                    this.selectedLng = position.lng;
                    this.reverseGeocode(this.selectedLat, this.selectedLng);
                },

                handleMapClick: function(event) {
                    const position = event.latlng;
                    this.marker.setLatLng(position);
                    this.selectedLat = position.lat;
                    this.selectedLng = position.lng;
                    this.reverseGeocode(this.selectedLat, this.selectedLng);
                },

                setMapLocation: function(lat, lng) {
                    if (this.map && this.marker) {
                        this.map.setView([lat, lng], 13);
                        this.marker.setLatLng([lat, lng]);
                        this.selectedLat = lat;
                        this.selectedLng = lng;
                    }
                },

                // ========================================
                // GEOCODING FUNCTIONALITY
                // ========================================

                reverseGeocode: function(lat, lng, callback) {
                    const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`;

                    $.ajax({
                        url: url,
                        method: 'GET',
                        dataType: 'json',
                        success: (data) => {
                            if (data && data.address) {
                                this.selectedAddress = {
                                    street: data.address.road || data.address.pedestrian || '',
                                    city: data.address.city || data.address.town || data.address.village || '',
                                    state: data.address.state || '',
                                    postal: data.address.postcode || '',
                                    country: data.address.country || ''
                                };
                            }
                            if (callback) callback(lat, lng, this.selectedAddress);
                        },
                        error: () => {
                            console.warn('Reverse geocoding failed');
                            if (callback) callback(lat, lng, {});
                        }
                    });
                },

                searchAddresses: function(query, firstResultCallback) {
                    const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=${this.config.search.maxResults}&addressdetails=1`;
                    $.ajax({
                        url: url,
                        method: 'GET',
                        dataType: 'json'
                    }).done((results) => {
                        if (typeof firstResultCallback === 'function') {
                            // If used via Enter key: return the first result and skip rendering the list
                            firstResultCallback(results && results.length ? results[0] : null);
                            return;
                        }
                        this.displaySearchResults(results);
                    }).fail(() => console.warn('Address search failed'));
                },


                displaySearchResults: function(results) {
                    $('.search-results').remove();

                    if (results.length > 0) {
                        const $results = this.buildSearchResultsHTML(results);
                        $('.search-input-container').after($results);
                        this.bindSearchResultEvents();
                    }
                },

                buildSearchResultsHTML: function(results) {
                    let html = '<div class="search-results">';

                    results.forEach((result) => {
                        html += `<div class="search-result-item" data-lat="${result.lat}" data-lon="${result.lon}" data-display-name="${result.display_name}">`;
                        html += `<span class="result-address">${result.display_name}</span>`;
                        html += '</div>';
                    });

                    html += '</div>';
                    return $(html);
                },

                bindSearchResultEvents: function() {
                    $('.search-result-item').on('click', this.handleSearchResultSelect.bind(this));
                },

                handleSearchResultSelect: function(e) {
                    const $item = $(e.currentTarget);
                    const lat = parseFloat($item.data('lat'));
                    const lon = parseFloat($item.data('lon'));
                    const displayName = $item.data('display-name');

                    $('#address-search-input').val(displayName);
                    $('.search-results').remove();

                    this.selectedLat = lat;
                    this.selectedLng = lon;

                    $('#location-tooltip').hide();
                    this.openLocationPopupWithCoordinates(lat, lon);
                },

                openLocationPopupWithCoordinates: function(lat, lon) {
                    $('#lwp-location-popup').show();
                    $('#lwp-location-step1').show();
                    $('#lwp-location-step2').hide();

                    this.initMap();
                    this.setMapLocation(lat, lon);
                    this.reverseGeocode(lat, lon);

                    setTimeout(() => {
                        if (this.map) this.map.invalidateSize();
                    }, 0);
                },

                // ========================================
                // UTILITY FUNCTIONS
                // ========================================

                setUserLocation: function(locationId) {
                    const expires = new Date(Date.now() + userLocationCookieExpiryMs).toUTCString();
                    document.cookie = `mulopimfwc_user_location=${locationId};expires=${expires};path=/;samesite=lax`;
                },

                setStoreLocation: function(storeSlug) {
                    const slug = (storeSlug || '').toString().trim();
                    if (!slug) {
                        return;
                    }

                    const settings = window.mulopimfwc_locationWiseProducts || {};
                    const cookieName = settings.cookieName || 'mulopimfwc_store_location';
                    const cookiePath = settings.cookiePath || '/';
                    const sameSite = settings.cookieSameSite ? String(settings.cookieSameSite).toLowerCase() : 'lax';
                    const isSecure = typeof settings.cookieSecure === 'boolean'
                        ? settings.cookieSecure
                        : window.location.protocol === 'https:';
                    const cookieDomain = settings.cookieDomain || '';
                    const days = (settings.cookie_expiry && Number.isFinite(parseInt(settings.cookie_expiry, 10)))
                        ? Math.max(parseInt(settings.cookie_expiry, 10), 1)
                        : 30;
                    const expires = new Date(Date.now() + days * 24 * 60 * 60 * 1000).toUTCString();

                    let cookie = `${cookieName}=${encodeURIComponent(slug)};expires=${expires};path=${cookiePath};samesite=${sameSite}`;
                    if (cookieDomain) {
                        cookie += `;domain=${cookieDomain}`;
                    }
                    if (isSecure) {
                        cookie += ';secure';
                    }
                    document.cookie = cookie;
                },

                getStoreLocation: function() {
                    const settings = window.mulopimfwc_locationWiseProducts || {};
                    const cookieName = settings.cookieName || 'mulopimfwc_store_location';
                    const name = cookieName + '=';
                    const decodedCookie = decodeURIComponent(document.cookie);
                    const ca = decodedCookie.split(';');
                    for (let i = 0; i < ca.length; i++) {
                        let c = ca[i];
                        while (c.charAt(0) === ' ') {
                            c = c.substring(1);
                        }
                        if (c.indexOf(name) === 0) {
                            return c.substring(name.length, c.length);
                        }
                    }
                    return '';
                },

                clearUserLocation: function() {
                    document.cookie = 'mulopimfwc_user_location=;path=/;expires=Thu, 01 Jan 1970 00:00:00 GMT';
                },

                reloadPage: function() {
                    // Reload with cache-busting parameter to bypass cache
                    var url = window.location.href.split('?')[0];
                    var separator = '?';
                    var location = this.getStoreLocation() || this.getUserLocation() || '';
                    if (location) {
                        url += separator + 'mulopimfwc_loc=' + encodeURIComponent(location) + '&_t=' + Date.now();
                    } else {
                        url += separator + '_t=' + Date.now();
                    }
                    window.location.href = url;
                },

                getUserLocation: function() {
                    const name = 'mulopimfwc_user_location=';
                    const decodedCookie = decodeURIComponent(document.cookie);
                    const ca = decodedCookie.split(';');
                    for (let i = 0; i < ca.length; i++) {
                        let c = ca[i];
                        while (c.charAt(0) === ' ') {
                            c = c.substring(1);
                        }
                        if (c.indexOf(name) === 0) {
                            return c.substring(name.length, c.length);
                        }
                    }
                    return '';
                },

                showAlert: function(message) {
                    alert(message);
                },

                injectStyles: function() {
                    if ($('#lwp-search-results-styles').length) return;
                    const css = `
                <style>
                .search-results {
                    margin-top: 10px;
                    border: 1px solid #e9ecef;
                    border-radius: 6px;
                    background: white;
                    max-height: 200px;
                    overflow-y: auto;
                    z-index: 1000;
                    position: relative;
                }
                
                .search-result-item {
                    padding: 12px 16px;
                    cursor: pointer;
                    border-bottom: 1px solid #f1f3f4;
                    transition: background-color 0.2s ease;
                }
                
                .search-result-item:last-child {
                    border-bottom: none;
                }
                
                .search-result-item:hover {
                    background: #f8f9fa;
                }
                
                .result-address {
                    font-size: 14px;
                    color: #495057;
                    display: block;
                    word-wrap: break-word;
                }
                </style>
            `;
                    $('head').append(css);
                }
            };

            // ========================================
            // INITIALIZE THE APPLICATION
            // ========================================

            // Check if we need to initialize location features
            if ($('.lwp-user-location-features').length > 0) {
                LocationFeatures.init();

                // Expose to global scope for debugging (remove in production)
                window.LocationFeatures = LocationFeatures;
            }
        });
    </script>
<?php endif; ?>
