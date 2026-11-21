import axios from 'axios';

let documentListenerAdded = false;

function ensureDocumentListener() {
  if (documentListenerAdded) return;

  documentListenerAdded = true;
  document.addEventListener('click', (event) => {
    const clicked = event.target.closest('.lote-search');
    document.querySelectorAll('.lote-search-results').forEach((element) => {
      if (!clicked || !clicked.contains(element)) element.classList.add('hidden');
    });
  });
}

/**
 * Buscador dinámico de lotes para ajustes de inventario.
 *
 * Emite en el contenedor:
 *   lote:selected → detail { id, label, disponible, unidad, desglose, presentaciones, costo, ... }
 *   lote:cleared
 */
export function buildLoteSearch(container) {
  if (!container || container.dataset.built === '1') return;

  ensureDocumentListener();

  const fieldName = container.dataset.field || '';
  const url = container.dataset.url || '';
  const placeholder = container.dataset.placeholder || 'Buscar lote por producto o código...';

  container.dataset.built = '1';
  container.innerHTML = '';
  container.classList.add('relative');

  const hidden = document.createElement('input');
  hidden.type = 'hidden';
  hidden.name = fieldName;
  hidden.className = 'lote-search-value';
  container.appendChild(hidden);

  const searchRow = document.createElement('div');
  searchRow.className = 'relative';
  container.appendChild(searchRow);

  const input = document.createElement('input');
  input.type = 'text';
  input.autocomplete = 'off';
  input.placeholder = placeholder;
  input.className = 'lote-search-input w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none';
  searchRow.appendChild(input);

  const results = document.createElement('div');
  results.className = 'lote-search-results hidden absolute left-0 right-0 top-full mt-1 bg-white rounded-lg shadow-lg border border-stone-200 z-50 max-h-72 overflow-y-auto';
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

  function showSelected(data) {
    searchRow.classList.add('hidden');
    selected.classList.remove('hidden');
    selected.innerHTML = '';

    const box = document.createElement('div');
    box.className = 'flex items-center justify-between gap-2 bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2';

    const left = document.createElement('div');
    left.className = 'min-w-0 flex flex-col';

    const text = document.createElement('span');
    text.className = 'text-sm text-emerald-800 font-medium truncate';
    text.textContent = data.label;
    text.title = data.label;
    left.appendChild(text);

    const detalle = [];

    if (data.disponible_formateado) {
      detalle.push('Disponible: ' + data.disponible_formateado
        + (data.unidad ? ' ' + data.unidad : ''));
    }

    if (data.desglose) {
      detalle.push(data.desglose);
    }

    if (detalle.length) {
      const sub = document.createElement('span');
      sub.className = 'text-[11px] text-stone-500 truncate';
      sub.textContent = detalle.join(' · ');
      sub.title = detalle.join(' · ');
      left.appendChild(sub);
    }

    box.appendChild(left);

    const right = document.createElement('div');
    right.className = 'flex items-center gap-1.5 shrink-0';

    if (data.costo >= 0) {
      const costo = document.createElement('span');
      costo.className = 'text-[11px] text-stone-500 whitespace-nowrap';
      costo.textContent = 'Costo: S/ ' + Number(data.costo || 0).toFixed(2);
      right.appendChild(costo);
    }

    const clear = document.createElement('button');
    clear.type = 'button';
    clear.className = 'lote-search-clear text-emerald-500 hover:text-emerald-700 p-1';
    clear.title = 'Cambiar lote';
    clear.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>';
    clear.addEventListener('click', (event) => {
      event.stopPropagation();
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
    showSelected(data);
    results.classList.add('hidden');
    input.value = '';
    emit('lote:selected', data);
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
    emit('lote:cleared');
  }

  function renderResults(items) {
    results.innerHTML = '';

    if (!items.length) {
      showMessage('No se encontraron lotes');
      return;
    }

    items.forEach((item) => {
      const option = document.createElement('button');
      option.type = 'button';
      option.className = 'lote-search-option w-full text-left px-3 py-2 hover:bg-emerald-50 transition border-b border-stone-100 last:border-0 flex items-center justify-between gap-3';

      const left = document.createElement('div');
      left.className = 'min-w-0';

      const name = document.createElement('div');
      name.className = 'text-sm font-medium text-stone-700 truncate';
      name.textContent = item.label;
      left.appendChild(name);

      const meta = document.createElement('div');
      meta.className = 'text-xs text-stone-400 truncate';
      meta.textContent = 'Disp. ' + item.disponible_formateado
        + (item.unidad ? ' ' + item.unidad : '')
        + ' base · Costo: S/ ' + Number(item.costo || 0).toFixed(2);
      left.appendChild(meta);

      option.appendChild(left);

      const badge = document.createElement('span');
      badge.className = 'text-[11px] text-emerald-700 whitespace-nowrap';
      badge.textContent = item.fecha_vencimiento ? 'Vence ' + item.fecha_vencimiento : '';
      option.appendChild(badge);

      option.addEventListener('click', () => select(item));
      results.appendChild(option);
    });

    results.classList.remove('hidden');
  }

  function search(query) {
    const seq = ++requestSeq;
    setLoading();

    axios.get(url, { params: { q: query } })
      .then((response) => {
        if (seq !== requestSeq) return;
        renderResults(response.data || []);
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

  input.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      results.classList.add('hidden');
      return;
    }

    if (event.key !== 'Enter') return;

    event.preventDefault();
    event.stopPropagation();

    const first = results.querySelector('.lote-search-option');
    if (!results.classList.contains('hidden') && first) {
      first.click();
    }
  });
}
