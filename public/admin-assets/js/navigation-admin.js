(function () {
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
}());
