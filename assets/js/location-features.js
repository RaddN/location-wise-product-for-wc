// Location-Wise Products - User Location Features
// Consolidated JavaScript for location functionality
if (typeof mulopimfwc_locationWiseProducts !== 'undefined' && mulopimfwc_locationWiseProducts.enableUserLocations === 'yes') {
    
    jQuery(document).ready(function($) {
        
        // ========================================
        // CONFIGURATION & GLOBAL VARIABLES
        // ========================================
        
        const LocationFeatures = {
            map: null,
            marker: null,
            selectedLat: null,
            selectedLng: null,
            selectedAddress: {},
            searchTimeout: null,
            
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
            },
            
            bindEvents: function() {
                // Location dropdown events
                $('.lwp-saved-locations').on('change', this.handleSavedLocationChange.bind(this));
                
                // Popup control events
                $('.lwp-add-location-btn, .lwp-use-current-location-btn').on('click', this.openLocationPopup.bind(this));
                $('.lwp-close-popup').on('click', this.closeLocationPopup.bind(this));
                
                // Step navigation events
                $('.lwp-continue-btn').on('click', this.handleContinueToStep2.bind(this));
                $('.lwp-back-btn').on('click', this.handleBackToStep1.bind(this));
                
                // Form submission
                $('#lwp-location-form').on('submit', this.handleLocationFormSubmit.bind(this));
                
                // Tooltip interface events
                $('#address-trigger').on('click', this.toggleTooltip.bind(this));
                $(document).on('click', this.closeTooltipOnOutsideClick.bind(this));
                $('#location-tooltip').on('click', function(e) { e.stopPropagation(); });
                
                // Search functionality
                $('#clear-address-btn').on('click', this.clearAddressInput.bind(this));
                $('#address-search-input').on('input', this.handleAddressSearch.bind(this));
                
                // Saved location management
                $('.saved-location-item').on('click', this.handleSavedLocationSelect.bind(this));
                $('.edit-location-btn').on('click', this.handleEditLocation.bind(this));
                $('.delete-location-btn').on('click', this.handleDeleteLocation.bind(this));
            },
            
            // ========================================
            // LOCATION DROPDOWN FUNCTIONALITY
            // ========================================
            
            handleSavedLocationChange: function(e) {
                const selectedLocation = $(e.target).val();
                if (selectedLocation) {
                    this.setUserLocation(selectedLocation);
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
                
                const formData = $(e.target).serialize();
                
                $.ajax({
                    url: mulopimfwc_locationWiseProducts.ajaxUrl,
                    type: 'POST',
                    data: formData + '&action=mulopimfwc_save_user_location',
                    success: this.handleLocationSaveSuccess.bind(this),
                    error: () => this.showAlert('Error saving location')
                });
            },
            
            handleLocationSaveSuccess: function(response) {
                if (response.success) {
                    this.closeLocationPopup();
                    
                    if (response.data.logged_in) {
                        this.addLocationToDropdown(response.data);
                    }
                    
                    this.setUserLocation(response.data.location_id);
                    this.reloadPage();
                } else {
                    this.showAlert(response.data.message || 'Error saving location');
                }
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
                const locationId = $item.data('location-id');
                
                if (locationId) {
                    $('.saved-location-item').removeClass('selected');
                    $item.addClass('selected');
                    
                    this.setUserLocation(locationId);
                    this.updateAddressDisplay($item);
                    $('#location-tooltip').hide();
                    this.reloadPage();
                }
            },
            
            updateAddressDisplay: function($item) {
                const locationLabel = $item.find('.location-label').text();
                const locationAddress = $item.find('.location-address').text();
                $('.address-text').text(locationLabel + ' - ' + locationAddress);
            },
            
            handleEditLocation: function(e) {
                e.stopPropagation();
                const locationId = $(e.target).data('location-id');
                // TODO: Implement edit functionality
                this.showAlert('Edit location functionality would be implemented here for location ID: ' + locationId);
            },
            
            handleDeleteLocation: function(e) {
                e.stopPropagation();
                const locationId = $(e.target).data('location-id');
                
                if (confirm('Are you sure you want to delete this location?')) {
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
                        $('.address-text').text('Set Your Location');
                        this.clearUserLocation();
                    }
                } else {
                    this.showAlert(response.data.message || 'Error deleting location');
                }
            },
            
            // ========================================
            // LOCATION DETECTION
            // ========================================
            
            attemptLocationDetection: function() {
                if (navigator.geolocation) {
                    this.tryGeolocation();
                } else {
                    this.getLocationByIP();
                }
            },
            
            tryGeolocation: function() {
                navigator.geolocation.getCurrentPosition(
                    (position) => this.handleGeolocationSuccess(position),
                    () => this.tryLowAccuracyGeolocation(),
                    this.config.geolocation.highAccuracy
                );
            },
            
            tryLowAccuracyGeolocation: function() {
                navigator.geolocation.getCurrentPosition(
                    (position) => this.handleGeolocationSuccess(position),
                    () => this.getLocationByIP(),
                    this.config.geolocation.lowAccuracy
                );
            },
            
            handleGeolocationSuccess: function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                this.setMapLocation(lat, lng);
                this.reverseGeocode(lat, lng);
            },
            
            getLocationByIP: function() {
                this.tryJSONPServices();
            },
            
            tryJSONPServices: function() {
                $.ajax({
                    url: 'https://ip-api.com/json/',
                    method: 'GET',
                    dataType: 'jsonp',
                    timeout: 5000,
                    success: (data) => {
                        if (data.lat && data.lon) {
                            this.setMapLocation(data.lat, data.lon);
                            this.reverseGeocode(data.lat, data.lon);
                        } else {
                            this.getLocationByTimezone();
                        }
                    },
                    error: () => this.getLocationByTimezone()
                });
            },
            
            getLocationByTimezone: function() {
                try {
                    const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
                    const coords = this.getTimezoneCoordinates(timezone);
                    this.setMapLocation(coords[0], coords[1]);
                } catch (error) {
                    this.setMapLocation(this.config.defaultLocation.lat, this.config.defaultLocation.lng);
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
                this.map = L.map('lwp-location-map').setView([
                    this.config.defaultLocation.lat,
                    this.config.defaultLocation.lng
                ], 13);
                
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                }).addTo(this.map);
                
                this.marker = L.marker([
                    this.config.defaultLocation.lat,
                    this.config.defaultLocation.lng
                ], {
                    draggable: true
                }).addTo(this.map);
                
                this.marker.on('dragend', this.handleMarkerDrag.bind(this));
                this.map.on('click', this.handleMapClick.bind(this));
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
            
            reverseGeocode: function(lat, lng) {
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
                    },
                    error: () => {
                        console.warn('Reverse geocoding failed');
                    }
                });
            },
            
            searchAddresses: function(query) {
                const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=${this.config.search.maxResults}&addressdetails=1`;
                
                $.ajax({
                    url: url,
                    method: 'GET',
                    dataType: 'json',
                    success: this.displaySearchResults.bind(this),
                    error: () => console.warn('Address search failed')
                });
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
            },
            
            // ========================================
            // UTILITY FUNCTIONS
            // ========================================
            
            setUserLocation: function(locationId) {
                document.cookie = `mulopimfwc_user_location=${locationId};path=/`;
            },
            
            clearUserLocation: function() {
                document.cookie = 'mulopimfwc_user_location=;path=/;expires=Thu, 01 Jan 1970 00:00:00 GMT';
            },
            
            reloadPage: function() {
                window.location.reload();
            },
            
            showAlert: function(message) {
                alert(message);
            },
            
            injectStyles: function() {
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
                
                if (!$('#lwp-search-results-styles').length) {
                    $('head').append(css);
                }
            }
        };
        
        // ========================================
        // INITIALIZE THE APPLICATION
        // ========================================
        
        LocationFeatures.init();
        
        // Expose to global scope for debugging (remove in production)
        window.LocationFeatures = LocationFeatures;
    });
}