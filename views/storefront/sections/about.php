<?php
/**
 * About us: the owner's own words.
 */
if ($section['text'] === '' && $mode === 'live') {
    return;
}
?>
<section class="sf-section sf-about" id="sf-about" aria-labelledby="sf-about-title">
    <div class="sf-wrap sf-about__inner">
        <h2 class="sf-h2" id="sf-about-title"><?= e($section['title'] !== '' ? $section['title'] : $labels['about']) ?></h2>
        <div class="sf-prose">
            <?= $section['text'] !== '' ? sf_paragraphs($section['text']) : sf_gap($ctx, 'Tell people who you are and what you make. Add it in Shop Studio.') ?>
        </div>
    </div>
</section>
