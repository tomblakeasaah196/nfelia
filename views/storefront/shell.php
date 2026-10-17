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
 * (storefront_catalogue), $mode ('live', 'preview' or 'look') and
 * $visibility ('live' or 'unlisted').
 *
 * Orders and quote requests post to the shop's own address (/checkout and
 * /quote-request); previews never send them.
 */
require_once __DIR__ . '/parts/helpers.php';

$look      = $theme['def'];
$labels    = $look['labels'];
$currency  = trim((string)($business['currency_symbol'] ?? '')) ?: '₦';
$wa        = shop_whatsapp_digits($business);
$is_live   = $mode === 'live';
$shop_url  = $business['subdomain'] ? shop_url($business['subdomain']) : APP_URL;
$indexable = $is_live && ($visibility ?? 'live') === 'live';
$json_flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

$sections  = [];
foreach ($settings['sections'] as $section) {
    $sections[$section['type']] = $section;
}
$hero      = $sections['hero'];
$quote_on  = $sections['quote']['on'];
$contact   = $sections['contact'];

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
$og_image = $business['logo_path'] && !str_ends_with((string)$business['logo_path'], '.svg')
    ? sf_media_url((string)$business['logo_path'])
    : APP_URL . '/og-image.php?biz=' . (int)$business['id'];

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
if ($business['phone'])           { $ld['telephone'] = (string)$business['phone']; }
if ($business['email'])           { $ld['email'] = (string)$business['email']; }
if ($business['address'])         { $ld['address'] = ['@type' => 'PostalAddress', 'streetAddress' => (string)$business['address'], 'addressCountry' => 'NG']; }
if ($socials)                     { $ld['sameAs'] = array_values($socials); }
if ($catalogue['items']) {
    $ld['hasOfferCatalog'] = [
        '@type' => 'OfferCatalog',
        'name'  => 'Products and services',
        'numberOfItems' => count($catalogue['items']),
        'itemListElement' => array_map(fn($item) => [
            '@type'    => $item['is_service'] ? 'Service' : 'Product',
            'name'     => $item['name'],
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
        'name'     => $item['name'],
        'desc'     => $item['description'],
        'price'    => $item['price'],
        'unit'     => $item['unit'],
        'cat'      => $item['category'],
        'svc'      => $item['is_service'],
        'img'      => $images,
        'variants' => $item['variants'],
    ];
}
$js = [
    'shop'     => $business['subdomain'] ?: 'preview-' . (int)$business['id'],
    'mode'     => $mode,
    'name'     => (string)$business['name'],
    'currency' => $currency,
    'wa'       => $wa,
    'look'     => $settings['look'],
    'items'    => (object)$js_items,
    'banks'    => array_map(fn($b) => ['id' => (int)$b['id'], 'bank' => $b['bank_name'], 'name' => $b['account_name'], 'number' => $b['account_number']], $catalogue['banks']),
    'delivery' => $sections['delivery']['on'] ? $sections['delivery']['text'] : '',
    'urls'     => ['checkout' => '/checkout', 'quote' => '/quote-request'],
];

$asset = fn(string $path) => APP_URL . '/' . $path . '?v=' . (@filemtime(BASE_PATH . '/' . $path) ?: '1');
?>
<!DOCTYPE html>
<html lang="en" class="<?= e($theme['classes']) ?>" prefix="og: https://ogp.me/ns#">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e($seo_title) ?></title>
    <meta name="description" content="<?= e($seo_desc) ?>">
    <meta name="robots" content="<?= $indexable ? 'index, follow' : 'noindex, nofollow' ?>">
    <meta name="theme-color" content="<?= e($theme['palette']['ground']) ?>">
    <link rel="canonical" href="<?= e($shop_url) ?>">

    <meta property="og:type" content="business.business">
    <meta property="og:site_name" content="<?= e($business['name']) ?>">
    <meta property="og:title" content="<?= e($seo_title) ?>">
    <meta property="og:description" content="<?= e($seo_desc) ?>">
    <meta property="og:image" content="<?= e($og_image) ?>">
    <meta property="og:url" content="<?= e($shop_url) ?>">
    <meta property="og:locale" content="en_NG">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($seo_title) ?>">
    <meta name="twitter:description" content="<?= e($seo_desc) ?>">
    <meta name="twitter:image" content="<?= e($og_image) ?>">
    <?php if ($contact['twitter']): ?>
    <meta name="twitter:site" content="@<?= e($contact['twitter']) ?>">
    <?php endif; ?>

    <script type="application/ld+json"><?= json_encode($ld, $json_flags) ?></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="<?= e($theme['fonts_url']) ?>" id="sf-fonts">
    <link rel="stylesheet" href="<?= e($asset('assets/css/storefront.css')) ?>">
    <style id="sf-vars"><?= storefront_css_vars($theme['vars']) ?></style>
</head>
<body>
    <a class="sf-skip" href="#sf-main">Skip to the shop</a>

    <?php
    if ($sections['announcement']['on'] && ($sections['announcement']['text'] !== '' || !$is_live)) {
        include __DIR__ . '/sections/announcement.php';
    }
    include __DIR__ . '/parts/header.php';
    ?>

    <main id="sf-main">
        <?php
        foreach ($settings['sections'] as $section) {
            if (!$section['on'] || in_array($section['type'], ['announcement', 'contact'], true)) {
                continue;
            }
            include __DIR__ . '/sections/' . $section['type'] . '.php';
        }
        ?>
    </main>

    <?php include __DIR__ . '/sections/contact.php'; ?>
    <?php include __DIR__ . '/parts/bag.php'; ?>

    <script type="application/json" id="sf-data"><?= json_encode($js, $json_flags) ?></script>
    <script src="<?= e($asset('assets/js/storefront.js')) ?>" defer></script>
</body>
</html>
