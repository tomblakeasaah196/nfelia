<?php
/**
 * Nfelia — Sign up (Pattern 3: Form, auth).
 * Referral links arrive as /register?ref=CODE; the code is filled in here.
 */

require_once BASE_PATH . '/views/home/_icons.php';

$old = $_SESSION['register_old'] ?? [];
unset($_SESSION['register_old']);

$ref = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', (string)($_GET['ref'] ?? $old['ref_code'] ?? '')));
$ref = substr($ref, 0, 20);

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en-NG">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Start free: Nfelia</title>
    <meta name="description" content="Create your Nfelia account. Free for <?= (int)TRIAL_DAYS ?> days, no card needed.">
    <meta name="theme-color" content="#8B004A">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= APP_URL ?>/favicon-32x32.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,700&family=Plus+Jakarta+Sans:wght@400;600&display=swap">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/home.css?v=1">
</head>
<body class="auth-page">
    <header class="auth-top">
        <a class="brand" href="<?= APP_URL ?>/" aria-label="Nfelia home">
            <img src="<?= APP_URL ?>/assets/img/nfelia-mark-email.png" alt="" width="55" height="40">
            <span>NFELIA</span>
        </a>
    </header>

    <main class="auth-main">
        <div class="auth-card">
            <h1>Start your free trial</h1>
            <p class="lead">Set up your business in about two minutes.</p>
            <p class="trial-strip"><?= nf_icon('check-circle') ?>Free for <?= (int)TRIAL_DAYS ?> days. No card needed.</p>

            <?php if ($flash): ?>
                <p class="auth-flash <?= $flash['type'] === 'error' ? 'is-error' : '' ?>" role="alert"><?= e($flash['message']) ?></p>
            <?php endif; ?>

            <form action="<?= APP_URL ?>/register" method="POST" data-validate>
                <?= csrf_field() ?>
                <div class="field">
                    <label for="full_name">Your full name</label>
                    <input id="full_name" type="text" name="full_name" required autocomplete="name" data-error="Add your full name." value="<?= e($old['full_name'] ?? '') ?>" placeholder="e.g. Ivie Okoro">
                </div>
                <div class="field">
                    <label for="business_name">Business name</label>
                    <input id="business_name" type="text" name="business_name" required autocomplete="organization" data-error="Add your business name." value="<?= e($old['business_name'] ?? '') ?>" placeholder="e.g. Ivie Luxe Hair">
                </div>
                <div class="field">
                    <label for="email">Email address</label>
                    <input id="email" type="email" name="email" required autocomplete="email" inputmode="email" data-error="Enter an email address like name@business.com." value="<?= e($old['email'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" required minlength="8" autocomplete="new-password" aria-describedby="password-hint" data-error="Use at least 8 characters.">
                    <span class="hint" id="password-hint">At least 8 characters.</span>
                </div>
                <div class="field">
                    <label for="password_confirm">Type the password again</label>
                    <input id="password_confirm" type="password" name="password_confirm" required minlength="8" autocomplete="new-password" data-match="password" data-error="Type the same password again.">
                </div>
                <details <?= $ref !== '' ? 'open' : '' ?>>
                    <summary>Have a referral code?</summary>
                    <div class="field">
                        <label for="ref_code">Referral code (optional)</label>
                        <input id="ref_code" type="text" name="ref_code" maxlength="20" value="<?= e($ref) ?>" placeholder="NF-XXX-YYYY" autocapitalize="characters">
                    </div>
                </details>
                <button type="submit" class="btn btn-primary">Create my account</button>
                <p class="terms-note">By creating an account you agree to our
                    <button type="button" class="linkish" data-dialog="terms">Terms</button> and
                    <button type="button" class="linkish" data-dialog="privacy">Privacy Policy</button>.</p>
            </form>

            <p class="auth-alt">Already have an account? <a href="<?= APP_URL ?>/login">Sign in</a></p>
        </div>
    </main>

    <p class="auth-links"><a href="<?= APP_URL ?>/">Return to home</a></p>

    <?php require BASE_PATH . '/views/home/_dialogs.php'; ?>
    <script src="<?= APP_URL ?>/assets/js/home.js?v=1" defer></script>
</body>
</html>
