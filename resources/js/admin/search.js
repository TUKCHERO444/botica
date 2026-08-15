import AdminRouter from './router';

const SELECTOR = '[data-search]';
const DEBOUNCE_MS = 350;

function initSearch() {
    document.addEventListener('input', (e) => {
        const input = e.target.closest(SELECTOR);
        if (!input) return;

        clearTimeout(input._searchTimer);
        input._searchTimer = setTimeout(() => {
            const q = input.value.trim();
            const baseUrl = input.getAttribute('data-search');
            const url = q ? `${baseUrl}?q=${encodeURIComponent(q)}` : baseUrl;
            AdminRouter.load(url, false);
        }, DEBOUNCE_MS);
    });

    document.addEventListener('panel-loaded', () => {
        const input = document.querySelector(`${SELECTOR}:focus`);
        if (input) {
            const len = input.value.length;
            input.setSelectionRange(len, len);
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSearch);
} else {
    initSearch();
}

export default initSearch;
