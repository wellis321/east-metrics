(function () {
    var sidebar = document.querySelector('.docs-sidebar');
    if (!sidebar || typeof IntersectionObserver === 'undefined') {
        return;
    }

    var links = Array.prototype.slice.call(sidebar.querySelectorAll('a[href^="#"]'));
    var linkBySectionId = {};
    var sections = [];

    links.forEach(function (link) {
        var id = link.getAttribute('href').slice(1);
        var section = document.getElementById(id);
        if (section) {
            linkBySectionId[id] = link;
            sections.push(section);
        }
    });

    if (sections.length === 0) {
        return;
    }

    var setCurrent = function (id) {
        links.forEach(function (link) { link.classList.remove('is-current'); });
        if (linkBySectionId[id]) {
            linkBySectionId[id].classList.add('is-current');
        }
    };

    // Treats a section as "current" once it crosses a band near the top of
    // the viewport, so the sidebar highlight tracks scroll position rather
    // than only updating when a section is fully in view.
    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                setCurrent(entry.target.id);
            }
        });
    }, { rootMargin: '-15% 0px -70% 0px', threshold: 0 });

    sections.forEach(function (section) { observer.observe(section); });
})();
