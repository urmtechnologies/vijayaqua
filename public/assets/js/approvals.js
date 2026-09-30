document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('approvalWorkspace');
    if (!root) return;
    let active;

    async function load(url, push = true) {
        active?.abort();
        const controller = new AbortController();
        active = controller;
        root.classList.add('is-loading');
        try {
            const response = await fetch(url, {signal: controller.signal, credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html'}});
            if (response.status === 401 || response.redirected) { location.assign(response.url); return; }
            if (!response.ok) throw new Error('Could not load approvals.');
            const html = await response.text();
            if (controller.signal.aborted) return;
            root.innerHTML = html;
            if (push && url.href !== location.href) history.pushState({}, '', url);
        } catch (error) {
            if (error.name !== 'AbortError' && !controller.signal.aborted) feedback(error.message, true);
        } finally {
            if (active === controller) { active = null; root.classList.remove('is-loading'); }
        }
    }

    function feedback(message, error = false) {
        let area = root.querySelector('.va-approval-feedback');
        if (!area) { area = document.createElement('div'); area.className = 'va-approval-feedback'; area.setAttribute('role', 'status'); root.prepend(area); }
        area.hidden = false; area.classList.toggle('is-error', error); area.textContent = message;
    }

    root.addEventListener('click', event => {
        const link = event.target.closest('a[href]');
        if (!link || !link.closest('.va-approval-tabs, .pagination, section h5')) return;
        event.preventDefault(); load(new URL(link.href));
    });
    root.addEventListener('submit', async event => {
        const form = event.target.closest('.va-approval-form');
        if (!form) return;
        event.preventDefault();
        const button = form.querySelector('button');
        button.disabled = true; button.textContent = 'Approving…';
        try {
            const response = await fetch(form.action, {method: 'POST', credentials: 'same-origin', headers: {
                'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            }});
            if (response.status === 401 || response.redirected) { location.assign(response.url); return; }
            const data = await response.json();
            if (!response.ok) throw new Error(Object.values(data.errors || {})[0]?.[0] || data.message || 'Approval failed.');
            await load(new URL(location.href), false);
            feedback(data.message);
        } catch (error) { button.disabled = false; button.textContent = 'Approve'; feedback(error.message, true); }
    });
    window.addEventListener('popstate', () => load(new URL(location.href), false));
});
