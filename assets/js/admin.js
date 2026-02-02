function mulopimfwc_toggleDisabledClass(isDisabled, allinputFields) {
    // If a jQuery object is passed, convert to array
    if (window.jQuery && allinputFields instanceof jQuery) {
        allinputFields = allinputFields.toArray();
    }

    if (!Array.isArray(allinputFields)) {
        if (isDisabled) {
            allinputFields.classList.add('disabled');
            allinputFields.readOnly = true;
        } else {
            allinputFields.classList.remove('disabled');
            allinputFields.readOnly = false;
        }
    } else {
        allinputFields.forEach(field => {
            if (isDisabled) {
                field.classList.add('disabled');
                field.readOnly = true;
            } else {
                field.classList.remove('disabled');
                field.readOnly = false;
            }
        });
    }
}



jQuery(document).ready(function ($) {
    var manageModalState = null;
    // Handle "Manage Product" button click (combined Edit Location + Quick Edit)
    $(document).on('click', '.manage-product-location', function (e) {
        e.preventDefault();
        var $button = $(this);
        var productId = $button.data('product-id');
        var productType = $button.data('product-type');
        openManageProductModal(productId, productType, $button);
    });

    // Handle "Add to Location" button click (for products without locations)
    $(document).on('click', '.add-location', function (e) {
        e.preventDefault();
        var $button = $(this);
        var productId = $button.data('product-id');
        var productType = $button.data('product-type');
        openManageProductModal(productId, productType, $button);
    });

    // Handle "Activate/Deactivate" location button clicks
    $(document).on('click', '.activate-location, .deactivate-location', function (e) {
        e.preventDefault();

        var $button = $(this);
        var productId = $button.data('product-id');
        var locationId = $button.data('location-id');
        var action = $button.data('action');

        // Show loading state
        $button.addClass('updating-message').prop('disabled', true);

        // Make AJAX request to handle activation/deactivation
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'update_product_location_status',
                product_id: productId,
                location_id: locationId,
                status_action: action,
                security: mulopimfwc_locationWiseProducts.nonce
            },
            success: function (response) {
                if (response.success) {
                    // Update button text and classes
                    if (action === 'activate') {
                        $button.text(mulopimfwc_locationWiseProducts.i18n.deactivate)
                            .removeClass('button-primary activate-location')
                            .addClass('button-secondary deactivate-location')
                            .data('action', 'deactivate');
                        
                        // Show success message
                        showNotice(response.data.message, 'success');
                    } else {
                        // Deactivation removes the location from product - reload page
                        showNotice(response.data.message, 'success');
                        // Reload page after a short delay to show the message
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    }
                } else {
                    // Show error message
                    showNotice(response.data.message, 'error');
                }
            },
            error: function () {
                showNotice(mulopimfwc_locationWiseProducts.i18n.ajaxError, 'error');
            },
            complete: function () {
                // Remove loading state
                $button.removeClass('updating-message').prop('disabled', false);
            }
        });
    });

    // Function to open location selector modal/dropdown - instant, no AJAX needed
    function openLocationSelector(productId, $button) {
        // Get locations data from data attribute (already loaded on page)
        var locationsJson = $button.data('locations');
        
        if (!locationsJson) {
            // Fallback: if data not available, use AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_available_locations',
                    product_id: productId,
                    security: mulopimfwc_locationWiseProducts.nonce
                },
                success: function (response) {
                    if (response.success) {
                        showLocationModal(productId, response.data.locations);
                    } else {
                        showNotice(response.data.message, 'error');
                    }
                },
                error: function () {
                    showNotice(mulopimfwc_locationWiseProducts.i18n.ajaxError, 'error');
                }
            });
            return;
        }

        // Parse JSON data
        var locations;
        try {
            locations = typeof locationsJson === 'string' ? JSON.parse(locationsJson) : locationsJson;
        } catch (e) {
            showNotice('Error parsing location data.', 'error');
            return;
        }

        // Show modal instantly with data
        showLocationModal(productId, locations);
    }

    // Function to show location selection modal
    function showLocationModal(productId, locations) {
        // Organize locations into hierarchy
        var locationTree = buildLocationTree(locations);

        // Create modal HTML
        var modalHtml = '<div id="location-selector-modal" class="location-modal">' +
            '<div class="location-modal-content">' +
            '<span class="location-modal-close">&times;</span>' +
            '<h3>' + mulopimfwc_locationWiseProducts.i18n.selectLocations + '</h3>' +
            '<div class="location-checkboxes">';

        // Add hierarchical location checkboxes
        modalHtml += renderLocationTree(locationTree, locations, 0, 0, 'product_locations');

        // Add submit button
        modalHtml += '</div>' +
            '<button class="button button-primary save-product-locations" data-product-id="' + productId + '">' +
            mulopimfwc_locationWiseProducts.i18n.saveLocations + '</button>' +
            '</div></div>';

        // Append modal to body and show it
        $('body').append(modalHtml);
        $('#location-selector-modal').show();

        // Handle close button
        $('.location-modal-close').on('click', function () {
            $('#location-selector-modal').remove();
        });

        // Handle save button
        $('.save-product-locations').on('click', function () {
            var selectedLocations = [];
            $('input[name="product_locations[]"]:checked').each(function () {
                selectedLocations.push($(this).val());
            });
            saveProductLocations(productId, selectedLocations);
        });
    }

    // Build location tree structure
    function buildLocationTree(locations) {
        var tree = {};
        var locationsMap = {};

        // Handle both arrays with objects and arrays with just IDs
        var processedLocations = locations.map(function(loc) {
            if (typeof loc === 'object' && loc !== null) {
                return loc;
            } else {
                // If it's just an ID, create a minimal object
                return {
                    id: loc,
                    name: 'Location ' + loc,
                    parent: 0,
                    selected: false
                };
            }
        });

        // Create a map of all locations
        processedLocations.forEach(function (loc) {
            var parentId = loc.parent || 0;
            locationsMap[loc.id] = loc;
            if (!tree[parentId]) {
                tree[parentId] = [];
            }
            tree[parentId].push(loc);
        });

        return tree;
    }

    // Render location tree recursively
    function renderLocationTree(tree, allLocations, parentId, level, namePrefix) {
        level = level || 0;
        namePrefix = namePrefix || 'product_locations'; // Default for compatibility
        var html = '';

        if (!tree[parentId]) {
            return html;
        }

        // Sort locations by name
        tree[parentId].sort(function (a, b) {
            return a.name.localeCompare(b.name);
        });

        tree[parentId].forEach(function (location) {
            var indent = level * 20; // 20px indent per level
            var hasChildren = tree[location.id] && tree[location.id].length > 0;

            html += '<div class="location-item" style="margin-left: ' + indent + 'px;">';
            html += '<label style="font-weight: ' + (level === 0 ? 'bold' : 'normal') + ';">';
            html += '<input type="checkbox" class="location-checkbox" name="' + namePrefix + '[]" value="' + location.id + '" ' +
                (location.selected ? 'checked' : '') + '> ';
            html += escapeHtml(location.name);
            html += '</label>';

            // Render children if any
            if (hasChildren) {
                html += '<div class="location-children">';
                html += renderLocationTree(tree, allLocations, location.id, level + 1, namePrefix);
                html += '</div>';
            }

            html += '</div>';
        });

        return html;
    }

    // Function to save product locations
    function saveProductLocations(productId, locationIds) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'save_product_locations',
                product_id: productId,
                location_ids: locationIds,
                security: mulopimfwc_locationWiseProducts.nonce
            },
            success: function (response) {
                if (response.success) {
                    // Close modal
                    $('#location-selector-modal').remove();

                    // Show success message
                    showNotice(response.data.message, 'success');

                    // Refresh the page to show updated locations
                    location.reload();
                } else {
                    showNotice(response.data.message, 'error');
                }
            },
            error: function () {
                showNotice(mulopimfwc_locationWiseProducts.i18n.ajaxError, 'error');
            }
        });
    }

    // Function to show notices
    function showNotice(message, type) {
        var noticeClass = 'notice notice-' + type + ' is-dismissible';
        var $notice = $('<div class="' + noticeClass + '"><p>' + message + '</p></div>');

        // Remove existing notices
        $('.location-notice').remove();

        // Add new notice
        $('.wp-header-end').after($notice);

        // Make it dismissible
        if (typeof wp !== 'undefined' && wp.notices && wp.notices.removeDismissible) {
            wp.notices.removeDismissible($notice);
        }
    }

    function resetManageModalState(productData, allLocations, productId) {
        var assignedLocations = (productData.locations || []).map(function(loc) {
            return $.extend({}, loc);
        });
        var assignedIds = assignedLocations.map(function(loc) { return loc.id; });
        var availableLocations = (allLocations || []).filter(function(loc) {
            return assignedIds.indexOf(loc.id) === -1;
        });

        manageModalState = {
            productId: productId,
            productData: productData,
            assignedLocations: assignedLocations,
            availableLocations: availableLocations,
            removedLocationIds: [],
            addedLocationIds: [],
            allLocations: allLocations || [],
            currencySymbol: '$'
        };
    }

    function getLocationById(locationId) {
        if (!manageModalState || !manageModalState.allLocations) return null;
        for (var i = 0; i < manageModalState.allLocations.length; i++) {
            if (parseInt(manageModalState.allLocations[i].id, 10) === parseInt(locationId, 10)) {
                return manageModalState.allLocations[i];
            }
        }
        return null;
    }

    function activateManageTab(tabId) {
        $('.manage-tab-btn').removeClass('active');
        $('.manage-tab-panel').removeClass('active');
        $('.manage-tab-btn[data-tab="' + tabId + '"]').addClass('active');
        $('.manage-tab-panel[data-tab="' + tabId + '"]').addClass('active');
    }

    function toggleAddTabAvailability() {
        var hasAvailable = manageModalState && manageModalState.availableLocations && manageModalState.availableLocations.length > 0;
        var $addBtn = $('.manage-tab-add');
        if (!$addBtn.length) return;
        if (hasAvailable) {
            $addBtn.show();
        } else {
            $addBtn.hide();
        }
    }

    function refreshAddLocationTabContent(productId) {
        if (!manageModalState) return;
        var panelHtml = buildAddLocationTabContent(
            manageModalState.availableLocations || [],
            manageModalState.assignedLocations || [],
            productId || manageModalState.productId,
            'add-location'
        );
        var $existingPanel = $('#manage-product-modal .manage-tab-panel[data-tab="add-location"]');
        if ($existingPanel.length) {
            var $newPanel = $(panelHtml);
            $existingPanel.replaceWith($newPanel);
        } else {
            $('.manage-product-tab-content').append(panelHtml);
        }
        toggleAddTabAvailability();
    }

    function showAddTabTooltip($trigger) {
        if ($trigger.data('tooltip-active')) return;
        $trigger.data('tooltip-active', true);
        var $tooltip = $('<span class="manage-add-tooltip">Add location</span>');
        $trigger.append($tooltip);
        setTimeout(function() {
            $tooltip.fadeOut(200, function() {
                $(this).remove();
                $trigger.removeData('tooltip-active');
            });
        }, 1200);
    }

    function addLocationTab(location) {
        if (!manageModalState) return;
        var tabId = 'location-' + location.id;
        if ($('.manage-tab-btn[data-tab="' + tabId + '"]').length) {
            activateManageTab(tabId);
            return;
        }
        var tabBtnHtml = '<button class="manage-tab-btn" data-tab="' + tabId + '">' + 
            escapeHtml(location.name) + 
            '<span class="tab-remove" data-location-id="' + location.id + '" title="Remove location">&times;</span>' +
            '</button>';
        $('.manage-tab-add').before(tabBtnHtml);
        var panelHtml = buildLocationTabContent(location, manageModalState.productData, manageModalState.currencySymbol || '$', tabId);
        $('.manage-product-tab-content').append(panelHtml);
        activateManageTab(tabId);
    }

    function queueLocationRemoval(locationId, locationName) {
        if (!manageModalState) return;
        if (!confirm('Remove "' + locationName + '" from this product? Changes will apply after saving.')) {
            return;
        }
        manageModalState.assignedLocations = manageModalState.assignedLocations.filter(function(loc) {
            return parseInt(loc.id, 10) !== parseInt(locationId, 10);
        });
        var addedIndex = manageModalState.addedLocationIds.indexOf(parseInt(locationId, 10));
        if (addedIndex !== -1) {
            manageModalState.addedLocationIds.splice(addedIndex, 1);
        } else if (manageModalState.removedLocationIds.indexOf(parseInt(locationId, 10)) === -1) {
            manageModalState.removedLocationIds.push(parseInt(locationId, 10));
        }

        var locationData = getLocationById(locationId);
        if (locationData && !(manageModalState.availableLocations || []).some(function(loc) { return parseInt(loc.id, 10) === parseInt(locationId, 10); })) {
            manageModalState.availableLocations.push(locationData);
        }

        var tabId = 'location-' + locationId;
        $('.manage-tab-btn[data-tab="' + tabId + '"]').remove();
        $('.manage-tab-panel[data-tab="' + tabId + '"]').remove();

        activateManageTab('default');
        refreshAddLocationTabContent(manageModalState.productId);
        toggleAddTabAvailability();
        showNotice('Location marked for removal. Save changes to apply.', 'info');
    }

    function updateTabErrorIcons(tabErrors) {
        $('.manage-tab-btn .tab-error-icon').remove();
        $('.manage-tab-btn').removeClass('has-error');
        if (!tabErrors) return;
        Object.keys(tabErrors).forEach(function(tabId) {
            var $btn = $('.manage-tab-btn[data-tab="' + tabId + '"]');
            if ($btn.length) {
                $btn.addClass('has-error');
                if (!$btn.find('.tab-error-icon').length) {
                    $btn.append('<span class="tab-error-icon" title="Validation issue">&#9888;</span>');
                }
            }
        });
    }

    function refreshTabErrorIndicatorsFromDom() {
        var tabErrors = {};
        $('#manage-product-modal .manage-error').each(function() {
                        var $tabPanel = $(this).closest('.manage-tab-panel');
                        if ($tabPanel.length) {
                            var tabId = $tabPanel.data('tab');
                            if (tabId) {
                                tabErrors[tabId] = true;
                            }
                        }
        });
        updateTabErrorIcons(tabErrors);
    }

    // Function to open manage product modal with tabs
    function openManageProductModal(productId, productType, $button) {
        // Get product data from data attribute (already loaded on page)
        var productDataJson = $button.data('product-data');
        var allLocationsJson = $button.data('locations');
        
        if (!productDataJson) {
            showNotice('Product data not available. Please refresh the page.', 'error');
            return;
        }

        // Parse JSON data
        var productData, allLocations;
        try {
            productData = typeof productDataJson === 'string' ? JSON.parse(productDataJson) : productDataJson;
            allLocations = allLocationsJson ? (typeof allLocationsJson === 'string' ? JSON.parse(allLocationsJson) : allLocationsJson) : [];
        } catch (e) {
            showNotice('Error parsing product data.', 'error');
            return;
        }

        resetManageModalState(productData, allLocations, productId);
        
        // Route to appropriate modal based on product type
        var type = productData.product_type || productData.type || productType || '';
        
        if (type === 'grouped') {
            // Simple location selector for grouped products
            showGroupedProductModal(productData, allLocations, productId);
        } else if (type === 'variable') {
            // Variation-first layout for variable products
            showVariableProductModal(productData, allLocations, productId);
        } else {
            // Default tabbed modal for simple/external/affiliate products
            showManageProductTabs(productData, allLocations, productId);
        }
    }

    // Function to show simple grouped product modal (location selector only)
    function showGroupedProductModal(data, allLocations, productId) {
        // Create modal container (no tabs)
        var modalHtml = '<div id="manage-product-modal" class="manage-product-modal grouped-product-modal">' +
            '<div class="manage-product-modal-content">' +
            '<div class="manage-product-header">' +
            '<h2>Manage Locations: ' + escapeHtml(data.name) + '</h2>' +
            '<span class="manage-product-close">&times;</span>' +
            '</div>' +
            '<div class="grouped-product-content">' +
            '<p class="description">Select the locations where this grouped product should be available. Individual child products are managed separately.</p>' +
            '<div class="location-checkbox-container">';
        
        var assignedIds = (data.locations || []).map(function(l) { return l.id; });
        var hasLocations = allLocations && allLocations.length > 0;
        
        if (!hasLocations) {
            modalHtml += '<p class="no-locations-message">No locations available. Please create locations first.</p>';
        } else {
            // Mark assigned locations as selected
            var allLocationsWithSelection = allLocations.map(function(loc) {
                return {
                    id: loc.id,
                    name: loc.name,
                    parent: loc.parent,
                    selected: assignedIds.indexOf(loc.id) !== -1
                };
            });
            
            // Build hierarchical location tree
            var locationTree = buildLocationTree(allLocationsWithSelection);
            modalHtml += renderLocationTree(locationTree, allLocationsWithSelection, 0, 0, 'grouped_locations');
        }
        
        modalHtml += '</div>' +
            '</div>' +
            '<div class="manage-product-footer">' +
            '<button type="button" class="button button-secondary manage-product-cancel">Cancel</button>' +
            '<button type="button" class="button button-primary manage-product-save grouped-product-save" data-product-id="' + productId + '">Save Locations</button>' +
            '</div>' +
            '</div>' +
            '</div>';
        
        $('body').append(modalHtml);
        $('#manage-product-modal').show();
        
        // Mark assigned locations as checked
        assignedIds.forEach(function(locId) {
            $('#manage-product-modal input[name="grouped_locations[]"][value="' + locId + '"]').prop('checked', true);
        });
        
        // Close handlers
        $('.manage-product-close, .manage-product-cancel').on('click', function() {
            $('#manage-product-modal').remove();
        });
        
        // Save handler for grouped products
        $('.grouped-product-save').on('click', function() {
            saveGroupedProductLocations(productId);
        });
        
        // Close on outside click
        $('#manage-product-modal').on('click', function(e) {
            if ($(e.target).is('#manage-product-modal')) {
                $('#manage-product-modal').remove();
            }
        });
    }
    
    // Function to save grouped product locations
    function saveGroupedProductLocations(productId) {
        var selectedLocations = [];
        $('#manage-product-modal input[name="grouped_locations[]"]:checked').each(function() {
            selectedLocations.push($(this).val());
        });
        
        // Show loading
        $('.grouped-product-save').prop('disabled', true).text('Saving...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'save_product_locations',
                product_id: productId,
                location_ids: selectedLocations,
                security: mulopimfwc_locationWiseProducts.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#manage-product-modal').remove();
                    showNotice(response.data.message || 'Locations saved successfully', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showNotice(response.data.message || 'Error saving locations', 'error');
                    $('.grouped-product-save').prop('disabled', false).text('Save Locations');
                }
            },
            error: function() {
                showNotice('Error saving locations', 'error');
                $('.grouped-product-save').prop('disabled', false).text('Save Locations');
            }
        });
    }
    
    // Function to show variation-first variable product modal
    function showVariableProductModal(data, allLocations, productId) {
        var currencySymbol = '$';
        if (typeof wc_add_to_cart_params !== 'undefined' && wc_add_to_cart_params.currency_format_symbol) {
            currencySymbol = wc_add_to_cart_params.currency_format_symbol;
        }
        if (manageModalState) {
            manageModalState.currencySymbol = currencySymbol;
        }
        
        // Create modal container (variation-first layout, no tabs)
        var modalHtml = '<div id="manage-product-modal" class="manage-product-modal variable-product-modal">' +
            '<div class="manage-product-modal-content">' +
            '<div class="manage-product-header">' +
            '<h2>Manage: ' + escapeHtml(data.name) + '</h2>' +
            '<span class="manage-product-close">&times;</span>' +
            '</div>' +
            '<div class="variable-product-content">';
        
        // Build variation accordions
        if (data.variations && data.variations.length > 0) {
            data.variations.forEach(function(variation, index) {
                var variationTitle = Object.values(variation.attributes).join(', ') || 'Variation #' + variation.id;
                var isFirst = index === 0;
                modalHtml += buildVariationAccordion(variation, data, currencySymbol, allLocations, isFirst);
            });
        } else {
            modalHtml += '<p class="no-variations-message">No variations found for this product.</p>';
        }
        
        modalHtml += '</div>' +
            '<div class="manage-product-footer">' +
            '<button type="button" class="button button-secondary manage-product-cancel">Cancel</button>' +
            '<button type="button" class="button button-primary manage-product-save variable-product-save" data-product-id="' + productId + '">Save Changes</button>' +
            '</div>' +
            '</div>' +
            '</div>';
        
        $('body').append(modalHtml);
        $('#manage-product-modal').show();
        
        // Close handlers
        $('.manage-product-close, .manage-product-cancel').on('click', function() {
            $('#manage-product-modal').remove();
        });
        
        // Save handler
        $('.variable-product-save').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var isValid = validateManageProductForm();
            if (isValid) {
                saveManageProductData(productId, data);
            } else {
                showNotice('Please fix the validation errors before saving.', 'error');
            }
        });
        
        // Close on outside click
        $('#manage-product-modal').on('click', function(e) {
            if ($(e.target).is('#manage-product-modal')) {
                $('#manage-product-modal').remove();
            }
        });
        
        // Store data in modal for later use
        var $modal = $('#manage-product-modal');
        $modal.data('product-data', data);
        $modal.data('all-locations', allLocations);
        $modal.data('currency-symbol', currencySymbol);
        
        // Accordion toggle handler
        $(document).off('click', '#manage-product-modal .variation-accordion-header').on('click', '#manage-product-modal .variation-accordion-header', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $header = $(this);
            var accordionId = $header.data('accordion-id');
            var $accordion = $header.closest('.variation-accordion');
            var $content = $accordion.find('.variation-accordion-content');
            var $toggle = $header.find('.accordion-toggle');
            
            // Toggle accordion
            if ($accordion.hasClass('expanded')) {
                $accordion.removeClass('expanded');
                $content.slideUp(300);
                $toggle.text('+');
            } else {
                // Close other accordions
                $('#manage-product-modal .variation-accordion.expanded').each(function() {
                    $(this).removeClass('expanded');
                    $(this).find('.variation-accordion-content').slideUp(300);
                    $(this).find('.accordion-toggle').text('+');
                });
                
                // Open this accordion
                $accordion.addClass('expanded');
                $content.slideDown(300);
                $toggle.text('−');
            }
        });
        
        // Tab switching handler for variations
        $(document).off('click', '#manage-product-modal .variation-tab-btn').on('click', '#manage-product-modal .variation-tab-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Don't toggle if clicking the remove button or add location button
            if ($(e.target).hasClass('tab-remove-btn') || $(e.target).closest('.tab-remove-btn').length) {
                return;
            }
            
            if ($(this).hasClass('add-location-tab-btn')) {
                var variationId = $(this).data('variation-id');
                var $variationAccordion = $(this).closest('.variation-accordion');
                var modalData = $modal.data('product-data');
                var modalLocations = $modal.data('all-locations');
                var modalCurrency = $modal.data('currency-symbol');
                if (modalData && modalLocations && modalCurrency) {
                    showVariationLocationSelector(variationId, $variationAccordion, modalData, modalLocations, modalCurrency);
                }
                return;
            }
            
            var $btn = $(this);
            var tabId = $btn.data('tab');
            var $tabsWrapper = $btn.closest('.variation-tabs-wrapper');
            
            // Update active tab button
            $tabsWrapper.find('.variation-tab-btn').removeClass('active');
            $btn.addClass('active');
            
            // Update active tab panel
            $tabsWrapper.find('.variation-tab-panel').removeClass('active');
            $tabsWrapper.find('#' + tabId).addClass('active');
        });
        
        // Remove location tab handler - remove from ALL variations
        $(document).off('click', '#manage-product-modal .tab-remove-btn').on('click', '#manage-product-modal .tab-remove-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var $btn = $(this);
            var locationId = $btn.data('location-id');
            var modalLocations = $('#manage-product-modal').data('all-locations');
            
            if (!confirm('Remove this location from all variations? Changes will apply after saving.')) {
                return;
            }
            
            // Remove location from ALL variations
            $('#manage-product-modal .variation-accordion').each(function() {
                var varId = $(this).data('variation-id');
                if (!varId) return;
                
                var $tabsWrapper = $(this).find('.variation-tabs-wrapper[data-variation-id="' + varId + '"]');
                var tabId = 'location-' + varId + '-' + locationId;
                var $tabBtn = $tabsWrapper.find('.variation-tab-btn[data-tab="' + tabId + '"]');
                
                if ($tabBtn.length) {
                    // Remove tab button
                    $tabBtn.fadeOut(200, function() {
                        $(this).remove();
                        
                        // Remove tab panel
                        $tabsWrapper.find('#' + tabId).remove();
                        
                        // Activate default tab if removed tab was active
                        if ($tabsWrapper.find('.variation-tab-btn.active').length === 0) {
                            $tabsWrapper.find('.variation-tab-btn[data-tab="default-' + varId + '"]').trigger('click');
                        }
                        
                        // Update "Add Location" button visibility
                        if (modalLocations) {
                            updateAddLocationButtonVisibility(varId, modalLocations);
                        }
                    });
                }
            });
        });
        
        // Setup validation
        setupManageProductValidation();
    }
    
    // Show location selector for a variation
    function showVariationLocationSelector(variationId, $variationAccordion, productData, allLocations, currencySymbol) {
        // Get already assigned locations for this variation
        var assignedIds = [];
        $variationAccordion.find('.variation-tab-btn[data-tab^="location-' + variationId + '-"]').each(function() {
            var tabId = $(this).data('tab');
            if (tabId) {
                var match = tabId.match(/location-\d+-(\d+)/);
                if (match) {
                    assignedIds.push(parseInt(match[1], 10));
                }
            }
        });
        
        // Get available locations
        var availableLocations = allLocations.filter(function(loc) {
            return assignedIds.indexOf(parseInt(loc.id, 10)) === -1;
        });
        
        if (availableLocations.length === 0) {
            showNotice('All available locations are already assigned to this variation.', 'info');
            return;
        }
        
        // Create location selector modal with improved UI
        var modalHtml = '<div id="variation-location-selector-modal" class="location-modal">' +
            '<div class="location-modal-content">' +
            '<h3>' +
            '<span>Select Location for Variation</span>' +
            '<span class="location-modal-close">&times;</span>' +
            '</h3>' +
            '<div class="location-checkboxes">';
        
        var locationTree = buildLocationTree(availableLocations);
        modalHtml += renderLocationTree(locationTree, availableLocations, 0, 0, 'variation_location_select');
        
        modalHtml += '</div>' +
            '<div class="location-modal-footer">' +
            '<button type="button" class="button button-secondary cancel-variation-location-select">Cancel</button> ' +
            '<button type="button" class="button button-primary add-selected-variation-locations" data-variation-id="' + variationId + '">Add Selected Locations</button>' +
            '</div>' +
            '</div></div>';
        
        $('body').append(modalHtml);
        $('#variation-location-selector-modal').fadeIn(200);
        
        // Close handlers
        $('#variation-location-selector-modal').on('click', function(e) {
            if ($(e.target).is('#variation-location-selector-modal')) {
                $('#variation-location-selector-modal').fadeOut(200, function() {
                    $(this).remove();
                });
            }
        });
        
        $('.location-modal-close, .cancel-variation-location-select').on('click', function() {
            $('#variation-location-selector-modal').fadeOut(200, function() {
                $(this).remove();
            });
        });
        
        // Add selected locations
        $('.add-selected-variation-locations').on('click', function() {
            var selectedIds = [];
            $('#variation-location-selector-modal input[name="variation_location_select[]"]:checked').each(function() {
                selectedIds.push(parseInt($(this).val(), 10));
            });
            
            if (selectedIds.length === 0) {
                showNotice('Please select at least one location.', 'error');
                return;
            }
            
            // Add locations to ALL variations (not just the current one)
            var $modal = $('#manage-product-modal');
            var productData = $modal.data('product-data');
            var allVariationIds = [];
            
            // Get all variation IDs
            $('#manage-product-modal .variation-accordion').each(function() {
                var varId = $(this).data('variation-id');
                if (varId) {
                    allVariationIds.push(parseInt(varId, 10));
                }
            });
            
            // Add location to each variation
            allVariationIds.forEach(function(varId) {
                var $tabsWrapper = $('#manage-product-modal .variation-tabs-wrapper[data-variation-id="' + varId + '"]');
                if ($tabsWrapper.length === 0) {
                    return; // Skip if variation tabs wrapper doesn't exist
                }
                
                var $tabsNav = $tabsWrapper.find('.variation-tabs-nav');
                var $tabsContent = $tabsWrapper.find('.variation-tabs-content');
                
                selectedIds.forEach(function(locId) {
                    // Check if location already exists in this variation
                    var existingTabId = 'location-' + varId + '-' + locId;
                    if ($tabsNav.find('.variation-tab-btn[data-tab="' + existingTabId + '"]').length > 0) {
                        return; // Skip if already exists
                    }
                    
                    var location = allLocations.find(function(loc) {
                        return parseInt(loc.id, 10) === locId;
                    });
                    
                    if (location) {
                        var tabId = existingTabId;
                        
                        // Create tab button
                        var tabBtnHtml = '<button type="button" class="variation-tab-btn" data-tab="' + tabId + '">' + 
                            escapeHtml(location.name) + 
                            '<span class="tab-remove-btn" data-location-id="' + location.id + '" data-variation-id="' + varId + '" title="Remove location">&times;</span>' +
                            '</button>';
                        
                        // Insert before "Add Location" button (if it exists), otherwise append
                        var $addBtn = $tabsNav.find('.add-location-tab-btn');
                        if ($addBtn.length) {
                            $addBtn.before(tabBtnHtml);
                        } else {
                            $tabsNav.append(tabBtnHtml);
                        }
                        
                        // Create tab panel
                        var tabPanelHtml = '<div class="variation-tab-panel" id="' + tabId + '">' +
                            buildVariationLocationTab(varId, {
                                id: location.id,
                                name: location.name,
                                stock: '',
                                regular_price: '',
                                sale_price: '',
                                backorders: 'off'
                            }, currencySymbol) +
                            '</div>';
                        
                        $tabsContent.append(tabPanelHtml);
                    }
                });
                
                // Update "Add Location" button visibility for this variation
                updateAddLocationButtonVisibility(varId, allLocations);
            });
            
            // Show message about adding to all variations
            if (allVariationIds.length > 1) {
                showNotice('Location(s) added to all variations. Click "Save Changes" to apply.', 'success');
            } else {
                showNotice('Location(s) added. Click "Save Changes" to apply.', 'success');
            }
            
            // Close modal
            $('#variation-location-selector-modal').remove();
        });
    }
    
    // Update "Add Location" button visibility based on available locations
    function updateAddLocationButtonVisibility(variationId, allLocations) {
        var $tabsNav = $('#manage-product-modal .variation-tabs-wrapper[data-variation-id="' + variationId + '"] .variation-tabs-nav');
        var $addBtn = $tabsNav.find('.add-location-tab-btn');
        
        // Get assigned location IDs
        var assignedIds = [];
        $tabsNav.find('.variation-tab-btn[data-tab^="location-' + variationId + '-"]').each(function() {
            var tabId = $(this).data('tab');
            if (tabId) {
                var match = tabId.match(/location-\d+-(\d+)/);
                if (match) {
                    assignedIds.push(parseInt(match[1], 10));
                }
            }
        });
        
        // Check if there are available locations
        var availableLocations = allLocations.filter(function(loc) {
            return assignedIds.indexOf(parseInt(loc.id, 10)) === -1;
        });
        
        if (availableLocations.length === 0) {
            // Hide button if no available locations
            if ($addBtn.length) {
                $addBtn.fadeOut(200, function() {
                    $(this).remove();
                });
            }
        } else {
            // Show button if there are available locations
            if (!$addBtn.length) {
                var addBtnHtml = '<button type="button" class="variation-tab-btn add-location-tab-btn" data-variation-id="' + variationId + '" title="Add Location">+ Add Location</button>';
                $tabsNav.append(addBtnHtml);
            }
        }
    }
    
    // Build variation accordion (for variable product modal)
    function buildVariationAccordion(variation, data, currencySymbol, allLocations, isExpanded) {
        var variationTitle = Object.values(variation.attributes).join(', ') || 'Variation #' + variation.id;
        var htmlParts = [];
        var accordionId = 'variation-accordion-' + variation.id;
        var expandedClass = isExpanded ? 'expanded' : '';
        var displayStyle = isExpanded ? 'block' : 'none';
        
        // Calculate available locations for this variation
        var assignedLocationIds = [];
        if (variation.locations && variation.locations.length > 0) {
            assignedLocationIds = variation.locations.map(function(loc) {
                return parseInt(loc.id, 10);
            });
        }
        var availableLocations = allLocations.filter(function(loc) {
            return assignedLocationIds.indexOf(parseInt(loc.id, 10)) === -1;
        });
        var hasAvailableLocations = availableLocations.length > 0;
        
        htmlParts.push('<div class="variation-accordion ' + expandedClass + '" data-variation-id="' + variation.id + '">');
        htmlParts.push('<div class="variation-accordion-header" data-accordion-id="' + accordionId + '">');
        htmlParts.push('<h3>' + escapeHtml(variationTitle) + '</h3>');
        htmlParts.push('<span class="accordion-toggle">' + (isExpanded ? '−' : '+') + '</span>');
        htmlParts.push('</div>');
        htmlParts.push('<div class="variation-accordion-content" id="' + accordionId + '" style="display: ' + displayStyle + ';">');
        
        // Build tabs for Default and Locations
        htmlParts.push('<div class="variation-tabs-wrapper" data-variation-id="' + variation.id + '">');
        
        // Tabs navigation
        htmlParts.push('<div class="variation-tabs-nav">');
        htmlParts.push('<button type="button" class="variation-tab-btn active" data-tab="default-' + variation.id + '">Default</button>');
        
        // Location tabs
        if (variation.locations && variation.locations.length > 0) {
            variation.locations.forEach(function(location, locIndex) {
                var locationTabId = 'location-' + variation.id + '-' + location.id;
                htmlParts.push('<button type="button" class="variation-tab-btn" data-tab="' + locationTabId + '">' + 
                    escapeHtml(location.name) + 
                    '<span class="tab-remove-btn" data-location-id="' + location.id + '" data-variation-id="' + variation.id + '" title="Remove location">&times;</span>' +
                    '</button>');
            });
        }
        
        // Add location button - only show if there are available locations
        if (hasAvailableLocations) {
            htmlParts.push('<button type="button" class="variation-tab-btn add-location-tab-btn" data-variation-id="' + variation.id + '" title="Add Location">+ Add Location</button>');
        }
        htmlParts.push('</div>');
        
        // Tab content
        htmlParts.push('<div class="variation-tabs-content">');
        
        // Default tab
        htmlParts.push('<div class="variation-tab-panel active" id="default-' + variation.id + '">');
        htmlParts.push(buildVariationDefaultTab(variation, currencySymbol));
        htmlParts.push('</div>');
        
        // Location tabs
        if (variation.locations && variation.locations.length > 0) {
            variation.locations.forEach(function(location) {
                var locationTabId = 'location-' + variation.id + '-' + location.id;
                htmlParts.push('<div class="variation-tab-panel" id="' + locationTabId + '">');
                htmlParts.push(buildVariationLocationTab(variation.id, location, currencySymbol));
                htmlParts.push('</div>');
            });
        }
        
        htmlParts.push('</div>'); // End tabs content
        htmlParts.push('</div>'); // End tabs wrapper
        htmlParts.push('</div>'); // End accordion content
        htmlParts.push('</div>'); // End accordion
        
        return htmlParts.join('');
    }
    
    // Build default tab content for variation
    function buildVariationDefaultTab(variation, currencySymbol) {
        var htmlParts = [];
        htmlParts.push('<form class="manage-product-form" data-section="variation" data-variation-id="' + variation.id + '">');
        
        htmlParts.push('<div class="manage-form-row">');
        htmlParts.push('<label>Stock Quantity:</label>');
        htmlParts.push('<input type="number" name="variations[' + variation.id + '][default][stock_quantity]" value="' + (variation.default.stock_quantity || '') + '" min="0" step="1">');
        htmlParts.push('</div>');
        
        htmlParts.push('<div class="manage-form-row">');
        htmlParts.push('<label>Regular Price (' + currencySymbol + '):</label>');
        htmlParts.push('<input type="number" name="variations[' + variation.id + '][default][regular_price]" value="' + (variation.default.regular_price || '') + '" min="0" step="0.01">');
        htmlParts.push('</div>');
        
        htmlParts.push('<div class="manage-form-row">');
        htmlParts.push('<label>Sale Price (' + currencySymbol + '):</label>');
        htmlParts.push('<input type="number" name="variations[' + variation.id + '][default][sale_price]" value="' + (variation.default.sale_price || '') + '" min="0" step="0.01">');
        htmlParts.push('</div>');
        
        htmlParts.push('<div class="manage-form-row">');
        htmlParts.push('<label>Backorders:</label>');
        htmlParts.push('<select name="variations[' + variation.id + '][default][backorders]">');
        htmlParts.push('<option value="no"' + (variation.default.backorders === 'no' ? ' selected' : '') + '>Do not allow</option>');
        htmlParts.push('<option value="notify"' + (variation.default.backorders === 'notify' ? ' selected' : '') + '>Allow, but notify customer</option>');
        htmlParts.push('<option value="yes"' + (variation.default.backorders === 'yes' ? ' selected' : '') + '>Allow</option>');
        htmlParts.push('</select>');
        htmlParts.push('</div>');
        
        htmlParts.push('<div class="manage-form-row">');
        htmlParts.push('<label>Purchase Price (' + currencySymbol + '):</label>');
        htmlParts.push('<input type="number" name="variations[' + variation.id + '][default][purchase_price]" value="' + (variation.default.purchase_price || '') + '" min="0" step="0.01">');
        htmlParts.push('</div>');
        
        htmlParts.push('</form>');
        return htmlParts.join('');
    }
    
    // Build location tab content for variation
    function buildVariationLocationTab(variationId, location, currencySymbol) {
        var htmlParts = [];
        htmlParts.push('<form class="manage-product-form" data-section="variation-location" data-variation-id="' + variationId + '" data-location-id="' + location.id + '">');
        
        htmlParts.push('<div class="manage-form-row">');
        htmlParts.push('<label>Stock Quantity:</label>');
        htmlParts.push('<input type="number" name="variations[' + variationId + '][locations][' + location.id + '][stock]" value="' + (location.stock || '') + '" min="0" step="1">');
        htmlParts.push('</div>');
        
        htmlParts.push('<div class="manage-form-row">');
        htmlParts.push('<label>Regular Price (' + currencySymbol + '):</label>');
        htmlParts.push('<input type="number" name="variations[' + variationId + '][locations][' + location.id + '][regular_price]" value="' + (location.regular_price || '') + '" min="0" step="0.01">');
        htmlParts.push('</div>');
        
        htmlParts.push('<div class="manage-form-row">');
        htmlParts.push('<label>Sale Price (' + currencySymbol + '):</label>');
        htmlParts.push('<input type="number" name="variations[' + variationId + '][locations][' + location.id + '][sale_price]" value="' + (location.sale_price || '') + '" min="0" step="0.01">');
        htmlParts.push('</div>');
        
        htmlParts.push('<div class="manage-form-row">');
        htmlParts.push('<label>Backorders:</label>');
        htmlParts.push('<select name="variations[' + variationId + '][locations][' + location.id + '][backorders]">');
        htmlParts.push('<option value="off"' + (location.backorders === 'off' ? ' selected' : '') + '>Do not allow</option>');
        htmlParts.push('<option value="notify"' + (location.backorders === 'notify' ? ' selected' : '') + '>Allow, but notify customer</option>');
        htmlParts.push('<option value="on"' + (location.backorders === 'on' ? ' selected' : '') + '>Allow</option>');
        htmlParts.push('</select>');
        htmlParts.push('</div>');
        
        htmlParts.push('</form>');
        return htmlParts.join('');
    }
    

    // Function to show manage product tabs modal
    function showManageProductTabs(data, allLocations, productId) {
        var currencySymbol = '$';
        if (typeof wc_add_to_cart_params !== 'undefined' && wc_add_to_cart_params.currency_format_symbol) {
            currencySymbol = wc_add_to_cart_params.currency_format_symbol;
        }
        if (manageModalState) {
            manageModalState.currencySymbol = currencySymbol;
        }

        // Create modal container
        var modalHtml = '<div id="manage-product-modal" class="manage-product-modal">' +
            '<div class="manage-product-modal-content">' +
            '<div class="manage-product-header">' +
            '<h2>Manage: ' + escapeHtml(data.name) + '</h2>' +
            '<span class="manage-product-close">&times;</span>' +
            '</div>' +
            '<div class="manage-product-tabs-wrapper">' +
            '<div class="manage-product-tabs"></div>' +
            '<div class="manage-product-tab-content"></div>' +
            '</div>' +
            '<div class="manage-product-footer">' +
            '<button type="button" class="button button-secondary manage-product-cancel">Cancel</button>' +
            '<button type="button" class="button button-primary manage-product-save" data-product-id="' + productId + '">Save Changes</button>' +
            '</div>' +
            '</div>' +
            '</div>';
        $('body').append(modalHtml);
        $('#manage-product-modal').show();

        // Build tabs
        var tabsHtml = [];
        var contentHtml = [];
        var activeTab = 'default';

        // Default tab
        tabsHtml.push('<button class="manage-tab-btn active" data-tab="default">Default</button>');
        contentHtml.push(buildDefaultTabContent(data, currencySymbol, 'default'));

        // Location tabs
        if (data.locations && data.locations.length > 0) {
            data.locations.forEach(function(location) {
                var tabId = 'location-' + location.id;
                tabsHtml.push('<button class="manage-tab-btn" data-tab="' + tabId + '">' + 
                    escapeHtml(location.name) + 
                    '<span class="tab-remove" data-location-id="' + location.id + '" title="Remove location">&times;</span>' +
                    '</button>');
                contentHtml.push(buildLocationTabContent(location, data, currencySymbol, tabId));
            });
        }

        // Add new location tab (+)
        tabsHtml.push('<button class="manage-tab-btn manage-tab-add" data-tab="add-location" title="Add new location">+</button>');
        contentHtml.push(buildAddLocationTabContent(allLocations, data.locations || [], productId, 'add-location'));

        // Variations tabs (if variable product)
        var productType = data.product_type || data.type || '';
        if (productType === 'variable' && data.variations && data.variations.length > 0) {
            data.variations.forEach(function(variation) {
                var variationTitle = Object.values(variation.attributes).join(', ') || 'Variation #' + variation.id;
                var tabId = 'variation-' + variation.id;
                tabsHtml.push('<button class="manage-tab-btn" data-tab="' + tabId + '">' + 
                    escapeHtml(variationTitle.substring(0, 20)) + 
                    '</button>');
                contentHtml.push(buildVariationTabContent(variation, data, currencySymbol, tabId));
            });
        }

        // Set tabs and content
        $('.manage-product-tabs').html(tabsHtml.join(''));
        $('.manage-product-tab-content').html(contentHtml.join(''));

        toggleAddTabAvailability();

        // Tab switching
        $('#manage-product-modal').off('click', '.manage-tab-btn').on('click', '.manage-tab-btn', function() {
            var tabId = $(this).data('tab');
            if (tabId === 'add-location') {
                activateManageTab(tabId);
                showAddTabTooltip($(this));
                return;
            }
            activateManageTab(tabId);
        });

        // Remove location
        $('#manage-product-modal').off('click', '.tab-remove').on('click', '.tab-remove', function(e) {
            e.stopPropagation();
            var locationId = $(this).data('location-id');
            var locationName = $(this).closest('.manage-tab-btn').clone().children().remove().end().text().trim();
            queueLocationRemoval(locationId, locationName);
        });

        // Add location handler
        $('#manage-product-modal').off('click', '.manage-tab-add').on('click', '.manage-tab-add', function() {
            activateManageTab('add-location');
            showAddTabTooltip($(this));
        });

        // Close handlers
        $('.manage-product-close, .manage-product-cancel').on('click', function() {
            $('#manage-product-modal').remove();
        });

        // Save handler
        $('.manage-product-save').on('click', function() {
            if (validateManageProductForm()) {
                saveManageProductData(productId, data);
            }
        });

        // Close on outside click
        $('#manage-product-modal').on('click', function(e) {
            if ($(e.target).is('#manage-product-modal')) {
                $('#manage-product-modal').remove();
            }
        });

        // Setup validation
        setupManageProductValidation();
    }

    // Build default tab content
    function buildDefaultTabContent(data, currencySymbol, tabId) {
        var htmlParts = [];
        var productType = data.product_type || data.type || '';
        var isGrouped = productType === 'grouped';
        var isExternal = productType === 'external' || productType === 'affiliate';
        
        htmlParts.push('<div class="manage-tab-panel active" data-tab="' + tabId + '">');
        htmlParts.push('<form class="manage-product-form" data-section="default">');
        htmlParts.push('<div class="manage-section-title">Default Settings</div>');
        
        // Stock Quantity - not for grouped or external products
        if (!isGrouped && !isExternal) {
            htmlParts.push('<div class="manage-form-row">');
            htmlParts.push('<label>Stock Quantity:</label>');
            htmlParts.push('<input type="number" name="default[stock_quantity]" value="' + (data.default.stock_quantity || '') + '" min="0" step="1">');
            htmlParts.push('</div>');
        }
        
        // Regular Price - not for grouped products
        if (!isGrouped) {
            htmlParts.push('<div class="manage-form-row">');
            htmlParts.push('<label>Regular Price (' + currencySymbol + '):</label>');
            htmlParts.push('<input type="number" name="default[regular_price]" value="' + (data.default.regular_price || '') + '" min="0" step="0.01">');
            htmlParts.push('</div>');
        }
        
        // Sale Price - not for grouped products
        if (!isGrouped) {
            htmlParts.push('<div class="manage-form-row">');
            htmlParts.push('<label>Sale Price (' + currencySymbol + '):</label>');
            htmlParts.push('<input type="number" name="default[sale_price]" value="' + (data.default.sale_price || '') + '" min="0" step="0.01">');
            htmlParts.push('</div>');
        }
        
        // Backorders - not for grouped or external products
        if (!isGrouped && !isExternal) {
            htmlParts.push('<div class="manage-form-row">');
            htmlParts.push('<label>Backorders:</label>');
            htmlParts.push('<select name="default[backorders]">');
            htmlParts.push('<option value="no"' + (data.default.backorders === 'no' ? ' selected' : '') + '>Do not allow</option>');
            htmlParts.push('<option value="notify"' + (data.default.backorders === 'notify' ? ' selected' : '') + '>Allow, but notify customer</option>');
            htmlParts.push('<option value="yes"' + (data.default.backorders === 'yes' ? ' selected' : '') + '>Allow</option>');
            htmlParts.push('</select>');
            htmlParts.push('</div>');
        }
        
        // Purchase Price - not for grouped products
        if (!isGrouped) {
            htmlParts.push('<div class="manage-form-row">');
            htmlParts.push('<label>Purchase Price (' + currencySymbol + '):</label>');
            htmlParts.push('<input type="number" name="default[purchase_price]" value="' + (data.default.purchase_price || '') + '" min="0" step="0.01">');
            htmlParts.push('</div>');
        }
        
        // Purchase Quantity - not for grouped or external products
        if (!isGrouped && !isExternal) {
            htmlParts.push('<div class="manage-form-row">');
            htmlParts.push('<label>Purchase Quantity:</label>');
            htmlParts.push('<input type="number" name="default[purchase_quantity]" value="' + (data.default.purchase_quantity || '') + '" min="0" step="1">');
            htmlParts.push('</div>');
        }
        
        htmlParts.push('</form>');
        htmlParts.push('</div>');
        return htmlParts.join('');
    }

    // Build location tab content
    function buildLocationTabContent(location, data, currencySymbol, tabId) {
        var htmlParts = [];
        var productType = data.product_type || data.type || '';
        var isGrouped = productType === 'grouped';
        var isExternal = productType === 'external' || productType === 'affiliate';
        
        htmlParts.push('<div class="manage-tab-panel" data-tab="' + tabId + '">');
        htmlParts.push('<form class="manage-product-form" data-section="location" data-location-id="' + location.id + '">');
        htmlParts.push('<div class="manage-section-title">' + escapeHtml(location.name) + ' Settings</div>');
        
        // Stock Quantity - not for grouped or external products
        if (!isGrouped && !isExternal) {
            htmlParts.push('<div class="manage-form-row">');
            htmlParts.push('<label>Stock Quantity:</label>');
            htmlParts.push('<input type="number" name="locations[' + location.id + '][stock]" value="' + (location.stock || '') + '" min="0" step="1">');
            htmlParts.push('</div>');
        }
        
        // Regular Price - not for grouped products
        if (!isGrouped) {
            htmlParts.push('<div class="manage-form-row">');
            htmlParts.push('<label>Regular Price (' + currencySymbol + '):</label>');
            htmlParts.push('<input type="number" name="locations[' + location.id + '][regular_price]" value="' + (location.regular_price || '') + '" min="0" step="0.01">');
            htmlParts.push('</div>');
        }
        
        // Sale Price - not for grouped products
        if (!isGrouped) {
            htmlParts.push('<div class="manage-form-row">');
            htmlParts.push('<label>Sale Price (' + currencySymbol + '):</label>');
            htmlParts.push('<input type="number" name="locations[' + location.id + '][sale_price]" value="' + (location.sale_price || '') + '" min="0" step="0.01">');
            htmlParts.push('</div>');
        }
        
        // Backorders - not for grouped or external products
        if (!isGrouped && !isExternal) {
            htmlParts.push('<div class="manage-form-row">');
            htmlParts.push('<label>Backorders:</label>');
            htmlParts.push('<select name="locations[' + location.id + '][backorders]">');
            htmlParts.push('<option value="off"' + (location.backorders === 'off' ? ' selected' : '') + '>Do not allow</option>');
            htmlParts.push('<option value="notify"' + (location.backorders === 'notify' ? ' selected' : '') + '>Allow, but notify customer</option>');
            htmlParts.push('<option value="on"' + (location.backorders === 'on' ? ' selected' : '') + '>Allow</option>');
            htmlParts.push('</select>');
            htmlParts.push('</div>');
        }
        
        // If grouped product, show a message
        if (isGrouped) {
            htmlParts.push('<div class="manage-form-row">');
            htmlParts.push('<p class="description">Grouped products do not have price or stock management. Individual child products are managed separately.</p>');
            htmlParts.push('</div>');
        }
        
        htmlParts.push('</form>');
        htmlParts.push('</div>');
        return htmlParts.join('');
    }

    // Build add location tab content
    function buildAddLocationTabContent(allLocations, assignedLocations, productId, tabId) {
        var assignedIds = assignedLocations.map(function(l) { return l.id; });
        var availableLocations;
        if (manageModalState && allLocations === (manageModalState.availableLocations || [])) {
            availableLocations = (manageModalState.availableLocations || []).slice();
        } else {
            availableLocations = allLocations.filter(function(loc) {
                return assignedIds.indexOf(loc.id) === -1;
            });
        }

        var htmlParts = [];
        htmlParts.push('<div class="manage-tab-panel" data-tab="' + tabId + '">');
        htmlParts.push('<div class="manage-section-title">Add New Location</div>');
        
        if (availableLocations.length === 0) {
            htmlParts.push('<p class="no-locations-message">All available locations are already assigned to this product.</p>');
        } else {
            htmlParts.push('<div class="location-select-grid">');
            availableLocations.forEach(function(location) {
                htmlParts.push('<div class="location-select-item">');
                htmlParts.push('<label>');
                htmlParts.push('<input type="checkbox" name="new_locations[]" value="' + location.id + '">');
                htmlParts.push('<span>' + escapeHtml(location.name) + '</span>');
                htmlParts.push('</label>');
                htmlParts.push('</div>');
            });
            htmlParts.push('</div>');
            htmlParts.push('<button type="button" class="button button-primary add-selected-locations" data-product-id="' + productId + '">Add Selected Locations</button>');
        }
        
        htmlParts.push('</div>');
        return htmlParts.join('');
    }

    // Build variation tab content
    function buildVariationTabContent(variation, data, currencySymbol, tabId) {
        var variationTitle = Object.values(variation.attributes).join(', ') || 'Variation #' + variation.id;
        var htmlParts = [];
        htmlParts.push('<div class="manage-tab-panel" data-tab="' + tabId + '">');
        htmlParts.push('<form class="manage-product-form" data-section="variation" data-variation-id="' + variation.id + '">');
        htmlParts.push('<div class="manage-section-title">' + escapeHtml(variationTitle) + '</div>');
        
        // Default variation settings
        htmlParts.push('<div class="manage-subsection">');
        htmlParts.push('<h4>Default Settings</h4>');
        htmlParts.push('<div class="manage-form-row">');
        htmlParts.push('<label>Stock Quantity:</label>');
        htmlParts.push('<input type="number" name="variations[' + variation.id + '][default][stock_quantity]" value="' + (variation.default.stock_quantity || '') + '" min="0" step="1">');
        htmlParts.push('</div>');
        htmlParts.push('<div class="manage-form-row">');
        htmlParts.push('<label>Regular Price (' + currencySymbol + '):</label>');
        htmlParts.push('<input type="number" name="variations[' + variation.id + '][default][regular_price]" value="' + (variation.default.regular_price || '') + '" min="0" step="0.01">');
        htmlParts.push('</div>');
        htmlParts.push('<div class="manage-form-row">');
        htmlParts.push('<label>Sale Price (' + currencySymbol + '):</label>');
        htmlParts.push('<input type="number" name="variations[' + variation.id + '][default][sale_price]" value="' + (variation.default.sale_price || '') + '" min="0" step="0.01">');
        htmlParts.push('</div>');
        htmlParts.push('<div class="manage-form-row">');
        htmlParts.push('<label>Backorders:</label>');
        htmlParts.push('<select name="variations[' + variation.id + '][default][backorders]">');
        htmlParts.push('<option value="no"' + (variation.default.backorders === 'no' ? ' selected' : '') + '>Do not allow</option>');
        htmlParts.push('<option value="notify"' + (variation.default.backorders === 'notify' ? ' selected' : '') + '>Allow, but notify customer</option>');
        htmlParts.push('<option value="yes"' + (variation.default.backorders === 'yes' ? ' selected' : '') + '>Allow</option>');
        htmlParts.push('</select>');
        htmlParts.push('</div>');
        htmlParts.push('<div class="manage-form-row">');
        htmlParts.push('<label>Purchase Price (' + currencySymbol + '):</label>');
        htmlParts.push('<input type="number" name="variations[' + variation.id + '][default][purchase_price]" value="' + (variation.default.purchase_price || '') + '" min="0" step="0.01">');
        htmlParts.push('</div>');
        htmlParts.push('</div>');

        // Location settings for variation
        if (variation.locations && variation.locations.length > 0) {
            htmlParts.push('<div class="manage-subsection">');
            htmlParts.push('<h4>Location-Wise Settings</h4>');
            variation.locations.forEach(function(location) {
                htmlParts.push('<div class="manage-location-group">');
                htmlParts.push('<h5>' + escapeHtml(location.name) + '</h5>');
                htmlParts.push('<div class="manage-form-row">');
                htmlParts.push('<label>Stock Quantity:</label>');
                htmlParts.push('<input type="number" name="variations[' + variation.id + '][locations][' + location.id + '][stock]" value="' + (location.stock || '') + '" min="0" step="1">');
                htmlParts.push('</div>');
                htmlParts.push('<div class="manage-form-row">');
                htmlParts.push('<label>Regular Price (' + currencySymbol + '):</label>');
                htmlParts.push('<input type="number" name="variations[' + variation.id + '][locations][' + location.id + '][regular_price]" value="' + (location.regular_price || '') + '" min="0" step="0.01">');
                htmlParts.push('</div>');
                htmlParts.push('<div class="manage-form-row">');
                htmlParts.push('<label>Sale Price (' + currencySymbol + '):</label>');
                htmlParts.push('<input type="number" name="variations[' + variation.id + '][locations][' + location.id + '][sale_price]" value="' + (location.sale_price || '') + '" min="0" step="0.01">');
                htmlParts.push('</div>');
                htmlParts.push('<div class="manage-form-row">');
                htmlParts.push('<label>Backorders:</label>');
                htmlParts.push('<select name="variations[' + variation.id + '][locations][' + location.id + '][backorders]">');
                htmlParts.push('<option value="off"' + (location.backorders === 'off' ? ' selected' : '') + '>Do not allow</option>');
                htmlParts.push('<option value="notify"' + (location.backorders === 'notify' ? ' selected' : '') + '>Allow, but notify customer</option>');
                htmlParts.push('<option value="on"' + (location.backorders === 'on' ? ' selected' : '') + '>Allow</option>');
                htmlParts.push('</select>');
                htmlParts.push('</div>');
                htmlParts.push('</div>');
            });
            htmlParts.push('</div>');
        }
        
        htmlParts.push('</form>');
        htmlParts.push('</div>');
        return htmlParts.join('');
    }

    // Remove location from product
    function removeLocationFromProduct(productId, locationId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'remove_product_location',
                product_id: productId,
                location_id: locationId,
                security: mulopimfwc_locationWiseProducts.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('Location removed successfully', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showNotice(response.data.message || 'Error removing location', 'error');
                }
            },
            error: function() {
                showNotice('Error removing location', 'error');
            }
        });
    }

    // Add selected locations handler
    $(document).on('click', '.add-selected-locations', function() {
        var productId = $(this).data('product-id');
        var selectedLocations = [];
        $(this).closest('.manage-tab-panel').find('input[name="new_locations[]"]:checked').each(function() {
            selectedLocations.push($(this).val());
        });

        if (selectedLocations.length === 0) {
            showNotice('Please select at least one location', 'error');
            return;
        }

        if (!manageModalState) {
            showNotice('Unable to queue locations right now. Please reopen Manage Product.', 'error');
            return;
        }

        selectedLocations.forEach(function(idStr) {
            var locId = parseInt(idStr, 10);
            var locData = getLocationById(locId);
            if (!locData) return;

            manageModalState.availableLocations = (manageModalState.availableLocations || []).filter(function(loc) {
                return parseInt(loc.id, 10) !== locId;
            });
            manageModalState.assignedLocations.push({
                id: locData.id,
                name: locData.name,
                stock: '',
                regular_price: '',
                sale_price: '',
                backorders: 'off'
            });
            if (manageModalState.removedLocationIds.indexOf(locId) !== -1) {
                manageModalState.removedLocationIds = manageModalState.removedLocationIds.filter(function(val) { return val !== locId; });
            }
            if (manageModalState.addedLocationIds.indexOf(locId) === -1) {
                manageModalState.addedLocationIds.push(locId);
            }
            addLocationTab(locData);
        });

        refreshAddLocationTabContent(productId);
        toggleAddTabAvailability();
        showNotice('Locations queued. Click \"Save Changes\" to apply.', 'success');
    });

    // Function to setup validation for manage product form
    function setupManageProductValidation() {
        // Remove previous error states on input
        $('#manage-product-modal').on('input change', 'input, select', function() {
            $(this).removeClass('manage-error');
            $(this).closest('.manage-form-row').find('.validation-error').remove();
        });

        // Validate on blur
        $('#manage-product-modal').on('blur', 'input[type="number"]', function() {
            validateManageField($(this));
        });
    }

    // Function to validate a single field in manage product form
    function validateManageField($field) {
        var fieldName = $field.attr('name');
        if (!fieldName) return true;

        // Get product type from modal state
        var productType = '';
        if (manageModalState && manageModalState.productData) {
            productType = manageModalState.productData.product_type || manageModalState.productData.type || '';
        }
        var isGrouped = productType === 'grouped';
        var isExternal = productType === 'external' || productType === 'affiliate';

        var value = parseFloat($field.val()) || 0;
        var isValid = true;
        var errorMessage = '';

        // Get default values (only if fields exist and are applicable)
        var defaultRegularPrice = 0;
        var defaultSalePrice = 0;
        var defaultPurchasePrice = 0;
        var defaultStock = 0;
        var defaultPurchaseQty = 0;
        
        var $regularPriceField = $('#manage-product-modal input[name="default[regular_price]"]');
        var $salePriceField = $('#manage-product-modal input[name="default[sale_price]"]');
        var $purchasePriceField = $('#manage-product-modal input[name="default[purchase_price]"]');
        var $stockField = $('#manage-product-modal input[name="default[stock_quantity]"]');
        var $purchaseQtyField = $('#manage-product-modal input[name="default[purchase_quantity]"]');
        
        if ($regularPriceField.length && !isGrouped) {
            defaultRegularPrice = parseFloat($regularPriceField.val()) || 0;
        }
        if ($salePriceField.length && !isGrouped) {
            defaultSalePrice = parseFloat($salePriceField.val()) || 0;
        }
        if ($purchasePriceField.length && !isGrouped) {
            defaultPurchasePrice = parseFloat($purchasePriceField.val()) || 0;
        }
        if ($stockField.length && !isGrouped && !isExternal) {
            defaultStock = parseFloat($stockField.val()) || 0;
        }
        if ($purchaseQtyField.length && !isGrouped && !isExternal) {
            defaultPurchaseQty = parseFloat($purchaseQtyField.val()) || 0;
        }

        // Validation rules (skip for grouped/external products where applicable)
        if (fieldName.indexOf('default[regular_price]') !== -1 && !isGrouped) {
            if (defaultPurchasePrice > 0 && value < defaultPurchasePrice) {
                isValid = false;
                errorMessage = 'Regular price cannot be less than purchase price (' + defaultPurchasePrice + ')';
            }
            if (defaultSalePrice > 0 && defaultSalePrice >= value) {
                var $saleField = $('#manage-product-modal input[name="default[sale_price]"]');
                if ($saleField.length) {
                    $saleField.addClass('manage-error');
                    showManageFieldError($saleField, 'Sale price must be less than regular price');
                }
            }
        }

        if (fieldName.indexOf('default[sale_price]') !== -1 && !isGrouped) {
            if (defaultRegularPrice > 0 && value >= defaultRegularPrice) {
                isValid = false;
                errorMessage = 'Sale price must be less than regular price (' + defaultRegularPrice + ')';
            }
            if (defaultPurchasePrice > 0 && value > 0 && value < defaultPurchasePrice) {
                isValid = false;
                errorMessage = 'Sale price cannot be less than purchase price (' + defaultPurchasePrice + ')';
            }
        }

        if (fieldName.indexOf('default[stock_quantity]') !== -1 && !isGrouped && !isExternal) {
            if (defaultPurchaseQty > 0 && value > defaultPurchaseQty) {
                isValid = false;
                errorMessage = 'Stock quantity cannot be greater than purchase quantity (' + defaultPurchaseQty + ')';
            }
        }

        // Location-based validations (skip for grouped products)
        if (fieldName.indexOf('locations[') !== -1 && !isGrouped) {
            var match = fieldName.match(/locations\[(\d+)\]\[(\w+)\]/);
            if (match) {
                var fieldType = match[2];
                
                // Skip stock validation for external products
                if (fieldType === 'regular_price' && !isExternal && defaultRegularPrice > 0 && value > defaultRegularPrice) {
                    isValid = false;
                    errorMessage = 'Location regular price cannot be greater than default regular price (' + defaultRegularPrice + ')';
                }
                if (fieldType === 'sale_price' && !isExternal && defaultSalePrice > 0 && value > defaultSalePrice) {
                    isValid = false;
                    errorMessage = 'Location sale price cannot be greater than default sale price (' + defaultSalePrice + ')';
                }
                // Skip stock and backorders validation for external products
                if ((fieldType === 'stock' || fieldType === 'backorders') && isExternal) {
                    return true; // Skip validation for stock/backorders on external products
                }
            }
        }

        // Variation validations
        if (fieldName.indexOf('variations[') !== -1) {
            var varMatch = fieldName.match(/variations\[(\d+)\]\[default\]\[(\w+)\]/);
            if (varMatch) {
                var varId = varMatch[1];
                var varFieldType = varMatch[2];
                var varDefaultRegularPrice = parseFloat($('#manage-product-modal input[name="variations[' + varId + '][default][regular_price]"]').val()) || 0;
                var varDefaultSalePrice = parseFloat($('#manage-product-modal input[name="variations[' + varId + '][default][sale_price]"]').val()) || 0;
                var varDefaultPurchasePrice = parseFloat($('#manage-product-modal input[name="variations[' + varId + '][default][purchase_price]"]').val()) || 0;

                if (varFieldType === 'regular_price' && varDefaultPurchasePrice > 0 && value < varDefaultPurchasePrice) {
                    isValid = false;
                    errorMessage = 'Regular price cannot be less than purchase price (' + varDefaultPurchasePrice + ')';
                }
                if (varFieldType === 'sale_price' && varDefaultRegularPrice > 0 && value >= varDefaultRegularPrice) {
                    isValid = false;
                    errorMessage = 'Sale price must be less than regular price (' + varDefaultRegularPrice + ')';
                }
            }
        }

        if (!isValid) {
            $field.addClass('manage-error');
            showManageFieldError($field, errorMessage);
        }

        return isValid;
    }

    // Function to show field error in manage product form
    function showManageFieldError($field, message) {
        var $row = $field.closest('.manage-form-row');
        $row.find('.validation-error').remove();
        $row.append('<span class="validation-error">' + escapeHtml(message) + '</span>');
        
        // For variable products: Show error at top of accordion and highlight it
        var $accordion = $field.closest('.variation-accordion');
        if ($accordion.length) {
            var varId = $accordion.data('variation-id');
            // Add error class to accordion
            $accordion.addClass('has-error');
            
            // Add error to accordion header (insert before toggle button)
            var $header = $accordion.find('.variation-accordion-header');
            $header.find('.accordion-error-message').remove();
            var $toggle = $header.find('.accordion-toggle');
            if ($toggle.length) {
                $toggle.before('<span class="accordion-error-message">⚠ ' + escapeHtml(message) + '</span>');
            } else {
                $header.append('<span class="accordion-error-message">⚠ ' + escapeHtml(message) + '</span>');
            }
            
            // Find which tab has the error and highlight it
            var $tabPanel = $field.closest('.variation-tab-panel');
            var $tabsNav = $accordion.find('.variation-tabs-nav');
            
            if ($tabPanel.length) {
                var tabId = $tabPanel.attr('id');
                var $tabBtn = $accordion.find('.variation-tab-btn[data-tab="' + tabId + '"]');
                if ($tabBtn.length) {
                    $tabBtn.addClass('has-error');
                }
            } else {
                // Check if it's a default tab field (fields in default tab)
                var fieldName = $field.attr('name');
                if (fieldName && fieldName.indexOf('variations[') === 0 && fieldName.indexOf('[default]') !== -1) {
                    var defaultTabId = 'default-' + varId;
                    var $defaultTabBtn = $accordion.find('.variation-tab-btn[data-tab="' + defaultTabId + '"]');
                    if ($defaultTabBtn.length) {
                        $defaultTabBtn.addClass('has-error');
                        // Activate default tab to show error
                        var $tabsWrapper = $accordion.find('.variation-tabs-wrapper');
                        $tabsWrapper.find('.variation-tab-btn').removeClass('active');
                        $defaultTabBtn.addClass('active');
                        $tabsWrapper.find('.variation-tab-panel').removeClass('active');
                        $tabsWrapper.find('#' + defaultTabId).addClass('active');
                    }
                }
            }
            
            // Show error in tab nav area (only once per accordion)
            if ($tabsNav.find('.tab-nav-error-message').length === 0) {
                $tabsNav.append('<span class="tab-nav-error-message">⚠ Please fix validation errors below</span>');
            }
            
            // Expand accordion if collapsed to show errors
            if (!$accordion.hasClass('expanded')) {
                $accordion.addClass('expanded');
                $accordion.find('.variation-accordion-content').slideDown(300);
                $accordion.find('.accordion-toggle').text('−');
            }
        } else {
            // For simple products: Show error at top of tab and highlight it
            var $tabPanel = $field.closest('.manage-tab-panel');
            if ($tabPanel.length) {
                var tabId = $tabPanel.data('tab');
                var $tabBtn = $('#manage-product-modal .manage-tab-btn[data-tab="' + tabId + '"]');
                if ($tabBtn.length) {
                    $tabBtn.addClass('has-error');
                    // Show error in tab navigation area
                    var $tabsNav = $('#manage-product-modal .manage-product-tabs');
                    $tabsNav.find('.tab-nav-error-message').remove();
                    var tabName = $tabBtn.clone().find('.tab-error-icon').remove().end().text().trim();
                    if (tabName) {
                        $tabsNav.append('<span class="tab-nav-error-message">⚠ Validation errors in ' + escapeHtml(tabName) + ' tab</span>');
                    }
                    
                    // Activate the tab to show errors
                    $('#manage-product-modal .manage-tab-btn').removeClass('active');
                    $tabBtn.addClass('active');
                    $('#manage-product-modal .manage-tab-panel').removeClass('active');
                    $tabPanel.addClass('active');
                }
            }
        }
    }

    // Function to validate entire manage product form
    function validateManageProductForm() {
        var isValid = true;

        // Clear previous errors
        $('#manage-product-modal .manage-error').removeClass('manage-error');
        $('#manage-product-modal .validation-error').remove();
        $('#manage-product-modal .has-error').removeClass('has-error');
        $('#manage-product-modal .accordion-error-message').remove();
        $('#manage-product-modal .tab-nav-error-message').remove();
        updateTabErrorIcons({});
        var tabErrors = {};
        var accordionErrors = {};

        // Get product type from modal
        var productType = '';
        var $modal = $('#manage-product-modal');
        if (manageModalState && manageModalState.productData) {
            productType = manageModalState.productData.product_type || manageModalState.productData.type || '';
        } else if ($modal.length) {
            // Fallback: get from modal data
            var modalData = $modal.data('product-data');
            if (modalData) {
                productType = modalData.product_type || modalData.type || '';
            }
        }
        var isGrouped = productType === 'grouped';
        var isVariable = productType === 'variable';
        var isExternal = productType === 'external' || productType === 'affiliate';

        // Get default values (only if fields exist)
        var defaultRegularPrice = 0;
        var defaultSalePrice = 0;
        var defaultPurchasePrice = 0;
        var defaultStock = 0;
        var defaultPurchaseQty = 0;
        
        if (!$('#manage-product-modal input[name="default[regular_price]"]').length || !isGrouped) {
            defaultRegularPrice = parseFloat($('#manage-product-modal input[name="default[regular_price]"]').val()) || 0;
        }
        if (!$('#manage-product-modal input[name="default[sale_price]"]').length || !isGrouped) {
            defaultSalePrice = parseFloat($('#manage-product-modal input[name="default[sale_price]"]').val()) || 0;
        }
        if (!$('#manage-product-modal input[name="default[purchase_price]"]').length || !isGrouped) {
            defaultPurchasePrice = parseFloat($('#manage-product-modal input[name="default[purchase_price]"]').val()) || 0;
        }
        if (!$('#manage-product-modal input[name="default[stock_quantity]"]').length || isGrouped || isExternal) {
            defaultStock = 0;
        } else {
            defaultStock = parseFloat($('#manage-product-modal input[name="default[stock_quantity]"]').val()) || 0;
        }
        if (!$('#manage-product-modal input[name="default[purchase_quantity]"]').length || isGrouped || isExternal) {
            defaultPurchaseQty = 0;
        } else {
            defaultPurchaseQty = parseFloat($('#manage-product-modal input[name="default[purchase_quantity]"]').val()) || 0;
        }

        // Skip default field validations for variable products (they have variation-specific validations)
        if (!isVariable) {
            // Validation 1: Regular price can't be less than purchase price (skip for grouped products)
            if (!isGrouped && defaultPurchasePrice > 0 && defaultRegularPrice > 0 && defaultRegularPrice < defaultPurchasePrice) {
                var $field = $('#manage-product-modal input[name="default[regular_price]"]');
                if ($field.length) {
                    $field.addClass('manage-error');
                    showManageFieldError($field, 'Regular price cannot be less than purchase price');
                    isValid = false;
                    tabErrors['default'] = true;
                }
            }

            // Validation 2: Sale price can't be greater than or equal to regular price (skip for grouped products)
            if (!isGrouped && defaultSalePrice > 0 && defaultRegularPrice > 0 && defaultSalePrice >= defaultRegularPrice) {
                var $field = $('#manage-product-modal input[name="default[sale_price]"]');
                if ($field.length) {
                    $field.addClass('manage-error');
                    showManageFieldError($field, 'Sale price must be less than regular price');
                    isValid = false;
                    tabErrors['default'] = true;
                }
            }

            // Validation 3: Sale price can't be less than purchase price (skip for grouped products)
            if (!isGrouped && defaultSalePrice > 0 && defaultPurchasePrice > 0 && defaultSalePrice < defaultPurchasePrice) {
                var $field = $('#manage-product-modal input[name="default[sale_price]"]');
                if ($field.length) {
                    $field.addClass('manage-error');
                    showManageFieldError($field, 'Sale price cannot be less than purchase price');
                    isValid = false;
                    tabErrors['default'] = true;
                }
            }

            // Validation 4: Default quantity can't be greater than purchase quantity (skip for grouped/external products)
            if (!isGrouped && !isExternal && defaultPurchaseQty > 0 && defaultStock > defaultPurchaseQty) {
                var $field = $('#manage-product-modal input[name="default[stock_quantity]"]');
                if ($field.length) {
                    $field.addClass('manage-error');
                    showManageFieldError($field, 'Stock quantity cannot be greater than purchase quantity');
                    isValid = false;
                    tabErrors['default'] = true;
                }
            }

            // Validation 5: Sum of all location stock can't be greater than default quantity (skip for grouped/external products)
            if (!isGrouped && !isExternal) {
                var totalLocationStock = 0;
            $('#manage-product-modal input[name*="locations["][name*="[stock]"]').each(function() {
                var name = $(this).attr('name');
                // Only count simple product location stocks, not variation locations
                if (name.indexOf('variations[') === -1) {
                    var stock = parseFloat($(this).val()) || 0;
                    if (stock > 0) {
                        totalLocationStock += stock;
                    }
                }
            });
            if (defaultStock > 0 && totalLocationStock > defaultStock) {
                $('#manage-product-modal input[name*="locations["][name*="[stock]"]').each(function() {
                    var name = $(this).attr('name');
                    if (name.indexOf('variations[') === -1 && parseFloat($(this).val()) > 0) {
                        $(this).addClass('manage-error');
                        showManageFieldError($(this), 'Total location stock exceeds default stock');
                        var $tabPanel = $(this).closest('.manage-tab-panel');
                        if ($tabPanel.length) {
                            var tabId = $tabPanel.data('tab');
                            if (tabId) {
                                tabErrors[tabId] = true;
                            }
                        }
                    }
                });
                showNotice('Total location stock (' + totalLocationStock + ') cannot be greater than default stock quantity (' + defaultStock + ')', 'error');
                isValid = false;
                }
            }
        }

        // Validate location prices (skip for grouped and variable products - variable products have their own validation)
        if (!isGrouped && !isVariable) {
            $('#manage-product-modal input[name*="locations["][name*="[regular_price]"]').each(function() {
                var name = $(this).attr('name');
                // Only validate simple product location prices, not variation locations
                if (name.indexOf('variations[') === -1) {
                    var locationPrice = parseFloat($(this).val()) || 0;
                    if (locationPrice > 0 && defaultRegularPrice > 0 && locationPrice > defaultRegularPrice) {
                        $(this).addClass('manage-error');
                        showManageFieldError($(this), 'Location regular price cannot be greater than default regular price');
                        isValid = false;
                        var $tabPanel = $(this).closest('.manage-tab-panel');
                        if ($tabPanel.length) {
                            var tabId = $tabPanel.data('tab');
                            if (tabId) {
                                tabErrors[tabId] = true;
                            }
                        }
                    }
                }
            });

                $('#manage-product-modal input[name*="locations["][name*="[sale_price]"]').each(function() {
                    var name = $(this).attr('name');
                    // Only validate simple product location prices, not variation locations
                    if (name.indexOf('variations[') === -1) {
                        var locationPrice = parseFloat($(this).val()) || 0;
                        if (locationPrice > 0 && defaultSalePrice > 0 && locationPrice > defaultSalePrice) {
                            $(this).addClass('manage-error');
                            showManageFieldError($(this), 'Location sale price cannot be greater than default sale price');
                            isValid = false;
                            var $tabPanel = $(this).closest('.manage-tab-panel');
                            if ($tabPanel.length) {
                                var tabId = $tabPanel.data('tab');
                                if (tabId) {
                                    tabErrors[tabId] = true;
                                }
                            }
                        }
                    }
                });
        }

        // Validate all variation fields
        $('#manage-product-modal input[name*="variations["]').each(function() {
            if (!validateManageField($(this))) {
                isValid = false;
                // Track accordion errors for variable products
                var $accordion = $(this).closest('.variation-accordion');
                if ($accordion.length) {
                    var varId = $accordion.data('variation-id');
                    if (varId) {
                        accordionErrors[varId] = true;
                    }
                }
                // Track tab errors for simple products
                var $tabPanel = $(this).closest('.manage-tab-panel');
                if ($tabPanel.length) {
                    var tabId = $tabPanel.data('tab');
                    if (tabId) {
                        tabErrors[tabId] = true;
                    }
                }
                // Track variation tab errors
                var $variationTabPanel = $(this).closest('.variation-tab-panel');
                if ($variationTabPanel.length) {
                    var variationTabId = $variationTabPanel.attr('id');
                    if (variationTabId) {
                        tabErrors[variationTabId] = true;
                    }
                }
            }
        });

        // Validate variation location stocks sum
        $('#manage-product-modal input[name*="variations["][name*="[default][stock_quantity]"]').each(function() {
            var varMatch = $(this).attr('name').match(/variations\[(\d+)\]/);
            if (varMatch) {
                var varId = varMatch[1];
                var varDefaultStock = parseFloat($(this).val()) || 0;
                var totalVarLocationStock = 0;

                // Find all location stock inputs for this variation
                $('#manage-product-modal input[name*="variations[' + varId + '][locations]["][name*="[stock]"]').each(function() {
                    var stock = parseFloat($(this).val()) || 0;
                    if (stock > 0) {
                        totalVarLocationStock += stock;
                    }
                });

                if (varDefaultStock > 0 && totalVarLocationStock > varDefaultStock) {
                    $('#manage-product-modal input[name*="variations[' + varId + '][locations]["][name*="[stock]"]').each(function() {
                        if (parseFloat($(this).val()) > 0) {
                            $(this).addClass('manage-error');
                            showManageFieldError($(this), 'Total location stock exceeds variation default stock');
                            var $accordion = $(this).closest('.variation-accordion');
                            if ($accordion.length) {
                                accordionErrors[varId] = true;
                            }
                            var tabId = $(this).closest('.variation-tab-panel').attr('id');
                            if (tabId) {
                                tabErrors[tabId] = true;
                            }
                        }
                    });
                    showNotice('Variation: Total location stock (' + totalVarLocationStock + ') cannot be greater than default stock quantity (' + varDefaultStock + ')', 'error');
                    isValid = false;
                }
            }
        });

        // Validate variation location prices
        $('#manage-product-modal input[name*="variations["][name*="[default][regular_price]"]').each(function() {
            var varMatch = $(this).attr('name').match(/variations\[(\d+)\]/);
            if (varMatch) {
                var varId = varMatch[1];
                var varDefaultRegularPrice = parseFloat($(this).val()) || 0;
                var varDefaultSalePrice = parseFloat($('#manage-product-modal input[name*="variations[' + varId + '][default][sale_price]"]').val()) || 0;

                $('#manage-product-modal input[name*="variations[' + varId + '][locations]["][name*="[regular_price]"]').each(function() {
                    var locPrice = parseFloat($(this).val()) || 0;
                    if (locPrice > 0 && varDefaultRegularPrice > 0 && locPrice > varDefaultRegularPrice) {
                        $(this).addClass('manage-error');
                        showManageFieldError($(this), 'Location regular price cannot be greater than variation default regular price');
                        isValid = false;
                        var $accordion = $(this).closest('.variation-accordion');
                        if ($accordion.length) {
                            accordionErrors[varId] = true;
                        }
                        var tabId = $(this).closest('.variation-tab-panel').attr('id');
                        if (tabId) {
                            tabErrors[tabId] = true;
                        }
                    }
                });

                $('#manage-product-modal input[name*="variations[' + varId + '][locations]["][name*="[sale_price]"]').each(function() {
                    var locPrice = parseFloat($(this).val()) || 0;
                    if (locPrice > 0 && varDefaultSalePrice > 0 && locPrice > varDefaultSalePrice) {
                        $(this).addClass('manage-error');
                        showManageFieldError($(this), 'Location sale price cannot be greater than variation default sale price');
                        isValid = false;
                        var $accordion = $(this).closest('.variation-accordion');
                        if ($accordion.length) {
                            accordionErrors[varId] = true;
                        }
                        var tabId = $(this).closest('.variation-tab-panel').attr('id');
                        if (tabId) {
                            tabErrors[tabId] = true;
                        }
                    }
                });
            }
        });

        // Highlight accordions and tabs with errors
        if (!isValid) {
            // Highlight accordions with errors (variable products)
            Object.keys(accordionErrors).forEach(function(varId) {
                var $accordion = $('#manage-product-modal .variation-accordion[data-variation-id="' + varId + '"]');
                if ($accordion.length) {
                    $accordion.addClass('has-error');
                    // Expand accordion to show errors
                    if (!$accordion.hasClass('expanded')) {
                        $accordion.addClass('expanded');
                        $accordion.find('.variation-accordion-content').slideDown(300);
                        $accordion.find('.accordion-toggle').text('−');
                    }
                }
            });
            
            // Highlight tabs with errors (simple products)
            Object.keys(tabErrors).forEach(function(tabId) {
                // For variation tabs
                var $variationTabBtn = $('#manage-product-modal .variation-tab-btn[data-tab="' + tabId + '"]');
                if ($variationTabBtn.length) {
                    $variationTabBtn.addClass('has-error');
                    // Activate the tab to show errors
                    var $tabsWrapper = $variationTabBtn.closest('.variation-tabs-wrapper');
                    $tabsWrapper.find('.variation-tab-btn').removeClass('active');
                    $variationTabBtn.addClass('active');
                    $tabsWrapper.find('.variation-tab-panel').removeClass('active');
                    $tabsWrapper.find('#' + tabId).addClass('active');
                }
                
                // For simple product tabs
                var $simpleTabBtn = $('#manage-product-modal .manage-tab-btn[data-tab="' + tabId + '"]');
                if ($simpleTabBtn.length) {
                    $simpleTabBtn.addClass('has-error');
                    // Activate the tab to show errors
                    $('#manage-product-modal .manage-tab-btn').removeClass('active');
                    $simpleTabBtn.addClass('active');
                    $('#manage-product-modal .manage-tab-panel').removeClass('active');
                    $('#manage-product-modal .manage-tab-panel[data-tab="' + tabId + '"]').addClass('active');
                }
            });
            
            showNotice('Please fix the validation errors before saving.', 'error');
            // Scroll to first error (prefer accordion header if it's a variable product)
            var $firstAccordionError = $('#manage-product-modal .variation-accordion.has-error').first();
            if ($firstAccordionError.length) {
                $('html, body').animate({
                    scrollTop: $firstAccordionError.offset().top - 100
                }, 500);
            } else {
                var $firstError = $('#manage-product-modal .manage-error').first();
                if ($firstError.length) {
                    $('html, body').animate({
                        scrollTop: $firstError.offset().top - 100
                    }, 500);
                }
            }
        }

        updateTabErrorIcons(tabErrors);
        return isValid;
    }

    // Function to save manage product data
    function saveManageProductData(productId, originalData) {
        var data = {
            action: 'save_product_quick_edit_data',
            product_id: productId,
            security: mulopimfwc_locationWiseProducts.nonce
        };
        var locationIds = [];
        var removedLocationIds = manageModalState && manageModalState.removedLocationIds ? manageModalState.removedLocationIds.slice() : [];

        // Track all visible location forms so assignment happens even if values are empty
        // For simple products: track from location forms
        $('#manage-product-modal .manage-product-form[data-section="location"]').each(function() {
            var locId = parseInt($(this).data('location-id'), 10);
            if (!isNaN(locId) && locationIds.indexOf(locId) === -1) {
                locationIds.push(locId);
            }
        });
        
        // For variable products: track location IDs from variation location tabs
        // Get all location tab panels to ensure we track all assigned locations
        $('#manage-product-modal .variation-tab-panel[id^="location-"]').each(function() {
            var tabId = $(this).attr('id');
            var match = tabId.match(/location-(\d+)-(\d+)/);
            if (match) {
                var locId = parseInt(match[2], 10);
                if (!isNaN(locId) && locationIds.indexOf(locId) === -1) {
                    locationIds.push(locId);
                }
            }
        });
        
        // Also track from any variation location input fields (as backup)
        $('#manage-product-modal input[name*="variations["][name*="[locations]["][name*="[stock]"]').each(function() {
            var name = $(this).attr('name');
            var locMatch = name.match(/variations\[\d+\]\[locations\]\[(\d+)\]/);
            if (locMatch) {
                var locId = parseInt(locMatch[1], 10);
                if (!isNaN(locId) && locationIds.indexOf(locId) === -1) {
                    locationIds.push(locId);
                }
            }
        });

        // First, ensure all location assignments are tracked (even with empty values)
        // For variable products: create location entries for all visible location tabs
        $('#manage-product-modal .variation-tab-panel[id^="location-"]').each(function() {
            var tabId = $(this).attr('id');
            var match = tabId.match(/location-(\d+)-(\d+)/);
            if (match) {
                var varId = parseInt(match[1], 10);
                var locId = parseInt(match[2], 10);
                
                // Initialize variation and location structure if not exists
                if (!data.variations) data.variations = [];
                var varIndex = data.variations.findIndex(function(v) { return v.id === varId; });
                if (varIndex === -1) {
                    data.variations.push({ id: varId });
                    varIndex = data.variations.length - 1;
                }
                if (!data.variations[varIndex].locations) {
                    data.variations[varIndex].locations = [];
                }
                var locIndex = data.variations[varIndex].locations.findIndex(function(l) { return l.id === locId; });
                if (locIndex === -1) {
                    data.variations[varIndex].locations.push({ id: locId });
                }
            }
        });
        
        // Collect all form fields
        $('#manage-product-modal .manage-product-form').each(function() {
            $(this).find('input, select').each(function() {
                var $field = $(this);
                var name = $field.attr('name');
                var value = $field.val();
                
                // Include all fields (even empty) for variation locations to track assignment
                var isVariationLocation = name && name.indexOf('variations[') === 0 && name.indexOf('[locations][') !== -1;
                var shouldInclude = name && value !== null && value !== undefined && (value !== '' || isVariationLocation);

                if (shouldInclude) {
                    // Handle locations structure
                    if (name.indexOf('locations[') === 0) {
                        var match = name.match(/locations\[(\d+)\]\[(\w+)\]/);
                        if (match) {
                            var locId = parseInt(match[1], 10);
                            var fieldName = match[2];
                            if (!data.locations) data.locations = [];
                            var locIndex = data.locations.findIndex(function(l) { return l.id === locId; });
                            if (locIndex === -1) {
                                data.locations.push({ id: locId });
                                locIndex = data.locations.length - 1;
                            }
                            data.locations[locIndex][fieldName] = value;
                        }
                    }
                    // Handle variations structure
                    else if (name.indexOf('variations[') === 0) {
                        var defaultMatch = name.match(/variations\[(\d+)\]\[default\]\[(\w+)\]/);
                        if (defaultMatch) {
                            var varId = parseInt(defaultMatch[1], 10);
                            var fieldName = defaultMatch[2];
                            if (!data.variations) data.variations = [];
                            var varIndex = data.variations.findIndex(function(v) { return v.id === varId; });
                            if (varIndex === -1) {
                                data.variations.push({ id: varId });
                                varIndex = data.variations.length - 1;
                            }
                            if (!data.variations[varIndex].default) {
                                data.variations[varIndex].default = {};
                            }
                            data.variations[varIndex].default[fieldName] = value;
                        } else {
                            var locMatch = name.match(/variations\[(\d+)\]\[locations\]\[(\d+)\]\[(\w+)\]/);
                            if (locMatch) {
                                var varId = parseInt(locMatch[1], 10);
                                var locId = parseInt(locMatch[2], 10);
                                var fieldName = locMatch[3];
                                if (!data.variations) data.variations = [];
                                var varIndex = data.variations.findIndex(function(v) { return v.id === varId; });
                                if (varIndex === -1) {
                                    data.variations.push({ id: varId });
                                    varIndex = data.variations.length - 1;
                                }
                                if (!data.variations[varIndex].locations) {
                                    data.variations[varIndex].locations = [];
                                }
                                var locIndex = data.variations[varIndex].locations.findIndex(function(l) { return l.id === locId; });
                                if (locIndex === -1) {
                                    data.variations[varIndex].locations.push({ id: locId });
                                    locIndex = data.variations[varIndex].locations.length - 1;
                                }
                                // Include value to track location assignment (even empty values for assignment tracking)
                                data.variations[varIndex].locations[locIndex][fieldName] = value || '';
                            }
                        }
                    }
                    // Handle default fields
                    else if (name.indexOf('default[') === 0) {
                        var defMatch = name.match(/default\[(\w+)\]/);
                        if (defMatch) {
                            if (!data.default) data.default = {};
                            data.default[defMatch[1]] = value;
                        }
                    }
                }
            });
        });

        data.location_ids = locationIds;
        data.removed_location_ids = removedLocationIds.filter(function(id) {
            return locationIds.indexOf(id) === -1;
        });

        // Show loading
        $('.manage-product-save').prop('disabled', true).text('Saving...');

        // Send AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: data,
            success: function (response) {
                if (response.success) {
                    $('#manage-product-modal').remove();
                    showNotice(response.data.message || 'Product data saved successfully', 'success');
                    setTimeout(function () {
                        location.reload();
                    }, 1000);
                } else {
                    showNotice(response.data.message || 'Error saving product data', 'error');
                    $('.manage-product-save').prop('disabled', false).text('Save Changes');
                }
            },
            error: function () {
                showNotice('Error saving product data', 'error');
                $('.manage-product-save').prop('disabled', false).text('Save Changes');
            }
        });
    }

    // Function to show quick edit form - optimized with document fragments
    function showQuickEditForm(data) {
        // Create modal container first
        var modalHtml = '<div id="quick-edit-modal" class="quick-edit-modal">' +
            '<div class="quick-edit-modal-content"></div>' +
            '</div>';
        $('body').append(modalHtml);
        $('#quick-edit-modal').show();

        var currencySymbol = '$'; // Default, can be localized
        if (typeof wc_add_to_cart_params !== 'undefined' && wc_add_to_cart_params.currency_format_symbol) {
            currencySymbol = wc_add_to_cart_params.currency_format_symbol;
        }

        // Use array join instead of string concatenation for better performance
        var htmlParts = [];
        
        htmlParts.push('<div class="quick-edit-header">');
        htmlParts.push('<h2>Quick Edit: ' + escapeHtml(data.name) + '</h2>');
        htmlParts.push('<span class="quick-edit-close">&times;</span>');
        htmlParts.push('</div>');

        htmlParts.push('<div class="quick-edit-body">');
        htmlParts.push('<form id="quick-edit-form">');

        // Default section
        htmlParts.push('<div class="quick-edit-section">');
        htmlParts.push('<h3>Default Settings</h3>');
        htmlParts.push('<div class="mulopimfwc-quick-edit-row">');
        htmlParts.push('<label>Stock Quantity:</label>');
        htmlParts.push('<input type="number" name="default[stock_quantity]" value="' + (data.default.stock_quantity || '') + '" min="0" step="1">');
        htmlParts.push('</div>');
        htmlParts.push('<div class="mulopimfwc-quick-edit-row">');
        htmlParts.push('<label>Regular Price (' + currencySymbol + '):</label>');
        htmlParts.push('<input type="number" name="default[regular_price]" value="' + (data.default.regular_price || '') + '" min="0" step="0.01">');
        htmlParts.push('</div>');
        htmlParts.push('<div class="mulopimfwc-quick-edit-row">');
        htmlParts.push('<label>Sale Price (' + currencySymbol + '):</label>');
        htmlParts.push('<input type="number" name="default[sale_price]" value="' + (data.default.sale_price || '') + '" min="0" step="0.01">');
        htmlParts.push('</div>');
        htmlParts.push('<div class="mulopimfwc-quick-edit-row">');
        htmlParts.push('<label>Backorders:</label>');
        htmlParts.push('<select name="default[backorders]">');
        htmlParts.push('<option value="no"' + (data.default.backorders === 'no' ? ' selected' : '') + '>Do not allow</option>');
        htmlParts.push('<option value="notify"' + (data.default.backorders === 'notify' ? ' selected' : '') + '>Allow, but notify customer</option>');
        htmlParts.push('<option value="yes"' + (data.default.backorders === 'yes' ? ' selected' : '') + '>Allow</option>');
        htmlParts.push('</select>');
        htmlParts.push('</div>');
        htmlParts.push('<div class="mulopimfwc-quick-edit-row">');
        htmlParts.push('<label>Purchase Price (' + currencySymbol + '):</label>');
        htmlParts.push('<input type="number" name="default[purchase_price]" value="' + (data.default.purchase_price || '') + '" min="0" step="0.01">');
        htmlParts.push('</div>');
        htmlParts.push('<div class="mulopimfwc-quick-edit-row">');
        htmlParts.push('<label>Purchase Quantity:</label>');
        htmlParts.push('<input type="number" name="default[purchase_quantity]" value="' + (data.default.purchase_quantity || '') + '" min="0" step="1">');
        htmlParts.push('</div>');
        htmlParts.push('</div>');

        // Location sections
        if (data.locations && data.locations.length > 0) {
            htmlParts.push('<div class="quick-edit-section">');
            htmlParts.push('<h3>Location-Wise Settings</h3>');
            data.locations.forEach(function (location) {
                htmlParts.push('<div class="quick-edit-location-group">');
                htmlParts.push('<h4>' + escapeHtml(location.name) + '</h4>');
                htmlParts.push('<div class="mulopimfwc-quick-edit-row">');
                htmlParts.push('<label>Stock Quantity:</label>');
                htmlParts.push('<input type="number" name="locations[' + location.id + '][stock]" value="' + (location.stock || '') + '" min="0" step="1">');
                htmlParts.push('</div>');
                htmlParts.push('<div class="mulopimfwc-quick-edit-row">');
                htmlParts.push('<label>Regular Price (' + currencySymbol + '):</label>');
                htmlParts.push('<input type="number" name="locations[' + location.id + '][regular_price]" value="' + (location.regular_price || '') + '" min="0" step="0.01">');
                htmlParts.push('</div>');
                htmlParts.push('<div class="mulopimfwc-quick-edit-row">');
                htmlParts.push('<label>Sale Price (' + currencySymbol + '):</label>');
                htmlParts.push('<input type="number" name="locations[' + location.id + '][sale_price]" value="' + (location.sale_price || '') + '" min="0" step="0.01">');
                htmlParts.push('</div>');
                htmlParts.push('<div class="mulopimfwc-quick-edit-row">');
                htmlParts.push('<label>Backorders:</label>');
                htmlParts.push('<select name="locations[' + location.id + '][backorders]">');
                htmlParts.push('<option value="off"' + (location.backorders === 'off' ? ' selected' : '') + '>Do not allow</option>');
                htmlParts.push('<option value="notify"' + (location.backorders === 'notify' ? ' selected' : '') + '>Allow, but notify customer</option>');
                htmlParts.push('<option value="on"' + (location.backorders === 'on' ? ' selected' : '') + '>Allow</option>');
                htmlParts.push('</select>');
                htmlParts.push('</div>');
                htmlParts.push('</div>');
            });
            htmlParts.push('</div>');
        }

        // Variations section for variable products
        var quickEditProductType = data.product_type || data.type || '';
        if (quickEditProductType === 'variable' && data.variations && data.variations.length > 0) {
            htmlParts.push('<div class="quick-edit-section">');
            htmlParts.push('<h3>Variations</h3>');
            data.variations.forEach(function (variation) {
                var variationTitle = Object.values(variation.attributes).join(', ') || 'Variation #' + variation.id;
                htmlParts.push('<div class="quick-edit-variation-group">');
                htmlParts.push('<h4>' + escapeHtml(variationTitle) + '</h4>');

                // Default variation settings
                htmlParts.push('<div class="quick-edit-subsection">');
                htmlParts.push('<h5>Default</h5>');
                htmlParts.push('<div class="mulopimfwc-quick-edit-row">');
                htmlParts.push('<label>Stock Quantity:</label>');
                htmlParts.push('<input type="number" name="variations[' + variation.id + '][default][stock_quantity]" value="' + (variation.default.stock_quantity || '') + '" min="0" step="1">');
                htmlParts.push('</div>');
                htmlParts.push('<div class="mulopimfwc-quick-edit-row">');
                htmlParts.push('<label>Regular Price (' + currencySymbol + '):</label>');
                htmlParts.push('<input type="number" name="variations[' + variation.id + '][default][regular_price]" value="' + (variation.default.regular_price || '') + '" min="0" step="0.01">');
                htmlParts.push('</div>');
                htmlParts.push('<div class="mulopimfwc-quick-edit-row">');
                htmlParts.push('<label>Sale Price (' + currencySymbol + '):</label>');
                htmlParts.push('<input type="number" name="variations[' + variation.id + '][default][sale_price]" value="' + (variation.default.sale_price || '') + '" min="0" step="0.01">');
                htmlParts.push('</div>');
                htmlParts.push('<div class="mulopimfwc-quick-edit-row">');
                htmlParts.push('<label>Backorders:</label>');
                htmlParts.push('<select name="variations[' + variation.id + '][default][backorders]">');
                htmlParts.push('<option value="no"' + (variation.default.backorders === 'no' ? ' selected' : '') + '>Do not allow</option>');
                htmlParts.push('<option value="notify"' + (variation.default.backorders === 'notify' ? ' selected' : '') + '>Allow, but notify customer</option>');
                htmlParts.push('<option value="yes"' + (variation.default.backorders === 'yes' ? ' selected' : '') + '>Allow</option>');
                htmlParts.push('</select>');
                htmlParts.push('</div>');
                htmlParts.push('<div class="mulopimfwc-quick-edit-row">');
                htmlParts.push('<label>Purchase Price (' + currencySymbol + '):</label>');
                htmlParts.push('<input type="number" name="variations[' + variation.id + '][default][purchase_price]" value="' + (variation.default.purchase_price || '') + '" min="0" step="0.01">');
                htmlParts.push('</div>');
                htmlParts.push('</div>');

                // Location settings for variation
                if (variation.locations && variation.locations.length > 0) {
                    htmlParts.push('<div class="quick-edit-subsection">');
                    htmlParts.push('<h5>Location-Wise</h5>');
                    variation.locations.forEach(function (location) {
                        htmlParts.push('<div class="quick-edit-location-group">');
                        htmlParts.push('<h6>' + escapeHtml(location.name) + '</h6>');
                        htmlParts.push('<div class="mulopimfwc-quick-edit-row">');
                        htmlParts.push('<label>Stock Quantity:</label>');
                        htmlParts.push('<input type="number" name="variations[' + variation.id + '][locations][' + location.id + '][stock]" value="' + (location.stock || '') + '" min="0" step="1">');
                        htmlParts.push('</div>');
                        htmlParts.push('<div class="mulopimfwc-quick-edit-row">');
                        htmlParts.push('<label>Regular Price (' + currencySymbol + '):</label>');
                        htmlParts.push('<input type="number" name="variations[' + variation.id + '][locations][' + location.id + '][regular_price]" value="' + (location.regular_price || '') + '" min="0" step="0.01">');
                        htmlParts.push('</div>');
                        htmlParts.push('<div class="mulopimfwc-quick-edit-row">');
                        htmlParts.push('<label>Sale Price (' + currencySymbol + '):</label>');
                        htmlParts.push('<input type="number" name="variations[' + variation.id + '][locations][' + location.id + '][sale_price]" value="' + (location.sale_price || '') + '" min="0" step="0.01">');
                        htmlParts.push('</div>');
                        htmlParts.push('<div class="mulopimfwc-quick-edit-row">');
                        htmlParts.push('<label>Backorders:</label>');
                        htmlParts.push('<select name="variations[' + variation.id + '][locations][' + location.id + '][backorders]">');
                        htmlParts.push('<option value="off"' + (location.backorders === 'off' ? ' selected' : '') + '>Do not allow</option>');
                        htmlParts.push('<option value="notify"' + (location.backorders === 'notify' ? ' selected' : '') + '>Allow, but notify customer</option>');
                        htmlParts.push('<option value="on"' + (location.backorders === 'on' ? ' selected' : '') + '>Allow</option>');
                        htmlParts.push('</select>');
                        htmlParts.push('</div>');
                        htmlParts.push('</div>');
                    });
                    htmlParts.push('</div>');
                }
                htmlParts.push('</div>');
            });
            htmlParts.push('</div>');
        }

        htmlParts.push('</form>');
        htmlParts.push('</div>');

        htmlParts.push('<div class="quick-edit-footer">');
        htmlParts.push('<button type="button" class="button button-secondary quick-edit-cancel">Cancel</button>');
        htmlParts.push('<button type="button" class="button button-primary quick-edit-save" data-product-id="' + data.id + '">Save Changes</button>');
        htmlParts.push('</div>');

        // Join all parts at once for better performance
        var html = htmlParts.join('');
        $('#quick-edit-modal .quick-edit-modal-content').html(html);

        // Handle close button
        $('.quick-edit-close, .quick-edit-cancel').on('click', function () {
            $('#quick-edit-modal').remove();
        });

        // Handle save button
        $('.quick-edit-save').on('click', function () {
            if (validateQuickEditForm()) {
                saveQuickEditData(data.id);
            }
        });

        // Close on outside click
        $('#quick-edit-modal').on('click', function (e) {
            if ($(e.target).is('#quick-edit-modal')) {
                $('#quick-edit-modal').remove();
            }
        });

        // Add real-time validation
        setupQuickEditValidation();
    }

    // Function to setup real-time validation
    function setupQuickEditValidation() {
        // Remove previous error states on input
        $('#quick-edit-form').on('input change', 'input, select', function() {
            $(this).removeClass('quick-edit-error');
            $(this).closest('.mulopimfwc-quick-edit-row').find('.validation-error').remove();
        });

        // Validate on blur
        $('#quick-edit-form').on('blur', 'input[type="number"]', function() {
            validateField($(this));
        });
    }

    // Function to validate a single field
    function validateField($field) {
        var fieldName = $field.attr('name');
        if (!fieldName) return true;

        var value = parseFloat($field.val()) || 0;
        var isValid = true;
        var errorMessage = '';

        // Get default values
        var defaultRegularPrice = parseFloat($('input[name="default[regular_price]"]').val()) || 0;
        var defaultSalePrice = parseFloat($('input[name="default[sale_price]"]').val()) || 0;
        var defaultPurchasePrice = parseFloat($('input[name="default[purchase_price]"]').val()) || 0;
        var defaultStock = parseFloat($('input[name="default[stock_quantity]"]').val()) || 0;
        var defaultPurchaseQty = parseFloat($('input[name="default[purchase_quantity]"]').val()) || 0;

        // Validation rules
        if (fieldName.indexOf('default[regular_price]') !== -1) {
            // Regular price can't be less than purchase price
            if (defaultPurchasePrice > 0 && value < defaultPurchasePrice) {
                isValid = false;
                errorMessage = 'Regular price cannot be less than purchase price (' + defaultPurchasePrice + ')';
            }
            // Sale price can't be greater than or equal to regular price
            if (defaultSalePrice > 0 && defaultSalePrice >= value) {
                var $saleField = $('input[name="default[sale_price]"]');
                $saleField.addClass('quick-edit-error');
                showFieldError($saleField, 'Sale price must be less than regular price');
            }
        }

        if (fieldName.indexOf('default[sale_price]') !== -1) {
            // Sale price can't be greater than or equal to regular price
            if (defaultRegularPrice > 0 && value >= defaultRegularPrice) {
                isValid = false;
                errorMessage = 'Sale price must be less than regular price (' + defaultRegularPrice + ')';
            }
            // Sale price can't be less than purchase price
            if (defaultPurchasePrice > 0 && value > 0 && value < defaultPurchasePrice) {
                isValid = false;
                errorMessage = 'Sale price cannot be less than purchase price (' + defaultPurchasePrice + ')';
            }
        }

        if (fieldName.indexOf('default[stock_quantity]') !== -1) {
            // Default quantity can't be greater than purchase quantity
            if (defaultPurchaseQty > 0 && value > defaultPurchaseQty) {
                isValid = false;
                errorMessage = 'Stock quantity cannot be greater than purchase quantity (' + defaultPurchaseQty + ')';
            }
        }

        if (fieldName.indexOf('default[purchase_quantity]') !== -1) {
            // Purchase quantity can't be less than default stock
            if (defaultStock > 0 && value < defaultStock) {
                isValid = false;
                errorMessage = 'Purchase quantity cannot be less than stock quantity (' + defaultStock + ')';
            }
        }

        // Location-based validations
        if (fieldName.indexOf('locations[') !== -1) {
            var match = fieldName.match(/locations\[(\d+)\]\[(\w+)\]/);
            if (match) {
                var fieldType = match[2];
                
                if (fieldType === 'regular_price' || fieldType === 'sale_price') {
                    var locationPrice = value;
                    // Location price can't be greater than default price
                    if (fieldType === 'regular_price' && defaultRegularPrice > 0 && locationPrice > defaultRegularPrice) {
                        isValid = false;
                        errorMessage = 'Location regular price cannot be greater than default regular price (' + defaultRegularPrice + ')';
                    }
                    if (fieldType === 'sale_price' && defaultSalePrice > 0 && locationPrice > defaultSalePrice) {
                        isValid = false;
                        errorMessage = 'Location sale price cannot be greater than default sale price (' + defaultSalePrice + ')';
                    }
                }
            }
        }

        // Variation validations
        if (fieldName.indexOf('variations[') !== -1) {
            var varMatch = fieldName.match(/variations\[(\d+)\]\[default\]\[(\w+)\]/);
            if (varMatch) {
                var varFieldType = varMatch[2];
                var varDefaultRegularPrice = parseFloat($('input[name="variations[' + varMatch[1] + '][default][regular_price]"]').val()) || 0;
                var varDefaultSalePrice = parseFloat($('input[name="variations[' + varMatch[1] + '][default][sale_price]"]').val()) || 0;
                var varDefaultPurchasePrice = parseFloat($('input[name="variations[' + varMatch[1] + '][default][purchase_price]"]').val()) || 0;

                if (varFieldType === 'regular_price' && varDefaultPurchasePrice > 0 && value < varDefaultPurchasePrice) {
                    isValid = false;
                    errorMessage = 'Regular price cannot be less than purchase price (' + varDefaultPurchasePrice + ')';
                }
                if (varFieldType === 'sale_price' && varDefaultRegularPrice > 0 && value >= varDefaultRegularPrice) {
                    isValid = false;
                    errorMessage = 'Sale price must be less than regular price (' + varDefaultRegularPrice + ')';
                }
            }
        }

        if (!isValid) {
            $field.addClass('quick-edit-error');
            showFieldError($field, errorMessage);
        }

        return isValid;
    }

    // Function to show field error
    function showFieldError($field, message) {
        var $row = $field.closest('.mulopimfwc-quick-edit-row');
        $row.find('.validation-error').remove();
        $row.append('<span class="validation-error">' + escapeHtml(message) + '</span>');
    }

    // Function to validate entire form
    function validateQuickEditForm() {
        var isValid = true;
        var errors = [];

        // Clear previous errors
        $('#quick-edit-form .quick-edit-error').removeClass('quick-edit-error');
        $('#quick-edit-form .validation-error').remove();

        // Get default values
        var defaultRegularPrice = parseFloat($('input[name="default[regular_price]"]').val()) || 0;
        var defaultSalePrice = parseFloat($('input[name="default[sale_price]"]').val()) || 0;
        var defaultPurchasePrice = parseFloat($('input[name="default[purchase_price]"]').val()) || 0;
        var defaultStock = parseFloat($('input[name="default[stock_quantity]"]').val()) || 0;
        var defaultPurchaseQty = parseFloat($('input[name="default[purchase_quantity]"]').val()) || 0;

        // Validation 1: Regular price can't be less than purchase price
        if (defaultPurchasePrice > 0 && defaultRegularPrice > 0 && defaultRegularPrice < defaultPurchasePrice) {
            var $field = $('input[name="default[regular_price]"]');
            $field.addClass('quick-edit-error');
            showFieldError($field, 'Regular price cannot be less than purchase price');
            isValid = false;
        }

        // Validation 2: Sale price can't be greater than or equal to regular price
        if (defaultSalePrice > 0 && defaultRegularPrice > 0 && defaultSalePrice >= defaultRegularPrice) {
            var $field = $('input[name="default[sale_price]"]');
            $field.addClass('quick-edit-error');
            showFieldError($field, 'Sale price must be less than regular price');
            isValid = false;
        }

        // Validation 3: Sale price can't be less than purchase price
        if (defaultSalePrice > 0 && defaultPurchasePrice > 0 && defaultSalePrice < defaultPurchasePrice) {
            var $field = $('input[name="default[sale_price]"]');
            $field.addClass('quick-edit-error');
            showFieldError($field, 'Sale price cannot be less than purchase price');
            isValid = false;
        }

        // Validation 4: Default quantity can't be greater than purchase quantity
        if (defaultPurchaseQty > 0 && defaultStock > defaultPurchaseQty) {
            var $field = $('input[name="default[stock_quantity]"]');
            $field.addClass('quick-edit-error');
            showFieldError($field, 'Stock quantity cannot be greater than purchase quantity');
            isValid = false;
        }

        // Validation 5: Sum of all location stock can't be greater than default quantity
        var totalLocationStock = 0;
        $('input[name^="locations["][name$="[stock]"]').each(function() {
            var stock = parseFloat($(this).val()) || 0;
            if (stock > 0) {
                totalLocationStock += stock;
            }
        });
        if (defaultStock > 0 && totalLocationStock > defaultStock) {
            $('input[name^="locations["][name$="[stock]"]').each(function() {
                if (parseFloat($(this).val()) > 0) {
                    $(this).addClass('quick-edit-error');
                    showFieldError($(this), 'Total location stock exceeds default stock');
                }
            });
            showNotice('Total location stock (' + totalLocationStock + ') cannot be greater than default stock quantity (' + defaultStock + ')', 'error');
            isValid = false;
        }

        // Validate location prices
        $('input[name^="locations["][name$="[regular_price]"]').each(function() {
            var locationPrice = parseFloat($(this).val()) || 0;
            if (locationPrice > 0 && defaultRegularPrice > 0 && locationPrice > defaultRegularPrice) {
                $(this).addClass('quick-edit-error');
                showFieldError($(this), 'Location regular price cannot be greater than default regular price');
                isValid = false;
            }
        });

        $('input[name^="locations["][name$="[sale_price]"]').each(function() {
            var locationPrice = parseFloat($(this).val()) || 0;
            if (locationPrice > 0 && defaultSalePrice > 0 && locationPrice > defaultSalePrice) {
                $(this).addClass('quick-edit-error');
                showFieldError($(this), 'Location sale price cannot be greater than default sale price');
                isValid = false;
            }
        });

        // Validate variations
        $('input[name^="variations["]').each(function() {
            if (!validateField($(this))) {
                isValid = false;
            }
        });

        // Validate variation location prices
        $('input[name^="variations["][name*="[default][regular_price]"]').each(function() {
            var varMatch = $(this).attr('name').match(/variations\[(\d+)\]/);
            if (varMatch) {
                var varId = varMatch[1];
                var varDefaultRegularPrice = parseFloat($(this).val()) || 0;
                var varDefaultSalePrice = parseFloat($('input[name="variations[' + varId + '][default][sale_price]"]').val()) || 0;

                // Check variation location regular prices
                $('#quick-edit-form input[name^="variations[' + varId + '][locations]["][name$="[regular_price]"]').each(function() {
                    var locPrice = parseFloat($(this).val()) || 0;
                    if (locPrice > 0 && varDefaultRegularPrice > 0 && locPrice > varDefaultRegularPrice) {
                        $(this).addClass('quick-edit-error');
                        showFieldError($(this), 'Location regular price cannot be greater than variation default regular price');
                        isValid = false;
                    }
                });

                // Check variation location sale prices
                $('#quick-edit-form input[name^="variations[' + varId + '][locations]["][name$="[sale_price]"]').each(function() {
                    var locPrice = parseFloat($(this).val()) || 0;
                    if (locPrice > 0 && varDefaultSalePrice > 0 && locPrice > varDefaultSalePrice) {
                        $(this).addClass('quick-edit-error');
                        showFieldError($(this), 'Location sale price cannot be greater than variation default sale price');
                        isValid = false;
                    }
                });
            }
        });

        // Validate variation location stocks sum
        $('input[name^="variations["][name*="[default][stock_quantity]"]').each(function() {
            var varMatch = $(this).attr('name').match(/variations\[(\d+)\]/);
            if (varMatch) {
                var varId = varMatch[1];
                var varDefaultStock = parseFloat($(this).val()) || 0;
                var totalVarLocationStock = 0;

                // Find all location stock inputs for this variation (pattern: variations[ID][locations][LOC_ID][stock])
                $('#quick-edit-form input[name^="variations[' + varId + '][locations]["][name$="[stock]"]').each(function() {
                    var stock = parseFloat($(this).val()) || 0;
                    if (stock > 0) {
                        totalVarLocationStock += stock;
                    }
                });

                if (varDefaultStock > 0 && totalVarLocationStock > varDefaultStock) {
                    $('#quick-edit-form input[name^="variations[' + varId + '][locations]["][name$="[stock]"]').each(function() {
                        if (parseFloat($(this).val()) > 0) {
                            $(this).addClass('quick-edit-error');
                            showFieldError($(this), 'Total location stock exceeds variation default stock');
                        }
                    });
                    showNotice('Variation: Total location stock (' + totalVarLocationStock + ') cannot be greater than default stock quantity (' + varDefaultStock + ')', 'error');
                    isValid = false;
                }
            }
        });

        if (!isValid) {
            showNotice('Please fix the validation errors before saving.', 'error');
            // Scroll to first error
            var $firstError = $('#quick-edit-form .quick-edit-error').first();
            if ($firstError.length) {
                $('html, body').animate({
                    scrollTop: $firstError.offset().top - 100
                }, 500);
            }
        }

        return isValid;
    }

    // Function to save quick edit data
    function saveQuickEditData(productId) {
        var data = {
            action: 'save_product_quick_edit_data',
            product_id: productId,
            security: mulopimfwc_locationWiseProducts.nonce
        };

        // Helper function to parse field name and set value
        function setValueByPath(obj, path, value) {
            // Skip empty values
            if (value === '' || value === null || value === undefined) {
                return;
            }
            
            var parts = path.split(/[\[\]]+/).filter(function(p) { return p !== ''; });
            var current = obj;
            
            for (var i = 0; i < parts.length - 1; i++) {
                var part = parts[i];
                // Convert numeric strings to numbers for array indices
                var key = isNaN(part) ? part : parseInt(part, 10);
                if (!current[key]) {
                    current[key] = {};
                }
                current = current[key];
            }
            
            var lastKey = parts[parts.length - 1];
            current[isNaN(lastKey) ? lastKey : parseInt(lastKey, 10)] = value;
        }

        // Collect all form fields manually and build proper structure
        $('#quick-edit-form').find('input, select').each(function() {
            var $field = $(this);
            var name = $field.attr('name');
            var value = $field.val();
            
            if (name && value !== null && value !== undefined) {
                // Handle locations structure: locations[ID][field] -> locations: [{id: ID, field: value}]
                if (name.indexOf('locations[') === 0 && name.indexOf('][') !== -1) {
                    var match = name.match(/locations\[(\d+)\]\[(\w+)\]/);
                    if (match) {
                        var locId = parseInt(match[1], 10);
                        var fieldName = match[2];
                        if (!data.locations) data.locations = [];
                        var locIndex = data.locations.findIndex(function(l) { return l.id === locId; });
                        if (locIndex === -1) {
                            data.locations.push({ id: locId });
                            locIndex = data.locations.length - 1;
                        }
                        data.locations[locIndex][fieldName] = value;
                    }
                }
                // Handle variations structure: variations[ID][default][field] or variations[ID][locations][LOC_ID][field]
                else if (name.indexOf('variations[') === 0) {
                    // Try to match variations[ID][default][field] first
                    var defaultMatch = name.match(/variations\[(\d+)\]\[default\]\[(\w+)\]/);
                    if (defaultMatch) {
                        var varId = parseInt(defaultMatch[1], 10);
                        var fieldName = defaultMatch[2];
                        
                        if (!data.variations) data.variations = [];
                        var varIndex = data.variations.findIndex(function(v) { return v.id === varId; });
                        if (varIndex === -1) {
                            data.variations.push({ id: varId });
                            varIndex = data.variations.length - 1;
                        }
                        if (!data.variations[varIndex].default) {
                            data.variations[varIndex].default = {};
                        }
                        data.variations[varIndex].default[fieldName] = value;
                    } else {
                        // Try to match variations[ID][locations][LOC_ID][field]
                        var locMatch = name.match(/variations\[(\d+)\]\[locations\]\[(\d+)\]\[(\w+)\]/);
                        if (locMatch) {
                            var varId = parseInt(locMatch[1], 10);
                            var locId = parseInt(locMatch[2], 10);
                            var fieldName = locMatch[3];
                            
                            if (!data.variations) data.variations = [];
                            var varIndex = data.variations.findIndex(function(v) { return v.id === varId; });
                            if (varIndex === -1) {
                                data.variations.push({ id: varId });
                                varIndex = data.variations.length - 1;
                            }
                            if (!data.variations[varIndex].locations) {
                                data.variations[varIndex].locations = [];
                            }
                            var locIndex = data.variations[varIndex].locations.findIndex(function(l) { return l.id === locId; });
                            if (locIndex === -1) {
                                data.variations[varIndex].locations.push({ id: locId });
                                locIndex = data.variations[varIndex].locations.length - 1;
                            }
                            data.variations[varIndex].locations[locIndex][fieldName] = value;
                        }
                    }
                }
                // Handle default fields: default[field]
                else if (name.indexOf('default[') === 0) {
                    var defMatch = name.match(/default\[(\w+)\]/);
                    if (defMatch) {
                        if (!data.default) data.default = {};
                        data.default[defMatch[1]] = value;
                    }
                }
            }
        });

        // Show loading
        $('.quick-edit-save').prop('disabled', true).text('Saving...');

        // Send AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: data,
            success: function (response) {
                if (response.success) {
                    $('#quick-edit-modal').remove();
                    showNotice(response.data.message || 'Product data saved successfully', 'success');
                    // Reload page to show updated data
                    setTimeout(function () {
                        location.reload();
                    }, 1000);
                } else {
                    showNotice(response.data.message || 'Error saving product data', 'error');
                    $('.quick-edit-save').prop('disabled', false).text('Save Changes');
                }
            },
            error: function () {
                showNotice('Error saving product data', 'error');
                $('.quick-edit-save').prop('disabled', false).text('Save Changes');
            }
        });
    }

    // Helper function to escape HTML
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return (text || '').toString().replace(/[&<>"']/g, function (m) { return map[m]; });
    }

    const $select = $('.lwp-location-show-title>table select#mulopimfwc_display_title');
    const $locationintitletable = $('.lwp-location-show-title>table:first');
    const $strict_filtering = $('#product-visibility-settings table select#strict_filtering');
    const $strict_table = $('#product-visibility-settings table:eq(3)');
    const $enable_popup = $('select#enable_popup');
    const $popup_settings = $('#popup-shortcode-settings table tr:eq(2),#popup-shortcode-settings table tr:eq(3),#popup-shortcode-settings table tr:eq(4),#popup-shortcode-settings table tr:eq(5)');
    const $herichical = $('#herichical');
    $herichical_settings = $('#popup-shortcode-settings table tr:eq(7)');
    function togglelocationintitlesettings($selectoption, $optionvalue, $selecttable) {
        if ($selectoption.val() == $optionvalue) {
            $selecttable.find('tr:not(:first)').hide();
        } else {
            $selecttable.find('tr:not(:first)').show();
        }
    }

    $select.on('change', function () {
        togglelocationintitlesettings($select, 'none', $locationintitletable);
    });

    $strict_filtering.on('change', function () {
        togglelocationintitlesettings($strict_filtering, 'disabled', $strict_table);
    });

    togglelocationintitlesettings($select, 'none', $locationintitletable);
    togglelocationintitlesettings($strict_filtering, 'disabled', $strict_table);


    const $enableLocationInfo = $('.enable_location_information input');

    // Get the "Enable Location by User Role" table row
    const $userRoleRow = $('.enable_location_information').closest('tr').next();

    // Function to check and toggle visibility
    function toggleUserRoleRow() {
        if ($enableLocationInfo.is(':checked')) {
            $userRoleRow.show();
        } else {
            $userRoleRow.hide();
        }
    }

    // Run once on page load
    toggleUserRoleRow();

    // Listen for changes to the dropdown
    $enableLocationInfo.on('change', toggleUserRoleRow);

    function togglepopupsetting() {
        if ($enable_popup.val() === 'off') {
            $popup_settings.hide();
        } else {
            $popup_settings.show();
        }
    }
    togglepopupsetting();
    $enable_popup.on('change', togglepopupsetting);

    function toggleherichicalsettings() {
        if ($herichical.val() === 'off') {
            $herichical_settings.hide();
        } else {
            $herichical_settings.show();
        }
    }
    toggleherichicalsettings();
    $herichical.on('change', toggleherichicalsettings);
});


