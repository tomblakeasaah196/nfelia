<?php
/**
 * The review form, name.nfelia.com/review/{token}: stars and a few words
 * about an order that was delivered or collected. One review per order; it
 * shows on the shop as "Bisi A." and the owner can't change it. Works as a
 * plain form, no script needed.
 *
 * From shell.php: $page['review'] (with order_number and public_token),
 * $page['error'] and $page['comment'] (when the form comes back), $page['rating'].
 */
$review   = $page['review'];
$shop     = (string)$business['name'];
$answered = $review['status'] !== 'invited';
$shown_as = review_display_name((string)$review['customer_name']);
$picked   = (int)($page['rating'] ?? 0);
$words    = [1 => 'Poor', 2 => 'Not great', 3 => 'Okay', 4 => 'Good', 5 => 'Excellent'];
$star     = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2.8l2.8 5.8 6.3.9-4.6 4.4 1.1 6.3L12 17.2l-5.6 3 1.1-6.3-4.6-4.4 6.3-.9z"/></svg>';
?>
<section class="sf-section sf-page" aria-labelledby="sf-review-title">
    <div class="sf-wrap sf-wrap--narrow">
        <p class="sf-eyebrow">Order <?= e($review['order_number']) ?></p>
        <?php if ($answered): ?>
            <span class="sf-done__icon" aria-hidden="true"><?= sf_icon('check', 30) ?></span>
            <h1 class="sf-h1 sf-page__title" id="sf-review-title">Thank you</h1>
            <p class="sf-lead">Your review helps other people shop at <?= e($shop) ?> with confidence.</p>
            <?php if ($review['rating']): ?>
                <div class="sf-card sf-yourreview">
                    <p class="sf-card__title">Your review</p>
                    <?= sf_stars((float)$review['rating'], 20) ?>
                    <?php if ($review['comment']): ?>
                        <div class="sf-yourreview__text"><?= sf_paragraphs((string)$review['comment']) ?></div>
                    <?php endif; ?>
                    <p class="sf-yourreview__by"><?= e($shown_as) ?></p>
                </div>
            <?php endif; ?>
            <div class="sf-page__actions">
                <a class="sf-btn sf-btn--block" href="/">Back to <?= e($shop) ?></a>
                <a class="sf-link" href="/order/<?= e($review['public_token']) ?>">See your order</a>
            </div>
        <?php else: ?>
            <h1 class="sf-h1 sf-page__title" id="sf-review-title">How was your order from <?= e($shop) ?>?</h1>
            <form class="sf-form sf-reviewform" method="post" action="/review/<?= e($review['token']) ?>">
                <fieldset class="sf-rate">
                    <legend class="sf-field__label">Your rating</legend>
                    <div class="sf-rate__stars">
                        <?php for ($n = 1; $n <= 5; $n++): ?>
                            <label class="sf-rate__star">
                                <input type="radio" name="rating" value="<?= $n ?>" required<?= $picked === $n ? ' checked' : '' ?>>
                                <?= $star ?>
                                <span class="sf-sr"><?= $n ?> star<?= $n === 1 ? '' : 's' ?>: <?= e($words[$n]) ?></span>
                            </label>
                        <?php endfor; ?>
                    </div>
                    <p class="sf-rate__words" aria-hidden="true">
                        <?php foreach ($words as $n => $word): ?>
                            <span data-n="<?= $n ?>"><?= e($word) ?></span>
                        <?php endforeach; ?>
                    </p>
                </fieldset>
                <label class="sf-field">
                    <span class="sf-field__label">Tell others about it <span class="sf-field__opt">(optional)</span></span>
                    <textarea name="comment" rows="4" maxlength="600" placeholder="What you got, how it arrived, what you liked"><?= e((string)($page['comment'] ?? '')) ?></textarea>
                    <span class="sf-field__hint">It shows on the shop with your stars as “<?= e($shown_as) ?>”. The shop can't change it.</span>
                </label>
                <?php if (!empty($page['error'])): ?>
                    <p class="sf-form__error" role="alert"><?= e($page['error']) ?></p>
                <?php endif; ?>
                <button type="submit" class="sf-btn sf-btn--block">Post my review</button>
            </form>
        <?php endif; ?>
    </div>
</section>
