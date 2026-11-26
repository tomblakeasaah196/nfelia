<?php
/**
 * Guide: the business records lenders ask for, and how to get 6 to 12 months
 * of them ready. Facts about lenders, registries and regulators were checked
 * on 28 October 2026 (Central Bank of Nigeria, Bank of Ghana, SASRA, COBAC
 * and Cameroon's Ministry of Finance, the ORC, BRS, CFCE, NRS and GRA).
 */
require_once BASE_PATH . '/views/home/_icons.php';   // nf_icon() for site_related and site_cta (the layout loads it only after the view)
$u = fn(string $key) => e(site_url($key));
$toc = [
    'what-lenders-want' => 'What a lender wants to know',
    'the-records'       => 'The records lenders usually ask for',
    'who-lends'         => 'Who lends to small businesses',
    'get-ready'         => 'Get 6 to 12 months of records ready',
    'doubts'            => 'What makes a lender doubt your records',
    'nfelia'            => 'How Nfelia helps',
    'questions'         => 'Questions people ask',
];
?>
<article class="wrap article-wrap has-aside">
    <header class="article-head">
        <p class="kicker">Guide</p>
        <h1>The business records lenders ask for, and how to get them ready</h1>
        <p class="page-lead">Before a bank, a microfinance bank or a SACCO lends to your business, it wants proof that money comes in steadily and that you can pay back. That proof is your records: bank statements, sales, spending and who owes you. Here is what they usually ask for, and how to get six to twelve months of it in order.</p>
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
        <p>A busy business can still look invisible to a lender. When sales live in a notebook, a WhatsApp chat and the owner's head, the loan officer cannot see them. Good records will not guarantee a loan, and nobody can promise you one. But they turn "I sell well" into something a lender can check, and that is the first thing they need.</p>

        <h2 id="what-lenders-want">What a lender wants to know</h2>
        <p>Every lender's form looks different, but they are all asking three questions:</p>
        <ol>
            <li><strong>Is the business real?</strong> Who owns it, where it is, and how long it has been running.</li>
            <li><strong>Does money come in steadily?</strong> Not one big month, but sales month after month.</li>
            <li><strong>Can you pay back from what is left?</strong> After stock, rent, transport and staff, is there enough to cover the repayment?</li>
        </ol>
        <p>Each record below answers one of those questions. Keep that in mind and you will know why each one matters.</p>

        <h2 id="the-records">The records lenders usually ask for</h2>
        <p>Every lender has its own list, and it changes with the size of the loan. Ask for theirs before you start, and bring exactly what it says. These are the records that come up again and again:</p>
        <ul>
            <li><strong>Bank statements.</strong> The most common request. Many lenders ask for the last six months; some ask for three, some for twelve. Statements from the account your business money goes through count most.</li>
            <li><strong>Sales records.</strong> What you sold, to whom, when and for how much. Invoices and receipts are the best proof, because each one has a date, a customer and an amount.</li>
            <li><strong>Spending records.</strong> Stock you bought, rent, transport, salaries, data and fuel. These show what is left after costs.</li>
            <li><strong>Who owes you, and who you owe.</strong> A list of customers who still owe you, and suppliers you still owe.</li>
            <li><strong>A stock list</strong>, if you sell goods: what you have and what it cost you.</li>
            <li><strong>Proof the business is registered</strong>, if it is. In Nigeria, that is your Corporate Affairs Commission (CAC) certificate. In Ghana, registration is with the Office of the Registrar of Companies (ORC). In Kenya, it is the Business Registration Service (BRS) certificate. In Cameroon, registration and your taxpayer number (NIU) come through the business creation centres (CFCE).</li>
            <li><strong>Your tax number.</strong> Kenyan lenders usually ask for your KRA PIN. In Ghana, the Ghana Card PIN is the tax number for individuals; companies use their own GRA TIN.</li>
            <li><strong>Your ID, and sometimes a guarantor or collateral</strong>, especially for bigger loans.</li>
        </ul>
        <p class="note"><strong>Ask first, print second.</strong> A short call or visit to ask "what exactly do you need from me?" saves you days of gathering the wrong papers.</p>

        <h2 id="who-lends">Who lends to small businesses</h2>
        <p>Where you borrow changes what you are asked for. This is a general picture, not a list of every option:</p>
        <div class="table-scroll">
            <table>
                <caption class="sr-only">Kinds of lenders and what they tend to look at</caption>
                <thead>
                    <tr><th scope="col">Lender</th><th scope="col">Where</th><th scope="col">What they tend to look at</th></tr>
                </thead>
                <tbody>
                    <tr><th scope="row">Commercial banks</th><td>All four countries</td><td>Bank statements, registration, tax number, and often collateral or a guarantor for larger amounts.</td></tr>
                    <tr><th scope="row">Microfinance banks</th><td>Nigeria (licensed by the Central Bank of Nigeria)</td><td>Bank statements, ID and your BVN, sometimes a guarantor. Some small loans need no collateral.</td></tr>
                    <tr><th scope="row">SACCOs</th><td>Kenya (deposit-taking SACCOs are licensed by SASRA)</td><td>Your savings with the SACCO and guarantors who are members. Many SACCOs lend a multiple of what you have saved, often up to three times.</td></tr>
                    <tr><th scope="row">Microfinance lenders and susu</th><td>Ghana (rules set by the Bank of Ghana)</td><td>A steady record of saving, such as daily or weekly susu contributions, plus your sales.</td></tr>
                    <tr><th scope="row">Microfinance institutions</th><td>Cameroon (approved by the Ministry of Finance after COBAC's opinion)</td><td>Your account with them, your sales and your spending.</td></tr>
                </tbody>
            </table>
        </div>
        <p>Before you hand over documents or money, check the lender is licensed. Central banks and regulators publish lists: the Central Bank of Nigeria, the Bank of Ghana, SASRA in Kenya, and Cameroon's Ministry of Finance. Never pay a "processing fee" to someone you cannot find on those lists.</p>

        <h2 id="get-ready">Get 6 to 12 months of records ready</h2>
        <p>You do not need an accountant to do this. You need one evening a week for a few weeks, and the habit of writing things down as they happen from now on.</p>
        <ol class="steps">
            <li><div><h3>Pick the months</h3><p>Take the last six or twelve full months. If the lender asked for six, prepare six, and keep the rest ready in case they ask for more.</p></div></li>
            <li><div><h3>Put every sale in one place</h3><p>One list, or one app, for every sale: cash, transfer and mobile money. Give an invoice or receipt for each one, even small sales. A sale with a document is a sale you can prove.</p></div></li>
            <li><div><h3>Record what you spend, by kind</h3><p>Stock, rent, transport, salaries, light and fuel, data. Keep supplier invoices and receipts. Kinds matter: a lender wants to see that most of your spending buys stock that you sell again.</p></div></li>
            <li><div><h3>Run business money through one account</h3><p>Pay your takings into the account you will show the lender, and pay suppliers from it. When sales in your records match money into that account, your statement backs up your story.</p></div></li>
            <li><div><h3>Total each month</h3><p>For each month: what you sold, what you received, what you spent and what was left. A small table like the one below is enough.</p></div></li>
            <li><div><h3>List who owes you and who you owe</h3><p>Names, amounts and how long each has been owed. Chase the oldest debts before you apply: money collected looks better than money owed.</p></div></li>
        </ol>
        <p>A month-by-month summary might look like this (example figures; they read the same in naira, cedis, shillings or CFA francs):</p>
        <div class="table-scroll">
            <table>
                <caption class="sr-only">Example monthly summary of sales, money received, spending and what was left</caption>
                <thead>
                    <tr><th scope="col">Month</th><th scope="col">Sold</th><th scope="col">Money received</th><th scope="col">Spent</th><th scope="col">Left over</th></tr>
                </thead>
                <tbody>
                    <tr><th scope="row">July</th><td>820,000</td><td>760,000</td><td>590,000</td><td>170,000</td></tr>
                    <tr><th scope="row">August</th><td>905,000</td><td>870,000</td><td>640,000</td><td>230,000</td></tr>
                    <tr><th scope="row">September</th><td>780,000</td><td>810,000</td><td>575,000</td><td>235,000</td></tr>
                </tbody>
            </table>
        </div>
        <p>"Sold" and "money received" are different on purpose. If you sell on credit, some of what you sold in July is paid in August. A lender who sees both understands your business better. For help working out the last column, read <a href="<?= $u('blog/calculate-profit') ?>">how to calculate your profit</a>.</p>

        <h2 id="doubts">What makes a lender doubt your records</h2>
        <ul>
            <li><strong>Business and personal money mixed together.</strong> School fees, family transfers and sales in one account make it hard to see what the business earns.</li>
            <li><strong>Big cash deposits just before you apply.</strong> Money that appears in the last few weeks and was never there before raises questions. Steady months are worth more.</li>
            <li><strong>Records that do not match the bank.</strong> If your notebook says you sold far more than ever reached your account, be ready to explain where the cash went.</li>
            <li><strong>Missing months.</strong> A gap looks like a month the business stopped. If you closed for a reason, say so.</li>
            <li><strong>Figures that look made up.</strong> Sales of exactly the same round amount every month are not how real shops trade.</li>
        </ul>

        <h2 id="nfelia">How Nfelia helps</h2>
        <p>Nfelia keeps your records as you work, so there is nothing to gather when a lender asks. Here is what it does, and what it does not do.</p>
        <ul>
            <li><strong>Every sale is recorded as you make it.</strong> Each <a href="<?= $u('features/invoices') ?>">invoice</a> and <a href="<?= $u('features/receipts') ?>">receipt</a> you send is saved with its date, customer and amount, and payments update what each customer still owes.</li>
            <li><strong>Spending, by kind.</strong> Record <a href="<?= $u('features/profit') ?>">expenses</a> under Stock Purchase, Rent, Transport, Salary / Wages and more. The Profit and loss report shows money in against money out for this month, last month, this quarter, the year to date or the last 12 months, and downloads as a PDF.</li>
            <li><strong>Your Records Book.</strong> One Excel workbook in your colours with your customers, invoices, receipts, payments, expenses, debts and stock, plus an At a glance sheet with every month side by side and a chart. Choose all time, this year, the last 12 months, last year, or dates you pick. Read more about <a href="<?= $u('features/records-book') ?>">the Records Book</a>.</li>
            <li><strong>Anyone can check it is genuine.</strong> The book, and every invoice and receipt in it, has a code. A loan officer can type it or scan it at <a href="<?= e(APP_URL . '/verify') ?>">nfelia.com/verify</a> to see the record is real and unchanged.</li>
            <li><strong>Free in every month you pay for.</strong> One Records Book comes with each paid month. Your plain copy, every record as CSV files, is free whatever your plan.</li>
        </ul>
        <?= site_screens([
            ['records-1', 'Your records screen in Nfelia: the Records Book, free this month, with a Make my Records Book button', 'Made in one tap'],
            ['records-2', 'nfelia.com/verify showing an invoice from Ivie Luxe Hair as genuine, with its order, amount and status', 'Anyone can check it'],
        ]) ?>
        <p>To be clear about the limits: Nfelia does not lend money or arrange loans, and it does not replace your bank statements. The Records Book holds only what you record in Nfelia, so it is strongest when every sale and expense goes in. Lenders decide for themselves. The screens are from Ivie Luxe Hair, an example business. The <a href="<?= $u('help/records-book') ?>">Records Book help guide</a> shows how to make yours.</p>

        <h2 id="questions">Questions people ask</h2>
        <?= site_faq([
            ['How many months of bank statements do lenders ask for?',
             'It depends on the lender and the loan. Six months is a common request; some lenders ask for three and some for twelve. Ask the lender for their list before you print anything.'],
            ['Will good records guarantee a loan?',
             'No. The lender decides, and looks at things records cannot show, such as your credit history, the loan size and any guarantor or collateral. Good records make your case clear and easy to check.'],
            ['Does Nfelia lend money?',
             'No. Nfelia does not lend or arrange loans. It keeps your sales, spending and debts as you work, and makes your <a href="' . $u('features/records-book') . '">Records Book</a>, which you can show to any lender you choose.'],
        ]) ?>
    </div>
</article>
<?= site_related(['features/records-book', 'help/records-book', 'blog/calculate-profit', 'features/profit', 'help/expenses-and-profit', 'blog/collect-money-customers-owe']) ?>
<?= site_cta('Keep records a lender can check', 'Every invoice, receipt and expense in one place, and a Records Book anyone can verify. Free for ' . (int)(site_prices($page['country'])['trial_days'] ?? TRIAL_DAYS) . ' days, no card needed.') ?>
