import { initGestorImagenes, sincronizarOrden } from './shared.js';

initGestorImagenes();

const form = document.querySelector('#gestor-imagenes')?.closest('form');
if (form) {
    form.addEventListener('submit', function () {
        sincronizarOrden(this);
    });
}
