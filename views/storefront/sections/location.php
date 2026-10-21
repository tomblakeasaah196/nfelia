<?php
/**
 * Map and directions: where the shop is, at the bottom of the home page and
 * on the contact page. A picture of the map in the shop's colours
 * (app/helpers/maps.php), the address, the owner's note on how to find them,
 * and Get directions. It shows once the owner has placed the pin in Shop
 * Studio; without a map picture (no key yet) the rest still shows.
 */
$loc = $section;
if ($loc['lat'] === null) {
    if ($mode === 'live') {
        return;
    }
}
$loc_title   = $loc['title'] !== '' ? $loc['title'] : $labels['location'];
$loc_address = trim((string)($business['address'] ?? ''));
$loc_picture = $loc['lat'] !== null
    ? maps_picture((int)$business['id'], (float)$loc['lat'], (float)$loc['lng'], (int)$loc['zoom'], $theme['palette']['dark'], $theme['palette']['accent'])
    : null;
$loc_go = $loc['lat'] !== null ? maps_directions_url((float)$loc['lat'], (float)$loc['lng']) : '';
?>
<section class="sf-section sf-location" id="sf-location" aria-labelledby="sf-location-title">
    <div class="sf-wrap">
        <div class="sf-location__card<?= $loc_picture ? '' : ' sf-location__card--plain' ?>">
            <?php if ($loc_picture): ?>
                <a class="sf-location__map" href="<?= e($loc_go) ?>" target="_blank" rel="noopener" aria-label="Open <?= e($business['name']) ?> in Google Maps">
                    <img src="<?= e($loc_picture['src']) ?>" srcset="<?= e($loc_picture['srcset']) ?>" sizes="(min-width: 1080px) 640px, 100vw"
                         width="<?= (int)$loc_picture['w'] ?>" height="<?= (int)$loc_picture['h'] ?>" alt="Map showing where <?= e($business['name']) ?> is" loading="lazy" decoding="async">
                    <span class="sf-location__credit">Powered by Geoapify | © OpenMapTiles © OpenStreetMap contributors</span>
                </a>
            <?php endif; ?>
            <div class="sf-location__body">
                <h2 class="sf-h2" id="sf-location-title"><?= e($loc_title) ?></h2>
                <?php if ($loc['lat'] === null): ?>
                    <?= sf_gap($ctx, 'Place your pin in Shop Studio and a map of where to find you shows here, with Get directions.') ?>
                <?php else: ?>
                    <?php if ($loc_address !== ''): ?>
                        <p class="sf-location__address"><?= sf_icon('pin', 20) ?><span><?= nl2br(e($loc_address), false) ?></span></p>
                    <?php endif; ?>
                    <?php if ($loc['note'] !== ''): ?>
                        <?= sf_paragraphs($loc['note'], 'sf-location__note') ?>
                    <?php endif; ?>
                    <a class="sf-btn sf-location__go" href="<?= e($loc_go) ?>" target="_blank" rel="noopener"><?= sf_icon('route', 20) ?><span>Get directions</span></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
