<?php
/**
 * Help: track stock. Checked against views/products (index, view, form),
 * ProductController::adjust_stock(), app/helpers/stock.php, the orders and
 * invoices that move stock (stock_settle), ExpenseController (stock
 * purchases), planner_auto_items() (Restock in Tasks) and cron/planner.php
 * (the daily low-stock alert).
 */
require_once BASE_PATH . '/views/home/_icons.php';   // nf_icon() for the building blocks: the layout loads it only after the view
$u = fn(string $key) => e(site_url($key));
$toc = [
    'see'        => 'See what you have',
    'add'        => 'Add stock when goods come in',
    'take-out'   => 'Take stock out, or correct the count',
    'warning'    => 'Choose when Nfelia warns you',
    'automatic'  => 'What moves stock by itself',
    'history'    => 'Check an item\'s stock history',
    'problems'   => 'Common problems and fixes',
];
?>
<article class="wrap article-wrap has-aside">
    <header class="article-head">
        <p class="kicker">Help centre</p>
        <h1>Track your stock and know when to restock</h1>
        <p class="page-lead">Your stock lives in the Catalogue. Open an item and tap Add stock when goods come in, or Take out when some leave without a sale. Sales take stock off by themselves, and Nfelia warns you when an item gets down to the number you choose.</p>
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
        <p>This guide is about keeping the count right. To put your items into Nfelia in the first place, one by one or many at once, read <a href="<?= $u('help/add-products') ?>">how to add your products</a>.</p>

        <h2 id="see">See what you have</h2>
        <?= site_steps([
            ['Open the Catalogue', 'On a phone, tap <strong>More</strong> at the bottom, then <strong>Catalogue</strong>. On a computer, choose <strong>Catalogue</strong> in the menu on the left.'],
            ['Read the top line', 'You see <strong>Stock you hold, at cost</strong>: what everything on your shelves cost you. Under it, how many items need restocking, or <strong>Nothing is running low</strong>.'],
            ['Filter the list', 'Tap a chip: <strong>Running low</strong>, <strong>Out of stock</strong>, <strong>Low or out</strong> or <strong>In stock</strong>. Each item shows a tag such as <strong>Only 2 left</strong> or <strong>Out of stock</strong>. The sort menu has <strong>Least in stock</strong> first, which makes a good shopping list.'],
        ]) ?>
        <?= site_screens([
            ['stock-1', 'Stock running low in Nfelia: a frontal wig with 2 left, a closure with 1 left and a hair care kit with 3 left', 'Running low'],
            ['stock-2', 'A wig\'s page in Nfelia: its price, cost, 37.9% margin and 2 left in stock', 'Price, margin and stock'],
        ]) ?>
        <p>The screens are from Ivie Luxe Hair, an example business.</p>

        <h2 id="add">Add stock when goods come in</h2>
        <?= site_steps([
            ['Open the item', 'Tap it in the Catalogue. Its page shows <strong>In stock</strong> with the count, what it is worth at cost, and the number at which you get a reminder to restock.'],
            ['Tap Add stock', 'A sheet opens with <strong>Add stock</strong> already chosen.'],
            ['Type how many came in', 'Type the number in <strong>How many came in</strong>. The sheet reminds you how many you have now.'],
            ['Check the cost', '<strong>Cost each</strong> is what you paid for one. It is filled with the item\'s cost price; change it if this delivery cost more or less. It is optional.'],
            ['Save', 'Add a note if you like, such as the supplier\'s name, and tap <strong>Add stock</strong>. You see a message like "Added 10 bundles. You have 18 bundles of Body Wave now."'],
        ]) ?>
        <p class="note"><strong>Bought it from a supplier?</strong> Record the purchase as an expense with the category <strong>Stock purchase</strong> instead, and pick the item under <strong>Purchase items</strong>. Nfelia adds the stock and counts the money you spent, in one go. See <a href="<?= $u('help/expenses-and-profit') ?>">expenses and profit</a>.</p>

        <h2 id="take-out">Take stock out, or correct the count</h2>
        <p>Use <strong>Take out</strong> for goods that leave without a sale: damaged, expired, given away or used in the shop. Use <strong>Set the count</strong> after you count your shelves and the number in Nfelia is different.</p>
        <?= site_steps([
            ['Open the item and tap Take out', 'The same sheet opens, with three choices at the top: <strong>Add stock</strong>, <strong>Take out</strong> and <strong>Set the count</strong>.'],
            ['Choose what happened', 'For <strong>Take out</strong>, type how many to take out. For <strong>Set the count</strong>, type how many you have now; Nfelia works out the difference.'],
            ['Say why, then save', 'A short note helps later, such as "2 damaged in the rain". Tap <strong>Take out</strong> or <strong>Save the count</strong>. The new count shows at once.'],
        ]) ?>

        <h2 id="warning">Choose when Nfelia warns you</h2>
        <p>Every item has its own warning level. When the count gets down to it, the item shows as running low.</p>
        <?= site_steps([
            ['Open the item and tap Edit', 'Go down to the <strong>Stock</strong> section.'],
            ['Set Remind me at', 'Type the number at which you want to reorder. Nfelia sets it to 5 until you change it. Pick it from how fast the item sells and how long your supplier takes.'],
            ['Tap Save changes', 'From now on the item shows as running low at that number.'],
        ]) ?>
        <p>When an item is low or out, you see it in three places:</p>
        <ul>
            <li><strong>Home</strong>, under <strong>Needs you</strong>, as how many items are low or out.</li>
            <li><strong>Tasks</strong>, as "Restock" and the item's name. Ticking it hides it for a week; adding stock clears it for good.</li>
            <li>A <strong>Low stock</strong> alert on your phone, once a day in the morning, when phone alerts are on. You choose this under More, then Notifications.</li>
        </ul>

        <h2 id="automatic">What moves stock by itself</h2>
        <div class="table-scroll">
            <table>
                <caption class="sr-only">What changes your stock in Nfelia without you adjusting it</caption>
                <thead>
                    <tr><th scope="col">When you</th><th scope="col">What happens to stock</th></tr>
                </thead>
                <tbody>
                    <tr><td>Make an order in Nfelia, or confirm one from your website</td><td>Its items come off, under the order's invoice</td></tr>
                    <tr><td>Mark a draft invoice as sent</td><td>Its items come off</td></tr>
                    <tr><td>Change the items on an order</td><td>Only the difference moves</td></tr>
                    <tr><td>Cancel an order or an invoice</td><td>Its items go back on the shelf</td></tr>
                    <tr><td>Record a stock purchase expense with items</td><td>Those items are added</td></tr>
                </tbody>
            </table>
        </div>
        <p>Services, like a styling or a repair, have no stock to count: tick <strong>This is a service</strong> on the item and Nfelia leaves its count alone.</p>

        <h2 id="history">Check an item's stock history</h2>
        <p>At the bottom of each item's page, <strong>Stock history</strong> lists its last 50 changes, newest first, with the count after each one. Filter it by <strong>Added</strong>, <strong>Taken out</strong>, <strong>Sales</strong>, <strong>Returns</strong> or <strong>Corrections</strong>. When your shelf count and Nfelia disagree, this is where to look first. Our guide to <a href="<?= $u('blog/track-stock') ?>">tracking stock in a small shop</a> explains how to count and how to find missing goods.</p>
        <p>To keep a copy, tap <strong>Download the list as a spreadsheet</strong> at the foot of the Catalogue.</p>

        <h2 id="problems">Common problems and fixes</h2>
        <?= site_faq([
            ['It says "Only 3 in stock" when I try to take out more. Why?',
             'You cannot take out more than Nfelia thinks you have. If the count is wrong, use Set the count instead and type how many are really on the shelf.'],
            ['I sold something but the stock did not go down. Why?',
             'Stock comes off when the order is confirmed or the invoice is sent. A website order you have not confirmed yet, or a draft invoice, has not moved stock. Also check the item is not marked as a service.'],
            ['Can I change the count from the Edit page?',
             'No. The count box is there only when you first add an item, as its opening stock. After that, use Add stock, Take out or Set the count on the item\'s page, so every change is in the history.'],
            ['Why is "Stock you hold, at cost" lower than I expected?',
             'It counts only items with a cost price. Add the cost price to each item, and Nfelia also shows its margin.'],
            ['Can my staff add stock?',
             'Yes. Anyone on your team can open the Catalogue and add or take out stock, and every change goes into the item\'s stock history. Read <a href="' . $u('help/staff') . '">what staff can see</a>.'],
        ]) ?>
    </div>
</article>
<?= site_related(['features/stock', 'help/add-products', 'blog/track-stock', 'help/expenses-and-profit', 'blog/price-your-products', 'for/phones-and-gadgets']) ?>
<?= site_cta('Let your stock count itself') ?>
