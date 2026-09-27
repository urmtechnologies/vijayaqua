document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('stockEntryForm');
    if (!form) return;

    const products = JSON.parse(document.getElementById('stockProducts').textContent);
    const rows = document.getElementById('stockRows');
    const add = document.getElementById('addStockRow');
    const total = document.getElementById('stockTotal');
    let nextIndex = Math.max(0, ...Array.from(rows.querySelectorAll('.va-stock-product-id'), input => {
        const match = input.name.match(/^items\[(\d+)\]/);
        return match ? Number(match[1]) + 1 : 0;
    }));

    function currentRows() { return Array.from(rows.querySelectorAll('[data-stock-row]')); }

    function selectedIds(except) {
        return new Set(currentRows().filter(row => row !== except)
            .map(row => row.querySelector('.va-stock-product-id').value).filter(Boolean));
    }

    function closeOptions(row) {
        const list = row.querySelector('.va-stock-options');
        list.hidden = true;
        row.querySelector('.va-stock-search').setAttribute('aria-expanded', 'false');
    }

    function optionsFor(row) {
        const search = row.querySelector('.va-stock-search');
        const list = row.querySelector('.va-stock-options');
        const currentId = row.querySelector('.va-stock-product-id').value;
        const query = search.value.trim().toLocaleLowerCase();
        const excluded = selectedIds(row);
        const matches = products.filter(product =>
            (product.available || String(product.id) === currentId) &&
            !excluded.has(String(product.id)) &&
            product.name.toLocaleLowerCase().includes(query)
        ).slice(0, 12);

        list.replaceChildren();
        for (const product of matches) {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'va-stock-option';
            option.setAttribute('role', 'option');
            option.textContent = product.name + (product.available ? '' : ' (Inactive)');
            option.addEventListener('click', () => {
                search.value = product.name;
                row.querySelector('.va-stock-product-id').value = product.id;
                search.setCustomValidity('');
                closeOptions(row);
                row.querySelector('.va-stock-qty').focus();
            });
            list.append(option);
        }
        if (!matches.length) {
            const empty = document.createElement('div');
            empty.className = 'va-stock-empty';
            empty.textContent = 'No matching products';
            list.append(empty);
        }
        list.hidden = false;
        search.setAttribute('aria-expanded', 'true');
    }

    function recalculate() {
        let count = 0n;
        for (const input of rows.querySelectorAll('.va-stock-qty')) {
            if (/^\d+$/.test(input.value) && Number(input.value) <= 1000000000) count += BigInt(input.value);
        }
        total.textContent = count.toLocaleString('en-US') + ' CTN';
        add.disabled = products.length === 0;
        currentRows().forEach((row, index) => {
            row.querySelector('.va-stock-row-number').textContent = String(index + 1);
        });
    }

    function connectRow(row) {
        const search = row.querySelector('.va-stock-search');
        search.addEventListener('focus', () => optionsFor(row));
        search.addEventListener('input', () => {
            row.querySelector('.va-stock-product-id').value = '';
            search.setCustomValidity('');
            optionsFor(row);
        });
        search.addEventListener('keydown', event => {
            const list = row.querySelector('.va-stock-options');
            let choices = Array.from(list.querySelectorAll('.va-stock-option'));
            if (event.key === 'Escape') { closeOptions(row); return; }
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                if (list.hidden) {
                    optionsFor(row);
                    choices = Array.from(list.querySelectorAll('.va-stock-option'));
                }
                if (!choices.length) return;
                const current = choices.findIndex(option => option.classList.contains('is-active'));
                const next = event.key === 'ArrowDown'
                    ? (current + 1) % choices.length
                    : (current - 1 + choices.length) % choices.length;
                choices.forEach(option => option.classList.remove('is-active'));
                if (choices[next]) {
                    choices[next].classList.add('is-active');
                    choices[next].scrollIntoView({block: 'nearest'});
                }
            } else if (event.key === 'Enter' && !list.hidden && choices.length) {
                event.preventDefault();
                (choices.find(option => option.classList.contains('is-active')) || choices[0]).click();
            }
        });
        row.querySelector('.va-stock-qty').addEventListener('input', recalculate);
        row.querySelector('.va-stock-remove').addEventListener('click', () => {
            if (currentRows().length === 1) {
                search.value = '';
                row.querySelector('.va-stock-product-id').value = '';
                row.querySelector('.va-stock-qty').value = '';
                search.focus();
            } else {
                row.remove();
            }
            recalculate();
        });
    }

    currentRows().forEach(connectRow);
    recalculate();

    add.addEventListener('click', () => {
        const row = document.getElementById('stockRowTemplate').content.firstElementChild.cloneNode(true);
        const index = nextIndex++;
        const search = row.querySelector('.va-stock-search');
        const quantity = row.querySelector('.va-stock-qty');
        search.id = `stockProduct${index}`;
        quantity.id = `stockCartons${index}`;
        row.querySelectorAll('label')[0].htmlFor = search.id;
        row.querySelectorAll('label')[1].htmlFor = quantity.id;
        row.querySelector('.va-stock-product-id').name = `items[${index}][product_id]`;
        quantity.name = `items[${index}][cartons]`;
        rows.append(row);
        connectRow(row);
        recalculate();
        search.focus();
    });

    document.addEventListener('click', event => {
        for (const row of currentRows()) {
            if (!row.querySelector('.va-stock-picker').contains(event.target)) closeOptions(row);
        }
    });

    form.addEventListener('submit', event => {
        const selected = new Set();
        for (const row of currentRows()) {
            const search = row.querySelector('.va-stock-search');
            const id = row.querySelector('.va-stock-product-id').value;
            search.setCustomValidity(!id ? 'Choose a product from the suggestions.'
                : selected.has(id) ? 'This product is already added.' : '');
            if (!search.checkValidity()) {
                event.preventDefault();
                search.reportValidity();
                return;
            }
            selected.add(id);
        }
        if (!form.checkValidity()) return;
        document.getElementById('saveStockEntry').disabled = true;
        document.getElementById('stockSavingSpinner').classList.remove('d-none');
        document.getElementById('stockSaveLabel').textContent = 'Saving...';
    });
});
