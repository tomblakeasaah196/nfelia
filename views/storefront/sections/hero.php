<?php
/**
 * The top of the shop: headline, a line or two, the main button and a photo
 * (or the look's own drawing until there is one). Each look lays it out its
 * own way; see storefront_looks()['…']['hero'].
 */
$hero_section = $section;
$variant  = $look['hero'];
$headline = $hero_section['headline'] !== '' ? $hero_section['headline'] : (string)$business['name'];
$button   = $hero_section['button'] !== '' ? $hero_section['button'] : $labels['button'];
$has_items = (bool)$catalogue['items'];
$cat_on   = $sections['catalogue']['on'] && $has_items;
// The button goes to the shop's list, else its request form
$go       = $cat_on ? sf_page_href('catalogue') : ($quote_on ? sf_quote_href() : '');
$quote_title = $sections['quote']['title'] !== '' ? $sections['quote']['title'] : $labels['quote'];

// The photo or video, if the owner has one
$image     = $hero_section['image'];
$media     = '';
$is_video  = false;
if ($image !== '' && is_file(BASE_PATH . '/' . $image)) {
    if (str_ends_with($image, '.mp4')) {
        $is_video = true;
        $media = '<video class="sf-hero__video" muted loop playsinline preload="none" data-src="' . e(sf_media_url($image)) . '" aria-hidden="true"></video>';
    } elseif (str_ends_with($image, '.gif')) {
        $media = '<img class="sf-hero__img" src="' . e(sf_media_url($image)) . '" alt="" fetchpriority="high">';
    } else {
        $media = sf_img($image, '(min-width: 1080px) 600px, (min-width: 720px) 50vw, 100vw', '', 'sf-hero__img', true);
    }
}
// Looks with a drawing show it until there is a photo (and under a video
// until it plays)
$motif = in_array($variant, ['plate', 'soft', 'arch', 'stamp', 'shelf', 'facade'], true) && ($media === '' || $is_video)
    ? sf_motif($settings['look'], $ctx) : '';
$frame = $media !== '' || $motif !== '' ? $motif . $media : '';

$primary = $go !== ''
    ? '<a class="sf-btn" href="' . $go . '">' . e($button) . '</a>'
    : ($wa !== '' ? '<a class="sf-btn" href="https://wa.me/' . e($wa) . '" target="_blank" rel="noopener">' . sf_icon('whatsapp', 20) . '<span>Chat on WhatsApp</span></a>' : '');
$wa_button = $wa !== '' && $go !== ''
    ? '<a class="sf-btn sf-btn--outline" href="https://wa.me/' . e($wa) . '?text=' . e(rawurlencode('Hello ' . $business['name'] . ', I found your shop and I would like to order.')) . '" target="_blank" rel="noopener">' . sf_icon('whatsapp', 20) . '<span>Order on WhatsApp</span></a>'
    : '';
