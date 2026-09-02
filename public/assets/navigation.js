document.querySelectorAll('.builtin-site-nav__trigger').forEach(function (trigger) {
    trigger.addEventListener('click', function () {
        var item = trigger.closest('.builtin-site-nav__item');
        var open = trigger.getAttribute('aria-expanded') === 'true';
        trigger.setAttribute('aria-expanded', open ? 'false' : 'true');
        item.classList.toggle('is-open', !open);
    });
});
