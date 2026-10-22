<?php
/**
 * Nfelia — MoMo Checkout View
 * Phase 4 — Mobile Money (Cameroon) payment screen
 * Injected variables: $country, $subscription, $amount_due, $kind, $extra_seats, $covered, $book_id, $user, $business
 */
$covered_names = [];
foreach (billing_team(get_db(), (int)$business['id']) as $member) {
    if (in_array((int)$member['id'], $covered, true)) {
        $covered_names[] = $member['full_name'];
    }
}
?>
<div class="max-w-lg mx-auto space-y-6">

    <!-- Header -->
    <div>
        <div class="flex items-center gap-2 mb-1">
            <span class="text-2xl"><?= e($country['flag_emoji'] ?? '🌐') ?></span>
            <span class="text-sm font-semibold text-brand-plum/60"><?= e($country['name']) ?> · Mobile Money</span>
        </div>
        <h1 class="font-heading font-bold text-2xl text-brand-plum">Complete your payment</h1>
        <p class="text-sm text-brand-plum/60 mt-1">We check every payment by hand, usually within a few hours. <?= $kind === 'book' ? 'We make your Records Book' : 'Nfelia opens ' . ($kind === 'seats' ? 'the new seats' : 'the month') ?> as soon as it is approved.</p>
    </div>

    <!-- Payment Instructions Card -->
    <div class="bg-white border-2 border-accent rounded-2xl p-6 shadow-sm">
        <div class="flex items-start gap-4 mb-5">
            <div class="w-10 h-10 rounded-full bg-accent/20 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-accent-text" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <h3 class="font-heading font-bold text-base text-brand-plum mb-1">Step 1: send this exact amount</h3>
                <div class="font-heading font-extrabold text-3xl text-accent-text tracking-tight">
                    <?= e($country['currency_symbol']) ?><?= number_format($amount_due, 0) ?>
                    <span class="text-base font-normal text-brand-plum/50 ml-1"><?= e($country['currency_code']) ?></span>
                </div>
            </div>
        </div>

        <?php if ($country['momo_number']): ?>
        <div class="bg-brand-cream border border-brand-sand rounded-xl p-4 mb-4">
            <p class="text-sm text-brand-plum/60 mb-1">Send it to this Mobile Money number</p>
            <div class="flex items-center justify-between gap-3">
                <p class="font-mono font-extrabold text-xl text-brand-plum tracking-wider" id="momoNumber"><?= e($country['momo_number']) ?></p>
                <button onclick="copyMomoNumber()" class="min-h-[44px] text-sm font-semibold text-accent-text border border-accent/30 px-4 py-1.5 rounded-lg hover:bg-accent hover:text-on-accent transition-colors">
                    Copy
                </button>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($country['payment_instructions']): ?>
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-sm text-blue-800 font-medium leading-relaxed">
            <p class="font-semibold text-sm text-blue-900 mb-1">How to pay</p>
            <?= nl2br(e($country['payment_instructions'])) ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Submission Form -->
    <div class="bg-white border border-brand-sand rounded-2xl p-6 shadow-sm">
        <h3 class="font-heading font-bold text-base text-brand-plum mb-5">Step 2: upload your receipt</h3>

        <form action="<?= APP_URL ?>/momo/submit" method="POST" enctype="multipart/form-data" id="momoForm" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="kind" value="<?= e($kind) ?>">
            <input type="hidden" name="seats" value="<?= (int)$extra_seats ?>">
            <input type="hidden" name="covered" value="<?= e(implode(',', $covered)) ?>">
            <?php if ($kind === 'book'): ?><input type="hidden" name="book" value="<?= (int)$book_id ?>"><?php endif; ?>

            <!-- Receipt Upload -->
            <div>
                <label class="block text-sm font-semibold text-brand-plum mb-2">
                    Receipt or screenshot of the payment <span class="text-accent-text">*</span>
                </label>
                <label for="receiptFile" class="flex flex-col items-center justify-center w-full h-36 border-2 border-dashed border-brand-sand rounded-xl cursor-pointer hover:border-accent transition-colors bg-brand-cream/30 relative">
                    <input type="file" id="receiptFile" name="receipt" accept="image/jpeg,image/png,image/webp,application/pdf"
                           class="absolute inset-0 opacity-0 cursor-pointer" required onchange="previewFile(this)">
                    <div id="uploadPlaceholder" class="text-center">
                        <svg class="w-8 h-8 text-brand-plum/30 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <p class="text-xs font-semibold text-brand-plum/60">Click to upload receipt</p>
                        <p class="text-xs text-brand-plum/60 mt-1">JPG, PNG, WebP or PDF, up to 5MB</p>
                    </div>
                    <div id="uploadPreview" class="hidden text-center">
                        <svg class="w-8 h-8 text-green-500 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-xs font-semibold text-brand-plum" id="fileName">File selected</p>
                        <p class="text-xs text-brand-plum/60" id="fileSize"></p>
                    </div>
                </label>
            </div>

            <!-- Reference Number -->
            <div>
                <label class="block text-sm font-semibold text-brand-plum mb-2">
                    Transaction reference <span class="text-brand-plum/60 font-normal">(optional, but it helps us find it)</span>
                </label>
                <input type="text" name="reference_number" maxlength="255"
                       class="w-full px-4 py-3 border border-brand-sand rounded-xl text-sm font-mono focus:border-accent-text outline-none"
                       placeholder="e.g. TXN123456789">
                <p class="text-xs text-brand-plum/60 mt-1">It is in the SMS Mobile Money sent when you paid.</p>
            </div>

            <!-- Amount Confirmation -->
            <div class="bg-brand-cream/50 border border-brand-sand rounded-xl p-4 flex items-center justify-between">
                <div>
                    <p class="text-sm text-brand-plum/60">Confirming payment of</p>
                    <p class="font-heading font-bold text-lg text-brand-plum">
                        <?= e($country['currency_symbol']) ?><?= number_format($amount_due, 0) ?>
                        <span class="text-xs font-normal text-brand-plum/50"><?= e($country['currency_code']) ?></span>
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-brand-plum/60">For</p>
                    <?php if ($kind === 'book'): ?>
                        <p class="text-xs font-semibold text-brand-plum">A Records Book</p>
                    <?php elseif ($kind === 'seats'): ?>
                        <p class="text-xs font-semibold text-brand-plum"><?= (int)$extra_seats ?> more seat<?= $extra_seats > 1 ? 's' : '' ?> this month</p>
                    <?php else: ?>
                        <p class="text-xs font-semibold text-brand-plum">A month of Nfelia</p>
                        <?php if ($extra_seats > 0): ?>
                            <p class="text-xs text-brand-plum/60">+ <?= (int)$extra_seats ?> staff seat<?= $extra_seats > 1 ? 's' : '' ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($kind === 'month'): ?>
            <p class="text-sm text-brand-plum/70 leading-relaxed">
                Covers you<?= $covered_names ? ' and ' . e(implode(', ', $covered_names)) : ' only' ?>.
                Anyone else on your team is switched off when the payment is approved.
                <a href="<?= APP_URL ?>/settings?tab=billing" class="font-bold text-accent-text">Change</a>
            </p>
            <?php endif; ?>

            <button type="submit" id="submitBtn"
                    class="w-full btn-primary text-white min-h-[48px] py-4 rounded-xl text-base font-semibold shadow-sm hover:shadow-md transition-all">
                I have paid: send my receipt
            </button>

            <p class="text-center text-xs text-brand-plum/60 leading-relaxed">
                Your receipt is checked by hand by our team. We will tell you by email and here in the app.
                Your data stays safe while we check.
            </p>
        </form>
    </div>

    <!-- Back link -->
    <div class="text-center">
        <a href="<?= APP_URL ?>/settings?tab=billing" class="inline-flex items-center min-h-[44px] text-sm font-semibold text-brand-plum/60 hover:text-brand-plum transition-colors">
            ← Back to Billing
        </a>
    </div>
</div>

<script>
function copyMomoNumber() {
    const num = document.getElementById('momoNumber').textContent.trim();
    navigator.clipboard.writeText(num).then(() => {
        const btn = event.target.closest('button');
        const orig = btn.textContent;
        btn.textContent = 'Copied!';
        btn.classList.add('bg-green-500', 'border-green-500', 'text-white');
        setTimeout(() => { btn.textContent = orig; btn.classList.remove('bg-green-500', 'border-green-500', 'text-white'); }, 2000);
    });
}

function previewFile(input) {
    const file = input.files[0];
    if (!file) return;
    document.getElementById('uploadPlaceholder').classList.add('hidden');
    document.getElementById('uploadPreview').classList.remove('hidden');
    document.getElementById('fileName').textContent = file.name;
    document.getElementById('fileSize').textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';
}

document.getElementById('momoForm').addEventListener('submit', function(e) {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.textContent = 'Sending…';
});
</script>
