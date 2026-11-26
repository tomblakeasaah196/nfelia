<?php
/**
 * Nfelia in Ghana, Kenya and Cameroon: /gh, /ke, /cm. $page['country'] says
 * which; the words for each are in $countries below, so every page is
 * written for its own country, not a name swapped in.
 *
 * Checked against the app:
 *   - how customers pay a shop: Settings → Payment accounts
 *     (views/settings/payment_accounts.php, SettingsController::save_account):
 *     a bank account or a Mobile Money number (6 to 15 digits), which one in
 *     words, and the name on it. The shop shows them at checkout
 *     (views/storefront/parts/bag.php) with an optional screenshot of the
 *     payment; Nfelia is not connected to any network and never holds money.
 *   - money: a business's currency follows its country (onboarding step 1, or
 *     More → Country and currency, api/ajax/set_country.php), and only
 *     countries with an active row can be chosen. No active row is what
 *     site_prices() returns null for, so the page says "opening".
 *   - a shop's WhatsApp buttons put a local number (024…, 0712…, 6 70…) into
 *     international form with the shop's own country code
 *     (shop_whatsapp_digits → wa_digits); typing the code is still the
 *     safest, hence the tip.
 * The screens are from example businesses in Nigeria, and the page says so.
 */
require_once BASE_PATH . '/views/home/_icons.php';

$iso     = strtoupper((string)$page['country']);
$c       = site_country($iso);
$prices  = site_prices($iso);
$open    = $prices !== null;
$money   = $open ? fn(float $n): string => e(($prices['money'])($n)) : null;
$days    = (int)($prices['trial_days'] ?? TRIAL_DAYS);
$name    = $c['name'];
$cities  = $c['cities'];
$pkey    = strtolower($iso) . '/pricing';
$signup  = site_signup_url($iso);
$wa_help = site_wa('Hello Nfelia, I am in ' . $name . ' and I want to start my free trial. Please help me set up.');

// A link to another page of the site when it exists, its words otherwise;
// $more: a closing "read more" link, or nothing while that page is missing
$link = fn(string $key, string $text): string => site_page($key) ? '<a href="' . e(site_url($key)) . '">' . e($text) . '</a>' : e($text);
$more = fn(string $key, string $text): string => site_page($key) ? ' <a href="' . e(site_url($key)) . '">' . e($text) . '</a>.' : '';

/* ──────────────────────────────────────────
 *  EACH COUNTRY'S OWN WORDS
 * ────────────────────────────────────────── */