jQuery(document).ready(function ($) {
    // Tab functionality
    $('.lwp-nav-tabs a').click(function (e) {
        e.preventDefault();

        // Update active tab
        $('.lwp-nav-tabs a').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        // Show target content
        $('.lwp-tab-content,.mulopimfwc_settings').hide();
        $($(this).attr('href')).show();
        $($(this).attr('href')).closest('.mulopimfwc_settings').show();
        // Update URL hash (without page reload)
        const tabHash = $(this).attr('href');
        history.replaceState(null, null, tabHash);
    });

    // On page load, check URL hash and show correct tab
    const currentHash = window.location.hash;
    if (currentHash && $('.lwp-nav-tabs a[href="' + currentHash + '"]').length) {
        $('.lwp-nav-tabs a[href="' + currentHash + '"]').trigger('click');
    } else {
        // Default: show first tab if none specified
        $('.lwp-nav-tabs a:first').trigger('click');
    }

    // Add toggle functionality for sections if needed
    $('.lwp-settings-box h2').addClass('lwp-section-toggle');
    $('.lwp-section-toggle').click(function () {
        $(this).next('.form-table').slideToggle();
        $(this).toggleClass('closed');
    });


    const $displayFormat = $('#mulopimfwc_display_title');
    const $separatorRow = $('input[name="mulopimfwc_display_options[separator]"]').closest('tr');

    function toggleSeparatorRow() {
        if ($displayFormat.val() === 'brackets' || $displayFormat.val() === 'none') {
            $separatorRow.hide();
        } else {
            $separatorRow.show();
        }
    }

    // Initial check on page load
    toggleSeparatorRow();

    // Listen for changes
    $displayFormat.on('change', toggleSeparatorRow);

    // handle Display Location on Single Product toggle

    const $enableLocationSingleProduct = $('input[name="mulopimfwc_display_options[display_location_single_product]"]');
    const $relatedSettings = $enableLocationSingleProduct.closest('table').find('input, select').not($enableLocationSingleProduct);

    // Set initial state
    mulopimfwc_toggleDisabledClass(!$enableLocationSingleProduct.is(':checked'), $relatedSettings);

    // Handle change event
    $enableLocationSingleProduct.on('change', function () {
        mulopimfwc_toggleDisabledClass(!$(this).is(':checked'), $relatedSettings);
    });

    const $enable_popup = $('input[name="mulopimfwc_display_options[enable_popup]"]');
    const $popup_related_settings = $enable_popup.closest('table').find('input, select, textarea').not($enable_popup);

    // Set initial state
    mulopimfwc_toggleDisabledClass(!$enable_popup.is(':checked'), $popup_related_settings);

    // Handle change event
    $enable_popup.on('change', function () {
        mulopimfwc_toggleDisabledClass(!$(this).is(':checked'), $popup_related_settings);
    });

    const $enable_all_locations = $('input[name="mulopimfwc_display_options[enable_all_locations]"]');
    const $all_locations_related_settings = $enable_all_locations.closest('table').find('input, select, textarea').not($enable_all_locations);

    // Set initial state
    mulopimfwc_toggleDisabledClass(!$enable_all_locations.is(':checked'), $all_locations_related_settings);

    // Handle change event
    $enable_all_locations.on('change', function () {
        mulopimfwc_toggleDisabledClass(!$(this).is(':checked'), $all_locations_related_settings);
    });


    const $enable_location_shipping = $('select[name="mulopimfwc_display_options[enable_location_shipping]"]');
    const $location_shipping_related_settings = $enable_location_shipping.closest('#lwp-subtab-shipping').find('input, select, textarea').not($enable_location_shipping);
    // Set initial state
    mulopimfwc_toggleDisabledClass($enable_location_shipping.val() !== 'on', $location_shipping_related_settings);
    // Handle change event
    $enable_location_shipping.on('change', function () {
        mulopimfwc_toggleDisabledClass($(this).val() !== 'on', $location_shipping_related_settings);
    });


    const $enable_location_discounts = $('select[name="mulopimfwc_display_options[enable_location_discounts]"]');
    const $location_discounts_related_settings = $enable_location_discounts.closest('#lwp-subtab-discounts').find('input, select, textarea').not($enable_location_discounts);
    // Set initial state
    mulopimfwc_toggleDisabledClass($enable_location_discounts.val() !== 'on', $location_discounts_related_settings);
    // Handle change event
    $enable_location_discounts.on('change', function () {
        mulopimfwc_toggleDisabledClass($(this).val() !== 'on', $location_discounts_related_settings);
    });

    const $enable_store_locator = $('select[name="mulopimfwc_display_options[enable_store_locator]"]');
    const $store_locator_related_settings = $enable_store_locator.closest('table').find('input, select, textarea').not($enable_store_locator);
    // Set initial state
    mulopimfwc_toggleDisabledClass($enable_store_locator.val() !== 'on', $store_locator_related_settings);
    // Handle change event
    $enable_store_locator.on('change', function () {
        mulopimfwc_toggleDisabledClass($(this).val() !== 'on', $store_locator_related_settings);
    });

    const $enable_business_hours = $('select[name="mulopimfwc_display_options[enable_business_hours]"]');
    const $business_hours_related_settings = $enable_business_hours.closest('table').find('input, select, textarea').not($enable_business_hours);
    // Set initial state
    mulopimfwc_toggleDisabledClass($enable_business_hours.val() !== 'on', $business_hours_related_settings);
    // Handle change event
    $enable_business_hours.on('change', function () {
        mulopimfwc_toggleDisabledClass($(this).val() !== 'on', $business_hours_related_settings);
    });

    const $enable_location_urls = $('select[name="mulopimfwc_display_options[enable_location_urls]"]');
    const $location_urls_related_settings = $enable_location_urls.closest('table').find('input, select, textarea').not($enable_location_urls);
    // Set initial state
    mulopimfwc_toggleDisabledClass($enable_location_urls.val() !== 'on', $location_urls_related_settings);
    // Handle change event
    $enable_location_urls.on('change', function () {
        mulopimfwc_toggleDisabledClass($(this).val() !== 'on', $location_urls_related_settings);
    });



});


