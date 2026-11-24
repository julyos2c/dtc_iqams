// Phase 2 stores rehearsal events separately from any future attendance queue.
export function openQueue(operational = false) {
    return new Promise((resolve, reject) => {
        const prefix = operational ? 'iqams-attendance:' : 'iqams-scanner-preview:';
        const request = indexedDB.open(prefix + new URL('../', import.meta.url).pathname, 2);
        request.onupgradeneeded = () => {
            const db = request.result;
            if (!db.objectStoreNames.contains('metadata')) {
                db.createObjectStore('metadata');
                const events = db.createObjectStore('events', { keyPath: 'event_uuid' });
                events.createIndex('sequence', 'local_sequence', { unique: true });
                events.createIndex('pending', ['sync_status', 'local_sequence']);
            }
            request.transaction.objectStore('events').createIndex('rehearsal', ['device', 'operator_id', 'sync_status', 'local_sequence']);
        };
        request.onblocked = () => reject(new Error('Close other scanner tabs and reload.'));
        request.onerror = () => reject(request.error);
        request.onsuccess = () => {
            const db = request.result;
            db.onversionchange = () => db.close();
            resolve(db);
        };
    });
}

function transaction(db, stores, mode, work) {
    return new Promise((resolve, reject) => {
        const tx = db.transaction(stores, mode, { durability: 'strict' });
        let result;
        tx.oncomplete = () => resolve(result);
        tx.onabort = () => reject(tx.error || new Error('Storage transaction aborted.'));
        work(tx, value => { result = value; });
    });
}

export function context(db, value, key = 'context') {
    return transaction(db, ['metadata'], value === undefined ? 'readonly' : 'readwrite', (tx, done) => {
        const store = tx.objectStore('metadata');
        const request = value === undefined ? store.get(key) : store.put(value, key);
        request.onsuccess = () => done(value ?? request.result);
    });
}

export function pendingCount(db) {
    return transaction(db, ['events'], 'readonly', (tx, done) => {
        const request = tx.objectStore('events').index('pending').count(IDBKeyRange.bound(['pending', 0], ['pending', Number.MAX_SAFE_INTEGER]));
        request.onsuccess = () => done(request.result);
    });
}

export function pendingBatch(db, limit = 25, device = null, operator = null) {
    return transaction(db, ['events'], 'readonly', (tx, done) => {
        const index = tx.objectStore('events').index(device ? 'rehearsal' : 'pending');
        const prefix = device ? [device, operator, 'pending'] : ['pending'];
        const range = IDBKeyRange.bound([...prefix, 0], [...prefix, Number.MAX_SAFE_INTEGER]);
        const request = index.getAll(range, Math.max(1, Math.min(25, limit)));
        request.onsuccess = () => done(request.result);
    });
}

export function capture(db, event) {
    return transaction(db, ['metadata', 'events'], 'readwrite', (tx, done) => {
        const meta = tx.objectStore('metadata');
        const events = tx.objectStore('events');
        const last = meta.get('sequence');
        last.onsuccess = () => {
            const sequence = (last.result ?? 0) + 1;
            meta.put(sequence, 'sequence');
            const insert = () => events.add({ ...event, local_sequence: sequence, sync_status: 'pending',
                retry_count: 0, last_sync_attempt: null, receipt_id: null });
            const finish = () => {
                insert();
                const count = events.index('pending').count(IDBKeyRange.bound(['pending', 0], ['pending', Number.MAX_SAFE_INTEGER]));
                count.onsuccess = () => done(count.result);
            };
            if (event.device) {
                const key = 'sequence:' + event.offline_session_id;
                const previous = meta.get(key);
                previous.onsuccess = () => {
                    event.sequence = (previous.result || 0) + 1;
                    meta.put(event.sequence, key);
                    finish();
                };
            } else finish();
        };
    });
}

export function updateAttempts(db, batch, receipts = null, revoked = false) {
    return transaction(db, ['events'], 'readwrite', tx => {
        const store = tx.objectStore('events');
        for (const event of batch) {
            const read = store.get(event.event_uuid);
            read.onsuccess = () => {
                const row = read.result;
                if (!row || row.sync_status !== 'pending') return;
                if (revoked) {
                    row.sync_status = 'review_required';
                    row.reason = 'session_revoked_before_receipt';
                } else if (receipts) {
                    const receipt = receipts.find(item => item.event_uuid === row.event_uuid);
                    if (!receipt?.receipt_id || !['synchronized','confirmed','duplicate','review_required','rejected'].includes(receipt.status)) return;
                    Object.assign(row, { sync_status: receipt.status, receipt_id: receipt.receipt_id, reason: receipt.reason });
                    if (['confirmed','duplicate'].includes(receipt.status)) delete row.qr_code;
                } else {
                    row.retry_count++;
                    row.last_sync_attempt = new Date().toISOString();
                }
                store.put(row);
            };
        }
    });
}
