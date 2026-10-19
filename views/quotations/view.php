<?php
/**
 * Nfelia — Quotation Detail View + Document Preview + Actions
 * Renders inside views/layouts/app.php
 *
 * Available from controller:
 *   $user, $business, $quotation, $customer, $items, $bank, $public_url
 */
$currency = $business['currency_symbol'] ?? '₦';
$font_map = FONT_PAIRINGS[$business['font_pairing'] ?? 'modern_sans'] ?? FONT_PAIRINGS['modern_sans'];

// Status config
$status_cfg = match($quotation['status']) {
    'draft'     => ['bg-gray-100 text-gray-700 border-gray-300', 'Draft', 'gray'],
    'sent'      => ['bg-blue-100 text-blue-700 border-blue-300', 'Sent', 'blue'],
    'accepted'  => ['bg-green-100 text-green-700 border-green-300', 'Accepted', 'green'],
    'declined'  => ['bg-red-100 text-red-700 border-red-300', 'Declined', 'red'],
    'expired'   => ['bg-amber-100 text-amber-700 border-amber-300', 'Expired', 'amber'],
    'converted' => ['bg-purple-100 text-purple-700 border-purple-300', !empty($quote_order) ? 'Ordered' : 'Converted to Invoice', 'purple'],
    default     => ['bg-gray-100 text-gray-700 border-gray-300', ucfirst($quotation['status']), 'gray'],
};

$wa_number = $customer['whatsapp'] ?: $customer['phone'] ?: '';
$wa_number = preg_replace('/[^0-9]/', '', $wa_number);
if (str_starts_with($wa_number, '0')) $wa_number = '234' . substr($wa_number, 1);
$wa_text = urlencode(
    "Hello" . ($customer ? ' ' . $customer['full_name'] : '') . "!\n\n"
    . "Please find your *Quotation* from *{$business['name']}*:\n\n"
    . "{$quotation['doc_number']}\n"
    . "Total: {$currency}" . number_format((float)$quotation['total'], 2) . "\n"
    . ($quotation['expiry_date'] ? "Valid until: " . fmt_date($quotation['expiry_date'], 'd M Y') . "\n" : '')
    . "\nView & download here:\n{$public_url}\n\n"
    . "_Sent via Nfelia — nfelia.com_"
);
$wa_link = "https://wa.me/{$wa_number}?text={$wa_text}";
?>

