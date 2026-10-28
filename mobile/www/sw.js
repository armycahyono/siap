const CACHE_NAME = 'siap-mobile-cache-v1';
const STATIC_ASSETS = [
    './',
    'index.html',
    'manifest.json',
    'css/mobile.css',
    'js/api.js',
    'js/router.js',
    'js/app.js',
    'assets/logo.png',
    'pages/auth/login.html',
    'pages/dashboard.html',
    'pages/attendance.html',
    'pages/tasks.html',
    'pages/profile.html',
];

// Install: Cache Shell & Core Templates
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
// - API requests (/api/*) -> Network Only (always fresh dynamic data)
// - Static Assets & Pages -> Stale-While-Revalidate
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Bypass API and non-GET requests
    if (url.pathname.includes('/api/') || event.request.method !== 'GET') {
        return;
    }

    event.respondWith(
        caches.match(event.request).then((cachedResponse) => {
            const fetchPromise = fetch(event.request).then((networkResponse) => {
                if (networkResponse && networkResponse.status === 200 && networkResponse.type === 'basic') {
                    const responseToCache = networkResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, responseToCache);
                    });
                }
                return networkResponse;
            }).catch(() => cachedResponse);

            return cachedResponse || fetchPromise;
        })
    );
});
