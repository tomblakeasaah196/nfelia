<?php
/**
 * Guide: how to calculate profit (/blog/calculate-profit). The worked
 * example is one made-up shoe seller; every sum in the tables adds up, so
 * check them again if a number changes.
 *
 * From site_render: $page, $country.
 */
require_once BASE_PATH . '/views/home/_icons.php';   // nf_icon(): the layout loads it only after the view

$sections = [
    'three-numbers'   => 'Sales, profit and cash are not the same',
    'formula'         => 'The simple formula',
    'example'         => 'A worked example',
    'cash'            => 'Why your cash is not your profit',
    'forgotten-costs' => 'Costs people forget',
    'cost-per-item'   => 'Know what each item costs you',
    'monthly-check'   => 'A 20-minute monthly check',
    'nfelia'          => 'How Nfelia helps',
];
$h2   = fn(string $id) => '<h2 id="' . e($id) . '">' . e($sections[$id]) . '</h2>';
$link = fn(string $key, string $text) => site_page($key) ? '<a href="' . e(site_url($key)) . '">' . e($text) . '</a>' : e($text);
?>
<article class="wrap article-wrap has-aside">
    <header class="article-head">
        <p class="kicker">Guide</p>
        <h1><?= e($page['og']['title']) ?></h1>
        <p class="page-lead">Busy all month and still not sure where the money went? Profit is what is left after you take away what your goods cost and what it cost to run the business. Here is how to work it out, with simple numbers.</p>
        <?= site_dates($page) ?>
    </header>
    <aside class="article-aside">
        <nav class="toc" aria-labelledby="toc-title">
            <h2 id="toc-title">On this page</h2>
            <ol>
                <?php foreach ($sections as $id => $title): ?>
                <li><a href="#<?= e($id) ?>"><?= e($title) ?></a></li>
                <?php endforeach; ?>
            </ol>
        </nav>
    </aside>

    <div class="prose">
        <p>To calculate profit, take your sales for the month and subtract what the goods you sold cost you: that is your gross profit. Then subtract your running costs, such as rent, transport, data and wages: that is your net profit, what the business really made. Net profit is not the same as the money in your account, and the gap between the two is where most small businesses get confused. The worked example below shows both, step by step.</p>

        <?= $h2('three-numbers') ?>
        <p>Three numbers get mixed up all the time:</p>
        <ul>
            <li><strong>Sales:</strong> the value of everything you sold in the month, paid or not. Some people call it turnover or revenue.</li>
            <li><strong>Profit:</strong> what is left of those sales after your costs.</li>
            <li><strong>Cash:</strong> the money actually in your account, your drawer or your mobile money wallet right now.</li>
        </ul>
        <p>A business can have high sales and no profit, or a good profit and no cash. You need to know all three, and which one you are looking at.</p>

        <?= $h2('formula') ?>
        <p>The whole calculation is two lines:</p>
        <p class="template">Sales − cost of goods sold = gross profit