document.addEventListener('DOMContentLoaded', function () {
    function updateLocationRows() {
        const checkedLocations = Array.from(document.querySelectorAll('#mulopimfwc_store_locationchecklist input[type="checkbox"]:checked'))
            .map(checkbox => checkbox.value);

        // Hide all location rows (both simple product and variation tables)
        const allRows = document.querySelectorAll('tr[id^="location-"]');
        allRows.forEach(row => {
            row.style.display = 'none';
        });

        if (checkedLocations.length === 0) {
            const plugincyMessage = document.getElementById('plugincy_message');
            if (plugincyMessage) {
                plugincyMessage.style.display = 'block';
            }
        } else {
            const plugincyMessage = document.getElementById('plugincy_message');
            if (plugincyMessage) {
                plugincyMessage.style.display = 'none';
            }
            
            // Show rows for checked locations (works for both simple product and variation tables)
            checkedLocations.forEach(locationId => {
                // Use querySelectorAll to get all rows with this location ID (including variations)
                const rows = document.querySelectorAll(`tr[id="location-${locationId}"]`);
                rows.forEach(row => {
                    row.style.display = '';
                });
            });
        }
    }

    function highlightChecklist() {
        const checklist = document.getElementById('mulopimfwc_store_locationdiv');
        checklist.classList.toggle('highlight');
        checklist.scrollIntoView({ behavior: 'smooth', block: 'center' });

        // Remove highlight from others if any checkbox is checked
        const checkboxes = document.querySelectorAll('#mulopimfwc_store_locationchecklist input[type="checkbox"]');
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                checklist.classList.remove('highlight');
            });
        });
    }

    updateLocationRows();

    const checkboxes = document.querySelectorAll('#mulopimfwc_store_locationchecklist input[type="checkbox"]');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateLocationRows);
    });

    const highlightButton = document.getElementById('highlightButton');
    if (highlightButton) {
        highlightButton.addEventListener('click', highlightChecklist);
    }
    
    // Handle WooCommerce variation loading events
    if (typeof jQuery !== 'undefined') {
        // When variations are loaded/added
        jQuery(document).on('woocommerce_variations_loaded', function() {
            updateLocationRows();
        });
        
        // When a variation is added
        jQuery(document).on('woocommerce_variations_added', function() {
            updateLocationRows();
        });
        
        // When variation panel is shown
        jQuery(document).on('woocommerce_variations_loaded', function() {
            updateLocationRows();
        });
    }
});

