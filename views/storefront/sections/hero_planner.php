<?php
/**
 * Stage's first screen: plan your event. Photos of events the shop has done
 * run across the page with what clients said beside them, then the planner:
 * how many guests (the package that holds them lights up), the day (free or
 * taken, for packages booked by the day), the colours (a drawing of the hall
 * takes them), and extras (enough for everyone, suggested from the guests).
 * The plan adds up as it goes, and goes to the shop: "Hold the date" books
 * the day with its deposit when the package is booked by the day, else
 * "Send this plan" sends it on WhatsApp or the request form (storefront.js,
 * "Stage's planner").
 *
 * From hero.php: $plan, $headline, $text_html, $badge, $media, $is_video,
 * $hero_section, and the shell's $business, $ctx, $currency, $contact,
 * $reviews, $wa.
 */
$address  = !empty($contact['show_address']) ? sf_short_address($business) : '';
$packages = $plan['packages'];
$extras   = $plan['extras'];
[$first, $last] = booking_window(booking_settings($business));

// The photos: the owner's own, then each package's first
$shots = [];
foreach ($hero_section['slides'] as $rel) {
    if (!str_ends_with($rel, '.mp4') && is_file(BASE_PATH . '/' . $rel)) {
        $shots[] = $rel;
    }
}
foreach ($packages as $package) {
    if ($package['images'] && !in_array($package['images'][0], $shots, true)) {
        $shots[] = $package['images'][0];
    }
}
$shots = array_slice($shots, 0, 8);
// What clients said, the warm ones, set between the photos
$said = $reviews ? array_slice(array_values(array_filter($reviews['items'], fn($r) => (int)$r['rating'] >= 4 && trim((string)$r['comment']) !== '')), 0, 3) : [];

$themes = $hero_section['themes'] ? array_intersect_key(SF_THEMES, array_flip($hero_section['themes'])) : SF_THEMES;
$most   = max(array_map(fn($p) => $p['guests'], $packages));
$top    = (int)(ceil(max($most * ($packages[count($packages) - 1]['open'] ? 2 : 1.2), 100) / 50) * 50);
$start  = min($top, max(10, (int)(round($packages[0]['guests'] * 0.75 / 10) * 10)));

