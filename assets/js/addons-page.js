(function($) {
    'use strict';

    var config = window.mulopimfwcAddonsPage || {};

    function escapeHtml(value) {
        return $('<div>').text(value || '').html();
    }

    function setLoading(isLoading) {
        var button = $('#mulopimfwc-addons-reload');
        button.prop('disabled', isLoading).text(isLoading ? config.reloading : config.reload);

        if (isLoading) {
            $('#mulopimfwc-addons-content').html(
                '<div class="mulopimfwc-addons-loading"><span class="spinner is-active"></span><span>' + escapeHtml(config.loading) + '</span></div>'
            );
        }
    }

    function renderNotices(data) {
        var notices = '';

        if (data && data.license_valid === false) {
            notices += '<div class="notice notice-warning"><p>' + escapeHtml(data.license_message || '') + '</p></div>';
        }

        if (data && data.error) {
            notices += '<div class="notice notice-error"><p>' + escapeHtml(data.error) + '</p></div>';
        }

        $('#mulopimfwc-addons-notices').html(notices);
    }

    function renderDetailsTab(modal, tabs, index) {
        var tab = tabs[index] || {};
        modal.find('.mulopimfwc-addon-details-modal__tab').removeClass('is-active').attr('aria-selected', 'false');
        modal.find('.mulopimfwc-addon-details-modal__tab[data-tab-index="' + index + '"]').addClass('is-active').attr('aria-selected', 'true');
        modal.find('.mulopimfwc-addon-details-modal__content').html(tab.content || '<p>' + escapeHtml(config.detailsFallback) + '</p>');
    }

    function renderDetailsSidebar(modal, details) {
        var sidebar = modal.find('.mulopimfwc-addon-details-modal__sidebar').empty();
        var list = $('<dl>');
        var meta = $.isArray(details.meta) ? details.meta : [];

        meta.forEach(function(item) {
            if (!item || !item.value) {
                return;
            }

            list.append($('<dt>').text(item.label || ''));
            list.append($('<dd>').text(item.value || ''));
        });

        sidebar.append(list);

        if ($.isArray(details.quick_links) && details.quick_links.length) {
            var links = $('<div>', {
                class: 'mulopimfwc-addon-details-modal__links'
            });

            details.quick_links.forEach(function(link) {
                if (!link || !link.url) {
                    return;
                }

                links.append($('<a>', {
                    href: link.url,
                    target: '_blank',
                    rel: 'noopener noreferrer',
                    text: link.label || link.url
                }));
            });

            sidebar.append(links);
        }
    }

    function renderDetailsFooter(modal, details) {
        var footer = modal.find('.mulopimfwc-addon-details-modal__footer').empty();
        var action = details.action || {};

        if (!action.label) {
            footer.hide();
            return;
        }

        footer.show();

        if (action.disabled || !action.url) {
            footer.append($('<button>', {
                type: 'button',
                class: 'button button-primary disabled',
                disabled: true,
                text: action.label
            }));
        } else {
            footer.append($('<a>', {
                class: action.className || 'button button-primary',
                href: action.url,
                text: action.label
            }));
        }
    }

    function openDetails(details) {
        var modal = $('#mulopimfwc-addon-details-modal');
        var tabs = $.isArray(details.tabs) && details.tabs.length ? details.tabs : [{
            slug: 'description',
            title: config.descriptionTitle || 'Description',
            content: '<p>' + escapeHtml(config.detailsFallback) + '</p>'
        }];
        var tabsNav = modal.find('.mulopimfwc-addon-details-modal__tabs').empty();
        var banner = modal.find('[data-addon-banner]').empty();

        modal.find('#mulopimfwc-addon-details-title').text(details.name || '');

        if (details.banner_url) {
            banner.removeClass('is-empty').append($('<img>', {
                src: details.banner_url,
                alt: ''
            }));
        } else {
            banner.addClass('is-empty');
        }

        tabs.forEach(function(tab, index) {
            tabsNav.append($('<button>', {
                type: 'button',
                role: 'tab',
                class: 'mulopimfwc-addon-details-modal__tab',
                'data-tab-index': index,
                'aria-selected': index === 0 ? 'true' : 'false',
                text: tab.title || tab.slug || ''
            }));
        });

        renderDetailsTab(modal, tabs, 0);
        renderDetailsSidebar(modal, details);
        renderDetailsFooter(modal, details);

        modal.data('tabs', tabs);
        modal.removeAttr('hidden').attr('aria-hidden', 'false');
        $('body').addClass('mulopimfwc-addon-details-open');
        modal.find('.mulopimfwc-addon-details-modal__close').trigger('focus');
    }

    function closeDetails() {
        var modal = $('#mulopimfwc-addon-details-modal');
        modal.attr('hidden', 'hidden').attr('aria-hidden', 'true');
        $('body').removeClass('mulopimfwc-addon-details-open');
    }

    function loadAddons(force) {
        setLoading(true);

        $.post(config.ajaxUrl || window.ajaxurl, {
            action: 'mulopimfwc_load_addons',
            nonce: config.nonce,
            force: force ? 1 : 0
        }).done(function(response) {
            if (!response || !response.success || !response.data) {
                $('#mulopimfwc-addons-content').html('<div class="mulopimfwc-addons-empty">' + escapeHtml(config.error) + '</div>');
                return;
            }

            $('#mulopimfwc-addons-content').html(response.data.html);
            $('#mulopimfwc-addons-last-check').text(response.data.last_check || '');
            renderNotices(response.data);
        }).fail(function(xhr) {
            var response = xhr.responseJSON || {};
            var message = response.data && response.data.message ? response.data.message : config.error;
            $('#mulopimfwc-addons-content').html('<div class="mulopimfwc-addons-empty">' + escapeHtml(message) + '</div>');
        }).always(function() {
            setLoading(false);
        });
    }

    $(document).on('click', '#mulopimfwc-addons-reload', function() {
        loadAddons(true);
    });

    $(document).on('click', '.mulopimfwc-addon-delete-link[data-mulopimfwc-confirm]', function(event) {
        if (!window.confirm($(this).attr('data-mulopimfwc-confirm') || '')) {
            event.preventDefault();
        }
    });

    $(document).on('click', '.mulopimfwc-addon-view-details', function(event) {
        event.preventDefault();

        try {
            openDetails(JSON.parse($(this).attr('data-addon-details') || '{}'));
        } catch (error) {
            openDetails({});
        }
    });

    $(document).on('click', '.mulopimfwc-addon-details-modal__tab', function() {
        var modal = $('#mulopimfwc-addon-details-modal');
        renderDetailsTab(modal, modal.data('tabs') || [], parseInt($(this).attr('data-tab-index'), 10) || 0);
    });

    $(document).on('click', '[data-mulopimfwc-addon-details-close]', function(event) {
        event.preventDefault();
        closeDetails();
    });

    $(document).on('keydown', function(event) {
        if (event.key === 'Escape' && !$('#mulopimfwc-addon-details-modal').is('[hidden]')) {
            closeDetails();
        }
    });

    $(function() {
        loadAddons(false);
    });
})(jQuery);
