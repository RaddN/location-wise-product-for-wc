(function($) {
    'use strict';

    function syncBusinessHourRow($input) {
        var day = $input.data('day');
        var mode = $input.val();
        var $times = $('.mulopimfwc-bh-times[data-day="' + day + '"]');

        if (mode === 'custom') {
            $times.show();
        } else {
            $times.hide();
        }
    }

    $(function() {
        $(document).on('change', '.mulopimfwc-bh-mode', function() {
            syncBusinessHourRow($(this));
        });

        $('.mulopimfwc-bh-mode:checked').each(function() {
            syncBusinessHourRow($(this));
        });
    });
})(jQuery);
