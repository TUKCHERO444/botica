import { initHistorialFiltros } from '../historial/filtros';

function init() {
    initHistorialFiltros('atenciones');
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
