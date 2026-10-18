<?php /* A forgotten password: we email a link. $email. */ ?>
<h1>Reset your password</h1>
<p>We'll email you a link to choose a new one. It works for an hour.</p>
<form method="post" action="<?= e(ops_url('forgot')) ?>" novalidate>
    <?= ops_csrf_field() ?>
    <div class="ops-field">
        <label for="email">Email</label>
        <input class="ops-input" id="email" name="email" type="email" autocomplete="username" required value="<?= e($email) ?>">
    </div>
    <button type="submit" class="ops-btn ops-btn-primary ops-btn-block">Email me a link</button>
</form>
<p class="ops-auth-foot"><a href="<?= e(ops_url('signin')) ?>">Back to sign in</a></p>
