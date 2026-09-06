(() => {
    'use strict';

    const root = document.querySelector('[data-site-settings-modules]');
    if (!root) return;

    const rows = Array.from(root.querySelectorAll('[data-site-settings-module-row]'));
    const search = root.querySelector('[data-site-settings-module-search]');
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
        const needle = (search?.value || '').trim().toLowerCase();
        let visible = 0;
        rows.forEach((row) => {
            const matches = needle === '' || (row.dataset.searchIndex || '').includes(needle);
            row.hidden = !matches;
            if (matches) visible += 1;
        });
        if (count) count.textContent = needle === '' ? `${rows.length} Modules` : `${visible} matching Module${visible === 1 ? '' : 's'}`;
        if (noMatch) noMatch.hidden = visible !== 0;
    };

    search?.addEventListener('input', filter);
})();
