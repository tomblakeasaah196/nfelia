<?php
/**
 * The Terms of Service, written once. /terms (views/site/pages/terms.php)
 * and the Terms dialog on the sign-up page (views/home/_dialogs.php) both
 * show this file, so they can never say different things.
 *
 * The words are the owner's decisions (October 2026): the laws of Nigeria
 * and the courts of Lagos State; no refund once a paid month has started,
 * but a payment taken by mistake is always refunded; records kept for 12
 * months after the last paid month (or the end of the trial), with an email
 * to the owner before anything is deleted. When they change, change the
 * version and date below and 'updated' in app/site/pages/legal.php.
 *
 * What it says about paying, the trial, pausing, the website and records
 * was checked against app/helpers/billing.php, PaystackController,
 * MoMoController, app/middleware/subscription_check.php, shop_visibility()
 * and StorefrontController, app/helpers/prices.php and records.php.
 * Prices are never typed here: each country's pricing page has them.
 *
 * In:  $legal_opts (optional) ['h' => heading tag for sections (h2),
 *      'sub' => tag for the headings inside a section (h3), 'id' => a
 *      prefix for the ids, 'meta' => true to print the date line first]
 * Out: prints the terms; returns ['toc' => [[id, title], ...],
 *      'effective' => 'Y-m-d', 'version' => '2.0']
 */
