<?php
/**
 * Nfelia — Receipt Builder (POS-style Direct Sale)
 * Renders inside views/layouts/app.php
 *
 * Available from controller:
 *   $user, $business, $receipt (empty or POST data), $items, $editing, $customers
 */
$currency = $business['currency_symbol'] ?? '₦';
?>

<!-- Breadcrumb -->
<div class="flex items-center gap-3 mb-6">
    <a href="<?= APP_URL ?>/receipts" class="text-brand-plum/40 hover:text-brand-plum transition-colors">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    </a>
    <h1 class="font-heading font-extrabold text-xl text-brand-plum tracking-tight">New Receipt — Direct Sale</h1>
</div>

<!-- Form -->
<form method="POST" action="<?= APP_URL ?>/receipts/create" x-data="receiptBuilder()" class="space-y-6">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- LEFT: Line Items -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Customer Selection -->
            <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm">
                <h2 class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest mb-3">Customer (Optional)</h2>

                <div class="flex gap-3">
                    <select name="customer_id" x-model="customer_id"
                            class="flex-1 text-xs font-semibold text-brand-plum bg-brand-cream border border-brand-sand rounded-lg px-3 py-2.5 focus:outline-none focus:border-brand-gold">
                        <option value="">Walk-in Customer</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (int)($receipt['customer_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= e($c['full_name']) ?><?= $c['phone'] ? ' — ' . e($c['phone']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" @click="showNewClient = !showNewClient"
                            class="text-[10px] font-bold text-brand-gold uppercase tracking-widest bg-brand-cream border border-brand-sand px-3 py-2.5 rounded-lg hover:border-brand-gold whitespace-nowrap">
                        + New
                    </button>
                </div>

                <!-- Inline New Client -->
                <div x-show="showNewClient" x-collapse class="mt-3 p-3 bg-brand-cream rounded-lg border border-brand-sand space-y-2">
                    <button type="button" data-pick-contact hidden data-fill-name='[x-model="newClient.name"]' data-fill-phone='[x-model="newClient.phone"]' data-fill-email='[x-model="newClient.email"]'
                            class="flex items-center gap-1.5 text-[10px] font-bold text-blue-600 uppercase tracking-widest hover:text-blue-800 transition-colors min-h-[44px]">
                        <?= nav_icon('clients') ?>Pick from Contacts
                    </button>
                    <input type="text" x-model="newClient.name" placeholder="Full Name *" class="w-full px-3 py-2 text-xs font-medium bg-brand-white border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
                    <div class="grid grid-cols-2 gap-2">
                        <input type="tel" maxlength="20" x-model="newClient.phone" placeholder="Phone" class="w-full px-3 py-2 text-xs font-medium bg-brand-white border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
                        <input type="email" x-model="newClient.email" placeholder="Email" class="w-full px-3 py-2 text-xs font-medium bg-brand-white border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
                    </div>
                    <button type="button" @click="saveClient()" class="btn-primary text-white px-3 py-1.5 rounded-lg text-[10px] font-bold uppercase tracking-widest">Save Client</button>
                </div>
            </div>

            <!-- Line Items -->
            <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Line Items</h2>
                    <button type="button" @click="addItem()" class="text-[10px] font-bold text-brand-gold uppercase tracking-widest hover:text-brand-burgundy">+ Add Item</button>
                </div>

                <template x-for="(item, idx) in items" :key="idx">
                    <div class="mb-4 p-4 bg-brand-cream/50 border border-brand-sand/50 rounded-lg relative">
                        <!-- Remove -->
                        <button type="button" @click="removeItem(idx)" x-show="items.length > 1"
                                class="absolute -top-2 -right-2 w-6 h-6 bg-red-100 text-red-500 rounded-full flex items-center justify-center hover:bg-red-200 text-xs font-bold">×</button>

                        <!-- Product search -->
                        <div class="relative mb-3">
                            <input type="text" :placeholder="'Search product or type description...'" x-model="item.search" @input.debounce.300ms="searchProducts(idx)"
                                   @focus="item.showDropdown = true" @click.outside="item.showDropdown = false"
                                   class="w-full px-3 py-2.5 text-xs font-medium bg-brand-white border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
                            <!-- Dropdown -->
                            <div x-show="item.showDropdown && item.results.length > 0" class="absolute z-30 left-0 right-0 mt-1 bg-brand-white border border-brand-sand rounded-lg shadow-lg max-h-40 overflow-y-auto">
                                <template x-for="(prod, pi) in item.results" :key="pi">
                                    <button type="button" @click="selectProduct(idx, prod)" class="w-full text-left px-3 py-2 hover:bg-brand-cream border-b border-brand-sand/30 last:border-0">
                                        <div class="text-xs font-semibold text-brand-plum" x-text="prod.name"></div>
                                        <div class="flex items-center gap-2 text-[9px] text-brand-plum/50">
                                            <span x-text="prod.sku || ''"></span>
                                            <span>•</span>
                                            <span x-text="'<?= $currency ?>' + Number(prod.sell_price).toLocaleString()"></span>
                                            <span>•</span>
                                            <span x-text="prod.is_service ? 'Service' : 'Stock: ' + prod.stock_qty"></span>
                                        </div>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <div class="grid grid-cols-12 gap-2">
                            <div class="col-span-5">
                                <label class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest">Description</label>
                                <input type="text" x-model="item.description" required class="w-full px-2 py-2 text-xs font-medium bg-brand-white border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
                            </div>
                            <div class="col-span-2">
                                <label class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest">Qty</label>
                                <input type="number" x-model="item.quantity" step="0.01" min="0.01" class="w-full px-2 py-2 text-xs font-medium bg-brand-white border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum text-center">
                            </div>
                            <div class="col-span-3">
                                <label class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest">Unit Price</label>
                                <input type="number" x-model="item.unit_price" step="0.01" min="0" class="w-full px-2 py-2 text-xs font-medium bg-brand-white border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum text-right">
                            </div>
                            <div class="col-span-2">
                                <label class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest">Subtotal</label>
                                <div class="px-2 py-2 text-xs font-bold text-brand-plum text-right data-text" x-text="'<?= $currency ?>' + lineTotal(idx).toLocaleString(undefined, {minimumFractionDigits: 2})"></div>
                            </div>
                        </div>

                        <!-- Hidden fields -->
                        <input type="hidden" :name="'item_description[' + idx + ']'" :value="item.description">
                        <input type="hidden" :name="'item_quantity[' + idx + ']'" :value="item.quantity">
                        <input type="hidden" :name="'item_unit_price[' + idx + ']'" :value="item.unit_price">
                        <input type="hidden" :name="'item_product_id[' + idx + ']'" :value="item.product_id || ''">
                    </div>
                </template>

                <button type="button" @click="addItem()" class="w-full py-3 border-2 border-dashed border-brand-sand rounded-lg text-[10px] font-bold text-brand-plum/40 uppercase tracking-widest hover:border-brand-gold hover:text-brand-gold transition-all">
                    + Add Another Item
                </button>
            </div>
        </div>

        <!-- RIGHT: Details + Totals -->
        <div class="space-y-6">

            <!-- Receipt Details -->
            <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm space-y-4">
                <h2 class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Receipt Details</h2>

                <div>
                    <label class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest block mb-1">Date</label>
                    <input type="date" name="issue_date" value="<?= e($receipt['issue_date'] ?? date('Y-m-d')) ?>"
                           class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
                </div>

                <div>
                    <label class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest block mb-1">Payment Method</label>
                    <select name="method"
                            class="w-full px-3 py-2.5 text-xs font-semibold bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
                        <option value="cash" <?= ($receipt['method'] ?? '') === 'cash' ? 'selected' : '' ?>>Cash</option>
                        <option value="bank_transfer" <?= ($receipt['method'] ?? '') === 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                        <option value="pos" <?= ($receipt['method'] ?? '') === 'pos' ? 'selected' : '' ?>>POS</option>
                        <option value="mobile_money" <?= ($receipt['method'] ?? '') === 'mobile_money' ? 'selected' : '' ?>>Mobile Money</option>
                        <option value="cheque" <?= ($receipt['method'] ?? '') === 'cheque' ? 'selected' : '' ?>>Cheque</option>
                        <option value="other" <?= ($receipt['method'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>

                <div>
                    <label class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest block mb-1">Note</label>
                    <textarea name="note" rows="3" placeholder="Optional note..."
                              class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/30 resize-none"><?= e($receipt['note'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Totals -->
            <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm">
                <h2 class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest mb-3">Total</h2>
                <div class="flex justify-between items-center py-3" style="border-top: 2px solid <?= e($business['primary_color'] ?? '#8B004A') ?>;">
                    <span class="font-heading font-bold text-sm uppercase tracking-wider" style="color: <?= e($business['primary_color'] ?? '#8B004A') ?>;">Grand Total</span>
                    <span class="font-heading font-extrabold text-xl" style="color: <?= e($business['primary_color'] ?? '#8B004A') ?>;" x-text="'<?= $currency ?>' + grandTotal().toLocaleString(undefined, {minimumFractionDigits: 2})"></span>
                </div>
            </div>

            <!-- Submit -->
            <button type="submit" class="w-full btn-primary text-white py-3.5 rounded-xl text-[10px] font-bold uppercase tracking-widest shadow-sm hover:shadow-md transition-all">
                Create Receipt
            </button>
        </div>
    </div>
</form>

<script>
function receiptBuilder() {
    return {
        customer_id: '<?= e($receipt['customer_id'] ?? '') ?>',
        showNewClient: false,
        newClient: { name: '', phone: '', email: '' },

        items: [
            { product_id: '', description: '', quantity: 1, unit_price: 0, search: '', results: [], showDropdown: false }
        ],

        addItem() {
            this.items.push({ product_id: '', description: '', quantity: 1, unit_price: 0, search: '', results: [], showDropdown: false });
        },

        removeItem(idx) {
            this.items.splice(idx, 1);
        },

        lineTotal(idx) {
            const i = this.items[idx];
            return Math.round((parseFloat(i.quantity) || 0) * (parseFloat(i.unit_price) || 0) * 100) / 100;
        },

        grandTotal() {
            return this.items.reduce((sum, _, idx) => sum + this.lineTotal(idx), 0);
        },

        searchProducts(idx) {
            const q = this.items[idx].search;
            if (q.length < 1) { this.items[idx].results = []; return; }

            $.ajax({
                url: '<?= APP_URL ?>/receipts/search_products',
                type: 'POST',
                data: { q },
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                success: (res) => {
                    this.items[idx].results = res.results || [];
                    this.items[idx].showDropdown = true;
                }
            });
        },

        selectProduct(idx, prod) {
            this.items[idx].product_id   = prod.id;
            this.items[idx].description  = prod.name;
            this.items[idx].unit_price   = prod.sell_price;
            this.items[idx].search       = prod.name;
            this.items[idx].showDropdown = false;
            this.items[idx].results      = [];
        },

        saveClient() {
            if (!this.newClient.name.trim()) return;

            $.ajax({
                url: '<?= APP_URL ?>/receipts/create_customer',
                type: 'POST',
                data: {
                    csrf_token: '<?= $_SESSION['csrf_token'] ?? '' ?>',
                    full_name: this.newClient.name,
                    phone: this.newClient.phone,
                    email: this.newClient.email,
                },
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                success: (res) => {
                    if (res.id) {
                        const sel = document.querySelector('select[name="customer_id"]');
                        const opt = new Option(res.full_name + (res.phone ? ' — ' + res.phone : ''), res.id, true, true);
                        sel.add(opt);
                        this.customer_id = res.id;
                        this.showNewClient = false;
                        this.newClient = { name: '', phone: '', email: '' };
                    }
                }
            });
        }
    };
}
</script>