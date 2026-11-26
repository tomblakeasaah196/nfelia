<?php
/**
 * /features/profit: the expense tracker and the profit and loss report.
 *
 * Checked against app/controllers/ExpenseController.php (categories, how it
 * was paid, paid / part paid / unpaid, stock purchases that restock and set
 * cost prices, supplier debts; owner and admins only), app/helpers/money.php
 * (money in and money out, counted when the money moves),
 * app/controllers/ReportController.php, app/helpers/report_period.php and
 * views/reports/profit_loss.php, profit_loss_pdf.php (the report, its
 * periods, the comparison, the plain-words summary and the PDF), and
 * views/dashboard/index.php (Home: came in, spent, left after spending).
 */
require_once BASE_PATH . '/views/home/_icons.php';   // site_hero() draws icons before the layout loads them

$prices = site_prices($page['country']);
$month  = $prices ? $prices['money']((float)$prices['month']) : null;
$trial  = (int)($prices['trial_days'] ?? TRIAL_DAYS);
$link   = fn(string $key, string $text): string => site_page($key) ? '<a href="' . e(site_url($key)) . '">' . e($text) . '</a>' : e($text);

/** Cards to the "for your business" pages that exist: [key, title, text, icon]. */
$for_cards = function (array $rows): string {
    $items = [];
    foreach ($rows as [$key, $title, $text, $icon]) {
        if ($p = site_page($key)) {
            $items[] = [$title, $text, site_url($key), $p['icon'] ?? $icon];
        }
    }
    return $items ? site_cards($items, count($items) === 4 ? 'cards-2' : '') : '';
};

echo site_hero([
    'kicker'    => 'Expenses and profit',
    'h1'        => 'Know what you really made this month',
    'lead_html' => 'Nfelia is an expense tracker with a profit and loss report built in. Write down what you spend, and Nfelia already knows what came in from your payments and receipts. '
                 . 'It shows money in, money out and what is left, for any month, and downloads as a PDF for your bank or accountant.',
    'note'      => 'Part of Nfelia. Free for ' . $trial . ' days, no card needed.',
    'screens'   => [
        ['profit-1', 'Home: ₦962,000 came in this month, ₦695,500 spent, ₦266,500 left, and what needs you today', 'Your month at a glance'],
        ['profit-2', 'Profit and loss for October: ₦962,000 in, ₦695,500 out, ₦266,500 profit', 'Profit, worked out'],
    ],
]);
?>

<section class="page-section" aria-labelledby="in-title">
    <div class="wrap split">
        <div class="split-copy">
            <h2 id="in-title">Money in counts itself</h2>
            <p>You don’t type your sales twice. Every payment you record on an invoice, every sale paid on the spot and every receipt counts as money in, on the day it was paid. So does a booking deposit, once you tick that you have it.</p>
            <p>Money people still owe you is kept apart, under <strong>Money still owed to you</strong>. A sale counts once the money is in your hand.</p>
            <p>Your Home screen shows this month as you go: what came in, what you spent and what is left after spending.</p>
            <p class="hero-note"><?= nf_icon('check-circle') ?>Screens from Ivie Luxe Hair, an example business.</p>
        </div>
        <div>
            <?= site_screens([['paid-2', 'Receipts: ₦962,000 received this month, each payment listed by customer', 'Every payment, counted']]) ?>
        </div>
    </div>
</section>

<section class="page-section tint" aria-labelledby="out-title">
    <div class="wrap split">
        <div class="split-copy">
            <h2 id="out-title">Write down what you spend in seconds</h2>
            <p>Tap <strong>Record an expense</strong>, pick a category, say what it was for, the amount and the date. Then say how you paid: cash, bank transfer, card, mobile money, POS or cheque. Add the supplier and their receipt number if you have them.</p>
            <p>The categories are Stock purchase, Rent, Utilities, Transport, Salary / wages, Marketing, Maintenance, Office supplies and Miscellaneous, so the report can show where the money went.</p>
        </div>
        <div class="split-copy">
            <?= site_ticks([
                ['Stock you buy goes into your stock.', 'List the items with how many and what each cost. Your stock goes up, and a product with no cost price gets one.'],
                ['Not paid yet?', 'Choose Part paid or Unpaid, on credit. It waits under Money you owe, and with a supplier named it goes into Debts too. It counts as money out on the day you pay.'],
                ['Only you see it.', 'Expenses and the profit report are for the owner and admins, not your staff.'],
            ]) ?>
        </div>
    </div>
</section>

