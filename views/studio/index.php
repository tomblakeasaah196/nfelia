<?php
/**
 * Nfelia — Shop Studio (a standalone page, outside the app layout, so the
 * preview can have the whole screen)
 *
 * Phone first: the preview on top and a sheet of controls below it; on a
 * computer, the controls on the left and the preview on the right. The
 * lists in the tabs are drawn by assets/js/studio.js from #st-data; the
 * shop-link card is drawn here because it is an ordinary form.
 *
 * Available from LandingPageController::settings(): $business, $draft,
 * $published, $products (those on the shop), $all_products (every one on
 * sale, with on_shop), $theme, $public_url, $shop_host, $pending_name,
 * $can_rename, $unpublished.
 */
$json_flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
$asset = fn(string $path) => APP_URL . '/' . $path . '?v=' . (@filemtime(BASE_PATH . '/' . $path) ?: '1');
$icon  = fn(string $name, int $size = 22) => sf_icon($name, $size);

$looks = [];
foreach (storefront_looks() as $key => $look) {
    $looks[] = ['key' => $key, 'name' => $look['name'], 'kind' => $look['kind'], 'pair' => $look['pair'],
                'colours' => $look['colours'], 'band' => $look['band'], 'shape' => $look['shape'], 'labels' => $look['labels'],
                'thumb' => STOREFRONT_LOOKS[$key]['shape'], 'slides' => $look['slides'], 'backdrop' => $look['backdrop'], 'hero' => $look['hero']];
}
$fonts = [];
foreach (storefront_fonts() as $key => $font) {
    $fonts[$key] = array_intersect_key($font, array_flip(['name', 'head_stack', 'body_stack', 'weight', 'stretch', 'scale']))
                 + ['url' => storefront_fonts_url($key)];
}
$types = [];
foreach (storefront_section_types() as $key => $type) {
    if ($type['available']) {
        $types[$key] = $type;
    }
}
// The brand from Settings, which leads: its fonts as a pair and its colours
// as a light and a dark set (app/helpers/brand.php)
$brand_pair    = brand_font_pair($business);
$brand_colours = brand_colours($business);
$brand = [
    'fonts'   => $brand_pair ? array_intersect_key($brand_pair, array_flip(['name', 'head', 'body', 'head_stack', 'body_stack', 'weight', 'stretch', 'scale', 'url'])) : null,
    'colours' => $brand_colours ? ['light' => brand_colour_set($brand_colours, 'light'), 'dark' => brand_colour_set($brand_colours, 'dark')] + $brand_colours : null,
];
// The Studio itself wears the business's colour, as the app does
// (views/layouts/app.php): buttons and selections in the colour, and the
// wine-tinted greys of studio.css turned to its hue.
$chrome = theme_tokens($business['primary_color'] ?? null, $business['secondary_color'] ?? null);
$greys  = array_map(fn($grey) => theme_retint($grey, $chrome['accent']), [
    '--st-ground' => '#FBF8F9', '--st-stage' => '#F1E8EC', '--st-tint' => '#F8EEF3', '--st-line' => '#E9DCE2', '--st-line-soft' => '#F1E6EB',
    '--st-handle' => '#DCCAD3', '--st-field' => '#A8909C', '--st-disabled' => '#B9A9B1', '--st-control' => '#8E7A85']);
// Its links also sit on the stage behind the preview, the darkest of its greys
$chrome['accent_text'] = theme_readable($chrome['accent_text'], [$greys['--st-stage'], $greys['--st-tint']]);

