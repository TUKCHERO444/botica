export function initIngresoForm({ startIndex = 1 } = {}) {
  let rowIndex = startIndex;

  const container = document.getElementById('productos-container');
  const btnAgregar = document.getElementById('agregar-producto');

  if (!container || !btnAgregar) return;

  function configurarRow(row) {
    const select = row.querySelector('.producto-select');
    const cantidad = row.querySelector('.producto-cantidad');
    const costo = row.querySelector('.producto-costo');
    const alerta = row.querySelector('.producto-alerta');
    const eliminar = row.querySelector('.eliminar-producto');
    const stockSpan = row.querySelector('.producto-stock-actual');
    const conversion = row.querySelector('.producto-conversion');

    function formatearCantidad(valor) {
      const numero = parseFloat(valor);

      if (!Number.isFinite(numero)) return '0';

      return numero.toLocaleString('es-PE', { maximumFractionDigits: 3 });
    }

    function actualizarConversion() {
      if (!select || !conversion) return;

      const option = select.options[select.selectedIndex];
      const factor = option ? parseFloat(option.dataset.factor) || 1 : 1;
      const unidad = option ? option.dataset.unidad || '' : '';
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

    function validarCosto() {
      if (!select || !costo || !alerta) return;

      const option = select.options[select.selectedIndex];
      const precioVenta = option ? parseFloat(option.dataset.precioVenta || '0') : 0;
      const costoIngresado = parseFloat(costo.value || '0');

      const supera = precioVenta > 0 && costoIngresado > precioVenta;
      alerta.classList.toggle('hidden', !supera);
    }

    if (select && stockSpan) {
      select.addEventListener('change', function () {
        const option = this.options[this.selectedIndex];
        const stock = option ? option.dataset.stock || '0' : '0';
        const paso = option ? parseFloat(option.dataset.paso) || 1 : 1;
        stockSpan.textContent = stock;
        if (cantidad) {
          cantidad.step = paso;
          cantidad.min = paso;
        }
        actualizarConversion();
        validarCosto();
        actualizarTotales();
      });
    }

    cantidad?.addEventListener('input', actualizarConversion);

    costo?.addEventListener('input', validarCosto);

    if (eliminar) {
      eliminar.addEventListener('click', () => {
        if (container.querySelectorAll('.producto-row').length > 1) {
          row.remove();
          actualizarTotales();
        }
      });
    }

    if (select && select.selectedIndex > 0) {
      select.dispatchEvent(new Event('change'));
    }

    validarCosto();
    actualizarConversion();
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

    newRow.querySelectorAll('select, input').forEach((el) => {
      const name = el.getAttribute('name');
      if (name) {
        el.setAttribute('name', name.replace(/\d+/, rowIndex));
      }
      if (el.tagName === 'INPUT') {
        el.value = el.name.includes('cantidad') ? '1' : '';
      }
      if (el.tagName === 'SELECT') {
        el.selectedIndex = 0;
      }
    });

    const stockSpan = newRow.querySelector('.producto-stock-actual');
    if (stockSpan) stockSpan.textContent = '0';

    container.appendChild(newRow);
    configurarRow(newRow);
    actualizarTotales();
    rowIndex++;
  });

  container.querySelectorAll('.producto-row').forEach(configurarRow);
  actualizarTotales();
}
