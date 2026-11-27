<?php
/**
 * Free trials (OpsClientsController::trials): every business on its free
 * trial, on a board by how far it has got. $board (stage => rows), $stages.
 */
$total = array_sum(array_map('count', $board));
$plural = fn(int $n, string $one, string $many) => $n . ' ' . ($n === 1 ? $one : $many);
$left = function (?int $days): string {
    if ($days === null) return 'No end date';
    if ($days <= 0) return 'Ends today';
    return ($days === 1 ? '1 day' : $days . ' days') . ' left';
};
$card = function (array $b) use ($left, $plural): string {
    $days = $b['days_left'] === null ? null : (int)$b['days_left'];
    $did = (int)$b['onboarding_complete']
        ? implode(' · ', [$plural((int)$b['products'], 'item', 'items'), $plural((int)$b['customers'], 'customer', 'customers'), $plural((int)$b['sales'], 'sale', 'sales')])
        : 'Setup: step ' . max(1, min(4, (int)$b['onboarding_step'])) . ' of 4';
    ob_start(); ?>
    <li class="ops-lead-card">
        <a class="cell-main" href="<?= e(ops_url('clients/' . (int)$b['id'])) ?>"><?= e($b['name']) ?></a>
        <span class="cell-sub"><?= e(implode(' · ', array_filter([(string)($b['owner'] ?? ''), $b['country'] ? trim(($b['flag_emoji'] ?? '') . ' ' . $b['country']) : '']))) ?></span>
        <span class="cell-sub">Day <?= (int)$b['day'] ?> · <?= $days !== null && $days <= 3 ? '<span class="ops-chip ops-chip-warn">' . e($left($days)) . '</span>' : e($left($days)) ?></span>
        <span class="cell-sub"><?= e($did) ?><?= (int)$b['shop_online'] ? ' · shop online' : '' ?></span>
        <span class="cell-sub">Last active <?= $b['last_login'] ? e(ops_ago($b['last_login'])) : 'never' ?><?= $b['last_emailed'] ? ' · emailed ' . e(ops_ago($b['last_emailed'])) : '' ?><?= $b['lead_id'] ? ' · a lead in Sales' : '' ?></span>
    </li>
    <?php return ob_get_clean();
};
?>
<div class="ops-head">
    <div>
        <h1>Free trials</h1>
        <p><?php if ($total): ?>
            <?= $plural($total, 'business is', 'businesses are') ?> on a free trial:
            <?= implode(', ', array_map(fn($k) => count($board[$k]) . ' ' . strtolower($stages[$k]['label']), array_keys($stages))) ?>.
            Open one for the whole picture, or to email the owner.
        <?php else: ?>
            Nobody is on a free trial right now.
        <?php endif; ?>
        New owners get a getting-started email from Customer Support on days 1, 3 and 7 until they use Nfelia; owners set up for 2 days with nothing added become leads in Sales.</p>
    </div>
</div>

<div class="ops-board">
    <?php foreach ($stages as $stage => $s): ?>
        <section class="ops-board-col" aria-label="<?= e($s['label']) ?>">
            <h2><?= e($s['label']) ?> <span class="muted num"><?= count($board[$stage]) ?></span></h2>
            <p class="small muted"><?= e($s['hint']) ?></p>
            <ul>
                <?php foreach ($board[$stage] as $b): ?><?= $card($b) ?><?php endforeach; ?>
                <?php if (!$board[$stage]): ?><li class="ops-lead-empty">Nobody</li><?php endif; ?>
            </ul>
        </section>
    <?php endforeach; ?>
</div>
