<?php
/**
 * Messages from the home page held as spam (OpsLeadsController::held): $rows
 * (general_feedback rows with a spam_reason, the last 90 days; the ones
 * nobody has looked at first).
 */
$waiting = array_filter($rows, fn($r) => $r['reviewed_at'] === null);
?>
<p class="ops-back"><a href="<?= e(ops_url('leads')) ?>">← Leads</a></p>
<div class="ops-head">
    <div class="ops-head-main">
        <h1>Held as spam</h1>
        <p>Messages from the home page that read like a sales pitch, carry a link to another site, are named after a famous company,
           or are gibberish. They make no lead and tell nobody. If one is a real person after all, make it a lead.</p>
    </div>
</div>

<section class="ops-card"><div class="ops-card-body">
    <h2><?= count($waiting) ?> to look at</h2>
    <?php if (!$rows): ?>
        <p class="muted">Nothing held in the last 90 days.</p>
    <?php else: ?>
    <ul class="ops-notes">
        <?php foreach ($rows as $r): ?>
        <li<?= $r['reviewed_at'] ? ' class="muted"' : '' ?>>
            <b><?= e($r['enterprise_name']) ?></b>
            <span class="muted small"><?= e($r['contact_info'] ?: 'no contact') ?> · <?= e(ops_ago($r['created_at'])) ?> · <?= e($r['spam_reason']) ?><?= $r['reviewed_at'] ? ' · left as spam' : '' ?></span>
            <p style="white-space:pre-wrap"><?= e(mb_strimwidth((string)$r['message'], 0, 600, '…')) ?></p>
            <?php if (!$r['reviewed_at']): ?>
            <div class="ops-actions">
                <form method="post" action="<?= e(ops_url('leads/held/' . (int)$r['id'])) ?>">
                    <?= ops_csrf_field() ?><input type="hidden" name="decision" value="real">
                    <button class="ops-btn ops-btn-secondary ops-btn-sm" type="submit">It's real: make it a lead</button>
                </form>
                <form method="post" action="<?= e(ops_url('leads/held/' . (int)$r['id'])) ?>">
                    <?= ops_csrf_field() ?><input type="hidden" name="decision" value="spam">
                    <button class="ops-btn ops-btn-ghost ops-btn-sm" type="submit">Leave as spam</button>
                </form>
            </div>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div></section>
