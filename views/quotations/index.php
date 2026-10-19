<?php
/**
 * Sales → Quotes. Renders inside views/layouts/app.php.
 *
 * Available from controller:
 *   $user, $business, $quotations, $summary,
 *   $search, $status, $sort, $page, $total_pages, $total_items
 */
$currency   = $business['currency_symbol'] ?? '₦';
$orders_here = 'quotations';

$waiting  = (int)($summary['waiting_count'] ?? 0);
$accepted = (int)($summary['accepted_count'] ?? 0);
$today    = planner_today();
$keep     = ['q' => $search, 'status' => $status, 'sort' => $sort !== 'newest' ? $sort : ''];
$filters  = ['' => 'All', 'waiting' => 'Waiting', 'accepted' => 'Accepted', 'draft' => 'Drafts', 'converted' => 'Ordered'];
$counts   = ['waiting' => $waiting, 'accepted' => $accepted, 'draft' => (int)($summary['draft_count'] ?? 0)];
$sorts    = ['newest' => 'Newest first', 'oldest' => 'Oldest first', 'total_desc' => 'Biggest first', 'total_asc' => 'Smallest first'];
?>
<div class="nf-page">
<?php require BASE_PATH . '/views/partials/orders_head.php'; ?>

<?php if (!empty($open_requests)): ?>
<a class="nf-card" href="<?= APP_URL ?>/quotations/requests" style="display:flex;align-items:center;gap:12px;text-decoration:none;color:inherit">
    <span class="nf-tile-ic"><?= nav_icon('chat') ?></span>
    <span style="flex:1"><b><?= $open_requests ?> quote request<?= $open_requests === 1 ? '' : 's' ?> from your shop</b><br>
        <small style="color:var(--ink-2)">People waiting for a price.</small></span>
    <?= nav_icon('chevron') ?>
</a>
<?php endif; ?>

<section class="nf-money" aria-label="Waiting on an answer">
    <p class="nf-money-k">Waiting on an answer</p>
    <p class="nf-money-v"><?= planner_money((float)($summary['waiting_value'] ?? 0), $currency) ?></p>
    <p class="nf-money-sub">
        <?php if ($waiting > 0): ?>
            On <?= $waiting ?> quote<?= $waiting === 1 ? '' : 's' ?> you sent.
        <?php else: ?>
            No quote is waiting on a customer.
        <?php endif; ?>
        <?php if ($accepted > 0): ?>
            <a href="<?= e(screen_url('quotations', ['status' => 'accepted'])) ?>"><?= $accepted ?> accepted, ready to invoice</a>.
        <?php endif; ?>
    </p>
</section>

<form class="nf-find" method="GET" action="<?= APP_URL ?>/quotations" role="search">
    <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <?php if ($sort !== 'newest'): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>
    <label class="nf-search"><?= nav_icon('search') ?>
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search by name or number" aria-label="Search quotes" enterkeyhint="search" autocomplete="off">
    </label>
</form>

<nav class="nf-chips" aria-label="Show">
    <?php foreach ($filters as $key => $label): ?>
        <a href="<?= e(screen_url('quotations', ['status' => $key] + $keep)) ?>" data-prefetch<?= $status === $key ? ' aria-current="true"' : '' ?>><?= $label ?><?php if (!empty($counts[$key])): ?> <small><?= $counts[$key] ?></small><?php endif; ?></a>
    <?php endforeach; ?>
</nav>

<?php if (!empty($quotations)): ?>
    <div class="nf-tools">
        <p><?= $total_items ?> quote<?= $total_items === 1 ? '' : 's' ?><?= $search !== '' ? ' for “' . e($search) . '”' : '' ?></p>
        <form method="GET" action="<?= APP_URL ?>/quotations">
            <?php foreach (['q' => $search, 'status' => $status] as $k => $v): if ($v !== ''): ?><input type="hidden" name="<?= $k ?>" value="<?= e($v) ?>"><?php endif; endforeach; ?>
            <select class="nf-select" name="sort" aria-label="Order" onchange="this.form.submit()">
                <?php foreach ($sorts as $k => $label): ?>
                    <option value="<?= $k ?>"<?= $sort === $k ? ' selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <div class="nf-items">
        <?php foreach ($quotations as $q):
            [$tag, $tag_cls] = screen_quote_tag($q['status'], (bool)($q['has_order'] ?? true));
            // A quote still out with the customer says how long it stands.
            if (in_array($q['status'], ['sent', 'in_negotiation', 'pending_signature'], true) && $q['expiry_date']) {
                if ($q['expiry_date'] < $today) {
                    [$tag, $tag_cls] = ['Past its date', 'is-warn'];
                } elseif ($q['status'] === 'sent') {
                    $tag = $q['expiry_date'] === $today ? 'Ends today' : 'Until ' . screen_date($q['expiry_date']);
                }
            }
        ?>
            <a class="nf-item<?= in_array($q['status'], ['declined', 'expired'], true) ? ' is-off' : '' ?>" href="<?= APP_URL ?>/quotations/view/<?= (int)$q['id'] ?>" data-prefetch>
                <span class="nf-item-main">
                    <b><?= e($q['customer_name'] ?: 'No client') ?></b>
                    <small><?= e($q['doc_number']) ?> · <?= e(screen_date($q['issue_date'])) ?></small>
                </span>
                <span class="nf-item-end">
                    <b><?= planner_money((float)$q['total'], $currency) ?></b>
                    <span class="nf-tag <?= $tag_cls ?>"><?= e($tag) ?></span>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php $pager = ['path' => 'quotations', 'params' => $keep, 'page' => $page, 'pages' => $total_pages];
          require BASE_PATH . '/views/partials/pager.php'; ?>

<?php elseif ($search !== '' || $status !== ''): ?>
    <div class="nf-empty">
        <span class="nf-tile-ic"><?= nav_icon('search') ?></span>
        <?php if ($search !== ''): ?>
            <h2>No quotes match “<?= e($search) ?>”</h2>
            <p>Check the spelling, or search by the quote number.</p>
        <?php else: ?>
            <h2><?= match ($status) {
                'waiting'   => 'No quote is waiting',
                'accepted'  => 'No accepted quotes',
                'draft'     => 'No drafts',
                'converted' => 'No quote has become an order yet',
                default     => 'No quotes here',
            } ?></h2>
            <p><?= match ($status) {
                'waiting'   => 'Quotes you send wait here until the customer answers.',
                'accepted'  => 'When a customer accepts a quote, it becomes an order. One that didn\'t waits here: tap Make it an order.',
                'draft'     => 'Quotes you save without sending wait here.',
                'converted' => 'Accepted quotes become orders, and show here.',
                default     => 'No quote has this status right now.',
            } ?></p>
        <?php endif; ?>
        <a class="nf-link" href="<?= APP_URL ?>/quotations">Show all quotes</a>
    </div>
<?php else: ?>
    <div class="nf-empty">
        <span class="nf-tile-ic"><?= nav_icon('quote') ?></span>
        <h2>No quotes yet</h2>
        <p>Send a price before the job: when the customer says yes, it becomes an invoice in one tap.</p>
        <a class="nf-btn nf-btn-primary" href="<?= APP_URL ?>/quotations/create"><?= nav_icon('plus') ?>Create a quote</a>
    </div>
<?php endif; ?>
</div>
