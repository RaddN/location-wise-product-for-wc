(function($) {
    'use strict';

    var config = window.mulopimfwcDeactivationFeedback || {};
    var deactivateUrl = '';

    function showModal(event) {
        event.preventDefault();
        deactivateUrl = $(this).attr('href');
        $('#mulopimfwc_plugin-deactivation-feedback').show();
    }

    function closeModal() {
        $('#mulopimfwc_plugin-deactivation-feedback').hide();
    }

    function bindDeactivationLinks() {
        var pluginBasename = config.pluginBasename || '';
        var pluginSlug = config.pluginSlug || '';
        var selectors = [
            'tr[data-slug="' + pluginSlug + '"] .deactivate a',
            'tr[data-plugin="' + pluginBasename + '"] .deactivate a',
            '.wp-list-table.plugins tr[data-slug="' + pluginSlug + '"] .row-actions .deactivate a'
        ];

        selectors.forEach(function(selector) {
            $(document).on('click', selector, showModal);
        });

        $('a[href*="action=deactivate"]').each(function() {
            var href = $(this).attr('href') || '';
            if (pluginBasename && href.indexOf(encodeURIComponent(pluginBasename)) > -1) {
                $(this).on('click', showModal);
            }
        });
    }

    function submitFeedback(event) {
        event.preventDefault();

        var $form = $(this);
        var reason = $('input[name="reason"]:checked').val();
        var otherReason = $('textarea[name="other_reason"]').val();

        if (reason === 'other' && otherReason) {
            reason = otherReason;
        }

        $form.find('button.btn.btn-primary').text(config.deactivatingText || 'Deactivating...');

        $.ajax({
            url: config.ajaxUrl || window.ajaxurl,
            type: 'POST',
            data: {
                action: 'mulopimfwc_send_deactivation_feedback',
                reason: reason || 'no-reason-provided',
                nonce: config.nonce || ''
            },
            success: function() {
                setTimeout(function() {
                    window.location.href = deactivateUrl;
                }, 500);
            },
            error: function(xhr, status, error) {
                if (window.console && window.console.error) {
                    window.console.error('Feedback send failed:', status, error);
                    window.console.error('Response:', xhr.responseText);
                }

                setTimeout(function() {
                    window.location.href = deactivateUrl;
                }, 500);
            }
        });
    }

    $(function() {
        bindDeactivationLinks();

        $('#mulopimfwc_deactivation-feedback-form').on('submit', submitFeedback);

        $('input[name="reason"]').on('change', function() {
            if ($(this).val() === 'other') {
                $('.other-reason-container').slideDown(300);
            } else {
                $('.other-reason-container').slideUp(300);
            }
        });

        $('.close-button').on('click', closeModal);

        $('.feedback-overlay').on('click', function(event) {
            if (event.target === this) {
                closeModal();
            }
        });

        $(document).on('keyup', function(event) {
            if (event.keyCode === 27) {
                closeModal();
            }
        });
    });
})(jQuery);
