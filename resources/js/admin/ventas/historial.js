import { initHistorialFiltros } from '../historial/filtros';

function init() {
    initHistorialFiltros('ventas');
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
