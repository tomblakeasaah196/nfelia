<?php
/**
 * Ops pages on their own: signing in, invites, password resets, and
 * messages to someone not signed in. A centred card, no menu.
 */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= e(ops_csrf()) ?>">
    <meta name="theme-color" content="#1B0A11">
    <title><?= e($title) ?> · Nfelia Ops</title>
    <link rel="icon" href="/favicon.ico">
    <link rel="stylesheet" href="<?= e(ops_asset('assets/css/ops.css')) ?>">
    <script defer src="<?= e(ops_asset('assets/js/ops.js')) ?>"></script>
</head>
<body>
<main class="ops-auth">
    <div class="ops-auth-card">
        <div class="ops-brand"><span class="ops-brand-mark"></span><span>Nfelia Ops<small>The Nfelia team</small></span></div>
        <?php if ($flash): ?>
            <div class="ops-flash ops-flash-<?= e($flash['type']) ?>" role="<?= $flash['type'] === 'error' ? 'alert' : 'status' ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>
        <?php require $view_file; ?>
    </div>
</main>
</body>
</html>
