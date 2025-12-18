<?php
if (!defined('ABSPATH')) exit;
$options = $this->get_display_options();
global $MULOPIMFWC_Admin;

$show_title = isset($options['title_show_popup']) && $options['title_show_popup'] === 'on';
$popup_title = $options['mulopimfwc_popup_title'] ?? 'Select Your Location';

$locations_data = [];
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

        $status = ['open' => false, 'next_change' => null];
        if (!empty($MULOPIMFWC_Admin) && method_exists($MULOPIMFWC_Admin, 'is_location_open_now')) {
            $status = $MULOPIMFWC_Admin->is_location_open_now($term_id);
        }

        $status_label = $status['open']
            ? __('Open Now', 'multi-location-product-and-inventory-management')
            : __('Closed', 'multi-location-product-and-inventory-management');

        $next_change_label = '';
        if (!empty($status['next_change']) && $status['next_change'] instanceof DateTimeInterface) {
            $time_label = $status['next_change']->format('g:i A');
            $next_change_label = $status['open']
                ? sprintf(__('Closes at %s', 'multi-location-product-and-inventory-management'), $time_label)
                : sprintf(__('Opens at %s', 'multi-location-product-and-inventory-management'), $time_label);
        }

        $hours_today = '';
        $business_hours = get_term_meta($term_id, 'business_hours', true);
        if (is_array($business_hours) && !empty($business_hours['days'])) {
            $timezone = !empty($business_hours['timezone']) ? $business_hours['timezone'] : wp_timezone_string();
            $now = new DateTimeImmutable('now', new DateTimeZone($timezone));
            $day_keys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
            $today_key = $day_keys[(int) $now->format('N') - 1];
            $today = $business_hours['days'][$today_key] ?? null;

            if (is_array($today)) {
                if (!empty($today['closed'])) {
                    $hours_today = __('Closed today', 'multi-location-product-and-inventory-management');
                } elseif (!empty($today['all_day'])) {
                    $hours_today = __('Open 24 hours', 'multi-location-product-and-inventory-management');
                } else {
                    $open_time = $today['open'] ?? '';
                    $close_time = $today['close'] ?? '';
                    if ($open_time && $close_time) {
                        $open_dt = DateTime::createFromFormat('H:i', $open_time, new DateTimeZone($timezone));
                        $close_dt = DateTime::createFromFormat('H:i', $close_time, new DateTimeZone($timezone));
                        if ($open_dt && $close_dt) {
                            $hours_today = sprintf(
                                __('%s - %s', 'multi-location-product-and-inventory-management'),
                                $open_dt->format('g:i A'),
                                $close_dt->format('g:i A')
                            );
                        }
                    }
                }
            }
        }

        $locations_data[] = [
            'termId' => $term_id,
            'slug' => $location->slug,
            'name' => $location->name,
            'lat' => $lat !== '' ? (float) $lat : null,
            'lng' => $lng !== '' ? (float) $lng : null,
            'address' => [
                'street' => $street,
                'city' => $city,
                'state' => $state,
                'postal' => $postal,
                'country' => $country,
                'line' => trim(implode(', ', array_filter([$street, $city, $state, $postal, $country]))),
            ],
            'logo' => $logo_url,
            'status' => [
                'open' => !empty($status['open']),
                'label' => $status_label,
                'nextChange' => $next_change_label,
            ],
            'hoursToday' => $hours_today,
        ];
    }
}

