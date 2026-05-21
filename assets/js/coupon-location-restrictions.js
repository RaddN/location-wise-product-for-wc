(function ($) {
    'use strict';

    $(function () {
        var $include = $('#_mulopimfwc_coupon_locations_include');
        var $exclude = $('#_mulopimfwc_coupon_locations_exclude');
        var syncing = false;

        if (!$include.length || !$exclude.length) {
            return;
        }

        function asArray(values) {
            if (!values) {
                return [];
            }

            return Array.isArray(values) ? values : [values];
        }

        function removeOverlap(primaryValues, secondaryValues) {
            var blocked = {};

            $.each(primaryValues, function (_, value) {
                blocked[String(value)] = true;
            });

            return $.grep(secondaryValues, function (value) {
                return !blocked[String(value)];
            });
        }

        function syncOneWay($source, $target) {
            var sourceValues = asArray($source.val());
            var targetValues = asArray($target.val());
            var cleanTargetValues = removeOverlap(sourceValues, targetValues);
            var blocked = {};

            $.each(sourceValues, function (_, value) {
                blocked[String(value)] = true;
            });

            if (cleanTargetValues.length !== targetValues.length) {
                $target.val(cleanTargetValues);
            }

            $target.find('option').each(function () {
                var value = String($(this).val());
                var shouldDisable = !!blocked[value] && !$(this).prop('selected');
                $(this).prop('disabled', shouldDisable);
            });
        }

        function syncBoth(preferred) {
            if (syncing) {
                return;
            }

            syncing = true;

            if (preferred === 'exclude') {
                syncOneWay($exclude, $include);
                syncOneWay($include, $exclude);
            } else {
                syncOneWay($include, $exclude);
                syncOneWay($exclude, $include);
            }

            $include.trigger('change.select2');
            $exclude.trigger('change.select2');

            syncing = false;
        }

        $include.on('change select2:select select2:unselect', function () {
            syncBoth('include');
        });

        $exclude.on('change select2:select select2:unselect', function () {
            syncBoth('exclude');
        });

        syncBoth('include');
    });
})(jQuery);
