<?php
/**
 * A product's own page, name.nfelia.com/p/{id}-{name}: its photos, price,
 * choices and description, adding it to the bag, sharing it (a link, or an
 * image for WhatsApp Status), and more from the shop. The shop page opens
 * the same product as a sheet; this page is what a shared link, a new tab or
 * a search engine gets.
 *
 * From shell.php: $page['item'], $ctx, $catalogue, $sections, $reviews.
 */
$item      = $page['item'];
$bag_word  = $settings['look'] === 'market' ? 'order' : 'bag';
$photos    = array_values(array_filter($item['images'], fn($rel) => sf_image_size($rel) !== null));
$delivery  = $sections['delivery'];

// Up to four more: the same category first, then the newest
$related = array_values(array_filter($catalogue['items'], fn($other) => $other['id'] !== $item['id']));
$same    = fn(array $other) => $item['category'] !== '' && $other['category'] === $item['category'] ? 1 : 0;
usort($related, fn($a, $b) => [$same($b), $b['created_at'], $b['id']] <=> [$same($a), $a['created_at'], $a['id']]);
$related = array_slice($related, 0, $settings['look'] === 'buka' ? 3 : 4);

$wa_text = 'Hello ' . $business['name'] . ', I found ' . $item['name'] . ' on your shop. Is it available?';
?>
<article class="sf-pdp" data-item="<?= (int)$item['id'] ?>" aria-labelledby="sf-pdp-name">
    <div class="sf-wrap">
        <a class="sf-back" href="/#sf-catalogue"><?= sf_icon('left', 18) ?><span>All of <?= e($business['name']) ?></span></a>

        <div class="sf-pdp__grid">
            <div class="sf-pdp__media">
                <div class="sf-pdp__gallery" data-gallery>
                    <?php if ($photos): ?>
                        <?php foreach ($photos as $i => $rel): ?>
                            <div class="sf-pdp__slide">
                                <?= sf_img($rel, '(min-width: 1080px) 560px, (min-width: 720px) 50vw, 100vw', $item['name'] . (count($photos) > 1 ? ', photo ' . ($i + 1) . ' of ' . count($photos) : ''), '', $i === 0) ?>
                                <?php if (count($photos) > 1): ?>
                                    <span class="sf-product__count" aria-hidden="true"><?= $i + 1 ?> of <?= count($photos) ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="sf-pdp__slide">
                            <span class="sf-ph sf-ph--<?= (int)$item['id'] % 4 ?>"><span class="sf-ph__mark"><?= e(sf_initials($item['name'])) ?></span></span>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if (count($photos) > 1): ?>
                    <div class="sf-pdp__thumbs">
                        <?php foreach ($photos as $i => $rel): ?>
                            <button type="button" class="sf-pdp__thumb" data-thumb="<?= $i ?>" aria-label="Photo <?= $i + 1 ?> of <?= count($photos) ?>"<?= $i === 0 ? ' aria-current="true"' : '' ?>>
                                <?= sf_img($rel, '72px', '', '') ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="sf-pdp__body">
                <?php if ($item['category'] !== ''): ?>
                    <p class="sf-eyebrow"><?= e($item['category']) ?></p>
                <?php endif; ?>
                <h1 class="sf-h1 sf-pdp__name" id="sf-pdp-name"><?= e($item['name']) ?></h1>
                <p class="sf-pdp__price"><?= sf_price_html($item, $ctx) ?></p>
                <?php if ($reviews && $reviews['count']): ?>
                    <a class="sf-rating sf-rating--link" href="/#sf-reviews">
                        <?= sf_stars($reviews['average'], 16) ?>
                        <strong><?= e(number_format($reviews['average'], 1)) ?></strong>
                        <span><?= (int)$reviews['count'] ?> review<?= $reviews['count'] === 1 ? '' : 's' ?> of <?= e($business['name']) ?></span>
                    </a>
                <?php endif; ?>
                <?php if ($item['description'] !== ''): ?>
                    <div class="sf-prose sf-pdp__desc"><?= sf_paragraphs($item['description']) ?></div>
                <?php endif; ?>

                <?php if ($item['booking']): ?>
                <div class="sf-buy sf-buy--book">
                    <p class="sf-buy__meta"><?= sf_icon('clock', 18) ?><span><?= e(booking_summary($item['booking'], $item['price'], $currency)) ?></span></p>
                    <a class="sf-btn sf-btn--block" href="<?= e(booking_page_path($item)) ?>" data-book="<?= (int)$item['id'] ?>"><?= sf_icon('calendar', 20) ?><span><?= match ($item['booking']['mode']) { 'nights' => 'Check dates and book', 'day' => 'Pick a day and book', default => 'Pick a time and book' } ?></span></a>
                    <?php if ($wa !== ''): ?>
                        <a class="sf-btn sf-btn--outline sf-btn--block" href="https://wa.me/<?= e($wa) ?>?text=<?= e(rawurlencode($wa_text)) ?>" target="_blank" rel="noopener"><?= sf_icon('whatsapp', 20) ?><span>Ask on WhatsApp</span></a>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <?php $has_priced = (bool)array_filter($item['variants'], fn($g) => !empty($g['deltas'])); ?>
                <form class="sf-buy" data-buy="<?= (int)$item['id'] ?>" novalidate>
                    <?php foreach ($item['variants'] as $gi => $group): ?>
                        <fieldset class="sf-buy__group" data-group="<?= e($group['name']) ?>">
                            <legend><?= e($group['name']) ?></legend>
                            <div class="sf-choices">
                                <?php foreach ($group['options'] as $oi => $option):
                                    $d = (float)($group['deltas'][$option] ?? 0); ?>
                                    <label class="sf-choice">
                                        <input type="radio" name="pdp-v<?= $gi ?>" value="<?= e($option) ?>" data-delta="<?= e(rtrim(rtrim(number_format($d, 2, '.', ''), '0'), '.')) ?>"<?= $oi === 0 ? ' checked' : '' ?>>
                                        <span><?= e($option) ?><?php if ($d != 0.0): ?> <small class="sf-choice__delta"><?= ($d > 0 ? '+' : '−') . e(sf_money(abs($d), $currency)) ?></small><?php endif; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                    <?php endforeach; ?>
                    <?php if ($has_priced): ?>
                        <p class="sf-buy__chosen" data-buy-price hidden></p>
                    <?php endif; ?>
                    <label class="sf-field">
                        <span class="sf-field__label">Note for the shop <span class="sf-field__opt">(optional)</span></span>
                        <input type="text" name="note" maxlength="255" autocomplete="off">
                    </label>
                    <div class="sf-product__buy">
                        <div class="sf-stepper sf-stepper--lg">
                            <button type="button" data-buy-step="-1" aria-label="One fewer"><?= sf_icon('minus', 18) ?></button>
                            <output data-buy-qty aria-live="polite">1</output>
                            <button type="button" data-buy-step="1" aria-label="One more"><?= sf_icon('plus', 18) ?></button>
                        </div>
                        <button type="submit" class="sf-btn sf-btn--grow">Add to <?= $bag_word ?></button>
                    </div>
                    <?php if ($wa !== ''): ?>
                        <a class="sf-btn sf-btn--outline sf-btn--block" data-buy-wa href="https://wa.me/<?= e($wa) ?>?text=<?= e(rawurlencode($wa_text)) ?>" target="_blank" rel="noopener"><?= sf_icon('whatsapp', 20) ?><span>Ask on WhatsApp</span></a>
                    <?php endif; ?>
                </form>
                <?php endif; ?>

                <div class="sf-share">
                    <button type="button" class="sf-share__btn" data-share-item="<?= (int)$item['id'] ?>"><?= sf_icon('share', 18) ?><span>Share</span></button>
                    <button type="button" class="sf-share__btn" data-status-item="<?= (int)$item['id'] ?>"><?= sf_icon('image', 18) ?><span>Image for your Status</span></button>
                </div>

                <?php if ($delivery['on'] && ($delivery['text'] !== '' || $catalogue['banks'])): ?>
                    <div class="sf-pdp__info">
                        <h2 class="sf-pdp__info-title"><?= sf_icon('truck', 20) ?><span><?= e($delivery['title'] !== '' ? $delivery['title'] : $labels['delivery']) ?></span></h2>
                        <?php if ($delivery['text'] !== ''): ?>
                            <?= sf_paragraphs($delivery['text']) ?>
                        <?php endif; ?>
                        <?php if ($catalogue['banks']): ?>
                            <p>Pay by <?= e(sf_pay_way($catalogue['banks'])) ?> when you check out<?= $wa !== '' ? ', or order on WhatsApp' : '' ?>.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</article>

<?php if ($related): ?>
<section class="sf-section sf-featured" aria-labelledby="sf-more-title">
    <div class="sf-wrap">
        <div class="sf-section__head">
            <h2 class="sf-h2" id="sf-more-title">More from <?= e($business['name']) ?></h2>
            <?php if (count($catalogue['items']) > count($related) + 1): ?>
                <a class="sf-link" href="/#sf-catalogue">See all <?= count($catalogue['items']) ?></a>
            <?php endif; ?>
        </div>
        <div class="sf-tiles sf-tiles--<?= e($look['tiles']) ?>">
            <?php foreach ($related as $other): ?>
                <?= sf_tile($other, $ctx, $look['tiles']) ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