$popup_data = [
    'variant' => 'simple',
    'cookieExpiryDays' => mulopimfwc_get_location_cookie_expiry_days(),
    'locations' => $locations_data,
    'i18n' => [
        'detecting' => __('Detecting your location...', 'multi-location-product-and-inventory-management'),
        'searching' => __('Searching...', 'multi-location-product-and-inventory-management'),
        'searchFailed' => __('Search failed. Try again.', 'multi-location-product-and-inventory-management'),
        'detectFailed' => __('We could not detect your location. Search for a place instead.', 'multi-location-product-and-inventory-management'),
        'noLocations' => __('No store locations found.', 'multi-location-product-and-inventory-management'),
        'noResults' => __('No matches found. Try a more specific address.', 'multi-location-product-and-inventory-management'),
        'distanceAway' => __('away', 'multi-location-product-and-inventory-management'),
        'distanceUnit' => __('km', 'multi-location-product-and-inventory-management'),
        'addressUnavailable' => __('Address unavailable', 'multi-location-product-and-inventory-management'),
        'hoursToday' => __('Hours today', 'multi-location-product-and-inventory-management'),
        'approximate' => __('Approximate location', 'multi-location-product-and-inventory-management'),
        'nearLabel' => __('Near you', 'multi-location-product-and-inventory-management'),
        'showingNear' => __('Showing stores near your location.', 'multi-location-product-and-inventory-management'),
        'selectStore' => __('Select this store', 'multi-location-product-and-inventory-management'),
    ],
];

$inline_handle = wp_script_is('mulopimfwc-modern-popup', 'enqueued') ? 'mulopimfwc-modern-popup' : 'mulopimfwc_script';
wp_add_inline_script($inline_handle, 'window.mulopimfwcModernPopupData = ' . wp_json_encode($popup_data) . ';', 'before');
?>
<div id="lwp-store-selector-modal" class="lwp-modern-popup lwp-modern-popup--simple" style="display: <?php echo $show_modal ? 'flex' : 'none'; ?>;">
    <div class="lwp-modern-popup__panel">
        <div class="lwp-modern-popup__hero">
            <div class="lwp-modern-popup__icon" aria-hidden="true">
                <svg width="44" height="44" viewBox="0 0 24 24" fill="none">
                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z" fill="currentColor" />
                </svg>
            </div>
            <div class="lwp-modern-popup__heading">
                <?php if ($show_title) { ?>
                    <h2><?php echo esc_html($popup_title); ?></h2>
                <?php } ?>
                <p class="lwp-modern-popup__subtitle"><?php esc_html_e('Choose a nearby store to continue.', 'multi-location-product-and-inventory-management'); ?></p>
            </div>
        </div>

        <div class="lwp-modern-popup__body">
            <div class="lwp-modern-popup__search">
                <label class="lwp-modern-label" for="lwp-modern-location-search"><?php esc_html_e('Your location', 'multi-location-product-and-inventory-management'); ?></label>
                <div class="lwp-modern-search-row">
                    <input type="text" id="lwp-modern-location-search" placeholder="<?php esc_attr_e('Enter city, address, or postal code', 'multi-location-product-and-inventory-management'); ?>" autocomplete="off">
                    <button type="button" id="lwp-modern-search-btn" class="lwp-modern-button"><?php esc_html_e('Search', 'multi-location-product-and-inventory-management'); ?></button>
                </div>
                <div class="lwp-modern-search-actions">
                    <button type="button" id="lwp-modern-detect" class="lwp-modern-ghost-button"><?php esc_html_e('Use my location', 'multi-location-product-and-inventory-management'); ?></button>
                    <span id="lwp-modern-status" class="lwp-modern-status" data-state="idle"></span>
                </div>
                <div id="lwp-modern-suggestions" class="lwp-modern-suggestions" style="display:none;"></div>
            </div>

            <div class="lwp-modern-popup__results">
                <div class="lwp-modern-section-header">
                    <div>
                        <h3><?php esc_html_e('Nearest store', 'multi-location-product-and-inventory-management'); ?></h3>
                        <span id="lwp-modern-origin" class="lwp-modern-origin"></span>
                    </div>
                </div>
                <div id="lwp-modern-featured" class="lwp-modern-featured"></div>

                <div class="lwp-modern-section-header lwp-modern-section-header--compact">
                    <h4><?php esc_html_e('More locations', 'multi-location-product-and-inventory-management'); ?></h4>
                </div>
                <div id="lwp-modern-list" class="lwp-modern-list"></div>
            </div>
        </div>

        <input type="hidden" id="lwp-selected-store" name="mulopimfwc_selected_store" value="">
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
