<?php
/**
 * /for/fashion-designers: Nfelia for fashion designers and tailors.
 *
 * Checked against the app (28 Oct 2026): private notes on a client's page,
 * with "Coming up" and their totals (views/customers); quotes with notes and
 * terms, accepted with one tap or signed (views/quotations); a quote becomes
 * an order with its invoice (QuotationController::convert); part payments,
 * receipts and the balance in Debts (views/orders/form.php); the Sewing job
 * plan and WhatsApp reminders for appointments (app/helpers/planner.php);
 * fabric counted in yards or metres (views/products/form.php).
 */
require_once BASE_PATH . '/views/home/_icons.php';   // site_hero and site_ticks draw icons before the layout loads them
$prices = site_prices($page['country']);
$link   = fn(string $key, string $text) => site_page($key) ? '<a href="' . e(site_url($key)) . '">' . e($text) . '</a>' : e($text);
$price_html = ($prices
        ? 'Free for ' . (int)$prices['trial_days'] . ' days with everything, and no card needed. Then ' . e(($prices['money'])($prices['month'])) . ' a month in Nigeria. '
        : 'Message us on WhatsApp for the price. ')
    . 'For Ghana, Kenya and Cameroon, see the prices for ' . $link('gh/pricing', 'Ghana') . ', ' . $link('ke/pricing', 'Kenya') . ' and ' . $link('cm/pricing', 'Cameroon') . '.';

$rows = [
    ['Measurements in an old notebook', 'Notes on each client\'s page, next to their orders and payments.', 'help/getting-started', 'Getting started'],
    ['Clients asking “how much?”', 'Quotes with fabric and sewing on their own lines, accepted from WhatsApp.', 'features/invoices', 'Invoices and quotes'],
    ['Deposits and balances in your head', 'Part payments with receipts. The balance waits in Debts with a reminder.', 'features/debts', 'Debts'],
    ['Fittings and pickup dates', 'The Sewing job plan, your calendar and reminders on your phone.', 'features/tasks', 'Tasks and reminders'],
    ['Showing your work', 'Your own website in the Atelier design, with a form for made-to-order requests.', 'features/website', 'Your website'],
];

echo site_hero([
    'kicker'    => 'For fashion designers and tailors',
    'h1'        => 'Measurements, deposits and pickups in one place',
    'lead_html' => 'Nfelia is an app for fashion designers and tailors in Nigeria, Ghana, Kenya and Cameroon. Keep each client\'s measurements, quote fabric and sewing, take the deposit and collect the balance at pickup, all from your phone.',
    'screens'   => [['tasks-1', 'Tasks: a job with 2 days left, payments and a delivery that are late, and deliveries due today', 'Today, and what is late'],
                    ['owed-2', 'A balance of ₦60,000, 6 days overdue, with a green Send reminder button', 'One-tap reminder']],
]);
?>
<section class="page-section" aria-labelledby="measure-title">
    <div class="wrap split">
        <div class="split-copy">
            <h2 id="measure-title">Every client's measurements, next to their orders</h2>
            <p>Open a client in Nfelia and write their measurements in the notes, one per line, the way you take them. They stay on the client's page with their quotes, orders, payments and balance. When a client calls for a new outfit, her sizes are one tap away.</p>
            <?= site_ticks([['Private.', 'Only you and your team see the notes.'],
                            ['Coming up.', 'Her next fitting or delivery shows on her page.'],
                            ['Her totals.', 'What she has paid you, and what is still outstanding.']]) ?>
        </div>
        <div class="prose">
            <figure>
                <p class="template">Measurements, 12 October (inches)
Shoulder 15 · Bust 36 · Under-bust 31
Waist 30 · Hip 41 · Full length 58
Sleeve 22 · Round arm 12
Likes a fitted waist. No slit.</p>
                <figcaption>An example of measurements in a client's notes</figcaption>
            </figure>
        </div>
    </div>
</section>

