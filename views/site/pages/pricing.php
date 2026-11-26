<?php
/**
 * Pricing, for any country: /pricing (Nigeria), /gh/pricing, /ke/pricing,
 * /cm/pricing. $page['country'] says which.
 *
 * Every amount comes from site_prices() (the countries table, Ops →
 * Countries and Prices) and is written with its own money formatter. A
 * country with no active row is "opening": the page says so plainly and
 * shows no amount at all.
 *
 * What it says about paying, the trial and stopping was checked against
 * PaystackController, MoMoController and views/billing/momo_checkout.php,
 * views/settings/index.php (Billing), app/helpers/billing.php (grace days,
 * trial staff seat), app/middleware/subscription_check.php (what stays open)
 * and StorefrontController::closed().
 */
require_once BASE_PATH . '/views/home/_icons.php';

$iso      = strtoupper((string)$page['country']);
$c        = site_country($iso);
$prices   = site_prices($iso);
$open     = $prices !== null;
$money    = $open ? fn(float $n): string => e(($prices['money'])($n)) : null;
$days     = (int)($prices['trial_days'] ?? TRIAL_DAYS);
$name     = $c['name'];
$home_key = $iso === 'NG' ? 'home' : strtolower($iso);
$signup   = site_signup_url($iso);
$words    = ['NG' => 'naira', 'GH' => 'cedis', 'KE' => 'Kenyan shillings', 'CM' => 'FCFA'];
$money_word = $words[$iso] ?? $c['currency'];
$ask_wa   = site_wa('Hello Nfelia, I am in ' . $name . '. What will Nfelia cost me each month, and how do I pay?');
$ask_account = site_wa('Hello Nfelia, I am in ' . $name . '. Which Mobile Money account do I pay Nfelia into?');
$grace    = defined('BILLING_GRACE_DAYS') ? (int)BILLING_GRACE_DAYS : 3;
$trial_staff = defined('TRIAL_STAFF_SEATS') ? (int)TRIAL_STAFF_SEATS : 1;
$staff_words = $trial_staff === 1 ? 'one staff member' : $trial_staff . ' staff members';

// A closing "read more" link to another page of the site, or nothing while it is missing
$more = fn(string $key, string $text): string => site_page($key) ? ' <a href="' . e(site_url($key)) . '">' . e($text) . '</a>.' : '';

// Billing takes the payment itself (by card or Mobile Money), so its options apply
$self_serve = $open && in_array($prices['pays_by'], ['paystack', 'mobile_money'], true);

