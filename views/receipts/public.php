<?php
/**
 * Nfelia — Public Receipt View (Standalone, No Auth)
 * Accessed via /doc/{token}
 *
 * Controller (PublicController) provides:
 *   $receipt, $business, $customer, $items, $invoice
 */
$currency  = $business['currency_symbol'] ?? '₦';
$primary   = $business['primary_color'] ?? '#8B004A';
$secondary = $business['secondary_color'] ?? '#B69F5E';
$doc_fonts    = brand_doc_fonts($business);   // the brand fonts (app/helpers/brand.php)

$is_standalone = !$receipt['invoice_id'];

$doc_url   = APP_URL . '/doc/' . e($receipt['token']);
$og_title  = 'Receipt ' . e($receipt['doc_number']) . ' from ' . e($business['name']);
$og_desc   = 'RECEIPT — ' . money((float)$receipt['amount'], $currency)
           . ($customer ? ' from ' . e($customer['full_name']) : '')
           . '. Dated ' . fmt_date($receipt['issue_date'], 'd M Y')
           . '. Method: ' . ucfirst(str_replace('_', ' ', $receipt['method']))
           . ' | Powered by Nfelia';
$og_image  = $business['logo_path']
    ? APP_URL . '/' . e($business['logo_path'])
    : APP_URL . '/og-image.php?biz=' . (int)$business['id'] . '&doc=' . urlencode($receipt['doc_number']);