<!-- Breadcrumb + Actions -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div class="flex items-center gap-3">
        <a href="<?= APP_URL ?>/quotations" class="text-brand-plum/40 hover:text-brand-plum transition-colors">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="font-heading font-extrabold text-xl text-brand-plum tracking-tight"><?= e($quotation['doc_number']) ?></h1>
            <span class="data-text text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded <?= $status_cfg[0] ?>"><?= $status_cfg[1] ?></span>
        </div>
    </div>

    <!-- Action buttons -->
    <div class="flex flex-wrap items-center gap-2">
        
        <!-- NEW AUTHORIZE BUTTON STARTS HERE -->
        <?php if (empty($quotation['owner_signature_data'])): ?>
            <button onclick="document.getElementById('ownerSignModal').classList.remove('hidden')" class="flex items-center gap-1.5 bg-brand-plum text-white px-3 py-2 rounded-lg hover:bg-brand-burgundy transition-all">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                <span class="text-[10px] font-bold uppercase tracking-widest">Authorize</span>
            </button>
        <?php else: ?>
            <span class="flex items-center gap-1.5 bg-green-50 text-green-700 border border-green-200 px-3 py-2 rounded-lg">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                <span class="text-[10px] font-bold uppercase tracking-widest">Authorized</span>
            </span>
        <?php endif; ?>
        <!-- NEW AUTHORIZE BUTTON ENDS HERE -->

        <?php if (in_array($quotation['status'], ['draft', 'sent', 'in_negotiation'])): ?>
            <a href="<?= APP_URL ?>/quotations/edit/<?= $quotation['id'] ?>" class="flex items-center gap-1.5 bg-brand-white border border-brand-sand hover:border-brand-gold px-3 py-2 rounded-lg transition-all">
                <svg class="w-3.5 h-3.5 text-brand-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                <span class="text-[10px] font-bold text-brand-plum uppercase tracking-widest">Edit</span>
            </a>
        <?php endif; ?>

        <?php if ($quotation['status'] === 'draft'): ?>
            <form method="POST" action="<?= APP_URL ?>/quotations/send/<?= $quotation['id'] ?>" class="inline">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <button class="flex items-center gap-1.5 btn-primary text-white px-3 py-2 rounded-lg transition-all">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    <span class="text-[10px] font-bold uppercase tracking-widest">Mark Sent</span>
                </button>
            </form>
        <?php endif; ?>

        <?php if ($quotation['status'] === 'sent'): ?>
            <form method="POST" action="<?= APP_URL ?>/quotations/update-status/<?= $quotation['id'] ?>" class="inline">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <input type="hidden" name="status" value="accepted">
                <button class="flex items-center gap-1.5 bg-green-500 text-white px-3 py-2 rounded-lg hover:bg-green-600 transition-all">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    <span class="text-[10px] font-bold uppercase tracking-widest">Accept and make the order</span>
                </button>
            </form>
            <form method="POST" action="<?= APP_URL ?>/quotations/update-status/<?= $quotation['id'] ?>" class="inline">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <input type="hidden" name="status" value="declined">
                <button class="flex items-center gap-1.5 bg-brand-white border border-red-200 hover:border-red-400 text-red-600 px-3 py-2 rounded-lg transition-all">
                    <span class="text-[10px] font-bold uppercase tracking-widest">Decline</span>
                </button>
            </form>
        <?php endif; ?>

        <?php if (!empty($quote_order)): ?>
            <a href="<?= APP_URL ?>/orders/view/<?= (int)$quote_order['id'] ?>" class="flex items-center gap-1.5 btn-primary text-white px-3 py-2 rounded-lg transition-all min-h-[44px]">
                <span class="text-sm font-semibold">Open order <?= e($quote_order['order_number']) ?></span>
            </a>
        <?php elseif (in_array($quotation['status'], ['accepted', 'in_negotiation', 'pending_signature'], true)): ?>
            <form method="POST" action="<?= APP_URL ?>/quotations/convert/<?= $quotation['id'] ?>" class="inline">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <button class="flex items-center gap-1.5 bg-purple-500 text-white px-3 py-2 rounded-lg hover:bg-purple-600 transition-all min-h-[44px]">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    <span class="text-sm font-semibold">Make it an order</span>
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Share Actions Bar -->
<div class="bg-brand-white border border-brand-sand rounded-xl p-4 mb-6 shadow-sm flex flex-wrap items-center gap-3">
    <?php $share_pdf = ['url' => $public_url, 'name' => $quotation['doc_number'] . '.pdf', 'title' => 'Quote ' . $quotation['doc_number'],
                        'text' => 'Quote ' . $quotation['doc_number'] . ' from ' . $business['name'] . ': ' . $public_url];
          require BASE_PATH . '/views/partials/share_pdf.php'; ?>

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
    <a href="<?= APP_URL ?>/doc/<?= e($quotation['token']) ?>?format=pdf" target="_blank" class="flex items-center gap-1.5 bg-brand-cream border border-brand-sand hover:border-brand-gold px-3 py-2 rounded-lg transition-all">
        <svg class="w-3.5 h-3.5 text-brand-plum/50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
        <span class="text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest">Download PDF</span>
    </a>

    <!-- Open Public -->
    <a href="<?= e($public_url) ?>" target="_blank" class="flex items-center gap-1.5 bg-brand-cream border border-brand-sand hover:border-brand-gold px-3 py-2 rounded-lg transition-all">
        <svg class="w-3.5 h-3.5 text-brand-plum/50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
        <span class="text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest">Public View</span>
    </a>

    <!-- Delete -->
    <form method="POST" action="<?= APP_URL ?>/quotations/delete/<?= $quotation['id'] ?>" class="inline ml-auto"
          onsubmit="return confirm('Permanently delete this quotation?');">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
        <button class="text-[10px] font-bold text-red-400 uppercase tracking-widest hover:text-red-600 transition-colors">Delete</button>
    </form>
</div>

