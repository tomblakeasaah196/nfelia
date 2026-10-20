<?php
/**
 * Nfelia — Reset Password View
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?= errors_script_tag() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- The URL carries the reset token: never send it to the font/CSS hosts. -->
    <meta name="referrer" content="no-referrer">
    <title>Establish New Access — Nfelia</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/public.css?v=<?= @filemtime(BASE_PATH . '/assets/css/public.css') ?>">
    <style>body { background-color: #F2EFE7; color: #1B0A11; } </style>
</head>
<body class="min-h-screen flex flex-col items-center justify-center p-4">

    <div class="mb-10 text-center">
        <span class="font-heading font-extrabold text-2xl tracking-[0.2em] text-brand-burgundy uppercase">Nfelia</span>
    </div>

    <div class="w-full max-w-md relative">
        <div class="absolute inset-0 bg-brand-gold translate-x-2 translate-y-2 border border-brand-plum z-0 hidden sm:block"></div>
        
        <div class="relative bg-brand-white border border-brand-sand p-8 z-10 shadow-sm">
            <div class="mb-8 border-b border-brand-sand pb-6">
                <h2 class="font-heading font-bold text-2xl text-brand-plum mb-2">Establish Passkey</h2>
                <p class="text-xs text-brand-plum/70 font-medium">Clearance validated. Construct a new secure passkey for your workspace.</p>
            </div>

            <?php $flash = get_flash(); if ($flash): ?>
                <div class="mb-6 p-4 text-xs font-bold data-text uppercase tracking-widest border <?= $flash['type'] === 'error' ? 'border-[#ff0000] bg-[#fff0f0] text-[#ff0000]' : 'border-brand-gold bg-brand-cream text-brand-burgundy' ?>">
                    > <?= e($flash['message']) ?>
                </div>
            <?php endif; ?>

            <form action="<?= APP_URL ?>/reset-password" method="POST" class="space-y-5">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                
                <div>
                    <label for="new-password" class="block text-xs font-bold text-brand-plum uppercase tracking-widest mb-2">New password</label>
                    <input id="new-password" type="password" name="password" required minlength="9" maxlength="200" autocomplete="new-password" class="w-full px-4 py-3 bg-brand-white border border-brand-sand text-brand-plum text-sm focus:border-brand-gold focus:ring-0 outline-none transition-all rounded-none placeholder-brand-plum/30" placeholder="••••••••">
                    <?php $pw_input = '#new-password'; $pw_confirm = '#new-password-confirm'; require BASE_PATH . '/views/partials/password_rules.php'; ?>
                </div>

                <div>
                    <label for="new-password-confirm" class="block text-xs font-bold text-brand-plum uppercase tracking-widest mb-2">Type it again</label>
                    <input id="new-password-confirm" type="password" name="password_confirm" required minlength="9" maxlength="200" autocomplete="new-password" class="w-full px-4 py-3 bg-brand-white border border-brand-sand text-brand-plum text-sm focus:border-brand-gold focus:ring-0 outline-none transition-all rounded-none placeholder-brand-plum/30" placeholder="••••••••">
                </div>

                <button type="submit" class="w-full bg-brand-plum text-brand-white text-xs font-bold py-4 uppercase tracking-widest hover:bg-brand-burgundy transition-colors mt-4">
                    Confirm & Update
                </button>
            </form>
        </div>
    </div>
</body>
</html>