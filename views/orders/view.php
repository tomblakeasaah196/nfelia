<?php
/**
 * The order page: the heart of a sale. Who, how much, where it stands and
 * the one thing to do next on top; then WhatsApp, Call, Log and More; then
 * its items, money, delivery, tasks and customer, each folded to one line.
 * Renders inside views/layouts/app.php.
 *
 * Available from controller:
 *   $user, $business, $order, $lines, $invoice, $delivery, $stage, $steps, $pay,
 *   $next, $payments, $refunds, $refund_open, $customer, $kept_here, $kept_total,
 *   $debt, $tasks, $events, $quote, $track_url, $customer_url, $review, $messages, $is_boss,
 *   $email_bounced (when the last email to the customer bounced, app/helpers/bounces.php)
 */
$currency    = $business['currency_symbol'] ?? '₦';
$today       = planner_today();
$oid         = (int)$order['id'];
$num         = (string)$order['order_number'];
$name        = (string)$order['customer_name'] ?: 'Walk-in customer';
$first       = planner_first_name($order['customer_name']) ?: 'the customer';
$is_delivery = $order['fulfillment_type'] === 'delivery';
$walk_in     = $customer && (int)$customer['is_walk_in'] === 1;
$live        = $invoice && !in_array($invoice['status'], ['draft', 'cancelled'], true);
$total       = $invoice ? (float)$invoice['total'] : (float)$order['total'];
$balance     = $pay['balance'];
$paid        = $pay['paid'];
$closed      = in_array($stage['key'], ['cancelled', 'turned_down'], true);
$can_edit    = $is_boss && in_array($stage['key'], ['new', 'confirmed', 'ready'], true)
               && !($stage['key'] === 'new' && !empty($order['payment_proof_path']));   // paid as it is: confirm first
$wa_list     = $messages['list'];
$wa_phone    = $messages['phone'];
$call_phone  = $walk_in ? '' : preg_replace('/[^0-9+]/', '', (string)($order['customer_phone'] ?? ''));
$csrf        = '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';

$channel = [
    'cart_pop' => ['Shop', 'orders'], 'whatsapp' => ['Shop, by WhatsApp', 'whatsapp'], 'manual' => ['Typed in', 'hand'],
    'quick' => ['Quick sale', 'bolt'], 'quote' => ['From a quote', 'quote'],
][$order['channel']] ?? ['Order', 'orders'];
$meta = [e($channel[0]), e(screen_date($order['created_at'])) . ', ' . e(date('g:ia', strtotime($order['created_at'])))];
if ($quote) {
    $meta[] = 'from <a href="' . APP_URL . '/quotations/view/' . (int)$quote['id'] . '" data-prefetch>' . e($quote['doc_number']) . '</a>';
}
if (!empty($order['edited_at'])) {
    $meta[] = 'changed ' . e(strtolower(screen_date($order['edited_at'])));
}

// When they get it: the invoice's delivery promise
$when = $invoice['deliver_by'] ?? null;
$when_words = $when ? planner_fmt_day($when, $today) : null;
$countdown = ($when && !in_array($stage['key'], ['done', 'cancelled', 'turned_down'], true)) ? planner_countdown($when, $today, $is_delivery ? 'Deliver' : 'Ready') : null;
$tone_cls = ['late' => 'is-owed', 'today' => 'is-owed', 'soon' => 'is-warn', 'later' => ''];

$pay_by = ($live && $balance > 0) ? ($invoice['due_date'] ?? null) : null;
$pay_by_late = $pay_by && $pay_by < $today;
$balance_help = 'The balance is in Debts under ' . $first . "'s name, so it is never forgotten. "
    . ($pay_by ? 'It is due by ' . date('D j M', strtotime($pay_by)) . '; you get a reminder on Tasks that day.' : 'Set a pay-by day and you get a reminder on Tasks that day.');

$open_tasks = count(array_filter($tasks, fn($t) => empty($t['done_at'])));
$item_count = count($lines);
$method_words = ['cash' => 'Cash', 'bank_transfer' => 'Transfer', 'mobile_money' => 'MoMo', 'pos' => 'POS', 'cheque' => 'Cheque', 'other' => 'Other', 'kept' => 'Kept money'];
$refunds_paid = array_values(array_filter($refunds, fn($r) => $r['status'] === 'paid'));

// The refund waiting on the customer, ready to send on WhatsApp
$refund_wa = null;
if ($refund_open && $wa_phone !== '') {
    $refund_wa = 'https://wa.me/' . $wa_phone . '?text=' . rawurlencode('Hello ' . $first . ', this is ' . $business['name'] . '. We are sending you '
        . planner_money((float)$refund_open['amount'], $currency) . ' back for order ' . $num . '. Tell us where it should go here: ' . refund_url((string)$refund_open['token']));
}
// The customer's email bounced: ask for the right one on WhatsApp
$email_wa = null;
if ($email_bounced && $wa_phone !== '') {
    $email_wa = 'https://wa.me/' . $wa_phone . '?text=' . rawurlencode('Hello ' . $first . ', this is ' . $business['name'] . '. We couldn’t email you about order '
        . $num . ': ' . $order['customer_email'] . ' doesn’t seem to work. Please reply with your correct email address.');
}
$event_icons = ['created' => 'plus', 'confirmed' => 'check', 'payment' => 'money', 'receipt' => 'receipt', 'ready' => 'delivery', 'done' => 'check',
                'whatsapp' => 'whatsapp', 'refund' => 'refund', 'refund_asked' => 'refund', 'refund_details' => 'refund', 'kept' => 'money',
                'kept_used' => 'money', 'unkept' => 'money', 'lines' => 'edit', 'edited' => 'edit', 'cancelled' => 'cancel', 'turned_down' => 'cancel',
                'when' => 'clock', 'pay_by' => 'clock', 'email' => 'send', 'preparing' => 'clock'];
?>
<div class="nf-order">
<div class="nf-cols is-doc">
<div>

