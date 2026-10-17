/* Nfelia — public pages. Small and dependency-free. */
(function () {
    'use strict';

    // Header gets a hairline once the page scrolls.
    var header = document.querySelector('[data-header]');
    if (header) {
        var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 8); };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    // Floating WhatsApp menu.
    var wa = document.querySelector('[data-wa]');
    if (wa) {
        var toggle = wa.querySelector('[data-wa-toggle]');
        var panel = wa.querySelector('#wa-panel');
        var closeBtn = wa.querySelector('[data-wa-close]');
        var nudge = wa.querySelector('[data-wa-nudge]');

        var setOpen = function (open, restoreFocus) {
            panel.hidden = !open;
            if (open && nudge) nudge.hidden = true;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) {
                var first = panel.querySelector('a');
                if (first) first.focus();
            } else if (restoreFocus) {
                toggle.focus();
            }
        };

        var seen = false;
        try { seen = sessionStorage.getItem('nf_wa_nudge') === '1'; } catch (e) { seen = true; }
        if (nudge && !seen) {
            setTimeout(function () {
                if (!panel.hidden) return;
                nudge.hidden = false;
                try { sessionStorage.setItem('nf_wa_nudge', '1'); } catch (e) {}
                setTimeout(function () { nudge.hidden = true; }, 7000);
            }, 9000);
        }

        toggle.addEventListener('click', function () { setOpen(panel.hidden, false); });
        closeBtn.addEventListener('click', function () { setOpen(false, true); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !panel.hidden) setOpen(false, true);
        });
        document.addEventListener('click', function (e) {
            if (!panel.hidden && !wa.contains(e.target)) setOpen(false, false);
        });
        // Picking a message opens WhatsApp in a new tab; close the menu behind it.
        panel.querySelectorAll('a').forEach(function (a) {
            a.addEventListener('click', function () { setOpen(false, false); });
        });
    }

    // Reveal sections as they scroll into view. Groups stagger their
    // children by 30ms (150ms at most), so a list arrives in order.
    var revealables = document.querySelectorAll('[data-reveal], [data-reveal-group]');
    document.querySelectorAll('[data-reveal-group]').forEach(function (group) {
        Array.prototype.forEach.call(group.children, function (child, i) {
            child.style.setProperty('--delay', Math.min(i, 5) * 30 + 'ms');
        });
    });
    if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-in');
                    io.unobserve(entry.target);
                }
            });
        }, { rootMargin: '0px 0px -10% 0px', threshold: 0.12 });
        revealables.forEach(function (el) { io.observe(el); });
    } else {
        revealables.forEach(function (el) { el.classList.add('is-in'); });
    }

    // Terms / Privacy / Feedback dialogs.
    document.querySelectorAll('[data-dialog]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var dlg = document.getElementById(btn.getAttribute('data-dialog'));
            if (dlg && typeof dlg.showModal === 'function') dlg.showModal();
        });
    });
    document.querySelectorAll('dialog.sheet').forEach(function (dlg) {
        dlg.querySelectorAll('[data-close]').forEach(function (b) {
            b.addEventListener('click', function () { dlg.close(); });
        });
        // A click on the backdrop closes the dialog.
        dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close(); });
    });

    // Forms: check a field when the person leaves it, clear the message as
    // soon as they start fixing it, and on submit show every problem inline
    // instead of the browser's bubbles. The server still validates too.
    document.querySelectorAll('form[data-validate]').forEach(function (form) {
        form.noValidate = true;
        var fields = form.querySelectorAll('input[required], textarea[required], input[data-match]');

        var problem = function (field) {
            if (field.dataset.match) {
                var other = form.querySelector('[name="' + field.dataset.match + '"]');
                if (field.value === '' || (other && field.value !== other.value)) return field.dataset.error;
                return '';
            }
            return field.checkValidity() ? '' : (field.dataset.error || 'Fill in this field.');
        };
        var errorEl = function (field) {
            var id = field.id + '-error';
            var el = document.getElementById(id);
            if (!el) {
                el = document.createElement('span');
                el.className = 'error'; el.id = id; el.hidden = true;
                field.insertAdjacentElement('afterend', el);
                var described = (field.getAttribute('aria-describedby') || '').split(' ').filter(Boolean);
                described.push(id);
                field.setAttribute('aria-describedby', described.join(' '));
            }
            return el;
        };
        var show = function (field) {
            var msg = problem(field);
            var el = errorEl(field);
            el.textContent = msg; el.hidden = msg === '';
            field.setAttribute('aria-invalid', msg === '' ? 'false' : 'true');
            return msg === '';
        };
        var clear = function (field) {
            var el = document.getElementById(field.id + '-error');
            if (el) el.hidden = true;
            field.removeAttribute('aria-invalid');
        };

        fields.forEach(function (field) {
            field.addEventListener('blur', function () { show(field); });
            field.addEventListener('input', function () { clear(field); });
        });
        form.addEventListener('submit', function (e) {
            var first = null;
            fields.forEach(function (field) { if (!show(field) && !first) first = field; });
            if (first) { e.preventDefault(); first.focus(); }
        });
    });

    // Setup video: load YouTube only when someone presses play, to save data.
    document.querySelectorAll('[data-video]').forEach(function (box) {
        var btn = box.querySelector('.video-play');
        if (!btn) return;
        btn.addEventListener('click', function () {
            var iframe = document.createElement('iframe');
            iframe.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(box.getAttribute('data-video')) + '?autoplay=1&rel=0';
            iframe.title = 'Nfelia setup video';
            iframe.allow = 'autoplay; encrypted-media; picture-in-picture';
            iframe.allowFullscreen = true;
            box.replaceChildren(iframe);
        });
    });
})();
