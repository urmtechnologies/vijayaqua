document.addEventListener('DOMContentLoaded', () => {
    const target = document.getElementById('productResults');
    const desktop = document.getElementById('desktopProductFilters');
    const mobile = document.getElementById('mobileProductFilters');
    const modal = document.getElementById('productModal');
    if (!target || !desktop || !mobile || !modal) return;

    const form = document.getElementById('productForm');
    const method = document.getElementById('productMethod');
    let pending = null;
    let timer;

    function clearModalErrors() {
        modal.querySelector('.alert-danger')?.remove();
        modal.querySelectorAll('.is-invalid').forEach(input => input.classList.remove('is-invalid'));
    }

    function syncForms(url) {
        for (const filterForm of [desktop, mobile]) {
            filterForm.elements.search.value = url.searchParams.get('search') || '';
            filterForm.elements.status.value = url.searchParams.get('status') || '';
            filterForm.elements.sort.value = url.searchParams.get('sort') || 'newest';
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
            if (response.status === 401 || response.redirected) {
                window.location.assign(response.url);
                return;
            }
            if (!response.ok) throw new Error('Unable to load products.');
            const html = await response.text();
            if (controller.signal.aborted) return;

            target.innerHTML = html;
            target.classList.remove('is-loading');
            if (push && url.href !== location.href) history.pushState({}, '', url);
            syncForms(url);
            bootstrap.Offcanvas.getInstance(document.getElementById('productFilters'))?.hide();
        } catch (error) {
            if (error.name !== 'AbortError' && !controller.signal.aborted) {
                target.classList.remove('is-loading');
                target.querySelector('.va-load-error')?.remove();
                target.insertAdjacentHTML('afterbegin', '<div class="alert alert-danger va-load-error" role="alert">Unable to load products. Please retry.</div>');
            }
        } finally {
            if (pending === controller) pending = null;
        }
    }

    function filter(filterForm) {
        const url = new URL(filterForm.action);
        const params = new URLSearchParams(new FormData(filterForm));
        for (const [key, value] of params) {
            if (String(value).trim()) url.searchParams.set(key, String(value).trim());
        }
        load(url);
    }

    for (const filterForm of [desktop, mobile]) {
        filterForm.addEventListener('submit', event => {
            event.preventDefault();
            clearTimeout(timer);
            filter(filterForm);
        });
        filterForm.elements.sort.addEventListener('change', () => filter(filterForm));
        filterForm.elements.status.addEventListener('change', () => filter(filterForm));
        filterForm.elements.search.addEventListener('input', () => {
            clearTimeout(timer);
            if (filterForm === desktop) timer = setTimeout(() => filter(filterForm), 350);
        });
        filterForm.querySelector('.va-clear-filters').addEventListener('click', event => {
            event.preventDefault();
            load(new URL(filterForm.action));
        });
    }

    document.getElementById('newProduct').addEventListener('click', () => {
        clearModalErrors();
        form.reset();
        form.action = modal.dataset.storeUrl;
        method.disabled = true;
        document.getElementById('productFormContext').value = 'create';
        document.getElementById('productId').value = '';
        document.getElementById('productModalTitle').textContent = 'New Product';
        bootstrap.Modal.getOrCreateInstance(modal).show();
    });

    target.addEventListener('click', event => {
        const edit = event.target.closest('[data-edit-product]');
        if (edit) {
            clearModalErrors();
            form.action = edit.dataset.updateUrl;
            method.disabled = false;
            method.value = 'PUT';
            document.getElementById('productFormContext').value = 'edit';
            document.getElementById('productId').value = edit.dataset.productId;
            document.getElementById('productName').value = edit.dataset.productName;
            document.getElementById('productStatus').value = edit.dataset.productStatus;
            document.getElementById('productModalTitle').textContent = 'Edit Product';
            bootstrap.Modal.getOrCreateInstance(modal).show();
            return;
        }

        const remove = event.target.closest('[data-delete-product]');
        if (remove) {
            document.getElementById('deleteProductForm').action = remove.dataset.deleteUrl;
            document.getElementById('deleteProductName').textContent = remove.dataset.productName;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteProductModal')).show();
            return;
        }

        const link = event.target.closest('.pagination a.page-link');
        if (!link || link.closest('.disabled') || link.closest('.active')) return;
        event.preventDefault();
        load(new URL(link.href));
    });

    form.addEventListener('submit', () => {
        if (form.checkValidity()) document.getElementById('saveProduct').disabled = true;
    });
    document.getElementById('deleteProductForm').addEventListener('submit', () => {
        document.getElementById('confirmDeleteProduct').disabled = true;
    });
    window.addEventListener('popstate', () => load(new URL(location.href), false));
    syncForms(new URL(location.href));
    if (modal.dataset.reopen === 'yes') bootstrap.Modal.getOrCreateInstance(modal).show();
});
