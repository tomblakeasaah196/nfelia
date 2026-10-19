<?php
/**
 * Nfelia — Debt Create Form (Manual)
 * Renders inside views/layouts/app.php
 *
 * Available from controller:
 *   $user, $business, $debt (empty or POST data), $editing, $pre_type
 */
$currency = $business['currency_symbol'] ?? '₦';
?>

<!-- Breadcrumb -->
<div class="flex items-center gap-3 mb-6">
    <a href="<?= APP_URL ?>/debts" class="text-brand-plum/40 hover:text-brand-plum transition-colors">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    </a>
    <h1 class="font-heading font-extrabold text-xl text-brand-plum tracking-tight">Add a debt</h1>
</div>

<form method="POST" action="<?= APP_URL ?>/debts/create" class="max-w-2xl"
      x-data="debtForm()">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

    <div class="bg-brand-white border border-brand-sand rounded-xl p-6 shadow-sm space-y-5">

        <h2 class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Debt Details</h2>

        <!-- Type toggle -->
        <div>
            <label class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest block mb-2">Debt Type <span class="text-red-500">*</span></label>
            <div class="flex gap-2">
                <button type="button" @click="debtType = 'receivable'"
                        :class="debtType === 'receivable' ? 'btn-primary text-white shadow-sm' : 'bg-brand-cream text-brand-plum/60 border border-brand-sand hover:border-brand-gold'"
                        class="flex-1 py-3 rounded-xl text-[10px] font-bold uppercase tracking-widest transition-all text-center">
                    Owed to me
                </button>
                <button type="button" @click="debtType = 'payable'"
                        :class="debtType === 'payable' ? 'btn-primary text-white shadow-sm' : 'bg-brand-cream text-brand-plum/60 border border-brand-sand hover:border-brand-gold'"
                        class="flex-1 py-3 rounded-xl text-[10px] font-bold uppercase tracking-widest transition-all text-center">
                    I owe
                </button>
            </div>
            <input type="hidden" name="type" :value="debtType">
        </div>

        <!-- Customer (receivable) -->
        <div x-show="debtType === 'receivable'">
            <label class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest block mb-1">Customer <span class="text-red-500">*</span></label>
            <div class="relative">
                <input type="hidden" name="customer_id" :value="partyId">
                <input type="text" x-model="partySearch" @input.debounce.300ms="searchParty()"
                       @focus="showPartyDrop = partyResults.length > 0"
                       class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum"
                       :class="partyId ? 'border-green-300 bg-green-50/30' : ''"
                       placeholder="Search customer...">
                <template x-if="partyId">
                    <button type="button" @click="clearParty()" class="absolute right-2 top-1/2 -translate-y-1/2 text-red-400 hover:text-red-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </template>
                <div x-show="showPartyDrop && !partyId" @click.outside="showPartyDrop = false"
                     class="absolute z-20 top-full left-0 right-0 mt-1 bg-brand-white border border-brand-sand rounded-lg shadow-lg max-h-48 overflow-y-auto">
                    <template x-for="p in partyResults" :key="p.id">
                        <button type="button" @click="selectParty(p)"
                                class="w-full text-left px-3 py-2 hover:bg-brand-cream transition-colors">
                            <div class="text-xs font-semibold text-brand-plum" x-text="p.full_name"></div>
                            <div class="text-[10px] text-brand-plum/40" x-text="p.phone || p.business_name || ''"></div>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <!-- Supplier (payable) -->
        <div x-show="debtType === 'payable'">
            <label class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest block mb-1">Supplier <span class="text-red-500">*</span></label>
            <div class="relative">
                <input type="hidden" name="supplier_id" :value="partyId">
                <input type="text" x-model="partySearch" @input.debounce.300ms="searchParty()"
                       @focus="showPartyDrop = partyResults.length > 0"
                       class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum"
                       :class="partyId ? 'border-green-300 bg-green-50/30' : ''"
                       placeholder="Search supplier...">
                <template x-if="partyId">
                    <button type="button" @click="clearParty()" class="absolute right-2 top-1/2 -translate-y-1/2 text-red-400 hover:text-red-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </template>
                <div x-show="showPartyDrop && !partyId" @click.outside="showPartyDrop = false"
                     class="absolute z-20 top-full left-0 right-0 mt-1 bg-brand-white border border-brand-sand rounded-lg shadow-lg max-h-48 overflow-y-auto">
                    <template x-for="p in partyResults" :key="p.id">
                        <button type="button" @click="selectParty(p)"
                                class="w-full text-left px-3 py-2 hover:bg-brand-cream transition-colors">
                            <div class="text-xs font-semibold text-brand-plum" x-text="p.full_name"></div>
                            <div class="text-[10px] text-brand-plum/40" x-text="p.phone || p.business_name || ''"></div>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <!-- Description -->
        <div>
            <label class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest block mb-1">Description <span class="text-red-500">*</span></label>
            <input type="text" name="description" value="<?= e($debt['description'] ?? '') ?>" required
                   class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum"
                   placeholder="e.g. Balance for 20 bags of cement, Outstanding rent payment...">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Amount -->
            <div>
                <label class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest block mb-1">Amount (<?= $currency ?>) <span class="text-red-500">*</span></label>
                <input type="number" name="total_amount" step="0.01" min="0.01" value="<?= e($debt['total_amount'] ?? '') ?>" required
                       class="w-full px-3 py-2.5 text-sm font-bold bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum"
                       placeholder="0.00">
            </div>

            <!-- Due Date -->
            <div>
                <label class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest block mb-1">Due Date</label>
                <input type="date" name="due_date" value="<?= e($debt['due_date'] ?? '') ?>"
                       class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="flex items-center gap-3 mt-6">
        <button type="submit" class="btn-primary text-white px-6 py-3 rounded-xl text-[10px] font-bold uppercase tracking-widest shadow-sm hover:shadow-md transition-all">
            Record Debt
        </button>
        <a href="<?= APP_URL ?>/debts" class="text-[10px] font-bold text-brand-plum/40 uppercase tracking-widest hover:text-brand-burgundy">Cancel</a>
    </div>
</form>

<script>
function debtForm() {
    return {
        debtType: '<?= e($pre_type ?? ($debt['type'] ?? 'receivable')) ?>',
        partyId: <?= (int)($debt['customer_id'] ?? ($debt['supplier_id'] ?? 0)) ?>,
        partySearch: '',
        partyResults: [],
        showPartyDrop: false,

        async searchParty() {
            if (this.partySearch.length < 2) { this.partyResults = []; return; }
            const endpoint = this.debtType === 'receivable' ? 'search_customers' : 'search_suppliers';
            const res = await fetch(`<?= APP_URL ?>/debts/${endpoint}?q=${encodeURIComponent(this.partySearch)}`);
            this.partyResults = await res.json();
            this.showPartyDrop = this.partyResults.length > 0;
        },
        selectParty(p) {
            this.partyId = p.id;
            this.partySearch = p.full_name;
            this.showPartyDrop = false;
        },
        clearParty() {
            this.partyId = 0;
            this.partySearch = '';
        }
    };
}
</script>