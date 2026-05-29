const CACHE_NAME = 'mycloud-v3';
const STATIC_ASSETS = [
    '/',
    '/styles-login/styles.css',
    '/cloud/manifest.php',
    '/images/background-logo-192.png',
    '/images/favicon-default.ico'
];

self.addEventListener('install', (event) => {
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            // Pre-cache core shell assets
            return cache.addAll(STATIC_ASSETS);
        })
    );
});

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
        }).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Bypass caching for API/AJAX calls (POST requests or explicit actions)
    if (event.request.method === 'POST' || url.searchParams.has('myCloud_action')) {
        return; 
    }

    // Dynamic JS/CSS bundles (Stale-While-Revalidate)
    if (url.searchParams.has('myCloud_js') || url.searchParams.has('myCloud_css')) {
		const paramKey = url.searchParams.has('myCloud_js') ? 'myCloud_js' : 'myCloud_css';
        event.respondWith(
            caches.match(event.request).then((cachedResponse) => {
                const fetchPromise = fetch(event.request).then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200) {
                        const responseToCache = networkResponse.clone();
                        caches.open(CACHE_NAME).then((cache) => {
                            // Find and delete older versions of this specific bundle
                            cache.keys().then((keys) => {
                                keys.forEach((request) => {
                                    const reqUrl = new URL(request.url);
                                    if (reqUrl.searchParams.has(paramKey) && request.url !== event.request.url) {
                                        cache.delete(request);
                                    }
                                });
                            });
                            // Store the new version
                            cache.put(event.request, responseToCache);
                        });
                    }
                    return networkResponse;
                }).catch(() => cachedResponse); // Safe fallback to cache if offline
                return cachedResponse || fetchPromise;
            })
        );
        return;
    }

    // Default Cache-First strategy for images and icons
    if (event.request.destination === 'image' || url.pathname.includes('/images/')) {
        event.respondWith(
            caches.match(event.request).then((response) => {
                return response || fetch(event.request).then((networkResponse) => {
                    const responseToCache = networkResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, responseToCache);
                    });
                    return networkResponse;
                });
            })
        );
        return;
    }

    // Network-First for navigation requests
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(() => caches.match(event.request))
        );
    }
});