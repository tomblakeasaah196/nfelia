<?php
/**
 * The header of every public page: the landing page (views/home) and the
 * site's own pages (views/site/layout.php). Real links, so search engines
 * and people find the main pages from anywhere.
 *
 * $nav_iso       the country the visitor is reading about ('NG' by default),
 *                so Pricing goes to that country's prices
 * $nav_get_app   show "Get the app" (the landing page has its dialog)
 */
$nav_iso      = $nav_iso ?? 'NG';
$nav_get_app  = $nav_get_app ?? false;
$nav_country  = site_country($nav_iso);
$nav_pricing  = site_page(($nav_country['prefix'] ? strtolower($nav_iso) . '/' : '') . 'pricing') ?? site_page('pricing');
$nav_items = array_values(array_filter([
    site_page('features') ? ['Features', site_url('features')] : null,
    $nav_pricing ? ['Pricing', site_url($nav_pricing['key'])] : null,
    ['Shops', APP_URL . '/shops'],
    site_page('help') ? ['Help', site_url('help')] : null,
    site_page('blog') ? ['Guides', site_url('blog')] : null,
]));
// The page you are on: its section's link is marked (aria-current)
$nav_current = rtrim((string)($GLOBALS['site_page']['path'] ?? parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/'), '/');
$nav_mark   = function (string $href) use ($nav_current): string {
    $path = rtrim((string)(parse_url($href, PHP_URL_PATH) ?? ''), '/');
    if ($path === '') {
        return '';
    }
    return $nav_current === $path ? ' aria-current="page"' : (str_starts_with($nav_current, $path . '/') ? ' aria-current="true"' : '');
};
?>
<header class="site-header" data-header>
    <div class="wrap">
        <a class="brand" href="<?= APP_URL ?>/" aria-label="Nfelia home">
            <img src="<?= APP_URL ?>/assets/img/nfelia-mark-email.png" alt="" width="55" height="40">
            <span>NFELIA</span>
        </a>
        <nav class="nav-links" aria-label="Main">
            <?php foreach ($nav_items as [$label, $href]): ?>
            <a href="<?= e($href) ?>"<?= $nav_mark($href) ?>><?= e($label) ?></a>
            <?php endforeach; ?>
            <?php if ($nav_get_app): ?><button type="button" class="linkish" data-dialog="get-app">Get the app</button><?php endif; ?>
        </nav>
        <div class="header-actions">
            <a class="signin-link" href="<?= APP_URL ?>/login">Sign in</a>
            <a class="btn btn-primary btn-sm" href="<?= e(site_signup_url($nav_iso)) ?>">Start free</a>
            <details class="nav-menu">
                <summary aria-label="Menu"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg></summary>
                <nav class="nav-menu-panel" aria-label="Menu">
                    <?php foreach ($nav_items as [$label, $href]): ?>
                    <a href="<?= e($href) ?>"<?= $nav_mark($href) ?>><?= e($label) ?></a>
                    <?php endforeach; ?>
                    <?php if ($nav_get_app): ?><button type="button" class="linkish" data-dialog="get-app">Get the app</button><?php endif; ?>
                    <?php if (site_page('about')): ?><a href="<?= e(site_url('about')) ?>">About Nfelia</a><?php endif; ?>
                    <?php if (site_page('contact')): ?><a href="<?= e(site_url('contact')) ?>">Contact us</a><?php endif; ?>
                    <a href="<?= APP_URL ?>/login">Sign in</a>
                </nav>
            </details>
        </div>
    </div>
</header>
