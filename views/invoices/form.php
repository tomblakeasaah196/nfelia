<?php
/**
 * Nfelia — Invoice Builder (Create / Edit)
 * Renders inside views/layouts/app.php
 *
 * Available from controller:
 *   $user, $business, $invoice (array|empty), $items (array),
 *   $editing (bool), $customers, $banks
 */
$currency    = $business['currency_symbol'] ?? '₦';
$tax_default = (float)($business['default_tax_rate'] ?? business_default_tax((int)$business['id']));

// Pre-populate
$q_customer  = $invoice['customer_id'] ?? '';
$q_bank      = $invoice['bank_account_id'] ?? '';
$q_issue     = $invoice['issue_date'] ?? date('Y-m-d');
$q_due       = $invoice['due_date'] ?? date('Y-m-d', strtotime('+14 days'));
$q_tax       = $invoice['tax_rate'] ?? $tax_default;
$q_discount  = $invoice['discount_amount'] ?? 0;
$q_notes     = $invoice['notes'] ?? '';
$q_terms     = $invoice['payment_terms'] ?? '';

// Build items JSON for Alpine
$items_json = [];
if (!empty($items)) {
    foreach ($items as $it) {
        $items_json[] = [
            'product_id'  => (int)($it['product_id'] ?? 0),
            'description' => $it['description'] ?? '',
            'quantity'    => (float)($it['quantity'] ?? 1),
            'unit_price'  => (float)($it['unit_price'] ?? 0),
            'discount'    => (float)($it['discount_percent'] ?? 0),
        ];
    }
}
if (empty($items_json)) {
    $items_json[] = ['product_id' => 0, 'description' => '', 'quantity' => 1, 'unit_price' => 0, 'discount' => 0];
}
?>

<!-- Page Header -->
<div class="flex items-center gap-3 mb-8">
    <a href="<?= APP_URL ?>/invoices" class="text-brand-plum/40 hover:text-brand-plum transition-colors">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    </a>
    <h1 class="font-heading font-extrabold text-2xl text-brand-plum tracking-tight"><?= $editing ? 'Edit Invoice' : 'New Commercial Invoice' ?></h1>
</div>