$badge = $hero_section['badge'];
if ($badge === '' && $variant === 'plate' && $catalogue['min_price']) {
    $badge = 'From ' . sf_money((float)$catalogue['min_price'], $currency);
}
$text_html = $hero_section['text'] !== '' ? sf_paragraphs($hero_section['text'], 'sf-lead') : '';
?>
<section class="sf-hero sf-hero--<?= e($variant) ?>" id="sf-hero" aria-labelledby="sf-hero-title">
<?php switch ($variant):

    case 'cover':
        // Atelier: the photos fill the first screen and take turns, with the
        // words on a dark fade at the bottom (storefront.js runs the slides)
        $slides = array_values(array_filter($hero_section['slides'], fn($rel) => is_file(BASE_PATH . '/' . $rel)));
        $count  = count($slides);
        $second = $quote_on && $cat_on
            ? '<a class="sf-btn sf-btn--glass" href="' . e(sf_quote_href()) . '">' . e($quote_title) . '</a>'
            : ($wa !== '' ? '<a class="sf-btn sf-btn--glass" href="https://wa.me/' . e($wa) . '?text=' . e(rawurlencode('Hello ' . $business['name'] . ', I found your shop and I would like to order.')) . '" target="_blank" rel="noopener">' . sf_icon('whatsapp', 20) . '<span>Order on WhatsApp</span></a>' : '');
    ?>
    <div class="sf-cover" data-cover<?= $count > 1 ? ' data-interval="6500"' : '' ?>>
        <div class="sf-cover__media"<?= $count > 1 ? ' role="group" aria-roledescription="carousel" aria-label="' . e('Photos from ' . $business['name']) . '"' : '' ?>>
            <?php if (!$slides): ?>
                <div class="sf-cover__slide sf-cover__slide--drawn is-on"><?= sf_motif($settings['look'], $ctx) ?></div>
            <?php endif; ?>
            <?php foreach ($slides as $i => $rel): ?>
                <div class="sf-cover__slide<?= $i === 0 ? ' is-on' : '' ?>"<?= $count > 1 ? ' role="group" aria-roledescription="slide" aria-label="' . ($i + 1) . ' of ' . $count . '"' : '' ?><?= $i ? ' aria-hidden="true"' : '' ?>>
                    <?= sf_cover_media($rel, $i) ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="sf-cover__fade" aria-hidden="true"></div>
        <div class="sf-wrap sf-cover__body">
            <?php if ($badge !== ''): ?><p class="sf-cover__badge"><?= e($badge) ?></p><?php endif; ?>
            <h1 class="sf-h1 sf-cover__title" id="sf-hero-title"><?= e($headline) ?></h1>
            <?= $hero_section['text'] !== '' ? sf_paragraphs($hero_section['text'], 'sf-cover__lead') : '' ?>
            <?php if ($primary !== '' || $second !== ''): ?>
                <div class="sf-cover__actions"><?= $primary ?><?= $second ?></div>
            <?php endif; ?>
            <?php if ($count > 1): ?>
                <div class="sf-cover__bars">
                    <?php foreach ($slides as $i => $rel): ?>
                        <button type="button" class="sf-cover__bar" data-slide-to="<?= $i ?>" aria-label="Show photo <?= $i + 1 ?> of <?= $count ?>"<?= $i === 0 ? ' aria-current="true"' : '' ?>><span></span></button>
                    <?php endforeach; ?>
                    <button type="button" class="sf-cover__pause" data-cover-pause aria-label="Pause the photos" aria-pressed="false"><?= sf_icon('pause', 18) ?><?= sf_icon('play', 16, 'sf-cover__play') ?></button>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php break;

    case 'plate': ?>
    <div class="sf-wrap sf-hero__grid">
        <div class="sf-hero__plate">
            <div class="sf-plate sf-plate--hero<?= $media !== '' && !$is_video ? '' : ' sf-plate--drawn' ?>"><?= $media !== '' && !$is_video ? $media : $frame ?></div>
            <?php if ($badge !== ''): ?><p class="sf-roundel"><?= e($badge) ?></p><?php endif; ?>
        </div>
        <div class="sf-hero__body">
            <h1 class="sf-h1" id="sf-hero-title"><?= e($headline) ?></h1>
            <?= $text_html ?>
            <div class="sf-hero__actions"><?= $primary ?><?= $wa_button ?></div>
        </div>
    </div>
    <?php break;

    case 'spec': ?>
    <div class="sf-wrap sf-hero__grid">
        <div class="sf-hero__body">
            <?php $address = !empty($contact['show_address']) ? sf_short_address($business) : ''; if ($address !== '' || $badge !== ''): ?>
                <p class="sf-hero__meta">
                    <?php if ($address !== ''): ?><span><?= sf_icon('pin', 16) ?><?= e($address) ?></span><?php endif; ?>
                    <?php if ($badge !== ''): ?><span class="sf-hero__flag"><?= e($badge) ?></span><?php endif; ?>
                </p>
            <?php endif; ?>
            <h1 class="sf-h1" id="sf-hero-title"><?= e($headline) ?></h1>
            <?= $text_html ?>
            <?php if (count($catalogue['categories']) < 2 && $primary !== ''): ?>
                <div class="sf-hero__actions"><?= $primary ?></div>
            <?php endif; ?>
        </div>
        <?php if ($media !== ''): ?><div class="sf-hero__frame"><?= $media ?></div><?php endif; ?>
        <?php if (count($catalogue['categories']) >= 2 && $cat_on): ?>
            <div class="sf-cattiles">
                <?php foreach (array_slice($catalogue['categories'], 0, 4) as $i => $category): ?>
                    <a class="sf-cattile" href="<?= e(sf_page_href('catalogue', '#cat=' . rawurlencode(mb_strtolower($category)))) ?>">
                        <?= sf_icon(['grid', 'tag', 'sparkle', 'bag'][$i], 24) ?><span><?= e($category) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php break;

    case 'band': ?>
    <?php // Market: the headline is in the header band ?>
    <?php if ($media !== ''): ?><div class="sf-wrap"><div class="sf-hero__frame"><?= $media ?></div></div><?php endif; ?>
    <?php break;

    case 'estimator':
        $choices = array_slice(array_merge(
            array_values(array_filter($catalogue['items'], fn($i) => $i['is_service'])),
            array_values(array_filter($catalogue['items'], fn($i) => !$i['is_service']))
        ), 0, 6);
    ?>
    <div class="sf-wrap sf-hero__grid">
        <div class="sf-hero__body">
            <?php if ($badge !== ''): ?><p class="sf-hero__badge"><?= e($badge) ?></p><?php endif; ?>
            <h1 class="sf-h1" id="sf-hero-title"><?= e($headline) ?></h1>
            <?= $text_html ?>
            <?php if (!$choices && $primary !== ''): ?><div class="sf-hero__actions"><?= $primary ?></div><?php endif; ?>
            <?php if ($media !== ''): ?><div class="sf-hero__frame"><?= $media ?></div><?php endif; ?>
        </div>
        <?php if ($choices):
            $first = $choices[0];
        ?>
        <div class="sf-estimator" data-estimator>
            <h2 class="sf-h3">See a price</h2>
            <fieldset>
                <legend>What do you need?</legend>
                <div class="sf-estimator__choices">
                    <?php foreach ($choices as $i => $choice): ?>
                        <label class="sf-choice">
                            <input type="radio" name="sf-estimate" value="<?= (int)$choice['id'] ?>" <?= $i === 0 ? 'checked' : '' ?>
                                   data-name="<?= e($choice['name']) ?>"
                                   data-price="<?= e($choice['price'] > 0 ? sf_money($choice['price'], $currency) : 'Ask for the price') ?>"
                                   data-unit="<?= e($choice['price'] > 0 ? trim(sf_unit_text($choice['unit'])) : '') ?>">
                            <span><?= e($choice['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <p class="sf-estimator__band" aria-live="polite">
                <strong data-estimate-price><?= e($first['price'] > 0 ? sf_money($first['price'], $currency) : 'Ask for the price') ?></strong>
                <span data-estimate-unit><?= e($first['price'] > 0 ? trim(sf_unit_text($first['unit'])) : '') ?></span>
            </p>
            <div class="sf-estimator__actions">
                <?php if ($quote_on): ?>
                    <a class="sf-btn sf-btn--block" href="<?= e(sf_quote_href($first['name'])) ?>" data-estimate-quote>Ask for a quote</a>
                <?php endif; ?>
                <button type="button" class="sf-btn <?= $quote_on ? 'sf-btn--outline' : '' ?> sf-btn--block" data-estimate-open>See details</button>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php break;

    default: // soft, arch, stamp, shelf, facade
        $frame_first = in_array($variant, ['arch', 'stamp', 'facade'], true);
    ?>
    <div class="sf-wrap sf-hero__grid<?= $frame_first ? ' sf-hero__grid--frame-first' : '' ?>">
        <?php if ($frame !== ''): ?>
            <div class="sf-hero__frame">
                <?= $frame ?>
                <?php if ($variant === 'stamp' && $badge !== ''): ?><p class="sf-stamp"><?= e($badge) ?></p><?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="sf-hero__body">
            <?php if ($badge !== '' && $variant !== 'stamp'): ?><p class="sf-hero__badge"><?= e($badge) ?></p><?php endif; ?>
            <h1 class="sf-h1" id="sf-hero-title"><?= e($headline) ?></h1>
            <?= $text_html ?>
            <div class="sf-hero__actions"><?= $primary ?><?= $variant === 'arch' ? $wa_button : '' ?></div>
        </div>
    </div>
<?php endswitch; ?>
</section>
