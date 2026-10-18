<?php
/**
 * Home (a glance): what needs someone, for this role, then recent activity.
 * $tiles: [[label, value, note, url, tone]] (five at most); $passkeys;
 * $recent: the latest audit rows (admins).
 */
$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$first = preg_split('/\s+/u', trim($staff['full_name']))[0] ?? '';
?>
<div class="ops-head">
    <div>
        <h1><?= e($greeting . ', ' . $first) ?></h1>
        <p>What needs the team's attention, as <?= e(ops_role_label($staff['role'])) ?>.</p>
    </div>
</div>

<?php if ($passkeys === 0): ?>
    <div class="ops-flash ops-flash-info">
        <span>Signing in takes one touch with a passkey (fingerprint, face or phone PIN).
        <a href="<?= e(ops_url('account#passkeys')) ?>">Add a passkey</a></span>
    </div>
<?php endif; ?>

<?php if (!empty($tiles)): ?>
<div class="ops-kpis">
    <?php foreach (array_slice($tiles, 0, 5) as [$label, $value, $note, $url, $tone]): ?>
        <a class="ops-kpi<?= $tone ? ' is-' . e($tone) : '' ?>" href="<?= e(ops_url($url)) ?>">
            <div class="label"><?= e($label) ?></div>
            <div class="value"><?= e($value) ?></div>
            <?php if ($note): ?><div class="note"><?= e($note) ?></div><?php endif; ?>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($recent): ?>
<section class="ops-section">
    <h2>Recent activity</h2>
    <p>The latest things done in Ops. <a href="<?= e(ops_url('audit')) ?>">See the audit log</a></p>
    <?php $rows = $recent; require __DIR__ . '/_feed.php'; ?>
</section>
<?php endif; ?>
