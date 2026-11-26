<?php
/**
 * The guides (the blog): the hub at /blog and the first four guides.
 * More guides are listed in blog_b.php; the hub shows every page in the
 * blog group, newest first.
 *
 * A guide's og title is its headline (the h1), which the schema uses too.
 */
return [
    'blog' => [
        'path'        => '/blog',
        'view'        => 'blog',
        'title'       => 'Guides for small businesses | Nfelia',
        'description' => 'Free guides for small businesses in Nigeria, Ghana, Kenya and Cameroon: invoices, getting paid, selling on WhatsApp, stock and profit, with things to copy.',
        'summary'     => 'Practical guides for small businesses: invoices, getting paid, selling online, stock and profit.',
        'crumb'       => 'Guides',
        'parent'      => null,
        'type'        => 'CollectionPage',
        'group'       => 'main',
        'og'          => ['kicker' => 'Guides', 'title' => 'Guides for small businesses'],
        'published'   => '2026-10-28',
        'updated'     => '2026-10-28',
        'order'       => 80,
    ],

    'blog/how-to-write-an-invoice' => [
        'path'        => '/blog/how-to-write-an-invoice',
        'view'        => 'blog/how-to-write-an-invoice',
        'title'       => 'How to write an invoice: simple guide and template | Nfelia',
        'description' => 'What every invoice must show, a sample layout to copy, payment terms that work and the mistakes that delay payment. For small businesses in Africa.',
        'summary'     => 'What every invoice must show, a sample layout to copy, and the habits that get you paid on time.',
        'crumb'       => 'How to write an invoice',
        'parent'      => 'blog',
        'type'        => 'BlogPosting',
        'group'       => 'blog',
        'og'          => ['kicker' => 'Guide', 'title' => 'How to write an invoice that gets you paid on time'],
        'published'   => '2026-10-28',
        'updated'     => '2026-10-28',
        'order'       => 10,
    ],

    'blog/collect-money-customers-owe' => [
        'path'        => '/blog/collect-money-customers-owe',
        'view'        => 'blog/collect-money-customers-owe',
        'title'       => 'How to collect money customers owe you | Nfelia',
        'description' => 'Get paid without losing the customer: when to remind, 8 polite-to-firm WhatsApp messages to copy, when to stop giving credit and what to do as a last step.',
        'summary'     => 'When to remind, eight WhatsApp messages from polite to firm, and when to stop giving credit.',
        'crumb'       => 'Collect money customers owe',
        'parent'      => 'blog',
        'type'        => 'BlogPosting',
        'group'       => 'blog',
        'og'          => ['kicker' => 'Guide', 'title' => 'Get paid by customers who owe you, and keep them'],
        'published'   => '2026-10-28',
        'updated'     => '2026-10-28',
        'order'       => 20,
    ],

    'blog/sell-on-whatsapp-and-instagram' => [
        'path'        => '/blog/sell-on-whatsapp-and-instagram',
        'view'        => 'blog/sell-on-whatsapp-and-instagram',
        'title'       => 'How to sell on WhatsApp and Instagram | Nfelia',
        'description' => 'A daily selling routine for WhatsApp and Instagram: what to set up once, what to post, ready replies to copy, and how one website link turns chats into orders.',
        'summary'     => 'What to set up once, a daily routine, replies to copy, and one link that turns chats into orders.',
        'crumb'       => 'Sell on WhatsApp and Instagram',
        'parent'      => 'blog',
        'type'        => 'BlogPosting',
        'group'       => 'blog',
        'og'          => ['kicker' => 'Guide', 'title' => 'A simple routine for selling on WhatsApp and Instagram'],
        'published'   => '2026-10-28',
        'updated'     => '2026-10-28',
        'order'       => 30,
    ],

    'blog/calculate-profit' => [
        'path'        => '/blog/calculate-profit',
        'view'        => 'blog/calculate-profit',
        'title'       => 'How to calculate profit for a small business | Nfelia',
        'description' => 'Work out your real profit in plain steps: sales, cost of goods and running costs, a worked example with simple numbers, and why your cash is not your profit.',
        'summary'     => 'Sales, cost of goods and running costs, a worked example, and why the cash you have is not your profit.',
        'crumb'       => 'Calculate your profit',
        'parent'      => 'blog',
        'type'        => 'BlogPosting',
        'group'       => 'blog',
        'og'          => ['kicker' => 'Guide', 'title' => 'Are you making money? How to work out your real profit'],
        'published'   => '2026-10-28',
        'updated'     => '2026-10-28',
        'order'       => 40,
    ],
];
