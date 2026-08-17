(function () {
    'use strict';

    const detailList = document.querySelector('[data-system-manager-details]');
    if (detailList) {
        const rows = () => Array.from(detailList.querySelectorAll(':scope > div'));
        let frame = 0;
        const evaluateDetailFit = () => {
            frame = 0;
            if (detailList.clientWidth < 1) return;
            detailList.classList.remove('is-stacked');
            const available = detailList.clientWidth;
            const stacked = rows().some((row) => {
                const label = row.querySelector('dt');
                const value = row.querySelector('dd');
                if (!label || !value) return false;
                const originalStyle = value.getAttribute('style');
                value.style.whiteSpace = 'nowrap';
                value.style.width = 'max-content';
                value.style.maxWidth = 'none';
                value.style.overflow = 'visible';
                const gap = parseFloat(window.getComputedStyle(row).columnGap) || 0;
                const required = label.getBoundingClientRect().width + gap + value.getBoundingClientRect().width;
                if (originalStyle === null) value.removeAttribute('style');
                else value.setAttribute('style', originalStyle);
                return required > available + 1;
            });
            detailList.classList.toggle('is-stacked', stacked);
        };
        const schedule = () => {
            if (frame) return;
            frame = window.requestAnimationFrame(evaluateDetailFit);
        };
        if (typeof window.ResizeObserver === 'function') {
            const observer = new ResizeObserver(schedule);
            observer.observe(detailList);
        }
        new MutationObserver(schedule).observe(detailList, { childList: true, characterData: true, subtree: true });
        window.addEventListener('resize', schedule, { passive: true });
        schedule();
    }

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
        if (payload.guidance) {
            const guidance = document.createElement('p');
            guidance.textContent = payload.guidance;
            result.appendChild(guidance);
        }
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
