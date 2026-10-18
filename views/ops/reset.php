<?php /* Choosing a new password from an emailed link. $token. */ ?>
<h1>Choose a new password</h1>
<p>Every other device you're signed in on will be signed out.</p>
<form method="post" action="<?= e(ops_url('reset/' . $token)) ?>" novalidate>
    <?= ops_csrf_field() ?>
    <div class="ops-field">
        <label for="password">New password</label>
        <input class="ops-input" id="password" name="password" type="password" autocomplete="new-password" minlength="<?= OPS_MIN_PASSWORD ?>" required>
        <span class="hint">At least <?= OPS_MIN_PASSWORD ?> characters.</span>
    </div>
    <div class="ops-field">
        <label for="password_again">New password again</label>
        <input class="ops-input" id="password_again" name="password_again" type="password" autocomplete="new-password" required>
    </div>
    <button type="submit" class="ops-btn ops-btn-primary ops-btn-block">Save and sign in</button>
</form>
<p class="ops-auth-foot"><a href="<?= e(ops_url('signin')) ?>">Back to sign in</a></p>