$countries = [
    'GH' => [
        'h1'      => 'Your shop online and your books in order, in Ghana',
        'lead'    => 'Nfelia gives your business its own website at yourname.nfelia.com, plus invoices, receipts, debts and stock, all on your phone.',
        'shots'   => [['website-1', 'Ivie Luxe Hair’s website, an example business in Nigeria: bundles and wigs with photos, prices and Add buttons', 'Your own website'],
                      ['owed-1', 'The Sales screen of an example business in Nigeria: ₦185,000 owed on 5 unpaid invoices, 2 of them overdue', 'Who owes you']],
        'who_lead'=> 'Whether you trade at Makola or Kejetia, run a salon in Tema or sell from your Instagram page in Takoradi, Nfelia keeps your customers, prices and money in one place.',
        'who'     => [
            'for/fashion-designers' => ['Seamstresses and fashion designers', 'Kaba and slit, kente for weddings, uniforms in bulk: send a quote, take a deposit, collect the balance at pickup.'],
            'for/salons'            => ['Salons, braiders and barbers', 'Clients pick a free time on your website and pay the deposit before they come.'],
            'for/food-and-catering' => ['Caterers and food sellers', 'Quotes for weddings, funerals and outdoorings, the deposit, and what you spent at the market.'],
            'for/phones-and-gadgets'=> ['Phones and accessories', 'Stock for every model, receipts with your logo, and who still owes you for that phone.'],
            'for/events'            => ['Events and décor', 'A proper quote, a deposit, and the balance tracked till the day.'],
            'for/online-sellers'    => ['Instagram, TikTok and WhatsApp sellers', 'One link for your bio with every price, so you stop sending price lists in DMs.'],
        ],
        'momo'    => 'MTN MoMo, Telecel Cash or AT Money',
        'momo_eg' => 'MTN MoMo',
        'check'   => 'MoMo messages',
        'dial'    => '+233',
        'dial_eg' => 'so 024… becomes +233 24…',
        'features'=> ['features/website', 'features/orders', 'features/invoices', 'features/debts', 'features/stock', 'features/records-book'],
        'faq'     => [],
    ],
    'KE' => [
        'h1'      => 'An online shop and proper records for your business in Kenya',
        'lead'    => 'Nfelia gives you an online shop at yourname.nfelia.com, plus invoices, receipts, a debt tracker and stock, all on your phone.',
        'shots'   => [['website-1', 'Ivie Luxe Hair’s website, an example business in Nigeria: bundles and wigs with photos, prices and Add buttons', 'Your online shop'],
                      ['paid-1', 'An order from an example business in Nigeria, waiting for a payment check, with the customer’s payment screenshot attached', 'Payment proof with the order']],
        'who_lead'=> 'From a boutique in Nairobi’s CBD to a mitumba stall at Gikomba, a salon in Nakuru or an Instagram shop in Mombasa, Nfelia keeps your customers, prices and money in one place.',
        'who'     => [
            'for/fashion-designers' => ['Tailors, boutiques and mitumba sellers', 'Quote a suit or a dress, take a deposit, collect the balance at pickup. Every piece you sell comes off your stock.'],
            'for/salons'            => ['Salons, barbers and nail studios', 'Clients pick a free time on your website and pay the deposit before they come.'],
            'for/food-and-catering' => ['Caterers, bakers and food sellers', 'Quotes for weddings, birthdays and office events, the deposit, and what you spent on supplies.'],
            'for/phones-and-gadgets'=> ['Phones and electronics', 'Stock for every model, receipts with your logo, and who still owes you for that phone.'],
            'for/events'            => ['Events, tents and décor', 'A proper quote, a deposit, and the balance tracked till the day.'],
            'for/online-sellers'    => ['Instagram, TikTok and WhatsApp sellers', 'One link for your bio with every price, so people stop asking “how much?” in DMs.'],
        ],
        'momo'    => 'M-Pesa or Airtel Money',
        'momo_eg' => 'M-Pesa Till',
        'check'   => 'M-Pesa messages',
        'dial'    => '+254',
        'dial_eg' => 'so 0712… becomes +254 712…',
        'features'=> ['features/website', 'features/orders', 'features/receipts', 'features/debts', 'features/stock', 'features/bookings'],
        'faq'     => [
            ['Can I use a Paybill number?',
             'You can add it, but each account holds one number, with no separate box for the account number a Paybill also needs. Write that account number in the name on the account, so customers see it at checkout. A Till number, or the M-Pesa line customers send to, is simpler.'],
        ],
    ],
    'CM' => [
        'h1'      => 'Sell online and know where every franc goes, in Cameroon',
        'lead'    => 'Nfelia gives your business its own website at yourname.nfelia.com, plus invoices, receipts, debts and stock, all on your phone.',
        'shots'   => [['website-1', 'Ivie Luxe Hair’s website, an example business in Nigeria: bundles and wigs with photos, prices and Add buttons', 'Your own website'],
                      ['invoice-1', 'Kemi Events & Décor’s invoice, an example business in Nigeria, as the client sees it: deposit paid and balance due', 'Deposit and balance']],
        'who_lead'=> 'Whether you sell at Marché Central in Douala or Mokolo in Yaoundé, sew in Bamenda or bake in Buea, Nfelia keeps your customers, prices and money in one place.',
        'who'     => [
            'for/fashion-designers' => ['Tailors and fashion designers', 'Kaba ngondo, pagne outfits and uniforms: send a quote, take a deposit, collect the balance at pickup.'],
            'for/salons'            => ['Hair salons and beauty', 'Clients pick a free time on your website and pay the deposit before they come.'],
            'for/food-and-catering' => ['Caterers, bakers and snack bars', 'Quotes for weddings, born houses and office parties, the deposit, and what you spent at the market.'],
            'for/phones-and-gadgets'=> ['Phones and accessories', 'Stock for every model, receipts with your logo, and who still owes you for that phone.'],
            'for/events'            => ['Events and décor', 'A proper quote, a deposit, and the balance tracked till the day.'],
            'for/online-sellers'    => ['Facebook, Instagram and WhatsApp sellers', 'One link with every price, so people stop asking “how much?” in your inbox.'],
        ],
        'momo'    => 'MTN MoMo or Orange Money',
        'momo_eg' => 'MTN MoMo',
        'check'   => 'MoMo messages',
        'dial'    => '+237',
        'dial_eg' => 'so 6 70… becomes +237 6 70…',
        'features'=> ['features/website', 'features/invoices', 'features/debts', 'features/stock', 'features/profit', 'features/records-book'],
        'faq'     => [
            ['Is Nfelia in French?',
             'Not yet. Nfelia’s screens, and the buttons on your website, are in English for now. What you write yourself, like product names, descriptions and your About us, can be in French.'],
        ],
    ],
];
$w = $countries[$iso] ?? $countries['GH'];

