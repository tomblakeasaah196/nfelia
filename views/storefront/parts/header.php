<?php
/**
 * Storefront header: the shop's name (and logo), its pages and the bag. On
 * a phone the pages are in the menu (parts/menu.php); wider screens show
 * them as links. On the shop's other pages the name leads back home.
 * Atelier centres the name, with the menu on the left; over its cover the
 * header is clear until the cover scrolls away (storefront.js).
 * Market draws its price-list band here, with the headline in it, so its
 * hero partial adds only a photo; other pages get the band without it.
 */
$logo     = (string)($business['logo_path'] ?? '');
$logo_ok  = $logo !== '' && is_file(BASE_PATH . '/' . $logo) && str_starts_with($logo, 'uploads/');
$address  = !empty($contact['show_address']) ? sf_short_address($business) : '';   // hidden means hidden (W3)
// Market's order is a sheet, not a list: a list beside the menu reads as two menus
$bag_icon = match ($settings['look']) { 'harvest' => 'basket', 'market' => 'clipboard', default => 'bag' };
$bag_name = $settings['look'] === 'market' ? 'Your order' : 'Your bag';
$sub_line = in_array($settings['look'], ['buka', 'harvest', 'desk'], true) ? $address : '';
$centred  = $settings['look'] === 'atelier';

$bag_button = '<button type="button" class="sf-bagbtn" data-open-bag aria-label="' . e($bag_name) . '">'
            . sf_icon($bag_icon, 23) . '<span class="sf-badge" data-bag-count hidden>0</span></button>';
$menu_button = '<button type="button" class="sf-menubtn" data-open-menu aria-haspopup="dialog" aria-controls="sf-menu" aria-label="Menu">'
             . sf_icon('menu', 24) . '</button>';

$home_link  = $kind !== 'shop' && $mode !== 'look';
$brand_open = $home_link ? '<a class="sf-brand__home" href="' . e(sf_page_href('home')) . '">' : '';
$brand_shut = $home_link ? '<span class="sf-sr"> — back to the shop</span></a>' : '';

$brand_mark = '';
if ($logo_ok) {
    $brand_mark = '<img class="sf-brand__logo" src="' . e(sf_media_url($logo)) . '" alt="" height="40">';
} elseif ($settings['look'] === 'buka') {
    $brand_mark = '<span class="sf-brand__mono" aria-hidden="true">' . e(sf_initials($business['name'])) . '</span>';
}

// The shop's pages as links, for wider screens
$nav_links = '<nav class="sf-nav" aria-label="Pages of the shop">';
foreach ($nav_pages as $nav_key => $nav_label) {
    $nav_links .= '<a href="' . e(sf_page_href($nav_key)) . '"' . ($nav_key === $nav_here ? ' aria-current="page"' : '') . '>'
                . e($nav_key === 'news' ? 'News' : $nav_label) . '</a>';
}
$nav_links .= '</nav>';
?>
<?php if ($settings['look'] === 'market'):
    $market_headline = $hero['headline'] !== '' ? $hero['headline'] : (string)$business['name'];
?>
<header class="sf-header sf-header--band<?= $kind !== 'shop' ? ' sf-header--slim' : '' ?>" data-header>
    <div class="sf-wrap sf-header__inner">
        <div class="sf-brand">
            <?= $brand_open ?><?= $brand_mark ?>
            <p class="sf-brand__name"><?= e($business['name']) ?></p><?= $brand_shut ?>
        </div>
        <?= $nav_links ?>
        <?= $bag_button ?>
        <?= $menu_button ?>
    </div>
    <?php if ($kind === 'shop'): ?>
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
    <?php endif; ?>
</header>
<?php else: ?>
<header class="sf-header<?= $centred ? ' sf-header--centred' : '' ?><?= $cover ? ' sf-header--over' : '' ?>" data-header>
    <div class="sf-wrap sf-header__inner">
        <?php if ($centred): ?><?= $menu_button ?><?= $nav_links ?><?php endif; ?>
        <div class="sf-brand">
            <?= $brand_open ?><?= $brand_mark ?>
            <div class="sf-brand__text">
                <p class="sf-brand__name"><?= e($business['name']) ?></p>
                <?php if ($sub_line !== ''): ?>
                    <p class="sf-brand__sub"><?= e($sub_line) ?></p>
                <?php endif; ?>
            </div><?= $brand_shut ?>
        </div>
        <?php if (!$centred): ?><?= $nav_links ?><?php endif; ?>
        <?= $bag_button ?>
        <?php if (!$centred): ?><?= $menu_button ?><?php endif; ?>
    </div>
</header>
<?php endif; ?>
