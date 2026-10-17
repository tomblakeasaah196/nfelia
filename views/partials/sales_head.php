<?php
/**
 * The top of the Sales tab: one title, three lists (Invoices, Receipts,
 * Quotes), each at its own address so Back and links keep working.
 * Rendered by views/invoices/index.php, views/receipts/index.php and
 * views/quotations/index.php, which set $sales_here to their own name.
 */
$sales_new = [
    'invoices'   => ['invoices/create', 'Create invoice'],
    'receipts'   => ['receipts/create', 'Create receipt'],
    'quotations' => ['quotations/create', 'Create quote'],
][$sales_here];
?>
<div class="nf-head">
    <h1 class="nf-large-title">Sales</h1>
    <a class="nf-btn nf-btn-primary" href="<?= APP_URL ?>/<?= $sales_new[0] ?>"><?= nav_icon('plus') ?><?= $sales_new[1] ?></a>
</div>
<nav class="nf-segs" aria-label="Sales">
    <?php foreach (['invoices' => 'Invoices', 'receipts' => 'Receipts', 'quotations' => 'Quotes'] as $seg => $seg_label): ?>
        <a href="<?= APP_URL ?>/<?= $seg ?>" data-prefetch<?= $sales_here === $seg ? ' aria-current="page"' : '' ?>><?= $seg_label ?></a>
    <?php endforeach; ?>
</nav>
