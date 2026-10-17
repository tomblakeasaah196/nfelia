<?php
/**
 * Nfelia — Invoice Detail View + Document Preview + Payment Recording
 * Renders inside views/layouts/app.php
 *
 * Available from controller:
 *   $user, $business, $invoice, $customer, $items, $bank,
 *   $payments, $quotation, $public_url
 */
$currency = $business['currency_symbol'] ?? '₦';
$font_map = FONT_PAIRINGS[$business['font_pairing'] ?? 'modern_sans'] ?? FONT_PAIRINGS['modern_sans'];

// Status config
$status_cfg = match($invoice['status']) {
    'draft'          => ['bg-gray-100 text-gray-700 border-gray-300', 'Draft', 'gray'],
    'sent'           => ['bg-blue-100 text-blue-700 border-blue-300', 'Sent', 'blue'],
    'partially_paid' => ['bg-amber-100 text-amber-700 border-amber-300', 'Partially Paid', 'amber'],
    'paid'           => ['bg-green-100 text-green-700 border-green-300', 'Paid', 'green'],
    'overdue'        => ['bg-red-100 text-red-700 border-red-300', 'Overdue', 'red'],
    'cancelled'      => ['bg-gray-200 text-gray-500 border-gray-400', 'Cancelled', 'gray'],
    default          => ['bg-gray-100 text-gray-700 border-gray-300', ucfirst($invoice['status']), 'gray'],
};

// Payment watermark text
$watermark_text = match($invoice['status']) {
    'paid'           => 'PAID',
    'partially_paid' => 'PARTIALLY PAID',
    'cancelled'      => 'CANCELLED',
    default          => '',
};

// WhatsApp share message
$wa_number = $customer ? ($customer['whatsapp'] ?? $customer['phone'] ?? '') : '';
$wa_number = preg_replace('/[^0-9]/', '', $wa_number);
if (str_starts_with($wa_number, '0')) $wa_number = '234' . substr($wa_number, 1);
$wa_text = urlencode(
    "Hello" . ($customer ? ' ' . $customer['full_name'] : '') . "!\n\n"
    . "Please find your *Commercial Invoice* from *{$business['name']}*:\n\n"
    . "{$invoice['doc_number']}\n"
    . "Total: {$currency}" . number_format((float)$invoice['total'], 2) . "\n"
    . "Balance Due: {$currency}" . number_format((float)$invoice['balance_due'], 2) . "\n"
    . ($invoice['due_date'] ? "Due Date: " . fmt_date($invoice['due_date'], 'd M Y') . "\n" : '')
    . "\nView & pay here:\n{$public_url}\n\n"
    . "_Sent via Nfelia — nfelia.com_"
);
$wa_link = "https://wa.me/{$wa_number}?text={$wa_text}";

// Payment progress
$total_val = max(0.01, (float)$invoice['total']);
$paid_val  = (float)$invoice['amount_paid'];
$pct_paid  = min(100, round($paid_val / $total_val * 100));

// Days overdue
$days_overdue = 0;
if ($invoice['due_date'] && in_array($invoice['status'], ['overdue','sent','partially_paid'])) {
    $diff = (int)((time() - strtotime($invoice['due_date'])) / 86400);
    if ($diff > 0) $days_overdue = $diff;
}
?>

<!-- Alpine scope for payment modal -->
<div x-data="invoiceView()" x-cloak>

