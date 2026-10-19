<?php
/** Records Books (list): $rows, every client's books made lately. */
$state = fn(array $b) => match ($b['status']) {
    'ready' => '<span class="ops-chip ops-chip-ok">Ready</span>', 'queued', 'building' => '<span class="ops-chip ops-chip-accent">Being made</span>',
    'awaiting_payment' => '<span class="ops-chip ops-chip-warn">Waiting for payment</span>', 'failed' => '<span class="ops-chip ops-chip-bad">Failed</span>',
    'expired' => '<span class="ops-chip ops-chip-muted">No longer kept</span>', default => '<span class="ops-chip">' . e($b['status']) . '</span>',
};
$charge = ['free' => 'Free with their plan', 'paid' => 'Bought', 'team' => 'Made by the team'];
?>
<div class="ops-head">
    <div>
        <h1>Records Books</h1>
        <p>A client's records as one Excel workbook in their colours, with a code anyone can check at nfelia.com/verify. Clients make their own from Your records; make one here when they ask you.</p>
    </div>
    <div class="ops-head-actions"><a class="ops-btn ops-btn-primary" href="<?= e(ops_url('records/new')) ?>"><?= ops_icon('plus') ?> Make one for a client</a></div>
</div>

<div class="ops-table-wrap">
    <table class="ops-table is-stacked is-compact">
        <thead><tr><th scope="col">Client</th><th scope="col">How</th><th scope="col">State</th><th scope="col">Made</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $b): ?>
            <tr class="is-link">
                <td><a class="cell-main" href="<?= e(ops_url('records/' . (int)$b['id'])) ?>"><?= e($b['business_name']) ?></a>
                    <span class="cell-sub"><?= e(records_period_text($b)) ?> · <?= e(records_code_display((string)$b['code'])) ?></span></td>
                <td data-label="How"><?= e($charge[$b['charge']] ?? $b['charge']) ?><?php if ($b['staff_name']): ?><span class="cell-sub"><?= e($b['staff_name']) ?></span><?php endif; ?></td>
                <td data-label="State"><?= $state($b) ?></td>
                <td data-label="Made"><?= e(ops_ago($b['created_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
            <tr><td colspan="4"><div class="ops-empty"><h3>No books yet</h3><p>When a client makes a Records Book, or you make one for them, it shows here.</p></div></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