<section class="page-section" aria-labelledby="report-title">
    <div class="wrap">
        <?= site_head('Your profit and loss report', 'Pick This month, Last month, This quarter, Year to date or Last 12 months, or any range of months. Tap <strong>Download PDF</strong> for a Profit &amp; Loss Statement with your logo and colours.', 'report-title') ?>
        <div class="compare table-scroll">
            <table>
                <caption class="sr-only">What the profit and loss report shows</caption>
                <thead><tr><th scope="col">On the report</th><th scope="col">What it tells you</th></tr></thead>
                <tbody>
                    <tr><th scope="row">Money in</th><td>Payments received in the period</td></tr>
                    <tr><th scope="row">Money out</th><td>What you paid out, by category, and how much of it went on stock</td></tr>
                    <tr><th scope="row">Left after spending</th><td>Money in less money out, and how much of every ₦100 that came in you kept</td></tr>
                    <tr><th scope="row">Compared</th><td>The same figures for the stretch of time just before. Year to date is set against the same dates last year.</td></tr>
                    <tr><th scope="row">Day by day or month by month</th><td>Money in and out across the period, so you see your busy and quiet times</td></tr>
                    <tr><th scope="row">Money still owed to you</th><td>Unpaid invoices and other debts customers owe you</td></tr>
                    <tr><th scope="row">Money you owe</th><td>Unpaid expenses and other debts you owe</td></tr>
                    <tr><th scope="row">Invoices issued</th><td>How many invoices you sent in the period, and their total</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="page-section rose" aria-labelledby="words-title">
    <div class="wrap split">
        <div class="split-copy">
            <h2 id="words-title">It tells you what the numbers mean</h2>
            <p>Above the figures, the report says in a sentence or two what happened. When you paid out more than came in but the gap went on stock, it says so: that stock is still yours to sell. When little was left, it points you to your biggest costs and to unpaid invoices you could collect.</p>
        </div>
        <div class="split-copy">
            <p>Nfelia counts money when it moves: a sale when it is paid, a bill when you pay it. That is the picture you need to run the business day to day.</p>
            <p>For tax returns or audited accounts, work with an accountant and give them your <?= $link('features/records-book', 'Records Book') ?>: every record in one Excel workbook.</p>
        </div>
    </div>
</section>

<?php $cards = $for_cards([
    ['for/food-and-catering', 'Food and catering', 'Foodstuff, gas and transport set against what each party paid you.', 'bag'],
    ['for/phones-and-gadgets', 'Phones and gadgets', 'Stock bought in bulk set against what you sold and were paid for.', 'box'],
    ['for/salons', 'Salons and beauty', 'Rent, products and wages set against what your chairs brought in.', 'calendar'],
    ['for/events', 'Events and décor', 'Vendors and hire costs set against the deposits and balances you collected.', 'calendar'],
]); ?>
<?php if ($cards !== ''): ?>
<section class="page-section" aria-labelledby="who-title">
    <div class="wrap">
        <?= site_head('Who it helps', 'Any business that is busy all month and still wonders where the money went.', 'who-title') ?>
        <?= $cards ?>
    </div>
</section>
<?php endif; ?>

<section class="page-section tint" aria-labelledby="faq-title">
    <div class="wrap">
        <?= site_head('Questions about expenses and profit', '', 'faq-title', true) ?>
        <?= site_faq([
            ['Is this full accounting software?',
             'No. It is a simple, honest picture of your money: what came in, what went out and what is left, in plain words. For tax returns or audited accounts, work with an accountant and give them your ' . $link('features/records-book', 'Records Book') . '.'],
            ['Why is what is left different from my sales?',
             'Sales you haven’t been paid for yet are not money in: they wait under Money still owed to you. And stock counts as money out on the day you pay for it, even before you sell it. The report tells you when a gap is money that went into stock.'],
            ['Can I see last month, or the whole year?',
             'Yes. Pick This month, Last month, This quarter, Year to date or Last 12 months, or choose a range of months. Each is compared with the stretch of time just before it.'],
            ['Can I give the report to my bank?',
             'Yes. Tap <strong>Download PDF</strong> for a profit and loss statement with your logo and colours. If they want to see the records behind it, send them your Records Book as well.'],
            ['Can my staff see how much I make?',
             'No. Expenses, the profit report and the money on Home are only for the owner and admins.'],
            ['What does it cost?',
             $month !== null
                ? 'Expenses and the profit report are part of Nfelia: ' . e($month) . ' a month in Nigeria, with your website, invoices and receipts included. Free for ' . $trial . ' days, no card needed.'
                : 'Expenses and the profit report are part of Nfelia. Ask us on WhatsApp for the price where you are.'],
        ]) ?>
    </div>
</section>

<?= site_related(['help/expenses-and-profit', 'blog/calculate-profit', 'features/records-book']) ?>
<?= site_cta('See your real profit this month') ?>
