export function initVentaForm({ startIndex = 1 } = {}) {
  let rowIndex = startIndex;

  const mc = document.getElementById('modal-container');
  if (!mc) return;

  mc.classList.remove('max-w-lg');
  mc.classList.add('max-w-5xl');

  const resetObserver = new MutationObserver(() => {
    if (mc.children.length === 0) {
      mc.classList.remove('max-w-5xl');
      mc.classList.add('max-w-lg');
      resetObserver.disconnect();
    }
  });
  resetObserver.observe(mc, { childList: true });

  const container = document.getElementById('productos-container');
  const btnAgregar = document.getElementById('agregar-producto');
  const totalSpan = document.getElementById('total-venta');

  if (!container || !btnAgregar || !totalSpan) return;

  function recalcularTotal() {
    let total = 0;
    container.querySelectorAll('.producto-row').forEach((row) => {
      const precio = parseFloat(row.querySelector('.producto-precio')?.value) || 0;
      const cantidad = parseInt(row.querySelector('.producto-cantidad')?.value) || 0;
      const sub = precio * cantidad;
      total += sub;
      const subEl = row.querySelector('.producto-subtotal');
      if (subEl) subEl.textContent = 'S/ ' + sub.toFixed(2);
    });
    totalSpan.textContent = 'S/ ' + total.toFixed(2);
  }

  function configurarRow(row) {
    const select = row.querySelector('.producto-select');
    const cantidad = row.querySelector('.producto-cantidad');
    const precio = row.querySelector('.producto-precio');
    const eliminar = row.querySelector('.eliminar-producto');

    if (select && precio) {
      select.addEventListener('change', function () {
        const option = this.options[this.selectedIndex];
        const p = option ? parseFloat(option.dataset.precio) || 0 : 0;
        precio.value = p.toFixed(2);
        recalcularTotal();
      });
    }

    if (cantidad) {
      cantidad.addEventListener('input', recalcularTotal);
    }

    if (eliminar) {
      eliminar.addEventListener('click', () => {
        if (container.querySelectorAll('.producto-row').length > 1) {
          row.remove();
          recalcularTotal();
        }
      });
    }

    if (select && select.selectedIndex > 0) {
      select.dispatchEvent(new Event('change'));
    }
  }

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

    const subEl = newRow.querySelector('.producto-subtotal');
    if (subEl) subEl.textContent = 'S/ 0.00';

    container.appendChild(newRow);
    configurarRow(newRow);
    rowIndex++;
    recalcularTotal();
  });

  container.querySelectorAll('.producto-row').forEach(configurarRow);
  recalcularTotal();
}
