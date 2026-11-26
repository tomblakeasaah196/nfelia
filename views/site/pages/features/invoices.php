<?php
/**
 * Feature: invoices and quotes. Checked against InvoiceController,
 * QuotationController, PublicController (the customer's page and its
 * answers), app/helpers/orders.php (every sale is an order, with its
 * invoice), functions.php (numbers), signing.php (verify codes) and
 * cron/planner.php (overdue and expired).
 */
require_once BASE_PATH . '/views/home/_icons.php';   // nf_icon(): the layout loads it only after the view

$fi_a = fn(string $key, string $text): string => site_page($key) ? '<a href="' . e(site_url($key)) . '">' . e($text) . '</a>' : e($text);

echo site_hero([
    'kicker'    => 'Invoices and quotes',
    'h1'        => 'Invoices and quotes that look like a big company’s',
    'lead_html' => 'Nfelia is an invoice app for small businesses. Make an invoice or a quote with your logo in a minute, send it as a WhatsApp link or a PDF, and see at a glance who has paid, who paid part, and who is late. Your customer opens it on any phone, with no app to install.',
    'screens'   => [
        ['invoice-1', 'Kemi Events & Décor’s invoice as the client sees it: ₦645,000 of ₦1,290,000 paid, with the balance due', 'Deposit and balance'],
        ['invoice-2', 'Ivie Luxe Hair’s invoice marked as fully paid', 'Paid in full'],
    ],
]);
?>

<section class="page-section" aria-labelledby="fi-make"><div class="wrap split">
    <div class="split-copy">
        <h2 id="fi-make">Every sale gets its invoice</h2>
        <p>In Nfelia every sale is an order, and every order has its invoice. Tap <strong>New order</strong>, pick the customer and the items from your catalogue, choose pickup or delivery, and say how they are paying: <strong>Paid now</strong>, <strong>Part now</strong> or <strong>Pay later</strong>. The invoice is made with the order. Orders from <?= $fi_a('features/website', 'your website') ?> get theirs when you confirm them.</p>
        <?= site_ticks([
            ['Numbered for you', 'like KED-INV-027: your letters, then INV, then the next number. No two papers ever share a number.'],
            ['Your letters', '2 to 5 letters or digits, made from your business name. Change them in Settings under Document numbers.'],
            ['A pay-by date', 'the invoice’s due date. A balance goes into your Debts straight away.'],
            ['Your account details', 'the bank or mobile money account to pay into, shown while something is still owed.'],
            ['Stock comes off', 'for the goods on it, and goes back if the order is cancelled.'],
        ]) ?>
        <p>The screens here are from Kemi Events &amp; Décor and Ivie Luxe Hair, example businesses in Lagos.</p>
    </div>
    <div class="split-copy">
        <h2 id="fi-see">What your customer sees</h2>
        <p>The link opens the invoice as a clean page in your colours and fonts, with your logo, address and phone number. It shows who it is for, each item with its quantity, unit price and discount, the total, what has been paid and the balance due. A banner tells them where it stands: part paid, overdue, or fully paid with thanks. A large faint PAID shows across it once they finish paying.</p>
        <p>They can download it as a PDF with one tap. At the foot is a QR code and a short code: anyone can check at <a href="<?= APP_URL ?>/verify">nfelia.com/verify</a> that the invoice is genuine, and see its main details to compare with the paper. You can also have invoices signed for the business, with a stamp or your own drawn signature, in Settings under Signatures.</p>
    </div>
</div></section>

<section class="page-section tint" aria-labelledby="fi-send"><div class="wrap">
    <?= site_head('Send it on WhatsApp, or any way you like', 'From the invoice in Nfelia, one tap does each of these:', 'fi-send') ?>
    <ul class="fits">
        <li><strong>WhatsApp</strong><span>Opens a chat with your customer and the message written: the invoice number, the total, the balance, the pay-by date and the link.</span></li>
        <li><strong>Share PDF</strong><span>On phones that can share files, the PDF goes straight into WhatsApp or email through your phone’s share sheet.</span></li>
        <li><strong>Copy link</strong><span>For Instagram DMs, SMS or anywhere else you talk to customers.</span></li>
        <li><strong>Download PDF</strong><span>To print, or to attach to an email for a company client.</span></li>
    </ul>
</div></section>

<section class="page-section" aria-labelledby="fi-paid"><div class="wrap split">
    <div class="split-copy">
        <h2 id="fi-paid">Then watch it get paid</h2>
        <p>When money comes in, tap <strong>Record payment</strong>: all of it, half, or any amount, by cash, bank transfer, POS, mobile money or cheque. Each payment makes its own receipt, the balance updates, and the invoice moves on by itself.</p>
    </div>
    <div class="compare table-scroll">
        <table>
            <caption class="sr-only">What each invoice status means in Nfelia</caption>
            <thead><tr><th scope="col">Status</th><th scope="col">What it means</th></tr></thead>
            <tbody>
                <tr><th scope="row">Sent</th><td>Made and waiting for payment.</td></tr>
                <tr><th scope="row">Part paid</th><td>A deposit or part payment is in. The balance shows on the invoice and in Debts.</td></tr>
                <tr><th scope="row">Paid</th><td>Nothing left to pay. The customer’s page says so and shows PAID.</td></tr>
                <tr><th scope="row">Overdue</th><td>The pay-by date has passed with money still owed. You hear about it once a week, for up to eight weeks, and a <strong>Send a reminder</strong> button appears.</td></tr>
                <tr><th scope="row">Cancelled</th><td>The sale was called off. The stock it took goes back on your shelves.</td></tr>
            </tbody>
        </table>
    </div>
