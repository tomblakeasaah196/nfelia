<?php
/**
 * /for/salons: Nfelia for salons, barbers and beauty studios.
 *
 * Checked against the app (28 Oct 2026): "People book this" on a service,
 * how long it takes and its deposit (views/products/form.php); Booking hours
 * with "Customers at the same time" (views/bookings/settings.php); a request
 * holds its time for 24 hours (BOOKING_HOLD_HOURS); "I have the deposit"
 * makes a receipt and "Confirm and add to my calendar" adds an appointment
 * with a reminder an hour before (BookingController::confirm); the client's
 * booking page with "To pay on the day" (views/storefront/pages/booking.php);
 * WhatsApp reminders for appointments (app/helpers/planner.php); money paid
 * ahead kept for the next order (kept_add in app/helpers/orders.php).
 */
require_once BASE_PATH . '/views/home/_icons.php';   // site_hero and site_ticks draw icons before the layout loads them
$prices = site_prices($page['country']);
$link   = fn(string $key, string $text) => site_page($key) ? '<a href="' . e(site_url($key)) . '">' . e($text) . '</a>' : e($text);
$price_html = ($prices
        ? 'Free for ' . (int)$prices['trial_days'] . ' days with everything, and no card needed. Then ' . e(($prices['money'])($prices['month'])) . ' a month in Nigeria. '
        : 'Message us on WhatsApp for the price. ')
    . 'For Ghana, Kenya and Cameroon, see the prices for ' . $link('gh/pricing', 'Ghana') . ', ' . $link('ke/pricing', 'Kenya') . ' and ' . $link('cm/pricing', 'Cameroon') . '.';

$rows = [
    ['“Can I come on Saturday?” all day', 'Clients see your free times and book on your website.', 'features/bookings', 'Bookings'],
    ['No-shows', 'A deposit before the booking is confirmed, with its receipt.', 'help/bookings', 'Taking bookings'],
    ['Forgetting who booked what', 'Confirmed bookings on your calendar, with reminders.', 'features/tasks', 'Tasks and reminders'],
    ['Regulars who pay later', 'Their balance in Debts, with a WhatsApp reminder.', 'features/debts', 'Debts'],
    ['Hair and beauty products', 'Stock that comes off when you sell, with a warning before you run out.', 'features/stock', 'Stock'],
];

echo site_hero([
    'kicker'    => 'For salons and beauty',
    'h1'        => 'Bookings that come with a deposit',
    'lead_html' => 'Nfelia is a salon booking app with deposits, for salons, barbers, nail and lash studios in Nigeria, Ghana, Kenya and Cameroon. Clients pick a free time on your website and pay the deposit before they come, so your chair is not kept for someone who never shows up.',
    'screens'   => [['book-1', 'Booking a wig install on Ivie Luxe Hair\'s website: ₦25,000, 2 hours, 50% deposit, with a calendar of free days', 'They pick a free time'],
                    ['book-2', 'A booking request for Saturday at 10am, with Confirm and add to my calendar', 'You confirm in one tap']],
]);
?>
<section class="page-section" aria-labelledby="how-title">
    <div class="wrap split">
        <div class="split-copy">
            <h2 id="how-title">How booking works on your website</h2>
            <p>Open a service in your products, turn on “People book this”, and set how long it takes and the deposit: a share of the price or a fixed amount. A Book button then shows on your website. Clients only see free times inside your opening hours.</p>
            <?= site_ticks([['Two stylists?', 'Set how many clients you take at the same time.'],
                            ['No double booking.', 'A request holds its time for 24 hours while you check the deposit.'],
                            ['One tap to confirm.', 'It goes on your calendar, and the deposit gets its receipt.']]) ?>
        </div>
        <div>
            <?= site_steps([
                ['They pick a time', 'On your website, from the free times you have.'],
                ['They pay the deposit', 'Into your own account, then send the proof with the booking.'],
                ['They get their own page', 'With the time, the deposit and what is left to pay on the day.'],
            ]) ?>
        </div>
    </div>
</section>

