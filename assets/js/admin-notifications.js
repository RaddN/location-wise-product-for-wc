(function ($) {
    'use strict';

    const config = window.mulopimfwcNotificationConfig || {};
    if (!config.ajaxurl) {
        return;
    }

    if (window.mulopimfwcNotificationsInitialized) {
        return;
    }
    window.mulopimfwcNotificationsInitialized = true;


    const alertHistory = new Set();
    const floatContainer = $('<div id="mulopimfwc-floating-notifications"></div>').appendTo('body');
    let serviceWorkerRegistration = null;
    let allNotifications = []; // Store all notifications for admin bar dropdown
    let lastRealtimeDataAt = 0;
    let liveRequestInFlight = false;
    const LIVE_RATE_LIMIT_MS = 5000;

    // Storage keys for read/unread notifications
    const STORAGE_READ_KEY = 'mulopimfwc_read_notifications';
    const STORAGE_SEEN_KEY = 'mulopimfwc_seen_notifications';
    const STORAGE_CLEARED_KEY = 'mulopimfwc_cleared_notifications';
    const STORAGE_RECENT_KEY = 'mulopimfwc_recent_notifications';
    const RECENT_TTL_MS = 10 * 60 * 1000;

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

    function loadRecentNotifications() {
        try {
            const stored = localStorage.getItem(STORAGE_RECENT_KEY);
            return stored ? JSON.parse(stored) : {};
        } catch (e) {
            return {};
        }
    }

    function saveRecentNotifications(map) {
        try {
            localStorage.setItem(STORAGE_RECENT_KEY, JSON.stringify(map));
        } catch (e) {
            console.warn('Failed to save recent notifications:', e);
        }
    }

    function pruneRecentNotifications(map) {
        const now = Date.now();
        Object.keys(map || {}).forEach(function (key) {
            if (!map[key] || (now - map[key]) > RECENT_TTL_MS) {
                delete map[key];
            }
        });
        return map;
    }

    function hasRecentNotification(fingerprint) {
        if (!fingerprint) {
            return false;
        }
        const map = pruneRecentNotifications(loadRecentNotifications());
        return Boolean(map[fingerprint]);
    }

    function markRecentNotification(fingerprint) {
        if (!fingerprint) {
            return;
        }
        const map = pruneRecentNotifications(loadRecentNotifications());
        map[fingerprint] = Date.now();
        saveRecentNotifications(map);
    }

    function hashString(input) {
        let hash = 5381;
        for (let i = 0; i < input.length; i++) {
            hash = ((hash << 5) + hash) + input.charCodeAt(i);
        }
        return (hash >>> 0).toString(36);
    }

    function buildAlertFingerprint(alert) {
        if (!alert) {
            return '';
        }

        const data = alert.data || {};
        const parts = [String(alert.type || alert.label || 'alert')];

        if (data.order_id) {
            parts.push('order:' + data.order_id);
        } else if (Array.isArray(data.order_ids) && data.order_ids.length) {
            const orderIds = data.order_ids.slice().sort();
            parts.push('orders:' + orderIds.join(','));
        } else if (data.product_id) {
            parts.push('product:' + data.product_id);
            if (data.location_id || data.location_name) {
                parts.push('loc:' + (data.location_id || data.location_name));
            }
            if (data.stock !== undefined && data.stock !== null) {
                parts.push('stock:' + data.stock);
            }
        } else if (Array.isArray(data.product_ids) && data.product_ids.length) {
            const productIds = data.product_ids.slice().sort();
            parts.push('products:' + productIds.join(','));
        } else if (data.review_id) {
            parts.push('review:' + data.review_id);
        } else if (data.user_id) {
            parts.push('user:' + data.user_id);
        } else if (data.url) {
            parts.push('url:' + data.url);
        } else if (alert.message) {
            parts.push('msg:' + stripHtmlTags(alert.message));
        }

        if (data.changed_at) {
            parts.push('at:' + data.changed_at);
        }

        return 'mulopimfwc:' + hashString(parts.join('|'));
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

    function canSendLiveRequest() {
        if (liveRequestInFlight || window.mulopimfwcRealtimeActive) {
            return false;
        }
        const now = Date.now();
        const lastRequestAt = window.mulopimfwcLiveRequestAt || 0;
        if (lastRequestAt && (now - lastRequestAt) < LIVE_RATE_LIMIT_MS) {
            return false;
        }
        window.mulopimfwcLiveRequestAt = now;
        return true;
    }

    // Check if notification is read
    function isRead(alertId) {
        return getReadNotifications().includes(alertId);
    }

    // Check if notification is seen (for floating)
    function isSeen(alertId) {
        if (!alertId) {
            return false;
        }
        const seen = getSeenNotifications();
        // Also check if it's in the current session history
        return seen.includes(alertId) || alertHistory.has(alertId);
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

        // Test floating notification (settings page)
        $('#mulopimfwc-test-floating-notification').on('click', function () {
            try {
                const alert = {
                    id: 'test-floating-' + Date.now(),
                    label: 'Test Notification',
                    message: 'This is a test floating notification.',
                    severity: 'info',
                    type: 'test',
                    data: {}
                };
                const msg = formatAlertMessage(alert);
                showFloatingNotification(alert, msg);
            } catch (e) {
                alert('❌ Failed to show floating notification. Check console for details.');
            }
        });

        // Test email UI behavior + actions
        function updateTestEmailUI() {
            const type = $('#mulopimfwc-test-email-recipient-type').val();
            const needsLocation = (type === 'location_contact' || type === 'location_manager');
            const needsManager = (type === 'location_manager');
            const needsCustom = (type === 'custom');

            $('.mulopimfwc-test-email-location, .mulopimfwc-test-email-location-label').toggle(needsLocation);
            $('.mulopimfwc-test-email-manager, .mulopimfwc-test-email-manager-label').toggle(needsManager);
            $('.mulopimfwc-test-email-custom, .mulopimfwc-test-email-custom-label').toggle(needsCustom);
        }

        $('#mulopimfwc-test-email-recipient-type').on('change', function () {
            updateTestEmailUI();
        });

        // Fetch managers for selected location
        $('#mulopimfwc-test-email-location').on('change', function () {
            const slug = $(this).val();
            const $mgr = $('#mulopimfwc-test-email-manager');
            $mgr.empty().append('<option value="all">All managers for selected location</option>');
            if (!slug) return;

            $.post(config.ajaxurl, {
                action: 'mulopimfwc_get_location_managers',
                nonce: config.test_nonce,
                location_slug: slug
            }).done(function (resp) {
                if (!resp || !resp.success || !resp.data || !Array.isArray(resp.data.managers)) {
                    return;
                }
                resp.data.managers.forEach(function (m) {
                    $mgr.append('<option value="' + escapeHtml(String(m.id)) + '">' + escapeHtml(m.label) + '</option>');
                });
            });
        });

        $('#mulopimfwc-send-test-email').on('click', function () {
            const $btn = $(this);
            const $result = $('#mulopimfwc-test-email-result');
            const type = $('#mulopimfwc-test-email-recipient-type').val();
            const locationSlug = $('#mulopimfwc-test-email-location').val();
            const managerId = $('#mulopimfwc-test-email-manager').val();
            const customEmail = $('#mulopimfwc-test-email-custom').val();

            $result.empty();

            $btn.prop('disabled', true).text('Sending...');
            $.post(config.ajaxurl, {
                action: 'mulopimfwc_send_test_notification_email',
                nonce: config.test_nonce,
                recipient_type: type,
                location_slug: locationSlug,
                manager_user_id: managerId,
                custom_email: customEmail
            }).done(function (resp) {
                if (resp && resp.success) {
                    const list = (resp.data && resp.data.recipients) ? resp.data.recipients : [];
                    $result.html('<div class="notice notice-success inline"><p><strong>✓ Sent.</strong> Recipients: ' + escapeHtml(list.join(', ')) + '</p></div>');
                } else {
                    const msg = resp && resp.data && resp.data.message ? resp.data.message : 'Failed to send.';
                    $result.html('<div class="notice notice-error inline"><p><strong>❌</strong> ' + escapeHtml(msg) + '</p></div>');
                }
            }).fail(function () {
                $result.html('<div class="notice notice-error inline"><p><strong>❌</strong> Request failed.</p></div>');
            }).always(function () {
                $btn.prop('disabled', false).text('Send Test Email');
            });
        });

        // Initialize visibility
        updateTestEmailUI();

        const realtimeEnabled = config.realtime_enabled !== 'off';
        const isDashboard = isDashboardView();

        if (realtimeEnabled && !isDashboard) {
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
        if ($('#wp-admin-bar-mulopimfwc-notifications').length > 0 && !isDashboard) {
            if (!realtimeEnabled) {
                fetchNotificationsForAdminBar();
            } else {
                setTimeout(function () {
                    if (lastRealtimeDataAt === 0) {
                        fetchNotificationsForAdminBar();
                    }
                }, 6000);
            }
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
        if (!canSendLiveRequest()) {
            return;
        }
        liveRequestInFlight = true;
        $.post(config.ajaxurl, {
            action: 'mulopimfwc_dashboard_live_data',
            nonce: config.nonce
        })
            .done(function (response) {
                if (response.success && response.data) {
                    handleRealtimeData(response.data);
                }
            })
            .always(function () {
                liveRequestInFlight = false;
            });
    }

    function handleRealtimeData(data) {
        lastRealtimeDataAt = Date.now();
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

        // Get seen notifications once at the start to avoid multiple localStorage reads
        // This improves performance and ensures consistency
        const seenNotificationsSet = new Set(getSeenNotifications());

        activeAlerts.forEach(function (alert) {
            if (config.realtime_enabled === 'off') {
                return;
            }
            if (!alert.id) {
                return;
            }

            const alertId = alert.id;
            const fingerprint = buildAlertFingerprint(alert);

            if (fingerprint && hasRecentNotification(fingerprint)) {
                // Mark as seen/read to keep admin bar counts accurate
                markAsSeen(alertId);
                markAsRead(alertId);
                alertHistory.add(alertId);
                return;
            }

            // Check if notification has been seen before (persists across page reloads)
            // Check both localStorage (via Set) and in-memory history
            const hasBeenSeenInStorage = seenNotificationsSet.has(alertId);
            const hasBeenSeenInSession = alertHistory.has(alertId);

            // Skip if already seen in either storage or session
            if (hasBeenSeenInStorage || hasBeenSeenInSession) {
                return;
            }

            const message = formatAlertMessage(alert);

            if (config.floating_enabled !== 'off') {
                showFloatingNotification(alert, message, fingerprint);
            }

            if (config.pwa_enabled === 'on') {
                sendPushNotification(alert, message, fingerprint);
            }

            // Record the notification only after display channels have had a chance to render it.
            // The floating renderer checks these stores to suppress duplicates, so marking earlier
            // prevents the first floating notification from appearing.
            alertHistory.add(alertId);
            markAsSeen(alertId);
            seenNotificationsSet.add(alertId);
            markRecentNotification(fingerprint);
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

    function showFloatingNotification(alert, message, fingerprint) {
        if (!alert || !alert.id) {
            return;
        }

        if (fingerprint && hasRecentNotification(fingerprint)) {
            return;
        }

        // Triple-check: storage, session, and DOM
        // This prevents race conditions where the same notification might be processed twice
        const seenInStorage = getSeenNotifications().includes(alert.id);
        const seenInSession = alertHistory.has(alert.id);
        const alreadyInDOM = floatContainer.find('.mulopimfwc-floating-notification[data-alert-id="' + alert.id + '"]').length > 0;
        
        if (seenInStorage || seenInSession || alreadyInDOM) {
            // Already seen or already displayed, don't show again
            return;
        }

        // Mark as seen IMMEDIATELY before creating DOM elements
        // This ensures that even if there's a race condition, we won't show duplicates
        markAsSeen(alert.id);
        alertHistory.add(alert.id);
        markRecentNotification(fingerprint);

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
            // Mark as seen when user closes the notification
            if (alert.id) {
                markAsSeen(alert.id);
            }
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
                    // Mark as seen when notification auto-closes
                    if (alert.id) {
                        markAsSeen(alert.id);
                    }
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
    }

    async function sendPushNotification(alert, message, fingerprint) {
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

        await showNotification(alert, message, fingerprint);
    }

    async function showNotification(alert, message, fingerprint) {
        // Ensure absolute URLs for icons with proper fallbacks
        let iconUrl = config.pwa_icon || window.location.origin + '/favicon.ico';
        let badgeUrl = config.pwa_badge || config.pwa_icon || window.location.origin + '/favicon.ico';
        
        // Make URLs absolute
        const icon = iconUrl.startsWith('http') ? iconUrl : (window.location.origin + (iconUrl.startsWith('/') ? '' : '/') + iconUrl);
        const badge = badgeUrl.startsWith('http') ? badgeUrl : (window.location.origin + (badgeUrl.startsWith('/') ? '' : '/') + badgeUrl);
        
        // Strip HTML from title as well (notifications don't support HTML)
        const title = stripHtmlTags(alert.label || alert.type || 'Notification');
        
        const dedupeTag = fingerprint || alert.id || 'mulopimfwc-notification-' + Date.now();
        const payload = {
            title: title,
            body: message,
            icon: icon,
            badge: badge,
            tag: dedupeTag,
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
                    return;
                }
            }
            
            // Try to get service worker registration if we don't have it
            if ('serviceWorker' in navigator && !serviceWorkerRegistration) {
                try {
                    const registration = await navigator.serviceWorker.ready;
                    if (registration && registration.showNotification) {
                        await registration.showNotification(payload.title, payload);
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

    function normalizeSeverity(severity) {
        const normalized = String(severity || 'info').toLowerCase();
        return ['critical', 'warning', 'info'].includes(normalized) ? normalized : 'info';
    }

    function getAdminBarSvgIcon(name) {
        const icons = {
            info: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"></circle><path d="M12 11v5"></path><path d="M12 8h.01"></path></svg>',
            warning: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 3 2.8 19a2 2 0 0 0 1.7 3h15a2 2 0 0 0 1.7-3L12 3Z"></path><path d="M12 9v5"></path><path d="M12 17h.01"></path></svg>',
            critical: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v6"></path><path d="M12 17h.01"></path></svg>',
            empty: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path><path d="M4 4 20 20"></path></svg>',
            more: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="5" r="1.6"></circle><circle cx="12" cy="12" r="1.6"></circle><circle cx="12" cy="19" r="1.6"></circle></svg>',
            view: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"></path><circle cx="12" cy="12" r="2.5"></circle></svg>',
            markRead: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m4 12 5 5L20 6"></path></svg>',
            markUnread: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 4h16v16H4z"></path><path d="m4 7 8 6 8-6"></path></svg>',
            remove: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6l-1 14H6L5 6"></path><path d="M10 11v5"></path><path d="M14 11v5"></path></svg>',
            readAll: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m4 12 4 4L20 5"></path><path d="m4 19 3 3 4-4"></path></svg>',
            clearAll: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6l-1 14H6L5 6"></path></svg>'
        };

        return icons[name] || icons.info;
    }

    function getAdminBarAlertMessage(alert) {
        const message = stripHtmlTags(alert && alert.message ? alert.message : '');
        return message || formatAlertMessage(alert || {});
    }

    function getNotificationTimeLabel(alert) {
        const timestamp = alert && alert.timestamp ? Number(alert.timestamp) : 0;
        if (!timestamp) {
            return '';
        }

        const timestampMs = timestamp < 10000000000 ? timestamp * 1000 : timestamp;
        const diffSeconds = Math.max(0, Math.floor((Date.now() - timestampMs) / 1000));

        if (diffSeconds < 60) {
            return 'Just now';
        }

        const diffMinutes = Math.floor(diffSeconds / 60);
        if (diffMinutes < 60) {
            return diffMinutes + 'm ago';
        }

        const diffHours = Math.floor(diffMinutes / 60);
        if (diffHours < 24) {
            return diffHours + 'h ago';
        }

        const diffDays = Math.floor(diffHours / 24);
        return diffDays + 'd ago';
    }

    function getAdminBarSummaryText(count, unreadCount) {
        if (count === 1) {
            return unreadCount === 1 ? '1 unread alert' : '1 alert';
        }

        return unreadCount > 0
            ? unreadCount + ' unread of ' + count
            : count + ' alerts';
    }

    function initAdminBarNotifications() {
        // Add styles for admin bar notifications
        if ($('#mulopimfwc-admin-bar-styles').length === 0) {
            $('<style id="mulopimfwc-admin-bar-styles"></style>').text(`
                #wpadminbar #wp-admin-bar-mulopimfwc-notifications > .ab-item {
                    display: flex!important;
                    align-items: center!important;
                    gap: 6px!important;
                    min-width: 42px!important;
                    padding: 0 10px !important;
                    color: #f0f0f1!important;
                }
                #wpadminbar #wp-admin-bar-mulopimfwc-notifications > .ab-item:hover,
                #wpadminbar #wp-admin-bar-mulopimfwc-notifications.hover > .ab-item,
                #wpadminbar #wp-admin-bar-mulopimfwc-notifications > .ab-item:focus {
                    color: #fff!important;
                    background: #1d2327!important;
                }
                #wpadminbar #wp-admin-bar-mulopimfwc-notifications {
                    position: relative!important;
                }
                #wpadminbar #wp-admin-bar-mulopimfwc-notifications .mulopimfwc-adminbar-icon {
                    display: inline-flex!important;
                    align-items: center!important;
                    justify-content: center!important;
                    width: 22px!important;
                    height: 32px!important;
                    color: currentColor!important;
                }
                #wpadminbar #wp-admin-bar-mulopimfwc-notifications .mulopimfwc-adminbar-icon svg,
                #wpadminbar #wp-admin-bar-mulopimfwc-notifications .mulopimfwc-adminbar-svg {
                    width: 18px!important;
                    height: 18px!important;
                    display: block!important;
                }
                #wpadminbar #wp-admin-bar-mulopimfwc-notifications .mulopimfwc-notification-count {
                    display: inline-flex!important;
                    align-items: center!important;
                    justify-content: center!important;
                    min-width: 17px!important;
                    height: 17px!important;
                    padding: 0 5px!important;
                    border-radius: 999px!important;
                    background: #d63638!important;
                    color: #fff!important;
                    font-size: 11px!important;
                    font-weight: 700!important;
                    line-height: 17px!important;
                    box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.22)!important;
                }
                #wpadminbar #wp-admin-bar-mulopimfwc-notifications .mulopimfwc-notification-count[data-count="0"] {
                    display: none!important;
                }
                #wpadminbar #wp-admin-bar-mulopimfwc-notifications .ab-sub-wrapper {
                    position: absolute !important;
                    top: 32px !important;
                    right: 0 !important;
                    left: auto !important;
                    width: 420px !important;
                    padding: 8px 0 0!important;
                    background: transparent!important;
                    box-shadow: none!important;
                    z-index: 1000001!important;
                }
                #wpadminbar #wp-admin-bar-mulopimfwc-notifications.hover > .ab-sub-wrapper,
                #wpadminbar #wp-admin-bar-mulopimfwc-notifications:focus-within > .ab-sub-wrapper {
                    display: block !important;
                }
                #wpadminbar #wp-admin-bar-mulopimfwc-notifications-dropdown {
                    width: 100%!important;
                }
                #wpadminbar #wp-admin-bar-mulopimfwc-notifications-dropdown .ab-item {
                    height: auto !important;
                    min-height: 0!important;
                    padding: 0 !important;
                    line-height: normal !important;
                    color: #1d2327 !important;
                    background: transparent !important;
                    white-space: normal!important;
                }
                #wpadminbar ul#wp-admin-bar-mulopimfwc-notifications-default {
                    width: 420px !important;
                    max-height: min(560px, calc(100vh - 48px)) !important;
                    min-height: 0 !important;
                    padding: 0 !important;
                    margin: 0 !important;
                    background: #fff !important;
                    border: 1px solid #dcdcde!important;
                    border-radius: 8px!important;
                    box-shadow: 0 18px 45px rgba(0, 0, 0, 0.20)!important;
                    overflow: hidden auto!important;
                    scrollbar-width: thin!important;
                    z-index: 1000002!important;
                }
                .mulopimfwc-notifications-dropdown-content {
                    color: #1d2327!important;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif!important;
                    font-size: 13px!important;
                    line-height: 1.4!important;
                    padding: 16px !important;
                }
                .mulopimfwc-notifications-header {
                    padding: 14px 16px!important;
                    border-bottom: 1px solid #dcdcde!important;
                    display: flex!important;
                    justify-content: space-between!important;
                    align-items: flex-start!important;
                    gap: 12px!important;
                    background: #fff!important;
                    position: sticky!important;
                    top: 0!important;
                    z-index: 20!important;
                }
                .mulopimfwc-notifications-heading {
                    display: flex!important;
                    flex-direction: column!important;
                    gap: 2px!important;
                    min-width: 0!important;
                }
                .mulopimfwc-notifications-title {
                    color: #1d2327!important;
                    font-size: 14px!important;
                    font-weight: 700!important;
                    line-height: 1.25!important;
                }
                .mulopimfwc-notifications-subtitle {
                    color: #646970!important;
                    font-size: 12px!important;
                    line-height: 1.35!important;
                }
                .mulopimfwc-notifications-header-buttons {
                    display: flex!important;
                    gap: 6px!important;
                    flex-shrink: 0!important;
                }
                .mulopimfwc-notifications-header-btn {
                    display: inline-flex!important;
                    align-items: center!important;
                    justify-content: center!important;
                    gap: 5px!important;
                    min-height: 28px!important;
                    min-width: 78px!important;
                    padding: 5px 9px !important;
                    font-size: 12px !important;
                    font-weight: 600 !important;
                    line-height: 1 !important;
                    font-family: inherit!important;
                    color: #1d2327!important;
                    border: 1px solid #c3c4c7!important;
                    background: #fff!important;
                    cursor: pointer!important;
                    border-radius: 6px !important;
                    transition: background 0.18s ease, border-color 0.18s ease, color 0.18s ease !important;
                    white-space: nowrap!important;
                    box-sizing: border-box !important;
                }
                .mulopimfwc-notifications-header-btn span,
                .mulopimfwc-notification-item-menu-item span {
                    line-height: 1.2!important;
                    white-space: nowrap!important;
                }
                .mulopimfwc-notifications-header-btn svg,
                .mulopimfwc-notification-item-menu-item svg,
                .mulopimfwc-notification-item-menu-btn svg {
                    width: 14px!important;
                    height: 14px!important;
                    display: block!important;
                    fill: none!important;
                    stroke: currentColor!important;
                    stroke-width: 1.9!important;
                    stroke-linecap: round!important;
                    stroke-linejoin: round!important;
                }
                .mulopimfwc-notifications-header-btn:hover {
                    background: #f6f7f7!important;
                    border-color: #8c8f94!important;
                }
                .mulopimfwc-notification-item {
                    padding: 12px 14px !important;
                    border-bottom: 1px solid #f0f0f1!important;
                    transition: background-color 0.18s ease !important;
                    display: grid!important;
                    grid-template-columns: 36px minmax(0, 1fr) 30px!important;
                    gap: 11px!important;
                    position: relative !important;
                    background: #fff !important;
                }
                .mulopimfwc-notification-item:hover {
                    background-color: #f6f7f7!important;
                }
                .mulopimfwc-notification-item:last-child {
                    border-bottom: none!important;
                }
                .mulopimfwc-notification-item.unread {
                    background-color: #f5f9ff!important;
                }
                .mulopimfwc-notification-item.unread .mulopimfwc-notification-item-title {
                    font-weight: 700!important;
                }
                .mulopimfwc-notification-item.unread:after {
                    content: ""!important;
                    position: absolute!important;
                    top: 18px!important;
                    right: 12px!important;
                    width: 6px!important;
                    height: 6px!important;
                    border-radius: 999px!important;
                    background: #2271b1!important;
                }
                .mulopimfwc-notification-item.severity-critical {
                    box-shadow: inset 3px 0 0 #d63638!important;
                }
                .mulopimfwc-notification-item.severity-warning {
                    box-shadow: inset 3px 0 0 #dba617!important;
                }
                .mulopimfwc-notification-item.severity-info {
                    box-shadow: inset 3px 0 0 #2271b1 !important;
                }
                .mulopimfwc-notification-item-icon {
                    width: 34px !important;
                    height: 34px!important;
                    border-radius: 8px!important;
                    display: inline-flex!important;
                    align-items: center!important;
                    justify-content: center!important;
                    margin-top: 1px!important;
                    color: #2271b1!important;
                    background: #e7f1fb!important;
                }
                .mulopimfwc-notification-item-icon svg {
                    width: 18px!important;
                    height: 18px!important;
                    fill: none!important;
                    stroke: currentColor!important;
                    stroke-width: 1.9!important;
                    stroke-linecap: round!important;
                    stroke-linejoin: round!important;
                }
                .mulopimfwc-notification-item.severity-critical .mulopimfwc-notification-item-icon {
                    color: #b32d2e!important;
                    background: #fcf0f1!important;
                }
                .mulopimfwc-notification-item.severity-warning .mulopimfwc-notification-item-icon {
                    color: #996800!important;
                    background: #fcf9e8!important;
                }
                .mulopimfwc-notification-item-content {
                    min-width: 0!important;
                    cursor: pointer!important;
                }
                .mulopimfwc-notification-item-meta {
                    display: flex!important;
                    align-items: center!important;
                    gap: 8px!important;
                    margin-bottom: 3px!important;
                }
                .mulopimfwc-notification-item-title {
                    font-weight: 600!important;
                    font-size: 13px!important;
                    color: #1d2327!important;
                    line-height: 1.3!important;
                    overflow: hidden!important;
                    text-overflow: ellipsis!important;
                    white-space: nowrap!important;
                }
                .mulopimfwc-notification-item-time {
                    color: #8c8f94!important;
                    font-size: 11px!important;
                    line-height: 1.3!important;
                    white-space: nowrap!important;
                }
                .mulopimfwc-notification-item-message {
                    font-size: 12px!important;
                    color: #646970!important;
                    line-height: 1.45!important;
                    overflow: hidden!important;
                    display: -webkit-box!important;
                    -webkit-line-clamp: 2!important;
                    -webkit-box-orient: vertical!important;
                    word-break: break-word!important;
                }
                .mulopimfwc-notification-item-actions {
                    position: relative!important;
                    justify-self: end!important;
                }
                .mulopimfwc-notification-item-menu-btn {
                    width: 28px!important;
                    height: 28px!important;
                    border: none!important;
                    background: transparent!important;
                    cursor: pointer!important;
                    border-radius: 6px!important;
                    display: flex!important;
                    align-items: center!important;
                    justify-content: center!important;
                    color: #646970!important;
                    padding: 0!important;
                    transition: background 0.18s ease, color 0.18s ease!important;
                }
                .mulopimfwc-notification-item-menu-btn:hover {
                    background: #e7f1fb!important;
                    color: #1d2327!important;
                }
                .mulopimfwc-notification-item-menu {
                    position: absolute!important;
                    top: 100%!important;
                    right: 0!important;
                    background: #fff!important;
                    border: 1px solid #dcdcde!important;
                    border-radius: 8px!important;
                    box-shadow: 0 10px 24px rgba(0,0,0,0.16)!important;
                    min-width: 168px!important;
                    z-index: 100000!important;
                    display: none!important;
                    margin-top: 4px!important;
                    padding: 5px!important;
                    overflow: hidden!important;
                }
                .mulopimfwc-notification-item-menu.show {
                    display: block!important;
                }
                .mulopimfwc-notification-item-menu-item {
                    width: 100%!important;
                    min-height: 32px!important;
                    padding: 7px 9px!important;
                    font-size: 12px!important;
                    line-height: 1.2!important;
                    color: #1d2327!important;
                    cursor: pointer!important;
                    border: 0!important;
                    background: transparent!important;
                    border-radius: 6px!important;
                    transition: background 0.18s ease, color 0.18s ease!important;
                    display: flex!important;
                    align-items: center!important;
                    gap: 8px!important;
                    text-align: left!important;
                }
                .mulopimfwc-notification-item-menu-item:hover {
                    background: #f6f7f7!important;
                }
                .mulopimfwc-notification-item-menu-item.danger {
                    color: #b32d2e!important;
                }
                .mulopimfwc-notification-item-menu-item.danger:hover {
                    background: #fcf0f1!important;
                }
                .mulopimfwc-notifications-empty {
                    padding: 46px 28px!important;
                    text-align: center!important;
                    color: #646970!important;
                    font-size: 13px!important;
                    display: flex!important;
                    flex-direction: column!important;
                    align-items: center!important;
                    justify-content: center!important;
                    gap: 8px!important;
                    min-height: 220px!important;
                }
                .mulopimfwc-notifications-empty svg {
                    width: 36px!important;
                    height: 36px!important;
                    margin-bottom: 4px!important;
                    color: #8c8f94!important;
                    fill: none!important;
                    stroke: currentColor!important;
                    stroke-width: 1.8!important;
                    stroke-linecap: round!important;
                    stroke-linejoin: round!important;
                }
                .mulopimfwc-notifications-empty strong {
                    color: #1d2327!important;
                    font-size: 14px!important;
                }
                .mulopimfwc-notifications-empty span {
                    max-width: 250px!important;
                    color: #646970!important;
                    line-height: 1.45!important;
                }
                .mulopimfwc-notifications-loading {
                    padding: 42px 20px!important;
                    text-align: center!important;
                    color: #646970!important;
                    font-size: 13px!important;
                }
                @media screen and (max-width: 782px) {
                    #wpadminbar #wp-admin-bar-mulopimfwc-notifications .ab-sub-wrapper {
                        position: fixed !important;
                        top: 46px !important;
                        right: 10px !important;
                        left: 10px !important;
                        width: auto !important;
                    }
                    #wpadminbar ul#wp-admin-bar-mulopimfwc-notifications-default {
                        width: auto !important;
                        max-height: calc(100vh - 66px)!important;
                    }
                    .mulopimfwc-notifications-header {
                        flex-direction: column!important;
                    }
                    .mulopimfwc-notifications-header-buttons {
                        width: 100%!important;
                    }
                    .mulopimfwc-notifications-header-btn {
                        flex: 1!important;
                        justify-content: center!important;
                    }
                }
                .mulopimfwc-floating-notification {
                    position: relative!important;
                }
                .mulopimfwc-notification-close {
                    position: absolute!important;
                    top: 8px!important;
                    right: 8px!important;
                    background: rgba(0, 0, 0, 0.3)!important;
                    border: none!important;
                    color: #fff!important;
                    width: 24px!important;
                    height: 24px!important;
                    border-radius: 50%!important;
                    cursor: pointer!important;
                    font-size: 18px!important;
                    line-height: 1!important;
                    display: flex!important;
                    align-items: center!important;
                    justify-content: center!important;
                    opacity: 0.7!important;
                    transition: opacity 0.2s, background 0.2s!important;
                    z-index: 10!important;
                }
                .mulopimfwc-notification-close:hover {
                    opacity: 1!important;
                    background: rgba(0, 0, 0, 0.5)!important;
                }
                .mulopimfwc-notification-content {
                    padding-right: 30px!important;
                }
            `).appendTo('head');
        }
    }

    function fetchNotificationsForAdminBar() {
        if (!canSendLiveRequest()) {
            return;
        }
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
        const activeAlerts = Array.isArray(alerts) ? alerts : [];
        const count = activeAlerts.length;
        const unreadCount = activeAlerts.filter(function (a) { return !isRead(a.id); }).length;
        const countElement = $('.mulopimfwc-notification-count');
        countElement.attr('data-count', unreadCount);
        countElement.text(unreadCount);

        const dropdown = $('#mulopimfwc-notifications-list');
        if (!dropdown.length) {
            return;
        }

        if (count === 0) {
            dropdown.html(
                '<div class="mulopimfwc-notifications-empty">' +
                getAdminBarSvgIcon('empty') +
                '<strong>No new notifications</strong>' +
                '<span>Order, stock, and system alerts will appear here.</span>' +
                '</div>'
            );
            return;
        }

        // Build header with buttons
        let html = '<div class="mulopimfwc-notifications-header">';
        html += '<div class="mulopimfwc-notifications-heading">';
        html += '<span class="mulopimfwc-notifications-title">Notifications</span>';
        html += '<span class="mulopimfwc-notifications-subtitle">' + escapeHtml(getAdminBarSummaryText(count, unreadCount)) + '</span>';
        html += '</div>';
        html += '<div class="mulopimfwc-notifications-header-buttons">';
        html += '<button type="button" class="mulopimfwc-notifications-header-btn" data-action="read-all">' + getAdminBarSvgIcon('readAll') + '<span>Read All</span></button>';
        html += '<button type="button" class="mulopimfwc-notifications-header-btn" data-action="clear-all">' + getAdminBarSvgIcon('clearAll') + '<span>Clear All</span></button>';
        html += '</div></div>';

        // Build notification items
        activeAlerts.forEach(function (alert) {
            const severity = normalizeSeverity(alert.severity);
            const message = getAdminBarAlertMessage(alert);
            const url = getNotificationUrl(alert);
            const icon = getAdminBarSvgIcon(severity);
            const read = isRead(alert.id);
            const readClass = read ? 'read' : 'unread';
            const timeLabel = getNotificationTimeLabel(alert);

            html += '<div class="mulopimfwc-notification-item severity-' + severity + ' ' + readClass + '" data-url="' +
                escapeHtml(url || '') + '" data-alert-id="' + escapeHtml(alert.id || '') + '">';
            html += '<span class="mulopimfwc-notification-item-icon">' + icon + '</span>';
            html += '<div class="mulopimfwc-notification-item-content">';
            html += '<div class="mulopimfwc-notification-item-meta">';
            html += '<div class="mulopimfwc-notification-item-title">' + escapeHtml(alert.label || 'Notification') + '</div>';
            if (timeLabel) {
                html += '<span class="mulopimfwc-notification-item-time">' + escapeHtml(timeLabel) + '</span>';
            }
            html += '</div>';
            html += '<div class="mulopimfwc-notification-item-message">' + escapeHtml(message) + '</div>';
            html += '</div>';
            html += '<div class="mulopimfwc-notification-item-actions">';
            html += '<button type="button" class="mulopimfwc-notification-item-menu-btn" aria-label="Notification actions" data-alert-id="' + escapeHtml(alert.id || '') + '">' + getAdminBarSvgIcon('more') + '</button>';
            html += '<div class="mulopimfwc-notification-item-menu" data-alert-id="' + escapeHtml(alert.id || '') + '">';
            html += '<button type="button" class="mulopimfwc-notification-item-menu-item" data-action="view" data-url="' + escapeHtml(url || '') + '">' + getAdminBarSvgIcon('view') + '<span>View</span></button>';
            if (read) {
                html += '<button type="button" class="mulopimfwc-notification-item-menu-item" data-action="mark-unread" data-alert-id="' + escapeHtml(alert.id || '') + '">' + getAdminBarSvgIcon('markUnread') + '<span>Mark as Unread</span></button>';
            } else {
                html += '<button type="button" class="mulopimfwc-notification-item-menu-item" data-action="mark-read" data-alert-id="' + escapeHtml(alert.id || '') + '">' + getAdminBarSvgIcon('markRead') + '<span>Mark as Read</span></button>';
            }
            html += '<button type="button" class="mulopimfwc-notification-item-menu-item danger" data-action="remove" data-alert-id="' + escapeHtml(alert.id || '') + '">' + getAdminBarSvgIcon('remove') + '<span>Remove</span></button>';
            html += '</div></div></div>';
        });

        dropdown.html(html);

        // Header button handlers
        $('.mulopimfwc-notifications-header-btn[data-action="read-all"]').on('click', function (e) {
            e.stopPropagation();
            markAllAsRead(activeAlerts);
            updateAdminBarNotifications(activeAlerts);
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
            updateAdminBarNotifications(activeAlerts);
        });

        $('.mulopimfwc-notification-item-menu-item[data-action="mark-unread"]').on('click', function (e) {
            e.stopPropagation();
            const alertId = $(this).attr('data-alert-id');
            markAsUnread(alertId);
            updateAdminBarNotifications(activeAlerts);
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
            const filtered = activeAlerts.filter(function (a) { return a.id !== alertId; });
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
                updateAdminBarNotifications(activeAlerts);
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
                clearAllNotifications(activeAlerts);
                // Update UI to show empty state
                updateAdminBarNotifications([]);
                // Also clear any floating notifications
                floatContainer.find('.mulopimfwc-floating-notification').remove();
            }
        });
    }

})(jQuery);