</div></section>

<section class="page-section rose" aria-labelledby="fi-quote"><div class="wrap split flip">
    <div class="split-copy">
        <h2 id="fi-quote">Quotes your customer can accept from their phone</h2>
        <p>Big jobs start with a price. Tap <strong>New quote</strong> and add the lines, with a discount on any line or on the whole quote, tax, your notes and terms, and a <strong>Valid until</strong> date.</p>
        <p>Your customer opens the quote from the link and can:</p>
        <?= site_ticks([
            ['Accept the quote', 'with one tap, or by signing with a finger if you set the quote to be signed.'],
            ['Ask a question or ask for a change', 'in a thread on the quote. You see “Customer replied” and answer there.'],
            ['Decline', 'and say why, so you know what to change next time.'],
        ]) ?>
        <p>Once it is accepted, the quote becomes an order with its invoice, and any balance goes into Debts. If they have not answered after three days, a follow-up shows on your Tasks with a WhatsApp message ready. A quote nobody answered turns Expired the day after its date, and you are told.</p>
        <p>Customers can ask you for a quote too: the <strong>Custom and bulk orders</strong> form on your website sends their request to your list of quote requests.</p>
    </div>
    <div>
        <ul class="facts">
            <li><strong>14 days</strong><span>How long a new quote is valid, unless you pick another date.</span></li>
            <li><strong>3 days</strong><span>When Nfelia reminds you to follow up a quote with no answer.</span></li>
            <li><strong>1 link</strong><span>The quote, your customer’s questions and their answer, in one place.</span></li>
        </ul>
    </div>
</div></section>

<section class="page-section" aria-labelledby="fi-who"><div class="wrap">
    <?= site_head('Who it helps', 'Anyone who has lost a job to a competitor with a proper quote.', 'fi-who') ?>
    <ul class="fits">
        <li><strong><?= $fi_a('for/events', 'Event planners and décor') ?></strong><span>A branded quote, a 50% deposit, and the balance tracked until the day.</span></li>
        <li><strong><?= $fi_a('for/fashion-designers', 'Fashion designers') ?></strong><span>Fabric and sewing on one invoice, with the balance due at pickup.</span></li>
        <li><strong><?= $fi_a('for/food-and-catering', 'Caterers') ?></strong><span>Party quotes per head, accepted from the client’s phone.</span></li>
        <li><strong><?= $fi_a('for/phones-and-gadgets', 'Phones and gadgets') ?></strong><span>An invoice a company client can file, with the model on each line.</span></li>
    </ul>
</div></section>

<section class="page-section tint" aria-labelledby="faq-title"><div class="wrap">
    <?= site_head('Questions people ask', '', 'faq-title', true) ?>
    <?= site_faq([
        ['Can I put my logo and colours on my invoices?',
         'Yes. Add your logo and pick your colours in Settings, and every invoice, quote and receipt wears them, with your business name, address and phone number at the top.'],
        ['Does my customer need to download an app?',
         'No. They open the link in their phone’s browser, like any web page, and can download the invoice as a PDF from there.'],
        ['How are my invoices numbered?',
         'With your letters, the kind of document and a number that counts up, like ABC-INV-001, ABC-INV-002. Quotes use QUO and receipts REC. If you change your letters, papers already sent keep their numbers and new ones count on from where they were.'],
        ['Can a customer pay in parts?',
         'Yes. Record each payment as it comes in. The invoice shows what is paid and what is left, each payment gets its own receipt, and the balance waits in ' . $fi_a('features/debts', 'Debts') . ' until it is cleared.'],
        ['Is there a limit to how many invoices I can send?',
         'No. Invoices, quotes and receipts are unlimited. See ' . $fi_a('pricing', 'pricing') . ' for what Nfelia costs.'],
        ['How can my client be sure an invoice is really from me?',
         'Every invoice carries a QR code and a code of its own. Anyone can type the code at nfelia.com/verify to see that it is genuine, which business it is from, and its main details to compare with the paper. If you sign your invoices, it also shows who signed and whether anything changed since.'],
    ]) ?>
</div></section>

<?= site_related(['help/send-an-invoice', 'blog/how-to-write-an-invoice', 'features/receipts', 'features/debts', 'features/records-book', 'help/receipts-and-payments']) ?>
<?= site_cta('Send your first invoice tonight') ?>
