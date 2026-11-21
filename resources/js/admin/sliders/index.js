import axios from 'axios';

let sliderTipoActual = 'buscados';

function cargarTab(tipo, page = 1) {
    sliderTipoActual = tipo;
    const content = document.getElementById('slider-content');
    content.innerHTML = '<p class="text-sm text-stone-400 py-8 text-center">Cargando...</p>';

    axios.get(`/admin/sliders/productos/${tipo}`, { params: { page } })
        .then(res => {
            content.innerHTML = res.data;
            document.getElementById('slider-count').textContent =
                `${content.querySelectorAll('tbody tr').length} productos`;
        })
        .catch(() => {
            content.innerHTML = '<p class="text-sm text-red-500 py-8 text-center">Error al cargar productos.</p>';
        });
}

function abrirModal() {
    document.getElementById('modal-agregar').classList.remove('hidden');
    document.getElementById('buscar-producto-input').value = '';
    document.getElementById('resultados-busqueda').innerHTML =
        '<p class="text-sm text-stone-400 text-center py-4">Escribe para buscar productos.</p>';
    document.getElementById('buscar-producto-input').focus();
}

function cerrarModal() {
    document.getElementById('modal-agregar').classList.add('hidden');
}

function buscarProductos(q) {
    const container = document.getElementById('resultados-busqueda');
    if (q.length < 2) {
        container.innerHTML = '<p class="text-sm text-stone-400 text-center py-4">Escribe al menos 2 caracteres.</p>';
        return;
    }

    container.innerHTML = '<p class="text-sm text-stone-400 text-center py-4">Buscando...</p>';

    axios.get(`/admin/sliders/buscar-productos?q=${encodeURIComponent(q)}`)
        .then(res => {
            const productos = res.data;
            if (productos.length === 0) {
                container.innerHTML = '<p class="text-sm text-stone-400 text-center py-4">Sin resultados.</p>';
                return;
            }

            container.innerHTML = productos.map(p => `
                <div class="flex items-center gap-3 p-2 rounded-lg hover:bg-stone-50 transition cursor-pointer producto-item"
                     data-producto-id="${p.id_producto}">
                    ${p.foto_url
                        ? `<img src="${p.foto_url}" alt="" class="w-10 h-10 object-cover rounded-lg border border-stone-200">`
                        : `<span class="w-10 h-10 flex items-center justify-center bg-stone-100 rounded-lg text-stone-400 text-xs">—</span>`
                    }
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-stone-800 truncate">${p.nombre}</p>
                        <p class="text-xs text-stone-500">${p.categoria || '—'} · S/ ${parseFloat(p.precio_venta).toFixed(2)}</p>
                    </div>
                    <button type="button" class="btn-asignar px-3 py-1 text-xs bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">
                        Asignar
                    </button>
                </div>
            `).join('');
        })
        .catch(() => {
            container.innerHTML = '<p class="text-sm text-red-500 text-center py-4">Error al buscar.</p>';
        });
}

function asignarProducto(productoId) {
    axios.post(`/admin/sliders/asignar/${sliderTipoActual}`, { producto_id: productoId })
        .then(res => {
            if (res.data.success) {
                cerrarModal();
                cargarTab(sliderTipoActual);
            }
        })
        .catch(err => {
            const msg = err.response?.data?.message || 'Error al asignar producto';
            alert(msg);
        });
}

function quitarProducto(productoId) {
    if (!confirm('¿Quitar este producto del slider?')) return;

    axios.delete(`/admin/sliders/quitar/${sliderTipoActual}/${productoId}`)
        .then(res => {
            if (res.data.success) {
                cargarTab(sliderTipoActual);
            }
        })
        .catch(() => {
            alert('Error al quitar producto');
        });
}

function reordenar() {
    const inputs = document.querySelectorAll('.input-orden');
    const items = Array.from(inputs).map(input => ({
        producto_id: parseInt(input.dataset.productoId),
        orden: parseInt(input.value) || 0,
    }));

    axios.post(`/admin/sliders/reordenar/${sliderTipoActual}`, { items })
        .catch(() => {
            alert('Error al actualizar orden');
        });
}

let debounceTimer;
let reordenarTimer;

function init() {
    cargarTab('buscados');

    document.addEventListener('click', (e) => {
        const tab = e.target.closest('[data-slider-tab]');
        if (tab) {
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('text-emerald-700', 'border-emerald-600');
                btn.classList.add('text-stone-500', 'border-transparent');
            });
            tab.classList.remove('text-stone-500', 'border-transparent');
            tab.classList.add('text-emerald-700', 'border-emerald-600');
            cargarTab(tab.dataset.sliderTab);
        }
    });

    document.addEventListener('click', (e) => {
        if (e.target.closest('#btn-agregar')) {
            abrirModal();
        }
    });

    document.addEventListener('click', (e) => {
        if (e.target.closest('.cerrar-modal')) {
            cerrarModal();
        }
    });

    document.addEventListener('click', (e) => {
        if (e.target === document.getElementById('modal-agregar')) {
            cerrarModal();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') cerrarModal();
    });

    const searchInput = document.getElementById('buscar-producto-input');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                buscarProductos(searchInput.value);
            }, 300);
        });
    }

    document.addEventListener('click', (e) => {
        const pageBtn = e.target.closest('[data-slider-page]');
        if (pageBtn) {
            cargarTab(sliderTipoActual, parseInt(pageBtn.dataset.sliderPage, 10) || 1);
        }
    });

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-asignar');
        if (btn) {
            const item = btn.closest('.producto-item');
            if (item) asignarProducto(item.dataset.productoId);
        }
    });

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-quitar');
        if (btn) {
            quitarProducto(btn.dataset.productoId);
        }
    });

    document.addEventListener('input', (e) => {
        if (e.target.closest('.input-orden')) {
            clearTimeout(reordenarTimer);
            reordenarTimer = setTimeout(reordenar, 500);
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
