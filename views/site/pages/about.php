<?php
/**
 * /about: what Nfelia is, why it exists, where it works and how we work.
 * No people and no history here: Nfelia is a product of JBS Praxis.
 */
require_once BASE_PATH . '/views/home/_icons.php';   // the building blocks draw icons before the layout loads them
$org    = site_org();
$prices = site_prices('NG');
$days   = (int)($prices['trial_days'] ?? TRIAL_DAYS);
$link   = fn(string $key, string $text) => site_page($key) ? '<a href="' . e(site_url($key)) . '">' . e($text) . '</a>' : e($text);

echo site_hero([
    'kicker'    => 'About Nfelia',
    'h1'        => 'Small businesses deserve proper tools too',
    'lead_html' => 'Nfelia is an app that gives small businesses their own website, plus invoices, receipts, debts, stock, bookings and profit, all on the phone. It is a product of ' . e($org['legal']) . '.',
    'actions'   => [['See what Nfelia does', site_page('features') ? site_url('features') : APP_URL . '/', 'btn btn-primary'],
                    ['Talk to us', site_page('contact') ? site_url('contact') : site_wa(), 'btn btn-outline']],
]);
?>
<section class="page-section tint" aria-labelledby="why-title">
    <div class="wrap split">
        <div class="split-copy">
            <h2 id="why-title">Why Nfelia exists</h2>
            <p>Many small businesses run on WhatsApp chats, a notebook and memory. Orders come in through DMs. Debts live on a page with names crossed out. The transfer screenshot is somewhere in a chat. Stock is counted by eye.</p>
            <p>It works until it doesn’t. A customer says she paid and you can’t find the proof. A debt is forgotten. A big client wants a proper invoice. The bank asks for your records and you have none to show.</p>
            <p>Nfelia is for that business. It keeps everything in one place on the phone you already have, in words you already use.</p>
        </div>
        <div>
            <article class="story">
                <img class="story-photo" src="<?= APP_URL ?>/assets/img/home/story-ivie.webp" width="720" height="450" loading="lazy" decoding="async" alt="Ivie smiling at her phone in her hair shop">
                <div class="story-body">
                    <div class="story-who"><h3>Ivie Luxe Hair</h3><p>Sells hair at Lekki Town Square. An example business.</p></div>
                    <div class="before-after">
                        <div class="before"><b>Before</b>Regular customers took bundles on credit. Their names lived in a notebook.</div>
                        <div class="after"><b>With Nfelia</b>She sees ₦185,000 owed by five customers and reminds them on WhatsApp in seconds.</div>
                    </div>
                </div>
            </article>
            <p class="stories-note">An example story to show how Nfelia fits a business. It is not a customer review.</p>
        </div>
    </div>
</section>

<section class="page-section" aria-labelledby="thread-title">
    <div class="wrap split flip">
        <div class="split-copy">
            <h2 id="thread-title">The thread that holds your business together</h2>
            <p>A business is one long thread: a customer asks, orders, pays, receives and comes back. Most tools cut that thread into pieces, so you copy the same name and amount from one place to the next.</p>
            <p>Nfelia keeps it whole. The order makes the invoice. The payment makes the receipt. The sale takes the item off your stock. The money adds up in your profit. Each record is joined to the next, so nothing falls through.</p>
            <p><?= $link('features', 'See everything Nfelia does') ?></p>
        </div>
        <div>
            <?= site_screens([['profit-1', 'Home: ₦962,000 came in this month, ₦695,500 spent and ₦266,500 left, with what needs you today', 'Your month at a glance'],
                              ['track-1', 'Tracking page for order IVL-ORD-018: received, payment confirmed, being prepared, on its way', 'Every order, followed through']]) ?>
        </div>
    </div>
</section>

<section class="page-section tint" aria-labelledby="who-title">
    <div class="wrap">
        <?= site_head('Who Nfelia is for', 'Small businesses that sell things or serve people: market traders, Instagram and WhatsApp sellers, fashion designers, salons, caterers, phone shops and event planners. If you can use WhatsApp, you can use Nfelia.', 'who-title') ?>
        <?php $for = array_values(array_filter(['for/fashion-designers', 'for/salons', 'for/food-and-catering', 'for/phones-and-gadgets', 'for/events', 'for/online-sellers'], 'site_page')); ?>
        <?= $for ? site_cards($for) : '' ?>
    </div>
</section>

<section class="page-section" aria-labelledby="where-title">
    <div class="wrap">
        <?= site_head('Where Nfelia works', 'Nfelia is for businesses in four countries. The site and the app are in English.', 'where-title') ?>
        <div class="table-scroll compare about-countries">
            <table>
                <caption class="sr-only">Countries where Nfelia works, how customers pay businesses there, and whether sign-ups are open</caption>
                <thead><tr><th scope="col">Country</th><th scope="col">How your customers pay you</th><th scope="col">Sign-ups</th></tr></thead>
                <tbody>
                <?php foreach (site_countries() as $c):
                    $home = $c['prefix'] === '' ? site_page('home') : site_page(strtolower($c['iso']));
                    $open = site_prices($c['iso']) !== null; ?>
                    <tr>
                        <th scope="row"><?= $home ? '<a href="' . e(site_url($home['key'])) . '" hreflang="' . e($c['hreflang']) . '">' . e('Nfelia in ' . $c['name']) . '</a>' : e($c['name']) ?></th>
                        <td><?= e(ucfirst($c['customers_pay'])) ?>, straight into your own account</td>
                        <td><?= $open ? 'Open. <a href="' . e(site_signup_url($c['iso'])) . '">Start free in ' . e($c['name']) . '</a>' : 'Not open yet. <a href="' . e(site_wa('Hello Nfelia, I am in ' . $c['name'] . '. Please tell me when I can sign up.')) . '" target="_blank" rel="noopener">Ask us on WhatsApp</a>' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="page-section rose" aria-labelledby="how-title">
    <div class="wrap split">
        <div class="split-copy">
            <h2 id="how-title">How we work</h2>
            <p>A few promises we keep, whatever you pay and wherever you are.</p>
            <p><?= $link('privacy', 'Read our Privacy Policy') ?> and <?= $link('terms', 'our Terms of Service') ?>.</p>
        </div>
        <div class="split-copy">
            <?= site_ticks([
                ['Your records belong to you.', 'Every month you pay for comes with your Records Book, one Excel workbook of everything. Your plain copy, every record as CSV files, is free whenever you ask, whatever your plan.'],
                ['No commission.', 'Customers pay into your own account. Nfelia takes nothing from your sales.'],
                ['We never sell your data or market to your customers.', 'Your customers are yours. The only emails Nfelia sends them are about their own orders, bookings and the papers you make for them, in your shop’s name.'],
                ['Help on WhatsApp.', 'Free setup help, and a real person to ask when you get stuck.'],
                ['Try it before you pay.', "Use everything free for {$days} days. No card needed to start."],
            ]) ?>
        </div>
    </div>
</section>

<section class="page-section" aria-labelledby="behind-title">
    <div class="wrap">
        <?= site_head('Who is behind Nfelia', 'Nfelia is a product of ' . e($org['legal']) . '. You can reach the team on WhatsApp or by phone on ' . e($org['phone_text']) . ', or by email at <a href="mailto:' . e($org['support']) . '">' . e($org['support']) . '</a>. ' . $link('contact', 'All the ways to reach us') . '.', 'behind-title') ?>
    </div>
</section>

<?= site_related(['features', 'pricing', 'faq']) ?>
<?= site_cta() ?>
