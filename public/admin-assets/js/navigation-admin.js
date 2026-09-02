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
    function navigationNode(id) { return workspace.querySelector('[data-navigation-node][data-navigation-id="' + id + '"]'); }
    function siblingIds(parentId) {
        var list = parentId ? navigationNode(parentId)?.querySelector(':scope > [data-navigation-children]') : workspace.querySelector('[data-navigation-tree-body] > nav > [data-navigation-tree], [data-navigation-tree-body] > nav > ol');
        return Array.from(list?.children || []).map(function (node) { return node.dataset.navigationId; });
    }
    function submitMove(movedId, parentId, ids) {
        if (workspace.dataset.navigationDragEnabled !== 'true') return;
        var body = new URLSearchParams(); body.set('_token', workspace.dataset.navigationCsrf || ''); body.set('moved_id', movedId); if (parentId) body.set('parent_id', parentId); ids.forEach(function (id) { body.append('item_ids[]', id); });
        fetch(workspace.dataset.navigationReorderUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' }, body: body.toString() }).then(function (response) { if (!response.ok) throw new Error('Navigation reorder failed'); window.location.reload(); }).catch(function () { window.alert('Navigation order could not be saved.'); });
    }
    workspace.querySelectorAll('[data-navigation-toggle]').forEach(function (toggle) { toggle.addEventListener('click', function (event) { event.preventDefault(); event.stopPropagation(); var node = toggle.closest('[data-navigation-node]'); var expanded = toggle.getAttribute('aria-expanded') === 'true'; toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true'); toggle.setAttribute('aria-label', (expanded ? 'Expand ' : 'Collapse ') + (node.querySelector('.admin-navigation-tree__select span:nth-of-type(2)')?.textContent || 'navigation item')); node.classList.toggle('is-collapsed', expanded); }); });
    workspace.querySelectorAll('[data-navigation-move]').forEach(function (button) { button.addEventListener('click', function (event) { event.preventDefault(); event.stopPropagation(); var node = button.closest('[data-navigation-node]'); var parent = node.dataset.navigationParent || ''; var ids = siblingIds(parent); var index = ids.indexOf(node.dataset.navigationId); var next = button.dataset.navigationMove === 'up' ? index - 1 : index + 1; if (index < 0 || next < 0 || next >= ids.length) return; var moved = ids.splice(index, 1)[0]; ids.splice(next, 0, moved); submitMove(moved, parent, ids); }); });
    var dragged = null; var expandTimer = null;
    function clearDropState() { workspace.querySelectorAll('.is-drop-target, .is-drop-before, .is-drop-after, .is-drop-inside, .is-dragging-target').forEach(function (element) { element.classList.remove('is-drop-target', 'is-drop-before', 'is-drop-after', 'is-drop-inside', 'is-dragging-target'); }); if (expandTimer) { window.clearTimeout(expandTimer); expandTimer = null; } }
    function dropPlan(target, event) { var rect = target.getBoundingClientRect(); var fraction = (event.clientY - rect.top) / Math.max(rect.height, 1); var zone = fraction < .28 ? 'before' : (fraction > .72 ? 'after' : 'inside'); var targetNode = target.closest('[data-navigation-node]'); var parent = zone === 'inside' ? targetNode.dataset.navigationId : (targetNode.dataset.navigationParent || ''); var ids = siblingIds(parent).filter(function (id) { return id !== dragged; }); if (zone === 'inside') ids.push(dragged); else { var targetIndex = ids.indexOf(targetNode.dataset.navigationId); ids.splice(zone === 'before' ? targetIndex : targetIndex + 1, 0, dragged); } return { parent: parent, ids: ids }; }
    workspace.querySelectorAll('[data-navigation-row][draggable="true"]').forEach(function (row) {
        row.addEventListener('dragstart', function (event) { if (workspace.dataset.navigationDragEnabled !== 'true') { event.preventDefault(); return; } dragged = row.dataset.navigationId; row.classList.add('is-dragging'); event.dataTransfer.effectAllowed = 'move'; event.dataTransfer.setData('text/plain', dragged); });
        row.addEventListener('dragend', function () { row.classList.remove('is-dragging'); dragged = null; clearDropState(); });
        row.addEventListener('dragover', function (event) { if (!dragged || dragged === row.dataset.navigationId) return; event.preventDefault(); clearDropState(); var plan = dropPlan(row, event); var targetNode = row.closest('[data-navigation-node]'); row.classList.add('is-drop-target', 'is-drop-' + (plan.parent === row.dataset.navigationId ? 'inside' : ((event.clientY - row.getBoundingClientRect().top) < row.getBoundingClientRect().height * .5 ? 'before' : 'after'))); if (plan.parent === row.dataset.navigationId) { targetNode.classList.add('is-dragging-target'); if (targetNode.classList.contains('is-collapsed')) { expandTimer = window.setTimeout(function () { var toggle = targetNode.querySelector('[data-navigation-toggle]'); if (toggle) toggle.click(); }, 600); } } });
        row.addEventListener('dragleave', function () { clearDropState(); });
        row.addEventListener('drop', function (event) { if (!dragged || dragged === row.dataset.navigationId) return; event.preventDefault(); var plan = dropPlan(row, event); clearDropState(); submitMove(dragged, plan.parent, plan.ids); });
    });
    if (form) form.addEventListener('submit', function () { if (window.matchMedia('(max-width: 760px)').matches) sessionStorage.setItem('navigation-saved', '1'); });
    if (window.matchMedia('(max-width: 760px)').matches && sessionStorage.getItem('navigation-saved') === '1') {
        sessionStorage.removeItem('navigation-saved');
        history.replaceState(null, '', workspace.dataset.navigationTreeUrl || '/admin/navigation');
        workspace.classList.remove('has-navigation-detail');
        workspace.querySelectorAll('[data-navigation-detail], [data-navigation-dismiss]').forEach(function (element) { element.hidden = true; });
    }
}());
