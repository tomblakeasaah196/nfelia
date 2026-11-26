<?php
/**
 * /faq: every question people ask about Nfelia, by topic. Each answer was
 * checked against the app (controllers, helpers and views) on the day in
 * the page list; change one when the app changes. All the questions
 * together are the FAQPage's mainEntity (site_faq gathers them).
 *
 * Prices come from site_prices('NG') and the limits from config/app.php
 * and app/helpers/billing.php, never typed.
 */
require_once BASE_PATH . '/views/home/_icons.php';   // the building blocks draw icons before the layout loads them
$org    = site_org();
$ng     = site_prices('NG');
$days   = (int)($ng['trial_days'] ?? TRIAL_DAYS);
$link   = fn(string $key, string $text) => site_page($key) ? '<a href="' . e(site_url($key)) . '">' . e($text) . '</a>' : e($text);
$and    = function (array $items): string {
    $last = array_pop($items);
    return $items ? implode(', ', $items) . ' and ' . $last : (string)$last;
};
$wa     = fn(string $text, string $label) => '<a href="' . e(site_wa($text)) . '" target="_blank" rel="noopener">' . e($label) . '</a>';

// Each country's pricing page, and where sign-ups are open today
$price_links = $open = $closed = [];
foreach (site_countries() as $c) {
    $key = ($c['prefix'] ? strtolower($c['iso']) . '/' : '') . 'pricing';
    $price_links[] = $link($key, $c['name']);
    $home = $c['prefix'] === '' ? 'home' : strtolower($c['iso']);
    $name = $link($home, $c['name']);
    if (site_prices($c['iso']) !== null) {
        $open[] = $name;
    } else {
        $closed[] = $name;
    }
}
$staff_max = MAX_SEATS;
$trial_staff = TRIAL_STAFF_SEATS . ' staff member' . (TRIAL_STAFF_SEATS === 1 ? '' : 's');

if ($ng) {
    $m = $ng['money'];
    $price_answer = 'In Nigeria, Nfelia is ' . e($m($ng['month'])) . ' a month after a free ' . $days . '-day trial, with every feature included. Each extra person on your team is '
        . e($m($ng['seat'])) . ' a month. A Records Book is free in every month you pay for, and ' . e($m($ng['book'])) . ' at other times. Nfelia takes no commission on your sales. '
        . 'Prices differ by country: see the prices for ' . $and($price_links) . '.';
    $seat_text = e($m($ng['seat'])) . ' a month in Nigeria';
    $book_text = e($m($ng['book']));
} else {
    $price_answer = 'One monthly price, with every feature included, after a free trial. Nfelia takes no commission on your sales. Prices differ by country: see the prices for ' . $and($price_links) . '.';
    $seat_text = 'charged by the month';
    $book_text = 'the price of a Records Book';
}

