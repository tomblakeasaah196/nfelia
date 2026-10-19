<?php
/**
 * Nfelia — public landing page (route type: Home / landing).
 *
 * Written for Nigerian market traders, Instagram/WhatsApp sellers and
 * service businesses: short lines, their words, real app screens.
 * Shown at / to visitors who are not signed in.
 *
 * "Sound familiar?" is a grid of cards, one problem each. A card links to
 * #its-id and opens a sheet with two real screens (home.js), so a feature
 * can be shared as nfelia.com/#bookings and Back closes it like an app.
 */

require_once BASE_PATH . '/views/home/_icons.php';

$wa_number = '2348169789106';
$wa_link   = fn(string $text = '') => 'https://wa.me/' . $wa_number . ($text !== '' ? '?text=' . rawurlencode($text) : '');

// Ready-made WhatsApp messages: one tap opens WhatsApp with the text filled in.
$wa_messages = [
    ['Start my free trial',          'Help me set up',              'Hello Nfelia, I want to start my free 14-day trial. Please help me set up my business.'],
    ['Ask if it fits my business',   'Tell us what you sell or do', 'Hello Nfelia, I run a small business and I want to know if Nfelia will work for me. I sell / I do: '],
    ['Ask about price and payment',  'After the free trial',        'Hello Nfelia, how much is it after the free trial, and how do I pay?'],
    ['Show me how it works',         'A quick demo',                'Hello Nfelia, please can someone show me how Nfelia works? I would like a quick demo.'],
];

// Set this to the YouTube video ID once the setup video is published.
$onboarding_video_id = '';

// Prices as Ops → Prices has them (app/helpers/prices.php), so a change
// there is the price here from the day it starts.
require_once BASE_PATH . '/app/helpers/prices.php';
$site_prices = prices_site();
$plan_price  = (int)round($site_prices['month']);     // ₦ per month
$seat_price  = (int)round($site_prices['seat']);      // ₦ per extra person
$book_price  = (int)round($site_prices['book']);      // ₦ for a Records Book outside a paid month
$per_day     = (int)(floor($plan_price / 30 / 10) * 10);
$naira       = fn(int $n) => '₦' . number_format($n);

$img      = APP_URL . '/assets/img/home';
$hero_img = is_file(BASE_PATH . '/assets/img/home/hero-1024.webp');

// Someone who tapped "Shop by Nfelia" at the foot of a tenant's website
// arrives with ?from=<its name>. Greet them with that business's name, but
// only for a real, live shop; a failed lookup just skips the greeting.
$from_shop = null;
$from      = strtolower(trim((string)($_GET['from'] ?? '')));
if ($from !== '' && preg_match('/^[a-z0-9-]{3,30}$/', $from)) {
    try {
        $from_shop = shop_find_by_name($from)['name'] ?? null;
    } catch (Throwable $e) {
        $from_shop = null;
    }
}

