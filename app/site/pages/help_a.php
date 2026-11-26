<?php
/**
 * The help centre: the hub at /help and the first eight articles (setting
 * up, products, the website, being found, website orders, invoices,
 * receipts, the app). More articles are listed in help_b.php.
 *
 * Every article has a 'topic', one of the hub's headings, in this order:
 * Start, Sell, Get paid, Run your business, Your account. The hub
 * (views/site/pages/help.php) lists every page in the help group under its
 * topic, by 'order'. An article's og title is its headline (the h1), which
 * the schema uses too.
 *
 * Every step names the app's own buttons and menus, as the views had them
 * on 28 October 2026. When a screen changes its words, its article changes
 * with it.
 */
return [
    'help' => [
        'path'        => '/help',
        'view'        => 'help',
        'title'       => 'Help centre: step-by-step guides to Nfelia | Nfelia',
        'description' => 'Step-by-step help for Nfelia: set up, add products, run your website, take orders, send invoices and receipts, and install the app on your phone.',
        'summary'     => 'Step-by-step guides to every job in Nfelia, with the real button names.',
        'crumb'       => 'Help centre',
        'parent'      => null,
        'type'        => 'CollectionPage',
        'group'       => 'main',
        'og'          => ['kicker' => 'Help centre', 'title' => 'Help with Nfelia, step by step'],
        'published'   => '2026-10-28',
        'updated'     => '2026-10-28',
        'order'       => 70,
    ],

    /* ── Start ── */

    'help/getting-started' => [
        'path'        => '/help/getting-started',
        'view'        => 'help/getting-started',
        'title'       => 'How to get started with Nfelia | Nfelia Help',
        'description' => 'Sign up free, tell Nfelia about your business, add where customers pay you and your logo, then pick your shop link and look. What each screen asks.',
        'summary'     => 'Sign up, the four setup screens, and the first things to do once you are in.',
        'crumb'       => 'Get started',
        'parent'      => 'help',
        'type'        => 'TechArticle',
        'group'       => 'help',
        'topic'       => 'Start',
        'og'          => ['kicker' => 'Help centre', 'title' => 'Set up Nfelia in four steps'],
        'published'   => '2026-10-28',
        'updated'     => '2026-10-28',
        'order'       => 10,
    ],

    'help/install-the-app' => [
        'path'        => '/help/install-the-app',
        'view'        => 'help/install-the-app',
        'title'       => 'How to install the Nfelia app on your phone | Nfelia Help',
        'description' => 'Install Nfelia from your browser on Android or iPhone in under a minute, with no app store. Then turn on phone alerts, and fix the usual install problems.',
        'summary'     => 'Add Nfelia to your home screen on Android or iPhone, then turn on phone alerts.',
        'crumb'       => 'Install the app',
        'parent'      => 'help',
        'type'        => 'TechArticle',
        'group'       => 'help',
        'topic'       => 'Start',
        'og'          => ['kicker' => 'Help centre', 'title' => 'Put Nfelia on your home screen'],
        'published'   => '2026-10-28',
        'updated'     => '2026-10-28',
        'order'       => 20,
    ],

    'help/add-products' => [
        'path'        => '/help/add-products',
        'view'        => 'help/add-products',
        'title'       => 'How to add products on Nfelia, one or many | Nfelia Help',
        'description' => 'Add an item with photos, price, cost and stock, or many at once from a spreadsheet. Choose what shows on your website and fix the usual import errors.',
        'summary'     => 'One item with photos and options, or many at once from a spreadsheet.',
        'crumb'       => 'Add products',
        'parent'      => 'help',
        'type'        => 'TechArticle',
        'group'       => 'help',
        'topic'       => 'Start',
        'og'          => ['kicker' => 'Help centre', 'title' => 'Add your products and prices'],
        'published'   => '2026-10-28',
        'updated'     => '2026-10-28',
        'order'       => 30,
    ],

    /* ── Sell ── */

    'help/your-website' => [
        'path'        => '/help/your-website',
        'view'        => 'help/your-website',
        'title'       => 'How to set up your shop website on Nfelia | Nfelia Help',
        'description' => 'Pick your shop link and one of 10 looks, then change colours, photos and sections in Shop Studio and publish. Share your link, rename it or switch it off.',
        'summary'     => 'Your shop link, the 10 looks, Shop Studio and Publish, and sharing your link.',
        'crumb'       => 'Your website',
        'parent'      => 'help',
        'type'        => 'TechArticle',
        'group'       => 'help',
        'topic'       => 'Sell',
        'og'          => ['kicker' => 'Help centre', 'title' => 'Set up and change your website'],
        'published'   => '2026-10-28',
        'updated'     => '2026-10-28',
        'order'       => 40,
    ],

    'help/get-found-on-google' => [
        'path'        => '/help/get-found-on-google',
        'view'        => 'help/get-found-on-google',
        'title'       => 'How to get your Nfelia shop on Google | Nfelia Help',
        'description' => 'When Google can show your Nfelia shop, why trial shops stay hidden, how to join the Nfelia shop directory, and what helps people find you. It takes time.',
        'summary'     => 'Paid shops are offered to Google, trial shops stay hidden, and the directory is your choice.',
        'crumb'       => 'Get found on Google',
        'parent'      => 'help',
        'type'        => 'TechArticle',
        'group'       => 'help',
        'topic'       => 'Sell',
        'og'          => ['kicker' => 'Help centre', 'title' => 'Get your shop found on Google'],
        'published'   => '2026-10-28',
        'updated'     => '2026-10-28',
        'order'       => 50,
    ],

    'help/website-orders' => [
        'path'        => '/help/website-orders',
        'view'        => 'help/website-orders',
        'title'       => 'How to handle orders from your website | Nfelia Help',
        'description' => 'What happens when a customer orders on your Nfelia website: check the transfer, confirm, send it out or mark it ready, and keep the customer told at each step.',
        'summary'     => 'Check the payment, confirm the order, send it out, and keep the customer told.',
        'crumb'       => 'Website orders',
        'parent'      => 'help',
        'type'        => 'TechArticle',
        'group'       => 'help',
        'topic'       => 'Sell',
        'og'          => ['kicker' => 'Help centre', 'title' => 'Handle orders from your website'],
        'published'   => '2026-10-28',
        'updated'     => '2026-10-28',
        'order'       => 60,
    ],

    /* ── Get paid ── */

    'help/send-an-invoice' => [
        'path'        => '/help/send-an-invoice',
        'view'        => 'help/send-an-invoice',
        'title'       => 'How to send an invoice on Nfelia | Nfelia Help',
        'description' => 'Make an invoice from a new order in about a minute, send it on WhatsApp or as a PDF, show the deposit and the balance, and record payments as they come in.',
        'summary'     => 'Every invoice starts as an order: add the items, save, then send it on WhatsApp.',
        'crumb'       => 'Send an invoice',
        'parent'      => 'help',
        'type'        => 'TechArticle',
        'group'       => 'help',
        'topic'       => 'Get paid',
        'og'          => ['kicker' => 'Help centre', 'title' => 'Send an invoice and get paid'],
        'published'   => '2026-10-28',
        'updated'     => '2026-10-28',
        'order'       => 70,
    ],

    'help/receipts-and-payments' => [
        'path'        => '/help/receipts-and-payments',
        'view'        => 'help/receipts-and-payments',
        'title'       => 'How to record payments and send receipts | Nfelia Help',
        'description' => 'Record a cash, transfer, POS or mobile money payment and Nfelia makes the receipt, ready for WhatsApp. The balance, Debts and your monthly total update too.',
        'summary'     => 'Every payment you record makes a receipt. Find it, send it, or put a mistake right.',
        'crumb'       => 'Receipts and payments',
        'parent'      => 'help',
        'type'        => 'TechArticle',
        'group'       => 'help',
        'topic'       => 'Get paid',
        'og'          => ['kicker' => 'Help centre', 'title' => 'Record payments and send receipts'],
        'published'   => '2026-10-28',
        'updated'     => '2026-10-28',
        'order'       => 80,
    ],
];
