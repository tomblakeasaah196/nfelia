<?php
/**
 * /features/bookings: bookings with deposits on the owner's website.
 *
 * Checked against app/helpers/bookings.php (the three ways to book, booking
 * hours, deposits, caution fees, longer-stay prices, the 24-hour hold),
 * app/controllers/BookingController.php (confirm with the deposit receipt,
 * decline, cancel, done), StorefrontController::place_booking and
 * views/storefront/pages/book.php and booking.php (what the customer sees),
 * views/products/form.php (People book this) and views/bookings/*.php.
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
    'kicker'    => 'Bookings with deposits',
    'h1'        => 'Let customers book a time and pay the deposit first',
    'lead_html' => 'Nfelia is a booking app with deposits for salons, braiders, tailors, make-up artists, event planners and shortlets. '
                 . 'Customers pick a free time on your Nfelia website and send the deposit to your account. You confirm in one tap, and it goes on your calendar.',
    'note'      => 'Part of Nfelia. Free for ' . $trial . ' days, no card needed.',
    'screens'   => [
        ['book-1', 'Booking a wig install on Ivie Luxe Hair’s website: ₦25,000, 2 hours, 50% deposit, with a calendar of free days', 'They pick a free time'],
        ['book-2', 'A booking request for Saturday at 10am, with a Confirm and add to my calendar button', 'You confirm in one tap'],
    ],
]);
?>

<section class="page-section" aria-labelledby="how-title">
    <div class="wrap split">
        <div class="split-copy">
            <h2 id="how-title">How a booking works</h2>
            <p>No more back and forth on WhatsApp to find a time. Your customer books on your website in a minute, and you only say yes to a time that is free and paid for.</p>
            <p class="hero-note"><?= nf_icon('check-circle') ?>Screens from Ivie Luxe Hair, an example business.</p>
        </div>
        <div>
            <?= site_steps([
                ['They pick a free time', 'Every service you take bookings for has a Book button on your website. A calendar shows only the days and times you are open and free.'],
                ['They pay the deposit', 'The page shows the total, the deposit and what is left to pay on the day. They send the deposit to your account and add a screenshot of the transfer, or book on WhatsApp instead.'],
                ['You check and confirm', 'Nfelia tells you straight away, in the app and by email, and on your phone once you turn on notifications. Check your bank app for the money, then tap <strong>Confirm and add to my calendar</strong>. Tick that you have the deposit and Nfelia makes the receipt.'],
                ['They follow it on their own page', 'Each booking has a private page with the time, the deposit, what is left to pay and a button to add it to their phone’s calendar. If they gave an email, they hear when you confirm.'],
            ]) ?>
        </div>
    </div>
</section>

<section class="page-section tint" aria-labelledby="free-title">
    <div class="wrap split flip">
        <div class="split-copy">
            <h2 id="free-title">Only free times show, so no double booking</h2>
            <p>You set your rules once, in <strong>Booking hours</strong>. Nfelia works out what is free from them, from your other bookings and from your own appointments.</p>
            <?= site_ticks([
                ['Opening hours for each day.', 'Close the days you don’t work.'],
                ['Notice and how far ahead.', 'Ask for up to 3 days’ notice, and let people book anything from a week to a year ahead.'],
                ['More than one chair.', 'Set how many customers you can serve at the same time, up to 20. Two stylists? Put 2.'],
                ['Your own appointments count.', 'Add an appointment in Tasks & Calendar and time-slot bookings skip that time.'],
                ['A request holds its time for 24 hours.', 'Nobody else can take it while you check the deposit. After a day without an answer the time opens up again, so unanswered requests never fill your diary.'],
            ]) ?>
        </div>
        <div>
            <?= site_screens([['tasks-2', 'Calendar for October with appointments, deliveries and money due marked on each day', 'Confirmed bookings on your calendar']]) ?>
        </div>
    </div>
</section>

<section class="page-section" aria-labelledby="ways-title">
    <div class="wrap">
        <?= site_head('Three ways to be booked', 'Open any service in your products, turn on <strong>People book this</strong> and choose how it is booked.', 'ways-title') ?>
        <div class="compare table-scroll">
            <table>
                <caption class="sr-only">How a service can be booked on your Nfelia website</caption>
                <thead>
                    <tr><th scope="col">People book it</th><th scope="col">Good for</th><th scope="col">How it works</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <th scope="row">By the time slot, like an appointment</th>
                        <td>Hair, braids, nails, make-up, fittings, repairs, lessons</td>
                        <td>From 15 minutes to 8 hours long, with start times every 15, 30 or 60 minutes.</td>
                    </tr>
                    <tr>
                        <th scope="row">By the night, like a room or apartment</th>
                        <td>Shortlets, guest houses, rooms</td>
                        <td>Guests pick the day they arrive and the day they leave, up to 30 nights. They see your check-in and check-out times and house rules before they pay.</td>
                    </tr>
                    <tr>
                        <th scope="row">By the whole day, like an event</th>
                        <td>Event halls, décor packages, party catering, photo shoots</td>
                        <td>Customers pick a day. You set how many bookings you can take on one day, from 1 to 20.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="page-section rose" aria-labelledby="deposit-title">
    <div class="wrap split">
        <div class="split-copy">
            <h2 id="deposit-title">Deposits that go straight to you</h2>
            <p>For each service, choose no deposit, a share of the price (like 50%) or a fixed amount. Customers pay it into your own bank account, so the money is yours at once.</p>
            <p>A deposit also means fewer no-shows. Someone who has paid to hold your Saturday morning is far more likely to turn up, or to tell you in good time.</p>
        </div>
        <div class="split-copy">
            <?= site_ticks([
                ['The deposit is in your books.', 'Tick “I have the deposit” as you confirm, and Nfelia makes a receipt for it, so it counts in your money in.'],
                ['A reminder before each booking.', 'A confirmed booking with a time reminds you an hour before. Times follow Nigeria time for now, so in Kenya the reminder and the customer’s calendar entry come two hours late, and in Ghana an hour early.'],
                ['Caution fees for stays.', 'Take a refundable caution fee with the booking. It stays out of your money in, and Nfelia reminds you to give it back after check-out.'],
                ['Cheaper for longer stays.', 'Set up to three lower nightly prices, such as 7 nights or more. A stay pays the cheapest price it reaches.'],
            ]) ?>
        </div>
    </div>
</section>

<?php $cards = $for_cards([
    ['for/salons', 'Salons and beauty', 'Hair, nails, braids and make-up booked by the time slot, with the deposit paid first.', 'calendar'],
    ['for/fashion-designers', 'Fashion designers', 'Customers book a fitting or a measurement visit and pay a deposit to hold it.', 'pen'],
    ['for/events', 'Events and décor', 'Halls and packages booked by the whole day, with as many bookings a day as you can handle.', 'calendar'],
    ['for/food-and-catering', 'Food and catering', 'Party dates booked as a whole day, with a deposit in hand before you buy foodstuff.', 'bag'],
]); ?>
<?php if ($cards !== ''): ?>
<section class="page-section" aria-labelledby="who-title">
    <div class="wrap">
        <?= site_head('Who takes bookings on Nfelia', 'Any business that sells time: a chair, a fitting, a room or a whole day.', 'who-title') ?>
        <?= $cards ?>
    </div>
</section>
<?php endif; ?>

<section class="page-section tint" aria-labelledby="faq-title">
    <div class="wrap">
        <?= site_head('Questions about bookings', '', 'faq-title', true) ?>
        <?= site_faq([
            ['Can customers pay the deposit by card?',
             'Not on the booking page. They pay by transfer straight into your own account, so the money is yours at once and Nfelia takes no commission. They can add a screenshot of the transfer, and you confirm once you see the money in your bank app.'],
            ['What if two people want the same time?',
             'Only one can have it. Only free times are shown, and Nfelia checks the time again at the moment a booking is sent. If you can serve more than one person at once, set <strong>Customers at the same time</strong> in Booking hours.'],
            ['Do I need a website to take bookings?',
             'Bookings happen on your Nfelia website, at yourname.nfelia.com, which comes with Nfelia. Open a service in your products, turn on <strong>People book this</strong>, and a Book button shows on your website. Read more about ' . $link('features/website', 'your own website') . '.'],
            ['Can I keep some hours free?',
             'Yes. Close whole days in Booking hours. To keep a few hours free on a working day, add an appointment to ' . $link('features/tasks', 'your calendar in Nfelia') . ': time-slot bookings skip that time.'],
            ['What if I can’t take a booking?',
             'Tap <strong>Decline and free the time</strong>, with a short note like “Fully booked that day. Please pick another time.” They see it on their booking page, and by email if they gave one. A confirmed booking can be cancelled the same way, and it comes off your calendar.'],
            ['What does it cost?',
             $month !== null
                ? 'Bookings are part of Nfelia, with your website, invoices, receipts and the rest: ' . e($month) . ' a month in Nigeria, free for ' . $trial . ' days with no card needed. See ' . $link('pricing', 'pricing') . '.'
                : 'Bookings are part of Nfelia, with your website, invoices, receipts and the rest. Ask us on WhatsApp for the price where you are.'],
        ]) ?>
    </div>
</section>

<?= site_related(['help/bookings', 'blog/take-booking-deposits', 'features/website']) ?>
<?= site_cta('Take your next booking with a deposit') ?>
