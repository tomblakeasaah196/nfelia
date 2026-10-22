<?php
/**
 * An invoice: who owes what, what has been paid, and what to do next, with
 * the document itself as the customer sees it. Renders inside
 * views/layouts/app.php.
 *
 * Available from controller:
 *   $user, $business, $invoice, $customer, $items, $bank,
 *   $payments, $quotation, $public_url, $delivered_by_note, $job_plan, $order,
 *   $sign (doc_sign_state(): its signatures and verify code)
 */
$currency = $business['currency_symbol'] ?? '₦';
$doc_colours = brand_doc_colours($business);   // the document in the brand colours, else Nfelia's (app/helpers/brand.php)
$today    = planner_today();
[$status_words, $status_cls] = screen_invoice_tag($invoice['status']);

// Watermark on the document
$watermark_text = match($invoice['status']) {
    'paid'           => 'PAID',
    'partially_paid' => 'PARTIALLY PAID',
    'cancelled'      => 'CANCELLED',
    default          => '',
};

// WhatsApp share message: the invoice leads, its order is named
$wa_number = wa_digits($customer ? (($customer['whatsapp'] ?? '') ?: ($customer['phone'] ?? '')) : '', $business);
$wa_text = rawurlencode(
    "Hello" . ($customer ? ' ' . planner_first_name($customer['full_name']) : '') . ",\n\n"
    . "Here is invoice *{$invoice['doc_number']}*" . ($order ? " for your order {$order['order_number']}" : '') . " from *{$business['name']}*.\n\n"
    . "Total: " . planner_money((float)$invoice['total'], $currency) . "\n"
    . ((float)$invoice['balance_due'] > 0 ? "Balance: " . planner_money((float)$invoice['balance_due'], $currency) . "\n" : "Paid in full\n")
    . ($invoice['due_date'] && (float)$invoice['balance_due'] > 0 ? "Pay by: " . fmt_date($invoice['due_date'], 'd M Y') . "\n" : '')
    . "\nSee it here: {$public_url}"
);
$wa_link = "https://wa.me/{$wa_number}?text={$wa_text}";

// Payment progress
$total_val = max(0.01, (float)$invoice['total']);
$paid_val  = (float)$invoice['amount_paid'];
$balance   = (float)$invoice['balance_due'];
$pct_paid  = min(100, round($paid_val / $total_val * 100));
$can_pay   = in_array($invoice['status'], ['sent', 'partially_paid', 'overdue'], true);
$live      = !in_array($invoice['status'], ['draft', 'cancelled'], true);

// Days overdue
$days_overdue = 0;
if ($invoice['due_date'] && in_array($invoice['status'], ['overdue', 'sent', 'partially_paid'], true)) {
    $diff = (int)((strtotime($today) - strtotime($invoice['due_date'])) / 86400);
    if ($diff > 0) $days_overdue = $diff;
}
$meta = [e($invoice['doc_number'])];
if ($order) {
    $meta[] = 'order <a href="' . APP_URL . '/orders/view/' . (int)$order['id'] . '" data-prefetch>' . e($order['order_number']) . '</a>';
} else {
    $meta[] = 'No order';   // made before every sale was an order
}
$meta[] = 'Issued ' . e(screen_date($invoice['issue_date']));
if ($invoice['due_date'] && $live) {
    $meta[] = $days_overdue > 0
        ? '<span class="is-owed">' . $days_overdue . ' day' . ($days_overdue > 1 ? 's' : '') . ' overdue</span>'
        : ($invoice['due_date'] === $today ? 'Due today' : 'Due ' . e(screen_date($invoice['due_date'])));
}
if ($quotation) {
    $meta[] = 'from <a href="' . APP_URL . '/quotations/view/' . (int)$quotation['id'] . '">' . e($quotation['doc_number']) . '</a>';
}
?>

<div x-data="invoiceView()">
<div class="nf-cols is-doc">
<div>