<!-- Who, how much, where it stands, and the one thing to do next -->
<section class="nf-doc nf-order-hero" aria-label="Order <?= e($num) ?>">
    <p class="nf-doc-who">
        <?php if ($customer && !$walk_in && empty($customer['deleted_at'])): ?>
            <a href="<?= APP_URL ?>/customers/view/<?= (int)$customer['id'] ?>" data-prefetch><?= e($name) ?></a>
        <?php else: ?>
            <span><?= e($name) ?></span>
        <?php endif; ?>
    </p>
    <p class="nf-doc-amt"><?= planner_money($total, $currency) ?></p>
    <p class="nf-order-tags">
        <span class="nf-tag <?= $stage['tone'] ?>"><?= e($stage['words']) ?></span>
        <?php if ($pay['words'] !== '' && !$closed): ?><span class="nf-tag <?= $pay['tone'] ?>"><?= e($pay['words']) ?></span><?php endif; ?>
    </p>
    <p class="nf-doc-meta"><?= implode(' · ', $meta) ?></p>

    <?php if ($live): $pct = min(100, round($paid / max(0.01, $total) * 100)); ?>
        <div class="nf-thread<?= $paid > 0 ? '' : ' is-empty' ?>" style="--done: <?= $pct ?>%" role="img" aria-label="<?= $pct ?>% paid"><i></i></div>
        <p class="nf-thread-key">
            <span><b class="is-in"><?= planner_money($paid, $currency) ?></b> paid</span>
            <?php if ($balance > 0): ?>
                <span class="nf-bal">Balance <b class="is-owed"><?= planner_money($balance, $currency) ?></b><?= nf_info('Balance', $balance_help) ?></span>
            <?php elseif ($pay['overpaid'] > 0): ?>
                <span><b class="is-warn"><?= planner_money($pay['overpaid'], $currency) ?></b> paid over</span>
            <?php else: ?>
                <span>Paid in full</span>
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <?php if ($steps): ?>
        <ol class="nf-stepper" aria-label="Where the order is">
            <?php foreach ($steps as $s): ?>
                <li class="is-<?= $s['state'] ?>"<?= $s['state'] === 'now' ? ' aria-current="step"' : '' ?>><span><?= e($s['label']) ?></span></li>
            <?php endforeach; ?>
        </ol>
    <?php elseif ($closed): ?>
        <p class="nf-order-closed"><?= $stage['key'] === 'cancelled' ? 'Cancelled' : 'Turned down' ?><?= !empty($order['cancel_reason']) ? ': ' . e($order['cancel_reason']) : '.' ?></p>
    <?php endif; ?>

    <?php if ($next):
        $next_cls = $next['key'] === 'done' ? 'nf-btn nf-btn-in' : 'nf-btn nf-btn-primary'; ?>
        <div class="nf-hero-do">
        <?php if ($next['key'] === 'confirm'): ?>
            <button type="button" class="nf-btn nf-btn-primary" data-open="nf-confirm"><?= nav_icon('check') ?>Confirm order</button>
            <button type="button" class="nf-btn nf-btn-ghost" data-open="nf-turn-down">Turn down</button>
        <?php elseif ($next['key'] === 'payment'): ?>
            <button type="button" class="nf-btn nf-btn-primary" data-open="nf-pay"><?= nav_icon('money') ?>Record payment</button>
        <?php else: ?>
            <form method="POST" action="<?= APP_URL ?>/orders/stage/<?= $oid ?>"><?= $csrf ?>
                <input type="hidden" name="to" value="<?= e($next['key']) ?>">
                <button class="<?= $next_cls ?>"><?= nav_icon($next['icon']) ?><?= e($next['label']) ?></button>
            </form>
            <?php if ($live && $balance > 0): ?>
                <button type="button" class="nf-btn nf-btn-ghost" data-open="nf-pay"><?= nav_icon('money') ?>Record payment</button>
            <?php endif; ?>
        <?php endif; ?>
        </div>
    <?php elseif ($stage['key'] === 'done'): ?>
        <p class="nf-order-done"><?= nav_icon('check') ?><?= $is_delivery ? 'Delivered' : 'Collected' ?><?= !empty($order['done_at']) ? ' ' . e(strtolower(screen_date($order['done_at']))) : '' ?>, and paid in full.</p>
    <?php endif; ?>
</section>

<!-- WhatsApp, Call, Log, More -->
<div class="nf-do">
    <button type="button" class="is-wa" data-open="nf-wa" aria-haspopup="dialog"><?= nav_icon('whatsapp') ?><span>WhatsApp</span></button>
    <?php if ($call_phone !== ''): ?>
        <a href="tel:<?= e($call_phone) ?>"><?= nav_icon('call') ?><span>Call</span></a>
    <?php else: ?>
        <button type="button" disabled><?= nav_icon('call') ?><span>Call</span></button>
    <?php endif; ?>
    <button type="button" data-open="nf-log" aria-haspopup="dialog"><?= nav_icon('history') ?><span>Log</span></button>
    <button type="button" data-open="nf-more" aria-haspopup="dialog"><?= nav_icon('dots') ?><span>More</span></button>
</div>

<?php if ($pay['overpaid'] > 0 && !$closed && !$refund_open): ?>
    <!-- Paid over after a change: give it back or keep it -->
    <section class="nf-card is-warn nf-callout" aria-labelledby="nf-over-h">
        <h2 id="nf-over-h"><?= e(ucfirst($first)) ?> paid <?= planner_money($pay['overpaid'], $currency) ?> more than the new total</h2>
        <p>The order now comes to <?= planner_money($total, $currency) ?>, and <?= planner_money($paid, $currency) ?> was paid.</p>
        <?php if ($is_boss): ?>
            <div class="nf-callout-do">
                <?php if (!$walk_in): ?>
                    <form method="POST" action="<?= APP_URL ?>/orders/keep/<?= $oid ?>"><?= $csrf ?><button class="nf-btn nf-btn-primary">Keep for next order</button></form>
                <?php endif; ?>
                <button type="button" class="nf-btn nf-btn-ghost" data-open="nf-refund"><?= nav_icon('refund') ?>Give it back</button>
            </div>
        <?php else: ?>
            <p class="nf-help">The owner decides whether to give it back or keep it for next time.</p>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($kept_total > 0 && $balance > 0 && !$closed && $kept_here <= 0): ?>
    <!-- Money this customer has with the shop -->
    <section class="nf-card nf-callout is-kept" aria-labelledby="nf-kept-h">
        <h2 id="nf-kept-h"><?= e(ucfirst($first)) ?> has <?= planner_money($kept_total, $currency) ?> kept with you</h2>
        <p>From an earlier order. Use it to pay <?= $kept_total >= $balance ? 'this balance' : 'part of this balance' ?>.</p>
        <form method="POST" action="<?= APP_URL ?>/orders/use_kept/<?= $oid ?>"><?= $csrf ?>
            <button class="nf-btn nf-btn-primary"><?= nav_icon('money') ?>Use <?= planner_money(min($kept_total, $balance), $currency) ?></button>
        </form>
    </section>
