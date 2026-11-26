<?php
/**
 * Guide: tracking stock in a small shop. Counting, a stock sheet to copy,
 * reorder levels and shrinkage, then what Nfelia's Catalogue does (checked
 * against views/products, ProductController and app/helpers/stock.php).
 */
require_once BASE_PATH . '/views/home/_icons.php';   // nf_icon() for site_related and site_cta (the layout loads it only after the view)
$u = fn(string $key) => e(site_url($key));
$toc = [
    'why'         => 'Why stock goes missing',
    'count'       => 'Start with a proper count',
    'stock-sheet' => 'A simple stock sheet to copy',
    'reorder'     => 'Set a reorder level for each item',
    'shrinkage'   => 'Find and cut shrinkage',
    'how-often'   => 'How often to count',
    'nfelia'      => 'How Nfelia helps',
    'questions'   => 'Questions people ask',
];
?>
<article class="wrap article-wrap has-aside">
    <header class="article-head">
        <p class="kicker">Guide</p>
        <h1>Know what is on your shelves: a simple way to track stock</h1>
        <p class="page-lead">To track stock in a small shop you need three things: a count you trust, a record of everything that comes in and goes out, and a number for each item that tells you when to reorder. Here is a simple way to do all three, on paper or on your phone.</p>
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
        <p>Stock is money sitting on a shelf. When you do not know how much you have, two things happen. Your best sellers run out and customers buy from the shop next door. And slow items pile up in the store, holding money you could have used. Tracking stock fixes both, and it shows you when goods are leaving without being paid for.</p>

        <h2 id="why">Why stock goes missing</h2>
        <p>Shop owners call it "shrinkage": stock that leaves the shelf without a sale being written down. It is rarely one big theft. It is usually many small things:</p>
        <ul>
            <li><strong>Sales nobody wrote down.</strong> A quick cash sale while you were busy, a transfer you meant to note later.</li>
            <li><strong>Goods taken on credit</strong> and never added to the list of who owes you.</li>
            <li><strong>Damage and expiry.</strong> A leaking tin, a torn bag, food past its date, thrown away and forgotten.</li>
            <li><strong>Items taken for home</strong>, given as samples or as a gift to a good customer.</li>
            <li><strong>Short deliveries.</strong> The supplier's invoice says 24, the carton holds 22, and nobody counted.</li>
            <li><strong>Theft</strong>, by customers or, sadly, sometimes by staff.</li>
        </ul>
        <p>You cannot fix what you cannot see. A count and a stock sheet make it visible.</p>

        <h2 id="count">Start with a proper count</h2>
        <p>Your first full count is your starting point. Everything after it is measured against it.</p>
        <ol class="steps">
            <li><div><h3>Pick a quiet time</h3><p>Before you open, after you close, or on your day off. Counting while you sell gives you a number that is already wrong.</p></div></li>
            <li><div><h3>Count shelf by shelf</h3><p>Start at one end and work round. Do the store room too. Two people work best: one counts, one writes.</p></div></li>
            <li><div><h3>Count in the unit you sell</h3><p>If you sell single tins but buy by the carton, count tins. If you sell by the carton, count cartons. Mixing units is how counts go wrong.</p></div></li>
            <li><div><h3>Write the cost beside each item</h3><p>What you paid for one. Count times cost gives what your stock is worth, which is money you have already spent.</p></div></li>
        </ol>

        <h2 id="stock-sheet">A simple stock sheet to copy</h2>
        <p>A stock sheet is a table with one row per item. For each period (a week or a month) it answers one question: how many should I have, and how many do I have?</p>
        <div class="table-scroll">
            <table>
                <caption class="sr-only">Example stock sheet for one month in a provisions shop</caption>
                <thead>
                    <tr><th scope="col">Item</th><th scope="col">Opening</th><th scope="col">In</th><th scope="col">Sold</th><th scope="col">Other out</th><th scope="col">Should have</th><th scope="col">Counted</th><th scope="col">Difference</th></tr>
                </thead>
                <tbody>
                    <tr><th scope="row">Rice, 50 kg bag</th><td>12</td><td>10</td><td>15</td><td>0</td><td>7</td><td>7</td><td>0</td></tr>
                    <tr><th scope="row">Vegetable oil, 5 litres</th><td>24</td><td>12</td><td>25</td><td>1 leaking</td><td>10</td><td>9</td><td>−1</td></tr>
                    <tr><th scope="row">Noodles, carton</th><td>30</td><td>20</td><td>38</td><td>0</td><td>12</td><td>10</td><td>−2</td></tr>
                </tbody>
            </table>
        </div>
        <p>How it works, column by column:</p>
        <ul>
            <li><strong>Opening:</strong> what you counted at the end of the last period.</li>
            <li><strong>In:</strong> what came in from suppliers, checked against their invoice.</li>
            <li><strong>Sold:</strong> from your sales book, invoices or receipts.</li>
            <li><strong>Other out:</strong> damaged, expired, samples, taken for home. Always with a reason.</li>
            <li><strong>Should have:</strong> opening + in − sold − other out.</li>
            <li><strong>Counted:</strong> what is really on the shelf.</li>
            <li><strong>Difference:</strong> counted − should have. Zero is the goal. A minus means stock left without a record.</li>
        </ul>
        <p>In the example, the rice is right, one tin of oil is missing and two cartons of noodles are gone. That is where to look first: was a carton sold on credit and not written down? Did the last delivery come short?</p>

        <h2 id="reorder">Set a reorder level for each item</h2>
        <p>A reorder level is the number that tells you "order now". When stock falls to it, you buy, and the new goods arrive before you run out. Work it out like this:</p>
        <p class="note"><strong>Reorder level = what you sell in a day × (days your supplier takes + a few spare days)</strong>
        Say you sell 3 cartons of noodles a day, your supplier takes 4 days to deliver and you keep 2 spare days for delays. 3 × (4 + 2) = 18. Order when you are down to 18 cartons.</p>
        <p>Then decide how much to order. Enough for one or two weeks is a good start for most goods: at 3 a day, two weeks is about 40 cartons. Give fast sellers higher levels and slow items lower ones, and check the levels every few months, because what sells changes with the season.</p>
        <p>If an item sells slowly, do not stock deep just because the supplier offers a discount for buying more. Money stuck in slow stock cannot buy the goods that sell.</p>

        <h2 id="shrinkage">Find and cut shrinkage</h2>
        <p>Add up the differences across the shop at cost. Say your stock sheet says you should have stock worth 850,000 at cost, and your count finds 824,500. That is 25,500 missing: 3% of your stock. Example figures; they read the same in any currency.</p>
        <p>Then cut it, one cause at a time:</p>
        <ul>
            <li><strong>Write every sale, cash ones too.</strong> If a sale is not recorded, it looks the same as a theft on your stock sheet.</li>
            <li><strong>Record credit at the moment you give it.</strong> Goods on credit are sold goods with money still owed, not missing goods.</li>
            <li><strong>Write out damaged and expired stock</strong> with a reason before you throw it away.</li>
            <li><strong>Count deliveries before you pay.</strong> Check the cartons against the supplier's invoice while the delivery person is still there.</li>
            <li><strong>Spot-check expensive small items</strong> often. Phones, perfume, hair bundles and phone accessories are easy to pocket.</li>
            <li><strong>Give each staff member their own record</strong> of what they sold, so a shortfall can be traced to a shift.</li>
        </ul>

        <h2 id="how-often">How often to count</h2>
        <ul>
            <li><strong>A full count</strong> every month, or every three months in a quiet shop.</li>
            <li><strong>A spot check every week</strong> of your ten most valuable or fastest-selling items. It takes ten minutes.</li>
            <li><strong>A daily count</strong> of small, high-value items, such as phones in a gadget shop.</li>
        </ul>
        <p>The more often you count, the smaller each difference is, and the easier it is to remember what happened.</p>

        <h2 id="nfelia">How Nfelia helps</h2>
        <p>Nfelia keeps the stock sheet for you, from the sales and purchases you already record. Every item in your Catalogue has its own stock count:</p>
        <ul>
            <li><strong>Sales take stock off.</strong> When you send an invoice, the items on it come off your stock. Cancel the invoice and they go back.</li>
            <li><strong>Purchases put stock in.</strong> Record buying goods as a Stock Purchase expense and pick the items: they are added to your stock with what you paid.</li>
            <li><strong>Add stock, Take out, or Set the count.</strong> Each change can carry a note, such as "damaged" or "new stock from the supplier". After a count, Set the count fixes the number and the change shows as "Count corrected".</li>
            <li><strong>A stock history for every item.</strong> The item's page lists its latest changes, each with the date, the note and who made it. This is your "other out" column, kept for you.</li>
            <li><strong>Your reorder level.</strong> Type it in "Remind me at" and Nfelia reminds you to restock when the item gets down to that number. The "Low or out" list shows everything that needs restocking.</li>
            <li><strong>What your stock is worth.</strong> Add a cost price and each item shows its stock value at cost.</li>
            <li><strong>Many products at once.</strong> Add them from a list, and download your whole stock list as a file whenever you need it. Your website shows up to <?= e(number_format(MAX_PRODUCTS)) ?> of them.</li>
        </ul>
        <?= site_screens([
            ['stock-1', 'Stock running low in Nfelia: a frontal wig with 2 left, a closure with 1 left and a hair care kit with 3 left', 'Running low'],
            ['stock-2', 'A wig\'s page in Nfelia: its price, cost, 37.9% margin and 2 left in stock', 'Price, margin and stock'],
        ]) ?>
        <p>Nfelia counts what you record. It cannot see goods that leave without a sale, so you still need to count your shelves. The difference is that you compare your count with a number Nfelia already worked out, and the stock history shows where to look. The screens are from Ivie Luxe Hair, an example business. Read more about <a href="<?= $u('features/stock') ?>">stock tracking in Nfelia</a>, or follow the <a href="<?= $u('help/stock') ?>">stock help guide</a> and the guide to <a href="<?= $u('help/add-products') ?>">adding your products</a>.</p>

        <h2 id="questions">Questions people ask</h2>
        <?= site_faq([
            ['What is a reorder level?',
             'The stock number at which you order more, so new goods arrive before you run out. Work it out as what you sell in a day times the days your supplier takes, plus a few spare days. At 3 a day, a 4-day delivery and 2 spare days, reorder at 18.'],
            ['What is shrinkage in a shop?',
             'Stock that leaves without a sale being recorded: theft, damage, expiry, short deliveries, goods taken for home, and sales or credit nobody wrote down. Find it by comparing what you should have with what you count.'],
            ['Should I value my stock at cost or at selling price?',
             'At cost, for what your stock is worth to you: it is money you have already spent. The selling price shows what it could bring in if it all sells. Our guide to <a href="' . $u('blog/price-your-products') . '">pricing your products</a> explains the gap between the two.'],
        ]) ?>
    </div>
</article>
<?= site_related(['features/stock', 'help/stock', 'help/add-products', 'blog/price-your-products', 'for/phones-and-gadgets', 'blog/calculate-profit']) ?>
<?= site_cta('Let your stock count itself') ?>
