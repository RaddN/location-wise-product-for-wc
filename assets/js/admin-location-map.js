(function($){
    'use strict';

    function MapPicker($wrap) {
        this.$wrap = $wrap;
        this.$form = $wrap.closest('form');
        this.$lat = this.$form.find('input[name="latitude"]');
        this.$lng = this.$form.find('input[name="longitude"]');
        this.$street = this.$form.find('input[name="street_address"]');
        this.$city = this.$form.find('input[name="city"]');
        this.$state = this.$form.find('input[name="state"]');
        this.$postal = this.$form.find('input[name="postal_code"]');
        this.$country = this.$form.find('input[name="country"]');
        this.$search = $wrap.find('.mulopimfwc-location-search');
        this.$searchBtn = $wrap.find('.mulopimfwc-location-search-btn');
        this.$feedback = $wrap.find('.mulopimfwc-location-map-feedback');
        this.$mapEl = $wrap.find('.mulopimfwc-location-map');
        this.map = null;
        this.marker = null;
        this.settings = window.mulopimfwcAdminLocationMap || {};
        this.init();
    }

    MapPicker.prototype.init = function() {
        if (!this.$mapEl.length || typeof L === 'undefined') {
            return;
        }
        this.initMap();
        this.bindEvents();
    };

    MapPicker.prototype.getInputCoords = function() {
        var lat = parseFloat(this.$lat.val());
        var lng = parseFloat(this.$lng.val());
        if (Number.isFinite(lat) && Number.isFinite(lng)) {
            return [lat, lng];
        }
        return null;
    };

    MapPicker.prototype.initMap = function() {
        var coords = this.getInputCoords();
        var defaultLat = typeof this.settings.defaultLat === 'number' ? this.settings.defaultLat : 0;
        var defaultLng = typeof this.settings.defaultLng === 'number' ? this.settings.defaultLng : 0;
        var defaultZoom = typeof this.settings.defaultZoom === 'number' ? this.settings.defaultZoom : 15;
        var center = coords || [defaultLat, defaultLng];
        var zoom = coords ? defaultZoom : 2;

        this.map = L.map(this.$mapEl.get(0)).setView(center, zoom);

        var tileUrl = this.settings.tileUrl || 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
        var tileAttribution = this.settings.tileAttribution || '&copy; OpenStreetMap contributors';

        L.tileLayer(tileUrl, {
            attribution: tileAttribution
        }).addTo(this.map);

        if (coords) {
            this.marker = L.marker(coords, { draggable: true }).addTo(this.map);
            this.marker.on('dragend', this.handleMarkerDrag.bind(this));
        }

        this.map.on('click', this.handleMapClick.bind(this));

        setTimeout(function() {
            if (this.map) {
                this.map.invalidateSize();
            }
        }.bind(this), 0);
    };

    MapPicker.prototype.bindEvents = function() {
        this.$searchBtn.on('click', this.searchAddress.bind(this));
        this.$search.on('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.searchAddress();
            }
        }.bind(this));

        this.$lat.add(this.$lng).on('change', function() {
            this.syncFromInputs();
        }.bind(this));
    };

    MapPicker.prototype.syncFromInputs = function() {
        var coords = this.getInputCoords();
        if (!coords || !this.map) {
            return;
        }
        var defaultZoom = typeof this.settings.defaultZoom === 'number' ? this.settings.defaultZoom : 15;
        this.setMarker(coords[0], coords[1], false);
        this.map.setView(coords, defaultZoom);
    };

    MapPicker.prototype.handleMapClick = function(e) {
        this.setMarker(e.latlng.lat, e.latlng.lng, true);
    };

    MapPicker.prototype.handleMarkerDrag = function() {
        if (!this.marker) {
            return;
        }
        var pos = this.marker.getLatLng();
        this.setMarker(pos.lat, pos.lng, true);
    };

    MapPicker.prototype.setMarker = function(lat, lng, doReverse) {
        var latlng = L.latLng(lat, lng);
        if (!this.marker) {
            this.marker = L.marker(latlng, { draggable: true }).addTo(this.map);
            this.marker.on('dragend', this.handleMarkerDrag.bind(this));
        } else {
            this.marker.setLatLng(latlng);
        }
        this.setLatLngInputs(lat, lng);
        if (doReverse) {
            this.reverseGeocode(lat, lng);
        }
    };

    MapPicker.prototype.setLatLngInputs = function(lat, lng) {
        this.$lat.val(this.formatCoord(lat));
        this.$lng.val(this.formatCoord(lng));
    };

    MapPicker.prototype.formatCoord = function(value) {
        if (!Number.isFinite(value)) {
            return '';
        }
        return String(Math.round(value * 1000000) / 1000000);
    };

    MapPicker.prototype.reverseGeocode = function(lat, lng) {
        var baseUrl = this.settings.nominatimUrl || 'https://nominatim.openstreetmap.org';
        var self = this;

        $.ajax({
            url: baseUrl + '/reverse',
            method: 'GET',
            dataType: 'json',
            data: {
                format: 'json',
                lat: lat,
                lon: lng
            }
        }).done(function(data) {
            if (data && data.address) {
                self.applyAddress(data.address);
                self.showFeedback('');
            } else {
                self.showFeedback('Address not found for the selected location.', true);
            }
        }).fail(function() {
            self.showFeedback('Reverse geocoding failed. Please try again.', true);
        });
    };

    MapPicker.prototype.applyAddress = function(address) {
        if (!address) {
            return;
        }

        var streetParts = [];
        if (address.house_number) {
            streetParts.push(address.house_number);
        }
        if (address.road) {
            streetParts.push(address.road);
        } else if (address.pedestrian) {
            streetParts.push(address.pedestrian);
        }

        var street = streetParts.join(' ');
        var city = address.city || address.town || address.village || address.hamlet || address.county || '';
        var state = address.state || address.region || '';
        var postal = address.postcode || '';
        var country = address.country || '';

        if (street) {
            this.$street.val(street);
        }
        if (city) {
            this.$city.val(city);
        }
        if (state) {
            this.$state.val(state);
        }
        if (postal) {
            this.$postal.val(postal);
        }
        if (country) {
            this.$country.val(country);
        }
    };

    MapPicker.prototype.searchAddress = function() {
        var query = $.trim(this.$search.val());
        if (!query) {
            this.showFeedback('Please enter an address to search.', true);
            return;
        }

        var baseUrl = this.settings.nominatimUrl || 'https://nominatim.openstreetmap.org';
        var self = this;

        $.ajax({
            url: baseUrl + '/search',
            method: 'GET',
            dataType: 'json',
            data: {
                format: 'json',
                q: query,
                limit: 1,
                addressdetails: 1
            }
        }).done(function(results) {
            if (!results || !results.length) {
                self.showFeedback('No results found. Try a different search.', true);
                return;
            }

            var result = results[0];
            var lat = parseFloat(result.lat);
            var lng = parseFloat(result.lon);
            if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                self.showFeedback('Invalid coordinates from search result.', true);
                return;
            }

            var defaultZoom = typeof self.settings.defaultZoom === 'number' ? self.settings.defaultZoom : 15;
            self.map.setView([lat, lng], defaultZoom);
            self.setMarker(lat, lng, false);

            if (result.display_name) {
                self.$search.val(result.display_name);
            }

            if (result.address) {
                self.applyAddress(result.address);
                self.showFeedback('');
            } else {
                self.reverseGeocode(lat, lng);
            }
        }).fail(function() {
            self.showFeedback('Address search failed. Please try again.', true);
        });
    };

    MapPicker.prototype.showFeedback = function(message, isError) {
        if (!this.$feedback.length) {
            return;
        }
        if (!message) {
            this.$feedback.text('').hide();
            return;
        }
        this.$feedback.text(message).show();
        this.$feedback.toggleClass('is-error', !!isError);
    };

    $(function() {
        if (typeof L === 'undefined') {
            return;
        }
        $('.mulopimfwc-location-map-wrap').each(function() {
            var $wrap = $(this);
            if ($wrap.data('mulopimfwcMapInit')) {
                return;
            }
            $wrap.data('mulopimfwcMapInit', true);
            new MapPicker($wrap);
        });
    });
})(jQuery);
