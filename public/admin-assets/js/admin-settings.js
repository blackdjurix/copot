(() => {
    'use strict';

    const page = document.querySelector('[data-settings-page]');
    if (!page) return;

    const tabs = Array.from(page.querySelectorAll('[data-settings-tab]'));
    const panels = new Map(Array.from(page.querySelectorAll('[data-settings-panel]')).map((panel) => [panel.dataset.settingsPanel, panel]));
    const tabKey = (tab) => tab.dataset.settingsTabKey || (tab.dataset.settingsTab || '').replace(/^site-settings-/, '');
    const validKeys = tabs.map(tabKey);
    const dirtyTabs = new Set();
    let isSubmitting = false;

    const hashKey = () => window.location.hash.replace(/^#/, '').trim().toLowerCase();
    const initialKey = validKeys.includes(hashKey()) ? hashKey() : (validKeys.includes(page.dataset.initialTab) ? page.dataset.initialTab : 'identity');

    const activate = (key, { focus = false, updateHash = true } = {}) => {
        if (!validKeys.includes(key)) key = 'identity';
        tabs.forEach((tab) => {
            const active = tabKey(tab) === key;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            tab.tabIndex = active ? 0 : -1;
            if (active && focus) tab.focus();
        });
        panels.forEach((panel, panelId) => { panel.hidden = panelId !== `site-settings-${key}`; });
        if (updateHash && window.location.hash !== `#${key}`) history.replaceState(null, '', `${window.location.pathname}${window.location.search}#${key}`);
    };

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => activate(tab.dataset.settingsTab));
        tab.addEventListener('keydown', (event) => {
            let next = null;
            if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
            if (event.key === 'ArrowLeft') next = (index - 1 + tabs.length) % tabs.length;
            if (event.key === 'Home') next = 0;
            if (event.key === 'End') next = tabs.length - 1;
            if (next !== null) {
                event.preventDefault();
                activate(tabs[next].dataset.settingsTab, { focus: true });
                return;
            }
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                activate(tab.dataset.settingsTab, { focus: true });
            }
        });
    });

    const markDirty = (tabId) => {
        dirtyTabs.add(tabId);
        const tab = tabs.find((candidate) => candidate.dataset.settingsTab === tabId);
        const indicator = tab?.querySelector('.admin-settings-tab__dirty');
        if (indicator) indicator.hidden = false;
    };

    page.querySelectorAll('[data-settings-dirty-form]').forEach((form) => {
        const panel = form.closest('[data-settings-panel]');
        const tabId = panel?.dataset.settingsPanel || 'general';
        form.addEventListener('input', () => markDirty(tabId));
        form.addEventListener('change', () => markDirty(tabId));
        form.addEventListener('submit', () => {
            isSubmitting = true;
            dirtyTabs.clear();
            const button = form.querySelector('button[type="submit"]');
            if (button) {
                button.disabled = true;
                button.dataset.originalLabel = button.textContent || '';
                button.textContent = button.dataset.savingLabel || 'Saving…';
            }
        });
    });

    page.querySelectorAll('[data-asset-input]').forEach((input) => {
        input.addEventListener('change', () => {
            const file = input.files?.[0];
            if (!file) return;
            const slot = input.dataset.assetInput;
            const preview = page.querySelector(`[data-asset-preview="${slot}"]`);
            const empty = page.querySelector(`[data-asset-empty="${slot}"]`);
            if (!preview) return;
            let image = preview.querySelector('[data-asset-preview-image]');
            if (!image) {
                image = document.createElement('img');
                image.alt = preview.dataset.assetPreviewAlt || '';
                image.setAttribute('data-asset-preview-image', '');
                preview.appendChild(image);
            }
            const previous = image.dataset.previewObjectUrl;
            if (previous) URL.revokeObjectURL(previous);
            const objectUrl = URL.createObjectURL(file);
            image.src = objectUrl;
            image.dataset.previewObjectUrl = objectUrl;
            preview.hidden = false;
            if (empty) empty.hidden = true;
        });
    });

    window.addEventListener('hashchange', () => activate(validKeys.includes(hashKey()) ? hashKey() : 'identity', { updateHash: false }));
    window.addEventListener('beforeunload', (event) => {
        if (!isSubmitting && dirtyTabs.size > 0) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    activate(initialKey, { updateHash: !window.location.hash });
})();
