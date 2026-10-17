<?php
/**
 * Nfelia — Sign in (Pattern 3: Form, auth).
 * The marketing page now lives at / (views/home/index.php).
 */

require_once BASE_PATH . '/views/home/_icons.php';

$flash = get_flash();
// The installed app started signed out: play its opening here.
$splash = ($_GET['source'] ?? '') === 'pwa';
?>
<!DOCTYPE html>
<html lang="en-NG"<?= $splash ? ' class="nf-splashing"' : '' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in: Nfelia</title>
    <meta name="description" content="Sign in to your Nfelia business account.">
    <meta name="theme-color" content="<?= $splash ? '#6E0039' : '#8B004A' ?>" data-theme-color="#8B004A">
<?php require BASE_PATH . '/views/partials/pwa_head.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,700&family=Plus+Jakarta+Sans:wght@400;600&display=swap">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/home.css?v=3">
</head>
<body class="auth-page">
<?php if ($splash) require BASE_PATH . '/views/partials/nf_splash.php'; ?>
    <header class="auth-top">
        <a class="brand" href="<?= APP_URL ?>/" aria-label="Nfelia home">
            <img src="<?= APP_URL ?>/assets/img/nfelia-mark-email.png" alt="" width="55" height="40">
            <span>NFELIA</span>
        </a>
    </header>

    <main class="auth-main">
        <div class="auth-card">
            <h1>Welcome back</h1>
            <p class="lead">Sign in to your business.</p>

            <?php if ($flash): ?>
                <p class="auth-flash <?= $flash['type'] === 'error' ? 'is-error' : '' ?>" role="alert"><?= e($flash['message']) ?></p>
            <?php endif; ?>

            <form action="<?= APP_URL ?>/login" method="POST" data-validate>
                <?= csrf_field() ?>
                <div class="field">
                    <label for="email">Email address</label>
                    <input id="email" type="email" name="email" required autocomplete="email" inputmode="email" autofocus data-error="Enter the email address you signed up with.">
                </div>
                <div class="field">
                    <div class="auth-row">
                        <label for="password">Password</label>
                        <a href="<?= APP_URL ?>/forgot-password">Forgot password?</a>
                    </div>
                    <input id="password" type="password" name="password" required autocomplete="current-password" data-error="Enter your password.">
                </div>
                <label class="check"><input type="checkbox" name="remember_me" value="1"> Keep me signed in</label>
                <button type="submit" class="btn btn-primary">Sign in</button>
            </form>

            <p class="auth-divider">or</p>
            <a class="btn btn-outline" href="<?= APP_URL ?>/login-link">Email me a sign-in link</a>

            <p class="auth-alt">New to Nfelia? <a href="<?= APP_URL ?>/register">Start free for <?= (int)TRIAL_DAYS ?> days</a></p>
        </div>
    </main>

    <p class="auth-links"><a href="<?= APP_URL ?>/">Return to home</a></p>
    <script src="<?= APP_URL ?>/assets/js/home.js?v=3" defer></script>
</body>
</html>