<form method="POST" id="invoice-form"
      action="<?= APP_URL ?>/invoices/<?= $editing ? 'edit/' . (int)$invoice['id'] : 'create' ?>"
      x-data="invoiceBuilder()" x-init="init()">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

    <div class="grid lg:grid-cols-3 gap-6">

        <!-- ===== MAIN COLUMN (2/3) ===== -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Client Selection -->
            <div class="bg-brand-white border border-brand-sand rounded-xl p-6 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-heading font-bold text-xs text-brand-plum uppercase tracking-widest">Client</h2>
                    <div class="flex items-center gap-3">
                        <button type="button" x-show="showNewClient && hasContactsAPI" @click="importClientFromContacts()"
                                class="flex items-center gap-1.5 text-[10px] font-bold text-blue-600 uppercase tracking-widest hover:text-blue-800 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            Pick from Contacts
                        </button>
                        <button type="button" @click="showNewClient = !showNewClient" class="text-[10px] font-bold text-brand-gold uppercase tracking-widest hover:text-brand-burgundy transition-colors">
                            <span x-text="showNewClient ? 'Cancel' : '+ New Client'"></span>
                        </button>
                    </div>
                </div>

                <div x-show="!showNewClient">
                    <select name="customer_id" x-model="customerId" required
                            class="w-full px-4 py-3 text-sm font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
                        <option value="">Select a client...</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (int)$q_customer === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= e($c['full_name']) ?><?= $c['phone'] ? ' — ' . e($c['phone']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div x-show="showNewClient" x-transition class="space-y-3">
                    <input type="hidden" name="customer_id" :value="customerId" x-show="showNewClient">
                    <div class="grid sm:grid-cols-3 gap-3">
                        <input type="text" x-model="newClient.name" placeholder="Full Name *"
                               class="px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/25">
                        <input type="tel" maxlength="20" x-model="newClient.phone" placeholder="Phone"
                               class="px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/25">
                        <input type="email" x-model="newClient.email" placeholder="Email"
                               class="px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/25">
                    </div>
                    <button type="button" @click="saveNewClient()" class="btn-primary text-white px-4 py-2 rounded-lg text-[10px] font-bold uppercase tracking-widest">
                        Save Client
                    </button>
                    <p x-show="clientError" x-text="clientError" class="text-[10px] text-red-600 font-medium"></p>
                </div>
            </div>

            <!-- ===== LINE ITEMS ===== -->
            <div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-brand-sand flex items-center justify-between">
                    <h2 class="font-heading font-bold text-xs text-brand-plum uppercase tracking-widest">Line Items</h2>
                    <button type="button" @click="addItem()" class="text-[10px] font-bold text-brand-gold uppercase tracking-widest hover:text-brand-burgundy flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Add Line
                    </button>
                </div>

                <div class="hidden sm:grid grid-cols-12 gap-2 px-6 py-2 bg-brand-cream/50 border-b border-brand-sand text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">
                    <div class="col-span-5">Description</div>
                    <div class="col-span-2 text-center">Qty</div>
                    <div class="col-span-2 text-center">Unit Price</div>
                    <div class="col-span-1 text-center">Disc %</div>
                    <div class="col-span-1 text-right">Subtotal</div>
                    <div class="col-span-1"></div>
                </div>

                <template x-for="(item, idx) in lineItems" :key="idx">
                    <div class="px-6 py-4 border-b border-brand-sand/50 hover:bg-brand-cream/20 transition-colors">
                        <input type="hidden" :name="'item_product_id[' + idx + ']'" :value="item.product_id">
                        <input type="hidden" :name="'item_description[' + idx + ']'" :value="item.description">
                        <input type="hidden" :name="'item_quantity[' + idx + ']'" :value="item.quantity">
                        <input type="hidden" :name="'item_unit_price[' + idx + ']'" :value="item.unit_price">
                        <input type="hidden" :name="'item_discount[' + idx + ']'" :value="item.discount">

                        <div class="sm:grid grid-cols-12 gap-2 items-center space-y-3 sm:space-y-0">
                            <div class="col-span-5 relative">
                                <input type="text" x-model="item.description"
                                       @input="searchProduct(idx, item.description)"
                                       @focus="searchProduct(idx, item.description)"
                                       @keydown.escape="item._results = []"
                                       placeholder="Type product name or description..."
                                       class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/25">

                                <div x-show="item._results && item._results.length > 0"
                                     @click.away="item._results = []"
                                     class="absolute top-full left-0 right-0 mt-1 bg-brand-white border border-brand-sand rounded-lg shadow-xl z-30 max-h-48 overflow-y-auto">
                                    <template x-for="(prod, pi) in item._results" :key="pi">
                                        <button type="button" @click="selectProduct(idx, prod)"
                                                class="w-full text-left px-3 py-2 hover:bg-brand-cream transition-colors border-b border-brand-sand/30 last:border-0">
                                            <div class="text-xs font-semibold text-brand-plum" x-text="prod.name"></div>
                                            <div class="data-text text-[9px] text-brand-plum/40">
                                                <span x-text="prod.sku"></span> ·
                                                <span x-text="'<?= $currency ?>' + Number(prod.sell_price).toLocaleString()"></span>
                                                <span x-show="!prod.is_service" x-text="' · ' + prod.stock_qty + ' ' + prod.unit + ' in stock'"></span>
                                            </div>
                                        </button>
                                    </template>
                                </div>
                            </div>

                            <div class="col-span-2">
                                <label class="sm:hidden text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1 block">Qty</label>
                                <input type="number" x-model.number="item.quantity" step="0.01" min="0.01"
                                       class="w-full px-3 py-2.5 text-xs font-mono font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum text-center">
                            </div>

                            <div class="col-span-2">
                                <label class="sm:hidden text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1 block">Price</label>
                                <input type="number" x-model.number="item.unit_price" step="0.01" min="0"
                                       class="w-full px-3 py-2.5 text-xs font-mono font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum text-center">
                            </div>

                            <div class="col-span-1">
                                <label class="sm:hidden text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1 block">Disc%</label>
                                <input type="number" x-model.number="item.discount" step="0.1" min="0" max="100"
                                       class="w-full px-2 py-2.5 text-xs font-mono font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum text-center">
                            </div>

                            <div class="col-span-1 text-right">
                                <label class="sm:hidden text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1 block">Subtotal</label>
                                <span class="data-text text-xs font-bold text-brand-plum" x-text="formatMoney(lineSubtotal(idx))"></span>
                            </div>

                            <div class="col-span-1 text-right">
                                <button type="button" @click="removeItem(idx)" x-show="lineItems.length > 1"
                                        class="text-red-400 hover:text-red-600 transition-colors p-1">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </template>

                <div class="px-6 py-3">
                    <button type="button" @click="addItem()" class="w-full py-3 border-2 border-dashed border-brand-sand hover:border-brand-gold rounded-lg text-[10px] font-bold text-brand-plum/30 uppercase tracking-widest hover:text-brand-gold transition-all">
                        + Add Another Item
                    </button>
                </div>
            </div>

            <!-- Notes & Payment Terms -->
            <div class="bg-brand-white border border-brand-sand rounded-xl p-6 shadow-sm">
                <h2 class="font-heading font-bold text-xs text-brand-plum uppercase tracking-widest mb-4">Notes & Terms</h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-bold text-brand-plum/70 uppercase tracking-widest mb-1.5">Payment Terms</label>
                        <input type="text" name="payment_terms" value="<?= e($q_terms) ?>" placeholder="e.g., Net 14, 50% upfront, etc."
                               class="w-full px-4 py-3 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/25">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-brand-plum/70 uppercase tracking-widest mb-1.5">Additional Notes</label>
                        <textarea name="notes" rows="3" placeholder="Special conditions, delivery notes..."
                                  class="w-full px-4 py-3 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/25 resize-none"><?= e($q_notes) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== RIGHT COLUMN (1/3) ===== -->
        <div class="space-y-6">

            <!-- Dates -->
            <div class="bg-brand-white border border-brand-sand rounded-xl p-6 shadow-sm">
                <h2 class="font-heading font-bold text-xs text-brand-plum uppercase tracking-widest mb-4">Dates</h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-bold text-brand-plum/70 uppercase tracking-widest mb-1.5">Issue Date</label>
                        <input type="date" name="issue_date" value="<?= e($q_issue) ?>"
                               class="w-full px-3 py-2.5 text-xs font-mono font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-brand-plum/70 uppercase tracking-widest mb-1.5">Due Date</label>
                        <input type="date" name="due_date" value="<?= e($q_due) ?>"
                               class="w-full px-3 py-2.5 text-xs font-mono font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
                    </div>
                </div>
            </div>

            <!-- Delivery: when the customer gets it (Tasks & Calendar) -->
            <div class="bg-brand-white border border-brand-sand rounded-xl p-6 shadow-sm">
                <h2 class="font-heading font-bold text-xs text-brand-plum uppercase tracking-widest mb-4">Delivery</h2>
                <?php
                $df = [
                    'mode'   => $invoice['deliver_mode'] ?? '',
                    'date'   => $invoice['deliver_by'] ?? '',
                    'show'   => !empty($invoice['deliver_show']),
                    'handed' => empty($invoice['delivered_at']),
                    'id'     => 'inv',
                ];
                require BASE_PATH . '/views/partials/deliver_fields.php';
                ?>
            </div>

            <!-- Bank Account -->
            <?php if (!empty($banks)): ?>
            <div class="bg-brand-white border border-brand-sand rounded-xl p-6 shadow-sm">
                <h2 class="font-heading font-bold text-xs text-brand-plum uppercase tracking-widest mb-4">Payment Account</h2>
                <select name="bank_account_id" class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
                    <option value="">None</option>
                    <?php foreach ($banks as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= (int)$q_bank === (int)$b['id'] ? 'selected' : '' ?>>
                            <?= e($b['bank_name']) ?> — <?= e($b['account_number']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <!-- Totals Card -->
            <div class="bg-brand-white border border-brand-sand rounded-xl p-6 shadow-sm">
                <h2 class="font-heading font-bold text-xs text-brand-plum uppercase tracking-widest mb-4">Totals</h2>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold text-brand-plum/50 uppercase tracking-widest">Subtotal</span>
                        <span class="data-text text-sm font-bold text-brand-plum" x-text="formatMoney(subtotal())"></span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[10px] font-bold text-brand-plum/50 uppercase tracking-widest">Discount</span>
                        <div class="flex items-center gap-2">
                            <span class="text-[9px] text-brand-plum/30"><?= e($currency) ?></span>
                            <input type="number" name="discount_amount" x-model.number="discountAmount" step="0.01" min="0"
                                   class="w-24 px-2 py-1.5 text-xs font-mono font-medium bg-brand-cream border border-brand-sand rounded text-right focus:outline-none focus:border-brand-gold text-brand-plum">
                        </div>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[10px] font-bold text-brand-plum/50 uppercase tracking-widest">Tax</span>
                        <div class="flex items-center gap-2">
                            <input type="number" name="tax_rate" x-model.number="taxRate" step="0.01" min="0" max="100"
                                   class="w-16 px-2 py-1.5 text-xs font-mono font-medium bg-brand-cream border border-brand-sand rounded text-right focus:outline-none focus:border-brand-gold text-brand-plum">
                            <span class="text-[9px] text-brand-plum/30">%</span>
                            <span class="data-text text-xs text-brand-plum/50" x-text="formatMoney(taxAmount())"></span>
                        </div>
                    </div>
                    <div class="border-t border-brand-sand pt-3 flex items-center justify-between">
                        <span class="font-heading font-bold text-xs text-brand-plum uppercase tracking-widest">Grand Total</span>
                        <span class="font-heading font-extrabold text-xl text-brand-plum" x-text="formatMoney(grandTotal())"></span>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="space-y-3">
                <button type="submit" class="w-full btn-primary text-white py-3.5 rounded-xl text-[11px] font-bold uppercase tracking-widest shadow-sm hover:shadow-md transition-all">
                    <?= $editing ? 'Update Invoice' : 'Save as Draft' ?>
                </button>
                <a href="<?= APP_URL ?>/invoices<?= $editing ? '/view/' . (int)$invoice['id'] : '' ?>"
                   class="block w-full text-center bg-brand-cream border border-brand-sand text-brand-plum py-3.5 rounded-xl text-[11px] font-bold uppercase tracking-widest hover:border-brand-gold transition-all">Cancel</a>
            </div>
        </div>
    </div>
</form>

<!-- ===== ALPINE.JS INVOICE BUILDER LOGIC ===== -->
<script>
function invoiceBuilder() {
    return {
        lineItems: <?= json_encode($items_json) ?>,
        customerId: '<?= e($q_customer) ?>',
        discountAmount: <?= (float)$q_discount ?>,
        taxRate: <?= (float)$q_tax ?>,
        showNewClient: false,
        newClient: { name: '', phone: '', email: '' },
        hasContactsAPI: ('contacts' in navigator && 'ContactsManager' in window),
        clientError: '',
        _searchTimeout: null,

        init() {
            if (this.lineItems.length === 0) this.addItem();
            this.lineItems.forEach(item => { if (!item._results) item._results = []; });
        },

        addItem() {
            this.lineItems.push({ product_id: 0, description: '', quantity: 1, unit_price: 0, discount: 0, _results: [] });
        },
        removeItem(idx) { if (this.lineItems.length > 1) this.lineItems.splice(idx, 1); },

        selectProduct(idx, prod) {
            this.lineItems[idx].product_id  = prod.id;
            this.lineItems[idx].description = prod.name;
            this.lineItems[idx].unit_price  = prod.sell_price;
            this.lineItems[idx]._results    = [];
        },

        searchProduct(idx, query) {
            clearTimeout(this._searchTimeout);
            if (!query || query.length < 2) { this.lineItems[idx]._results = []; return; }
            this._searchTimeout = setTimeout(() => {
                $.ajax({
                    url: Nfelia.url + '/invoices/search_products',
                    method: 'POST',
                    data: { q: query, csrf_token: Nfelia.csrf },
                    dataType: 'json',
                    success: (res) => { this.lineItems[idx]._results = res.results || []; }
                });
            }, 250);
        },

        async importClientFromContacts() {
            try {
                const props = ['name', 'tel', 'email'];
                const contacts = await navigator.contacts.select(props, { multiple: false });
                if (contacts && contacts.length > 0) {
                    const c = contacts[0];
                    this.newClient.name  = (c.name  && c.name[0])  ? c.name[0]  : '';
                    this.newClient.phone = (c.tel   && c.tel[0])   ? c.tel[0]   : '';
                    this.newClient.email = (c.email && c.email[0]) ? c.email[0] : '';
                }
            } catch (e) { /* user cancelled */ }
        },

        saveNewClient() {
            this.clientError = '';
            if (!this.newClient.name.trim()) { this.clientError = 'Client name is required.'; return; }
            $.ajax({
                url: Nfelia.url + '/invoices/create_customer',
                method: 'POST',
                data: { full_name: this.newClient.name, phone: this.newClient.phone, email: this.newClient.email, csrf_token: Nfelia.csrf },
                dataType: 'json',
                success: (res) => {
                    if (res.error) { this.clientError = res.error; return; }
                    const select = document.querySelector('select[name="customer_id"]');
                    select.add(new Option(res.full_name + (res.phone ? ' — ' + res.phone : ''), res.id, true, true));
                    this.customerId = String(res.id);
                    this.showNewClient = false;
                    this.newClient = { name: '', phone: '', email: '' };
                },
                error: () => { this.clientError = 'Failed to save. Try again.'; }
            });
        },

        lineSubtotal(idx) {
            const item = this.lineItems[idx];
            const raw  = item.quantity * item.unit_price;
            return raw - (raw * item.discount / 100);
        },
        subtotal() { let t = 0; for (let i = 0; i < this.lineItems.length; i++) t += this.lineSubtotal(i); return t; },
        taxAmount() { return Math.round((this.subtotal() - this.discountAmount) * this.taxRate / 100 * 100) / 100; },
        grandTotal() { return this.subtotal() - this.discountAmount + this.taxAmount(); },
        formatMoney(val) { return '<?= $currency ?>' + Number(val || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}); }
    };
}
</script>