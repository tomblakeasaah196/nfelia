<?php
/**
 * Nfelia — Settings View (Tabbed)
 * Renders inside views/layouts/app.php
 *
 * Available from controller:
 *   $user, $business, $tab, $subscription, $staff, $payment_history,
 *   $font_options, $currency_options, $country_pricing
 */
$currency   = $business['currency_symbol'] ?? '₦';
$is_owner   = ($user['role'] ?? 'staff') === 'owner';
$logo_url   = !empty($business['logo_path']) ? APP_URL . '/' . $business['logo_path'] : null;
$avatar_url = !empty($user['avatar_path']) ? APP_URL . '/' . $user['avatar_path'] : null;

// Subscription helpers
$sub_status = $subscription['status'] ?? 'trial';
$trial_days_left = 0;
if ($sub_status === 'trial' && !empty($subscription['trial_ends_at'])) {
    $trial_days_left = max(0, (int)round((strtotime(substr($subscription['trial_ends_at'], 0, 10)) - strtotime(date('Y-m-d'))) / 86400));
}
// What the plan card says, from billing_access() (app/helpers/billing.php)
$access     = $access ?? 'paid';
$period_end = !empty($subscription['current_period_end']) ? substr($subscription['current_period_end'], 0, 10) : null;
$pay_by     = $sub_status === 'past_due' && !empty($subscription['grace_ends_at'])
    ? substr($subscription['grace_ends_at'], 0, 10)
    : ($period_end ? billing_add_days($period_end, BILLING_GRACE_DAYS) : null);
[$plan_badge, $plan_tone] = match (true) {
    $access === 'trial'                                => ['Free trial', 'blue'],
    $access === 'paid' && $sub_status === 'cancelled'  => ['Cancelled', 'amber'],
    $access === 'paid'                                 => ['Paid', 'green'],
    $access === 'grace'                                => ['Grace days', 'amber'],
    $sub_status === 'suspended'                        => ['Suspended', 'red'],
    default                                            => ['Locked', 'red'],
};
$max_seats = ($subscription['user_seats'] ?? 1) + ($subscription['extra_seats'] ?? 0);
$current_staff_count = count($staff);
?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

<!-- Page Header -->
<div class="mb-8">
    <h1 class="font-heading font-extrabold text-2xl sm:text-3xl text-brand-plum tracking-tight">Settings</h1>
    <p class="text-xs text-brand-plum/50 font-medium mt-1">Manage your business, profile, team, and billing</p>
</div>

<!-- Tab Navigation -->
<div class="flex flex-wrap gap-1 mb-6 bg-brand-cream rounded-xl p-1 border border-brand-sand">
    <a href="<?= APP_URL ?>/settings?tab=business"
       class="flex-1 text-center px-4 py-2.5 rounded-lg text-[10px] font-bold uppercase tracking-widest transition-colors <?= $tab === 'business' ? 'bg-brand-white text-brand-plum shadow-sm' : 'text-brand-plum/50 hover:text-brand-plum' ?>">Business</a>
    <a href="<?= APP_URL ?>/settings?tab=profile"
       class="flex-1 text-center px-4 py-2.5 rounded-lg text-[10px] font-bold uppercase tracking-widest transition-colors <?= $tab === 'profile' ? 'bg-brand-white text-brand-plum shadow-sm' : 'text-brand-plum/50 hover:text-brand-plum' ?>">Profile</a>
    <?php if ($is_owner): ?>
    <a href="<?= APP_URL ?>/settings?tab=staff"
       class="flex-1 text-center px-4 py-2.5 rounded-lg text-[10px] font-bold uppercase tracking-widest transition-colors <?= $tab === 'staff' ? 'bg-brand-white text-brand-plum shadow-sm' : 'text-brand-plum/50 hover:text-brand-plum' ?>">Team</a>
    <a href="<?= APP_URL ?>/settings?tab=billing"
       class="flex-1 text-center px-4 py-2.5 rounded-lg text-[10px] font-bold uppercase tracking-widest transition-colors <?= $tab === 'billing' ? 'bg-brand-white text-brand-plum shadow-sm' : 'text-brand-plum/50 hover:text-brand-plum' ?>">Billing</a>
    <?php endif; ?>
</div>

<!-- ═══════════════════════════════════════════
     TAB: BUSINESS PROFILE
     ═══════════════════════════════════════════ -->
