import axios from 'axios';
import AdminRouter from '../router';

function initCaja() {
    const btnCerrar = document.getElementById('btn-cerrar-caja');
    if (btnCerrar) {
        btnCerrar.addEventListener('click', async (e) => {
            e.preventDefault();
            if (!confirm('¿Está seguro de cerrar la caja? Esta acción no se puede deshacer.')) return;

            try {
                const res = await axios({
                    method: 'POST',
                    url: btnCerrar.getAttribute('data-action'),
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });

                if (res.data.success) {
                    await AdminRouter.load(window.location.href, false);
                    AdminToast.show(res.data.message, 'success');
                }
            } catch (err) {
                if (err.response?.data?.message) {
                    AdminToast.show(err.response.data.message, 'error');
                } else {
                    AdminToast.show('Error al cerrar la caja', 'error');
                }
            }
        });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCaja);
} else {
    initCaja();
}
