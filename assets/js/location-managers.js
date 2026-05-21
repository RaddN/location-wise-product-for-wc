(function($) {
    'use strict';

    var config = window.mulopimfwcLocationManagers || {};
    var searchTimeout;
    var isEditMode = false;

    function resetForm() {
        $('#mulopimfwc-manager-form')[0].reset();
        $('#manager-id').val('');
        $('#selected-user-id').val('');
        $('#user-search-results').empty().hide();
        $('#create-new-user').hide();
        $('#social_slack_webhook, #social_custom_webhook, #social_telegram_chat').val('');
        $('#toggle-create-user').text(config.createNewUserText || 'Create New User Instead');
        refreshDefaultLocationOptions('');
    }

    function refreshDefaultLocationOptions(preferredLocation) {
        var $defaultLocation = $('#manager-default-location');
        var fallbackLabel = config.defaultLocationFallback || 'Use first assigned location';
        var explicitPreferred = typeof preferredLocation === 'string' ? preferredLocation : null;
        var currentValue = explicitPreferred !== null ? explicitPreferred : ($defaultLocation.val() || '');
        var assignedLocations = [];

        $('input[name="assigned_locations[]"]:checked').each(function() {
            assignedLocations.push({
                value: $(this).val(),
                label: $.trim($(this).parent().text())
            });
        });

        $defaultLocation.empty();
        $defaultLocation.append($('<option></option>').val('').text(fallbackLabel));

        assignedLocations.forEach(function(locationData) {
            $defaultLocation.append(
                $('<option></option>').val(locationData.value).text(locationData.label)
            );
        });

        var hasCurrent = currentValue !== '' && assignedLocations.some(function(locationData) {
            return locationData.value === currentValue;
        });
        $defaultLocation.val(hasCurrent ? currentValue : '');
    }

    function loadManagerData(managerId, assignLocations, assignCapabilities, defaultLocation, socialChannels) {
        $('#mulopimfwc-modal-title').text(config.editTitle || 'Edit Location Manager');
        $('#manager-id').val(managerId);
        $('#action-type').val('edit');
        $('#search_or_add_manager').hide();

        $('input[name="assigned_locations[]"]').prop('checked', false);
        $('input[name="manager_capabilities[]"]').prop('checked', false);

        if (assignLocations && assignLocations.length > 0) {
            assignLocations.forEach(function(location) {
                $('input[name="assigned_locations[]"][value="' + location + '"]').prop('checked', true);
            });
        }
        refreshDefaultLocationOptions(defaultLocation || '');

        if (assignCapabilities && assignCapabilities.length > 0) {
            assignCapabilities.forEach(function(capability) {
                $('input[name="manager_capabilities[]"][value="' + capability + '"]').prop('checked', true);
            });
        }

        var social = socialChannels || {};
        $('#social_slack_webhook').val(social.slack_webhook || '');
        $('#social_custom_webhook').val(social.custom_webhook || '');
        $('#social_telegram_chat').val(social.telegram_chat_id || '');

        $('#mulopimfwc-manager-modal').show();
    }

    function searchUsers(query) {
        $.ajax({
            url: config.ajaxUrl || window.ajaxurl,
            type: 'POST',
            data: {
                action: 'mulopimfwc_search_users',
                query: query,
                nonce: $('#mulopimfwc_location_managers_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    displaySearchResults(response.data.users);
                }
            }
        });
    }

    function displaySearchResults(users) {
        var resultsContainer = $('#user-search-results');
        resultsContainer.empty();

        if (users.length === 0) {
            resultsContainer.append($('<div>', {
                class: 'search-result-item',
                text: config.noUsersFound || 'No users found'
            }));
        } else {
            users.forEach(function(user) {
                var item = $('<div>', {
                    class: 'search-result-item',
                    'data-user-id': user.ID
                });

                item.append($('<strong>').text(user.display_name));
                item.append(document.createTextNode(' (' + user.user_email + ')'));

                item.on('click', function() {
                    $('#selected-user-id').val(user.ID);
                    $('#user-search').val(user.display_name);
                    resultsContainer.empty().hide();
                });

                resultsContainer.append(item);
            });
        }

        resultsContainer.show();
    }

    function saveManager() {
        var formData = new FormData($('#mulopimfwc-manager-form')[0]);
        formData.append('action', 'mulopimfwc_create_location_manager');
        formData.append('nonce', $('#mulopimfwc_location_managers_nonce').val());

        $.ajax({
            url: config.ajaxUrl || window.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    window.location.reload();
                } else {
                    window.alert(response.data.message || config.errorSaving || 'Error saving manager');
                }
            }
        });

        setTimeout(function() {
            window.location.reload();
        }, 1000);
    }

    function deleteManager(managerId) {
        $.ajax({
            url: config.ajaxUrl || window.ajaxurl,
            type: 'POST',
            data: {
                action: 'mulopimfwc_delete_location_manager',
                manager_id: managerId,
                nonce: $('#mulopimfwc_location_managers_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    window.location.reload();
                } else {
                    window.alert(response.data.message || config.errorDeleting || 'Error deleting manager');
                }
            }
        });
    }

    $(function() {
        $('#mulopimfwc-add-manager-btn').on('click', function() {
            isEditMode = false;
            resetForm();
            $('#mulopimfwc-modal-title').text(config.addTitle || 'Add New Location Manager');
            $('#action-type').val('create');
            $('#mulopimfwc-manager-modal').show();
            $('#search_or_add_manager').show();
        });

        $(document).on('click', '.mulopimfwc-edit-manager', function() {
            isEditMode = true;
            loadManagerData(
                $(this).data('manager-id'),
                $(this).data('assign-locations'),
                $(this).data('assign-capabilities'),
                $(this).data('default-location') || '',
                $(this).data('social-channels') || {}
            );
        });

        $(document).on('click', '.mulopimfwc-delete-manager', function() {
            if (window.confirm(config.deleteConfirm || 'Are you sure you want to delete this location manager?')) {
                deleteManager($(this).data('manager-id'));
            }
        });

        $(document).on('click', '.mulopimfwc-modal-close', function() {
            $('#mulopimfwc-manager-modal').hide();
        });

        $('#toggle-create-user').on('click', function() {
            $('#create-new-user').toggle();
            var isVisible = $('#create-new-user').is(':visible');
            $(this).text(isVisible ? (config.selectExistingUserText || 'Select Existing User Instead') : (config.createNewUserText || 'Create New User Instead'));
        });

        $('#user-search').on('input', function() {
            var query = $(this).val();
            clearTimeout(searchTimeout);

            if (query.length < 2) {
                $('#user-search-results').empty().hide();
                return;
            }

            searchTimeout = setTimeout(function() {
                searchUsers(query);
            }, 300);
        });

        $('#mulopimfwc-manager-form').on('submit', function(event) {
            event.preventDefault();
            saveManager();
        });

        $(document).on('change', 'input[name="assigned_locations[]"]', function() {
            refreshDefaultLocationOptions();
        });
    });
})(jQuery);
