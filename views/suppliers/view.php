<?php
/**
 * Nfelia — Supplier Profile / Detail View
 * Renders inside views/layouts/app.php
 *
 * Available from controller:
 *   $user, $business, $supplier, $expenses, $purchases, $debts, $stats, $wa_number
 */
$currency = $business['currency_symbol'] ?? '₦';
$wa_link  = $wa_number ? "https://wa.me/{$wa_number}" : '';

$pay_status_badge = function($s) {
    return match($s) {
        'paid'    => 'bg-green-100 text-green-700',
        'partial' => 'bg-amber-100 text-amber-700',
        'unpaid'  => 'bg-red-100 text-red-700',
        default   => 'bg-gray-100 text-gray-700',
    };
};
?>

<!-- Breadcrumb + Actions -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div class="flex items-center gap-3">
        <a href="<?= APP_URL ?>/suppliers" class="text-brand-plum/40 hover:text-brand-plum transition-colors">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="font-heading font-extrabold text-xl text-brand-plum tracking-tight"><?= e($supplier['full_name']) ?></h1>
            <div class="flex items-center gap-2 mt-0.5">
                <?php if ($supplier['business_name']): ?>
                    <span class="text-[10px] text-brand-plum/50 font-medium"><?= e($supplier['business_name']) ?></span>
                    <span class="text-brand-plum/20">&bull;</span>
                <?php endif; ?>
                <span class="data-text text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded <?= $supplier['is_active'] ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-500' ?>">
                    <?= $supplier['is_active'] ? 'Active' : 'Inactive' ?>
                </span>
                <span class="text-[10px] text-brand-plum/30">Since <?= fmt_date($supplier['created_at'], 'M Y') ?></span>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <a href="<?= APP_URL ?>/suppliers/edit/<?= $supplier['id'] ?>" class="flex items-center gap-1.5 bg-brand-white border border-brand-sand hover:border-accent px-3 py-2 rounded-lg transition-all">
            <svg class="w-3.5 h-3.5 text-accent-text" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            <span class="text-[10px] font-bold text-brand-plum uppercase tracking-widest">Edit</span>
        </a>

        <?php if ($wa_link): ?>
        <a href="<?= $wa_link ?>" target="_blank" class="flex items-center gap-1.5 bg-green-500 text-white px-3 py-2 rounded-lg hover:bg-green-600 transition-all">
            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            <span class="text-[10px] font-bold uppercase tracking-widest">WhatsApp</span>
        </a>
        <?php endif; ?>

        <?php if ($supplier['phone']): ?>
        <a href="tel:<?= e($supplier['phone']) ?>" class="flex items-center gap-1.5 bg-brand-cream border border-brand-sand hover:border-accent px-3 py-2 rounded-lg transition-all">
            <svg class="w-3.5 h-3.5 text-brand-plum/50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
            <span class="text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest">Call</span>
        </a>
        <?php endif; ?>

        <!-- Quick Actions -->
        <?php if (is_boss()): ?>
        <a href="<?= APP_URL ?>/expenses/create?supplier_id=<?= $supplier['id'] ?>" class="flex items-center gap-1.5 btn-primary text-white px-3 py-2 rounded-lg transition-all">
            <span class="text-[10px] font-bold uppercase tracking-widest">New Expense</span>
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Contact Details Card -->
<div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm mb-6">
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div>
            <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-0.5">Phone</div>
            <div class="text-xs font-semibold text-brand-plum"><?= $supplier['phone'] ? e($supplier['phone']) : '—' ?></div>
        </div>
        <div>
            <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-0.5">WhatsApp</div>
            <div class="text-xs font-semibold text-brand-plum"><?= $supplier['whatsapp'] ? e($supplier['whatsapp']) : ($supplier['phone'] ? 'Same as phone' : '—') ?></div>
        </div>
        <div>
            <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-0.5">Email</div>
            <div class="text-xs font-semibold text-brand-plum"><?= $supplier['email'] ? e($supplier['email']) : '—' ?></div>
        </div>
        <div>
            <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-0.5">Address</div>
            <div class="text-xs font-semibold text-brand-plum"><?= $supplier['address'] ? e($supplier['address']) : '—' ?></div>
        </div>
    </div>
    <?php if ($supplier['notes']): ?>
        <div class="mt-3 pt-3 border-t border-brand-sand/50">
            <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-0.5">Notes</div>
            <p class="text-xs text-brand-plum/60 whitespace-pre-line"><?= e($supplier['notes']) ?></p>
        </div>
    <?php endif; ?>
