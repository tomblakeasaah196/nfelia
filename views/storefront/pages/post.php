<?php
/**
 * One post, name.nfelia.com/news/{id}-{title}: its photo, date and words,
 * sharing, and the way back to the shop.
 *
 * From shell.php: $page['post'], $shop_url.
 */
$post  = $page['post'];
$photo = $post['image_path'] ? sf_img((string)$post['image_path'], '(min-width: 760px) 720px, 100vw', '', 'sf-article__img', true) : '';
$more  = array_values(array_filter(shop_posts(get_db(), (int)$business['id'], 4), fn($p) => (int)$p['id'] !== (int)$post['id']));
?>
<article class="sf-section sf-article" aria-labelledby="sf-article-title">
    <div class="sf-wrap sf-wrap--article">
        <a class="sf-back" href="/news"><?= sf_icon('left', 18) ?><span>News and offers</span></a>
        <p class="sf-eyebrow"><time datetime="<?= e(date('Y-m-d', strtotime((string)$post['published_at']))) ?>"><?= e(date('j F Y', strtotime((string)$post['published_at']))) ?></time></p>
        <h1 class="sf-h1 sf-article__title" id="sf-article-title"><?= e($post['title']) ?></h1>
        <?php if ($photo !== ''): ?>
            <figure class="sf-article__media"><?= $photo ?></figure>
        <?php endif; ?>
        <div class="sf-prose sf-article__body"><?= sf_paragraphs((string)$post['body']) ?></div>
        <div class="sf-share">
            <button type="button" class="sf-share__btn" data-share-url="<?= e($shop_url . shop_post_path($post)) ?>" data-share-title="<?= e($post['title']) ?>"><?= sf_icon('share', 18) ?><span>Share</span></button>
            <?php if ($wa !== ''): ?>
                <a class="sf-share__btn" href="https://wa.me/<?= e($wa) ?>?text=<?= e(rawurlencode('Hello ' . $business['name'] . ', I saw "' . $post['title'] . '" on your shop.')) ?>" target="_blank" rel="noopener"><?= sf_icon('whatsapp', 18) ?><span>Ask on WhatsApp</span></a>
            <?php endif; ?>
        </div>
        <a class="sf-btn sf-btn--block sf-article__cta" href="/"><?= sf_icon('bag', 20) ?><span>See what <?= e($business['name']) ?> sells</span></a>
    </div>
</article>
<?php if ($more): ?>
<section class="sf-section" aria-labelledby="sf-more-news-title">
    <div class="sf-wrap">
        <div class="sf-section__head"><h2 class="sf-h2" id="sf-more-news-title">More news</h2></div>
        <ul class="sf-posts">
            <?php foreach (array_slice($more, 0, 3) as $post): ?>
                <?php include __DIR__ . '/../parts/post_card.php'; ?>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>
