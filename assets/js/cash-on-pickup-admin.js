(function () {
    'use strict';

    var config = window.mulopimfwcCashOnPickupAdmin || {};
    var badgeHtml = config.badgeHtml || '';
    var tooltipId = config.tooltipId || 'mulopimfwc-cop-badge-tooltip';

    function insertBadge() {
        var row = document.getElementById('cash_on_pickup');
        if (!row || !badgeHtml) {
            return;
        }

        var title = row.querySelector('.woocommerce-list__item-title');
        if (!title || title.querySelector('.mulopimfwc-cop-badge')) {
            return;
        }

        title.insertAdjacentHTML('beforeend', badgeHtml);
        attachTooltip(title.querySelector('.mulopimfwc-cop-badge .woocommerce-official-extension-badge__container'));
    }

    function attachTooltip(target) {
        if (!target || target.dataset.mulopimfwcTooltipReady) {
            return;
        }

        var tooltip;
        var text = target.getAttribute('data-tooltip');

        function showTooltip() {
            if (!text) {
                return;
            }

            if (!tooltip) {
                tooltip = document.createElement('div');
                tooltip.id = tooltipId;
                tooltip.className = 'mulopimfwc-cop-tooltip';
                tooltip.textContent = text;
                document.body.appendChild(tooltip);
            }

            var rect = target.getBoundingClientRect();
            tooltip.style.top = (window.scrollY + rect.top - tooltip.offsetHeight - 8) + 'px';
            tooltip.style.left = (window.scrollX + rect.left) + 'px';
            tooltip.style.display = 'block';
        }

        function hideTooltip() {
            if (tooltip) {
                tooltip.style.display = 'none';
            }
        }

        target.addEventListener('mouseenter', showTooltip);
        target.addEventListener('focus', showTooltip);
        target.addEventListener('mouseleave', hideTooltip);
        target.addEventListener('blur', hideTooltip);

        target.dataset.mulopimfwcTooltipReady = '1';
    }

    if (typeof MutationObserver !== 'undefined') {
        var observer = new MutationObserver(insertBadge);
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        window.addEventListener('beforeunload', function () {
            observer.disconnect();
        });
    }

    insertBadge();
    document.addEventListener('DOMContentLoaded', insertBadge);
})();
