<?php
/**
 * Nfelia — Onboarding Wizard View
 *
 * NFELIA FIX — Country selector added to Step 1 — 2026-06-04
 * NFELIA FIX — Step 2 currency driven by countries table — 2026-06-04
 *
 * Three steps here; the shop link and look (views/shop_setup) are the fourth.
 *
 * Available from controller:
 *   Step 1: $countries (active countries list)
 *   Step 2: $countries, $current_currency
 * After a refused save, what the person typed comes back in $old
 * (OnboardingController::back_to_step).
 */
$business = auth_business();
$categories = BUSINESS_CATEGORIES ?? ['other' => 'Other'];
$fonts = FONT_PAIRINGS ?? [];
$old = $_SESSION['onboarding_old'] ?? [];
unset($_SESSION['onboarding_old']);
$intro = [
    1 => 'Tell us about ' . $business['name'] . '. This goes on your invoices, receipts and shop.',
    2 => 'The money you work in, and where customers pay you.',
    3 => 'Your colours, lettering and logo. They go on your invoices, receipts and shop.',
][$step];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?= errors_script_tag() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> — Nfelia setup</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/public.css?v=<?= @filemtime(BASE_PATH . '/assets/css/public.css') ?>">
    <style>body { background-color: #F2EFE7; color: #1B0A11; }</style>
</head>
<body class="min-h-screen flex flex-col items-center justify-center p-4 lg:p-8">

    <div class="w-full max-w-3xl">
        <!-- Header -->
        <div class="mb-8 flex items-center justify-between border-b border-brand-sand pb-4">
            <div class="flex items-center gap-3">
                <svg class="h-8 w-8 text-brand-burgundy" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="40" height="40" rx="4" fill="currentColor"/>
                    <path d="M10 30L30 10" stroke="#F2EFE7" stroke-width="3" stroke-linecap="square"/>
                    <circle cx="20" cy="20" r="14" stroke="#B69F5E" stroke-width="1.5" stroke-dasharray="4 4"/>
                </svg>
                <span class="font-heading font-extrabold text-xl tracking-[0.2em] text-brand-burgundy uppercase">Nfelia</span>
            </div>
            <div class="text-xs font-bold text-brand-plum border border-brand-gold px-3 py-1 bg-brand-white">
                Step <?= $step ?> of 4
            </div>
        </div>

        <?php $flash = get_flash(); if ($flash): ?>
            <div class="mb-6 p-4 text-sm font-medium border <?= $flash['type'] === 'error' ? 'border-[#ff0000] bg-[#fff0f0] text-[#ff0000]' : 'border-brand-gold bg-brand-cream text-brand-burgundy' ?>" role="alert">
                <?= e($flash['message']) ?>
            </div>
        <?php endif; ?>

        <!-- Main Form Container -->
        <div class="bg-brand-white border border-brand-plum shadow-flat-hard p-8 lg:p-12 relative">
            <h1 class="font-heading font-extrabold text-3xl text-brand-plum tracking-tight mb-2"><?= e($title) ?></h1>
            <p class="text-sm text-brand-plum/70 font-medium mb-8"><?= e($intro) ?></p>

            <form action="<?= APP_URL ?>/onboarding/step<?= $step ?>" method="POST" <?= $step === 3 ? 'enctype="multipart/form-data"' : '' ?> class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

                <?php if ($step === 1): ?>
                    <!-- STEP 1: ABOUT YOUR BUSINESS -->
                    <div class="grid md:grid-cols-2 gap-6">

                        <!-- Country Selector -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-brand-plum mb-2">Country</label>
                            <select name="country_id" required class="w-full px-4 py-3 bg-brand-white border border-brand-sand text-brand-plum text-sm focus:border-brand-gold focus:ring-0 outline-none rounded-none">
                                <option value="">Choose your country</option>
                                <?php $picked_country = (int)($old['country_id'] ?? $business['country_id'] ?? 0); ?>
                                <?php foreach ($countries as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>"
                                        <?= ($picked_country === (int)$c['id']) ? 'selected' : '' ?>>
                                        <?= e($c['flag_emoji'] ?? '🌐') ?> <?= e($c['name']) ?> (<?= e($c['currency_symbol']) ?> <?= e($c['currency_code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-xs text-brand-plum/60 mt-1">Sets your money and what Nfelia costs you each month.</p>
                        </div>

                        <!-- Business Type Selector -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-brand-plum mb-3">What do you sell?</label>
                            <div class="grid grid-cols-3 gap-3" id="business-type-selector">
                                <?php
                                $biz_type_opts = [
                                    'product' => ['label' => 'Products', 'sub' => 'Things you keep in stock', 'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
                                    'service' => ['label' => 'Services', 'sub' => 'Work you do for people', 'icon' => 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
                                    'mixed'   => ['label' => 'Both', 'sub' => 'Products and services', 'icon' => 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z'],
                                ];
                                foreach ($biz_type_opts as $val => $opt): ?>
                                <label class="relative cursor-pointer" for="btype_<?= $val ?>">
                                    <input type="radio" name="business_type" id="btype_<?= $val ?>" value="<?= $val ?>"
                                           class="sr-only peer" <?= $val === ($old['business_type'] ?? 'product') ? 'checked' : '' ?>>
                                    <div class="flex flex-col items-center gap-2 p-4 border-2 border-brand-sand rounded-none text-center
                                                transition-all peer-checked:border-brand-gold peer-checked:bg-brand-gold/5 hover:border-brand-plum/30">
                                        <svg class="w-7 h-7 text-brand-plum/50 peer-checked:text-brand-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="<?= $opt['icon'] ?>"/>
                                        </svg>
                                        <span class="text-sm font-bold text-brand-plum"><?= $opt['label'] ?></span>
                                        <span class="text-xs text-brand-plum/60"><?= $opt['sub'] ?></span>
                                    </div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-brand-plum mb-2">Type of business</label>
                            <select name="category" class="w-full px-4 py-3 bg-brand-white border border-brand-sand text-brand-plum text-sm focus:border-brand-gold focus:ring-0 outline-none rounded-none">
                                <?php foreach ($categories as $val => $label): ?>
                                    <option value="<?= e($val) ?>" <?= ($old['category'] ?? '') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-brand-plum mb-2">A short line about your business <span class="text-brand-plum/50 font-medium">(optional)</span></label>
                            <input type="text" name="tagline" maxlength="255" value="<?= e($old['tagline'] ?? '') ?>" class="w-full px-4 py-3 bg-brand-white border border-brand-sand text-brand-plum text-sm focus:border-brand-gold focus:ring-0 outline-none rounded-none" placeholder="e.g. Quality hair. Fast delivery.">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-brand-plum mb-2">Business registration number <span class="text-brand-plum/50 font-medium">(optional)</span></label>
                            <input type="text" name="registration_number" maxlength="100" value="<?= e($old['registration_number'] ?? '') ?>" class="w-full px-4 py-3 bg-brand-white border border-brand-sand text-brand-plum text-sm focus:border-brand-gold focus:ring-0 outline-none rounded-none" placeholder="e.g. RC 1049281">
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-brand-plum mb-2">Phone number</label>
                            <input type="tel" name="phone" maxlength="20" value="<?= e($old['phone'] ?? '') ?>" class="w-full px-4 py-3 bg-brand-white border border-brand-sand text-brand-plum text-sm focus:border-brand-gold focus:ring-0 outline-none rounded-none" placeholder="e.g. 08012345678">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-brand-plum mb-2">WhatsApp number</label>
                            <input type="tel" name="whatsapp" maxlength="20" value="<?= e($old['whatsapp'] ?? '') ?>" class="w-full px-4 py-3 bg-brand-white border border-brand-sand text-brand-plum text-sm focus:border-brand-gold focus:ring-0 outline-none rounded-none" placeholder="e.g. 08012345678">
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-brand-plum mb-2">Shop or office address</label>
                            <textarea name="address" rows="3" class="w-full px-4 py-3 bg-brand-white border border-brand-sand text-brand-plum text-sm focus:border-brand-gold focus:ring-0 outline-none rounded-none resize-none" placeholder="Shows on your invoices and quotes"><?= e($old['address'] ?? '') ?></textarea>
                        </div>
                    </div>

                <?php elseif ($step === 2): ?>
                    <!-- STEP 2: MONEY AND PAYMENTS -->
                    <div class="grid md:grid-cols-2 gap-6 mb-8 border-b border-brand-sand pb-8">
                        <div>
                            <label class="block text-sm font-bold text-brand-plum mb-2">Currency</label>
                            <select name="currency_symbol" class="w-full px-4 py-3 bg-brand-white border border-brand-sand text-brand-plum text-sm focus:border-brand-gold focus:ring-0 outline-none rounded-none">
                                <?php foreach ($countries as $c): ?>
                                    <option value="<?= e($c['currency_symbol']) ?>"
                                        <?= (($old['currency_symbol'] ?? $current_currency) === $c['currency_symbol']) ? 'selected' : '' ?>>
                                        <?= e($c['flag_emoji'] ?? '🌐') ?> <?= e($c['currency_code']) ?> (<?= e($c['currency_symbol']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-xs text-brand-plum/60 mt-1">Set from your country. Change it if you charge customers in another currency.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-brand-plum mb-2">VAT or tax on your invoices (%)</label>
                            <input type="number" step="0.01" min="0" max="100" name="default_tax_rate" value="<?= e($old['default_tax_rate'] ?? '7.50') ?>" class="w-full px-4 py-3 bg-brand-white border border-brand-sand text-brand-plum text-sm focus:border-brand-gold focus:ring-0 outline-none rounded-none" placeholder="e.g. 7.50">
                            <p class="text-xs text-brand-plum/60 mt-1">Put 0 if you don't charge VAT.</p>
                        </div>
                    </div>

                    <h3 class="font-heading font-bold text-base text-brand-plum mb-1">Where customers pay you <span class="text-sm text-brand-plum/50 font-medium">(optional)</span></h3>
                    <p class="text-xs text-brand-plum/60 mb-4">No business account yet? Leave this empty and add it later in Settings, under Payment accounts.</p>
                    <div class="grid md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-brand-plum mb-2">Bank name</label>
                            <input type="text" name="bank_name" value="<?= e($old['bank_name'] ?? '') ?>" class="w-full px-4 py-3 bg-brand-white border border-brand-sand text-brand-plum text-sm focus:border-brand-gold focus:ring-0 outline-none rounded-none" placeholder="e.g. Guaranty Trust Bank">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-brand-plum mb-2">Account number</label>
                            <input type="text" name="account_number" inputmode="numeric" value="<?= e($old['account_number'] ?? '') ?>" class="w-full px-4 py-3 bg-brand-white border border-brand-sand text-brand-plum text-sm focus:border-brand-gold focus:ring-0 outline-none rounded-none" placeholder="e.g. 0123456789">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-brand-plum mb-2">Account name</label>
                            <input type="text" name="account_name" value="<?= e($old['account_name'] ?? '') ?>" class="w-full px-4 py-3 bg-brand-white border border-brand-sand text-brand-plum text-sm focus:border-brand-gold focus:ring-0 outline-none rounded-none" placeholder="The name on the account">
                        </div>
                    </div>

                <?php elseif ($step === 3): ?>
                    <!-- STEP 3: HOW YOUR BUSINESS LOOKS -->
                    <?php
                    // The colours the business has now (the burgundy and gold
                    // from sign-up), so not choosing keeps them
                    $primary_now   = $business['primary_color'] ?? '#8B004A';
                    $secondary_now = $business['secondary_color'] ?? '#B69F5E';
                    ?>
                    <div class="grid md:grid-cols-2 gap-8 mb-8 border-b border-brand-sand pb-8">
                        <div>
                            <label class="block text-sm font-bold text-brand-plum mb-2">Main colour</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="primary_color" id="primary_color_picker" value="<?= e($primary_now) ?>" class="h-12 w-12 cursor-pointer border-none p-0 bg-transparent" oninput="document.getElementById('primary_color_hex').value = this.value">
                                <input type="text" id="primary_color_hex" value="<?= e($primary_now) ?>" class="w-full px-4 py-3 bg-brand-white border border-brand-sand font-mono text-sm uppercase rounded-none" oninput="if(/^#[0-9A-Fa-f]{6}$/.test(this.value)) document.getElementById('primary_color_picker').value = this.value" maxlength="7" placeholder="<?= e($primary_now) ?>">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-brand-plum mb-2">Second colour</label>
                            <div class="flex items-center gap-3">
                                <input type="color" name="secondary_color" id="secondary_color_picker" value="<?= e($secondary_now) ?>" class="h-12 w-12 cursor-pointer border-none p-0 bg-transparent" oninput="document.getElementById('secondary_color_hex').value = this.value">
                                <input type="text" id="secondary_color_hex" value="<?= e($secondary_now) ?>" class="w-full px-4 py-3 bg-brand-white border border-brand-sand font-mono text-sm uppercase rounded-none" oninput="if(/^#[0-9A-Fa-f]{6}$/.test(this.value)) document.getElementById('secondary_color_picker').value = this.value" maxlength="7" placeholder="<?= e($secondary_now) ?>">
                            </div>
                        </div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-8">
                        <div>
                            <label class="block text-sm font-bold text-brand-plum mb-2">Lettering</label>
                            <select name="font_pairing" class="w-full px-4 py-3 bg-brand-white border border-brand-sand text-brand-plum text-sm focus:border-brand-gold focus:ring-0 outline-none rounded-none mb-2">
                                <?php foreach (['modern_sans' => 'Clean and modern (Inter + DM Sans)', 'corporate_serif' => 'Classic and formal (EB Garamond + Questrial)', 'bold_display' => 'Bold and creative (Syne + Outfit)'] as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= ($business['font_pairing'] ?? 'modern_sans') === $val ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-xs text-brand-plum/60 font-medium">Used on the invoices, quotes and receipts you send.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-brand-plum mb-2">Your logo <span class="text-brand-plum/50 font-medium">(optional)</span></label>
                            <div class="border-2 border-dashed border-brand-sand hover:border-brand-gold transition-colors p-4 text-center cursor-pointer relative bg-brand-cream/30">
                                <input type="file" name="logo" accept="image/png, image/jpeg, image/webp" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" onchange="this.nextElementSibling.textContent = this.files.length ? this.files[0].name : 'Choose a picture'">
                                <span class="text-sm text-brand-plum font-bold break-all">Choose a picture</span><br>
                                <span class="text-xs text-brand-plum/60">JPG, PNG or WebP, up to 20MB</span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="pt-8 flex justify-end">
                    <button type="submit" class="bg-brand-burgundy text-brand-white text-sm font-bold px-10 py-4 hover:bg-brand-gold transition-colors shadow-sm">
                        Save and continue
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
