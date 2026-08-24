(function () {
    'use strict';

    document.querySelectorAll('[data-color-control]').forEach((control) => {
        const native = control.querySelector('[data-color-native]');
        const input = control.querySelector('[data-color-input]');
        const canonical = control.querySelector('[data-color-canonical]');
        const validHex = (value) => /^#[0-9a-f]{6}$/i.test(value.trim());
        const syncDisplay = () => { input.value = canonical.value; input.removeAttribute('aria-invalid'); native.value = canonical.value; };
        native.addEventListener('input', () => { canonical.value = native.value.toLowerCase(); syncDisplay(); });
        input.addEventListener('input', () => { if (!validHex(input.value)) { input.setAttribute('aria-invalid', 'true'); return; } canonical.value = input.value.trim().toLowerCase(); native.value = canonical.value; input.removeAttribute('aria-invalid'); });
        syncDisplay();
    });

    const detailList = document.querySelector('[data-system-manager-details]');
    if (detailList) {
        const fitTolerance = 2;
        let frame = 0;
        const evaluateDetailFit = () => {
            frame = 0;
            if (detailList.clientWidth < 1) return;
            const available = detailList.clientWidth;
            const currentStacked = detailList.classList.contains('is-stacked');
            const measurement = detailList.cloneNode(true);
            measurement.removeAttribute('data-system-manager-details');
            measurement.classList.remove('is-stacked');
            measurement.setAttribute('aria-hidden', 'true');
            measurement.style.position = 'absolute';
            measurement.style.visibility = 'hidden';
            measurement.style.pointerEvents = 'none';
            measurement.style.width = available + 'px';
            measurement.style.maxWidth = available + 'px';
            detailList.parentElement.appendChild(measurement);
            const inlineFits = Array.from(measurement.querySelectorAll(':scope > div')).every((row) => {
                const label = row.querySelector('dt');
                const value = row.querySelector('dd');
                if (!label || !value) return true;
                row.style.gridTemplateColumns = 'max-content max-content';
                value.style.whiteSpace = 'nowrap';
                value.style.width = 'max-content';
                value.style.maxWidth = 'none';
                value.style.overflow = 'visible';
                const gap = parseFloat(window.getComputedStyle(row).columnGap) || 0;
                return label.getBoundingClientRect().width + gap + value.getBoundingClientRect().width <= available + fitTolerance;
            });
            const inlineSafe = Array.from(measurement.querySelectorAll(':scope > div')).every((row) => {
                const label = row.querySelector('dt');
                const value = row.querySelector('dd');
                if (!label || !value) return true;
                const gap = parseFloat(window.getComputedStyle(row).columnGap) || 0;
                return label.getBoundingClientRect().width + gap + value.getBoundingClientRect().width <= available - fitTolerance;
            });
            measurement.remove();
            const stacked = currentStacked ? !inlineSafe : !inlineFits;
            if (stacked !== currentStacked) detailList.classList.toggle('is-stacked', stacked);
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
        detail.textContent = [payload.status, payload.action, payload.module, payload.title, payload.classification, payload.reason].filter(Boolean).join(' · ');
        result.append(heading, detail);
        if (payload.next_action) {
            const next = document.createElement('p');
            next.textContent = 'Next action: ' + payload.next_action;
            result.appendChild(next);
        }
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