<!-- Who owes what -->
<section class="nf-doc" aria-label="Invoice <?= e($invoice['doc_number']) ?>">
    <p class="nf-doc-who">
        <?php if ($customer && !empty($customer['deleted_at'])): ?>
            <span><?= e($customer['full_name']) ?> (deleted)</span>
        <?php elseif ($customer): ?>
            <a href="<?= APP_URL ?>/customers/view/<?= (int)$customer['id'] ?>" data-prefetch><?= e($customer['full_name']) ?></a>
        <?php else: ?>
            <span>No client</span>
        <?php endif; ?>
        <span class="nf-tag <?= $status_cls ?>"><?= e($status_words) ?></span>
    </p>
    <p class="nf-doc-amt"><?= planner_money((float)$invoice['total'], $currency) ?></p>
    <p class="nf-doc-meta"><?= implode(' · ', $meta) ?></p>

    <?php if ($live): ?>
        <div class="nf-thread<?= $paid_val > 0 ? '' : ' is-empty' ?>" style="--done: <?= $pct_paid ?>%" role="img" aria-label="<?= $pct_paid ?>% paid"><i></i></div>
        <p class="nf-thread-key">
            <span><b class="is-in"><?= planner_money($paid_val, $currency) ?></b> paid</span>
            <?php if ($balance > 0): ?>
                <span><b class="is-owed"><?= planner_money($balance, $currency) ?></b> left to pay</span>
            <?php else: ?>
                <span>Paid in full</span>
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <?php if ($can_pay): ?>
        <button type="button" class="nf-btn nf-btn-primary" data-open="nf-pay"><?= nav_icon('money') ?>Record payment</button>
        <?php if ($invoice['status'] === 'overdue'): ?>
            <a class="nf-btn nf-btn-ghost nf-wide" href="<?= APP_URL ?>/invoices/send_reminder/<?= (int)$invoice['id'] ?>" data-wa="<?= e(invoice_reminder_wa_link($invoice, $customer ?: null, $business, $order)) ?>" data-no-prefetch><?= nav_icon('whatsapp') ?>Send a reminder</a>
        <?php endif; ?>
    <?php elseif ($invoice['status'] === 'draft'): ?>
        <form method="POST" action="<?= APP_URL ?>/invoices/send/<?= (int)$invoice['id'] ?>"
              onsubmit="return confirm('Mark <?= e($invoice['doc_number']) ?> as sent? Stock for the items on it is taken off your shelves.');">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <button class="nf-btn nf-btn-primary"><?= nav_icon('send') ?>Mark as sent</button>
        </form>
    <?php endif; ?>
</section>

<?php if ($order): ?>
<!-- The order this invoice belongs to: delivery, messages and refunds live there -->
<a class="nf-doc-row nf-inv-order" href="<?= APP_URL ?>/orders/view/<?= (int)$order['id'] ?>" data-prefetch>
    <span class="nf-tile-ic"><?= nav_icon('orders') ?></span>
    <span class="nf-doc-row-main"><b>Order <?= e($order['order_number']) ?></b><small>Delivery, messages and refunds are on the order</small></span>
    <span class="nf-go"><?= nav_icon('chevron') ?></span>
</a>
<?php endif; ?>

<!-- Share it -->
<div class="nf-do">
    <?php $share_pdf = ['url' => $public_url, 'name' => $invoice['doc_number'] . '.pdf', 'title' => 'Invoice ' . $invoice['doc_number'],
                        'text' => 'Invoice ' . $invoice['doc_number'] . (!empty($order) ? ' for order ' . $order['order_number'] : '') . ' from ' . $business['name'] . '. View and pay here: ' . $public_url, 'tile' => true];
          require BASE_PATH . '/views/partials/share_pdf.php'; ?>
    <a class="is-wa" href="<?= e($wa_link) ?>" target="_blank" rel="noopener"><?= nav_icon('whatsapp') ?><span>WhatsApp</span></a>
    <button type="button" data-copy="<?= e($public_url) ?>"><?= nav_icon('copy') ?><span>Copy link</span></button>
    <button type="button" data-open="nf-inv-more" aria-haspopup="dialog"><?= nav_icon('dots') ?><span>More</span></button>
</div>

<!-- Delivery: the promise to the customer (Tasks & Calendar) -->
<?php if ($invoice['status'] !== 'cancelled'):
    $is_delivered  = !empty($invoice['delivered_at']) || !empty($delivered_by_note);
    $deliver_count = (!$is_delivered && $invoice['deliver_by']) ? planner_countdown($invoice['deliver_by'], $today, 'Deliver') : null;
    $tone_cls = ['late' => 'is-owed', 'today' => 'is-owed', 'soon' => 'is-warn', 'later' => ''];
