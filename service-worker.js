const CACHE_NAME = 'erp-cache-v1';
const OFFLINE_URL = './offline.html';

const urlsToCache = [
    './assets/css/custom.css',
    './assets/css/dashboard-modern.css',
    './assets/css/responsive.css',
    './assets/js/cache-manager.js',
    './assets/js/theme-manager.js'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                // We will cache basic assets, but since it's a dynamic PHP app, 
                // we rely mostly on network-first strategies.
                return cache.addAll(urlsToCache);
            })
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    // For HTML requests, we want network-first, fallback to offline page
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(() => {
                return caches.match(OFFLINE_URL);
            })
        );
        return;
    }

    // For other requests (CSS, JS, Images), Cache-First strategy
    event.respondWith(
        caches.match(event.request).then((response) => {
            return response || fetch(event.request).then((fetchResponse) => {
                return caches.open(CACHE_NAME).then((cache) => {
                    // Verify valid response before caching
                    if (event.request.method === 'GET' && fetchResponse.status === 200) {
                        cache.put(event.request, fetchResponse.clone());
                    }
                    return fetchResponse;
                });
            });
        })
    );
});
