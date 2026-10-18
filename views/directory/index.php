<?php
/**
 * Nfelia — the shop directory: nfelia.com/shops, /shops/{city},
 * /shops/{kind} and /shops/{city}/{kind}
 *
 * Paid shops whose owners chose to be listed (Shop Studio → More → Found on
 * Google), by city and by what they sell, each linking to its shop. It runs
 * from index.php before any session starts, so it sets no cookie.
 * $m holds the path's two parts.
 */
$first  = $m[1] ?? '';
$second = $m[2] ?? '';
$cats   = directory_categories();
$cat_by_slug = [];
foreach ($cats as $key => $cat) {
    $cat_by_slug[$cat['slug']] = $key;
}

$all = seo_live_shops(get_db(), true);
$cities = [];
foreach ($all as $shop) {
    if ($shop['city_slug']) {
        $cities[$shop['city_slug']] ??= ['name' => $shop['city'], 'count' => 0];
        $cities[$shop['city_slug']]['count']++;
    }
}
ksort($cities);

// /shops/{city}, /shops/{kind} or /shops/{city}/{kind}
$city_slug = $cat_slug = '';
if ($first !== '' && $second === '' && isset($cat_by_slug[$first]) && !isset($cities[$first])) {
    $cat_slug = $first;
} else {
    $city_slug = $first;
    $cat_slug  = $second;
}
$known = ($city_slug === '' || isset($cities[$city_slug])) && ($cat_slug === '' || isset($cat_by_slug[$cat_slug]));
$shops = $known ? array_values(array_filter($all, fn($s) =>
    ($city_slug === '' || $s['city_slug'] === $city_slug) && ($cat_slug === '' || directory_category_slug((string)$s['category']) === $cat_slug))) : [];

$city_name = $city_slug !== '' && isset($cities[$city_slug]) ? $cities[$city_slug]['name'] : '';
$cat_name  = $cat_slug !== '' && isset($cat_by_slug[$cat_slug]) ? $cats[$cat_by_slug[$cat_slug]]['name'] : '';
$heading = match (true) {
    $city_name !== '' && $cat_name !== '' => $cat_name . ' in ' . $city_name,
    $city_name !== ''                     => 'Shops in ' . $city_name,
    $cat_name !== ''                      => $cat_name,
    default                               => 'Shops on Nfelia',
};
$path      = '/shops' . ($city_slug !== '' ? '/' . $city_slug : '') . ($cat_slug !== '' ? '/' . $cat_slug : '');
$canonical = APP_URL . $path;
$desc      = $shops
    ? count($shops) . ' Nigerian shop' . (count($shops) === 1 ? '' : 's') . ($city_name !== '' ? ' in ' . $city_name : '') . ($cat_name !== '' ? ' for ' . strtolower($cat_name) : '') . ' you can order from online or on WhatsApp.'
    : 'Shops you can order from online or on WhatsApp.';

// What's in this city, by kind (for the chips)
$kind_counts = [];
foreach ($all as $shop) {
    if ($city_slug === '' || $shop['city_slug'] === $city_slug) {
        $slug = directory_category_slug((string)$shop['category']);
        $kind_counts[$slug] = ($kind_counts[$slug] ?? 0) + 1;
    }
}

