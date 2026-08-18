(function () {
    'use strict';

    const scope = document.querySelector('[data-admin-draft-scope]');
    if (!scope) return;

    const draftKey = 'copot.admin.system-manager.drafts.v1';
    const readDrafts = () => {
        try { const value = JSON.parse(window.sessionStorage.getItem(draftKey) || '{}'); return value && typeof value === 'object' ? value : {}; } catch (error) { return {}; }
    };
    let drafts = readDrafts();
    const writeDrafts = () => { try { window.sessionStorage.setItem(draftKey, JSON.stringify(drafts)); } catch (error) {} };
    const forms = Array.from(scope.querySelectorAll('[data-admin-capability-form]'));
    const valuesFor = (form) => {
        const values = {};
        new FormData(form).forEach((value, key) => { if (key !== '_token' && typeof value === 'string') values[key] = value; });
        return values;
    };
    const applyValues = (form, values) => {
        Object.entries(values || {}).forEach(([name, value]) => {
            const field = form.elements.namedItem(name);
            if (!field || typeof field.value === 'undefined') return;
            field.value = value;
            field.dispatchEvent(new Event('change', { bubbles: true }));
        });
        form.querySelectorAll('[data-color-control]').forEach((control) => {
            const input = control.querySelector('[data-color-input]');
            const native = control.querySelector('[data-color-native]');
            const canonical = control.querySelector('[data-color-canonical]');
            if (!input || !native || !canonical || !/^#[0-9a-fA-F]{6}$/.test(input.value)) return;
            canonical.value = input.value.toLowerCase();
            native.value = canonical.value;
        });
    };
    const dirtyKeys = () => new Set(forms.map((form) => form.dataset.adminCapability).filter((key) => drafts[key]));
    const updateTabs = () => {
        const dirty = dirtyKeys();
        document.querySelectorAll('[data-admin-capability-tab]').forEach((tab) => {
            const section = tab.dataset.adminCapabilityTab;
            const hasDirty = forms.some((form) => form.dataset.adminCapabilitySection === section && dirty.has(form.dataset.adminCapability));
            const indicator = tab.querySelector('.admin-capability-tab__dirty');
            if (indicator) indicator.hidden = !hasDirty;
            tab.classList.toggle('is-dirty', hasDirty);
        });
    };
    const clearAll = () => { drafts = {}; try { window.sessionStorage.removeItem(draftKey); } catch (error) {} updateTabs(); };
    const clearCapability = scope.dataset.adminClearCapability;
    if (clearCapability) { delete drafts[clearCapability]; writeDrafts(); }

    forms.forEach((form) => {
        const key = form.dataset.adminCapability;
        if (drafts[key]) applyValues(form, drafts[key]);
        const markDirty = () => { drafts[key] = valuesFor(form); writeDrafts(); updateTabs(); };
        form.addEventListener('input', markDirty);
        form.addEventListener('change', markDirty);
    });
    updateTabs();

    const systemPath = window.location.pathname.replace(/\/$/, '');
    const hasDirty = () => dirtyKeys().size > 0;
    document.addEventListener('click', (event) => {
        const link = event.target.closest ? event.target.closest('a[href]') : null;
        if (!link || !hasDirty()) return;
        let destination;
        try { destination = new URL(link.href, window.location.href); } catch (error) { return; }
        if (destination.pathname.replace(/\/$/, '') === systemPath) return;
        if (!window.confirm('You have unsaved System Manager changes. Leave without saving?')) { event.preventDefault(); return; }
        clearAll();
    }, true);
    window.addEventListener('beforeunload', (event) => {
        if (!hasDirty()) return;
        event.preventDefault();
        event.returnValue = '';
    });

    const fitGroups = document.querySelectorAll('[data-admin-fit-group]');
    const fitField = (field) => {
        const label = field.querySelector('.admin-field__label');
        const control = field.querySelector('select, input, textarea, .system-manager-color-control');
        if (!label || !control) return true;
        const originalStyle = control.getAttribute('style');
        control.style.width = 'max-content';
        control.style.maxWidth = 'none';
        const gap = parseFloat(window.getComputedStyle(field).columnGap) || 0;
        const fits = label.getBoundingClientRect().width + gap + control.getBoundingClientRect().width <= field.clientWidth + 1;
        if (originalStyle === null) control.removeAttribute('style'); else control.setAttribute('style', originalStyle);
        return fits;
    };
    fitGroups.forEach((group) => {
        const fields = () => Array.from(group.querySelectorAll(':scope > .admin-fit-field'));
        const setLevel = (level) => { group.classList.remove('is-level-1', 'is-level-2', 'is-level-3'); group.classList.add('is-level-' + level); group.dataset.adminFitLevel = String(level); };
        let frame = 0;
        const evaluate = () => {
            frame = 0;
            if (group.clientWidth < 1) return;
            setLevel(1);
            if (fields().every(fitField)) return;
            setLevel(2);
            if (fields().every(fitField)) return;
            setLevel(3);
        };
        const schedule = () => { if (frame) return; frame = window.requestAnimationFrame(evaluate); };
        if (typeof window.ResizeObserver === 'function') { const observer = new ResizeObserver(schedule); observer.observe(group); }
        new MutationObserver(schedule).observe(group, { childList: true, characterData: true, subtree: true });
        window.addEventListener('resize', schedule, { passive: true });
        schedule();
    });
}());
