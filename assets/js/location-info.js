/**
 * Enhanced Frontend Location Information JavaScript
 * Multi Location Product & Inventory Management for WooCommerce
 */

(function ($) {
    'use strict';

    const MulopimfwcLocationInfo = {

        maps: {},
        tabbedMap: null,
        tabbedMapMarker: null,
        currentTab: null,
        lightboxImages: [],
        lightboxIndex: 0,
        overlayVisible: true,

        /**
         * Validate latitude/longitude pair
         */
        isValidLatLng: function (lat, lng) {
            // Check if both are finite numbers and within valid ranges
            if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                return false;
            }
            // Validate latitude range: -90 to 90
            if (lat < -90 || lat > 90) {
                return false;
            }
            // Validate longitude range: -180 to 180
            if (lng < -180 || lng > 180) {
                return false;
            }
            return true;
        },

        /**
         * Safely parse float from data attribute, returning NaN if invalid
         */
        parseCoordinate: function (value) {
            // Handle null, undefined, empty string, or falsy values
            if (value === null || value === undefined || value === '' || value === false) {
                return NaN;
            }
            
            // Convert to string first to handle edge cases
            const strValue = String(value).trim();
            
            // Check for empty string after trimming
            if (strValue === '' || strValue === 'null' || strValue === 'undefined' || strValue === 'NaN') {
                return NaN;
            }
            
            // Parse as float
            const parsed = parseFloat(strValue);
            
            // Return NaN if not a finite number
            return Number.isFinite(parsed) ? parsed : NaN;
        },

        /**
         * Find the first tab with valid coordinates within a scope
         */
        findFirstValidTab: function ($scope) {
            const self = this;
            const $tabs = $scope ? $scope.find('.mulopimfwc-tab-item') : $('.mulopimfwc-tab-item');
            let $validTab = $();

            $tabs.each(function () {
                const $tab = $(this);
                
                // Skip if data attributes don't exist
                if (!$tab.attr('data-lat') || !$tab.attr('data-lng')) {
                    return; // Continue to next tab
                }
                
                const lat = self.parseCoordinate($tab.data('lat'));
                const lng = self.parseCoordinate($tab.data('lng'));

                if (self.isValidLatLng(lat, lng)) {
                    $validTab = $tab;
                    return false;
                }
            });

            return $validTab;
        },

        /**
         * Initialize
         */
        init: function () {
            this.initMaps();
            this.initTabbedInterface();
            this.initShortcodeMaps();
            this.initSearch();
            this.initGallery();
            this.bindEvents();
        },

        /**
         * Initialize all standalone maps
         */
        initMaps: function () {
            const self = this;

            $('.mulopimfwc-location-map').not('#mulopimfwc-tabbed-map').each(function () {
                const $map = $(this);
                const mapId = $map.attr('id');
                
                // Check if data attributes exist before parsing
                if (!$map.attr('data-lat') || !$map.attr('data-lng')) {
                    return; // Skip if data attributes are missing
                }
                
                const lat = self.parseCoordinate($map.data('lat'));
                const lng = self.parseCoordinate($map.data('lng'));
                const name = $map.data('name');
                const address = $map.data('address');

                if (!mapId || !self.isValidLatLng(lat, lng)) {
                    return;
                }

                // Skip if map is already initialized
                if (self.maps[mapId] || ($map[0] && $map[0]._leaflet_id)) {
                    return;
                }

                $map.addClass('loading');

                self.waitForLeaflet(function () {
                    self.createMap(mapId, lat, lng, name, address);
                    $map.removeClass('loading');
                });
            });
        },

        /**
         * Initialize search functionality
         */
        initSearch: function () {
            const self = this;

            $('.mulopimfwc-location-search').on('input', function () {
                const $search = $(this);
                const query = $search.val().toLowerCase().trim();
                const targetId = $search.data('target');
                const $container = $('#' + targetId);

                if (!$container.length) {
                    return;
                }

                self.performSearch(query, $container);
            });

            // Clear search on escape
            $('.mulopimfwc-location-search').on('keydown', function (e) {
                if (e.key === 'Escape') {
                    $(this).val('');
                    $(this).trigger('input');
                }
            });
        },


        /**
         * Perform search filtering
         */
        performSearch: function (query, $container) {
            const $items = $container.find('.mulopimfwc-tab-item, .mulopimfwc-grid-location-item');
            const $noResults = $container.find('.mulopimfwc-no-results');
            let visibleCount = 0;

            if (query === '') {
                // Show all items
                $items.show().removeClass('search-hidden');
                $noResults.hide();
                return;
            }

            // Filter items
            $items.each(function () {
                const $item = $(this);
                const searchData = $item.data('search') || '';

                if (searchData.includes(query)) {
                    $item.show().removeClass('search-hidden');
                    visibleCount++;
                } else {
                    $item.hide().addClass('search-hidden');
                }
            });

            // Show/hide no results message
            if (visibleCount === 0) {
                $noResults.fadeIn(200);
            } else {
                $noResults.fadeOut(200);
            }

            // If first visible tab is not active, activate it
            if ($container.hasClass('mulopimfwc-locations-tabs-container')) {
                const $visibleTabs = $container.find('.mulopimfwc-tab-item:visible');
                const $activeTab = $container.find('.mulopimfwc-tab-item.active');

                if ($activeTab.hasClass('search-hidden') && $visibleTabs.length > 0) {
                    $visibleTabs.first().trigger('click');
                }
            }
        },
        /**
         * Initialize shortcode maps
         */
        initShortcodeMaps: function () {
            const self = this;

            // Find all shortcode tabbed map containers
            $('[id^="mulopimfwc-tabbed-map-shortcode-"]').each(function () {
                const $map = $(this);
                const mapId = $map.attr('id');
                const $container = $map.closest('.mulopimfwc-locations-tabs-container');
                const $activeTab = $container.find('.mulopimfwc-tab-item.active').first();
                
                // Check if active tab has valid coordinates
                let $firstValidTab = $();
                if ($activeTab.length && $activeTab.attr('data-lat') && $activeTab.attr('data-lng')) {
                    const activeLat = self.parseCoordinate($activeTab.data('lat'));
                    const activeLng = self.parseCoordinate($activeTab.data('lng'));
                    if (self.isValidLatLng(activeLat, activeLng)) {
                        $firstValidTab = $activeTab;
                    }
                }
                
                // If active tab doesn't have valid coordinates, find first valid tab
                if (!$firstValidTab.length) {
                    $firstValidTab = self.findFirstValidTab($container);
                }

                if ($firstValidTab.length) {
                    const lat = self.parseCoordinate($firstValidTab.data('lat'));
                    const lng = self.parseCoordinate($firstValidTab.data('lng'));
                    const name = $firstValidTab.data('name');
                    const address = $firstValidTab.data('address');

                    if (!$activeTab.is($firstValidTab)) {
                        $container.find('.mulopimfwc-tab-item').removeClass('active');
                        $firstValidTab.addClass('active');
                    }

                    self.waitForLeaflet(function () {
                        self.createShortcodeMap(mapId, lat, lng, name, address, $container);
                    });
                }
            });
        },

        /**
         * Create a shortcode map instance
         */
        createShortcodeMap: function (mapId, lat, lng, name, address, $container) {
            const self = this;

            if (!mapId || !self.isValidLatLng(lat, lng)) {
                return;
            }

            // Check if map already exists for this container
            if (this.maps[mapId]) {
                // Map already exists, just update it if needed
                const existingMapData = this.maps[mapId];
                const existingMap = existingMapData.map || existingMapData;
                if (existingMap && typeof existingMap.setView === 'function' && self.isValidLatLng(lat, lng)) {
                    // Final safety check before setView
                    if (Number.isFinite(lat) && Number.isFinite(lng)) {
                        existingMap.setView([lat, lng], existingMap.getZoom());
                    }
                    setTimeout(() => {
                        if (existingMap && typeof existingMap.invalidateSize === 'function') {
                            existingMap.invalidateSize();
                        }
                    }, 100);
                }
                return;
            }

            // Check if container already has a Leaflet map instance
            const container = document.getElementById(mapId);
            if (container && container._leaflet_id) {
                // Container already has a map, remove it first
                try {
                    if (this.maps[mapId]) {
                        const existingMapData = this.maps[mapId];
                        const existingMap = existingMapData.map || existingMapData;
                        if (existingMap && typeof existingMap.remove === 'function') {
                            existingMap.remove();
                        }
                    }
                    delete container._leaflet_id;
                } catch (e) {
                    console.warn('Error removing existing shortcode map:', e);
                }
            }

            try {
                // Final safety check before creating map
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                    console.warn('Invalid coordinates in createShortcodeMap, cannot create map:', { mapId, lat, lng });
                    return;
                }

                const map = L.map(mapId, {
                    center: [lat, lng],
                    zoom: mulopimfwcLocationInfo.defaultMapZoom,
                    scrollWheelZoom: false,
                    zoomControl: true
                });

                L.tileLayer(mulopimfwcLocationInfo.mapTileUrl, {
                    attribution: mulopimfwcLocationInfo.mapAttribution,
                    maxZoom: 19
                }).addTo(map);

                // Add marker
                const marker = this.createTabbedMarker(lat, lng, name, address);
                if (marker) {
                    marker.addTo(map);
                }

                // Store map and marker with initialization flag
                this.maps[mapId] = {
                    map: map,
                    marker: marker || null,
                    initialized: true,
                    hasValidCenter: true, // Track if map has a valid center
                    isReady: false // Track if map is ready for animations
                };

                // Ensure map is properly sized immediately after creation
                // This is critical for maps in hidden containers or tabs
                const ensureMapReady = () => {
                    const containerEl = document.getElementById(mapId);
                    if (containerEl) {
                        const rect = containerEl.getBoundingClientRect();
                        const isVisible = rect.width > 0 && rect.height > 0;
                        
                        if (isVisible && map && typeof map.invalidateSize === 'function') {
                            map.invalidateSize();
                            // Mark as ready after invalidate
                            setTimeout(() => {
                                if (this.maps[mapId]) {
                                    this.maps[mapId].isReady = true;
                                }
                            }, 100);
                        } else if (!isVisible) {
                            // Container not visible yet, try again later
                            setTimeout(ensureMapReady, 200);
                        }
                    }
                };

                // Try to ensure map is ready immediately
                setTimeout(ensureMapReady, 50);
                
                // Also try after a longer delay to catch cases where container becomes visible later
                setTimeout(ensureMapReady, 500);

                // Bind tab clicks for this container
                $container.find('.mulopimfwc-tab-item').on('click', function () {
                    const $tab = $(this);
                    
                    // Check if data attributes exist before parsing
                    if (!$tab.attr('data-lat') || !$tab.attr('data-lng')) {
                        // No coordinates available, skip map update
                        $container.find('.mulopimfwc-tab-item').removeClass('active');
                        $tab.addClass('active');
                        return;
                    }
                    
                    const tabLat = self.parseCoordinate($tab.data('lat'));
                    const tabLng = self.parseCoordinate($tab.data('lng'));
                    const tabName = $tab.data('name');
                    const tabAddress = $tab.data('address');
                    const locationId = $tab.data('tab').replace('location-', '');

                    // Update active state
                    $container.find('.mulopimfwc-tab-item').removeClass('active');
                    $tab.addClass('active');

                    // Update map only if coordinates are valid
                    if (self.isValidLatLng(tabLat, tabLng)) {
                        self.updateShortcodeMap(mapId, tabLat, tabLng, tabName, tabAddress);
                    }

                    // Update overlay
                    self.updateOverlayContent(locationId, $container);
                });

                // Invalidate size after delay
                setTimeout(() => {
                    if (map && typeof map.invalidateSize === 'function') {
                        map.invalidateSize();
                    }
                }, 100);

            } catch (error) {
                console.error('Error creating shortcode map:', error);
            }
        },

        /**
         * Update shortcode map location
         */
        updateShortcodeMap: function (mapId, lat, lng, name, address) {
            const mapData = this.maps[mapId];

            if (!mapData || !mapData.map) {
                return;
            }

            const map = mapData.map;

            // Ensure map is fully initialized
            if (!map || typeof map.getCenter !== 'function' || typeof map.setView !== 'function') {
                console.warn('Map not fully initialized:', mapId);
                return;
            }

            // If coordinates are invalid, remove marker and keep current view
            if (!this.isValidLatLng(lat, lng)) {
                if (mapData.marker) {
                    map.removeLayer(mapData.marker);
                    mapData.marker = null;
                }
                return;
            }

            // Double-check coordinates are valid numbers before using them
            // This prevents any edge cases where NaN might slip through
            const safeLat = Number.isFinite(lat) ? lat : NaN;
            const safeLng = Number.isFinite(lng) ? lng : NaN;
            
            if (!Number.isFinite(safeLat) || !Number.isFinite(safeLng)) {
                console.warn('Invalid coordinates in updateShortcodeMap:', { lat, lng, mapId });
                return;
            }

            // Get animation settings
            const animationType = mulopimfwcLocationInfo.mapAnimationType || 'setview';
            const animationDuration = mulopimfwcLocationInfo.mapAnimationDuration || 1.5;

            // Check if map is ready (properly sized and initialized)
            const mapIsReady = mapData.isReady !== false;
            
            // Ensure map is properly sized before attempting animations
            if (!mapIsReady) {
                // Map not ready yet, invalidate size and wait
                try {
                    if (map && typeof map.invalidateSize === 'function') {
                        map.invalidateSize();
                    }
                    // Mark as ready after invalidate
                    mapData.isReady = true;
                } catch (e) {
                    console.warn('Error invalidating map size:', e);
                }
            }

            // Check if map container is visible and has dimensions
            const container = document.getElementById(mapId);
            let containerIsReady = true;
            if (container) {
                const rect = container.getBoundingClientRect();
                const isVisible = rect.width > 0 && rect.height > 0;
                if (!isVisible) {
                    // Container not visible or has no dimensions, invalidate size
                    try {
                        if (map && typeof map.invalidateSize === 'function') {
                            map.invalidateSize();
                        }
                    } catch (e) {
                        // Ignore
                    }
                    containerIsReady = false;
                }
            }

            // Check if map state is valid for animation
            // For flyTo, we need to be extra careful as it uses the current center
            let canUseFlyTo = false;
            let canUseAnimatedSetView = false;
            
            // Check if map has been marked as having a valid center
            const mapHasValidCenter = mapData.hasValidCenter !== false;
            
            if (animationType !== 'none' && animationType !== 'setview_instant' && mapHasValidCenter && mapIsReady && containerIsReady) {
                try {
                    const currentCenter = map.getCenter();
                    const currentZoom = map.getZoom();
                    
                    // Validate center exists and is finite
                    if (currentCenter && 
                        Number.isFinite(currentCenter.lat) && 
                        Number.isFinite(currentCenter.lng) &&
                        Number.isFinite(currentZoom) &&
                        currentCenter.lat >= -90 && currentCenter.lat <= 90 && 
                        currentCenter.lng >= -180 && currentCenter.lng <= 180 &&
                        currentZoom >= 0 && currentZoom <= 20) {
                        
                        // Additional string check to catch any NaN values
                        const centerLatStr = String(currentCenter.lat);
                        const centerLngStr = String(currentCenter.lng);
                        if (centerLatStr !== 'NaN' && centerLngStr !== 'NaN' && 
                            !centerLatStr.includes('NaN') && !centerLngStr.includes('NaN')) {
                            
                            // For flyTo, we need to be even more strict
                            // Try to access the internal projection to ensure it's valid
                            try {
                                // Test if we can actually project the current center
                                const testProjection = map.options.crs.latLngToPoint(currentCenter, map.getZoom());
                                if (testProjection && Number.isFinite(testProjection.x) && Number.isFinite(testProjection.y)) {
                                    // Additional test: try to unproject back to ensure round-trip works
                                    // This ensures the projection system is fully functional
                                    const testUnproject = map.options.crs.pointToLatLng(testProjection, map.getZoom());
                                    if (testUnproject && 
                                        Number.isFinite(testUnproject.lat) && 
                                        Number.isFinite(testUnproject.lng) &&
                                        testUnproject.lat >= -90 && testUnproject.lat <= 90 &&
                                        testUnproject.lng >= -180 && testUnproject.lng <= 180) {
                                        canUseFlyTo = true;
                                        canUseAnimatedSetView = true;
                                    }
                                }
                            } catch (projError) {
                                // Projection test failed, don't use flyTo
                                canUseFlyTo = false;
                                canUseAnimatedSetView = true; // setView is safer
                            }
                        }
                    }
                } catch (e) {
                    // If we can't verify state, don't use animation
                    canUseFlyTo = false;
                    canUseAnimatedSetView = false;
                    mapData.hasValidCenter = false; // Mark as invalid
                }
            }

            // Before attempting any animation, ensure map is properly sized
            // This is critical for maps in hidden containers or tabs
            if (!containerIsReady || !mapIsReady) {
                // Map not ready, use instant setView to ensure it's set correctly
                try {
                    map.setView([safeLat, safeLng], 15, { animate: false });
                    mapData.hasValidCenter = true;
                    mapData.isReady = true;
                    // Invalidate size to ensure proper rendering
                    setTimeout(() => {
                        if (map && typeof map.invalidateSize === 'function') {
                            map.invalidateSize();
                        }
                    }, 50);
                } catch (e) {
                    console.warn('Error setting initial map view:', e);
                }
                return; // Skip animation for now, will work on next update
            }

            // Update map view based on animation type
            try {
                if (animationType === 'flyto' && canUseFlyTo) {
                    // Before using flyTo, ensure map center is in a valid state
                    // If map center was previously invalid, set it first without animation
                    if (!mapHasValidCenter) {
                        map.setView([safeLat, safeLng], 15, { animate: false });
                        mapData.hasValidCenter = true;
                        // Wait a bit for the map to settle, then use flyTo on next update
                        return;
                    }
                    
                    // Double-check: Try to get and validate the current center one more time
                    let centerIsValid = false;
                    try {
                        const finalCenterCheck = map.getCenter();
                        if (finalCenterCheck && 
                            Number.isFinite(finalCenterCheck.lat) && 
                            Number.isFinite(finalCenterCheck.lng) &&
                            finalCenterCheck.lat >= -90 && finalCenterCheck.lat <= 90 &&
                            finalCenterCheck.lng >= -180 && finalCenterCheck.lng <= 180) {
                            centerIsValid = true;
                        }
                    } catch (centerCheckError) {
                        centerIsValid = false;
                    }
                    
                    // If center check failed, set it first then return (will use flyTo next time)
                    if (!centerIsValid) {
                        map.setView([safeLat, safeLng], 15, { animate: false });
                        mapData.hasValidCenter = true;
                        return;
                    }
                    
                    // Final safeguard: Wrap flyTo in try-catch and fall back immediately if it fails
                    // If current center is invalid, flyTo will fail, so use setView instead
                    try {
                        // Use flyTo for smooth animation - only if map state is completely valid
                        map.flyTo([safeLat, safeLng], 15, {
                            duration: animationDuration,
                            easeLinearity: 0.5
                        });
                        // Mark center as valid after successful flyTo
                        mapData.hasValidCenter = true;
                    } catch (flyToError) {
                        // flyTo failed, immediately fall back to setView with animation
                        // This happens when the map's internal projection state has NaN
                        console.warn('flyTo failed, falling back to animated setView:', flyToError);
                        try {
                            // Use setView with animation as a graceful fallback
                            map.setView([safeLat, safeLng], 15, { 
                                animate: true,
                                duration: animationDuration
                            });
                            mapData.hasValidCenter = true;
                        } catch (setViewError) {
                            // Even animated setView failed, try instant
                            console.warn('Animated setView failed, trying instant:', setViewError);
                            try {
                                map.setView([safeLat, safeLng], 15, { animate: false });
                                mapData.hasValidCenter = true;
                            } catch (finalError) {
                                console.error('All setView attempts failed:', finalError);
                                mapData.hasValidCenter = false;
                            }
                        }
                    }
                } else if (animationType === 'setview' && canUseAnimatedSetView) {
                    // Use setView with animation
                    map.setView([safeLat, safeLng], 15, { 
                        animate: true,
                        duration: animationDuration
                    });
                    // Mark center as valid after successful setView
                    mapData.hasValidCenter = true;
                } else {
                    // Use setView without animation (instant, none, or fallback)
                    map.setView([safeLat, safeLng], 15, { animate: false });
                    // Mark center as valid after successful setView
                    mapData.hasValidCenter = true;
                }
            } catch (error) {
                console.error('Error in map update for updateShortcodeMap:', error, { lat, lng, safeLat, safeLng, mapId, animationType });
                mapData.hasValidCenter = false; // Mark as invalid
                // Try once more without animation as last resort
                try {
                    map.setView([safeLat, safeLng], 15, { animate: false });
                    mapData.hasValidCenter = true; // Mark as valid after successful setView
                } catch (finalError) {
                    console.error('Final setView attempt failed:', finalError);
                    mapData.hasValidCenter = false;
                    return;
                }
            }

            // Remove old marker
            if (mapData.marker) {
                map.removeLayer(mapData.marker);
            }

            // Add new marker with animation delay
            setTimeout(() => {
                const marker = this.createTabbedMarker(safeLat, safeLng, name, address);
                if (marker) {
                    marker.addTo(map);
                    mapData.marker = marker;
                }
            }, 800);
        },


        /**
         * Initialize tabbed interface with interactive map
         */
        initTabbedInterface: function () {
            const self = this;
            const $tabbedMap = $('#mulopimfwc-tabbed-map');

            if ($tabbedMap.length === 0) {
                return;
            }

            // Initialize map
            self.waitForLeaflet(function () {
                const $activeTab = $('.mulopimfwc-tab-item.active').first();
                
                // Check if active tab has valid coordinates
                let $firstValidTab = $();
                if ($activeTab.length && $activeTab.attr('data-lat') && $activeTab.attr('data-lng')) {
                    const activeLat = self.parseCoordinate($activeTab.data('lat'));
                    const activeLng = self.parseCoordinate($activeTab.data('lng'));
                    if (self.isValidLatLng(activeLat, activeLng)) {
                        $firstValidTab = $activeTab;
                    }
                }
                
                // If active tab doesn't have valid coordinates, find first valid tab
                if (!$firstValidTab.length) {
                    $firstValidTab = self.findFirstValidTab();
                }

                if ($firstValidTab && $firstValidTab.length) {
                    const lat = self.parseCoordinate($firstValidTab.data('lat'));
                    const lng = self.parseCoordinate($firstValidTab.data('lng'));
                    const name = $firstValidTab.data('name');
                    const address = $firstValidTab.data('address');
                    const locationId = $firstValidTab.data('tab').replace('location-', '');

                    if (!$activeTab.is($firstValidTab)) {
                        $('.mulopimfwc-tab-item').removeClass('active');
                        $firstValidTab.addClass('active');
                    }

                    self.createTabbedMap(lat, lng, name, address);
                    self.currentTab = $firstValidTab.data('tab');

                    // Show overlay by default
                    $('.mulopimfwc-map-info-overlay').addClass('visible');
                }
            });

            // Tab click handler
            $('.mulopimfwc-tab-item').on('click', function () {
                const $tab = $(this);
                const tabId = $tab.data('tab');

                if (tabId === self.currentTab) {
                    return;
                }

                // Update active state
                $('.mulopimfwc-tab-item').removeClass('active');
                $tab.addClass('active');

                // Update map - check if data attributes exist first
                if (!$tab.attr('data-lat') || !$tab.attr('data-lng')) {
                    // No coordinates available, skip map update
                    return;
                }
                
                const lat = self.parseCoordinate($tab.data('lat'));
                const lng = self.parseCoordinate($tab.data('lng'));
                const name = $tab.data('name');
                const address = $tab.data('address');
                const locationId = tabId.replace('location-', '');

                // Only update map if coordinates are valid
                if (self.isValidLatLng(lat, lng)) {
                    self.updateTabbedMap(lat, lng, name, address);
                }
                self.currentTab = tabId;

                // Update overlay content with animation
                self.updateOverlayContent(locationId);
            });

            // Marker click handler - toggle overlay
            $(document).on('click', '.mulopimfwc-marker-pin', function (e) {
                e.stopPropagation();
                self.toggleOverlay();
            });

            // Map click handler - show overlay if hidden
            // $tabbedMap.on('click', function() {
            //     if (!self.overlayVisible) {
            //         self.showOverlay();
            //     }
            // });
        },

        /**
         * Create a tabbed map instance
         */
        createTabbedMap: function (lat, lng, name, address) {
            const mapId = 'mulopimfwc-tabbed-map';
            const self = this;

            if (!self.isValidLatLng(lat, lng)) {
                return;
            }

            try {
                // Final safety check before creating map
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                    console.warn('Invalid coordinates in createTabbedMap, cannot create map:', { lat, lng });
                    return;
                }

                this.tabbedMap = L.map(mapId, {
                    center: [lat, lng],
                    zoom: mulopimfwcLocationInfo.defaultMapZoom,
                    scrollWheelZoom: false,
                    zoomControl: true
                });

                L.tileLayer(mulopimfwcLocationInfo.mapTileUrl, {
                    attribution: mulopimfwcLocationInfo.mapAttribution,
                    maxZoom: 19
                }).addTo(this.tabbedMap);

                // Add marker with click handler
                const marker = this.createTabbedMarker(lat, lng, name, address);
                if (marker) {
                    this.tabbedMapMarker = marker;
                    this.tabbedMapMarker.addTo(this.tabbedMap);
                }

                // Track if tabbed map is ready
                this.tabbedMapReady = false;

                // Ensure map is properly sized immediately after creation
                // This is critical for maps in hidden containers or tabs
                const ensureTabbedMapReady = () => {
                    const containerEl = document.getElementById(mapId);
                    if (containerEl) {
                        const rect = containerEl.getBoundingClientRect();
                        const isVisible = rect.width > 0 && rect.height > 0;
                        
                        if (isVisible && this.tabbedMap && typeof this.tabbedMap.invalidateSize === 'function') {
                            this.tabbedMap.invalidateSize();
                            // Mark as ready after invalidate
                            setTimeout(() => {
                                this.tabbedMapReady = true;
                            }, 100);
                        } else if (!isVisible) {
                            // Container not visible yet, try again later
                            setTimeout(ensureTabbedMapReady, 200);
                        }
                    }
                };

                // Try to ensure map is ready immediately
                setTimeout(ensureTabbedMapReady, 50);
                
                // Also try after a longer delay to catch cases where container becomes visible later
                setTimeout(ensureTabbedMapReady, 500);

            } catch (error) {
                console.error('Error creating tabbed map:', error);
            }
        },

        /**
         * Create a marker for tabbed interface (clickable to toggle overlay)
         */
        createTabbedMarker: function (lat, lng, name, address) {
            const self = this;

            // Validate coordinates before creating marker
            if (!self.isValidLatLng(lat, lng)) {
                console.warn('Invalid coordinates for tabbed marker:', 'lat:', lat, 'lng:', lng);
                return null;
            }

            const customIcon = L.divIcon({
                className: 'mulopimfwc-custom-marker',
                html: `<div class="mulopimfwc-marker-pin" style="cursor: pointer;">
                        <svg width="32" height="42" viewBox="0 0 32 42" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M16 0C7.163 0 0 7.163 0 16c0 13.125 16 26 16 26s16-12.875 16-26c0-8.837-7.163-16-16-16zm0 21.5c-3.038 0-5.5-2.462-5.5-5.5s2.462-5.5 5.5-5.5 5.5 2.462 5.5 5.5-2.462 5.5-5.5 5.5z" fill="#667eea"/>
                        </svg>
                        <div class="mulopimfwc-marker-pulse"></div>
                    </div>`,
                iconSize: [32, 42],
                iconAnchor: [16, 42],
                popupAnchor: [0, -42]
            });

            // Final safety check right before creating marker
            if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                console.warn('Invalid coordinates detected in createTabbedMarker before marker creation:', { lat, lng });
                return null;
            }

            // Final safety check and try-catch for marker creation
            try {
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                    console.warn('Invalid coordinates detected in createTabbedMarker before marker creation:', { lat, lng });
                    return null;
                }
                const marker = L.marker([lat, lng], { icon: customIcon });
                return marker;
            } catch (error) {
                console.error('Error creating tabbed marker:', error, { lat, lng });
                return null;
            }
        },

        /**
         * Update tabbed map location
         */
        updateTabbedMap: function (lat, lng, name, address) {
            if (!this.tabbedMap) {
                return;
            }

            // Ensure map is fully initialized
            if (typeof this.tabbedMap.getCenter !== 'function' || typeof this.tabbedMap.setView !== 'function') {
                console.warn('Tabbed map not fully initialized');
                return;
            }

            // If coordinates are invalid, remove marker and keep existing view
            if (!this.isValidLatLng(lat, lng)) {
                if (this.tabbedMapMarker) {
                    this.tabbedMap.removeLayer(this.tabbedMapMarker);
                    this.tabbedMapMarker = null;
                }
                return;
            }

            // Double-check coordinates are valid numbers before using them
            // This prevents any edge cases where NaN might slip through
            const safeLat = Number.isFinite(lat) ? lat : NaN;
            const safeLng = Number.isFinite(lng) ? lng : NaN;
            
            if (!Number.isFinite(safeLat) || !Number.isFinite(safeLng)) {
                console.warn('Invalid coordinates in updateTabbedMap:', { lat, lng });
                return;
            }

            // Get animation settings
            const animationType = mulopimfwcLocationInfo.mapAnimationType || 'setview';
            const animationDuration = mulopimfwcLocationInfo.mapAnimationDuration || 1.5;

            // Check if tabbed map is ready (properly sized and initialized)
            const mapIsReady = this.tabbedMapReady !== false;
            
            // Ensure map is properly sized before attempting animations
            if (!mapIsReady) {
                // Map not ready yet, invalidate size and wait
                try {
                    if (this.tabbedMap && typeof this.tabbedMap.invalidateSize === 'function') {
                        this.tabbedMap.invalidateSize();
                    }
                    // Mark as ready after invalidate
                    this.tabbedMapReady = true;
                } catch (e) {
                    console.warn('Error invalidating tabbed map size:', e);
                }
            }

            // Check if map container is visible and has dimensions
            const container = document.getElementById('mulopimfwc-tabbed-map');
            let containerIsReady = true;
            if (container) {
                const rect = container.getBoundingClientRect();
                const isVisible = rect.width > 0 && rect.height > 0;
                if (!isVisible) {
                    // Container not visible or has no dimensions, invalidate size
                    try {
                        if (this.tabbedMap && typeof this.tabbedMap.invalidateSize === 'function') {
                            this.tabbedMap.invalidateSize();
                        }
                    } catch (e) {
                        // Ignore
                    }
                    containerIsReady = false;
                }
            }

            // Check if map state is valid for animation
            // For flyTo, we need to be extra careful as it uses the current center
            let canUseFlyTo = false;
            let canUseAnimatedSetView = false;
            
            if (animationType !== 'none' && animationType !== 'setview_instant' && mapIsReady && containerIsReady) {
                try {
                    const currentCenter = this.tabbedMap.getCenter();
                    const currentZoom = this.tabbedMap.getZoom();
                    
                    // Validate center exists and is finite
                    if (currentCenter && 
                        Number.isFinite(currentCenter.lat) && 
                        Number.isFinite(currentCenter.lng) &&
                        Number.isFinite(currentZoom) &&
                        currentCenter.lat >= -90 && currentCenter.lat <= 90 && 
                        currentCenter.lng >= -180 && currentCenter.lng <= 180 &&
                        currentZoom >= 0 && currentZoom <= 20) {
                        
                        // Additional string check to catch any NaN values
                        const centerLatStr = String(currentCenter.lat);
                        const centerLngStr = String(currentCenter.lng);
                        if (centerLatStr !== 'NaN' && centerLngStr !== 'NaN' && 
                            !centerLatStr.includes('NaN') && !centerLngStr.includes('NaN')) {
                            
                            // For flyTo, we need to be even more strict
                            // Try to access the internal projection to ensure it's valid
                            try {
                                // Test if we can actually project the current center
                                const testProjection = this.tabbedMap.options.crs.latLngToPoint(currentCenter, this.tabbedMap.getZoom());
                                if (testProjection && Number.isFinite(testProjection.x) && Number.isFinite(testProjection.y)) {
                                    // Additional test: try to unproject back to ensure round-trip works
                                    // This ensures the projection system is fully functional
                                    const testUnproject = this.tabbedMap.options.crs.pointToLatLng(testProjection, this.tabbedMap.getZoom());
                                    if (testUnproject && 
                                        Number.isFinite(testUnproject.lat) && 
                                        Number.isFinite(testUnproject.lng) &&
                                        testUnproject.lat >= -90 && testUnproject.lat <= 90 &&
                                        testUnproject.lng >= -180 && testUnproject.lng <= 180) {
                                        canUseFlyTo = true;
                                        canUseAnimatedSetView = true;
                                    }
                                }
                            } catch (projError) {
                                // Projection test failed, don't use flyTo
                                canUseFlyTo = false;
                                canUseAnimatedSetView = true; // setView is safer
                            }
                        }
                    }
                } catch (e) {
                    // If we can't verify state, don't use animation
                    canUseFlyTo = false;
                    canUseAnimatedSetView = false;
                }
            }

            // Before attempting any animation, ensure map is properly sized
            // This is critical for maps in hidden containers or tabs
            if (!containerIsReady || !mapIsReady) {
                // Map not ready, use instant setView to ensure it's set correctly
                try {
                    this.tabbedMap.setView([safeLat, safeLng], 15, { animate: false });
                    this.tabbedMapReady = true;
                    // Invalidate size to ensure proper rendering
                    setTimeout(() => {
                        if (this.tabbedMap && typeof this.tabbedMap.invalidateSize === 'function') {
                            this.tabbedMap.invalidateSize();
                        }
                    }, 50);
                } catch (e) {
                    console.warn('Error setting initial tabbed map view:', e);
                }
                return; // Skip animation for now, will work on next update
            }

            // Update map view based on animation type
            try {
                if (animationType === 'flyto' && canUseFlyTo) {
                    // Final safeguard: Wrap flyTo in try-catch and fall back immediately if it fails
                    try {
                        // Use flyTo for smooth animation - only if map state is completely valid
                        this.tabbedMap.flyTo([safeLat, safeLng], 15, {
                            duration: animationDuration,
                            easeLinearity: 0.5
                        });
                    } catch (flyToError) {
                        // flyTo failed, immediately fall back to setView with animation
                        console.warn('flyTo failed in updateTabbedMap, falling back to animated setView:', flyToError);
                        try {
                            // Use setView with animation as a graceful fallback
                            this.tabbedMap.setView([safeLat, safeLng], 15, { 
                                animate: true,
                                duration: animationDuration
                            });
                        } catch (setViewError) {
                            // Even animated setView failed, try instant
                            console.warn('Animated setView failed, trying instant:', setViewError);
                            this.tabbedMap.setView([safeLat, safeLng], 15, { animate: false });
                        }
                    }
                } else if (animationType === 'setview' && canUseAnimatedSetView) {
                    // Use setView with animation
                    this.tabbedMap.setView([safeLat, safeLng], 15, { 
                        animate: true,
                        duration: animationDuration
                    });
                } else {
                    // Use setView without animation (instant, none, or fallback)
                    this.tabbedMap.setView([safeLat, safeLng], 15, { animate: false });
                }
            } catch (error) {
                console.error('Error in map update for updateTabbedMap:', error, { lat, lng, safeLat, safeLng, animationType });
                // Try once more without animation as last resort
                try {
                    this.tabbedMap.setView([safeLat, safeLng], 15, { animate: false });
                } catch (finalError) {
                    console.error('Final setView attempt failed:', finalError);
                    return;
                }
            }

            // Remove old marker
            if (this.tabbedMapMarker) {
                this.tabbedMap.removeLayer(this.tabbedMapMarker);
            }

            // Add new marker with animation delay
            setTimeout(() => {
                const marker = this.createTabbedMarker(safeLat, safeLng, name, address);
                if (marker) {
                    this.tabbedMapMarker = marker;
                    this.tabbedMapMarker.addTo(this.tabbedMap);
                }
            }, 800);
        },

        /**
         * Update overlay content based on location ID
         */
        updateOverlayContent: function (locationId, $container) {
            const self = this;
            const $overlay = $container ? $container.find('.mulopimfwc-map-info-overlay') : $('.mulopimfwc-map-info-overlay');
            const $tab = $container ?
                $container.find(`.mulopimfwc-tab-item[data-tab="location-${locationId}"]`) :
                $(`.mulopimfwc-tab-item[data-tab="location-${locationId}"]`);

            if ($tab.length === 0) {
                return;
            }

            // Fade out
            $overlay.removeClass('visible');

            // Get data from tab
            const name = $tab.data('name');
            const address = $tab.data('address');
            
            // Check if data attributes exist before parsing
            let lat = NaN;
            let lng = NaN;
            let hasValidCoords = false;
            
            if ($tab.attr('data-lat') && $tab.attr('data-lng')) {
                lat = this.parseCoordinate($tab.data('lat'));
                lng = this.parseCoordinate($tab.data('lng'));
                hasValidCoords = this.isValidLatLng(lat, lng);
            }

            // Get additional details from tab content
            const $tabInfo = $tab.find('.mulopimfwc-tab-details');
            const phone = $tabInfo.find('a[href^="tel:"]').text().trim();
            const email = $tabInfo.find('a[href^="mailto:"]').text().trim();
            const logo = $tab.find('.mulopimfwc-tab-logo img').attr('src') || '';
            const status = $tab.find('.mulopimfwc-status-badge').clone();

            setTimeout(() => {
                // Build new content
                let newContent = `<div class="mulopimfwc-overlay-content" data-location="${locationId}">`;

                if (logo) {
                    newContent += `
                <div class="mulopimfwc-overlay-logo">
                    <img src="${logo}" alt="${self.escapeHtml(name)}">
                </div>
            `;
                }

                newContent += `
            <div class="mulopimfwc-overlay-header">
                <h4>${self.escapeHtml(name)}</h4>
                ${status.prop('outerHTML')}
            </div>
        `;

                // Build details content first to check if there's anything to display
                let detailsContent = '';

                // Only show address if there's actual content (not just empty strings or whitespace)
                if (address && address.trim().length > 0) {
                    const addressParts = address.split(',').map(part => part.trim()).filter(part => part.length > 0);
                    if (addressParts.length > 0) {
                        detailsContent += `
                <div class="mulopimfwc-overlay-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" fill="currentColor"/>
                    </svg>
                    <div>
                        ${addressParts.map(part => `<div>${self.escapeHtml(part)}</div>`).join('')}
                    </div>
                </div>
            `;
                    }
                }

                if (phone && phone.trim().length > 0) {
                    detailsContent += `
                <div class="mulopimfwc-overlay-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                        <path d="M20.01 15.38c-1.23 0-2.42-.2-3.53-.56-.35-.12-.74-.03-1.01.24l-1.57 1.97c-2.83-1.35-5.48-3.9-6.89-6.83l1.95-1.66c.27-.28.35-.67.24-1.02-.37-1.11-.56-2.3-.56-3.53 0-.54-.45-.99-.99-.99H4.19C3.65 3 3 3.24 3 3.99 3 13.28 10.73 21 20.01 21c.71 0 .99-.63.99-1.18v-3.45c0-.54-.45-.99-.99-.99z" fill="currentColor"/>
                    </svg>
                    <a href="tel:${self.escapeHtml(phone)}">${self.escapeHtml(phone)}</a>
                </div>
            `;
                }

                if (email && email.trim().length > 0) {
                    detailsContent += `
                <div class="mulopimfwc-overlay-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                        <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z" fill="currentColor"/>
                    </svg>
                    <a href="mailto:${self.escapeHtml(email)}">${self.escapeHtml(email)}</a>
                </div>
            `;
                }

                // Only add overlay-details wrapper if there's actual content
                if (detailsContent.trim().length > 0) {
                    newContent += `<div class="mulopimfwc-overlay-details">${detailsContent}</div>`;
                }

                newContent += `
            <div class="mulopimfwc-overlay-actions">
                ${hasValidCoords ? `
                    <a href="https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}" 
                       target="_blank" 
                       rel="noopener noreferrer"
                       class="mulopimfwc-btn mulopimfwc-btn-sm mulopimfwc-btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <path d="M21.71 11.29l-9-9c-.39-.39-1.02-.39-1.41 0l-9 9c-.39.39-.39 1.02 0 1.41l9 9c.39.39 1.02.39 1.41 0l9-9c.39-.38.39-1.01 0-1.41zM14 14.5V12h-4v2H8v-4c0-.55.45-1 1-1h5V6.5l3.5 3.5-3.5 3.5z" fill="currentColor"/>
                        </svg>
                        Get Directions
                    </a>
                ` : ''}
                ${(() => {
                    // Get URL from data-url attribute, or from the link in tab-title
                    let url = $tab.attr('data-url') || $tab.find('.mulopimfwc-tab-title a').attr('href');
                    
                    // If still no URL or it's undefined, try to construct it from locationId
                    if (!url || url === 'undefined') {
                        // Construct URL manually as fallback
                        url = window.location.origin + '/store-location/' + locationId + '/';
                    }
                    
                    // Final fallback if URL is still invalid
                    if (!url || url === 'undefined' || url === '#') {
                        return ''; // Don't render the link if we can't get a valid URL
                    }
                    
                    return `<a href="${self.escapeHtml(url)}" 
                       class="mulopimfwc-btn mulopimfwc-btn-sm mulopimfwc-btn-secondary">
                        View Details
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z" fill="currentColor"/>
                        </svg>
                    </a>`;
                })()}
            </div>
        </div>`;

                // Update content
                $overlay.html(newContent);

                // Ensure it's visible if it was hidden
                if (self.overlayVisible) {
                    $overlay.addClass('visible');
                }
            }, 300);
        },


        /**
         * Toggle overlay visibility
         */
        toggleOverlay: function () {
            const $overlay = $('.mulopimfwc-map-info-overlay');

            if (this.overlayVisible) {
                $overlay.removeClass('visible');
                this.overlayVisible = false;
            } else {
                $overlay.addClass('visible');
                this.overlayVisible = true;
            }
        },

        /**
         * Show overlay
         */
        showOverlay: function () {
            const $overlay = $('.mulopimfwc-map-info-overlay');
            $overlay.addClass('visible');
            this.overlayVisible = true;
        },

        /**
         * Hide overlay
         */
        hideOverlay: function () {
            const $overlay = $('.mulopimfwc-map-info-overlay');
            $overlay.removeClass('visible');
            this.overlayVisible = false;
        },

        /**
         * Wait for Leaflet library
         */
        waitForLeaflet: function (callback) {
            if (typeof L !== 'undefined') {
                callback();
            } else {
                setTimeout(() => this.waitForLeaflet(callback), 100);
            }
        },

        /**
         * Create a map instance
         */
        createMap: function (mapId, lat, lng, name, address) {
            // Validate coordinates before proceeding
            if (!this.isValidLatLng(lat, lng)) {
                console.warn('Invalid coordinates for map:', mapId, 'lat:', lat, 'lng:', lng);
                return;
            }

            try {
                // Check if map already exists for this container
                if (this.maps[mapId]) {
                    // Map already exists, just update it if needed
                    const existingMap = this.maps[mapId];
                    if (existingMap && typeof existingMap.setView === 'function' && this.isValidLatLng(lat, lng)) {
                        // Double-check coordinates are finite before using
                        if (Number.isFinite(lat) && Number.isFinite(lng)) {
                            existingMap.setView([lat, lng], existingMap.getZoom());
                        }
                        // Invalidate size in case container was hidden/shown
                        setTimeout(() => {
                            if (existingMap && typeof existingMap.invalidateSize === 'function') {
                                existingMap.invalidateSize();
                            }
                        }, 100);
                    }
                    return;
                }

                // Check if container already has a Leaflet map instance
                const container = document.getElementById(mapId);
                if (container && container._leaflet_id) {
                    // Container already has a map, remove it first
                    try {
                        if (this.maps[mapId]) {
                            this.maps[mapId].remove();
                        }
                        delete container._leaflet_id;
                    } catch (e) {
                        console.warn('Error removing existing map:', e);
                    }
                }

                // Final safety check before creating map
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                    console.warn('Invalid coordinates in createMap, cannot create map:', { mapId, lat, lng });
                    return;
                }

                const map = L.map(mapId, {
                    center: [lat, lng],
                    zoom: mulopimfwcLocationInfo.defaultMapZoom,
                    scrollWheelZoom: false,
                    zoomControl: true
                });

                L.tileLayer(mulopimfwcLocationInfo.mapTileUrl, {
                    attribution: mulopimfwcLocationInfo.mapAttribution,
                    maxZoom: 19
                }).addTo(map);

                // Add marker
                const marker = this.createMarker(lat, lng, name, address);
                if (marker) {
                    marker.addTo(map);
                }

                // Store map instance
                this.maps[mapId] = map;

                // Handle resize
                $(window).on('resize', () => {
                    setTimeout(() => {
                        if (map && typeof map.invalidateSize === 'function') {
                            map.invalidateSize();
                        }
                    }, 100);
                });

            } catch (error) {
                console.error('Error creating map:', error);
            }
        },

        /**
         * Create a custom marker
         */
        createMarker: function (lat, lng, name, address) {
            // Validate coordinates before creating marker
            if (!this.isValidLatLng(lat, lng)) {
                console.warn('Invalid coordinates for marker:', 'lat:', lat, 'lng:', lng);
                return null;
            }

            const customIcon = L.divIcon({
                className: 'mulopimfwc-custom-marker',
                html: `<div class="mulopimfwc-marker-pin">
                        <svg width="32" height="42" viewBox="0 0 32 42" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M16 0C7.163 0 0 7.163 0 16c0 13.125 16 26 16 26s16-12.875 16-26c0-8.837-7.163-16-16-16zm0 21.5c-3.038 0-5.5-2.462-5.5-5.5s2.462-5.5 5.5-5.5 5.5 2.462 5.5 5.5-2.462 5.5-5.5 5.5z" fill="#667eea"/>
                        </svg>
                        <div class="mulopimfwc-marker-pulse"></div>
                    </div>`,
                iconSize: [32, 42],
                iconAnchor: [16, 42],
                popupAnchor: [0, -42]
            });

            // Final safety check right before creating marker
            if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                console.warn('Invalid coordinates detected in createMarker before marker creation:', { lat, lng });
                return null;
            }

            // Final safety check and try-catch for marker creation
            try {
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                    console.warn('Invalid coordinates detected in createMarker before marker creation:', { lat, lng });
                    return null;
                }
                const marker = L.marker([lat, lng], { icon: customIcon });

                const popupContent = `
                    <div class="mulopimfwc-map-popup">
                        <div class="mulopimfwc-popup-title">${this.escapeHtml(name)}</div>
                        ${address ? `<div class="mulopimfwc-popup-address">${this.escapeHtml(address)}</div>` : ''}
                        <div class="mulopimfwc-popup-actions">
                            <a href="https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}" 
                               target="_blank" 
                               rel="noopener noreferrer"
                               class="mulopimfwc-popup-link">
                                Get Directions →
                            </a>
                        </div>
                    </div>
                `;

                marker.bindPopup(popupContent);

                return marker;
            } catch (error) {
                console.error('Error creating marker:', error, { lat, lng });
                return null;
            }
        },

        /**
         * Initialize gallery lightbox
         */
        initGallery: function () {
            const self = this;

            $('.mulopimfwc-gallery-item').on('click', function (e) {
                e.preventDefault();
                const imageUrl = $(this).attr('href');
                const locationId = $(this).data('lightbox').replace('location-', '');
                const $gallery = $(this).closest('.mulopimfwc-gallery-grid');
                const images = $gallery.find('.mulopimfwc-gallery-item').map(function () {
                    return $(this).attr('href');
                }).get();
                const currentIndex = images.indexOf(imageUrl);

                self.openLightbox(images, currentIndex);
            });
        },

        /**
         * Open lightbox with navigation
         */
        openLightbox: function (images, currentIndex) {
            const self = this;
            self.lightboxImages = images;
            self.lightboxIndex = currentIndex;

            if (!$('#mulopimfwc-lightbox').length) {
                $('body').append(`
                    <div id="mulopimfwc-lightbox" class="mulopimfwc-lightbox">
                        <div class="mulopimfwc-lightbox-overlay"></div>
                        <div class="mulopimfwc-lightbox-content">
                            <button class="mulopimfwc-lightbox-close" aria-label="Close">&times;</button>
                            <button class="mulopimfwc-lightbox-prev" aria-label="Previous">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                                    <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z" fill="currentColor"/>
                                </svg>
                            </button>
                            <button class="mulopimfwc-lightbox-next" aria-label="Next">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                                    <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z" fill="currentColor"/>
                                </svg>
                            </button>
                            <div class="mulopimfwc-lightbox-image-wrapper">
                                <img src="" alt="" class="mulopimfwc-lightbox-image">
                                <div class="mulopimfwc-lightbox-loader">
                                    <div class="mulopimfwc-spinner"></div>
                                </div>
                            </div>
                            <div class="mulopimfwc-lightbox-counter">
                                <span class="mulopimfwc-lightbox-current">1</span> / 
                                <span class="mulopimfwc-lightbox-total">${images.length}</span>
                            </div>
                        </div>
                    </div>
                `);

                // Bind lightbox events
                this.bindLightboxEvents();
            }

            this.showLightboxImage(currentIndex);
            $('#mulopimfwc-lightbox').fadeIn(300);
            $('body').css('overflow', 'hidden');
        },

        /**
         * Show lightbox image
         */
        showLightboxImage: function (index) {
            const $lightbox = $('#mulopimfwc-lightbox');
            const $img = $lightbox.find('.mulopimfwc-lightbox-image');
            const $loader = $lightbox.find('.mulopimfwc-lightbox-loader');
            const $current = $lightbox.find('.mulopimfwc-lightbox-current');

            $loader.show();
            $img.css('opacity', '0');

            $img.attr('src', this.lightboxImages[index]).on('load', function () {
                $loader.hide();
                $img.css('opacity', '1');
            });

            $current.text(index + 1);
            this.lightboxIndex = index;

            // Update button states
            $lightbox.find('.mulopimfwc-lightbox-prev').prop('disabled', index === 0);
            $lightbox.find('.mulopimfwc-lightbox-next').prop('disabled', index === this.lightboxImages.length - 1);
        },

        /**
         * Bind lightbox events
         */
        bindLightboxEvents: function () {
            const self = this;
            const $lightbox = $('#mulopimfwc-lightbox');

            // Close button
            $lightbox.find('.mulopimfwc-lightbox-close, .mulopimfwc-lightbox-overlay').on('click', function () {
                self.closeLightbox();
            });

            // Previous button
            $lightbox.find('.mulopimfwc-lightbox-prev').on('click', function () {
                if (self.lightboxIndex > 0) {
                    self.showLightboxImage(self.lightboxIndex - 1);
                }
            });

            // Next button
            $lightbox.find('.mulopimfwc-lightbox-next').on('click', function () {
                if (self.lightboxIndex < self.lightboxImages.length - 1) {
                    self.showLightboxImage(self.lightboxIndex + 1);
                }
            });

            // Keyboard navigation
            $(document).on('keydown.lightbox', function (e) {
                if ($lightbox.is(':visible')) {
                    if (e.key === 'Escape') {
                        self.closeLightbox();
                    } else if (e.key === 'ArrowLeft' && self.lightboxIndex > 0) {
                        self.showLightboxImage(self.lightboxIndex - 1);
                    } else if (e.key === 'ArrowRight' && self.lightboxIndex < self.lightboxImages.length - 1) {
                        self.showLightboxImage(self.lightboxIndex + 1);
                    }
                }
            });

            // Prevent content click from closing
            $lightbox.find('.mulopimfwc-lightbox-content').on('click', function (e) {
                e.stopPropagation();
            });
        },

        /**
         * Close lightbox
         */
        closeLightbox: function () {
            $('#mulopimfwc-lightbox').fadeOut(300);
            $('body').css('overflow', '');
            $(document).off('keydown.lightbox');
        },

        /**
         * Bind global events
         */
        bindEvents: function () {
            const self = this;

            // Handle window resize for maps
            let resizeTimer;
            $(window).on('resize', function () {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function () {
                    // Invalidate all map sizes
                    Object.keys(self.maps).forEach(function (mapId) {
                        if (self.maps[mapId]) {
                            try {
                                // Handle both direct map instances and objects with map property (shortcode maps)
                                const mapInstance = self.maps[mapId].map || self.maps[mapId];
                                if (mapInstance && typeof mapInstance.invalidateSize === 'function') {
                                    mapInstance.invalidateSize();
                                }
                            } catch (e) {
                                console.warn('Error invalidating map size for ' + mapId + ':', e);
                            }
                        }
                    });

                    // Invalidate tabbed map
                    if (self.tabbedMap && typeof self.tabbedMap.invalidateSize === 'function') {
                        try {
                            self.tabbedMap.invalidateSize();
                        } catch (e) {
                            console.warn('Error invalidating tabbed map size:', e);
                        }
                    }
                }, 250);
            });

            // Smooth scroll to map on "Get Directions" click (if on same page)
            $('.mulopimfwc-btn-directions').on('click', function (e) {
                const $map = $(this).closest('.mulopimfwc-location-info-wrapper').find('.mulopimfwc-location-map');

                if ($map.length && !$(this).attr('target')) {
                    e.preventDefault();
                    $('html, body').animate({
                        scrollTop: $map.offset().top - 100
                    }, 600);
                }
            });

            // Toggle business hours on mobile
            $('.mulopimfwc-hours-card .mulopimfwc-card-title').on('click', function () {
                if ($(window).width() < 768) {
                    $(this).closest('.mulopimfwc-hours-card').toggleClass('expanded');
                }
            });

            // Lazy load images in gallery
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver(function (entries, observer) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            const $img = $(entry.target).find('img');
                            const src = $img.attr('src');

                            if (src) {
                                $img.on('load', function () {
                                    $(this).addClass('loaded');
                                });
                            }

                            observer.unobserve(entry.target);
                        }
                    });
                });

                $('.mulopimfwc-gallery-item').each(function () {
                    imageObserver.observe(this);
                });
            }
        },

        /**
         * Escape HTML
         */
        escapeHtml: function (text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, function (m) { return map[m]; });
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        MulopimfwcLocationInfo.init();
    });

    // Re-initialize on AJAX complete (for dynamic content)
    $(document).ajaxComplete(function () {
        setTimeout(function () {
            MulopimfwcLocationInfo.initMaps();
            MulopimfwcLocationInfo.initGallery();
        }, 100);
    });

})(jQuery);
