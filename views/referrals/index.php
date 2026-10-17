<?php
/**
 * views/referrals/index.php — Tenant referral dashboard
 * Variables: $code, $referral_url, $stats, $referrals, $business
 */
$currency = $business['currency_symbol'] ?? '₦';
$pending_discount = number_format(($stats['pending_discount_total'] ?? 0) / 100, 0);
?>

<div class="max-w-4xl mx-auto px-4 py-6 space-y-6">

    <!-- Header -->
    <div>
        <h1 class="text-2xl font-bold text-brand-plum">Referral Programme</h1>
        <p class="text-sm text-brand-plum/60 mt-1">Share your code, earn <?= $currency ?>2,500 off your next bill for every business that subscribes.</p>
    </div>

    <!-- Referral Code Card -->
    <div class="bg-gradient-to-br from-brand-plum to-brand-plum/80 rounded-2xl p-6 text-white" x-data="{ copied: false }">
        <p class="text-sm text-white/70 mb-2">Your Referral Code</p>
        <div class="flex items-center gap-4 flex-wrap">
            <span class="text-3xl font-bold tracking-wider font-mono"><?= htmlspecialchars($code) ?></span>
            <button @click="navigator.clipboard.writeText('<?= htmlspecialchars($referral_url) ?>'); copied = true; setTimeout(() => copied = false, 2000)"
                    class="px-4 py-2 bg-white/20 hover:bg-white/30 rounded-lg text-sm font-medium transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                <span x-text="copied ? 'Copied!' : 'Copy Link'"></span>
            </button>
            <a href="https://wa.me/?text=<?= urlencode("Join me on Nfelia — the all-in-one business tool for Nigerian traders! Sign up with my code and we both win. 🚀\n\n" . $referral_url) ?>"
               target="_blank" class="px-4 py-2 bg-green-500 hover:bg-green-600 rounded-lg text-sm font-medium transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492a.75.75 0 00.913.913l4.458-1.495A11.952 11.952 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-2.387 0-4.592-.822-6.328-2.2l-.442-.36-3.085 1.034 1.034-3.085-.36-.442A9.96 9.96 0 012 12C2 6.486 6.486 2 12 2s10 4.486 10 10-4.486 10-10 10z"/></svg>
                Share on WhatsApp
            </a>
        </div>
        <p class="text-xs text-white/50 mt-3">Registration link: <?= htmlspecialchars($referral_url) ?></p>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
            <p class="text-2xl font-bold text-brand-plum"><?= (int)($stats['total_referrals'] ?? 0) ?></p>
            <p class="text-xs text-gray-500 mt-1">Total Shared</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
            <p class="text-2xl font-bold text-blue-600"><?= (int)($stats['converted'] ?? 0) ?></p>
            <p class="text-xs text-gray-500 mt-1">Signed Up</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
            <p class="text-2xl font-bold text-green-600"><?= (int)($stats['rewarded'] ?? 0) ?></p>
            <p class="text-xs text-gray-500 mt-1">Rewarded</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
            <p class="text-2xl font-bold text-brand-gold"><?= $currency ?><?= $pending_discount ?></p>
            <p class="text-xs text-gray-500 mt-1">Pending Credit</p>
        </div>
    </div>

    <?php if ($stats['pending_discounts'] ?? 0): ?>
    <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-3 text-sm text-green-800 flex items-center gap-2">
        <svg class="w-5 h-5 text-green-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        You have <strong><?= $currency ?><?= $pending_discount ?></strong> in referral credits. This will be applied to your next billing cycle automatically.
    </div>
    <?php endif; ?>

    <!-- How It Works -->
    <div class="bg-white rounded-xl border border-gray-100 p-6">
        <h2 class="font-semibold text-brand-plum mb-4">How It Works</h2>
        <div class="grid sm:grid-cols-3 gap-4">
            <div class="flex gap-3">
                <div class="w-8 h-8 rounded-full bg-brand-plum/10 flex items-center justify-center text-brand-plum font-bold text-sm shrink-0">1</div>
                <div>
                    <p class="font-medium text-sm text-gray-900">Share Your Code</p>
                    <p class="text-xs text-gray-500 mt-0.5">Send your referral link to another business owner via WhatsApp or any channel.</p>
                </div>
            </div>
            <div class="flex gap-3">
                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold text-sm shrink-0">2</div>
                <div>
                    <p class="font-medium text-sm text-gray-900">They Sign Up</p>
                    <p class="text-xs text-gray-500 mt-0.5">They register on Nfelia using your code and get a 14-day free trial.</p>
                </div>
            </div>
            <div class="flex gap-3">
                <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center text-green-600 font-bold text-sm shrink-0">3</div>
                <div>
                    <p class="font-medium text-sm text-gray-900">You Earn <?= $currency ?>2,500</p>
                    <p class="text-xs text-gray-500 mt-0.5">When they make their first payment, you get 25% off your next bill.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Referral History -->
    <?php if (!empty($referrals)): ?>
    <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-brand-plum">Referral History</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left">Business</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Discount</th>
                        <th class="px-4 py-3 text-left">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($referrals as $ref): ?>
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-4 py-3 font-medium text-gray-900">
                            <?= htmlspecialchars($ref['referred_business_name'] ?? $ref['referred_email'] ?? '—') ?>
                        </td>
                        <td class="px-4 py-3">
                            <?php
                            $badge = match($ref['status']) {
                                'pending'   => 'bg-gray-100 text-gray-600',
                                'converted' => 'bg-blue-100 text-blue-700',
                                'rewarded'  => 'bg-green-100 text-green-700',
                                'expired'   => 'bg-red-100 text-red-600',
                                default     => 'bg-gray-100 text-gray-600',
                            };
                            ?>
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $badge ?>">
                                <?= ucfirst($ref['status']) ?>
                            </span>
                            <?php if ($ref['status'] === 'rewarded' && $ref['discount_applied']): ?>
                                <span class="text-xs text-gray-400 ml-1">Used</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-gray-600"><?= $currency ?><?= number_format($ref['discount_amount'] / 100, 0) ?></td>
                        <td class="px-4 py-3 text-gray-400 text-xs"><?= date('M j, Y', strtotime($ref['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
