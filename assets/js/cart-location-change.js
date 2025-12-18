jQuery(document).ready(function ($) {
    'use strict';

    // Track which cart items already have selectors to prevent duplicates
    const processedCartItems = new Set();
    
    // Function to inject location selectors into cart blocks
    function injectLocationSelectorsIntoBlocks() {
        // Find cart item rows in blocks - try multiple selectors
        const cartRows = document.querySelectorAll('.wc-block-cart-item, .wc-block-cart-items__row, tr.wc-block-cart-items__row');
        
        cartRows.forEach(function(row) {
            // Check if selector already exists in this row
            if (row.querySelector('.mulopimfwc-cart-location-selector')) {
                return;
            }

            // Try to find product name element or product details container
            const productNameEl = row.querySelector('.wc-block-components-product-name, .wc-block-cart-item__product-name, .wc-block-components-product-details__name');
            const productDetails = row.querySelector('.wc-block-components-product-details');
            
            if (!productNameEl && !productDetails) {
                return;
            }

            // Try to get cart item key from various sources
            let cartItemKey = row.getAttribute('data-cart-item-key') || 
                            row.closest('[data-cart-item-key]')?.getAttribute('data-cart-item-key');
            
            // If not found, try to extract from WooCommerce cart data
            if (!cartItemKey && window.wp && window.wp.data) {
                try {
                    const cartStore = window.wp.data.select('wc/store/cart');
                    if (cartStore && cartStore.getCartData) {
                        const cartData = cartStore.getCartData();
                        if (cartData && cartData.items) {
                            // Try to match by product name or other identifier
                            const productName = productNameEl ? productNameEl.textContent.trim() : '';
                            const matchedItem = cartData.items.find(function(item) {
                                return item.name === productName || 
                                       (productNameEl && item.name && productNameEl.textContent.includes(item.name));
                            });
                            if (matchedItem && matchedItem.key) {
                                cartItemKey = matchedItem.key;
                            }
                        }
                    }
                } catch(e) {
                    // Ignore errors
                }
            }

            // If still no cart item key, try to get from the row's data attributes or class
            if (!cartItemKey) {
                // Try to find a link or element with cart item key
                const removeLink = row.querySelector('a[href*="remove-item"], button[data-cart-item-key]');
                if (removeLink) {
                    const href = removeLink.getAttribute('href') || '';
                    const match = href.match(/remove-item[\/=]([^&\/\?]+)/);
                    if (match && match[1]) {
                        cartItemKey = decodeURIComponent(match[1]);
                    } else {
                        cartItemKey = removeLink.getAttribute('data-cart-item-key');
                    }
                }
            }
            
            if (!cartItemKey) {
                return;
            }

            // Get product ID - try multiple methods
            let productId = row.getAttribute('data-product-id');
            let variationId = row.getAttribute('data-variation-id') || '0';
            
            // Try to get from WooCommerce cart data
            if (!productId && window.wp && window.wp.data) {
                try {
                    const cartStore = window.wp.data.select('wc/store/cart');
                    if (cartStore && cartStore.getCartData) {
                        const cartData = cartStore.getCartData();
                        if (cartData && cartData.items) {
                            const matchedItem = cartData.items.find(function(item) {
                                return item.key === cartItemKey;
                            });
                            if (matchedItem) {
                                productId = matchedItem.id || matchedItem.product_id;
                                variationId = matchedItem.variation_id || '0';
                            }
                        }
                    }
                } catch(e) {
                    // Ignore errors
                }
            }

            // Try to extract from product link
            if (!productId && productNameEl) {
                const productLink = productNameEl.querySelector('a');
                if (productLink) {
                    const href = productLink.getAttribute('href');
                    const match = href ? href.match(/product[\/=]([^&\/\?]+)/) : null;
                    if (match && match[1]) {
                        productId = match[1];
                    }
                }
            }

            if (!productId) {
                return;
            }

            // Check if we've already processed this cart item (after we have all the IDs)
            const itemKey = cartItemKey + '_' + (productId || '') + '_' + (variationId || '0');
            if (processedCartItems.has(itemKey)) {
                return;
            }

            // Mark as processing to prevent duplicate AJAX calls
            processedCartItems.add(itemKey);

            // Fetch available locations via AJAX
            $.ajax({
                url: mulopimfwcCartLocationChange.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'get_cart_item_locations',
                    cart_item_key: cartItemKey,
                    product_id: productId,
                    variation_id: variationId,
                    nonce: mulopimfwcCartLocationChange.nonce
                },
                success: function(response) {
                    if (response.success && response.data && response.data.locations && response.data.locations.length > 1) {
                        // Double-check selector doesn't already exist (race condition protection)
                        const existingSelector = row.querySelector('.mulopimfwc-cart-location-selector');
                        if (existingSelector) {
                            return;
                        }

                        // Create selector HTML
                        let optionsHtml = '';
                        response.data.locations.forEach(function(location) {
                            const selected = location.slug === response.data.current_location ? 'selected' : '';
                            optionsHtml += '<option value="' + location.slug + '" ' + selected + '>' + location.name + '</option>';
                        });

                        const selectorHtml = '<div class="mulopimfwc-cart-location-selector" data-cart-item-key="' + cartItemKey + '" data-product-id="' + productId + '" data-variation-id="' + variationId + '">' +
                            '<label for="cart-location-' + cartItemKey + '" style="font-size: 0.9em; font-weight: 600; display: block; margin-top: 8px; margin-bottom: 4px;">Change Location:</label>' +
                            '<select name="cart_location[' + cartItemKey + ']" id="cart-location-' + cartItemKey + '" class="mulopimfwc-cart-location-select" style="width: 100%; max-width: 300px; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px;">' +
                            optionsHtml +
                            '</select>' +
                            '</div>';

                        // Insert after product name or in product details
                        const targetContainer = productDetails || productNameEl.parentElement;
                        if (targetContainer) {
                            $(targetContainer).append(selectorHtml);
                        } else if (productNameEl) {
                            $(productNameEl).after(selectorHtml);
                        }
                    } else {
                        // Remove from processed set if failed, so we can retry later
                        processedCartItems.delete(itemKey);
                    }
                },
                error: function() {
                    // Remove from processed set on error, so we can retry later
                    processedCartItems.delete(itemKey);
                }
            });
        });
    }

    // Debounce function to prevent too many calls
    let injectTimeout = null;
    function debouncedInject() {
        if (injectTimeout) {
            clearTimeout(injectTimeout);
        }
        injectTimeout = setTimeout(function() {
            injectLocationSelectorsIntoBlocks();
        }, 200);
    }

    // Run on page load
    injectLocationSelectorsIntoBlocks();

    // Watch for cart updates (for block-based carts) - with debouncing
    if (window.wp && window.wp.data && window.wp.data.subscribe) {
        window.wp.data.subscribe(function() {
            debouncedInject();
        });
    }

    // Also watch for DOM changes - with debouncing
    const observer = new MutationObserver(function(mutations) {
        let shouldInject = false;
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length > 0) {
                // Check if any added node is a cart item row
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) { // Element node
                        if (node.matches && (node.matches('.wc-block-cart-item, .wc-block-cart-items__row, tr.wc-block-cart-items__row') || 
                            node.querySelector('.wc-block-cart-item, .wc-block-cart-items__row, tr.wc-block-cart-items__row'))) {
                            shouldInject = true;
                        }
                    }
                });
            }
        });
        if (shouldInject) {
            debouncedInject();
        }
    });

    // Observe cart container
    const cartContainer = document.querySelector('.wc-block-cart, .wp-block-woocommerce-cart');
    if (cartContainer) {
        observer.observe(cartContainer, { childList: true, subtree: true });
    }

    // Handle location change in cart
    $(document).on('change', '.mulopimfwc-cart-location-select', function () {
        const $select = $(this);
        const $selector = $select.closest('.mulopimfwc-cart-location-selector');
        const cartItemKey = $selector.data('cart-item-key');
        const newLocationSlug = $select.val();
        const $originalValue = $select.data('original-value') || $select.val();

        // Prevent multiple simultaneous requests
        if ($selector.hasClass('updating')) {
            $select.val($originalValue);
            return;
        }

        // Store original value
        $select.data('original-value', $originalValue);

        // Add updating class
        $selector.addClass('updating');
        $select.prop('disabled', true);

        // Show updating text
        const originalText = $select.find('option:selected').text();
        const $option = $select.find('option:selected');
        const originalOptionText = $option.text();
        $option.text(originalOptionText + ' (' + mulopimfwcCartLocationChange.updatingText + ')');

        // Make AJAX request
        $.ajax({
            url: mulopimfwcCartLocationChange.ajaxUrl,
            type: 'POST',
            data: {
                action: 'update_cart_item_location',
                cart_item_key: cartItemKey,
                location_slug: newLocationSlug,
                nonce: mulopimfwcCartLocationChange.nonce
            },
            success: function (response) {
                if (response.success) {
                    // Update option text back
                    $option.text(originalOptionText);
                    
                    // Update original value
                    $select.data('original-value', newLocationSlug);

                    // Trigger WooCommerce cart fragments update
                    if (typeof $ !== 'undefined' && typeof wc_add_to_cart_params !== 'undefined') {
                        // Trigger cart update
                        $('body').trigger('update_checkout');
                        $('body').trigger('wc_fragment_refresh');
                        $('body').trigger('updated_wc_div');
                        
                        // Also trigger cart updated event
                        $(document.body).trigger('wc_update_cart');
                    }

                    // For block-based carts, trigger refresh
                    if (window.wp && window.wp.data) {
                        try {
                            const cartStore = window.wp.data.dispatch('wc/store/cart');
                            if (cartStore && cartStore.invalidateResolutionForStoreSelector) {
                                cartStore.invalidateResolutionForStoreSelector('getCartData');
                            }
                        } catch(e) {
                            // Ignore errors
                        }
                    }

                    // Reload page to ensure all cart data is refreshed
                    // This ensures prices, totals, and location displays are all updated correctly
                    setTimeout(function () {
                        window.location.reload();
                    }, 300);
                } else {
                    // Revert selection on error
                    $select.val($originalValue);
                    $option.text(originalOptionText);
                    alert(response.data && response.data.message ? response.data.message : mulopimfwcCartLocationChange.errorText);
                }
            },
            error: function (xhr, status, error) {
                // Revert selection on error
                $select.val($originalValue);
                $option.text(originalOptionText);
                alert(mulopimfwcCartLocationChange.errorText);
            },
            complete: function () {
                // Remove updating class
                $selector.removeClass('updating');
                $select.prop('disabled', false);
            }
        });
    });

    // Handle WooCommerce cart fragments update
    $(document.body).on('wc_fragment_refresh', function () {
        // Clear processed items when cart is refreshed to allow re-injection
        processedCartItems.clear();
        
        // Re-initialize if needed
        $('.mulopimfwc-cart-location-select').each(function () {
            const $select = $(this);
            if (!$select.data('original-value')) {
                $select.data('original-value', $select.val());
            }
        });
        // Re-inject selectors for blocks with debouncing
        debouncedInject();
    });

    // Clear processed items when cart items are removed
    $(document.body).on('removed_from_cart', function() {
        processedCartItems.clear();
        debouncedInject();
    });
});
