<?php
/**
 * Nfelia — Recovery Transmission Sent
 *
 * Shown after every reset or sign-in link request, whether or not the
 * address has an account. It only repeats back what the visitor typed.
 */

$sent     = $_SESSION['link_sent'] ?? [];
$email    = $sent['email'] ?? '';
$is_reset = ($sent['purpose'] ?? 'reset') === 'reset';
$domain   = strtolower(substr(strrchr($email, '@') ?: '', 1));

// Determine the best Mail App Link
$mail_link = 'mailto:';
$mail_label = 'Open Mail App';

if ($domain === 'gmail.com') {
    $mail_link = 'https://mail.google.com/mail/u/0/#inbox';
    $mail_label = 'Open Gmail';
} elseif ($domain === 'yahoo.com') {
    $mail_link = 'https://mail.yahoo.com/d/folders/1';
    $mail_label = 'Open Yahoo Mail';
} elseif (in_array($domain, ['outlook.com', 'hotmail.com', 'live.com'])) {
    $mail_link = 'https://outlook.live.com/mail/0/inbox';
    $mail_label = 'Open Outlook';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?= errors_script_tag() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transmission Sent — Nfelia</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Plus+Jakarta+Sans:ital,wght@0,600;0,700;0,800;1,600&family=Roboto+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { brand: { burgundy: '#8B004A', gold: '#B69F5E', cream: '#F2EFE7', plum: '#1B0A11', sand: '#E8E4DA', white: '#FFFFFF' } },
                    fontFamily: { jakarta: ['"Plus Jakarta Sans"', 'sans-serif'], montserrat: ['Montserrat', 'sans-serif'], mono: ['"Roboto Mono"', 'monospace'] },
                    boxShadow: { 'flat': '0 4px 15px rgba(27, 10, 17, 0.03)' }
                }
            }
        }
    </script>
    <style>
        body { background-color: #F2EFE7; color: #1B0A11; font-family: 'Montserrat', sans-serif; }
        .font-heading { font-family: '"Plus Jakarta Sans"', sans-serif; }
        .data-text { font-family: '"Roboto Mono"', monospace; }
    </style>
</head>
<body class="min-h-screen flex flex-col items-center justify-center p-4">

    <div class="mb-10 text-center">
        <span class="font-heading font-extrabold text-2xl tracking-[0.2em] text-brand-burgundy uppercase">Nfelia</span>
    </div>

    <div class="w-full max-w-md relative">
        <div class="absolute inset-0 bg-brand-gold translate-x-2 translate-y-2 border border-brand-plum z-0 hidden sm:block"></div>
        
        <div class="relative bg-brand-white border border-brand-sand p-8 z-10 shadow-flat text-center">
            
            <div class="w-16 h-16 bg-brand-cream border border-brand-gold mx-auto mb-6 flex items-center justify-center">
                <svg class="w-8 h-8 text-brand-burgundy" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
            </div>

            <h2 class="font-heading font-bold text-2xl text-brand-plum mb-2">Transmission Sent</h2>
            <p class="text-sm text-brand-plum/70 font-medium mb-6">If an account uses this address, we've emailed it a <?= $is_reset ? 'password reset link' : 'one-time sign-in link' ?>:</p>

            <div class="data-text font-bold text-base text-brand-burgundy tracking-wide mb-4 p-3 border border-brand-sand bg-brand-cream break-all">
                <?= e($email) ?>
            </div>

            <p class="text-[11px] text-brand-plum/60 leading-relaxed mb-8">
                The link expires in <?= $is_reset ? '1 hour' : '15 minutes' ?>. Nothing after a few minutes?
                Check your spam folder, or
                <a href="<?= APP_URL ?>/<?= $is_reset ? 'forgot-password' : 'login-link' ?>" class="font-bold text-brand-burgundy hover:text-brand-gold">try another address you use</a>.
            </p>

            <a href="<?= $mail_link ?>" target="_blank" class="block w-full bg-brand-burgundy text-brand-white text-xs font-bold py-4 uppercase tracking-widest hover:bg-brand-gold transition-colors">
                <?= $mail_label ?>
            </a>
            
            <div class="mt-6">
                <a href="<?= APP_URL ?>/login" class="text-[10px] text-brand-plum/50 font-mono uppercase tracking-widest hover:text-brand-burgundy transition-colors">Return to Login</a>
            </div>
        </div>
    </div>
</body>
</html>