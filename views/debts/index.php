<?php
/**
 * Debts: who owes me, and who I owe. Every order with a balance is here
 * from the moment the balance exists (docs/ORDERS_HUB.md), next to debts
 * typed in by hand and unpaid supplier expenses.
 * Renders inside views/layouts/app.php.
 *
 * Available from controller:
 *   $user, $business, $debts, $dashboard,
 *   $tab (receivable = owed to me, payable = I owe), $filter, $search, $sort, $page, $total_pages, $total_items
 */
$currency = $business['currency_symbol'] ?? '₦';
$today    = planner_today();
$mine     = $tab === 'receivable';
$keep     = ['tab' => $tab, 'q' => $search, 'filter' => $filter, 'sort' => $sort !== 'newest' ? $sort : ''];
$owed_me  = (float)$dashboard['receivable_balance'];
$i_owe    = (float)$dashboard['payable_balance'];
$late_n   = (int)$dashboard[$mine ? 'receivable_overdue_count' : 'payable_overdue_count'];
$late_v   = (float)$dashboard[$mine ? 'receivable_overdue' : 'payable_overdue'];
$filters  = ['' => 'Open', 'overdue' => 'Late', 'cleared' => 'Paid off', 'written_off' => 'Written off'];
$sorts    = ['newest' => 'Newest first', 'due_soonest' => 'Due soonest', 'highest_balance' => 'Most owed first', 'oldest' => 'Oldest first'];
?>
<div class="nf-page">
<div class="nf-head">
    <h1 class="nf-large-title">Debts</h1>
    <a class="nf-btn nf-btn-primary" href="<?= APP_URL ?>/debts/create?type=<?= e($tab) ?>"><?= nav_icon('plus') ?>Add a debt</a>
</div>

<nav class="nf-segs" aria-label="Whose debts">
    <a href="<?= APP_URL ?>/debts?tab=receivable" data-prefetch<?= $mine ? ' aria-current="page"' : '' ?>>Owed to me</a>
    <a href="<?= APP_URL ?>/debts?tab=payable" data-prefetch<?= !$mine ? ' aria-current="page"' : '' ?>>I owe</a>
</nav>

<section class="nf-money" aria-label="<?= $mine ? 'Owed to me' : 'I owe' ?>">
    <p class="nf-money-k"><?= $mine ? 'Owed to me' : 'I owe' ?> <?= nf_info($mine ? 'Owed to me' : 'I owe', $mine
        ? 'Every order with a balance shows here from the moment the balance exists, with debts you add yourself. Paying one off records the payment on its order, with a receipt.'
        : 'Unpaid supplier expenses show here, with debts you add yourself.') ?></p>
    <p class="nf-money-v<?= ($mine ? $owed_me : $i_owe) > 0 ? ($mine ? ' is-owed' : '') : '' ?>"><?= planner_money($mine ? $owed_me : $i_owe, $currency) ?></p>
    <p class="nf-money-sub">
        <?php $open_n = (int)$dashboard[$mine ? 'receivable_count' : 'payable_count']; ?>
        <?php if ($open_n > 0): ?>
            <?= $open_n ?> open<?php if ($late_n > 0): ?>, <a href="<?= e(screen_url('debts', ['tab' => $tab, 'filter' => 'overdue'])) ?>"><?= $late_n ?> late (<?= planner_money($late_v, $currency) ?>)</a><?php endif; ?>.
            <?= $mine ? 'You owe ' . planner_money($i_owe, $currency) . '.' : 'Owed to you: ' . planner_money($owed_me, $currency) . '.' ?>
        <?php else: ?>
            <?= $mine ? 'Nobody owes you right now.' : 'You owe nobody right now.' ?>
        <?php endif; ?>
    </p>
</section>

<form class="nf-find" method="GET" action="<?= APP_URL ?>/debts" role="search">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <?php if ($filter !== ''): ?><input type="hidden" name="filter" value="<?= e($filter) ?>"><?php endif; ?>
    <label class="nf-search"><?= nav_icon('search') ?>
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="<?= $mine ? 'Name, order or note' : 'Supplier or note' ?>" aria-label="Search debts" enterkeyhint="search" autocomplete="off">
    </label>
</form>

<nav class="nf-chips" aria-label="Show">
    <?php foreach ($filters as $key => $label): ?>
        <a href="<?= e(screen_url('debts', ['filter' => $key] + $keep)) ?>" data-prefetch<?= $filter === $key ? ' aria-current="true"' : '' ?>><?= $label ?><?php if ($key === 'overdue' && $late_n > 0): ?> <small><?= $late_n ?></small><?php endif; ?></a>
    <?php endforeach; ?>
