// Vuno Farming Assistant - Service Worker
const CACHE_NAME = 'vuno-farming-v1';
const OFFLINE_URL = '/offline.html';

const STATIC_CACHE = [
    '/',
    '/index.html',
    '/css/style.css',
    '/js/app.js',
    '/js/offline.js',
    '/manifest.json',
    '/icons/icon-72x72.png',
    '/icons/icon-96x96.png',
    '/icons/icon-128x128.png',
    '/icons/icon-144x144.png',
    '/icons/icon-152x152.png',
    '/icons/icon-192x192.png',
    '/icons/icon-384x384.png',
    '/icons/icon-512x512.png'
];

// Install event - cache static assets
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('Caching static assets');
                return cache.addAll(STATIC_CACHE);
            })
            .then(() => self.skipWaiting())
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch event - cache-first strategy with network fallback
self.addEventListener('fetch', event => {
    // Skip non-GET requests and chrome-extension
    if (event.request.method !== 'GET' || 
        event.request.url.startsWith('chrome-extension://')) {
        return;
    }

    // Handle API requests differently
    if (event.request.url.includes('/api/')) {
        event.respondWith(
            networkFirstStrategy(event.request)
        );
    } else {
        // For static assets, use cache-first
        event.respondWith(
            cacheFirstStrategy(event.request)
        );
    }
});

// Cache-first strategy for static assets
async function cacheFirstStrategy(request) {
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
        return cachedResponse;
    }

    try {
        const networkResponse = await fetch(request);
        
        // Cache the new response if it's successful
        if (networkResponse && networkResponse.status === 200) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        // If offline and requesting HTML, show offline page
        if (request.headers.get('Accept').includes('text/html')) {
            return caches.match(OFFLINE_URL);
        }
        
        throw error;
    }
}

// Network-first strategy for API calls
async function networkFirstStrategy(request) {
    try {
        const networkResponse = await fetch(request);
        
        // Cache successful API responses
        if (networkResponse && networkResponse.status === 200) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        // If network fails, try cache
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Return offline fallback for API
        return new Response(JSON.stringify({
            success: false,
            message: 'You are offline. Please check your connection.',
            offline: true
        }), {
            headers: { 'Content-Type': 'application/json' }
        });
    }
}

// Background sync for offline requests
self.addEventListener('sync', event => {
    if (event.tag === 'sync-requests') {
        event.waitUntil(syncOfflineRequests());
    }
});

async function syncOfflineRequests() {
    // Implementation for background sync
    // Would sync queued API requests when online
}

// Push notifications
self.addEventListener('push', event => {
    const options = {
        body: event.data ? event.data.text() : 'New update from Vuno!',
        icon: '/icons/icon-192x192.png',
        badge: '/icons/icon-72x72.png',
        vibrate: [100, 50, 100],
        data: {
            dateOfArrival: Date.now(),
            primaryKey: 'vuno-notification'
        },
        actions: [
            {
                action: 'open',
                title: 'Open Vuno'
            },
            {
                action: 'close',
                title: 'Close'
            }
        ]
    };

    event.waitUntil(
        self.registration.showNotification('Vuno Farming Assistant', options)
    );
});

self.addEventListener('notificationclick', event => {
    event.notification.close();

    if (event.action === 'open') {
        event.waitUntil(
            clients.openWindow('/')
        );
    }
});