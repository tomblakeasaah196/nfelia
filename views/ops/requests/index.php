<?php
/** Document requests: $waiting (new, then asked), $done (decided lately), $roles. */
$who = fn(array $r) => $r['organisation'] ? $r['organisation'] . ' (' . $r['name'] . ')' : $r['name'];
$chip = fn(string $s) => match ($s) {
    'new' => '<span class="ops-chip ops-chip-warn">New: ask the client</span>', 'asked' => '<span class="ops-chip ops-chip-accent">Waiting for the client</span>',
    'approved' => '<span class="ops-chip ops-chip-ok">Shared</span>', 'declined' => '<span class="ops-chip">Declined</span>', default => '<span class="ops-chip ops-chip-muted">Closed, no answer</span>',
};
?>
<div class="ops-head">
    <div>
        <h1>Document requests</h1>
        <p>People who checked a code at nfelia.com/verify and asked for the full document. Nothing is shared unless the client says yes: ask them in one click, or turn down junk here.</p>
    </div>
</div>
<?php foreach (['Waiting' => $waiting, 'Lately' => $done] as $label => $rows): ?>
<h2 class="ops-section"><?= e($label) ?></h2>
<div class="ops-table-wrap">
    <table class="ops-table is-stacked is-compact">
        <thead><tr><th scope="col">Who</th><th scope="col">Wants</th><th scope="col">State</th><th scope="col">Asked</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr class="is-link">
                <td><a class="cell-main" href="<?= e(ops_url('requests/' . (int)$r['id'])) ?>"><?= e($who($r)) ?></a><span class="cell-sub"><?= e($roles[$r['role']] ?? '') ?> · <?= e($r['email']) ?></span></td>
                <td data-label="Wants"><?= e($r['label']) ?><span class="cell-sub"><?= e($r['business_name']) ?></span></td>
                <td data-label="State"><?= $chip($r['status']) ?></td>
                <td data-label="Asked"><?= e(ops_ago($r['confirmed_at'] ?? $r['created_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="4"><div class="ops-empty"><h3><?= $label === 'Waiting' ? 'Nothing waiting' : 'None yet' ?></h3></div></td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php endforeach; ?>