</nav>

<?php if (!empty($debts)): ?>
    <div class="nf-tools">
        <p><?= $total_items ?> <?= $total_items === 1 ? 'debt' : 'debts' ?><?= $search !== '' ? ' for “' . e($search) . '”' : '' ?></p>
        <form method="GET" action="<?= APP_URL ?>/debts">
            <?php foreach (['tab' => $tab, 'q' => $search, 'filter' => $filter] as $k => $v): if ($v !== ''): ?><input type="hidden" name="<?= $k ?>" value="<?= e($v) ?>"><?php endif; endforeach; ?>
            <select class="nf-select" name="sort" aria-label="Order" onchange="this.form.submit()">
                <?php foreach ($sorts as $k => $label): ?>
                    <option value="<?= $k ?>"<?= $sort === $k ? ' selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <div class="nf-items">
        <?php foreach ($debts as $d):
            $open   = in_array($d['status'], ['active', 'settling'], true);
            $late   = $open && $d['due_date'] && $d['due_date'] < $today;
            $who    = $d['party_name'] ?: ($d['counterparty_name'] ?: 'Someone');
            $what   = !empty($d['order_number']) ? (string)$d['order_number'] : (string)$d['description'];
            $when   = $d['due_date'] ? ($late ? planner_days_between($d['due_date'], $today) . ' day' . (planner_days_between($d['due_date'], $today) === 1 ? '' : 's') . ' late' : 'by ' . planner_fmt_day_inline($d['due_date'], $today)) : '';
            [$tag, $tone] = match (true) {
                $d['status'] === 'cleared'     => ['Paid off', 'is-in'],
                $d['status'] === 'written_off' => ['Written off', 'is-line'],
                $late                          => ['Late', 'is-owed'],
                (float)$d['amount_settled'] > 0 => ['Part paid', 'is-warn'],
                default                        => ['Not paid', ''],
            };
            $url = !empty($d['order_ref_id']) ? 'orders/view/' . (int)$d['order_ref_id'] . '#money' : 'debts/view/' . (int)$d['id']; ?>
            <a class="nf-item<?= $open ? '' : ' is-off' ?>" href="<?= APP_URL ?>/<?= $url ?>" data-prefetch>
                <span class="nf-face<?= $late ? ' is-owed' : '' ?>" aria-hidden="true"><?= e(planner_initials($who)) ?></span>
                <span class="nf-item-main">
                    <b><?= e($who) ?><?= !empty($d['party_deleted']) ? ' (deleted)' : '' ?></b>
                    <small class="<?= $late ? 'is-owed' : '' ?>"><?= e(implode(' · ', array_filter([$what, $when]))) ?></small>
                </span>
                <span class="nf-item-end">
                    <b class="<?= $open && $mine ? 'is-owed' : '' ?>"><?= planner_money($open ? (float)$d['balance'] : (float)$d['total_amount'], $currency) ?></b>
                    <span class="nf-tag <?= $tone ?>"><?= e($tag) ?></span>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
    <?php $pager = ['path' => 'debts', 'params' => $keep, 'page' => $page, 'pages' => $total_pages];
          require BASE_PATH . '/views/partials/pager.php'; ?>

<?php else: ?>
    <div class="nf-empty">
        <span class="nf-tile-ic"><?= nav_icon($search !== '' ? 'search' : 'debt') ?></span>
        <?php if ($search !== '' || $filter !== ''): ?>
            <h2>Nothing here</h2>
            <p><?= $search !== '' ? 'No debt matches “' . e($search) . '”.' : 'No debts like that right now.' ?></p>
            <a class="nf-link" href="<?= APP_URL ?>/debts?tab=<?= e($tab) ?>">Show open debts</a>
        <?php elseif ($mine): ?>
            <h2>Nobody owes you</h2>
            <p>When a customer pays part, or will pay later, their balance shows here straight away, with a reminder on the day it is due.</p>
            <a class="nf-btn nf-btn-primary" href="<?= APP_URL ?>/debts/create?type=receivable"><?= nav_icon('plus') ?>Add a debt</a>
        <?php else: ?>
            <h2>You owe nobody</h2>
            <p>Unpaid supplier expenses show here, and anything else you add.</p>
            <a class="nf-btn nf-btn-primary" href="<?= APP_URL ?>/debts/create?type=payable"><?= nav_icon('plus') ?>Add a debt</a>
        <?php endif; ?>
    </div>
<?php endif; ?>
</div>
