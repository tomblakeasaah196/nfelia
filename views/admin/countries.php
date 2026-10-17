<?php
/**
 * Nfelia — Admin: Countries Management View
 * Phase 3 — Multi-Country System
 * Accessible at /admin/countries (admin/business_id=1 only)
 */

// $countries injected by CountryController
$payment_methods = [
    'paystack'     => 'Paystack (Card / Bank Transfer)',
    'mobile_money' => 'Mobile Money (Manual Upload)',
    'stripe'       => 'Stripe',
    'manual'       => 'Manual (Pay & Submit Proof)',
    'other'        => 'Other',
];
?>

<div class="space-y-6">

    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="font-heading font-bold text-2xl text-brand-plum">Country Management</h1>
            <p class="text-xs text-brand-plum/60 mt-1">Add markets, configure payment methods, and set per-country subscription pricing.</p>
        </div>
        <button onclick="document.getElementById('addCountryModal').classList.remove('hidden')"
                class="btn-primary text-white text-xs font-bold px-5 py-3 rounded-xl uppercase tracking-widest">
            + Add Country
        </button>
    </div>

    <!-- Countries Table -->
    <div class="bg-white border border-brand-sand rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-brand-cream/50 border-b border-brand-sand">
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Country</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Currency</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Payment Method</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-right">Monthly Price</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-right">Seat Price</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-center">Businesses</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-center">Status</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-sand/50">
                    <?php foreach ($countries as $c): ?>
                    <tr class="hover:bg-brand-cream/20 transition-colors">
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <span class="text-2xl"><?= e($c['flag_emoji'] ?? '🌐') ?></span>
                                <div>
                                    <div class="font-semibold text-sm text-brand-plum"><?= e($c['name']) ?></div>
                                    <div class="data-text text-[10px] text-brand-plum/50"><?= e($c['iso_code']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-4">
                            <div class="text-sm text-brand-plum font-semibold"><?= e($c['currency_symbol']) ?> <?= e($c['currency_code']) ?></div>
                            <div class="text-[10px] text-brand-plum/50"><?= e($c['currency_name']) ?></div>
                        </td>
                        <td class="px-5 py-4">
                            <span class="text-xs font-semibold text-brand-plum px-2 py-1 rounded-full
                                <?= $c['payment_method'] === 'paystack' ? 'bg-green-100 text-green-700' :
                                   ($c['payment_method'] === 'mobile_money' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700') ?>">
                                <?= e($payment_methods[$c['payment_method']] ?? $c['payment_method']) ?>
                            </span>
                            <?php if ($c['momo_number']): ?>
                                <div class="data-text text-[9px] text-brand-plum/50 mt-1"><?= e($c['momo_number']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-4 text-right">
                            <div class="font-semibold text-sm text-brand-plum data-text">
                                <?= e($c['currency_symbol']) ?><?= number_format($c['monthly_price'], 0) ?>
                            </div>
                        </td>
                        <td class="px-5 py-4 text-right">
                            <div class="text-sm text-brand-plum/70 data-text">
                                <?= e($c['currency_symbol']) ?><?= number_format($c['extra_seat_price'], 0) ?>
                            </div>
                        </td>
                        <td class="px-5 py-4 text-center">
                            <span class="data-text font-bold text-sm text-brand-plum"><?= (int)$c['business_count'] ?></span>
                        </td>
                        <td class="px-5 py-4 text-center">
                            <?php if ($c['iso_code'] === 'NG'): ?>
                                <span class="text-[9px] font-bold text-green-700 bg-green-100 px-2 py-1 rounded-full uppercase">Core Market</span>
                            <?php else: ?>
                                <button
                                    onclick="toggleCountry(<?= (int)$c['id'] ?>, this)"
                                    data-active="<?= $c['is_active'] ? '1' : '0' ?>"
                                    class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none
                                           <?= $c['is_active'] ? 'bg-green-500' : 'bg-gray-300' ?>">
                                    <span class="inline-block h-4 w-4 rounded-full bg-white shadow transition-transform
                                                 <?= $c['is_active'] ? 'translate-x-4' : 'translate-x-0.5' ?>"></span>
                                </button>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-4 text-center">
                            <button onclick="openEditModal(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)"
                                    class="text-xs font-bold text-brand-burgundy hover:underline uppercase tracking-wider">
                                Edit
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($countries)): ?>
                    <tr>
                        <td colspan="8" class="px-5 py-12 text-center text-brand-plum/50 text-sm">No countries configured.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ADD COUNTRY MODAL -->
<div id="addCountryModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-brand-plum/60 backdrop-blur-sm">
    <div class="bg-white rounded-2xl border border-brand-sand w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-2xl">
        <div class="sticky top-0 bg-white border-b border-brand-sand px-6 py-4 flex justify-between items-center">
            <h3 class="font-heading font-bold text-lg text-brand-plum uppercase tracking-wider">Add New Country</h3>
            <button onclick="document.getElementById('addCountryModal').classList.add('hidden')" class="text-brand-plum/50 hover:text-brand-plum p-1">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form action="<?= APP_URL ?>/countries/add" method="POST" class="p-6 space-y-5">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Country Name *</label>
                    <input type="text" name="name" required class="w-full px-4 py-3 border border-brand-sand rounded-xl text-sm focus:border-brand-gold outline-none" placeholder="e.g. Cameroon">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">ISO Code (2 letters) *</label>
                    <input type="text" name="iso_code" required maxlength="2" class="w-full px-4 py-3 border border-brand-sand rounded-xl text-sm uppercase focus:border-brand-gold outline-none font-mono" placeholder="CM">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Flag Emoji</label>
                    <input type="text" name="flag_emoji" maxlength="10" class="w-full px-4 py-3 border border-brand-sand rounded-xl text-sm focus:border-brand-gold outline-none" placeholder="🇨🇲">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Currency Name *</label>
                    <input type="text" name="currency_name" required class="w-full px-4 py-3 border border-brand-sand rounded-xl text-sm focus:border-brand-gold outline-none" placeholder="CFA Franc BEAC">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Currency Code *</label>
                    <input type="text" name="currency_code" required maxlength="10" class="w-full px-4 py-3 border border-brand-sand rounded-xl text-sm uppercase focus:border-brand-gold outline-none font-mono" placeholder="XAF">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Currency Symbol *</label>
                    <input type="text" name="currency_symbol" required maxlength="10" class="w-full px-4 py-3 border border-brand-sand rounded-xl text-sm focus:border-brand-gold outline-none" placeholder="FCFA">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Payment Method *</label>
                    <select name="payment_method" id="addPaymentMethod" class="w-full px-4 py-3 border border-brand-sand rounded-xl text-sm focus:border-brand-gold outline-none" onchange="toggleMomoFields('add', this.value)">
                        <?php foreach ($payment_methods as $val => $label): ?>
                            <option value="<?= e($val) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Monthly Price (local currency) *</label>
                    <input type="number" step="0.01" name="monthly_price" required class="w-full px-4 py-3 border border-brand-sand rounded-xl text-sm focus:border-brand-gold outline-none" placeholder="6500">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Extra Seat Price</label>
                    <input type="number" step="0.01" name="extra_seat_price" class="w-full px-4 py-3 border border-brand-sand rounded-xl text-sm focus:border-brand-gold outline-none" placeholder="3000">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Trial Days</label>
                    <input type="number" name="trial_days" value="14" min="1" max="90" class="w-full px-4 py-3 border border-brand-sand rounded-xl text-sm focus:border-brand-gold outline-none">
                </div>
            </div>

            <div id="addMomoFields" class="space-y-4">
                <div>
                    <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Mobile Money Number</label>
                    <input type="text" name="momo_number" class="w-full px-4 py-3 border border-brand-sand rounded-xl text-sm focus:border-brand-gold outline-none font-mono" placeholder="+237 6XX XXX XXX">
                    <p class="text-[10px] text-brand-plum/50 mt-1">This number is displayed to users at checkout. Leave blank if not applicable.</p>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Payment Instructions (shown at checkout)</label>
                    <textarea name="payment_instructions" rows="3" class="w-full px-4 py-3 border border-brand-sand rounded-xl text-sm focus:border-brand-gold outline-none resize-none" placeholder="Send the exact amount to the number above via MTN/Orange Money, then upload a screenshot as proof."></textarea>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <input type="checkbox" name="is_active" id="addIsActive" value="1" checked class="w-4 h-4 accent-brand-burgundy">
                <label for="addIsActive" class="text-xs font-semibold text-brand-plum uppercase tracking-wider">Active (visible to users immediately)</label>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="flex-1 btn-primary text-white py-3 rounded-xl text-xs font-bold uppercase tracking-widest">Add Country</button>
                <button type="button" onclick="document.getElementById('addCountryModal').classList.add('hidden')" class="flex-1 bg-brand-cream text-brand-plum py-3 rounded-xl text-xs font-bold uppercase tracking-widest border border-brand-sand">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT COUNTRY MODAL -->
<div id="editCountryModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-brand-plum/60 backdrop-blur-sm">
    <div class="bg-white rounded-2xl border border-brand-sand w-full max-w-xl max-h-[90vh] overflow-y-auto shadow-2xl">
        <div class="sticky top-0 bg-white border-b border-brand-sand px-6 py-4 flex justify-between items-center">
            <h3 class="font-heading font-bold text-lg text-brand-plum uppercase tracking-wider" id="editModalTitle">Edit Country</h3>
            <button onclick="document.getElementById('editCountryModal').classList.add('hidden')" class="text-brand-plum/50 hover:text-brand-plum p-1">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form action="<?= APP_URL ?>/countries/edit" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="country_id" id="editCountryId">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Currency Symbol</label>
                    <input type="text" name="currency_symbol" id="editCurrencySymbol" maxlength="10" class="w-full px-4 py-3 border border-brand-sand rounded-xl text-sm focus:border-brand-gold outline-none">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Flag Emoji</label>
                    <input type="text" name="flag_emoji" id="editFlagEmoji" maxlength="10" class="w-full px-4 py-3 border border-brand-sand rounded-xl text-sm focus:border-brand-gold outline-none">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Payment Method</label>
                    <select name="payment_method" id="editPaymentMethod" class="w-full px-4 py-3 border border-brand-sand rounded-xl text-sm focus:border-brand-gold outline-none" onchange="toggleMomoFields('edit', this.value)">
                        <?php foreach ($payment_methods as $val => $label): ?>
                            <option value="<?= e($val) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Trial Days</label>
                    <input type="number" name="trial_days" id="editTrialDays" min="1" max="90" class="w-full px-4 py-3 border border-brand-sand rounded-xl text-sm focus:border-brand-gold outline-none">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Monthly Price</label>
                    <input type="number" step="0.01" name="monthly_price" id="editMonthlyPrice" class="w-full px-4 py-3 border border-brand-sand rounded-xl text-sm focus:border-brand-gold outline-none">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Extra Seat Price</label>
                    <input type="number" step="0.01" name="extra_seat_price" id="editExtraSeatPrice" class="w-full px-4 py-3 border border-brand-sand rounded-xl text-sm focus:border-brand-gold outline-none">
                </div>
            </div>

            <div id="editMomoFields">
                <div class="mb-3">
                    <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Mobile Money Number</label>
                    <input type="text" name="momo_number" id="editMomoNumber" class="w-full px-4 py-3 border border-brand-sand rounded-xl text-sm focus:border-brand-gold outline-none font-mono" placeholder="+237 6XX XXX XXX">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Payment Instructions</label>
                    <textarea name="payment_instructions" id="editPaymentInstructions" rows="3" class="w-full px-4 py-3 border border-brand-sand rounded-xl text-sm focus:border-brand-gold outline-none resize-none"></textarea>
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="flex-1 btn-primary text-white py-3 rounded-xl text-xs font-bold uppercase tracking-widest">Save Changes</button>
                <button type="button" onclick="document.getElementById('editCountryModal').classList.add('hidden')" class="flex-1 bg-brand-cream text-brand-plum py-3 rounded-xl text-xs font-bold uppercase tracking-widest border border-brand-sand">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(country) {
    document.getElementById('editCountryId').value    = country.id;
    document.getElementById('editModalTitle').textContent = 'Edit — ' + country.name;
    document.getElementById('editPaymentMethod').value = country.payment_method;
    document.getElementById('editMonthlyPrice').value  = country.monthly_price;
    document.getElementById('editExtraSeatPrice').value = country.extra_seat_price;
    document.getElementById('editMomoNumber').value    = country.momo_number || '';
    document.getElementById('editPaymentInstructions').value = country.payment_instructions || '';
    document.getElementById('editTrialDays').value     = country.trial_days;
    document.getElementById('editCurrencySymbol').value = country.currency_symbol;
    document.getElementById('editFlagEmoji').value     = country.flag_emoji || '';
    toggleMomoFields('edit', country.payment_method);
    document.getElementById('editCountryModal').classList.remove('hidden');
}

function toggleMomoFields(prefix, method) {
    const show = ['mobile_money', 'manual'].includes(method);
    const el   = document.getElementById(prefix + 'MomoFields');
    if (el) el.style.display = show ? '' : 'none';
}

function toggleCountry(id, btn) {
    const formData = new FormData();
    formData.append('country_id', id);
    formData.append('csrf_token', '<?= e(csrf_token()) ?>');

    fetch('<?= APP_URL ?>/countries/toggle', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const active = data.is_active;
                btn.dataset.active = active;
                btn.className = btn.className.replace(active ? 'bg-gray-300' : 'bg-green-500', active ? 'bg-green-500' : 'bg-gray-300');
                const span = btn.querySelector('span');
                span.className = span.className.replace(active ? 'translate-x-0.5' : 'translate-x-4', active ? 'translate-x-4' : 'translate-x-0.5');
            } else {
                alert(data.error || 'Failed to update.');
            }
        })
        .catch(() => alert('Network error.'));
}

// Initialise MoMo field visibility on page load
document.querySelectorAll('[id$="MomoFields"]').forEach(el => {
    const prefix = el.id.replace('MomoFields', '');
    const sel = document.getElementById(prefix + 'PaymentMethod');
    if (sel) toggleMomoFields(prefix, sel.value);
});
</script>
