<?php
/**
 * Nfelia — Storefront document (standalone, no app layout)
 *
 * Drawn by storefront_render() in app/helpers/storefront.php for shoppers at
 * name.nfelia.com, for the Shop Studio preview and for the look previews on
 * the setup screen. It never touches the session: shop pages set no cookie,
 * so they can be cached.
 *
 * Available: $business, $settings, $theme (storefront_resolve), $catalogue
 * (storefront_catalogue), $mode ('live', 'preview' or 'look'), $visibility
 * ('live' or 'unlisted') and $page: the shop itself, a product's page, an
 * order's tracking page, a review form, a booking page or a booking's own
 * page (see storefront_render). Every page has the shop's header, footer and
 * bag; order, review and booking pages are private and never indexed.
 *
 * Orders and quote requests post to the shop's own address (/checkout and
 * /quote-request); previews never send them.
 */
require_once __DIR__ . '/parts/helpers.php';

$kind      = $page['kind'];
$look      = $theme['def'];
$labels    = $look['labels'];
$currency  = trim((string)($business['currency_symbol'] ?? '')) ?: '₦';
$wa        = shop_whatsapp_digits($business);
$is_live   = $mode === 'live';
$shop_url  = $business['subdomain'] ? shop_url($business['subdomain']) : APP_URL;
$private   = in_array($kind, ['order', 'review', 'booking'], true);
$indexable = $is_live && !$private && $kind !== 'book' && ($visibility ?? 'live') === 'live';
$robots    = $indexable ? 'index, follow' : ($kind === 'book' && ($visibility ?? 'live') === 'live' ? 'noindex, follow' : 'noindex, nofollow');
$json_flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
sf_live_links($is_live);

$sections  = [];
foreach ($settings['sections'] as $section) {
    $sections[$section['type']] = $section;
}
$hero      = $sections['hero'];
$quote_on  = $sections['quote']['on'];
$contact   = $sections['contact'];

// Published reviews, for the reviews section and a product page's rating
$reviews = !empty($sections['reviews']['on']) && in_array($kind, ['shop', 'product'], true)
    ? shop_reviews(get_db(), (int)$business['id'])
    : null;
// The newest posts, for the news section
$news = !empty($sections['news']['on']) && $kind === 'shop' ? shop_posts(get_db(), (int)$business['id'], 3) : [];

// Shared with every partial
$ctx = [
    'business'  => $business,
    'mode'      => $mode,
    'currency'  => $currency,
    'wa'        => $wa,
    'look'      => $settings['look'],
    'def'       => $look,
    'labels'    => $labels,
    'quote_on'  => $quote_on,
    'catalogue' => $catalogue,
    'reviews'   => $reviews,
];

// Words for search engines and link previews
$headline = $hero['headline'] !== '' ? $hero['headline'] : (string)$business['name'];
$summary  = $hero['text'] !== '' ? $hero['text'] : ($sections['about']['text'] ?: (string)($business['tagline'] ?? ''));
$summary  = trim(preg_replace('/\s+/u', ' ', $summary) ?? '');
$seo_title = $business['name'] . ($headline !== $business['name'] ? ' — ' . $headline : '');
if ($summary === '') {
    $count = count($catalogue['items']);
    $summary = $business['name'] . ($count ? ': ' . $count . ' item' . ($count === 1 ? '' : 's') . ' to order online or on WhatsApp.' : '.');
}
$seo_desc = mb_strlen($summary) > 158 ? rtrim(mb_substr($summary, 0, 155)) . '…' : $summary;
// The owner's own words for Google, from Shop Studio, come first
$seo = $settings['seo'];
if ($seo['title'] !== '') {
    $seo_title = $seo['title'];
}
if ($seo['description'] !== '') {
    $seo_desc = $seo['description'];
}
$og_image = $business['logo_path'] && !str_ends_with((string)$business['logo_path'], '.svg')
    ? sf_media_url((string)$business['logo_path'])
    : APP_URL . '/og-image.php?biz=' . (int)$business['id'];
