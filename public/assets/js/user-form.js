document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('userForm');
    if (!form) return;

    form.querySelectorAll('[data-password-toggle]').forEach(button => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.passwordToggle);
            const visible = input.type === 'password';
            input.type = visible ? 'text' : 'password';
            button.querySelector('.va-eye-open').classList.toggle('d-none', visible);
            button.querySelector('.va-eye-closed').classList.toggle('d-none', !visible);
            button.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
            button.setAttribute('aria-pressed', String(visible));
        });
    });

    form.addEventListener('submit', () => {
        if (form.checkValidity()) document.getElementById('saveUser').disabled = true;
    });
});