$currency = trim((string)($business['currency_symbol'] ?? '')) ?: '₦';
// Every product on sale: "Everything you sell" ticks each on the shop or off
// it ('shop'); the other lists pick from those on it (studio.js keeps
// D.products in step when a tick changes)
$catalogue_list = [];
foreach ($all_products as $p) {
    $thumb = $p['image_path'] && sf_image_size((string)$p['image_path']) ? sf_media_url((string)$p['image_path']) : '';
    $catalogue_list[] = ['id' => (int)$p['id'], 'name' => $p['name'], 'price' => sf_money((float)$p['sell_price'], $currency), 'amount' => (float)$p['sell_price'],
                         'service' => (bool)$p['is_service'], 'thumb' => $thumb, 'mode' => (string)($p['booking_mode'] ?? 'none'),
                         // Stage's planner: how many guests its words say it holds
                         'guests' => sf_event_guests(['name' => (string)$p['name'], 'description' => (string)($p['description'] ?? '')])['guests'],
                         'shop' => (int)$p['on_shop'] === 1,
                         'category' => trim((string)$p['category']),
                         // Pinned to the top of the shop (Order on your shop): 1 first
                         'pin' => $p['pin_rank'] !== null && (int)$p['on_shop'] === 1 ? (int)$p['pin_rank'] : null];
}
$product_list = array_values(array_filter($catalogue_list, fn($p) => $p['shop']));
// Shea's skin finder: the skin types it offers until the owner sets their
// own, the steps, and our guess at each product's step (storefront_routines)
// (in the shop's order, as storefront_catalogue: the owner's, then by each
// category's first product)
$categories = shop_categories($product_list, shop_category_order($business));
$finder = [
    'skins' => storefront_skins(['categories' => $categories], ['skins' => []]),
    'steps' => array_map(fn($step) => $step['name'], SF_ROUTINE_STEPS),
    'guess' => (object)array_column(array_map(fn($p) => ['id' => (string)$p['id'], 'step' => sf_routine_guess(['name' => (string)$p['name'], 'category' => (string)$p['category'], 'is_service' => (bool)$p['is_service']])], $products), 'step', 'id'),
];
$logo = (string)($business['logo_path'] ?? '');
$logo_url = $logo !== '' && str_starts_with($logo, 'uploads/') && is_file(BASE_PATH . '/' . $logo) ? sf_media_url($logo) : '';

// What Google shows when the owner leaves the search words empty (as shell.php)
$hero_draft    = sf_section($draft, 'hero') ?? [];
$about_draft   = sf_section($draft, 'about') ?? [];
$seo_default_title = (string)$business['name'] . (($hero_draft['headline'] ?? '') !== '' && $hero_draft['headline'] !== $business['name'] ? ' — ' . $hero_draft['headline'] : '');
$seo_default_desc  = trim(preg_replace('/\s+/u', ' ', ($hero_draft['text'] ?? '') ?: (($about_draft['text'] ?? '') ?: (string)($business['tagline'] ?? ''))) ?? '');

// The city picker offers the cities of the business's own country, with its
// biggest as the example (site_countries); in a country with no list the
// City box is free to type in, and the example is left out
$city_iso     = sf_country_iso($business);
$city_choices = sf_cities($city_iso);
$city_example = $city_choices ? (site_countries()[$city_iso]['cities'][0] ?? $city_choices[0]) : '';

