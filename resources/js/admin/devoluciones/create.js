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

function initDevolucionCreate() {
  const select = document.getElementById('devolucion-ingreso');

  if (select) {
    select.addEventListener('change', function () {
      if (!this.value) return;

      const url = this.dataset.createUrl + '?ingreso_id=' + encodeURIComponent(this.value);
      window.AdminModal.open(url);
    });
  }

  actualizarTotal();
}

if (!window.__devolucionCreateBound) {
  window.__devolucionCreateBound = true;

  document.addEventListener('input', (event) => {
    if (event.target.classList.contains('linea-cantidad')) {
      actualizarTotal();
    }
  });
}

initDevolucionCreate();
