document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('saleForm');
    if (!form) return;
    const products = JSON.parse(document.getElementById('saleProducts').textContent);
    const rows = document.getElementById('saleRows');
    const add = document.getElementById('addSaleRow');
    const mobile = document.getElementById('partyMobile');
    const party = document.getElementById('partyName');
    const partyInfo = document.getElementById('partyLookup');
    const discount = document.getElementById('saleDiscount');
    const vehicle = document.getElementById('saleVehicle');
    const paid = document.getElementById('salePaid');
    const dueDate = document.getElementById('saleDueDate');
    const method = document.getElementById('salePaymentMethod');
    const saleDate = document.getElementById('saleDate');
    const limit = 9000000000000000n;
    let nextIndex = Math.max(0, ...Array.from(rows.querySelectorAll('.va-sale-product-id'), input => {
        const match = input.name.match(/^items\[(\d+)\]/);
        return match ? Number(match[1]) + 1 : 0;
    }));
    let lookupRequest = null;
    let lookupTimer;

    const money = value => '₹' + value.toLocaleString('en-US');
    const digits = input => /^\d+$/.test(input.value) ? BigInt(input.value) : 0n;
    const currentRows = () => Array.from(rows.querySelectorAll('[data-sale-row]'));

    function selectedIds(except) {
        return new Set(currentRows().filter(row => row !== except)
            .map(row => row.querySelector('.va-sale-product-id').value).filter(Boolean));
    }

    function close(row) {
        row.querySelector('.va-sale-options').hidden = true;
        row.querySelector('.va-sale-search').setAttribute('aria-expanded', 'false');
    }

    function showOptions(row) {
        const search = row.querySelector('.va-sale-search');
        const list = row.querySelector('.va-sale-options');
        const match = search.value.trim().toLocaleLowerCase();
        const excluded = selectedIds(row);
        const choices = products.filter(p => !excluded.has(String(p.id)) && p.name.toLocaleLowerCase().includes(match)).slice(0, 12);
        list.replaceChildren();
        for (const product of choices) {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'va-sale-option';
            option.setAttribute('role', 'option');
            option.textContent = `${product.name} · ${Number(product.available).toLocaleString('en-US')} CTN available`;
            option.addEventListener('click', () => {
                search.value = product.name;
                row.querySelector('.va-sale-product-id').value = product.id;
                search.setCustomValidity('');
                close(row);
                row.querySelector('.va-sale-qty').focus();
            });
            list.append(option);
        }
        if (!choices.length) {
            const empty = document.createElement('div');
            empty.className = 'va-sale-empty';
            empty.textContent = 'No matching products';
            list.append(empty);
        }
        list.hidden = false;
        search.setAttribute('aria-expanded', 'true');
    }

    function recalculate() {
        let subtotal = 0n;
        for (const row of currentRows()) {
            const line = digits(row.querySelector('.va-sale-qty')) * digits(row.querySelector('.va-sale-rate'));
            subtotal += line;
            row.querySelector('.va-sale-line-total').textContent = money(line);
        }
        const reduction = digits(discount);
        const charge = digits(vehicle);
        const received = digits(paid);
        const total = subtotal >= reduction ? subtotal - reduction + charge : 0n;
        const due = total >= received ? total - received : 0n;
        discount.setCustomValidity(reduction > subtotal ? 'Discount exceeds product subtotal.' : '');
        paid.setCustomValidity(received > total ? 'Payment exceeds invoice total.' : '');
        vehicle.setCustomValidity(total > limit || subtotal > limit ? 'Amount exceeds the supported limit.' : '');
        dueDate.required = due > 0n;
        dueDate.min = saleDate.value;
        method.required = received > 0n;
        document.getElementById('saleSubtotal').textContent = money(subtotal);
        document.getElementById('saleTotal').textContent = money(total);
        document.getElementById('saleDue').textContent = money(due);
        currentRows().forEach((row, index) => { row.querySelector('.va-sale-row-number').textContent = String(index + 1); });
    }

    function connectRow(row) {
        const search = row.querySelector('.va-sale-search');
        search.addEventListener('focus', () => showOptions(row));
        search.addEventListener('input', () => {
            row.querySelector('.va-sale-product-id').value = '';
            search.setCustomValidity('');
            showOptions(row);
        });
        search.addEventListener('keydown', event => {
            const list = row.querySelector('.va-sale-options');
            if (event.key === 'Escape') { close(row); return; }
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                if (list.hidden) showOptions(row);
                const choices = Array.from(list.querySelectorAll('.va-sale-option'));
                if (!choices.length) return;
                const current = choices.findIndex(option => option.classList.contains('is-active'));
                const next = event.key === 'ArrowDown' ? (current + 1) % choices.length : (current - 1 + choices.length) % choices.length;
                choices.forEach(option => option.classList.remove('is-active'));
                choices[next].classList.add('is-active');
                choices[next].scrollIntoView({block: 'nearest'});
            } else if (event.key === 'Enter' && !list.hidden) {
                const options = Array.from(list.querySelectorAll('.va-sale-option'));
                if (!options.length) return;
                event.preventDefault();
                (options.find(option => option.classList.contains('is-active')) || options[0]).click();
            }
        });
        row.querySelector('.va-sale-qty').addEventListener('input', recalculate);
        row.querySelector('.va-sale-rate').addEventListener('input', recalculate);
        row.querySelector('.va-sale-remove').addEventListener('click', () => {
            if (currentRows().length === 1) {
                search.value = '';
                row.querySelector('.va-sale-product-id').value = '';
                row.querySelector('.va-sale-qty').value = '';
                row.querySelector('.va-sale-rate').value = '';
                search.focus();
            } else row.remove();
            recalculate();
        });
    }

    currentRows().forEach(connectRow);
    for (const input of [discount, vehicle, paid, saleDate]) input.addEventListener('input', recalculate);
    recalculate();

    add.addEventListener('click', () => {
        const row = document.getElementById('saleRowTemplate').content.firstElementChild.cloneNode(true);
        const index = nextIndex++;
        for (const [selector, suffix, name, labelIndex] of [
            ['.va-sale-search', 'Product', null, 0], ['.va-sale-qty', 'Cartons', 'cartons', 1], ['.va-sale-rate', 'Rate', 'rate_rupees', 2],
        ]) {
            const input = row.querySelector(selector);
            input.id = `sale${suffix}${index}`;
            row.querySelectorAll('label')[labelIndex].htmlFor = input.id;
            if (name) input.name = `items[${index}][${name}]`;
        }
        row.querySelector('.va-sale-product-id').name = `items[${index}][product_id]`;
        rows.append(row);
        connectRow(row);
        recalculate();
        row.querySelector('.va-sale-search').focus();
    });

    document.addEventListener('click', event => {
        for (const row of currentRows()) if (!row.querySelector('.va-sale-picker').contains(event.target)) close(row);
    });

    function partyMessage(message, url) {
        partyInfo.replaceChildren();
        const text = document.createElement('span');
        text.textContent = message;
        partyInfo.append(text);
        if (url) {
            const link = document.createElement('a');
            link.href = url;
            link.textContent = 'View party account';
            link.className = 'ms-2';
            partyInfo.append(link);
        }
    }

    async function lookupParty() {
        lookupRequest?.abort();
        if (!/^[0-9]{10}$/.test(mobile.value)) { partyMessage(''); return; }
        const value = mobile.value;
        const controller = new AbortController();
        lookupRequest = controller;
        partyMessage('Checking party...');
        try {
            const url = new URL(form.dataset.customerLookup);
            url.searchParams.set('mobile', value);
            const response = await fetch(url, {signal: controller.signal, headers: {Accept: 'application/json'}, credentials: 'same-origin'});
            if (!response.ok) throw new Error('Lookup failed');
            const data = await response.json();
            if (controller.signal.aborted || value !== mobile.value) return;
            if (data.found) {
                party.value = data.name;
                party.readOnly = true;
                partyMessage(`${data.invoices} previous invoice(s) · Due ₹${BigInt(data.due).toLocaleString('en-US')}`, data.account_url);
                if (data.recent.length) {
                    const history = document.createElement('div');
                    history.className = 'va-party-history';
                    for (const previous of data.recent) {
                        const link = document.createElement('a');
                        link.href = previous.url;
                        link.textContent = `${previous.invoice} · ${previous.date} · Due ₹${Number(previous.due).toLocaleString('en-US')}`;
                        history.append(link);
                    }
                    partyInfo.append(history);
                }
            } else {
                party.readOnly = false;
                partyMessage('New party. Enter their name to create an account.');
            }
        } catch (error) {
            if (error.name !== 'AbortError') partyMessage('Could not check the party. You can still submit; the server will verify the mobile number.');
        }
    }

    mobile.addEventListener('input', () => {
        clearTimeout(lookupTimer);
        lookupRequest?.abort();
        party.readOnly = false;
        party.value = '';
        partyMessage('');
        if (mobile.value.length === 10) lookupTimer = setTimeout(lookupParty, 300);
    });
    if (/^[0-9]{10}$/.test(mobile.value)) lookupParty();

    form.addEventListener('submit', event => {
        const selected = new Set();
        for (const row of currentRows()) {
            const search = row.querySelector('.va-sale-search');
            const id = row.querySelector('.va-sale-product-id').value;
            search.setCustomValidity(!id ? 'Choose a product from the suggestions.'
                : selected.has(id) ? 'This product is already on the invoice.' : '');
            if (!search.checkValidity()) { event.preventDefault(); search.reportValidity(); return; }
            selected.add(id);
        }
        recalculate();
        if (!form.checkValidity()) { event.preventDefault(); form.reportValidity(); return; }
        document.getElementById('saveSale').disabled = true;
        document.getElementById('saleSpinner').classList.remove('d-none');
        document.getElementById('saleButtonText').textContent = 'Creating invoice...';
    });
});
