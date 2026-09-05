document.addEventListener('DOMContentLoaded', function () {
    const fieldset = document.querySelector('.site-settings-identity__hero');
    const typeField = document.getElementById('homepage_content_type')?.closest('.admin-field');
    const pageField = document.querySelector('[data-homepage-content-page-field]');
    if (!fieldset || !typeField || !pageField || fieldset.querySelector('.site-settings-homepage-content-grid')) return;

    const grid = document.createElement('div');
    grid.className = 'site-settings-homepage-content-grid';
    const helper = typeField.querySelector('.admin-field__help');
    const error = pageField.querySelector('.admin-field__error');
    helper?.classList.add('site-settings-homepage-content-grid__help');
    error?.classList.add('site-settings-homepage-content-grid__error');
    helper?.remove();
    error?.remove();
    typeField.parentNode.insertBefore(grid, typeField);
    grid.append(typeField, pageField);
    if (helper) grid.append(helper);
    if (error) grid.append(error);
});
