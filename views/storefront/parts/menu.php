<?php
/**
 * The menu: the shop's pages, then how to reach it. A sheet over the page,
 * opened by the header's menu button (storefront.js); wider screens show the
 * same pages as links in the header too.
 *
 * From shell.php: $nav_pages, $nav_here, $socials, $catalogue, $sections.
 */
$menu_count   = count($catalogue['items']);
$menu_address = !empty($contact['show_address']) || $map_on ? sf_short_address($business) : '';
$menu_notes   = [
    'home'      => '',
    'catalogue' => $menu_count ? $menu_count . ' ' . ($menu_count === 1 ? 'item' : 'items') : '',
    'news'      => '',
    'contact'   => $quote_on ? ($sections['quote']['title'] !== '' ? $sections['quote']['title'] : $labels['quote']) : 'WhatsApp, phone and email',
];
$menu_networks = ['instagram' => 'Instagram', 'facebook' => 'Facebook', 'tiktok' => 'TikTok', 'twitter' => 'X'];
?>
<dialog class="sf-menu" id="sf-menu" aria-labelledby="sf-menu-title">
    <div class="sf-menu__panel">
        <div class="sf-menu__head">
            <p class="sf-menu__title" id="sf-menu-title"><?= e($business['name']) ?></p>
            <button type="button" class="sf-iconbtn sf-iconbtn--bare" data-close aria-label="Close the menu"><?= sf_icon('close', 24) ?></button>
        </div>
        <nav class="sf-menu__nav" aria-label="Pages of the shop">
            <?php foreach ($nav_pages as $menu_key => $menu_label): ?>
                <a href="<?= e(sf_page_href($menu_key)) ?>"<?= $menu_key === $nav_here ? ' aria-current="page"' : '' ?>>
                    <span><?= e($menu_label) ?></span>
                    <?php if ($menu_notes[$menu_key] !== ''): ?><small><?= e($menu_notes[$menu_key]) ?></small><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sf-menu__foot">
            <?php if ($wa !== ''): ?>
                <a class="sf-btn sf-btn--block" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener"><?= sf_icon('whatsapp', 20) ?><span>Chat on WhatsApp</span></a>
            <?php endif; ?>
            <?php if ($socials): ?>
                <div class="sf-menu__socials">
                    <?php foreach ($socials as $network => $url): ?>
                        <a class="sf-iconbtn" href="<?= e($url) ?>" target="_blank" rel="noopener" aria-label="<?= e($menu_networks[$network]) ?>"><?= sf_icon($network === 'twitter' ? 'x' : $network, 20) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($menu_address !== ''): ?>
                <p class="sf-menu__address"><?= sf_icon('pin', 18) ?><span><?= e($menu_address) ?></span></p>
            <?php endif; ?>
        </div>
    </div>
</dialog>
