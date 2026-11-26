<?php
/**
 * Guide: taking booking deposits. How much to ask, policies to copy (the
 * .template blocks keep their line breaks), checking transfers and mobile
 * money, then what Nfelia's bookings do (checked against
 * app/helpers/bookings.php, views/storefront/pages/book.php and
 * views/bookings/view.php).
 */
require_once BASE_PATH . '/views/home/_icons.php';   // nf_icon() for site_related and site_cta (the layout loads it only after the view)
$u = fn(string $key) => e(site_url($key));
$toc = [
    'why'       => 'Why take a deposit',
    'how-much'  => 'How much deposit to ask for',
    'policy'    => 'Write your policy down',
    'templates' => 'Policies and messages to copy',
    'check'     => 'Check the money before you confirm',
    'balance'   => 'Collect the balance',
    'nfelia'    => 'How Nfelia helps',
    'questions' => 'Questions people ask',
];
?>
<article class="wrap article-wrap has-aside">
    <header class="article-head">
        <p class="kicker">Guide</p>
        <h1>Booking deposits: how much to ask, what to write, how to check</h1>
        <p class="page-lead">A booking deposit is part of the price, paid up front, that holds a customer's time or date. To take one well, set an amount that covers what a no-show costs you, write your rules down before anyone pays, and check the money has really arrived before you confirm.</p>
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
        <p>Every salon owner, tailor, caterer and event planner knows the feeling. Saturday at 10am was booked. You turned two people away. You bought the hair, or the fabric, or the food. And the customer did not come, and does not answer the phone. A deposit does not stop every no-show, but it changes who carries the cost, and a customer who has paid has a reason to turn up.</p>

        <h2 id="why">Why take a deposit</h2>
        <ul>
            <li><strong>It protects your time.</strong> A slot you held for someone who did not come is a slot you cannot sell again.</li>
            <li><strong>It pays for materials.</strong> Hair, fabric, flowers, food and hired chairs are bought for one customer. If they cancel, you are left holding them.</li>
            <li><strong>It shows the customer is serious.</strong> A small payment filters out the "just checking" bookings.</li>
            <li><strong>It helps you plan.</strong> Confirmed, paid bookings tell you how busy next week really is.</li>
        </ul>

        <h2 id="how-much">How much deposit to ask for</h2>
        <p>There is no single right number. Start from one question: <strong>what does it cost me if this customer does not come?</strong> The deposit should cover that. Some ways to set it:</p>
        <ul>
            <li><strong>A fixed amount</strong> for short services where only your time is at risk. It is easy to say and easy to remember.</li>
            <li><strong>A share of the price</strong>, such as 30% or 50%, for bigger jobs where the cost grows with the job.</li>
            <li><strong>The cost of materials</strong>, at least, whenever you buy something for that customer before the day.</li>
        </ul>
        <div class="table-scroll">
            <table>
                <caption class="sr-only">Examples of what a no-show costs different businesses, and a deposit that covers it</caption>
                <thead>
                    <tr><th scope="col">Business</th><th scope="col">What a no-show costs you</th><th scope="col">A deposit that covers it</th></tr>
                </thead>
                <tbody>
                    <tr><th scope="row">Braids, nails, barbing, make-up</th><td>The hours you held</td><td>A fixed amount, or a share of the price</td></tr>
                    <tr><th scope="row">Wig install with hair you supply</th><td>Your time, plus the hair you bought</td><td>The cost of the hair plus part of your time, for example half the price or more</td></tr>
                    <tr><th scope="row">Tailoring</th><td>Fabric, lining and cutting time</td><td>At least the materials, before you cut</td></tr>
                    <tr><th scope="row">Events, décor, catering</th><td>Hired items, food, staff, and a date you refused others for</td><td>A share to hold the date, the balance a set number of days before</td></tr>
                    <tr><th scope="row">Shortlets</th><td>Nights you could have let to someone else</td><td>The first night or a share, plus a refundable caution fee</td></tr>
                </tbody>
            </table>
        </div>
        <p>Too high a deposit puts off new customers; too low and it does not cover your loss. If you are not sure, start lower for regulars and higher for first-time customers and big jobs.</p>

        <h2 id="policy">Write your policy down</h2>
        <p>A deposit only works if the customer knew the rules before they paid. Arguments start when the rules are made up after a no-show. A good policy is short and answers six things:</p>
        <ol>
            <li>How much the deposit is, and how to pay it.</li>
            <li>Whether it comes off the final bill. (It should.)</li>
            <li>How much notice the customer must give to move or cancel.</li>
            <li>What happens to the deposit if they cancel late or do not come.</li>
            <li>What happens if they are late.</li>
            <li>What happens if <em>you</em> have to cancel.</li>
        </ol>
        <p>Put it where customers see it before they pay: on your website, in your WhatsApp catalogue, in the message with your account details. This is general good practice, not legal advice. Consumer rules differ from country to country; if you are unsure about refunds, ask a lawyer or your trade association.</p>

        <h2 id="templates">Policies and messages to copy</h2>
        <p>Change the words in [brackets] to fit your business.</p>

        <h3>For appointments: salons, nails, barbing, make-up</h3>
        <div class="template">Booking and deposit policy
