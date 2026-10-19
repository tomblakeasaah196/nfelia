<?php
/**
 * Nfelia — the signed-in app's frame (app.php).
 *
 * On a phone: an app bar on top, the page, and tabs at the bottom (Home,
 * Orders, + to create, Tasks, More). On a computer: a sidebar instead of the
 * tabs. The business's colour runs through it (app/helpers/theme.php), and
 * the person's text size and background choice are read from this device.
 * In the installed app, the first page of a launch plays the opening.
 *
 * Expected variables from controller:
 *   $title     — Page title
 *   $view_file — Absolute path to the view being rendered
 */
$user        = auth_user();
$business    = auth_business();
$user_role   = strtolower($user['role'] ?? 'staff'); // owner, admin, manager, staff
$business_id = (int)auth_business_id(); // Required for Super Admin check

$theme         = theme_tokens($business['primary_color'] ?? null, $business['secondary_color'] ?? null);
$ui            = theme_appearance();
$logo_path     = !empty($business['logo_path']) ? APP_URL . '/' . $business['logo_path'] : null;
$currency      = $business['currency_symbol'] ?? '₦';
$business_type = $business['business_type'] ?? 'product'; // product | service | mixed
$show_verify_banner = $user_role === 'owner' && !is_email_verified();
$is_boss       = in_array($user_role, ['owner', 'admin'], true);

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

$nav_groups      = nav_groups($user_role, $business_type, $business_id);
$current_url     = trim($_GET['url'] ?? '', '/');
$current_section = explode('/', $current_url)[0] ?: 'dashboard';
$current_tab     = nav_tab_for($current_section);
// Orders holds four lists (Orders, Quotes, Invoices, Receipts): each is a first page of the tab.
$tab_roots       = ['dashboard' => 'home', 'orders' => 'orders', 'invoices' => 'orders', 'receipts' => 'orders', 'quotations' => 'orders', 'tasks' => 'tasks', 'more' => 'more', '' => 'home'];
$is_tab_root     = isset($tab_roots[$current_url]);
// Below a tab's first page the bar shows Back: to the section's list when
// it has one, otherwise to More, where the rest of the app is listed.
$list_sections = ['invoices', 'receipts', 'quotations', 'customers', 'products', 'suppliers', 'deliveries',
                  'debts', 'expenses', 'orders', 'tasks', 'admin', 'countries', 'deed'];
$back_url = APP_URL . '/' . (str_contains($current_url, '/') && in_array($current_section, $list_sections, true) ? $current_section : 'more');

// Locked (app/middleware/auth_check.php): the page shows only an outline of
// the app behind the lock, never the business.
$locked_page = !empty($locked_page);

// sw.js keeps a copy of app pages so they can be read offline. Not these:
// what they show should never be left on a phone.
if (!$locked_page && !in_array($current_section, ['settings', 'purge', 'admin', 'momo', 'countries'], true)) {
    header('X-Nfelia-Page: app');
}

$first_name = explode(' ', trim($user['full_name'] ?? ''))[0] ?: 'there';
$lock_minutes = max(1, intdiv(LOCK_AFTER_SECONDS, 60));
$lock_words = $lock_minutes % 60 === 0
    ? ($lock_minutes / 60) . ' hour' . ($lock_minutes === 60 ? '' : 's')
    : $lock_minutes . ' minute' . ($lock_minutes === 1 ? '' : 's');
$stmt_pk = get_db()->prepare("SELECT COUNT(*) FROM user_passkeys WHERE user_id = ?");
$stmt_pk->execute([(int)$user['id']]);
$passkey_count = (int)$stmt_pk->fetchColumn();

$splash = ($_GET['source'] ?? '') === 'pwa';
$just_signed_in = !empty($_SESSION['just_signed_in']);
unset($_SESSION['just_signed_in']);

