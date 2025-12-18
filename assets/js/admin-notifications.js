 (function ($) {
    'use strict';

    const config = window.mulopimfwcNotificationConfig || {};
    if (!config.ajaxurl) {
        return;
    }

    const alertHistory = new Set();
    const floatContainer = $('<div id="mulopimfwc-floating-notifications"></div>').appendTo('body');

    injectStyles();
    updateFloatingPosition();

    $(document).ready(function () {
        document.addEventListener('mulopimfwcRealtimeData', function (event) {
            handleRealtimeData(event.detail);
        });

        if (config.pwa_enabled === 'on' && 'serviceWorker' in navigator) {
            registerServiceWorker();
        }

        if (config.pwa_enabled === 'on' && 'Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission().catch(() => { });
        }

        if (config.realtime_enabled !== 'off' && !isDashboardView()) {
            startPolling();
        }
    });

    function isDashboardView() {
        return $('.lwp-dashboard').length > 0;
    }

    function startPolling() {
        fetchNotifications();
        const interval = parseInt(config.poll_interval, 10) || 30000;
        if (window.mulopimfwcNotificationInterval) {
            clearInterval(window.mulopimfwcNotificationInterval);
        }
        window.mulopimfwcNotificationInterval = setInterval(fetchNotifications, interval);
    }

    function fetchNotifications() {
        $.post(config.ajaxurl, {
            action: 'mulopimfwc_dashboard_live_data',
            nonce: config.nonce
        })
            .done(function (response) {
                if (response.success && response.data) {
                    handleRealtimeData(response.data);
                }
            });
    }

    function handleRealtimeData(data) {
        if (!data || !Array.isArray(data.alerts) || data.alerts.length === 0) {
            return;
        }

        data.alerts.forEach(function (alert) {
            if (config.realtime_enabled === 'off') {
                return;
            }
            if (!alert.id || alertHistory.has(alert.id)) {
                return;
            }
            alertHistory.add(alert.id);
            const message = formatAlertMessage(alert);

            if (config.floating_enabled !== 'off') {
                showFloatingNotification(alert, message);
            }

            if (config.show_admin_notice !== 'off') {
                renderAdminNotice(alert, message);
            }

            if (config.pwa_enabled === 'on') {
                sendPushNotification(alert, message);
            }
        });
    }

    function formatAlertMessage(alert) {
        const template = config.notification_template || '[{event}] {message}';
        return template
            .replace('{event}', alert.label || '')
            .replace('{message}', alert.message || '')
            .replace('{count}', alert.data && alert.data.count ? alert.data.count : '')
            .replace('{status}', alert.data && alert.data.status ? alert.data.status : '');
    }

    function showFloatingNotification(alert, message) {
        const item = $(
            '<div class="mulopimfwc-floating-notification" role="status">' +
            '<div class="mulopimfwc-notification-title">' + escapeHtml(alert.label) + '</div>' +
            '<div class="mulopimfwc-notification-body">' + escapeHtml(message) + '</div>' +
            '</div>'
        );

        floatContainer.append(item);
        const duration = parseInt(config.floating_duration, 10) || 6000;
        setTimeout(function () {
            item.fadeOut(200, function () {
                item.remove();
            });
        }, duration);
    }

    function updateFloatingPosition() {
        const position = config.floating_position || 'top-right';
        floatContainer
            .removeClass()
            .addClass('mulopimfwc-floating-wrapper mulopimfwc-floating-' + position);
    }

    function renderAdminNotice(alert, message) {
        const stack = $('#mulopimfwc-admin-notice-stack');
        const container = stack.length ? stack : $('<div id="mulopimfwc-admin-notice-stack"></div>').prependTo('#wpbody-content');
        const notice = $(
            '<div class="notice notice-info is-dismissible mulopimfwc-admin-notice">' +
            '<p>' + escapeHtml(message) + '</p>' +
            '</div>'
        );
        container.prepend(notice);
        notice.on('click', '.notice-dismiss', function () {
            notice.remove();
        });
        setTimeout(function () {
            notice.fadeOut(200, function () {
                notice.remove();
            });
        }, (parseInt(config.floating_duration, 10) || 6000) * 0.8);
    }

    function registerServiceWorker() {
        navigator.serviceWorker.register(config.pwa_sw_url || '')
            .then(function (registration) {
                window.mulopimfwcNotificationSW = registration;
            })
            .catch(function () {
                // quiet fail
            });
    }

    function sendPushNotification(alert, message) {
        if (!('Notification' in window) || Notification.permission !== 'granted') {
            return;
        }

        const payload = {
            action: 'notify',
            title: alert.label || alert.type,
            body: message,
            icon: config.pwa_icon || ''
        };

        if (window.mulopimfwcNotificationSW && window.mulopimfwcNotificationSW.showNotification) {
            window.mulopimfwcNotificationSW.showNotification(payload.title, {
                body: payload.body,
                icon: payload.icon
            });
            return;
        }

        new Notification(payload.title, {
            body: payload.body,
            icon: payload.icon
        });
    }

    function injectStyles() {
        const styles = `
            #mulopimfwc-floating-notifications {
                position: fixed;
                pointer-events: none;
                z-index: 99999;
            }
            #mulopimfwc-floating-notifications.mulopimfwc-floating-top-right {
                top: 12px;
                right: 12px;
            }
            #mulopimfwc-floating-notifications.mulopimfwc-floating-bottom-right {
                bottom: 12px;
                right: 12px;
            }
            #mulopimfwc-floating-notifications.mulopimfwc-floating-top-left {
                top: 12px;
                left: 12px;
            }
            #mulopimfwc-floating-notifications.mulopimfwc-floating-bottom-left {
                bottom: 12px;
                left: 12px;
            }
            .mulopimfwc-floating-notification {
                background: #1f2937;
                color: #fff;
                padding: 12px 16px;
                border-radius: 8px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
                margin-bottom: 12px;
                opacity: 0.95;
                pointer-events: auto;
                transition: transform 0.3s ease, opacity 0.3s ease;
            }
            .mulopimfwc-notice-stack .mulopimfwc-admin-notice {
                margin-bottom: 10px;
            }
        `;
        if ($('#mulopimfwc-notification-styles').length) {
            return;
        }
        $('<style id="mulopimfwc-notification-styles"></style>').text(styles).appendTo('head');
    }

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return (text || '').replace(/[&<>"']/g, function (m) { return map[m]; });
    }

})(jQuery);
