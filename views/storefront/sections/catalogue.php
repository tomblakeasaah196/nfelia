<?php
/**
 * Everything the shop sells, on its own page (/shop), in the look's own
 * style: a filter by category when there is more than one, a search box for
 * longer lists, and products grouped under their category where the look
 * reads like a menu or a price list. The home page shows a taste of it
 * instead (catalogue_home.php).
 */
$items      = $catalogue['items'];
$style      = $look['items'];
$cat_title  = $section['title'] !== '' ? $section['title'] : $labels['catalogue'];
$grouped    = in_array($style, ['menu', 'pricerow', 'pricelist'], true);
$searchable = count($items) >= (in_array($settings['look'], ['volt', 'market'], true) ? 6 : 8);
$categories = $catalogue['categories'];
$render     = 'sf_item_' . $style;

// Packages read best from the smallest up, after any the owner pinned
if ($style === 'package') {
    usort($items, fn($a, $b) => [$a['pin'] === null, $a['pin'], $a['price'] <= 0, $a['price'], $a['name']] <=> [$b['pin'] === null, $b['pin'], $b['price'] <= 0, $b['price'], $b['name']]);
}

// Volt pulls services out into its repair-desk ticket
$ticket = [];
if ($settings['look'] === 'volt' && $catalogue['has_products']) {
    $ticket = array_values(array_filter($items, fn($i) => $i['is_service']));
    $items  = array_values(array_filter($items, fn($i) => !$i['is_service']));
}
if (!$items && !$ticket && $mode === 'live') {
    if ($wa === '') {
        return;
    }
}
?>
<section class="sf-section sf-catalogue sf-catalogue--<?= e($style) ?>" id="sf-catalogue" aria-labelledby="sf-catalogue-title" data-catalogue>
    <div class="sf-wrap">
        <div class="sf-section__head sf-page__head">
            <h1 class="sf-h1 sf-page__title" id="sf-catalogue-title"><?= e($cat_title) ?></h1>
            <?php if (count($items) > 1): ?><span class="sf-section__count" data-count><?= count($items) ?> items</span><?php endif; ?>
        </div>
        <?php if ($settings['look'] === 'market' && $catalogue['updated_at']): ?>
            <p class="sf-page__note"><?= sf_icon('clock', 16) ?>Prices updated <?= e(date('j F Y', strtotime($catalogue['updated_at']))) ?></p>
        <?php endif; ?>

        <?php if ($searchable || count($categories) > 1): ?><div class="sf-shopbar"><?php endif; ?>
        <?php if ($searchable): ?>
            <label class="sf-search">
                <span class="sf-sr">Search the shop</span>
                <?= sf_icon('search', 20) ?>
                <input type="search" data-search placeholder="Search <?= count($items) ?> items" autocomplete="off" enterkeyhint="search">
            </label>
        <?php endif; ?>

        <?php if (count($categories) > 1): ?>
            <div class="sf-filters" role="group" aria-label="Show a category">
                <?php if ($settings['look'] === 'shea'): ?><span class="sf-filters__label" aria-hidden="true">Show products for</span><?php endif; ?>
                <button type="button" class="sf-filter" data-filter="" aria-pressed="true">All</button>
                <?php foreach ($categories as $category): ?>
                    <button type="button" class="sf-filter" data-filter="<?= e(mb_strtolower($category)) ?>" aria-pressed="false"><?= e($category) ?></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($searchable || count($categories) > 1): ?></div><?php endif; ?>

        <?php if (!$items && !$ticket): ?>
            <?php if ($mode === 'live'): ?>
                <p class="sf-empty">New items are on the way. <a class="sf-link" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener">Ask on WhatsApp</a> what's available today.</p>
            <?php else: ?>
                <p class="sf-gap">Add products or services in Nfelia and they show here.</p>
            <?php endif; ?>
        <?php elseif ($grouped): ?>
            <?php
            $groups = [];
            foreach ($items as $item) {
                $groups[$item['category'] !== '' ? $item['category'] : ''][] = $item;
            }
            $order = array_flip($categories);
            uksort($groups, fn($a, $b) => ($order[$a] ?? PHP_INT_MAX) <=> ($order[$b] ?? PHP_INT_MAX));
            ?>
            <div class="sf-groups" data-list>
                <?php foreach ($groups as $category => $group_items): ?>
                    <div class="sf-group" data-group="<?= e(mb_strtolower($category)) ?>">
                        <?php if (count($groups) > 1 || $style === 'pricelist'): ?>
                            <div class="sf-group__head">
                                <h3 class="sf-h3"><?= e($category !== '' ? $category : 'More') ?></h3>
                                <?php if ($style === 'pricelist'): ?><span class="sf-group__col">Price</span><?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <div class="sf-items sf-items--<?= e($style) ?>">
                            <?php foreach ($group_items as $item): ?>
                                <?= $render($item, $ctx) ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="sf-items sf-items--<?= e($style) ?>" data-list>
                <?php foreach ($items as $item): ?>
                    <?= $render($item, $ctx) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <p class="sf-empty" data-no-match hidden>Nothing matches that. Try another word, or <button type="button" class="sf-link sf-link--button" data-clear-filters>show everything</button>.</p>

        <?php if ($ticket): ?>
            <div class="sf-ticket" id="sf-services">
                <div class="sf-ticket__body">
                    <h3 class="sf-h2">Services</h3>
                    <dl class="sf-ticket__list">
                        <?php foreach ($ticket as $item): ?>
                            <div <?= sf_item_attrs($item, 'sf-ticket__row') ?>>
                                <dt><?= sf_open_link($item, e($item['name'])) ?></dt>
                                <dd><?= sf_price_html($item, $ctx) ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                </div>
                <div class="sf-ticket__tear" aria-hidden="true"></div>
                <div class="sf-ticket__foot">
                    <?php if ($quote_on): ?>
                        <a class="sf-btn sf-btn--band sf-btn--block" href="<?= e(sf_quote_href()) ?>">Ask for a quote</a>
                    <?php elseif ($wa !== ''): ?>
                        <a class="sf-btn sf-btn--band sf-btn--block" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener">Ask on WhatsApp</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