// One card per problem. The sheet behind it shows two real screens
// (assets/img/home/see/<shot>-1.webp and -2.webp, 540 × 1169).
$cards = [
    'owed' => [
        'icon'  => 'naira', 'site' => false, 'shot' => 'owed',
        'pain'  => '“I go pay you next week.”',
        'fix'   => 'Nfelia tracks it and reminds them',
        'title' => 'Get your money back',
        'lead'  => 'See who owes you, how much and who is late. Send a polite WhatsApp reminder in one tap.',
        'ticks' => ['The balance updates the moment you record a payment', 'The reminder says the balance and how many days late', 'Overdue money shows on your Home every morning'],
        'shots' => [['Sales screen: ₦185,000 owed to you on 5 unpaid invoices, 2 overdue', 'Who owes you'],
                    ['A balance of ₦60,000, 6 days overdue, with a green Send reminder button', 'One-tap reminder']],
    ],
    'price-list' => [
        'icon'  => 'globe', 'site' => true, 'shot' => 'website',
        'pain'  => '“Send me your price list.”',
        'fix'   => 'Your website shows every price',
        'title' => 'Your own website, with every price',
        'lead'  => 'One link for your bio and your WhatsApp status, like ivieluxe.nfelia.com. Customers see what you sell, add it to their bag and order.',
        'ticks' => ['10 designs made for Nigerian businesses', 'Customers pay into your own account. No commission', 'Every order lands in Nfelia, with the customer saved'],
        'shots' => [['Ivie Luxe Hair\'s website: bundles and wigs with photos, prices and Add buttons', 'Your prices, always current'],
                    ['Checkout on Ivie\'s website: pay ₦65,000 by bank transfer to Ivie Luxe Hair\'s Moniepoint account', 'Paid into your account']],
    ],
    'payments' => [
        'icon'  => 'chat', 'site' => false, 'shot' => 'paid',
        'pain'  => '“Who paid? Send the screenshot again.”',
        'fix'   => 'Every payment saved, with its proof',
        'title' => 'Know who paid, without scrolling chats',
        'lead'  => 'The transfer screenshot comes in with the order. Check it, approve, and the invoice and receipt are made for you.',
        'ticks' => ['Every receipt in one list', 'Money-in alerts on your phone', 'Send a receipt the moment you are paid'],
        'shots' => [['An order waiting for a payment check, with the customer\'s transfer screenshot attached', 'Proof comes with the order'],
                    ['Receipts: ₦962,000 received this month, each payment listed by customer', 'Every receipt, in one list']],
    ],
    'tracking' => [
        'icon'  => 'pin', 'site' => true, 'shot' => 'track',
        'pain'  => '“Where is my order?”',
        'fix'   => 'A tracking link answers for you',
        'title' => 'Customers check their order themselves',
        'lead'  => 'Every order gets its own tracking link: received, paid, on its way, delivered. No more answering the same question all day.',
        'ticks' => ['Sent with the order on WhatsApp and by email', 'After delivery, they can leave a review', 'Reviews come only from real orders, so people trust them'],
        'shots' => [['Tracking page for order IVL-ORD-018: received, payment confirmed, being prepared, on its way', 'On its way'],
                    ['A collected order with the customer\'s five-star review', 'Collected, with a review']],
    ],
    'profit' => [
        'icon'  => 'chart', 'site' => false, 'shot' => 'profit',
        'pain'  => 'Busy all month, but where is the profit?',
        'fix'   => 'Nfelia works out your profit',
        'title' => 'Know your real profit',
        'lead'  => 'Write down what you spend. Nfelia puts money in against money out, in plain words.',
        'ticks' => ['Money in, money spent and what is left, on your Home', 'Profit for any month', 'Download it as a PDF for your bank'],
        'shots' => [['Home: ₦962,000 came in this month, ₦695,500 spent, ₦266,500 left, and what needs you today', 'Your month at a glance'],
                    ['Profit and loss for October: ₦962,000 in, ₦695,500 out, ₦266,500 profit', 'Profit, worked out']],
    ],
    'bookings' => [
        'icon'  => 'calendar', 'site' => true, 'shot' => 'book',
        'pain'  => '“Can I book for Saturday?”',
        'fix'   => 'They pick a time and pay the deposit',
        'title' => 'Bookings with deposits',
        'lead'  => 'For hair, nails, fittings, events and shortlets. Customers pick a free time on your website and pay the deposit by transfer.',
        'ticks' => ['Only free times show, so no double booking', 'Confirm in one tap and it goes on your calendar', 'They get a page with the time and what is left to pay'],
        'shots' => [['Booking a wig install on Ivie\'s website: ₦25,000, 2 hours, 50% deposit, with a calendar of free days', 'They pick a free time'],
                    ['A booking request for Saturday at 10am, with Confirm and add to my calendar', 'You confirm in one tap']],
    ],
    'stock' => [
        'icon'  => 'box', 'site' => false, 'shot' => 'stock',
        'pain'  => 'Stock that doesn\'t add up',
        'fix'   => 'Stock that counts itself',
        'title' => 'Know what you have left',
        'lead'  => 'Send an invoice and the items come off your stock. See what is running low before customers ask.',
        'ticks' => ['A warning before a product runs out', 'What your stock is worth, at cost', 'Add many products at once from a list'],
        'shots' => [['Stock running low: a frontal wig with 2 left, a closure with 1 left, a hair care kit with 3 left', 'Running low'],
                    ['A wig\'s page: price, cost, 37.9% margin and 2 left in stock', 'Price, margin and stock']],
    ],
    'invoices' => [
        'icon'  => 'invoice', 'site' => false, 'shot' => 'invoice',
        'pain'  => 'Big client wants a proper invoice',
        'fix'   => 'Invoices with your logo, in a minute',
        'title' => 'Look like a big company',
        'lead'  => 'Quotes, invoices and receipts with your logo and colours. Your customer opens them from a WhatsApp link, on any phone.',
        'ticks' => ['Turn a quote into an invoice in one tap', 'Deposit paid and balance due, clearly shown', 'A PAID stamp when they finish paying'],
        'shots' => [['Kemi Events & Décor\'s invoice as the client sees it: deposit paid and balance due', 'Deposit and balance'],
                    ['Ivie Luxe Hair\'s invoice marked as fully paid', 'Paid in full']],
    ],
    'delivery' => [
        'icon'  => 'truck', 'site' => false, 'shot' => 'delivery',
        'pain'  => 'Delivery wahala',
        'fix'   => 'Proof they signed for it',
        'title' => 'Proof of delivery',
        'lead'  => 'The rider says delivered. The customer says no. Now your customer signs on the phone when the goods arrive.',
        'ticks' => ['A delivery note with what was sent and who took it', 'Signed with their name and a finger', 'Once they sign, they are asked for a review'],
        'shots' => [['Delivery note IVL-DEL-041: what was sent, to whom, and the rider', 'What was sent'],
                    ['The customer types her name and signs with a finger to confirm she received it', 'Signed on the phone']],
    ],
    'tasks' => [
        'icon'  => 'clock', 'site' => false, 'shot' => 'tasks',
        'pain'  => '“Was that delivery today or tomorrow?”',
        'fix'   => 'Nfelia reminds you before it\'s late',
        'title' => 'Never miss a delivery date',
        'lead'  => 'Deliveries, fittings and money due, on one list every morning. A countdown shows what is due and what is late.',
        'ticks' => ['Reminders on your phone and by email', 'Ready-made plans for sewing jobs and events', 'Give a task to anyone on your team'],
        'shots' => [['Tasks: a job with 2 days left, payments and a delivery that are late, and deliveries due today', 'Today, and what is late'],
                    ['Calendar for October with appointments, deliveries and money due marked on each day', 'Your month']],
    ],
    // Last and wide: the newest, and the one that says the records are theirs
    'records' => [
        'icon'  => 'sheet', 'site' => false, 'shot' => 'records', 'wide' => true, 'chip' => 'New',
        'pain'  => '“Bring your records for the loan.”',
        'fix'   => 'Your Records Book, ready to show',
        'title' => 'Your records, ready for the bank',
        'lead'  => 'Everything you record in Nfelia, in one Excel workbook in your colours. Whoever you show it to can check it is genuine at nfelia.com/verify.',
        'ticks' => ['Customers, sales, money and stock, with totals and a chart of every month', 'A code on every invoice and receipt that anyone can check', 'Free in every month you pay for. Your plain copy is always free'],
        'shots' => [['Your records in Nfelia: the Records Book, free this month, with a Make my Records Book button', 'Made in one tap'],
                    ['nfelia.com/verify showing Invoice IVL-INV-281 from Ivie Luxe Hair as genuine, with its order, amount and status', 'Anyone can check it']],
    ],
];

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en-NG">
<head>
    <meta charset="UTF-8">
    <?= errors_script_tag() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nfelia: your own website, invoices, debts and stock for Nigerian businesses</title>
    <meta name="description" content="Your own website at yourname.nfelia.com, plus invoices, receipts, debts and stock. All on your phone. Free for 14 days, then <?= $naira($plan_price) ?> a month.">
    <link rel="canonical" href="<?= APP_URL ?>/">
    <meta name="theme-color" content="#8B004A">

    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= APP_URL ?>/">
    <meta property="og:title" content="Nfelia: the thread that holds your business together">
    <meta property="og:description" content="Know who owes you, what you sold and made. Oh, and your own website comes free.">
    <meta property="og:image" content="<?= APP_URL ?>/assets/img/nfelia-og-banner.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:site_name" content="Nfelia">
    <meta property="og:locale" content="en_NG">
    <meta name="twitter:card" content="summary_large_image">

