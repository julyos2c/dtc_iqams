// Run with node --test tests/browser/scanner-preview.mjs. Set CHROME_BIN outside Windows.
import test from 'node:test';
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { launch, until } from './chrome.mjs';
import { once } from 'node:events';
import { mkdtemp, readFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';


test('queue commits, retries and survives offline reload and browser restart', { timeout: 90000 }, async () => {
    let down = false;
    let authorized = true;
    const posts = [];
    const server = createServer(async (req, res) => {
        if (req.method !== 'GET') posts.push(req.url);
        if (down) return req.socket.destroy();
        let path = new URL(req.url, 'http://localhost').pathname.slice(1);
        if (path === 'attendance-scanner/preview-context') {
            if (!authorized) { res.writeHead(403); return res.end(); }
            res.writeHead(200, { 'Content-Type': 'application/json' });
            return res.end(JSON.stringify({ mode: 'preview', terminal_id: 7, offline_session_id: 'test-session',
                expires_at: Math.floor(Date.now()/1000) + 3600, server_time: new Date().toISOString() }));
        }
        if (path === 'attendance-scanner/preview') path = 'scanner-preview/shell.html';
        const mime = { html: 'text/html', css: 'text/css', js: 'application/javascript', svg: 'image/svg+xml' };
        if (!['service-worker.js','scanner-preview-worker.js','favicon.svg'].includes(path) && !/^scanner-preview\/[\w.-]+$/.test(path)) {
            res.writeHead(404); return res.end();
        }
        const body = await readFile(new URL('../../public/' + path, import.meta.url));
        res.writeHead(200, { 'Content-Type': mime[path.split('.').pop()], 'Cache-Control': 'no-store' });
        res.end(body);
    });
    server.listen(0, '127.0.0.1');
    await once(server, 'listening');
    const url = 'http://127.0.0.1:' + server.address().port + '/attendance-scanner/preview';
    const profile = await mkdtemp(join(tmpdir(), 'iqams-queue-test-'));
    let page;
    const scan = (value, enter = true) => {
        const input = 'document.querySelector("#qr")';
        return `${input}.value=${JSON.stringify(value)}; ${input}.dispatchEvent(new Event("input")); ${enter ? input + '.dispatchEvent(new KeyboardEvent("keydown",{key:"Enter"}));' : ''}`;
    };
    const ready = async () => {
        await page.send('Page.navigate', { url });
        await until(page, 'document.querySelector("#qr")?.disabled === false');
    };
    const count = n => until(page, `document.querySelector('#pending').textContent === '${n}'`);
    const batch = () => page.evaluate(`(async () => {
        const q = await import('../scanner-preview/queue.js');
        const db = await q.openQueue();
        try {return await q.pendingBatch(db)} finally {db.close()}
    })()`);
    try {
        page = await launch(profile);
        await ready();
        await page.evaluate(scan('TEST-one') + scan('TEST-two') + scan('TEST-three', false));
        await count(3);
        const first = await batch();
        assert.deepEqual(first.map(e => e.local_sequence), [1,2,3]);
        assert.deepEqual(first.map(e => e.qr_code), ['TEST-one','TEST-two','TEST-three']);
        assert.equal(new Set(first.map(e => e.event_uuid)).size, 3);
        assert.ok(first.every(e => Date.parse(e.captured_at) && e.terminal_id === 7 && e.sync_status === 'pending'));
        const cached = await page.evaluate('(async()=>{const c=await caches.open((await caches.keys()).find(k=>k.startsWith("iqams-scanner-preview"))); return (await c.keys()).map(r=>new URL(r.url).pathname).sort()})()');
        assert.deepEqual(cached, ['/favicon.svg','/scanner-preview/queue.js','/scanner-preview/rehearsal.js','/scanner-preview/scanner.css','/scanner-preview/scanner.js','/scanner-preview/shell.html']);
        await page.evaluate(`const original = IDBObjectStore.prototype.add;
            IDBObjectStore.prototype.add = function (...args) {
                const result = original.apply(this,args);
                this.transaction.abort(); return result;
            };` + scan('TEST-retry'));
        await until(page, 'document.querySelector("#failure").textContent.includes("1 unsaved")');
        await count(3);
        await page.evaluate('IDBObjectStore.prototype.add=original; document.querySelector("#retry").click()');
        await count(4);
        down = true;
        await page.send('Page.reload');
        await until(page, 'document.querySelector("#connection")?.textContent === "Offline"');
        await page.evaluate(scan('TEST-offline'));
        await count(5);
        await page.close();
        page = null;
        await rm(join(profile, 'DevToolsActivePort'), { force: true });
        page = await launch(profile);
        await ready();
        await count(5);
        assert.deepEqual((await batch()).slice(0,3), first);
        await page.evaluate(scan('TEST-reopened'));
        await count(6);
        assert.equal((await batch()).at(-1).local_sequence, 6);
        down = false;
        await page.evaluate('window.dispatchEvent(new Event("online"))');
        await until(page, 'document.querySelector("#connection").textContent === "Online"');
        authorized = false;
        await page.evaluate('window.dispatchEvent(new Event("online"))');
        await until(page, 'document.querySelector("#qr").disabled');
        await count(6);
        down = true;
        await page.send('Page.reload');
        await until(page, 'document.querySelector("#connection")?.textContent === "Offline"');
        assert.equal(await page.evaluate('document.querySelector("#qr").disabled'), true);
        assert.deepEqual(posts, []);
    } finally {
        if (page) await page.close();
        server.closeAllConnections();
        server.close();
        await rm(profile, { recursive: true, force: true, maxRetries: 4 });
    }
});
