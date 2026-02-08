jQuery(function ($) {
    const locationI18n = (window.mulopimfwc_locationWiseProducts && mulopimfwc_locationWiseProducts.i18n) || {};
    const selectStoreLocationAlert = locationI18n.selectStoreLocation || 'Please select a store location.';
    // Function to initialize popup layout modals
    function initializePopupLayouts() {
        // Handle all location info popup modals (including instance-specific ones)
        var $modals = $('#lwp-store-selector-modal.lwp-location-info-popup, [id^="lwp-store-selector-modal-"].lwp-location-info-popup').not('.mulopimfwc-initialized');
        if (!$modals.length) {
            return;
        }
        
        // Process each modal
        $modals.each(function() {
            var $modal = $(this);
            initPopupLayouts($modal);
            $modal.addClass('mulopimfwc-initialized');
        });
    }
    
    // Initialize on page load
    initializePopupLayouts();
    
    // Use MutationObserver for better compatibility (DOMNodeInserted is deprecated)
    if (typeof MutationObserver !== 'undefined') {
        var observer = new MutationObserver(function(mutations) {
            var shouldInit = false;
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length) {
                    for (var i = 0; i < mutation.addedNodes.length; i++) {
                        var node = mutation.addedNodes[i];
                        if (node.nodeType === 1) { // Element node
                            var $node = $(node);
                            if ($node.is('#lwp-store-selector-modal.lwp-location-info-popup, [id^="lwp-store-selector-modal-"].lwp-location-info-popup') ||
                                $node.find('#lwp-store-selector-modal.lwp-location-info-popup, [id^="lwp-store-selector-modal-"].lwp-location-info-popup').length) {
                                shouldInit = true;
                                break;
                            }
                        }
                    }
                }
            });
            if (shouldInit) {
                setTimeout(initializePopupLayouts, 100);
            }
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    } else {
        // Fallback for older browsers
        $(document).on('DOMNodeInserted', function(e) {
            var $target = $(e.target);
            var $newModals = $target.find('#lwp-store-selector-modal.lwp-location-info-popup, [id^="lwp-store-selector-modal-"].lwp-location-info-popup').add(
                $target.filter('#lwp-store-selector-modal.lwp-location-info-popup, [id^="lwp-store-selector-modal-"].lwp-location-info-popup')
            ).not('.mulopimfwc-initialized');
            if ($newModals.length) {
                setTimeout(initializePopupLayouts, 100);
            }
        });
    }
    
    // Also check after a delay to catch modals rendered in footer
    setTimeout(initializePopupLayouts, 500);
    
    function initPopupLayouts($modal) {
        // Skip if already initialized
        if ($modal.hasClass('mulopimfwc-initialized')) {
            return;
        }
        
        var $hidden = $modal.find('#lwp-selected-store');

    function isInteractiveTarget(event) {
        return $(event.target).closest('a, button, input, select, textarea, .mulopimfwc-btn').length > 0;
    }

    function setSelected($item) {
        if (!$item || !$item.length) {
            return;
        }

        var slug = $item.data('location-slug');
        if (!slug) {
            return;
        }

        $hidden.val(slug);

        if ($item.hasClass('mulopimfwc-tab-item')) {
            $modal.find('.mulopimfwc-tab-item').removeClass('is-selected');
            $item.addClass('is-selected');
        } else {
            $modal.find('.mulopimfwc-grid-location-item').removeClass('is-selected');
            $item.addClass('is-selected');
        }
    }

    function initSelection() {
        var $activeTab = $modal.find('.mulopimfwc-tab-item.active[data-location-slug]').first();
        if ($activeTab.length) {
            setSelected($activeTab);
            return;
        }

        var $firstGrid = $modal.find('.mulopimfwc-grid-location-item[data-location-slug]').first();
        if ($firstGrid.length) {
            setSelected($firstGrid);
        }
    }

    $modal.on('click', '.mulopimfwc-tab-item[data-location-slug]', function (event) {
        if (isInteractiveTarget(event)) {
            return;
        }
        setSelected($(this));
    });

    $modal.on('click', '.mulopimfwc-grid-location-item[data-location-slug]', function (event) {
        if (isInteractiveTarget(event)) {
            return;
        }
        setSelected($(this));
    });

    $modal.on('input', '.mulopimfwc-location-search', function () {
        var $current = $modal.find('.mulopimfwc-grid-location-item.is-selected');
        if ($current.length && !$current.is(':visible')) {
            var $firstVisible = $modal.find('.mulopimfwc-grid-location-item:visible').first();
            if ($firstVisible.length) {
                setSelected($firstVisible);
            }
        }
    });

    initSelection();

    // Handle submit button click
    $modal.on('click', '#lwp-store-selector-submit', function (e) {
        e.preventDefault();
        var slug = $hidden.val();
        if (!slug) {
            alert(selectStoreLocationAlert);
            return;
        }
        
        // Set cookie
        var cookieDays = 30;
        if (window.mulopimfwc_locationWiseProducts && mulopimfwc_locationWiseProducts.cookie_expiry) {
            var override = parseInt(mulopimfwc_locationWiseProducts.cookie_expiry, 10);
            if (Number.isFinite(override) && override > 0) {
                cookieDays = override;
            }
        }
        // FIXED: Validate location slug and add secure flag for HTTPS
        var expiryDate = new Date(Date.now() + cookieDays * 24 * 60 * 60 * 1000);
        var cookieName = (window.mulopimfwc_locationWiseProducts && mulopimfwc_locationWiseProducts.cookieName)
            ? mulopimfwc_locationWiseProducts.cookieName
            : 'mulopimfwc_store_location';
        var cookiePath = (window.mulopimfwc_locationWiseProducts && mulopimfwc_locationWiseProducts.cookiePath)
            ? mulopimfwc_locationWiseProducts.cookiePath
            : '/';
        var sameSite = (window.mulopimfwc_locationWiseProducts && mulopimfwc_locationWiseProducts.cookieSameSite)
            ? String(mulopimfwc_locationWiseProducts.cookieSameSite).toLowerCase()
            : 'lax';
        var isSecure = (window.mulopimfwc_locationWiseProducts && typeof mulopimfwc_locationWiseProducts.cookieSecure === 'boolean')
            ? mulopimfwc_locationWiseProducts.cookieSecure
            : window.location.protocol === 'https:';
        var cookieDomain = (window.mulopimfwc_locationWiseProducts && mulopimfwc_locationWiseProducts.cookieDomain)
            ? mulopimfwc_locationWiseProducts.cookieDomain
            : '';
        var cookieString = cookieName + '=' + encodeURIComponent(slug) +
                          ';expires=' + expiryDate.toUTCString() +
                          ';path=' + cookiePath +
                          ';samesite=' + sameSite;
        if (cookieDomain) {
            cookieString += ';domain=' + cookieDomain;
        }
        if (isSecure) {
            cookieString += ';secure';
        }
        document.cookie = cookieString;
        
        // FIXED: Validate cookie was set by making AJAX call to server
        if (window.mulopimfwc_locationWiseProducts && mulopimfwc_locationWiseProducts.ajax_url) {
            jQuery.post(mulopimfwc_locationWiseProducts.ajax_url, {
                action: 'mulopimfwc_validate_location',
                location_slug: slug,
                nonce: mulopimfwc_locationWiseProducts.nonce || ''
            }).fail(function() {
                var clearCookie = cookieName + '=;expires=Thu, 01 Jan 1970 00:00:00 UTC;path=' + cookiePath + ';samesite=' + sameSite;
                if (cookieDomain) {
                    clearCookie += ';domain=' + cookieDomain;
                }
                if (isSecure) {
                    clearCookie += ';secure';
                }
                document.cookie = clearCookie;
            });
        }
        
        // Hide modal and reload with cache-busting parameter
        $modal.hide();
        $('body').removeClass('mulopimfwc-modal-open');
        
        // Add cache-busting parameter to force fresh page load
        var url = window.location.href.split('?')[0];
        var separator = url.indexOf('?') !== -1 ? '&' : '?';
        url += separator + 'mulopimfwc_loc=' + encodeURIComponent(slug) + '&_t=' + Date.now();
        window.location.href = url;
    });
    }
});
