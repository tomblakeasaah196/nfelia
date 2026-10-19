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
            <span class="data-text text-xs font-bold text-brand-plum/50 uppercase tracking-widest"><?= e($country['name']) ?> · Mobile Money</span>
        </div>
        <h1 class="font-heading font-bold text-2xl text-brand-plum">Complete Your Payment</h1>
        <p class="text-sm text-brand-plum/60 mt-1">We check every payment by hand, usually within a few hours. <?= $kind === 'book' ? 'We make your Records Book' : 'Nfelia opens ' . ($kind === 'seats' ? 'the new seats' : 'the month') ?> as soon as it is approved.</p>
    </div>

    <!-- Payment Instructions Card -->
    <div class="bg-white border-2 border-brand-gold rounded-2xl p-6 shadow-sm">
        <div class="flex items-start gap-4 mb-5">
            <div class="w-10 h-10 rounded-full bg-brand-gold/20 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-brand-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <h3 class="font-heading font-bold text-sm text-brand-plum uppercase tracking-wider mb-1">Step 1 — Send This Exact Amount</h3>
                <div class="font-heading font-extrabold text-3xl text-brand-burgundy tracking-tight">
                    <?= e($country['currency_symbol']) ?><?= number_format($amount_due, 0) ?>
                    <span class="text-base font-normal text-brand-plum/50 ml-1"><?= e($country['currency_code']) ?></span>
                </div>
            </div>
        </div>

        <?php if ($country['momo_number']): ?>
        <div class="bg-brand-cream border border-brand-sand rounded-xl p-4 mb-4">
            <p class="text-[10px] font-bold text-brand-plum/50 uppercase tracking-widest mb-1">Send to this Mobile Money Number</p>
            <div class="flex items-center justify-between gap-3">
                <p class="font-mono font-extrabold text-xl text-brand-plum tracking-wider" id="momoNumber"><?= e($country['momo_number']) ?></p>
                <button onclick="copyMomoNumber()" class="text-[10px] font-bold text-brand-burgundy border border-brand-burgundy/30 px-3 py-1.5 rounded-lg hover:bg-brand-burgundy hover:text-white transition-colors uppercase tracking-wider">
                    Copy
                </button>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($country['payment_instructions']): ?>
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-sm text-blue-800 font-medium leading-relaxed">
            <p class="font-bold text-[10px] uppercase tracking-widest text-blue-700 mb-1">Instructions</p>
            <?= nl2br(e($country['payment_instructions'])) ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Submission Form -->
    <div class="bg-white border border-brand-sand rounded-2xl p-6 shadow-sm">
        <h3 class="font-heading font-bold text-sm text-brand-plum uppercase tracking-wider mb-5">Step 2 — Upload Your Receipt</h3>

        <form action="<?= APP_URL ?>/momo/submit" method="POST" enctype="multipart/form-data" id="momoForm" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="kind" value="<?= e($kind) ?>">
            <input type="hidden" name="seats" value="<?= (int)$extra_seats ?>">
            <input type="hidden" name="covered" value="<?= e(implode(',', $covered)) ?>">
            <?php if ($kind === 'book'): ?><input type="hidden" name="book" value="<?= (int)$book_id ?>"><?php endif; ?>

            <!-- Receipt Upload -->
            <div>
                <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-2">
                    Payment Receipt / Screenshot <span class="text-brand-burgundy">*</span>
                </label>
                <label for="receiptFile" class="flex flex-col items-center justify-center w-full h-36 border-2 border-dashed border-brand-sand rounded-xl cursor-pointer hover:border-brand-gold transition-colors bg-brand-cream/30 relative">
                    <input type="file" id="receiptFile" name="receipt" accept="image/jpeg,image/png,image/webp,application/pdf"
                           class="absolute inset-0 opacity-0 cursor-pointer" required onchange="previewFile(this)">
                    <div id="uploadPlaceholder" class="text-center">
                        <svg class="w-8 h-8 text-brand-plum/30 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <p class="text-xs font-semibold text-brand-plum/60">Click to upload receipt</p>
                        <p class="text-[10px] text-brand-plum/40 mt-1">JPG, PNG, WebP or PDF · Max 5MB</p>
                    </div>
                    <div id="uploadPreview" class="hidden text-center">
                        <svg class="w-8 h-8 text-green-500 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-xs font-semibold text-brand-plum" id="fileName">File selected</p>
                        <p class="text-[10px] text-brand-plum/50" id="fileSize"></p>
                    </div>
                </label>
            </div>

            <!-- Reference Number -->
            <div>
                <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-2">
                    Transaction Reference Number <span class="text-brand-plum/40 normal-case">(optional but recommended)</span>
                </label>
                <input type="text" name="reference_number" maxlength="255"
                       class="w-full px-4 py-3 border border-brand-sand rounded-xl text-sm font-mono focus:border-brand-gold outline-none"
                       placeholder="e.g. TXN123456789">
                <p class="text-[10px] text-brand-plum/40 mt-1">Found in your Mobile Money SMS confirmation message.</p>
            </div>

            <!-- Amount Confirmation -->
            <div class="bg-brand-cream/50 border border-brand-sand rounded-xl p-4 flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-bold text-brand-plum/50 uppercase tracking-widest">Confirming payment of</p>
                    <p class="font-heading font-bold text-lg text-brand-plum">
                        <?= e($country['currency_symbol']) ?><?= number_format($amount_due, 0) ?>
                        <span class="text-xs font-normal text-brand-plum/50"><?= e($country['currency_code']) ?></span>
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-[10px] font-bold text-brand-plum/50 uppercase tracking-widest">For</p>
                    <?php if ($kind === 'book'): ?>
                        <p class="text-xs font-semibold text-brand-plum">A Records Book</p>
                    <?php elseif ($kind === 'seats'): ?>
                        <p class="text-xs font-semibold text-brand-plum"><?= (int)$extra_seats ?> more seat<?= $extra_seats > 1 ? 's' : '' ?> this month</p>
                    <?php else: ?>
                        <p class="text-xs font-semibold text-brand-plum">A month of Nfelia</p>
                        <?php if ($extra_seats > 0): ?>
                            <p class="text-[10px] text-brand-plum/50">+ <?= (int)$extra_seats ?> staff seat<?= $extra_seats > 1 ? 's' : '' ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($kind === 'month'): ?>
            <p class="text-[11px] text-brand-plum/70 leading-relaxed">
                Covers you<?= $covered_names ? ' and ' . e(implode(', ', $covered_names)) : ' only' ?>.
                Anyone else on your team is switched off when the payment is approved.
                <a href="<?= APP_URL ?>/settings?tab=billing" class="font-bold text-brand-burgundy">Change</a>
            </p>
            <?php endif; ?>

            <button type="submit" id="submitBtn"
                    class="w-full btn-primary text-white py-4 rounded-xl text-xs font-bold uppercase tracking-widest shadow-sm hover:shadow-md transition-all">
                I Have Paid — Submit for Verification
            </button>

            <p class="text-center text-[10px] text-brand-plum/40 leading-relaxed">
                Your receipt is checked by hand by our team. We will tell you by email and here in the app.
                Your data stays safe while we check.
            </p>
        </form>
    </div>

    <!-- Back link -->
    <div class="text-center">
        <a href="<?= APP_URL ?>/settings?tab=billing" class="text-xs font-semibold text-brand-plum/50 hover:text-brand-plum uppercase tracking-widest transition-colors">
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
    btn.textContent = 'Submitting...';
});
</script>
