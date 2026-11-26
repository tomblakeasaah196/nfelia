<?php
/**
 * The help centre hub (/help): every article in the help group, under its
 * topic (Start, Sell, Get paid, Run your business, Your account), in each
 * article's own order. Articles come from app/site/pages/help_*.php and
 * show up here by themselves; one with a topic not in the list goes under
 * "More help" at the end, so nothing is ever left out.
 *
 * From site_render: $page, $country.
 */
require_once BASE_PATH . '/views/home/_icons.php';   // the view runs before the layout loads it

$topics = [
    'Start'             => ['topic-start', 'Sign up, set up your business and put Nfelia on your phone.'],
    'Sell'              => ['topic-sell', 'Your website, being found on Google, and orders from customers.'],
    'Get paid'          => ['topic-get-paid', 'Invoices, receipts, payments and the money people owe you.'],
    'Run your business' => ['topic-run', 'Stock, bookings, deliveries, spending, profit, tasks and your records.'],
    'Your account'      => ['topic-account', 'Your team, your plan and paying for Nfelia.'],
];
$more = ['topic-more', 'Other guides.'];

// Every article, under its topic, in the group's order (site_group keeps it)
$by_topic = array_fill_keys(array_keys($topics), []);
$others   = [];
foreach (site_group('help') as $p) {
    if (isset($by_topic[$p['topic'] ?? ''])) {
        $by_topic[$p['topic']][] = $p;
    } else {
        $others[] = $p;
    }
}
$groups = [];
foreach ($topics as $name => [$id, $blurb]) {
    if ($by_topic[$name]) {
        $groups[] = [$name, $id, $blurb, $by_topic[$name]];
    }
}
if ($others) {
    $groups[] = ['More help', $more[0], $more[1], $others];
}
$headline = fn(array $p) => $p['og']['title'] ?? $p['crumb'];

// The whole list, for search engines and AI assistants
$listed = array_merge(...array_map(fn($g) => $g[3], $groups ?: [[null, null, null, []]]));
if ($listed) {
    site_schema_add([
        '@type'           => 'ItemList',
        '@id'             => site_url('help') . '#articles',
        'name'            => 'Nfelia help centre',
        'numberOfItems'   => count($listed),
        'itemListElement' => array_map(fn($i) => [
            '@type'    => 'ListItem',
            'position' => $i + 1,
            'url'      => site_url($listed[$i]['key']),
            'name'     => $headline($listed[$i]),
        ], array_keys($listed)),
    ]);
}

$start_here = array_values(array_filter(['help/getting-started', 'help/add-products', 'help/send-an-invoice'], fn($k) => site_page($k) !== null));
$org = site_org();
?>
<section class="page-hero is-plain">
    <div class="wrap page-hero-grid">
        <div class="page-hero-copy">
            <p class="kicker">Help centre</p>
            <h1><?= e($page['og']['title']) ?></h1>
            <p class="page-lead">Short, step-by-step guides for every job in Nfelia: setting up, adding products, running your website, taking orders, and sending invoices and receipts. Each guide uses the same buttons and words you see in the app, and says what you should see after each step.</p>
        </div>
    </div>
</section>

<?php if ($start_here): ?>
<section class="page-section tint" aria-labelledby="start-title">
    <div class="wrap">
        <?= site_head('New to Nfelia? Start here', 'Three guides take you from signing up to your first invoice.', 'start-title') ?>
        <?= site_cards($start_here) ?>
    </div>
</section>
<?php endif; ?>

<div class="wrap article-wrap has-aside">
    <aside class="article-aside">
        <?php if ($groups): ?>
        <nav class="toc" aria-labelledby="toc-title">
            <h2 id="toc-title">Topics</h2>
            <ol>
                <?php foreach ($groups as [$name, $id]): ?>
                <li><a href="#<?= e($id) ?>"><?= e($name) ?></a></li>
                <?php endforeach; ?>
            </ol>
        </nav>
        <?php endif; ?>
    </aside>
    <div>
        <?php if ($groups): ?>
            <?php foreach ($groups as [$name, $id, $blurb, $articles]): ?>
            <section class="list-group" aria-labelledby="<?= e($id) ?>">
                <h2 id="<?= e($id) ?>"><?= e($name) ?></h2>
                <p class="meta" style="margin:-8px 0 12px"><?= e($blurb) ?></p>
                <ul class="link-list">
                    <?php foreach ($articles as $a): ?>
                    <li><a href="<?= e(site_url($a['key'])) ?>"><strong><?= e($headline($a)) ?></strong><span><?= e($a['summary'] ?: $a['description']) ?></span></a></li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endforeach; ?>
        <?php else: ?>
            <p>The first guides are being written. Until then, <a href="<?= e(site_wa('Hello Nfelia, I need help with Nfelia.')) ?>" target="_blank" rel="noopener">message us on WhatsApp</a> and we will walk you through it.</p>
        <?php endif; ?>
    </div>
</div>

<section class="page-section tint" aria-labelledby="contact-help-title">
    <div class="wrap split">
        <div class="split-copy">
            <h2 id="contact-help-title">Still stuck? Ask a person</h2>
            <p>Setup help is free. Tell us what you are trying to do and what you see on your screen, and we will walk you through it on WhatsApp. If you are already signed in, <strong>More › Contact support</strong> in the app reaches the same team.</p>
            <?php if (site_page('faq')): ?>
            <p>Questions about price, payment or your data? See <a href="<?= e(site_url('faq')) ?>">questions people ask</a>.</p>
            <?php endif; ?>
            <?php if (site_page('blog')): ?>
            <p>For advice on running the business itself, like invoices, collecting debts, stock and pricing, read our <a href="<?= e(site_url('blog')) ?>">guides for small businesses</a>.</p>
            <?php endif; ?>
        </div>
        <ul class="contact-list">
            <li><a href="<?= e(site_wa('Hello Nfelia, I need help with ')) ?>" target="_blank" rel="noopener"><?= nf_icon('whatsapp') ?><div><strong>Chat on WhatsApp</strong><span><?= e($org['phone_text']) ?></span></div></a></li>
            <li><a href="mailto:<?= e($org['support']) ?>"><?= nf_icon('chat') ?><div><strong>Email support</strong><span><?= e($org['support']) ?></span></div></a></li>
            <li><a href="<?= e(APP_URL . '/book') ?>"><?= nf_icon('calendar') ?><div><strong>Book a call with us</strong><span>Pick a time that suits you</span></div></a></li>
        </ul>
    </div>
</section>

<?= site_cta('Try it yourself, free') ?>
