(() => {
    'use strict';

    const preview = document.querySelector('[data-media-preview]');
    const rows = Array.from(document.querySelectorAll('[data-media-preview-open]'));
    if (!preview || rows.length === 0) return;

    const dialog = preview.querySelector('[role="dialog"]');
    const stage = preview.querySelector('[data-media-preview-stage]');
    const details = preview.querySelector('[data-media-preview-details]');
    const title = preview.querySelector('#media-preview-title');
    const deleteForm = preview.querySelector('[data-media-preview-delete]');
    const deleteButton = preview.querySelector('[data-media-preview-delete-button]');
    let origin = null;

    const addDetail = (label, value) => {
        if (!value) return;
        const term = document.createElement('dt');
        term.textContent = label;
        const definition = document.createElement('dd');
        definition.textContent = value;
        details.append(term, definition);
    };

    const render = (row) => {
        const data = row.dataset;
        title.textContent = data.mediaFilename;
        stage.replaceChildren();
        if (data.mediaKind === 'image') {
            const image = document.createElement('img');
            image.src = data.mediaUrl;
            image.alt = data.mediaFilename;
            stage.append(image);
        } else {
            const documentPreview = document.createElement('div');
            documentPreview.className = 'admin-media-preview__document';
            documentPreview.textContent = 'Document preview';
            stage.append(documentPreview);
        }
        details.replaceChildren();
        addDetail('Filename', data.mediaFilename);
        addDetail('Type', data.mediaMime);
        if (data.mediaWidth && data.mediaHeight) addDetail('Dimensions', `${data.mediaWidth} × ${data.mediaHeight}`);
        addDetail('Usage', data.mediaUsage === '0' ? 'Not currently referenced' : `${data.mediaUsage} active reference${data.mediaUsage === '1' ? '' : 's'}`);
        if (deleteForm) deleteForm.action = data.mediaDeleteUrl;
        if (deleteButton) deleteButton.hidden = data.mediaDeleteEligible !== '1';
    };

    const close = () => {
        if (preview.hidden) return;
        preview.hidden = true;
        preview.setAttribute('aria-hidden', 'true');
        if (origin && typeof origin.focus === 'function') origin.focus();
    };

    const open = (row) => {
        origin = document.activeElement;
        render(row);
        preview.hidden = false;
        preview.setAttribute('aria-hidden', 'false');
        window.requestAnimationFrame(() => preview.querySelector('[data-media-preview-close]').focus());
    };

    rows.forEach((row) => {
        row.addEventListener('click', () => open(row));
        row.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') return;
            event.preventDefault();
            open(row);
        });
    });
    preview.querySelectorAll('[data-media-preview-close]').forEach((button) => button.addEventListener('click', close));
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); });
})();