<?php require BASE_PATH . '/views/partials/pwa_head.php'; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,700&family=Caveat:wght@600&family=Plus+Jakarta+Sans:wght@400;600&display=swap">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/home.css?v=6">
    <?php if ($hero_img): ?>
    <link rel="preload" as="image" imagesrcset="<?= $img ?>/hero-640.webp 640w, <?= $img ?>/hero-1024.webp 1024w" imagesizes="(min-width: 960px) 492px, 92vw" fetchpriority="high">
    <?php endif; ?>
    <script>document.documentElement.classList.add('js');</script>

    <script type="application/ld+json">
    <?= json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'SoftwareApplication',
        'name' => 'Nfelia',
        'applicationCategory' => 'BusinessApplication',
        'operatingSystem' => 'Web, Android, iOS',
        'description' => 'Your own website, plus invoices, receipts, debts, stock and profit tracking for small businesses in Nigeria.',
        'offers' => ['@type' => 'Offer', 'price' => (string)$plan_price, 'priceCurrency' => 'NGN'],
        'publisher' => ['@type' => 'Organization', 'name' => 'JBS Praxis'],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
    </script>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header" data-header>
    <div class="wrap">
        <a class="brand" href="<?= APP_URL ?>/" aria-label="Nfelia home">
            <img src="<?= APP_URL ?>/assets/img/nfelia-mark-email.png" alt="" width="55" height="40">
            <span>NFELIA</span>
        </a>
        <nav class="nav-links" aria-label="Page sections">
            <a href="#website">Your website</a>
            <a href="#how">How it works</a>
            <a href="#pricing">Pricing</a>
            <a href="#questions">Questions</a>
            <button type="button" class="linkish" data-dialog="get-app">Get the app</button>
        </nav>
        <div class="header-actions">
            <a class="signin-link" href="<?= APP_URL ?>/login">Sign in</a>
            <a class="btn btn-primary btn-sm" href="<?= APP_URL ?>/register">Start free</a>
        </div>
    </div>
</header>

<?php if ($flash): ?>
<div class="flash <?= $flash['type'] === 'error' ? 'is-error' : '' ?>" role="status"><p><?= e($flash['message']) ?></p></div>
<?php endif; ?>

<?php if ($from_shop !== null): ?>
<div class="from-shop">
    <div class="wrap">
        <p><strong>Like <?= e($from_shop) ?>’s website?</strong> Get one like it for your business, free for 14 days.</p>
        <a class="btn btn-primary btn-sm" href="<?= APP_URL ?>/register">Start free</a>
    </div>
</div>
<?php endif; ?>

<main id="main">

<!-- Hero: one promise, two actions -->
<section class="hero">
    <div class="wrap">
        <div>
            <h1>The thread that holds your business together.</h1>
            <p class="hero-lead">Know who owes you, what you sold and made. Oh, and your own website comes free.</p>
            <div class="hero-actions">
                <a class="btn btn-primary" href="<?= APP_URL ?>/register">Start free for 14 days</a>
                <a class="btn btn-outline" href="<?= e($wa_link($wa_messages[3][2])) ?>" target="_blank" rel="noopener"><?= nf_icon('whatsapp') ?>Chat on WhatsApp</a>
            </div>
            <p class="hero-note"><?= nf_icon('check-circle') ?>No card needed. Free setup help on WhatsApp.</p>
        </div>

        <div class="hero-visual <?= $hero_img ? '' : 'no-photo' ?>">
            <?php if ($hero_img): ?>
                <div class="hero-photo">
                    <img src="<?= $img ?>/hero-1024.webp" srcset="<?= $img ?>/hero-640.webp 640w, <?= $img ?>/hero-1024.webp 1024w" sizes="(min-width: 960px) 492px, 92vw" width="1024" height="1280" alt="A hair vendor smiling at her phone behind the counter of her shop" fetchpriority="high">
                </div>
            <?php else: ?>
                <div class="laptop">
                    <div class="laptop-screen"><img src="<?= $img ?>/dashboard-laptop.webp" width="1440" height="900" alt="Nfelia dashboard showing today's sales, money owed and low stock" fetchpriority="high"></div>
                    <div class="laptop-base"></div>
                </div>
            <?php endif; ?>
            <div class="phone">
                <img src="<?= $img ?>/debts-summary-phone.webp" width="780" height="1688" alt="Debts screen: ₦185,000 owed by 5 customers, 2 of them overdue">
            </div>
            <div class="tag tag-owed" aria-hidden="true">
                <span class="tag-icon"><?= nf_icon('naira') ?></span>
                <span><strong>₦185,000</strong>owed to you</span>
            </div>
            <div class="tag tag-paid" aria-hidden="true">
                <span class="tag-icon"><?= nf_icon('check') ?></span>
                <span><strong>Paid in full</strong>₦72,000 invoice</span>
            </div>
            <svg class="thread" viewBox="0 0 520 650" preserveAspectRatio="none" aria-hidden="true">
                <path class="line" pathLength="1" d="M540 18 C 500 34, 468 70, 482 112 C 494 158, 542 204, 512 262 C 484 326, 532 398, 504 458 C 474 522, 384 598, 262 610 C 162 618, 96 592, 58 628 S -14 660, -40 642"/>
                <path class="stitch" d="M540 18 C 500 34, 468 70, 482 112 C 494 158, 542 204, 512 262 C 484 326, 532 398, 504 458 C 474 522, 384 598, 262 610 C 162 618, 96 592, 58 628 S -14 660, -40 642"/>
            </svg>
        </div>
    </div>
