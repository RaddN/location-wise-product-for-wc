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
            return Number.isFinite(lat) && Number.isFinite(lng);
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
                const lat = parseFloat($tab.data('lat'));
                const lng = parseFloat($tab.data('lng'));

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
                const lat = parseFloat($map.data('lat'));
                const lng = parseFloat($map.data('lng'));
                const name = $map.data('name');
                const address = $map.data('address');

                if (!mapId || !self.isValidLatLng(lat, lng)) {
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
                const $firstValidTab = self.isValidLatLng(
                    parseFloat($activeTab.data('lat')),
                    parseFloat($activeTab.data('lng'))
                ) ? $activeTab : self.findFirstValidTab($container);

                if ($firstValidTab.length) {
                    const lat = parseFloat($firstValidTab.data('lat'));
                    const lng = parseFloat($firstValidTab.data('lng'));
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

            try {
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
                marker.addTo(map);

                // Store map and marker
                this.maps[mapId] = {
                    map: map,
                    marker: marker
                };

                // Bind tab clicks for this container
                $container.find('.mulopimfwc-tab-item').on('click', function () {
                    const $tab = $(this);
                    const tabLat = parseFloat($tab.data('lat'));
                    const tabLng = parseFloat($tab.data('lng'));
                    const tabName = $tab.data('name');
                    const tabAddress = $tab.data('address');
                    const locationId = $tab.data('tab').replace('location-', '');

                    // Update active state
                    $container.find('.mulopimfwc-tab-item').removeClass('active');
                    $tab.addClass('active');

                    // Update map
                    self.updateShortcodeMap(mapId, tabLat, tabLng, tabName, tabAddress);

                    // Update overlay
                    self.updateOverlayContent(locationId, $container);
                });

                // Invalidate size after delay
                setTimeout(() => {
                    map.invalidateSize();
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

            // If coordinates are invalid, remove marker and keep current view
            if (!this.isValidLatLng(lat, lng)) {
                if (mapData.marker) {
                    map.removeLayer(mapData.marker);
                    mapData.marker = null;
                }
                return;
            }

            // Animate to new location
            map.flyTo([lat, lng], 15, {
                duration: 1.5,
                easeLinearity: 0.5
            });

            // Remove old marker
            if (mapData.marker) {
                map.removeLayer(mapData.marker);
            }

            // Add new marker with animation delay
            setTimeout(() => {
                const marker = this.createTabbedMarker(lat, lng, name, address);
                marker.addTo(map);
                mapData.marker = marker;
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
                const $firstValidTab = self.isValidLatLng(
                    parseFloat($activeTab.data('lat')),
                    parseFloat($activeTab.data('lng'))
                ) ? $activeTab : self.findFirstValidTab();

                if ($firstValidTab && $firstValidTab.length) {
                    const lat = parseFloat($firstValidTab.data('lat'));
                    const lng = parseFloat($firstValidTab.data('lng'));
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

                // Update map
                const lat = parseFloat($tab.data('lat'));
                const lng = parseFloat($tab.data('lng'));
                const name = $tab.data('name');
                const address = $tab.data('address');
                const locationId = tabId.replace('location-', '');

                self.updateTabbedMap(lat, lng, name, address);
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
                this.tabbedMapMarker = this.createTabbedMarker(lat, lng, name, address);
                this.tabbedMapMarker.addTo(this.tabbedMap);

                // Invalidate size after a short delay to ensure proper rendering
                setTimeout(() => {
                    this.tabbedMap.invalidateSize();
                }, 100);

            } catch (error) {
                console.error('Error creating tabbed map:', error);
            }
        },

        /**
         * Create a marker for tabbed interface (clickable to toggle overlay)
         */
        createTabbedMarker: function (lat, lng, name, address) {
            const self = this;

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

            const marker = L.marker([lat, lng], { icon: customIcon });

            return marker;
        },

        /**
         * Update tabbed map location
         */
        updateTabbedMap: function (lat, lng, name, address) {
            if (!this.tabbedMap) {
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

            // Animate to new location
            this.tabbedMap.flyTo([lat, lng], 15, {
                duration: 1.5,
                easeLinearity: 0.5
            });

            // Remove old marker
            if (this.tabbedMapMarker) {
                this.tabbedMap.removeLayer(this.tabbedMapMarker);
            }

            // Add new marker with animation delay
            setTimeout(() => {
                this.tabbedMapMarker = this.createTabbedMarker(lat, lng, name, address);
                this.tabbedMapMarker.addTo(this.tabbedMap);
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
            const lat = parseFloat($tab.data('lat'));
            const lng = parseFloat($tab.data('lng'));
            const hasValidCoords = this.isValidLatLng(lat, lng);

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

                newContent += `<div class="mulopimfwc-overlay-details">`;

                if (address) {
                    const addressParts = address.split(',').map(part => part.trim());
                    newContent += `
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

                if (phone) {
                    newContent += `
                <div class="mulopimfwc-overlay-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                        <path d="M20.01 15.38c-1.23 0-2.42-.2-3.53-.56-.35-.12-.74-.03-1.01.24l-1.57 1.97c-2.83-1.35-5.48-3.9-6.89-6.83l1.95-1.66c.27-.28.35-.67.24-1.02-.37-1.11-.56-2.3-.56-3.53 0-.54-.45-.99-.99-.99H4.19C3.65 3 3 3.24 3 3.99 3 13.28 10.73 21 20.01 21c.71 0 .99-.63.99-1.18v-3.45c0-.54-.45-.99-.99-.99z" fill="currentColor"/>
                    </svg>
                    <a href="tel:${self.escapeHtml(phone)}">${self.escapeHtml(phone)}</a>
                </div>
            `;
                }

                if (email) {
                    newContent += `
                <div class="mulopimfwc-overlay-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                        <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z" fill="currentColor"/>
                    </svg>
                    <a href="mailto:${self.escapeHtml(email)}">${self.escapeHtml(email)}</a>
                </div>
            `;
                }

                newContent += `</div>`; // Close overlay-details

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
                <a href="${$tab.find('.mulopimfwc-tab-title a').attr('href')}" 
                   class="mulopimfwc-btn mulopimfwc-btn-sm mulopimfwc-btn-secondary">
                    View Details
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z" fill="currentColor"/>
                    </svg>
                </a>
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
            try {
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
                marker.addTo(map);

                // Store map instance
                this.maps[mapId] = map;

                // Handle resize
                $(window).on('resize', () => {
                    setTimeout(() => map.invalidateSize(), 100);
                });

            } catch (error) {
                console.error('Error creating map:', error);
            }
        },

        /**
         * Create a custom marker
         */
        createMarker: function (lat, lng, name, address) {
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
                            self.maps[mapId].invalidateSize();
                        }
                    });

                    // Invalidate tabbed map
                    if (self.tabbedMap) {
                        self.tabbedMap.invalidateSize();
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
