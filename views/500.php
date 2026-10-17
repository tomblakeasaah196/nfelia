<?php http_response_code(500); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 System Exception — Nfelia</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&family=Roboto+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: { burgundy: '#8B004A', gold: '#B69F5E', cream: '#F2EFE7', plum: '#1B0A11', sand: '#E8E4DA', white: '#FFFFFF' }
                    },
                    fontFamily: {
                        heading: ['"Plus Jakarta Sans"', 'sans-serif'],
                        body: ['Montserrat', 'sans-serif'],
                        mono: ['"Roboto Mono"', 'monospace'],
                    },
                    boxShadow: { 'flat': '0 4px 15px rgba(27, 10, 17, 0.03)' }
                }
            }
        }
    </script>
    <style>
        body { background-color: #F2EFE7; color: #1B0A11; font-family: 'Montserrat', sans-serif; -webkit-font-smoothing: antialiased; }
        .data-text { font-family: '"Roboto Mono"', monospace; }
    </style>
</head>
<body class="min-h-screen flex flex-col justify-center items-center p-4 relative overflow-hidden">
    
    <div class="absolute inset-0 opacity-5" style="background-image: radial-gradient(#8B004A 1px, transparent 1px); background-size: 24px 24px;"></div>

    <div class="w-full max-w-xl bg-brand-white border border-brand-sand shadow-flat relative z-10 p-10 md:p-16 text-center">
        <!-- Error Accent Line -->
        <div class="absolute top-0 left-0 w-full h-2 bg-red-600"></div>
        
        <div class="inline-block border border-red-200 px-4 py-1.5 mb-6 bg-red-50">
            <span class="data-text text-[10px] font-bold text-red-600 uppercase tracking-[0.2em]">Error 500</span>
        </div>
        
        <h1 class="font-heading font-extrabold text-4xl md:text-5xl text-brand-plum mb-4 tracking-tight">System Exception.</h1>
        <p class="text-sm text-brand-plum/70 font-medium mb-10 leading-relaxed max-w-md mx-auto">
            Our infrastructure encountered an unexpected anomaly while processing your request. Our engineering team has been notified of the failure.
        </p>

        <div class="border-t border-brand-sand pt-8 mt-2">
            <a href="<?= defined('APP_URL') ? APP_URL : '/' ?>" class="inline-flex items-center justify-center gap-2 bg-brand-plum text-brand-white text-xs font-bold px-8 py-4 uppercase tracking-widest hover:bg-brand-burgundy transition-colors w-full sm:w-auto">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="square" stroke-linejoin="miter" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Return to Dashboard
            </a>
            
            <div class="mt-6 data-text text-[9px] text-brand-plum/40 uppercase tracking-widest">
                If this persists, please contact support@nfelia.com
            </div>
        </div>
    </div>
</body>
</html>