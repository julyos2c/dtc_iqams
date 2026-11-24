// Narrower registration than the site's generic offline-navigation worker.
const scope = self.registration.scope;
const preview = new URL('preview', scope).href;
const station = new URL('station', scope).href;
const assets = new Map([
    ['../scanner-preview/shell.html', 'text/html'],
    ['../scanner-preview/scanner.js', 'javascript'],
    ['../scanner-preview/queue.js', 'javascript'],
    ['../scanner-preview/rehearsal.js', 'javascript'],
    ['../scanner-preview/scanner.css', 'text/css'],
    ['../favicon.svg', 'image/svg+xml'],
].map(([path, type]) => [new URL(path, scope).href, type]));
const shell = new URL('../scanner-preview/shell.html', scope).href;
const cacheName = 'iqams-scanner-preview-v3:' + scope;

self.addEventListener('install', event => {
    event.waitUntil((async () => {
        const cache = await caches.open(cacheName);
        for (const [url, type] of assets) {
            const response = await fetch(url, { credentials: 'omit', cache: 'reload', redirect: 'error' });
            if (!response.ok || !response.headers.get('Content-Type')?.includes(type)) {
                throw new Error('Scanner asset unavailable');
            }
            await cache.put(url, response);
        }
        await self.skipWaiting();
    })());
});
self.addEventListener('activate', event => event.waitUntil((async () => {
    await caches.delete('iqams-scanner-preview-v2:' + scope);
    await self.clients.claim();
})()));
self.addEventListener('fetch', event => {
    const { request } = event;
    if (request.method !== 'GET') return;
    if (request.mode === 'navigate' && [preview, station].includes(request.url)) {
        event.respondWith((async () => {
            const fallback = async () => (await caches.open(cacheName)).match(shell);
            try {
                const response = await fetch(request, { cache: 'no-store', signal: AbortSignal.timeout(5000) });
                if ([502, 503, 504].includes(response.status)) return await fallback() || response;
                return response;
            } catch {
                return await fallback() || Response.error();
            }
        })());
    } else if (assets.has(request.url) && request.mode !== 'navigate') {
        event.respondWith(caches.open(cacheName).then(async cache => await cache.match(request.url) || fetch(request)));
    }
});
