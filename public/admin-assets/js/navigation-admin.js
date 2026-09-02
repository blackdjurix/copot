(function () {
    var workspace = document.querySelector('[data-navigation-workspace]');
    if (!workspace) return;
    var select = document.querySelector('[data-navigation-target]');
    var targetFields = document.querySelector('[data-navigation-target-fields]');
    var contentOptions = [];
    var targetValues = { link: '', content: '' };
    var activeTarget = select ? select.value : '';
    try { contentOptions = JSON.parse(workspace.dataset.navigationContentOptions || '[]'); } catch (error) { contentOptions = []; }
    function field(tag, attributes, text) {
        var element = document.createElement(tag);
        Object.keys(attributes || {}).forEach(function (name) { element.setAttribute(name, attributes[name]); });
        if (text !== undefined) element.textContent = text;
        return element;
    }
    function rememberTargetValue() {
        if (!select || !targetFields) return;
        if (activeTarget === 'link' || activeTarget === 'custom') targetValues.link = targetFields.querySelector('[name="custom_url"]')?.value || '';
        if (activeTarget === 'content') targetValues.content = targetFields.querySelector('[name="content_reference"]')?.value || '';
    }
    function syncTarget() {
        if (!select || !targetFields) return;
        rememberTargetValue();
        targetFields.replaceChildren();
        if (select.value === 'link') {
            var customField = field('div', { 'class': 'admin-field' });
            customField.append(field('label', { 'class': 'admin-field__label', 'for': 'custom_url' }, 'Link'));
            var customInput = field('input', { id: 'custom_url', name: 'custom_url', placeholder: 'example.com, /path, #section, or https://example.com' });
            customInput.value = targetValues.link;
            customField.append(customInput);
            customField.append(field('p', { 'class': 'admin-field__help' }, 'Use example.com, /path, #section, or an explicit supported scheme.'));
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
        } else if (select.value === 'article_collection') {
            var help = field('p', { 'class': 'admin-field__help' }, 'Uses the canonical published Articles collection at ');
            help.append(field('code', {}, '/articles'));
            help.append(document.createTextNode('.'));
            targetFields.append(help);
        } else {
            targetFields.append(field('p', { 'class': 'admin-field__help' }, 'Navigation Group has no destination and can contain child items.'));
        }
        activeTarget = select.value;
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
    function requestTransition(destination) {
        if (dirty() && !window.confirm('Discard unsaved changes?')) return false;
        window.location.assign(destination);
        return true;
    }
    function guardedNavigation(event, link) {
        if (!dirty()) return;
        event.preventDefault();
        requestTransition(link.href);
    }
    workspace.querySelectorAll('[data-navigation-cancel]').forEach(function (button) { button.addEventListener('click', function () { var mobile = window.matchMedia('(max-width: 760px)').matches; requestTransition(mobile ? (workspace.dataset.navigationTreeUrl || '/admin/navigation') : button.dataset.navigationCancelUrl); }); });
    workspace.querySelectorAll('[data-navigation-delete]').forEach(function (deleteForm) { deleteForm.addEventListener('submit', function (event) { if (!window.confirm('Delete this navigation item?')) event.preventDefault(); }); });
    if (backdrop) backdrop.addEventListener('click', function () { requestTransition(workspace.dataset.navigationTreeUrl || '/admin/navigation'); });
    workspace.addEventListener('click', function (event) {
        var link = event.target.closest('a');
        if (link && !event.target.closest('.admin-navigation-workspace__detail')) guardedNavigation(event, link);
        if (event.defaultPrevented || window.matchMedia('(max-width: 760px)').matches || !workspace.classList.contains('has-navigation-detail')) return;
        if (event.target.closest('.admin-navigation-workspace__detail, .admin-navigation-workspace__master .admin-panel__header, .admin-navigation-tree__row, a, button, input, select, textarea, label, form, [role="button"]')) return;
        requestTransition(workspace.dataset.navigationTreeUrl || '/admin/navigation');
    });
    workspace.querySelectorAll('.admin-navigation-tree__branch').forEach(function (branch) { branch.addEventListener('click', function (event) { if (branch.closest('.admin-navigation-tree__row')?.classList.contains('is-selected')) { event.preventDefault(); event.stopPropagation(); } }); });
    if (form) form.addEventListener('submit', function () { if (window.matchMedia('(max-width: 760px)').matches) sessionStorage.setItem('navigation-saved', '1'); });
    if (window.matchMedia('(max-width: 760px)').matches && sessionStorage.getItem('navigation-saved') === '1') {
        sessionStorage.removeItem('navigation-saved');
        history.replaceState(null, '', workspace.dataset.navigationTreeUrl || '/admin/navigation');
        workspace.classList.remove('has-navigation-detail');
        workspace.querySelectorAll('[data-navigation-detail], [data-navigation-dismiss]').forEach(function (element) { element.hidden = true; });
    }
}());