document.addEventListener('DOMContentLoaded', function () {
    const tabs = document.querySelectorAll('.lwp-subtab');
    const contents = document.querySelectorAll('.lwp-subtab-content');
    tabs.forEach(tab => {
        tab.addEventListener('click', function (e) {
            e.preventDefault();
            tabs.forEach(t => t.classList.remove('lwp-subtab-active'));
            tab.classList.add('lwp-subtab-active');
            contents.forEach(c => c.style.display = 'none');
            const target = document.querySelector(tab.getAttribute('href'));
            if (target) target.style.display = 'block';
        });
    });
});


/**
 * Product Edit Page Validation System
 * Beautiful alert notifications for WooCommerce product management
 */

(function ($) {
    'use strict';

    // Notification System
    const NotificationSystem = {
        container: null,

        init() {
            if (!this.container) {
                this.createContainer();
            }
        },

        createContainer() {
            const container = document.createElement('div');
            container.id = 'product-notification-container';
            container.style.cssText = `
                position: fixed;
                top: 32px;
                right: 20px;
                z-index: 999999;
                max-width: 400px;
                pointer-events: none;
            `;
            document.body.appendChild(container);
            this.container = container;
        },

        show(message, type = 'error', duration = 5000) {
            this.init();

            const notification = document.createElement('div');
            notification.className = `product-notification product-notification-${type}`;

            const icons = {
                error: `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="15" y1="9" x2="9" y2="15"></line>
                    <line x1="9" y1="9" x2="15" y2="15"></line>
                </svg>`,
                warning: `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>`,
                info: `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>`
            };

            notification.innerHTML = `
                <div class="notification-icon">${icons[type]}</div>
                <div class="notification-content">
                    <div class="notification-message">${message}</div>
                </div>
                <button class="notification-close" aria-label="Close">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            `;

            // Styles
            this.injectStyles();

            this.container.appendChild(notification);

            // Enable pointer events for this notification
            notification.style.pointerEvents = 'auto';

            // Animate in
            setTimeout(() => notification.classList.add('show'), 10);

            // Close button
            const closeBtn = notification.querySelector('.notification-close');
            closeBtn.addEventListener('click', () => this.hide(notification));

            // Auto dismiss
            if (duration > 0) {
                setTimeout(() => this.hide(notification), duration);
            }

            return notification;
        },

        hide(notification) {
            notification.classList.remove('show');
            notification.classList.add('hide');
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        },

        injectStyles() {
            if (document.getElementById('product-notification-styles')) return;

            const styles = document.createElement('style');
            styles.id = 'product-notification-styles';
            styles.textContent = `
                .product-notification {
                    display: flex;
                    align-items: flex-start;
                    gap: 12px;
                    padding: 16px;
                    margin-bottom: 12px;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                    background: white;
                    border-left: 4px solid;
                    opacity: 0;
                    transform: translateX(400px);
                    transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
                }

                .product-notification.show {
                    opacity: 1;
                    transform: translateX(0);
                }

                .product-notification.hide {
                    opacity: 0;
                    transform: translateX(400px);
                }

                .product-notification-error {
                    border-left-color: #dc3545;
                }

                .product-notification-error .notification-icon {
                    color: #dc3545;
                }

                .product-notification-warning {
                    border-left-color: #ffc107;
                }

                .product-notification-warning .notification-icon {
                    color: #ffc107;
                }

                .product-notification-info {
                    border-left-color: #0dcaf0;
                }

                .product-notification-info .notification-icon {
                    color: #0dcaf0;
                }

                .notification-icon {
                    flex-shrink: 0;
                    margin-top: 2px;
                }

                .notification-content {
                    flex: 1;
                    min-width: 0;
                }

                .notification-message {
                    color: #333;
                    font-size: 14px;
                    line-height: 1.5;
                    font-weight: 500;
                }

                .notification-close {
                    flex-shrink: 0;
                    background: none;
                    border: none;
                    color: #999;
                    cursor: pointer;
                    padding: 0;
                    margin-top: 2px;
                    transition: color 0.2s;
                }

                .notification-close:hover {
                    color: #333;
                }

                .product-field-error {
                    border-color: #dc3545 !important;
                    box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
                }

                .product-field-warning {
                    border-color: #ffc107 !important;
                    box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25) !important;
                }
            `;
            document.head.appendChild(styles);
        }
    };

    // Validation Rules
    const ProductValidator = {
        initialized: false,

        init() {
            if (this.initialized) {
                return;
            }
            this.initialized = true;
            this.bindEvents();
            NotificationSystem.init();
        },

        bindEvents() {
            const self = this;

            // Real-time validation on price fields
            $('#_regular_price, #_sale_price').on('blur', function () {
                self.validatePrices();
            });

            // Validate on stock field change
            $(document).on('change', '#_stock', function () {
                self.validateStock();
                self.validateLocationStock();
            });

            // Validate on purchase price change
            $(document).on('change', '#_purchase_price', function () {
                self.validatePrices();
                self.validateLocationPrices();
            });

            // Validate on purchase quantity change
            $(document).on('change', '#_purchase_quantity', function () {
                self.validateStock();
                self.validateLocationStock();
            });

            // Check manage stock when checkbox changes
            $(document).on('change', '#_manage_stock', function () {
                self.checkManageStock();
            });

            // Validate location prices
            $(document).on('blur', 'input[name^="location_regular_price"], input[name^="location_sale_price"]', function () {
                self.validateLocationPrices();
            });

            // Validate location stock
            $(document).on('change', 'input[name^="location_stock"]', function () {
                self.validateLocationStock();
            });

            // Check manage stock when location stock is entered
            $(document).on('input', 'input[name^="location_stock"]', function () {
                const value = parseFloat($(this).val());
                if (value > 0) {
                    self.checkManageStock();
                }
            });
        },

        validateAll() {
            let isValid = true;

            if (!this.validatePrices()) isValid = false;
            if (!this.validateStock()) isValid = false;
            if (!this.validateLocationPrices()) isValid = false;
            if (!this.validateLocationStock()) isValid = false;
            if (!this.checkManageStock()) isValid = false;

            return isValid;
        },

        validatePrices() {
            const purchasePrice = parseFloat($('#_purchase_price').val()) || 0;
            const regularPrice = parseFloat($('#_regular_price').val()) || 0;
            const salePrice = parseFloat($('#_sale_price').val()) || 0;

            let isValid = true;

            // Remove previous error states
            $('#_regular_price, #_sale_price').removeClass('product-field-error');

            if (purchasePrice > 0) {
                if (regularPrice > 0 && regularPrice < purchasePrice) {
                    $('#_regular_price').addClass('product-field-error');
                    NotificationSystem.show(
                        `Regular price (${regularPrice}) cannot be less than purchase price (${purchasePrice})`,
                        'error'
                    );
                    isValid = false;
                }

                if (salePrice > 0 && salePrice < purchasePrice) {
                    $('#_sale_price').addClass('product-field-error');
                    NotificationSystem.show(
                        `Sale price (${salePrice}) cannot be less than purchase price (${purchasePrice})`,
                        'error'
                    );
                    isValid = false;
                }
            }

            return isValid;
        },

        validateStock() {
            const purchaseQuantity = parseFloat($('#_purchase_quantity').val()) || 0;
            const stock = parseFloat($('#_stock').val()) || 0;

            let isValid = true;

            // Remove previous error states
            $('#_stock').removeClass('product-field-error');

            if (purchaseQuantity > 0 && stock > purchaseQuantity) {
                $('#_stock').addClass('product-field-error');
                NotificationSystem.show(
                    `Stock quantity (${stock}) cannot be greater than purchase quantity (${purchaseQuantity})`,
                    'error'
                );
                isValid = false;
            }

            return isValid;
        },

        validateLocationPrices() {
            const purchasePrice = parseFloat($('#_purchase_price').val()) || 0;
            let isValid = true;

            if (purchasePrice <= 0) return true;

            $('input[name^="location_regular_price"]').each(function () {
                const $field = $(this);
                const locationPrice = parseFloat($field.val()) || 0;
                const nameAttr = $field.attr('name');
                const match = nameAttr ? nameAttr.match(/\[(\d+)\]/) : null;

                if (!match) return true;

                const locationId = match[1];
                const $row = $(`#location-${locationId}`);

                // Check if row is not hidden by style attribute
                const rowStyle = $row.attr('style') || '';
                const isRowVisible = $row.length > 0 && !rowStyle.includes('display: none');

                if (isRowVisible && locationPrice > 0 && locationPrice < purchasePrice) {
                    $field.addClass('product-field-error');
                    const locationName = $row.find('td:first').text();
                    NotificationSystem.show(
                        `${locationName}: Regular price (${locationPrice}) cannot be less than purchase price (${purchasePrice})`,
                        'error'
                    );
                    isValid = false;
                } else {
                    $field.removeClass('product-field-error');
                }
            });

            $('input[name^="location_sale_price"]').each(function () {
                const $field = $(this);
                const locationPrice = parseFloat($field.val()) || 0;
                const nameAttr = $field.attr('name');
                const match = nameAttr ? nameAttr.match(/\[(\d+)\]/) : null;

                if (!match) return true;

                const locationId = match[1];
                const $row = $(`#location-${locationId}`);

                // Check if row is not hidden by style attribute
                const rowStyle = $row.attr('style') || '';
                const isRowVisible = $row.length > 0 && !rowStyle.includes('display: none');

                if (isRowVisible && locationPrice > 0 && locationPrice < purchasePrice) {
                    $field.addClass('product-field-error');
                    const locationName = $row.find('td:first').text();
                    NotificationSystem.show(
                        `${locationName}: Sale price (${locationPrice}) cannot be less than purchase price (${purchasePrice})`,
                        'error'
                    );
                    isValid = false;
                } else {
                    $field.removeClass('product-field-error');
                }
            });

            return isValid;
        },

        validateLocationStock() {
            const purchaseQuantity = parseFloat($('#_purchase_quantity').val()) || 0;
            const totalStock = parseFloat($('#_stock').val()) || 0;
            let isValid = true;
            let totalLocationStock = 0;
            const locationStocks = [];
            const isStockManageable = $('#product-type').val() === 'variable' || $('#product-type').val() === 'simple';
            if (!isStockManageable) return true;

            // Remove previous error states
            $('input[name^="location_stock"]').removeClass('product-field-error');
            $('.location-stock-quantity input').removeClass('product-field-error');

            // Calculate total location stock
            $('input[name^="location_stock"]').each(function () {
                const $field = $(this);
                const nameAttr = $field.attr('name');

                if (!nameAttr || !nameAttr.includes('location_stock')) {
                    return true;
                }

                const match = nameAttr ? nameAttr.match(/\[(\d+)\]/) : null;

                if (!match) return true;

                const locationId = match[1];
                const $row = $(`#location-${locationId}`);

                // Check if row exists and is not explicitly hidden
                const rowStyle = $row.attr('style') || '';
                const isRowVisible = $row.length > 0 && !rowStyle.includes('display: none');

                if (isRowVisible) {
                    const locationStock = parseFloat($field.val()) || 0;

                    if (locationStock > 0) {
                        totalLocationStock += locationStock;
                        const locationName = $row.find('td:first').text().trim();
                        locationStocks.push({
                            field: $field,
                            name: locationName,
                            quantity: locationStock
                        });
                    }
                }
            });

            // Check if total location stock exceeds purchase quantity
            if (purchaseQuantity > 0 && totalLocationStock > purchaseQuantity) {
                locationStocks.forEach(loc => {
                    loc.field.addClass('product-field-error');
                });

                NotificationSystem.show(
                    `Total location stock (${totalLocationStock}) cannot be greater than purchase quantity (${purchaseQuantity})`,
                    'error'
                );
                isValid = false;
            }

            // Check if total location stock exceeds total stock
            if (totalStock > 0 && totalLocationStock > totalStock) {
                locationStocks.forEach(loc => {
                    loc.field.addClass('product-field-error');
                });

                NotificationSystem.show(
                    `Total location stock (${totalLocationStock}) cannot be greater than total inventory stock (${totalStock})`,
                    'error'
                );
                isValid = false;
            }

            return isValid;
        },

        checkManageStock() {
            const isManageStockEnabled = $('#_manage_stock').is(':checked');
            const isStockManageable = $('#product-type').val() === 'variable' || $('#product-type').val() === 'simple';
            let hasLocationStock = false;

            // Check if any visible location has stock
            $('input[name^="location_stock"]').each(function () {
                const $field = $(this);
                const nameAttr = $field.attr('name');
                const match = nameAttr ? nameAttr.match(/\[(\d+)\]/) : null;

                if (!match) return true;

                const locationId = match[1];
                const $row = $(`#location-${locationId}`);
                const stock = parseFloat($field.val()) || 0;

                // Check if row is not hidden by style attribute
                const rowStyle = $row.attr('style') || '';
                const isRowVisible = $row.length > 0 && !rowStyle.includes('display: none');

                if (isRowVisible && stock > 0) {
                    hasLocationStock = true;
                    return false;
                }
            });

            if (hasLocationStock && !isManageStockEnabled && isStockManageable) {
                NotificationSystem.show(
                    'Location-wise stock is set, but "Manage stock" is not enabled. Please enable stock management to track inventory properly.',
                    'warning',
                    7000
                );

                // Highlight the manage stock checkbox
                $('#_manage_stock').closest('p.form-field').addClass('product-field-warning');
                setTimeout(() => {
                    $('#_manage_stock').closest('p.form-field').removeClass('product-field-warning');
                }, 3000);

                return false;
            }

            return true;
        }
    };

    // Initialize when document is ready
    $(document).ready(() => {
        ProductValidator.init();
    });

})(jQuery);

