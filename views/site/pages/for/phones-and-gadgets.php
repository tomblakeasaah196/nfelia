<?php
/**
 * /for/phones-and-gadgets: Nfelia for phone, laptop and accessory shops.
 *
 * Checked against the app (28 Oct 2026): stock is kept per product, not per
 * option (app/helpers/stock.php), so each model and size is its own product;
 * stock history of the last 50 changes (views/products/view.php); Restock
 * items on Tasks (app/helpers/planner.php); the website shows up to MAX_PRODUCTS products.
 * Nfelia has no warranty or IMEI tracker: warranty terms go in a product's
 * description (shown on the website) or a quote's notes and terms, and the
 * IMEI in the order's own note, seen only by the team (views/orders/form.php).
 * Invoices made from orders carry no notes (orders.php), so this page never
 * says "write it on the invoice". Debts, expenses and profit are for owners
 * and admins only (require_boss); stock purchases on credit add stock and
 * show as money owed (ExpenseController, DebtController).
 */
require_once BASE_PATH . '/views/home/_icons.php';   // site_hero and site_ticks draw icons before the layout loads them
$prices = site_prices($page['country']);
$link   = fn(string $key, string $text) => site_page($key) ? '<a href="' . e(site_url($key)) . '">' . e($text) . '</a>' : e($text);
$price_html = ($prices
        ? 'Free for ' . (int)$prices['trial_days'] . ' days with everything, and no card needed. Then ' . e(($prices['money'])($prices['month'])) . ' a month in Nigeria. '
        : 'Message us on WhatsApp for the price. ')
    . 'For Ghana, Kenya and Cameroon, see the prices for ' . $link('gh/pricing', 'Ghana') . ', ' . $link('ke/pricing', 'Kenya') . ' and ' . $link('cm/pricing', 'Cameroon') . '.';
$seat = $prices ? ' for ' . e(($prices['money'])($prices['seat'])) . ' a month' : '';

$rows = [
    ['Counting phones by hand at night', 'Stock that comes off when you sell, model by model.', 'features/stock', 'Stock'],
    ['Buyers who want a proper receipt', 'Receipts with your logo and a code anyone can check.', 'features/receipts', 'Receipts'],
    ['“I go balance you next week”', 'The balance in Debts, with a WhatsApp reminder on the day.', 'features/debts', 'Debts'],
    ['Staff on the counter', 'A login each. Only you and your admins see debts, expenses and profit.', 'help/staff', 'Staff'],
    ['Buying stock on credit', 'A stock purchase marked unpaid shows as money you owe.', 'features/profit', 'Expenses and profit'],
];

echo site_hero([
    'kicker'    => 'For phone and gadget shops',
    'h1'        => 'Know every phone on your shelf',
    'lead_html' => 'Nfelia is a phone shop inventory app for phone, laptop and accessory sellers in Nigeria, Ghana, Kenya and Cameroon. Count stock for every model, sell with a receipt that carries your logo, and know who still owes you.',
    'screens'   => [['stock-1', 'Stock running low: a frontal wig with 2 left, a closure with 1 left, a hair care kit with 3 left', 'Running low'],
                    ['stock-2', 'A wig\'s page: price, cost, 37.9% margin and 2 left in stock', 'Price, margin and stock']],
]);
?>
<section class="page-section" aria-labelledby="stock-title">
    <div class="wrap split">
        <div class="split-copy">
            <h2 id="stock-title">Stock for every model</h2>
            <p>Add each model as its own product, like “iPhone 12, 64GB” or “Samsung A15, 128GB”, with its cost and selling price. Add colours as options. When you sell one, it comes off your stock. When a model runs low, it shows on your Tasks to restock.</p>
            <?= site_ticks([['Add many products at once', 'from a list.'],
                            ['Stock history:', 'the last 50 changes on each product, and what caused them.'],
                            ['What your stock is worth,', 'at cost, and your margin on each model.']]) ?>
        </div>
        <div class="prose">
            <figure>
                <p class="template">iPhone 12, 64GB · 3 left
Samsung A15, 128GB · 2 left
Laptop charger, 65W · 6 left
Fast charger, 25W · 14 left
Screen guard, A15 · 1 left</p>
                <figcaption>An example of a phone shop's products, each with its own count</figcaption>
            </figure>
        </div>
    </div>