<?php endif; ?>

<?php if ($refund_open): ?>
    <!-- A refund waiting: for the customer's details, or for the owner to send it -->
    <section class="nf-card nf-callout" id="refund" aria-labelledby="nf-rf-h">
        <?php if ($refund_open['status'] === 'details_in'): ?>
            <h2 id="nf-rf-h">Send <?= e($first) ?> <?= planner_money((float)$refund_open['amount'], $currency) ?></h2>
            <p><?= e(ucfirst($first)) ?> told you where it goes:</p>
            <dl class="nf-kv nf-payee">
                <div><dt>Name</dt><dd><?= e($refund_open['payee_name']) ?></dd></div>
                <div><dt><?= $refund_open['payee_kind'] === 'momo' ? 'Network' : 'Bank' ?></dt><dd><?= e($refund_open['payee_bank']) ?></dd></div>
                <div><dt><?= $refund_open['payee_kind'] === 'momo' ? 'Number' : 'Account' ?></dt>
                    <dd><button type="button" class="nf-copy" data-copy="<?= e($refund_open['payee_number']) ?>" aria-label="Copy <?= e($refund_open['payee_number']) ?>"><?= e($refund_open['payee_number']) ?><?= nav_icon('copy') ?></button></dd></div>
            </dl>
        <?php else: ?>
            <h2 id="nf-rf-h">Waiting for <?= e($first) ?>'s account details</h2>
            <p>For a refund of <?= planner_money((float)$refund_open['amount'], $currency) ?>. <?= (int)$refund_open['asked_email'] ? 'The link went by email' . ($refund_open['asked_at'] ? ' ' . e(strtolower(screen_date($refund_open['asked_at']))) : '') . '.' : 'Send the link so they can tell you.' ?></p>
        <?php endif; ?>
        <?php if ($is_boss): ?>
            <div class="nf-callout-do">
                <button type="button" class="nf-btn nf-btn-primary" data-open="nf-refund"><?= nav_icon('refund') ?>Record refund</button>
                <?php if ($refund_wa && $refund_open['status'] === 'asked'): ?>
                    <a class="nf-btn nf-btn-ghost" href="<?= e($refund_wa) ?>" target="_blank" rel="noopener"><?= nav_icon('whatsapp') ?>Send link</a>
                <?php elseif ($refund_open['status'] === 'asked'): ?>
                    <button type="button" class="nf-btn nf-btn-ghost" data-copy="<?= e(refund_url((string)$refund_open['token'])) ?>"><?= nav_icon('copy') ?>Copy link</button>
                <?php endif; ?>
            </div>
            <form method="POST" action="<?= APP_URL ?>/orders/refund_off/<?= $oid ?>"
                  onsubmit="return confirm('Call off this refund?<?= $walk_in ? '' : ' Then you can keep the money for the next order instead.' ?>');"><?= $csrf ?>
                <button class="nf-link">Call it off</button>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($email_bounced): ?>
    <!-- Emails to the customer don't arrive: ask for the right address, then save it -->
    <section class="nf-card is-warn nf-callout" id="email" aria-labelledby="nf-em-h">
        <h2 id="nf-em-h">Emails to <?= e($first) ?> don’t arrive</h2>
        <p>The mail server says <?= e($order['customer_email']) ?> doesn’t exist. Ask <?= e($first) ?> for the right one<?= $email_wa ? ' on WhatsApp' : '' ?>, then save it here.</p>
        <?php if ($email_wa): ?>
            <div class="nf-callout-do">
                <a class="nf-btn nf-btn-primary" href="<?= e($email_wa) ?>" target="_blank" rel="noopener"><?= nav_icon('whatsapp') ?>Ask on WhatsApp</a>
            </div>
        <?php endif; ?>
        <form class="nf-payby" method="POST" action="<?= APP_URL ?>/orders/email/<?= $oid ?>"><?= $csrf ?>
            <label for="nf-email-new">Their right email</label>
            <span class="nf-payby-row">
                <input id="nf-email-new" type="email" name="email" maxlength="100" autocomplete="off" required>
                <button class="nf-btn nf-btn-ghost">Save</button>
            </span>
        </form>
    </section>
<?php endif; ?>

</div>
<div>

<!-- Items -->
<details class="nf-fold" id="items"<?= $stage['key'] === 'new' ? ' open' : '' ?>>
    <summary>
        <span class="nf-tile-ic"><?= nav_icon('orders') ?></span>
        <span class="nf-fold-t">Items</span>
        <span class="nf-fold-end"><?= $item_count ?> · <?= planner_money($total, $currency) ?></span>
        <?= nav_icon('chevron') ?>
    </summary>
    <div class="nf-fold-body">
        <ul class="nf-lines">
            <?php foreach ($lines as $l):
                $short = $stage['key'] === 'new' && $l['product_id'] && !(int)($l['is_service'] ?? 0) && $l['stock_qty'] !== null && (float)$l['stock_qty'] < (float)$l['quantity']; ?>
                <li>
                    <span class="nf-line-main">
                        <b><?= e($l['product_name']) ?></b>
                        <?php if (!empty($l['variant_selection'])): ?><small><?= e($l['variant_selection']) ?></small><?php endif; ?>
                        <?php if (!empty($l['item_note'])): ?><small class="nf-line-note">“<?= e($l['item_note']) ?>”</small><?php endif; ?>
                        <?php if ($short): ?><small class="is-owed">Only <?= fmt_qty((float)$l['stock_qty']) ?> in stock</small><?php endif; ?>
                    </span>
                    <span class="nf-line-end">
                        <b><?= planner_money((float)$l['line_total'], $currency) ?></b>
                        <small><?= fmt_qty((float)$l['quantity']) ?> × <?= planner_money((float)$l['unit_price'], $currency) ?></small>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
        <dl class="nf-kv">
            <?php if ((float)$order['discount_amount'] > 0): ?>
                <div><dt>Discount</dt><dd>−<?= planner_money((float)$order['discount_amount'], $currency) ?></dd></div>
            <?php endif; ?>
            <div class="is-big"><dt>Total</dt><dd><?= planner_money($total, $currency) ?></dd></div>
        </dl>
        <?php if ($can_edit): ?>
            <a class="nf-btn nf-btn-ghost nf-wide" href="<?= APP_URL ?>/orders/edit/<?= $oid ?>"><?= nav_icon('edit') ?>Change items</a>
        <?php endif; ?>
    </div>
