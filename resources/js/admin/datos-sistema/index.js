import axios from 'axios';

const form = document.getElementById('datos-sistema-form');

if (form) {
    const input = form.querySelector('[name="nombre_negocio"]');
    const submit = form.querySelector('[type="submit"]');
    const submitLabel = submit?.textContent ?? 'Guardar cambios';

    const sincronizarVistaPrevia = () => {
        const nombre = input?.value.trim() || 'Nombre de la empresa';

        document.querySelectorAll('[data-brand-name]').forEach((element) => {
            element.textContent = nombre;
        });
    };

    input?.addEventListener('input', sincronizarVistaPrevia);
    sincronizarVistaPrevia();

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (submit) {
            submit.disabled = true;
            submit.textContent = 'Guardando...';
        }

        try {
            const response = await axios.post(form.action, new FormData(form), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (! response.data.success) return;

            const nombre = response.data.nombre_negocio;

            if (input) input.value = nombre;

            document.querySelectorAll('[data-brand-name]').forEach((element) => {
                element.textContent = nombre;
            });

            const brand = document.getElementById('sidebar-brand');
            if (brand) {
                brand.textContent = nombre;
                brand.title = nombre;
            }

            form.querySelectorAll('.field-error').forEach((element) => element.remove());
            form.querySelectorAll('.is-invalid').forEach((element) => {
                element.classList.remove('is-invalid', 'border-red-500');
            });

            window.AdminToast?.show(response.data.message, 'success');
        } catch (error) {
            if (error.response?.status === 422 && error.response.data?.errors) {
                window.AdminModal?.renderErrors(error.response.data.errors);

                return;
            }

            window.AdminToast?.show(
                error.response?.data?.message ?? 'No se pudieron guardar los datos del sistema.',
                'error'
            );
        } finally {
            if (submit) {
                submit.disabled = false;
                submit.textContent = submitLabel;
            }
        }
    });
}
