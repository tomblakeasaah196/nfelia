<?php
/**
 * /features/orders: website orders, tracking links, delivery notes the
 * customer signs (proof of delivery) and reviews from real orders.
 *
 * Checked against PublicController::place_order (bag, pickup or delivery,
 * transfer proof or WhatsApp), app/helpers/orders.php (order_confirm: the
 * invoice, stock, receipt, debt and delivery note; the stages and their
 * buttons), app/helpers/shop_orders.php and views/storefront/pages/order.php
 * (the tracking page, emails, reviews), app/controllers/DeliveryController.php,
 * PublicController::signoff and views/deliveries/*.php (delivery notes, the
 * sign-off link, proof of delivery), app/helpers/signing.php (signatures and
 * verify codes) and StorefrontController::review_page.
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
    'kicker'    => 'Orders and delivery',
    'h1'        => 'Every order tracked, every delivery signed for',
    'lead_html' => 'Nfelia gives every order its own tracking link, so customers see where it is without calling you. '
                 . 'When the goods arrive, your customer confirms on their phone with their name, and their signature if you ask for one: proof of delivery you can show anyone.',
    'note'      => 'Part of Nfelia. Free for ' . $trial . ' days, no card needed.',
    'screens'   => [
        ['track-1', 'Tracking page for order IVL-ORD-018: received, payment confirmed, being prepared, on its way', 'They check it themselves'],
        ['delivery-2', 'The customer types her name and signs with a finger to confirm she received it', 'Signed on the phone'],
    ],
]);
?>

<section class="page-section" aria-labelledby="in-title">
    <div class="wrap split">
        <div class="split-copy">
            <h2 id="in-title">Orders come in with the proof of payment</h2>
            <p>Customers add what they want to the bag on your website and choose pickup or delivery. They pay into your account by transfer and attach the screenshot, or they send the order to you on WhatsApp. Every order lands in <strong>Orders</strong>, and the customer is saved to your clients.</p>
            <?= site_ticks([
                ['One tap to confirm.', 'Confirm order makes the invoice, takes the items off your stock and, when they have paid, makes the receipt.'],
                ['No balance forgotten.', 'Whatever is left to pay goes into Debts, with a date to pay by.'],
                ['Delivery dates on your calendar.', 'The day you promise stays in Tasks & Calendar until it is done.'],
                ['Every sale in one list.', 'Orders from WhatsApp, Instagram or your counter go in too: tap New order. See them by To confirm, In progress, Balance or Done.'],
            ]) ?>
        </div>
        <div>
            <?= site_screens([['paid-1', 'An order waiting for a payment check, with the customer’s transfer screenshot attached', 'Proof comes with the order']]) ?>
        </div>
    </div>
</section>

<section class="page-section tint" aria-labelledby="track-title">
    <div class="wrap split flip">
        <div class="split-copy">
            <h2 id="track-title">A tracking link answers “Where is my order?”</h2>
            <p>Every order has its own private page on your website. Your customer gets the link when they order, and by email if they give one. There is no app to download and no account to make: it opens in any phone browser.</p>
            <p>Each time you move the order on, the page changes, and you can send the same news on WhatsApp from the order page in one tap.</p>
            <div class="compare table-scroll">
                <table>
                    <caption class="sr-only">What your customer sees when you move an order on</caption>
                    <thead><tr><th scope="col">You tap</th><th scope="col">Your customer sees</th></tr></thead>
                    <tbody>
                        <tr><th scope="row">Confirm order</th><td>Payment confirmed, then Being prepared. If they gave an email, it lists the whole order.</td></tr>
                        <tr><th scope="row">Send it out, or Mark ready</th><td>On its way, or Ready for pickup</td></tr>
                        <tr><th scope="row">Mark delivered or Mark collected, or they sign for it</th><td>Delivered or Collected, and a link to rate the order. Their receipt is there once they have paid.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div>
            <?= site_screens([['track-2', 'A collected order with the customer’s five-star review', 'Collected, with a review']]) ?>
        </div>
    </div>
</section>

<section class="page-section" aria-labelledby="pod-title">
    <div class="wrap split">
        <div class="split-copy">
            <h2 id="pod-title">Delivery notes and proof of delivery</h2>
            <p>When you confirm an order for delivery, Nfelia makes its delivery note, numbered like IVL-DEL-041, with what is going and where. You can also make one for any invoice, or on its own.</p>
            <p>Note who is taking it: your own rider, or a courier such as Kwik Delivery, GIG Logistics, Uber, Bolt or DHL, with the rider’s name and phone and any tracking number. Nfelia doesn’t book riders. It keeps the paperwork straight.</p>
            <?= site_ticks([
                ['Send the sign-off link on WhatsApp.', 'From the delivery note, one tap opens WhatsApp with the link ready for the person receiving the goods.'],
                ['They confirm on their phone.', 'They type their full name and tap to confirm. If you ask for signatures on delivery notes, they sign with a finger too.'],
                ['Proof you can show anyone.', 'The note shows who received it and when. Print it, download it as a PDF, or let anyone check its code at nfelia.com/verify.'],
                ['Everything moves on by itself.', 'The order shows Delivered, the date comes off your calendar and Nfelia tells you it arrived.'],
            ]) ?>
        </div>
        <div>
            <?= site_screens([['delivery-1', 'Delivery note IVL-DEL-041: what was sent, to whom, and the rider', 'What was sent, and with whom']]) ?>
        </div>
    </div>
</section>

<section class="page-section rose" aria-labelledby="reviews-title">
    <div class="wrap split">
        <div class="split-copy">
            <h2 id="reviews-title">Reviews people believe, from real orders</h2>
            <p>Once an order is delivered or collected, your customer is asked to rate it: 1 to 5 stars and a few words. Reviews show on <?= $link('features/website', 'your website') ?> with their first name and the first letter of their surname.</p>
        </div>
        <div class="split-copy">
            <?= site_ticks([
                ['Only real customers.', 'A review link is made only for a finished order, and each order can be reviewed once.'],
                ['You can’t dress them up.', 'Owners can’t write or change reviews. That is why shoppers trust them.'],
                ['You hear about each one.', 'Nfelia tells you when a new review comes in.'],
            ]) ?>
        </div>
    </div>
</section>

<?php $cards = $for_cards([
    ['for/online-sellers', 'Online sellers', 'Instagram and WhatsApp sellers send one link per order and answer fewer “Where is my order?” messages.', 'bag'],
    ['for/food-and-catering', 'Food and catering', 'Trays and party orders delivered on the promised day, and signed for at the door.', 'truck'],
    ['for/phones-and-gadgets', 'Phones and gadgets', 'A signed delivery note for every costly item that leaves your shop.', 'box'],
    ['for/fashion-designers', 'Fashion designers', 'Ready for pickup, with the balance paid and the receipt on the same page.', 'pen'],
]); ?>
<?php if ($cards !== ''): ?>
<section class="page-section" aria-labelledby="who-title">
    <div class="wrap">
        <?= site_head('Who it helps', 'Anyone who sends goods out, or gets asked the same question about an order ten times a day.', 'who-title') ?>
        <?= $cards ?>
    </div>
</section>
<?php endif; ?>

<section class="page-section tint" aria-labelledby="faq-title">
    <div class="wrap">
        <?= site_head('Questions about orders and delivery', '', 'faq-title', true) ?>
        <?= site_faq([
            ['What is proof of delivery?',
             'A record that the goods reached the right person: who received them and when, with their signature if you ask for one. In Nfelia it is on the delivery note, and anyone can check the note is genuine with the code at its foot, at nfelia.com/verify.'],
            ['Does my customer need an app or an account?',
             'No. The tracking page, the delivery note and the sign-off page all open from a link, in any phone browser.'],
            ['Can I track orders that didn’t come from my website?',
             'Yes. Add a sale from WhatsApp, Instagram or your shop with <strong>New order</strong>. It gets the same tracking link, which you can send from the order page in a ready-written WhatsApp message.'],
            ['Does Nfelia deliver for me?',
             'No. You deliver yourself or use your own rider or courier. Nfelia keeps the delivery note, the tracking page and the proof that it arrived.'],
            ['What if a delivery fails?',
             'Mark it <strong>Failed</strong> on the delivery note. When you are ready, tap <strong>Try again</strong> and send it out again.'],
            ['What does it cost?',
             $month !== null
                ? 'Orders, tracking links, delivery notes and reviews are all part of Nfelia: ' . e($month) . ' a month in Nigeria, with ' . $link('features/website', 'your website') . ' included. Free for ' . $trial . ' days, no card needed.'
                : 'Orders, tracking links, delivery notes and reviews are all part of Nfelia. Ask us on WhatsApp for the price where you are.'],
        ]) ?>
    </div>
</section>

<?= site_related(['help/website-orders', 'help/deliveries', 'features/receipts']) ?>
<?= site_cta('Send your next order with a tracking link') ?>
