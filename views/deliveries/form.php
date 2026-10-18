<?php
/**
 * Nfelia — Delivery Create / Edit Form
 * Renders inside views/layouts/app.php
 *
 * Available from controller:
 *   $user, $business, $delivery (array|empty), $editing (bool), $items,
 *   $providers, $pre_invoice, $pre_invoice_items, $pre_customer
 */
$currency = $business['currency_symbol'] ?? '₦';

// Build initial items JSON for Alpine
$initial_items = [];
if (!empty($items)) {
    foreach ($items as $it) {
        $initial_items[] = [
            'description' => $it['description'],
            'quantity'    => (float)$it['quantity'],
            'unit'        => $it['unit'] ?? '',
            'product_id'  => (int)($it['product_id'] ?? 0),
            'notes'       => $it['notes'] ?? '',
        ];
    }
} elseif (!$editing && !empty($pre_invoice_items)) {
    foreach ($pre_invoice_items as $it) {
        $initial_items[] = [
            'description' => $it['product_name'] ?: $it['description'],
            'quantity'    => (float)$it['quantity'],
            'unit'        => '',
            'product_id'  => (int)($it['product_id'] ?? 0),
            'notes'       => '',
        ];
    }
}
if (empty($initial_items)) {
    $initial_items[] = ['description' => '', 'quantity' => 1, 'unit' => '', 'product_id' => 0, 'notes' => ''];
}
?>

<!-- Page Header -->
<div class="flex items-center gap-3 mb-8">
    <a href="<?= APP_URL ?>/deliveries" class="w-9 h-9 flex items-center justify-center rounded-lg bg-brand-cream border border-brand-sand hover:border-brand-gold transition-colors">
        <svg class="w-4 h-4 text-brand-plum/60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    </a>
    <div>
        <h1 class="font-heading font-extrabold text-2xl sm:text-3xl text-brand-plum tracking-tight">
            <?= $editing ? 'Edit ' . e($delivery['doc_number']) : 'New Delivery' ?>
        </h1>
        <p class="text-xs text-brand-plum/50 font-medium mt-1"><?= $editing ? 'Update dispatch details' : 'Create a delivery note for dispatch' ?></p>
    </div>
</div>

