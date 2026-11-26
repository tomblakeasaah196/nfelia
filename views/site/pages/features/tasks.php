<?php
/**
 * /features/tasks: Tasks & Calendar, reminders and the morning plan.
 *
 * Checked against app/helpers/planner.php (the sections, the dates Nfelia
 * already knows, reminder choices, repeats, job plans),
 * app/controllers/TasksController.php and views/tasks/index.php (the two
 * tabs, Plan a job, Who is doing it?, the Reminders sheet, what staff see),
 * cron/planner.php (reminders, the morning plan from 7am, quiet hours) and
 * app/helpers/notify.php (the bell, phone and email; More > Notifications).
 */
require_once BASE_PATH . '/views/home/_icons.php';   // site_hero() draws icons before the layout loads them

$prices = site_prices($page['country']);
$month  = $prices ? $prices['money']((float)$prices['month']) : null;
$seat   = $prices ? $prices['money']((float)$prices['seat']) : null;
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
    'kicker'    => 'Tasks and reminders',
    'h1'        => 'Never miss a delivery, fitting or payment date',
    'lead_html' => 'Tasks &amp; Calendar is the business reminder app inside Nfelia. Your own tasks and appointments sit on one list with the dates Nfelia already knows: deliveries you promised, money due, orders to confirm. '
                 . 'It reminds you on your phone and by email, and sends you your day early each morning.',
    'note'      => 'Part of Nfelia. Free for ' . $trial . ' days, no card needed.',
    'screens'   => [
        ['tasks-1', 'Tasks: a job with 2 days left, payments and a delivery that are late, and deliveries due today', 'Today, and what is late'],
        ['tasks-2', 'Calendar for October with appointments, deliveries and money due marked on each day', 'Your month'],
    ],
]);
?>

<section class="page-section" aria-labelledby="list-title">
    <div class="wrap split">
        <div class="split-copy">
            <h2 id="list-title">One list for the day</h2>
            <p>The <strong>Tasks</strong> tab shows what is late, what is due today, the next seven days and your jobs in progress. A countdown says how many days are left. The <strong>Calendar</strong> tab shows the month, with a coloured dot for each appointment, task, delivery, money date and quote.</p>
            <p>Type a task under <strong>Add a task</strong>, pick when, and tap Add. Tick it off when it is done.</p>
            <p class="hero-note"><?= nf_icon('check-circle') ?>Screens from example businesses.</p>
        </div>
        <div class="split-copy">
            <h3>Dates Nfelia already knows</h3>
            <p>You don’t type these in. They come from the rest of Nfelia and clear by themselves when the money comes in or the goods go out.</p>
            <?= site_ticks([
                ['Deliveries', 'you promised on an invoice or an order, and delivery notes on their way'],
                ['Invoices due', 'and customers who promised to pay, with a polite WhatsApp reminder ready to send'],
                ['Suppliers', 'you said you would pay, on the day you promised'],
                ['Quotes', 'waiting for an answer or about to run out'],
                ['Shop orders', 'waiting for you to confirm them'],
                ['Products', 'running low, to restock'],
            ]) ?>
        </div>
    </div>
</section>

<section class="page-section tint" aria-labelledby="remind-title">
    <div class="wrap split">
        <div class="split-copy">
            <h2 id="remind-title">Reminders that reach you</h2>
            <p>The bell at the top of Nfelia always shows what is due. Choose how else Nfelia reaches you in <strong>Set up reminders</strong>, on the Tasks page.</p>
            <p>Your morning plan comes at 7am Nigeria time (6am in Ghana, 9am in Kenya) on days with something on: your appointments, tasks, deliveries and money due, by email and on your phone. Days with nothing on send nothing.</p>
            <p>Reminder times follow Nigeria time (WAT) for now, the same as Cameroon’s. In Kenya they come two hours late and in Ghana an hour early.</p>
        </div>
        <div class="split-copy">
            <?= site_ticks([
                ['At the time you choose.', 'Any task or appointment can remind you at the time, 15 minutes, 1 hour or 1 day before. A task with no time reminds you at 8am, on the day or the day before.'],
                ['On your phone.', 'Your phone buzzes at the reminder time, even with Nfelia closed. On iPhone, add Nfelia to your Home Screen first.'],
                ['By email.', 'An email at the time you chose, if you want one.'],
                ['Quiet when you rest.', 'Turn on quiet hours in More › Notifications and your phone won’t buzz at night. Everything still waits in the bell.'],
            ]) ?>
        </div>
    </div>
</section>

