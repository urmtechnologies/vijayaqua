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
});
