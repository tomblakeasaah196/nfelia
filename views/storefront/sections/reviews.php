<?php
/**
 * Customer reviews: stars and words from people whose orders were delivered
 * or collected (shop_reviews in app/helpers/shop_orders.php). A live shop
 * shows the section once the first review arrives; Shop Studio shows where
 * it will go.
 */
$reviews_title = $section['title'] !== '' ? $section['title'] : ($labels['reviews'] ?? 'What customers say');
$has_reviews   = $reviews && $reviews['count'] > 0;
if (!$has_reviews && $mode === 'live') {
    return;
}
?>
<section class="sf-section sf-reviews" id="sf-reviews" aria-labelledby="sf-reviews-title">
    <div class="sf-wrap">
        <div class="sf-section__head sf-reviews__head">
            <h2 class="sf-h2" id="sf-reviews-title"><?= e($reviews_title) ?></h2>
            <?php if ($has_reviews): ?>
                <p class="sf-rating">
                    <?= sf_stars($reviews['average'], 18) ?>
                    <strong><?= e(number_format($reviews['average'], 1)) ?></strong>
                    <span>from <?= (int)$reviews['count'] ?> review<?= $reviews['count'] === 1 ? '' : 's' ?></span>
                </p>
            <?php endif; ?>
        </div>
        <?php if ($has_reviews): ?>
            <ul class="sf-reviews__list">
                <?php foreach ($reviews['items'] as $one): ?>
                    <li class="sf-review">
                        <?= sf_stars((float)$one['rating'], 16) ?>
                        <?php if ($one['comment']): ?>
                            <blockquote class="sf-review__text"><?= sf_paragraphs((string)$one['comment']) ?></blockquote>
                        <?php endif; ?>
                        <p class="sf-review__by"><?= e(review_display_name((string)$one['customer_name'])) ?> · <?= e(date('F Y', strtotime((string)$one['submitted_at']))) ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="sf-reviews__note"><?= sf_icon('shield', 16) ?><span>Only people whose orders were delivered or collected can leave a review.</span></p>
        <?php else: ?>
            <?= sf_gap($ctx, 'Reviews show here once customers rate orders that were delivered or collected.') ?>
        <?php endif; ?>
    </div>
</section>
