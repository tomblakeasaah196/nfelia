<?php
/**
 * The footer of every public page. It links to every main page of the site,
 * grouped the way people look for things, so each page is one tap (and one
 * link for a search engine) from anywhere.
 *
 * $nav_iso         the country the visitor is reading about
 * $foot_feedback   show "Send feedback" (the landing page has its dialog)
 */
$nav_iso       = $nav_iso ?? 'NG';
$foot_feedback = $foot_feedback ?? false;
$foot_org      = site_org();
$foot_links    = fn(array $pages) => array_map(fn($p) => [$p['crumb'], site_url($p['key'])], $pages);
$foot_columns  = array_filter([
    'Product' => array_merge(
        site_page('features') ? [['All features', site_url('features')]] : [],
        $foot_links(array_slice(site_group('features'), 0, 9)),
        site_page('pricing') ? [['Pricing', site_url(($nav_iso !== 'NG' && site_page(strtolower($nav_iso) . '/pricing')) ? strtolower($nav_iso) . '/pricing' : 'pricing')]] : []
    ),
    'For your business' => array_merge(
        site_page('for') ? [['All trades', site_url('for')]] : [],
        $foot_links(site_group('for'))
    ),
    'Help' => array_merge(
        site_page('help') ? [['Help centre', site_url('help')]] : [],
        site_page('faq') ? [['Questions people ask', site_url('faq')]] : [],
        [['Book a call with us', APP_URL . '/book'], ['Check a document is genuine', APP_URL . '/verify'], ['Shops on Nfelia', APP_URL . '/shops']]
    ),
    // The legal pages are in the bar at the bottom
    'Company' => $foot_links(array_filter([site_page('about'), site_page('contact'), site_page('blog')])),
]);
?>
<footer class="site-footer">
    <div class="wrap">
        <h2 class="sr-only">Nfelia site links</h2>
        <div class="footer-grid footer-grid-wide">
            <div class="footer-brand">
                <a class="brand" href="<?= APP_URL ?>/" aria-label="Nfelia home">
                    <img src="<?= APP_URL ?>/assets/img/nfelia-mark-email.png" alt="" width="55" height="40">
                    <span>NFELIA</span>
                </a>
                <p class="footer-about">Your own website, plus invoices, debts, stock and profit, for small businesses in Nigeria, Ghana, Kenya and Cameroon.</p>
                <ul class="footer-contact">
                    <li><a href="<?= e(site_wa()) ?>" target="_blank" rel="noopener">WhatsApp: <?= e($foot_org['phone_text']) ?></a></li>
                    <li><a href="tel:<?= e($foot_org['phone']) ?>">Call: <?= e($foot_org['phone_text']) ?></a></li>
                    <li><a href="mailto:<?= e($foot_org['support']) ?>">Support: <?= e($foot_org['support']) ?></a></li>
                    <li><a href="mailto:<?= e($foot_org['sales']) ?>">Sales: <?= e($foot_org['sales']) ?></a></li>
                </ul>
                <?php if ($foot_org['same_as']): ?>
                <p class="footer-follow">Follow Nfelia:
                    <?php foreach ($foot_org['same_as'] as $label => $href): ?>
                    <a href="<?= e($href) ?>" target="_blank" rel="noopener me"><?= e($label) ?></a>
                    <?php endforeach; ?>
                </p>
                <?php endif; ?>
            </div>
            <?php foreach ($foot_columns as $title => $links): if (!$links) continue; ?>
            <div>
                <h3><?= e($title) ?></h3>
                <ul>
                    <?php foreach ($links as [$label, $href]): ?>
                    <li><a href="<?= e($href) ?>"><?= e($label) ?></a></li>
                    <?php endforeach; ?>
                    <?php if ($title === 'Company' && $foot_feedback): ?>
                    <li><button type="button" class="linkish" data-dialog="feedback">Send feedback</button></li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>
        <nav class="footer-countries" aria-label="Nfelia in your country">
            <span>Nfelia in:</span>
            <?php foreach (site_countries() as $c):
                $home = $c['prefix'] === '' ? site_page('home') : site_page(strtolower($c['iso']));
                if (!$home) continue; ?>
            <a href="<?= e(site_url($home['key'])) ?>" hreflang="<?= e($c['hreflang']) ?>"<?= $c['iso'] === $nav_iso ? ' aria-current="true"' : '' ?>><?= e($c['name']) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="footer-bottom">
            <span>&copy; <?= date('Y') ?> Nfelia, a product of <?= e($foot_org['legal']) ?>.</span>
            <span class="legal">
                <?php foreach (site_group('legal') as $p): ?>
                <a href="<?= e(site_url($p['key'])) ?>"><?= e($p['crumb']) ?></a>
                <?php endforeach; ?>
            </span>
        </div>
    </div>
</footer>