<section class="page-section" aria-labelledby="plan-title">
    <div class="wrap">
        <?= site_head('Plan a job in one go', 'Tap <strong>Plan a job</strong>, pick a plan and the day you must finish. Nfelia adds the steps with dates, counted back from that day. Change any of them, give the job to someone on your team, and link it to an invoice so the delivery step marks the invoice delivered.', 'plan-title') ?>
        <div class="compare table-scroll">
            <table>
                <caption class="sr-only">Ready-made job plans in Tasks and Calendar</caption>
                <thead><tr><th scope="col">Plan</th><th scope="col">Steps it adds</th></tr></thead>
                <tbody>
                    <tr><th scope="row">Sewing job</th><td>Take measurements, buy fabric and materials, fitting, ready for pickup, deliver</td></tr>
                    <tr><th scope="row">Customer order</th><td>Confirm payment, get the goods ready, deliver</td></tr>
                    <tr><th scope="row">Event</th><td>Collect the deposit three weeks before, book vendors, confirm guest numbers, collect the balance, set-up, event day</td></tr>
                    <tr><th scope="row">Supplier order</th><td>Pay the supplier, check it is on the way, goods arrive, add the new stock to Nfelia</td></tr>
                    <tr><th scope="row">My own steps</th><td>Start from a blank plan and write your own</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="page-section rose" aria-labelledby="team-title">
    <div class="wrap split flip">
        <div class="split-copy">
            <h2 id="team-title">Work as a team</h2>
            <p>Give any task or job to someone on your team under <strong>Who is doing it?</strong> They hear about it straight away, and you hear when they finish. Switch between <strong>Everyone</strong> and <strong>Mine</strong> to see the whole plan or just your own work.<?php if ($seat !== null): ?> Each extra person on your team is <?= e($seat) ?> a month.<?php endif; ?></p>
            <?= site_ticks([
                ['Staff see the plan, not the money.', 'Invoices due and debts stay with the owner and admins.'],
                ['Repeats.', 'Rent, market day, a weekly stock count: set a task to repeat every day, week or month.'],
                ['Bookings fill in by themselves.', 'A booking you confirm goes on the calendar as an appointment, with a reminder an hour before when it has a time.'],
            ]) ?>
        </div>
        <div>
            <?= site_screens([['book-2', 'A booking request for Saturday at 10am, with a Confirm and add to my calendar button', 'Confirmed bookings join your calendar']]) ?>
        </div>
    </div>
</section>

<?php $cards = $for_cards([
    ['for/fashion-designers', 'Fashion designers', 'A sewing plan from measurements to delivery, with the fitting on your calendar.', 'pen'],
    ['for/events', 'Events and décor', 'An event plan from the deposit to the big day, step by step.', 'calendar'],
    ['for/salons', 'Salons and beauty', 'Appointments and bookings on one calendar, with a reminder before each.', 'calendar'],
    ['for/food-and-catering', 'Food and catering', 'Every delivery day and party date, sent to you each morning.', 'bag'],
]); ?>
<?php if ($cards !== ''): ?>
<section class="page-section" aria-labelledby="who-title">
    <div class="wrap">
        <?= site_head('Who it helps', 'Anyone juggling deliveries, fittings, events and money due in their head.', 'who-title') ?>
        <?= $cards ?>
    </div>
</section>
<?php endif; ?>

<section class="page-section tint" aria-labelledby="faq-title">
    <div class="wrap">
        <?= site_head('Questions about tasks and reminders', '', 'faq-title', true) ?>
        <?= site_faq([
            ['Will it remind me when Nfelia is closed?',
             'Yes, once you turn on phone notifications in Set up reminders: your phone buzzes at the reminder time even with Nfelia closed. On iPhone, add Nfelia to your Home Screen first (tap Share, then Add to Home Screen). Email reminders work on any phone. See ' . $link('help/install-the-app', 'how to install the app') . '.'],
            ['Do I have to type in my deliveries and payments?',
             'No. A delivery date on an invoice or order, an invoice’s due date, a customer’s promise to pay, a quote’s end date, a new shop order and low stock all show up by themselves, and clear when they are done.'],
            ['What is the morning plan?',
             'On days with something planned, Nfelia sends you the day at 7am Nigeria time (6am in Ghana, 9am in Kenya), by email and on your phone: appointments, tasks, deliveries and money due. Days with nothing on send nothing. Switch either off in Set up reminders.'],
            ['Can I give tasks to my staff?',
             'Yes. Pick who is doing it and they are told straight away. Staff see the plan and their own work, but not invoices due or debts.'],
            ['Can a task repeat?',
             'Yes: every day, every week or every month. A monthly task keeps its day, so the 31st comes back on the last day of a shorter month.'],
            ['What does it cost?',
             $month !== null
                ? 'Tasks and the calendar are part of Nfelia: ' . e($month) . ' a month in Nigeria, with your website, invoices and receipts included. Free for ' . $trial . ' days, no card needed.'
                : 'Tasks and the calendar are part of Nfelia. Ask us on WhatsApp for the price where you are.'],
        ]) ?>
    </div>
</section>

<?= site_related(['help/tasks-and-reminders', 'features/bookings', 'features/orders']) ?>
<?= site_cta('Let Nfelia remember the dates for you') ?>
