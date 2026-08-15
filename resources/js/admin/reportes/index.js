function initReportes() {
    const tipoSelect = document.getElementById('reporte-tipo');
    const btnPreview = document.getElementById('btn-preview');
    const btnGenerate = document.getElementById('btn-generate');
    const previewContainer = document.getElementById('reporte-preview');

    if (!tipoSelect) return;

    function getFilters() {
        const params = new URLSearchParams();
        params.set('tipo', tipoSelect.value);

        const inputs = document.querySelectorAll('[id^="reporte-"]');
        inputs.forEach(el => {
            if (el.type === 'checkbox') {
                if (el.checked) params.set(el.name, el.value);
            } else if (el.value) {
                params.set(el.name, el.value);
            }
        });

        return params;
    }

    function toggleFilters() {
        const tipo = tipoSelect.value;
        document.querySelectorAll('.filtro-fecha, .filtro-contextual').forEach(el => {
            const showFor = (el.dataset.reporteShow || '').split(',');
            if (showFor.includes(tipo) || showFor.includes('*')) {
                el.classList.remove('hidden');
            } else {
                el.classList.add('hidden');
            }
        });
    }

    function updateFilterLabels() {
        const tipo = tipoSelect.value;
        const usuarioLabel = document.getElementById('filtro-usuario-label');
        if (!usuarioLabel) return;

        const labels = {
            ventas: 'Usuario (Vendedor)',
            atenciones: 'Usuario (Doctor/Asistente)',
            ingresos: 'Usuario (Recepcionista)',
            auditoria: 'Usuario',
            caja: 'Usuario',
        };
        usuarioLabel.textContent = labels[tipo] || 'Usuario';
    }

    tipoSelect.addEventListener('change', () => {
        toggleFilters();
        updateFilterLabels();
        if (!previewContainer.classList.contains('hidden')) {
            previewContainer.classList.add('hidden');
        }
    });

    toggleFilters();
    updateFilterLabels();

    btnPreview?.addEventListener('click', async () => {
        const params = getFilters();
        previewContainer.classList.remove('hidden');
        previewContainer.innerHTML = '<p class="text-stone-500 text-sm py-4 text-center">Cargando vista previa...</p>';

        try {
            const response = await axios.get(`/admin/reportes/preview?${params.toString()}`);
            previewContainer.innerHTML = `<div class="bg-white rounded-xl shadow-sm border border-stone-200 p-4 lg:p-6 overflow-auto">${response.data}</div>`;
        } catch (error) {
            previewContainer.innerHTML = '<p class="text-red-600 text-sm py-4 text-center">Error al cargar la vista previa.</p>';
        }
    });

    btnGenerate?.addEventListener('click', () => {
        const params = getFilters();
        const url = `/admin/reportes/generate?${params.toString()}`;
        window.open(url, '_blank');
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initReportes);
} else {
    initReportes();
}

document.addEventListener('panel-loaded', initReportes);