<!-- ===== DOCUMENT PREVIEW ===== -->
<div id="quotation-document" class="bg-white border border-brand-sand rounded-xl shadow-sm overflow-hidden mb-8">

    <!-- Document Header (Branded) -->
    <div class="p-8 sm:p-10" style="border-top: 4px solid <?= e($business['primary_color'] ?? '#8B004A') ?>;">

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
                <h1 class="font-heading font-extrabold text-2xl uppercase tracking-wider" style="color: <?= e($business['primary_color'] ?? '#8B004A') ?>;">Quotation</h1>
                <div class="data-text text-sm font-bold text-gray-700 mt-1"><?= e($quotation['doc_number']) ?></div>
                <div class="text-[10px] text-gray-500 mt-2 space-y-0.5">
                    <div>Issued: <?= fmt_date($quotation['issue_date'], 'd M Y') ?></div>
                    <?php if ($quotation['expiry_date']): ?>
                        <div>Valid Until: <?= fmt_date($quotation['expiry_date'], 'd M Y') ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Client Block -->
        <?php if ($customer): ?>
        <div class="mb-8 p-4 rounded-lg" style="background: <?= e($business['primary_color'] ?? '#8B004A') ?>0A;">
            <div class="text-[9px] font-bold uppercase tracking-widest mb-1" style="color: <?= e($business['primary_color'] ?? '#8B004A') ?>;">Prepared For</div>
            <div class="text-sm font-bold text-gray-800"><?= e($customer['full_name']) ?></div>
            <?php if ($customer['business_name']): ?><div class="text-[10px] text-gray-600"><?= e($customer['business_name']) ?></div><?php endif; ?>
            <?php if ($customer['phone']): ?><div class="text-[10px] text-gray-500"><?= e($customer['phone']) ?></div><?php endif; ?>
            <?php if ($customer['email']): ?><div class="text-[10px] text-gray-500"><?= e($customer['email']) ?></div><?php endif; ?>
            <?php if ($customer['address']): ?><div class="text-[10px] text-gray-500"><?= e($customer['address']) ?></div><?php endif; ?>
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
            <div class="w-64 space-y-2">
                <div class="flex justify-between text-xs">
                    <span class="text-gray-500">Subtotal</span>
                    <span class="data-text font-bold text-gray-700"><?= money((float)$quotation['subtotal'], $currency) ?></span>
                </div>
                <?php if ((float)$quotation['discount_amount'] > 0): ?>
                    <div class="flex justify-between text-xs">
                        <span class="text-gray-500">Discount</span>
                        <span class="data-text font-bold text-red-600">-<?= money((float)$quotation['discount_amount'], $currency) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ((float)$quotation['tax_rate'] > 0): ?>
                    <div class="flex justify-between text-xs">
                        <span class="text-gray-500">Tax (<?= number_format((float)$quotation['tax_rate'], 1) ?>%)</span>
                        <span class="data-text font-bold text-gray-700"><?= money((float)$quotation['tax_amount'], $currency) ?></span>
                    </div>
                <?php endif; ?>
                <div class="flex justify-between text-sm pt-2" style="border-top: 2px solid <?= e($business['primary_color'] ?? '#8B004A') ?>;">
                    <span class="font-heading font-bold uppercase tracking-wider" style="color: <?= e($business['primary_color'] ?? '#8B004A') ?>;">Grand Total</span>
                    <span class="font-heading font-extrabold text-lg" style="color: <?= e($business['primary_color'] ?? '#8B004A') ?>;"><?= money((float)$quotation['total'], $currency) ?></span>
                </div>
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

        <!-- Notes -->
        <?php if ($quotation['notes']): ?>
        <div class="mt-6">
            <div class="text-[9px] font-bold uppercase tracking-widest text-gray-500 mb-1">Notes & Terms</div>
            <p class="text-xs text-gray-600 leading-relaxed whitespace-pre-line"><?= e($quotation['notes']) ?></p>
        </div>
        <?php endif; ?>

        <!-- Signature Block -->
        <div class="mt-10 pt-6 border-t border-gray-200">
            <div class="grid grid-cols-2 gap-8">
                <div>
                    <div class="border-b border-gray-300 pb-8 mb-2"></div>
                    <div class="text-[9px] font-bold uppercase tracking-widest text-gray-500">Authorized By</div>
                    <div class="text-xs text-gray-700 mt-0.5"><?= e($business['name']) ?></div>
                </div>
                <div>
                    <div class="border-b border-gray-300 pb-8 mb-2"></div>
                    <div class="text-[9px] font-bold uppercase tracking-widest text-gray-500">Client Acceptance</div>
                    <div class="text-xs text-gray-400 mt-0.5">Date: _______________</div>
                </div>
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

