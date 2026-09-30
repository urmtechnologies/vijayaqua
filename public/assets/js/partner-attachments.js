document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-partner-images]');
    if (!root) return;
    const input = root.querySelector('[data-partner-file]');
    const previews = root.querySelector('[data-partner-previews]');
    const error = root.querySelector('[data-partner-image-error]');
    const removals = Array.from(root.querySelectorAll('input[name="remove_attachments[]"]'));
    const max = Number(root.dataset.maxImages) || 10;
    let files = [];
    let objectUrls = [];

    function validate() {
        removals.forEach(checkbox => checkbox.closest('[data-existing-image]').classList.toggle('is-removed', checkbox.checked));
        const count = removals.filter(checkbox => !checkbox.checked).length + files.length;
        const message = count > max ? `Keep a maximum of ${max} images. Remove an image to add another.`
            : files.some(file => file.size > 5 * 1024 * 1024) ? 'Each image must be 5 MB or smaller.' : '';
        input.setCustomValidity(message);
        error.hidden = !message;
        error.textContent = message;
    }

    function sync() {
        const selection = new DataTransfer();
        files.forEach(file => selection.items.add(file));
        input.files = selection.files;
        objectUrls.forEach(url => URL.revokeObjectURL(url));
        objectUrls = [];
        previews.replaceChildren();
        files.forEach((file, index) => {
            const card = document.createElement('div'); card.className = 'va-partner-image';
            const preview = document.createElement('img');
            preview.alt = file.name;
            preview.src = URL.createObjectURL(file); objectUrls.push(preview.src);
            const name = document.createElement('span'); name.className = 'va-partner-image-name'; name.textContent = file.name; name.title = file.name;
            const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'btn btn-sm btn-light va-partner-image-remove'; remove.textContent = 'Remove';
            remove.setAttribute('aria-label', `Remove ${file.name}`);
            remove.addEventListener('click', () => { files.splice(index, 1); sync(); });
            card.append(preview, name, remove); previews.append(card);
        });
        validate();
    }
    input.addEventListener('change', () => {
        for (const file of Array.from(input.files)) {
            if (!files.some(item => item.name === file.name && item.size === file.size && item.lastModified === file.lastModified)) files.push(file);
        }
        sync();
    });
    removals.forEach(checkbox => checkbox.addEventListener('change', validate));
    window.addEventListener('beforeunload', () => objectUrls.forEach(url => URL.revokeObjectURL(url)));
    validate();
});