$og_size  = null;
$og_type  = 'business.business';
$canonical = $shop_url;

$socials = array_filter([
    'instagram' => $contact['instagram'] ? 'https://instagram.com/' . $contact['instagram'] : null,
    'facebook'  => $contact['facebook'] ? 'https://facebook.com/' . $contact['facebook'] : null,
    'tiktok'    => $contact['tiktok'] ? 'https://tiktok.com/@' . $contact['tiktok'] : null,
    'twitter'   => $contact['twitter'] ? 'https://x.com/' . $contact['twitter'] : null,
]);

$ld = [
    '@context' => 'https://schema.org',
    '@type'    => 'LocalBusiness',
    'name'     => (string)$business['name'],
    'url'      => $shop_url,
];
if ($summary !== '')              { $ld['description'] = $seo_desc; }
if ($business['logo_path'])       { $ld['logo'] = $ld['image'] = sf_media_url((string)$business['logo_path']); }
// Only what the owner shows on the page goes into Google's data (W3)
if ($business['phone'] && !empty($contact['show_phone']))     { $ld['telephone'] = (string)$business['phone']; }
if ($business['email'] && !empty($contact['show_email']))     { $ld['email'] = (string)$business['email']; }
if ($business['address'] && !empty($contact['show_address'])) { $ld['address'] = ['@type' => 'PostalAddress', 'streetAddress' => (string)$business['address'], 'addressCountry' => 'NG']; }
if ($seo['city'] !== '')          { $ld['address'] = ($ld['address'] ?? ['@type' => 'PostalAddress', 'addressCountry' => 'NG']) + ['addressLocality' => $seo['city']]; }
if ($catalogue['min_price'] !== null) {
    $prices = array_filter(array_column($catalogue['items'], 'price'), fn($p) => $p > 0);
    $ld['priceRange'] = sf_money(min($prices), $currency) . (max($prices) > min($prices) ? ' – ' . sf_money(max($prices), $currency) : '');
}
// Opening hours, when the owner has set booking hours
if (!empty($business['booking_settings'])) {
    $ld_hours = [];
    foreach (booking_settings($business)['hours'] as $key => $open) {
        if ($open) {
            $ld_hours[] = ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'https://schema.org/' . BOOKING_DAYS[$key], 'opens' => $open['open'], 'closes' => $open['close']];
        }
    }
    if ($ld_hours) {
        $ld['openingHoursSpecification'] = $ld_hours;
    }
}
if ($socials)                     { $ld['sameAs'] = array_values($socials); }
if ($catalogue['items']) {
    $ld['hasOfferCatalog'] = [
        '@type' => 'OfferCatalog',
        'name'  => 'Products and services',
        'numberOfItems' => count($catalogue['items']),
        'itemListElement' => array_map(fn($item) => [
            '@type'    => $item['is_service'] ? 'Service' : 'Product',
            'name'     => $item['name'],
            'url'      => $shop_url . sf_product_path($item),
            'category' => $item['category'],
            'offers'   => [
                '@type' => 'Offer',
                'price' => number_format($item['price'], 2, '.', ''),
                'priceCurrency' => business_currency_code($business),
                'availability'  => 'https://schema.org/InStock',
            ],
        ], array_slice($catalogue['items'], 0, 10)),
    ];
}
if ($quote_on) {
    $ld['potentialAction'] = ['@type' => 'QuoteAction', 'target' => $shop_url . '#sf-quote', 'name' => 'Ask for a quote'];
}

