<?php
/** Referrals (list): $rows, $stats. */
?>
<div class="ops-head">
    <div>
        <h1>Referrals</h1>
        <p>Businesses that brought other businesses. <?= (int)$stats['total'] ?> in all, <?= (int)$stats['converted'] + (int)$stats['rewarded'] ?> became paying, <?= (int)$stats['rewarded'] ?> rewarded (₦<?= number_format(((float)$stats['discount']) / 100) ?> in discounts).</p>
    </div>
</div>
<div class="ops-table-wrap">
    <table class="ops-table is-stacked is-compact">
        <thead><tr><th scope="col">Brought by</th><th scope="col">Who</th><th scope="col">Where it is</th><th scope="col">When</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= $r['referrer_id'] ? '<a class="cell-main" href="' . e(ops_url('clients/' . (int)$r['referrer_id'])) . '">' . e($r['referrer_name']) . '</a>' : '—' ?></td>
                <td data-label="Who"><?= $r['referred_id'] ? '<a href="' . e(ops_url('clients/' . (int)$r['referred_id'])) . '">' . e($r['referred_name']) . '</a>' : e($r['referred_email'] ?? '—') ?></td>
                <td data-label="Where it is"><span class="ops-chip <?= $r['status'] === 'rewarded' ? 'ops-chip-ok' : ($r['status'] === 'converted' ? 'ops-chip-accent' : '') ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                <td data-label="When"><?= e(date('j M Y', strtotime($r['created_at']))) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="4"><div class="ops-empty"><p>No referrals yet.</p></div></td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
