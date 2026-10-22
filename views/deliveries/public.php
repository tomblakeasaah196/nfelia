<?php
/**
 * Nfelia — Public Delivery Note (Business View)
 * Standalone page: /delivery/{token}
 * Shows the branded dispatch confirmation document.
 * If signed off, shows the e-signature block.
 *
 * Available from PublicController:
 *   $delivery, $business, $items, $customer, $statuses, $providers
 */
$currency  = $business['currency_symbol'] ?? '₦';
['primary' => $primary, 'secondary' => $secondary, 'text' => $text, 'on' => $on] = brand_doc_colours($business);   // the brand colours, else Nfelia's (app/helpers/brand.php)
$doc_fonts = brand_doc_fonts($business);   // the brand fonts (app/helpers/brand.php)
$logo_url  = !empty($business['logo_path']) ? APP_URL . '/' . $business['logo_path'] : null;

// In sentence case ("Pending dispatch"), as the rest of the app writes it
$status_label = ucfirst(strtolower($statuses[$delivery['status']]['label'] ?? str_replace('_', ' ', $delivery['status'])));
$status_color = $statuses[$delivery['status']]['color'] ?? 'bg-gray-100 text-gray-700';
// A tracking link is a web address (DeliveryController checks it): never javascript:
$tracking_link = preg_match('#^https?://#i', (string)$delivery['tracking_url']) ? $delivery['tracking_url'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?= errors_script_tag() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($delivery['doc_number']) ?> — <?= e($business['name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="<?= e($doc_fonts['url']) ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/public.css?v=<?= @filemtime(BASE_PATH . '/assets/css/public.css') ?>">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        :root {
            --brand-primary: <?= e($primary) ?>;
            --brand-secondary: <?= e($secondary) ?>;
            --brand-text: <?= e($text) ?>;
            --on-brand: <?= e($on) ?>;
        }
        body { --font-body: <?= $doc_fonts['body_stack'] ?>; --font-heading: <?= $doc_fonts['head_stack'] ?>; font-family: <?= $doc_fonts['body_stack'] ?>; }
        .brand-primary { color: var(--brand-text); }
        .brand-secondary { color: var(--brand-secondary); }
        .bg-brand-primary { background-color: var(--brand-primary); }
        .bg-brand-secondary { background-color: var(--brand-secondary); }
        .border-brand-primary { border-color: var(--brand-primary); }
        .border-brand-secondary { border-color: var(--brand-secondary); }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">

    <!-- Action Bar -->
    <div class="no-print bg-white border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-3xl mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <?php if ($logo_url): ?>
                    <img src="<?= e($logo_url) ?>" alt="Logo" class="h-8 w-8 object-contain rounded">
                <?php endif; ?>
                <span class="text-sm font-bold brand-primary"><?= e($business['name']) ?></span>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="window.print()" class="px-3 py-1.5 text-sm font-semibold border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">Print</button>
                <button onclick="downloadPDF()" class="px-3 py-1.5 text-sm font-semibold text-on-brand rounded-lg transition-colors bg-brand-primary hover:opacity-90">Download PDF</button>
            </div>
        </div>
    </div>

    <!-- Document -->
    <div class="max-w-3xl mx-auto my-8 px-4" id="delivery-document">
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">

            <!-- Header -->
            <div class="bg-brand-primary text-on-brand px-8 py-6">
                <div class="flex items-start justify-between">
                    <div>
                        <?php if ($logo_url): ?>
                            <img src="<?= e($logo_url) ?>" alt="Logo" class="h-12 w-12 object-contain rounded-lg bg-white/10 p-1 mb-3">
                        <?php endif; ?>
                        <h1 class="text-2xl font-extrabold tracking-tight"><?= e($business['name']) ?></h1>
                        <?php if (!empty($business['address'])): ?>
                            <p class="text-xs opacity-70 mt-1"><?= e($business['address']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($business['phone'])): ?>
                            <p class="text-xs opacity-70"><?= e($business['phone']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="text-right">
                        <div class="text-xs font-semibold opacity-60">Dispatch confirmation</div>
                        <div class="text-xl font-extrabold mt-1"><?= e($delivery['doc_number']) ?></div>
                        <?php if (!empty($order_number)): ?><div class="text-xs font-semibold mt-0.5 opacity-80">Order: <?= e($order_number) ?></div><?php endif; ?>
                        <div class="inline-block mt-2 px-2.5 py-1 rounded text-xs font-semibold <?= $status_color ?>"><?= $status_label ?></div>
                    </div>
                </div>
            </div>

            <!-- Dates Bar -->
            <div class="bg-gray-50 border-b border-gray-200 px-8 py-3 flex flex-wrap gap-6 text-xs">
                <div>
                    <span class="font-semibold text-gray-500">Created</span>
                    <span class="ml-2 font-bold text-gray-700"><?= fmt_date($delivery['created_at'], 'd M Y') ?></span>
                </div>
                <?php if ($delivery['dispatch_date']): ?>
                <div>
                    <span class="font-semibold text-gray-500">Dispatched</span>
                    <span class="ml-2 font-bold text-gray-700"><?= fmt_date($delivery['dispatch_date'], 'd M Y, H:i') ?></span>
                </div>
                <?php endif; ?>
                <?php if ($delivery['estimated_delivery']): ?>
                <div>
                    <span class="font-semibold text-gray-500">ETA</span>
                    <span class="ml-2 font-bold text-gray-700"><?= fmt_date($delivery['estimated_delivery'], 'd M Y, H:i') ?></span>
                </div>
                <?php endif; ?>
                <?php if ($delivery['actual_delivery']): ?>
                <div>
                    <span class="font-semibold text-green-500">Delivered</span>
                    <span class="ml-2 font-bold text-green-700"><?= fmt_date($delivery['actual_delivery'], 'd M Y, H:i') ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="px-8 py-6 space-y-6">

                <!-- From / To -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <div class="text-xs font-semibold text-gray-500 mb-2">From (Sender)</div>
                        <div class="text-sm font-bold brand-primary"><?= e($business['name']) ?></div>
                        <?php if ($delivery['pickup_address']): ?>
                            <p class="text-xs text-gray-600 mt-1 leading-relaxed"><?= nl2br(e($delivery['pickup_address'])) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($business['phone'])): ?>
                            <p class="text-xs text-gray-500 mt-1"><?= e($business['phone']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="text-xs font-semibold text-gray-500 mb-2">To (Recipient)</div>
                        <div class="text-sm font-bold brand-primary"><?= e($delivery['recipient_name']) ?></div>
                        <p class="text-xs text-gray-600 mt-1 leading-relaxed"><?= nl2br(e($delivery['delivery_address'])) ?></p>
                        <?php if ($delivery['recipient_phone']): ?>
                            <p class="text-xs text-gray-500 mt-1"><?= e($delivery['recipient_phone']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Items Table -->
                <div>
                    <div class="text-xs font-semibold text-gray-500 mb-3">Items dispatched</div>
                    <table class="w-full text-left border border-gray-200 rounded-lg overflow-hidden">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-2.5 text-xs font-semibold text-gray-500 border-b border-gray-200">#</th>
                                <th class="px-4 py-2.5 text-xs font-semibold text-gray-500 border-b border-gray-200">Description</th>
                                <th class="px-4 py-2.5 text-xs font-semibold text-gray-500 border-b border-gray-200 text-center">Qty</th>
                                <th class="px-4 py-2.5 text-xs font-semibold text-gray-500 border-b border-gray-200">Unit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $i => $item): ?>
                            <tr class="<?= $i % 2 ? 'bg-gray-50/50' : '' ?>">
                                <td class="px-4 py-2.5 text-xs text-gray-400 border-b border-gray-100"><?= $i + 1 ?></td>
                                <td class="px-4 py-2.5 text-xs font-semibold text-gray-800 border-b border-gray-100">
                                    <?= e($item['product_name'] ?: $item['description']) ?>
                                    <?php if ($item['notes']): ?>
                                        <span class="text-xs text-gray-400 block"><?= e($item['notes']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-2.5 text-xs font-bold text-gray-800 border-b border-gray-100 text-center"><?= number_format((float)$item['quantity'], $item['quantity'] == (int)$item['quantity'] ? 0 : 2) ?></td>
                                <td class="px-4 py-2.5 text-xs text-gray-500 border-b border-gray-100"><?= $item['unit'] ? e($item['unit']) : '—' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Provider / Rider -->
                <?php if ($delivery['provider'] || $delivery['rider_name']): ?>
                <div>
                    <div class="text-xs font-semibold text-gray-500 mb-3">Courier or rider</div>
                    <div class="bg-gray-50 rounded-lg p-4 grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <?php if ($delivery['provider']): ?>
                        <div>
                            <div class="text-xs text-gray-500">Provider</div>
                            <div class="text-xs font-bold text-gray-700"><?= $providers[$delivery['provider']] ?? e(ucfirst($delivery['provider'])) ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($delivery['rider_name']): ?>
                        <div>
                            <div class="text-xs text-gray-500">Rider</div>
                            <div class="text-xs font-bold text-gray-700"><?= e($delivery['rider_name']) ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($delivery['rider_phone']): ?>
                        <div>
                            <div class="text-xs text-gray-500">Rider phone</div>
                            <div class="text-xs text-gray-700"><?= e($delivery['rider_phone']) ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($delivery['booking_reference']): ?>
                        <div>
                            <div class="text-xs text-gray-500">Booking ref</div>
                            <div class="text-xs font-bold text-gray-700"><?= e($delivery['booking_reference']) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Tracking -->
                <?php if ($delivery['tracking_number'] || $tracking_link): ?>
                <div>
                    <div class="text-xs font-semibold text-gray-500 mb-3">Tracking</div>
                    <div class="bg-blue-50 rounded-lg p-4 flex items-center justify-between">
                        <div>
                            <?php if ($delivery['tracking_number']): ?>
                                <span class="text-xs font-bold text-blue-800"><?= e($delivery['tracking_number']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($tracking_link): ?>
                            <a href="<?= e($tracking_link) ?>" target="_blank" class="text-sm font-semibold text-blue-600 hover:text-blue-800">Track shipment &rarr;</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Special Instructions -->
                <?php if ($delivery['special_instructions']): ?>
                <div>
                    <div class="text-xs font-semibold text-gray-500 mb-2">Special instructions</div>
                    <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-xs text-amber-800 leading-relaxed"><?= nl2br(e($delivery['special_instructions'])) ?></div>
                </div>
                <?php endif; ?>

                <!-- Proof of delivery: the receiver's confirmation, the signatures (when signed) and the verify code -->
                <div class="border-t-2 border-dashed border-gray-200 pt-6">
                    <div class="text-xs font-semibold text-gray-500 mb-4">Proof of delivery</div>

                    <?php if ($delivery['status'] === 'signed_off' && $delivery['signoff_name']): ?>
                        <div class="bg-green-50 border border-green-200 rounded-xl p-5">
                            <div class="flex items-center gap-2 mb-3">
                                <svg class="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span class="text-sm font-bold text-green-700">Receipt confirmed</span>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <div class="text-xs text-green-500 font-semibold"><?= $delivery['signoff_signature_path'] ? 'Signed by' : 'Received by' ?></div>
                                    <div class="text-sm font-bold text-green-900"><?= e($delivery['signoff_name']) ?></div>
                                </div>
                                <div>
                                    <div class="text-xs text-green-500 font-semibold">Date and time</div>
                                    <div class="text-sm text-green-900"><?= $delivery['signoff_timestamp'] ? fmt_date($delivery['signoff_timestamp'], 'd M Y, H:i') : '—' ?></div>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($delivery['status'] !== 'failed'): ?>
                        <div class="bg-gray-50 border border-gray-200 rounded-xl p-5 text-center">
                            <p class="text-xs text-gray-400 font-medium"><?= !empty($delivery['sign_on']) ? 'Waiting for the receiver to sign for it' : 'Waiting for the receiver to confirm it arrived' ?></p>
                        </div>
                    <?php endif; ?>

                    <?php require BASE_PATH . '/views/partials/doc_signs.php'; ?>
                </div>

            </div>

            <!-- Footer -->
            <div class="bg-gray-50 border-t border-gray-200 px-8 py-4 text-center">
                <p class="text-xs text-gray-400">This is a computer-generated document from <strong><?= e($business['name']) ?></strong> via Nfelia.</p>
            </div>
        </div>
    </div>

    <script>
    function downloadPDF() {
        const el = document.getElementById('delivery-document');
        const opt = {
            margin: 0,
            filename: '<?= e($delivery['doc_number']) ?>.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2pdf: { scrollX: 0, scrollY: 0 },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };
        html2pdf().set(opt).from(el).save();
    }
    </script>
</body>
</html>