/* ── The top ── */
echo site_hero([
    'kicker'    => 'Nfelia in ' . $name,
    'h1'        => $w['h1'],
    'lead_html' => e($w['lead']) . ' For sellers and service businesses in ' . e(implode(', ', array_slice($cities, 0, 3))) . ' and beyond.',
    'note'      => $open ? 'Free for ' . $days . ' days. No card needed.' : 'Opening in ' . $name . ': the free trial works now.',
    'screens'   => [[$w['shots'][0][0], $w['shots'][0][1], $w['shots'][0][2] . ' (example)'],
                    [$w['shots'][1][0], $w['shots'][1][1], $w['shots'][1][2] . ' (example)']],
]);

// Who it is for: the "for" pages, in this country's words
$who = [];
foreach ($w['who'] as $key => [$title, $text]) {
    $p = site_page($key);
    $who[] = [$title, $text, $p ? site_url($key) : (site_page('for') ? site_url('for') : (site_page('features') ? site_url('features') : $signup)), $p['icon'] ?? null];
}
?>

<section class="page-section tint" aria-labelledby="who-title">
    <div class="wrap">
        <?= site_head('Who Nfelia is for in ' . $name, e($w['who_lead']), 'who-title') ?>
        <?= site_cards($who) ?>
    </div>
</section>

<section class="page-section" aria-labelledby="pay-title">
    <div class="wrap split">
        <div class="split-copy">
            <h2 id="pay-title">How your customers pay you in <?= e($name) ?></h2>
            <p>Customers pay you directly, the way they already do: <?= e($c['customers_pay']) ?>. The money goes straight to you. Nfelia never holds it and takes no commission.</p>
            <?= site_ticks([
                ['Add your accounts once.', 'In Settings, under Payment accounts, choose Mobile Money or Bank account. Write which one (like ' . $w['momo_eg'] . '), the number, and the name customers will see.'],
                ['Your website shows them at checkout.', 'The customer picks one, sends the money and can add a screenshot of the payment to the order.'],
                ['You check, then confirm.', 'Check your ' . $w['check'] . ', then confirm the order in Nfelia. It makes the invoice, and the receipt once you say the money came in.'],
                ['On invoices too.', 'Put one of your accounts on each invoice and quote, so your customer knows where to pay.'],
            ]) ?>
            <?php $order_links = array_filter([site_page('features/orders') ? $link('features/orders', 'How online orders work') : null,
                                               site_page('help/website-orders') ? $link('help/website-orders', 'Set up your website orders') : null]); ?>
            <?php if ($order_links): ?><p><?= implode(' · ', $order_links) ?></p><?php endif; ?>
        </div>
        <div>
            <?= site_screens([
                ['website-2', 'Checkout on the website of an example business in Nigeria: pay ₦65,000 by bank transfer to the shop’s own account', 'Checkout (example)'],
                ['paid-2', 'Receipts in an example business in Nigeria: ₦962,000 received this month, each payment listed by customer', 'Every receipt (example)'],
            ]) ?>
        </div>
    </div>
    <div class="wrap">
        <p class="price-note">The screens on this page come from example businesses in Nigeria, so amounts show in naira (₦).<?= $open ? ' Set up in ' . e($name) . ', yours show ' . e($c['symbol']) . '.' : '' ?></p>
    </div>
</section>

