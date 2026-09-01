(function () {
    var workspace = document.querySelector('[data-navigation-workspace]');
    if (!workspace) return;
    var select = document.querySelector('[data-navigation-target]');
    if (!select) return;
    var panels = document.querySelectorAll('[data-navigation-target-panel]');
    function syncTarget() {
        panels.forEach(function (panel) {
            var active = panel.dataset.navigationTargetPanel === select.value;
            panel.hidden = !active;
            panel.querySelectorAll('input, select').forEach(function (control) { control.disabled = !active; });
        });
    }
    select.addEventListener('change', syncTarget);
    syncTarget();
    var form = workspace.querySelector('[data-navigation-edit-form]');
    var initial = form ? new FormData(form) : null;
    function dirty() {
        if (!form || !initial) return false;
        var current = new FormData(form);
        for (var pair of initial.entries()) if (current.getAll(pair[0]).join('|') !== initial.getAll(pair[0]).join('|')) return true;
        return false;
    }
    workspace.querySelectorAll('[data-navigation-cancel]').forEach(function (link) { link.addEventListener('click', function (event) { if (dirty() && !window.confirm('Discard unsaved changes?')) event.preventDefault(); }); });
    workspace.querySelectorAll('[data-navigation-delete]').forEach(function (deleteForm) { deleteForm.addEventListener('submit', function (event) { if (!window.confirm('Delete this navigation item?')) event.preventDefault(); }); });
}());
