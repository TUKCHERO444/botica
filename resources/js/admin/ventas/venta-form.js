import { buildProductoSearch } from '../producto-search.js';

export function initVentaForm({ startIndex = 1 } = {}) {
  let rowIndex = startIndex;

  const container = document.getElementById('productos-container');
  const btnAgregar = document.getElementById('agregar-producto');
  const totalSpan = document.getElementById('total-venta');
  const template = document.getElementById('producto-row-template');

  if (!container || !btnAgregar || !totalSpan) return;

  const form = container.closest('form');

  function recalcularTotal() {
    let total = 0;
    container.querySelectorAll('.producto-row').forEach((row) => {
      const precio = parseFloat(row.querySelector('.producto-precio')?.value) || 0;
      const cantidad = parseFloat(row.querySelector('.producto-cantidad')?.value) || 0;
      const sub = precio * cantidad;
      total += sub;
      const subEl = row.querySelector('.producto-subtotal');
      if (subEl) subEl.textContent = 'S/ ' + sub.toFixed(2);
    });
    totalSpan.textContent = 'S/ ' + total.toFixed(2);

    if (form) {
      form.dispatchEvent(new CustomEvent('total:changed', { detail: { total } }));
    }
  }

  function configurarRow(row) {
    const search = row.querySelector('.producto-search');
    const cantidad = row.querySelector('.producto-cantidad');
    const precio = row.querySelector('.producto-precio');
    const eliminar = row.querySelector('.eliminar-producto');
    const advertencia = row.querySelector('.producto-advertencia');

    let stockInfo = null;

    function actualizarAdvertencia() {
      if (!advertencia) return;

      if (!stockInfo) {
        advertencia.textContent = '';
        advertencia.classList.add('hidden');
        return;
      }

      const cantidadBase = (parseFloat(cantidad?.value) || 0) * stockInfo.factor;
      let mensaje = '';

      if (stockInfo.vendible <= 0) {
        mensaje = 'Sin stock vendible.';
      } else if (cantidadBase > stockInfo.vendible) {
        mensaje = 'La cantidad supera el stock vendible.';
      } else if (stockInfo.minimo > 0 && stockInfo.vendible - cantidadBase < stockInfo.minimo) {
        mensaje = 'Quedará por debajo del stock mínimo.';
      }

      advertencia.textContent = mensaje;
      advertencia.classList.toggle('hidden', !mensaje);
    }

    if (search && search.dataset.built !== '1') {
      buildProductoSearch(search);
    }

    row.addEventListener('producto:selected', (e) => {
      const data = e.detail || {};
      const p = parseFloat(data.precio) || 0;
      const paso = parseFloat(data.paso) || 1;

      stockInfo = {
        vendible: parseFloat(data.stock_vendible ?? data.stock ?? 0) || 0,
        minimo: parseFloat(data.stock_minimo ?? 0) || 0,
        factor: parseFloat(data.factor ?? 1) || 1,
      };

      if (precio) precio.value = p.toFixed(2);
      if (cantidad) {
        cantidad.step = paso;
        cantidad.min = paso;
        if ((parseFloat(cantidad.value) || 0) < paso) {
          cantidad.value = paso;
        }
      }
      recalcularTotal();
      actualizarAdvertencia();
    });

    row.addEventListener('producto:cleared', () => {
      stockInfo = null;
      if (precio) precio.value = '';
      if (cantidad) {
        cantidad.step = 1;
        cantidad.min = 1;
        if ((parseFloat(cantidad.value) || 0) <= 0) cantidad.value = 1;
      }
      recalcularTotal();
      actualizarAdvertencia();
    });

    if (cantidad) {
      cantidad.addEventListener('input', () => {
        recalcularTotal();
        actualizarAdvertencia();
      });
    }

    if (eliminar) {
      eliminar.addEventListener('click', () => {
        if (container.querySelectorAll('.producto-row').length > 1) {
          row.remove();
          recalcularTotal();
        }
      });
    }
  }

  function clonarFila() {
    const firstRow = container.querySelector('.producto-row');
    const newRow = firstRow.cloneNode(true);

    const search = newRow.querySelector('.producto-search');
    if (search) {
      delete search.dataset.built;
      delete search.dataset.initialId;
      delete search.dataset.initialLabel;
      delete search.dataset.initialPrecio;
      delete search.dataset.initialPaso;
      delete search.dataset.initialStock;
      search.innerHTML = '';
    }

    const advertencia = newRow.querySelector('.producto-advertencia');
    if (advertencia) {
      advertencia.textContent = '';
      advertencia.classList.add('hidden');
    }

    return newRow;
  }

  btnAgregar.addEventListener('click', () => {
    const newRow = template
      ? template.content.firstElementChild.cloneNode(true)
      : clonarFila();

    if (!newRow) return;

    newRow.querySelectorAll('input').forEach((el) => {
      const name = el.getAttribute('name');
      if (name) {
        el.setAttribute('name', name.replace(/\[\d+\]/, `[${rowIndex}]`));
      }
      if (el.classList.contains('producto-search-value')) {
        el.value = '';
      } else if (el.classList.contains('producto-cantidad')) {
        el.value = '1';
      } else {
        el.value = '';
      }
    });

    const search = newRow.querySelector('.producto-search');
    if (search) {
      if (search.dataset.field) {
        search.dataset.field = search.dataset.field.replace(/\[\d+\]/, `[${rowIndex}]`);
      }
      delete search.dataset.built;
      search.innerHTML = '';
    }

    const subEl = newRow.querySelector('.producto-subtotal');
    if (subEl) subEl.textContent = 'S/ 0.00';

    container.appendChild(newRow);
    configurarRow(newRow);
    rowIndex++;
    recalcularTotal();
    newRow.querySelector('.producto-search-input')?.focus();
  });

  if (form) {
    form.addEventListener('submit', (e) => {
      const filaIncompleta = Array.from(container.querySelectorAll('.producto-row')).find((row) => {
        const hidden = row.querySelector('.producto-search-value');
        return !hidden || !hidden.value;
      });

      if (!filaIncompleta) return;

      e.preventDefault();
      e.stopPropagation();
      const input = filaIncompleta.querySelector('.producto-search-input');
      if (input) {
        input.focus();
        input.dispatchEvent(new Event('input'));
      }
      const mensaje = 'Seleccione un producto en todas las líneas.';
      if (window.showToast) {
        window.showToast(mensaje, 'error');
      } else {
        alert(mensaje);
      }
    });
  }

  container.querySelectorAll('.producto-row').forEach(configurarRow);
  recalcularTotal();
}
