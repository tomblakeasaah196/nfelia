<?php
/**
 * Questions people ask, each one opening to its answer.
 */
$faq_items = array_values(array_filter($section['items'], fn($i) => $i['q'] !== '' && $i['a'] !== ''));
if (!$faq_items && $mode === 'live') {
    return;
}
?>
<section class="sf-section sf-faq" id="sf-faq" aria-labelledby="sf-faq-title">
    <div class="sf-wrap sf-faq__inner">
        <h2 class="sf-h2" id="sf-faq-title"><?= e($section['title'] !== '' ? $section['title'] : $labels['faq']) ?></h2>
        <?php if ($faq_items): ?>
            <div class="sf-faq__list">
                <?php foreach ($faq_items as $item): ?>
                    <details class="sf-faq__item">
                        <summary><span><?= e($item['q']) ?></span><?= sf_icon('chevron', 20, 'sf-faq__chev') ?></summary>
                        <div class="sf-prose"><?= sf_paragraphs($item['a']) ?></div>
                    </details>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <?= sf_gap($ctx, 'Add the questions customers ask you most, with your answers.') ?>
        <?php endif; ?>
    </div>
</section>
