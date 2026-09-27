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
});