$data = [
    'first' => $first, 'last' => $last, 'top' => $top, 'start' => $start,
    'themes' => array_map(fn($t) => $t['colours'], $themes),
    'packages' => array_map(fn($p) => [
        'id' => $p['id'], 'name' => $p['name'], 'price' => $p['price'], 'guests' => $p['guests'], 'open' => $p['open'],
        'book' => ($p['booking']['mode'] ?? '') === 'day' ? ['url' => booking_page_path($p), 'free' => '/book/' . (int)$p['id'] . '/free',
                                                             'type' => $p['booking']['deposit_type'], 'value' => $p['booking']['deposit_value']] : null,
    ], $packages),
    'extras' => array_map(fn($x) => ['id' => $x['id'], 'name' => $x['name'], 'price' => $x['price'], 'per' => $x['per'], 'unit' => trim(sf_unit_text($x['unit']))], $extras),
];
$stars = fn(int $n) => '<span class="sf-plan__stars" aria-label="' . $n . ' out of 5">' . str_repeat('★', $n) . '<span aria-hidden="true">' . str_repeat('★', 5 - $n) . '</span></span>';
?>
<div class="sf-plan" data-plan="<?= e(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">
    <div class="sf-wrap sf-plan__head">
        <?php if ($address !== '' || $badge !== ''): ?>
            <p class="sf-hero__meta">
                <?php if ($address !== ''): ?><span><?= sf_icon('pin', 16) ?><?= e($address) ?></span><?php endif; ?>
                <?php if ($badge !== ''): ?><span class="sf-plan__badge"><?= e($badge) ?></span><?php endif; ?>
            </p>
        <?php endif; ?>
        <h1 class="sf-h1 sf-plan__title" id="sf-hero-title"><?= e($headline) ?></h1>
        <?= $text_html ?>
    </div>

    <?php if ($shots || $said): ?>
        <ul class="sf-plan__strip" aria-label="Events by <?= e($business['name']) ?>">
            <?php $s = 0; foreach ($shots as $i => $rel): ?>
                <li class="sf-plan__shot"><?= sf_img($rel, '(min-width: 960px) 360px, 74vw', '', 'sf-plan__img', $i === 0) ?></li>
                <?php if (($i === 0 || $i === 2 || $i === 4) && isset($said[$s])): $r = $said[$s++]; ?>
                    <li class="sf-plan__review">
                        <?= $stars((int)$r['rating']) ?>
                        <blockquote><p><?= e(mb_strimwidth(trim((string)$r['comment']), 0, 180, '…')) ?></p></blockquote>
                        <p class="sf-plan__who"><?= e(review_display_name((string)$r['customer_name'])) ?></p>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php for (; $s < count($said); $s++): $r = $said[$s]; ?>
                <li class="sf-plan__review">
                    <?= $stars((int)$r['rating']) ?>
                    <blockquote><p><?= e(mb_strimwidth(trim((string)$r['comment']), 0, 180, '…')) ?></p></blockquote>
                    <p class="sf-plan__who"><?= e(review_display_name((string)$r['customer_name'])) ?></p>
                </li>
            <?php endfor; ?>
        </ul>
    <?php endif; ?>

    <div class="sf-wrap">
        <form class="sf-planner" data-planner novalidate aria-labelledby="sf-planner-title">
            <h2 class="sf-planner__title" id="sf-planner-title"><?= sf_icon('sparkle', 20) ?>Plan your event</h2>

            <fieldset class="sf-planner__step">
                <legend class="sf-planner__q">How many guests?</legend>
                <div class="sf-guests">
                    <button type="button" class="sf-guests__btn" data-plan-step="-10" aria-label="Ten guests fewer"><?= sf_icon('minus', 20) ?></button>
                    <p class="sf-guests__n"><output data-plan-guests aria-live="polite"><?= $start ?></output><span>guests</span></p>
                    <button type="button" class="sf-guests__btn" data-plan-step="10" aria-label="Ten more guests"><?= sf_icon('plus', 20) ?></button>
                </div>
                <input class="sf-guests__range" type="range" min="10" max="<?= $top ?>" step="10" value="<?= $start ?>" data-plan-range aria-label="How many guests">
                <div class="sf-guests__ticks" aria-hidden="true">
                    <?php foreach ($packages as $p): ?>
                        <span style="--at: <?= round(min(100, ($p['guests'] - 10) / max(1, $top - 10) * 100), 2) ?>%"><?= e($p['name']) ?></span>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <div class="sf-planner__packages" role="radiogroup" aria-label="Package">
                <?php foreach ($packages as $p): ?>
                    <label class="sf-pack" data-pack="<?= (int)$p['id'] ?>">
                        <input type="radio" name="sf-package" value="<?= (int)$p['id'] ?>">
                        <span class="sf-pack__top">
                            <strong class="sf-pack__name"><?= e($p['name']) ?></strong>
                            <span class="sf-pack__price"><?= sf_price_html($p, $ctx, false) ?></span>
                        </span>
                        <span class="sf-pack__guests"><?= sf_icon('people', 15) ?><?= $p['open'] ? e(number_format($p['guests'])) . ' guests and more' : 'Up to ' . e(number_format($p['guests'])) . ' guests' ?></span>
                        <?php if ($p['description'] !== ''): ?><span class="sf-pack__desc"><?= e(mb_strimwidth($p['description'], 0, 140, '…')) ?></span><?php endif; ?>
                        <span class="sf-pack__fit" data-pack-fit></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <fieldset class="sf-planner__step">
                <legend class="sf-planner__q">When is it?</legend>
                <label class="sf-field sf-planner__date">
                    <span class="sf-sr">The day of your event</span>
                    <input type="date" min="<?= e($first) ?>" max="<?= e($last) ?>" data-plan-date>
                </label>
                <p class="sf-planner__said" data-plan-day aria-live="polite">Pick the day and we tell you if it's free.</p>
            </fieldset>

            <fieldset class="sf-planner__step">
                <legend class="sf-planner__q">Your colours</legend>
                <div class="sf-themes" role="radiogroup" aria-label="Colour theme">
                    <?php $t = 0; foreach ($themes as $key => $theme): ?>
                        <label class="sf-theme" style="--t1: <?= e($theme['colours'][0]) ?>; --t2: <?= e($theme['colours'][1]) ?>; --t3: <?= e($theme['colours'][2]) ?>">
                            <input type="radio" name="sf-theme" value="<?= e($key) ?>" data-theme-name="<?= e($theme['name']) ?>"<?= $t++ === 0 ? ' checked' : '' ?>>
                            <span class="sf-theme__dots" aria-hidden="true"><i></i><i></i><i></i></span>
                            <span class="sf-theme__name"><?= e($theme['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php $c = reset($themes)['colours']; ?>
                <div class="sf-decor" data-decor style="--t1: <?= e($c[0]) ?>; --t2: <?= e($c[1]) ?>; --t3: <?= e($c[2]) ?>">
                    <svg viewBox="0 0 320 170" role="img" aria-label="A drawing of the hall in the colours you picked">
                        <rect class="sf-decor__wall" width="320" height="170"/>
                        <rect class="sf-decor__floor" y="124" width="320" height="46"/>
                        <path class="sf-decor__wire" d="M0 14 Q80 40 160 22 T320 14"/>
                        <g class="sf-decor__bulbs">
                            <?php foreach ([[14, 18], [42, 26], [70, 30], [100, 29], [130, 25], [160, 22], [190, 21], [220, 23], [250, 21], [280, 17], [306, 15]] as [$x, $y]): ?><circle cx="<?= $x ?>" cy="<?= $y + 3 ?>" r="2.6"/><?php endforeach; ?>
                        </g>
                        <path class="sf-decor__arch" d="M104 124V74a56 56 0 0 1 112 0v50z"/>
                        <path class="sf-decor__drape" d="M116 124V76a44 44 0 0 1 88 0v48z"/>
                        <path class="sf-decor__fold" d="M134 124V60M148 124V44M160 124V40M172 124V44M186 124V60"/>
                        <g class="sf-decor__flowers">
                            <circle cx="106" cy="78" r="9"/><circle cx="115" cy="68" r="7"/><circle cx="99" cy="90" r="6"/>
                            <circle cx="214" cy="78" r="9"/><circle cx="205" cy="68" r="7"/><circle cx="221" cy="90" r="6"/>
                            <circle cx="108" cy="120" r="8"/><circle cx="98" cy="114" r="6"/><circle cx="212" cy="120" r="8"/><circle cx="222" cy="114" r="6"/>
                        </g>
                        <g class="sf-decor__petals">
                            <circle cx="110" cy="74" r="3"/><circle cx="210" cy="74" r="3"/><circle cx="104" cy="118" r="2.6"/><circle cx="216" cy="118" r="2.6"/>
                        </g>
                        <rect class="sf-decor__cloth" x="126" y="104" width="68" height="7" rx="1.5"/>
                        <rect class="sf-decor__skirt" x="130" y="111" width="60" height="13"/>
                        <?php foreach ([[46, 140], [274, 140], [92, 156], [228, 156], [160, 150]] as [$x, $y]): ?>
                            <g class="sf-decor__table">
                                <rect class="sf-decor__chair" x="<?= $x - 30 ?>" y="<?= $y - 9 ?>" width="7" height="12" rx="1.5"/>
                                <rect class="sf-decor__chair" x="<?= $x + 23 ?>" y="<?= $y - 9 ?>" width="7" height="12" rx="1.5"/>
                                <ellipse class="sf-decor__top" cx="<?= $x ?>" cy="<?= $y - 3 ?>" rx="22" ry="5"/>
                                <rect class="sf-decor__skirt" x="<?= $x - 20 ?>" y="<?= $y - 3 ?>" width="40" height="10" rx="3"/>
                                <circle class="sf-decor__bloom" cx="<?= $x ?>" cy="<?= $y - 9 ?>" r="4"/>
                            </g>
                        <?php endforeach; ?>
                    </svg>
                </div>
            </fieldset>

            <?php if ($extras): ?>
                <fieldset class="sf-planner__step">
                    <legend class="sf-planner__q">Add extras</legend>
                    <ul class="sf-extras">
                        <?php foreach ($extras as $x): ?>
                            <li class="sf-extra" data-extra="<?= (int)$x['id'] ?>">
                                <label class="sf-extra__pick">
                                    <input type="checkbox" data-extra-on>
                                    <span class="sf-extra__name"><?= e($x['name']) ?></span>
                                    <span class="sf-extra__each"><?= e(sf_money($x['price'], $currency)) ?><?= e(sf_unit_text($x['unit'])) ?><?= $x['per'] ? ' · for ' . (int)$x['per'] . ' guests' : '' ?></span>
                                </label>
                                <div class="sf-stepper" data-extra-qty hidden>
                                    <button type="button" data-extra-step="-1" aria-label="<?= e('One fewer ' . $x['name']) ?>"><?= sf_icon('minus', 16) ?></button>
                                    <output aria-live="polite">1</output>
                                    <button type="button" data-extra-step="1" aria-label="<?= e('One more ' . $x['name']) ?>"><?= sf_icon('plus', 16) ?></button>
                                </div>
                                <p class="sf-extra__said" data-extra-said></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </fieldset>
            <?php endif; ?>

            <div class="sf-planner__total">
                <p class="sf-planner__sum"><span>Your plan</span><strong data-plan-total>—</strong><small data-plan-sum></small></p>
                <div class="sf-planner__go">
                    <a class="sf-btn" data-plan-hold hidden href="#">Hold the date</a>
                    <a class="sf-btn sf-btn--outline" data-plan-send href="<?= e(sf_quote_href()) ?>"<?= $wa !== '' ? ' target="_blank" rel="noopener"' : '' ?>><?= $wa !== '' ? sf_icon('whatsapp', 20) : '' ?><span>Send this plan</span></a>
                </div>
            </div>
            <p class="sf-planner__note" data-plan-note></p>
        </form>
    </div>
</div>
