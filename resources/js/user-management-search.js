export function userManagementSearch() {
    return {
        timer: null,
        controller: null,
        sequence: 0,
        loading: false,
        error: '',
        paginationHandler: null,
        popstateHandler: null,

        init() {
            const form = this.$el;
            const card = form.closest('[data-repeatable-list]');
            this.paginationHandler = (event) => {
                const link = event.target.closest('[data-user-search-pagination] a[href]');
                if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
                event.preventDefault();
                if (this.timer) {
                    this.submit(form);
                } else {
                    this.load(link.href);
                }
            };
            card.addEventListener('click', this.paginationHandler);
            this.popstateHandler = () => {
                this.cancel();
                const url = new URL(window.location.href);
                this.query = url.searchParams.get('search') || '';
                this.load(url.href, false);
            };
            window.addEventListener('popstate', this.popstateHandler);
        },
        destroy() {
            this.cancel();
            this.$el.closest('[data-repeatable-list]')?.removeEventListener('click', this.paginationHandler);
            window.removeEventListener('popstate', this.popstateHandler);
        },
        cancel() {
            clearTimeout(this.timer);
            this.timer = null;
            this.sequence++;
            this.controller?.abort();
            this.controller = null;
            this.loading = false;
        },
        searchUrl(form) {
            const url = new URL(window.location.href);
            url.searchParams.delete('page');
            const value = form.elements.search.value;
            if (value.trim()) url.searchParams.set('search', value);
            else url.searchParams.delete('search');
            return url.href;
        },
        schedule(form) {
            this.cancel();
            this.error = '';
            this.timer = setTimeout(() => this.load(this.searchUrl(form)), 300);
        },
        submit(form) {
            this.cancel();
            this.load(this.searchUrl(form));
        },
        clear(form) {
            this.query = '';
            form.elements.search.value = '';
            form.elements.search.focus();
            this.submit(form);
        },
        async load(url, updateHistory = true) {
            this.cancel();
            const sequence = this.sequence;
            const controller = new AbortController();
            this.controller = controller;
            this.loading = true;
            this.error = '';
            try {
                const response = await fetch(url, { signal: controller.signal, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } });
                if (!response.ok || response.redirected) throw new Error('Search request failed');
                const html = await response.text();
                if (sequence !== this.sequence) return;
                const page = new DOMParser().parseFromString(html, 'text/html');
                const card = this.$el.closest('[data-repeatable-list]');
                const selectors = ['[data-user-search-count]', '[data-user-search-table]', '[data-user-search-pagination]'];
                const current = selectors.map(selector => card.querySelector(selector));
                const next = selectors.map(selector => page.querySelector(selector));
                if (current.some(element => !element) || next.some(element => !element)) throw new Error('Invalid search response');
                const ids = JSON.parse(next[1].dataset.userIds);
                if (!Array.isArray(ids)) throw new Error('Invalid search response');
                current.forEach((element, index) => {
                    element.innerHTML = next[index].innerHTML;
                });
                current[1].dataset.userIds = next[1].dataset.userIds;
                window.dispatchEvent(new CustomEvent('user-management-results', { detail: ids }));
                if (updateHistory) window.history.replaceState(window.history.state, '', url);
            } catch (error) {
                if (sequence === this.sequence && error.name !== 'AbortError') this.error = 'Unable to load results. Please try again.';
            } finally {
                if (sequence === this.sequence) {
                    this.loading = false;
                    this.controller = null;
                }
            }
        },
    };
}
