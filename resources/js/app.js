import './bootstrap';
import './admin/toast';
import './admin/toggle';
import './admin/autocomplete';
import AdminRouter from './admin/router';
import AdminModal from './admin/modal';

document.addEventListener('DOMContentLoaded', () => {
    AdminRouter.init();
    AdminModal.init();
});
