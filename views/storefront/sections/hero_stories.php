<?php
/**
 * Glow's first screen: shoppable stories, the way its shoppers already look
 * at beauty on their phones. The owner's own photos open them, the first
 * with the headline; then one story per service or product, each with its
 * price, how long it takes, and Book or Add right there. A bar per story
 * fills while it shows; a tap on the right goes on, on the left goes back,
 * holding still pauses, a swipe up opens the details (storefront.js, "The
 * stories"). Under the stories, a ring per category jumps to its first one.
 * On a computer the story stands in the middle with the next ones beside it,
 * in a glow of its own colours.
 *
 * From hero.php: $hero_section, $headline, $text_html, $primary, $go,
 * $quote_on, $badge, and the shell's $business, $catalogue, $ctx, $wa.
 */
$picks  = storefront_hero_picks($catalogue, $hero_section);
$photos = array_values(array_filter($hero_section['slides'], fn($rel) => is_file(BASE_PATH . '/' . $rel)));
$stories = [];
foreach ($photos ?: [''] as $i => $rel) {
    $stories[] = ['kind' => $i === 0 ? 'intro' : 'photo', 'media' => $rel];
}
foreach ($picks as $item) {
    $stories[] = ['kind' => 'item', 'item' => $item, 'media' => $item['images'][0] ?? ''];
}
$count = count($stories);

// A story's photo or video; the first loads with the page, the others just
// before their turn (sf_cover_media)
$story_media = function (string $rel, int $i): string {
    if ($rel === '' || !is_file(BASE_PATH . '/' . $rel)) {
        return '';
    }
    return sf_cover_media($rel, $i);
};
// The faint glow behind the story on a computer: a small copy of its photo
$glow_url = function (string $rel): string {
    if ($rel === '' || !preg_match('/\.(png|jpe?g|webp)$/', $rel) || !sf_image_size($rel)) {
        return '';
    }
    $set = sf_image_srcset($rel);
    return $set ? substr($set[0], 0, strrpos($set[0], ' ')) : sf_media_url($rel);
};

