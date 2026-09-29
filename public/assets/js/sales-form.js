document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('saleForm');
    if (!form) return;
    const products = JSON.parse(document.getElementById('saleProducts').textContent);
    const holder = document.getElementById('saleRows');
    const listRows = () => Array.from(holder.querySelectorAll('[data-sale-row]'));
    const money = paise => {
        const rupees = (paise / 100n).toLocaleString('en-IN');
        const fraction = paise % 100n;
        return '₹' + rupees + (fraction ? '.' + String(fraction).padStart(2, '0') : '');
    };
    const parsePaise = value => {
        if (!/^(0|[1-9]\d*)(?:\.\d{1,2})?$/.test(value)) return 0n;
        const [rupees, decimal = ''] = value.split('.');
        return BigInt(rupees) * 100n + BigInt(decimal.padEnd(2, '0'));
    };
    let nextIndex = Math.max(0, ...listRows().map(row => {
        const match = row.querySelector('.va-sale-product-id').name.match(/items\[(\d+)\]/);
        return match ? Number(match[1]) + 1 : 0;
    }));

    function recalculate() {
        let sum = 0n;
        listRows().forEach((row, index) => {
            const quantity = row.querySelector('.va-sale-qty').value;
            const rate = row.querySelector('.va-sale-rate').value;
            const total = (/^\d+$/.test(quantity) ? BigInt(quantity) : 0n) * parsePaise(rate);
            row.querySelector('.va-sale-row-number').textContent = String(index + 1);
            row.querySelector('.va-sale-line-total').textContent = money(total);
            sum += total;
        });
        document.getElementById('saleTotal').textContent = money(sum);
    }

    function openOptions(row) {
        const search = row.querySelector('.va-sale-search');
        const menu = row.querySelector('.va-sale-options');
        const used = new Set(listRows().filter(item => item !== row).map(item => item.querySelector('.va-sale-product-id').value));
        const matches = products.filter(product => !used.has(String(product.id)) && product.name.toLowerCase().includes(search.value.trim().toLowerCase())).slice(0, 12);
        menu.replaceChildren();
        matches.forEach(product => {
            const option = document.createElement('button');
            option.type = 'button'; option.className = 'va-sale-option';
            option.textContent = `${product.name} · ${product.available} CTN available`;
            option.addEventListener('click', () => {
                search.value = product.name;
                row.querySelector('.va-sale-product-id').value = product.id;
                search.setCustomValidity('');
                menu.hidden = true;
                row.querySelector('.va-sale-qty').focus();
            });
            menu.append(option);
        });
        if (!matches.length) menu.textContent = 'No matching product';
        menu.hidden = false;
    }

    function connect(row) {
        const search = row.querySelector('.va-sale-search');
        search.addEventListener('focus', () => openOptions(row));
        search.addEventListener('input', () => {
            row.querySelector('.va-sale-product-id').value = '';
            search.setCustomValidity('');
            openOptions(row);
        });
        search.addEventListener('keydown', event => {
            if (event.key === 'Escape') row.querySelector('.va-sale-options').hidden = true;
            if (event.key === 'Enter' && !row.querySelector('.va-sale-options').hidden) {
                const first = row.querySelector('.va-sale-option');
                if (first) { event.preventDefault(); first.click(); }
            }
        });
        row.querySelector('.va-sale-qty').addEventListener('input', recalculate);
        row.querySelector('.va-sale-rate').addEventListener('input', recalculate);
        row.querySelector('.va-sale-remove').addEventListener('click', () => {
            if (listRows().length === 1) {
                row.querySelectorAll('input').forEach(input => input.value = '');
                search.focus();
            } else row.remove();
            recalculate();
        });
    }
    listRows().forEach(connect);
    recalculate();
    document.getElementById('addSaleRow').addEventListener('click', () => {
        const row = document.getElementById('saleRowTemplate').content.firstElementChild.cloneNode(true);
        const index = nextIndex++;
        [['.va-sale-search', 'Product'], ['.va-sale-qty', 'Cartons'], ['.va-sale-rate', 'Rate']].forEach(([selector, label]) => {
            const input = row.querySelector(selector);
            input.id = `sale${label}${index}`;
            input.closest('[class$="-field"], .va-sale-product')?.querySelector('label')?.setAttribute('for', input.id);
        });
        row.querySelector('.va-sale-product-id').name = `items[${index}][product_id]`;
        row.querySelector('.va-sale-qty').name = `items[${index}][cartons]`;
        row.querySelector('.va-sale-rate').name = `items[${index}][rate_rupees]`;
        holder.append(row); connect(row); recalculate(); row.querySelector('.va-sale-search').focus();
    });
    document.addEventListener('click', event => listRows().forEach(row => {
        if (!row.querySelector('.va-sale-picker').contains(event.target)) row.querySelector('.va-sale-options').hidden = true;
    }));

    const staffSearch = document.getElementById('staffSearch');
    const staffSelect = document.getElementById('staffSelect');
    staffSearch.addEventListener('input', () => Array.from(staffSelect.options).forEach(option => {
        option.hidden = Boolean(option.value) && !option.dataset.search.includes(staffSearch.value.trim().toLowerCase());
    }));
    form.addEventListener('submit', event => {
        const seen = new Set();
        for (const row of listRows()) {
            const search = row.querySelector('.va-sale-search');
            const id = row.querySelector('.va-sale-product-id').value;
            search.setCustomValidity(!id ? 'Choose a product from the list.' : seen.has(id) ? 'This product is already selected.' : '');
            if (!search.checkValidity()) { event.preventDefault(); search.reportValidity(); return; }
            seen.add(id);
        }
        if (!form.checkValidity()) { event.preventDefault(); form.reportValidity(); return; }
        document.getElementById('saveSale').disabled = true;
    });
});
