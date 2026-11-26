<?php
/**
 * Guide: how to write an invoice (/blog/how-to-write-an-invoice).
 * Tax facts checked on 28 October 2026: Nigeria's VAT rate and the NRS
 * (PwC tax summaries, reviewed May 2026; NRS Establishment Act 2025);
 * Ghana's CIS/FED rule (PwC tax summaries, reviewed October 2026); Kenya's
 * eTIMS rule (KRA public notice). Anything else about tax is left to each
 * country's tax office.
 *
 * From site_render: $page, $country.
 */
require_once BASE_PATH . '/views/home/_icons.php';   // nf_icon(): the layout loads it only after the view

$sections = [
    'invoice-quote-receipt' => 'Invoice, quote or receipt?',
    'what-to-show'          => 'What every invoice must show',
    'sample'                => 'A sample invoice to copy',
    'numbering'             => 'Number every invoice',
    'terms'                 => 'Payment terms that get you paid',
    'tax'                   => 'Tax on your invoices',
    'mistakes'              => 'Mistakes that delay payment',
    'nfelia'                => 'How Nfelia helps',
];
$h2   = fn(string $id) => '<h2 id="' . e($id) . '">' . e($sections[$id]) . '</h2>';
$link = fn(string $key, string $text) => site_page($key) ? '<a href="' . e(site_url($key)) . '">' . e($text) . '</a>' : e($text);
?>
<article class="wrap article-wrap has-aside">
    <header class="article-head">
        <p class="kicker">Guide</p>
        <h1><?= e($page['og']['title']) ?></h1>
        <p class="page-lead">An invoice is a written request for payment. It says who is paying, for what, how much, by when and how. Get those right and customers pay sooner, with fewer questions.</p>
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
        <p>To write an invoice, put the word <strong>Invoice</strong> at the top, then your business details, your customer’s details, an invoice number and two dates: the day you issued it and the day it is due. List each item with its quantity and price, add up the total, take off anything already paid, and show the balance and exactly how to pay you. Below is a checklist, a layout you can copy, and the small habits that decide whether you are paid this week or chasing money for a month.</p>

        <?= $h2('invoice-quote-receipt') ?>
        <p>Three documents, three jobs. Mixing them up confuses customers, so it helps to be clear.</p>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr><th scope="col">Document</th><th scope="col">When you send it</th><th scope="col">What it says</th></tr>
                </thead>
                <tbody>
                    <tr><th scope="row">Quote</th><td>Before the job, when someone asks for a price</td><td>“This is what it will cost.” Nothing is owed yet.</td></tr>
                    <tr><th scope="row">Invoice</th><td>When the job is agreed or the goods are ready</td><td>“Please pay this amount by this date.”</td></tr>
                    <tr><th scope="row">Receipt</th><td>When the money reaches you</td><td>“I have received this amount. Thank you.”</td></tr>
                </tbody>
            </table>
        </div>
        <p>A deposit sits between them. You send one invoice for the whole job, record the deposit against it, and the invoice then shows the balance still to pay. You send a receipt for the deposit, and another when the balance comes in.</p>

        <?= $h2('what-to-show') ?>
        <p>Use this as a checklist before you press send. If any line is missing, the customer has a reason to ask a question, and a question is a delay.</p>
        <ul>
            <li><strong>The word “Invoice”</strong> at the top, so nobody mistakes it for a quote.</li>
            <li><strong>Your business:</strong> name, phone number and address, and your logo if you have one.</li>
            <li><strong>Your customer:</strong> name and phone number. For a company, its full name and address, and the name of the person who ordered.</li>
            <li><strong>An invoice number</strong> that is yours alone and never used twice.</li>
            <li><strong>The issue date:</strong> the day you send it.</li>
            <li><strong>A due date:</strong> a real date, like 10 November, not “soon” or “next week”.</li>
            <li><strong>Each item on its own line:</strong> what it is, how many, the price for one and the line total. “Frontal wig, 20 inches” beats “hair”.</li>
            <li><strong>Discount, delivery and tax</strong> (if you charge it), each on a line of its own.</li>
            <li><strong>The total, what is already paid, and the balance due.</strong></li>
            <li><strong>How to pay:</strong> bank name, account name and account number, or your mobile money number, M-Pesa till or paybill number. Write the account name exactly as the bank or network shows it, so the customer can check it before sending money.</li>
            <li><strong>Your payment terms</strong> in one line, such as “Balance due before delivery”.</li>
            <li><strong>Your tax number</strong>, if your business is registered for VAT or another tax.</li>
        </ul>

        <?= $h2('sample') ?>
        <p>Here is a simple invoice from Kemi Events &amp; Décor, an example business that decorates events in Lagos. Copy the shape and put in your own details.</p>
        <h3>The top of the invoice</h3>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr><th scope="col">Field</th><th scope="col">Example</th></tr>
                </thead>
                <tbody>
                    <tr><th scope="row">From</th><td>Kemi Events &amp; Décor, [address], [phone number]</td></tr>
                    <tr><th scope="row">Bill to</th><td>Mrs Funke Adeyemi, [phone number]</td></tr>
                    <tr><th scope="row">Invoice number</th><td>KED-INV-042</td></tr>
                    <tr><th scope="row">Issue date</th><td>28 October 2026</td></tr>
                    <tr><th scope="row">Due date</th><td>10 November 2026</td></tr>
                </tbody>
            </table>
        </div>
        <h3>The items and the money</h3>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr><th scope="col">Item</th><th scope="col">Qty</th><th scope="col">Price</th><th scope="col">Amount</th></tr>
                </thead>
                <tbody>
                    <tr><td>Hall decoration, gold and white</td><td>1</td><td>₦350,000</td><td>₦350,000</td></tr>
                    <tr><td>Chair covers and sashes</td><td>200</td><td>₦1,500</td><td>₦300,000</td></tr>
                    <tr><td>Table centrepieces</td><td>20</td><td>₦7,500</td><td>₦150,000</td></tr>
                    <tr><td>Delivery, setup and pack-down</td><td>1</td><td>₦40,000</td><td>₦40,000</td></tr>
                </tbody>
                <tbody>
                    <tr><th scope="row" colspan="3">Subtotal</th><td>₦840,000</td></tr>
                    <tr><th scope="row" colspan="3">Discount</th><td>−&#8288;₦40,000</td></tr>
                    <tr><th scope="row" colspan="3">Total</th><td>₦800,000</td></tr>
                    <tr><th scope="row" colspan="3">Deposit paid on 20 October</th><td>−&#8288;₦400,000</td></tr>
                    <tr><th scope="row" colspan="3">Balance due by 10 November</th><td><strong>₦400,000</strong></td></tr>
                </tbody>
            </table>
        </div>
        <h3>The bottom of the invoice</h3>
        <p class="template">How to pay: transfer to [bank name], account name Kemi Events &amp; Décor, account number [account number]. Please use KED-INV-042 as the reference.
