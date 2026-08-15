const AdminToast = {
    show(message, type = 'success', duration = 3000) {
        const colors = {
            success: 'bg-emerald-600',
            error: 'bg-red-600',
            info: 'bg-blue-600',
            warning: 'bg-amber-500',
        };
        const existing = document.querySelector('.admin-toast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.className = `admin-toast fixed top-4 right-4 z-[60] ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg text-sm transition-all duration-300`;
        toast.style.transform = 'translateY(-100%)';
        toast.style.opacity = '0';
        toast.textContent = message;
        document.body.appendChild(toast);

        requestAnimationFrame(() => {
            toast.style.transform = 'translateY(0)';
            toast.style.opacity = '1';
        });

        setTimeout(() => {
            toast.style.transform = 'translateY(-100%)';
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    },
};

window.AdminToast = AdminToast;