</details>

<!-- Money: the invoice, every payment with its receipt, refunds, kept money -->
<details class="nf-fold" id="money"<?= ($pay['overpaid'] > 0 || $refund_open) ? ' open' : '' ?>>
    <summary>
        <span class="nf-tile-ic"><?= nav_icon('money') ?></span>
        <span class="nf-fold-t">Money</span>
        <span class="nf-fold-end"><?php
            if (!$invoice) echo 'No invoice yet';
            elseif ($balance > 0) echo '<span class="is-owed">' . planner_money($balance, $currency) . ' left</span>';
            elseif ($pay['overpaid'] > 0) echo '<span class="is-warn">' . planner_money($pay['overpaid'], $currency) . ' over</span>';
            else echo e($invoice['doc_number']); ?></span>
        <?= nav_icon('chevron') ?>
    </summary>
    <div class="nf-fold-body">
        <?php if (!$invoice): ?>
            <p class="nf-help">Confirming the order makes its invoice.</p>
        <?php else: [$inv_words, $inv_cls] = screen_invoice_tag($invoice['status']); ?>
            <a class="nf-doc-row" href="<?= APP_URL ?>/invoices/view/<?= (int)$invoice['id'] ?>" data-prefetch>
                <span class="nf-tile-ic"><?= nav_icon('invoice') ?></span>
                <span class="nf-doc-row-main"><b>Invoice <?= e($invoice['doc_number']) ?></b><small>For order <?= e($num) ?></small></span>
                <span class="nf-tag <?= $inv_cls ?>"><?= e($inv_words) ?></span>
            </a>

            <?php if ($payments): ?>
                <ul class="nf-pays" aria-label="Payments">
                    <?php foreach ($payments as $p):
                        $amt = (float)$p['amount'];
                        $is_kept = $p['method'] === 'kept'; ?>
                        <li>
                            <span class="nf-pay-main">
                                <b class="<?= $amt < 0 ? '' : ($is_kept ? '' : 'is-in') ?>"><?= $amt < 0 ? '−' : '' ?><?= planner_money(abs($amt), $currency) ?></b>
                                <small><?= e(screen_date($p['payment_date'])) ?> · <?= $is_kept ? ($amt < 0 ? 'Kept for next order' : 'Kept money used') : ($amt < 0 ? 'Refund' : e($method_words[$p['method']] ?? ucfirst($p['method']))) ?><?= $p['reference'] ? ' · ' . e($p['reference']) : '' ?></small>
                            </span>
                            <?php if (!empty($p['receipt_id'])): ?>
                                <a class="nf-link" href="<?= APP_URL ?>/receipts/view/<?= (int)$p['receipt_id'] ?>" data-prefetch><?= e($p['receipt_number']) ?></a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php foreach ($refunds_paid as $r): ?>
                <div class="nf-doc-row is-plain">
                    <span class="nf-tile-ic"><?= nav_icon('refund') ?></span>
                    <span class="nf-doc-row-main"><b>Refund <?= e($r['doc_number']) ?> · <?= planner_money((float)$r['amount'], $currency) ?></b>
                        <small><?= e($method_words[$r['method']] ?? '') ?><?= $r['reference'] ? ' · ' . e($r['reference']) : '' ?><?= $r['proof_path'] ? ' · proof kept' : '' ?></small></span>
                    <a class="nf-link" href="<?= e(refund_url((string)$r['token'])) ?>" target="_blank" rel="noopener">Slip</a>
                </div>
            <?php endforeach; ?>

            <?php if ($kept_here > 0): ?>
                <div class="nf-kept-note">
                    <p><b><?= planner_money($kept_here, $currency) ?></b> from this order is kept for <?= e($first) ?>'s next order.</p>
                    <?php if ($is_boss && $invoice && $invoice['status'] !== 'cancelled'): ?>
                        <form method="POST" action="<?= APP_URL ?>/orders/unkeep/<?= $oid ?>"><?= $csrf ?><button class="nf-link">Give it back instead</button></form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($live && $balance > 0): ?>
                <form class="nf-payby" method="POST" action="<?= APP_URL ?>/orders/pay_by/<?= $oid ?>"><?= $csrf ?>
                    <label for="nf-payby-d">Pay by <?= nf_info('Pay-by day', 'The day the balance is due. Debts shows it, and you get a reminder on Tasks that morning. Change it whenever ' . $first . ' asks for more time.') ?></label>
                    <span class="nf-payby-row">
                        <input id="nf-payby-d" type="date" name="pay_by" value="<?= e($pay_by ?? '') ?>" min="<?= e($today) ?>" required<?= $pay_by_late ? ' class="is-late"' : '' ?>>
                        <button class="nf-btn nf-btn-ghost">Save</button>
                    </span>
                    <?php if ($pay_by_late): ?><small class="is-owed"><?= planner_days_between($pay_by, $today) ?> day<?= planner_days_between($pay_by, $today) === 1 ? '' : 's' ?> late</small><?php endif; ?>
                </form>
            <?php endif; ?>

            <div class="nf-fold-do">
                <?php if ($live && $balance > 0): ?>
                    <button type="button" class="nf-btn nf-btn-primary" data-open="nf-pay"><?= nav_icon('money') ?>Record payment</button>
                <?php endif; ?>
                <?php if ($is_boss && $paid > 0 && !$refund_open && $pay['overpaid'] <= 0): ?>
                    <button type="button" class="nf-btn nf-btn-ghost" data-open="nf-refund"><?= nav_icon('refund') ?>Give a refund</button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</details>

