import { initGestorImagenes, sincronizarOrden } from './shared.js';
import { initPresentaciones } from './presentaciones.js';

initGestorImagenes();
initPresentaciones();

const form = document.querySelector('#gestor-imagenes')?.closest('form');
if (form) {
    form.addEventListener('submit', function () {
        sincronizarOrden(this);
    });
}
