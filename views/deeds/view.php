<?php
/**
 * Nfelia — Public Deed of Acquisition View
 * Variables: $deed, $business, $customer, $items, $title,
 *            $sign (doc_sign_state(): its signature and verify code),
 *            $photos (DeedController::item_photos(): the items' photos)
 */

['primary' => $primary, 'secondary' => $accent, 'text' => $text, 'secondary_text' => $accent_text, 'on' => $on] = brand_doc_colours($business);   // the brand colours, else Nfelia's (app/helpers/brand.php)
$biz_name = $business['name']            ?? 'Nfelia';
$biz_type = $business['business_type']   ?? 'product';
$currency = $deed['currency_symbol']     ?? '₦';

$deed_title    = $biz_type === 'service' ? 'Certificate of completion' : 'Deed of acquisition';
$items_heading = $biz_type === 'service' ? 'Services rendered' : 'Subject of acquisition';

// Logo src
$logo_src = '';
if (!empty($business['logo_path']) && file_exists(BASE_PATH . '/' . $business['logo_path'])) {
    $logo_src = APP_URL . '/' . $business['logo_path'];
}

// The items' photos, as the shop shows them (WebP copies)
$photo_urls = array_map(
    fn($rel) => preg_match('#^uploads/(' . STOREFRONT_VARIANT_PATH . ')$#i', $rel, $m)
        ? sf_media_url('uploads/cache/640/' . $m[1] . '.webp')
        : APP_URL . '/' . $rel,
    $photos ?? []
);
$pdf_url = APP_URL . '/deed/download/' . $deed['token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?= errors_script_tag() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($deed['doc_number']) ?> — <?= e($deed_title) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=EB+Garamond:ital,wght@0,400;0,600;1,400&family=Questrial&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/public.css?v=<?= @filemtime(BASE_PATH . '/assets/css/public.css') ?>">
    <style>
        :root { --font-serif: "EB Garamond", Georgia, serif; --font-body: "Questrial", sans-serif; --font-heading: "EB Garamond", Georgia, serif;
                --brand-rgb: <?= e(brand_rgb($primary)) ?>; --brand-secondary-rgb: <?= e(brand_rgb($accent)) ?>; }
    </style>
    <style>
        body { background: #f5f2ed; font-family: 'Questrial', sans-serif; }
        .garamond { font-family: 'EB Garamond', Georgia, serif; }
    </style>
</head>
<body class="min-h-screen flex flex-col items-center py-4 px-3 sm:py-10 sm:px-4">

    <!-- The PDF first: on a phone it is what most people came for -->
    <div class="w-full max-w-2xl flex justify-end mb-3 sm:mb-4">
        <a href="<?= e($pdf_url) ?>"
           class="w-full sm:w-auto text-center px-6 py-3 text-sm font-bold font-sans transition-opacity hover:opacity-90"
           style="background:<?= e($primary) ?>; color:<?= e($on) ?>">
            Download PDF
        </a>
    </div>

    <!-- Deed Card -->
    <div class="w-full max-w-2xl bg-white shadow-2xl">

        <!-- Brand top stripe -->
        <div style="background:<?= e($primary) ?>; height:5px;"></div>

        <!-- Header -->
        <div class="px-5 pt-6 pb-5 sm:px-10 sm:pt-10 sm:pb-7 border-b border-stone-200 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 sm:gap-6">
            <div class="min-w-0">
                <?php if ($logo_src): ?>
                    <img src="<?= e($logo_src) ?>" alt="<?= e($biz_name) ?>" class="h-12 object-contain mb-2">
                <?php else: ?>
                    <div class="font-sans font-bold text-lg mb-2"
                         style="color:<?= e($text) ?>"><?= e($biz_name) ?></div>
                <?php endif; ?>
                <!-- On a phone the address shows once, under Issuing party -->
                <p class="hidden sm:block text-xs text-stone-600 leading-relaxed font-sans">
                    <?= nl2br(e($business['address'] ?? '')) ?>
                </p>
            </div>
            <div class="sm:text-right sm:shrink-0">
                <div class="font-sans font-bold text-base"
                     style="color:<?= e($text) ?>"><?= e($deed_title) ?></div>
                <div class="text-sm mt-1.5 font-sans"
                     style="color:<?= e($accent_text) ?>"><?= e($deed['doc_number']) ?></div>
                <div class="text-xs text-stone-600 mt-0.5 font-sans">
                    <?= date('d F Y', strtotime($deed['issued_at'])) ?>
                </div>
            </div>
        </div>

        <!-- Parties -->
        <div class="grid grid-cols-1 sm:grid-cols-2 divide-y sm:divide-y-0 sm:divide-x divide-stone-200 border-b border-stone-200">
            <div class="px-5 py-5 sm:px-10 sm:py-7 min-w-0">
                <p class="text-xs font-bold mb-3 font-sans"
                   style="color:<?= e($accent_text) ?>">Issuing party</p>
                <p class="font-sans font-bold text-base mb-2" style="color:<?= e($text) ?>">
                    <?= e($biz_name) ?>
                </p>
                <p class="garamond text-sm text-stone-600 leading-relaxed">
                    <?= nl2br(e($business['address'] ?? '')) ?><br>
                    <?= e($business['phone'] ?? '') ?>
                </p>
            </div>
            <div class="px-5 py-5 sm:px-10 sm:py-7 min-w-0">
                <p class="text-xs font-bold mb-3 font-sans"
                   style="color:<?= e($accent_text) ?>"><?= $biz_type === 'service' ? 'Client' : 'Acquired by' ?></p>
                <p class="font-sans font-bold text-base mb-2" style="color:<?= e($text) ?>">
                    <?= e($customer['full_name'] ?? 'Client') ?>
                </p>
                <p class="garamond text-sm text-stone-600 leading-relaxed break-words">
                    <?php if (!empty($customer['business_name'])): ?><?= e($customer['business_name']) ?><br><?php endif; ?>
                    <?= e($customer['phone'] ?? '') ?><br>
                    <?= e($customer['email'] ?? '') ?>
                </p>
            </div>
        </div>

        <!-- Subject / Provenance -->
        <div class="px-5 py-6 sm:px-10 sm:py-8 border-b border-stone-200">
            <p class="text-xs font-bold mb-5 font-sans"
               style="color:<?= e($accent_text) ?>"><?= e($items_heading) ?></p>

            <?php if (!empty($deed['provenance_text'])): ?>
            <div class="border-l-2 pl-5 mb-4" style="border-color:<?= e($accent) ?>">
                <p class="garamond italic text-base text-stone-700 leading-relaxed">
                    <?= nl2br(e($deed['provenance_text'])) ?>
                </p>
            </div>
            <?php endif; ?>

            <?php if ($photo_urls): ?>
            <div class="grid <?= count($photo_urls) === 1 ? 'grid-cols-1' : 'grid-cols-2' ?> gap-2 mb-5">
                <?php foreach ($photo_urls as $url): ?>
                <div class="bg-stone-50 flex items-center justify-center <?= count($photo_urls) === 1 ? 'h-72' : 'h-40 sm:h-56' ?>">
                    <img src="<?= e($url) ?>" alt="" loading="lazy" class="max-w-full max-h-full object-contain">
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php foreach ($items as $it): ?>
            <div class="flex items-baseline justify-between gap-4 py-3 border-b border-stone-100 last:border-0">
                <div class="min-w-0">
                    <span class="garamond text-sm text-stone-800"><?= e($it['description'] ?? '') ?></span>
                    <span class="text-xs text-stone-400 ml-2 font-sans whitespace-nowrap">× <?= number_format((float)($it['quantity'] ?? 1), 0) ?></span>
                </div>
                <span class="font-sans font-bold text-sm whitespace-nowrap shrink-0" style="color:<?= e($text) ?>">
                    <?= money((float)($it['subtotal'] ?? 0), $currency) ?>
                </span>
            </div>
            <?php endforeach; ?>

            <!-- Total -->
            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 pt-5 mt-3 border-t-2" style="border-color:<?= e($primary) ?>">
                <span class="font-sans text-sm font-bold" style="color:<?= e($text) ?>">
                    Total <?= $biz_type === 'service' ? 'service value' : 'acquisition value' ?>
                </span>
                <span class="font-sans font-bold text-xl whitespace-nowrap" style="color:<?= e($text) ?>">
                    <?= money((float)$deed['total_amount'], $currency) ?>
                </span>
            </div>
        </div>

        <!-- Footer: the signature (when signed), the verify code, and the PDF -->
        <div class="px-5 pb-6 sm:px-10 sm:pb-8">
            <?php if (!empty($sign['can_sign'])): // the business's owner or an admin, looking at their own deed ?>
            <div class="flex flex-wrap gap-2 mb-2 font-sans">
                <?php $sign_row = false; $sign_back = 'deed/' . $deed['token'];
                      require BASE_PATH . '/views/partials/doc_sign_control.php'; ?>
            </div>
            <?php endif; ?>
            <div class="font-sans"><?php require BASE_PATH . '/views/partials/doc_signs.php'; ?></div>
            <div class="sm:text-right mt-6">
                <a href="<?= e($pdf_url) ?>"
                   class="block sm:inline-block text-center px-6 py-3 text-sm font-bold font-sans transition-opacity hover:opacity-90"
                   style="background:<?= e($primary) ?>; color:<?= e($on) ?>">
                    Download PDF
                </a>
            </div>
        </div>

        <!-- Nfelia watermark -->
        <div class="border-t border-stone-100 px-5 sm:px-10 py-4 text-center">
            <p class="text-xs text-stone-400 font-sans">
                Issued via Nfelia · nfelia.com
            </p>
        </div>
    </div>

</body>
</html>
