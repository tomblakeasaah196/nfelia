<?php
/**
 * Feature: your own website (Shop Studio, the shop at name.nfelia.com, its
 * checkout, and being found). Every claim here is checked against
 * app/helpers/storefront.php, shop_links.php, seo.php,
 * LandingPageController, StorefrontController and PublicController::place_order.
 */
require_once BASE_PATH . '/views/home/_icons.php';   // nf_icon(): the layout loads it only after the view

$fw_prices = site_prices($page['country']);
$fw_days   = (int)($fw_prices['trial_days'] ?? TRIAL_DAYS);
$fw_month  = $fw_prices ? $fw_prices['money']((float)$fw_prices['month']) : null;
$fw_a      = fn(string $key, string $text): string => site_page($key) ? '<a href="' . e(site_url($key)) . '">' . e($text) . '</a>' : e($text);

// The other countries, open for sign-ups or not yet (site_prices)
$fw_open = $fw_soon = [];
foreach (site_countries() as $fw_c) {
    if ($fw_c['iso'] === 'NG') {
        continue;
    }
    $fw_key  = strtolower($fw_c['iso']);
    $fw_name = site_page($fw_key) ? '<a href="' . e(site_url($fw_key)) . '">' . e($fw_c['name']) . '</a>' : e($fw_c['name']);
    if (site_prices($fw_c['iso'])) {
        $fw_open[] = $fw_name;
    } else {
        $fw_soon[] = $fw_name;
    }
}
$fw_list = fn(array $names): string => count($names) > 1 ? implode(', ', array_slice($names, 0, -1)) . ' and ' . end($names) : (string)($names[0] ?? '');

echo site_hero([
    'kicker'    => 'Your website',
    'h1'        => 'Your own website, with every price on it',
    'lead_html' => 'Every Nfelia account comes with a website and online store of its own, at <strong>yourname.nfelia.com</strong>. Customers see your photos and prices, add to their bag and order. They pay into your own account, and Nfelia takes no commission.',
    'screens'   => [
        ['website-1', 'Ivie Luxe Hair’s website: bundles and wigs with photos, prices and Add buttons', 'Your prices, always current'],
        ['website-2', 'Checkout on Ivie’s website: pay ₦65,000 by bank transfer to Ivie Luxe Hair’s Moniepoint account', 'Paid into your account'],
    ],
]);
?>

<section class="page-section" aria-labelledby="fw-see"><div class="wrap split">
    <div class="split-copy">
        <h2 id="fw-see">What your customers see</h2>
        <p>Your website is a real shop, made for phones. It opens from one link you can put in your Instagram bio, your WhatsApp status and on your business card. No designer and no setup fee: it fills up as you add products.</p>
        <?= site_ticks([
            ['Everything you sell', 'on one page, with a search and a filter by category.'],
            ['A page for each product', 'with its photos, its price and choices like length, size or colour. A choice can cost more, like a longer wig.'],
            ['Ways to reach you', 'a WhatsApp button, your socials, and your address on a map with a Get directions button.'],
            ['Reviews from real orders', 'stars and words only from customers whose order was delivered or collected.'],
            ['News and offers', 'short posts for new stock, a sale or your holiday hours.'],
        ]) ?>
        <p>Sell a service? Customers can also pick a free time and pay the deposit: see <?= $fw_a('features/bookings', 'bookings with deposits') ?>. The screens on this page are from Ivie Luxe Hair, an example business in Lekki.</p>
    </div>
    <div class="screens">
        <figure class="screen">
            <div class="phone phone-browser">
                <div class="browser-bar"><span class="browser-url"><?= nf_icon('lock') ?><span>ivieluxe.nfelia.com</span></span></div>
                <img src="<?= APP_URL ?>/assets/img/home/site-ivieluxe.webp" width="600" height="1298" loading="lazy" decoding="async" alt="Ivie Luxe Hair’s website at ivieluxe.nfelia.com: the headline “Luxury hair, honest prices.”, a Shop now button, a photo of Ivie, and her bundles and wigs with prices">
            </div>
            <figcaption>One link for everything</figcaption>
        </figure>
        <figure class="screen">
            <div class="phone"><img src="<?= APP_URL ?>/assets/img/home/see/book-1.webp" width="540" height="1169" loading="lazy" decoding="async" alt="Booking a wig install on Ivie’s website: ₦25,000, 2 hours, 50% deposit, with a calendar of free days"></div>
            <figcaption>Bookings too</figcaption>
        </figure>
    </div>
