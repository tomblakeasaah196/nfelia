<?php
/**
 * Nfelia — Expense Detail View
 * Renders inside views/layouts/app.php
 *
 * Available from controller:
 *   $user, $business, $expense, $supplier, $items, $categories
 */
$currency = $business['currency_symbol'] ?? '₦';

$pay_badge = match($expense['payment_status']) {
    'paid'    => 'bg-green-100 text-green-700',
    'partial' => 'bg-amber-100 text-amber-700',
    'unpaid'  => 'bg-red-100 text-red-700',
    default   => 'bg-gray-100 text-gray-700',
};
?>

<!-- Breadcrumb + Actions -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div class="flex items-center gap-3">
        <a href="<?= APP_URL ?>/expenses" class="text-brand-plum/40 hover:text-brand-plum transition-colors">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="font-heading font-extrabold text-xl text-brand-plum tracking-tight"><?= e($expense['doc_number']) ?></h1>
            <div class="flex items-center gap-2 mt-0.5">
                <span class="data-text text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-brand-cream text-brand-plum/60">
                    <?= $categories[$expense['category']] ?? ucfirst(str_replace('_', ' ', $expense['category'])) ?>
                </span>
                <span class="data-text text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded <?= $pay_badge ?>">
                    <?= ucfirst($expense['payment_status']) ?>
                </span>
                <span class="text-[10px] text-brand-plum/30"><?= fmt_date($expense['expense_date'], 'd M Y') ?></span>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <a href="<?= APP_URL ?>/expenses/edit/<?= $expense['id'] ?>" class="flex items-center gap-1.5 bg-brand-white border border-brand-sand hover:border-accent px-3 py-2 rounded-lg transition-all">
            <svg class="w-3.5 h-3.5 text-accent-text" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            <span class="text-[10px] font-bold text-brand-plum uppercase tracking-widest">Edit</span>
        </a>

        <form method="POST" action="<?= APP_URL ?>/expenses/delete/<?= $expense['id'] ?>" class="inline"
              onsubmit="return confirm('Delete this expense? Stock movements will be reversed.');">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            <button class="flex items-center gap-1.5 bg-brand-white border border-red-200 hover:border-red-400 px-3 py-2 rounded-lg transition-all">
                <svg class="w-3.5 h-3.5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                <span class="text-[10px] font-bold text-red-400 uppercase tracking-widest">Delete</span>
            </button>
        </form>
    </div>
</div>

<!-- Detail Card -->
<div class="bg-brand-white border border-brand-sand rounded-xl p-6 shadow-sm mb-6">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <div>
            <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Description</div>
            <p class="text-sm font-medium text-brand-plum"><?= e($expense['description']) ?></p>
        </div>

        <div>
            <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Supplier</div>
            <?php if ($supplier && !empty($supplier['deleted_at'])): ?>
                <span class="text-sm font-medium text-brand-plum"><?= e($supplier['full_name']) ?> <span class="text-brand-plum/50">(deleted)</span></span>
            <?php elseif ($supplier): ?>
                <a href="<?= APP_URL ?>/suppliers/view/<?= $supplier['id'] ?>" class="text-sm font-medium text-blue-600 hover:text-blue-800">
                    <?= e($supplier['full_name']) ?>
                    <?php if ($supplier['business_name']): ?><span class="text-brand-plum/40 text-xs"> — <?= e($supplier['business_name']) ?></span><?php endif; ?>
                </a>
            <?php else: ?>
                <span class="text-sm text-brand-plum/40">No supplier linked</span>
            <?php endif; ?>
        </div>

        <div>
            <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Date</div>
            <div class="text-sm font-medium text-brand-plum"><?= fmt_date($expense['expense_date'], 'd M Y') ?></div>
        </div>

        <?php if ($expense['reference']): ?>
        <div>
            <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Reference</div>
            <div class="text-sm font-medium text-brand-plum"><?= e($expense['reference']) ?></div>
        </div>
        <?php endif; ?>

        <?php if ($expense['payment_method']): ?>
        <div>
            <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Payment Method</div>
            <div class="text-sm font-medium text-brand-plum"><?= ucfirst(str_replace('_', ' ', $expense['payment_method'])) ?></div>
        </div>
        <?php endif; ?>

        <?php if ($expense['notes']): ?>
        <div class="sm:col-span-2">
            <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Notes</div>
            <p class="text-xs text-brand-plum/60 whitespace-pre-line"><?= e($expense['notes']) ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Financial Summary -->
