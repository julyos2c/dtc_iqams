import test from 'node:test';
import assert from 'node:assert/strict';
import { installPasswordVisibilityToggles } from '../../resources/js/password-toggle.js';

function classList(initial = []) {
    const values = new Set(initial);
    return {
        contains: (value) => values.has(value),
        toggle: (value, force) => force ? values.add(value) : values.delete(value),
    };
}

function passwordField(value = '') {
    const input = {
        type: 'password',
        value,
        selectionStart: value.length,
        selectionEnd: value.length,
        selectionDirection: 'none',
        setSelectionRange(start, end, direction) { this.selection = [start, end, direction]; },
    };
    const showIcon = { classList: classList() };
    const hideIcon = { classList: classList(['hidden']) };
    const field = {
        querySelector(selector) {
            return {
                '[data-password-input]': input,
                '[data-password-toggle-show]': showIcon,
                '[data-password-toggle-hide]': hideIcon,
            }[selector] || null;
        },
    };
    const attributes = new Map();
    const button = {
        closest: (selector) => selector === '[data-password-field]' ? field : null,
        setAttribute: (name, value) => attributes.set(name, value),
        getAttribute: (name) => attributes.get(name),
    };

    return { input, showIcon, hideIcon, button };
}

function click(documentRef, button) {
    const event = new Event('click');
    Object.defineProperty(event, 'target', {
        value: { closest: (selector) => selector === '[data-password-toggle]' ? button : null },
    });
    documentRef.dispatchEvent(event);
}

test('password visibility toggle updates only its own field and can be repeated', () => {
    const documentRef = new EventTarget();
    installPasswordVisibilityToggles(documentRef);
    const first = passwordField('first-secret');
    const second = passwordField('second-secret');

    click(documentRef, first.button);
    assert.equal(first.input.type, 'text');
    assert.equal(first.input.value, 'first-secret');
    assert.deepEqual(first.input.selection, [12, 12, 'none']);
    assert.equal(first.button.getAttribute('aria-label'), 'Hide password');
    assert.equal(first.button.getAttribute('aria-pressed'), 'true');
    assert.equal(first.showIcon.classList.contains('hidden'), false);
    assert.equal(first.hideIcon.classList.contains('hidden'), true);
    assert.equal(second.input.type, 'password');

    click(documentRef, second.button);
    assert.equal(second.input.type, 'text');
    assert.equal(second.input.value, 'second-secret');
    assert.equal(first.input.type, 'text');

    click(documentRef, first.button);
    assert.equal(first.input.type, 'password');
    assert.equal(first.button.getAttribute('aria-label'), 'Show password');
    assert.equal(first.button.getAttribute('aria-pressed'), 'false');
    assert.equal(first.showIcon.classList.contains('hidden'), true);
    assert.equal(first.hideIcon.classList.contains('hidden'), false);
});
