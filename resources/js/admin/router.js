import axios from 'axios';

const AdminRouter = {
    init() {
        this.$panel = document.getElementById('panel-content');
        if (!this.$panel) return;
        this.bindEvents();
        this.highlightActiveLink();
    },

    bindEvents() {
        document.addEventListener('click', (e) => {
            const link = e.target.closest('[data-panel]');
            if (link) {
                e.preventDefault();
                this.navigate(link.getAttribute('data-panel'));
            }
        });

        window.addEventListener('popstate', (e) => {
            if (e.state?.url) {
                this.load(e.state.url, false);
            }
        });
    },

    highlightActiveLink() {
        document.querySelectorAll('[data-panel]').forEach((link) => {
            link.classList.remove('bg-emerald-700', 'text-white');
            if (link.getAttribute('data-panel') === window.location.pathname) {
                link.classList.add('bg-emerald-700', 'text-white');
            }
        });
    },

    navigate(url) {
        this.load(url, true);
    },

    async load(url, pushState = true) {
        try {
            const res = await axios.get(url);
            this.$panel.innerHTML = res.data;
            this.$panel.querySelectorAll('script').forEach((old) => {
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
            if (pushState) {
                window.history.pushState({ url }, '', url);
            }
            this.highlightActiveLink();
            document.getElementById('sidebar')?.classList.add('-translate-x-full');
            document.getElementById('sidebar')?.classList.remove('translate-x-0');
            document.getElementById('sidebar-backdrop')?.classList.add('hidden');
            window.scrollTo(0, 0);
            document.dispatchEvent(new CustomEvent('panel-loaded'));
        } catch (err) {
            console.error('Error al cargar panel:', err);
        }
    },
};

export default AdminRouter;
