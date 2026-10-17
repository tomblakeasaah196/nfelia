<?php
/**
 * Nfelia — Admin: MoMo Payment Review Dashboard
 * Phase 4 — Injected vars: $pending_payments, $reviewed_payments
 */
?>
<div class="space-y-8">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="font-heading font-bold text-2xl text-brand-plum">MoMo Payment Review</h1>
            <p class="text-xs text-brand-plum/60 mt-1">
                <?= count($pending_payments) ?> payment(s) awaiting verification · sorted oldest first.
            </p>
        </div>
        <?php if (count($pending_payments) > 0): ?>
            <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-bold bg-amber-100 text-amber-700">
                <?= count($pending_payments) ?> Pending
            </span>
        <?php endif; ?>
    </div>

    <!-- PENDING PAYMENTS -->
    <?php if (empty($pending_payments)): ?>
        <div class="bg-white border border-brand-sand rounded-2xl p-12 text-center">
            <svg class="w-12 h-12 text-brand-gold mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <h3 class="font-heading font-bold text-lg text-brand-plum mb-2">All Caught Up!</h3>
            <p class="text-sm text-brand-plum/60">No MoMo payments are awaiting review right now.</p>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($pending_payments as $p): ?>
            <div class="bg-white border border-brand-sand rounded-2xl p-6 shadow-sm" id="payment-<?= (int)$p['id'] ?>">
                <div class="flex flex-col lg:flex-row lg:items-start gap-6">

                    <!-- Business Info -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="text-xl"><?= e($p['flag_emoji'] ?? '🌐') ?></span>
                            <div>
                                <p class="font-heading font-bold text-sm text-brand-plum"><?= e($p['business_name']) ?></p>
                                <p class="text-[10px] text-brand-plum/50"><?= e($p['owner_name']) ?> · <?= e($p['owner_email']) ?></p>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3 text-xs">
                            <div>
                                <p class="text-[9px] text-brand-plum/40 uppercase tracking-widest font-bold mb-0.5">Amount</p>
                                <p class="font-heading font-bold text-base text-brand-burgundy">
                                    <?= e($p['currency_symbol']) ?><?= number_format($p['amount'], 0) ?>
                                    <span class="text-xs font-normal text-brand-plum/50"><?= e($p['currency_code']) ?></span>
                                </p>
                            </div>
                            <div>
                                <p class="text-[9px] text-brand-plum/40 uppercase tracking-widest font-bold mb-0.5">Submitted</p>
                                <p class="data-text text-xs font-semibold text-brand-plum"><?= fmt_date($p['submitted_at'], 'd M Y, H:i') ?></p>
                            </div>
                            <div>
                                <p class="text-[9px] text-brand-plum/40 uppercase tracking-widest font-bold mb-0.5">Reference No.</p>
                                <p class="data-text text-xs font-semibold text-brand-plum"><?= $p['reference_number'] ? e($p['reference_number']) : '<span class="text-brand-plum/30 italic">Not provided</span>' ?></p>
                            </div>
                            <div>
                                <p class="text-[9px] text-brand-plum/40 uppercase tracking-widest font-bold mb-0.5">Country</p>
                                <p class="text-xs font-semibold text-brand-plum"><?= e($p['country_name']) ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Receipt Thumbnail -->
                    <div class="shrink-0">
                        <?php
                        $receipt_ext = strtolower(pathinfo($p['receipt_path'] ?? '', PATHINFO_EXTENSION));
                        $is_img = in_array($receipt_ext, ['jpg','jpeg','png','webp']);
                        ?>
                        <a href="<?= APP_URL ?>/momo/receipt/<?= (int)$p['id'] ?>" target="_blank"
                           class="block w-24 h-24 border border-brand-sand rounded-xl overflow-hidden bg-brand-cream hover:border-brand-gold transition-colors">
                            <?php if ($is_img): ?>
                                <img src="<?= APP_URL ?>/momo/receipt/<?= (int)$p['id'] ?>"
                                     alt="Receipt" class="w-full h-full object-cover"
                                     onerror="this.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center text-brand-plum/30 text-[10px]\'>View</div>'">
                            <?php else: ?>
                                <div class="w-full h-full flex flex-col items-center justify-center text-brand-plum/50">
                                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                    <span class="text-[9px] font-bold uppercase mt-1">PDF</span>
                                </div>
                            <?php endif; ?>
                        </a>
                        <a href="<?= APP_URL ?>/momo/receipt/<?= (int)$p['id'] ?>" target="_blank"
                           class="block text-center text-[9px] font-bold text-brand-burgundy uppercase tracking-wider mt-1 hover:underline">
                            View Full
                        </a>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col gap-3 shrink-0 lg:w-36">
                        <button onclick="approvePayment(<?= (int)$p['id'] ?>, this)"
                                class="w-full bg-green-600 text-white text-xs font-bold py-3 px-4 rounded-xl hover:bg-green-700 transition-colors uppercase tracking-wider">
                            ✓ Approve
                        </button>
                        <button onclick="document.getElementById('rejectForm-<?= (int)$p['id'] ?>').classList.toggle('hidden')"
                                class="w-full bg-red-50 text-red-600 border border-red-200 text-xs font-bold py-3 px-4 rounded-xl hover:bg-red-100 transition-colors uppercase tracking-wider">
                            ✕ Reject
                        </button>
                    </div>
                </div>

                <!-- Reject Form (hidden by default) -->
                <div id="rejectForm-<?= (int)$p['id'] ?>" class="hidden mt-4 pt-4 border-t border-brand-sand">
                    <label class="block text-[10px] font-bold text-red-600 uppercase tracking-widest mb-2">Rejection Reason (sent to customer) *</label>
                    <textarea id="rejectReason-<?= (int)$p['id'] ?>" rows="3" placeholder="e.g. Receipt is unclear. Please send a screenshot showing the amount and your phone number."
                              class="w-full px-4 py-3 border border-red-200 rounded-xl text-sm focus:border-red-400 outline-none resize-none mb-3"></textarea>
                    <div class="flex gap-3">
                        <button onclick="rejectPayment(<?= (int)$p['id'] ?>, this)"
                                class="bg-red-600 text-white text-xs font-bold py-2.5 px-5 rounded-xl hover:bg-red-700 transition-colors uppercase tracking-wider">
                            Confirm Rejection
                        </button>
                        <button onclick="document.getElementById('rejectForm-<?= (int)$p['id'] ?>').classList.add('hidden')"
                                class="text-xs font-semibold text-brand-plum/50 hover:text-brand-plum uppercase tracking-wider">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- RECENTLY REVIEWED -->
    <?php if (!empty($reviewed_payments)): ?>
    <div>
        <h2 class="font-heading font-bold text-base text-brand-plum uppercase tracking-wider mb-4">Recently Reviewed</h2>
        <div class="bg-white border border-brand-sand rounded-2xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-brand-cream/50 border-b border-brand-sand">
                            <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Business</th>
                            <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-right">Amount</th>
                            <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Submitted</th>
                            <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Reviewed By</th>
                            <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-center">Result</th>
                            <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Receipt</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-sand/50">
                        <?php foreach ($reviewed_payments as $r): ?>
                        <tr>
                            <td class="px-5 py-3">
                                <p class="text-xs font-semibold text-brand-plum"><?= e($r['business_name']) ?></p>
                                <p class="text-[10px] text-brand-plum/50"><?= e($r['owner_name']) ?></p>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <p class="data-text text-xs font-bold text-brand-plum"><?= e($r['currency_symbol']) ?><?= number_format($r['amount'], 0) ?></p>
                            </td>
                            <td class="px-5 py-3">
                                <p class="data-text text-[10px] text-brand-plum/70"><?= fmt_date($r['submitted_at'], 'd M Y') ?></p>
                            </td>
                            <td class="px-5 py-3">
                                <p class="text-xs text-brand-plum/70"><?= e($r['reviewer_name'] ?? '—') ?></p>
                            </td>
                            <td class="px-5 py-3 text-center">
                                <span class="text-[9px] font-bold uppercase tracking-wider px-2 py-1 rounded-full
                                    <?= $r['status'] === 'approved' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                                    <?= $r['status'] === 'approved' ? 'Approved' : 'Rejected' ?>
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                <a href="<?= APP_URL ?>/momo/receipt/<?= (int)$r['id'] ?>" target="_blank"
                                   class="text-[10px] font-bold text-brand-burgundy hover:underline uppercase tracking-wider">View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
