<?php
if (!defined('ABSPATH')) {
    exit;
}

class MULOPIMFWC_Addons_Page
{
    private const MANIFEST_TRANSIENT = 'mulopimfwc_addons_manifest_cache_v2';
    private const INSTALLED_STATUS_OPTION = 'mulopimfwc_addons_installed_status';
    private const LAST_ERROR_OPTION = 'mulopimfwc_addons_last_error';
    private const LAST_CHECK_OPTION = 'mulopimfwc_addons_last_update_check';
    private const NOTICE_TRANSIENT = 'mulopimfwc_addons_admin_notice';
    private const MANIFEST_PRODUCT_SLUG = 'multi-location-product-inventory-management-for-woocommerce-pro';
    private const DEFAULT_MANIFEST_URL = 'https://plugincy.com/wp-json/plugincy/v1/' . self::MANIFEST_PRODUCT_SLUG . '/addons';

    private static $instance = null;

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_post_mulopimfwc_addon_action', [$this, 'handle_action']);
        add_action('wp_ajax_mulopimfwc_load_addons', [$this, 'ajax_load_addons']);
    }

    public function render_page()
    {
        if (!current_user_can('install_plugins')) {
            wp_die(esc_html__('You do not have permission to manage add-ons.', 'multi-location-product-and-inventory-management-pro'));
        }

        $last_error = get_option(self::LAST_ERROR_OPTION, '');
        $last_check = (int) get_option(self::LAST_CHECK_OPTION, 0);
        $ajax_nonce = wp_create_nonce('mulopimfwc_addons_ajax');
        $notice = get_transient(self::NOTICE_TRANSIENT);
        if (is_array($notice)) {
            delete_transient(self::NOTICE_TRANSIENT);
        }
?>
        <div class="wrap mulopimfwc-addons-page">
            <h1><?php echo esc_html__('Addons', 'multi-location-product-and-inventory-management-pro'); ?></h1>

            <div class="mulopimfwc-addons-hero">
                <div>
                    <p><?php echo esc_html__('Install and manage private Plugincy add-ons delivered from your licensed Plugincy account.', 'multi-location-product-and-inventory-management-pro'); ?></p>
                </div>
                <button type="button" class="button button-primary" id="mulopimfwc-addons-reload">
                    <?php echo esc_html__('Reload add-ons', 'multi-location-product-and-inventory-management-pro'); ?>
                </button>
            </div>

            <?php settings_errors('mulopimfwc_addons'); ?>

            <div id="mulopimfwc-addons-notices">
                <?php if (is_array($notice) && !empty($notice['message'])) : ?>
                    <div class="notice notice-<?php echo esc_attr(!empty($notice['type']) ? (string) $notice['type'] : 'info'); ?>">
                        <p><?php echo esc_html((string) $notice['message']); ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($last_error !== '') : ?>
                    <div class="notice notice-error">
                        <p><?php echo esc_html($last_error); ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mulopimfwc-addons-toolbar">
                <span id="mulopimfwc-addons-last-check" class="description">
                    <?php echo esc_html($last_check > 0 ? $this->format_last_check($last_check) : __('Add-ons load from Plugincy when this page opens.', 'multi-location-product-and-inventory-management-pro')); ?>
                </span>
            </div>

            <div id="mulopimfwc-addons-content" class="mulopimfwc-addons-grid" aria-live="polite">
                <div class="mulopimfwc-addons-loading">
                    <span class="spinner is-active"></span>
                    <span><?php echo esc_html__('Loading add-ons from Plugincy...', 'multi-location-product-and-inventory-management-pro'); ?></span>
                </div>
            </div>
        </div>
        <?php $this->render_details_modal(); ?>
        <style>
            .mulopimfwc-addons-hero {
                align-items: center;
                background: #fff;
                border: 1px solid #dcdcde;
                border-left: 4px solid #2271b1;
                border-radius: 6px;
                display: flex;
                gap: 16px;
                justify-content: space-between;
                margin: 18px 0;
                padding: 18px 20px;
            }

            .mulopimfwc-addons-hero p {
                color: #50575e;
                margin: 0;
                max-width: 720px;
            }

            .mulopimfwc-addons-toolbar {
                align-items: center;
                display: flex;
                justify-content: space-between;
                margin: 12px 0;
            }

            .mulopimfwc-addons-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
                gap: 18px;
                margin-top: 18px;
            }

            .mulopimfwc-addons-loading,
            .mulopimfwc-addons-empty {
                align-items: center;
                background: #fff;
                border: 1px solid #dcdcde;
                border-radius: 6px;
                color: #50575e;
                display: flex;
                gap: 10px;
                grid-column: 1 / -1;
                padding: 22px;
            }

            .mulopimfwc-addon-card {
                background: #fff;
                border: 1px solid #dcdcde;
                border-radius: 6px;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
                display: flex;
                flex-direction: column;
                min-height: 100%;
                overflow: hidden;
                max-width: 430px;
            }

            .mulopimfwc-addon-card__header {
                align-items: flex-start;
                display: flex;
                gap: 16px;
                justify-content: space-between;
                padding: 18px 18px 0;
            }

            .mulopimfwc-addon-card__identity {
                align-items: center;
                display: flex;
                gap: 14px;
                min-width: 0;
            }

            .mulopimfwc-addon-card__logo {
                align-items: center;
                background: #f6f7f7;
                border: 1px solid #dcdcde;
                border-radius: 6px;
                color: #646970;
                display: flex;
                flex: 0 0 56px;
                font-weight: 700;
                height: 56px;
                justify-content: center;
                overflow: hidden;
                text-transform: uppercase;
                width: 56px;
            }

            .mulopimfwc-addon-card__logo img {
                display: block;
                height: 100%;
                object-fit: contain;
                width: 100%;
            }

            .mulopimfwc-addon-card h2 {
                font-size: 17px;
                line-height: 1.3;
                margin: 0 0 8px;
            }

            .mulopimfwc-addon-card p {
                margin-top: 0;
            }

            .mulopimfwc-addon-card__description {
                color: #50575e;
                margin-bottom: 0;
            }

            .mulopimfwc-addon-status {
                border-radius: 999px;
                display: inline-block;
                font-size: 12px;
                font-weight: 600;
                line-height: 1;
                padding: 7px 10px;
                white-space: nowrap;
            }

            .mulopimfwc-addon-status--active {
                background: #edfaef;
                color: #0a6b21;
            }

            .mulopimfwc-addon-status--inactive {
                background: #fff8e5;
                color: #8a5a00;
            }

            .mulopimfwc-addon-status--missing {
                background: #f0f0f1;
                color: #50575e;
            }

            .mulopimfwc-addon-status--update {
                background: #e7f5ff;
                color: #005c99;
            }

            .mulopimfwc-addon-meta {
                display: grid;
                grid-template-columns: 130px minmax(0, 1fr);
                margin: 16px 18px;
            }

            .mulopimfwc-addon-meta dt,
            .mulopimfwc-addon-meta dd {
                margin: 0;
                padding: 8px 0;
            }

            .mulopimfwc-addon-meta dt {
                color: #646970;
                font-weight: 600;
            }

            .mulopimfwc-addon-actions {
                gap: 8px;
                align-items: center;
                background: #f9f9f9;
                display: flex;
                flex-wrap: wrap;
                margin-top: auto;
                padding: 1em 2em;
                border-top: 1px solid #dcdcde;
            }

            .mulopimfwc-addon-details-modal[hidden] {
                display: none;
            }

            .mulopimfwc-addon-details-modal {
                align-items: center;
                bottom: 0;
                display: flex;
                justify-content: center;
                left: 0;
                padding: 24px;
                position: fixed;
                right: 0;
                top: 0;
                z-index: 100000;
            }

            .mulopimfwc-addon-details-modal__backdrop {
                background: rgba(0, 0, 0, 0.55);
                bottom: 0;
                left: 0;
                position: absolute;
                right: 0;
                top: 0;
            }

            .mulopimfwc-addon-details-modal__dialog {
                background: #fff;
                box-shadow: 0 24px 90px rgba(0, 0, 0, 0.32);
                box-sizing: border-box;
                max-height: calc(100vh - 48px);
                max-width: 840px;
                overflow: auto;
                position: relative;
                width: min(840px, 100%);
            }

            .mulopimfwc-addon-details-modal__banner {
                align-items: flex-end;
                background: #f0f0f1;
                display: flex;
                min-height: 180px;
                overflow: hidden;
            }

            .mulopimfwc-addon-details-modal__banner img {
                display: block;
                height: 100%;
                max-height: 260px;
                object-fit: cover;
                width: 100%;
            }

            .mulopimfwc-addon-details-modal__banner.is-empty {
                display: none;
            }

            .mulopimfwc-addon-details-modal__header {
                align-items: center;
                border-bottom: 1px solid #dcdcde;
                display: flex;
                justify-content: space-between;
                padding: 16px 20px;
            }

            .mulopimfwc-addon-details-modal__header h2 {
                font-size: 22px;
                line-height: 1.3;
                margin: 0;
            }

            .mulopimfwc-addon-details-modal__close {
                align-items: center;
                background: #f6f7f7;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                color: #1d2327;
                cursor: pointer;
                display: inline-flex;
                font-size: 22px;
                height: 32px;
                justify-content: center;
                line-height: 1;
                padding: 0;
                width: 32px;
            }

            .mulopimfwc-addon-details-modal__tabs {
                background: #f6f7f7;
                border-bottom: 1px solid #dcdcde;
                display: flex;
                flex-wrap: wrap;
                gap: 0;
                padding: 0 12px;
            }

            .mulopimfwc-addon-details-modal__tab {
                background: transparent;
                border: 0;
                border-left: 1px solid transparent;
                border-right: 1px solid transparent;
                color: #2271b1;
                cursor: pointer;
                font-size: 14px;
                margin: 0;
                padding: 12px 14px;
            }

            .mulopimfwc-addon-details-modal__tab.is-active {
                background: #fff;
                border-color: #dcdcde;
                color: #1d2327;
                margin-bottom: -1px;
            }

            .mulopimfwc-addon-details-modal__body {
                display: grid;
                grid-template-columns: minmax(0, 1fr) 250px;
                min-height: 280px;
            }

            .mulopimfwc-addon-details-modal__content {
                color: #1d2327;
                font-size: 14px;
                line-height: 1.7;
                min-width: 0;
                padding: 26px;
            }

            .mulopimfwc-addon-details-modal__content img {
                height: auto;
                max-width: 100%;
            }

            .mulopimfwc-addon-details-modal__sidebar {
                background: #f9f9f9;
                border-left: 1px solid #dcdcde;
                padding: 22px 18px;
            }

            .mulopimfwc-addon-details-modal__sidebar dl {
                display: grid;
                gap: 8px;
                margin: 0;
            }

            .mulopimfwc-addon-details-modal__sidebar dt {
                color: #50575e;
                font-weight: 700;
                margin: 8px 0 0;
            }

            .mulopimfwc-addon-details-modal__sidebar dd {
                margin: 0;
            }

            .mulopimfwc-addon-details-modal__links {
                display: grid;
                gap: 7px;
                margin-top: 18px;
            }

            .mulopimfwc-addon-details-modal__footer {
                align-items: center;
                background: #f6f7f7;
                border-top: 1px solid #dcdcde;
                display: flex;
                gap: 8px;
                justify-content: flex-end;
                padding: 14px 18px;
            }

            body.mulopimfwc-addon-details-open {
                overflow: hidden;
            }

            @media (max-width: 782px) {
                .mulopimfwc-addons-hero {
                    align-items: stretch;
                    flex-direction: column;
                }

                .mulopimfwc-addons-grid {
                    grid-template-columns: 1fr;
                }

                .mulopimfwc-addon-card__header {
                    flex-direction: column;
                }

                .mulopimfwc-addon-details-modal {
                    padding: 12px;
                }

                .mulopimfwc-addon-details-modal__body {
                    grid-template-columns: 1fr;
                }

                .mulopimfwc-addon-details-modal__sidebar {
                    border-left: 0;
                    border-top: 1px solid #dcdcde;
                }
            }
        </style>
        <script>
            (function($) {
                'use strict';

                var config = <?php echo wp_json_encode([
                                    'ajaxUrl' => admin_url('admin-ajax.php'),
                                    'nonce' => $ajax_nonce,
                                    'loading' => __('Loading add-ons from Plugincy...', 'multi-location-product-and-inventory-management-pro'),
                                    'error' => __('Add-ons could not be loaded from Plugincy. Please reload or check the license connection.', 'multi-location-product-and-inventory-management-pro'),
                                    'reloading' => __('Reloading...', 'multi-location-product-and-inventory-management-pro'),
                                    'reload' => __('Reload add-ons', 'multi-location-product-and-inventory-management-pro'),
                                    'detailsFallback' => __('No details were provided for this add-on yet.', 'multi-location-product-and-inventory-management-pro'),
                                ]); ?>;

                function setLoading(isLoading) {
                    var button = $('#mulopimfwc-addons-reload');
                    button.prop('disabled', isLoading).text(isLoading ? config.reloading : config.reload);

                    if (isLoading) {
                        $('#mulopimfwc-addons-content').html(
                            '<div class="mulopimfwc-addons-loading"><span class="spinner is-active"></span><span>' + config.loading + '</span></div>'
                        );
                    }
                }

                function renderNotices(data) {
                    var notices = '';

                    if (data && data.license_valid === false) {
                        notices += '<div class="notice notice-warning"><p>' + $('<div>').text(data.license_message || '').html() + '</p></div>';
                    }

                    if (data && data.error) {
                        notices += '<div class="notice notice-error"><p>' + $('<div>').text(data.error).html() + '</p></div>';
                    }

                    $('#mulopimfwc-addons-notices').html(notices);
                }

                function renderDetailsTab(modal, tabs, index) {
                    var tab = tabs[index] || {};
                    modal.find('.mulopimfwc-addon-details-modal__tab').removeClass('is-active').attr('aria-selected', 'false');
                    modal.find('.mulopimfwc-addon-details-modal__tab[data-tab-index="' + index + '"]').addClass('is-active').attr('aria-selected', 'true');
                    modal.find('.mulopimfwc-addon-details-modal__content').html(tab.content || '<p>' + config.detailsFallback + '</p>');
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
                        title: <?php echo wp_json_encode(__('Description', 'multi-location-product-and-inventory-management-pro')); ?>,
                        content: '<p>' + config.detailsFallback + '</p>'
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

                    $.post(config.ajaxUrl, {
                        action: 'mulopimfwc_load_addons',
                        nonce: config.nonce,
                        force: force ? 1 : 0
                    }).done(function(response) {
                        if (!response || !response.success || !response.data) {
                            $('#mulopimfwc-addons-content').html('<div class="mulopimfwc-addons-empty">' + config.error + '</div>');
                            return;
                        }

                        $('#mulopimfwc-addons-content').html(response.data.html);
                        $('#mulopimfwc-addons-last-check').text(response.data.last_check || '');
                        renderNotices(response.data);
                    }).fail(function(xhr) {
                        var response = xhr.responseJSON || {};
                        var message = response.data && response.data.message ? response.data.message : config.error;
                        $('#mulopimfwc-addons-content').html('<div class="mulopimfwc-addons-empty">' + $('<div>').text(message).html() + '</div>');
                    }).always(function() {
                        setLoading(false);
                    });
                }

                $(document).on('click', '#mulopimfwc-addons-reload', function() {
                    loadAddons(true);
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
        </script>
        <?php
    }

    private function render_details_modal(): void
    {
        ?>
        <div id="mulopimfwc-addon-details-modal" class="mulopimfwc-addon-details-modal" hidden aria-hidden="true">
            <div class="mulopimfwc-addon-details-modal__backdrop" data-mulopimfwc-addon-details-close></div>
            <div class="mulopimfwc-addon-details-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="mulopimfwc-addon-details-title">
                <div class="mulopimfwc-addon-details-modal__banner" data-addon-banner></div>
                <div class="mulopimfwc-addon-details-modal__header">
                    <h2 id="mulopimfwc-addon-details-title"></h2>
                    <button type="button" class="mulopimfwc-addon-details-modal__close" data-mulopimfwc-addon-details-close aria-label="<?php esc_attr_e('Close add-on details', 'multi-location-product-and-inventory-management-pro'); ?>">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="mulopimfwc-addon-details-modal__tabs" role="tablist"></div>
                <div class="mulopimfwc-addon-details-modal__body">
                    <div class="mulopimfwc-addon-details-modal__content"></div>
                    <aside class="mulopimfwc-addon-details-modal__sidebar"></aside>
                </div>
                <div class="mulopimfwc-addon-details-modal__footer"></div>
            </div>
        </div>
        <?php
    }

    public function ajax_load_addons()
    {
        if (!current_user_can('install_plugins')) {
            wp_send_json_error(
                ['message' => __('You do not have permission to manage add-ons.', 'multi-location-product-and-inventory-management-pro')],
                403
            );
        }

        check_ajax_referer('mulopimfwc_addons_ajax', 'nonce');

        $force = !empty($_POST['force']);
        $license_valid = $this->has_valid_license();
        $addons = $this->get_addons_with_manifest($force);
        $statuses = $this->get_addon_statuses($addons);
        $last_error = get_option(self::LAST_ERROR_OPTION, '');
        $last_check = (int) get_option(self::LAST_CHECK_OPTION, 0);

        wp_send_json_success([
            'html' => $this->render_addons_cards($addons, $statuses, $license_valid),
            'last_check' => $last_check > 0 ? $this->format_last_check($last_check) : '',
            'error' => is_scalar($last_error) ? (string) $last_error : '',
            'license_valid' => $license_valid,
            'license_message' => __('Activate a valid Plugincy license to install or update private add-ons.', 'multi-location-product-and-inventory-management-pro'),
        ]);
    }

    private function render_addons_cards(array $addons, array $statuses, bool $license_valid): string
    {
        ob_start();

        if (empty($addons)) {
        ?>
            <div class="mulopimfwc-addons-empty">
                <?php echo esc_html__('No add-ons were returned from Plugincy for this license.', 'multi-location-product-and-inventory-management-pro'); ?>
            </div>
        <?php
            return (string) ob_get_clean();
        }

        foreach ($addons as $addon) {
            $slug = isset($addon['slug']) ? sanitize_key((string) $addon['slug']) : '';
            if ($slug === '') {
                continue;
            }

            $status = $statuses[$slug] ?? $this->get_addon_status($addon);
            $this->render_addon_card($addon, $status, $license_valid);
        }

        return (string) ob_get_clean();
    }

    private function render_addon_card(array $addon, array $status, bool $license_valid): void
    {
        $name = isset($addon['name']) && is_scalar($addon['name']) ? (string) $addon['name'] : __('Plugincy add-on', 'multi-location-product-and-inventory-management-pro');
        $description = isset($addon['description']) && is_scalar($addon['description']) ? (string) $addon['description'] : '';
        $logo_url = isset($addon['logo_url']) && is_scalar($addon['logo_url']) ? esc_url((string) $addon['logo_url']) : '';
        $requires = isset($addon['requires']) && is_scalar($addon['requires']) && trim((string) $addon['requires']) !== ''
            ? (string) $addon['requires']
            : __('Not specified', 'multi-location-product-and-inventory-management-pro');
        $details_payload = $this->get_addon_details_payload($addon, $status, $license_valid);
        ?>
        <div class="mulopimfwc-addon-card">
            <div class="mulopimfwc-addon-card__header">
                <div class="mulopimfwc-addon-card__identity">
                    <div class="mulopimfwc-addon-card__logo">
                        <?php if ($logo_url) : ?>
                            <img src="<?php echo esc_url($logo_url); ?>" alt="">
                        <?php else : ?>
                            <span><?php echo esc_html($this->get_addon_initials($name)); ?></span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h2><?php echo esc_html($name); ?></h2>
                        <?php if ($description !== '') : ?>
                            <p class="mulopimfwc-addon-card__description"><?php echo wp_kses_post($description); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <span class="mulopimfwc-addon-status mulopimfwc-addon-status--<?php echo esc_attr($status['state']); ?>">
                    <?php echo esc_html($status['label']); ?>
                </span>
            </div>

            <dl class="mulopimfwc-addon-meta">
                <dt><?php echo esc_html__('Current Version', 'multi-location-product-and-inventory-management-pro'); ?></dt>
                <dd><?php echo esc_html($status['installed_version'] ?: __('Not installed', 'multi-location-product-and-inventory-management-pro')); ?></dd>
                <dt><?php echo esc_html__('Latest Version', 'multi-location-product-and-inventory-management-pro'); ?></dt>
                <dd><?php echo esc_html(!empty($addon['version']) ? (string) $addon['version'] : __('Unknown', 'multi-location-product-and-inventory-management-pro')); ?></dd>
                <dt><?php echo esc_html__('Requires', 'multi-location-product-and-inventory-management-pro'); ?></dt>
                <dd><?php echo esc_html($requires); ?></dd>
            </dl>

            <div class="mulopimfwc-addon-actions">
                <button type="button" class="button mulopimfwc-addon-view-details" data-addon-details="<?php echo esc_attr(wp_json_encode($details_payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)); ?>">
                    <?php echo esc_html__('View details', 'multi-location-product-and-inventory-management-pro'); ?>
                </button>
                <?php $this->render_quick_links($addon); ?>
                <?php $this->render_actions($addon, $status, $license_valid); ?>
            </div>
        </div>
<?php
    }

    private function render_quick_links(array $addon): void
    {
        foreach ($this->get_quick_links($addon) as $link) {
            printf(
                '<a class="button" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
                esc_url($link['url']),
                esc_html($link['label'])
            );
        }
    }

    private function get_addon_details_payload(array $addon, array $status, bool $license_valid): array
    {
        $name = isset($addon['name']) && is_scalar($addon['name']) ? (string) $addon['name'] : __('Plugincy add-on', 'multi-location-product-and-inventory-management-pro');
        $requires = isset($addon['requires']) && is_scalar($addon['requires']) && trim((string) $addon['requires']) !== ''
            ? (string) $addon['requires']
            : '';

        $meta = [
            [
                'label' => __('Status', 'multi-location-product-and-inventory-management-pro'),
                'value' => isset($status['label']) ? (string) $status['label'] : '',
            ],
            [
                'label' => __('Current Version', 'multi-location-product-and-inventory-management-pro'),
                'value' => !empty($status['installed_version']) ? (string) $status['installed_version'] : __('Not installed', 'multi-location-product-and-inventory-management-pro'),
            ],
            [
                'label' => __('Latest Version', 'multi-location-product-and-inventory-management-pro'),
                'value' => !empty($addon['version']) ? (string) $addon['version'] : __('Unknown', 'multi-location-product-and-inventory-management-pro'),
            ],
            [
                'label' => __('Requires', 'multi-location-product-and-inventory-management-pro'),
                'value' => $requires !== '' ? $requires : __('Not specified', 'multi-location-product-and-inventory-management-pro'),
            ],
            [
                'label' => __('Requires WordPress', 'multi-location-product-and-inventory-management-pro'),
                'value' => isset($addon['requires_wp']) && is_scalar($addon['requires_wp']) ? (string) $addon['requires_wp'] : '',
            ],
            [
                'label' => __('Requires PHP', 'multi-location-product-and-inventory-management-pro'),
                'value' => isset($addon['requires_php']) && is_scalar($addon['requires_php']) ? (string) $addon['requires_php'] : '',
            ],
            [
                'label' => __('Tested up to', 'multi-location-product-and-inventory-management-pro'),
                'value' => isset($addon['tested']) && is_scalar($addon['tested']) ? (string) $addon['tested'] : '',
            ],
            [
                'label' => __('Tested WooCommerce', 'multi-location-product-and-inventory-management-pro'),
                'value' => isset($addon['tested_wc']) && is_scalar($addon['tested_wc']) ? (string) $addon['tested_wc'] : '',
            ],
        ];

        return [
            'slug' => isset($addon['slug']) && is_scalar($addon['slug']) ? sanitize_key((string) $addon['slug']) : '',
            'name' => $name,
            'banner_url' => $this->get_banner_url($addon),
            'tabs' => $this->get_detail_tabs($addon),
            'quick_links' => $this->get_quick_links($addon),
            'meta' => $meta,
            'action' => $this->get_primary_action($addon, $status, $license_valid),
        ];
    }

    private function get_primary_action(array $addon, array $status, bool $license_valid): array
    {
        $slug = isset($addon['slug']) && is_scalar($addon['slug']) ? sanitize_key((string) $addon['slug']) : '';
        if ($slug === '') {
            return [];
        }

        if (empty($status['installed'])) {
            return [
                'label' => __('Install', 'multi-location-product-and-inventory-management-pro'),
                'url' => $license_valid && !empty($addon['package']) ? $this->action_url('install', $slug) : '',
                'className' => 'button button-primary',
                'disabled' => !$license_valid || empty($addon['package']),
            ];
        }

        if (!empty($status['update_available']) && $license_valid && !empty($addon['package'])) {
            return [
                'label' => __('Update Now', 'multi-location-product-and-inventory-management-pro'),
                'url' => $this->action_url('update', $slug),
                'className' => 'button button-primary',
                'disabled' => false,
            ];
        }

        if (empty($status['active'])) {
            return [
                'label' => __('Activate', 'multi-location-product-and-inventory-management-pro'),
                'url' => $this->action_url('activate', $slug),
                'className' => 'button button-primary',
                'disabled' => false,
            ];
        }

        return [
            'label' => __('Deactivate', 'multi-location-product-and-inventory-management-pro'),
            'url' => $this->action_url('deactivate', $slug),
            'className' => 'button',
            'disabled' => false,
        ];
    }

    private function get_banner_url(array $addon): string
    {
        foreach (['banner_url', 'banner'] as $key) {
            if (!empty($addon[$key]) && is_scalar($addon[$key])) {
                return esc_url_raw((string) $addon[$key]);
            }
        }

        if (!empty($addon['banners']) && is_array($addon['banners'])) {
            foreach (['high', 'low', 'default'] as $key) {
                if (!empty($addon['banners'][$key]) && is_scalar($addon['banners'][$key])) {
                    return esc_url_raw((string) $addon['banners'][$key]);
                }
            }
        }

        return '';
    }

    private function get_quick_links(array $addon): array
    {
        if (empty($addon['quick_links']) || !is_array($addon['quick_links'])) {
            return [];
        }

        $links = [];
        foreach ($addon['quick_links'] as $link) {
            if (!is_array($link)) {
                continue;
            }

            $url = isset($link['url']) && is_scalar($link['url']) ? esc_url_raw((string) $link['url']) : '';
            if ($url === '') {
                continue;
            }

            $label = isset($link['label']) && is_scalar($link['label']) && trim((string) $link['label']) !== ''
                ? sanitize_text_field((string) $link['label'])
                : $url;

            $links[] = [
                'label' => $label,
                'url' => $url,
            ];
        }

        return $links;
    }

    private function get_detail_tabs(array $addon): array
    {
        $tabs = [];

        if (!empty($addon['tabs']) && is_array($addon['tabs'])) {
            foreach ($addon['tabs'] as $tab) {
                if (!is_array($tab)) {
                    continue;
                }

                $slug = isset($tab['slug']) && is_scalar($tab['slug']) ? sanitize_key((string) $tab['slug']) : '';
                $title = isset($tab['title']) && is_scalar($tab['title']) ? sanitize_text_field((string) $tab['title']) : '';
                $content = isset($tab['content']) && is_scalar($tab['content']) ? wp_kses_post((string) $tab['content']) : '';

                if ($slug === '' && $title !== '') {
                    $slug = sanitize_key($title);
                }

                if ($title === '' && $slug !== '') {
                    $title = ucwords(str_replace(['-', '_'], ' ', $slug));
                }

                if ($slug === '' || $title === '' || trim($content) === '') {
                    continue;
                }

                $tabs[$slug] = [
                    'slug' => $slug,
                    'title' => $title,
                    'content' => $content,
                ];
            }
        }

        if (!empty($addon['sections']) && is_array($addon['sections'])) {
            $labels = !empty($addon['section_labels']) && is_array($addon['section_labels']) ? $addon['section_labels'] : [];
            foreach ($addon['sections'] as $slug => $content) {
                $slug = sanitize_key((string) $slug);
                $content = is_scalar($content) ? wp_kses_post((string) $content) : '';
                if ($slug === '' || trim($content) === '' || isset($tabs[$slug])) {
                    continue;
                }

                $title = isset($labels[$slug]) && is_scalar($labels[$slug]) && trim((string) $labels[$slug]) !== ''
                    ? sanitize_text_field((string) $labels[$slug])
                    : ucwords(str_replace(['-', '_'], ' ', $slug));

                $tabs[$slug] = [
                    'slug' => $slug,
                    'title' => $title,
                    'content' => $content,
                ];
            }
        }

        if (!isset($tabs['description']) && !empty($addon['description']) && is_scalar($addon['description'])) {
            $tabs = ['description' => [
                'slug' => 'description',
                'title' => __('Description', 'multi-location-product-and-inventory-management-pro'),
                'content' => wp_kses_post((string) $addon['description']),
            ]] + $tabs;
        }

        $changelog = '';
        if (!empty($addon['changelog']) && is_scalar($addon['changelog'])) {
            $changelog = (string) $addon['changelog'];
        } elseif (!empty($addon['changeslog']) && is_scalar($addon['changeslog'])) {
            $changelog = (string) $addon['changeslog'];
        }

        if (!isset($tabs['changelog']) && trim($changelog) !== '') {
            $tabs['changelog'] = [
                'slug' => 'changelog',
                'title' => __('Changelog', 'multi-location-product-and-inventory-management-pro'),
                'content' => wp_kses_post($changelog),
            ];
        }

        $preferred_order = [
            'description' => 10,
            'installation' => 20,
            'changelog' => 30,
            'screenshots' => 40,
            'reviews' => 50,
        ];

        uasort($tabs, static function ($a, $b) use ($preferred_order) {
            $a_rank = $preferred_order[$a['slug']] ?? 1000;
            $b_rank = $preferred_order[$b['slug']] ?? 1000;

            return $a_rank <=> $b_rank;
        });

        return array_values($tabs);
    }

    private function get_addon_initials(string $name): string
    {
        $words = preg_split('/\s+/', trim(wp_strip_all_tags($name)));
        if (!is_array($words) || empty($words)) {
            return 'A';
        }

        $initials = '';
        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }

            $initials .= strtoupper(substr($word, 0, 1));
            if (strlen($initials) >= 2) {
                break;
            }
        }

        return $initials !== '' ? $initials : 'A';
    }

    private function format_last_check(int $timestamp): string
    {
        return sprintf(
            /* translators: %s: localized date and time. */
            __('Last checked: %s', 'multi-location-product-and-inventory-management-pro'),
            date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp)
        );
    }

    private function render_actions(array $addon, array $status, bool $license_valid)
    {
        if (!$status['installed']) {
            printf(
                '<a class="button button-primary %1$s" href="%2$s">%3$s</a>',
                !$license_valid || empty($addon['package']) ? 'disabled' : '',
                $license_valid && !empty($addon['package']) ? esc_url($this->action_url('install', $addon['slug'])) : '#',
                esc_html__('Install', 'multi-location-product-and-inventory-management-pro')
            );
            return;
        }

        if (!$status['active']) {
            printf(
                '<a class="button button-primary" href="%1$s">%2$s</a>',
                esc_url($this->action_url('activate', $addon['slug'])),
                esc_html__('Activate', 'multi-location-product-and-inventory-management-pro')
            );
        } else {
            printf(
                '<a class="button" href="%1$s">%2$s</a>',
                esc_url($this->action_url('deactivate', $addon['slug'])),
                esc_html__('Deactivate', 'multi-location-product-and-inventory-management-pro')
            );
        }

        if ($status['update_available'] && $license_valid && !empty($addon['package'])) {
            printf(
                '<a class="button" href="%1$s">%2$s</a>',
                esc_url($this->action_url('update', $addon['slug'])),
                esc_html__('Update', 'multi-location-product-and-inventory-management-pro')
            );
        }

        printf(
            '<a class="button" href="%1$s" onclick="return confirm(%2$s);">%3$s</a>',
            esc_url($this->action_url('delete', $addon['slug'])),
            esc_attr(wp_json_encode(__('Delete this add-on plugin?', 'multi-location-product-and-inventory-management-pro'))),
            esc_html__('Delete', 'multi-location-product-and-inventory-management-pro')
        );
    }

    public function handle_action()
    {
        if (!current_user_can('install_plugins')) {
            wp_die(esc_html__('You do not have permission to manage add-ons.', 'multi-location-product-and-inventory-management-pro'));
        }

        $action = isset($_GET['addon_action']) ? sanitize_key(wp_unslash($_GET['addon_action'])) : '';
        $slug = isset($_GET['addon']) ? sanitize_key(wp_unslash($_GET['addon'])) : '';

        check_admin_referer('mulopimfwc_addon_' . $action . '_' . $slug);

        try {
            $addons = $this->get_addons_with_manifest(in_array($action, ['refresh', 'install', 'update'], true));
            if (!isset($addons[$slug])) {
                throw new RuntimeException(__('Unknown add-on.', 'multi-location-product-and-inventory-management-pro'));
            }

            $addon = $addons[$slug];

            if (in_array($action, ['install', 'update'], true)) {
                if (!$this->has_valid_license()) {
                    throw new RuntimeException(__('A valid Plugincy license is required to download this add-on.', 'multi-location-product-and-inventory-management-pro'));
                }

                $this->install_or_update($addon);
                $message = $action === 'install'
                    ? __('Add-on installed successfully.', 'multi-location-product-and-inventory-management-pro')
                    : __('Add-on updated successfully.', 'multi-location-product-and-inventory-management-pro');
            } elseif ($action === 'activate') {
                $this->activate($addon);
                $message = __('Add-on activated successfully.', 'multi-location-product-and-inventory-management-pro');
            } elseif ($action === 'deactivate') {
                $this->deactivate($addon);
                $message = __('Add-on deactivated successfully.', 'multi-location-product-and-inventory-management-pro');
            } elseif ($action === 'delete') {
                $this->delete($addon);
                $message = __('Add-on deleted successfully.', 'multi-location-product-and-inventory-management-pro');
            } elseif ($action === 'refresh') {
                $message = __('Add-on metadata refreshed.', 'multi-location-product-and-inventory-management-pro');
            } else {
                throw new RuntimeException(__('Unsupported add-on action.', 'multi-location-product-and-inventory-management-pro'));
            }

            delete_option(self::LAST_ERROR_OPTION);
            set_transient(self::NOTICE_TRANSIENT, ['type' => 'success', 'message' => $message], 60);
            add_settings_error('mulopimfwc_addons', 'mulopimfwc_addon_success', $message, 'updated');
        } catch (Throwable $exception) {
            update_option(self::LAST_ERROR_OPTION, $exception->getMessage(), false);
            set_transient(self::NOTICE_TRANSIENT, ['type' => 'error', 'message' => $exception->getMessage()], 60);
            add_settings_error('mulopimfwc_addons', 'mulopimfwc_addon_error', $exception->getMessage(), 'error');
        }

        set_transient('settings_errors', get_settings_errors(), 30);
        wp_safe_redirect(admin_url('admin.php?page=mulopimfwc-addons'));
        exit;
    }

    private function action_url(string $action, string $slug): string
    {
        return wp_nonce_url(
            add_query_arg(
                [
                    'action' => 'mulopimfwc_addon_action',
                    'addon_action' => $action,
                    'addon' => $slug,
                ],
                admin_url('admin-post.php')
            ),
            'mulopimfwc_addon_' . $action . '_' . $slug
        );
    }

    private function get_addons_with_manifest(bool $force): array
    {
        $defaults = $this->get_default_addons();
        $manifest = $this->get_manifest($force);

        if (empty($manifest)) {
            return $this->has_valid_license() && !$force ? $defaults : [];
        }

        $addons = [];
        foreach ($manifest as $slug => $manifest_addon) {
            if (!is_array($manifest_addon)) {
                continue;
            }

            $slug = sanitize_key((string) $slug);
            if ($slug === '') {
                continue;
            }

            $base = $defaults[$slug] ?? $this->get_empty_addon($slug);
            $addons[$slug] = $this->merge_manifest_addon($base, $manifest_addon);
        }

        return $addons;
    }

    private function merge_manifest_addon(array $addon, array $manifest): array
    {
        foreach (['version', 'package', 'checksum', 'changelog', 'changeslog', 'plugin_file', 'requires_wp', 'requires_php', 'requires_plugin_version', 'banner_url', 'banner'] as $key) {
            if (array_key_exists($key, $manifest)) {
                $addon[$key] = $manifest[$key];
            }
        }

        foreach (['name', 'description', 'logo_url'] as $key) {
            if (isset($manifest[$key]) && is_scalar($manifest[$key]) && trim((string) $manifest[$key]) !== '') {
                $addon[$key] = $manifest[$key];
            }
        }

        foreach (['quick_links', 'tabs', 'sections', 'section_labels', 'banners'] as $key) {
            if (isset($manifest[$key]) && is_array($manifest[$key])) {
                $addon[$key] = $manifest[$key];
            }
        }

        $requires_label = $this->build_requires_label($manifest);

        if (isset($manifest['requires_label']) && is_scalar($manifest['requires_label']) && trim((string) $manifest['requires_label']) !== '') {
            $addon['requires'] = $manifest['requires_label'];
        } elseif (isset($manifest['requirements']) && is_array($manifest['requirements']) && !empty($manifest['requirements']['label']) && is_scalar($manifest['requirements']['label'])) {
            $addon['requires'] = (string) $manifest['requirements']['label'];
        } elseif ($requires_label !== '') {
            $addon['requires'] = $requires_label;
        } elseif (isset($manifest['requires']) && is_scalar($manifest['requires']) && trim((string) $manifest['requires']) !== '') {
            $addon['requires'] = (string) $manifest['requires'];
        }

        return $addon;
    }

    private function build_requires_label(array $manifest): string
    {
        $parts = [];
        $main_plugin = isset($manifest['requires_plugin_version']) && is_scalar($manifest['requires_plugin_version'])
            ? trim((string) $manifest['requires_plugin_version'])
            : '';
        $wp = isset($manifest['requires_wp']) && is_scalar($manifest['requires_wp'])
            ? trim((string) $manifest['requires_wp'])
            : '';
        $php = isset($manifest['requires_php']) && is_scalar($manifest['requires_php'])
            ? trim((string) $manifest['requires_php'])
            : '';

        if ($wp === '' && isset($manifest['requires']) && is_scalar($manifest['requires']) && preg_match('/^[0-9.]+$/', trim((string) $manifest['requires']))) {
            $wp = trim((string) $manifest['requires']);
        }

        if ($main_plugin !== '') {
            $parts[] = sprintf(
                /* translators: %s: main plugin version. */
                __('Multi Location Pro %s+', 'multi-location-product-and-inventory-management-pro'),
                $main_plugin
            );
        }

        if ($wp !== '') {
            $parts[] = sprintf(
                /* translators: %s: WordPress version. */
                __('WordPress %s+', 'multi-location-product-and-inventory-management-pro'),
                $wp
            );
        }

        if ($php !== '') {
            $parts[] = sprintf(
                /* translators: %s: PHP version. */
                __('PHP %s+', 'multi-location-product-and-inventory-management-pro'),
                $php
            );
        }

        return implode(', ', $parts);
    }

    private function get_empty_addon(string $slug): array
    {
        return [
            'slug' => sanitize_key($slug),
            'name' => ucwords(str_replace(['-', '_'], ' ', sanitize_key($slug))),
            'description' => '',
            'version' => '',
            'requires' => '',
            'requires_wp' => '',
            'requires_php' => '',
            'requires_plugin_version' => '',
            'plugin_file' => '',
            'package' => '',
            'checksum' => '',
            'changelog' => '',
            'changeslog' => '',
            'logo_url' => '',
            'banner_url' => '',
            'quick_links' => [],
            'tabs' => [],
            'sections' => [],
            'section_labels' => [],
            'banners' => [],
        ];
    }

    private function get_default_addons(): array
    {
        return apply_filters('mulopimfwc_available_addons', [
            'mulopimfwc-pos-connector' => [
                'slug' => 'mulopimfwc-pos-connector',
                'name' => __('Multi Location POS Connector', 'multi-location-product-and-inventory-management-pro'),
                'description' => __('Connect Multi Location stock and pricing with supported POS systems. OpenPOS is supported in v1.', 'multi-location-product-and-inventory-management-pro'),
                'version' => '1.0.0',
                'requires' => __('WooCommerce, OpenPOS, Multi Location Pro', 'multi-location-product-and-inventory-management-pro'),
                'plugin_file' => 'mulopimfwc-pos-connector/mulopimfwc-pos-connector.php',
                'package' => '',
                'checksum' => '',
                'changelog' => '',
                'logo_url' => '',
                'banner_url' => '',
                'quick_links' => [],
                'tabs' => [],
                'sections' => [],
                'section_labels' => [],
                'banners' => [],
            ],
        ]);
    }

    private function get_manifest(bool $force): array
    {
        if (!$this->has_valid_license()) {
            delete_transient(self::MANIFEST_TRANSIENT);
            return [];
        }

        if (!$force) {
            $cached = get_transient(self::MANIFEST_TRANSIENT);
            if (is_array($cached)) {
                return $cached;
            }
        }

        update_option(self::LAST_CHECK_OPTION, time(), false);

        $endpoint = apply_filters('mulopimfwc_addons_manifest_url', self::DEFAULT_MANIFEST_URL);
        $response = wp_remote_post($endpoint, [
            'timeout' => 30,
            'sslverify' => true,
            'headers' => [
                'Accept' => 'application/json',
            ],
            'body' => [
                'license' => get_option('mulopimfwc_license_key', ''),
                'site_url' => home_url(),
                'plugin_version' => defined('MULOPIMFWC_VERSION') ? MULOPIMFWC_VERSION : '',
                'product_slug' => self::MANIFEST_PRODUCT_SLUG,
                'wp_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
            ],
        ]);

        if (is_wp_error($response)) {
            update_option(self::LAST_ERROR_OPTION, $response->get_error_message(), false);
            return [];
        }

        $response_code = (int) wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $body = json_decode($response_body, true);
        if (!is_array($body)) {
            update_option(self::LAST_ERROR_OPTION, __('The Plugincy add-on manifest response was invalid.', 'multi-location-product-and-inventory-management-pro'), false);
            return [];
        }

        if ($response_code < 200 || $response_code >= 300) {
            $message = isset($body['message']) && is_scalar($body['message'])
                ? sanitize_text_field((string) $body['message'])
                : __('The Plugincy add-on manifest request failed.', 'multi-location-product-and-inventory-management-pro');
            update_option(self::LAST_ERROR_OPTION, $message, false);
            return [];
        }

        $server_license = isset($body['license']) && is_array($body['license']) ? $body['license'] : [];
        if (isset($body['success']) && !$body['success'] && !empty($server_license)) {
            $server_message = isset($server_license['message']) && is_scalar($server_license['message'])
                ? sanitize_text_field((string) $server_license['message'])
                : __('The Plugincy license server did not return signed add-on downloads for this license.', 'multi-location-product-and-inventory-management-pro');
            update_option(self::LAST_ERROR_OPTION, $server_message, false);
        }

        $addons = isset($body['addons']) && is_array($body['addons']) ? $body['addons'] : $body;
        $normalized = [];

        foreach ($addons as $slug => $data) {
            if (is_int($slug) && isset($data['slug'])) {
                $slug = sanitize_key((string) $data['slug']);
            } else {
                $slug = sanitize_key((string) $slug);
            }

            if (!is_array($data) || $slug === '') {
                continue;
            }

            $logo_url = $this->extract_manifest_image_url($data, ['logo_url', 'logo', 'icon'], 'icons', ['2x', '1x', 'default', 'svg']);
            $banner_url = $this->extract_manifest_image_url($data, ['banner_url', 'banner'], 'banners', ['high', 'low', 'default']);

            $requires_label = '';
            if (isset($data['requires_label']) && is_scalar($data['requires_label'])) {
                $requires_label = sanitize_text_field((string) $data['requires_label']);
            } elseif (isset($data['requirements']) && is_array($data['requirements']) && isset($data['requirements']['label']) && is_scalar($data['requirements']['label'])) {
                $requires_label = sanitize_text_field((string) $data['requirements']['label']);
            }

            $normalized[$slug] = [
                'slug' => $slug,
                'name' => isset($data['name']) && is_scalar($data['name']) ? sanitize_text_field((string) $data['name']) : '',
                'description' => isset($data['description']) && is_scalar($data['description']) ? wp_kses_post((string) $data['description']) : '',
                'plugin_file' => isset($data['plugin_file']) && is_scalar($data['plugin_file']) ? sanitize_text_field((string) $data['plugin_file']) : '',
                'version' => isset($data['version']) && is_scalar($data['version']) ? sanitize_text_field((string) $data['version']) : '1.0.0',
                'package' => isset($data['download_url']) && is_scalar($data['download_url']) ? esc_url_raw((string) $data['download_url']) : (isset($data['package']) && is_scalar($data['package']) ? esc_url_raw((string) $data['package']) : ''),
                'checksum' => isset($data['checksum']) && is_scalar($data['checksum']) ? sanitize_text_field((string) $data['checksum']) : '',
                'changelog' => isset($data['changelog']) && is_scalar($data['changelog']) ? wp_kses_post((string) $data['changelog']) : '',
                'changeslog' => isset($data['changeslog']) && is_scalar($data['changeslog']) ? wp_kses_post((string) $data['changeslog']) : '',
                'logo_url' => $logo_url,
                'banner_url' => $banner_url,
                'quick_links' => $this->normalize_manifest_quick_links($data['quick_links'] ?? []),
                'tabs' => $this->normalize_manifest_tabs($data['tabs'] ?? []),
                'sections' => $this->normalize_manifest_sections($data['sections'] ?? []),
                'section_labels' => $this->normalize_manifest_section_labels($data['section_labels'] ?? []),
                'banners' => isset($data['banners']) && is_array($data['banners']) ? $data['banners'] : [],
                'requires_label' => $requires_label,
                'requires' => isset($data['requires']) && is_scalar($data['requires']) ? sanitize_text_field((string) $data['requires']) : '',
                'requires_wp' => isset($data['requires_wp']) && is_scalar($data['requires_wp']) ? sanitize_text_field((string) $data['requires_wp']) : '',
                'requires_php' => isset($data['requires_php']) && is_scalar($data['requires_php']) ? sanitize_text_field((string) $data['requires_php']) : '',
                'requires_plugin_version' => isset($data['requires_plugin_version']) && is_scalar($data['requires_plugin_version']) ? sanitize_text_field((string) $data['requires_plugin_version']) : (isset($data['requires_plugin']) && is_scalar($data['requires_plugin']) ? sanitize_text_field((string) $data['requires_plugin']) : ''),
                'tested' => isset($data['tested']) && is_scalar($data['tested']) ? sanitize_text_field((string) $data['tested']) : '',
                'tested_wc' => isset($data['tested_wc']) && is_scalar($data['tested_wc']) ? sanitize_text_field((string) $data['tested_wc']) : '',
            ];
        }

        if (isset($body['success']) && $body['success']) {
            delete_option(self::LAST_ERROR_OPTION);
        }

        $cache_ttl = $this->get_manifest_cache_ttl($body, $normalized);
        if ($cache_ttl > 0) {
            set_transient(self::MANIFEST_TRANSIENT, $normalized, $cache_ttl);
        } else {
            delete_transient(self::MANIFEST_TRANSIENT);
        }

        return $normalized;
    }

    private function extract_manifest_image_url(array $data, array $scalar_keys, string $map_key, array $map_order): string
    {
        foreach ($scalar_keys as $key) {
            if (!empty($data[$key]) && is_scalar($data[$key])) {
                return esc_url_raw((string) $data[$key]);
            }
        }

        if (!empty($data[$map_key]) && is_array($data[$map_key])) {
            foreach ($map_order as $key) {
                if (!empty($data[$map_key][$key]) && is_scalar($data[$map_key][$key])) {
                    return esc_url_raw((string) $data[$map_key][$key]);
                }
            }
        }

        return '';
    }

    private function normalize_manifest_quick_links($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $links = [];
        foreach ($raw as $link) {
            if (!is_array($link)) {
                continue;
            }

            $url = isset($link['url']) && is_scalar($link['url']) ? esc_url_raw((string) $link['url']) : '';
            if ($url === '') {
                continue;
            }

            $label = isset($link['label']) && is_scalar($link['label']) && trim((string) $link['label']) !== ''
                ? sanitize_text_field((string) $link['label'])
                : $url;

            $links[] = [
                'label' => $label,
                'url' => $url,
            ];
        }

        return $links;
    }

    private function normalize_manifest_tabs($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $tabs = [];
        foreach ($raw as $tab) {
            if (!is_array($tab)) {
                continue;
            }

            $slug = isset($tab['slug']) && is_scalar($tab['slug']) ? sanitize_key((string) $tab['slug']) : '';
            $title = isset($tab['title']) && is_scalar($tab['title']) ? sanitize_text_field((string) $tab['title']) : '';
            $content = isset($tab['content']) && is_scalar($tab['content']) ? wp_kses_post((string) $tab['content']) : '';

            if ($slug === '' && $title !== '') {
                $slug = sanitize_key($title);
            }

            if ($title === '' && $slug !== '') {
                $title = ucwords(str_replace(['-', '_'], ' ', $slug));
            }

            if ($slug === '' || $title === '' || trim($content) === '') {
                continue;
            }

            $tabs[] = [
                'slug' => $slug,
                'title' => $title,
                'content' => $content,
            ];
        }

        return $tabs;
    }

    private function normalize_manifest_sections($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $sections = [];
        foreach ($raw as $slug => $content) {
            $slug = sanitize_key((string) $slug);
            $content = is_scalar($content) ? wp_kses_post((string) $content) : '';

            if ($slug === '' || trim($content) === '') {
                continue;
            }

            $sections[$slug] = $content;
        }

        return $sections;
    }

    private function normalize_manifest_section_labels($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $labels = [];
        foreach ($raw as $slug => $label) {
            $slug = sanitize_key((string) $slug);
            if ($slug === '' || !is_scalar($label) || trim((string) $label) === '') {
                continue;
            }

            $labels[$slug] = sanitize_text_field((string) $label);
        }

        return $labels;
    }

    private function get_manifest_cache_ttl(array $body, array $addons): int
    {
        $has_package = false;
        foreach ($addons as $addon) {
            if (!empty($addon['package'])) {
                $has_package = true;
                break;
            }
        }

        if (!$has_package) {
            return 6 * HOUR_IN_SECONDS;
        }

        $token_ttl = isset($body['token_ttl']) ? absint($body['token_ttl']) : 0;
        if ($token_ttl <= 0) {
            return 2 * MINUTE_IN_SECONDS;
        }

        return max(30, min(5 * MINUTE_IN_SECONDS, $token_ttl - 30));
    }

    private function get_addon_status(array $addon): array
    {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $plugin_file = $this->resolve_plugin_file($addon);
        $installed = $plugin_file !== '';
        $active = $installed && is_plugin_active($plugin_file);
        $installed_version = '';

        if ($installed) {
            $plugins = get_plugins();
            $installed_version = isset($plugins[$plugin_file]['Version']) ? (string) $plugins[$plugin_file]['Version'] : '';
        }

        $update_available = $installed && $installed_version !== '' && version_compare($installed_version, (string) $addon['version'], '<');

        if ($update_available) {
            $state = 'update';
            $label = __('Update available', 'multi-location-product-and-inventory-management-pro');
        } elseif ($active) {
            $state = 'active';
            $label = __('Active', 'multi-location-product-and-inventory-management-pro');
        } elseif ($installed) {
            $state = 'inactive';
            $label = __('Installed', 'multi-location-product-and-inventory-management-pro');
        } else {
            $state = 'missing';
            $label = __('Not installed', 'multi-location-product-and-inventory-management-pro');
        }

        return [
            'installed' => $installed,
            'active' => $active,
            'installed_version' => $installed_version,
            'update_available' => $update_available,
            'state' => $state,
            'label' => $label,
            'plugin_file' => $plugin_file,
        ];
    }

    private function get_addon_statuses(array $addons): array
    {
        $statuses = [];
        $stored_statuses = [];
        $checked_at = time();

        foreach ($addons as $slug => $addon) {
            $status_slug = isset($addon['slug']) ? sanitize_key((string) $addon['slug']) : sanitize_key((string) $slug);
            $status = $this->get_addon_status($addon);
            $statuses[$status_slug] = $status;
            $stored_statuses[$status_slug] = [
                'installed' => (bool) $status['installed'],
                'active' => (bool) $status['active'],
                'installed_version' => (string) $status['installed_version'],
                'update_available' => (bool) $status['update_available'],
                'state' => (string) $status['state'],
                'plugin_file' => (string) $status['plugin_file'],
                'checked_at' => $checked_at,
            ];
        }

        update_option(self::INSTALLED_STATUS_OPTION, $stored_statuses, false);

        return $statuses;
    }

    private function resolve_plugin_file(array $addon): string
    {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $plugins = get_plugins();
        $expected_file = isset($addon['plugin_file']) && is_scalar($addon['plugin_file'])
            ? wp_normalize_path((string) $addon['plugin_file'])
            : '';
        $addon_slug = isset($addon['slug']) && is_scalar($addon['slug'])
            ? sanitize_key((string) $addon['slug'])
            : '';

        if ($expected_file !== '' && isset($plugins[$expected_file])) {
            return $expected_file;
        }

        $expected_basename = $expected_file !== '' ? basename($expected_file) : '';
        $expected_name = isset($addon['name']) && is_scalar($addon['name'])
            ? strtolower(trim((string) $addon['name']))
            : '';

        foreach ($plugins as $plugin_file => $data) {
            $plugin_file = wp_normalize_path((string) $plugin_file);
            $plugin_dir = sanitize_key(basename(dirname($plugin_file)));

            if ($addon_slug !== '' && ($plugin_dir === $addon_slug || strpos($plugin_dir, $addon_slug . '-') === 0)) {
                return $plugin_file;
            }

            if ($expected_basename !== '' && basename($plugin_file) === $expected_basename) {
                return $plugin_file;
            }

            $text_domain = isset($data['TextDomain']) ? sanitize_key((string) $data['TextDomain']) : '';
            if ($addon_slug !== '' && $text_domain === $addon_slug) {
                return $plugin_file;
            }

            $plugin_name = isset($data['Name']) ? strtolower(trim((string) $data['Name'])) : '';
            if ($expected_name !== '' && $plugin_name === $expected_name) {
                return $plugin_file;
            }
        }

        return '';
    }

    private function install_or_update(array $addon): void
    {
        if (empty($addon['package'])) {
            throw new RuntimeException(__('No signed download URL was returned for this add-on.', 'multi-location-product-and-inventory-management-pro'));
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        $package = download_url($addon['package'], 30);
        if (is_wp_error($package)) {
            throw new RuntimeException($package->get_error_message());
        }

        try {
            if (!empty($addon['checksum'])) {
                $actual = hash_file('sha256', $package);
                if (!hash_equals(strtolower((string) $addon['checksum']), strtolower($actual))) {
                    throw new RuntimeException(__('The downloaded add-on package failed checksum verification.', 'multi-location-product-and-inventory-management-pro'));
                }
            }

            $upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
            $result = $upgrader->install($package, ['overwrite_package' => true]);

            if (is_wp_error($result)) {
                throw new RuntimeException($result->get_error_message());
            }

            if (!$result) {
                throw new RuntimeException(__('WordPress could not install the add-on package.', 'multi-location-product-and-inventory-management-pro'));
            }

            wp_clean_plugins_cache(true);
            $this->persist_addon_status($addon);
            delete_transient(self::MANIFEST_TRANSIENT);
        } finally {
            if (is_string($package) && file_exists($package)) {
                @unlink($package);
            }
        }
    }

    private function activate(array $addon): void
    {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $plugin_file = $this->resolve_plugin_file($addon);
        if ($plugin_file === '') {
            throw new RuntimeException(__('The add-on is not installed.', 'multi-location-product-and-inventory-management-pro'));
        }

        $result = activate_plugin($plugin_file, '', false, true);
        if (is_wp_error($result)) {
            throw new RuntimeException($result->get_error_message());
        }

        wp_clean_plugins_cache(true);
        $this->persist_addon_status($addon);
    }

    private function deactivate(array $addon): void
    {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $plugin_file = $this->resolve_plugin_file($addon);
        if ($plugin_file !== '') {
            deactivate_plugins($plugin_file);
            wp_clean_plugins_cache(true);
            $this->persist_addon_status($addon);
        }
    }

    private function delete(array $addon): void
    {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';

        $plugin_file = $this->resolve_plugin_file($addon);
        if ($plugin_file === '') {
            return;
        }

        if (is_plugin_active($plugin_file)) {
            deactivate_plugins($plugin_file);
        }

        $result = delete_plugins([$plugin_file]);
        if (is_wp_error($result)) {
            throw new RuntimeException($result->get_error_message());
        }

        wp_clean_plugins_cache(true);
        $this->persist_addon_status($addon);
    }

    private function persist_addon_status(array $addon): void
    {
        $slug = isset($addon['slug']) ? sanitize_key((string) $addon['slug']) : '';
        if ($slug === '') {
            return;
        }

        $statuses = get_option(self::INSTALLED_STATUS_OPTION, []);
        if (!is_array($statuses)) {
            $statuses = [];
        }

        $status = $this->get_addon_status($addon);
        $statuses[$slug] = [
            'installed' => (bool) $status['installed'],
            'active' => (bool) $status['active'],
            'installed_version' => (string) $status['installed_version'],
            'update_available' => (bool) $status['update_available'],
            'state' => (string) $status['state'],
            'plugin_file' => (string) $status['plugin_file'],
            'checked_at' => time(),
        ];

        update_option(self::INSTALLED_STATUS_OPTION, $statuses, false);
    }

    private function has_valid_license(): bool
    {
        return function_exists('mulopimfwc_is_license_valid') && mulopimfwc_is_license_valid();
    }
}

MULOPIMFWC_Addons_Page::instance();
