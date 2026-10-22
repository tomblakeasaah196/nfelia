<?php
/**
 * An order's tracking page, name.nfelia.com/order/{token}: where the order
 * stands, what was ordered, the receipt once it's paid, "order again", and
 * the review link once it's done. Private: no account, just the link the
 * shopper got when they ordered (shop_order_by_token, shop_order_steps).
 *
 * From shell.php: $page['order'], $catalogue, $wa, $currency.
 */
$order   = $page['order'];
$shop    = (string)$business['name'];
$track   = shop_order_steps($order, $shop);
$review  = $order['review'];
$is_wa   = $order['channel'] === 'whatsapp';
$dstatus = (string)($order['delivery']['status'] ?? '');
// 'ready' on a delivery is "sent out", as the steps say (shop_order_steps)
$moving  = in_array($dstatus, ['dispatched', 'in_transit'], true)
        || ($order['status'] === 'ready' && $order['fulfillment_type'] === 'delivery');
$money   = fn($amount) => sf_money((float)$amount, $currency);
$when    = fn(?string $at) => $at ? date('j M, g:ia', strtotime($at)) : '';
$here    = $shop_url . '/order/' . $order['public_token'];

$lead = match (true) {
    $track['state'] === 'turned_down' => $shop . ' couldn\'t take this order. If you already paid, ask them about your money.',
    $track['state'] === 'cancelled'   => 'This order was cancelled.',
    $track['state'] === 'done'        => ($order['fulfillment_type'] === 'delivery' ? 'Delivered' : 'Collected') . '. Thank you for shopping with ' . $shop . '.',
    $dstatus === 'delivered'          => 'Your order has been delivered.',
    $moving                           => 'Your order is on its way.',
    $order['status'] === 'ready'      => 'Your order is ready. Pick it up from ' . $shop . '.',
    in_array($order['status'], ['approved', 'processing'], true) => $shop . ' is getting your order ready.',
    $is_wa                            => 'Send your order to ' . $shop . ' on WhatsApp so they can confirm it with you.',
    // No transfer was made (the shop shows no account): nothing to check yet
    $order['payment_method'] === 'none' => $shop . ' will contact you on the number you gave to confirm it. This page shows each step as it happens.',
    default                           => $shop . ' is checking your payment. This page shows each step as it happens.',
};

// The order's lines, for WhatsApp and for "order again" (only what's still on sale)
$lines = [];
$again = [];
$on_sale = array_column($catalogue['items'], null, 'id');
foreach ($order['items'] as $i => $line) {
    $lines[] = ($i + 1) . '. ' . $line['product_name'] . ' x' . (int)$line['quantity'] . ($line['variant_selection'] ? ' (' . $line['variant_selection'] . ')' : '');
    if (isset($on_sale[(int)$line['product_id']])) {
        $again[] = ['id' => (int)$line['product_id'], 'qty' => (int)$line['quantity'], 'variant' => (string)($line['variant_selection'] ?? ''), 'note' => (string)($line['item_note'] ?? '')];
    }
}
$wa_send = 'Hello ' . $shop . ', this is my order ' . $order['order_number'] . ":\n\n" . implode("\n", $lines)
         . "\n\nTotal: " . $money($order['total']) . "\nName: " . $order['customer_name'] . "\nFollow it here: " . $here;
$wa_ask  = 'Hello ' . $shop . ', I have a question about my order ' . $order['order_number'] . '.';
$waiting_on_wa = $is_wa && $order['status'] === 'pending';

