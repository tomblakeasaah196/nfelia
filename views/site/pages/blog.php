<?php
/**
 * The guides hub (/blog): every guide in the blog group, newest first, as
 * cards with their date and summary. Guides are added in
 * app/site/pages/blog_*.php and show up here by themselves.
 *
 * From site_render: $page, $country.
 */
require_once BASE_PATH . '/views/home/_icons.php';   // nf_icon(): the layout loads it only after the view

$posts = site_group('blog');
usort($posts, fn($a, $b) => [$b['published'], $a['order'], $a['crumb']] <=> [$a['published'], $b['order'], $b['crumb']]);

$fmt_day = fn(string $d) => date('j F Y', strtotime($d));

// The list, for search engines and AI assistants
if ($posts) {
    site_schema_add([
        '@type'           => 'ItemList',
        '@id'             => site_url('blog') . '#guides',
        'name'            => 'Guides for small businesses',
        'numberOfItems'   => count($posts),
        'itemListOrder'   => 'https://schema.org/ItemListOrderDescending',
        'itemListElement' => array_map(fn($i) => [
            '@type'    => 'ListItem',
            'position' => $i + 1,
            'url'      => site_url($posts[$i]['key']),
            'name'     => $posts[$i]['og']['title'] ?? $posts[$i]['crumb'],
        ], array_keys($posts)),
    ]);
}
?>
<section class="page-hero is-plain">
    <div class="wrap page-hero-grid">
        <div class="page-hero-copy">
            <p class="kicker">Guides</p>
            <h1>Guides for running a small business</h1>
            <p class="page-lead">Practical guides for small businesses in Nigeria, Ghana, Kenya and Cameroon. How to write an invoice, get paid by people who owe you, sell on WhatsApp and Instagram, and know your real profit. Each one has something you can copy and use today.</p>
        </div>
    </div>
</section>

<section class="page-section tint" aria-labelledby="guides-title">
    <div class="wrap">
        <?= site_head('All guides', 'Newest first. Free to read, no sign-up.', 'guides-title') ?>
        <?php if ($posts): ?>
        <ul class="post-list">
            <?php foreach ($posts as $p):
                $headline = $p['og']['title'] ?? $p['crumb']; ?>
            <li>
                <a class="card" href="<?= e(site_url($p['key'])) ?>">
                    <time datetime="<?= e($p['published']) ?>"><?= e($fmt_day($p['published'])) ?></time>
                    <h3><?= e($headline) ?></h3>
                    <p><?= e($p['summary'] ?: $p['description']) ?></p>
                    <span class="card-more"><?= nf_icon('arrow') ?><span class="sr-only">Read the guide: <?= e($headline) ?></span></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p>The first guides are being written. Until then, the <a href="<?= e(site_url('help')) ?>">help centre</a> shows how to do each job in Nfelia.</p>
        <?php endif; ?>
    </div>
</section>

<section class="page-section" aria-labelledby="about-guides-title">
    <div class="wrap split">
        <div class="split-copy">
            <h2 id="about-guides-title">How these guides are written</h2>
            <p>They are written by the Nfelia team for people who run a shop, a stall, a salon, a kitchen or an online store. Plain words, real examples and simple sums. Where a guide mentions a law or a tax, it names the office to check with, because rules change.</p>
            <p>The guides work whether you use Nfelia or a notebook. Each one ends with a short, honest note on how Nfelia can do part of the job for you.</p>
        </div>
        <div class="split-copy">
            <h2>Looking for help with Nfelia itself?</h2>
            <ul class="link-list">
                <?php if (site_page('help')): ?>
                <li><a href="<?= e(site_url('help')) ?>"><strong>Help centre</strong><span>Step by step: invoices, your website, stock, bookings and more.</span></a></li>
                <?php endif; ?>
                <?php if (site_page('features')): ?>
                <li><a href="<?= e(site_url('features')) ?>"><strong>What Nfelia does</strong><span>Every feature, with real screens.</span></a></li>
                <?php endif; ?>
                <?php if (site_page('faq')): ?>
                <li><a href="<?= e(site_url('faq')) ?>"><strong>Questions people ask</strong><span>Price, payment, your data and getting started.</span></a></li>
                <?php endif; ?>
                <?php if (site_page('pricing')): ?>
                <li><a href="<?= e(site_url('pricing')) ?>"><strong>Pricing</strong><span>What Nfelia costs after the free trial.</span></a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</section>

<?= site_cta('Put these guides to work with Nfelia') ?>
