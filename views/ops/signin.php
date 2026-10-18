<?php /* Sign in: a passkey (one touch), or email and password. $email: last one typed. */ ?>
<h1>Sign in</h1>
<p>For the Nfelia team only.</p>

<button type="button" class="ops-btn ops-btn-primary ops-btn-block" data-ops-passkey-signin
        data-options="<?= e(ops_url('signin/passkey/options')) ?>" data-verify="<?= e(ops_url('signin/passkey')) ?>">
    <?= ops_icon('key') ?> Sign in with a passkey
</button>
<p class="small muted" data-ops-passkey-note role="status" style="margin-top:8px"></p>

<div class="ops-or">or with your email</div>

<form method="post" action="<?= e(ops_url('signin')) ?>" novalidate>
    <?= ops_csrf_field() ?>
    <div class="ops-field">
        <label for="email">Email</label>
        <input class="ops-input" id="email" name="email" type="email" autocomplete="username" required value="<?= e($email) ?>">
    </div>
    <div class="ops-field">
        <label for="password">Password</label>
        <input class="ops-input" id="password" name="password" type="password" autocomplete="current-password" required>
    </div>
    <button type="submit" class="ops-btn ops-btn-secondary ops-btn-block">Sign in</button>
</form>
<p class="ops-auth-foot"><a href="<?= e(ops_url('forgot')) ?>">Forgot your password?</a></p>
