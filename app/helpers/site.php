<?php
/**
 * Nfelia — the public website: nfelia.com's own pages (features, pricing,
 * help, the blog, the country pages...), what search engines and AI
 * assistants read about them, and the pieces every page is built from.
 *
 * One list of pages is the whole site: site_pages(), gathered from
 * app/site/pages/*.php. The router serves from it, and the sitemaps,
 * llms.txt, breadcrumbs, menus and schema are made from it, so a page added
 * there is found everywhere at once.
 *
 * These pages are served before any session starts (index.php, BEING
 * FOUND): no cookie, cacheable, the same for everyone.
 *
 * A page in the list:
 *
 *   'features/debts' => [                      the key: how other pages name it
 *       'path'        => '/features/debts',     where it lives (no trailing slash; '/' is home)
 *       'view'        => 'features/debts',      views/site/pages/<view>.php (null: home, served by views/home)
 *       'title'       => '…',                   the <title>, up to about 60 characters
 *       'description' => '…',                   the meta description, about 140 to 160 characters
 *       'crumb'       => 'Debts',               its short name in breadcrumbs, menus and cards
 *       'parent'      => 'features',            the page above it in the breadcrumbs (null: home)
 *       'type'        => 'WebPage',             its schema.org type: WebPage, CollectionPage, AboutPage,
 *                                               ContactPage, FAQPage, Article, TechArticle or BlogPosting
 *       'group'       => 'features',            main, features, for, countries, help, blog or legal
 *       'published'   => '2026-10-28',          first published, and
 *       'updated'     => '2026-10-28',          last changed in a way a reader would notice (Y-m-d)
 *   optional:
 *       'country'     => 'GH',                  a page for one country (default: Nigeria)
 *       'alternates'  => 'pricing',             pages for different countries sharing this name are
 *                                               the same page for each country (hreflang)
 *       'summary'     => '…',                   one line for llms.txt and cards (default: description)
 *       'image'       => '/assets/img/…',       its main picture (default: a card drawn by og-image.php)
 *       'og'          => ['kicker' => '…', 'title' => '…'],   the words on that card
 *       'noindex'     => true,                  served, but kept out of search and the sitemaps
 *       'order'       => 10,                    its place among its group in menus and lists
 *   ]
 */

require_once __DIR__ . '/prices.php';

/** Nfelia's own profiles elsewhere (Instagram, LinkedIn, X...). Each one listed here is linked to Nfelia in Google's eyes. */
const SITE_SAME_AS = [
    'YouTube'   => 'https://www.youtube.com/@nfelia',
    'Instagram' => 'https://www.instagram.com/getnfelia',
    'X'         => 'https://x.com/getnfelia',
];

/** The day the public site's pages were last reviewed as a whole. */
const SITE_REVIEWED = '2026-10-28';

/* ──────────────────────────────────────────
 *  WHO WE ARE
 * ────────────────────────────────────────── */

/**
 * The facts about Nfelia that every page, the schema and llms.txt repeat.
 * Nfelia is a product of JBS Praxis. The address is JBS Praxis's registered
 * office (for letters and the legal pages), not a shop people walk into.
 * JBS Praxis has no company registration number to show: never add one.
 */
function site_org(): array {
    return [
        'name'       => 'Nfelia',
        'legal'      => 'JBS Praxis',
        'address'    => ['street' => '21B Bode Ajakaiye Street', 'locality' => 'Lekki', 'region' => 'Lagos', 'country' => 'NG', 'country_name' => 'Nigeria'],
        'address_text' => '21B Bode Ajakaiye Street, Lekki, Lagos, Nigeria',
        'url'        => APP_URL . '/',
        'logo'       => APP_URL . '/android-chrome-512x512.png',
        'mark'       => APP_URL . '/assets/img/nfelia-mark-email.png',
        'banner'     => APP_URL . '/assets/img/nfelia-og-banner.png',
        'tagline'    => 'The thread that holds your business together.',
        'about'      => 'Nfelia gives small businesses their own website, plus invoices, receipts, debts, stock, bookings and profit, all on the phone.',
        'support'    => 'support@nfelia.com',
        'sales'      => 'sales@nfelia.com',
        'phone'      => '+2348169789106',
        'phone_text' => '+234 816 978 9106',
        'whatsapp'   => '2348169789106',
        'same_as'    => SITE_SAME_AS,
    ];
}

