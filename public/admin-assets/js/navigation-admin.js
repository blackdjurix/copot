(function () {
    var workspace = document.querySelector('[data-navigation-workspace]');
    if (!workspace) return;
    var select = document.querySelector('[data-navigation-target]');
    var panels = document.querySelectorAll('[data-navigation-target-panel]');
    function syncTarget() {
        panels.forEach(function (panel) {
            var active = panel.dataset.navigationTargetPanel === select.value;
            panel.hidden = !active;
            panel.querySelectorAll('input, select').forEach(function (control) { control.disabled = !active; });
        });
    }
    if (select) {
        select.addEventListener('change', syncTarget);
        syncTarget();
    }
    var form = workspace.querySelector('[data-navigation-edit-form]');
    var backdrop = workspace.querySelector('[data-navigation-dismiss]');
    var initial = form ? new FormData(form) : null;
    function dirty() {
        if (!form || !initial) return false;
        var current = new FormData(form);
        for (var pair of initial.entries()) if (current.getAll(pair[0]).join('|') !== initial.getAll(pair[0]).join('|')) return true;
        return false;
    }
    workspace.querySelectorAll('[data-navigation-cancel]').forEach(function (button) { button.addEventListener('click', function () { if (dirty() && !window.confirm('Discard unsaved changes?')) return; var mobile = window.matchMedia('(max-width: 760px)').matches; window.location.href = mobile ? (workspace.dataset.navigationTreeUrl || '/admin/navigation') : button.dataset.navigationCancelUrl; }); });
    workspace.querySelectorAll('[data-navigation-delete]').forEach(function (deleteForm) { deleteForm.addEventListener('submit', function (event) { if (!window.confirm('Delete this navigation item?')) event.preventDefault(); }); });
    if (backdrop) backdrop.addEventListener('click', function () { var cancel = workspace.querySelector('[data-navigation-cancel]'); if (cancel) cancel.click(); else window.location.href = workspace.dataset.navigationTreeUrl || '/admin/navigation'; });
    if (form) form.addEventListener('submit', function () { if (window.matchMedia('(max-width: 760px)').matches) sessionStorage.setItem('navigation-saved', '1'); });
    if (window.matchMedia('(max-width: 760px)').matches && sessionStorage.getItem('navigation-saved') === '1') {
        sessionStorage.removeItem('navigation-saved');
        history.replaceState(null, '', workspace.dataset.navigationTreeUrl || '/admin/navigation');
        workspace.classList.remove('has-navigation-detail');
        workspace.querySelectorAll('[data-navigation-detail], [data-navigation-dismiss]').forEach(function (element) { element.hidden = true; });
    }
}());
