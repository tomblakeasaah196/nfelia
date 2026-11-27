<?php
/**
 * A booking's own page, name.nfelia.com/booking/{token}: where the request
 * stands, when it is, the deposit and what's left to pay, adding it to the
 * phone's calendar, and WhatsApp. Private, like an order's tracking page.
 *
 * From shell.php: $page['booking'], $wa, $currency.
 */
$booking = $page['booking'];
$shop    = (string)$business['name'];
$money   = fn($amount) => sf_money((float)$amount, $currency);
$status  = (string)$booking['status'];
$past    = strtotime((string)$booking['ends_at']) < time();
$balance = max(0, (float)$booking['total'] - (float)$booking['deposit_due']);
$caution = (float)($booking['caution_fee'] ?? 0);
$here    = $shop_url . '/booking/' . $booking['public_token'];
$when    = booking_when($booking);

$lead = match ($status) {
    'confirmed' => $past ? 'Thank you for booking with ' . $shop . '.' : 'Confirmed. ' . $shop . ' is expecting you.',
    'completed' => 'Done. Thank you for booking with ' . $shop . '.',
    'declined'  => $shop . ' couldn\'t take this booking.',
    'cancelled' => 'This booking was cancelled.',
    default     => $booking['channel'] === 'whatsapp'
        ? 'Send it to ' . $shop . ' on WhatsApp so they can confirm it with you.'
        : ($booking['deposit_due'] > 0 ? $shop . ' is checking your deposit. Your time is held for you meanwhile.' : $shop . ' will confirm it on the number you gave.'),
};
$steps = in_array($status, ['declined', 'cancelled'], true) ? [] : [
    ['label' => 'Booking requested', 'state' => 'done', 'time' => (string)$booking['created_at']],
    ['label' => $status === 'pending' ? ($booking['deposit_due'] > 0 ? 'Checking your deposit' : 'Waiting to be confirmed') : 'Confirmed',
     'state' => $status === 'pending' ? 'now' : 'done', 'time' => $status === 'pending' ? null : $booking['decided_at']],
    ['label' => $booking['kind'] === 'nights' ? 'Your stay' : ($booking['kind'] === 'day' ? 'The day' : 'Your appointment'),
     'state' => $status === 'completed' || ($status === 'confirmed' && $past) ? 'done' : ($status === 'confirmed' ? 'now' : 'next'), 'time' => null],
];

$wa_send = 'Hello ' . $shop . ', I booked ' . $booking['product_name'] . ' for ' . $when . '. My booking number is ' . $booking['booking_number']
         . '. Name: ' . $booking['customer_name'] . '. Details: ' . $here;
