<?php
/**
 * Storefront header: the shop's name (and logo), its pages and the bag. On
 * a phone the pages are in the menu (parts/menu.php); wider screens show
 * them as links. On the shop's other pages the name leads back home.
 * Atelier centres the name, with the menu on the left; over its cover the
 * header is clear until the cover scrolls away (storefront.js).
 * Market's header is a slim band in its colour; its headline is painted on
 * the price board below (sections/hero_board.php).
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
<?php if ($settings['look'] === 'market'): ?>
<header class="sf-header sf-header--band sf-header--slim" data-header>
    <div class="sf-wrap sf-header__inner">
        <div class="sf-brand">
            <?= $brand_open ?><?= $brand_mark ?>
            <p class="sf-brand__name"><?= e($business['name']) ?></p><?= $brand_shut ?>
        </div>
        <?= $nav_links ?>
        <?= $bag_button ?>
        <?= $menu_button ?>
    </div>
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
