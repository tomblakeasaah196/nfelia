<?php
/**
 * Buka's first screen: the dishes on a table that turns. The dish in front
 * is big, the next ones wait round the sides; a swipe, a drag, the arrows or
 * a dot brings the next one round, and its name, price and Add follow
 * (storefront.js, "The turntable"). A table with fewer than six dishes goes
 * round with them more than once, so it is never half empty. Without a dish,
 * the owner's photo on a plate, else the look's enamel plate with the shop's
 * initials.
 *
 * From hero.php: $hero_section, $headline, $primary, $wa_button, $badge,
 * $text_html, $media, $is_video, and the shell's $business, $catalogue, $ctx.
 */
$dishes = storefront_hero_picks($catalogue, $hero_section);
$count  = count($dishes);
// Places round the table: at least six, a whole number of rounds
$slots  = $count > 1 ? (int)(ceil(6 / $count) * $count) : max(1, $count);
// How far the table's middle is from a dish's, in plates: the next dish
// waits round the side, its edge just under the one in front
$reach  = $slots > 1 ? round(0.8 / sin(2 * M_PI / $slots), 3) : 1;
$hours  = sf_opening_hours($business);
$turn_label = 'Dishes from ' . $business['name'];
?>
<div class="sf-turntable" data-turntable data-count="<?= $count ?>" style="--slots: <?= $slots ?>; --reach: <?= $reach ?>">
    <div class="sf-wrap sf-turntable__head">
        <?php if ($hours): ?>
            <p class="sf-open" data-hours="<?= e(json_encode($hours)) ?>" data-tz="<?= e(date_default_timezone_get()) ?>">
                <span class="sf-open__dot" aria-hidden="true"></span><span data-open-text><?= e(sf_hours_today($hours)) ?></span>
            </p>
        <?php endif; ?>
        <h1 class="sf-h1 sf-turntable__title" id="sf-hero-title"><?= e($headline) ?></h1>
    </div>

    <div class="sf-turntable__stage"<?= $count > 1 ? ' role="group" aria-roledescription="carousel" aria-label="' . e($turn_label) . '"' : '' ?>>
        <div class="sf-turntable__cloth" aria-hidden="true"></div>
        <div class="sf-turntable__wheel" data-turn-wheel>
            <?php if (!$count && $media !== '' && !$is_video): ?>
                <div class="sf-turntable__plate is-front" style="--i: 0"><div class="sf-turntable__dish"><span class="sf-turntable__food"><span class="sf-media"><?= $media ?></span></span></div></div>
            <?php elseif (!$count): ?>
                <div class="sf-turntable__plate is-front" style="--i: 0"><div class="sf-turntable__dish sf-turntable__dish--drawn"><?= sf_motif('buka', $ctx) ?></div></div>
            <?php endif; ?>
            <?php for ($slot = 0; $slot < ($count ? $slots : 0); $slot++):
                $item = $dishes[$slot % $count];
            ?>
                <div class="sf-turntable__plate<?= $slot === 0 ? ' is-front' : '' ?>" style="--i: <?= $slot ?>" data-turn-slot="<?= $slot % $count ?>" aria-hidden="true">
                    <div class="sf-turntable__dish"><?= sf_open_link($item, sf_item_media($item, '(min-width: 960px) 440px, 80vw', $slot === 0), 'sf-turntable__food', true) ?></div>
                </div>
            <?php endfor; ?>
        </div>
        <?php if ($count): ?>
            <svg class="sf-turntable__steam" viewBox="0 0 120 110" aria-hidden="true" focusable="false">
                <path d="M34 104c-12-14 12-24 0-40s10-26 2-44"/><path d="M60 100c-12-16 12-26 0-44s10-28 2-48"/><path d="M86 104c-12-14 12-24 0-40s10-26 2-44"/>
            </svg>
        <?php endif; ?>
        <?php if ($badge !== ''): ?><p class="sf-roundel sf-turntable__roundel"><?= e($badge) ?></p><?php endif; ?>
    </div>

    <div class="sf-wrap sf-turntable__info">
        <?php if ($count): ?>
            <div class="sf-turntable__cards" aria-live="polite">
                <?php foreach ($dishes as $i => $item): ?>
                    <div class="sf-turntable__card<?= $i ? '' : ' is-on' ?>" data-turn-card="<?= $i ?>">
                        <h2 class="sf-turntable__name"><?= sf_open_link($item, e($item['name'])) ?></h2>
                        <?php if ($item['description'] !== ''): ?><p class="sf-turntable__desc"><?= e($item['description']) ?></p><?php endif; ?>
                        <div class="sf-turntable__buy">
                            <p class="sf-turntable__price"><?= sf_price_html($item, $ctx) ?></p>
                            <?= sf_add_button($item, 'Add', 'sf-btn sf-turntable__add') ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($count > 1): ?>
            <div class="sf-turntable__controls">
                <button type="button" class="sf-turntable__arrow" data-turn-step="-1" aria-label="Turn back a dish"><?= sf_icon('left', 22) ?></button>
                <div class="sf-turntable__dots">
                    <?php foreach ($dishes as $i => $item): ?>
                        <button type="button" class="sf-turntable__dot" data-turn-to="<?= $i ?>" aria-label="<?= e('Show ' . $item['name'] . ', ' . ($i + 1) . ' of ' . $count) ?>"<?= $i === 0 ? ' aria-current="true"' : '' ?>><span></span></button>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="sf-turntable__arrow" data-turn-step="1" aria-label="Turn to the next dish"><?= sf_icon('right', 22) ?></button>
            </div>
            <p class="sf-turntable__hint" data-turn-hint><?= sf_icon('turn', 18) ?><span>Swipe the table for more</span></p>
        <?php endif; ?>
    </div>

    <div class="sf-wrap sf-turntable__more">
        <?= $text_html ?>
        <?php if ($primary !== '' || $wa_button !== ''): ?>
            <div class="sf-hero__actions"><?= $primary ?><?= $wa_button ?></div>
        <?php endif; ?>
    </div>
</div>
