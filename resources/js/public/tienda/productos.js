import axios from 'axios';

const ProductosCatalogo = {
    debounceTimer: null,
    selectedCategorias: new Set(),
    selectedMarcas: new Set(),
    selectedPrecioMax: null,

    init() {
        this.parseQueryParams();
        this.cacheDom();
        this.bindEvents();
    },

    parseQueryParams() {
        const params = new URLSearchParams(window.location.search);
        params.getAll('categorias[]').forEach(id => this.selectedCategorias.add(id));
        params.getAll('marcas[]').forEach(id => this.selectedMarcas.add(id));
        const pm = params.get('precio_max');
        if (pm) this.selectedPrecioMax = pm;
    },

    cacheDom() {
        this.$grid = document.getElementById('productos-grid');
        this.$search = document.getElementById('search-productos');
        this.$suggestions = document.getElementById('search-suggestions');
        this.$btnLimpiar = document.getElementById('limpiar-filtros');
        this.$btnFiltrosMobile = document.getElementById('btn-filtros-mobile');
        this.$overlayFiltros = document.getElementById('mobile-filters-overlay');
        this.$panelFiltros = document.getElementById('mobile-filters-panel');
        this.$btnCerrarFiltros = document.getElementById('cerrar-filtros-mobile');
        this.$precioRange = document.getElementById('precio-range');
        this.$precioRangeLabel = document.getElementById('precio-range-label');
        this.$precioRangeMobile = document.getElementById('precio-range-mobile');
        this.$precioRangeMobileLabel = document.getElementById('precio-range-mobile-label');
    },

    bindEvents() {
        if (this.$search) {
            this.$search.addEventListener('input', (e) => {
                clearTimeout(this.debounceTimer);
                const q = e.target.value.trim();
                if (q.length < 2) {
                    this.cerrarSugerencias();
                    return;
                }
                this.debounceTimer = setTimeout(() => this.buscar(q), 300);
            });

            this.$search.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    this.cerrarSugerencias();
                    this.$search.blur();
                }
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    this.navegarSugerencias(1);
                }
                if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    this.navegarSugerencias(-1);
                }
                if (e.key === 'Enter') {
                    const active = this.$suggestions.querySelector('[data-index].active');
                    if (active) {
                        e.preventDefault();
                        const url = active.getAttribute('data-url');
                        if (url) window.location.href = url;
                    }
                }
            });

            document.addEventListener('click', (e) => {
                if (!e.target.closest('#search-productos') && !e.target.closest('#search-suggestions')) {
                    this.cerrarSugerencias();
                }
            });
        }

        document.addEventListener('change', (e) => {
            if (e.target.matches('[name="categorias[]"]')) {
                if (e.target.checked) {
                    this.selectedCategorias.add(e.target.value);
                } else {
                    this.selectedCategorias.delete(e.target.value);
                }
                this.syncCheckboxes(e.target, 'categorias');
                this.filtrar();
            }
            if (e.target.matches('[name="marcas[]"]')) {
                if (e.target.checked) {
                    this.selectedMarcas.add(e.target.value);
                } else {
                    this.selectedMarcas.delete(e.target.value);
                }
                this.syncCheckboxes(e.target, 'marcas');
                this.filtrar();
            }
        });

        const actualizarLabelPrecio = (val) => {
            this.selectedPrecioMax = val;
            const label = 'S/ ' + parseFloat(val).toFixed(2);
            if (this.$precioRangeLabel) this.$precioRangeLabel.textContent = label;
            if (this.$precioRangeMobileLabel) this.$precioRangeMobileLabel.textContent = label;
            if (this.$precioRange) this.$precioRange.value = val;
            if (this.$precioRangeMobile) this.$precioRangeMobile.value = val;
        };

        if (this.$precioRange) {
            this.$precioRange.addEventListener('input', (e) => {
                actualizarLabelPrecio(e.target.value);
            });
            this.$precioRange.addEventListener('change', (e) => {
                actualizarLabelPrecio(e.target.value);
                this.filtrar();
            });
        }
        if (this.$precioRangeMobile) {
            this.$precioRangeMobile.addEventListener('input', (e) => {
                actualizarLabelPrecio(e.target.value);
            });
            this.$precioRangeMobile.addEventListener('change', (e) => {
                actualizarLabelPrecio(e.target.value);
                this.filtrar();
            });
        }

        if (this.$btnLimpiar) {
            this.$btnLimpiar.addEventListener('click', () => {
                this.selectedCategorias.clear();
                this.selectedMarcas.clear();
                this.selectedPrecioMax = null;
                document.querySelectorAll('[name="categorias[]"], [name="marcas[]"]').forEach(cb => cb.checked = false);
                const maxVal = this.$precioRange?.max || this.$precioRangeMobile?.max || '';
                if (this.$precioRange) { this.$precioRange.value = maxVal; }
                if (this.$precioRangeMobile) { this.$precioRangeMobile.value = maxVal; }
                if (this.$precioRangeLabel) { this.$precioRangeLabel.textContent = 'S/ ' + parseFloat(maxVal).toFixed(2); }
                if (this.$precioRangeMobileLabel) { this.$precioRangeMobileLabel.textContent = 'S/ ' + parseFloat(maxVal).toFixed(2); }
                this.filtrar();
            });
        }

        if (this.$btnFiltrosMobile) {
            this.$btnFiltrosMobile.addEventListener('click', () => {
                this.$overlayFiltros.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            });
        }

        const cerrarFiltros = () => {
            this.$overlayFiltros.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        };

        if (this.$btnCerrarFiltros) {
            this.$btnCerrarFiltros.addEventListener('click', cerrarFiltros);
        }

        if (this.$overlayFiltros) {
            this.$overlayFiltros.addEventListener('click', (e) => {
                if (e.target === this.$overlayFiltros) cerrarFiltros();
            });
        }
    },

    syncCheckboxes(changed, name) {
        const val = changed.value;
        document.querySelectorAll(`[name="${name}[]"][value="${val}"]`).forEach(cb => {
            cb.checked = changed.checked;
        });
    },

    async buscar(q) {
        try {
            const res = await axios.get(`/productos/buscar?q=${encodeURIComponent(q)}`);
            this.renderSugerencias(res.data);
        } catch (err) {
            console.error('Error en búsqueda:', err);
        }
    },

    renderSugerencias(items) {
        if (!this.$suggestions) return;

        if (!items.length) {
            this.$suggestions.innerHTML = `
                <div class="px-4 py-3 text-sm text-stone-400 text-center">Sin resultados</div>
            `;
            this.$suggestions.classList.remove('hidden');
            return;
        }

        this.$suggestions.innerHTML = items.map((item, i) => `
            <a href="${item.url}" data-index="${i}" data-url="${item.url}"
               class="flex items-center gap-3 px-4 py-2.5 hover:bg-emerald-50 transition cursor-pointer ${i === 0 ? 'active' : ''}">
                ${item.foto_url
                    ? `<img src="${item.foto_url}" alt="${item.nombre}" class="w-10 h-10 object-cover rounded-lg shrink-0 bg-stone-100">`
                    : `<div class="w-10 h-10 rounded-lg bg-stone-100 flex items-center justify-center text-stone-300 shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>`
                }
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-stone-800 truncate">${this.escHtml(item.nombre)}</p>
                    <p class="text-xs text-stone-500">${item.categoria ? this.escHtml(item.categoria) + ' — ' : ''}S/ ${parseFloat(item.precio).toFixed(2)}</p>
                </div>
                <svg class="w-4 h-4 text-stone-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        `).join('');

        this.$suggestions.classList.remove('hidden');
    },

    cerrarSugerencias() {
        if (this.$suggestions) {
            this.$suggestions.classList.add('hidden');
            this.$suggestions.innerHTML = '';
        }
    },

    navegarSugerencias(dir) {
        const items = this.$suggestions.querySelectorAll('[data-index]');
        if (!items.length) return;

        let current = this.$suggestions.querySelector('[data-index].active');
        let idx = current ? parseInt(current.getAttribute('data-index')) : (dir === 1 ? -1 : items.length);
        let next = idx + dir;

        if (next < 0) next = items.length - 1;
        if (next >= items.length) next = 0;

        items.forEach(el => el.classList.remove('active'));
        items[next].classList.add('active');
        items[next].scrollIntoView({ block: 'nearest' });
    },

    escHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    },

    async filtrar() {
        const params = new URLSearchParams();
        this.selectedCategorias.forEach(id => params.append('categorias[]', id));
        this.selectedMarcas.forEach(id => params.append('marcas[]', id));
        if (this.selectedPrecioMax) params.set('precio_max', this.selectedPrecioMax);

        const qs = params.toString();
        const newUrl = `${window.location.pathname}${qs ? '?' + qs : ''}`;
        window.history.replaceState({}, '', newUrl);

        if (this.$btnLimpiar) {
            this.$btnLimpiar.classList.toggle('hidden', !qs);
        }

        try {
            const res = await axios.get(`/productos?${qs}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (this.$grid) this.$grid.innerHTML = res.data;
        } catch (err) {
            console.error('Error al filtrar productos:', err);
        }
    },
};

document.addEventListener('DOMContentLoaded', () => ProductosCatalogo.init());
