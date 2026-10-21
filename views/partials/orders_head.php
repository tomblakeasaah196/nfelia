<?php
/**
 * The top of the Orders tab: the list's own title, one primary button, and
 * the switches Orders · Quotes · Invoices · Receipts, each at its own address
 * so Back and links keep working. Rendered by views/orders/index.php,
 * views/quotations/index.php, views/invoices/index.php and
 * views/receipts/index.php, which set $orders_here to their own name.
 */
$orders_lists = [
    'orders'     => ['Orders',   'orders/create',     'New order'],
    'quotations' => ['Quotes',   'quotations/create', 'New quote'],
    'invoices'   => ['Invoices', 'orders/create',     'New order'],
    'receipts'   => ['Receipts', 'orders/create',     'New order'],
];
[$orders_title, $orders_new_url, $orders_new_label] = $orders_lists[$orders_here];
?>
<div class="nf-head">
    <h1 class="nf-large-title"><?= $orders_title ?></h1>
    <a class="nf-btn nf-btn-primary" href="<?= APP_URL ?>/<?= $orders_new_url ?>"><?= nav_icon('plus') ?><?= $orders_new_label ?></a>
</div>
<nav class="nf-segs" aria-label="Orders and papers">
    <?php foreach ($orders_lists as $seg => [$seg_label]): ?>
        <a href="<?= APP_URL ?>/<?= $seg ?>" data-prefetch<?= $orders_here === $seg ? ' aria-current="page"' : '' ?>><?= $seg_label ?></a>
    <?php endforeach; ?>
</nav>
