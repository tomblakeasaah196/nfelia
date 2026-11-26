<?php
/**
 * Help: get found on Google. The rules are the app's own:
 *   - shop_visibility() (app/helpers/shop_links.php): paid, or in the few
 *     days of grace after a paid month, is 'live'; the free trial is
 *     'unlisted'; a trial or month that ran out unpaid is 'closed'
 *   - shop_robots(), shop_sitemap(), seo_live_shops(), main_sitemap_index()
 *     (app/helpers/seo.php) and the robots meta in views/storefront/shell.php:
 *     only live shops let search engines in and have a sitemap
 *   - the directory (views/directory/index.php) lists live shops that ticked
 *     "List my shop in the Nfelia directory" in Shop Studio › More › Found
 *     on Google (published, LandingPageController::publish); city pages are
 *     for the cities in SF_CITIES (app/helpers/storefront.php), each shop
 *     picking from its own country's
 *
 * From site_render: $page, $country.
 */
require_once BASE_PATH . '/views/home/_icons.php';   // the view runs before the layout loads it

$to    = fn(string $key, string $text) => site_page($key) ? '<a href="' . e(site_url($key)) . '">' . e($text) . '</a>' : e($text);
$grace = defined('BILLING_GRACE_DAYS') ? (int)BILLING_GRACE_DAYS : 3;
$shops = APP_URL . '/shops';
$toc   = [
    'short'     => 'The short answer',
    'pay'       => 'What changes when you pay',
    'directory' => 'Join the Nfelia shop directory',
    'words'     => 'Choose the words Google shows',
    'how-long'  => 'How long it takes',
    'tips'      => 'Help people find you',
    'problems'  => 'Common problems',
];
$yes = 'Yes';
?>
<article class="wrap article-wrap has-aside">
    <header class="article-head">
        <p class="kicker">Help centre</p>
        <h1><?= e($page['og']['title']) ?></h1>
        <p class="page-lead">Google can show your Nfelia website once you pay for Nfelia. During the free trial it is kept hidden. Joining the Nfelia shop directory is your choice.</p>
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
        <p>Nfelia offers your shop to Google, Bing and other search engines only once your business has paid for a month. During the free trial your website works for anyone with the link, but search engines are asked to stay away. The Nfelia shop directory at <a href="<?= e($shops) ?>">nfelia.com/shops</a> lists only paid shops whose owners opted in. After that, it is up to Google: a new website can take days or weeks to appear.</p>

        <h2 id="short"><?= e($toc['short']) ?></h2>
        <ul>
            <li><strong>Paid shops are offered to Google.</strong> Nfelia lists their pages in a sitemap and lets search engines in.</li>
            <li><strong>Trial shops stay hidden.</strong> Every page asks search engines not to show it, and it is in no sitemap.</li>
            <li><strong>The directory needs you to opt in.</strong> In Shop Studio, open <strong>More</strong>, then tick <strong>List my shop in the Nfelia directory</strong> under <strong>Found on Google</strong>, and publish.</li>
            <li><strong>It takes time.</strong> Google decides when, and where, to show a new website. Allow days to weeks.</li>
        </ul>

        <h2 id="pay"><?= e($toc['pay']) ?></h2>
        <div class="table-scroll">
            <table>
                <caption class="sr-only">What your Nfelia website does during the trial, once paid, and when it closes</caption>
                <thead>
                    <tr><th scope="col">Your website</th><th scope="col">Free trial</th><th scope="col">Paid month</th><th scope="col">Unpaid after the trial or a month</th></tr>
                </thead>
                <tbody>
                    <tr><th scope="row">People with your link can see it and order</th><td><?= $yes ?></td><td><?= $yes ?></td><td>No: it shows "closed for now" with your WhatsApp button</td></tr>
                    <tr><th scope="row">Google and other search engines may show it</th><td>No: kept hidden</td><td><?= $yes ?></td><td>No</td></tr>
                    <tr><th scope="row">A sitemap lists its pages for search engines</th><td>No</td><td><?= $yes ?></td><td>No</td></tr>
                    <tr><th scope="row">It can be in the Nfelia shop directory</th><td>No</td><td>Yes, if you opt in</td><td>No</td></tr>
                </tbody>
            </table>
        </div>
        <p>A paid month keeps your shop open and listed for <?= $grace ?> more days after it ends, so a late renewal does not drop you. To pay, see <?= $to('help/billing', 'paying for Nfelia') ?>.</p>
        <p>Once you pay, Nfelia does this for you:</p>
        <ul>
            <li>Makes a sitemap at <strong>yourname.nfelia.com/sitemap.xml</strong> with your home page, your <strong>Everything you sell</strong> page, your contact page, every item's page and every news post, and adds it to nfelia.com's own sitemap.</li>
            <li>Gives every item its own page with its name, price and photo, marked up so search engines can read them.</li>
            <li>Lets search engines and AI assistants read your shop, but not private pages: order tracking, reviews, bookings and checkout stay out.</li>
        </ul>
        <p>Two things must also be true: your shop link is live (not waiting for a check by our team), and <strong>Customers can see my shop</strong> is on in Shop Studio.</p>

        <h2 id="directory"><?= e($toc['directory']) ?></h2>
        <p>The Nfelia shop directory at <a href="<?= e($shops) ?>">nfelia.com/shops</a> lists paid shops by city and by what they sell, each with a link to its website. Being in it is your choice.</p>
        <?= site_steps([
            ['Open Shop Studio', 'Tap <strong>More</strong>, then <strong>Shop Studio</strong>. On a computer, it is in the menu on the left.'],
            ['Open the More tab', 'Find the <strong>Found on Google</strong> card at the top.'],
            ['Tick List my shop in the Nfelia directory', 'Then type your <strong>City</strong> and pick it from the list as it appears. Lekki and Ikeja count as Lagos.'],
            ['Tap Publish', 'Nothing changes until you publish. Once your shop is paid, it appears in the directory under your city and under what you sell.'],
        ]) ?>
        <p>What you sell comes from the <strong>Category</strong> of your business, in <strong>Settings › Business › Business details</strong>. City pages are for the main cities of Nigeria, Ghana, Kenya and Cameroon, and Shop Studio offers the ones in your own country. A shop in another town still appears in the full list and under what it sells.</p>

        <h2 id="words"><?= e($toc['words']) ?></h2>
        <p>In Shop Studio, open <strong>More</strong>. The <strong>Found on Google</strong> card has a <strong>Title</strong> and a <strong>Description</strong>, with a preview of how your shop could look in Google. Leave them empty and Nfelia uses your shop's headline and the line under it.</p>
        <ul>
            <li><strong>Title</strong>, up to 70 characters: your name and what you sell, and where. For example: <em>Ivie Luxe Hair: bundles and wigs in Lekki</em>.</li>
            <li><strong>Description</strong>, up to 170 characters: what a customer gets, in their words. For example: <em>Human hair bundles, frontals and wig installs in Lekki, Lagos. See every price, order online and pay by transfer.</em></li>
        </ul>
        <p>Tap <strong>Publish</strong> to put them live. Google sometimes picks its own words from your page instead; that is Google's choice.</p>

        <h2 id="how-long"><?= e($toc['how-long']) ?></h2>
        <p>Nobody can promise when Google will show a new website, or where. It can take a few days for Google to find your shop and a few weeks for it to show for searches. Nfelia does the parts it can: the sitemap, the item pages and the directory.</p>
        <p>To see whether Google has found your shop, search Google for <strong>site:yourname.nfelia.com</strong>, with your own shop name. If pages come up, Google knows your shop. Then search for what you sell and your area, the way a customer would.</p>

        <h2 id="tips"><?= e($toc['tips']) ?></h2>
        <ul>
            <li><strong>Name items the way customers search.</strong> <em>Body wave bundle, 18 inches</em> is found; <em>BW18</em> is not. Add a short description to each.</li>
            <li><strong>Add clear photos</strong> of every item, and keep prices current. See <?= $to('help/add-products', 'how to add products') ?>.</li>
            <li><strong>Share your link everywhere.</strong> Your Instagram and TikTok bio, your WhatsApp Status and business profile, your Facebook page and your Google Business Profile if you have one. Links from places people visit help Google find you.</li>
            <li><strong>Post news and offers.</strong> New posts, from <strong>More › News and offers</strong>, give Google something new to read.</li>
            <li><strong>Collect reviews.</strong> Customers whose orders were delivered or collected get a link to review you, and reviews show on your website. See <?= $to('help/website-orders', 'website orders') ?>.</li>
            <li><strong>Fill in your address and map.</strong> Your address, and the <strong>Map and directions</strong> section in Shop Studio, tell people where you are.</li>
        </ul>

        <h2 id="problems"><?= e($toc['problems']) ?></h2>
        <?= site_faq([
            ['I paid, but my shop is not on Google yet', 'Give it time: days to weeks. Check that your shop link is live and that <strong>Customers can see my shop</strong> is on in Shop Studio, then search Google for site:yourname.nfelia.com.'],
            ['Why is my shop hidden during the free trial?', 'A trial shop that is not paid for closes when the trial ends, so Nfelia offers Google only shops that are paid for. Your link still works for anyone you share it with, and your shop can be found once you pay.'],
            ['My shop is not in the directory', 'The directory lists paid shops only. Check that <strong>List my shop in the Nfelia directory</strong> is ticked in Shop Studio › More, and that you tapped <strong>Publish</strong> after ticking it.'],
            ['Can I keep my shop off Google after I pay?', 'There is no separate switch for Google. Turning off <strong>Customers can see my shop</strong> hides the shop from everyone, not only from search engines.'],
            ['Google shows my old shop link', 'If you changed your link, the old one sends people to the new one for 90 days, while Google catches up. Share the new link everywhere.'],
            ['Can I use my own .com address?', 'Not for now. Your website lives at yourname.nfelia.com.'],
        ]) ?>
        <p>Still stuck? <a href="<?= e(site_wa('Hello Nfelia, I want my shop to show on Google.')) ?>" target="_blank" rel="noopener">Message us on WhatsApp</a> with your shop link.</p>
    </div>
</article>

<?= site_related(['help/your-website', 'help/billing', 'features/website', 'blog/sell-on-whatsapp-and-instagram']) ?>
<?= site_cta('Get a website Google can find') ?>
