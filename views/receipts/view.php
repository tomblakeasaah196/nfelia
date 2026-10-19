<?php
/**
 * A receipt: who paid, how much and how, with the document itself as the
 * customer sees it. Renders inside views/layouts/app.php.
 *
 * Available from controller:
 *   $user, $business, $receipt, $customer, $invoice, $items,
 *   $payment, $public_url, $is_standalone, $order,
 *   $sign (doc_sign_state(): its signature and verify code)
 */
$currency = $business['currency_symbol'] ?? '₦';

// WhatsApp share: the receipt leads, its order is named
$wa_number = wa_digits($customer && empty($customer['is_walk_in']) ? (($customer['whatsapp'] ?? '') ?: ($customer['phone'] ?? '')) : '', $business);
$wa_text = rawurlencode(
    "Hello" . ($customer && empty($customer['is_walk_in']) ? ' ' . planner_first_name($customer['full_name']) : '') . ",\n\n"
    . "Thank you for your payment of " . planner_money((float)$receipt['amount'], $currency) . ". Here is receipt *{$receipt['doc_number']}*"
    . ($order ? " for your order {$order['order_number']}" : '') . " from *{$business['name']}*.\n"
    . ((float)$receipt['balance_remaining'] > 0 ? "Balance: " . planner_money((float)$receipt['balance_remaining'], $currency) . "\n" : '')
    . "\nSee it here: {$public_url}"
);
$wa_link = "https://wa.me/{$wa_number}?text={$wa_text}";
?>

<div class="nf-cols is-doc">
<div>

<!-- Who paid what -->
<section class="nf-doc" aria-label="Receipt <?= e($receipt['doc_number']) ?>">
    <p class="nf-doc-who">
        <?php if ($customer && !empty($customer['deleted_at'])): ?>
            <span><?= e($customer['full_name']) ?> (deleted)</span>
        <?php elseif ($customer): ?>
            <a href="<?= APP_URL ?>/customers/view/<?= (int)$customer['id'] ?>" data-prefetch><?= e($customer['full_name']) ?></a>
        <?php else: ?>
            <span>Walk-in customer</span>
        <?php endif; ?>
        <span class="nf-tag <?= $is_standalone ? 'is-accent' : 'is-in' ?>"><?= $is_standalone ? 'Paid on the spot' : 'Invoice payment' ?></span>
    </p>
    <p class="nf-doc-amt is-in"><?= planner_money((float)$receipt['amount'], $currency) ?></p>
    <p class="nf-doc-meta"><?= e($receipt['doc_number']) ?><?php if ($order): ?> · order <a href="<?= APP_URL ?>/orders/view/<?= (int)$order['id'] ?>" data-prefetch><?= e($order['order_number']) ?></a><?php endif; ?> · <?= e(screen_date($receipt['issue_date'])) ?> · <?= e($receipt['method'] === 'kept' ? 'Kept money' : screen_method($receipt['method'])) ?></p>
    <?php if ($invoice): ?>
        <p class="nf-doc-meta">
            For <a href="<?= APP_URL ?>/invoices/view/<?= (int)$invoice['id'] ?>" data-prefetch><?= e($invoice['doc_number']) ?></a>:
            <?php if ((float)$invoice['balance_due'] > 0): ?>
                <span class="is-owed"><?= planner_money((float)$invoice['balance_due'], $currency) ?></span> still to pay of <?= planner_money((float)$invoice['total'], $currency) ?>.
            <?php else: ?>
                paid in full.
            <?php endif; ?>
        </p>
    <?php endif; ?>
</section>

<!-- Share it -->
<div class="nf-do">
    <?php $share_pdf = ['url' => $public_url, 'name' => $receipt['doc_number'] . '.pdf', 'title' => 'Receipt ' . $receipt['doc_number'],
                        'text' => 'Receipt ' . $receipt['doc_number'] . ' from ' . $business['name'] . ': ' . $public_url, 'tile' => true];
          require BASE_PATH . '/views/partials/share_pdf.php'; ?>
    <?php if ($wa_number): ?>
        <a class="is-wa" href="<?= e($wa_link) ?>" target="_blank" rel="noopener"><?= nav_icon('whatsapp') ?><span>WhatsApp</span></a>
    <?php endif; ?>
    <button type="button" data-copy="<?= e($public_url) ?>"><?= nav_icon('copy') ?><span>Copy link</span></button>
    <button type="button" data-open="nf-rec-more" aria-haspopup="dialog"><?= nav_icon('dots') ?><span>More</span></button>
</div>

