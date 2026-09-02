(function () {
    document.querySelectorAll('.site-nav-group-toggle').forEach(function (button) {
        button.addEventListener('click', function () {
            var item = button.closest('.site-nav-item');
            if (!item) return;
            var open = item.classList.toggle('is-open');
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    });
}());
