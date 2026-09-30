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
            rows().forEach(item => item.querySelector('select').setCustomValidity(''));
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
            if (event.target.matches('.va-vehicle-product')) rows().forEach(row => row.querySelector('select').setCustomValidity(''));
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

    const root = document.getElementById('vehicleAccounts') || document.getElementById('vehicleWorkspace');
    if (!root) return;
    const search = document.querySelector('.va-vehicle-search');
    let active;
    let saving = false;

    function feedback(message, error = false) {
        const area = root.querySelector('.va-vehicle-feedback');
        if (!area) return;
        area.hidden = false;
        area.classList.toggle('alert-danger', error);
        area.classList.toggle('alert-success', !error);
        area.textContent = message;
    }

    async function load(url, push = true) {
        active?.abort();
        const controller = new AbortController();
        active = controller;
        root.classList.add('is-loading');
        root.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, {signal: controller.signal, credentials: 'same-origin', headers: {
                'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html',
            }});
            if (response.status === 401 || response.redirected) { location.assign(response.url); return false; }
            if (!response.ok) throw new Error('Could not refresh vehicle entries. Please try again.');
            const html = await response.text();
            if (controller.signal.aborted) return false;
            root.innerHTML = html;
            if (search) {
                search.elements.search.value = url.searchParams.get('search') || '';
                search.elements.approval.value = url.searchParams.get('approval') || '';
            }
            if (push && url.href !== location.href) history.pushState({}, '', url);
            return true;
        } catch (error) {
            if (error.name !== 'AbortError' && !controller.signal.aborted) feedback(error.message, true);
            return false;
        } finally {
            if (active === controller) {
                active = null;
                root.classList.remove('is-loading');
                root.setAttribute('aria-busy', 'false');
            }
        }
    }
    search?.addEventListener('submit', event => {
        event.preventDefault();
        if (saving) return;
        const url = new URL(search.action);
        for (const field of ['search', 'approval']) {
            const value = search.elements[field].value.trim();
            if (value) url.searchParams.set(field, value);
        }
        load(url);
    });
    root.addEventListener('click', event => {
        const link = event.target.closest('.pagination a[href]');
        if (!link || link.getAttribute('aria-disabled') === 'true' || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        if (!saving) load(new URL(link.href));
    });
    root.addEventListener('submit', async event => {
        const paymentForm = event.target.closest('[data-vehicle-payment], [data-vehicle-approve]');
        if (!paymentForm) return;
        event.preventDefault();
        if (saving || !paymentForm.reportValidity()) return;
        saving = true;
        active?.abort();
        const button = paymentForm.querySelector('button[type="submit"], button:not([type])');
        const label = button.textContent;
        button.disabled = true;
        button.replaceChildren();
        const spinner = document.createElement('span');
        spinner.className = 'spinner-border spinner-border-sm me-1';
        spinner.setAttribute('aria-hidden', 'true');
        button.append(spinner, document.createTextNode(paymentForm.hasAttribute('data-vehicle-approve') ? 'Approving…' : 'Saving…'));
        try {
            const response = await fetch(paymentForm.action, {method: 'POST', credentials: 'same-origin', body: new FormData(paymentForm), headers: {
                'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json',
            }});
            if (response.status === 401 || response.redirected) { location.assign(response.url); return; }
            const data = await response.json();
            if (!response.ok) throw new Error(Object.values(data.errors || {})[0]?.[0] || data.message || 'Could not save. Please try again.');
            // All wallet figures and payment history come from the same server ledger.
            const url = new URL(location.href);
            if (paymentForm.hasAttribute('data-vehicle-payment')) url.searchParams.delete('payments_page');
            else if (search) url.searchParams.delete('page');
            if (await load(url)) feedback(data.message);
            else feedback('Saved successfully. Refresh this page to see the updated wallet.', true);
        } catch (error) { feedback(error.message, true); }
        finally {
            saving = false;
            if (button.isConnected) { button.disabled = false; button.textContent = label; }
        }
    });
    window.addEventListener('popstate', () => load(new URL(location.href), false));
});
