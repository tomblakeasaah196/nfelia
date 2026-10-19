<?php
/**
 * Sales → Receipts. Renders inside views/layouts/app.php.
 *
 * Available from controller:
 *   $user, $business, $receipts, $summary,
 *   $search, $type, $sort, $page, $total_pages, $total_items
 */
$currency   = $business['currency_symbol'] ?? '₦';
$orders_here = 'receipts';

$month_value = (float)($summary['month_value'] ?? 0);
$month_count = (int)($summary['month_count'] ?? 0);
$keep    = ['q' => $search, 'type' => $type, 'sort' => $sort !== 'newest' ? $sort : ''];
$filters = ['' => 'All', 'invoice' => 'For invoices', 'standalone' => 'Paid on the spot'];
$counts  = ['invoice' => (int)($summary['invoice_count'] ?? 0), 'standalone' => (int)($summary['standalone_count'] ?? 0)];
$sorts   = ['newest' => 'Newest first', 'oldest' => 'Oldest first', 'amount_desc' => 'Biggest first', 'amount_asc' => 'Smallest first'];
?>
<div class="nf-page">
<?php require BASE_PATH . '/views/partials/orders_head.php'; ?>

<section class="nf-money" aria-label="Received this month">
    <p class="nf-money-k">Receipts this month</p>
    <p class="nf-money-v<?= $month_value > 0 ? ' is-in' : '' ?>"><?= planner_money($month_value, $currency) ?></p>
    <p class="nf-money-sub">
        <?php if ($month_count > 0): ?>
            <?= $month_count ?> receipt<?= $month_count === 1 ? '' : 's' ?> since the 1st. <?= (int)($summary['total_receipts'] ?? 0) ?> in all.
        <?php else: ?>
            No receipts yet this month.
        <?php endif; ?>
    </p>
</section>

<form class="nf-find" method="GET" action="<?= APP_URL ?>/receipts" role="search">
    <?php if ($type !== ''): ?><input type="hidden" name="type" value="<?= e($type) ?>"><?php endif; ?>
    <?php if ($sort !== 'newest'): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>
    <label class="nf-search"><?= nav_icon('search') ?>
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search by name or number" aria-label="Search receipts" enterkeyhint="search" autocomplete="off">
    </label>
</form>

<nav class="nf-chips" aria-label="Show">
    <?php foreach ($filters as $key => $label): ?>
        <a href="<?= e(screen_url('receipts', ['type' => $key] + $keep)) ?>" data-prefetch<?= $type === $key ? ' aria-current="true"' : '' ?>><?= $label ?><?php if (!empty($counts[$key])): ?> <small><?= $counts[$key] ?></small><?php endif; ?></a>
    <?php endforeach; ?>
</nav>

<?php if (!empty($receipts)): ?>
    <div class="nf-tools">
        <p><?= $total_items ?> receipt<?= $total_items === 1 ? '' : 's' ?><?= $search !== '' ? ' for “' . e($search) . '”' : '' ?></p>
        <form method="GET" action="<?= APP_URL ?>/receipts">
            <?php foreach (['q' => $search, 'type' => $type] as $k => $v): if ($v !== ''): ?><input type="hidden" name="<?= $k ?>" value="<?= e($v) ?>"><?php endif; endforeach; ?>
            <select class="nf-select" name="sort" aria-label="Order" onchange="this.form.submit()">
                <?php foreach ($sorts as $k => $label): ?>
                    <option value="<?= $k ?>"<?= $sort === $k ? ' selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <div class="nf-items">
        <?php foreach ($receipts as $rec): ?>
            <a class="nf-item" href="<?= APP_URL ?>/receipts/view/<?= (int)$rec['id'] ?>" data-prefetch>
                <span class="nf-item-main">
                    <b><?= e($rec['customer_name'] ?: 'Walk-in customer') ?></b>
                    <small><?= e($rec['doc_number']) ?> · <?= e(screen_date($rec['issue_date'])) ?></small>
                </span>
                <span class="nf-item-end">
                    <b class="is-in"><?= planner_money((float)$rec['amount'], $currency) ?></b>
                    <small><?= e(screen_method($rec['method'])) ?></small>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php $pager = ['path' => 'receipts', 'params' => $keep, 'page' => $page, 'pages' => $total_pages];
          require BASE_PATH . '/views/partials/pager.php'; ?>

<?php elseif ($search !== '' || $type !== ''): ?>
    <div class="nf-empty">
        <span class="nf-tile-ic"><?= nav_icon('search') ?></span>
        <?php if ($search !== ''): ?>
            <h2>No receipts match “<?= e($search) ?>”</h2>
            <p>Check the spelling, or search by the receipt number.</p>
        <?php else: ?>
            <h2><?= $type === 'invoice' ? 'No receipts for invoices yet' : 'No sales on the spot yet' ?></h2>
            <p><?= $type === 'invoice' ? 'Record a payment on an order and its receipt shows here.' : 'Receipts from before orders, for sales on the spot, show here. A sale on the spot is now a quick sale.' ?></p>
        <?php endif; ?>
        <a class="nf-link" href="<?= APP_URL ?>/receipts">Show all receipts</a>
    </div>
<?php else: ?>
    <div class="nf-empty">
        <span class="nf-tile-ic"><?= nav_icon('receipt') ?></span>
        <h2>No receipts yet</h2>
        <p>Every payment on an order makes its receipt, so the customer has proof and you see all the money that came in.</p>
        <a class="nf-btn nf-btn-primary" href="<?= APP_URL ?>/orders/quick"><?= nav_icon('bolt') ?>Quick sale</a>
    </div>
<?php endif; ?>
</div>