<!-- Breadcrumb + Actions -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div class="flex items-center gap-3">
        <a href="<?= APP_URL ?>/invoices" class="text-brand-plum/40 hover:text-brand-plum transition-colors">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="font-heading font-extrabold text-xl text-brand-plum tracking-tight"><?= e($invoice['doc_number']) ?></h1>
            <div class="flex items-center gap-2 mt-0.5">
                <span class="data-text text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded <?= $status_cfg[0] ?>"><?= $status_cfg[1] ?></span>
                <?php if ($days_overdue > 0): ?>
                    <span class="data-text text-[9px] font-bold text-red-600"><?= $days_overdue ?> day<?= $days_overdue > 1 ? 's' : '' ?> overdue</span>
                <?php endif; ?>
                <?php if ($quotation): ?>
                    <a href="<?= APP_URL ?>/quotations/view/<?= $quotation['id'] ?>" class="data-text text-[9px] font-bold text-purple-500 hover:text-purple-700">
                        from <?= e($quotation['doc_number']) ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Action buttons -->
    <div class="flex flex-wrap items-center gap-2">
        <?php if (in_array($invoice['status'], ['draft', 'sent'])): ?>
            <a href="<?= APP_URL ?>/invoices/edit/<?= $invoice['id'] ?>" class="flex items-center gap-1.5 bg-brand-white border border-brand-sand hover:border-brand-gold px-3 py-2 rounded-lg transition-all">
                <svg class="w-3.5 h-3.5 text-brand-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                <span class="text-[10px] font-bold text-brand-plum uppercase tracking-widest">Edit</span>
            </a>
        <?php endif; ?>
        

        <?php if ($invoice['status'] === 'draft'): ?>
            <form method="POST" action="<?= APP_URL ?>/invoices/send/<?= $invoice['id'] ?>" class="inline"
                  onsubmit="return confirm('Mark this invoice as sent? Stock will be deducted for physical products.');">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <button class="flex items-center gap-1.5 btn-primary text-white px-3 py-2 rounded-lg transition-all">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    <span class="text-[10px] font-bold uppercase tracking-widest">Mark Sent</span>
                </button>
            </form>
        <?php endif; ?>

        <?php if (in_array($invoice['status'], ['sent', 'partially_paid', 'overdue'])): ?>
            <button @click="showPaymentModal = true" class="flex items-center gap-1.5 bg-green-500 text-white px-3 py-2 rounded-lg hover:bg-green-600 transition-all">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="text-[10px] font-bold uppercase tracking-widest">Record Payment</span>
            </button>
        <?php endif; ?>

        <?php if ($invoice['status'] === 'overdue'): ?>
            <a href="<?= APP_URL ?>/invoices/send_reminder/<?= $invoice['id'] ?>" class="flex items-center gap-1.5 bg-amber-500 text-white px-3 py-2 rounded-lg hover:bg-amber-600 transition-all">
                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                <span class="text-[10px] font-bold uppercase tracking-widest">Send Reminder</span>
            </a>
        <?php endif; ?>
        
        <!-- Create Delivery / Dispatch -->
        <?php if (!in_array($invoice['status'], ['cancelled', 'draft'])): ?>
            <a href="<?= APP_URL ?>/deliveries/create?invoice_id=<?= $invoice['id'] ?>" class="flex items-center gap-1.5 bg-brand-white border border-brand-sand hover:border-brand-gold px-3 py-2 rounded-lg transition-all">
                <svg class="w-3.5 h-3.5 text-brand-plum/60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a2 2 0 104 0m-4 0a2 2 0 11-4 0"/></svg>
                <span class="text-[10px] font-bold text-brand-plum uppercase tracking-widest">Create Delivery</span>
            </a>
        <?php endif; ?>

        <?php if (!in_array($invoice['status'], ['cancelled', 'draft'])): ?>
            <form method="POST" action="<?= APP_URL ?>/invoices/cancel/<?= $invoice['id'] ?>" class="inline"
                  onsubmit="return confirm('Cancel this invoice? Stock will be restored and any debt records cleared.');">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <button class="flex items-center gap-1.5 bg-brand-white border border-red-200 hover:border-red-400 text-red-600 px-3 py-2 rounded-lg transition-all">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                    <span class="text-[10px] font-bold uppercase tracking-widest">Cancel</span>
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Payment Progress Bar -->
<?php if ($invoice['status'] !== 'draft' && $invoice['status'] !== 'cancelled'): ?>
<div class="bg-brand-white border border-brand-sand rounded-xl p-4 mb-6 shadow-sm">
    <div class="flex items-center justify-between mb-2">
        <div class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Payment Progress</div>
        <div class="text-xs font-bold text-brand-plum"><?= $pct_paid ?>%</div>
    </div>
    <div class="w-full h-3 bg-brand-cream rounded-full overflow-hidden">
        <div class="h-full rounded-full transition-all duration-500 <?= $pct_paid >= 100 ? 'bg-green-500' : ($pct_paid > 0 ? 'bg-amber-500' : 'bg-gray-300') ?>"
             style="width: <?= $pct_paid ?>%;"></div>
    </div>
    <div class="flex items-center justify-between mt-2">
        <div class="data-text text-[10px] text-brand-plum/50">
            Paid: <span class="font-bold text-green-600"><?= money($paid_val, $currency) ?></span>
        </div>
        <div class="data-text text-[10px] text-brand-plum/50">
            Balance: <span class="font-bold <?= (float)$invoice['balance_due'] > 0 ? 'text-red-600' : 'text-green-600' ?>"><?= money((float)$invoice['balance_due'], $currency) ?></span>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Delivery: the promise to the customer (Tasks & Calendar) -->
