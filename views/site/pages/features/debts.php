<?php
/**
 * Feature: the debt tracker. Checked against DebtController, views/debts,
 * app/helpers/orders.php (a balance on an order is a debt from the moment it
 * exists), planner.php (money due on Tasks, with a WhatsApp reminder ready),
 * cron/planner.php (overdue invoices, once a week) and invoices.php (the
 * reminder message).
 */
require_once BASE_PATH . '/views/home/_icons.php';   // nf_icon(): the layout loads it only after the view

$fd_a = fn(string $key, string $text): string => site_page($key) ? '<a href="' . e(site_url($key)) . '">' . e($text) . '</a>' : e($text);

echo site_hero([
    'kicker'    => 'Debt tracker',
    'h1'        => 'Know who owes you, and get your money back',
    'lead_html' => 'Nfelia is a debt tracker for small businesses. Every unpaid balance lands in one list: who owes you, how much, since when and how many days late. A polite WhatsApp reminder, with the amount already written, goes out in one tap.',
    'screens'   => [
        ['owed-1', 'Sales screen: ₦185,000 owed to you on 5 unpaid invoices, 2 of them overdue', 'Who owes you'],
        ['owed-2', 'A balance of ₦60,000, 6 days overdue, with a green Send reminder button', 'One-tap reminder'],
    ],
]);
?>

<section class="page-section" aria-labelledby="fd-book"><div class="wrap split">
    <div class="split-copy">
        <h2 id="fd-book">A customer debt book that keeps itself</h2>
        <p>The old way is a notebook: “Mama Bisi 15,000, next week.” Then the page gets lost, or the amount gets crossed out twice and nobody is sure. In Nfelia you don’t write debts down. They arrive by themselves:</p>
        <?= site_ticks([
            ['From every sale with a balance', 'choose Part now or Pay later on an order, and the balance is in Debts at once, as “Balance on order …”, due on the pay-by date you picked.'],
            ['From overdue invoices', 'an invoice past its date with money still owed shows here too.'],
            ['Typed in by hand', 'for credit you gave outside an order: tap Add a debt, pick the customer, say what it is for, the amount and when they promised to pay.'],
        ]) ?>
        <p>At the top you see the total <strong>Owed to me</strong>, how many debts are open and how many are late. Search by name, order or note, and sort by newest, due soonest or most owed.</p>
        <p>The screens on this page are from Ivie Luxe Hair, an example business in Lekki.</p>
    </div>
    <div class="ledger" aria-hidden="true">
        <p class="title">Who owes me</p>
        <p>Mama Bisi: 15,000 <span class="red">(next week)</span></p>
        <p><s>Chidi, shop 4: 40,000</s> 25,000??</p>
        <p>Lady with red car: 18k</p>
        <p><s>Tunde: 9,500</s> <span class="red">paid? check</span></p>
        <p>Transfer screenshot???</p>
        <span class="ring"></span>
    </div>
</div></section>

<section class="page-section rose" aria-labelledby="fd-remind"><div class="wrap split flip">
    <div class="split-copy">
        <h2 id="fd-remind">Reminders that sound like you, not like a debt collector</h2>
        <p>Chasing money is awkward. Nfelia does the remembering and writes the message, and you decide when to send it. Open a debt and tap <strong>Send a reminder</strong>: WhatsApp opens with something like this, ready to go:</p>
        <div class="prose"><blockquote>“Hello Chioma, a friendly reminder from Ivie Luxe Hair: the balance on your order is ₦60,000, due Mon 12 Oct. It is 6 days late. Thank you.”</blockquote></div>
        <p>Nothing reaches your customer until you press send in WhatsApp. And you don’t have to remember to look:</p>
        <?= site_ticks([
            ['On the day it is due', 'it shows on your Tasks with a Remind button, like Remind Chioma, that opens WhatsApp with a reminder written.'],
            ['Every morning', 'money due today and money that is late are in your morning plan.'],
            ['When an invoice goes overdue', 'you hear about it on your phone or by email, once a week, for up to eight weeks.'],
        ]) ?>
        <p>For words that work, see <?= $fd_a('blog/collect-money-customers-owe', 'how to collect money customers owe you') ?>.</p>
    </div>
    <div class="split-copy">
        <h2 id="fd-pay">Record a payment, and everything follows</h2>
        <p>When they pay, open the debt and record it: <strong>Full balance</strong>, <strong>Half</strong>, or any amount, with how they paid. The balance drops at once, the customer’s total updates, and for a balance on an order the payment gets <?= $fd_a('features/receipts', 'its own receipt') ?>.</p>
        <div class="compare table-scroll">
            <table>
                <caption class="sr-only">What each debt status means in Nfelia</caption>
                <thead><tr><th scope="col">You see</th><th scope="col">It means</th></tr></thead>
                <tbody>
                    <tr><th scope="row">Not paid</th><td>Nothing paid yet, and not late.</td></tr>
                    <tr><th scope="row">Part paid</th><td>Some of it is in. The rest still shows.</td></tr>
                    <tr><th scope="row">Late</th><td>The promised date has passed, with the days counted.</td></tr>
                    <tr><th scope="row">Paid off</th><td>Cleared. It leaves your open list.</td></tr>
                    <tr><th scope="row">Written off</th><td>You decided not to chase it. It stays off and never comes back as a new debt.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div></section>