<?php if ($is_standalone && !empty($items)): ?>
<!-- What was bought -->
<section class="nf-card is-flush" aria-labelledby="nf-rec-items-h">
    <div class="nf-card-h"><h2 id="nf-rec-items-h">What was bought</h2></div>
    <div class="nf-items">
        <?php foreach ($items as $it): ?>
            <div class="nf-item">
                <span class="nf-item-main">
                    <b><?= e($it['description']) ?></b>
                    <small><?= rtrim(rtrim(number_format((float)$it['quantity'], 2), '0'), '.') ?> × <?= planner_money((float)$it['unit_price'], $currency) ?></small>
                </span>
                <span class="nf-item-end"><b><?= planner_money((float)$it['subtotal'], $currency) ?></b></span>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($receipt['note'])): ?>
<section class="nf-card" aria-labelledby="nf-rec-note-h">
    <div class="nf-card-h"><h2 id="nf-rec-note-h">Note</h2></div>
    <p class="nf-pre"><?= e($receipt['note']) ?></p>
</section>
<?php endif; ?>

</div>
<div>

<!-- ===== DOCUMENT PREVIEW ===== -->
<section class="nf-paper" aria-labelledby="nf-paper-h">
    <h2 class="nf-paper-h" id="nf-paper-h">What your customer sees <a class="nf-link" href="<?= e($public_url) ?>" target="_blank" rel="noopener">Open</a></h2>
    <div class="nf-sheet-doc">
<div id="receipt-document" class="bg-white overflow-hidden">

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
                <h1 class="font-heading font-extrabold text-2xl uppercase tracking-wider" style="color: <?= e($business['primary_color'] ?? '#8B004A') ?>;">Receipt</h1>
                <div class="data-text text-sm font-bold text-gray-700 mt-1"><?= e($receipt['doc_number']) ?></div>
                <?php if ($order): ?><div class="data-text text-xs text-gray-600 mt-0.5">Order: <?= e($order['order_number']) ?></div><?php endif; ?>
                <div class="text-[10px] text-gray-500 mt-2 space-y-0.5">
                    <div>Date: <?= fmt_date($receipt['issue_date'], 'd M Y') ?></div>
                    <div>Method: <?= $receipt['method'] === 'kept' ? 'Kept money' : ucfirst(str_replace('_', ' ', $receipt['method'])) ?></div>
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

        <!-- Signature (only when signed) and the verify code -->
        <?php require BASE_PATH . '/views/partials/doc_signs.php'; ?>

        <!-- Footer -->
        <?php if ($business['tagline']): ?>
        <div class="mt-8 text-center">
            <p class="text-[9px] uppercase tracking-[.3em] font-bold" style="color: <?= e($business['secondary_color'] ?? '#B69F5E') ?>;"><?= e($business['tagline']) ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>
    </div>
</section>

</div>
</div>

<!-- ===== MORE ===== -->
<dialog class="nf-sheet" id="nf-rec-more" aria-labelledby="nf-rec-more-h">
    <div class="nf-grab"></div>
    <div class="nf-sheet-h">
        <h2 id="nf-rec-more-h"><?= e($receipt['doc_number']) ?></h2>
        <button type="button" class="nf-icon-btn" data-close aria-label="Close"><?= nav_icon('close') ?></button>
    </div>
    <div class="nf-menu-sheet">
        <a class="nf-row" href="<?= e($public_url) ?>?format=pdf" download="<?= e(preg_replace('/[^A-Za-z0-9._-]+/', '-', $receipt['doc_number'])) ?>.pdf"><span class="nf-tile-ic"><?= nav_icon('download') ?></span>Download PDF</a>
        <a class="nf-row" href="<?= e($public_url) ?>" target="_blank" rel="noopener"><span class="nf-tile-ic"><?= nav_icon('open') ?></span>Open the customer's page</a>
        <?php $sign_row = true; $sign_back = 'receipts/view/' . (int)$receipt['id'];
              require BASE_PATH . '/views/partials/doc_sign_control.php'; ?>
        <?php if (empty($order)): // an order's receipt stays with its payment ?>
        <form method="POST" action="<?= APP_URL ?>/receipts/delete/<?= (int)$receipt['id'] ?>"
              onsubmit="return confirm('Delete receipt <?= e($receipt['doc_number']) ?>? <?= $invoice ? 'The payment stays recorded on ' . e($invoice['doc_number']) . '.' : 'What it sold goes back on your shelves.' ?>');">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <button class="nf-row is-danger"><span class="nf-tile-ic"><?= nav_icon('trash') ?></span>Delete the receipt</button>
        </form>
        <?php endif; ?>
    </div>
</dialog>
