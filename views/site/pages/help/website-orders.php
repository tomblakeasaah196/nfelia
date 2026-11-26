<?php
/**
 * Help: orders from your website. The shopper's bag and checkout
 * (views/storefront/parts/bag.php, PublicController::place_order), the
 * owner's alert (notify 'order.new'), Home's "Needs you", the Orders list
 * (views/orders/index.php) and the order page (views/orders/view.php,
 * OrderController, app/helpers/orders.php: order_stage, order_next_action),
 * and what the shopper hears (app/helpers/shop_orders.php).
 *
 * From site_render: $page, $country.
 */
require_once BASE_PATH . '/views/home/_icons.php';   // the view runs before the layout loads it

$to  = fn(string $key, string $text) => site_page($key) ? '<a href="' . e(site_url($key)) . '">' . e($text) . '</a>' : e($text);
$toc = [
    'customer'  => 'How a customer orders',
    'hear'      => 'How you hear about it',
    'confirm'   => 'Check the payment and confirm',
    'ready'     => 'Send it out, or have it ready',
    'tell'      => 'Keep the customer told',
    'turn-down' => 'Turn down or cancel an order',
    'whatsapp'  => 'Orders sent on WhatsApp',
    'problems'  => 'Common problems',
];
?>
<article class="wrap article-wrap has-aside">
    <header class="article-head">
        <p class="kicker">Help centre</p>
        <h1><?= e($page['og']['title']) ?></h1>
        <p class="page-lead">A customer orders on your website and pays you by transfer. You check the money, confirm, and Nfelia makes the invoice and receipt, takes the items off your stock and tells the customer.</p>
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
        <p>When a customer orders on your Nfelia website, the order lands in <strong>Orders</strong> as <strong>New</strong>, with their transfer screenshot if they sent one, and your phone gets an alert. Check that the money is in your account, then tap <strong>Confirm order</strong>. After that, tap <strong>Mark ready</strong> or <strong>Send it out</strong>, then <strong>Mark collected</strong> or <strong>Mark delivered</strong>. The customer follows each step on their own tracking link.</p>

        <h2 id="customer"><?= e($toc['customer']) ?></h2>
        <p>This is what your customer sees, so you can guide them if they ask:</p>
        <?= site_steps([
            ['They fill the bag', 'They tap an item, choose any options like length or colour, and tap <strong>Add to bag</strong>. The bar at the bottom shows the total and <strong>View bag</strong>. The Market look says "order" instead of "bag".'],
            ['Step 1 of 3: your details', 'They tap <strong>Check out</strong> and type their name, <strong>Phone or WhatsApp</strong>, and an email if they like.'],
            ['Step 2 of 3: getting it to you', 'They choose <strong>I\'ll pick it up</strong> or <strong>Deliver it to me</strong>, with their address, and can leave a note for you.'],
            ['Step 3 of 3: payment', 'They see your payment accounts and the amount to pay, pay by transfer, and can add a screenshot of the transfer. Then they tap <strong>Place my order</strong>, or <strong>Send my order on WhatsApp instead</strong>.'],
            ['Order placed', 'They see their order number and <strong>Follow your order</strong>, their private tracking link. If they gave an email, they also get a copy of the order there.'],
        ]) ?>
        <?= site_screens([['website-2', 'Checkout on Ivie Luxe Hair\'s website: pay by bank transfer into Ivie Luxe Hair\'s Moniepoint account', 'The customer pays you directly'], ['paid-1', 'An order in Nfelia waiting for a payment check, with the customer\'s transfer screenshot attached', 'The order, with its proof']]) ?>
        <p class="meta">Screens from Ivie Luxe Hair, an example business.</p>
        <p>The money goes straight into your account. Nfelia takes no commission. Nfelia always uses the prices in your Catalogue, so nobody can change a price on their side.</p>

        <h2 id="hear"><?= e($toc['hear']) ?></h2>
        <ul>
            <li><strong>On your phone:</strong> an alert like <em>New order</em>, with who ordered, how many items, and pickup or delivery. Turn alerts on as shown in <?= $to('help/install-the-app', 'install the app') ?>.</li>
            <li><strong>By email</strong>, unless you switched it off in <strong>More › Notifications</strong>.</li>
            <li><strong>In the bell</strong> at the top of the app, always.</li>
            <li><strong>On Home</strong>, under <strong>Needs you</strong>: <em>1 shop order is waiting. Check the payment, then confirm.</em></li>
        </ul>
        <p>Everyone on your team hears about new orders. Staff don't see the amounts.</p>

        <h2 id="confirm"><?= e($toc['confirm']) ?></h2>
        <?= site_steps([
            ['Open the order', 'Tap the alert, or open <strong>Orders</strong> and tap <strong>To confirm</strong>. A new order has the tag <strong>New</strong>.'],
            ['Check the items', 'Open <strong>Items</strong>. If you have fewer in stock than they ordered, the line says so, like <strong>Only 1 in stock</strong>.'],
            ['Check the money in your bank or MoMo app', 'Nfelia can\'t see your account, and a screenshot is not money. Open your own bank or mobile money app and look for the payment before you confirm.'],
            ['Tap Confirm order', 'The screenshot is at the top. Leave <strong>… is in my account</strong> on if the money arrived: it saves the payment and makes the receipt. Under <strong>When will they get it?</strong>, pick <strong>Today</strong>, <strong>Tomorrow</strong>, <strong>Pick a day</strong> or <strong>Not sure</strong>.'],
            ['Tap Confirm order again', 'You see <strong>Order confirmed and its invoice made.</strong> The items come off your stock, the customer is told by email if they gave one, and the order moves to <strong>Confirmed</strong>.'],
        ]) ?>
        <p>Not paid yet, or paid only a part? Turn the switch off, and set <strong>Balance due by</strong>. The balance goes into Debts under the customer's name, with a reminder on that day. Record the money later with <strong>Record payment</strong>: see <?= $to('help/receipts-and-payments', 'receipts and payments') ?>.</p>

        <h2 id="ready"><?= e($toc['ready']) ?></h2>
        <p>The big button at the top of the order always shows the next step:</p>
        <div class="table-scroll">
            <table>
                <caption class="sr-only">The next step on an order, for pickup and for delivery</caption>
                <thead><tr><th scope="col">The order is</th><th scope="col">Pickup: tap</th><th scope="col">Delivery: tap</th></tr></thead>
                <tbody>
                    <tr><th scope="row">New</th><td>Confirm order</td><td>Confirm order</td></tr>
                    <tr><th scope="row">Confirmed</th><td>Mark ready</td><td>Send it out</td></tr>
                    <tr><th scope="row">Ready for pickup, or On its way</th><td>Mark collected</td><td>Mark delivered</td></tr>
                    <tr><th scope="row">Collected, or Delivered, with a balance</th><td>Record payment</td><td>Record payment</td></tr>
                </tbody>
            </table>
        </div>
        <p>A delivery order gets its own delivery note. Under <strong>Delivery</strong> on the order, tap <strong>Copy sign-off link</strong> and send it to your rider: the customer signs on the phone when the goods arrive, and the order becomes <strong>Delivered</strong> by itself. See <?= $to('help/deliveries', 'deliveries') ?>.</p>

        <h2 id="tell"><?= e($toc['tell']) ?></h2>
        <p>Every website order has a tracking link. It shows the customer each step: <em>Order received</em>, <em>Checking your payment</em>, <em>Being prepared</em>, then <em>On its way</em> and <em>Delivered</em>, or <em>Ready for pickup</em> and <em>Collected</em>. If they gave an email, Nfelia emails them at each step too.</p>
        <p>To message them yourself, tap <strong>WhatsApp</strong> on the order. Ready-made messages fit where the order stands: <strong>Order received</strong>, <strong>Order confirmed</strong>, <strong>On its way</strong> or <strong>Ready for pickup</strong>, <strong>Balance reminder</strong>, <strong>Send the invoice</strong>, <strong>Send the receipt</strong> and <strong>Say thank you</strong>. Each one carries the right link. WhatsApp opens with the words ready; you tap send.</p>
        <p>Once an order is delivered or collected, the customer is invited to review it. Reviews show on your website under <strong>Customer reviews</strong>. You can't write or change them, which is why customers trust them.</p>
        <?= site_screens([['track-1', 'The tracking page for order IVL-ORD-018: received, payment confirmed, being prepared, on its way', 'What the customer follows'], ['track-2', 'A collected order on its tracking page, with the customer\'s five-star review', 'Done, with a review']]) ?>
        <p class="meta">Screens from Ivie Luxe Hair, an example business.</p>

        <h2 id="turn-down"><?= e($toc['turn-down']) ?></h2>
        <p><strong>Before you confirm:</strong> tap <strong>Turn down</strong>, add why if you like (for example, sold out until next week), and tap <strong>Turn down</strong> again. Nothing comes off your stock. The customer gets an email with your reason if they gave one; tell them on WhatsApp too.</p>
        <p><strong>After you confirm:</strong> before the order is delivered or collected, open <strong>More</strong> on the order and tap <strong>Cancel order</strong>. The owner and admins can do this. The items go back on your shelf. If the customer paid, you choose to give the money back or keep it for their next order.</p>

        <h2 id="whatsapp"><?= e($toc['whatsapp']) ?></h2>
        <p>If a customer taps <strong>Send my order on WhatsApp instead</strong>, the order is saved in Nfelia as <em>Shop, by WhatsApp</em>. They then tap <strong>Open WhatsApp to send it</strong>, and WhatsApp opens with the order written out, for them to send to you. Agree the payment in the chat, then confirm the order the same way.</p>
        <p>If you have no payment account, checkout tells customers you will contact them about payment. Add one in <strong>Settings › Business › Payment accounts</strong> so they can pay as they order.</p>

        <h2 id="problems"><?= e($toc['problems']) ?></h2>
        <?= site_faq([
            ['No screenshot came with the order', 'The switch says <strong>No screenshot came with this order</strong>. Check your bank or MoMo app. Turn it on once you see the money, or leave it off and record the payment when it arrives.'],
            ['The screenshot says paid, but the money is not in my account', 'Don\'t confirm it as paid. Call or WhatsApp the customer from the order. A screenshot can be wrong or fake; only your own account tells you the money arrived.'],
            ['I can\'t change the items on a new order', 'When the customer already paid for the order as it is, confirm it with their payment first, then tap <strong>Change items</strong>. If the new total is less, you choose to give the difference back or keep it for next time.'],
            ['A customer says they can\'t order any more today', 'One phone number can place up to 10 orders a day with your shop. Ask them to try tomorrow, or take the order yourself with <strong>New order</strong>.'],
            ['My phone does not buzz for new orders', 'Open <strong>More › Notifications</strong> and check <strong>This phone</strong>. On iPhone, add Nfelia to your Home Screen first. See ' . $to('help/install-the-app', 'install the app') . '.'],
            ['The customer ordered something I don\'t have', 'Turn the order down with a reason, or call them and offer something else. Turn on the sold-out switch in Shop Studio so items with no stock can\'t be ordered.'],
        ]) ?>
        <p>Still stuck? <a href="<?= e(site_wa('Hello Nfelia, I need help with an order from my website.')) ?>" target="_blank" rel="noopener">Message us on WhatsApp</a> with the order number.</p>
    </div>
</article>

<?= site_related(['help/receipts-and-payments', 'help/deliveries', 'features/orders', 'help/your-website']) ?>
<?= site_cta('Take orders on your own website') ?>
