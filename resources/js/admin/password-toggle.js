document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-toggle-password]');
    if (!button) return;

    event.preventDefault();

    const field = button.closest('[data-password-field]');
    const input = field?.querySelector('input');
    if (!input) return;

    const mostrar = input.type === 'password';
    input.type = mostrar ? 'text' : 'password';
    button.dataset.visible = mostrar ? 'true' : 'false';
    button.setAttribute('aria-pressed', mostrar ? 'true' : 'false');
    button.setAttribute('aria-label', mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña');
    button.title = mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña';

    button.querySelector('[data-icon-eye]')?.classList.toggle('hidden', mostrar);
    button.querySelector('[data-icon-eye-off]')?.classList.toggle('hidden', ! mostrar);

    input.focus();
    input.setSelectionRange?.(input.value.length, input.value.length);
});
