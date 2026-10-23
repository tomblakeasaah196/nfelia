<?php
/**
 * Haven's first screen: dates first. A guest's first question is "is it
 * free when I'm coming, and what will it cost?", so the page asks it: "When
 * are you coming?", over the owner's photo. Once they pick their nights,
 * every place says whether it is free and what that stay costs (a longer
 * stay's cheaper nights, the deposit and the refundable caution fee), free
 * ones first, and books those dates in one tap (storefront.js, "Haven's
 * dates", asking /stays/free). Each place shows how many it sleeps and its
 * amenities, power first. The house rules follow, with the check-in times.
 *
 * The page is the same for everyone, so it can be cached; what is free comes
 * from /stays/free. Without the script every place still links to its own
 * booking calendar.
 *
 * From hero.php: $stays, $headline, $text_html, $badge, $media, $is_video,
 * and the shell's $business, $ctx, $currency, $contact.
 */
$booking_settings = booking_settings($business);
[$first, $last]   = booking_window($booking_settings);
$address = !empty($contact['show_address']) ? sf_short_address($business) : '';
$rules   = $booking_settings['rules'];
// The owner's photo, else the first place's own
$backdrop = $media !== '' && !$is_video ? $media : '';
if ($backdrop === '') {
    foreach ($stays as $stay) {
        foreach ($stay['images'] as $rel) {
            if (($backdrop = sf_img($rel, '100vw', '', 'sf-hero__img', true)) !== '') {
                break 2;
            }
        }
    }
}
$max_sleeps = max(array_map(fn($s) => sf_stay_facts($s)['sleeps'] ?? 0, $stays) ?: [0]);
?>
<div class="sf-stays" data-stays data-first="<?= e($first) ?>" data-last="<?= e($last) ?>" data-max-nights="<?= BOOKING_MAX_NIGHTS ?>">
    <div class="sf-stays__top<?= $backdrop === '' ? ' sf-stays__top--drawn' : '' ?>">
        <div class="sf-stays__photo" aria-hidden="true"><?= $backdrop !== '' ? $backdrop : sf_motif('haven', $ctx) ?></div>
        <div class="sf-wrap sf-stays__words">
            <?php if ($address !== '' || $badge !== ''): ?>
                <p class="sf-stays__meta">
                    <?php if ($address !== ''): ?><span><?= sf_icon('pin', 16) ?><?= e($address) ?></span><?php endif; ?>
                    <?php if ($badge !== ''): ?><span class="sf-stays__badge"><?= e($badge) ?></span><?php endif; ?>
                </p>
            <?php endif; ?>
            <h1 class="sf-h1 sf-stays__title" id="sf-hero-title"><?= e($headline) ?></h1>
            <?= $text_html ?>
        </div>
    </div>

    <div class="sf-wrap sf-stays__bar">
        <form class="sf-datebar" data-datebar novalidate>
            <p class="sf-datebar__ask" id="sf-datebar-ask">When are you coming?</p>
            <div class="sf-datebar__fields" role="group" aria-labelledby="sf-datebar-ask">
                <button type="button" class="sf-datebar__field" data-dates-open="in">
                    <span class="sf-datebar__label"><?= sf_icon('calendar', 16) ?>Check in</span>
                    <strong data-date-in>Add a date</strong>
                </button>
                <button type="button" class="sf-datebar__field" data-dates-open="out">
                    <span class="sf-datebar__label"><?= sf_icon('moon', 16) ?>Check out</span>
                    <strong data-date-out>Add a date</strong>
                </button>
                <?php if ($max_sleeps > 1): ?>
                    <div class="sf-datebar__field sf-datebar__guests">
                        <span class="sf-datebar__label" id="sf-guests-label"><?= sf_icon('people', 16) ?>Guests</span>
                        <div class="sf-stepper" role="group" aria-labelledby="sf-guests-label">
                            <button type="button" data-guests-step="-1" aria-label="One guest fewer"><?= sf_icon('minus', 16) ?></button>
                            <output data-guests aria-live="polite">2</output>
                            <button type="button" data-guests-step="1" aria-label="One more guest"><?= sf_icon('plus', 16) ?></button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <p class="sf-datebar__said" data-dates-said aria-live="polite">Pick your nights and we show what's free, and what it costs.</p>
        </form>
    </div>

    <div class="sf-wrap">
        <ul class="sf-stays__list" data-stays-list>
            <?php foreach ($stays as $stay):
                $info  = $stay['booking'];
                $facts = sf_stay_facts($stay);
                $book  = booking_page_path($stay);
            ?>
                <li class="sf-stay" data-stay="<?= (int)$stay['id'] ?>" data-sleeps="<?= (int)($facts['sleeps'] ?? 0) ?>" data-book-href="<?= e($book) ?>">
                    <?= sf_open_link($stay, sf_item_media($stay, '(min-width: 960px) 380px, (min-width: 640px) 46vw, 92vw') . '<span class="sf-stay__price">' . e(sf_money($stay['price'], $currency)) . '<small> a night</small></span>'
                        . (count($stay['images']) > 1 ? '<span class="sf-stay__count">' . sf_icon('image', 14) . count($stay['images']) . '</span>' : ''), 'sf-stay__photo', true) ?>
                    <div class="sf-stay__body">
                        <p class="sf-stay__where">
                            <?php if ($stay['category'] !== ''): ?><span><?= sf_icon('pin', 15) ?><?= e($stay['category']) ?></span><?php endif; ?>
                            <?php if ($facts['sleeps']): ?><span data-stay-sleeps><?= sf_icon('people', 15) ?>Sleeps <?= (int)$facts['sleeps'] ?></span><?php endif; ?>
                        </p>
                        <h2 class="sf-stay__name"><?= sf_open_link($stay, e($stay['name'])) ?></h2>
                        <?php if ($facts['amenities']): ?>
                            <ul class="sf-stay__amenities" aria-label="What it has">
                                <?php foreach ($facts['amenities'] as $amenity): ?>
                                    <li class="sf-amenity<?= $amenity['key'] === 'power' ? ' sf-amenity--power' : '' ?>"><?= sf_icon($amenity['icon'], 15) ?><?= e($amenity['label']) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?= sf_stay_rates($info, $currency) ?>
                        <?php if ($info['caution'] > 0): ?><p class="sf-stay__caution"><?= sf_icon('shield', 15) ?><?= e(sf_money($info['caution'], $currency)) ?> refundable caution fee</p><?php endif; ?>
                        <div class="sf-stay__answer" data-stay-answer aria-live="polite"></div>
                        <a class="sf-btn sf-btn--block sf-stay__book" href="<?= e($book) ?>" data-stay-book>Check dates and book</a>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="sf-rules">
            <p class="sf-rules__title"><?= sf_icon('clipboard', 18) ?>House rules</p>
            <ul class="sf-rules__list">
                <li><?= sf_icon('clock', 16) ?>Check-in from <?= e(booking_time_text($booking_settings['checkin'])) ?>, check-out by <?= e(booking_time_text($booking_settings['checkout'])) ?></li>
                <?php foreach ($rules as $rule): ?><li><?= sf_icon('check', 16) ?><?= e($rule) ?></li><?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<dialog class="sf-dialog sf-dialog--dates" id="sf-dates" aria-labelledby="sf-dates-title">
    <div class="sf-dialog__panel sf-dates">
        <div class="sf-dialog__head">
            <h2 class="sf-h3" id="sf-dates-title" data-dates-title>When do you arrive?</h2>
            <button type="button" class="sf-iconbtn" data-dates-close aria-label="Close"><?= sf_icon('close', 22) ?></button>
        </div>
        <div class="sf-cal sf-dates__cal" data-dates-cal>
            <div class="sf-cal__head">
                <button type="button" class="sf-iconbtn" data-dates-prev aria-label="Earlier month"><?= sf_icon('left', 20) ?></button>
                <p class="sf-cal__month" data-dates-month aria-live="polite"></p>
                <button type="button" class="sf-iconbtn" data-dates-next aria-label="Later month"><?= sf_icon('right', 20) ?></button>
            </div>
            <div class="sf-cal__days" aria-hidden="true">
                <?php foreach (['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'] as $d): ?><span><?= $d ?></span><?php endforeach; ?>
            </div>
            <div class="sf-cal__grid" data-dates-grid></div>
            <p class="sf-cal__wait" data-dates-wait>Finding free nights…</p>
        </div>
        <p class="sf-dates__said" data-dates-picked aria-live="polite"></p>
        <div class="sf-dates__actions">
            <button type="button" class="sf-btn sf-btn--outline" data-dates-clear>Clear</button>
            <button type="button" class="sf-btn" data-dates-done disabled>Show what's free</button>
        </div>
    </div>
</dialog>