</section>

<section class="page-section tint" aria-labelledby="receipt-title">
    <div class="wrap split flip">
        <div class="split-copy">
            <h2 id="receipt-title">Receipts, warranties and part payments</h2>
            <p>Every sale gets an invoice and a receipt with your logo, sent as a link on WhatsApp. Each one has a code anyone can check at nfelia.com/verify, so a buyer can see it is genuine. A customer paying in parts shows in Debts, with a WhatsApp reminder ready on the day they promised.</p>
            <?= site_ticks([['Warranty terms:', 'write them in each product\'s description, so buyers read them on your website before they pay.'],
                            ['IMEI or serial number:', 'put it in the order\'s own note, which only your team sees.'],
                            ['Cash, transfer, POS or mobile money:', 'each payment is saved with how it came in.']]) ?>
        </div>
        <div><?= site_screens([['paid-2', 'Receipts: ₦962,000 received this month, each payment listed by customer', 'Every receipt, in one list']]) ?></div>
    </div>
</section>

<section class="page-section" aria-labelledby="staff-title">
    <div class="wrap split">
        <div class="split-copy">
            <h2 id="staff-title">Staff on the counter, suppliers on credit</h2>
            <p>Each sales person gets their own login. They sell and record payments, but only you or an admin can change an order's items, cancel it or give money back. Only you and your admins see the debts, expenses and profit.</p>
            <p>New stock on credit? Record it as a stock purchase marked unpaid. The phones go into your stock, and what you owe the supplier shows in Debts.</p>
        </div>
        <div><?= site_screens([['owed-1', 'Sales screen: ₦185,000 owed to you on 5 unpaid invoices, 2 overdue', 'Who owes you']]) ?></div>
    </div>
    <p class="stories-note">Screens from Ivie Luxe Hair, an example business.</p>
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
        <?= site_head('An example day', 'An example to show how Nfelia fits a phone shop. The customers and numbers are made up.', 'example-title') ?>
        <?= site_steps([
            ['9am: open up', 'Your Tasks say Restock Samsung A15, 2 left. You call your supplier.'],
            ['11am: a sale', 'A customer buys an iPhone 12 and a case by transfer. Both come off your stock, you write the IMEI in the order\'s note, and you send her receipt on WhatsApp in one tap.'],
            ['2pm: half now, half later', 'A regular takes a laptop and pays half. The rest goes into Debts, due on the day he promised.'],
            ['4pm: new stock on credit', 'Ten A15s arrive. You record a stock purchase marked unpaid: your stock goes up, and what you owe the supplier shows in Debts.'],
            ['The promised day', 'The laptop balance is on your Tasks, with a button that reminds him on WhatsApp.'],
        ]) ?>
    </div>
</section>

<section class="page-section tint" aria-labelledby="faq-title">
    <div class="wrap">
        <?= site_head('Questions phone shops ask', '', 'faq-title', true) ?>
        <?= site_faq([
            ['Can I track stock for each phone model?',
             'Yes. Add each model and storage size as its own product, with its own count, cost and price. It comes off your stock when you sell it, and your website shows up to ' . number_format(MAX_PRODUCTS) . ' products. ' . $link('help/stock', 'How stock works') . '.'],
            ['Does Nfelia track warranties or IMEI numbers?',
             'Not as a separate feature. Write your warranty terms in each product\'s description, so buyers see them on your website, and in the notes of a quote. Put the IMEI or serial number in the order\'s own note, which only your team sees.'],
            ['Can my sales staff use it without seeing my profit?',
             'Yes. Each person gets their own login' . $seat . '. Only you and your admins see the debts, expenses and profit, and only you or an admin can cancel an order or give money back. ' . $link('help/staff', 'Adding your staff') . '.'],
            ['Can customers see my prices online?',
             'Yes. ' . $link('features/website', 'Your website') . ' at yourname.nfelia.com lists your phones and accessories with prices, in a design such as Volt, made for gadgets. Customers order and pay into your own account, with no commission.'],
            ['How much does Nfelia cost?', $price_html],
        ]) ?>
    </div>
</section>

<?= site_related(['features/stock', 'help/stock', 'features/receipts', 'blog/track-stock', 'help/staff']) ?>
<?= site_cta('Count your stock with Nfelia') ?>