<section class="page-section tint" aria-labelledby="noshow-title">
    <div class="wrap split flip">
        <div class="split-copy">
            <h2 id="noshow-title">Fewer no-shows, without chasing</h2>
            <p>A deposit means the client has something on the line, and a missed time no longer leaves you with nothing. Confirmed bookings sit on your calendar, and Nfelia reminds you an hour before each one (in Nigeria time for now, so in Kenya the reminder comes two hours late and in Ghana an hour early). On the day, the appointment shows on your Tasks with a button that opens WhatsApp with the client's reminder already written.</p>
            <?= site_ticks([['Write your deposit rule', 'in the service\'s description, so clients read it before they book.'],
                            ['Can\'t take a booking?', 'Decline it with a message, and the time is free again.'],
                            ['Your day each morning,', 'by email, on mornings with something planned: 7am Nigeria time (6am in Ghana, 9am in Kenya).']]) ?>
        </div>
        <div><?= site_screens([['tasks-2', 'Calendar for October with appointments, deliveries and money due marked on each day', 'Your month']]) ?></div>
    </div>
</section>

<section class="page-section" aria-labelledby="regulars-title">
    <div class="wrap split">
        <div class="split-copy">
            <h2 id="regulars-title">Know your regulars</h2>
            <p>Every client who books or orders on your website is saved with their phone number. Their page shows their next appointment, their orders and payments, what they have spent with you and anything they still owe. Use the notes for what you want to remember: the style, the length, the products that suit them.</p>
            <?= site_ticks([['Paid ahead?', 'Money a client sends before they choose can be kept for their next order.'],
                            ['Sell your products too,', 'with stock that counts itself.'],
                            ['The Glow design', 'gives your website a look made for beauty and salons.']]) ?>
        </div>
        <div><?= site_screens([['stock-1', 'Stock running low: a frontal wig with 2 left, a closure with 1 left, a hair care kit with 3 left', 'Running low']]) ?></div>
    </div>
    <p class="stories-note">Screens from Ivie Luxe Hair, an example business.</p>
</section>

<section class="page-section tint" aria-labelledby="fix-title">
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

<section class="page-section" aria-labelledby="example-title">
    <div class="wrap">
        <?= site_head('An example weekend', 'An example to show how Nfelia fits a salon. The client and times are made up.', 'example-title') ?>
        <?= site_steps([
            ['Thursday night: a request', 'A client in Lekki picks Saturday at 10am for knotless braids on your website and sends a screenshot of her deposit.'],
            ['Friday morning: confirm', 'You check your bank app, tick that the deposit came in and tap Confirm and add to my calendar. Her receipt is made, and her booking page says Confirmed.'],
            ['Saturday, 7am: your day', 'Nfelia emails you the day\'s appointments.'],
            ['Saturday, 9am: her reminder', 'Her appointment is on your Tasks. One tap opens WhatsApp with her reminder written.'],
            ['Saturday, 1pm: done', 'She pays the rest at the chair. You record it as a new order in the shop, mark the booking as done, and add the style to her notes for next time.'],
        ]) ?>
    </div>
</section>

<section class="page-section tint" aria-labelledby="faq-title">
    <div class="wrap">
        <?= site_head('Questions salon owners ask', '', 'faq-title', true) ?>
        <?= site_faq([
            ['Can clients book and pay a deposit on my website?',
             'Yes. Turn on “People book this” for a service, set how long it takes and the deposit, and a Book button shows on your website. Clients pick a free time, pay the deposit into your account and send the proof. You confirm in one tap. ' . $link('features/bookings', 'See how bookings work') . '.'],
            ['What happens if a client doesn\'t show up?',
             'There is no separate no-show button. The deposit you confirmed already has its receipt and stays in your money in. The rule for missed times is yours: write it in the service\'s description, like “Deposit not refunded if you miss your time without telling us”, so clients read it before they book.'],
            ['Can two stylists take bookings at the same time?',
             'Yes. In Booking hours, set Customers at the same time to the number of chairs or stylists you have. That many bookings can share a time, and no more. Your own appointments in Tasks and Calendar block their time too.'],
            ['Do I need a separate booking app and website?',
             'No. Bookings are part of your Nfelia website at yourname.nfelia.com, next to your prices, products and reviews. Invoices, receipts, stock and debts are in the same app.'],
            ['How much does Nfelia cost?', $price_html],
        ]) ?>
    </div>
</section>

<?= site_related(['features/bookings', 'help/bookings', 'blog/take-booking-deposits', 'features/website', 'features/stock']) ?>
<?= site_cta('Take your next booking with a deposit') ?>
