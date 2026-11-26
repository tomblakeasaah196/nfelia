<?php
/**
 * Feature: receipts. Checked against ReceiptController, app/helpers/orders.php
 * (a quick sale makes its order, invoice and receipt in one go; every payment
 * on an order makes its receipt), views/receipts (what the receipt says and
 * how it is shared), notify.php (money-in alerts) and signing.php (codes).
 */
require_once BASE_PATH . '/views/home/_icons.php';   // nf_icon(): the layout loads it only after the view

$fr_a = fn(string $key, string $text): string => site_page($key) ? '<a href="' . e(site_url($key)) . '">' . e($text) . '</a>' : e($text);

echo site_hero([
    'kicker'    => 'Receipts',
    'h1'        => 'A proper receipt for every payment',
    'lead_html' => 'Nfelia is a receipt maker that works the way you sell. The moment you record a payment, in cash, by transfer or on POS, the receipt is made, numbered and ready to send on WhatsApp. Full payment or a deposit, walk-in or regular customer, every naira has its receipt.',
    'screens'   => [
        ['paid-1', 'An order waiting for a payment check, with the customer’s transfer screenshot attached', 'Proof comes with the order'],
        ['paid-2', 'Receipts: ₦962,000 received this month, each payment listed by customer, with its receipt number and how it was paid', 'Every receipt, in one list'],
    ],
]);
?>

<section class="page-section" aria-labelledby="fr-how"><div class="wrap split">
    <div class="split-copy">
        <h2 id="fr-how">You never sit down to “make” a receipt</h2>
        <p>Receipts come out of the money you record. There are three ways money comes in, and each one makes its receipt by itself.</p>
        <p>The screens on this page are from Ivie Luxe Hair, an example business in Lekki.</p>
    </div>
    <div><?= site_steps([
        ['A sale in the shop, paid in full', 'Tap <strong>New order</strong>, add the items and choose <strong>Paid now</strong>. For someone you don’t know, pick <strong>Walk-in customer</strong>: no name needed when they pay in full. The order, its invoice and the receipt are made in one go, and the items come off your stock.'],
        ['A deposit now, the rest later', 'Choose <strong>Part now</strong> and type what they paid. That payment gets its receipt, showing the amount received and the balance remaining. When the rest comes in, record it on the order or from ' . $fr_a('features/debts', 'Debts') . ', and that payment gets its own receipt too.'],
        ['An order from your website', 'The customer pays into your account and attaches a screenshot of the transfer. Check it against your bank app, then confirm the order: the invoice and the receipt are made together. See ' . $fr_a('features/website', 'your own website') . '.'],
    ]) ?></div>
</div></section>

<section class="page-section tint" aria-labelledby="fr-on"><div class="wrap split">
    <div class="split-copy">
        <h2 id="fr-on">What the receipt says</h2>
        <p>A receipt from Nfelia looks like it came from a big shop, because it has everything a careful customer, or their boss, looks for:</p>
        <?= site_ticks([
            ['Your logo and colours', 'with your business name, address and phone number.'],
            ['Its own number', 'like IVL-REC-301: your letters, REC, and a number that counts up.'],
            ['Who paid, when and how', 'cash, bank transfer, POS, mobile money or cheque.'],
            ['What it was for', 'the order and the invoice it pays, so anyone can see what the money bought.'],
            ['Amount received and balance remaining', 'so a deposit receipt says exactly what is still to pay.'],
            ['A code anyone can check', 'with a QR code. Typed at nfelia.com/verify, it shows the receipt is genuine.'],
        ]) ?>
        <p>Want it signed? In Settings under Signatures, receipts can carry a stamp or your own drawn signature for the business.</p>
    </div>
    <div class="split-copy">
        <h2 id="fr-send">Send it, print it, or let them keep it</h2>
        <p>Every receipt has a private link. Open the receipt in Nfelia and tap:</p>
        <ul class="fits">
            <li><strong>WhatsApp</strong><span>A chat opens with the message written: “Thank you for your payment of …”, the receipt number, any balance, and the link.</span></li>
            <li><strong>Share PDF</strong><span>The PDF itself, through your phone’s share sheet, on phones that can share files.</span></li>
            <li><strong>Copy link</strong><span>For SMS, Instagram or email.</span></li>
            <li><strong>Download PDF</strong><span>To print for a customer at the counter.</span></li>
        </ul>
        <p>Your customer can open the link any time and download the PDF themselves, on any phone, with no app.</p>
    </div>
