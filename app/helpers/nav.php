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
        'bookings'  => '<rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4M8 14h3M8 17h6"/>',
        'news'      => '<path d="M5 4h10.5L19 7.5V20H5Z"/><path d="M8 9h5M8 12.5h8M8 16h8"/>',
        'studio'    => '<path d="M4 20h16M6 16l9.5-9.5a2.1 2.1 0 0 1 3 3L9 19H6Z"/><path d="m13.5 8.5 2 2"/>',
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
        'search'    => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'share'     => '<path d="M12 3v12M7.5 7.5 12 3l4.5 4.5M6 11H5a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-8a1 1 0 0 0-1-1h-1"/>',
        'whatsapp'  => '<path d="M3.6 20.4 4.9 16.2a8.5 8.5 0 1 1 3.1 3Z"/><path d="M9 8.7c.2 3.2 3 5.9 6.3 6.3l1.2-1.5-2.1-1-.9.8a4.6 4.6 0 0 1-2.8-2.8l.8-.9-1-2.1Z"/>',
        'copy'      => '<rect x="8.5" y="8.5" width="12" height="12" rx="2"/><path d="M15.5 8.5V5A1.5 1.5 0 0 0 14 3.5H5A1.5 1.5 0 0 0 3.5 5v9A1.5 1.5 0 0 0 5 15.5h3.5"/>',
        'download'  => '<path d="M12 4v11M7.5 10.5 12 15l4.5-4.5M5 19.5h14"/>',
        'edit'      => '<path d="M4 20h4L19 9a2.1 2.1 0 0 0-3-3L5 17Z"/><path d="m14.5 7.5 3 3"/>',
        'dots'      => '<circle cx="5" cy="12" r="1.3"/><circle cx="12" cy="12" r="1.3"/><circle cx="19" cy="12" r="1.3"/>',
        'open'      => '<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
        'send'      => '<path d="M21 3 10 14M21 3l-7 18-4-7-7-4Z"/>',
        'cancel'    => '<circle cx="12" cy="12" r="9"/><path d="m5.6 5.6 12.8 12.8"/>',
        'alert'     => '<path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/>',
        'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'check'     => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'receipt'   => '<path d="M6 2.5h12v19l-3-2-3 2-3-2-3 2Z"/><path d="M9 8.5h6M9 12h6"/>',
        'info'      => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5.5M12 7.8h.01"/>',
        'call'      => '<path d="M5.2 3.5h3.3l1.7 4.4-2.2 1.4a11.5 11.5 0 0 0 6.7 6.7l1.4-2.2 4.4 1.7v3.3a1.7 1.7 0 0 1-1.8 1.7A16.8 16.8 0 0 1 3.5 5.3a1.7 1.7 0 0 1 1.7-1.8Z"/>',
        'history'   => '<path d="M3.5 12A8.5 8.5 0 1 0 6 6L3.5 8.5"/><path d="M3.5 4v4.5H8"/><path d="M12 7.5V12l3 2"/>',
        'bolt'      => '<path d="M13 2.5 4.5 13.5H12l-1 8 8.5-11H12l1-8Z"/>',
        'hand'      => '<path d="M8 12.5V5.5a1.5 1.5 0 0 1 3 0V11M11 10.5V4a1.5 1.5 0 0 1 3 0v6.5M14 10.5V5.5a1.5 1.5 0 0 1 3 0V12"/><path d="M17 8.5a1.5 1.5 0 0 1 3 0V14a7 7 0 0 1-7 7h-1.3a6 6 0 0 1-4.6-2.2l-3-3.7a1.5 1.5 0 0 1 2.3-1.9L8 15"/>',
        'refund'    => '<path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/>',
        'pin'       => '<path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11Z"/><circle cx="12" cy="10" r="2.5"/>',
        'image'     => '<rect x="3.5" y="4.5" width="17" height="15" rx="2"/><circle cx="9" cy="9.5" r="1.8"/><path d="m20.5 15.5-4.5-4.5L6 19.5"/>',
        'user'      => '<circle cx="12" cy="8" r="4"/><path d="M4.5 20.5a7.5 7.5 0 0 1 15 0"/>',
        'link'      => '<path d="M10 13.5a4.5 4.5 0 0 0 6.4.4l2.7-2.7a4.5 4.5 0 0 0-6.4-6.4l-1.5 1.5"/><path d="M14 10.5a4.5 4.5 0 0 0-6.4-.4l-2.7 2.7a4.5 4.5 0 0 0 6.4 6.4l1.5-1.5"/>',
        'eye'       => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3"/>',
        'star'      => '<path d="m12 3.5 2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.9l-5.2 2.7 1-5.8-4.3-4.1 5.9-.9Z"/>',
        'minus'     => '<path d="M5 12h14"/>',
        'filter'    => '<path d="M4 5h16l-6.2 7.4V19l-3.6-1.8v-4.8Z"/>',
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
            ['Bookings', 'bookings', 'bookings', $all],
            ['News and offers', 'posts', 'news', $boss],
            ['Shop Studio', 'landing-page', 'studio', $boss],
            ['Referrals', 'referrals', 'gift', $all],
        ],
        'Data' => [
            ['Purge data', 'purge', 'trash', ['owner']],
        ],
    ];
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
