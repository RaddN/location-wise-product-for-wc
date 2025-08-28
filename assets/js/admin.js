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
        // Create modal HTML
        var modalHtml = '<div id="location-selector-modal" class="location-modal">' +
            '<div class="location-modal-content">' +
            '<span class="location-modal-close">&times;</span>' +
            '<h3>' + mulopimfwc_locationWiseProducts.i18n.selectLocations + '</h3>' +
            '<div class="location-checkboxes">';

        // Add location checkboxes
        $.each(locations, function (index, location) {
            modalHtml += '<label><input type="checkbox" name="product_locations[]" value="' + location.id + '" ' + (location.selected ? 'checked' : '') + '> ' + location.name + '</label><br>';
        });

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

    function togglepopupsetting() {
        if ($enable_popup.val() === 'no') {
            $popup_settings.hide();
        } else {
            $popup_settings.show();
        }
    }
    togglepopupsetting();
    $enable_popup.on('change', togglepopupsetting);

    function toggleherichicalsettings() {
        if ($herichical.val() === 'no') {
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
        $('.lwp-tab-content').hide();
        $($(this).attr('href')).show();
    });

    // Add toggle functionality for sections if needed
    $('.lwp-settings-box h2').addClass('lwp-section-toggle');
    $('.lwp-section-toggle').click(function () {
        $(this).next('.form-table').slideToggle();
        $(this).toggleClass('closed');
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