<?php if ($invoice['status'] !== 'cancelled'):
    $today_lagos   = planner_today();
    $is_delivered  = !empty($invoice['delivered_at']) || !empty($delivered_by_note);
    $deliver_count = (!$is_delivered && $invoice['deliver_by']) ? planner_countdown($invoice['deliver_by'], $today_lagos, 'Deliver') : null;
    $tone_cls = ['late' => 'bg-[#FEE4E2] text-[#B42318]', 'today' => 'bg-[#FEE4E2] text-[#B42318]', 'soon' => 'bg-[#FEF0C7] text-[#93370D]', 'later' => 'bg-brand-cream text-brand-plum/70'];
?>
<div class="bg-brand-white border rounded-xl p-4 mb-6 shadow-sm <?= !$invoice['deliver_mode'] && !$is_delivered ? 'border-[#F7D9A8]' : 'border-brand-sand' ?>"
     x-data="{ editing: <?= !$invoice['deliver_mode'] && !$is_delivered ? 'true' : 'false' ?> }">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0">
            <?php if ($is_delivered): ?>
                <p class="text-sm font-semibold text-[#00764A]">
                    Delivered<?= !empty($invoice['delivered_at']) ? ' on ' . e(date('D j M', strtotime($invoice['delivered_at']))) : ' (signed delivery note)' ?>
                </p>
            <?php elseif ($invoice['deliver_mode']): ?>
                <p class="text-sm font-semibold text-brand-plum">
                    <?php $dday = planner_fmt_day($invoice['deliver_by'], $today_lagos); ?>
                    Deliver <?= $invoice['deliver_mode'] === 'immediate' ? 'immediately' : (in_array($dday, ['Today', 'Tomorrow'], true) ? strtolower($dday) : 'by ' . e($dday)) ?>
                    <?php if ($deliver_count && !in_array($deliver_count['label'], ['Deliver today', 'Deliver tomorrow'], true)): ?>
                        <span class="ml-1 px-2 py-0.5 rounded-full text-xs font-semibold <?= $tone_cls[$deliver_count['tone']] ?>"><?= e($deliver_count['label']) ?></span>
                    <?php endif; ?>
                </p>
                <p class="text-xs text-brand-plum/60 mt-0.5"><?= $invoice['deliver_show'] ? 'The customer sees this date on their invoice.' : 'Only you see this date.' ?></p>
            <?php else: ?>
                <p class="text-sm font-semibold text-brand-plum">When will <?= e(planner_first_name($customer['full_name'] ?? '') ?: 'the customer') ?> get this order?</p>
                <p class="text-xs text-brand-plum/60 mt-0.5">Set it once and it shows on your Tasks &amp; Calendar with a countdown.</p>
            <?php endif; ?>
            <?php if ($job_plan): ?>
                <a href="<?= APP_URL ?>/tasks" class="inline-block mt-1 text-xs font-semibold text-brand-burgundy underline underline-offset-2"><?= e($job_plan['title']) ?>: <?= (int)$job_plan['done'] ?> of <?= (int)$job_plan['total'] ?> steps done</a>
            <?php endif; ?>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <?php if ($is_delivered && !empty($invoice['delivered_at'])): ?>
                <form method="POST" action="<?= APP_URL ?>/invoices/mark-delivered/<?= $invoice['id'] ?>" class="inline">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="undo" value="1">
                    <button class="h-11 px-3 rounded-lg border border-brand-sand text-xs font-semibold text-brand-plum/70 hover:border-brand-gold">Mark not delivered</button>
                </form>
            <?php elseif (!$is_delivered && $invoice['deliver_mode']): ?>
                <form method="POST" action="<?= APP_URL ?>/invoices/mark-delivered/<?= $invoice['id'] ?>" class="inline">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <button class="h-11 px-4 rounded-lg bg-[#00764A] text-white text-xs font-semibold hover:opacity-90">Mark delivered</button>
                </form>
                <button type="button" @click="editing = !editing" :aria-expanded="editing" class="h-11 px-3 rounded-lg border border-brand-sand text-xs font-semibold text-brand-plum hover:border-brand-gold">Change date</button>
                <?php if (!$job_plan): ?>
                    <a href="<?= APP_URL ?>/tasks?invoice=<?= $invoice['id'] ?>" class="h-11 px-3 inline-flex items-center rounded-lg border border-brand-sand text-xs font-semibold text-brand-plum hover:border-brand-gold">Plan the job</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php if (!$is_delivered): ?>
    <form x-show="editing" method="POST" action="<?= APP_URL ?>/invoices/set-delivery/<?= $invoice['id'] ?>" class="mt-4 pt-4 border-t border-brand-sand space-y-3">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <?php
        $df = ['mode' => $invoice['deliver_mode'] ?? '', 'date' => $invoice['deliver_by'] ?? '', 'show' => !empty($invoice['deliver_show']), 'handed' => true, 'id' => 'inv-view', 'legend_hidden' => !$invoice['deliver_mode']];
        require BASE_PATH . '/views/partials/deliver_fields.php';
        ?>
        <button class="h-11 px-5 rounded-lg btn-primary text-white text-xs font-semibold">Save delivery</button>
    </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Share Actions Bar -->
