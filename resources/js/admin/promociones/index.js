import axios from 'axios';
import Sortable from 'sortablejs';

const PromocionManager = {
    init() {
        if (!document._promocionesInit) {
            this.bindEvents();
            document._promocionesInit = true;
        }
        this.initSortable();
        this.initPreview();
    },

    initSortable() {
        const tbody = document.getElementById('tabla-promociones');
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
            const crear = e.target.closest('#btn-crear-promocion');
            if (crear) {
                e.preventDefault();
                this.crear();
                return;
            }

            const editar = e.target.closest('.btn-editar');
            if (editar) {
                e.preventDefault();
                this.editar(editar.dataset.promocionId);
                return;
            }

            const eliminar = e.target.closest('.btn-eliminar');
            if (eliminar) {
                e.preventDefault();
                this.eliminar(eliminar.dataset.promocionId);
                return;
            }
        });

        document.addEventListener('change', (e) => {
            const tipoPromocion = e.target.closest('#tipo_promocion');
            if (tipoPromocion) {
                this.toggleCampos(tipoPromocion.value);
            }

            const ambito = e.target.closest('#ambito');
            if (ambito) {
                document.getElementById('ambito_id').value = '';
                document.getElementById('entidad-seleccionada')?.classList.add('hidden');
                document.getElementById('buscar-entidad-input').value = '';
            }
        });

        document.addEventListener('input', (e) => {
            const input = e.target.closest('#buscar-entidad-input');
            if (input) {
                this.buscarEntidad(input.value);
            }

            const precioSugerido = e.target.closest('#precio-sugerido');
            if (precioSugerido) {
                precioSugerido.dataset.userEdited = 'true';
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const resultados = document.getElementById('resultados-entidad');
                if (resultados) resultados.classList.add('hidden');
            }
        });
    },

    toggleCampos(tipo) {
        const descuento = document.getElementById('campos-descuento');
        const combo = document.getElementById('campos-combo');
        const enlace = document.getElementById('campos-enlace');

        if (descuento) descuento.classList.toggle('hidden', tipo !== 'descuento');
        if (combo) combo.classList.toggle('hidden', tipo !== 'combo');

        if (enlace) {
            if (tipo === '') {
                enlace.classList.remove('hidden');
            } else {
                enlace.classList.add('hidden');
            }
        }
    },

    async buscarEntidad(q) {
        const ambito = document.getElementById('ambito')?.value;
        const resultados = document.getElementById('resultados-entidad');
        if (!resultados || !ambito) return;

        if (q.length < 2) {
            resultados.classList.add('hidden');
            return;
        }

        try {
            const res = await axios.get(`/admin/promociones/buscar-entidad?ambito=${ambito}&q=${encodeURIComponent(q)}`);
            const items = res.data;

            if (items.length === 0) {
                resultados.classList.add('hidden');
                return;
            }

            resultados.innerHTML = items.map((item) => `
                <div class="resultado-item px-3 py-2 hover:bg-emerald-50 cursor-pointer text-sm border-b border-stone-100"
                     data-id="${item.id}" data-text="${item.text}">
                    ${item.text}
                </div>
            `).join('');

            resultados.classList.remove('hidden');

            resultados.querySelectorAll('.resultado-item').forEach((item) => {
                item.addEventListener('click', () => {
                    document.getElementById('ambito_id').value = item.dataset.id;
                    document.getElementById('buscar-entidad-input').value = item.dataset.text;
                    const label = document.getElementById('entidad-seleccionada');
                    if (label) {
                        label.textContent = `Seleccionado: ${item.dataset.text}`;
                        label.classList.remove('hidden');
                    }
                    resultados.classList.add('hidden');
                });
            });
        } catch (err) {
            console.error('Error al buscar entidad:', err);
        }
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
            const res = await axios.get('/admin/promociones/create');
            const container = document.getElementById('modal-container');
            const overlay = document.getElementById('modal-overlay');
            if (container) container.innerHTML = res.data;
            window.AdminModal?.applySizeFromContent();
            if (overlay) overlay.classList.remove('hidden');
        } catch (err) {
            console.error('Error al abrir crear promoción:', err);
        }
    },

    async editar(id) {
        try {
            const res = await axios.get(`/admin/promociones/${id}/edit`);
            const container = document.getElementById('modal-container');
            const overlay = document.getElementById('modal-overlay');
            if (container) container.innerHTML = res.data;
            window.AdminModal?.applySizeFromContent();
            if (overlay) overlay.classList.remove('hidden');
        } catch (err) {
            console.error('Error al abrir editar promoción:', err);
        }
    },

    async eliminar(id) {
        if (!confirm('¿Estás seguro de eliminar esta promoción?')) return;

        try {
            const res = await axios.delete(`/admin/promociones/${id}`);
            if (res.data.success) {
                await this.recargar();
                if (window.AdminToast) {
                    AdminToast.show(res.data.message, 'success');
                }
            }
        } catch (err) {
            console.error('Error al eliminar promoción:', err);
            if (window.AdminToast) {
                AdminToast.show('Error al eliminar la promoción', 'error');
            }
        }
    },

    async reordenar() {
        const ids = Array.from(
            document.querySelectorAll('#tabla-promociones tr[data-promocion-id]')
        ).map(tr => parseInt(tr.dataset.promocionId));

        try {
            await axios.post('/admin/promociones/reordenar', { items: ids });
        } catch (err) {
            console.error('Error al reordenar:', err);
        }
    },

    async recargar() {
        try {
            const res = await axios.get('/admin/promociones');
            const panel = document.getElementById('panel-content');
            if (panel) panel.innerHTML = res.data;
        } catch (err) {
            console.error('Error al recargar promociones:', err);
        }
    },
};

PromocionManager.init();
