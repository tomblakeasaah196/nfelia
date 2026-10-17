<?php
/**
 * A YouTube video. Nothing loads from YouTube until someone taps play, and
 * then from youtube-nocookie.com.
 */
$video_id = sf_youtube_id($section['url']);
if ($video_id === null && $mode === 'live') {
    return;
}
$video_title = $section['title'] !== '' ? $section['title'] : '';
?>
<section class="sf-section sf-video" id="sf-video" aria-label="<?= e($video_title !== '' ? $video_title : 'Video') ?>">
    <div class="sf-wrap">
        <?php if ($video_title !== ''): ?><h2 class="sf-h2"><?= e($video_title) ?></h2><?php endif; ?>
        <?php if ($video_id !== null): ?>
            <div class="sf-video__frame">
                <button type="button" class="sf-video__play" data-youtube="<?= e($video_id) ?>" aria-label="Play the video">
                    <img src="https://i.ytimg.com/vi/<?= e($video_id) ?>/hqdefault.jpg" alt="" loading="lazy" decoding="async" width="480" height="360">
                    <span class="sf-video__icon"><?= sf_icon('play', 28) ?></span>
                </button>
            </div>
        <?php else: ?>
            <?= sf_gap($ctx, 'Paste a YouTube link in Shop Studio and your video shows here.') ?>
        <?php endif; ?>
    </div>
</section>
