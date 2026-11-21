import axios from 'axios';
import AdminRouter from '../router';
import AdminModal from '../modal';
import { buildProductoSearch } from '../producto-search.js';

function reindexLineas() {
    const filas = document.querySelectorAll('[data-lineas] .linea-row');

    filas.forEach((fila, indice) => {
        fila.querySelectorAll('[name]').forEach((input) => {
            input.name = input.name.replace(/lineas\[\d+\]/, `lineas[${indice}]`);
        });

        fila.querySelectorAll('.producto-search').forEach((search) => {
            search.dataset.field = `lineas[${indice}][producto_presentacion_id]`;
        });
    });
}

function agregarLinea() {
    const tbody = document.querySelector('[data-lineas]');
    const primera = tbody?.querySelector('.linea-row');

    if (!tbody || !primera) return;

    const clon = primera.cloneNode(true);

    clon.querySelectorAll('input').forEach((input) => {
        input.value = '';
        input.classList.remove('border-red-500', 'is-invalid');
    });

    const buscador = clon.querySelector('.producto-search');

    if (buscador) {
        delete buscador.dataset.built;
        delete buscador.dataset.initialId;
        delete buscador.dataset.initialLabel;
        delete buscador.dataset.initialPrecio;
        delete buscador.dataset.initialStock;
        delete buscador.dataset.initialVentaLabel;
        buscador.innerHTML = '';
    }

    clon.querySelectorAll('.field-error').forEach((error) => error.remove());

    tbody.appendChild(clon);
    reindexLineas();

    if (buscador) {
        buildProductoSearch(buscador);
    }
}

function eliminarLinea(boton) {
    const fila = boton.closest('.linea-row');
    const filas = document.querySelectorAll('[data-lineas] .linea-row');

    if (!fila) return;

    if (filas.length === 1) {
        fila.querySelectorAll('input').forEach((input) => (input.value = ''));
        fila.querySelectorAll('select').forEach((select) => (select.value = ''));
        return;
    }

    fila.remove();
    reindexLineas();
}

function renderResultado(contenedor, data, dryRun) {
    contenedor.innerHTML = '';

    const resumen = data.resumen ?? null;

    if (resumen) {
        const plural = (cantidad, singular, pluralTexto) => (
            `${cantidad} ${Number(cantidad) === 1 ? singular : pluralTexto}`
        );

        const bloque = document.createElement('div');
        bloque.className = 'rounded-lg border border-stone-200 bg-stone-50 p-3 mb-3 text-stone-700';
        bloque.textContent = `${plural(resumen.lineas, 'línea', 'líneas')} · `
            + `${plural(resumen.productos_nuevos, 'producto nuevo', 'productos nuevos')} · `
            + `${plural(resumen.productos_existentes, 'producto existente', 'productos existentes')} · `
            + `costo S/ ${resumen.costo_total}`;
        contenedor.appendChild(bloque);
    }

    if (data.success) {
        const aviso = document.createElement('div');
        aviso.className = 'rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-emerald-800';
        aviso.textContent = data.message;
        contenedor.appendChild(aviso);
    } else if (data.message) {
        const aviso = document.createElement('div');
        aviso.className = 'rounded-lg border border-red-200 bg-red-50 p-3 text-red-800';
        aviso.textContent = data.message;
        contenedor.appendChild(aviso);
    }

    if (data.errors && typeof data.errors === 'object') {
        const lista = document.createElement('ul');
        lista.className = 'mt-3 text-xs text-red-700 space-y-1';

        Object.values(data.errors).flat().slice(0, 20).forEach((mensaje) => {
            const item = document.createElement('li');
            item.textContent = String(mensaje);
            lista.appendChild(item);
        });

        contenedor.appendChild(lista);
    }

    if (Array.isArray(data.advertencias) && data.advertencias.length > 0) {
        const lista = document.createElement('ul');
        lista.className = 'mt-3 text-xs text-amber-700 space-y-1';

        data.advertencias.slice(0, 20).forEach((advertencia) => {
            const item = document.createElement('li');
            item.textContent = `Fila ${advertencia.fila} (${advertencia.campo}): ${advertencia.mensaje}`;
            lista.appendChild(item);
        });

        contenedor.appendChild(lista);
    }

    if (Array.isArray(data.errores) && data.errores.length > 0) {
        const lista = document.createElement('ul');
        lista.className = 'mt-3 text-xs text-red-700 space-y-1 max-h-48 overflow-y-auto';

        data.errores.slice(0, 50).forEach((error) => {
            const item = document.createElement('li');
            item.textContent = `Fila ${error.fila} (${error.campo}): ${error.mensaje}`;
            lista.appendChild(item);
        });

        contenedor.appendChild(lista);
    }

    if (data.success && dryRun) {
        const nota = document.createElement('p');
        nota.className = 'mt-3 text-xs text-stone-500';
        nota.textContent = 'No se guardó nada. Usa “Importar como borrador” cuando el archivo esté correcto.';
        contenedor.appendChild(nota);
    }
}

