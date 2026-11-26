<?php
/**
 * Feature: stock. Checked against ProductController (the catalogue, Add
 * stock / Take out / Set the count, import and export), app/helpers/stock.php
 * (every change written down; documents undo exactly what they moved),
 * orders.php and InvoiceController (a sale takes stock off), ExpenseController
 * (stock purchases bring it in), cron/planner.php (the daily low-stock alert)
 * and storefront.php (sold out on the website).
 */
require_once BASE_PATH . '/views/home/_icons.php';   // nf_icon(): the layout loads it only after the view

$fs_a = fn(string $key, string $text): string => site_page($key) ? '<a href="' . e(site_url($key)) . '">' . e($text) . '</a>' : e($text);

echo site_hero([
    'kicker'    => 'Stock',
    'h1'        => 'Stock that counts itself',
    'lead_html' => 'Nfelia is an inventory app for small shops. Add what you sell once, and every sale takes it off your stock. You see what is running low before customers ask, what your stock is worth, and who changed what, all on your phone.',
    'screens'   => [
        ['stock-1', 'Stock running low: a frontal wig with 2 left, a closure with 1 left, a hair care kit with 3 left', 'Running low'],
        ['stock-2', 'A wig’s page: price, cost, 37.9% margin and 2 left in stock', 'Price, margin and stock'],
    ],
]);
?>

<section class="page-section" aria-labelledby="fs-add"><div class="wrap split">
    <div class="split-copy">
        <h2 id="fs-add">Add your products once</h2>
        <p>Tap <strong>Add item</strong> and fill in what you would tell a new sales assistant: what it is, what it sells for and how many you have. Nfelia does the rest.</p>
        <?= site_ticks([
            ['Photos, name and category', 'the same item shows on your invoices, your quotes and your website.'],
            ['Selling price and cost price', 'and Nfelia shows your margin, your profit as a share of the price.'],
            ['How many you have now', 'saved as your opening stock.'],
            ['Counted in your own unit', 'pieces, bundles, yards, metres, cartons, packs, crates, bottles, dozens, kilograms, litres and more.'],
            ['Remind me at', 'the number that means “time to restock”. It starts at 5; set it per item.'],
            ['Options', 'like length, colour or size, each with its own price if you like. Options share the item’s one stock count.'],
        ]) ?>
        <p>Selling a service, like styling or a repair? Switch on <strong>This is a service</strong> and there is no stock to count. Got a long list? <strong>Add many items at once</strong> from a spreadsheet: only the name is needed, and price, cost, stock and unit come in too. You can download your list as a spreadsheet any time.</p>
    </div>
    <div class="split-copy">
        <h2 id="fs-sale">Every sale takes it off</h2>
        <p>You never count down by hand after a sale. When an order is confirmed, the items on its invoice come off your stock. If anything changes, Nfelia moves only the difference:</p>
        <div class="compare table-scroll">
            <table>
                <caption class="sr-only">What changes your stock in Nfelia</caption>
                <thead><tr><th scope="col">When this happens</th><th scope="col">Your stock</th></tr></thead>
                <tbody>
                    <tr><th scope="row">An order is confirmed, in the shop or from your website</th><td>Goes down by what was sold</td></tr>
                    <tr><th scope="row">The items on an order are changed</th><td>Moves by the difference only</td></tr>
                    <tr><th scope="row">An order is cancelled</th><td>Gets back exactly what it lost</td></tr>
                    <tr><th scope="row">You record a stock purchase from a supplier</th><td>Goes up by what you bought</td></tr>
                    <tr><th scope="row">You tap Add stock, Take out or Set the count</th><td>Changes as you say, with your note</td></tr>
                </tbody>
            </table>
        </div>
        <p>Each item keeps a <strong>Stock history</strong> of its last 50 changes: how many, the count after, why, and who did it. When staff sell, their sales come off too, so goods that go missing show up quickly.</p>
    </div>
</div></section>

