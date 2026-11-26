<?php
/**
 * Help: your website. The shop link and look (views/shop_setup/index.php,
 * ShopSetupController), Shop Studio (views/studio/index.php,
 * LandingPageController, assets/js/studio.js), and how a shop shows during
 * the trial and after it (shop_visibility in app/helpers/shop_links.php).
 *
 * The looks and the sections are read from the app's own lists
 * (STOREFRONT_LOOKS, storefront_section_types()), so this page names them
 * as the app does, whatever is added later.
 *
 * From site_render: $page, $country.
 */
require_once BASE_PATH . '/views/home/_icons.php';   // the view runs before the layout loads it

$to  = fn(string $key, string $text) => site_page($key) ? '<a href="' . e(site_url($key)) . '">' . e($text) . '</a>' : e($text);
$toc = [
    'what'       => 'What you get',
    'link'       => 'Choose your shop link and look',
    'studio'     => 'Change your website in Shop Studio',
    'publish'    => 'Publish your changes',
    'share'      => 'Share your link',
    'rename'     => 'Change your shop link',
    'switch-off' => 'Switch your website off',
    'trial'      => 'During the free trial, and after',
    'problems'   => 'Common problems',
];
$sentence = fn(string $s) => rtrim(trim($s), '.') . '.';
$looks    = defined('STOREFRONT_LOOKS') ? STOREFRONT_LOOKS : [];
$sections = [];
// Where the app's own hint says where a section shows rather than what it holds
$plainer  = [
    'about'    => 'Your story, in your own words.',
    'delivery' => 'How you deliver, and your payment accounts if you want them shown, under your products.',
    'faq'      => 'Questions customers ask you, with your answers, on your contact page.',
];
if (function_exists('storefront_section_types')) {
    foreach (storefront_section_types() as $type => $s) {
        if (!empty($s['available'])) {
            $sections[] = [$s['name'], $plainer[$type] ?? ($s['hint'] !== '' ? $sentence($s['hint']) : '')];
        }
    }
}
$host = 'nfelia.com';
?>
<article class="wrap article-wrap has-aside">
    <header class="article-head">
        <p class="kicker">Help centre</p>
        <h1><?= e($page['og']['title']) ?></h1>
        <p class="page-lead">Every Nfelia business gets its own website at yourname.<?= $host ?>, with every price and an Add button on each item. Here is how to set it up, change it and share it.</p>
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
        <p>Your Nfelia website is made when you choose your shop link and one of <?= count($looks) ?: 10 ?> looks at sign-up. After that, open <strong>More › Shop Studio</strong> to change its colours, fonts, photos, words and sections, then tap <strong>Publish</strong>. The products on it come from your Catalogue, so a new item or a new price shows up without any extra work.</p>

        <h2 id="what"><?= e($toc['what']) ?></h2>
        <ul>
            <li><strong>Your own address</strong>, like ivieluxe.<?= $host ?>, to put in your bio and on your WhatsApp Status.</li>
            <li><strong>A page for everything you sell</strong>, with a search and your categories, and a page for each item.</li>
            <li><strong>A bag and a checkout.</strong> Customers pay into your own account and can send the transfer screenshot with the order. Nfelia takes no commission.</li>
            <li><strong>A contact page</strong> with your WhatsApp, a form for custom and bulk orders, and a map once you place your pin.</li>
            <li><strong>A tracking link for every order</strong>, and reviews from customers whose orders were delivered or collected.</li>
        </ul>
        <?= site_screens([['website-1', 'Ivie Luxe Hair\'s Nfelia website on a phone: bundles and wigs with photos, prices and Add buttons', 'The shop'], ['website-2', 'Checkout on Ivie Luxe Hair\'s website: pay by bank transfer into Ivie Luxe Hair\'s Moniepoint account', 'Checkout']]) ?>
        <p class="meta">Screens from Ivie Luxe Hair, an example business.</p>

        <h2 id="link"><?= e($toc['link']) ?></h2>
        <p>Nfelia asks for your link and look right after the setup screens, on <strong>Set up your shop</strong>. You need both to carry on.</p>
        <?= site_steps([
            ['Type your Shop name', 'Under <strong>Your shop link</strong>, type the name you want before .' . e($host) . '. Use 3 to 30 letters, numbers or hyphens; spaces become hyphens. Nfelia checks it as you type: you see <strong>yourname.' . e($host) . ' is free</strong>, or <strong>Free names you could use</strong> with a few to tap.'],
            ['Pick your look', 'Under <strong>Your look</strong>, pick the style that suits what you sell. Tap <strong>See it full size</strong> under any look to see your own shop in it, then <strong>Use this look</strong>.'],
            ['Tap Save my shop', 'You see <strong>Your shop is ready at yourname.' . e($host) . '</strong>. It is live: anyone with the link can see it and order.'],
        ]) ?>
        <?php if ($looks): ?>
        <div class="table-scroll">
            <table>
                <caption class="sr-only">The looks you can choose for your Nfelia website</caption>
                <thead><tr><th scope="col">Look</th><th scope="col">Made for</th></tr></thead>
                <tbody>
                    <?php foreach ($looks as $look): ?>
                    <tr><th scope="row"><?= e($look['name']) ?></th><td><?= e($look['for']) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <p>Any look works for any business, and you can switch looks as often as you like. Your products, photos and words stay as they are.</p>

        <h2 id="studio"><?= e($toc['studio']) ?></h2>
        <p>Open <strong>More › Shop Studio</strong> on a phone, or <strong>Shop Studio</strong> in the menu on a computer. The owner and admins can open it. On a phone, the preview of your website is on top and the controls are below; on a computer, the controls are on the left. Switch the preview between <strong>Phone</strong> and <strong>Computer</strong>, or tap <strong>Full preview</strong>.</p>
        <p>The controls have five tabs:</p>
        <ul>
            <li><strong>Look</strong>: pick a look to start from.</li>
            <li><strong>Colours</strong>: your brand colours from Settings, colours read from your logo, ready-made colour sets, or <strong>Your own colour</strong> for buttons and links. Every set is checked so your words stay easy to read.</li>
            <li><strong>Fonts</strong>: the lettering of your website.</li>
            <li><strong>Sections</strong>: what your website shows, in order. Tap a section to change its words and photos, switch it on or off, and drag it, or use the arrows, to move it.</li>
            <li><strong>More</strong>: how you show in Google, your shop link, switching your website on or off, sold-out items, earlier versions.</li>
        </ul>
        <?php if ($sections): ?>
        <p>These are the sections you can use:</p>
        <div class="table-scroll">
            <table>
                <caption class="sr-only">Sections of an Nfelia website and what each shows</caption>
                <thead><tr><th scope="col">Section</th><th scope="col">What it shows</th></tr></thead>
                <tbody>
                    <?php foreach ($sections as [$name, $hint]): ?>
                    <tr><th scope="row"><?= e($name) ?></th><td><?= e($hint) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <p>The photos at the top go in <strong>Top of your shop</strong>: up to five photos or videos. Your payment accounts and your logo come from <strong>Settings › Business</strong>, and Studio links you there.</p>

        <h2 id="publish"><?= e($toc['publish']) ?></h2>
        <p>Changes in Shop Studio are saved in a draft as you go: you see <strong>Draft saved. Publish when you're happy.</strong> Customers see nothing new until you publish.</p>
        <?= site_steps([
            ['Check the preview', 'Look at it as <strong>Phone</strong> and as <strong>Computer</strong>. <strong>Undo</strong> and <strong>Redo</strong> are at the top.'],
            ['Tap Publish', 'The button is at the top right. You see <strong>Published. Customers see this version now.</strong> and the button reads <strong>Published</strong>.'],
        ]) ?>
        <p>Some switches save at once, with no Publish: <strong>Customers can see my shop</strong>, the sold-out switch, and which items are on your website. Changed your mind after publishing? Under <strong>More › Earlier versions</strong>, bring one of your last ten publishes back into your draft, check it, then publish it.</p>

        <h2 id="share"><?= e($toc['share']) ?></h2>
        <p>In Shop Studio, open <strong>More</strong>. Under <strong>Your shop link</strong>, tap <strong>Copy link</strong> or <strong>Share on WhatsApp</strong>. Put the link in your Instagram and TikTok bio, on your WhatsApp Status and business profile, and on your business card. Once you pay for Nfelia, your website can also be found on Google: see <?= $to('help/get-found-on-google', 'how to get found on Google') ?>.</p>
        <p>Customers can share too. On your website, each item has <strong>Share</strong> and <strong>Image for your Status</strong>, which makes a picture of the item for a WhatsApp Status.</p>

        <h2 id="rename"><?= e($toc['rename']) ?></h2>
        <p>The owner can change the shop link once. In Shop Studio, open <strong>More</strong>, then under <strong>Your shop link</strong> tap <strong>Change link</strong>. Type the <strong>New shop name</strong> and tap <strong>Change my link</strong>. Your old link sends people to the new one for 90 days, so update it wherever you shared it. To change it a second time, contact support from <strong>More › Contact support</strong>.</p>

        <h2 id="switch-off"><?= e($toc['switch-off']) ?></h2>
        <p>In Shop Studio, open <strong>More</strong> and turn off <strong>Customers can see my shop</strong> under <strong>Show your shop</strong>. While it is off, your link says there is no shop at that address. Your products and orders stay as they are, and customers can still follow orders they already placed. Turn it back on any time.</p>

        <h2 id="trial"><?= e($toc['trial']) ?></h2>
        <ul>
            <li><strong>During the free trial</strong>, your website works for anyone with the link and takes orders. It is kept off Google until your first payment.</li>
            <li><strong>Once you pay</strong>, Google and other search engines may show it, and you can join the Nfelia shop directory. See <?= $to('help/get-found-on-google', 'get found on Google') ?>.</li>
            <li><strong>If the trial ends, or a paid month ends and a few days of grace pass, without a payment</strong>, your website shows a "closed for now" page with your WhatsApp button. Pay for a month and it opens again: see <?= $to('help/billing', 'paying for Nfelia') ?>. We keep your website and records for 12 months, and email you before anything is deleted.</li>
        </ul>

        <h2 id="problems"><?= e($toc['problems']) ?></h2>
        <?= site_faq([
            ['It says "Another shop already has this name"', 'Each link belongs to one shop. Tap one of the free names Nfelia offers, or add something to yours, like your area.'],
            ['It says the name needs a quick check by our team', 'Names that look like a bank, a phone network, a government office or an official account wait for our team to approve them. Your shop goes live as soon as it is approved. To go live now, choose another name.'],
            ['It says "That name is kept for Nfelia"', 'Some words, like shop, help or admin, are kept for Nfelia itself. Try another name.'],
            ['My changes don\'t show on my website', 'Tap <strong>Publish</strong> in Shop Studio: changes stay in your draft until you do. If you published and still see the old version, close the page and open it again after a few minutes.'],
            ['It says "Customers will see it once your shop link is live and switched on"', 'Your link is still waiting for a check, or <strong>Customers can see my shop</strong> is off. See the two answers above.'],
            ['Customers say there is nowhere to pay', 'Add an account in <strong>Settings › Business › Payment accounts</strong>. Without one, checkout tells customers you will contact them about payment.'],
            ['Can I use my own .com address?', 'Not for now. Your website lives at yourname.' . e($host) . '.'],
            ['Can I remove "Shop by Nfelia" from the footer?', 'It is an add-on. Ask us about it from <strong>More › Contact support</strong>, or on WhatsApp.'],
        ]) ?>
        <p>Still stuck? <a href="<?= e(site_wa('Hello Nfelia, I need help with my website.')) ?>" target="_blank" rel="noopener">Message us on WhatsApp</a> with your shop link.</p>
    </div>
</article>

<?= site_related(['help/get-found-on-google', 'help/website-orders', 'help/add-products', 'features/website']) ?>
<?= site_cta('Get your own website with Nfelia') ?>