$groups = [
    ['start', 'Nfelia, the price and the free trial', [
        ['What is Nfelia?',
         'Nfelia is an app for small businesses in Nigeria, Ghana, Kenya and Cameroon. It gives you your own website at yourname.nfelia.com, plus quotes, invoices, receipts, debts, stock, bookings, deliveries and your profit, all on your phone. It is a product of ' . e($org['legal']) . '. ' . $link('features', 'See everything Nfelia does') . '.'],
        ['Who is Nfelia for?',
         'Small businesses that sell things or serve people: market traders, Instagram and WhatsApp sellers, fashion designers, salons, caterers, phone and gadget shops, event planners and more. If you can send a WhatsApp message, you can use Nfelia. ' . $link('for', 'See Nfelia for your kind of business') . '.'],
        ['How much does Nfelia cost?', $price_answer],
        ['Can I try Nfelia for free?',
         'Yes. Use everything free for ' . $days . ' days, with no card needed to start. During the trial your website works for anyone with the link, but we keep it off Google until your first payment, and you can add ' . $trial_staff . ' besides yourself.'],
        ['What happens after the free trial?',
         'You decide. Pay for a month to carry on. If you don’t, Nfelia pauses, and your website shows a “closed for now” page with your WhatsApp button. Your records stay safe: you can still download your plain copy from Your records, and everything opens again when you pay for a month. We keep your records for 12 months after your trial or last paid month ends, and email you before anything is deleted.'],
        ['How do I pay for Nfelia?',
         'From Settings, then Billing, in the app. In Nigeria you pay online with Paystack. Where Nfelia takes Mobile Money, you pay by Mobile Money and send your receipt from Billing; Nfelia opens again once our team has checked it. Each payment buys one month and nothing renews by itself, so you are never charged without knowing. ' . $link('help/billing', 'More about billing') . '.'],
        ['Can I cancel?',
         'Yes. Open Settings, then Billing, and tap Cancel subscription. Nfelia and your website stay open until the end of the month you paid for. And since nothing renews by itself, if you simply don’t pay for the next month, nothing is charged.'],
        ['Is there a reward for telling other businesses?',
         'Yes, in Nigeria. Open Referrals in the app and share your code. When a business signs up with your code and makes its first payment, you get money off the next month you pay for.'],
    ]],
    ['website', 'Your website', [
        ['Is the website really free? Is it a real website?',
         'Yes. It comes with Nfelia at no extra cost: no designer, no setup fee. It lives at yourname.nfelia.com (like ivieluxe.nfelia.com, our example shop), in one of 10 designs made for different trades, from fashion and food to beauty, gadgets and events. You change the words, colours and photos from your phone in Shop Studio. A .com address of your own is not part of Nfelia for now. ' . $link('features/website', 'More about your website') . '.'],
        ['Can I change my website address later?',
         'You choose your address when you set up your shop, and you can change it once, from Shop Studio. Names that look like a bank, a phone network, a government body or an official account are checked by our team before they go live.'],
        ['Do you take a cut of my sales?',
         'No. Customers pay into your own account, by bank transfer or mobile money, and the money is yours. Nfelia takes no commission; you only pay the monthly price.'],
        ['How do customers order from my website?',
         'They add what they want to their bag and choose delivery or pickup. Then they pay into your account and attach their transfer screenshot, or send the order to you on WhatsApp. Every order lands in Nfelia with the customer saved, and they get a link to track it. ' . $link('help/website-orders', 'How website orders work') . '.'],
        ['Will people find my website on Google?',
         'Yes, once you subscribe. During the free trial your website works for anyone with the link, but we keep it off Google until your first payment. You can also list it in the <a href="' . APP_URL . '/shops">Nfelia shop directory</a>, by city and by what you sell. ' . $link('help/get-found-on-google', 'How to get found on Google') . '.'],
    ]],
    ['paid', 'Invoices, receipts and getting paid', [
        ['How do I send an invoice on WhatsApp?',
         'Make the invoice in Nfelia, then tap the WhatsApp button on it. Your customer gets a link that opens on any phone, with your logo and colours, and can download it as a PDF. Quotes, receipts and delivery notes work the same way. ' . $link('help/send-an-invoice', 'Send your first invoice') . '.'],
        ['Can I turn a quote into an invoice?',
         'Yes. When your customer agrees, open the quote and tap Accept and make the order. The order and its invoice are made from the quote, so you never type it twice. ' . $link('features/invoices', 'More about invoices and quotes') . '.'],
        ['Can I take a deposit and collect the balance later?',
         'Yes. Record each payment as it comes in. The invoice shows the deposit paid and the balance due, each payment gets its receipt, and a PAID stamp appears when they finish paying. ' . $link('help/receipts-and-payments', 'Receipts and payments, step by step') . '.'],
        ['How do I know who owes me?',
         'Nfelia shows who owes you, how much and who is late, and overdue money shows on your Home. Send a polite WhatsApp reminder in one tap: it says the balance and how many days late. You can keep track of what you owe your suppliers too. ' . $link('features/debts', 'More about the debt tracker') . '.'],
        ['Will Nfelia tell me when money comes in?',
         'Yes, once you allow notifications on your phone. Nfelia tells you when a payment is recorded, when a website order or booking arrives, and when something is running low. You choose which alerts you get.'],
    ]],
    ['stock', 'Stock, orders and deliveries', [
        ['Does my stock update itself?',
         'Yes. When you sell, the items come off your stock. Nfelia warns you when a product is running low and shows what your stock is worth at cost. You can add many products at once from a list. ' . $link('features/stock', 'More about stock') . '.'],
        ['Can customers track their order?',
         'Yes. Every website order gets its own tracking link: received, paid, on its way, delivered. The customer sees it when they order and gets it by email if they gave one, and you can send it on WhatsApp from the order in one tap. Once the order is delivered or collected, they can leave a review, so reviews come only from real orders. ' . $link('features/orders', 'More about orders and tracking') . '.'],
        ['How does proof of delivery work?',
         'Make a delivery note for what you are sending. When the goods arrive, your customer types their name on their phone to confirm, and signs with a finger too if you switch on signatures for delivery notes in Settings. The confirmed note stays in Nfelia, so there is no argument later. ' . $link('help/deliveries', 'Deliveries, step by step') . '.'],
    ]],
    ['bookings', 'Bookings, tasks and reminders', [
        ['Can customers book an appointment and pay a deposit?',
         'Yes, on your website: for hair, nails, fittings, events and shortlets. Customers pick a free time and pay the deposit into your account. Only free times show, so there is no double booking. You confirm in one tap and it goes on your calendar. ' . $link('features/bookings', 'More about bookings') . '.'],
        ['Will Nfelia remind me?',
         'Yes. On mornings with something planned, Nfelia sends you the day at 7am Nigeria time (6am in Ghana, 9am in Kenya): deliveries, appointments and money due. Any task can also remind you at the time you choose, by email and on your phone. Reminder times follow Nigeria time for now: in Kenya they come two hours late and in Ghana an hour early. On iPhone, add Nfelia to your Home Screen first (tap Share, then Add to Home Screen). ' . $link('features/tasks', 'More about tasks and reminders') . '.'],
        ['I’m a fashion designer. Where do I keep measurements?',
         'Save each client’s measurements in the notes on their customer profile, next to their quotes, deposits and balances. Each item on an order has a note too, for measurements or a name to print. In Tasks, the ready-made Sewing job plan takes you from measurements to delivery. ' . $link('for/fashion-designers', 'Nfelia for fashion designers') . '.'],
    ]],
    ['team', 'Your team', [
        ['Can my staff use Nfelia?',
         'Yes. Each person gets their own login. Each extra person is ' . $seat_text . ', and you can have up to ' . $staff_max . ' people in all, you included. Your free trial includes ' . $trial_staff . ' besides you. Only you, the owner, can add or remove people, in Settings, then Team. ' . $link('help/staff', 'Add your team, step by step') . '.'],
        ['What can my staff see?',
         'When you add someone, you choose Staff or Admin. Staff do the day-to-day work: orders, quotes, invoices, receipts, your catalogue, clients, deliveries, bookings and tasks. Admins can also see expenses, debts and profit, give refunds, cancel orders and edit your shop. Only you change the business details, payment accounts, team and billing.'],
        ['What if someone on my team is not honest?',
         'Every sale takes the items off your stock, and every change to stock is written down with what caused it, so missing goods show up quickly. Each person signs in as themselves, and you can remove someone at any time.'],
    ]],
    ['records', 'Your records and your data', [
        ['Is my business information safe?',
         'Your records belong to you. We never sell your data, and we never market to your customers: the only emails Nfelia sends them are about their own orders, bookings and the papers you make for them, in your shop’s name. Nfelia runs over a secure connection, passwords are stored scrambled, never as typed, and each business sees only its own records. After a while unused, Nfelia locks and asks for your fingerprint, face or password again. ' . $link('privacy', 'Read our Privacy Policy') . '.'],
        ['Can I take my records with me?',
         'Yes. Open Your records in the menu, under Data. Your Records Book puts everything in one Excel workbook in your colours, with totals, a chart of your months and a code anyone can check. It is free once in every month you pay for, or ' . $book_text . ' at other times. Your plain copy, every record as CSV files, is free whenever you ask, whatever your plan. Invoices, receipts and your profit report download as PDF whenever Nfelia is open for you; if it pauses, your plain copy and Records Book stay available for the 12 months we keep your records. ' . $link('features/records-book', 'More about the Records Book') . '.'],
        ['How can someone check that my invoice is genuine?',
         'Every invoice and receipt, and every Records Book, carries a code. Anyone can scan it or type it at <a href="' . APP_URL . '/verify">nfelia.com/verify</a> to see that it is genuine, with its amount and status. ' . $link('help/records-book', 'How the Records Book and its codes work') . '.'],
    ]],
    ['phone', 'Using Nfelia on your phone', [
        ['Do I need a laptop or a lot of data?',
         'No laptop needed. Nfelia opens in the browser on any smartphone, and you can add it to your home screen so it opens like an app, with no app store download. It needs a little data, like WhatsApp does, and pages you have already opened still open when the network drops. Use a laptop too if you like; your records are the same on both.'],
        ['How do I get the Nfelia app?',
         'Nfelia goes on your home screen straight from the browser, with nothing big to download. On Android, open your browser’s menu and tap Install app or Add to Home screen. On iPhone, tap Share in Safari, then Add to Home Screen. ' . $link('help/install-the-app', 'Install the app, step by step') . '.'],
        ['I’m not good with computers. Can I still use it?',
         'Yes. If you can send a WhatsApp message, you can send an invoice on Nfelia. We help you set up for free, and we are one ' . $wa('Hello Nfelia, please help me set up my business.', 'WhatsApp message') . ' away if you get stuck. You can also <a href="' . APP_URL . '/book">book a call with our team</a>.'],
        ['Which countries does Nfelia work in?',
         'Nigeria, Ghana, Kenya and Cameroon, in English. Your customers pay you the way they already do there, such as bank transfer, mobile money or M-Pesa. '
         . ($open ? 'Sign-ups are open in ' . $and($open) . '. ' : '')
         . ($closed ? 'In ' . $and($closed) . ', sign-ups are not open yet: ' . $wa('Hello Nfelia, when can I sign up in my country?', 'ask us on WhatsApp') . ' and we will tell you when.' : '')],
    ]],
];
?>
<div class="wrap article-wrap has-aside">
    <div class="prose">
        <header class="article-head">
            <p class="kicker">Questions people ask</p>
            <h1>Questions people ask about Nfelia</h1>
            <p class="page-lead">Straight answers about what Nfelia does, what it costs and how your records stay yours. Can’t find yours? <?= $wa('Hello Nfelia, I have a question.', 'Ask us on WhatsApp') ?>.</p>
            <p class="meta">Prices here are for Nigeria; they differ by country. Checked against the app on <time datetime="<?= e($page['updated']) ?>"><?= e(date('j F Y', strtotime($page['updated']))) ?></time>.</p>
        </header>
        <nav class="toc" aria-labelledby="topics-title">
            <h2 id="topics-title">Topics</h2>
            <ol>
                <?php foreach ($groups as [$id, $h2]): ?>
                <li><a href="#<?= e($id) ?>"><?= e($h2) ?></a></li>
                <?php endforeach; ?>
            </ol>
        </nav>
        <?php foreach ($groups as [$id, $h2, $qas]): ?>
        <h2 id="<?= e($id) ?>"><?= e($h2) ?></h2>
        <?= site_faq($qas) ?>
        <?php endforeach; ?>
    </div>
    <aside class="article-aside">
        <section class="toc" aria-labelledby="ask-title">
            <h2 id="ask-title">Still have a question?</h2>
            <ul class="link-list">
                <li><a href="<?= e(site_wa('Hello Nfelia, I have a question.')) ?>" target="_blank" rel="noopener"><strong>Chat on WhatsApp</strong><span><?= e($org['phone_text']) ?></span></a></li>
                <li><a href="<?= APP_URL ?>/book"><strong>Book a call</strong><span>On Google Meet, at a time that suits you</span></a></li>
                <?php if (site_page('contact')): ?>
                <li><a href="<?= e(site_url('contact')) ?>"><strong>All the ways to reach us</strong><span>Phone, support and sales email</span></a></li>
                <?php endif; ?>
                <?php if (site_page('help')): ?>
                <li><a href="<?= e(site_url('help')) ?>"><strong>The help centre</strong><span>Step-by-step guides for every part of Nfelia</span></a></li>
                <?php endif; ?>
            </ul>
        </section>
    </aside>
</div>

<?= site_related(['features', 'pricing', 'help']) ?>
<?= site_cta() ?>