<!-- Delivery or pickup -->
<details class="nf-fold" id="delivery">
    <summary>
        <span class="nf-tile-ic"><?= nav_icon($is_delivery ? 'delivery' : 'pin') ?></span>
        <span class="nf-fold-t"><?= $is_delivery ? 'Delivery' : 'Pickup' ?></span>
        <span class="nf-fold-end"><?php
            if ($stage['key'] === 'done') {
                echo $is_delivery ? 'Delivered' : 'Collected';
            } elseif ($when_words) {
                echo e($when_words) . ($is_delivery && !empty($order['delivery_address']) ? ' · ' . e(mb_strimwidth((string)$order['delivery_address'], 0, 22, '…')) : '');
            } else {
                echo 'No day set';
            } ?></span>
        <?= nav_icon('chevron') ?>
    </summary>
    <div class="nf-fold-body">
        <?php if ($countdown && !in_array($countdown['label'], ['Deliver today', 'Deliver tomorrow', 'Ready today', 'Ready tomorrow'], true)): ?>
            <p class="nf-when"><span class="nf-tag <?= $tone_cls[$countdown['tone']] ?? '' ?>"><?= e($countdown['label']) ?></span></p>
        <?php endif; ?>
        <dl class="nf-kv">
            <div><dt><?= $is_delivery ? 'Deliver' : 'Ready' ?></dt><dd><?= $when ? e(date('D j M', strtotime($when))) : 'Not set' ?></dd></div>
            <?php if ($is_delivery): ?>
                <div><dt>Address</dt><dd><?= e($order['delivery_address'] ?: 'Not given') ?></dd></div>
            <?php endif; ?>
            <?php if ($delivery): ?>
                <div><dt>Delivery note</dt><dd><a href="<?= APP_URL ?>/deliveries/view/<?= (int)$delivery['id'] ?>" data-prefetch><?= e($delivery['doc_number']) ?></a></dd></div>
                <?php if (!empty($delivery['rider_name'])): ?><div><dt>Rider</dt><dd><?= e($delivery['rider_name']) ?></dd></div><?php endif; ?>
            <?php endif; ?>
        </dl>
        <?php if ($invoice && !in_array($stage['key'], ['done', 'cancelled', 'turned_down'], true)): ?>
            <form class="nf-payby" method="POST" action="<?= APP_URL ?>/orders/when/<?= $oid ?>"><?= $csrf ?>
                <label for="nf-when-d"><?= $is_delivery ? 'Change the delivery day' : 'Change the pickup day' ?></label>
                <span class="nf-payby-row">
                    <input id="nf-when-d" type="date" name="deliver_by" value="<?= e($when ?? '') ?>" required>
                    <button class="nf-btn nf-btn-ghost">Save</button>
                </span>
            </form>
        <?php endif; ?>
        <?php if ($delivery && $is_delivery && !in_array($delivery['status'], ['signed_off', 'failed'], true)): ?>
            <div class="nf-fold-do">
                <button type="button" class="nf-btn nf-btn-ghost" data-copy="<?= e(APP_URL . '/sign/' . $delivery['signoff_token']) ?>"><?= nav_icon('link') ?>Copy sign-off link</button>
            </div>
            <p class="nf-help">The rider shows it to <?= e($first) ?>, who signs on the phone when it arrives.</p>
        <?php endif; ?>
    </div>
</details>

<!-- Tasks -->
<details class="nf-fold" id="tasks">
    <summary>
        <span class="nf-tile-ic"><?= nav_icon('task') ?></span>
        <span class="nf-fold-t">Tasks</span>
        <span class="nf-fold-end"><?= $open_tasks ? $open_tasks . ' to do' : ($tasks ? 'All done' : 'None') ?></span>
        <?= nav_icon('chevron') ?>
    </summary>
    <div class="nf-fold-body">
        <?php if ($tasks): ?>
            <ul class="nf-tasklist">
                <?php foreach ($tasks as $t): ?>
                    <li class="<?= $t['done_at'] ? 'is-done' : '' ?>">
                        <span class="nf-task-dot" aria-hidden="true"><?= $t['done_at'] ? nav_icon('check') : '' ?></span>
                        <span><b><?= e($t['title']) ?></b>
                            <small><?= $t['due_date'] ? e(planner_fmt_day($t['due_date'], $today)) : 'No date' ?><?= $t['assignee_name'] ? ' · ' . e(planner_first_name($t['assignee_name'])) : '' ?><?= $t['done_at'] ? ' · done' : '' ?></small></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="nf-help">Plan the work for this order: buying fabric, sewing, a fitting, the delivery.</p>
        <?php endif; ?>
        <div class="nf-fold-do">
            <a class="nf-btn nf-btn-ghost" href="<?= APP_URL ?>/tasks?new=task&amp;order=<?= $oid ?>"><?= nav_icon('plus') ?>Add task</a>
            <?php if ($tasks): ?><a class="nf-link" href="<?= APP_URL ?>/tasks">Open Tasks</a><?php endif; ?>
        </div>
    </div>
</details>

<!-- Customer -->
<details class="nf-fold" id="customer">
    <summary>
        <span class="nf-tile-ic"><?= nav_icon('user') ?></span>
        <span class="nf-fold-t">Customer</span>
        <span class="nf-fold-end"><?= e($walk_in ? 'Walk-in' : ($order['customer_phone'] ?: $name)) ?></span>
        <?= nav_icon('chevron') ?>
    </summary>
    <div class="nf-fold-body">
        <dl class="nf-kv">
            <div><dt>Name</dt><dd><?= e($name) ?></dd></div>
            <?php if (!$walk_in && $order['customer_phone']): ?><div><dt>Phone</dt><dd><a href="tel:<?= e($call_phone) ?>"><?= e($order['customer_phone']) ?></a></dd></div><?php endif; ?>
            <?php if (!$walk_in && $order['customer_email']): ?><div><dt>Email</dt><dd><?= e($order['customer_email']) ?></dd></div><?php endif; ?>
            <?php if ($review && $review['status'] === 'published'): ?><div><dt>Review</dt><dd><a href="#review" id="review"><?= str_repeat('★', (int)$review['rating']) ?></a></dd></div><?php endif; ?>
        </dl>
        <?php if (!empty($order['customer_note'])): ?>
            <p class="nf-said"><small>Note from <?= e($first) ?></small><?= nl2br(e($order['customer_note'])) ?></p>
        <?php endif; ?>
        <?php if (!empty($order['notes'])): ?>
            <p class="nf-said is-own"><small>Your note</small><?= nl2br(e($order['notes'])) ?></p>
        <?php endif; ?>
        <?php if (!empty($order['payment_proof_path'])): ?>
            <a class="nf-link" href="<?= APP_URL ?>/orders/proof/<?= $oid ?>" target="_blank" rel="noopener"><?= nav_icon('image') ?>&nbsp;Payment screenshot</a>
        <?php endif; ?>
        <?php if ($customer_url): ?>
            <div class="nf-fold-do">
                <button type="button" class="nf-btn nf-btn-ghost" data-copy="<?= e($customer_url) ?>"><?= nav_icon('link') ?>Copy <?= e($first) ?>'s page</button>
                <a class="nf-link" href="<?= e($customer_url) ?>" target="_blank" rel="noopener">Open it</a>
            </div>
            <p class="nf-help"><?= $track_url ? 'Where ' . e($first) . ' follows the order, step by step.' : 'The invoice ' . e($first) . ' sees, with the money paid.' ?></p>
        <?php endif; ?>
    </div>
