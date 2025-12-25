(function ($) {
    'use strict';

    const config = window.mulopimfwcNotificationConfig || {};
    if (!config.ajaxurl) {
        return;
    }


    const alertHistory = new Set();
    const floatContainer = $('<div id="mulopimfwc-floating-notifications"></div>').appendTo('body');
    let serviceWorkerRegistration = null;
    let allNotifications = []; // Store all notifications for admin bar dropdown

    // Storage keys for read/unread notifications
    const STORAGE_READ_KEY = 'mulopimfwc_read_notifications';
    const STORAGE_SEEN_KEY = 'mulopimfwc_seen_notifications';
    const STORAGE_CLEARED_KEY = 'mulopimfwc_cleared_notifications';

    // Get read notifications from localStorage
    function getReadNotifications() {
        try {
            const stored = localStorage.getItem(STORAGE_READ_KEY);
            return stored ? JSON.parse(stored) : [];
        } catch (e) {
            return [];
        }
    }

    // Save read notifications to localStorage
    function saveReadNotifications(ids) {
        try {
            localStorage.setItem(STORAGE_READ_KEY, JSON.stringify(ids));
        } catch (e) {
            console.warn('Failed to save read notifications:', e);
        }
    }

    // Get seen notifications (for floating notifications)
    function getSeenNotifications() {
        try {
            const stored = localStorage.getItem(STORAGE_SEEN_KEY);
            return stored ? JSON.parse(stored) : [];
        } catch (e) {
            return [];
        }
    }

    // Save seen notifications
    function saveSeenNotifications(ids) {
        try {
            localStorage.setItem(STORAGE_SEEN_KEY, JSON.stringify(ids));
        } catch (e) {
            console.warn('Failed to save seen notifications:', e);
        }
    }

    // Mark notification as read
    function markAsRead(alertId) {
        const read = getReadNotifications();
        if (!read.includes(alertId)) {
            read.push(alertId);
            saveReadNotifications(read);
        }
    }

    // Mark notification as unread
    function markAsUnread(alertId) {
        const read = getReadNotifications();
        const index = read.indexOf(alertId);
        if (index > -1) {
            read.splice(index, 1);
            saveReadNotifications(read);
        }
    }

    // Mark notification as seen (for floating)
    function markAsSeen(alertId) {
        const seen = getSeenNotifications();
        if (!seen.includes(alertId)) {
            seen.push(alertId);
            saveSeenNotifications(seen);
        }
    }

    // Mark all as read
    function markAllAsRead(alerts) {
        const read = getReadNotifications();
        alerts.forEach(function (alert) {
            if (alert.id && !read.includes(alert.id)) {
                read.push(alert.id);
            }
        });
        saveReadNotifications(read);
    }

    // Clear all notifications
    function clearAllNotifications(alerts) {
        // Mark all current alerts as cleared so they won't show again
        if (alerts && alerts.length > 0) {
            const cleared = getClearedNotifications();
            const seen = getSeenNotifications();
            const read = getReadNotifications();

            alerts.forEach(function (alert) {
                if (alert.id) {
                    // Add to cleared list
                    if (!cleared.includes(alert.id)) {
                        cleared.push(alert.id);
                    }
                    // Also mark as seen/read
                    if (!seen.includes(alert.id)) {
                        seen.push(alert.id);
                    }
                    if (!read.includes(alert.id)) {
                        read.push(alert.id);
                    }
                }
            });

            saveClearedNotifications(cleared);
            saveSeenNotifications(seen);
            saveReadNotifications(read);
        } else {
            // If no alerts provided, clear everything
            saveClearedNotifications([]);
            saveReadNotifications([]);
            saveSeenNotifications([]);
        }

        // Clear the in-memory history
        alertHistory.clear();

        // Clear the allNotifications array
        allNotifications = [];
    }

    // Check if notification is read
    function isRead(alertId) {
        return getReadNotifications().includes(alertId);
    }

    // Check if notification is seen (for floating)
    function isSeen(alertId) {
        return getSeenNotifications().includes(alertId);
    }

    // Get cleared notifications
    function getClearedNotifications() {
        try {
            const stored = localStorage.getItem(STORAGE_CLEARED_KEY);
            return stored ? JSON.parse(stored) : [];
        } catch (e) {
            return [];
        }
    }

    // Save cleared notifications
    function saveClearedNotifications(ids) {
        try {
            localStorage.setItem(STORAGE_CLEARED_KEY, JSON.stringify(ids));
        } catch (e) {
            console.warn('Failed to save cleared notifications:', e);
        }
    }

    // Check if notification is cleared
    function isCleared(alertId) {
        return getClearedNotifications().includes(alertId);
    }

    async function initializePWA() {
        console.log('Initializing PWA...');

        if (!('serviceWorker' in navigator)) {
            console.warn('Service Workers not supported');
            return;
        }

        // Register service worker first (required for PWA)
        const registration = await registerServiceWorker();
        if (!registration) {
            console.error('Failed to register service worker');
            return;
        }

    }

    injectStyles();
    updateFloatingPosition();

    $(document).ready(function () {
        document.addEventListener('mulopimfwcRealtimeData', function (event) {
            handleRealtimeData(event.detail);
        });

        if ($('#mulopimfwc-pwa-status-styles').length === 0) {
            $('<style id="mulopimfwc-pwa-status-styles"></style>').text(`
        .mulopimfwc-pwa-status {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-left: 4px solid #2271b1;
            padding: 12px;
            margin: 20px 0;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .mulopimfwc-pwa-status.active {
            border-left-color: #00a32a;
        }
        .mulopimfwc-pwa-status.blocked {
            border-left-color: #d63638;
        }
        .mulopimfwc-pwa-status-content {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .mulopimfwc-pwa-icon {
            font-size: 20px;
        }
        .mulopimfwc-pwa-text {
            flex: 1;
        }
        .mulopimfwc-update-banner {
            position: fixed;
            top: 32px;
            right: 20px;
            background: #2271b1;
            color: #fff;
            padding: 12px 20px;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            z-index: 999999;
        }
        .mulopimfwc-update-content {
            display: flex;
            align-items: center;
            gap: 15px;
        }
    `).appendTo('head');
        }

        // Initialize PWA if enabled
        if (config.pwa_enabled === 'on') {
            initializePWA().then(() => {
                console.log('PWA initialization completed');
            }).catch(err => {
                console.error('PWA initialization error:', err);
            });
        }

        // Handle test notification button
        $('#test-push-notification').on('click', async function () {
            const $btn = $(this);
            const originalText = $btn.text();
            $btn.prop('disabled', true).text('Setting up...');

            try {
                // Ensure service worker is registered
                if (!serviceWorkerRegistration) {
                    serviceWorkerRegistration = await registerServiceWorker();
                    if (!serviceWorkerRegistration) {
                        alert('❌ Failed to register service worker. Please check:\n\n' +
                            '1. Your site is using HTTPS (required for service workers)\n' +
                            '2. Try refreshing the page');
                        $btn.prop('disabled', false).text(originalText);
                        return;
                    }
                }

                // Request permission if needed
                if (Notification.permission === 'default') {
                    $btn.text('Requesting permission...');
                    const permission = await Notification.requestPermission();
                    if (permission !== 'granted') {
                        alert('❌ Notification permission denied. Please allow notifications in your browser settings to receive alerts.');
                        $btn.prop('disabled', false).text(originalText);
                        return;
                    }
                } else if (Notification.permission === 'denied') {
                    alert('❌ Notifications are blocked. Please enable them in your browser settings:\n\n' +
                        'Chrome/Edge: Click the lock icon → Site settings → Notifications → Allow\n' +
                        'Firefox: Options → Privacy & Security → Permissions → Notifications → Settings');
                    $btn.prop('disabled', false).text(originalText);
                    return;
                }

                // Show local notification via service worker
                $btn.text('Sending test...');
                
                if (serviceWorkerRegistration && Notification.permission === 'granted') {
                    try {
                        await navigator.serviceWorker.ready;
                        
                        let iconUrl = config.pwa_icon || window.location.origin + '/favicon.ico';
                        let badgeUrl = config.pwa_badge || config.pwa_icon || window.location.origin + '/favicon.ico';
                        
                        if (iconUrl && !iconUrl.startsWith('http')) {
                            iconUrl = window.location.origin + (iconUrl.startsWith('/') ? '' : '/') + iconUrl;
                        }
                        if (badgeUrl && !badgeUrl.startsWith('http')) {
                            badgeUrl = window.location.origin + (badgeUrl.startsWith('/') ? '' : '/') + badgeUrl;
                        }
                        
                        await serviceWorkerRegistration.showNotification('Test Notification', {
                            body: 'This is a test notification from Multi Location Product & Inventory Management.',
                            icon: iconUrl,
                            badge: badgeUrl,
                            tag: 'test-notification-' + Date.now(),
                            data: {
                                url: window.location.href
                            }
                        });
                        
                        alert('✓ Test notification sent! Check your desktop notification area.');
                    } catch (err) {
                        alert('❌ Failed to show notification. Check browser console for errors.');
                    }
                } else {
                    alert('❌ Service worker not ready or notification permission not granted.');
                }
            } catch (error) {
                alert('❌ Error: ' + (error.message || 'Unknown error occurred.'));
            } finally {
                $btn.prop('disabled', false).text(originalText);
            }
        });

        if (config.realtime_enabled !== 'off' && !isDashboardView()) {
            startPolling();
        }

        // Add manifest link if PWA is enabled
        if (config.pwa_enabled === 'on') {
            // Use dynamic manifest endpoint
            const manifestUrl = window.location.origin + '/mulopimfwc-manifest.json';
            addManifestLink(manifestUrl);
        }

        // Initialize admin bar notifications
        initAdminBarNotifications();

        // Fetch notifications for admin bar on load
        if ($('#wp-admin-bar-mulopimfwc-notifications').length > 0) {
            fetchNotificationsForAdminBar();
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
            updateAdminBarNotifications([]);
            return;
        }

        // Filter out cleared notifications
        const activeAlerts = data.alerts.filter(function (alert) {
            return alert.id && !isCleared(alert.id);
        });

        // Store filtered notifications for admin bar
        allNotifications = activeAlerts;
        updateAdminBarNotifications(activeAlerts);

        activeAlerts.forEach(function (alert) {
            if (config.realtime_enabled === 'off') {
                return;
            }
            if (!alert.id || alertHistory.has(alert.id)) {
                return;
            }

            // Only show floating notification if it's NEW (not seen before)
            const isNewNotification = !isSeen(alert.id);

            if (isNewNotification) {
                alertHistory.add(alert.id);
                markAsSeen(alert.id); // Mark as seen so it won't show again on reload
                const message = formatAlertMessage(alert);

                if (config.floating_enabled !== 'off') {
                    showFloatingNotification(alert, message);
                }

                if (config.pwa_enabled === 'on') {
                    sendPushNotification(alert, message);
                }
            }
        });
    }

    // Strip HTML tags and decode HTML entities for plain text notifications
    function stripHtmlTags(text) {
        if (!text) return '';
        
        // Use textarea element for proper HTML entity decoding
        const textarea = document.createElement('textarea');
        textarea.innerHTML = text;
        
        // Get the decoded text (textarea.value decodes HTML entities properly)
        let plainText = textarea.value;
        
        // If textarea didn't decode (shouldn't happen, but fallback), try div method
        if (plainText.indexOf('&') !== -1 && plainText.indexOf('&#') !== -1) {
            const tmp = document.createElement('div');
            tmp.innerHTML = text;
            plainText = tmp.textContent || tmp.innerText || plainText;
        }
        
        // Clean up whitespace (replace multiple spaces, newlines, tabs with single space)
        plainText = plainText.replace(/\s+/g, ' ').trim();
        return plainText;
    }

    function formatAlertMessage(alert) {
        const template = config.notification_template || '[{event}] {message}';
        // Strip HTML from all parts
        const event = stripHtmlTags(alert.label || '');
        const message = stripHtmlTags(alert.message || '');
        const count = alert.data && alert.data.count ? String(alert.data.count) : '';
        const status = alert.data && alert.data.status ? stripHtmlTags(String(alert.data.status)) : '';
        
        return template
            .replace('{event}', event)
            .replace('{message}', message)
            .replace('{count}', count)
            .replace('{status}', status);
    }

    function showFloatingNotification(alert, message) {
        const severity = alert.severity || 'info';
        const url = getNotificationUrl(alert);
        const size = config.floating_size || 'comfy';

        // Create the notification element
        const item = $('<div>', {
            'class': 'mulopimfwc-floating-notification severity-' + severity + ' size-' + size,
            'role': 'status',
            'data-alert-id': alert.id || ''
        });

        // Add close button
        $('<button>', {
            'class': 'mulopimfwc-notification-close',
            'aria-label': 'Close',
            'html': '&times;'
        }).appendTo(item);

        // Add content container
        const content = $('<div>', {
            'class': 'mulopimfwc-notification-content'
        });

        // Add title
        $('<div>', {
            'class': 'mulopimfwc-notification-title',
            'text': alert.label || 'Notification'
        }).appendTo(content);

        // Add message body (allow HTML)
        $('<div>', {
            'class': 'mulopimfwc-notification-body',
            'html': message
        }).appendTo(content);

        content.appendTo(item);

        floatContainer.append(item);

        // Trigger animation
        setTimeout(function () {
            item.addClass('show');
        }, 10);

        // Close button handler
        item.find('.mulopimfwc-notification-close').on('click', function (e) {
            e.stopPropagation();
            item.removeClass('show');
            setTimeout(function () {
                item.remove();
            }, 300);
        });

        // Click handler for notification content (not close button)
        item.find('.mulopimfwc-notification-content').on('click', function () {
            if (url) {
                window.location.href = url;
            }
        });

        // Don't auto-close if user is hovering
        let hoverTimeout;
        let autoCloseTimeout;

        const duration = parseInt(config.floating_duration, 10) || 6000;

        function scheduleAutoClose() {
            clearTimeout(autoCloseTimeout);
            autoCloseTimeout = setTimeout(function () {
                if (!item.is(':hover')) {
                    item.removeClass('show');
                    setTimeout(function () {
                        item.remove();
                    }, 300);
                } else {
                    // If still hovering, check again
                    scheduleAutoClose();
                }
            }, duration);
        }

        item.on('mouseenter', function () {
            clearTimeout(autoCloseTimeout);
        });

        item.on('mouseleave', function () {
            scheduleAutoClose();
        });

        // Start auto-close timer
        scheduleAutoClose();
    }

    function getNotificationUrl(alert) {
        if (alert.data && alert.data.url) {
            return alert.data.url;
        }

        const adminurl = config.adminurl || '';

        // Default URLs based on alert type
        if (alert.type === 'new_order') {
            return adminurl + 'edit.php?post_type=shop_order';
        } else if (alert.type === 'low_stock' || alert.type === 'out_of_stock') {
            if (alert.data && alert.data.product_id) {
                return adminurl + 'post.php?post=' + alert.data.product_id + '&action=edit';
            }
            return adminurl + 'edit.php?post_type=product';
        }

        return null;
    }

    function updateFloatingPosition() {
        const position = config.floating_position || 'top-right';
        floatContainer
            .removeClass()
            .addClass('mulopimfwc-floating-wrapper mulopimfwc-floating-' + position);
    }

    async function registerServiceWorker() {
        if (!('serviceWorker' in navigator)) {
            return null;
        }

        try {
            const swUrl = config.pwa_sw_url || window.location.origin + '/mulopimfwc-sw.js';
            const swUrlFallback = config.pwa_sw_url_rest || config.pwa_sw_url_fallback || window.location.origin + '/wp-json/mulopimfwc/v1/sw.js';

            const existingRegistrations = await navigator.serviceWorker.getRegistrations();
            for (let registration of existingRegistrations) {
                if (registration.scope.includes('mulopimfwc')) {
                    await registration.unregister();
                }
            }

            let registration = null;
            
            try {
                registration = await navigator.serviceWorker.register(swUrl, {
                    scope: '/'
                });
            } catch (rewriteError) {
                try {
                    registration = await navigator.serviceWorker.register(swUrlFallback, {
                        scope: '/'
                    });
                } catch (fallbackError) {
                    throw new Error('Failed to register service worker: ' + fallbackError.message);
                }
            }

            if (!registration) {
                throw new Error('Service worker registration returned null');
            }

            serviceWorkerRegistration = registration;
            window.mulopimfwcNotificationSW = registration;

            await new Promise(resolve => setTimeout(resolve, 100));
            
            if (!navigator.serviceWorker.controller) {
                try {
                    await Promise.race([
                        navigator.serviceWorker.ready,
                        new Promise((_, reject) => setTimeout(() => reject(new Error('Timeout')), 10000))
                    ]);
                } catch (timeoutError) {
                    // Continue anyway
                }
            }
            
            return registration;
        } catch (error) {
            return null;
        }
    }



    function addManifestLink(manifestUrl) {
        // Remove existing manifest links
        $('link[rel="manifest"]').remove();
        
        // Add new manifest link
        $('<link>')
            .attr('rel', 'manifest')
            .attr('href', manifestUrl)
            .appendTo('head');
        
        console.log('Manifest link added:', manifestUrl);
    }

    async function sendPushNotification(alert, message) {
        if (!('Notification' in window)) {
            return;
        }

        if (Notification.permission === 'denied') {
            return;
        }

        if (Notification.permission === 'default') {
            try {
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    return;
                }
            } catch (error) {
                return;
            }
        }

        await showNotification(alert, message);
    }

    async function showNotification(alert, message) {
        // Ensure absolute URLs for icons with proper fallbacks
        let iconUrl = config.pwa_icon || window.location.origin + '/favicon.ico';
        let badgeUrl = config.pwa_badge || config.pwa_icon || window.location.origin + '/favicon.ico';
        
        // Make URLs absolute
        const icon = iconUrl.startsWith('http') ? iconUrl : (window.location.origin + (iconUrl.startsWith('/') ? '' : '/') + iconUrl);
        const badge = badgeUrl.startsWith('http') ? badgeUrl : (window.location.origin + (badgeUrl.startsWith('/') ? '' : '/') + badgeUrl);
        
        // Strip HTML from title as well (notifications don't support HTML)
        const title = stripHtmlTags(alert.label || alert.type || 'Notification');
        
        const payload = {
            title: title,
            body: message,
            icon: icon,
            badge: badge,
            tag: alert.id || 'mulopimfwc-notification-' + Date.now(),
            requireInteraction: alert.severity === 'critical',
            data: {
                url: alert.data?.url || window.location.origin + '/wp-admin/admin.php?page=multi-location-product-and-inventory-management',
                type: alert.type,
                alertId: alert.id
            },
            vibrate: alert.severity === 'critical' ? [200, 100, 200, 100, 200] : [200, 100, 200],
            timestamp: Date.now()
        };

        // Play sound if enabled
        if (config.sound_enabled === 'on') {
            playNotificationSound(alert.severity);
        }

        // Prefer service worker notifications (works even without push subscription)
        try {
            // Wait for service worker to be ready if not already
            if (serviceWorkerRegistration) {
                // Ensure service worker is ready
                await navigator.serviceWorker.ready;
                
                if (serviceWorkerRegistration.showNotification) {
                    await serviceWorkerRegistration.showNotification(payload.title, payload);
                    console.log('✓ Notification shown via service worker');
                    return;
                }
            }
            
            // Try to get service worker registration if we don't have it
            if ('serviceWorker' in navigator && !serviceWorkerRegistration) {
                try {
                    const registration = await navigator.serviceWorker.ready;
                    if (registration && registration.showNotification) {
                        await registration.showNotification(payload.title, payload);
                        console.log('✓ Notification shown via service worker (retrieved)');
                        serviceWorkerRegistration = registration;
                        return;
                    }
                } catch (swError) {
                    console.warn('Could not get service worker registration:', swError);
                }
            }
        } catch (error) {
            console.error('Error showing notification via service worker:', error);
        }
        
        // Fallback to regular notification API if service worker fails
        if ('Notification' in window && Notification.permission === 'granted') {
            showRegularNotification(payload);
        } else {
            console.warn('Cannot show notification: Notification permission not granted or Notification API not available');
        }
    }

    function showRegularNotification(payload) {
        const notification = new Notification(payload.title, payload);
        notification.onclick = function () {
            window.focus();
            if (payload.data && payload.data.url) {
                window.location.href = payload.data.url;
            }
            notification.close();
        };
    }

    function playNotificationSound(severity) {
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);

            // Different frequencies for different severities
            if (severity === 'critical') {
                oscillator.frequency.value = 800;
                gainNode.gain.value = 0.3;
                oscillator.start();
                oscillator.stop(audioContext.currentTime + 0.2);
            } else if (severity === 'warning') {
                oscillator.frequency.value = 600;
                gainNode.gain.value = 0.2;
                oscillator.start();
                oscillator.stop(audioContext.currentTime + 0.15);
            } else {
                oscillator.frequency.value = 400;
                gainNode.gain.value = 0.15;
                oscillator.start();
                oscillator.stop(audioContext.currentTime + 0.1);
            }
        } catch (e) {
            // Fallback: use HTML5 audio if available
            const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuBzvLZiTYIG2m98OSfThAMUKfj8LZjHAY4kdfyzHksBSR3x/DdkEAKFF606euoVRQKRp/g8r5sIQUrgc7y2Yk2CBtpvfDkn04QDFCn4/C2YxwGOJHX8sx5LAUkd8fw3ZBAC');
            audio.volume = 0.3;
            audio.play().catch(() => { });
        }
    }

    function injectStyles() {
        const style = config.notification_style || 'modern';
        const styles = {
            modern: `
                #mulopimfwc-floating-notifications {
                    position: fixed;
                    pointer-events: none;
                    z-index: 99999;
                    max-width: 400px;
                }
                #mulopimfwc-floating-notifications.mulopimfwc-floating-top-right {
                    top: 32px;
                    right: 20px;
                }
                #mulopimfwc-floating-notifications.mulopimfwc-floating-bottom-right {
                    bottom: 20px;
                    right: 20px;
                }
                #mulopimfwc-floating-notifications.mulopimfwc-floating-top-left {
                    top: 32px;
                    left: 20px;
                }
                #mulopimfwc-floating-notifications.mulopimfwc-floating-bottom-left {
                    bottom: 20px;
                    left: 20px;
                }
                .mulopimfwc-floating-notification {
                    background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
                    color: #fff;
                    border-radius: 12px;
                    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(255, 255, 255, 0.1);
                    margin-bottom: 12px;
                    opacity: 0;
                    transform: translateY(-10px);
                    pointer-events: auto;
                    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                    cursor: pointer;
                    position: relative;
                    overflow: hidden;
                }
                .mulopimfwc-floating-notification.size-comfy {
                    padding: 16px 20px;
                    max-width: 400px;
                }
                .mulopimfwc-floating-notification.size-compact {
                    padding: 12px 16px;
                    max-width: 320px;
                }
                .mulopimfwc-floating-notification::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    height: 3px;
                    background: linear-gradient(90deg, #3b82f6, #8b5cf6);
                }
                .mulopimfwc-floating-notification.show {
                    opacity: 1;
                    transform: translateY(0);
                }
                .mulopimfwc-floating-notification.severity-critical::before {
                    background: linear-gradient(90deg, #ef4444, #dc2626);
                }
                .mulopimfwc-floating-notification.severity-warning::before {
                    background: linear-gradient(90deg, #f59e0b, #d97706);
                }
                .mulopimfwc-notification-title {
                    font-weight: 600;
                    margin-bottom: 4px;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                .mulopimfwc-floating-notification.size-comfy .mulopimfwc-notification-title {
                    font-size: 14px;
                }
                .mulopimfwc-floating-notification.size-compact .mulopimfwc-notification-title {
                    font-size: 13px;
                }
                .mulopimfwc-notification-body {
                    opacity: 0.9;
                    line-height: 1.5;
                }
                .mulopimfwc-floating-notification.size-comfy .mulopimfwc-notification-body {
                    font-size: 13px;
                }
                .mulopimfwc-floating-notification.size-compact .mulopimfwc-notification-body {
                    font-size: 12px;
                }
                .mulopimfwc-notice-stack .mulopimfwc-admin-notice {
                    margin-bottom: 10px;
                }
            `,
            minimal: `
                #mulopimfwc-floating-notifications {
                    position: fixed;
                    pointer-events: none;
                    z-index: 99999;
                    max-width: 320px;
                }
                #mulopimfwc-floating-notifications.mulopimfwc-floating-top-right {
                    top: 32px;
                    right: 20px;
                }
                #mulopimfwc-floating-notifications.mulopimfwc-floating-bottom-right {
                    bottom: 20px;
                    right: 20px;
                }
                #mulopimfwc-floating-notifications.mulopimfwc-floating-top-left {
                    top: 32px;
                    left: 20px;
                }
                #mulopimfwc-floating-notifications.mulopimfwc-floating-bottom-left {
                    bottom: 20px;
                    left: 20px;
                }
                .mulopimfwc-floating-notification {
                    background: #fff;
                    color: #1f2937;
                    border-radius: 6px;
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                    margin-bottom: 10px;
                    opacity: 0;
                    transform: translateX(20px);
                    pointer-events: auto;
                    transition: all 0.25s ease;
                    border-left: 3px solid #3b82f6;
                }
                .mulopimfwc-floating-notification.size-comfy {
                    padding: 12px 16px;
                    max-width: 350px;
                }
                .mulopimfwc-floating-notification.size-compact {
                    padding: 10px 14px;
                    max-width: 280px;
                }
                .mulopimfwc-floating-notification.show {
                    opacity: 1;
                    transform: translateX(0);
                }
                .mulopimfwc-floating-notification.severity-critical {
                    border-left-color: #ef4444;
                }
                .mulopimfwc-floating-notification.severity-warning {
                    border-left-color: #f59e0b;
                }
                .mulopimfwc-notification-title {
                    font-weight: 600;
                    font-size: 13px;
                    margin-bottom: 4px;
                }
                .mulopimfwc-notification-body {
                    font-size: 12px;
                    color: #6b7280;
                    line-height: 1.4;
                }
            `,
            classic: `
                #mulopimfwc-floating-notifications {
                    position: fixed;
                    pointer-events: none;
                    z-index: 99999;
                    max-width: 350px;
                }
                #mulopimfwc-floating-notifications.mulopimfwc-floating-top-right {
                    top: 32px;
                    right: 20px;
                }
                #mulopimfwc-floating-notifications.mulopimfwc-floating-bottom-right {
                    bottom: 20px;
                    right: 20px;
                }
                #mulopimfwc-floating-notifications.mulopimfwc-floating-top-left {
                    top: 32px;
                    left: 20px;
                }
                #mulopimfwc-floating-notifications.mulopimfwc-floating-bottom-left {
                    bottom: 20px;
                    left: 20px;
                }
                .mulopimfwc-floating-notification {
                    background: #fff;
                    color: #333;
                    border-radius: 4px;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
                    margin-bottom: 12px;
                    opacity: 0;
                    pointer-events: auto;
                    transition: opacity 0.3s ease;
                    border: 1px solid #ddd;
                }
                .mulopimfwc-floating-notification.size-comfy {
                    padding: 14px 18px;
                    max-width: 350px;
                }
                .mulopimfwc-floating-notification.size-compact {
                    padding: 12px 16px;
                    max-width: 300px;
                }
                .mulopimfwc-floating-notification.show {
                    opacity: 1;
                }
                .mulopimfwc-notification-title {
                    font-weight: bold;
                    font-size: 14px;
                    margin-bottom: 6px;
                }
                .mulopimfwc-notification-body {
                    font-size: 13px;
                    color: #666;
                    line-height: 1.5;
                }
            `
        };

        if ($('#mulopimfwc-notification-styles').length) {
            $('#mulopimfwc-notification-styles').text(styles[style] || styles.modern);
            return;
        }
        $('<style id="mulopimfwc-notification-styles"></style>').text(styles[style] || styles.modern).appendTo('head');
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

    function initAdminBarNotifications() {
        // Add styles for admin bar notifications
        if ($('#mulopimfwc-admin-bar-styles').length === 0) {
            $('<style id="mulopimfwc-admin-bar-styles"></style>').text(`
                #wp-admin-bar-mulopimfwc-notifications .ab-icon {
                    font-size: 20px;
                    line-height: 1;
                    margin-right: 5px;
                }
                #wp-admin-bar-mulopimfwc-notifications .mulopimfwc-notification-count[data-count="0"] {
                    display: none;
                }
                #wp-admin-bar-mulopimfwc-notifications-dropdown {
                    min-width: 350px;
                    max-width: 400px;
                }
                #wp-admin-bar-mulopimfwc-notifications-dropdown .ab-item {
                    padding: 0;
                }
                ul#wp-admin-bar-mulopimfwc-notifications-default {
                    max-height: 500px;
                    min-height: 390px;
                    background: #fff;
                    overflow: hidden;
                    overflow-y: auto;
                    scrollbar-width: thin;
                }
                .mulopimfwc-notifications-header {
                    padding: 12px 15px;
                    border-bottom: 1px solid #eee;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    background: #f9f9f9;
                    position: sticky;
                    top: 0;
                    z-index: 10;
                }
                .mulopimfwc-notifications-header-buttons {
                    display: flex;
                    gap: 8px;
                }
                .mulopimfwc-notifications-header-btn {
                    padding: 4px 12px;
                    font-size: 12px;
                    border: 1px solid #ddd;
                    background: #fff;
                    cursor: pointer;
                    border-radius: 3px;
                    transition: all 0.2s;
                }
                .mulopimfwc-notifications-header-btn:hover {
                    background: #f0f0f0;
                    border-color: #999;
                }
                .mulopimfwc-notification-item {
                    padding: 12px 15px;
                    border-bottom: 1px solid #eee;
                    transition: background-color 0.2s;
                    display: flex;
                    align-items: flex-start;
                    gap: 10px;
                    position: relative;
                }
                .mulopimfwc-notification-item:hover {
                    background-color: #f5f5f5;
                }
                .mulopimfwc-notification-item:last-child {
                    border-bottom: none;
                }
                .mulopimfwc-notification-item.unread {
                    background-color: #f0f6ff;
                    font-weight: 500;
                }
                .mulopimfwc-notification-item.unread .mulopimfwc-notification-item-title {
                    font-weight: 700;
                }
                .mulopimfwc-notification-item.read {
                    opacity: 0.7;
                }
                .mulopimfwc-notification-item.severity-critical {
                    border-left: 3px solid #dc3232;
                }
                .mulopimfwc-notification-item.severity-warning {
                    border-left: 3px solid #f56e28;
                }
                .mulopimfwc-notification-item.severity-info {
                    border-left: 3px solid #2271b1;
                }
                .mulopimfwc-notification-item-icon {
                    flex-shrink: 0;
                    width: 20px;
                    height: 20px;
                    margin-top: 2px;
                }
                .mulopimfwc-notification-item-content {
                    flex: 1;
                    min-width: 0;
                    cursor: pointer;
                }
                .mulopimfwc-notification-item-title {
                    font-weight: 600;
                    font-size: 13px;
                    color: #1d2327;
                    margin-bottom: 4px;
                }
                .mulopimfwc-notification-item-message {
                    font-size: 12px;
                    color: #646970;
                    line-height: 1.4;
                }
                .mulopimfwc-notification-item-actions {
                    position: relative;
                    flex-shrink: 0;
                }
                .mulopimfwc-notification-item-menu-btn {
                    width: 24px;
                    height: 24px;
                    border: none;
                    background: transparent;
                    cursor: pointer;
                    border-radius: 3px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 16px;
                    color: #646970;
                    padding: 0;
                    transition: all 0.2s;
                }
                .mulopimfwc-notification-item-menu-btn:hover {
                    background: #e0e0e0;
                    color: #1d2327;
                }
                .mulopimfwc-notification-item-menu {
                    position: absolute;
                    top: 100%;
                    right: 0;
                    background: #fff;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
                    min-width: 120px;
                    z-index: 1000;
                    display: none;
                    margin-top: 4px;
                }
                .mulopimfwc-notification-item-menu.show {
                    display: block;
                }
                .mulopimfwc-notification-item-menu-item {
                    padding: 8px 12px;
                    font-size: 12px;
                    cursor: pointer;
                    border-bottom: 1px solid #f0f0f0;
                    transition: background 0.2s;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                .mulopimfwc-notification-item-menu-item:last-child {
                    border-bottom: none;
                }
                .mulopimfwc-notification-item-menu-item:hover {
                    background: #f5f5f5;
                }
                .mulopimfwc-notification-item-menu-item.danger {
                    color: #dc3232;
                }
                .mulopimfwc-notification-item-menu-item.danger:hover {
                    background: #ffe0e0;
                }
                .mulopimfwc-notifications-empty {
                    padding: 20px;
                    text-align: center;
                    color: #646970;
                    font-size: 13px;
                }
                .mulopimfwc-notifications-empty {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    max-height: 500px;
                    min-height: 390px;
                }
                .mulopimfwc-notifications-loading {
                    padding: 20px;
                    text-align: center;
                    color: #646970;
                    font-size: 13px;
                }
                .mulopimfwc-floating-notification {
                    position: relative;
                }
                .mulopimfwc-notification-close {
                    position: absolute;
                    top: 8px;
                    right: 8px;
                    background: rgba(0, 0, 0, 0.3);
                    border: none;
                    color: #fff;
                    width: 24px;
                    height: 24px;
                    border-radius: 50%;
                    cursor: pointer;
                    font-size: 18px;
                    line-height: 1;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    opacity: 0.7;
                    transition: opacity 0.2s, background 0.2s;
                    z-index: 10;
                }
                .mulopimfwc-notification-close:hover {
                    opacity: 1;
                    background: rgba(0, 0, 0, 0.5);
                }
                .mulopimfwc-notification-content {
                    padding-right: 30px;
                }
            `).appendTo('head');
        }
    }

    function fetchNotificationsForAdminBar() {
        $.post(config.ajaxurl, {
            action: 'mulopimfwc_dashboard_live_data',
            nonce: config.nonce
        })
            .done(function (response) {
                if (response.success && response.data && response.data.alerts) {
                    updateAdminBarNotifications(response.data.alerts);
                }
            });
    }

    function updateAdminBarNotifications(alerts) {
        const count = alerts ? alerts.length : 0;
        const unreadCount = alerts ? alerts.filter(function (a) { return !isRead(a.id); }).length : 0;
        const countElement = $('.mulopimfwc-notification-count');
        countElement.attr('data-count', unreadCount);
        countElement.text(unreadCount);

        const dropdown = $('#mulopimfwc-notifications-list');
        if (!dropdown.length) {
            return;
        }

        if (count === 0) {
            dropdown.html('<div class="mulopimfwc-notifications-empty"><svg width="30" height="30" viewBox="0 0 0.9 0.9" xmlns="http://www.w3.org/2000/svg" data-name="Layer 1"><path d="M.758.331.525.104a.106.106 0 0 0-.146 0L.146.329a.1.1 0 0 0-.034.073v.322a.103.103 0 0 0 .105.101h.466A.103.103 0 0 0 .787.723V.401A.1.1 0 0 0 .757.33M.428.158a.03.03 0 0 1 .042 0l.205.198L.47.554a.03.03 0 0 1-.042 0L.225.356Zm.285.565A.03.03 0 0 1 .684.75H.217A.03.03 0 0 1 .188.723V.425L.34.571l-.062.06a.037.037 0 0 0 0 .053.04.04 0 0 0 .027.012.04.04 0 0 0 .026-.011L.397.621a.1.1 0 0 0 .11 0l.066.064a.04.04 0 0 0 .026.011.04.04 0 0 0 .027-.012.037.037 0 0 0 0-.053L.563.572l.15-.146Z"/></svg> No new notifications</div>');
            return;
        }

        // Build header with buttons
        let html = '<div class="mulopimfwc-notifications-header">';
        html += '<strong>Notifications</strong>';
        html += '<div class="mulopimfwc-notifications-header-buttons">';
        html += '<button class="mulopimfwc-notifications-header-btn" data-action="read-all">Read All</button>';
        html += '<button class="mulopimfwc-notifications-header-btn" data-action="clear-all">Clear All</button>';
        html += '</div></div>';

        // Build notification items
        alerts.forEach(function (alert) {
            const severity = alert.severity || 'info';
            const message = formatAlertMessage(alert);
            const url = getNotificationUrl(alert);
            const icon = severity === 'critical' ? '⚠️' : (severity === 'warning' ? '⚡' : 'ℹ️');
            const read = isRead(alert.id);
            const readClass = read ? 'read' : 'unread';

            html += '<div class="mulopimfwc-notification-item severity-' + severity + ' ' + readClass + '" data-url="' +
                escapeHtml(url || '') + '" data-alert-id="' + escapeHtml(alert.id || '') + '">';
            html += '<span class="mulopimfwc-notification-item-icon">' + icon + '</span>';
            html += '<div class="mulopimfwc-notification-item-content">';
            html += '<div class="mulopimfwc-notification-item-title">' + escapeHtml(alert.label || 'Notification') + '</div>';
            html += '<div class="mulopimfwc-notification-item-message">' + message + '</div>';
            html += '</div>';
            html += '<div class="mulopimfwc-notification-item-actions">';
            html += '<button class="mulopimfwc-notification-item-menu-btn" data-alert-id="' + escapeHtml(alert.id || '') + '">⋯</button>';
            html += '<div class="mulopimfwc-notification-item-menu" data-alert-id="' + escapeHtml(alert.id || '') + '">';
            html += '<div class="mulopimfwc-notification-item-menu-item" data-action="view" data-url="' + escapeHtml(url || '') + '">👁️ View</div>';
            if (read) {
                html += '<div class="mulopimfwc-notification-item-menu-item" data-action="mark-unread" data-alert-id="' + escapeHtml(alert.id || '') + '">↩️ Mark as Unread</div>';
            } else {
                html += '<div class="mulopimfwc-notification-item-menu-item" data-action="mark-read" data-alert-id="' + escapeHtml(alert.id || '') + '">✓ Mark as Read</div>';
            }
            html += '<div class="mulopimfwc-notification-item-menu-item danger" data-action="remove" data-alert-id="' + escapeHtml(alert.id || '') + '">🗑️ Remove</div>';
            html += '</div></div></div>';
        });

        dropdown.html(html);

        // Header button handlers
        $('.mulopimfwc-notifications-header-btn[data-action="read-all"]').on('click', function (e) {
            e.stopPropagation();
            markAllAsRead(alerts);
            updateAdminBarNotifications(alerts);
        });

        $('.mulopimfwc-notification-item-menu-btn').on('click', function (e) {
            e.stopPropagation();
            const alertId = $(this).attr('data-alert-id');
            const menu = $('.mulopimfwc-notification-item-menu[data-alert-id="' + alertId + '"]');

            // Close all other menus
            $('.mulopimfwc-notification-item-menu').not(menu).removeClass('show');

            // Toggle current menu
            menu.toggleClass('show');
        });

        // Menu item handlers
        $('.mulopimfwc-notification-item-menu-item[data-action="view"]').on('click', function (e) {
            e.stopPropagation();
            const url = $(this).attr('data-url');
            if (url) {
                window.location.href = url;
            }
        });

        $('.mulopimfwc-notification-item-menu-item[data-action="mark-read"]').on('click', function (e) {
            e.stopPropagation();
            const alertId = $(this).attr('data-alert-id');
            markAsRead(alertId);
            updateAdminBarNotifications(alerts);
        });

        $('.mulopimfwc-notification-item-menu-item[data-action="mark-unread"]').on('click', function (e) {
            e.stopPropagation();
            const alertId = $(this).attr('data-alert-id');
            markAsUnread(alertId);
            updateAdminBarNotifications(alerts);
        });

        $('.mulopimfwc-notification-item-menu-item[data-action="remove"]').on('click', function (e) {
            e.stopPropagation();
            const alertId = $(this).attr('data-alert-id');

            // Mark as cleared so it won't show again
            const cleared = getClearedNotifications();
            if (!cleared.includes(alertId)) {
                cleared.push(alertId);
                saveClearedNotifications(cleared);
            }

            // Also mark as seen/read
            markAsSeen(alertId);
            markAsRead(alertId);

            // Remove from alerts array
            const filtered = alerts.filter(function (a) { return a.id !== alertId; });
            allNotifications = filtered;
            updateAdminBarNotifications(filtered);

            // Remove floating notification if exists
            floatContainer.find('.mulopimfwc-floating-notification[data-alert-id="' + alertId + '"]').remove();
        });

        // Click on notification content to view
        $('.mulopimfwc-notification-item-content').on('click', function (e) {
            e.stopPropagation();
            const item = $(this).closest('.mulopimfwc-notification-item');
            const url = item.attr('data-url');
            const alertId = item.attr('data-alert-id');

            // Mark as read when clicked
            if (alertId && !isRead(alertId)) {
                markAsRead(alertId);
                updateAdminBarNotifications(alerts);
            }

            if (url) {
                window.location.href = url;
            }
        });

        // Close menu when clicking outside
        $(document).on('click', function (e) {
            if (!$(e.target).closest('.mulopimfwc-notification-item-actions').length) {
                $('.mulopimfwc-notification-item-menu').removeClass('show');
            }
        });

        $('.mulopimfwc-notifications-header-btn[data-action="clear-all"]').on('click', function (e) {
            e.stopPropagation();
            if (confirm('Are you sure you want to clear all notifications?')) {
                // Clear all notifications and mark current ones as seen
                clearAllNotifications(alerts);
                // Update UI to show empty state
                updateAdminBarNotifications([]);
                // Also clear any floating notifications
                floatContainer.find('.mulopimfwc-floating-notification').remove();
            }
        });
    }

})(jQuery);

