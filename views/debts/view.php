<?php
/**
 * Nfelia — Debt Detail View + Settlement History
 * Renders inside views/layouts/app.php
 *
 * Available from controller:
 *   $user, $business, $debt, $party, $settlements, $source_doc, $wa_number
 */
$currency = $business['currency_symbol'] ?? '₦';
$wa_link  = $wa_number ? "https://wa.me/{$wa_number}" : '';

$is_overdue = $debt['due_date'] && $debt['due_date'] < date('Y-m-d') && in_array($debt['status'], ['active', 'settling']);
$is_open    = in_array($debt['status'], ['active', 'settling']);
$pct_settled = $debt['total_amount'] > 0 ? min(100, round((float)$debt['amount_settled'] / (float)$debt['total_amount'] * 100)) : 0;

$status_class = match($debt['status']) {
    'active'      => 'bg-red-100 text-red-700',
    'settling'    => 'bg-amber-100 text-amber-700',
    'cleared'     => 'bg-green-100 text-green-700',
    'written_off' => 'bg-gray-200 text-gray-500',
    default       => 'bg-gray-100 text-gray-700',
};

// WhatsApp reminder message
if ($wa_link && $debt['type'] === 'receivable' && $is_open) {
    $days_overdue = $is_overdue ? (int)((time() - strtotime($debt['due_date'])) / 86400) : 0;
    $wa_msg = "Hello " . ($party['full_name'] ?? 'valued customer') . ",%0A%0A"
        . "This is a friendly reminder about your outstanding balance of *" . money((float)$debt['balance'], $currency) . "*"
        . ($debt['description'] ? " for: " . $debt['description'] : "")
        . "."
        . ($days_overdue > 0 ? "%0A%0AThis has been outstanding for {$days_overdue} days." : "")
        . ($debt['due_date'] ? "%0ADue date: " . fmt_date($debt['due_date'], 'd M Y') : "")
        . "%0A%0AKindly make the payment at your earliest convenience. Thank you!";
    $wa_reminder_link = $wa_link . "?text=" . $wa_msg;
}
?>

<!-- Breadcrumb + Actions -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div class="flex items-center gap-3">
        <a href="<?= APP_URL ?>/debts?tab=<?= e($debt['type']) ?>" class="text-brand-plum/40 hover:text-brand-plum transition-colors">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="font-heading font-extrabold text-xl text-brand-plum tracking-tight"><?= e($debt['description']) ?></h1>
            <div class="flex items-center gap-2 mt-0.5">
                <span class="data-text text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded <?= $debt['type'] === 'receivable' ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600' ?>">
                    <?= $debt['type'] === 'receivable' ? 'Receivable' : 'Payable' ?>
                </span>
                <span class="data-text text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded <?= $status_class ?>">
                    <?= $is_overdue ? 'Overdue' : ucfirst(str_replace('_', ' ', $debt['status'])) ?>
                </span>
                <?php if ($debt['source'] !== 'manual'): ?>
                    <span class="data-text text-[8px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded bg-blue-50 text-blue-500">Auto: <?= $debt['source'] ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <?php if ($is_open && isset($wa_reminder_link)): ?>
        <a href="<?= $wa_reminder_link ?>" target="_blank" class="flex items-center gap-1.5 bg-green-500 text-white px-3 py-2 rounded-lg hover:bg-green-600 transition-all">
            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            <span class="text-[10px] font-bold uppercase tracking-widest">Send Reminder</span>
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Party + Source -->
<div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm mb-6">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div>
            <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">
                <?= $debt['type'] === 'receivable' ? 'Customer' : 'Supplier' ?>
            </div>
            <?php if ($party): ?>
                <a href="<?= APP_URL ?>/<?= $debt['type'] === 'receivable' ? 'customers' : 'suppliers' ?>/view/<?= $party['id'] ?>"
                   class="text-sm font-bold text-blue-600 hover:text-blue-800"><?= e($party['full_name']) ?></a>
                <?php if ($party['phone']): ?><div class="text-[10px] text-brand-plum/40"><?= e($party['phone']) ?></div><?php endif; ?>
            <?php else: ?>
                <span class="text-sm text-brand-plum/40">No party linked</span>
            <?php endif; ?>
        </div>

        <?php if ($source_doc): ?>
        <div>
            <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Source Invoice</div>
            <a href="<?= APP_URL ?>/invoices/view/<?= $source_doc['id'] ?>" class="text-sm font-bold text-blue-600 hover:text-blue-800"><?= e($source_doc['doc_number']) ?></a>
            <div class="text-[10px] text-brand-plum/40">Total: <?= money((float)$source_doc['total'], $currency) ?></div>
        </div>
        <?php endif; ?>

        <div>
            <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Due Date</div>
            <div class="text-sm font-medium <?= $is_overdue ? 'text-red-600 font-bold' : 'text-brand-plum' ?>">
                <?= $debt['due_date'] ? fmt_date($debt['due_date'], 'd M Y') : 'No due date set' ?>
                <?php if ($is_overdue): ?>
                    <span class="text-[9px] font-bold text-red-500 ml-1">(<?= (int)((time() - strtotime($debt['due_date'])) / 86400) ?> days overdue)</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Financial Summary + Progress -->
