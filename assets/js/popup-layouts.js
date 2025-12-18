jQuery(function ($) {
    var $modal = $('#lwp-store-selector-modal.lwp-location-info-popup');
    if (!$modal.length) {
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
});