// A product's page speaks for the product
if ($kind === 'product') {
    $item      = $page['item'];
    $canonical = $shop_url . sf_product_path($item);
    $seo_title = $item['name'] . ' — ' . $business['name'];
    $og_type   = 'product';
    $price_text = $item['price'] > 0 ? sf_money($item['price'], $currency) . sf_unit_text($item['unit']) : '';
    $about_item = trim(preg_replace('/\s+/u', ' ', $item['description']) ?? '');
    $seo_desc = $about_item !== ''
        ? (mb_strlen($about_item) > 158 ? rtrim(mb_substr($about_item, 0, 155)) . '…' : $about_item)
        : $item['name'] . ($price_text !== '' ? ', ' . $price_text : '') . ', from ' . $business['name'] . '. Order online or on WhatsApp.';
    $item_images = [];
    foreach ($item['images'] as $rel) {
        if (sf_image_size($rel)) {
            $item_images[] = sf_media_url($rel);
            if ($og_size === null && ($og = sf_og_image($rel))) {
                [$og_image, $og_w, $og_h] = $og;
                $og_size = [$og_w, $og_h];
            }
        }
    }
    $product_ld = [
        '@context' => 'https://schema.org',
        '@type'    => $item['is_service'] ? 'Service' : 'Product',
        'name'     => $item['name'],
        'url'      => $canonical,
    ];
    if ($about_item !== '')    { $product_ld['description'] = mb_substr($about_item, 0, 5000); }
    if ($item_images)          { $product_ld['image'] = $item_images; }
    if ($item['category'] !== '') { $product_ld['category'] = $item['category']; }
    if ($item['is_service'])   { $product_ld['provider'] = ['@type' => 'LocalBusiness', 'name' => (string)$business['name'], 'url' => $shop_url]; }
    if ($item['price'] > 0) {
        $product_ld['offers'] = [
            '@type'         => 'Offer',
            'url'           => $canonical,
            'price'         => number_format($item['price'], 2, '.', ''),
            'priceCurrency' => business_currency_code($business),
            'availability'  => 'https://schema.org/InStock',
            'seller'        => ['@type' => 'Organization', 'name' => (string)$business['name'], 'url' => $shop_url],
        ];
    }
    $ld = [$product_ld, [
        '@context' => 'https://schema.org',
        '@type'    => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => (string)$business['name'], 'item' => $shop_url],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $item['name'], 'item' => $canonical],
        ],
    ]];
}

// News: the list, and each post as an article
if ($kind === 'news') {
    $canonical = $shop_url . '/news';
    $seo_title = 'News and offers — ' . $business['name'];
    $seo_desc  = 'The latest news and offers from ' . $business['name'] . '.';
    $ld        = null;
}
if ($kind === 'post') {
    $post      = $page['post'];
    $canonical = $shop_url . shop_post_path($post);
    $seo_title = $post['title'] . ' — ' . $business['name'];
    $seo_desc  = shop_post_excerpt((string)$post['body'], 158);
    $og_type   = 'article';
    if ($post['image_path'] && ($og = sf_og_image((string)$post['image_path']))) {
        [$og_image, $og_w, $og_h] = $og;
        $og_size = [$og_w, $og_h];
    }
    $ld = [[
        '@context'         => 'https://schema.org',
        '@type'            => 'BlogPosting',
        'headline'         => mb_substr((string)$post['title'], 0, 110),
        'description'      => $seo_desc,
        'datePublished'    => date('c', strtotime((string)$post['published_at'])),
        'dateModified'     => date('c', strtotime((string)$post['updated_at'])),
        'mainEntityOfPage' => $canonical,
        'author'           => ['@type' => 'Organization', 'name' => (string)$business['name'], 'url' => $shop_url],
        'publisher'        => ['@type' => 'Organization', 'name' => (string)$business['name'], 'url' => $shop_url]
                              + ($business['logo_path'] ? ['logo' => ['@type' => 'ImageObject', 'url' => sf_media_url((string)$business['logo_path'])]] : []),
    ] + ($post['image_path'] && sf_image_size((string)$post['image_path']) ? ['image' => [sf_media_url((string)$post['image_path'])]] : []), [
        '@context' => 'https://schema.org',
        '@type'    => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => (string)$business['name'], 'item' => $shop_url],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'News', 'item' => $shop_url . '/news'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => (string)$post['title'], 'item' => $canonical],
        ],
    ]];
}

