document.addEventListener('DOMContentLoaded', () => {
    const type = document.getElementById('type');
    const hours = document.getElementById('hours');
    if (!type || !hours) return;
    const update = () => {
        const custom = type.value === 'custom';
        document.getElementById('hoursGroup').classList.toggle('d-none', !custom);
        hours.required = custom;
        hours.disabled = !custom;
    };
    type.addEventListener('change', update);
    update();
});