$studio = [
    'csrf'        => csrf_token(),
    'seoDefaults' => ['title' => $seo_default_title, 'description' => $seo_default_desc],
    'urls'        => [
        'save'       => APP_URL . '/landing-page/save-draft',
        'publish'    => APP_URL . '/landing-page/publish',
        'versions'   => APP_URL . '/landing-page/versions',
        'restore'    => APP_URL . '/landing-page/restore-version',
        'upload'     => APP_URL . '/landing-page/upload-image',
        'visibility' => APP_URL . '/landing-page/visibility',
        'soldOut'    => APP_URL . '/landing-page/sold-out',
        'onShop'     => APP_URL . '/landing-page/on-shop',
        'pin'        => APP_URL . '/landing-page/pin',
        'catOrder'   => APP_URL . '/landing-page/category-order',
        'preview'    => APP_URL . '/landing-page/preview',
        'check'      => APP_URL . '/shop-setup/check',
        'products'   => APP_URL . '/products/create',
        'productsList' => APP_URL . '/products',
        'settings'   => APP_URL . '/settings?tab=business',
        'payments'   => APP_URL . '/settings?tab=business#payments',
        'bookings'   => APP_URL . '/bookings/settings',
        'posts'      => APP_URL . '/posts',
        'brand'      => APP_URL . '/settings?tab=business#nf-brand-h',
        'find'       => APP_URL . '/landing-page/location-find',
        'link'       => APP_URL . '/landing-page/location-link',
        'leaflet'    => $asset('assets/vendor/leaflet-1.9.4/leaflet.js'),
        'leafletCss' => $asset('assets/vendor/leaflet-1.9.4/leaflet.css'),
    ],
    'brand'       => $brand,
    // The map where owners place their pin: tiles need a key the browser may see
    'maps'        => ['tiles' => maps_browser_key() !== '' ? maps_tiles_url() . '/v1/tile/osm-bright/{z}/{x}/{y}{r}.png?apiKey=' . rawurlencode(maps_browser_key()) : '',
                      'find' => maps_key() !== ''],
    'settings'    => $draft,
    'unpublished' => $unpublished,
    'published'   => $published !== null,
    'theme'       => ['vars' => $theme['vars'], 'dark' => $theme['palette']['dark'], 'pair' => $theme['pair'], 'fonts_url' => $theme['fonts_url'], 'accent' => $theme['palette']['accent']],
    'looks'       => $looks,
    'fonts'       => $fonts,
    'sets'        => storefront_colour_sets(),
    'types'       => $types,
    'products'    => $product_list,
    'catalogue'   => $catalogue_list,
    'finder'      => $finder,
    // Order on your shop: the categories in the shop's order, and how many pins
    'order'       => ['categories' => $categories, 'max' => SHOP_MAX_PINS],
    'themes'      => array_map(fn($t) => ['name' => $t['name'], 'colours' => $t['colours']], SF_THEMES),
    'business'    => ['name' => (string)$business['name'], 'logo' => $logo_url, 'currency' => $currency,
                      'tagline' => (string)($business['tagline'] ?? ''), 'base' => shop_base_host(),
                      'address' => (string)($business['address'] ?? ''), 'owner' => is_owner(), 'city' => $city_example],
    'today'       => date('Y-m-d'),
    'visible'     => (bool)(int)$business['landing_enabled'],
];

// Each look's heading font, cut down to the letters the Studio shows: the
// shop's name on the font cards and "Aa" on the look cards
$sample   = mb_substr((string)$business['name'], 0, 40) . 'Aa';
$families = ['Bodoni+Moda:wght@600', 'Agbalumo', 'Gloock', 'Archivo:wdth,wght@125,800', 'Barlow+Condensed:wght@700',
             'Unbounded:wght@600', 'Big+Shoulders+Stencil:wght@800', 'Young+Serif', 'Familjen+Grotesk:wght@700', 'Gabarito:wght@800'];
$samples_url = 'https://fonts.googleapis.com/css2?family=' . implode('&family=', $families) . '&text=' . rawurlencode($sample) . '&display=swap';
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8">
    
    <?= errors_script_tag() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#FFFFFF">
    <title>Shop Studio — <?= e($business['name']) ?> | Nfelia</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600..800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="<?= e($samples_url) ?>">
    <?php if ($brand_pair): ?>
    <link rel="stylesheet" href="<?= e($brand_pair['url']) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= e($asset('assets/css/studio.css')) ?>">
    <style>
        :root {
            --accent: <?= $chrome['accent'] ?>; --on-accent: <?= $chrome['on_accent'] ?>; --accent-text: <?= $chrome['accent_text'] ?>;
            <?php foreach ($greys as $name => $grey): ?><?= $name ?>: <?= $grey ?>; <?php endforeach; ?>

        }
    </style>
