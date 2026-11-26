<?php
/**
 * Guide: pricing products and services. True cost, markup against margin
 * (worked tables), running costs, pricing by time, when to raise prices,
 * then what Nfelia shows (the item form's cost, price and margin:
 * views/products/form.php and view.php; margin = profit ÷ selling price).
 */
require_once BASE_PATH . '/views/home/_icons.php';   // nf_icon() for site_related and site_cta (the layout loads it only after the view)
$u = fn(string $key) => e(site_url($key));
$toc = [
    'true-cost'   => 'Work out what one item really costs',
    'markup'      => 'Markup and margin are not the same',
    'from-margin' => 'Set a price from the margin you want',
    'running'     => 'Cover your running costs',
    'services'    => 'Pricing services by time',
    'market'      => 'Check the market, but do not just copy',
    'raise'       => 'When to raise your prices',
    'nfelia'      => 'How Nfelia helps',
    'questions'   => 'Questions people ask',
];
?>
<article class="wrap article-wrap has-aside">
    <header class="article-head">
        <p class="kicker">Guide</p>
        <h1>Price your products so every sale makes you money</h1>
        <p class="page-lead">To price a product, add up everything one item really costs you, then add a margin that pays your running costs and leaves a profit. Here is how, with markup and margin worked out side by side, a way to price services by the hour, and the signs it is time to raise your prices.</p>
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
        <p>It is tempting to price by looking at what the shop next door charges and going a little lower. It feels safe. But the shop next door may pay less for its goods, pay no rent, or be losing money without knowing it. Your price has to start from your own costs. The examples below use plain numbers: they work the same in naira, cedis, shillings or CFA francs.</p>

        <h2 id="true-cost">Work out what one item really costs</h2>
        <p>The cost of an item is not only what you paid the supplier. It is everything it took to get that item onto your shelf and into the customer's hands. Say you sell hair bundles:</p>
        <div class="table-scroll">
            <table>
                <caption class="sr-only">Working out the true cost of one hair bundle</caption>
                <thead>
                    <tr><th scope="col">Cost</th><th scope="col">How it is worked out</th><th scope="col" class="num">For one bundle</th></tr>
                </thead>
                <tbody>
                    <tr><th scope="row">Buying price</th><td>10 bundles for 45,000</td><td class="num">4,500</td></tr>
                    <tr><th scope="row">Transport to bring them in</th><td>1,500 for the trip, shared by 10</td><td class="num">150</td></tr>
                    <tr><th scope="row">Packaging</th><td>A branded bag and tag</td><td class="num">50</td></tr>
                    <tr><th scope="row">Payment charges</th><td>Transfer or POS charges, on average</td><td class="num">100</td></tr>
                    <tr><th scope="row">True cost</th><td></td><td class="num"><strong>4,800</strong></td></tr>
                </tbody>
            </table>
        </div>
        <p>If you priced from the 4,500 alone, you would lose 300 on every bundle before you started. Add any loss you expect too: if one bundle in twenty gets damaged, the other nineteen must pay for it.</p>

        <h2 id="markup">Markup and margin are not the same</h2>
        <p>Both say how much profit is in a price, but they measure it against different things:</p>
        <ul>
            <li><strong>Markup</strong> is profit as a share of the <em>cost</em>. Profit ÷ cost × 100.</li>
            <li><strong>Margin</strong> is profit as a share of the <em>selling price</em>. Profit ÷ selling price × 100.</li>
        </ul>
        <p>The same sale always has a higher markup than margin. Here is an item that costs 1,000, at four prices:</p>
        <div class="table-scroll">
            <table>
                <caption class="sr-only">Markup and margin for an item costing 1,000 at four selling prices</caption>
                <thead>
                    <tr><th scope="col" class="num">Cost</th><th scope="col" class="num">Selling price</th><th scope="col" class="num">Profit</th><th scope="col" class="num">Markup</th><th scope="col" class="num">Margin</th></tr>
                </thead>
                <tbody>
                    <tr><th scope="row" class="num">1,000</th><td class="num">1,250</td><td class="num">250</td><td class="num">25%</td><td class="num">20%</td></tr>
                    <tr><th scope="row" class="num">1,000</th><td class="num">1,500</td><td class="num">500</td><td class="num">50%</td><td class="num">33.3%</td></tr>
                    <tr><th scope="row" class="num">1,000</th><td class="num">2,000</td><td class="num">1,000</td><td class="num">100%</td><td class="num">50%</td></tr>
                    <tr><th scope="row" class="num">1,000</th><td class="num">3,000</td><td class="num">2,000</td><td class="num">200%</td><td class="num">66.7%</td></tr>
                </tbody>
            </table>
        </div>
        <p class="note"><strong>The trap</strong>
        You want 30% of every sale to be profit, so you add 30% to the cost. On the 4,800 bundle that gives 6,240. But the profit, 1,440, is only 23% of 6,240. Adding 30% to the cost gives you a 30% markup, not a 30% margin.</p>

        <h2 id="from-margin">Set a price from the margin you want</h2>
        <p>To get the margin you want, divide the cost by what is left after the margin:</p>
        <p class="note"><strong>Selling price = cost ÷ (1 − the margin you want)</strong>
        For a 30% margin on the 4,800 bundle: 4,800 ÷ 0.70 = 6,857. Round it to a neat price, 6,900.</p>
        <div class="table-scroll">
            <table>
                <caption class="sr-only">Selling prices for an item costing 4,800 at four margins</caption>
                <thead>
                    <tr><th scope="col" class="num">Margin you want</th><th scope="col">Sum</th><th scope="col" class="num">Selling price</th><th scope="col" class="num">Profit on each</th></tr>
                </thead>
                <tbody>
                    <tr><th scope="row" class="num">20%</th><td>4,800 ÷ 0.80</td><td class="num">6,000</td><td class="num">1,200</td></tr>
                    <tr><th scope="row" class="num">30%</th><td>4,800 ÷ 0.70</td><td class="num">6,900 (rounded from 6,857)</td><td class="num">2,100</td></tr>
                    <tr><th scope="row" class="num">40%</th><td>4,800 ÷ 0.60</td><td class="num">8,000</td><td class="num">3,200</td></tr>
                    <tr><th scope="row" class="num">50%</th><td>4,800 ÷ 0.50</td><td class="num">9,600</td><td class="num">4,800</td></tr>
                </tbody>
            </table>
        </div>

        <h2 id="running">Cover your running costs</h2>
        <p>Your margin has to pay for everything that is not the item itself: rent, light and fuel, data, transport for errands, a helper's pay. Add up a normal month. Say it comes to 160,000. At 6,900 a bundle you make 2,100 on each. 160,000 ÷ 2,100 = 76.2, so you need to sell 77 bundles a month just to cover your running costs. Every bundle after that is profit.</p>
        <p>If 77 a month sounds out of reach, you have three choices: raise the price, cut running costs, or sell more of something with a better margin. It is much better to know this now than at the end of a busy month with nothing left. Our guide on <a href="<?= $u('blog/calculate-profit') ?>">how to calculate your profit</a> shows how to check it month by month.</p>

        <h2 id="services">Pricing services by time</h2>
        <p>If you sell a service, such as hair, tailoring, repairs or make-up, your time is the product. Price it by the hour first:</p>
        <ol class="steps">
            <li><div><h3>Decide what you need to earn</h3><p>What you want to take home in a month, plus your running costs. Say 150,000 + 90,000 = 240,000.</p></div></li>
            <li><div><h3>Count the hours you can really charge for</h3><p>You may be at the shop 200 hours a month, but some of that is waiting, cleaning and buying supplies. Say 120 hours are paid work.</p></div></li>
            <li><div><h3>Divide</h3><p>240,000 ÷ 120 = 2,000 an hour. That is your hourly rate.</p></div></li>
            <li><div><h3>Price each service</h3><p>Hours it takes × your rate, plus materials. Braids that take 5 hours with extensions costing 3,500: 5 × 2,000 + 3,500 = 13,500. Round it to 14,000.</p></div></li>
        </ol>
        <p>Set a minimum charge for small jobs, so a ten-minute fix does not cost you an hour of setting up. If you go to the customer, add a travel fee.</p>

        <h2 id="market">Check the market, but do not just copy</h2>
        <p>Once you know your own floor price, look at what others charge. If you are far above them, ask why: is your cost too high, or is your product better? If you are far below, you may be leaving money on the table. You do not have to be the cheapest. Customers also pay for reliability, quick replies, good packaging, delivery and a proper receipt.</p>
        <p>Bundles help too: three items at a small discount sell more and cost you less to serve than three separate sales.</p>

        <h2 id="raise">When to raise your prices</h2>
        <p>Raise your prices when:</p>
        <ul>
            <li><strong>Your costs went up.</strong> Your supplier's price, transport, rent or the exchange rate moved. Work out the new true cost and the new price at once.</li>
            <li><strong>You are always fully booked</strong> or always sold out. Demand is telling you the price is low.</li>
            <li><strong>Your profit is shrinking</strong> even though sales are steady.</li>
            <li><strong>You have not changed your prices in a year</strong> while everything you buy has changed.</li>
        </ul>
        <p>How to do it without losing customers: tell regulars before the change, keep any quote you have already given, raise in steps rather than one big jump, and update your price list and website the same day. A short message is enough:</p>
        <div class="template">Hello [name], thank you for buying from us. From [date], [item] will be [new price], because what we pay our suppliers has gone up. Anything you pay for before [date] stays at the old price. Thank you for understanding. [Your business name]</div>
        <p>If your business is registered for VAT, decide whether your prices include it and show it clearly on your invoices. Rules differ by country, so check with your tax office: the Nigeria Revenue Service, the Ghana Revenue Authority, the Kenya Revenue Authority or Cameroon's Direction Générale des Impôts.</p>

        <h2 id="nfelia">How Nfelia helps</h2>
        <ul>
            <li><strong>Cost and price on every item.</strong> Type the Selling price and the Cost price, and Nfelia shows your margin: your profit as a share of the price. If an item sells for less than it costs you, the margin turns red. The cost is only for you; customers never see it.</li>
            <li><strong>Options with their own prices.</strong> If an item comes in different lengths, colours or sizes, each option can change the price.</li>
            <li><strong>One price, everywhere.</strong> Change a price once: your <a href="<?= $u('features/website') ?>">website</a> shows it, and new quotes and invoices use it. Leave the price empty and your website shows "Ask for the price".</li>
            <li><strong>See whether your prices work.</strong> Record your spending as <a href="<?= $u('features/profit') ?>">expenses</a>, and the Profit and loss report shows money in against money out for any month.</li>
        </ul>
        <?= site_screens([
            ['stock-2', 'A wig\'s page in Nfelia: its selling price, cost price, a 37.9% margin and 2 left in stock', 'Price, cost and margin'],
            ['profit-2', 'Profit and loss for October in Nfelia: money in, money out and the profit left', 'Did your prices work?'],
        ]) ?>
        <p>Nfelia shows margin, not markup, so compare it with the margin tables above. The screens are from Ivie Luxe Hair, an example business. See how to set prices and costs in the guide to <a href="<?= $u('help/add-products') ?>">adding your products</a>, and how to read your month in the <a href="<?= $u('help/expenses-and-profit') ?>">expenses and profit guide</a>.</p>

        <h2 id="questions">Questions people ask</h2>
        <?= site_faq([
            ['What is the difference between markup and margin?',
             'Markup is profit as a share of the cost; margin is profit as a share of the selling price. An item that costs 1,000 and sells for 1,500 has a 50% markup and a 33.3% margin.'],
            ['What is a good profit margin for a small business?',
             'There is no single right number: it depends on your trade, how fast your goods sell and what your running costs are. The right margin is one that pays your running costs and leaves profit. Check it every month in your profit and loss.'],
            ['How do I price handmade products?',
             'Add the materials, your time at an hourly rate, and a share of your running costs, then your profit. Work out your hourly rate from what you need to earn in a month divided by the hours you can really charge for.'],
        ]) ?>
    </div>
</article>
<?= site_related(['blog/calculate-profit', 'features/profit', 'features/stock', 'help/add-products', 'blog/how-to-write-an-invoice', 'features/invoices']) ?>
<?= site_cta('See your margin on every item') ?>