<section class="page-section tint" aria-labelledby="quote-title">
    <div class="wrap split flip">
        <div class="split-copy">
            <h2 id="quote-title">Quote fabric and sewing, then take the deposit</h2>
            <p>Put fabric, lining and sewing on their own lines, so the client sees what she is paying for. Write your terms in the notes, like “Half to start, balance at pickup”. She opens the quote from WhatsApp and accepts with one tap, or signs it if you turn on signed quotes.</p>
            <?= site_ticks([['One tap', 'turns the accepted quote into an order with its invoice.'],
                            ['Record the deposit', 'and the receipt is made. The balance goes into Debts.'],
                            ['Keep fabric in stock?', 'Count it in yards or metres. It comes off when you sell it.']]) ?>
        </div>
        <div><?= site_screens([['invoice-1', 'Kemi Events & Décor\'s invoice as the client sees it: deposit paid and balance due', 'Deposit and balance, clearly shown']]) ?></div>
    </div>
</section>

<section class="page-section" aria-labelledby="fitting-title">
    <div class="wrap split">
        <div class="split-copy">
            <h2 id="fitting-title">Fittings and pickup days you won't forget</h2>
            <p>Tap Plan a job and pick Sewing job. Give it the day the client needs the outfit, and Nfelia lays out the steps before it: take measurements, buy fabric and materials, the fitting, ready for pickup, deliver. Each one lands on your Tasks and your calendar.</p>
            <?= site_ticks([['On the morning of a fitting,', 'a button opens WhatsApp with her reminder already written.'],
                            ['A countdown', 'runs to every delivery day, so a late job can\'t hide.'],
                            ['Give a step to your tailor', 'and the reminder reaches their phone.']]) ?>
        </div>
        <div><?= site_screens([['tasks-2', 'Calendar for October with appointments, deliveries and money due marked on each day', 'Your month']]) ?></div>
    </div>
    <p class="stories-note">Screens from Ivie Luxe Hair and Kemi Events &amp; Décor, example businesses.</p>
</section>

<section class="page-section tint" aria-labelledby="fix-title">
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

<section class="page-section" aria-labelledby="example-title">
    <div class="wrap">
        <?= site_head('An example week', 'An example to show how Nfelia fits a tailoring business. The client and the numbers are made up.', 'example-title') ?>
        <?= site_steps([
            ['Monday: a new client', 'A bride-to-be in Surulere sends her measurements on WhatsApp. You save her as a client and paste them into her notes.'],
            ['Monday: the quote', 'Three lines: 6 yards of lace, lining and sewing. In the notes: “Half to start, balance at pickup.” You share it on WhatsApp and she accepts with one tap.'],
            ['Tuesday: the deposit', 'Her transfer comes in. You turn the quote into an order and record half as paid. Her receipt goes out, and the balance waits in Debts.'],
            ['Tuesday: plan the job', 'You plan a Sewing job, delivered a week before the wedding. Buying the fabric, the fitting and pickup land on your calendar.'],
            ['Fitting day', 'The fitting is on your list that morning. One tap opens WhatsApp with her reminder written.'],
            ['Pickup', 'She pays the balance when she collects. You record it, the invoice shows PAID and her page says Clear.'],
        ]) ?>
    </div>
</section>

<section class="page-section tint" aria-labelledby="faq-title">
    <div class="wrap">
        <?= site_head('Questions fashion designers ask', '', 'faq-title', true) ?>
        <?= site_faq([
            ['Where do I keep my clients\' measurements in Nfelia?',
             'In the notes on each client\'s page. Write them the way you take them, one per line. Only you and your team see them, and they sit next to the client\'s quotes, orders and payments.'],
            ['Can I take a deposit and collect the balance at pickup?',
             'Yes. Record the deposit on the order: Nfelia makes the receipt and puts the balance in ' . $link('features/debts', 'Debts') . ' with the day it is due. That day it shows on your Tasks, with a WhatsApp reminder ready to send.'],
            ['Can clients ask for a price on my website?',
             'Yes. ' . $link('features/website', 'Your website') . ' at yourname.nfelia.com shows your styles with a price, or “Ask for the price” when you leave it empty. A request form lets clients describe what they want made. It lands in Nfelia, and the client is saved.'],
            ['Can I keep track of fabric?',
             'Yes. Add each fabric as a product counted in yards or metres. When you sell it on an order it comes off your stock, and when it runs low it shows on your Tasks to restock.'],
            ['How much does Nfelia cost?', $price_html],
        ]) ?>
    </div>
</section>

<?= site_related(['features/invoices', 'features/debts', 'features/tasks', 'help/tasks-and-reminders', 'blog/price-your-products']) ?>
<?= site_cta('Run your fashion business from your phone') ?>
