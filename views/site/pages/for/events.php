<?php
/**
 * /for/events: Nfelia for event planners, decorators and rental businesses.
 *
 * Checked against the app (28 Oct 2026): quotes line by line with "Valid
 * until", comments from the client and one-tap or signed acceptance
 * (views/quotations), notifications when a quote is accepted or commented on
 * (app/helpers/notify.php); a quote becomes an order with its invoice
 * (QuotationController::convert); the Event plan (planner_templates) and
 * steps given to the team; delivery notes the recipient signs on the phone
 * (views/deliveries/signoff.php); whole-day bookings with "Bookings you can
 * take a day" and a deposit (views/products/form.php); the Stage design's
 * event planner (views/storefront/sections/hero_planner.php).
 */
require_once BASE_PATH . '/views/home/_icons.php';   // site_hero and site_ticks draw icons before the layout loads them
$prices = site_prices($page['country']);
$link   = fn(string $key, string $text) => site_page($key) ? '<a href="' . e(site_url($key)) . '">' . e($text) . '</a>' : e($text);
$price_html = ($prices
        ? 'Free for ' . (int)$prices['trial_days'] . ' days with everything, and no card needed. Then ' . e(($prices['money'])($prices['month'])) . ' a month in Nigeria. '
        : 'Message us on WhatsApp for the price. ')
    . 'For Ghana, Kenya and Cameroon, see the prices for ' . $link('gh/pricing', 'Ghana') . ', ' . $link('ke/pricing', 'Kenya') . ' and ' . $link('cm/pricing', 'Cameroon') . '.';

$rows = [
    ['Prices sent as a text message', 'A quote with your logo, line by line, accepted or signed from WhatsApp.', 'features/invoices', 'Invoices and quotes'],
    ['Chasing the balance before the day', 'The balance in Debts, on your Tasks the day it is due, with a WhatsApp reminder.', 'features/debts', 'Debts'],
    ['Vendors, guest numbers, set-up', 'The Event plan, with every step on your calendar.', 'features/tasks', 'Tasks and reminders'],
    ['Items dropped at the venue', 'A delivery note the client signs on the phone.', 'help/deliveries', 'Deliveries'],
    ['Clients who want to see your work', 'Your own website, with your packages and a request form.', 'features/website', 'Your website'],
];

echo site_hero([
    'kicker'    => 'For event planners and decorators',
    'h1'        => 'From the quote to the big day, with the balance paid',
    'lead_html' => 'Nfelia is an invoice and quote app for event planners, decorators and rental businesses in Nigeria, Ghana, Kenya and Cameroon. Send a proper quote, take the deposit, track the balance until the event, and plan every step up to set-up.',
    'screens'   => [['invoice-1', 'Kemi Events & Décor\'s invoice as the client sees it: deposit paid and balance due', 'Deposit and balance'],
                    ['tasks-1', 'Tasks: a job with 2 days left, payments and a delivery that are late, and deliveries due today', 'Today, and what is late']],
]);
?>
<section class="page-section" aria-labelledby="quote-title">
    <div class="wrap split">
        <div class="split-copy">
            <h2 id="quote-title">A quote that wins the job</h2>
            <p>Lay out décor, chairs and covers, lighting, the cake table and logistics line by line, with your logo and colours. Set how long the price holds. The client can leave a comment to ask a question or for a change, then accept with one tap, or sign if you turn on signed quotes.</p>
            <?= site_ticks([['Accepted?', 'One tap turns the quote into an order with its invoice.'],
                            ['Deposit paid and balance due', 'show clearly on the invoice the client opens.'],
                            ['You hear at once', 'when a client accepts or comments.']]) ?>
        </div>
        <div class="prose">
            <figure>
                <img class="story-photo" src="<?= APP_URL ?>/assets/img/home/story-kemi.webp" width="720" height="450" loading="lazy" decoding="async" alt="Kemi checking her phone while her team sets up an event hall">
                <figcaption>Kemi Events &amp; Décor, an example business</figcaption>
            </figure>
        </div>
    </div>
</section>

