(function () {
    'use strict';

    var root = document.querySelector('[data-media-list-filters]');
    var table = document.getElementById('admin-media-list-table');
    if (!root || !table) return;

    var rows = Array.prototype.slice.call(table.querySelectorAll('[data-media-list-row]'));
    var search = root.querySelector('[data-media-list-search]');
    var kind = root.querySelector('[data-media-list-kind]');
    var usage = root.querySelector('[data-media-list-usage]');
    var pageSize = root.querySelector('[data-media-list-page-size]');
    var summary = document.querySelector('[data-media-list-summary]');
    var empty = document.querySelector('[data-media-list-empty]');

    function apply() {
        var query = (search.value || '').trim().toLowerCase();
        var selectedKind = kind.value || '';
        var selectedUsage = usage.value || '';
        var limit = parseInt(pageSize.value || '24', 10) || 24;
        var visible = 0;

        rows.forEach(function (row) {
            var matches = (!query || (row.dataset.mediaTitleFilter || '').indexOf(query) !== -1 || (row.dataset.mediaFilenameFilter || '').indexOf(query) !== -1)
                && (!selectedKind || row.dataset.mediaKindFilter === selectedKind)
                && (!selectedUsage || (selectedUsage === 'used' ? Number(row.dataset.mediaUsageCount) > 0 : Number(row.dataset.mediaUsageCount) === 0));
            if (matches && visible < limit) {
                row.hidden = false;
                visible += 1;
            } else {
                row.hidden = true;
            }
        });

        if (summary) summary.textContent = 'Showing ' + (visible ? '1–' + visible : '0') + ' of ' + visible + ' results.';
        if (empty) empty.hidden = visible !== 0;
    }

    root.querySelector('[data-media-list-apply]')?.addEventListener('click', apply);
    search.addEventListener('input', apply);
    kind.addEventListener('change', apply);
    usage.addEventListener('change', apply);
    pageSize.addEventListener('change', apply);
    apply();
}());