<section class="page-section tint" aria-labelledby="fs-low"><div class="wrap">
    <?= site_head('Know before you run out', 'Your catalogue opens on what matters: <strong>Stock you hold, at cost</strong>, how many items you have, and how many need restocking. Tap a filter to see only what is <strong>Running low</strong>, <strong>Out of stock</strong>, or both.', 'fs-low', true) ?>
    <ul class="facts">
        <li><strong>Every change</strong><span>written down, with who made it and why.</span></li>
        <li><strong>At cost</strong><span>the value of what is on your shelves, worked out for you.</span></li>
        <li><strong>Once a day</strong><span>a low-stock warning, never a buzz for every sale.</span></li>
    </ul>
    <div class="compare split-copy">
        <?= site_ticks([
            ['A daily warning', 'once a day from 9am Nigeria time (8am in Ghana, 11am in Kenya), you and your team hear which items are running low or out, the most urgent first.'],
            ['On your Home', 'items to restock are part of what needs you today.'],
            ['On your website, if you want', 'switch on “Show items as sold out when their stock runs out” in Shop Studio. Customers then see Sold out and an Ask when it’s back button for WhatsApp, instead of ordering what you don’t have.'],
        ]) ?>
        <p>Restocking is cheaper when you know what sells. For a simple routine, see <?= $fs_a('blog/track-stock', 'how to track stock in a small shop') ?>, and to set prices that leave a profit, <?= $fs_a('blog/price-your-products', 'how to price your products') ?>.</p>
    </div>
</div></section>

<section class="page-section" aria-labelledby="fs-joined"><div class="wrap">
    <?= site_head('Joined to everything else', 'Stock in Nfelia is not a separate spreadsheet. The same items run through your whole business.', 'fs-joined') ?>
    <ul class="fits">
        <li><strong><?= $fs_a('features/invoices', 'Invoices and quotes') ?></strong><span>On a new order, pick items from your stock with the price filled in and how many you have shown, so you never sell what is not there.</span></li>
        <li><strong><?= $fs_a('features/website', 'Your website') ?></strong><span>Your catalogue is your shop. Turn off <strong>Show on my shop</strong> to keep an item off it.</span></li>
        <li><strong><?= $fs_a('features/profit', 'Expenses and profit') ?></strong><span>Stock bought from suppliers is an expense, and comes into your stock at the same time.</span></li>
        <li><strong><?= $fs_a('features/records-book', 'The Records Book') ?></strong><span>Your stock is in the Excel workbook of all your records, ready to show your bank.</span></li>
    </ul>
</div></section>

<section class="page-section rose" aria-labelledby="fs-who"><div class="wrap">
    <?= site_head('Who it helps', 'Shops where every item counts.', 'fs-who') ?>
    <ul class="fits">
        <li><strong><?= $fs_a('for/phones-and-gadgets', 'Phones and gadgets') ?></strong><span>Each model with its own count, and a warning before the popular one runs out.</span></li>
        <li><strong><?= $fs_a('for/online-sellers', 'Online sellers') ?></strong><span>Hair, clothes, shoes and beauty, counted down as orders come in.</span></li>
        <li><strong><?= $fs_a('for/fashion-designers', 'Fashion designers') ?></strong><span>Fabric in yards, so you know what is left before you take the next job.</span></li>
        <li><strong><?= $fs_a('for/food-and-catering', 'Food and catering') ?></strong><span>Packs, crates and bottles, bought as stock purchases and counted.</span></li>
    </ul>
</div></section>

<section class="page-section tint" aria-labelledby="faq-title"><div class="wrap">
    <?= site_head('Questions people ask', '', 'faq-title', true) ?>
    <?= site_faq([
        ['How does the stock go down when I sell?',
         'When an order is confirmed, its invoice is made and the items on it come off your stock. Cancel the order and exactly that much goes back.'],
        ['Can I add all my products at once?',
         'Yes. In your catalogue, tap Add many items at once and upload a spreadsheet saved as CSV. Only the name is needed; price, cost, stock, unit and category can come in too.'],
        ['Does it count sizes and colours separately?',
         'No. Options like size or colour share the item’s one stock count. If you need a separate count for each, add each one as its own item.'],
        ['Will it tell me when something is running low?',
         'Yes. Set the Remind me at number on each item. Once a day from 9am Nigeria time (8am in Ghana, 11am in Kenya), you hear which items are at or below it, and your catalogue can show only those.'],
        ['Can I see what my stock is worth?',
         'Yes. Your catalogue shows the stock you hold at cost, and each item’s page shows its price, cost, margin and how many are left.'],
        ['What if my count is wrong after a stock check?',
         'Open the item and tap Set the count to the number you counted. The change goes into its stock history with your note.'],
    ]) ?>
</div></section>

<?= site_related(['help/stock', 'help/add-products', 'blog/track-stock', 'blog/price-your-products', 'features/profit', 'features/website']) ?>
<?= site_cta('Let your stock count itself') ?>
