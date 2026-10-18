<?php
/** Support tickets (inbox): $rows, $status, $counts. */
$tabs = ['open' => 'Open', 'replied' => 'Replied', 'closed' => 'Closed', 'all' => 'All'];
?>
<div class="ops-head">
    <div>
        <h1>Support</h1>
        <p>Messages clients send from Nfelia (More → Contact support). Reply here: they see it in Nfelia and by email.</p>
    </div>
</div>
<nav class="ops-tabs" aria-label="Show">
    <?php foreach ($tabs as $k => $label): ?>
        <a href="<?= e(ops_url('support?status=' . $k)) ?>"<?= $status === $k ? ' aria-current="page"' : '' ?>><?= e($label) ?><?php if ($k !== 'all' && !empty($counts[$k])): ?> <span class="num">(<?= (int)$counts[$k] ?>)</span><?php endif; ?></a>
    <?php endforeach; ?>
</nav>
<?php if (!$rows): ?>
    <div class="ops-card ops-empty"><h3>Nothing here</h3><p><?= $status === 'open' ? 'Every ticket has an answer.' : 'No tickets.' ?></p></div>
<?php else: ?>
    <ul class="ops-inbox">
        <?php foreach ($rows as $t): ?>
            <li class="<?= $t['status'] === 'open' ? 'is-new' : '' ?>">
                <a href="<?= e(ops_url('support/' . (int)$t['id'])) ?>">
                    <span class="what"><b><?= e($t['subject']) ?></b><span><?= e(($t['business_name'] ?? 'A client') . ($t['user_name'] ? ' · ' . $t['user_name'] : '')) ?> · <?= e(mb_strimwidth((string)$t['message'], 0, 120, '…')) ?></span></span>
                    <span class="when"><?= e($t['status']) ?> · <?= e(ops_ago($t['created_at'])) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
