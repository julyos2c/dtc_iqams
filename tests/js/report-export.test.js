import test from 'node:test';
import assert from 'node:assert/strict';
import { installReportExports } from '../../resources/js/report-export.js';

test('failed status checks offer a retry for the same export and release the button', async () => {
    const oldFormData = globalThis.FormData;
    globalThis.FormData = class { set() {} };
    try {
        const button = new EventTarget();
        button.dataset = { reportExportFormat: 'pdf', reportExportUrl: '/exports' };
        const status = { textContent: '', appendChild(node) { this.child = node; } };
        const doc = {
            querySelectorAll: () => [button],
            querySelector: selector => selector.includes('status') ? status : selector.includes('csrf') ? { content: 'token' } : {},
            createElement: () => new EventTarget(),
        };
        const tasks = [];
        let calls = 0;
        const win = { setTimeout: fn => tasks.push(fn), fetch: async () => {
            calls++;
            if (calls === 1) return { ok: true, json: async () => ({ status_url: '/status/1' }) };
            if (calls === 2) throw new Error('offline');
            return { ok: true, json: async () => ({ status: 'completed', download_url: '/download/1' }) };
        } };
        installReportExports(doc, win);
        button.dispatchEvent(new Event('click'));
        await new Promise(resolve => setImmediate(resolve));
        await tasks.shift()();
        assert.equal(button.disabled, false);
        assert.match(status.textContent, /Unable to check/);
        status.child.dispatchEvent(new Event('click'));
        await new Promise(resolve => setImmediate(resolve));
        assert.equal(calls, 3);
        assert.equal(status.textContent, 'Export ready.');
        assert.equal(status.child.href, '/download/1');
    } finally { globalThis.FormData = oldFormData; }
});
