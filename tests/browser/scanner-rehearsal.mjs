import test from 'node:test';
import assert from 'node:assert/strict';
import { launch, until } from './chrome.mjs';
import { spawn, spawnSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { mkdtemp, writeFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { once } from 'node:events';
import { createServer } from 'node:net';
import { setTimeout as delay } from 'node:timers/promises';

test('paired browser retries lost Laravel receipts and coordinates senders in two tabs', { timeout: 120000 }, async () => {
    const dir = await mkdtemp(join(tmpdir(), 'iqams-rehearsal-'));
    const db = join(dir, 'test.sqlite');
    await writeFile(db, '');
    const portProbe = createServer().listen(0, '127.0.0.1');
    await once(portProbe, 'listening');
    const port = portProbe.address().port;
    await new Promise(resolve => portProbe.close(resolve));
    const base = 'http://127.0.0.1:' + port;
    const env = { ...process.env, APP_ENV: 'local', APP_CONFIG_CACHE: join(dir, 'unused-config.php'),
        APP_URL: base, ASSET_URL: base, APP_KEY: 'base64:' + randomBytes(32).toString('base64'),
        DB_CONNECTION: 'sqlite', DB_DATABASE: db, DB_URL: '', CACHE_STORE: 'database', SESSION_DRIVER: 'database',
        SESSION_DOMAIN: 'null', SESSION_SECURE_COOKIE: 'false', QUEUE_CONNECTION: 'sync',
        SCANNER_QUEUE_PREVIEW: 'true', SCANNER_REHEARSAL_SYNC: 'true', SCANNER_OPERATIONAL_SYNC: 'true' };
    const password = randomBytes(24).toString('hex');
    let page, server;
    try {
        const setup = spawnSync('php', ['tests/Support/rehearsal-browser-fixture.php'], { env, input: password, encoding: 'utf8' });
        assert.equal(setup.status, 0, setup.stdout + setup.stderr);
        server = spawn('php', ['-S', '127.0.0.1:' + port, resolve('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],
            { env, cwd: resolve('public'), windowsHide: true, stdio: 'ignore' });
        for (let i = 0; i < 50; i++) {
            try { if ((await fetch(base + '/up')).ok) break; } catch {}
            await delay(100);
        }
        page = await launch(join(dir, 'chrome'));
        await page.send('Network.enable');
        await page.send('Network.setBlockedURLs', { urls: ['https://*', '*:5173/*'] });
        const nav = path => page.send('Page.navigate', { url: base + path });
        await nav('/login');
        await until(page, '!!document.querySelector("[name=user_id]")');
        await page.evaluate(`(async()=>{const data=new FormData(document.querySelector('[name=user_id]').form);
            data.set('user_id','rehearsal-test');data.set('password',${JSON.stringify(password)});
            await fetch('/login',{method:'POST',redirect:'manual',body:data});})()`);
        await nav('/attendance-scanner');
        await until(page, '!!document.querySelector("[name=scanner_terminal_id]")');
        await page.evaluate("const select=document.querySelector('[name=scanner_terminal_id]'); select.selectedIndex=1; select.form.submit()");
        await until(page, "location.pathname.endsWith('/station')");
        await nav('/attendance-scanner/preview');
        await until(page, "document.querySelector('#transport')?.hidden === false");
        assert.equal(await page.evaluate(`(async()=>{const c=await (await fetch('preview-context')).json();return (await fetch('../confirm-password',{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':c.csrf},body:JSON.stringify({password:${JSON.stringify(password)}})})).status})()`), 204);
        await page.evaluate("document.querySelector('#pair').click()");
        await until(page, "document.querySelector('#qr')?.disabled === false");
        assert.equal(await page.evaluate(`window.second = window.open(location.href,'second'); Boolean(window.second)`, true), true);
        await until(page, "window.second?.document.querySelector('#qr')?.disabled === false");
        // Lose one response AFTER Laravel commits. Keep the sender active long enough
        // for the other tab to try the same Web Lock while QR capture continues.
        await page.evaluate(`window.realFetch = fetch.bind(window); window.lost = 0;
            window.fetch = async (url, init) => {
                const result = await realFetch(url,init);
                if (String(url).endsWith('/rehearsal/sync') && !lost) {
                    lost++; await result.json();
                    await new Promise(r=>setTimeout(r,750));
                    throw new TypeError('Simulated lost response');
                }
                return result;
            };
            window.card = (win, code) => {
                const input = win.document.querySelector('#qr'); input.value=code;
                input.dispatchEvent(new win.KeyboardEvent('keydown',{key:'Enter'}));
            }; card(window,'TEST-first');`);
        await until(page, 'window.lost === 1');
        await page.evaluate("window.secondSyncs=0;const sendSecond=second.fetch.bind(second);second.fetch=(url,init)=>{if(String(url).endsWith('/rehearsal/sync'))secondSyncs++;return sendSecond(url,init)};card(second,'TEST-second'); second.document.querySelector('#sync').click()");
        assert.equal(await page.evaluate('secondSyncs'), 0);
        const readEvents = `window.events = async () => {const q=await import('../scanner-preview/queue.js'); const db=await q.openQueue();
            try{return await new Promise((res,rej)=>{const r=db.transaction('events').objectStore('events').getAll();r.onsuccess=()=>res(r.result);r.onerror=()=>rej(r.error)})}
            finally{db.close()}};`;
        await page.evaluate(readEvents);
        await until(page, "(async()=>{const a=await events();return a.length===2 && a.every(e=>e.sync_status==='synchronized')})()");
        const rows = await page.evaluate('events()');
        assert.equal(new Set(rows.map(r=>r.receipt_id)).size, 2);
        assert.ok(rows.find(r=>r.qr_code==='TEST-first').retry_count >= 2);
        assert.equal(await page.evaluate(`(async()=>{const q=await import('../scanner-preview/queue.js'); const db=await q.openQueue();try{return (await q.context(db,undefined,'key:1')).privateKey.extractable}finally{db.close()}})()`), false);
        await nav('/attendance-scanner/station');
        await until(page, "document.querySelector('#qr')?.disabled === false");
        await page.evaluate(readEvents.replace('q.openQueue()', 'q.openQueue(true)'));
        await page.send('Network.emulateNetworkConditions', { offline:true, latency:0, downloadThroughput:0, uploadThroughput:0 });
        await page.evaluate("const qr=document.querySelector('#qr');qr.value='STU-001';qr.dispatchEvent(new KeyboardEvent('keydown',{key:'Enter'}))");
        await until(page, "(async()=> (await events()).some(e=>e.sync_status==='pending' && e.qr_code==='STU-001'))()");
        await page.send('Network.emulateNetworkConditions', { offline:false, latency:0, downloadThroughput:-1, uploadThroughput:-1 });
        await page.evaluate("window.dispatchEvent(new Event('online'));document.querySelector('#sync').click()");
        await until(page, "(async()=> (await events()).some(e=>e.sync_status==='confirmed' && e.receipt_id.startsWith('attendance-')))()");
        assert.equal((await page.evaluate('events()')).length, 1);
        await page.close();
        page = null;
        await rm(join(dir,'chrome','DevToolsActivePort'), { force:true });
        page = await launch(join(dir,'chrome'));
        await page.send('Network.enable');
        await page.send('Network.setBlockedURLs', { urls: ['*/attendance-scanner/preview-context'] });
        await nav('/attendance-scanner/preview');
        await until(page, "document.querySelector('#connection')?.textContent === 'Offline' && !document.querySelector('#qr').disabled");
        await page.evaluate(readEvents);
        await page.evaluate("const qr=document.querySelector('#qr');qr.value='TEST-restart';qr.dispatchEvent(new KeyboardEvent('keydown',{key:'Enter'}))");
        await until(page, "(async()=> (await events()).some(e=>e.qr_code==='TEST-restart' && e.sync_status==='pending'))()");
        await page.send('Network.setBlockedURLs', { urls: [] });
        await page.evaluate("window.dispatchEvent(new Event('online'))");
        await until(page, "(async()=> (await events()).some(e=>e.qr_code==='TEST-restart' && e.sync_status==='review_required' && e.reason==='clock_uncertain'))()");
    } finally {
        if (page) await page.close();
        if (server && server.exitCode === null) { const stopped = once(server,'exit'); server.kill(); await stopped; }
        await rm(dir, { recursive:true, force:true, maxRetries:4 });
    }
});
