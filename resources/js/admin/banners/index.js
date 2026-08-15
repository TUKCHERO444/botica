import axios from 'axios';

const BannerManager = {
    init() {
        if (document._bannersInit) return;
        document._bannersInit = true;
        this.bindEvents();
        this.initPreview();
    },

    bindEvents() {
        document.addEventListener('click', (e) => {
            const crear = e.target.closest('#btn-crear-banner');
            if (crear) {
                e.preventDefault();
                this.crear();
                return;
            }

            const editar = e.target.closest('.btn-editar');
            if (editar) {
                e.preventDefault();
                this.editar(editar.dataset.bannerId);
                return;
            }

            const eliminar = e.target.closest('.btn-eliminar');
            if (eliminar) {
                e.preventDefault();
                this.eliminar(eliminar.dataset.bannerId);
                return;
            }
        });

        document.addEventListener('change', (e) => {
            const input = e.target.closest('.input-orden');
            if (input) {
                this.reordenar();
            }
        });

    },

    initPreview() {
        document.addEventListener('change', (e) => {
            if (e.target.id !== 'imagen') return;
            const file = e.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = (event) => {
                const preview = document.getElementById('preview-imagen');
                const bloque = document.getElementById('bloque-preview');
                if (preview) preview.src = event.target.result;
                if (bloque) bloque.classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        });
    },

    async crear() {
        try {
            const res = await axios.get('/admin/banners/create');
            const container = document.getElementById('modal-container');
            const overlay = document.getElementById('modal-overlay');
            if (container) container.innerHTML = res.data;
            if (overlay) overlay.classList.remove('hidden');
        } catch (err) {
            console.error('Error al abrir crear banner:', err);
        }
    },

    async editar(id) {
        try {
            const res = await axios.get(`/admin/banners/${id}/edit`);
            const container = document.getElementById('modal-container');
            const overlay = document.getElementById('modal-overlay');
            if (container) container.innerHTML = res.data;
            if (overlay) overlay.classList.remove('hidden');
        } catch (err) {
            console.error('Error al abrir editar banner:', err);
        }
    },

    async eliminar(id) {
        if (!confirm('¿Estás seguro de eliminar este banner?')) return;

        try {
            const res = await axios.delete(`/admin/banners/${id}`);
            if (res.data.success) {
                await this.recargar();
                if (window.AdminToast) {
                    AdminToast.show(res.data.message, 'success');
                }
            }
        } catch (err) {
            console.error('Error al eliminar banner:', err);
            if (window.AdminToast) {
                AdminToast.show('Error al eliminar el banner', 'error');
            }
        }
    },

    async reordenar() {
        const inputs = document.querySelectorAll('.input-orden');
        const items = Array.from(inputs)
            .map(input => ({
                id: parseInt(input.dataset.bannerId),
                orden: parseInt(input.value) || 0,
            }))
            .sort((a, b) => a.orden - b.orden)
            .map(item => item.id);

        try {
            await axios.post('/admin/banners/reordenar', { items });
        } catch (err) {
            console.error('Error al reordenar:', err);
        }
    },

    async recargar() {
        try {
            const res = await axios.get('/admin/banners');
            const panel = document.getElementById('panel-content');
            if (panel) panel.innerHTML = res.data;
        } catch (err) {
            console.error('Error al recargar banners:', err);
        }
    },
};

BannerManager.init();