</div></section>

<section class="page-section tint" aria-labelledby="fw-order"><div class="wrap split flip">
    <div class="split-copy">
        <h2 id="fw-order">How customers order and pay</h2>
        <p>Checkout takes three short steps on the phone. Nothing is paid through Nfelia: the money goes straight to you.</p>
        <?= site_steps([
            ['Their details', 'Name and phone or WhatsApp number. An email is optional; if they give one, they get the order by email with its tracking link.'],
            ['Pickup or delivery', 'For delivery they type the address. What you wrote about delivery shows here, so they know what to expect.'],
            ['Payment', 'They pay by ' . e(site_country($page['country'])['customers_pay']) . ' into an account you added in Settings, and can attach a screenshot of the transfer. Or they tap <strong>Send my order on WhatsApp instead</strong>.'],
        ]) ?>
        <p>The order lands in Nfelia with the customer saved to your clients. You check the transfer and confirm: the invoice and receipt are made, the stock comes off, and the customer’s tracking link shows each step. More on this in <?= $fw_a('features/orders', 'online orders and tracking') ?>.</p>
    </div>
    <div><?= site_screens([
        ['paid-1', 'An order waiting for a payment check, with the customer’s transfer screenshot attached', 'Proof comes with the order'],
        ['track-1', 'Tracking page for order IVL-ORD-018: received, payment confirmed, being prepared, on its way', 'They track it themselves'],
    ]) ?></div>
</div></section>

<section class="page-section" aria-labelledby="fw-looks"><div class="wrap">
    <?= site_head('Ten designs, changed from your phone', 'Pick a design when you choose your address, and change it any time in <strong>Shop Studio</strong>, with a live preview. Shoppers see nothing until you tap <strong>Publish</strong>, and your last ten versions are kept.', 'fw-looks', true) ?>
    <div class="compare table-scroll">
        <table>
            <caption class="sr-only">The ten website designs and the businesses each is made for</caption>
            <thead><tr><th scope="col">Design</th><th scope="col">Made for</th></tr></thead>
            <tbody>
                <?php foreach (STOREFRONT_LOOKS as $fw_look): ?>
                <tr><th scope="row"><?= e($fw_look['name']) ?></th><td><?= e($fw_look['for']) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="compare">
        <?= site_ticks([
            ['Your colours and fonts', 'from your logo and brand, or one of the design’s own. Text stays easy to read whatever colours you pick.'],
            ['Sections you turn on or off', 'and move up or down: an announcement bar, new arrivals, your story, a video, questions people ask, delivery and payment, a map.'],
            ['Your own words', 'on every heading and button.'],
            ['Sold out when it runs out', 'if you switch it on, an item with no stock left shows Sold out, with an Ask when it’s back button for WhatsApp.'],
        ]) ?>
    </div>
</div></section>

<section class="page-section rose" aria-labelledby="fw-share"><div class="wrap split">
    <div class="split-copy">
        <h2 id="fw-share">A WhatsApp store, an Instagram shop and a website in one</h2>
        <p>Most of your customers still find you on WhatsApp and Instagram. Your website does not replace that. It gives every chat one place to point to, so you stop typing prices into DMs.</p>
        <?= site_ticks([
            ['Share any product', 'from its page. The link shows a photo preview in WhatsApp.'],
            ['Image for your Status', 'makes a picture of a product with its link on it, ready for your WhatsApp Status or Instagram story.'],
            ['Ask on WhatsApp', 'on every product, so questions reach you with the product named.'],
            ['Wholesale prices', 'the Market design can show a minimum order, free delivery from an amount, and lower prices for buying more.'],
        ]) ?>
        <p>Tips for selling this way are in <?= $fw_a('blog/sell-on-whatsapp-and-instagram', 'how to sell on WhatsApp and Instagram') ?>.</p>
    </div>
    <div class="split-copy">
        <h2 id="fw-google">Found on Google, once you subscribe</h2>
        <p>During your free trial your website works for anyone with the link, but it stays off Google. From your first payment, Google may show it, with every product page’s name, photo and price written the way search engines read them.</p>
        <p>You can also join the Nfelia shop list at <a href="<?= APP_URL ?>/shops">nfelia.com/shops</a>, where people look for shops by city and by what they sell. The steps are in <?= $fw_a('help/get-found-on-google', 'how to get found on Google') ?>.</p>
    </div>
