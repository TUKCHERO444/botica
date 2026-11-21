import { formatearCantidad } from '../presentacion-stock.js';

function actualizarTotal() {
  const totalEl = document.getElementById('devolucion-total');
  if (!totalEl) return;

  let total = 0;

  document.querySelectorAll('.linea-cantidad').forEach((input) => {
    const cantidad = parseFloat(input.value || '0');
    const costo = parseFloat(input.dataset.costo || '0');

    if (Number.isFinite(cantidad) && Number.isFinite(costo)) {
      total += cantidad * costo;
    }
  });

  totalEl.textContent = total.toFixed(2);
}

function actualizarEquivalencia(input) {
  if (!input) return;

  const hint = input.closest('td')?.querySelector('.linea-equivalencia');
  if (!hint) return;

  const cantidad = parseFloat(input.value || '0') || 0;
  const factor = parseFloat(input.dataset.factor || '1') || 1;
  const unidad = input.dataset.unidad || '';

  if (cantidad > 0 && factor > 1) {
    hint.textContent = formatearCantidad(cantidad) + ' × ' + formatearCantidad(factor)
      + ' = ' + formatearCantidad(cantidad * factor) + (unidad ? ' ' + unidad : '');
    hint.classList.remove('hidden');
    return;
  }

  hint.textContent = '';
  hint.classList.add('hidden');
}

function initDevolucionCreate() {
  const select = document.getElementById('devolucion-ingreso');

  if (select) {
    select.addEventListener('change', function () {
      if (!this.value) return;

      const url = this.dataset.createUrl + '?ingreso_id=' + encodeURIComponent(this.value);
      window.AdminModal.open(url);
    });
  }

  document.querySelectorAll('.linea-cantidad').forEach(actualizarEquivalencia);
  actualizarTotal();
}

if (!window.__devolucionCreateBound) {
  window.__devolucionCreateBound = true;

  document.addEventListener('input', (event) => {
    if (event.target.classList.contains('linea-cantidad')) {
      actualizarTotal();
      actualizarEquivalencia(event.target);
    }
  });
}

initDevolucionCreate();
