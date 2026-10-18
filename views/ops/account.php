<?php
/** Your account (settings): passkeys, password, signing out everywhere. $passkeys. */
?>
<div class="ops-head">
    <div>
        <h1>Your account</h1>
        <p><?= e($staff['full_name']) ?> · <?= e($staff['email']) ?> · <?= e(ops_role_label($staff['role'])) ?></p>
    </div>
</div>

<div class="ops-settings">
    <nav class="ops-settings-nav" aria-label="Account sections">
        <a href="#passkeys">Passkeys</a>
        <a href="#alerts">Alerts</a>
        <a href="#password">Password</a>
        <a href="#devices">Signed-in devices</a>
    </nav>
    <div>
        <section id="passkeys" class="ops-card"><div class="ops-card-body">
            <h2>Passkeys</h2>
            <p>Sign in with your fingerprint, face or phone PIN. A passkey made here only works for Nfelia Ops.</p>
            <ul class="ops-list" data-ops-passkey-list<?= $passkeys ? '' : ' hidden' ?>>
                <?php foreach ($passkeys as $pk): ?>
                    <li>
                        <?= ops_icon('key') ?>
                        <div class="grow">
                            <b><?= e($pk['name']) ?></b>
                            <div class="small muted">Added <?= e(date('j M Y', strtotime($pk['created_at']))) ?> · <?= $pk['last_used_at'] ? 'last used ' . e(ops_ago($pk['last_used_at'])) : 'not used yet' ?></div>
                        </div>
                        <form method="post" action="<?= e(ops_url('account/passkeys/' . (int)$pk['id'] . '/remove')) ?>" data-ops-confirm="Remove the passkey on <?= e($pk['name']) ?>? It will no longer sign you in.">
                            <?= ops_csrf_field() ?>
                            <button class="ops-btn ops-btn-danger ops-btn-sm" type="submit">Remove</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="ops-btn ops-btn-primary" data-ops-passkey-add
                    data-options="<?= e(ops_url('account/passkeys/options')) ?>" data-save="<?= e(ops_url('account/passkeys')) ?>">
                <?= ops_icon('plus') ?> Add a passkey on this device
            </button>
            <p class="small muted" data-ops-passkey-note role="status" style="margin-top:8px"></p>
        </div></section>

        <section id="alerts" class="ops-card"><div class="ops-card-body">
            <h2>Alerts</h2>
            <p>New and returning errors, health problems, and errors given to you<?= ops_can('errors') ? '' : ' (your role gets few of these for now)' ?>.</p>
            <div class="ops-device" data-ops-push data-key="<?= e(VAPID_PUBLIC_KEY) ?>"
                 data-subscribe="<?= e(ops_url('alerts/subscribe')) ?>" data-unsubscribe="<?= e(ops_url('alerts/unsubscribe')) ?>">
                <div class="grow">
                    <b>On this device</b>
                    <p class="small muted" data-ops-push-state>Checking…</p>
                </div>
                <button type="button" class="ops-btn ops-btn-secondary" data-ops-push-toggle hidden>Turn on</button>
            </div>
            <form method="post" action="<?= e(ops_url('alerts/prefs')) ?>" class="ops-prefs">
                <?= ops_csrf_field() ?>
                <label class="ops-check"><input type="checkbox" name="alert_push" value="1"<?= (int)$staff['alert_push'] ? ' checked' : '' ?>>
                    <span><b>Phone alerts</b><small>Crashes, errors that come back, and things that stop working. Turned on per device above.</small></span></label>
                <label class="ops-check"><input type="checkbox" name="digest_email" value="1"<?= (int)$staff['digest_email'] ? ' checked' : '' ?>>
                    <span><b>Morning summary by email</b><small>At 8am: yesterday's new problems, what came back, and anything not working.</small></span></label>
                <button class="ops-btn ops-btn-primary" type="submit">Save</button>
            </form>
        </div></section>

        <section id="password" class="ops-card"><div class="ops-card-body">
            <h2>Password</h2>
            <p>Changing it signs you out on every other device.</p>
            <form method="post" action="<?= e(ops_url('account/password')) ?>" style="max-width:420px" novalidate>
                <?= ops_csrf_field() ?>
                <input type="email" name="email" value="<?= e($staff['email']) ?>" autocomplete="username" hidden>
                <div class="ops-field">
                    <label for="current_password">Current password</label>
                    <input class="ops-input" id="current_password" name="current_password" type="password" autocomplete="current-password" required>
                </div>
                <div class="ops-field">
                    <label for="password">New password</label>
                    <input class="ops-input" id="password" name="password" type="password" autocomplete="new-password" minlength="<?= OPS_MIN_PASSWORD ?>" required>
                    <span class="hint">At least <?= OPS_MIN_PASSWORD ?> characters.</span>
                </div>
                <div class="ops-field">
                    <label for="password_again">New password again</label>
                    <input class="ops-input" id="password_again" name="password_again" type="password" autocomplete="new-password" required>
                </div>
                <button class="ops-btn ops-btn-primary" type="submit">Change password</button>
            </form>
        </div></section>

        <section id="devices" class="ops-card ops-danger"><div class="ops-card-body">
            <h2>Signed-in devices</h2>
            <p>Lost a phone, or signed in somewhere you shouldn't have? Sign out everywhere, this device too.</p>
            <form method="post" action="<?= e(ops_url('account/signout-everywhere')) ?>" data-ops-confirm="Sign out of Nfelia Ops on every device, this one too?">
                <?= ops_csrf_field() ?>
                <button class="ops-btn ops-btn-danger" type="submit">Sign out everywhere</button>
            </form>
        </div></section>
    </div>
</div>
