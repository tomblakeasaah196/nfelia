/*
 * Nfelia — Settings › Branding (views/settings/index.php)
 *
 * The two font pickers, the two brand colours, "Take colours from my logo"
 * and the little preview above them. The fonts come from the library in
 * app/helpers/brand.php, sent as #nf-brand-data. Choices go into the
 * ordinary business form and are saved with it.
 *
 * A picker is a button that opens a search box and a list. Each font's name
 * is drawn in the font itself: one stylesheet, cut down to the letters of
 * the names, is fetched the first time a picker opens.
 */
(function () {
    'use strict';

    var card = document.querySelector('[data-brand]');
    var dataEl = document.getElementById('nf-brand-data');
    if (!card || !dataEl) { return; }
    var D = JSON.parse(dataEl.textContent);
    var $ = function (sel, el) { return (el || document).querySelector(sel); };
    var $$ = function (sel, el) { return Array.prototype.slice.call((el || document).querySelectorAll(sel)); };
    var byKey = {};
    D.fonts.forEach(function (f) { byKey[f.key] = f; });

    function el(tag, attrs, text) {
        var node = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (k) {
            if (attrs[k] === null || attrs[k] === undefined || attrs[k] === false) { return; }
            if (k === 'class') { node.className = attrs[k]; } else { node.setAttribute(k, attrs[k] === true ? '' : attrs[k]); }
        });
        if (text !== undefined && text !== null) { node.textContent = text; }
        return node;
    }

    /* ── Colours ─────────────────────────────────── */

    function hex(v) {
        if (typeof v !== 'string') { return null; }
        var h = v.trim().replace(/^#/, '');
        if (/^[0-9a-f]{3}$/i.test(h)) { h = h.split('').map(function (c) { return c + c; }).join(''); }
        return /^[0-9a-f]{6}$/i.test(h) ? '#' + h.toUpperCase() : null;
    }
    function rgb(h) { var n = parseInt(h.slice(1), 16); return [(n >> 16) & 255, (n >> 8) & 255, n & 255]; }
    function mix(a, b, t) {
        var A = rgb(a), B = rgb(b);
        return '#' + A.map(function (c, i) { return ('0' + Math.round(c + (B[i] - c) * t).toString(16)).slice(-2); }).join('').toUpperCase();
    }
    function luminance(h) {
        var w = [0.2126, 0.7152, 0.0722];
        return rgb(h).reduce(function (sum, c, i) {
            c /= 255;
            return sum + w[i] * (c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4));
        }, 0);
    }
    function contrast(a, b) {
        var la = luminance(a), lb = luminance(b);
        return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
    }

    var colours = {};
    ['primary', 'secondary'].forEach(function (which) {
        var pick = $('[data-colour-pick="' + which + '"]', card);
        var text = $('[data-colour-hex="' + which + '"]', card);
        colours[which] = hex(text.value) || '#1A1A2E';
        pick.addEventListener('input', function () { setColour(which, pick.value, 'pick'); });
        text.addEventListener('input', function () {
            var v = hex(text.value);
            if (v && text.value.trim().length >= 4) { setColour(which, v, 'text'); }
        });
        text.addEventListener('blur', function () { text.value = colours[which]; });
    });
    function setColour(which, value, from) {
        var v = hex(value);
        if (!v) { return; }
        colours[which] = v;
        if (from !== 'pick') { $('[data-colour-pick="' + which + '"]', card).value = v.toLowerCase(); }
        if (from !== 'text') { $('[data-colour-hex="' + which + '"]', card).value = v; }
        paintPreview();
    }

    /* ── Preview ─────────────────────────────────── */

    var preview = $('[data-brand-preview]', card);
    var previewFonts = $('[data-bp-fonts]', card);
    // Until the owner picks, the preview shows the fonts their links use today
    var chosen = { head: D.current.head || D.today.head, body: D.current.body || D.today.body };

    // As brand_colour_set() in app/helpers/brand.php: the secondary colour is
    // the dark one when it is dark enough, else a deep shade of the primary
    function paintPreview() {
        var p = colours.primary, s = colours.secondary;
        var dark = luminance(s) < 0.05 ? s : mix(p, '#000000', 0.82);
        var onP = contrast('#FFFFFF', p) >= contrast(dark, p) ? '#FFFFFF' : dark;
        preview.style.setProperty('--bp-primary', p);
        preview.style.setProperty('--bp-on', onP);
        preview.style.setProperty('--bp-dark', dark);
        preview.style.setProperty('--bp-light', mix(p, '#FFFFFF', 0.94));
        preview.style.setProperty('--bp-text', contrast(p, '#FFFFFF') >= 4.5 ? p : mix(p, '#000000', 0.35));
        var head = byKey[chosen.head], body = byKey[chosen.body];
        if (head) {
            preview.style.setProperty('--bp-head', head.stack);
            preview.style.setProperty('--bp-head-weight', head.head);
        }
        if (body) { preview.style.setProperty('--bp-body', body.stack); }
    }
    function loadPreviewFonts() {
        var parts = [];
        var head = byKey[chosen.head], body = byKey[chosen.body];
        if (head) { parts.push(head.q.head); }
        if (body && body.q.body) { parts.push(body.q.body); }
        if (parts.length) { previewFonts.href = 'https://fonts.googleapis.com/css2?family=' + parts.join('&family=') + '&display=swap'; }
    }
    paintPreview();

    /* ── Font pickers ────────────────────────────── */

    var samplesLoaded = false;
    function loadSamples() {
        if (samplesLoaded) { return; }
        samplesLoaded = true;
        document.head.appendChild(el('link', { rel: 'stylesheet', href: D.samples }));
    }
    var KINDS = ['All', 'Serif', 'Sans serif', 'Display', 'Script', 'Monospace'];
    var openPicker = null;

    $$('[data-font-picker]', card).forEach(function (box) {
        var role = box.dataset.fontPicker;
        var trigger = $('[data-font-trigger]', box);
        var pop = $('[data-font-pop]', box);
        var search = $('[data-font-search]', box);
        var list = $('[data-font-list]', box);
        var none = $('[data-font-none]', box);
        var kindsEl = $('[data-font-kinds]', box);
        var valueInput = $('[data-font-value]', box);
        var nameEl = $('[data-font-name]', box);
        var fonts = D.fonts.filter(function (f) { return role === 'head' ? f.head !== null : f.body; });
        var kind = 'All';
        var active = -1;
        var shown = [];

        search.placeholder = 'Search ' + fonts.length + ' fonts';
        KINDS.filter(function (k) { return k === 'All' || fonts.some(function (f) { return f.kind === k; }); }).forEach(function (k) {
            var b = el('button', { type: 'button', class: 'nf-font__kind', 'aria-pressed': String(k === 'All') }, k);
            b.addEventListener('click', function () {
                kind = k;
                $$('.nf-font__kind', kindsEl).forEach(function (o) { o.setAttribute('aria-pressed', String(o === b)); });
                draw();
                search.focus();
            });
            kindsEl.appendChild(b);
        });

        function draw() {
            var q = search.value.trim().toLowerCase();
            list.textContent = '';
            shown = fonts.filter(function (f) {
                return (kind === 'All' || f.kind === kind) && (!q || f.name.toLowerCase().indexOf(q) !== -1);
            });
            shown.forEach(function (f, i) {
                var selected = valueInput.value === f.key;
                var li = el('li', { role: 'option', id: 'nf-font-' + role + '-' + f.key, class: 'nf-font__option', 'aria-selected': String(selected), 'data-key': f.key });
                var name = el('span', { class: 'nf-font__sample' }, f.name);
                name.style.fontFamily = f.stack;
                if (role === 'head') { name.style.fontWeight = f.head; }
                name.style.fontSize = Math.round(19 * Math.min(1.25, Math.max(0.8, f.scale + 0.12))) + 'px';
                li.appendChild(name);
                li.appendChild(el('span', { class: 'nf-font__kindname' }, selected ? 'Chosen' : f.kind));
                li.addEventListener('mousedown', function (e) { e.preventDefault(); });   // keep focus in the search box
                li.addEventListener('click', function () { choose(f); });
                li.addEventListener('mousemove', function () { if (active !== i) { setActive(i, false); } });
                list.appendChild(li);
            });
            none.hidden = shown.length > 0;
            setActive(shown.length ? Math.max(0, shown.findIndex(function (f) { return f.key === valueInput.value; })) : -1, true);
        }
        function setActive(i, scroll) {
            active = i;
            $$('.nf-font__option', list).forEach(function (o, j) { o.classList.toggle('is-active', j === i); });
            var node = list.children[i];
            if (node) {
                search.setAttribute('aria-activedescendant', node.id);
                if (scroll) { node.scrollIntoView({ block: 'nearest' }); }
            } else {
                search.removeAttribute('aria-activedescendant');
            }
        }
        function choose(f) {
            valueInput.value = f.key;
            nameEl.textContent = f.name;
            nameEl.style.fontFamily = f.stack;
            nameEl.style.fontWeight = role === 'head' ? f.head : '';
            nameEl.classList.remove('is-empty');
            var help = nameEl.closest('.nf-font').querySelector('.nf-help');
            if (help) { help.hidden = true; }
            chosen[role] = f.key;
            loadPreviewFonts();
            paintPreview();
            close(true);
        }
        function open() {
            if (openPicker && openPicker !== api) { openPicker.close(false); }
            loadSamples();
            pop.hidden = false;
            box.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            search.value = '';
            kind = 'All';
            $$('.nf-font__kind', kindsEl).forEach(function (o) { o.setAttribute('aria-pressed', String(o.textContent === 'All')); });
            draw();
            openPicker = api;
            search.focus();
            // On a phone, bring the whole list into view
            var box2 = pop.getBoundingClientRect();
            if (box2.bottom > window.innerHeight) { pop.scrollIntoView({ block: 'end', behavior: 'smooth' }); }
        }
        function close(refocus) {
            pop.hidden = true;
            box.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
            if (openPicker === api) { openPicker = null; }
            if (refocus) { trigger.focus(); }
        }
        var api = { close: close, box: box };

        trigger.addEventListener('click', function () { if (pop.hidden) { open(); } else { close(true); } });
        trigger.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); open(); }
        });
        search.addEventListener('input', draw);
        search.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') { e.preventDefault(); if (shown.length) { setActive(Math.min(shown.length - 1, active + 1), true); } }
            else if (e.key === 'ArrowUp') { e.preventDefault(); if (shown.length) { setActive(Math.max(0, active - 1), true); } }
            else if (e.key === 'Home' && !search.value) { e.preventDefault(); setActive(0, true); }
            else if (e.key === 'End' && !search.value) { e.preventDefault(); setActive(shown.length - 1, true); }
            else if (e.key === 'Enter') { e.preventDefault(); if (shown[active]) { choose(shown[active]); } }
            else if (e.key === 'Escape') { e.preventDefault(); close(true); }
            else if (e.key === 'Tab') { close(false); }
        });
    });

    document.addEventListener('click', function (e) {
        if (openPicker && !openPicker.box.contains(e.target)) { openPicker.close(false); }
    });

    /* ── Colours from the logo ───────────────────── */
    // The colours the logo is mostly made of, strongest first: the same
    // reading as Shop Studio's (assets/js/studio.js), keeping up to five.

    var logoButton = $('[data-logo-colours]', card);
    var logoHelp = $('[data-logo-help]', card);
    var picks = $('[data-logo-picks]', card);
    var logoSrc = D.logo;

    // A logo just chosen in the form above, before it is saved
    var logoInput = document.querySelector('input[type="file"][name="logo"]');
    if (logoInput) {
        logoInput.addEventListener('change', function () {
            var file = logoInput.files && logoInput.files[0];
            if (!file) { return; }
            logoSrc = URL.createObjectURL(file);
            logoButton.disabled = false;
            logoHelp.textContent = 'We read your logo and fill both colours. You can still change them.';
        });
    }

    function readColours(img) {
        var size = 64;
        var canvas = document.createElement('canvas');
        canvas.width = size;
        canvas.height = size;
        var ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, size, size);
        var data = ctx.getImageData(0, 0, size, size).data;
        var buckets = {};
        for (var i = 0; i < data.length; i += 4) {
            if (data[i + 3] < 200) { continue; }
            var r = data[i], g = data[i + 1], b = data[i + 2];
            var max = Math.max(r, g, b), min = Math.min(r, g, b);
            if (max > 240 && min > 225) { continue; }   // a white background
            var key = (r >> 4) + ',' + (g >> 4) + ',' + (b >> 4);
            var bucket = buckets[key] || (buckets[key] = { n: 0, r: 0, g: 0, b: 0, sat: max - min, light: (max + min) / 2 });
            bucket.n++; bucket.r += r; bucket.g += g; bucket.b += b;
        }
        var list = Object.keys(buckets).map(function (k) {
            var x = buckets[k];
            var colour = '#' + [x.r / x.n, x.g / x.n, x.b / x.n].map(function (c) { return ('0' + Math.round(c).toString(16)).slice(-2); }).join('').toUpperCase();
            return { colour: colour, n: x.n, score: x.n * (0.4 + x.sat / 255) };
        }).sort(function (a, b) { return b.score - a.score; });
        // Keep colours that look different from the ones already kept
        var kept = [];
        list.forEach(function (x) {
            if (kept.length < 5 && kept.every(function (k) { return contrast(k, x.colour) > 1.35; })) { kept.push(x.colour); }
        });
        return kept;
    }
    function swatch(colour, which) {
        var b = el('button', { type: 'button', class: 'nf-swatch', 'aria-label': 'Use ' + colour + ' as the ' + which + ' colour', 'aria-pressed': String(colours[which] === colour) });
        b.style.background = colour;
        b.addEventListener('click', function () {
            setColour(which, colour);
            $$('.nf-swatch', b.parentNode).forEach(function (o) { o.setAttribute('aria-pressed', String(o === b)); });
        });
        return b;
    }
    if (logoButton) {
        logoButton.addEventListener('click', function () {
            if (!logoSrc) { return; }
            logoButton.disabled = true;
            var img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = function () {
                logoButton.disabled = false;
                var found = [];
                try { found = readColours(img); } catch (e) { found = []; }
                if (!found.length) {
                    logoHelp.textContent = 'We couldn\'t read colours from this logo. Pick them with the squares above.';
                    return;
                }
                // The primary is the strongest colour; the secondary the darkest
                // one that stands apart from it, or a deep shade of the primary
                var primary = found[0];
                var darks = found.slice(1).filter(function (c) { return luminance(c) < 0.08 && contrast(c, primary) > 2; });
                var secondary = darks[0] || mix(primary, '#000000', 0.82);
                var forSecondary = found.slice(1).concat(found.indexOf(secondary) === -1 ? [secondary] : []);
                setColour('primary', primary);
                setColour('secondary', secondary);
                ['primary', 'secondary'].forEach(function (which) {
                    var row = $('[data-logo-for="' + which + '"]', picks);
                    row.textContent = '';
                    (which === 'primary' ? found : forSecondary).forEach(function (c) { row.appendChild(swatch(c, which)); });
                });
                picks.hidden = false;
                logoHelp.textContent = 'Filled from your logo. Tap another colour to use it instead, then save.';
            };
            img.onerror = function () {
                logoButton.disabled = false;
                logoHelp.textContent = 'We couldn\'t open your logo just now. Try again.';
            };
            img.src = logoSrc;
        });
    }
})();
