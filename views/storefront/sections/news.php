<?php
/**
 * News and offers: the shop's three newest posts, each linking to its own
 * page. A live shop shows the section once the first post is published;
 * Shop Studio shows where it will go.
 */
if (!$news && $mode === 'live') {
    return;
}
$news_title = $section['title'] !== '' ? $section['title'] : 'News and offers';
?>
<section class="sf-section sf-news" id="sf-news" aria-labelledby="sf-news-title">
    <div class="sf-wrap">
        <div class="sf-section__head">
            <h2 class="sf-h2" id="sf-news-title"><?= e($news_title) ?></h2>
            <?php if ($news && $mode === 'live'): ?>
                <a class="sf-link" href="/news">All news</a>
            <?php endif; ?>
        </div>
        <?php if ($news): ?>
            <ul class="sf-posts">
                <?php foreach ($news as $post): ?>
                    <?php include __DIR__ . '/../parts/post_card.php'; ?>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <?= sf_gap($ctx, 'Your newest posts show here. Write one in News and offers.') ?>
        <?php endif; ?>
    </div>
</section>
