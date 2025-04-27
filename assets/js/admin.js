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
                security: locationWiseProducts.nonce
            },
            success: function (response) {
                if (response.success) {
                    // Update button text and classes
                    if (action === 'activate') {
                        $button.text(locationWiseProducts.i18n.deactivate)
                            .removeClass('button-primary activate-location')
                            .addClass('button-secondary deactivate-location')
                            .data('action', 'deactivate');
                    } else {
                        $button.text(locationWiseProducts.i18n.activate)
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
                showNotice(locationWiseProducts.i18n.ajaxError, 'error');
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
                security: locationWiseProducts.nonce
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
                showNotice(locationWiseProducts.i18n.ajaxError, 'error');
            }
        });
    }

    // Function to show location selection modal
    function showLocationModal(productId, locations) {
        // Create modal HTML
        var modalHtml = '<div id="location-selector-modal" class="location-modal">' +
            '<div class="location-modal-content">' +
            '<span class="location-modal-close">&times;</span>' +
            '<h3>' + locationWiseProducts.i18n.selectLocations + '</h3>' +
            '<div class="location-checkboxes">';

        // Add location checkboxes
        $.each(locations, function (index, location) {
            modalHtml += '<label><input type="checkbox" name="product_locations[]" value="' + location.id + '" ' + (location.selected ? 'checked' : '') + '> ' + location.name + '</label><br>';
        });

        // Add submit button
        modalHtml += '</div>' +
            '<button class="button button-primary save-product-locations" data-product-id="' + productId + '">' +
            locationWiseProducts.i18n.saveLocations + '</button>' +
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

    // Function to save product locations
    function saveProductLocations(productId, locationIds) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'save_product_locations',
                product_id: productId,
                location_ids: locationIds,
                security: locationWiseProducts.nonce
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
                showNotice(locationWiseProducts.i18n.ajaxError, 'error');
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

    const $select = $('.lwp-location-show-title>table select#lwp_display_title');
    const $locationintitletable = $('.lwp-location-show-title>table:first');
    const $strict_filtering = $('.lwp-location-show-title>table select#strict_filtering');
    const $strict_table = $('.lwp-location-show-title>table:eq(1)'); // Changed :second to :eq(1)

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


    const $enableLocationInfo = $('#enable_location_information');

    // Get the "Enable Location by User Role" table row
    const $userRoleRow = $('#enable_location_information').closest('tr').next();

    // Function to check and toggle visibility
    function toggleUserRoleRow() {
        if ($enableLocationInfo.val() === 'no') {
            $userRoleRow.hide();
        } else {
            $userRoleRow.show();
        }
    }

    // Run once on page load
    toggleUserRoleRow();

    // Listen for changes to the dropdown
    $enableLocationInfo.on('change', toggleUserRoleRow);

});


jQuery(document).ready(function($) {
    // Tab functionality
    $('.lwp-nav-tabs a').click(function(e) {
        e.preventDefault();

        // Update active tab
        $('.lwp-nav-tabs a').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        // Show target content
        $('.lwp-tab-content').hide();
        $($(this).attr('href')).show();
    });

    // Add toggle functionality for sections if needed
    $('.lwp-settings-box h2').addClass('lwp-section-toggle');
    $('.lwp-section-toggle').click(function() {
        $(this).next('.form-table').slideToggle();
        $(this).toggleClass('closed');
    });
});