</details>

</div>
</div>

<!-- ===== SHEETS ===== -->

<?php if ($stage['key'] === 'new'): $has_proof = !empty($order['payment_proof_path']); ?>
<dialog class="nf-sheet" id="nf-confirm" aria-labelledby="nf-confirm-h">
    <div class="nf-grab"></div>
    <div class="nf-sheet-h"><h2 id="nf-confirm-h">Confirm order</h2><button type="button" class="nf-icon-btn" data-close aria-label="Close"><?= nav_icon('close') ?></button></div>
    <form method="POST" action="<?= APP_URL ?>/orders/confirm/<?= $oid ?>" class="nf-form" x-data="{ paid: <?= $has_proof ? 'true' : 'false' ?>, when: '', day: '' }"><?= $csrf ?>
        <?php if ($has_proof): ?>
            <a class="nf-proof" href="<?= APP_URL ?>/orders/proof/<?= $oid ?>" target="_blank" rel="noopener">
                <img src="<?= APP_URL ?>/orders/proof/<?= $oid ?>" alt="<?= e($first) ?>'s payment screenshot" loading="lazy">
            </a>
            <label class="nf-switch"><input type="checkbox" role="switch" name="payment_received" value="1" checked x-model="paid">
                <span><b><?= planner_money($total, $currency) ?> is in my account</b><small>Saves the payment and makes the receipt.</small></span></label>
        <?php endif; ?>
        <fieldset class="nf-field">
            <legend>When will <?= e($first) ?> get it?</legend>
            <div class="nf-chipset">
                <label><input type="radio" name="when_pick" value="<?= e($today) ?>" x-model="when"><span>Today</span></label>
                <label><input type="radio" name="when_pick" value="<?= e(planner_add_days($today, 1)) ?>" x-model="when"><span>Tomorrow</span></label>
                <label><input type="radio" name="when_pick" value="pick" x-model="when"><span>Pick a day</span></label>
                <label><input type="radio" name="when_pick" value="" x-model="when"><span>Not sure</span></label>
            </div>
            <input type="hidden" name="deliver_by" :value="when === 'pick' ? day : when">
            <input type="date" x-model="day" x-show="when === 'pick'" x-cloak min="<?= e($today) ?>" aria-label="The day <?= e($first) ?> gets it">
        </fieldset>
        <label x-show="!paid">Balance due by <small>Debts follows it, with a reminder that day. Empty: the day they get it, or a week from today.</small>
            <input type="date" name="pay_by" min="<?= e($today) ?>">
        </label>
        <button class="nf-btn nf-btn-primary nf-sheet-go"><?= nav_icon('check') ?>Confirm order</button>
    </form>
</dialog>

<dialog class="nf-sheet" id="nf-turn-down" aria-labelledby="nf-td-h">
    <div class="nf-grab"></div>
    <div class="nf-sheet-h"><h2 id="nf-td-h">Turn down this order?</h2><button type="button" class="nf-icon-btn" data-close aria-label="Close"><?= nav_icon('close') ?></button></div>
    <form method="POST" action="<?= APP_URL ?>/orders/turn_down/<?= $oid ?>" class="nf-form"><?= $csrf ?>
        <p class="nf-help">Nothing is taken off your stock. <?= $order['customer_email'] ? e(ucfirst($first)) . ' gets an email with your reason.' : 'Tell ' . e($first) . ' on WhatsApp too.' ?></p>
        <label>Why <small>Optional. For example: sold out until next week.</small>
            <textarea name="reason" maxlength="300" rows="2"></textarea>
        </label>
        <div class="nf-two-btn">
            <button type="button" class="nf-btn nf-btn-ghost" data-close>Keep it</button>
            <button class="nf-btn nf-btn-danger">Turn down</button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<?php if ($live && $balance > 0): ?>
<dialog class="nf-sheet" id="nf-pay" aria-labelledby="nf-pay-h">
    <div class="nf-grab"></div>
    <div class="nf-sheet-h"><h2 id="nf-pay-h">Record payment</h2><button type="button" class="nf-icon-btn" data-close aria-label="Close"><?= nav_icon('close') ?></button></div>
    <form method="POST" action="<?= APP_URL ?>/orders/pay/<?= $oid ?>" class="nf-form" data-once x-data="{ amount: '<?= e(fmt_qty($balance)) ?>' }"><?= $csrf ?><?= form_once() ?>
        <label>How much <small>Balance <?= planner_money($balance, $currency) ?></small>
            <span class="nf-amount"><span><?= e($currency) ?></span><input type="text" name="amount" inputmode="decimal" x-model="amount" required autocomplete="off"></span>
        </label>
        <div class="nf-quick">
            <button type="button" @click="amount = '<?= e(fmt_qty($balance)) ?>'">All of it</button>
            <?php if ($balance >= 2): ?><button type="button" @click="amount = '<?= e(fmt_qty(money2($balance / 2))) ?>'">Half</button><?php endif; ?>
        </div>
        <?php $pm_id = 'pay'; require BASE_PATH . '/views/orders/_methods.php'; ?>
        <details class="nf-more-field"><summary>Add a reference</summary>
            <label>Reference <small>The transfer or POS reference</small><input type="text" name="reference" maxlength="100" autocomplete="off"></label>
        </details>
        <button class="nf-btn nf-btn-primary nf-sheet-go"><?= nav_icon('check') ?>Save payment</button>
        <p class="nf-help">A receipt is made for <?= e($first) ?> straight away.</p>
    </form>
</dialog>
<?php endif; ?>

