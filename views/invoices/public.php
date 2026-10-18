<?php
/**
 * Nfelia — Public Invoice View (Standalone, No Auth)
 * Accessed via /doc/{token}
 *
 * This file is NOT rendered inside app.php — it is a standalone page.
 * Controller (PublicController) provides:
 *   $invoice, $business, $customer, $items, $bank
 */
$currency  = $business['currency_symbol'] ?? '₦';
$primary   = $business['primary_color'] ?? '#8B004A';
$secondary = $business['secondary_color'] ?? '#B69F5E';
$font_map  = FONT_PAIRINGS[$business['font_pairing'] ?? 'modern_sans'] ?? FONT_PAIRINGS['modern_sans'];
$font_heading = $font_map['heading'];
$font_body    = $font_map['body'];
$google_fonts = urlencode($font_heading) . ':wght@400;600;700;800&family=' . urlencode($font_body) . ':wght@400;500;600';

// Payment watermark
$watermark_text = match($invoice['status']) {
    'paid'           => 'PAID',
    'partially_paid' => 'PARTIALLY PAID',
    'cancelled'      => 'CANCELLED',
    'overdue'        => 'OVERDUE',
    default          => '',
};
$watermark_color = match($invoice['status']) {
    'paid'           => '#16a34a',
    'partially_paid' => '#d97706',
    'cancelled'      => '#6b7280',
    'overdue'        => '#dc2626',
    default          => '#6b7280',
};

// Overdue?
$days_overdue = 0;
if ($invoice['due_date'] && in_array($invoice['status'], ['overdue','sent','partially_paid'])) {
    $diff = (int)((time() - strtotime($invoice['due_date'])) / 86400);
    if ($diff > 0) $days_overdue = $diff;
}

// SEO / embed helpers
$doc_url   = APP_URL . '/doc/' . e($invoice['token']);
$og_title  = 'Invoice ' . e($invoice['doc_number']) . ' from ' . e($business['name']);
$og_desc   = 'Commercial Invoice — ' . money((float)$invoice['total'], $currency)
           . ($customer ? ' for ' . e($customer['full_name']) : '')
           . '. Issued ' . fmt_date($invoice['issue_date'], 'd M Y')
           . ($invoice['due_date'] ? ', due ' . fmt_date($invoice['due_date'], 'd M Y') : '')
           . '. Balance: ' . money((float)$invoice['balance_due'], $currency)
           . ' | Powered by Nfelia';
$og_image  = $business['logo_path']
    ? APP_URL . '/' . e($business['logo_path'])
    : APP_URL . '/og-image.php?biz=' . (int)$business['id'] . '&doc=' . urlencode($invoice['doc_number']);
