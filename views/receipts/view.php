<?php
/**
 * Nfelia — Receipt Detail View + Document Preview
 * Renders inside views/layouts/app.php
 *
 * Available from controller:
 *   $user, $business, $receipt, $customer, $invoice, $items,
 *   $payment, $public_url, $is_standalone
 */
$currency = $business['currency_symbol'] ?? '₦';
$font_map = FONT_PAIRINGS[$business['font_pairing'] ?? 'modern_sans'] ?? FONT_PAIRINGS['modern_sans'];

// WhatsApp share
$wa_number = $customer ? ($customer['whatsapp'] ?? $customer['phone'] ?? '') : '';
$wa_number = preg_replace('/[^0-9]/', '', $wa_number);
if (str_starts_with($wa_number, '0')) $wa_number = '234' . substr($wa_number, 1);
$wa_text = urlencode(
    "Hello" . ($customer ? ' ' . $customer['full_name'] : '') . "!\n\n"
    . "Here is your *Payment Acknowledgement* from *{$business['name']}*:\n\n"
    . "{$receipt['doc_number']}\n"
    . "Amount: {$currency}" . number_format((float)$receipt['amount'], 2) . "\n"
    . "Date: " . fmt_date($receipt['issue_date'], 'd M Y') . "\n"
    . "Method: " . ucfirst(str_replace('_', ' ', $receipt['method'])) . "\n"
    . ($receipt['balance_remaining'] > 0 ? "Balance Remaining: {$currency}" . number_format((float)$receipt['balance_remaining'], 2) . "\n" : '')
    . "\nView & download here:\n{$public_url}\n\n"
    . "_Sent via Nfelia — nfelia.com_"
);
$wa_link = "https://wa.me/{$wa_number}?text={$wa_text}";
?>

<!-- Breadcrumb + Actions -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div class="flex items-center gap-3">
        <a href="<?= APP_URL ?>/receipts" class="text-brand-plum/40 hover:text-brand-plum transition-colors">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="font-heading font-extrabold text-xl text-brand-plum tracking-tight"><?= e($receipt['doc_number']) ?></h1>
            <div class="flex items-center gap-2 mt-0.5">
                <span class="data-text text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-green-100 text-green-700">
                    <?= $is_standalone ? 'Direct Sale' : 'Invoice Payment' ?>
                </span>
                <?php if ($invoice): ?>
                    <a href="<?= APP_URL ?>/invoices/view/<?= $invoice['id'] ?>" class="data-text text-[9px] font-bold text-blue-500 hover:text-blue-700">
                        for <?= e($invoice['doc_number']) ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <form method="POST" action="<?= APP_URL ?>/receipts/delete/<?= $receipt['id'] ?>" class="inline"
              onsubmit="return confirm('Permanently delete this receipt?');">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            <button class="flex items-center gap-1.5 bg-brand-white border border-red-200 hover:border-red-400 text-red-600 px-3 py-2 rounded-lg transition-all">
                <span class="text-[10px] font-bold uppercase tracking-widest">Delete</span>
            </button>
        </form>
    </div>
</div>

<!-- Share Actions Bar -->
<div class="bg-brand-white border border-brand-sand rounded-xl p-4 mb-6 shadow-sm flex flex-wrap items-center gap-3">
    <?php $share_pdf = ['url' => $public_url, 'name' => $receipt['doc_number'] . '.pdf', 'title' => 'Receipt ' . $receipt['doc_number'],
                        'text' => 'Receipt ' . $receipt['doc_number'] . ' from ' . $business['name'] . ': ' . $public_url];
          require BASE_PATH . '/views/partials/share_pdf.php'; ?>
    <button onclick="navigator.clipboard.writeText('<?= e($public_url) ?>'); this.querySelector('span').textContent = 'Copied!'; setTimeout(() => this.querySelector('span').textContent = 'Copy Link', 2000);"
            class="flex items-center gap-1.5 bg-brand-cream border border-brand-sand hover:border-brand-gold px-3 py-2 rounded-lg transition-all">
        <svg class="w-3.5 h-3.5 text-brand-plum/50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
        <span class="text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest">Copy Link</span>
    </button>

    <?php if ($wa_number): ?>
    <a href="<?= $wa_link ?>" target="_blank" class="flex items-center gap-1.5 bg-green-500 text-white px-3 py-2 rounded-lg hover:bg-green-600 transition-all">
        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
        <span class="text-[10px] font-bold uppercase tracking-widest">WhatsApp</span>
    </a>
    <?php endif; ?>

    <button onclick="downloadPDF()" class="flex items-center gap-1.5 bg-brand-cream border border-brand-sand hover:border-brand-gold px-3 py-2 rounded-lg transition-all">
        <svg class="w-3.5 h-3.5 text-brand-plum/50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
        <span class="text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest">Download PDF</span>
    </button>

    <a href="<?= e($public_url) ?>" target="_blank" class="flex items-center gap-1.5 bg-brand-cream border border-brand-sand hover:border-brand-gold px-3 py-2 rounded-lg transition-all">
        <svg class="w-3.5 h-3.5 text-brand-plum/50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
        <span class="text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest">Public View</span>
    </a>
</div>

