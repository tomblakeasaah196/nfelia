<?php
/**
 * One post as a card: its photo, date, title and first words, linking to
 * its page. $post from the including view.
 */
$post_href = $mode === 'live' ? shop_post_path($post) : '#sf-news';
$post_img  = $post['image_path'] ? sf_img((string)$post['image_path'], '(min-width: 1080px) 360px, (min-width: 720px) 45vw, 100vw', '', 'sf-post-card__img') : '';
?>
<li class="sf-post-card">
    <?php if ($post_img !== ''): ?>
        <a class="sf-post-card__media" href="<?= e($post_href) ?>" tabindex="-1" aria-hidden="true"><?= $post_img ?></a>
    <?php endif; ?>
    <div class="sf-post-card__body">
        <p class="sf-post-card__date"><time datetime="<?= e(date('Y-m-d', strtotime((string)$post['published_at']))) ?>"><?= e(date('j F Y', strtotime((string)$post['published_at']))) ?></time></p>
        <h3 class="sf-post-card__title"><a href="<?= e($post_href) ?>"><?= e($post['title']) ?></a></h3>
        <p class="sf-post-card__text"><?= e(shop_post_excerpt((string)$post['body'], 140)) ?></p>
    </div>
</li>
