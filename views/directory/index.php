<?php
/**
 * Nfelia — the shop directory: nfelia.com/shops, /shops/{city},
 * /shops/{kind} and /shops/{city}/{kind}
 *
 * Paid shops whose owners chose to be listed (Shop Studio → More → Found on
 * Google), by city and by what they sell, each linking to its shop. A page of
 * the public site: the same header, footer and breadcrumbs as the others, a
 * title and description of its own for every city and kind, and a schema
 * graph (CollectionPage, the list of shops, breadcrumbs) joined to Nfelia's.
 * A city or kind with no shops in it, or too few to stand on their own
 * (directory_indexable), is served but kept out of search.
 *
 * It runs from index.php before any session starts, so it sets no cookie.
 * $m holds the path's two parts.
 */
require_once BASE_PATH . '/views/home/_icons.php';

$first  = $m[1] ?? '';
$second = $m[2] ?? '';
$cats   = directory_categories();
$cat_by_slug = [];
foreach ($cats as $key => $cat) {
    $cat_by_slug[$cat['slug']] = $key;
}

// What the shops of a kind are called in a title or a sentence ("Hair and
// beauty shops in Lagos"), by the kind's slug
$kind_words = [
    'fashion'             => 'Fashion designers and tailors',
    'hair-and-beauty'     => 'Hair and beauty shops',
    'food'                => 'Food and drinks sellers',
    'phones-and-gadgets'  => 'Phone and gadget shops',
    'services'            => 'Home and business services',
    'wholesale'           => 'Wholesale and provisions shops',
    'distribution'        => 'Distributors',
    'events-and-creative' => 'Events and creative businesses',
    'more-shops'          => 'Farms, stays and more',
];

$all = seo_live_shops(get_db(), true);
$cities = [];
foreach ($all as $shop) {
    if (directory_city_listed($shop)) {   // cities in the lists of the shops' own countries only (W17)
        $cities[$shop['city_slug']] ??= ['name' => $shop['city'], 'iso' => (string)$shop['country_iso'], 'count' => 0];
        $cities[$shop['city_slug']]['count']++;
    }
}
// By country in the site's order (Nigeria's first, then Ghana's, Kenya's and
// Cameroon's), each country's in alphabetical order
$country_order = array_flip(array_keys(site_countries()));
uksort($cities, fn($a, $b) => [$country_order[$cities[$a]['iso']] ?? 99, $a] <=> [$country_order[$cities[$b]['iso']] ?? 99, $b]);

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
    ($city_slug === '' || ($s['city_slug'] === $city_slug && directory_city_listed($s))) && ($cat_slug === '' || directory_category_slug((string)$s['category']) === $cat_slug))) : [];

// Where the shops on this page are: a city page is in its city's country
// (each country has its own cities); the full list and the kinds say the
// countries their shops are in, so a page never calls a Ghanaian shop
// Nigerian
$city_iso = $city_slug !== '' && isset($cities[$city_slug]) ? $cities[$city_slug]['iso'] : '';
$isos = $city_slug !== '' ? [$city_iso ?: 'NG'] : array_values(array_unique(array_map(fn($s) => (string)($s['country_iso'] ?? 'NG'), $shops ?: $all)));
$isos = array_values(array_intersect(array_keys(site_countries()), $isos)) ?: ['NG'];   // in the site's own order, Nigeria first
$names = array_map(fn($iso) => site_country($iso)['name'], $isos);
$where = count($names) > 1 ? implode(', ', array_slice($names, 0, -1)) . ' and ' . end($names) : $names[0];
$one_country = count($isos) === 1 ? site_country($isos[0]) : null;
$lang = $one_country ? $one_country['hreflang'] : 'en';

// Offered to search engines only when the page is not thin (directory_indexable)
$indexable = $known && directory_indexable('/shops' . ($city_slug !== '' ? '/' . $city_slug : '') . ($cat_slug !== '' ? '/' . $cat_slug : ''), directory_pages($all));