<!-- ===== DOCUMENT PREVIEW ===== -->
<div id="receipt-document" class="bg-white border border-brand-sand rounded-xl shadow-sm overflow-hidden mb-8">

    <div class="p-8 sm:p-10" style="border-top: 4px solid <?= e($business['primary_color'] ?? '#8B004A') ?>;">

        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-6 mb-8">
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
                </div>
            </div>

            <div class="text-right">
                <h1 class="font-heading font-extrabold text-2xl uppercase tracking-wider" style="color: <?= e($business['primary_color'] ?? '#8B004A') ?>;">Payment Acknowledgement</h1>
                <div class="data-text text-sm font-bold text-gray-700 mt-1"><?= e($receipt['doc_number']) ?></div>
                <div class="text-[10px] text-gray-500 mt-2 space-y-0.5">
                    <div>Date: <?= fmt_date($receipt['issue_date'], 'd M Y') ?></div>
                    <div>Method: <?= ucfirst(str_replace('_', ' ', $receipt['method'])) ?></div>
                </div>
            </div>
        </div>

        <!-- Customer Block -->
        <?php if ($customer): ?>
        <div class="mb-8 p-4 rounded-lg" style="background: <?= e($business['primary_color'] ?? '#8B004A') ?>0A;">
            <div class="text-[9px] font-bold uppercase tracking-widest mb-1" style="color: <?= e($business['primary_color'] ?? '#8B004A') ?>;">Received From</div>
            <div class="text-sm font-bold text-gray-800"><?= e($customer['full_name']) ?></div>
            <?php if (!empty($customer['business_name'])): ?><div class="text-[10px] text-gray-600"><?= e($customer['business_name']) ?></div><?php endif; ?>
            <?php if (!empty($customer['phone'])): ?><div class="text-[10px] text-gray-500"><?= e($customer['phone']) ?></div><?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Invoice Reference (for invoice payments) -->
        <?php if ($invoice): ?>
        <div class="mb-6 p-4 rounded-lg bg-blue-50 border border-blue-200">
            <div class="text-[9px] font-bold uppercase tracking-widest text-blue-500 mb-1">Payment For Invoice</div>
            <div class="data-text text-sm font-bold text-blue-700"><?= e($invoice['doc_number']) ?></div>
            <div class="text-[10px] text-blue-600 mt-1">
                Invoice Total: <?= money((float)$invoice['total'], $currency) ?> •
                Paid: <?= money((float)$invoice['amount_paid'], $currency) ?> •
                <?php if ((float)$invoice['balance_due'] > 0): ?>
                    Balance: <strong class="text-red-600"><?= money((float)$invoice['balance_due'], $currency) ?></strong>
                <?php else: ?>
                    <strong class="text-green-600">Fully Paid</strong>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Line Items (standalone POS receipts) -->
        <?php if ($is_standalone && !empty($items)): ?>
        <div class="overflow-x-auto mb-6">
            <table class="w-full text-left">
                <thead>
                    <tr style="border-bottom: 2px solid <?= e($business['primary_color'] ?? '#8B004A') ?>;">
                        <th class="pb-2 text-[9px] font-bold uppercase tracking-widest text-gray-500">#</th>
                        <th class="pb-2 text-[9px] font-bold uppercase tracking-widest text-gray-500">Description</th>
                        <th class="pb-2 text-[9px] font-bold uppercase tracking-widest text-gray-500 text-center">Qty</th>
                        <th class="pb-2 text-[9px] font-bold uppercase tracking-widest text-gray-500 text-right">Unit Price</th>
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
                            <td class="py-3 data-text text-xs font-bold text-gray-800 text-right"><?= money((float)$it['subtotal'], $currency) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Amount -->
        <div class="flex justify-end mb-8">
            <div class="w-72 space-y-2">
                <div class="flex justify-between text-sm pt-2" style="border-top: 2px solid <?= e($business['primary_color'] ?? '#8B004A') ?>;">
                    <span class="font-heading font-bold uppercase tracking-wider" style="color: <?= e($business['primary_color'] ?? '#8B004A') ?>;">Amount Received</span>
                    <span class="font-heading font-extrabold text-xl text-green-600"><?= money((float)$receipt['amount'], $currency) ?></span>
                </div>
                <?php if ((float)$receipt['balance_remaining'] > 0): ?>
                    <div class="flex justify-between text-xs pt-1" style="border-top: 1px dashed <?= e($business['primary_color'] ?? '#8B004A') ?>66;">
                        <span class="text-red-600 font-semibold">Balance Remaining</span>
                        <span class="data-text font-bold text-red-600"><?= money((float)$receipt['balance_remaining'], $currency) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Note -->
        <?php if ($receipt['note']): ?>
        <div class="mb-8">
            <div class="text-[9px] font-bold uppercase tracking-widest text-gray-500 mb-1">Note</div>
            <p class="text-xs text-gray-600 leading-relaxed whitespace-pre-line"><?= e($receipt['note']) ?></p>
        </div>
        <?php endif; ?>

        <!-- Confirmation statement -->
        <div class="mt-6 p-4 rounded-lg bg-green-50 border border-green-200 text-center">
            <p class="text-xs text-green-700 font-semibold">
                This receipt confirms the payment of <strong><?= money((float)$receipt['amount'], $currency) ?></strong>
                received on <strong><?= fmt_date($receipt['issue_date'], 'd M Y') ?></strong>
                via <strong><?= ucfirst(str_replace('_', ' ', $receipt['method'])) ?></strong>.
            </p>
        </div>

        <!-- Footer -->
        <?php if ($business['tagline']): ?>
        <div class="mt-8 text-center">
            <p class="text-[9px] uppercase tracking-[.3em] font-bold" style="color: <?= e($business['secondary_color'] ?? '#B69F5E') ?>;"><?= e($business['tagline']) ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- html2pdf.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function downloadPDF() {
    html2pdf().set({
        margin: [0.3, 0.3, 0.3, 0.3],
        filename: '<?= e($receipt['doc_number']) ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
    }).from(document.getElementById('receipt-document')).save();
}
</script>