<?php if ($tab === 'business'): ?>
<form method="POST" action="<?= APP_URL ?>/settings/update-business" enctype="multipart/form-data" class="space-y-6">
    <?= csrf_field() ?>

    <!-- Logo -->
    <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm">
        <h2 class="font-heading font-bold text-sm text-brand-plum mb-4 uppercase tracking-wider">Business Logo</h2>
        <!-- Enhanced Logo Upload with Live Preview & Animated State -->
            <div x-data="{
                uploadState: 'idle', // 'idle', 'loading', 'success'
                fileUrl: '<?= e($logo_url) ?>',
                handleUpload(e) {
                    const file = e.target.files[0];
                    if (!file) return;
                    this.uploadState = 'loading';
                    
                    // Simulate processing time for UX feedback
                    setTimeout(() => {
                        this.fileUrl = URL.createObjectURL(file);
                        this.uploadState = 'success';
                    }, 600);
                }
            }">
                <label class="inline-flex items-center gap-2 px-4 py-2 bg-brand-cream border border-brand-sand rounded-lg cursor-pointer hover:border-brand-gold transition-colors">
                    
                    <!-- 1. Idle Icon -->
                    <svg x-show="uploadState === 'idle'" class="w-3.5 h-3.5 text-brand-plum/60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>

                    <!-- 2. Loading Spinner -->
                    <svg x-show="uploadState === 'loading'" class="animate-spin w-3.5 h-3.5 text-brand-plum" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" x-cloak><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>

                    <!-- 3. Success Tick -->
                    <svg x-show="uploadState === 'success'" class="w-4 h-4 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" x-cloak><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    
                    <span class="text-[10px] font-bold uppercase tracking-widest transition-colors" 
                          x-text="uploadState === 'loading' ? 'Processing...' : (uploadState === 'success' ? 'Ready to Save' : 'Upload Logo')" 
                          :class="uploadState === 'success' ? 'text-green-600' : 'text-brand-plum/60'"></span>
                    
                    <input type="file" name="logo" accept="image/jpeg,image/png,image/gif,image/webp" class="hidden" @change="handleUpload($event)">
                </label>

                <!-- Live Preview updates instantly -->
                <div class="mt-3 w-20 h-20 rounded-2xl overflow-hidden bg-brand-cream border-2 border-brand-sand flex items-center justify-center relative">
                    <!-- Subtle image overlay during loading -->
                    <div x-show="uploadState === 'loading'" class="absolute inset-0 bg-white/60 z-10 transition-opacity" x-cloak></div>
                    
                    <template x-if="fileUrl">
                        <img :src="fileUrl" class="w-full h-full object-contain">
                    </template>
                    <template x-if="!fileUrl">
                        <span class="text-2xl font-extrabold text-brand-plum/20">?</span>
                    </template>
                </div>
            </div>
        <?php if (!$logo_url && $is_owner): ?>
        <div class="mt-4 pt-4 border-t border-brand-sand/50">
            <p class="text-[10px] text-brand-plum/50 mb-2">No logo? We can generate one from your business initials:</p>
            <a href="javascript:void(0)" onclick="document.getElementById('auto-logo-form').submit()"
               class="text-[10px] font-bold text-brand-gold uppercase tracking-widest hover:text-brand-burgundy">Generate Auto-Logo</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Business Details -->
    <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm">
        <h2 class="font-heading font-bold text-sm text-brand-plum mb-4 uppercase tracking-wider">Business Details</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Business Name *</label>
                <input type="text" name="business_name" value="<?= e($business['name'] ?? '') ?>" required
                       class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Tagline</label>
                <input type="text" name="tagline" value="<?= e($business['tagline'] ?? '') ?>"
                       class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum"
                       placeholder="e.g. Quality Auto Parts at the Best Prices">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Phone</label>
                <input type="tel" name="phone" maxlength="20" value="<?= e($business['phone'] ?? '') ?>"
                       class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum"
                       placeholder="e.g. 08012345678">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Email</label>
                <input type="email" name="email" value="<?= e($business['email'] ?? '') ?>"
                       class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Address</label>
                <textarea name="address" rows="2" class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum resize-none"><?= e($business['address'] ?? '') ?></textarea>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Currency</label>
                <select name="currency_symbol" class="w-full px-3 py-2.5 text-xs font-semibold bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
                    <?php foreach ($currency_options as $sym => $label): ?>
                        <option value="<?= e($sym) ?>" <?= ($business['currency_symbol'] ?? '₦') === $sym ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Branding -->
<div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm"
     x-data="{
         primary: '<?= e($business['primary_color'] ?? '#1A1A2E') ?>',
         secondary: '<?= e($business['secondary_color'] ?? '#C5A55A') ?>',
         font: '<?= e($business['font_pairing'] ?? 'modern_sans') ?>',
         
         get fontFamily() {
             const map = {
                 'modern_sans': 'Inter, sans-serif',
                 'corporate_serif': 'EB Garamond, serif',
                 'bold_display': 'Syne, sans-serif',
                 'classic_tech': 'Roboto Mono, monospace',
                 'elegant_serif': 'Playfair Display, serif'
             };
             return map[this.font] || map['modern_sans'];
         }
     }">
    
    <h2 class="font-heading font-bold text-sm text-brand-plum mb-4 uppercase tracking-wider">Branding & Aesthetics</h2>

    <!-- UX Enhancement: Live Preview Card -->
    <div class="mb-6 p-6 rounded-xl border border-brand-sand bg-brand-cream/30 flex flex-col items-center justify-center relative overflow-hidden transition-all duration-300" :style="{ fontFamily: fontFamily }">
        <div class="absolute top-0 left-0 w-full h-1" :style="{ backgroundColor: primary }"></div>
        
        <!-- Miniature Document Mockup -->
        <div class="w-full max-w-[280px] bg-white rounded-lg shadow-sm border border-brand-sand overflow-hidden">
            <div class="px-4 py-3 border-b border-brand-sand flex justify-between items-center bg-gray-50/50">
                <span class="text-xs font-bold" :style="{ color: primary }">Invoice #1042</span>
                <span class="text-[9px] px-2 py-0.5 rounded uppercase tracking-wider font-bold" 
                      :style="{ backgroundColor: secondary + '20', color: secondary }">Paid</span>
            </div>
            <div class="p-5">
                <div class="text-[10px] text-gray-400 mb-1 font-medium uppercase tracking-widest">Total Due</div>
                <div class="text-2xl font-extrabold tracking-tight" :style="{ color: primary }"><?= e($currency) ?>45,000</div>
                <button type="button" class="mt-4 w-full py-2.5 rounded text-[10px] font-bold text-white uppercase tracking-widest transition-opacity hover:opacity-90 shadow-sm" 
                        :style="{ backgroundColor: primary }">Download PDF</button>
            </div>
        </div>
        <div class="mt-3 text-[9px] font-bold uppercase tracking-widest text-brand-plum/40 font-mono">Live Document Preview</div>
    </div>

    <!-- Controls -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <!-- Primary Color -->
        <div>
            <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1.5">Primary Color</label>
            <div class="flex items-center gap-2">
                <input type="color" name="primary_color" 
                       x-model="primary" 
                       class="w-10 h-10 rounded-lg cursor-pointer border border-brand-sand p-0 bg-transparent shrink-0">
                <input type="text" 
                       x-model="primary" 
                       @input="primary = primary.toUpperCase()"
                       class="flex-1 px-3 py-2.5 text-xs font-mono font-bold uppercase bg-brand-cream border border-brand-sand rounded-lg text-brand-plum focus:outline-none focus:border-brand-gold transition-colors" 
                       maxlength="7">
            </div>
        </div>

        <!-- Secondary Color -->
        <div>
            <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1.5">Secondary Color</label>
            <div class="flex items-center gap-2">
                <input type="color" name="secondary_color" 
                       x-model="secondary" 
                       class="w-10 h-10 rounded-lg cursor-pointer border border-brand-sand p-0 bg-transparent shrink-0">
                <input type="text" 
                       x-model="secondary" 
                       @input="secondary = secondary.toUpperCase()"
                       class="flex-1 px-3 py-2.5 text-xs font-mono font-bold uppercase bg-brand-cream border border-brand-sand rounded-lg text-brand-plum focus:outline-none focus:border-brand-gold transition-colors" 
                       maxlength="7">
            </div>
        </div>

        <!-- Font Style -->
        <div>
            <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1.5">Typography</label>
            <select name="font_pairing" 
                    x-model="font" 
                    class="w-full px-3 py-2.5 text-xs font-semibold bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum transition-colors cursor-pointer"
                    :style="{ fontFamily: fontFamily }">
                <?php foreach ($font_options as $key => $label): ?>
                    <?php 
                        // Backend rendering for option styles
                        $preview_font = match($key) {
                            'corporate_serif' => '"EB Garamond", serif',
                            'bold_display'    => '"Syne", sans-serif',
                            'classic_tech'    => '"Roboto Mono", monospace',
                            'elegant_serif'   => '"Playfair Display", serif',
                            default           => '"Inter", sans-serif'
                        };
                    ?>
                    <option value="<?= $key ?>" style="font-family: <?= $preview_font ?>">
                        <?= $label ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    
    <div class="mt-5 pt-4 border-t border-brand-sand/50 flex items-center justify-between">
        <p class="text-[9px] text-brand-plum/40 font-medium">Applied automatically to client portals, PDFs, and checkout links.</p>
    </div>
