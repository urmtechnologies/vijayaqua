document.addEventListener('DOMContentLoaded', () => {
    const selection = document.getElementById('salarySelection');
    if (!selection) return;
    const button = document.getElementById('fetchSalary');
    const preview = document.getElementById('salaryPreview');
    const form = document.getElementById('generateSalary');
    let pending;
    const reset = () => { form.classList.add('d-none'); preview.innerHTML = ''; pending?.abort(); };
    selection.querySelectorAll('select, input').forEach(field => field.addEventListener('change', reset));
    button.addEventListener('click', async () => {
        const employee = document.getElementById('employee_id').value;
        const month = document.getElementById('month').value;
        if (!employee || !month) { preview.textContent = 'Choose a staff member and a completed month.'; return; }
        pending?.abort();
        const controller = new AbortController();
        pending = controller;
        button.disabled = true;
        document.getElementById('fetchSpinner').classList.remove('d-none');
        document.getElementById('fetchLabel').textContent = 'Loading...';
        form.classList.add('d-none');
        preview.textContent = 'Loading attendance...';
        try {
            const url = new URL(selection.dataset.previewUrl);
            url.searchParams.set('employee_id', employee);
            url.searchParams.set('month', month);
            const response = await fetch(url, {signal: controller.signal, headers: {'X-Requested-With': 'XMLHttpRequest'}, credentials: 'same-origin'});
            if (response.redirected) { location.assign(response.url); return; }
            if (!response.ok) throw new Error('Unable to fetch attendance.');
            const html = await response.text();
            if (controller.signal.aborted) return;
            preview.innerHTML = html;
            if (!preview.querySelector('.alert-warning, .alert-info')) {
                form.querySelector('[name="employee_id"]').value = employee;
                form.querySelector('[name="month"]').value = month;
                form.classList.remove('d-none');
            }
        } catch (error) {
            if (!controller.signal.aborted) preview.textContent = 'Unable to fetch attendance. Please retry.';
        } finally {
            if (pending === controller) {
                button.disabled = false;
                document.getElementById('fetchSpinner').classList.add('d-none');
                document.getElementById('fetchLabel').textContent = 'Fetch Attendance';
            }
        }
    });
});
