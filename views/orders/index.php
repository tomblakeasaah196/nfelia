<?php
/**
 * Orders → Orders: every sale, from the shop or typed in. Renders inside
 * views/layouts/app.php.
 *
 * Available from controller:
 *   $user, $business, $orders, $counts, $search, $view, $page, $total_pages, $total_items
 */
$currency    = $business['currency_symbol'] ?? '₦';
$today       = planner_today();
$orders_here = 'orders';
$keep        = ['q' => $search, 'view' => $view];
$views       = ['' => 'All', 'confirm' => 'To confirm', 'progress' => 'In progress', 'balance' => 'Balance', 'done' => 'Done'];
$owed        = (float)($counts['balance_total'] ?? 0);
?>
<div class="nf-page">
<?php require BASE_PATH . '/views/partials/orders_head.php'; ?>

<?php if ((int)($counts['all'] ?? 0) > 0 || $search !== ''): ?>
    <?php if ($owed > 0 && $view === '' && $search === ''): ?>
        <a class="nf-owed-line" href="<?= e(screen_url('orders', ['view' => 'balance'])) ?>" data-prefetch>
            <span><b class="is-owed"><?= planner_money($owed, $currency) ?></b> balance to collect on <?= (int)$counts['balance'] ?> order<?= (int)$counts['balance'] === 1 ? '' : 's' ?></span>
            <?= nav_icon('chevron') ?>
        </a>
    <?php endif; ?>

    <form class="nf-find" method="GET" action="<?= APP_URL ?>/orders" role="search">
        <?php if ($view !== ''): ?><input type="hidden" name="view" value="<?= e($view) ?>"><?php endif; ?>
        <label class="nf-search"><?= nav_icon('search') ?>
            <input type="search" name="q" value="<?= e($search) ?>" placeholder="Name, phone or number" aria-label="Search orders" enterkeyhint="search" autocomplete="off">
        </label>
    </form>

    <nav class="nf-chips" aria-label="Show">
        <?php foreach ($views as $key => $label): $n = $key === '' ? 0 : (int)($counts[$key] ?? 0); ?>
            <a href="<?= e(screen_url('orders', ['view' => $key, 'q' => $search])) ?>" data-prefetch<?= $view === $key ? ' aria-current="true"' : '' ?>><?= $label ?><?php if ($n > 0 && $key !== 'done'): ?> <small><?= $n ?></small><?php endif; ?></a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>

<?php if (!empty($orders)): ?>
    <div class="nf-items">
        <?php foreach ($orders as $o) require BASE_PATH . '/views/partials/order_row.php'; ?>
    </div>
    <?php $pager = ['path' => 'orders', 'params' => $keep, 'page' => $page, 'pages' => $total_pages];
          require BASE_PATH . '/views/partials/pager.php'; ?>

<?php elseif ($search !== '' || $view !== ''): ?>
    <div class="nf-empty">
        <span class="nf-tile-ic"><?= nav_icon($search !== '' ? 'search' : 'orders') ?></span>
        <?php if ($search !== ''): ?>
            <h2>No orders match “<?= e($search) ?>”</h2>
            <p>Check the spelling, or search by phone number or order number.</p>
        <?php else: ?>
            <h2><?= match ($view) {
                'confirm'  => 'Nothing to confirm',
                'progress' => 'Nothing in progress',
                'balance'  => 'No balances to collect',
                default    => 'Nothing done yet',
            } ?></h2>
            <p><?= match ($view) {
                'confirm'  => 'New orders from your shop wait here until you confirm them.',
                'progress' => 'Confirmed orders show here until they are delivered or collected.',
                'balance'  => 'Every order is paid in full.',
                default    => 'Orders show here once they are delivered or collected.',
            } ?></p>
        <?php endif; ?>
        <a class="nf-link" href="<?= APP_URL ?>/orders">Show all orders</a>
    </div>

<?php else: ?>
    <div class="nf-empty">
        <span class="nf-tile-ic"><?= nav_icon('orders') ?></span>
        <h2>No orders yet</h2>
        <p>Every sale starts here: one order holds its invoice, payments, receipts and delivery. Orders from your shop come in on their own.</p>
        <div class="nf-empty-do">
            <a class="nf-btn nf-btn-primary" href="<?= APP_URL ?>/orders/create"><?= nav_icon('plus') ?>New order</a>
            <a class="nf-btn nf-btn-ghost" href="<?= APP_URL ?>/orders/quick"><?= nav_icon('bolt') ?>Quick sale</a>
        </div>
    </div>
<?php endif; ?>
</div>
