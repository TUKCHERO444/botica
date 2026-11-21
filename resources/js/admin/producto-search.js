import axios from 'axios';

let documentListenerAdded = false;

/**
 * Buscador dinámico de producto/presentación.
 *
 * Uso:
 *   <div class="producto-search"
 *        data-field="productos[0][producto_presentacion_id]"
 *        data-url="/admin/ventas/presentaciones/search"
 *        data-initial-id="..."
 *        data-initial-label="..."
 *        data-initial-precio="..."
 *        data-initial-paso="..."
 *        data-initial-stock="..."></div>
 *
 * Emite en el contenedor:
 *   producto:selected  → detail { id, label, precio, paso, stock, ... }
 *   producto:cleared
 */
export function initProductoSearch(root = document) {
  root.querySelectorAll('.producto-search').forEach(buildProductoSearch);
}

function ensureDocumentListener() {
  if (documentListenerAdded) return;

  documentListenerAdded = true;
  document.addEventListener('click', (e) => {
    const clicked = e.target.closest('.producto-search');
    document.querySelectorAll('.producto-search-results').forEach((el) => {
      if (!clicked || !clicked.contains(el)) el.classList.add('hidden');
    });
  });
}

export function buildProductoSearch(container) {
  if (!container || container.dataset.built === '1') return;

  ensureDocumentListener();

  const fieldName = container.dataset.field || '';
  const url = container.dataset.url || '/admin/ventas/presentaciones/search';
  const placeholder = container.dataset.placeholder || 'Buscar producto por nombre o código...';
  const soloStock = container.dataset.soloStock === '0' ? 0 : 1;
  const initialId = container.dataset.initialId || '';
  const initialLabel = container.dataset.initialLabel || '';
  const initialPrecio = container.dataset.initialPrecio || '';
  const initialPaso = container.dataset.initialPaso || '';
  const initialStock = container.dataset.initialStock || '';
  const initialEquivalencia = container.dataset.initialEquivalencia || '';
  const initialVentaLabel = container.dataset.initialVentaLabel || '';

  container.dataset.built = '1';
  container.innerHTML = '';
  container.classList.add('relative');

  const hidden = document.createElement('input');
  hidden.type = 'hidden';
  hidden.name = fieldName;
  hidden.value = initialId;
  hidden.className = 'producto-search-value';
  container.appendChild(hidden);

  const searchRow = document.createElement('div');
  searchRow.className = 'relative';
  container.appendChild(searchRow);

  const input = document.createElement('input');
  input.type = 'text';
  input.autocomplete = 'off';
  input.placeholder = placeholder;
  input.className = 'producto-search-input w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none';
  searchRow.appendChild(input);

  const results = document.createElement('div');
  results.className = 'producto-search-results hidden absolute left-0 right-0 top-full mt-1 bg-white rounded-lg shadow-lg border border-stone-200 z-50 max-h-72 overflow-y-auto';
  searchRow.appendChild(results);

  const selected = document.createElement('div');
  selected.className = 'hidden';
  container.appendChild(selected);

  let debounce = null;
  let requestSeq = 0;

  function emit(name, detail = {}) {
    container.dispatchEvent(new CustomEvent(name, { bubbles: true, detail }));
  }

  function setLoading() {
    results.innerHTML = '';
    const loading = document.createElement('div');
    loading.className = 'px-3 py-2.5 text-sm text-stone-400';
    loading.textContent = 'Buscando…';
    results.appendChild(loading);
    results.classList.remove('hidden');
  }

  function showMessage(message) {
    results.innerHTML = '';
    const empty = document.createElement('div');
    empty.className = 'px-3 py-2.5 text-sm text-stone-400';
    empty.textContent = message;
    results.appendChild(empty);
    results.classList.remove('hidden');
  }

  function showSelected(label, data = {}) {
    searchRow.classList.add('hidden');
    selected.classList.remove('hidden');
    selected.innerHTML = '';

    const box = document.createElement('div');
    box.className = 'flex items-center justify-between gap-2 bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2';

    const left = document.createElement('div');
    left.className = 'min-w-0 flex flex-col';

    const text = document.createElement('span');
    text.className = 'text-sm text-emerald-800 font-medium truncate';
    text.textContent = label;
    text.title = label;
    left.appendChild(text);

    const equivalencia = data.presentacion_equivalencia || data.stock_equivalencia;
    const factor = Number(data.factor);

    if (equivalencia && (!Number.isFinite(factor) || factor > 1)) {
      const eq = document.createElement('span');
      eq.className = 'text-[11px] text-stone-500 truncate';
      eq.textContent = 'Disponible: ' + equivalencia;
      eq.title = equivalencia;
      left.appendChild(eq);
    }

    box.appendChild(left);

    const right = document.createElement('div');
    right.className = 'flex items-center gap-1.5 shrink-0';

    const stockLabel = data.stockVendibleLabel
      || data.stock_vendible_formateado
      || data.stockLabel
      || data.stock_formateado;

    if (stockLabel) {
      const stock = document.createElement('span');
      stock.className = 'text-[11px] text-stone-500 whitespace-nowrap';
      stock.textContent = 'Vendible: ' + stockLabel;
      right.appendChild(stock);
    }

    const alertas = [data.alerta_stock, data.alerta_vencimiento].filter(Boolean);

    if (alertas.length) {
      const alerta = document.createElement('span');
      alerta.className = 'text-[11px] font-medium whitespace-nowrap '
        + (data.alerta_stock ? 'text-red-600' : 'text-amber-600');
      alerta.textContent = alertas.join(' · ');
      right.appendChild(alerta);
    }

    const ventaLabel = data.ventaLabel || data.precio_formateado;

    if (ventaLabel) {
      const venta = document.createElement('span');
      venta.className = 'text-[11px] text-emerald-700 whitespace-nowrap';
      venta.textContent = 'Venta: ' + ventaLabel;
      right.appendChild(venta);
    }

    const clear = document.createElement('button');
    clear.type = 'button';
    clear.className = 'producto-search-clear text-emerald-500 hover:text-emerald-700 p-1';
    clear.title = 'Cambiar producto';
    clear.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>';
    clear.addEventListener('click', (e) => {
      e.stopPropagation();
      clearSelection();
    });
    right.appendChild(clear);

    box.appendChild(right);
    selected.appendChild(box);
  }

  function select(data) {
    requestSeq++;
    if (debounce) clearTimeout(debounce);
    hidden.value = data.id;
    showSelected(data.label, data);
    results.classList.add('hidden');
    input.value = '';
    emit('producto:selected', data);
  }

  function clearSelection() {
    requestSeq++;
    if (debounce) clearTimeout(debounce);
    hidden.value = '';
    selected.classList.add('hidden');
    selected.innerHTML = '';
    searchRow.classList.remove('hidden');
    input.value = '';
    input.focus();
    emit('producto:cleared');
  }

  function renderResults(items, query) {
    results.innerHTML = '';

    if (!items.length) {
      showMessage('No se encontraron productos');
      return;
    }

    items.forEach((item) => {
      const option = document.createElement('button');
      option.type = 'button';
      option.className = 'producto-search-option w-full text-left px-3 py-2 hover:bg-emerald-50 transition border-b border-stone-100 last:border-0 flex items-center justify-between gap-3';

      const left = document.createElement('div');
      left.className = 'min-w-0';

      const name = document.createElement('div');
      name.className = 'text-sm font-medium text-stone-700 truncate';
      name.textContent = item.label;
      left.appendChild(name);

      const meta = document.createElement('div');
      meta.className = 'text-xs text-stone-400 truncate';
      meta.textContent = item.precio_formateado + ' · Vendible: '
        + (item.stock_vendible_formateado || item.stock_formateado);
      left.appendChild(meta);

      if (item.presentacion_equivalencia && Number(item.factor) > 1) {
        const eq = document.createElement('div');
        eq.className = 'text-[11px] text-stone-500 truncate';
        eq.textContent = 'Disponible: ' + item.presentacion_equivalencia;
        eq.title = item.presentacion_equivalencia;
        left.appendChild(eq);
      }

      const alertas = [item.alerta_stock, item.alerta_vencimiento].filter(Boolean);
      if (alertas.length) {
        const alerta = document.createElement('div');
        alerta.className = 'text-[11px] font-medium '
          + (item.alerta_stock ? 'text-red-600' : 'text-amber-600');
        alerta.textContent = alertas.join(' · ');
        left.appendChild(alerta);
      }

      option.appendChild(left);

      const price = document.createElement('span');
      price.className = 'text-sm font-semibold text-emerald-700 whitespace-nowrap';
      price.textContent = item.precio_formateado;
      option.appendChild(price);

      option.addEventListener('click', () => select(item));
      results.appendChild(option);
    });

    results.classList.remove('hidden');

    // Lector de código de barras: si algún resultado coincide exactamente con
    // el código escaneado, se selecciona sin obligar a hacer clic.
    const exacto = items.find((item) => item.codigo_barras && item.codigo_barras === query);
    if (exacto) {
      select(exacto);
    }
  }

  function search(query) {
    const seq = ++requestSeq;
    setLoading();

    axios.get(url, { params: { q: query, solo_stock: soloStock } })
      .then((res) => {
        if (seq !== requestSeq) return;
        renderResults(res.data || [], query);
      })
      .catch(() => {
        if (seq !== requestSeq) return;
        results.classList.add('hidden');
      });
  }

  input.addEventListener('input', () => {
    const query = input.value.trim();
    if (debounce) clearTimeout(debounce);

    if (query.length < 2) {
      requestSeq++;
      results.classList.add('hidden');
      return;
    }

    debounce = setTimeout(() => search(query), 250);
  });

  input.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      results.classList.add('hidden');
      return;
    }

    if (e.key !== 'Enter') return;

    e.preventDefault();
    e.stopPropagation();

    const first = results.querySelector('.producto-search-option');
    if (!results.classList.contains('hidden') && first) {
      first.click();
      return;
    }

    const query = input.value.trim();
    if (query.length >= 2) {
      if (debounce) clearTimeout(debounce);
      search(query);
    }
  });

  if (initialId && initialLabel) {
    showSelected(initialLabel, {
      id: initialId,
      label: initialLabel,
      precio: initialPrecio,
      paso: initialPaso,
      stockLabel: initialStock,
      presentacion_equivalencia: initialEquivalencia,
      ventaLabel: initialVentaLabel,
    });
  }
}
