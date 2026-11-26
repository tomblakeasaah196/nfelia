<?php
/**
 * Help: take bookings with deposits. Checked against views/products/form.php
 * (the Bookings section), views/bookings (index, settings, view),
 * BookingController (confirm, decline, complete, cancel, caution),
 * app/helpers/bookings.php (free times, the 24-hour hold) and the shop's
 * booking pages (views/storefront).
 */
require_once BASE_PATH . '/views/home/_icons.php';   // nf_icon() for the building blocks: the layout loads it only after the view
$u = fn(string $key) => e(site_url($key));
$toc = [
    'how'       => 'How bookings work',
    'turn-on'   => 'Turn on bookings for a service',
    'hours'     => 'Set your booking hours',
    'confirm'   => 'Confirm a booking request',
    'after'     => 'After you confirm',
    'stays'     => 'Rooms and apartments by the night',
    'problems'  => 'Common problems and fixes',
];
?>
<article class="wrap article-wrap has-aside">
    <header class="article-head">
        <p class="kicker">Help centre</p>
        <h1>Take bookings with a deposit on your website</h1>
        <p class="page-lead">Turn on bookings for a service in your Catalogue, choose how long it takes and the deposit, and set your booking hours. Customers then pick a free time on your website and pay the deposit by transfer. You check the money and tap Confirm and add to my calendar.</p>
        <?= site_dates($page) ?>
    </header>
    <aside class="article-aside">
        <nav class="toc" aria-labelledby="toc-title">
            <h2 id="toc-title">On this page</h2>
            <ol>
                <?php foreach ($toc as $id => $label): ?>
                <li><a href="#<?= e($id) ?>"><?= e($label) ?></a></li>
                <?php endforeach; ?>
            </ol>
        </nav>
    </aside>

    <div class="prose">
        <h2 id="how">How bookings work</h2>
        <ul>
            <li><strong>Customers only see free times.</strong> Nfelia shows times inside your booking hours that are not taken by another booking or by an appointment in your Tasks and Calendar, so you are not booked twice.</li>
            <li><strong>The deposit goes to your own account.</strong> Customers pay it by transfer to the payment account on your website. Nfelia takes no commission.</li>
            <li><strong>You stay in charge.</strong> Every booking arrives as a request. It holds its time for 24 hours while you check the deposit, and only goes on your calendar when you confirm it.</li>
        </ul>
        <p>It suits salons and barbers, nail and lash studios, fittings with a tailor, repairs, photo shoots, event packages, and rooms or apartments let by the night.</p>
        <?= site_screens([
            ['book-1', 'Booking a wig install on Ivie Luxe Hair\'s website: ₦25,000, 2 hours, a 50% deposit, with a calendar of free days', 'They pick a free time'],
            ['book-2', 'A booking request in Nfelia for Saturday at 10am, with a button to confirm it and add it to the calendar', 'You confirm it'],
        ]) ?>
        <p>The screens are from Ivie Luxe Hair, an example business.</p>

        <h2 id="turn-on">Turn on bookings for a service</h2>
        <?= site_steps([
            ['Open the service in your Catalogue', 'On a phone, tap <strong>More</strong>, then <strong>Catalogue</strong>, then the service. Tap <strong>Edit</strong>. New to the Catalogue? Read <a href="' . $u('help/add-products') . '">how to add your products</a> first.'],
            ['Find the Bookings section', 'It shows for services, and for any business that sells services. If you do not see it, tick <strong>This is a service</strong> in the <strong>Stock</strong> section.'],
            ['Choose how people book it', 'Under <strong>People book this</strong>, pick <strong>A time slot, like an appointment</strong>, <strong>By the night, like a room or apartment</strong>, or <strong>A whole day, like an event</strong>.'],
            ['Say how long, or how many', 'For a time slot, choose <strong>How long it takes</strong>, from 15 minutes to 8 hours. For a whole day, type <strong>Bookings you can take a day</strong>.'],
            ['Set the deposit', 'Under <strong>Deposit to book</strong>, choose <strong>A share (%)</strong> or <strong>An amount</strong>, then type <strong>How much</strong>. Or choose <strong>No deposit</strong>.'],
            ['Tap Save changes', 'Your website now shows a button on it: <strong>Pick a time and book</strong>, <strong>Pick a day and book</strong> or <strong>Check dates and book</strong>.'],
        ]) ?>
        <p class="note"><strong>Check your payment account</strong> Customers pay deposits to the account your website shows. The owner adds and changes it in Settings, Business, under <strong>Payment accounts</strong>.</p>

        <h2 id="hours">Set your booking hours</h2>
        <?= site_steps([
            ['Open Booking hours', 'Tap <strong>More</strong>, then <strong>Bookings</strong> under Storefront, then <strong>Booking hours</strong>. You can also tap <strong>Set your booking hours</strong> under the deposit on any service.'],
            ['Tick the days you open', 'Under <strong>Opening hours</strong>, tick each day and set its opening and closing time. An unticked day shows as Closed.'],
            ['Set the rules', '<strong>Book at least</strong> how long ahead, <strong>Book up to</strong> how far ahead, and <strong>Start times every</strong> 15 minutes, 30 minutes or hour.'],
            ['Say how many at once', '<strong>Customers at the same time</strong> is for time slots. Two stylists working together? Put 2.'],
            ['Tap Save booking hours', 'Your website offers only times inside these hours from now on.'],
        ]) ?>

        <h2 id="confirm">Confirm a booking request</h2>
        <p>When someone books, you get an alert, and the request waits in Bookings under <strong>Requests</strong>.</p>
        <?= site_steps([
            ['Open Bookings', 'Tap <strong>More</strong>, then <strong>Bookings</strong>. The tabs are <strong>Requests</strong>, <strong>Coming up</strong> and <strong>Past</strong>.'],
            ['Open the request', 'You see the booking number with the tag <strong>To confirm</strong>, the time, the service, the deposit and what is left to pay, the customer\'s note, and the proof of the deposit when they sent one.'],
            ['Check the money in your bank app', 'A screenshot alone is not proof. Look for the deposit in your bank or Mobile Money app first.'],
            ['Tick that you have the deposit', 'Tick the box <strong>I have the [amount] deposit</strong>. Nfelia makes a receipt for it, so it counts in your money in.'],
            ['Tap Confirm and add to my calendar', 'You see that the booking is confirmed and added to your calendar, with the deposit\'s receipt number. It moves to <strong>Coming up</strong>.'],
        ]) ?>
        <p>Can't take it? Write a short reason in <strong>Can't take it? Tell them why</strong> and tap <strong>Decline and free the time</strong>. The customer sees your message and the time opens up for someone else.</p>

        <h2 id="after">After you confirm</h2>
        <ul>
            <li>The booking is an appointment in <strong>Tasks</strong> on its day. A time-slot booking also gets a reminder an hour before (in Nigeria time for now: two hours late in Kenya, an hour early in Ghana). See <a href="<?= $u('help/tasks-and-reminders') ?>">tasks and reminders</a>.</li>
            <li>The customer has their own booking page with the time, the deposit and what is left to pay on the day. Its link is on the booking, under <strong>Their booking page</strong>.</li>
            <li>The booking page in Nfelia has a WhatsApp button to message the customer, and their phone and email.</li>
            <li>When the job is done, open the booking and tap <strong>Mark as done</strong>. If plans change, use <strong>Cancel this booking</strong>, with a message they will see.</li>
        </ul>

        <h2 id="stays">Rooms and apartments by the night</h2>
        <p>Choose <strong>By the night, like a room or apartment</strong> and the price becomes a price per night. You can add:</p>
        <ul>
            <li>a refundable <strong>Caution fee</strong>, paid with the booking. It is not counted as income, and Nfelia reminds you to give it back after check-out. Tap <strong>I've given it back</strong> on the booking when you do.</li>
            <li><strong>Cheaper for longer stays</strong>: a lower price a night from a number of nights you choose.</li>
            <li><strong>Check-in from</strong>, <strong>Check-out by</strong> and up to 8 <strong>House rules</strong>, in Booking hours. Guests see them before they pay.</li>
        </ul>

        <h2 id="problems">Common problems and fixes</h2>
        <?= site_faq([
            ['Why don\'t I see the Bookings section on my item?',
             'It shows for services. Tick This is a service in the item\'s Stock section, and the Bookings section appears below.'],
            ['Customers say no times are free. What is wrong?',
             'Check Booking hours: the day must be ticked, and Book at least and Book up to must leave room. Times also close when another booking or an appointment in your calendar takes them, or when the number in Customers at the same time is reached.'],
            ['A request is more than a day old. Can I still confirm it?',
             'Yes, if the time is still free. A request holds its time for 24 hours. After that, if someone else booked the same time, Nfelia tells you so: agree another time with the customer.'],
            ['Does Nfelia collect the deposit for me?',
             'No. Customers pay you directly, by transfer to your own account, and you confirm once you see the money. Nfelia never holds your money.'],
            ['Can my staff confirm bookings?',
             'Yes. Bookings is open to everyone on your team. Read <a href="' . $u('help/staff') . '">what staff can see</a>.'],
        ]) ?>
    </div>
</article>
<?= site_related(['features/bookings', 'blog/take-booking-deposits', 'for/salons', 'help/your-website', 'help/tasks-and-reminders', 'for/events']) ?>
<?= site_cta('Take bookings with deposits') ?>
