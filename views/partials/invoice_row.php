<?php
/**
 * One invoice in a list: who, which and when on the left; the amount and how
 * it stands on the right. Used by Sales → Invoices and Home.
 * Expects $inv (id, doc_number, customer_name, issue_date, due_date, total,
 * balance_due, status), $currency and $today.
 */
[$inv_tag, $inv_tag_cls] = screen_invoice_tag($inv['status']);
if ($inv['status'] === 'partially_paid') {
    $inv_tag = planner_money((float)$inv['balance_due'], $currency) . ' left';
} elseif ($inv['status'] === 'overdue') {
    $inv_tag = planner_money((float)$inv['balance_due'], $currency) . ' overdue';
} elseif ($inv['status'] === 'sent' && !empty($inv['due_date'])) {
    $inv_tag = $inv['due_date'] === $today ? 'Due today' : 'Due ' . screen_date($inv['due_date']);
}
?>
<a class="nf-item<?= $inv['status'] === 'cancelled' ? ' is-off' : '' ?>" href="<?= APP_URL ?>/invoices/view/<?= (int)$inv['id'] ?>" data-prefetch>
    <span class="nf-item-main">
        <b><?= e($inv['customer_name'] ?: 'No client') ?></b>
        <small><?= e($inv['doc_number']) ?> · <?= e(screen_date($inv['issue_date'])) ?></small>
    </span>
    <span class="nf-item-end">
        <b><?= planner_money((float)$inv['total'], $currency) ?></b>
        <span class="nf-tag <?= $inv_tag_cls ?>"><?= e($inv_tag) ?></span>
    </span>
</a>
