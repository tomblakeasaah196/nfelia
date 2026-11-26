<?php
/**
 * /for/online-sellers: Nfelia for Instagram, WhatsApp and TikTok sellers.
 *
 * Checked against the app (28 Oct 2026): the store at name.nfelia.com with a
 * page and link for every product, ten designs (STOREFRONT_LOOKS); "Image
 * for your Status" on live shops (views/storefront/parts/bag.php); checkout
 * with pickup or delivery, the transfer screenshot or "Send my order on
 * WhatsApp instead", both landing as orders (PublicController::place_order);
 * payment accounts by bank or mobile money (views/settings/payment_accounts.php);
 * "… is in my account" saves the payment and makes the receipt
 * (views/orders/view.php); tracking links and reviews only from real orders
 * (app/helpers/shop_orders.php); orders typed in as "Online or call"
 * (views/orders/form.php); kept off Google until the first payment.
 */
require_once BASE_PATH . '/views/home/_icons.php';   // site_hero and site_ticks draw icons before the layout loads them
$prices = site_prices($page['country']);
$link   = fn(string $key, string $text) => site_page($key) ? '<a href="' . e(site_url($key)) . '">' . e($text) . '</a>' : e($text);
$price_html = ($prices
        ? 'Free for ' . (int)$prices['trial_days'] . ' days with everything, and no card needed. Then ' . e(($prices['money'])($prices['month'])) . ' a month in Nigeria. '
        : 'Message us on WhatsApp for the price. ')
    . 'For Ghana, Kenya and Cameroon, see the prices for ' . $link('gh/pricing', 'Ghana') . ', ' . $link('ke/pricing', 'Kenya') . ' and ' . $link('cm/pricing', 'Cameroon') . '.';

$rows = [
    ['“Send me your price list”', 'Your store shows every price, always current.', 'features/website', 'Your website'],
    ['Orders lost in your DMs', 'Every order lands in Nfelia, with the customer saved.', 'features/orders', 'Online orders'],
    ['“Who paid? Send the screenshot again”', 'The transfer screenshot comes in with the order.', 'help/website-orders', 'Website orders'],
    ['“Where is my order?”', 'A tracking link for every order, and a delivery note they sign.', 'help/deliveries', 'Deliveries'],
    ['Getting found', 'Your store on Google once you subscribe, and in the Nfelia shop list.', 'help/get-found-on-google', 'Get found on Google'],
];

echo site_hero([
    'kicker'    => 'For Instagram, WhatsApp and TikTok sellers',
    'h1'        => 'One link for your bio. Every order in one place.',
    'lead_html' => 'Nfelia gives online sellers in Nigeria, Ghana, Kenya and Cameroon their own store at yourname.nfelia.com, the link for your Instagram, TikTok and WhatsApp bio. Customers order and send their transfer screenshot with it, and every order gets a tracking link. No more copying orders from DMs.',
    'screens'   => [['website-1', 'Ivie Luxe Hair\'s website: bundles and wigs with photos, prices and Add buttons', 'Your prices, always current'],
                    ['website-2', 'Checkout on Ivie\'s website: pay ₦65,000 by bank transfer to Ivie Luxe Hair\'s Moniepoint account', 'Paid into your account']],
]);
?>
<section class="page-section" aria-labelledby="store-title">
    <div class="wrap split">
        <div class="split-copy">
            <h2 id="store-title">Your store, ready for your bio</h2>
            <p>Every product gets its own page and link, with photos, price and an Add button. Pick one of ten designs and change the words, colours and photos from your phone. Customers pay into your own bank account or mobile money number, and Nfelia takes no commission.</p>
            <?= site_ticks([['Image for your Status:', 'on any product, make a picture with its link for your WhatsApp Status or Instagram story.'],
                            ['Prefer chat?', 'Customers can send their order on WhatsApp instead, and it still lands in Nfelia.'],
                            ['Found on Google', 'once you subscribe, and in the Nfelia shop list too.']]) ?>
        </div>
        <div class="screens">
            <figure class="screen"><div class="phone"><img src="<?= APP_URL ?>/assets/img/home/site-ivieluxe.webp" width="600" height="1298" loading="lazy" decoding="async"
                 alt="Ivie Luxe Hair's website at ivieluxe.nfelia.com: “Luxury hair, honest prices.”, a Shop now button, a photo of Ivie, and her bundles and wigs with prices"></div>
                <figcaption>One link for your bio</figcaption></figure>
        </div>
    </div>
