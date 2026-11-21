import { buildProductoSearch } from '../producto-search.js';

export function initIngresoForm({ startIndex = 1 } = {}) {
  let rowIndex = startIndex;

  const container = document.getElementById('productos-container');
  const btnAgregar = document.getElementById('agregar-producto');

  if (!container || !btnAgregar) return;

  function formatearCantidad(valor) {
    const numero = parseFloat(valor);

    if (!Number.isFinite(numero)) return '0';

    return numero.toLocaleString('es-PE', { maximumFractionDigits: 3 });
  }

  function factorDe(row) {
    return parseFloat(row.dataset.factor) || 1;
  }

  function unidadDe(row) {
    return row.dataset.unidad || '';
  }

  function precioVentaDe(row) {
    return parseFloat(row.dataset.precioVenta) || 0;
  }

  function actualizarConversion(row) {
    const conversion = row.querySelector('.producto-conversion');
    const cantidad = row.querySelector('.producto-cantidad');

    if (!conversion) return;

    const factor = factorDe(row);
    const unidad = unidadDe(row);
    const cantidadActual = parseFloat(cantidad?.value) || 0;

    if (cantidadActual > 0 && factor > 1) {
      conversion.textContent = 'Ingreso: ' + formatearCantidad(cantidadActual)
        + ' × ' + formatearCantidad(factor) + ' = '
        + formatearCantidad(cantidadActual * factor)
        + (unidad ? ' ' + unidad : '') + ' (unidad base)';
      conversion.classList.remove('hidden');
      return;
    }

    conversion.textContent = '';
    conversion.classList.add('hidden');
  }

  function validarCosto(row) {
    const costo = row.querySelector('.producto-costo');
    const alerta = row.querySelector('.producto-alerta');

    if (!costo || !alerta) return;

    const precioVenta = precioVentaDe(row);
    const costoIngresado = parseFloat(costo.value || '0');

    const supera = precioVenta > 0 && costoIngresado > precioVenta;
    alerta.classList.toggle('hidden', !supera);
  }

  function configurarRow(row) {
    const cantidad = row.querySelector('.producto-cantidad');
    const costo = row.querySelector('.producto-costo');
    const eliminar = row.querySelector('.eliminar-producto');
    const stockSpan = row.querySelector('.producto-stock-actual');
    const alerta = row.querySelector('.producto-alerta');
    const search = row.querySelector('.producto-search');

    row.addEventListener('producto:selected', (event) => {
      const data = event.detail || {};

      row.dataset.factor = data.factor ?? 1;
      row.dataset.unidad = data.unidad ?? '';
      row.dataset.precioVenta = data.precio_venta ?? 0;

      if (stockSpan) {
        stockSpan.textContent = data.stock_formateado || data.stockLabel || '0';
      }

      if (cantidad) {
        const paso = parseFloat(data.paso) || 1;
        cantidad.step = paso;
        cantidad.min = paso;

        if ((parseFloat(cantidad.value) || 0) < paso) {
          cantidad.value = paso;
        }
      }

      actualizarConversion(row);
      validarCosto(row);
      actualizarTotales();
    });

    row.addEventListener('producto:cleared', () => {
      delete row.dataset.factor;
      delete row.dataset.unidad;
      delete row.dataset.precioVenta;

      if (stockSpan) stockSpan.textContent = '0';
      if (alerta) alerta.classList.add('hidden');
      if (cantidad) {
        cantidad.step = 1;
        cantidad.min = 1;
      }

      actualizarConversion(row);
      validarCosto(row);
      actualizarTotales();
    });

    cantidad?.addEventListener('input', () => actualizarConversion(row));
    costo?.addEventListener('input', () => validarCosto(row));

    if (eliminar) {
      eliminar.addEventListener('click', () => {
        if (container.querySelectorAll('.producto-row').length > 1) {
          row.remove();
          actualizarTotales();
        }
      });
    }

    if (search && search.dataset.built !== '1') {
      buildProductoSearch(search);
    }

    validarCosto(row);
    actualizarConversion(row);
  }

  function actualizarTotales() {
    const totalEl = document.getElementById('compra-total');
    if (!totalEl) return;

    let total = 0;

    container.querySelectorAll('.producto-row').forEach((row) => {
      const cantidad = parseFloat(row.querySelector('.producto-cantidad')?.value || '0');
      const costo = parseFloat(row.querySelector('.producto-costo')?.value || '0');

      if (Number.isFinite(cantidad) && Number.isFinite(costo)) {
        total += cantidad * costo;
      }
    });

    totalEl.textContent = total.toFixed(2);
  }

  container.addEventListener('input', actualizarTotales);
  container.addEventListener('change', actualizarTotales);

  btnAgregar.addEventListener('click', () => {
    const firstRow = container.querySelector('.producto-row');
    if (!firstRow) return;

    const newRow = firstRow.cloneNode(true);

    newRow.querySelectorAll('input').forEach((el) => {
      const name = el.getAttribute('name');

      if (name) {
        el.setAttribute('name', name.replace(/\d+/, rowIndex));
      }

      if (el.classList.contains('producto-cantidad')) {
        el.value = '1';
      } else if (!el.classList.contains('producto-search-input')) {
        el.value = '';
      }
    });

    const search = newRow.querySelector('.producto-search');

    if (search) {
      delete search.dataset.built;
      delete search.dataset.initialId;
      delete search.dataset.initialLabel;
      delete search.dataset.initialPrecio;
      delete search.dataset.initialPaso;
      delete search.dataset.initialStock;
      delete search.dataset.initialEquivalencia;
      delete search.dataset.initialVentaLabel;
      search.dataset.field = search.dataset.field.replace(/\d+/, rowIndex);
      search.innerHTML = '';
    }

    const cantidadNueva = newRow.querySelector('.producto-cantidad');
    if (cantidadNueva) {
      cantidadNueva.step = 1;
      cantidadNueva.min = 1;
    }

    delete newRow.dataset.factor;
    delete newRow.dataset.unidad;
    delete newRow.dataset.precioVenta;

    const stockSpan = newRow.querySelector('.producto-stock-actual');
    if (stockSpan) stockSpan.textContent = '0';

    const conversion = newRow.querySelector('.producto-conversion');
    if (conversion) {
      conversion.textContent = '';
      conversion.classList.add('hidden');
    }

    const alerta = newRow.querySelector('.producto-alerta');
    if (alerta) alerta.classList.add('hidden');

    container.appendChild(newRow);
    configurarRow(newRow);
    actualizarTotales();
    rowIndex++;
  });

  container.querySelectorAll('.producto-row').forEach(configurarRow);
  actualizarTotales();

  const form = container.closest('form');

  if (form) {
    form.addEventListener('submit', (event) => {
      const incompleta = Array.from(container.querySelectorAll('.producto-row')).find((row) => {
        const hidden = row.querySelector('.producto-search-value');

        return !hidden || !hidden.value;
      });

      if (!incompleta) return;

      event.preventDefault();
      event.stopPropagation();

      const input = incompleta.querySelector('.producto-search-input');
      if (input) {
        input.focus();
        input.dispatchEvent(new Event('input'));
      }

      const mensaje = 'Seleccione un producto en todas las líneas.';

      if (window.AdminToast) {
        window.AdminToast.show(mensaje, 'error');
      } else {
        alert(mensaje);
      }
    });
  }
}
