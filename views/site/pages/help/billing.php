<?php
/**
 * Help: paying for Nfelia. Checked against views/settings/index.php (the
 * Billing tab), PaystackController (initialize, callback, cancel),
 * MoMoController and views/billing/momo_checkout.php, app/helpers/billing.php
 * (billing_access, the grace days, seats, billing_next_period),
 * app/middleware/subscription_check.php (what stays open), shop_visibility()
 * (the website), billing_lifecycle() (the notices) and ReferralController.
 * Every price comes from site_prices(), as Ops → Prices has it.
 */
require_once BASE_PATH . '/views/home/_icons.php';   // nf_icon() for the building blocks: the layout loads it only after the view
$u      = fn(string $key) => e(site_url($key));
$prices = site_prices($page['country']);
$money  = $prices['money'] ?? null;
$month  = $prices ? $money((float)$prices['month']) : null;
$seat   = $prices ? $money((float)$prices['seat']) : null;
$days   = (int)($prices['trial_days'] ?? TRIAL_DAYS);
$grace  = BILLING_GRACE_DAYS;
$pays   = fn(string $by) => match ($by) {
    'paystack'     => 'Online with Paystack',
    'mobile_money' => 'Mobile Money, then send the receipt',
    default        => 'Arranged with our team',
};
$price_key = fn(array $c) => ($c['prefix'] === '' ? '' : strtolower($c['iso']) . '/') . 'pricing';
// The countries open for sign-ups that pay by Mobile Money, in words ("Ghana and Cameroon")
$momo = array_values(array_map(fn($c) => $c['name'], array_filter(site_countries(), fn($c) => (site_prices($c['iso'])['pays_by'] ?? '') === 'mobile_money')));
$momo_words = count($momo) > 1 ? implode(', ', array_slice($momo, 0, -1)) . ' and ' . end($momo) : ($momo[0] ?? '');
// Of those, the ones whose Billing shows no number: their instructions say to ask us on WhatsApp for the account
$momo_ask = array_values(array_map(fn($c) => $c['name'], array_filter(site_countries(), fn($c) => (site_prices($c['iso'])['pays_by'] ?? '') === 'mobile_money' && !(site_prices($c['iso'])['momo_shown'] ?? false))));
$momo_ask_words = count($momo_ask) > 1 ? implode(', ', array_slice($momo_ask, 0, -1)) . ' and ' . end($momo_ask) : ($momo_ask[0] ?? '');
$toc = [
    'cost'      => 'What Nfelia costs',
    'trial'     => 'Your free trial',
    'pay'       => 'Pay for a month',
    'momo'      => 'Paying by Mobile Money',
    'seats'     => 'Add staff seats',
    'ends'      => 'When a month ends, or you stop',
    'cancel'    => 'Cancel',
    'referrals' => 'Referrals',
    'problems'  => 'Common problems and fixes',
];
?>
<article class="wrap article-wrap has-aside">
    <header class="article-head">
        <p class="kicker">Help centre</p>
        <h1>Pay for Nfelia, add seats, and what happens if you stop</h1>
        <p class="page-lead">You pay for Nfelia one month at a time, from Billing<?= $month ? ': ' . e($month) . ' a month in ' . e($country['name']) . ', plus ' . e($seat) . ' for each staff member' : '' ?>. In Nigeria you pay online with Paystack<?= $momo_words !== '' ? '; in ' . e($momo_words) . ' you pay by Mobile Money and send the receipt' : '' ?>. Nothing renews by itself, and if you stop, we keep your records for 12 months.</p>
        <?= site_dates($page) ?>
    </header>
    <aside class="article-aside">
        <nav class="toc" aria-labelledby="toc-title">
            <h2 id="toc-title">On this page</h2>
            <ol>
                <?php foreach ($toc as $id => $label): ?>
                <li><a href="#<?= e($id) ?>"><?= e($label) ?></a></li>
                <?php endforeach; ?>
            </ol>
        </nav>
    </aside>

    <div class="prose">
        <p class="note"><strong>Who can do this</strong> Only the owner sees Billing and pays. Staff see a message that Nfelia is paused if the owner has not paid.</p>

        <h2 id="cost">What Nfelia costs</h2>
        <p>One plan with everything in it: your website, invoices and receipts, debts, stock, bookings, deliveries, profit, tasks and a free Records Book each paid month. No commission on your sales.</p>
        <div class="table-scroll">
            <table>
                <caption class="sr-only">What Nfelia costs a month in each country, with the free trial and how you pay</caption>
                <thead>
                    <tr><th scope="col">Country</th><th scope="col">A month</th><th scope="col">Each staff member</th><th scope="col">Free trial</th><th scope="col">How you pay</th></tr>
                </thead>
                <tbody>
                <?php foreach (site_countries() as $c): $p = site_prices($c['iso']); ?>
                    <tr>
                        <th scope="row"><?php if (site_page($price_key($c))): ?><a href="<?= $u($price_key($c)) ?>"><?= e($c['name']) ?></a><?php else: ?><?= e($c['name']) ?><?php endif; ?></th>
                        <?php if ($p): ?>
                        <td><?= e($p['money']((float)$p['month'])) ?></td>
                        <td><?= e($p['money']((float)$p['seat'])) ?> a month</td>
                        <td><?= (int)$p['trial_days'] ?> days</td>
                        <td><?= e($pays($p['pays_by'])) ?></td>
                        <?php else: ?>
                        <td colspan="4">Not open for sign-ups yet. <a href="<?= e(site_wa('Hello Nfelia, I am in ' . $c['name'] . '. When can I start using Nfelia?')) ?>" target="_blank" rel="noopener">Ask us on WhatsApp</a> and we will tell you when it opens.</td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p>When a price changes, Billing tells you before it does, and a month that starts before the change keeps today's price.</p>

        <h2 id="trial">Your free trial</h2>
        <ul>
            <li><strong>Everything is open for <?= $days ?> days</strong>, with no card needed. Your trial includes <?= e(TRIAL_STAFF_SEATS . ' staff member' . (TRIAL_STAFF_SEATS === 1 ? '' : 's')) ?> besides you.</li>
            <li>Home shows how many days are left, with <strong>Open Billing</strong>. Nfelia tells you when three days are left, and again once it has ended.</li>
            <li>During the trial your website works for anyone with the link, but it is kept off Google. Once you pay, Google can show it: see <a href="<?= $u('help/get-found-on-google') ?>">how to get found on Google</a>.</li>
            <li>When the trial ends without a payment, Nfelia and your website pause until you pay. There are no extra days after a trial.</li>
        </ul>

        <h2 id="pay">Pay for a month</h2>
        <?= site_steps([
            ['Open Billing', 'On a phone, tap <strong>More</strong>, then <strong>Billing</strong>. On a computer, click your picture or initial at the top right, then <strong>Billing</strong>. You see <strong>Current plan</strong>, with Free trial, Paid or another status.'],
            ['Check the dates', 'Under <strong>Pay for a month</strong>, Nfelia shows the month you are buying, from one date to the next. Paying early never loses days: the new month starts when the one you are in ends.'],
            ['Choose who it covers', 'If you have staff, tick each person in the list headed <strong>Who does this month cover?</strong> You are always covered. Anyone you leave unticked is switched off when the payment goes through.'],
            ['Add spare seats if you need them', 'Use <strong>+</strong> and <strong>−</strong> beside <strong>Staff seats</strong> to add seats for people you will hire. You see the <strong>Total for the month</strong>.'],
            ['Tap Pay for the month', 'In Nigeria, Paystack\'s secure page opens: pay there with your card or another way Paystack offers you. You come back to Billing with a message that the payment was received, and <strong>Current plan</strong> shows <strong>Paid until</strong> the last day of your month.'],
        ]) ?>
        <p>Every payment is listed under <strong>Payment history</strong>, and Nfelia sends you a note that it went through. You pay on Paystack's own page, and your card details are never stored on Nfelia's servers.</p>

        <h2 id="momo">Paying by Mobile Money</h2>
        <p><?= $momo_words !== '' ? 'In ' . e($momo_words) . ', Nfelia is paid by Mobile Money.' : 'Where Nfelia is paid by Mobile Money, you pay and then send the receipt.' ?> Tapping <strong>Pay for the month</strong> opens <strong>Complete your payment</strong>:</p>
        <?= site_steps([
            ['Send the exact amount', 'Step 1 shows the amount to send and the Mobile Money number to send it to, with <strong>Copy</strong> and the steps under <strong>How to pay</strong>.'
                . ($momo_ask_words !== '' ? ' In ' . e($momo_ask_words) . ', <strong>How to pay</strong> asks you to <a href="' . e(site_wa('Hello Nfelia, which Mobile Money account do I pay Nfelia into?')) . '" target="_blank" rel="noopener">message us on WhatsApp</a> for the account to send it to.' : '')],
            ['Upload your receipt', 'In Step 2, choose the screenshot or photo of the payment (JPG, PNG, WebP or PDF, up to 5MB). Add the <strong>Transaction reference</strong> from the payment SMS if you have it: it helps us find it.'],
            ['Tap I have paid: send my receipt', 'Billing then shows that we are checking your payment. We check every payment by hand, usually within a few hours, and the month opens as soon as it is approved. We tell you in Nfelia and by email.'],
        ]) ?>
        <p>If your trial or month has already ended, Nfelia stays paused until the payment is approved, so send it a day or two before your end date.</p>

        <h2 id="seats">Add staff seats</h2>
        <p>Each person besides you takes a seat<?= $seat ? ', at ' . e($seat) . ' a month' : '' ?>. You can have up to <?= (int)MAX_SEATS ?> people in all: you and <?= (int)(MAX_SEATS - 1) ?> staff.</p>
        <ul>
            <li><strong>With your month</strong>: tick the staff it covers and add spare seats before you tap Pay for the month.</li>
            <li><strong>In the middle of a paid month</strong>: use <strong>Add seats now</strong> in Billing, choose how many, and tap <strong>Pay for the seats</strong>. They last until your month ends; next month you choose again.</li>
        </ul>
        <p>Then add the person on the Team tab: see <a href="<?= $u('help/staff') ?>">how to add staff and choose what they see</a>.</p>

        <h2 id="ends">When a month ends, or you stop</h2>
        <div class="table-scroll">
            <table>
                <caption class="sr-only">What happens to Nfelia and your website at each point after a payment</caption>
                <thead>
                    <tr><th scope="col">When</th><th scope="col">Nfelia</th><th scope="col">Your website</th></tr>
                </thead>
                <tbody>
                    <tr><td>Last three days of a paid month</td><td>Open. Nfelia tells you the month is ending.</td><td>Open</td></tr>
                    <tr><td>The <?= (int)$grace ?> days after it ends</td><td>Open, with a banner: pay by the date shown to keep it open</td><td>Open</td></tr>
                    <tr><td>After that, until you pay</td><td>Paused. You are taken to Billing; your staff see that Nfelia is paused</td><td>Shows "closed for now" with your WhatsApp button, so customers can still reach you</td></tr>
                    <tr><td>When you pay again</td><td>Open again at once, with everything as you left it</td><td>Open again</td></tr>
                    <tr><td>12 months after your last paid month, if you have not paid</td><td>We may delete your business’s records. We email you before anything is deleted</td><td>Removed</td></tr>
                </tbody>
            </table>
        </div>
        <p><strong>Your records are kept for 12 months.</strong> While Nfelia is paused, your records stay safe, and Your records stays open so you can download your plain copy, or a Records Book. The 12 months count from the end of your last paid month (or of your trial, if you never paid), and we email you before anything is deleted. Reminders and the morning plan stop until you pay. See <a href="<?= $u('help/records-book') ?>">your Records Book</a>.</p>

        <h2 id="cancel">Cancel</h2>
        <p>Because nothing renews by itself, you never have to cancel to stop paying: simply do not pay for the next month. If you want it on record, open Billing and tap <strong>Cancel subscription</strong> at the bottom. Tell us why if you like, then tap <strong>Confirm cancellation</strong>.</p>
        <p>Nfelia and your website stay open until the last day of the month you paid for. After a cancelled month there are no extra days: it pauses the next day. Changed your mind? Pay for a month in Billing whenever you are ready.</p>
        <p>A month that has started is not refunded, even if you cancel. A payment taken by mistake, such as being charged twice, is always refunded: <a href="<?= e(site_wa('Hello Nfelia, I was charged by mistake.')) ?>" target="_blank" rel="noopener">message us on WhatsApp</a> with the payment reference. See <a href="<?= $u('terms') ?>#refunds">refunds in our Terms of Service</a>.</p>

        <h2 id="referrals">Referrals</h2>
        <p>Know another business owner who would like Nfelia? Open <strong>More</strong>, then <strong>Referrals</strong>. Tap <strong>Copy Link</strong> or <strong>Share on WhatsApp</strong> to send your own sign-up link.</p>
        <p>When a business signs up with your link and makes its first payment with Paystack, you earn a credit that comes off your next month paid with Paystack, one credit a month. The Referrals page shows how much each credit is worth, who signed up, and the credit waiting for you. Referrals work in Nigeria, where Nfelia is paid with Paystack.</p>

        <h2 id="problems">Common problems and fixes</h2>
        <?= site_faq([
            ['I paid but Nfelia still says my month has ended.',
             'With Paystack, open Billing again: the payment is applied as soon as Paystack confirms it, and Payment history shows it. With Mobile Money, wait for our check, usually a few hours. If it is still not there, message us on WhatsApp with your payment reference.'],
            ['I can\'t see Billing.',
             'Only the owner of the business sees Billing. Staff and admins should ask the owner to pay.'],
            ['Paystack says the payment failed.',
             'The month only opens for a payment Paystack confirms. Try again, or another card or way of paying on the Paystack page; your bank may need you to approve it. If money left your account but the month did not open, message us on WhatsApp with the reference.'],
            ['It says "We are still checking your last Mobile Money payment".',
             'You can send only one Mobile Money receipt at a time. Once we approve it, or turn it down, you can send another.'],
            ['Do I lose my data if I don\'t pay?',
             'Not straight away. Nfelia and your website pause, and everything comes back the moment you pay. We keep your records for 12 months after your last paid month, and you can download them meanwhile. We email you before anything is deleted.'],
        ]) ?>
    </div>
</article>
<?= site_related(['pricing', 'help/staff', 'help/records-book', 'help/get-found-on-google', 'faq', 'cm/pricing']) ?>
<?= site_cta('Try Nfelia free') ?>
