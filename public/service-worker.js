const CACHE = 'iqams-offline-v3';
const FALLBACK = new URL('offline.html', self.registration.scope).href;

self.addEventListener('install', event => {
    event.waitUntil(caches.open(CACHE).then(cache => cache.add(new Request(FALLBACK, { cache: 'reload' }))));
});

self.addEventListener('activate', event => {
    event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(key => key.startsWith('iqams-offline-') && key !== CACHE).map(key => caches.delete(key)))).then(() => self.clients.claim()));
});

self.addEventListener('fetch', event => {
    if (event.request.mode !== 'navigate' || event.request.method !== 'GET') return;
    event.respondWith(fetch(event.request).catch(async () => (await caches.open(CACHE)).match(FALLBACK) || Response.error()));
});

