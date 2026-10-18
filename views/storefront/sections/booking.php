<?php
/**
 * Bookings: the services people book, each with how long it takes, its
 * deposit and a "Book" button to its booking page. A live shop shows the
 * section once a product can be booked; Shop Studio shows where it will go.
 */
$bookable = $catalogue['bookable'];
if (!$bookable && $mode === 'live') {
    return;
}
$booking_title = $section['title'] !== '' ? $section['title']
    : (count(array_filter($bookable, fn($i) => $i['booking']['mode'] === 'nights')) === count($bookable) && $bookable ? 'Book your stay' : 'Book a time');
?>
<section class="sf-section sf-bookings" id="sf-booking" aria-labelledby="sf-booking-title">
    <div class="sf-wrap">
        <div class="sf-section__head">
            <h2 class="sf-h2" id="sf-booking-title"><?= e($booking_title) ?></h2>
        </div>
        <?php if ($section['text'] !== ''): ?>
            <?= sf_paragraphs($section['text'], 'sf-lead sf-bookings__intro') ?>
        <?php endif; ?>
        <?php if ($bookable): ?>
            <ul class="sf-bookings__list">
                <?php foreach ($bookable as $item): ?>
                    <li class="sf-bookcard" data-item="<?= (int)$item['id'] ?>">
                        <div class="sf-bookcard__body">
                            <h3 class="sf-bookcard__name"><?= sf_open_link($item, e($item['name'])) ?></h3>
                            <p class="sf-bookcard__price"><?= sf_price_html($item, $ctx, false) ?><?= $item['booking']['mode'] === 'nights' && $item['price'] > 0 ? '<span class="sf-unit"> a night</span>' : '' ?></p>
                            <p class="sf-bookcard__meta"><?= sf_icon('clock', 16) ?><span><?= e(booking_summary($item['booking'], $item['price'], $ctx['currency'])) ?></span></p>
                        </div>
                        <?= sf_add_button($item, 'Book', 'sf-btn sf-btn--sm') ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <?= sf_gap($ctx, 'Turn on bookings for a service in your products, and it shows here with a Book button.') ?>
        <?php endif; ?>
    </div>
</section>