return (function (array $o): array {
    $effective = '2026-10-28';
    $version   = '2.0';
    $h   = $o['h'] ?? 'h2';
    $sub = $o['sub'] ?? 'h3';
    $pre = $o['id'] ?? '';
    $toc = [];
    $n   = 0;
    // A numbered section heading, remembered for the table of contents
    $sec = function (string $id, string $title) use (&$toc, &$n, $h, $pre): string {
        $toc[] = [$pre . $id, $title];
        $n++;
        return '<' . $h . ' id="' . e($pre . $id) . '">' . $n . '. ' . e($title) . '</' . $h . '>';
    };
    $s3   = fn(string $title): string => '<' . $sub . '>' . e($title) . '</' . $sub . '>';
    $to   = fn(string $id): string => '#' . e($pre . $id);
    $link = fn(string $key, string $text): string => site_page($key) ? '<a href="' . e(site_url($key)) . '">' . e($text) . '</a>' : e($text);

    $org     = site_org();
    $support = '<a href="mailto:' . e($org['support']) . '">' . e($org['support']) . '</a>';
    $sales   = '<a href="mailto:' . e($org['sales']) . '">' . e($org['sales']) . '</a>';
    $wa      = '<a href="' . e(site_wa()) . '" target="_blank" rel="noopener">WhatsApp</a>';
    $grace   = defined('BILLING_GRACE_DAYS') ? (int)BILLING_GRACE_DAYS : 3;
    $trial_staff = defined('TRIAL_STAFF_SEATS') ? (int)TRIAL_STAFF_SEATS : 1;
    $seats   = defined('MAX_SEATS') ? (int)MAX_SEATS : 5;
    $items   = defined('MAX_PRODUCTS') ? (int)MAX_PRODUCTS : 500;
    $book_days = defined('RECORDS_KEEP_DAYS') ? (int)RECORDS_KEEP_DAYS : 365;
    $copy_days = defined('RECORDS_RAW_DAYS') ? (int)RECORDS_RAW_DAYS : 7;

    // Each country: its trial, how it pays and where its prices are
    $countries = [];
    foreach (site_countries() as $c) {
        $p    = site_prices($c['iso']);
        $pkey = ($c['prefix'] === '' ? '' : strtolower($c['iso']) . '/') . 'pricing';
        $page = $link($pkey, $c['name'] . ' pricing page');
        if (!$p) {
            $countries[] = '<strong>' . e($c['name']) . '</strong>: opening soon. The free trial works now. The price, and how you pay it, are agreed with you before you pay anything. See the ' . $page . '.';
            continue;
        }
        $how = match ($p['pays_by']) {
            'paystack'     => 'You pay online with Paystack.',
            'mobile_money' => 'You pay by Mobile Money, then upload the receipt in Billing.',
            default        => 'How you pay is arranged with our team.',
        };
        $countries[] = '<strong>' . e($c['name']) . '</strong>: a ' . (int)$p['trial_days'] . '-day free trial. ' . $how . ' Prices are on the ' . $page . '.';
    }

    if (!empty($o['meta'])) {
        echo '<p class="meta">Effective date: <time datetime="' . $effective . '">' . date('j F Y', strtotime($effective)) . '</time> · Version ' . $version . '</p>';
    }
?>
<p class="note"><strong>The main points</strong> You pay one month at a time and nothing renews by itself. Once a paid month has started it is not refunded, but a payment taken by mistake always is. Your records belong to you, and your plain copy is free to download at any time. If you stop paying, we keep your records for 12 months and email you before anything is deleted.</p>

<?= $sec('who', 'Who we are') ?>
<p>Nfelia is a product of <?= e($org['legal']) ?>. These terms are an agreement between <?= e($org['legal']) ?> (“we”, “us”) and the business that uses Nfelia (“you”).</p>
<ul>
    <li><strong>Registered office:</strong> <?= e($org['legal']) ?>, <?= e($org['address_text']) ?>. This is our address for letters, not a shop.</li>
    <li><strong>Email:</strong> <?= $support ?> for help and anything about these terms, <?= $sales ?> before you sign up.</li>
    <li><strong>Phone and WhatsApp:</strong> <a href="tel:<?= e($org['phone']) ?>"><?= e($org['phone_text']) ?></a>.</li>
</ul>
<p>“Nfelia” means the app at nfelia.com, your website at yourname.nfelia.com, the documents and links it makes for you, and nfelia.com/verify.</p>

<?= $sec('agree', 'Agreeing to these terms') ?>
<p>When you create an account or use Nfelia, you agree to these terms. Our <?= $link('privacy', 'Privacy Policy') ?> explains how we handle personal data, and is part of the agreement.</p>
<ul>
    <li><strong>Nfelia is for business.</strong> You use it to run your business: to sell, keep records and serve your customers.</li>
    <li><strong>You must be 18 or over</strong> to create an account or to be added to a team.</li>
    <li><strong>You can agree for the business.</strong> If you sign up for a business, you confirm that you are allowed to agree to these terms for it.</li>
</ul>
<p>If you don’t agree, please don’t use Nfelia.</p>

<?= $sec('account', 'Your account and your team') ?>
<ul>
    <li><strong>The owner.</strong> The person who signs up is the owner. Only the owner pays, sees Billing, cancels, changes the business details and payment accounts, and adds or removes people. We take instructions about the account from the owner. If people disagree about who the owner is, we may ask for proof before we act.</li>
    <li><strong>Your team.</strong> You can have up to <?= $seats ?> people in all: you and <?= $seats - 1 ?> staff. Each person signs in as themselves. You are responsible for what your team does in Nfelia, and for removing people who should no longer have access.</li>
    <li><strong>Keep it safe.</strong> Keep your password secret, and use a passkey if your phone allows it. Don’t share a login: add the person to your team instead. Tell us at once if you think someone else has got into your account.</li>
    <li><strong>Alerts you can’t switch off.</strong> We tell you when your account is signed in from a new device, and when our support team looks at your account. Billing and security emails can’t be switched off.</li>
</ul>

<?= $sec('trial', 'The free trial') ?>
<p>A new business gets a free trial with everything open. No card is needed. How long it lasts depends on your country (see <a href="<?= $to('prices') ?>">prices in your country</a>). It is counted from the day you sign up, and runs to the end of its last day, Lagos time.</p>
<ul>
    <li>During the trial you can add <?= $trial_staff === 1 ? 'one staff member' : $trial_staff . ' staff members' ?> besides yourself.</li>
    <li>Your website works for anyone with the link and takes orders, but we ask search engines not to list it until you pay.</li>
    <li>We tell you before the trial ends. When it ends, Nfelia pauses until you pay for a month. There are no extra days after a trial.</li>
    <li>We may give you more trial days. That doesn’t change anything else in these terms.</li>
</ul>

<?= $sec('prices', 'Prices in your country') ?>
<p>You pay in your country’s money. What a month costs, what each extra person costs and what a Records Book costs are on your country’s pricing page, and in Billing. Billing always shows the total before you pay.</p>
<ul>
    <?php foreach ($countries as $line): ?>
    <li><?= $line ?></li>
    <?php endforeach; ?>
</ul>
<p>A Records Book is free once in every month you pay for. Another book in the same month, or a book made outside a paid month (during the trial, for example), costs the price shown for your country.</p>

<?= $sec('paying', 'How paying works') ?>
<ul>
    <li><strong>One month at a time.</strong> Each payment buys one month of Nfelia. Nothing renews by itself, so we take no money unless you pay in Billing (old card plans, below, stop after one more charge). To carry on next month, you pay again.</li>
    <li><strong>Your month’s dates.</strong> A month runs from its start date to the same date the next month (in a short month, to its last day). If you pay while a month is still running, the new month starts when that one ends, so paying early loses you nothing. Otherwise it starts on the day the payment is confirmed.</li>
    <li><strong>Who the month covers.</strong> When you pay, you tick the staff the month covers. Anyone you leave unticked is switched off when the payment goes through. Their records stay, and their open tasks move to you. Seats added in the middle of a month cost the full monthly price for each seat and last until that month ends.</li>
    <li><strong>Paying online with Paystack.</strong> You pay on Paystack’s own page, so we never see your card number. Your month opens once Paystack confirms the payment to us. A payment for less than the amount asked, or in another currency, doesn’t open a month: contact us and we will put it right.</li>
    <li><strong>Paying by Mobile Money.</strong> You send the exact amount Billing shows, then upload a photo or screenshot of the receipt (JPG, PNG, WebP or PDF, up to 5 MB). Our team checks every payment by hand, and your month opens when we approve it. If we can’t match a payment, we turn the receipt down and tell you why. You can send one receipt at a time, and each transaction reference can be used once.</li>
    <li><strong>Arranged with our team.</strong> Where neither applies, we agree with you how to pay, and open your month when the payment arrives.</li>
    <li><strong>Old card plans.</strong> If your business set up a card payment that renewed by itself before payments became month by month, it stops after its next charge, and we tell you. That charge pays for a month like any other.</li>
    <li><strong>Referral credits.</strong> When a business you invited pays for its first month, you earn a credit. One credit comes off your next month paid with Paystack. Credits have no cash value, and don’t apply to Mobile Money payments, seats or Records Books.</li>
</ul>

<?= $sec('refunds', 'Refunds') ?>
<p>The free trial is there so you can try Nfelia before you pay. So once a paid month has started, we don’t refund it, in full or in part, even if you cancel or stop using Nfelia during that month. The same goes for seats added to a month that has started, and for a Records Book once it is made. If you paid early for a month that has not started yet and want the money back, ask us before it starts.</p>
<p><strong>A payment taken by mistake is always refunded.</strong> For example:</p>
<ul>
    <li>you were charged twice: Nfelia adds a second payment as your next month, so if it was a mistake, tell us and we refund it instead;</li>
    <li>you were charged the wrong amount;</li>
    <li>money left your account but no month opened, and we can’t open it for you.</li>
</ul>
<p>Write to <?= $support ?> or message us on <?= $wa ?> with the payment reference. We check it, then refund it the way you paid: through Paystack for a card or online payment, or to the Mobile Money number you paid from.</p>

<?= $sec('month-ends', 'When a paid month ends, or you cancel') ?>
<p>We remind the owner before a paid month ends. If you don’t pay for the next month:</p>
<ul>
    <li><strong>For <?= $grace ?> days after the month ends,</strong> everything stays open, with a reminder of the date to pay by.</li>
    <li><strong>From the day after that,</strong> Nfelia pauses until you pay for a month.</li>
    <li><strong>After a free trial or a cancelled month</strong> there are no extra days: Nfelia pauses the day after it ends.</li>
</ul>
<?= $s3('While Nfelia is paused') ?>
<ul>
    <li>You and your team can’t use the app to make or change records. The owner is taken to Billing, and staff see that Nfelia is paused.</li>
    <li>Your website shows “This shop is closed for now” with your WhatsApp button, so customers can still reach you. It takes no new orders, bookings or quote requests.</li>
    <li>Reminders and the morning plan stop.</li>
    <li>These still work: Your records (to download your records), Billing, Settings, your account, Contact support, the user guide and your notifications. Links your customers already have keep working: their orders, bookings and reviews, and the invoices, receipts, quotes and delivery notes you sent them.</li>
    <li>Pay for a month and everything opens again at once.</li>
</ul>
<?= $s3('Cancelling') ?>
<p>You don’t have to cancel to stop paying: simply don’t pay for the next month. If you want it on record, the owner can tap <strong>Cancel subscription</strong> in Billing. Nfelia and your website stay open to the last day of the month you paid for, then pause the next day. Cancelling doesn’t close your account or delete your records, and the month is not refunded (see <a href="<?= $to('refunds') ?>">refunds</a>). Pay for a month whenever you want to start again.</p>

<?= $sec('price-changes', 'Price changes') ?>
<p>We may change our prices. When we do:</p>
<ul>
    <li>Billing shows the new price and the day it starts for your business, and we tell you by email. Normally that day is at least 30 days away.</li>
    <li>Any month that starts before that day keeps the old price.</li>
    <li>New prices apply to businesses that sign up after the change from the day they start.</li>
</ul>
<p>Because nothing renews by itself, you always see the price in Billing before you pay. If you don’t want to pay the new price, you can stop at any time.</p>

<?= $sec('website', 'Your website and what you publish') ?>
<p>Every business gets a website at yourname.nfelia.com.</p>
<ul>
    <li><strong>During the trial,</strong> anyone with the link can see it and order, but we ask search engines not to list it. <strong>Once you pay,</strong> search engines may list it, and you can choose to join the Nfelia shop directory. Your website shows up to <?= $items ?> of your items.</li>
    <li><strong>You can switch your website off</strong> at any time in Shop Studio.</li>
    <li><strong>Your name.</strong> Choose a name you have the right to use. Don’t pretend to be someone else. Names that look like a bank, a phone network, a government body or an official account are checked by our team before they go live, and we may turn down a name that could mislead people or uses someone else’s name or brand. If you change your name, the old one sends visitors to the new one for 90 days, and nobody else can take it meanwhile.</li>
    <li><strong>What you publish is yours, and your responsibility:</strong> your products, prices, photos, words, posts and the reviews you choose to show. Make sure it is true, legal, and yours to use.</li>
    <li><strong>Permission to show it.</strong> You keep every right to what you add to Nfelia. You allow us to store it, copy it, change its format (for example, make photos smaller) and show it, only as needed to run Nfelia for you: on your website and documents, in the shop directory if you join it, and to search engines once your website is listed.</li>
</ul>

<?= $sec('your-sales', 'Payments between you and your customers') ?>
<p>Your customers pay you directly, into the bank or Mobile Money accounts you add. Nfelia doesn’t hold your money, isn’t part of your sales, and takes no commission.</p>
<p>You are responsible for your prices, your goods and services, deliveries, refunds to your customers, your taxes, and any complaint about what you sell. The invoices, receipts and other documents Nfelia makes are your records, made from what you and your team type: check the amounts before you send them. If a customer contacts us about your business, we may pass their message on to you.</p>

<?= $sec('customer-data', 'Your customers’ personal data') ?>
<p>When you keep details about your customers, suppliers and team in Nfelia (names, phone numbers, addresses, notes, what they owe), your business decides what to collect and why. Under data protection law, your business is the controller of that data, and we process it for you.</p>
<ul>
    <li><strong>Your part:</strong> collect only what you need, for a lawful reason, and tell people how you use it. Keep it accurate. Answer your customers’ requests about their data: you can correct their records in Nfelia, and delete them for good with <strong>Purge data</strong>.</li>
    <li><strong>Our part:</strong> we use it only to run Nfelia for you, on your instructions, as our <?= $link('privacy', 'Privacy Policy') ?> explains. We keep it secure, and we never sell it or use it for our own marketing. Nfelia emails your customers only about their own orders, bookings, refunds and the documents you send them, in your shop’s name. If we learn of a breach that affects it, we tell you without delay.</li>
</ul>

<?= $sec('acceptable-use', 'What you must not do') ?>
<p>Don’t use Nfelia to:</p>
<ul>
    <li>sell or advertise anything illegal, including stolen or fake goods, or anything that needs a licence you don’t have;</li>
    <li>cheat anyone: no scams, and no invoices, receipts or records made to mislead a customer, a lender or anyone else;</li>
    <li>pretend to be another person, business, bank, phone network or government body;</li>
    <li>send messages people didn’t ask for, or use customers’ details for anything other than doing business with them;</li>
    <li>publish content that is abusive, hateful, threatening or sexually explicit, or that uses photos, words or brands you have no right to use;</li>
    <li>get into other accounts, test or break our security, overload Nfelia, copy it, or collect data from it with robots;</li>
    <li>resell Nfelia, or share your login, without our written agreement.</li>
</ul>

<?= $sec('ai', 'The AI writer') ?>
<p>Nfelia can write an item’s name, a short description and a category from a photo. It is off unless we switch it on for your business, and we may switch it off, or change how many photos it reads a week, at any time. To read a photo, Nfelia sends it to Google’s Gemini (see our <?= $link('privacy', 'Privacy Policy') ?>). What it writes can be wrong: read it and correct it before you save. What you publish stays your responsibility.</p>

<?= $sec('records', 'Your records') ?>
<p>Everything you record in Nfelia belongs to your business. We use it only to run Nfelia for you.</p>
<ul>
    <li><strong>Take them with you at any time.</strong> In <strong>Your records</strong>, the owner can download a plain copy of every record as CSV files, free, whatever your plan. Each paid month also comes with one free Records Book: all your records in one Excel workbook. Download your files when they are ready: a plain copy file is kept for <?= $copy_days ?> days, and a Records Book file for <?= $book_days ?> days.</li>
    <li><strong>While Nfelia is paused,</strong> Your records stays open, so you can still download everything.</li>
    <li><strong>Nobody else gets them from us without your yes.</strong> Someone you show a Records Book or document code to can check its key facts at nfelia.com/verify. If they ask for the full record, we send it only if the owner agrees, unless the law requires us to.</li>
</ul>
<?= $s3('If you stop paying') ?>
<p>We keep your business’s records for 12 months after the end of your last paid month, or after the end of your free trial if you never paid. During those 12 months you can download everything, and paying for a month opens everything again, as you left it.</p>
<p>Before we delete anything, we email the owner to say when it will happen, so there is time to download your records or pay for a month. After 12 months without a payment, we may delete your business’s data: its records, its website and its team’s accounts. Once that is done, it can’t be brought back, and the links you sent your customers (to invoices, receipts, quotes, delivery notes, orders and bookings) stop working.</p>
<p>Records the law requires us to keep, such as our records of your payments to Nfelia, are kept for as long as the law requires, even after the rest is deleted.</p>
<?= $s3('Deleting sooner') ?>
<p>The owner can ask us to delete the business’s data sooner, at any time: write to <?= $support ?> from the email address on the account. Data protection law gives people this right too (see our <?= $link('privacy', 'Privacy Policy') ?>). We confirm the request with you, delete the data, and tell you when it is done. Download anything you want to keep first.</p>

<?= $sec('suspension', 'Suspending or closing an account') ?>
<p>You can stop using Nfelia at any time: cancel in Billing, or simply don’t pay for the next month. To have your data deleted, see <a href="<?= $to('records') ?>">your records</a>.</p>
<p>We may suspend a business (put it on hold) or close it if:</p>
<ul>
    <li>it breaks these terms, especially <a href="<?= $to('acceptable-use') ?>">what you must not do</a>;</li>
    <li>the law, a court or an authority requires it;</li>
    <li>it puts Nfelia, other businesses or their customers at risk, for example through fraud or an attack.</li>
</ul>
<p>We tell the owner why, by email and in Nfelia, unless the law stops us or the risk is urgent. If you think we got it wrong, write to us and we will look again. While a business is suspended, Nfelia and its website are paused, and Your records stays open so the owner can download the records. If we close a business, the rules in <a href="<?= $to('records') ?>">your records</a> still apply to its data, unless the law requires something else.</p>

<?= $sec('service', 'Our service') ?>
<p>We work to keep Nfelia running and your records safe, every day. But no online service can promise never to fail, so we can’t promise that Nfelia will always be available, or free of mistakes. Sometimes we need to stop it for a short time to repair or update it.</p>
<p>We keep improving Nfelia, so features change. If we take away something important that you pay for, we tell you first.</p>
<p>Some things Nfelia works with are run by others: Paystack, Mobile Money providers, banks, WhatsApp, Google and your phone network. We are not responsible for how they work. Keep your own copy of the records that matter to you: download your plain copy from time to time.</p>

<?= $sec('liability', 'Our responsibility to you') ?>
<p>We provide Nfelia with reasonable care and skill. As far as the law allows:</p>
<ul>
    <li>We are not responsible for losses we could not reasonably have foreseen, or for indirect losses, such as lost profit, lost sales or lost chances.</li>
    <li>We are not responsible for losses caused by things outside our reasonable control, such as power or network failures, problems at the services named above, or events like floods, strikes or government action.</li>
    <li>We are not responsible for mistakes in what you or your team put into Nfelia, such as a wrong amount on an invoice, or for what happens between you and your customers.</li>
    <li>Our total responsibility to you, for all claims together, is limited to what you paid Nfelia in the 12 months before the claim.</li>
</ul>
<p>Nothing in these terms limits or excludes any responsibility that the law does not allow us to limit or exclude, such as for fraud, or for death or injury caused by our negligence.</p>

<?= $sec('changes', 'Changes to these terms') ?>
<p>We may change these terms, for example when Nfelia or the law changes. The version and date at the top show which terms apply. When a change matters to you, we tell the owner by email and in Nfelia before it applies, normally 30 days before. Changes the law or your security needs can apply sooner.</p>
<p>If you don’t agree with a change, you can stop using Nfelia and download your records. If you go on using Nfelia after a change applies, the new terms apply to you.</p>

<?= $sec('law', 'The law, and if we disagree') ?>
<p>These terms are governed by the laws of Nigeria. If there is a problem, please write to us first: most things are solved by talking. If we can’t solve it together, the courts of Lagos State, Nigeria, will decide it. If you are in Ghana, Kenya or Cameroon, you keep any rights the law there gives you that an agreement can’t take away.</p>
<?= $s3('Other things') ?>
<ul>
    <li>These terms and our <?= $link('privacy', 'Privacy Policy') ?> are the whole agreement between you and us about Nfelia.</li>
    <li>If a court decides that part of these terms can’t apply, the rest still does.</li>
    <li>If we don’t enforce a term at once, we can still enforce it later.</li>
    <li>We may pass this agreement to a company that takes over Nfelia. We will tell you if that happens, and your rights under these terms stay the same.</li>
</ul>

<?= $sec('contact', 'Contact us') ?>
<p>Questions about these terms? Write to <?= $support ?>, message us on <?= $wa ?> or call <a href="tel:<?= e($org['phone']) ?>"><?= e($org['phone_text']) ?></a>. By post: <?= e($org['legal']) ?>, <?= e($org['address_text']) ?>.</p>
<?php
    return ['toc' => $toc, 'effective' => $effective, 'version' => $version];
})($legal_opts ?? []);
