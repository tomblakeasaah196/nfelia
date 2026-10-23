<?php
/**
 * Nfelia — Bookings list (BookingController::index)
 * Variables: $business, $tab ('requests', 'upcoming' or 'past'), $counts, $bookings, $bookable_count
 */
$currency = $business['currency_symbol'] ?? '₦';
$badges = [
    'pending'   => ['To confirm', 'bg-amber-100 text-amber-800'],
    'confirmed' => ['Confirmed', 'bg-green-100 text-green-800'],
    'declined'  => ['Declined', 'bg-gray-100 text-gray-600'],
    'cancelled' => ['Cancelled', 'bg-gray-100 text-gray-600'],
    'completed' => ['Done', 'bg-emerald-100 text-emerald-800'],
];
$tabs = ['requests' => 'Requests', 'upcoming' => 'Coming up', 'past' => 'Past'];
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h1 class="font-heading font-extrabold text-2xl sm:text-3xl text-brand-plum tracking-tight">Bookings</h1>
        <p class="text-sm text-brand-plum/60 mt-1">People book on your shop and pay the deposit by transfer. Confirm each request once you see the money.</p>
    </div>
    <a href="<?= APP_URL ?>/bookings/settings" class="h-11 px-4 inline-flex items-center justify-center gap-2 rounded-xl border border-brand-sand bg-brand-white text-sm font-bold text-brand-plum hover:bg-brand-cream shrink-0">Booking hours</a>
</div>

<?php if (!$bookable_count): ?>
<div class="bg-brand-white border border-brand-sand rounded-xl p-5 mb-6">
    <p class="text-sm font-bold text-brand-plum">Nothing can be booked yet</p>
    <p class="text-sm text-brand-plum/60 mt-1">Open a service in <a class="underline font-semibold" href="<?= APP_URL ?>/products">your products</a>, turn on “People book this”, and set how long it takes and the deposit. A Book button then shows on your shop.</p>
</div>
<?php endif; ?>

<nav class="flex gap-1 bg-gray-100 rounded-xl p-1 mb-6 overflow-x-auto" aria-label="Bookings">
    <?php foreach ($tabs as $key => $label): ?>
    <a href="<?= APP_URL ?>/bookings<?= $key === 'requests' ? '' : '?show=' . $key ?>"
       class="px-4 py-2 rounded-lg text-sm font-semibold whitespace-nowrap <?= $tab === $key ? 'bg-white text-brand-plum shadow-sm' : 'text-gray-500 hover:text-gray-700' ?>"
       <?= $tab === $key ? 'aria-current="page"' : '' ?>>
        <?= $label ?><?php if (!empty($counts[$key])): ?> <span class="ml-1 px-1.5 rounded-full text-xs <?= $key === 'requests' ? 'bg-amber-500 text-white' : 'bg-gray-200 text-gray-700' ?>"><?= (int)$counts[$key] ?></span><?php endif; ?>
    </a>
    <?php endforeach; ?>
</nav>

<div class="space-y-3">
    <?php foreach ($bookings as $b): [$badge, $badge_class] = $badges[$b['status']] ?? [$b['status'], 'bg-gray-100 text-gray-600']; ?>
    <a href="<?= APP_URL ?>/bookings/view/<?= (int)$b['id'] ?>" class="block bg-brand-white border border-brand-sand rounded-xl p-4 sm:p-5 shadow-sm hover:border-brand-plum/30">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-base font-bold text-brand-plum"><?= e(booking_when($b)) ?></p>
                <p class="text-sm text-brand-plum/80 mt-0.5"><?= e($b['product_name']) ?> · <?= e($b['customer_name']) ?></p>
                <p class="text-xs text-brand-plum/50 mt-1">
                    <?= e($b['booking_number']) ?> ·
                    <?= $b['deposit_due'] > 0
                        ? 'Deposit ' . e(sf_money((float)$b['deposit_due'], $currency)) . ($b['payment_proof_path'] ? ', proof sent' : ($b['channel'] === 'whatsapp' ? ', on WhatsApp' : ''))
                        : 'No deposit' ?><?php if ((float)($b['caution_fee'] ?? 0) > 0 && !$b['caution_refunded_at'] && strtotime((string)$b['ends_at']) <= time() && in_array($b['status'], ['confirmed', 'completed'], true)): ?> · <span class="font-bold text-amber-800">Caution <?= e(sf_money((float)$b['caution_fee'], $currency)) ?> to give back</span><?php endif; ?>
                </p>
            </div>
            <span class="shrink-0 px-2.5 py-1 rounded-full text-xs font-bold <?= $badge_class ?>"><?= e($badge) ?></span>
        </div>
    </a>
    <?php endforeach; ?>
    <?php if (!$bookings): ?>
    <div class="text-center py-12 text-brand-plum/50">
        <p class="text-sm"><?= $tab === 'requests' ? 'No requests waiting. New bookings show here first.' : ($tab === 'upcoming' ? 'Nothing confirmed coming up.' : 'No past bookings yet.') ?></p>
    </div>
    <?php endif; ?>
</div>
