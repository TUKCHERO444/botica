document.addEventListener('DOMContentLoaded', () => {
  const sidebar = document.getElementById('sidebar');
  const backdrop = document.getElementById('sidebar-backdrop');
  const toggle = document.getElementById('sidebar-toggle');
  const close = document.getElementById('sidebar-close');

  if (!sidebar || !backdrop || !toggle || !close) return;

  function open() {
    sidebar.classList.remove('-translate-x-full');
    sidebar.classList.add('translate-x-0');
    backdrop.classList.remove('hidden');
  }

  function closeSidebar() {
    sidebar.classList.add('-translate-x-full');
    sidebar.classList.remove('translate-x-0');
    backdrop.classList.add('hidden');
  }

  toggle.addEventListener('click', open);
  close.addEventListener('click', closeSidebar);
  backdrop.addEventListener('click', closeSidebar);
});
