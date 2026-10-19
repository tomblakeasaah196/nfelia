<?php
/** One Records Book: $book (with business_name, staff_name), $sheets, $downloads (by the team), $can_download. */
$counts = json_decode((string)$book['counts'], true) ?: [];
$facts  = json_decode((string)$book['facts'], true) ?: [];
$sym    = '₦';
$busy   = in_array($book['status'], ['queued', 'building'], true);
?>
<p class="ops-back"><a href="<?= e(ops_url('records')) ?>">← Records Books</a></p>
<div class="ops-head">
    <div class="ops-head-main">
        <div class="ops-chips-row">
            <?= match ($book['status']) {
                'ready' => '<span class="ops-chip ops-chip-ok">Ready</span>', 'queued', 'building' => '<span class="ops-chip ops-chip-accent" data-ops-book="' . (int)$book['id'] . '">Being made</span>',
                'failed' => '<span class="ops-chip ops-chip-bad">Failed</span>', 'expired' => '<span class="ops-chip ops-chip-muted">No longer kept</span>',
                'awaiting_payment' => '<span class="ops-chip ops-chip-warn">Waiting for payment</span>', default => '<span class="ops-chip">' . e($book['status']) . '</span>',
            } ?>
            <span class="ops-chip ops-chip-muted"><?= e(records_code_display((string)$book['code'])) ?></span>
        </div>
        <h1 class="ops-break"><?= e($book['business_name']) ?>'s Records Book</h1>
        <p><?= e(records_period_text($book)) ?>. Made <?= e(date('j M Y, g:ia', strtotime((string)$book['created_at']))) ?><?= $book['staff_name'] ? ' by ' . e($book['staff_name']) : ' by the owner' ?>.</p>
    </div>
    <div class="ops-head-actions">
        <a class="ops-btn ops-btn-secondary" href="<?= e(ops_url('clients/' . (int)$book['business_id'])) ?>">Open the client</a>
        <?php if ($book['status'] === 'ready'): ?>
            <a class="ops-btn ops-btn-secondary" href="<?= e(records_verify_url((string)$book['code'])) ?>" target="_blank" rel="noopener">Its verify page</a>
            <?php if ($can_download): ?><a class="ops-btn ops-btn-primary" href="<?= e(ops_url('records/' . (int)$book['id'] . '/download')) ?>" data-no-prefetch><?= ops_icon('download') ?> Download</a><?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="ops-detail">
    <div class="ops-detail-main">
        <section class="ops-card"><div class="ops-card-body">
            <h2>What is in it</h2>
            <?php if ($busy): ?>
                <p class="muted">Being made. This page updates by itself when it is ready (usually under a minute).</p>
            <?php elseif ($book['status'] === 'failed'): ?>
                <p>It didn't work: <?= e((string)$book['error']) ?>. The error is in Errors. Make it again, with a shorter period if it was too big.</p>
            <?php else: ?>
                <ul class="ops-plain">
                <?php foreach ($counts as $key => $n): if (!$n || !isset($sheets[$key])) continue; ?>
                    <li><span><?= e($sheets[$key]['title']) ?></span><span class="muted num"><?= number_format((int)$n) ?></span></li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div></section>
    </div>
    <aside class="ops-detail-side">
        <section class="ops-card"><div class="ops-card-body">
            <h2>About it</h2>
            <dl class="ops-facts">
                <dt>How</dt><dd><?= e(['free' => 'Free, with their plan', 'paid' => 'Bought' . ($book['amount'] ? ', ' . $book['currency_code'] . ' ' . number_format((float)$book['amount']) : ''), 'team' => 'Made by the team'][$book['charge']] ?? $book['charge']) ?></dd>
                <?php if ($book['reason']): ?><dt>Why</dt><dd><?= e($book['reason']) ?></dd><?php endif; ?>
                <?php if ($book['charge'] === 'team'): ?><dt>Owner</dt><dd><?= (int)$book['send_to_owner'] ? 'Sent to them' : 'Told, not sent' ?></dd><?php endif; ?>
                <?php if ($book['file_size']): ?><dt>File</dt><dd><?= e(number_format($book['file_size'] / 1024)) ?> KB · <?= (int)$book['downloads'] ?> download<?= (int)$book['downloads'] === 1 ? '' : 's' ?></dd><?php endif; ?>
                <?php if ($book['expires_at']): ?><dt>Kept until</dt><dd><?= e(date('j M Y', strtotime((string)$book['expires_at']))) ?></dd><?php endif; ?>
            </dl>
            <?php if ($downloads): ?>
                <h3 style="margin-top:14px">Downloaded in Ops</h3>
                <ul class="ops-plain"><?php foreach ($downloads as $d): ?><li><span><?= e($d['full_name'] ?? '—') ?></span><span class="muted"><?= e(ops_ago($d['created_at'])) ?></span></li><?php endforeach; ?></ul>
            <?php endif; ?>
        </div></section>
    </aside>
</div>
<?php if ($busy): ?>
<script>
(function () {
    var n = 0;
    function look() {
        if (++n > 120) return;
        fetch(<?= json_encode(ops_url('records/' . (int)$book['id'] . '/status')) ?>, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (s) { if (s.status !== 'queued' && s.status !== 'building') location.reload(); else setTimeout(look, 3000); })
            .catch(function () { setTimeout(look, 6000); });
    }
    setTimeout(look, 2500);
})();
</script>
<?php endif; ?>