</div></section>

<section class="page-section" aria-labelledby="fr-list"><div class="wrap split">
    <div class="split-copy">
        <h2 id="fr-list">Every receipt in one list, every naira accounted for</h2>
        <p>Receipts sit under <strong>Orders</strong>, next to your invoices and quotes. At the top is what came in this month and how many receipts that took. Search by customer or receipt number, and sort by date or by amount.</p>
        <?= site_ticks([
            ['Money-in alerts', 'when someone on your team records a payment, you and your admins hear about it on your phone or by email, as you choose.'],
            ['Receipts stay with their payment', 'a receipt for a payment on an order can’t be deleted on its own, so your records stay honest.'],
            ['Paid in advance', 'money a customer leaves with you for their next order gets a receipt too, and you can use it when they order.'],
            ['Ready for the bank', 'every receipt goes into your Records Book, each with its own code.'],
        ]) ?>
        <p>The Records Book puts all your receipts, invoices and payments in one Excel workbook anyone can check: see <?= $fr_a('features/records-book', 'the Records Book') ?>. To see what is left after spending, see <?= $fr_a('features/profit', 'expenses and profit') ?>.</p>
    </div>
    <div>
        <img src="<?= APP_URL ?>/assets/img/home/workbook-receipts.webp" width="1130" height="1000" loading="lazy" decoding="async"
             alt="The Receipts sheet of a Records Book: 282 receipts, each with its order, date, customer, invoice, amount and how it was paid, an Open link and its own verify code">
        <p class="price-note">The Receipts sheet of Ivie Luxe Hair’s Records Book, an example business, opened in LibreOffice.</p>
    </div>
</div></section>

<section class="page-section rose" aria-labelledby="fr-who"><div class="wrap">
    <?= site_head('Who it helps', 'Anyone whose customers say “send me receipt”.', 'fr-who') ?>
    <ul class="fits">
        <li><strong><?= $fr_a('for/phones-and-gadgets', 'Phone and gadget shops') ?></strong><span>A receipt with the model on it, for warranty and for peace of mind.</span></li>
        <li><strong><?= $fr_a('for/salons', 'Salons') ?></strong><span>A receipt for the booking deposit, made when you confirm the booking.</span></li>
        <li><strong><?= $fr_a('for/fashion-designers', 'Fashion designers') ?></strong><span>Deposit at measurement, balance at pickup, both on record.</span></li>
        <li><strong><?= $fr_a('for/online-sellers', 'Online sellers') ?></strong><span>Transfer in, receipt out, without scrolling back through chats.</span></li>
    </ul>
</div></section>

<section class="page-section tint" aria-labelledby="faq-title"><div class="wrap">
    <?= site_head('Questions people ask', '', 'faq-title', true) ?>
    <?= site_faq([
        ['Can I give a receipt to a walk-in customer?',
         'Yes. On a new order, pick Walk-in customer and choose Paid now. No name is needed when they pay in full, and the receipt is made straight away.'],
        ['Can I give a receipt for a deposit or part payment?',
         'Yes. Each payment gets its own receipt, showing the amount received and the balance remaining. When the balance is paid, that payment gets its receipt too.'],
        ['Do my customers need an app to see the receipt?',
         'No. The receipt opens from its link in any phone’s browser, and they can download it as a PDF from there.'],
        ['Can I print receipts?',
         'Yes. Tap Download PDF on any receipt and print it, or send the PDF to a printer from your phone.'],
        ['Can someone check a receipt is real?',
         'Yes. Every receipt carries a QR code and a code. Anyone, like a customer’s employer or a bank, can type it at nfelia.com/verify to see that the receipt is genuine and which business it is from.'],
        ['Can I put my logo on receipts?',
         'Yes. Add your logo and colours in Settings, and every receipt, invoice and quote wears them.'],
    ]) ?>
</div></section>

<?= site_related(['help/receipts-and-payments', 'features/invoices', 'features/records-book', 'features/debts', 'blog/business-records-for-a-loan', 'help/send-an-invoice']) ?>
<?= site_cta('Send a receipt the moment you are paid') ?>
