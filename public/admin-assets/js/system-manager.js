(function () {
    'use strict';

    const form = document.querySelector('[data-system-manager-upload]');
    const result = document.querySelector('[data-system-manager-result]');
    if (!form || !result || typeof window.fetch !== 'function') return;

    const render = (payload) => {
        result.hidden = false;
        result.textContent = '';
        const heading = document.createElement('strong');
        heading.textContent = payload.accepted ? 'Preflight accepted' : 'Lifecycle request blocked';
        const detail = document.createElement('p');
        detail.textContent = [payload.status, payload.action, payload.reason].filter(Boolean).join(' · ');
        result.append(heading, detail);
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const data = new FormData(form);
        try {
            const response = await fetch(form.action, { method: 'POST', body: data, credentials: 'same-origin' });
            const payload = await response.json();
            render(payload);
            if (!payload.accepted || !payload.action) return;

            const apply = document.createElement('button');
            apply.type = 'button';
            apply.className = 'admin-button admin-button--primary';
            apply.textContent = 'Apply ' + payload.action;
            apply.addEventListener('click', async () => {
                data.set('action', payload.action);
                const applyResponse = await fetch(form.dataset.applyAction, { method: 'POST', body: data, credentials: 'same-origin' });
                render(await applyResponse.json());
            });
            result.appendChild(apply);
        } catch (error) {
            result.hidden = false;
            result.textContent = 'Package preflight is unavailable.';
        }
    });
}());
