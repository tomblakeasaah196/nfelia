<?php
/**
 * Nfelia — Expense Create / Edit Form
 * Renders inside views/layouts/app.php
 *
 * Available from controller:
 *   $user, $business, $expense (empty or existing), $editing,
 *   $categories, $payment_methods, $pre_supplier, $items (for edit stock_purchase)
 */
$currency = $business['currency_symbol'] ?? '₦';
?>

<!-- Breadcrumb -->
<div class="flex items-center gap-3 mb-6">
    <a href="<?= APP_URL ?>/expenses" class="text-brand-plum/40 hover:text-brand-plum transition-colors">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    </a>
    <h1 class="font-heading font-extrabold text-xl text-brand-plum tracking-tight">
        <?= $editing ? 'Edit — ' . e($expense['doc_number'] ?? '') : 'Record Expense' ?>
    </h1>
</div>

<form method="POST"
      action="<?= APP_URL ?>/expenses/<?= $editing ? 'edit/' . $expense['id'] : 'create' ?>"
      class="max-w-3xl"
      x-data="expenseForm()"
      @submit.prevent="submitForm($el)">

    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

    <!-- Main Details -->
    <div class="bg-brand-white border border-brand-sand rounded-xl p-6 shadow-sm space-y-5 mb-6">

        <h2 class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Expense Details</h2>

        <!-- Category -->
        <div>
            <label class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest block mb-1">Category <span class="text-red-500">*</span></label>
            <select name="category" x-model="category"
                    class="w-full px-3 py-2.5 text-xs font-semibold bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum uppercase tracking-wider">
                <?php foreach ($categories as $k => $v): ?>
                    <option value="<?= $k ?>" <?= ($expense['category'] ?? 'miscellaneous') === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Date -->
            <div>
                <label class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest block mb-1">Date <span class="text-red-500">*</span></label>
                <input type="date" name="expense_date" value="<?= e($expense['expense_date'] ?? date('Y-m-d')) ?>" required
                       class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
            </div>

            <!-- Reference -->
            <div>
                <label class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest block mb-1">Reference / Receipt No.</label>
                <input type="text" name="reference" value="<?= e($expense['reference'] ?? '') ?>"
                       class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum"
                       placeholder="Optional receipt or ref number">
            </div>
        </div>

        <!-- Supplier (optional) -->
        <div>
            <label class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest block mb-1">Supplier</label>
            <div class="relative">
                <input type="hidden" name="supplier_id" :value="supplierId">
                <input type="text" x-model="supplierSearch" @input.debounce.300ms="searchSuppliers()"
                       @focus="showSupplierDrop = supplierResults.length > 0"
                       class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum"
                       :placeholder="supplierId ? '' : 'Search supplier by name or phone...'"
                       :class="supplierId ? 'border-green-300 bg-green-50/30' : ''">

                <!-- Selected indicator -->
                <template x-if="supplierId">
                    <button type="button" @click="clearSupplier()" class="absolute right-2 top-1/2 -translate-y-1/2 text-red-400 hover:text-red-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </template>

                <!-- Dropdown (shows results OR "no match" prompt) -->
                <div x-show="showSupplierDrop && !supplierId" @click.outside="showSupplierDrop = false"
                     class="absolute z-20 top-full left-0 right-0 mt-1 bg-brand-white border border-brand-sand rounded-lg shadow-lg max-h-48 overflow-y-auto">
                    <template x-for="s in supplierResults" :key="s.id">
                        <button type="button" @click="selectSupplier(s)"
                                class="w-full text-left px-3 py-2 hover:bg-brand-cream transition-colors">
                            <div class="text-xs font-semibold text-brand-plum" x-text="s.full_name"></div>
                            <div class="text-[10px] text-brand-plum/40" x-text="s.phone || s.business_name || ''"></div>
                        </button>
                    </template>
                    <template x-if="supplierResults.length === 0 && supplierSearch.length >= 2">
                        <div class="px-3 py-2 text-[10px] text-brand-plum/40 italic">
                            No suppliers match "<span x-text="supplierSearch" class="font-semibold"></span>"
                        </div>
                    </template>
                    <button type="button" @click="showNewSupplier = true; showSupplierDrop = false"
                            class="w-full text-left px-3 py-2 border-t border-brand-sand hover:bg-brand-cream text-[10px] font-bold text-brand-gold uppercase tracking-widest">
                        + Add New Supplier
                    </button>
                </div>
            </div>
        </div>

        <!-- Inline new supplier form -->
        <div x-show="showNewSupplier" x-transition class="bg-brand-cream/50 border border-brand-sand rounded-lg p-4 space-y-3">
            <div class="flex items-center justify-between">
                <div class="text-[9px] font-bold text-brand-gold uppercase tracking-widest">New Supplier</div>
                <button type="button" x-show="hasContactsAPI" @click="importContact('supplier')"
                        class="flex items-center gap-1.5 text-[10px] font-bold text-blue-600 uppercase tracking-widest hover:text-blue-800 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Pick from Contacts
                </button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <input type="text" x-model="newSupplierName" placeholder="Supplier name *"
                       class="px-3 py-2 text-xs font-medium bg-brand-white border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
                <input type="tel" maxlength="50" x-model="newSupplierPhone" placeholder="Phone number"
                       class="px-3 py-2 text-xs font-medium bg-brand-white border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
            </div>
            <div class="flex gap-2">
                <button type="button" @click="saveNewSupplier()" class="btn-primary text-white px-3 py-1.5 rounded-lg text-[10px] font-bold uppercase tracking-widest">Save</button>
                <button type="button" @click="showNewSupplier = false" class="text-[10px] font-bold text-brand-plum/40 uppercase tracking-widest">Cancel</button>
            </div>
        </div>

        <!-- Description -->
        <div>
            <label class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest block mb-1">Description <span class="text-red-500">*</span></label>
            <input type="text" name="description" value="<?= e($expense['description'] ?? '') ?>" required
                   class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum"
                   placeholder="e.g. Monthly rent for shop, Bought 50 cartons of rice...">
        </div>

        <!-- Amount (hidden for stock_purchase — calculated from items) -->
        <div x-show="category !== 'stock_purchase'">
            <label class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest block mb-1">Amount (<?= $currency ?>) <span class="text-red-500">*</span></label>
            <input type="number" name="amount" step="0.01" min="0" value="<?= e($expense['amount'] ?? '') ?>"
                   class="w-full px-3 py-2.5 text-sm font-bold bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum"
                   placeholder="0.00">
        </div>

        <!-- Notes -->
        <div>
            <label class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest block mb-1">Notes</label>
            <textarea name="notes" rows="2"
                      class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum resize-none"
                      placeholder="Additional notes..."><?= e($expense['notes'] ?? '') ?></textarea>
        </div>
    </div>

    <!-- Stock Purchase Line Items (shown only for stock_purchase category) -->
    <div x-show="category === 'stock_purchase'" x-transition class="bg-brand-white border border-brand-sand rounded-xl p-6 shadow-sm space-y-4 mb-6">
        <div class="flex items-center justify-between">
            <h2 class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Purchase Items</h2>
            <button type="button" @click="addItem()" class="text-[10px] font-bold text-brand-gold uppercase tracking-widest hover:text-accent-text">+ Add Item</button>
        </div>

        <template x-for="(item, idx) in items" :key="idx">
            <div class="grid grid-cols-12 gap-2 items-end bg-brand-cream/30 rounded-lg p-3">
                <input type="hidden" :name="'item_product_id[' + idx + ']'" :value="item.product_id || ''">

                <!-- Product search / description -->
                <div class="col-span-12 sm:col-span-5">
                    <label x-show="idx === 0" class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest block mb-1">Item</label>
                    <div class="relative">
                        <input type="text" :name="'item_description[' + idx + ']'" x-model="item.description"
                               @input.debounce.300ms="searchProducts(idx)"
                               @focus="item.showDrop = item.productResults?.length > 0"
                               class="w-full px-3 py-2 text-xs font-medium bg-brand-white border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum"
                               :class="item.product_id ? 'border-green-300' : ''"
                               placeholder="Search product or type description...">
                        <div x-show="item.showDrop" @click.outside="item.showDrop = false"
                             class="absolute z-20 top-full left-0 right-0 mt-1 bg-brand-white border border-brand-sand rounded-lg shadow-lg max-h-36 overflow-y-auto">
                            <template x-for="p in item.productResults" :key="p.id">
                                <button type="button" @click="selectProduct(idx, p)"
                                        class="w-full text-left px-3 py-2 hover:bg-brand-cream">
                                    <div class="text-xs font-semibold text-brand-plum" x-text="p.name"></div>
                                    <div class="text-[9px] text-brand-plum/40">
                                        <span x-text="p.sku || ''"></span>
                                        <span x-show="p.stock_quantity !== null"> &bull; Stock: <span x-text="p.stock_quantity"></span></span>
                                        <span x-show="p.cost_price > 0"> &bull; Cost: <?= $currency ?><span x-text="Number(p.cost_price).toLocaleString()"></span></span>
                                    </div>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Qty -->
                <div class="col-span-4 sm:col-span-2">
                    <label x-show="idx === 0" class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest block mb-1">Qty</label>
                    <input type="number" :name="'item_quantity[' + idx + ']'" x-model.number="item.quantity" step="0.01" min="0.01"
                           class="w-full px-3 py-2 text-xs font-bold bg-brand-white border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum text-center">
                </div>

                <!-- Unit cost -->
                <div class="col-span-4 sm:col-span-2">
                    <label x-show="idx === 0" class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest block mb-1">Unit Cost</label>
                    <input type="number" :name="'item_unit_cost[' + idx + ']'" x-model.number="item.unit_cost" step="0.01" min="0"
                           class="w-full px-3 py-2 text-xs font-bold bg-brand-white border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum text-right">
                </div>

                <!-- Line total -->
                <div class="col-span-3 sm:col-span-2">
                    <label x-show="idx === 0" class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest block mb-1">Total</label>
                    <div class="px-3 py-2 text-xs font-bold text-brand-plum text-right bg-brand-cream rounded-lg" x-text="'<?= $currency ?>' + lineTotal(idx).toLocaleString()"></div>
                </div>

                <!-- Remove -->
                <div class="col-span-1 flex justify-center">
                    <button type="button" @click="removeItem(idx)" x-show="items.length > 1" class="text-red-400 hover:text-red-600 mt-1">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </div>
            </div>
        </template>

        <!-- Grand Total -->
        <div class="flex justify-end pt-3 border-t border-brand-sand">
            <div class="text-right">
                <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest">Total Purchase</div>
                <div class="font-heading font-extrabold text-xl text-brand-plum" x-text="'<?= $currency ?>' + grandTotal().toLocaleString()"></div>
            </div>
        </div>
    </div>

    <!-- Payment Section -->
    <div class="bg-brand-white border border-brand-sand rounded-xl p-6 shadow-sm space-y-5 mb-6">
        <h2 class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Payment</h2>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <!-- Payment Status -->
            <div>
                <label class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest block mb-1">Payment Status</label>
                <select name="payment_status" x-model="paymentStatus"
                        class="w-full px-3 py-2.5 text-xs font-semibold bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum uppercase tracking-wider">
                    <option value="paid" <?= ($expense['payment_status'] ?? 'paid') === 'paid' ? 'selected' : '' ?>>Paid in Full</option>
                    <option value="partial" <?= ($expense['payment_status'] ?? '') === 'partial' ? 'selected' : '' ?>>Partial Payment</option>
                    <option value="unpaid" <?= ($expense['payment_status'] ?? '') === 'unpaid' ? 'selected' : '' ?>>Unpaid (Credit)</option>
                </select>
            </div>

            <!-- Amount Paid (only for partial) -->
            <div x-show="paymentStatus === 'partial'">
                <label class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest block mb-1">Amount Paid (<?= $currency ?>)</label>
                <input type="number" name="amount_paid" step="0.01" min="0" value="<?= e($expense['amount_paid'] ?? '') ?>"
                       class="w-full px-3 py-2.5 text-xs font-bold bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum"
                       placeholder="0.00">
            </div>

            <!-- Payment Method -->
            <div x-show="paymentStatus !== 'unpaid'">
                <label class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest block mb-1">Payment Method</label>
                <select name="payment_method"
                        class="w-full px-3 py-2.5 text-xs font-semibold bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum uppercase tracking-wider">
                    <option value="">Select method</option>
                    <?php foreach ($payment_methods as $k => $v): ?>
                        <option value="<?= $k ?>" <?= ($expense['payment_method'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="flex items-center gap-3">
        <button type="submit" class="btn-primary text-white px-6 py-3 rounded-xl text-[10px] font-bold uppercase tracking-widest shadow-sm hover:shadow-md transition-all">
            <?= $editing ? 'Update Expense' : 'Save Expense' ?>
        </button>
        <a href="<?= APP_URL ?>/expenses" class="text-[10px] font-bold text-brand-plum/40 uppercase tracking-widest hover:text-accent-text">Cancel</a>
    </div>
</form>

<script>
function expenseForm() {
    return {
        category: '<?= e($expense['category'] ?? ($_GET['category'] ?? 'miscellaneous')) ?>',
        paymentStatus: '<?= e($expense['payment_status'] ?? 'paid') ?>',

        // Contacts API (mobile only)
        hasContactsAPI: ('contacts' in navigator && 'ContactsManager' in window),

        // Supplier
        supplierId: <?= (int)($pre_supplier['id'] ?? ($expense['supplier_id'] ?? 0)) ?>,
        supplierSearch: '<?= e($pre_supplier['full_name'] ?? '') ?>',
        supplierResults: [],
        showSupplierDrop: false,
        showNewSupplier: false,
        newSupplierName: '',
        newSupplierPhone: '',

        // Line items (stock purchases)
        items: <?= json_encode(!empty($items) ? array_map(fn($it) => [
            'product_id'    => $it['product_id'],
            'description'   => $it['description'],
            'quantity'      => (float)$it['quantity'],
            'unit_cost'     => (float)$it['unit_cost'],
            'showDrop'      => false,
            'productResults'=> [],
        ], $items) : [['product_id' => null, 'description' => '', 'quantity' => 1, 'unit_cost' => 0, 'showDrop' => false, 'productResults' => []]]) ?>,

        addItem() {
            this.items.push({ product_id: null, description: '', quantity: 1, unit_cost: 0, showDrop: false, productResults: [] });
        },
        removeItem(idx) { this.items.splice(idx, 1); },

        lineTotal(idx) {
            const item = this.items[idx];
            return Math.round((item.quantity || 0) * (item.unit_cost || 0) * 100) / 100;
        },
        grandTotal() {
            return this.items.reduce((sum, _, idx) => sum + this.lineTotal(idx), 0);
        },

        async searchSuppliers() {
            if (this.supplierSearch.length < 2) { this.supplierResults = []; this.showSupplierDrop = false; return; }
            const res = await fetch(`<?= APP_URL ?>/expenses/search_suppliers?q=${encodeURIComponent(this.supplierSearch)}`);
            this.supplierResults = await res.json();
            this.showSupplierDrop = true; // Always show — displays results or "no match + Add New"
        },
        selectSupplier(s) {
            this.supplierId = s.id;
            this.supplierSearch = s.full_name;
            this.showSupplierDrop = false;
        },
        clearSupplier() {
            this.supplierId = 0;
            this.supplierSearch = '';
        },
        async saveNewSupplier() {
            if (!this.newSupplierName) return;
            const fd = new FormData();
            fd.append('full_name', this.newSupplierName);
            fd.append('phone', this.newSupplierPhone);
            fd.append('csrf_token', window.Nfelia.csrf);
            const res = await fetch('<?= APP_URL ?>/expenses/create_supplier', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json().catch(() => ({ error: 'That didn\'t save. Please try again.' }));
            if (data.error) { alert(data.error); return; }
            if (data.id) {
                this.supplierId = data.id;
                this.supplierSearch = data.full_name;
                this.showNewSupplier = false;
                this.newSupplierName = '';
                this.newSupplierPhone = '';
            }
        },

        async searchProducts(idx) {
            const q = this.items[idx].description;
            if (q.length < 2) { this.items[idx].productResults = []; return; }
            const res = await fetch(`<?= APP_URL ?>/expenses/search_products?q=${encodeURIComponent(q)}`);
            this.items[idx].productResults = await res.json();
            this.items[idx].showDrop = this.items[idx].productResults.length > 0;
        },
        selectProduct(idx, p) {
            this.items[idx].product_id = p.id;
            this.items[idx].description = p.name;
            this.items[idx].unit_cost = parseFloat(p.cost_price) || 0;
            this.items[idx].showDrop = false;
        },

        async importContact(target) {
            try {
                const props = ['name', 'tel', 'email'];
                const contacts = await navigator.contacts.select(props, { multiple: false });
                if (contacts && contacts.length > 0) {
                    const c = contacts[0];
                    const name  = (c.name  && c.name[0])  ? c.name[0]  : '';
                    const phone = (c.tel   && c.tel[0])   ? c.tel[0]   : '';
                    if (target === 'supplier') {
                        this.newSupplierName  = name;
                        this.newSupplierPhone = phone;
                    }
                }
            } catch (e) {
                // User cancelled or API error — silently ignore
            }
        },

        submitForm(el) { el.submit(); }
    };
}
</script>