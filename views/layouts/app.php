<?php
/**
 * Nfelia — Authenticated Layout Shell (app.php)
 * Production Release — Enterprise Architecture
 *
 * Expected variables from controller:
 *   $title     — Page title
 *   $view_file — Absolute path to the view being rendered
 */
$user        = auth_user();
$business    = auth_business();
$user_role   = strtolower($user['role'] ?? 'staff'); // owner, admin, manager, staff
$business_id = (int)auth_business_id(); // Required for Super Admin check

// Font pairing map
$font_map = FONT_PAIRINGS[$business['font_pairing'] ?? 'modern_sans'] ?? FONT_PAIRINGS['modern_sans'];
$font_heading = $font_map['heading'];
$font_body    = $font_map['body'];
$google_fonts = urlencode($font_heading) . ':wght@400;600;700&family=' . urlencode($font_body) . ':wght@400;500;600';

// Brand colors from session
$primary   = e($business['primary_color']   ?? '#8B004A');
$secondary = e($business['secondary_color'] ?? '#B69F5E');
$logo_path = !empty($business['logo_path']) ? APP_URL . '/' . $business['logo_path'] : APP_URL . '/assets/img/nfelia-logo.png';
$currency      = $business['currency_symbol'] ?? '₦';
$business_type = $business['business_type'] ?? 'product'; // product | service | mixed
$show_verify_banner = $user_role === 'owner' && !is_email_verified();

// Phase 3 — Country session bootstrap
// Load selected country from session, fallback to cookie, fallback to Nigeria
if (!isset($_SESSION['selected_country'])) {
    $db_country = get_db();
    $country_id_cookie = (int)($_COOKIE['nfelia_country'] ?? 0);
    $biz_country_id    = (int)($business['country_id'] ?? 0);
    $resolve_id        = $biz_country_id ?: $country_id_cookie;

    if ($resolve_id) {
        $stmtC = $db_country->prepare("SELECT * FROM countries WHERE id = ? AND is_active = 1");
        $stmtC->execute([$resolve_id]);
        $resolved = $stmtC->fetch();
        if ($resolved) $_SESSION['selected_country'] = $resolved;
    }

    if (!isset($_SESSION['selected_country'])) {
        // Default to Nigeria
        $stmtC = $db_country->prepare("SELECT * FROM countries WHERE iso_code = 'NG' LIMIT 1");
        $stmtC->execute();
        $ng = $stmtC->fetch();
        if ($ng) $_SESSION['selected_country'] = $ng;
    }
}
$selected_country = $_SESSION['selected_country'] ?? ['name' => 'Nigeria', 'flag_emoji' => '🇳🇬', 'currency_symbol' => '₦', 'id' => 1];