</div>

    <div class="flex justify-end">
        <button type="submit" class="btn-primary text-white px-8 py-3 rounded-xl text-[10px] font-bold uppercase tracking-widest shadow-sm hover:shadow-md transition-all"><?= $is_owner ? 'Save Changes' : 'View Only (Owner Required)' ?></button>
    </div>
</form>

<?php require BASE_PATH . '/views/settings/payment_accounts.php'; ?>

<?php
// Document numbers: the prefix every order and paper number starts with,
// the next number of each kind, and a suggestion after a rename.
$prefix_now = strtoupper((string)($business['doc_prefix'] ?? 'NFE'));
$prefix_suggest = derive_prefix((string)($business['name'] ?? ''));
// Only after a rename: prefix_nudge_seen holds what the name suggested when
// the prefix was last settled. With no record yet (businesses from before
// this card), only a name that starts differently counts as a rename.
$prefix_seen  = strtoupper((string)($business['prefix_nudge_seen'] ?? ''));
$prefix_nudge = $is_owner && $prefix_suggest !== $prefix_now
    && ($prefix_seen !== '' ? $prefix_suggest !== $prefix_seen : $prefix_suggest[0] !== $prefix_now[0]);
$next_numbers = [];
$stmt_counters = get_db()->prepare("SELECT doc_type, last_number FROM document_counters WHERE business_id = ?");
$stmt_counters->execute([(int)$business['id']]);
$counters = $stmt_counters->fetchAll(PDO::FETCH_KEY_PAIR);
foreach (['ORD' => 'Order', 'INV' => 'Invoice', 'REC' => 'Receipt', 'DEL' => 'Delivery note'] as $doc_type => $doc_word) {
    $next_numbers[$doc_type] = [$doc_word, str_pad((string)((int)($counters[$doc_type] ?? 0) + 1), 3, '0', STR_PAD_LEFT)];
}
?>
<section class="nf-card nf-prefix" id="numbers" aria-labelledby="nf-numbers-h" x-data="{ p: '<?= e($prefix_now) ?>' }">
    <div class="nf-card-h">
        <div>
            <h2 id="nf-numbers-h">Document numbers <?= nf_info('Document numbers', 'Every order, invoice, receipt and delivery note number starts with these letters, so customers know it is yours. Changing them only changes new numbers: papers already sent keep theirs, and each kind keeps counting on from where it was.') ?></h2>
            <p>The letters your order and paper numbers start with.</p>
        </div>
    </div>

    <?php if ($prefix_nudge): ?>
        <div class="nf-prefix-nudge">
            <p>Your business is now <b><?= e($business['name']) ?></b>, but your numbers still start with <b><?= e($prefix_now) ?></b>. Start new ones with <b><?= e($prefix_suggest) ?></b>?</p>
            <div class="nf-prefix-nudge-do">
                <form method="POST" action="<?= APP_URL ?>/settings/doc-prefix"><?= csrf_field() ?><input type="hidden" name="doc_prefix" value="<?= e($prefix_suggest) ?>">
                    <button class="nf-btn nf-btn-primary">Use <?= e($prefix_suggest) ?></button></form>
                <form method="POST" action="<?= APP_URL ?>/settings/doc-prefix-keep"><?= csrf_field() ?>
                    <button class="nf-btn nf-btn-ghost">Keep <?= e($prefix_now) ?></button></form>
            </div>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= APP_URL ?>/settings/doc-prefix" class="nf-prefix-form">
        <?= csrf_field() ?>
        <label for="nf-prefix-in">Prefix <small>2 to 5 letters or digits</small></label>
        <div class="nf-prefix-row">
            <input id="nf-prefix-in" type="text" name="doc_prefix" x-model="p" @input="p = p.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 5)"
                   maxlength="5" autocapitalize="characters" autocomplete="off" spellcheck="false" required<?= $is_owner ? '' : ' disabled' ?>>
            <?php if ($is_owner): ?><button class="nf-btn nf-btn-primary" :disabled="p === '<?= e($prefix_now) ?>' || p.length < 2">Save</button><?php endif; ?>
        </div>
        <ul class="nf-prefix-next" aria-label="Your next numbers">
            <?php foreach ($next_numbers as $doc_type => [$doc_word, $doc_next]): ?>
                <li><small><?= e($doc_word) ?></small><b><span x-text="p || '<?= e($prefix_now) ?>'"><?= e($prefix_now) ?></span>-<?= $doc_type ?>-<?= $doc_next ?></b></li>
            <?php endforeach; ?>
        </ul>
        <?php if (!$is_owner): ?><p class="nf-help">Only the business owner can change them.</p><?php endif; ?>
    </form>
</section>

<?php if (is_boss()): // owners and admins sign for the business ?>
<?php require BASE_PATH . '/views/settings/signatures.php'; ?>
<?php endif; ?>

<!-- Hidden auto-logo form -->
<?php if (!$logo_url && $is_owner): ?>
<form id="auto-logo-form" method="POST" action="<?= APP_URL ?>/settings/generate-logo" class="hidden">
    <?= csrf_field() ?>
</form>
<?php endif; ?>

<!-- ═══════════════════════════════════════════
     TAB: USER PROFILE
     ═══════════════════════════════════════════ -->
