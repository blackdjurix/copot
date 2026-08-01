(function () {
    'use strict';
    const root = document.querySelector('[data-media-picker]');
    if (!root) return;
    const dialog = root.querySelector('[data-media-picker-dialog]');
    const input = root.querySelector('[data-media-picker-input]');
    const status = root.querySelector('[data-media-picker-status]');
    const results = root.querySelector('[data-media-picker-results]');
    const search = root.querySelector('[data-media-picker-search]');
    const confirm = root.querySelector('[data-media-picker-confirm]');
    const upload = root.querySelector('[data-media-picker-upload]');
    const uploadButton = root.querySelector('[data-media-picker-upload-button]');
    let pending = input.value || '';
    let restore = null;
    function render(items) {
        results.textContent = '';
        if (!items.length) { results.textContent = 'No available images found.'; return; }
        items.forEach(function (item) {
            const button = document.createElement('button'); button.type = 'button'; button.className = 'admin-media-picker__item'; button.dataset.id = item.id; button.setAttribute('aria-pressed', String(String(item.id) === String(pending)));
            button.innerHTML = '<img alt="" src="' + item.url + '"><span>' + item.title.replace(/[&<>"']/g, function (c) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]; }) + '</span>';
            button.addEventListener('click', function () { pending = String(item.id); results.querySelectorAll('[data-id]').forEach(function (node) { node.setAttribute('aria-pressed', String(node.dataset.id) === pending); }); confirm.disabled = false; });
            results.appendChild(button);
        });
    }
    function load() { const url = root.dataset.pickerUrl + '?consumer=content&current=' + encodeURIComponent(input.value || '') + '&q=' + encodeURIComponent(search.value || ''); fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).then(function (r) { if (!r.ok) throw new Error(); return r.json(); }).then(function (data) { if (data.stale) status.textContent = 'The previously selected Media is stale or unavailable. Choose another image or clear it.'; render(data.items || []); }).catch(function () { results.textContent = 'Media is unavailable. Try again later.'; }); }
    root.querySelector('[data-media-picker-open]').addEventListener('click', function () { restore = document.activeElement; pending = input.value || ''; confirm.disabled = !pending; dialog.showModal(); search.focus(); load(); });
    root.querySelector('[data-media-picker-cancel]').addEventListener('click', function () { dialog.close(); if (restore) restore.focus(); });
    root.querySelector('[data-media-picker-clear]').addEventListener('click', function () { input.value = ''; pending = ''; status.textContent = 'No featured Media selected.'; });
    confirm.addEventListener('click', function () { input.value = pending; status.textContent = pending ? 'A featured image is selected.' : 'No featured Media selected.'; dialog.close(); if (restore) restore.focus(); });
    search.addEventListener('input', load);
    uploadButton.addEventListener('click', function () {
        if (!upload.files.length) { status.textContent = 'Choose one JPEG, PNG, or WebP image first.'; return; }
        const form = new FormData(); form.append('_token', root.dataset.csrfToken || ''); form.append('media', upload.files[0]);
        uploadButton.disabled = true;
        fetch(root.dataset.uploadUrl, { method: 'POST', body: form, credentials: 'same-origin' }).then(function (r) { if (!r.ok) throw new Error(); return r.json(); }).then(function (data) { pending = String(data.id); input.value = pending; status.textContent = 'Uploaded image selected.'; upload.value = ''; }).catch(function () { status.textContent = 'The image could not be uploaded.'; }).finally(function () { uploadButton.disabled = false; });
    });
    dialog.addEventListener('cancel', function () { if (restore) restore.focus(); });
}());
