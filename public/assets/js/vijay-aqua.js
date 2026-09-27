document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('va-sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    const button = document.getElementById('sidebar-btn');
    if (!sidebar || !button) return;
    const toggle = () => {
        if (window.innerWidth < 992) document.body.classList.toggle('sidebar-enable');
        else document.body.dataset.sidebar = document.body.dataset.sidebar === 'small' ? 'large' : 'small';
    };
    button.addEventListener('click', toggle);
    backdrop?.addEventListener('click', () => document.body.classList.remove('sidebar-enable'));

    sidebar.querySelectorAll('.va-menu-toggle').forEach(menuToggle => {
        const menu = document.getElementById(menuToggle.getAttribute('aria-controls'));
        if (!menu) return;
        menuToggle.addEventListener('click', event => {
            event.preventDefault();
            const open = menuToggle.getAttribute('aria-expanded') !== 'true';
            menuToggle.setAttribute('aria-expanded', String(open));
            menu.classList.toggle('mm-show', open);
            menuToggle.parentElement.classList.toggle('mm-active', open);
        });
    });

    const dialog = document.getElementById('vaDeleteModal');
    const confirm = document.getElementById('vaDeleteConfirm');
    let selectedForm = null;
    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-va-delete]');
        if (!trigger || !dialog) return;
        selectedForm = trigger.closest('form');
        document.getElementById('vaDeleteName').textContent = trigger.dataset.vaDeleteName || 'This record';
        confirm.disabled = false;
        document.getElementById('vaDeleteSpinner').classList.add('d-none');
        document.getElementById('vaDeleteLabel').textContent = 'Yes, delete';
        bootstrap.Modal.getOrCreateInstance(dialog).show();
    });
    confirm?.addEventListener('click', () => {
        if (!selectedForm || confirm.disabled) return;
        confirm.disabled = true;
        document.getElementById('vaDeleteSpinner').classList.remove('d-none');
        document.getElementById('vaDeleteLabel').textContent = 'Deleting...';
        HTMLFormElement.prototype.submit.call(selectedForm);
    });
    dialog?.addEventListener('hidden.bs.modal', () => { selectedForm = null; });
});
