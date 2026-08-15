export function initIngresoForm({ startIndex = 1 } = {}) {
  let rowIndex = startIndex;

  const mc = document.getElementById('modal-container');
  if (!mc) return;

  mc.classList.remove('max-w-lg');
  mc.classList.add('max-w-4xl');

  const resetObserver = new MutationObserver(() => {
    if (mc.children.length === 0) {
      mc.classList.remove('max-w-4xl');
      mc.classList.add('max-w-lg');
      resetObserver.disconnect();
    }
  });
  resetObserver.observe(mc, { childList: true });

  const container = document.getElementById('productos-container');
  const btnAgregar = document.getElementById('agregar-producto');

  if (!container || !btnAgregar) return;

  function configurarRow(row) {
    const select = row.querySelector('.producto-select');
    const cantidad = row.querySelector('.producto-cantidad');
    const eliminar = row.querySelector('.eliminar-producto');
    const stockSpan = row.querySelector('.producto-stock-actual');

    if (select && stockSpan) {
      select.addEventListener('change', function () {
        const option = this.options[this.selectedIndex];
        const stock = option ? parseInt(option.dataset.stock) || 0 : 0;
        if (stockSpan) stockSpan.textContent = stock;
      });
    }

    if (eliminar) {
      eliminar.addEventListener('click', () => {
        if (container.querySelectorAll('.producto-row').length > 1) {
          row.remove();
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

    const stockSpan = newRow.querySelector('.producto-stock-actual');
    if (stockSpan) stockSpan.textContent = '0';

    container.appendChild(newRow);
    configurarRow(newRow);
    rowIndex++;
  });

  container.querySelectorAll('.producto-row').forEach(configurarRow);
}
