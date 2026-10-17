/*
 * Nfelia — Shop Studio (views/studio/index.php)
 *
 * The owner edits a copy of the shop's settings here. Every change is saved
 * to the draft a moment later (POST save-draft); nothing reaches shoppers
 * until Publish. Colour and font changes show at once: the preview is told
 * the new CSS custom properties by postMessage, worked out with the same
 * sums as app/helpers/storefront.php (the server's answer wins when it
 * arrives). Changes to sections reload the preview once the draft is saved.
 *
 * Undo and redo keep the last sixty changes made since the page opened.
 */
(function () {
    'use strict';

    var D = JSON.parse(document.getElementById('st-data').textContent);
    var $ = function (sel, el) { return (el || document).querySelector(sel); };
    var $$ = function (sel, el) { return Array.prototype.slice.call((el || document).querySelectorAll(sel)); };
    var BASE = D.urls.preview.replace(/\/landing-page\/preview$/, '');

    var state = D.settings;
    var unpublished = D.unpublished;
    var frame = $('[data-frame]');
    var statusEl = $('[data-status]');
    var publishBtn = $('[data-publish]');

    function el(tag, attrs, text) {
        var node = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (k) {
            if (attrs[k] === false || attrs[k] === null || attrs[k] === undefined) { return; }
            if (k === 'class') { node.className = attrs[k]; } else { node.setAttribute(k, attrs[k] === true ? '' : attrs[k]); }
        });
        if (text !== undefined && text !== null) { node.textContent = text; }
        return node;
    }
    function icon(paths, size) {
        var span = document.createElement('span');
        span.innerHTML = '<svg width="' + (size || 20) + '" height="' + (size || 20) + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + paths + '</svg>';
        return span.firstChild;
    }
    var ICON = {
        up: '<path d="M18 15l-6-6-6 6"/>',
        down: '<path d="M6 9l6 6 6-6"/>',
        back: '<path d="M15 18l-6-6 6-6"/>',
        grip: '<circle cx="9" cy="6" r="1.4" fill="currentColor"/><circle cx="15" cy="6" r="1.4" fill="currentColor"/><circle cx="9" cy="12" r="1.4" fill="currentColor"/><circle cx="15" cy="12" r="1.4" fill="currentColor"/><circle cx="9" cy="18" r="1.4" fill="currentColor"/><circle cx="15" cy="18" r="1.4" fill="currentColor"/>',
        check: '<path d="M5 12.5l4.5 4.5L19 7.5"/>'
    };

    var toastEl = $('[data-toast]');
    var toastTimer = null;
    function toast(text) {
        toastEl.textContent = text;
        toastEl.classList.add('is-on');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { toastEl.classList.remove('is-on'); }, 2800);
    }
    function say(text, bad) {
        statusEl.textContent = text;
        statusEl.classList.toggle('is-error', !!bad);
    }

    /* ── Colours: the same sums as app/helpers/storefront.php ── */

    function hex(v) {
        if (typeof v !== 'string') { return null; }
        var h = v.trim().replace(/^#/, '');
        if (/^[0-9a-f]{3}$/i.test(h)) { h = h.split('').map(function (c) { return c + c; }).join(''); }
        return /^[0-9a-f]{6}$/i.test(h) ? '#' + h.toUpperCase() : null;
    }
    function rgb(h) { var n = parseInt(h.slice(1), 16); return [(n >> 16) & 255, (n >> 8) & 255, n & 255]; }
    function mix(a, b, t) {
        var A = rgb(a), B = rgb(b);
        return '#' + A.map(function (c, i) {
            return ('0' + Math.round(c + (B[i] - c) * t).toString(16)).slice(-2);
        }).join('').toUpperCase();
    }
    function luminance(h) {
        var w = [0.2126, 0.7152, 0.0722];
        return rgb(h).reduce(function (sum, c, i) {
            c /= 255;
            c = c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
            return sum + w[i] * c;
        }, 0);
    }
    function contrast(a, b) {
        var la = luminance(a), lb = luminance(b);
        return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
    }
    function readable(fg, bg, min) {
        min = min || 4.5;
        if (contrast(fg, bg) >= min) { return fg; }
        var target = contrast('#000000', bg) >= contrast('#FFFFFF', bg) ? '#000000' : '#FFFFFF';
        for (var i = 1; i <= 25; i++) {
            var t = mix(fg, target, i / 25);
            if (contrast(t, bg) >= min) { return t; }
        }
        return target;
    }
    function accentPair(accent, ground, ink, on) {
        var darkText = luminance(ink) < luminance(ground) ? ink : ground;
        on = hex(on) || (luminance(ground) < 0.2 ? darkText : '#FFFFFF');
        if (contrast(on, accent) >= 4.5) { return [accent, on]; }
        var other = on === darkText ? '#FFFFFF' : darkText;
        if (luminance(accent) > 0.4 && contrast(other, accent) >= 4.5) { return [accent, other]; }
        var target = luminance(on) > luminance(accent) ? '#000000' : '#FFFFFF';
        for (var i = 1; i <= 25; i++) {
            var t = mix(accent, target, i / 25);
            if (contrast(on, t) >= 4.5) { return [t, on]; }
        }
        return [target, on];
    }
    function palette(set, band) {
        var ground = hex(set.ground) || '#FFFFFF';
        var dark = contrast('#FFFFFF', ground) > contrast('#000000', ground);
        var ink = readable(hex(set.ink) || (dark ? '#F5F5F5' : '#1A1A1A'), ground, 7.0);
        var surface = hex(set.surface) || (dark ? mix(ground, '#FFFFFF', 0.07) : mix(ground, ink, 0.045));
        var card = hex(set.card) || (dark ? surface : (luminance(ground) > 0.96 ? surface : '#FFFFFF'));
        var pair = accentPair(readable(hex(set.accent) || ink, ground, 1.5), ground, ink, set.on);
        var accent = pair[0], on = pair[1];
        var muted = readable(readable(hex(set.muted) || mix(ink, ground, 0.32), ground), card);
        var bandC = hex(band) || mix(accent, ground, 0.78);
        var bandInk = contrast(ink, bandC) >= contrast('#FFFFFF', bandC) ? ink : '#FFFFFF';
        if (contrast(bandInk, bandC) < 4.5) {
            bandInk = contrast('#000000', bandC) >= contrast('#FFFFFF', bandC) ? '#000000' : '#FFFFFF';
        }
        var line = hex(set.line) || mix(ground, ink, 0.13);
        return {
            ground: ground, ink: readable(ink, card), muted: muted, accent: accent, on: on,
            accent_text: readable(readable(accent, ground), card), line: line, field: readable(line, ground, 3.0),
            surface: surface, card: card,
            motif: hex(set.motif) || (on === '#FFFFFF' ? mix(accent, '#FFFFFF', 0.8) : mix(accent, on, 0.6)),
            band: bandC, band_ink: bandInk, tint: mix(accent, ground, 0.87), dark: dark
        };
    }
    function look(key) { return D.looks.filter(function (l) { return l.key === key; })[0] || D.looks[0]; }
    function colourSet(s) {
        var lk = look(s.look);
        var c = s.colours;
        var chosen = function (k) { return k === 'look' || !D.sets[k] ? lk.colours : D.sets[k]; };
        if (c.set === 'logo') { return { ground: c.ground, ink: c.ink, accent: c.accent }; }
        if (c.set === 'custom') {
            var base = Object.assign({}, chosen(c.base || 'look'));
            base.accent = c.accent;
            delete base.on;
            delete base.motif;
            return base;
        }
        return chosen(c.set);
    }
    function pairKey(s) { return s.font || look(s.look).pair; }
    function themeFor(s) {
        var lk = look(s.look);
        var p = palette(colourSet(s), lk.band);
        var f = D.fonts[pairKey(s)];
        return {
            vars: {
                '--sf-ground': p.ground, '--sf-ink': p.ink, '--sf-muted': p.muted, '--sf-accent': p.accent, '--sf-on': p.on,
                '--sf-accent-text': p.accent_text, '--sf-line': p.line, '--sf-field': p.field, '--sf-surface': p.surface,
                '--sf-card': p.card, '--sf-motif': p.motif, '--sf-band': p.band, '--sf-band-ink': p.band_ink, '--sf-tint': p.tint,
                '--sf-head': f.head_stack, '--sf-body': f.body_stack, '--sf-head-weight': String(f.weight),
                '--sf-head-stretch': f.stretch, '--sf-scale': String(f.scale)
            },
            dark: p.dark, pair: pairKey(s), fonts_url: f.url, accent: p.accent
        };
    }
    function postTheme(theme) {
        if (!frame.contentWindow) { return; }
        frame.contentWindow.postMessage({ type: 'nfelia:theme', vars: theme.vars, dark: theme.dark, pair: theme.pair, fontsHref: theme.fonts_url }, location.origin);
    }

    /* ── Changes, history, saving ────────────────── */

    var past = [];
    var future = [];
    var lastKey = null;
    var lastAt = 0;
    var undoBtn = $('[data-undo]');
    var redoBtn = $('[data-redo]');

    function snapshot() { return JSON.stringify(state); }
    function updateHistoryButtons() {
        undoBtn.disabled = !past.length;
        redoBtn.disabled = !future.length;
    }
    /*
     * Make a change. opts:
     *   key         typing in one field counts as one step for undo
     *   theme       colours or fonts: repaint the preview now
     *   reload      the preview needs the saved draft (sections, words)
     *   scrollTo    a section to show in the preview afterwards
     */
    function commit(mutate, opts) {
        opts = opts || {};
        var before = snapshot();
        mutate(state);
        if (snapshot() === before) { return; }
        var now = Date.now();
        if (!(opts.key && opts.key === lastKey && now - lastAt < 2500)) {
            past.push(before);
            if (past.length > 60) { past.shift(); }
        }
        future = [];
        lastKey = opts.key || null;
        lastAt = now;
        changed(opts);
    }
    function changed(opts) {
        unpublished = true;
        updatePublish();
        updateHistoryButtons();
        if (opts.theme) { postTheme(themeFor(state)); }
        if (opts.reload) { wantPreview(opts.scrollTo || null); }
        scheduleSave();
    }
    function undoRedo(from, to) {
        if (!from.length) { return; }
        to.push(snapshot());
        state = JSON.parse(from.pop());
        lastKey = null;
        renderAll();
        changed({ theme: true, reload: true });
    }
    undoBtn.addEventListener('click', function () { undoRedo(past, future); });
    redoBtn.addEventListener('click', function () { undoRedo(future, past); });
    document.addEventListener('keydown', function (e) {
        if (!(e.ctrlKey || e.metaKey) || e.altKey || /^(INPUT|TEXTAREA)$/.test(document.activeElement.tagName)) { return; }
        if (e.key.toLowerCase() === 'z') {
            e.preventDefault();
            if (e.shiftKey) { undoRedo(future, past); } else { undoRedo(past, future); }
        } else if (e.key.toLowerCase() === 'y') {
            e.preventDefault();
            undoRedo(future, past);
        }
    });

    function api(url, body, method) {
        var headers = { 'X-CSRF-Token': D.csrf, 'Accept': 'application/json' };
        var opts = { method: method || 'POST', headers: headers, credentials: 'same-origin' };
        if (body instanceof FormData) {
            opts.body = body;
        } else if (body !== undefined) {
            headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(body);
        }
        return fetch(url, opts).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (json) {
                if (!r.ok || json.error) {
                    var err = new Error(json.error || 'Something went wrong. Try again.');
                    err.status = r.status;
                    throw err;
                }
                return json;
            });
        });
    }

    var saveTimer = null;
    var saving = null;
    var saveAgain = false;
    var previewWanted = false;
    var previewScroll = null;

    function scheduleSave() {
        say('Saving…');
        clearTimeout(saveTimer);
        saveTimer = setTimeout(save, 600);
    }
    function save() {
        clearTimeout(saveTimer);
        saveTimer = null;
        if (saving) { saveAgain = true; return saving; }
        saving = api(D.urls.save, { settings: state })
            .then(function (json) {
                unpublished = json.unpublished;
                updatePublish();
                if (!saveAgain) {
                    say(unpublished ? 'Draft saved. Publish when you\'re happy.' : 'Changes stay in a draft until you publish.');
                    // The server checked the colours; use its answer
                    postTheme(json.theme);
                    showOwnColourNote(json.theme);
                    if (previewWanted) { reloadPreview(); }
                }
            })
            .catch(function (err) {
                say(err.status === 419 ? err.message : 'Couldn\'t save: ' + err.message, true);
            })
            .then(function () {
                saving = null;
                if (saveAgain) {
                    saveAgain = false;
                    return save();
                }
            });
        return saving;
    }
    function flush() {
        if (saveTimer) { return save(); }
        return saving || Promise.resolve();
    }
    window.addEventListener('beforeunload', function (e) {
        if (saveTimer || saving) { e.preventDefault(); e.returnValue = ''; }
    });

    /* ── Preview ─────────────────────────────────── */

    function wantPreview(scrollTo) {
        previewWanted = true;
        if (scrollTo) { previewScroll = scrollTo; }
    }
    function reloadPreview() {
        previewWanted = false;
        var target = previewScroll;
        previewScroll = null;
        var y = 0;
        try { y = frame.contentWindow.scrollY; } catch (e) { y = 0; }
        frame.addEventListener('load', function onload() {
            frame.removeEventListener('load', onload);
            try {
                var node = target && frame.contentDocument.getElementById(target);
                if (node) { node.scrollIntoView({ block: 'start' }); } else { frame.contentWindow.scrollTo(0, y); }
            } catch (e) { /* the preview is on this site; nothing to do if not */ }
        });
        frame.src = D.urls.preview + '?r=' + Date.now();
    }
    function scrollPreview(id) {
        try {
            var node = frame.contentDocument.getElementById(id);
            if (node) { node.scrollIntoView({ block: 'start', behavior: 'smooth' }); }
        } catch (e) { /* ignore */ }
    }

    $$('[data-device]').forEach(function (b) {
        b.addEventListener('click', function () {
            $$('[data-device]').forEach(function (o) { o.setAttribute('aria-pressed', String(o === b)); });
            $('.st-preview').classList.toggle('is-phone', b.dataset.device === 'phone');
        });
    });
    $('.st-preview').classList.add('is-phone');

    var panel = $('[data-panel]');
    $('[data-expand]').addEventListener('click', function () {
        var tall = !panel.classList.contains('is-tall');
        panel.classList.toggle('is-tall', tall);
        this.setAttribute('aria-expanded', String(tall));
        this.setAttribute('aria-label', tall ? 'Make the controls shorter' : 'Make the controls taller');
    });

    /* ── Publish ─────────────────────────────────── */

    function updatePublish() {
        publishBtn.disabled = !unpublished;
        publishBtn.textContent = unpublished ? 'Publish' : 'Published';
    }
    publishBtn.addEventListener('click', function () {
        publishBtn.disabled = true;
        publishBtn.setAttribute('aria-busy', 'true');
        say('Publishing…');
        flush().then(function () {
            return api(D.urls.publish, {});
        }).then(function (json) {
            unpublished = false;
            D.published = true;
            updatePublish();
            say(json.live ? 'Published. Customers see this version now.' : 'Published. Customers will see it once your shop link is live and switched on.');
            renderVersions(json.versions);
        }).catch(function (err) {
            say(err.message, true);
            publishBtn.disabled = false;
        }).then(function () {
            publishBtn.removeAttribute('aria-busy');
        });
    });

    /* ── Tabs ────────────────────────────────────── */

    var tabs = $$('[role="tab"]');
    function selectTab(tab, focus) {
        tabs.forEach(function (t) {
            var on = t === tab;
            t.setAttribute('aria-selected', String(on));
            t.tabIndex = on ? 0 : -1;
            $('[data-tab-panel="' + t.dataset.tab + '"]').hidden = !on;
        });
        if (focus) { tab.focus(); }
        if (tab.dataset.tab === 'more' && !versionsLoaded) { loadVersions(); }
        $('.st-panel__body').scrollTop = 0;
    }
    tabs.forEach(function (tab, i) {
        tab.addEventListener('click', function () { selectTab(tab); });
        tab.addEventListener('keydown', function (e) {
            var next = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: tabs.length - 1 }[e.key];
            if (next === undefined) { return; }
            e.preventDefault();
            selectTab(tabs[(next + tabs.length) % tabs.length], true);
        });
    });

    /* ── Look ────────────────────────────────────── */

    var looksEl = $('[data-looks]');
    function renderLooks() {
        looksEl.textContent = '';
        D.looks.forEach(function (lk) {
            var f = D.fonts[lk.pair];
            var pressed = state.look === lk.key;
            var b = el('button', { type: 'button', class: 'st-look', 'aria-pressed': String(pressed) });
            var thumb = el('span', { class: 'st-look__thumb', 'aria-hidden': 'true' });
            thumb.style.background = lk.colours.ground;
            var top = el('span', { class: 'st-look__top' });
            var aa = el('span', { class: 'st-look__aa' }, 'Aa');
            aa.style.cssText = 'font-family:' + f.head_stack + ';font-weight:' + f.weight + ';font-stretch:' + f.stretch + ';color:' + lk.colours.ink;
            var shape = el('span', { class: 'st-look__shape' });
            shape.style.cssText = 'background:' + lk.colours.accent + ';border-radius:' + lk.thumb;
            top.appendChild(aa);
            top.appendChild(shape);
            thumb.appendChild(top);
            [['80%'], ['52%']].forEach(function (w) {
                var line = el('span', { class: 'st-look__line' });
                line.style.cssText = 'width:' + w[0] + ';background:' + lk.colours.ink;
                thumb.appendChild(line);
            });
            var btn = el('span', { class: 'st-look__btn' });
            btn.style.cssText = 'background:' + lk.colours.accent + ';border-radius:' + lk.shape.btn;
            thumb.appendChild(btn);
            b.appendChild(thumb);
            var name = el('span', { class: 'st-look__name' }, lk.name);
            if (pressed) { name.appendChild(icon(ICON.check, 16)); }
            b.appendChild(name);
            b.appendChild(el('span', { class: 'st-look__kind' }, lk.kind));
            b.addEventListener('click', function () {
                if (state.look === lk.key) { return; }
                commit(function (s) {
                    s.look = lk.key;
                    s.colours = { set: 'look' };
                    s.font = '';
                }, { theme: true, reload: true });
                renderLooks();
                renderColours();
                renderFonts();
                renderSections();
            });
            looksEl.appendChild(b);
        });
    }

    /* ── Colours ─────────────────────────────────── */

    var setsEl = $('[data-sets]');
    function chip(name, colours, pressed, onPick) {
        var b = el('button', { type: 'button', class: 'st-chip', 'aria-pressed': String(pressed) });
        var sw = el('span', { class: 'st-chip__swatches', 'aria-hidden': 'true' });
        [colours.ground, colours.accent, colours.ink].forEach(function (c) {
            var s = el('span');
            s.style.background = c;
            sw.appendChild(s);
        });
        b.appendChild(sw);
        b.appendChild(el('span', {}, name));
        b.addEventListener('click', onPick);
        return b;
    }
    function pickColours(colours) {
        commit(function (s) { s.colours = colours; }, { theme: true });
        renderColours();
    }
    function renderColours() {
        setsEl.textContent = '';
        var lk = look(state.look);
        setsEl.appendChild(chip('Original', lk.colours, state.colours.set === 'look', function () { pickColours({ set: 'look' }); }));
        Object.keys(D.sets).forEach(function (key) {
            setsEl.appendChild(chip(D.sets[key].name, D.sets[key], state.colours.set === key, function () { pickColours({ set: key }); }));
        });
        renderLogoSets();
        var own = $('[data-own-colour]');
        if (own && state.colours.set !== 'custom') { own.value = themeFor(state).accent.toLowerCase(); }
    }

    var ownInput = $('[data-own-colour]');
    var ownNote = $('[data-own-note]');
    ownInput.addEventListener('input', function () {
        var picked = hex(ownInput.value);
        if (!picked) { return; }
        var base = state.colours.set === 'custom' ? (state.colours.base || 'look') : (D.sets[state.colours.set] ? state.colours.set : 'look');
        commit(function (s) { s.colours = { set: 'custom', base: base, accent: picked }; }, { key: 'own-colour', theme: true });
        $$('.st-chip', setsEl).forEach(function (c) { c.setAttribute('aria-pressed', 'false'); });
        showOwnColourNote(themeFor(state));
    });
    function showOwnColourNote(theme) {
        if (state.colours.set !== 'custom' || !theme) { ownNote.hidden = true; return; }
        var same = hex(theme.accent) === hex(state.colours.accent);
        ownNote.hidden = same;
        if (!same) {
            ownNote.textContent = luminance(theme.accent) < luminance(state.colours.accent)
                ? 'We made it a little darker so words on it stay easy to read.'
                : 'We made it a little lighter so words on it stay easy to read.';
        }
    }

    // Colours read from the logo: the strongest colour, a second one if the
    // logo has it, and a dark set
    var logoSets = null;
    function hueName(h) {
        var c = rgb(h);
        var max = Math.max(c[0], c[1], c[2]), min = Math.min(c[0], c[1], c[2]);
        var light = (max + min) / 510;
        var sat = max === min ? 0 : (max - min) / (255 - Math.abs(max + min - 255));
        if (sat < 0.18) { return light < 0.5 ? 'Charcoal' : 'Stone'; }
        var hue;
        if (max === c[0]) { hue = ((c[1] - c[2]) / (max - min) + 6) % 6; } else if (max === c[1]) { hue = (c[2] - c[0]) / (max - min) + 2; } else { hue = (c[0] - c[1]) / (max - min) + 4; }
        hue *= 60;
        if (hue < 15 || hue >= 345) { return light < 0.35 ? 'Wine' : 'Red'; }
        if (hue < 40) { return light < 0.4 ? 'Rust' : 'Orange'; }
        if (hue < 65) { return light < 0.45 ? 'Olive' : 'Gold'; }
        if (hue < 160) { return 'Green'; }
        if (hue < 195) { return 'Teal'; }
        if (hue < 235) { return 'Blue'; }
        if (hue < 260) { return 'Indigo'; }
        if (hue < 300) { return 'Purple'; }
        return 'Pink';
    }
    function readLogo(img) {
        try {
            var size = 48;
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
                if (max > 240 && min > 225) { continue; }   // white background
                if (max < 20) { continue; }                 // black
                var key = (r >> 4) + ',' + (g >> 4) + ',' + (b >> 4);
                var bucket = buckets[key] || (buckets[key] = { n: 0, r: 0, g: 0, b: 0, sat: max - min });
                bucket.n++; bucket.r += r; bucket.g += g; bucket.b += b;
            }
            var list = Object.keys(buckets).map(function (k) {
                var x = buckets[k];
                return { colour: '#' + [x.r / x.n, x.g / x.n, x.b / x.n].map(function (c) { return ('0' + Math.round(c).toString(16)).slice(-2); }).join('').toUpperCase(), score: x.n * (0.4 + x.sat / 255) };
            }).sort(function (a, b) { return b.score - a.score; });
            if (!list.length) { return null; }
            var first = list[0].colour;
            var second = (list.filter(function (x) { return contrast(x.colour, first) > 1.8; })[0] || {}).colour || null;
            var sets = [{ set: 'logo', name: hueName(first), ground: mix(first, '#FFFFFF', 0.94), ink: mix(first, '#000000', 0.82), accent: first }];
            if (second) {
                sets.push({ set: 'logo', name: hueName(second) === sets[0].name ? 'Second colour' : hueName(second), ground: '#F7F6F4', ink: mix(second, '#000000', 0.84), accent: second });
            } else {
                sets.push({ set: 'logo', name: 'Soft', ground: '#FFFFFF', ink: mix(first, '#000000', 0.86), accent: mix(first, '#000000', 0.2) });
            }
            sets.push({ set: 'logo', name: 'Dark', ground: mix(first, '#000000', 0.84), ink: '#F6F6F3', accent: second ? mix(second, '#FFFFFF', 0.2) : mix(first, '#FFFFFF', 0.45) });
            return sets.map(function (s) { ['ground', 'ink', 'accent'].forEach(function (k) { s[k] = hex(s[k]); }); return s; });
        } catch (e) {
            return null;
        }
    }
    function renderLogoSets() {
        var box = $('[data-logo-sets]');
        if (!box || !logoSets) { return; }
        box.textContent = '';
        logoSets.forEach(function (s) {
            var pressed = state.colours.set === 'logo' && state.colours.accent === s.accent && state.colours.ground === s.ground;
            box.appendChild(chip(s.name, s, pressed, function () { pickColours(s); }));
        });
    }
    var logoImg = $('[data-logo]');
    if (logoImg) {
        var onLogo = function () {
            logoSets = readLogo(logoImg);
            if (!logoSets) {
                $('[data-logo-sets]').innerHTML = '';
                $('[data-logo-sets]').appendChild(el('p', { class: 'st-help' }, 'We couldn\'t read colours from this logo. Try a colour set below.'));
                return;
            }
            renderLogoSets();
        };
        if (logoImg.complete && logoImg.naturalWidth) { onLogo(); } else { logoImg.addEventListener('load', onLogo); }
    }

    /* ── Fonts ───────────────────────────────────── */

    var fontsEl = $('[data-fonts]');
    function renderFonts() {
        fontsEl.textContent = '';
        var current = pairKey(state);
        Object.keys(D.fonts).forEach(function (key) {
            var f = D.fonts[key];
            var b = el('button', { type: 'button', class: 'st-font', 'aria-pressed': String(key === current) });
            var sample = el('span', { class: 'st-font__sample' }, D.business.name);
            sample.style.cssText = 'font-family:' + f.head_stack + ';font-weight:' + f.weight + ';font-stretch:' + f.stretch + ';font-size:' + Math.round(20 * f.scale + 2) + 'px';
            b.appendChild(sample);
            b.appendChild(el('span', { class: 'st-font__name' }, f.name + (key === look(state.look).pair ? ' (this look\'s own)' : '')));
            b.addEventListener('click', function () {
                commit(function (s) { s.font = key === look(s.look).pair ? '' : key; }, { theme: true });
                renderFonts();
            });
            fontsEl.appendChild(b);
        });
    }

    /* ── Sections ────────────────────────────────── */

    var listEl = $('[data-sections]');
    var listWrap = $('[data-section-list]');
    var editorEl = $('[data-editor]');

    function sectionAt(type) { return state.sections.filter(function (s) { return s.type === type; })[0]; }
    function movable(type) { return D.types[type].pin === null; }
    function labelFor(type, field) {
        var lk = look(state.look);
        var key = { featured: 'featured', catalogue: 'catalogue', about: 'about', delivery: 'delivery', faq: 'faq', quote: 'quote', video: 'video' }[type];
        if (field === 'button') { return lk.labels.button; }
        return key ? (lk.labels[key] || '') : '';
    }
    function short(text, n) {
        text = (text || '').replace(/\s+/g, ' ').trim();
        return text.length > n ? text.slice(0, n - 1) + '…' : text;
    }
    function summary(s) {
        switch (s.type) {
            case 'announcement': return s.text ? short(s.text, 60) : D.types.announcement.hint;
            case 'hero': return short(s.headline || D.business.tagline || D.business.name, 60);
            case 'featured': return s.products.length ? s.products.length + ' picked' : 'Your newest, once you have six or more';
            case 'catalogue': return s.only.length ? 'Only ' + s.only.length + ' of your products' : 'Everything you sell';
            case 'about': return s.text ? short(s.text, 60) : 'Tell people who you are';
            case 'video': return s.url ? 'A YouTube video' : 'Add a YouTube link';
            case 'delivery': return s.text ? short(s.text, 60) : 'Say how you deliver';
            case 'faq': return s.items.length ? s.items.length + ' question' + (s.items.length === 1 ? '' : 's') : 'Add the questions people ask';
            case 'quote': return s.title || labelFor('quote');
            case 'contact': return ['instagram', 'facebook', 'tiktok', 'twitter'].filter(function (k) { return s[k]; }).length
                ? 'WhatsApp and your socials' : 'WhatsApp, address and phone';
        }
        return '';
    }

    function renderSections() {
        listEl.textContent = '';
        var middle = state.sections.filter(function (s) { return movable(s.type); });
        state.sections.forEach(function (s) {
            var def = D.types[s.type];
            var li = el('li', { class: 'st-section', 'data-type': s.type });
            var grip = el('span', { class: 'st-section__grip' + (movable(s.type) ? '' : ' st-section__grip--fixed'), 'aria-hidden': 'true', title: movable(s.type) ? 'Drag to move' : '' });
            grip.appendChild(icon(ICON.grip, 16));
            li.appendChild(grip);
            var main = el('button', { type: 'button', class: 'st-section__main', 'aria-label': 'Edit ' + def.name });
            main.appendChild(el('span', { class: 'st-section__name' }, def.name));
            main.appendChild(el('span', { class: 'st-section__hint' }, summary(s)));
            main.addEventListener('click', function () { openEditor(s.type); });
            li.appendChild(main);
            if (movable(s.type)) {
                var idx = middle.indexOf(s);
                var up = el('button', { type: 'button', class: 'st-section__move', 'aria-label': 'Move ' + def.name + ' up', disabled: idx === 0 });
                up.appendChild(icon(ICON.up));
                up.addEventListener('click', function () { moveSection(s.type, -1); });
                var down = el('button', { type: 'button', class: 'st-section__move', 'aria-label': 'Move ' + def.name + ' down', disabled: idx === middle.length - 1 });
                down.appendChild(icon(ICON.down));
                down.addEventListener('click', function () { moveSection(s.type, 1); });
                li.appendChild(up);
                li.appendChild(down);
                grip.addEventListener('pointerdown', function (e) { startDrag(e, li); });
            }
            if (def.switch) {
                var sw = el('button', { type: 'button', class: 'st-switch', role: 'switch', 'aria-checked': String(s.on), 'aria-label': 'Show ' + def.name });
                sw.appendChild(el('span'));
                sw.addEventListener('click', function () {
                    commit(function () { s.on = !s.on; }, { reload: true, scrollTo: 'sf-' + s.type });
                    sw.setAttribute('aria-checked', String(s.on));
                });
                li.appendChild(sw);
            }
            listEl.appendChild(li);
        });
    }
    function moveSection(type, step) {
        commit(function (st) {
            var order = st.sections.filter(function (s) { return movable(s.type); });
            var i = order.indexOf(sectionAt(type));
            var j = i + step;
            if (j < 0 || j >= order.length) { return; }
            order.splice(j, 0, order.splice(i, 1)[0]);
            st.sections = st.sections.filter(function (s) { return D.types[s.type].pin === 'top'; })
                .concat(order, st.sections.filter(function (s) { return D.types[s.type].pin === 'bottom'; }));
        }, { reload: true, scrollTo: 'sf-' + type });
        renderSections();
        var moved = $('[data-type="' + type + '"] .st-section__move:not([disabled])', listEl);
        if (moved) { moved.focus(); }
    }

    // Drag a section by its grip; the list settles into the new order on release
    function startDrag(e, li) {
        e.preventDefault();
        var grip = e.currentTarget;
        grip.setPointerCapture(e.pointerId);
        li.classList.add('is-dragging');
        var move = function (ev) {
            var rows = $$('.st-section', listEl).filter(function (r) { return movable(r.dataset.type) && r !== li; });
            var after = null;
            rows.forEach(function (r) {
                var box = r.getBoundingClientRect();
                if (ev.clientY > box.top + box.height / 2) { after = r; }
            });
            if (after) {
                after.after(li);
            } else if (rows.length) {
                rows[0].before(li);
            }
        };
        var end = function () {
            grip.removeEventListener('pointermove', move);
            grip.removeEventListener('pointerup', end);
            grip.removeEventListener('pointercancel', end);
            li.classList.remove('is-dragging');
            var order = $$('.st-section', listEl).map(function (r) { return r.dataset.type; }).filter(movable);
            commit(function (st) {
                var byType = {};
                st.sections.forEach(function (s) { byType[s.type] = s; });
                st.sections = st.sections.filter(function (s) { return D.types[s.type].pin === 'top'; })
                    .concat(order.map(function (t) { return byType[t]; }), st.sections.filter(function (s) { return D.types[s.type].pin === 'bottom'; }));
            }, { reload: true, scrollTo: 'sf-' + li.dataset.type });
            renderSections();
        };
        grip.addEventListener('pointermove', move);
        grip.addEventListener('pointerup', end);
        grip.addEventListener('pointercancel', end);
    }

    /* ── Section editor ──────────────────────────── */

    var editing = null;
    function openEditor(type) {
        editing = type;
        listWrap.hidden = true;
        editorEl.hidden = false;
        renderEditor();
        scrollPreview('sf-' + type);
        $('.st-panel__body').scrollTop = 0;
        var back = $('[data-editor-back]', editorEl);
        if (back) { back.focus(); }
    }
    function closeEditor() {
        var type = editing;
        editing = null;
        editorEl.hidden = true;
        listWrap.hidden = false;
        renderSections();
        var row = $('[data-type="' + type + '"] .st-section__main', listEl);
        if (row) { row.focus(); }
    }

    function field(label, input, hint) {
        var wrap = el('label', { class: 'st-field' });
        wrap.appendChild(el('span', { class: 'st-label' }, label));
        if (hint) { wrap.appendChild(el('span', { class: 'st-field__hint' }, hint)); }
        wrap.appendChild(input);
        return wrap;
    }
    function edit(s, key, value, extra) {
        commit(function () { s[key] = value; }, Object.assign({ key: s.type + '.' + key, reload: true, scrollTo: 'sf-' + s.type }, extra || {}));
    }
    function textInput(s, key, spec, placeholder) {
        var input = el(spec.kind === 'text' ? 'textarea' : 'input', spec.kind === 'text'
            ? { rows: spec.max > 400 ? 6 : 3, maxlength: spec.max, placeholder: placeholder || null }
            : { type: 'text', maxlength: spec.max || 300, placeholder: placeholder || null, autocomplete: 'off' });
        input.value = s[key] || '';
        input.addEventListener('input', function () { edit(s, key, input.value); });
        return input;
    }
    function checkbox(s, key, label) {
        var wrap = el('label', { class: 'st-check' });
        var input = el('input', { type: 'checkbox' });
        input.checked = !!s[key];
        input.addEventListener('change', function () { edit(s, key, input.checked); });
        wrap.appendChild(input);
        wrap.appendChild(el('span', {}, label));
        return wrap;
    }
    function picks(s, key, max, note) {
        var box = el('div', { class: 'st-field' });
        box.appendChild(el('span', { class: 'st-label' }, D.types[s.type].fields[key].label));
        if (note) { box.appendChild(el('span', { class: 'st-field__hint' }, note)); }
        if (!D.products.length) {
            var none = el('p', { class: 'st-help' }, 'You have no products yet. ');
            none.appendChild(el('a', { href: D.urls.products }, 'Add your first product'));
            box.appendChild(none);
            return box;
        }
        var list = el('ul', { class: 'st-picks' });
        D.products.forEach(function (p) {
            var li = el('li');
            var label = el('label', { class: 'st-pick' });
            var input = el('input', { type: 'checkbox', value: String(p.id) });
            input.checked = s[key].indexOf(p.id) !== -1;
            input.addEventListener('change', function () {
                if (input.checked && s[key].length >= max) {
                    input.checked = false;
                    toast('You can pick up to ' + max + '.');
                    return;
                }
                commit(function () {
                    s[key] = input.checked ? s[key].concat([p.id]) : s[key].filter(function (id) { return id !== p.id; });
                }, { reload: true, scrollTo: 'sf-' + s.type });
            });
            label.appendChild(input);
            if (p.thumb) { label.appendChild(el('img', { src: p.thumb, alt: '', loading: 'lazy' })); } else { label.appendChild(el('span', { class: 'st-pick__ph' })); }
            label.appendChild(el('span', { class: 'st-pick__name' }, p.name));
            label.appendChild(el('span', { class: 'st-pick__price' }, p.price + (p.service ? ' · service' : '')));
            li.appendChild(label);
            list.appendChild(li);
        });
        box.appendChild(list);
        return box;
    }

    function renderEditor() {
        var s = sectionAt(editing);
        var def = D.types[editing];
        editorEl.textContent = '';
        var head = el('div', { class: 'st-editor__head' });
        var back = el('button', { type: 'button', class: 'st-iconbtn', 'aria-label': 'Back to sections', 'data-editor-back': true });
        back.appendChild(icon(ICON.back, 22));
        back.addEventListener('click', closeEditor);
        head.appendChild(back);
        head.appendChild(el('h2', { class: 'st-h2' }, def.name));
        if (def.switch) {
            var sw = el('button', { type: 'button', class: 'st-switch', role: 'switch', 'aria-checked': String(s.on), 'aria-label': 'Show ' + def.name });
            sw.appendChild(el('span'));
            sw.addEventListener('click', function () {
                commit(function () { s.on = !s.on; }, { reload: true, scrollTo: 'sf-' + s.type });
                sw.setAttribute('aria-checked', String(s.on));
            });
            head.appendChild(sw);
        }
        editorEl.appendChild(head);
        if (def.hint) { editorEl.appendChild(el('p', { class: 'st-help' }, def.hint)); }

        var f = def.fields;
        switch (editing) {
            case 'announcement':
                editorEl.appendChild(field('Message', textInput(s, 'text', f.text, 'Free delivery in Lagos this week')));
                break;
            case 'hero':
                editorEl.appendChild(field('Headline', textInput(s, 'headline', f.headline, D.business.tagline || D.business.name)));
                editorEl.appendChild(field('A line or two under it', textInput(s, 'text', f.text, 'What you sell, where, and why people come back')));
                editorEl.appendChild(field('Button', textInput(s, 'button', f.button, labelFor('hero', 'button')), 'It takes people to your products.'));
                editorEl.appendChild(field('Sticker', textInput(s, 'badge', f.badge, 'Delivery in 24 hours'), 'A short note in a sticker or stamp. Leave it empty for none.'));
                editorEl.appendChild(mediaField(s));
                break;
            case 'featured':
                editorEl.appendChild(field('Title', textInput(s, 'title', f.title, labelFor('featured'))));
                editorEl.appendChild(picks(s, 'products', 8, 'Pick up to 8. Leave them all empty and we show your newest.'));
                break;
            case 'catalogue':
                editorEl.appendChild(field('Title', textInput(s, 'title', f.title, labelFor('catalogue'))));
                editorEl.appendChild(picks(s, 'only', 500, 'Leave them all empty to show everything you sell. Tick some to show only those.'));
                break;
            case 'about':
                editorEl.appendChild(field('Title', textInput(s, 'title', f.title, labelFor('about'))));
                editorEl.appendChild(field('Your story', textInput(s, 'text', f.text, 'Who you are, what you make and where to find you')));
                break;
            case 'video':
                editorEl.appendChild(field('Title', textInput(s, 'title', f.title, 'How we make it')));
                editorEl.appendChild(youtubeField(s));
                break;
            case 'delivery':
                editorEl.appendChild(field('Title', textInput(s, 'title', f.title, labelFor('delivery'))));
                editorEl.appendChild(field('How you deliver', textInput(s, 'text', f.text, 'Where you deliver, what it costs, when people can pick up')));
                editorEl.appendChild(checkbox(s, 'show_bank', 'Show my bank details'));
                editorEl.appendChild(el('p', { class: 'st-help' }, 'Your bank accounts come from Settings. People also see them when they check out.'));
                break;
            case 'faq':
                editorEl.appendChild(field('Title', textInput(s, 'title', f.title, labelFor('faq'))));
                editorEl.appendChild(faqField(s, f.items.max));
                break;
            case 'quote':
                editorEl.appendChild(field('Title', textInput(s, 'title', f.title, labelFor('quote'))));
                editorEl.appendChild(field('A line under the title', textInput(s, 'text', f.text, 'Tell us what you need and we will reply with a price.')));
                editorEl.appendChild(el('p', { class: 'st-help' }, 'The form always asks for a name, a phone number and what they need. You can also:'));
                ['quantity_range', 'budget', 'timeline', 'delivery_location', 'company_name'].forEach(function (k) {
                    editorEl.appendChild(checkbox(s, k, f[k].label));
                });
                break;
            case 'contact':
                editorEl.appendChild(field('A short line', textInput(s, 'text', f.text, 'Open Monday to Saturday, 9am to 6pm')));
                ['instagram', 'facebook', 'tiktok', 'twitter'].forEach(function (k) { editorEl.appendChild(handleField(s, k, f[k].label)); });
                editorEl.appendChild(checkbox(s, 'show_address', 'Show my address'));
                editorEl.appendChild(checkbox(s, 'show_phone', 'Show my phone number'));
                editorEl.appendChild(checkbox(s, 'show_email', 'Show my email'));
                editorEl.appendChild(el('p', { class: 'st-help' }, 'Your WhatsApp button, address, phone and email come from Settings.'));
                break;
        }
    }

    function handleField(s, key, label) {
        var wrap = el('div', { class: 'st-field' });
        var id = 'st-handle-' + key;
        wrap.appendChild(el('label', { class: 'st-label', for: id }, label));
        var box = el('div', { class: 'st-prefix' });
        box.appendChild(el('span', { 'aria-hidden': 'true' }, '@'));
        var input = el('input', { type: 'text', id: id, maxlength: 80, autocomplete: 'off', autocapitalize: 'none', spellcheck: 'false', placeholder: 'yourname' });
        input.value = s[key] || '';
        input.addEventListener('input', function () {
            var v = input.value.trim().replace(/^https?:\/\/(www\.|m\.)?[a-z]+\.com\/@?/i, '').replace(/^@/, '').replace(/[/?#].*$/, '');
            edit(s, key, /^[A-Za-z0-9._-]{0,60}$/.test(v) ? v : s[key]);
        });
        box.appendChild(input);
        wrap.appendChild(box);
        return wrap;
    }
    function youtubeField(s) {
        var input = el('input', { type: 'url', inputmode: 'url', placeholder: 'https://youtu.be/…', autocomplete: 'off' });
        input.value = s.url || '';
        var err = el('p', { class: 'st-field__error', role: 'alert' });
        var ok = function (v) { return /^(https?:\/\/)?(www\.|m\.)?(youtube\.com\/(watch\?(.*&)?v=|embed\/|shorts\/|live\/)|youtu\.be\/)[A-Za-z0-9_-]{11}/.test(v.trim()); };
        input.addEventListener('input', function () {
            var v = input.value.trim();
            err.textContent = v && !ok(v) ? 'Paste a YouTube link, like https://youtu.be/…' : '';
            if (!v || ok(v)) { edit(s, 'url', v); }
        });
        var wrap = field('YouTube link', input, 'Nothing loads from YouTube until someone taps play.');
        wrap.appendChild(err);
        return wrap;
    }
    function faqField(s, max) {
        var box = el('div', { class: 'st-faq' });
        var draw = function () {
            box.textContent = '';
            s.items.forEach(function (item, i) {
                var card = el('div', { class: 'st-faq__item' });
                var q = el('input', { type: 'text', maxlength: 160, placeholder: 'How long does delivery take?' });
                q.value = item.q;
                q.addEventListener('input', function () { commit(function () { item.q = q.value; }, { key: 'faq.q.' + i, reload: true, scrollTo: 'sf-faq' }); });
                var a = el('textarea', { rows: 3, maxlength: 800, placeholder: 'Your answer' });
                a.value = item.a;
                a.addEventListener('input', function () { commit(function () { item.a = a.value; }, { key: 'faq.a.' + i, reload: true, scrollTo: 'sf-faq' }); });
                card.appendChild(field('Question ' + (i + 1), q));
                card.appendChild(field('Answer', a));
                var actions = el('div', { class: 'st-faq__actions' });
                var remove = el('button', { type: 'button', class: 'st-btn st-btn--quiet st-btn--sm' }, 'Remove this question');
                remove.addEventListener('click', function () {
                    commit(function () { s.items.splice(i, 1); }, { reload: true, scrollTo: 'sf-faq' });
                    draw();
                });
                actions.appendChild(remove);
                card.appendChild(actions);
                box.appendChild(card);
            });
            if (s.items.length < max) {
                var add = el('button', { type: 'button', class: 'st-btn st-btn--quiet' }, 'Add a question');
                add.addEventListener('click', function () {
                    commit(function () { s.items.push({ q: '', a: '' }); }, {});
                    draw();
                    var inputs = $$('.st-faq__item input', box);
                    if (inputs.length) { inputs[inputs.length - 1].focus(); }
                });
                box.appendChild(add);
            }
        };
        draw();
        return box;
    }
    function mediaField(s) {
        var box = el('div', { class: 'st-field st-media' });
        box.appendChild(el('span', { class: 'st-label' }, 'Photo or video'));
        box.appendChild(el('span', { class: 'st-field__hint' }, 'A wide photo works best. Videos play only on good connections and never with sound.'));
        var preview = null;
        if (s.image) {
            preview = /\.mp4$/.test(s.image)
                ? el('video', { class: 'st-media__preview', src: BASE + '/' + s.image, muted: true, playsinline: true, preload: 'metadata' })
                : el('img', { class: 'st-media__preview', src: BASE + '/' + s.image, alt: 'Your current photo' });
            box.appendChild(preview);
        }
        var actions = el('div', { class: 'st-media__actions' });
        var pick = el('label', { class: 'st-btn st-btn--quiet st-file' });
        pick.appendChild(el('span', {}, s.image ? 'Replace it' : 'Upload a photo or video'));
        var input = el('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp,video/mp4' });
        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (!file) { return; }
            var fd = new FormData();
            fd.append('media', file);
            fd.append('csrf_token', D.csrf);
            say('Uploading…');
            api(D.urls.upload, fd).then(function (json) {
                commit(function () { s.image = json.path; }, { reload: true, scrollTo: 'sf-hero' });
                renderEditor();
            }).catch(function (err) { say(err.message, true); });
        });
        pick.appendChild(input);
        actions.appendChild(pick);
        if (s.image) {
            var remove = el('button', { type: 'button', class: 'st-btn st-btn--quiet' }, 'Remove it');
            remove.addEventListener('click', function () {
                commit(function () { s.image = ''; }, { reload: true, scrollTo: 'sf-hero' });
                renderEditor();
            });
            actions.appendChild(remove);
        }
        box.appendChild(actions);
        return box;
    }

    /* ── More: versions, visibility, link ────────── */

    var versionsEl = $('[data-versions]');
    var versionsLoaded = false;
    function loadVersions() {
        versionsLoaded = true;
        api(D.urls.versions, undefined, 'GET').then(function (json) { renderVersions(json.versions); })
            .catch(function () { versionsEl.innerHTML = ''; versionsEl.appendChild(el('li', { class: 'st-help' }, 'Couldn\'t load your versions. Try again later.')); });
    }
    function renderVersions(list) {
        versionsLoaded = true;
        versionsEl.textContent = '';
        if (!list || !list.length) {
            versionsEl.appendChild(el('li', { class: 'st-help' }, 'Nothing published from Shop Studio yet.'));
            return;
        }
        list.forEach(function (v, i) {
            var li = el('li', { class: 'st-version' });
            var text = el('div');
            text.appendChild(el('p', { class: 'st-version__when' }, v.when + (i === 0 ? ' (live now)' : '')));
            text.appendChild(el('p', { class: 'st-version__meta' }, v.look + (v.by ? ' · ' + v.by : '')));
            li.appendChild(text);
            var b = el('button', { type: 'button', class: 'st-btn st-btn--quiet st-btn--sm' }, 'Bring back');
            b.setAttribute('aria-label', 'Bring back the version from ' + v.when);
            b.addEventListener('click', function () {
                b.disabled = true;
                flush().then(function () { return api(D.urls.restore, { id: v.id }); }).then(function (json) {
                    past.push(snapshot());
                    future = [];
                    state = json.settings;
                    unpublished = json.unpublished;
                    updatePublish();
                    updateHistoryButtons();
                    renderAll();
                    postTheme(json.theme);
                    reloadPreview();
                    say('The version from ' + v.when + ' is in your draft. Publish to make it live.');
                }).catch(function (err) { say(err.message, true); }).then(function () { b.disabled = false; });
            });
            li.appendChild(b);
            versionsEl.appendChild(li);
        });
    }

    var visible = $('[data-visibility]');
    visible.addEventListener('click', function () {
        var on = visible.getAttribute('aria-checked') !== 'true';
        visible.disabled = true;
        api(D.urls.visibility, { on: on }).then(function (json) {
            visible.setAttribute('aria-checked', String(json.on));
            toast(json.on ? 'Your shop is open to customers.' : 'Your shop is hidden. Your link says there is no shop there.');
        }).catch(function (err) { say(err.message, true); }).then(function () { visible.disabled = false; });
    });

    $$('[data-copy]').forEach(function (b) {
        b.addEventListener('click', function () {
            if (!navigator.clipboard) { toast(b.dataset.copy); return; }
            navigator.clipboard.writeText(b.dataset.copy).then(function () { toast('Link copied. Paste it on WhatsApp or Instagram.'); }, function () { toast(b.dataset.copy); });
        });
    });

    // The rename form: checked while typing, with the same rules as setup
    var rename = $('[data-rename]');
    if (rename) {
        var nameInput = $('[data-name-input]', rename);
        var nameStatus = $('[data-name-status]', rename);
        var nameSubmit = $('[data-name-submit]', rename);
        var checkTimer = null;
        var lastAsked = '';
        nameInput.addEventListener('input', function () {
            clearTimeout(checkTimer);
            nameSubmit.disabled = true;
            nameStatus.className = 'st-namestatus';
            nameStatus.textContent = '';
            var value = nameInput.value.trim();
            if (!value) { return; }
            checkTimer = setTimeout(function () {
                lastAsked = value;
                fetch(D.urls.check + '?name=' + encodeURIComponent(value), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (json) {
                        if (lastAsked !== nameInput.value.trim()) { return; }
                        var good = json.state === 'ok' || json.state === 'review';
                        nameStatus.textContent = json.message + (json.suggestions && json.suggestions.length && !good ? ' Free: ' + json.suggestions.join(', ') + '.' : '');
                        nameStatus.className = 'st-namestatus ' + (json.state === 'ok' ? 'is-ok' : json.state === 'review' ? 'is-wait' : 'is-bad');
                        nameSubmit.disabled = !good;
                    })
                    .catch(function () { nameStatus.textContent = 'Couldn\'t check that name just now.'; });
            }, 350);
        });
    }

    /* ── Start ───────────────────────────────────── */

    function renderAll() {
        renderLooks();
        renderColours();
        renderFonts();
        if (editing && sectionAt(editing)) { renderEditor(); } else { if (editing) { closeEditor(); } renderSections(); }
    }
    renderAll();
    updatePublish();
    updateHistoryButtons();
})();
