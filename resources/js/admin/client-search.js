import axios from 'axios';

let genericoHandlerAdded = false;

export function initClientSearch() {
  document.querySelectorAll('.client-search').forEach(el => buildClientSearch(el));

  if (!genericoHandlerAdded) {
    genericoHandlerAdded = true;
    document.addEventListener('click', async (e) => {
      const btn = e.target.closest('[data-generico]');
      if (!btn) return;
      e.preventDefault();

      try {
        const r = await axios.post('/admin/clientes/generico');
        const c = r.data.cliente;
        const text = `${c.nombre} — ${c.dni}`;
        const searchEl = btn.closest('.client-search') || document.querySelector('.client-search');
        if (searchEl) {
          const hidden = searchEl.querySelector('input[type="hidden"]');
          const searchRow = searchEl.querySelector('.flex.gap-2');
          const input = searchEl.querySelector('input[type="text"]');
          const selected = searchEl.querySelector('div.hidden');
          if (hidden) hidden.value = c.id_cliente;
          if (searchRow) searchRow.classList.add('hidden');
          if (selected) {
            selected.classList.remove('hidden');
            selected.innerHTML =
              `<div class="flex items-center justify-between bg-emerald-50 border border-emerald-200 rounded-lg px-4 py-2">
                <span class="text-sm text-emerald-800 font-medium">${escapeHtml(text)}</span>
                <button type="button" class="client-search-clear text-emerald-500 hover:text-emerald-700 p-1" title="Cambiar cliente">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                  </svg>
                </button>
              </div>`;
            selected.querySelector('.client-search-clear').addEventListener('click', (e) => {
              e.stopPropagation();
              selected.classList.add('hidden');
              if (searchRow) {
                searchRow.classList.remove('hidden');
                if (input) {
                  input.value = '';
                  input.focus();
                }
                if (hidden) hidden.value = '';
              }
            });
          }
        }
      } catch (err) {
        console.error('Error al obtener cliente genérico:', err);
      }
    });
  }
}

