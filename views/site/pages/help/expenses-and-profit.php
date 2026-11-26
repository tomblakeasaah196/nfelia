<?php
/**
 * Help: expenses and profit. Checked against views/expenses (form, index),
 * ExpenseController (categories, payment status, stock purchases, the
 * supplier debt), views/reports/profit_loss.php, ReportController,
 * app/helpers/report_period.php, app/helpers/money.php (cash in, cash out)
 * and the Home money card (views/dashboard/index.php).
 */
require_once BASE_PATH . '/views/home/_icons.php';   // nf_icon() for the building blocks: the layout loads it only after the view
$u = fn(string $key) => e(site_url($key));
$toc = [
    'record'    => 'Record an expense',
    'stock'     => 'Stock you buy from a supplier',
    'credit'    => 'Things you buy on credit',
    'home'      => 'Your month on Home',
    'report'    => 'Open your profit and loss',
    'counts'    => 'What the report counts',
    'problems'  => 'Common problems and fixes',
];
?>
<article class="wrap article-wrap has-aside">
    <header class="article-head">
        <p class="kicker">Help centre</p>
        <h1>Record what you spend and see what is left</h1>
        <p class="page-lead">Record each thing you pay for in Expenses: rent, transport, salaries, stock. Nfelia sets it against the money your customers paid you, and Profit and loss shows money in, money out and what was left, for this month or any months you choose.</p>
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
        <p class="note"><strong>Who can do this</strong> Expenses and Profit and loss are open to the owner and admins. Staff do not see them. See <a href="<?= $u('help/staff') ?>">what staff can see</a>.</p>

        <h2 id="record">Record an expense</h2>
        <?= site_steps([
            ['Start a new expense', 'Tap the <strong>+</strong> button at the bottom of the screen, then <strong>Add expense</strong>. Or open <strong>More</strong>, then <strong>Expenses</strong>, then <strong>Record an expense</strong>.'],
            ['Choose a category', '<strong>Rent</strong>, <strong>Utilities</strong>, <strong>Transport</strong>, <strong>Salary / wages</strong>, <strong>Marketing</strong>, <strong>Maintenance</strong>, <strong>Office supplies</strong>, <strong>Stock purchase</strong> or <strong>Miscellaneous</strong>. The category decides where it shows in your report.'],
            ['Fill in the details', 'The <strong>Date</strong> you paid, a <strong>Description</strong> such as "Shop rent for October", and the <strong>Amount</strong>. A <strong>Reference or receipt number</strong>, a <strong>Supplier</strong> and <strong>Notes</strong> are optional.'],
            ['Say how it was paid', 'Under <strong>Payment status</strong>, choose <strong>Paid in full</strong>, <strong>Part paid</strong> or <strong>Unpaid, on credit</strong>. Pick the <strong>Payment method</strong>: cash, bank transfer, card, mobile money, POS or cheque.'],
            ['Tap Save expense', 'It joins the list in Expenses. At the top you see everything recorded, what was paid out, what is unpaid and what went on stock purchases. Tap a category chip to see only that category.'],
        ]) ?>
        <p>Do it the same day, while you still have the receipt in your hand. Small amounts add up: transport and data are often the costs people forget.</p>

        <h2 id="stock">Stock you buy from a supplier</h2>
        <p>Choose the category <strong>Stock purchase</strong> and a <strong>Purchase items</strong> section opens. For each item, pick it from your Catalogue, type the <strong>Qty</strong> and <strong>Unit cost</strong>, and tap <strong>+ Add item</strong> for the next one. Nfelia adds up the amount for you.</p>
        <p>When you save, those items are added to your stock, and an item with no cost price takes this unit cost. One record does both jobs: the money out and the goods in. More in <a href="<?= $u('help/stock') ?>">track your stock</a>.</p>

        <h2 id="credit">Things you buy on credit</h2>
        <p>Choose <strong>Unpaid, on credit</strong>, or <strong>Part paid</strong> with the <strong>Amount paid</strong>, and pick the supplier. With a supplier picked, what is still unpaid goes into Debts under <strong>I owe</strong>. When you pay the supplier, open that debt and use <strong>Record a payment</strong>: the money counts as paid out on the day you pay it.</p>

        <h2 id="home">Your month on Home</h2>
        <p>The owner and admins see this month's money at the top of <strong>Home</strong>:</p>
        <ul>
            <li><strong>Came in this month</strong>: what customers paid you, with how much is <strong>still owed</strong>.</li>
            <li><strong>Spent this month</strong>: what you paid out.</li>
            <li><strong>Left after spending</strong>: the first less the second.</li>
            <li><strong>Of it, on stock</strong>: the part of your spending that went on stock you can still sell.</li>
        </ul>
        <?= site_screens([
            ['profit-1', 'Nfelia Home: ₦962,000 came in this month, ₦695,500 spent and ₦266,500 left, with what needs attention today', 'Your month at a glance'],
            ['profit-2', 'Profit and loss for October: ₦962,000 in, ₦695,500 out and ₦266,500 left', 'Profit and loss'],
        ]) ?>
        <p>The screens are from Ivie Luxe Hair, an example business.</p>

        <h2 id="report">Open your profit and loss</h2>
        <?= site_steps([
            ['Open Profit and loss', 'On a phone, tap <strong>More</strong>, then <strong>Profit and loss</strong>. On a computer, it is in the menu on the left.'],
            ['Choose the period', 'Tap the period button at the top. Pick <strong>This month</strong>, <strong>Last month</strong>, <strong>This quarter</strong>, <strong>Year to date</strong> or <strong>Last 12 months</strong>. For other months, open <strong>Month range</strong>, choose the first and last month, and tap <strong>Show these months</strong>.'],
            ['Read the three numbers', '<strong>Money in</strong>, <strong>Money out</strong> and <strong>Left after spending</strong>, each compared with the period before. A line in plain words sums it up, such as how much you kept for every 100 that came in.'],
            ['Look further down', 'A chart <strong>Day by day</strong> or <strong>Month by month</strong>, <strong>Where the money went</strong> by category, <strong>Money still owed to you</strong>, <strong>Money you owe</strong>, and <strong>Invoices issued</strong> in the period.'],
            ['Download it', 'Tap <strong>Download PDF</strong> to save or share the report, for your records, a partner or a lender.'],
        ]) ?>

        <h2 id="counts">What the report counts</h2>
        <p>Nfelia counts cash: money that actually came in and went out in the period, by the day it moved.</p>
        <div class="table-scroll">
            <table>
                <caption class="sr-only">What counts as money in and money out in Nfelia's profit and loss</caption>
                <thead>
                    <tr><th scope="col">Line</th><th scope="col">Counts</th><th scope="col">Does not count</th></tr>
                </thead>
                <tbody>
                    <tr><td>Money in</td><td>Payments recorded on orders and invoices, and receipts for sales paid on the spot, on the day they were paid</td><td>Invoices not paid yet (they show under Money still owed to you), and a refundable caution fee</td></tr>
                    <tr><td>Money out</td><td>What you paid on expenses, and payments to suppliers you owed, on the day you paid</td><td>Expenses still unpaid (they show under Money you owe)</td></tr>
                    <tr><td>Left after spending</td><td>Money in less money out</td><td>Stock still on your shelves is not taken off: the report tells you how much of your spending went on stock</td></tr>
                </tbody>
            </table>
        </div>
        <p>So a month when you bought a lot of stock can show little left, even though the goods are still there to sell. Our guide to <a href="<?= $u('blog/calculate-profit') ?>">working out your profit</a> explains the difference between cash left and profit, and how to price for it.</p>
        <p>The report shows your numbers; it is not tax or accounting advice. For tax, ask an accountant or your tax office. To give a lender every record behind the numbers, make your <a href="<?= $u('help/records-book') ?>">Records Book</a>.</p>

        <h2 id="problems">Common problems and fixes</h2>
        <?= site_faq([
            ['I can\'t find Expenses or Profit and loss.',
             'They are open to the owner and admins only. If you are staff, ask the owner. If you are the owner, look under More on a phone, or in the menu on the left on a computer.'],
            ['Money in looks too low. What is missing?',
             'Only payments you recorded count. A sale where the customer paid but nobody tapped Record payment is not in. Check Orders, then Balance, for orders that still show a balance.'],
            ['I bought on credit but Money out did not change. Why?',
             'Unpaid expenses are not money out yet. They show under Money you owe, and count as paid out on the day you record the payment to your supplier.'],
            ['Where the money went shows "Miscellaneous" a lot.',
             'Pick a real category when you record each expense. Open an old expense to change its category, and the report follows.'],
            ['Can I see last year?',
             'Yes. Open Month range in the period button and choose the months. Last 12 months is quickest for a year up to today.'],
        ]) ?>
    </div>
</article>
<?= site_related(['features/profit', 'blog/calculate-profit', 'help/records-book', 'help/stock', 'help/debt-reminders', 'blog/business-records-for-a-loan']) ?>
<?= site_cta('See your real profit with Nfelia') ?>