?>
<!DOCTYPE html>
<html lang="en" prefix="og: https://ogp.me/ns#">
<head>
    <meta charset="UTF-8">
    <?= errors_script_tag() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($invoice['doc_number']) ?> — <?= e($business['name']) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="canonical" href="<?= $doc_url ?>">

    <!-- Open Graph (WhatsApp, Facebook, LinkedIn, Telegram) -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= e($business['name']) ?> — via Nfelia">
    <meta property="og:title" content="<?= e($og_title) ?>">
    <meta property="og:description" content="<?= e($og_desc) ?>">
    <meta property="og:image" content="<?= $og_image ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="Invoice <?= e($invoice['doc_number']) ?> from <?= e($business['name']) ?>">
    <meta property="og:url" content="<?= $doc_url ?>">
    <meta property="og:locale" content="en_NG">

    <!-- Twitter / X Card -->
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= e($og_title) ?>">
    <meta name="twitter:description" content="<?= e($og_desc) ?>">
    <meta name="twitter:image" content="<?= $og_image ?>">

    <!-- JSON-LD Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Invoice",
        "identifier": "<?= e($invoice['doc_number']) ?>",
        "url": "<?= $doc_url ?>",
        "totalPaymentDue": {
            "@type": "PriceSpecification",
            "price": "<?= number_format((float)$invoice['balance_due'], 2, '.', '') ?>",
            "priceCurrency": "NGN"
        },
        "paymentStatus": "<?= $invoice['status'] === 'paid' ? 'PaymentComplete' : 'PaymentDue' ?>",
        "provider": {
            "@type": "Organization",
            "name": "<?= e($business['name']) ?>"
            <?php if ($business['logo_path']): ?>,
            "logo": "<?= APP_URL ?>/<?= e($business['logo_path']) ?>"
            <?php endif; ?>
        }
        <?php if ($customer): ?>,
        "customer": {
            "@type": "Person",
            "name": "<?= e($customer['full_name']) ?>"
        }
        <?php endif; ?>,
        "paymentDueDate": "<?= e($invoice['due_date'] ?? '') ?>"
    }
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=<?= $google_fonts ?>&family=Roboto+Mono:wght@400;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { fontFamily: {
            heading: ['"<?= $font_heading ?>"', 'sans-serif'],
            body: ['"<?= $font_body ?>"', 'sans-serif'],
            mono: ['"Roboto Mono"', 'monospace'],
        }}}}
    </script>
    <style>
        body { font-family: '<?= $font_body ?>', sans-serif; background: #F2EFE7; }
        .data-text { font-family: 'Roboto Mono', monospace; }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</head>
<body class="min-h-screen py-8 px-4">

    <div class="max-w-3xl mx-auto">

        <!-- Top Bar -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-2">
                <?php if ($business['logo_path']): ?>
                    <img src="<?= APP_URL ?>/<?= e($business['logo_path']) ?>" alt="" class="h-8 w-8 object-contain rounded">
                <?php endif; ?>
                <span class="font-heading font-bold text-sm" style="color: <?= e($primary) ?>;"><?= e($business['name']) ?></span>
            </div>
            <button onclick="downloadPDF()" class="flex items-center gap-1.5 bg-white border border-gray-300 hover:border-gray-400 px-3 py-2 rounded-lg text-[10px] font-bold text-gray-600 uppercase tracking-widest">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Download PDF
            </button>
        </div>

        <!-- Status Banner -->
        <?php if ($invoice['status'] === 'paid'): ?>
            <div class="bg-green-50 border border-green-300 rounded-lg px-4 py-3 mb-4 text-xs font-semibold text-green-800">
                This invoice has been fully paid. Thank you!
            </div>
        <?php elseif ($invoice['status'] === 'overdue'): ?>
            <div class="bg-red-50 border border-red-300 rounded-lg px-4 py-3 mb-4 text-xs font-semibold text-red-800">
                This invoice is overdue<?= $days_overdue > 0 ? " by {$days_overdue} day" . ($days_overdue > 1 ? 's' : '') : '' ?>. Please arrange payment as soon as possible.
            </div>
        <?php elseif ($invoice['status'] === 'partially_paid'): ?>
            <div class="bg-amber-50 border border-amber-300 rounded-lg px-4 py-3 mb-4 text-xs font-semibold text-amber-800">
                Partial payment received — <?= money((float)$invoice['amount_paid'], $currency) ?> of <?= money((float)$invoice['total'], $currency) ?>. Outstanding balance: <strong><?= money((float)$invoice['balance_due'], $currency) ?></strong>
            </div>
        <?php elseif ($invoice['status'] === 'cancelled'): ?>
            <div class="bg-gray-100 border border-gray-300 rounded-lg px-4 py-3 mb-4 text-xs font-semibold text-gray-600">
                This invoice has been cancelled.
            </div>
        <?php endif; ?>

        <!-- Document -->
        <div id="invoice-document" class="bg-white rounded-xl shadow-lg overflow-hidden relative">

            <!-- Watermark -->
            <?php if ($watermark_text): ?>
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none z-10 overflow-hidden">
                <div class="transform -rotate-45 font-heading font-extrabold uppercase tracking-[.2em] opacity-[0.07] whitespace-nowrap"
                     style="font-size: min(120px, 18vw); color: <?= $watermark_color ?>;">
                    <?= $watermark_text ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="p-8 sm:p-10 relative z-20" style="border-top: 4px solid <?= e($primary) ?>;">

                <!-- Header -->
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-6 mb-8">
                    <div>
                        <div class="flex items-center gap-3 mb-3">
                            <?php if ($business['logo_path']): ?>
                                <img src="<?= APP_URL ?>/<?= e($business['logo_path']) ?>" alt="" class="h-12 w-12 object-contain rounded">
                            <?php endif; ?>
                            <div>
                                <h2 class="font-heading font-extrabold text-lg" style="color: <?= e($primary) ?>;"><?= e($business['name']) ?></h2>
                                <?php if ($business['tagline']): ?><p class="text-[10px] text-gray-500"><?= e($business['tagline']) ?></p><?php endif; ?>
                            </div>
                        </div>
                        <div class="text-[10px] text-gray-500 space-y-0.5">
                            <?php if ($business['address']): ?><div><?= e($business['address']) ?></div><?php endif; ?>
                            <?php if ($business['phone']): ?><div>Tel: <?= e($business['phone']) ?></div><?php endif; ?>
                            <?php if ($business['email']): ?><div><?= e($business['email']) ?></div><?php endif; ?>
                        </div>
                    </div>
                    <div class="text-right">
                        <h1 class="font-heading font-extrabold text-2xl uppercase tracking-wider" style="color: <?= e($primary) ?>;">Commercial Invoice</h1>
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

                <!-- Client -->
                <?php if ($customer): ?>
                <div class="mb-8 p-4 rounded-lg" style="background: <?= e($primary) ?>0A;">
                    <div class="text-[9px] font-bold uppercase tracking-widest mb-1" style="color: <?= e($primary) ?>;">Bill To</div>
                    <div class="text-sm font-bold text-gray-800"><?= e($customer['full_name']) ?></div>
                    <?php if (!empty($customer['business_name'])): ?><div class="text-[10px] text-gray-600"><?= e($customer['business_name']) ?></div><?php endif; ?>
                    <?php if (!empty($customer['phone'])): ?><div class="text-[10px] text-gray-500"><?= e($customer['phone']) ?></div><?php endif; ?>
                    <?php if (!empty($customer['email'])): ?><div class="text-[10px] text-gray-500"><?= e($customer['email']) ?></div><?php endif; ?>
                    <?php if (!empty($customer['address'])): ?><div class="text-[10px] text-gray-500"><?= e($customer['address']) ?></div><?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Items Table -->
                <table class="w-full text-left mb-6">
                    <thead>
                        <tr style="border-bottom: 2px solid <?= e($primary) ?>;">
                            <th class="pb-2 text-[9px] font-bold uppercase tracking-widest text-gray-500">#</th>
                            <th class="pb-2 text-[9px] font-bold uppercase tracking-widest text-gray-500">Description</th>
                            <th class="pb-2 text-[9px] font-bold uppercase tracking-widest text-gray-500 text-center">Qty</th>
                            <th class="pb-2 text-[9px] font-bold uppercase tracking-widest text-gray-500 text-right">Unit Price</th>
                            <th class="pb-2 text-[9px] font-bold uppercase tracking-widest text-gray-500 text-center">Disc.</th>
                            <th class="pb-2 text-[9px] font-bold uppercase tracking-widest text-gray-500 text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($items as $idx => $it): ?>
                            <tr>
                                <td class="py-3 text-[10px] text-gray-400"><?= $idx + 1 ?></td>
                                <td class="py-3 text-xs font-semibold text-gray-800"><?= e($it['description']) ?></td>
                                <td class="py-3 data-text text-xs text-gray-700 text-center"><?= number_format((float)$it['quantity'], 2) ?></td>
                                <td class="py-3 data-text text-xs text-gray-700 text-right"><?= money((float)$it['unit_price'], $currency) ?></td>
                                <td class="py-3 data-text text-xs text-gray-500 text-center"><?= (float)$it['discount_percent'] > 0 ? number_format((float)$it['discount_percent'], 1) . '%' : '—' ?></td>
                                <td class="py-3 data-text text-xs font-bold text-gray-800 text-right"><?= money((float)$it['subtotal'], $currency) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Totals -->
                <div class="flex justify-end mb-8">
                    <div class="w-72 space-y-2">
                        <div class="flex justify-between text-xs"><span class="text-gray-500">Subtotal</span><span class="data-text font-bold text-gray-700"><?= money((float)$invoice['subtotal'], $currency) ?></span></div>
                        <?php if ((float)$invoice['discount_amount'] > 0): ?>
                            <div class="flex justify-between text-xs"><span class="text-gray-500">Discount</span><span class="data-text font-bold text-red-600">-<?= money((float)$invoice['discount_amount'], $currency) ?></span></div>
                        <?php endif; ?>
                        <?php if ((float)$invoice['tax_rate'] > 0): ?>
                            <div class="flex justify-between text-xs"><span class="text-gray-500">Tax (<?= number_format((float)$invoice['tax_rate'], 1) ?>%)</span><span class="data-text font-bold text-gray-700"><?= money((float)$invoice['tax_amount'], $currency) ?></span></div>
                        <?php endif; ?>
                        <div class="flex justify-between text-sm pt-2" style="border-top: 2px solid <?= e($primary) ?>;">
                            <span class="font-heading font-bold uppercase tracking-wider" style="color: <?= e($primary) ?>;">Grand Total</span>
                            <span class="font-heading font-extrabold text-lg" style="color: <?= e($primary) ?>;"><?= money((float)$invoice['total'], $currency) ?></span>
                        </div>

                        <!-- Amount Paid / Balance Due -->
                        <?php if ((float)$invoice['amount_paid'] > 0): ?>
                            <div class="flex justify-between text-xs pt-1">
                                <span class="text-green-600 font-semibold">Amount Paid</span>
                                <span class="data-text font-bold text-green-600">-<?= money((float)$invoice['amount_paid'], $currency) ?></span>
                            </div>
                            <div class="flex justify-between text-sm pt-1" style="border-top: 1px dashed <?= e($primary) ?>66;">
                                <span class="font-heading font-bold uppercase tracking-wider <?= (float)$invoice['balance_due'] > 0 ? 'text-red-600' : 'text-green-600' ?>">Balance Due</span>
                                <span class="font-heading font-extrabold text-lg <?= (float)$invoice['balance_due'] > 0 ? 'text-red-600' : 'text-green-600' ?>">
                                    <?= (float)$invoice['balance_due'] > 0 ? money((float)$invoice['balance_due'], $currency) : 'PAID' ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Bank Details -->
                <?php if ($bank && (float)$invoice['balance_due'] > 0): ?>
                <div class="p-4 rounded-lg bg-gray-50 border border-gray-200 mb-6">
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
                <div class="mb-4">
                    <div class="text-[9px] font-bold uppercase tracking-widest text-gray-500 mb-1">Payment Terms</div>
                    <p class="text-xs text-gray-600 leading-relaxed whitespace-pre-line"><?= e($invoice['payment_terms']) ?></p>
                </div>
                <?php endif; ?>

                <!-- Notes -->
                <?php if ($invoice['notes']): ?>
                <div class="mb-6">
                    <div class="text-[9px] font-bold uppercase tracking-widest text-gray-500 mb-1">Notes</div>
                    <p class="text-xs text-gray-600 leading-relaxed whitespace-pre-line"><?= e($invoice['notes']) ?></p>
                </div>
                <?php endif; ?>

                <!-- Signature -->
                <div class="pt-6 border-t border-gray-200 grid grid-cols-2 gap-8">
                    <div>
                        <div class="border-b border-gray-300 pb-8 mb-2"></div>
                        <div class="text-[9px] font-bold uppercase tracking-widest text-gray-500">Authorized By</div>
                    </div>
                    <div>
                        <div class="border-b border-gray-300 pb-8 mb-2"></div>
                        <div class="text-[9px] font-bold uppercase tracking-widest text-gray-500">Client Acknowledgement</div>
                    </div>
                </div>

                <?php if ($business['tagline']): ?>
                <div class="mt-8 text-center">
                    <p class="text-[9px] uppercase tracking-[.3em] font-bold" style="color: <?= e($secondary) ?>;"><?= e($business['tagline']) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Powered by footer -->
        <div class="text-center mt-6">
            <p class="text-[9px] text-gray-400">Powered by <a href="https://nfelia.com" target="_blank" rel="noopener" class="font-bold text-gray-500 hover:text-gray-700">Nfelia</a> — Business Operations Platform</p>
        </div>
    </div>

    <script>
    function downloadPDF() {
        html2pdf().set({
            margin: [0.3, 0.3, 0.3, 0.3],
            filename: '<?= e($invoice['doc_number']) ?>.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true },
            jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
        }).from(document.getElementById('invoice-document')).save();
    }
    </script>
</body>
</html>