</div></section>

<section class="page-section" aria-labelledby="fw-who"><div class="wrap">
    <?= site_head('Who it helps', 'Any business that sells from a phone. A few we built the designs around:', 'fw-who') ?>
    <ul class="fits">
        <li><strong><?= $fw_a('for/online-sellers', 'Instagram and WhatsApp sellers') ?></strong><span>One link with every price, instead of “send me your price list” all day.</span></li>
        <li><strong><?= $fw_a('for/fashion-designers', 'Fashion designers') ?></strong><span>Your collection in the Atelier design, and a form for custom orders.</span></li>
        <li><strong><?= $fw_a('for/salons', 'Salons and beauty') ?></strong><span>Services with prices, and bookings with a deposit.</span></li>
        <li><strong><?= $fw_a('for/food-and-catering', 'Food and catering') ?></strong><span>A menu customers order from, and a form for party orders.</span></li>
        <li><strong><?= $fw_a('for/phones-and-gadgets', 'Phones and gadgets') ?></strong><span>Every model with its price, and Sold out when stock runs out.</span></li>
        <li><strong><?= $fw_a('for/events', 'Events and décor') ?></strong><span>Your packages, and a quote request for each event.</span></li>
    </ul>
</div></section>

<section class="page-section tint" aria-labelledby="faq-title"><div class="wrap">
    <?= site_head('Questions people ask', '', 'faq-title', true) ?>
    <?= site_faq([
        ['Is the website really free?',
         'It comes with Nfelia at no extra cost: no designer, no setup fee and no commission on sales. It is free for your first ' . $fw_days . ' days, like everything in Nfelia.'
         . ($fw_month ? ' After that it is part of the one monthly price, ' . e($fw_month) . ' a month in Nigeria, with invoices, receipts, debts and stock included. See ' . $fw_a('pricing', 'pricing') . '.' : ' See ' . $fw_a('pricing', 'pricing') . '.')],
        ['Do you take a cut of my sales?',
         'No. Customers pay into your own bank account or mobile money number, and the money is yours. Nfelia never handles it.'],
        ['Can I use my own .com address?',
         'Not for now. Your address is yourname.nfelia.com: 3 to 30 letters, numbers and hyphens. You can change the name once from Shop Studio, and for 90 days the old name sends visitors to the new one.'],
        ['Will people find my website on Google?',
         'Yes, once you subscribe. During the free trial it works for anyone with the link but stays off Google. You can also list it in the Nfelia shop directory, by city and by what you sell.'],
        ['What happens to my website if I stop paying?',
         'It shows a “closed for now” page with your WhatsApp button, so customers can still reach you, and it opens again when you pay. We keep your website and records for 12 months after your last paid month, and email you before anything is deleted.'],
        ['Does it work in Ghana, Kenya and Cameroon?',
         ($fw_open ? 'Sign-ups are open in ' . $fw_list($fw_open) . ', where customers can pay into a mobile money number or a bank account. ' : '')
         . ($fw_soon ? 'Nfelia is not open for sign-ups in ' . $fw_list($fw_soon) . ' yet. Message us on WhatsApp and we will tell you when it is. ' : '')
         . 'The screens on this page are from a Nigerian example business, so they show naira.'],
    ]) ?>
</div></section>

<?= site_related(['help/your-website', 'help/get-found-on-google', 'help/website-orders', 'blog/sell-on-whatsapp-and-instagram', 'features/orders', 'features/bookings']) ?>
<?= site_cta('Get your website today', 'Pick your address and your design in a few minutes. Free for ' . $fw_days . ' days, no card needed, and free setup help on WhatsApp.') ?>
