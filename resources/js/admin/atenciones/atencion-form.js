export function initAtencionForm({ productStartIndex = 1, serviceStartIndex = 1 } = {}) {
  let prodRowIndex = productStartIndex;
  let servRowIndex = serviceStartIndex;

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

  const prodContainer = document.getElementById('productos-container');
  const servContainer = document.getElementById('servicios-container');
  const btnAgregarProd = document.getElementById('agregar-producto');
  const btnAgregarServ = document.getElementById('agregar-servicio');
  const totalSpan = document.getElementById('total-atencion');

  if (!totalSpan) return;

  function recalcularTotal() {
    let total = 0;

    if (prodContainer) {
      prodContainer.querySelectorAll('.producto-row').forEach((row) => {
        const precio = parseFloat(row.querySelector('.producto-precio')?.value) || 0;
        const cantidad = parseInt(row.querySelector('.producto-cantidad')?.value) || 0;
        const sub = precio * cantidad;
        total += sub;
        const subEl = row.querySelector('.producto-subtotal');
        if (subEl) subEl.textContent = 'S/ ' + sub.toFixed(2);
      });
    }

    if (servContainer) {
      servContainer.querySelectorAll('.servicio-row').forEach((row) => {
        const precio = parseFloat(row.querySelector('.servicio-precio')?.value) || 0;
        total += precio;
        const subEl = row.querySelector('.servicio-subtotal');
        if (subEl) subEl.textContent = 'S/ ' + precio.toFixed(2);
      });
    }

    totalSpan.textContent = 'S/ ' + total.toFixed(2);
  }

  function configurarProductoRow(row) {
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
        if (prodContainer) {
          row.remove();
          recalcularTotal();
        }
      });
    }

    if (select && select.selectedIndex > 0) {
      select.dispatchEvent(new Event('change'));
    }
  }

  function configurarServicioRow(row) {
    const select = row.querySelector('.servicio-select');
    const precio = row.querySelector('.servicio-precio');
    const eliminar = row.querySelector('.eliminar-servicio');

    if (select && precio) {
      select.addEventListener('change', function () {
        const option = this.options[this.selectedIndex];
        const p = option ? parseFloat(option.dataset.precio) || 0 : 0;
        precio.value = p.toFixed(2);
        recalcularTotal();
      });
    }

    if (eliminar) {
      eliminar.addEventListener('click', () => {
        if (servContainer && servContainer.querySelectorAll('.servicio-row').length > 1) {
          row.remove();
          recalcularTotal();
        }
      });
    }

    if (select && select.selectedIndex > 0) {
      select.dispatchEvent(new Event('change'));
    }
  }

  if (btnAgregarProd && prodContainer) {
    btnAgregarProd.addEventListener('click', () => {
      const template = document.getElementById('producto-template');
      if (!template) return;

      const newRow = template.content.cloneNode(true).firstElementChild;

      newRow.querySelectorAll('select, input').forEach((el) => {
        const name = el.getAttribute('name');
        if (name) {
          el.setAttribute('name', name.replace(/\d+/, prodRowIndex));
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

      prodContainer.appendChild(newRow);
      configurarProductoRow(newRow);
      prodRowIndex++;
      recalcularTotal();
    });
  }

  if (btnAgregarServ && servContainer) {
    btnAgregarServ.addEventListener('click', () => {
      const firstRow = servContainer.querySelector('.servicio-row');
      if (!firstRow) return;

      const newRow = firstRow.cloneNode(true);

      newRow.querySelectorAll('select, input').forEach((el) => {
        const name = el.getAttribute('name');
        if (name) {
          el.setAttribute('name', name.replace(/\d+/, servRowIndex));
        }
        if (el.tagName === 'INPUT') {
          el.value = '';
        }
        if (el.tagName === 'SELECT') {
          el.selectedIndex = 0;
        }
      });

      const subEl = newRow.querySelector('.servicio-subtotal');
      if (subEl) subEl.textContent = 'S/ 0.00';

      servContainer.appendChild(newRow);
      configurarServicioRow(newRow);
      servRowIndex++;
      recalcularTotal();
    });
  }

  if (prodContainer) {
    prodContainer.querySelectorAll('.producto-row').forEach(configurarProductoRow);
  }
  if (servContainer) {
    servContainer.querySelectorAll('.servicio-row').forEach(configurarServicioRow);
  }
  recalcularTotal();
}
