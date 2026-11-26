<?php
/**
 * Guide: selling on WhatsApp and Instagram (/blog/sell-on-whatsapp-and-instagram).
 * App features named here (WhatsApp Business quick replies, labels,
 * greeting and away messages; Status lasting 24 hours; Instagram
 * professional accounts and pinned posts) are the apps' own, described
 * without limits or numbers that change.
 *
 * From site_render: $page, $country.
 */
require_once BASE_PATH . '/views/home/_icons.php';   // nf_icon(): the layout loads it only after the view

$sections = [
    'set-up'        => 'Set up once',
    'one-link'      => 'One link for everything you sell',
    'routine'       => 'Your daily routine',
    'what-to-post'  => 'What to post so people buy',
    'replies'       => 'Ready replies to copy',
    'chat-to-order' => 'From chat to paid order',
    'mistakes'      => 'Mistakes that cost sales',
    'nfelia'        => 'How Nfelia helps',
];
$h2   = fn(string $id) => '<h2 id="' . e($id) . '">' . e($sections[$id]) . '</h2>';
$link = fn(string $key, string $text) => site_page($key) ? '<a href="' . e(site_url($key)) . '">' . e($text) . '</a>' : e($text);
?>
<article class="wrap article-wrap has-aside">
    <header class="article-head">
        <p class="kicker">Guide</p>
        <h1><?= e($page['og']['title']) ?></h1>
        <p class="page-lead">Selling in chats works. What wears you out is answering the same questions all day. A short routine, a few saved replies and one link with every price fix most of that.</p>
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
        <p>To sell on WhatsApp and Instagram, use the free WhatsApp Business app and an Instagram professional account, put one link with all your products and prices in both profiles, post a little every day, and answer common questions with saved replies. Then turn every chat into an order the same way: confirm it, send the total and payment details, check the money has arrived, send a receipt and send the goods. This guide gives you the routine, what to post, and replies you can copy today.</p>

        <?= $h2('set-up') ?>
        <p>Do these once, in an afternoon.</p>
        <h3>WhatsApp</h3>
        <ul>
            <li><strong>Use the WhatsApp Business app</strong>, which is free, on a number you keep for the business. Customers stay apart from family chats.</li>
            <li><strong>Fill in your business profile:</strong> what you sell, your area, your opening hours, your address if people can visit, and your website link.</li>
            <li><strong>Set up quick replies:</strong> saved answers you send by typing / in a chat. Start with the ones in <a href="#replies">Ready replies to copy</a>.</li>
            <li><strong>Use labels</strong> such as New order, Waiting for payment, Paid and Delivered, so you can see at a glance who needs what.</li>
            <li><strong>Turn on a greeting message</strong> for people who message you for the first time, and an away message for when you are closed.</li>
        </ul>
        <h3>Instagram</h3>
        <ul>
            <li><strong>Switch to a professional account.</strong> It adds contact buttons to your profile and shows you which posts reach people.</li>
            <li><strong>Write a bio that answers three questions:</strong> what do you sell, where are you, and how do people order?</li>
            <li><strong>Put your website link in your bio,</strong> first if you have more than one.</li>
            <li><strong>Pin your most useful posts</strong> to the top of your profile: your best sellers, how to order, and customer reviews.</li>
        </ul>
        <p>A bio you can copy and change, written for an example hair business:</p>
        <p class="template">Human hair bundles and wigs
Lekki, Lagos · Delivery nationwide
Order on our website: link below</p>

        <?= $h2('one-link') ?>
        <p>The question you hear most is “How much?”. A price list sent as a picture gets lost in the chat, goes out of date and cannot be tapped. A website with every product, price and photo answers that question once, for everyone, all day.</p>
        <p>Put that one link everywhere: your Instagram bio, your WhatsApp business profile, your Status, the end of every price reply, your receipts, your packaging stickers and your flyers. When someone asks about one product, send the link to that product, not a screenshot.</p>
        <p>A good selling link:</p>
        <ul>
            <li>shows every product with its price and photos,</li>
            <li>makes clear what is in stock and what is sold out,</li>
            <li>lets customers choose a size, colour and quantity,</li>
            <li>says how to pay and how delivery works,</li>
            <li>and sends the order to you with the customer’s name and phone number.</li>
        </ul>

        <?= $h2('routine') ?>
        <p>Selling online is a little every day, not a lot once a week. Status updates disappear after 24 hours, and a quiet profile looks closed. Here is a routine of about an hour a day, spread out:</p>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr><th scope="col">When</th><th scope="col">What to do</th></tr>
                </thead>
                <tbody>
                    <tr><th scope="row">Morning, 15 minutes</th><td>Answer messages that came in overnight. Post 2 to 4 Status updates: a new arrival, a best seller with its price, a customer review, or work in progress.</td></tr>
                    <tr><th scope="row">Late morning</th><td>Post once on Instagram: a photo, a short video or a story, with the price and how to order. Reply to comments and messages.</td></tr>
                    <tr><th scope="row">Afternoon</th><td>Check payments in your bank or mobile money app. Send receipts. Pack, dispatch, and tell each customer their order is on its way.</td></tr>
                    <tr><th scope="row">Evening, 10 minutes</th><td>Follow up people who asked but did not buy. Check who still owes you. Plan tomorrow’s posts.</td></tr>
                </tbody>
            </table>
        </div>

        <?= $h2('what-to-post') ?>
        <p>People buy what they can see clearly, at a price they know.</p>
        <ul>
            <li><strong>Show the price.</strong> Many buyers will not message to ask. “DM for price” loses them.</li>
            <li><strong>One product per post:</strong> its name, price, sizes or colours, and how to order.</li>
            <li><strong>Good light and a plain background.</strong> Daylight by a window is enough.</li>
            <li><strong>Real reviews and customer photos,</strong> with the customer’s permission.</li>
            <li><strong>New arrivals and restocks:</strong> “Back in stock: [product], [price].”</li>
            <li><strong>Before and after</strong> for services: hair, make-up, tailoring, cleaning, repairs.</li>
            <li><strong>Behind the scenes:</strong> packing orders, the workshop, the kitchen. It shows you are real.</li>
            <li><strong>How to order,</strong> step by step, once a week.</li>
        </ul>
        <p>A caption you can reuse for any product:</p>
        <p class="template">[Product name]
