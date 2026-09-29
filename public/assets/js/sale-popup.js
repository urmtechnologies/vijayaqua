document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('editSaleModal');
    if (!modal) return;
    const frame = modal.querySelector('iframe');
    modal.addEventListener('show.bs.modal', event => {
        frame.src = event.relatedTarget?.dataset.saleUrl || 'about:blank';
    });
    frame.addEventListener('load', () => {
        try {
            if (frame.contentWindow.location.pathname === new URL(frame.dataset.partyPath).pathname) window.location.reload();
        } catch (_) { /* Browser may block inspecting a frame before it loads. */ }
    });
    modal.addEventListener('hidden.bs.modal', () => { frame.src = 'about:blank'; });
});
