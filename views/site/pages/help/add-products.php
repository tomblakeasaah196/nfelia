<?php
/**
 * Help: add products. The Catalogue (views/products/index.php), the item
 * form (views/products/form.php, ProductController::create), an item's page
 * (views/products/view.php) and Catalogue Setup with Quick Add Items and the
 * CSV import (views/products/setup.php, ProductController::setup/import).
 *
 * The import reads the header row as typed, lower-cased: a file saved as
 * "CSV UTF-8" starts with a byte-order mark that hides a first column called
 * Name, and prices are read as plain numbers (no commas, no currency sign).
 * The problems below say so in the owner's words.
 *
 * From site_render: $page, $country.
 */
require_once BASE_PATH . '/views/home/_icons.php';   // the view runs before the layout loads it

$to  = fn(string $key, string $text) => site_page($key) ? '<a href="' . e(site_url($key)) . '">' . e($text) . '</a>' : e($text);
$toc = [
    'catalogue' => 'Where your products live',
    'add-one'   => 'Add one item',
    'add-many'  => 'Add many items at once',
    'csv'       => 'Import a spreadsheet',
    'on-shop'   => 'Choose what shows on your website',
    'change'    => 'Change an item later',
    'problems'  => 'Common problems',
];
$columns = [
    ['Name', 'Yes', 'Ankara fabric', 'Each name once. A name already in your catalogue is skipped.'],
    ['SKU', 'No', 'ANK-0012', 'Your item code. Left empty, Nfelia makes one.'],
    ['Category', 'No', 'Fabrics', 'Groups items on your website.'],
    ['Description', 'No', 'Premium quality ankara', 'Shows on your website, invoices and quotes.'],
    ['Sell Price', 'No', '3500', 'A plain number: no commas, no currency sign.'],
    ['Cost Price', 'No', '2000', 'What it costs you. Used for your margin and stock value.'],
    ['Stock Qty', 'No', '50', 'How many you have now.'],
    ['Unit', 'No', 'yards', 'How you count it, like pcs, bundles or kg.'],
    ['Is Service', 'No', 'No', 'Yes for a service, which has no stock.'],
];
?>
<article class="wrap article-wrap has-aside">
    <header class="article-head">
        <p class="kicker">Help centre</p>
        <h1><?= e($page['og']['title']) ?></h1>
        <p class="page-lead">Add what you sell once, and Nfelia uses it everywhere: on your website, on orders and invoices, and in your stock count.</p>
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
        <p>To add a product in Nfelia, tap <strong>+</strong>, then <strong>Add product</strong>. Add photos, a name and a selling price, and how many you have, then tap <strong>Add to catalogue</strong>. To add many products at once, open <strong>More › Catalogue</strong>, tap <strong>Add many items at once</strong>, and type them in one after another or import a spreadsheet.</p>

        <h2 id="catalogue"><?= e($toc['catalogue']) ?></h2>
        <p>Everything you sell lives in your <strong>Catalogue</strong>: products you keep in stock and services you do for people. Open it from <strong>More › Catalogue</strong> on a phone, or <strong>Catalogue</strong> in the menu on a computer.</p>
        <p>At the top you see <strong>Stock you hold, at cost</strong> and how many items need restocking. Below, each item shows its price and stock, like <strong>Only 2 left</strong> or <strong>Out of stock</strong>. The chips above the list show <strong>Running low</strong>, <strong>Out of stock</strong>, <strong>Services</strong> and more.</p>

        <h2 id="add-one"><?= e($toc['add-one']) ?></h2>
        <?= site_steps([
            ['Open the form', 'Tap <strong>+</strong> at the bottom of the screen, then <strong>Add product</strong> (<strong>Add service</strong> if you only sell services). Or tap <strong>Add item</strong> at the top of the Catalogue.'],
            ['Add photos', 'Under <strong>Photos</strong>, tap <strong>Add photos</strong> and pick one or more: JPG, PNG or WebP, up to 20 MB each. The first one is the cover on your website. You can make another photo the <strong>Cover</strong> later.'],
            ['Say whether it shows on your website', '<strong>Show on my shop</strong> is on for a new item. Turn it off for something you only put on invoices and orders.'],
            ['Name it', 'Under <strong>About it</strong>, type the <strong>Name</strong> the way customers ask for it, like <em>Body wave bundle, 18 inches</em>. A <strong>Category</strong> groups it on your website. A <strong>Description</strong> shows on your website, invoices and quotes.'],
            ['Set the price', 'Under <strong>Price</strong>, type the <strong>Selling price</strong>. Add the <strong>Cost price</strong> too, what it costs you, and Nfelia shows your margin: your profit as a share of the price.'],
            ['Say how many you have', 'Under <strong>Stock</strong>, type <strong>How many you have now</strong>, choose what it is <strong>Counted in</strong> (pieces, bundles, yards, kg and more), and set <strong>Remind me at</strong>: Nfelia warns you when stock falls to that number. For work like a styling or a repair, turn on <strong>This is a service</strong> instead: there is no stock to count.'],
            ['Add options, if it has them', 'Tap <strong>Add options, like length or colour</strong>. Name the option, like <em>Length</em>, and add each choice, like <em>16 inches</em>. Turn on <strong>This changes the price</strong> if a choice costs more or less, and type how much each adds or takes off.'],
            ['Tap Add to catalogue', 'You see <strong>… added to your catalogue</strong> and the item\'s own page, with its price, margin, stock and <strong>Stock history</strong>. If <strong>Show on my shop</strong> was on, it is on your website already.'],
        ]) ?>
        <?= site_screens([['stock-2', 'An item\'s page in Nfelia: a wig with its price, its cost, a 37.9% margin and 2 left in stock', 'An item\'s page'], ['stock-1', 'Stock running low: a frontal wig with 2 left, a closure with 1 left and a hair care kit with 3 left', 'What is running low']]) ?>
        <p class="meta">Screens from Ivie Luxe Hair, an example business.</p>
        <p>An item code is made for you, like ANK-0012: the first three letters of the name and a number. Under <strong>Item code</strong>, turn off <strong>Make the code for me</strong> to type your own. If your business sells services, a <strong>Bookings</strong> section lets customers book the service on your website: see <?= $to('help/bookings', 'how bookings work') ?>.</p>
        <div class="note"><strong>Fill from a photo</strong> If you see <strong>Fill from a photo</strong> on the form, take or pick a photo of the item and Nfelia writes its name, a short description and a category for you. Check every word before you save.</div>

        <h2 id="add-many"><?= e($toc['add-many']) ?></h2>
        <p>Open <strong>More › Catalogue</strong> and tap <strong>Add many items at once</strong> at the bottom of the list. In an empty catalogue, tap <strong>Or add many at once</strong>. You land on <strong>Catalogue Setup</strong>, with two ways to add many items:</p>
        <?= site_steps([
            ['Quick Add Items: type them one after another', 'Open <strong>Quick Add Items</strong>. Type the <strong>Name</strong>, a <strong>Category</strong>, the <strong>Sell Price</strong>, <strong>Cost Price</strong> and <strong>Stock Qty</strong>, and pick the <strong>Unit</strong>. Tick <strong>Service (no stock)</strong> for a service. Tap <strong>Add &amp; Continue</strong>: you see <strong>… added</strong>, and it joins <strong>Recently Added</strong> on the same screen. Open <strong>Quick Add Items</strong> again for the next one.'],
            ['Bulk Import (CSV): bring in a spreadsheet', 'Best when your price list is already in Excel or Google Sheets. The next section shows how.'],
        ]) ?>
        <p><strong>Product Categories</strong> on the same screen suggests categories for your kind of business. A category is made the moment you give it to an item, so there is nothing to set up first.</p>

        <h2 id="csv"><?= e($toc['csv']) ?></h2>
        <?= site_steps([
            ['Make the sheet', 'In Excel or Google Sheets, put these words in the first row, one per column, then one item per row below. Only <strong>Name</strong> is needed.'],
            ['Save it as a CSV file', 'In Google Sheets: <strong>File › Download › Comma-separated values (.csv)</strong>. In Excel: <strong>Save As</strong>, then <strong>CSV (Comma delimited)</strong>.'],
            ['Import it', 'On Catalogue Setup, open <strong>Bulk Import (CSV)</strong>, tap <strong>Choose CSV file...</strong>, pick your file and tap <strong>Import</strong>.'],
            ['Check the result', 'You see how many were imported and how many were skipped, like <strong>48 products imported. 2 skipped (duplicates or empty rows).</strong> Your new items are in the Catalogue and on your website. Add photos to each from its page: tap the item, then <strong>Edit</strong>.'],
        ]) ?>
        <div class="table-scroll">
            <table>
                <caption class="sr-only">The columns Nfelia reads from a CSV file</caption>
                <thead>
                    <tr><th scope="col">Column</th><th scope="col">Needed</th><th scope="col">Example</th><th scope="col">What it is</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($columns as [$col, $needed, $example, $what]): ?>
                    <tr><th scope="row"><?= e($col) ?></th><td><?= e($needed) ?></td><td><?= e($example) ?></td><td><?= e($what) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p>Want to see the columns filled in? In the Catalogue, tap <strong>Download the list as a spreadsheet</strong>: you get every item you have, in the same columns.</p>

        <h2 id="on-shop"><?= e($toc['on-shop']) ?></h2>
        <p>Every item in your Catalogue has a small <strong>Shop</strong> switch on its row. On, it shows on your website. Off, it stays in your catalogue and on invoices, quotes and orders, but your website doesn't show it. The same switch is <strong>Show on my shop</strong> on the item's page, and the ticks under <strong>Everything you sell</strong> in Shop Studio. It saves at once: there is nothing to publish.</p>
        <ul>
            <li>To see what is off your website, tap the <strong>Not on your shop</strong> chip in the Catalogue.</li>
            <li><strong>Hide</strong> on an item's page takes it off your website and out of the lists you pick from on orders. <strong>Show again</strong> brings it back.</li>
            <li>Shop Studio can show items with no stock left as <strong>Sold out</strong>. See <?= $to('help/your-website', 'your website') ?>.</li>
        </ul>

        <h2 id="change"><?= e($toc['change']) ?></h2>
        <p>Tap the item in the Catalogue to open its page. Tap <strong>Edit</strong> to change its photos, name, price or options, then <strong>Save changes</strong>. A new price shows on your website within a few minutes; invoices already sent keep their price.</p>
        <p>For stock, use <strong>Add stock</strong> when goods come in, <strong>Take out</strong> for damaged or used goods, or <strong>Set the count</strong> after you count the shelf. Sales take stock off by themselves. The guide to <?= $to('help/stock', 'tracking stock') ?> explains more. <strong>Clone</strong> makes a copy to edit, handy for the same item in another size.</p>

        <h2 id="problems"><?= e($toc['problems']) ?></h2>
        <?= site_faq([
            ['It says "CSV must have a Name column"', 'The first row of your file needs a column called Name. If it is there and you still see this, the file was saved as "CSV UTF-8". Save it again as <strong>CSV (Comma delimited)</strong> in Excel, or download it from Google Sheets as <strong>Comma-separated values</strong>, and import it again.'],
            ['My prices came in wrong, like 3 instead of 3,500', 'Type prices as plain numbers in the sheet: 3500, with no commas, spaces or currency sign. Fix the items that came in wrong from their page (<strong>Edit</strong>), or delete them and import a corrected file.'],
            ['Some rows were skipped', 'A row is skipped when its Name is empty, when an item with the same name is already in your catalogue, or when a price or stock is below zero. The message after the import says how many and why.'],
            ['It says "A product with this name already exists"', 'Each name is used once. Add what makes it different, like the size, or use <strong>Add options, like length or colour</strong> on the one item.'],
            ['An item is not on my website', 'Check its <strong>Shop</strong> switch is on and that it is not hidden. If Shop Studio shows sold-out items and its stock is 0, it shows as sold out until you add stock. Things people book show under Bookings on your website, not in the bag.'],
            ['A photo did not upload', 'Use a JPG, PNG or WebP photo up to 20 MB. On a weak network, try again on Wi-Fi or a stronger signal.'],
        ]) ?>
        <p>Still stuck? <a href="<?= e(site_wa('Hello Nfelia, I need help adding my products.')) ?>" target="_blank" rel="noopener">Send us your price list on WhatsApp</a> and we will help you get it in.</p>
    </div>
</article>

<?= site_related(['help/your-website', 'help/stock', 'features/stock', 'help/website-orders']) ?>
<?= site_cta('Put your price list online today') ?>
