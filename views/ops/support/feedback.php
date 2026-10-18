<?php
/** Feedback from inside the app (feed): $rows, $dist (rating => count). */
$total = array_sum($dist);
$avg = $total ? array_sum(array_map(fn($r, $n) => $r * $n, array_keys($dist), $dist)) / $total : 0;
?>
<div class="ops-head">
    <div>
        <h1>Feedback</h1>
        <p>Ratings and comments clients leave inside Nfelia. <?= $total ? number_format($total) . ' in all, ' . number_format($avg, 1) . ' stars on average.' : '' ?></p>
    </div>
</div>
<?php if ($total): ?>
<div class="ops-kpis" style="margin-bottom:20px">
    <?php for ($r = 5; $r >= 1; $r--): ?>
        <div class="ops-kpi"><div class="label"><?= str_repeat('★', $r) ?></div><div class="value"><?= (int)($dist[$r] ?? 0) ?></div></div>
    <?php endfor; ?>
</div>
<?php endif; ?>
<?php if (!$rows): ?>
    <div class="ops-card ops-empty"><h3>No feedback yet</h3></div>
<?php else: ?>
    <ul class="ops-feed">
        <?php foreach ($rows as $f): ?>
            <li>
                <span class="ops-chip <?= (int)$f['rating'] <= 2 ? 'ops-chip-bad' : ((int)$f['rating'] >= 4 ? 'ops-chip-ok' : '') ?>"><?= (int)$f['rating'] ?>★</span>
                <div class="what"><?= $f['message'] ? nl2br(e($f['message'])) : '<span class="muted">No comment</span>' ?>
                    <div class="meta"><a href="<?= e(ops_url('clients/' . (int)$f['business_id'])) ?>"><?= e($f['business_name'] ?? 'A client') ?></a><?= $f['user_name'] ? ' · ' . e($f['user_name']) : '' ?><?= $f['trigger_action'] ? ' · after ' . e(str_replace('_', ' ', $f['trigger_action'])) : '' ?></div></div>
                <time class="when"><?= e(ops_ago($f['created_at'])) ?></time>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
