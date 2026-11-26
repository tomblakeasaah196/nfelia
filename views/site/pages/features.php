<?php
/**
 * /features: everything Nfelia does, grouped the way an owner thinks about
 * the day (sell, get paid, run the business, keep records), each card
 * leading to its own page.
 */
require_once BASE_PATH . '/views/home/_icons.php';   // the building blocks draw icons before the layout loads them
$prices = site_prices($page['country']);
$days   = (int)($prices['trial_days'] ?? TRIAL_DAYS);
$link   = fn(string $key, string $text) => site_page($key) ? '<a href="' . e(site_url($key)) . '">' . e($text) . '</a>' : e($text);

$groups = [
    ['sell', 'Sell online', 'Your own website takes orders and bookings while you work. Customers see your prices, order, pay into your account and follow their order themselves.',
     ['features/website', 'features/orders', 'features/bookings']],
    ['paid', 'Get paid', 'Send a proper quote, invoice or receipt on WhatsApp. See who owes you and remind them in one tap.',
     ['features/invoices', 'features/receipts', 'features/debts']],
    ['run', 'Run your business', 'Know what is left in stock, what you spent and what you made, and what needs you today.',
     ['features/stock', 'features/profit', 'features/tasks']],
];

// The feature pages, in the order shown here, for search engines and assistants
$items = [];
foreach ([...array_merge(...array_column($groups, 3)), 'features/records-book'] as $key) {
    if ($p = site_page($key)) {
        $items[] = ['@type' => 'ListItem', 'position' => count($items) + 1, 'name' => $p['crumb'], 'url' => site_url($key)];
    }
}
if ($items) {
    site_schema_add(['@type' => 'ItemList', '@id' => site_url('features') . '#features', 'name' => 'What Nfelia does', 'numberOfItems' => count($items), 'itemListElement' => $items]);
}

echo site_hero([
    'kicker'    => 'Features',
    'h1'        => 'Everything your business needs, in one app on your phone',
    'lead_html' => 'Nfelia is an app for small businesses in Nigeria, Ghana, Kenya and Cameroon. It gives you your own website, plus invoices, receipts, debts, stock, bookings and profit. All on your phone, and all joined up.',
    'actions'   => [['Start free', site_signup_url($page['country']), 'btn btn-primary'],
                    ['Chat on WhatsApp', site_wa('Hello Nfelia, I want to know what Nfelia can do for my business.'), 'btn btn-outline', 'whatsapp']],
    'note'      => "Free for {$days} days. No card needed.",
    'screens'   => [['website-1', "Ivie Luxe Hair's website: bundles and wigs with photos, prices and Add buttons", 'Your own website'],
                    ['owed-1', 'Sales screen: ₦185,000 owed on 5 unpaid invoices, 2 of them overdue', 'Who owes you']],
]);
?>
<section class="page-section tint" aria-labelledby="facts-title">
    <div class="wrap">
        <?= site_head('Three things to know first', '', 'facts-title') ?>
        <ul class="facts">
            <li><strong>No commission</strong><span>Customers pay into your own account. Nfelia takes nothing from your sales; you pay one monthly price.</span></li>
            <li><strong>Your own website</strong><span>At yourname.nfelia.com, in one of 10 designs. Change the words, colours and photos from your phone.</span></li>
            <li><strong>Works on any phone</strong><span>Nfelia opens in the browser, with no laptop needed. Add it to your home screen and it opens like an app.</span></li>
        </ul>
    </div>
</section>

<?php foreach ($groups as $i => [$id, $h2, $lead, $keys]): ?>
<section class="page-section<?= $i % 2 ? ' tint' : '' ?>" aria-labelledby="<?= e($id) ?>-title">
    <div class="wrap">
        <?= site_head($h2, e($lead), $id . '-title') ?>
        <?= site_cards(array_values(array_filter($keys, 'site_page'))) ?>
    </div>
</section>
<?php endforeach; ?>

<section class="page-section tint" aria-labelledby="records-title">
    <div class="wrap split flip">
        <div class="split-copy">
            <p class="kicker">Your records</p>
            <h2 id="records-title">Your records, in one Excel book you can show the bank</h2>
            <p>Everything you record in Nfelia goes into your Records Book: customers, sales, money and stock, with totals and a chart of every month, in your colours. Every invoice and receipt in it carries a code anyone can check at nfelia.com/verify.</p>
            <?= site_ticks([['Free in every month you pay for.', 'At other times it costs the price of a Records Book in your country.'],
                            ['Your plain copy is always free.', 'Every record as CSV files, whatever your plan. Your records belong to you.']]) ?>
            <p><?= $link('features/records-book', 'See how the Records Book works') ?></p>
        </div>
        <div><?= site_screens([['records-1', 'Your records in Nfelia: the Records Book, free this month, with a Make my Records Book button', 'Made in one tap'],
                               ['records-2', 'nfelia.com/verify showing an invoice from Ivie Luxe Hair as genuine, with its amount and status', 'Anyone can check it']]) ?></div>
    </div>
</section>

<section class="page-section" aria-labelledby="thread-title">
    <div class="wrap split">
        <div class="split-copy">
            <h2 id="thread-title">One order, and everything follows</h2>
            <p>The parts of Nfelia are joined up, so you write things down once. When a customer orders on your website, this is what happens next.</p>
            <p>Not sure where to start? See <?= $link('for', 'Nfelia for your kind of business') ?>, or read <?= $link('help/getting-started', 'how to get started') ?>.</p>
        </div>
        <div>
            <?= site_steps([
                ['The customer is saved', 'Their name and number go into your clients, with every order they make.'],
                ['They get a tracking link', 'Received, paid, on its way, delivered. They check it themselves instead of asking you.'],
                ['The invoice and receipt are made', 'When you approve their payment. The items come off your stock.'],
                ['The delivery is signed for', 'Your customer signs on the phone when the goods arrive.'],
                ['They are asked for a review', 'Reviews come only from real orders, so people trust them.'],
                ['Your profit adds itself up', 'Money in against what you spent, for any month.'],
            ]) ?>
        </div>
    </div>
</section>

<section class="page-section rose" aria-labelledby="price-title">
    <div class="wrap">
        <?php if ($prices): ?>
        <?= site_head('One price, every feature', 'In Nigeria, Nfelia is ' . e($prices['money']($prices['month'])) . ' a month after a free ' . $days . '-day trial, with everything on this page. Each extra person on your team is '
            . e($prices['money']($prices['seat'])) . ' a month. Prices differ by country: ' . $link('pricing', 'see Nigeria’s prices')
            . (site_page('gh/pricing') ? ', ' . $link('gh/pricing', 'Ghana') : '') . (site_page('ke/pricing') ? ', ' . $link('ke/pricing', 'Kenya') : '')
            . (site_page('cm/pricing') ? ', ' . $link('cm/pricing', 'Cameroon') : '') . '.', 'price-title') ?>
        <?php else: ?>
        <?= site_head('One price, every feature', 'Every feature on this page comes in one monthly price, after a free trial. Prices differ by country: ' . $link('pricing', 'see the prices') . '.', 'price-title') ?>
        <?php endif; ?>
    </div>
</section>

<?= site_related(['pricing', 'faq', 'help'], 'Before you start') ?>
<?= site_cta('Try every feature free') ?>
