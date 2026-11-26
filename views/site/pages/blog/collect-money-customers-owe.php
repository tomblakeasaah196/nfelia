<?php
/**
 * Guide: collecting money customers owe (/blog/collect-money-customers-owe).
 * Court limits checked on 28 October 2026: Lagos State Small Claims Court,
 * ₦5,000,000 (Practice Direction on Small Claims); Kenya's Small Claims
 * Court, KSh 1,000,000 (Small Claims Court Act, s.12(3)).
 *
 * From site_render: $page, $country.
 */
require_once BASE_PATH . '/views/home/_icons.php';   // nf_icon(): the layout loads it only after the view

$sections = [
    'before'      => 'Stop debts before they start',
    'one-list'    => 'Keep one list of who owes you',
    'when'        => 'When to send each reminder',
    'messages'    => '8 reminder messages to copy',
    'tone'        => 'Polite, clear and easy to pay',
    'stop-credit' => 'When to stop giving credit',
    'last-steps'  => 'If they still do not pay',
    'nfelia'      => 'How Nfelia helps',
];
$h2   = fn(string $id) => '<h2 id="' . e($id) . '">' . e($sections[$id]) . '</h2>';
$link = fn(string $key, string $text) => site_page($key) ? '<a href="' . e(site_url($key)) . '">' . e($text) . '</a>' : e($text);
?>
<article class="wrap article-wrap has-aside">
    <header class="article-head">
        <p class="kicker">Guide</p>
        <h1><?= e($page['og']['title']) ?></h1>
        <p class="page-lead">Most customers who owe you are not thieves. They are busy, short of cash or hoping you forget. A clear record, a reminder plan and the right words get most of that money back, and keep the customer.</p>
        <?= site_dates($page) ?>
    </header>
    <aside class="article-aside">
        <nav class="toc" aria-labelledby="toc-title">
            <h2 id="toc-title">On this page</h2>
            <ol>
                <?php foreach ($sections as $id => $title): ?>
                <li><a href="#<?= e($id) ?>"><?= e($title) ?></a></li>
                <?php endforeach; ?>
            </ol>
        </nav>
    </aside>

    <div class="prose">
        <p>To collect money customers owe you, write every debt down the day it starts, agree a real date for payment, and remind them on a plan: a friendly note before the date, a clear reminder on the day, then firmer messages as the days pass. Keep every message short, give the exact amount, and make paying easy. Below are eight WhatsApp messages you can copy, from polite to firm, and what to do when messages stop working.</p>

        <?= $h2('before') ?>
        <p>The easiest debt to collect is the one you never let grow.</p>
        <ul>
            <li><strong>Write it down the same day.</strong> Who, what they took, how much and when they will pay. A debt that lives only in your head becomes an argument later.</li>
            <li><strong>Agree a date, not a feeling.</strong> “Next week” is not a date. “Friday 7 November” is.</li>
            <li><strong>Take a deposit on custom work.</strong> If you buy fabric, hair or food for one customer’s order, ask for at least the cost of the materials before you start.</li>
            <li><strong>Send an invoice, even to people you know.</strong> It turns a favour into an agreement. Our guide on <?= $link('blog/how-to-write-an-invoice', 'how to write an invoice') ?> shows what to put on it.</li>
            <li><strong>Start new customers on full payment.</strong> Give credit to people who have bought from you before and paid on time.</li>
        </ul>

        <?= $h2('one-list') ?>
        <p>Keep every debt in one place: a notebook page, a spreadsheet or an app. Not in your head, and not spread across chats. For each one, write down the same few things. Here is what a list looks like, with example customers:</p>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr><th scope="col">Customer</th><th scope="col">What for</th><th scope="col">Owed</th><th scope="col">Due</th><th scope="col">Paid so far</th><th scope="col">Balance</th></tr>
                </thead>
                <tbody>
                    <tr><th scope="row">Mama Bisi</th><td>3 bundles of hair</td><td>₦45,000</td><td>31 Oct</td><td>₦30,000</td><td>₦15,000</td></tr>
                    <tr><th scope="row">Chidi, shop 4</th><td>Phone screen repair</td><td>₦25,000</td><td>25 Oct</td><td>₦0</td><td>₦25,000</td></tr>
                    <tr><th scope="row">Grace O.</th><td>Office lunch, 20 packs</td><td>₦60,000</td><td>7 Nov</td><td>₦0</td><td>₦60,000</td></tr>
                </tbody>
            </table>
        </div>
        <p>Look at the list every morning. A list you check every day gets paid; a list you check once a month gets forgotten. Record part payments the moment they come in, so the balance is always right. Nothing annoys a customer more than being asked for money they have already paid.</p>

        <?= $h2('when') ?>
        <p>A plan takes the emotion out of it. You are not chasing anyone; you are following your routine. Here is a plan that works for most small businesses:</p>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr><th scope="col">When</th><th scope="col">What to send</th><th scope="col">Tone</th></tr>
                </thead>
                <tbody>
                    <tr><th scope="row">2 or 3 days before the due date</th><td>A friendly heads-up (message 1)</td><td>Warm</td></tr>
                    <tr><th scope="row">On the due date</th><td>The amount and how to pay (message 2)</td><td>Polite</td></tr>
                    <tr><th scope="row">3 days late</th><td>Ask when they will pay (message 3)</td><td>Polite, direct</td></tr>
                    <tr><th scope="row">7 days late</th><td>Ask for a date, even for part of it (message 4)</td><td>Firm</td></tr>
                    <tr><th scope="row">14 days late</th><td>Call, then offer a payment plan in writing (message 5)</td><td>Firm, helpful</td></tr>
                    <tr><th scope="row">21 days late</th><td>New orders only with full payment (message 7)</td><td>Firm</td></tr>
                    <tr><th scope="row">30 days late</th><td>A final notice with a deadline (message 8)</td><td>Formal</td></tr>
                </tbody>
            </table>
        </div>
        <p>Send reminders in the morning on a working day, when people are planning their day and checking their bank or mobile money app. Message 6 is for any day a part payment arrives.</p>

        <?= $h2('messages') ?>
        <p>Copy these into WhatsApp and fill in the parts in square brackets. Each one says the amount, what it was for and how to pay, so the customer never has to ask.</p>

        <h3>1. Before the due date</h3>
        <p class="template">Hello [Name], I hope you are well. A quick reminder that the balance of [amount] for [what they bought] is due on [date].