<!-- ===== INTERNAL CHAT & AUDIT LOG ===== -->
<div class="mt-8 bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-brand-sand bg-brand-cream/30 flex justify-between items-center">
        <h2 class="font-heading font-bold text-xs text-brand-plum uppercase tracking-widest">Client Discussion & Audit Log</h2>
    </div>
    
    <div class="p-6 space-y-4 max-h-96 overflow-y-auto bg-slate-50">
        <?php if (empty($activities)): ?>
            <p class="text-xs text-gray-400 text-center font-medium">No activity or comments yet.</p>
        <?php else: ?>
            <?php foreach ($activities as $act): ?>
                <?php $is_internal = $act['actor_type'] === 'business'; ?>
                <div class="flex flex-col <?= $is_internal ? 'items-end' : 'items-start' ?>">
                    <span class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-1 mx-1">
                        <?= $is_internal ? e($act['full_name']) : e($customer['full_name'] ?? 'Client') ?> 
                        • <?= date('M d, H:i', strtotime($act['created_at'])) ?>
                    </span>
                    
                    <?php if ($act['action'] === 'comment_added'): ?>
                        <div class="px-4 py-3 rounded-2xl max-w-[80%] text-sm <?= $is_internal ? 'bg-brand-plum text-white rounded-tr-sm' : 'bg-white text-gray-800 border border-gray-200 shadow-sm rounded-tl-sm' ?>">
                            <?= nl2br(e($act['message'])) ?>
                        </div>
                    <?php else: ?>
                        <div class="px-3 py-1.5 bg-gray-100 border border-gray-200 rounded-md text-[10px] font-medium text-gray-500 uppercase tracking-wider">
                            <i class="fas fa-info-circle mr-1"></i> <?= e($act['message']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Reply Form (Only visible if sent or in negotiation) -->
    <?php if (in_array($quotation['status'], ['sent', 'in_negotiation'])): ?>
        <div class="p-4 border-t border-brand-sand bg-white">
            <form method="POST" action="<?= APP_URL ?>/quotations/add_comment/<?= $quotation['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <div class="flex gap-3">
                    <textarea name="message" rows="2" required placeholder="Reply to the client..." 
                              class="flex-1 px-4 py-3 text-sm bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum resize-none"></textarea>
                    <button type="submit" class="btn-primary text-white px-6 py-2 rounded-lg text-[10px] font-bold uppercase tracking-widest self-end h-11">
                        Send
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<div id="ownerSignModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
    <div class="bg-white w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden">
        <div class="p-5 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
            <div>
                <h3 class="font-heading font-bold text-lg text-gray-900">Authorize Document</h3>
                <p class="text-xs text-gray-500">Sign this quotation before or after sending to the client.</p>
            </div>
            <button onclick="document.getElementById('ownerSignModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-700">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        
        <form method="POST" action="<?= APP_URL ?>/quotations/sign_internal/<?= $quotation['id'] ?>" onsubmit="return submitOwnerSignature(event)">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            <input type="hidden" name="owner_signature_data" id="owner_signature_data">

            <div class="p-5">
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Authorized Signatory Name</label>
                <input type="text" name="owner_signed_by_name" id="owner_signed_by_name" value="<?= e($user['full_name']) ?>" <?php /* NFELIA AUDIT FIX M07 2026-06-03 */ ?> class="w-full p-3 border-2 border-gray-200 rounded-lg mb-5 text-sm font-bold text-gray-900 focus:border-brand-plum outline-none" required>
                
                <div class="flex justify-between items-end mb-2">
                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest">Draw Signature</label>
                    <button type="button" onclick="clearOwnerCanvas()" class="text-[10px] font-bold text-brand-plum uppercase tracking-widest hover:underline">Clear</button>
                </div>
                <div class="border-2 border-gray-900 rounded-xl bg-gray-50 overflow-hidden relative" style="height: 150px;">
                    <canvas id="owner-sig-canvas" width="500" height="150" class="w-full h-full absolute inset-0 cursor-crosshair touch-none"></canvas>
                </div>
            </div>

            <div class="p-5 border-t border-gray-100 flex gap-3 bg-white">
                <button type="submit" class="w-full py-3 text-xs font-bold text-white bg-brand-plum rounded-lg uppercase tracking-widest shadow-md hover:bg-brand-burgundy transition-colors">
                    Save Signature
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const canvas = document.getElementById('owner-sig-canvas');
    const ctx = canvas.getContext('2d');
    let isDrawing = false;
    let hasDrawn = false;

    ctx.lineWidth = 3;
    ctx.lineCap = 'round';
    ctx.strokeStyle = '#0F172A';

    function getMousePos(e) {
        const rect = canvas.getBoundingClientRect();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        return {
            x: (clientX - rect.left) * (canvas.width / rect.width),
            y: (clientY - rect.top) * (canvas.height / rect.height)
        };
    }

    const startDrawing = (e) => { isDrawing = true; hasDrawn = true; const pos = getMousePos(e); ctx.beginPath(); ctx.moveTo(pos.x, pos.y); };
    const draw = (e) => { if (!isDrawing) return; e.preventDefault(); const pos = getMousePos(e); ctx.lineTo(pos.x, pos.y); ctx.stroke(); };
    const stopDrawing = () => { isDrawing = false; };

    canvas.addEventListener('mousedown', startDrawing); canvas.addEventListener('mousemove', draw); canvas.addEventListener('mouseup', stopDrawing); canvas.addEventListener('mouseout', stopDrawing);
    canvas.addEventListener('touchstart', startDrawing, {passive: false}); canvas.addEventListener('touchmove', draw, {passive: false}); canvas.addEventListener('touchend', stopDrawing);

    function clearOwnerCanvas() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        hasDrawn = false;
        ctx.beginPath();
    }

    function submitOwnerSignature(e) {
        if (!hasDrawn) {
            alert("Please draw your signature.");
            e.preventDefault();
            return false;
        }
        document.getElementById('owner_signature_data').value = canvas.toDataURL('image/png');
        return true;
    }
</script>