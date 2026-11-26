<?php
/**
 * /for: Nfelia for your kind of business. One card per trade (the "for"
 * group), a line for every other trade, what every business gets, and the
 * questions people ask before they pick.
 */
require_once BASE_PATH . '/views/home/_icons.php';   // site_hero and site_ticks draw icons before the layout loads them
$prices = site_prices($page['country']);
$days   = (int)($prices['trial_days'] ?? TRIAL_DAYS);
$link   = fn(string $key, string $text) => site_page($key) ? '<a href="' . e(site_url($key)) . '">' . e($text) . '</a>' : e($text);
$trades = array_column(site_group('for'), 'key');

// The trade pages, in order, for search engines and assistants
$items = [];
foreach ($trades as $key) {
    $p = site_page($key);
    $items[] = ['@type' => 'ListItem', 'position' => count($items) + 1, 'name' => $p['crumb'], 'url' => site_url($key)];
}
if ($items) {
    site_schema_add(['@type' => 'ItemList', '@id' => site_url('for') . '#trades', 'name' => 'Nfelia for your business',
                     'numberOfItems' => count($items), 'itemListElement' => $items]);
}

$price_html = $prices
    ? 'Free for ' . $days . ' days with everything, and no card needed. Then ' . e(($prices['money'])($prices['month'])) . ' a month in Nigeria, the same for every trade.'
    : 'Message us on WhatsApp and we will tell you the price.';

echo site_hero([
    'kicker'    => 'For your business',
    'h1'        => 'Nfelia for the way your business works',
    'lead_html' => 'Nfelia is one app for your own website, invoices, receipts, debts, stock, bookings and profit. Here is how it fits six kinds of small business in Nigeria, Ghana, Kenya and Cameroon, in the words you use every day.',
    'actions'   => [['Start free', site_signup_url($page['country']), 'btn btn-primary'],
                    ['Ask if it fits my business', site_wa('Hello Nfelia, I want to know if Nfelia will work for my business. I sell / I do: '), 'btn btn-outline', 'whatsapp']],
    'note'      => "Free for {$days} days. No card needed.",
    'screens'   => [['website-1', 'Ivie Luxe Hair\'s website: bundles and wigs with photos, prices and Add buttons', 'A hair seller\'s website'],
                    ['invoice-1', 'Kemi Events & Décor\'s invoice as the client sees it: deposit paid and balance due', 'An event planner\'s invoice']],
]);
?>
<section class="page-section tint" aria-labelledby="trades-title">
    <div class="wrap">
        <?= site_head('Pick your trade', 'Each page shows the problems you meet every day and what Nfelia does about them, with an example day.', 'trades-title') ?>
        <?= site_cards($trades) ?>
        <p class="price-note">Something else? Nfelia also has website designs for skincare and perfume oils, farm produce, wholesale and provisions, shortlets, and home and professional services. See <?= $link('features', 'everything Nfelia does') ?>, or <a href="<?= e(site_wa('Hello Nfelia, I want to know if Nfelia will work for my business. I sell / I do: ')) ?>" target="_blank" rel="noopener">tell us what you sell on WhatsApp</a>.</p>
    </div>
</section>

<section class="page-section" aria-labelledby="every-title">
    <div class="wrap split">
        <div class="split-copy">
            <h2 id="every-title">What every business gets</h2>
            <p>The trade pages show different parts of the same app. Whatever you sell or do, you get all of it, at one price.</p>
            <?= site_ticks([
                ['Your own website', 'at yourname.nfelia.com, with orders, bookings and price requests'],
                ['Quotes, invoices and receipts', 'with your logo, sent on WhatsApp'],
                ['Debts', 'with a WhatsApp reminder in one tap'],
                ['Stock', 'that comes off when you sell, with a warning before you run out'],
                ['Expenses and profit', 'for any month'],
                ['Tasks and a calendar', 'with reminders on your phone'],
            ]) ?>
            <p><?= $price_html ?> <?= $link('pricing', 'See the prices') ?>.</p>
        </div>
        <div><?= site_screens([['profit-1', 'Home: ₦962,000 came in this month, ₦695,500 spent, ₦266,500 left, and what needs you today', 'Your month at a glance'],
                               ['tasks-1', 'Tasks: a job with 2 days left, payments and a delivery that are late, and deliveries due today', 'What needs you today']]) ?></div>
    </div>
    <p class="stories-note">Screens from Ivie Luxe Hair and Kemi Events &amp; Décor, example businesses.</p>
</section>

<section class="page-section tint" aria-labelledby="faq-title">
    <div class="wrap">
        <?= site_head('Questions people ask', '', 'faq-title', true) ?>
        <?= site_faq([
            ['Is Nfelia only for these six kinds of business?',
             'No. These are the trades we have written pages for. Nfelia works for any small business that sells goods or services: shops, makers, service businesses and online sellers. ' . $link('features', 'See everything Nfelia does') . '.'],
            ['I sell products and also take bookings. Can I do both?',
             'Yes. Customers can buy your products and book your services, with a deposit if you want one, on the same website. A salon can sell hair products, and a fashion designer can take bookings for fittings.'],
            ['Does Nfelia work in Ghana, Kenya and Cameroon?',
             'Nfelia is made for small businesses in Nigeria, Ghana, Kenya and Cameroon, and customers can pay into your bank account or mobile money number. See the page for ' . $link('gh', 'Ghana') . ', ' . $link('ke', 'Kenya') . ' or ' . $link('cm', 'Cameroon') . ' for what it costs there.'],
            ['How much does it cost?', $price_html . ($prices ? ' Each extra person on your team is ' . e(($prices['money'])($prices['seat'])) . ' a month.' : '')],
        ]) ?>
    </div>
</section>

<?= site_related(['features', 'pricing', 'help/getting-started', 'faq']) ?>
<?= site_cta('Try Nfelia free for your business') ?>
