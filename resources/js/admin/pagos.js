const round2 = (value) => Math.round((Number(value) || 0) * 100) / 100;

const money = (value) => 'S/ ' + round2(value).toFixed(2);

function parseMoney(text) {
  const limpio = String(text || '')
    .replace(/[^\d.,-]/g, '')
    .replace(',', '.');

  return Number.parseFloat(limpio) || 0;
}

/**
 * Pago de ventas y atenciones:
 * - Modo único: el cajero elige un método y se cobra el total, sin pedir monto.
 * - Modo mixto: se habilitan los importes para repartir el total entre métodos.
 */
export function initPagos({ rootSelector = '[data-pagos]' } = {}) {
  document.querySelectorAll(rootSelector).forEach(initPagoRoot);
}

function initPagoRoot(root) {
  if (root.dataset.pagosReady === '1') return;
  root.dataset.pagosReady = '1';

  const form = root.closest('form');
  const totalEl = document.querySelector(root.dataset.totalSource || '#total-venta');
  const inputs = Array.from(root.querySelectorAll('.pago-monto'));
  const buttons = Array.from(root.querySelectorAll('[data-metodo-btn]'));
  const detalle = root.querySelector('[data-pagos-detalle]');
  const mixtoBtn = root.querySelector('[data-pago-mixto]');
  const mixtoLabel = root.querySelector('[data-pago-mixto-label]');
  const ayuda = root.querySelector('[data-pagos-ayuda]');
  const badge = root.querySelector('[data-pagos-modo-badge]');
  const totalOut = root.querySelector('[data-pagos-total]');
  const pagadoOut = root.querySelector('[data-pagos-pagado]');
  const faltaOut = root.querySelector('[data-pagos-falta]');
  const estadoOut = root.querySelector('[data-pagos-estado]');
  const alerta = root.querySelector('[data-pagos-alerta]');

  const tieneMetodos = inputs.length > 0;
  let total = parseMoney(totalEl?.textContent);
  let modo = root.dataset.modo === 'mixto' ? 'mixto' : 'unico';
  let seleccionadoId = null;

  const inputDe = (id) => inputs.find((input) => String(input.dataset.metodoId) === String(id)) || null;
  const botonDe = (id) => buttons.find((btn) => String(btn.dataset.metodoId) === String(id)) || null;

  function limpiarAlerta() {
    if (!alerta) return;
    alerta.textContent = '';
    alerta.classList.add('hidden');
  }

  function mostrarAlerta(mensaje) {
    if (alerta) {
      alerta.textContent = mensaje;
      alerta.classList.remove('hidden');
    }

    if (window.AdminToast) {
      window.AdminToast.show(mensaje, 'error');
    }
  }

  function bloquearEnvio(event, mensaje, focusEl = null) {
    event.preventDefault();
    event.stopPropagation();
    mostrarAlerta(mensaje);
    focusEl?.focus();
  }

  function limpiarSeleccion() {
    buttons.forEach((btn) => {
      btn.dataset.selected = 'false';
      btn.setAttribute('aria-pressed', 'false');
    });
    seleccionadoId = null;
  }

  function limpiarMontos() {
    inputs.forEach((input) => {
      input.value = '';
    });
  }

  function bloquearBotones(bloquear) {
    buttons.forEach((btn) => {
      btn.disabled = bloquear;
      btn.setAttribute('aria-disabled', bloquear ? 'true' : 'false');
    });
  }

  function seleccionarMetodo(btn) {
    limpiarSeleccion();
    limpiarMontos();

    if (!btn) return;

    btn.dataset.selected = 'true';
    btn.setAttribute('aria-pressed', 'true');
    seleccionadoId = String(btn.dataset.metodoId);

    const input = inputDe(seleccionadoId);
    if (input) {
      input.value = total > 0 ? total.toFixed(2) : '';
    }
  }

  function pintarModo() {
    if (ayuda) {
      ayuda.textContent = modo === 'mixto'
        ? 'Distribuya el total entre dos o más métodos. Los botones quedan bloqueados mientras el pago mixto está activo.'
        : 'Seleccione un método para cobrar el total.';
    }

    if (badge) {
      badge.textContent = modo === 'mixto' ? 'Pago mixto' : 'Pago único';
      badge.classList.toggle('bg-amber-100', modo === 'mixto');
      badge.classList.toggle('text-amber-700', modo === 'mixto');
      badge.classList.toggle('bg-emerald-100', modo !== 'mixto');
      badge.classList.toggle('text-emerald-700', modo !== 'mixto');
    }

    if (mixtoLabel) {
      mixtoLabel.textContent = modo === 'mixto' ? 'Volver a pago único' : 'Pago mixto';
    }

    if (mixtoBtn) {
      mixtoBtn.dataset.activo = modo === 'mixto' ? 'true' : 'false';
    }
  }

  function recalcular() {
    if (modo === 'unico' && seleccionadoId) {
      const input = inputDe(seleccionadoId);
      if (input) {
        input.value = total > 0 ? total.toFixed(2) : '';
      }
    }

    const pagado = round2(
      inputs.reduce((suma, input) => suma + (Number.parseFloat(input.value) || 0), 0)
    );
    const falta = round2(total - pagado);

    if (totalOut) totalOut.textContent = money(total);
    if (pagadoOut) pagadoOut.textContent = money(pagado);

    if (faltaOut) {
      faltaOut.textContent = money(Math.abs(falta));

      if (falta > 0) {
        faltaOut.classList.remove('text-emerald-700', 'text-amber-600');
        faltaOut.classList.add('text-red-600');
      } else if (falta < 0) {
        faltaOut.classList.remove('text-red-600', 'text-emerald-700');
        faltaOut.classList.add('text-amber-600');
      } else {
        faltaOut.classList.remove('text-red-600', 'text-amber-600');
        faltaOut.classList.add('text-emerald-700');
      }
    }

    if (estadoOut) {
      estadoOut.textContent = falta > 0 ? 'Falta' : (falta < 0 ? 'Excede' : 'Pago completo');
    }

    return { pagado, falta };
  }

  function aplicarModo(nuevoModo, { preservarValores = false } = {}) {
    modo = nuevoModo;
    root.dataset.modo = modo;

    if (modo === 'mixto') {
      detalle?.classList.remove('hidden');
      bloquearBotones(true);
      limpiarSeleccion();

      if (!preservarValores) {
        limpiarMontos();
      }
    } else {
      detalle?.classList.add('hidden');
      bloquearBotones(false);

      if (!preservarValores) {
        limpiarSeleccion();
        limpiarMontos();
      }
    }

    pintarModo();
    recalcular();
  }

  function inicializarEstado() {
    const conMonto = inputs.filter((input) => (Number.parseFloat(input.value) || 0) > 0);

    if (modo === 'mixto' || conMonto.length > 1) {
      aplicarModo('mixto', { preservarValores: true });

      return;
    }

    detalle?.classList.add('hidden');
    bloquearBotones(false);
    limpiarSeleccion();

    if (conMonto.length === 1) {
      const id = conMonto[0].dataset.metodoId;
      const btn = botonDe(id);

      if (btn) {
        btn.dataset.selected = 'true';
        btn.setAttribute('aria-pressed', 'true');
        seleccionadoId = String(id);
      } else {
        // El pago existente usa un método que ya no está activo.
        limpiarMontos();
      }
    }

    pintarModo();
    recalcular();
  }

  buttons.forEach((btn) => {
    btn.addEventListener('click', () => {
      if (modo === 'mixto' || btn.disabled) return;

      limpiarAlerta();

      const id = String(btn.dataset.metodoId);

      if (seleccionadoId === id) {
        limpiarSeleccion();
        limpiarMontos();
        recalcular();

        return;
      }

      seleccionarMetodo(btn);
      recalcular();
    });
  });

  mixtoBtn?.addEventListener('click', () => {
    limpiarAlerta();
    aplicarModo(modo === 'mixto' ? 'unico' : 'mixto');
  });

  inputs.forEach((input) => {
    input.addEventListener('input', () => {
      if (modo !== 'mixto') return;

      limpiarAlerta();
      recalcular();
    });
  });

  root.querySelectorAll('[data-pago-resto]').forEach((button) => {
    button.addEventListener('click', () => {
      if (modo !== 'mixto') return;

      const row = button.closest('[data-pago-row]');
      const input = row?.querySelector('.pago-monto');
      if (!input) return;

      const { falta } = recalcular();
      input.value = falta > 0 ? falta.toFixed(2) : '';
      recalcular();
      input.focus();
      input.select?.();
    });
  });

  if (form) {
    form.addEventListener('total:changed', (event) => {
      const nuevoTotal = Number(event.detail?.total);
      total = Number.isFinite(nuevoTotal) ? round2(nuevoTotal) : parseMoney(totalEl?.textContent);
      recalcular();
    });

    form.addEventListener(
      'submit',
      (event) => {
        if (!tieneMetodos) return;

        limpiarAlerta();

        if (modo === 'unico') {
          if (!seleccionadoId) {
            bloquearEnvio(event, 'Seleccione un método de pago para cobrar el total.', buttons[0]);

            return;
          }

          if (total <= 0) {
            bloquearEnvio(event, 'El total a pagar debe ser mayor que cero.');

            return;
          }

          recalcular();

          return;
        }

        const { falta } = recalcular();
        const conMonto = inputs.filter((input) => (Number.parseFloat(input.value) || 0) > 0).length;

        if (conMonto < 2) {
          bloquearEnvio(
            event,
            'El pago mixto requiere al menos dos métodos con importe.',
            inputs.find((input) => (Number.parseFloat(input.value) || 0) <= 0)
          );

          return;
        }

        if (Math.abs(falta) >= 0.005) {
          const mensaje = falta > 0
            ? `Falta cubrir ${money(falta)} del total.`
            : `El desglose excede el total en ${money(Math.abs(falta))}.`;

          bloquearEnvio(
            event,
            mensaje,
            falta > 0 ? inputs.find((input) => (Number.parseFloat(input.value) || 0) <= 0) : null
          );
        }
      },
      true
    );
  }

  if (tieneMetodos) {
    inicializarEstado();
  }
}
