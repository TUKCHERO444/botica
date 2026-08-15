import axios from 'axios';

const NavbarSearch = {
    debounceTimer: null,

    init() {
        this.$input = document.getElementById('navbar-search');
        this.$btn = document.getElementById('navbar-search-btn');
        this.$suggestions = document.getElementById('navbar-search-suggestions');

        if (!this.$input) return;

        this.bindEvents();
    },

    bindEvents() {
        this.$input.addEventListener('input', (e) => {
            clearTimeout(this.debounceTimer);
            const q = e.target.value.trim();
            if (q.length < 2) {
                this.cerrarSugerencias();
                return;
            }
            this.debounceTimer = setTimeout(() => this.buscar(q), 300);
        });

        this.$input.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.cerrarSugerencias();
                this.$input.blur();
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
                } else {
                    const q = this.$input.value.trim();
                    if (q) {
                        window.location.href = `/productos?q=${encodeURIComponent(q)}`;
                    }
                }
            }
        });

        if (this.$btn) {
            this.$btn.addEventListener('click', () => {
                const q = this.$input.value.trim();
                if (q) {
                    window.location.href = `/productos?q=${encodeURIComponent(q)}`;
                }
            });
        }

        document.addEventListener('click', (e) => {
            if (!e.target.closest('#navbar-search') && !e.target.closest('#navbar-search-suggestions') && !e.target.closest('#navbar-search-btn')) {
                this.cerrarSugerencias();
            }
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
};

document.addEventListener('DOMContentLoaded', () => NavbarSearch.init());
