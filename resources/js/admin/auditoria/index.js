import axios from 'axios';

const AdminAuditoria = {
    init() {
        this.bindFilters();
    },

    bindFilters() {
        const form = document.getElementById('auditoria-filters');
        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const params = new URLSearchParams(new FormData(form));
            const url = `${window.location.pathname}?${params.toString()}`;

            try {
                const res = await axios.get(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });

                const parser = new DOMParser();
                const doc = parser.parseFromString(res.data, 'text/html');
                const newTable = doc.getElementById('auditoria-table');
                const currentTable = document.getElementById('auditoria-table');

                if (newTable && currentTable) {
                    currentTable.innerHTML = newTable.innerHTML;
                }

                window.history.pushState({ url }, '', url);
            } catch (err) {
                console.error('Error al filtrar auditoría:', err);
            }
        });

        const selects = form.querySelectorAll('select, input[type="date"]');
        selects.forEach((el) => {
            el.addEventListener('change', () => {
                form.requestSubmit();
            });
        });
    },
};

document.addEventListener('DOMContentLoaded', () => {
    AdminAuditoria.init();
});
