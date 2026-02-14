jQuery(function ($) {

    const locationI18n = (window.mulopimfwc_locationWiseProducts && mulopimfwc_locationWiseProducts.i18n) || {};
    const selectStoreLocationAlert = locationI18n.selectStoreLocation || 'Please select a store location.';

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
        // Check cache first (24 hour cache)
        var cacheKey = 'mulopimfwc_ip_location';
        var cached = localStorage.getItem(cacheKey);
        if (cached) {
            try {
                var cachedData = JSON.parse(cached);
                var cacheTime = cachedData.timestamp || 0;
                var now = Date.now();
                // Cache valid for 24 hours
                if (now - cacheTime < 24 * 60 * 60 * 1000 && cachedData.data && cachedData.data.latitude && cachedData.data.longitude) {
                    var data = cachedData.data;
                    var labelParts = [data.city, data.region, data.country_name].filter(Boolean).join(', ');
                    var label = labelParts ? (i18n.distanceApproximate || 'Approximate distances') + ': ' + labelParts : (i18n.distanceApproximate || 'Approximate distances');
                    updateDistances(parseFloat(data.latitude), parseFloat(data.longitude), label, true);
                    return;
                }
            } catch (e) {
                // Invalid cache, continue to API call
            }
        }

        // Check rate limit cooldown (1 hour cooldown after 429 error)
        var rateLimitKey = 'mulopimfwc_ip_ratelimit';
        var rateLimitData = localStorage.getItem(rateLimitKey);
        if (rateLimitData) {
            try {
                var rateLimitInfo = JSON.parse(rateLimitData);
                var rateLimitTime = rateLimitInfo.timestamp || 0;
                var now = Date.now();
                // Cooldown period: 1 hour
                if (now - rateLimitTime < 60 * 60 * 1000) {
                    setStatus(i18n.detectFailed || 'Location service is temporarily unavailable. Please try again later.', 'error');
                    return;
                }
            } catch (e) {
                // Invalid rate limit data, continue
            }
        }

        // Check if we're already making a request (prevent multiple simultaneous calls)
        if (window.mulopimfwc_ipRequestInProgress) {
            // Don't retry - just show error message
            setStatus(i18n.detectFailed || 'Location detection is already in progress. Please wait.', 'loading');
            return;
        }

        window.mulopimfwc_ipRequestInProgress = true;

        $.ajax({
            url: 'https://ipapi.co/jsonp/',
            dataType: 'jsonp',
            timeout: 5000
        }).done(function (data) {
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
                
                var labelParts = [data.city, data.region, data.country_name].filter(Boolean).join(', ');
                var label = labelParts ? (i18n.distanceApproximate || 'Approximate distances') + ': ' + labelParts : (i18n.distanceApproximate || 'Approximate distances');
                updateDistances(parseFloat(data.latitude), parseFloat(data.longitude), label, true);
            } else {
                setStatus(i18n.detectFailed || 'We could not detect your location. Distances may be unavailable.', 'error');
            }
        }).fail(function (xhr, status, error) {
            window.mulopimfwc_ipRequestInProgress = false;
            
            // For JSONP, xhr.status might not be available, but we can check the error message
            // Rate limit errors often show as "abort" or "timeout" status, or status 429
            var isRateLimit = (status === 'abort' || status === 'timeout' || 
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
                
                setStatus(i18n.detectFailed || 'Location service is temporarily unavailable. Please try again later.', 'error');
            } else {
                setStatus(i18n.detectFailed || 'We could not detect your location. Distances may be unavailable.', 'error');
            }
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
            alert(selectStoreLocationAlert);
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
        var cookieName = (window.mulopimfwc_locationWiseProducts && mulopimfwc_locationWiseProducts.cookieName)
            ? mulopimfwc_locationWiseProducts.cookieName
            : 'mulopimfwc_store_location';
        var cookiePath = (window.mulopimfwc_locationWiseProducts && mulopimfwc_locationWiseProducts.cookiePath)
            ? mulopimfwc_locationWiseProducts.cookiePath
            : '/';
        var sameSite = (window.mulopimfwc_locationWiseProducts && mulopimfwc_locationWiseProducts.cookieSameSite)
            ? String(mulopimfwc_locationWiseProducts.cookieSameSite).toLowerCase()
            : 'lax';
        var isSecure = (window.mulopimfwc_locationWiseProducts && typeof mulopimfwc_locationWiseProducts.cookieSecure === 'boolean')
            ? mulopimfwc_locationWiseProducts.cookieSecure
            : window.location.protocol === 'https:';
        var cookieDomain = (window.mulopimfwc_locationWiseProducts && mulopimfwc_locationWiseProducts.cookieDomain)
            ? mulopimfwc_locationWiseProducts.cookieDomain
            : '';
        var cookieString = cookieName + '=' + encodeURIComponent(slug) +
                          ';expires=' + expiryDate.toUTCString() +
                          ';path=' + cookiePath +
                          ';samesite=' + sameSite;
        if (cookieDomain) {
            cookieString += ';domain=' + cookieDomain;
        }
        if (isSecure) {
            cookieString += ';secure';
        }
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
                var clearCookie = cookieName + '=;expires=Thu, 01 Jan 1970 00:00:00 UTC;path=' + cookiePath + ';samesite=' + sameSite;
                if (cookieDomain) {
                    clearCookie += ';domain=' + cookieDomain;
                }
                if (isSecure) {
                    clearCookie += ';secure';
                }
                document.cookie = clearCookie;
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