function buildClientSearch(container) {
  const fieldName = container.dataset.field || 'cliente_id_cliente';
  const placeholder = container.dataset.placeholder || 'Buscar cliente por nombre o DNI...';
  const initialId = container.dataset.initialId;
  const initialText = container.dataset.initialText;

  container.innerHTML = '';
  container.classList.add('relative');

  const hidden = document.createElement('input');
  hidden.type = 'hidden';
  hidden.name = fieldName;
  hidden.value = initialId || '';
  container.appendChild(hidden);

  const searchRow = document.createElement('div');
  searchRow.className = 'flex gap-2';
  container.appendChild(searchRow);

  const input = document.createElement('input');
  input.type = 'text';
  input.autocomplete = 'off';
  input.placeholder = placeholder;
  input.className = 'flex-1 min-w-0 rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none';
  searchRow.appendChild(input);

  const reniecBtn = document.createElement('button');
  reniecBtn.type = 'button';
  reniecBtn.className = 'reniec-search-btn px-2.5 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-stone-300 text-white rounded-lg text-sm transition disabled:cursor-not-allowed';
  reniecBtn.disabled = true;
  reniecBtn.title = 'Buscar DNI en RENIEC';
  reniecBtn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>';
  searchRow.appendChild(reniecBtn);

  let reniecLoading = false;
  reniecBtn.addEventListener('click', async function () {
    const dni = input.value.trim();
    if (!/^\d{8}$/.test(dni) || reniecLoading) return;

    reniecLoading = true;
    reniecBtn.disabled = true;
    reniecBtn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>';

    try {
      const res = await axios.post('/admin/reniec/consultar', { dni });

      if (res.data.success && res.data.exists) {
        const c = res.data.cliente;
        const text = `${c.nombre} ${c.apellido_paterno || ''} ${c.apellido_materno || ''} — ${c.dni}`;
        selectedClient = { id: c.id_cliente, text, registroWeb: Boolean(c.registro_web) };
        hidden.value = c.id_cliente;
        showSelected(text);
        results.classList.add('hidden');
        limpiarClienteData();
      } else if (res.data.success && !res.data.exists) {
        const data = res.data.data;
        const text = `${data.nombre} ${data.apellido_paterno} ${data.apellido_materno} — ${data.dni}`;
        results.classList.add('hidden');
        mostrarClienteDesdeApi(text, data);
      } else {
        showErrorToast(res.data.message || 'No se pudieron obtener los datos del DNI.');
      }
    } catch (err) {
      const msg = err.response?.data?.message || 'Error de conexión al consultar RENIEC.';
      showErrorToast(msg);
    } finally {
      reniecLoading = false;
      reniecBtn.disabled = false;
      reniecBtn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>';
    }
  });

  function mostrarClienteDesdeApi(text, data) {
    selected.innerHTML = `
      <div class="flex items-center justify-between bg-amber-50 border border-amber-200 rounded-lg px-4 py-2">
        <span class="text-sm text-amber-800 font-medium">
          <svg class="w-3.5 h-3.5 inline -mt-0.5 mr-1 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
          </svg>
          ${escapeHtml(text)}
        </span>
        <button type="button" class="reniec-clear text-amber-500 hover:text-amber-700 p-1" title="Cancelar">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>`;
    selected.classList.remove('hidden');
    searchRow.classList.add('hidden');

    selected.querySelector('.reniec-clear').addEventListener('click', () => {
      selected.classList.add('hidden');
      searchRow.classList.remove('hidden');
      input.value = '';
      input.focus();
      hidden.value = '';
      selectedClient = null;
      limpiarClienteData();
    });

    crearClienteData(data);
  }

  function crearClienteData(data) {
    limpiarClienteData();
    const form = container.closest('form');
    if (!form) return;

    const fields = ['dni', 'nombre', 'apellido_paterno', 'apellido_materno'];
    fields.forEach(field => {
      const el = document.createElement('input');
      el.type = 'hidden';
      el.name = `cliente_data[${field}]`;
      el.value = data[field] || '';
      el.dataset.reniecClienteData = '';
      form.appendChild(el);
    });
  }

  function limpiarClienteData() {
    document.querySelectorAll('[data-reniec-cliente-data]').forEach(el => el.remove());
  }

  function showErrorToast(message) {
    if (window.showToast) {
      window.showToast(message, 'error');
    } else {
      alert(message);
    }
  }

  const selected = document.createElement('div');
  selected.className = 'hidden';
  container.appendChild(selected);

  const results = document.createElement('div');
  results.className = 'hidden absolute left-0 right-0 top-full mt-1 bg-white rounded-lg shadow-lg border border-stone-200 z-50 max-h-60 overflow-y-auto';
  container.appendChild(results);

  let currentTimeout = null;
  let selectedClient = null;

  if (initialId && initialText) {
    selectedClient = { id: initialId, text: initialText, registroWeb: false };
    showSelected(initialText);
  }

  function showSelected(text) {
    searchRow.classList.add('hidden');
    selected.classList.remove('hidden');
    limpiarClienteData();
    const badge = selectedClient && selectedClient.registroWeb
      ? `<span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700 ml-2">Registrado</span>`
      : '';
    selected.innerHTML =
      `<div class="flex items-center justify-between bg-emerald-50 border border-emerald-200 rounded-lg px-4 py-2">
        <span class="text-sm text-emerald-800 font-medium">${escapeHtml(text)}${badge}</span>
        <button type="button" class="client-search-clear text-emerald-500 hover:text-emerald-700 p-1" title="Cambiar cliente">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>`;
    selected.querySelector('.client-search-clear').addEventListener('click', (e) => {
      e.stopPropagation();
      clearSelection();
    });
  }

  function showInput() {
    searchRow.classList.remove('hidden');
    selected.classList.add('hidden');
    input.value = '';
    input.focus();
  }

  function clearSelection() {
    selectedClient = null;
    hidden.value = '';
    limpiarClienteData();
    showInput();
  }

  input.addEventListener('input', function () {
    const q = this.value.trim();
    if (currentTimeout) clearTimeout(currentTimeout);

    reniecBtn.disabled = !/^\d{8}$/.test(q);

    if (q.length < 2) {
      results.classList.add('hidden');
      return;
    }

    currentTimeout = setTimeout(() => {
      axios.get('/admin/clientes/search', { params: { q } })
        .then(res => {
          results.innerHTML = '';
          if (res.data.length === 0) {
            const msg = document.createElement('div');
            msg.className = 'px-4 py-3 text-sm text-stone-400';
            msg.textContent = 'No se encontraron clientes';
            results.appendChild(msg);
          } else {
            res.data.forEach(c => {
              const item = document.createElement('button');
              item.type = 'button';
              const text = `${c.nombre} ${c.apellido_paterno}${c.apellido_materno ? ' ' + c.apellido_materno : ''} — ${c.dni}`;
              const registroWeb = Boolean(c.registro_web);
              const badgeHtml = registroWeb
                ? ` <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">Registrado</span>`
                : '';
              item.className = 'w-full text-left px-4 py-2.5 text-sm text-stone-700 hover:bg-emerald-50 hover:text-emerald-700 transition border-b border-stone-100 last:border-0';
              item.innerHTML = escapeHtml(text) + badgeHtml;
              item.addEventListener('click', () => {
                selectedClient = { id: c.id_cliente, text, registroWeb };
                hidden.value = c.id_cliente;
                showSelected(text);
                results.classList.add('hidden');
              });
              results.appendChild(item);
            });
          }
          results.classList.remove('hidden');
        })
        .catch(() => {
          results.classList.add('hidden');
        });
    }, 300);
  });

  document.addEventListener('click', function (e) {
    if (!container.contains(e.target)) {
      results.classList.add('hidden');
    }
  });

  input.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      results.classList.add('hidden');
    }
  });
}

function escapeHtml(text) {
  const d = document.createElement('div');
  d.textContent = text;
  return d.innerHTML;
}
