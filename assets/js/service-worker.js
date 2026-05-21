// Service Worker for PWA Notifications
'use strict';

const CACHE_NAME = 'mulopimfwc-notifications-v1';

// Get site favicon for notifications (fallback to default favicon)
function getNotificationIcon() {
    // Use default favicon - browser will use site's favicon
    return self.location.origin + '/favicon.ico';
}

function getNotificationBadge() {
    return self.location.origin + '/favicon.ico';
}

// Install event - activate immediately
self.addEventListener('install', (event) => {
    self.skipWaiting();
});

// Activate event - take control of all clients
self.addEventListener('activate', (event) => {
    event.waitUntil(
        Promise.all([
            clients.claim(),
            caches.keys().then((cacheNames) => {
                return Promise.all(
                    cacheNames
                        .filter((cacheName) => cacheName !== CACHE_NAME)
                        .map((cacheName) => caches.delete(cacheName))
                );
            })
        ])
    );
});

// Push event handler (for future server push, currently using local notifications only)
self.addEventListener('push', (event) => {
    if (!event.data) {
        return;
    }

    let data = {};
    try {
        data = event.data.json();
    } catch (e) {
        const textData = event.data.text();
        data = {
            title: 'Notification',
            body: textData || 'New update available'
        };
    }

    const iconUrl = data.icon || getNotificationIcon();
    const badgeUrl = data.badge || getNotificationBadge();
    const icon = iconUrl.startsWith('http') ? iconUrl : (self.location.origin + (iconUrl.startsWith('/') ? '' : '/') + iconUrl);
    const badge = badgeUrl.startsWith('http') ? badgeUrl : (self.location.origin + (badgeUrl.startsWith('/') ? '' : '/') + badgeUrl);
    
    const options = {
        body: data.body || data.message || 'New notification',
        icon: icon,
        badge: badge,
        tag: data.tag || 'mulopimfwc-notification-' + Date.now(),
        requireInteraction: data.requireInteraction || false,
        data: data.data || {},
        actions: data.actions || [],
        vibrate: data.vibrate || (data.severity === 'critical' ? [200, 100, 200, 100, 200] : [200, 100, 200]),
        timestamp: data.timestamp || Date.now()
    };

    event.waitUntil(
        self.registration.showNotification(data.title || 'Location Management', options)
    );
});

// Notification click event
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    let urlToOpen = event.notification.data?.url || '/wp-admin/admin.php?page=multi-location-product-and-inventory-management';
    
    if (urlToOpen.startsWith('/')) {
        urlToOpen = self.location.origin + urlToOpen;
    }

    event.waitUntil(
        clients.matchAll({
            type: 'window',
            includeUncontrolled: true
        }).then((clientList) => {
            for (let i = 0; i < clientList.length; i++) {
                const client = clientList[i];
                const clientUrl = new URL(client.url);
                const targetUrl = new URL(urlToOpen);
                
                if (clientUrl.origin === targetUrl.origin && clientUrl.pathname === targetUrl.pathname) {
                    if ('focus' in client) {
                        return client.focus();
                    }
                }
            }
            
            if (clients.openWindow) {
                return clients.openWindow(urlToOpen);
            }
        }).catch(() => {
            if (clients.openWindow) {
                return clients.openWindow(urlToOpen);
            }
        })
    );
});

// Message event
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    
    if (event.ports && event.ports[0]) {
        event.ports[0].postMessage({ success: true });
    }
});

