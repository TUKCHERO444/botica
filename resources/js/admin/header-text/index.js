import axios from 'axios';
import Sortable from 'sortablejs';

const HeaderTextManager = {
    init() {
        if (!document._headerTextInit) {
            this.bindEvents();
            document._headerTextInit = true;
        }
        this.initSortable();
    },

    initSortable() {
        const tbody = document.getElementById('tabla-header-texts');
        if (!tbody) return;
        Sortable.create(tbody, {
            handle: '.drag-handle',
            animation: 150,
            ghostClass: 'bg-emerald-50',
            onEnd: () => this.reordenar(),
        });
    },

    bindEvents() {
        document.addEventListener('click', (e) => {
            const crear = e.target.closest('#btn-crear-header-text');
            if (crear) {
                e.preventDefault();
                this.crear();
                return;
            }

            const editar = e.target.closest('.btn-editar');
            if (editar) {
                e.preventDefault();
                this.editar(editar.dataset.headerTextId);
                return;
            }

            const eliminar = e.target.closest('.btn-eliminar');
            if (eliminar) {
                e.preventDefault();
                this.eliminar(eliminar.dataset.headerTextId);
                return;
            }
        });
    },

    async crear() {
        try {
            const res = await axios.get('/admin/header-text/create');
            const container = document.getElementById('modal-container');
            const overlay = document.getElementById('modal-overlay');
            if (container) container.innerHTML = res.data;
            if (overlay) overlay.classList.remove('hidden');
        } catch (err) {
            console.error('Error al abrir crear texto:', err);
        }
    },

    async editar(id) {
        try {
            const res = await axios.get(`/admin/header-text/${id}/edit`);
            const container = document.getElementById('modal-container');
            const overlay = document.getElementById('modal-overlay');
            if (container) container.innerHTML = res.data;
            if (overlay) overlay.classList.remove('hidden');
        } catch (err) {
            console.error('Error al abrir editar texto:', err);
        }
    },

    async eliminar(id) {
        if (!confirm('¿Estás seguro de eliminar este texto?')) return;

        try {
            const res = await axios.delete(`/admin/header-text/${id}`);
            if (res.data.success) {
                await this.recargar();
                if (window.AdminToast) {
                    AdminToast.show(res.data.message, 'success');
                }
            }
        } catch (err) {
            console.error('Error al eliminar texto:', err);
            if (window.AdminToast) {
                AdminToast.show('Error al eliminar el texto', 'error');
            }
        }
    },

    async reordenar() {
        const ids = Array.from(
            document.querySelectorAll('#tabla-header-texts tr[data-header-text-id]')
        ).map(tr => parseInt(tr.dataset.headerTextId));

        if (ids.length === 0) return;

        try {
            await axios.post('/admin/header-text/reordenar', { items: ids });
        } catch (err) {
            console.error('Error al reordenar:', err);
        }
    },

    async recargar() {
        try {
            const res = await axios.get('/admin/header-text');
            const panel = document.getElementById('panel-content');
            if (panel) panel.innerHTML = res.data;
        } catch (err) {
            console.error('Error al recargar textos:', err);
        }
    },
};

HeaderTextManager.init();