[Price] · [sizes or colours]
In stock now. Delivery to [areas] in [number] days.
Order on our website (link in bio) or send us a message.</p>

        <?= $h2('replies') ?>
        <p>Save these as quick replies in WhatsApp Business, and keep them in your phone’s notes for Instagram messages. Fill in the parts in square brackets once, and they are ready every time.</p>
        <h3>Price</h3>
        <p class="template">Thank you for asking. Every product and price is on our website: [link]
Tap a product to see sizes and photos, then add it to your bag. If you have a question, ask me here.</p>
        <h3>How to pay</h3>
        <p class="template">Here are our payment details:
[bank name], [account name], [account number]
or mobile money: [number], [registered name]
Please send the screenshot here once you have paid, with your name.</p>
        <h3>Delivery</h3>
        <p class="template">We deliver to [areas]. Delivery costs [amount] and takes [number] days.
You can also pick up from [place], [days and hours].</p>
        <h3>Payment received</h3>
        <p class="template">Thank you, [Name]. Your payment of [amount] has arrived.
Your order will be sent on [day]. Here is your receipt: [link]</p>
        <h3>On its way</h3>
        <p class="template">Your order is on its way with [rider or company]. Expect it [day and time].
Rider’s number: [number]</p>

        <?= $h2('chat-to-order') ?>
        <p>Handle every order the same way, and you will never wonder who paid or what you promised.</p>
        <ol>
            <li><strong>Confirm the order in writing:</strong> item, size or colour, quantity and delivery address.</li>
            <li><strong>Send the total, with delivery, in one message.</strong></li>
            <li><strong>Send your payment details</strong> with your saved reply.</li>
            <li><strong>Check the money in your own bank or mobile money app</strong> before you send anything. A screenshot or a forwarded alert is not proof; your own account is.</li>
            <li><strong>Send a receipt.</strong></li>
            <li><strong>Send the goods</strong> and tell the customer when to expect them.</li>
            <li><strong>Ask for a review</strong> a day or two after delivery.</li>
        </ol>
        <p>An order confirmation you can copy:</p>
        <p class="template">Thank you, [Name]. Here is your order:
[quantity] × [item], [size or colour]: [amount]
Delivery to [area]: [amount]
Total: [total]
Please pay to [account details] and send the screenshot here. I will confirm as soon as it arrives.</p>
        <p>If a customer pays part now and the rest later, write the balance down the same day. Our guide to <?= $link('blog/collect-money-customers-owe', 'collecting money customers owe') ?> has reminder messages for when the date comes.</p>

        <?= $h2('mistakes') ?>
        <p>Small habits lose more sales than bad products do. Watch for these:</p>
        <ul>
            <li><strong>Replying hours late.</strong> A buyer who waits often buys from someone else.</li>
            <li><strong>Hiding prices,</strong> so only the most patient buyers ask.</li>
            <li><strong>One number for family and business,</strong> so orders get buried.</li>
            <li><strong>No record of who paid,</strong> so goods go out twice, or not at all.</li>
            <li><strong>Ten posts in one hour, then nothing for a week.</strong></li>
            <li><strong>Never following up</strong> people who asked about a product.</li>
            <li><strong>Arguing in public comments.</strong> Move it to a private chat and sort it out there.</li>
        </ul>

        <?= $h2('nfelia') ?>
        <p>Nfelia gives you that one link: your own website, like ivieluxe.nfelia.com for the example business Ivie Luxe Hair, with every product and price. It works with the way you already sell:</p>
        <ul>
            <li>Each product has its own page and link, so you can send exactly what the customer asked about.</li>
            <li>On any product, Image for your Status makes a picture with the product’s link on it, ready for your WhatsApp Status or Instagram story.</li>
            <li>Customers add to their bag and pay into your own bank or mobile money account, with the transfer screenshot attached, or they send the order to you on WhatsApp instead. No commission.</li>
            <li>Every order lands in Nfelia with the customer saved, and the customer gets a link to track it.</li>
            <li>Check the payment, approve, and the invoice and receipt are made for you.</li>
            <li>Once you subscribe, Google can show your website in search results.</li>
        </ul>
        <?= site_screens([
            ['website-1', 'Ivie Luxe Hair’s website: bundles and wigs with photos, prices and Add buttons', 'Every price, one link'],
            ['website-2', 'Checkout on Ivie’s website: pay ₦65,000 by bank transfer to Ivie Luxe Hair’s Moniepoint account', 'Paid into your account'],
        ]) ?>
        <p>See what your <?= $link('features/website', 'own website') ?> looks like, how <?= $link('features/orders', 'online orders and tracking links') ?> work, and the steps to <?= $link('help/your-website', 'set up your website') ?> and <?= $link('help/get-found-on-google', 'get found on Google') ?>. Selling mostly online? There is a page <?= $link('for/online-sellers', 'for online sellers') ?> too.</p>
    </div>
</article>

<?= site_related(['features/website', 'help/website-orders', 'features/orders', 'for/online-sellers', 'blog/price-your-products', 'blog/track-stock']) ?>
<?= site_cta('Get your own website with every price') ?>