// Quick Location Assignment for Orders
jQuery(document).ready(function ($) {
    // Prevent navigation when clicking on the store location column (quick assignment area)
    $(document).on('click', 'td.column-mulopimfwc_store_location, td.mulopimfwc_store_location', function(e) {
        var $target = $(e.target);
        
        // Allow clicks on actual links to work normally
        if ($target.is('a') || $target.closest('a').length > 0) {
            return true;
        }
        
        // Prevent navigation for all other clicks within this column
        // This includes clicks on the wrapper, badge, select dropdown, spinner, or empty space
        e.preventDefault();
        e.stopPropagation();
        return false;
    });
    
    // Prevent clicks on the wrapper and its children from bubbling up (but allow select to function)
    $(document).on('click', '.mulopimfwc-quick-assignment-wrapper, .mulopimfwc-unassigned-badge, .mulopimfwc-assignment-spinner', function(e) {
        e.preventDefault();
        e.stopPropagation();
        return false;
    });
    
    // For the select dropdown, stop propagation to prevent row navigation but allow dropdown to work
    $(document).on('click', '.mulopimfwc-quick-assignment-select', function(e) {
        e.stopPropagation(); // Prevent bubbling to row click handler, but allow select to function
    });

    // Handle quick assignment dropdown change
    $(document).on('change', '.mulopimfwc-quick-assignment-select', function () {
        var $select = $(this);
        var $wrapper = $select.closest('.mulopimfwc-quick-assignment-wrapper');
        var $spinner = $wrapper.find('.mulopimfwc-assignment-spinner');
        var orderId = $select.data('order-id');
        var nonce = $select.data('nonce');
        var locationSlug = $select.val();
        var priceChanged = false;

        if (!locationSlug) {
            return; // Don't do anything if empty selection
        }

        // Show spinner
        $spinner.show();
        $select.prop('disabled', true);

        // Make AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mulopimfwc_quick_assign_location',
                order_id: orderId,
                location_slug: locationSlug,
                nonce: nonce
            },
            success: function (response) {
                if (response.success) {
                    // Check if price changed - if so, reload page to show updated totals
                    priceChanged = response.data && response.data.price_changed;
                    
                    if (priceChanged) {
                        // Price changed - reload page to show updated totals
                        $spinner.text('⏳ ' + (response.data.message || 'Price updated! Reloading...'));
                        
                        // Show success notice before reload
                        if (typeof showNotice === 'function') {
                            showNotice(response.data.message || 'Location assigned and prices updated! Reloading...', 'success');
                        }
                        
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        // Only location changed - replace the dropdown with the location label
                        $wrapper.html('<span class="mulopimfwc-location-assigned">' + 
                            response.data.location_label + '</span>');
                        
                        // Remove unassigned row styling if present
                        $wrapper.closest('tr').removeClass('unassigned-order-row');
                        
                        // Show success notice
                        if (typeof showNotice === 'function') {
                            showNotice(response.data.message || 'Location assigned successfully', 'success');
                        } else {
                            // Fallback notice
                            $('<div class="notice notice-success is-dismissible"><p>' + 
                                (response.data.message || 'Location assigned successfully') + '</p></div>')
                                .insertAfter('.wp-header-end, .wrap h1')
                                .delay(3000)
                                .fadeOut();
                        }
                        
                        // Trigger WooCommerce order update if needed
                        if (typeof woocommerce_admin !== 'undefined') {
                            $(document.body).trigger('wc_order_update');
                        }
                    }
                } else {
                    // Show error
                    var errorMsg = response.data && response.data.message 
                        ? response.data.message 
                        : 'An error occurred';
                    
                    if (typeof showNotice === 'function') {
                        showNotice(errorMsg, 'error');
                    } else {
                        $('<div class="notice notice-error is-dismissible"><p>' + 
                            errorMsg + '</p></div>')
                            .insertAfter('.wp-header-end, .wrap h1')
                            .delay(5000)
                            .fadeOut();
                    }
                    
                    // Re-enable select
                    $select.prop('disabled', false);
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', error);
                
                var errorMsg = 'Failed to assign location. Please try again.';
                if (typeof showNotice === 'function') {
                    showNotice(errorMsg, 'error');
                } else {
                    $('<div class="notice notice-error is-dismissible"><p>' + 
                        errorMsg + '</p></div>')
                        .insertAfter('.wp-header-end, .wrap h1')
                        .delay(5000)
                        .fadeOut();
                }
                
                // Re-enable select
                $select.prop('disabled', false);
            },
            complete: function () {
                // Only hide spinner if we're not reloading
                if (!priceChanged) {
                    $spinner.hide();
                }
            }
        });
    });

    // Add unassigned row class for styling (better browser compatibility)
    $('.mulopimfwc-unassigned-badge, .mulopimfwc-quick-assignment-wrapper').each(function() {
        $(this).closest('tr').addClass('unassigned-order-row');
    });
    
    // Also handle dynamically added rows (if using AJAX table updates)
    if (typeof MutationObserver !== 'undefined') {
        var observer = new MutationObserver(function(mutations) {
            $('.mulopimfwc-unassigned-badge, .mulopimfwc-quick-assignment-wrapper').each(function() {
                $(this).closest('tr').addClass('unassigned-order-row');
            });
        });
        
        var tableContainer = document.querySelector('.woocommerce_page_wc-orders .wp-list-table, .wp-list-table');
        if (tableContainer) {
            observer.observe(tableContainer, { childList: true, subtree: true });
        }
    }
    
    // Auto-submit location filter dropdown on change (optional enhancement)
    $('#store_location_filter').on('change', function() {
        var $form = $(this).closest('form');
        if ($form.length) {
            $form.submit();
        } else {
            // If not in a form, navigate with the filter parameter
            var filterValue = $(this).val();
            var url = new URL(window.location.href);
            if (filterValue) {
                url.searchParams.set('store_location_filter', filterValue);
            } else {
                url.searchParams.delete('store_location_filter');
            }
            window.location.href = url.toString();
        }
    });
});






