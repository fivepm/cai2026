const CACHE_NAME = 'cai2026-v1';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll([
                '/login.php',
                '/assets/images/Logo_apk_CAI.jpg',
                '/assets/images/Logo%20CAI.jpg',
                '/assets/images/logo_kmm.png'
            ]);
        })
    );
});

self.addEventListener('fetch', (event) => {
    event.respondWith(
        fetch(event.request).catch(() => caches.match(event.request))
    );
});
