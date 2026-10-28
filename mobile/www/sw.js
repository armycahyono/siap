const CACHE_NAME = 'siap-mobile-cache-v2';
const STATIC_ASSETS = [
    './',
    'index.html',
    'manifest.json',
    'css/mobile.css',
    'assets/logo.png',
];

// Install: Cache Shell & Core Assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(STATIC_ASSETS);
        }).then(() => self.skipWaiting())
    );
});

// Activate: Cleanup Old Caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch Strategy:
// - API requests (/api/*) -> Network Only
// - HTML & JS -> Network First with Cache Fallback (always fresh code)
// - CSS, Images -> Stale-While-Revalidate
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Bypass API and non-GET requests
    if (url.pathname.includes('/api/') || event.request.method !== 'GET') {
        return;
    }

    const isCodeFile = url.pathname.endsWith('.js') || url.pathname.endsWith('.html') || url.pathname.endsWith('/mobile') || url.pathname.endsWith('/mobile/');

    if (isCodeFile) {
        // Network First
        event.respondWith(
            fetch(event.request).then((networkResponse) => {
                if (networkResponse && networkResponse.status === 200) {
                    const clone = networkResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
                }
                return networkResponse;
            }).catch(() => caches.match(event.request))
        );
        return;
    }

    // Static Assets: Stale While Revalidate
    event.respondWith(
        caches.match(event.request).then((cachedResponse) => {
            const fetchPromise = fetch(event.request).then((networkResponse) => {
                if (networkResponse && networkResponse.status === 200) {
                    const clone = networkResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
                }
                return networkResponse;
            }).catch(() => cachedResponse);

            return cachedResponse || fetchPromise;
        })
    );
});
