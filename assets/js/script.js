jQuery(document).ready(function ($) {
    const modal = document.getElementById('lwp-store-selector-modal');
    const modalSubmit = document.getElementById('lwp-store-selector-submit');
    const COOKIE_MS_PER_DAY = 24 * 60 * 60 * 1000;
    const locationSettings = window.mulopimfwc_locationWiseProducts || {};
    const i18n = locationSettings.i18n || {};
    const selectStoreAlert = i18n.selectStore || 'Please select a store.';
    const selectStoreLocationAlert = i18n.selectStoreLocation || 'Please select a store location.';
    const cartClearError = i18n.cartClearError || 'Failed to clear the cart. Please try again.';

    function syncModalScrollLock() {
        var hasVisibleModal = $('[id^="lwp-store-selector-modal"]').filter(':visible').length > 0;
        $('body').toggleClass('mulopimfwc-modal-open', hasVisibleModal);
    }

    // Ensure auto-opened modals (rendered with display:flex server-side) lock scroll.
    syncModalScrollLock();
    setTimeout(syncModalScrollLock, 100);

    function getLocationCookieExpiryDays() {
        if (typeof mulopimfwc_locationWiseProducts === 'undefined') {
            return 30;
        }

        const rawValue =
            (typeof mulopimfwc_locationWiseProducts.cookie_expiry !== 'undefined')
                ? mulopimfwc_locationWiseProducts.cookie_expiry
                : mulopimfwc_locationWiseProducts.cookieExpiryDays;
        const value = parseInt(rawValue, 10);
        return Number.isFinite(value) && value > 0 ? value : 30;
    }

    function getStoreCookieName() {
        if (typeof mulopimfwc_locationWiseProducts !== 'undefined' && mulopimfwc_locationWiseProducts.cookieName) {
            return mulopimfwc_locationWiseProducts.cookieName;
        }
        return 'mulopimfwc_store_location';
    }

    function getAjaxUrl() {
        if (typeof mulopimfwc_locationWiseProducts === 'undefined') {
            return '';
        }
        return mulopimfwc_locationWiseProducts.ajaxUrl || mulopimfwc_locationWiseProducts.ajax_url || '';
    }

    function getCookieOptions() {
        const options = {
            path: '/',
            sameSite: 'lax',
            secure: window.location.protocol === 'https:'
        };

        if (typeof mulopimfwc_locationWiseProducts !== 'undefined') {
            if (mulopimfwc_locationWiseProducts.cookiePath) {
                options.path = mulopimfwc_locationWiseProducts.cookiePath;
            }
            if (mulopimfwc_locationWiseProducts.cookieSameSite) {
                options.sameSite = String(mulopimfwc_locationWiseProducts.cookieSameSite).toLowerCase();
            }
            if (typeof mulopimfwc_locationWiseProducts.cookieSecure === 'boolean') {
                options.secure = mulopimfwc_locationWiseProducts.cookieSecure;
            }
            if (mulopimfwc_locationWiseProducts.cookieDomain) {
                options.domain = mulopimfwc_locationWiseProducts.cookieDomain;
            }
        }

        return options;
    }

    function buildCookieString(name, value, expiryDate) {
        const options = getCookieOptions();
        const encodedValue = encodeURIComponent(String(value));
        let cookie = `${name}=${encodedValue};expires=${expiryDate.toUTCString()};path=${options.path};samesite=${options.sameSite}`;
        if (options.domain) {
            cookie += `;domain=${options.domain}`;
        }
        if (options.secure) {
            cookie += ';secure';
        }
        return cookie;
    }

    function setPluginCookie(name, value) {
        const expiryDate = new Date(Date.now() + getLocationCookieExpiryDays() * COOKIE_MS_PER_DAY);
        document.cookie = buildCookieString(name, value, expiryDate);
    }

    // Function to check if the cart has products
    function checkCartHasProducts(callback) {
        const ajaxUrl = getAjaxUrl();
        if (!ajaxUrl) {
            callback(false);
            return;
        }
        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: { action: 'check_cart_products' },
            success: function (response) {
                callback(response.success ? response.data.cartHasProducts : false);
            },
            error: function () {
                callback(false);
            }
        });
    }


    // Get location id of selected saved item
    function getSelectedLocationId() {
        var selectedItem = $('.saved-location-item.selected');
        return selectedItem.length ? selectedItem.data('location-id') : null;
    }

    function hasSavedLocationItems() {
        return $('.saved-location-item').length > 0;
    }

    function normalizeLocationText(value) {
        return (value || '')
            .toString()
            .toLowerCase()
            .replace(/[-_]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function normalizeLocationSlug(value) {
        return (value || '')
            .toString()
            .trim()
            .toLowerCase();
    }

    function savedLocationMatchesStore($item, storeValue, storeLabel) {
        if (!$item || !$item.length) {
            return false;
        }

        const normalizedValue = normalizeLocationText(storeValue);
        const normalizedLabel = normalizeLocationText(storeLabel);
        const address = ($item.data('address') || '').toString();
        const addressParts = address
            .split(',')
            .map(normalizeLocationText)
            .filter(Boolean);

        if (normalizedValue && addressParts.includes(normalizedValue)) {
            return true;
        }

        if (normalizedLabel && addressParts.some((part) => normalizedLabel.includes(part))) {
            return true;
        }

        const fields = [
            address,
            $item.data('street'),
            $item.data('city'),
            $item.data('state'),
            $item.data('postal'),
            $item.data('country'),
            $item.data('label')
        ]
            .filter(Boolean)
            .join(' ');
        const haystack = normalizeLocationText(fields);

        return (
            (normalizedValue && haystack.includes(normalizedValue)) ||
            (normalizedLabel && haystack.includes(normalizedLabel))
        );
    }

    function findMatchingSavedLocationId(storeValue, storeLabel) {
        if (!storeValue || !hasSavedLocationItems()) {
            return null;
        }

        if (storeValue === 'all-products') {
            const $allProducts = $('.saved-location-item[data-location-id="all-products"]').first();
            return $allProducts.length ? $allProducts.data('location-id') : 'all-products';
        }

        const $currentSelected = $('.saved-location-item.selected').first();
        if ($currentSelected.length) {
            const selectedId = ($currentSelected.data('location-id') || '').toString().trim();
            if (selectedId && selectedId !== 'all-products') {
                return selectedId;
            }
        }

        let matchId = null;

        $('.saved-location-item').each(function () {
            const $item = $(this);
            const itemId = $item.data('location-id');
            if (itemId === 'all-products') {
                return;
            }

            if (savedLocationMatchesStore($item, storeValue, storeLabel)) {
                matchId = itemId;
                return false;
            }
        });

        return matchId;
    }

    function getForcedUserLocationSelection() {
        const forced = window.mulopimfwcForcedUserLocationSelection;
        if (!forced || typeof forced !== 'object') {
            return null;
        }

        const locationId = (forced.locationId || '').toString().trim();
        if (!locationId) {
            return null;
        }

        return {
            locationId: locationId,
            storeSlug: normalizeLocationSlug(forced.storeSlug || '')
        };
    }

    function clearForcedUserLocationSelection() {
        if (Object.prototype.hasOwnProperty.call(window, 'mulopimfwcForcedUserLocationSelection')) {
            delete window.mulopimfwcForcedUserLocationSelection;
        }
    }

    function consumeForcedUserLocationId(selectedStore) {
        const forced = getForcedUserLocationSelection();
        clearForcedUserLocationSelection();

        if (!forced) {
            return null;
        }

        const normalizedSelectedStore = normalizeLocationSlug(selectedStore);
        if (normalizedSelectedStore !== 'all-products' && normalizeLocationSlug(forced.locationId) === 'all-products') {
            return null;
        }

        return forced.locationId;
    }

    function clearPluginCookie(name) {
        const options = getCookieOptions();
        let cookie = `${name}=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=${options.path};samesite=${options.sameSite}`;
        if (options.domain) {
            cookie += `;domain=${options.domain}`;
        }
        if (options.secure) {
            cookie += ';secure';
        }
        document.cookie = cookie;
    }

    // Function to clear the cart and reload the page
    function clearCartAndReload() {
        const ajaxUrl = getAjaxUrl();
        if (!ajaxUrl) {
            return;
        }
        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: { action: 'clear_cart' },
            success: function () {
                // Reload with cache-busting parameter
                var url = window.location.href.split('?')[0];
                var separator = '?';
                url += separator + '_t=' + Date.now();
                window.location.href = url;
            },
            error: function () {
                alert(cartClearError);
            }
        });
    }

    let locationChangeInProgress = false;

    function isSpecializedPopupModal($modal) {
        return !!(
            $modal &&
            $modal.length &&
            (
                $modal.hasClass('lwp-location-info-popup') ||
                $modal.hasClass('lwp-classic-popup') ||
                $modal.hasClass('lwp-modern-popup')
            )
        );
    }

    function buildLocationReloadUrl(selectedStore) {
        var url = window.location.href.split('?')[0];
        var separator = url.indexOf('?') !== -1 ? '&' : '?';

        if (selectedStore) {
            url += separator + 'mulopimfwc_loc=' + encodeURIComponent(selectedStore) + '&_t=' + Date.now();
        } else {
            url += separator + '_t=' + Date.now();
        }

        return url;
    }

    function closeLocationModal($modal) {
        if ($modal && $modal.length) {
            $modal.hide();
        }
        syncModalScrollLock();
    }

    function syncSelectedUserLocation(selectedStore, locationLabel, explicitLocationId) {
        if (selectedStore === 'all-products') {
            setPluginCookie('mulopimfwc_user_location', 'all-products');
            return;
        }

        const resolvedLocationId =
            explicitLocationId ||
            consumeForcedUserLocationId(selectedStore) ||
            findMatchingSavedLocationId(selectedStore, locationLabel || '');

        if (resolvedLocationId) {
            setPluginCookie('mulopimfwc_user_location', resolvedLocationId);
        } else if (hasSavedLocationItems()) {
            clearPluginCookie('mulopimfwc_user_location');
        }
    }

    function fallbackLocationReload(selectedStore, options) {
        const config = options || {};

        setPluginCookie(getStoreCookieName(), selectedStore);
        syncSelectedUserLocation(selectedStore, config.locationLabel, config.locationId);
        closeLocationModal(config.$modal);
        window.location.href = buildLocationReloadUrl(selectedStore);
    }

    function performAjaxLocationSwitch(selectedStore, options) {
        const config = options || {};
        const ajaxUrl = getAjaxUrl();
        const behavior = locationSettings.location_switching_behavior || 'update_cart';
        const requiresCleanup = locationSettings.allow_mixed_in_cart !== 'on' && behavior !== 'preserve_cart';

        if (!ajaxUrl) {
            fallbackLocationReload(selectedStore, config);
            return;
        }

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: {
                action: 'mulopimfwc_switch_location',
                nonce: locationSettings.nonce || '',
                location: selectedStore
            },
            success: function (response) {
                if (response && response.success) {
                    syncSelectedUserLocation(selectedStore, config.locationLabel, config.locationId);
                    closeLocationModal(config.$modal);

                    var removedItems = response.data && response.data.removed_items ? response.data.removed_items : [];
                    if (removedItems.length) {
                        const template = i18n.cartRemovedItems ||
                            'The following items were removed because they are not available at the selected location: %s';
                        const itemsText = removedItems.join(', ');
                        const message = template.indexOf('%s') !== -1
                            ? template.replace('%s', itemsText)
                            : template + ' ' + itemsText;
                        alert(message);
                    }

                    window.location.replace(buildLocationReloadUrl(selectedStore));
                    return;
                }

                locationChangeInProgress = false;
                alert(
                    (response && response.data && response.data.message) ||
                    i18n.cartUnableChange ||
                    'Unable to change location. Please try again.'
                );
            },
            error: function () {
                if (!requiresCleanup) {
                    fallbackLocationReload(selectedStore, config);
                    return;
                }

                locationChangeInProgress = false;
                alert(i18n.cartUnableChangeNow || 'Unable to change location right now. Please try again.');
            }
        });
    }

    function applyLocationSelection(selectedStore, options) {
        const config = options || {};
        const selectedLocation = (selectedStore || '').toString().trim();
        const previousLocation = getCookie(getStoreCookieName());

        if (!selectedLocation) {
            alert(config.emptySelectionMessage || selectStoreLocationAlert);
            return false;
        }

        if (locationChangeInProgress) {
            return false;
        }
        locationChangeInProgress = true;

        if (normalizeLocationSlug(previousLocation) === normalizeLocationSlug(selectedLocation)) {
            closeLocationModal(config.$modal);
            locationChangeInProgress = false;
            return true;
        }

        const allowMixed = locationSettings.allow_mixed_in_cart === 'on';
        const behavior = locationSettings.location_switching_behavior || 'update_cart';
        const shouldUpdateCart = !allowMixed && behavior !== 'preserve_cart';

        const proceed = function () {
            if (!shouldUpdateCart) {
                fallbackLocationReload(selectedLocation, config);
                return;
            }

            performAjaxLocationSwitch(selectedLocation, config);
        };

        if (!shouldUpdateCart) {
            proceed();
            return true;
        }

        checkCartHasProducts(function (cartHasProducts) {
            if (!cartHasProducts) {
                proceed();
                return;
            }

            const shouldPrompt = behavior === 'prompt_user' || !!locationSettings.location_change_notification;
            if (shouldPrompt) {
                const message =
                    locationSettings.location_notification_text ||
                    'Do you want to change the store location? Your cart will be updated.';
                const confirmed = window.confirm(message);
                if (!confirmed) {
                    locationChangeInProgress = false;
                    return;
                }
            }

            proceed();
        });

        return true;
    }

    window.mulopimfwcLocationSwitch = {
        selectLocation: applyLocationSelection,
        isSwitching: function () {
            return locationChangeInProgress;
        }
    };

    // Modal logic for changing store location (works for both global and instance-specific modals)
    function handleModalSubmit($modal) {
        if (isSpecializedPopupModal($modal)) {
            return;
        }

        var $submitBtn = $modal.find('#lwp-store-selector-submit');
        var $dropdown = $modal.find('#lwp-selected-store');
        
        if ($submitBtn.length && $dropdown.length) {
            $submitBtn.off('click.mulopimfwc').on('click.mulopimfwc', function(e) {
                e.preventDefault();
                var selectedStore = $dropdown.val();
                if (!selectedStore) {
                    alert(selectStoreAlert);
                    return;
                }

                var $selectedOption = $dropdown.is('select') ? $dropdown.find('option:selected') : $();
                applyLocationSelection(selectedStore, {
                    $modal: $modal,
                    locationLabel: $selectedOption.length ? ($selectedOption.text() || '') : '',
                    locationId: $selectedOption.length ? ($selectedOption.data('location-id') || '') : '',
                    emptySelectionMessage: selectStoreAlert
                });
            });
        }
    }

    // Handle instance-specific modals that are already in the DOM
    setTimeout(function() {
        $('[id^="lwp-store-selector-modal"]').each(function() {
            var $modal = $(this);
            if (!$modal.data('mulopimfwc-handled')) {
                handleModalSubmit($modal);
                $modal.data('mulopimfwc-handled', true);
            }
        });
    }, 100);

    function getShortcodeSelection($form) {
        const $single = $form.find('#lwp-shortcode-selector');
        if ($single.length) {
            return {
                value: $single.val() || '',
                label: $single.find('option:selected').text() || '',
                isHierarchical: false,
                isReady: true
            };
        }

        const $dropdowns = $form.find('.lwp-shortcode-selector-dropdown');
        if ($dropdowns.length) {
            const $visibleDropdowns = $dropdowns.filter(function () {
                return $(this).closest('.lwp-select-container').is(':visible');
            });
            const $activeDropdown = $visibleDropdowns.last();
            const value = $activeDropdown.length ? ($activeDropdown.val() || '') : '';
            const label = $activeDropdown.find('option:selected').text() || '';

            return {
                value: value,
                label: label,
                isHierarchical: true,
                isReady: !!value
            };
        }

        const $hidden = $form.find('#lwp-selected-store-shortcode');
        return {
            value: $hidden.length ? ($hidden.val() || '') : '',
            label: '',
            isHierarchical: false,
            isReady: true
        };
    }

    function resolveShortcodeLabel($form, value, fallbackLabel) {
        if (fallbackLabel) {
            return fallbackLabel;
        }

        if (!value) {
            return '';
        }

        const $option = $form
            .find('select option:selected')
            .filter(function () {
                return $(this).val() === value;
            })
            .first();

        return $option.length ? $option.text() : '';
    }

    function syncShortcodeHiddenValue($form, value) {
        const $hidden = $form.find('#lwp-selected-store-shortcode');
        if ($hidden.length) {
            $hidden.val(value || '');
        }
    }

    $(document).on('submit', '#lwp-shortcode-selector-form', function (e) {
        e.preventDefault();

        const $form = $(this);
        const selection = getShortcodeSelection($form);
        const selectedStore = selection.value;
        const storeCookieName = getStoreCookieName();

        if (!selectedStore) {
            alert(selectStoreLocationAlert);
            return;
        }

        if (selection.isHierarchical && !selection.isReady) {
            alert(selectStoreLocationAlert);
            return;
        }

        syncShortcodeHiddenValue($form, selectedStore);

        const selectedStoreLabel = resolveShortcodeLabel($form, selectedStore, selection.label);
        const forcedLocationId = consumeForcedUserLocationId(selectedStore);
        const matchedLocationId = forcedLocationId || findMatchingSavedLocationId(selectedStore, selectedStoreLabel);

        if (selectedStore === 'all-products') {
            setPluginCookie(storeCookieName, 'all-products');
            setPluginCookie('mulopimfwc_user_location', 'all-products');
            location.reload();
            return;
        }

        const allowMixedInCart = locationSettings.allow_mixed_in_cart || 'off';
        const allowCartUpdate = !!locationSettings.allow_cart_update;

        if (allowMixedInCart !== 'on' && allowCartUpdate) {

            // Check if the cart has products before changing the store location
            checkCartHasProducts(function (cartHasProducts) {
                if (cartHasProducts && locationSettings.location_change_notification) {
                    const confirmChange = confirm(locationSettings.location_notification_text || 'Do you want to change the store location? Your cart will be updated.');
                    if (!confirmChange) {
                        var currentLocation = getCookie(storeCookieName);
                        if (currentLocation) {
                            var revertUrl = window.location.href.split('?')[0];
                            var separator = '?';
                            revertUrl += separator + 'mulopimfwc_loc=' + encodeURIComponent(currentLocation) + '&_t=' + Date.now();
                            window.location.href = revertUrl;
                        }
                        return;
                    }
                }

                // Set the cookie and clear the cart
                setPluginCookie(storeCookieName, selectedStore);
                if (matchedLocationId) {
                    setPluginCookie('mulopimfwc_user_location', matchedLocationId);
                } else if (hasSavedLocationItems()) {
                    clearPluginCookie('mulopimfwc_user_location');
                }
                clearCartAndReload();
            });
        } else {
            // Set the cookie without clearing the cart
            setPluginCookie(storeCookieName, selectedStore);
            if (matchedLocationId) {
                setPluginCookie('mulopimfwc_user_location', matchedLocationId);
            } else if (hasSavedLocationItems()) {
                clearPluginCookie('mulopimfwc_user_location');
            }
            // Reload with cache-busting parameter
            var url = window.location.href.split('?')[0];
            var separator = '?';
            url += separator + 'mulopimfwc_loc=' + encodeURIComponent(selectedStore) + '&_t=' + Date.now();
            window.location.href = url;
        }
    });

    // Helper function to get cookie value
    function getCookie(name) {
        const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        if (!match) {
            return '';
        }
        try {
            return decodeURIComponent(match[2]);
        } catch (e) {
            return match[2];
        }
    }

    function updateSingleProductAddToCartState() {
        if (
            typeof mulopimfwc_locationWiseProducts === 'undefined' ||
            !mulopimfwc_locationWiseProducts.locationSelectionEnforced ||
            !mulopimfwc_locationWiseProducts.singleProductRequiresLocation
        ) {
            return;
        }

        const selectedLocation =
            getCookie(getStoreCookieName()) ||
            (mulopimfwc_locationWiseProducts.currentLocation || '');
        const needsSelection = !selectedLocation || selectedLocation === 'all-products';
        const $button = $('.single_add_to_cart_button');

        if (!$button.length) {
            return;
        }

        let $notice = $('#mulopimfwc-location-required-notice');
        if (!$notice.length) {
            $notice = $('<p id="mulopimfwc-location-required-notice" class="mulopimfwc-location-required-notice" style="display:none;"></p>');
            $button.after($notice);
        }

        const prompt =
            mulopimfwc_locationWiseProducts.selectLocationPrompt ||
            'Please select a store location before adding this product to your cart.';

        if (needsSelection) {
            $button.prop('disabled', true).addClass('disabled');
            // $notice.text(prompt).show();
        } else {
            $button.prop('disabled', false).removeClass('disabled');
            $notice.hide();
        }
    }

    updateSingleProductAddToCartState();

    $(document).on('change', '#lwp-shortcode-selector, .lwp-shortcode-selector-dropdown, #mulopimfwc_store_location, .mulopimfwc-location-selector', function () {
        setTimeout(updateSingleProductAddToCartState, 100);
    });

    // Handle popup shortcode button clicks
    $(document).on('click', '.mulopimfwc-popup-trigger-button', function (e) {
        e.preventDefault();
        var $button = $(this);
        var instanceId = $button.data('instance-id');
        var layout = $button.data('layout') || 'default';
        
        // Find modal for this instance
        var $modal = $('#lwp-store-selector-modal-' + instanceId);
        
        // Fallback to instance class selector
        if (!$modal.length && instanceId) {
            $modal = $('.mulopimfwc-popup-instance-' + instanceId.replace('mulopimfwc-popup-', ''));
        }
        
        // If still not found, try to find any modal with matching layout
        if (!$modal.length) {
            // Check if modal exists in DOM but might not be loaded yet
            $modal = $('#lwp-store-selector-modal');
        }
        
        // Show the modal
        if ($modal.length) {
            // Ensure modal is initialized if not already
            if (!$modal.hasClass('mulopimfwc-initialized')) {
                // Trigger initialization based on layout
                if ($modal.hasClass('lwp-modern-popup')) {
                    if (typeof window.initModernPopup === 'function') {
                        window.initModernPopup($modal);
                    } else {
                        // Trigger custom event for initialization
                        $modal.trigger('mulopimfwc:init');
                    }
                } else if ($modal.hasClass('lwp-classic-popup')) {
                    if (typeof window.initClassicPopup === 'function') {
                        window.initClassicPopup($modal);
                    } else {
                        $modal.trigger('mulopimfwc:init');
                    }
                } else if ($modal.hasClass('lwp-location-info-popup')) {
                    if (typeof window.initPopupLayouts === 'function') {
                        window.initPopupLayouts($modal);
                    } else {
                        $modal.trigger('mulopimfwc:init');
                    }
                }
            }
            
            $modal.css('display', 'flex');
            syncModalScrollLock();

            // Ensure maps inside the popup render correctly once visible
            if ($modal.hasClass('lwp-location-info-popup')) {
                setTimeout(function () {
                    $(document).trigger('mulopimfwc:popup:opened', [$modal]);
                }, 60);
            }
        } else {
            console.warn('Popup modal not found for instance: ' + instanceId);
        }
    });

    // Handle modal close clicks (backdrop and close buttons)
    $(document).on('click', '[id^="lwp-store-selector-modal"]', function (e) {
        var $modal = $(this);
        if (!$(e.target).is($modal)) {
            return;
        }

        var allowBackdropClose = $modal.data('allowBackdropClose');
        if (!allowBackdropClose) {
            return;
        }

        $modal.css('display', 'none');
        syncModalScrollLock();
    });

    // Handle close button clicks
    $(document).on('click', '.lwp-store-selector-close, .lwp-location-info-popup__close', function (e) {
        e.preventDefault();
        var $closeBtn = $(this);
        var $modal = $closeBtn.closest('[id^="lwp-store-selector-modal"]');
        if ($modal.length) {
            $modal.css('display', 'none');
            syncModalScrollLock();
        }
    });
});