</head>
<body>
<div class="st-app" data-studio>

    <header class="st-top">
        <a class="st-iconbtn" href="<?= e(APP_URL) ?>/dashboard" aria-label="Back to your dashboard"><?= $icon('left') ?></a>
        <div class="st-top__title">
            <h1>Shop Studio</h1>
            <p class="st-status" role="status" aria-live="polite" data-status><?= $unpublished && $published !== null
                ? 'You have changes that aren\'t published yet.'
                : 'Changes stay in a draft until you publish.' ?></p>
        </div>
        <div class="st-top__tools">
            <button type="button" class="st-iconbtn" data-undo aria-label="Undo" disabled><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 14L4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/></svg></button>
            <button type="button" class="st-iconbtn" data-redo aria-label="Redo" disabled><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 14l5-5-5-5"/><path d="M20 9H9.5a5.5 5.5 0 0 0 0 11H13"/></svg></button>
        </div>
        <button type="button" class="st-btn st-btn--primary st-publish" data-publish <?= $unpublished ? '' : 'disabled' ?>><?= $unpublished ? 'Publish' : 'Published' ?></button>
    </header>

    <main class="st-main">
        <section class="st-preview" aria-label="Preview of your draft">
            <div class="st-preview__bar">
                <p class="st-preview__host"><?= e($shop_host ?? 'Your shop') ?></p>
                <div class="st-device" role="group" aria-label="Preview size">
                    <button type="button" data-device="phone" aria-pressed="true">Phone</button>
                    <button type="button" data-device="computer" aria-pressed="false">Computer</button>
                </div>
                <a class="st-link" href="<?= e(APP_URL) ?>/landing-page/preview" target="_blank" rel="noopener"><?= $icon('sparkle', 16) ?><span>Full preview</span></a>
            </div>
            <div class="st-preview__stage" data-stage>
                <iframe class="st-preview__frame" src="<?= e(APP_URL) ?>/landing-page/preview" title="Preview of your shop" data-frame></iframe>
            </div>
        </section>

        <section class="st-panel" aria-label="Change your shop" data-panel>
            <button type="button" class="st-panel__handle" data-expand aria-expanded="false" aria-label="Make the controls taller"><span></span></button>
            <div class="st-tabs" role="tablist" aria-label="What to change">
                <?php foreach (['look' => 'Look', 'colours' => 'Colours', 'fonts' => 'Fonts', 'sections' => 'Sections', 'more' => 'More'] as $key => $label): ?>
                    <button type="button" role="tab" id="st-tab-<?= $key ?>" aria-controls="st-panel-<?= $key ?>" aria-selected="<?= $key === 'look' ? 'true' : 'false' ?>" tabindex="<?= $key === 'look' ? '0' : '-1' ?>" data-tab="<?= $key ?>"><?= $label ?></button>
                <?php endforeach; ?>
            </div>

            <div class="st-panel__body">
                <div class="st-tabpanel" role="tabpanel" id="st-panel-look" aria-labelledby="st-tab-look" data-tab-panel="look">
                    <h2 class="st-h2">Pick a look to start from</h2>
                    <div class="st-looks" data-looks></div>
                    <p class="st-help">Your products, photos and words stay as they are. Switch looks as often as you like.</p>
                </div>

                <div class="st-tabpanel" role="tabpanel" id="st-panel-colours" aria-labelledby="st-tab-colours" data-tab-panel="colours" hidden>
                    <div class="st-block">
                        <h2 class="st-h2">Your brand</h2>
                        <div class="st-chips st-chips--three" data-brand-sets></div>
                    </div>
                    <div class="st-block">
                        <h2 class="st-h2">From your logo</h2>
                        <?php if ($logo_url !== ''): ?>
                            <div class="st-logo-row">
                                <img class="st-logo" src="<?= e($logo_url) ?>" alt="Your logo" data-logo crossorigin="anonymous">
                                <div class="st-chips st-chips--three" data-logo-sets><p class="st-help">Reading your logo…</p></div>
                            </div>
                        <?php else: ?>
                            <p class="st-help">Add your logo in <a href="<?= e(APP_URL) ?>/settings?tab=business">Settings</a> and we'll suggest colours from it.</p>
                        <?php endif; ?>
                    </div>
                    <div class="st-block">
                        <h2 class="st-h2">Colour sets</h2>
                        <div class="st-chips st-chips--five" data-sets></div>
                    </div>
                    <div class="st-block">
                        <h2 class="st-h2">Your own colour</h2>
                        <label class="st-colour">
                            <input type="color" data-own-colour value="<?= e($theme['palette']['accent']) ?>">
                            <span>Pick a colour for buttons and links</span>
                        </label>
                        <p class="st-help" data-own-note hidden></p>
                    </div>
                    <p class="st-help">Every set is checked so your words stay easy to read, even on a phone in bright sun.</p>
                </div>

                <div class="st-tabpanel" role="tabpanel" id="st-panel-fonts" aria-labelledby="st-tab-fonts" data-tab-panel="fonts" hidden>
                    <h2 class="st-h2">Fonts</h2>
                    <div class="st-fonts" data-fonts></div>
                </div>

                <div class="st-tabpanel" role="tabpanel" id="st-panel-sections" aria-labelledby="st-tab-sections" data-tab-panel="sections" hidden>
                    <section class="st-card st-announce" data-announce-box aria-labelledby="st-announce-h"></section>
                    <div data-section-list>
                        <div class="st-row-head">
                            <h2 class="st-h2">Sections</h2>
                            <span class="st-help">Drag, or use the arrows, to reorder</span>
                        </div>
                        <ul class="st-sections" data-sections></ul>
                    </div>
                    <div class="st-editor" data-editor hidden></div>
                </div>

                <div class="st-tabpanel" role="tabpanel" id="st-panel-more" aria-labelledby="st-tab-more" data-tab-panel="more" hidden>
                    <section class="st-card st-seo" data-seo-box>
                        <h2 class="st-h2">Found on Google</h2>
                        <p class="st-help">How your shop shows in Google and when you share its link. Leave them empty to use your headline and the line under it. Shops on a free trial stay out of Google until the first payment.</p>
                        <div class="st-serp" aria-hidden="true">
                            <p class="st-serp__url"><?= e($shop_host ?: shop_base_host()) ?></p>
                            <p class="st-serp__title" data-serp-title></p>
                            <p class="st-serp__desc" data-serp-desc></p>
                        </div>
                        <div class="st-field">
                            <label class="st-label" for="st-seo-title">Title</label>
                            <input id="st-seo-title" type="text" maxlength="70" autocomplete="off" data-seo="title" placeholder="<?= e($seo_default_title) ?>">
                            <span class="st-help" data-seo-count="title" aria-live="polite"></span>
                        </div>
                        <div class="st-field">
                            <label class="st-label" for="st-seo-desc">Description</label>
                            <textarea id="st-seo-desc" maxlength="170" rows="3" data-seo="description" placeholder="<?= e($seo_default_desc) ?>"></textarea>
                            <span class="st-help" data-seo-count="description" aria-live="polite"></span>
                        </div>
                        <label class="st-check">
                            <input type="checkbox" data-seo="directory">
                            <span>List my shop in the Nfelia directory</span>
                        </label>
                        <p class="st-help">nfelia.com/shops lists paid shops by city and by what they sell, each with a link to its shop.</p>
                        <div class="st-field" data-seo-city-row>
                            <label class="st-label" for="st-seo-city">City</label>
                            <input id="st-seo-city" type="text" maxlength="60" autocomplete="off" list="st-cities" data-seo="city" placeholder="<?= e($city_example) ?>">
                            <datalist id="st-cities">
                                <?php foreach ($city_choices as $city_name): ?><option value="<?= e($city_name) ?>"><?php endforeach; ?>
                            </datalist>
                        </div>
                        <p class="st-help">Publish to put these live.</p>
                    </section>

                    <section class="st-card">
                        <h2 class="st-h2">Your shop link</h2>
                        <?php if ($public_url): ?>
                            <p class="st-linkline">
                                <a href="<?= e($public_url) ?>" target="_blank" rel="noopener"><?= e($shop_host) ?></a>
                                <span style="display: inline-flex; flex-wrap: wrap; gap: 8px">
                                    <button type="button" class="st-btn st-btn--quiet st-btn--sm" data-copy="<?= e($public_url) ?>">Copy link</button>
                                    <a class="st-btn st-btn--quiet st-btn--sm" href="https://wa.me/?text=<?= e(rawurlencode('Have a look at my shop: ' . $public_url)) ?>" target="_blank" rel="noopener">Share on WhatsApp</a>
                                </span>
                            </p>
                        <?php else: ?>
                            <p class="st-help">Your shop doesn't have a live link yet.</p>
                        <?php endif; ?>

                        <?php if ($pending_name): ?>
                            <p class="st-note st-note--wait">We're checking <strong><?= e($pending_name . '.' . shop_base_host()) ?></strong>.
                                <?= $public_url ? 'Your current link keeps working until then.' : 'Your shop goes live as soon as it is approved.' ?></p>
                        <?php elseif (!$public_url): ?>
                            <a class="st-btn st-btn--quiet" href="<?= e(APP_URL) ?>/shop-setup">Choose your shop link</a>
                        <?php elseif ($can_rename): ?>
                            <details class="st-rename">
                                <summary>Change link</summary>
                                <form method="POST" action="<?= e(APP_URL) ?>/shop-setup/rename" data-rename>
                                    <?= csrf_field() ?>
                                    <label class="st-label" for="st-rename-name">New shop name</label>
                                    <div class="st-namefield">
                                        <input id="st-rename-name" name="shop_name" type="text" autocomplete="off" autocapitalize="none" spellcheck="false" maxlength="<?= SHOP_NAME_MAX ?>" aria-describedby="st-rename-status st-rename-rules" data-name-input>
                                        <span>.<?= e(shop_base_host()) ?></span>
                                    </div>
                                    <p class="st-namestatus" id="st-rename-status" role="status" data-name-status></p>
                                    <p class="st-help" id="st-rename-rules">Use <?= SHOP_NAME_MIN ?> to <?= SHOP_NAME_MAX ?> letters, numbers or hyphens; spaces become hyphens. You can change your link once. Your old link keeps sending people to the new one for 90 days, so update it where you've shared it.</p>
                                    <button type="submit" class="st-btn st-btn--primary" data-name-submit disabled>Change my link</button>
                                </form>
                            </details>
                        <?php elseif (is_owner()): ?>
                            <p class="st-help">You've already changed your link once. <a href="<?= e(APP_URL) ?>/contact-support">Contact support</a> to change it again.</p>
                        <?php endif; ?>
                    </section>

                    <section class="st-card">
                        <h2 class="st-h2">Show your shop</h2>
                        <div class="st-switchrow">
                            <span id="st-visible-label">Customers can see my shop</span>
                            <button type="button" class="st-switch" role="switch" aria-checked="<?= (int)$business['landing_enabled'] ? 'true' : 'false' ?>" aria-labelledby="st-visible-label" data-visibility><span></span></button>
                        </div>
                        <p class="st-help">When it's off, your link says there is no shop at that address. Your products and orders stay as they are.</p>
                    </section>

                    <?php /* shop_sold_out(): saved at once, like the switch above */ ?>
                    <section class="st-card">
                        <h2 class="st-h2">Sold out</h2>
                        <div class="st-switchrow">
                            <span id="st-soldout-label">Show items as sold out when their stock runs out</span>
                            <button type="button" class="st-switch" role="switch" aria-checked="<?= (int)($business['shop_sold_out'] ?? 0) ? 'true' : 'false' ?>" aria-labelledby="st-soldout-label" data-sold-out><span></span></button>
                        </div>
                        <p class="st-help">When it's on, an item with no stock left says “Sold out” and can't be ordered until you add stock. Leave it off if you don't count your stock in Nfelia. Services and things people book are never sold out.</p>
                    </section>

                    <section class="st-card">
                        <h2 class="st-h2">Earlier versions</h2>
                        <p class="st-help">Your last ten publishes. Bring one back into your draft, check it, then publish it.</p>
                        <ul class="st-versions" data-versions><li class="st-help">Loading…</li></ul>
                    </section>

                    <section class="st-card">
                        <h2 class="st-h2">“Shop by Nfelia”</h2>
                        <?php if ((int)($business['hide_nfelia_credit'] ?? 0)): ?>
                            <p class="st-help">Your shop's footer doesn't show “Shop by Nfelia”.</p>
                        <?php else: ?>
                            <p class="st-help">Your shop's footer shows a small “Shop by Nfelia” line. To remove it, <a href="<?= e(APP_URL) ?>/contact-support">ask us about the add-on</a>.</p>
                        <?php endif; ?>
                    </section>
                </div>
            </div>
        </section>
    </main>
</div>

<p class="st-toast" role="status" aria-live="polite" data-toast></p>

<script type="application/json" id="st-data"><?= json_encode($studio, $json_flags) ?></script>
<script src="<?= e($asset('assets/js/studio.js')) ?>" defer></script>
<script src="<?= e($asset('assets/js/wa.js')) ?>" defer></script>
</body>
</html>
