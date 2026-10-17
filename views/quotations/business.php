<?php
/**
 * Nfelia — Public Business Landing Page (Standalone, No Auth)
 * Accessed via /biz/{slug}
 *
 * This is a standalone page — NOT rendered inside app.php.
 * Controller (PublicController) provides:
 *   $business, $products, $services, $bank, $categories
 */
$currency   = $business['currency_symbol'] ?? '₦';
$primary    = $business['primary_color'] ?? '#8B004A';
$secondary  = $business['secondary_color'] ?? '#B69F5E';
$font_map   = FONT_PAIRINGS[$business['font_pairing'] ?? 'modern_sans'] ?? FONT_PAIRINGS['modern_sans'];
$font_heading = $font_map['heading'];
$font_body    = $font_map['body'];
$google_fonts = urlencode($font_heading) . ':wght@400;600;700;800&family=' . urlencode($font_body) . ':wght@400;500;600';

$has_products = !empty($products);
$has_services = !empty($services);
$has_socials  = $business['social_instagram'] || $business['social_facebook'] || $business['social_tiktok'] || $business['social_twitter'];

// SEO helpers
$page_url     = APP_URL . '/biz/' . e($business['slug']);
$product_count = count($products);
$service_count = count($services);
$item_summary  = [];
if ($product_count) $item_summary[] = $product_count . ' product' . ($product_count > 1 ? 's' : '');
if ($service_count) $item_summary[] = $service_count . ' service' . ($service_count > 1 ? 's' : '');
$item_text     = !empty($item_summary) ? implode(' & ', $item_summary) : '';

$seo_title = e($business['name']) . ($business['tagline'] ? ' — ' . e($business['tagline']) : '');
$seo_desc  = $business['about_text']
    ? mb_substr(strip_tags($business['about_text']), 0, 155) . '…'
    : ($business['tagline']
        ? e($business['tagline']) . ($item_text ? '. Browse ' . $item_text . '.' : '')
        : e($business['name']) . ($item_text ? ' — Browse ' . $item_text . ' and request a quote.' : ' — View our offerings and request a quote.'));
$seo_desc .= ' | Powered by Nfelia';

$og_image = $business['logo_path']
    ? APP_URL . '/' . e($business['logo_path'])
    : APP_URL . '/og-image.php?biz=' . (int)$business['id'];
