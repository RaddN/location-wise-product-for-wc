/**
 * Location-Based Recommendations JavaScript
 * Place in: assets/js/recommendations.js
 */

(function($) {
    'use strict';

    /**
     * Recommendations Manager
     */
    const RecommendationsManager = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.refreshOnLocationChange();
        },

        /**
         * Bind event listeners
         */
        bindEvents: function() {
            // Refresh recommendations when location changes
            $(document).on('change', '.mulopimfwc-location-selector, #mulopimfwc_store_location', 
                this.handleLocationChange.bind(this)
            );

            // Handle add to cart from recommendations
            $(document).on('click', '.mulopimfwc-recommendation-actions .add_to_cart_button', 
                this.handleAddToCart.bind(this)
            );

            // Track recommendation clicks
            $(document).on('click', '.mulopimfwc-recommendation-link', 
                this.trackRecommendationClick.bind(this)
            );
        },

        /**
         * Handle location change
         */
        handleLocationChange: function(e) {
            const locationSlug = $(e.target).val();
            
            if (locationSlug && locationSlug !== 'all-products') {
                // Delay to allow cookie to be set
                setTimeout(() => {
                    this.refreshRecommendations(locationSlug);
                }, 300);
            } else {
                this.hideRecommendations();
            }
        },

        /**
         * Refresh recommendations for a location
         */
        refreshRecommendations: function(locationSlug) {
            const $containers = $('.mulopimfwc-recommendations-container');
            
            if ($containers.length === 0) {
                return;
            }

            $containers.each(function() {
                const $container = $(this);
                const limit = $container.data('limit') || 8;

                // Show loading state
                RecommendationsManager.showLoading($container);

                // Fetch recommendations
                $.ajax({
                    url: mulopimfwcRecommendations.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'mulopimfwc_get_recommendations',
                        location: locationSlug,
                        limit: limit,
                        nonce: mulopimfwcRecommendations.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data.count > 0) {
                            // Reload the page to show updated recommendations
                            // In a more advanced implementation, you could dynamically update the HTML
                            location.reload();
                        } else {
                            RecommendationsManager.showNoResults($container);
                        }
                    },
                    error: function() {
                        RecommendationsManager.showError($container);
                    }
                });
            });
        },

        /**
         * Show loading state
         */
        showLoading: function($container) {
            const $grid = $container.find('.mulopimfwc-recommendations-grid');
            $grid.html('<div class="mulopimfwc-recommendations-loading">Loading recommendations...</div>');
        },

        /**
         * Show no results message
         */
        showNoResults: function($container) {
            const $grid = $container.find('.mulopimfwc-recommendations-grid');
            $grid.html('<div class="mulopimfwc-recommendations-notice">No recommendations available yet for this location.</div>');
        },

        /**
         * Show error message
         */
        showError: function($container) {
            const $grid = $container.find('.mulopimfwc-recommendations-grid');
            $grid.html('<div class="mulopimfwc-recommendations-notice" style="border-color: #e74c3c; background: #ffe8e8;">An error occurred while loading recommendations. Please try again.</div>');
        },

        /**
         * Hide recommendations
         */
        hideRecommendations: function() {
            const $containers = $('.mulopimfwc-recommendations-container');
            $containers.each(function() {
                const $container = $(this);
                const $grid = $container.find('.mulopimfwc-recommendations-grid');
                $grid.html('<div class="mulopimfwc-recommendations-notice">Please select a location to see recommendations.</div>');
            });
        },

        /**
         * Refresh on location change from cookie
         */
        refreshOnLocationChange: function() {
            // Check if location cookie changed
            const currentLocation = this.getCookie('mulopimfwc_store_location');
            
            if (currentLocation && currentLocation !== 'all-products') {
                // Location is set, recommendations should be visible
                this.enhanceRecommendations();
            } else {
                // No location set
                this.hideRecommendations();
            }
        },

        /**
         * Enhance recommendations display
         */
        enhanceRecommendations: function() {
            // Add animations
            $('.mulopimfwc-recommendation-item').each(function(index) {
                $(this).css({
                    'opacity': '0',
                    'transform': 'translateY(20px)'
                }).delay(index * 100).animate({
                    'opacity': '1',
                    'transform': 'translateY(0)'
                }, 400);
            });

            // Lazy load images if needed
            this.lazyLoadImages();

            // Handle out of stock products
            this.handleOutOfStock();
        },

        /**
         * Lazy load images
         */
        lazyLoadImages: function() {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        const src = img.dataset.src;
                        
                        if (src) {
                            img.src = src;
                            img.removeAttribute('data-src');
                            observer.unobserve(img);
                        }
                    }
                });
            });

            $('.mulopimfwc-recommendation-image img[data-src]').each(function() {
                imageObserver.observe(this);
            });
        },

        /**
         * Handle out of stock products
         */
        handleOutOfStock: function() {
            $('.mulopimfwc-recommendation-item').each(function() {
                const $item = $(this);
                const $button = $item.find('.add_to_cart_button');
                
                if ($button.hasClass('product_type_variable')) {
                    // Variable product - check if in stock
                    // This would need additional AJAX call for accurate stock status
                }
            });
        },

        /**
         * Handle add to cart from recommendations
         */
        handleAddToCart: function(e) {
            const $button = $(e.currentTarget);
            const productId = $button.data('product_id');
            
            // Track the add to cart action
            this.trackAction('add_to_cart', productId);

            // Visual feedback
            $button.addClass('loading');
            
            // The default WooCommerce AJAX will handle the actual add to cart
            // We just track it here
        },

        /**
         * Track recommendation click
         */
        trackRecommendationClick: function(e) {
            const $link = $(e.currentTarget);
            const $item = $link.closest('.mulopimfwc-recommendation-item');
            const productId = $item.find('.add_to_cart_button').data('product_id');
            
            if (productId) {
                this.trackAction('click', productId);
            }
        },

        /**
         * Track action via AJAX
         */
        trackAction: function(actionType, productId) {
            $.ajax({
                url: mulopimfwcRecommendations.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mulopimfwc_track_recommendation_action',
                    action_type: actionType,
                    product_id: productId,
                    nonce: mulopimfwcRecommendations.nonce
                },
                // Silent tracking - no need to handle response
                error: function() {
                    // Silently fail
                }
            });
        },

        /**
         * Get cookie value
         */
        getCookie: function(name) {
            const value = `; ${document.cookie}`;
            const parts = value.split(`; ${name}=`);
            if (parts.length === 2) {
                return parts.pop().split(';').shift();
            }
            return null;
        },

        /**
         * Format price
         */
        formatPrice: function(price) {
            // This would need to respect WooCommerce currency settings
            return '$' + parseFloat(price).toFixed(2);
        }
    };

    /**
     * Smooth scroll to recommendations
     */
    function scrollToRecommendations() {
        const $recommendations = $('.mulopimfwc-recommendations-container');
        
        if ($recommendations.length) {
            $('html, body').animate({
                scrollTop: $recommendations.offset().top - 100
            }, 600);
        }
    }

    /**
     * Handle WooCommerce added to cart event
     */
    $(document.body).on('added_to_cart', function(event, fragments, cart_hash, $button) {
        if ($button.closest('.mulopimfwc-recommendation-item').length) {
            // Show success message
            const $item = $button.closest('.mulopimfwc-recommendation-item');
            const $message = $('<div class="mulopimfwc-added-message">Added to cart!</div>');
            
            $item.append($message);
            
            setTimeout(function() {
                $message.fadeOut(function() {
                    $(this).remove();
                });
            }, 2000);
        }
    });

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        RecommendationsManager.init();

        // Add CSS for added message
        $('<style>')
            .text(`
                .mulopimfwc-added-message {
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    background: rgba(40, 167, 69, 0.95);
                    color: white;
                    padding: 12px 24px;
                    border-radius: 4px;
                    font-weight: 600;
                    z-index: 999;
                    animation: slideInUp 0.3s ease;
                }
                
                @keyframes slideInUp {
                    from {
                        opacity: 0;
                        transform: translate(-50%, -40%);
                    }
                    to {
                        opacity: 1;
                        transform: translate(-50%, -50%);
                    }
                }
            `)
            .appendTo('head');
    });

    /**
     * Export for external use
     */
    window.MulopimfwcRecommendations = RecommendationsManager;

})(jQuery);