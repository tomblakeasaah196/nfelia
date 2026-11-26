<?php
/**
 * The Privacy Policy, written once. /privacy (views/site/pages/privacy.php)
 * and the Privacy dialog on the sign-up page (views/home/_dialogs.php) both
 * show this file, so they can never say different things.
 *
 * Every claim was checked against the code in October 2026: what is stored
 * (the tables and columns behind each form), the cookies (config/app.php,
 * signin.php, set_country.php, shell.js) and browser storage, the outside
 * services (Cloudflare, Paystack, Gemini in product_ai.php, Geoapify in
 * maps.php, Google Fonts, YouTube, web push, Have I Been Pwned, IndexNow),
 * what is public (shops, /doc links, /verify) and what is deleted when
 * (cron/planner.php, records.php, errors.php, wipe.php). If the code
 * changes what it keeps or who it sends data to, change this file, its
 * version and date below, and 'updated' in app/site/pages/legal.php.
 *
 * The laws and regulators named under Complaints were checked with a web
 * search on 28 October 2026.
 *
 * In:  $legal_opts (optional) ['h' => heading tag for sections (h2),
 *      'sub' => tag for the headings inside a section (h3), 'id' => a
 *      prefix for the ids, 'meta' => true to print the date line first]
 * Out: prints the policy; returns ['toc' => [[id, title], ...],
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
    $wa      = '<a href="' . e(site_wa()) . '" target="_blank" rel="noopener">WhatsApp</a>';
    $book_days = defined('RECORDS_KEEP_DAYS') ? (int)RECORDS_KEEP_DAYS : 365;
    $copy_days = defined('RECORDS_RAW_DAYS') ? (int)RECORDS_RAW_DAYS : 7;

    if (!empty($o['meta'])) {
        echo '<p class="meta">Effective date: <time datetime="' . $effective . '">' . date('j F Y', strtotime($effective)) . '</time> · Version ' . $version . '</p>';
    }
?>
<?= $sec('who', 'Who we are') ?>
<p>Nfelia is a product of <?= e($org['legal']) ?>, <?= e($org['address_text']) ?> (“we”, “us”). This policy explains what personal data Nfelia collects, why, who we share it with, how long we keep it, and your rights. It covers nfelia.com, the Nfelia app, the shop websites at name.nfelia.com and nfelia.com/verify, in Nigeria, Ghana, Kenya and Cameroon.</p>
<ul>
    <li><strong>For your account, and for people who contact us,</strong> we decide how the data is used: we are the controller.</li>
    <li><strong>For the details a business keeps about its own customers, suppliers and team,</strong> the business decides, and we process the data for it. See <a href="<?= $to('businesses') ?>">your customers’ data</a>.</li>
</ul>
<p>Questions, or want to use your rights? Write to <?= $support ?>, or message us on <?= $wa ?> at <?= e($org['phone_text']) ?>.</p>

<?= $sec('summary', 'The short version') ?>
<ul>
    <li>We collect what we need to run Nfelia for your business, and what you choose to record about your customers.</li>
    <li>Your records belong to your business. We never sell your data, and we never market to your customers.</li>
    <li>A few service providers help us run Nfelia, such as Cloudflare, Paystack and Google. They get only what they need for that.</li>
    <li>Some things are public because you make them so: your shop website, and the links to documents you send.</li>
    <li>We use only the cookies Nfelia needs to work and to remember your settings. No advertising or tracking cookies.</li>
    <li>If you stop paying, we keep your records for 12 months, and email you before anything is deleted.</li>
    <li>You can see, download, correct and delete your data. Write to <?= $support ?>.</li>
</ul>

<?= $sec('collect', 'What we collect') ?>
<?= $s3('When you run a business on Nfelia') ?>
<ul>
    <li><strong>Signing up:</strong> your full name, your business name, your email address, a password, and a referral code if you have one.</li>
    <li><strong>Your profile:</strong> your name, email address and profile photo, and the signature you draw if you sign documents.</li>
    <li><strong>Signing in:</strong> your password, stored scrambled (never as you typed it); for a passkey, only its public key and name (your fingerprint, face or PIN never leaves your phone); when you last signed in; the devices you sign in from (a label such as “Chrome on Android”, and when it was first and last seen); and, if you tick “Keep me signed in”, the IP address and browser type of that sign-in.</li>
    <li><strong>Your business:</strong> its name, logo, tagline, colours, phone, WhatsApp, email, address, city and map pin, social links, category, country and money, your shop’s name, and the bank or Mobile Money accounts you add for customers to pay into.</li>
    <li><strong>Your team:</strong> each person’s name, email address and role. When you remove someone, their account is switched off, and their name stays on the records they made.</li>
    <li><strong>Paying us:</strong> what you paid and when; with Paystack, Paystack’s confirmation of each payment and the codes it gives us to recognise you (never your full card number); with Mobile Money, the receipt you upload and its transaction reference.</li>
    <li><strong>Using Nfelia:</strong> your notifications and alert settings; for phone alerts, the address your browser gives us to deliver them; your referrals (the business that invited you, and the emails you type when you invite someone); messages and screenshots you send us as feedback or support; and a log of each photo read by the AI writer (when, by whom, and whether it worked, but not the photo).</li>
</ul>
<?= $s3('The records you keep about others') ?>
<p>Your business decides what to record about its customers, suppliers and team: names, phone numbers, emails, addresses, notes (such as measurements), orders, quotes, invoices, receipts, payments, debts, deliveries, bookings, refunds, expenses and tasks. We hold these for you. See <a href="<?= $to('businesses') ?>">your customers’ data</a>.</p>
<?= $s3('When you buy from or book with a shop on Nfelia') ?>
<ul>
    <li><strong>An order:</strong> your name and phone number, and if you give them, your email address, delivery address, a note, how you paid and a screenshot of your payment.</li>
    <li><strong>A booking:</strong> your name and phone number, and if you give them, your email address, a note, the dates, and proof of your deposit.</li>
    <li><strong>A review:</strong> your rating and comment, the IP address and browser type you sent it from (so we can tell when a shop reviews itself), and when you sent it.</li>
    <li><strong>Signing for a delivery, or accepting a quote:</strong> the name you type, your drawn signature if the business asks for one, the time, and the IP address you signed from.</li>
    <li><strong>A refund:</strong> the name, bank and account number you give for the refund to be paid into, and the business’s proof of payment.</li>
</ul>
<p>The shop you buy from sees these details and decides how to use them. We use your email address only to send you updates about your own order, booking or refund, in the shop’s name.</p>
<?= $s3('When you contact us, book a call or ask for a record') ?>
<ul>
    <li><strong>The “Send feedback” form:</strong> your business name, your message, the phone or WhatsApp number you add, and your IP address. Our sales team may reply on that number.</li>
    <li><strong>Booking a call at nfelia.com/book:</strong> your name, email address, phone, business name, your note, the time you chose and your IP address. We may record and transcribe the call, and the confirmation email tells you so.</li>
    <li><strong>Asking for a record at nfelia.com/verify:</strong> your name, organisation, email address, phone, role, the reason you ask, your IP address and browser type.</li>
    <li><strong>Meeting us elsewhere:</strong> if you give us your details at an event, on social media or in person, we keep them as a sales contact.</li>
</ul>
<?= $s3('When you visit nfelia.com or a shop website') ?>
<ul>
    <li><strong>Error reports:</strong> when a page breaks in your browser, it sends us a short report: the error, the page address (with long codes and extra details removed), and your browser type, plus your account if you are signed in. We mask email addresses and Nigerian phone numbers in these reports, and we don’t keep your IP address with them.</li>
    <li><strong>Security reports:</strong> your browser may tell us when a page tried to load something our security rules don’t allow.</li>
    <li><strong>Server logs:</strong> like every website, our server and Cloudflare see your IP address, the pages you ask for, the time and your browser type.</li>
</ul>

<?= $sec('use', 'How we use it') ?>
<ul>
    <li><strong>To run Nfelia for you:</strong> your account, your records and documents, your website, orders and bookings, your Records Book, reminders and alerts.</li>
    <li><strong>To take payment</strong> for Nfelia and Records Books, and to keep our accounts.</li>
    <li><strong>To keep Nfelia and your account safe:</strong> sign-in checks, limits on wrong passwords, alerts about new devices, and spotting fraud or attacks.</li>
    <li><strong>To help you:</strong> to answer your messages, set you up and fix problems. Our team may contact you if you sign up but don’t finish setting up, or when your trial is about to end.</li>
    <li><strong>To fix and improve Nfelia,</strong> using error reports and counts of how much each part is used.</li>
    <li><strong>To tell business owners about Nfelia</strong> (news, tips and offers), with an unsubscribe link in every such email.</li>
    <li><strong>To obey the law,</strong> such as keeping payment records and answering lawful requests.</li>
</ul>
<p>We never sell your data, or your customers’ data. We never use your customers’ details to market anything, ours or anyone else’s. We don’t make decisions about you by computer alone that have legal or similarly serious effects on you.</p>

<?= $sec('bases', 'Our legal reasons') ?>
<p>Data protection law asks us to have a lawful reason for each use. Ours are:</p>
<ul>
    <li><strong>Our agreement with you:</strong> to give you the Nfelia you signed up for, under our <?= $link('terms', 'Terms of Service') ?>.</li>
    <li><strong>Legitimate interests:</strong> to keep Nfelia secure, fix and improve it, answer people who contact us, and tell business owners about Nfelia. We weigh these against your rights, and you can object at any time.</li>
    <li><strong>Your consent, where we ask for it:</strong> for example, phone alerts, or listing your shop in the Nfelia shop directory. You can withdraw it at any time.</li>
    <li><strong>The law:</strong> to keep records the law requires, such as payment records, and to answer lawful requests from authorities.</li>
</ul>
<p>For the records a business keeps about its customers, the business has its own lawful reasons, and we process them on its instructions.</p>

<?= $sec('businesses', 'Your customers’ data') ?>
<p>When your business keeps details about its customers, suppliers and team in Nfelia, your business is the controller of that data, and we process it for you. We:</p>
<ul>
    <li>use it only to run Nfelia for you, on your instructions;</li>
    <li>never sell it, and never contact your customers for ourselves. Nfelia emails them only about their own orders, bookings, refunds and the documents you send them, in your shop’s name;</li>
    <li>let only the people you add to your team see it, plus our support team when you need help (see <a href="<?= $to('security') ?>">how we protect it</a>);</li>
    <li>keep it secure, and tell you without delay if a breach affects it;</li>
    <li>help you answer your customers’ requests: you can correct their records in Nfelia, download them in your plain copy, and delete them for good with <strong>Purge data</strong>.</li>
</ul>
<p><strong>If you are a customer of a business on Nfelia,</strong> please ask that business first about your data: it decides how your data is used. If you write to us, we will pass your request on and help the business answer it.</p>

<?= $sec('emails', 'Emails and alerts') ?>
<ul>
    <li><strong>About your account:</strong> sign-in links, new-device and security alerts, billing reminders, and notices when our support team looks at your account. You can’t switch these off, because they protect you and your money.</li>
    <li><strong>About your business:</strong> new orders, bookings, payments, reminders and the morning plan. You choose which you get, and where, in your notification settings.</li>
    <li><strong>About Nfelia:</strong> news, tips and offers for business owners. Every one has an unsubscribe link, and we stop when you use it.</li>
    <li><strong>To your customers:</strong> only about their own orders, bookings, refunds and documents, in your shop’s name. Customers are never sent our news or offers.</li>
</ul>
<p>If you turn on phone alerts, your browser’s alert service delivers them (Google, Mozilla, Apple or Microsoft, depending on your browser). Each alert is encrypted, so that service can’t read it. With “hide amounts” on, your phone shows only a short title, such as “Payment received”.</p>

<?= $sec('sharing', 'Who we share it with') ?>
<p>We share personal data only with the service providers below, and only what each needs to do its job for us:</p>
<ul>
    <li><strong>Cloudflare</strong> carries every visit to nfelia.com and the shop websites. It protects Nfelia from attacks, keeps copies of public pages for a few minutes so they load fast, and serves some of the scripts on our pages (from cdnjs.cloudflare.com). It sees IP addresses and the pages people ask for. It may also add a small script that counts visits and measures how fast pages load, and it may keep our backup copies.</li>
    <li><strong>Newton Corp</strong> runs the server where Nfelia, its database, its files and its email live, in an OVH data centre.</li>
    <li><strong>Paystack</strong> takes card and online payments for Nfelia and Records Books. We send it your email address, the amount, the currency and a reference. You type your card details on Paystack’s own page, so we never see them. Paystack tells us when a payment is confirmed.</li>
    <li><strong>Google</strong>, in these ways:
        <ul>
            <li><strong>Gemini (the AI writer),</strong> only for businesses we have switched it on for, when someone adds an item from a photo. We send the photo, re-saved without its location or camera details, and a few lines about the shop: its category, its look, what it sells, its country, its tagline and the categories its items already have. We send no customer data.</li>
            <li><strong>Google Fonts:</strong> our public pages, shop websites and document pages load their fonts from Google, so Google sees your IP address.</li>
            <li><strong>YouTube:</strong> where a page has a video, its picture comes from YouTube. The video itself loads from youtube-nocookie.com only when you tap play.</li>
            <li><strong>Google Calendar and Google Meet,</strong> for calls booked at nfelia.com/book: you are invited by email.</li>
            <li><strong>Google Maps,</strong> only when you tap “Get directions”, or paste a Google Maps link in Shop Studio for us to find your pin.</li>
        </ul>
    </li>
    <li><strong>Geoapify</strong> finds your address on the map in Shop Studio: the address you type is sent to it, and the map you move loads from it. The map picture on your website is made once and then served by us, so your shoppers don’t contact Geoapify.</li>
    <li><strong>Have I Been Pwned</strong> tells us whether a new password has appeared in a known leak. We send only the first 5 characters of a scrambled form of it, never the password or your email.</li>
    <li><strong>IndexNow</strong> tells search engines when our pages and paid shop websites change. It gets only their web addresses.</li>
</ul>
<p>When you pay us by Mobile Money, the payment goes through your own Mobile Money provider. WhatsApp buttons in Nfelia open WhatsApp with a message ready for you to send yourself: we send nothing to WhatsApp.</p>
<p>We may also share data when the law requires it (for example, a court order), to protect people from harm or fraud, or to defend our legal rights. If another company takes over Nfelia, your data would pass to it under this policy, and we would tell you first.</p>

<?= $sec('transfers', 'Data outside your country') ?>
<p>Your data may be stored or handled outside the country you live in: on our server, and by Cloudflare, Google and our other providers, which run systems in many countries. When that happens, we use providers that protect personal data, and we rely on the safeguards the data protection law of your country allows for such transfers.</p>

<?= $sec('public', 'What is public') ?>
<p>Some things are public because that is their job:</p>
<ul>
    <li><strong>Your shop website</strong> shows what you publish: your name, logo, address and map, phone and WhatsApp, products and prices, posts, and the reviews you choose to show (with the reviewer’s first name and last initial, like “Ada O.”). At checkout it shows the accounts customers pay into. Once you pay, search engines may list it. It appears in the Nfelia shop directory only if you choose to join.</li>
    <li><strong>Document links:</strong> each quote, invoice, receipt, delivery note and sign-off page you send has its own link, made of a long random code. Anyone who has the link can open it and see what the document says, including your customer’s name, phone, email and address, and the account to pay into. The links don’t expire, so share them only with the people they are for. We ask search engines not to list them. A link stops working if its record is deleted.</li>
    <li><strong>nfelia.com/verify:</strong> anyone with a document’s or Records Book’s code can check it is genuine. It shows only key facts: the business, the type of document, its number and date, the customer’s first name and last initial, the amount, whether it is paid, and who signed it. It never shows a phone number, email or address. A full record goes to the person asking only if the business owner says yes.</li>
    <li><strong>Pictures:</strong> your logo, product and post photos, profile photo, and screenshots attached to feedback are stored as image files that anyone with their exact web address can open.</li>
</ul>

<?= $sec('cookies', 'Cookies and browser storage') ?>
<p>Nfelia uses only the cookies it needs to work and to remember your settings:</p>
<ul>
    <li><strong>__Host-nfelia_session</strong> keeps you signed in while you use Nfelia, and protects forms from misuse. It is set on the app, the sign-in pages, the home page, document links, nfelia.com/book and nfelia.com/verify, and ends when you close your browser.</li>
    <li><strong>__Host-nfelia_remember</strong> keeps you signed in for 30 days, if you tick “Keep me signed in”.</li>
    <li><strong>__Host-nfelia_device</strong> recognises your browser for a year, so we can alert you when someone signs in from a new device.</li>
    <li><strong>nfelia_country</strong> remembers the country you chose in the app, for 30 days.</li>
    <li><strong>nf_ui</strong> remembers the text size and background you chose on this device, for a year.</li>
</ul>
<p>Nfelia sets no cookies on shop websites, on the shop directory, or on nfelia.com’s information pages (features, prices, help, the blog and these legal pages).</p>
<p><strong>Browser storage.</strong> Your browser also keeps some small things on your device, which we don’t receive:</p>
<ul>
    <li><strong>In the app:</strong> your colours, whether you put off the install or alerts prompts, when Nfelia should lock, whether this device has a passkey, which alerts you have seen, your last sale settings, and drafts you haven’t saved yet (an order or a task). If you turn on phone alerts, it keeps what this phone needs to show them.</li>
    <li><strong>For offline use:</strong> Nfelia’s own files, and up to 60 app pages you opened, so they still open when the network drops. Those pages are cleared when you sign out.</li>
    <li><strong>On a shop website:</strong> your bag, links to your last 5 orders or bookings there, and the prices you last saw, so the shop can show you what changed.</li>
</ul>
<p><strong>No advertising or tracking.</strong> Nfelia has no advertising or analytics cookies: no Google Analytics, no Facebook (Meta) or TikTok pixels, and shops can’t add tracking code. Cloudflare’s visit counter, if it runs, works without cookies, Cloudflare says. Cloudflare may set its own security cookie if it needs to check that a visitor is a person.</p>

<?= $sec('keep', 'How long we keep it') ?>
<ul>
    <li><strong>Your account and your business’s records:</strong> while you pay for Nfelia or are on your trial. If you stop paying, we keep them for 12 months after the end of your last paid month (or of your trial, if you never paid), and email the owner before anything is deleted. After that we may delete them. The owner can ask us to delete them sooner, at any time.</li>
    <li><strong>Records you delete in the app:</strong> deleting a customer, product, supplier, expense, delivery or task hides it from your lists, but keeps it with your records, so the documents that mention it still make sense. <strong>Purge data</strong> removes records for good. Nfelia keeps a log of each purge, with a copy of what was removed and who removed it, so the owner can see it; the log stays with your account.</li>
    <li><strong>Our records of your payments to Nfelia,</strong> including Mobile Money receipts: as long as the law requires, even after the rest is deleted.</li>
    <li><strong>Records Books and plain copies:</strong> a Records Book file for <?= $book_days ?> days, a plain copy file for <?= $copy_days ?> days, and a file shared after a record request for 7 days. Then they are deleted. Unconfirmed record requests are deleted after a day.</li>
    <li><strong>Notifications:</strong> read ones are deleted after 6 months, all of them after 12 months. Emails waiting to be sent are deleted a month after they go.</li>
    <li><strong>Signing in:</strong> “Keep me signed in” lasts 30 days. Limits on wrong passwords are cleared after a day. Emailed links stop working after 15 minutes (sign-in), 60 minutes (password reset) or 48 hours (confirming your email). Your list of devices stays while your account exists.</li>
    <li><strong>Error reports:</strong> 30 days.</li>
    <li><strong>A “fresh start”:</strong> if you ask us to clear part of your records, we keep a copy for 30 days, in case you need it back, then delete it.</li>
    <li><strong>Feedback, support messages, sales contacts and call bookings:</strong> we keep them while they help us answer and serve you, and delete them when you ask, unless the law needs us to keep them.</li>
    <li><strong>Backups:</strong> where we keep backup copies, each is replaced within 90 days, so deleted data leaves them within 90 days.</li>
</ul>

<?= $sec('security', 'How we protect it') ?>
<ul>
    <li>Every page is sent over an encrypted connection (HTTPS).</li>
    <li>Passwords are stored scrambled, never as typed. New passwords are checked against known leaks.</li>
    <li>You can sign in with a passkey, so your fingerprint, face or PIN does the work and never leaves your phone.</li>
    <li>After a while unused, Nfelia locks and asks for your passkey or password again. Changing your password signs you out everywhere else.</li>
    <li>After too many wrong passwords, sign-in waits for a while, and we alert the person whose account it is. A sign-in from a new device sends you an alert too.</li>
    <li>Each business sees only its own records, and only the owner can take them out of Nfelia.</li>
    <li>Signatures, payment proofs, Mobile Money receipts and Records Books are kept where the web can’t reach them, and shown only to the people allowed to see them.</li>
</ul>
<?= $s3('Our team’s access') ?>
<p>Our team works in Nfelia Ops, our own tool. It shows your account and team details, your payments and plan, and how much you use each part of Nfelia, but not your customers or records. Each person on our team sees only what their role needs, and every action in Nfelia Ops is logged with who did it.</p>
<p>To help you, our support team can view your Nfelia as you see it, for up to 30 minutes, without being able to change anything. They must give a reason, and we email you who looked and why. If you ask, we can make your Records Book for you; we tell you when we do.</p>
<p>No system is perfectly secure. If a breach puts your data at risk, we tell you, and the data protection authority, as the law requires.</p>

<?= $sec('rights', 'Your rights') ?>
<p>Wherever you live, you can ask us to:</p>
<ul>
    <li><strong>tell you</strong> what data we hold about you, and give you a copy;</li>
    <li><strong>correct</strong> data that is wrong or incomplete (you can change most of it yourself in Settings);</li>
    <li><strong>delete</strong> your data (we will tell you if the law needs us to keep any of it);</li>
    <li><strong>stop or limit</strong> how we use it, or object to it, including for news and offers;</li>
    <li><strong>give you your data</strong> in a form other software can read: the plain copy in Your records does this for a business’s records;</li>
    <li><strong>withdraw your consent,</strong> where we asked for it.</li>
</ul>
<p>To use a right, write to <?= $support ?> from the email address on your account, or message us on <?= $wa ?>. We may ask you to confirm who you are. We don’t charge for this, and we answer as soon as we can, within the time the law where you live allows. If you are a customer of a business on Nfelia, ask that business first (see <a href="<?= $to('businesses') ?>">your customers’ data</a>).</p>

<?= $sec('complaints', 'Complaints') ?>
<p>If you are unhappy with how we handle your data, please tell us first, and we will try to put it right. You can also complain to the data protection authority where you live:</p>
<ul>
    <li><strong>Nigeria:</strong> the Nigeria Data Protection Commission (NDPC), under the Nigeria Data Protection Act 2023.</li>
    <li><strong>Ghana:</strong> the Data Protection Commission, under the Data Protection Act, 2012 (Act 843).</li>
    <li><strong>Kenya:</strong> the Office of the Data Protection Commissioner (ODPC), under the Data Protection Act, 2019.</li>
    <li><strong>Cameroon:</strong> the Personal Data Protection Authority set up by Law No. 2024/017 of 23 December 2024 on the protection of personal data.</li>
</ul>

<?= $sec('children', 'Children') ?>
<p>Nfelia is for businesses. Accounts are for people aged 18 or over, and we don’t knowingly collect data about children for ourselves. If you think a child has given us their details, tell us and we will delete them.</p>

<?= $sec('changes', 'Changes to this policy') ?>
<p>When we change this policy, we change the version and date at the top. When a change matters to you, we tell account owners by email and in Nfelia before it applies.</p>

<?= $sec('contact', 'Contact us') ?>
<p>Write to <?= $support ?>, message us on <?= $wa ?> or call <a href="tel:<?= e($org['phone']) ?>"><?= e($org['phone_text']) ?></a>. By post: <?= e($org['legal']) ?>, <?= e($org['address_text']) ?>.</p>
<?php
    return ['toc' => $toc, 'effective' => $effective, 'version' => $version];
})($legal_opts ?? []);
