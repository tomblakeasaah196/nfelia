<?php
/**
 * Nfelia — being found: shop news posts, robots.txt and sitemaps, and the
 * public shop directory (nfelia.com/shops).
 *
 * Only shops Google may show are offered to it: paid shops ('live' in
 * shop_visibility). Trial shops ask search engines to stay away from every
 * page; closed shops let them in to read that every page is noindex (a
 * blocked page's noindex is never seen). Neither is ever in a sitemap or
 * the directory.
 *
 * nfelia.com's sitemaps (index.php, BEING FOUND):
 *   /sitemap.xml         the index: the four below, then every paid shop's own
 *   /sitemap-pages.xml   the site's pages (app/helpers/site.php) but help and blog
 *   /sitemap-help.xml    the help centre's articles
 *   /sitemap-blog.xml    the guides
 *   /sitemap-shops.xml   the shop directory: /shops, its cities and kinds
 * The same lists feed IndexNow (app/helpers/indexnow.php), so what a search
 * engine is told about is exactly what the sitemaps hold.
 */

/* ──────────────────────────────────────────
 *  NEWS POSTS
 * ────────────────────────────────────────── */

function shop_post_path(array $post): string {
    return '/news/' . (int)$post['id'] . '-' . sf_product_slug((string)$post['title']);
}

/**
 * A shop's published posts, newest first.
 */
