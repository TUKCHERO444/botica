import axios from 'axios';
import AdminRouter from './router';

const AdminModal = {
    init() {
        this.$overlay = document.getElementById('modal-overlay');
        this.$container = document.getElementById('modal-container');
        if (!this.$overlay) return;
        this.bindEvents();
    },

    bindEvents() {
        document.addEventListener('click', (e) => {
            const trigger = e.target.closest('[data-modal]');
            if (trigger) {
                e.preventDefault();
                const url = trigger.getAttribute('data-modal');
                this.open(url);
            }

            if (e.target.closest('[data-close-modal]')) {
                this.close();
            }

            const delBtn = e.target.closest('[data-delete]');
            if (delBtn) {
                e.preventDefault();
                const url = delBtn.getAttribute('data-delete');
                this.confirmDelete(url);
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') this.close();
        });

        document.addEventListener('submit', async (e) => {
            const form = e.target.closest('#modal-container form');
            if (!form) return;
            e.preventDefault();
            await this.submitForm(form);
        });
    },

    async open(url) {
        try {
            const res = await axios.get(url);
            this.$container.innerHTML = res.data;
            this.$container.querySelectorAll('script').forEach((old) => {
                const s = document.createElement('script');
                if (old.src) {
                    s.src = old.src + (old.src.includes('?') ? '&' : '?') + '_t=' + Date.now();
                    s.type = 'module';
                    s.async = false;
                } else {
                    s.textContent = old.textContent;
                }
                old.parentNode.replaceChild(s, old);
            });
            this.$overlay.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        } catch (err) {
            console.error('Error al abrir modal:', err);
        }
    },

    close() {
        this.$overlay?.classList.add('hidden');
        if (this.$container) this.$container.innerHTML = '';
        document.body.classList.remove('overflow-hidden');
    },

    async submitForm(form) {
        const hiddenMethod = form.querySelector('input[name="_method"]')?.value;
        const originalMethod = (hiddenMethod || form.method).toUpperCase();
        const url = form.action;
        const data = new FormData(form);
        const submitBtn = form.querySelector('[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Guardando...';
        }

        try {
            const res = await axios({
                method: hiddenMethod ? 'POST' : originalMethod,
                url,
                data,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (res.data.success) {
                this.close();
                await AdminRouter.load(window.location.href, false);
                AdminToast.show(res.data.message, 'success');
            }
        } catch (err) {
            if (err.response?.status === 422) {
                if (err.response.data?.errors) {
                    this.renderErrors(err.response.data.errors);
                } else if (err.response.data?.message) {
                    AdminToast.show(err.response.data.message, 'error');
                } else {
                    AdminToast.show('Error de validación', 'error');
                }
            } else {
                AdminToast.show('Error inesperado', 'error');
            }
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalMethod === 'PUT' ? 'Actualizar' : 'Guardar';
            }
        }
    },

    confirmDelete(url) {
        if (!confirm('¿Estás seguro de desactivar este registro?')) return;

        axios({
            method: 'DELETE',
            url,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        }).then((res) => {
            if (res.data.success) {
                AdminRouter.load(window.location.href, false);
                AdminToast.show(res.data.message, 'success');
            }
        }).catch(() => {
            AdminToast.show('Error al eliminar', 'error');
        });
    },

    renderErrors(errors) {
        document.querySelectorAll('.field-error').forEach(el => el.remove());
        document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

        for (const [field, messages] of Object.entries(errors)) {
            const name = field.replace(/\.(\d+)\./g, '[$1]').replace(/\.(\d+)$/g, '[$1]');
            const input = document.querySelector(`[name="${name}"]`);
            if (input) {
                input.classList.add('is-invalid');
                input.classList.add('border-red-500');
                const errorEl = document.createElement('p');
                errorEl.className = 'field-error text-red-600 text-xs mt-1';
                errorEl.textContent = messages[0];
                input.parentNode.appendChild(errorEl);
            } else {
                AdminToast.show(messages[0], 'error');
            }
        }
    },
};

window.AdminModal = AdminModal;
export default AdminModal;
