<?php
/**
 * Market's first screen: the price board, painted like a shop's signboard.
 * The shop's name and headline across the top, today's prices underneath by
 * category, each with Add, which opens the trader's calculator to type how
 * many, and a stepper once it is in the order. Prices that moved since the
 * shopper last looked get an arrow (storefront.js, "Since your last visit").
 * A second tab takes a list typed or pasted the way people send it on
 * WhatsApp and prices every line ("Send us your list"). The order fills the
 * carbon order book in parts/bag.php.
 *
 * From hero.php: $hero_section, $headline, $text_html, $badge, $media, and
 * the shell's $business, $catalogue, $ctx, $currency.
 */
$on_board = array_values(array_filter($catalogue['items'], fn($i) => $i['booking'] === null));
$all      = count($on_board);
$shown    = array_slice($on_board, 0, 30);
$rows = [];
foreach ($shown as $item) {
    $rows[$item['category'] !== '' ? $item['category'] : ''][] = $item;
}
$order = array_flip($catalogue['categories']);
uksort($rows, fn($a, $b) => ($order[$a] ?? PHP_INT_MAX) <=> ($order[$b] ?? PHP_INT_MAX));

$updated = $catalogue['updated_at'] ? strtotime($catalogue['updated_at']) : null;
$updated_text = $updated === null ? ''
    : (date('Y-m-d', $updated) === date('Y-m-d') ? 'Prices updated today, ' . sf_clock(date('H:i', $updated))
    : 'Prices updated ' . date('j F', $updated));
$hours = sf_opening_hours($business);
// A shop with no headline of its own has its name as one: painted once
$same_name = mb_strtolower(trim($headline)) === mb_strtolower(trim((string)$business['name']));
$min   = (float)$hero_section['min_order'];
$free  = (float)$hero_section['free_from'];
?>
<div class="sf-wrap">
    <div class="sf-board" data-board>
        <div class="sf-board__sign">
            <?php if ($same_name): ?>
                <h1 class="sf-board__shop" id="sf-hero-title"><span aria-hidden="true">★</span><?= e($business['name']) ?><span aria-hidden="true">★</span></h1>
            <?php else: ?>
                <p class="sf-board__shop" aria-hidden="true"><span>★</span><?= e($business['name']) ?><span>★</span></p>
                <h1 class="sf-board__title" id="sf-hero-title"><?= e($headline) ?></h1>
            <?php endif; ?>
            <?= $hero_section['text'] !== '' ? sf_paragraphs($hero_section['text'], 'sf-board__lead') : '' ?>
            <p class="sf-board__notes">
                <?php if ($hours): ?>
                    <span class="sf-open" data-hours="<?= e(json_encode($hours)) ?>" data-tz="<?= e(date_default_timezone_get()) ?>"><span class="sf-open__dot" aria-hidden="true"></span><span data-open-text><?= e(sf_hours_today($hours)) ?></span></span>
                <?php endif; ?>
                <?php if ($badge !== ''): ?><span><?= sf_icon('tag', 16) ?><?= e($badge) ?></span><?php endif; ?>
                <?php if ($min > 0): ?><span><?= sf_icon('clipboard', 16) ?>Smallest order <?= e(sf_money($min, $currency)) ?></span><?php endif; ?>
                <?php if ($free > 0): ?><span><?= sf_icon('truck', 16) ?>Free delivery from <?= e(sf_money($free, $currency)) ?></span><?php endif; ?>
            </p>
        </div>

        <div class="sf-board__tabs" role="tablist" aria-label="How to order">
            <button type="button" role="tab" id="sf-board-tab-prices" aria-controls="sf-board-prices" aria-selected="true" data-board-tab="prices"><?= sf_icon('list', 18) ?><span>Today's prices</span></button>
            <button type="button" role="tab" id="sf-board-tab-list" aria-controls="sf-board-list" aria-selected="false" tabindex="-1" data-board-tab="list"><?= sf_icon('clipboard', 18) ?><span>Send us your list</span></button>
        </div>

        <div class="sf-board__panel" id="sf-board-prices" role="tabpanel" aria-labelledby="sf-board-tab-prices" data-board-panel="prices">
            <?php if ($updated_text !== ''): ?><p class="sf-board__updated"><?= sf_icon('clock', 15) ?><?= e($updated_text) ?></p><?php endif; ?>
            <?php if (!$shown): ?>
                <?= sf_gap($ctx, 'Add products in Nfelia and your prices show on this board.') ?>
            <?php endif; ?>
            <?php foreach ($rows as $category => $group): ?>
                <div class="sf-board__group">
                    <?php if (count($rows) > 1): ?><h2 class="sf-board__cat"><?= e($category !== '' ? $category : 'More') ?></h2><?php endif; ?>
                    <?php foreach ($group as $item): $unit = sf_unit_text($item['unit']); ?>
                        <div <?= sf_item_attrs($item, 'sf-board__row') ?>>
                            <div class="sf-board__what">
                                <h3 class="sf-board__name"><?= sf_open_link($item, e($item['name'])) ?></h3>
                                <?php if ($unit !== ''): ?><p class="sf-board__unit"><?= e(ucfirst(trim($unit))) ?></p><?php endif; ?>
                                <?= sf_buy_more($item, $ctx) ?>
                            </div>
                            <p class="sf-board__price"><?= sf_price_html($item, $ctx, false) ?><?= sf_price_move($item) ?></p>
                            <div class="sf-board__act"><?= sf_qty_control($item, '') ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            <?php if ($all > count($shown)): ?>
                <a class="sf-board__more" href="<?= e(sf_page_href('catalogue')) ?>">See all <?= $all ?> prices<?= sf_icon('right', 18) ?></a>
            <?php endif; ?>
        </div>

        <div class="sf-board__panel" id="sf-board-list" role="tabpanel" aria-labelledby="sf-board-tab-list" data-board-panel="list" hidden>
            <form class="sf-mylist" data-mylist novalidate>
                <label class="sf-field">
                    <span class="sf-field__label">Type or paste your list, a line for each thing</span>
                    <textarea name="mylist" rows="5" maxlength="2000" placeholder="3 bags rice&#10;5 ctn noodles&#10;2 vegetable oil 5L" autocomplete="off" spellcheck="false" data-mylist-text></textarea>
                </label>
                <p class="sf-mylist__hint" data-mylist-hint>We price each line as you type. Anything we can't find goes to <?= e($business['name']) ?> as a note.</p>
                <ul class="sf-mylist__lines" data-mylist-lines aria-live="polite"></ul>
                <button type="submit" class="sf-btn sf-btn--block" data-mylist-add hidden>Add these to my order</button>
            </form>
        </div>
    </div>
</div>