<div class="bg-brand-white border border-brand-sand rounded-xl p-4 mb-6 shadow-sm flex flex-wrap items-center gap-3">
    <!-- Copy Link -->
    <button onclick="navigator.clipboard.writeText('<?= e($public_url) ?>'); this.querySelector('span').textContent = 'Copied!'; setTimeout(() => this.querySelector('span').textContent = 'Copy Link', 2000);"
            class="flex items-center gap-1.5 bg-brand-cream border border-brand-sand hover:border-brand-gold px-3 py-2 rounded-lg transition-all">
        <svg class="w-3.5 h-3.5 text-brand-plum/50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
        <span class="text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest">Copy Link</span>
    </button>

    <!-- WhatsApp -->
    <a href="<?= $wa_link ?>" target="_blank" class="flex items-center gap-1.5 bg-green-500 text-white px-3 py-2 rounded-lg hover:bg-green-600 transition-all">
        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
        <span class="text-[10px] font-bold uppercase tracking-widest">WhatsApp</span>
    </a>

    <!-- Download PDF -->
    <button onclick="downloadPDF()" class="flex items-center gap-1.5 bg-brand-cream border border-brand-sand hover:border-brand-gold px-3 py-2 rounded-lg transition-all">
        <svg class="w-3.5 h-3.5 text-brand-plum/50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
        <span class="text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest">Download PDF</span>
    </button>

    <!-- Open Public -->
    <a href="<?= e($public_url) ?>" target="_blank" class="flex items-center gap-1.5 bg-brand-cream border border-brand-sand hover:border-brand-gold px-3 py-2 rounded-lg transition-all">
        <svg class="w-3.5 h-3.5 text-brand-plum/50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
        <span class="text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest">Public View</span>
    </a>

    <!-- Delete (drafts only) -->
    <?php if ($invoice['status'] === 'draft'): ?>
    <form method="POST" action="<?= APP_URL ?>/invoices/delete/<?= $invoice['id'] ?>" class="inline ml-auto"
          onsubmit="return confirm('Permanently delete this invoice?');">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
        <button class="text-[10px] font-bold text-red-400 uppercase tracking-widest hover:text-red-600 transition-colors">Delete</button>
    </form>
    <?php endif; ?>
</div>

<!-- Payment History -->
<?php if (!empty($payments)): ?>
<div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden mb-6">
    <div class="px-5 py-3 border-b border-brand-sand bg-brand-cream/50">
        <h2 class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Payment History — <?= count($payments) ?> payment<?= count($payments) > 1 ? 's' : '' ?></h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="border-b border-brand-sand/50">
                    <th class="px-5 py-2.5 text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest">Date</th>
                    <th class="px-5 py-2.5 text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest text-right">Amount</th>
                    <th class="px-5 py-2.5 text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest">Method</th>
                    <th class="px-5 py-2.5 text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest">Reference</th>
                    <th class="px-5 py-2.5 text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest">Receipt</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-brand-sand/30">
                <?php foreach ($payments as $pay): ?>
                    <tr class="hover:bg-brand-cream/20">
                        <td class="px-5 py-3 data-text text-[10px] text-brand-plum/60"><?= fmt_date($pay['payment_date'], 'd M Y') ?></td>
                        <td class="px-5 py-3 data-text text-xs font-bold text-green-600 text-right"><?= money((float)$pay['amount'], $currency) ?></td>
                        <td class="px-5 py-3">
                            <span class="data-text text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-gray-100 text-gray-600"><?= ucfirst(str_replace('_', ' ', $pay['method'])) ?></span>
                        </td>
                        <td class="px-5 py-3 data-text text-[10px] text-brand-plum/50"><?= $pay['reference'] ? e($pay['reference']) : '—' ?></td>
                        <td class="px-5 py-3">
                            <?php if ($pay['receipt_id']): ?>
                                <a href="<?= APP_URL ?>/receipts/view/<?= $pay['receipt_id'] ?>" class="text-[10px] font-bold text-brand-gold uppercase tracking-widest hover:text-brand-burgundy"><?= e($pay['receipt_number']) ?></a>
                            <?php else: ?>
                                <a href="<?= APP_URL ?>/receipts/from_payment/<?= $pay['id'] ?>" class="text-[10px] font-bold text-purple-500 uppercase tracking-widest hover:text-purple-700">Generate</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ===== DOCUMENT PREVIEW ===== -->
