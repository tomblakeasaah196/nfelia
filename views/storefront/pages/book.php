<?php
/**
 * Booking a product, name.nfelia.com/book/{id}-{name}: pick a day and a time
 * (or the nights of a stay), give your details, pay the deposit by transfer,
 * and send it. storefront.js draws the calendar from /book/{id}/free and
 * sends the booking to /book/{id}; this page is its frame, the same for
 * everyone, so it can be cached.
 *
 * From shell.php: $page['item'] (with its booking info), $catalogue (banks),
 * $wa, $currency.
 */
$item    = $page['item'];
$info    = $item['booking'];
$banks   = $catalogue['banks'];
$nights  = $info['mode'] === 'nights';
$slot    = $info['mode'] === 'slot';
$has_dep = $info['deposit_type'] !== 'none' && $info['deposit_value'] > 0;
// A stay may ask for a refundable caution fee, paid with the booking
$caution = $nights ? $info['caution'] : 0.0;
$pay_now = $has_dep || $caution > 0;
$pay_what = $has_dep && $caution > 0 ? 'the deposit and the caution fee' : ($caution > 0 ? 'the caution fee' : 'the deposit');
$rules   = $nights ? $page['settings']['rules'] : [];
$photo   = '';
foreach ($item['images'] as $rel) {
    if (($photo = sf_img($rel, '96px', '', 'sf-book__photo-img')) !== '') {
        break;
    }
}
$price_line = $item['price'] > 0 ? sf_money($item['price'], $currency) . ($nights ? ' a night' : '') : 'Ask for the price';
?>
<section class="sf-section sf-page sf-book" aria-labelledby="sf-book-title" data-book-page>
    <div class="sf-wrap sf-wrap--narrow">
        <a class="sf-back" href="<?= e(sf_product_path($item)) ?>"><?= sf_icon('left', 18) ?><span><?= e($item['name']) ?></span></a>

        <div class="sf-book__head">
            <?php if ($photo !== ''): ?>
                <span class="sf-book__photo"><?= $photo ?></span>
            <?php endif; ?>
            <div class="sf-book__title">
                <p class="sf-eyebrow">Book with <?= e($business['name']) ?></p>
                <h1 class="sf-h1 sf-page__title" id="sf-book-title"><?= e($item['name']) ?></h1>
                <p class="sf-book__meta"><strong><?= e($price_line) ?></strong> · <?= e(booking_summary($info, $item['price'], $currency)) ?></p>
                <?= $nights ? sf_stay_rates($info, $currency) : '' ?>
            </div>
        </div>

        <form class="sf-form sf-bookform" data-bookform novalidate>
            <input type="text" name="website" class="sf-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
            <fieldset class="sf-book__step">
                <legend class="sf-book__legend"><span aria-hidden="true">1</span><?= $nights ? 'Your dates' : 'Pick a day' ?></legend>
                <?php if ($nights): ?>
                    <p class="sf-field__hint">Tap the day you arrive, then the day you leave. Check-in from <?= e(booking_time_text($page['settings']['checkin'])) ?>, check-out by <?= e(booking_time_text($page['settings']['checkout'])) ?>.</p>
                <?php endif; ?>
                <div class="sf-cal" data-cal>
                    <div class="sf-cal__head">
                        <button type="button" class="sf-iconbtn" data-cal-prev aria-label="Earlier month"><?= sf_icon('left', 20) ?></button>
                        <p class="sf-cal__month" data-cal-month aria-live="polite"></p>
                        <button type="button" class="sf-iconbtn" data-cal-next aria-label="Later month"><?= sf_icon('right', 20) ?></button>
                    </div>
                    <div class="sf-cal__days" aria-hidden="true">
                        <?php foreach (['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'] as $d): ?><span><?= $d ?></span><?php endforeach; ?>
                    </div>
                    <div class="sf-cal__grid" data-cal-grid></div>
                    <p class="sf-cal__wait" data-cal-wait>Finding free days…</p>
                </div>
                <?php if ($slot): ?>
                    <div class="sf-times" data-times hidden>
                        <p class="sf-field__label" data-times-title>Free times</p>
                        <div class="sf-times__list" data-times-list></div>
                        <p class="sf-empty" data-times-none hidden>No free times left that day. Pick another day.</p>
                    </div>
                <?php endif; ?>
                <p class="sf-book__picked" data-book-picked aria-live="polite"></p>
            </fieldset>

            <fieldset class="sf-book__step">
                <legend class="sf-book__legend"><span aria-hidden="true">2</span>Your details</legend>
                <label class="sf-field">
                    <span class="sf-field__label">Your name</span>
                    <input type="text" name="customer_name" required maxlength="100" autocomplete="name">
                </label>
                <label class="sf-field">
                    <span class="sf-field__label">Phone or WhatsApp</span>
                    <input type="tel" name="customer_phone" required maxlength="20" autocomplete="tel" inputmode="tel" placeholder="0803 123 4567">
                </label>
                <label class="sf-field">
                    <span class="sf-field__label">Email <span class="sf-field__opt">(optional)</span></span>
                    <input type="email" name="customer_email" maxlength="100" autocomplete="email">
                </label>
                <label class="sf-field">
                    <span class="sf-field__label">Anything <?= e($business['name']) ?> should know <span class="sf-field__opt">(optional)</span></span>
                    <textarea name="customer_note" rows="2" maxlength="1000"></textarea>
                </label>
            </fieldset>

            <fieldset class="sf-book__step">
                <legend class="sf-book__legend"><span aria-hidden="true">3</span><?= $pay_now ? 'Pay ' . e($pay_what) : 'Send your request' ?></legend>
                <?php if ($rules): ?>
                    <div class="sf-rules sf-rules--book">
                        <p class="sf-rules__title"><?= sf_icon('clipboard', 18) ?>House rules</p>
                        <ul class="sf-rules__list">
                            <li>Check-in from <?= e(booking_time_text($page['settings']['checkin'])) ?>, check-out by <?= e(booking_time_text($page['settings']['checkout'])) ?></li>
                            <?php foreach ($rules as $rule): ?><li><?= e($rule) ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <p class="sf-total"><span>Total</span><strong data-book-total>—</strong></p>
                <?php if ($nights): ?><p class="sf-orderline__meta" data-book-rate></p><?php endif; ?>
                <?php if ($pay_now): ?>
                    <?php if ($has_dep): ?><p class="sf-total sf-total--deposit"><span>Deposit to book</span><strong data-book-deposit>—</strong></p><?php endif; ?>
                    <?php if ($caution > 0): ?>
                        <p class="sf-total sf-total--deposit"><span>Caution fee, refundable</span><strong><?= e(sf_money($caution, $currency)) ?></strong></p>
                        <p class="sf-total sf-total--now"><span>To pay now</span><strong data-book-now>—</strong></p>
                    <?php endif; ?>
                    <?php if ($banks): ?>
                        <p class="sf-note">Send <?= e($pay_what) ?> to one of these accounts, then book. <?= e($business['name']) ?> confirms your booking once they see it.<?= $caution > 0 ? ' The caution fee comes back to you after you check out.' : '' ?><?= $has_dep ? ' You pay the rest on the day.' : '' ?></p>
                        <fieldset class="sf-options">
                            <legend>Pay by <?= e(sf_pay_way($banks)) ?> to</legend>
                            <?php foreach ($banks as $i => $bank): ?>
                                <label class="sf-option sf-option--bank">
                                    <input type="radio" name="bank_id" value="<?= (int)$bank['id'] ?>" <?= $i === 0 ? 'checked' : '' ?>>
                                    <span>
                                        <strong><?= e($bank['bank_name']) ?></strong>
                                        <span class="sf-option__number"><?= e($bank['account_number']) ?></span>
                                        <small><?= e($bank['account_name']) ?></small>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                        <?= sf_proof_field('A screenshot of your transfer helps ' . $business['name'] . ' confirm it quickly.') ?>
                    <?php else: ?>
                        <p class="sf-note"><?= e($business['name']) ?> will tell you how to pay <?= e($pay_what) ?>, on the number you give.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="sf-note"><?= e($business['name']) ?> confirms your booking on the number you give. You pay on the day.</p>
                <?php endif; ?>
                <p class="sf-form__error" role="alert" data-book-error></p>
                <button type="submit" class="sf-btn sf-btn--block" data-book-submit><?= $pay_now && $banks ? 'Book it' : 'Send my booking request' ?></button>
                <?php if ($wa !== ''): ?>
                    <button type="button" class="sf-btn sf-btn--outline sf-btn--block" data-book-wa><?= sf_icon('whatsapp', 20) ?><span>Book on WhatsApp instead</span></button>
                <?php endif; ?>
            </fieldset>
        </form>

        <div class="sf-done sf-book__done" data-book-done hidden>
            <span class="sf-done__icon" aria-hidden="true"><?= sf_icon('check', 30) ?></span>
            <h2 class="sf-h2" tabindex="-1" data-book-done-title>Booking requested</h2>
            <p>Your booking number is <strong data-book-number></strong>.</p>
            <p data-book-done-when></p>
            <p data-book-done-text></p>
            <a class="sf-btn sf-btn--block" data-book-done-wa hidden target="_blank" rel="noopener"><?= sf_icon('whatsapp', 20) ?><span>Open WhatsApp to send it</span></a>
            <a class="sf-btn sf-btn--outline sf-btn--block" data-book-track><?= sf_icon('calendar', 20) ?><span>Follow your booking</span></a>
            <a class="sf-link" href="/">Back to the shop</a>
        </div>
    </div>
</section>