<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm text-center">
        <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Total Amount</div>
        <div class="font-heading font-extrabold text-xl text-brand-plum"><?= money((float)$expense['amount'], $currency) ?></div>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm text-center">
        <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Amount Paid</div>
        <div class="font-heading font-extrabold text-xl text-green-600"><?= money((float)$expense['amount_paid'], $currency) ?></div>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm text-center">
        <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Balance Due</div>
        <div class="font-heading font-extrabold text-xl <?= (float)$expense['balance_due'] > 0 ? 'text-red-600' : 'text-green-600' ?>">
            <?= (float)$expense['balance_due'] > 0 ? money((float)$expense['balance_due'], $currency) : 'Cleared' ?>
        </div>
    </div>
</div>

<!-- Line Items (Stock Purchases) -->
<?php if (!empty($items)): ?>
<div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden mb-6">
    <div class="px-5 py-3 border-b border-brand-sand bg-brand-cream/30">
        <h3 class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Purchase Items</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="border-b border-brand-sand bg-brand-cream/50">
                    <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Item</th>
                    <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Product</th>
                    <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-center">Qty</th>
                    <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-right">Unit Cost</th>
                    <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-right">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-brand-sand/50">
                <?php foreach ($items as $item): ?>
                    <tr class="hover:bg-brand-cream/30">
                        <td class="px-5 py-3 text-xs font-medium text-brand-plum"><?= e($item['description']) ?></td>
                        <td class="px-5 py-3">
                            <?php if ($item['product_name']): ?>
                                <span class="data-text text-[10px] font-semibold text-blue-600"><?= e($item['product_name']) ?></span>
                                <?php if ($item['product_sku']): ?><span class="text-[9px] text-brand-plum/30 ml-1"><?= e($item['product_sku']) ?></span><?php endif; ?>
                            <?php else: ?>
                                <span class="text-[10px] text-brand-plum/30">Manual entry</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3 data-text text-xs font-bold text-brand-plum text-center"><?= rtrim(rtrim(number_format((float)$item['quantity'], 2), '0'), '.') ?></td>
                        <td class="px-5 py-3 data-text text-xs font-bold text-brand-plum text-right"><?= money((float)$item['unit_cost'], $currency) ?></td>
                        <td class="px-5 py-3 data-text text-xs font-bold text-brand-plum text-right"><?= money((float)$item['total'], $currency) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-brand-sand">
                    <td colspan="4" class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-right">Total</td>
                    <td class="px-5 py-3 font-heading font-extrabold text-sm text-brand-plum text-right"><?= money((float)$expense['amount'], $currency) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Payment Progress (for partial payments) -->
<?php if ($expense['payment_status'] === 'partial'):
    $pct = min(100, round((float)$expense['amount_paid'] / max(0.01, (float)$expense['amount']) * 100));
?>
<div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm mb-6">
    <div class="flex items-center justify-between mb-2">
        <span class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Payment Progress</span>
        <span class="data-text text-xs font-bold text-brand-plum"><?= $pct ?>%</span>
    </div>
    <div class="w-full bg-brand-cream rounded-full h-2.5 overflow-hidden">
        <div class="h-full rounded-full transition-all duration-500 <?= $pct >= 75 ? 'bg-green-500' : ($pct >= 40 ? 'bg-amber-500' : 'bg-red-500') ?>"
             style="width: <?= $pct ?>%"></div>
    </div>
    <div class="flex justify-between mt-1">
        <span class="text-[9px] text-green-600 font-bold">Paid: <?= money((float)$expense['amount_paid'], $currency) ?></span>
        <span class="text-[9px] text-red-600 font-bold">Remaining: <?= money((float)$expense['balance_due'], $currency) ?></span>
    </div>
</div>
<?php endif; ?>

<!-- Created timestamp -->
<div class="text-center text-[10px] text-brand-plum/30 mb-8">
    Created <?= fmt_date($expense['created_at'], 'd M Y \a\t H:i') ?>
</div>