<div id="invoice-document" class="bg-white border border-brand-sand rounded-xl shadow-sm overflow-hidden mb-8 relative">

    <!-- Payment Status Watermark -->
    <?php if ($watermark_text): ?>
    <div class="absolute inset-0 flex items-center justify-center pointer-events-none z-10 overflow-hidden">
        <div class="transform -rotate-45 text-[80px] sm:text-[120px] font-heading font-extrabold uppercase tracking-[.2em] opacity-[0.06] whitespace-nowrap
            <?= $invoice['status'] === 'paid' ? 'text-green-600' : ($invoice['status'] === 'cancelled' ? 'text-gray-600' : 'text-amber-600') ?>">
            <?= $watermark_text ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Document Header (Branded) -->
    <div class="p-8 sm:p-10 relative z-20" style="border-top: 4px solid <?= e($business['primary_color'] ?? '#8B004A') ?>;">

        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-6 mb-8">
            <!-- Business Info (Left) -->
            <div>
                <div class="flex items-center gap-3 mb-3">
                    <?php if ($business['logo_path']): ?>
                        <img src="<?= APP_URL ?>/<?= e($business['logo_path']) ?>" alt="" class="h-12 w-12 object-contain rounded">
                    <?php endif; ?>
                    <div>
                        <h2 class="font-heading font-extrabold text-lg" style="color: <?= e($business['primary_color'] ?? '#8B004A') ?>;"><?= e($business['name']) ?></h2>
                        <?php if ($business['tagline']): ?>
                            <p class="text-[10px] text-gray-500"><?= e($business['tagline']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="text-[10px] text-gray-500 space-y-0.5">
                    <?php if ($business['address']): ?><div><?= e($business['address']) ?></div><?php endif; ?>
                    <?php if ($business['phone']): ?><div>Tel: <?= e($business['phone']) ?></div><?php endif; ?>
                    <?php if ($business['email']): ?><div><?= e($business['email']) ?></div><?php endif; ?>
                    <?php if (!empty($business['registration_number'])): ?><div>Reg: <?= e($business['registration_number']) ?></div><?php endif; ?>
                </div>
            </div>

            <!-- Doc Info (Right) -->
            <div class="text-right">
                <h1 class="font-heading font-extrabold text-2xl uppercase tracking-wider" style="color: <?= e($business['primary_color'] ?? '#8B004A') ?>;">Commercial Invoice</h1>
                <div class="data-text text-sm font-bold text-gray-700 mt-1"><?= e($invoice['doc_number']) ?></div>
                <div class="text-[10px] text-gray-500 mt-2 space-y-0.5">
                    <div>Issued: <?= fmt_date($invoice['issue_date'], 'd M Y') ?></div>
                    <?php if ($invoice['due_date']): ?>
                        <div class="<?= $days_overdue > 0 ? 'text-red-600 font-bold' : '' ?>">Due: <?= fmt_date($invoice['due_date'], 'd M Y') ?></div>
                    <?php endif; ?>
                    <?php if ($delivery_line = planner_delivery_line($invoice)): ?>
                        <div><?= e($delivery_line) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Client Block -->
        <?php if ($customer): ?>
        <div class="mb-8 p-4 rounded-lg" style="background: <?= e($business['primary_color'] ?? '#8B004A') ?>0A;">
            <div class="text-[9px] font-bold uppercase tracking-widest mb-1" style="color: <?= e($business['primary_color'] ?? '#8B004A') ?>;">Bill To</div>
            <div class="text-sm font-bold text-gray-800"><?= e($customer['full_name']) ?></div>
            <?php if (!empty($customer['business_name'])): ?><div class="text-[10px] text-gray-600"><?= e($customer['business_name']) ?></div><?php endif; ?>
            <?php if (!empty($customer['phone'])): ?><div class="text-[10px] text-gray-500"><?= e($customer['phone']) ?></div><?php endif; ?>
            <?php if (!empty($customer['email'])): ?><div class="text-[10px] text-gray-500"><?= e($customer['email']) ?></div><?php endif; ?>
            <?php if (!empty($customer['address'])): ?><div class="text-[10px] text-gray-500"><?= e($customer['address']) ?></div><?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Line Items Table -->
        <div class="overflow-x-auto mb-6">
            <table class="w-full text-left">
                <thead>
                    <tr style="border-bottom: 2px solid <?= e($business['primary_color'] ?? '#8B004A') ?>;">
                        <th class="pb-2 text-[9px] font-bold uppercase tracking-widest text-gray-500">#</th>
                        <th class="pb-2 text-[9px] font-bold uppercase tracking-widest text-gray-500">Description</th>
                        <th class="pb-2 text-[9px] font-bold uppercase tracking-widest text-gray-500 text-center">Qty</th>
                        <th class="pb-2 text-[9px] font-bold uppercase tracking-widest text-gray-500 text-right">Unit Price</th>
                        <th class="pb-2 text-[9px] font-bold uppercase tracking-widest text-gray-500 text-center">Discount</th>
                        <th class="pb-2 text-[9px] font-bold uppercase tracking-widest text-gray-500 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($items as $idx => $it): ?>
                        <tr>
                            <td class="py-3 text-[10px] text-gray-400"><?= $idx + 1 ?></td>
                            <td class="py-3">
                                <div class="text-xs font-semibold text-gray-800"><?= e($it['description']) ?></div>
                                <?php if (!empty($it['product_sku'])): ?>
                                    <div class="data-text text-[9px] text-gray-400"><?= e($it['product_sku']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 data-text text-xs text-gray-700 text-center"><?= number_format((float)$it['quantity'], 2) ?></td>
                            <td class="py-3 data-text text-xs text-gray-700 text-right"><?= money((float)$it['unit_price'], $currency) ?></td>
                            <td class="py-3 data-text text-xs text-gray-500 text-center"><?= (float)$it['discount_percent'] > 0 ? number_format((float)$it['discount_percent'], 1) . '%' : '—' ?></td>
                            <td class="py-3 data-text text-xs font-bold text-gray-800 text-right"><?= money((float)$it['subtotal'], $currency) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Totals -->
        <div class="flex justify-end">
            <div class="w-72 space-y-2">
                <div class="flex justify-between text-xs">
                    <span class="text-gray-500">Subtotal</span>
                    <span class="data-text font-bold text-gray-700"><?= money((float)$invoice['subtotal'], $currency) ?></span>
                </div>
                <?php if ((float)$invoice['discount_amount'] > 0): ?>
                    <div class="flex justify-between text-xs">
                        <span class="text-gray-500">Discount</span>
                        <span class="data-text font-bold text-red-600">-<?= money((float)$invoice['discount_amount'], $currency) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ((float)$invoice['tax_rate'] > 0): ?>
                    <div class="flex justify-between text-xs">
                        <span class="text-gray-500">Tax (<?= number_format((float)$invoice['tax_rate'], 1) ?>%)</span>
                        <span class="data-text font-bold text-gray-700"><?= money((float)$invoice['tax_amount'], $currency) ?></span>
                    </div>
                <?php endif; ?>
                <div class="flex justify-between text-sm pt-2" style="border-top: 2px solid <?= e($business['primary_color'] ?? '#8B004A') ?>;">
                    <span class="font-heading font-bold uppercase tracking-wider" style="color: <?= e($business['primary_color'] ?? '#8B004A') ?>;">Grand Total</span>
                    <span class="font-heading font-extrabold text-lg" style="color: <?= e($business['primary_color'] ?? '#8B004A') ?>;"><?= money((float)$invoice['total'], $currency) ?></span>
                </div>

                <!-- Amount Paid / Balance Due -->
                <?php if ((float)$invoice['amount_paid'] > 0): ?>
                    <div class="flex justify-between text-xs pt-1">
                        <span class="text-green-600 font-semibold">Amount Paid</span>
                        <span class="data-text font-bold text-green-600">-<?= money((float)$invoice['amount_paid'], $currency) ?></span>
                    </div>
                    <div class="flex justify-between text-sm pt-1" style="border-top: 1px dashed <?= e($business['primary_color'] ?? '#8B004A') ?>66;">
                        <span class="font-heading font-bold uppercase tracking-wider <?= (float)$invoice['balance_due'] > 0 ? 'text-red-600' : 'text-green-600' ?>">Balance Due</span>
                        <span class="font-heading font-extrabold text-lg <?= (float)$invoice['balance_due'] > 0 ? 'text-red-600' : 'text-green-600' ?>">
                            <?= (float)$invoice['balance_due'] > 0 ? money((float)$invoice['balance_due'], $currency) : 'PAID' ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bank Details -->
        <?php if ($bank): ?>
        <div class="mt-8 p-4 rounded-lg bg-gray-50 border border-gray-200">
            <div class="text-[9px] font-bold uppercase tracking-widest text-gray-500 mb-2">Payment Details</div>
            <div class="data-text text-xs text-gray-700 space-y-0.5">
                <div>Bank: <?= e($bank['bank_name']) ?></div>
                <div>Account Name: <?= e($bank['account_name']) ?></div>
                <div>Account Number: <strong><?= e($bank['account_number']) ?></strong></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Payment Terms -->
        <?php if ($invoice['payment_terms']): ?>
        <div class="mt-6">
            <div class="text-[9px] font-bold uppercase tracking-widest text-gray-500 mb-1">Payment Terms</div>
            <p class="text-xs text-gray-600 leading-relaxed whitespace-pre-line"><?= e($invoice['payment_terms']) ?></p>
        </div>
        <?php endif; ?>

        <!-- Notes -->
        <?php if ($invoice['notes']): ?>
        <div class="mt-4">
            <div class="text-[9px] font-bold uppercase tracking-widest text-gray-500 mb-1">Notes</div>
            <p class="text-xs text-gray-600 leading-relaxed whitespace-pre-line"><?= e($invoice['notes']) ?></p>
        </div>
        <?php endif; ?>

        <!-- Signature Block (Business Owner Only) -->
        <div class="mt-10 pt-6 border-t border-gray-200">
            <div class="max-w-xs">
                <div class="border-b border-gray-300 pb-8 mb-2"></div>
                <div class="text-[9px] font-bold uppercase tracking-widest text-gray-500">Authorized By</div>
                <div class="text-xs text-gray-700 mt-0.5"><?= e($business['name']) ?></div>
            </div>
        </div>

        <!-- Footer -->
        <?php if ($business['tagline']): ?>
        <div class="mt-8 text-center">
            <p class="text-[9px] uppercase tracking-[.3em] font-bold" style="color: <?= e($business['secondary_color'] ?? '#B69F5E') ?>;"><?= e($business['tagline']) ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== PAYMENT MODAL ===== -->
<div x-show="showPaymentModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(0,0,0,.45);">

    <div @click.outside="showPaymentModal = false" @keydown.escape.window="showPaymentModal = false"
         x-show="showPaymentModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         class="bg-brand-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden">

        <!-- Modal Header -->
        <div class="px-6 py-4 border-b border-brand-sand" style="background: <?= e($business['primary_color'] ?? '#8B004A') ?>0A;">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="font-heading font-bold text-lg text-brand-plum">Record Payment</h3>
                    <p class="text-[10px] text-brand-plum/50 font-medium mt-0.5"><?= e($invoice['doc_number']) ?> — Balance: <strong class="text-red-600"><?= money((float)$invoice['balance_due'], $currency) ?></strong></p>
                </div>
                <button @click="showPaymentModal = false" class="text-brand-plum/30 hover:text-brand-plum transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        <!-- Modal Body -->
        <form @submit.prevent="submitPayment" class="p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

            <!-- Amount -->
            <div>
                <label class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest block mb-1">Amount <span class="text-red-500">*</span></label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-brand-plum/40"><?= $currency ?></span>
                    <input type="number" x-model="payForm.amount" step="0.01" min="0.01" max="<?= (float)$invoice['balance_due'] ?>"
                           class="w-full pl-8 pr-3 py-2.5 text-sm font-bold bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum"
                           placeholder="0.00" required>
                </div>
                <!-- Quick amount buttons -->
                <div class="flex gap-2 mt-2">
                    <button type="button" @click="payForm.amount = <?= (float)$invoice['balance_due'] ?>"
                            class="text-[9px] font-bold text-brand-gold uppercase tracking-widest bg-brand-cream border border-brand-sand px-2 py-1 rounded hover:border-brand-gold">Full Balance</button>
                    <?php if ((float)$invoice['balance_due'] > 1000): ?>
                        <button type="button" @click="payForm.amount = Math.round(<?= (float)$invoice['balance_due'] ?> / 2 * 100) / 100"
                                class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest bg-brand-cream border border-brand-sand px-2 py-1 rounded hover:border-brand-gold">Half</button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Date + Method -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest block mb-1">Date</label>
                    <input type="date" x-model="payForm.payment_date"
                           class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
                </div>
                <div>
                    <label class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest block mb-1">Method</label>
                    <select x-model="payForm.method"
                            class="w-full px-3 py-2.5 text-xs font-semibold bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
                        <option value="cash">Cash</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="pos">POS</option>
                        <option value="mobile_money">Mobile Money</option>
                        <option value="cheque">Cheque</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>

            <!-- Reference -->
            <div>
                <label class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest block mb-1">Reference / Transaction ID</label>
                <input type="text" x-model="payForm.reference" placeholder="e.g. TRF-12345"
                       class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/30">
            </div>

            <!-- Note -->
            <div>
                <label class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest block mb-1">Note</label>
                <textarea x-model="payForm.note" rows="2" placeholder="Optional note..."
                          class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/30 resize-none"></textarea>
            </div>

            <!-- Error -->
            <div x-show="payError" x-text="payError" class="text-xs font-semibold text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2"></div>

            <!-- Submit -->
            <button type="submit" :disabled="payLoading"
                    class="w-full btn-primary text-white py-3 rounded-xl text-[10px] font-bold uppercase tracking-widest shadow-sm hover:shadow-md transition-all disabled:opacity-50">
                <span x-show="!payLoading">Record Payment</span>
                <span x-show="payLoading">Processing...</span>
            </button>
        </form>
    </div>
</div>

</div><!-- end Alpine scope -->

<!-- html2pdf.js for PDF download -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function downloadPDF() {
    const element = document.getElementById('invoice-document');
    const opt = {
        margin:       [0.3, 0.3, 0.3, 0.3],
        filename:     '<?= e($invoice['doc_number']) ?>.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true },
        jsPDF:        { unit: 'in', format: 'a4', orientation: 'portrait' }
    };
    html2pdf().set(opt).from(element).save();
}

function invoiceView() {
    return {
        showPaymentModal: false,
        payLoading: false,
        payError: '',
        payForm: {
            amount: '',
            payment_date: new Date().toISOString().split('T')[0],
            method: 'cash',
            reference: '',
            note: ''
        },

        submitPayment() {
            this.payError = '';
            const amt = parseFloat(this.payForm.amount);
            if (!amt || amt <= 0) {
                this.payError = 'Please enter a valid amount.';
                return;
            }
            if (amt > <?= (float)$invoice['balance_due'] ?>) {
                this.payError = 'Amount exceeds the remaining balance.';
                return;
            }

            this.payLoading = true;

            const formData = new FormData();
            formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?? '' ?>');
            formData.append('amount', this.payForm.amount);
            formData.append('payment_date', this.payForm.payment_date);
            formData.append('method', this.payForm.method);
            formData.append('reference', this.payForm.reference);
            formData.append('note', this.payForm.note);

            $.ajax({
                url: '<?= APP_URL ?>/invoices/record_payment/<?= $invoice['id'] ?>',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                success: (res) => {
                    if (res.success) {
                        // Reload to show updated state
                        window.location.reload();
                    } else {
                        this.payError = res.error || 'An error occurred.';
                        this.payLoading = false;
                    }
                },
                error: (xhr) => {
                    const err = xhr.responseJSON;
                    this.payError = err?.error || 'Network error. Please try again.';
                    this.payLoading = false;
                }
            });
        }
    };
}
</script>

<?php if (in_array($invoice['status'] ?? '', ['paid', 'partially_paid'])): ?>
<!-- Deed of Acquisition Button -->
<div class="mt-6 bg-white border border-brand-sand rounded-2xl p-5 shadow-sm" x-data="{ showDeed: false }">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="font-heading font-bold text-sm text-brand-plum uppercase tracking-wider">
                Deed of Acquisition
            </h3>
            <p class="text-xs text-brand-plum/60 mt-0.5">Issue a formal acquisition document to this client.</p>
        </div>
        <button @click="showDeed = !showDeed"
                class="text-xs font-bold uppercase tracking-widest px-4 py-2.5 border border-brand-gold text-brand-gold hover:bg-brand-gold hover:text-white transition-colors rounded-xl">
            Issue Deed
        </button>
    </div>

    <form x-show="showDeed" x-transition action="<?= APP_URL ?>/deed/create" method="POST" class="mt-5 pt-5 border-t border-brand-sand space-y-4" style="display:none;">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="source" value="invoice">
        <input type="hidden" name="source_id" value="<?= (int)$invoice['id'] ?>">
        <div>
            <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-2">
                Editorial / Provenance Text
                <span class="text-brand-plum/30 normal-case tracking-normal font-medium">(optional — appears on the deed as the narrative description)</span>
            </label>
            <textarea name="provenance_text" rows="3"
                      class="w-full px-4 py-3 border border-brand-sand rounded-xl text-sm focus:border-brand-gold outline-none resize-none"
                      placeholder="e.g. Hand-crafted from premium materials, this piece represents the intersection of craft and commerce..."></textarea>
        </div>
        <button type="submit"
                class="btn-primary text-white text-xs font-bold px-6 py-3 rounded-xl uppercase tracking-widest">
            Generate &amp; Issue Deed
        </button>
    </form>
</div>
<?php endif; ?>
