<?php
/**
 * Help: send an invoice. Every sale starts as an order and saving it makes
 * its invoice (InvoiceController::create sends people to New order;
 * order_create and order_confirm in app/helpers/orders.php). New order is
 * views/orders/form.php (assets/js/orders.js); the invoice page is
 * views/invoices/view.php, what the customer opens is
 * views/invoices/public.php; quotes become orders with "Accept and make the
 * order" (views/quotations/view.php, QuotationController).
 *
 * From site_render: $page, $country.
 */
require_once BASE_PATH . '/views/home/_icons.php';   // the view runs before the layout loads it

$to  = fn(string $key, string $text) => site_page($key) ? '<a href="' . e(site_url($key)) . '">' . e($text) . '</a>' : e($text);
$toc = [
    'how'      => 'How invoices work in Nfelia',
    'make'     => 'Make the invoice',
    'send'     => 'Send it to your customer',
    'sees'     => 'What your customer sees',
    'quote'    => 'Start from a quote',
    'paid'     => 'When they pay',
    'late'     => 'When they pay late',
    'change'   => 'Change or cancel an invoice',
    'problems' => 'Common problems',
];
?>
<article class="wrap article-wrap has-aside">
    <header class="article-head">
        <p class="kicker">Help centre</p>
        <h1><?= e($page['og']['title']) ?></h1>
        <p class="page-lead">In Nfelia, an invoice comes from an order. Add the customer and the items, say what they paid, and save: the invoice is made, numbered and ready to send on WhatsApp.</p>
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
        <p>To send an invoice on Nfelia, tap <strong>+</strong>, then <strong>New order</strong>. Pick the customer, add the items, choose <strong>Paid now</strong>, <strong>Part now</strong> or <strong>Pay later</strong>, and tap <strong>Save order</strong>. Nfelia makes the invoice straight away. Open it from the order and tap <strong>WhatsApp</strong>: your customer gets a link to the invoice, with your logo, the balance and where to pay.</p>

        <h2 id="how"><?= e($toc['how']) ?></h2>
        <ul>
            <li><strong>Every sale is an order</strong>, and every order has one invoice. The invoice holds the money: what was paid, each payment's receipt, and what is left.</li>
            <li><strong>Numbers are made for you</strong>, starting with letters from your business name, like IVL-INV-281. Change the letters in <strong>Settings › Business › Document numbers</strong>.</li>
            <li><strong>Your customer gets a link</strong>, not just a picture. It opens on any phone, shows your logo and colours, and can be saved as a PDF.</li>
            <li><strong>The items come off your stock</strong> when the invoice is made, and go back if it is cancelled.</li>
        </ul>

        <h2 id="make"><?= e($toc['make']) ?></h2>
        <?= site_steps([
            ['Open New order', 'Tap <strong>+</strong> at the bottom of the screen, then <strong>New order</strong>. Or open the <strong>Orders</strong> tab and tap <strong>New order</strong>; the <strong>Invoices</strong> list has the same button.'],
            ['Pick the customer', 'Under <strong>Customer</strong>, type a name or phone number and tap them. Someone new? Tap <strong>New customer</strong> and type their <strong>Name</strong> and <strong>Phone</strong>, and an email under <strong>Add email</strong> if you have it. For someone paying in full on the spot, <strong>No name, paying now</strong> is enough.'],
            ['Add the items', 'Tap <strong>Add an item</strong> and pick from your list, with its options. Not in your list? Type it as your own item. Set <strong>How many</strong> and the <strong>Price each</strong>, and add a note like measurements if you need to. Tap <strong>Add discount</strong> to take something off the total.'],
            ['Say where the customer is', 'Under <strong>Where is the customer?</strong>, tap <strong>In the shop</strong> or <strong>Online or call</strong>. Online, choose <strong>Picks up</strong> or <strong>Delivery</strong>, with the day and the address.'],
            ['Say what they paid', 'Under <strong>Payment</strong>, tap <strong>Paid now</strong>, <strong>Part now</strong> (and type how much) or <strong>Pay later</strong>. Under <strong>Paid by</strong>, tap <strong>Cash</strong>, <strong>Transfer</strong>, <strong>POS</strong> or <strong>MoMo</strong>. If something is left to pay, choose when it is due: <strong>The day they get it</strong>, <strong>In a week</strong> or <strong>Pick a day</strong>.'],
            ['Tap Save order', 'The total and what is left show at the bottom as you go. After saving you see, for example, <strong>Order IVL-ORD-031 saved. The balance of … is in Debts.</strong> You are on the order\'s page. Its invoice is under <strong>Money</strong>.'],
        ]) ?>
        <div class="note"><strong>A sale in the shop</strong> When the customer is in the shop, pays everything now and takes the goods now, the button reads <strong>Paid</strong> with the amount. Tap it: the sale, its invoice and its receipt are made in one go.</div>
        <p>Typing in a sale from an earlier day? At the top of New order, tap <strong>Change</strong> next to the date. The order, its invoice and its payment are all dated that day, so your reports count it then.</p>

        <h2 id="send"><?= e($toc['send']) ?></h2>
        <?= site_steps([
            ['Open the invoice', 'On the order, open <strong>Money</strong> and tap the invoice. Or find it in the <strong>Orders</strong> tab under <strong>Invoices</strong>.'],
            ['Tap WhatsApp', 'WhatsApp opens with a message ready: the invoice number, the total, the balance, the pay-by date and the link. Pick the chat if Nfelia doesn\'t have their number, then send.'],
            ['Or send it another way', 'Tap <strong>Share PDF</strong> to send the PDF through your phone\'s share menu, or <strong>Copy link</strong> to paste it anywhere. Under <strong>More</strong> you can <strong>Download PDF</strong> or <strong>Open the customer\'s page</strong> to see exactly what they see.'],
        ]) ?>
        <p>From the order itself, <strong>WhatsApp</strong> also has <strong>Send the invoice</strong> among its ready messages.</p>

        <h2 id="sees"><?= e($toc['sees']) ?></h2>
        <p>Your customer opens the link on any phone, with no app and no sign-in. They see your logo and colours, the items, the total, the <strong>Amount paid</strong> and the <strong>Balance due</strong>. While a balance is due, your bank or mobile money account shows on it, so they know where to pay. They can tap <strong>Download PDF</strong> to keep a copy. When it is paid in full, the invoice carries a PAID stamp.</p>
        <p>Each invoice also has a code. Anyone, like a bank or a buyer's accounts office, can scan it or type it at <a href="<?= e(APP_URL . '/verify') ?>">nfelia.com/verify</a> to see that the invoice is genuine.</p>
        <?= site_screens([['invoice-1', 'Kemi Events & Décor\'s invoice as the client sees it: the deposit paid and the balance due', 'Deposit paid, balance due'], ['invoice-2', 'Ivie Luxe Hair\'s invoice as the customer sees it, marked as fully paid', 'Paid in full']]) ?>
        <p class="meta">Screens from Kemi Events &amp; Décor and Ivie Luxe Hair, example businesses.</p>

        <h2 id="quote"><?= e($toc['quote']) ?></h2>
        <p>For a big job, send a quote first: tap <strong>+</strong>, then <strong>New quote</strong>. When the customer agrees, open the quote and tap <strong>Accept and make the order</strong>. You see <strong>Accepted. Order … is made, with its invoice.</strong> Send the invoice the same way.</p>

        <h2 id="paid"><?= e($toc['paid']) ?></h2>
        <p>On the invoice or its order, tap <strong>Record payment</strong>. Type <strong>How much</strong>, or tap <strong>All of it</strong> or <strong>Half</strong>, choose how they paid, and save. The receipt is made at once, the balance goes down, and the invoice tag changes from <strong>Sent</strong> to <strong>Part paid</strong> or <strong>Paid</strong>. See <?= $to('help/receipts-and-payments', 'receipts and payments') ?>.</p>

        <h2 id="late"><?= e($toc['late']) ?></h2>
        <p>Anything left to pay goes into <strong>Debts</strong> under the customer's name, so it is never forgotten. On the pay-by day you get a reminder on Tasks. After that day, the invoice turns <strong>Overdue</strong> and shows <strong>Send a reminder</strong>: one tap opens WhatsApp with a polite message and the balance. See <?= $to('help/debt-reminders', 'debt reminders') ?>.</p>

        <h2 id="change"><?= e($toc['change']) ?></h2>
        <ul>
            <li><strong>Wrong items or price?</strong> On the order, tap <strong>Change items</strong>, fix them and save. You see <strong>Items changed. The invoice now says …</strong> The owner and admins can do this.</li>
            <li><strong>Wrong date?</strong> On the order, open <strong>More</strong> and tap <strong>Change dates</strong>. The owner and admins can do this.</li>
            <li><strong>The sale is off?</strong> Before it is delivered or collected, open <strong>More</strong> on the order and tap <strong>Cancel order</strong>. The invoice is cancelled and the items go back on your shelf. The owner and admins can do this.</li>
        </ul>

        <h2 id="problems"><?= e($toc['problems']) ?></h2>
        <?= site_faq([
            ['It says pay later or part payment needs the customer\'s name and phone', 'A balance has to be followed up, so Nfelia needs to know who owes it. Add their name and phone, or take the full amount now.'],
            ['My bank account is not on the invoice', 'Add it in <strong>Settings › Business › Payment accounts</strong>. The main account is the one new invoices show. Invoices already sent keep what they showed.'],
            ['WhatsApp opens without the customer\'s chat', 'Nfelia has no phone number for them. Pick their chat in WhatsApp, or add their number on their page under <strong>Clients</strong> for next time.'],
            ['The customer says the link doesn\'t open', 'Send the PDF instead: <strong>Share PDF</strong>, or <strong>More › Download PDF</strong> and send the file.'],
            ['I want different letters on my invoice numbers', 'Change them in <strong>Settings › Business › Document numbers</strong>. Only new numbers change; papers already sent keep theirs.'],
            ['Can my customer pay the invoice by card?', 'Not through Nfelia. Customers pay into your own bank or mobile money account, shown on the invoice. Record each payment when it arrives, and Nfelia makes the receipt.'],
        ]) ?>
        <p>Still stuck? <a href="<?= e(site_wa('Hello Nfelia, I need help sending an invoice.')) ?>" target="_blank" rel="noopener">Message us on WhatsApp</a>.</p>
    </div>
</article>

<?= site_related(['help/receipts-and-payments', 'help/debt-reminders', 'features/invoices', 'blog/how-to-write-an-invoice']) ?>
<?= site_cta('Send your first invoice tonight') ?>
