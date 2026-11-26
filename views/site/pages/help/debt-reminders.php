<?php
/**
 * Help: remind customers who owe you. Checked against views/debts,
 * DebtController, views/orders/view.php (the WhatsApp sheet and Money),
 * OrderController::messages(), views/invoices/view.php (Send a reminder),
 * invoice_reminder_wa_link() and planner_auto_items() (money due in Tasks).
 */
require_once BASE_PATH . '/views/home/_icons.php';   // nf_icon() for the building blocks: the layout loads it only after the view
$u = fn(string $key) => e(site_url($key));
$toc = [
    'where'      => 'Where Nfelia keeps who owes you',
    'send'       => 'Send a reminder on WhatsApp',
    'other-ways' => 'Other places to send a reminder',
    'pay-by'     => 'Set the day they promised to pay',
    'record'     => 'Record the payment when it comes',
    'add'        => 'Add a debt that is not in Nfelia yet',
    'write-off'  => 'When a debt will not be paid',
    'problems'   => 'Common problems and fixes',
];
?>
<article class="wrap article-wrap has-aside">
    <header class="article-head">
        <p class="kicker">Help centre</p>
        <h1>Remind customers who owe you, on WhatsApp</h1>
        <p class="page-lead">Open Debts to see who owes you and how late they are. Then open the order, tap WhatsApp and choose Balance reminder: Nfelia writes a polite message with the balance, the day it is due and a link to the invoice, ready to send from your own WhatsApp.</p>
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
        <p class="note"><strong>Who can do this</strong> Debts is open to the owner and admins. Staff can still see orders with a balance (Orders, then Balance) and record payments, but they do not see the Debts page. See <a href="<?= $u('help/staff') ?>">what staff can see</a>.</p>

        <h2 id="where">Where Nfelia keeps who owes you</h2>
        <p>You do not need to write debts down. When a customer pays part, or says they will pay later, the balance on their order goes into Debts at once, under their name. Debts you add by hand sit in the same list.</p>
        <ul>
            <li><strong>Owed to me</strong> shows money customers owe you. <strong>I owe</strong> shows money you owe suppliers, such as an expense you bought on credit.</li>
            <li>At the top you see the total owed to you, how many debts are open, and how many are late, with their amount.</li>
            <li>The chips filter the list: <strong>Open</strong>, <strong>Late</strong>, <strong>Paid off</strong> and <strong>Written off</strong>. The menu beside the count sorts it: newest first, due soonest, most owed first or oldest first.</li>
            <li>Each line shows the customer, the order number, when it is due or how many days late it is, the balance and a tag: Not paid, Part paid, Late, Paid off or Written off.</li>
        </ul>
        <?= site_screens([
            ['owed-1', 'Nfelia showing ₦185,000 owed by customers on 5 unpaid invoices, 2 of them overdue', 'Who owes you'],
            ['owed-2', 'A balance of ₦60,000, 6 days overdue, with a green button to send a reminder', 'One-tap reminder'],
        ]) ?>
        <p>The screens are from Ivie Luxe Hair, an example business.</p>

        <h2 id="send">Send a reminder on WhatsApp</h2>
        <?= site_steps([
            ['Open Debts', 'On a phone, tap <strong>More</strong> at the bottom of the screen, then <strong>Debts</strong>. On a computer, choose <strong>Debts</strong> in the menu on the left. You see <strong>Owed to me</strong> with the total, and the list of open debts.'],
            ['Show the late ones', 'Tap <strong>Late</strong>. Only debts past their pay-by day stay in the list, each with how many days late it is.'],
            ['Open the customer\'s balance', 'Tap the line. A balance from an order opens that order at its <strong>Money</strong> section. You see the invoice, every payment so far, the balance left and the pay-by day.'],
            ['Tap WhatsApp, then Balance reminder', 'Tap <strong>WhatsApp</strong> under the order\'s total. A list of ready messages opens. Choose <strong>Balance reminder</strong>.'],
            ['Check the message and send it', 'WhatsApp opens on the customer\'s chat with the message already written. Read it, change anything you like, and press send. The reminder goes on the order\'s <strong>Log</strong>, so you can see later when you last reminded them.'],
        ]) ?>
        <p>The balance reminder greets the customer by their first name and says, in a friendly way:</p>
        <ul>
            <li>that it is a reminder from your business,</li>
            <li>the order number and the balance on it,</li>
            <li>the day it is due, when you set one,</li>
            <li>and a link to the invoice, which they can open on any phone and save as a PDF.</li>
        </ul>
        <p>For ideas on what to say when the first reminder does not work, read our guide to <a href="<?= $u('blog/collect-money-customers-owe') ?>">collecting money customers owe you</a>.</p>

        <h2 id="other-ways">Other places to send a reminder</h2>
        <ul>
            <li><strong>On an overdue invoice.</strong> Once an invoice is past its due date, its page shows <strong>Send a reminder</strong> under <strong>Record payment</strong>. The message gives the total, the balance due, how many days past due it is, and the link to the invoice.</li>
            <li><strong>On a debt you added yourself.</strong> Open it from Debts and tap the green <strong>Send a reminder</strong> button at the top. It shows while the debt is open and the customer has a phone number.</li>
            <li><strong>From Tasks.</strong> A balance with a pay-by day shows in <strong>Tasks</strong> as money due from that customer, on Today when the day comes and under Late after it, with a WhatsApp button to remind them. The owner and admins see these. Read more about <a href="<?= $u('help/tasks-and-reminders') ?>">tasks and reminders</a>.</li>
        </ul>

        <h2 id="pay-by">Set the day they promised to pay</h2>
        <p>A pay-by day makes the reminder clear and puts the debt on your Tasks list that morning.</p>
        <?= site_steps([
            ['Open the order', 'From Debts or from Orders, open the order with the balance.'],
            ['Open Money', 'Tap <strong>Money</strong>. Under the payments you see <strong>Pay by</strong> with a date box.'],
            ['Pick the day and save', 'Choose the date the customer promised and tap <strong>Save</strong>. Debts now shows it, and if that day passes without payment, the debt turns Late and shows how many days late it is.'],
        ]) ?>
        <p>You can set it from the start. When you make a new order with a balance, Nfelia asks when the balance is due: <strong>The day they get it</strong>, <strong>In a week</strong> or <strong>Pick a day</strong>. When you confirm an order from your website, the <strong>Balance due by</strong> box does the same. Change the day whenever the customer asks for more time.</p>

        <h2 id="record">Record the payment when it comes</h2>
        <?= site_steps([
            ['Open the order and tap Record payment', 'You find <strong>Record payment</strong> at the top of the order and in its Money section.'],
            ['Enter how much', 'Type the amount, or tap <strong>All of it</strong> or <strong>Half</strong>. The balance is shown above the box.'],
            ['Choose how they paid', 'Under <strong>Paid by</strong>, pick <strong>Cash</strong>, <strong>Transfer</strong>, <strong>POS</strong> or <strong>MoMo</strong>. Tap <strong>Add a reference</strong> if you want to keep the transfer or POS reference.'],
            ['Tap Save payment', 'A receipt is made for the customer straight away. The balance goes down in Debts, and when nothing is left the debt shows <strong>Paid off</strong>.'],
        ]) ?>
        <p>For a debt you added by hand, open it from Debts and use <strong>Record a payment</strong>: the amount (or <strong>Full balance</strong> or <strong>Half</strong>), the method and the date, then <strong>Record</strong>. Every payment is listed under <strong>Payments</strong> on the same page. More on receipts in <a href="<?= $u('help/receipts-and-payments') ?>">receipts and payments</a>.</p>

        <h2 id="add">Add a debt that is not in Nfelia yet</h2>
        <p>For money owed from before you used Nfelia, or a loan to a customer, add it by hand.</p>
        <?= site_steps([
            ['Tap Add a debt', 'It is at the top of the Debts page.'],
            ['Choose Owed to me', 'Then search for the customer. They must be saved in your Clients first.'],
            ['Describe it', 'Write what it is for, the amount, and the due date if there is one.'],
            ['Tap Record debt', 'You land on the debt\'s page, with <strong>Send a reminder</strong> and <strong>Record a payment</strong> ready to use.'],
        ]) ?>

        <h2 id="write-off">When a debt will not be paid</h2>
        <p>If you have stopped expecting the money, open the debt and tap <strong>Write off debt</strong> at the bottom. Nfelia asks you to confirm, marks the balance as not recoverable, and moves it to <strong>Written off</strong>, so it stops counting in what you are owed. A debt you added by hand, with no payments on it, can be deleted instead.</p>

        <h2 id="problems">Common problems and fixes</h2>
        <?= site_faq([
            ['Why is there no Balance reminder on the order?',
             'The order must be confirmed and still have a balance. A new order waiting for you to confirm it has no invoice yet, so there is nothing to remind about. Confirm the order first, then open WhatsApp again.'],
            ['WhatsApp says there is no phone number. What do I do?',
             'The customer has no phone number saved, or the sale was to a walk-in customer. Open the customer\'s page in Clients, add their WhatsApp or phone number, and try again. Nfelia uses the WhatsApp number first, then the phone number.'],
            ['Does Nfelia send reminders to my customers by itself?',
             'No. Nfelia does not message your customers about money on its own. You choose when to send each reminder, and it goes from your own WhatsApp, in your name.'],
            ['Why can\'t my staff see Debts?',
             'Debts, Expenses and Profit and loss are for the owner and admins. Make a trusted person an admin if they need it. Read <a href="' . $u('help/staff') . '">how to add staff and choose what they see</a>.'],
            ['The debt is paid but still shows in the list. Why?',
             'The payment was not recorded on the order or the debt. Open it, use Record payment, and the balance clears at once. A transfer screenshot alone does not change the balance.'],
        ]) ?>
    </div>
</article>
<?= site_related(['features/debts', 'blog/collect-money-customers-owe', 'help/send-an-invoice', 'help/receipts-and-payments', 'help/tasks-and-reminders', 'help/staff']) ?>
<?= site_cta('Get your money back with Nfelia') ?>
