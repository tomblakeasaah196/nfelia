<?php
/**
 * /for/food-and-catering: Nfelia for caterers and food businesses.
 *
 * Checked against the app (28 Oct 2026): quotes line by line with "Valid
 * until" and notes and terms (views/quotations/form.php); the website's
 * request form with how many, budget, when and where (views/storefront/
 * sections/quote.php), titled "Party and bulk orders" in the Buka design
 * (app/helpers/storefront.php); "Ask for the price" when a price is left
 * empty (views/products/form.php); expenses by category, unpaid ones as
 * money owed (ExpenseController, DebtController); profit for any period as a
 * PDF, owners and admins only (ReportController); crates, bottles and packs
 * as stock units (views/products/form.php).
 */
require_once BASE_PATH . '/views/home/_icons.php';   // site_hero and site_ticks draw icons before the layout loads them
$prices = site_prices($page['country']);
$link   = fn(string $key, string $text) => site_page($key) ? '<a href="' . e(site_url($key)) . '">' . e($text) . '</a>' : e($text);
$price_html = ($prices
        ? 'Free for ' . (int)$prices['trial_days'] . ' days with everything, and no card needed. Then ' . e(($prices['money'])($prices['month'])) . ' a month in Nigeria. '
        : 'Message us on WhatsApp for the price. ')
    . 'For Ghana, Kenya and Cameroon, see the prices for ' . $link('gh/pricing', 'Ghana') . ', ' . $link('ke/pricing', 'Kenya') . ' and ' . $link('cm/pricing', 'Cameroon') . '.';

$rows = [
    ['Party prices typed out on WhatsApp', 'Quotes with your logo, line by line, accepted with one tap.', 'features/invoices', 'Invoices and quotes'],
    ['Deposits you have to remember', 'Part payments with receipts. The balance waits in Debts.', 'features/debts', 'Debts'],
    ['Foodstuff money that disappears', 'Expenses by category, and your profit for any month.', 'features/profit', 'Expenses and profit'],
    ['Orders scattered across your DMs', 'Your menu on your own website, with orders for pickup or delivery.', 'features/orders', 'Online orders'],
    ['“We never received it”', 'A delivery note the client signs on the phone when the food arrives.', 'help/deliveries', 'Deliveries'],
];

echo site_hero([
    'kicker'    => 'For food and catering',
    'h1'        => 'Party orders, deposits and foodstuff costs, sorted',
    'lead_html' => 'Nfelia is a catering business app for caterers, small chops makers, bakers and food vendors in Nigeria, Ghana, Kenya and Cameroon. Send a proper quote for a party, take the deposit, write down what you spend at the market, and see what you really made.',
    'screens'   => [['invoice-1', 'Kemi Events & Décor\'s invoice as the client sees it: deposit paid and balance due', 'Deposit and balance'],
                    ['profit-2', 'Profit and loss for October: ₦962,000 in, ₦695,500 out, ₦266,500 profit', 'Profit, worked out']],
]);
?>
<section class="page-section" aria-labelledby="quote-title">
    <div class="wrap split">
        <div class="split-copy">
            <h2 id="quote-title">Quotes for parties and bulk orders</h2>
            <p>Put each part of the job on its own line: jollof rice for 150, small chops, drinks by the crate, two servers. Set how long the price holds, and write your terms in the notes, like the deposit and the last day to change numbers. The client accepts from WhatsApp with one tap, and you turn the quote into an order with its invoice.</p>
            <?= site_ticks([['Requests come from your website too.', 'Your request form can ask how many, the budget, when and where to deliver.'],
                            ['Prices change at the market?', 'Leave a price empty and your menu says “Ask for the price”.'],
                            ['Record the deposit,', 'and the balance waits in Debts with its due date.']]) ?>
        </div>
        <div class="prose">
            <figure>
                <p class="template">Jollof rice, 150 portions
Fried rice, 150 portions
Small chops, 150 packs
Soft drinks, 6 crates
Servers, 2
Notes: 60% deposit to confirm. Final numbers 5 days before.</p>
                <figcaption>An example of the lines on a party quote</figcaption>
            </figure>
        </div>
    </div>
</section>

<section class="page-section tint" aria-labelledby="profit-title">
    <div class="wrap split flip">
        <div class="split-copy">
            <h2 id="profit-title">Know what each month really made</h2>
            <p>Every market run is money out. Record it as an expense: what you bought, how much and when. Bought on credit? Mark it unpaid, and what you owe the supplier shows in Debts. Nfelia puts money in against money out, so you see your profit for any month, as a PDF if your bank asks.</p>
            <?= site_ticks([['Stock purchase, transport, wages:', 'every expense has its category.'],
                            ['Drinks and packs', 'counted by the crate, bottle or pack, and taken off when you sell.'],
                            ['Only you or an admin', 'see the expenses and the profit.']]) ?>
        </div>
        <div><?= site_screens([['profit-1', 'Home: ₦962,000 came in this month, ₦695,500 spent, ₦266,500 left, and what needs you today', 'Your month at a glance']]) ?></div>
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
        <?= site_head('An example naming ceremony', 'An example to show how Nfelia fits a catering business. The client, dates and numbers are made up.', 'example-title') ?>
        <?= site_steps([
            ['Monday: a request', 'Someone in Abuja asks on your website for food for 120 guests at a naming ceremony on the 14th.'],
            ['Monday: the quote', 'You send a quote line by line, valid for a week, with “60% deposit to confirm” in the notes.'],
            ['Tuesday: the deposit', 'She accepts and pays. You turn the quote into an order and record the deposit. Her receipt goes out, and the balance waits in Debts.'],
            ['Thursday: the market', 'You record the market run as an expense. The drinks supplier gives you credit, so that one is marked unpaid.'],
            ['The 11th: the balance', 'You set the balance due three days before the day. That morning it is on your Tasks, with a button to remind her on WhatsApp.'],
            ['Month end: your profit', 'Profit and loss shows what came in, what went out and what was left.'],
        ]) ?>
    </div>
</section>

<section class="page-section" aria-labelledby="faq-title">
    <div class="wrap">
        <?= site_head('Questions food businesses ask', '', 'faq-title', true) ?>
        <?= site_faq([
            ['Can customers order food from my website?',
             'Yes. Your website shows your menu with photos and prices. Customers choose pickup or delivery, pay into your own account and send the proof, or send the order on WhatsApp instead. Every order lands in Nfelia. ' . $link('features/orders', 'See how online orders work') . '.'],
            ['Can I send a quote for a party or event?',
             'Yes. Put each dish, drink and service on its own line, with your logo, and write the deposit and your terms in the notes. The client accepts with one tap from WhatsApp, and the quote becomes an order with its invoice. ' . $link('help/send-an-invoice', 'How to send a quote or invoice') . '.'],
            ['How do I keep track of what I spend on foodstuff?',
             'Record each purchase as an expense, with what you bought and the amount. If a supplier gave you credit, mark it unpaid and it shows in Debts as money you owe. Your profit for the month counts every expense. ' . $link('help/expenses-and-profit', 'Expenses and profit, step by step') . '.'],
            ['Can I keep stock of drinks and packaged items?',
             'Yes. Add them as products counted by the crate, bottle, pack or carton. They come off your stock when you sell them, and Nfelia warns you before they run out.'],
            ['How much does Nfelia cost?', $price_html],
        ]) ?>
    </div>
</section>

<?= site_related(['features/invoices', 'features/profit', 'help/expenses-and-profit', 'blog/calculate-profit', 'for/events']) ?>
<?= site_cta('Send your next party quote with Nfelia') ?>
