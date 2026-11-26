<?php
/**
 * A taste of everything the shop sells, on the home page; the whole list is
 * on its own page (/shop). With two or more categories, a tile for each,
 * opening the list at that category. Otherwise, where "New in" isn't shown,
 * the first few products in the look's own style. Either way, a way to see
 * everything.
 */
$items       = $catalogue['items'];
$categories  = $catalogue['categories'];
$all_count   = count($items);
$list_href   = sf_page_href('catalogue');
$list_title  = $section['title'] !== '' ? $section['title'] : $labels['catalogue'];
$featured_on = $sections['featured']['on'] && storefront_featured($catalogue, $sections['featured'], 1);
// Market's price board is its taste of the list, with its own way to all of it
if ($look['hero'] === 'board') {
    return;
}
// Haven's first screen lists its places, Stage's planner its packages and
// extras; here, only what else they sell
if ($look['hero'] === 'stays' || $look['hero'] === 'planner') {
    $planned = $look['hero'] === 'planner' ? storefront_plan($catalogue, $sections['hero']) : ['packages' => [], 'extras' => []];
    $in_plan = array_merge(array_column($planned['packages'], 'id'), array_column($planned['extras'], 'id'));
    $items = array_values(array_filter($items, fn($i) => ($i['booking']['mode'] ?? '') !== 'nights' && !in_array($i['id'], $in_plan, true)));
    if (!$items) {
        return;
    }
    $categories = array_values(array_intersect($categories, array_column($items, 'category')));
    $all_count  = count($catalogue['items']);
}
if (!$items) {
    if ($mode === 'live') {
        return;
    }
}

// A photo for each category: its first product that has one. Glow's
// stories already have a ring per category, so it shows its first prices.
$tiles = [];
if (count($categories) >= 2 && $look['hero'] !== 'stories') {
    foreach (array_slice($categories, 0, 6) as $category) {
        $in = array_values(array_filter($items, fn($i) => $i['category'] === $category));
        $pictured = array_values(array_filter($in, fn($i) => (bool)$i['images']))[0] ?? $in[0];
        $tiles[] = ['name' => $category, 'count' => count($in), 'item' => $pictured];
    }
}
$teaser = !$tiles && !$featured_on ? array_slice($items, 0, 6) : [];
$render = 'sf_item_' . $look['items'];

// How wide each tile shows, for its photo: the grid fills its rows for any
// number of categories (storefront.css, .sf-cats--N)
$tile_sizes = function (int $i) use ($tiles): string {
    $n    = count($tiles);
    $wide = $n === 2 || ($n === 5 && $i < 2);
    return '(min-width: 1080px) ' . ($wide ? '540px' : ($n === 4 ? '270px' : '360px'))
         . ', (min-width: 720px) ' . ($wide || $n === 4 ? '46vw' : '30vw')
         . ', ' . ($n % 2 === 1 && $i === 0 ? '92vw' : '46vw');
};
?>
<section class="sf-section sf-catalogue-home" id="sf-catalogue" aria-labelledby="sf-catalogue-title">
    <div class="sf-wrap">
        <?php if ($tiles || $teaser || !$items): ?>
            <div class="sf-section__head">
                <h2 class="sf-h2" id="sf-catalogue-title"><?= e($tiles ? 'Shop by category' : $list_title) ?></h2>
                <?php if ($all_count > count($teaser)): ?>
                    <a class="sf-link" href="<?= e($list_href) ?>">See all <?= $all_count ?></a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <h2 class="sf-sr" id="sf-catalogue-title"><?= e($list_title) ?></h2>
        <?php endif; ?>

        <?php if (!$items): ?>
            <?= sf_gap($ctx, 'Add products or services in Nfelia and a taste of them shows here, with a link to the full list.') ?>
        <?php elseif ($tiles): ?>
            <ul class="sf-cats sf-cats--<?= count($tiles) ?>">
                <?php foreach ($tiles as $tile_i => $tile): ?>
                    <li>
                        <a class="sf-cat" href="<?= e(sf_page_href('catalogue', '#cat=' . rawurlencode(mb_strtolower($tile['name'])))) ?>">
                            <span class="sf-cat__media"><?= sf_item_media($tile['item'], $tile_sizes($tile_i)) ?></span>
                            <span class="sf-cat__name"><?= e($tile['name']) ?></span>
                            <span class="sf-cat__count"><?= $tile['count'] ?> <?= $tile['count'] === 1 ? 'item' : 'items' ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php elseif ($teaser): ?>
            <div class="sf-items sf-items--<?= e($look['items']) ?>">
                <?php foreach ($teaser as $item): ?>
                    <?= $render($item, $ctx) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($items && !$tiles && !$teaser): ?>
            <div class="sf-catalogue-home__all">
                <a class="sf-btn sf-btn--outline" href="<?= e($list_href) ?>">See all <?= $all_count ?> <?= $all_count === 1 ? 'item' : 'items' ?></a>
            </div>
        <?php endif; ?>
    </div>
</section>