You can pay to [account details].
Thank you, [your name], [business name]</p>

        <h3>2. On the due date</h3>
        <p class="template">Good morning [Name]. The balance of [amount] for [what they bought] is due today.
Here are the payment details again: [account details].
Please send me a message once you have paid, so I can send your receipt. Thank you.</p>

        <h3>3. A few days late</h3>
        <p class="template">Hello [Name], I have not yet received the [amount] that was due on [date]. Maybe it slipped your mind.
Can you let me know today when you will pay?
Payment details: [account details]. Thank you.</p>

        <h3>4. When they have gone quiet</h3>
        <p class="template">Hello [Name], I have sent a few messages about the [amount] for [what they bought], due on [date], and I have not heard back.
Please reply today with the date you will pay, even if you can only pay part of it now.</p>

        <h3>5. Offering a payment plan</h3>
        <p class="template">Hello [Name], I understand things may be tight. To make it easier, you can pay the [amount] in two parts: [first amount] by [date] and [second amount] by [date].
Please reply to confirm, and I will note it down.</p>

        <h3>6. After a part payment</h3>
        <p class="template">Thank you, [Name]. I have received [amount paid].
The balance is now [balance], due on [date].
Here is your receipt: [receipt link]</p>

        <h3>7. Firm: new orders need full payment</h3>
        <p class="template">Hello [Name], the [amount] for [what they bought] is now [number] days late.
Until it is paid, I can only take new orders from you with full payment in advance.
Please pay to [account details], or call me today to agree a date.</p>

        <h3>8. Final notice</h3>
        <p class="template">Dear [Name], this is my final reminder about the [amount] for [what they bought], due on [date] and now [number] days late.
