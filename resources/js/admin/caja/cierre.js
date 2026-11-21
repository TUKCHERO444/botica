function initCierreCaja() {
    const form = document.getElementById('form-cierre-caja');
    if (!form || form.dataset.cierreReady === '1') return;
    form.dataset.cierreReady = '1';

    const esperado = Number(form.dataset.esperado) || 0;
    const pasoConteo = document.getElementById('cierre-paso-conteo');
    const pasoRevision = document.getElementById('cierre-paso-revision');
    const radiosModo = Array.from(form.querySelectorAll('input[name="modo_conteo"]'));
    const bloqueRapido = document.getElementById('bloque-conteo-rapido');
    const bloqueDetallado = document.getElementById('bloque-conteo-detallado');
    const quickInput = document.getElementById('conteo-rapido');
    const detailInputs = Array.from(form.querySelectorAll('[data-denominacion]'));
    const hiddenContado = document.getElementById('efectivo-contado');
    const totalContado = document.getElementById('total-contado');
    const btnRevisar = document.getElementById('btn-revisar-cierre');
    const btnVolver = document.getElementById('btn-volver-conteo');
    const btnLimpiar = document.getElementById('btn-limpiar-conteo');

    const revisionEsperado = document.getElementById('revision-esperado');
    const revisionContado = document.getElementById('revision-contado');
    const revisionDiferencia = document.getElementById('revision-diferencia');
    const revisionDiferenciaLabel = document.getElementById('revision-diferencia-label');
    const bloqueDiferencia = document.getElementById('bloque-diferencia');
    const motivo = document.getElementById('motivo-diferencia');
    const observacion = document.getElementById('observacion-cierre');
    const labelObservacion = document.getElementById('label-observacion');
    const ayudaObservacion = document.getElementById('ayuda-observacion');
    const bloqueCero = document.getElementById('bloque-cero');
    const confirmoCero = document.getElementById('confirmo-cero');

    if (!pasoConteo || !pasoRevision || !quickInput || !hiddenContado || !totalContado) return;

    const round2 = (value) => Math.round((Number(value) || 0) * 100) / 100;
    const money = (value) => 'S/ ' + round2(value).toFixed(2);

    let modo = radiosModo.find((radio) => radio.checked)?.value || 'rapido';

    function toast(mensaje) {
        if (window.AdminToast) {
            window.AdminToast.show(mensaje, 'error');
        }
    }

    function totalDetallado() {
        let total = 0;

        detailInputs.forEach((input) => {
            const denominacion = Number.parseFloat(input.dataset.denominacion) || 0;
            const cantidad = Math.max(0, Number.parseInt(input.value, 10) || 0);
            const subtotal = round2(denominacion * cantidad);
            total += subtotal;

            const celda = form.querySelector(`[data-subtotal="${input.dataset.denominacion}"]`);
            if (celda) {
                celda.textContent = money(subtotal);
            }
        });

        return round2(total);
    }

    function totalActual() {
        return modo === 'rapido'
            ? round2(Number.parseFloat(quickInput.value) || 0)
            : totalDetallado();
    }

    function actualizarTotal() {
        const total = totalActual();
        totalContado.textContent = money(total);
        hiddenContado.value = total.toFixed(2);
    }

    function aplicarModo(nuevoModo) {
        modo = nuevoModo;

        bloqueRapido?.classList.toggle('hidden', modo !== 'rapido');
        bloqueDetallado?.classList.toggle('hidden', modo !== 'detallado');

        if (modo === 'rapido' && quickInput.value.trim() === '') {
            const detalle = totalDetallado();
            if (detalle > 0) {
                quickInput.value = detalle.toFixed(2);
            }
        }

        actualizarTotal();
    }

    radiosModo.forEach((radio) => {
        radio.addEventListener('change', () => {
            if (radio.checked) {
                aplicarModo(radio.value);
            }
        });
    });

    quickInput.addEventListener('input', actualizarTotal);

    detailInputs.forEach((input) => {
        input.addEventListener('input', () => {
            if (modo === 'detallado') {
                actualizarTotal();
            }
        });
    });

    btnLimpiar?.addEventListener('click', () => {
        detailInputs.forEach((input) => {
            input.value = '';
        });
        actualizarTotal();
        detailInputs[0]?.focus();
    });

    btnRevisar?.addEventListener('click', () => {
        if (modo === 'rapido' && quickInput.value.trim() === '') {
            toast('Ingrese el efectivo contado.');
            quickInput.focus();
            return;
        }

        const total = totalActual();

        if (total < 0) {
            toast('El efectivo contado no puede ser negativo.');
            return;
        }

        hiddenContado.value = total.toFixed(2);

        const diferencia = round2(total - esperado);
        const hayDiferencia = Math.abs(diferencia) >= 0.005;

        revisionEsperado.textContent = money(esperado);
        revisionContado.textContent = money(total);
        revisionDiferencia.textContent = money(diferencia);
        revisionDiferencia.className = 'text-lg font-bold ' + (hayDiferencia ? 'text-red-600' : 'text-emerald-600');
        revisionDiferenciaLabel.textContent = diferencia === 0
            ? 'Cuadra'
            : (diferencia < 0 ? 'Faltante' : 'Sobrante');

        bloqueDiferencia.classList.toggle('hidden', !hayDiferencia);
        motivo.disabled = !hayDiferencia;
        motivo.required = hayDiferencia;
        observacion.required = hayDiferencia;

        if (hayDiferencia) {
            labelObservacion.innerHTML = 'Observación del cierre <span class="text-red-500">*</span>';
            ayudaObservacion.textContent = 'Obligatoria para justificar la diferencia.';
        } else {
            motivo.value = '';
            labelObservacion.textContent = 'Observación del cierre (opcional)';
            ayudaObservacion.textContent = 'Opcional: alguna nota del turno.';
        }

        const esCero = total === 0;
        bloqueCero.classList.toggle('hidden', !esCero);

        if (!esCero) {
            confirmoCero.checked = false;
        }

        pasoConteo.classList.add('hidden');
        pasoRevision.classList.remove('hidden');
        pasoRevision.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    btnVolver?.addEventListener('click', () => {
        pasoRevision.classList.add('hidden');
        pasoConteo.classList.remove('hidden');
    });

    form.addEventListener('submit', (event) => {
        // Si el usuario intenta enviar desde el paso de conteo, primero revisamos.
        if (pasoRevision.classList.contains('hidden')) {
            event.preventDefault();
            event.stopImmediatePropagation();
            btnRevisar?.click();
            return;
        }

        const total = round2(Number(hiddenContado.value) || 0);
        const diferencia = round2(total - esperado);
        const hayDiferencia = Math.abs(diferencia) >= 0.005;

        if (hayDiferencia) {
            if (!motivo.value) {
                event.preventDefault();
                event.stopImmediatePropagation();
                toast('Seleccione el motivo de la diferencia.');
                motivo.focus();
                return;
            }

            if (observacion.value.trim() === '') {
                event.preventDefault();
                event.stopImmediatePropagation();
                toast('Explique la diferencia en la observación.');
                observacion.focus();
                return;
            }
        }

        if (total === 0 && !confirmoCero.checked) {
            event.preventDefault();
            event.stopImmediatePropagation();
            toast('Confirme que el cajón quedó en cero para continuar.');
            confirmoCero.focus();
        }
    }, true);

    aplicarModo(modo);
    quickInput.focus();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCierreCaja);
} else {
    initCierreCaja();
}