</section>

<svg class="seam" viewBox="0 0 1200 18" preserveAspectRatio="none" aria-hidden="true">
    <line x1="0" y1="9" x2="1200" y2="9" stroke="#8B004A" stroke-width="2"/>
    <line x1="0" y1="9" x2="1200" y2="9" stroke="#B69F5E" stroke-width="2" stroke-dasharray="3 12" transform="translate(0 -5)"/>
</svg>

<!-- Your own website: what customers see, joined to everything else -->
<section class="section site" id="website" aria-labelledby="site-title">
    <div class="wrap site-grid">
        <div class="section-head site-head" data-reveal>
            <h2 id="site-title">Customers order on your website. Nfelia does the rest.</h2>
            <p>Your own address, like ivieluxe.nfelia.com. Every order, booking and price request lands in Nfelia, with the customer saved. No more copying from DMs.</p>
        </div>

        <div class="site-visual" data-site-visual>
            <div class="phone phone-browser">
                <div class="browser-bar">
                    <span class="browser-url"><?= nf_icon('lock') ?><span data-type-url>ivieluxe.nfelia.com</span></span>
                </div>
                <img src="<?= $img ?>/site-ivieluxe.webp" width="600" height="1298" loading="lazy" alt="Ivie Luxe Hair's website at ivieluxe.nfelia.com: “Luxury hair, honest prices.”, a Shop now button, a photo of Ivie, and her bundles and wigs with prices">
            </div>
            <div class="tag tag-order" aria-hidden="true">
                <span class="tag-icon"><?= nf_icon('bag') ?></span>
                <span><strong>New website order</strong>₦145,000 · Kemi Bello</span>
            </div>
            <div class="tag tag-saved" aria-hidden="true">
                <span class="tag-icon"><?= nf_icon('user-plus') ?></span>
                <span><strong>Customer saved</strong>Added to your clients</span>
            </div>
        </div>

        <div class="site-details">
            <h3 class="site-thread-title">When someone orders, Nfelia</h3>
            <ol class="site-thread" data-reveal-group>
                <li>Saves the customer to your clients</li>
                <li>Sends them a tracking link</li>
                <li>Makes the invoice and receipt when you approve</li>
                <li>Gets the delivery signed for</li>
                <li>Asks them for a review</li>
            </ol>
            <ul class="site-points" data-reveal-group>
                <li><?= nf_icon('palette') ?><div><strong>Your name, your look</strong><span>10 designs made for Nigerian businesses. Change it from your phone.</span></div></li>
                <li><?= nf_icon('naira') ?><div><strong>Paid straight to you</strong><span>Customers pay into your own account. No commission.</span></div></li>
                <li><?= nf_icon('check-circle') ?><div><strong>Customers trust you</strong><span>A tracking link for every order. Reviews only from real orders.</span></div></li>
                <li><?= nf_icon('search') ?><div><strong>Found on Google</strong><span>Once you subscribe, Google can show it. Join the Nfelia shop list too.</span></div></li>
            </ul>
            <a class="btn btn-primary" href="<?= APP_URL ?>/register">Get your website</a>
        </div>
    </div>
</section>

<!-- Sound familiar? Each card opens a sheet with the fix (home.js) -->
<section class="section see" id="see" aria-labelledby="see-title">
    <div class="wrap see-grid">
        <div class="see-side">
            <div class="section-head" data-reveal>
                <h2 id="see-title">Sound familiar?</h2>
                <p>Tap one to see how Nfelia fixes it.</p>
            </div>
            <div class="ledger" aria-hidden="true" data-reveal>
                <p class="title">Who owes me</p>
                <p>Mama Bisi: 15,000 <span class="red">(next week)</span></p>
                <p><s>Chidi, shop 4: 40,000</s> 25,000??</p>
                <p>Lady with red car: 18k</p>
                <p>Sold 3 bundles... or 4?</p>
                <p><s>Tunde: 9,500</s> <span class="red">paid? check</span></p>
                <p>Transfer screenshot???</p>
                <span class="ring"></span>
            </div>
        </div>
        <ul class="see-cards" data-reveal-group>
            <?php foreach ($cards as $id => $c): ?>
            <li<?= !empty($c['wide']) ? ' class="is-wide"' : '' ?>>
                <a class="see-card" href="#<?= $id ?>" data-see="<?= $id ?>" aria-haspopup="dialog">
                    <span class="see-top">
                        <span class="see-icon"><?= nf_icon($c['icon']) ?></span>
                        <?php if ($c['site'] || !empty($c['chip'])): ?><span class="see-chip"><?= e($c['chip'] ?? 'Your website') ?></span><?php endif; ?>
                    </span>
                    <h3><?= e($c['pain']) ?></h3>
                    <span class="see-fix"><?= e($c['fix']) ?><?= nf_icon('arrow') ?></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <p class="see-note">Screens show Ivie Luxe Hair and Kemi Events &amp; Décor, example businesses.</p>
