<?php
/**
 * The blog, part B: four guides for small businesses in Nigeria, Ghana,
 * Kenya and Cameroon (views/site/pages/blog/<slug>.php). Each answers one
 * thing people search for, in plain words, and ends with what Nfelia does
 * about it. The hub and the other posts are in their own list file.
 *
 * Facts about lenders, regulators and tax offices in these guides were
 * checked on 28 October 2026; review them when a guide is updated.
 */
return [
    'blog/business-records-for-a-loan' => [
        'path'        => '/blog/business-records-for-a-loan',
        'view'        => 'blog/business-records-for-a-loan',
        'title'       => 'Business records for a loan: what lenders ask for | Nfelia',
        'description' => 'The records banks, microfinance banks and SACCOs usually ask for before a business loan, and how to organise 6 to 12 months of sales, spending and statements.',
        'summary'     => 'What lenders usually ask to see before a small business loan, and how to get 6 to 12 months of records ready.',
        'crumb'       => 'Records for a loan',
        'parent'      => 'blog',
        'type'        => 'BlogPosting',
        'group'       => 'blog',
        'og'          => ['kicker' => 'Guide', 'title' => 'The business records lenders ask for, and how to get them ready'],
        'published'   => '2026-10-28',
        'updated'     => '2026-10-28',
        'order'       => 50,
    ],
    'blog/track-stock' => [
        'path'        => '/blog/track-stock',
        'view'        => 'blog/track-stock',
        'title'       => 'How to track stock in a small shop, step by step | Nfelia',
        'description' => 'Count your stock, keep a simple stock sheet, set a reorder level for each item and find out where goods go missing. With a stock sheet layout you can copy.',
        'summary'     => 'How to count stock, keep a stock sheet, set reorder levels and cut shrinkage in a small shop.',
        'crumb'       => 'Track your stock',
        'parent'      => 'blog',
        'type'        => 'BlogPosting',
        'group'       => 'blog',
        'og'          => ['kicker' => 'Guide', 'title' => 'Know what is on your shelves: a simple way to track stock'],
        'published'   => '2026-10-28',
        'updated'     => '2026-10-28',
        'order'       => 60,
    ],
    'blog/take-booking-deposits' => [
        'path'        => '/blog/take-booking-deposits',
        'view'        => 'blog/take-booking-deposits',
        'title'       => 'How to take booking deposits and stop no-shows | Nfelia',
        'description' => 'How much deposit to ask for, a booking and cancellation policy you can copy, and how to check a transfer or mobile money payment before you confirm a booking.',
        'summary'     => 'How much deposit to take, deposit and cancellation policies to copy, and how to check the payment is real.',
        'crumb'       => 'Booking deposits',
        'parent'      => 'blog',
        'type'        => 'BlogPosting',
        'group'       => 'blog',
        'og'          => ['kicker' => 'Guide', 'title' => 'Booking deposits: how much to ask, what to write, how to check'],
        'published'   => '2026-10-28',
        'updated'     => '2026-10-28',
        'order'       => 70,
    ],
    'blog/price-your-products' => [
        'path'        => '/blog/price-your-products',
        'view'        => 'blog/price-your-products',
        'title'       => 'How to price your products: markup vs margin | Nfelia',
        'description' => 'Work out what an item really costs you, set a price from the margin you want, price your services by the hour, and know when it is time to raise your prices.',
        'summary'     => 'How to price products and services: true cost, markup and margin side by side, pricing by time and when to raise prices.',
        'crumb'       => 'Pricing your products',
        'parent'      => 'blog',
        'type'        => 'BlogPosting',
        'group'       => 'blog',
        'og'          => ['kicker' => 'Guide', 'title' => 'Price your products so every sale makes you money'],
        'published'   => '2026-10-28',
        'updated'     => '2026-10-28',
        'order'       => 80,
    ],
];
