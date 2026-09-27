document.addEventListener('DOMContentLoaded', () => {
    const target = document.getElementById('stockResults');
    const desktop = document.getElementById('desktopStockFilters');
    const mobile = document.getElementById('mobileStockFilters');
    if (!target || !desktop || !mobile) return;

    let pending = null;
    let timer;

    function syncForms(url) {
        for (const form of [desktop, mobile]) {
            for (const name of ['search', 'from', 'to']) {
                form.elements[name].value = url.searchParams.get(name) || '';
            }
            form.elements.sort.value = url.searchParams.get('sort') || 'newest';
        }
    }

    function showError(message) {
        target.querySelector('.va-load-error')?.remove();
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger va-load-error';
        alert.setAttribute('role', 'alert');
        alert.textContent = message;
        target.prepend(alert);
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
            if (response.status === 401 || response.redirected) {
                window.location.assign(response.url);
                return;
            }
            if (!response.ok) throw new Error('Unable to load stock entries.');
            const html = await response.text();
            if (controller.signal.aborted) return;

            target.innerHTML = html;
            target.classList.remove('is-loading');
            if (push && url.href !== location.href) history.pushState({}, '', url);
            syncForms(url);
            bootstrap.Offcanvas.getInstance(document.getElementById('stockFilters'))?.hide();
        } catch (error) {
            if (error.name !== 'AbortError' && !controller.signal.aborted) {
                target.classList.remove('is-loading');
                showError('Unable to load stock entries. Please retry.');
            }
        } finally {
            if (pending === controller) pending = null;
        }
    }

    function apply(form) {
        const fields = new FormData(form);
        const from = fields.get('from');
        const to = fields.get('to');
        if (from && to && from > to) {
            showError('To date must be on or after From date.');
            return;
        }
        const url = new URL(form.action);
        for (const [key, value] of fields) {
            if (String(value).trim()) url.searchParams.set(key, String(value).trim());
        }
        load(url);
    }

    for (const form of [desktop, mobile]) {
        form.addEventListener('submit', event => {
            event.preventDefault();
            clearTimeout(timer);
            apply(form);
        });
        for (const name of ['sort', 'from', 'to']) {
            form.elements[name].addEventListener('change', () => apply(form));
        }
        form.elements.search.addEventListener('input', () => {
            clearTimeout(timer);
            if (form === desktop) timer = setTimeout(() => apply(form), 350);
        });
        form.querySelector('.va-clear-filters').addEventListener('click', event => {
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
    syncForms(new URL(location.href));
});