</div>

<!-- Financial Summary Cards -->
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Total Purchased</div>
        <div class="font-heading font-extrabold text-lg text-brand-plum truncate"><?= money((float)$supplier['total_purchased'], $currency) ?></div>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Total Paid</div>
        <div class="font-heading font-extrabold text-lg text-green-600 truncate"><?= money((float)$supplier['total_paid'], $currency) ?></div>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">You Owe</div>
        <div class="font-heading font-extrabold text-lg <?= (float)$supplier['outstanding_balance'] > 0 ? 'text-red-600' : 'text-green-600' ?> truncate">
            <?= (float)$supplier['outstanding_balance'] > 0 ? money((float)$supplier['outstanding_balance'], $currency) : 'Clear' ?>
        </div>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Expenses</div>
        <div class="font-heading font-extrabold text-lg text-brand-plum"><?= (int)$stats['expense_count'] ?></div>
        <?php if ((int)$stats['unpaid_count'] > 0): ?>
            <div class="data-text text-[9px] font-semibold text-red-500 mt-0.5"><?= (int)$stats['unpaid_count'] ?> unpaid</div>
        <?php endif; ?>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Stock Purchases</div>
        <div class="font-heading font-extrabold text-lg text-brand-plum"><?= (int)$stats['purchase_count'] ?></div>
    </div>
</div>

