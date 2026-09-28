document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-password-toggle]').forEach(button => {
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

    const form = document.getElementById('userForm');
    form?.addEventListener('submit', () => {
        if (form.checkValidity()) {
            const button = document.getElementById('saveUser');
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Saving...';
        }
    });
});