<?php elseif ($tab === 'profile'): ?>
<div class="space-y-6">
    <!-- Profile Info -->
    <form method="POST" action="<?= APP_URL ?>/settings/update-profile" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm space-y-5">
            <h2 class="font-heading font-bold text-sm text-brand-plum uppercase tracking-wider">Your Profile</h2>

            <!-- Avatar Upload with Cropper Modal & State Animations -->
            <div x-data="avatarCropper()">
                <label class="inline-flex items-center gap-2 px-3 py-2 bg-brand-cream border border-brand-sand rounded-lg cursor-pointer hover:border-brand-gold transition-colors">
                    
                    <!-- 1. Idle Icon -->
                    <svg x-show="uploadState === 'idle'" class="w-3.5 h-3.5 text-brand-plum/60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>

                    <!-- 2. Loading Spinner -->
                    <svg x-show="uploadState === 'loading'" class="animate-spin w-3.5 h-3.5 text-brand-plum" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" x-cloak><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>

                    <!-- 3. Success Tick -->
                    <svg x-show="uploadState === 'success'" class="w-4 h-4 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" x-cloak><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>

                    <span class="text-[10px] font-bold uppercase tracking-widest transition-colors" 
                          x-text="uploadState === 'loading' ? 'Cropping...' : (uploadState === 'success' ? 'Avatar Ready' : 'Change Avatar')"
                          :class="uploadState === 'success' ? 'text-green-600' : 'text-brand-plum/60'"></span>
                    
                    <input type="file" id="avatar_input" accept="image/jpeg,image/png,image/webp" class="hidden" @change="openCropper($event)">
                </label>

                <!-- Hidden Input to carry the Base64 crop payload to PHP -->
                <input type="hidden" name="avatar_base64" :value="croppedData">

                <!-- Remove Avatar Option -->
                <?php if ($avatar_url): ?>
                <label class="ml-3 inline-flex items-center gap-1 text-[10px] font-bold text-red-400 uppercase tracking-widest cursor-pointer hover:text-red-600">
                    <input type="checkbox" name="remove_avatar" value="1" class="w-3 h-3"> Remove
                </label>
                <?php endif; ?>
                <p class="text-[9px] text-brand-plum/30 mt-1">JPG, PNG or WebP. Max 512KB.</p>

                <!-- Live Preview -->
                <div class="mt-3 w-16 h-16 rounded-full overflow-hidden bg-brand-cream border-2 border-brand-sand relative flex items-center justify-center">
                    <div x-show="uploadState === 'loading'" class="absolute inset-0 bg-white/60 z-10 transition-opacity" x-cloak></div>
                    <template x-if="previewUrl || '<?= e($avatar_url ?? '') ?>'">
                        <img :src="previewUrl || '<?= e($avatar_url ?? '') ?>'" class="w-full h-full object-cover">
                    </template>
                    <?php if (!$avatar_url): ?>
                    <span x-show="!previewUrl" class="text-xl font-extrabold text-brand-plum/20"><?= mb_strtoupper(mb_substr($user['full_name'] ?? 'U', 0, 1)) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Cropper Modal (Tailwind) -->
                <div x-show="showModal" class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4" x-cloak>
                    <div class="bg-white rounded-xl p-4 w-full max-w-md">
                        <div class="w-full h-64 bg-gray-100">
                            <img id="cropper_image" class="max-w-full">
                        </div>
                        <div class="mt-4 flex justify-end gap-2">
                            <button type="button" @click="showModal = false" class="px-4 py-2 bg-gray-200 rounded text-xs font-bold hover:bg-gray-300">Cancel</button>
                            <button type="button" @click="cropImage()" class="px-4 py-2 bg-brand-plum text-white rounded text-xs font-bold hover:bg-brand-plum/90">Crop & Apply</button>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            function avatarCropper() {
                return {
                    showModal: false, 
                    cropperInstance: null, 
                    croppedData: '', 
                    previewUrl: '',
                    uploadState: 'idle', // idle, loading, success

                    openCropper(e) {
                        const file = e.target.files[0];
                        if (!file) return;
                        
                        // Reset state if they pick a new file
                        this.uploadState = 'idle'; 
                        
                        const url = URL.createObjectURL(file);
                        const image = document.getElementById('cropper_image');
                        image.src = url;
                        this.showModal = true;
                        
                        if (this.cropperInstance) this.cropperInstance.destroy();
                        setTimeout(() => {
                            this.cropperInstance = new Cropper(image, { aspectRatio: 1, viewMode: 1 });
                        }, 100);
                        
                        // Clear the input so selecting the same file again triggers change event
                        e.target.value = ''; 
                    },
                    cropImage() {
                        this.showModal = false;
                        this.uploadState = 'loading'; // Trigger UI Spinner
                        
                        // Wait slightly to let the UI update and give the browser breathing room to crop
                        setTimeout(() => {
                            const canvas = this.cropperInstance.getCroppedCanvas({ width: 400, height: 400 });
                            this.croppedData = canvas.toDataURL('image/jpeg', 0.9); // Base64
                            this.previewUrl = this.croppedData;
                            this.uploadState = 'success'; // Trigger UI Tick
                        }, 600);
                    }
                }
            }
            </script>

            <!-- Name + Email -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Full Name *</label>
                    <input type="text" name="full_name" value="<?= e($user['full_name'] ?? '') ?>" required
                           class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Email *</label>
                    <input type="email" name="email" value="<?= e($user['email'] ?? '') ?>" required
                           class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
                </div>
            </div>

            <div class="flex items-center justify-between pt-2 border-t border-brand-sand/50">
                <div class="text-[10px] text-brand-plum/40">Role: <strong class="text-brand-plum"><?= ucfirst($user['role'] ?? 'staff') ?></strong></div>
                <button type="submit" class="btn-primary text-white px-6 py-2.5 rounded-xl text-[10px] font-bold uppercase tracking-widest shadow-sm">Save Profile</button>
            </div>
        </div>
    </form>

    <!-- Appearance: this device only, applies at once -->
    <section id="appearance" class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm">
        <h2 class="font-heading font-bold text-sm text-brand-plum mb-4 uppercase tracking-wider">Appearance</h2>
        <?php require BASE_PATH . '/views/partials/appearance.php'; ?>
    </section>

    <?php require BASE_PATH . '/views/partials/passkeys_card.php'; ?>

    <!-- Change Password -->
    <form method="POST" action="<?= APP_URL ?>/settings/change-password">
        <?= csrf_field() ?>
        <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm">
            <h2 class="font-heading font-bold text-sm text-brand-plum mb-4 uppercase tracking-wider">Change Password</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Current Password</label>
                    <input type="password" name="current_password" required
                           class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
                </div>
                <div>
                    <label for="new_password" class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">New Password</label>
                    <input id="new_password" type="password" name="new_password" required minlength="9" maxlength="200" autocomplete="new-password"
                           class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
                </div>
                <div>
                    <label for="confirm_password" class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Confirm Password</label>
                    <input id="confirm_password" type="password" name="confirm_password" required minlength="9" maxlength="200" autocomplete="new-password"
                           class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
                </div>
            </div>
            <?php $pw_input = '#new_password'; $pw_confirm = '#confirm_password'; $pw_compact = true; require BASE_PATH . '/views/partials/password_rules.php'; ?>
            <div class="flex justify-end mt-4">
                <button type="submit" class="bg-brand-white border border-brand-sand text-brand-plum px-6 py-2.5 rounded-xl text-[10px] font-bold uppercase tracking-widest hover:border-brand-gold transition-colors">Update Password</button>
            </div>
        </div>
    </form>
