import axios from 'axios';

export function initReniecSearch(dniInputId, btnId, fields) {
  const dniInput = document.getElementById(dniInputId);
  const btnBuscar = document.getElementById(btnId);
  if (!dniInput || !btnBuscar) return;

  dniInput.addEventListener('input', function () {
    btnBuscar.disabled = !/^\d{8}$/.test(this.value);
  });

  btnBuscar.addEventListener('click', async function () {
    const dni = dniInput.value.trim();
    if (!/^\d{8}$/.test(dni)) return;

    btnBuscar.disabled = true;
    btnBuscar.innerHTML = '<svg class="w-4 h-4 animate-spin inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>';

    try {
      const isPublic = btnBuscar.dataset.public === 'true';
      const url = isPublic ? '/api/reniec/consultar' : '/admin/reniec/consultar';
      const res = await axios.post(url, { dni });

      if (res.data.success) {
        const data = isPublic ? res.data.data : res.data.data || res.data.cliente;
        fields.forEach(field => {
          const el = document.querySelector(`[name="${field}"]`);
          if (el && data[field]) el.value = data[field];
        });
      } else {
        alert(res.data.message || 'No se pudieron obtener los datos del DNI.');
      }
    } catch (err) {
      const msg = err.response?.data?.message || 'Error al consultar RENIEC.';
      alert(msg);
    } finally {
      btnBuscar.disabled = false;
      btnBuscar.innerHTML = '<svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg> Buscar DNI';
    }
  });
}
