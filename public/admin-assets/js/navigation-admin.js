(function () {
    var workspace = document.querySelector('[data-navigation-workspace]');
    if (!workspace) return;
    var select = document.querySelector('[data-navigation-target]');
    var targetFields = document.querySelector('[data-navigation-target-fields]');
    var contentOptions = [];
    var targetValues = { custom: '', content: '' };
    try { contentOptions = JSON.parse(workspace.dataset.navigationContentOptions || '[]'); } catch (error) { contentOptions = []; }
    function field(tag, attributes, text) {
        var element = document.createElement(tag);
        Object.keys(attributes || {}).forEach(function (name) { element.setAttribute(name, attributes[name]); });
        if (text !== undefined) element.textContent = text;
        return element;
    }
    function rememberTargetValue() {
        if (!select || !targetFields) return;
        if (select.value === 'custom') targetValues.custom = targetFields.querySelector('[name="custom_url"]')?.value || '';
        if (select.value === 'content') targetValues.content = targetFields.querySelector('[name="content_reference"]')?.value || '';
    }
    function syncTarget() {
        if (!select || !targetFields) return;
        rememberTargetValue();
        targetFields.replaceChildren();
        if (select.value === 'custom') {
            var customField = field('div', { 'class': 'admin-field' });
            customField.append(field('label', { 'class': 'admin-field__label', 'for': 'custom_url' }, 'URL'));
            var customInput = field('input', { id: 'custom_url', name: 'custom_url', placeholder: 'https://example.com, /internal-path, or #section' });
            customInput.value = targetValues.custom;
            customField.append(customInput);
            customField.append(field('p', { 'class': 'admin-field__help' }, 'Use https://example.com, /internal-path, or #section.'));
            targetFields.append(customField);
        } else if (select.value === 'content') {
            var contentField = field('div', { 'class': 'admin-field' });
            var contentSelect = field('select', { id: 'content_reference', name: 'content_reference' });
            contentField.append(field('label', { 'class': 'admin-field__label', 'for': 'content_reference' }, 'Content'));
            contentSelect.append(field('option', { value: '' }, 'Select published Content'));
            contentOptions.forEach(function (option) { contentSelect.append(field('option', { value: option.slug }, option.label)); });
            contentSelect.value = targetValues.content;
            contentField.append(contentSelect);
            targetFields.append(contentField);
        } else {
            var help = field('p', { 'class': 'admin-field__help' }, 'Uses the canonical published Articles collection at ');
            help.append(field('code', {}, '/articles'));
            help.append(document.createTextNode('.'));
            targetFields.append(help);
        }
    }
    if (select) {
        select.addEventListener('change', syncTarget);
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
    workspace.querySelectorAll('[data-navigation-unselect]').forEach(function (surface) { surface.addEventListener('click', function () { window.location.href = workspace.dataset.navigationTreeUrl || '/admin/navigation'; }); });
    if (form) form.addEventListener('submit', function () { if (window.matchMedia('(max-width: 760px)').matches) sessionStorage.setItem('navigation-saved', '1'); });
    if (window.matchMedia('(max-width: 760px)').matches && sessionStorage.getItem('navigation-saved') === '1') {
        sessionStorage.removeItem('navigation-saved');
        history.replaceState(null, '', workspace.dataset.navigationTreeUrl || '/admin/navigation');
        workspace.classList.remove('has-navigation-detail');
        workspace.querySelectorAll('[data-navigation-detail], [data-navigation-dismiss]').forEach(function (element) { element.hidden = true; });
    }
}());
