/**
 * Multi-Location Product Location Selector
 * Handles dynamic product updates when location changes
 */
(function($) {
    'use strict';

    class LocationSelector {
        constructor() {
            this.init();
        }

        init() {
            this.bindEvents();
        }

        bindEvents() {
            // Handle location change via radio buttons
            $(document).on('change', '.mulopimfwc-location-checkbox', (e) => {
                this.handleLocationChange($(e.target).val());
            });

            // Handle location change via buttons
            $(document).on('click', '.mulopimfwc-location-button', (e) => {
                e.preventDefault();
                const $button = $(e.target);
                const location = $button.data('location');
                
                // Update button states
                $button.addClass('active').siblings().removeClass('active');
                
                this.handleLocationChange(location);
            });

            // Handle location change via select dropdown
            $(document).on('change', '.mulopimfwc-location-dropdown', (e) => {
                this.handleLocationChange($(e.target).val());
            });
        }

        handleLocationChange(location) {
            if (!location) {
                return;
            }
            
            // Update location cookie
            this.setLocationCookie(location);

            window.location.reload();
        }

        hideLoadingState() {
            $('.mulopimfwc-product-location-selector').removeClass('loading');
            $('.mulopimfwc-loading').removeClass('mulopimfwc-loading');
            $('.mulopimfwc-loader').remove();
        }

        setLocationCookie(location) {
            const expires = new Date();
            expires.setTime(expires.getTime() + (30 * 24 * 60 * 60 * 1000)); // 30 days
            document.cookie = `mulopimfwc_store_location=${location}; expires=${expires.toUTCString()}; path=/; samesite=lax`;
        }

        getCookie(name) {
            const nameEQ = name + "=";
            const ca = document.cookie.split(';');
            for (let i = 0; i < ca.length; i++) {
                let c = ca[i];
                while (c.charAt(0) === ' ') {
                    c = c.substring(1, c.length);
                }
                if (c.indexOf(nameEQ) === 0) {
                    return c.substring(nameEQ.length, c.length);
                }
            }
            return null;
        }
    }

    // Initialize when document is ready
    $(document).ready(() => {
        new LocationSelector();
    });

})(jQuery);