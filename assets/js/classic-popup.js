jQuery(function ($) {
    // Function to initialize classic popup modals
    function initializeClassicPopups() {
        // Handle all classic popup modals (including instance-specific ones)
        var $modals = $('#lwp-store-selector-modal.lwp-classic-popup, [id^="lwp-store-selector-modal-"].lwp-classic-popup').not('.mulopimfwc-initialized');
        if (!$modals.length) {
            return;
        }
        
        // Process each modal
        $modals.each(function() {
            var $modal = $(this);
            // Always initialize if modal exists, data will be read from attribute or global
            try {
                initClassicPopup($modal);
                $modal.addClass('mulopimfwc-initialized');
            } catch (e) {
                console.warn('Error initializing classic popup:', e);
            }
        });
    }
    
    // Initialize on page load
    initializeClassicPopups();
    
    // Use MutationObserver for better compatibility (DOMNodeInserted is deprecated)
    if (typeof MutationObserver !== 'undefined') {
        var observer = new MutationObserver(function(mutations) {
            var shouldInit = false;
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length) {
                    for (var i = 0; i < mutation.addedNodes.length; i++) {
                        var node = mutation.addedNodes[i];
                        if (node.nodeType === 1) { // Element node
                            var $node = $(node);
                            if ($node.is('#lwp-store-selector-modal.lwp-classic-popup, [id^="lwp-store-selector-modal-"].lwp-classic-popup') ||
                                $node.find('#lwp-store-selector-modal.lwp-classic-popup, [id^="lwp-store-selector-modal-"].lwp-classic-popup').length) {
                                shouldInit = true;
                                break;
                            }
                        }
                    }
                }
            });
            if (shouldInit) {
                setTimeout(initializeClassicPopups, 100);
            }
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    } else {
        // Fallback for older browsers
        $(document).on('DOMNodeInserted', function(e) {
            var $target = $(e.target);
            var $newModals = $target.find('#lwp-store-selector-modal.lwp-classic-popup, [id^="lwp-store-selector-modal-"].lwp-classic-popup').add(
                $target.filter('#lwp-store-selector-modal.lwp-classic-popup, [id^="lwp-store-selector-modal-"].lwp-classic-popup')
            ).not('.mulopimfwc-initialized');
            if ($newModals.length) {
                setTimeout(initializeClassicPopups, 100);
            }
        });
    }
    
    // Also check after a delay to catch modals rendered in footer
    setTimeout(initializeClassicPopups, 500);
    
    function initClassicPopup($modal) {
        // Skip if already initialized
        if ($modal.hasClass('mulopimfwc-initialized')) {
            return;
        }
        
        var $search = $modal.find('#lwp-classic-search');
        var $items = $modal.find('.lwp-classic-location');
        var $empty = $modal.find('.lwp-classic-empty').last();
        var $selected = $modal.find('#lwp-selected-store');
        var $status = $modal.find('#lwp-classic-location-status');
        
        // Get data from modal data attribute or fallback to global window object (for backward compatibility)
        var popupDataAttr = $modal.attr('data-popup-data');
        var popupData = {};
        
        if (popupDataAttr) {
            try {
                popupData = typeof popupDataAttr === 'string' ? JSON.parse(popupDataAttr) : popupDataAttr;
            } catch (e) {
                console.warn('Error parsing popup data:', e);
                popupData = window.mulopimfwcClassicPopupData || {};
            }
        } else {
            popupData = window.mulopimfwcClassicPopupData || {};
        }
        
        var i18n = (popupData && popupData.i18n) || {};

    function applyFilter() {
        var query = ($search.val() || '').toString().toLowerCase().trim();
        var visibleCount = 0;

        $items.each(function () {
            var $item = $(this);
            var haystack = ($item.data('search') || '').toString();
            var isMatch = !query || haystack.indexOf(query) !== -1;
            $item.toggle(isMatch);
            if (isMatch) {
                visibleCount += 1;
            }
        });

        if (visibleCount === 0 && $items.length) {
            $empty.show();
        } else {
            $empty.hide();
        }
    }

    $search.on('input', applyFilter);

    function setStatus(message, state) {
        if (!$status.length) {
            return;
        }
        $status.text(message || '');
        if (state) {
            $status.attr('data-state', state);
        } else {
            $status.removeAttr('data-state');
        }
    }

    function toRadians(value) {
        return value * Math.PI / 180;
    }

    function haversineKm(lat1, lon1, lat2, lon2) {
        var radius = 6371;
        var dLat = toRadians(lat2 - lat1);
        var dLon = toRadians(lon2 - lon1);
        var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(toRadians(lat1)) * Math.cos(toRadians(lat2)) *
            Math.sin(dLon / 2) * Math.sin(dLon / 2);
        var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return radius * c;
    }

    function formatDistance(km) {
        if (!Number.isFinite(km)) {
            return '';
        }
        if (km < 1) {
            return Math.round(km * 1000) + ' m';
        }
        return km.toFixed(1) + ' ' + (i18n.distanceUnit || 'km');
    }

    function updateDistances(lat, lng, label, approximate) {
        $items.each(function () {
            var $item = $(this);
            var latValue = parseFloat($item.data('lat'));
            var lngValue = parseFloat($item.data('lng'));
            var $distance = $item.find('.lwp-classic-location__distance');

            if (!Number.isFinite(latValue) || !Number.isFinite(lngValue)) {
                $distance.text('').removeClass('has-distance');
                return;
            }

            var distance = haversineKm(lat, lng, latValue, lngValue);
            var labelText = formatDistance(distance);
            if (labelText) {
                var suffix = i18n.distanceAway || 'away';
                $distance.text(labelText + ' ' + suffix).addClass('has-distance');
            } else {
                $distance.text('').removeClass('has-distance');
            }
        });

        if (label) {
            setStatus(label, approximate ? 'approximate' : 'ready');
        } else {
            setStatus(i18n.distanceFromYou || 'Distances from your location', 'ready');
        }
    }

    function detectByIp() {
        $.ajax({
            url: 'https://ipapi.co/jsonp/',
            dataType: 'jsonp',
            timeout: 5000
        }).done(function (data) {
            if (data && data.latitude && data.longitude) {
                var labelParts = [data.city, data.region, data.country_name].filter(Boolean).join(', ');
                var label = labelParts ? (i18n.distanceApproximate || 'Approximate distances') + ': ' + labelParts : (i18n.distanceApproximate || 'Approximate distances');
                updateDistances(parseFloat(data.latitude), parseFloat(data.longitude), label, true);
            } else {
                setStatus(i18n.detectFailed || 'We could not detect your location. Distances may be unavailable.', 'error');
            }
        }).fail(function () {
            setStatus(i18n.detectFailed || 'We could not detect your location. Distances may be unavailable.', 'error');
        });
    }

    function attemptDetection() {
        setStatus(i18n.detecting || 'Detecting your location...', 'loading');

        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function (position) {
                    updateDistances(position.coords.latitude, position.coords.longitude);
                },
                function () {
                    detectByIp();
                },
                { enableHighAccuracy: true, timeout: 8000, maximumAge: 0 }
            );
        } else {
            detectByIp();
        }
    }

    $modal.on('click', '.lwp-classic-select', function () {
        var slug = $(this).data('slug');
        if (!slug) {
            return;
        }
        $selected.val(slug);
        $items.removeClass('selected');
        $(this).closest('.lwp-classic-location').addClass('selected');
    });

    $modal.on('click', '.lwp-classic-location', function (event) {
        if ($(event.target).closest('.lwp-classic-select').length) {
            return;
        }
        var slug = $(this).data('slug');
        if (slug) {
            $selected.val(slug);
            $items.removeClass('selected');
            $(this).addClass('selected');
        }
    });

    // Handle submit button click
    $modal.on('click', '#lwp-store-selector-submit', function (e) {
        e.preventDefault();
        var slug = $selected.val();
        if (!slug) {
            alert('Please select a store location.');
            return;
        }
        
        // Set cookie
        var cookieDays = 30;
        if (window.mulopimfwc_locationWiseProducts && mulopimfwc_locationWiseProducts.cookie_expiry) {
            var override = parseInt(mulopimfwc_locationWiseProducts.cookie_expiry, 10);
            if (Number.isFinite(override) && override > 0) {
                cookieDays = override;
            }
        }
        // FIXED: Validate location slug and add secure flag for HTTPS
        var expiryDate = new Date(Date.now() + cookieDays * 24 * 60 * 60 * 1000);
        var isSecure = window.location.protocol === 'https:';
        var cookieString = 'mulopimfwc_store_location=' + encodeURIComponent(slug) + 
                          ';expires=' + expiryDate.toUTCString() + 
                          ';path=/' + 
                          (isSecure ? ';secure' : '') + 
                          ';samesite=lax';
        document.cookie = cookieString;
        
        // FIXED: Validate cookie was set by making AJAX call to server
        // Server will validate location exists before accepting it
        if (window.mulopimfwc_locationWiseProducts && mulopimfwc_locationWiseProducts.ajax_url) {
            jQuery.post(mulopimfwc_locationWiseProducts.ajax_url, {
                action: 'mulopimfwc_validate_location',
                location_slug: slug,
                nonce: mulopimfwc_locationWiseProducts.nonce || ''
            }).fail(function() {
                // If validation fails, remove invalid cookie
                document.cookie = 'mulopimfwc_store_location=;expires=Thu, 01 Jan 1970 00:00:00 UTC;path=/;';
            });
        }
        
        // Hide modal and reload with cache-busting parameter
        $modal.hide();
        $('body').removeClass('mulopimfwc-modal-open');
        
        // Add cache-busting parameter to force fresh page load
        var url = window.location.href.split('?')[0];
        var separator = url.indexOf('?') !== -1 ? '&' : '?';
        url += separator + 'mulopimfwc_loc=' + encodeURIComponent(slug) + '&_t=' + Date.now();
        window.location.href = url;
    });

        attemptDetection();
    }
});
