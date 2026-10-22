<?php
/**
 * Nfelia — Onboarding Wizard View
 *
 * NFELIA FIX — Country selector added to Step 1 — 2026-06-04
 * NFELIA FIX — Step 2 currency driven by countries table — 2026-06-04
 *
 * Three steps here; the shop link and look (views/shop_setup) are the fourth.
 * Dressed like sign-up (views/auth/register.php): the same card, mark, fonts
 * and fields from assets/css/home.css, plus its "Onboarding" block.
 *
 * Available from controller:
 *   Step 1: $countries (active countries list)
 *   Step 2: $country (the one chosen in step 1, or null), $current_currency
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
$home_css = BASE_PATH . '/assets/css/home.css';
?>
<!DOCTYPE html>
<html lang="en-NG">
<head>
    <meta charset="UTF-8">
    <?= errors_script_tag() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> — Nfelia setup</title>
    <meta name="theme-color" content="#8B004A">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,700&family=Plus+Jakarta+Sans:wght@400;600&display=swap">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/home.css?v=<?= @filemtime($home_css) ?>">
</head>
<body class="auth-page">
    <header class="auth-top">
        <span class="brand">
            <img src="<?= APP_URL ?>/assets/img/nfelia-mark-email.png" alt="" width="55" height="40">
            <span>NFELIA</span>
        </span>
    </header>

    <main class="auth-main">
        <div class="auth-card setup-card">
            <p class="setup-step">Step <?= $step ?> of 4</p>
            <h1><?= e($title) ?></h1>
            <p class="lead"><?= e($intro) ?></p>

            <?php $flash = get_flash(); if ($flash): ?>
                <p class="auth-flash <?= $flash['type'] === 'error' ? 'is-error' : '' ?>" role="alert"><?= e($flash['message']) ?></p>
            <?php endif; ?>

            <form action="<?= APP_URL ?>/onboarding/step<?= $step ?>" method="POST" <?= $step === 3 ? 'enctype="multipart/form-data"' : '' ?>>
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

                <?php if ($step === 1): ?>
                    <!-- STEP 1: ABOUT YOUR BUSINESS -->
                    <div class="field">
                        <label for="country_id">Country</label>
                        <select id="country_id" name="country_id" required aria-describedby="country-hint">
                            <option value="">Choose your country</option>
                            <?php $picked_country = (int)($old['country_id'] ?? $business['country_id'] ?? 0); ?>
                            <?php foreach ($countries as $c): ?>
                                <option value="<?= (int)$c['id'] ?>"
                                    <?= ($picked_country === (int)$c['id']) ? 'selected' : '' ?>>
                                    <?= e($c['flag_emoji'] ?? '🌐') ?> <?= e($c['name']) ?> (<?= e($c['currency_symbol']) ?> <?= e($c['currency_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="hint" id="country-hint">Sets your money and what Nfelia costs you each month.</span>
                    </div>

                    <fieldset class="field">
                        <legend>What do you sell?</legend>
                        <div class="setup-types" id="business-type-selector">
                            <?php
                            $biz_type_opts = [
                                'product' => ['label' => 'Products', 'sub' => 'Things you keep in stock', 'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
                                'service' => ['label' => 'Services', 'sub' => 'Work you do for people', 'icon' => 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
                                'mixed'   => ['label' => 'Both', 'sub' => 'Products and services', 'icon' => 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z'],
                            ];
                            foreach ($biz_type_opts as $val => $opt): ?>
                            <label class="setup-type" for="btype_<?= $val ?>">
                                <input type="radio" name="business_type" id="btype_<?= $val ?>" value="<?= $val ?>"
                                       <?= $val === ($old['business_type'] ?? 'product') ? 'checked' : '' ?>>
                                <span class="setup-type-box">
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="<?= $opt['icon'] ?>"/>
                                    </svg>
                                    <b><?= $opt['label'] ?></b>
                                    <small><?= $opt['sub'] ?></small>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <div class="field">
                        <label for="category">Type of business</label>
                        <select id="category" name="category">
                            <?php foreach ($categories as $val => $label): ?>
                                <option value="<?= e($val) ?>" <?= ($old['category'] ?? '') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="tagline">A short line about your business <span class="setup-optional">(optional)</span></label>
                        <input id="tagline" type="text" name="tagline" maxlength="255" value="<?= e($old['tagline'] ?? '') ?>" placeholder="e.g. Quality hair. Fast delivery.">
                    </div>
                    <div class="field">
                        <label for="registration_number">Business registration number <span class="setup-optional">(optional)</span></label>
                        <input id="registration_number" type="text" name="registration_number" maxlength="100" value="<?= e($old['registration_number'] ?? '') ?>" placeholder="e.g. RC 1049281">
                    </div>

                    <div class="setup-pair">
                        <div class="field">
                            <label for="phone">Phone number</label>
                            <input id="phone" type="tel" name="phone" maxlength="20" value="<?= e($old['phone'] ?? '') ?>" placeholder="e.g. 08012345678">
                        </div>
                        <div class="field">
                            <label for="whatsapp">WhatsApp number</label>
                            <input id="whatsapp" type="tel" name="whatsapp" maxlength="20" value="<?= e($old['whatsapp'] ?? '') ?>" placeholder="e.g. 08012345678">
                        </div>
                    </div>

                    <div class="field">
                        <label for="address">Shop or office address</label>
                        <textarea id="address" name="address" rows="3" placeholder="Shows on your invoices and quotes"><?= e($old['address'] ?? '') ?></textarea>
                    </div>

                <?php elseif ($step === 2): ?>
                    <!-- STEP 2: MONEY AND PAYMENTS -->
                    <div class="field">
                        <span class="setup-label">Currency</span>
                        <p class="setup-fixed">
                            <?php if ($country): ?>
                                <?= e($country['flag_emoji'] ?? '🌐') ?> <?= e($country['currency_code']) ?> (<?= e($country['currency_symbol']) ?>)
                            <?php else: ?>
                                <?= e($current_currency) ?>
                            <?php endif; ?>
                        </p>
                        <span class="hint">It comes from your country<?= $country ? ', ' . e($country['name']) : '' ?>.</span>
                    </div>
                    <div class="field">
                        <label for="default_tax_rate">VAT or tax on your invoices (%)</label>
                        <input id="default_tax_rate" type="number" step="0.01" min="0" max="100" name="default_tax_rate" value="<?= e($old['default_tax_rate'] ?? '0') ?>" placeholder="e.g. 7.5" aria-describedby="tax-hint">
                        <span class="hint" id="tax-hint">Add your VAT rate if you charge it.</span>
                    </div>

                    <div class="setup-head">
                        <h2>Where customers pay you <span class="setup-optional">(optional)</span></h2>
                        <p class="hint">No business account yet? Leave this empty and add it later in Settings, under Payment accounts.</p>
                    </div>
                    <div class="field">
                        <label for="bank_name">Bank name</label>
                        <input id="bank_name" type="text" name="bank_name" value="<?= e($old['bank_name'] ?? '') ?>" placeholder="e.g. Guaranty Trust Bank">
                    </div>
                    <div class="setup-pair">
                        <div class="field">
                            <label for="account_number">Account number</label>
                            <input id="account_number" type="text" name="account_number" inputmode="numeric" value="<?= e($old['account_number'] ?? '') ?>" placeholder="e.g. 0123456789">
                        </div>
                        <div class="field">
                            <label for="account_name">Account name</label>
                            <input id="account_name" type="text" name="account_name" value="<?= e($old['account_name'] ?? '') ?>" placeholder="The name on the account">
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
                    <div class="setup-pair">
                        <div class="field">
                            <label for="primary_color_picker">Main colour</label>
                            <div class="setup-colour">
                                <input type="color" name="primary_color" id="primary_color_picker" value="<?= e($primary_now) ?>" oninput="document.getElementById('primary_color_hex').value = this.value">
                                <input type="text" id="primary_color_hex" value="<?= e($primary_now) ?>" oninput="if(/^#[0-9A-Fa-f]{6}$/.test(this.value)) document.getElementById('primary_color_picker').value = this.value" maxlength="7" placeholder="<?= e($primary_now) ?>" aria-label="Main colour code">
                            </div>
                        </div>
                        <div class="field">
                            <label for="secondary_color_picker">Second colour</label>
                            <div class="setup-colour">
                                <input type="color" name="secondary_color" id="secondary_color_picker" value="<?= e($secondary_now) ?>" oninput="document.getElementById('secondary_color_hex').value = this.value">
                                <input type="text" id="secondary_color_hex" value="<?= e($secondary_now) ?>" oninput="if(/^#[0-9A-Fa-f]{6}$/.test(this.value)) document.getElementById('secondary_color_picker').value = this.value" maxlength="7" placeholder="<?= e($secondary_now) ?>" aria-label="Second colour code">
                            </div>
                        </div>
                    </div>

                    <div class="field">
                        <label for="font_pairing">Lettering</label>
                        <select id="font_pairing" name="font_pairing" aria-describedby="font-hint">
                            <?php foreach (['modern_sans' => 'Clean and modern (Inter + DM Sans)', 'corporate_serif' => 'Classic and formal (EB Garamond + Questrial)', 'bold_display' => 'Bold and creative (Syne + Outfit)'] as $val => $label): ?>
                                <option value="<?= $val ?>" <?= ($business['font_pairing'] ?? 'modern_sans') === $val ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="hint" id="font-hint">Used on the invoices, quotes and receipts you send.</span>
                    </div>
                    <div class="field">
                        <label for="logo">Your logo <span class="setup-optional">(optional)</span></label>
                        <div class="setup-file">
                            <input id="logo" type="file" name="logo" accept="image/png, image/jpeg, image/webp" onchange="this.nextElementSibling.textContent = this.files.length ? this.files[0].name : 'Choose a picture'">
                            <span class="setup-file-name">Choose a picture</span>
                            <span class="hint">JPG, PNG or WebP, up to 20MB</span>
                        </div>
                    </div>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary">Save and continue</button>
            </form>
        </div>
    </main>
</body>
</html>
