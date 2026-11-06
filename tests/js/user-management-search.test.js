import test from 'node:test';
import assert from 'node:assert/strict';
import { userManagementSearch } from '../../resources/js/user-management-search.js';

const wait = ms => new Promise(resolve => setTimeout(resolve, ms));

test('typing debounces, clears pending work, and resets pagination while preserving filters', async () => {
    const previous = globalThis.window;
    globalThis.window = { location: { href: 'https://example.test/students?page=3&filter=active' } };
    try {
        const search = userManagementSearch();
        const urls = [];
        search.load = url => urls.push(url);
        const form = { elements: { search: { value: 'Mar', focus() {} } } };
        search.schedule(form);
        await wait(180);
        form.elements.search.value = 'Maria';
        search.schedule(form);
        await wait(180);
        assert.equal(urls.length, 0);
        await wait(160);
        assert.equal(new URL(urls[0]).searchParams.get('search'), 'Maria');
        assert.equal(new URL(urls[0]).searchParams.get('filter'), 'active');
        assert.equal(new URL(urls[0]).searchParams.has('page'), false);
        search.schedule(form);
        search.clear(form);
        assert.equal(form.elements.search.value, '');
        assert.equal(new URL(urls[1]).searchParams.has('search'), false);
        await wait(320);
        assert.equal(urls.length, 2);
    } finally { globalThis.window = previous; }
});

test('stale responses cannot overwrite newer results and failure preserves input', async () => {
    const saved = { window: globalThis.window, fetch: globalThis.fetch, DOMParser: globalThis.DOMParser, CustomEvent: globalThis.CustomEvent };
    const elements = () => [{ innerHTML: 'old count' }, { innerHTML: 'old table', dataset: { userIds: '[]' } }, { innerHTML: 'old pages' }];
    const current = elements();
    const next = elements();
    next[0].innerHTML = 'new count'; next[1].innerHTML = 'new table'; next[1].dataset.userIds = '[42]'; next[2].innerHTML = 'new pages';
    const selectors = ['[data-user-search-count]', '[data-user-search-table]', '[data-user-search-pagination]'];
    const find = list => selector => list[selectors.indexOf(selector)];
    const pending = [];
    globalThis.fetch = () => new Promise((resolve, reject) => pending.push({ resolve, reject }));
    globalThis.DOMParser = class { parseFromString() { return { querySelector: find(next) }; } };
    globalThis.CustomEvent = class { constructor(type, options) { this.type = type; this.detail = options.detail; } };
    const events = [];
    globalThis.window = { dispatchEvent: event => events.push(event), history: { state: null, replaceState() {} } };
    const search = userManagementSearch();
    search.$el = { closest: () => ({ querySelector: find(current) }) };
    search.query = 'latest';
    try {
        const first = search.load('https://example.test/?search=old');
        const second = search.load('https://example.test/?search=latest');
        pending[1].resolve({ ok: true, redirected: false, text: async () => 'latest' });
        await second;
        assert.equal(current[1].innerHTML, 'new table');
        assert.deepEqual(events[0].detail, [42]);
        next[1].innerHTML = 'stale table';
        pending[0].resolve({ ok: true, redirected: false, text: async () => 'old' });
        await first;
        assert.equal(current[1].innerHTML, 'new table');
        const failed = search.load('https://example.test/?search=latest');
        pending[2].reject(new Error('network'));
        await failed;
        assert.match(search.error, /Unable to load/);
        assert.equal(search.query, 'latest');
        assert.equal(current[1].innerHTML, 'new table');
        assert.equal(search.loading, false);
    } finally { Object.assign(globalThis, saved); }
});