To book, please pay a deposit of [amount, or 30%] of the price. Your time is held once we confirm the deposit.
The deposit comes off your bill on the day.
Changing your time: tell us at least [24 hours] before, and we will move your booking once at no cost.
Cancelling: tell us at least [24 hours] before, and we will refund your deposit.
Running late: after [15 minutes], we may shorten your service or move you to another time.
Not coming, or cancelling with less than [24 hours'] notice: the deposit is not refunded.
If we have to cancel, you choose: a full refund of your deposit, or a new time.</div>

        <h3>For bigger jobs: events, catering, décor, tailoring</h3>
        <div class="template">Payment and cancellation terms
1. A deposit of [50%] confirms your date. We do not hold dates without it.
2. The balance of [amount] is due [7 days before the event / at the final fitting / before delivery].
3. Materials bought for your job (fabric, flowers, food, hired items) are paid for from the deposit. Once bought, they cannot be refunded.
4. If you cancel more than [30 days] before, we refund your deposit minus what we have already spent on your job, with the receipts.
5. If you cancel [30 days] or less before, the deposit is not refunded.
6. Changes after [date] may change the price. We will tell you in writing first.
7. If we cannot deliver, we refund everything you have paid.</div>

        <h3>Confirming a booking on WhatsApp</h3>
        <div class="template">Hello [name], your booking for [service] on [day, date] at [time] is confirmed.
Deposit received: [amount]. Thank you.
Balance to pay on the day: [amount].
To change your time, please tell us by [day and time]. Our booking policy: [link].
See you then. [Your business name]</div>

        <h3>After a no-show</h3>
        <div class="template">Hello [name], we missed you today at [time]. As our booking policy says, the deposit for a missed appointment is not refunded. We would still love to see you: reply here and we will find you a new time.</div>

        <h2 id="check">Check the money before you confirm</h2>
        <p>Screenshots and text messages can be faked. Before you confirm a booking, see the money where only you can see it:</p>
        <ul>
            <li><strong>Bank transfer:</strong> open your own bank app and find the payment. Match the amount and the sender's name. Do not rely on a screenshot or a forwarded alert.</li>
            <li><strong>Mobile money</strong> (MTN MoMo, Telecel Cash, AT Money, M-Pesa, Airtel Money, Orange Money): check your balance or your own statement, and match the transaction ID in the customer's message with the one in yours.</li>
            <li><strong>Not there yet?</strong> Transfers are sometimes delayed. Tell the customer you will confirm once it lands, hold the time for a short while, and check again.</li>
            <li><strong>Give a receipt for the deposit</strong>, and write the balance due on it, so neither of you forgets what is left to pay.</li>
        </ul>

        <h2 id="balance">Collect the balance</h2>
        <p>Remind the customer the day before, with the time and the balance. For events, ask for the balance before the day, as your terms say, so you are not chasing money while you work. When they pay in full, send a receipt marked paid. If someone still owes after the job, our guide on <a href="<?= $u('blog/collect-money-customers-owe') ?>">collecting money customers owe you</a> has messages that work.</p>

        <h2 id="nfelia">How Nfelia helps</h2>
        <p>Nfelia takes bookings with deposits on your own website, and keeps the time, the money and the customer together:</p>
        <ul>
            <li><strong>Customers pick a free time.</strong> You set your opening hours. Only free times show, so two people cannot book the same slot. Services can be booked by the time slot, by the whole day, or by the night for stays.</li>
            <li><strong>You set the deposit for each service:</strong> no deposit, a share (%), or a fixed amount. The customer sees it before they book.</li>
            <li><strong>They pay into your own account.</strong> Your bank account or Mobile Money number shows on the booking page, and the customer can attach a screenshot of the payment.</li>
            <li><strong>You check, then confirm.</strong> Tick that you have the deposit and tap Confirm and add to my calendar. Nfelia makes a receipt for the deposit, so it shows in your money in. A request you have not answered holds its time for 24 hours.</li>
            <li><strong>The customer gets a booking page</strong> with the time, the deposit and what is left to pay on the day.</li>
            <li><strong>Decline or cancel with a message</strong> the customer sees, and the time is freed for someone else.</li>
            <li><strong>Your policy on show.</strong> Write it in the service's description and customers read it on its page before they tap to book. Stays also have house rules and a refundable caution fee, with a reminder to give the fee back after check-out.</li>
        </ul>
        <?= site_screens([
            ['book-1', 'Booking a wig install on Ivie Luxe Hair\'s website: 2 hours, a 50% deposit, and a calendar of free days', 'They pick a free time'],
            ['book-2', 'A booking request in Nfelia for Saturday at 10am, with a button to confirm it and add it to the calendar', 'You confirm in one tap'],
        ]) ?>
        <p>Nfelia does not move the money: customers pay you directly, and you confirm only when you have seen it. The screens are from Ivie Luxe Hair, an example business. Read more about <a href="<?= $u('features/bookings') ?>">bookings with deposits</a>, or follow the <a href="<?= $u('help/bookings') ?>">bookings help guide</a> to set yours up.</p>

        <h2 id="questions">Questions people ask</h2>
        <?= site_faq([
            ['Should a booking deposit be refundable?',
             'That is your choice, as long as you say so before the customer pays. One fair approach is to refund or move the deposit when the customer gives enough notice, and keep it for no-shows and late cancellations. This is general guidance, not legal advice.'],
            ['How much deposit should a salon take?',
             'Enough to cover what a no-show costs you. When only your time is at risk, a fixed amount or a share of the price works. When you buy hair or products for that client, the deposit should cover their cost too.'],
            ['What if a customer says they paid but I cannot see the money?',
             'Do not confirm yet. Check your own bank app or mobile money statement, ask for the transaction ID, and remember transfers are sometimes delayed. A screenshot alone is not proof.'],
        ]) ?>
    </div>
</article>
<?= site_related(['features/bookings', 'help/bookings', 'for/salons', 'for/events', 'help/receipts-and-payments', 'blog/collect-money-customers-owe']) ?>
<?= site_cta('Take bookings with deposits on your own website') ?>