const CSRF = '<?= e(csrf_token()) ?>';

function approvePayment(id, btn) {
    if (!confirm('Approve this payment and activate the subscription?')) return;
    btn.disabled = true;
    btn.textContent = 'Processing...';
    const form = new FormData();
    form.append('payment_id', id);
    form.append('csrf_token', CSRF);
    fetch('<?= APP_URL ?>/momo/approve', { method: 'POST', body: form })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                const el = document.getElementById('payment-' + id);
                el.innerHTML = '<div class="p-4 bg-green-50 border border-green-200 rounded-xl text-sm text-green-700 font-semibold">✓ Payment approved. Subscription activated.</div>';
            } else {
                alert(d.error || 'Failed to approve.');
                btn.disabled = false;
                btn.textContent = '✓ Approve';
            }
        })
        .catch(() => { alert('Network error.'); btn.disabled = false; btn.textContent = '✓ Approve'; });
}

function rejectPayment(id, btn) {
    const reason = document.getElementById('rejectReason-' + id).value.trim();
    if (!reason) { alert('Please provide a rejection reason.'); return; }
    if (!confirm('Reject this payment and notify the customer?')) return;
    btn.disabled = true;
    btn.textContent = 'Processing...';
    const form = new FormData();
    form.append('payment_id', id);
    form.append('reason', reason);
    form.append('csrf_token', CSRF);
    fetch('<?= APP_URL ?>/momo/reject', { method: 'POST', body: form })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                const el = document.getElementById('payment-' + id);
                el.innerHTML = '<div class="p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700 font-semibold">✕ Payment rejected. Customer notified via email.</div>';
            } else {
                alert(d.error || 'Failed to reject.');
                btn.disabled = false;
                btn.textContent = 'Confirm Rejection';
            }
        })
        .catch(() => { alert('Network error.'); btn.disabled = false; btn.textContent = 'Confirm Rejection'; });
}
</script>
