<?php
/**
 * Nfelia — Public Deed of Acquisition View
 * Variables: $deed, $business, $customer, $items, $title
 */

$primary  = $business['primary_color']   ?? '#1B0A11';
$accent   = $business['secondary_color'] ?? '#B69F5E';
$biz_name = $business['name']            ?? 'Nfelia';
$biz_type = $business['business_type']   ?? 'product';
$currency = $deed['currency_symbol']     ?? '₦';

$deed_title    = $biz_type === 'service' ? 'Certificate of Completion' : 'Deed of Acquisition';
$items_heading = $biz_type === 'service' ? 'Services Rendered' : 'Subject of Acquisition';

// Logo src
$logo_src = '';
if (!empty($business['logo_path']) && file_exists(BASE_PATH . '/' . $business['logo_path'])) {
    $logo_src = APP_URL . '/' . $business['logo_path'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($deed['doc_number']) ?> — <?= e($deed_title) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=EB+Garamond:ital,wght@0,400;0,600;1,400&family=Questrial&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: { primary: '<?= e($primary) ?>', accent: '<?= e($accent) ?>' }
                    },
                    fontFamily: {
                        serif:  ['"EB Garamond"', 'Georgia', 'serif'],
                        sans:   ['Questrial', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body { background: #f5f2ed; font-family: 'Questrial', sans-serif; }
        .garamond { font-family: 'EB Garamond', Georgia, serif; }
    </style>
</head>
<body class="min-h-screen flex flex-col items-center py-10 px-4">

    <!-- Deed Card -->
    <div class="w-full max-w-2xl bg-white shadow-2xl">

        <!-- Brand top stripe -->
        <div style="background:<?= e($primary) ?>; height:5px;"></div>

        <!-- Header -->
        <div class="px-10 pt-10 pb-7 border-b border-stone-200 flex items-start justify-between gap-6">
            <div>
                <?php if ($logo_src): ?>
                    <img src="<?= e($logo_src) ?>" alt="<?= e($biz_name) ?>" class="h-12 object-contain mb-2">
                <?php else: ?>
                    <div class="font-sans font-bold text-lg uppercase tracking-widest mb-2"
                         style="color:<?= e($primary) ?>"><?= e($biz_name) ?></div>
                <?php endif; ?>
                <p class="text-xs text-stone-400 leading-relaxed font-sans tracking-wide">
                    <?= nl2br(e($business['address'] ?? '')) ?>
                </p>
            </div>
            <div class="text-right shrink-0">
                <div class="font-sans font-bold text-sm uppercase tracking-[0.25em]"
                     style="color:<?= e($primary) ?>"><?= e($deed_title) ?></div>
                <div class="text-[10px] uppercase tracking-[0.2em] mt-1.5 font-sans"
                     style="color:<?= e($accent) ?>"><?= e($deed['doc_number']) ?></div>
                <div class="text-[10px] text-stone-400 uppercase tracking-wider mt-0.5 font-sans">
                    <?= date('d F Y', strtotime($deed['issued_at'])) ?>
                </div>
            </div>
        </div>

        <!-- Parties -->
        <div class="grid grid-cols-2 divide-x divide-stone-200 border-b border-stone-200">
            <div class="px-10 py-7">
                <p class="text-[9px] uppercase tracking-[0.28em] font-bold mb-3 font-sans"
                   style="color:<?= e($accent) ?>">Issuing Party</p>
                <p class="font-sans font-bold text-sm uppercase tracking-wide mb-2" style="color:<?= e($primary) ?>">
                    <?= e($biz_name) ?>
                </p>
                <p class="garamond text-sm text-stone-600 leading-relaxed">
                    <?= nl2br(e($business['address'] ?? '')) ?><br>
                    <?= e($business['phone'] ?? '') ?>
                </p>
            </div>
            <div class="px-10 py-7">
                <p class="text-[9px] uppercase tracking-[0.28em] font-bold mb-3 font-sans"
                   style="color:<?= e($accent) ?>"><?= $biz_type === 'service' ? 'Client' : 'Acquired By' ?></p>
                <p class="font-sans font-bold text-sm uppercase tracking-wide mb-2" style="color:<?= e($primary) ?>">
                    <?= e($customer['full_name'] ?? 'Client') ?>
                </p>
                <p class="garamond text-sm text-stone-600 leading-relaxed">
                    <?php if (!empty($customer['business_name'])): ?><?= e($customer['business_name']) ?><br><?php endif; ?>
                    <?= e($customer['phone'] ?? '') ?><br>
                    <?= e($customer['email'] ?? '') ?>
                </p>
            </div>
        </div>

        <!-- Subject / Provenance -->
        <div class="px-10 py-8 border-b border-stone-200">
            <p class="text-[9px] uppercase tracking-[0.28em] font-bold mb-5 font-sans"
               style="color:<?= e($accent) ?>"><?= e($items_heading) ?></p>

            <?php if (!empty($deed['provenance_text'])): ?>
            <div class="border-l-2 pl-5 mb-4" style="border-color:<?= e($accent) ?>">
                <p class="garamond italic text-base text-stone-700 leading-relaxed">
                    <?= nl2br(e($deed['provenance_text'])) ?>
                </p>
            </div>
            <?php endif; ?>

            <?php foreach ($items as $it): ?>
            <div class="flex items-baseline justify-between py-3 border-b border-stone-100 last:border-0">
                <div>
                    <span class="garamond text-sm text-stone-800"><?= e($it['description'] ?? '') ?></span>
                    <span class="text-xs text-stone-400 ml-2 font-sans">× <?= number_format((float)($it['quantity'] ?? 1), 0) ?></span>
                </div>
                <span class="font-sans font-bold text-sm" style="color:<?= e($primary) ?>">
                    <?= money((float)($it['subtotal'] ?? 0), $currency) ?>
                </span>
            </div>
            <?php endforeach; ?>

            <!-- Total -->
            <div class="flex items-center justify-between pt-5 mt-3 border-t-2" style="border-color:<?= e($primary) ?>">
                <span class="font-sans text-xs font-bold uppercase tracking-[0.18em]" style="color:<?= e($primary) ?>">
                    Total <?= $biz_type === 'service' ? 'Service Value' : 'Acquisition Value' ?>
                </span>
                <span class="font-sans font-bold text-xl" style="color:<?= e($primary) ?>">
                    <?= money((float)$deed['total_amount'], $currency) ?>
                </span>
            </div>
        </div>

        <!-- Footer: Signature + Download -->
        <div class="px-10 py-8 flex items-end justify-between gap-6">
            <div class="flex-1">
                <div class="h-10 border-b mb-2" style="border-color:<?= e($primary) ?>"></div>
                <p class="text-[9px] uppercase tracking-[0.2em] font-sans"
                   style="color:<?= e($accent) ?>">Authorized by <?= e($biz_name) ?></p>
            </div>
            <div class="text-right">
                <a href="<?= APP_URL ?>/deed/download/<?= e($deed['token']) ?>"
                   class="inline-block px-6 py-3 text-[10px] font-bold uppercase tracking-widest text-white font-sans transition-opacity hover:opacity-90"
                   style="background:<?= e($primary) ?>">
                    Download PDF
                </a>
            </div>
        </div>

        <!-- Nfelia watermark -->
        <div class="border-t border-stone-100 px-10 py-4 text-center">
            <p class="text-[9px] text-stone-300 uppercase tracking-[0.2em] font-sans">
                Issued via Nfelia · nfelia.com
            </p>
        </div>
    </div>

</body>
</html>
