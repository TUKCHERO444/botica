function initAjusteForm() {
  const container = document.getElementById('lotes-container');
  const btnAgregar = document.getElementById('agregar-lote');
  const tipo = document.getElementById('ajuste-tipo');
  const totalEl = document.getElementById('ajuste-total');

  if (!container || !btnAgregar) return;

  let index = container.querySelectorAll('.lote-row').length;

  function actualizarTotal() {
    if (!totalEl) return;

    let total = 0;

    container.querySelectorAll('.lote-row').forEach((row) => {
      const select = row.querySelector('.lote-select');
      const input = row.querySelector('.lote-cantidad');
      if (!select || !input) return;

      const option = select.options[select.selectedIndex];
      if (!option || !option.value) return;

      const cantidad = parseFloat(input.value || '0');
      const costo = parseFloat(option.dataset.costo || '0');
      const disponible = parseFloat(option.dataset.disponible || '0');

      let cantidadAjuste = cantidad;

      if (tipo?.value === 'conteo_fisico') {
        cantidadAjuste = Math.abs(cantidad - disponible);
      }

      if (Number.isFinite(cantidadAjuste) && Number.isFinite(costo)) {
        total += cantidadAjuste * costo;
      }
    });

    totalEl.textContent = total.toFixed(2);
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

    document.querySelectorAll('.ajuste-cantidad-label').forEach((el) => {
      el.innerHTML = label + ' <span class="text-red-500">*</span>';
    });

    const helpEl = document.getElementById('ajuste-help');
    if (helpEl) helpEl.textContent = help;

    actualizarTotal();
  }

  function configurar(row) {
    row.querySelector('.lote-select')?.addEventListener('change', actualizarTotal);
    row.querySelector('.lote-cantidad')?.addEventListener('input', actualizarTotal);

    row.querySelector('.eliminar-lote')?.addEventListener('click', () => {
      if (container.querySelectorAll('.lote-row').length > 1) {
        row.remove();
        actualizarTotal();
      }
    });
  }

  btnAgregar.addEventListener('click', () => {
    const first = container.querySelector('.lote-row');
    if (!first) return;

    const row = first.cloneNode(true);

    row.querySelectorAll('select, input').forEach((el) => {
      const name = el.getAttribute('name');
      if (name) {
        el.setAttribute('name', name.replace(/\d+/, index));
      }
      if (el.tagName === 'INPUT') {
        el.value = el.name.includes('cantidad') ? '0' : '';
      }
      if (el.tagName === 'SELECT') {
        el.selectedIndex = 0;
      }
    });

    container.appendChild(row);
    configurar(row);
    index++;
    actualizarTotal();
  });

  tipo?.addEventListener('change', actualizarLabels);

  container.querySelectorAll('.lote-row').forEach(configurar);
  actualizarLabels();
}

initAjusteForm();