jQuery(document).ready(function ($) {
    const variationSettings = (window.mulopimfwc_locationWiseProducts && mulopimfwc_locationWiseProducts.variation) || {};
    const variationMessages = {
        infoHeading: variationSettings.infoHeading || 'Information for %s',
        stockLabel: variationSettings.stockLabel || 'Stock',
        inStock: variationSettings.inStock || '%s in stock',
        outOfStock: variationSettings.outOfStock || 'Out of stock',
        backorder: variationSettings.backorder || 'Available on backorder',
        priceLabel: variationSettings.priceLabel || 'Price at this location:'
    };

    function formatWithValue(template, value) {
        if (!template) {
            return value;
        }
        if (template.indexOf('%s') !== -1) {
            return template.replace('%s', value);
        }
        return template + ' ' + value;
    }

    // Listen for variation change events
    $('.variations_form').on('found_variation', function (event, variation) {
        if (variation.location_data) {
            var location_data = variation.location_data;
            var location_info_html = '';

            location_info_html += '<h4>' + formatWithValue(variationMessages.infoHeading, location_data.location_name) + '</h4>';

            // Stock info
            if (location_data.stock_display && location_data.stock_display.show && location_data.stock_display.label) {
                var statusClass = 'in-stock';
                if (location_data.stock_display.status === 'outofstock') {
                    statusClass = 'out-of-stock';
                } else if (location_data.stock_display.status === 'onbackorder') {
                    statusClass = 'on-backorder';
                }
                if (location_data.stock_display.level) {
                    statusClass += ' stock-level-' + location_data.stock_display.level;
                }
                location_info_html += '<p class="location-stock"><strong>' + variationMessages.stockLabel + '</strong> ';
                location_info_html += '<span class="' + statusClass + '">' + location_data.stock_display.label + '</span>';
                location_info_html += '</p>';
            } else if (location_data.location_stock !== '') {
                location_info_html += '<p class="location-stock"><strong>' + variationMessages.stockLabel + '</strong> ';

                if (parseInt(location_data.location_stock) > 0) {
                    location_info_html += '<span class="in-stock">' + formatWithValue(variationMessages.inStock, location_data.location_stock) + '</span>';
                } else {
                    if (location_data.location_backorders === 'off') {
                        location_info_html += '<span class="out-of-stock">' + variationMessages.outOfStock + '</span>';
                    } else {
                        location_info_html += '<span class="on-backorder">' + variationMessages.backorder + '</span>';
                    }
                }
                location_info_html += '</p>';
            }

            // Price info
            if (location_data.location_regular_price) {
                location_info_html += '<p class="location-price"><strong>' + variationMessages.priceLabel + '</strong> ';

                if (location_data.location_sale_price) {
                    location_info_html += '<del>' + location_data.location_regular_price + '</del> <ins>' + location_data.location_sale_price + '</ins>';
                } else {
                    location_info_html += location_data.location_regular_price;
                }
                location_info_html += '</p>';
            }

            $('.location-specific-info').hide().html(location_info_html).fadeIn(500);
        } else {
            // Hide location info when no variation is selected
            $('.location-specific-info').fadeOut(500);
        }
    });
});
