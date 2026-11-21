import axios from 'axios';
import '../modal';
import AdminRouter from '../router';

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('[data-conciliar-form]');
    if (!form) return;

    event.preventDefault();

    const button = form.querySelector('button[type="submit"]');
    const originalText = button?.textContent;

    if (button) {
        button.disabled = true;
        button.textContent = 'Conciliando...';
    }

    try {
        const response = await axios.post(form.action, new FormData(form), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (response.data.success) {
            window.AdminToast?.show(response.data.message, 'success');
            await AdminRouter.load(window.location.href, false);
        } else {
            window.AdminToast?.show(response.data.message || 'No se pudo conciliar.', 'error');
        }
    } catch (error) {
        const message = error.response?.data?.message || 'Error al ejecutar la conciliación.';
        window.AdminToast?.show(message, 'error');
    } finally {
        if (button) {
            button.disabled = false;
            button.textContent = originalText;
        }
    }
});
