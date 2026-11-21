import axios from 'axios';

export function initSunatSearch(rucInputId, btnId, fields, verifyBtnId = null) {
  const rucInput = document.getElementById(rucInputId);
  const btnBuscar = document.getElementById(btnId);
  if (!rucInput || !btnBuscar) return;

  const verifyBtn = verifyBtnId ? document.getElementById(verifyBtnId) : null;
  let verificado = false;
  let rucVerificado = '';
  let cargando = false;
  let aviso = null;
  let camposAutocompletados = false;

  if (verifyBtn) {
    verifyBtn.disabled = !/^\d{11}$/.test(rucInput.value.trim());
    btnBuscar.disabled = true;
  } else {
    btnBuscar.disabled = !/^\d{11}$/.test(rucInput.value.trim());
  }

  rucInput.addEventListener('input', function () {
    const ruc = this.value.trim();
    const esRuc = /^\d{11}$/.test(ruc);

    if (ruc !== rucVerificado) {
      reiniciarVerificacion();
    }

    if (verifyBtn) {
      verifyBtn.disabled = !esRuc || cargando;
    } else {
      btnBuscar.disabled = !esRuc;
    }
  });

  if (verifyBtn) {
    verifyBtn.addEventListener('click', async function () {
      const ruc = rucInput.value.trim();
      if (!/^\d{11}$/.test(ruc) || cargando) return;

      cargando = true;
      verifyBtn.disabled = true;
      const htmlOriginal = verifyBtn.textContent;
      verifyBtn.innerHTML = spinner();

      try {
        const res = await axios.post('/admin/proveedores/verificar-ruc', { ruc });

        if (res.data.success && res.data.exists) {
          verificado = false;
          rucVerificado = ruc;
          btnBuscar.disabled = true;

          const proveedor = res.data.proveedor || {};
          llenarCampos(proveedor);
          mostrarAviso(
            `Este proveedor ya está registrado: ${proveedor.razon_social || ''} — ${proveedor.ruc || ruc}. Búscalo en el listado para editarlo.`,
            'warning'
          );
          habilitarEnvio(false);
        } else {
          verificado = true;
          rucVerificado = ruc;
          btnBuscar.disabled = false;
          habilitarEnvio(true);
          mostrarAviso('Proveedor no registrado. Ya puedes buscar el RUC en SUNAT.', 'info');
        }
      } catch (err) {
        mostrarAviso(
          err.response?.data?.message || 'Error al verificar el RUC.',
          'error'
        );
        habilitarEnvio(true);
      } finally {
        cargando = false;
        verifyBtn.innerHTML = htmlOriginal;
        const actual = rucInput.value.trim();
        verifyBtn.disabled = !/^\d{11}$/.test(actual) || (actual === rucVerificado);
      }
    });
  }

  btnBuscar.addEventListener('click', async function () {
    const ruc = rucInput.value.trim();
    if (!/^\d{11}$/.test(ruc)) return;

    if (verifyBtn && (!verificado || rucVerificado !== ruc)) {
      mostrarAviso('Primero verifica el proveedor en la base de datos.', 'info');
      return;
    }

    btnBuscar.disabled = true;
    const htmlOriginal = btnBuscar.innerHTML;
    btnBuscar.innerHTML = spinner();

    try {
      const res = await axios.post('/admin/sunat/consultar', { ruc });

      if (res.data.success) {
        if (res.data.exists) {
          const proveedor = res.data.proveedor || {};
          llenarCampos(proveedor);
          mostrarAviso(
            `Este proveedor ya está registrado: ${proveedor.razon_social || ''} — ${proveedor.ruc || ruc}. Búscalo en el listado para editarlo.`,
            'warning'
          );
          habilitarEnvio(false);
        } else {
          const data = res.data.data || {};
          llenarCampos(data);
          limpiarAviso();
          habilitarEnvio(true);
          btnBuscar.disabled = true;
        }
      } else {
        mostrarAviso(
          res.data.message || 'No se pudieron obtener los datos del RUC.',
          'error'
        );
        btnBuscar.disabled = !(verificado && rucVerificado === ruc);
      }
    } catch (err) {
      mostrarAviso(
        err.response?.data?.message || 'Error al consultar SUNAT.',
        'error'
      );
      btnBuscar.disabled = !(verificado && rucVerificado === ruc);
    } finally {
      btnBuscar.innerHTML = htmlOriginal;
    }
  });

  function reiniciarVerificacion() {
    verificado = false;
    rucVerificado = '';
    btnBuscar.disabled = true;

    if (camposAutocompletados) {
      fields.forEach(field => {
        const el = document.querySelector(`[name="${field}"]`);
        if (el) el.value = '';
      });
      camposAutocompletados = false;
    }

    limpiarAviso();
    habilitarEnvio(true);
  }

  function llenarCampos(data) {
    fields.forEach(field => {
      const el = document.querySelector(`[name="${field}"]`);
      if (el && data[field] !== undefined && data[field] !== null) {
        el.value = data[field];
        camposAutocompletados = true;
      }
    });
  }

  function mostrarAviso(mensaje, tipo = 'info') {
    if (!aviso) {
      aviso = document.createElement('p');
      aviso.className = 'text-xs mt-1';
      (rucInput.closest('div') || rucInput).after(aviso);
    }

    aviso.textContent = mensaje;
    aviso.classList.remove('text-red-600', 'text-amber-600', 'text-emerald-600');
    aviso.classList.add(
      tipo === 'error' ? 'text-red-600' : tipo === 'warning' ? 'text-amber-600' : 'text-emerald-600'
    );
  }

  function limpiarAviso() {
    if (aviso) {
      aviso.remove();
      aviso = null;
    }
  }

  function habilitarEnvio(habilitar) {
    const form = rucInput.closest('form');
    if (!form) return;

    form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(btn => {
      btn.disabled = !habilitar;
    });
  }

  function spinner() {
    return '<svg class="w-4 h-4 animate-spin inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>';
  }
}