<section class="page-section tint" aria-labelledby="fd-owe"><div class="wrap split">
    <div class="split-copy">
        <h2 id="fd-owe">And what you owe, too</h2>
        <p>Debts has two sides. <strong>Owed to me</strong> is your customers. <strong>I owe</strong> is your suppliers: an expense you have not fully paid, with a supplier on it, shows there by itself, and you can add any other debt by hand. Pay it off and the expense shows paid too. See <?= $fd_a('features/profit', 'expenses and profit') ?>.</p>
    </div>
    <div class="split-copy">
        <h2 id="fd-private">Private to you</h2>
        <p>Who owes you is between you and your customer. Only the owner and admins can open Debts. Staff can still sell and record payments on orders, but they don’t see the list.</p>
    </div>
</div></section>

<section class="page-section" aria-labelledby="fd-who"><div class="wrap">
    <?= site_head('Who it helps', 'Anyone who sells on credit, or takes a deposit and waits for the rest.', 'fd-who') ?>
    <ul class="fits">
        <li><strong><?= $fd_a('for/fashion-designers', 'Fashion designers') ?></strong><span>Deposit at measurement, balance at pickup, and a reminder on pickup day if it is still open.</span></li>
        <li><strong><?= $fd_a('for/events', 'Event planners') ?></strong><span>50% before, the rest by the event date, tracked until the day.</span></li>
        <li><strong><?= $fd_a('for/food-and-catering', 'Caterers') ?></strong><span>Party balances that don’t get forgotten after the party.</span></li>
        <li><strong><?= $fd_a('for/phones-and-gadgets', 'Phone and gadget shops') ?></strong><span>Phones paid in parts, each part with its receipt.</span></li>
        <li><strong><?= $fd_a('for/online-sellers', 'Online sellers') ?></strong><span>“I go pay you next week” customers, written down for you.</span></li>
    </ul>
</div></section>

<section class="page-section tint" aria-labelledby="faq-title"><div class="wrap">
    <?= site_head('Questions people ask', '', 'faq-title', true) ?>
    <?= site_faq([
        ['Does Nfelia message my customers by itself?',
         'No. Nfelia writes the reminder and opens WhatsApp with it, but you press send. Your customer only hears from you, in your own chat.'],
        ['Do I have to type every debt in?',
         'No. A balance on any order goes into Debts by itself, and so does an overdue invoice. You only type in credit you gave outside an order.'],
        ['What if a customer pays in bits?',
         'Record each amount as it comes. The balance goes down each time, the debt shows Part paid, and it clears itself when the last part is in.'],
        ['What happens to a debt I will never collect?',
         'Write it off. It leaves your open list and your totals, and it never comes back as a new debt. A debt you typed in by hand with no payments on it can also be deleted.'],
        ['Can my staff see who owes me?',
         'No. Only the owner and admins can open Debts. Staff can still take payments on orders.'],
        ['Can I track money I owe suppliers?',
         'Yes. The I owe side lists unpaid supplier expenses and any debt you add by hand, with dates, so you pay on time too.'],
    ]) ?>
</div></section>

<?= site_related(['help/debt-reminders', 'blog/collect-money-customers-owe', 'features/invoices', 'features/receipts', 'features/tasks', 'features/profit']) ?>
<?= site_cta('Get your money back with Nfelia') ?>
