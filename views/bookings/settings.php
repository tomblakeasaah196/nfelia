<?php
/**
 * Nfelia — Booking hours and rules (BookingController::settings)
 * Variables: $business, $settings (booking_settings)
 */
$notice = [0 => 'Any time', 1 => '1 hour', 2 => '2 hours', 4 => '4 hours', 12 => '12 hours', 24 => '1 day', 48 => '2 days', 72 => '3 days'];
$ahead  = [7 => '1 week', 14 => '2 weeks', 30 => '1 month', 60 => '2 months', 90 => '3 months', 180 => '6 months', 365 => '1 year'];
$input  = 'h-11 px-3 rounded-lg border border-brand-sand bg-white text-sm';
?>
<div class="mb-6">
    <a href="<?= APP_URL ?>/bookings" class="text-sm font-semibold text-brand-plum/60 hover:text-brand-plum">← Bookings</a>
    <h1 class="font-heading font-extrabold text-2xl sm:text-3xl text-brand-plum tracking-tight mt-2">Booking hours</h1>
    <p class="text-sm text-brand-plum/60 mt-1">Shoppers can book times inside these hours that aren't taken by another booking or by an appointment in Tasks & Calendar.</p>
</div>

<form method="POST" action="<?= APP_URL ?>/bookings/settings" class="space-y-6 max-w-2xl">
    <?= csrf_field() ?>
    <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm">
        <h2 class="text-base font-bold text-brand-plum mb-4">Opening hours</h2>
        <div class="space-y-3">
            <?php foreach (BOOKING_DAYS as $key => $day): $h = $settings['hours'][$key]; ?>
            <div class="flex flex-wrap items-center gap-3" x-data="{ open: <?= $h ? 'true' : 'false' ?> }">
                <label class="flex items-center gap-2 w-36 text-sm font-semibold text-brand-plum">
                    <input type="checkbox" name="open_<?= $key ?>" value="1" x-model="open" class="w-5 h-5" <?= $h ? 'checked' : '' ?>>
                    <?= $day ?>
                </label>
                <div class="flex items-center gap-2" x-show="open">
                    <label class="sr-only" for="from-<?= $key ?>"><?= $day ?> opens</label>
                    <input id="from-<?= $key ?>" type="time" name="from_<?= $key ?>" value="<?= e($h['open'] ?? '09:00') ?>" class="<?= $input ?>">
                    <span class="text-sm text-brand-plum/50">to</span>
                    <label class="sr-only" for="to-<?= $key ?>"><?= $day ?> closes</label>
                    <input id="to-<?= $key ?>" type="time" name="to_<?= $key ?>" value="<?= e($h['close'] ?? '18:00') ?>" class="<?= $input ?>">
                </div>
                <span class="text-sm text-brand-plum/40" x-show="!open">Closed</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm grid sm:grid-cols-2 gap-4">
        <label class="block">
            <span class="block text-sm font-semibold text-brand-plum mb-1">Book at least</span>
            <select name="notice" class="<?= $input ?> w-full">
                <?php foreach ($notice as $v => $label): ?><option value="<?= $v ?>" <?= $settings['notice'] === $v ? 'selected' : '' ?>><?= $label ?> ahead</option><?php endforeach; ?>
            </select>
        </label>
        <label class="block">
            <span class="block text-sm font-semibold text-brand-plum mb-1">Book up to</span>
            <select name="ahead" class="<?= $input ?> w-full">
                <?php foreach ($ahead as $v => $label): ?><option value="<?= $v ?>" <?= $settings['ahead'] === $v ? 'selected' : '' ?>><?= $label ?> ahead</option><?php endforeach; ?>
            </select>
        </label>
        <label class="block">
            <span class="block text-sm font-semibold text-brand-plum mb-1">Start times every</span>
            <select name="step" class="<?= $input ?> w-full">
                <?php foreach ([15, 30, 60] as $v): ?><option value="<?= $v ?>" <?= $settings['step'] === $v ? 'selected' : '' ?>><?= $v === 60 ? 'hour' : $v . ' minutes' ?></option><?php endforeach; ?>
            </select>
        </label>
        <label class="block">
            <span class="block text-sm font-semibold text-brand-plum mb-1">Customers at the same time</span>
            <input type="number" name="at_once" min="1" max="20" value="<?= (int)$settings['at_once'] ?>" class="<?= $input ?> w-full">
            <span class="block text-xs text-brand-plum/50 mt-1">For time slots. Two stylists? Put 2.</span>
        </label>
        <label class="block">
            <span class="block text-sm font-semibold text-brand-plum mb-1">Check-in from</span>
            <input type="time" name="checkin" value="<?= e($settings['checkin']) ?>" class="<?= $input ?> w-full">
            <span class="block text-xs text-brand-plum/50 mt-1">For stays booked by the night.</span>
        </label>
        <label class="block">
            <span class="block text-sm font-semibold text-brand-plum mb-1">Check-out by</span>
            <input type="time" name="checkout" value="<?= e($settings['checkout']) ?>" class="<?= $input ?> w-full">
        </label>
    </div>

    <button type="submit" class="h-12 px-6 btn-primary text-white text-sm font-bold rounded-xl">Save booking hours</button>
</form>
