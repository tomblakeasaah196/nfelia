<?php
/**
 * Where everything in the app lives. One list feeds the desktop sidebar and
 * the More page, so both always show the same places to the same people.
 */

/** 24px line icons, drawn with stroke="currentColor". */
function nav_icon(string $name): string
{
    static $paths = [
        'home'      => '<path d="M4 10.5 12 4l8 6.5V20h-5v-6H9v6H4Z"/>',
        'sales'     => '<path d="M6 2.5h12v19l-3-2-3 2-3-2-3 2Z"/><path d="M9 7.5h6M9 11h6M9 14.5h3.5"/>',
        'tasks'     => '<rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/><path d="m9 14.8 2 2 4-4"/>',
        'more'      => '<rect x="4" y="4" width="6.5" height="6.5" rx="1.6"/><rect x="13.5" y="4" width="6.5" height="6.5" rx="1.6"/><rect x="4" y="13.5" width="6.5" height="6.5" rx="1.6"/><rect x="13.5" y="13.5" width="6.5" height="6.5" rx="1.6"/>',
        'plus'      => '<path d="M12 5v14M5 12h14"/>',
        'invoice'   => '<path d="M14 3H6.5A1.5 1.5 0 0 0 5 4.5v15A1.5 1.5 0 0 0 6.5 21h11a1.5 1.5 0 0 0 1.5-1.5V8Z"/><path d="M14 3v5h5M8.5 13h7M8.5 17h4.5"/>',
        'quote'     => '<path d="M14 3H6.5A1.5 1.5 0 0 0 5 4.5v15A1.5 1.5 0 0 0 6.5 21h11a1.5 1.5 0 0 0 1.5-1.5V8Z"/><path d="M14 3v5h5M12 11.5v6M9 14.5h6"/>',
        'payment'   => '<circle cx="12" cy="12" r="9"/><path d="M9 16V8l6 8V8M7 11h10M7 13.2h10"/>',
        'expense'   => '<path d="M3 7.5A2.5 2.5 0 0 1 5.5 5H18v4"/><path d="M3 7.5V18a2 2 0 0 0 2 2h14a1 1 0 0 0 1-1v-9a1 1 0 0 0-1-1H5.5A2.5 2.5 0 0 1 3 7.5Z"/><path d="M16 14.5h.01"/>',
        'task'      => '<circle cx="12" cy="12" r="9"/><path d="m8 12.2 2.8 2.8L16 9.5"/>',
        'customer'  => '<circle cx="9.5" cy="8" r="3.5"/><path d="M3 20a6.5 6.5 0 0 1 13 0M19 8v6M16 11h6"/>',
        'clients'   => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.6a3.5 3.5 0 0 1 0 6.8M18 14.3a6.4 6.4 0 0 1 3.5 5.7"/>',
        'stock'     => '<path d="M21 7.5 12 3 3 7.5 12 12l9-4.5Z"/><path d="M3 7.5v9L12 21l9-4.5v-9M12 12v9"/>',
        'supplier'  => '<path d="M3 21V9l9-6 9 6v12"/><path d="M9 21v-7h6v7M3 21h18"/>',
        'delivery'  => '<path d="M2.5 6.5h11v9.5h-11zM13.5 10h4l3 3.2V16h-7"/><circle cx="6.5" cy="17.5" r="2"/><circle cx="16.5" cy="17.5" r="2"/>',
        'debt'      => '<path d="M12 3v18M17 7.5a4 4 0 0 0-4-2.5h-2a3 3 0 0 0 0 6h2a3 3 0 0 1 0 6h-2a4 4 0 0 1-4-2.5"/>',
        'report'    => '<path d="M4 20h16M7 16v-4M12 16V6M17 16v-7"/>',
        'orders'    => '<path d="M6 7h12l-1 13H7L6 7Z"/><path d="M9 7a3 3 0 0 1 6 0"/>',
        'globe'     => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 3.8 5.6 3.8 9S14.5 18.4 12 21M12 3C9.5 5.6 8.2 8.6 8.2 12s1.3 6.4 3.8 9"/>',
        'gift'      => '<rect x="3.5" y="8" width="17" height="4" rx="1"/><path d="M5 12v8h14v-8M12 8v12M12 8c-1.5-4-6-4-6-1.5S10 8 12 8Zm0 0c1.5-4 6-4 6-1.5S14 8 12 8Z"/>',
        'trash'     => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
        'settings'  => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>',
        'card'      => '<rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M2.5 10h19M6 15h4"/>',
        'phone'     => '<rect x="6.5" y="2.5" width="11" height="19" rx="2.5"/><path d="M10.5 18.5h3"/>',
        'book'      => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5v-15Z"/><path d="M4 20.5A2.5 2.5 0 0 0 6.5 23H20v-5"/>',
        'chat'      => '<path d="M20.5 12a8.5 8.5 0 0 1-12.4 7.5L3.5 20.5l1-4.4A8.5 8.5 0 1 1 20.5 12Z"/>',
        'signout'   => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l5-5-5-5M15 12H3"/>',
        'shield'    => '<path d="M12 3 4.5 6v5.5c0 4.5 3.2 8.4 7.5 9.5 4.3-1.1 7.5-5 7.5-9.5V6L12 3Z"/><path d="m9 12 2 2 4-4"/>',
        'money'     => '<rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/>',
        'chevron'   => '<path d="m9 6 6 6-6 6"/>',
        'back'      => '<path d="M15 5 8 12l7 7"/>',
        'bell'      => '<path d="M6 16v-5a6 6 0 1 1 12 0v5l1.5 2h-15Z"/><path d="M10 21h4"/>',
        'close'     => '<path d="M18 6 6 18M6 6l12 12"/>',
        'fingerprint' => '<path d="M12 10a2 2 0 0 0-2 2c0 1.02-.1 2.51-.26 4M14 13.12c0 2.38 0 6.38-1 8.88M17.29 21.02c.12-.6.43-2.3.5-3.02M2 12a10 10 0 0 1 18-6M2 16h.01M21.8 16c.2-2 .13-5.35 0-6M5 19.5C5.5 18 6 15 6 12a6 6 0 0 1 .34-2M8.65 22c.21-.66.45-1.32.57-2M9 6.8a6 6 0 0 1 9 5.2v2"/>',
        'textsize'  => '<path d="m2.5 18 5-12 5 12M4.6 13.5h5.8"/><path d="M14.5 18v-4a2.75 2.75 0 0 1 5.5 0v4M14.5 15.5H20"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . ($paths[$name] ?? '') . '</svg>';
}

/**
 * Every place, grouped, filtered to what this person may open.
 * @return array<string, array<int, array{label:string, url:string, icon:string}>>
 */
function nav_groups(string $role, string $business_type, int $business_id): array
{
    $all  = ['owner', 'admin', 'staff', 'manager'];
    $boss = ['owner', 'admin'];
    $groups = [
        'Your business' => [
            ['Home', 'dashboard', 'home', $all],
            ['Tasks and calendar', 'tasks', 'tasks', $all],
            ['Quotations', 'quotations', 'quote', $all],
            ['Invoices', 'invoices', 'invoice', $all],
            ['Receipts', 'receipts', 'payment', $all],
            [$business_type === 'service' ? 'Services' : 'Stock', 'products', 'stock', $all],
            ['Clients', 'customers', 'clients', $all],
            ['Suppliers', 'suppliers', 'supplier', $all],
            ['Deliveries', 'deliveries', 'delivery', $all],
            ['Debts', 'debts', 'debt', $boss],
            ['Expenses', 'expenses', 'expense', $boss],
            ['Profit and loss', 'reports/profit-loss', 'report', $boss],
        ],
        'Storefront' => [
            ['Orders', 'orders', 'orders', $all],
            ['Landing page', 'landing-page/settings', 'globe', $boss],
            ['Referrals', 'referrals', 'gift', $all],
        ],
        'Data' => [
            ['Purge data', 'purge', 'trash', ['owner']],
        ],
    ];
    if ($business_id === 1) {
        $groups['Founder panel'] = [
            ['Admin panel', 'admin', 'shield', $all],
            ['Countries', 'countries', 'globe', $all],
            ['MoMo review', 'momo/review', 'money', $all],
        ];
    }
    $out = [];
    foreach ($groups as $name => $items) {
        foreach ($items as [$label, $url, $icon, $access]) {
            if (in_array($role, $access, true)) {
                $out[$name][] = ['label' => $label, 'url' => $url, 'icon' => $icon];
            }
        }
    }
    return $out;
}

/** The bottom tab a section belongs to. Anything else lives under More. */
function nav_tab_for(string $section): string
{
    return match ($section) {
        'dashboard', '' => 'home',
        'invoices', 'receipts', 'quotations' => 'sales',
        'tasks' => 'tasks',
        default => 'more',
    };
}