if ($open) {
    $month = (float)$prices['month'];
    $seat  = (float)$prices['seat'];
    $book  = (float)$prices['book'];
    // About a day: whole tens for big amounts, whole units or tenths for small ones
    $d       = $month / 30;
    $per_day = $d >= 100 ? floor($d / 10) * 10 : ($d >= 10 ? floor($d) : floor($d * 10) / 10);

    // The three prices, for search engines and assistants (the month is also
    // in the SoftwareApplication's offers)
    // What each one buys: the app itself, a place for one more person in
    // it, or the Records Book (an Excel workbook of the business's records)
    $offer = fn(string $id, string $label, float $amount, bool $monthly, array $item) => array_filter([
        '@type'          => 'Offer',
        '@id'            => site_url($page['key']) . '#' . $id,
        'name'           => $label,
        'price'          => number_format($amount, 2, '.', ''),
        'priceCurrency'  => $prices['currency'],
        'eligibleRegion' => ['@type' => 'Country', 'name' => $name],
        'itemOffered'    => $item,
        'priceSpecification' => $monthly ? ['@type' => 'UnitPriceSpecification', 'price' => number_format($amount, 2, '.', ''),
                                            'priceCurrency' => $prices['currency'], 'billingDuration' => 'P1M', 'unitCode' => 'MON'] : null,
    ]);
    site_schema_add([
        '@type' => 'OfferCatalog',
        '@id'   => site_url($page['key']) . '#prices',
        'name'  => 'What Nfelia costs in ' . $name,
        'url'   => site_url($page['key']),
        'itemListElement' => [
            $offer('month', 'Nfelia, one month (' . $name . ')', $month, true, ['@id' => APP_URL . '/#software']),
            $offer('seat', 'Each extra person, one month (' . $name . ')', $seat, true,
                   ['@type' => 'Service', 'name' => 'Nfelia staff seat', 'description' => 'One more person on your Nfelia account, signing in as themselves.', 'isRelatedTo' => ['@id' => APP_URL . '/#software']]),
            $offer('book', 'A Records Book outside a paid month (' . $name . ')', $book, false,
                   ['@type' => 'DigitalDocument', 'name' => 'Nfelia Records Book', 'description' => 'All of a business’s records in one Excel workbook, with a code anyone can check at ' . APP_URL . '/verify.', 'encodingFormat' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']),
        ],
    ]);
}

/* ── The top ── */
echo site_hero([
    'kicker'    => $iso === 'NG' ? 'Pricing' : 'Pricing in ' . $name,
    'h1'        => 'What Nfelia costs in ' . $name,
    'lead_html' => $open
        ? '<strong>' . $money($month) . ' a month</strong> for everything: your own website, invoices, receipts, debts, stock and bookings. '
          . 'Free for ' . $days . ' days first, with no card needed. No commission on your sales.'
        : 'Nfelia is opening in ' . e($name) . '. You can try everything free now. The monthly price in ' . e($money_word)
          . ' will be shown here, and agreed with you before you pay anything.',
    'actions'   => [
        ['Start free for ' . $days . ' days', $signup, 'btn btn-primary'],
        [$open ? 'Ask us on WhatsApp' : 'Ask about the price on WhatsApp', $ask_wa, 'btn btn-outline', 'whatsapp'],
    ],
]);

$included = [
    ['Your own website', 'at yourname.nfelia.com'],
    'No commission: customers pay you directly',
    'Unlimited invoices, quotes and receipts',
    'Debt tracker with WhatsApp reminders',
    'Bookings with deposits',
    'Stock tracking for all your products (your website shows up to ' . number_format(MAX_PRODUCTS) . ')',
    'Monthly profit report',
    'Tasks and calendar with reminders',
    'Delivery notes your customer signs',
    ['Your Records Book,', 'free in every month you pay for'],
    'Free setup help on WhatsApp',
];
?>

<section class="page-section tint" aria-labelledby="price-title">
    <div class="wrap">
        <?= site_head($open ? 'One simple price' : 'Free to try, while we open in ' . $name,
                      $open ? 'Try everything free first. Pay only if it works for you.' : 'Use everything now. You see and agree the price before you pay anything.',
                      'price-title', true) ?>
        <div class="price-card">
            <div class="price-main">
                <?php if ($open): ?>
                    <p class="price-amount"><?= $money($month) ?> <small>a month</small></p>
                    <p class="price-day">About <?= $money($per_day) ?> a day</p>
                    <p class="price-compare">Collect one <?= $money($month * 3) ?> debt with a reminder and Nfelia has paid for itself for three months.</p>
                <?php else: ?>
                    <p class="price-amount">Free <small>to try now</small></p>
                    <p class="price-day">The monthly price in <?= e($money_word) ?> comes next</p>
                    <p class="price-compare">It will be shown on this page, and agreed with you before you pay anything.</p>
                <?php endif; ?>
                <div class="price-trial">
                    <strong>Free for <?= $days ?> days</strong>
                    <span>Use everything. No card needed to start.</span>
                </div>
                <a class="btn btn-primary" href="<?= e($signup) ?>">Start free for <?= $days ?> days</a>
                <?php if (!$open): ?>
                    <a class="btn btn-outline" href="<?= e($ask_wa) ?>" target="_blank" rel="noopener"><?= nf_icon('whatsapp') ?>Ask about the price</a>
                <?php endif; ?>
            </div>
            <div class="price-includes">
                <h3>Everything included</h3>
                <?= site_ticks($included) ?>
                <p class="price-extra">
                    <?php if ($open): ?>
                        Need staff on it? <?= $money($seat) ?> a month for each extra person, up to <?= (int)MAX_SEATS ?> people in all.
                    <?php else: ?>
                        Need staff on it? You can have up to <?= (int)MAX_SEATS ?> people in all. Each extra person has a monthly price, shown here before you pay.
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
</section>

<section class="page-section" aria-labelledby="lines-title">
    <div class="wrap">
        <?= site_head('What you pay for, line by line', $open ? 'Every price in ' . e($money_word) . ', as it is today for someone joining in ' . e($name) . '.' : 'What is free now, and what will have a price in ' . e($money_word) . '.', 'lines-title', true) ?>
        <div class="compare">
            <div class="table-scroll">
                <table>
                    <caption class="sr-only">Nfelia prices in <?= e($name) ?></caption>
                    <thead>
                        <tr><th scope="col">What</th><th scope="col">In <?= e($name) ?></th></tr>
                    </thead>
                    <tbody>
                        <tr><th scope="row">Your free trial</th>
                            <td><?= $days ?> days, with everything open and <?= e($staff_words) ?> included. No card needed.</td></tr>
                        <tr><th scope="row">A month of Nfelia</th>
                            <td><?= $open ? $money($month) . ', for you and everything listed above.' : 'Shown here, and agreed with you, before you pay anything.' ?></td></tr>
                        <tr><th scope="row">Each extra person, a month</th>
                            <td><?= $open ? $money($seat) . '. ' : 'A monthly price, shown here before you pay. ' ?>Up to <?= (int)MAX_SEATS ?> people in all: you and <?= (int)MAX_SEATS - 1 ?> staff.</td></tr>
                        <tr><th scope="row">A Records Book</th>
                            <td>One free in every month you pay for<?= $open ? '. ' . $money($book) . ' at other times.' : '.' ?></td></tr>
                        <tr><th scope="row">Your plain copy of your records</th>
                            <td>Free, always: every record as CSV files.</td></tr>
                        <tr><th scope="row">Your website</th>
                            <td>Included. No designer, no setup fee.</td></tr>
                        <tr><th scope="row">Commission on your sales</th>
                            <td>None. Customers pay into your own account.</td></tr>
                        <tr><th scope="row">Setup help</th>
                            <td>Free, on WhatsApp.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<section class="page-section tint" aria-labelledby="pay-title">
    <div class="wrap split">
        <div class="split-copy">
            <h2 id="pay-title"><?= $open ? 'How you pay in ' . e($name) : 'How paying will work in ' . e($name) ?></h2>
            <?php if (!$open): ?>
                <p>Nothing is charged during your free trial, and no card is asked for. Before you pay anything, we will agree the monthly price in <?= e($money_word) ?> with you and tell you how to pay it.</p>
                <p><a class="btn btn-outline" href="<?= e($ask_wa) ?>" target="_blank" rel="noopener"><?= nf_icon('whatsapp') ?>Ask us on WhatsApp</a></p>
            <?php elseif ($prices['pays_by'] === 'paystack'): ?>
                <p>You pay online with Paystack<?= $iso === 'NG' ? ', the payment service many Nigerian businesses already use' : '' ?>. Each payment buys one month.</p>
                <?= site_steps([
                    ['Open Billing', 'In Nfelia, go to <strong>Settings</strong>, then <strong>Billing</strong>. Only the owner of the business pays.'],
                    ['Choose who the month covers', 'Tick the staff it covers and add seats if you need them. Nfelia shows the total for the month.'],
                    ['Pay on Paystack', 'Tap <strong>Pay for the month</strong> and pay on Paystack’s secure page. Your payment details are never stored on Nfelia’s servers.'],
                    ['Your month opens', 'As soon as the payment goes through. Nothing renews by itself: next month, you pay again from Billing.'],
                ]) ?>
            <?php elseif ($prices['pays_by'] === 'mobile_money'): ?>
                <p>You pay by Mobile Money from your phone, and the Nfelia team checks every payment by hand. Each payment buys one month.</p>
                <?= site_steps([
                    ['Open Billing', 'In Nfelia, go to <strong>Settings</strong>, then <strong>Billing</strong>, and tap <strong>Pay for the month</strong>. Only the owner of the business pays.'],
                    ['Send the exact amount', $prices['momo_shown']
                        ? 'Nfelia shows the exact amount in ' . e($money_word) . ' and the Mobile Money number to send it to. Send it from your phone as usual.'
                        : 'Nfelia shows the exact amount in ' . e($money_word) . '. <a href="' . e($ask_account) . '" target="_blank" rel="noopener">Message us on WhatsApp</a> for the Mobile Money account to send it to, then send it from your phone as usual.'],
                    ['Upload your receipt', 'Add a screenshot or photo of the payment (JPG, PNG, WebP or PDF, up to 5 MB) and, if you have it, the transaction reference from the SMS. Then tap <strong>I have paid: send my receipt</strong>.'],
                    ['We check it, then your month opens', 'Usually within a few hours. Your month opens as soon as the payment is approved, and we tell you in the app and by email.'],
                ]) ?>
            <?php else: ?>
                <p>Payment in <?= e($name) ?> is arranged with our team. Message us on WhatsApp and we will set up your month. Each payment buys one month.</p>
                <p><a class="btn btn-outline" href="<?= e($ask_wa) ?>" target="_blank" rel="noopener"><?= nf_icon('whatsapp') ?>Ask us on WhatsApp</a></p>
            <?php endif; ?>
        </div>
        <div class="split-copy">
            <h2>Month by month, on your terms</h2>
            <?= site_ticks(array_values(array_filter([
                ['One month at a time.', 'Each payment buys one month, and nothing is taken from you by itself.'],
                $self_serve ? ['Pay early, lose nothing.', 'Pay before your month ends and the new month starts when the current one ends.'] : null,
                $self_serve ? ['You choose who it covers.', 'When you pay, tick the staff the month is for. Anyone you leave unticked is switched off when the payment goes through.'] : null,
                $open ? null : ['No card, no surprises.', 'Nothing is charged while you try it, and the price is agreed with you first.'],
                $open && $prices['pays_by'] === 'mobile_money' ? ['One payment at a time.', 'While we check a Mobile Money payment, you cannot send another one.'] : null,
                $open ? ['Price changes come with notice.', 'If a price changes, Billing shows the new one and the day it starts, and a month that starts before that day keeps today’s price.'] : null,
            ]))) ?>
        </div>
    </div>
</section>

<section class="page-section" aria-labelledby="after-title">
    <div class="wrap">
        <?= site_head('After the free trial, and if you stop paying', 'Your records are yours, paid up or not. If you stop, we keep them for 12 months.', 'after-title') ?>
        <div class="split">
            <div class="split-copy">
                <h3>When your <?= $days ?> days end</h3>
                <p>You decide. Pay for a month from Billing to keep making invoices and receipts and to keep your website open.</p>
                <?= site_ticks([
                    ['Your website goes on Google once you pay.', 'During the trial it works for anyone with the link, but it stays off Google.'],
                    ['Everything you added stays.', 'Customers, products, invoices and stock carry on into your first paid month.'],
                ]) ?>
            </div>
            <div class="split-copy">
                <h3>If you stop paying</h3>
                <?= site_ticks([
                    ['Your records are kept for 12 months.', 'Your customers, invoices, receipts and stock stay safe, and we email you before anything is deleted.'],
                    [$grace . ' days to pay.', 'When a paid month ends, everything stays open ' . $grace . ' more days.'],
                    ['Then Nfelia pauses.', 'You can still open Your records and download your plain copy.'],
                    ['Your website shows “closed for now”,', 'with your WhatsApp button, so customers can still reach you. Tracking links you already sent keep working.'],
                    ['Pay for a month within those 12 months', 'and everything opens again, as you left it.'],
                ]) ?>
            </div>
        </div>
    </div>
</section>

<section class="page-section rose" aria-labelledby="worth-title">
    <div class="wrap">
        <?php if ($open): ?>
            <?= site_head('Is it worth it?', 'Count what one paid debt or one extra order is worth to you.', 'worth-title', true) ?>
            <ul class="facts">
                <li><strong><?= $money($per_day) ?></strong><span>About what Nfelia costs you a day.</span></li>
                <li><strong><?= $money($month * 3) ?></strong><span>One debt collected with a reminder, and three months are paid for.</span></li>
                <li><strong>No commission</strong><span>Customers pay into your own account, so every sale on your website is yours.</span></li>
            </ul>
        <?php else: ?>
            <?= site_head('Is it worth it? Try it and see', 'Use it with your real customers for ' . $days . ' days. If it does not help your business, you pay nothing.', 'worth-title', true) ?>
            <ul class="facts">
                <li><strong><?= $days ?> days</strong><span>Free, with everything open and no card needed.</span></li>
                <li><strong>No commission</strong><span>Customers pay into your own account, so every sale on your website is yours.</span></li>
                <li><strong>Price first</strong><span>The monthly price in <?= e($money_word) ?> is agreed with you before you pay anything.</span></li>
            </ul>
        <?php endif; ?>
        <p class="price-note">And the time you get back: no more copying orders from DMs, no more scrolling for transfer screenshots, no more “send me your price list”.</p>
    </div>
</section>

<?php
/* ── Questions ── */
$faq = [];
$faq[] = ['Is there a free trial?',
    'Yes. You get ' . $days . ' days free with everything open, and your trial includes ' . e($staff_words) . '. No card is needed to start.'];
if (!$open) {
    $faq[] = ['When will the price in ' . $money_word . ' be ready?',
        'Nfelia is opening in ' . e($name) . ' now. The monthly price in ' . e($money_word) . ' will be shown on this page, and agreed with you before you pay anything. <a href="' . e($ask_wa) . '" target="_blank" rel="noopener">Ask us on WhatsApp</a> and we will tell you where it stands.'];
    $faq[] = ['Can I sign up from ' . $name . ' today?',
        'Yes, the free trial works now. When you set up, Nfelia asks for your country, because it sets your money and your Nfelia price. If ' . e($name) . ' is not in that list yet, <a href="' . e(site_wa('Hello Nfelia, I am in ' . $name . ' and I want to start my free trial.')) . '" target="_blank" rel="noopener">message us on WhatsApp</a> before you go on, and we will help you set up.'];
}
$faq[] = ['Do you take a cut of my sales?',
    'No. Customers in ' . e($name) . ' pay you by ' . e($c['customers_pay']) . ', straight into your own account, and the money is yours. Nfelia takes no commission; you only pay for Nfelia itself.'];
if ($open) {
    $faq[] = ['How do I pay for Nfelia in ' . $name . '?', match ($prices['pays_by']) {
        'paystack'     => 'Online with Paystack, from Settings, then Billing in Nfelia. Each payment buys one month, and the month opens as soon as the payment goes through.',
        'mobile_money' => 'By Mobile Money, from Settings, then Billing in Nfelia. Nfelia shows the exact amount'
                          . ($prices['momo_shown'] ? ' and the number to send it to' : ', and you <a href="' . e($ask_account) . '" target="_blank" rel="noopener">message us on WhatsApp</a> for the account to send it to')
                          . '; you upload a screenshot of the payment, and the Nfelia team checks it by hand, usually within a few hours. Your month opens once it is approved.',
        default        => 'Payment in ' . e($name) . ' is arranged with our team. <a href="' . e($ask_wa) . '" target="_blank" rel="noopener">Message us on WhatsApp</a> and we will set up your month.',
    }];
    $faq[] = ['Does it renew by itself?',
        'No. Each payment buys one month and nothing is taken from you by itself. When a paid month ends, you have ' . $grace . ' more days to pay for the next one before Nfelia pauses.'];
    $faq[] = ['Can I get a refund?',
        'The free trial is there so you can try Nfelia before you pay, so a month that has started is not refunded. A payment taken by mistake, such as being charged twice, is always refunded.' . $more('terms', 'Read the refund terms')];
}
$faq[] = ['What happens if I stop paying?',
    'Nfelia pauses, and your website shows a “closed for now” page with your WhatsApp button. You can still download your records, and when you pay for a month everything opens again, as you left it. We keep your records for 12 months after your last paid month, and email you before anything is deleted.'];
$faq[] = ['How much is it for my staff?', $open
    ? e($prices['money']($seat)) . ' a month for each extra person, up to ' . (int)MAX_SEATS . ' people in all (you and ' . ((int)MAX_SEATS - 1) . ' staff). When you pay, you choose who the month covers. Need more people? Email <a href="mailto:' . e(site_org()['support']) . '">' . e(site_org()['support']) . '</a>.'
    : 'Your free trial includes ' . e($staff_words) . '. After that, each extra person has a monthly price in ' . e($money_word) . ', shown here before you pay, up to ' . (int)MAX_SEATS . ' people in all.'];
$faq[] = ['What is the Records Book, and what does it cost?',
    'All your records in one Excel workbook, in your colours, with a code anyone can check at nfelia.com/verify. One is free in every month you pay for'
    . ($open ? '; at other times it costs ' . e($prices['money']($book)) : '')
    . '. Your plain copy, every record as CSV files, is always free.' . $more('features/records-book', 'More about the Records Book')];
$faq[] = ['Is the website extra?',
    'No. Your website at yourname.nfelia.com comes with Nfelia at no extra cost. During the free trial it works for anyone with the link, and it goes on Google once you pay.' . $more('features/website', 'See what your website does')];
?>

<section class="page-section tint" aria-labelledby="faq-title">
    <div class="wrap">
        <?= site_head('Questions about the price', '', 'faq-title', true) ?>
        <?= site_faq($faq) ?>
    </div>
</section>

<?php
// The same page for the other countries, so people (and search engines) find theirs
$others = [];
foreach (site_countries() as $other) {
    if ($other['iso'] === $iso) continue;
    $key = $other['prefix'] === '' ? 'pricing' : strtolower($other['iso']) . '/pricing';
    if (site_page($key)) {
        $others[] = [$other, $key];
    }
}
if ($others): ?>
<section class="page-section" aria-labelledby="elsewhere-title">
    <div class="wrap">
        <div class="list-group">
            <h2 id="elsewhere-title">Pricing in other countries</h2>
            <ul class="link-list">
                <?php foreach ($others as [$other, $key]): ?>
                <li><a href="<?= e(site_url($key)) ?>" hreflang="<?= e($other['hreflang']) ?>"><strong>Nfelia in <?= e($other['name']) ?></strong><span><?= site_prices($other['iso']) ? 'What it costs, in ' . e($words[$other['iso']] ?? $other['currency']) . '.' : 'Opening now: try it free.' ?></span></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</section>
<?php endif; ?>

<?= site_related(array_values(array_filter([$iso === 'NG' ? null : $home_key, 'features', 'help/billing', 'features/records-book', 'faq']))) ?>
<?= site_cta($open ? 'Try everything free, then decide' : 'Try Nfelia free in ' . $name) ?>