http_response_code($known ? 200 : 404);
header('Cache-Control: public, max-age=0, s-maxage=600');
if (!$shops) {
    header('X-Robots-Tag: noindex, follow');
}
$json_flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
$ld = [
    '@context' => 'https://schema.org',
    '@type'    => 'ItemList',
    'name'     => $heading,
    'url'      => $canonical,
    'itemListElement' => array_map(fn($s, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'url' => shop_url($s['subdomain']), 'name' => $s['name']], $shops, array_keys($shops)),
];
$chip = fn(string $href, string $label, bool $on) => '<a class="chip' . ($on ? ' is-on' : '') . '" href="' . e($href) . '"' . ($on ? ' aria-current="page"' : '') . '>' . e($label) . '</a>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?= errors_script_tag() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($heading) ?> | Nfelia</title>
    <meta name="description" content="<?= e($desc) ?>">
    <meta name="robots" content="<?= $shops ? 'index, follow' : 'noindex, follow' ?>">
    <link rel="canonical" href="<?= e($canonical) ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e($heading) ?>">
    <meta property="og:description" content="<?= e($desc) ?>">
    <meta property="og:url" content="<?= e($canonical) ?>">
    <meta property="og:image" content="<?= e(APP_URL) ?>/android-chrome-512x512.png">
    <link rel="icon" href="<?= e(APP_URL) ?>/favicon-32x32.png" sizes="32x32">
    <?php if ($shops): ?>
    <script type="application/ld+json"><?= json_encode($ld, $json_flags) ?></script>
    <?php endif; ?>
    <style>
        :root { --ink: #1B0A11; --muted: #6B5A61; --brand: #8B004A; --line: #E8E4DA; --cream: #F7F4EE; --card: #FFFFFF; }
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; background: var(--cream); color: var(--ink); font: 16px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; -webkit-font-smoothing: antialiased; }
        a { color: inherit; }
        .wrap { max-width: 1120px; margin: 0 auto; padding: 0 16px; }
        header { border-bottom: 1px solid var(--line); background: #fff; }
        header .wrap { display: flex; align-items: center; justify-content: space-between; min-height: 60px; gap: 12px; }
        .brand { font-weight: 800; letter-spacing: 0.08em; color: var(--brand); text-decoration: none; font-size: 18px; }
        .sell { font-size: 14px; font-weight: 700; color: var(--brand); }
        h1 { margin: 28px 0 6px; font-size: 32px; line-height: 1.15; letter-spacing: -0.01em; text-wrap: balance; }
        .lead { margin: 0 0 18px; color: var(--muted); }
        .chips { display: flex; gap: 8px; overflow-x: auto; padding: 2px 0 10px; scrollbar-width: none; }
        .chips::-webkit-scrollbar { display: none; }
        .chip { flex-shrink: 0; display: inline-flex; align-items: center; min-height: 40px; padding: 0 14px; border: 1.5px solid var(--line); border-radius: 999px; background: #fff; font-size: 14px; font-weight: 600; text-decoration: none; white-space: nowrap; }
        .chip.is-on { border-color: var(--ink); background: var(--ink); color: #fff; }
        .label { margin: 10px 0 6px; font-size: 13px; font-weight: 700; color: var(--muted); }
        .grid { list-style: none; margin: 18px 0 40px; padding: 0; display: grid; gap: 14px; }
        .card { display: flex; flex-direction: column; overflow: hidden; border: 1px solid var(--line); border-radius: 16px; background: var(--card); }
        .card__band { height: 8px; }
        .card__body { display: flex; gap: 14px; padding: 16px; align-items: flex-start; }
        .card__mark { flex-shrink: 0; width: 56px; height: 56px; border-radius: 12px; display: flex; align-items: center; justify-content: center; overflow: hidden; font-weight: 800; font-size: 20px; }
        .card__mark img, .card__mark picture { width: 100%; height: 100%; object-fit: contain; background: #fff; }
        .card__name { margin: 0; font-size: 18px; line-height: 1.25; }
        .card__name a { text-decoration: none; }
        .card__name a::after { content: ""; position: absolute; inset: 0; }
        .card { position: relative; }
        .card__line { margin: 4px 0 0; color: var(--muted); font-size: 15px; }
        .card__meta { margin: 6px 0 0; font-size: 13px; font-weight: 600; color: var(--muted); }
        .empty { padding: 28px; border: 2px dashed var(--line); border-radius: 16px; text-align: center; color: var(--muted); margin: 18px 0 40px; }
        footer { border-top: 1px solid var(--line); padding: 24px 0 40px; color: var(--muted); font-size: 14px; }
        a:focus-visible { outline: 2px solid var(--brand); outline-offset: 2px; }
        @media (min-width: 640px) { .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } h1 { font-size: 40px; } }
        @media (min-width: 980px) { .grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    </style>
</head>
<body>
    <header>
        <div class="wrap">
            <a class="brand" href="<?= e(APP_URL) ?>/">NFELIA</a>
            <a class="sell" href="<?= e(APP_URL) ?>/register">Open your own shop</a>
        </div>
    </header>
    <main class="wrap">
        <h1><?= e($heading) ?></h1>
        <p class="lead"><?= $known ? e($desc) : 'There are no shops listed here yet.' ?></p>

        <?php if ($cities): ?>
            <p class="label">City</p>
            <nav class="chips" aria-label="Cities">
                <?= $chip('/shops' . ($cat_slug !== '' && $city_slug === '' ? '/' . $cat_slug : ''), 'All cities', $city_slug === '') ?>
                <?php foreach ($cities as $slug => $city): ?>
                    <?= $chip('/shops/' . $slug, $city['name'] . ' (' . $city['count'] . ')', $slug === $city_slug) ?>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
        <?php if (count($kind_counts) > 1 || $cat_slug !== ''): ?>
            <p class="label">What they sell</p>
            <nav class="chips" aria-label="Kinds of shop">
                <?= $chip($city_slug !== '' ? '/shops/' . $city_slug : '/shops', 'Everything', $cat_slug === '') ?>
                <?php foreach ($cats as $key => $cat): if (empty($kind_counts[$cat['slug']])) { continue; } ?>
                    <?= $chip(($city_slug !== '' ? '/shops/' . $city_slug : '/shops') . '/' . $cat['slug'], $cat['name'], $cat['slug'] === $cat_slug) ?>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>

        <?php if ($shops): ?>
            <ul class="grid">
                <?php foreach ($shops as $shop):
                    $look = STOREFRONT_LOOKS[$shop['storefront_look'] ?? ''] ?? STOREFRONT_LOOKS['atelier'];
                    $logo = (string)($shop['logo_path'] ?? '');
                    $logo_img = $logo !== '' && !str_ends_with($logo, '.svg') ? sf_img($logo, '56px', '', '') : '';
                ?>
                    <li class="card">
                        <span class="card__band" style="background: <?= e($look['accent']) ?>" aria-hidden="true"></span>
                        <div class="card__body">
                            <span class="card__mark" style="background: <?= e($look['ground']) ?>; color: <?= e($look['accent']) ?>" aria-hidden="true">
                                <?= $logo_img !== '' ? $logo_img : e(sf_initials((string)$shop['name'])) ?>
                            </span>
                            <div>
                                <h2 class="card__name"><a href="<?= e(shop_url($shop['subdomain'])) ?>"><?= e($shop['name']) ?></a></h2>
                                <?php if (trim((string)$shop['tagline']) !== ''): ?>
                                    <p class="card__line"><?= e($shop['tagline']) ?></p>
                                <?php endif; ?>
                                <p class="card__meta"><?= e($cats[$shop['category']]['name'] ?? $cats['other']['name']) ?><?= $shop['city'] ? ' · ' . e($shop['city']) : '' ?></p>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="empty">
                <p>No shops listed here yet.</p>
                <p><a href="/shops">See every shop</a></p>
            </div>
        <?php endif; ?>
    </main>
    <footer>
        <div class="wrap">Shops listed here chose to be. Run a shop? <a href="<?= e(APP_URL) ?>/register">Start free on Nfelia</a> and get your own link, like yourshop.<?= e(shop_base_host()) ?>.</div>
    </footer>
</body>
</html>