?>
<section class="nf-card<?= !$invoice['deliver_mode'] && !$is_delivered ? ' is-warn' : '' ?>" aria-label="Delivery"
     x-data="{ editing: <?= !$invoice['deliver_mode'] && !$is_delivered ? 'true' : 'false' ?> }">
    <div class="nf-deliver">
        <div class="nf-deliver-txt">
            <?php if ($is_delivered): ?>
                <p class="nf-deliver-h is-in">
                    Delivered<?= !empty($invoice['delivered_at']) ? ' on ' . e(date('D j M', strtotime($invoice['delivered_at']))) : ' (signed delivery note)' ?>
                </p>
            <?php elseif ($invoice['deliver_mode']): ?>
                <p class="nf-deliver-h">
                    <?php $dday = planner_fmt_day($invoice['deliver_by'], $today); ?>
                    Deliver <?= $invoice['deliver_mode'] === 'immediate' ? 'immediately' : (in_array($dday, ['Today', 'Tomorrow'], true) ? strtolower($dday) : 'by ' . e($dday)) ?>
                    <?php if ($deliver_count && !in_array($deliver_count['label'], ['Deliver today', 'Deliver tomorrow'], true)): ?>
                        <span class="nf-tag <?= $tone_cls[$deliver_count['tone']] ?>"><?= e($deliver_count['label']) ?></span>
                    <?php endif; ?>
                </p>
                <p class="nf-deliver-sub"><?= $invoice['deliver_show'] ? 'The customer sees this date on their invoice.' : 'Only you see this date.' ?></p>
            <?php else: ?>
                <p class="nf-deliver-h">When will <?= e(planner_first_name($customer['full_name'] ?? '') ?: 'the customer') ?> get this order?</p>
                <p class="nf-deliver-sub">Set it once and it shows on your Tasks &amp; Calendar with a countdown.</p>
            <?php endif; ?>
            <?php if ($job_plan): ?>
                <a href="<?= APP_URL ?>/tasks" class="nf-link"><?= e($job_plan['title']) ?>: <?= (int)$job_plan['done'] ?> of <?= (int)$job_plan['total'] ?> steps done</a>
            <?php endif; ?>
        </div>
        <div class="nf-deliver-do">
            <?php if ($is_delivered && !empty($invoice['delivered_at'])): ?>
                <form method="POST" action="<?= APP_URL ?>/invoices/mark-delivered/<?= (int)$invoice['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="undo" value="1">
                    <button class="nf-btn nf-btn-ghost">Mark not delivered</button>
                </form>
            <?php elseif (!$is_delivered && $invoice['deliver_mode']): ?>
                <form method="POST" action="<?= APP_URL ?>/invoices/mark-delivered/<?= (int)$invoice['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <button class="nf-btn nf-btn-in">Mark delivered</button>
                </form>
                <button type="button" @click="editing = !editing" :aria-expanded="editing" class="nf-btn nf-btn-ghost">Change date</button>
                <?php if (!$job_plan): ?>
                    <a href="<?= APP_URL ?>/tasks?invoice=<?= (int)$invoice['id'] ?>" class="nf-btn nf-btn-ghost">Plan the job</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php if (!$is_delivered): ?>
    <form x-show="editing" method="POST" action="<?= APP_URL ?>/invoices/set-delivery/<?= (int)$invoice['id'] ?>" class="nf-deliver-form">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <?php
        $df = ['mode' => $invoice['deliver_mode'] ?? '', 'date' => $invoice['deliver_by'] ?? '', 'show' => !empty($invoice['deliver_show']), 'handed' => true, 'id' => 'inv-view', 'legend_hidden' => !$invoice['deliver_mode']];
        require BASE_PATH . '/views/partials/deliver_fields.php';
        ?>
        <button class="nf-btn nf-btn-primary">Save delivery</button>
    </form>
    <?php endif; ?>
</section>
<?php endif; ?>