$asset = function (string $path): string {
    $file = BASE_PATH . '/' . $path;
    return APP_URL . '/' . $path . (is_file($file) ? '?v=' . filemtime($file) : '');
};
$initial = strtoupper(mb_substr($business['name'] ?? 'N', 0, 1));
$avatar  = $logo_path
    ? '<span class="nf-avatar"><img src="' . e($logo_path) . '" alt=""></span>'
    : '<span class="nf-avatar">' . e($initial) . '</span>';
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en" data-text="<?= e($ui['text']) ?>" data-bg="<?= e($ui['bg']) ?>"<?= $splash || $locked_page ? ' class="' . trim(($splash ? 'nf-splashing ' : '') . ($locked_page ? 'nf-locked' : '')) . '"' : '' ?>>
<head>
    <meta charset="UTF-8">
    <?= errors_script_tag() ?>
    <!-- NFELIA AUDIT FIX H01 — CSRF meta tag for JS consumption — 2026-06-03 -->
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e($title ?? 'Home') ?> — <?= e($business['name']) ?> | Nfelia</title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="<?= $splash ? '#6E0039' : ($ui['bg'] === 'cream' ? '#F7F3EA' : '#FFFFFF') ?>" data-theme-color="<?= $ui['bg'] === 'cream' ? '#F7F3EA' : '#FFFFFF' ?>">
<?php require BASE_PATH . '/views/partials/pwa_head.php'; ?>
    <link rel="preload" href="<?= APP_URL ?>/assets/fonts/plus-jakarta-sans-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= APP_URL ?>/assets/fonts/bricolage-grotesque-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= $asset('assets/css/app.css') ?>">
    <style>
        :root {
            --accent: <?= $theme['accent'] ?>; --accent-rgb: <?= $theme['accent_rgb'] ?>; --on-accent: <?= $theme['on_accent'] ?>;
            --accent-text: <?= $theme['accent_text'] ?>; --accent-soft: <?= $theme['accent_soft'] ?>;
            --glow: <?= $theme['glow'] ?>; --glow-2: <?= $theme['glow_2'] ?>; --brand-secondary-rgb: <?= $theme['secondary_rgb'] ?>;
        }
    </style>
    <!-- Start loading a page as a finger lands on its link. Only the menus'
         links (and any marked data-prefetch): some other links change things
         when opened, such as receipts/from_payment, which makes a receipt. -->
    <script type="speculationrules">
    {"prefetch": [{"where": {"and": [
        {"href_matches": "/*"},
        {"selector_matches": ".nf-tab, .nf-side-link, .nf-tile, .nf-create a, .nf-list a.nf-row, a[data-prefetch]"},
        {"not": {"selector_matches": "[data-no-prefetch], [download], [target=_blank]"}}
    ]}, "eagerness": "moderate"}]}
    </script>
    <script defer src="<?= $asset('assets/js/push.js') ?>"></script>
    <script defer src="<?= $asset('assets/js/bell.js') ?>"></script>
    <script defer src="<?= $asset('assets/vendor/alpine-3.14.3.min.js') ?>"></script>
    <script>
        window.Nfelia = {
            url: '<?= APP_URL ?>',
            csrf: '<?= $_SESSION['csrf_token'] ?? '' ?>',
            currency: '<?= e($currency) ?>',
            push: { enabled: <?= push_enabled() ? 'true' : 'false' ?>, key: '<?= e(VAPID_PUBLIC_KEY) ?>' },
            lock: { after: <?= LOCK_AFTER_SECONDS ?>, left: <?= $locked_page ? 0 : lock_seconds_left() ?>, locked: <?= $locked_page ? 'true' : 'false' ?>, user: <?= (int)$user['id'] ?>, passkeys: <?= $passkey_count ?> }
        };
    </script>