</section>

<!-- The Records Book: their records, in one workbook they keep. Real
     pictures of the example shop's book (docs/handoff/testkit/shoot_workbook.sh);
     the sheet buttons are tabs once home.js runs, and all three show without it. -->
<section class="section books" id="records-book" aria-labelledby="books-title">
    <div class="wrap books-grid">
        <div class="section-head books-head" data-reveal>
            <h2 id="books-title">Your records, in one Excel book</h2>
            <p>Everything you record in Nfelia, in a workbook made in your colours: customers, sales, money and stock, with totals and a chart of every month. Show it to your bank or your accountant, or take it wherever your business goes next.</p>
        </div>

        <div class="book-window" data-book-tabs>
            <div class="book-tabs" role="tablist" aria-label="Sheets of a Records Book">
                <button type="button" role="tab" id="book-tab-cover" aria-controls="book-cover" aria-selected="true">Cover</button>
                <button type="button" role="tab" id="book-tab-glance" aria-controls="book-glance" aria-selected="false" tabindex="-1">At a glance</button>
                <button type="button" role="tab" id="book-tab-receipts" aria-controls="book-receipts" aria-selected="false" tabindex="-1">Receipts</button>
            </div>
            <figure class="book-shot" id="book-cover" role="tabpanel" aria-labelledby="book-tab-cover">
                <a class="book-zoom" href="<?= $img ?>/workbook-cover.webp" target="_blank" rel="noopener"><img src="<?= $img ?>/workbook-cover.webp" width="1130" height="1000" loading="lazy" decoding="async"
                     alt="The cover of Ivie Luxe Hair's Records Book, open in a spreadsheet: the Nfelia logo, the shop's name in its colours, how long it has been with Nfelia, its details, its customers, sales, money received and orders, and a QR code to check the book"></a>
                <figcaption><strong>The cover.</strong> Your name and colours, your figures, and a code anyone can scan to check the book.</figcaption>
            </figure>
            <figure class="book-shot" id="book-glance" role="tabpanel" aria-labelledby="book-tab-glance">
                <a class="book-zoom" href="<?= $img ?>/workbook-glance.webp" target="_blank" rel="noopener"><img src="<?= $img ?>/workbook-glance.webp" width="1130" height="1000" loading="lazy" decoding="async"
                     alt="The At a glance sheet: totals for sales, money in, spending and what is owed, a table of every month, and a chart of the months"></a>
                <figcaption><strong>At a glance.</strong> Every month side by side, with totals and a chart, worked out for you.</figcaption>
            </figure>
            <figure class="book-shot" id="book-receipts" role="tabpanel" aria-labelledby="book-tab-receipts">
                <a class="book-zoom" href="<?= $img ?>/workbook-receipts.webp" target="_blank" rel="noopener"><img src="<?= $img ?>/workbook-receipts.webp" width="1130" height="1000" loading="lazy" decoding="async"
                     alt="The Receipts sheet: 282 receipts, each with its order, date, customer, invoice, amount and how it was paid, an Open link and its own verify code"></a>
                <figcaption><strong>Every record.</strong> Each receipt, invoice and payment in its own sheet, with a link to it and a code of its own.</figcaption>
            </figure>
        </div>

        <div class="books-details">
            <ul class="site-points books-points" data-reveal-group>
                <li><?= nf_icon('check-circle') ?><div><strong>Anyone can check it</strong><span>The book, and every invoice and receipt in it, has a code. Scan it or type it at nfelia.com/verify to see it is genuine.</span></div></li>
                <li><?= nf_icon('sheet') ?><div><strong>Opens anywhere</strong><span>Excel, Google Sheets, LibreOffice or Numbers. Sort and filter any sheet; the totals follow.</span></div></li>
                <li><?= nf_icon('naira') ?><div><strong>Free in every month you pay for</strong><span>One book each paid month, <?= $naira($book_price) ?> at other times.</span></div></li>
                <li><?= nf_icon('lock') ?><div><strong>Your plain copy, always free</strong><span>Every record as CSV files, whatever your plan. Your records belong to you.</span></div></li>
            </ul>
            <a class="btn btn-primary" href="<?= APP_URL ?>/register">Start free for <?= (int)TRIAL_DAYS ?> days</a>
            <p class="books-note">Pictures of the Records Book of Ivie Luxe Hair, an example business, opened in LibreOffice.</p>
        </div>
    </div>
</section>

