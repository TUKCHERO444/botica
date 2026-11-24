import axios from 'axios';

/**
 * Filtros del historial (ventas y atenciones).
 *
 * - Reemplaza solo el contenedor de la tabla para no perder el foco.
 * - Refleja los filtros en la URL con pushState.
 * - Si se indica una fecha exacta, el rango se deshabilita y se vacía.
 */
export function initHistorialFiltros(prefix) {
    const form = document.getElementById(`${prefix}-historial-filtros`);
    const contenedor = document.getElementById(`${prefix}-historial-tabla`);

    if (!form || !contenedor) return;

    let timer;

    const aplicar = async () => {
        const fecha = form.querySelector('input[name="fecha"]');
        const rango = form.querySelectorAll('input[name="desde"], input[name="hasta"]');

        rango.forEach((input) => {
            input.disabled = Boolean(fecha?.value);
            if (fecha?.value) input.value = '';
        });

        const params = new URLSearchParams(new FormData(form));
        [...params.entries()].forEach(([clave, valor]) => {
            if (!valor) params.delete(clave);
        });

        const url = `${window.location.pathname}${params.toString() ? '?' + params : ''}`;

        try {
            const res = await axios.get(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            const doc = new DOMParser().parseFromString(res.data, 'text/html');
            const nueva = doc.getElementById(`${prefix}-historial-tabla`);

            if (nueva) contenedor.innerHTML = nueva.innerHTML;

            window.history.pushState({ url }, '', url);
        } catch (error) {
            window.AdminToast?.show('No se pudo aplicar el filtro.', 'error');
        }
    };

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        aplicar();
    });

    form.querySelectorAll('select, input[type="date"]').forEach((input) => {
        input.addEventListener('change', aplicar);
    });

    form.querySelector('input[name="q"]')?.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(aplicar, 500);
    });
}
