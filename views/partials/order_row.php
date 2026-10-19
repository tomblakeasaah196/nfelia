<?php
/**
 * One order in a list: who, which and when on the left; the amount and where
 * it stands on the right. Used by Orders and Home.
 * Expects $o (a row from order_rows()), $currency and $today.
 */
$o_stage = order_stage($o, $o['delivery_status'] ? ['status' => $o['delivery_status']] : null);
$o_pay   = $o['inv_id'] ? order_pay_tag(['total' => $o['inv_total'], 'amount_paid' => $o['inv_paid'], 'balance_due' => $o['inv_balance'], 'status' => $o['inv_status']]) : null;
$o_icon  = ['cart_pop' => 'orders', 'whatsapp' => 'whatsapp', 'manual' => 'hand', 'quick' => 'bolt', 'quote' => 'quote'][$o['channel']] ?? 'orders';
$o_where = ['cart_pop' => 'Shop', 'whatsapp' => 'WhatsApp', 'manual' => 'Typed in', 'quick' => 'Quick sale', 'quote' => 'From a quote'][$o['channel']] ?? '';
$o_off   = in_array($o_stage['key'], ['cancelled', 'turned_down'], true);
// One tag: what needs attention (new, money owed or paid over), else the money is fine
[$o_tag, $o_tone] = match (true) {
    $o_stage['key'] === 'new' || $o_off => [$o_stage['words'], $o_stage['tone']],
    $o_pay && $o_pay['balance'] > 0     => ['Balance ' . planner_money($o_pay['balance'], $currency), 'is-owed'],
    $o_pay && $o_pay['overpaid'] > 0    => ['Paid more', 'is-warn'],
    default                             => ['Paid', 'is-in'],
};
// Under the name: the number, and where it is (or when it came in, while new)
$o_where_now = in_array($o_stage['key'], ['new', 'cancelled', 'turned_down'], true) ? screen_date($o['created_at']) : $o_stage['words'];
?>
<a class="nf-item nf-order-row<?= $o_off ? ' is-off' : '' ?>" href="<?= APP_URL ?>/orders/view/<?= (int)$o['id'] ?>" data-prefetch>
    <span class="nf-face" aria-hidden="true"><?= e(planner_initials($o['customer_name'])) ?></span>
    <span class="nf-orow">
        <span class="nf-orow-1"><b><?= e($o['customer_name'] ?: 'Walk-in customer') ?></b><b class="nf-orow-amt"><?= planner_money((float)$o['total'], $currency) ?></b></span>
        <span class="nf-orow-2">
            <small><span class="nf-order-ch" title="<?= e($o_where) ?>"><?= nav_icon($o_icon) ?><span class="sr-only"><?= e($o_where) ?>, </span></span><span class="nf-orow-num"><?= e($o['order_number']) ?></span><span class="nf-orow-now"> · <?= e($o_where_now) ?></span></small>
            <span class="nf-tag <?= $o_tone ?>"><?= e($o_tag) ?></span>
        </span>
    </span>
</a>
