<?php
/**
 * Feature pages, part B: bookings, orders and deliveries, expenses and
 * profit, the Records Book, tasks and reminders. Part A (the website,
 * invoices, receipts, debts, stock) is in its own list; the hub at
 * /features shows every page in the features group, in 'order'.
 *
 * Each page's words were checked against the app (controllers, helpers and
 * views named in its view's docblock). Prices come from site_prices().
 */
return [
    'features/bookings' => [
        'path'        => '/features/bookings',
        'view'        => 'features/bookings',
        'title'       => 'Booking app with deposits for salons and services | Nfelia',
        'description' => 'Customers pick a free time on your website and pay the deposit by transfer. Confirm in one tap and it goes on your calendar. For salons, tailors and events.',
        'summary'     => 'Customers book a free time on your website and pay a deposit by transfer; you confirm in one tap and it goes on your calendar.',
        'crumb'       => 'Bookings',
        'parent'      => 'features',
        'type'        => 'WebPage',
        'group'       => 'features',
        'icon'        => 'calendar',
        'og'          => ['kicker' => 'Bookings with deposits', 'title' => 'Customers book a time and pay the deposit first'],
        'published'   => '2026-10-28',
        'updated'     => '2026-10-28',
        'order'       => 60,
    ],

    'features/orders' => [
        'path'        => '/features/orders',
        'view'        => 'features/orders',
        'title'       => 'Order tracking and proof of delivery app | Nfelia',
        'description' => 'Give every order a tracking link and every delivery a note your customer signs on their phone. Proof of delivery and reviews from real orders, in one app.',
        'summary'     => 'Website orders with the payment proof attached, a tracking link for every order, and delivery notes your customer signs on their phone.',
        'crumb'       => 'Orders and delivery',
        'parent'      => 'features',
        'type'        => 'WebPage',
        'group'       => 'features',
        'icon'        => 'truck',
        'og'          => ['kicker' => 'Orders and delivery', 'title' => 'Every order tracked, every delivery signed for'],
        'published'   => '2026-10-28',
        'updated'     => '2026-10-28',
        'order'       => 70,
    ],

    'features/profit' => [
        'path'        => '/features/profit',
        'view'        => 'features/profit',
        'title'       => 'Profit and loss report and expense tracker | Nfelia',
        'description' => 'Write down what you spend in seconds and see money in, money out and what is left for any month. A plain-words profit and loss report, as a PDF too.',
        'summary'     => 'An expense tracker and a profit and loss report in plain words: money in, money out and what is left, for any month, as a PDF too.',
        'crumb'       => 'Expenses and profit',
        'parent'      => 'features',
        'type'        => 'WebPage',
        'group'       => 'features',
        'icon'        => 'chart',
        'og'          => ['kicker' => 'Expenses and profit', 'title' => 'Know what you really made this month'],
        'published'   => '2026-10-28',
        'updated'     => '2026-10-28',
        'order'       => 80,
    ],

    'features/records-book' => [
        'path'        => '/features/records-book',
        'view'        => 'features/records-book',
        'title'       => 'Business records for a bank loan, in Excel | Nfelia',
        'description' => 'All your business records in one Excel workbook for your bank or lender: sales, money, stock and customers. Anyone can check it is genuine at nfelia.com/verify.',
        'summary'     => 'Every record you keep in Nfelia in one Excel workbook in your colours, with codes anyone can check at nfelia.com/verify.',
        'crumb'       => 'Records Book',
        'parent'      => 'features',
        'type'        => 'WebPage',
        'group'       => 'features',
        'icon'        => 'sheet',
        'og'          => ['kicker' => 'Records Book', 'title' => 'Your records in one Excel book, ready for the bank'],
        'published'   => '2026-10-28',
        'updated'     => '2026-10-28',
        'order'       => 90,
    ],

    'features/tasks' => [
        'path'        => '/features/tasks',
        'view'        => 'features/tasks',
        'title'       => 'Business reminders, tasks and calendar app | Nfelia',
        'description' => 'Tasks, appointments and the dates Nfelia already knows, on one list and one calendar. Reminders on your phone and by email, and your day sent each morning.',
        'summary'     => 'Tasks, appointments, deliveries and money due on one list and calendar, with reminders on your phone and by email.',
        'crumb'       => 'Tasks and reminders',
        'parent'      => 'features',
        'type'        => 'WebPage',
        'group'       => 'features',
        'icon'        => 'clock',
        'og'          => ['kicker' => 'Tasks and reminders', 'title' => 'Never miss a delivery, fitting or payment date'],
        'published'   => '2026-10-28',
        'updated'     => '2026-10-28',
        'order'       => 100,
    ],
];
