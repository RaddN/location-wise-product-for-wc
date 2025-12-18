jQuery(document).ready(function ($) {
    const modal = document.getElementById('lwp-store-selector-modal');
    const modalSubmit = document.getElementById('lwp-store-selector-submit');
    const COOKIE_MS_PER_DAY = 24 * 60 * 60 * 1000;

    function getLocationCookieExpiryDays() {
        if (typeof mulopimfwc_locationWiseProducts === 'undefined') {
            return 30;
        }

        const value = parseInt(mulopimfwc_locationWiseProducts.cookie_expiry, 10);
        return Number.isFinite(value) && value > 0 ? value : 30;
    }

    function setPluginCookie(name, value) {
        const expiryDate = new Date(Date.now() + getLocationCookieExpiryDays() * COOKIE_MS_PER_DAY);
        document.cookie = `${name}=${value};expires=${expiryDate.toUTCString()};path=/;samesite=lax`;
    }

    // Function to check if the cart has products
    function checkCartHasProducts(callback) {
        $.ajax({
            url: mulopimfwc_locationWiseProducts.ajaxUrl,
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
        if ($currentSelected.length && savedLocationMatchesStore($currentSelected, storeValue, storeLabel)) {
            return $currentSelected.data('location-id');
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

    function clearPluginCookie(name) {
        document.cookie = `${name}=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/;samesite=lax`;
    }

    // Function to clear the cart and reload the page
    function clearCartAndReload() {
        $.ajax({
            url: mulopimfwc_locationWiseProducts.ajaxUrl,
            method: 'POST',
            data: { action: 'clear_cart' },
            success: function () {
                window.location.href = window.location.href.split('?')[0];
            },
            error: function () {
                alert('Failed to clear the cart. Please try again.');
            }
        });
    }

    // Modal logic for changing store location
    if (modal && modalSubmit) {
        modalSubmit.addEventListener('click', function () {
            const modalDropdown = document.getElementById('lwp-selected-store');
            const selectedStore = modalDropdown.value;
            if (selectedStore) {
                setPluginCookie('mulopimfwc_store_location', selectedStore);
                modal.style.display = 'none';
                location.reload();
            } else {
                alert('Please select a store.');
            }
        });
    }

    $('#lwp-shortcode-selector-form').on('change', function () {
        const dropdown = $(this).find('#lwp-shortcode-selector');
        const selectedStore = dropdown.val();
        const selectedStoreLabel = dropdown.find('option:selected').text() || '';
        const matchedLocationId = findMatchingSavedLocationId(selectedStore, selectedStoreLabel);


        if (!selectedStore) {
            alert('Please select a store location.');
            return;
        }

        if (selectedStore === 'all-products') {
            setPluginCookie('mulopimfwc_store_location', 'all-products');
            setPluginCookie('mulopimfwc_user_location', 'all-products');
            location.reload();
            return;
        }

        if (mulopimfwc_locationWiseProducts.allow_mixed_in_cart !== 'on' && mulopimfwc_locationWiseProducts.allow_cart_update) {

            // Check if the cart has products before changing the store location
            checkCartHasProducts(function (cartHasProducts) {
                if (cartHasProducts && mulopimfwc_locationWiseProducts.location_change_notification) {
                    const confirmChange = confirm(mulopimfwc_locationWiseProducts.location_notification_text || 'Do you want to change the store location? Your cart will be emptied.');
                    if (!confirmChange) {
                        dropdown.val(getCookie('mulopimfwc_store_location') || '');
                        $('.saved-location-item').removeClass('selected');
                        $('.saved-location-item[data-location-id="' + getCookie('mulopimfwc_user_location') + '"]').addClass('selected');
                        var selectedAddress = $('.saved-location-item.selected').data('address');
                        var label = $('.saved-location-item.selected').data('label');
                        if (selectedAddress) {
                            $('.address-text').text(label + ' - ' + selectedAddress);
                        }
                        return;
                    }
                }

                // Set the cookie and clear the cart
                setPluginCookie('mulopimfwc_store_location', selectedStore);
                if (matchedLocationId) {
                    setPluginCookie('mulopimfwc_user_location', matchedLocationId);
                } else if (hasSavedLocationItems()) {
                    clearPluginCookie('mulopimfwc_user_location');
                }
                clearCartAndReload();
            });
        } else {
            // Set the cookie without clearing the cart
            setPluginCookie('mulopimfwc_store_location', selectedStore);
            if (matchedLocationId) {
                setPluginCookie('mulopimfwc_user_location', matchedLocationId);
            } else if (hasSavedLocationItems()) {
                clearPluginCookie('mulopimfwc_user_location');
            }
            window.location.href = window.location.href.split('?')[0];
        }
    });

    // Helper function to get cookie value
    function getCookie(name) {
        const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        return match ? match[2] : '';
    }

    function updateSingleProductAddToCartState() {
        if (
            typeof mulopimfwc_locationWiseProducts === 'undefined' ||
            !mulopimfwc_locationWiseProducts.locationSelectionEnforced ||
            !mulopimfwc_locationWiseProducts.singleProductRequiresLocation
        ) {
            return;
        }

        const selectedLocation = getCookie('mulopimfwc_store_location');
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

    $(document).on('change', '#lwp-shortcode-selector, #mulopimfwc_store_location, .mulopimfwc-location-selector', function () {
        setTimeout(updateSingleProductAddToCartState, 100);
    });
});

jQuery(document).ready(function ($) {
    // Listen for variation change events
    $('.variations_form').on('found_variation', function (event, variation) {
        if (variation.location_data) {
            var location_data = variation.location_data;
            var location_info_html = '';

            location_info_html += '<h4>' + 'Information for' + location_data.location_name + '</h4>';

            // Stock info
            if (location_data.location_stock !== '') {
                location_info_html += '<p class="location-stock"><strong>Stock</strong> ';

                if (parseInt(location_data.location_stock) > 0) {
                    location_info_html += '<span class="in-stock">' + location_data.location_stock + ' in stock</span>';
                } else {
                    if (location_data.location_backorders === 'off') {
                        location_info_html += '<span class="out-of-stock">Out of stock</span>';
                    } else {
                        location_info_html += '<span class="on-backorder">Available on backorder</span>';
                    }
                }
                location_info_html += '</p>';
            }

            // Price info
            if (location_data.location_regular_price) {
                location_info_html += '<p class="location-price"><strong>Price at this location:</strong> ';

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
