<?php
/**
 * Storefront header: the shop's name (and logo), and the bag.
 * Market draws its price-list band here, with the headline in it, so its
 * hero partial adds only a photo.
 */
$logo     = (string)($business['logo_path'] ?? '');
$logo_ok  = $logo !== '' && is_file(BASE_PATH . '/' . $logo) && str_starts_with($logo, 'uploads/');
$address  = sf_short_address($business);
$bag_icon = match ($settings['look']) { 'harvest' => 'basket', 'market' => 'list', default => 'bag' };
$bag_name = $settings['look'] === 'market' ? 'Your order' : 'Your bag';
$sub_line = in_array($settings['look'], ['buka', 'harvest', 'desk'], true) ? $address : '';

$bag_button = '<button type="button" class="sf-bagbtn" data-open-bag aria-label="' . e($bag_name) . '">'
            . sf_icon($bag_icon, 23) . '<span class="sf-badge" data-bag-count hidden>0</span></button>';

$brand_mark = '';
if ($logo_ok) {
    $brand_mark = '<img class="sf-brand__logo" src="' . e(sf_media_url($logo)) . '" alt="" height="40">';
} elseif ($settings['look'] === 'buka') {
    $brand_mark = '<span class="sf-brand__mono" aria-hidden="true">' . e(sf_initials($business['name'])) . '</span>';
}
?>
<?php if ($settings['look'] === 'market'):
    $market_headline = $hero['headline'] !== '' ? $hero['headline'] : (string)$business['name'];
?>
<header class="sf-header sf-header--band">
    <div class="sf-wrap sf-header__inner">
        <div class="sf-brand">
            <?= $brand_mark ?>
            <p class="sf-brand__name"><?= e($business['name']) ?></p>
        </div>
        <?= $bag_button ?>
    </div>
    <div class="sf-wrap sf-header__hero">
        <?php if ($market_headline !== (string)$business['name']): ?>
            <h1 class="sf-h1" id="sf-hero-title"><?= e($market_headline) ?></h1>
        <?php else: ?>
            <h1 class="sf-sr" id="sf-hero-title"><?= e($market_headline) ?></h1>
        <?php endif; ?>
        <?php if ($hero['text'] !== ''): ?>
            <?= sf_paragraphs($hero['text'], 'sf-header__text') ?>
        <?php elseif ($address !== ''): ?>
            <p class="sf-header__text"><?= e($address) ?></p>
        <?php endif; ?>
        <?php if ($hero['badge'] !== ''): ?>
            <p class="sf-header__chip"><?= sf_icon('tag', 16) ?><?= e($hero['badge']) ?></p>
        <?php elseif ($catalogue['updated_at']): ?>
            <p class="sf-header__chip"><?= sf_icon('clock', 16) ?>Prices updated <?= e(date('j F Y', strtotime($catalogue['updated_at']))) ?></p>
        <?php endif; ?>
    </div>
</header>
<?php else: ?>
<header class="sf-header">
    <div class="sf-wrap sf-header__inner">
        <?php if ($settings['look'] === 'atelier'): ?>
            <?php if ($wa !== ''): ?>
                <a class="sf-iconbtn sf-iconbtn--bare" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener" aria-label="Chat with <?= e($business['name']) ?> on WhatsApp"><?= sf_icon('whatsapp', 22) ?></a>
            <?php else: ?>
                <span aria-hidden="true"></span>
            <?php endif; ?>
        <?php endif; ?>
        <div class="sf-brand">
            <?= $brand_mark ?>
            <div class="sf-brand__text">
                <p class="sf-brand__name"><?= e($business['name']) ?></p>
                <?php if ($sub_line !== ''): ?>
                    <p class="sf-brand__sub"><?= e($sub_line) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?= $bag_button ?>
    </div>
</header>
<?php endif; ?>