</section>

<section class="page-section tint" aria-labelledby="orders-title">
    <div class="wrap split flip">
        <div class="split-copy">
            <h2 id="orders-title">Orders with proof, and a tracking link</h2>
            <p>An order arrives with what they picked, pickup or delivery with the address, and a screenshot of their transfer. Check your bank app, then tell Nfelia the money is in your account: the payment is saved and the receipt is made. Every order has its own tracking link, so customers see where it is without asking you.</p>
            <?= site_ticks([['Took an order in your DMs?', 'Add it as a new order, online or call. It gets a tracking link too.'],
                            ['On delivery,', 'the customer signs for it on the phone and is asked for a review.'],
                            ['Reviews only come from real orders,', 'so people trust them.']]) ?>
        </div>
        <div><?= site_screens([['paid-1', 'An order waiting for a payment check, with the customer\'s transfer screenshot attached', 'Proof comes with the order'],
                               ['track-1', 'Tracking page for order IVL-ORD-018: received, payment confirmed, being prepared, on its way', 'On its way']]) ?></div>
    </div>
    <p class="stories-note">Screens from Ivie Luxe Hair, an example business.</p>
</section>

<section class="page-section" aria-labelledby="fix-title">
    <div class="wrap">
        <?= site_head('What you deal with, and what Nfelia does', '', 'fix-title') ?>
        <div class="compare table-scroll">
            <table>
                <thead><tr><th scope="col">What you deal with</th><th scope="col">What Nfelia does</th></tr></thead>
                <tbody>
                <?php foreach ($rows as [$pain, $fix, $key, $text]): ?>
                    <tr><th scope="row"><?= e($pain) ?></th><td><?= e($fix) ?> <?= $link($key, $text) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="page-section tint" aria-labelledby="example-title">
    <div class="wrap">
        <?= site_head('An example day', 'An example to show how Nfelia fits an online seller. The customers and products are made up.', 'example-title') ?>
        <?= site_steps([
            ['Morning: post', 'You open your newest dress on your store, tap Image for your Status, and post the picture with its link to your WhatsApp Status and Instagram story.'],
            ['11am: an order', 'A customer in Port Harcourt orders two dresses for delivery and attaches her transfer screenshot.'],
            ['11:10am: check and approve', 'You see the money in your bank app and approve. The invoice and receipt are made, and she has her tracking link.'],
            ['2pm: an order in your DMs', 'Someone orders in your Instagram DMs and pays half. You add it as a new order, online. The balance waits in Debts.'],
            ['Next day: delivered', 'Your rider delivers. She confirms on her phone that it arrived, and her tracking page asks her for a review, by email too if she gave one.'],
        ]) ?>
    </div>
</section>

<section class="page-section" aria-labelledby="faq-title">
    <div class="wrap">
        <?= site_head('Questions online sellers ask', '', 'faq-title', true) ?>
        <?= site_faq([
            ['Can I put my Nfelia store in my Instagram or TikTok bio?',
             'Yes. Your store lives at yourname.nfelia.com, one link for every bio. Each product also has its own link to share in chats, stories and Status. ' . $link('help/your-website', 'Set up your website') . '.'],
            ['Do you take a cut of my sales?',
             'No. Customers pay into your own bank account or mobile money number, and the money is yours. Nfelia takes no commission; you pay one monthly price.'],
            ['What if a customer sends a fake transfer screenshot?',
             'The screenshot comes in with the order, but a screenshot alone is not proof. Check your bank app before you approve. Until you do, the order waits for a payment check, and no receipt is made.'],
            ['Will my store show on Google?',
             'Yes, once you subscribe. During the free trial your store works for anyone with the link, but it stays off Google until your first payment. You can also list it in the Nfelia shop list. ' . $link('help/get-found-on-google', 'How to get found on Google') . '.'],
            ['How much does Nfelia cost?', $price_html],
        ]) ?>
    </div>
</section>

<?= site_related(['features/website', 'features/orders', 'help/website-orders', 'blog/sell-on-whatsapp-and-instagram', 'help/get-found-on-google']) ?>
<?= site_cta('Get your store link today') ?>
