<?php
/**
 * Nfelia — One booking (BookingController::view)
 * Variables: $business, $booking, $booking_link (the shopper's page), $customer_wa
 */
$currency = $business['currency_symbol'] ?? '₦';
$status   = $booking['status'];
$first    = planner_first_name($booking['customer_name']);
$when     = booking_when($booking);
$balance  = max(0, (float)$booking['total'] - (float)$booking['deposit_due']);
$wa_text  = match ($status) {
    'confirmed' => 'Hello ' . $first . ', your booking ' . $booking['booking_number'] . ' for ' . $when . ' is confirmed. See you then.',
    'declined'  => 'Hello ' . $first . ', I\'m sorry, I can\'t take your booking ' . $booking['booking_number'] . ' for ' . $when . '.',
    default     => 'Hello ' . $first . ', about your booking ' . $booking['booking_number'] . ' for ' . $when . '.',
} . ($booking_link ? ' Details: ' . $booking_link : '');
$labels = ['pending' => 'To confirm', 'confirmed' => 'Confirmed', 'declined' => 'Declined', 'cancelled' => 'Cancelled', 'completed' => 'Done'];
?>
<div class="mb-6">
    <a href="<?= APP_URL ?>/bookings" class="text-sm font-semibold text-brand-plum/60 hover:text-brand-plum">← Bookings</a>
    <div class="flex flex-wrap items-center gap-3 mt-2">
        <h1 class="font-heading font-extrabold text-2xl sm:text-3xl text-brand-plum tracking-tight">Booking <?= e($booking['booking_number']) ?></h1>
        <span class="px-2.5 py-1 rounded-full text-xs font-bold <?= $status === 'pending' ? 'bg-amber-100 text-amber-800' : ($status === 'confirmed' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600') ?>"><?= e($labels[$status] ?? $status) ?></span>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm space-y-3">
            <p class="text-lg font-bold text-brand-plum"><?= e($when) ?></p>
            <p class="text-sm text-brand-plum/80"><?= e($booking['product_name']) ?><?= $booking['kind'] === 'nights' ? ' · ' . (int)$booking['units'] . ' night' . ((int)$booking['units'] === 1 ? '' : 's') . ' × ' . e(sf_money((float)$booking['unit_price'], $currency)) : '' ?></p>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm pt-2 border-t border-brand-sand/60">
                <dt class="text-brand-plum/50">Total</dt><dd class="font-bold text-brand-plum text-right"><?= e(sf_money((float)$booking['total'], $currency)) ?></dd>
                <dt class="text-brand-plum/50">Deposit</dt><dd class="font-bold text-brand-plum text-right"><?= $booking['deposit_due'] > 0 ? e(sf_money((float)$booking['deposit_due'], $currency)) : 'None' ?></dd>
                <?php if (!empty($deposit_receipt)): ?>
                <dt class="text-brand-plum/50">Deposit receipt</dt><dd class="text-right"><a href="<?= APP_URL ?>/receipts/view/<?= (int)$deposit_receipt['id'] ?>" class="font-bold text-brand-plum underline"><?= e($deposit_receipt['doc_number']) ?></a></dd>
                <?php endif; ?>
                <dt class="text-brand-plum/50">To pay on the day</dt><dd class="font-bold text-brand-plum text-right"><?= e(sf_money($balance, $currency)) ?></dd>
                <dt class="text-brand-plum/50">How they paid</dt><dd class="text-brand-plum text-right"><?= $booking['channel'] === 'whatsapp' ? 'Arranging on WhatsApp' : ($booking['payment_method'] === 'bank_transfer' ? 'Bank transfer' : 'To arrange') ?></dd>
            </dl>
            <?php if ($booking['customer_note']): ?>
            <p class="text-sm text-brand-plum/70 italic">“<?= e($booking['customer_note']) ?>”</p>
            <?php endif; ?>
            <?php if ($booking['owner_message']): ?>
            <p class="text-sm text-brand-plum/70">Your message to them: “<?= e($booking['owner_message']) ?>”</p>
            <?php endif; ?>
        </div>

        <?php if ($booking['payment_proof_path']): ?>
        <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm">
            <p class="text-sm font-bold text-brand-plum mb-3">Proof of the deposit</p>
            <a href="<?= APP_URL . '/bookings/proof/' . (int)$booking['id'] ?>" target="_blank" rel="noopener">
                <img src="<?= APP_URL . '/bookings/proof/' . (int)$booking['id'] ?>" alt="Proof of payment from <?= e($booking['customer_name']) ?>" class="max-h-96 rounded-lg border border-brand-sand">
            </a>
            <p class="text-xs text-brand-plum/50 mt-2">Check your bank app for the money before you confirm. A screenshot alone isn't proof.</p>
        </div>
        <?php endif; ?>

        <?php if ($status === 'pending'): ?>
        <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm space-y-4">
            <form method="POST" action="<?= APP_URL ?>/bookings/confirm/<?= (int)$booking['id'] ?>" class="space-y-3">
                <?= csrf_field() ?>
                <?php if ($booking['deposit_due'] > 0): ?>
                <label class="flex items-start gap-3 text-sm text-brand-plum">
                    <input type="checkbox" name="deposit_received" value="1" class="mt-0.5 w-5 h-5 rounded border-brand-sand" <?= $booking['payment_proof_path'] ? 'checked' : '' ?>>
                    <span>I have the <?= e(sf_money((float)$booking['deposit_due'], $currency)) ?> deposit <span class="block text-xs text-brand-plum/50">We make a receipt for it, so it shows in your money in.</span></span>
                </label>
                <?php endif; ?>
                <button type="submit" class="w-full h-12 btn-primary text-white text-sm font-bold rounded-xl">Confirm and add to my calendar</button>
            </form>
            <form method="POST" action="<?= APP_URL ?>/bookings/decline/<?= (int)$booking['id'] ?>" class="space-y-2">
                <?= csrf_field() ?>
                <label class="block text-sm font-semibold text-brand-plum" for="decline-message">Can't take it? Tell them why <span class="font-normal text-brand-plum/50">(they see this)</span></label>
                <input id="decline-message" type="text" name="message" maxlength="300" placeholder="Fully booked that day. Please pick another time."
                       class="w-full h-11 px-3 rounded-lg border border-brand-sand text-sm">
                <button type="submit" class="w-full h-11 text-red-700 border border-red-200 bg-red-50 hover:bg-red-100 text-sm font-bold rounded-xl">Decline and free the time</button>
            </form>
        </div>
        <?php elseif ($status === 'confirmed'): ?>
        <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm space-y-4">
            <form method="POST" action="<?= APP_URL ?>/bookings/complete/<?= (int)$booking['id'] ?>">
                <?= csrf_field() ?>
                <button type="submit" class="w-full h-12 text-emerald-800 border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 text-sm font-bold rounded-xl">Mark as done</button>
            </form>
            <form method="POST" action="<?= APP_URL ?>/bookings/cancel/<?= (int)$booking['id'] ?>" class="space-y-2"
                  onsubmit="return confirm(<?= e(json_encode('Cancel ' . $booking['booking_number'] . ' and take it off your calendar?', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>)">
                <?= csrf_field() ?>
                <label class="block text-sm font-semibold text-brand-plum" for="cancel-message">Message to them <span class="font-normal text-brand-plum/50">(optional, they see this)</span></label>
                <input id="cancel-message" type="text" name="message" maxlength="300" class="w-full h-11 px-3 rounded-lg border border-brand-sand text-sm">
                <button type="submit" class="w-full h-11 text-red-700 border border-red-200 bg-red-50 hover:bg-red-100 text-sm font-bold rounded-xl">Cancel this booking</button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <div class="space-y-6">
        <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm space-y-2">
            <p class="text-sm font-bold text-brand-plum">Customer</p>
            <p class="text-sm text-brand-plum"><?= e($booking['customer_name']) ?></p>
            <a class="block text-sm accent-primary hover:underline" href="tel:<?= e($booking['customer_phone']) ?>"><?= e($booking['customer_phone']) ?></a>
            <?php if ($booking['customer_email']): ?>
            <a class="block text-sm accent-primary hover:underline break-all" href="mailto:<?= e($booking['customer_email']) ?>"><?= e($booking['customer_email']) ?></a>
            <?php endif; ?>
            <?php if ($customer_wa !== ''): ?>
            <a href="https://wa.me/<?= e($customer_wa) ?>?text=<?= e(rawurlencode($wa_text)) ?>" target="_blank" rel="noopener"
               class="mt-2 h-11 px-3 inline-flex w-full items-center justify-center rounded-xl border border-brand-sand text-sm font-bold text-brand-plum hover:bg-brand-cream">
                <?= $status === 'confirmed' ? 'Tell them it\'s confirmed on WhatsApp' : 'Message them on WhatsApp' ?>
            </a>
            <?php endif; ?>
        </div>
        <?php if ($booking_link): ?>
        <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm space-y-2">
            <p class="text-sm font-bold text-brand-plum">Their booking page</p>
            <p class="text-xs text-brand-plum/60">What they see: the time, the deposit and whether you've confirmed.</p>
            <a href="<?= e($booking_link) ?>" target="_blank" rel="noopener" class="block text-sm accent-primary hover:underline break-all"><?= e($booking_link) ?></a>
        </div>
        <?php endif; ?>
        <?php if ($booking['task_id'] && in_array($status, ['confirmed', 'completed'], true)): ?>
        <a href="<?= APP_URL ?>/tasks" class="block text-sm font-semibold text-brand-plum/70 hover:text-brand-plum">On your calendar in Tasks →</a>
        <?php endif; ?>
    </div>
</div>
