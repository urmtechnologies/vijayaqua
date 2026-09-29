document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('paymentForm');
    if (!form) return;
    const invoice = document.getElementById('paymentInvoice');
    const saleId = document.getElementById('paymentSaleId');
    const info = document.getElementById('paymentInvoiceInfo');
    const amount = document.getElementById('paymentAmount');
    let timer;
    let pending;

    function message(text, linkUrl) {
        info.replaceChildren();
        const span = document.createElement('span');
        span.textContent = text;
        info.append(span);
        if (linkUrl) {
            const link = document.createElement('a');
            link.href = linkUrl;
            link.textContent = 'View invoice';
            link.className = 'ms-2';
            info.append(link);
        }
    }

    async function lookup() {
        pending?.abort();
        const value = invoice.value.trim();
        saleId.value = '';
        if (!value) { message('Enter an invoice number.'); return; }
        const controller = new AbortController();
        pending = controller;
        message('Checking invoice...');
        try {
            const url = new URL(form.dataset.lookupUrl);
            url.searchParams.set('invoice', value);
            const response = await fetch(url, {signal: controller.signal, headers: {Accept: 'application/json'}, credentials: 'same-origin'});
            if (!response.ok) throw new Error('Lookup failed');
            const data = await response.json();
            if (controller.signal.aborted || value !== invoice.value.trim()) return;
            if (!data.found) { message('Invoice not found.'); return; }
            saleId.value = data.id;
            amount.max = String(data.due);
            document.getElementById('paymentDate').min = data.sale_date;
            message(`${data.party} · ${data.mobile} · Due ₹${Number(data.due).toLocaleString('en-IN', {minimumFractionDigits: 2})}`, data.url);
            if (Number(data.due) <= 0) message('This invoice is already fully paid.', data.url);
        } catch (error) {
            if (error.name !== 'AbortError') message('Unable to check invoice. Please retry.');
        }
    }

    invoice.addEventListener('input', () => {
        clearTimeout(timer);
        pending?.abort();
        saleId.value = '';
        amount.removeAttribute('max');
        timer = setTimeout(lookup, 350);
    });
    if (invoice.value.trim()) lookup();

    form.addEventListener('submit', event => {
        if (!saleId.value) {
            event.preventDefault();
            invoice.setCustomValidity('Choose a valid invoice.');
            invoice.reportValidity();
            invoice.setCustomValidity('');
            return;
        }
        if (!form.checkValidity()) { event.preventDefault(); form.reportValidity(); return; }
        document.getElementById('savePayment').disabled = true;
        document.getElementById('paymentSpinner').classList.remove('d-none');
        document.getElementById('paymentButtonText').textContent = 'Recording...';
    });
});
