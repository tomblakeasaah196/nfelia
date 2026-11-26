<?php
/**
 * /contact: every way to reach the Nfelia team, and where to find answers
 * without waiting. The only reply time promised is the one the app already
 * gives for email (views/support/contact.php).
 */
require_once BASE_PATH . '/views/home/_icons.php';   // the building blocks draw icons before the layout loads them
$org  = site_org();
$link = fn(string $key, string $text) => site_page($key) ? '<a href="' . e(site_url($key)) . '">' . e($text) . '</a>' : e($text);
// Line icons for the two ways nf_icon() has none for
$svg  = fn(string $paths) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths . '</svg>';
$icon_call = $svg('<path d="M5.2 3.5h3.3l1.7 4.4-2.2 1.4a11.5 11.5 0 0 0 6.7 6.7l1.4-2.2 4.4 1.7v3.3a1.7 1.7 0 0 1-1.8 1.7A16.8 16.8 0 0 1 3.5 5.3a1.7 1.7 0 0 1 1.7-1.8Z"/>');
$icon_mail = $svg('<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 6.5 8.5 6.5 8.5-6.5"/>');

$ways = [
    [site_wa('Hello Nfelia, I have a question.'), nf_icon('whatsapp'), 'WhatsApp: ' . $org['phone_text'], 'The quickest way to reach us. Questions, free setup help and support.', true],
    ['tel:' . $org['phone'], $icon_call, 'Call: ' . $org['phone_text'], 'Speak to a person on the Nfelia team.', false],
    ['mailto:' . $org['support'], $icon_mail, 'Support: ' . $org['support'], 'Help with your account, your website or a payment. We reply within 24 hours.', false],
    ['mailto:' . $org['sales'], $icon_mail, 'Sales: ' . $org['sales'], 'Questions before you sign up, and what Nfelia costs in your country.', false],
    [APP_URL . '/book', nf_icon('calendar'), 'Book a call with us', 'Pick a time that suits you. We meet on Google Meet, from your phone or computer. Times are Lagos time.', false],
];

echo site_hero([
    'kicker'    => 'Contact',
    'h1'        => 'Talk to a real person at Nfelia',
    'lead_html' => 'The quickest way to reach us is WhatsApp on ' . e($org['phone_text']) . '. You can also call, send an email or book a video call with the team.',
    'actions'   => [['Chat on WhatsApp', site_wa('Hello Nfelia, I have a question.'), 'btn btn-primary', 'whatsapp'],
                    ['Book a call', APP_URL . '/book', 'btn btn-outline']],
]);
?>
<section class="page-section tint" aria-labelledby="ways-title">
    <div class="wrap split">
        <div class="split-copy">
            <h2 id="ways-title">Ways to reach us</h2>
            <p>Nfelia is a product of <?= e($org['legal']) ?>. We help businesses in Nigeria, Ghana, Kenya and Cameroon, in English.</p>
            <p>Already using Nfelia? Open <strong>More</strong>, then <strong>Contact support</strong>, to send us a message from the app.</p>
        </div>
        <ul class="contact-list">
            <?php foreach ($ways as [$href, $icon, $label, $text, $external]): ?>
            <li><a href="<?= e($href) ?>"<?= $external ? ' target="_blank" rel="noopener"' : '' ?>><?= $icon ?><div><strong><?= e($label) ?></strong><span><?= e($text) ?></span></div></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<section class="page-section" aria-labelledby="answers-title">
    <div class="wrap split">
        <div class="split-copy">
            <h2 id="answers-title">Find an answer now</h2>
            <p>Many questions are answered already, so you don’t have to wait for us.</p>
        </div>
        <ul class="contact-list">
            <?php if (site_page('help')): ?>
            <li><a href="<?= e(site_url('help')) ?>"><?= nf_icon('search') ?><div><strong>The help centre</strong><span>Step by step: set up, send an invoice, take bookings, add your team.</span></div></a></li>
            <?php endif; ?>
            <?php if (site_page('faq')): ?>
            <li><a href="<?= e(site_url('faq')) ?>"><?= nf_icon('chat') ?><div><strong>Questions people ask</strong><span>Price, free trial, your website, getting paid and your records.</span></div></a></li>
            <?php endif; ?>
            <li><a href="<?= APP_URL ?>/verify"><?= nf_icon('check-circle') ?><div><strong>Check a document is genuine</strong><span>Type or scan the code on an invoice, receipt or Records Book at nfelia.com/verify.</span></div></a></li>
            <li><a href="<?= APP_URL ?>/shops"><?= nf_icon('bag') ?><div><strong>Find a shop on Nfelia</strong><span>Looking for a business that uses Nfelia? Browse the shops by city and by what they sell.</span></div></a></li>
        </ul>
    </div>
</section>

<section class="page-section rose" aria-labelledby="shop-title">
    <div class="wrap">
        <?= site_head('Buying from a shop on Nfelia?', 'Each shop on nfelia.com is run by its own business. For an order, a delivery or a refund, talk to the shop first, with the WhatsApp button on its website or on your order’s tracking page. You pay the shop directly, into its own account, so Nfelia never holds your money. If something is still wrong, write to <a href="mailto:' . e($org['support']) . '">' . e($org['support']) . '</a> and tell us the shop’s name.', 'shop-title') ?>
    </div>
</section>

<?= site_related(['about', 'faq', 'help']) ?>
<?= site_cta('Ready when you are') ?>
