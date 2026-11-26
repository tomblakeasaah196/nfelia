<?php
/**
 * Help: tasks, the calendar and reminders. Checked against views/tasks,
 * assets/js/planner.js (sections, date choices), TasksController,
 * app/helpers/planner.php (reminder times, automatic items, job plans,
 * who sees what), cron/planner.php (reminders, the morning plan from
 * 07:00), NOTIFY_TOPICS in app/helpers/notify.php and the Notifications
 * sheet (views/partials/notify_sheet.php).
 */
require_once BASE_PATH . '/views/home/_icons.php';   // nf_icon() for the building blocks: the layout loads it only after the view
$u = fn(string $key) => e(site_url($key));
$toc = [
    'list'       => 'What is on your Tasks list',
    'add'        => 'Add a task or an appointment',
    'remind'     => 'Get reminded',
    'morning'    => 'Your day each morning',
    'job'        => 'Plan a job step by step',
    'team'       => 'Give tasks to your team',
    'calendar'   => 'Use the calendar',
    'problems'   => 'Common problems and fixes',
];
$remind_timed   = planner_remind_options(true);
$remind_untimed = planner_remind_options(false);
?>
<article class="wrap article-wrap has-aside">
    <header class="article-head">
        <p class="kicker">Help centre</p>
        <h1>Plan your day with tasks, the calendar and reminders</h1>
        <p class="page-lead">Tap Tasks at the bottom of the screen to see what is late, what is due today and what is coming up. Add a task or an appointment with a reminder, and Nfelia tells you on your phone and by email at the time you choose. On days with something planned, your day arrives early each morning.</p>
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
        <h2 id="list">What is on your Tasks list</h2>
        <p>The page is called <strong>Tasks &amp; Calendar</strong>, with two tabs: <strong>Tasks</strong> and <strong>Calendar</strong>. The Tasks list is in sections: <strong>Late</strong>, <strong>Today</strong>, <strong>Coming up</strong> (the next seven days), <strong>No date</strong> and <strong>Done today</strong>.</p>
        <p>Your own tasks sit beside things Nfelia already knows, so you do not have to type them twice:</p>
        <ul>
            <li>deliveries and pickups promised on orders, with a countdown,</li>
            <li>delivery notes with a planned date,</li>
            <li>money customers promised to pay, on its due day, with a WhatsApp button to remind them (the owner and admins see these),</li>
            <li>quotes to follow up three days after you send them, or the day before they run out,</li>
            <li>website orders waiting for you to confirm them,</li>
            <li>items to restock.</li>
        </ul>
        <p>These clear by themselves when the money comes in or the goods go out.</p>
        <?= site_screens([
            ['tasks-1', 'Tasks in Nfelia: a job with 2 days left, payments and a delivery that are late, and deliveries due today', 'Today, and what is late'],
            ['tasks-2', 'The calendar for October, with appointments, deliveries and money due marked on each day', 'Your month'],
        ]) ?>
        <p>The screens are from Ivie Luxe Hair, an example business.</p>

        <h2 id="add">Add a task or an appointment</h2>
        <?= site_steps([
            ['Type it in Add a task', 'At the top of Tasks, type what needs doing, for example "Buy 3 bundles from the supplier". Under <strong>When?</strong>, tap <strong>Today</strong>, <strong>Tomorrow</strong>, <strong>Pick a date</strong> or <strong>No date</strong>, then <strong>Add</strong>. It appears in its section at once.'],
            ['Add details when you need them', 'Tap <strong>Add details</strong> instead of Add. Choose <strong>Task</strong> or <strong>Appointment</strong>, set a time, link a <strong>Customer</strong>, choose <strong>Remind me</strong>, <strong>Repeat</strong> (every day, week or month), <strong>Who is doing it?</strong> and <strong>Notes</strong>. Tap the star for something important.'],
            ['Save it', 'Tap <strong>Add task</strong> or <strong>Add appointment</strong>. Appointments show on the calendar in your colour, and on your website no customer can book that time.'],
            ['Tick it off when it is done', 'Tap the circle beside it. Nfelia asks whether you have completed it; tap <strong>Yes, it\'s done</strong>. It moves to <strong>Done today</strong>, and <strong>Undo</strong> shows for a moment in case you tapped the wrong one.'],
        ]) ?>
        <p>For an appointment straight away, tap <strong>Book appointment</strong>. You can also add a task from anywhere with the <strong>+</strong> button at the bottom of the screen, then <strong>Add task</strong>.</p>

        <h2 id="remind">Get reminded</h2>
        <p>Choose <strong>Remind me</strong> on a task or appointment:</p>
        <div class="table-scroll">
            <table>
                <caption class="sr-only">The reminder times Nfelia offers for tasks with and without a time</caption>
                <thead>
                    <tr><th scope="col">The task has</th><th scope="col">You can be reminded</th></tr>
                </thead>
                <tbody>
                    <tr><td>A time</td><td><?= e(implode(', ', $remind_timed)) ?></td></tr>
                    <tr><td>A date but no time</td><td><?= e(implode(', ', $remind_untimed)) ?></td></tr>
                </tbody>
            </table>
        </div>
        <p class="note"><strong>In Ghana or Kenya</strong> Reminder times follow Nigeria time (WAT) for now. In Kenya they come two hours late, so set them two hours earlier; in Ghana they come an hour early, so set them an hour later.</p>
        <p>The bell at the top of Nfelia always shows what is due. To choose how else it reaches you, tap <strong>Set up reminders</strong> at the top of Tasks:</p>
        <?= site_steps([
            ['Turn on alerts for this phone', 'Under <strong>Phone notifications</strong>, tap <strong>Turn on for this phone</strong> and allow notifications when your phone asks. Tap <strong>Send me a test</strong> to check it buzzes.'],
            ['Choose phone, email or both', 'Switch <strong>Reminders on your phone</strong> and <strong>Reminder emails</strong> on or off.'],
            ['Do it on each phone you use', 'Alerts are set per phone. Your reminders then reach every phone you turned on, even with Nfelia closed.'],
        ]) ?>
        <p class="note"><strong>On an iPhone</strong> Phone alerts need Nfelia on your Home Screen. In Safari, tap Share, then <strong>Add to Home Screen</strong>, open Nfelia from there, and turn alerts on. See <a href="<?= $u('help/install-the-app') ?>">how to install the app</a>.</p>
        <p>Quiet at night? Open <strong>More</strong>, then <strong>Notifications</strong>, and switch on <strong>Quiet hours</strong> with the times. Your phone stays silent then, and alerts wait in the bell.</p>

        <h2 id="morning">Your day each morning</h2>
        <p>On mornings with something planned, Nfelia sends you your day at 7am Nigeria time (6am in Ghana, 9am in Kenya): late items, today's appointments, tasks and deliveries, deliveries in the next two days, and money due from customers. A day with nothing planned sends nothing.</p>
        <p>It comes by email and to your phone. Switch either off under <strong>Morning plan</strong> in <strong>Set up reminders</strong>, or with the link at the foot of the email. Each person on your team gets their own day: staff see their tasks, without the money.</p>

        <h2 id="job">Plan a job step by step</h2>
        <p>For work with several steps and a deadline, such as a dress, a party or a big order, let Nfelia lay out the steps for you.</p>
        <?= site_steps([
            ['Tap Plan a job', 'Choose a ready-made plan: <strong>Sewing job</strong>, <strong>Customer order</strong>, <strong>Event</strong> or <strong>Supplier order</strong>, or <strong>My own steps</strong> to start blank.'],
            ['Add the customer and the finish date', 'Pick the <strong>Customer</strong> and, if you like, the <strong>Invoice</strong>. Set the finish date, such as <strong>Deliver by</strong> for a sewing job. Nfelia spaces the steps before it, so a fitting falls a few days before delivery.'],
            ['Check the steps', 'Rename, remove or <strong>Add a step</strong>. Choose <strong>Who is doing it?</strong> for the whole job.'],
            ['Tap Save job plan', 'The job shows under <strong>Jobs in progress</strong> with how many steps are done and a countdown. Each step appears on Tasks on its own day.'],
        ]) ?>

        <h2 id="team">Give tasks to your team</h2>
        <p>Under <strong>Who is doing it?</strong>, pick a person. They get an alert that you gave them a task, and you hear when they finish it. When you have staff, <strong>Everyone</strong> and <strong>Mine</strong> at the top of Tasks switch between the whole team's list and your own.</p>
        <p>Staff can change the tasks they added or were given; the owner and admins can change all of them. If you remove someone from your team, their open tasks come to you. Read <a href="<?= $u('help/staff') ?>">how to add staff</a>.</p>

        <h2 id="calendar">Use the calendar</h2>
        <p>Tap <strong>Calendar</strong> for the month. Each day shows marks for what is on it: appointments, deliveries and money due. Tap a day to see its list, and <strong>Add to this day</strong> to put something on it. Move between months with the arrows, and tap <strong>Today</strong> to come back.</p>

        <h2 id="problems">Common problems and fixes</h2>
        <?= site_faq([
            ['My phone does not buzz for reminders.',
             'Open Set up reminders on Tasks. If it says notifications are blocked, allow them for Nfelia in your browser\'s site settings and turn them on again. On iPhone, open Nfelia from your Home Screen first. Check Quiet hours too.'],
            ['I didn\'t get the morning email.',
             'It only comes on days with something planned. Check Morning plan by email is on, and look in your spam or promotions folder. The email goes to the address on your profile.'],
            ['Why can\'t I tick off a delivery?',
             'You can: ticking a delivery asks if the order was delivered, and Yes marks it delivered. If you would rather move it, open the order and change the delivery day.'],
            ['A money due item will not go away.',
             'It clears when the customer pays. Record the payment on the order, or tick it to hide it if you have agreed something else.'],
            ['My staff don\'t see money due in Tasks.',
             'That is on purpose: money due is for the owner and admins. Staff see their tasks, deliveries, quotes to follow up, orders to confirm and restocking.'],
        ]) ?>
    </div>
</article>
<?= site_related(['features/tasks', 'help/install-the-app', 'help/deliveries', 'help/bookings', 'for/fashion-designers', 'for/events']) ?>
<?= site_cta('Never miss a delivery date again') ?>
