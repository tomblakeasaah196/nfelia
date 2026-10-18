<?php
/**
 * Sales → Invoices. Renders inside views/layouts/app.php.
 *
 * Available from controller:
 *   $user, $business, $invoices, $summary,
 *   $search, $status, $sort, $page, $total_pages, $total_items
 */
$currency   = $business['currency_symbol'] ?? '₦';
$sales_here = 'invoices';

$owed     = (float)($summary['owed'] ?? 0);
$unpaid   = (int)($summary['unpaid_count'] ?? 0);
$overdue  = (int)($summary['overdue_count'] ?? 0);
$today    = planner_today();
$keep     = ['q' => $search, 'status' => $status, 'sort' => $sort !== 'newest' ? $sort : ''];
$filters  = ['' => 'All', 'unpaid' => 'Unpaid', 'overdue' => 'Overdue', 'paid' => 'Paid', 'draft' => 'Drafts'];
$counts   = ['unpaid' => $unpaid, 'overdue' => $overdue, 'draft' => (int)($summary['draft_count'] ?? 0)];
$sorts    = ['newest' => 'Newest first', 'oldest' => 'Oldest first', 'due_date' => 'By due date', 'total_desc' => 'Biggest first', 'balance_desc' => 'Most owed first'];
?>
<div class="nf-page">
<?php require BASE_PATH . '/views/partials/sales_head.php'; ?>

<section class="nf-money" aria-label="Owed to you">
    <p class="nf-money-k">Owed to you</p>
    <p class="nf-money-v<?= $owed > 0 ? ' is-owed' : '' ?>"><?= planner_money($owed, $currency) ?></p>
    <p class="nf-money-sub">
        <?php if ($unpaid > 0): ?>
            On <?= $unpaid ?> unpaid invoice<?= $unpaid === 1 ? '' : 's' ?><?php if ($overdue > 0): ?>,
            <a href="<?= e(screen_url('invoices', ['status' => 'overdue'])) ?>"><?= $overdue ?> overdue (<?= planner_money((float)($summary['overdue_value'] ?? 0), $currency) ?>)</a><?php endif; ?>.
        <?php else: ?>
            Nobody owes you on an invoice right now.
        <?php endif; ?>
    </p>
</section>

<form class="nf-find" method="GET" action="<?= APP_URL ?>/invoices" role="search">
    <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <?php if ($sort !== 'newest'): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>
    <label class="nf-search"><?= nav_icon('search') ?>
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search by name or number" aria-label="Search invoices" enterkeyhint="search" autocomplete="off">
    </label>
</form>

<nav class="nf-chips" aria-label="Show">
    <?php foreach ($filters as $key => $label): ?>
        <a href="<?= e(screen_url('invoices', ['status' => $key] + $keep)) ?>" data-prefetch<?= $status === $key ? ' aria-current="true"' : '' ?>><?= $label ?><?php if (!empty($counts[$key])): ?> <small><?= $counts[$key] ?></small><?php endif; ?></a>
    <?php endforeach; ?>
</nav>

<?php if (!empty($invoices)): ?>
    <div class="nf-tools">
        <p><?= $total_items ?> invoice<?= $total_items === 1 ? '' : 's' ?><?= $search !== '' ? ' for “' . e($search) . '”' : '' ?></p>
        <form method="GET" action="<?= APP_URL ?>/invoices">
            <?php foreach (['q' => $search, 'status' => $status] as $k => $v): if ($v !== ''): ?><input type="hidden" name="<?= $k ?>" value="<?= e($v) ?>"><?php endif; endforeach; ?>
            <select class="nf-select" name="sort" aria-label="Order" onchange="this.form.submit()">
                <?php foreach ($sorts as $k => $label): ?>
                    <option value="<?= $k ?>"<?= $sort === $k ? ' selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <div class="nf-items">
        <?php foreach ($invoices as $inv) require BASE_PATH . '/views/partials/invoice_row.php'; ?>
    </div>

    <?php $pager = ['path' => 'invoices', 'params' => $keep, 'page' => $page, 'pages' => $total_pages];
          require BASE_PATH . '/views/partials/pager.php'; ?>

<?php elseif ($search !== '' || $status !== ''): ?>
    <div class="nf-empty">
        <span class="nf-tile-ic"><?= nav_icon('search') ?></span>
        <?php if ($search !== ''): ?>
            <h2>No invoices match “<?= e($search) ?>”</h2>
            <p>Check the spelling, or search by the invoice number.</p>
        <?php else: ?>
            <h2><?= match ($status) {
                'unpaid'  => 'No unpaid invoices',
                'overdue' => 'Nothing overdue',
                'paid'    => 'No paid invoices yet',
                'draft'   => 'No drafts',
                default   => 'No invoices here',
            } ?></h2>
            <p><?= match ($status) {
                'unpaid'  => 'Every invoice you sent has been paid.',
                'overdue' => 'Every unpaid invoice is still within its due date.',
                'paid'    => 'Invoices show here once they are paid in full.',
                'draft'   => 'Invoices you save without sending wait here.',
                default   => 'No invoice has this status right now.',
            } ?></p>
        <?php endif; ?>
        <a class="nf-link" href="<?= APP_URL ?>/invoices">Show all invoices</a>
    </div>
<?php else: ?>
    <div class="nf-empty">
        <span class="nf-tile-ic"><?= nav_icon('invoice') ?></span>
        <h2>No invoices yet</h2>
        <p>When a customer buys now and pays later, an invoice keeps count of what they owe, and reminds you when it's due.</p>
        <a class="nf-btn nf-btn-primary" href="<?= APP_URL ?>/invoices/create"><?= nav_icon('plus') ?>Create an invoice</a>
    </div>
<?php endif; ?>
</div>
