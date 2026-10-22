/* SIAP - Service Worker */
const CACHE_NAME = 'siap-cache-v4';

const PRECACHE_ASSETS = [
    '/offline.html',
    '/manifest.webmanifest',
    '/icons/icon-192x192.png',
    '/icons/icon-512x512.png',
    '/icons/icon-maskable-512x512.png',
    '/icons/apple-touch-icon.png',
    '/vendor/framework7/framework7-bundle.min.css',
    '/vendor/framework7/framework7-bundle.min.js',
    '/vendor/tabler-icons/tabler-icons.min.css',
    '/vendor/tabler-icons/fonts/tabler-icons.woff2'
];

// Install Event - Precache fallback and static branding assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(PRECACHE_ASSETS);
        }).then(() => {
            return self.skipWaiting();
        })
    );
});

// Activate Event - Clean up obsolete caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((name) => {
                    if (name !== CACHE_NAME) {
                        return caches.delete(name);
                    }
                })
            );
        }).then(() => {
            return self.clients.claim();
        })
    );
});

// Fetch Event - Handle caching strategies
self.addEventListener('fetch', (event) => {
    const request = event.request;

    // Only handle GET requests
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    // Skip Chrome extension, WebSockets, or unsupported schemes
    if (!url.protocol.startsWith('http')) {
        return;
    }

    // 1. Navigation Requests (HTML pages) -> Network-First with Offline Fallback
    if (request.mode === 'navigate') {
        // Jangan pernah simpan atau sajikan cache offline untuk halaman presensi / KBM dinamis agar selalu live & fresh
        const isDynamicLiveRoute = 
            url.pathname.includes('/attendance') || 
            url.pathname.includes('/piket') || 
            url.pathname.includes('/rekap') || 
            url.pathname.includes('/kbm');

        if (isDynamicLiveRoute) {
            event.respondWith(
                fetch(request).catch(() => caches.match('/offline.html'))
            );
            return;
        }

        event.respondWith(
            fetch(request)
                .then((networkResponse) => {
                    // Cache successful navigation page for offline reading
                    if (networkResponse && networkResponse.status === 200) {
                        const responseClone = networkResponse.clone();
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(request, responseClone);
                        });
                    }
                    return networkResponse;
                })
                .catch(() => {
                    // Try to serve cached version of this page first
                    return caches.match(request).then((cachedResponse) => {
                        if (cachedResponse) {
                            return cachedResponse;
                        }
                        // Otherwise serve offline fallback page
                        return caches.match('/offline.html');
                    });
                })
        );
        return;
    }

    // 2. Static Assets (CSS, JS, Fonts, Images) -> Stale-While-Revalidate
    const isStaticAsset = 
        request.destination === 'style' ||
        request.destination === 'script' ||
        request.destination === 'image' ||
        request.destination === 'font' ||
        url.hostname.includes('cdn.jsdelivr.net') ||
        url.hostname.includes('fonts.googleapis.com') ||
        url.hostname.includes('fonts.gstatic.com');

    if (isStaticAsset) {
        event.respondWith(
            caches.match(request).then((cachedResponse) => {
                const fetchPromise = fetch(request).then((networkResponse) => {
                    if (networkResponse && (networkResponse.status === 200 || networkResponse.type === 'opaque')) {
                        const responseClone = networkResponse.clone();
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(request, responseClone);
                        });
                    }
                    return networkResponse;
                }).catch(() => {
                    // Network failed, nothing extra needed if cachedResponse was already returned
                });

                return cachedResponse || fetchPromise;
            })
        );
        return;
    }

    // 3. Default -> Network with Cache fallback
    event.respondWith(
        fetch(request).catch(() => caches.match(request))
    );
});
