/**
 * Nfelia — New order, Quick sale and Change items (views/orders/form.php).
 *
 * One Alpine component. It keeps the lines, works out the total and the
 * balance as things are typed, finds customers as their name or phone is
 * typed, and keeps a draft on this phone so a call or a lost signal never
 * loses an order half typed. The server checks everything again.
 */
function orderForm() {
    var D = JSON.parse(document.getElementById('order-form-data').textContent);
    var uid = 0;
    var NUM = /[^0-9.]/g;

    function num(v) { var n = parseFloat(String(v === undefined || v === null ? '' : v).replace(NUM, '')); return isFinite(n) ? n : 0; }
    function round2(n) { return Math.round(n * 100) / 100; }
    function line(src, product) {
        var p = product || null;
        return {
            key: ++uid, product_id: src.product_id || null, name: src.name || (p ? p.name : ''),
            quantity: String(src.quantity || '1'), unit_price: src.unit_price !== undefined && src.unit_price !== '' ? String(src.unit_price) : (p ? String(p.price) : ''),
            note: src.note || '', showNote: false, stock: p ? p.stock : null, unit: p ? p.unit : ''
        };
    }
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

        init: function () {
            var self = this;
            this.items = (D.old.items || []).map(function (it) { return line(it, it.product_id ? productById(it.product_id) : null); });
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
            if (this.mode === 'quick' && this.old.pay === 'now' && this.old.fulfillment === 'now') return 'Paid ' + this.money(this.paidNow());
            return 'Save order';
        },

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
        openPicker: function () {
            var self = this;
            this.pickQuery = ''; this.ownName = ''; this.ownPrice = '';
            setTimeout(function () { self.$refs.pickq && self.$refs.pickq.focus(); }, 120);
        },
        pickList: function () {
            var q = this.pickQuery.trim().toLowerCase();
            var list = q ? this.products.filter(function (p) { return p.name.toLowerCase().indexOf(q) !== -1; }) : this.products;
            return list.slice(0, 60);
        },
        addProduct: function (p) {
            for (var i = 0; i < this.items.length; i++) {
                if (this.items[i].product_id === p.id) { this.step(this.items[i], 1); this.closePicker(); return; }
            }
            this.items.push(line({ product_id: p.id, name: p.name, quantity: '1', unit_price: p.price }, p));
            this.closePicker();
        },
        addOwn: function () {
            if (!this.ownName.trim()) return;
            this.items.push(line({ name: this.ownName.trim(), quantity: '1', unit_price: this.ownPrice }));
            this.closePicker();
        },
        closePicker: function () {
            var dlg = document.getElementById('nf-pick');
            if (dlg && dlg.open) dlg.close();
        },
        step: function (it, d) {
            var q = round2(num(it.quantity) + d);
            it.quantity = this.fmtQty(q < 1 ? (num(it.quantity) > 1 ? 1 : num(it.quantity) || 1) : q);
        },
        removeItem: function (i) { this.items.splice(i, 1); },

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
        }
    };
}
