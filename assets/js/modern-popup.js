jQuery(function ($) {
    'use strict';

    const locationI18n = (window.mulopimfwc_locationWiseProducts && mulopimfwc_locationWiseProducts.i18n) || {};
    const selectStoreLocationAlert = locationI18n.selectStoreLocation || 'Please select a store location.';

    // Function to initialize modern popup modals
    function initializeModernPopups() {
        // Handle all modern popup modals (including instance-specific ones)
        var $modals = $('#lwp-store-selector-modal.lwp-modern-popup, [id^="lwp-store-selector-modal-"].lwp-modern-popup').not('.mulopimfwc-initialized');
        if (!$modals.length) {
            return;
        }
        
        // Process each modal
        $modals.each(function() {
            var $modal = $(this);
            // Always initialize if modal exists, data will be read from attribute or global
            try {
                initModernPopup($modal);
                $modal.addClass('mulopimfwc-initialized');
            } catch (e) {
                console.warn('Error initializing modern popup:', e);
            }
        });
    }
    
    // Initialize on page load
    initializeModernPopups();
    
    // Re-initialize when modals are added dynamically (e.g., via shortcode)
    $(document).on('DOMNodeInserted', function(e) {
        var $target = $(e.target);
        var $newModals = $target.find('#lwp-store-selector-modal.lwp-modern-popup, [id^="lwp-store-selector-modal-"].lwp-modern-popup').add(
            $target.filter('#lwp-store-selector-modal.lwp-modern-popup, [id^="lwp-store-selector-modal-"].lwp-modern-popup')
        ).not('.mulopimfwc-initialized');
        if ($newModals.length) {
            setTimeout(initializeModernPopups, 100);
        }
    });
    
    // Also check on ready state changes
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeModernPopups);
    }
    
    function initModernPopup($modal) {
        // Skip if already initialized
        if ($modal.hasClass('mulopimfwc-initialized')) {
            return;
        }
        
        // Get data from modal data attribute or fallback to global window object (for backward compatibility)
        var popupDataAttr = $modal.attr('data-popup-data');
        var data = {};
        
        if (popupDataAttr) {
            try {
                data = typeof popupDataAttr === 'string' ? JSON.parse(popupDataAttr) : popupDataAttr;
            } catch (e) {
                console.warn('Error parsing popup data:', e);
                data = window.mulopimfwcModernPopupData || {};
            }
        } else {
            data = window.mulopimfwcModernPopupData || {};
        }
        var locations = Array.isArray(data.locations) ? data.locations : [];
        var i18n = data.i18n || {};
        var variant = data.variant || ($modal.hasClass('lwp-modern-popup--simple') ? 'simple' : 'modern');
        var cookieDays = parseInt(data.cookieExpiryDays, 10);
        if (!Number.isFinite(cookieDays) || cookieDays < 1) {
            cookieDays = 30;
        }

        var $input = $modal.find('#lwp-modern-location-search');
        var $status = $modal.find('#lwp-modern-status');
        var $suggestions = $modal.find('#lwp-modern-suggestions');
        var $featured = $modal.find('#lwp-modern-featured');
        var $list = $modal.find('#lwp-modern-list');
        var $origin = $modal.find('#lwp-modern-origin');
        var $detect = $modal.find('#lwp-modern-detect');
        var $searchBtn = $modal.find('#lwp-modern-search-btn');
        var activeRequest = null;

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function setStatus(message, state) {
        $status.text(message || '');
        if (state) {
            $status.attr('data-state', state);
        } else {
            $status.removeAttr('data-state');
        }
    }

    function setPluginCookie(name, value) {
        var days = cookieDays;
        var cookieName = name;
        var options = {
            path: '/',
            sameSite: 'lax',
            secure: window.location.protocol === 'https:'
        };

        if (window.mulopimfwc_locationWiseProducts) {
            if (mulopimfwc_locationWiseProducts.cookie_expiry) {
                var override = parseInt(mulopimfwc_locationWiseProducts.cookie_expiry, 10);
                if (Number.isFinite(override) && override > 0) {
                    days = override;
                }
            }
            if (mulopimfwc_locationWiseProducts.cookieName) {
                cookieName = mulopimfwc_locationWiseProducts.cookieName;
            }
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

        var expiryDate = new Date(Date.now() + days * 24 * 60 * 60 * 1000);
        var encodedValue = encodeURIComponent(String(value));
        var cookieString = cookieName + '=' + encodedValue + ';expires=' + expiryDate.toUTCString() + ';path=' + options.path + ';samesite=' + options.sameSite;
        if (options.domain) {
            cookieString += ';domain=' + options.domain;
        }
        if (options.secure) {
            cookieString += ';secure';
        }
        document.cookie = cookieString;
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

    function buildAddressLines(loc) {
        var lines = [];
        if (loc.address) {
            if (loc.address.street) {
                lines.push(loc.address.street);
            }
            var cityLine = [loc.address.city, loc.address.state, loc.address.postal].filter(Boolean).join(', ');
            if (cityLine) {
                lines.push(cityLine);
            }
            if (loc.address.country) {
                lines.push(loc.address.country);
            }
        }
        return lines;
    }

    function buildCard(loc, featured, index) {
        var baseClass = featured ? 'lwp-modern-card lwp-modern-card--featured' : 'lwp-modern-card';
        var cardClass = variant === 'simple' ? baseClass + ' lwp-modern-card--simple' : baseClass;
        var distanceText = formatDistance(loc.distance);
        var distanceHtml = distanceText
            ? '<span class="lwp-modern-chip">' + escapeHtml(distanceText + ' ' + (i18n.distanceAway || 'away')) + '</span>'
            : '';
        var statusClass = loc.status && loc.status.open ? 'open' : 'closed';
        var statusLabel = loc.status && loc.status.label ? loc.status.label : '';
        var statusHtml = statusLabel
            ? '<span class="lwp-modern-status-badge lwp-modern-status-badge--' + statusClass + '">' + escapeHtml(statusLabel) + '</span>'
            : '';
        var nextChange = loc.status && loc.status.nextChange ? loc.status.nextChange : '';
        var hoursToday = loc.hoursToday || '';
        var addressLines = buildAddressLines(loc);
        var addressHtml = addressLines.length
            ? addressLines.map(function (line) {
                return escapeHtml(line);
            }).join('<br>')
            : escapeHtml(i18n.addressUnavailable || 'Address unavailable');
        var logoHtml = '';
        var safeName = escapeHtml(loc.name || '');
        var initial = (loc.name || '?').trim().charAt(0).toUpperCase();
        if (loc.logo) {
            logoHtml = '<div class="lwp-modern-card__logo"><img src="' + escapeHtml(loc.logo) + '" alt="' + safeName + ' logo" loading="lazy"></div>';
        } else {
            logoHtml = '<div class="lwp-modern-card__logo lwp-modern-card__logo--placeholder">' + escapeHtml(initial) + '</div>';
        }

        var html = '<article class="' + cardClass + '" data-slug="' + escapeHtml(loc.slug || '') + '" style="--stagger:' + index + ';">';

        if (variant === 'simple') {
            html += '<button type="button" class="lwp-modern-card__select lwp-modern-location-select" data-slug="' +
                escapeHtml(loc.slug || '') + '" aria-label="' + escapeHtml(i18n.selectStore || 'Select this store') + '">';
            html += '<div class="lwp-modern-card__header">';
            html += logoHtml;
            html += '<div class="lwp-modern-card__headline">';
            html += '<div class="lwp-modern-card__title-row">';
            html += '<h5 class="lwp-modern-card__title">' + safeName + '</h5>';
            html += statusHtml;
            html += '</div>';
            html += '<div class="lwp-modern-card__meta-row">' + distanceHtml + '</div>';
            html += '</div>';
            html += '</div>';
            html += '</button>';
            html += '</article>';
            return html;
        }

        html += '<div class="lwp-modern-card__header">';
        html += logoHtml;
        html += '<div class="lwp-modern-card__headline">';
        html += '<div class="lwp-modern-card__title-row">';
        html += '<h5 class="lwp-modern-card__title">' + safeName + '</h5>';
        html += statusHtml;
        html += '</div>';
        html += '<div class="lwp-modern-card__meta-row">' + distanceHtml + '</div>';
        html += '</div>';
        html += '</div>';
        html += '<div class="lwp-modern-card__body">';
        html += '<div class="lwp-modern-card__address">' + addressHtml + '</div>';
        if (hoursToday) {
            html += '<div class="lwp-modern-card__meta"><span class="lwp-modern-card__meta-label">' +
                escapeHtml(i18n.hoursToday || 'Hours today') + ':</span> ' + escapeHtml(hoursToday) + '</div>';
        }
        if (nextChange) {
            html += '<div class="lwp-modern-card__meta">' + escapeHtml(nextChange) + '</div>';
        }
        html += '</div>';
        html += '<div class="lwp-modern-card__actions">';
        html += '<button type="button" class="lwp-modern-location-select" data-slug="' + escapeHtml(loc.slug || '') + '">' +
            escapeHtml(i18n.selectStore || 'Select this store') + '</button>';
        html += '</div>';
        html += '</article>';

        return html;
    }

    function computeSortedLocations(lat, lng) {
        var withDistances = locations.map(function (loc) {
            var distance = null;
            if (Number.isFinite(lat) && Number.isFinite(lng) && Number.isFinite(loc.lat) && Number.isFinite(loc.lng)) {
                distance = haversineKm(lat, lng, loc.lat, loc.lng);
            }
            return $.extend({}, loc, { distance: distance });
        });

        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            return withDistances;
        }

        return withDistances.sort(function (a, b) {
            if (a.distance === null && b.distance === null) {
                return 0;
            }
            if (a.distance === null) {
                return 1;
            }
            if (b.distance === null) {
                return -1;
            }
            return a.distance - b.distance;
        });
    }

    function renderLocations(lat, lng, originLabel) {
        if (!locations.length) {
            $featured.html('<div class="lwp-modern-empty">' + escapeHtml(i18n.noLocations || 'No store locations found.') + '</div>');
            $list.empty();
            return;
        }

        var sorted = computeSortedLocations(lat, lng);
        if (sorted[0] && sorted[0].slug) {
            $modal.find('#lwp-selected-store').val(sorted[0].slug);
        }

        $featured.html(sorted[0] ? buildCard(sorted[0], true, 0) : '');
        if (sorted.length > 1) {
            var listHtml = '';
            for (var i = 1; i < sorted.length; i++) {
                listHtml += buildCard(sorted[i], false, i);
            }
            $list.html(listHtml);
        } else {
            $list.html('<div class="lwp-modern-empty">' + escapeHtml(i18n.noLocations || 'No store locations found.') + '</div>');
        }

        if (originLabel) {
            $origin.text(originLabel);
            $origin.show();
        } else {
            $origin.text('');
            $origin.hide();
        }
    }

    function updateFromCoordinates(lat, lng, label) {
        renderLocations(lat, lng, label);
        if (label) {
            $input.val(label);
        }
        setStatus(i18n.showingNear || '', 'ready');
    }

    function reverseGeocode(lat, lng, callback) {
        $.ajax({
            url: 'https://nominatim.openstreetmap.org/reverse',
            method: 'GET',
            dataType: 'json',
            data: {
                format: 'json',
                lat: lat,
                lon: lng
            }
        }).done(function (data) {
            if (data && data.display_name) {
                callback(data.display_name);
            } else {
                callback('');
            }
        }).fail(function () {
            callback('');
        });
    }

    function attemptLocationDetection() {
        if (!locations.length) {
            setStatus(i18n.noLocations || 'No store locations found.', 'error');
            return;
        }

        setStatus(i18n.detecting || 'Detecting your location...', 'loading');

        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function (position) {
                    var lat = position.coords.latitude;
                    var lng = position.coords.longitude;
                    updateFromCoordinates(lat, lng, i18n.nearLabel || 'Near');
                    reverseGeocode(lat, lng, function (label) {
                        if (label) {
                            $input.val(label);
                            $origin.text(label).show();
                        }
                    });
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
                    var label = labelParts || (i18n.approximate || 'Approximate location');
                    updateFromCoordinates(parseFloat(data.latitude), parseFloat(data.longitude), label);
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
                    setStatus(i18n.detectFailed || 'Location service is temporarily unavailable. Please try again later or search for a place.', 'error');
                    renderLocations(null, null, '');
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
                var label = labelParts || (i18n.approximate || 'Approximate location');
                updateFromCoordinates(parseFloat(data.latitude), parseFloat(data.longitude), label);
            } else {
                setStatus(i18n.detectFailed || 'We could not detect your location. Search for a place instead.', 'error');
                renderLocations(null, null, '');
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
                
                setStatus(i18n.detectFailed || 'Location service is temporarily unavailable. Please try again later or search for a place.', 'error');
                renderLocations(null, null, '');
            } else {
                setStatus(i18n.detectFailed || 'We could not detect your location. Search for a place instead.', 'error');
                renderLocations(null, null, '');
            }
        });
    }

    function renderSuggestions(results) {
        if (!results || !results.length) {
            $suggestions.hide().empty();
            return;
        }
        var html = '';
        results.forEach(function (result) {
            if (!result || !result.display_name) {
                return;
            }
            html += '<div class="lwp-modern-suggestion" data-lat="' + escapeHtml(result.lat) +
                '" data-lng="' + escapeHtml(result.lon) + '" data-label="' + escapeHtml(result.display_name) + '">' +
                escapeHtml(result.display_name) + '</div>';
        });
        $suggestions.html(html).show();
    }

    function searchByQuery(query) {
        if (!query) {
            setStatus(i18n.noResults || 'No matches found. Try a more specific address.', 'error');
            return;
        }

        if (activeRequest && activeRequest.abort) {
            activeRequest.abort();
        }

        setStatus(i18n.searching || 'Searching...', 'loading');
        activeRequest = $.ajax({
            url: 'https://nominatim.openstreetmap.org/search',
            method: 'GET',
            dataType: 'json',
            data: {
                format: 'json',
                q: query,
                limit: 5,
                addressdetails: 1
            }
        }).done(function (results) {
            if (!results || !results.length) {
                setStatus(i18n.noResults || 'No matches found. Try a more specific address.', 'error');
                renderSuggestions([]);
                return;
            }
            renderSuggestions(results);
            var first = results[0];
            var lat = parseFloat(first.lat);
            var lng = parseFloat(first.lon);
            if (Number.isFinite(lat) && Number.isFinite(lng)) {
                updateFromCoordinates(lat, lng, first.display_name || '');
            }
        }).fail(function () {
            setStatus(i18n.searchFailed || 'Search failed. Try again.', 'error');
        });
    }

        $detect.on('click', function () {
            attemptLocationDetection();
        });

        $searchBtn.on('click', function () {
            searchByQuery($input.val().trim());
        });

        $input.on('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                searchByQuery($input.val().trim());
            }
        });

        $modal.on('click', '.lwp-modern-location-select', function (event) {
            event.preventDefault();

            var $button = $(this);
            var slug = $button.data('slug');
            if (!slug) {
                alert(selectStoreLocationAlert);
                return;
            }

            if (window.mulopimfwcLocationSwitch && typeof window.mulopimfwcLocationSwitch.selectLocation === 'function') {
                var selectedLabel = $.trim(
                    $button.data('label') ||
                    $button.closest('[data-slug], .lwp-modern-location-card, .lwp-modern-location-item, .lwp-modern-popup__location').find('h3, h4, .lwp-modern-location-name').first().text()
                );

                window.mulopimfwcLocationSwitch.selectLocation(slug, {
                    $modal: $modal,
                    locationLabel: selectedLabel,
                    emptySelectionMessage: selectStoreLocationAlert
                });
                return;
            }

            setPluginCookie('mulopimfwc_store_location', slug);
            $modal.hide();
            $('body').removeClass('mulopimfwc-modal-open');
            window.location.href = window.location.href.split('?')[0];
        });

        $modal.on('click', '.lwp-modern-suggestion', function () {
            var $item = $(this);
            var lat = parseFloat($item.data('lat'));
            var lng = parseFloat($item.data('lng'));
            var label = $item.data('label') || '';
            $suggestions.hide();
            if (Number.isFinite(lat) && Number.isFinite(lng)) {
                updateFromCoordinates(lat, lng, label);
            }
        });

        $(document).on('click', function (event) {
            if (!$(event.target).closest('.lwp-modern-popup__search').length) {
                $suggestions.hide();
            }
        });

        renderLocations(null, null, '');
        attemptLocationDetection();
    }
});
