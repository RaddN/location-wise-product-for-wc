(function ($) {
    'use strict';

    $(document).on('change', '.mulopimfwc-shipping-locations-all', function () {
        var $input = $(this);
        var listId = $input.attr('data-locations-list');
        var $list = listId ? $('#' + listId) : $();

        if (!$list.length) {
            return;
        }

        if ($input.is(':checked')) {
            $list.slideUp();
            $list.find('input[type="checkbox"]').prop('checked', false);
        } else {
            $list.slideDown();
        }
    });

    $(document).on('change', '#mulopimfwc_bulk_zone_select', function () {
        if ($(this).val()) {
            $('#mulopimfwc_bulk_locations_row, #mulopimfwc_bulk_apply_row').show();
        } else {
            $('#mulopimfwc_bulk_locations_row, #mulopimfwc_bulk_apply_row').hide();
        }
    });

    $(document).on('click', '#mulopimfwc_bulk_apply_btn', function () {
        var config = window.mulopimfwcShipping || {};
        var i18n = config.i18n || {};
        var $btn = $(this);
        var $spinner = $btn.next('.spinner');
        var zoneId = $('#mulopimfwc_bulk_zone_select').val();
        var locations = [];

        $('input[name="mulopimfwc_bulk_locations[]"]:checked').each(function () {
            locations.push($(this).val());
        });

        if (!zoneId) {
            window.alert(i18n.pleaseSelectZone || 'Please select a zone');
            return;
        }

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');

        $.ajax({
            url: config.ajaxUrl || window.ajaxurl,
            type: 'POST',
            data: {
                action: 'mulopimfwc_bulk_assign_locations',
                nonce: config.nonce,
                zone_id: zoneId,
                locations: locations
            },
            success: function (response) {
                if (response.success) {
                    window.alert(response.data.message);
                    window.location.reload();
                } else {
                    window.alert(response.data.message);
                }
            },
            error: function () {
                window.alert(i18n.genericError || 'An error occurred');
            },
            complete: function () {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });
})(jQuery);
