<?php
/**
 * Help: deliveries and sign-off. Checked against views/orders/view.php
 * (Send it out, Delivery, Copy sign-off link), order_confirm() in
 * app/helpers/orders.php (the delivery note it makes), views/deliveries
 * (form, view, signoff), DeliveryController, PublicController's sign-off,
 * views/settings/signatures.php and shop_order_tell() (the customer's email).
 */
require_once BASE_PATH . '/views/home/_icons.php';   // nf_icon() for the building blocks: the layout loads it only after the view
$u = fn(string $key) => e(site_url($key));
$toc = [
    'how'        => 'How delivery sign-off works',
    'order'      => 'Send out an order for delivery',
    'customer'   => 'What your customer sees',
    'note'       => 'Make a delivery note on its own',
    'statuses'   => 'Delivery statuses',
    'signed'     => 'Signed or one tap: your choice',
    'problems'   => 'Common problems and fixes',
];
?>
<article class="wrap article-wrap has-aside">
    <header class="article-head">
        <p class="kicker">Help centre</p>
        <h1>Send deliveries and get proof they arrived</h1>
        <p class="page-lead">Every order you deliver gets a delivery note. Send the sign-off link with your rider, and when the goods arrive the customer types their name on their own phone, or signs with a finger, to confirm they received them. You get an alert, and the order is marked delivered.</p>
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
        <h2 id="how">How delivery sign-off works</h2>
        <p>The rider says delivered and the customer says no: a delivery note ends that argument. It lists what was sent, to whom, by which rider, and when the customer confirmed it arrived, with their name and, if you choose, their signature.</p>
        <?= site_screens([
            ['delivery-1', 'Delivery note IVL-DEL-041 in Nfelia: the items sent, the customer they went to, and the rider', 'What was sent'],
            ['delivery-2', 'The customer types her name and signs with a finger on her phone to confirm she received the order', 'Signed on the phone'],
        ]) ?>
        <p>The screens are from Ivie Luxe Hair, an example business.</p>

        <h2 id="order">Send out an order for delivery</h2>
        <p>When an order is set for delivery, you do not need to make the note yourself: confirming the order makes it.</p>
        <?= site_steps([
            ['Confirm the order', 'An order you make in Nfelia for delivery starts confirmed. An order from your website waits for you: open it and tap <strong>Confirm order</strong>. Either way Nfelia makes the invoice and the delivery note together, and the order shows <strong>Confirmed</strong>.'],
            ['Tap Send it out', 'When the goods leave, tap <strong>Send it out</strong> at the top of the order. It now shows <strong>On its way</strong>. A customer who gave an email is told by email, and you can tell them on WhatsApp too: tap <strong>WhatsApp</strong>, then <strong>On its way</strong>.'],
            ['Copy the sign-off link', 'Open <strong>Delivery</strong> on the order. You see the delivery day, the address, the delivery note number and the rider. Tap <strong>Copy sign-off link</strong> and send it to your rider, or to the customer.'],
            ['The customer confirms on arrival', 'The rider shows the link to the customer, who confirms on their phone. The order then shows it was delivered, and you get an alert.'],
        ]) ?>
        <p>If the customer cannot confirm, for example they have no data, tap <strong>Mark delivered</strong> on the order yourself. To change the delivery day, open <strong>Delivery</strong> and use <strong>Change the delivery day</strong>. Orders from your website work the same way: read <a href="<?= $u('help/website-orders') ?>">how website orders work</a>.</p>

        <h2 id="customer">What your customer sees</h2>
        <ol>
            <li>A page with your business name, <strong>Delivery confirmation</strong>, and the items on the note.</li>
            <li>Under <strong>Confirm receipt</strong>, a box for <strong>Your full name</strong>, and on a signed note a space to draw <strong>Your signature</strong>, with <strong>Clear</strong> to start again.</li>
            <li>A button: <strong>Confirm received</strong>, or <strong>Sign and confirm</strong> on a signed note.</li>
            <li>Then a green message that the receipt is confirmed and the sender has been told. For an order, a <strong>Rate your order</strong> button asks for a review.</li>
        </ol>
        <p>Nothing to install, and no account: the link opens in any phone browser.</p>

        <h2 id="note">Make a delivery note on its own</h2>
        <p>For goods that did not come through an order, such as a delivery against an older invoice, make the note by hand.</p>
        <?= site_steps([
            ['Open Deliveries', 'Tap <strong>More</strong>, then <strong>Deliveries</strong>, then <strong>New delivery</strong>.'],
            ['Link an invoice, if there is one', 'Under <strong>Link to an invoice (optional)</strong>, search for it, and its items fill in.'],
            ['Fill in the recipient and items', '<strong>Recipient name</strong> and <strong>Delivery address</strong> are needed; add their <strong>Phone number</strong> too. Under <strong>Items being dispatched</strong>, add each item with its quantity.'],
            ['Add the dispatch details', 'Choose the <strong>Provider or courier</strong> and the <strong>Estimated delivery</strong> date. Open <strong>Rider or driver</strong> for the rider\'s name and phone, and <strong>Tracking</strong> for a courier\'s tracking number or link.'],
            ['Tap Create delivery', 'You land on the delivery note, with its number, its status <strong>Pending dispatch</strong> and the <strong>Sign-off link</strong>, with <strong>Copy</strong> and <strong>Send via WhatsApp</strong>.'],
        ]) ?>
        <p><strong>Special instructions</strong>, like "call before delivery", show on the note. <strong>Internal notes</strong> stay private.</p>

        <h2 id="statuses">Delivery statuses</h2>
        <p>On a delivery note, buttons move it along, and each change asks you to confirm first.</p>
        <div class="table-scroll">
            <table>
                <caption class="sr-only">The statuses of a delivery note in Nfelia and what each means</caption>
                <thead>
                    <tr><th scope="col">Status</th><th scope="col">What it means</th></tr>
                </thead>
                <tbody>
                    <tr><td>Pending dispatch</td><td>The note is made; the goods have not left.</td></tr>
                    <tr><td>Dispatched</td><td>Handed to the rider or courier. Tap <strong>Mark dispatched</strong>.</td></tr>
                    <tr><td>In transit</td><td>On the road. Tap <strong>Mark in transit</strong>.</td></tr>
                    <tr><td>Delivered</td><td>You marked it delivered, but the customer has not confirmed yet.</td></tr>
                    <tr><td>Received</td><td>The customer confirmed, or signed, from the sign-off link. You can also tap <strong>Mark received</strong> yourself; the note then shows that you marked it.</td></tr>
                    <tr><td>Failed</td><td>It could not be delivered. Tap <strong>Try again</strong> to send it out again.</td></tr>
                </tbody>
            </table>
        </div>
        <p>The <strong>Timeline</strong> on the note shows when each step happened. A note can be deleted only before it is in transit.</p>

        <h2 id="signed">Signed or one tap: your choice</h2>
        <p>By default the customer confirms with their name and one tap. For goods where you want a signature, such as expensive items or business customers:</p>
        <ul>
            <li>For one note, tick <strong>Signed delivery note</strong> on the form. It is signed for your business, and the receiver signs when it arrives.</li>
            <li>For every note, the owner or an admin opens <strong>Settings</strong> and, on the Business tab, goes down to <strong>Signatures</strong>. Switch on <strong>Delivery notes</strong>, choose <strong>Stamp</strong> or <strong>Drawn signature</strong>, and tap <strong>Save</strong>.</li>
        </ul>
        <p>Every note carries a QR code. Anyone can scan it, or type its code at <a href="<?= e(APP_URL) ?>/verify">nfelia.com/verify</a>, to check it is genuine and who signed it. Read more about <a href="<?= $u('help/records-book') ?>">codes and checking records</a>.</p>

        <h2 id="problems">Common problems and fixes</h2>
        <?= site_faq([
            ['There is no Copy sign-off link on the order. Why?',
             'The order is set for pickup, not delivery, or it is already delivered. A pickup order has no delivery note: tap Mark ready, then Mark collected when the customer collects it.'],
            ['The customer says the link does not work.',
             'The note may have been marked failed or cancelled, or it was already confirmed: a note can be received only once. Open the note in Deliveries to see its status, and tap Try again on a failed one.'],
            ['Can the customer sign on the rider\'s phone?',
             'Yes. The link works on any phone, so the rider can open it on theirs and hand it over. The name the customer types is what is kept.'],
            ['Does the customer need an account or an app?',
             'No. The sign-off page opens in the phone\'s browser with no sign-in.'],
            ['Where do I see who signed?',
             'On the delivery note, under Receipt: their name and the date and time they confirmed, with their signature on a signed note. The order\'s Log shows it too.'],
        ]) ?>
    </div>
</article>
<?= site_related(['features/orders', 'help/website-orders', 'help/send-an-invoice', 'help/tasks-and-reminders', 'for/online-sellers', 'help/records-book']) ?>
<?= site_cta('Get proof of every delivery') ?>
