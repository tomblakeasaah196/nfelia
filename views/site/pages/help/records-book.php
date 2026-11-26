<?php
/**
 * Help: the Records Book and nfelia.com/verify. Checked against
 * views/records (index, request), RecordsController, records_offer() and
 * RECORDS_KEEP_DAYS in app/helpers/records.php, RECORDS_GROUPS in
 * records_sheets.php, record_requests.php (the 7-day link),
 * VerifyController and views/verify/page.php. Prices from site_prices().
 */
require_once BASE_PATH . '/views/home/_icons.php';   // nf_icon() for the building blocks: the layout loads it only after the view
$u      = fn(string $key) => e(site_url($key));
$prices = site_prices($page['country']);
$money  = $prices['money'] ?? null;
$book   = $prices && $money ? $money((float)$prices['book']) : null;
$month  = $prices && $money ? $money((float)$prices['month']) : null;
$verify = e(APP_URL . '/verify');
$toc = [
    'what'      => 'What the Records Book is',
    'cost'      => 'What it costs',
    'make'      => 'Make your Records Book',
    'plain'     => 'Your plain copy, free',
    'verify'    => 'How anyone checks it at nfelia.com/verify',
    'requests'  => 'When someone asks for a record',
    'problems'  => 'Common problems and fixes',
];
?>
<article class="wrap article-wrap has-aside">
    <header class="article-head">
        <p class="kicker">Help centre</p>
        <h1>Make your Records Book and let anyone check it</h1>
        <p class="page-lead">Open Your records and tap Make my Records Book. In a few minutes you get one Excel workbook with all your customers, sales, money and stock, in your colours. Every record in it has a code that a bank, a landlord or a buyer can check at nfelia.com/verify.</p>
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
        <p class="note"><strong>Who can do this</strong> Only the owner can take the business's records out of Nfelia. Your records stays open even when your plan has ended, for the 12 months we keep your records after you stop paying: they belong to you, paid up or not.</p>

        <h2 id="what">What the Records Book is</h2>
        <p>It is an Excel file (.xlsx) made from everything you have recorded in Nfelia. It opens in Excel, Google Sheets, LibreOffice and Numbers.</p>
        <ul>
            <li><strong>A cover</strong> with your name, colours and figures, and a QR code to check the book.</li>
            <li><strong>At a glance</strong>: totals for sales, money in, spending and what is owed, a table of every month and a chart.</li>
            <li><strong>A sheet for each kind of record</strong>, grouped as People, Selling, Money, Stock, and Planning and your shop: customers, orders, invoices, receipts, payments, expenses, debts, products, stock movements, tasks and more. Invoices, receipts and the other documents each have a link to open them and a verify code of their own.</li>
        </ul>
        <?= site_screens([
            ['records-1', 'Your records in Nfelia: the Records Book, free this month, with a Make my Records Book button', 'Made in one tap'],
            ['records-2', 'nfelia.com/verify showing Invoice IVL-INV-281 from Ivie Luxe Hair as genuine, with its order, amount and status', 'Anyone can check it'],
        ]) ?>
        <p>The screens are from Ivie Luxe Hair, an example business.</p>

        <h2 id="cost">What it costs</h2>
        <?php if ($book && $month): ?>
        <ul>
            <li><strong>Free once in every month you pay for.</strong> Nfelia costs <?= e($month) ?> a month in <?= e($country['name']) ?>, and each paid month includes one book.</li>
            <li><strong>Another book in the same month, or a book during the free trial or after your plan has ended</strong>: <?= e($book) ?> each.</li>
            <li><strong>Your plain copy</strong> (every record as CSV files) is free, always.</li>
        </ul>
        <?php else: ?>
        <p>One Records Book is free in every month you pay for, and your plain copy is free, always. The Your records page shows the price of any other book before you pay.</p>
        <?php endif; ?>
        <p>Paying for a book works like paying for Nfelia: with Paystack in Nigeria, or by Mobile Money with a receipt where that is how Nfelia is paid. See <a href="<?= $u('help/billing') ?>">billing and payments</a>.</p>

        <h2 id="make">Make your Records Book</h2>
        <?= site_steps([
            ['Open Your records', 'On a phone, tap <strong>More</strong>, then <strong>Your records</strong> under Data. On a computer, it is in the menu on the left. You see <strong>Your Records Book</strong> and a line saying whether this month\'s book is free.'],
            ['Choose what goes in, if you want to', 'Tap <strong>Choose what goes in</strong>. Under <strong>Which records</strong>, pick <strong>All time</strong>, <strong>This year</strong>, <strong>The last 12 months</strong>, <strong>Last year</strong> or <strong>Dates I choose</strong>. Untick any sheets you do not need. Leave it alone for everything, all time.'],
            ['Tap Make my Records Book', 'Outside a free month the button says <strong>Get my Records Book for</strong> with the price, and you pay first. While Nfelia makes the book, the button says <strong>Making your book</strong>.'],
            ['Download it', 'When it is ready, Nfelia tells you: "Your Records Book is ready". It appears under <strong>Your books</strong> with a <strong>Download</strong> button. Books are kept for a year, and you can download them as often as you like.'],
        ]) ?>
        <p>Showing your records to a bank for a loan? Our guide to <a href="<?= $u('blog/business-records-for-a-loan') ?>">business records for a loan</a> explains what lenders look for.</p>

        <h2 id="plain">Your plain copy, free</h2>
        <p>Further down Your records, under <strong>Your plain copy</strong>, tap <strong>Get my plain copy</strong>. You get every record as it is kept, in CSV files any spreadsheet opens, in one ZIP file. It is free whatever your plan, so your records are never locked in.</p>

        <h2 id="verify">How anyone checks it at nfelia.com/verify</h2>
        <p>Every quote, invoice, receipt, delivery note and deed made with Nfelia carries a code and a QR code, and so does every Records Book and each record in it. Nobody needs an account to check one.</p>
        <?= site_steps([
            ['Open nfelia.com/verify', 'Go to <a href="' . $verify . '">nfelia.com/verify</a>, or scan the QR code with a phone camera.'],
            ['Type the code', 'Under <strong>The code</strong>, type it as it is printed: 10 letters and numbers, like K7Q2M-XA9WT, sometimes with more after it. It is at the foot of a document, on the cover of a book, or in a record\'s Verify column. Tap <strong>Check it</strong>.'],
            ['Read the answer', 'A genuine record shows <strong>Genuine</strong> with its key facts: the business, the amount and the status, and who signed it. It never shows a customer\'s phone, email or address.'],
        ]) ?>
        <p>Someone who received the Excel file itself can choose it under <strong>Check the file</strong>. Nfelia reads only its fingerprint and says whether it is exactly as Nfelia made it. The file is not kept.</p>
        <p>Other answers they may see: <strong>Genuine, since removed</strong> when you deleted the record later; a note that a book is more than a year old, when Nfelia can confirm the code but no longer what it said; and <strong>We can't find that code</strong> when it was typed wrong. Codes never use the letters I, L, O or U.</p>

        <h2 id="requests">When someone asks for a record</h2>
        <p>After checking a code, a lender or buyer can ask you for the full document. They give their name, email, organisation, what they are and why they need it, and confirm their email.</p>
        <?= site_steps([
            ['You get an alert and an email', 'The request waits in Your records under <strong>People who asked for your records</strong>. Nothing is shared unless you say yes.'],
            ['Open it and read why', 'You see who asked, when, and their reason.'],
            ['Approve or decline', 'Tap <strong>Approve and share it</strong> and Nfelia emails them a link to download it, which works for 7 days. Tap <strong>Decline</strong> and they are told politely.'],
        ]) ?>

        <h2 id="problems">Common problems and fixes</h2>
        <?= site_faq([
            ['I can\'t find Your records.',
             'It is for the owner only. Staff and admins do not see it. On a phone, look under More, in the Data section.'],
            ['It says my Records Book needs another try.',
             'You have a lot of records. Choose a shorter period, a year for example, under Choose what goes in, and make it again. Nothing was charged for the book that did not work.'],
            ['Is my free book used up if I make one early in the month?',
             'Yes, one free book comes with each paid month. Make it when you need it, or pay for another. The page tells you when your next free one comes.'],
            ['The bank says the code is not found.',
             'Check it was typed exactly, without spaces in the wrong place, or scan the QR code instead. Codes have no I, L, O or U, so a "0" is a zero.'],
            ['Can I open the book on my phone?',
             'Yes, with Google Sheets, Excel or Numbers on your phone. It is easier to read on a computer or tablet.'],
        ]) ?>
    </div>
</article>
<?= site_related(['features/records-book', 'blog/business-records-for-a-loan', 'help/expenses-and-profit', 'help/billing', 'help/deliveries']) ?>
<?= site_cta('Keep your records ready for the bank') ?>
