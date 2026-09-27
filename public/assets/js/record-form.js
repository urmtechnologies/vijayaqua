document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-safe-submit]').forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) { event.preventDefault(); form.reportValidity(); return; }
            const button = form.querySelector('[data-submit-button]');
            button.disabled = true;
            form.querySelector('[data-submit-spinner]').classList.remove('d-none');
            form.querySelector('[data-submit-label]').textContent = 'Saving...';
        });
    });
});