// Rings: a category each, jumping to its first story (or its place in the list)
$rings = [];
if (count($catalogue['categories']) >= 2) {
    foreach (array_slice($catalogue['categories'], 0, 8) as $category) {
        $at = null;
        foreach ($stories as $i => $story) {
            if ($story['kind'] === 'item' && $story['item']['category'] === $category) {
                $at = $i;
                break;
            }
        }
        $in = array_values(array_filter($catalogue['items'], fn($i) => $i['category'] === $category));
        $pictured = array_values(array_filter($in, fn($i) => (bool)$i['images']))[0] ?? $in[0];
        $rings[] = ['name' => $category, 'at' => $at, 'item' => $pictured];
    }
}
$story_label = 'Stories from ' . $business['name'];
?>
<div class="sf-stories" data-stories<?= $count > 1 ? ' data-interval="5500"' : '' ?>>
    <div class="sf-stories__stage"<?= $count > 1 ? ' role="group" aria-roledescription="carousel" aria-label="' . e($story_label) . '"' : '' ?>>
        <div class="sf-stories__glow" aria-hidden="true">
            <?php foreach ($stories as $i => $story): $glow = $glow_url($story['media']); ?>
                <span data-glow="<?= $i ?>"<?= $glow !== '' ? ' style="background-image: url(\'' . e($glow) . '\')"' : '' ?><?= $i === 0 ? ' class="is-on"' : '' ?>></span>
            <?php endforeach; ?>
        </div>
        <?php foreach ($stories as $i => $story):
            $media = $story_media($story['media'], $i);
            $item  = $story['item'] ?? null;
            $label = ($i + 1) . ' of ' . $count . ($item ? ': ' . $item['name'] : '');
        ?>
            <article class="sf-story sf-story--<?= e($story['kind']) ?><?= $media === '' ? ' sf-story--drawn' : '' ?><?= $i === 0 ? ' is-on' : '' ?>" data-story="<?= $i ?>"
                     <?= $item ? 'data-story-item="' . (int)$item['id'] . '"' : '' ?>
                     <?= $count > 1 ? 'role="group" aria-roledescription="slide" aria-label="' . e($label) . '"' : '' ?><?= $i ? ' inert' : '' ?>>
                <div class="sf-story__media">
                    <?= $media !== '' ? $media : '<span class="sf-story__aura" aria-hidden="true"></span><span class="sf-story__mark" aria-hidden="true">' . e(sf_initials($item ? $item['name'] : (string)$business['name'])) . '</span>' ?>
                </div>
                <div class="sf-story__fade" aria-hidden="true"></div>
                <div class="sf-story__body">
                    <?php if ($story['kind'] === 'intro'): ?>
                        <?php if ($badge !== ''): ?><p class="sf-story__chips"><span><?= e($badge) ?></span></p><?php endif; ?>
                        <h1 class="sf-h1 sf-story__title" id="sf-hero-title"><?= e($headline) ?></h1>
                        <?= $hero_section['text'] !== '' ? sf_paragraphs($hero_section['text'], 'sf-story__lead') : '' ?>
                        <div class="sf-story__actions">
                            <?= $primary !== '' ? str_replace('class="sf-btn"', 'class="sf-btn sf-story__go"', $primary) : '' ?>
                            <?php if ($count > 1): ?>
                                <button type="button" class="sf-btn sf-btn--glass sf-story__next" data-story-step="1"><span>See what we do</span><?= sf_icon('right', 18) ?></button>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($story['kind'] === 'photo'): ?>
                        <p class="sf-story__by"><?= e($business['name']) ?></p>
                        <?php if ($go !== ''): ?>
                            <div class="sf-story__actions"><a class="sf-btn sf-btn--glass" href="<?= e($go) ?>"><?= e($labels['button']) ?></a></div>
                        <?php endif; ?>
                    <?php else:
                        $chips = array_filter([
                            $item['category'],
                            $item['booking'] && $item['booking']['mode'] === 'slot' ? booking_duration_text($item['booking']['minutes']) : '',
                        ]);
                    ?>
                        <?php if ($chips): ?>
                            <p class="sf-story__chips"><?php foreach ($chips as $chip): ?><span><?= e($chip) ?></span><?php endforeach; ?></p>
                        <?php endif; ?>
                        <h2 class="sf-story__name"><?= e($item['name']) ?></h2>
                        <p class="sf-story__price"><?= sf_price_html($item, $ctx) ?></p>
                        <div class="sf-story__actions">
                            <?php if ($item['booking']): ?>
                                <a class="sf-btn sf-story__go" href="<?= e(sf_live_links() ? booking_page_path($item) : '#item-' . (int)$item['id']) ?>" data-book="<?= (int)$item['id'] ?>"><?= sf_icon('calendar', 18) ?><span>Book a slot</span></a>
                            <?php elseif (!$item['sold_out']): ?>
                                <?= sf_add_button($item, 'Add to bag', 'sf-btn sf-story__go') ?>
                            <?php endif; ?>
                            <?= sf_open_link($item, '<span>Details</span>' . sf_icon('chevron', 18, 'sf-story__up'), 'sf-btn sf-btn--glass') ?>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>

        <?php if ($count > 1): ?>
            <div class="sf-stories__bars">
                <?php foreach ($stories as $i => $story): ?>
                    <button type="button" class="sf-stories__bar" data-story-to="<?= $i ?>" aria-label="Show story <?= $i + 1 ?> of <?= $count ?>"<?= $i === 0 ? ' aria-current="true"' : '' ?>><span></span></button>
                <?php endforeach; ?>
            </div>
            <button type="button" class="sf-stories__pause" data-stories-pause aria-label="Pause the stories" aria-pressed="false"><?= sf_icon('pause', 20) ?><?= sf_icon('play', 18, 'sf-stories__play') ?></button>
            <button type="button" class="sf-stories__side sf-stories__side--back" data-story-step="-1" aria-label="Last story"><?= sf_icon('left', 24) ?></button>
            <button type="button" class="sf-stories__side sf-stories__side--next" data-story-step="1" aria-label="Next story"><?= sf_icon('right', 24) ?></button>
        <?php endif; ?>
    </div>

    <?php if ($rings): ?>
        <nav class="sf-wrap sf-rings" aria-label="Jump to">
            <ul>
                <?php foreach ($rings as $ring): ?>
                    <li>
                        <?php $face = '<span class="sf-ring__face">' . sf_item_media($ring['item'], '72px') . '</span><span class="sf-ring__name">' . e($ring['name']) . '</span>'; ?>
                        <?php if ($ring['at'] !== null): ?>
                            <button type="button" class="sf-ring" data-story-to="<?= $ring['at'] ?>"><?= $face ?></button>
                        <?php else: ?>
                            <a class="sf-ring" href="<?= e(sf_page_href('catalogue', '#cat=' . rawurlencode(mb_strtolower($ring['name'])))) ?>"><?= $face ?></a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
                <?php if ($go !== '' && $cat_on): ?>
                    <li><a class="sf-ring sf-ring--all" href="<?= e($go) ?>"><span class="sf-ring__face"><?= sf_icon('grid', 26) ?></span><span class="sf-ring__name"><?= e($labels['catalogue']) ?></span></a></li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>