<section class="page-section tint" aria-labelledby="money-title">
    <div class="wrap split">
        <div class="split-copy">
            <?php if ($open): ?>
                <h2 id="money-title">Your money, in <?= e($c['symbol']) ?></h2>
                <p>Choose <?= e($name) ?> when you set up, and your invoices, receipts, debts and website show money in <?= e($c['symbol']) ?> (<?= e($prices['currency']) ?>). What you pay for Nfelia is in <?= e($c['symbol']) ?> too.</p>
                <p>Moved, or set the wrong country? The owner changes it under <strong>More</strong>, then <strong>Country and currency</strong>.</p>
            <?php else: ?>
                <h2 id="money-title">Your money on Nfelia</h2>
                <p>The money on your invoices, receipts and website follows the country you choose when you set up. <?= e($name) ?> is not in that list yet, because Nfelia is still opening here.</p>
                <p>Message us on WhatsApp before you start, and we will help you set up.</p>
                <p><a class="btn btn-outline" href="<?= e($wa_help) ?>" target="_blank" rel="noopener"><?= nf_icon('whatsapp') ?>Ask us on WhatsApp</a></p>
            <?php endif; ?>
        </div>
        <div class="split-copy">
            <h2>On any phone, light on data</h2>
            <p>No laptop needed. Nfelia opens in the browser on any smartphone, and you can add it to your home screen so it opens like an app, with no app store download. It needs a little data, like WhatsApp does.</p>
            <?= site_ticks([
                ['Your WhatsApp, the right way.', 'In Settings, under Business details, type your WhatsApp number with ' . $w['dial'] . ' in front (' . $w['dial_eg'] . '). Then the WhatsApp buttons on your website open your chat.'],
                ['Your staff on their own phones.', 'Each person gets their own login. Only you change the payment accounts and the team.'],
            ]) ?>
        </div>
    </div>
</section>

<?php $features = array_values(array_filter($w['features'], fn($k) => site_page($k) !== null)); ?>
<?php if ($features): ?>
<section class="page-section" aria-labelledby="features-title">
    <div class="wrap">
        <?= site_head('Everything in one app', 'Your website, your paperwork and your money, joined up.' . $more('features', 'See every feature'), 'features-title') ?>
        <?= site_cards($features) ?>
    </div>
</section>
<?php endif; ?>

<section class="page-section rose" aria-labelledby="cost-title">
    <div class="wrap">
        <?= site_head('What Nfelia costs in ' . $name, '', 'cost-title', true) ?>
        <div class="price-card">
            <div class="price-main">
                <?php if ($open): ?>
                    <p class="price-amount"><?= $money((float)$prices['month']) ?> <small>a month</small></p>
                    <p class="price-day">Everything included. No commission on your sales.</p>
                    <p class="price-compare">Each extra person: <?= $money((float)$prices['seat']) ?> a month, up to <?= (int)MAX_SEATS ?> people in all.</p>
                <?php else: ?>
                    <p class="price-amount">Free <small>to try now</small></p>
                    <p class="price-day">Nfelia is opening in <?= e($name) ?>.</p>
                    <p class="price-compare">The monthly price in <?= e($c['symbol']) ?> will be shown on our <?= e($name) ?> pricing page, and agreed with you before you pay anything.</p>
                <?php endif; ?>
                <div class="price-trial">
                    <strong>Free for <?= $days ?> days</strong>
                    <span>Use everything. No card needed to start.</span>
                </div>
                <?php if (site_page($pkey)): ?>
                    <a class="btn btn-primary" href="<?= e(site_url($pkey)) ?>">See pricing in <?= e($name) ?></a>
                <?php else: ?>
                    <a class="btn btn-primary" href="<?= e($signup) ?>">Start free for <?= $days ?> days</a>
                <?php endif; ?>
            </div>
            <div class="price-includes">
                <h3>Everything included</h3>
                <?= site_ticks([
                    ['Your own website', 'at yourname.nfelia.com'],
                    'Unlimited invoices, quotes and receipts',
                    'Debt tracker with WhatsApp reminders',
                    'Bookings with deposits',
                    'Stock tracking for all your products (your website shows up to ' . number_format(MAX_PRODUCTS) . ')',
                    'Monthly profit report',
                    ['Your Records Book,', 'free in every month you pay for'],
                    'Free setup help on WhatsApp',
                ]) ?>
            </div>
        </div>
    </div>
