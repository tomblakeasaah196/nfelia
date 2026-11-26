<?php
/**
 * Help: receipts and payments. Every payment on an order's invoice makes its
 * receipt (order__payment in app/helpers/orders.php): from New order
 * (views/orders/form.php), Record payment on the order or the invoice
 * (views/orders/view.php, views/invoices/view.php), and the Confirm order
 * switch on a shop order. The receipt page is views/receipts/view.php and
 * the list views/receipts/index.php. Alerts: the 'money' topic in
 * app/helpers/notify.php.
 *
 * From site_render: $page, $country.
 */
require_once BASE_PATH . '/views/home/_icons.php';   // the view runs before the layout loads it

$to  = fn(string $key, string $text) => site_page($key) ? '<a href="' . e(site_url($key)) . '">' . e($text) . '</a>' : e($text);
$toc = [
    'rule'     => 'Record the payment, get the receipt',
    'now'      => 'Paid when you take the order',
    'later'    => 'Paid later, or bit by bit',
    'send'     => 'Send the receipt',
    'find'     => 'Find a receipt',
    'fix'      => 'Put a mistake right',
    'alerts'   => 'Know when money comes in',
    'problems' => 'Common problems',
];
?>
<article class="wrap article-wrap has-aside">
    <header class="article-head">
        <p class="kicker">Help centre</p>
        <h1><?= e($page['og']['title']) ?></h1>
        <p class="page-lead">Each time you record money from a customer, Nfelia makes the receipt for you, brings the balance down and keeps Debts and your monthly total right.</p>
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
        <p>To make a receipt in Nfelia, record the payment: choose <strong>Paid now</strong> or <strong>Part now</strong> when you save a new order, or tap <strong>Record payment</strong> on the order later. Type how much, choose <strong>Cash</strong>, <strong>Transfer</strong>, <strong>POS</strong> or <strong>MoMo</strong>, and save. The receipt is made straight away. Open it and tap <strong>WhatsApp</strong> to send it to your customer.</p>

        <h2 id="rule"><?= e($toc['rule']) ?></h2>
        <p>There is no separate receipt form. Every payment you record on an order makes its own receipt, numbered like IVL-REC-090, so every payment that comes in has proof, for you and for the customer. At the same time:</p>
        <ul>
            <li>the order's balance goes down, and its tag moves to <strong>Deposit paid</strong> or <strong>Paid</strong>;</li>
            <li>the customer's debt in <strong>Debts</strong> goes down, or clears;</li>
            <li>the money shows in <strong>Came in this month</strong> on Home and in <strong>Receipts this month</strong>.</li>
        </ul>

        <h2 id="now"><?= e($toc['now']) ?></h2>
        <?= site_steps([
            ['Start a New order', 'Tap <strong>+</strong>, then <strong>New order</strong>, and add the customer and the items. See ' . $to('help/send-an-invoice', 'how to send an invoice') . '.'],
            ['Choose Paid now or Part now', 'Under <strong>Payment</strong>, tap <strong>Paid now</strong>, or <strong>Part now</strong> and type what they paid.'],
            ['Say how they paid', 'Under <strong>Paid by</strong>, tap <strong>Cash</strong>, <strong>Transfer</strong>, <strong>POS</strong> or <strong>MoMo</strong>. Tap <strong>Add a reference</strong> to keep the transfer or POS reference.'],
            ['Save', 'Tap <strong>Save order</strong>, or <strong>Paid</strong> with the amount for a sale in the shop. You see, for example, <strong>Order … saved and paid. Receipt made.</strong>'],
        ]) ?>

        <h2 id="later"><?= e($toc['later']) ?></h2>
        <?= site_steps([
            ['Open the order', 'Open <strong>Orders</strong> and tap it. The <strong>Balance</strong> filter in Orders lists every order with money still to come.'],
            ['Tap Record payment', 'Under <strong>How much</strong>, type the amount, or tap <strong>All of it</strong> or <strong>Half</strong>. Choose how they paid, and add a reference if you like.'],
            ['Tap Save payment', 'You see <strong>Payment saved, receipt made.</strong> with the balance left, or <strong>Paid in full. Receipt made.</strong> The receipt shows under <strong>Money</strong> on the order.'],
        ]) ?>
        <p><strong>Record payment</strong> is also on the invoice itself. For an order from your website, the switch <strong>… is in my account</strong> on <strong>Confirm order</strong> records the payment and makes the receipt in the same step: see <?= $to('help/website-orders', 'website orders') ?>.</p>
        <div class="note"><strong>Check before you record</strong> Record a transfer once you see it in your bank or MoMo app, not when the customer says they sent it.</div>

        <h2 id="send"><?= e($toc['send']) ?></h2>
        <?= site_steps([
            ['Open the receipt', 'On the order, open <strong>Money</strong> and tap the receipt number. Or find it in the <strong>Orders</strong> tab under <strong>Receipts</strong>.'],
            ['Tap WhatsApp', 'WhatsApp opens with a thank-you message ready: the amount, the receipt number, any balance left and the link. Send it.'],
            ['Or send the PDF', 'Tap <strong>Share PDF</strong>, or <strong>Copy link</strong>. Under <strong>More</strong>, tap <strong>Download PDF</strong> or <strong>Open the customer\'s page</strong>.'],
        ]) ?>
        <p>On the order, <strong>WhatsApp</strong> also offers <strong>Send the receipt</strong> for the latest payment. Your customer opens the receipt on any phone: your logo, the <strong>Amount received</strong> and any <strong>Balance remaining</strong>. Like invoices, each receipt has a code anyone can check at <a href="<?= e(APP_URL . '/verify') ?>">nfelia.com/verify</a>.</p>

        <h2 id="find"><?= e($toc['find']) ?></h2>
        <p>Open the <strong>Orders</strong> tab and tap <strong>Receipts</strong>. At the top, <strong>Receipts this month</strong> shows what came in since the 1st. Search by the customer's name or the receipt number, and sort by date or by amount. The chips <strong>For invoices</strong> and <strong>Paid on the spot</strong> separate payments on orders from older on-the-spot receipts.</p>
        <?= site_screens([['paid-2', 'Receipts in Nfelia: ₦962,000 received this month, each payment listed by customer', 'Every receipt in one list'], ['paid-1', 'An order waiting for a payment check, with the customer\'s transfer screenshot attached', 'A website order with its proof']]) ?>
        <p class="meta">Screens from Ivie Luxe Hair, an example business.</p>

        <h2 id="fix"><?= e($toc['fix']) ?></h2>
        <ul>
            <li><strong>Recorded on the wrong day?</strong> On the order, open <strong>More</strong> and tap <strong>Change dates</strong>: you can set the day each payment came in. You see <strong>Dates saved. Your reports count it on those days.</strong> The owner and admins can do this.</li>
            <li><strong>Paid more than the total</strong>, after you changed the items? The order says so, and the owner or an admin chooses <strong>Keep for next order</strong> or <strong>Give it back</strong>.</li>
            <li><strong>Giving money back?</strong> On the order, open <strong>Money</strong> and tap <strong>Give a refund</strong>. Nfelia makes a refund slip to send the customer. The owner and admins can do this.</li>
            <li><strong>A receipt can't be deleted</strong> when it belongs to a payment on an order: it stays with its payment, so your records stay honest. If you saved a wrong payment, <a href="<?= e(site_wa('Hello Nfelia, I recorded a payment by mistake.')) ?>" target="_blank" rel="noopener">message us on WhatsApp</a> and we will help you put it right.</li>
        </ul>

        <h2 id="alerts"><?= e($toc['alerts']) ?></h2>
        <p>The owner and admins can have payments reach their phone the moment someone else on the team records them. Open <strong>More › Notifications</strong> and turn on <strong>Phone</strong> for <strong>Money in</strong>. To keep amounts off your lock screen, turn on <strong>Hide amounts on the lock screen</strong>. Staff never see amounts. See <?= $to('help/install-the-app', 'install the app') ?> to turn phone alerts on.</p>

        <h2 id="problems"><?= e($toc['problems']) ?></h2>
        <?= site_faq([
            ['It says the payment exceeds the remaining balance', 'You typed more than is left to pay. Tap <strong>All of it</strong> to fill in exactly the balance.'],
            ['It says "That payment was already saved"', 'The button was tapped twice, and Nfelia saved it once. Check <strong>Money</strong> on the order: the payment and its receipt are there.'],
            ['I can\'t find Record payment', 'It shows only while there is a balance. A fully paid order has nothing left to record. A new order from your website needs <strong>Confirm order</strong> first.'],
            ['It says no payments can be recorded on this order', 'The order was cancelled or turned down. Make a new order if the sale is back on.'],
            ['My customer wants the receipt as a PDF', 'Open the receipt and tap <strong>Share PDF</strong>, or <strong>More › Download PDF</strong>, and send the file.'],
            ['Someone asks if my receipt is real', 'Send them to <a href="' . e(APP_URL . '/verify') . '">nfelia.com/verify</a> with the code on the receipt, or let them scan its QR code.'],
        ]) ?>
        <p>Still stuck? <a href="<?= e(site_wa('Hello Nfelia, I need help with payments and receipts.')) ?>" target="_blank" rel="noopener">Message us on WhatsApp</a>.</p>
    </div>
</article>

<?= site_related(['help/send-an-invoice', 'help/debt-reminders', 'features/receipts', 'help/records-book']) ?>
<?= site_cta('Make every payment count') ?>
