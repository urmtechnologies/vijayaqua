document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('vehicleEntryForm');
    if (form) {
        const holder = document.getElementById('vehicleRows');
        const rows = () => Array.from(holder.querySelectorAll('[data-vehicle-row]'));
        let index = Math.max(0, ...rows().map(row => {
            const match = row.querySelector('.va-vehicle-product').name.match(/items\[(\d+)\]/);
            return match ? Number(match[1]) + 1 : 0;
        }));
        holder.addEventListener('click', event => {
            const button = event.target.closest('.va-vehicle-remove');
            if (!button) return;
            const row = button.closest('[data-vehicle-row]');
            if (rows().length === 1) row.querySelectorAll('select,input').forEach(input => { input.value = ''; input.setCustomValidity(''); });
            else row.remove();
        });
        document.getElementById('addVehicleRow').addEventListener('click', () => {
            if (rows().length >= 40) return;
            const row = document.getElementById('vehicleRowTemplate').content.firstElementChild.cloneNode(true);
            row.querySelector('.va-vehicle-product').name = `items[${index}][product_id]`;
            row.querySelector('.va-vehicle-qty').name = `items[${index}][cartons]`;
            index += 1;
            holder.append(row); row.querySelector('select').focus();
        });
        holder.addEventListener('change', event => {
            if (event.target.matches('.va-vehicle-product')) event.target.setCustomValidity('');
        });
        form.addEventListener('submit', event => {
            const seen = new Set();
            for (const row of rows()) {
                const select = row.querySelector('.va-vehicle-product');
                select.setCustomValidity(select.value && seen.has(select.value) ? 'This product is already selected.' : '');
                if (!select.reportValidity()) { event.preventDefault(); return; }
                seen.add(select.value);
            }
            if (!form.checkValidity()) { event.preventDefault(); form.reportValidity(); return; }
            const button = document.getElementById('submitVehicleEntry');
            button.disabled = true;
            document.getElementById('vehicleSubmitSpinner').classList.remove('d-none');
        });
    }

    const accounts = document.getElementById('vehicleAccounts');
    const search = document.querySelector('.va-vehicle-search');
    if (!accounts || !search) return;
    let active;
    async function load(url, push = true) {
        active?.abort();
        const controller = new AbortController(); active = controller;
        accounts.classList.add('is-loading');
        try {
            const response = await fetch(url, {signal: controller.signal, credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html'}});
            if (response.status === 401 || response.redirected) { location.assign(response.url); return; }
            if (!response.ok) throw new Error('Unable to load accounts.');
            const html = await response.text();
            if (controller.signal.aborted) return;
            accounts.innerHTML = html;
            search.elements.search.value = url.searchParams.get('search') || '';
            if (push && url.href !== location.href) history.pushState({}, '', url);
        } catch (error) {
            if (error.name !== 'AbortError' && !controller.signal.aborted) {
                accounts.querySelector('.va-vehicle-error')?.remove();
                const notice = document.createElement('p'); notice.className = 'va-vehicle-error text-danger'; notice.textContent = error.message; accounts.prepend(notice);
            }
        } finally { if (active === controller) { active = null; accounts.classList.remove('is-loading'); } }
    }
    search.addEventListener('submit', event => {
        event.preventDefault();
        const url = new URL(search.action);
        const query = search.elements.search.value.trim();
        if (query) url.searchParams.set('search', query);
        load(url);
    });
    accounts.addEventListener('click', event => {
        const link = event.target.closest('.pagination a[href]');
        if (!link) return;
        event.preventDefault(); load(new URL(link.href));
    });
    window.addEventListener('popstate', () => load(new URL(location.href), false));
});
