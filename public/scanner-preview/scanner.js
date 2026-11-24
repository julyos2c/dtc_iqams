import { openQueue, context, capture, pendingCount } from './queue.js';
import { rehearsal, clockEvidence } from './rehearsal.js';

const field = document.querySelector('#qr');
const el = id => document.getElementById(id);
const root = new URL('../', import.meta.url);
const scope = new URL('attendance-scanner/', root);
const operational = location.href === new URL('station', scope).href;
let db, session, idleTimer, probing = false, writing = false;
const unsaved = [];
let blocked = false;
let transport, heartbeatSeconds = 30;

function show(title, message, capturedAt = '') {
    el('title').textContent = title;
    el('message').textContent = message;
    el('captured').textContent = capturedAt ? new Date(capturedAt).toLocaleString() : '';
    el('captured').dateTime = capturedAt;
}
function validSession() {
    return session?.expires_at * 1000 > Date.now();
}
function focus() {
    if (!field.disabled) field.focus({ preventScroll: true });
}

async function probe() {
    if (probing) return;
    probing = true;
    try {
        let response;
        try {
            response = await fetch(new URL(operational ? 'station-context' : 'preview-context', scope), {
                headers: { Accept: 'application/json' }, cache: 'no-store', signal: AbortSignal.timeout(5000),
            });
        } catch {
            el('connection').textContent = 'Offline';
            return;
        }
        if (response.status >= 500) {
            el('connection').textContent = 'Offline';
            return;
        }
        let next = null;
        if (response.ok && !response.redirected && response.headers.get('Content-Type')?.includes('application/json')) {
            const data = await response.json();
            const secureMode = data.mode === (operational ? 'operational' : 'rehearsal');
            el('transport').hidden = !secureMode;
            if (secureMode) {
                heartbeatSeconds = data.heartbeat_seconds;
                next = await transport.connect(data);
            } else if (!operational && data.mode === 'preview' && Number.isSafeInteger(data.terminal_id) && data.offline_session_id && Number.isFinite(data.expires_at)) {
                transport.pause();
                next = { terminal_id: data.terminal_id, offline_session_id: data.offline_session_id,
                    expires_at: data.expires_at, server_time: data.server_time };
            }
        }
        session = next;
        if (!session) transport.pause();
        await context(db, session);
        el('connection').textContent = session ? 'Online' : 'Authorization required';
    } catch (error) {
        if (error instanceof TypeError || error.name === 'TimeoutError' || error.status === 429 || error.status >= 500) {
            el('connection').textContent = 'Offline';
            return;
        }
        session = null;
        transport.pause();
        await context(db, null);
        el('connection').textContent = 'Initialization failed';
        show('Scanner paused', 'Unable to verify or save the scanner context. Check local storage and reconnect.');
    } finally {
        probing = false;
        field.disabled = !validSession();
        if (field.disabled && el('connection').textContent !== 'Initialization failed') show('Scanner paused', 'Reconnect and initialize the preview from an authorized scanner session.');
        if (!field.disabled && ['Starting scanner', 'Scanner paused'].includes(el('title').textContent)) show('Waiting for QR card', operational ? 'Scan your card for server validation.' : 'Scan a test card to check local capture.');
        focus();
    }
}

