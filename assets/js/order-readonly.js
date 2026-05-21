(function() {
    'use strict';

    function shouldSkip(node) {
        if (!node) {
            return true;
        }

        if (node.matches('.notice-dismiss, .handlediv, .postbox-header button, .hndle button, .toggle-indicator')) {
            return true;
        }

        if (node.closest('#screen-options-wrap, #contextual-help-wrap, #wpadminbar, #adminmenuwrap, #adminmenuback')) {
            return true;
        }

        return false;
    }

    function lockOrderEditing() {
        var roots = document.querySelectorAll('#post, .woocommerce-layout__main, .woocommerce-order');
        if (!roots.length) {
            roots = [document];
        }

        roots.forEach(function(root) {
            var controls = root.querySelectorAll('input:not([type="hidden"]), select, textarea, button');
            controls.forEach(function(control) {
                if (shouldSkip(control)) {
                    return;
                }

                control.disabled = true;
                if (control.tagName === 'INPUT' || control.tagName === 'TEXTAREA') {
                    control.readOnly = true;
                }
                control.classList.add('mulopimfwc-order-readonly-field');
            });

            var actionLinks = root.querySelectorAll('a.button, a.button-primary, a.button-secondary');
            actionLinks.forEach(function(link) {
                if (shouldSkip(link)) {
                    return;
                }

                link.classList.add('mulopimfwc-order-readonly-link');
                link.setAttribute('aria-disabled', 'true');
                link.setAttribute('tabindex', '-1');
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', lockOrderEditing);
    } else {
        lockOrderEditing();
    }

    var observer = new MutationObserver(function() {
        lockOrderEditing();
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
})();