</div>

<!-- ═══════════════════════════════════════════
     TAB: STAFF / TEAM
     ═══════════════════════════════════════════ -->
<?php elseif ($tab === 'staff' && $is_owner): ?>
<div class="space-y-6">

    <!-- Seat Counter -->
    <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-heading font-bold text-sm text-brand-plum uppercase tracking-wider">Team Seats</h2>
                <p class="text-[10px] text-brand-plum/40 mt-0.5"><?= $current_staff_count ?> of <?= $max_seats ?> seats used</p>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-24 h-2 bg-brand-cream rounded-full overflow-hidden">
                    <div class="h-full rounded-full transition-all <?= $current_staff_count >= $max_seats ? 'bg-red-400' : 'bg-brand-gold' ?>"
                         style="width: <?= min(100, ($current_staff_count / $max_seats) * 100) ?>%"></div>
                </div>
                <?php if ($current_staff_count >= $max_seats): ?>
                    <a href="<?= APP_URL ?>/settings?tab=billing" class="text-[9px] font-bold text-brand-gold uppercase tracking-widest">Add Seats</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Current Staff -->
    <div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-brand-sand">
            <h2 class="font-heading font-bold text-sm text-brand-plum uppercase tracking-wider">Team Members</h2>
        </div>
        <div class="divide-y divide-brand-sand/50">
            <?php foreach ($staff as $member): ?>
            <div class="px-5 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full overflow-hidden bg-brand-cream border border-brand-sand flex items-center justify-center shrink-0">
                        <?php if ($member['avatar_path']): ?>
                            <img src="<?= APP_URL ?>/<?= e($member['avatar_path']) ?>" alt="" class="w-full h-full object-cover">
                        <?php else: ?>
                            <span class="text-sm font-extrabold text-brand-plum/20"><?= mb_strtoupper(mb_substr($member['full_name'], 0, 1)) ?></span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="text-xs font-semibold text-brand-plum"><?= e($member['full_name']) ?></div>
                        <div class="text-[10px] text-brand-plum/40"><?= e($member['email']) ?></div>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <?php if ($member['role'] === 'owner'): ?>
                        <span class="text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-purple-100 text-purple-700">Owner</span>
                    <?php elseif ($is_owner): ?>
                        <select onchange="updateRole(<?= (int)$member['id'] ?>, this.value, this)"
                                class="text-[9px] font-bold uppercase tracking-wider px-2 py-1 rounded border border-brand-sand bg-brand-cream text-brand-plum focus:outline-none focus:border-brand-gold cursor-pointer">
                            <option value="admin" <?= $member['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="staff" <?= $member['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
                        </select>
                    <?php else: ?>
                        <span class="text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-gray-100 text-gray-600"><?= ucfirst($member['role']) ?></span>
                    <?php endif; ?>
                    <?php if ($is_owner && (int)$member['id'] !== (int)$user['id'] && $member['role'] !== 'owner'): ?>
                        <form method="POST" action="<?= APP_URL ?>/settings/send-staff-reset/<?= (int)$member['id'] ?>" class="inline"
                              onsubmit="return confirm(<?= e(json_encode('Email ' . $member['full_name'] . ' a link to set a new password?', JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP)) ?>)">
                            <?= csrf_field() ?>
                            <button type="submit" class="text-[10px] font-bold text-brand-plum/50 uppercase tracking-widest hover:text-brand-burgundy">Send reset link</button>
                        </form>
                    <?php endif; ?>
                    <?php if ((int)$member['id'] !== (int)$user['id'] && $member['role'] !== 'owner'): ?>
                        <form method="POST" action="<?= APP_URL ?>/settings/remove-staff/<?= $member['id'] ?>" class="inline"
                              onsubmit="return confirm(<?= e(json_encode('Remove ' . $member['full_name'] . ' from your team?', JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP)) ?>)">
                            <?= csrf_field() ?>
                            <button type="submit" class="text-[10px] font-bold text-red-400 uppercase tracking-widest hover:text-red-600">Remove</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Add Staff -->
    <?php if ($current_staff_count < $max_seats): ?>
    <form method="POST" action="<?= APP_URL ?>/settings/add-staff">
        <?= csrf_field() ?>
        <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm">
            <h2 class="font-heading font-bold text-sm text-brand-plum mb-4 uppercase tracking-wider">Add Team Member</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Full Name *</label>
                    <input type="text" name="staff_name" required
                           class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Email *</label>
                    <input type="email" name="staff_email" required
                           class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
                </div>
                <div>
                    <div>
                    <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Role</label>
                    <select name="staff_role" class="w-full px-3 py-2.5 text-xs font-semibold bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum uppercase tracking-wider">
                        <option value="staff">Staff — Operational access only</option>
                        <option value="admin">Admin — Full access except billing</option>
                    </select>
                </div>
                    <label for="staff_password" class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Password *</label>
                    <input id="staff_password" type="password" name="staff_password" required minlength="9" maxlength="200" autocomplete="new-password"
                           class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum"
                           placeholder="9+ characters, a capital, a number, a symbol">
                    <?php $pw_input = '#staff_password'; $pw_confirm = ''; $pw_compact = true; require BASE_PATH . '/views/partials/password_rules.php'; ?>
                </div>
            </div>
            <p class="text-[9px] text-brand-plum/30 mt-2">Staff members can access operational modules but cannot manage billing, reports, or settings.</p>
            <div class="flex justify-end mt-4">
                <button type="submit" class="btn-primary text-white px-6 py-2.5 rounded-xl text-[10px] font-bold uppercase tracking-widest shadow-sm">Add Member</button>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>

<script>
async function updateRole(memberId, newRole, selectEl) {
    const fd = new FormData();
    fd.append('role', newRole);
    fd.append('csrf_token', window.Nfelia?.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '');
    try {
        const res = await fetch(`${window.Nfelia?.url || ''}/settings/update-role/${memberId}`, { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.success) {
            alert(data.error || 'Failed to update role.');
            selectEl.value = selectEl.dataset.prev || 'staff';
        } else {
            selectEl.dataset.prev = newRole;
        }
    } catch (e) {
        alert('Network error. Please try again.');
    }
}
</script>

<!-- ═══════════════════════════════════════════
     TAB: BILLING & SUBSCRIPTION
     ═══════════════════════════════════════════ -->
<?php elseif ($tab === 'billing' && $is_owner): ?>
<div class="space-y-6">

    <!-- Current Plan -->
    <div class="bg-brand-white border-2 rounded-xl p-6 shadow-sm
        <?php if ($plan_tone === 'green'): ?>border-green-200 bg-green-50/30
        <?php elseif ($plan_tone === 'blue'): ?>border-blue-200 bg-blue-50/30
        <?php elseif ($plan_tone === 'amber'): ?>border-amber-200 bg-amber-50/30
        <?php else: ?>border-red-200 bg-red-50/30<?php endif; ?>">

        <div class="flex items-start justify-between mb-4">
            <div>
                <h2 class="font-heading font-bold text-sm text-brand-plum uppercase tracking-wider">Current Plan</h2>
                <div class="text-2xl font-extrabold text-brand-plum mt-1"><?= e($prices['symbol']) ?><?= number_format($prices['monthly_now'], 0) ?><span class="text-sm font-medium text-brand-plum/40">/month</span></div>
                <?php if (($subscription['extra_seats'] ?? 0) > 0): ?>
                    <div class="text-xs text-brand-plum/50 mt-0.5">+ <?= e($prices['symbol']) ?><?= number_format($subscription['extra_seats'] * $prices['seat_now'], 0) ?> for <?= $subscription['extra_seats'] ?> extra seat<?= $subscription['extra_seats'] > 1 ? 's' : '' ?></div>
                <?php endif; ?>
            </div>
            <span class="text-[9px] font-bold uppercase tracking-wider px-3 py-1 rounded-full
                <?php if ($plan_tone === 'green'): ?>bg-green-100 text-green-700
                <?php elseif ($plan_tone === 'blue'): ?>bg-blue-100 text-blue-700
                <?php elseif ($plan_tone === 'amber'): ?>bg-amber-100 text-amber-700
                <?php else: ?>bg-red-100 text-red-700<?php endif; ?>">
                <?= e($plan_badge) ?>
            </span>
        </div>

        <?php if ($access === 'trial'): ?>
            <div class="text-xs text-blue-700 bg-blue-100 rounded-lg px-4 py-3">
                <?php if ($trial_days_left === 0): ?>
                    <strong>Your free trial ends today.</strong>
                <?php else: ?>
                    <strong><?= $trial_days_left ?> day<?= $trial_days_left !== 1 ? 's' : '' ?> left</strong> on your free trial.
                <?php endif; ?>
                Full access to everything. Pay for a month whenever you are ready.
            </div>
        <?php elseif ($access === 'paid' && $sub_status === 'cancelled'): ?>
            <div class="text-xs text-amber-700 bg-amber-100 rounded-lg px-4 py-3">
                <strong>Cancelled.</strong> Nfelia and your website stay open until <strong><?= fmt_date($period_end, 'j M Y') ?></strong>.
            </div>
        <?php elseif ($access === 'paid'): ?>
            <div class="text-xs text-green-700">
                <?php if ($period_end && empty($subscription['expiry_exempt'])): ?>
                    Paid until <strong><?= fmt_date($period_end, 'j M Y') ?></strong> &middot;
                <?php endif; ?>
                <?= $max_seats ?> seat<?= $max_seats > 1 ? 's' : '' ?>
            </div>
        <?php elseif ($access === 'grace'): ?>
            <div class="text-xs text-amber-700 bg-amber-100 rounded-lg px-4 py-3">
                <strong>Your paid month has ended.</strong> Pay by <strong><?= fmt_date($pay_by, 'j M Y') ?></strong> to keep Nfelia and your website open.
            </div>
        <?php else: ?>
            <div class="text-xs text-red-700 bg-red-100 rounded-lg px-4 py-3">
                <strong><?= $sub_status === 'trial' ? 'Your free trial has ended.' : ($sub_status === 'suspended' ? 'Your account is suspended.' : 'Your paid month has ended.') ?></strong>
                Pay for a month below to open Nfelia and your website again.
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($prices['coming'])): ?>
    <!-- A price change still to come for them (Ops → Prices), so nobody is surprised -->
    <div class="bg-brand-cream/60 border border-brand-sand rounded-xl p-5 text-xs text-brand-plum/80 leading-relaxed" role="note">
        <strong class="text-brand-plum">Our prices are changing.</strong>
        <?php foreach ($prices['coming'] as $item => $c): ?>
            <?= e(ucfirst(PRICE_ITEMS[$item]['short'])) ?>: <?= e($prices['symbol']) ?><?= number_format($c['amount'], 0) ?> from <?= fmt_date($c['from'], 'j M Y') ?> (now <?= e($prices['symbol']) ?><?= number_format($c['now'], 0) ?>).
        <?php endforeach; ?>
        A month that starts before then stays at today's price.
    </div>
    <?php endif; ?>

    <!-- Paying: a month (with the staff it covers), or more seats now -->
    <?php
    $sym        = $prices['symbol'] ?? $country_pricing['currency_symbol'];
    $month_cost = (float)($prices['monthly'] ?? $country_pricing['monthly_price']);
    $seat_cost  = (float)($prices['seat'] ?? $country_pricing['extra_seat_price']);
    $team       = $team ?? [];
    $room       = max(0, MAX_SEATS - 1 - (int)($subscription['extra_seats'] ?? 0));
    $running    = $period_end && $period_end >= date('Y-m-d') && in_array($sub_status, ['active', 'cancelled'], true);
    ?>
    <?php if (!empty($waiting_payment)): ?>
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-5 shadow-sm text-xs text-blue-800 leading-relaxed" role="status">
        <strong>We are checking your Mobile Money payment</strong> of <?= e($sym) ?><?= number_format((float)$waiting_payment['amount'], 0) ?>,
        sent on <?= fmt_date($waiting_payment['submitted_at'], 'j M Y, H:i') ?>.
        Nfelia opens the month as soon as it is approved, and we will tell you here and by email.
    </div>
    <?php elseif (($prices['method'] ?? 'paystack') === 'manual'): ?>
    <div class="bg-brand-white border border-brand-sand rounded-xl p-6 shadow-sm">
        <h2 class="font-heading font-bold text-sm text-brand-plum mb-3 uppercase tracking-wider">How to pay</h2>
        <div class="text-xs text-brand-plum/80 leading-relaxed">
            <?php if (!empty($prices['country']['payment_instructions'])): ?>
                <?= nl2br(e($prices['country']['payment_instructions'])) ?>
            <?php else: ?>
                Payment for <?= e($prices['country']['name'] ?? 'your country') ?> is arranged with our team. Message us and we will set up your month.
            <?php endif; ?>
        </div>
        <div class="mt-4 flex gap-4">
            <a href="mailto:support@nfelia.com" class="text-brand-burgundy font-bold hover:underline tracking-widest uppercase text-[10px]">Email support</a>
            <a href="https://wa.me/2348169789106" target="_blank" rel="noopener" class="text-brand-burgundy font-bold hover:underline tracking-widest uppercase text-[10px]">WhatsApp us</a>
        </div>
    </div>
    <?php else: ?>
    <div class="bg-brand-white border border-brand-sand rounded-xl p-6 shadow-sm" x-data="payMonth()">
        <h2 class="font-heading font-bold text-sm text-brand-plum mb-1 uppercase tracking-wider">Pay for a month</h2>
        <p class="text-xs text-brand-plum/60 mb-4">
            <?= $running ? 'Your next month starts when this one ends' : 'Your month starts today' ?>:
            <?= fmt_date($next_start, 'j M') ?> to <?= fmt_date($next_end, 'j M Y') ?>.
        </p>

        <?php if ($team): ?>
        <fieldset class="mb-4">
            <div class="flex items-center justify-between mb-2">
                <legend class="text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest">Who does this month cover?</legend>
                <button type="button" @click="covered = <?= e(json_encode(array_map('intval', array_column($team, 'id')))) ?>" class="text-[10px] font-bold text-brand-burgundy uppercase tracking-widest hover:underline">All</button>
            </div>
            <p class="text-[10px] text-brand-plum/50 mb-2">You are always covered. Anyone you leave unticked is switched off when the payment goes through.</p>
            <div class="space-y-1.5">
                <?php foreach ($team as $member): ?>
                <label class="flex items-center gap-3 px-3 py-2 rounded-lg border border-brand-sand bg-brand-cream/40 cursor-pointer">
                    <input type="checkbox" value="<?= (int)$member['id'] ?>" x-model.number="covered" class="w-4 h-4">
                    <span class="text-xs font-semibold text-brand-plum grow"><?= e($member['full_name']) ?></span>
                    <span class="text-[10px] text-brand-plum/50"><?= e(ucfirst($member['role'])) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <?php endif; ?>

        <div class="mb-4">
            <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-2">Staff seats (<?= e($sym) ?><?= number_format($seat_cost, 0) ?> each)</label>
            <div class="flex items-center gap-3">
                <button type="button" @click="spare = Math.max(0, spare - 1)" aria-label="One seat fewer" class="w-8 h-8 rounded-lg bg-brand-cream border border-brand-sand flex items-center justify-center hover:border-brand-gold">−</button>
                <span class="data-text text-lg font-extrabold text-brand-plum w-8 text-center" x-text="totalSeats()"></span>
                <button type="button" @click="spare = Math.max(0, Math.min(<?= MAX_SEATS - 1 ?> - covered.length, spare + 1))" aria-label="One seat more" class="w-8 h-8 rounded-lg bg-brand-cream border border-brand-sand flex items-center justify-center hover:border-brand-gold">+</button>
                <span class="text-xs text-brand-plum/50" x-text="totalSeats() === 0 ? 'Just you' : (1 + totalSeats()) + ' people in all, with you'"></span>
            </div>
            <p class="text-[10px] text-brand-plum/40 mt-2">One for each person ticked; add spare seats for new staff. Up to <?= MAX_SEATS ?> people (you and <?= MAX_SEATS - 1 ?> staff). Need more? Email support@nfelia.com.</p>
        </div>

        <div class="flex items-center justify-between mb-4 p-3 bg-brand-cream rounded-lg">
            <span class="text-xs font-semibold text-brand-plum">Total for the month</span>
            <span class="font-heading font-extrabold text-lg text-brand-plum" x-text="money(<?= $month_cost ?> + totalSeats() * <?= $seat_cost ?>)"></span>
        </div>

        <button type="button" @click="pay({ kind: 'month', extra_seats: totalSeats(), covered: covered })" :disabled="loading"
                class="w-full btn-primary text-white py-3.5 rounded-xl text-[10px] font-bold uppercase tracking-widest shadow-sm hover:shadow-md transition-all disabled:opacity-50 disabled:cursor-not-allowed">
            <span x-show="!loading">Pay for the month</span>
            <span x-show="loading" x-cloak>Processing...</span>
        </button>
        <p class="text-[9px] text-brand-plum/30 text-center mt-2">Secure, encrypted payment. Your details are never stored on our servers.</p>
        <div x-show="errorMsg" x-cloak class="mt-3 bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-xs text-red-600" x-text="errorMsg"></div>
    </div>

    <?php if ($access === 'paid' && $sub_status === 'active' && $room > 0): ?>
    <div class="bg-brand-white border border-brand-sand rounded-xl p-6 shadow-sm" x-data="payMonth()">
        <h2 class="font-heading font-bold text-sm text-brand-plum mb-1 uppercase tracking-wider">Add seats now</h2>
        <p class="text-xs text-brand-plum/60 mb-4">For new staff this month<?= $period_end ? ', until ' . fmt_date($period_end, 'j M') : '' ?>. Next month you choose again.</p>
        <div class="flex items-center gap-3 mb-4">
            <button type="button" @click="add = Math.max(1, add - 1)" aria-label="One seat fewer" class="w-8 h-8 rounded-lg bg-brand-cream border border-brand-sand flex items-center justify-center hover:border-brand-gold">−</button>
            <span class="data-text text-lg font-extrabold text-brand-plum w-8 text-center" x-text="add"></span>
            <button type="button" @click="add = Math.min(<?= $room ?>, add + 1)" aria-label="One seat more" class="w-8 h-8 rounded-lg bg-brand-cream border border-brand-sand flex items-center justify-center hover:border-brand-gold">+</button>
            <span class="text-xs text-brand-plum/50" x-text="money(add * <?= (float)($prices['seat_now'] ?? $seat_cost) ?>)"></span>
        </div>
        <button type="button" @click="pay({ kind: 'seats', add_seats: add })" :disabled="loading"
                class="w-full bg-brand-cream border border-brand-sand text-brand-plum py-3 rounded-xl text-[10px] font-bold uppercase tracking-widest hover:border-brand-gold transition-all disabled:opacity-50">
            <span x-show="!loading">Pay for the seats</span>
            <span x-show="loading" x-cloak>Processing...</span>
        </button>
        <div x-show="errorMsg" x-cloak class="mt-3 bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-xs text-red-600" x-text="errorMsg"></div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php /* NFELIA AUDIT FIX H07 2026-06-03: Paystack JS removed — payment via authorization_url redirect */ ?>
    <script>
    function payMonth() {
        return {
            covered: <?= json_encode(array_map('intval', array_column($team, 'id'))) ?>,
            spare: 0,
            add: 1,
            loading: false,
            errorMsg: '',
            totalSeats() { return Math.min(<?= MAX_SEATS - 1 ?>, this.covered.length + this.spare); },
            money(n) { return <?= json_encode($sym) ?> + Math.round(n).toLocaleString(); },

            async pay(fields) {
                this.loading = true;
                this.errorMsg = '';
                try {
                    const formData = new FormData();
                    for (const [k, v] of Object.entries(fields)) {
                        if (Array.isArray(v)) v.forEach((x) => formData.append(k + '[]', x));
                        else formData.append(k, v);
                    }
                    formData.append('csrf_token', <?= json_encode($_SESSION['csrf_token'] ?? '') ?>);
                    const res = await fetch('<?= APP_URL ?>/billing/initialize', { method: 'POST', body: formData });
                    let data;
                    try { data = await res.json(); }
                    catch (e) { this.errorMsg = 'Something went wrong. Please try again.'; return; }
                    if (data.success && data.authorization_url) {
                        window.location.href = data.authorization_url;
                    } else {
                        this.errorMsg = data.error || 'Failed to start the payment.';
                    }
                } catch (err) {
                    this.errorMsg = 'Network request failed. Check your connection.';
                } finally {
                    this.loading = false;
                }
            }
        };
    }
    </script>

    <!-- Plan Features -->
    <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm">
        <h2 class="font-heading font-bold text-sm text-brand-plum mb-4 uppercase tracking-wider">What's Included</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <?php
            $features = [
                'All business modules', 'Unlimited invoices & receipts', 'Up to 500 products',
                'Quotation builder', 'Expense & supplier tracking', 'Debt management',
                'Delivery & dispatch', 'Profit & loss reports', 'Public landing page',
                'WhatsApp integration', 'PDF document export', 'E-signature collection',
            ];
            foreach ($features as $f): ?>
            <div class="flex items-center gap-2 text-xs text-brand-plum/70">
                <svg class="w-3.5 h-3.5 text-green-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                <?= $f ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Your records: the Records Book, free in every month paid for -->
    <a href="<?= APP_URL ?>/records" class="flex items-center gap-4 bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm hover:border-brand-gold transition-colors">
        <span class="shrink-0 w-10 h-10 rounded-lg bg-brand-cream border border-brand-sand flex items-center justify-center text-brand-burgundy" aria-hidden="true"><?= nav_icon('book') ?></span>
        <span class="grow">
            <span class="block font-heading font-bold text-sm text-brand-plum uppercase tracking-wider">Your records</span>
            <span class="block text-xs text-brand-plum/60 mt-1">Every month you pay for comes with a free Records Book: all your records in one Excel workbook, in your colours, with a code anyone can check. Your plain copy is free, always.</span>
        </span>
        <svg class="w-4 h-4 text-brand-plum/40 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 6 6 6-6 6"/></svg>
    </a>

    <!-- Payment History -->
    <?php if (!empty($payment_history)): ?>
    <div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-brand-sand">
            <h2 class="font-heading font-bold text-sm text-brand-plum uppercase tracking-wider">Payment History</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-brand-sand bg-brand-cream/50">
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Date</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Event</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-right">Amount</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-sand/50">
                    <?php foreach ($payment_history as $ph): ?>
                    <tr>
                        <td class="px-5 py-3 data-text text-[10px] text-brand-plum/60"><?= fmt_date($ph['created_at'], 'd/m/Y, H:i') ?></td>
                        <td class="px-5 py-3 text-xs text-brand-plum"><?= e($ph['event_type']) ?> <?php /* NFELIA AUDIT FIX M08 2026-06-03: description col doesn't exist */ ?></td>
                        <td class="px-5 py-3 data-text text-xs font-bold text-brand-plum text-right"><?= $ph['amount'] ? e($country_pricing['currency_symbol']) . number_format($ph['amount'] / 100, 0) : '—' ?></td>
                        <td class="px-5 py-3 text-center">
                            <span class="text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded
                                <?php if ($ph['status'] === 'success'): ?>bg-green-100 text-green-700
                                <?php elseif ($ph['status'] === 'failed'): ?>bg-red-100 text-red-700
                                <?php else: ?>bg-gray-100 text-gray-600<?php endif; ?>">
                                <?= e($ph['status']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Cancel Subscription -->
    <?php if (in_array($sub_status, ['active', 'past_due'])): ?>
    <div class="bg-brand-white border border-red-200 rounded-xl p-5 shadow-sm" x-data="{ showCancel: false }">
        <button type="button" @click="showCancel = !showCancel" class="text-[10px] font-bold text-red-400 uppercase tracking-widest hover:text-red-600">Cancel Subscription</button>
        <div x-show="showCancel" x-cloak class="mt-4">
            <form method="POST" action="<?= APP_URL ?>/billing/cancel" onsubmit="return confirm('Are you sure? You will lose access at the end of your billing period.')">
                <?= csrf_field() ?>
                <textarea name="cancel_reason" rows="2" placeholder="Why are you cancelling? (optional)"
                          class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-red-300 text-brand-plum resize-none mb-3"></textarea>
                <button type="submit" class="bg-red-500 text-white px-6 py-2.5 rounded-xl text-[10px] font-bold uppercase tracking-widest hover:bg-red-600 transition-colors">Confirm Cancellation</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>