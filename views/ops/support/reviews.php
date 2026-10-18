<?php
/** Shoppers' reviews (feed): $rows, $show. Hiding never edits what a shopper wrote. */
$tabs = ['all' => 'All', 'low' => '1 or 2 stars', 'hidden' => 'Hidden'];
?>
<div class="ops-head">
    <div>
        <h1>Reviews</h1>
        <p>What shoppers wrote about shops after an order. Hide one that breaks the rules (abuse, someone's phone number, spam); nobody can edit what a shopper wrote.</p>
    </div>
</div>
<nav class="ops-tabs" aria-label="Show">
    <?php foreach ($tabs as $k => $label): ?><a href="<?= e(ops_url('reviews' . ($k === 'all' ? '' : '?show=' . $k))) ?>"<?= $show === $k ? ' aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
</nav>
<?php if (!$rows): ?>
    <div class="ops-card ops-empty"><h3>No reviews here</h3></div>
<?php else: ?>
    <ul class="ops-feed">
        <?php foreach ($rows as $r): ?>
            <li>
                <span class="ops-chip <?= (int)$r['rating'] <= 2 ? 'ops-chip-bad' : 'ops-chip-ok' ?>"><?= (int)$r['rating'] ?>★</span>
                <div class="what"><?= $r['comment'] ? nl2br(e($r['comment'])) : '<span class="muted">No comment</span>' ?>
                    <div class="meta"><?= e($r['customer_name'] ?: 'A shopper') ?> on <a href="<?= e(ops_url('clients/' . (int)$r['business_id'])) ?>"><?= e($r['business_name']) ?></a> · order <?= e($r['order_number']) ?>
                        <?= $r['status'] === 'hidden' ? ' · <b>hidden</b>' : '' ?></div></div>
                <form method="post" action="<?= e(ops_url('reviews/' . (int)$r['id'])) ?>"<?= $r['status'] === 'hidden' ? '' : ' data-ops-confirm="Hide this review from the shop?"' ?>>
                    <?= ops_csrf_field() ?><input type="hidden" name="hide" value="<?= $r['status'] === 'hidden' ? '0' : '1' ?>">
                    <button class="ops-btn <?= $r['status'] === 'hidden' ? 'ops-btn-secondary' : 'ops-btn-ghost' ?> ops-btn-sm" type="submit"><?= $r['status'] === 'hidden' ? 'Show again' : 'Hide' ?></button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
