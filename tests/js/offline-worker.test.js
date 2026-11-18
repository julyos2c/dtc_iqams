import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync('public/service-worker.js', 'utf8');
function worker(fetchImpl) {
    const listeners = {};
    const entries = new Map();
    const cache = { add: async request => entries.set(request.url, 'offline page'), match: async key => entries.get(key) };
    const caches = { open: async () => cache, keys: async () => ['iqams-offline-v0', 'other'], delete: async key => { entries.set('deleted', key); } };
    const self = { registration: { scope: 'https://example.test/iqams/' }, clients: { claim: async () => {} }, addEventListener: (name, fn) => { listeners[name] = fn; } };
    vm.runInNewContext(source, { self, caches, fetch: fetchImpl, Request, Response, URL, Promise });
    return { listeners, entries };
}
async function lifecycle(listener) { let promise; listener({ waitUntil(value) { promise = value; } }); await promise; }
function navigate(listener, request) { let response; listener({ request, respondWith(value) { response = value; } }); return response; }

test('only the static fallback is cached and old IQAMS caches are removed', async () => {
    const { listeners, entries } = worker(async () => new Response());
    await lifecycle(listeners.install);
    assert.equal(entries.get('https://example.test/iqams/offline.html'), 'offline page');
    await lifecycle(listeners.activate);
    assert.equal(entries.get('deleted'), 'iqams-offline-v0');
});

test('navigation preserves HTTP errors and uses fallback only on network failure', async () => {
    let fails = false;
    const { listeners } = worker(async () => { if (fails) throw new TypeError('network failure'); return new Response('unavailable', { status: 503 }); });
    await lifecycle(listeners.install);
    const request = { mode: 'navigate', method: 'GET' };
    assert.equal((await navigate(listeners.fetch, request)).status, 503);
    fails = true;
    assert.equal(await navigate(listeners.fetch, request), 'offline page');
    assert.equal(navigate(listeners.fetch, { mode: 'cors', method: 'GET' }), undefined);
    assert.equal(navigate(listeners.fetch, { mode: 'navigate', method: 'POST' }), undefined);
});
