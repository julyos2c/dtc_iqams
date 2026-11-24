import { context, openQueue, pendingBatch, pendingCount, updateAttempts } from './queue.js';

const boot = globalThis.crypto?.randomUUID?.();
const encoder = new TextEncoder();
const base64 = bytes => btoa(String.fromCharCode(...new Uint8Array(bytes)));
let anchorStart;
export function clockEvidence(session) {
    if (!session.device) return {};
    return { device: session.device, operator_id: session.operator_id, clock_id: boot,
        elapsed_ms: session.clock_id === boot ? Math.max(0, Math.round(performance.now() - anchorStart)) : null };
}

export function rehearsal(db, status, refresh, operational = false, captureDrained = () => true) {
    const mode = operational ? 'operational' : 'rehearsal';
    const scope = new URL('../attendance-scanner/' + mode + '/', import.meta.url);
    // Device pairing is shared. Scan queues and lease journals are separate databases.
    const deviceStore = operational ? openQueue() : Promise.resolve(db);
    let settings, key, lease, timer;
    let active = false;
    const metadata = async (name, value) => context(['key','fingerprint'].includes(name) ? await deviceStore : db, value, name + ':' + settings.terminal_id);
    const lock = name => scope.href + name;

    async function post(action, body, headers = {}) {
        const response = await fetch(new URL(action, scope), { method: 'POST', redirect: 'error',
            credentials: 'same-origin', cache: 'no-store', signal: AbortSignal.timeout(10000),
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': settings.csrf, ...headers }, body });
        if (!response.ok) {
            const error = new Error(response.status === 423 ? 'Confirm your password and retry.' : 'Scanner request failed (' + response.status + ').');
            error.retryAfter = Number(response.headers.get('Retry-After')) || 0;
            error.status = response.status;
            error.revokedSession = response.headers.get('X-Scanner-Session') === 'revoked';
            throw error;
        }
        return response.json();
    }

    async function signed(action, data) {
        const { nonce } = await post('challenge', JSON.stringify({ purpose: action }));
        const body = JSON.stringify(typeof data === 'function' ? await data() : data);
        const hash = await crypto.subtle.digest('SHA-256', encoder.encode(body));
        const hex = Array.from(new Uint8Array(hash), x => x.toString(16).padStart(2,'0')).join('');
        const signature = await crypto.subtle.sign('RSASSA-PKCS1-v1_5', key.privateKey, encoder.encode([nonce, action, hex].join('\n')));
        return post(action, body, { 'X-Scanner-Challenge': nonce, 'X-Scanner-Signature': base64(signature) });
    }

    async function pair() {
        await navigator.locks.request(lock('pair'), async () => {
            key = await metadata('key');
            if (!key || (!settings.registered_device && await metadata('fingerprint'))) {
                key = await crypto.subtle.generateKey({ name: 'RSASSA-PKCS1-v1_5', modulusLength: 2048,
                    publicExponent: new Uint8Array([1,0,1]), hash: 'SHA-256' }, false, ['sign','verify']);
                await metadata('key', key);
            }
            const pub = await crypto.subtle.exportKey('spki', key.publicKey);
            const result = await signed('pair', { public_key: base64(pub) });
            await metadata('fingerprint', result.device);
        });
    }

    async function connect(config) {
        settings = config;
        active = false;
        if (!navigator.locks || !crypto.subtle) throw new Error('Device keys and Web Locks are required.');
        key = await metadata('key');
        const device = await metadata('fingerprint');
        if (!device || !key) return null;
        if (!lease || lease.device !== device || lease.operator_id !== config.operator_id || lease.expires_at * 1000 <= Date.now()) {
            const issued = await signed('lease', { device, clock_id: boot });
            anchorStart = performance.now();
            lease = { ...issued, device, operator_id: config.operator_id, clock_id: boot };
            if (operational) {
                await context(db, 0, 'sequence:' + issued.offline_session_id);
                const journal = await metadata('journal') || [];
                journal.push(lease);
                await metadata('journal', journal);
            }
        }
        let beat;
        try {
            beat = await signed('heartbeat', { device, session_id: lease.offline_session_id, pending: await pendingCount(db) });
        } catch (error) {
            if (error.revokedSession) lease = null;
            throw error;
        }
        if (!beat.session_valid || !beat.device_authorized) { lease = null; throw new Error('Session expired or revoked.'); }
        active = beat.sync_available;
        if (operational && active) status('Ready to synchronize');
        schedule(0);
        return lease;
    }

    function schedule(delay) {
        clearTimeout(timer);
        if (active) timer = setTimeout(() => void drain().catch(error => status(error.message)), delay);
    }
    async function drain(manual = false) {
        if (!active) return;
        await navigator.locks.request(lock('sender'), { ifAvailable: true }, async held => {
            if (!active) return;
            if (!held) {
                if (operational) schedule(250);
                return;
            }
            const retry = await metadata('retry');
            if (retry && retry.after > Date.now() && (!manual || retry.limited)) {
                schedule(retry.after - Date.now()); return;
            }
            const rows = await pendingBatch(db, settings.batch_size, lease.device, lease.operator_id);
            if (!rows.length) {
                if (operational && captureDrained()) {
                    const journal = await metadata('journal') || [];
                    for (const entry of journal.filter(e => e.device === lease.device && e.operator_id === lease.operator_id)) {
                        try {
                            const proof = await signed('checkpoint', async () => {
                                const watermark = await context(db, undefined, 'sequence:' + entry.offline_session_id);
                                if (!Number.isSafeInteger(watermark) || !captureDrained()) throw new Error('Capture still pending');
                                return { device: entry.device, session_id: entry.offline_session_id, watermark };
                            });
                            if (proof.complete) journal.splice(journal.indexOf(entry),1);
                        } catch (error) {
                            if (!error.revokedSession) throw error;
                            journal.splice(journal.indexOf(entry),1);
                        }
                    }
                    await metadata('journal',journal);
                } else if (!operational) status('Rehearsal queue checked');
                return;
            }
            const sessionId = rows[0].offline_session_id;
            const batch = [];
            for (const row of rows) {
                if (row.offline_session_id !== sessionId) break;
                batch.push(row);
            }
            try {
                await updateAttempts(db, batch);
                const events = batch.map(({ event_uuid, sequence, captured_at, clock_id, elapsed_ms, qr_code }) =>
                    ({ event_uuid, sequence, captured_at, clock_id, elapsed_ms, qr_code }));
                status('Synchronizing');
                const reply = await signed('sync', { device: lease.device, session_id: sessionId, events });
                if (reply.mode !== mode || reply.session_id !== sessionId || !Array.isArray(reply.receipts)) throw new Error('Invalid receipt response.');
                await updateAttempts(db, batch, reply.receipts);
                await refresh();
                if (!batch.every(row => reply.receipts.some(r => r.event_uuid === row.event_uuid && r.receipt_id &&
                    ['synchronized','confirmed','duplicate','rejected','review_required'].includes(r.status)))) throw new Error('Incomplete acknowledgment.');
                await metadata('retry', null);
                const issues = reply.receipts.filter(r => r.status !== 'synchronized');
                if (operational) status('Attendance decisions: ' + reply.receipts.map(r => r.status.replace('_',' ')).join(', '));
                else status(issues.length ? 'Receipts requiring attention: ' + issues.length : 'Rehearsal receipts saved');
                if (operational || rows.length === settings.batch_size || batch.length < rows.length) schedule(50);
            } catch (error) {
                if (error.revokedSession) {
                    await updateAttempts(db, batch, null, true);
                    await refresh();
                    status('Revoked session. Scans retained locally for review.');
                    schedule(50);
                    return;
                }
                const attempts = Math.min(8, (retry?.attempts || 0) + 1);
                const delay = Math.max((error.retryAfter || 0) * 1000, (0.5 + Math.random()) * 1000 * 2 ** attempts);
                await metadata('retry', { attempts, after: Date.now() + delay, limited: error.status === 429 });
                status(error.message + ' Pending events retained.');
                schedule(delay);
            }
        });
    }
    return { connect, pair, drain, kick: () => schedule(0), pause: () => { active = false; clearTimeout(timer); },
        revoke: async () => {
            const device = settings.registered_device;
            if (!device) throw new Error('No registered device.');
            await post('revoke', JSON.stringify({ device }));
            await metadata('key', null);
            await metadata('fingerprint', null);
            lease = null; active = false; clearTimeout(timer);
        } };
}
