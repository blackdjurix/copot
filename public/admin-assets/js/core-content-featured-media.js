(() => {
    'use strict';

    document.querySelectorAll('[data-core-content-media-picker]').forEach((root) => {
        const input = root.querySelector('[data-core-content-media-input]');
        const selected = root.querySelector('[data-core-content-media-selected]');
        const status = root.querySelector('[data-core-content-media-status]');
        const openButton = root.querySelector('[data-core-content-media-open]');
        const clearButton = root.querySelector('[data-core-content-media-clear]');
        const dialog = root.querySelector('[data-core-content-media-dialog]');
        const results = root.querySelector('[data-core-content-media-results]');
        const search = root.querySelector('[data-core-content-media-search]');
        const closeButton = root.querySelector('[data-core-content-media-close]');
        const upload = root.querySelector('[data-core-content-media-upload]');
        const uploadButton = root.querySelector('[data-core-content-media-upload-button]');
        const placeholder = root.querySelector('[data-core-content-media-placeholder]');
        let restoreFocus = null;
        let availableItems = [];

        if (!input || !selected || !status || !openButton || !clearButton || !dialog || !results || !search || !closeButton) return;

        const announce = (message) => { status.textContent = message; };
        const setValue = (value) => {
            input.value = value;
            input.defaultValue = value;
            input.setAttribute('value', value);
        };
        const label = (item) => item.title || item.original_filename || 'Image';
        const renderSelected = (item) => {
            selected.replaceChildren();
            selected.hidden = !item;
            if (placeholder) placeholder.hidden = Boolean(item);
            openButton.textContent = item ? 'Change' : 'Select media';
            clearButton.hidden = !item;
            if (!item) return;

            const image = document.createElement('img');
            image.src = item.url;
            image.alt = '';
            image.loading = 'lazy';
            const identity = document.createElement('strong');
            identity.textContent = label(item);
            const filename = document.createElement('small');
            filename.textContent = item.original_filename || '';
            selected.append(image, identity, filename);
        };
        const renderItems = (items) => {
            results.replaceChildren();
            if (!items.length) {
                results.textContent = search.value.trim() ? 'No images match your search.' : 'No supported images are available.';
                return;
            }
            items.forEach((item) => {
                const id = String(item.id || '');
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'admin-media-picker__item admin-media-card';
                button.setAttribute('aria-pressed', id === input.value ? 'true' : 'false');
                button.setAttribute('aria-label', `${label(item)} (${item.original_filename || item.mime_type || 'image'})`);
                const preview = document.createElement('div');
                preview.className = 'admin-media-card__preview';
                const image = document.createElement('img');
                image.src = item.url;
                image.alt = '';
                image.loading = 'lazy';
                preview.append(image);
                const body = document.createElement('div');
                body.className = 'admin-media-card__body';
                const identity = document.createElement('div');
                identity.className = 'admin-media-card__identity';
                const title = document.createElement('h3');
                title.textContent = label(item);
                identity.append(title);
                const metadata = document.createElement('div');
                metadata.className = 'admin-media-card__meta';
                const filename = document.createElement('span');
                filename.textContent = item.original_filename || '';
                metadata.append(filename);
                body.append(identity, metadata);
                button.append(preview, body);
                button.addEventListener('click', () => {
                    setValue(id);
                    renderSelected(item);
                    dialog.close();
                    announce('Featured Media selected. Save Content to keep the selection.');
                    restoreFocus?.focus();
                });
                results.append(button);
            });
        };
        const load = () => {
            results.textContent = 'Loading images…';
            fetch(`${root.dataset.pickerUrl}?kind=image`, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
                .then((response) => response.ok ? response.json() : Promise.reject(new Error()))
                .then((data) => {
                    availableItems = Array.isArray(data.items) ? data.items : [];
                    const query = search.value.trim().toLocaleLowerCase();
                    renderItems(query ? availableItems.filter((item) => `${item.title || ''} ${item.original_filename || ''}`.toLocaleLowerCase().includes(query)) : availableItems);
                })
                .catch(() => { results.textContent = 'Media is unavailable. Try again later.'; });
        };

        let initial = null;
        try { initial = JSON.parse(root.dataset.selectedMedia || 'null'); } catch (_) { initial = null; }
        renderSelected(initial);
        openButton.addEventListener('click', () => { restoreFocus = document.activeElement; dialog.showModal(); load(); search.focus(); });
        closeButton.addEventListener('click', () => { dialog.close(); restoreFocus?.focus(); });
        search.addEventListener('input', () => {
            const query = search.value.trim().toLocaleLowerCase();
            const filtered = query ? availableItems.filter((item) => `${item.title || ''} ${item.original_filename || ''}`.toLocaleLowerCase().includes(query)) : availableItems;
            renderItems(filtered);
        });
        clearButton.addEventListener('click', () => {
            setValue('');
            renderSelected(null);
            announce('Featured Media selection cleared. Save Content to remove it.');
        });

        uploadButton?.addEventListener('click', () => {
            const file = upload?.files?.[0];
            if (!file) { announce('Choose one JPEG, PNG, or WebP image first.'); return; }
            const form = new FormData();
            form.append('_token', root.dataset.csrfToken || '');
            form.append('media', file);
            uploadButton.disabled = true;
            announce('Uploading image…');
            fetch(root.dataset.uploadUrl, { method: 'POST', body: form, credentials: 'same-origin', headers: { Accept: 'application/json' } })
                .then((response) => response.ok ? response.json() : response.json().then((data) => Promise.reject(new Error(data.error || 'The image could not be uploaded.'))))
                .then((item) => {
                    const descriptor = { id: item.id, title: file.name, original_filename: file.name, url: `${window.location.origin}/media/${item.id}` };
                    setValue(String(item.id));
                    renderSelected(descriptor);
                    dialog.close();
                    announce('Image uploaded and selected. Save Content to keep the selection.');
                    restoreFocus?.focus();
                })
                .catch((error) => announce(error.message || 'The image could not be uploaded.'))
                .finally(() => { uploadButton.disabled = false; });
        });
    });
})();