<!-- Example stories (clearly not reviews) -->
<section class="section stories" aria-labelledby="stories-title">
    <div class="wrap">
        <div class="section-head center" data-reveal>
            <h2 id="stories-title">Made for businesses like yours</h2>
        </div>
        <div class="stories-grid" data-reveal-group>
            <article class="story">
                <img class="story-photo" src="<?= $img ?>/story-ivie.webp" width="720" height="450" loading="lazy" alt="Ivie smiling at her phone in her hair shop">
                <div class="story-body">
                <div class="story-who">
                    <div><h3>Ivie Luxe Hair</h3><p>Sells hair at Lekki Town Square</p></div>
                </div>
                <div class="before-after">
                    <div class="before"><b>Before</b>Regular customers took bundles on credit. Their names lived in a notebook.</div>
                    <div class="after"><b>With Nfelia</b>She sees ₦185,000 owed by five customers and reminds them on WhatsApp in seconds.</div>
                </div>
                </div>
            </article>
            <article class="story">
                <img class="story-photo" src="<?= $img ?>/story-kemi.webp" width="720" height="450" loading="lazy" alt="Kemi checking her phone while her team sets up an event hall">
                <div class="story-body">
                <div class="story-who">
                    <div><h3>Kemi Events &amp; Décor</h3><p>Plans parties and corporate events in Lagos</p></div>
                </div>
                <div class="before-after">
                    <div class="before"><b>Before</b>Sent prices by text. Lost an office party to a planner with a proper quote.</div>
                    <div class="after"><b>With Nfelia</b>Sends a branded quote, takes a 50% deposit and tracks the balance till the event.</div>
                </div>
                </div>
            </article>
        </div>
        <p class="stories-note">These are example stories to show how Nfelia fits a business. They are not customer reviews.</p>

        <ul class="fits" data-reveal-group>
            <li><strong>Fashion designers</strong><span>Quote fabric and sewing, take a deposit, collect the balance at pickup.</span></li>
            <li><strong>Salons and beauty</strong><span>Clients book a time on your website and pay the deposit first.</span></li>
            <li><strong>Food and catering</strong><span>Party quotes, deposits, and what you spent on foodstuff.</span></li>
            <li><strong>Phones and gadgets</strong><span>Stock for every model, receipts with your logo, and who owes you.</span></li>
        </ul>
    </div>
</section>

<!-- How it works -->
<section class="section how" id="how" aria-labelledby="how-title">
    <div class="wrap how-grid">
        <div>
            <div class="section-head" data-reveal>
                <h2 id="how-title">Start in three steps</h2>
                <p>If you can use WhatsApp, you can use Nfelia.</p>
            </div>
            <ol class="steps" data-reveal-group>
                <li><div><h3>Sign up free</h3><p>Your name, your business name and an email. Then pick your website address.</p></div></li>
                <li><div><h3>Add your products and customers</h3><p>All at once, or one by one as you sell. Your website fills up as you go.</p></div></li>
                <li><div><h3>Send your first invoice</h3><p>Share it on WhatsApp and watch the money come in.</p></div></li>
            </ol>
        </div>
        <div>
            <?php if ($onboarding_video_id !== ''): ?>
                <div class="video" data-video="<?= e($onboarding_video_id) ?>">
                    <button type="button" class="video-play" aria-label="Play the setup video">
                        <img src="https://i.ytimg.com/vi/<?= e($onboarding_video_id) ?>/hqdefault.jpg" alt="" loading="lazy" width="480" height="360">
                        <span><?= nf_icon('play') ?></span>
                    </button>
                </div>
            <?php else: ?>
                <!-- Brand intro: home.js plays it once, muted, when it is in view. The still
                     of its last frame stays for no JavaScript, reduced motion or Data Saver. -->
                <div class="video video-intro" data-intro data-src="<?= APP_URL ?>/assets/video/nfelia-intro.mp4" data-start="<?= $img ?>/intro-start.webp">
                    <img src="<?= $img ?>/intro-end.webp" width="960" height="540" loading="lazy" decoding="async" alt="Nfelia: the simple way to look big and track everything.">
                </div>
                <div class="video-note">
                    <strong>Setup video coming soon</strong>
                    <p>Want help now? <a href="<?= e($wa_link($wa_messages[0][2])) ?>" target="_blank" rel="noopener">Message us on WhatsApp</a> and we will set you up.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Pricing -->
<section class="section pricing" id="pricing" aria-labelledby="pricing-title">
    <div class="wrap">
        <div class="section-head center" data-reveal>
            <h2 id="pricing-title">One simple price</h2>
            <p>Try everything free first. Pay only if it works for you.</p>
        </div>
        <div class="price-card" data-reveal>
            <div class="price-main">
                <p class="price-amount"><?= $naira($plan_price) ?> <small>a month</small></p>
                <p class="price-day">About <?= $naira($per_day) ?> a day</p>
                <p class="price-compare">Collect one ₦30,000 debt with a reminder and Nfelia has paid for itself for three months.</p>
                <div class="price-trial">
                    <strong>Free for <?= (int)TRIAL_DAYS ?> days</strong>
                    <span>Use everything. No card needed to start.</span>
                </div>
                <a class="btn btn-primary" href="<?= APP_URL ?>/register">Start free for <?= (int)TRIAL_DAYS ?> days</a>
            </div>
            <div class="price-includes">
                <h3>Everything included</h3>
                <ul class="ticks">
                    <li><?= nf_icon('check') ?><span><strong>Your own website</strong> at yourname.nfelia.com</span></li>
                    <li><?= nf_icon('check') ?>No commission: customers pay you directly</li>
                    <li><?= nf_icon('check') ?>Unlimited invoices, quotes and receipts</li>
                    <li><?= nf_icon('check') ?>Debt tracker with WhatsApp reminders</li>
                    <li><?= nf_icon('check') ?>Bookings with deposits</li>
                    <li><?= nf_icon('check') ?>Stock tracking for up to <?= number_format(MAX_PRODUCTS) ?> products</li>
                    <li><?= nf_icon('check') ?>Monthly profit report</li>
                    <li><?= nf_icon('check') ?>Tasks and calendar with reminders</li>
                    <li><?= nf_icon('check') ?>Delivery notes your customer signs</li>
                    <li><?= nf_icon('check') ?><span><strong>Your Records Book</strong>, free every month you pay</span></li>
                    <li><?= nf_icon('check') ?>Free setup help on WhatsApp</li>
                </ul>
                <p class="price-extra">Need staff on it? <?= $naira($seat_price) ?> a month for each extra person.</p>
            </div>
        </div>
    </div>
</section>

