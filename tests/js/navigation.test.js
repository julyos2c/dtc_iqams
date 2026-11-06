import test from 'node:test';
import assert from 'node:assert/strict';
import { installNavigationFeedback } from '../../resources/js/navigation.js';

function setup() {
    const win = new EventTarget();
    win.location = { href: 'http://localhost/instructor/dashboard' };
    win.HTMLFormElement = class { target = ''; checkValidity() { return true; } };
    const doc = new EventTarget();
    const counts = { starts: 0, stops: 0, navigating: 0 };
    win.addEventListener('iqams:navigating', () => counts.navigating++);
    installNavigationFeedback(win, doc, { start: () => counts.starts++, stop: () => counts.stops++ });
    const click = (href, options = {}) => {
        const link = { href, target: '', hasAttribute: () => false, ...options.link };
        const event = new Event('click', { cancelable: true });
        Object.defineProperties(event, {
            target: { value: { closest: () => link } },
            button: { value: options.button ?? 0 },
            ctrlKey: { value: options.ctrlKey ?? false },
        });
        if (options.prevented) event.preventDefault();
        doc.dispatchEvent(event);
        return event;
    };
    return { win, doc, counts, click };
}

test('sidebar clicks leave the destination and history under native browser control', async () => {
    const { click, counts, win } = setup();
    win.fetch = () => assert.fail('Navigation must not fetch a duplicate document');
    win.history = { pushState: () => assert.fail('Navigation must not push stale URLs') };
    assert.equal(click('/instructor/attendance').defaultPrevented, false);
    assert.equal(click('/instructor/summary').defaultPrevented, false);
    await Promise.resolve();
    assert.equal(counts.starts, 2);
    win.dispatchEvent(new Event('popstate'));
    assert.equal(counts.starts, 2, 'Back/forward must not start a competing async navigation');
});

test('downloads, new tabs, modified clicks, hashes and cancelled actions show no loader', () => {
    const { click, counts } = setup();
    click('/export', { link: { hasAttribute: () => true } });
    click('/history', { link: { target: '_blank' } });
    click('/history', { ctrlKey: true });
    click('/history', { button: 1 });
    click('/history', { prevented: true });
    click('#attendance');
    click('mailto:help@example.test');
    assert.equal(counts.starts, 0);
});

test('same-page filters and explicit self links remain native navigations', async () => {
    const { click, counts } = setup();
    assert.equal(click('?month=2026-08').defaultPrevented, false);
    assert.equal(click('/scanner', { link: { target: '_self' } }).defaultPrevented, false);
    await Promise.resolve();
    assert.equal(counts.starts, 2);
});

test('valid forms show feedback, cancelled logout does not, and back cache resets feedback', async () => {
    const { win, doc, counts } = setup();
    for (const cancelled of [true, false]) {
        const event = new Event('submit', { cancelable: true });
        Object.defineProperty(event, 'target', { value: new win.HTMLFormElement() });
        if (cancelled) event.preventDefault();
        doc.dispatchEvent(event);
    }
    await Promise.resolve();
    assert.equal(counts.starts, 1);
    assert.equal(counts.navigating, 1);
    win.dispatchEvent(new Event('pageshow'));
    assert.equal(counts.stops, 1);
});

test('actions cancelled by a later listener do not start navigation feedback', async () => {
    const { doc, counts, click } = setup();
    doc.addEventListener('click', event => event.preventDefault());
    click('/cancelled');
    await Promise.resolve();
    assert.equal(counts.starts, 0);
});
