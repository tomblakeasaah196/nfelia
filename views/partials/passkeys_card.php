<?php
/**
 * Settings → Profile: this person's passkeys. Each passkey belongs to one
 * person on one device (or synced across their own phones by Apple or
 * Google). assets/js/lock.js adds and removes them through api/ajax/auth.php.
 */
$pk_rows = passkey_list((int)auth_user()['id']);
$pk_when = function (?string $at): string {
    if (!$at) return 'Not used yet';
    $day = date('Y-m-d', strtotime($at));
    return 'Last used ' . ($day === date('Y-m-d') ? 'today' : ($day === date('Y-m-d', strtotime('-1 day')) ? 'yesterday' : date('j M Y', strtotime($at))));
};
$pk_row_html = '<span class="nf-tile-ic">' . nav_icon('fingerprint') . '</span><span class="nf-pk-who"><b></b><small></small></span>'
    . '<button type="button" class="nf-pk-remove" data-pk-remove="" data-pk-name="">Remove</button>';
?>
<section id="passkeys" class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm" data-passkeys>
    <h2 class="font-heading font-bold text-sm text-brand-plum mb-2 uppercase tracking-wider">Passkeys</h2>
    <p class="nf-help" style="margin-bottom:14px">Sign in and unlock with your fingerprint, face or phone PIN instead of your password. Add one on each phone or computer you use.</p>
    <ul class="nf-pk-list" data-pk-list data-pk-template="<?= e($pk_row_html) ?>">
        <?php foreach ($pk_rows as $row): ?>
            <li>
                <span class="nf-tile-ic"><?= nav_icon('fingerprint') ?></span>
                <span class="nf-pk-who"><b><?= e($row['name']) ?></b><small>Added <?= e(date('j M Y', strtotime($row['created_at']))) ?>. <?= e($pk_when($row['last_used_at'])) ?>.</small></span>
                <button type="button" class="nf-pk-remove" data-pk-remove="<?= (int)$row['id'] ?>" data-pk-name="<?= e($row['name']) ?>" aria-label="Remove the passkey on <?= e($row['name']) ?>">Remove</button>
            </li>
        <?php endforeach; ?>
    </ul>
    <p class="nf-help" data-pk-empty<?= $pk_rows ? ' hidden' : '' ?>>No passkeys yet.</p>
    <p class="nf-lock-err" data-pk-note role="alert" hidden></p>
    <p class="nf-help" data-pk-unsupported hidden>This browser cannot make passkeys. On your phone, use Chrome or Safari.</p>
    <button type="button" class="nf-btn nf-btn-primary nf-pk-add" data-pk-add><?= nav_icon('fingerprint') ?>Add a passkey on this device</button>
</section>
