import { desglosarCantidad, formatearCantidad } from '../presentacion-stock.js';
import { buildLoteSearch } from './lote-search.js';

function initAjusteForm() {
  const container = document.getElementById('lotes-container');
  const btnAgregar = document.getElementById('agregar-lote');
  const tipo = document.getElementById('ajuste-tipo');
  const totalEl = document.getElementById('ajuste-total');

  if (!container || !btnAgregar) return;

  let index = container.querySelectorAll('.lote-row').length;

  function parsePresentaciones(valor) {
    try {
      return JSON.parse(valor || '[]');
    } catch (error) {
      return [];
    }
  }

  function datosFila(row) {
    return {
      loteId: row.dataset.loteId || '',
      disponible: parseFloat(row.dataset.disponible || '0') || 0,
      costo: parseFloat(row.dataset.costo || '0') || 0,
      unidad: row.dataset.unidad || '',
      desglose: row.dataset.desglose || '',
      presentaciones: parsePresentaciones(row.dataset.presentaciones),
    };
  }

  function actualizarTotal() {
    if (!totalEl) return;

    let total = 0;

    container.querySelectorAll('.lote-row').forEach((row) => {
      const input = row.querySelector('.lote-cantidad');
      const datos = datosFila(row);

      if (!datos.loteId || !input) return;

      const cantidad = parseFloat(input.value || '0');

      let cantidadAjuste = cantidad;

      if (tipo?.value === 'conteo_fisico') {
        cantidadAjuste = Math.abs(cantidad - datos.disponible);
      }

      if (Number.isFinite(cantidadAjuste) && Number.isFinite(datos.costo)) {
        total += cantidadAjuste * datos.costo;
      }
    });

    totalEl.textContent = total.toFixed(2);
  }

  function actualizarInfo(row) {
    const info = row.querySelector('.lote-info');
    const equivalencia = row.querySelector('.lote-equivalencia');
    const cantidadInput = row.querySelector('.lote-cantidad');
    const datos = datosFila(row);

    if (!datos.loteId) {
      if (info) {
        info.textContent = '';
        info.classList.add('hidden');
      }

      if (equivalencia) {
        equivalencia.textContent = '';
        equivalencia.classList.add('hidden');
      }

      return;
    }

    if (info) {
      info.textContent = 'Disponible en el lote: ' + formatearCantidad(datos.disponible)
        + (datos.unidad ? ' ' + datos.unidad : '')
        + (datos.desglose ? ' · ' + datos.desglose : '');
      info.classList.remove('hidden');
    }

    if (!equivalencia) return;

    const cantidadActual = parseFloat(cantidadInput?.value || '0') || 0;

    if (cantidadActual > 0) {
      const texto = desglosarCantidad(cantidadActual, datos.presentaciones, datos.unidad);

      equivalencia.textContent = 'Cantidad: ' + formatearCantidad(cantidadActual)
        + (datos.unidad ? ' ' + datos.unidad : '')
        + (texto ? ' = ' + texto : '');
      equivalencia.classList.remove('hidden');
    } else {
      equivalencia.textContent = '';
      equivalencia.classList.add('hidden');
    }
  }

  function actualizarLabels() {
    const valor = tipo?.value;
    let label = 'Cantidad (unidad base)';
    let help = 'Ingresa la cantidad en unidad base.';

    if (valor === 'conteo_fisico') {
      label = 'Stock físico contado (unidad base)';
      help = 'Ingresa el stock final contado. El sistema calcula la diferencia contra lo disponible.';
    } else if (valor === 'ajuste_positivo') {
      label = 'Cantidad a sumar (unidad base)';
      help = 'La cantidad se suma al lote seleccionado y crea una nueva capa de ajuste.';
    } else if (['dano', 'vencimiento', 'consumo_interno', 'otro'].includes(valor)) {
      label = 'Cantidad a restar (unidad base)';
      help = 'La cantidad se descuenta del lote seleccionado.';
    }

    document.querySelectorAll('.ajuste-cantidad-label').forEach((element) => {
      element.innerHTML = label + ' <span class="text-red-500">*</span>';
    });

    const helpEl = document.getElementById('ajuste-help');
    if (helpEl) helpEl.textContent = help;

    actualizarTotal();
  }

  function configurar(row) {
    const cantidad = row.querySelector('.lote-cantidad');
    const search = row.querySelector('.lote-search');

    row.addEventListener('lote:selected', (event) => {
      const data = event.detail || {};

      row.dataset.loteId = data.id || '';
      row.dataset.disponible = data.disponible ?? 0;
      row.dataset.unidad = data.unidad || '';
      row.dataset.desglose = data.desglose || '';
      row.dataset.presentaciones = JSON.stringify(data.presentaciones || []);
      row.dataset.costo = data.costo ?? 0;

      actualizarInfo(row);
      actualizarTotal();
    });

    row.addEventListener('lote:cleared', () => {
      delete row.dataset.loteId;
      delete row.dataset.disponible;
      delete row.dataset.unidad;
      delete row.dataset.desglose;
      delete row.dataset.presentaciones;
      delete row.dataset.costo;

      actualizarInfo(row);
      actualizarTotal();
    });

    cantidad?.addEventListener('input', () => {
      actualizarTotal();
      actualizarInfo(row);
    });

    row.querySelector('.eliminar-lote')?.addEventListener('click', () => {
      if (container.querySelectorAll('.lote-row').length > 1) {
        row.remove();
        actualizarTotal();
      }
    });

    if (search && search.dataset.built !== '1') {
      buildLoteSearch(search);
    }

    actualizarInfo(row);
  }

  btnAgregar.addEventListener('click', () => {
    const first = container.querySelector('.lote-row');
    if (!first) return;

    const row = first.cloneNode(true);

    row.querySelectorAll('input').forEach((element) => {
      const name = element.getAttribute('name');

      if (name) {
        element.setAttribute('name', name.replace(/\d+/, index));
      }

      if (element.classList.contains('lote-cantidad')) {
        element.value = '0';
      } else if (!element.classList.contains('lote-search-input')) {
        element.value = '';
      }
    });

    const search = row.querySelector('.lote-search');

    if (search) {
      delete search.dataset.built;
      search.dataset.field = search.dataset.field.replace(/\d+/, index);
      search.innerHTML = '';
    }

    delete row.dataset.loteId;
    delete row.dataset.disponible;
    delete row.dataset.unidad;
    delete row.dataset.desglose;
    delete row.dataset.presentaciones;
    delete row.dataset.costo;

    const info = row.querySelector('.lote-info');
    if (info) {
      info.textContent = '';
      info.classList.add('hidden');
    }

    const equivalencia = row.querySelector('.lote-equivalencia');
    if (equivalencia) {
      equivalencia.textContent = '';
      equivalencia.classList.add('hidden');
    }

    container.appendChild(row);
    configurar(row);
    index++;
    actualizarTotal();
  });

  tipo?.addEventListener('change', actualizarLabels);

  container.querySelectorAll('.lote-row').forEach(configurar);
  actualizarLabels();

  const form = container.closest('form');

  if (form) {
    form.addEventListener('submit', (event) => {
      const incompleta = Array.from(container.querySelectorAll('.lote-row')).find((row) => {
        const hidden = row.querySelector('.lote-search-value');

        return !hidden || !hidden.value;
      });

      if (!incompleta) return;

      event.preventDefault();
      event.stopPropagation();

      const input = incompleta.querySelector('.lote-search-input');
      if (input) {
        input.focus();
        input.dispatchEvent(new Event('input'));
      }

      const mensaje = 'Seleccione un lote en todas las líneas.';

      if (window.AdminToast) {
        window.AdminToast.show(mensaje, 'error');
      } else {
        alert(mensaje);
      }
    });
  }
}

initAjusteForm();
