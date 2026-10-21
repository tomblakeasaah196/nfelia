<?php
/**
 * Custom and bulk orders, on the home page: the title, its line and one
 * button to the request form, which is on the contact page (/contact).
 */
$cta_title = $section['title'] !== '' ? $section['title'] : $labels['quote'];
$cta_text  = $section['text'] !== '' ? $section['text'] : 'Tell us what you need and we will reply with a price.';
?>
<section class="sf-section sf-quote-cta" id="sf-quote" aria-labelledby="sf-quote-title">
    <div class="sf-wrap sf-quote-cta__inner">
        <div class="sf-quote-cta__words">
            <h2 class="sf-h2" id="sf-quote-title"><?= e($cta_title) ?></h2>
            <?= sf_paragraphs($cta_text, 'sf-quote-cta__text') ?>
        </div>
        <a class="sf-btn sf-quote-cta__btn" href="<?= e(sf_quote_href()) ?>">Start your request</a>
    </div>
</section>
