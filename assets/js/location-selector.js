/**
 * location-selector.js
 * Multi-Location Product Location Selector
 * Handles dynamic product updates when location changes
 */
(function ($) {
    'use strict';

    class LocationSelector {
        constructor() {
            this.placed = false;
            this.retryCount = 0;
            this.maxRetries = 20; // ~4s with 200ms interval
            this.retryTimer = null;
            this.isSwitching = false;
            this.settings = window.mulopimfwc_locationWiseProducts || {};

            // Read PHP-provided hints for placement
            this.cfg = window.MULOPIMFWC_LOC_SELECTOR || {
                position: 'after_price',
                targets: {}
            };

            this.init();
        }

        init() {
            this.bindEvents();
            this.placeSelector(); // initial attempt

            // Try again after full load (Elementor sometimes injects late)
            $(window).on('load', () => this.placeSelector());

            // Observe DOM changes within the product summary area to catch late renders
            this.setupObserver();
        }

        bindEvents() {
            // Handle location change via radio buttons
            $(document).on('change', '.mulopimfwc-location-checkbox', (e) => {
                this.handleLocationChange($(e.target).val());
            });

            // Handle location change via buttons
            $(document).on('click', '.mulopimfwc-location-button', (e) => {
                e.preventDefault();
                const $button = $(e.currentTarget);
                const location = $button.data('location');

                // Update button states
                $button.addClass('active').siblings().removeClass('active');

                this.handleLocationChange(location);
            });

            // Handle location change via select dropdown
            $(document).on('change', '.mulopimfwc-location-dropdown', (e) => {
                const $el = $(e.target);
                const newVal = $el.val();
                
                const current = $el.data('current-location');
                if (String(newVal || '') === String(current || '')) return;
                
                this.handleLocationChange(newVal);
            });
        }

        /* ---------------------------
         * Placement
         * -------------------------- */
        placeSelector() {
            if (this.placed) return;

            const $selector = this.getSelectorElement();
            if (!$selector || !$selector.length) {
                this.scheduleRetry();
                return;
            }

            const { position, targets } = this.cfg;
            const list = (targets && targets[position]) ? targets[position] : [];

            // Fallback common containers to constrain queries a bit
            const $root = $('.product, .single-product, .woocommerce-product-details__short-description, .summary, body');

            let $anchor = null;
            for (let i = 0; i < list.length; i++) {
                const sel = list[i];
                const $candidate = $root.find(sel).first();
                if ($candidate.length) {
                    $anchor = $candidate;
                    break;
                }
            }

            // If still nothing, try some generic anchors
            if (!$anchor || !$anchor.length) {
                $anchor = $('.summary').find('form.cart, .price, h1.product_title, h1.entry-title, .product_meta').first();
            }

            if (!$anchor || !$anchor.length) {
                this.scheduleRetry();
                return;
            }

            // Decide before/after behavior (match PHP should_inject_after())
            const injectAfter = ['after_title', 'after_price', 'product_meta', 'after_add_to_cart'].includes(this.cfg.position);

            // Insert only once
            if (!$selector.data('mulopimfwc-placed')) {
                if (injectAfter) {
                    $selector.insertAfter($anchor);
                } else {
                    $selector.insertBefore($anchor);
                }
                $selector.attr('data-mulopimfwc-placed', '1');
                this.placed = true;
            }
        }

        getSelectorElement() {
            // Primary: server-rendered wrapper
            let $selector = $('.mulopimfwc-product-location-selector-wrapper').first();

            // If the wrapper is inside the hidden portal, move it out once
            const $portal = $('#mulopimfwc-selector-portal');
            if ((!$selector || !$selector.length) && $portal.length) {
                const $inside = $portal.find('.mulopimfwc-product-location-selector-wrapper').first();
                if ($inside.length) {
                    // detach keeps events
                    $selector = $inside.detach().show();
                    // append temporarily near summary so we can reposition accurately
                    const $summary = $('.summary').first();
                    if ($summary.length) {
                        $summary.prepend($selector);
                    } else {
                        $('body').append($selector);
                    }
                }
            }

            return $selector && $selector.length ? $selector : null;
        }

        scheduleRetry() {
            if (this.retryTimer || this.retryCount >= this.maxRetries || this.placed) return;
            this.retryCount++;
            this.retryTimer = setTimeout(() => {
                this.retryTimer = null;
                this.placeSelector();
            }, 200);
        }

        setupObserver() {
            const target = document.querySelector('.product, .single-product, .summary') || document.body;
            if (!target || !('MutationObserver' in window)) return;

            const observer = new MutationObserver(() => {
                if (!this.placed) this.placeSelector();
            });

            observer.observe(target, {
                childList: true,
                subtree: true
            });
        }

        /* ---------------------------
         * Location change
         * -------------------------- */
        handleLocationChange(location) {
            if (!location || this.isSwitching) return;

            const previousLocation = this.getCurrentStoreLocation();

            if (previousLocation && String(previousLocation) === String(location)) {
                return;
            }

            const allowMixed = this.settings.allow_mixed_in_cart === 'on';
            const behavior = this.settings.location_switching_behavior || 'update_cart';
            const shouldUpdateCart = !allowMixed && behavior !== 'preserve_cart';

            const proceed = () => this.performLocationSwitch(location, previousLocation);

            if (!shouldUpdateCart) {
                proceed();
                return;
            }

            this.checkCartHasProducts()
                .then((cartHasProducts) => {
                    if (!cartHasProducts) {
                        proceed();
                        return;
                    }

                    const shouldPrompt = behavior === 'prompt_user' || !!this.settings.location_change_notification;
                    if (shouldPrompt) {
                        const message =
                            this.settings.location_notification_text ||
                            'Do you want to change the store location? Your cart will be updated.';
                        const confirmed = window.confirm(message);
                        if (!confirmed) {
                            this.resetSelectorUI(previousLocation);
                            return;
                        }
                    }

                    proceed();
                })
                .catch(() => {
                    proceed();
                });
        }

        setLoadingState(isLoading) {
            const $selector = $('.mulopimfwc-product-location-selector');
            if (isLoading) {
                $selector.addClass('loading');
            } else {
                this.hideLoadingState();
            }
        }

        hideLoadingState() {
            $('.mulopimfwc-product-location-selector').removeClass('loading');
            $('.mulopimfwc-loading').removeClass('mulopimfwc-loading');
            $('.mulopimfwc-loader').remove();
        }

        performLocationSwitch(location, previousLocation) {
            const ajaxUrl = this.settings.ajaxUrl;
            const nonce = this.settings.nonce || '';
            const behavior = this.settings.location_switching_behavior || 'update_cart';
            const requiresCleanup = this.settings.allow_mixed_in_cart !== 'on' && behavior !== 'preserve_cart';

            const fallbackReload = () => {
                this.setLocationCookie(location);
                // Perform a hard reload, ensuring any potential form data is not resubmitted
                if (window.location.href.indexOf('//' + window.location.host + window.location.pathname) !== -1) {
                    // Remove query and hash for a clean reload to prevent form resubmission prompt
                    window.location.replace(window.location.origin + window.location.pathname);
                } else {
                    // Fallback: full page reload (hard reload)
                    window.location.reload(true);
                }
            };

            if (!ajaxUrl) {
                fallbackReload();
                return;
            }

            this.isSwitching = true;
            this.setLoadingState(true);

            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: {
                    action: 'mulopimfwc_switch_location',
                    nonce: nonce,
                    location: location
                },
                success: (response) => {
                    this.isSwitching = false;

                    if (response && response.success) {
                        const removedItems = response.data && response.data.removed_items ? response.data.removed_items : [];

                        if (removedItems && removedItems.length) {
                            alert(
                                'The following items were removed because they are not available at the selected location: ' +
                                removedItems.join(', ')
                            );
                        }

                        this.setLocationCookie(location);
                        // Perform a hard reload, ensuring any potential form data is not resubmitted
                        if (window.location.href.indexOf('//' + window.location.host + window.location.pathname) !== -1) {
                            // Remove query and hash for a clean reload to prevent form resubmission prompt
                            window.location.replace(window.location.origin + window.location.pathname);
                        } else {
                            // Fallback: full page reload (hard reload)
                            window.location.reload(true);
                        }
                        return;
                    }

                    this.resetSelectorUI(previousLocation);
                    this.setLoadingState(false);
                    alert((response && response.data && response.data.message) || 'Unable to change location. Please try again.');
                },
                error: () => {
                    this.isSwitching = false;
                    this.resetSelectorUI(previousLocation);
                    this.setLoadingState(false);
                    if (requiresCleanup) {
                        alert('Unable to change location right now. Please try again.');
                        return;
                    }

                    fallbackReload();
                }
            });
        }

        checkCartHasProducts() {
            const ajaxUrl = this.settings.ajaxUrl;

            return new Promise((resolve) => {
                if (!ajaxUrl) {
                    resolve(false);
                    return;
                }

                $.ajax({
                    url: ajaxUrl,
                    method: 'POST',
                    data: { action: 'check_cart_products' },
                    success: (response) => {
                        const hasProducts = response && response.success && response.data
                            ? !!response.data.cartHasProducts
                            : false;
                        resolve(hasProducts);
                    },
                    error: () => resolve(false)
                });
            });
        }

        getCurrentStoreLocation() {
            const cookieLocation = this.getCookie('mulopimfwc_store_location');
            if (cookieLocation) return cookieLocation;

            return '';
        }

        resetSelectorUI(previousLocation) {
            const targetLocation = previousLocation || this.getCurrentStoreLocation();

            const $buttons = $('.mulopimfwc-location-button');
            if ($buttons.length) {
                $buttons.removeClass('active button-primary btn-primary').addClass('button-secondary btn-secondary plugincy-btn-secondary');
                const $buttonToActivate = $buttons.filter(`[data-location="${targetLocation}"]`);
                if ($buttonToActivate.length) {
                    $buttonToActivate.addClass('active button-primary btn-primary').removeClass('button-secondary btn-secondary plugincy-btn-secondary');
                }
            }

            const $radios = $('.mulopimfwc-location-checkbox');
            if ($radios.length) {
                $radios.prop('checked', false);
                $radios.filter(`[value="${targetLocation}"]`).prop('checked', true);
            }

            const $dropdown = $('.mulopimfwc-location-dropdown');
            if ($dropdown.length) {
                $dropdown.val(targetLocation);
            }
        }

        setLocationCookie(location) {
            const expires = new Date(Date.now() + this.getCookieExpiryMs());
            document.cookie = `mulopimfwc_store_location=${location}; expires=${expires.toUTCString()}; path=/; samesite=lax`;
        }

        getCookieExpiryMs() {
            const days = parseInt(this.cfg.cookieExpiryDays, 10);
            const safeDays = Number.isFinite(days) && days > 0 ? days : 30;
            return safeDays * 24 * 60 * 60 * 1000;
        }

        getCookie(name) {
            const nameEQ = name + "=";
            const ca = document.cookie.split(';');
            for (let i = 0; i < ca.length; i++) {
                let c = ca[i];
                while (c.charAt(0) === ' ') c = c.substring(1, c.length);
                if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
            }
            return null;
        }
    }

    // Initialize when document is ready
    $(document).ready(() => {
        new LocationSelector();
    });

})(jQuery);
