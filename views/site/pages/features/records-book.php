<?php
/**
 * /features/records-book: the Records Book (every record in one Excel
 * workbook) and nfelia.com/verify.
 *
 * Checked against app/helpers/records.php (who gets a free book, the price,
 * the plain copy, periods, how long files are kept, codes),
 * app/helpers/records_book.php (cover, contents, At a glance, the sheets,
 * About), app/helpers/records_sheets.php (every kind of record and its
 * group), app/controllers/RecordsController.php and views/records/index.php
 * (owner only, open on any plan), app/controllers/VerifyController.php,
 * views/verify/page.php and app/helpers/record_requests.php (checking a
 * code or a file, asking the business for the full document).
 */
require_once BASE_PATH . '/views/home/_icons.php';   // site_hero() draws icons before the layout loads them

$prices = site_prices($page['country']);
$month  = $prices ? $prices['money']((float)$prices['month']) : null;
$book   = $prices ? $prices['money']((float)$prices['book']) : null;
$trial  = (int)($prices['trial_days'] ?? TRIAL_DAYS);
$img    = APP_URL . '/assets/img/home';
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
    'kicker'    => 'Records Book',
    'h1'        => 'Your records in one Excel book, ready for the bank',
    'lead_html' => 'When a bank, a lender or a landlord asks for your business records, Nfelia makes them in one tap: your customers, sales, payments, expenses and stock in one Excel workbook, in your colours. '
                 . 'Whoever you show it to can check it is genuine at nfelia.com/verify.',
    'note'      => 'Free in every month you pay for Nfelia.',
    'screens'   => [
        ['records-1', 'Your records in Nfelia: the Records Book, free this month, with a Make my Records Book button', 'Made in one tap'],
        ['records-2', 'nfelia.com/verify showing Invoice IVL-INV-281 from Ivie Luxe Hair as genuine, with its order, amount and status', 'Anyone can check it'],
    ],
]);
?>

<section class="page-section" aria-labelledby="inside-title">
    <div class="wrap split">
        <div class="split-copy">
            <h2 id="inside-title">What is in the book</h2>
            <p>The Records Book is a real Excel file (.xlsx). It opens in Excel, Google Sheets, LibreOffice, Numbers and spreadsheet apps on the phone, and looks the same in each.</p>
            <?= site_ticks([
                ['The cover.', 'Your name and colours, how long you have been with Nfelia, your key figures, and a QR code that checks the book.'],
                ['At a glance.', 'Money in and out for every month side by side, with totals and a chart, plus your top customers, best sellers and who owes you.'],
                ['A sheet for each kind of record.', 'Each is a proper Excel table: sort and filter from the header, and the totals at the top follow your filters.'],
                ['Links and codes.', 'Each invoice, receipt and delivery note links to its document, and records like these carry their own verify code.'],
            ]) ?>
        </div>
        <figure class="book-shot">
            <img src="<?= $img ?>/workbook-cover.webp" width="1130" height="1000" loading="lazy" decoding="async"
                 alt="The cover of Ivie Luxe Hair’s Records Book, open in a spreadsheet: the shop’s name in its colours, how long it has been with Nfelia, its customers, sales, money received and orders, and a QR code to check the book">
            <figcaption><strong>The cover.</strong> From the Records Book of Ivie Luxe Hair, an example business, opened in LibreOffice.</figcaption>
        </figure>
    </div>
</section>

<section class="page-section tint" aria-labelledby="sheets-title">
    <div class="wrap">
        <?= site_head('Every kind of record you keep', 'Choose the period (all time, this year, the last 12 months, last year or dates you pick) and untick anything you don’t need. The book is ready in about a minute, and Nfelia keeps it for a year to download again.', 'sheets-title') ?>
        <div class="compare table-scroll">
            <table>
                <caption class="sr-only">The sheets a Records Book can hold, by group</caption>
                <thead><tr><th scope="col">Group</th><th scope="col">Sheets</th></tr></thead>
                <tbody>
                    <tr><th scope="row">People</th><td>Customers, Suppliers, Team</td></tr>
                    <tr><th scope="row">Selling</th><td>Quotes, Orders and Invoices (each with their lines), Bookings, Deliveries, Deeds, Quote requests, Reviews</td></tr>
                    <tr><th scope="row">Money</th><td>Payments, Receipts, Refunds, Kept money, Expenses, Debts (with their payments), Bank accounts</td></tr>
                    <tr><th scope="row">Stock</th><td>Products, Stock movements</td></tr>
                    <tr><th scope="row">Planning and your shop</th><td>Tasks and calendar, Shop news</td></tr>
                </tbody>
            </table>
        </div>
        <div class="split" style="margin-top:40px">
            <figure class="book-shot">
                <img src="<?= $img ?>/workbook-glance.webp" width="1130" height="1000" loading="lazy" decoding="async"
                     alt="The At a glance sheet: totals for sales, money in, spending and what is owed, a table of every month, and a chart of the months">
                <figcaption><strong>At a glance.</strong> Every month side by side, with totals and a chart.</figcaption>
            </figure>
            <figure class="book-shot">
                <img src="<?= $img ?>/workbook-receipts.webp" width="1130" height="1000" loading="lazy" decoding="async"
                     alt="The Receipts sheet: each receipt with its order, date, customer, invoice, amount and how it was paid, an Open link and its own verify code">
                <figcaption><strong>Every record.</strong> Each receipt in its own row, with a link to it and a code of its own.</figcaption>
            </figure>
        </div>
    </div>
