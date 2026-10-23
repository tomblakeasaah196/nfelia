<?php
/**
 * Volt's first screen: each device under a spotlight, the way a phone is
 * shown in a good shop. The device stands in a pool of light on a photo
 * studio's sweep (white photo backgrounds melt into it), with what a buyer
 * checks first pointed out round it: storage, battery health, what works.
 * Under it, its condition, a battery meter, its price, Add and Ask. A swipe,
 * the arrows or a dot brings the next device (storefront.js, "The
 * spotlight"). The owner's sticker becomes a stamp ("Tested before you
 * pay"). A shop that also repairs gets a strip to its repair desk.
 * sf_device_facts() reads the facts from what the owner already writes.
 *
 * From hero.php: $devices, $headline, $text_html, $badge, and the shell's
 * $business, $catalogue, $ctx, $contact, $wa.
 */
$count   = count($devices);
$address = !empty($contact['show_address']) ? sf_short_address($business) : '';
$repairs = array_values(array_filter($catalogue['items'], fn($i) => $i['is_service']));
$repair_from = $repairs ? min(array_map(fn($i) => $i['price'] > 0 ? $i['price'] : PHP_FLOAT_MAX, $repairs)) : 0;
?>
<div class="sf-spot" data-spotlight data-count="<?= $count ?>">
    <div class="sf-wrap sf-spot__head">
        <?php if ($address !== ''): ?><p class="sf-hero__meta"><span><?= sf_icon('pin', 16) ?><?= e($address) ?></span></p><?php endif; ?>
        <h1 class="sf-h1 sf-spot__title" id="sf-hero-title"><?= e($headline) ?></h1>
        <?= $text_html ?>
    </div>

    <div class="sf-spot__stage"<?= $count > 1 ? ' role="group" aria-roledescription="carousel" aria-label="' . e('Devices from ' . $business['name']) . '"' : '' ?>>
        <span class="sf-spot__light" aria-hidden="true"></span>
        <?php foreach ($devices as $i => $item):
            $facts = sf_device_facts($item);
        ?>
            <figure class="sf-spot__device<?= $i === 0 ? ' is-on' : '' ?>" data-spot="<?= $i ?>" aria-hidden="true">
                <?= sf_open_link($item, sf_item_media($item, '(min-width: 960px) 360px, 56vw', $i === 0), 'sf-spot__photo', true) ?>
                <span class="sf-spot__floor"></span>
                <?php if ($facts['specs']): ?>
                    <ul class="sf-spot__callouts">
                        <?php foreach ($facts['specs'] as $n => $spec): ?>
                            <li class="sf-callout sf-callout--<?= $n % 2 ? 'r' : 'l' ?>" style="--n: <?= $n ?>">
                                <span class="sf-callout__chip"><?php if ($facts['battery'] !== null && stripos($spec, 'battery') !== false): ?><span class="sf-battery sf-battery--sm" style="--level: <?= $facts['battery'] ?>%"></span><?php endif; ?><?= e($spec) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </figure>
        <?php endforeach; ?>
        <?php if ($badge !== ''): ?><p class="sf-spot__stamp"><span><?= e($badge) ?></span><?= sf_icon('check', 22) ?></p><?php endif; ?>
        <?php if ($count > 1): ?>
            <button type="button" class="sf-spot__arrow sf-spot__arrow--back" data-spot-step="-1" aria-label="The device before"><?= sf_icon('left', 22) ?></button>
            <button type="button" class="sf-spot__arrow sf-spot__arrow--next" data-spot-step="1" aria-label="The next device"><?= sf_icon('right', 22) ?></button>
        <?php endif; ?>
    </div>

    <div class="sf-wrap sf-spot__info">
        <div class="sf-spot__cards" aria-live="polite">
            <?php foreach ($devices as $i => $item):
                $facts = sf_device_facts($item);
            ?>
                <div class="sf-spot__card<?= $i === 0 ? ' is-on' : '' ?>" data-spot-card="<?= $i ?>">
                    <?= sf_grade_badge($facts['grade']) ?>
                    <h2 class="sf-spot__name"><?= sf_open_link($item, e($item['name'])) ?></h2>
                    <?= sf_battery_meter($facts['battery']) ?>
                    <div class="sf-spot__buy">
                        <p class="sf-spot__price"><?= sf_price_html($item, $ctx) ?></p>
                        <div class="sf-spot__actions"><?= sf_add_button($item, 'Add to bag', 'sf-btn sf-btn--ink', false) ?><?= sf_wa_ask($item, $ctx) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($count > 1): ?>
            <div class="sf-spot__dots">
                <?php foreach ($devices as $i => $item): ?>
                    <button type="button" class="sf-spot__dot" data-spot-to="<?= $i ?>" aria-label="<?= e('Show ' . $item['name'] . ', ' . ($i + 1) . ' of ' . $count) ?>"<?= $i === 0 ? ' aria-current="true"' : '' ?>><span></span></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($repairs): ?>
        <div class="sf-wrap">
            <a class="sf-spot__repairs" href="<?= e(sf_page_href('catalogue', '#sf-services')) ?>">
                <span class="sf-spot__repairs-icon"><?= sf_icon('tool', 24) ?></span>
                <span class="sf-spot__repairs-words">
                    <strong>Repairs<?= $repair_from > 0 && $repair_from < PHP_FLOAT_MAX ? ' from ' . e(sf_money($repair_from, $ctx['currency'])) : '' ?></strong>
                    <span><?= e(implode(' · ', array_map(fn($i) => $i['name'], array_slice($repairs, 0, 3)))) ?></span>
                </span>
                <?= sf_icon('right', 20) ?>
            </a>
        </div>
    <?php endif; ?>
</div>
