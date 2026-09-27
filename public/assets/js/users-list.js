document.addEventListener('DOMContentLoaded', () => {
    const target = document.getElementById('userResults');
    const desktop = document.getElementById('desktopFilters');
    const mobile = document.getElementById('mobileFilterForm');
    if (!target || !desktop || !mobile) return;
    let pending = null;
    let timer;

    function syncForms(url) {
        for (const form of [desktop, mobile]) {
            form.elements.search.value = url.searchParams.get('search') || '';
            form.elements.sort.value = url.searchParams.get('sort') || 'newest';
            form.elements.role.value = url.searchParams.get('role') || '';
        }
    }

    async function load(url, push = true) {
        pending?.abort();
        const controller = new AbortController();
        pending = controller;
        target.classList.add('is-loading');
        try {
            const response = await fetch(url, {
                signal: controller.signal,
                headers: {'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html'},
                credentials: 'same-origin',
            });
            if (response.status === 401 || response.redirected) { window.location.assign(response.url); return; }
            if (!response.ok) throw new Error('Unable to load users.');
            const html = await response.text();
            if (controller.signal.aborted) return;
            target.innerHTML = html;
            target.classList.remove('is-loading');
            if (push && url.href !== location.href) history.pushState({}, '', url);
            syncForms(url);
            const canvas = document.getElementById('mobileFilters');
            if (window.bootstrap && canvas) bootstrap.Offcanvas.getInstance(canvas)?.hide();
        } catch (error) {
            if (error.name !== 'AbortError') {
                target.classList.remove('is-loading');
                target.querySelector('.va-load-error')?.remove();
                target.insertAdjacentHTML('afterbegin', '<div class="alert alert-danger va-load-error" role="alert">Unable to load users. Please retry.</div>');
            }
        } finally {
            if (pending === controller) pending = null;
        }
    }

    function filter(form) {
        const url = new URL(form.action);
        const params = new URLSearchParams(new FormData(form));
        for (const [key, value] of params) if (String(value).trim()) url.searchParams.set(key, String(value).trim());
        load(url);
    }

    for (const form of [desktop, mobile]) {
        form.addEventListener('submit', (event) => { event.preventDefault(); clearTimeout(timer); filter(form); });
        form.elements.sort.addEventListener('change', () => filter(form));
        form.elements.role.addEventListener('change', () => filter(form));
        form.elements.search.addEventListener('input', () => {
            clearTimeout(timer);
            if (form === desktop) timer = setTimeout(() => filter(form), 350);
        });
        form.querySelector('.va-clear-filters').addEventListener('click', (event) => {
            event.preventDefault();
            load(new URL(form.action));
        });
    }
    target.addEventListener('click', (event) => {
        const deleteButton = event.target.closest('[data-delete-url]');
        if (deleteButton) {
            document.getElementById('deleteUserForm').action = deleteButton.dataset.deleteUrl;
            document.getElementById('deleteUserName').textContent = deleteButton.dataset.userName;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteUserModal')).show();
            return;
        }
        const link = event.target.closest('.pagination a.page-link');
        if (!link || link.closest('.disabled') || link.closest('.active')) return;
        event.preventDefault();
        load(new URL(link.href));
    });
    window.addEventListener('popstate', () => load(new URL(location.href), false));
    document.getElementById('deleteUserForm').addEventListener('submit', () => {
        document.getElementById('confirmDeleteUser').disabled = true;
    });
});
