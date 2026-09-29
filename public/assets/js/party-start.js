document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('partyStartForm');
    if (!form) return;
    const mobile = document.getElementById('partyMobile');
    const name = document.getElementById('partyName');
    const hint = document.getElementById('partyStartHint');
    let timer;
    mobile.addEventListener('input', () => {
        clearTimeout(timer);
        hint.textContent = '';
        if (!/^\d{10}$/.test(mobile.value)) return;
        const number = mobile.value;
        timer = setTimeout(async () => {
            try {
                const url = new URL(form.dataset.customerLookup, window.location.origin);
                url.searchParams.set('mobile', number);
                const response = await fetch(url, {headers: {Accept: 'application/json'}});
                const result = await response.json();
                if (mobile.value !== number) return;
                if (result.found) {
                    name.value = result.name;
                    document.getElementById('businessName').value = result.business_name || '';
                    hint.textContent = 'Existing party found. Continue to its sale entry.';
                } else hint.textContent = 'New party. Continue to add products.';
            } catch (_) { hint.textContent = 'Party will be checked when you continue.'; }
        }, 300);
    });
});
