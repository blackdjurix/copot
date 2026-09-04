(() => {
    'use strict';

    document.querySelectorAll('[data-site-settings-hero-picker]').forEach((dialog) => {
        const root = dialog.closest('[data-site-settings-hero-picker]')?.parentElement;
        const input = root?.querySelector('[data-site-settings-hero-input]');
        const selected = root?.querySelector('[data-site-settings-hero-selected]');
        const status = root?.querySelector('[data-site-settings-hero-status]');
        const results = dialog.querySelector('[data-site-settings-hero-results]');
        const upload = dialog.querySelector('[data-site-settings-hero-upload]');
        const uploadButton = dialog.querySelector('[data-site-settings-hero-upload-button]');
        const clearButton = root?.querySelector('[data-site-settings-hero-clear]');
        const openButton = root?.querySelector('[data-site-settings-hero-open]');
        const closeButton = dialog.querySelector('[data-site-settings-hero-close]');
        let restore = null;

        if (!input || !selected || !status || !results || !openButton || !closeButton) return;

        const announce = (message) => { status.textContent = message; };
        const itemId = (item) => String(item.id ?? ((item.url || '').split('/').pop() || ''));
        const setInputValue = (value) => {
            input.value = value;
            input.defaultValue = value;
            input.setAttribute('value', value);
        };
        const renderSelected = (item) => {
            selected.replaceChildren();
            selected.hidden = false;
            if (clearButton) clearButton.hidden = !item;
            if (!item) {
                const empty = document.createElement('span');
                empty.className = 'site-settings-hero-preview__empty';
                empty.textContent = 'No Hero Image selected.';
                selected.append(empty);
                return;
            }
            const image = document.createElement('img');
            image.src = item.url;
            image.alt = 'Homepage Hero Image';
            image.loading = 'lazy';
            selected.append(image);
        };
        const renderItems = (items) => {
            results.replaceChildren();
            if (!items.length) {
                results.textContent = 'No supported images are available.';
                return;
            }
            items.forEach((item) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'admin-media-picker__item';
                button.dataset.mediaId = itemId(item);
                button.setAttribute('aria-pressed', itemId(item) === input.value ? 'true' : 'false');
                button.setAttribute('aria-label', `${item.title || item.original_filename || 'Image'} (${item.original_filename || item.mime_type})`);
                const image = document.createElement('img');
                image.src = item.url;
                image.alt = '';
                image.loading = 'lazy';
                const caption = document.createElement('span');
                caption.textContent = item.title || item.original_filename || 'Image';
                const filename = document.createElement('small');
                filename.textContent = item.original_filename || '';
                button.append(image, caption, filename);
                button.addEventListener('click', () => {
                    dialog.close();
                    setInputValue(itemId(item));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    renderSelected(item);
                    announce('Homepage Hero Image selected. Save Site Settings to keep the selection.');
                    if (restore) restore.focus();
                });
                results.append(button);
            });
        };
        const load = () => {
            results.textContent = 'Loading images…';
            const url = `${dialog.dataset.pickerUrl}?kind=image&current=${encodeURIComponent(input.value || '')}`;
            fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
                .then((response) => response.ok ? response.json() : Promise.reject(new Error()))
                .then((data) => {
                    const items = data.items || [];
                    const current = items.find((item) => String(item.id) === input.value);
                    if (current) renderSelected(current);
                    renderItems(items);
                })
                .catch(() => { results.textContent = 'Media is unavailable. Try again later.'; });
        };
        const open = () => { restore = document.activeElement; dialog.showModal(); load(); closeButton.focus(); };
        openButton.addEventListener('click', open);
        closeButton.addEventListener('click', () => { dialog.close(); if (restore) restore.focus(); });
        clearButton?.addEventListener('click', () => { setInputValue(''); input.dispatchEvent(new Event('change', { bubbles: true })); renderSelected(null); announce('Homepage Hero Image selection cleared. Save Site Settings to remove it.'); });
        uploadButton?.addEventListener('click', () => {
            const file = upload?.files?.[0];
            if (!file) { announce('Choose one JPEG, PNG, or WebP image first.'); return; }
            const form = new FormData();
            form.append('_token', dialog.dataset.csrfToken || '');
            form.append('media', file);
            announce('Uploading image…');
            fetch(dialog.dataset.uploadUrl, { method: 'POST', body: form, credentials: 'same-origin' })
                .then((response) => response.ok ? response.json() : response.json().then((data) => Promise.reject(new Error(data.error || 'The image could not be uploaded.'))))
                .then((data) => { dialog.close(); setInputValue(String(data.id)); input.dispatchEvent(new Event('change', { bubbles: true })); announce('Image uploaded and selected. Save Site Settings to keep the selection.'); load(); if (restore) restore.focus(); })
                .catch((error) => announce(error.message || 'The image could not be uploaded.'));
        });
    });
})();
