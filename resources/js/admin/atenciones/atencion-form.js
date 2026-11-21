export function initAtencionForm({ productStartIndex = 1, serviceStartIndex = 1 } = {}) {
  let prodRowIndex = productStartIndex;
  let servRowIndex = serviceStartIndex;

  const prodContainer = document.getElementById('productos-container');
  const servContainer = document.getElementById('servicios-container');
  const btnAgregarProd = document.getElementById('agregar-producto');
  const btnAgregarServ = document.getElementById('agregar-servicio');
  const totalSpan = document.getElementById('total-atencion');

  if (!totalSpan) return;

  const form = totalSpan.closest('form');

  function recalcularTotal() {
    let total = 0;

    if (prodContainer) {
      prodContainer.querySelectorAll('.producto-row').forEach((row) => {
        const precio = parseFloat(row.querySelector('.producto-precio')?.value) || 0;
        const cantidad = parseFloat(row.querySelector('.producto-cantidad')?.value) || 0;
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

    if (form) {
      form.dispatchEvent(new CustomEvent('total:changed', { detail: { total } }));
    }
  }

  function actualizarAdvertenciaProducto(row) {
    const select = row.querySelector('.producto-select');
    const cantidad = row.querySelector('.producto-cantidad');
    const advertencia = row.querySelector('.producto-advertencia');

    if (!select || !advertencia) return;

    const option = select.options[select.selectedIndex];

    if (!option || !option.value) {
      advertencia.textContent = '';
      advertencia.classList.add('hidden');
      return;
    }

    const factor = parseFloat(option.dataset.factor) || 1;
    const vendible = parseFloat(option.dataset.stockVendible) || 0;
    const minimo = parseFloat(option.dataset.stockMinimo) || 0;
    const cantidadBase = (parseFloat(cantidad?.value) || 0) * factor;

    let mensaje = '';
    if (vendible <= 0) {
      mensaje = 'Sin stock vendible.';
    } else if (cantidadBase > vendible) {
      mensaje = 'La cantidad supera el stock vendible.';
    } else if (minimo > 0 && vendible - cantidadBase < minimo) {
      mensaje = 'Quedará por debajo del stock mínimo.';
    }

    advertencia.textContent = mensaje;
    advertencia.classList.toggle('hidden', !mensaje);
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
        const paso = option ? parseFloat(option.dataset.paso) || 1 : 1;
        precio.value = p.toFixed(2);
        if (cantidad) {
          cantidad.step = paso;
          cantidad.min = paso;
          if (parseFloat(cantidad.value) < paso) {
            cantidad.value = paso;
          }
        }
        recalcularTotal();
        actualizarAdvertenciaProducto(row);
      });
    }

    if (cantidad) {
      cantidad.addEventListener('input', () => {
        recalcularTotal();
        actualizarAdvertenciaProducto(row);
      });
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
