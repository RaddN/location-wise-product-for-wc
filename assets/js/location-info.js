/**
 * Frontend Location Information JavaScript
 * Multi Location Product & Inventory Management for WooCommerce
 */

(function($) {
    'use strict';

    const MulopimfwcLocationInfo = {
        
        maps: {},
        
        /**
         * Initialize
         */
        init: function() {
            this.initMaps();
            this.initGallery();
            this.bindEvents();
        },

        /**
         * Initialize all maps on the page
         */
        initMaps: function() {
            const self = this;
            
            $('.mulopimfwc-location-map').each(function() {
                const $map = $(this);
                const mapId = $map.attr('id');
                const lat = parseFloat($map.data('lat'));
                const lng = parseFloat($map.data('lng'));
                const name = $map.data('name');
                const address = $map.data('address');

                if (!lat || !lng || !mapId) {
                    console.warn('Missing map data for', mapId);
                    return;
                }

                // Add loading class
                $map.addClass('loading');

                // Wait for Leaflet to be available
                self.waitForLeaflet(function() {
                    self.createMap(mapId, lat, lng, name, address);
                    $map.removeClass('loading');
                });
            });
        },

        /**
         * Wait for Leaflet library to be loaded
         */
        waitForLeaflet: function(callback) {
            if (typeof L !== 'undefined') {
                callback();
            } else {
                setTimeout(() => this.waitForLeaflet(callback), 100);
            }
        },

        /**
         * Create a map instance
         */
        createMap: function(mapId, lat, lng, name, address) {
            try {
                // Initialize map
                const map = L.map(mapId, {
                    center: [lat, lng],
                    zoom: 15,
                    scrollWheelZoom: false,
                    zoomControl: true
                });

                // Add tile layer
                L.tileLayer(mulopimfwcLocationInfo.mapTileUrl, {
                    attribution: mulopimfwcLocationInfo.mapAttribution,
                    maxZoom: 19
                }).addTo(map);

                // Custom marker icon
                const customIcon = L.divIcon({
                    className: 'mulopimfwc-custom-marker',
                    html: `<div class="mulopimfwc-marker-pin">
                            <svg width="32" height="42" viewBox="0 0 32 42" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M16 0C7.163 0 0 7.163 0 16c0 13.125 16 26 16 26s16-12.875 16-26c0-8.837-7.163-16-16-16zm0 21.5c-3.038 0-5.5-2.462-5.5-5.5s2.462-5.5 5.5-5.5 5.5 2.462 5.5 5.5-2.462 5.5-5.5 5.5z" fill="#e74c3c"/>
                            </svg>
                        </div>`,
                    iconSize: [32, 42],
                    iconAnchor: [16, 42],
                    popupAnchor: [0, -42]
                });

                // Add marker
                const marker = L.marker([lat, lng], { icon: customIcon }).addTo(map);

                // Create popup content
                const popupContent = `
                    <div class="mulopimfwc-map-popup">
                        <div class="mulopimfwc-map-popup-title">${this.escapeHtml(name)}</div>
                        ${address ? `<div class="mulopimfwc-map-popup-address">${this.escapeHtml(address)}</div>` : ''}
                        <div class="mulopimfwc-map-popup-actions" style="margin-top: 10px;">
                            <a href="https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}" 
                               target="_blank" 
                               rel="noopener noreferrer"
                               style="color: #3498db; text-decoration: none; font-weight: 600; font-size: 13px;">
                                Get Directions →
                            </a>
                        </div>
                    </div>
                `;

                marker.bindPopup(popupContent);

                // Store map instance
                this.maps[mapId] = map;

                // Invalidate size on window resize
                $(window).on('resize', function() {
                    map.invalidateSize();
                });

                // Invalidate size when tab becomes visible (for tabs/accordions)
                $(document).on('shown.bs.tab shown.bs.collapse', function() {
                    setTimeout(() => map.invalidateSize(), 100);
                });

            } catch (error) {
                console.error('Error creating map:', error);
            }
        },

        /**
         * Initialize gallery lightbox
         */
        initGallery: function() {
            const self = this;
            
            // Simple lightbox functionality
            $('.mulopimfwc-gallery-item').on('click', function(e) {
                e.preventDefault();
                const imageUrl = $(this).attr('href');
                self.openLightbox(imageUrl);
            });
        },

        /**
         * Open lightbox
         */
        openLightbox: function(imageUrl) {
            // Create lightbox if it doesn't exist
            if (!$('#mulopimfwc-lightbox').length) {
                $('body').append(`
                    <div id="mulopimfwc-lightbox" class="mulopimfwc-lightbox">
                        <div class="mulopimfwc-lightbox-overlay"></div>
                        <div class="mulopimfwc-lightbox-content">
                            <button class="mulopimfwc-lightbox-close">&times;</button>
                            <img src="" alt="Gallery Image">
                        </div>
                    </div>
                `);

                // Add lightbox styles
                this.addLightboxStyles();
            }

            const $lightbox = $('#mulopimfwc-lightbox');
            const $img = $lightbox.find('img');

            // Set image source
            $img.attr('src', imageUrl);

            // Show lightbox
            $lightbox.fadeIn(300);
            $('body').addClass('mulopimfwc-lightbox-open');

            // Close on overlay or button click
            $lightbox.find('.mulopimfwc-lightbox-overlay, .mulopimfwc-lightbox-close').on('click', function() {
                $lightbox.fadeOut(300);
                $('body').removeClass('mulopimfwc-lightbox-open');
            });

            // Close on escape key
            $(document).on('keydown.lightbox', function(e) {
                if (e.keyCode === 27) {
                    $lightbox.fadeOut(300);
                    $('body').removeClass('mulopimfwc-lightbox-open');
                    $(document).off('keydown.lightbox');
                }
            });
        },

        /**
         * Add lightbox styles dynamically
         */
        addLightboxStyles: function() {
            if ($('#mulopimfwc-lightbox-styles').length) return;

            $('head').append(`
                <style id="mulopimfwc-lightbox-styles">
                    .mulopimfwc-lightbox {
                        display: none;
                        position: fixed;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        z-index: 999999;
                    }
                    .mulopimfwc-lightbox-overlay {
                        position: absolute;
                        inset: 0;
                        background: rgba(0, 0, 0, 0.9);
                        cursor: pointer;
                    }
                    .mulopimfwc-lightbox-content {
                        position: relative;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        height: 100%;
                        padding: 40px;
                    }
                    .mulopimfwc-lightbox-content img {
                        max-width: 100%;
                        max-height: 100%;
                        object-fit: contain;
                        border-radius: 8px;
                        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
                    }
                    .mulopimfwc-lightbox-close {
                        position: absolute;
                        top: 20px;
                        right: 20px;
                        width: 50px;
                        height: 50px;
                        background: rgba(255, 255, 255, 0.2);
                        border: 2px solid rgba(255, 255, 255, 0.4);
                        border-radius: 50%;
                        color: #fff;
                        font-size: 32px;
                        line-height: 1;
                        cursor: pointer;
                        transition: all 0.3s ease;
                        z-index: 10;
                    }
                    .mulopimfwc-lightbox-close:hover {
                        background: rgba(255, 255, 255, 0.3);
                        border-color: rgba(255, 255, 255, 0.6);
                        transform: rotate(90deg);
                    }
                    .mulopimfwc-lightbox-open {
                        overflow: hidden;
                    }
                    @media screen and (max-width: 768px) {
                        .mulopimfwc-lightbox-content {
                            padding: 20px;
                        }
                        .mulopimfwc-lightbox-close {
                            width: 40px;
                            height: 40px;
                            font-size: 24px;
                            top: 10px;
                            right: 10px;
                        }
                    }
                </style>
            `);
        },

        /**
         * Add custom marker styles
         */
        addMarkerStyles: function() {
            if ($('#mulopimfwc-marker-styles').length) return;

            $('head').append(`
                <style id="mulopimfwc-marker-styles">
                    .mulopimfwc-custom-marker {
                        background: transparent;
                        border: none;
                    }
                    .mulopimfwc-marker-pin {
                        position: relative;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        animation: mulopimfwc-marker-bounce 1s ease-in-out;
                    }
                    .mulopimfwc-marker-pin svg {
                        filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.3));
                    }
                    @keyframes mulopimfwc-marker-bounce {
                        0%, 100% { transform: translateY(0); }
                        50% { transform: translateY(-10px); }
                    }
                </style>
            `);
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            const self = this;

            // Re-initialize maps when content is dynamically loaded
            $(document).on('mulopimfwc_content_loaded', function() {
                self.initMaps();
                self.initGallery();
            });

            // Handle responsive map resize
            let resizeTimeout;
            $(window).on('resize', function() {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(function() {
                    Object.values(self.maps).forEach(map => {
                        if (map && map.invalidateSize) {
                            map.invalidateSize();
                        }
                    });
                }, 250);
            });
        },

        /**
         * Escape HTML to prevent XSS
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        /**
         * Destroy map instance
         */
        destroyMap: function(mapId) {
            if (this.maps[mapId]) {
                this.maps[mapId].remove();
                delete this.maps[mapId];
            }
        },

        /**
         * Get user's current location
         */
        getUserLocation: function(callback) {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        callback(null, {
                            lat: position.coords.latitude,
                            lng: position.coords.longitude
                        });
                    },
                    function(error) {
                        callback(error, null);
                    }
                );
            } else {
                callback(new Error('Geolocation not supported'), null);
            }
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        MulopimfwcLocationInfo.init();
        MulopimfwcLocationInfo.addMarkerStyles();
    });

    // Expose to global scope for external use
    window.MulopimfwcLocationInfo = MulopimfwcLocationInfo;

})(jQuery);