<section class="page-section tint" aria-labelledby="plan-title">
    <div class="wrap split flip">
        <div class="split-copy">
            <h2 id="plan-title">Every step up to the day</h2>
            <p>Tap Plan a job and pick Event. Give it the event day, and Nfelia dates each step back from it: collect the deposit, book vendors, confirm guest numbers, collect the balance, set-up, and the day itself. Give steps to people on your team, and their reminders reach their phones.</p>
            <?= site_ticks([['Delivery notes', 'for what you drop at the venue, signed by the client on the phone.'],
                            ['Rent by the day?', 'Halls, canopies and décor packages can be booked as whole days, with a deposit.'],
                            ['The Stage design', 'turns your event packages into a planner on your website: clients pick the guests, the day, colours and extras.']]) ?>
        </div>
        <div><?= site_screens([['delivery-1', 'Delivery note IVL-DEL-041: what was sent, to whom, and the rider', 'What was sent'],
                               ['delivery-2', 'The customer types her name and signs with a finger to confirm she received it', 'Signed on the phone']]) ?></div>
    </div>
    <p class="stories-note">Screens from Ivie Luxe Hair and Kemi Events &amp; Décor, example businesses.</p>
</section>

<section class="page-section" aria-labelledby="fix-title">
    <div class="wrap">
        <?= site_head('What you deal with, and what Nfelia does', '', 'fix-title') ?>
        <div class="compare table-scroll">
            <table>
                <thead><tr><th scope="col">What you deal with</th><th scope="col">What Nfelia does</th></tr></thead>
                <tbody>
                <?php foreach ($rows as [$pain, $fix, $key, $text]): ?>
                    <tr><th scope="row"><?= e($pain) ?></th><td><?= e($fix) ?> <?= $link($key, $text) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="page-section tint" aria-labelledby="example-title">
    <div class="wrap">
        <?= site_head('An example: an office party', 'Kemi Events &amp; Décor is an example business in Lagos. The client and numbers are made up to show how Nfelia fits event work.', 'example-title') ?>
        <?= site_steps([
            ['Six weeks before: the quote', 'Kemi sends a quote for décor, 200 chairs with covers, lighting and set-up, valid for 7 days.'],
            ['Two days later: accepted', 'The client asks for gold covers in a comment. Kemi updates the quote, the client accepts with one tap, and Kemi turns it into an order.'],
            ['The deposit', 'The client pays half. Kemi records it, the receipt goes out, and the invoice shows the deposit paid and the balance due.'],
            ['Plan the job', 'An Event plan puts vendors, guest numbers, the balance and set-up on the calendar. Kemi gives set-up to her team lead.'],
            ['Three days before: the balance', 'The balance is due. It is on Kemi\'s Tasks that morning, with a button to remind the client on WhatsApp.'],
            ['Party day', 'When the décor is up, the client signs the delivery note on the phone.'],
        ]) ?>
    </div>
</section>

<section class="page-section" aria-labelledby="faq-title">
    <div class="wrap">
        <?= site_head('Questions event planners ask', '', 'faq-title', true) ?>
        <?= site_faq([
            ['Can I take a deposit and track the balance until the event?',
             'Yes. Record the deposit on the order and Nfelia makes the receipt. The invoice the client opens shows the deposit paid and the balance due, and the balance waits in ' . $link('features/debts', 'Debts') . ' until it is paid.'],
            ['Can clients sign my quote?',
             'Yes. Turn on Signed quote and the client signs to accept it, with your business\'s signature on it too. Leave it off and they accept with one tap. Either way, you hear about it straight away.'],
            ['Can I rent out chairs, canopies or a hall by the day?',
             'Yes. Set the item to be booked for a whole day, choose how many bookings you can take a day, and set the deposit. Clients pick a free day on your website. ' . $link('features/bookings', 'See how bookings work') . '.'],
            ['Can my team see what to do for each event?',
             'Yes. Give any step of an event plan to someone on your team. It shows on their Tasks, and the reminder reaches their phone. ' . $link('help/tasks-and-reminders', 'Tasks and reminders, step by step') . '.'],
            ['How much does Nfelia cost?', $price_html],
        ]) ?>
    </div>
</section>

<?= site_related(['features/invoices', 'features/tasks', 'help/deliveries', 'blog/how-to-write-an-invoice', 'for/food-and-catering']) ?>
<?= site_cta('Send your next event quote with Nfelia') ?>