<!-- Payments -->
<?php if (!empty($payments)): ?>
<section class="nf-card is-flush" aria-labelledby="nf-pays-h">
    <div class="nf-card-h"><h2 id="nf-pays-h">Payments</h2></div>
    <div class="nf-items">
        <?php foreach ($payments as $pay): ?>
            <div class="nf-item">
                <span class="nf-item-main">
                    <?php if ((float)$pay['amount'] < 0): ?>
                        <b>Refund <?= planner_money(-(float)$pay['amount'], $currency) ?></b>
                    <?php else: ?>
                        <b class="is-in"><?= planner_money((float)$pay['amount'], $currency) ?></b>
                    <?php endif; ?>
                    <small><?= e(screen_date($pay['payment_date'])) ?> · <?= e(screen_method($pay['method'])) ?><?= $pay['reference'] ? ' · ' . e($pay['reference']) : '' ?></small>
                </span>
                <?php if ((float)$pay['amount'] < 0): ?>
                <?php elseif ($pay['receipt_id']): ?>
                    <a class="nf-link" href="<?= APP_URL ?>/receipts/view/<?= (int)$pay['receipt_id'] ?>" data-prefetch><?= e($pay['receipt_number']) ?></a>
                <?php else: ?>
                    <a class="nf-link" href="<?= APP_URL ?>/receipts/from_payment/<?= (int)$pay['id'] ?>" data-no-prefetch>Make a receipt</a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if (in_array($invoice['status'] ?? '', ['paid', 'partially_paid'])): ?>
<!-- Deed of Acquisition -->
<section class="nf-card" x-data="{ showDeed: false }" aria-labelledby="nf-deed-h">
    <div class="nf-card-h">
        <div>
            <h2 id="nf-deed-h">Deed of Acquisition</h2>
            <p>A formal record of what <?= e($customer['full_name'] ?? '') ?: 'the customer' ?> bought and now owns.</p>
        </div>
    </div>
    <button type="button" class="nf-btn nf-btn-ghost nf-wide" @click="showDeed = !showDeed" :aria-expanded="showDeed">Issue a deed</button>
    <form x-show="showDeed" action="<?= APP_URL ?>/deed/create" method="POST" class="nf-form nf-deed-form" style="display:none">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="source" value="invoice">
        <input type="hidden" name="source_id" value="<?= (int)$invoice['id'] ?>">
        <label>The story of the piece <small>Optional. It appears on the deed as its description.</small>
            <textarea name="provenance_text" rows="3" placeholder="For example: hand-made from premium materials in our Lagos studio."></textarea>
        </label>
        <input type="hidden" name="sign_box" value="1">
        <label class="nf-switch"><input type="checkbox" role="switch" name="sign_on" value="1"<?= signing_settings_for(get_db(), (int)$business['id'])['deed']['on'] ? ' checked' : '' ?>>
            <span><b>Signed deed</b><small>Signed for <?= e($business['name']) ?> by the owner or an admin</small></span></label>
        <button type="submit" class="nf-btn nf-btn-primary">Issue the deed</button>
    </form>
</section>
<?php endif; ?>

</div>
<div>

<!-- ===== DOCUMENT PREVIEW ===== -->
<section class="nf-paper" aria-labelledby="nf-paper-h">
    <h2 class="nf-paper-h" id="nf-paper-h">What your customer sees <a class="nf-link" href="<?= e($public_url) ?>" target="_blank" rel="noopener">Open</a></h2>
    <div class="nf-sheet-doc">
