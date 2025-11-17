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
    // Handle "Add to Location" button click
    $(document).on('click', '.add-location', function (e) {
        e.preventDefault();

        var productId = $(this).data('product-id');

        // Open a modal or dropdown to select locations
        openLocationSelector(productId);
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
                    } else {
                        $button.text(mulopimfwc_locationWiseProducts.i18n.activate)
                            .removeClass('button-secondary deactivate-location')
                            .addClass('button-primary activate-location')
                            .data('action', 'activate');
                    }

                    // Show success message
                    showNotice(response.data.message, 'success');
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

    // Function to open location selector modal/dropdown
    function openLocationSelector(productId) {
        // Get available locations via AJAX
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
                    // Create and show modal with locations
                    showLocationModal(productId, response.data.locations);
                } else {
                    showNotice(response.data.message, 'error');
                }
            },
            error: function () {
                showNotice(mulopimfwc_locationWiseProducts.i18n.ajaxError, 'error');
            }
        });
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
        modalHtml += renderLocationTree(locationTree, locations, 0);

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

        // Create a map of all locations
        locations.forEach(function (loc) {
            locationsMap[loc.id] = loc;
            if (!tree[loc.parent]) {
                tree[loc.parent] = [];
            }
            tree[loc.parent].push(loc);
        });

        return tree;
    }

    // Render location tree recursively
    function renderLocationTree(tree, allLocations, parentId, level) {
        level = level || 0;
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
            html += '<input type="checkbox" class="location-checkbox" name="product_locations[]" value="' + location.id + '" ' +
                (location.selected ? 'checked' : '') + '> ';
            html += location.name;
            html += '</label>';

            // Render children if any
            if (hasChildren) {
                html += '<div class="location-children">';
                html += renderLocationTree(tree, allLocations, location.id, level + 1);
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


    const $enable_location_reviews = $('select[name="mulopimfwc_display_options[location_specific_reviews]"]');
    const $location_reviews_related_settings = $enable_location_reviews.closest('#lwp-subtab-reviews').find('input, select, textarea').not($enable_location_reviews);
    // Set initial state
    mulopimfwc_toggleDisabledClass($enable_location_reviews.val() !== 'on', $location_reviews_related_settings);
    // Handle change event
    $enable_location_reviews.on('change', function () {
        mulopimfwc_toggleDisabledClass($(this).val() !== 'on', $location_reviews_related_settings);
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
            checkedLocations.forEach(locationId => {
                const row = document.getElementById(`location-${locationId}`);
                if (row) {
                    row.style.display = '';
                }
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

            if (hasLocationStock && !isManageStockEnabled) {
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






