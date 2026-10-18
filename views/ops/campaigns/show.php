<?php
/** One campaign: a draft (who it reaches, a preview, test, send) or sent (how it went). $c, $people, $preview, $stats, $sends. */
$n = count($people);
?>
<p class="ops-back"><a href="<?= e(ops_url('campaigns')) ?>">← Campaigns</a></p>
<div class="ops-head">
    <div class="ops-head-main">
        <div class="ops-chips-row"><span class="ops-chip <?= $c['status'] === 'sent' ? 'ops-chip-ok' : ($c['status'] === 'sending' ? 'ops-chip-warn' : '') ?>"><?= e(['draft' => 'Draft', 'sending' => 'Sending', 'sent' => 'Sent'][$c['status']]) ?></span></div>
        <h1 class="ops-break"><?= e($c['name']) ?></h1>
        <p>Written by <?= e($c['author'] ?? '—') ?><?= $c['sent_at'] ? ', sent by ' . e($c['sender'] ?? '—') . ' on ' . e(date('j M Y, g:ia', strtotime($c['sent_at']))) : '' ?>.</p>
    </div>
    <?php if ($c['status'] === 'draft'): ?>
    <div class="ops-head-actions">
        <a class="ops-btn ops-btn-secondary" href="<?= e(ops_url('campaigns/' . (int)$c['id'] . '?edit=1')) ?>">Change it</a>
        <form method="post" action="<?= e(ops_url('campaigns/' . (int)$c['id'] . '/test')) ?>"><?= ops_csrf_field() ?><button class="ops-btn ops-btn-secondary" type="submit">Send me a test</button></form>
        <?php if ($n): ?>
        <form method="post" action="<?= e(ops_url('campaigns/' . (int)$c['id'] . '/send')) ?>" data-ops-confirm="Send &quot;<?= e($c['subject']) ?>&quot; to <?= $n ?> <?= $n === 1 ? 'person' : 'people' ?>? This can't be stopped once it starts.">
            <?= ops_csrf_field() ?><button class="ops-btn ops-btn-primary" type="submit">Send to <?= $n ?> <?= $n === 1 ? 'person' : 'people' ?></button></form>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($c['status'] !== 'draft'): ?>
<div class="ops-kpis">
    <div class="ops-kpi"><div class="label">People</div><div class="value"><?= (int)$stats['people'] ?></div></div>
    <div class="ops-kpi is-ok"><div class="label">Delivered</div><div class="value"><?= (int)$stats['delivered'] ?></div></div>
    <div class="ops-kpi"><div class="label">Still to go</div><div class="value"><?= (int)$stats['waiting'] + (int)$stats['sending'] ?></div><div class="note">A few every 5 minutes</div></div>
    <div class="ops-kpi<?= (int)$stats['failed'] ? ' is-bad' : '' ?>"><div class="label">Failed</div><div class="value"><?= (int)$stats['failed'] ?></div></div>
    <div class="ops-kpi"><div class="label">Unsubscribed</div><div class="value"><?= (int)$stats['unsubscribed'] ?></div></div>
</div>
<?php endif; ?>

<div class="ops-detail" style="margin-top:16px">
    <div class="ops-detail-main">
        <section class="ops-card"><div class="ops-card-body">
            <h2>How it looks</h2>
            <p class="muted">Subject: <b><?= e(ops_campaign_vars($c, reset($people) ?: ['name' => ops_staff()['full_name'], 'business' => 'Mama Put Kitchen'], '')['subject']) ?></b></p>
            <iframe class="ops-preview" title="The email" sandbox srcdoc="<?= e($preview) ?>"></iframe>
        </div></section>
    </div>
    <aside class="ops-detail-side">
        <section class="ops-card"><div class="ops-card-body">
            <?php if ($c['status'] === 'draft'): ?>
                <h2>Who it reaches</h2>
                <p><b><?= $n ?></b> <?= $n === 1 ? 'person' : 'people' ?>, each once, leaving out whoever unsubscribed.</p>
                <?php if ($n): ?><ul class="ops-plain" style="margin-top:10px"><?php foreach (array_slice($people, 0, 12) as $p): ?><li><span class="ops-break"><?= e($p['name'] ?: $p['email']) ?><?= $p['business'] ? ' · ' . e($p['business']) : '' ?></span></li><?php endforeach; ?></ul>
                    <?php if ($n > 12): ?><p class="small muted">and <?= $n - 12 ?> more</p><?php endif; ?>
                <?php else: ?><p class="muted">Nobody yet. <a href="<?= e(ops_url('campaigns/' . (int)$c['id'] . '?edit=1')) ?>">Choose who it goes to</a>.</p><?php endif; ?>
            <?php else: ?>
                <h2>Who it went to</h2>
                <ul class="ops-plain">
                    <?php foreach ($sends as $s): ?>
                        <li><span class="ops-break"><?= e($s['name'] ?: $s['email']) ?></span>
                            <span class="muted"><?= e($s['mail_status'] === 'sent' ? 'delivered' : ($s['mail_status'] === 'failed' ? 'failed' : ($s['status'] === 'skipped' ? 'unsubscribed' : 'waiting'))) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div></section>
    </aside>
</div>