<div id="invoice-document" class="bg-white overflow-hidden relative">

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
    <div class="p-8 sm:p-10 relative z-20" style="border-top: 4px solid <?= e($doc_colours['primary']) ?>;">

        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-6 mb-8">
            <!-- Business Info (Left) -->
            <div>
                <div class="flex items-center gap-3 mb-3">
                    <?php if ($business['logo_path']): ?>
                        <img src="<?= APP_URL ?>/<?= e($business['logo_path']) ?>" alt="" class="h-12 w-12 object-contain rounded">
                    <?php endif; ?>
                    <div>
                        <h2 class="font-heading font-extrabold text-lg" style="color: <?= e($doc_colours['primary']) ?>;"><?= e($business['name']) ?></h2>
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
                <h1 class="font-heading font-extrabold text-2xl uppercase tracking-wider" style="color: <?= e($doc_colours['primary']) ?>;">Invoice</h1>
                <div class="data-text text-sm font-bold text-gray-700 mt-1"><?= e($invoice['doc_number']) ?></div>
                <?php if ($order): ?><div class="data-text text-xs text-gray-600 mt-0.5">Order: <?= e($order['order_number']) ?></div><?php endif; ?>
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
        <div class="mb-8 p-4 rounded-lg" style="background: <?= e($doc_colours['primary']) ?>0A;">
            <div class="text-[9px] font-bold uppercase tracking-widest mb-1" style="color: <?= e($doc_colours['primary']) ?>;">Bill To</div>
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
                    <tr style="border-bottom: 2px solid <?= e($doc_colours['primary']) ?>;">
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
                <div class="flex justify-between text-sm pt-2" style="border-top: 2px solid <?= e($doc_colours['primary']) ?>;">
                    <span class="font-heading font-bold uppercase tracking-wider" style="color: <?= e($doc_colours['primary']) ?>;">Grand Total</span>
                    <span class="font-heading font-extrabold text-lg" style="color: <?= e($doc_colours['primary']) ?>;"><?= money((float)$invoice['total'], $currency) ?></span>
                </div>

                <!-- Amount Paid / Balance Due -->
                <?php if ((float)$invoice['amount_paid'] > 0): ?>
                    <div class="flex justify-between text-xs pt-1">
                        <span class="text-green-600 font-semibold">Amount Paid</span>
                        <span class="data-text font-bold text-green-600">-<?= money((float)$invoice['amount_paid'], $currency) ?></span>
                    </div>
                    <div class="flex justify-between text-sm pt-1" style="border-top: 1px dashed <?= e($doc_colours['primary']) ?>66;">
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

        <!-- Signatures (only when signed) and the verify code -->
        <?php require BASE_PATH . '/views/partials/doc_signs.php'; ?>

        <!-- Footer -->
        <?php if ($business['tagline']): ?>
        <div class="mt-8 text-center">
            <p class="text-[9px] uppercase tracking-[.3em] font-bold" style="color: <?= e($doc_colours['secondary']) ?>;"><?= e($business['tagline']) ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>
    </div>
</section>

</div>
</div>

