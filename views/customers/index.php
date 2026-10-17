<?php
/**
 * Clients. Renders inside views/layouts/app.php.
 *
 * Available from controller:
 *   $user, $business, $customers, $summary,
 *   $search, $filter, $sort, $page, $total_pages, $total_items
 */
$currency = $business['currency_symbol'] ?? '₦';
$owing    = (int)($summary['owing_count'] ?? 0);
$owed     = (float)($summary['total_outstanding'] ?? 0);
$keep     = ['q' => $search, 'filter' => $filter, 'sort' => $sort !== 'name' ? $sort : ''];
$filters  = ['' => 'Active', 'owing' => 'Owing', 'clear' => 'Paid up', 'inactive' => 'Inactive', 'all' => 'All'];
$sorts    = ['name' => 'A to Z', 'newest' => 'Newest first', 'oldest' => 'Oldest first', 'highest_owing' => 'Owe the most',
             'highest_invoiced' => 'Bought the most', 'highest_received' => 'Paid the most'];
?>
<div class="nf-page">
<div class="nf-head">
    <h1 class="nf-large-title">Clients</h1>
    <a class="nf-btn nf-btn-primary" href="<?= APP_URL ?>/customers/create"><?= nav_icon('plus') ?>Add client</a>
</div>

<section class="nf-money" aria-label="Clients owe you">
    <p class="nf-money-k">Clients owe you</p>
    <p class="nf-money-v<?= $owed > 0 ? ' is-owed' : '' ?>"><?= planner_money(max(0, $owed), $currency) ?></p>
    <p class="nf-money-sub">
        <?php if ($owing > 0): ?>
            <a href="<?= e(screen_url('customers', ['filter' => 'owing', 'sort' => 'highest_owing'])) ?>"><?= $owing ?> of your <?= (int)($summary['active_count'] ?? 0) ?> clients owe something</a>.
        <?php else: ?>
            Every client is paid up.
        <?php endif; ?>
    </p>
</section>

<form class="nf-find" method="GET" action="<?= APP_URL ?>/customers" role="search">
    <?php if ($filter !== ''): ?><input type="hidden" name="filter" value="<?= e($filter) ?>"><?php endif; ?>
    <?php if ($sort !== 'name'): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>
    <label class="nf-search"><?= nav_icon('search') ?>
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search by name, phone or email" aria-label="Search clients" enterkeyhint="search" autocomplete="off">
    </label>
</form>

<nav class="nf-chips" aria-label="Show">
    <?php foreach ($filters as $key => $label): ?>
        <a href="<?= e(screen_url('customers', ['filter' => $key] + $keep)) ?>" data-prefetch<?= $filter === $key ? ' aria-current="true"' : '' ?>><?= $label ?><?php if ($key === 'owing' && $owing > 0): ?> <small><?= $owing ?></small><?php endif; ?></a>
    <?php endforeach; ?>
</nav>

<?php if (!empty($customers)): ?>
    <div class="nf-tools">
        <p><?= (int)$total_items ?> client<?= (int)$total_items === 1 ? '' : 's' ?><?= $search !== '' ? ' for “' . e($search) . '”' : '' ?></p>
        <form method="GET" action="<?= APP_URL ?>/customers">
            <?php foreach (['q' => $search, 'filter' => $filter] as $k => $v): if ($v !== ''): ?><input type="hidden" name="<?= $k ?>" value="<?= e($v) ?>"><?php endif; endforeach; ?>
            <select class="nf-select" name="sort" aria-label="Order" onchange="this.form.submit()">
                <?php foreach ($sorts as $k => $label): ?>
                    <option value="<?= $k ?>"<?= $sort === $k ? ' selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <div class="nf-items">
        <?php foreach ($customers as $c):
            $owes    = (float)$c['outstanding_balance'];
            $overdue = (int)($c['overdue_invoices'] ?? 0) > 0;
            $line    = implode(' · ', array_filter([$c['business_name'] ?? '', $c['phone'] ?: ($c['email'] ?? '')]));
        ?>
            <a class="nf-item<?= !$c['is_active'] ? ' is-off' : '' ?>" href="<?= APP_URL ?>/customers/view/<?= (int)$c['id'] ?>" data-prefetch>
                <span class="nf-face" aria-hidden="true"><?= e(planner_initials($c['full_name'])) ?></span>
                <span class="nf-item-main">
                    <b><?= e($c['full_name']) ?></b>
                    <small><?= $line !== '' ? e($line) : 'No phone or email yet' ?></small>
                </span>
                <span class="nf-item-end">
                    <?php if (!$c['is_active']): ?>
                        <span class="nf-tag is-line">Inactive</span>
                    <?php elseif ($owes > 0): ?>
                        <b class="is-owed"><?= planner_money($owes, $currency) ?></b>
                        <?php if ($overdue): ?><span class="nf-tag is-owed">Overdue</span><?php else: ?><small>owes you</small><?php endif; ?>
                    <?php else: ?>
                        <span class="nf-tag is-in">Paid up</span>
                    <?php endif; ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php $pager = ['path' => 'customers', 'params' => $keep, 'page' => $page, 'pages' => $total_pages];
          require BASE_PATH . '/views/partials/pager.php'; ?>

<?php elseif ($search !== '' || $filter !== ''): ?>
    <div class="nf-empty">
        <span class="nf-tile-ic"><?= nav_icon('search') ?></span>
        <?php if ($search !== ''): ?>
            <h2>No clients match “<?= e($search) ?>”</h2>
            <p>Check the spelling, or search by phone number.</p>
        <?php else: ?>
            <h2><?= match ($filter) {
                'owing'    => 'Nobody owes you',
                'clear'    => 'No paid-up clients yet',
                'inactive' => 'No inactive clients',
                default    => 'No clients here',
            } ?></h2>
            <p><?= match ($filter) {
                'owing'    => 'Every client has paid what they owe.',
                'clear'    => 'Clients show here once they have paid everything they owe.',
                'inactive' => 'Clients you mark inactive wait here, out of your way.',
                default    => 'No client matches this filter.',
            } ?></p>
        <?php endif; ?>
        <a class="nf-link" href="<?= APP_URL ?>/customers">Show active clients</a>
    </div>
<?php else: ?>
    <div class="nf-empty">
        <span class="nf-tile-ic"><?= nav_icon('clients') ?></span>
        <h2>No clients yet</h2>
        <p>Add the people you sell to. Clients are also added for you when you make a quote or an invoice.</p>
        <a class="nf-btn nf-btn-primary" href="<?= APP_URL ?>/customers/create"><?= nav_icon('plus') ?>Add a client</a>
    </div>
<?php endif; ?>
</div>