<?php if ($is_boss && $invoice && $paid > 0): $refund_default = $pay['overpaid'] > 0 ? $pay['overpaid'] : ($refund_open ? (float)$refund_open['amount'] : $paid); ?>
<dialog class="nf-sheet" id="nf-refund" aria-labelledby="nf-rf2-h">
    <div class="nf-grab"></div>
    <div class="nf-sheet-h"><h2 id="nf-rf2-h">Give money back</h2><button type="button" class="nf-icon-btn" data-close aria-label="Close"><?= nav_icon('close') ?></button></div>
    <div class="nf-form" x-data="{ how: '<?= $refund_open ? 'bank' : 'cash' ?>', sent: <?= $refund_open ? 'true' : 'false' ?> }">
        <fieldset class="nf-field">
            <legend>How are you giving it back?</legend>
            <div class="nf-seg is-2">
                <label><input type="radio" name="how_pick" value="cash" x-model="how"><span class="opt"><?= nav_icon('money') ?>Cash</span></label>
                <label><input type="radio" name="how_pick" value="bank" x-model="how"><span class="opt"><?= nav_icon('send') ?>Transfer or MoMo</span></label>
            </div>
        </fieldset>

        <!-- Cash: straight away, no proof -->
        <form method="POST" action="<?= APP_URL ?>/orders/refund/<?= $oid ?>" x-show="how === 'cash'" class="nf-form is-inner" data-once><?= $csrf ?><?= form_once() ?>
            <input type="hidden" name="method" value="cash">
            <?php if ($refund_open): ?><input type="hidden" name="refund_id" value="<?= (int)$refund_open['id'] ?>"><?php endif; ?>
            <label>How much <small>At most <?= planner_money($paid, $currency) ?></small>
                <span class="nf-amount"><span><?= e($currency) ?></span><input type="text" name="amount" inputmode="decimal" value="<?= e(fmt_qty($refund_default)) ?>" required autocomplete="off"></span></label>
            <label>Why <small>Optional. It shows on the refund slip.</small><input type="text" name="reason" maxlength="300"></label>
            <button class="nf-btn nf-btn-primary nf-sheet-go"><?= nav_icon('refund') ?>Give cash back</button>
        </form>

        <!-- Transfer or MoMo: ask where to send it, then record it with proof -->
        <div x-show="how === 'bank'" x-cloak>
            <?php if (!$refund_open || $refund_open['status'] === 'asked'): ?>
                <form method="POST" action="<?= APP_URL ?>/orders/refund_ask/<?= $oid ?>" class="nf-form is-inner nf-step" x-show="!sent"><?= $csrf ?>
                    <p class="nf-step-h"><span>1</span>Ask <?= e($first) ?> where to send it</p>
                    <label>How much
                        <span class="nf-amount"><span><?= e($currency) ?></span><input type="text" name="amount" inputmode="decimal" value="<?= e(fmt_qty($refund_default)) ?>" required autocomplete="off"></span></label>
                    <label>Why <small>Optional. <?= e(ucfirst($first)) ?> sees it.</small><input type="text" name="reason" maxlength="300" value="<?= e($refund_open['reason'] ?? '') ?>"></label>
                    <button class="nf-btn nf-btn-primary nf-sheet-go"><?= nav_icon('send') ?><?= $refund_open ? 'Ask again' : 'Ask for account details' ?></button>
                    <p class="nf-help"><?= $order['customer_email'] ? 'We email ' . e($first) . ' a link to a short form. You can send the same link on WhatsApp.' : e(ucfirst($first)) . ' has no email: you send the link on WhatsApp next.' ?></p>
                    <button type="button" class="nf-link" @click="sent = true">I already know where to send it</button>
                </form>
            <?php endif; ?>
            <form method="POST" action="<?= APP_URL ?>/orders/refund/<?= $oid ?>" enctype="multipart/form-data" class="nf-form is-inner nf-step" data-once x-show="sent"<?= $refund_open ? '' : ' x-cloak' ?>><?= $csrf ?><?= form_once() ?>
                <p class="nf-step-h"><span>2</span>Record it once you've sent it</p>
                <?php if ($refund_open): ?><input type="hidden" name="refund_id" value="<?= (int)$refund_open['id'] ?>"><?php endif; ?>
                <label>How much
                    <span class="nf-amount"><span><?= e($currency) ?></span><input type="text" name="amount" inputmode="decimal" value="<?= e(fmt_qty($refund_default)) ?>" required autocomplete="off"></span></label>
                <div class="nf-seg is-2">
                    <?php $momo = ($refund_open['payee_kind'] ?? '') === 'momo'; // as the customer said to send it ?>
                    <label><input type="radio" name="method" value="bank_transfer"<?= $momo ? '' : ' checked' ?>><span class="opt">Bank transfer</span></label>
                    <label><input type="radio" name="method" value="mobile_money"<?= $momo ? ' checked' : '' ?>><span class="opt">MoMo</span></label>
                </div>
                <label>Transfer reference <small>Or add the screenshot below. One of them goes on the slip.</small><input type="text" name="reference" maxlength="100" autocomplete="off"></label>
                <label class="nf-file"><?= nav_icon('image') ?><span>Add the screenshot or receipt <small>Picture or PDF</small></span>
                    <input type="file" name="proof" accept="image/jpeg,image/png,image/webp,application/pdf"></label>
                <label>Why <small>Optional</small><input type="text" name="reason" maxlength="300" value="<?= e($refund_open['reason'] ?? '') ?>"></label>
                <button class="nf-btn nf-btn-primary nf-sheet-go"><?= nav_icon('check') ?>Save refund</button>
                <?php if (!$refund_open || $refund_open['status'] === 'asked'): ?>
                    <button type="button" class="nf-link" @click="sent = false">Back to asking</button>
                <?php endif; ?>
            </form>
        </div>
    </div>
</dialog>
<?php endif; ?>

<dialog class="nf-sheet" id="nf-wa" aria-labelledby="nf-wa-h">
    <div class="nf-grab"></div>
    <div class="nf-sheet-h"><h2 id="nf-wa-h">Message <?= e($first) ?></h2><button type="button" class="nf-icon-btn" data-close aria-label="Close"><?= nav_icon('close') ?></button></div>
    <?php if ($wa_phone === ''): ?>
        <p class="nf-sheet-note">There is no phone number for <?= $walk_in ? 'a walk-in customer' : e($first) ?>.<?= ($customer && !$walk_in) ? ' Add one on their client page.' : '' ?></p>
    <?php else: ?>
        <p class="nf-sheet-note">Pick a message. It opens in WhatsApp, ready to send, and goes on the Log.</p>
        <div class="nf-menu-sheet">
            <?php foreach ($wa_list as $key => $m): ?>
                <a class="nf-row nf-wa-row" href="<?= APP_URL ?>/orders/wa/<?= $oid ?>?m=<?= e($key) ?>" data-wa="https://wa.me/<?= e($wa_phone) ?>?text=<?= e(rawurlencode($m['text'])) ?>" target="_blank" rel="noopener" data-no-prefetch>
                    <span class="nf-tile-ic"><?= nav_icon($m['icon']) ?></span>
                    <span class="nf-wa-txt"><b><?= e($m['label']) ?></b><small><?= e(mb_strimwidth(str_replace("\n", ' ', $m['text']), 0, 90, '…')) ?></small></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</dialog>

