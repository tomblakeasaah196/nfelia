<?php http_response_code(404); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Not Found — Nfelia</title>
    
    <!-- Typography -->
    
    <!-- Tailwind CSS -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/public.css?v=<?= @filemtime(BASE_PATH . '/assets/css/public.css') ?>">
    <style>
        body { background-color: #F2EFE7; color: #1B0A11; -webkit-font-smoothing: antialiased; }
        </style>
</head>
<body class="min-h-screen flex flex-col justify-center items-center p-4 relative overflow-hidden">
    
    <!-- Background Grid Accent -->
    <div class="absolute inset-0 opacity-5" style="background-image: radial-gradient(#1B0A11 1px, transparent 1px); background-size: 24px 24px;"></div>

    <!-- Main Card -->
    <div class="w-full max-w-xl bg-brand-white border border-brand-sand shadow-flat relative z-10 p-10 md:p-16 text-center">
        <!-- Accent Line -->
        <div class="absolute top-0 left-0 w-full h-2 bg-brand-burgundy"></div>
        
        <div class="inline-block border border-brand-gold px-4 py-1.5 mb-6 bg-brand-cream">
            <span class="data-text text-[10px] font-bold text-brand-gold uppercase tracking-[0.2em]">Error 404</span>
        </div>
        
        <h1 class="font-heading font-extrabold text-4xl md:text-5xl text-brand-plum mb-4 tracking-tight">Route Not Found.</h1>
        <p class="text-sm text-brand-plum/70 font-medium mb-10 leading-relaxed max-w-md mx-auto">
            The structural coordinate you requested does not exist within the Nfelia architecture. It may have been moved or deleted.
        </p>

        <!-- About Nfelia Snippet -->
        <div class="border-t border-b border-brand-sand py-6 mb-10 text-left bg-brand-cream/30 px-6">
            <h3 class="data-text text-[10px] font-bold text-brand-plum uppercase tracking-widest mb-2 flex items-center gap-2">
                <svg class="w-3 h-3 text-brand-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="square" stroke-linejoin="miter" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                System Information
            </h3>
            <p class="text-xs text-brand-plum/70 leading-relaxed font-medium">
                Nfelia is the invisible thread holding micro-businesses together. We provide corporate-grade operational packaging—from invoicing to inventory tracking—so you can drop the weight of manual tracking and focus on growth.
            </p>
        </div>

        <a href="<?= defined('APP_URL') ? APP_URL : '/' ?>" class="inline-flex items-center justify-center gap-2 bg-brand-burgundy text-brand-white text-xs font-bold px-8 py-4 uppercase tracking-widest hover:bg-brand-gold transition-colors w-full sm:w-auto">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="square" stroke-linejoin="miter" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Return to Landing Page
        </a>
    </div>

    <!-- Footer Branding -->
    <div class="mt-8 text-center relative z-10">
        <div class="data-text text-[9px] text-brand-plum/40 uppercase tracking-widest">
            Powered by JBS Praxis
        </div>
    </div>
</body>
</html>