/** A wa.me link to Nfelia, with the message filled in when there is one. */
function site_wa(string $text = ''): string {
    return 'https://wa.me/' . site_org()['whatsapp'] . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

/* ──────────────────────────────────────────
 *  COUNTRIES
 * ────────────────────────────────────────── */

/**
 * The countries the site speaks to. Nigeria is the site itself (no prefix);
 * the others have their own home and pricing pages under /gh, /ke and /cm.
 * What a month costs comes from the countries table (Ops → Countries and
 * Prices), never from here: see site_prices().
 */
function site_countries(): array {
    return [
        'NG' => ['iso' => 'NG', 'name' => 'Nigeria',  'prefix' => '',    'hreflang' => 'en-NG', 'locale' => 'en_NG', 'currency' => 'NGN', 'symbol' => '₦',
                 'people' => 'Nigerian', 'cities' => ['Lagos', 'Abuja', 'Port Harcourt', 'Ibadan', 'Kano', 'Benin City'],
                 'customers_pay' => 'bank transfer (Moniepoint, OPay, Kuda, any bank)', 'flag' => '🇳🇬'],
        'GH' => ['iso' => 'GH', 'name' => 'Ghana',    'prefix' => '/gh', 'hreflang' => 'en-GH', 'locale' => 'en_GH', 'currency' => 'GHS', 'symbol' => 'GH₵',
                 'people' => 'Ghanaian', 'cities' => ['Accra', 'Kumasi', 'Tema', 'Takoradi', 'Tamale', 'Cape Coast'],
                 'customers_pay' => 'mobile money (MTN MoMo, Telecel Cash, AT Money) or bank transfer', 'flag' => '🇬🇭'],
        'KE' => ['iso' => 'KE', 'name' => 'Kenya',    'prefix' => '/ke', 'hreflang' => 'en-KE', 'locale' => 'en_KE', 'currency' => 'KES', 'symbol' => 'KSh',
                 'people' => 'Kenyan', 'cities' => ['Nairobi', 'Mombasa', 'Kisumu', 'Nakuru', 'Eldoret', 'Thika'],
                 'customers_pay' => 'M-Pesa (Till or Paybill), Airtel Money or bank transfer', 'flag' => '🇰🇪'],
        'CM' => ['iso' => 'CM', 'name' => 'Cameroon', 'prefix' => '/cm', 'hreflang' => 'en-CM', 'locale' => 'en_CM', 'currency' => 'XAF', 'symbol' => 'FCFA',
                 'people' => 'Cameroonian', 'cities' => ['Douala', 'Yaoundé', 'Bamenda', 'Buea', 'Limbe', 'Bafoussam'],
                 'customers_pay' => 'mobile money (MTN MoMo, Orange Money) or bank transfer', 'flag' => '🇨🇲'],
    ];
}

function site_country(?string $iso): array {
    $all = site_countries();
    return $all[strtoupper((string)$iso)] ?? $all['NG'];
}

/**
 * What Nfelia costs in a country today, as Ops → Prices has it, or null
 * while the country is not set up for sign-ups (no active countries row).
 * ['month', 'seat', 'book'] in the country's money, plus 'trial_days',
 * 'pays_by' (paystack, mobile_money...), 'momo_shown' (Billing shows the
 * Mobile Money number to pay; when not, its instructions say to ask us on
 * WhatsApp) and 'money' (a formatter).
 */
function site_prices(string $iso): ?array {
    static $cache = [];
    $iso = strtoupper($iso);
    if (!array_key_exists($iso, $cache) || defined('NFELIA_TESTING')) {
        $cache[$iso] = null;
        try {
            $db = get_db();
            $country = prices_country($db, $iso);
            if ($country && (int)$country['is_active'] === 1) {
                $now = prices_now($db, $country);
                $cache[$iso] = $now + [
                    'trial_days' => (int)($country['trial_days'] ?: TRIAL_DAYS),
                    'pays_by'    => (string)$country['payment_method'],
                    'momo_shown' => trim((string)($country['momo_number'] ?? '')) !== '',
                    'currency'   => (string)$country['currency_code'],
                    'money'      => fn(float $n) => prices_money($n, $country),
                ];
            }
        } catch (Throwable $e) {
            error_log('[Nfelia] site_prices: ' . $e->getMessage());
            if ($iso === 'NG') {
                // The page still shows Nigeria's launch prices if the database is away
                $cache[$iso] = ['month' => prices_fallback('month'), 'seat' => prices_fallback('seat'), 'book' => prices_fallback('book'),
                                'trial_days' => TRIAL_DAYS, 'pays_by' => 'paystack', 'momo_shown' => false, 'currency' => 'NGN',
                                'money' => fn(float $n) => '₦' . number_format($n)];
            }
        }
    }
    return $cache[$iso];
}

/* ──────────────────────────────────────────
 *  THE PAGES
 * ────────────────────────────────────────── */

/**
 * Every page of the site, by key, from app/site/pages/*.php (each file
 * returns an array of pages). Pages are checked once here, so a mistake in a
 * list shows up in the error log rather than as a broken link.
 */
function site_pages(): array {
    static $pages = null;
    if ($pages !== null) {
        return $pages;
    }
    $pages = [];
    foreach (glob(BASE_PATH . '/app/site/pages/*.php') ?: [] as $file) {
        foreach ((array)require $file as $key => $page) {
            $pages[$key] = $page + [
                'key' => $key, 'view' => null, 'parent' => null, 'type' => 'WebPage', 'group' => 'main',
                'country' => 'NG', 'alternates' => null, 'summary' => null, 'image' => null, 'og' => [],
                'noindex' => false, 'order' => 100, 'published' => SITE_REVIEWED, 'updated' => SITE_REVIEWED,
            ];
        }
    }
    foreach ($pages as $key => $page) {
        foreach (['path', 'title', 'description', 'crumb'] as $need) {
            if (empty($page[$need])) {
                error_log("[Nfelia] site page {$key} has no {$need}");
            }
        }
        if ($page['parent'] !== null && !isset($pages[$page['parent']])) {
            error_log("[Nfelia] site page {$key}: no parent {$page['parent']}");
        }
    }
    uasort($pages, fn($a, $b) => [$a['order'], $a['crumb']] <=> [$b['order'], $b['crumb']]);
    return $pages;
}

function site_page(string $key): ?array {
    return site_pages()[$key] ?? null;
}

/** The page at a path (as the router sees it: no leading or trailing slash), or null. */
function site_page_by_path(string $path): ?array {
    $path = '/' . trim($path, '/');
    foreach (site_pages() as $page) {
        if ($page['path'] === $path) {
            return $page;
        }
    }
    return null;
}

/** The full address of a page, by key. */
function site_url(string $key): string {
    $page = site_page($key);
    if (!$page) {
        error_log("[Nfelia] site_url: no page {$key}");
        return APP_URL . '/';
    }
    return APP_URL . ($page['path'] === '/' ? '/' : $page['path']);
}

/** The pages of a group, in order (for menus, hubs and llms.txt). */
function site_group(string $group, bool $with_hidden = false): array {
    return array_values(array_filter(site_pages(), fn($p) => $p['group'] === $group && ($with_hidden || !$p['noindex'])));
}

/** The pages under a page, in order. */
function site_children(string $key): array {
    return array_values(array_filter(site_pages(), fn($p) => $p['parent'] === $key && !$p['noindex']));
}

/** Home first, down to the page: [[name, url], ...]. */
function site_trail(array $page): array {
    $trail = [];
    $top   = $page;
    for ($p = $page, $guard = 0; $p && $guard < 8; $p = $p['parent'] ? site_page($p['parent']) : null, $guard++) {
        $trail[] = [$p['crumb'], site_url($p['key'])];
        $top = $p;
    }
    if ($top['path'] !== '/') {
        $trail[] = ['Nfelia', APP_URL . '/'];
    }
    return array_reverse($trail);
}

/**
 * Whether a page shows breadcrumbs: every page but the home page, the
 * country pages included. The schema has them exactly when the page shows
 * them (Google asks for markup only of what readers see).
 */
function site_shows_crumbs(array $page): bool {
    return count(site_trail($page)) > 1;
}

/**
 * The same page for every country it exists in: [hreflang => url], with
 * x-default (Nigeria's, the site's own) when there is more than one.
 */
function site_alternates(array $page): array {
    if (!$page['alternates']) {
        return [];
    }
    $out = [];
    foreach (site_pages() as $p) {
        if ($p['alternates'] === $page['alternates'] && !$p['noindex']) {
            $out[site_country($p['country'])['hreflang']] = site_url($p['key']);
        }
    }
    if (count($out) > 1 && isset($out['en-NG'])) {
        $out['x-default'] = $out['en-NG'];
    }
    return count($out) > 1 ? $out : [];
}

/** The picture shared with a page: its own, or a card with its name drawn on it. */
function site_image(array $page): string {
    if ($page['image']) {
        return str_starts_with($page['image'], 'http') ? $page['image'] : APP_URL . $page['image'];
    }
    return APP_URL . '/og-image.php?page=' . rawurlencode($page['key']) . '&v=' . str_replace('-', '', $page['updated']);
}

/* ──────────────────────────────────────────
 *  SERVING A PAGE
 * ────────────────────────────────────────── */

/**
 * Serve a page: its view inside the site layout. The view can add to the
 * schema (site_schema_add) and gathers its questions (site_faq).
 */
function site_render(array $page): void {
    $GLOBALS['site_page']   = $page;
    $GLOBALS['site_faqs']   = [];
    $GLOBALS['site_nodes']  = [];
    $country = site_country($page['country']);
    require_once BASE_PATH . '/views/home/_icons.php';   // the building blocks draw icons
    ob_start();
    require BASE_PATH . '/views/site/pages/' . $page['view'] . '.php';
    $content = ob_get_clean();
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: public, max-age=600, s-maxage=3600');
    header('Content-Language: ' . $country['hreflang']);
    if ($page['noindex']) {
        header('X-Robots-Tag: noindex, follow');
    }
    require BASE_PATH . '/views/site/layout.php';
}

/** Add a node to this page's schema graph (an ItemList, an Offer...). */
function site_schema_add(array $node): void {
    $GLOBALS['site_nodes'][] = $node;
}

/* ──────────────────────────────────────────
 *  SCHEMA
 * ────────────────────────────────────────── */

/**
 * The schema.org graph for a page: who Nfelia is (the same on every page,
 * so search engines and AI assistants join the facts up), the website, the
 * app, this page and its breadcrumbs, plus whatever the page added.
 */
function site_schema(array $page): array {
    $org  = site_org();
    $url  = $page['path'] === '/' ? APP_URL . '/' : APP_URL . $page['path'];
    $lang = site_country($page['country'])['hreflang'];

    $graph = [];
    $graph[] = array_filter([
        '@type'       => 'Organization',
        '@id'         => APP_URL . '/#organization',
        'name'        => $org['name'],   // a product of JBS Praxis (parentOrganization), not a company of its own: no legalName
        'url'         => $org['url'],
        'logo'        => ['@type' => 'ImageObject', '@id' => APP_URL . '/#logo', 'url' => $org['logo'], 'width' => 512, 'height' => 512, 'caption' => 'Nfelia'],
        'image'       => ['@id' => APP_URL . '/#logo'],
        'description' => $org['about'],
        'slogan'      => $org['tagline'],
        'email'       => $org['support'],
        'telephone'   => $org['phone'],
        'address'     => ['@type' => 'PostalAddress', '@id' => APP_URL . '/#address', 'streetAddress' => $org['address']['street'],
                          'addressLocality' => $org['address']['locality'], 'addressRegion' => $org['address']['region'], 'addressCountry' => $org['address']['country']],
        'sameAs'      => array_values($org['same_as']) ?: null,
        'areaServed'  => array_map(fn($c) => ['@type' => 'Country', 'name' => $c['name'], 'identifier' => $c['iso']], array_values(site_countries())),
        'knowsLanguage' => ['en'],
        // The address is JBS Praxis's registered office, so it is the parent's too
        'parentOrganization' => ['@type' => 'Organization', '@id' => APP_URL . '/#jbs-praxis', 'name' => $org['legal'], 'legalName' => $org['legal'],
                                 'address' => ['@id' => APP_URL . '/#address']],
        'contactPoint' => [
            ['@type' => 'ContactPoint', 'contactType' => 'customer support', 'telephone' => $org['phone'], 'email' => $org['support'],
             'availableLanguage' => ['English'], 'areaServed' => array_keys(site_countries()), 'url' => site_wa()],
            ['@type' => 'ContactPoint', 'contactType' => 'sales', 'telephone' => $org['phone'], 'email' => $org['sales'],
             'availableLanguage' => ['English'], 'areaServed' => array_keys(site_countries())],
        ],
    ]);
    $graph[] = [
        '@type'       => 'WebSite',
        '@id'         => APP_URL . '/#website',
        'url'         => APP_URL . '/',
        'name'        => 'Nfelia',
        'alternateName' => ['Nfelia by JBS Praxis', 'nfelia.com'],
        'description' => $org['about'],
        'publisher'   => ['@id' => APP_URL . '/#organization'],
        'inLanguage'  => 'en',
    ];
    if (in_array($page['group'], ['main', 'features', 'for', 'countries'], true)) {
        $graph[] = site_schema_app($page['country']);
    }

    $trail = site_trail($page);
    $webpage = array_filter([
        '@type'       => in_array($page['type'], ['Article', 'TechArticle', 'BlogPosting'], true) ? 'WebPage' : $page['type'],
        '@id'         => $url . '#webpage',
        'url'         => $url,
        'name'        => $page['title'],
        'description' => $page['description'],
        'isPartOf'    => ['@id' => APP_URL . '/#website'],
        'about'       => ['@id' => APP_URL . '/#' . (in_array($page['group'], ['main', 'features', 'for', 'countries'], true) ? 'software' : 'organization')],
        'inLanguage'  => $lang,
        'datePublished' => $page['published'],
        'dateModified'  => $page['updated'],
        'primaryImageOfPage' => ['@type' => 'ImageObject', 'url' => site_image($page)],
        'breadcrumb'  => site_shows_crumbs($page) ? ['@id' => $url . '#breadcrumb'] : null,
        'mainEntity'  => !empty($GLOBALS['site_faqs']) && $page['type'] === 'FAQPage' ? array_map('site_schema_question', $GLOBALS['site_faqs']) : null,
    ]);
    $graph[] = $webpage;
    if (site_shows_crumbs($page)) {
        $graph[] = [
            '@type' => 'BreadcrumbList',
            '@id'   => $url . '#breadcrumb',
            'itemListElement' => array_map(fn($i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $trail[$i][0], 'item' => $trail[$i][1]], array_keys($trail)),
        ];
    }
    if (in_array($page['type'], ['Article', 'TechArticle', 'BlogPosting'], true)) {
        $graph[] = [
            '@type'            => $page['type'],
            '@id'              => $url . '#article',
            'headline'         => $page['og']['title'] ?? $page['crumb'],
            'description'      => $page['description'],
            'image'            => site_image($page),
            'datePublished'    => $page['published'],
            'dateModified'     => $page['updated'],
            'author'           => ['@type' => 'Organization', 'name' => 'The Nfelia team', 'url' => site_page('about') ? site_url('about') : APP_URL . '/'],
            'publisher'        => ['@id' => APP_URL . '/#organization'],
            'mainEntityOfPage' => ['@id' => $url . '#webpage'],
            'isPartOf'         => ['@id' => APP_URL . '/#website'],
            'inLanguage'       => $lang,
        ];
    }
    if (!empty($GLOBALS['site_faqs']) && $page['type'] !== 'FAQPage') {
        // Questions on a page that is about something else: a FAQPage of
        // their own, so assistants can still quote the answers
        $graph[] = ['@type' => 'FAQPage', '@id' => $url . '#faq', 'isPartOf' => ['@id' => $url . '#webpage'],
                    'mainEntity' => array_map('site_schema_question', $GLOBALS['site_faqs'])];
    }
    foreach ($GLOBALS['site_nodes'] ?? [] as $node) {
        $graph[] = $node;
    }
    return ['@context' => 'https://schema.org', '@graph' => array_values($graph)];
}

/**
 * Nfelia the app, with what a month costs in each country that is open for
 * sign-ups. Shown on the pages about the product.
 */
function site_schema_app(string $iso = 'NG'): array {
    $offers = [];
    foreach (site_countries() as $c) {
        $p = site_prices($c['iso']);
        if (!$p) {
            continue;
        }
        $offers[] = [
            '@type'         => 'Offer',
            'name'          => 'Nfelia, one month (' . $c['name'] . ')',
            'price'         => number_format($p['month'], 2, '.', ''),
            'priceCurrency' => $p['currency'],
            'eligibleRegion' => ['@type' => 'Country', 'name' => $c['name']],
            'priceSpecification' => ['@type' => 'UnitPriceSpecification', 'price' => number_format($p['month'], 2, '.', ''), 'priceCurrency' => $p['currency'],
                                     'billingDuration' => 'P1M', 'unitCode' => 'MON', 'referenceQuantity' => ['@type' => 'QuantitativeValue', 'value' => 1, 'unitCode' => 'MON']],
            'url'           => site_page(($c['prefix'] ? strtolower($c['iso']) . '/' : '') . 'pricing') ? site_url(($c['prefix'] ? strtolower($c['iso']) . '/' : '') . 'pricing') : APP_URL . '/',
            'availability'  => 'https://schema.org/InStock',
        ];
    }
    return array_filter([
        '@type'               => 'SoftwareApplication',
        '@id'                 => APP_URL . '/#software',
        'name'                => 'Nfelia',
        'url'                 => APP_URL . '/',
        'applicationCategory' => 'BusinessApplication',
        'applicationSubCategory' => 'Small business management, invoicing and online store',
        'operatingSystem'     => 'Web browser, Android, iOS (installs from the browser)',
        'description'         => site_org()['about'],
        'image'               => site_org()['banner'],
        'screenshot'          => APP_URL . '/assets/img/home/see/owed-1.webp',
        'featureList'         => [
            'Your own website at yourname.nfelia.com, with online orders and no commission',
            'Invoices, quotes and receipts with your logo, shared on WhatsApp',
            'Debt tracking with one-tap WhatsApp reminders',
            'Stock that updates itself when you sell, with low-stock warnings',
            'Bookings with deposits, on your website',
            'Order tracking links and delivery notes the customer signs',
            'Expenses and a monthly profit report',
            'A Records Book: all your records in one Excel workbook anyone can verify',
            'Tasks and a calendar with reminders',
        ],
        'softwareHelp'        => site_page('help') ? ['@type' => 'CreativeWork', 'url' => site_url('help')] : null,
        'publisher'           => ['@id' => APP_URL . '/#organization'],
        'creator'             => ['@id' => APP_URL . '/#jbs-praxis'],
        'countriesSupported'  => implode(', ', array_keys(site_countries())),
        'inLanguage'          => 'en',
        'isAccessibleForFree' => false,
        'offers'              => $offers ?: null,
    ], fn($v) => $v !== null);
}

function site_schema_question(array $qa): array {
    // A sentence that is only a link ("More about the Records Book.") means
    // nothing once the link is gone, so the schema's answer leaves it out
    $html = preg_replace('#(^|[.?!:]\s+)<a\b[^>]*>[^<]*</a>[.?!]?(?=\s|$)#u', '$1', $qa[1]) ?? $qa[1];
    if (trim(strip_tags($html)) === '') {
        $html = $qa[1];
    }
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return ['@type' => 'Question', 'name' => html_entity_decode($qa[0], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim(preg_replace('/\s+/u', ' ', $text))]];
}

/* ──────────────────────────────────────────
 *  BUILDING BLOCKS FOR VIEWS
 *  Each returns HTML. Words are escaped here unless a parameter says it
 *  takes HTML (then it must be written in the view, never from a visitor).
 * ────────────────────────────────────────── */

/** The link to sign up, with the country it came from. */
function site_signup_url(string $iso = 'NG'): string {
    return APP_URL . '/register' . ($iso !== 'NG' ? '?country=' . strtolower($iso) : '');
}

/**
 * The top of a page: a small label, the h1, a lead (HTML allowed), its
 * buttons and, beside it, up to two phone screens (see site_screens).
 *   ['kicker' => 'Debt tracker', 'h1' => '…', 'lead_html' => '…', 'actions' => true|[[label, url, class]], 'screens' => [...]]
 */
function site_hero(array $o): string {
    $country = site_country($GLOBALS['site_page']['country'] ?? 'NG');
    $actions = $o['actions'] ?? true;
    if ($actions === true) {
        $actions = [['Start free', site_signup_url($country['iso']), 'btn btn-primary'],
                    ['Chat on WhatsApp', site_wa('Hello Nfelia, I want to know more about ' . ($o['kicker'] ?? 'Nfelia') . '.'), 'btn btn-outline', 'whatsapp']];
    }
    $html = '<section class="page-hero' . (!empty($o['screens']) ? ' has-visual' : '') . '"><div class="wrap page-hero-grid"><div class="page-hero-copy">';
    if (!empty($o['kicker'])) {
        $html .= '<p class="kicker">' . e($o['kicker']) . '</p>';
    }
    $html .= '<h1>' . e($o['h1']) . '</h1>';
    if (!empty($o['lead_html'])) {
        $html .= '<p class="page-lead">' . $o['lead_html'] . '</p>';
    }
    if ($actions) {
        $html .= '<div class="hero-actions">';
        foreach ($actions as $a) {
            $ext = str_starts_with($a[1], 'https://wa.me') ? ' target="_blank" rel="noopener"' : '';
            $html .= '<a class="' . e($a[2] ?? 'btn btn-primary') . '" href="' . e($a[1]) . '"' . $ext . '>' . (!empty($a[3]) ? nf_icon($a[3]) : '') . e($a[0]) . '</a>';
        }
        $html .= '</div>';
    }
    if (!empty($o['note'])) {
        $html .= '<p class="hero-note">' . nf_icon('check-circle') . e($o['note']) . '</p>';
    }
    $html .= '</div>';
    if (!empty($o['screens'])) {
        $html .= '<div class="page-hero-visual">' . site_screens($o['screens'], true) . '</div>';
    }
    return $html . '</div></section>';
}

/**
 * Phone screens from the app: [[shot, alt, caption], ...]. shot is a file
 * in assets/img/home/see without "-1.webp", e.g. 'owed-1' → owed-1.webp.
 * The first is loaded at once when $eager (the top of a page).
 */
function site_screens(array $shots, bool $eager = false): string {
    $html = '<div class="screens">';
    foreach ($shots as $i => $s) {
        [$shot, $alt, $caption] = $s + [2 => ''];
        $html .= '<figure class="screen"><div class="phone"><img src="' . APP_URL . '/assets/img/home/see/' . e($shot) . '.webp" width="540" height="1169" alt="' . e($alt) . '"'
               . ($eager && $i === 0 ? ' fetchpriority="high"' : ' loading="lazy"') . ' decoding="async"></div>'
               . ($caption !== '' ? '<figcaption>' . e($caption) . '</figcaption>' : '') . '</figure>';
    }
    return $html . '</div>';
}

/** A section heading: h2 and an optional lead (HTML allowed). */
function site_head(string $h2, string $lead_html = '', string $id = '', bool $center = false): string {
    return '<div class="section-head' . ($center ? ' center' : '') . '"><h2' . ($id !== '' ? ' id="' . e($id) . '"' : '') . '>' . e($h2) . '</h2>'
         . ($lead_html !== '' ? '<p>' . $lead_html . '</p>' : '') . '</div>';
}

/** A list with green ticks. Items are plain words, or [strong, rest]. */
function site_ticks(array $items): string {
    $html = '<ul class="ticks">';
    foreach ($items as $item) {
        $html .= '<li>' . nf_icon('check') . (is_array($item) ? '<span><strong>' . e($item[0]) . '</strong> ' . e($item[1]) . '</span>' : '<span>' . e($item) . '</span>') . '</li>';
    }
    return $html . '</ul>';
}

/**
 * Cards that link somewhere: [[title, text, url, icon?], ...], or page
 * keys (their crumb, summary and address are used).
 */
function site_cards(array $items, string $class = ''): string {
    $html = '<ul class="cards ' . e($class) . '">';
    foreach ($items as $item) {
        if (is_string($item)) {
            $p = site_page($item);
            if (!$p) {
                error_log("[Nfelia] site_cards: no page {$item}");
                continue;
            }
            $item = [$p['crumb'], $p['summary'] ?: $p['description'], site_url($item), $p['icon'] ?? null];
        }
        [$title, $text, $url, $icon] = $item + [3 => null];
        $html .= '<li><a class="card" href="' . e($url) . '">' . ($icon ? '<span class="card-icon">' . nf_icon($icon) . '</span>' : '')
               . '<h3>' . e($title) . '</h3><p>' . e($text) . '</p><span class="card-more">' . nf_icon('arrow') . '<span class="sr-only">Read about ' . e($title) . '</span></span></a></li>';
    }
    return $html . '</ul>';
}

/** Numbered steps: [[title, text (HTML allowed)], ...]. */
function site_steps(array $steps): string {
    $html = '<ol class="steps">';
    foreach ($steps as [$title, $text_html]) {
        $html .= '<li><div><h3>' . e($title) . '</h3><p>' . $text_html . '</p></div></li>';
    }
    return $html . '</ol>';
}

/**
 * Questions and answers, open one at a time. Answers may hold HTML (links).
 * They also go into the page's schema.
 */
function site_faq(array $qas): string {
    $html = '<div class="faq-list">';
    foreach ($qas as [$q, $a_html]) {
        $GLOBALS['site_faqs'][] = [$q, $a_html];
        $html .= '<details><summary>' . e($q) . '</summary><p>' . $a_html . '</p></details>';
    }
    return $html . '</div>';
}

/** "Read next": links to other pages by key. */
function site_related(array $keys, string $title = 'Read next'): string {
    $items = array_values(array_filter($keys, fn($k) => site_page($k) !== null));
    if (!$items) {
        return '';
    }
    return '<aside class="related" aria-labelledby="related-title"><div class="wrap"><h2 id="related-title">' . e($title) . '</h2>' . site_cards($items, 'cards-compact') . '</div></aside>';
}

/** The closing call: start free, or ask on WhatsApp. */
function site_cta(string $h2 = 'Try Nfelia free', string $lead = '', ?string $iso = null): string {
    $iso ??= $GLOBALS['site_page']['country'] ?? 'NG';
    $prices = site_prices($iso);
    $days = $prices['trial_days'] ?? TRIAL_DAYS;
    $lead = $lead !== '' ? $lead : "Use everything free for {$days} days. No card needed. Free setup help on WhatsApp.";
    return '<section class="cta-band" aria-labelledby="cta-title"><div class="wrap"><h2 id="cta-title">' . e($h2) . '</h2><p>' . e($lead) . '</p>'
         . '<div class="hero-actions"><a class="btn btn-light" href="' . e(site_signup_url($iso)) . '">Start free for ' . (int)$days . ' days</a>'
         . '<a class="btn btn-ghost-light" href="' . e(site_wa('Hello Nfelia, I have a question before I start.')) . '" target="_blank" rel="noopener">' . nf_icon('whatsapp') . 'Ask us on WhatsApp</a></div></div></section>';
}

/** When a page was written and last changed, for articles. */
function site_dates(array $page): string {
    $fmt = fn(string $d) => date('j F Y', strtotime($d));
    return '<p class="meta">By the Nfelia team · ' . ($page['updated'] !== $page['published']
        ? 'Updated <time datetime="' . e($page['updated']) . '">' . $fmt($page['updated']) . '</time>'
        : '<time datetime="' . e($page['published']) . '">' . $fmt($page['published']) . '</time>') . '</p>';
}
