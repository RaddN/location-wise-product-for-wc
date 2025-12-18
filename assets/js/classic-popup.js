jQuery(function ($) {
    var $modal = $('#lwp-store-selector-modal.lwp-classic-popup');
    if (!$modal.length) {
        return;
    }

    var $search = $modal.find('#lwp-classic-search');
    var $items = $modal.find('.lwp-classic-location');
    var $empty = $modal.find('.lwp-classic-empty').last();
    var $selected = $modal.find('#lwp-selected-store');
    var $status = $modal.find('#lwp-classic-location-status');
    var i18n = (window.mulopimfwcClassicPopupData && window.mulopimfwcClassicPopupData.i18n) || {};

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

    attemptDetection();
});