$city_name = $city_slug !== '' && isset($cities[$city_slug]) ? $cities[$city_slug]['name'] : '';
$cat_name  = $cat_slug !== '' && isset($cat_by_slug[$cat_slug]) ? $cats[$cat_by_slug[$cat_slug]]['name'] : '';
$kind      = $cat_name !== '' ? ($kind_words[$cat_slug] ?? $cat_name) : '';
// The city with its country in the title and description ("Accra, Ghana"),
// as people search for it; Nigeria's cities stand alone, as they always have
$place     = $city_name !== '' && $city_iso !== 'NG' ? $city_name . ', ' . site_country($city_iso)['name'] : $city_name;

// The h1, the title (main words first) and what the shops are called in the description
[$heading, $title, $subject] = match (true) {
    !$known                               => ['No shops listed here yet', 'No shops listed here yet | Nfelia', ''],
    $city_name !== '' && $cat_name !== '' => [$cat_name . ' in ' . $city_name, $kind . ' in ' . $place . ' | Nfelia', $kind . ' in ' . $place],
    $city_name !== ''                     => ['Shops in ' . $city_name, 'Shops in ' . $place . ' you can order from online | Nfelia', 'Shops in ' . $place],
    $cat_name !== ''                      => [$kind . ' on Nfelia', $kind . ' in ' . $where . ' | Nfelia', $kind . ' across ' . $where],
    default                               => ['Shops on Nfelia', ($one_country ? $one_country['people'] . ' shops' : 'Shops') . ' you can order from online | Nfelia', 'Shops across ' . $where],
};

