import axios from 'axios';
import AdminRouter from './router';

document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-toggle-status]');
    if (!btn) return;
    e.preventDefault();

    const url = btn.getAttribute('data-toggle-status');

    axios({
        method: 'PATCH',
        url,
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    }).then((res) => {
        if (res.data.success) {
            AdminRouter.load(window.location.href, false);
            AdminToast.show(res.data.message, 'success');
        }
    }).catch((err) => {
        if (err.response?.data?.message) {
            AdminToast.show(err.response.data.message, 'error');
        } else {
            AdminToast.show('Error al cambiar estado', 'error');
        }
    });
});
