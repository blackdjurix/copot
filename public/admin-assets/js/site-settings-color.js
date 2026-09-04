(() => {
    'use strict';

    const picker = document.querySelector('[data-main-color-picker]');
    const hex = document.querySelector('[data-main-color-hex]');
    if (!picker || !hex) return;

    const valid = (value) => /^#[0-9a-f]{6}$/i.test(value.trim());
    const syncHex = () => {
        hex.value = picker.value;
        hex.setCustomValidity('');
    };

    picker.addEventListener('input', syncHex);
    hex.addEventListener('input', () => {
        const value = hex.value.trim();
        if (!valid(value)) {
            hex.setCustomValidity('Enter a 6-digit HEX color, for example #1769e0.');
            return;
        }
        hex.setCustomValidity('');
        picker.value = value.toLowerCase();
    });
    syncHex();
})();
