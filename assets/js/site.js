/* Nfelia — the site's own pages (views/site). Small on purpose: every page
   works without it. It only adds the header's line on scroll, lets wide tables
   be scrolled from the keyboard, and closes the phone menu when you tap away
   or press Escape. */
(function () {
    var header = document.querySelector('[data-header]');
    if (header) {
        var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 8); };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    // A table wider than its box can be scrolled from the keyboard too, and is
    // named by its caption for screen readers
    var tables = document.querySelectorAll('.table-scroll');
    var markScroll = function () {
        for (var i = 0; i < tables.length; i++) {
            var box = tables[i], wide = box.scrollWidth > box.clientWidth + 1;
            if (wide && !box.hasAttribute('tabindex')) {
                // Named by its caption, else by the heading it sits under
                var caption = box.querySelector('caption'), label = caption ? caption.textContent.trim() : '';
                for (var el = box.previousElementSibling; !label && el; el = el.previousElementSibling) {
                    if (/^H[2-4]$/.test(el.tagName)) label = el.textContent.trim();
                }
                box.setAttribute('tabindex', '0');
                box.setAttribute('role', 'region');
                box.setAttribute('aria-label', label || 'Table');
            } else if (!wide && box.getAttribute('tabindex') === '0') {
                box.removeAttribute('tabindex');
                box.removeAttribute('role');
                box.removeAttribute('aria-label');
            }
        }
    };
    if (tables.length) {
        markScroll();
        window.addEventListener('resize', markScroll, { passive: true });
    }

    var menu = document.querySelector('.nav-menu');
    if (menu) {
        document.addEventListener('click', function (e) {
            if (menu.open && !menu.contains(e.target)) menu.open = false;
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && menu.open) {
                menu.open = false;
                menu.querySelector('summary').focus();
            }
        });
    }
})();
