import AdminRouter from './router';

const SELECTOR = '[data-autocomplete]';
const DEBOUNCE_MS = 300;

function escHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function initAutocomplete() {
    document.addEventListener('click', (e) => {
        const result = e.target.closest('.autocomplete-result');
        if (result) {
            const input = result.closest('.autocomplete-wrapper')?.querySelector(SELECTOR);
            if (!input) return;
            const nombre = result.getAttribute('data-nombre');
            const searchUrl = input.getAttribute('data-search-url');
            selectResult(input, nombre, searchUrl);
            return;
        }

        document.querySelectorAll('.autocomplete-dropdown').forEach((dd) => {
            const wrapper = dd.closest('.autocomplete-wrapper');
            const input = wrapper?.querySelector(SELECTOR);
            if (wrapper && !wrapper.contains(e.target)) {
                dd.classList.add('hidden');
            }
        });
    });

    document.addEventListener('input', (e) => {
        const input = e.target.closest(SELECTOR);
        if (!input) return;

        const dropdown = input.parentElement?.querySelector('.autocomplete-dropdown');
        if (!dropdown) return;

        clearTimeout(input._acTimer);

        const q = input.value.trim();

        if (!q) {
            dropdown.classList.add('hidden');
            const searchUrl = input.getAttribute('data-search-url');
            if (searchUrl) {
                clearTimeout(input._searchTimer);
                input._searchTimer = setTimeout(() => {
                    AdminRouter.load(searchUrl, false);
                }, 200);
            }
            return;
        }

        if (q.length < 2) {
            dropdown.classList.add('hidden');
            return;
        }

        input._acTimer = setTimeout(async () => {
            const url = input.getAttribute('data-autocomplete');
            try {
                const res = await axios.get(`${url}?q=${encodeURIComponent(q)}`);
                renderResults(input, dropdown, res.data);
            } catch (err) {
                console.error('Error en autocomplete:', err);
            }
        }, DEBOUNCE_MS);
    });

    document.addEventListener('keydown', (e) => {
        const input = e.target.closest(SELECTOR);
        if (!input) return;

        const dropdown = input.parentElement?.querySelector('.autocomplete-dropdown');
        if (!dropdown || dropdown.classList.contains('hidden')) return;

        const items = dropdown.querySelectorAll('.autocomplete-result');

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            navigateItems(items, 1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            navigateItems(items, -1);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const active = dropdown.querySelector('.autocomplete-result.active');
            if (active) {
                const nombre = active.getAttribute('data-nombre');
                const searchUrl = input.getAttribute('data-search-url');
                selectResult(input, nombre, searchUrl);
            }
        } else if (e.key === 'Escape') {
            dropdown.classList.add('hidden');
            input.blur();
        }
    });
}

function selectResult(input, nombre, searchUrl) {
    input.value = nombre;
    const dropdown = input.parentElement?.querySelector('.autocomplete-dropdown');
    if (dropdown) dropdown.classList.add('hidden');
    if (searchUrl && nombre) {
        AdminRouter.load(`${searchUrl}?q=${encodeURIComponent(nombre)}`, false);
    }
}

function navigateItems(items, dir) {
    if (!items.length) return;

    let current = [...items].findIndex((el) => el.classList.contains('active'));
    let next = current === -1 ? (dir === 1 ? 0 : items.length - 1) : current + dir;

    if (next < 0) next = items.length - 1;
    if (next >= items.length) next = 0;

    items.forEach((el) => el.classList.remove('active'));
    items[next].classList.add('active');
    items[next].scrollIntoView({ block: 'nearest' });
}

function renderResults(input, dropdown, results) {
    if (!results.length) {
        dropdown.innerHTML = `
            <div class="px-4 py-3 text-sm text-stone-400 text-center">Sin resultados</div>
        `;
        dropdown.classList.remove('hidden');
        return;
    }

    const isProducto = results[0].foto_url !== undefined;

    dropdown.innerHTML = results
        .map(
            (item, i) => `
        <button type="button"
            data-nombre="${escHtml(item.nombre)}"
            data-index="${i}"
            class="autocomplete-result flex items-center gap-3 w-full text-left px-4 py-2.5 hover:bg-emerald-50 transition cursor-pointer border-b border-stone-100 last:border-b-0 ${i === 0 ? 'active' : ''}"
        >
            ${isProducto ? renderProductoThumb(item) : ''}
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-stone-800 truncate">${escHtml(item.nombre)}</p>
                <p class="text-xs text-stone-500">${isProducto ? renderProductoMeta(item) : ''}</p>
            </div>
            <svg class="w-4 h-4 text-stone-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
        </button>
    `
        )
        .join('');

    dropdown.classList.remove('hidden');
}

function renderProductoThumb(item) {
    if (item.foto_url) {
        return `<img src="${item.foto_url}" alt="${escHtml(item.nombre)}" class="w-9 h-9 object-cover rounded-lg shrink-0 bg-stone-100">`;
    }
    return `<div class="w-9 h-9 rounded-lg bg-stone-100 flex items-center justify-center text-stone-300 shrink-0">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
    </div>`;
}

function renderProductoMeta(item) {
    let meta = '';
    if (item.categoria) meta += escHtml(item.categoria);
    if (item.marca) meta += (meta ? ' — ' : '') + escHtml(item.marca);
    return meta;
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAutocomplete);
} else {
    initAutocomplete();
}

export default initAutocomplete;
