<?php
/**
 * Help: install the app. Nfelia installs from the browser (site.webmanifest,
 * sw.js): the offer after sign-in on a phone and More › Install the app
 * (views/layouts/app.php #nf-install, assets/js/shell.js), Get the app on
 * the landing page (views/home/index.php #get-app), and phone alerts
 * (#nf-push-offer, More › Notifications, assets/js/notify.js).
 *
 * From site_render: $page, $country.
 */
require_once BASE_PATH . '/views/home/_icons.php';   // the view runs before the layout loads it

$to  = fn(string $key, string $text) => site_page($key) ? '<a href="' . e(site_url($key)) . '">' . e($text) . '</a>' : e($text);
$toc = [
    'why'      => 'Why put it on your home screen',
    'android'  => 'On an Android phone',
    'iphone'   => 'On an iPhone or iPad',
    'computer' => 'On a computer',
    'alerts'   => 'Turn on phone alerts',
    'problems' => 'Common problems',
];
?>
<article class="wrap article-wrap has-aside">
    <header class="article-head">
        <p class="kicker">Help centre</p>
        <h1><?= e($page['og']['title']) ?></h1>
        <p class="page-lead">Nfelia goes on your home screen straight from the browser. No app store, nothing big to download, and it takes a few seconds.</p>
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
        <p>To install the Nfelia app, open nfelia.com in your phone's browser and sign in. On Android, tap <strong>Install Nfelia</strong> when Nfelia offers it. On iPhone, tap <strong>Share</strong>, then <strong>Add to Home Screen</strong>, then <strong>Add</strong>. Nfelia then opens from its own icon, like any other app, with the same account and records as on the web.</p>

        <h2 id="why"><?= e($toc['why']) ?></h2>
        <ul>
            <li><strong>It opens like an app</strong>, full screen, from its own icon.</li>
            <li><strong>Payments and reminders come to your phone</strong>, once you turn on alerts.</li>
            <li><strong>It is faster, even on a weak network.</strong> Pages you have already opened still open when the network drops, and Nfelia tells you when you are looking at a saved copy.</li>
            <li><strong>Nothing to update.</strong> You always get the latest Nfelia, with no app store and no big download.</li>
        </ul>

        <h2 id="android"><?= e($toc['android']) ?></h2>
        <p>Use Chrome, the browser most Android phones come with.</p>
        <?= site_steps([
            ['Sign in at nfelia.com', 'Open <a href="' . e(APP_URL . '/login') . '">nfelia.com/login</a> in Chrome and sign in.'],
            ['Tap Install Nfelia', 'Right after you sign in, a sheet comes up: <strong>Add Nfelia to your home screen</strong>. Tap <strong>Install Nfelia</strong>, then confirm in the box Chrome shows. If you tapped <strong>Not now</strong>, open <strong>More</strong> and tap <strong>Install the app</strong>.'],
            ['Find it on your home screen', 'You see <strong>Nfelia is on your home screen</strong>. Open Nfelia from its icon from now on.'],
        ]) ?>
        <p>No install button anywhere? Open Chrome's menu <strong>⋮</strong> and tap <strong>Install app</strong> or <strong>Add to Home screen</strong>. The same works from <strong>Get the app</strong> at the top of the nfelia.com home page, before you sign in.</p>
        <div class="note"><strong>Shortcuts</strong> On many Android phones, pressing and holding the Nfelia icon shows shortcuts to <strong>Create invoice</strong>, <strong>Record payment</strong> and <strong>Tasks</strong>.</div>

        <h2 id="iphone"><?= e($toc['iphone']) ?></h2>
        <p>Use Safari, the browser your iPhone came with.</p>
        <?= site_steps([
            ['Open nfelia.com in Safari', 'Sign in at <a href="' . e(APP_URL . '/login') . '">nfelia.com/login</a>, or tap <strong>Get the app</strong> on the nfelia.com home page.'],
            ['Tap Share', 'The square with an arrow pointing up. In Safari it may be under <strong>•••</strong>.'],
            ['Tap Add to Home Screen', 'Scroll down the list if you don\'t see it.'],
            ['Tap Add', 'Nfelia appears on your home screen. Open it from there, and sign in once if it asks.'],
        ]) ?>

        <h2 id="computer"><?= e($toc['computer']) ?></h2>
        <p>On a laptop or desktop, just use Nfelia in your browser: sign in at <a href="<?= e(APP_URL . '/login') ?>">nfelia.com/login</a>. Your records are the same on your phone and your computer, so you can type a long invoice on the laptop and send it from your phone. Some browsers, such as Chrome and Edge, can also install it as an app on a computer: look for <strong>Install</strong> in the address bar or the browser's menu.</p>

        <h2 id="alerts"><?= e($toc['alerts']) ?></h2>
        <p>Alerts tell you about new orders, payments and your reminders the moment they happen, even with Nfelia closed.</p>
        <?= site_steps([
            ['Tap Turn on', 'After the install sheet, Nfelia asks <strong>Get alerts on this phone</strong>. Tap <strong>Turn on</strong>, then allow notifications when your phone asks.'],
            ['Choose what reaches you', 'Open <strong>More › Notifications</strong>. Under <strong>What reaches you</strong>, each line has a <strong>Phone</strong> switch and an <strong>Email</strong> switch: shop orders, bookings, money in, low stock, task reminders, your morning plan and more. Under <strong>On your phone</strong> you can also set <strong>Quiet hours</strong>, or <strong>Hide amounts on the lock screen</strong>.'],
            ['Send a test', 'Under <strong>This phone</strong>, tap <strong>Send a test</strong>. If it arrives, you are set.'],
        ]) ?>
        <div class="note"><strong>On iPhone</strong> Alerts work only from the home-screen app. Add Nfelia to your Home Screen first, open it from its icon, then turn alerts on from there.</div>
        <p>Staff get alerts about their own work, like orders and tasks, but never see amounts. Alerts about money go to owners and admins.</p>

        <h2 id="problems"><?= e($toc['problems']) ?></h2>
        <?= site_faq([
            ['I don\'t see Install the app in More', 'It shows only when your browser can install Nfelia and it is not installed already. On Android, use Chrome\'s menu <strong>⋮</strong>, then <strong>Install app</strong>. On iPhone, use Safari\'s <strong>Share</strong> button, then <strong>Add to Home Screen</strong>.'],
            ['I tapped Not now and the offer went away', 'Nfelia asks again after 7 days. To install now, open <strong>More</strong> and tap <strong>Install the app</strong>, or use your browser\'s menu.'],
            ['The app asks me to sign in again', 'Sign in once inside the app. On iPhone the home-screen app keeps its own sign-in, separate from Safari. You can add a fingerprint or face sign-in when Nfelia offers it.'],
            ['My phone does not buzz for new orders', 'Open <strong>More › Notifications</strong> and check <strong>This phone</strong> says alerts are on, then tap <strong>Send a test</strong>. If it says notifications are blocked, allow them for Nfelia in your browser settings and come back. Quiet hours you set there hold alerts until they end. Everything still reaches the bell at the top of the app.'],
            ['Will removing the app delete my records?', 'No. Your records are kept by Nfelia, not on the phone. Remove the icon like any app, and sign in again any time from the browser.'],
            ['Is Nfelia in the Play Store or App Store?', 'No. It installs from the browser instead, so there is nothing big to download and it is always up to date.'],
        ]) ?>
        <p>Still stuck? <a href="<?= e(site_wa('Hello Nfelia, I need help installing the app on my phone.')) ?>" target="_blank" rel="noopener">Message us on WhatsApp</a> and tell us which phone you have.</p>
    </div>
</article>

<?= site_related(['help/getting-started', 'help/website-orders', 'help/tasks-and-reminders', 'help/receipts-and-payments']) ?>
<?= site_cta('Nfelia on your phone, free to try') ?>