function shop_posts(PDO $db, int $business_id, int $limit = 20): array {
    $stmt = $db->prepare("
        SELECT id, title, body, image_path, published_at, updated_at
        FROM shop_posts
        WHERE business_id = ? AND status = 'published' AND deleted_at IS NULL
        ORDER BY published_at DESC, id DESC
        LIMIT " . max(1, min(200, $limit)));
    $stmt->execute([$business_id]);
    return $stmt->fetchAll();
}

function shop_post(PDO $db, int $business_id, int $id): ?array {
    $stmt = $db->prepare("
        SELECT id, title, body, image_path, published_at, updated_at
        FROM shop_posts
        WHERE business_id = ? AND id = ? AND status = 'published' AND deleted_at IS NULL
    ");
    $stmt->execute([$business_id, $id]);
    return $stmt->fetch() ?: null;
}

/**
 * The first words of a post, for cards and descriptions.
 */
function shop_post_excerpt(string $body, int $max = 160): string {
    $text = trim(preg_replace('/\s+/u', ' ', $body) ?? '');
    return mb_strlen($text) > $max ? rtrim(mb_substr($text, 0, $max - 1)) . '…' : $text;
}

/* ──────────────────────────────────────────
 *  ROBOTS AND SITEMAPS
 * ────────────────────────────────────────── */

/**
 * A shop's robots.txt: paid shops let search engines in, except to private
 * and working pages. A trial shop keeps them out entirely (the owner's
 * choice: trial shops stay hidden). A closed shop (its paid month or trial
 * over) lets them in as a paid one does, so they read the closed notice's
 * noindex and drop its pages cleanly: Google's advice is never to block a
 * paused site in robots.txt, which can leave its addresses in results.
 */
function shop_robots(array $business, string $visibility): string {
    if ($visibility === 'unlisted') {
        return "User-agent: *\nDisallow: /\n";
    }
    return "User-agent: *\n"
         . "Disallow: /order/\nDisallow: /review/\nDisallow: /booking/\nDisallow: /book/\nDisallow: /checkout\nDisallow: /quote-request\n"
         . ($visibility === 'live' ? "\nSitemap: " . shop_url($business['subdomain'], '/sitemap.xml') . "\n" : '');
}

/**
 * A paid shop's sitemap: its front page, its full list and contact page,
 * every product page and every news post, with when each last changed and
 * the photos each one shows.
 */
function shop_sitemap(PDO $db, array $business): string {
    return sitemap_xml(shop_sitemap_urls($db, $business));
}

/**
 * The addresses in a paid shop's sitemap, as sitemap_xml() takes them:
 * [url, last changed, ['images' => [photo url, ...], 'shows' => fingerprint]].
 * The front page carries the shop's logo and the photos at the top of it; a
 * product page its photos, the same files (and the same addresses) its page
 * shows. IndexNow compares this list day to day (app/helpers/indexnow.php).
 *
 * Product pages and the full list carry no "last changed": the only date
 * Nfelia keeps for a product (products.updated_at) moves with every sale,
 * because the stock count changes, while the page shows only the price,
 * the words, the photos and whether it is sold out. A wrong date makes a
 * search engine distrust them all, so there is none; 'shows' is a
 * fingerprint of what the page shows instead, for IndexNow to compare.
 */
function shop_sitemap_urls(PDO $db, array $business): array {
    $settings  = storefront_settings($business, 'published');
    $catalogue = storefront_catalogue($business, $settings);
    $name = (string)$business['subdomain'];
    $shows = [];
    foreach ($catalogue['items'] as $item) {
        $shows[$item['id']] = md5((string)json_encode($item));
    }
    $all_shown = md5(implode(',', $shows));
    $published = $business['storefront_published_at'] ?: null;
    $urls = [[shop_url($name), $published,
              ['images' => shop_sitemap_cover($business, $settings), 'shows' => sitemap_date($published) . '/' . $all_shown]]];
    if ((sf_section($settings, 'catalogue')['on'] ?? false) && $catalogue['items']) {
        $urls[] = [shop_url($name, '/shop'), null, ['shows' => $all_shown]];
    }
    $urls[] = [shop_url($name, '/contact'), $published];
    foreach ($catalogue['items'] as $item) {
        $urls[] = [shop_url($name, sf_product_path($item)), null,
                   ['images' => seo_image_urls($item['images']), 'shows' => $shows[$item['id']]]];
    }
    $posts = shop_posts($db, (int)$business['id'], 200);
    if ($posts) {
        $urls[] = [shop_url($name, '/news'), $posts[0]['updated_at']];
    }
    foreach ($posts as $post) {
        $urls[] = [shop_url($name, shop_post_path($post)), $post['updated_at'],
                   ['images' => seo_image_urls([(string)$post['image_path']])]];
    }
    return $urls;
}

/**
 * A shop's own pictures for its front page: the logo (as the page's schema
 * and link preview use it) and the photos at the top of the page (the
 * slides and the page background). Videos and missing files are left out.
 */
function shop_sitemap_cover(array $business, array $settings): array {
    $paths = [];
    $logo  = (string)($business['logo_path'] ?? '');
    if ($logo !== '' && !str_ends_with(strtolower($logo), '.svg')) {
        $paths[] = $logo;
    }
    $hero = sf_section($settings, 'hero') ?? [];
    foreach (array_merge(is_array($hero['slides'] ?? null) ? $hero['slides'] : [], [$hero['image'] ?? '', $hero['background'] ?? '']) as $rel) {
        if (is_string($rel) && preg_match('/\.(png|jpe?g|webp)$/i', $rel)) {
            $paths[] = $rel;
        }
    }
    return seo_image_urls($paths);
}

/**
 * Uploaded photos as full addresses, the way shop pages link them
 * (sf_media_url), skipping any file that is not there: Google is never sent
 * to a picture that would not load. Only whether the file is there is
 * checked, not its size: a sitemap lists every photo of every product, and
 * opening each one on every request (and every IndexNow run) costs too much.
 */
function seo_image_urls(array $paths): array {
    $out = [];
    foreach ($paths as $rel) {
        $rel = ltrim((string)$rel, '/');
        if (str_starts_with($rel, 'uploads/') && !str_contains($rel, '..') && preg_match('/\.(png|jpe?g|webp|gif)$/i', $rel)
            && is_file(BASE_PATH . '/' . $rel)) {
            $out[] = sf_media_url($rel);
        }
    }
    return array_values(array_unique($out));
}

/**
 * <urlset> from [url, last changed] pairs. A third, optional item adds
 * ['images' => [url, ...]] (image:image, up to 1,000 a page, as Google
 * reads them) and ['alternates' => [hreflang => url]] (the same page for
 * other countries, itself and x-default included). Their namespaces are
 * declared only when used.
 */
function sitemap_xml(array $urls): string {
    $x = fn(string $s) => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $has_images = $has_alternates = false;
    foreach ($urls as $u) {
        $has_images     = $has_images || !empty($u[2]['images']);
        $has_alternates = $has_alternates || !empty($u[2]['alternates']);
    }
    $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
         . ($has_alternates ? ' xmlns:xhtml="http://www.w3.org/1999/xhtml"' : '')
         . ($has_images ? ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"' : '') . ">\n";
    foreach ($urls as $u) {
        $at    = sitemap_date($u[1] ?? null);
        $extra = '';
        foreach ($u[2]['alternates'] ?? [] as $lang => $href) {
            $extra .= "\n    " . '<xhtml:link rel="alternate" hreflang="' . $x((string)$lang) . '" href="' . $x((string)$href) . '"/>';
        }
        foreach (array_slice($u[2]['images'] ?? [], 0, 1000) as $image) {
            $extra .= "\n    <image:image><image:loc>" . $x((string)$image) . '</image:loc></image:image>';
        }
        $out .= '  <url><loc>' . $x((string)$u[0]) . '</loc>' . ($at ? '<lastmod>' . $at . '</lastmod>' : '')
              . ($extra !== '' ? $extra . "\n  " : '') . "</url>\n";
    }
    return $out . "</urlset>\n";
}

/** A date or time as a sitemap's lastmod (Y-m-d), or null when there is none. */
function sitemap_date(mixed $at): ?string {
    $time = ($at === null || $at === '') ? false : strtotime((string)$at);
    return $time ? date('Y-m-d', $time) : null;
}

/**
 * Every shop Google may show (paid, with a live link and its page on), with
 * what the directory needs, its country included (country_iso; a business
 * with none set is Nigerian, as everywhere else).
 */
function seo_live_shops(PDO $db, bool $directory_only = false): array {
    $sql = fn(string $exempt) => "
        SELECT b.id, b.name, b.subdomain, b.tagline, b.category, b.city, b.city_slug, b.logo_path, b.directory_opt_in,
               b.storefront_published_at, b.storefront_look, b.updated_at, UPPER(COALESCE(c.iso_code, 'NG')) AS country_iso,
               s.status, s.trial_ends_at, s.current_period_end, s.grace_ends_at, s.last_payment_date, {$exempt} AS expiry_exempt
        FROM businesses b
        LEFT JOIN subscriptions s ON s.business_id = b.id
        LEFT JOIN countries c ON c.id = b.country_id
        WHERE b.subdomain IS NOT NULL AND b.landing_enabled = 1 AND b.is_active = 1   -- a live link stays listed while a rename waits (W7)
        " . ($directory_only ? "AND b.directory_opt_in = 1" : "") . "
        ORDER BY b.name ASC
    ";
    try {
        $rows = $db->query($sql('s.expiry_exempt'))->fetchAll();
    } catch (PDOException $e) {
        // The minute of a deploy before the migration adds the column
        error_log('[Nfelia] seo_live_shops: ' . $e->getMessage());
        $rows = $db->query($sql('0'))->fetchAll();
    }
    return array_values(array_filter($rows, fn($row) => shop_visibility($row['status'] !== null ? $row : null) === 'live'));
}

/**
 * The search engines and AI assistants named in nfelia.com's robots.txt.
 * Each reads its own name first: Google (and Google-Extended, Google's
 * Gemini and AI training control), Bing, OpenAI (GPTBot trains,
 * OAI-SearchBot is ChatGPT search, ChatGPT-User fetches for a person),
 * Anthropic (ClaudeBot, Claude-SearchBot, Claude-User, the same three
 * roles), Perplexity, Apple (Applebot-Extended is Apple's AI control),
 * Amazon, Meta, DuckDuckGo's assistant, Common Crawl and Yandex.
 */
const SEO_CRAWLERS = [
    'Googlebot', 'Google-Extended', 'Bingbot',
    'GPTBot', 'OAI-SearchBot', 'ChatGPT-User',
    'ClaudeBot', 'Claude-SearchBot', 'Claude-User',
    'PerplexityBot', 'Perplexity-User',
    'Applebot', 'Applebot-Extended', 'Amazonbot', 'Meta-ExternalAgent', 'DuckAssistBot', 'CCBot', 'YandexBot',
];

/**
 * nfelia.com/robots.txt. The site's own pages, the shop directory, and the
 * sign-in and sign-up pages (so Google can show "Sign in" and "Start free"
 * under Nfelia) are open to everyone; the app's private and working pages
 * are not.
 *
 * The crawlers in SEO_CRAWLERS are named in the same group as *, so they all
 * read exactly the same rules: a crawler that finds its own name follows
 * only that group and ignores the * one, so a group of its own would need
 * every rule repeated. Content-Signal (contentsignals.org) says search,
 * AI answers and AI training may all use the pages.
 */
function main_robots(): string {
    // /login and /register themselves are open; what follows them (/login/…,
    // /register/…) reaches the token links' code, as /signin/… and
    // /reset-password/… do. The bare /signin, /reset-password and
    // /forgot-password pages are open, so their own noindex is read.
    $disallow = ['/dashboard', '/admin', '/landing-page', '/shop-setup', '/api/', '/doc/', '/sign/', '/delivery/', '/deed/',
                 '/signin/', '/login-link', '/reset-password/', '/recovery-sent', '/verify',
                 '/unsubscribe/', '/support-view/', '/login/', '/register/'];
    return "# nfelia.com: Nfelia's own pages and the shop directory are open to search\n"
         . "# engines and AI assistants; the app's private pages are not.\n"
         . "# For AI assistants, what Nfelia is in one file: " . APP_URL . "/llms.txt\n"
         . "# and every page as plain text: " . APP_URL . "/llms-full.txt\n"
         . "# Content signals (https://contentsignals.org): search, AI answers (ai-input)\n"
         . "# and AI training may all use these pages.\n\n"
         . "User-agent: *\n"
         . implode('', array_map(fn($bot) => 'User-agent: ' . $bot . "\n", SEO_CRAWLERS))
         . "Content-Signal: search=yes, ai-input=yes, ai-train=yes\n"
         . implode('', array_map(fn($p) => 'Disallow: ' . $p . "\n", $disallow))
         . "Allow: /shops\n\nSitemap: " . APP_URL . "/sitemap.xml\n";
}

/** nfelia.com's own sitemaps, in the order the index lists them (sitemap-<name>.xml). */
const MAIN_SITEMAPS = ['pages', 'help', 'blog', 'shops'];

/**
 * Whether a page of the site is offered to search engines: not marked
 * noindex, and really served (the landing page at /, or a page whose view
 * is there; a page still being written stays out until its view is).
 */
function seo_site_page_listed(array $page): bool {
    if (!empty($page['noindex'])) {
        return false;
    }
    if ($page['view'] === null) {
        return $page['path'] === '/';
    }
    return is_file(BASE_PATH . '/views/site/pages/' . $page['view'] . '.php');
}

/** Which of nfelia.com's sitemaps a page of the site is in: its help and blog groups have their own. */
function seo_site_sitemap_of(array $page): string {
    return in_array($page['group'], ['help', 'blog'], true) ? $page['group'] : 'pages';
}

/**
 * The addresses in one of nfelia.com's own sitemaps (MAIN_SITEMAPS), as
 * sitemap_xml() takes them. Site pages carry when they last changed, their
 * versions for other countries (hreflang) and their own picture when they
 * have one (not the cards og-image.php draws).
 */
function main_sitemap_urls(?PDO $db, string $name): array {
    if ($name === 'shops') {
        return main_directory_urls($db ?? get_db());
    }
    $urls = [];
    foreach (site_pages() as $page) {
        if (!seo_site_page_listed($page) || seo_site_sitemap_of($page) !== $name) {
            continue;
        }
        $urls[] = [site_url($page['key']), $page['updated'], array_filter([
            'alternates' => site_alternates($page),
            'images'     => $page['image'] ? [site_image($page)] : [],
        ])];
    }
    usort($urls, fn($a, $b) => strcmp($a[0], $b[0]));   // by address: the home page first, each section together
    return $urls;
}

/** One of nfelia.com's own sitemaps (MAIN_SITEMAPS) as XML. */
function main_sitemap(?PDO $db, string $name): string {
    return sitemap_xml(main_sitemap_urls($db, $name));
}

/**
 * nfelia.com/sitemap.xml: a sitemap index of the site's own sitemaps (those
 * with something in them) and every paid shop's sitemap. (Google reads the
 * shops' sitemaps from here once the nfelia.com domain is verified in Search
 * Console.)
 */
function main_sitemap_index(PDO $db): string {
    $x = fn(string $s) => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach (MAIN_SITEMAPS as $name) {
        $urls = main_sitemap_urls($db, $name);
        if (!$urls && $name !== 'pages') {
            continue;
        }
        $last = max(array_map(fn($u) => sitemap_date($u[1] ?? null) ?? '', $urls) ?: ['']);
        $out .= '  <sitemap><loc>' . $x(APP_URL . '/sitemap-' . $name . '.xml') . '</loc>'
              . ($last !== '' ? '<lastmod>' . $last . '</lastmod>' : '') . "</sitemap>\n";
    }
    // A shop's sitemap last changed when the newest date in it did: the shop
    // was published, or a news post was (product pages carry none, see
    // shop_sitemap_urls)
    $posted = [];
    try {
        $posted = $db->query("SELECT business_id, MAX(updated_at) FROM shop_posts WHERE status = 'published' AND deleted_at IS NULL GROUP BY business_id")
                     ->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (PDOException $e) {
        error_log('[Nfelia] main_sitemap_index: ' . $e->getMessage());
    }
    foreach (seo_live_shops($db) as $shop) {
        $at = max(sitemap_date($shop['storefront_published_at']) ?? '', sitemap_date($posted[$shop['id']] ?? null) ?? '');
        $out .= '  <sitemap><loc>' . $x(shop_url($shop['subdomain'], '/sitemap.xml')) . '</loc>'
              . ($at !== '' ? '<lastmod>' . $at . '</lastmod>' : '') . "</sitemap>\n";
    }
    return $out . "</sitemapindex>\n";
}

/**
 * The site's pages sitemap (sitemap-pages.xml). Kept by this name for older
 * callers; the shop directory is in sitemap-shops.xml now.
 */
function main_pages_sitemap(PDO $db): string {
    return main_sitemap($db, 'pages');
}

/**
 * The shop directory's pages offered to search engines (directory_indexable):
 * /shops, each kind of shop, each listed city and each kind in a city, last
 * changed when the newest of their shops was published.
 */
function main_directory_urls(PDO $db): array {
    $shops = seo_live_shops($db, true);
    if (!$shops) {
        return [];
    }
    $pages = directory_pages($shops);
    $urls = [];
    foreach ($pages as $path => $list) {
        if (directory_indexable($path, $pages)) {
            $urls[] = [APP_URL . $path, max(array_map(fn($s) => (string)($s['storefront_published_at'] ?? ''), $list)) ?: null];
        }
    }
    return $urls;
}

/** The fewest shops a directory page other than /shops needs before search engines are offered it. */
const DIRECTORY_MIN_SHOPS = 3;

/**
 * The directory's pages and the shops on each, [path => [shop, ...]]:
 * /shops, each kind (/shops/{kind}), each listed city (/shops/{city}) and
 * each kind in a city (/shops/{city}/{kind}). A city page holds the shops
 * of that city in its own country's list (W17, sf_cities), so all of one
 * country; the full list and the kinds hold every listed shop, wherever it
 * is.
 */
function directory_pages(array $shops): array {
    $pages = ['/shops' => $shops];
    foreach ($shops as $shop) {
        $cat = directory_category_slug((string)$shop['category']);
        $pages['/shops/' . $cat][] = $shop;
        if (directory_city_listed($shop)) {
            $pages['/shops/' . $shop['city_slug']][] = $shop;
            $pages['/shops/' . $shop['city_slug'] . '/' . $cat][] = $shop;
        }
    }
    return $pages;
}

/**
 * Whether a shop has a city page: its city is in its own country's list
 * (W17). A shop filed under a city of another country (a shop in Cameroon
 * that picked Lagos when the list was Nigeria's) has none.
 */
function directory_city_listed(array $shop): bool {
    return !empty($shop['city_slug']) && sf_known_city($shop['city'], $shop['country_iso'] ?? 'NG') !== '';
}

/**
 * Whether a directory page is offered to search engines (index, and in
 * sitemap-shops.xml). /shops is, with any shop in it. Any other page needs
 * DIRECTORY_MIN_SHOPS shops and a list that differs from the page above it
 * (/shops for a city or a kind, the city for a kind in a city): a page that
 * repeats its parent, or shows only a shop or two, is thin and stays out,
 * though people can still open it.
 */
function directory_indexable(string $path, array $pages): bool {
    $list = $pages[$path] ?? [];
    if (!$list) {
        return false;
    }
    if ($path === '/shops') {
        return true;
    }
    if (count($list) < DIRECTORY_MIN_SHOPS) {
        return false;
    }
    $parent = substr_count($path, '/') === 3 ? dirname($path) : '/shops';
    return array_column($list, 'id') !== array_column($pages[$parent] ?? [], 'id');
}

/**
 * Every address in nfelia.com's own sitemaps, [url => last changed (Y-m-d)
 * or ''], for IndexNow (app/helpers/indexnow.php).
 */
function seo_main_urls(PDO $db): array {
    $out = [];
    foreach (MAIN_SITEMAPS as $name) {
        foreach (main_sitemap_urls($db, $name) as $u) {
            $out[$u[0]] = sitemap_date($u[1] ?? null) ?? '';
        }
    }
    return $out;
}

/* ──────────────────────────────────────────
 *  DIRECTORY — nfelia.com/shops/{city}/{kind}
 * ────────────────────────────────────────── */

/**
 * What shops sell, by businesses.category, as the directory names it.
 */
function directory_categories(): array {
    return [
        'fashion'          => ['slug' => 'fashion', 'name' => 'Fashion and tailoring'],
        'beauty'           => ['slug' => 'hair-and-beauty', 'name' => 'Hair and beauty'],
        'food_beverage'    => ['slug' => 'food', 'name' => 'Food and drinks'],
        'tech_repair'      => ['slug' => 'phones-and-gadgets', 'name' => 'Phones, gadgets and repairs'],
        'services'         => ['slug' => 'services', 'name' => 'Home and business services'],
        'wholesale_retail' => ['slug' => 'wholesale', 'name' => 'Wholesale and provisions'],
        'distribution'     => ['slug' => 'distribution', 'name' => 'Distribution'],
        'creative'         => ['slug' => 'events-and-creative', 'name' => 'Events, design and creative'],
        'other'            => ['slug' => 'more-shops', 'name' => 'Beauty, farms, stays and more'],
    ];
}

function directory_category_slug(string $category): string {
    return directory_categories()[$category]['slug'] ?? directory_categories()['other']['slug'];
}
