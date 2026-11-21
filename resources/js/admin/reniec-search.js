import axios from 'axios';

export function initReniecSearch(dniInputId, btnId, fields, verifyBtnId = null) {
  const dniInput = document.getElementById(dniInputId);
  const btnBuscar = document.getElementById(btnId);
  if (!dniInput || !btnBuscar) return;

  const verifyBtn = verifyBtnId ? document.getElementById(verifyBtnId) : null;
  const esPublico = btnBuscar.dataset.public === 'true';
  let verificado = false;
  let dniVerificado = '';
  let cargando = false;
  let aviso = null;
  let camposAutocompletados = false;

  if (verifyBtn) {
    verifyBtn.disabled = !/^\d{8}$/.test(dniInput.value.trim());
    btnBuscar.disabled = true;
  } else {
    btnBuscar.disabled = !/^\d{8}$/.test(dniInput.value.trim());
  }

  dniInput.addEventListener('input', function () {
    const dni = this.value.trim();
    const esDni = /^\d{8}$/.test(dni);

    if (dni !== dniVerificado) {
      reiniciarVerificacion();
    }

    if (verifyBtn) {
      verifyBtn.disabled = !esDni || cargando;
    } else {
      btnBuscar.disabled = !esDni;
    }
  });

  if (verifyBtn) {
    verifyBtn.addEventListener('click', async function () {
      const dni = dniInput.value.trim();
      if (!/^\d{8}$/.test(dni) || cargando) return;

      cargando = true;
      verifyBtn.disabled = true;
      const htmlOriginal = verifyBtn.innerHTML;
      verifyBtn.innerHTML = spinner();

      try {
        const url = esPublico ? '/api/reniec/verificar' : '/admin/clientes/verificar-dni';
        const res = await axios.post(url, { dni });

        if (res.data.success && res.data.exists) {
          verificado = false;
          dniVerificado = dni;
          btnBuscar.disabled = true;

          if (esPublico) {
            mostrarAviso(
              'Este DNI ya está registrado. Acércate a la botica para habilitar tu cuenta.',
              'error'
            );
          } else {
            const cliente = res.data.cliente || {};
            llenarCampos(cliente);
            mostrarAviso(
              `Este cliente ya está registrado: ${textoCliente(cliente)}. Búscalo en el listado para editarlo.`,
              'warning'
            );
          }

          habilitarEnvio(false);
        } else {
          verificado = true;
          dniVerificado = dni;
          btnBuscar.disabled = false;
          habilitarEnvio(true);
          mostrarAviso('Cliente no registrado. Ya puedes buscar el DNI en RENIEC.', 'info');
        }
      } catch (err) {
        mostrarAviso(
          err.response?.data?.message || 'Error al verificar el DNI.',
          'error'
        );
        habilitarEnvio(true);
      } finally {
        cargando = false;
        verifyBtn.innerHTML = htmlOriginal;
        const actual = dniInput.value.trim();
        verifyBtn.disabled = !/^\d{8}$/.test(actual) || (actual === dniVerificado);
      }
    });
  }

  btnBuscar.addEventListener('click', async function () {
    const dni = dniInput.value.trim();
    if (!/^\d{8}$/.test(dni)) return;

    if (verifyBtn && (!verificado || dniVerificado !== dni)) {
      mostrarAviso('Primero verifica el cliente en la base de datos.', 'info');
      return;
    }

    btnBuscar.disabled = true;
    const htmlOriginal = btnBuscar.innerHTML;
    btnBuscar.innerHTML = spinner();

    try {
      const url = esPublico ? '/api/reniec/consultar' : '/admin/reniec/consultar';
      const res = await axios.post(url, { dni });

      if (res.data.success && res.data.exists) {
        if (esPublico) {
          mostrarAviso(
            res.data.message ||
              'Este DNI ya está registrado. Acércate a la botica para habilitar tu cuenta.',
            'error'
          );
          habilitarEnvio(false);
        } else {
          const cliente = res.data.cliente || {};
          llenarCampos(cliente);
          mostrarAviso(
            `Este cliente ya está registrado: ${textoCliente(cliente)}. Búscalo en el listado para editarlo.`,
            'warning'
          );
          habilitarEnvio(false);
        }
      } else if (res.data.success) {
        const data = esPublico ? res.data.data : res.data.data || res.data.cliente;
        if (data) {
          llenarCampos(data);
        }
        limpiarAviso();
        habilitarEnvio(true);
        btnBuscar.disabled = true;
      } else {
        mostrarAviso(
          res.data.message || 'No se pudieron obtener los datos del DNI.',
          'error'
        );
      }
    } catch (err) {
      mostrarAviso(
        err.response?.data?.message || 'Error al consultar RENIEC.',
        'error'
      );
      btnBuscar.disabled = !(verificado && dniVerificado === dni);
    } finally {
      btnBuscar.innerHTML = htmlOriginal;
    }
  });

  function reiniciarVerificacion() {
    verificado = false;
    dniVerificado = '';
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

  function textoCliente(cliente) {
    const nombre = [
      cliente.nombre,
      cliente.apellido_paterno,
      cliente.apellido_materno,
    ].filter(Boolean).join(' ');
    const dni = cliente.dni ? ` — ${cliente.dni}` : '';

    return `${nombre}${dni}`;
  }

  function mostrarAviso(mensaje, tipo = 'info') {
    if (!aviso) {
      aviso = document.createElement('p');
      aviso.className = 'text-xs mt-1';
      (dniInput.closest('div') || dniInput).after(aviso);
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
    const form = dniInput.closest('form');
    if (!form) return;

    form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(btn => {
      btn.disabled = !habilitar;
    });
  }

  function spinner() {
    return '<svg class="w-4 h-4 animate-spin inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>';
  }
}