<div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm mb-6">
    <!-- Stacked rows on phones: three amounts side by side do not fit at 390px. -->
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-4 mb-4">
        <div class="flex items-baseline justify-between sm:block sm:text-center">
            <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Total Owed</div>
            <div class="font-heading font-extrabold text-xl text-brand-plum"><?= money((float)$debt['total_amount'], $currency) ?></div>
        </div>
        <div class="flex items-baseline justify-between sm:block sm:text-center">
            <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Settled</div>
            <div class="font-heading font-extrabold text-xl text-green-600"><?= money((float)$debt['amount_settled'], $currency) ?></div>
        </div>
        <div class="flex items-baseline justify-between sm:block sm:text-center">
            <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Remaining</div>
            <div class="font-heading font-extrabold text-xl <?= (float)$debt['balance'] > 0 ? 'text-red-600' : 'text-green-600' ?>">
                <?= (float)$debt['balance'] > 0 ? money((float)$debt['balance'], $currency) : 'Cleared' ?>
            </div>
        </div>
    </div>

    <!-- Progress bar -->
    <div class="flex items-center justify-between mb-2">
        <span class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Settlement Progress</span>
        <span class="data-text text-xs font-bold text-brand-plum"><?= $pct_settled ?>%</span>
    </div>
    <div class="w-full bg-brand-cream rounded-full h-3 overflow-hidden">
        <div class="h-full rounded-full transition-all duration-500 <?= $pct_settled >= 100 ? 'bg-green-500' : ($pct_settled >= 50 ? 'bg-amber-500' : 'bg-red-500') ?>"
             style="width: <?= $pct_settled ?>%"></div>
    </div>
</div>

<!-- Record Settlement (AJAX Modal) -->
<?php if ($is_open): ?>
<div class="bg-brand-white border border-brand-sand rounded-xl p-6 shadow-sm mb-6" x-data="settlementModal()">
    <h3 class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest mb-4">Record Settlement</h3>

    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
        <div>
            <label class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest block mb-1">Amount (<?= $currency ?>)</label>
            <input type="number" x-model.number="amount" step="0.01" min="0.01" max="<?= (float)$debt['balance'] ?>"
                   class="w-full px-3 py-2.5 text-sm font-bold bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
        </div>
        <div>
            <label class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest block mb-1">Method</label>
            <select x-model="method" class="w-full px-3 py-2.5 text-xs font-semibold bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum uppercase tracking-wider">
                <option value="cash">Cash</option>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="pos">POS or card</option>
                <option value="mobile_money">Mobile Money</option>
                <option value="other">Other (cheque and the rest)</option>
            </select>
        </div>
        <div>
            <label class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest block mb-1">Date</label>
            <input type="date" x-model="date" class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
        </div>
        <div class="flex items-end">
            <button type="button" @click="recordSettlement()" :disabled="submitting || amount <= 0"
                    class="w-full btn-primary text-white py-2.5 rounded-lg text-[10px] font-bold uppercase tracking-widest disabled:opacity-50 transition-all">
                <span x-show="!submitting">Record</span>
                <span x-show="submitting">Saving...</span>
            </button>
        </div>
    </div>

    <!-- Quick amount buttons -->
    <div class="flex gap-2 mt-3">
        <button type="button" @click="amount = <?= (float)$debt['balance'] ?>" class="text-[9px] font-bold text-brand-gold uppercase tracking-widest hover:text-brand-burgundy">Full Balance</button>
        <button type="button" @click="amount = Math.round(<?= (float)$debt['balance'] ?> / 2 * 100) / 100" class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest hover:text-brand-burgundy">Half</button>
    </div>

    <!-- Reference + Note (collapsible) -->
    <div class="mt-3">
        <button type="button" @click="showMore = !showMore" class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest hover:text-brand-plum">
            + Reference & Note
        </button>
        <div x-show="showMore" x-transition class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-2">
            <input type="text" x-model="reference" placeholder="Receipt/reference number"
                   class="px-3 py-2 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
            <input type="text" x-model="note" placeholder="Settlement note"
                   class="px-3 py-2 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
        </div>
    </div>

    <!-- Success message -->
    <div x-show="successMsg" x-transition class="mt-3 p-3 bg-green-50 border border-green-200 rounded-lg text-xs font-semibold text-green-700" x-text="successMsg"></div>