// A booking page belongs to its product's page
if ($kind === 'book') {
    $canonical = $shop_url . sf_product_path($page['item']);
    $seo_title = 'Book ' . $page['item']['name'] . ' — ' . $business['name'];
    $seo_desc  = 'Pick a free time for ' . $page['item']['name'] . ' and book it with ' . $business['name'] . '.';
    $ld        = null;
}

// Order, review and booking pages belong to one shopper: no address to share, no data
if ($private) {
    $canonical = null;
    $ld        = null;
    $seo_title = match ($kind) {
        'order'   => 'Order ' . $page['order']['order_number'],
        'booking' => 'Booking ' . $page['booking']['booking_number'],
        default   => 'Review your order',
    } . ' — ' . $business['name'];
    $seo_desc  = ($kind === 'booking' ? 'Your booking with ' : 'Your order from ') . $business['name'] . '.';
}

// What storefront.js needs: the catalogue for the product sheet and the bag,
// bank accounts for checkout, and where to send things
$js_items = [];
foreach ($catalogue['items'] as $item) {
    $images = [];
    foreach ($item['images'] as $rel) {
        if ($size = sf_image_size($rel)) {
            $images[] = ['src' => sf_media_url($rel), 'set' => implode(', ', sf_image_srcset($rel)), 'w' => $size[0], 'h' => $size[1]];
        }
    }
    $js_items[(string)$item['id']] = [
        'id'       => $item['id'],
        'path'     => sf_product_path($item),
        'name'     => $item['name'],
        'desc'     => $item['description'],
        'price'    => $item['price'],
        'unit'     => $item['unit'],
        'cat'      => $item['category'],
        'svc'      => $item['is_service'],
        'img'      => $images,
        'variants' => $item['variants'],
        'book'     => $item['booking'] ? [
            'url'   => booking_page_path($item),
            'text'  => booking_summary($item['booking'], $item['price'], $currency),
            'label' => match ($item['booking']['mode']) { 'nights' => 'Check dates and book', 'day' => 'Pick a day and book', default => 'Pick a time and book' },
        ] : null,
    ];
}
$js = [
    'shop'     => $business['subdomain'] ?: 'preview-' . (int)$business['id'],
    'mode'     => $mode,
    'page'     => $kind,
    'name'     => (string)$business['name'],
    'url'      => $shop_url,
    'currency' => $currency,
    'wa'       => $wa,
    'look'     => $settings['look'],
    'items'    => (object)$js_items,
    'banks'    => array_map(fn($b) => ['id' => (int)$b['id'], 'bank' => $b['bank_name'], 'name' => $b['account_name'], 'number' => $b['account_number']], $catalogue['banks']),
    'delivery' => $sections['delivery']['on'] ? $sections['delivery']['text'] : '',
    'urls'     => ['checkout' => '/checkout', 'quote' => '/quote-request'],
];
if ($kind === 'book') {
    $book_info = $page['item']['booking'];
    $js['booking'] = [
        'id'       => $page['item']['id'],
        'mode'     => $book_info['mode'],
        'minutes'  => $book_info['minutes'],
        'price'    => $page['item']['price'],
        'deposit'  => ['type' => $book_info['deposit_type'], 'value' => $book_info['deposit_value']],
        'first'    => $page['window'][0],
        'last'     => $page['window'][1],
        'urls'     => ['free' => '/book/' . $page['item']['id'] . '/free', 'submit' => '/book/' . $page['item']['id']],
    ];
}

