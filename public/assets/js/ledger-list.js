document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-ledger-list]');
    if (!page) return;
    const forms = Array.from(page.querySelectorAll('.va-ledger-filter'));
    const target = page.querySelector('.va-ledger-results');
    const panel = page.querySelector('.offcanvas');
    let pending = null;
    let timer;

    function sync(url) {
        for (const form of forms) {
            for (const element of form.querySelectorAll('[name]')) {
                element.value = url.searchParams.get(element.name) || '';
            }
        }
    }

    function error(message) {
        target.querySelector('.va-load-error')?.remove();
        const box = document.createElement('div');
        box.className = 'alert alert-danger va-load-error';
        box.setAttribute('role', 'alert');
        box.textContent = message;
        target.prepend(box);
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
            if (response.status === 401 || response.redirected) { location.assign(response.url); return; }
            if (!response.ok) throw new Error('Request failed');
            const html = await response.text();
            if (controller.signal.aborted) return;
            target.innerHTML = html;
            target.classList.remove('is-loading');
            if (push && url.href !== location.href) history.pushState({}, '', url);
            sync(url);
            bootstrap.Offcanvas.getInstance(panel)?.hide();
        } catch (exception) {
            if (exception.name !== 'AbortError' && !controller.signal.aborted) {
                target.classList.remove('is-loading');
                error('Unable to load records. Please retry.');
            }
        } finally {
            if (pending === controller) pending = null;
        }
    }

    function apply(form) {
        const values = new FormData(form);
        if (values.get('from') && values.get('to') && values.get('from') > values.get('to')) {
            error('To date must be on or after From date.');
            return;
        }
        const url = new URL(form.action);
        for (const [key, value] of values) if (String(value).trim()) url.searchParams.set(key, String(value).trim());
        load(url);
    }

    for (const form of forms) {
        form.addEventListener('submit', event => { event.preventDefault(); clearTimeout(timer); apply(form); });
        for (const select of form.querySelectorAll('select, input[type="date"]')) {
            select.addEventListener('change', () => apply(form));
        }
        form.querySelector('input[type="search"]')?.addEventListener('input', () => {
            clearTimeout(timer);
            if (form === forms[0]) timer = setTimeout(() => apply(form), 350);
        });
        form.querySelector('.va-clear-filters')?.addEventListener('click', event => {
            event.preventDefault();
            load(new URL(form.action));
        });
    }
    target.addEventListener('click', event => {
        const link = event.target.closest('.pagination a.page-link');
        if (!link || link.closest('.disabled') || link.closest('.active')) return;
        event.preventDefault();
        load(new URL(link.href));
    });
    window.addEventListener('popstate', () => load(new URL(location.href), false));
    sync(new URL(location.href));
});
