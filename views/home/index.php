<?php
/**
 * Nfelia — public landing page (route type: Home / landing).
 *
 * Written for Nigerian market traders, Instagram/WhatsApp sellers and
 * service businesses: short lines, their words, real app screens.
 * Shown at / to visitors who are not signed in.
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

$plan_price  = (int)(PLAN_BASE_PRICE / 100);           // ₦ per month
$seat_price  = (int)(PLAN_EXTRA_SEAT_PRICE / 100);     // ₦ per extra person
$per_day     = (int)(floor($plan_price / 30 / 10) * 10);
$naira       = fn(int $n) => '₦' . number_format($n);

$img      = APP_URL . '/assets/img/home';
$hero_img = is_file(BASE_PATH . '/assets/img/home/hero-1024.webp');

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en-NG">
<head>
    <meta charset="UTF-8">
    <?= errors_script_tag() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nfelia: invoices, debts and stock for Nigerian businesses</title>
    <meta name="description" content="Know who owes you, what you sold and what you made. Send proper invoices and receipts on WhatsApp. Free for 14 days, then <?= $naira($plan_price) ?> a month.">
    <link rel="canonical" href="<?= APP_URL ?>/">
    <meta name="theme-color" content="#8B004A">

    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= APP_URL ?>/">
    <meta property="og:title" content="Nfelia: the thread that holds your business together">
    <meta property="og:description" content="Know who owes you, what you sold and what you made. Free for 14 days.">
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
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/home.css?v=4">
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
        'description' => 'Invoices, receipts, debts, stock and profit tracking for small businesses in Nigeria.',
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

<main id="main">

<!-- Hero: one promise, two actions -->
<section class="hero">
    <div class="wrap">
        <div>
            <h1>The thread that holds your business together.</h1>
            <p class="hero-lead">Know who owes you, what you sold and what you made. All on your phone.</p>
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

<!-- Pains -->
<section class="section pains" aria-labelledby="pains-title">
    <div class="wrap">
        <div class="section-head" data-reveal>
            <h2 id="pains-title">Sound familiar?</h2>
            <p>Running a business from an exercise book and WhatsApp chats is hard work.</p>
        </div>
        <div class="pains-grid">
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
            <ul class="pain-list" data-reveal-group>
                <li>
                    <span class="pain-icon"><?= nf_icon('naira') ?></span>
                    <div><h3>“I go pay you next week.”</h3><p>Three months later, you are still waiting, and too shy to keep asking.</p></div>
                </li>
                <li>
                    <span class="pain-icon"><?= nf_icon('chart') ?></span>
                    <div><h3>Busy all month, but where is the profit?</h3><p>Shop money and house money mix, and month end shows nothing.</p></div>
                </li>
                <li>
                    <span class="pain-icon"><?= nf_icon('box') ?></span>
                    <div><h3>Stock that doesn't add up</h3><p>Goods go missing and nobody knows how many were sold.</p></div>
                </li>
                <li>
                    <span class="pain-icon"><?= nf_icon('chat') ?></span>
                    <div><h3>“Who paid? Send the screenshot again.”</h3><p>Orders, payments and balances buried in WhatsApp and Instagram chats.</p></div>
                </li>
                <li>
                    <span class="pain-icon"><?= nf_icon('invoice') ?></span>
                    <div><h3>Big client wants a proper invoice</h3><p>You send a text message, and the job goes to someone who looks bigger.</p></div>
                </li>
                <li>
                    <span class="pain-icon"><?= nf_icon('truck') ?></span>
                    <div><h3>Delivery wahala</h3><p>The rider says delivered. The customer says no. No proof either way.</p></div>
                </li>
            </ul>
        </div>
    </div>
</section>

<!-- Fixes: the real app screens -->
<section class="section fixes" aria-labelledby="fixes-title">
    <div class="wrap">
        <div class="section-head center" data-reveal>
            <h2 id="fixes-title">Nfelia puts it all in one place</h2>
            <p>Your sales, debts, stock and receipts, connected. Here is what it looks like.</p>
        </div>

        <ol class="fix-list">
            <li class="fix" data-reveal>
                <div class="fix-media">
                    <div class="phone"><img src="<?= $img ?>/debt-view-phone.webp" width="780" height="1688" loading="lazy" alt="A customer's unpaid balance: 5 days overdue, with a green Send reminder button for WhatsApp"></div>
                </div>
                <div class="fix-copy">
                    <h3>Get your money back</h3>
                    <p>See who owes you, how much, and who is late. Send a polite WhatsApp reminder in one tap.</p>
                    <ul class="ticks">
                        <li><?= nf_icon('check') ?>Balance updates as soon as you record a payment</li>
                        <li><?= nf_icon('check') ?>The message says the balance and how many days late</li>
                    </ul>
                </div>
            </li>
            <li class="fix" data-reveal>
                <div class="fix-media">
                    <div class="phone"><img src="<?= $img ?>/invoice-phone.webp" width="780" height="1688" loading="lazy" alt="An invoice from Kemi Events as the client sees it, with deposit paid and balance due"></div>
                </div>
                <div class="fix-copy">
                    <h3>Look like a big company</h3>
                    <p>Invoices and receipts with your logo and colours. Your customer opens them from a WhatsApp link, on any phone.</p>
                    <ul class="ticks">
                        <li><?= nf_icon('check') ?>Deposit paid and balance due, clearly shown</li>
                        <li><?= nf_icon('check') ?>PAID stamp when they finish paying</li>
                    </ul>
                </div>
            </li>
            <li class="fix" data-reveal>
                <div class="fix-media">
                    <div class="phone"><img src="<?= $img ?>/shop-phone.webp" width="780" height="1688" loading="lazy" alt="Ivie's online shop: wigs and bundles with prices, ready to add to cart or order on WhatsApp"></div>
                </div>
                <div class="fix-copy">
                    <h3>Turn your Instagram into a shop</h3>
                    <p>One link for your bio and your WhatsApp status. Customers see your products and prices, then order from the cart or chat you on WhatsApp.</p>
                    <ul class="ticks">
                        <li><?= nf_icon('check') ?>Orders land in Nfelia, not lost in your DMs</li>
                        <li><?= nf_icon('check') ?>Customers pay into your own bank account</li>
                    </ul>
                </div>
            </li>
            <li class="fix" data-reveal>
                <div class="fix-media">
                    <div class="phone"><img src="<?= $img ?>/tasks-phone.webp" width="780" height="1688" loading="lazy" alt="Ivie's tasks: an order to deliver in 2 days, a payment 5 days late, a delivery 1 day late, a delivery due today and a 2pm wig install"></div>
                </div>
                <div class="fix-copy">
                    <h3>Never miss a delivery date</h3>
                    <p>Deliveries, fittings and money due, on one list every morning. A countdown shows what is due and what is late, so no customer is left waiting.</p>
                    <ul class="ticks">
                        <li><?= nf_icon('check') ?>Reminders on your phone and by email</li>
                        <li><?= nf_icon('check') ?>Ready-made plans for sewing jobs and events</li>
                    </ul>
                </div>
            </li>
            <li class="fix" data-reveal>
                <div class="fix-media">
                    <div class="laptop">
                        <div class="laptop-screen"><img src="<?= $img ?>/dashboard-laptop.webp" width="1440" height="900" loading="lazy" alt="Dashboard: today's sales, money owed to you and products running low"></div>
                        <div class="laptop-base"></div>
                    </div>
                </div>
                <div class="fix-copy">
                    <h3>Know your business at a glance</h3>
                    <p>Today's sales, money owed to you and stock running low. One screen, every morning.</p>
                    <ul class="ticks">
                        <li><?= nf_icon('check') ?>Stock goes down by itself when you sell</li>
                        <li><?= nf_icon('check') ?>A warning before a product runs out</li>
                    </ul>
                </div>
            </li>
            <li class="fix" data-reveal>
                <div class="fix-media">
                    <div class="laptop">
                        <div class="laptop-screen"><img src="<?= $img ?>/profit-laptop.webp" width="1440" height="900" loading="lazy" alt="Profit report: money in, money out and profit for the month"></div>
                        <div class="laptop-base"></div>
                    </div>
                </div>
                <div class="fix-copy">
                    <h3>Know your real profit</h3>
                    <p>Write down what you spend, and see money in against money out. No big grammar.</p>
                    <ul class="ticks">
                        <li><?= nf_icon('check') ?>Profit for any month, in plain words</li>
                        <li><?= nf_icon('check') ?>Download it as a PDF for your bank</li>
                    </ul>
                </div>
            </li>
        </ol>

        <ul class="extras" data-reveal-group>
            <li><?= nf_icon('quote') ?><div><strong>Quotes in seconds</strong><span>Turn a quote into an invoice in one tap.</span></div></li>
            <li><?= nf_icon('pen') ?><div><strong>Proof of delivery</strong><span>Your customer signs on the phone when goods arrive.</span></div></li>
            <li><?= nf_icon('receipt') ?><div><strong>Receipts</strong><span>Send a receipt the moment you are paid.</span></div></li>
            <li><?= nf_icon('globe') ?><div><strong>An app, no app store</strong><span>Add it to your home screen straight from the browser.</span></div></li>
            <li><?= nf_icon('users') ?><div><strong>Staff accounts</strong><span>Give each staff member their own login.</span></div></li>
            <li><?= nf_icon('bell') ?><div><strong>Low-stock alerts</strong><span>Know what to restock before customers ask.</span></div></li>
            <li><?= nf_icon('calendar') ?><div><strong>Appointments</strong><span>Book fittings and visits, and remind customers on WhatsApp.</span></div></li>
            <li><?= nf_icon('check-circle') ?><div><strong>Tasks for your staff</strong><span>Give a job to anyone on your team. It shows on their own list.</span></div></li>
        </ul>
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
            <li><strong>Salons and beauty</strong><span>A receipt for every client, and stock of the products you sell.</span></li>
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
                <li><div><h3>Sign up free</h3><p>Your name, your business name and an email. About two minutes.</p></div></li>
                <li><div><h3>Add your products and customers</h3><p>All at once, or one by one as you sell.</p></div></li>
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
                    <li><?= nf_icon('check') ?>Unlimited invoices, quotes and receipts</li>
                    <li><?= nf_icon('check') ?>Debt tracker with WhatsApp reminders</li>
                    <li><?= nf_icon('check') ?>Stock tracking for up to <?= number_format(MAX_PRODUCTS) ?> products</li>
                    <li><?= nf_icon('check') ?>Monthly profit report</li>
                    <li><?= nf_icon('check') ?>Tasks and calendar with reminders</li>
                    <li><?= nf_icon('check') ?>Delivery notes your customer signs</li>
                    <li><?= nf_icon('check') ?>Your own online shop link</li>
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
                <summary>Do I need a laptop or a lot of data?</summary>
                <p>No laptop needed. Nfelia opens in the browser on any smartphone, and you can add it to your home screen so it opens like an app, with no app store download. It needs a little data, like WhatsApp does, and pages you have already opened still open when the network drops. Use a laptop too if you like; your records are the same on both.</p>
            </details>
            <details>
                <summary>Will it remind me?</summary>
                <p>Yes. On mornings with something planned, Nfelia emails you the day at 7am: deliveries, appointments and money due. Any task can also remind you at the time you choose, by email and on your phone. On iPhone, add Nfelia to your Home Screen first (tap Share, then Add to Home Screen).</p>
            </details>
            <details>
                <summary>What happens after the 14 days?</summary>
                <p>You decide. Pay <?= $naira($plan_price) ?> a month to keep creating invoices and receipts. If you stop, nothing is deleted: your records stay safe.</p>
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
                <p>Your records belong to you. We never sell your data or contact your customers. Download your invoices, receipts and profit report as PDF, and your product list as a spreadsheet, any time.</p>
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
                <p class="footer-about">Invoices, debts, stock and profit for small businesses in Nigeria.</p>
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

<script src="<?= APP_URL ?>/assets/js/home.js?v=5" defer></script>
</body>
</html>