$asset = fn(string $path) => APP_URL . '/' . $path . '?v=' . (@filemtime(BASE_PATH . '/' . $path) ?: '1');
?>
<!DOCTYPE html>
<html lang="en" class="<?= e($theme['classes']) ?>" prefix="og: https://ogp.me/ns#">
<head>
    <meta charset="UTF-8">
    <?= errors_script_tag() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e($seo_title) ?></title>
    <meta name="description" content="<?= e($seo_desc) ?>">
    <meta name="robots" content="<?= $robots ?>">
    <meta name="theme-color" content="<?= e($theme['palette']['ground']) ?>">
    <?php if ($private): ?>
    <meta name="referrer" content="same-origin">
    <?php endif; ?>
    <?php if ($canonical !== null): ?>
    <link rel="canonical" href="<?= e($canonical) ?>">
    <?php endif; ?>

    <meta property="og:type" content="<?= e($og_type) ?>">
    <meta property="og:site_name" content="<?= e($business['name']) ?>">
    <meta property="og:title" content="<?= e($seo_title) ?>">
    <meta property="og:description" content="<?= e($seo_desc) ?>">
    <meta property="og:image" content="<?= e($og_image) ?>">
    <?php if ($og_size): ?>
    <meta property="og:image:width" content="<?= (int)$og_size[0] ?>">
    <meta property="og:image:height" content="<?= (int)$og_size[1] ?>">
    <meta property="og:image:alt" content="<?= e($kind === 'post' ? $page['post']['title'] : ($page['item']['name'] ?? $business['name'])) ?>">
    <?php endif; ?>
    <?php if ($canonical !== null): ?>
    <meta property="og:url" content="<?= e($canonical) ?>">
    <?php endif; ?>
    <?php if ($kind === 'post'): ?>
    <meta property="article:published_time" content="<?= e(date('c', strtotime((string)$page['post']['published_at']))) ?>">
    <meta property="article:modified_time" content="<?= e(date('c', strtotime((string)$page['post']['updated_at']))) ?>">
    <?php endif; ?>
    <?php if ($kind === 'product' && $page['item']['price'] > 0): ?>
    <meta property="product:price:amount" content="<?= e(number_format($page['item']['price'], 2, '.', '')) ?>">
    <meta property="product:price:currency" content="<?= e(business_currency_code($business)) ?>">
    <?php endif; ?>
    <meta property="og:locale" content="en_NG">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($seo_title) ?>">
    <meta name="twitter:description" content="<?= e($seo_desc) ?>">
    <meta name="twitter:image" content="<?= e($og_image) ?>">
    <?php if ($contact['twitter']): ?>
    <meta name="twitter:site" content="@<?= e($contact['twitter']) ?>">
    <?php endif; ?>

    <?php if ($ld !== null): ?>
    <script type="application/ld+json"><?= json_encode($ld, $json_flags) ?></script>
    <?php endif; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="<?= e($theme['fonts_url']) ?>" id="sf-fonts">
    <link rel="stylesheet" href="<?= e($asset('assets/css/storefront.css')) ?>">
    <style id="sf-vars"><?= storefront_css_vars($theme['vars']) ?></style>
</head>
<body>
    <a class="sf-skip" href="#sf-main"><?= $kind === 'shop' ? 'Skip to the shop' : 'Skip to the page' ?></a>

    <?php
    if (!$private && $sections['announcement']['on'] && ($sections['announcement']['text'] !== '' || !$is_live)) {
        include __DIR__ . '/sections/announcement.php';
    }
    include __DIR__ . '/parts/header.php';
    ?>

    <main id="sf-main">
        <?php
        if ($kind === 'shop') {
            foreach ($settings['sections'] as $section) {
                if (!$section['on'] || in_array($section['type'], ['announcement', 'contact'], true)) {
                    continue;
                }
                include __DIR__ . '/sections/' . $section['type'] . '.php';
            }
        } else {
            include __DIR__ . '/pages/' . $kind . '.php';
        }
        ?>
    </main>

    <?php include __DIR__ . '/sections/contact.php'; ?>
    <?php include __DIR__ . '/parts/bag.php'; ?>

    <script type="application/json" id="sf-data"><?= json_encode($js, $json_flags) ?></script>
    <script src="<?= e($asset('assets/js/storefront.js')) ?>" defer></script>
    <script src="<?= e($asset('assets/js/wa.js')) ?>" defer></script>
</body>
</html>
