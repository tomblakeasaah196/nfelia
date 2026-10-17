<?php
/**
 * "New in": the owner's picks, or the newest few products once the shop has
 * enough of them. Drawn as tiles in the look's own frame.
 */
$featured_items = storefront_featured($catalogue, $section, $settings['look'] === 'buka' ? 3 : 4);
if (!$featured_items && $mode === 'live') {
    return;
}
$featured_title = $section['title'] !== '' ? $section['title'] : $labels['featured'];
?>
<section class="sf-section sf-featured" id="sf-featured" aria-labelledby="sf-featured-title">
    <div class="sf-wrap">
        <div class="sf-section__head">
            <h2 class="sf-h2" id="sf-featured-title"><?= e($featured_title) ?></h2>
            <?php if ($featured_items && $sections['catalogue']['on'] && count($catalogue['items']) > count($featured_items)): ?>
                <a class="sf-link" href="#sf-catalogue">See all <?= count($catalogue['items']) ?></a>
            <?php endif; ?>
        </div>
        <?php if ($featured_items): ?>
            <div class="sf-tiles sf-tiles--<?= e($look['tiles']) ?>">
                <?php foreach ($featured_items as $item): ?>
                    <?= sf_tile($item, $ctx, $look['tiles']) ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <?= sf_gap($ctx, 'Pick products in Shop Studio, or add six or more and your newest show here.') ?>
        <?php endif; ?>
    </div>
</section>