<dialog class="nf-sheet" id="nf-more" aria-labelledby="nf-more-h">
    <div class="nf-grab"></div>
    <div class="nf-sheet-h"><h2 id="nf-more-h"><?= e($num) ?></h2><button type="button" class="nf-icon-btn" data-close aria-label="Close"><?= nav_icon('close') ?></button></div>
    <div class="nf-menu-sheet">
        <?php if ($can_edit): ?>
            <a class="nf-row" href="<?= APP_URL ?>/orders/edit/<?= $oid ?>"><span class="nf-tile-ic"><?= nav_icon('edit') ?></span>Change items</a>
        <?php endif; ?>
        <?php if ($invoice): ?>
            <a class="nf-row" href="<?= APP_URL ?>/invoices/view/<?= (int)$invoice['id'] ?>"><span class="nf-tile-ic"><?= nav_icon('invoice') ?></span>Open invoice <?= e($invoice['doc_number']) ?></a>
            <?php if ($live): ?>
                <a class="nf-row" href="<?= APP_URL ?>/doc/<?= e($invoice['token']) ?>?format=pdf" target="_blank" rel="noopener"><span class="nf-tile-ic"><?= nav_icon('download') ?></span>Invoice PDF</a>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($customer_url): ?>
            <button type="button" class="nf-row" data-copy="<?= e($customer_url) ?>"><span class="nf-tile-ic"><?= nav_icon('link') ?></span>Copy <?= e($first) ?>'s page</button>
        <?php endif; ?>
        <?php if ($stage['key'] === 'done' && $is_boss && empty($review['rating'])): // not once the customer has reviewed it ?>
            <form method="POST" action="<?= APP_URL ?>/orders/stage/<?= $oid ?>"><?= $csrf ?><input type="hidden" name="to" value="undo_done">
                <button class="nf-row"><span class="nf-tile-ic"><?= nav_icon('refund') ?></span>Mark not <?= $is_delivery ? 'delivered' : 'collected' ?></button></form>
        <?php endif; ?>
        <?php if ($stage['key'] === 'new'): ?>
            <button type="button" class="nf-row is-danger" data-open="nf-turn-down"><span class="nf-tile-ic"><?= nav_icon('cancel') ?></span>Turn down</button>
        <?php elseif ($is_boss && in_array($stage['key'], ['confirmed', 'ready'], true)): ?>
            <button type="button" class="nf-row is-danger" data-open="nf-cancel"><span class="nf-tile-ic"><?= nav_icon('cancel') ?></span>Cancel order</button>
        <?php endif; ?>
    </div>
</dialog>

<?php if ($is_boss && in_array($stage['key'], ['confirmed', 'ready'], true)): ?>
<dialog class="nf-sheet" id="nf-cancel" aria-labelledby="nf-cancel-h">
    <div class="nf-grab"></div>
    <div class="nf-sheet-h"><h2 id="nf-cancel-h">Cancel order <?= e($num) ?>?</h2><button type="button" class="nf-icon-btn" data-close aria-label="Close"><?= nav_icon('close') ?></button></div>
    <?php if ($paid > 0): ?>
        <p class="nf-sheet-note"><?= planner_money($paid, $currency) ?> was paid on this order. Give it back first, then cancel.</p>
        <div class="nf-actions"><button type="button" class="nf-btn nf-btn-primary" data-close data-open="nf-refund"><?= nav_icon('refund') ?>Give money back</button></div>
    <?php else: ?>
        <form method="POST" action="<?= APP_URL ?>/orders/cancel/<?= $oid ?>" class="nf-form"><?= $csrf ?>
            <p class="nf-help">Its stock goes back on the shelf, its invoice <?= $invoice ? e($invoice['doc_number']) . ' ' : '' ?>is cancelled and the balance leaves Debts.<?= $order['customer_email'] ? ' ' . e(ucfirst($first)) . ' gets an email.' : '' ?></p>
            <label>Why <small>Optional</small><input type="text" name="reason" maxlength="300"></label>
            <div class="nf-two-btn">
                <button type="button" class="nf-btn nf-btn-ghost" data-close>Keep the order</button>
                <button class="nf-btn nf-btn-danger">Cancel order</button>
            </div>
        </form>
    <?php endif; ?>
</dialog>
<?php endif; ?>

<dialog class="nf-sheet nf-log-sheet" id="nf-log" aria-labelledby="nf-log-h">
    <div class="nf-grab"></div>
    <div class="nf-sheet-h"><h2 id="nf-log-h">Log</h2><button type="button" class="nf-icon-btn" data-close aria-label="Close"><?= nav_icon('close') ?></button></div>
    <?php if (!$events): ?>
        <p class="nf-sheet-note">Everything that happens to this order shows here.</p>
    <?php else: ?>
        <ol class="nf-log">
            <?php $last_day = null; foreach ($events as $ev):
                $day = substr((string)$ev['created_at'], 0, 10);
                if ($day !== $last_day): $last_day = $day; ?>
                    <li class="nf-log-day"><span><?= e(screen_date($day)) ?></span></li>
                <?php endif;
                $kind = (string)$ev['kind'];
                $side = in_array($kind, ['whatsapp', 'email'], true) ? 'is-out' : (in_array($kind, ['refund_details', 'signed', 'review'], true) ? 'is-in' : ''); ?>
                <li class="nf-log-ev <?= $side ?> is-<?= e($kind) ?>">
                    <span class="nf-log-ic" aria-hidden="true"><?= nav_icon($event_icons[$kind] ?? 'clock') ?></span>
                    <span class="nf-log-b">
                        <span class="nf-log-t"><?= e($ev['text']) ?></span>
                        <small><?= e(date('g:ia', strtotime($ev['created_at']))) ?><?= $ev['user_name'] ? ' · ' . e(planner_first_name($ev['user_name'])) : '' ?></small>
                    </span>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</dialog>
</div>