</div>

<script>
function settlementModal() {
    return {
        amount: <?= (float)$debt['balance'] ?>,
        method: 'cash',
        date: '<?= date('Y-m-d') ?>',
        reference: '',
        note: '',
        showMore: false,
        submitting: false,
        successMsg: '',

        async recordSettlement() {
            if (this.amount <= 0) return;
            this.submitting = true;
            this.successMsg = '';

            const fd = new FormData();
            fd.append('amount', this.amount);
            fd.append('method', this.method);
            fd.append('settlement_date', this.date);
            fd.append('reference', this.reference);
            fd.append('note', this.note);
            fd.append('csrf_token', <?= json_encode(csrf_token()) ?>);

            try {
                const res = await fetch('<?= APP_URL ?>/debts/settle/<?= $debt['id'] ?>', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                if (data.success) {
                    this.successMsg = '<?= $currency ?>' + this.amount.toLocaleString() + ' settlement recorded!';
                    setTimeout(() => location.reload(), 1200);
                } else {
                    alert(data.error || 'Settlement failed');
                }
            } catch (e) {
                alert('Network error');
            }
            this.submitting = false;
        }
    };
}
</script>
<?php endif; ?>

<!-- Settlement History -->
<div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden mb-6">
    <div class="px-5 py-3 border-b border-brand-sand bg-brand-cream/30">
        <h3 class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Settlement History (<?= count($settlements) ?>)</h3>
    </div>
    <?php if (!empty($settlements)): ?>
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="border-b border-brand-sand bg-brand-cream/50">
                    <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Date</th>
                    <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-right">Amount</th>
                    <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Method</th>
                    <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Reference</th>
                    <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Note</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-brand-sand/50">
                <?php foreach ($settlements as $s): ?>
                    <tr class="hover:bg-brand-cream/30">
                        <td class="px-5 py-3 data-text text-[10px] text-brand-plum/60"><?= fmt_date($s['settlement_date'], 'd M Y') ?></td>
                        <td class="px-5 py-3 data-text text-xs font-bold text-green-600 text-right"><?= money((float)$s['amount'], $currency) ?></td>
                        <td class="px-5 py-3">
                            <?php if ($s['method']): ?>
                                <span class="data-text text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-gray-100 text-gray-600"><?= ucfirst(str_replace('_', ' ', $s['method'])) ?></span>
                            <?php else: ?>
                                <span class="text-[10px] text-brand-plum/30">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3 data-text text-[10px] text-brand-plum/50"><?= $s['reference'] ? e($s['reference']) : '—' ?></td>
                        <td class="px-5 py-3 text-[10px] text-brand-plum/50"><?= $s['note'] ? e($s['note']) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="p-6 text-center text-xs text-brand-plum/40">No settlements recorded yet.</div>
    <?php endif; ?>
</div>

<!-- Footer Actions -->
<div class="flex items-center justify-between mb-8">
    <?php if ($is_open): ?>
        <form method="POST" action="<?= APP_URL ?>/debts/write_off/<?= $debt['id'] ?>" class="inline"
              onsubmit="return confirm('Write off this debt? The remaining balance will be marked as unrecoverable.');">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            <button class="text-[10px] font-bold text-amber-500 uppercase tracking-widest hover:text-amber-700 transition-colors">Write Off Debt</button>
        </form>
    <?php else: ?>
        <div></div>
    <?php endif; ?>

    <?php if ($debt['source'] === 'manual' && empty($settlements)): ?>
        <form method="POST" action="<?= APP_URL ?>/debts/delete/<?= $debt['id'] ?>" class="inline"
              onsubmit="return confirm('Delete this debt record?');">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            <button class="text-[10px] font-bold text-red-400 uppercase tracking-widest hover:text-red-600 transition-colors">Delete</button>
        </form>
    <?php endif; ?>
</div>