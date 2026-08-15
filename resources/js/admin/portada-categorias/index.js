import axios from 'axios';

const PortadaCategoriasManager = {
    init() {
        if (document._pcInit) return;
        document._pcInit = true;
        this.bindEvents();
    },

    bindEvents() {
        document.addEventListener('click', (e) => {
            const toggle = e.target.closest('[data-toggle-portada]');
            if (toggle) {
                e.preventDefault();
                this.toggle(toggle.dataset.togglePortada);
            }
        });

        document.addEventListener('change', (e) => {
            const input = e.target.closest('.input-orden-portada');
            if (input) {
                this.reordenar();
            }
        });
    },

    async toggle(categoriaId) {
        try {
            const res = await axios.post(`/admin/portada-categorias/toggle/${categoriaId}`);
            if (res.data.success) {
                await this.recargar();
                if (window.AdminToast) {
                    AdminToast.show(res.data.message, 'success');
                }
            }
        } catch (err) {
            if (window.AdminToast) {
                AdminToast.show('Error al cambiar estado', 'error');
            }
        }
    },

    async reordenar() {
        const inputs = document.querySelectorAll('.input-orden-portada');
        const items = Array.from(inputs)
            .map(input => ({
                id: parseInt(input.dataset.pcId),
                orden: parseInt(input.value) || 0,
            }))
            .sort((a, b) => a.orden - b.orden)
            .map(item => item.id);

        if (items.length === 0) return;

        try {
            await axios.post('/admin/portada-categorias/reordenar', { items });
        } catch (err) {
            console.error('Error al reordenar:', err);
        }
    },

    async recargar() {
        try {
            const res = await axios.get('/admin/portada-categorias');
            const panel = document.getElementById('panel-content');
            if (panel) panel.innerHTML = res.data;
        } catch (err) {
            console.error('Error al recargar:', err);
        }
    },
};

PortadaCategoriasManager.init();