If it is not paid, or we have not agreed a plan, by [deadline], I will take further steps to recover it.
I would much rather settle this between us. Please call or reply today.</p>

        <p>When the last of the money comes in, close the loop: say thank you and send the receipt. People remember how you treated them when they were late.</p>

        <?= $h2('tone') ?>
        <p>How you ask matters as much as when.</p>
        <ul>
            <li><strong>Use your business number and name,</strong> so the message reads as business, not a personal favour.</li>
            <li><strong>Always say the exact amount,</strong> what it was for and the due date. Vague messages get vague replies.</li>
            <li><strong>Put the payment details in every message:</strong> your account name and number, your mobile money number, or your M-Pesa till or paybill. Do not make them scroll up or ask again.</li>
            <li><strong>Accept part payments and say thank you for each one.</strong> A customer who pays something intends to pay the rest.</li>
            <li><strong>Call when messages stop working.</strong> A voice is harder to ignore, and two minutes on the phone can tell you more than ten messages.</li>
            <li><strong>Never post a customer’s debt</strong> on your Status, in a group or on Instagram. It shames them in front of people they know, it rarely gets you paid, and it can turn a small debt into a bigger dispute.</li>
            <li><strong>Keep it in writing.</strong> If you agree a new date on a call, send a short message after it: “Thanks for the call. As agreed, [amount] by [date].”</li>
        </ul>

        <?= $h2('stop-credit') ?>
        <p>Credit is a tool, not something you owe everyone. Set simple rules and keep them:</p>
        <ul>
            <li><strong>One open debt per customer.</strong> A new order waits until the last one is paid.</li>
            <li><strong>A limit for each customer,</strong> such as the value of one normal order.</li>
            <li><strong>New customers pay in full</strong> until they have paid on time at least once.</li>
            <li><strong>After a late payment, the next order needs a deposit.</strong></li>
        </ul>
        <p>Tell customers the rule before you need it. “We give credit to regular customers up to [amount], for up to [number] days” is easy to say and hard to argue with.</p>

        <?= $h2('last-steps') ?>
        <p>Most debts are paid somewhere between message 3 and message 6. For the few that are not:</p>
        <ol>
            <li><strong>Visit or call, calmly.</strong> Ask what is going on and agree a plan, even a small weekly payment.</li>
            <li><strong>Write a formal letter of demand.</strong> Say what is owed and for what, the dates, what you have done to collect it, and the date by which they must pay. Keep a copy.</li>
            <li><strong>Think about a small claims court.</strong> In Lagos State, the Small Claims Court, part of the Magistrates’ Courts, hears money claims of up to ₦5 million. In Kenya, the Small Claims Court hears claims of up to KSh 1 million under the Small Claims Court Act. Ask the court registry or a lawyer how to file.</li>
            <li><strong>Know when to let go.</strong> If the amount is small and the customer has disappeared, the time and stress may cost more than the debt. Write it off in your records so your figures stay true, and do not give that customer credit again.</li>
        </ol>
        <p>This is general information, not legal advice. For a large amount, speak to a lawyer.</p>

        <?= $h2('nfelia') ?>
        <p>Nfelia is a debt tracker built into your sales, on your phone. It keeps the list and does the typing for you:</p>
        <ul>
            <li>Your Debts list shows who owes you, how much and who is late. What you owe suppliers sits on its own tab.</li>
            <li>An invoice that passes its due date goes on the list by itself. You can add other debts by hand.</li>
            <li>Send a reminder opens WhatsApp with the message written: the customer’s name, the balance and how many days late it is.</li>
            <li>Record part payments as they come in. The balance updates, and the invoice shows what is left to pay.</li>
            <li>Money due shows in your tasks and calendar, with a button to remind the customer, and Nfelia can alert you on your phone when an invoice becomes overdue.</li>
            <li>A debt you give up on can be written off, so your figures stay true.</li>
        </ul>
        <?= site_screens([
            ['owed-1', 'Sales screen: ₦185,000 owed to you on 5 unpaid invoices, 2 of them overdue', 'Who owes you'],
            ['owed-2', 'A balance of ₦60,000, 6 days overdue, with a green Send reminder button', 'One-tap reminder'],
        ]) ?>
        <p>The screens are from an example business. See how the <?= $link('features/debts', 'debt tracker') ?> works, follow the steps to <?= $link('help/debt-reminders', 'send debt reminders') ?>, or read about <?= $link('features/invoices', 'invoices with deposits and balances') ?>.</p>
    </div>
</article>

<?= site_related(['features/debts', 'help/debt-reminders', 'blog/how-to-write-an-invoice', 'features/receipts', 'blog/calculate-profit', 'features/invoices']) ?>
<?= site_cta('Know who owes you, and get paid') ?>
