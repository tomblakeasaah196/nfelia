<?php
/**
 * Help: staff and team seats. Checked against views/settings/index.php
 * (the Team tab), SettingsController (add_staff, remove_staff,
 * send_staff_reset, update_role), billing_seats() and TRIAL_STAFF_SEATS in
 * app/helpers/billing.php, nav_groups() in app/helpers/nav.php, require_boss()
 * and the pages that call it, RecordsController and PurgeController (owner
 * only). Prices from site_prices().
 */
require_once BASE_PATH . '/views/home/_icons.php';   // nf_icon() for the building blocks: the layout loads it only after the view
$u      = fn(string $key) => e(site_url($key));
$prices = site_prices($page['country']);
$seat   = $prices ? $prices['money']((float)$prices['seat']) : null;
$staff_most = MAX_SEATS - 1;
$trial_staff = TRIAL_STAFF_SEATS . ' staff member' . (TRIAL_STAFF_SEATS === 1 ? '' : 's');
$toc = [
    'roles'     => 'Staff or admin: who sees what',
    'seats'     => 'Seats and what they cost',
    'add'       => 'Add someone to your team',
    'sign-in'   => 'How they sign in',
    'change'    => 'Change a role, reset a password or remove someone',
    'problems'  => 'Common problems and fixes',
];
?>
<article class="wrap article-wrap has-aside">
    <header class="article-head">
        <p class="kicker">Help centre</p>
        <h1>Add staff to Nfelia and decide what they can see</h1>
        <p class="page-lead">Open Settings, then Team, and fill in Add team member: their name, email, a password and a role. Choose Staff for day-to-day work, or Admin for someone you trust with money too. Each person gets their own login, and only you decide who is on the team.</p>
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
        <p class="note"><strong>Who can do this</strong> Only the owner manages the team. Admins and staff do not see the Team tab.</p>

        <h2 id="roles">Staff or admin: who sees what</h2>
        <div class="table-scroll">
            <table>
                <caption class="sr-only">What the owner, admins and staff can open in Nfelia</caption>
                <thead>
                    <tr><th scope="col">In Nfelia</th><th scope="col">Staff</th><th scope="col">Admin</th><th scope="col">Owner</th></tr>
                </thead>
                <tbody>
                    <tr><td>Orders, quotes, invoices and receipts, recording payments</td><td>Yes</td><td>Yes</td><td>Yes</td></tr>
                    <tr><td>Catalogue and stock, clients, suppliers, deliveries</td><td>Yes</td><td>Yes</td><td>Yes</td></tr>
                    <tr><td>Tasks and calendar, bookings</td><td>Yes</td><td>Yes</td><td>Yes</td></tr>
                    <tr><td>This month's money on Home, and money due in Tasks</td><td>No</td><td>Yes</td><td>Yes</td></tr>
                    <tr><td>Debts, Expenses, Profit and loss</td><td>No</td><td>Yes</td><td>Yes</td></tr>
                    <tr><td>Refunds, cancelling orders, changing an order's items or dates</td><td>No</td><td>Yes</td><td>Yes</td></tr>
                    <tr><td>Shop Studio, News and offers, signing for the business</td><td>No</td><td>Yes</td><td>Yes</td></tr>
                    <tr><td>Business details, payment accounts, Team, Billing</td><td>No</td><td>No</td><td>Yes</td></tr>
                    <tr><td>Your records (the Records Book) and Purge data</td><td>No</td><td>No</td><td>Yes</td></tr>
                </tbody>
            </table>
        </div>
        <p>Staff who type in the address of a page they cannot open are sent back to Home with a short message. Every sale they make still takes stock off, so missing goods show up in your stock.</p>

        <h2 id="seats">Seats and what they cost</h2>
        <ul>
            <li><strong>During your free trial</strong>, your team can include <?= e($trial_staff) ?> besides you, at no cost.</li>
            <?php if ($seat): ?>
            <li><strong>After the trial</strong>, each person besides you is a seat, at <?= e($seat) ?> a month each in <?= e($country['name']) ?>, paid with your month.</li>
            <?php else: ?>
            <li><strong>After the trial</strong>, each person besides you is a seat, paid each month with your plan. The Billing page shows the price in your country.</li>
            <?php endif; ?>
            <li><strong>Up to <?= (int)MAX_SEATS ?> people</strong> in all: you and <?= (int)$staff_most ?> staff. Need more? Email <?= e(site_org()['support']) ?>.</li>
            <li>The Team tab shows <strong>Team seats</strong>, such as "2 of 3 seats used". When every seat is taken, an <strong>Add seats</strong> link takes you to Billing.</li>
        </ul>
        <p>How to pay for seats, now or with your next month, is in <a href="<?= $u('help/billing') ?>">billing and payments</a>. Prices for every country are on the <a href="<?= $u('pricing') ?>">pricing page</a>.</p>

        <h2 id="add">Add someone to your team</h2>
        <?= site_steps([
            ['Open the Team tab', 'On a phone, tap <strong>More</strong>, then <strong>Settings</strong>, then <strong>Team</strong>. On a computer, click your picture or initial at the top right, then <strong>Settings</strong>, then <strong>Team</strong>.'],
            ['Fill in Add team member', 'Type their <strong>Full name</strong> and <strong>Email</strong>. Each person needs their own email address: one that is not already on another Nfelia business.'],
            ['Choose the role', '<strong>Staff — Day-to-day work</strong>, or <strong>Admin — Also money, refunds and the shop</strong>. You can change it later.'],
            ['Set a password', 'At least ' . (int)PASSWORD_MIN . ' characters, with a capital letter, a number and a symbol. The box shows which rules are met as you type. A password known from a data breach is refused, so choose a fresh one.'],
            ['Tap Add member', 'You see "[their name] added to your team. We emailed them how to sign in." They appear under <strong>Team members</strong> with their role.'],
        ]) ?>

        <h2 id="sign-in">How they sign in</h2>
        <p>Nfelia emails the new person that they have joined your business, with a <strong>Sign in to Nfelia</strong> button. The email never contains the password: tell them yourself, in person or on a call.</p>
        <p>They sign in at <a href="<?= e(APP_URL) ?>/login">nfelia.com/login</a> with their email and that password, or tap <strong>Email me a sign-in link</strong> to get in without it. Once in, they can change their password under Settings, then Profile, and turn on phone alerts for their own tasks. Show them <a href="<?= $u('help/install-the-app') ?>">how to install the app</a> on their phone.</p>

        <h2 id="change">Change a role, reset a password or remove someone</h2>
        <p>Everything is on the Team tab, beside each person under <strong>Team members</strong>:</p>
        <ul>
            <li><strong>Change a role</strong>: pick Admin or Staff from the menu beside their name. It changes at once.</li>
            <li><strong>Forgotten password</strong>: tap <strong>Send reset link</strong>. Nfelia emails them a link to set a new one. You never see or choose their new password.</li>
            <li><strong>Someone leaves</strong>: tap <strong>Remove</strong> and confirm. They are signed out everywhere, their phones stop getting your alerts, and their open tasks move to your list. Their sales and records stay in Nfelia.</li>
        </ul>
        <p>Removed someone by mistake? Add them again with the same email: they come back with the same login and a new password.</p>

        <h2 id="problems">Common problems and fixes</h2>
        <?= site_faq([
            ['It says "This email is already registered". What now?',
             'That email belongs to someone on another Nfelia business. Ask them to use a different email for your team, such as a new one made for work.'],
            ['I can\'t see the Team tab.',
             'Only the owner sees Team and Billing. If you are an admin, ask the owner to add the person.'],
            ['It says my free trial includes only ' . e($trial_staff) . '.',
             'During the trial you can add ' . e($trial_staff) . '. To add more now, pay for a month with more seats in Billing.'],
            ['Every seat is taken. How do I add one more?',
             'In Billing, use Add seats now for the month you are in, or choose more seats when you pay for your next month.'],
            ['My staff can\'t see Debts or Expenses.',
             'That is how the Staff role works. Change them to Admin on the Team tab if they need money pages, or keep them as Staff.'],
        ]) ?>
    </div>
</article>
<?= site_related(['help/billing', 'help/getting-started', 'help/tasks-and-reminders', 'pricing', 'help/install-the-app', 'faq']) ?>
<?= site_cta('Bring your team onto Nfelia') ?>
