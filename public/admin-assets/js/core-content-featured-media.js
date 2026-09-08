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
        const closeButton = root.querySelector('[data-core-content-media-close]');
        let restoreFocus = null;

        if (!input || !selected || !status || !openButton || !clearButton || !dialog || !results || !closeButton) return;

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
                results.textContent = 'No supported images are available.';
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
                .then((data) => renderItems(Array.isArray(data.items) ? data.items : []))
                .catch(() => { results.textContent = 'Media is unavailable. Try again later.'; });
        };

        let initial = null;
        try { initial = JSON.parse(root.dataset.selectedMedia || 'null'); } catch (_) { initial = null; }
        renderSelected(initial);
        openButton.addEventListener('click', () => { restoreFocus = document.activeElement; dialog.showModal(); load(); closeButton.focus(); });
        closeButton.addEventListener('click', () => { dialog.close(); restoreFocus?.focus(); });
        clearButton.addEventListener('click', () => {
            setValue('');
            renderSelected(null);
            announce('Featured Media selection cleared. Save Content to remove it.');
        });
    });
})();