// The description: about 140 to 160 characters, the first wording that fits
$desc = 'There are no shops listed here yet. See every shop on Nfelia, by city and by what they sell.';
if ($known && $shops) {
    $one     = count($shops) === 1;
    $listed  = $one ? 'one listed' : count($shops) . ' listed';
    $own     = $one ? 'with its own website' : 'each with its own website';
    $see     = $one ? 'See what it sells, order online or on WhatsApp, and pay the shop directly.'
                    : 'See what they sell, order online or on WhatsApp, and pay the shop directly.';
    $order   = 'Order online or on WhatsApp and pay the shop directly.';
    $chose   = $one ? 'The shop chose to be listed.' : 'Every shop here chose to be listed.';
    $choices = [
        "{$subject} on Nfelia: {$listed}, {$own}. {$see}",
        "{$subject} on Nfelia: {$listed}, {$own}. {$order} {$chose}",
        "{$subject} on Nfelia: {$listed}, {$own}. {$see} {$chose}",
        "{$subject}: {$listed} on Nfelia. {$see}",
        "{$subject}: {$listed} on Nfelia, {$own}. {$order}",
    ];
    $fits = array_values(array_filter($choices, fn($d) => mb_strlen($d) >= 140 && mb_strlen($d) <= 160));
    $short = array_values(array_filter($choices, fn($d) => mb_strlen($d) <= 160));
    usort($short, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
    $desc = $fits[0] ?? $short[0] ?? $choices[3];
} elseif ($known) {
    $desc = "{$subject}: none listed on Nfelia yet. See the other shops on Nfelia, by city and by what they sell.";
}

$path      = '/shops' . ($city_slug !== '' ? '/' . $city_slug : '') . ($cat_slug !== '' ? '/' . $cat_slug : '');
$canonical = APP_URL . $path;
$og_image  = APP_URL . '/assets/img/nfelia-og-banner.png';

// Home first, down to this page: [[name, url], ...]
$trail = [['Nfelia', APP_URL . '/'], ['Shops', APP_URL . '/shops']];
if (!$known) {
    $trail[] = ['Not found', $canonical];
} else {
    if ($city_name !== '') {
        $trail[] = [$city_name, APP_URL . '/shops/' . $city_slug];
    }
    if ($cat_name !== '') {
        $trail[] = [$cat_name, $canonical];
    }
}

// What's in this city, by kind (for the chips)
$kind_counts = [];
foreach ($all as $shop) {
    if ($city_slug === '' || ($shop['city_slug'] === $city_slug && directory_city_listed($shop))) {
        $slug = directory_category_slug((string)$shop['category']);
        $kind_counts[$slug] = ($kind_counts[$slug] ?? 0) + 1;
    }
}

http_response_code($known ? 200 : 404);
header('Cache-Control: public, max-age=0, s-maxage=600');
header('Content-Language: ' . $lang);
if (!$indexable) {
    header('X-Robots-Tag: noindex, follow');
}

// The schema graph: this page (a CollectionPage of nfelia.com), its shops
// and its breadcrumbs, pointing at the Nfelia and website nodes every page
// of the site shares
$graph = [array_filter([
    '@type'       => 'CollectionPage',
    '@id'         => $canonical . '#webpage',
    'url'         => $canonical,
    'name'        => $title,
    'description' => $desc,
    'isPartOf'    => ['@id' => APP_URL . '/#website'],
    'publisher'   => ['@id' => APP_URL . '/#organization'],
    'inLanguage'  => $lang,
    'primaryImageOfPage' => ['@type' => 'ImageObject', 'url' => $og_image],
    'breadcrumb'  => ['@id' => $canonical . '#breadcrumb'],
    'mainEntity'  => $shops ? ['@id' => $canonical . '#shops'] : null,
    'spatialCoverage' => $city_name !== '' ? ['@type' => 'City', 'name' => $city_name, 'containedInPlace' => ['@type' => 'Country', 'name' => site_country($city_iso)['name']]] : null,
])];
if ($shops) {
    $graph[] = [
        '@type'           => 'ItemList',
        '@id'             => $canonical . '#shops',
        'name'            => $heading,
        'numberOfItems'   => count($shops),
        'itemListOrder'   => 'https://schema.org/ItemListOrderAscending',
        'itemListElement' => array_map(fn($s, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'url' => shop_url($s['subdomain']), 'name' => $s['name']], $shops, array_keys($shops)),
    ];
}
$graph[] = [
    '@type' => 'BreadcrumbList',
    '@id'   => $canonical . '#breadcrumb',
    'itemListElement' => array_map(fn($i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $trail[$i][0], 'item' => $trail[$i][1]], array_keys($trail)),
];
$ld = ['@context' => 'https://schema.org', '@graph' => $graph];

$chip = fn(string $href, string $label, bool $on) => '<a class="chip' . ($on ? ' is-on' : '') . '" href="' . e($href) . '"' . ($on ? ' aria-current="page"' : '') . '>' . e($label) . '</a>';
$shops_url = APP_URL . '/shops';
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>">
<head><meta charset="utf-8">
    <?= errors_script_tag() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?></title>
    <meta name="description" content="<?= e($desc) ?>">
    <meta name="robots" content="<?= $indexable ? 'index, follow, max-image-preview:large, max-snippet:-1' : 'noindex, follow' ?>">
    <link rel="canonical" href="<?= e($canonical) ?>">
    <meta name="theme-color" content="#8B004A">

    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= e($canonical) ?>">
    <meta property="og:title" content="<?= e($heading) ?>">
    <meta property="og:description" content="<?= e($desc) ?>">
    <meta property="og:image" content="<?= e($og_image) ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:site_name" content="Nfelia">
<?php if ($one_country): ?>
    <meta property="og:locale" content="<?= e($one_country['locale']) ?>">
<?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($heading) ?>">
    <meta name="twitter:description" content="<?= e($desc) ?>">
    <meta name="twitter:image" content="<?= e($og_image) ?>">

<?php require BASE_PATH . '/views/partials/pwa_head.php'; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,700&family=Caveat:wght@600&family=Plus+Jakarta+Sans:wght@400;600&display=swap">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/home.css?v=<?= (int)@filemtime(BASE_PATH . '/assets/css/home.css') ?>">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/site.css?v=<?= (int)@filemtime(BASE_PATH . '/assets/css/site.css') ?>">
    <link rel="alternate" type="text/plain" title="Nfelia for AI assistants" href="<?= APP_URL ?>/llms.txt">

    <script type="application/ld+json"><?= json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
    <style>
        /* The directory's own pieces: the chips that filter it and the shop cards.
           Everything else comes from home.css and site.css. */
        .dir { padding-bottom: 56px; }
        .dir-label { margin: 16px 0 8px; font-size: .8125rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--ink-2); }
        .chips { display: flex; gap: 8px; overflow-x: auto; padding: 2px 0 8px; scrollbar-width: none; }
        .chips::-webkit-scrollbar { display: none; }
        .chip { flex-shrink: 0; display: inline-flex; align-items: center; min-height: 44px; padding: 0 16px; border: 1px solid var(--line); border-radius: 999px; background: #fff; font-size: .875rem; font-weight: 600; text-decoration: none; white-space: nowrap; color: var(--ink); transition: border-color var(--motion-150) ease; }
        .chip:hover { border-color: var(--burgundy); }
        .chip.is-on { border-color: var(--ink); background: var(--ink); color: #fff; }
        .dir-grid { list-style: none; margin: 24px 0 0; padding: 0; display: grid; gap: 16px; }
        .dir-card { position: relative; display: flex; flex-direction: column; overflow: hidden; border: 1px solid var(--line); border-radius: var(--radius-m); background: #fff; transition: border-color var(--motion-150) ease; }
        .dir-card:hover { border-color: var(--burgundy); }
        .dir-card__band { height: 8px; }
        .dir-card__body { display: flex; gap: 16px; padding: 16px; align-items: flex-start; }
        .dir-card__mark { flex-shrink: 0; width: 56px; height: 56px; border-radius: var(--radius-m); display: flex; align-items: center; justify-content: center; overflow: hidden; font-weight: 700; font-size: 1.25rem; }
        .dir-card__mark img, .dir-card__mark picture { width: 100%; height: 100%; object-fit: contain; background: #fff; }
        .dir-card__name { margin: 0; font-size: 1.125rem; line-height: 1.25; }
        .dir-card__name a { text-decoration: none; color: var(--ink); }
        .dir-card__name a::after { content: ""; position: absolute; inset: 0; }
        .dir-card__line { margin: 4px 0 0; color: var(--ink-2); font-size: .9375rem; }
        .dir-card__meta { margin: 8px 0 0; font-size: .8125rem; font-weight: 600; color: var(--ink-2); }
        .dir-empty { margin: 24px 0 0; padding: 32px 24px; border: 2px dashed var(--line); border-radius: var(--radius-m); text-align: center; color: var(--ink-2); }
        .dir-empty a { color: var(--burgundy); font-weight: 600; }
        .dir-note { margin-top: 24px; font-size: .875rem; color: var(--ink-2); }
        @media (min-width: 640px) { .dir-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (min-width: 980px) { .dir-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        @media (prefers-reduced-motion: reduce) { .chip, .dir-card { transition: none; } }
    </style>
</head>
<body class="site-page site-shops">
<a class="skip-link" href="#main">Skip to content</a>

<?php require BASE_PATH . '/views/site/_header.php'; ?>

<main id="main">
<nav class="crumbs" aria-label="Breadcrumb">
    <ol class="wrap">
        <?php foreach ($trail as $i => [$name, $href]): ?>
        <li><?php if ($i < count($trail) - 1): ?><a href="<?= e($href) ?>"><?= e($name) ?></a><?php else: ?><span aria-current="page"><?= e($name) ?></span><?php endif; ?></li>
        <?php endforeach; ?>
    </ol>
</nav>

<section class="page-hero is-plain">
    <div class="wrap page-hero-grid">
        <div class="page-hero-copy">
            <p class="kicker">Shop directory</p>
            <h1><?= e($heading) ?></h1>
            <p class="page-lead"><?= e($desc) ?></p>
        </div>
    </div>
</section>

<div class="wrap dir">
    <?php if ($cities): ?>
        <p class="dir-label" id="dir-cities">City</p>
        <nav class="chips" aria-labelledby="dir-cities">
            <?= $chip($shops_url . ($cat_slug !== '' && $city_slug === '' ? '/' . $cat_slug : ''), 'All cities', $city_slug === '') ?>
            <?php foreach ($cities as $slug => $city): ?>
                <?= $chip($shops_url . '/' . $slug, $city['name'] . ' (' . $city['count'] . ')', $slug === $city_slug) ?>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>
    <?php if (count($kind_counts) > 1 || $cat_slug !== ''): ?>
        <p class="dir-label" id="dir-kinds">What they sell</p>
        <nav class="chips" aria-labelledby="dir-kinds">
            <?= $chip($city_slug !== '' ? $shops_url . '/' . $city_slug : $shops_url, 'Everything', $cat_slug === '') ?>
            <?php foreach ($cats as $key => $cat): if (empty($kind_counts[$cat['slug']])) { continue; } ?>
                <?= $chip(($city_slug !== '' ? $shops_url . '/' . $city_slug : $shops_url) . '/' . $cat['slug'], $cat['name'], $cat['slug'] === $cat_slug) ?>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <?php if ($shops): ?>
        <ul class="dir-grid">
            <?php foreach ($shops as $shop):
                $look = STOREFRONT_LOOKS[$shop['storefront_look'] ?? ''] ?? STOREFRONT_LOOKS['atelier'];
                $logo = (string)($shop['logo_path'] ?? '');
                $logo_img = $logo !== '' && !str_ends_with($logo, '.svg') ? sf_img($logo, '56px', '', '') : '';
            ?>
                <li class="dir-card">
                    <span class="dir-card__band" style="background: <?= e($look['accent']) ?>" aria-hidden="true"></span>
                    <div class="dir-card__body">
                        <span class="dir-card__mark" style="background: <?= e($look['ground']) ?>; color: <?= e($look['accent']) ?>" aria-hidden="true">
                            <?= $logo_img !== '' ? $logo_img : e(sf_initials((string)$shop['name'])) ?>
                        </span>
                        <div>
                            <h2 class="dir-card__name"><a href="<?= e(shop_url($shop['subdomain'])) ?>"><?= e($shop['name']) ?></a></h2>
                            <?php if (trim((string)$shop['tagline']) !== ''): ?>
                                <p class="dir-card__line"><?= e($shop['tagline']) ?></p>
                            <?php endif; ?>
                            <p class="dir-card__meta"><?= e($cats[$shop['category']]['name'] ?? $cats['other']['name']) ?><?= directory_city_listed($shop) ? ' · ' . e($shop['city']) : '' ?></p>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="dir-note">Every shop here runs on Nfelia and chose to be listed.</p>
    <?php else: ?>
        <div class="dir-empty">
            <p>No shops listed here yet.</p>
            <p><a href="<?= e($shops_url) ?>">See every shop</a></p>
        </div>
    <?php endif; ?>
</div>

<?= site_cta('Get a website like these for your shop', 'Your own website, like yourshop.' . shop_base_host() . ', with every price on it. Each order lands in Nfelia, with the customer saved. No card needed.') ?>
</main>

<?php require BASE_PATH . '/views/site/_footer.php'; ?>

<script src="<?= APP_URL ?>/assets/js/site.js?v=<?= (int)@filemtime(BASE_PATH . '/assets/js/site.js') ?>" defer></script>
</body>
</html>
