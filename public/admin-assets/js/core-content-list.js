(function () {
    const filters = document.querySelector('[data-core-content-list-filters]');
    const root = document.querySelector('[data-core-content-list-search]');
    const type = document.querySelector('[data-core-content-list-type]');
    const status = document.querySelector('[data-core-content-list-status]');
    const author = document.querySelector('[data-core-content-list-author]');
    const clear = document.querySelector('[data-core-content-list-clear]');
    const summary = document.querySelector('[data-core-content-list-summary]');
    const table = document.querySelector('#core-content-list-table');
    if (!filters || !root || !type || !status || !author || !clear || !table) return;
    const rows = Array.from(table.querySelectorAll('[data-core-content-row]'));
    const empty = document.querySelector('[data-core-content-list-empty]');

    const filter = () => {
        const query = root.value.trim().toLocaleLowerCase();
        const selectedType = type.value;
        const selectedStatus = status.value;
        const selectedAuthor = author.value;
        let visible = 0;
        rows.forEach((row) => {
            const haystack = `${row.dataset.contentTitle || ''} ${row.dataset.contentSlug || ''}`;
            const match = (!query || haystack.includes(query))
                && (!selectedType || row.dataset.contentType === selectedType)
                && (!selectedStatus || row.dataset.contentStatus === selectedStatus)
                && (!selectedAuthor || row.dataset.contentAuthor === selectedAuthor);
            row.hidden = !match;
            if (match) visible += 1;
        });
        if (empty) empty.hidden = visible !== 0;
        if (summary) summary.textContent = `${visible} ${visible === 1 ? 'Content item' : 'Content items'} shown.`;
    };

    root.addEventListener('input', filter);
    [type, status, author].forEach((control) => control.addEventListener('change', filter));
    clear.addEventListener('click', () => {
        root.value = '';
        type.value = '';
        status.value = '';
        author.value = '';
        filter();
        root.focus();
    });
    rows.forEach((row) => {
        const destination = row.dataset.contentEditUrl;
        if (!destination) return;
        const open = () => { window.location.href = destination; };
        row.addEventListener('click', (event) => {
            if (!event.target.closest('a, button, input, select, textarea, form')) open();
        });
        row.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                open();
            }
        });
    });
}());