</section>

<?php
/* ── Questions ── */
$faq = [];
if ($open) {
    $faq[] = ['How do I pay for Nfelia in ' . $name . '?', match ($prices['pays_by']) {
        'paystack'     => 'Online with Paystack, from Settings, then Billing in Nfelia. Each payment buys one month, and nothing renews by itself.' . $more($pkey, 'See pricing in ' . $name),
        'mobile_money' => 'By Mobile Money, from Settings, then Billing in Nfelia. You send the exact amount '
                          . ($prices['momo_shown'] ? 'to the number Nfelia shows' : 'to the account we give you when you <a href="' . e(site_wa('Hello Nfelia, I am in ' . $name . '. Which Mobile Money account do I pay Nfelia into?')) . '" target="_blank" rel="noopener">message us on WhatsApp</a>')
                          . ' and upload a screenshot of the payment; the Nfelia team checks it by hand, usually within a few hours.' . $more($pkey, 'See pricing in ' . $name),
        default        => 'Payment in ' . e($name) . ' is arranged with our team: <a href="' . e(site_wa('Hello Nfelia, I am in ' . $name . '. How do I pay for Nfelia?')) . '" target="_blank" rel="noopener">message us on WhatsApp</a>.' . $more($pkey, 'See pricing in ' . $name),
    }];
} else {
    $faq[] = ['Is Nfelia available in ' . $name . ' now?',
        'Nfelia is opening in ' . e($name) . '. The free trial works now: <a href="' . e($wa_help) . '" target="_blank" rel="noopener">message us on WhatsApp</a> and we will help you set up. The monthly price in ' . e($c['symbol']) . ' will be shown on our ' . $link($pkey, $name . ' pricing page') . ', and agreed with you before you pay anything.'];
}
$faq[] = ['Can my customers pay me with ' . $w['momo'] . '?',
    'Yes. Add your number in Settings, under Payment accounts: choose Mobile Money, say which one, and type the number and the name customers will see. Your website shows it at checkout, and you can put it on invoices too. Add a bank account as well if customers pay you by transfer. The money goes straight to you; Nfelia never holds it.'];
foreach ($w['faq'] as $qa) {
    $faq[] = $qa;
}
$faq[] = ['Does Nfelia confirm payments by itself?',
    'No. Nfelia is not connected to ' . e($w['momo']) . ', or to any bank. Your customer pays the usual way and can add a screenshot of the payment to the order. You check your ' . e($w['check']) . ', then confirm the order in Nfelia: it makes the invoice, and the receipt once you say the money came in.'];
$faq[] = ['My WhatsApp number starts with 0. What do I type?',
    'Typing it with ' . e($w['dial']) . ' in front and without the first 0 is safest (' . e($w['dial_eg']) . '), and Nfelia adds the code by itself to a number that starts with 0. Put it in Settings, under Business details, WhatsApp. The WhatsApp buttons on your website then open your chat.'];
if ($iso === 'CM') {
    // A Cameroonian mobile number has no leading 0
    $faq[count($faq) - 1] = ['What do I type for my WhatsApp number?',
        'Typing it with ' . e($w['dial']) . ' in front is safest (' . e($w['dial_eg']) . '), and Nfelia adds the code by itself if you leave it out. Put it in Settings, under Business details, WhatsApp. The WhatsApp buttons on your website then open your chat.'];
}
$faq[] = ['Will my website show on Google?',
    'Yes, once you pay for a month. During the free trial your website works for anyone with the link, but it stays off Google.' . $more('help/get-found-on-google', 'How to get found on Google')];
?>

<section class="page-section tint" aria-labelledby="faq-title">
    <div class="wrap">
        <?= site_head('Questions about Nfelia in ' . $name, '', 'faq-title', true) ?>
        <?= site_faq($faq) ?>
    </div>
</section>

<?= site_related(array_values(array_filter([$pkey, 'features', 'for', 'help/getting-started', 'help/website-orders', 'blog/sell-on-whatsapp-and-instagram'], fn($k) => site_page($k) !== null))) ?>
<?= site_cta($open ? 'Start free in ' . $name . ' today' : 'Try Nfelia free in ' . $name) ?>
