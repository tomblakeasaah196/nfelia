/**
 * Nfelia — Tasks & Calendar page (views/tasks/index.php).
 *
 * One Alpine component for both tabs. The server is the source of truth:
 * every change posts JSON and gets the refreshed Tasks tab back ("board"),
 * so the list never drifts from the database. The calendar fetches one
 * six-week grid at a time and is refetched after any change.
 *
 * Dates are plain YYYY-MM-DD strings handled in UTC, so a phone set to
 * another time zone still agrees with the server about what "today" is.
 */
function planner() {
    var D = JSON.parse(document.getElementById('planner-data').textContent);
    var BASE = window.Nfelia.url;
    var DAY = 86400000;
    var MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    var MON = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    var WD = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    var uid = 0;

    function ms(s) { var p = s.split('-').map(Number); return Date.UTC(p[0], p[1] - 1, p[2]); }
    function iso(t) { return new Date(t).toISOString().slice(0, 10); }
    function addDays(s, n) { return iso(ms(s) + n * DAY); }
    function between(a, b) { return Math.round((ms(b) - ms(a)) / DAY); }
    function firstName(n) { return (n || '').trim().split(/\s+/)[0] || ''; }
    function lagosDay() {
        try { return new Intl.DateTimeFormat('en-CA', { timeZone: 'Africa/Lagos', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date()); }
        catch (e) { return ''; }
    }
    function reducedMotion() { return window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches; }

    var ICONS = {
        deliver: 'M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12',
        money: 'M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z',
        order: 'M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z',
        restock: 'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z',
        quote: 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z'
    };

    return {
        today: D.today,
        tab: D.tab,
        board: D.board,
        customers: D.customers,
        staff: D.staff,
        templates: D.templates,
        me: D.me,
        prefs: D.prefs,
        appUrl: BASE,

        busy: false,
        seq: 0,
        inFlight: 0,
        nextBoard: null,
        nextBoardSeq: 0,
        mine: false,
        showDone: false,
        quick: { title: '', when: 'today', date: addDays(D.today, 2) },

        panel: null,
        lastFocus: null,
        form: {},
        formBase: '',
        err: {},
        custQuery: '',
        confirmDelete: false,
        confirmFrom: null,

        // A tick asks before it clears a task, so a mis-tap never quietly
        // removes something. The item waits here until the owner says yes.
        confirmDone: false,
        doneItem: null,
        doneInJob: false,
        doneFrom: null,

        plan: { template: null, steps: [] },
        job: {},
        jobSteps: [],
        jobLoading: false,

        viewMonth: (D.prefill.date || D.today).slice(0, 7),
        selected: D.prefill.date || D.today,
        cal: {},
        calLoading: false,

        pushState: 'unavailable',
        toast: { show: false, text: '', undo: null, error: false, label: 'Undo' },
        toastTimer: null,

        init: function () {
            var self = this;
            var p = D.prefill;
            if (p.task) {
                this.openItem(p.task);
            } else if (p.plan || (p.invoice && !p.new)) {
                this.openPlan(p);
            } else if (p.new) {
                this.openEditor({ kind: p.new, date: p.date || this.today, customer_id: p.customer, order: p.order });
            } else if (p.reminders) {
                this.openPanel('reminders');
            } else {
                this.restoreDraft();
            }
            if (p.task || p.plan || p.new || p.invoice || p.customer || p.date || p.reminders || p.order) {
                history.replaceState(null, '', BASE + '/tasks' + (this.tab === 'calendar' ? '?tab=calendar' : ''));
            }
            if (this.tab === 'calendar') this.loadMonth();
            this.checkPush();
            this.$watch('panel', function () { self.lockScroll(); });
            this.$watch('confirmDone', function () { self.lockScroll(); });
            this.$watch('form', function () { self.saveDraft(); });
            this.$watch('plan', function () { self.saveDraft(); });
            document.addEventListener('keydown', function (e) { if (e.key === 'Tab' && (self.panel || self.confirmDone)) self.trapFocus(e); });

            // A page left open overnight starts the new day fresh, unless a
            // sheet is open with something half typed. Compared with the
            // device's own date at load, so a phone with a wrong clock never
            // reloads in a loop.
            var loadedOn = lagosDay();
            var newDay = function () { if (loadedOn && !self.panel && lagosDay() !== loadedOn) location.reload(); };
            document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') newDay(); });
            setInterval(newDay, 300000);
        },

        /* ── Server ─────────────────────────────────── */

        api: function (path, body) {
            var self = this, seq = ++this.seq;
            this.busy = true;
            this.inFlight++;
            return fetch(BASE + '/tasks/' + path, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.Nfelia.csrf, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(body || {})
            }).catch(function () {
                throw new Error('No connection. Check your data and try again.');
            }).then(function (r) {
                if (r.redirected) throw new Error('You were signed out. Reload the page to sign in again.');
                return r.json().catch(function () { return null; }).then(function (data) {
                    if (!r.ok || !data || data.error) {
                        var e = new Error((data && data.error) || 'Something went wrong. Check your connection and try again.');
                        e.fields = data && data.fields;
                        throw e;
                    }
                    if (data.board && seq > self.nextBoardSeq) { self.nextBoard = data.board; self.nextBoardSeq = seq; }
                    return data;
                });
            }).finally(function () {
                // Two quick ticks: show the server's list once every change in
                // flight is answered, keeping the newest reply's list, so it
                // never flickers back through an older one.
                if (--self.inFlight > 0) return;
                self.busy = false;
                if (self.nextBoard) {
                    self.board = self.nextBoard;
                    self.nextBoard = null;
                    self.refreshCalendar();
                }
            });
        },

        get: function (url) {
            return fetch(BASE + url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .catch(function () { throw new Error('No connection. Check your data and try again.'); })
                .then(function (r) {
                    if (r.redirected) throw new Error('You were signed out. Reload the page to sign in again.');
                    return r.json().catch(function () { return {}; }).then(function (d) {
                        if (!r.ok || d.error) throw new Error(d.error || 'Could not load this. Try again.');
                        return d;
                    });
                });
        },

        // Background stays put whenever a sheet or a confirm is open.
        lockScroll: function () { document.body.style.overflow = (this.panel || this.confirmDone) ? 'hidden' : ''; },

        say: function (text, undo, error, label) {
            clearTimeout(this.toastTimer);
            this.toast = { show: true, text: text, undo: undo || null, error: !!error, label: label || 'Undo' };
            this.releaseToast(undo ? 6000 : 3500);
        },

        // While a finger or the keyboard is on the toast (its Undo), it stays.
        holdToast: function () { clearTimeout(this.toastTimer); },
        releaseToast: function (ms) {
            var self = this;
            clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(function () { self.toast.show = false; }, ms || 3000);
        },

        undo: function () {
            var fn = this.toast.undo;
            this.toast.show = false;
            if (fn) fn();
        },

        fail: function (e) {
            var fields = e.fields || {};
            var first = Object.keys(fields).map(function (k) { return fields[k]; }).filter(Boolean)[0];
            this.say(first || e.message, null, true);
        },

        focusFirstError: function () {
            var self = this;
            this.$nextTick(function () {
                var sheet = self.$refs.sheet, el = sheet && sheet.querySelector('[aria-invalid=true]');
                if (el && el.getAttribute('role') === 'radiogroup') el = el.querySelector('button');
                if (!el && self.err.steps) el = document.getElementById('pl-add-step');
                if (el) el.focus();
            });
        },

        /* ── Drafts ─────────────────────────────────── */

        // Half-typed work survives a reload: phones often drop a tab in the
        // background while someone checks WhatsApp. Saving, deleting or
        // closing the sheet clears it.
        draftKey: function () { return 'nfelia.planner.draft.' + this.me.id; },

        saveDraft: function () {
            var d = null;
            var changed = JSON.stringify(this.form) !== this.formBase;
            if (this.panel === 'editor' && changed && (String(this.form.title || '').trim() || String(this.form.notes || '').trim())) d = { panel: 'editor', form: this.form };
            if (this.panel === 'plan' && this.plan.template) d = { panel: 'plan', plan: this.plan };
            try {
                if (d) { d.at = Date.now(); localStorage.setItem(this.draftKey(), JSON.stringify(d)); }
                else localStorage.removeItem(this.draftKey());
            } catch (e) {}
        },

        clearDraft: function () { try { localStorage.removeItem(this.draftKey()); } catch (e) {} },

        restoreDraft: function () {
            var self = this, d = null;
            try { d = JSON.parse(localStorage.getItem(this.draftKey()) || 'null'); } catch (e) {}
            if (!d || !d.at || Date.now() - d.at > DAY) return;
            if (d.panel === 'editor' && d.form) { this.form = d.form; this.formBase = ''; this.openPanel('editor'); }
            else if (d.panel === 'plan' && d.plan) { this.plan = d.plan; this.openPanel('plan'); }
            else return;
            this.say('Your unsaved changes are back.', function () { self.closePanel(); }, false, 'Discard');
        },

        /* ── Tabs and lists ─────────────────────────── */

        setTab: function (t) {
            this.tab = t;
            history.replaceState(null, '', BASE + '/tasks' + (t === 'calendar' ? '?tab=calendar' : ''));
            if (t === 'calendar') this.loadMonth();
        },

        isMine: function (it) {
            return !!it.task_id && ((it.assignee && it.assignee.id === this.me.id) || (!it.assignee && it.created_by === this.me.id));
        },

        groups: function () {
            var self = this;
            if (this.tab === 'calendar') {
                return [{ key: 'day', label: '', items: this.dayItems(this.selected), empty: this.calLoading ? 'Loading…' : (this.dayItems(this.selected).length ? '' : 'Nothing on this day.') }];
            }
            var s = this.board.sections;
            var f = function (list) { return self.mine ? list.filter(function (it) { return self.isMine(it); }) : list; };
            var out = [];
            var late = f(s.late), today = f(s.today), soon = f(s.soon), undated = f(s.undated), done = f(s.done);
            if (late.length) out.push({ key: 'late', label: 'Late', items: late });
            out.push({ key: 'today', label: 'Today', items: today, empty: today.length ? '' : 'Nothing planned for today. Write down what you must do, and tick it off as you go.' });
            if (soon.length) out.push({ key: 'soon', label: 'Coming up', items: soon, byDay: true });
            if (undated.length) out.push({ key: 'undated', label: 'No date', items: undated });
            if (done.length) out.push({ key: 'done', label: 'Done today', items: done });
            return out;
        },

        jobs: function () {
            var self = this;
            return this.mine ? this.board.jobs.filter(function (j) { return self.isMine(j); }) : this.board.jobs;
        },

        jobPct: function (job) {
            return job.steps && job.steps.total ? Math.round(100 * job.steps.done / job.steps.total) : 0;
        },

        openItem: function (it) {
            if (it.kind === 'job') return this.openJob(it);
            if (it.task_id) return this.openEditor(it);
            if (it.url) window.location.href = BASE + '/' + it.url;
        },

        /* ── Ticking off ────────────────────────────── */

        // Completing a task (or a job step) asks first; undoing it, or the
        // automatic items, go straight through — nothing is lost that way.
        tick: function (it, inJob) {
            if ((it.tick === 'task' || inJob) && !it.done) return this.askDone(it, inJob);
            return this.doTick(it, inJob);
        },

        askDone: function (it, inJob) {
            this.doneItem = it;
            this.doneInJob = !!inJob;
            this.doneFrom = document.activeElement;
            this.confirmDone = true;
            this.$nextTick(function () { var y = document.getElementById('pl-done-yes'); if (y) y.focus(); });
        },

        confirmDoneYes: function () {
            var it = this.doneItem, inJob = this.doneInJob;
            this.closeDone();
            if (it) this.doTick(it, inJob);
        },

        closeDone: function () {
            var self = this, from = this.doneFrom;
            this.confirmDone = false;
            this.doneItem = null;
            this.doneInJob = false;
            this.$nextTick(function () { if (from && from.isConnected && from.focus) from.focus(); });
        },

        doTick: function (it, inJob) {
            var self = this;
            var after = function () { if (inJob || self.panel === 'job') self.reloadJob(); };
            if (it.tick === 'task' || inJob) {
                var was = it.done;
                it.done = !was;
                return this.api('tick', { id: it.task_id, done: !was }).then(function (d) {
                    after();
                    self.say(d.message, function () {
                        self.api('tick', { id: it.task_id, done: was }).then(after).catch(self.fail.bind(self));
                    });
                }).catch(function (e) { it.done = was; self.fail(e); });
            }
            if (it.tick === 'dismiss') {
                var key = it.id.replace(/^auto:/, '');
                it.done = true;
                return this.api('dismiss', { key: key }).then(function (d) {
                    self.say(d.message, function () { self.api('dismiss', { key: key, undo: true }).catch(self.fail.bind(self)); });
                }).catch(function (e) { it.done = false; self.fail(e); });
            }
            if (it.tick === 'delivered') {
                it.done = true;
                return this.api('delivered', { invoice_id: it.invoice_id }).then(function (d) {
                    self.say(d.message, function () { self.api('delivered', { invoice_id: it.invoice_id, undo: true }).catch(self.fail.bind(self)); });
                }).catch(function (e) { it.done = false; self.fail(e); });
            }
        },

        star: function (it) {
            var self = this;
            it.starred = !it.starred;
            this.api('star', { id: it.task_id }).catch(function (e) { it.starred = !it.starred; self.fail(e); });
        },

        /* ── Quick add ──────────────────────────────── */

        startTask: function () {
            var el = document.getElementById('pl-quick');
            this.quick.when = 'today';
            if (el) { el.focus(); el.scrollIntoView({ behavior: reducedMotion() ? 'auto' : 'smooth', block: 'center' }); }
        },

        quickChips: function () {
            return [{ value: 'today', label: 'Today' }, { value: 'tomorrow', label: 'Tomorrow' }, { value: 'pick', label: 'Pick a date' }, { value: 'none', label: 'No date' }];
        },

        quickDate: function () {
            return { today: this.today, tomorrow: addDays(this.today, 1), pick: this.quick.date, none: null }[this.quick.when];
        },

        quickAdd: function () {
            var self = this;
            if (this.busy) return;
            var title = this.quick.title.trim();
            if (!title) { document.getElementById('pl-quick').focus(); return this.say('Write the task first.', null, true); }
            // "Pick a date" with the date cleared is not "No date" (P15)
            if (this.quick.when === 'pick' && !this.quick.date) return this.say('Pick the date first, or choose No date.', null, true);
            this.api('save', { kind: 'task', title: title, date: this.quickDate() }).then(function (d) {
                self.quick.title = '';
                self.say(d.message);
            }).catch(this.fail.bind(this));
        },

        /* ── Sheets ─────────────────────────────────── */

        openPanel: function (name) {
            var self = this;
            if (!this.panel) this.lastFocus = document.activeElement;
            this.panel = name;
            this.err = {};
            this.$nextTick(function () {
                var sheet = self.$refs.sheet;
                var el = sheet && (sheet.querySelector('#pl-title') || sheet.querySelector('input:not([type=hidden]):not([type=checkbox]), select, textarea, button'));
                if (el) el.focus();
            });
        },

        closePanel: function (keepDraft) {
            var self = this;
            if (!this.panel) return;
            this.panel = null;
            if (!keepDraft) this.clearDraft();
            this.custQuery = '';
            this.confirmDelete = false;
            this.job = {};
            this.jobSteps = [];
            this.$nextTick(function () { if (self.lastFocus && self.lastFocus.focus) self.lastFocus.focus(); });
        },

        onEscape: function () {
            if (this.confirmDone) this.closeDone();
            else if (this.confirmDelete) this.closeConfirm();
            else this.dismissPanel();
        },

        // A tap outside the sheet, or Escape, never throws away what was
        // typed (P14): the sheet closes, the draft stays, and the toast brings
        // it back. Only the sheet's own close button (or saving) discards it.
        dismissPanel: function () {
            var self = this, kept = false;
            if (!this.panel) return;
            var dirty = (this.panel === 'editor' && JSON.stringify(this.form) !== this.formBase
                         && (String(this.form.title || '').trim() || String(this.form.notes || '').trim()))
                     || (this.panel === 'plan' && this.plan.template);
            if (!dirty) return this.closePanel();
            this.saveDraft();
            try { kept = !!localStorage.getItem(this.draftKey()); } catch (e) {}
            if (!kept) return this.say('Save it, or close it with the cross to throw it away.', null, true);
            this.closePanel(true);
            this.say('Closed. What you typed is kept.', function () { self.restoreDraft(); }, false, 'Open');
        },

        askDelete: function () {
            this.confirmFrom = document.activeElement;
            this.confirmDelete = true;
            this.$nextTick(function () { var keep = document.getElementById('pl-del-keep'); if (keep) keep.focus(); });
        },

        closeConfirm: function () {
            var self = this;
            this.confirmDelete = false;
            this.$nextTick(function () { if (self.confirmFrom && self.confirmFrom.isConnected) self.confirmFrom.focus(); });
        },

        trapFocus: function (e) {
            var sheet = this.confirmDone ? document.getElementById('pl-done-dialog')
                      : this.confirmDelete ? document.getElementById('pl-del-dialog')
                      : this.$refs.sheet;
            if (!sheet) return;
            var els = Array.prototype.filter.call(
                sheet.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select, textarea'),
                function (el) { return el.offsetParent !== null; }
            );
            if (!els.length) return;
            var first = els[0], last = els[els.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
            else if (!sheet.contains(document.activeElement)) { e.preventDefault(); first.focus(); }
        },

        /* ── Task / appointment editor ──────────────── */

        openEditor: function (src) {
            src = src || {};
            var kind = src.kind === 'appointment' ? 'appointment' : 'task';
            var isNew = !src.task_id;
            var date = isNew ? (src.date !== undefined ? src.date : (kind === 'appointment' ? this.today : this.today)) : src.date;
            this.form = {
                id: src.task_id || null,
                kind: kind,
                title: src.title || '',
                date: date || null,
                pickOpen: !!date && date !== this.today && date !== addDays(this.today, 1),
                time: src.time || '',
                end_time: src.end_time || '',
                customer_id: src.customer ? src.customer.id : (src.customer_id || null),
                order: src.order || null,
                assigned_to: src.assignee ? src.assignee.id : '',
                remind_before: isNew ? (kind === 'appointment' ? 0 : '') : (src.remind_before === null || src.remind_before === undefined ? '' : src.remind_before),
                repeat: src.repeat || '',
                notes: src.notes || '',
                starred: !!src.starred,
                parent_id: src.parent_id || null,
                created_by: src.created_by || this.me.id,
                plan: src.plan || null,
                wa: src.wa || null,
                wa_label: src.wa_label || null,
                fromQuick: !!src.fromQuick
            };
            this.formBase = JSON.stringify(this.form);
            this.custQuery = '';
            this.openPanel('editor');
        },

        setKind: function (k) {
            this.form.kind = k;
            if (k === 'appointment') {
                if (!this.form.date) this.form.date = this.today;
                if (this.form.remind_before === '') this.form.remind_before = this.form.time ? 60 : 0;
            } else {
                this.form.end_time = '';
            }
        },

        dateChips: function () {
            var c = [{ value: 'today', label: 'Today' }, { value: 'tomorrow', label: 'Tomorrow' }, { value: 'pick', label: 'Pick a date' }];
            if (this.form.kind === 'task') c.push({ value: 'none', label: 'No date' });
            return c;
        },

        dateChoice: function () {
            if (this.form.pickOpen) return 'pick';
            if (!this.form.date) return 'none';
            if (this.form.date === this.today) return 'today';
            if (this.form.date === addDays(this.today, 1)) return 'tomorrow';
            return 'pick';
        },

        pickDate: function (v) {
            this.form.pickOpen = v === 'pick';
            if (v === 'today') this.form.date = this.today;
            if (v === 'tomorrow') this.form.date = addDays(this.today, 1);
            if (v === 'pick' && !this.form.date) this.form.date = addDays(this.today, 2);
            if (v === 'none') { this.form.date = null; this.form.time = ''; this.form.end_time = ''; this.form.repeat = ''; this.form.remind_before = ''; }
            this.clearErr('date');
        },

        remindOptions: function () {
            var set = this.form.time ? D.remind.timed : D.remind.untimed;
            return Object.keys(set).map(function (k) { return { value: Number(k), label: set[k] }; });
        },

        timeChanged: function () {
            var ok = this.remindOptions().some(function (o) { return String(o.value) === String(this.form.remind_before); }, this);
            if (this.form.remind_before !== '' && !ok) this.form.remind_before = this.form.time ? 60 : 0;
            if (!this.form.id && this.form.kind === 'appointment' && this.form.time && this.form.remind_before === 0) this.form.remind_before = 60;
        },

        touch: function (field) {
            if (field === 'title' && !String(this.form.title || '').trim()) this.setErr('title', this.form.kind === 'appointment' ? 'Write what the appointment is.' : 'Write what needs doing.');
        },
        checkEnd: function () {
            var f = this.form;
            if (f.end_time && f.time && f.end_time <= f.time) this.setErr('end_time', 'The end time must be after the start.');
            else this.clearErr('end_time');
        },
        setErr: function (f, msg) { var e = Object.assign({}, this.err); e[f] = msg; this.err = e; },
        clearErr: function (f) { if (this.err[f]) this.setErr(f, ''); },

        customerName: function (id) {
            var c = this.customers.find(function (c) { return c.id === Number(id); });
            return c ? c.name : '';
        },

        custMatches: function () {
            var q = this.custQuery.trim().toLowerCase();
            if (!q) return [];
            var digits = q.replace(/\D/g, '');
            return this.customers.filter(function (c) {
                return c.name.toLowerCase().indexOf(q) !== -1 || (digits.length > 3 && (c.phone || '').replace(/\D/g, '').indexOf(digits) !== -1);
            }).slice(0, 6);
        },

        canDelete: function (x) {
            return this.me.manager || Number(x.created_by) === this.me.id;
        },

        save: function () {
            var self = this, f = this.form;
            if (this.busy) return;
            this.err = {};
            this.touch('title');
            if (f.kind === 'appointment' && !f.date) this.setErr('date', 'Pick the day of the appointment.');
            if (f.end_time && f.time && f.end_time <= f.time) this.setErr('end_time', 'The end time must be after the start.');
            if (Object.values(this.err).some(Boolean)) return this.focusFirstError();
            var parent = f.parent_id;
            this.api('save', {
                id: f.id, kind: f.kind, title: f.title.trim(), date: f.date, time: f.date ? f.time : '',
                end_time: f.end_time, customer_id: f.customer_id, assigned_to: f.assigned_to, order_id: f.order ? f.order.id : null,
                remind_before: f.remind_before, repeat: f.date ? f.repeat : '', notes: f.notes, starred: f.starred
            }).then(function (d) {
                self.clearDraft();
                self.say(d.message);
                if (f.fromQuick) self.quick.title = '';
                if (parent && self.job.task_id === parent) { self.openPanel('job'); self.reloadJob(); }
                else self.closePanel();
            }).catch(function (e) {
                if (e.fields) { self.err = e.fields; self.focusFirstError(); }
                self.fail(e);
            });
        },

        remove: function () {
            var self = this, f = this.form;
            if (this.busy) return;
            this.api('delete', { id: f.id }).then(function (d) {
                self.clearDraft();
                self.confirmDelete = false;
                if (f.parent_id && self.job.task_id === f.parent_id) { self.openPanel('job'); self.reloadJob(); }
                else self.closePanel();
                self.say(d.message);
            }).catch(function (e) { self.closeConfirm(); self.fail(e); });
        },

        /* ── Job plans ──────────────────────────────── */

        templateOf: function (key) {
            return this.templates.find(function (t) { return t.key === key; }) || { label: '', finish: 'Finish by', steps: [] };
        },

        nameIn: function (raw, name) {
            if (raw.indexOf('{name}') === -1) return raw;
            if (name) return raw.split('{name}').join(name);
            return raw.replace(/ (for|with) \{name\}/, '').split('{name}').join('customer');
        },

        openPlan: function (p) {
            p = p || {};
            this.plan = {
                template: null, customer_id: p.customer || null, invoice_id: p.invoice ? String(p.invoice) : '',
                finish: addDays(this.today, 7), title: '', titleTouched: false, assigned_to: '', steps: []
            };
            if (p.invoice) {
                var inv = D.invoices.find(function (i) { return i.id === Number(p.invoice); });
                if (inv) {
                    if (inv.customer_id) this.plan.customer_id = inv.customer_id;
                    if (inv.deliver_by && inv.deliver_by >= this.today) this.plan.finish = inv.deliver_by;
                }
            }
            this.custQuery = '';
            this.openPanel('plan');
            if (p.plan) this.chooseTemplate(p.plan);
        },

        chooseTemplate: function (key) {
            var t = this.templateOf(key), name = firstName(this.customerName(this.plan.customer_id));
            var self = this;
            this.plan.template = key;
            if (!this.plan.titleTouched) this.plan.title = this.nameIn(t.title, name);
            this.plan.steps = t.steps.map(function (s) {
                return { uid: ++uid, raw: s.title, title: self.nameIn(s.title, name), offset: s.offset, kind: s.kind, deliver: s.deliver, date: '', time: '', touched: false };
            });
            this.recalcPlan();
            this.err = {};
        },

        setPlanCustomer: function (id) {
            var oldName = firstName(this.customerName(this.plan.customer_id));
            var newName = firstName(this.customerName(id));
            var self = this, t = this.templateOf(this.plan.template);
            this.plan.customer_id = id;
            this.custQuery = '';
            if (!this.plan.titleTouched && t.title) this.plan.title = this.nameIn(t.title, newName);
            this.plan.steps.forEach(function (s) {
                if (s.raw && s.title === self.nameIn(s.raw, oldName)) s.title = self.nameIn(s.raw, newName);
            });
            var inv = D.invoices.find(function (i) { return String(i.id) === String(self.plan.invoice_id); });
            if (inv && id && inv.customer_id !== id) this.plan.invoice_id = '';
        },

        planInvoices: function () {
            var cid = this.plan.customer_id;
            return D.invoices.filter(function (i) { return !cid || i.customer_id === cid; }).slice(0, 50);
        },

        planInvoiceChanged: function () {
            var self = this;
            var inv = D.invoices.find(function (i) { return String(i.id) === String(self.plan.invoice_id); });
            if (!inv) return;
            if (inv.customer_id && !this.plan.customer_id) this.setPlanCustomer(inv.customer_id);
            if (inv.deliver_by && inv.deliver_by >= this.today) { this.plan.finish = inv.deliver_by; this.recalcPlan(); }
        },

        recalcPlan: function () {
            var finish = this.plan.finish, today = this.today;
            if (!finish) return;
            var offsets = this.plan.steps.map(function (s) { return s.offset || 0; });
            var max = offsets.length ? Math.max.apply(null, offsets) : 0;
            var room = Math.max(0, between(today, finish));
            this.plan.steps.forEach(function (s, i) {
                if (s.touched) return;
                var back = (max === 0 || room >= max) ? offsets[i] : Math.round(offsets[i] * room / max);
                s.date = addDays(finish, -back);
            });
        },

        addStep: function () {
            this.plan.steps.push({ uid: ++uid, raw: '', title: '', offset: 0, kind: 'task', deliver: false, date: this.plan.finish || this.today, time: '', touched: true });
        },

        savePlan: function () {
            var self = this, p = this.plan;
            if (this.busy) return;
            this.err = {};
            if (!p.title.trim()) this.setErr('title', 'Give the job a name.');
            if (!p.finish) this.setErr('finish', 'Pick the finish date.');
            var steps = p.steps.filter(function (s) { return s.title.trim(); });
            if (!steps.length) this.setErr('steps', 'Add at least one step.');
            if (steps.some(function (s) { return !s.date; })) this.setErr('steps', 'Every step needs a date.');
            if (Object.values(this.err).some(Boolean)) return this.focusFirstError();
            this.api('plan', {
                template: p.template, title: p.title.trim(), finish: p.finish, customer_id: p.customer_id,
                invoice_id: p.invoice_id, assigned_to: p.assigned_to,
                steps: steps.map(function (s) { return { title: s.title.trim(), date: s.date, time: s.kind === 'appointment' ? s.time : '', kind: s.kind, deliver: s.deliver }; })
            }).then(function (d) {
                self.closePanel();
                self.say(d.message);
            }).catch(function (e) {
                if (e.fields) { self.err = e.fields; self.focusFirstError(); }
                self.fail(e);
            });
        },

        openJob: function (job) {
            this.job = job;
            this.jobSteps = [];
            this.openPanel('job');
            this.reloadJob();
        },

        reloadJob: function () {
            var self = this, id = this.job.task_id;
            if (!id) return;
            var fresh = this.board.jobs.find(function (j) { return j.task_id === id; });
            if (fresh) this.job = fresh;
            this.jobLoading = !this.jobSteps.length;
            this.get('/tasks/job/' + id).then(function (d) {
                if (self.job.task_id !== id) return;   // another job was opened meanwhile
                self.jobSteps = d.steps;
                if (!fresh) {
                    var done = d.steps.filter(function (s) { return s.done; }).length;
                    self.job = Object.assign({}, self.job, { countdown: null, steps: { done: done, total: d.steps.length, next: null } });
                }
            }).catch(this.fail.bind(this)).finally(function () { self.jobLoading = false; });
        },

        /* ── Calendar ───────────────────────────────── */

        gridStart: function () {
            var first = this.viewMonth + '-01';
            var wd = new Date(ms(first)).getUTCDay();
            return addDays(first, -((wd + 6) % 7));
        },

        gridDays: function () {
            var start = this.gridStart(), out = [];
            for (var i = 0; i < 42; i++) {
                var d = addDays(start, i);
                out.push({ date: d, day: Number(d.slice(8)), inMonth: d.slice(0, 7) === this.viewMonth });
            }
            return out;
        },

        // After a change: this month refreshes in place (no blank list, focus
        // kept); other months fetch again when they are opened.
        refreshCalendar: function () {
            var keep = {};
            if (this.cal[this.viewMonth]) keep[this.viewMonth] = this.cal[this.viewMonth];
            this.cal = keep;
            if (this.tab === 'calendar') this.loadMonth(true);
        },

        loadMonth: function (refresh) {
            var self = this, key = this.viewMonth;
            if (this.cal[key] && !refresh) return;
            var from = this.gridStart(), to = addDays(from, 41);
            this.calLoading = !this.cal[key];
            this.get('/tasks/feed?from=' + from + '&to=' + to).then(function (d) {
                var byDay = {};
                d.items.forEach(function (it) { (byDay[it.date] = byDay[it.date] || []).push(it); });
                Object.keys(byDay).forEach(function (k) {
                    byDay[k].sort(function (a, b) {
                        return ((a.time || '99') + a.title).localeCompare((b.time || '99') + b.title);
                    });
                });
                var c = Object.assign({}, self.cal); c[key] = byDay; self.cal = c;
            }).catch(this.fail.bind(this)).finally(function () { self.calLoading = false; });
        },

        dayItems: function (date) {
            return (this.cal[this.viewMonth] || {})[date] || [];
        },

        moveMonth: function (n) {
            var p = this.viewMonth.split('-').map(Number);
            var d = new Date(Date.UTC(p[0], p[1] - 1 + n, 1));
            this.viewMonth = iso(d.getTime()).slice(0, 7);
            this.selected = this.today.slice(0, 7) === this.viewMonth ? this.today : this.viewMonth + '-01';
            this.loadMonth();
        },

        goToday: function () {
            this.viewMonth = this.today.slice(0, 7);
            this.selected = this.today;
            this.loadMonth();
        },

        selectDay: function (date) {
            this.selected = date;
            if (window.innerWidth < 1024) {
                this.$nextTick(function () {
                    var h = document.querySelector('[x-text="dayHeading(selected)"]');
                    if (h) h.scrollIntoView({ behavior: reducedMotion() ? 'auto' : 'smooth', block: 'start' });
                });
            }
        },

        monthLabel: function () {
            var p = this.viewMonth.split('-').map(Number);
            return MONTHS[p[1] - 1] + ' ' + p[0];
        },

        dayAria: function (d) {
            var n = this.dayItems(d.date).length;
            return this.dayHeading(d.date) + (n ? ', ' + n + (n === 1 ? ' item' : ' items') : ', nothing planned');
        },

        dotClass: function (it) {
            var c = 'bg-[#5B4650]';
            if (it.kind === 'appointment') c = 'bg-brand-burgundy';
            else if (it.source === 'deliver' || it.source === 'delivery') c = 'bg-[#B54708]';
            else if (it.source === 'invoice_due' || it.source === 'debt_in') c = 'bg-[#00764A]';
            else if (it.source === 'debt_out') c = 'bg-[#B42318]';
            else if (it.source === 'quote') c = 'bg-[#026AA2]';
            return c + (it.done ? ' opacity-40' : '');
        },

        /* ── Formatting ─────────────────────────────── */

        dayHeading: function (date) {
            if (!date) return '';
            var diff = between(this.today, date);
            if (diff === 0) return 'Today';
            if (diff === 1) return 'Tomorrow';
            if (diff === -1) return 'Yesterday';
            var d = new Date(ms(date));
            var s = WD[d.getUTCDay()] + ' ' + d.getUTCDate() + ' ' + MON[d.getUTCMonth()];
            return d.getUTCFullYear() === Number(this.today.slice(0, 4)) ? s : s + ' ' + d.getUTCFullYear();
        },

        fmtTime: function (t) {
            if (!t) return '';
            var p = t.split(':').map(Number), h = p[0] % 12 || 12;
            return h + ':' + String(p[1]).padStart(2, '0') + (p[0] < 12 ? 'am' : 'pm');
        },

        timeRange: function (it) {
            return this.fmtTime(it.time) + (it.end_time ? '–' + this.fmtTime(it.end_time) : '');
        },

        repeatLabel: function (r) {
            return { day: 'Every day', week: 'Every week', month: 'Every month' }[r] || '';
        },

        toneClass: function (tone) {
            if (tone === 'late' || tone === 'today') return 'bg-[#FEE4E2] text-[#B42318]';
            if (tone === 'soon') return 'bg-[#FEF0C7] text-[#93370D]';
            return 'bg-brand-cream text-[#5B4650]';
        },

        sourceColor: function (it) {
            return {
                deliver: 'text-[#B54708]', delivery: 'text-[#B54708]', invoice_due: 'text-[#00764A]', debt_in: 'text-[#00764A]',
                debt_out: 'text-[#B42318]', order: 'text-brand-burgundy', restock: 'text-[#5B4650]', quote: 'text-[#026AA2]'
            }[it.source] || 'text-[#5B4650]';
        },

        sourceIcon: function (it, cls) {
            var key = { deliver: 'deliver', delivery: 'deliver', invoice_due: 'money', debt_in: 'money', debt_out: 'money', order: 'order', restock: 'restock', quote: 'quote' }[it.source];
            if (!key) return '';
            return '<svg class="' + (cls || 'w-5 h-5') + '" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="' + ICONS[key] + '"/></svg>';
        },

        /* ── Reminders and phone notifications ──────── */

        savePrefs: function () {
            var self = this;
            this.api('prefs', this.prefs).then(function (d) { self.say(d.message); }).catch(this.fail.bind(this));
        },

        checkPush: function () {
            var self = this;
            if (!D.push.enabled) { this.pushState = 'unavailable'; return; }
            if (!window.NfeliaPush || !NfeliaPush.supported()) { this.pushState = 'unsupported'; return; }
            if (Notification.permission === 'denied') { this.pushState = 'blocked'; return; }
            // A phone that had alerts on but lost its subscription gets it
            // back here without a tap (assets/js/push.js heal), then the state shows.
            NfeliaPush.heal(D.push.key, null).then(function () { return NfeliaPush.state(D.push.key, null); }).then(function (state) {
                self.pushState = state === 'reset' ? 'reset' : (state === 'on' ? 'on' : 'off');
            }).catch(function () { self.pushState = 'off'; });
        },

        pushOn: function () {
            var self = this;
            this.busy = true;
            NfeliaPush.enable(D.push.key).then(function () {
                self.pushState = 'on';
                self.say('Notifications are on for this phone.');
            }).catch(function (e) {
                if (window.Notification && Notification.permission === 'denied') self.pushState = 'blocked';
                self.fail(e);
            }).finally(function () { self.busy = false; });
        },

        pushOff: function () {
            var self = this;
            this.busy = true;
            NfeliaPush.disable().then(function () {
                self.pushState = 'off';
                self.say('Notifications are off for this phone.');
            }).catch(this.fail.bind(this)).finally(function () { self.busy = false; });
        },

        pushTest: function () {
            var self = this;
            this.api('push-test', {}).then(function (d) { self.say(d.message); }).catch(this.fail.bind(this));
        }
    };
}
