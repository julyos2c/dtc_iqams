export function installPasswordVisibilityToggles(documentRef = document) {
    documentRef.addEventListener('click', (event) => {
        const button = event.target.closest?.('[data-password-toggle]');
        if (!button) return;

        const field = button.closest('[data-password-field]');
        const input = field?.querySelector('[data-password-input]');
        if (!input) return;

        const selectionStart = input.selectionStart;
        const selectionEnd = input.selectionEnd;
        const selectionDirection = input.selectionDirection;
        const isHidden = input.type === 'password';

        input.type = isHidden ? 'text' : 'password';
        const isVisible = input.type === 'text';
        button.setAttribute('aria-label', isVisible ? 'Hide password' : 'Show password');
        button.setAttribute('aria-pressed', String(isVisible));
        field.querySelector('[data-password-toggle-show]')?.classList.toggle('hidden', !isVisible);
        field.querySelector('[data-password-toggle-hide]')?.classList.toggle('hidden', isVisible);

        if (selectionStart !== null && selectionEnd !== null) {
            input.setSelectionRange(selectionStart, selectionEnd, selectionDirection);
        }
    });
}
