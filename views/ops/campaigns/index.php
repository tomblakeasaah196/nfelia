<?php
/** Campaigns (list): $rows. */
?>
<div class="ops-head">
    <div>
        <h1>Campaigns</h1>
        <p>One email to a group of leads or clients: trials ending this week, people who stalled in setup, everyone you met at a market. Sent a few at a time, each with an unsubscribe link.</p>
    </div>
    <div class="ops-head-actions"><a class="ops-btn ops-btn-primary" href="<?= e(ops_url('campaigns/new')) ?>"><?= ops_icon('plus') ?> Write a campaign</a></div>
</div>
<div class="ops-table-wrap">
    <table class="ops-table is-stacked is-compact">
        <thead><tr><th scope="col">Campaign</th><th scope="col">State</th><th scope="col" class="num">People</th><th scope="col" class="num">Delivered</th><th scope="col">Sent</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $c): ?>
            <tr class="is-link">
                <td><a class="cell-main" href="<?= e(ops_url('campaigns/' . (int)$c['id'])) ?>"><?= e($c['name']) ?></a><span class="cell-sub"><?= e($c['subject']) ?> · by <?= e($c['author'] ?? '—') ?></span></td>
                <td data-label="State"><span class="ops-chip <?= $c['status'] === 'sent' ? 'ops-chip-ok' : ($c['status'] === 'sending' ? 'ops-chip-warn' : '') ?>"><?= e(['draft' => 'Draft', 'sending' => 'Sending', 'sent' => 'Sent'][$c['status']]) ?></span></td>
                <td data-label="People" class="num"><?= $c['status'] === 'draft' ? '—' : (int)$c['people'] ?></td>
                <td data-label="Delivered" class="num"><?= $c['status'] === 'draft' ? '—' : (int)$c['delivered'] ?></td>
                <td data-label="Sent"><?= $c['sent_at'] ? e(date('j M Y', strtotime($c['sent_at']))) : '<span class="muted">Not yet</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5"><div class="ops-empty"><h3>No campaigns yet</h3><p>Write one to a group of leads or clients.</p></div></td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