?>
<!DOCTYPE html>
<html lang="en" prefix="og: https://ogp.me/ns#">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $seo_title ?></title>
    <meta name="description" content="<?= e($seo_desc) ?>">
    <meta name="robots" content="index, follow">
    <meta name="author" content="<?= e($business['name']) ?>">
    <link rel="canonical" href="<?= $page_url ?>">

    <!-- Open Graph (Facebook, WhatsApp, LinkedIn, Telegram) -->
    <meta property="og:type" content="business.business">
    <meta property="og:site_name" content="<?= e($business['name']) ?> — via Nfelia">
    <meta property="og:title" content="<?= e($business['name']) ?><?= $item_text ? ' — ' . $item_text : '' ?>">
    <meta property="og:description" content="<?= e($seo_desc) ?>">
    <meta property="og:image" content="<?= $og_image ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="<?= e($business['name']) ?> — Products & Services">
    <meta property="og:url" content="<?= $page_url ?>">
    <meta property="og:locale" content="en_NG">
    <?php if ($business['phone']): ?>
        <meta property="business:contact_data:phone_number" content="<?= e($business['phone']) ?>">
    <?php endif; ?>
    <?php if ($business['email']): ?>
        <meta property="business:contact_data:email" content="<?= e($business['email']) ?>">
    <?php endif; ?>
    <?php if ($business['address']): ?>
        <meta property="business:contact_data:street_address" content="<?= e($business['address']) ?>">
    <?php endif; ?>

    <!-- Twitter / X Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($business['name']) ?><?= $item_text ? ' — ' . $item_text : '' ?>">
    <meta name="twitter:description" content="<?= e($seo_desc) ?>">
    <meta name="twitter:image" content="<?= $og_image ?>">
    <?php if ($business['social_twitter']): ?>
        <meta name="twitter:site" content="@<?= e(ltrim($business['social_twitter'], '@')) ?>">
    <?php endif; ?>

    <!-- JSON-LD Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "LocalBusiness",
        "name": <?= json_encode($business['name']) ?>,
        <?php if ($business['tagline']): ?>"description": <?= json_encode($business['tagline']) ?>,<?php endif; ?>
        "url": <?= json_encode($page_url) ?>,
        <?php if ($business['logo_path']): ?>"logo": <?= json_encode(APP_URL . '/' . $business['logo_path']) ?>,
        "image": <?= json_encode(APP_URL . '/' . $business['logo_path']) ?>,<?php endif; ?>
        <?php if ($business['phone']): ?>"telephone": <?= json_encode($business['phone']) ?>,<?php endif; ?>
        <?php if ($business['email']): ?>"email": <?= json_encode($business['email']) ?>,<?php endif; ?>
        <?php if ($business['address']): ?>"address": { "@type": "PostalAddress", "streetAddress": <?= json_encode($business['address']) ?> },<?php endif; ?>
        <?php if ($has_socials): ?>
        "sameAs": [
            <?php $links = []; ?>
            <?php if ($business['social_instagram']): $links[] = '"https://instagram.com/' . e(ltrim($business['social_instagram'], '@')) . '"'; endif; ?>
            <?php if ($business['social_facebook']): $links[] = '"https://facebook.com/' . e($business['social_facebook']) . '"'; endif; ?>
            <?php if ($business['social_tiktok']): $links[] = '"https://tiktok.com/@' . e(ltrim($business['social_tiktok'], '@')) . '"'; endif; ?>
            <?php if ($business['social_twitter']): $links[] = '"https://x.com/' . e(ltrim($business['social_twitter'], '@')) . '"'; endif; ?>
            <?= implode(",\n            ", $links) ?>
        ],
        <?php endif; ?>
        <?php if ($has_products || $has_services): ?>
        "hasOfferCatalog": {
            "@type": "OfferCatalog",
            "name": "Products & Services",
            "numberOfItems": <?= $product_count + $service_count ?>,
            "itemListElement": [
                <?php $ld_items = []; foreach (array_slice(array_merge($products, $services), 0, 10) as $p): ?>
                <?php $ld_items[] = json_encode([
                    '@type' => $p['is_service'] ? 'Service' : 'Product',
                    'name' => $p['name'],
                    'category' => $p['category'] ?? '',
                    'offers' => [
                        '@type' => 'Offer',
                        'price' => number_format((float)$p['sell_price'], 2, '.', ''),
                        'priceCurrency' => 'NGN',
                        'availability' => 'https://schema.org/InStock'
                    ]
                ]); endforeach; ?>
                <?= implode(",\n                ", $ld_items) ?>
            ]
        },
        <?php endif; ?>
        "potentialAction": {
            "@type": "QuoteAction",
            "target": <?= json_encode($page_url . '#request-quote') ?>,
            "name": "Request a Quote"
        },
        "branding": {
            "@type": "Organization",
            "name": "Nfelia",
            "url": "https://nfelia.lpc.cm"
        }
    }
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=<?= $google_fonts ?>&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { fontFamily: {
            heading: ['"<?= $font_heading ?>"', 'sans-serif'],
            body: ['"<?= $font_body ?>"', 'sans-serif'],
        }, colors: {
            brand: { primary: '<?= e($primary) ?>', secondary: '<?= e($secondary) ?>' }
        }}}}
    </script>
    <style>
        body { font-family: '<?= $font_body ?>', sans-serif; }
        .btn-quote {
            background: <?= e($primary) ?>;
            color: #fff;
            transition: all .2s;
        }
        .btn-quote:hover { filter: brightness(1.15); transform: translateY(-1px); }
        .card-hover { transition: all .25s; }
        .card-hover:hover { transform: translateY(-3px); box-shadow: 0 12px 24px rgba(0,0,0,.08); }
        .hero-gradient {
            background: linear-gradient(135deg, <?= e($primary) ?> 0%, <?= e($primary) ?>DD 50%, <?= e($secondary) ?> 100%);
        }
        .section-fade { animation: fadeUp .5s ease-out both; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

    <!-- ═══ HERO ═══ -->
    <header class="hero-gradient text-white">
        <div class="max-w-5xl mx-auto px-4 py-12 sm:py-16">
            <div class="flex flex-col sm:flex-row items-center gap-6">
                <?php if ($business['logo_path']): ?>
                    <div class="w-20 h-20 sm:w-24 sm:h-24 bg-white/20 rounded-2xl p-2 flex-shrink-0 backdrop-blur-sm">
                        <img src="<?= APP_URL ?>/<?= e($business['logo_path']) ?>" alt="<?= e($business['name']) ?>" class="w-full h-full object-contain rounded-xl">
                    </div>
                <?php endif; ?>
                <div class="text-center sm:text-left">
                    <h1 class="font-heading font-extrabold text-3xl sm:text-4xl tracking-tight"><?= e($business['name']) ?></h1>
                    <?php if ($business['tagline']): ?>
                        <p class="text-white/80 text-sm sm:text-base mt-2 max-w-lg"><?= e($business['tagline']) ?></p>
                    <?php endif; ?>
                    <div class="flex flex-wrap items-center justify-center sm:justify-start gap-3 mt-4 text-xs text-white/70">
                        <?php if ($business['phone']): ?>
                            <a href="tel:<?= e($business['phone']) ?>" class="flex items-center gap-1 hover:text-white">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                <?= e($business['phone']) ?>
                            </a>
                        <?php endif; ?>
                        <?php if ($business['email']): ?>
                            <a href="mailto:<?= e($business['email']) ?>" class="flex items-center gap-1 hover:text-white">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                <?= e($business['email']) ?>
                            </a>
                        <?php endif; ?>
                        <?php if ($business['address']): ?>
                            <span class="flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <?= e($business['address']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- CTA in hero -->
            <div class="mt-8 flex flex-wrap items-center justify-center sm:justify-start gap-3">
                <a href="#request-quote" class="bg-white font-heading font-bold text-xs uppercase tracking-widest px-6 py-3 rounded-xl shadow-lg hover:shadow-xl transition-all" style="color: <?= e($primary) ?>;">
                    Request a Quote
                </a>
                <?php if ($has_products || $has_services): ?>
                    <a href="#catalogue" class="border-2 border-white/40 text-white font-heading font-bold text-xs uppercase tracking-widest px-6 py-3 rounded-xl hover:bg-white/10 transition-all">
                        View Catalogue
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- ═══ ABOUT ═══ -->
    <?php if ($business['about_text']): ?>
    <section class="section-fade max-w-5xl mx-auto px-4 py-10">
        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-gray-100">
            <h2 class="font-heading font-extrabold text-lg uppercase tracking-wider mb-3" style="color: <?= e($primary) ?>;">About Us</h2>
            <div class="text-sm text-gray-700 leading-relaxed whitespace-pre-line"><?= e($business['about_text']) ?></div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ═══ PRODUCTS CATALOGUE ═══ -->
    <?php if ($has_products || $has_services): ?>
    <section id="catalogue" class="section-fade max-w-5xl mx-auto px-4 py-8" <?php if (count($categories) > 1): ?>x-data="{ active: 'all' }"<?php endif; ?>>
        <h2 class="font-heading font-extrabold text-xl uppercase tracking-wider mb-1" style="color: <?= e($primary) ?>;">
            <?php if ($has_products && $has_services): ?>Products & Services
            <?php elseif ($has_services): ?>Our Services
            <?php else: ?>Our Products<?php endif; ?>
        </h2>
        <p class="text-xs text-gray-500 mb-6">Browse our offerings and request a quote for anything you need.</p>

        <!-- Category Filter (if multiple categories) -->
        <?php if (count($categories) > 1): ?>
        <div class="flex flex-wrap gap-2 mb-6">
            <button @click="active = 'all'" :class="active === 'all' ? 'text-white' : 'bg-white text-gray-600 border border-gray-200'"
                    class="text-[10px] font-bold uppercase tracking-widest px-4 py-2 rounded-lg transition-all"
                    :style="active === 'all' ? 'background: <?= e($primary) ?>' : ''">All</button>
            <?php foreach ($categories as $cat): ?>
                <button @click="active = '<?= e(strtolower($cat)) ?>'" :class="active === '<?= e(strtolower($cat)) ?>' ? 'text-white' : 'bg-white text-gray-600 border border-gray-200'"
                        class="text-[10px] font-bold uppercase tracking-widest px-4 py-2 rounded-lg transition-all"
                        :style="active === '<?= e(strtolower($cat)) ?>' ? 'background: <?= e($primary) ?>' : ''"><?= e($cat) ?></button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Product Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            <?php foreach (array_merge($products, $services) as $item): ?>
                <div class="card-hover bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden"
                     <?php if (count($categories) > 1): ?>
                     x-show="active === 'all' || active === '<?= e(strtolower($item['category'] ?? '')) ?>'"
                     <?php endif; ?>>
                    <?php if ($item['image_path']): ?>
                        <div class="aspect-square bg-gray-100 overflow-hidden">
                            <img src="<?= APP_URL ?>/<?= e($item['image_path']) ?>" alt="<?= e($item['name']) ?>" class="w-full h-full object-cover">
                        </div>
                    <?php else: ?>
                        <div class="aspect-square bg-gray-50 flex items-center justify-center">
                            <?php if ($item['is_service']): ?>
                                <svg class="w-10 h-10 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            <?php else: ?>
                                <svg class="w-10 h-10 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="p-3">
                        <h3 class="font-heading font-bold text-xs text-gray-800 truncate"><?= e($item['name']) ?></h3>
                        <?php if ($item['category']): ?>
                            <p class="text-[9px] text-gray-400 uppercase tracking-wider mt-0.5"><?= e($item['category']) ?></p>
                        <?php endif; ?>
                        <div class="flex items-center justify-between mt-2">
                            <span class="font-heading font-extrabold text-sm" style="color: <?= e($primary) ?>;">
                                <?= money((float)$item['sell_price'], $currency) ?>
                            </span>
                            <?php if ($item['is_service']): ?>
                                <span class="text-[8px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full" style="background: <?= e($secondary) ?>22; color: <?= e($secondary) ?>;">Service</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ═══ REQUEST A QUOTE ═══ -->
    <section id="request-quote" class="section-fade max-w-5xl mx-auto px-4 py-10">
        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-gray-100">
            <h2 class="font-heading font-extrabold text-lg uppercase tracking-wider mb-1" style="color: <?= e($primary) ?>;">Request a Quote</h2>
            <p class="text-xs text-gray-500 mb-6">Fill in your details and we'll get back to you with a quotation.</p>

            <form id="quoteRequestForm" class="space-y-4">
                <input type="hidden" name="business_id" value="<?= (int)$business['id'] ?>">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Your Name *</label>
                        <input type="text" name="name" required class="w-full px-4 py-3 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-opacity-30" style="--tw-ring-color: <?= e($primary) ?>;" placeholder="Full name">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Phone / WhatsApp *</label>
                        <input type="tel" name="phone" required class="w-full px-4 py-3 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-opacity-30" placeholder="e.g. 0801 234 5678">
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Email (optional)</label>
                    <input type="email" name="email" class="w-full px-4 py-3 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-opacity-30" placeholder="your@email.com">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">What do you need? *</label>
                    <textarea name="message" rows="4" required class="w-full px-4 py-3 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-opacity-30 resize-none" placeholder="Describe the products or services you're interested in, quantities, and any special requirements..."></textarea>
                </div>
                <div class="flex items-center gap-3">
                    <button type="submit" class="btn-quote font-heading font-bold text-xs uppercase tracking-widest px-8 py-3 rounded-xl shadow-sm">
                        Send Request
                    </button>
                    <span id="formStatus" class="text-xs font-semibold hidden"></span>
                </div>
            </form>
        </div>
    </section>

    <!-- ═══ PAYMENT INFO ═══ -->
    <?php if ($bank): ?>
    <section class="section-fade max-w-5xl mx-auto px-4 pb-8">
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
            <h2 class="font-heading font-extrabold text-sm uppercase tracking-wider mb-3" style="color: <?= e($primary) ?>;">Payment Details</h2>
            <div class="text-xs text-gray-600 space-y-1">
                <div>Bank: <strong><?= e($bank['bank_name']) ?></strong></div>
                <div>Account Name: <strong><?= e($bank['account_name']) ?></strong></div>
                <div>Account Number: <strong class="text-sm" style="color: <?= e($primary) ?>;"><?= e($bank['account_number']) ?></strong></div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ═══ SOCIAL LINKS ═══ -->
    <?php if ($has_socials): ?>
    <section class="max-w-5xl mx-auto px-4 pb-8">
        <div class="flex items-center justify-center gap-4">
            <?php if ($business['social_instagram']): ?>
                <a href="https://instagram.com/<?= e(ltrim($business['social_instagram'], '@')) ?>" target="_blank" rel="noopener" class="w-10 h-10 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:text-pink-600 hover:border-pink-200 transition-all">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                </a>
            <?php endif; ?>
            <?php if ($business['social_facebook']): ?>
                <a href="https://facebook.com/<?= e($business['social_facebook']) ?>" target="_blank" rel="noopener" class="w-10 h-10 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:text-blue-600 hover:border-blue-200 transition-all">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                </a>
            <?php endif; ?>
            <?php if ($business['social_tiktok']): ?>
                <a href="https://tiktok.com/@<?= e(ltrim($business['social_tiktok'], '@')) ?>" target="_blank" rel="noopener" class="w-10 h-10 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:text-gray-900 hover:border-gray-400 transition-all">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>
                </a>
            <?php endif; ?>
            <?php if ($business['social_twitter']): ?>
                <a href="https://x.com/<?= e(ltrim($business['social_twitter'], '@')) ?>" target="_blank" rel="noopener" class="w-10 h-10 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:text-gray-900 hover:border-gray-400 transition-all">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                </a>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ═══ WHATSAPP FLOATING BUTTON ═══ -->
    <?php if ($business['phone']): ?>
    <?php
        $wa_number = preg_replace('/[^0-9]/', '', $business['phone']);
        // If starts with 0, assume Nigerian — prepend 234
        if (str_starts_with($wa_number, '0')) {
            $wa_number = '234' . substr($wa_number, 1);
        }
        $wa_text = urlencode("Hello " . $business['name'] . "! I found you on your page and I'm interested in your products/services. Can I get a quote?");
    ?>
    <a href="https://wa.me/<?= $wa_number ?>?text=<?= $wa_text ?>" target="_blank" rel="noopener"
       class="fixed bottom-6 right-6 w-14 h-14 bg-green-500 hover:bg-green-600 text-white rounded-full shadow-lg flex items-center justify-center transition-all hover:scale-110 z-50">
        <svg class="w-7 h-7" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
    </a>
    <?php endif; ?>

    <!-- ═══ FOOTER ═══ -->
    <footer class="py-8 text-center border-t border-gray-200 mt-8">
        <p class="text-[10px] text-gray-400 font-medium">
            &copy; <?= date('Y') ?> <?= e($business['name']) ?>. All rights reserved.
        </p>
        <p class="text-[9px] text-gray-300 mt-1">Powered by <a href="https://nfelia.lpc.cm" target="_blank" rel="noopener" class="font-bold text-gray-400 hover:text-gray-600">Nfelia</a> — Business Operations Platform</p>
    </footer>

    <!-- Alpine.js for category filter -->
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js" defer></script>

    <script>
    // Quote request form
    document.getElementById('quoteRequestForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        const status = document.getElementById('formStatus');
        const data = new FormData(this);

        btn.disabled = true;
        btn.textContent = 'Sending...';
        status.classList.add('hidden');

        try {
            const resp = await fetch('<?= APP_URL ?>/public/quote-request', {
                method: 'POST',
                body: data
            });
            const json = await resp.json();

            if (json.success) {
                status.textContent = 'Request sent! We\'ll get back to you soon.';
                status.className = 'text-xs font-semibold text-green-600';
                status.classList.remove('hidden');
                this.reset();
            } else {
                status.textContent = json.error || 'Something went wrong. Please try again.';
                status.className = 'text-xs font-semibold text-red-600';
                status.classList.remove('hidden');
            }
        } catch (err) {
            status.textContent = 'Network error. Please try again.';
            status.className = 'text-xs font-semibold text-red-600';
            status.classList.remove('hidden');
        }

        btn.disabled = false;
        btn.textContent = 'Send Request';
    });
    </script>
</body>
</html>