Terms: 50% deposit to book your date. Balance due by 10 November 2026.
Thank you for your business.</p>
        <p class="note"><strong>Selling in Ghana, Kenya or Cameroon?</strong> The layout is the same. Change the money to cedis, shillings or CFA francs, and give the way your customers pay: your mobile money number and the name registered on it, your M-Pesa till number, or your paybill number with the account number to use.</p>

        <?= $h2('numbering') ?>
        <p>Give every invoice its own number and keep the numbers in order: KED-INV-001, KED-INV-002, and so on. A short prefix from your business name helps when a customer has invoices from several sellers.</p>
        <p>Numbers matter for three reasons. You and your customer can talk about one invoice without confusion (“I paid 041, not 042”). You can see at a glance if one is missing. And a bank, an accountant or a tax office expects them to run in order.</p>
        <p>Never reuse a number, and never change the amount on an invoice you have already sent. If something changes, cancel it, send a new one and say why. Keep a copy of every invoice, paid or not.</p>

        <?= $h2('terms') ?>
        <p>Payment terms are your rules for paying. Agree them before you start the work and write them on the invoice in plain words.</p>
        <ul>
            <li><strong>Ask for a deposit on custom work.</strong> Tailors, caterers and decorators often ask for half before they buy materials. It covers your costs if the customer disappears.</li>
            <li><strong>Give a short due date.</strong> Seven or 14 days is common. For events and made-to-order work, make the balance due before delivery or before the day.</li>
            <li><strong>Send the invoice the same day.</strong> An invoice that arrives a week later feels less urgent, and the money may already be spent elsewhere.</li>
            <li><strong>Send it where your customer reads.</strong> For most small businesses that is WhatsApp, as a PDF or a link that opens on any phone.</li>
            <li><strong>Make paying easy.</strong> The right account details, the right account name and a reference to use.</li>
        </ul>
        <p>Terms you can copy and adjust:</p>
        <p class="template">50% deposit to start. Balance due before delivery.
