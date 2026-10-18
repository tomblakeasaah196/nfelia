<?php
/**
 * The Ops frame: the menu (a plum sidebar on computers, a sheet behind the
 * menu button on phones), the flash message, then the page.
 * From ops_render(): $title, $view_file, $staff, $flash, and the page's own vars.
 */
$nav = ops_nav($staff);
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
    <link rel="preload" href="/assets/fonts/plus-jakarta-sans-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(ops_asset('assets/css/ops.css')) ?>">
    <script defer src="<?= e(ops_asset('assets/js/ops.js')) ?>"></script>
</head>
<body>
<div class="ops-shell" data-ops-shell>
    <header class="ops-top">
        <button type="button" data-ops-menu aria-label="Open the menu" aria-expanded="false"><?= ops_icon('menu') ?></button>
        <a class="ops-brand" href="<?= e(ops_url()) ?>"><span class="ops-brand-mark"></span>Nfelia Ops</a>
    </header>
    <div class="ops-scrim" data-ops-scrim hidden></div>

    <aside class="ops-side" aria-label="Menu">
        <a class="ops-brand" href="<?= e(ops_url()) ?>">
            <span class="ops-brand-mark"></span>
            <span>Nfelia Ops<small>The Nfelia team</small></span>
        </a>
        <nav class="ops-nav">
            <?php foreach ($nav as $group => $links): ?>
                <div class="ops-nav-group"><?= e($group) ?></div>
                <?php foreach ($links as [$path, $label, $icon, $count]): ?>
                    <a href="<?= e(ops_url($path)) ?>"<?= ops_nav_current($path) ? ' aria-current="page"' : '' ?>>
                        <?= ops_icon($icon) ?><span><?= e($label) ?></span>
                        <?php if ($count): ?><span class="ops-count num"><?= (int)$count ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>
        <div class="ops-me">
            <span class="ops-avatar" aria-hidden="true"><?= e(ops_initials($staff['full_name'])) ?></span>
            <div>
                <a href="<?= e(ops_url('account')) ?>"><?= e($staff['full_name']) ?></a>
                <small><?= e(ops_role_label($staff['role'])) ?></small>
            </div>
            <form method="post" action="<?= e(ops_url('signout')) ?>">
                <?= ops_csrf_field() ?>
                <button type="submit" title="Sign out" aria-label="Sign out"><?= ops_icon('signout') ?></button>
            </form>
        </div>
    </aside>

    <main class="ops-main" id="main">
        <div class="ops-page<?= !empty($narrow) ? ' narrow' : '' ?>">
            <?php if ($flash): ?>
                <div class="ops-flash ops-flash-<?= e($flash['type']) ?>" role="<?= $flash['type'] === 'error' ? 'alert' : 'status' ?>"><?= e($flash['message']) ?></div>
            <?php endif; ?>
            <?php require $view_file; ?>
        </div>
    </main>
</div>
</body>
</html>