<form method="POST" action="<?= APP_URL ?>/deliveries/<?= $editing ? 'edit/' . $delivery['id'] : 'create' ?>"
      x-data="deliveryForm()" class="space-y-6">
    <?= csrf_field() ?>

    <!-- Section: Link to Invoice (optional) -->
    <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm">
        <h2 class="font-heading font-bold text-sm text-brand-plum mb-4 uppercase tracking-wider">Link to Invoice (Optional)</h2>

        <div class="relative">
            <input type="hidden" name="invoice_id" :value="invoiceId">
            <input type="hidden" name="customer_id" :value="customerId">

            <template x-if="invoiceId > 0">
                <div class="flex items-center justify-between bg-brand-cream border border-brand-sand rounded-lg px-4 py-3">
                    <div>
                        <span class="data-text text-xs font-bold text-brand-plum" x-text="invoiceNumber"></span>
                        <span class="text-[10px] text-brand-plum/50 ml-2" x-text="invoiceCustomer"></span>
                    </div>
                    <button type="button" @click="clearInvoice()" class="text-[10px] font-bold text-red-500 uppercase tracking-widest hover:text-red-700">Remove</button>
                </div>
            </template>

            <template x-if="invoiceId == 0">
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-brand-plum/30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" x-model="invoiceSearch" @input.debounce.400ms="searchInvoices()"
                           placeholder="Search invoice number or customer name..."
                           class="w-full pl-10 pr-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/30">

                    <!-- Invoice Results -->
                    <div x-show="invoiceResults.length > 0" x-cloak
                         class="absolute z-20 w-full mt-1 bg-brand-white border border-brand-sand rounded-lg shadow-lg max-h-48 overflow-y-auto">
                        <template x-for="inv in invoiceResults" :key="inv.id">
                            <button type="button" @click="selectInvoice(inv)"
                                    class="w-full text-left px-4 py-3 hover:bg-brand-cream/50 border-b border-brand-sand/50 last:border-0">
                                <div class="flex justify-between">
                                    <span class="data-text text-xs font-bold text-brand-plum" x-text="inv.doc_number"></span>
                                    <span class="data-text text-[10px] font-bold text-green-600" x-text="'<?= $currency ?>' + Number(inv.total).toLocaleString()"></span>
                                </div>
                                <div class="text-[10px] text-brand-plum/50" x-text="inv.customer_name || 'Walk-in'"></div>
                            </button>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Section: Recipient -->
    <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm">
        <h2 class="font-heading font-bold text-sm text-brand-plum mb-4 uppercase tracking-wider">Recipient Details</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Recipient Name *</label>
                <input type="text" name="recipient_name" x-model="recipientName" required
                       class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/30"
                       placeholder="Full name of person receiving">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Phone Number</label>
                <input type="tel" maxlength="50" name="recipient_phone" x-model="recipientPhone"
                       class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/30"
                       placeholder="e.g. 08012345678">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Delivery Address *</label>
                <textarea name="delivery_address" x-model="deliveryAddress" required rows="2"
                          class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/30 resize-none"
                          placeholder="Full delivery address"></textarea>
            </div>
        </div>
    </div>

    <!-- Section: Items -->
    <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-heading font-bold text-sm text-brand-plum uppercase tracking-wider">Items Being Dispatched</h2>
            <button type="button" @click="addItem()"
                    class="text-[10px] font-bold text-brand-gold uppercase tracking-widest hover:text-brand-burgundy flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                Add Item
            </button>
        </div>

        <template x-for="(item, idx) in items" :key="idx">
            <div class="flex flex-col sm:flex-row gap-3 mb-3 p-3 bg-brand-cream/30 rounded-lg border border-brand-sand/50 relative group">
                <input type="hidden" :name="'item_product_id[' + idx + ']'" :value="item.product_id">

                <div class="flex-1 min-w-0">
                    <label class="block text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Description *</label>
                    <input type="text" :name="'item_description[' + idx + ']'" x-model="item.description"
                           class="w-full px-3 py-2 text-xs font-medium bg-brand-white border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum"
                           placeholder="Item description">
                </div>
                <div class="w-20">
                    <label class="block text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Qty *</label>
                    <input type="number" :name="'item_quantity[' + idx + ']'" x-model="item.quantity" min="0.01" step="0.01"
                           class="w-full px-3 py-2 text-xs font-medium bg-brand-white border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum text-center">
                </div>
                <div class="w-24">
                    <label class="block text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Unit</label>
                    <input type="text" :name="'item_unit[' + idx + ']'" x-model="item.unit"
                           class="w-full px-3 py-2 text-xs font-medium bg-brand-white border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum"
                           placeholder="pcs, kg...">
                </div>
                <div class="flex-1 min-w-0 hidden sm:block">
                    <label class="block text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Notes</label>
                    <input type="text" :name="'item_notes[' + idx + ']'" x-model="item.notes"
                           class="w-full px-3 py-2 text-xs font-medium bg-brand-white border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum"
                           placeholder="Optional">
                </div>
                <div class="flex items-end">
                    <button type="button" @click="removeItem(idx)" x-show="items.length > 1"
                            class="w-9 h-9 flex items-center justify-center text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </div>
            </div>
        </template>
    </div>

    <!-- Section: Pickup & Provider -->
    <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm">
        <h2 class="font-heading font-bold text-sm text-brand-plum mb-4 uppercase tracking-wider">Dispatch Details</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Pickup Address</label>
                <textarea name="pickup_address" rows="2"
                          class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/30 resize-none"
                          placeholder="Defaults to your business address"><?= e($editing ? $delivery['pickup_address'] : ($delivery['pickup_address'] ?? ($business['address'] ?? ''))) ?></textarea>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Provider / Courier</label>
                <select name="provider" class="w-full px-3 py-2.5 text-xs font-semibold bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
                    <option value="">— Select Provider —</option>
                    <?php foreach ($providers as $k => $v): ?>
                        <option value="<?= $k ?>" <?= ($delivery['provider'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Estimated Delivery</label>
                <input type="datetime-local" name="estimated_delivery"
                       value="<?= e(!empty($delivery['estimated_delivery']) ? date('Y-m-d\TH:i', strtotime($delivery['estimated_delivery'])) : '') ?>"
                       class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
            </div>
        </div>
    </div>

    <!-- Section: Rider Details (collapsible) -->
    <div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden" x-data="{ open: <?= !empty($delivery['rider_name']) || !empty($delivery['rider_phone']) ? 'true' : 'false' ?> }">
        <button type="button" @click="open = !open" class="w-full flex items-center justify-between p-5">
            <h2 class="font-heading font-bold text-sm text-brand-plum uppercase tracking-wider">Rider / Driver Details</h2>
            <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 text-brand-plum/40 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="open" x-collapse class="px-5 pb-5">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Rider Name</label>
                    <input type="text" name="rider_name" value="<?= e($delivery['rider_name'] ?? '') ?>"
                           class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/30"
                           placeholder="Name of rider / driver">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Rider Phone</label>
                    <input type="tel" maxlength="50" name="rider_phone" value="<?= e($delivery['rider_phone'] ?? '') ?>"
                           class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/30"
                           placeholder="e.g. 08012345678">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Booking Reference</label>
                    <input type="text" name="booking_reference" value="<?= e($delivery['booking_reference'] ?? '') ?>"
                           class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/30"
                           placeholder="3PL booking/order ID">
                </div>
            </div>
        </div>
    </div>

    <!-- Section: Tracking (collapsible) -->
    <div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden" x-data="{ open: <?= !empty($delivery['tracking_number']) || !empty($delivery['tracking_url']) ? 'true' : 'false' ?> }">
        <button type="button" @click="open = !open" class="w-full flex items-center justify-between p-5">
            <h2 class="font-heading font-bold text-sm text-brand-plum uppercase tracking-wider">Tracking Info</h2>
            <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 text-brand-plum/40 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="open" x-collapse class="px-5 pb-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Tracking Number</label>
                    <input type="text" name="tracking_number" value="<?= e($delivery['tracking_number'] ?? '') ?>"
                           class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/30"
                           placeholder="Courier tracking ID">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Tracking URL</label>
                    <input type="url" name="tracking_url" value="<?= e($delivery['tracking_url'] ?? '') ?>"
                           class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/30"
                           placeholder="https://...">
                </div>
            </div>
        </div>
    </div>

    <!-- Section: Notes -->
    <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm">
        <h2 class="font-heading font-bold text-sm text-brand-plum mb-4 uppercase tracking-wider">Additional Notes</h2>
        <div class="grid grid-cols-1 gap-4">
            <div>
                <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Special Instructions</label>
                <textarea name="special_instructions" rows="2"
                          class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/30 resize-none"
                          placeholder="Fragile, call before delivery, etc."><?= e($delivery['special_instructions'] ?? '') ?></textarea>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Internal Notes</label>
                <textarea name="notes" rows="2"
                          class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/30 resize-none"
                          placeholder="Private notes (not shown to recipient)"><?= e($delivery['notes'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="flex items-center justify-between">
        <a href="<?= APP_URL ?>/deliveries" class="text-[10px] font-bold text-brand-plum/40 uppercase tracking-widest hover:text-brand-burgundy">Cancel</a>
        <button type="submit" class="btn-primary text-white px-8 py-3 rounded-xl text-[10px] font-bold uppercase tracking-widest shadow-sm hover:shadow-md transition-all">
            <?= $editing ? 'Update Delivery' : 'Create Delivery' ?>
        </button>
    </div>
</form>

<script>
function deliveryForm() {
    return {
        invoiceId: <?= (int)($delivery['invoice_id'] ?? ($pre_invoice['id'] ?? 0)) ?>,
        invoiceNumber: '<?= e($delivery['invoice_doc_number'] ?? ($pre_invoice['doc_number'] ?? '')) ?>',
        invoiceCustomer: '<?= e($pre_customer['full_name'] ?? '') ?>',
        customerId: <?= (int)($delivery['customer_id'] ?? ($pre_customer['id'] ?? 0)) ?>,
        invoiceSearch: '',
        invoiceResults: [],

        recipientName: '<?= e($delivery['recipient_name'] ?? ($pre_customer['full_name'] ?? '')) ?>',
        recipientPhone: '<?= e($delivery['recipient_phone'] ?? ($pre_customer['phone'] ?? '')) ?>',
        deliveryAddress: '<?= e($delivery['delivery_address'] ?? ($pre_customer['address'] ?? '')) ?>',

        items: <?= json_encode($initial_items, JSON_UNESCAPED_UNICODE) ?>,

        addItem() {
            this.items.push({ description: '', quantity: 1, unit: '', product_id: 0, notes: '' });
        },
        removeItem(idx) {
            if (this.items.length > 1) this.items.splice(idx, 1);
        },

        async searchInvoices() {
            if (this.invoiceSearch.length < 2) { this.invoiceResults = []; return; }
            try {
                const res = await fetch(`<?= APP_URL ?>/deliveries/search_invoices?q=${encodeURIComponent(this.invoiceSearch)}`);
                this.invoiceResults = await res.json();
            } catch(e) { this.invoiceResults = []; }
        },

        async selectInvoice(inv) {
            this.invoiceId = inv.id;
            this.invoiceNumber = inv.doc_number;
            this.invoiceCustomer = inv.customer_name || 'Walk-in';
            this.customerId = inv.customer_id || 0;
            this.invoiceSearch = '';
            this.invoiceResults = [];

            // Auto-fill recipient from customer
            if (inv.customer_name) this.recipientName = inv.customer_name;
            if (inv.customer_phone) this.recipientPhone = inv.customer_phone;
            if (inv.customer_address) this.deliveryAddress = inv.customer_address;

            // Load invoice items
            try {
                const res = await fetch(`<?= APP_URL ?>/deliveries/get_invoice_items?invoice_id=${inv.id}`);
                const data = await res.json();
                if (data.length > 0) {
                    this.items = data.map(it => ({
                        description: it.product_name || it.description,
                        quantity: parseFloat(it.quantity),
                        unit: '',
                        product_id: it.product_id || 0,
                        notes: ''
                    }));
                }
            } catch(e) {}
        },

        clearInvoice() {
            this.invoiceId = 0;
            this.invoiceNumber = '';
            this.invoiceCustomer = '';
            this.invoiceSearch = '';
            this.invoiceResults = [];
        }
    };
}
</script>