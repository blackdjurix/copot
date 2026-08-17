(function () {
    'use strict';

    const colorControls = document.querySelectorAll('[data-color-control]');
    const clamp = (value, minimum, maximum) => Math.min(maximum, Math.max(minimum, value));
    const hexToRgb = (hex) => {
        const value = /^#[0-9a-f]{6}$/i.test(hex) ? hex.slice(1) : null;
        return value ? [parseInt(value.slice(0, 2), 16), parseInt(value.slice(2, 4), 16), parseInt(value.slice(4, 6), 16)] : null;
    };
    const rgbToHex = (rgb) => '#' + rgb.map((channel) => Math.round(clamp(channel, 0, 255)).toString(16).padStart(2, '0')).join('');
    const hexToHsl = (hex) => {
        const rgb = hexToRgb(hex).map((channel) => channel / 255);
        const maximum = Math.max(...rgb); const minimum = Math.min(...rgb); const delta = maximum - minimum;
        let hue = 0;
        const lightness = (maximum + minimum) / 2;
        const saturation = delta === 0 ? 0 : delta / (1 - Math.abs((2 * lightness) - 1));
        if (delta !== 0) {
            hue = maximum === rgb[0] ? ((rgb[1] - rgb[2]) / delta) % 6 : maximum === rgb[1] ? ((rgb[2] - rgb[0]) / delta) + 2 : ((rgb[0] - rgb[1]) / delta) + 4;
            hue = Math.round(hue * 60); if (hue < 0) hue += 360;
        }
        return [hue, Math.round(saturation * 100), Math.round(lightness * 100)];
    };
    const hslToHex = (hsl) => {
        const [hue, saturation, lightness] = [hsl[0] / 360, hsl[1] / 100, hsl[2] / 100];
        const chroma = (1 - Math.abs((2 * lightness) - 1)) * saturation;
        const part = chroma * (1 - Math.abs(((hue * 6) % 2) - 1));
        const match = hue < 1 / 6 ? [chroma, part, 0] : hue < 2 / 6 ? [part, chroma, 0] : hue < 3 / 6 ? [0, chroma, part] : hue < 4 / 6 ? [0, part, chroma] : hue < 5 / 6 ? [part, 0, chroma] : [chroma, 0, part];
        const adjustment = lightness - (chroma / 2);
        return rgbToHex(match.map((channel) => (channel + adjustment) * 255));
    };
    const parseColor = (format, value) => {
        const text = value.trim();
        if (format === 'hex') return /^#[0-9a-f]{6}$/i.test(text) ? text.toLowerCase() : null;
        const numbers = text.replace(/^(rgb|hsl)\(|\)$/gi, '').split(/[,\s]+/).filter(Boolean);
        if (numbers.length !== 3 || numbers.some((number) => !/^-?\d+(?:\.\d+)?%?$/.test(number))) return null;
        if (format === 'rgb') {
            const rgb = numbers.map((number) => Number(number.replace('%', '')) * (number.endsWith('%') ? 2.55 : 1));
            return rgb.every((channel) => channel >= 0 && channel <= 255) ? rgbToHex(rgb) : null;
        }
        const hsl = [Number(numbers[0]), Number(numbers[1].replace('%', '')), Number(numbers[2].replace('%', ''))];
        return hsl[0] >= 0 && hsl[0] <= 360 && hsl[1] >= 0 && hsl[1] <= 100 && hsl[2] >= 0 && hsl[2] <= 100 ? hslToHex(hsl) : null;
    };
    const displayColor = (format, hex) => {
        if (format === 'hex') return hex;
        if (format === 'rgb') return 'rgb(' + hexToRgb(hex).join(', ') + ')';
        const hsl = hexToHsl(hex); return 'hsl(' + hsl[0] + ', ' + hsl[1] + '%, ' + hsl[2] + '%)';
    };
    colorControls.forEach((control) => {
        const native = control.querySelector('[data-color-native]');
        const format = control.querySelector('[data-color-format]');
        const input = control.querySelector('[data-color-input]');
        const canonical = control.querySelector('[data-color-canonical]');
        const syncDisplay = () => { input.value = displayColor(format.value, canonical.value); input.removeAttribute('aria-invalid'); native.value = canonical.value; };
        format.addEventListener('change', syncDisplay);
        native.addEventListener('input', () => { canonical.value = native.value.toLowerCase(); syncDisplay(); });
        input.addEventListener('input', () => { const parsed = parseColor(format.value, input.value); if (parsed === null) { input.setAttribute('aria-invalid', 'true'); return; } canonical.value = parsed; native.value = parsed; input.removeAttribute('aria-invalid'); });
        syncDisplay();
    });

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
