/**
 * Nfelia — live check for a shop link (name.nfelia.com).
 * Used by the setup screen and the "Change link" form on Landing Page:
 *   <div x-data="shopNameField({ base: 'nfelia.com', checkUrl: '…/shop-setup/check', name: 'bisi' })">
 * The server checks again on save; this only gives the answer while typing.
 */
window.shopNameField = function (opts) {
    return {
        base: opts.base,
        checkUrl: opts.checkUrl,
        name: opts.name || '',
        normalised: opts.name || '',
        state: opts.state || '',
        message: opts.message || '',
        suggestions: opts.suggestions || [],
        ticket: 0,

        init() {
            if (this.name && !this.state) this.check();
        },

        // Same clean-up as shop_name_normalise(), so the preview matches
        // what will be saved before the check has answered.
        get previewHost() {
            const typed = this.name.trim().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                .toLowerCase().replace(/[\s._]+/g, '-');
            return (typed || 'yourshop') + '.' + this.base;
        },
        get otherSuggestions() {
            return this.suggestions.filter(s => s !== this.normalised);
        },
        get canSubmit() {
            // '' means the check hasn't answered (or couldn't): let the
            // server decide on save rather than block the owner.
            return this.state === 'ok' || this.state === 'review' || (this.state === '' && this.name.trim() !== '');
        },
        get statusClass() {
            if (this.state === 'ok') return 'text-[#00764A]';
            if (this.state === 'review') return 'text-amber-800';
            if (this.state === 'invalid' || this.state === 'reserved' || this.state === 'taken') return 'text-[#B42318]';
            return 'text-brand-plum/60';
        },
        get borderClass() {
            if (this.state === 'ok') return 'border-[#00764A]';
            if (this.state === 'invalid' || this.state === 'reserved' || this.state === 'taken') return 'border-[#B42318]';
            return 'border-brand-sand';
        },

        async check() {
            const typed = this.name;
            const ticket = ++this.ticket;
            if (!typed.trim()) {
                this.state = 'invalid';
                this.message = 'Type the name you want people to see.';
                return;
            }
            try {
                const res = await fetch(this.checkUrl + '?name=' + encodeURIComponent(typed), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const data = await res.json();
                if (ticket !== this.ticket) return;   // a newer check is on its way
                this.state = data.state || '';
                this.message = data.message || '';
                this.normalised = data.name || typed;
                // Free names only come with an answer that says no; keep the
                // last ones on screen otherwise.
                if (data.suggestions) this.suggestions = data.suggestions;
            } catch (e) {
                if (ticket !== this.ticket) return;
                this.state = '';
                this.message = 'We could not check the name just now. You can still save; we check it again then.';
            }
        },

        pick(suggestion) {
            this.name = suggestion;
            this.check();
        },
    };
};