<!-- FAQ -->
<section class="section faq" id="questions" aria-labelledby="faq-title">
    <div class="wrap">
        <div class="section-head center" data-reveal>
            <h2 id="faq-title">Questions people ask</h2>
        </div>
        <div class="faq-list" data-reveal>
            <details>
                <summary>Is the website really free? Is it a real website?</summary>
                <p>Yes. It comes with Nfelia at no extra cost: no designer, no setup fee. It lives at yourname.nfelia.com (like ivieluxe.nfelia.com), in one of 10 designs made for Nigerian businesses, and you change the words, colours and photos from your phone. A .com address of your own is not part of Nfelia for now.</p>
            </details>
            <details>
                <summary>Do you take a cut of my sales?</summary>
                <p>No. Customers pay into your own bank account by transfer, and the money is yours. Nfelia takes no commission; you only pay the monthly price.</p>
            </details>
            <details>
                <summary>Will people find my website on Google?</summary>
                <p>Yes, once you subscribe. During the free trial your website works for anyone with the link, but we keep it off Google until your first payment. You can also list it in the Nfelia shop directory, by city and by what you sell.</p>
            </details>
            <details>
                <summary>Do I need a laptop or a lot of data?</summary>
                <p>No laptop needed. Nfelia opens in the browser on any smartphone, and you can add it to your home screen so it opens like an app, with no app store download. It needs a little data, like WhatsApp does, and pages you have already opened still open when the network drops. Use a laptop too if you like; your records are the same on both.</p>
            </details>
            <details>
                <summary>Will it remind me?</summary>
                <p>Yes. On mornings with something planned, Nfelia emails you the day at 7am: deliveries, appointments and money due. Any task can also remind you at the time you choose, by email and on your phone. On iPhone, add Nfelia to your Home Screen first (tap Share, then Add to Home Screen).</p>
            </details>
            <details>
                <summary>What happens after the 14 days?</summary>
                <p>You decide. Pay <?= $naira($plan_price) ?> a month to keep creating invoices and receipts and to keep your website open. If you stop, nothing is deleted: your records stay safe, and your website shows a “closed for now” page with your WhatsApp button until you come back.</p>
            </details>
            <details>
                <summary>How do I pay?</summary>
                <p>Online with Paystack, the payment service many Nigerian businesses already use.</p>
            </details>
            <details>
                <summary>Can my staff use it? What if someone is not honest?</summary>
                <p>Each staff member gets their own login for <?= $naira($seat_price) ?> a month. Every sale reduces your stock, so missing goods show up quickly, and only you can add or remove staff.</p>
            </details>
            <details>
                <summary>Is my business information safe? Can I take it with me?</summary>
                <p>Your records belong to you. We never sell your data or contact your customers. Every month you pay for comes with your <a href="#records-book">Records Book</a>: all your records in one Excel workbook, in your colours, that anyone can check is genuine. Your plain copy (every record as CSV files) is free whenever you ask, whatever your plan, and invoices, receipts and your profit report download as PDF any time.</p>
            </details>
            <details>
                <summary>I'm a fashion designer. Where do I keep measurements?</summary>
                <p>Save each client's measurements in the notes on their customer profile, next to their quotes, deposits and balances. A proper measurement book is coming soon.</p>
            </details>
            <details>
                <summary>I'm not good with computers. Can I still use it?</summary>
                <p>Yes. If you can send a WhatsApp message, you can send an invoice on Nfelia. We help you set up for free, and we are one WhatsApp message away if you get stuck.</p>
            </details>
        </div>
    </div>
</section>

<!-- Final call -->
<section class="section final" aria-labelledby="final-title">
    <svg class="final-thread" viewBox="0 0 1200 400" preserveAspectRatio="none" aria-hidden="true">
        <path d="M-20 320 C 200 220, 360 380, 600 260 S 980 120, 1220 200"/>
    </svg>
    <div class="wrap" data-reveal>
        <h2 id="final-title">Try it free for 14 days. E no go cost you one kobo.</h2>
        <p>Set up today, send your first invoice tonight.</p>
        <div class="hero-actions">
            <a class="btn btn-light" href="<?= APP_URL ?>/register">Start free for 14 days</a>
            <a class="btn btn-ghost-light" href="<?= e($wa_link($wa_messages[1][2])) ?>" target="_blank" rel="noopener"><?= nf_icon('whatsapp') ?>Ask us on WhatsApp</a>
        </div>
    </div>
</section>

</main>

<footer class="site-footer">
    <div class="wrap">
        <div class="footer-grid">
            <div>
                <a class="brand" href="<?= APP_URL ?>/" aria-label="Nfelia home">
                    <img src="<?= APP_URL ?>/assets/img/nfelia-mark-email.png" alt="" width="55" height="40">
                    <span>NFELIA</span>
                </a>
                <p class="footer-about">Your own website, plus invoices, debts, stock and profit, for small businesses in Nigeria.</p>
            </div>
            <div>
                <h3>Nfelia</h3>
                <ul>
                    <li><a href="<?= APP_URL ?>/register">Start free</a></li>
                    <li><a href="<?= APP_URL ?>/login">Sign in</a></li>
                    <li><button type="button" class="linkish" data-dialog="get-app">Get the app</button></li>
                    <li><a href="#pricing">Pricing</a></li>
                    <li><button type="button" class="linkish" data-dialog="feedback">Send feedback</button></li>
                </ul>
            </div>
            <div>
                <h3>Talk to us</h3>
                <ul>
                    <li><a href="<?= e($wa_link()) ?>" target="_blank" rel="noopener">WhatsApp: +234 816 978 9106</a></li>
                    <li><a href="tel:+2348169789106">Call: +234 816 978 9106</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <span>&copy; <?= date('Y') ?> Nfelia. Powered by JBS Praxis.</span>
            <span class="legal">
                <button type="button" class="linkish" data-dialog="terms">Terms</button>
                <button type="button" class="linkish" data-dialog="privacy">Privacy</button>
            </span>
        </div>
    </div>