</section>

<section class="page-section" aria-labelledby="verify-title">
    <div class="wrap split">
        <div class="split-copy">
            <h2 id="verify-title">Anyone can check it is genuine</h2>
            <p>A spreadsheet is easy to change. That is why every Records Book, and every record in it, carries a code that Nfelia can check. So does every quote, invoice, receipt and delivery note you make with Nfelia.</p>
            <p>Nothing more is shared unless you say yes.</p>
        </div>
        <div>
            <?= site_steps([
                ['They scan or type the code', 'The book’s code is on its cover, like K7Q2M-XA9WT, with a QR code. Invoices, receipts, payments and other records have their own in the Verify column. They check it at nfelia.com/verify, with no sign-in.'],
                ['They see that it is genuine', 'The business, when the book was made, the key facts and whether anything changed since. Never a customer’s phone number, email or address.'],
                ['They can check the file itself', 'Nfelia says whether an Excel file is exactly as Nfelia made it. Only its fingerprint is read; the file isn’t kept.'],
                ['They can ask you for more', 'They give their name, email and reason. If you say yes, they get a download link that works for 7 days.'],
            ]) ?>
        </div>
    </div>
</section>

<section class="page-section rose" aria-labelledby="cost-title">
    <div class="wrap">
        <?= site_head('What it costs', 'Your records belong to you. The Records Book page stays open whatever your plan, and if you stop paying, for the 12 months we keep your records. Only the owner can take the records out of Nfelia.', 'cost-title') ?>
        <div class="compare table-scroll">
            <table>
                <caption class="sr-only">Prices for the Records Book and the plain copy in Nigeria</caption>
                <thead><tr><th scope="col">What you get</th><th scope="col">Price in Nigeria</th></tr></thead>
                <tbody>
                    <tr><th scope="row">One Records Book in each month you pay for Nfelia</th><td>Free<?= $month !== null ? ' (Nfelia is ' . e($month) . ' a month)' : '' ?></td></tr>
                    <tr><th scope="row">Another book that month, or a book outside a paid month</th><td><?= $book !== null ? e($book) . ' a book' : 'Ask us on WhatsApp' ?></td></tr>
                    <tr><th scope="row">Your plain copy: every record as CSV files</th><td>Free, always, whatever your plan</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php $cards = $for_cards([
    ['for/phones-and-gadgets', 'Phones and gadgets', 'Months of sales and stock to show a supplier or a lender before you ask for credit.', 'box'],
    ['for/fashion-designers', 'Fashion designers', 'Every client, deposit and balance, ready when you apply for a loan for a new machine.', 'pen'],
    ['for/events', 'Events and décor', 'A record of events done and paid for, to show a corporate client or a hall owner.', 'calendar'],
    ['for/online-sellers', 'Online sellers', 'Orders and payments month by month, ready when a lender asks how your business is doing.', 'bag'],
]); ?>
<?php if ($cards !== ''): ?>
<section class="page-section" aria-labelledby="who-title">
    <div class="wrap">
        <?= site_head('Who it helps', 'Anyone who has to show their records: for a loan, a lease, a big client or a partner. Read ' . $link('blog/business-records-for-a-loan', 'what records to get ready for a loan') . '.', 'who-title') ?>
        <?= $cards ?>
    </div>
</section>
<?php endif; ?>

<section class="page-section tint" aria-labelledby="faq-title">
    <div class="wrap">
        <?= site_head('Questions about the Records Book', '', 'faq-title', true) ?>
        <?= site_faq([
            ['Will my bank accept a Records Book?',
             'Each bank or lender decides what it needs, so ask yours. What the book gives them is every record in one Excel file they can open, sort and check, with codes that show it came from Nfelia and hasn’t changed.'],
            ['Is it really an Excel file?',
             'Yes, an .xlsx workbook. It opens in Microsoft Excel, Google Sheets, LibreOffice, Numbers and spreadsheet apps on the phone.'],
            ['What does it cost?',
             $book !== null
                ? 'One book is free in every month you pay for Nfelia' . ($month !== null ? ' (' . e($month) . ' a month in Nigeria)' : '') . '. Another book, or one outside a paid month, is ' . e($book) . '. Your plain copy, every record as CSV files, is always free.'
                : 'One book is free in every month you pay for Nfelia, and your plain copy is always free. Ask us on WhatsApp for the price of another book where you are.'],
            ['Who can see my records at nfelia.com/verify?',
             'Only someone who has a code, and only the key facts of that book or record: never a customer’s phone number, email or address. The full book goes to nobody unless you say yes.'],
            ['Can I get my records if I stop paying?',
             'Yes. Your records belong to you, and the Records Book page stays open whatever your plan: get your plain copy free' . ($book !== null ? ', or a Records Book for ' . e($book) : '') . '. We keep your records for 12 months after your last paid month, and email you before anything is deleted.'],
            ['Can my staff make a Records Book?',
             'No. Only the owner can take the business’s records out of Nfelia.'],
        ]) ?>
    </div>
</section>

<?= site_related(['help/records-book', 'blog/business-records-for-a-loan', 'features/profit']) ?>
<?= site_cta('Get your records ready for the bank') ?>
