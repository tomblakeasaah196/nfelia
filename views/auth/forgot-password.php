<?php
/**
 * Nfelia — Forgot Password View (Workspace Challenge Flow)
 * 
 * NFELIA FIX — Now supports ALL business users (staff + admin), not just owner — 2026-06-05
 *
 * Three states:
 *   State 1: Enter business name → locate workspace
 *   State 2: Select your account from masked email list (NEW)
 *   State 3: Type full email to verify → send reset link
 */

$masked_hint       = $_SESSION['recovery_masked_hint'] ?? null;       // State 3: single masked email
$recovery_accounts = $_SESSION['recovery_accounts'] ?? null;          // State 2: array of masked accounts
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recover Workspace — Nfelia</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Plus+Jakarta+Sans:ital,wght@0,600;0,700;0,800;1,600&family=Roboto+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { 
                        brand: { 
                            burgundy: '#8B004A', 
                            gold: '#B69F5E', 
                            cream: '#F2EFE7', 
                            plum: '#1B0A11', 
                            sand: '#E8E4DA', 
                            white: '#FFFFFF' 
                        } 
                    },
                    fontFamily: { 
                        jakarta: ['"Plus Jakarta Sans"', 'sans-serif'], 
                        montserrat: ['Montserrat', 'sans-serif'], 
                        mono: ['"Roboto Mono"', 'monospace'] 
                    },
                    boxShadow: { 
                        'flat': '0 4px 15px rgba(27, 10, 17, 0.03)' 
                    }
                }
            }
        }
    </script>
    <style>
        body { background-color: #F2EFE7; color: #1B0A11; font-family: 'Montserrat', sans-serif; }
        h1, h2, h3, .font-heading { font-family: '"Plus Jakarta Sans"', sans-serif; }
        .data-text { font-family: '"Roboto Mono"', monospace; }
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


            <?php if ($masked_hint): ?>
            <!-- ═══ STATE 3: Verify full email ═══ -->

                <div class="mb-6 border-b border-brand-sand pb-6">
                    <h2 class="font-heading font-bold text-2xl text-brand-plum mb-2">Security Challenge</h2>
                    <p class="text-xs text-brand-plum/70 font-medium">To authorize the reset, type the full email that matches this account:</p>
                </div>

                <div class="data-text font-bold text-lg text-brand-burgundy tracking-widest mb-6 p-3 border border-brand-sand bg-brand-cream text-center">
                    <?= e($masked_hint) ?>
                </div>

                <form action="<?= APP_URL ?>/forgot-password" method="POST" class="space-y-5">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                    <input type="hidden" name="verify_email" value="1">
                    
                    <div>
                        <label class="block text-xs font-bold text-brand-plum uppercase tracking-widest mb-2">Full Email Address</label>
                        <input type="email" name="email" required class="w-full px-4 py-3 bg-brand-white border border-brand-sand text-brand-plum text-sm focus:border-brand-gold focus:ring-0 outline-none transition-all rounded-none placeholder-brand-plum/30" placeholder="Type the exact match...">
                    </div>

                    <button type="submit" class="w-full bg-brand-burgundy text-brand-white text-xs font-bold py-4 uppercase tracking-widest hover:bg-brand-gold transition-colors mt-4">
                        Verify & Transmit
                    </button>
                </form>


            <?php elseif ($recovery_accounts): ?>
            <!-- ═══ STATE 2: Select your account ═══ -->

                <div class="mb-6 border-b border-brand-sand pb-6">
                    <h2 class="font-heading font-bold text-2xl text-brand-plum mb-2">Select Your Account</h2>
                    <p class="text-xs text-brand-plum/70 font-medium">Workspace located. Select your account to continue recovery:</p>
                </div>

                <div class="space-y-2 mb-6">
                    <?php foreach ($recovery_accounts as $idx => $acct): ?>
                        <form action="<?= APP_URL ?>/forgot-password" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                            <input type="hidden" name="select_account" value="1">
                            <input type="hidden" name="account_index" value="<?= $idx ?>">
                            <button type="submit" class="w-full text-left px-4 py-3 border border-brand-sand bg-brand-cream hover:border-brand-gold hover:bg-brand-gold/5 transition-all flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-brand-burgundy/10 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4 text-brand-burgundy" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-brand-plum"><?= e($acct['name']) ?></div>
                                    <div class="data-text text-[10px] text-brand-plum/50 tracking-wider"><?= e($acct['masked_email']) ?></div>
                                    <div class="text-[9px] text-brand-plum/30 uppercase tracking-widest mt-0.5"><?= e($acct['role']) ?></div>
                                </div>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>


            <?php else: ?>
            <!-- ═══ STATE 1: Enter business name ═══ -->

                <div class="mb-8 border-b border-brand-sand pb-6">
                    <h2 class="font-heading font-bold text-2xl text-brand-plum mb-2">Initiate Recovery</h2>
                    <p class="text-xs text-brand-plum/70 font-medium">Enter your registered Enterprise Name to locate your workspace.</p>
                </div>

                <form action="<?= APP_URL ?>/forgot-password" method="POST" class="space-y-5">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                    <input type="hidden" name="search_enterprise" value="1">
                    
                    <div>
                        <label class="block text-xs font-bold text-brand-plum uppercase tracking-widest mb-2">Enterprise Name</label>
                        <input type="text" name="enterprise_name" required class="w-full px-4 py-3 bg-brand-white border border-brand-sand text-brand-plum text-sm focus:border-brand-gold focus:ring-0 outline-none transition-all rounded-none placeholder-brand-plum/30" placeholder="e.g. Chukwudi Fabrics">
                    </div>

                    <button type="submit" class="w-full bg-brand-burgundy text-brand-white text-xs font-bold py-4 uppercase tracking-widest hover:bg-brand-gold transition-colors mt-4">
                        Locate Workspace
                    </button>
                </form>

            <?php endif; ?>
            <div class="mt-6 text-center">
                <a href="<?= APP_URL ?>/login" class="text-[10px] text-brand-plum/50 font-mono uppercase tracking-widest hover:text-brand-burgundy transition-colors">Abort & Return to Login</a>
            </div>
        </div>
    </div>
</body>
</html>