</head>
<body x-data="{ sidebarOpen: false, notifOpen: false, profileOpen: false }" data-tab="<?= e($current_tab) ?>"<?= $just_signed_in ? ' data-just-signed-in' : '' ?>>
<?php if ($splash) require BASE_PATH . '/views/partials/nf_splash.php'; ?>

    <!-- ===== SIDEBAR (computers) ===== -->
    <aside class="nf-side" aria-label="Main menu">
        <a class="nf-side-brand" href="<?= APP_URL ?>/dashboard" style="text-decoration:none;color:inherit"><?= $avatar ?><b><?= e($business['name']) ?></b></a>
        <nav class="nf-side-nav">
            <?php // nav_ names: the page's own view runs in this same scope after the sidebar.
            foreach ($nav_groups as $nav_group => $nav_items): ?>
                <?php if ($nav_group !== 'Your business'): ?><p class="nf-side-h"><?= e($nav_group) ?></p><?php endif; ?>
                <?php foreach ($nav_items as $nav_item):
                    $nav_on = $current_section === explode('/', $nav_item['url'])[0]; ?>
                    <a class="nf-side-link" href="<?= APP_URL ?>/<?= $nav_item['url'] ?>"<?= $nav_on ? ' aria-current="page"' : '' ?>>
                        <?= nav_icon($nav_item['icon']) ?><?= e($nav_item['label']) ?>
                        <?php if ($nav_item['url'] === 'momo/review'):
                            try {
                                $mm_count = (int)get_db()->query("SELECT COUNT(*) FROM subscription_payments WHERE status='pending_verification'")->fetchColumn();
                                if ($mm_count > 0): ?><span class="nf-pill"><?= $mm_count ?></span><?php endif;
                            } catch (\Exception $e) {}
                        endif; ?>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>
        <div class="nf-side-foot">
            <?php if (!empty($user['avatar_path'])): ?>
                <span class="nf-avatar"><img src="<?= APP_URL ?>/<?= e($user['avatar_path']) ?>" alt=""></span>
            <?php else: ?>
                <span class="nf-avatar"><?= e(strtoupper(mb_substr($user['full_name'], 0, 1))) ?></span>
            <?php endif; ?>
            <div class="who"><b><?= e($user['full_name']) ?></b><span><?= e($user['role']) ?></span></div>
            <form action="<?= APP_URL ?>/logout" method="POST" class="m-0">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <button type="submit" class="nf-icon-btn" aria-label="Sign out" data-tip="Sign out"><?= nav_icon('signout') ?></button>
            </form>
        </div>
    </aside>

    <!-- ===== APP BAR ===== -->
    <header class="nf-appbar" data-appbar>
        <div class="nf-ab-lead<?= $is_tab_root ? '' : ' has-back' ?>">
            <?php if ($is_tab_root): ?>
                <a class="nf-ab-biz lg:hidden" href="<?= APP_URL ?>/dashboard"><?= $avatar ?><span class="nf-ab-title"><?= e($business['name']) ?></span></a>
            <?php else: ?>
                <a class="nf-icon-btn lg:hidden" href="<?= e($back_url) ?>" aria-label="Back" data-back><?= nav_icon('back') ?></a>
                <span class="nf-ab-title lg:hidden"><?= e($title ?? '') ?></span>
            <?php endif; ?>
            <span class="nf-ab-title hidden lg:block"><?= e($title ?? 'Home') ?></span>
        </div>

        <div class="nf-ab-actions">
            <!-- Phase 3: Country / Currency Selector Widget (the owner's to change) -->
            <?php if (is_owner()): ?>
            <div class="relative hidden lg:block" x-data="{ countryOpen: false }" @click.away="countryOpen = false" @keydown.escape="countryOpen = false">
                <button type="button" @click="countryOpen = !countryOpen" :aria-expanded="countryOpen" class="nf-icon-btn" aria-label="Country and currency" data-tip="Country and currency">
                    <span style="font-size:1.25rem;line-height:1"><?= e($selected_country['flag_emoji'] ?? '🌐') ?></span>
                </button>
                <div x-show="countryOpen" x-cloak x-transition.opacity.duration.150ms class="nf-pop nf-menu">
                    <div class="nf-pop-h"><b>Country and currency</b></div>
                    <?php
                    $all_countries = get_db()->query("SELECT id, name, iso_code, flag_emoji, currency_symbol, currency_code FROM countries WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
                    foreach ($all_countries as $co): $is_sel = (int)($selected_country['id'] ?? 0) === (int)$co['id']; ?>
                        <button type="button" onclick="setCountry(<?= (int)$co['id'] ?>)" @click="countryOpen = false"<?= $is_sel ? ' aria-current="true"' : '' ?>>
                            <span style="font-size:1.125rem"><?= e($co['flag_emoji'] ?? '🌐') ?></span>
                            <span style="flex:1"><?= e($co['name']) ?> <span style="color:var(--ink-3)"><?= e($co['currency_symbol']) ?> <?= e($co['currency_code']) ?></span></span>
                            <?php if ($is_sel): ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" style="color:var(--accent-text)"><path d="M5 13l4 4L19 7"/></svg><?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Notifications (assets/js/bell.js). Each row opens notifications/open/{id}:
                 marked read, then the exact page. -->
            <div class="relative" x-data="nfBell()" @keydown.escape="notifOpen = false">
                <button type="button" @click="notifOpen = !notifOpen; if (notifOpen) load();" @click.away="notifOpen = false" :aria-expanded="notifOpen"
                        class="nf-icon-btn" aria-label="Notifications" data-tip="Notifications">
                    <?= nav_icon('bell') ?>
                    <span class="nf-count" x-show="count > 0" x-cloak x-text="count > 9 ? '9+' : count"></span>
                    <span class="sr-only" x-text="count > 0 ? count + ' unread' : ''"></span>
                </button>
                <div x-show="notifOpen" x-cloak x-transition.opacity.duration.150ms class="nf-pop is-sheetlike" role="dialog" aria-label="Notifications">
                    <div class="nf-pop-h">
                        <b>Notifications</b>
                        <button type="button" x-show="count > 0" @click="markAllRead()">Mark all read</button>
                        <a class="nf-icon-btn" href="<?= APP_URL ?>/more#notifications" aria-label="Notification settings" data-tip="Notification settings"><?= nav_icon('settings') ?></a>
                    </div>
                    <div style="overflow-y:auto;max-height:22rem;flex:1" class="no-scrollbar">
                        <p x-show="loading" style="padding:20px 16px;color:var(--ink-3);font-size:.875rem">Loading…</p>
                        <p x-show="failed" x-cloak style="padding:20px 16px;color:var(--ink-2);font-size:.875rem">Couldn’t load your notifications. Check your connection and open the bell again.</p>
                        <div x-show="!loading && loaded && notifications.length === 0" x-cloak style="padding:28px 20px;text-align:center">
                            <p style="font-weight:600;margin:0 0 4px">You're up to date</p>
                            <p style="color:var(--ink-2);font-size:.875rem;margin:0">Payments, reminders and new orders show up here.</p>
                        </div>
                        <template x-for="notif in notifications" :key="notif.id">
                            <a :href="window.Nfelia.url + '/' + notif.url" @click="open(notif)"
                               class="nf-note" :class="notif.is_read == 1 ? '' : 'is-new'">
                                <i></i>
                                <span style="min-width:0">
                                    <b x-text="notif.title"></b>
                                    <p x-text="notif.message"></p>
                                    <small x-text="notif.ago"></small>
                                </span>
                            </a>
                        </template>
                    </div>
                    <a class="nf-pop-all" href="<?= APP_URL ?>/notifications">See all notifications</a>
                </div>
            </div>

            <!-- Account (computers; phones use More) -->
            <div class="relative hidden lg:block" @keydown.escape="profileOpen = false">
                <button type="button" @click="profileOpen = !profileOpen" @click.away="profileOpen = false" :aria-expanded="profileOpen" class="nf-icon-btn" aria-label="Account" data-tip="Account">
                    <?php if (!empty($user['avatar_path'])): ?>
                        <span class="nf-avatar"><img src="<?= APP_URL ?>/<?= e($user['avatar_path']) ?>" alt=""></span>
                    <?php else: ?>
                        <span class="nf-avatar"><?= e(strtoupper(mb_substr($user['full_name'], 0, 1))) ?></span>
                    <?php endif; ?>
                </button>
                <div x-show="profileOpen" x-cloak x-transition.opacity.duration.150ms class="nf-pop nf-menu" style="width:16rem">
                    <div class="nf-pop-h" style="display:block">
                        <b style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($user['full_name']) ?></b>
                        <span style="display:block;font-size:.8125rem;color:var(--ink-3);overflow:hidden;text-overflow:ellipsis"><?= e($user['email']) ?></span>
                    </div>
                    <a href="<?= APP_URL ?>/settings"><?= nav_icon('settings') ?>Settings</a>
                    <?php if ($user_role === 'owner'): ?><a href="<?= APP_URL ?>/settings?tab=billing"><?= nav_icon('card') ?>Billing</a><?php endif; ?>
                    <a href="<?= APP_URL ?>/user-guide"><?= nav_icon('book') ?>User guide</a>
                    <a href="<?= APP_URL ?>/contact-support"><?= nav_icon('chat') ?>Contact support</a>
                    <hr>
                    <form action="<?= APP_URL ?>/logout" method="POST" class="m-0">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                        <button type="submit"><?= nav_icon('signout') ?>Sign out</button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <!-- ===== CONTENT ===== -->
    <main class="nf-main" id="main">
        <?php if ($view_as = view_as_active()): ?>
            <!-- Nfelia support looking, read-only (app/helpers/view_as.php) -->
            <div class="nf-banner is-warn" role="status" data-view-as>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                <span class="grow">Viewing <?= e($business['name']) ?> as Nfelia support (<?= e($view_as['staff']) ?>). Read-only; ends in <?= max(1, (int)ceil(($view_as['until'] - time()) / 60)) ?> min.</span>
                <form action="<?= APP_URL ?>/support-view/end" method="POST" class="m-0">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <button type="submit">End and go back to Ops</button>
                </form>
            </div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['billing_warning']) && $user_role === 'owner'): ?>
            <div class="nf-banner is-warn" role="status">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                <span class="grow"><?= e($_SESSION['billing_warning']) ?></span>
                <a href="<?= APP_URL ?>/settings?tab=billing">Update billing</a>
            </div>
        <?php endif; ?>
        <?php if ($show_verify_banner): ?>
            <div class="nf-banner is-info" x-data="{ show: true }" x-show="show" role="status">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 8l7.9 5.3a2 2 0 0 0 2.2 0L21 8M5 19h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2Z"/></svg>
                <span class="grow">Verify your email to share documents with your customers.</span>
                <form action="<?= APP_URL ?>/resend-verification" method="POST" class="m-0">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <button type="submit">Resend the link</button>
                </form>
                <button type="button" @click="show = false" aria-label="Hide this message" style="text-decoration:none;padding:10px">✕</button>
            </div>
        <?php endif; ?>
        <div class="px-4 lg:px-8 py-6 flex-1 w-full max-w-7xl mx-auto">
            <?php if (isset($view_file) && file_exists($view_file)): ?>
                <?php require_once $view_file; ?>
            <?php else: ?>
                <div class="text-center py-20">
                    <h2 class="font-heading font-bold text-2xl text-brand-plum mb-2">Page not found</h2>
                    <p class="text-sm text-brand-plum/60">This page doesn't exist. Go back, or open Home.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- ===== TABS (phones and tablets) ===== -->
    <nav class="nf-tabbar" aria-label="Tabs">
        <ul>
            <?php foreach ([['home', 'dashboard', 'Home'], ['orders', 'orders', 'Orders'], ['create', '', ''], ['tasks', 'tasks', 'Tasks'], ['more', 'more', 'More']] as [$key, $url, $label]): ?>
                <li>
                <?php if ($key === 'create'): ?>
                    <button type="button" class="nf-tab-create" aria-label="Create" aria-haspopup="dialog" data-open="nf-create"><?= nav_icon('plus') ?></button>
                <?php else:
                    $here = $current_tab === $key; ?>
                    <a class="nf-tab" href="<?= APP_URL ?>/<?= $url ?>"<?= $here ? ' aria-current="' . ($is_tab_root ? 'page' : 'true') . '"' : '' ?>><?= nav_icon($key) ?><span><?= $label ?></span></a>
                <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <!-- ===== CREATE ===== -->
    <dialog class="nf-sheet" id="nf-create" aria-labelledby="nf-create-h">
        <div class="nf-grab"></div>
        <div class="nf-sheet-h">
            <h2 id="nf-create-h">Create</h2>
            <button type="button" class="nf-icon-btn" data-close aria-label="Close"><?= nav_icon('close') ?></button>
        </div>
        <div class="nf-create">
            <a class="is-big" href="<?= APP_URL ?>/orders/create"><span class="nf-tile-ic"><?= nav_icon('orders') ?></span>New order</a>
            <a class="is-big" href="<?= APP_URL ?>/orders/quick"><span class="nf-tile-ic"><?= nav_icon('bolt') ?></span>Quick sale</a>
            <a href="<?= APP_URL ?>/quotations/create"><span class="nf-tile-ic"><?= nav_icon('quote') ?></span>New quote</a>
            <a href="<?= APP_URL ?>/tasks?new=task"><span class="nf-tile-ic"><?= nav_icon('task') ?></span>Add task</a>
            <a href="<?= APP_URL ?>/customers/create"><span class="nf-tile-ic"><?= nav_icon('customer') ?></span>Add customer</a>
            <a href="<?= APP_URL ?>/products/create"><span class="nf-tile-ic"><?= nav_icon('stock') ?></span><?= $business_type === 'service' ? 'Add service' : 'Add product' ?></a>
            <?php if ($is_boss): ?>
                <a href="<?= APP_URL ?>/expenses/create"><span class="nf-tile-ic"><?= nav_icon('expense') ?></span>Add expense</a>
            <?php endif; ?>
        </div>
    </dialog>

    <!-- ===== INFO: the longer words behind an (i), filled by shell.js (nf_info() in app/helpers/ui.php) ===== -->
    <dialog class="nf-sheet nf-info-sheet" id="nf-info" aria-labelledby="nf-info-h">
        <div class="nf-grab"></div>
        <div class="nf-sheet-h">
            <h2 id="nf-info-h" data-info-title></h2>
            <button type="button" class="nf-icon-btn" data-close aria-label="Close"><?= nav_icon('close') ?></button>
        </div>
        <p class="nf-info-text" data-info-text></p>
        <div class="nf-actions"><button type="button" class="nf-btn nf-btn-primary" data-close>Got it</button></div>
    </dialog>

    <!-- ===== INSTALL (shown by shell.js after sign-in, on a phone) ===== -->
    <dialog class="nf-sheet" id="nf-install" aria-labelledby="nf-install-h">
        <div class="nf-grab"></div>
        <div class="nf-install-app">
            <img src="<?= APP_URL ?>/android-chrome-192x192.png" alt="" width="64" height="64">
            <div><b id="nf-install-h">Add Nfelia to your home screen</b><span>It takes a few seconds.</span></div>
        </div>
        <ul class="nf-reasons">
            <li><span class="nf-tile-ic"><?= nav_icon('phone') ?></span>Opens like an app, full screen</li>
            <li><span class="nf-tile-ic"><?= nav_icon('bell') ?></span>Payments and reminders come to your phone</li>
            <li><span class="nf-tile-ic"><?= nav_icon('home') ?></span>Faster, even on a weak network</li>
        </ul>
        <ol class="nf-steps" data-install-ios hidden>
            <li><span>Tap <b>Share</b> <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-label="the share icon"><path d="M12 3v12M7.5 7.5 12 3l4.5 4.5M6 11H5a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-8a1 1 0 0 0-1-1h-1"/></svg> in your browser. In Safari it may be under <b>•••</b>.</span></li>
            <li><span>Tap <b>Add to Home Screen</b>. Scroll down if you don’t see it.</span></li>
            <li><span>Tap <b>Add</b>. Nfelia appears on your home screen.</span></li>
        </ol>
        <div class="nf-actions">
            <button type="button" class="nf-btn nf-btn-ghost" data-install-later>Not now</button>
            <button type="button" class="nf-btn nf-btn-primary" data-install-go hidden>Install Nfelia</button>
            <button type="button" class="nf-btn nf-btn-primary" data-install-done hidden>Done</button>
        </div>
    </dialog>

    <!-- ===== LOCK (assets/js/lock.js): after <?= e($lock_words) ?> without use ===== -->
    <dialog class="nf-lock" id="nf-lock" aria-labelledby="nf-lock-h">
        <div class="nf-lock-card">
            <span class="nf-lock-avatar"><?= $avatar ?></span>
            <h2 id="nf-lock-h">Welcome back, <?= e($first_name) ?></h2>
            <p class="nf-lock-sub">Nfelia locked itself after <?= e($lock_words) ?> without use.</p>
            <button type="button" class="nf-btn nf-btn-primary nf-lock-pk" data-lock-passkey hidden><?= nav_icon('fingerprint') ?><span>Unlock with <span data-passkey-name>a passkey</span></span></button>
            <form class="nf-lock-pw" data-lock-password novalidate>
                <label for="nf-lock-pw">Password</label>
                <input id="nf-lock-pw" type="password" name="password" autocomplete="current-password" enterkeyhint="go">
                <button type="submit" class="nf-btn nf-btn-primary">Unlock</button>
            </form>
            <button type="button" class="nf-lock-alt" data-lock-use-password hidden>Use password instead</button>
            <p class="nf-lock-err" data-lock-error role="alert" hidden></p>
            <p class="nf-lock-offline" data-lock-offline hidden>You’re offline. Connect to the internet to unlock.</p>
            <form action="<?= APP_URL ?>/logout" method="POST" class="nf-lock-out">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <button type="submit">Not <?= e($first_name) ?>? Sign out</button>
            </form>
        </div>
    </dialog>

    <!-- ===== PASSKEY OFFER (lock.js, right after signing in) ===== -->
    <dialog class="nf-sheet" id="nf-passkey" aria-labelledby="nf-passkey-h">
        <div class="nf-grab"></div>
        <div class="nf-pk-offer">
            <span class="nf-pk-badge"><?= nav_icon('fingerprint') ?></span>
            <h2 id="nf-passkey-h" data-pk-title>Sign in with a passkey next time</h2>
            <p>No password to type: open Nfelia the way you unlock your phone. Your fingerprint and face never leave your phone.</p>
            <p class="nf-pk-team">Everyone on your team sets up their own, on their own phone.</p>
            <p class="nf-lock-err" data-pk-error role="alert" hidden></p>
        </div>
        <div class="nf-actions">
            <button type="button" class="nf-btn nf-btn-ghost" data-pk-later>Not now</button>
            <button type="button" class="nf-btn nf-btn-primary" data-pk-go>Set it up</button>
        </div>
    </dialog>

    <!-- Toasts: flash messages from the server, and nfToast() from scripts. -->
    <div class="nf-toasts" id="nf-toasts" role="status" aria-live="polite">
        <?php if ($flash): $type = in_array($flash['type'], ['error', 'warning'], true) ? $flash['type'] : 'success'; ?>
            <div class="nf-toast is-<?= $type ?>" data-toast>
                <?php if ($type === 'success'): ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12.2 2.8 2.8L16 9.5"/></svg>
                <?php else: ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.5h.01"/></svg>
                <?php endif; ?>
                <span><?= e($flash['message']) ?></span>
                <button type="button" class="x" aria-label="Dismiss"><?= nav_icon('close') ?></button>
            </div>
        <?php endif; ?>
    </div>

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
    <script src="<?= $asset('assets/vendor/jquery-3.7.1.min.js') ?>"></script>
    <script src="<?= $asset('assets/js/app.js') ?>"></script>
    <script src="<?= $asset('assets/js/shell.js') ?>" defer></script>
    <script src="<?= $asset('assets/js/passkey.js') ?>" defer></script>
    <script src="<?= $asset('assets/js/lock.js') ?>" defer></script>
    <script src="<?= $asset('assets/js/phone.js') ?>" defer></script>
    <script src="<?= $asset('assets/js/wa.js') ?>" defer></script>

    <!-- Global Feedback Modal -->
    <?php
    if (file_exists(__DIR__ . '/../partials/feedback_modal.php')) {
        require_once __DIR__ . '/../partials/feedback_modal.php';
    }
    ?>
</body>
</html>
