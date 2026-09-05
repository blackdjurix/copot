document.querySelectorAll('.builtin-site-nav__trigger').forEach(function (trigger) {
    trigger.addEventListener('click', function () {
        var item = trigger.closest('.builtin-site-nav__item');
        var open = trigger.getAttribute('aria-expanded') === 'true';
        trigger.setAttribute('aria-expanded', open ? 'false' : 'true');
        item.classList.toggle('is-open', !open);
    });
});

document.querySelectorAll('.builtin-site-nav__toggle').forEach(function (toggle) {
    var navigation = document.getElementById(toggle.getAttribute('aria-controls'));
    if (!navigation) return;
    toggle.addEventListener('click', function () {
        var open = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
        toggle.setAttribute('aria-label', open ? 'Open menu' : 'Close menu');
        navigation.classList.toggle('is-open', !open);
    });
});