async function importarCsv(boton) {
    const contenedor = boton.closest('[data-csv-form]');
    const archivo = contenedor?.querySelector('[data-csv-archivo]')?.files?.[0];
    const resultado = contenedor?.querySelector('[data-csv-resultado]');
    const botones = contenedor?.querySelectorAll('[data-csv-validar]') ?? [];

    if (!contenedor || !resultado) return;

    if (!archivo) {
        window.AdminToast?.show('Selecciona un archivo CSV.', 'error');
        return;
    }

    const dryRun = boton.dataset.dryRun === '1';
    const data = new FormData();
    data.append('archivo', archivo);
    data.append('dry_run', dryRun ? '1' : '0');

    const textos = [];

    botones.forEach((item) => {
        textos.push(item.textContent);
        item.disabled = true;
        item.textContent = dryRun ? 'Validando...' : 'Importando...';
    });

    try {
        const response = await axios.post(contenedor.dataset.importUrl, data, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        renderResultado(resultado, response.data, dryRun);

        if (response.data.success && !dryRun) {
            window.AdminToast?.show(response.data.message, 'success');
            AdminModal.close();
            await AdminRouter.load(window.location.href, false);
        }
    } catch (error) {
        const data = error.response?.data;

        if (data) {
            renderResultado(resultado, data, dryRun);
        } else {
            window.AdminToast?.show('Error al importar el CSV.', 'error');
        }
    } finally {
        botones.forEach((item, indice) => {
            item.disabled = false;
            item.textContent = textos[indice];
        });
    }
}

document.addEventListener('click', (event) => {
    if (event.target.closest('[data-agregar-linea]')) {
        agregarLinea();
        return;
    }

    const eliminar = event.target.closest('[data-eliminar-linea]');

    if (eliminar) {
        eliminarLinea(eliminar);
        return;
    }

    const importar = event.target.closest('[data-csv-validar]');

    if (importar) {
        importarCsv(importar);
    }
});

document.addEventListener('producto:selected', (event) => {
    const search = event.target.closest('.producto-search');
    const fila = search?.closest('.linea-row');
    const costo = fila?.querySelector('.costo-input');
    const precioVenta = fila?.querySelector('.precio-venta-input');

    if (costo) {
        costo.value = event.detail?.precio ?? '';
    }

    if (precioVenta) {
        precioVenta.value = event.detail?.precio_venta_formateado ?? '';
    }

    search?.querySelector('.producto-search-warning')?.remove();

    if (search && event.detail?.elegible === false) {
        const aviso = document.createElement('p');
        aviso.className = 'producto-search-warning text-[11px] text-amber-700 mt-1';
        aviso.textContent = 'Este producto ya tiene stock o lotes; el guardado será bloqueado.';
        search.appendChild(aviso);
    }
});

document.addEventListener('producto:cleared', (event) => {
    const search = event.target.closest('.producto-search');
    const fila = search?.closest('.linea-row');
    const costo = fila?.querySelector('.costo-input');
    const precioVenta = fila?.querySelector('.precio-venta-input');

    if (costo) costo.value = '';
    if (precioVenta) precioVenta.value = '';
    search?.querySelector('.producto-search-warning')?.remove();
});

function initBuscadoresProducto() {
    document.querySelectorAll('.producto-search:not([data-built="1"])').forEach(buildProductoSearch);
}

const modalContainer = document.getElementById('modal-container');

if (modalContainer) {
    new MutationObserver(initBuscadoresProducto).observe(modalContainer, {
        childList: true,
    });
}

initBuscadoresProducto();
