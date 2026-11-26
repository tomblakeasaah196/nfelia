<?php
/**
 * The landing page itself (views/home, served by index.php to visitors who
 * are not signed in). It is in the list so breadcrumbs, the sitemap,
 * llms.txt and the country pages (hreflang) know it; it has no view here.
 */
return [
    'home' => [
        'path'        => '/',
        'view'        => null,
        'title'       => 'Nfelia: website, invoices, debts and stock in Nigeria',
        'description' => 'Your own website at yourname.nfelia.com, plus invoices, receipts, debts and stock, all on your phone. Free for ' . (int)TRIAL_DAYS . ' days, no card needed, with free setup help.',
        'summary'     => 'What Nfelia is, what it does for a small business, and what it costs in Nigeria.',
        'crumb'       => 'Nfelia',
        'parent'      => null,
        'type'        => 'WebPage',
        'group'       => 'main',
        'country'     => 'NG',
        'alternates'  => 'home',
        'image'       => '/assets/img/nfelia-og-banner.png',
        'updated'     => '2026-10-28',
        'order'       => 0,
    ],
];
