(function($) {
    'use strict';

    var config = window.mulopimfwcLocationTracking || {};

    $(document).on('change', '.mulopimfwc-location-selector, #mulopimfwc_store_location', function() {
        var locationSlug = $(this).val();
        var locationName = $(this).find('option:selected').text();

        if (locationSlug && locationSlug !== 'all-products') {
            $.ajax({
                url: config.ajaxUrl || window.ajaxurl,
                type: 'POST',
                data: {
                    action: 'mulopimfwc_track_location_selection',
                    location_slug: locationSlug,
                    location_name: locationName,
                    nonce: config.nonce || ''
                }
            });
        }
    });
})(jQuery);