$payment = match (true) {
    $is_wa                                      => 'Agreed with ' . $shop . ' on WhatsApp',
    $order['payment_method'] === 'bank_transfer' => 'Bank transfer' . ($order['payment_proof_path'] ? ', proof sent' : ''),
    $order['payment_method'] === 'mobile_money'  => 'Mobile money' . ($order['payment_proof_path'] ? ', proof sent' : ''),
    default                                     => 'To be agreed with ' . $shop,
};
?>
<section class="sf-section sf-page" aria-labelledby="sf-order-title" data-order="<?= e($order['order_number']) ?>">
    <div class="sf-wrap sf-wrap--narrow">
        <p class="sf-eyebrow">Your order from <?= e($shop) ?></p>
        <h1 class="sf-h1 sf-page__title" id="sf-order-title">Order <?= e($order['order_number']) ?></h1>
        <p class="sf-lead"><?= e($lead) ?></p>

        <?php if ($track['steps']): ?>
            <ol class="sf-track">
                <?php foreach ($track['steps'] as $step): ?>
                    <li class="sf-track__step is-<?= e($step['state']) ?>"<?= $step['state'] === 'now' ? ' aria-current="step"' : '' ?>>
                        <span class="sf-track__dot" aria-hidden="true"><?= $step['state'] === 'done' ? sf_icon('check', 16) : '' ?></span>
                        <div class="sf-track__body">
                            <p class="sf-track__label">
                                <?= e($step['label']) ?>
                                <span class="sf-sr"><?= $step['state'] === 'done' ? '(done)' : ($step['state'] === 'now' ? '(now)' : '(next)') ?></span>
                            </p>
                            <?php if ($step['time']): ?>
                                <p class="sf-track__time"><?= e($when($step['time'])) ?></p>
                            <?php endif; ?>
                            <?php if ($step['detail'] !== '' && $step['state'] !== 'done'): ?>
                                <p class="sf-track__detail"><?= e($step['detail']) ?></p>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>

        <div class="sf-page__actions">
            <?php if ($waiting_on_wa && $wa !== ''): ?>
                <a class="sf-btn sf-btn--block" href="https://wa.me/<?= e($wa) ?>?text=<?= e(rawurlencode($wa_send)) ?>" target="_blank" rel="noopener"><?= sf_icon('whatsapp', 20) ?><span>Send it on WhatsApp</span></a>
            <?php endif; ?>
            <?php if ($review && $review['status'] === 'invited'): ?>
                <a class="sf-btn sf-btn--block" href="/review/<?= e($review['token']) ?>"><?= sf_icon('star', 20) ?><span>Rate your order</span></a>
            <?php endif; ?>
            <?php if (!empty($order['receipt_token'])): ?>
                <a class="sf-btn sf-btn--outline sf-btn--block" href="<?= e(APP_URL . '/doc/' . $order['receipt_token']) ?>" target="_blank" rel="noopener"><?= sf_icon('card', 20) ?><span>See your receipt</span></a>
            <?php endif; ?>
        </div>

        <?php if ($review && $review['status'] !== 'invited' && $review['rating']): ?>
            <div class="sf-card sf-yourreview">
                <p class="sf-card__title">Your review</p>
                <?= sf_stars((float)$review['rating'], 18) ?>
                <?php if ($review['comment']): ?>
                    <div class="sf-yourreview__text"><?= sf_paragraphs((string)$review['comment']) ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="sf-card">
            <h2 class="sf-card__title">What you ordered</h2>
            <ul class="sf-orderlines">
                <?php foreach ($order['items'] as $line): ?>
                    <li class="sf-orderline">
                        <?php $photo = !empty($line['image_path']) ? sf_img((string)$line['image_path'], '56px', '', 'sf-orderline__img') : ''; ?>
                        <?php if ($photo !== ''): ?><span class="sf-orderline__photo" aria-hidden="true"><?= $photo ?></span><?php endif; ?>
                        <div class="sf-orderline__body">
                            <span class="sf-orderline__name"><?= e($line['product_name']) ?></span>
                            <?php $meta = array_filter([(string)$line['variant_selection'], $line['item_note'] ? 'Note: ' . $line['item_note'] : '']); ?>
                            <?php if ($meta): ?>
                                <span class="sf-orderline__meta"><?= e(implode(' · ', $meta)) ?></span>
                            <?php endif; ?>
                            <span class="sf-orderline__meta"><?= (int)$line['quantity'] ?> × <?= e($money($line['unit_price'])) ?></span>
                        </div>
                        <span class="sf-orderline__total"><?= e($money($line['line_total'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="sf-total"><span>Total</span><strong><?= e($money($order['total'])) ?></strong></p>
        </div>

        <dl class="sf-facts">
            <div><dt>Placed</dt><dd><?= e(date('j F Y, g:ia', strtotime((string)$order['created_at']))) ?></dd></div>
            <div>
                <dt><?= $order['fulfillment_type'] === 'delivery' ? 'Delivery to' : 'Pick up' ?></dt>
                <dd><?= $order['fulfillment_type'] === 'delivery' ? nl2br(e((string)$order['delivery_address']), false) : 'From ' . e($shop) . (sf_short_address($business) !== '' ? ', ' . e(sf_short_address($business)) : '') ?></dd>
            </div>
            <div><dt>Payment</dt><dd><?= e($payment) ?></dd></div>
            <div><dt>Name</dt><dd><?= e($order['customer_name']) ?></dd></div>
        </dl>

        <div class="sf-page__actions">
            <?php if ($again): ?>
                <button type="button" class="sf-btn sf-btn--outline sf-btn--block" data-reorder="<?= e(json_encode($again, JSON_UNESCAPED_UNICODE)) ?>"><?= sf_icon('bag', 20) ?><span>Order <?= count($again) === count($order['items']) ? 'these' : 'what\'s still in the shop' ?> again</span></button>
            <?php endif; ?>
            <?php if ($wa !== '' && !$waiting_on_wa): ?>
                <a class="sf-btn sf-btn--outline sf-btn--block" href="https://wa.me/<?= e($wa) ?>?text=<?= e(rawurlencode($wa_ask)) ?>" target="_blank" rel="noopener"><?= sf_icon('whatsapp', 20) ?><span>Ask <?= e($shop) ?> about it</span></a>
            <?php endif; ?>
            <a class="sf-link" href="/">Back to the shop</a>
        </div>

        <p class="sf-fineprint">Keep this link to follow your order. Anyone you send it to can see this page, so share it only with people you trust.</p>
    </div>
</section>
