<?php
/**
 * Nfelia — Forgot Password View
 *
 * One field: the email address. The next screen says the same thing whether
 * or not the address has an account, so this page cannot be used to look up
 * who is registered. (Replaced the workspace-name lookup on 2026-10-17, which
 * listed every user of any workspace whose name someone could guess.)
 */
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8">
    
    <?= errors_script_tag() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Recover Workspace — Nfelia</title>
    
    
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/public.css?v=<?= @filemtime(BASE_PATH . '/assets/css/public.css') ?>">
    <style>
        body { background-color: #F2EFE7; color: #1B0A11; }
        </style>
</head>
<body class="min-h-screen flex flex-col items-center justify-center p-4">

    <div class="mb-10 text-center">
        <a href="<?= APP_URL ?>/" class="inline-flex items-center gap-3">
            <img src="<?= APP_URL ?>/assets/img/nfelia-logo.png" alt="Nfelia Logo" class="h-10 w-auto object-contain">
            <span class="font-heading font-extrabold text-2xl tracking-[0.2em] text-brand-burgundy uppercase">Nfelia</span>
        </a>
    </div>

    <div class="w-full max-w-md relative">
        <div class="absolute inset-0 bg-brand-gold translate-x-2 translate-y-2 border border-brand-plum z-0 hidden sm:block"></div>
        
        <div class="relative bg-brand-white border border-brand-sand p-8 z-10 shadow-flat">
            
            <?php $flash = get_flash(); if ($flash): ?>
                <div class="mb-6 p-4 text-xs font-bold data-text uppercase tracking-widest border <?= $flash['type'] === 'error' ? 'border-[#ff0000] bg-[#fff0f0] text-[#ff0000]' : 'border-brand-gold bg-brand-cream text-brand-burgundy' ?>">
                    > <?= e($flash['message']) ?>
                </div>
            <?php endif; ?>


            <div class="mb-8 border-b border-brand-sand pb-6">
                <h2 class="font-heading font-bold text-2xl text-brand-plum mb-2">Initiate Recovery</h2>
                <p class="text-xs text-brand-plum/70 font-medium">Enter the email address you sign in with and we'll send you a link to set a new password.</p>
            </div>

            <form action="<?= APP_URL ?>/forgot-password" method="POST" class="space-y-5">
                <?= csrf_field() ?>

                <div>
                    <label class="block text-xs font-bold text-brand-plum uppercase tracking-widest mb-2">Email Address</label>
                    <input type="email" name="email" required autocomplete="email" autofocus class="w-full px-4 py-3 bg-brand-white border border-brand-sand text-brand-plum text-sm focus:border-brand-gold focus:ring-0 outline-none transition-all rounded-none placeholder-brand-plum/30" placeholder="director@company.com">
                    <p class="mt-2 text-[11px] text-brand-plum/60 leading-relaxed">Not sure which address you used? Try each one. Only an address with an account will receive a link.</p>
                </div>

                <button type="submit" class="w-full bg-brand-burgundy text-brand-white text-xs font-bold py-4 uppercase tracking-widest hover:bg-brand-gold transition-colors mt-4">
                    Send Reset Link
                </button>
            </form>

            <div class="mt-6 pt-6 border-t border-brand-sand space-y-2 text-[11px] text-brand-plum/60 leading-relaxed">
                <p>Just want to get in? <a href="<?= APP_URL ?>/login-link" class="font-bold text-brand-burgundy hover:text-brand-gold">Email me a sign-in link</a> instead.</p>
                <p>Team member? Your workspace owner can also send you a reset link from Settings &rarr; Staff.</p>
            </div>
            <div class="mt-6 text-center">
                <a href="<?= APP_URL ?>/login" class="text-[10px] text-brand-plum/50 font-mono uppercase tracking-widest hover:text-brand-burgundy transition-colors">Abort & Return to Login</a>
            </div>
        </div>
    </div>
</body>
</html>