async function writeScans() {
    if (writing || blocked) return;
    writing = true;
    try {
        while (unsaved.length) {
            const event = unsaved[0];
            const count = await capture(db, event);
            unsaved.shift();
            el('pending').textContent = count;
            show('QR Scan Captured', 'Your scan has been saved locally and is pending server validation.', event.captured_at);
            if (event.device) transport.kick();
        }
    } catch {
        blocked = true;
        el('retry').hidden = false;
        show('Scan could not be saved', 'Keep this page open and retry after restoring browser storage.');
    } finally {
        writing = false;
        el('failure').textContent = blocked ? unsaved.length + ' unsaved in this tab. Closing it loses these scans.' : '';
    }
}
function completeInput() {
    clearTimeout(idleTimer);
    const code = field.value.trim();
    field.value = '';
    if (!code) return;
    if (!validSession()) {
        field.disabled = true;
        show('Scanner paused', 'Preview authorization expired. Reconnect and rescan.');
        return;
    }
    if (operational && (/^TEST-/i.test(code) || code.length > 255 || /[\x00-\x1f\x7f]/.test(code))) {
        show('Card rejected', 'Use an attendance credential for this station.');
        return;
    }
    if (!operational && !/^TEST-[A-Za-z0-9_-]{1,100}$/.test(code)) {
        show('Test card required', 'Scan a synthetic identifier beginning with TEST-.');
        return;
    }
    unsaved.push({ event_uuid: crypto.randomUUID(), captured_at: new Date().toISOString(),
        terminal_id: session.terminal_id, offline_session_id: session.offline_session_id, qr_code: code, ...clockEvidence(session) });
    if (blocked) el('failure').textContent = unsaved.length + ' unsaved in this tab. Keep this page open and retry.';
    void writeScans();
}
field.addEventListener('input', () => {
    clearTimeout(idleTimer);
    idleTimer = setTimeout(completeInput, 350);
});
field.addEventListener('keydown', event => {
    if (event.key === 'Enter') { event.preventDefault(); completeInput(); }
});
el('retry').addEventListener('click', () => {
    blocked = false;
    el('retry').hidden = true;
    void writeScans();
    focus();
});
document.addEventListener('click', focus);
window.addEventListener('beforeunload', event => {
    if (unsaved.length) { event.preventDefault(); event.returnValue = ''; }
});

async function initialize() {
    if ((!operational && location.href !== new URL('preview', scope).href) || !window.isSecureContext) throw new Error('Open the authorized scanner using HTTPS or localhost.');
    if (operational) {
        document.querySelector('.preview').textContent = 'Local operational station · Attendance requires server confirmation';
        await new Promise((resolve, reject) => {
            navigator.locks.request(scope.href + 'station-capture', { ifAvailable: true }, held => {
                if (!held) { reject(new Error('Another tab is operating this station.')); return; }
                resolve();
                return new Promise(() => {});
            }).catch(reject);
        });
    }
    if (!navigator.serviceWorker || !crypto.randomUUID) throw new Error('This browser lacks required offline scanner support.');
    const script = new URL('service-worker.js?scanner-preview', root);
    let registration = await navigator.serviceWorker.getRegistration(location.href);
    if (registration?.active?.scriptURL !== script.href || registration.scope !== scope.href) {
        registration = await navigator.serviceWorker.register(script, { scope: scope.href });
        const worker = registration.installing || registration.waiting || registration.active;
        if (worker?.state !== 'activated') await new Promise((resolve, reject) => {
            if (!worker) return reject(new Error('Offline shell unavailable.'));
            const changed = () => {
                if (worker.state === 'activated') resolve();
                if (worker.state === 'redundant') reject(new Error('Could not install the offline shell.'));
            };
            worker.addEventListener('statechange', changed);
            changed();
        });
    }
    db = await openQueue(operational);
    transport = rehearsal(db, message => { el('sync-status').textContent = message; },
        async () => { el('pending').textContent = await pendingCount(db); },
        operational, () => !unsaved.length && !writing && !field.value);
    for (const [id, action] of Object.entries({
        pair: async () => { await transport.pair(); await probe(); },
        sync: () => transport.drain(true),
        revoke: async () => {
            if (window.confirm('Revoke this terminal’s device and all its offline sessions?')) {
                await transport.revoke(); await probe();
            }
        },
    })) el(id).onclick = () => action().catch(error => { el('sync-status').textContent = error.message; });
    el('pending').textContent = await pendingCount(db);
    session = await context(db);
    const persistent = await navigator.storage?.persist?.().catch(() => false);
    if (!persistent) el('storage').textContent = 'Persistent storage was not granted. Browser cleanup can remove this queue.';
    await probe();
    window.addEventListener('online', () => { el('connection').textContent = 'Reconnecting'; void probe(); });
    window.addEventListener('offline', probe);
    const heartbeat = async () => { await probe(); setTimeout(heartbeat, heartbeatSeconds * 1000); };
    setTimeout(heartbeat, heartbeatSeconds * 1000);
    document.addEventListener('visibilitychange', async () => {
        if (!document.hidden) {
            try { el('pending').textContent = await pendingCount(db); }
            catch { el('failure').textContent = 'Unable to read pending count. Check browser storage.'; }
            void probe();
        }
    });
}
initialize().catch(error => {
    field.disabled = true;
    show('Scanner unavailable', 'Local capture could not initialize. Check browser storage and reconnect.');
    el('failure').textContent = error.message;
});
