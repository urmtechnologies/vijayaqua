document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('upcomingOrderForm');
    if (!form) return;
    const products = JSON.parse(document.getElementById('upcomingProducts').textContent);
    const rows = document.getElementById('upcomingRows');
    const mobile = document.getElementById('upcomingMobile');
    const customer = document.getElementById('upcomingCustomer');
    const info = document.getElementById('upcomingCustomerInfo');
    let nextIndex = Math.max(0, ...Array.from(rows.querySelectorAll('.va-order-product-id'), input => {
        const match = input.name.match(/^items\[(\d+)\]/);
        return match ? Number(match[1]) + 1 : 0;
    }));
    let lookupTimer;
    let lookupRequest;
    const currentRows = () => Array.from(rows.querySelectorAll('[data-order-row]'));

    function selectedIds(except) {
        return new Set(currentRows().filter(row => row !== except)
            .map(row => row.querySelector('.va-order-product-id').value).filter(Boolean));
    }

    function close(row) {
        row.querySelector('.va-order-options').hidden = true;
        row.querySelector('.va-order-search').setAttribute('aria-expanded', 'false');
    }

    function showOptions(row) {
        const search = row.querySelector('.va-order-search');
        const list = row.querySelector('.va-order-options');
        const id = row.querySelector('.va-order-product-id').value;
        const match = search.value.trim().toLocaleLowerCase();
        const excluded = selectedIds(row);
        const choices = products.filter(product =>
            (product.selectable || String(product.id) === id) &&
            !excluded.has(String(product.id)) && product.name.toLocaleLowerCase().includes(match)
        ).slice(0, 12);
        list.replaceChildren();
        for (const product of choices) {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'va-order-option';
            option.setAttribute('role', 'option');
            option.textContent = product.name + (product.selectable ? '' : ' (Inactive)');
            option.addEventListener('click', () => {
                search.value = product.name;
                row.querySelector('.va-order-product-id').value = product.id;
                search.setCustomValidity('');
                close(row);
                row.querySelector('.va-order-qty').focus();
            });
            list.append(option);
        }
        if (!choices.length) {
            const empty = document.createElement('div');
            empty.className = 'va-order-option text-muted';
            empty.textContent = 'No matching products';
            list.append(empty);
        }
        list.hidden = false;
        search.setAttribute('aria-expanded', 'true');
    }

    function recalculate() {
        let total = 0n;
        currentRows().forEach((row, index) => {
            const qty = row.querySelector('.va-order-qty').value;
            if (/^\d+$/.test(qty)) total += BigInt(qty);
            row.querySelector('.va-order-row-number').textContent = String(index + 1);
        });
        document.getElementById('upcomingTotal').textContent = `${total.toLocaleString('en-US')} CTN`;
    }

    function connect(row) {
        const search = row.querySelector('.va-order-search');
        search.addEventListener('focus', () => showOptions(row));
        search.addEventListener('input', () => {
            row.querySelector('.va-order-product-id').value = '';
            search.setCustomValidity('');
            showOptions(row);
        });
        search.addEventListener('keydown', event => {
            const list = row.querySelector('.va-order-options');
            if (event.key === 'Escape') { close(row); return; }
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                if (list.hidden) showOptions(row);
                const choices = Array.from(list.querySelectorAll('button.va-order-option'));
                if (!choices.length) return;
                const current = choices.findIndex(option => option.classList.contains('is-active'));
                const next = event.key === 'ArrowDown' ? (current + 1) % choices.length : (current - 1 + choices.length) % choices.length;
                choices.forEach(option => option.classList.remove('is-active'));
                choices[next].classList.add('is-active');
                choices[next].scrollIntoView({block: 'nearest'});
            } else if (event.key === 'Enter' && !list.hidden) {
                const choices = Array.from(list.querySelectorAll('button.va-order-option'));
                if (!choices.length) return;
                event.preventDefault();
                (choices.find(option => option.classList.contains('is-active')) || choices[0]).click();
            }
        });
        row.querySelector('.va-order-qty').addEventListener('input', recalculate);
        row.querySelector('.va-order-remove').addEventListener('click', () => {
            if (currentRows().length === 1) {
                search.value = '';
                row.querySelector('.va-order-product-id').value = '';
                row.querySelector('.va-order-qty').value = '';
                search.focus();
            } else row.remove();
            recalculate();
        });
    }

    currentRows().forEach(connect);
    recalculate();
    document.getElementById('addUpcomingRow').addEventListener('click', () => {
        const row = document.getElementById('upcomingRowTemplate').content.firstElementChild.cloneNode(true);
        const index = nextIndex++;
        const search = row.querySelector('.va-order-search');
        const quantity = row.querySelector('.va-order-qty');
        search.id = `upcomingProduct${index}`;
        quantity.id = `upcomingCartons${index}`;
        row.querySelectorAll('label')[0].htmlFor = search.id;
        row.querySelectorAll('label')[1].htmlFor = quantity.id;
        row.querySelector('.va-order-product-id').name = `items[${index}][product_id]`;
        quantity.name = `items[${index}][cartons]`;
        rows.append(row);
        connect(row);
        recalculate();
        search.focus();
    });
    document.addEventListener('click', event => {
        for (const row of currentRows()) if (!row.querySelector('.va-order-picker').contains(event.target)) close(row);
    });

    async function lookupCustomer() {
        lookupRequest?.abort();
        if (!/^[0-9]{10}$/.test(mobile.value)) { info.textContent = ''; return; }
        const value = mobile.value;
        const controller = new AbortController();
        lookupRequest = controller;
        info.textContent = 'Checking customer...';
        try {
            const url = new URL(form.dataset.customerLookup);
            url.searchParams.set('mobile', value);
            const response = await fetch(url, {signal: controller.signal, headers: {Accept: 'application/json'}, credentials: 'same-origin'});
            if (!response.ok) throw new Error('Lookup failed');
            const data = await response.json();
            if (controller.signal.aborted || value !== mobile.value) return;
            if (data.found) {
                customer.value = data.name;
                customer.readOnly = true;
                info.textContent = 'Existing customer found.';
            } else {
                customer.readOnly = false;
                info.textContent = 'New customer. Enter the name to save this order.';
            }
        } catch (error) {
            if (error.name !== 'AbortError') info.textContent = 'Could not check the customer. You can still submit; the server will verify the mobile number.';
        }
    }
    mobile.addEventListener('input', () => {
        clearTimeout(lookupTimer);
        lookupRequest?.abort();
        customer.readOnly = false;
        customer.value = '';
        info.textContent = '';
        if (mobile.value.length === 10) lookupTimer = setTimeout(lookupCustomer, 300);
    });
    if (/^[0-9]{10}$/.test(mobile.value)) lookupCustomer();

    form.addEventListener('submit', event => {
        const selected = new Set();
        for (const row of currentRows()) {
            const search = row.querySelector('.va-order-search');
            const id = row.querySelector('.va-order-product-id').value;
            search.setCustomValidity(!id ? 'Choose a product from the suggestions.'
                : selected.has(id) ? 'This product is already on the order.' : '');
            if (!search.checkValidity()) { event.preventDefault(); search.reportValidity(); return; }
            selected.add(id);
        }
        if (!form.checkValidity()) { event.preventDefault(); form.reportValidity(); return; }
        document.getElementById('saveUpcoming').disabled = true;
        document.getElementById('upcomingSpinner').classList.remove('d-none');
        document.getElementById('upcomingButtonText').textContent = 'Saving...';
    });
});