<!-- ===== RECORD PAYMENT ===== -->
<?php if ($can_pay): ?>
<?php if ($invoice['status'] !== 'cancelled' && (float)$invoice['amount_paid'] > 0): ?>
<!-- ===== REFUND (money given back; needed before a paid invoice can be cancelled) ===== -->
<dialog class="nf-sheet" id="nf-refund" aria-labelledby="nf-refund-h">
    <div class="nf-grab"></div>
    <div class="nf-sheet-h">
        <h2 id="nf-refund-h">Record a refund</h2>
        <button type="button" class="nf-icon-btn" data-close aria-label="Close"><?= nav_icon('close') ?></button>
    </div>
    <form method="POST" action="<?= APP_URL ?>/invoices/record_refund/<?= (int)$invoice['id'] ?>" class="nf-form">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <p class="nf-help"><?= planner_money((float)$invoice['amount_paid'], $currency) ?> has been paid on <?= e($invoice['doc_number']) ?>. Record what you gave back.</p>
        <label>Amount given back
            <span class="nf-amount"><span><?= e($currency) ?></span>
                <input type="number" name="amount" step="0.01" min="0.01" max="<?= (float)$invoice['amount_paid'] ?>" inputmode="decimal" required>
            </span>
        </label>
        <div class="nf-two">
            <label>Date <input type="date" name="refund_date" value="<?= date('Y-m-d') ?>"></label>
            <label>How you paid it back
                <select name="method">
                    <option value="cash">Cash</option>
                    <option value="bank_transfer">Bank transfer</option>
                    <option value="pos">POS</option>
                    <option value="mobile_money">Mobile money</option>
                    <option value="other">Other</option>
                </select>
            </label>
        </div>
        <label>Note <small>Optional</small>
            <input type="text" name="note" maxlength="500" autocomplete="off">
        </label>
        <div class="nf-actions nf-actions-flush">
            <button type="submit" class="nf-btn nf-btn-primary">Record the refund</button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<dialog class="nf-sheet" id="nf-pay" aria-labelledby="nf-pay-h">
    <div class="nf-grab"></div>
    <div class="nf-sheet-h">
        <h2 id="nf-pay-h">Record payment</h2>
        <button type="button" class="nf-icon-btn" data-close aria-label="Close"><?= nav_icon('close') ?></button>
    </div>
    <form @submit.prevent="submitPayment" class="nf-form" novalidate>
        <p class="nf-help"><?= e($invoice['doc_number']) ?><?= $customer ? ' · ' . e($customer['full_name']) : '' ?> · <?= planner_money($balance, $currency) ?> left to pay</p>
        <label>Amount received
            <span class="nf-amount"><span><?= e($currency) ?></span>
                <input type="number" x-model="payForm.amount" step="0.01" min="0.01" max="<?= $balance ?>" inputmode="decimal" placeholder="0" required>
            </span>
        </label>
        <div class="nf-quick">
            <button type="button" @click="payForm.amount = <?= $balance ?>">All of it, <?= planner_money($balance, $currency) ?></button>
            <?php if ($balance > 1000): ?>
                <button type="button" @click="payForm.amount = Math.round(<?= $balance ?> / 2 * 100) / 100">Half</button>
            <?php endif; ?>
        </div>
        <div class="nf-two">
            <label>Date <input type="date" x-model="payForm.payment_date"></label>
            <label>How they paid
                <select x-model="payForm.method">
                    <option value="cash">Cash</option>
                    <option value="bank_transfer">Bank transfer</option>
                    <option value="pos">POS</option>
                    <option value="mobile_money">Mobile money</option>
                    <option value="cheque">Cheque</option>
                    <option value="other">Other</option>
                </select>
            </label>
        </div>
        <label>Reference <small>Optional, like the transfer number</small>
            <input type="text" x-model="payForm.reference" autocomplete="off">
        </label>
        <label>Note <small>Optional</small>
            <textarea x-model="payForm.note" rows="2"></textarea>
        </label>
        <p class="nf-err" x-show="payError" x-text="payError" x-cloak role="alert"></p>
        <div class="nf-actions nf-actions-flush">
            <button type="submit" class="nf-btn nf-btn-primary" :disabled="payLoading">
                <span x-show="!payLoading">Record payment</span>
                <span x-show="payLoading" x-cloak>Recording…</span>
            </button>
        </div>
    </form>
</dialog>
<?php endif; ?>

</div><!-- end Alpine scope -->

