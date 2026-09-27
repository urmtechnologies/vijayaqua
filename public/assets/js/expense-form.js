document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('expenseForm');
    if (!form) return;
    form.addEventListener('submit', event => {
        if (!form.checkValidity()) { event.preventDefault(); form.reportValidity(); return; }
        document.getElementById('saveExpense').disabled = true;
        document.getElementById('expenseSpinner').classList.remove('d-none');
        document.getElementById('expenseButtonText').textContent = 'Saving...';
    });
});
