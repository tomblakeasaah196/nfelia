/**
 * Nfelia — New order and Change items (views/orders/form.php).
 *
 * One Alpine component. It keeps the lines, works out the total and the
 * balance as things are typed, finds customers as their name or phone is
 * typed, and keeps a draft on this phone so a call or a lost signal never
 * loses an order half typed. Items are chosen in the picker's item step
 * (a copy, the draft, saved back on Add or Save). The phone remembers the
 * last "in the shop or online" and payment choice, so each shop starts where
 * it usually is. The server checks everything again.
 */
function orderForm() {
    var D = JSON.parse(document.getElementById('order-form-data').textContent);
    var uid = 0;
    var NUM = /[^0-9.]/g;

    function num(v) { var n = parseFloat(String(v === undefined || v === null ? '' : v).replace(NUM, '')); return isFinite(n) ? n : 0; }
    function round2(n) { return Math.round(n * 100) / 100; }
    // Variant helpers: the chosen options' price changes, and the text that
    // names the choice ("Colour: Navy Blue, Size: XXL").
    function deltaSum(variants, choices) {
        var d = 0;
        (variants || []).forEach(function (g) {
            var lab = choices[g.name];
            (g.options || []).forEach(function (o) { if (o.label === lab) { d += num(o.delta); } });
        });
        return d;
    }
    function variantStr(variants, choices) {
        return (variants || []).map(function (g) { return g.name + ': ' + (choices[g.name] || ''); })
            .filter(function (s) { return s.slice(-2) !== ': '; }).join(', ');
    }
    function line(src, product) {
        var p = product || null;
        var variants = (p && p.variants) || [];
        var choices = {};
        // Start from a stored choice (editing), then fill any gap with the first option.
        if (src.variant) {
            String(src.variant).split(', ').forEach(function (tok) { var at = tok.indexOf(': '); if (at > 0) { choices[tok.slice(0, at)] = tok.slice(at + 2); } });
        }
        variants.forEach(function (g) { if (choices[g.name] === undefined) { choices[g.name] = g.options[0] ? g.options[0].label : ''; } });
        var base = p ? p.price : num(src.unit_price);
        var hasPrice = src.unit_price !== undefined && src.unit_price !== '';
        return {
            key: ++uid, product_id: src.product_id || null, name: src.name || (p ? p.name : ''),
            quantity: String(src.quantity || '1'),
            unit_price: hasPrice ? String(src.unit_price) : String(round2(base + deltaSum(variants, choices))),
            note: src.note || '', variant: src.variant || variantStr(variants, choices), variants: variants, choices: choices, base: base,
            showNote: false, stock: p ? p.stock : null, unit: p ? p.unit : '', photo: p ? p.photo : null
        };
    }
    // A line to change in the item step, without touching the one on the order
    function copyLine(it) { var c = JSON.parse(JSON.stringify(it)); c.choices = c.choices || {}; return c; }
    var store = {
        get: function (k) { try { return JSON.parse(localStorage.getItem(k) || 'null'); } catch (e) { return null; } },
        set: function (k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* not kept: next time starts from the default */ } }
    };
    function productById(id) {
        for (var i = 0; i < D.products.length; i++) { if (D.products[i].id === id) return D.products[i]; }
        return null;
    }

    return {
        mode: D.mode, today: D.today, products: D.products, old: D.old,
        customer: D.customer, walkIn: !!D.old.walk_in && !D.customer, newCustomer: !D.customer && D.old.customer_name !== '',
        query: '', found: [], items: [], showDiscount: num(D.old.discount) > 0, showNote: D.old.note !== '',
        whenPick: '', whenDate: '', payByPick: 'week', payByDate: '',
        pickQuery: '', ownName: '', ownPrice: '', problem: '', submitting: false,
        pickStep: 'list', draft: null, editing: -1,

        init: function () {
            var self = this;
            this.items = (D.old.items || []).map(function (it) {
                var l = line(it, it.product_id ? productById(it.product_id) : null);
                // Changing items: what this order holds already came off the stock
                if (self.mode === 'edit' && l.stock !== null) l.stock = round2(l.stock + num(it.quantity));
                return l;
            });
            // In the shop or online: what was sent, else what this phone did last time
            if (this.mode !== 'edit' && !this.old.sale) {
                var last = D.restore ? store.get(D.lastKey) : null;
                if (last && (last.sale === 'shop' || last.sale === 'online')) {
                    this.old.sale = last.sale;
                    if (last.sale === 'online') this.old.fulfillment = last.fulfillment === 'delivery' ? 'delivery' : 'pickup';
                    if (last.pay === 'now' || last.pay === 'later') this.old.pay = last.pay;   // a part payment is typed each time
                    if (/^(cash|bank_transfer|pos|mobile_money)$/.test(last.method || '')) this.old.method = last.method;
                } else {
                    this.old.sale = this.old.fulfillment === 'now' ? 'shop' : 'online';
                }
            }
            this.setWhen(D.old.deliver_by);
            if (D.old.pay_by) { this.payByPick = 'pick'; this.payByDate = D.old.pay_by; }
            if (this.mode === 'new' && !this.customer && !this.newCustomer) { this.$nextTick(function () { self.$refs.find && self.$refs.find.focus(); }); }

            // A draft left on this phone comes back, once
            if (D.draftKey && D.restore) {
                try {
                    var saved = JSON.parse(sessionStorage.getItem(D.draftKey) || 'null');
                    if (saved && saved.items && saved.items.length) {
                        this.items = saved.items.map(function (it) { return line(it, it.product_id ? productById(it.product_id) : null); });
                        Object.assign(this.old, saved.old || {});
                        this.customer = saved.customer || null;
                        this.walkIn = !!saved.walkIn && !this.customer;
                        this.newCustomer = !this.customer && (this.old.customer_name || '') !== '';
                        this.setWhen(this.old.deliver_by);
                        if (window.nfToast) window.nfToast('Your unsaved order is back.');
                    }
                } catch (e) { /* no storage here: nothing to bring back */ }
            }
            if (D.draftKey) {
                this.$watch('items', function () { self.saveDraft(); }, { deep: true });
                this.$watch('old', function () { self.saveDraft(); }, { deep: true });
                this.$watch('customer', function () { self.saveDraft(); });
            }
            this.$watch('old.sale', function (v) {
                if (v === 'shop') self.old.fulfillment = 'now';
                if (v === 'online' && self.old.fulfillment === 'now') self.old.fulfillment = 'pickup';
            });
            this.$watch('old.fulfillment', function (v) {
                if (v === 'delivery' && self.customer && !self.old.delivery_address) self.old.delivery_address = self.customer.address || '';
                if (v !== 'now' && self.payByPick === 'week' && self.deliverBy()) self.payByPick = 'when';
            });
            this.$watch('whenPick', function () { if (self.old.fulfillment !== 'now' && self.deliverBy() && self.payByPick === 'week') self.payByPick = 'when'; });
        },

        // ---------- Words and figures ----------
        num: num,
        money: function (n) {
            var v = round2(n), whole = Math.floor(Math.abs(v)), kobo = Math.round((Math.abs(v) - whole) * 100);
            return (v < 0 ? '−' : '') + D.currency + whole.toLocaleString('en-NG') + (kobo ? '.' + String(kobo).padStart(2, '0') : '');
        },
        fmtQty: function (q) { return String(round2(q)).replace(/\.0+$/, ''); },
        first: function (name) { return (name || '').trim().split(/\s+/)[0] || ''; },
        initials: function (name) {
            var w = (name || '').trim().split(/\s+/);
            return ((w[0] || '').charAt(0) + (w.length > 1 ? w[w.length - 1].charAt(0) : '')).toUpperCase() || '?';
        },
        stockWords: function (it) {
            if (it.stock === null || it.stock === undefined) return '';
            if (it.stock < num(it.quantity)) return 'Only ' + this.fmtQty(it.stock) + ' in stock';
            return this.fmtQty(it.stock) + ' in stock';
        },
        short: function (it) { return it.stock !== null && it.stock !== undefined && it.stock < num(it.quantity); },
        // Under an item's name: the chosen options, then its note
        itemWords: function (it) {
            var bits = (it.variants && it.variants.length)
                ? it.variants.map(function (g) { return it.choices[g.name] || ''; }).filter(Boolean)
                : (it.variant ? [it.variant] : []);
            if (it.note) bits.push('“' + it.note + '”');
            return bits.join(' · ');
        },
        lineTotal: function (it) { return round2(round2(num(it.quantity)) * round2(num(it.unit_price))); },   // as the server rounds them
        subtotal: function () { var s = 0, self = this; this.items.forEach(function (it) { s += self.lineTotal(it); }); return round2(s); },
        total: function () { return Math.max(0, round2(this.subtotal() - num(this.old.discount))); },
        kept: function () { return (this.customer && this.old.use_kept) ? Math.min(this.customer.kept || 0, this.total()) : 0; },
        paidNow: function () {
            if (this.mode === 'edit') return 0;
            if (this.old.pay === 'now') return round2(this.total() - this.kept());
            if (this.old.pay === 'part') return Math.min(num(this.old.paid_now), this.total());
            return 0;
        },
        balance: function () { return this.mode === 'edit' ? 0 : Math.max(0, round2(this.total() - this.kept() - this.paidNow())); },
        sumWords: function () {
            if (this.mode === 'edit') {
                var over = round2(D.paidSoFar - this.total());
                return over > 0 ? this.money(over) + ' paid over: you choose next' : (D.paidSoFar > 0 ? 'Balance ' + this.money(this.total() - D.paidSoFar) : 'New total');
            }
            if (!this.items.length) return 'No items yet';
            if (this.balance() > 0) return 'Balance ' + this.money(this.balance()) + ' to Debts';
            return this.old.pay === 'now' ? 'Paid in full' : 'Total';
        },
        buttonWords: function () {
            if (this.submitting) return 'Saving…';
            if (this.mode === 'edit') return 'Save changes';
            if (this.isQuick() && this.items.length) return 'Paid ' + this.money(this.paidNow());
            return 'Save order';
        },
        // In the shop, paid in full, taken now: a quick sale (its receipt is made at once)
        isQuick: function () { return this.mode !== 'edit' && this.old.sale === 'shop' && this.old.fulfillment === 'now' && this.old.pay === 'now'; },

        // ---------- Customer ----------
        search: function () {
            var self = this, q = this.query.trim();
            if (!q) { this.found = []; return; }
            fetch(D.searchUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : { customers: [] }; })
                .then(function (j) { if (self.query.trim() === q) self.found = j.customers || []; })
                .catch(function () { self.found = []; });
        },
        pickCustomer: function (c) {
            this.customer = c; this.query = ''; this.found = []; this.newCustomer = false; this.walkIn = false;
            if (c.kept > 0) this.old.use_kept = true;
            if (this.old.fulfillment === 'delivery' && !this.old.delivery_address) this.old.delivery_address = c.address || '';
        },
        clearCustomer: function () {
            var self = this;
            this.customer = null; this.old.use_kept = false; this.newCustomer = false;
            this.$nextTick(function () { self.$refs.find && self.$refs.find.focus(); });
        },
        startNew: function () {
            var q = this.query.trim();
            if (/^[+\d][\d\s-]{5,}$/.test(q)) { this.old.customer_phone = q; } else if (q) { this.old.customer_name = q; }
            this.newCustomer = true; this.query = ''; this.found = [];
        },

        // ---------- Items ----------
        sheet: function (open) {
            if (open) { if (window.nfOpenSheet) window.nfOpenSheet('nf-pick'); else { var d = document.getElementById('nf-pick'); if (d && !d.open) d.showModal(); } }
            else if (window.nfCloseSheet) window.nfCloseSheet('nf-pick');
            else { var dlg = document.getElementById('nf-pick'); if (dlg && dlg.open) dlg.close(); }
        },
        scrollSheetTop: function () { var dlg = document.getElementById('nf-pick'); if (dlg) dlg.scrollTop = 0; },
        openPicker: function () {
            var self = this;
            this.pickQuery = ''; this.ownName = ''; this.ownPrice = '';
            this.pickStep = 'list'; this.draft = null; this.editing = -1;
            this.sheet(true);
            setTimeout(function () { self.$refs.pickq && self.$refs.pickq.focus(); }, 120);
        },
        pickList: function () {
            var q = this.pickQuery.trim().toLowerCase();
            var list = q ? this.products.filter(function (p) { return p.name.toLowerCase().indexOf(q) !== -1; }) : this.products;
            return list.slice(0, 60);
        },
        // The item step: a photo, its colours and sizes as chips, how many, the price
        openItem: function (p) {
            this.draft = line({ product_id: p.id, name: p.name, quantity: '1' }, p);
            this.editing = -1; this.pickStep = 'item';
            this.$nextTick(this.scrollSheetTop);
        },
        editItem: function (i) {
            this.draft = copyLine(this.items[i]);
            this.editing = i; this.pickStep = 'item';
            this.sheet(true);
            this.$nextTick(this.scrollSheetTop);
        },
        backToList: function () { this.pickStep = 'list'; this.draft = null; this.editing = -1; },
        saveItem: function () {
            var d = this.draft;
            if (!d || !(d.name || '').trim()) return;
            d.name = d.name.trim();
            d.variant = d.variants && d.variants.length ? variantStr(d.variants, d.choices) : d.variant;
            if (this.editing >= 0) {
                this.items.splice(this.editing, 1, d);
            } else {
                // The same product with the same choice and price goes onto its line
                var same = null;
                for (var i = 0; i < this.items.length; i++) {
                    var it = this.items[i];
                    if (d.product_id && it.product_id === d.product_id && (it.variant || '') === (d.variant || '') && num(it.unit_price) === num(d.unit_price) && !it.note && !d.note) { same = it; break; }
                }
                if (same) same.quantity = this.fmtQty(round2(num(same.quantity) + num(d.quantity)));
                else this.items.push(d);
            }
            this.draft = null; this.editing = -1; this.pickStep = 'list';
            this.sheet(false);
        },
        onVariant: function (it) {
            it.variant = variantStr(it.variants, it.choices);
            it.unit_price = String(round2(num(it.base) + deltaSum(it.variants, it.choices)));
        },
        addOwn: function () {
            if (!this.ownName.trim()) return;
            this.items.push(line({ name: this.ownName.trim(), quantity: '1', unit_price: this.ownPrice }));
            this.sheet(false);
        },
        step: function (it, d) {
            var q = round2(num(it.quantity) + d);
            it.quantity = this.fmtQty(q < 1 ? (num(it.quantity) > 1 ? 1 : num(it.quantity) || 1) : q);
        },
        removeItem: function (i) {
            this.items.splice(i, 1);
            this.draft = null; this.editing = -1; this.pickStep = 'list';
            this.sheet(false);
        },

        // ---------- When, and pay by ----------
        setWhen: function (date) {
            if (!date) { this.whenPick = ''; this.whenDate = ''; return; }
            if (date === D.today) this.whenPick = 'today';
            else if (date === D.tomorrow) this.whenPick = 'tomorrow';
            else { this.whenPick = 'pick'; this.whenDate = date; }
        },
        deliverBy: function () {
            if (this.old.fulfillment === 'now') return '';
            return this.whenPick === 'today' ? D.today : this.whenPick === 'tomorrow' ? D.tomorrow : this.whenPick === 'pick' ? this.whenDate : '';
        },
        payBy: function () {
            if (this.balance() <= 0) return '';
            if (this.payByPick === 'when' && this.deliverBy()) return this.deliverBy() > D.today ? this.deliverBy() : D.week;
            if (this.payByPick === 'pick' && this.payByDate) return this.payByDate;
            return D.week;
        },

        // ---------- Saving ----------
        saveDraft: function () {
            if (!D.draftKey) return;
            try {
                if (!this.items.length && !this.customer && !this.old.customer_name) { sessionStorage.removeItem(D.draftKey); return; }
                sessionStorage.setItem(D.draftKey, JSON.stringify({ items: this.items, old: this.old, customer: this.customer, walkIn: this.walkIn }));
            } catch (e) { /* storage full or blocked: the form still works */ }
        },
        submit: function (e) {
            if (e.defaultPrevented) return;   // offline (shell.js): nothing is sent, so keep the draft
            this.problem = '';
            var named = this.items.filter(function (it) { return (it.name || '').trim() !== ''; });
            if (!named.length) { this.problem = 'Add at least one item.'; e.preventDefault(); return; }
            if (this.items.some(function (it) { return !(it.name || '').trim(); })) { this.problem = 'Give each item a name, or remove it.'; e.preventDefault(); return; }
            if (this.mode !== 'edit') {
                if (!this.customer && !this.walkIn && !this.newCustomer) {
                    this.problem = 'Choose the customer, or add a new one.'; e.preventDefault(); return;
                }
                if (this.newCustomer && !(this.old.customer_name || '').trim() && !(this.old.customer_phone || '').trim()) {
                    this.problem = 'Add the customer\'s name and phone.'; e.preventDefault(); return;
                }
                if (this.walkIn) { this.old.customer_name = ''; this.old.customer_phone = ''; this.old.customer_email = ''; }
                if (this.balance() > 0 && (this.walkIn || (!this.customer && !(this.old.customer_phone || '').trim()))) {
                    this.problem = 'A balance needs the customer\'s name and phone, so it can be followed up. Add them, or take the full amount now.';
                    e.preventDefault(); return;
                }
                if (this.old.pay === 'part' && (num(this.old.paid_now) <= 0 || num(this.old.paid_now) >= this.total())) {
                    this.problem = 'For a part payment, enter an amount below the total. For the whole amount, choose Paid now.';
                    e.preventDefault(); return;
                }
            }
            this.submitting = true;
            try { if (D.draftKey) sessionStorage.removeItem(D.draftKey); } catch (err) { /* nothing kept */ }
            if (this.mode !== 'edit') store.set(D.lastKey, { sale: this.old.sale, fulfillment: this.old.fulfillment, pay: this.old.pay, method: this.old.method });
        }
    };
}