?>
<!DOCTYPE html>
<html lang="en" prefix="og: https://ogp.me/ns#">
<head>
    <meta charset="UTF-8">
    <?= errors_script_tag() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($receipt['doc_number']) ?> — <?= e($business['name']) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="canonical" href="<?= $doc_url ?>">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= e($business['name']) ?> — via Nfelia">
    <meta property="og:title" content="<?= e($og_title) ?>">
    <meta property="og:description" content="<?= e($og_desc) ?>">
    <meta property="og:image" content="<?= $og_image ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:url" content="<?= $doc_url ?>">
    <meta property="og:locale" content="en_NG">

    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= e($og_title) ?>">
    <meta name="twitter:description" content="<?= e($og_desc) ?>">
    <meta name="twitter:image" content="<?= $og_image ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="<?= e($doc_fonts['url']) ?>" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/public.css?v=<?= @filemtime(BASE_PATH . '/assets/css/public.css') ?>">
    <style>
        :root { --font-heading: <?= $doc_fonts['head_stack'] ?>; --font-body: <?= $doc_fonts['body_stack'] ?>; --font-mono: "Roboto Mono", monospace; }
    </style>
    <style>
        body { font-family: <?= $doc_fonts['body_stack'] ?>; background: #F2EFE7; }
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

        <!-- Document -->
        <div id="receipt-document" class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="p-8 sm:p-10" style="border-top: 4px solid <?= e($primary) ?>;">

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
                        <h1 class="font-heading font-extrabold text-2xl uppercase tracking-wider" style="color: <?= e($primary) ?>;">RECEIPT</h1>
                        <div class="data-text text-sm font-bold text-gray-700 mt-1"><?= e($receipt['doc_number']) ?></div>
                        <?php if (!empty($order_number)): ?><div class="data-text text-xs text-gray-600 mt-0.5">Order: <?= e($order_number) ?></div><?php endif; ?>
                        <div class="text-[10px] text-gray-500 mt-2 space-y-0.5">
                            <div>Date: <?= fmt_date($receipt['issue_date'], 'd M Y') ?></div>
                            <div>Method: <?= ucfirst(str_replace('_', ' ', $receipt['method'])) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Customer -->
                <?php if ($customer): ?>
                <div class="mb-8 p-4 rounded-lg" style="background: <?= e($primary) ?>0A;">
                    <div class="text-[9px] font-bold uppercase tracking-widest mb-1" style="color: <?= e($primary) ?>;">Received From</div>
                    <div class="text-sm font-bold text-gray-800"><?= e($customer['full_name']) ?></div>
                    <?php if (!empty($customer['business_name'])): ?><div class="text-[10px] text-gray-600"><?= e($customer['business_name']) ?></div><?php endif; ?>
                    <?php if (!empty($customer['phone'])): ?><div class="text-[10px] text-gray-500"><?= e($customer['phone']) ?></div><?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Invoice reference -->
                <?php if ($invoice): ?>
                <div class="mb-6 p-4 rounded-lg bg-blue-50 border border-blue-200">
                    <div class="text-[9px] font-bold uppercase tracking-widest text-blue-500 mb-1">RECEIPT</div>
                    <div class="data-text text-sm font-bold text-blue-700"><?= e($invoice['doc_number']) ?></div>
                </div>
                <?php endif; ?>

                <!-- Line Items (standalone) -->
                <?php if ($is_standalone && !empty($items)): ?>
                <table class="w-full text-left mb-6">
                    <thead>
                        <tr style="border-bottom: 2px solid <?= e($primary) ?>;">
                            <th class="pb-2 text-[9px] font-bold uppercase tracking-widest text-gray-500">#</th>
                            <th class="pb-2 text-[9px] font-bold uppercase tracking-widest text-gray-500">Description</th>
                            <th class="pb-2 text-[9px] font-bold uppercase tracking-widest text-gray-500 text-center">Qty</th>
                            <th class="pb-2 text-[9px] font-bold uppercase tracking-widest text-gray-500 text-right">Price</th>
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
                                <td class="py-3 data-text text-xs font-bold text-gray-800 text-right"><?= money((float)$it['subtotal'], $currency) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>

                <!-- Amount -->
                <div class="flex justify-end mb-8">
                    <div class="w-72">
                        <div class="flex justify-between text-sm pt-2" style="border-top: 2px solid <?= e($primary) ?>;">
                            <span class="font-heading font-bold uppercase tracking-wider" style="color: <?= e($primary) ?>;">Amount Received</span>
                            <span class="font-heading font-extrabold text-xl text-green-600"><?= money((float)$receipt['amount'], $currency) ?></span>
                        </div>
                        <?php if ((float)$receipt['balance_remaining'] > 0): ?>
                            <div class="flex justify-between text-xs pt-2 mt-2" style="border-top: 1px dashed <?= e($primary) ?>66;">
                                <span class="text-red-600 font-semibold">Balance Remaining</span>
                                <span class="data-text font-bold text-red-600"><?= money((float)$receipt['balance_remaining'], $currency) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Note -->
                <?php if ($receipt['note']): ?>
                <div class="mb-6">
                    <div class="text-[9px] font-bold uppercase tracking-widest text-gray-500 mb-1">Note</div>
                    <p class="text-xs text-gray-600 leading-relaxed whitespace-pre-line"><?= e($receipt['note']) ?></p>
                </div>
                <?php endif; ?>

                <!-- Confirmation -->
                <div class="p-4 rounded-lg bg-green-50 border border-green-200 text-center mb-8">
                    <p class="text-xs text-green-700 font-semibold">
                        This receipt confirms the payment of <strong><?= money((float)$receipt['amount'], $currency) ?></strong>
                        received on <strong><?= fmt_date($receipt['issue_date'], 'd M Y') ?></strong>
                        via <strong><?= ucfirst(str_replace('_', ' ', $receipt['method'])) ?></strong>.
                    </p>
                </div>

                <!-- Signatures (only when signed) and the verify code -->
                <?php require BASE_PATH . '/views/partials/doc_signs.php'; ?>

                <?php if ($business['tagline']): ?>
                <div class="mt-8 text-center">
                    <p class="text-[9px] uppercase tracking-[.3em] font-bold" style="color: <?= e($secondary) ?>;"><?= e($business['tagline']) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Powered by -->
        <div class="text-center mt-6">
            <p class="text-[9px] text-gray-400">Powered by <a href="https://nfelia.com" target="_blank" rel="noopener" class="font-bold text-gray-500 hover:text-gray-700">Nfelia</a> — Business Operations Platform</p>
        </div>
    </div>

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
</body>
</html>