Gross profit − running costs = net profit</p>
        <p><strong>Cost of goods sold</strong> is what you paid for the items you sold this month, not everything you bought. If you bought 100 shirts and sold 60, only the cost of those 60 goes here. The other 40 are still stock: money sitting on your shelf. If you sell a service, such as hair, tailoring or repairs, it is the materials you used: hair, fabric, thread, parts.</p>
        <p><strong>Running costs</strong> are what it takes to keep the business open, whether you sell or not: rent, transport, data, electricity or fuel, wages, packaging, adverts, and bank or POS charges.</p>

        <?= $h2('example') ?>
        <p>Tola sells shoes from a small shop in Lagos and on Instagram. She is an example, and the numbers are kept simple. Each pair costs her ₦9,000 and sells for ₦15,000. In October she sold 60 pairs.</p>
        <div class="table-scroll">
            <table>
                <caption class="sr-only">Tola’s sales and gross profit for October</caption>
                <thead>
                    <tr><th scope="col">Line</th><th scope="col">How it is worked out</th><th scope="col">Amount</th></tr>
                </thead>
                <tbody>
                    <tr><th scope="row">Sales</th><td>60 pairs × ₦15,000</td><td>₦900,000</td></tr>
                    <tr><th scope="row">Cost of goods sold</th><td>60 pairs × ₦9,000</td><td>₦540,000</td></tr>
                    <tr><th scope="row">Gross profit</th><td>₦900,000 − ₦540,000 (40% of sales)</td><td><strong>₦360,000</strong></td></tr>
                </tbody>
            </table>
        </div>
        <p>Then her running costs for the month:</p>
        <div class="table-scroll">
            <table>
                <caption class="sr-only">Tola’s running costs for October</caption>
                <thead>
                    <tr><th scope="col">Running cost</th><th scope="col">Amount</th></tr>
                </thead>
                <tbody>
                    <tr><th scope="row">Sales assistant’s wages</th><td>₦60,000</td></tr>
                    <tr><th scope="row">Shop rent (₦600,000 a year ÷ 12)</th><td>₦50,000</td></tr>
                    <tr><th scope="row">Transport and delivery</th><td>₦30,000</td></tr>
                    <tr><th scope="row">Instagram adverts</th><td>₦20,000</td></tr>
                    <tr><th scope="row">Data and airtime</th><td>₦10,000</td></tr>
                    <tr><th scope="row">Packaging</th><td>₦8,000</td></tr>
                    <tr><th scope="row">Bank and POS charges</th><td>₦4,000</td></tr>
                    <tr><th scope="row">Total running costs</th><td><strong>₦182,000</strong></td></tr>
                </tbody>
            </table>
        </div>
        <p>So her net profit for October is ₦360,000 − ₦182,000 = <strong>₦178,000</strong>. That is about 20% of her sales: for every ₦100 a customer paid, Tola kept about ₦20.</p>
        <p>Notice what is not in the costs: money Tola took out for her own use. That comes out of the profit, not before it. Pay yourself a fixed amount each month and write it down, so you can see what stays in the business.</p>

        <?= $h2('cash') ?>
        <p>Tola made ₦178,000 profit in October, but her account only went up by ₦43,000. Nothing was stolen. Here is where the rest went:</p>
        <div class="table-scroll">
            <table>
                <caption class="sr-only">From Tola’s profit to her cash for October</caption>
                <thead>
                    <tr><th scope="col">Step</th><th scope="col">Amount</th></tr>
                </thead>
                <tbody>
                    <tr><th scope="row">Net profit</th><td>₦178,000</td></tr>
                    <tr><th scope="row">Less: extra stock for December. She bought 70 pairs (₦630,000), ₦90,000 more than the stock she sold.</th><td>−&#8288;₦90,000</td></tr>
                    <tr><th scope="row">Less: sales not yet paid for. Two customers still owe her.</th><td>−&#8288;₦45,000</td></tr>
                    <tr><th scope="row">Cash left from October</th><td><strong>₦43,000</strong></td></tr>
                </tbody>
            </table>
        </div>
        <p>The money is on her shelves and in her customers’ pockets. That is why you watch both numbers. Profit tells you whether the business makes money. Cash tells you whether you can pay next week’s bills.</p>
        <p>One habit makes both numbers easier: keep business money and personal money apart, in separate accounts or at least separate wallets. When they are mixed, neither number makes sense.</p>

        <?= $h2('forgotten-costs') ?>
        <p>Profit is often smaller than people think because small costs slip through. Check for these:</p>
        <ul>
            <li><strong>Transport:</strong> your own trips to the market or the supplier, not only deliveries.</li>
            <li><strong>Data, airtime</strong> and phone repairs.</li>
            <li><strong>Bank transfer, POS and mobile money charges.</strong></li>
            <li><strong>Power:</strong> electricity, generator fuel or solar.</li>
            <li><strong>Packaging:</strong> bags, boxes, tape and stickers.</li>
            <li><strong>“Free” delivery</strong> that you paid the rider for.</li>
            <li><strong>Goods that were damaged, expired, stolen or given away.</strong></li>
            <li><strong>Discounts</strong> you gave at the counter or in the chat.</li>
            <li><strong>Yearly costs</strong> such as rent, permits and association dues: divide by 12 and count a share every month.</li>
            <li><strong>Tools that wear out:</strong> a sewing machine, a hair dryer or a generator will need replacing. Put a little aside each month.</li>
        </ul>

        <?= $h2('cost-per-item') ?>
        <p>Profit starts with knowing what one item really costs you once it is on your shelf. Add everything you paid to get it there, then divide by the number of items. Here is how Tola worked out her ₦9,000, from one batch of shoes:</p>
        <div class="table-scroll">
            <table>
                <caption class="sr-only">The real cost of one pair of shoes</caption>
                <thead>
                    <tr><th scope="col">What she paid</th><th scope="col">Amount</th></tr>
                </thead>
                <tbody>
                    <tr><th scope="row">50 pairs from the supplier, at ₦8,500 each</th><td>₦425,000</td></tr>
                    <tr><th scope="row">Transport from the market</th><td>₦15,000</td></tr>
                    <tr><th scope="row">Packaging for 50 pairs</th><td>₦10,000</td></tr>
                    <tr><th scope="row">Total</th><td>₦450,000</td></tr>
                    <tr><th scope="row">Real cost of one pair (₦450,000 ÷ 50)</th><td><strong>₦9,000</strong></td></tr>
                </tbody>
            </table>
        </div>
        <p>If Tola used the supplier’s price of ₦8,500, she would think each pair made ₦500 more than it does, and overestimate her profit by ₦30,000 on 60 pairs. Getting this number right is also the first step to <?= $link('blog/price-your-products', 'pricing your products') ?> so every sale makes money.</p>

        <?= $h2('monthly-check') ?>
        <p>Once a month, sit down with your records for about 20 minutes:</p>
        <ol>
            <li><strong>Add up your sales</strong> for the month, paid and unpaid.</li>
            <li><strong>Work out the cost of goods sold:</strong> the number of each item sold × its real cost.</li>
            <li><strong>Add up your running costs.</strong> Go through your bank, mobile money and cash records so nothing is missed.</li>
            <li><strong>Subtract</strong> to get your gross profit, then your net profit.</li>
            <li><strong>Compare with last month.</strong> Up or down, and why?</li>
            <li><strong>Check your cash:</strong> what came in, what went out, what customers still owe you and how much stock you are holding.</li>
            <li><strong>Choose one thing to change</strong> next month: a price, a cost, or a product that does not sell.</li>
        </ol>
        <p>Write the numbers down every month. After three months you will see your best and worst months, and you will have the records a bank or lender asks for. Our guide to <?= $link('blog/business-records-for-a-loan', 'business records for a loan') ?> explains what they look for.</p>

        <?= $h2('nfelia') ?>
        <p>Nfelia does the adding up for you, from what you already record on your phone:</p>
        <ul>
            <li>Payments you record against invoices, and sales paid on the spot, count as money in.</li>
            <li>Record expenses in plain categories, such as Stock Purchase, Rent, Utilities, Transport, Salary / Wages and Marketing, and say whether you paid by cash, transfer, POS or mobile money.</li>
            <li>Your Home shows what came in this month, what you spent and what is left.</li>
            <li>The Profit and loss report covers any month or range of months, compares it with the period before and shows where the money went.</li>
            <li>It lists what customers still owe you and what you owe, so the cash picture sits next to the profit.</li>
            <li>Each product shows its cost, its price and your margin.</li>
            <li>Download the report as a PDF for your bank or accountant.</li>
        </ul>
        <?= site_screens([
            ['profit-1', 'Home: ₦962,000 came in this month, ₦695,500 spent and ₦266,500 left', 'Your month at a glance'],
            ['profit-2', 'Profit and loss for October: ₦962,000 in, ₦695,500 out, ₦266,500 left', 'Profit and loss'],
        ]) ?>
        <p>The screens are from an example business. Nfelia’s report counts money as it actually comes in and goes out, like the cash view in Tola’s example. It also says how much of your spending went on stock, which is still yours to sell, so a month of heavy restocking does not look like a loss. Read more about <?= $link('features/profit', 'expenses and profit in Nfelia') ?>, follow the steps to <?= $link('help/expenses-and-profit', 'record expenses and see your profit') ?>, or see how <?= $link('features/stock', 'stock and margins') ?> are kept.</p>
    </div>
</article>

<?= site_related(['features/profit', 'blog/price-your-products', 'blog/business-records-for-a-loan', 'features/records-book', 'blog/track-stock', 'blog/collect-money-customers-owe']) ?>
<?= site_cta('See your real profit every month') ?>