Payment due within 7 days of the invoice date.
Your booking is confirmed when the deposit is paid.
Please send proof of payment on WhatsApp with the invoice number.</p>
        <p>When a due date passes without payment, follow the reminder plan in our guide to <?= $link('blog/collect-money-customers-owe', 'collecting money customers owe you') ?>.</p>

        <?= $h2('tax') ?>
        <p>Many very small businesses send simple invoices with no tax on them. If your business is registered for VAT, the rules are stricter: the tax must show as its own line with its rate, and your tax number goes on the invoice. Who must register, and what a tax invoice must contain, is set by each country’s tax office, so check with yours before you add tax to your invoices.</p>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr><th scope="col">Country</th><th scope="col">Tax office</th><th scope="col">Worth knowing</th></tr>
                </thead>
                <tbody>
                    <tr><th scope="row">Nigeria</th><td>Nigeria Revenue Service (NRS), which took over from the Federal Inland Revenue Service (FIRS) on 1 January 2026</td><td>The standard VAT rate is 7.5%.</td></tr>
                    <tr><th scope="row">Ghana</th><td>Ghana Revenue Authority (GRA)</td><td>The GRA is moving VAT-registered businesses onto its E-VAT system, where invoices come from a Certified Invoicing System or a Fiscal Electronic Device. Ask the GRA whether this applies to your business yet.</td></tr>
                    <tr><th scope="row">Kenya</th><td>Kenya Revenue Authority (KRA)</td><td>Since 1 September 2023, every business, including those not registered for VAT, must issue its invoices through KRA’s electronic Tax Invoice Management System (eTIMS).</td></tr>
                    <tr><th scope="row">Cameroon</th><td>Direction Générale des Impôts (DGI)</td><td>Ask your tax centre what your invoices must show.</td></tr>
                </tbody>
            </table>
        </div>
        <p>This is general information, not tax advice. Rules change, so the tax office has the last word.</p>

        <?= $h2('mistakes') ?>
        <p>Most late payments are not about money. They are about an invoice that made paying harder than it needed to be.</p>
        <ul>
            <li><strong>No due date,</strong> so the customer never feels late.</li>
            <li><strong>Vague items</strong> like “goods” or “services”, which invite questions and arguments.</li>
            <li><strong>A wrong account number,</strong> or an account name that does not match your business, so careful customers stop and ask.</li>
            <li><strong>Changing figures on an invoice already sent,</strong> instead of sending a new one.</li>
            <li><strong>Sending the invoice days after the job,</strong> when the urgency has gone.</li>
            <li><strong>Not keeping a copy,</strong> so when a customer says “I paid”, you cannot check.</li>
            <li><strong>Business and personal money in one account,</strong> so you cannot tell which transfer was for which invoice.</li>
        </ul>

        <?= $h2('nfelia') ?>
        <p>Nfelia is an invoice app for small businesses, on your phone. It does much of the checklist above for you:</p>
        <ul>
            <li>Invoices carry your logo and colours, and are numbered for you, in order. A number is never used twice.</li>
            <li>Pick items from your products and the prices fill in. Add a discount on the order. Need tax or written terms? Start with a quote: it takes a tax rate, notes and terms, and when it becomes an order the tax carries over to the invoice as its own line.</li>
            <li>Choose which of your bank or mobile money accounts goes on the invoice.</li>
            <li>Send it on WhatsApp as a link the customer opens on any phone, and they can download it as a PDF.</li>
            <li>Record a deposit or a part payment and the invoice shows what is paid and the balance due. Make the receipt in one tap.</li>
            <li>Any balance goes on your Debts list straight away, with its pay-by date. Once that date passes it shows as late, with a one-tap WhatsApp reminder.</li>
            <li>Every invoice carries a code, so anyone can check it is genuine at nfelia.com/verify.</li>
            <li>Started with a quote? Turn it into an invoice in one tap.</li>
        </ul>
        <?= site_screens([
            ['invoice-1', 'Kemi Events & Décor’s invoice as the client sees it: deposit paid and balance due', 'Deposit paid, balance due'],
            ['invoice-2', 'Ivie Luxe Hair’s invoice marked as fully paid', 'Paid in full'],
        ]) ?>
        <p>The screens show Kemi Events &amp; Décor and Ivie Luxe Hair, example businesses. Nfelia does not send invoices to a tax office, so if yours requires invoices from its own system, such as eTIMS in Kenya, issue those as well. Read more about <?= $link('features/invoices', 'invoices and quotes in Nfelia') ?>, follow the steps to <?= $link('help/send-an-invoice', 'send your first invoice') ?>, or see how <?= $link('features/receipts', 'receipts') ?> work.</p>
    </div>
</article>

<?= site_related(['features/invoices', 'help/send-an-invoice', 'blog/collect-money-customers-owe', 'features/receipts', 'blog/price-your-products', 'features/debts']) ?>
<?= site_cta('Send your next invoice from your phone') ?>
