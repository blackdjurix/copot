(() => {
    'use strict';

    const preview = document.querySelector('[data-media-preview]');
    const template = document.querySelector('[data-media-preview-actions-template]');
    const cards = Array.from(document.querySelectorAll('[data-media-card]'));

    if (!preview || !template || cards.length === 0) {
        return;
    }

    const dialog = preview.querySelector('[role="dialog"]');
    const closeButtons = Array.from(preview.querySelectorAll('[data-media-preview-close]'));
    const previousButton = preview.querySelector('[data-media-preview-prev]');
    const nextButton = preview.querySelector('[data-media-preview-next]');
    const stage = preview.querySelector('[data-media-preview-stage]');
    const details = preview.querySelector('[data-media-preview-details]');
    const title = preview.querySelector('#media-preview-title');
    const main = preview.closest('main');
    const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
    let currentIndex = 0;
    let origin = null;
    let lockedScrollY = 0;
    let inactiveElements = [];

    const currentCard = () => cards[currentIndex];
    const visibleFocusable = () => Array.from(dialog.querySelectorAll(focusableSelector))
        .filter((element) => !element.hasAttribute('hidden') && element.getClientRects().length > 0);

    const setBackgroundInactive = (inactive) => {
        if (!main) return;
        if (inactive) {
            inactiveElements = Array.from(main.children).filter((element) => element !== preview);
            inactiveElements.forEach((element) => {
                if ('inert' in element) element.inert = true;
                element.setAttribute('aria-hidden', 'true');
            });
        } else {
            inactiveElements.forEach((element) => {
                if ('inert' in element) element.inert = false;
                element.removeAttribute('aria-hidden');
            });
            inactiveElements = [];
        }
    };

    const lockScroll = () => {
        lockedScrollY = window.scrollY;
        document.body.style.position = 'fixed';
        document.body.style.top = `-${lockedScrollY}px`;
        document.body.style.width = '100%';
        document.documentElement.classList.add('admin-media-preview-open');
    };

    const unlockScroll = () => {
        document.body.style.position = '';
        document.body.style.top = '';
        document.body.style.width = '';
        document.documentElement.classList.remove('admin-media-preview-open');
        window.scrollTo(0, lockedScrollY);
    };

    const addDetail = (label, value) => {
        if (!value) return;
        const item = document.createElement('span');
        const strong = document.createElement('strong');
        strong.textContent = `${label}: `;
        item.append(strong, document.createTextNode(value));
        details.append(item);
    };

    const renderStage = (card) => {
        stage.replaceChildren();
        if (card.dataset.mediaKind === 'image') {
            const image = document.createElement('img');
            image.src = card.dataset.mediaPublicUrl;
            image.alt = card.dataset.mediaTitle;
            stage.append(image);
            return;
        }

        const documentPreview = document.createElement('div');
        documentPreview.className = 'admin-media-preview__document';
        const icon = document.createElement('span');
        icon.className = 'admin-media-preview__document-icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = 'PDF';
        const label = document.createElement('span');
        label.textContent = 'Document preview';
        documentPreview.append(icon, label);
        stage.append(documentPreview);
    };

    const renderActions = (card) => {
        const actions = template.content.cloneNode(true);
        const titleForm = actions.querySelector('[data-preview-title-form]');
        const deleteForm = actions.querySelector('[data-preview-delete-form]');

        if (titleForm) {
            titleForm.action = card.dataset.mediaTitleUrl;
            titleForm.querySelector('[data-preview-title-input]').value = card.dataset.mediaTitle;
            titleForm.querySelector('[data-preview-title-input]').setAttribute('aria-label', `Title for ${card.dataset.mediaTitle}`);
            titleForm.querySelector('[data-preview-title-label]').textContent = 'Title';
        }
        if (deleteForm) deleteForm.setAttribute('action', card.dataset.mediaDeleteUrl);

        const fragment = document.createDocumentFragment();
        fragment.append(actions);
        return fragment;
    };

    const render = () => {
        const card = currentCard();
        title.textContent = card.dataset.mediaTitle;
        title.setAttribute('data-media-preview-title', '');
        details.replaceChildren();
        addDetail('Filename', card.dataset.mediaFilename);
        addDetail('Type', card.dataset.mediaKind === 'document' ? 'PDF / document' : card.dataset.mediaMime);
        addDetail('Size', card.dataset.mediaBytes);
        if (card.dataset.mediaWidth && card.dataset.mediaHeight) addDetail('Dimensions', `${card.dataset.mediaWidth} × ${card.dataset.mediaHeight}`);
        addDetail('Access', card.dataset.mediaEditable === '1' ? 'Editable' : 'Manage-only');
        addDetail('Usage', card.dataset.mediaUsageCount === '0' ? 'Not currently referenced' : `${card.dataset.mediaUsageCount} active reference${card.dataset.mediaUsageCount === '1' ? '' : 's'}`);
        addDetail('Variants', card.dataset.mediaVariantCount === '0' ? 'No generated variants' : `${card.dataset.mediaVariantCount} generated variant${card.dataset.mediaVariantCount === '1' ? '' : 's'}${card.dataset.mediaVariantWidths ? ` (${card.dataset.mediaVariantWidths}px widths)` : ''}`);
        renderStage(card);
        const actions = preview.querySelector('[data-media-preview-actions]');
        actions.replaceChildren(renderActions(card));
        const deleteHelp = preview.querySelector('[data-preview-delete-help]');
        if (deleteHelp) deleteHelp.textContent = card.dataset.mediaUsageCount === '0' ? 'This item is currently eligible for deletion if you have permission.' : 'Deletion is blocked automatically while this media is in use.';
        previousButton.disabled = currentIndex === 0;
        nextButton.disabled = currentIndex === cards.length - 1;
        dialog.scrollTop = 0;
    };

    const open = (index) => {
        currentIndex = Math.max(0, Math.min(index, cards.length - 1));
        origin = document.activeElement;
        render();
        preview.hidden = false;
        preview.setAttribute('aria-hidden', 'false');
        setBackgroundInactive(true);
        lockScroll();
        window.requestAnimationFrame(() => preview.querySelector('button[data-media-preview-close]').focus());
    };

    const close = () => {
        if (preview.hidden) return;
        preview.hidden = true;
        preview.setAttribute('aria-hidden', 'true');
        setBackgroundInactive(false);
        unlockScroll();
        if (origin && typeof origin.focus === 'function') window.requestAnimationFrame(() => origin.focus());
    };

    cards.forEach((card, index) => {
        card.addEventListener('click', () => open(index));
        card.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                open(index);
            }
        });
    });

    closeButtons.forEach((button) => button.addEventListener('click', close));
    previousButton.addEventListener('click', () => { if (currentIndex > 0) { currentIndex -= 1; render(); } });
    nextButton.addEventListener('click', () => { if (currentIndex < cards.length - 1) { currentIndex += 1; render(); } });

    document.addEventListener('keydown', (event) => {
        if (preview.hidden) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            close();
            return;
        }
        if (event.key !== 'Tab') return;
        const items = visibleFocusable();
        if (items.length === 0) {
            event.preventDefault();
            dialog.focus();
            return;
        }
        const first = items[0];
        const last = items[items.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
})();