// Navigation Array with Strict Role-Based Visibility
$nav_items = [
    ['label' => 'Command Center', 'url' => 'dashboard',             'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', 'access' => ['all']],
    ['label' => 'Tasks & Calendar', 'url' => 'tasks',               'icon' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-12-3.75l2.25 2.25 3.75-4.5', 'access' => ['all']],
    ['label' => 'Quotations',     'url' => 'quotations',            'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'access' => ['all']],
    ['label' => 'Invoices',       'url' => 'invoices',              'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4', 'access' => ['all']],
    ['label' => 'Receipts',       'url' => 'receipts',              'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', 'access' => ['all']],
    ['label' => ($business_type === 'service' ? 'Service Catalog' : 'Inventory'), 'url' => 'products',              'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4', 'access' => ['all']],
    ['label' => 'Clients',        'url' => 'customers',             'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', 'access' => ['all']],
    ['label' => 'Suppliers',      'url' => 'suppliers',             'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', 'access' => ['all']],
    ['label' => 'Deliveries',     'url' => 'deliveries',            'icon' => 'M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a2 2 0 104 0m-4 0a2 2 0 11-4 0', 'access' => ['all']],
    ['label' => 'Debts',          'url' => 'debts',                 'icon' => 'M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3', 'access' => ['owner', 'admin']],
    ['label' => 'Expenses',       'url' => 'expenses',              'icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z', 'access' => ['owner', 'admin']],
    ['label' => 'P&L Reports',    'url' => 'reports/profit-loss',   'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', 'access' => ['owner', 'admin']],
    
        ['section' => 'Data Management'],
    [
        'label'  => 'Purge Engine',
        'url'    => 'purge',
        'icon'   => 'M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0',
        'access' => ['owner']
    ],
    
    ['section' => 'Storefront'],
    ['label' => 'Orders',         'url' => 'orders',                'icon' => 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z', 'access' => ['all']],
    ['label' => 'Bookings',       'url' => 'bookings',              'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', 'access' => ['all']],
    ['label' => 'News and offers', 'url' => 'posts',                'icon' => 'M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10l6 6v8a2 2 0 01-2 2zM7 8h5m-5 4h10m-10 4h10', 'access' => ['owner', 'admin']],
    ['label' => 'Shop Studio',    'url' => 'landing-page', 'icon' => 'M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9', 'access' => ['owner', 'admin']],
    ['label' => 'Referrals',      'url' => 'referrals',             'icon' => 'M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z', 'access' => ['all']],
];

$current_url = trim($_GET['url'] ?? '', '/');
$current_section = explode('/', $current_url)[0] ?? 'dashboard';

// Filter navigation securely (keep section headers)
$visible_nav = array_filter($nav_items, function($item) use ($user_role) {
    if (isset($item['section'])) return true; // section header — always keep
    return in_array('all', $item['access']) || in_array($user_role, $item['access']);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <!-- NFELIA AUDIT FIX H01 — CSRF meta tag for JS consumption — 2026-06-03 -->
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e($title ?? 'Dashboard') ?> — <?= e($business['name']) ?> | Nfelia</title>
    <meta name="robots" content="noindex, nofollow">

    <!-- Dynamic Brand Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=<?= $google_fonts ?>&family=Roboto+Mono:wght@400;600&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            primary:   '<?= $primary ?>',
                            secondary: '<?= $secondary ?>',
                            plum: '#1B0A11',
                            sand: '#E8E4DA',
                            cream: '#F2EFE7',
                            white: '#FFFFFF',
                            gold: '#B69F5E',
                            burgundy: '#8B004A'
                        }
                    },
                    fontFamily: {
                        heading: ['"<?= $font_heading ?>"', 'sans-serif'],
                        body: ['"<?= $font_body ?>"', 'sans-serif'],
                        mono: ['"Roboto Mono"', 'monospace'],
                    }
                }
            }
        }
    </script>

    <!-- Dynamic Brand CSS Variables -->
    <style>
        :root {
            --brand-primary: <?= $primary ?>;
            --brand-secondary: <?= $secondary ?>;
        }
        body {
            font-family: '<?= $font_body ?>', 'Inter', sans-serif;
            background-color: #F2EFE7;
            color: #1B0A11;
            -webkit-font-smoothing: antialiased;
        }
        h1, h2, h3, .font-heading {
            font-family: '<?= $font_heading ?>', 'Plus Jakarta Sans', sans-serif;
        }
        .data-text { font-family: 'Roboto Mono', monospace; }
        .btn-primary { background-color: var(--brand-primary); color: #FFF; }
        .btn-primary:hover { opacity: 0.9; }
        .btn-secondary { background-color: var(--brand-secondary); color: #FFF; }
        .accent-primary { color: var(--brand-primary); }
        .accent-secondary { color: var(--brand-secondary); }
        .bg-accent-primary { background-color: var(--brand-primary); }
        .border-accent { border-color: var(--brand-primary); }

        /* Sidebar active state */
        .nav-link.active { background-color: rgba(27, 10, 17, 0.04); border-left-color: var(--brand-primary); color: var(--brand-primary); }
        .nav-link:hover:not(.active) { background-color: rgba(27, 10, 17, 0.02); }

        /* Mobile bottom nav */
        .mob-nav-item.active svg { color: var(--brand-primary); }
        .mob-nav-item.active span { color: var(--brand-primary); }

        /* Hide scrollbars cleanly */
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
    
    <!-- Global JS Namespace -->
    <script>
        window.Nfelia = {
            url: '<?= APP_URL ?>',
            csrf: '<?= $_SESSION['csrf_token'] ?? '' ?>',
            currency: '<?= e($currency) ?>'
        };
    </script>
</head>
<body x-data="{ sidebarOpen: false, notifOpen: false, profileOpen: false }" class="min-h-screen flex flex-col overflow-x-hidden">

    <!-- ===== DESKTOP SIDEBAR ===== -->
    <aside class="hidden lg:flex lg:flex-col fixed inset-y-0 left-0 w-64 bg-brand-white border-r border-brand-sand z-40">
        <!-- Logo Block -->
        <div class="h-20 flex items-center gap-3 px-6 border-b border-brand-sand shrink-0">
            <div class="w-9 h-9 rounded-lg overflow-hidden border border-brand-sand flex items-center justify-center bg-brand-white shadow-sm">
                <img src="<?= $logo_path ?>" alt="<?= e($business['name']) ?>" class="w-full h-full object-contain">
            </div>
            <div class="min-w-0">
                <div class="font-heading font-bold text-sm text-brand-plum truncate"><?= e($business['name']) ?></div>
                <div class="data-text text-[9px] text-brand-plum/50 uppercase tracking-widest"><?= e($business['doc_prefix'] ?? 'NFE') ?> Workspace</div>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1 no-scrollbar">
            <?php foreach ($visible_nav as $item):
                if (isset($item['section'])): ?>
                    <div class="mt-3 pt-3 border-t border-brand-sand">
                        <p class="px-4 text-[9px] font-bold text-brand-plum/30 uppercase tracking-widest mb-1"><?= e($item['section']) ?></p>
                    </div>
                <?php continue; endif;
                $is_active = ($current_section === explode('/', $item['url'])[0]);
            ?>
                <a href="<?= APP_URL ?>/<?= $item['url'] ?>" class="nav-link flex items-center gap-3 px-4 py-3 text-xs font-semibold uppercase tracking-wider border-l-2 border-transparent transition-all <?= $is_active ? 'active' : 'text-brand-plum/70 hover:text-brand-plum' ?>">
                    <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="<?= $item['icon'] ?>" />
                    </svg>
                    <?= $item['label'] ?>
                </a>
            <?php endforeach; ?>

            <!-- Super Admin Links (Only for Business ID 1) -->
            <?php if ($business_id === 1): ?>
                <div class="mt-4 pt-4 border-t border-brand-sand space-y-1">
                    <p class="px-4 text-[9px] font-bold text-brand-plum/30 uppercase tracking-widest mb-2">Founder Panel</p>
                    <a href="<?= APP_URL ?>/admin" class="nav-link flex items-center gap-3 px-4 py-2.5 text-xs font-semibold uppercase tracking-wider border-l-2 border-transparent transition-all <?= ($current_section === 'admin') ? 'bg-[#fff0f0] border-brand-burgundy text-brand-burgundy' : 'text-brand-burgundy/70 hover:text-brand-burgundy hover:bg-[#fff0f0]/50' ?>">
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                        Admin Panel
                    </a>
                    <a href="<?= APP_URL ?>/countries" class="nav-link flex items-center gap-3 px-4 py-2.5 text-xs font-semibold uppercase tracking-wider border-l-2 border-transparent transition-all <?= ($current_section === 'countries') ? 'bg-[#fff0f0] border-brand-burgundy text-brand-burgundy' : 'text-brand-burgundy/70 hover:text-brand-burgundy hover:bg-[#fff0f0]/50' ?>">
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Countries
                    </a>
                    <a href="<?= APP_URL ?>/momo/review" class="nav-link flex items-center gap-3 px-4 py-2.5 text-xs font-semibold uppercase tracking-wider border-l-2 border-transparent transition-all <?= ($current_section === 'momo') ? 'bg-[#fff0f0] border-brand-burgundy text-brand-burgundy' : 'text-brand-burgundy/70 hover:text-brand-burgundy hover:bg-[#fff0f0]/50' ?>">
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z"/></svg>
                        MoMo Review
                        <?php
                        // Show pending count badge
                        try {
                            $stmt_mm = get_db()->prepare("SELECT COUNT(*) FROM subscription_payments WHERE status='pending_verification'");
                            $stmt_mm->execute();
                            $mm_count = (int)$stmt_mm->fetchColumn();
                            if ($mm_count > 0): ?>
                                <span class="ml-auto bg-brand-burgundy text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full"><?= $mm_count ?></span>
                            <?php endif;
                        } catch(\Exception $e) {}
                        ?>
                    </a>
                </div>
            <?php endif; ?>
        </nav>

        <!-- Sidebar Footer -->
        <div class="border-t border-brand-sand p-4 shrink-0">
            <div class="flex items-center gap-3 mb-3">
                <?php if (!empty($user['avatar_path'])): ?>
                    <img src="<?= APP_URL ?>/<?= e($user['avatar_path']) ?>" alt="<?= e($user['full_name']) ?>" class="w-9 h-9 rounded-full object-cover shadow-sm border border-brand-sand shrink-0">
                <?php else: ?>
                    <div class="w-9 h-9 rounded-full flex items-center justify-center text-white font-heading font-bold text-sm shadow-sm shrink-0" style="background-color: var(--brand-primary);">
                        <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <div class="min-w-0">
                    <div class="text-xs font-bold text-brand-plum truncate"><?= e($user['full_name']) ?></div>
                    <div class="text-[10px] font-bold text-brand-plum/50 truncate capitalize"><?= e($user['role']) ?></div>
                </div>
            </div>
            <form action="<?= APP_URL ?>/logout" method="POST" class="m-0 p-0">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <button type="submit" class="w-full text-left text-[10px] font-bold text-brand-plum/50 uppercase tracking-widest hover:text-brand-burgundy transition-colors py-2 focus:outline-none">
                    Close Session
                </button>
            </form>
        </div>
    </aside>

    <!-- ===== TOP BAR (Mobile + Desktop) ===== -->
    <header class="fixed top-0 left-0 right-0 lg:left-64 h-16 bg-brand-white/80 backdrop-blur-md border-b border-brand-sand z-30 flex items-center justify-between px-4 lg:px-8 transition-all duration-300">
        <!-- Mobile: Hamburger -->
        <button @click="sidebarOpen = true" class="lg:hidden text-brand-plum p-1 focus:outline-none">
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>

        <!-- Page Title -->
        <h2 class="font-heading font-bold text-sm text-brand-plum uppercase tracking-widest hidden lg:block"><?= e($title ?? 'Dashboard') ?></h2>

        <!-- Right Actions -->
        <div class="flex items-center gap-4 ml-auto lg:ml-0">
            <!-- Phase 3: Country / Currency Selector Widget -->
            <div class="relative" x-data="{ countryOpen: false }" @click.away="countryOpen = false">
                <button @click="countryOpen = !countryOpen"
                        class="flex items-center gap-1.5 px-3 py-2 rounded-lg border border-brand-sand hover:border-brand-gold transition-colors text-xs font-semibold text-brand-plum focus:outline-none">
                    <span class="text-base"><?= e($selected_country['flag_emoji'] ?? '🌐') ?></span>
                    <span class="hidden sm:inline data-text text-[10px] font-bold uppercase tracking-wider"><?= e($selected_country['currency_symbol'] ?? '₦') ?></span>
                    <svg class="w-3 h-3 text-brand-plum/50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>

                <div x-show="countryOpen" style="display:none;"
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     class="absolute right-0 mt-2 w-52 bg-brand-white border border-brand-sand rounded-xl shadow-lg z-50 overflow-hidden">
                    <div class="px-4 py-2.5 border-b border-brand-sand bg-brand-cream/30">
                        <p class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Select Your Country</p>
                    </div>
                    <?php
                    $db_clist = get_db();
                    $stmtClist = $db_clist->query("SELECT id, name, iso_code, flag_emoji, currency_symbol, currency_code FROM countries WHERE is_active = 1 ORDER BY name ASC");
                    $all_countries = $stmtClist->fetchAll();
                    foreach ($all_countries as $co): ?>
                    <button onclick="setCountry(<?= (int)$co['id'] ?>)" @click="countryOpen = false"
                            class="w-full flex items-center gap-3 px-4 py-3 hover:bg-brand-cream/50 transition-colors text-left border-b border-brand-sand/50 last:border-0
                                   <?= ((int)($selected_country['id'] ?? 0) === (int)$co['id']) ? 'bg-brand-cream/30' : '' ?>">
                        <span class="text-lg"><?= e($co['flag_emoji'] ?? '🌐') ?></span>
                        <div>
                            <div class="text-xs font-semibold text-brand-plum"><?= e($co['name']) ?></div>
                            <div class="data-text text-[9px] text-brand-plum/50"><?= e($co['currency_symbol']) ?> · <?= e($co['currency_code']) ?></div>
                        </div>
                        <?php if ((int)($selected_country['id'] ?? 0) === (int)$co['id']): ?>
                            <svg class="w-4 h-4 text-brand-gold ml-auto shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <?php endif; ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Notification Bell -->
            <div class="relative" x-data="{ 
                count: 0, 
                notifications: [], 
                loaded: false, 
                loading: false,
                
                // Fetch initial unread count
                init() {
                    fetch(window.Nfelia.url + '/api/notifications?action=count')
                        .then(r => r.json())
                        .then(d => { this.count = d.count || 0; })
                        .catch(() => {});
                },
                
                // Lazy load the list only when opened
                loadNotifications() {
                    if (this.loaded) return;
                    this.loading = true;
                    fetch(window.Nfelia.url + '/api/notifications?action=list')
                        .then(r => r.json())
                        .then(d => {
                            if (d.success) {
                                this.notifications = d.notifications || [];
                                this.loaded = true;
                            }
                        })
                        .finally(() => { this.loading = false; });
                },

                // Hit your mark_read endpoint
                markAllRead() {
                    fetch(window.Nfelia.url + '/api/notifications?action=mark_read', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ csrf_token: window.Nfelia.csrf, id: 'all' })
                    }).then(() => {
                        this.count = 0;
                        this.notifications.forEach(n => n.is_read = 1);
                    });
                }
            }">
            
                <!-- Trigger Button -->
                <button @click="notifOpen = !notifOpen; if(notifOpen) loadNotifications();" @click.away="notifOpen = false" class="relative text-brand-plum/60 hover:text-brand-plum transition-colors p-2 focus:outline-none rounded-full hover:bg-brand-sand/50">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                    </svg>
                    <span x-show="count > 0" x-text="count > 9 ? '9+' : count" class="absolute top-1 right-1 bg-brand-burgundy text-white text-[9px] font-bold w-4 h-4 flex items-center justify-center rounded-full border-2 border-brand-white" style="display:none;"></span>
                </button>

                <!-- Notification Dropdown Panel -->
                <div x-show="notifOpen" style="display: none;"
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="transform opacity-0 scale-95"
                     x-transition:enter-end="transform opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="transform opacity-100 scale-100"
                     x-transition:leave-end="transform opacity-0 scale-95"
                     class="absolute right-0 mt-3 w-80 bg-brand-white rounded-2xl shadow-[0_20px_50px_rgb(27,10,17,0.12)] border border-brand-sand overflow-hidden z-50">
                    
                    <!-- Header -->
                    <div class="px-4 py-3 border-b border-brand-sand bg-brand-cream/30 flex items-center justify-between">
                        <p class="text-sm font-bold text-brand-plum">Notifications</p>
                        <button x-show="count > 0" @click="markAllRead()" class="text-[10px] font-bold text-brand-burgundy uppercase tracking-widest hover:underline focus:outline-none">
                            Mark Read
                        </button>
                    </div>
                    
                    <!-- Body (Scrollable) -->
                    <div class="max-h-72 overflow-y-auto no-scrollbar">
                        
                        <!-- Loading State -->
                        <div x-show="loading" class="flex justify-center py-6">
                            <svg class="animate-spin h-5 w-5 text-brand-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        </div>

                        <!-- Empty State ("You are up to date") -->
                        <div x-show="!loading && loaded && notifications.length === 0" style="display:none;" class="flex flex-col items-center justify-center py-8 text-center px-4">
                            <div class="w-12 h-12 bg-brand-sand/50 rounded-full flex items-center justify-center mb-3">
                                <svg class="w-6 h-6 text-brand-plum/40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <p class="text-xs font-bold text-brand-plum mb-1">You are all caught up!</p>
                            <p class="text-[10px] text-brand-plum/60 leading-relaxed">No new alerts or activities in your workspace right now.</p>
                        </div>

                        <!-- Dynamic Iteration via Backend Data -->
                        <template x-for="notif in notifications" :key="notif.id">
                            <a :href="notif.action_url ? (notif.action_url.startsWith('http') ? notif.action_url : window.Nfelia.url + '/' + notif.action_url) : '#'"
                               class="flex gap-3 p-3 border-b border-brand-sand transition-colors block"
                               :class="notif.is_read == 1 ? 'bg-brand-white hover:bg-brand-sand/20' : 'bg-[#fff0f0]/30 hover:bg-[#fff0f0]/60'">
                                
                                <div class="mt-0.5 shrink-0">
                                    <div class="w-2 h-2 mt-1.5 rounded-full" :class="notif.is_read == 1 ? 'bg-transparent' : 'bg-brand-burgundy'"></div>
                                </div>
                                
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-bold text-brand-plum" x-text="notif.title"></p>
                                    <p class="text-[11px] text-brand-plum/70 mt-0.5 leading-snug" x-text="notif.message"></p>
                                    <p class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mt-1.5" x-text="notif.formatted_date"></p>
                                </div>
                            </a>
                        </template>

                    </div>
                    
                    <!-- Footer -->
                    <div class="border-t border-brand-sand py-1">
                        <a href="<?= APP_URL ?>/settings" class="flex justify-center w-full px-4 py-2.5 text-xs font-bold text-brand-primary hover:bg-brand-sand/30 transition-colors">
                            View All Activity
                        </a>
                    </div>
                </div>
            </div>

            <!-- Profile Dropdown Menu -->
            <div class="relative">
                <button @click="profileOpen = !profileOpen" @click.away="profileOpen = false" class="w-8 h-8 rounded-full flex items-center justify-center text-white font-heading font-bold text-xs shadow-sm active:scale-95 transition-transform focus:outline-none bg-brand-sand overflow-hidden" <?= empty($user['avatar_path']) ? 'style="background-color: var(--brand-primary);"' : '' ?>>
                    <?php if (!empty($user['avatar_path'])): ?>
                        <img src="<?= APP_URL ?>/<?= e($user['avatar_path']) ?>" alt="Profile" class="w-full h-full object-cover">
                    <?php else: ?>
                        <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                    <?php endif; ?>
                </button>
                
                <div x-show="profileOpen" style="display: none;"
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="transform opacity-0 scale-95"
                     x-transition:enter-end="transform opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="transform opacity-100 scale-100"
                     x-transition:leave-end="transform opacity-0 scale-95"
                     class="absolute right-0 mt-3 w-56 bg-brand-white rounded-2xl shadow-[0_20px_50px_rgb(27,10,17,0.12)] border border-brand-sand overflow-hidden z-50">
                    
                    <div class="px-4 py-3 border-b border-brand-sand bg-brand-cream/30">
                        <p class="text-sm font-bold text-brand-plum truncate"><?= e($user['full_name']) ?></p>
                        <p class="data-text text-[10px] text-brand-plum/60 truncate mt-0.5"><?= e($user['email']) ?></p>
                    </div>
                    
                    <div class="py-1">
                        <a href="<?= APP_URL ?>/settings" class="flex items-center gap-3 px-4 py-2.5 text-xs font-semibold text-brand-plum hover:bg-brand-sand/30 transition-colors">
                            <svg class="w-4 h-4 text-brand-plum/50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        Workspace Settings
                    </a>
                    <?php if ($user_role === 'owner'): ?>
                    <a href="<?= APP_URL ?>/settings?tab=billing" class="flex items-center gap-3 px-4 py-2.5 text-xs font-semibold text-brand-plum hover:bg-brand-sand/30 transition-colors">
                        <svg class="w-4 h-4 text-brand-plum/50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/></svg>
                        Billing Plan
                    </a>
                    <?php endif; ?>
                </div>
                
                <div class="border-t border-brand-sand py-1">
                    <a href="<?= APP_URL ?>/user-guide" class="flex items-center gap-3 px-4 py-2.5 text-xs font-semibold text-brand-plum hover:bg-brand-sand/30 transition-colors">
                        <svg class="w-4 h-4 text-brand-plum/50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                        User Guide
                    </a>
                    <a href="<?= APP_URL ?>/contact-support" class="flex items-center gap-3 px-4 py-2.5 text-xs font-semibold text-brand-plum hover:bg-brand-sand/30 transition-colors">
                        <svg class="w-4 h-4 text-brand-plum/50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/></svg>
                        Contact Support
                    </a>
                </div>

                <div class="border-t border-brand-sand py-1">
                    <form action="<?= APP_URL ?>/logout" method="POST" class="w-full m-0 p-0">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                        <button type="submit" class="w-full text-left flex items-center gap-3 px-4 py-2.5 text-xs font-bold text-brand-burgundy hover:bg-[#fff0f0] transition-colors focus:outline-none">
                            <svg class="w-4 h-4 text-brand-burgundy" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg>
                            Sign Out
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <!-- ===== MOBILE SIDEBAR OVERLAY ===== -->
    <div x-show="sidebarOpen" style="display:none;" class="lg:hidden fixed inset-0 z-50">
        <div x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen = false" class="absolute inset-0 bg-brand-plum/60 backdrop-blur-sm"></div>
        <aside x-show="sidebarOpen" 
               x-transition:enter="transition ease-out duration-200" 
               x-transition:enter-start="-translate-x-full" 
               x-transition:enter-end="translate-x-0" 
               x-transition:leave="transition ease-in duration-150" 
               x-transition:leave-start="translate-x-0" 
               x-transition:leave-end="-translate-x-full" 
               class="absolute left-0 top-0 bottom-0 w-72 bg-brand-white shadow-2xl flex flex-col">
            
            <div class="h-16 flex items-center justify-between px-5 border-b border-brand-sand shrink-0">
                <div class="flex items-center gap-2">
                    <img src="<?= $logo_path ?>" alt="" class="h-8 w-8 object-contain rounded">
                    <span class="font-heading font-bold text-sm text-brand-plum truncate max-w-[120px]"><?= e($business['name']) ?></span>
                </div>
                <button @click="sidebarOpen = false" class="text-brand-plum/50 p-1 focus:outline-none">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
                <?php foreach ($visible_nav as $item):
                    if (isset($item['section'])): ?>
                        <div class="mt-3 pt-3 border-t border-brand-sand">
                            <p class="px-4 text-[9px] font-bold text-brand-plum/30 uppercase tracking-widest mb-1"><?= e($item['section']) ?></p>
                        </div>
                    <?php continue; endif;
                    $is_active = ($current_section === explode('/', $item['url'])[0]);
                ?>
                    <a href="<?= APP_URL ?>/<?= $item['url'] ?>" @click="sidebarOpen = false" class="nav-link flex items-center gap-3 px-4 py-3 text-xs font-semibold uppercase tracking-wider border-l-2 border-transparent transition-all <?= $is_active ? 'active' : 'text-brand-plum/70' ?>">
                        <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="<?= $item['icon'] ?>" />
                        </svg>
                        <?= $item['label'] ?>
                    </a>
                <?php endforeach; ?>

                <?php if ($business_id === 1): ?>
                    <div class="mt-4 pt-4 border-t border-brand-sand space-y-1">
                        <p class="px-4 text-[9px] font-bold text-brand-plum/30 uppercase tracking-widest mb-2">Founder Panel</p>
                        <a href="<?= APP_URL ?>/admin" @click="sidebarOpen = false" class="nav-link flex items-center gap-3 px-4 py-2.5 text-xs font-semibold uppercase tracking-wider border-l-2 border-transparent transition-all <?= ($current_section === 'admin') ? 'bg-[#fff0f0] border-brand-burgundy text-brand-burgundy' : 'text-brand-burgundy/70 hover:text-brand-burgundy hover:bg-[#fff0f0]/50' ?>">
                            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                            Admin Panel
                        </a>
                        <a href="<?= APP_URL ?>/countries" @click="sidebarOpen = false" class="nav-link flex items-center gap-3 px-4 py-2.5 text-xs font-semibold uppercase tracking-wider border-l-2 border-transparent transition-all <?= ($current_section === 'countries') ? 'bg-[#fff0f0] border-brand-burgundy text-brand-burgundy' : 'text-brand-burgundy/70 hover:text-brand-burgundy hover:bg-[#fff0f0]/50' ?>">
                            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Countries
                        </a>
                        <a href="<?= APP_URL ?>/momo/review" @click="sidebarOpen = false" class="nav-link flex items-center gap-3 px-4 py-2.5 text-xs font-semibold uppercase tracking-wider border-l-2 border-transparent transition-all <?= ($current_section === 'momo') ? 'bg-[#fff0f0] border-brand-burgundy text-brand-burgundy' : 'text-brand-burgundy/70 hover:text-brand-burgundy hover:bg-[#fff0f0]/50' ?>">
                            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z"/></svg>
                            MoMo Review
                        </a>
                    </div>
                <?php endif; ?>
            </nav>

            <div class="border-t border-brand-sand p-4 shrink-0">
                <form action="<?= APP_URL ?>/logout" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                    <button type="submit" class="text-[10px] font-bold text-brand-plum/50 uppercase tracking-widest hover:text-brand-burgundy transition-colors focus:outline-none">Close Session</button>
                </form>
            </div>
        </aside>
    </div>

    <!-- ===== BILLING WARNING BANNER ===== -->
    <?php if (!empty($_SESSION['billing_warning']) && $user_role === 'owner'): ?>
        <div class="fixed top-16 left-0 right-0 lg:left-64 z-20 bg-amber-50 border-b border-amber-300 px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span class="text-xs font-semibold text-amber-800"><?= e($_SESSION['billing_warning']) ?></span>
            </div>
            <a href="<?= APP_URL ?>/settings?tab=billing" class="text-[10px] font-bold text-amber-700 uppercase tracking-widest hover:underline">Update Billing</a>
        </div>
    <?php endif; ?>

    <!-- ===== EMAIL VERIFICATION BANNER ===== -->
    <?php if ($show_verify_banner): ?>
        <div class="fixed top-16 left-0 right-0 lg:left-64 z-20 bg-blue-50 border-b border-blue-200 px-4 py-2.5 flex items-center justify-between gap-3"
             x-data="{ show: true }" x-show="show">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                <span class="text-xs font-semibold text-blue-800">
                    Verify your email to unlock document sharing with clients.
                </span>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                <form action="<?= APP_URL ?>/resend-verification" method="POST" class="m-0 p-0">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <button type="submit" class="text-[10px] font-bold text-blue-700 uppercase tracking-widest hover:underline focus:outline-none">
                        Resend Link
                    </button>
                </form>
                <button @click="show = false" class="text-blue-400 hover:text-blue-600 focus:outline-none ml-1">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>
    <?php endif; ?>

    <!-- ===== FLASH MESSAGES ===== -->
    <?php $flash = get_flash(); if ($flash): ?>
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" 
             x-transition.duration.300ms 
             class="fixed top-20 left-1/2 -translate-x-1/2 z-50 max-w-md w-full mx-auto px-4">
            <div class="p-3 text-[11px] font-bold data-text uppercase tracking-widest border rounded shadow-lg text-center <?= $flash['type'] === 'error' ? 'border-[#ff0000] bg-[#fff0f0] text-[#ff0000]' : ($flash['type'] === 'warning' ? 'border-brand-gold bg-brand-cream text-brand-burgundy' : 'border-[#16a085] bg-[#e8f8f5] text-[#16a085]') ?>">
                <?= e($flash['message']) ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="flex-1 lg:ml-64 pt-16 pb-24 lg:pb-8 flex flex-col">
        <div class="px-4 lg:px-8 py-6 flex-1 w-full max-w-7xl mx-auto">
            <?php if (isset($view_file) && file_exists($view_file)): ?>
                <?php require_once $view_file; ?>
            <?php else: ?>
                <div class="text-center py-20">
                    <h2 class="font-heading font-bold text-2xl text-brand-plum mb-2">Module Not Found</h2>
                    <p class="text-sm text-brand-plum/60">This view could not be located in the workspace architecture.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- ===== MOBILE BOTTOM NAV ===== -->
    <nav class="lg:hidden fixed bottom-0 left-0 right-0 bg-brand-white/95 backdrop-blur-md border-t border-brand-sand z-30 pb-[env(safe-area-inset-bottom)]">
        <div class="flex items-center justify-around h-16">
            <?php
            // Limit to core operational modules for bottom bar
            $mob_nav = [
                ['label' => 'Home',     'url' => 'dashboard',  'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
                ['label' => 'Invoices', 'url' => 'invoices',   'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
                ['label' => 'Stock',    'url' => 'products',   'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
                ['label' => 'Tasks',    'url' => 'tasks',      'icon' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-12-3.75l2.25 2.25 3.75-4.5'],
                ['label' => 'Menu',     'url' => '#',          'icon' => 'M4 6h16M4 12h16M4 18h16'],
            ];
            foreach ($mob_nav as $mitem):
                $mob_active = ($current_section === $mitem['url']);
            ?>
                <a href="<?= $mitem['url'] === '#' ? '#' : APP_URL . '/' . $mitem['url'] ?>"
                   <?= $mitem['url'] === '#' ? '@click.prevent="sidebarOpen = true"' : '' ?>
                   class="mob-nav-item flex flex-col items-center justify-center w-full h-full gap-1 <?= $mob_active ? 'active' : '' ?> focus:outline-none transition-colors">
                    <svg class="w-6 h-6 <?= $mob_active ? '' : 'text-brand-plum/40' ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="<?= $mitem['icon'] ?>" />
                    </svg>
                    <span class="text-[9px] font-bold uppercase tracking-wider <?= $mob_active ? '' : 'text-brand-plum/40' ?>"><?= $mitem['label'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>

    <script>
    /* Phase 3 — Country selection */
    function setCountry(countryId) {
        const form = new FormData();
        form.append('country_id', countryId);
        form.append('csrf_token', window.Nfelia ? window.Nfelia.csrf : document.querySelector('meta[name="csrf-token"]').content);
        fetch(window.Nfelia.url + '/api/set-country', { method: 'POST', body: form })
            .then(r => r.json())
            .then(d => { if (d.success) window.location.reload(); })
            .catch(() => window.location.reload());
    }
    </script>

    <!-- System Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="<?= APP_URL ?>/assets/js/app.js"></script>

    <!-- Global Feedback Modal -->
    <?php 
    if (file_exists(__DIR__ . '/../partials/feedback_modal.php')) {
        require_once __DIR__ . '/../partials/feedback_modal.php'; 
    }
    ?>
</body>
</html>