</footer>

<!-- Floating WhatsApp: pick a ready-made message -->
<div class="wa" data-wa>
    <div class="wa-panel" id="wa-panel" role="region" aria-labelledby="wa-title" hidden>
        <div class="wa-head">
            <strong id="wa-title">Chat with us on WhatsApp</strong>
            <p>Pick a message. WhatsApp opens with it ready to send.</p>
            <button type="button" class="wa-close" data-wa-close aria-label="Close menu" data-tip="Close menu" data-tip-below><?= nf_icon('close') ?></button>
        </div>
        <ul class="wa-options">
            <?php foreach ($wa_messages as [$label, $hint, $text]): ?>
            <li><a href="<?= e($wa_link($text)) ?>" target="_blank" rel="noopener"><strong><?= e($label) ?></strong><span><?= e($hint) ?></span></a></li>
            <?php endforeach; ?>
        </ul>
        <a class="wa-own" href="<?= e($wa_link()) ?>" target="_blank" rel="noopener">Write your own message</a>
    </div>
    <p class="wa-nudge" data-wa-nudge hidden>Questions? We reply on WhatsApp.</p>
    <button type="button" class="wa-toggle" aria-expanded="false" aria-controls="wa-panel" aria-label="Chat on WhatsApp" data-tip="Chat on WhatsApp" data-wa-toggle>
        <?= nf_icon('whatsapp') ?>
    </button>
</div>

<?php require BASE_PATH . '/views/home/_dialogs.php'; ?>

<!-- The fix behind each card. A bottom sheet on phones, a window on wider
     screens. Without JavaScript, :target shows it and its close link
     goes back to the cards. -->
<?php foreach ($cards as $id => $c): ?>
<dialog class="see-sheet" id="<?= $id ?>" aria-labelledby="<?= $id ?>-title">
    <div class="see-sheet-head">
        <div>
            <p class="see-pain"><?= e($c['pain']) ?></p>
            <h2 id="<?= $id ?>-title"><?= e($c['title']) ?></h2>
        </div>
        <a class="sheet-close" href="#see" data-see-close aria-label="Close" data-tip="Close" data-tip-below><?= nf_icon('close') ?></a>
    </div>
    <div class="see-sheet-body">
        <div class="see-screens">
            <?php foreach ($c['shots'] as $i => [$alt, $caption]): ?>
            <figure class="see-screen">
                <div class="phone"><img src="<?= $img ?>/see/<?= $c['shot'] ?>-<?= $i + 1 ?>.webp" width="540" height="1169" loading="lazy" decoding="async" alt="<?= e($alt) ?>"></div>
                <figcaption><?= e($caption) ?></figcaption>
            </figure>
            <?php endforeach; ?>
        </div>
        <div class="see-copy">
            <p class="see-lead"><?= e($c['lead']) ?></p>
            <ul class="ticks">
                <?php foreach ($c['ticks'] as $tick): ?>
                <li><?= nf_icon('check') ?><?= e($tick) ?></li>
                <?php endforeach; ?>
            </ul>
            <a class="btn btn-primary" href="<?= APP_URL ?>/register">Start free for 14 days</a>
            <p class="see-small">Screens from example businesses.</p>
        </div>
    </div>
</dialog>
<?php endforeach; ?>

<!-- Get the app: home.js shows the right way to install for this phone. -->
<dialog class="sheet sheet-small" id="get-app" aria-labelledby="get-app-title">
    <div class="sheet-head">
        <h2 id="get-app-title">Get the Nfelia app</h2>
        <button type="button" class="sheet-close" data-close aria-label="Close" data-tip="Close" data-tip-below><?= nf_icon('close') ?></button>
    </div>
    <div class="sheet-body">
        <div class="get-app-intro">
            <img src="<?= APP_URL ?>/android-chrome-192x192.png" alt="" width="64" height="64">
            <p>Nfelia goes on your home screen straight from the browser. No app store, nothing big to download.</p>
        </div>
        <div data-app="prompt" hidden>
            <button type="button" class="btn btn-primary" data-app-install>Install Nfelia</button>
        </div>
        <ol class="app-steps" data-app="ios" hidden>
            <li><span>Tap <strong>Share</strong> <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-label="the share icon"><path d="M12 3v12M7.5 7.5 12 3l4.5 4.5M6 11H5a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-8a1 1 0 0 0-1-1h-1"/></svg> in your browser. In Safari it may be under <strong>•••</strong>.</span></li>
            <li><span>Tap <strong>Add to Home Screen</strong>. Scroll down if you don’t see it.</span></li>
            <li><span>Tap <strong>Add</strong>.</span></li>
        </ol>
        <ol class="app-steps" data-app="menu" hidden>
            <li><span>Open your browser’s menu <strong>⋮</strong>.</span></li>
            <li><span>Tap <strong>Install app</strong> or <strong>Add to Home screen</strong>.</span></li>
        </ol>
        <p data-app="desktop" hidden>Open <strong>nfelia.com</strong> on your phone’s browser, then tap <strong>Get the app</strong> there.</p>
        <p data-app="installed" hidden>Nfelia is already on this device. Open it from your home screen.</p>
    </div>
</dialog>

<script src="<?= APP_URL ?>/assets/js/home.js?v=7" defer></script>
<script src="<?= APP_URL ?>/assets/js/wa.js?v=<?= (int)@filemtime(BASE_PATH . '/assets/js/wa.js') ?>" defer></script>
</body>
</html>
