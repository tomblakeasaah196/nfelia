<?php
/**
 * Nfelia — Landing & Authentication View
 * Loaded via AuthController
 */
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nfelia — Track Everything. Carry Nothing.</title>
    
    <!-- Search Engine Optimization -->
    <meta name="description" content="The invisible thread that holds your micro-business together. Drop the weight of manual tracking and instantly upgrade to corporate-grade packaging. Look big, track simply.">
    <meta name="keywords" content="Nfelia, micro-business software, small business invoicing, business operations, receipt generator, inventory management, logistics tracking, Africa business tools">
    <meta name="author" content="JBS Praxis">
    <link rel="canonical" href="<?= APP_URL ?>/">
    <meta name="theme-color" content="#8B004A">

    <!-- Open Graph / WhatsApp / Facebook / LinkedIn -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= APP_URL ?>/">
    <meta property="og:title" content="Nfelia — Track Everything. Carry Nothing.">
    <meta property="og:description" content="The invisible thread that holds your micro-business together. Drop the weight of manual tracking and instantly upgrade to corporate-grade packaging.">
    <meta property="og:image" content="<?= APP_URL ?>/assets/img/nfelia-og-banner.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:site_name" content="Nfelia">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?= APP_URL ?>/">
    <meta name="twitter:title" content="Nfelia — Track Everything. Carry Nothing.">
    <meta name="twitter:description" content="The invisible thread that holds your micro-business together. Drop the weight of manual tracking and instantly upgrade to corporate-grade packaging.">
    <meta name="twitter:image" content="<?= APP_URL ?>/assets/img/nfelia-og-banner.png">
    
    <!-- Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Plus+Jakarta+Sans:ital,wght@0,600;0,700;0,800;1,600&family=Roboto+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    
    <!-- Alpine.js for lightweight interactions -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
    
    <!-- Tailwind CSS -->
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
                        mono: ['"Roboto Mono"', 'monospace'],
                    },
                    borderRadius: {
                        'sm': '4px',
                        DEFAULT: '4px',
                        'md': '4px',
                        'lg': '4px',
                        'xl': '4px',
                        '2xl': '4px',
                        '3xl': '4px',
                        'full': '9999px' // Used only for specific badges/avatars
                    },
                    boxShadow: {
                        'flat': '0 4px 15px rgba(27, 10, 17, 0.03)',
                        'hover': '0 8px 25px rgba(27, 10, 17, 0.08)',
                        'solid': '4px 4px 0px 0px rgba(182, 159, 94, 1)',
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background-color: #F2EFE7;
            color: #1B0A11;
            font-family: 'Montserrat', sans-serif;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }
        h1, h2, h3, .font-heading {
            font-family: 'Plus Jakarta Sans', sans-serif; /* NFELIA AUDIT FIX 2026-06-03 */
        }
        .data-text {
            font-family: 'Roboto Mono', monospace; /* NFELIA AUDIT FIX 2026-06-03 */
        }
        /* Custom Scrollbar for brutalist aesthetic */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #F2EFE7; }
        ::-webkit-scrollbar-thumb { background: #E8E4DA; border: 1px solid #1B0A11; }
        ::-webkit-scrollbar-thumb:hover { background: #B69F5E; }
        
        .diagonal-slice {
            clip-path: polygon(0 0, 100% 0, 100% 90%, 0 100%);
        }
    </style>
</head>
<body x-data="{ 
    mobileMenuOpen: false, 
    authTab: 'login',
    phoneMenuOpen: false,
    termsOpen: false,
    privacyOpen: false,
    feedbackOpen: false
}" class="relative w-full h-full min-h-screen overflow-x-hidden">

    <!-- Navigation -->
    <nav class="fixed w-full z-50 bg-brand-cream/90 backdrop-blur-md border-b border-brand-sand transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <!-- Logo -->
                <div class="flex-shrink-0 flex items-center gap-3 cursor-pointer" @click="window.scrollTo(0,0)">
                    <img src="<?= APP_URL ?>/assets/img/nfelia-logo.png" alt="Nfelia Logo" class="h-10 w-auto object-contain">
                    <span class="font-heading font-extrabold text-xl tracking-[0.2em] text-brand-burgundy uppercase">Nfelia</span>
                </div>

                <!-- Desktop Menu -->
                <div class="hidden md:flex items-center space-x-8">
                    <a href="#manifesto" class="text-xs font-semibold text-brand-plum hover:text-brand-burgundy uppercase tracking-widest transition-colors">Manifesto</a>
                    <a href="#architecture" class="text-xs font-semibold text-brand-plum hover:text-brand-burgundy uppercase tracking-widest transition-colors">Architecture</a>
                    <a href="#pricing" class="text-xs font-semibold text-brand-plum hover:text-brand-burgundy uppercase tracking-widest transition-colors">Pricing</a>
                </div>

                <!-- Desktop CTA -->
                <div class="hidden md:flex items-center space-x-4">
                    <button @click="authTab = 'login'; document.getElementById('auth-section').scrollIntoView()" class="text-xs font-semibold text-brand-plum hover:text-brand-gold uppercase tracking-widest transition-colors">
                        Sign In
                    </button>
                    <button @click="authTab = 'register'; document.getElementById('auth-section').scrollIntoView()" class="bg-brand-burgundy text-brand-white text-xs font-semibold px-6 py-3 uppercase tracking-widest hover:bg-brand-gold transition-all duration-300 shadow-flat hover:shadow-hover">
                        Start 14-Day Free Trial
                    </button>
                </div>

                <!-- Mobile menu button -->
                <div class="md:hidden flex items-center">
                    <button @click="mobileMenuOpen = !mobileMenuOpen" class="text-brand-plum focus:outline-none">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path x-show="!mobileMenuOpen" stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            <path x-show="mobileMenuOpen" stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M6 18L18 6M6 6l12 12" style="display: none;" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div x-show="mobileMenuOpen" x-transition class="md:hidden bg-brand-white border-b border-brand-sand absolute w-full" style="display: none;">
            <div class="px-4 pt-2 pb-6 space-y-1 shadow-flat">
                <a href="#manifesto" @click="mobileMenuOpen = false" class="block px-3 py-4 text-xs font-semibold text-brand-plum uppercase tracking-widest border-b border-brand-sand">Manifesto</a>
                <a href="#architecture" @click="mobileMenuOpen = false" class="block px-3 py-4 text-xs font-semibold text-brand-plum uppercase tracking-widest border-b border-brand-sand">Architecture</a>
                <a href="#pricing" @click="mobileMenuOpen = false" class="block px-3 py-4 text-xs font-semibold text-brand-plum uppercase tracking-widest border-b border-brand-sand">Pricing</a>
                <div class="pt-4 flex flex-col gap-3">
                    <button @click="authTab = 'login'; mobileMenuOpen = false; document.getElementById('auth-section').scrollIntoView()" class="w-full text-center text-xs font-semibold text-brand-plum border border-brand-sand py-4 uppercase tracking-widest">Sign In</button>
                    <button @click="authTab = 'register'; mobileMenuOpen = false; document.getElementById('auth-section').scrollIntoView()" class="w-full text-center bg-brand-burgundy text-brand-white text-xs font-semibold py-4 uppercase tracking-widest">Start Free Trial</button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero / Authentication Section -->
    <section id="auth-section" class="relative pt-32 pb-20 lg:pt-40 lg:pb-28 overflow-hidden min-h-screen flex items-center">
        <!-- Background Monolith Accent -->
        <div class="absolute top-0 right-0 w-1/3 h-full bg-brand-white border-l border-brand-sand -z-10 hidden lg:block"></div>
        <div class="absolute top-40 right-[15%] w-px h-screen bg-brand-gold opacity-30 -z-10 hidden lg:block"></div>
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
            <div class="lg:grid lg:grid-cols-12 lg:gap-16 items-center">
                
                <!-- Left: Value Proposition -->
                <div class="lg:col-span-7 mb-16 lg:mb-0">
                    <div class="inline-block border border-brand-gold px-4 py-1.5 mb-6 bg-brand-white">
                        <span class="data-text text-[10px] font-bold text-brand-gold uppercase tracking-[0.2em]">MVP v1.0 / Pilot Access</span>
                    </div>
                    <h1 class="font-heading font-extrabold text-5xl lg:text-7xl text-brand-burgundy leading-[1.1] mb-6 tracking-tight">
                        Track everything.<br>
                        <span class="text-brand-plum">Carry nothing.</span>
                    </h1>
                    <p class="text-base lg:text-lg text-brand-plum leading-relaxed mb-8 max-w-2xl font-medium">
                        The simple way to look big and track everything. Drop the mental weight of unrecorded sales, messy chats, and scrap-paper tallies. Nfelia is the invisible thread that provides your micro-business with the undeniable packaging of a corporate enterprise.
                    </p>
                    
                    <div class="grid grid-cols-2 gap-6 max-w-lg mb-10">
                        <div class="border-l-2 border-brand-burgundy pl-4">
                            <div class="font-heading font-bold text-xl text-brand-burgundy mb-1">0 Sec</div>
                            <div class="text-xs text-brand-plum uppercase tracking-widest font-semibold">To setup tracking</div>
                        </div>
                        <div class="border-l-2 border-brand-gold pl-4">
                            <div class="font-heading font-bold text-xl text-brand-plum mb-1">₦10k</div>
                            <div class="text-xs text-brand-plum uppercase tracking-widest font-semibold">Flat Monthly Cap</div>
                        </div>
                    </div>

                    <div class="flex items-center gap-4 text-xs font-semibold uppercase tracking-widest text-brand-plum data-text">
                        <svg class="w-4 h-4 text-brand-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Mobile-First
                        <span class="w-1 h-1 bg-brand-burgundy rounded-full mx-2"></span>
                        <svg class="w-4 h-4 text-brand-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Corporate Packaging
                    </div>
                </div>

                <!-- Right: Authentication Hub -->
                <div class="lg:col-span-5 relative">
                    <!-- Brutalist Shadow Element -->
                    <div class="absolute inset-0 bg-brand-gold translate-x-3 translate-y-3 border border-brand-plum z-0 hidden sm:block"></div>
                    
                    <div class="relative bg-brand-white border border-brand-sand p-8 sm:p-10 z-10 shadow-flat">
                        
                        <!-- Auth Tabs -->
                        <div class="flex border-b border-brand-sand mb-8">
                            <button @click="authTab = 'login'" 
                                    :class="authTab === 'login' ? 'border-b-2 border-brand-burgundy text-brand-burgundy' : 'text-brand-plum/50 border-b-2 border-transparent hover:text-brand-plum'"
                                    class="flex-1 pb-4 text-xs font-bold uppercase tracking-widest transition-all">
                                Access Workspace
                            </button>
                            <button @click="authTab = 'register'" 
                                    :class="authTab === 'register' ? 'border-b-2 border-brand-gold text-brand-burgundy' : 'text-brand-plum/50 border-b-2 border-transparent hover:text-brand-plum'"
                                    class="flex-1 pb-4 text-xs font-bold uppercase tracking-widest transition-all">
                                Start Pilot
                            </button>
                        </div>

                        <!-- Flash Messages -->
                        <?php $flash = get_flash(); if ($flash): ?>
                            <div class="mb-6 p-4 text-xs font-bold data-text uppercase tracking-widest border <?= $flash['type'] === 'error' ? 'border-[#ff0000] bg-[#fff0f0] text-[#ff0000]' : 'border-brand-gold bg-brand-cream text-brand-burgundy' ?>">
                                > <?= e($flash['message']) ?>
                            </div>
                        <?php endif; ?>

                        <!-- Login Form -->
                        <div x-show="authTab === 'login'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                            <div class="mb-8">
                                <h2 class="font-heading font-bold text-2xl text-brand-plum mb-2">System Access</h2>
                                <p class="text-xs text-brand-plum/70 font-medium">Enter your credentials to manage your enterprise.</p>
                            </div>

                            <form action="<?= APP_URL ?>/login" method="POST" class="space-y-5">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                                
                                <div>
                                    <label class="block text-xs font-bold text-brand-plum uppercase tracking-widest mb-2">Email Address</label>
                                    <input type="email" name="email" required class="w-full px-4 py-3 bg-brand-white border border-brand-sand text-brand-plum text-sm focus:border-brand-gold focus:ring-0 outline-none transition-all rounded-none placeholder-brand-plum/30" placeholder="director@company.com">
                                </div>

                                <div>
                                    <div class="flex items-center justify-between mb-2">
                                        <label class="block text-xs font-bold text-brand-plum uppercase tracking-widest">Password</label>
                                        <a href="<?= APP_URL ?>/forgot-password" class="text-[10px] text-brand-gold font-bold uppercase tracking-widest hover:text-brand-burgundy transition-colors">Recover</a>
                                    </div>
                                    <input type="password" name="password" required class="w-full px-4 py-3 bg-brand-white border border-brand-sand text-brand-plum text-sm focus:border-brand-gold focus:ring-0 outline-none transition-all rounded-none placeholder-brand-plum/30" placeholder="••••••••">
                                </div>

                                <label class="flex items-center gap-3 cursor-pointer mb-4 mt-2">
                                    <input type="checkbox" name="remember_me" value="1" class="w-4 h-4 border border-brand-sand accent-brand-burgundy">
                                    <span class="text-xs font-semibold text-brand-plum uppercase tracking-widest">Keep me signed in</span>
                                </label>

                                <button type="submit" class="w-full bg-brand-burgundy text-brand-white text-xs font-bold py-4 uppercase tracking-widest hover:bg-brand-gold transition-colors mt-4">
                                    Authenticate
                                </button>
                            </form>

                            <p class="mt-5 text-center text-[11px] text-brand-plum/60">
                                Can't remember your password?
                                <a href="<?= APP_URL ?>/login-link" class="font-bold text-brand-burgundy hover:text-brand-gold transition-colors">Email me a sign-in link</a>
                            </p>
                        </div>

                        <!-- Registration Form -->
                        <div x-show="authTab === 'register'" style="display: none;" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                            <div class="mb-8">
                                <h2 class="font-heading font-bold text-2xl text-brand-plum mb-2">Deploy Workspace</h2>
                                <p class="text-xs text-brand-plum/70 font-medium">Join the MVP pilot. No credit card required.</p>
                            </div>

                            <form action="<?= APP_URL ?>/register" method="POST" class="space-y-5">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                                
                                <div>
                                    <label class="block text-xs font-bold text-brand-plum uppercase tracking-widest mb-2">Director Full Name</label>
                                    <input type="text" name="full_name" required class="w-full px-4 py-3 bg-brand-white border border-brand-sand text-brand-plum text-sm focus:border-brand-gold focus:ring-0 outline-none transition-all rounded-none placeholder-brand-plum/30" placeholder="e.g. Chioma Chukwudi">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-brand-plum uppercase tracking-widest mb-2">Enterprise Name</label>
                                    <input type="text" name="business_name" required class="w-full px-4 py-3 bg-brand-white border border-brand-sand text-brand-plum text-sm focus:border-brand-gold focus:ring-0 outline-none transition-all rounded-none placeholder-brand-plum/30" placeholder="e.g. Chukwudi Fabrics">
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-bold text-brand-plum uppercase tracking-widest mb-2">Email Address</label>
                                    <input type="email" name="email" required class="w-full px-4 py-3 bg-brand-white border border-brand-sand text-brand-plum text-sm focus:border-brand-gold focus:ring-0 outline-none transition-all rounded-none placeholder-brand-plum/30" placeholder="you@company.com">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-brand-plum uppercase tracking-widest mb-2">Secure Password</label>
                                    <input type="password" name="password" required class="w-full px-4 py-3 bg-brand-white border border-brand-sand text-brand-plum text-sm focus:border-brand-gold focus:ring-0 outline-none transition-all rounded-none placeholder-brand-plum/30" placeholder="••••••••">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-brand-plum uppercase tracking-widest mb-2">Confirm Password</label>
                                    <input type="password" name="password_confirm" required class="w-full px-4 py-3 bg-brand-white border border-brand-sand text-brand-plum text-sm focus:border-brand-gold focus:ring-0 outline-none transition-all rounded-none placeholder-brand-plum/30" placeholder="••••••••">
                                </div>

                                <!-- Referral Code (collapsible) -->
                                <div x-data="{ showRef: false }">
                                    <button type="button" @click="showRef = !showRef"
                                            class="text-[10px] font-bold text-brand-gold/80 hover:text-brand-gold uppercase tracking-widest transition-colors">
                                        + Have a referral code?
                                    </button>
                                    <div x-show="showRef" x-transition class="mt-2">
                                        <input type="text" name="ref_code" id="ref_code_input" maxlength="20"
                                               class="w-full px-4 py-3 bg-brand-white border border-brand-sand text-brand-plum text-sm focus:border-brand-gold focus:ring-0 outline-none transition-all rounded-none font-mono uppercase placeholder-brand-plum/30"
                                               placeholder="NF-XXX-YYYY">
                                    </div>
                                </div>

                                <button type="submit" class="w-full bg-brand-plum text-brand-white text-xs font-bold py-4 uppercase tracking-widest hover:bg-brand-burgundy transition-colors mt-4">
                                    Initialize Setup Wizard
                                </button>
                                <p class="text-center text-[10px] text-brand-plum/50 font-mono mt-4 uppercase">By initializing, you accept the structural terms.</p>

                                <script>
                                // Auto-populate referral code from URL ?ref=CODE and expand the field
                                (function() {
                                    const urlParams = new URLSearchParams(window.location.search);
                                    const ref = urlParams.get('ref');
                                    if (ref) {
                                        const inp = document.getElementById('ref_code_input');
                                        if (inp) {
                                            inp.value = ref.toUpperCase();
                                            // Trigger Alpine to show the field
                                            inp.closest('[x-data]').__x.$data.showRef = true;
                                        }
                                    }
                                })();
                                </script>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Manifesto / Core Problem Section -->
    <section id="manifesto" class="py-24 bg-brand-white border-y border-brand-sand relative">
        <div class="absolute left-1/2 top-0 -translate-x-1/2 w-px h-full bg-brand-sand hidden md:block"></div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16 relative bg-brand-white py-4 inline-block mx-auto z-10 w-full">
                <h3 class="data-text text-xs font-bold text-brand-gold uppercase tracking-[0.2em] mb-4">1. Brand Manifesto</h3>
                <h2 class="font-heading font-extrabold text-3xl md:text-4xl text-brand-burgundy uppercase tracking-tight">Credibility without complexity.</h2>
            </div>
            
            <div class="grid md:grid-cols-2 gap-16 lg:gap-24 items-center">
                <div class="space-y-6">
                    <p class="font-heading font-semibold text-xl text-brand-plum leading-snug border-l-4 border-brand-gold pl-6 py-2">
                        Right now, your business is heavy. You are carrying the mental weight of unrecorded sales, messy WhatsApp chats, and stock you cannot find.
                    </p>
                    <p class="text-sm text-brand-plum/80 leading-relaxed font-medium">
                        You want the credibility of a large corporate entity—clean invoices, professional receipts, structured records—but you do not have the time or the money for heavy, bloated, Western-designed software that requires a manual to understand. Existing solutions are built for massive corporations. You just need to issue a deed of acquisition from a chat in three taps.
                    </p>
                </div>
                
                <div class="bg-brand-cream border border-brand-sand p-8 relative">
                    <div class="absolute -top-3 -right-3 bg-brand-burgundy text-brand-white data-text text-[10px] font-bold px-3 py-1 uppercase tracking-widest">The Solution</div>
                    <ul class="space-y-6">
                        <li class="flex items-start gap-4">
                            <div class="w-8 h-8 rounded-none border border-brand-gold flex items-center justify-center shrink-0 bg-brand-white">
                                <span class="data-text text-brand-burgundy font-bold text-sm">01</span>
                            </div>
                            <div>
                                <h4 class="font-heading font-bold text-brand-plum text-sm uppercase tracking-wider mb-1">Radical Simplicity</h4>
                                <p class="text-xs text-brand-plum/70 font-medium leading-relaxed">If a feature requires a manual, it doesn't belong in Nfelia. The experience is a sudden unburdening of cognitive load.</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-4">
                            <div class="w-8 h-8 rounded-none border border-brand-gold flex items-center justify-center shrink-0 bg-brand-white">
                                <span class="data-text text-brand-burgundy font-bold text-sm">02</span>
                            </div>
                            <div>
                                <h4 class="font-heading font-bold text-brand-plum text-sm uppercase tracking-wider mb-1">Uncompromising Credibility</h4>
                                <p class="text-xs text-brand-plum/70 font-medium leading-relaxed">Our outputs—invoices, receipts, portals—project absolute professionalism. We provide the ultimate "packaging."</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-4">
                            <div class="w-8 h-8 rounded-none border border-brand-gold flex items-center justify-center shrink-0 bg-brand-white">
                                <span class="data-text text-brand-burgundy font-bold text-sm">03</span>
                            </div>
                            <div>
                                <h4 class="font-heading font-bold text-brand-plum text-sm uppercase tracking-wider mb-1">Mobile-First Reality</h4>
                                <p class="text-xs text-brand-plum/70 font-medium leading-relaxed">The modern vendor does not need a desktop application. Our architecture begins and ends with smartphone excellence.</p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Product Architecture / Pipeline Demonstration -->
    <section id="architecture" class="py-24 bg-brand-plum text-brand-white overflow-hidden relative">
        <div class="absolute inset-0 opacity-5" style="background-image: radial-gradient(#F2EFE7 1px, transparent 1px); background-size: 24px 24px;"></div>
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10" x-data="{ activeTab: 'quote' }">
            <div class="mb-16 md:flex justify-between items-end border-b border-brand-white/10 pb-8">
                <div class="max-w-2xl">
                    <h3 class="data-text text-xs font-bold text-brand-gold uppercase tracking-[0.2em] mb-4">2. Product Architecture</h3>
                    <h2 class="font-heading font-extrabold text-3xl md:text-4xl text-brand-cream uppercase tracking-tight">The Binding Thread.</h2>
                    <p class="text-sm text-brand-cream/70 mt-4 leading-relaxed font-medium">
                        Nfelia consolidates the core operational functions of a small business into one highly structured workspace. From quotation to bank deposit, every module is connected automatically.
                    </p>
                </div>
                
                <!-- Interactive Tabs -->
                <div class="mt-8 md:mt-0 flex flex-wrap gap-2">
                    <button @click="activeTab = 'quote'" :class="activeTab === 'quote' ? 'bg-brand-burgundy text-brand-white border-brand-burgundy' : 'border-brand-white/20 text-brand-white/50 hover:text-brand-white'" class="border px-4 py-2 text-[10px] font-bold uppercase tracking-widest transition-all">Quotations</button>
                    <button @click="activeTab = 'invoice'" :class="activeTab === 'invoice' ? 'bg-brand-burgundy text-brand-white border-brand-burgundy' : 'border-brand-white/20 text-brand-white/50 hover:text-brand-white'" class="border px-4 py-2 text-[10px] font-bold uppercase tracking-widest transition-all">Invoicing</button>
                    <button @click="activeTab = 'delivery'" :class="activeTab === 'delivery' ? 'bg-brand-burgundy text-brand-white border-brand-burgundy' : 'border-brand-white/20 text-brand-white/50 hover:text-brand-white'" class="border px-4 py-2 text-[10px] font-bold uppercase tracking-widest transition-all">Delivery 3PL</button>
                    <button @click="activeTab = 'inventory'" :class="activeTab === 'inventory' ? 'bg-brand-burgundy text-brand-white border-brand-burgundy' : 'border-brand-white/20 text-brand-white/50 hover:text-brand-white'" class="border px-4 py-2 text-[10px] font-bold uppercase tracking-widest transition-all">Inventory</button>
                </div>
            </div>

            <!-- Tab Content Area -->
            <div class="grid lg:grid-cols-12 gap-12 items-start">
                
                <!-- Description Panel -->
                <div class="lg:col-span-5 space-y-6 min-h-[300px]">
                    <!-- Quote Content -->
                    <div x-show="activeTab === 'quote'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0">
                        <div class="data-text text-brand-gold text-xs font-bold mb-2">MODULE: REQUEST FOR ENGAGEMENT</div>
                        <h4 class="font-heading font-bold text-2xl text-brand-white mb-4">Professional Quotations in Seconds.</h4>
                        <p class="text-sm text-brand-cream/70 leading-relaxed mb-6">Stop sending prices in messy WhatsApp texts. Select items from your automated inventory, specify a validity period, and generate a secure URL token. Your client receives a beautifully branded, corporate-grade PDF viewable on any device.</p>
                        <ul class="space-y-3 data-text text-[11px] text-brand-cream/90 uppercase tracking-wider">
                            <li class="flex items-center gap-3"><span class="text-brand-gold">✓</span> Auto-calculates totals & tax</li>
                            <li class="flex items-center gap-3"><span class="text-brand-gold">✓</span> Generates shareable WhatsApp link</li>
                            <li class="flex items-center gap-3"><span class="text-brand-gold">✓</span> One-tap conversion to Invoice</li>
                        </ul>
                    </div>

                    <!-- Invoice Content -->
                    <div x-show="activeTab === 'invoice'" style="display: none;" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0">
                        <div class="data-text text-brand-gold text-xs font-bold mb-2">MODULE: COMMERCIAL INVOICE</div>
                        <h4 class="font-heading font-bold text-2xl text-brand-white mb-4">The Heart of Commercial Credibility.</h4>
                        <p class="text-sm text-brand-cream/70 leading-relaxed mb-6">Convert a quote or create a direct invoice instantly. The document applies your dynamic CSS storefront branding—logo, exact colors, and corporate typography. The system automatically tracks partial payments and flags overdue accounts.</p>
                        <ul class="space-y-3 data-text text-[11px] text-brand-cream/90 uppercase tracking-wider">
                            <li class="flex items-center gap-3"><span class="text-brand-gold">✓</span> Auto-tracks in Debt Manager</li>
                            <li class="flex items-center gap-3"><span class="text-brand-gold">✓</span> Dynamic watermarks (PAID / UNPAID)</li>
                            <li class="flex items-center gap-3"><span class="text-brand-gold">✓</span> Auto-deducts from active inventory</li>
                        </ul>
                    </div>

                    <!-- Delivery Content -->
                    <div x-show="activeTab === 'delivery'" style="display: none;" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0">
                        <div class="data-text text-brand-gold text-xs font-bold mb-2">MODULE: DISPATCH CONFIRMATION</div>
                        <h4 class="font-heading font-bold text-2xl text-brand-white mb-4">Logistics Tracking & E-Signatures.</h4>
                        <p class="text-sm text-brand-cream/70 leading-relaxed mb-6">Built for the African 3PL reality. Generate a dispatch note linked to any invoice. Assign rider details (Uber/Bolt/Kwik). The recipient opens the secure URL link upon arrival and signs digitally on their screen, locking the transaction.</p>
                        <ul class="space-y-3 data-text text-[11px] text-brand-cream/90 uppercase tracking-wider">
                            <li class="flex items-center gap-3"><span class="text-brand-gold">✓</span> URL-tokenized E-Signature pad</li>
                            <li class="flex items-center gap-3"><span class="text-brand-gold">✓</span> Time-stamped proof of delivery</li>
                            <li class="flex items-center gap-3"><span class="text-brand-gold">✓</span> Rider & booking reference logs</li>
                        </ul>
                    </div>

                    <!-- Inventory Content -->
                    <div x-show="activeTab === 'inventory'" style="display: none;" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0">
                        <div class="data-text text-brand-gold text-xs font-bold mb-2">MODULE: STRUCTURAL INVENTORY</div>
                        <h4 class="font-heading font-bold text-2xl text-brand-white mb-4">Silent, Automated Stock Control.</h4>
                        <p class="text-sm text-brand-cream/70 leading-relaxed mb-6">Your catalog is deeply integrated. When an invoice is issued, stock is instantly reduced. When stock falls below your designated threshold, the mobile dashboard alerts you. Calculate total business valuation instantly.</p>
                        <ul class="space-y-3 data-text text-[11px] text-brand-cream/90 uppercase tracking-wider">
                            <li class="flex items-center gap-3"><span class="text-brand-gold">✓</span> Cost price vs Selling price (P&L sync)</li>
                            <li class="flex items-center gap-3"><span class="text-brand-gold">✓</span> Low-stock dashboard alerts</li>
                            <li class="flex items-center gap-3"><span class="text-brand-gold">✓</span> Historical movement audit logs</li>
                        </ul>
                    </div>
                </div>

                <!-- Visual Representation Panel (Mocked UI) -->
                <div class="lg:col-span-7 bg-brand-cream p-6 border border-brand-sand/20 shadow-2xl relative">
                    <div class="absolute top-0 right-0 bg-brand-gold text-brand-plum data-text text-[9px] font-bold px-2 py-1 uppercase border-l border-b border-brand-sand/20">Live Rendering Preview</div>
                    
                    <!-- Simulated Invoice Document Output -->
                    <div class="bg-brand-white w-full aspect-[1/1.4] p-8 border border-brand-sand flex flex-col relative overflow-hidden transform transition-all shadow-sm">
                        <!-- Header Line -->
                        <div class="absolute top-0 left-0 w-full h-2 bg-[#2c3e50]" x-bind:class="activeTab === 'quote' ? 'bg-[#34495e]' : (activeTab === 'delivery' ? 'bg-[#16a085]' : (activeTab === 'inventory' ? 'bg-[#8B004A]' : 'bg-[#2c3e50]'))"></div>
                        
                        <!-- Doc Header -->
                        <div class="flex justify-between items-start mb-8 mt-2 border-b pb-6 border-brand-sand">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-brand-cream border border-brand-sand flex items-center justify-center">
                                    <span class="font-heading font-bold text-[#2c3e50] text-xs">LOGO</span>
                                </div>
                                <div>
                                    <div class="font-heading font-bold text-sm text-[#2c3e50]">CHUKWUDI FABRICS</div>
                                    <div class="data-text text-[8px] text-brand-plum/50 mt-1">RC: 1049281 | LAGOS, NG</div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="data-text font-bold text-[10px] text-brand-plum uppercase" x-text="activeTab === 'quote' ? 'CHF-QUO-001' : (activeTab === 'delivery' ? 'CHF-DEL-001' : (activeTab === 'inventory' ? 'INVENTORY REP' : 'CHF-INV-001'))">CHF-INV-001</div>
                                <div class="font-heading font-bold text-sm mt-1 text-[#2c3e50] uppercase" x-text="activeTab === 'quote' ? 'Request for Engagement' : (activeTab === 'delivery' ? 'Dispatch Confirmation' : (activeTab === 'inventory' ? 'Stock Valuation' : 'Commercial Invoice'))">Commercial Invoice</div>
                            </div>
                        </div>

                        <!-- Data Body -->
                        <div class="flex-grow">
                            <!-- Table Headers -->
                            <div class="flex text-[8px] font-bold uppercase tracking-widest text-brand-plum/60 border-b border-brand-sand pb-2 mb-3">
                                <div class="w-1/2">Description</div>
                                <div class="w-1/6 text-right">Qty</div>
                                <div class="w-1/6 text-right">Unit Rate</div>
                                <div class="w-1/6 text-right">Total</div>
                            </div>
                            <!-- Rows -->
                            <div class="space-y-3">
                                <div class="flex text-[10px] text-brand-plum border-b border-brand-sand/30 pb-3">
                                    <div class="w-1/2 font-semibold">Premium Ankara Silk Blend<br><span class="text-[8px] text-brand-plum/50 font-normal">Blue/Gold geometric pattern</span></div>
                                    <div class="w-1/6 text-right data-text">50.0</div>
                                    <div class="w-1/6 text-right data-text">4,500</div>
                                    <div class="w-1/6 text-right data-text font-bold">225,000</div>
                                </div>
                                <div class="flex text-[10px] text-brand-plum border-b border-brand-sand/30 pb-3">
                                    <div class="w-1/2 font-semibold">Logistics Handling (GIG)<br><span class="text-[8px] text-brand-plum/50 font-normal">Express delivery to Abuja</span></div>
                                    <div class="w-1/6 text-right data-text">1.0</div>
                                    <div class="w-1/6 text-right data-text">12,500</div>
                                    <div class="w-1/6 text-right data-text font-bold">12,500</div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer Totals -->
                        <div class="border-t-2 border-brand-plum pt-4 mt-8 flex justify-between items-end">
                            <div class="w-1/2">
                                <div class="text-[8px] font-bold uppercase tracking-widest text-brand-plum/60 mb-1" x-show="activeTab !== 'delivery'">Payment Details</div>
                                <div class="data-text text-[9px] text-brand-plum" x-show="activeTab !== 'delivery'">
                                    GTBank / 0123456789<br>
                                    Chukwudi Enterprises
                                </div>
                            </div>
                            <div class="w-1/2 text-right space-y-1">
                                <div class="flex justify-end text-[10px]">
                                    <span class="w-24 text-brand-plum/60 uppercase">Subtotal</span>
                                    <span class="w-24 data-text font-semibold">237,500.00</span>
                                </div>
                                <div class="flex justify-end text-[10px]">
                                    <span class="w-24 text-brand-plum/60 uppercase">Tax (7.5%)</span>
                                    <span class="w-24 data-text font-semibold">17,812.50</span>
                                </div>
                                <div class="flex justify-end text-sm mt-2 pt-2 border-t border-brand-sand">
                                    <span class="w-24 font-heading font-bold text-[#2c3e50] uppercase">Total NGN</span>
                                    <span class="w-24 data-text font-bold text-[#2c3e50]">255,312.50</span>
                                </div>
                            </div>
                        </div>

                        <!-- Watermark / Stamp -->
                        <div class="absolute inset-0 flex items-center justify-center pointer-events-none opacity-10" x-show="activeTab === 'invoice'">
                            <div class="transform -rotate-45 font-heading font-extrabold text-6xl text-brand-burgundy border-8 border-brand-burgundy px-8 py-4 tracking-widest">UNPAID</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Architecture -->
    <section id="pricing" class="py-24 bg-brand-cream border-t border-brand-sand">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h3 class="data-text text-xs font-bold text-brand-gold uppercase tracking-[0.2em] mb-4">3. Commercials</h3>
                <h2 class="font-heading font-extrabold text-3xl md:text-4xl text-brand-plum uppercase tracking-tight">Accessible Architecture.</h2>
                <p class="text-sm text-brand-plum/70 mt-4 max-w-xl mx-auto font-medium">Powerful business tools should not be gated behind luxury pricing. We provide immense value at an entry point the smallest vendor can easily afford.</p>
            </div>

            <div class="max-w-md mx-auto bg-brand-white border border-brand-sand shadow-flat relative">
                <!-- Highlight Ribbon -->
                <div class="absolute top-0 left-0 w-full h-1 bg-brand-burgundy"></div>
                <div class="absolute -right-3 top-6 bg-brand-gold text-brand-white data-text text-[10px] font-bold px-4 py-2 uppercase tracking-widest shadow-sm">MVP PILOT RATE</div>
                
                <div class="p-8 md:p-10">
                    <h4 class="font-heading font-bold text-2xl text-brand-plum mb-2">Standard Protocol</h4>
                    <p class="text-xs text-brand-plum/60 font-medium mb-6">Everything you need to structure operations immediately.</p>
                    
                    <div class="mb-8 border-b border-brand-sand pb-8">
                        <div class="flex items-baseline">
                            <span class="font-heading font-extrabold text-5xl text-brand-burgundy tracking-tight">₦10k</span>
                            <span class="data-text text-brand-plum/50 text-sm ml-2">/ month</span>
                        </div>
                    </div>

                    <ul class="space-y-4 mb-10">
                        <li class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-brand-gold shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span class="text-xs font-semibold text-brand-plum uppercase tracking-wider mt-0.5">All 13 MVP Modules Included</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-brand-gold shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span class="text-xs font-semibold text-brand-plum uppercase tracking-wider mt-0.5">Unlimited Document Generation</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-brand-gold shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span class="text-xs font-semibold text-brand-plum uppercase tracking-wider mt-0.5">Custom Branded CSS Engine</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-brand-gold shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span class="text-xs font-semibold text-brand-plum uppercase tracking-wider mt-0.5">Inventory Tracking (Up to 500 SKUs)</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-brand-gold shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span class="text-xs font-semibold text-brand-plum uppercase tracking-wider mt-0.5">1 Master User Seat</span>
                        </li>
                    </ul>

                    <button @click="authTab = 'register'; document.getElementById('auth-section').scrollIntoView()" class="w-full bg-brand-burgundy text-brand-white text-xs font-bold py-4 uppercase tracking-widest hover:bg-brand-gold transition-colors block text-center">
                        Initiate 14-Day Free Trial
                    </button>
                    <div class="text-center mt-4 text-[10px] data-text text-brand-plum/50 uppercase">No credit card required for setup.</div>
                </div>
                <div class="bg-brand-sand/30 border-t border-brand-sand px-8 py-4">
                    <p class="text-[10px] text-brand-plum/70 font-semibold uppercase tracking-wider text-center">Need staff access? Additional seats ₦5,000/mo.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Detailed Module Feature Grid (The "Everything" Section) -->
    <section class="py-24 bg-brand-white border-t border-brand-sand">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16 border-b border-brand-sand pb-16">
                <h3 class="data-text text-xs font-bold text-brand-gold uppercase tracking-[0.2em] mb-4">Module Index</h3>
                <h2 class="font-heading font-extrabold text-2xl md:text-3xl text-brand-plum uppercase tracking-tight max-w-2xl mx-auto">Enterprise infrastructure, miniaturized for mobile deployment.</h2>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-x-12 gap-y-16">
                <!-- Feature 1 -->
                <div>
                    <div class="w-10 h-10 border border-brand-burgundy flex items-center justify-center mb-4 bg-brand-cream">
                        <span class="data-text font-bold text-brand-burgundy text-sm">CRM</span>
                    </div>
                    <h4 class="font-heading font-bold text-sm text-brand-plum uppercase tracking-wider mb-2">Customer CRM (Lite)</h4>
                    <p class="text-xs text-brand-plum/70 font-medium leading-relaxed">A lightweight contact book. Track every client's transaction history, outstanding balance, and generate documents for them in one tap. Direct WhatsApp integration included.</p>
                </div>
                <!-- Feature 2 -->
                <div>
                    <div class="w-10 h-10 border border-brand-burgundy flex items-center justify-center mb-4 bg-brand-cream">
                        <span class="data-text font-bold text-brand-burgundy text-sm">REC</span>
                    </div>
                    <h4 class="font-heading font-bold text-sm text-brand-plum uppercase tracking-wider mb-2">Payment Acknowledgement</h4>
                    <p class="text-xs text-brand-plum/70 font-medium leading-relaxed">Issue instant, professional receipts. Works as a standalone POS-style receipt or generated automatically from a settled invoice. Share via URL token.</p>
                </div>
                <!-- Feature 3 -->
                <div>
                    <div class="w-10 h-10 border border-brand-burgundy flex items-center justify-center mb-4 bg-brand-cream">
                        <span class="data-text font-bold text-brand-burgundy text-sm">DBT</span>
                    </div>
                    <h4 class="font-heading font-bold text-sm text-brand-plum uppercase tracking-wider mb-2">Debt Tracker</h4>
                    <p class="text-xs text-brand-plum/70 font-medium leading-relaxed">Manage Receivables and Payables. Auto-populates from overdue invoices. Includes 1-click polite WhatsApp payment reminders pre-filled with dynamic invoice links.</p>
                </div>
                <!-- Feature 4 -->
                <div>
                    <div class="w-10 h-10 border border-brand-burgundy flex items-center justify-center mb-4 bg-brand-cream">
                        <span class="data-text font-bold text-brand-burgundy text-sm">EXP</span>
                    </div>
                    <h4 class="font-heading font-bold text-sm text-brand-plum uppercase tracking-wider mb-2">Expense Logging</h4>
                    <p class="text-xs text-brand-plum/70 font-medium leading-relaxed">A simple money-out log to give you the real picture of your financials. Categorize spending (Stock, Transport, Utilities) and upload receipt photos for audit trails.</p>
                </div>
                <!-- Feature 5 -->
                <div>
                    <div class="w-10 h-10 border border-brand-burgundy flex items-center justify-center mb-4 bg-brand-cream">
                        <span class="data-text font-bold text-brand-burgundy text-sm">P&L</span>
                    </div>
                    <h4 class="font-heading font-bold text-sm text-brand-plum uppercase tracking-wider mb-2">Profit & Loss Reporting</h4>
                    <p class="text-xs text-brand-plum/70 font-medium leading-relaxed">Clean, simple monthly financial summaries without the accounting jargon. See Total Revenue, Total Expenses, and Net Position instantly. Export as PDF.</p>
                </div>
                <!-- Feature 6 -->
                <div>
                    <div class="w-10 h-10 border border-brand-burgundy flex items-center justify-center mb-4 bg-brand-cream">
                        <span class="data-text font-bold text-brand-burgundy text-sm">WEB</span>
                    </div>
                    <h4 class="font-heading font-bold text-sm text-brand-plum uppercase tracking-wider mb-2">Public Business Profile</h4>
                    <p class="text-xs text-brand-plum/70 font-medium leading-relaxed">Every account receives a shareable, branded webpage (e.g., nfelia.lpc.cm/biz/your-name). Contains contact details and a lead-generation "Request Quote" form.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-brand-plum pt-20 pb-10 border-t-4 border-brand-gold">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-12 mb-16">
                <!-- Branding -->
                <div class="md:col-span-5">
                    <div class="flex items-center gap-3 mb-6">
                        <img src="<?= APP_URL ?>/assets/img/nfelia-logo.png" alt="Nfelia Logo" class="h-8 w-auto object-contain">
                        <span class="font-heading font-extrabold text-lg tracking-[0.2em] text-brand-white uppercase">Nfelia</span>
                    </div>
                    <p class="text-brand-cream/60 text-xs font-medium leading-relaxed max-w-sm mb-8">
                        The simple way to look big and track everything. Replace chaos with clean lines, confusion with deep data tracking, and overwhelm with structured relief.
                    </p>
                    <div class="data-text text-[10px] text-brand-gold uppercase tracking-widest">
                        Business Management Platform
                    </div>
                </div>

                <!-- Links -->
                <div class="md:col-span-3">
                    <h5 class="font-heading font-bold text-sm text-brand-white uppercase tracking-wider mb-6">Platform</h5>
                    <ul class="space-y-4 text-xs font-medium text-brand-cream/60">
                        <li><a href="#" class="hover:text-brand-gold transition-colors uppercase tracking-widest">Authentication</a></li>
                        <li><a href="#architecture" class="hover:text-brand-gold transition-colors uppercase tracking-widest">Architecture</a></li>
                        <li><a href="#pricing" class="hover:text-brand-gold transition-colors uppercase tracking-widest">Pricing</a></li>
                        <li><a href="#" class="hover:text-brand-gold transition-colors uppercase tracking-widest">System Status</a></li>
                    </ul>
                </div>

                <!-- Legal & Contact -->
                <div class="md:col-span-4">
                    <h5 class="font-heading font-bold text-sm text-brand-white uppercase tracking-wider mb-6">Administration</h5>
                    <ul class="space-y-4 text-xs font-medium text-brand-cream/60 relative">
                        <!-- Email Patch -->
                        <li class="flex items-start gap-2">
                            <span class="text-brand-gold data-text shrink-0">@</span>
                            <a href="mailto:support@nfelia.lpc.cm" class="uppercase tracking-widest hover:text-brand-gold transition-colors">support@nfelia.lpc.cm</a>
                        </li>
                        <!-- Phone Dropdown Patch -->
                        <li class="flex items-start gap-2 relative">
                            <span class="text-brand-gold data-text shrink-0">✆</span>
                            <button @click="phoneMenuOpen = !phoneMenuOpen" @click.away="phoneMenuOpen = false" class="uppercase tracking-widest hover:text-brand-gold transition-colors text-left flex items-center gap-2">
                                +234 816 978 9106
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div x-show="phoneMenuOpen" x-transition.opacity.duration.200ms style="display: none;" class="absolute left-6 top-6 mt-1 w-32 bg-brand-white border border-brand-sand shadow-flat z-20">
                                <a href="https://wa.me/2348169789106" target="_blank" class="block px-4 py-3 text-[10px] font-bold text-brand-plum hover:bg-brand-cream hover:text-brand-burgundy uppercase tracking-widest border-b border-brand-sand transition-colors">WhatsApp</a>
                                <a href="tel:+2348169789106" class="block px-4 py-3 text-[10px] font-bold text-brand-plum hover:bg-brand-cream hover:text-brand-burgundy uppercase tracking-widest transition-colors">Phone Call</a>
                            </div>
                        </li>
                        <!-- Terms Patch -->
                        <li class="flex items-start gap-2">
                            <span class="text-brand-gold data-text shrink-0">#</span>
                            <button @click="termsOpen = true" class="uppercase tracking-widest hover:text-brand-gold transition-colors">Terms of Service</button>
                        </li>
                        <!-- Privacy Patch -->
                        <li class="flex items-start gap-2">
                            <span class="text-brand-gold data-text shrink-0">#</span>
                            <button @click="privacyOpen = true" class="uppercase tracking-widest hover:text-brand-gold transition-colors">Privacy Protocol</button>
                        </li>
                        <!-- Feedback Patch -->
                        <li class="flex items-start gap-2 mt-2 pt-2 border-t border-brand-cream/10">
                            <span class="text-brand-gold data-text shrink-0">✦</span>
                            <button @click="feedbackOpen = true" class="uppercase tracking-widest text-brand-gold hover:text-brand-white transition-colors font-bold">Submit Feedback</button>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Bottom Bar -->
            <div class="border-t border-brand-cream/10 pt-8 flex flex-col md:flex-row justify-between items-center gap-4">
                <div class="text-[10px] data-text text-brand-cream/40 uppercase tracking-widest">
                    &copy; 2026 Nfelia. All rights reserved.
                </div>
                <div class="text-[10px] data-text text-brand-cream/40 uppercase tracking-widest">
                    <a href="https://jbspraxis.com" target="_blank" class="hover:text-brand-gold transition-colors">Powered by JBS Praxis</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- MODALS OVERLAYS -->
    
    <!-- Terms of Service Modal -->
    <div x-show="termsOpen" style="display: none;" class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <!-- Backdrop -->
        <div x-show="termsOpen" x-transition.opacity @click="termsOpen = false" class="fixed inset-0 bg-brand-plum/80 backdrop-blur-sm transition-opacity"></div>
        
        <!-- Panel -->
        <div x-show="termsOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-4" class="relative bg-brand-white border border-brand-sand w-full max-w-2xl max-h-[85vh] overflow-y-auto shadow-solid transform transition-all">
            
            <div class="sticky top-0 bg-brand-white border-b border-brand-sand px-6 py-4 flex justify-between items-center z-10">
                <h3 class="font-heading font-extrabold text-lg text-brand-burgundy uppercase tracking-widest">Terms of Service</h3>
                <button @click="termsOpen = false" class="text-brand-plum hover:text-brand-burgundy transition-colors p-2">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <div class="p-6 space-y-6 text-sm text-brand-plum font-medium leading-relaxed">
                <p class="data-text text-[10px] font-bold text-brand-gold uppercase tracking-[0.2em]">Effective Date: May 2026 | Version 1.0</p>
                
                <div>
                    <h4 class="font-heading font-bold text-brand-plum uppercase tracking-wider mb-2">1. The Structural Agreement</h4>
                    <p>By initializing a workspace on Nfelia, you agree to these structural terms. Nfelia provides the digital architecture, document generation pipeline, and tracking modules for your micro-business. We provide the packaging; you provide the operations.</p>
                </div>
                
                <div>
                    <h4 class="font-heading font-bold text-brand-plum uppercase tracking-wider mb-2">2. Subscription Protocol</h4>
                    <p>The Nfelia MVP standard protocol is capped at ₦10,000 per month. Billing is recurring. In the event of payment failure, your workspace will enter a 3-day grace period, after which access to generation modules will be restricted until arrears are cleared. Your historical data will remain intact.</p>
                </div>
                
                <div>
                    <h4 class="font-heading font-bold text-brand-plum uppercase tracking-wider mb-2">3. Data Ownership</h4>
                    <p>You retain absolute ownership of all inventory, client CRM, and financial data inputted into your workspace. Nfelia acts solely as the processor and custodian of this data to generate your commercial documents.</p>
                </div>
                
                <div>
                    <h4 class="font-heading font-bold text-brand-plum uppercase tracking-wider mb-2">4. Liability Constraint</h4>
                    <p>While Nfelia is built on robust, shared hosting infrastructure, we do not assume liability for business losses, missed deliveries, or incorrect financial inputs resulting from user error or temporary server unavailability. You are responsible for verifying all document amounts before generating URL tokens.</p>
                </div>
            </div>
            
            <div class="sticky bottom-0 bg-brand-cream border-t border-brand-sand px-6 py-4">
                <button @click="termsOpen = false" class="w-full bg-brand-plum text-brand-white text-xs font-bold py-3 uppercase tracking-widest hover:bg-brand-burgundy transition-colors">Acknowledge</button>
            </div>
        </div>
    </div>

    <!-- Privacy Protocol Modal -->
    <div x-show="privacyOpen" style="display: none;" class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <!-- Backdrop -->
        <div x-show="privacyOpen" x-transition.opacity @click="privacyOpen = false" class="fixed inset-0 bg-brand-plum/80 backdrop-blur-sm transition-opacity"></div>
        
        <!-- Panel -->
        <div x-show="privacyOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-4" class="relative bg-brand-white border border-brand-sand w-full max-w-2xl max-h-[85vh] overflow-y-auto shadow-solid transform transition-all">
            
            <div class="sticky top-0 bg-brand-white border-b border-brand-sand px-6 py-4 flex justify-between items-center z-10">
                <h3 class="font-heading font-extrabold text-lg text-brand-burgundy uppercase tracking-widest">Privacy Protocol</h3>
                <button @click="privacyOpen = false" class="text-brand-plum hover:text-brand-burgundy transition-colors p-2">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <div class="p-6 space-y-6 text-sm text-brand-plum font-medium leading-relaxed">
                <p class="data-text text-[10px] font-bold text-brand-gold uppercase tracking-[0.2em]">Effective Date: May 2026 | Version 1.0</p>
                
                <div>
                    <h4 class="font-heading font-bold text-brand-plum uppercase tracking-wider mb-2">1. Data Collection</h4>
                    <p>To provide structural packaging for your business, Nfelia collects authentication data (email, password), operational data (inventory limits, pricing), and client CRM data (names, phone numbers, delivery addresses) that you manually input into the platform.</p>
                </div>
                
                <div>
                    <h4 class="font-heading font-bold text-brand-plum uppercase tracking-wider mb-2">2. Utilization</h4>
                    <p>Your data is strictly utilized to generate operational documents (Quotations, Invoices, Delivery Notes) and to populate your dashboard metrics. We do not mine your commercial data, nor do we access your client contact list for external communication.</p>
                </div>
                
                <div>
                    <h4 class="font-heading font-bold text-brand-plum uppercase tracking-wider mb-2">3. Storage & Infrastructure</h4>
                    <p>Your workspace is hosted securely on our designated infrastructure (`nfelia.lpc.cm`). Database entries are isolated via unique business identifiers. URL tokens generated for your clients are public-facing but cryptographically randomized to prevent unauthorized sequential access.</p>
                </div>
                
                <div>
                    <h4 class="font-heading font-bold text-brand-plum uppercase tracking-wider mb-2">4. Zero Brokerage</h4>
                    <p>Nfelia will never sell, lease, or broker your financial flow data, client information, or operational metrics to third-party marketing or financial entities.</p>
                </div>
            </div>
            
            <div class="sticky bottom-0 bg-brand-cream border-t border-brand-sand px-6 py-4">
                <button @click="privacyOpen = false" class="w-full bg-brand-plum text-brand-white text-xs font-bold py-3 uppercase tracking-widest hover:bg-brand-burgundy transition-colors">Acknowledge</button>
            </div>
        </div>
    </div>

    <!-- Feedback Modal -->
    <div x-show="feedbackOpen" style="display: none;" class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <!-- Backdrop -->
        <div x-show="feedbackOpen" x-transition.opacity @click="feedbackOpen = false" class="fixed inset-0 bg-brand-plum/80 backdrop-blur-sm transition-opacity"></div>
        
        <!-- Panel -->
        <div x-show="feedbackOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-4" class="relative bg-brand-white border border-brand-sand w-full max-w-md shadow-solid transform transition-all">
            
            <div class="bg-brand-white border-b border-brand-sand px-6 py-4 flex justify-between items-center">
                <h3 class="font-heading font-extrabold text-lg text-brand-burgundy uppercase tracking-widest">Pilot Feedback</h3>
                <button @click="feedbackOpen = false" class="text-brand-plum hover:text-brand-burgundy transition-colors p-2">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <form action="<?= APP_URL ?>/public-feedback" method="POST" class="p-6 space-y-5">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <p class="text-xs text-brand-plum/70 font-medium mb-4">You are part of the initial MVP cohort. Your architectural feedback directly shapes the platform.</p>
                
                <div>
                    <label class="block text-xs font-bold text-brand-plum uppercase tracking-widest mb-2">Business Name</label>
                    <input type="text" name="enterprise" required class="w-full px-4 py-3 bg-brand-white border border-brand-sand text-brand-plum text-sm focus:border-brand-gold focus:ring-0 outline-none transition-all rounded-none" placeholder="Your business name">
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-brand-plum uppercase tracking-widest mb-2">WhatsApp / Phone</label>
                    <input type="text" name="contact" class="w-full px-4 py-3 bg-brand-white border border-brand-sand text-brand-plum text-sm focus:border-brand-gold focus:ring-0 outline-none transition-all rounded-none" placeholder="+234...">
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-brand-plum uppercase tracking-widest mb-2">Structural Request</label>
                    <textarea name="message" rows="4" required class="w-full px-4 py-3 bg-brand-white border border-brand-sand text-brand-plum text-sm focus:border-brand-gold focus:ring-0 outline-none transition-all rounded-none resize-none" placeholder="What should we build next?"></textarea>
                </div>
                
                <button type="submit" class="w-full bg-brand-burgundy text-brand-white text-xs font-bold py-4 uppercase tracking-widest hover:bg-brand-gold transition-colors mt-2">
                    Submit Transmission
                </button>
            </form>
        </div>
    </div>
    
</body>
</html>