(function () {
    const root = document.querySelector('[data-core-content-list-search]');
    const table = document.querySelector('#core-content-list-table');
    if (!root || !table) return;
    const rows = Array.from(table.querySelectorAll('[data-core-content-row]'));
    const empty = document.querySelector('[data-core-content-list-empty]');

    const filter = () => {
        const query = root.value.trim().toLocaleLowerCase();
        let visible = 0;
        rows.forEach((row) => {
            const haystack = `${row.dataset.contentTitle || ''} ${row.dataset.contentSlug || ''}`;
            const match = !query || haystack.includes(query);
            row.hidden = !match;
            if (match) visible += 1;
        });
        if (empty) empty.hidden = visible !== 0;
    };

    root.addEventListener('input', filter);
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