<!-- ===== MORE ===== -->
<dialog class="nf-sheet" id="nf-inv-more" aria-labelledby="nf-inv-more-h">
    <div class="nf-grab"></div>
    <div class="nf-sheet-h">
        <h2 id="nf-inv-more-h"><?= e($invoice['doc_number']) ?></h2>
        <button type="button" class="nf-icon-btn" data-close aria-label="Close"><?= nav_icon('close') ?></button>
    </div>
    <div class="nf-menu-sheet">
        <?php if ($order): ?>
            <a class="nf-row" href="<?= APP_URL ?>/orders/view/<?= (int)$order['id'] ?>"><span class="nf-tile-ic"><?= nav_icon('orders') ?></span>Open order <?= e($order['order_number']) ?></a>
            <?php if (is_boss() && $live && !in_array($order['status'], ['fulfilled', 'cancelled', 'rejected'], true)): ?>
                <a class="nf-row" href="<?= APP_URL ?>/orders/edit/<?= (int)$order['id'] ?>"><span class="nf-tile-ic"><?= nav_icon('edit') ?></span>Change the items</a>
            <?php endif; ?>
        <?php elseif (in_array($invoice['status'], ['draft', 'sent'], true)): ?>
            <a class="nf-row" href="<?= APP_URL ?>/invoices/edit/<?= (int)$invoice['id'] ?>"><span class="nf-tile-ic"><?= nav_icon('edit') ?></span>Edit the invoice</a>
        <?php endif; ?>
        <?php if ($live): ?>
            <a class="nf-row" href="<?= APP_URL ?>/deliveries/create?invoice_id=<?= (int)$invoice['id'] ?>"><span class="nf-tile-ic"><?= nav_icon('delivery') ?></span>Make a delivery note</a>
        <?php endif; ?>
        <a class="nf-row" href="<?= e($public_url) ?>?format=pdf" download="<?= e(preg_replace('/[^A-Za-z0-9._-]+/', '-', $invoice['doc_number'])) ?>.pdf"><span class="nf-tile-ic"><?= nav_icon('download') ?></span>Download PDF</a>
        <a class="nf-row" href="<?= e($public_url) ?>" target="_blank" rel="noopener"><span class="nf-tile-ic"><?= nav_icon('open') ?></span>Open the customer's page</a>
        <?php $sign_row = true; $sign_back = 'invoices/view/' . (int)$invoice['id'];
              require BASE_PATH . '/views/partials/doc_sign_control.php'; ?>
        <?php if ($invoice['status'] !== 'cancelled' && (float)$invoice['amount_paid'] > 0 && is_boss()): // giving money back is for owners and admins ?>
            <?php if ($order): ?>
                <a class="nf-row" href="<?= APP_URL ?>/orders/view/<?= (int)$order['id'] ?>#money"><span class="nf-tile-ic"><?= nav_icon('refund') ?></span>Give money back</a>
            <?php else: ?>
                <button type="button" class="nf-row" data-open="nf-refund"><span class="nf-tile-ic"><?= nav_icon('money') ?></span>Record a refund</button>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($live && is_boss() && $order): // an order's invoice goes with its order: cancel it there, with its reason ?>
            <a class="nf-row is-danger" href="<?= APP_URL ?>/orders/view/<?= (int)$order['id'] ?>#sheet:nf-cancel"><span class="nf-tile-ic"><?= nav_icon('cancel') ?></span>Cancel order <?= e($order['order_number']) ?></a>
        <?php elseif ($live && is_boss()): ?>
            <form method="POST" action="<?= APP_URL ?>/invoices/cancel/<?= (int)$invoice['id'] ?>"
                  onsubmit="return confirm('Cancel <?= e($invoice['doc_number']) ?>? Its stock goes back on your shelves, and what is owed on it is cleared.');">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <button class="nf-row is-danger"><span class="nf-tile-ic"><?= nav_icon('cancel') ?></span>Cancel the invoice</button>
            </form>
        <?php endif; ?>
        <?php if ($invoice['status'] === 'draft'): ?>
            <form method="POST" action="<?= APP_URL ?>/invoices/delete/<?= (int)$invoice['id'] ?>"
                  onsubmit="return confirm('Delete the draft <?= e($invoice['doc_number']) ?> for good?');">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <button class="nf-row is-danger"><span class="nf-tile-ic"><?= nav_icon('trash') ?></span>Delete the draft</button>
            </form>
        <?php endif; ?>
    </div>
</dialog>

<script>
function invoiceView() {
    return {
        payLoading: false,
        payError: '',
        payForm: {
            amount: '',
            payment_date: '<?= $today ?>',
            method: 'cash',
            reference: '',
            note: ''
        },

        submitPayment() {
            this.payError = '';
            const amt = parseFloat(this.payForm.amount);
            if (!amt || amt <= 0) {
                this.payError = 'Enter how much they paid.';
                return;
            }
            if (amt > <?= $balance ?>) {
                this.payError = 'That is more than the <?= e(planner_money($balance, $currency)) ?> left to pay.';
                return;
            }

            this.payLoading = true;

            const formData = new FormData();
            formData.append('csrf_token', window.Nfelia.csrf);
            formData.append('amount', this.payForm.amount);
            formData.append('payment_date', this.payForm.payment_date);
            formData.append('method', this.payForm.method);
            formData.append('reference', this.payForm.reference);
            formData.append('note', this.payForm.note);

            fetch('<?= APP_URL ?>/invoices/record_payment/<?= (int)$invoice['id'] ?>', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            }).then((r) => r.json().catch(() => ({}))).then((res) => {
                if (res.success) {
                    window.location.reload();      // the server leaves a "recorded" message for the next page
                } else {
                    this.payError = res.error || 'The payment was not recorded. Try again.';
                    this.payLoading = false;
                }
            }).catch(() => {
                this.payError = 'No connection, so the payment was not recorded. Try again when you are online.';
                this.payLoading = false;
            });
        }
    };
}
</script>
