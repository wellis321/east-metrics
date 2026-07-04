document.querySelectorAll('[data-dropdown]').forEach(function (wrap) {
    var toggle = wrap.querySelector('[data-dropdown-toggle]');
    var menu = wrap.querySelector('[data-dropdown-menu]');
    if (!toggle || !menu) {
        return;
    }
    toggle.addEventListener('click', function (e) {
        e.stopPropagation();
        menu.classList.toggle('is-open');
    });
    document.addEventListener('click', function (e) {
        if (!wrap.contains(e.target)) {
            menu.classList.remove('is-open');
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            menu.classList.remove('is-open');
        }
    });
});
