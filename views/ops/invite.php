<?php /* Accepting an invite: name and password. $invitee, $token. */ ?>
<h1>Join Nfelia Ops</h1>
<p>You're joining as <b><?= e(ops_role_label($invitee['role'])) ?></b> with <?= e($invitee['email']) ?>. Choose a password; you can add a passkey next.</p>
<form method="post" action="<?= e(ops_url('invite/' . $token)) ?>" novalidate>
    <?= ops_csrf_field() ?>
    <div class="ops-field">
        <label for="full_name">Your name</label>
        <input class="ops-input" id="full_name" name="full_name" autocomplete="name" required value="<?= e($invitee['full_name']) ?>">
    </div>
    <input type="email" name="email" value="<?= e($invitee['email']) ?>" autocomplete="username" hidden>
    <div class="ops-field">
        <label for="password">Password</label>
        <input class="ops-input" id="password" name="password" type="password" autocomplete="new-password" minlength="<?= OPS_MIN_PASSWORD ?>" required>
        <span class="hint">At least <?= OPS_MIN_PASSWORD ?> characters.</span>
    </div>
    <div class="ops-field">
        <label for="password_again">Password again</label>
        <input class="ops-input" id="password_again" name="password_again" type="password" autocomplete="new-password" required>
    </div>
    <button type="submit" class="ops-btn ops-btn-primary ops-btn-block">Join Nfelia Ops</button>
</form>