$wa_ask  = 'Hello ' . $shop . ', I have a question about my booking ' . $booking['booking_number'] . ' (' . $when . ').';
$waiting_on_wa = $booking['channel'] === 'whatsapp' && $status === 'pending';
?>
<section class="sf-section sf-page" aria-labelledby="sf-booking-title" data-booking="<?= e($booking['booking_number']) ?>">
    <div class="sf-wrap sf-wrap--narrow">
        <p class="sf-eyebrow">Your booking with <?= e($shop) ?></p>
        <h1 class="sf-h1 sf-page__title" id="sf-booking-title">Booking <?= e($booking['booking_number']) ?></h1>
        <p class="sf-lead"><?= e($lead) ?></p>
        <?php if ($booking['owner_message'] && in_array($status, ['declined', 'cancelled'], true)): ?>
            <p class="sf-note"><strong><?= e($shop) ?>:</strong> <?= e((string)$booking['owner_message']) ?></p>
        <?php endif; ?>

        <div class="sf-card sf-when">
            <?php $lines = booking_lines(get_db(), $booking); ?>
            <?php if (count($lines) > 1): /* several services in one visit */ ?>
                <p class="sf-card__title"><?= count($lines) ?> services in one visit</p>
                <ul class="sf-when__lines">
                    <?php foreach ($lines as $line): ?><li><?= e($line['name']) ?></li><?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="sf-card__title"><?= e($booking['product_name']) ?></p>
            <?php endif; ?>
            <p class="sf-when__date"><?= sf_icon('calendar', 20) ?><span><?= e($when) ?></span></p>
            <?php if (sf_short_address($business) !== ''): ?>
                <p class="sf-when__place"><?= sf_icon('pin', 20) ?><span><?= e(sf_short_address($business)) ?></span></p>
            <?php endif; ?>
        </div>

        <?php if ($steps): ?>
            <ol class="sf-track">
                <?php foreach ($steps as $step): ?>
                    <li class="sf-track__step is-<?= e($step['state']) ?>"<?= $step['state'] === 'now' ? ' aria-current="step"' : '' ?>>
                        <span class="sf-track__dot" aria-hidden="true"><?= $step['state'] === 'done' ? sf_icon('check', 16) : '' ?></span>
                        <div class="sf-track__body">
                            <p class="sf-track__label"><?= e($step['label']) ?> <span class="sf-sr"><?= $step['state'] === 'done' ? '(done)' : ($step['state'] === 'now' ? '(now)' : '(next)') ?></span></p>
                            <?php if ($step['time']): ?>
                                <p class="sf-track__time"><?= e(date('j M, g:ia', strtotime((string)$step['time']))) ?></p>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>

        <div class="sf-card">
            <p class="sf-total"><span>Total</span><strong><?= e($money($booking['total'])) ?></strong></p>
            <?php if ($booking['kind'] === 'nights'): ?>
                <p class="sf-orderline__meta"><?= (int)$booking['units'] ?> night<?= (int)$booking['units'] === 1 ? '' : 's' ?> × <?= e($money($booking['unit_price'])) ?></p>
            <?php endif; ?>
            <?php if ($booking['deposit_due'] > 0): ?>
                <p class="sf-total sf-total--deposit"><span>Deposit<?= $booking['payment_method'] === 'bank_transfer' ? ($booking['payment_proof_path'] ? ', proof sent' : ', by transfer') : '' ?></span><strong><?= e($money($booking['deposit_due'])) ?></strong></p>
                <p class="sf-total sf-total--deposit"><span>To pay on the day</span><strong><?= e($money($balance)) ?></strong></p>
            <?php endif; ?>
            <?php if ($caution > 0): ?>
                <p class="sf-total sf-total--deposit"><span>Caution fee, refundable</span><strong><?= e($money($caution)) ?></strong></p>
                <p class="sf-orderline__meta"><?= $booking['caution_refunded_at']
                    ? e($shop . ' gave it back on ' . date('j F', strtotime((string)$booking['caution_refunded_at'])) . '.')
                    : (in_array($status, ['declined', 'cancelled'], true) ? 'If you paid it, ' . e($shop) . ' gives it back.' : 'Paid with the booking. It comes back to you after you check out.') ?></p>
            <?php endif; ?>
        </div>

        <div class="sf-page__actions">
            <?php if ($waiting_on_wa && $wa !== ''): ?>
                <a class="sf-btn sf-btn--block" href="https://wa.me/<?= e($wa) ?>?text=<?= e(rawurlencode($wa_send)) ?>" target="_blank" rel="noopener"><?= sf_icon('whatsapp', 20) ?><span>Send it on WhatsApp</span></a>
            <?php endif; ?>
            <?php if (in_array($status, ['pending', 'confirmed'], true) && !$past): ?>
                <a class="sf-btn sf-btn--outline sf-btn--block" href="/booking/<?= e($booking['public_token']) ?>/calendar.ics" download="booking-<?= e(strtolower($booking['booking_number'])) ?>.ics"><?= sf_icon('calendar', 20) ?><span>Add to my calendar</span></a>
            <?php endif; ?>
            <?php if ($wa !== '' && !$waiting_on_wa): ?>
                <a class="sf-btn sf-btn--outline sf-btn--block" href="https://wa.me/<?= e($wa) ?>?text=<?= e(rawurlencode($wa_ask)) ?>" target="_blank" rel="noopener"><?= sf_icon('whatsapp', 20) ?><span><?= $status === 'confirmed' && !$past ? 'Change or cancel on WhatsApp' : 'Ask ' . e($shop) . ' about it' ?></span></a>
            <?php endif; ?>
            <a class="sf-link" href="/">Back to the shop</a>
        </div>

        <p class="sf-fineprint">Keep this link to follow your booking. Anyone you send it to can see this page, so share it only with people you trust.</p>
    </div>
</section>
