(() => {
    'use strict';

    const preview = document.querySelector('[data-media-preview]');
    const openButtons = Array.from(document.querySelectorAll('[data-media-preview-open]'));
    if (!preview || openButtons.length === 0) return;

    const dialog = preview.querySelector('[role="dialog"]');
    const stage = preview.querySelector('[data-media-preview-stage]');
    const details = preview.querySelector('[data-media-preview-details]');
    const title = preview.querySelector('#media-preview-title');
    const deleteForm = preview.querySelector('[data-media-preview-delete]');
    const deleteHelp = preview.querySelector('[data-media-preview-delete-help]');
    let origin = null;

    const detail = (label, value) => {
        if (!value) return;
        const item = document.createElement('span');
        const strong = document.createElement('strong');
        strong.textContent = `${label}: `;
        item.append(strong, document.createTextNode(value));
        details.append(item);
    };

    const render = (button) => {
        const data = button.dataset;
        title.textContent = data.mediaTitle;
        stage.replaceChildren();
        if (data.mediaKind === 'image') {
            const image = document.createElement('img');
            image.src = data.mediaUrl;
            image.alt = data.mediaTitle;
            stage.append(image);
        } else {
            const documentPreview = document.createElement('div');
            documentPreview.className = 'admin-media-preview__document';
            const label = document.createElement('span');
            label.textContent = 'Document preview';
            documentPreview.append(label);
            stage.append(documentPreview);
        }
        details.replaceChildren();
        detail('Filename', data.mediaFilename);
        detail('Type', data.mediaMime);
        if (data.mediaWidth && data.mediaHeight) detail('Dimensions', `${data.mediaWidth} × ${data.mediaHeight}`);
        detail('Usage', data.mediaUsage === '0' ? 'Not currently referenced' : `${data.mediaUsage} active reference${data.mediaUsage === '1' ? '' : 's'}`);
        if (deleteForm) deleteForm.action = data.mediaDeleteUrl;
        if (deleteHelp) deleteHelp.textContent = data.mediaUsage === '0' ? 'This item is currently eligible for deletion if you have permission.' : 'Deletion is blocked automatically while this media is in use.';
    };

    const close = () => {
        if (preview.hidden) return;
        preview.hidden = true;
        preview.setAttribute('aria-hidden', 'true');
        if (origin && typeof origin.focus === 'function') origin.focus();
    };

    openButtons.forEach((button) => button.addEventListener('click', () => {
        origin = document.activeElement;
        render(button);
        preview.hidden = false;
        preview.setAttribute('aria-hidden', 'false');
        window.requestAnimationFrame(() => preview.querySelector('[data-media-preview-close]').focus());
    }));
    preview.querySelectorAll('[data-media-preview-close]').forEach((button) => button.addEventListener('click', close));
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); });
})();
