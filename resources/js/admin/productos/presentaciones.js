function sincronizarPaso(row) {
    const checkbox = row.querySelector('.paso-avanzado');
    const wrap = row.querySelector('.paso-wrap');
    const input = wrap?.querySelector('input[name$="[paso]"]');

    if (!checkbox || !wrap || !input) return;

    const activo = checkbox.checked;
    wrap.classList.toggle('hidden', !activo);

    if (!activo) {
        input.value = '1';
    }
}

function configurarPaso(row) {
    const checkbox = row.querySelector('.paso-avanzado');
    if (!checkbox) return;

    checkbox.addEventListener('change', () => sincronizarPaso(row));
    sincronizarPaso(row);
}

function initBasePaso() {
    const checkbox = document.getElementById('base-paso-avanzado');
    const wrap = document.getElementById('base-paso-wrap');
    const input = wrap?.querySelector('input[name="presentacion_base_paso"]');

    if (!checkbox || !wrap || !input) return;

    const sincronizar = () => {
        wrap.classList.toggle('hidden', !checkbox.checked);

        if (!checkbox.checked) {
            input.value = '1';
        }
    };

    checkbox.addEventListener('change', sincronizar);
    sincronizar();
}

export function initPresentaciones() {
    const container = document.getElementById('presentaciones-container');
    const btnAgregar = document.getElementById('agregar-presentacion');
    const template = document.getElementById('presentacion-template');

    if (!container || !btnAgregar || !template) return;

    let nextIndex = container.querySelectorAll('.presentacion-row').length;

    container.querySelectorAll('.presentacion-row').forEach(configurarPaso);

    btnAgregar.addEventListener('click', () => {
        const fragment = template.content.cloneNode(true);
        const row = fragment.firstElementChild;

        row.querySelectorAll('[name]').forEach((el) => {
            el.name = el.name.replaceAll('__INDEX__', nextIndex);
        });

        container.appendChild(row);
        configurarPaso(row);
        nextIndex++;
    });

    container.addEventListener('click', (e) => {
        const btn = e.target.closest('.eliminar-presentacion');
        if (!btn) return;

        btn.closest('.presentacion-row')?.remove();
    });

    initBasePaso();
}
