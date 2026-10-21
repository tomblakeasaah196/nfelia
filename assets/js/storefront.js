/*
 * Nfelia — Storefront behaviour (no framework)
 *
 * Reads the shop's catalogue from <script id="sf-data"> (views/storefront/
 * shell.php) and runs the product sheet, the bag and its checkout, the
 * category filter and search, the quote form and the hero video; on a
 * product's page, its buy box and photos; on an order's page, "order again";
 * on a booking page, the calendar of free days and times, and sending it.
 *
 * Product links go to each product's page (/p/12-name). On the shop a tap
 * opens the product as a sheet instead and puts its address in the bar, so
 * the address can be shared and Back closes the sheet.
 *
 * The bag lives in localStorage as nfelia_cart_<shop>, the same key and
 * shape the old shop page used, so bags survive the change. Checkout sends
 * only product ids, quantities and choices: the server prices the order.
 * The links of the shopper's last few orders and bookings are kept beside it
 * (nfelia_orders_<shop>), so they find them again without an account.
 *
 * In Shop Studio's preview ($mode 'preview' or 'look') nothing is sent, and
 * the page listens for colour and font changes from Studio.
 */
(function () {
    'use strict';

    var dataEl = document.getElementById('sf-data');
    if (!dataEl) { return; }
    var S = JSON.parse(dataEl.textContent);
    var LIVE = S.mode === 'live';
    var root = document.documentElement;
    var $ = function (sel, el) { return (el || document).querySelector(sel); };
    var $$ = function (sel, el) { return Array.prototype.slice.call((el || document).querySelectorAll(sel)); };
    var PREVIEW_NOTE = 'This is a preview. Orders and requests work on your live shop.';
    var baseTitle = document.title;

    /* ── Money and words ─────────────────────────── */

    function money(n) {
        var sym = S.currency || '₦';
        var whole = Math.round(n * 100) % 100 === 0;
        return sym + (sym.length > 1 ? ' ' : '') + Number(n).toLocaleString('en-NG', {
            minimumFractionDigits: whole ? 0 : 2, maximumFractionDigits: 2
        });
    }
    function unitText(unit) {
        unit = (unit || '').trim();
        if (!unit) { return ''; }
        return /^(pc|pcs|piece|pieces|unit|units|each|item|items|ea)$/i.test(unit) ? ' each' : ' per ' + unit;
    }
    function plural(n, word) { return n + ' ' + word + (n === 1 ? '' : 's'); }
    // As sf_initials() in app/helpers/storefront.php
    function initials(name) {
        var letters = '';
        var digit = '';
        (name || '').split(/[\s&\-_.,]+/).forEach(function (w) {
            var m = w.match(/^[^\p{L}\p{N}]*(\p{L})/u);
            if (m && letters.length < 2) {
                letters += m[1].toUpperCase();
            } else if (!digit && /\p{N}/u.test(w)) {
                digit = w.match(/\p{N}/u)[0];
            }
        });
        return letters || digit || 'N';
    }
    function el(tag, attrs, text) {
        var node = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (k) {
            if (k === 'class') { node.className = attrs[k]; } else { node.setAttribute(k, attrs[k]); }
        });
        if (text !== undefined && text !== null) { node.textContent = text; }
        return node;
    }

    var toastEl = $('[data-toast]');
    var toastTimer = null;
    function toast(text) {
        if (!toastEl) { return; }
        toastEl.textContent = text;
        toastEl.classList.add('is-on');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { toastEl.classList.remove('is-on'); }, 2600);
    }

    /* ── Variant prices ──────────────────────────── */

    function round2(n) { return Math.round(n * 100) / 100; }
    // Sum the price changes of the chosen options, read back from the variant
    // string ("Colour: Navy Blue, Size: XXL") the forms already produce.
    function variantDelta(item, variant) {
        if (!item || !item.variants || !variant) { return 0; }
        var chosen = {};
        String(variant).split(', ').forEach(function (tok) {
            var at = tok.indexOf(': ');
            if (at > 0) { chosen[tok.slice(0, at)] = tok.slice(at + 2); }
        });
        var d = 0;
        item.variants.forEach(function (g) {
            var label = chosen[g.name];
            if (label && g.deltas && typeof g.deltas[label] === 'number') { d += g.deltas[label]; }
        });
        return d;
    }
    function linePrice(item, variant) { return round2((item.price || 0) + variantDelta(item, variant)); }

    /* ── The bag ─────────────────────────────────── */

    var CART_KEY = 'nfelia_cart_' + S.shop;
    var cart = [];
    function loadCart() {
        try { cart = JSON.parse(localStorage.getItem(CART_KEY)) || []; } catch (e) { cart = []; }
        // Keep lines for products still on sale, at today's name and price
        cart = cart.filter(function (line) {
            var item = line && S.items[String(line.id)];
            if (!item) { return false; }
            line.name = item.name;
            line.price = linePrice(item, line.variant);
            line.qty = Math.max(1, Math.min(999, parseInt(line.qty, 10) || 1));
            return true;
        });
    }
    function saveCart() {
        try { localStorage.setItem(CART_KEY, JSON.stringify(cart)); } catch (e) { /* private browsing */ }
        renderCart();
    }
    function cartCount() { return cart.reduce(function (s, l) { return s + l.qty; }, 0); }
    function cartTotal() { return cart.reduce(function (s, l) { return s + l.price * l.qty; }, 0); }
    function putLine(item, qty, variant, note) {
        var key = item.id + '|' + (variant || '');
        var line = cart.filter(function (l) { return l.key === key; })[0];
        if (line) {
            line.qty = Math.min(999, line.qty + qty);
            if (note) { line.note = note; }
        } else {
            cart.push({ key: key, id: item.id, name: item.name, price: linePrice(item, variant), qty: qty, variant: variant || '', note: note || '',
                        image: item.img.length ? item.img[0].src : '' });
        }
    }
    function addedText() { return 'Added. ' + (S.look === 'market' ? 'Your order has ' : 'Your bag has ') + plural(cartCount(), 'item') + '.'; }
    function addLine(id, qty, variant, note) {
        var item = S.items[String(id)];
        if (!item) { return; }
        putLine(item, qty, variant, note);
        saveCart();
        toast(addedText());
    }
    // Several lines at once ("order again"); returns how many were still on sale
    function addLines(lines) {
        var added = 0;
        lines.forEach(function (l) {
            var item = S.items[String(l.id)];
            if (!item) { return; }
            putLine(item, Math.max(1, Math.min(999, parseInt(l.qty, 10) || 1)), l.variant || '', l.note || '');
            added++;
        });
        if (added) { saveCart(); }
        return added;
    }

    /* ── Recent orders (tracking links on this phone) ── */

    var ORDERS_KEY = 'nfelia_orders_' + S.shop;
    function recentOrders() {
        try {
            var list = JSON.parse(localStorage.getItem(ORDERS_KEY)) || [];
            return list.filter(function (o) { return o && typeof o.n === 'string' && /^\/(order|booking)\/[a-f0-9]{32}$/.test(o.p || ''); });
        } catch (e) { return []; }
    }
    function rememberOrder(number, path) {
        if (!/^\/(order|booking)\/[a-f0-9]{32}$/.test(path)) { return; }
        var list = recentOrders().filter(function (o) { return o.p !== path; });
        list.unshift({ n: number, p: path, t: Date.now() });
        try { localStorage.setItem(ORDERS_KEY, JSON.stringify(list.slice(0, 5))); } catch (e) { /* private browsing */ }
    }
    function renderRecent() {
        var box = $('[data-recent]');
        if (!box) { return; }
        var list = LIVE && !cart.length ? recentOrders() : [];
        box.hidden = !list.length;
        var ul = $('[data-recent-list]', box);
        ul.textContent = '';
        list.forEach(function (o) {
            var li = el('li');
            var a = el('a', { href: o.p, class: 'sf-recent__link' });
            a.appendChild(el('strong', {}, (o.p.indexOf('/booking/') === 0 ? 'Booking ' : 'Order ') + o.n));
            a.appendChild(el('span', {}, new Date(o.t).toLocaleDateString('en-NG', { day: 'numeric', month: 'short' })));
            li.appendChild(a);
            ul.appendChild(li);
        });
    }
    function setLineQty(key, qty) {
        cart = cart.filter(function (l) {
            if (l.key !== key) { return true; }
            l.qty = Math.min(999, qty);
            return qty > 0;
        });
        saveCart();
    }

    function renderCart() {
        var count = cartCount();
        var total = money(cartTotal());
        $$('[data-bag-count]').forEach(function (b) { b.textContent = count; b.hidden = count === 0; });
        $$('.sf-bagbtn').forEach(function (b) {
            b.setAttribute('aria-label', (S.look === 'market' ? 'Your order, ' : 'Your bag, ') + plural(count, 'item'));
        });
        $$('[data-bag-total]').forEach(function (t) { t.textContent = total; });
        $$('[data-bag-count-text]').forEach(function (t) { t.textContent = plural(count, 'item'); });
        var bar = $('[data-bagbar]');
        if (bar) { bar.hidden = count === 0; }
        root.classList.toggle('has-bagbar', count > 0);

        // Market's steppers show how many of each plain product are in the order
        $$('[data-stepper]').forEach(function (stepper) {
            var line = cart.filter(function (l) { return l.key === stepper.dataset.stepper + '|'; })[0];
            var add = stepper.parentNode.querySelector('[data-add]');
            stepper.hidden = !line;
            if (add) { add.hidden = !!line; }
            stepper.querySelector('output').textContent = line ? line.qty : 0;
        });

        var list = $('[data-bag-list]');
        if (!list) { return; }
        list.textContent = '';
        cart.forEach(function (line) {
            var item = S.items[String(line.id)];
            var li = el('li', { class: 'sf-bagline' });
            var media = el('span', { class: 'sf-bagline__media' });
            if (item.img.length) {
                media.appendChild(el('img', { src: item.img[0].src, alt: '', loading: 'lazy' }));
            } else {
                var ph = el('span', { class: 'sf-media sf-ph sf-ph--' + (item.id % 4) });
                ph.appendChild(el('span', { class: 'sf-ph__mark' }, initials(item.name)));
                media.appendChild(ph);
            }
            var body = el('div', { class: 'sf-bagline__body' });
            body.appendChild(el('span', { class: 'sf-bagline__name' }, line.name));
            var meta = [line.variant, line.note ? 'Note: ' + line.note : ''].filter(Boolean).join(' · ');
            if (meta) { body.appendChild(el('span', { class: 'sf-bagline__meta' }, meta)); }
            var row = el('div', { class: 'sf-bagline__row' });
            var stepper = el('div', { class: 'sf-stepper' });
            var minus = el('button', { type: 'button', 'aria-label': line.qty === 1 ? 'Remove ' + line.name : 'One fewer ' + line.name, 'data-line': line.key, 'data-line-step': '-1' }, '−');
            var out = el('output', { 'aria-live': 'polite' }, String(line.qty));
            var plus = el('button', { type: 'button', 'aria-label': 'One more ' + line.name, 'data-line': line.key, 'data-line-step': '1' }, '+');
            stepper.appendChild(minus);
            stepper.appendChild(out);
            stepper.appendChild(plus);
            row.appendChild(stepper);
            row.appendChild(el('span', { class: 'sf-bagline__price' }, money(line.price * line.qty)));
            body.appendChild(row);
            li.appendChild(media);
            li.appendChild(body);
            list.appendChild(li);
        });
        var empty = $('[data-bag-empty]');
        var foot = $('[data-bag-foot]');
        if (empty) { empty.hidden = cart.length > 0; }
        if (foot) { foot.hidden = cart.length === 0; }
        renderRecent();
    }

    /* ── Product sheet ───────────────────────────── */

    var sheet = $('#sf-product');
    var current = null;
    var pushed = false;

    function variantString(form) {
        return $$('fieldset', form).map(function (fs) {
            var picked = $('input:checked', fs);
            return picked ? fs.dataset.group + ': ' + picked.value : '';
        }).filter(Boolean).join(', ');
    }
    function updateSheetWa() {
        var link = $('[data-p-wa]', sheet);
        if (!link || !current) { return; }
        var choice = variantString($('[data-p-variants]', sheet));
        var text = 'Hello ' + S.name + ', I found ' + current.name + (choice ? ' (' + choice + ')' : '') + ' on your shop. Is it available?';
        link.href = 'https://wa.me/' + S.wa + '?text=' + encodeURIComponent(text);
    }
    function updateSheetPrice() {
        var el2 = $('[data-p-price]', sheet);
        if (!el2 || !current) { return; }
        if (current.price > 0) {
            el2.textContent = money(linePrice(current, variantString($('[data-p-variants]', sheet)))) + unitText(current.unit);
        } else {
            el2.textContent = 'Ask for the price';
        }
    }

    function openProduct(id, opts) {
        var item = S.items[String(id)];
        if (!item || !sheet) { return; }
        opts = opts || {};
        current = item;
        current.qty = 1;

        var gallery = $('[data-p-gallery]', sheet);
        gallery.textContent = '';
        var images = item.img.length ? item.img : [null];
        images.forEach(function (img, i) {
            var slide = el('div', { class: 'sf-product__slide' });
            if (img) {
                var picture = el('picture');
                if (img.set) { picture.appendChild(el('source', { type: 'image/webp', srcset: img.set, sizes: '(min-width: 720px) 440px, 100vw' })); }
                picture.appendChild(el('img', { src: img.src, width: img.w, height: img.h, alt: item.name + (images.length > 1 ? ', photo ' + (i + 1) + ' of ' + images.length : ''), decoding: 'async' }));
                slide.appendChild(picture);
            } else {
                var ph = el('span', { class: 'sf-ph sf-ph--' + (item.id % 4) });
                ph.appendChild(el('span', { class: 'sf-ph__mark' }, initials(item.name)));
                slide.appendChild(ph);
            }
            if (images.length > 1) { slide.appendChild(el('span', { class: 'sf-product__count', 'aria-hidden': 'true' }, (i + 1) + ' of ' + images.length)); }
            gallery.appendChild(slide);
        });
        gallery.scrollLeft = 0;

        $('[data-p-name]', sheet).textContent = item.name;
        var price = $('[data-p-price]', sheet);
        price.textContent = item.price > 0 ? money(item.price) + unitText(item.unit) : 'Ask for the price';
        var desc = $('[data-p-desc]', sheet);
        desc.textContent = item.desc || '';
        desc.style.whiteSpace = 'pre-line';

        var variants = $('[data-p-variants]', sheet);
        variants.textContent = '';
        item.variants.forEach(function (group, gi) {
            var fs = el('fieldset');
            fs.dataset.group = group.name;
            fs.appendChild(el('legend', {}, group.name));
            var wrap = el('div', { class: 'sf-choices' });
            group.options.forEach(function (option, oi) {
                var label = el('label', { class: 'sf-choice' });
                var input = el('input', { type: 'radio', name: 'sf-v' + gi, value: option });
                var preset = opts.preset && opts.preset[group.name];
                if (preset ? preset === option : oi === 0) { input.checked = true; }
                label.appendChild(input);
                var optText = el('span', {}, option);
                var d = group.deltas && group.deltas[option];
                if (d) { optText.appendChild(el('small', { class: 'sf-choice__delta' }, (d > 0 ? '+' : '−') + money(Math.abs(d)))); }
                label.appendChild(optText);
                wrap.appendChild(label);
            });
            fs.appendChild(wrap);
            variants.appendChild(fs);
        });
        $('[data-p-note]', sheet).value = '';
        $('[data-p-qty]', sheet).textContent = '1';
        // Booked, not bought: a link to its booking page instead of the bag
        var book = item.book;
        $('[data-p-note]', sheet).closest('.sf-field').hidden = !!book;
        $('.sf-product__buy', sheet).hidden = !!book;
        variants.hidden = !!book;
        var bookLink = $('[data-p-book]', sheet);
        var bookText = $('[data-p-book-text]', sheet);
        bookLink.hidden = !book;
        bookText.hidden = !book;
        if (book) {
            bookLink.href = LIVE ? book.url : '#item-' + item.id;
            bookLink.lastElementChild.textContent = book.label;
            bookText.textContent = book.text;
        }
        updateSheetWa();
        updateSheetPrice();

        if (opts.push !== false && LIVE) {
            history.pushState({ sfItem: item.id }, '', item.path || '#item-' + item.id);
            pushed = true;
        }
        if (LIVE) { document.title = item.name + ' — ' + S.name; }
        if (!sheet.open) { sheet.showModal(); }
        sheet.scrollTop = 0;
    }
    function closeProduct() {
        if (pushed && history.state && history.state.sfItem) {
            pushed = false;
            history.back();   // popstate closes the sheet
            return;
        }
        pushed = false;
        if (sheet && sheet.open) { sheet.close(); }
        document.title = baseTitle;
        if (/^#item-\d+$/.test(location.hash)) { history.replaceState(history.state, '', location.pathname + location.search); }
    }
    window.addEventListener('popstate', function (e) {
        if (sheet && sheet.open) {
            pushed = false;
            sheet.close();
            document.title = baseTitle;
        } else if (e.state && e.state.sfItem) {
            openProduct(e.state.sfItem, { push: false });
        }
    });

    if (sheet) {
        sheet.addEventListener('cancel', function (e) { e.preventDefault(); closeProduct(); });
        sheet.addEventListener('click', function (e) { if (e.target === sheet) { closeProduct(); } });
        sheet.addEventListener('change', function () { updateSheetWa(); updateSheetPrice(); });
        $('[data-p-add]', sheet).addEventListener('click', function () {
            if (!current) { return; }
            var qty = parseInt($('[data-p-qty]', sheet).textContent, 10) || 1;
            addLine(current.id, qty, variantString($('[data-p-variants]', sheet)), $('[data-p-note]', sheet).value.trim().slice(0, 255));
            closeProduct();
        });
        $$('[data-p-step]', sheet).forEach(function (b) {
            b.addEventListener('click', function () {
                var out = $('[data-p-qty]', sheet);
                out.textContent = String(Math.max(1, Math.min(999, (parseInt(out.textContent, 10) || 1) + parseInt(b.dataset.pStep, 10))));
            });
        });
    }

    /* ── Bag and checkout ────────────────────────── */

    var bag = $('#sf-bag');
    var steps = ['bag', 'details', 'delivery', 'pay', 'done'];
    var step = 'bag';

    function showStep(name) {
        step = name;
        $$('[data-step]', bag).forEach(function (s) { s.hidden = s.dataset.step !== name; });
        var back = $('[data-bag-back]', bag);
        back.hidden = ['details', 'delivery', 'pay'].indexOf(name) === -1;
        var titles = { bag: S.look === 'market' ? 'Your order' : 'Your bag', details: 'Check out', delivery: 'Check out', pay: 'Check out', done: 'Thank you' };
        $('[data-bag-title]', bag).textContent = titles[name];
        $$('[data-step-error]', bag).forEach(function (e) { e.textContent = ''; });
        var focus = name === 'details' ? $('[name="customer_name"]', bag)
            : name === 'done' ? $('[data-done-title]', bag)
            : $('[data-step="' + name + '"] .sf-step__count', bag);
        if (focus && name !== 'bag') { setTimeout(function () { focus.focus(); }, 30); }
        bag.scrollTop = 0;
    }
    function openBag() {
        if (!bag) { return; }
        renderCart();
        showStep('bag');
        if (!bag.open) { bag.showModal(); }
    }
    function stepError(name, text) {
        var box = $('[data-step="' + name + '"] [data-step-error]', bag);
        if (box) { box.textContent = text; }
    }
    function field(name) {
        var input = $('[name="' + name + '"]', bag);
        return input ? input.value.trim() : '';
    }

    if (bag) {
        bag.addEventListener('click', function (e) {
            if (e.target === bag) { bag.close(); return; }
            var go = e.target.closest('[data-go]');
            if (go) {
                if (!cart.length) { return; }
                showStep(go.dataset.go);
                return;
            }
            if (e.target.closest('[data-bag-back]')) {
                showStep(steps[Math.max(0, steps.indexOf(step) - 1)]);
                return;
            }
            var lineStep = e.target.closest('[data-line-step]');
            if (lineStep) {
                var line = cart.filter(function (l) { return l.key === lineStep.dataset.line; })[0];
                if (line) { setLineQty(line.key, line.qty + parseInt(lineStep.dataset.lineStep, 10)); }
                return;
            }
            if (e.target.closest('[data-place-wa]')) {
                placeOrder('whatsapp');
            }
        });
        $$('input[name="fulfillment_type"]', bag).forEach(function (r) {
            r.addEventListener('change', function () {
                $('[data-address]', bag).hidden = !$('input[name="fulfillment_type"][value="delivery"]', bag).checked;
            });
        });
        $('[data-step="details"]', bag).addEventListener('submit', function (e) {
            e.preventDefault();
            var digits = field('customer_phone').replace(/\D/g, '');
            if (!field('customer_name')) { stepError('details', 'Type your name.'); return; }
            if (digits.length < 7 || digits.length > 15) { stepError('details', 'Type a phone number the shop can reach you on, like 0803 123 4567.'); return; }
            var email = field('customer_email');
            if (email && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) { stepError('details', 'Check the email address, or leave it empty.'); return; }
            showStep('delivery');
        });
        $('[data-step="delivery"]', bag).addEventListener('submit', function (e) {
            e.preventDefault();
            if ($('input[name="fulfillment_type"][value="delivery"]', bag).checked && !field('delivery_address')) {
                stepError('delivery', 'Type the address we should deliver to.');
                return;
            }
            showStep('pay');
        });
        $('[data-step="pay"]', bag).addEventListener('submit', function (e) {
            e.preventDefault();
            placeOrder('cart_pop');
        });
    }

    var placing = false;
    function placeOrder(channel) {
        if (placing || !cart.length) { return; }
        if (!LIVE) { stepError('pay', PREVIEW_NOTE); return; }
        var fd = new FormData();
        fd.append('channel', channel);
        ['customer_name', 'customer_phone', 'customer_email', 'delivery_address', 'customer_note', 'website'].forEach(function (n) { fd.append(n, field(n)); });
        var delivery = $('input[name="fulfillment_type"][value="delivery"]', bag);
        fd.append('fulfillment_type', delivery && delivery.checked ? 'delivery' : 'pickup');
        fd.append('items', JSON.stringify(cart.map(function (l) {
            return { product_id: l.id, quantity: l.qty, variant_selection: l.variant, item_note: l.note };
        })));
        var bank = $('input[name="bank_id"]:checked', bag);
        if (channel === 'cart_pop') {
            fd.append('payment_method', bank ? 'bank_transfer' : 'none');
            if (bank) { fd.append('bank_id', bank.value); }
            var proof = $('input[name="payment_proof"]', bag);
            if (proof && proof.files && proof.files[0]) { fd.append('payment_proof', proof.files[0]); }
        }
        var buttons = $$('[data-place], [data-place-wa]', bag);
        buttons.forEach(function (b) { b.disabled = true; });
        placing = true;
        stepError('pay', '');
        fetch(S.urls.checkout, { method: 'POST', body: fd, credentials: 'omit' })
            .then(function (r) { return r.json().catch(function () { return { error: 'Something went wrong. Please try again.' }; }); })
            .then(function (json) {
                if (!json.success) {
                    // Items the shop no longer sells leave the bag, so the
                    // shopper sees what they would really order (W8)
                    if (json.unavailable && json.unavailable.length) {
                        cart = cart.filter(function (l) { return json.unavailable.indexOf(Number(l.id)) === -1; });
                        saveCart();
                        renderCart();
                    }
                    stepError('pay', json.error || 'Something went wrong. Please try again.');
                    return;
                }
                var lines = cart.map(function (l, i) {
                    return (i + 1) + '. ' + l.name + ' x' + l.qty + (l.variant ? ' (' + l.variant + ')' : '') + (l.note ? ' [' + l.note + ']' : '');
                });
                var total = money(json.total);
                var trackPath = '';
                if (json.order_url) {
                    try { trackPath = new URL(json.order_url, location.href).pathname; } catch (e) { trackPath = ''; }
                    rememberOrder(json.order_number, trackPath);
                }
                cart = [];
                saveCart();
                // The next order in this visit starts clean: no old payment
                // screenshot or note goes with it (W10)
                var proofInput = $('input[name="payment_proof"]', bag);
                if (proofInput) { proofInput.value = ''; proofInput.dispatchEvent(new Event('change', { bubbles: true })); }
                var noteInput = $('[name="customer_note"]', bag);
                if (noteInput) { noteInput.value = ''; }
                $('[data-done-number]', bag).textContent = json.order_number;
                var track = $('[data-done-track]', bag);
                track.hidden = !trackPath;
                $('[data-done-keep]', bag).hidden = !trackPath;
                if (trackPath) { track.href = trackPath; }
                var wa = $('[data-done-wa]', bag);
                if (channel === 'whatsapp' && S.wa) {
                    var message = ['Hello ' + S.name + ', this is my order ' + json.order_number + ':', ''].concat(lines, ['', 'Total: ' + total, 'Name: ' + field('customer_name')],
                        json.order_url ? ['Follow it here: ' + json.order_url] : []);
                    wa.href = 'https://wa.me/' + S.wa + '?text=' + encodeURIComponent(message.join('\n'));
                    wa.hidden = false;
                    $('[data-done-text]', bag).textContent = 'Send it to ' + S.name + ' on WhatsApp so they can confirm it with you.';
                } else {
                    wa.hidden = true;
                    $('[data-done-text]', bag).textContent = bank
                        ? S.name + ' will check your transfer and contact you on the number you gave.'
                        : S.name + ' will contact you on the number you gave.';
                }
                showStep('done');
            })
            .catch(function () { stepError('pay', 'We couldn\'t reach the shop. Check your connection and try again.'); })
            .then(function () {
                placing = false;
                buttons.forEach(function (b) { b.disabled = false; });
            });
    }

    /* ── Filters, search ─────────────────────────── */

    var catalogue = $('[data-catalogue]');
    var filter = '';
    var query = '';
    function applyFilters() {
        if (!catalogue) { return; }
        var shown = 0;
        $$('[data-item]', catalogue).forEach(function (item) {
            var ok = (!filter || item.dataset.cat === filter) && (!query || item.dataset.text.indexOf(query) !== -1);
            item.hidden = !ok;
            if (ok) { shown++; }
        });
        $$('[data-group]', catalogue).forEach(function (g) { g.hidden = !$('[data-item]:not([hidden])', g); });
        var ticket = $('.sf-ticket', catalogue);
        if (ticket) { ticket.hidden = !$('[data-item]:not([hidden])', ticket); }
        $$('[data-filter]', catalogue).forEach(function (b) { b.setAttribute('aria-pressed', String(b.dataset.filter === filter)); });
        var none = $('[data-no-match]', catalogue);
        if (none) { none.hidden = shown > 0; }
    }
    function setFilter(value) { filter = value; applyFilters(); }

    var search = $('[data-search]');
    if (search) {
        search.addEventListener('input', function () {
            query = search.value.trim().toLowerCase();
            applyFilters();
        });
    }

    /* ── Clicks ──────────────────────────────────── */

    document.addEventListener('click', function (e) {
        var t = e.target;
        var open = t.closest('[data-open]');
        if (open) {
            // A new tab or window gets the product's own page
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button > 0) { return; }
            e.preventDefault();
            openProduct(open.dataset.open);
            return;
        }
        var shareUrl = t.closest('[data-share-url]');
        if (shareUrl) {
            shareLink(shareUrl.dataset.shareUrl, shareUrl.dataset.shareTitle || S.name);
            return;
        }
        var shareBtn = t.closest('[data-share-item]');
        if (shareBtn) {
            var shared = S.items[shareBtn.dataset.shareItem || (current && current.id)];
            if (shared) { shareLink(S.url + shared.path, shared.name + ' — ' + S.name); }
            return;
        }
        var statusBtn = t.closest('[data-status-item]');
        if (statusBtn) {
            var pictured = S.items[statusBtn.dataset.statusItem || (current && current.id)];
            if (pictured) { openStatus(pictured); }
            return;
        }
        var bookBtn = t.closest('[data-book]');
        if (bookBtn && !LIVE) {
            e.preventDefault();
            if (!sheet || !sheet.open) { openProduct(bookBtn.dataset.book); } else { toast(PREVIEW_NOTE); }
            return;
        }
        var again = t.closest('[data-reorder]');
        if (again) {
            var lines = [];
            try { lines = JSON.parse(again.dataset.reorder) || []; } catch (err) { lines = []; }
            if (addLines(lines)) { openBag(); } else { toast('These items aren\'t in the shop any more.'); }
            return;
        }
        var add = t.closest('[data-add]');
        if (add) {
            var item = S.items[add.dataset.add];
            if (!item) { return; }
            var card = add.closest('[data-item]');
            var size = card && $('[data-size][aria-pressed="true"]', card);
            if (!item.variants.length) {
                addLine(item.id, 1, '', '');
            } else if (size && item.variants.length === 1) {
                addLine(item.id, 1, item.variants[0].name + ': ' + size.dataset.size, '');
            } else {
                var preset = {};
                if (size) { preset[size.closest('[role=group]').getAttribute('aria-label')] = size.dataset.size; }
                openProduct(item.id, { preset: preset });
            }
            return;
        }
        var stepBtn = t.closest('[data-stepper] [data-step]');
        if (stepBtn) {
            var id = stepBtn.closest('[data-stepper]').dataset.stepper;
            var line = cart.filter(function (l) { return l.key === id + '|'; })[0];
            var change = parseInt(stepBtn.dataset.step, 10);
            if (line) { setLineQty(line.key, line.qty + change); } else if (change > 0) { addLine(id, 1, '', ''); }
            return;
        }
        if (t.closest('[data-open-bag]')) { openBag(); return; }
        if (t.closest('[data-open-menu]')) { openMenu(); return; }
        var close = t.closest('[data-close]');
        if (close) {
            var dialog = close.closest('dialog');
            if (dialog === sheet) { closeProduct(); } else if (dialog) { dialog.close(); }
            return;
        }
        var filterBtn = t.closest('[data-filter]');
        if (filterBtn) { setFilter(filterBtn.dataset.filter); return; }
        var filterGo = t.closest('[data-filter-go]');
        if (filterGo) { setFilter(filterGo.dataset.filterGo); return; }   // the link scrolls on its own
        if (t.closest('[data-clear-filters]')) {
            filter = '';
            query = '';
            if (search) { search.value = ''; }
            applyFilters();
            return;
        }
        var sizeBtn = t.closest('[data-size]');
        if (sizeBtn) {
            $$('[data-size]', sizeBtn.parentNode).forEach(function (b) { b.setAttribute('aria-pressed', String(b === sizeBtn)); });
            return;
        }
        var copy = t.closest('[data-copy]');
        if (copy) {
            copyText(copy.dataset.copy, 'Account number copied');
            return;
        }
        var yt = t.closest('[data-youtube]');
        if (yt) {
            var frame = el('iframe', {
                src: 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(yt.dataset.youtube) + '?autoplay=1&rel=0',
                title: 'Video', allow: 'autoplay; encrypted-media; picture-in-picture; fullscreen', allowfullscreen: ''
            });
            yt.parentNode.replaceChild(frame, yt);
            return;
        }
        // The request form is on the contact page; the link takes the
        // product's name there. On that page itself, fill it in here.
        var ask = t.closest('[data-quote]');
        if (ask) {
            if ($('[data-quote-form]')) { e.preventDefault(); prefillQuote('I would like a price for ' + ask.dataset.quote + '.', true); }
            return;
        }
        if (t.closest('[data-estimate-open]')) {
            var chosen = $('[data-estimator] input:checked');
            if (chosen) { openProduct(chosen.value); }
        }
    });

    function copyText(value, done) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(value).then(function () { toast(done); }, function () { toast(value); });
        } else {
            toast(value);
        }
    }
    function shareLink(url, title) {
        if (navigator.share) {
            navigator.share({ title: title, url: url }).catch(function () { /* closed */ });
        } else {
            copyText(url, 'Link copied. Paste it in a chat to share it.');
        }
    }

    function prefillQuote(text, focus) {
        var message = $('[data-quote-form] textarea[name="message"]');
        if (!message) { return; }
        if (!message.value.trim()) { message.value = text; }
        var card = $('#sf-quote');
        if (card) { card.scrollIntoView({ block: 'start', behavior: 'smooth' }); }
        if (focus) { setTimeout(function () { message.focus(); }, 350); }
    }

    var estimator = $('[data-estimator]');
    if (estimator) {
        estimator.addEventListener('change', function (e) {
            var input = e.target;
            if (input.name !== 'sf-estimate') { return; }
            $('[data-estimate-price]', estimator).textContent = input.dataset.price;
            $('[data-estimate-unit]', estimator).textContent = input.dataset.unit;
            var quoteLink = $('[data-estimate-quote]', estimator);
            if (quoteLink) { quoteLink.href = S.urls.contact.replace(/#.*$/, '') + '#quote=' + encodeURIComponent(input.dataset.name); }
        });
    }

    /* ── Quote form ──────────────────────────────── */

    var quote = $('[data-quote-form]');
    if (quote) {
        quote.addEventListener('submit', function (e) {
            e.preventDefault();
            var status = $('[data-form-status]', quote);
            var say = function (text, bad) { status.textContent = text; status.classList.toggle('is-error', !!bad); };
            if (!LIVE) { say(PREVIEW_NOTE, true); return; }
            var name = quote.elements.name.value.trim();
            var phone = quote.elements.phone.value.trim();
            var message = quote.elements.message.value.trim();
            if (!name || !phone || !message) { say('Fill in your name, phone number and what you need.', true); return; }
            var button = $('button[type=submit]', quote);
            button.disabled = true;
            say('Sending…');
            fetch(S.urls.quote, { method: 'POST', body: new FormData(quote), credentials: 'omit' })
                .then(function (r) { return r.json().catch(function () { return { error: 'Something went wrong. Please try again.' }; }); })
                .then(function (json) {
                    if (json.success) {
                        quote.reset();
                        say('Sent. ' + S.name + ' will reply on the number you gave.');
                    } else {
                        say(json.error || 'Something went wrong. Please try again.', true);
                    }
                })
                .catch(function () { say('We couldn\'t reach the shop. Check your connection and try again.', true); })
                .then(function () { button.disabled = false; });
        });
    }

    /* ── Hero video: only on a good connection ───── */

    var video = $('.sf-hero__video');
    if (video) {
        var conn = navigator.connection || {};
        var slow = conn.saveData || /(^|-)2g$|^3g$/.test(conn.effectiveType || '');
        var calm = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (!slow && !calm) {
            video.src = video.dataset.src;
            var play = video.play();
            if (play && play.then) {
                play.then(function () { video.classList.add('is-playing'); }, function () { /* the drawing stays */ });
            }
        }
    }

    /* ── The menu ────────────────────────────────── */

    var menu = $('#sf-menu');
    function openMenu() {
        if (menu && !menu.open) { menu.showModal(); }
    }
    if (menu) {
        menu.addEventListener('click', function (e) {
            // A tap outside the panel, or on a link to this same page, closes it
            if (e.target === menu || e.target.closest('a[href^="#"]')) { menu.close(); }
        });
    }

    /* ── Header over a cover: clear, then frosted ── */
    // The announcement's height is taken off the cover, so the cover fills
    // exactly the first screen

    var announce = $('.sf-announce');
    function measureTop() { root.style.setProperty('--sf-top', (announce ? announce.offsetHeight : 0) + 'px'); }
    measureTop();
    window.addEventListener('resize', measureTop);

    var overHeader = $('.sf-header--over');
    var coverEl = $('[data-cover]');
    if (overHeader && coverEl && 'IntersectionObserver' in window) {
        new IntersectionObserver(function (entries) {
            overHeader.classList.toggle('is-solid', !entries[0].isIntersecting);
        }, { rootMargin: '-' + overHeader.offsetHeight + 'px 0px 0px 0px' }).observe(coverEl);
    }

    /* ── The cover: photos taking turns ──────────── */
    // A bar under the words fills while a photo shows; when it is full the
    // next photo fades in. Photos after the first load just before their
    // turn. Nothing moves for people who ask for less motion or save data:
    // they tap the bars. A pause button stops it, and it rests while the tab
    // or the cover is out of sight.

    if (coverEl) {
        var slides = $$('.sf-cover__slide', coverEl);
        var bars = $$('[data-slide-to]', coverEl);
        var pauseBtn = $('[data-cover-pause]', coverEl);
        var connection = navigator.connection || {};
        var thrifty = connection.saveData || /(^|-)2g$/.test(connection.effectiveType || '');
        var stillness = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var at = 0;
        var heldBy = { user: false, hidden: false, away: false, focus: false };

        var loadSlide = function (i) {
            var slide = slides[i];
            if (!slide) { return; }
            var img = $('img[data-src]', slide);
            if (img) {
                if (img.dataset.srcset) { img.srcset = img.dataset.srcset; }
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
                img.removeAttribute('data-srcset');
            }
            var video = $('video[data-src]', slide);
            if (video && !thrifty && !stillness) {
                video.src = video.dataset.src;
                video.removeAttribute('data-src');
            }
        };
        var playVideos = function () {
            slides.forEach(function (slide, i) {
                var video = $('video', slide);
                if (!video || !video.src) { return; }
                if (i === at) {
                    var playing = video.play();
                    if (playing && playing.then) { playing.then(function () { video.classList.add('is-playing'); }, function () { /* the cover colour shows */ }); }
                } else {
                    video.pause();
                }
            });
        };
        var show = function (i) {
            at = (i + slides.length) % slides.length;
            loadSlide(at);
            slides.forEach(function (slide, j) {
                slide.classList.toggle('is-on', j === at);
                if (bars.length) { slide.setAttribute('aria-hidden', String(j !== at)); }
            });
            bars.forEach(function (bar, j) {
                bar.classList.toggle('is-done', j < at);
                if (j === at) { bar.setAttribute('aria-current', 'true'); } else { bar.removeAttribute('aria-current'); }
            });
            playVideos();
            // The next photo arrives while this one shows
            if (slides.length > 1 && !thrifty) { setTimeout(function () { loadSlide(at + 1); }, 1200); }
        };
        var held = function () { return heldBy.user || heldBy.hidden || heldBy.away || heldBy.focus; };
        var updateHold = function () { coverEl.classList.toggle('is-paused', held()); };

        loadSlide(0);
        playVideos();
        if (bars.length > 1) {
            coverEl.style.setProperty('--sf-interval', (parseInt(coverEl.dataset.interval, 10) || 6500) + 'ms');
            if (!stillness && !thrifty) {
                coverEl.classList.add('is-running');
                setTimeout(function () { loadSlide(1); }, 1500);
            }
            coverEl.addEventListener('animationend', function (e) {
                if (e.animationName === 'sf-bar') { show(at + 1); }
            });
            bars.forEach(function (bar) {
                bar.addEventListener('click', function () { show(parseInt(bar.dataset.slideTo, 10)); });
            });
            if (pauseBtn) {
                pauseBtn.addEventListener('click', function () {
                    heldBy.user = !heldBy.user;
                    pauseBtn.setAttribute('aria-pressed', String(heldBy.user));
                    pauseBtn.setAttribute('aria-label', heldBy.user ? 'Play the photos' : 'Pause the photos');
                    updateHold();
                });
            }
            document.addEventListener('visibilitychange', function () { heldBy.hidden = document.hidden; updateHold(); });
            if ('IntersectionObserver' in window) {
                new IntersectionObserver(function (entries) { heldBy.away = !entries[0].isIntersecting; updateHold(); }).observe(coverEl);
            }
            // Someone reading or using the buttons isn't rushed
            coverEl.addEventListener('focusin', function () { heldBy.focus = true; updateHold(); });
            coverEl.addEventListener('focusout', function () { heldBy.focus = false; updateHold(); });
            // A swipe moves to the next or the last photo
            var startX = null;
            coverEl.addEventListener('pointerdown', function (e) { startX = e.isPrimary ? e.clientX : null; });
            coverEl.addEventListener('pointerup', function (e) {
                if (startX === null || e.target.closest('a, button')) { startX = null; return; }
                var dx = e.clientX - startX;
                startX = null;
                if (Math.abs(dx) > 48) { show(at + (dx < 0 ? 1 : -1)); }
            });
        }
    }

    /* ── Arriving at the list or the contact page ── */
    // /shop#cat=kaftans opens the list at that category; /contact#quote=Name
    // starts the request with that product

    function fromHash() {
        var cat = location.hash.match(/^#cat=(.+)$/);
        if (cat && catalogue) {
            var wanted = decodeURIComponent(cat[1]);
            if ($('[data-filter="' + wanted.replace(/["\\]/g, '') + '"]', catalogue)) { setFilter(wanted); }
        }
        var about = location.hash.match(/^#quote=(.+)$/);
        if (about) {
            prefillQuote('I would like a price for ' + decodeURIComponent(about[1]) + '.', false);
        }
    }
    fromHash();
    window.addEventListener('hashchange', fromHash);

    /* ── A product's page: buy box and photos ────── */

    var buy = $('[data-buy]');
    if (buy) {
        var buyItem = S.items[buy.dataset.buy];
        var buyQty = $('[data-buy-qty]', buy);
        var buyWa = $('[data-buy-wa]', buy);
        var buyPrice = $('[data-buy-price]', buy);
        var updateBuyWa = function () {
            if (!buyWa || !buyItem) { return; }
            var choice = variantString(buy);
            var text = 'Hello ' + S.name + ', I found ' + buyItem.name + (choice ? ' (' + choice + ')' : '') + ' on your shop. Is it available?';
            buyWa.href = 'https://wa.me/' + S.wa + '?text=' + encodeURIComponent(text);
        };
        var updateBuyPrice = function () {
            if (!buyPrice || !buyItem) { return; }
            var d = variantDelta(buyItem, variantString(buy));
            if (d && buyItem.price > 0) {
                buyPrice.textContent = 'Your choice: ' + money(round2(buyItem.price + d)) + unitText(buyItem.unit);
                buyPrice.hidden = false;
            } else {
                buyPrice.hidden = true;
            }
        };
        buy.addEventListener('change', function () { updateBuyWa(); updateBuyPrice(); });
        updateBuyPrice();
        buy.addEventListener('click', function (e) {
            var b = e.target.closest('[data-buy-step]');
            if (!b) { return; }
            buyQty.textContent = String(Math.max(1, Math.min(999, (parseInt(buyQty.textContent, 10) || 1) + parseInt(b.dataset.buyStep, 10))));
        });
        buy.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!buyItem) { return; }
            if (!LIVE) { toast(PREVIEW_NOTE); return; }
            var note = buy.elements.note ? buy.elements.note.value.trim().slice(0, 255) : '';
            addLine(buyItem.id, parseInt(buyQty.textContent, 10) || 1, variantString(buy), note);
            buyQty.textContent = '1';
            if (buy.elements.note) { buy.elements.note.value = ''; }
        });
    }

    var gallery = $('[data-gallery]');
    var thumbs = $$('[data-thumb]');
    if (gallery && thumbs.length) {
        thumbs.forEach(function (b) {
            b.addEventListener('click', function () {
                gallery.scrollTo({ left: gallery.clientWidth * parseInt(b.dataset.thumb, 10), behavior: 'smooth' });
            });
        });
        var syncTimer = null;
        gallery.addEventListener('scroll', function () {
            clearTimeout(syncTimer);
            syncTimer = setTimeout(function () {
                var at = Math.round(gallery.scrollLeft / Math.max(1, gallery.clientWidth));
                thumbs.forEach(function (b, i) { if (i === at) { b.setAttribute('aria-current', 'true'); } else { b.removeAttribute('aria-current'); } });
            }, 60);
        });
    }

    /* ── Booking page: calendar, times, details, deposit ── */
    // Free days come a month at a time from /book/{id}/free?month=, free
    // times a day at a time (?date=). The server checks the time again when
    // the booking is sent; if it was just taken, the calendar reloads.

    var bookForm = $('[data-bookform]');
    if (bookForm && S.booking) {
        var B = S.booking;
        var MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        var WEEKDAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        var cal = { month: B.first.slice(0, 7), cache: {} };
        var pick = { date: '', time: '', timeLabel: '', checkin: '', checkout: '' };
        var grid = $('[data-cal-grid]', bookForm);
        var calWait = $('[data-cal-wait]', bookForm);
        var timesBox = $('[data-times]', bookForm);
        var bookError = $('[data-book-error]', bookForm);
        var pad = function (n) { return (n < 10 ? '0' : '') + n; };
        var parseDay = function (s) { var p = s.split('-'); return new Date(+p[0], +p[1] - 1, +(p[2] || 1)); };
        var ymd = function (d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); };
        var addDays = function (s, n) { var d = parseDay(s); d.setDate(d.getDate() + n); return ymd(d); };
        var nightsBetween = function (a, b) { return Math.round((parseDay(b) - parseDay(a)) / 86400000); };
        var dayText = function (s) { var d = parseDay(s); return WEEKDAYS[d.getDay()] + ' ' + d.getDate() + ' ' + MONTHS[d.getMonth()]; };
        var shiftMonth = function (m, n) { var d = parseDay(m + '-01'); d.setMonth(d.getMonth() + n); return ymd(d).slice(0, 7); };
        var dayState = function (d) { var m = cal.cache[d.slice(0, 7)]; return m ? m[d] : 'open'; };
        // Every night from check-in to the night before check-out is free
        var nightsFree = function (a, b) {
            for (var d = a; d < b; d = addDays(d, 1)) { if (dayState(d) !== 'open') { return false; } }
            return true;
        };
        var units = function () {
            if (B.mode === 'nights') { return pick.checkin && pick.checkout ? nightsBetween(pick.checkin, pick.checkout) : 0; }
            return pick.date && (B.mode !== 'slot' || pick.time) ? 1 : 0;
        };
        var depositFor = function (total) {
            if (B.deposit.type === 'percent') { return Math.round(total * Math.min(100, B.deposit.value)) / 100; }
            if (B.deposit.type === 'fixed') { return Math.min(B.deposit.value, total); }
            return 0;
        };

        var drawCal = function () {
            var days = cal.cache[cal.month] || {};
            calWait.hidden = true;
            $('[data-cal-month]', bookForm).textContent = MONTHS[+cal.month.slice(5) - 1] + ' ' + cal.month.slice(0, 4);
            $('[data-cal-prev]', bookForm).disabled = cal.month <= B.first.slice(0, 7);
            $('[data-cal-next]', bookForm).disabled = cal.month >= B.last.slice(0, 7);
            grid.textContent = '';
            var lead = (parseDay(cal.month + '-01').getDay() + 6) % 7;   // weeks start on Monday
            for (var i = 0; i < lead; i++) { grid.appendChild(el('span', { class: 'sf-cal__pad', 'aria-hidden': 'true' })); }
            Object.keys(days).sort().forEach(function (d) {
                var state = days[d];
                var checkout = B.mode === 'nights' && pick.checkin && !pick.checkout && d > pick.checkin && nightsFree(pick.checkin, d);
                var b = el('button', { type: 'button', class: 'sf-cal__day is-' + state, 'data-day': d }, String(parseDay(d).getDate()));
                b.disabled = !(state === 'open' || checkout);
                if (checkout) { b.classList.add('can-end'); }
                var picked = d === pick.date || d === pick.checkin || d === pick.checkout;
                if (picked) { b.classList.add('is-picked'); }
                if (pick.checkin && pick.checkout && d > pick.checkin && d < pick.checkout) { b.classList.add('is-between'); }
                b.setAttribute('aria-pressed', String(picked));
                b.setAttribute('aria-label', dayText(d) + (state === 'full' ? (checkout ? ', free to leave' : ', fully booked') : state === 'closed' ? ', not open for booking' : ''));
                grid.appendChild(b);
            });
        };
        var loadMonth = function (m) {
            cal.month = m;
            if (cal.cache[m]) { drawCal(); return; }
            calWait.hidden = false;
            calWait.textContent = 'Finding free days…';
            grid.textContent = '';
            fetch(B.urls.free + '?month=' + m, { credentials: 'omit' })
                .then(function (r) { return r.json(); })
                .then(function (json) {
                    cal.cache[m] = json.days || {};
                    if (cal.month === m) { drawCal(); }
                })
                .catch(function () { calWait.textContent = 'We couldn\'t load the free days. Check your connection and try again.'; });
        };
        var loadTimes = function (d) {
            if (!timesBox) { return; }
            var list = $('[data-times-list]', timesBox);
            timesBox.hidden = false;
            list.textContent = '';
            $('[data-times-none]', timesBox).hidden = true;
            $('[data-times-title]', timesBox).textContent = 'Free times on ' + dayText(d);
            list.appendChild(el('p', { class: 'sf-cal__wait' }, 'Finding free times…'));
            fetch(B.urls.free + '?date=' + d, { credentials: 'omit' })
                .then(function (r) { return r.json(); })
                .then(function (json) {
                    if (pick.date !== d) { return; }
                    var times = json.times || [];
                    list.textContent = '';
                    $('[data-times-none]', timesBox).hidden = times.length > 0;
                    times.forEach(function (t) {
                        var label = el('label', { class: 'sf-choice' });
                        label.appendChild(el('input', { type: 'radio', name: 'sf-time', value: t.t, 'data-label': t.label }));
                        label.appendChild(el('span', {}, t.label));
                        list.appendChild(label);
                    });
                })
                .catch(function () { list.textContent = 'We couldn\'t load the free times. Check your connection and try again.'; });
        };
        var updatePicked = function () {
            var text = '';
            if (B.mode === 'nights') {
                if (pick.checkin && pick.checkout) { text = dayText(pick.checkin) + ' to ' + dayText(pick.checkout) + ', ' + plural(units(), 'night'); }
                else if (pick.checkin) { text = 'Arriving ' + dayText(pick.checkin) + '. Now tap the day you leave.'; }
            } else if (pick.date) {
                text = dayText(pick.date) + (B.mode === 'slot' ? (pick.time ? ' at ' + pick.timeLabel : '. Now pick a time.') : '');
            }
            $('[data-book-picked]', bookForm).textContent = text;
            var total = B.price * units();
            $('[data-book-total]', bookForm).textContent = units() ? money(total) : '—';
            var dep = $('[data-book-deposit]', bookForm);
            if (dep) { dep.textContent = units() ? money(depositFor(total)) : '—'; }
        };

        grid.addEventListener('click', function (e) {
            var b = e.target.closest('[data-day]');
            if (!b || b.disabled) { return; }
            var d = b.dataset.day;
            bookError.textContent = '';
            if (B.mode === 'nights') {
                if (pick.checkin && !pick.checkout && d > pick.checkin && nightsFree(pick.checkin, d)) {
                    pick.checkout = d;
                } else {
                    pick.checkin = d;
                    pick.checkout = '';
                }
            } else {
                pick.date = d;
                pick.time = '';
                loadTimes(d);
            }
            drawCal();
            updatePicked();
            var again = grid.querySelector('[data-day="' + d + '"]');
            if (again) { again.focus(); }
        });
        if (timesBox) {
            timesBox.addEventListener('change', function (e) {
                if (e.target.name !== 'sf-time') { return; }
                pick.time = e.target.value;
                pick.timeLabel = e.target.dataset.label;
                bookError.textContent = '';
                updatePicked();
            });
        }
        $('[data-cal-prev]', bookForm).addEventListener('click', function () { loadMonth(shiftMonth(cal.month, -1)); });
        $('[data-cal-next]', bookForm).addEventListener('click', function () { loadMonth(shiftMonth(cal.month, 1)); });

        var bookProblem = function () {
            if (B.mode === 'nights' && !(pick.checkin && pick.checkout)) { return 'Pick the day you arrive and the day you leave.'; }
            if (B.mode !== 'nights' && !pick.date) { return 'Pick a day first.'; }
            if (B.mode === 'slot' && !pick.time) { return 'Pick a time.'; }
            var f = bookForm.elements;
            if (!f.customer_name.value.trim()) { return 'Type your name.'; }
            var digits = f.customer_phone.value.replace(/\D/g, '');
            if (digits.length < 7 || digits.length > 15) { return 'Type a phone number the shop can reach you on, like 0803 123 4567.'; }
            var email = f.customer_email.value.trim();
            if (email && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) { return 'Check the email address, or leave it empty.'; }
            return '';
        };
        var sendingBooking = false;
        var sendBooking = function (channel) {
            if (sendingBooking) { return; }
            if (!LIVE) { bookError.textContent = PREVIEW_NOTE; return; }
            var problem = bookProblem();
            if (problem) { bookError.textContent = problem; return; }
            var fd = new FormData();
            fd.append('channel', channel);
            ['customer_name', 'customer_phone', 'customer_email', 'customer_note'].forEach(function (n) { fd.append(n, bookForm.elements[n].value.trim()); });
            fd.append('website', bookForm.elements.website ? bookForm.elements.website.value : '');   // empty unless a robot filled it
            if (B.mode === 'nights') {
                fd.append('checkin', pick.checkin);
                fd.append('checkout', pick.checkout);
            } else {
                fd.append('date', pick.date);
                if (pick.time) { fd.append('time', pick.time); }
            }
            var bank = $('input[name="bank_id"]:checked', bookForm);
            if (channel === 'site' && bank) {
                fd.append('bank_id', bank.value);
                var proof = bookForm.elements.payment_proof;
                if (proof && proof.files && proof.files[0]) { fd.append('payment_proof', proof.files[0]); }
            }
            var buttons = $$('[data-book-submit], [data-book-wa]', bookForm);
            buttons.forEach(function (b) { b.disabled = true; });
            sendingBooking = true;
            bookError.textContent = '';
            fetch(B.urls.submit, { method: 'POST', body: fd, credentials: 'omit' })
                .then(function (r) { return r.json().catch(function () { return { error: 'Something went wrong. Please try again.' }; }); })
                .then(function (json) {
                    if (!json.success) {
                        bookError.textContent = json.error || 'Something went wrong. Please try again.';
                        cal.cache = {};
                        loadMonth(cal.month);
                        if (B.mode === 'slot' && pick.date) { pick.time = ''; loadTimes(pick.date); updatePicked(); }
                        return;
                    }
                    showBooked(json, channel);
                })
                .catch(function () { bookError.textContent = 'We couldn\'t reach the shop. Check your connection and try again.'; })
                .then(function () {
                    sendingBooking = false;
                    buttons.forEach(function (b) { b.disabled = false; });
                });
        };
        var showBooked = function (json, channel) {
            var done = $('[data-book-done]');
            bookForm.hidden = true;
            done.hidden = false;
            $('[data-book-number]', done).textContent = json.booking_number;
            $('[data-book-done-when]', done).textContent = json.when;
            var path = '';
            try { path = json.booking_url ? new URL(json.booking_url, location.href).pathname : ''; } catch (e) { path = ''; }
            var track = $('[data-book-track]', done);
            track.hidden = !path;
            if (path) {
                track.href = path;
                rememberOrder(json.booking_number, path);
            }
            var wa = $('[data-book-done-wa]', done);
            if (channel === 'whatsapp' && S.wa) {
                var msg = 'Hello ' + S.name + ', I would like to book ' + S.items[String(B.id)].name + ' for ' + json.when + '. My booking number is ' + json.booking_number + '.'
                        + (json.booking_url ? ' Details: ' + json.booking_url : '');
                wa.href = 'https://wa.me/' + S.wa + '?text=' + encodeURIComponent(msg);
                wa.hidden = false;
                $('[data-book-done-text]', done).textContent = 'Send it to ' + S.name + ' on WhatsApp so they can confirm it with you.';
            } else {
                wa.hidden = true;
                $('[data-book-done-text]', done).textContent = json.deposit > 0
                    ? S.name + ' will check your deposit and confirm your booking.'
                    : S.name + ' will confirm your booking on the number you gave.';
            }
            done.scrollIntoView({ block: 'start' });
            $('[data-book-done-title]', done).focus();
        };
        bookForm.addEventListener('submit', function (e) { e.preventDefault(); sendBooking('site'); });
        var bookWa = $('[data-book-wa]', bookForm);
        if (bookWa) { bookWa.addEventListener('click', function () { sendBooking('whatsapp'); }); }

        loadMonth(cal.month);
        updatePicked();
    }

    /* ── Image for WhatsApp Status ───────────────── */
    // A 1080 × 1920 picture of a product in the shop's colours and fonts,
    // with the product's short address (/p/12) on it, made on the phone.

    var statusDialog = $('#sf-status');
    var statusUrl = null;
    if (statusDialog) {
        statusDialog.addEventListener('click', function (e) { if (e.target === statusDialog) { statusDialog.close(); } });
    }
    function cssVar(name) { return getComputedStyle(root).getPropertyValue(name).trim(); }
    function firstFamily(stack, fallback) {
        var m = (stack || '').match(/^\s*(?:'([^']+)'|"([^"]+)"|([^,]+))/);
        return m ? (m[1] || m[2] || m[3]).trim() : fallback;
    }
    // Photos come from the main site; draw them from this shop's own address
    // (the same files) so the canvas can be saved.
    function bestPhoto(item) {
        if (!item.img.length) { return null; }
        var img = item.img[0];
        var pick = img.src;
        if (img.set) {
            var sizes = img.set.split(',').map(function (part) {
                var bits = part.trim().split(/\s+/);
                return { url: bits[0], w: parseInt(bits[1], 10) || 0 };
            }).sort(function (a, b) { return a.w - b.w; });
            var big = sizes.filter(function (c) { return c.w >= 1080; })[0] || sizes[sizes.length - 1];
            if (big) { pick = big.url; }
        }
        try { return location.origin + new URL(pick, location.href).pathname; } catch (e) { return null; }
    }
    function loadImage(src) {
        return new Promise(function (resolve) {
            var img = new Image();
            img.onload = function () { resolve(img); };
            img.onerror = function () { resolve(null); };
            img.src = src;
        });
    }
    function roundRect(c, x, y, w, h, r) {
        c.beginPath();
        c.moveTo(x + r, y);
        c.arcTo(x + w, y, x + w, y + h, r);
        c.arcTo(x + w, y + h, x, y + h, r);
        c.arcTo(x, y + h, x, y, r);
        c.arcTo(x, y, x + w, y, r);
        c.closePath();
    }
    function fitText(c, text, max) {
        if (c.measureText(text).width <= max) { return text; }
        while (text.length > 1 && c.measureText(text + '…').width > max) { text = text.slice(0, -1); }
        return text.replace(/\s+$/, '') + '…';
    }
    function wrapText(c, text, max, most) {
        var lines = [];
        var line = '';
        text.split(/\s+/).forEach(function (word) {
            var next = line ? line + ' ' + word : word;
            if (line && c.measureText(next).width > max) { lines.push(line); line = word; } else { line = next; }
        });
        if (line) { lines.push(line); }
        if (lines.length > most) {
            lines = lines.slice(0, most);
            lines[most - 1] = fitText(c, lines[most - 1] + '…', max);
        }
        return lines.map(function (l) { return fitText(c, l, max); });
    }
    function shortAddress(item) {
        return S.url.replace(/^https?:\/\//, '') + '/p/' + item.id;
    }
    function makeStatusImage(item) {
        var W = 1080, H = 1920;
        var canvas = document.createElement('canvas');
        canvas.width = W;
        canvas.height = H;
        var c = canvas.getContext('2d');
        var colour = {
            ground: cssVar('--sf-ground') || '#FFFFFF', ink: cssVar('--sf-ink') || '#111111', muted: cssVar('--sf-muted') || '#555555',
            accent: cssVar('--sf-accent') || '#111111', on: cssVar('--sf-on') || '#FFFFFF', surface: cssVar('--sf-surface') || '#F2F2F2'
        };
        var head = firstFamily(cssVar('--sf-head'), 'Georgia');
        var body = firstFamily(cssVar('--sf-body'), 'Arial');
        var weight = cssVar('--sf-head-weight') || '700';
        var photoUrl = bestPhoto(item);
        var fonts = document.fonts && document.fonts.load
            ? Promise.all([document.fonts.load(weight + ' 72px "' + head + '"'), document.fonts.load('700 44px "' + body + '"')]).catch(function () {})
            : Promise.resolve();
        return Promise.all([photoUrl ? loadImage(photoUrl) : Promise.resolve(null), fonts]).then(function (got) {
            var photo = got[0];
            c.fillStyle = colour.ground;
            c.fillRect(0, 0, W, H);
            c.textAlign = 'center';
            c.textBaseline = 'alphabetic';

            c.fillStyle = colour.ink;
            c.font = weight + ' 46px "' + head + '", Georgia, serif';
            c.fillText(fitText(c, S.name, 920), W / 2, 146);

            var px = 80, py = 210, pw = 920, ph = 1060;
            c.save();
            roundRect(c, px, py, pw, ph, 40);
            c.clip();
            c.fillStyle = colour.surface;
            c.fillRect(px, py, pw, ph);
            if (photo) {
                var scale = Math.max(pw / photo.naturalWidth, ph / photo.naturalHeight);
                var dw = photo.naturalWidth * scale, dh = photo.naturalHeight * scale;
                c.drawImage(photo, px + (pw - dw) / 2, py + (ph - dh) / 2, dw, dh);
            } else {
                c.fillStyle = colour.muted;
                c.font = weight + ' 240px "' + head + '", Georgia, serif';
                c.textBaseline = 'middle';
                c.fillText(initials(item.name), W / 2, py + ph / 2);
                c.textBaseline = 'alphabetic';
            }
            c.restore();

            c.fillStyle = colour.ink;
            c.font = weight + ' 70px "' + head + '", Georgia, serif';
            var y = 1376;
            wrapText(c, item.name, 920, 2).forEach(function (line) { c.fillText(line, W / 2, y); y += 82; });

            if (item.price > 0) {
                c.font = '700 52px "' + body + '", Arial, sans-serif';
                var label = fitText(c, money(item.price) + unitText(item.unit), 860);
                var pill = Math.min(940, c.measureText(label).width + 100);
                var top = y - 30;
                c.fillStyle = colour.accent;
                roundRect(c, (W - pill) / 2, top, pill, 104, 52);
                c.fill();
                c.fillStyle = colour.on;
                c.textBaseline = 'middle';
                c.fillText(label, W / 2, top + 54);
                c.textBaseline = 'alphabetic';
            }

            c.fillStyle = colour.muted;
            c.font = '600 36px "' + body + '", Arial, sans-serif';
            c.fillText('Order it at', W / 2, 1770);
            c.fillStyle = colour.ink;
            c.font = '700 46px "' + body + '", Arial, sans-serif';
            c.fillText(fitText(c, shortAddress(item), 980), W / 2, 1834);

            return new Promise(function (resolve) { canvas.toBlob(resolve, 'image/jpeg', 0.9); });
        });
    }
    function openStatus(item) {
        if (!statusDialog) { return; }
        var img = $('[data-status-img]', statusDialog);
        var wait = $('[data-status-wait]', statusDialog);
        var save = $('[data-status-save]', statusDialog);
        var share = $('[data-status-share]', statusDialog);
        img.hidden = true;
        save.hidden = true;
        share.hidden = true;
        wait.hidden = false;
        wait.textContent = 'Making your image…';
        if (!statusDialog.open) { statusDialog.showModal(); }
        makeStatusImage(item).then(function (blob) {
            if (!blob) { throw new Error('no image'); }
            if (statusUrl) { URL.revokeObjectURL(statusUrl); }
            statusUrl = URL.createObjectURL(blob);
            var fileName = S.shop + '-' + item.id + '.jpg';
            img.src = statusUrl;
            img.alt = item.name + ', with its price and link';
            img.hidden = false;
            wait.hidden = true;
            save.href = statusUrl;
            save.setAttribute('download', fileName);
            save.hidden = false;
            var file = typeof File === 'function' ? new File([blob], fileName, { type: 'image/jpeg' }) : null;
            if (file && navigator.canShare && navigator.canShare({ files: [file] })) {
                share.hidden = false;
                share.onclick = function () {
                    navigator.share({ files: [file], text: item.name + ' ' + S.url + item.path }).catch(function () { /* closed */ });
                };
            }
        }).catch(function () {
            wait.textContent = 'This phone couldn\'t make the image. Take a screenshot of the product instead.';
        });
    }

    /* ── Shop Studio preview ─────────────────────── */

    if (!LIVE) {
        window.addEventListener('message', function (e) {
            if (e.origin !== location.origin || !e.data || typeof e.data !== 'object') { return; }
            if (e.data.type === 'nfelia:theme') {
                var vars = e.data.vars || {};
                Object.keys(vars).forEach(function (k) {
                    if (/^--sf-[a-z-]+$/.test(k)) { root.style.setProperty(k, String(vars[k])); }
                });
                if (typeof e.data.dark === 'boolean') {
                    root.classList.toggle('is-dark', e.data.dark);
                    root.classList.toggle('is-light', !e.data.dark);
                }
                if (typeof e.data.pair === 'string' && /^[a-z]+$/.test(e.data.pair)) {
                    root.className = root.className.replace(/\bfont-[a-z]+\b/, 'font-' + e.data.pair);
                }
                var link = document.getElementById('sf-fonts');
                if (link && typeof e.data.fontsHref === 'string' && e.data.fontsHref.indexOf('https://fonts.googleapis.com/') === 0) {
                    link.href = e.data.fontsHref;
                }
            } else if (e.data.type === 'nfelia:scroll' && typeof e.data.id === 'string') {
                var target = document.getElementById(e.data.id);
                if (target) { target.scrollIntoView({ block: 'start' }); }
            }
        });
    }

    // In Shop Studio, a section under the pointer is outlined with its name,
    // and tapping it (anywhere but a link or button) opens it in Studio
    if (!LIVE && S.sections && window.parent !== window) {
        var editable = null;
        var editTag = el('span', { class: 'sf-editable__tag', 'aria-hidden': 'true' });
        editTag.hidden = true;
        document.body.appendChild(editTag);
        var sectionOf = function (node) {
            for (; node && node !== document.body; node = node.parentElement) {
                var type = node.id && node.id.indexOf('sf-') === 0 ? node.id.slice(3) : '';
                if (type && S.sections[type]) { return node; }
            }
            return null;
        };
        var mark = function (node) {
            if (node === editable) { return; }
            if (editable) { editable.classList.remove('sf-editable'); }
            editable = node;
            editTag.hidden = !node;
            if (!node) { return; }
            node.classList.add('sf-editable');
            var box = node.getBoundingClientRect();
            editTag.textContent = 'Edit: ' + S.sections[node.id.slice(3)];
            editTag.style.left = Math.max(8, box.left + 8) + 'px';
            editTag.style.top = Math.max(8, box.top + 8) + 'px';
        };
        document.addEventListener('mouseover', function (e) { mark(sectionOf(e.target)); });
        document.addEventListener('mouseleave', function () { mark(null); });
        window.addEventListener('scroll', function () { mark(null); }, { passive: true });
        document.addEventListener('click', function (e) {
            if (e.target.closest('a, button, input, select, textarea, label, summary, dialog')) { return; }
            var node = sectionOf(e.target);
            if (!node) { return; }
            e.preventDefault();
            e.stopPropagation();
            window.parent.postMessage({ type: 'nfelia:edit', section: node.id.slice(3) }, location.origin);
        }, true);
    }

    /* ── Start ───────────────────────────────────── */

    loadCart();
    renderCart();
    var deep = location.hash.match(/^#item-(\d+)$/);
    if (deep && S.items[deep[1]]) { openProduct(deep[1], { push: false }); }
    // Opening an order's or a booking's link keeps it in the bag's recent orders
    var orderPage = $('[data-order]') || $('[data-booking]');
    if (orderPage && LIVE) {
        rememberOrder(orderPage.dataset.order || orderPage.dataset.booking, location.pathname);
        renderRecent();
    }
})();
