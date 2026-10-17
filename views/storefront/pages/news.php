<?php
/**
 * The shop's news and offers, name.nfelia.com/news: every published post,
 * newest first.
 *
 * From shell.php: $page['posts'].
 */
?>
<section class="sf-section sf-page sf-news-page" aria-labelledby="sf-news-page-title">
    <div class="sf-wrap">
        <a class="sf-back" href="/"><?= sf_icon('left', 18) ?><span><?= e($business['name']) ?></span></a>
        <h1 class="sf-h1 sf-page__title" id="sf-news-page-title">News and offers</h1>
        <ul class="sf-posts">
            <?php foreach ($page['posts'] as $post): ?>
                <?php include __DIR__ . '/../parts/post_card.php'; ?>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
