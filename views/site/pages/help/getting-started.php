<?php
/**
 * Help: getting started. Sign-up (views/auth/register.php), the three
 * onboarding screens (views/onboarding/wizard.php, OnboardingController) and
 * the shop link and look (views/shop_setup/index.php), then the app's tabs
 * (views/layouts/app.php) and the first jobs.
 *
 * From site_render: $page, $country.
 */
require_once BASE_PATH . '/views/home/_icons.php';   // the view runs before the layout loads it

$to   = fn(string $key, string $text) => site_page($key) ? '<a href="' . e(site_url($key)) . '">' . e($text) . '</a>' : e($text);
$days = (int)(site_prices($page['country'])['trial_days'] ?? TRIAL_DAYS);
$toc  = [
    'before'   => 'Before you start',
    'sign-up'  => 'Create your account',
    'step-1'   => 'Step 1: About your business',
    'step-2'   => 'Step 2: Money and payments',
    'step-3'   => 'Step 3: How your business looks',
    'step-4'   => 'Step 4: Your shop link and look',
    'around'   => 'Find your way around',
    'first'    => 'Your first three jobs',
    'problems' => 'Common problems',
    'help'     => 'Get help from a person',
];
?>
<article class="wrap article-wrap has-aside">
    <header class="article-head">
        <p class="kicker">Help centre</p>
        <h1><?= e($page['og']['title']) ?></h1>
        <p class="page-lead">Sign up, answer four short screens, and your business is ready: invoices, receipts and stock, plus your own website at yourname.nfelia.com.</p>
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
        <p>Getting started with Nfelia takes a few minutes on your phone. You create an account, then Nfelia asks four things in order: about your business, where customers pay you, how your business looks, and the link and look of your shop. Everything you type can be changed later. The free trial starts the day you sign up (<?= $days ?> days in Nigeria), with no card needed.</p>

        <h2 id="before"><?= e($toc['before']) ?></h2>
        <p>You need a phone or computer with a browser and an email address you can open. Have these ready if you can. None of them is required to start:</p>
        <ul>
            <li>Your business phone number and WhatsApp number</li>
            <li>The bank or mobile money account customers pay into</li>
            <li>A picture of your logo (JPG, PNG or WebP)</li>
            <li>The name you want in your shop link, like <strong>ivieluxe</strong> for ivieluxe.nfelia.com</li>
        </ul>

        <h2 id="sign-up"><?= e($toc['sign-up']) ?></h2>
        <?= site_steps([
            ['Open the sign-up page', 'Go to <a href="' . e(site_signup_url('NG')) . '">nfelia.com/register</a>, or tap <strong>Start free</strong> at the top of any page on nfelia.com.'],
            ['Fill in your details', 'Type <strong>Your full name</strong>, <strong>Business name</strong>, <strong>Email address</strong> and a <strong>Password</strong>, then type the password again. The list under the password ticks each rule as you type: at least 9 characters, a capital letter, a number and a symbol.'],
            ['Add a referral code, if you have one', 'Tap <strong>Have a referral code?</strong> and type it. A referral link fills it in for you.'],
            ['Tap Create my account', 'You are signed in straight away and see <strong>Step 1 of 4</strong>. We also send a welcome email to the address you gave.'],
        ]) ?>

        <h2 id="step-1"><?= e($toc['step-1']) ?></h2>
        <p>This screen is called <strong>About your business</strong>. What you type here goes on your invoices, receipts and shop.</p>
        <?= site_steps([
            ['Choose your Country', 'Pick it from the list. It sets your money and what Nfelia costs you each month, so it is the one field you must fill. If your country is not in the list, Nfelia is not open for sign-ups there yet: <a href="' . e(site_wa('Hello Nfelia, my country is not in the sign-up list.')) . '" target="_blank" rel="noopener">tell us on WhatsApp</a>.'],
            ['Answer What do you sell?', 'Tap <strong>Products</strong> (things you keep in stock), <strong>Services</strong> (work you do for people) or <strong>Both</strong>.'],
            ['Pick your Type of business', 'For example fashion or beauty. It chooses the look your shop starts with, and where the Nfelia shop directory lists you.'],
            ['Add your contact details', 'A short line about your business and a registration number are optional. Add your <strong>Phone number</strong>, <strong>WhatsApp number</strong> and <strong>Shop or office address</strong>: they show on your invoices and quotes, and your shop\'s WhatsApp buttons use the WhatsApp number. Outside Nigeria, type the WhatsApp number with your country code, like +233 for Ghana.'],
            ['Tap Save and continue', 'You see <strong>Step 2 of 4</strong>.'],
        ]) ?>

        <h2 id="step-2"><?= e($toc['step-2']) ?></h2>
        <p>This screen is called <strong>Money and payments</strong>. Your <strong>Currency</strong> is already set from your country.</p>
        <?= site_steps([
            ['Add your VAT rate, if you charge VAT', 'Type it in <strong>VAT or tax on your invoices (%)</strong>, or leave it at 0. Nfelia fills it in on new quotes.'],
            ['Add where customers pay you', 'Under <strong>Where customers pay you</strong>, type the <strong>Bank name</strong>, <strong>Account number</strong> and <strong>Account name</strong>. A mobile money account works too: type its name, like MTN MoMo, as the bank name. This account shows on your invoices and at checkout on your website.'],
            ['Tap Save and continue', 'You see <strong>Step 3 of 4</strong>. No business account yet? Leave all three empty and add one later in <strong>Settings › Business › Payment accounts</strong>, where you can add more than one.'],
        ]) ?>

        <h2 id="step-3"><?= e($toc['step-3']) ?></h2>
        <p>This screen is called <strong>How your business looks</strong>. Your colours, lettering and logo go on every invoice, quote and receipt you send.</p>
        <?= site_steps([
            ['Pick your Main colour and Second colour', 'Tap the colour box, or type a colour code like #8B004A. Skip it and you keep Nfelia\'s burgundy and gold.'],
            ['Choose your Lettering', 'Clean and modern, Classic and formal, or Bold and creative.'],
            ['Add your logo', 'Tap <strong>Choose a picture</strong> and pick your logo: JPG, PNG or WebP, up to 20MB.'],
            ['Tap Save and continue', 'You see <strong>Saved. One last step: your shop link and look.</strong>'],
        ]) ?>

        <h2 id="step-4"><?= e($toc['step-4']) ?></h2>
        <p>The last screen, <strong>Set up your shop</strong>, asks for two things, and you need both to carry on:</p>
        <ul>
            <li><strong>Your shop link.</strong> Type the name you want in <strong>Shop name</strong>. Nfelia checks it as you type and says when it is free, or offers free names you could use. Use 3 to 30 letters, numbers or hyphens.</li>
            <li><strong>Your look.</strong> Pick one of the 10 looks, each made for a kind of business. Tap <strong>See it full size</strong> to see your own shop in that look, then <strong>Use this look</strong>.</li>
        </ul>
        <p>Tap <strong>Save my shop</strong>. You see <strong>Your shop is ready at yourname.nfelia.com</strong> and land on Home. The guide to <?= $to('help/your-website', 'setting up your website') ?> explains the link and the looks in full.</p>

        <h2 id="around"><?= e($toc['around']) ?></h2>
        <p>On a phone, five tabs sit at the bottom of the screen:</p>
        <ul>
            <li><strong>Home</strong>: money that came in this month, what is still owed, what needs you, and today's plan.</li>
            <li><strong>Orders</strong>: your orders, and above them the switches to <strong>Quotes</strong>, <strong>Invoices</strong> and <strong>Receipts</strong>.</li>
            <li><strong>+</strong> (Create): <strong>New order</strong>, <strong>New quote</strong>, <strong>Add task</strong>, <strong>Add customer</strong>, <strong>Add product</strong> and, for owners and admins, <strong>Add expense</strong>.</li>
            <li><strong>Tasks</strong>: your tasks and calendar.</li>
            <li><strong>More</strong>: everything else, such as <strong>Catalogue</strong>, <strong>Clients</strong>, <strong>Debts</strong>, <strong>Shop Studio</strong> and <strong>Settings</strong>.</li>
        </ul>
        <p>On a computer, the same places are in the menu on the left. Your records are the same on both.</p>
        <div class="note"><strong>Tip</strong> Right after you sign in on a phone, Nfelia offers to go on your home screen so it opens like an app. Tap <strong>Install Nfelia</strong>, or follow the steps for iPhone. The guide to <?= $to('help/install-the-app', 'installing the app') ?> has the details.</div>

        <h2 id="first"><?= e($toc['first']) ?></h2>
        <ol>
            <li><strong>Add what you sell.</strong> One by one, or many at once from a spreadsheet. See <?= $to('help/add-products', 'how to add products') ?>.</li>
            <li><strong>Share your shop link.</strong> Put it in your Instagram bio and on your WhatsApp Status. See <?= $to('help/your-website', 'your website') ?>.</li>
            <li><strong>Send your first invoice.</strong> Tap <strong>+</strong>, then <strong>New order</strong>. See <?= $to('help/send-an-invoice', 'how to send an invoice') ?>.</li>
        </ol>

        <h2 id="problems"><?= e($toc['problems']) ?></h2>
        <?= site_faq([
            ['It says "This email already has an account"', 'You, or someone in your business, already signed up with that email. <a href="' . e(APP_URL . '/login') . '">Sign in</a> instead, and use <strong>Forgot password</strong> on the sign-in page if you need to. To start a separate business, use another email.'],
            ['My password is refused', 'It must meet every line in the list under the password field. If it says the password appeared in a known data breach, it could be guessed: choose a different one.'],
            ['It says "Choose your country"', 'The country sets your money and your Nfelia price, so Nfelia never guesses it. Pick it at the top of Step 1. If yours is not in the list, Nfelia is not open there yet.'],
            ['It asks for the bank, the account number and the account name', 'You filled in only part of the account. Fill in all three, or leave all three empty and add the account later in Settings.'],
            ['My logo did not upload', 'Use a JPG, PNG or WebP picture up to 20MB. You can carry on without it and add it any time in <strong>Settings › Business › Business logo</strong>.'],
            ['My shop name is taken, or needs a check', 'Pick one of the free names Nfelia offers, or try another. Names that look like a bank, a phone network, a government office or an official account wait for a quick check by our team: your shop goes live once it is approved, or choose another name to go live now.'],
            ['It says too many new accounts from this connection', 'A few new businesses a day can sign up from one internet connection. Try again tomorrow, or message us on WhatsApp and we will help.'],
        ]) ?>

        <h2 id="help"><?= e($toc['help']) ?></h2>
        <p>Setup help is free. <a href="<?= e(site_wa('Hello Nfelia, I am setting up and I need help.')) ?>" target="_blank" rel="noopener">Message us on WhatsApp</a> and we will set you up, or <a href="<?= e(APP_URL . '/book') ?>">book a call</a> at a time that suits you.</p>
    </div>
</article>

<?= site_related(['help/install-the-app', 'help/add-products', 'help/your-website', 'help/send-an-invoice']) ?>
<?= site_cta('Set up your business today') ?>