<!-- Tabbed History -->
<div x-data="{ tab: 'expenses' }">

    <div class="flex items-center gap-1 mb-4 overflow-x-auto">
        <button @click="tab = 'expenses'" :class="tab === 'expenses' ? 'btn-primary text-white' : 'bg-brand-white text-brand-plum/60 border border-brand-sand hover:border-accent'"
                class="px-4 py-2 rounded-lg text-[10px] font-bold uppercase tracking-widest transition-all whitespace-nowrap">
            All Expenses (<?= count($expenses) ?>)
        </button>
        <button @click="tab = 'purchases'" :class="tab === 'purchases' ? 'btn-primary text-white' : 'bg-brand-white text-brand-plum/60 border border-brand-sand hover:border-accent'"
                class="px-4 py-2 rounded-lg text-[10px] font-bold uppercase tracking-widest transition-all whitespace-nowrap">
            Stock Purchases (<?= count($purchases) ?>)
        </button>
        <?php if (!empty($debts)): ?>
        <button @click="tab = 'debts'" :class="tab === 'debts' ? 'btn-primary text-white' : 'bg-brand-white text-brand-plum/60 border border-brand-sand hover:border-accent'"
                class="px-4 py-2 rounded-lg text-[10px] font-bold uppercase tracking-widest transition-all whitespace-nowrap">
            Debts (<?= count($debts) ?>)
        </button>
        <?php endif; ?>
    </div>

    <!-- ALL EXPENSES TAB -->
    <div x-show="tab === 'expenses'" class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden mb-8">
        <?php if (!empty($expenses)): ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-brand-sand bg-brand-cream/50">
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Number</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Category</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Description</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Date</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-right">Amount</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-center">Payment</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-sand/50">
                    <?php foreach ($expenses as $exp): ?>
                        <tr class="hover:bg-brand-cream/30 <?= $exp['payment_status'] === 'unpaid' ? 'bg-red-50/30' : '' ?>">
                            <td class="px-5 py-3"><a href="<?= APP_URL ?>/expenses/view/<?= $exp['id'] ?>" class="data-text text-xs font-bold text-brand-plum hover:text-accent-text"><?= e($exp['doc_number']) ?></a></td>
                            <td class="px-5 py-3"><span class="data-text text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-brand-cream text-brand-plum/60"><?= ucfirst(str_replace('_', ' ', $exp['category'])) ?></span></td>
                            <td class="px-5 py-3 text-xs text-brand-plum/70 max-w-[200px] truncate"><?= e($exp['description']) ?></td>
                            <td class="px-5 py-3 data-text text-[10px] text-brand-plum/60"><?= fmt_date($exp['expense_date'], 'd M Y') ?></td>
                            <td class="px-5 py-3 data-text text-xs font-bold text-brand-plum text-right"><?= money((float)$exp['amount'], $currency) ?></td>
                            <td class="px-5 py-3 text-center"><span class="data-text text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded <?= $pay_status_badge($exp['payment_status']) ?>"><?= ucfirst($exp['payment_status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="p-8 text-center text-xs text-brand-plum/40">No expenses recorded for this supplier.</div>
        <?php endif; ?>
    </div>

    <!-- STOCK PURCHASES TAB -->
    <div x-show="tab === 'purchases'" class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden mb-8">
        <?php if (!empty($purchases)): ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-brand-sand bg-brand-cream/50">
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Number</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Description</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Date</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-right">Amount</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-right">Balance</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-center">Payment</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-sand/50">
                    <?php foreach ($purchases as $p): ?>
                        <tr class="hover:bg-brand-cream/30">
                            <td class="px-5 py-3"><a href="<?= APP_URL ?>/expenses/view/<?= $p['id'] ?>" class="data-text text-xs font-bold text-brand-plum hover:text-accent-text"><?= e($p['doc_number']) ?></a></td>
                            <td class="px-5 py-3 text-xs text-brand-plum/70 max-w-[200px] truncate"><?= e($p['description']) ?></td>
                            <td class="px-5 py-3 data-text text-[10px] text-brand-plum/60"><?= fmt_date($p['expense_date'], 'd M Y') ?></td>
                            <td class="px-5 py-3 data-text text-xs font-bold text-brand-plum text-right"><?= money((float)$p['amount'], $currency) ?></td>
                            <td class="px-5 py-3 data-text text-xs font-bold text-right <?= (float)$p['balance_due'] > 0 ? 'text-red-600' : 'text-green-600' ?>"><?= (float)$p['balance_due'] > 0 ? money((float)$p['balance_due'], $currency) : 'Paid' ?></td>
                            <td class="px-5 py-3 text-center"><span class="data-text text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded <?= $pay_status_badge($p['payment_status']) ?>"><?= ucfirst($p['payment_status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="p-8 text-center text-xs text-brand-plum/40">No stock purchases from this supplier.</div>
        <?php endif; ?>
    </div>

    <!-- DEBTS TAB -->
    <?php if (!empty($debts)): ?>
    <div x-show="tab === 'debts'" class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden mb-8">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-brand-sand bg-brand-cream/50">
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Description</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-right">Total</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-right">Settled</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-right">Balance</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Due</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-sand/50">
                    <?php foreach ($debts as $d):
                        $d_badge = match($d['status']) {
                            'active'      => 'bg-red-100 text-red-700',
                            'settling'    => 'bg-amber-100 text-amber-700',
                            'cleared'     => 'bg-green-100 text-green-700',
                            'written_off' => 'bg-gray-200 text-gray-500',
                            default       => 'bg-gray-100 text-gray-700',
                        };
                    ?>
                        <tr class="hover:bg-brand-cream/30">
                            <td class="px-5 py-3 text-xs font-semibold text-brand-plum"><?= e($d['description']) ?></td>
                            <td class="px-5 py-3 data-text text-xs font-bold text-brand-plum text-right"><?= money((float)$d['total_amount'], $currency) ?></td>
                            <td class="px-5 py-3 data-text text-xs font-bold text-green-600 text-right"><?= money((float)$d['amount_settled'], $currency) ?></td>
                            <td class="px-5 py-3 data-text text-xs font-bold text-red-600 text-right"><?= money((float)$d['balance'], $currency) ?></td>
                            <td class="px-5 py-3 data-text text-[10px] text-brand-plum/60"><?= $d['due_date'] ? fmt_date($d['due_date'], 'd M Y') : '—' ?></td>
                            <td class="px-5 py-3 text-center"><span class="data-text text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded <?= $d_badge ?>"><?= ucfirst(str_replace('_', ' ', $d['status'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Footer Actions -->
<div class="flex items-center justify-between mb-8">
    <form method="POST" action="<?= APP_URL ?>/suppliers/toggle_active/<?= $supplier['id'] ?>" class="inline">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
        <button class="text-[10px] font-bold uppercase tracking-widest <?= $supplier['is_active'] ? 'text-amber-500 hover:text-amber-700' : 'text-green-500 hover:text-green-700' ?> transition-colors">
            <?= $supplier['is_active'] ? 'Deactivate Supplier' : 'Reactivate Supplier' ?>
        </button>
    </form>

    <form method="POST" action="<?= APP_URL ?>/suppliers/delete/<?= $supplier['id'] ?>" class="inline"
          onsubmit="return confirm('Remove this supplier? They will no longer appear in your supplier list.');">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
        <button class="text-[10px] font-bold text-red-400 uppercase tracking-widest hover:text-red-600 transition-colors">Remove Supplier</button>
    </form>
</div>