import test from 'node:test';
import assert from 'node:assert/strict';
import { loadClassAttendance } from '../../resources/js/class-attendance.js';

test('latest date owns results and loading state even if an older request finishes', async () => {
    const pending = [];
    const fetcher = (url, options) => new Promise(resolve => pending.push({ url, options, resolve }));
    const state = { endpoint: '/schedule/__SCHEDULE__', selectedDay: { schedule_id: 3 } };
    const first = loadClassAttendance(state, '2026-08-01', fetcher);
    const second = loadClassAttendance(state, '2026-08-02', fetcher);
    assert.equal(pending[0].options.signal.aborted, true);
    pending[0].resolve({ ok: true, json: async () => ({ date: 'old' }) });
    await first;
    assert.equal(state.loading, true);
    pending[1].resolve({ ok: true, json: async () => ({ date: 'new' }) });
    await second;
    assert.equal(state.loading, false);
    assert.equal(state.selectedDate, '2026-08-02');
    assert.equal(state.attendance.date, 'new');
});

test('failure releases the current loading state', async () => {
    const state = { endpoint: '/__SCHEDULE__', selectedDay: { schedule_id: 1 } };
    await loadClassAttendance(state, '2026-08-01', async () => { throw new Error('offline'); });
    assert.equal(state.loading, false);
    assert.equal(state.error, 'offline');
});
