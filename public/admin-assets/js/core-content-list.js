(() => {
    const filters = document.querySelector('[data-core-content-list-filters]');
    const search = document.querySelector('[data-core-content-list-search]');
    const type = document.querySelector('[data-core-content-list-type]');
    const status = document.querySelector('[data-core-content-list-status]');
    const pageSize = document.querySelector('[data-core-content-list-page-size]');
    const apply = document.querySelector('[data-core-content-list-apply]');
    const summary = document.querySelector('[data-core-content-list-summary]');
    const table = document.querySelector('#core-content-list-table');
    if (!filters || !search || !type || !status || !pageSize || !apply || !summary || !table) return;

    const rows = Array.from(table.querySelectorAll('[data-core-content-row]'));
    const empty = document.querySelector('[data-core-content-list-empty]');
    const filter = () => {
        const query = search.value.trim().toLowerCase();
        const selectedType = type.value;
        const selectedStatus = status.value;
        const limit = Math.max(1, Number.parseInt(pageSize.value, 10) || rows.length);
        const matches = rows.filter((row) => {
            const textMatches = !query || `${row.dataset.contentTitle || ''} ${row.dataset.contentSlug || ''}`.includes(query);
            return textMatches
                && (!selectedType || row.dataset.contentType === selectedType)
                && (!selectedStatus || row.dataset.contentStatus === selectedStatus);
        });
        rows.forEach((row) => { row.hidden = matches.indexOf(row) === -1 || matches.indexOf(row) >= limit; });
        const shown = Math.min(limit, matches.length);
        summary.textContent = matches.length === 0
            ? 'Showing 0 results.'
            : `Showing 1–${shown} of ${matches.length} result${matches.length === 1 ? '' : 's'}.`;
        if (empty) empty.hidden = matches.length !== 0;
    };

    search.addEventListener('input', filter);
    type.addEventListener('change', filter);
    status.addEventListener('change', filter);
    pageSize.addEventListener('change', filter);
    apply.addEventListener('click', filter);
    filters.addEventListener('submit', (event) => { event.preventDefault(); filter(); });

    rows.forEach((row) => {
        const target = () => {
            if (row.dataset.contentEditUrl) window.location.href = row.dataset.contentEditUrl;
        };
        row.addEventListener('click', (event) => {
            if (event.target.closest('a,button,input,select,textarea,form')) return;
            target();
        });
        row.addEventListener('keydown', (event) => {
            if ((event.key === 'Enter' || event.key === ' ') && !event.target.closest('a,button,input,select,textarea,form')) {
                event.preventDefault();
                target();
            }
        });
    });

    filter();
})();
