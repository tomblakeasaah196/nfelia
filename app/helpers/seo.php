<?php
/**
 * Nfelia — being found: shop news posts, robots.txt and sitemaps, and the
 * public shop directory (nfelia.com/shops).
 *
 * Only shops Google may show are offered to it: paid shops ('live' in
 * shop_visibility). Trial shops and closed shops ask search engines to stay
 * away from every page, and are never in a sitemap or the directory.
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
 * and working pages; others keep them out entirely.
 */
function shop_robots(array $business, string $visibility): string {
    if ($visibility !== 'live') {
        return "User-agent: *\nDisallow: /\n";
    }
    return "User-agent: *\n"
         . "Disallow: /order/\nDisallow: /review/\nDisallow: /booking/\nDisallow: /book/\nDisallow: /checkout\nDisallow: /quote-request\n"
         . "\nSitemap: " . shop_url($business['subdomain'], '/sitemap.xml') . "\n";
}

/**
 * A paid shop's sitemap: its front page, its full list and contact page,
 * every product page and every news post, with when each last changed.
 */
function shop_sitemap(PDO $db, array $business): string {
    $settings  = storefront_settings($business, 'published');
    $catalogue = storefront_catalogue($business, $settings);
    $urls = [[shop_url($business['subdomain']), $business['storefront_published_at'] ?: $catalogue['updated_at']]];
    if ((sf_section($settings, 'catalogue')['on'] ?? false) && $catalogue['items']) {
        $urls[] = [shop_url($business['subdomain'], '/shop'), $catalogue['updated_at']];
    }
    $urls[] = [shop_url($business['subdomain'], '/contact'), $business['storefront_published_at']];
    $stmt = $db->prepare("SELECT id, updated_at FROM products WHERE business_id = ?");
    $stmt->execute([(int)$business['id']]);
    $changed = array_column($stmt->fetchAll(), 'updated_at', 'id');
    foreach ($catalogue['items'] as $item) {
        $urls[] = [shop_url($business['subdomain'], sf_product_path($item)), $changed[$item['id']] ?? null];
    }
    $posts = shop_posts($db, (int)$business['id'], 200);
    if ($posts) {
        $urls[] = [shop_url($business['subdomain'], '/news'), $posts[0]['updated_at']];
    }
    foreach ($posts as $post) {
        $urls[] = [shop_url($business['subdomain'], shop_post_path($post)), $post['updated_at']];
    }
    return sitemap_xml($urls);
}

/**
 * <urlset> from [url, last changed] pairs.
 */
function sitemap_xml(array $urls): string {
    $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($urls as [$loc, $at]) {
        $out .= '  <url><loc>' . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>'
              . ($at ? '<lastmod>' . date('Y-m-d', strtotime((string)$at)) . '</lastmod>' : '') . "</url>\n";
    }
    return $out . "</urlset>\n";
}

/**
 * Every shop Google may show (paid, with a live link and its page on), with
 * what the directory needs.
 */
function seo_live_shops(PDO $db, bool $directory_only = false): array {
    $sql = fn(string $exempt) => "
        SELECT b.id, b.name, b.subdomain, b.tagline, b.category, b.city, b.city_slug, b.logo_path, b.directory_opt_in,
               b.storefront_published_at, b.storefront_look, b.updated_at,
               s.status, s.trial_ends_at, s.current_period_end, s.grace_ends_at, s.last_payment_date, {$exempt} AS expiry_exempt
        FROM businesses b
        LEFT JOIN subscriptions s ON s.business_id = b.id
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
 * nfelia.com/robots.txt: the app stays out of search; the home page and the
 * directory are in.
 */
function main_robots(): string {
    $disallow = ['/login', '/register', '/dashboard', '/landing-page', '/shop-setup', '/admin', '/api/', '/doc/', '/sign/', '/delivery/',
                 '/signin', '/forgot-password', '/reset-password', '/verify', '/deed/'];
    return "User-agent: *\n" . implode('', array_map(fn($p) => 'Disallow: ' . $p . "\n", $disallow))
         . "Allow: /shops\n\nSitemap: " . APP_URL . "/sitemap.xml\n";
}

/**
 * nfelia.com/sitemap.xml: a sitemap index of the main site's own pages and
 * every paid shop's sitemap. (Google reads the shops' sitemaps from here once
 * the nfelia.com domain is verified in Search Console.)
 */
function main_sitemap_index(PDO $db): string {
    $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    $out .= '  <sitemap><loc>' . htmlspecialchars(APP_URL . '/sitemap-pages.xml', ENT_XML1) . "</loc></sitemap>\n";
    foreach (seo_live_shops($db) as $shop) {
        $out .= '  <sitemap><loc>' . htmlspecialchars(shop_url($shop['subdomain'], '/sitemap.xml'), ENT_XML1) . '</loc>'
              . ($shop['storefront_published_at'] ? '<lastmod>' . date('Y-m-d', strtotime($shop['storefront_published_at'])) . '</lastmod>' : '')
              . "</sitemap>\n";
    }
    return $out . "</sitemapindex>\n";
}

/**
 * The main site's own pages: the home page and the directory pages that
 * have shops in them.
 */
function main_pages_sitemap(PDO $db): string {
    $urls = [[APP_URL . '/', null]];
    $shops = seo_live_shops($db, true);
    if ($shops) {
        $urls[] = [APP_URL . '/shops', null];
        $seen = [];
        foreach ($shops as $shop) {
            $cat = directory_category_slug((string)$shop['category']);
            $city_ok = $shop['city_slug'] && sf_known_city($shop['city']) !== '';   // listed cities only (W17)
            foreach (array_filter([$city_ok ? '/shops/' . $shop['city_slug'] : null,
                                   $city_ok && $cat ? '/shops/' . $shop['city_slug'] . '/' . $cat : null]) as $path) {
                $seen[$path] = true;
            }
        }
        foreach (array_keys($seen) as $path) {
            $urls[] = [APP_URL . $path, null];
        }
    }
    return sitemap_xml($urls);
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
