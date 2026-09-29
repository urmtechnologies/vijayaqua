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
        listRows().forEach(row => {
            const quantity = row.querySelector('.va-sale-qty').value;
            const gross = (/^\d+$/.test(quantity) ? BigInt(quantity) : 0n) * parsePaise(row.querySelector('.va-sale-rate').value);
            const discount = parsePaise(row.querySelector('.va-sale-discount').value);
            const total = gross > discount ? gross - discount : 0n;
            row.querySelector('.va-sale-line-total').textContent = money(total);
            sum += total;
        });
        const legacy = parsePaise(form.dataset.oldDiscount || '0');
        const charge = parsePaise(form.dataset.oldCharge || '0');
        document.getElementById('saleTotal').textContent = money((sum > legacy ? sum - legacy : 0n) + charge);
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
        row.querySelectorAll('.va-sale-qty, .va-sale-rate, .va-sale-discount').forEach(input => input.addEventListener('input', () => {
            input.setCustomValidity('');
            recalculate();
        }));
        row.querySelector('.va-sale-remove').addEventListener('click', () => {
            if (listRows().length === 1) {
                row.querySelectorAll('input').forEach(input => input.value = '');
                row.querySelector('.va-sale-discount').value = '0';
                row.querySelector('.va-sale-reference').value = '';
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
        [['.va-sale-search', 'Product'], ['.va-sale-qty', 'Cartons'], ['.va-sale-rate', 'Rate'],
            ['.va-sale-discount', 'Discount'], ['.va-sale-reference', 'Reference']].forEach(([selector, label]) => {
            const input = row.querySelector(selector);
            input.id = `sale${label}${index}`;
            input.closest('[class$="-field"], .va-sale-product')?.querySelector('label')?.setAttribute('for', input.id);
        });
        row.querySelector('.va-sale-product-id').name = `items[${index}][product_id]`;
        row.querySelector('.va-sale-qty').name = `items[${index}][cartons]`;
        row.querySelector('.va-sale-rate').name = `items[${index}][rate_rupees]`;
        row.querySelector('.va-sale-discount').name = `items[${index}][discount_rupees]`;
        row.querySelector('.va-sale-reference').name = `items[${index}][reference_user_id]`;
        holder.append(row); connect(row); recalculate(); row.querySelector('.va-sale-search').focus();
    });
    document.addEventListener('click', event => listRows().forEach(row => {
        if (!row.querySelector('.va-sale-picker').contains(event.target)) row.querySelector('.va-sale-options').hidden = true;
    }));

    form.addEventListener('submit', event => {
        const seen = new Set();
        for (const row of listRows()) {
            const search = row.querySelector('.va-sale-search');
            const id = row.querySelector('.va-sale-product-id').value;
            search.setCustomValidity(!id ? 'Choose a product from the list.' : seen.has(id) ? 'This product is already selected.' : '');
            if (!search.checkValidity()) { event.preventDefault(); search.reportValidity(); return; }
            const quantity = row.querySelector('.va-sale-qty').value;
            const gross = (/^\d+$/.test(quantity) ? BigInt(quantity) : 0n) * parsePaise(row.querySelector('.va-sale-rate').value);
            const discount = parsePaise(row.querySelector('.va-sale-discount').value || '0');
            const discountInput = row.querySelector('.va-sale-discount');
            discountInput.setCustomValidity(discount > gross ? 'Discount cannot exceed product amount.' : '');
            if (!discountInput.checkValidity()) { event.preventDefault(); discountInput.reportValidity(); return; }
            seen.add(id);
        }
        if (!form.checkValidity()) { event.preventDefault(); form.reportValidity(); return; }
        document.getElementById('saveSale').disabled = true;
    });
});
