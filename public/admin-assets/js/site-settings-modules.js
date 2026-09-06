(() => {
    'use strict';

    const root = document.querySelector('[data-site-settings-modules]');
    if (!root) return;

    const rows = Array.from(root.querySelectorAll('[data-site-settings-module-row]'));
    const filters = Object.fromEntries(Array.from(root.querySelectorAll('[data-site-settings-module-filter]')).map((control) => [control.dataset.siteSettingsModuleFilter, control]));
    const count = root.querySelector('[data-site-settings-module-count]');
    const noMatch = root.querySelector('[data-site-settings-module-no-match]');

    const open = (row) => {
        const target = row.dataset.detailUrl;
        if (target) window.location.assign(target);
    };

    rows.forEach((row) => {
        row.addEventListener('click', () => open(row));
        row.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                open(row);
            }
        });
    });

    const filter = () => {
        const values = Object.fromEntries(Object.entries(filters).map(([key, control]) => [key, (control?.value || '').trim().toLowerCase()]));
        const active = Object.values(values).some((value) => value !== '');
        let visible = 0;
        rows.forEach((row) => {
            const matches = Object.entries(values).every(([key, value]) => value === '' || (row.dataset[`filter${key[0].toUpperCase()}${key.slice(1)}`] || '') === value || (key === 'name' && (row.dataset.filterName || '').includes(value)));
            row.hidden = !matches;
            if (matches) visible += 1;
        });
        if (count) count.textContent = active ? `${visible} matching Module${visible === 1 ? '' : 's'}` : `${rows.length} Modules`;
        if (noMatch) noMatch.hidden = !active || visible !== 0;
    };

    Object.values(filters).forEach((control) => control.addEventListener('input', filter));
    Object.values(filters).forEach((control) => control.addEventListener('change', filter));
    filter();
})();
