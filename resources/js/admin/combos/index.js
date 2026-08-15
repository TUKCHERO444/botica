import axios from 'axios';

const ComboManager = {
    rowIndex: 0,

    init() {
        if (!document._combosInit) {
            this.bindEvents();
            document._combosInit = true;
        }
        this.initPreview();
        this.initExistingRows();
    },

    bindEvents() {
        document.addEventListener('click', (e) => {
            const crear = e.target.closest('#btn-crear-combo');
            if (crear) {
                e.preventDefault();
                this.crear();
                return;
            }

            const editar = e.target.closest('.btn-editar');
            if (editar) {
                e.preventDefault();
                this.editar(editar.dataset.comboId);
                return;
            }

            const eliminar = e.target.closest('.btn-eliminar');
            if (eliminar) {
                e.preventDefault();
                this.eliminar(eliminar.dataset.comboId);
                return;
            }

            const quitar = e.target.closest('.btn-quitar-producto');
            if (quitar) {
                e.preventDefault();
                quitar.closest('.producto-row').remove();
                this.recalcularPrecio();
                return;
            }
        });

        document.addEventListener('input', (e) => {
            const input = e.target.closest('#buscar-producto-input');
            if (input) {
                this.buscarProductos(input.value);
            }

            const cantidad = e.target.closest('.producto-cantidad');
            if (cantidad) {
                this.recalcularPrecio();
            }
        });

        document.addEventListener('change', (e) => {
            const precio = e.target.closest('#precio-sugerido');
            if (precio) return;
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const resultados = document.getElementById('resultados-busqueda');
                if (resultados) resultados.classList.add('hidden');
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

    initExistingRows() {
        const rows = document.querySelectorAll('.producto-row');
        rows.forEach((row, i) => {
            this.renombrarInputs(row, i);
        });
        this.rowIndex = rows.length;
        this.recalcularPrecio();
    },

    renombrarInputs(row, index) {
        row.querySelectorAll('[name*="[id]"], [name*="[cantidad]"]').forEach((input) => {
            const name = input.getAttribute('name');
            if (name) {
                input.setAttribute('name', name.replace(/\[\d+\]/g, `[${index}]`));
            }
        });
    },

    async buscarProductos(q) {
        const resultados = document.getElementById('resultados-busqueda');
        if (!resultados) return;

        if (q.length < 2) {
            resultados.classList.add('hidden');
            return;
        }

        try {
            const res = await axios.get(`/admin/combos/buscar-productos?q=${encodeURIComponent(q)}`);
            const productos = res.data;

            if (productos.length === 0) {
                resultados.classList.add('hidden');
                return;
            }

            resultados.innerHTML = productos.map((p) => `
                <div class="resultado-item px-3 py-2 hover:bg-emerald-50 cursor-pointer text-sm border-b border-stone-100 flex items-center gap-2"
                     data-id="${p.id_producto}" data-nombre="${p.nombre}" data-precio="${p.precio_venta}" data-foto="${p.foto_url || ''}">
                    <img src="${p.foto_url || ''}" alt="" class="w-8 h-8 object-cover rounded"
                        onerror="this.style.display='none'">
                    <span class="flex-1">${p.nombre}</span>
                    <span class="text-stone-400 text-xs">S/ ${parseFloat(p.precio_venta).toFixed(2)}</span>
                </div>
            `).join('');

            resultados.classList.remove('hidden');

            resultados.querySelectorAll('.resultado-item').forEach((item) => {
                item.addEventListener('click', () => {
                    this.agregarProducto(
                        parseInt(item.dataset.id),
                        item.dataset.nombre,
                        parseFloat(item.dataset.precio),
                        item.dataset.foto
                    );
                    resultados.classList.add('hidden');
                    document.getElementById('buscar-producto-input').value = '';
                });
            });
        } catch (err) {
            console.error('Error al buscar productos:', err);
        }
    },

    agregarProducto(id, nombre, precio, foto) {
        const container = document.getElementById('productos-seleccionados');
        const idx = this.rowIndex++;

        const existing = container.querySelector(`.producto-row[data-id="${id}"]`);
        if (existing) {
            const input = existing.querySelector('.producto-cantidad');
            input.value = parseInt(input.value) + 1;
            this.recalcularPrecio();
            return;
        }

        const row = document.createElement('div');
        row.className = 'producto-row flex items-center gap-2 p-2 bg-stone-50 rounded-lg';
        row.dataset.id = id;
        row.dataset.precio = precio;

        row.innerHTML = `
            <input type="hidden" name="productos[${idx}][id]" value="${id}">
            <img src="${foto || ''}" alt="" class="w-10 h-10 object-cover rounded"
                onerror="this.style.display='none'">
            <span class="flex-1 text-sm text-stone-700">${nombre}</span>
            <span class="text-xs text-stone-400">S/ ${precio.toFixed(2)}</span>
            <input type="number" name="productos[${idx}][cantidad]" value="1" min="1"
                class="producto-cantidad w-16 text-center rounded border border-stone-300 px-1 py-1 text-xs">
            <span class="producto-subtotal text-xs text-stone-500">S/ ${precio.toFixed(2)}</span>
            <button type="button" class="btn-quitar-producto text-red-500 hover:text-red-700 text-xs font-medium">Quitar</button>
        `;

        container.appendChild(row);
        this.recalcularPrecio();
    },

    recalcularPrecio() {
        const rows = document.querySelectorAll('.producto-row');
        let totalOriginal = 0;

        rows.forEach((row) => {
            const precio = parseFloat(row.dataset.precio) || 0;
            const cantidad = parseInt(row.querySelector('.producto-cantidad')?.value) || 1;
            const subtotal = precio * cantidad;
            totalOriginal += subtotal;

            const subEl = row.querySelector('.producto-subtotal');
            if (subEl) subEl.textContent = `S/ ${subtotal.toFixed(2)}`;
        });

        const sugerido = totalOriginal * 0.9;
        const precioInput = document.getElementById('precio-sugerido');
        const precioOrigEl = document.getElementById('precio-original');

        if (precioInput && !precioInput.dataset.userEdited) {
            precioInput.value = sugerido.toFixed(2);
        }

        if (precioOrigEl) {
            if (totalOriginal > 0) {
                precioOrigEl.textContent = `Suma original: S/ ${totalOriginal.toFixed(2)}`;
            } else {
                precioOrigEl.textContent = '';
            }
        }

        const msg = document.getElementById('sin-productos-msg');
        if (msg) {
            if (rows.length === 0) {
                msg.classList.remove('hidden');
            } else {
                msg.classList.add('hidden');
            }
        }
    },

    async crear() {
        try {
            const res = await axios.get('/admin/combos/create');
            const container = document.getElementById('modal-container');
            const overlay = document.getElementById('modal-overlay');
            if (container) container.innerHTML = res.data;
            if (overlay) overlay.classList.remove('hidden');
        } catch (err) {
            console.error('Error al abrir crear combo:', err);
        }
    },

    async editar(id) {
        try {
            const res = await axios.get(`/admin/combos/${id}/edit`);
            const container = document.getElementById('modal-container');
            const overlay = document.getElementById('modal-overlay');
            if (container) container.innerHTML = res.data;
            if (overlay) overlay.classList.remove('hidden');
        } catch (err) {
            console.error('Error al abrir editar combo:', err);
        }
    },

    async eliminar(id) {
        if (!confirm('¿Estás seguro de eliminar este combo?')) return;

        try {
            const res = await axios.delete(`/admin/combos/${id}`);
            if (res.data.success) {
                await this.recargar();
                if (window.AdminToast) {
                    AdminToast.show(res.data.message, 'success');
                }
            }
        } catch (err) {
            console.error('Error al eliminar combo:', err);
            if (window.AdminToast) {
                AdminToast.show('Error al eliminar el combo', 'error');
            }
        }
    },

    async recargar() {
        try {
            const res = await axios.get('/admin/combos');
            const panel = document.getElementById('panel-content');
            if (panel) panel.innerHTML = res.data;
        } catch (err) {
            console.error('Error al recargar combos:', err);
        }
    },
};

ComboManager.init();
