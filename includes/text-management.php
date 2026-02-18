<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mulopimfwc_get_text_management_fields')) {
    function mulopimfwc_get_text_management_fields(): array
    {
        return [
            'popup_content' => [
                'title' => __('Modal & Popup', 'multi-location-product-and-inventory-management'),
                'description' => __('Text shown in location selector popups and modal layouts.', 'multi-location-product-and-inventory-management'),
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" style="margin-right:6px;vertical-align:middle;background-color:#e0f2fe;padding:10px;border-radius:6px"><path fill="#0284c7" d="M3 4a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H8l-5 4v-4H5a2 2 0 0 1-2-2V4zm4 4h10v2H7V8zm0-3h10v2H7V5zm0 6h6v2H7v-2z"/></svg>',
                'fields' => [
                    [
                        'key' => 'mulopimfwc_popup_title',
                        'label' => __('Popup Title', 'multi-location-product-and-inventory-management'),
                        'default' => __('Select Your Location', 'multi-location-product-and-inventory-management'),
                        'description' => __('Main heading shown in all popup layouts.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                        'pro_only' => true,
                    ],
                    [
                        'key' => 'text_popup_subtitle',
                        'label' => __('Popup Subtitle', 'multi-location-product-and-inventory-management'),
                        'default' => __('Choose a store location to continue shopping with accurate availability.', 'multi-location-product-and-inventory-management'),
                        'description' => __('Subtitle shown across all popup layouts.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'mulopimfwc_popup_placeholder',
                        'label' => __('Dropdown Placeholder', 'multi-location-product-and-inventory-management'),
                        'default' => __('-- Select a Store --', 'multi-location-product-and-inventory-management'),
                        'description' => __('Placeholder for the default popup dropdown.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                        'template_only' => 'default',
                        'pro_only' => true,
                    ],
                    [
                        'key' => 'text_popup_placeholder_subarea',
                        'label' => __('Dropdown Placeholder (Sub-area)', 'multi-location-product-and-inventory-management'),
                        'default' => __('-- Select Sub-area --', 'multi-location-product-and-inventory-management'),
                        'description' => __('Placeholder for deeper hierarchical dropdowns.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'mulopimfwc_popup_btn_txt',
                        'label' => __('Popup Button Text', 'multi-location-product-and-inventory-management'),
                        'default' => __('Select Location', 'multi-location-product-and-inventory-management'),
                        'description' => __('Primary action button text across popup layouts and selection lists.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                        'pro_only' => true,
                    ],
                    [
                        'key' => 'text_popup_label_your_location',
                        'label' => __('Popup Label: Your Location', 'multi-location-product-and-inventory-management'),
                        'default' => __('Your location', 'multi-location-product-and-inventory-management'),
                        'description' => __('Label above the search input in modern layouts.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_popup_placeholder_search',
                        'label' => __('Popup Search Placeholder', 'multi-location-product-and-inventory-management'),
                        'default' => __('Enter city, address, or postal code', 'multi-location-product-and-inventory-management'),
                        'description' => __('Placeholder inside the popup search input across layouts.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_popup_button_search',
                        'label' => __('Popup Search Button', 'multi-location-product-and-inventory-management'),
                        'default' => __('Search', 'multi-location-product-and-inventory-management'),
                        'description' => __('Button label for starting a popup search.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_popup_button_use_my_location',
                        'label' => __('Popup "Use My Location" Button', 'multi-location-product-and-inventory-management'),
                        'default' => __('Use my location', 'multi-location-product-and-inventory-management'),
                        'description' => __('Button label for geolocation lookup.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_popup_heading_nearest_store',
                        'label' => __('Popup Heading: Nearest Store', 'multi-location-product-and-inventory-management'),
                        'default' => __('Nearest store', 'multi-location-product-and-inventory-management'),
                        'description' => __('Heading above the nearest store section.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_popup_heading_more_locations',
                        'label' => __('Popup Heading: More Locations', 'multi-location-product-and-inventory-management'),
                        'default' => __('More locations', 'multi-location-product-and-inventory-management'),
                        'description' => __('Heading above the additional locations list.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_popup_label_search_locations',
                        'label' => __('Popup Label: Search Locations', 'multi-location-product-and-inventory-management'),
                        'default' => __('Search locations', 'multi-location-product-and-inventory-management'),
                        'description' => __('Label above the classic popup search input.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                ],
            ],
            'popup_messages' => [
                'title' => __('Popup System Messages', 'multi-location-product-and-inventory-management'),
                'description' => __('Status text and helper messages shown inside popup layouts.', 'multi-location-product-and-inventory-management'),
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" style="margin-right:6px;vertical-align:middle;background-color:#fef3c7;padding:10px;border-radius:6px"><path fill="#d97706" d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm1 15h-2v-2h2zm0-4h-2V7h2z"/></svg>',
                'fields' => [
                    [
                        'key' => 'text_popup_msg_detecting',
                        'label' => __('Detecting Location', 'multi-location-product-and-inventory-management'),
                        'default' => __('Detecting your location...', 'multi-location-product-and-inventory-management'),
                        'description' => __('Shown while the popup is trying to detect the customer location.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_popup_msg_searching',
                        'label' => __('Searching', 'multi-location-product-and-inventory-management'),
                        'default' => __('Searching...', 'multi-location-product-and-inventory-management'),
                        'description' => __('Shown while searching for locations.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_popup_msg_search_failed',
                        'label' => __('Search Failed Message', 'multi-location-product-and-inventory-management'),
                        'default' => __('Search failed. Try again.', 'multi-location-product-and-inventory-management'),
                        'description' => __('Shown when a popup search request fails.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_popup_msg_detect_failed',
                        'label' => __('Location Detect Failed', 'multi-location-product-and-inventory-management'),
                        'default' => __('We could not detect your location. Please search for a store.', 'multi-location-product-and-inventory-management'),
                        'description' => __('Shown when geolocation fails in popup layouts.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_popup_msg_no_locations',
                        'label' => __('No Locations Found', 'multi-location-product-and-inventory-management'),
                        'default' => __('No store locations found.', 'multi-location-product-and-inventory-management'),
                        'description' => __('Shown when there are no locations to display.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_popup_msg_no_results',
                        'label' => __('No Search Matches', 'multi-location-product-and-inventory-management'),
                        'default' => __('No locations match your search.', 'multi-location-product-and-inventory-management'),
                        'description' => __('Shown when a popup search returns no results.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_popup_msg_distance_from_you',
                        'label' => __('Distance Header', 'multi-location-product-and-inventory-management'),
                        'default' => __('Distances from your location', 'multi-location-product-and-inventory-management'),
                        'description' => __('Header shown before listing distances in the classic popup.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_popup_msg_distance_approximate',
                        'label' => __('Approximate Distance Label', 'multi-location-product-and-inventory-management'),
                        'default' => __('Approximate distances', 'multi-location-product-and-inventory-management'),
                        'description' => __('Label used when distance data is approximate.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_popup_msg_distance_away',
                        'label' => __('Distance Suffix', 'multi-location-product-and-inventory-management'),
                        'default' => __('away', 'multi-location-product-and-inventory-management'),
                        'description' => __('Suffix shown after distance values.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_popup_msg_distance_unit',
                        'label' => __('Distance Unit', 'multi-location-product-and-inventory-management'),
                        'default' => __('km', 'multi-location-product-and-inventory-management'),
                        'description' => __('Unit label for distance values.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_popup_msg_address_unavailable',
                        'label' => __('Address Unavailable', 'multi-location-product-and-inventory-management'),
                        'default' => __('Address unavailable', 'multi-location-product-and-inventory-management'),
                        'description' => __('Shown when a location address is missing.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_popup_msg_hours_today',
                        'label' => __('Hours Today Label', 'multi-location-product-and-inventory-management'),
                        'default' => __('Hours today', 'multi-location-product-and-inventory-management'),
                        'description' => __('Label for today\'s hours in popup cards.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_popup_msg_approximate_location',
                        'label' => __('Approximate Location', 'multi-location-product-and-inventory-management'),
                        'default' => __('Approximate location', 'multi-location-product-and-inventory-management'),
                        'description' => __('Shown when location results are approximate.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_popup_msg_near_you',
                        'label' => __('Near You Label', 'multi-location-product-and-inventory-management'),
                        'default' => __('Near you', 'multi-location-product-and-inventory-management'),
                        'description' => __('Label for nearby location results.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_popup_msg_showing_near',
                        'label' => __('Showing Near Message', 'multi-location-product-and-inventory-management'),
                        'default' => __('Showing stores near your location.', 'multi-location-product-and-inventory-management'),
                        'description' => __('Helper message when showing nearby stores.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_popup_msg_layouts_unavailable',
                        'label' => __('Layouts Unavailable Message', 'multi-location-product-and-inventory-management'),
                        'default' => __('Location layouts are unavailable.', 'multi-location-product-and-inventory-management'),
                        'description' => __('Shown when the popup layout renderer is unavailable.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                ],
            ],
            'product_selector' => [
                'title' => __('Product Page Location Texts', 'multi-location-product-and-inventory-management'),
                'description' => __('Texts used on the single product page for the location selector and related messages.', 'multi-location-product-and-inventory-management'),
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" style="margin-right:6px;vertical-align:middle;background-color:#ecfccb;padding:10px;border-radius:6px"><path fill="#65a30d" d="M4 4h16v2H4V4zm0 5h16v2H4V9zm0 5h10v2H4v-2z"/></svg>',
                'fields' => [
                    [
                        'key' => 'text_selector_label',
                        'label' => __('Selector Label', 'multi-location-product-and-inventory-management'),
                        'default' => __('Select Location:', 'multi-location-product-and-inventory-management'),
                        'description' => __('Label shown above the location selector on product pages and shortcodes.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_selector_placeholder',
                        'label' => __('Selector Placeholder', 'multi-location-product-and-inventory-management'),
                        'default' => __('Choose a location...', 'multi-location-product-and-inventory-management'),
                        'description' => __('Placeholder text for the selector dropdown layout.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_selector_changed_to',
                        'label' => __('Location Changed Message', 'multi-location-product-and-inventory-management'),
                        'default' => __('Location changed to: %s', 'multi-location-product-and-inventory-management'),
                        'description' => __('Success message after changing location via AJAX.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_selector_all_products',
                        'label' => __('All Products Label', 'multi-location-product-and-inventory-management'),
                        'default' => __('All Products', 'multi-location-product-and-inventory-management'),
                        'description' => __('Label used when the selector is set to show all products.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_selector_error_security',
                        'label' => __('Security Error Message', 'multi-location-product-and-inventory-management'),
                        'default' => __('Security check failed', 'multi-location-product-and-inventory-management'),
                        'description' => __('Error shown when the selector security check fails.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_selector_error_invalid',
                        'label' => __('Invalid Location Message', 'multi-location-product-and-inventory-management'),
                        'default' => __('Invalid location', 'multi-location-product-and-inventory-management'),
                        'description' => __('Error shown when the selected location is invalid.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_selector_error_not_found',
                        'label' => __('Location Not Found Message', 'multi-location-product-and-inventory-management'),
                        'default' => __('Location not found', 'multi-location-product-and-inventory-management'),
                        'description' => __('Error shown when a location slug does not exist.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                ],
            ],
            'shortcode_user_locations' => [
                'title' => __('Shortcode User Location Labels', 'multi-location-product-and-inventory-management'),
                'description' => __('Texts used by the [mulopimfwc_store_location_selector] user address popup and saved-address flow.', 'multi-location-product-and-inventory-management'),
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" style="margin-right:6px;vertical-align:middle;background-color:#dcfce7;padding:10px;border-radius:6px"><path fill="#16a34a" d="M12 2a8 8 0 0 1 8 8c0 5.12-6.4 11.19-7.13 11.87a1.25 1.25 0 0 1-1.74 0C10.4 21.19 4 15.12 4 10a8 8 0 0 1 8-8zm0 4.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/></svg>',
                'fields' => [
                    [
                        'key' => 'text_shortcode_enter_your_address',
                        'label' => __('Enter Your Address', 'multi-location-product-and-inventory-management'),
                        'default' => __('Enter Your Address', 'multi-location-product-and-inventory-management'),
                        'description' => __('Shown as the address input label and placeholder in the shortcode tooltip.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_shortcode_saved_address',
                        'label' => __('Saved Address', 'multi-location-product-and-inventory-management'),
                        'default' => __('Saved Address', 'multi-location-product-and-inventory-management'),
                        'description' => __('Heading above the saved address list.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_shortcode_add_new_address',
                        'label' => __('Add New Address', 'multi-location-product-and-inventory-management'),
                        'default' => __('Add New Address', 'multi-location-product-and-inventory-management'),
                        'description' => __('Button text used to open the add-address flow.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_shortcode_set_your_location',
                        'label' => __('Set Your Location', 'multi-location-product-and-inventory-management'),
                        'default' => __('Set Your Location', 'multi-location-product-and-inventory-management'),
                        'description' => __('Popup title and fallback text when no address is selected.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_shortcode_continue',
                        'label' => __('Continue', 'multi-location-product-and-inventory-management'),
                        'default' => __('Continue', 'multi-location-product-and-inventory-management'),
                        'description' => __('Button text for moving from map step to details step.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_shortcode_location_details',
                        'label' => __('Location Details', 'multi-location-product-and-inventory-management'),
                        'default' => __('Location Details', 'multi-location-product-and-inventory-management'),
                        'description' => __('Heading for the saved location details form.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_shortcode_label',
                        'label' => __('Label', 'multi-location-product-and-inventory-management'),
                        'default' => __('Label', 'multi-location-product-and-inventory-management'),
                        'description' => __('Field label for selecting a saved address type.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_shortcode_home',
                        'label' => __('Home', 'multi-location-product-and-inventory-management'),
                        'default' => __('Home', 'multi-location-product-and-inventory-management'),
                        'description' => __('Address type option for Home.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_shortcode_work',
                        'label' => __('Work', 'multi-location-product-and-inventory-management'),
                        'default' => __('Work', 'multi-location-product-and-inventory-management'),
                        'description' => __('Address type option for Work.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_shortcode_partner',
                        'label' => __('Partner', 'multi-location-product-and-inventory-management'),
                        'default' => __('Partner', 'multi-location-product-and-inventory-management'),
                        'description' => __('Address type option for Partner.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_shortcode_other',
                        'label' => __('Other', 'multi-location-product-and-inventory-management'),
                        'default' => __('Other', 'multi-location-product-and-inventory-management'),
                        'description' => __('Address type option for Other.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_shortcode_street_address',
                        'label' => __('Street Address', 'multi-location-product-and-inventory-management'),
                        'default' => __('Street Address', 'multi-location-product-and-inventory-management'),
                        'description' => __('Field label for the primary street address.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_shortcode_apartment_suite',
                        'label' => __('Apartment, Suite, Etc.', 'multi-location-product-and-inventory-management'),
                        'default' => __('Apartment, suite, etc.', 'multi-location-product-and-inventory-management'),
                        'description' => __('Field label for apartment, suite, or secondary address details.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_shortcode_note',
                        'label' => __('Note', 'multi-location-product-and-inventory-management'),
                        'default' => __('Note', 'multi-location-product-and-inventory-management'),
                        'description' => __('Field label for optional delivery or location notes.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_shortcode_back',
                        'label' => __('Back', 'multi-location-product-and-inventory-management'),
                        'default' => __('Back', 'multi-location-product-and-inventory-management'),
                        'description' => __('Button label for returning to the map step.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_shortcode_save_location',
                        'label' => __('Save Location', 'multi-location-product-and-inventory-management'),
                        'default' => __('Save Location', 'multi-location-product-and-inventory-management'),
                        'description' => __('Submit button text for saving a user location.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                ],
            ],
            'location_info' => [
                'title' => __('Location Information', 'multi-location-product-and-inventory-management'),
                'description' => __('Headings and labels used in location information displays.', 'multi-location-product-and-inventory-management'),
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" style="margin-right:6px;vertical-align:middle;background-color:#fee2e2;padding:10px;border-radius:6px"><path fill="#ef4444" d="M12 2a6 6 0 0 0-6 6c0 4.4 6 12 6 12s6-7.6 6-12a6 6 0 0 0-6-6zm0 8a2 2 0 1 1 0-4 2 2 0 0 1 0 4z"/></svg>',
                'fields' => [
                    [
                        'key' => 'text_location_info_tab_title',
                        'label' => __('Location Info Tab Title', 'multi-location-product-and-inventory-management'),
                        'default' => __('Location Information', 'multi-location-product-and-inventory-management'),
                        'description' => __('Title used for the product tab that shows location information.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_location_contact_heading',
                        'label' => __('Contact Information Heading', 'multi-location-product-and-inventory-management'),
                        'default' => __('Contact Information', 'multi-location-product-and-inventory-management'),
                        'description' => __('Heading above contact details on location pages.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_location_label_address',
                        'label' => __('Address Label', 'multi-location-product-and-inventory-management'),
                        'default' => __('Address', 'multi-location-product-and-inventory-management'),
                        'description' => __('Label for location addresses.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_location_label_phone',
                        'label' => __('Phone Label', 'multi-location-product-and-inventory-management'),
                        'default' => __('Phone', 'multi-location-product-and-inventory-management'),
                        'description' => __('Label for location phone numbers.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_location_label_email',
                        'label' => __('Email Label', 'multi-location-product-and-inventory-management'),
                        'default' => __('Email', 'multi-location-product-and-inventory-management'),
                        'description' => __('Label for location email addresses.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_location_button_directions',
                        'label' => __('Get Directions Button', 'multi-location-product-and-inventory-management'),
                        'default' => __('Get Directions', 'multi-location-product-and-inventory-management'),
                        'description' => __('Button label for map directions.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_location_heading_map',
                        'label' => __('Location Map Heading', 'multi-location-product-and-inventory-management'),
                        'default' => __('Location Map', 'multi-location-product-and-inventory-management'),
                        'description' => __('Heading above the location map.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_location_heading_gallery',
                        'label' => __('Gallery Heading', 'multi-location-product-and-inventory-management'),
                        'default' => __('Gallery', 'multi-location-product-and-inventory-management'),
                        'description' => __('Heading above the location gallery.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_location_heading_hours',
                        'label' => __('Business Hours Heading', 'multi-location-product-and-inventory-management'),
                        'default' => __('Business Hours', 'multi-location-product-and-inventory-management'),
                        'description' => __('Heading above business hours displays.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_location_heading_multi',
                        'label' => __('Multiple Locations Heading', 'multi-location-product-and-inventory-management'),
                        'default' => __('Available at Multiple Locations', 'multi-location-product-and-inventory-management'),
                        'description' => __('Heading for items available at multiple locations.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_location_button_details',
                        'label' => __('View Details Button', 'multi-location-product-and-inventory-management'),
                        'default' => __('View Details', 'multi-location-product-and-inventory-management'),
                        'description' => __('Button label for viewing location details.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_location_shortcode_heading',
                        'label' => __('Locations Count Title', 'multi-location-product-and-inventory-management'),
                        'default' => __('Our Locations (%d)', 'multi-location-product-and-inventory-management'),
                        'description' => __('Title used for location lists with a count placeholder.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_location_shortcode_search',
                        'label' => __('Location Search Placeholder', 'multi-location-product-and-inventory-management'),
                        'default' => __('Search locations...', 'multi-location-product-and-inventory-management'),
                        'description' => __('Placeholder for the location search input.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_location_shortcode_no_results',
                        'label' => __('No Matching Locations', 'multi-location-product-and-inventory-management'),
                        'default' => __('No locations found matching your search.', 'multi-location-product-and-inventory-management'),
                        'description' => __('Message shown when a location search has no matches.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_location_shortcode_no_locations',
                        'label' => __('No Locations Message', 'multi-location-product-and-inventory-management'),
                        'default' => __('No locations found.', 'multi-location-product-and-inventory-management'),
                        'description' => __('Message shown when there are no locations to list.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                ],
            ],
            'product_filter' => [
                'title' => __('Product Filter', 'multi-location-product-and-inventory-management'),
                'description' => __('Labels and messages used by the frontend product filter.', 'multi-location-product-and-inventory-management'),
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" style="margin-right:6px;vertical-align:middle;background-color:#ede9fe;padding:10px;border-radius:6px"><path fill="#7c3aed" d="M4 5h16v2H4V5zm3 6h10v2H7v-2zm3 6h4v2h-4v-2z"/></svg>',
                'fields' => [
                    [
                        'key' => 'text_filter_location_label',
                        'label' => __('Filter Label: Location', 'multi-location-product-and-inventory-management'),
                        'default' => __('Location', 'multi-location-product-and-inventory-management'),
                        'description' => __('Label for the location filter.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_filter_stock_label',
                        'label' => __('Filter Label: Stock Status', 'multi-location-product-and-inventory-management'),
                        'default' => __('Stock Status', 'multi-location-product-and-inventory-management'),
                        'description' => __('Label for the stock status filter.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_filter_all_locations',
                        'label' => __('Filter Option: All Locations', 'multi-location-product-and-inventory-management'),
                        'default' => __('All Locations', 'multi-location-product-and-inventory-management'),
                        'description' => __('Option label for showing all locations.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_filter_all_products',
                        'label' => __('Filter Option: All Products', 'multi-location-product-and-inventory-management'),
                        'default' => __('All Products', 'multi-location-product-and-inventory-management'),
                        'description' => __('Option label for showing all products.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_filter_in_stock',
                        'label' => __('Filter Option: In Stock', 'multi-location-product-and-inventory-management'),
                        'default' => __('In Stock', 'multi-location-product-and-inventory-management'),
                        'description' => __('Option label for in-stock products.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_filter_out_stock',
                        'label' => __('Filter Option: Out of Stock', 'multi-location-product-and-inventory-management'),
                        'default' => __('Out of Stock', 'multi-location-product-and-inventory-management'),
                        'description' => __('Option label for out-of-stock products.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_filter_button',
                        'label' => __('Filter Button', 'multi-location-product-and-inventory-management'),
                        'default' => __('Filter', 'multi-location-product-and-inventory-management'),
                        'description' => __('Button label to apply filters.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_filter_clear',
                        'label' => __('Clear Button', 'multi-location-product-and-inventory-management'),
                        'default' => __('Clear', 'multi-location-product-and-inventory-management'),
                        'description' => __('Button label to clear filters.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_filter_msg_loading',
                        'label' => __('Filter Loading Message', 'multi-location-product-and-inventory-management'),
                        'default' => __('Loading products...', 'multi-location-product-and-inventory-management'),
                        'description' => __('Shown while filter results are loading.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_filter_msg_no_results',
                        'label' => __('Filter No Results Message', 'multi-location-product-and-inventory-management'),
                        'default' => __('No products found.', 'multi-location-product-and-inventory-management'),
                        'description' => __('Shown when the filter returns no products.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_filter_msg_error',
                        'label' => __('Filter Error Message', 'multi-location-product-and-inventory-management'),
                        'default' => __('An error occurred. Please try again.', 'multi-location-product-and-inventory-management'),
                        'description' => __('Shown when the filter encounters an error.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                ],
            ],
            'cart_checkout' => [
                'title' => __('Cart & Checkout', 'multi-location-product-and-inventory-management'),
                'description' => __('Text used during cart and checkout location changes.', 'multi-location-product-and-inventory-management'),
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" style="margin-right:6px;vertical-align:middle;background-color:#ffedd5;padding:10px;border-radius:6px"><path fill="#ea580c" d="M7 4h-2l-1 2H2v2h2l3.6 7.59-1.35 2.45A1 1 0 0 0 7.1 20H19v-2H7.42a.25.25 0 0 1-.22-.37l.94-1.72H16a1 1 0 0 0 .92-.62L21 6H6.42l-.7-2z"/></svg>',
                'fields' => [
                    [
                        'key' => 'location_notification_text',
                        'label' => __('Location Change Confirmation', 'multi-location-product-and-inventory-management'),
                        'default' => __('Do you want to change the store location? Your cart will be updated.', 'multi-location-product-and-inventory-management'),
                        'description' => __('Confirmation shown before updating the cart when a location changes.', 'multi-location-product-and-inventory-management'),
                        'type' => 'textarea',
                        'rows' => 2,
                        'manual_disable' => true,
                        'pro_only' => true,
                    ],
                    [
                        'key' => 'text_cart_change_location_label',
                        'label' => __('Change Location Label', 'multi-location-product-and-inventory-management'),
                        'default' => __('Change Location:', 'multi-location-product-and-inventory-management'),
                        'description' => __('Label above the cart item location selector.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_cart_updating',
                        'label' => __('Updating Text', 'multi-location-product-and-inventory-management'),
                        'default' => __('Updating...', 'multi-location-product-and-inventory-management'),
                        'description' => __('Shown while a cart item location is updating.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_cart_update_error',
                        'label' => __('Cart Update Error', 'multi-location-product-and-inventory-management'),
                        'default' => __('Error updating location. Please try again.', 'multi-location-product-and-inventory-management'),
                        'description' => __('Shown when a cart location update fails.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_cart_clear_error',
                        'label' => __('Clear Cart Error', 'multi-location-product-and-inventory-management'),
                        'default' => __('Failed to clear the cart. Please try again.', 'multi-location-product-and-inventory-management'),
                        'description' => __('Shown when the cart cannot be cleared during a location change.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_cart_removed_items',
                        'label' => __('Removed Items Notice', 'multi-location-product-and-inventory-management'),
                        'default' => __('The following items were removed because they are not available at the selected location: %s', 'multi-location-product-and-inventory-management'),
                        'description' => __('Shown after a location switch removes unavailable items. Use %s for item names.', 'multi-location-product-and-inventory-management'),
                        'type' => 'textarea',
                        'rows' => 2,
                    ],
                    [
                        'key' => 'text_cart_unable_change',
                        'label' => __('Unable to Change Location', 'multi-location-product-and-inventory-management'),
                        'default' => __('Unable to change location. Please try again.', 'multi-location-product-and-inventory-management'),
                        'description' => __('Shown when an AJAX location change fails.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_cart_unable_change_now',
                        'label' => __('Unable to Change Location (Fallback)', 'multi-location-product-and-inventory-management'),
                        'default' => __('Unable to change location right now. Please try again.', 'multi-location-product-and-inventory-management'),
                        'description' => __('Shown when an AJAX location change fails and cart cleanup is required.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                ],
            ],
            'reviews' => [
                'title' => __('Reviews', 'multi-location-product-and-inventory-management'),
                'description' => __('Text used in location-based review blocks.', 'multi-location-product-and-inventory-management'),
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" style="margin-right:6px;vertical-align:middle;background-color:#fee2e2;padding:10px;border-radius:6px"><path fill="#dc2626" d="M12 17.3l-6.18 3.25 1.18-6.88L2 8.76l6.91-1L12 1.5l3.09 6.26 6.91 1-4.99 4.91 1.18 6.88L12 17.3z"/></svg>',
                'fields' => [
                    [
                        'key' => 'text_reviews_heading',
                        'label' => __('Reviews Heading', 'multi-location-product-and-inventory-management'),
                        'default' => __('Reviews from your neighbours', 'multi-location-product-and-inventory-management'),
                        'description' => __('Heading above the reviews block.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_reviews_recent',
                        'label' => __('Recent Reviews Label', 'multi-location-product-and-inventory-management'),
                        'default' => __('Showing recent reviews from %s', 'multi-location-product-and-inventory-management'),
                        'description' => __('Label shown before the location name. Use %s for the location.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_reviews_label',
                        'label' => __('Review Location Label', 'multi-location-product-and-inventory-management'),
                        'default' => __('Reviewed from: %s', 'multi-location-product-and-inventory-management'),
                        'description' => __('Label shown with each review location. Use %s for the location.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                ],
            ],
            'business_hours' => [
                'title' => __('Business Hours & Status', 'multi-location-product-and-inventory-management'),
                'description' => __('Status labels and hour formatting used across location displays.', 'multi-location-product-and-inventory-management'),
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" style="margin-right:6px;vertical-align:middle;background-color:#d1fae5;padding:10px;border-radius:6px"><path fill="#059669" d="M12 1a11 11 0 1 0 11 11A11 11 0 0 0 12 1zm1 6h-2v5.17l3.71 3.7 1.42-1.41L13 11.17z"/></svg>',
                'fields' => [
                    [
                        'key' => 'text_status_open_now',
                        'label' => __('Open Now', 'multi-location-product-and-inventory-management'),
                        'default' => __('Open Now', 'multi-location-product-and-inventory-management'),
                        'description' => __('Status label for locations currently open.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_status_closed',
                        'label' => __('Closed', 'multi-location-product-and-inventory-management'),
                        'default' => __('Closed', 'multi-location-product-and-inventory-management'),
                        'description' => __('Status label for closed locations.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_hours_closed_today',
                        'label' => __('Closed Today', 'multi-location-product-and-inventory-management'),
                        'default' => __('Closed today', 'multi-location-product-and-inventory-management'),
                        'description' => __('Shown when a location is closed for the day.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_hours_open_24',
                        'label' => __('Open 24 Hours', 'multi-location-product-and-inventory-management'),
                        'default' => __('Open 24 hours', 'multi-location-product-and-inventory-management'),
                        'description' => __('Shown when a location is open all day.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_hours_closes_at',
                        'label' => __('Closes At', 'multi-location-product-and-inventory-management'),
                        'default' => __('Closes at %s', 'multi-location-product-and-inventory-management'),
                        'description' => __('Shown when a location will close. Use %s for time.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_hours_opens_at',
                        'label' => __('Opens At', 'multi-location-product-and-inventory-management'),
                        'default' => __('Opens at %s', 'multi-location-product-and-inventory-management'),
                        'description' => __('Shown when a location will open. Use %s for time.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_hours_time_range',
                        'label' => __('Time Range Format', 'multi-location-product-and-inventory-management'),
                        'default' => __('%s - %s', 'multi-location-product-and-inventory-management'),
                        'description' => __('Format for opening and closing times. Use %s placeholders.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_hours_today_label',
                        'label' => __('Today Label', 'multi-location-product-and-inventory-management'),
                        'default' => __('Today', 'multi-location-product-and-inventory-management'),
                        'description' => __('Label for the current day in hours tables.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_hours_day_mon',
                        'label' => __('Monday', 'multi-location-product-and-inventory-management'),
                        'default' => __('Monday', 'multi-location-product-and-inventory-management'),
                        'description' => __('Day label for Monday.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_hours_day_tue',
                        'label' => __('Tuesday', 'multi-location-product-and-inventory-management'),
                        'default' => __('Tuesday', 'multi-location-product-and-inventory-management'),
                        'description' => __('Day label for Tuesday.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_hours_day_wed',
                        'label' => __('Wednesday', 'multi-location-product-and-inventory-management'),
                        'default' => __('Wednesday', 'multi-location-product-and-inventory-management'),
                        'description' => __('Day label for Wednesday.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_hours_day_thu',
                        'label' => __('Thursday', 'multi-location-product-and-inventory-management'),
                        'default' => __('Thursday', 'multi-location-product-and-inventory-management'),
                        'description' => __('Day label for Thursday.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_hours_day_fri',
                        'label' => __('Friday', 'multi-location-product-and-inventory-management'),
                        'default' => __('Friday', 'multi-location-product-and-inventory-management'),
                        'description' => __('Day label for Friday.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_hours_day_sat',
                        'label' => __('Saturday', 'multi-location-product-and-inventory-management'),
                        'default' => __('Saturday', 'multi-location-product-and-inventory-management'),
                        'description' => __('Day label for Saturday.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_hours_day_sun',
                        'label' => __('Sunday', 'multi-location-product-and-inventory-management'),
                        'default' => __('Sunday', 'multi-location-product-and-inventory-management'),
                        'description' => __('Day label for Sunday.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                ],
            ],
            'recommendations' => [
                'title' => __('Recommendations', 'multi-location-product-and-inventory-management'),
                'description' => __('Text used by location-based recommendations.', 'multi-location-product-and-inventory-management'),
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" style="margin-right:6px;vertical-align:middle;background-color:#cffafe;padding:10px;border-radius:6px"><path fill="#0891b2" d="M12 2l2.5 6H21l-5 3.5 2 6L12 14l-6 3.5 2-6L3 8h6.5L12 2z"/></svg>',
                'fields' => [
                    [
                        'key' => 'text_recommendations_loading',
                        'label' => __('Loading Recommendations', 'multi-location-product-and-inventory-management'),
                        'default' => __('Loading recommendations...', 'multi-location-product-and-inventory-management'),
                        'description' => __('Shown while recommendations are loading.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_recommendations_none',
                        'label' => __('No Recommendations Message', 'multi-location-product-and-inventory-management'),
                        'default' => __('No recommendations available yet for this location.', 'multi-location-product-and-inventory-management'),
                        'description' => __('Shown when there are no recommendations for a location.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_recommendations_error',
                        'label' => __('Recommendations Error', 'multi-location-product-and-inventory-management'),
                        'default' => __('An error occurred while loading recommendations. Please try again.', 'multi-location-product-and-inventory-management'),
                        'description' => __('Shown when recommendation loading fails.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_recommendations_select_location',
                        'label' => __('Select Location Prompt', 'multi-location-product-and-inventory-management'),
                        'default' => __('Please select a location to see recommendations.', 'multi-location-product-and-inventory-management'),
                        'description' => __('Shown when no location is selected.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_recommendations_added',
                        'label' => __('Added to Cart Message', 'multi-location-product-and-inventory-management'),
                        'default' => __('Added to cart!', 'multi-location-product-and-inventory-management'),
                        'description' => __('Shown after adding a recommendation to the cart.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_recommendations_title',
                        'label' => __('Recommendations Title', 'multi-location-product-and-inventory-management'),
                        'default' => __('Popular at %s', 'multi-location-product-and-inventory-management'),
                        'description' => __('Default title for recommendations. Use %s for the location.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_recommendations_badge',
                        'label' => __('Recommendations Badge Label', 'multi-location-product-and-inventory-management'),
                        'default' => __('Popular', 'multi-location-product-and-inventory-management'),
                        'description' => __('Badge label shown on recommended products.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_recommendations_badge_title',
                        'label' => __('Recommendations Badge Tooltip', 'multi-location-product-and-inventory-management'),
                        'default' => __('Views: %d | Purchases: %d', 'multi-location-product-and-inventory-management'),
                        'description' => __('Tooltip shown on recommendation badges. Use %d for views and purchases.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                ],
            ],
            'alerts_messages' => [
                'title' => __('Alerts & Messages', 'multi-location-product-and-inventory-management'),
                'description' => __('General alert and status text used across the frontend.', 'multi-location-product-and-inventory-management'),
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" style="margin-right:6px;vertical-align:middle;background-color:#ffe4e6;padding:10px;border-radius:6px"><path fill="#e11d48" d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm1 13h-2v-2h2zm0-4h-2V7h2z"/></svg>',
                'fields' => [
                    [
                        'key' => 'text_alert_select_store',
                        'label' => __('Select Store Alert', 'multi-location-product-and-inventory-management'),
                        'default' => __('Please select a store.', 'multi-location-product-and-inventory-management'),
                        'description' => __('Alert shown when the popup submit is clicked without a selection.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_alert_select_store_location',
                        'label' => __('Select Store Location Alert', 'multi-location-product-and-inventory-management'),
                        'default' => __('Please select a store location.', 'multi-location-product-and-inventory-management'),
                        'description' => __('Alert shown when the shortcode selector is submitted without a location.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_alert_location_required',
                        'label' => __('Location Required Prompt', 'multi-location-product-and-inventory-management'),
                        'default' => __('Please select a store location before adding this product to your cart.', 'multi-location-product-and-inventory-management'),
                        'description' => __('Prompt shown when a location is required to add to cart.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_alert_out_of_stock_badge',
                        'label' => __('Out of Stock Badge Text', 'multi-location-product-and-inventory-management'),
                        'default' => __('Out of Stock', 'multi-location-product-and-inventory-management'),
                        'description' => __('Badge text for out-of-stock products.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                ],
            ],
            'location_hours_restriction' => [
                'title' => __('Location Hours Restriction', 'multi-location-product-and-inventory-management'),
                'description' => __('Messages shown when purchases are restricted by business hours.', 'multi-location-product-and-inventory-management'),
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" style="margin-right:6px;vertical-align:middle;background-color:#f1f5f9;padding:10px;border-radius:6px"><path fill="#475569" d="M12 1a11 11 0 1 0 11 11A11 11 0 0 0 12 1zm1 6h-2v5.17l3.71 3.7 1.42-1.41L13 11.17z"/></svg>',
                'fields' => [
                    [
                        'key' => 'text_restriction_closed_notice',
                        'label' => __('Closed Notice (Blocked)', 'multi-location-product-and-inventory-management'),
                        'default' => __('%s is currently closed. Products cannot be purchased at this time.', 'multi-location-product-and-inventory-management'),
                        'description' => __('Shown when checkout is blocked during closed hours. Use %s for location name.', 'multi-location-product-and-inventory-management'),
                        'type' => 'textarea',
                        'rows' => 2,
                    ],
                    [
                        'key' => 'text_restriction_opens_at',
                        'label' => __('Opens At Note', 'multi-location-product-and-inventory-management'),
                        'default' => __('Opens at %s.', 'multi-location-product-and-inventory-management'),
                        'description' => __('Supplemental note that shows opening time. Use %s for time.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_restriction_closed_browse',
                        'label' => __('Closed Notice (Browse Only)', 'multi-location-product-and-inventory-management'),
                        'default' => __('%s is currently closed. You can browse products but cannot make purchases at this time.', 'multi-location-product-and-inventory-management'),
                        'description' => __('Shown when browsing is allowed but purchasing is blocked. Use %s for location name.', 'multi-location-product-and-inventory-management'),
                        'type' => 'textarea',
                        'rows' => 2,
                    ],
                    [
                        'key' => 'text_restriction_we_open_at',
                        'label' => __('We Open At Note', 'multi-location-product-and-inventory-management'),
                        'default' => __('We open at %s.', 'multi-location-product-and-inventory-management'),
                        'description' => __('Shown after the browse-only notice. Use %s for time.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_restriction_cannot_add',
                        'label' => __('Cannot Add to Cart Message', 'multi-location-product-and-inventory-management'),
                        'default' => __('Cannot add to cart. %s is currently closed.', 'multi-location-product-and-inventory-management'),
                        'description' => __('Shown when a product cannot be added because the location is closed. Use %s for location name.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_restriction_title',
                        'label' => __('Restriction Title', 'multi-location-product-and-inventory-management'),
                        'default' => __('Location Closed', 'multi-location-product-and-inventory-management'),
                        'description' => __('Title used for restriction notices.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                ],
            ],
            'variation_info' => [
                'title' => __('Location Based Product Info (Single & Archive Pages)', 'multi-location-product-and-inventory-management'),
                'description' => __('Labels used in the variation-specific location info block displayed on single product and archive pages when Location Information is enabled in Settings → Inventory.', 'multi-location-product-and-inventory-management'),
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" style="margin-right:6px;vertical-align:middle;background-color:#e0f2fe;padding:10px;border-radius:6px"><path fill="#0ea5e9" d="M5 4h14a2 2 0 0 1 2 2v3H3V6a2 2 0 0 1 2-2zm-2 7h18v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7zm5 2h4v2H8v-2z"/></svg>',
                'fields' => [
                    [
                        'key' => 'text_variation_info_heading',
                        'label' => __('Variation Info Heading', 'multi-location-product-and-inventory-management'),
                        'default' => __('Information for %s', 'multi-location-product-and-inventory-management'),
                        'description' => __('Heading shown above variation location details. Use %s for location.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_variation_stock_label',
                        'label' => __('Variation Stock Label', 'multi-location-product-and-inventory-management'),
                        'default' => __('Stock', 'multi-location-product-and-inventory-management'),
                        'description' => __('Label used before variation stock details.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_variation_in_stock',
                        'label' => __('Variation In Stock Label', 'multi-location-product-and-inventory-management'),
                        'default' => __('%s in stock', 'multi-location-product-and-inventory-management'),
                        'description' => __('Used with the stock quantity. Use %s for quantity.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_variation_backorder',
                        'label' => __('Variation Backorder Label', 'multi-location-product-and-inventory-management'),
                        'default' => __('Available on backorder', 'multi-location-product-and-inventory-management'),
                        'description' => __('Shown when a variation is available on backorder.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                    [
                        'key' => 'text_variation_price_label',
                        'label' => __('Variation Price Label', 'multi-location-product-and-inventory-management'),
                        'default' => __('Price at this location:', 'multi-location-product-and-inventory-management'),
                        'description' => __('Label shown before location-specific pricing.', 'multi-location-product-and-inventory-management'),
                        'type' => 'text',
                    ],
                ],
            ],
        ];
    }
}

if (!function_exists('mulopimfwc_get_text_management_defaults')) {
    function mulopimfwc_get_text_management_defaults(): array
    {
        $defaults = [];
        $groups = mulopimfwc_get_text_management_fields();
        foreach ($groups as $group) {
            if (empty($group['fields'])) {
                continue;
            }
            foreach ($group['fields'] as $field) {
                if (!isset($field['key'])) {
                    continue;
                }
                $defaults[$field['key']] = $field['default'] ?? '';
            }
        }
        return $defaults;
    }
}

if (!function_exists('mulopimfwc_get_text_value')) {
    function mulopimfwc_get_text_value(string $key, $default = null)
    {
        global $mulopimfwc_options;
        $options = is_array($mulopimfwc_options ?? null)
            ? $mulopimfwc_options
            : get_option('mulopimfwc_display_options', []);

        if ($default === null) {
            $defaults = mulopimfwc_get_text_management_defaults();
            if (array_key_exists($key, $defaults)) {
                $default = $defaults[$key];
            } else {
                $default = '';
            }
        }

        if (is_array($options) && array_key_exists($key, $options)) {
            $value = $options[$key];
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
            if (is_string($value) && trim($value) === '') {
                return $default;
            }
            if (!is_string($value)) {
                return $default;
            }
        }

        return $default;
    }
}
