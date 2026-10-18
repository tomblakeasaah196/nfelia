<?php
/**
 * Nfelia — Sign-in Link Confirmation
 *
 * Opening the emailed link lands here; signing in needs the button press.
 * Mail scanners open links automatically and must not use up the token.
 * Naming the account also stops anyone tricking a person into signing in
 * to an account that is not theirs with a link of their own.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- The URL carries the sign-in token: never send it to the font/CSS hosts. -->
    <meta name="referrer" content="no-referrer">
    <title>Sign In — Nfelia</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Plus+Jakarta+Sans:ital,wght@0,600;0,700;0,800;1,600&family=Roboto+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { brand: { burgundy: '#8B004A', gold: '#B69F5E', cream: '#F2EFE7', plum: '#1B0A11', sand: '#E8E4DA', white: '#FFFFFF' } },
                    fontFamily: { jakarta: ['"Plus Jakarta Sans"', 'sans-serif'], montserrat: ['Montserrat', 'sans-serif'], mono: ['"Roboto Mono"', 'monospace'] }
                }
            }
        }
    </script>
    <style>body { background-color: #F2EFE7; color: #1B0A11; font-family: 'Montserrat', sans-serif; } .font-heading { font-family: '"Plus Jakarta Sans"', sans-serif; } .data-text { font-family: '"Roboto Mono"', monospace; }</style>
</head>
<body class="min-h-screen flex flex-col items-center justify-center p-4">

    <div class="mb-10 text-center">
        <span class="font-heading font-extrabold text-2xl tracking-[0.2em] text-brand-burgundy uppercase">Nfelia</span>
    </div>

    <div class="w-full max-w-md relative">
        <div class="absolute inset-0 bg-brand-gold translate-x-2 translate-y-2 border border-brand-plum z-0 hidden sm:block"></div>
        
        <div class="relative bg-brand-white border border-brand-sand p-8 z-10 shadow-sm">
            <div class="mb-8 border-b border-brand-sand pb-6">
                <h2 class="font-heading font-bold text-2xl text-brand-plum mb-2">Sign In to Nfelia</h2>
                <p class="text-xs text-brand-plum/70 font-medium">Your sign-in link is valid. Continue as:</p>
                <div class="data-text font-bold text-sm text-brand-burgundy mt-3 p-3 border border-brand-sand bg-brand-cream break-all"><?= e($account['email']) ?></div>
            </div>

            <?php $flash = get_flash(); if ($flash): ?>
                <div class="mb-6 p-4 text-xs font-bold data-text uppercase tracking-widest border <?= $flash['type'] === 'error' ? 'border-[#ff0000] bg-[#fff0f0] text-[#ff0000]' : 'border-brand-gold bg-brand-cream text-brand-burgundy' ?>">
                    > <?= e($flash['message']) ?>
                </div>
            <?php endif; ?>

            <form action="<?= APP_URL ?>/signin" method="POST" class="space-y-5">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <input type="hidden" name="next" value="<?= e(signin_next_valid($_GET['next'] ?? '')) ?>">
                
                <button type="submit" class="w-full bg-brand-burgundy text-brand-white text-xs font-bold py-4 uppercase tracking-widest hover:bg-brand-gold transition-colors">
                    Sign In
                </button>
            </form>

            <p class="mt-6 text-[11px] text-brand-plum/60 leading-relaxed text-center">Not your account, or you didn't ask for this link? Close this page; nothing happens until you press Sign In.</p>
        </div>
    </div>
</body>
</html>