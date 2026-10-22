<?php
/**
 * Nfelia — Deed of Acquisition PDF Template
 * Rendered by DeedController::download() via dompdf
 * Variables: $deed, $business, $customer, $items, $sign (doc_sign_state),
 *            $photos (DeedController::item_photos(): the items' photos)
 *
 * Design: luxury editorial — two-column header, hero story block, financial table,
 * the signature when signed and the verify code, brand colors injected per business.
 */

['primary' => $primary, 'secondary' => $accent] = brand_doc_colours($business);   // the brand colours, else Nfelia's (app/helpers/brand.php)
$biz_name = $business['name']            ?? 'Nfelia';
$tagline  = $business['tagline']         ?? '';
$currency = $deed['currency_symbol']     ?? '₦';
$is_creative = in_array($business['category'] ?? '', ['creative', 'fashion', 'other']);

// Logo → base64 for dompdf
$logo_src = '';
if (!empty($business['logo_path']) && file_exists(BASE_PATH . '/' . $business['logo_path'])) {
    $ext      = strtolower(pathinfo($business['logo_path'], PATHINFO_EXTENSION));
    $mime     = in_array($ext, ['svg']) ? 'image/svg+xml' : "image/{$ext}";
    $logo_src = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents(BASE_PATH . '/' . $business['logo_path']));
}

// The items' photos → base64 for dompdf, as light JPEG copies: the link-preview
// size for one photo, the email size for several side by side
$photo_srcs = [];
foreach ($photos ?? [] as $rel) {
    $file = BASE_PATH . '/' . $rel;
    if (preg_match('#^uploads/(' . STOREFRONT_VARIANT_PATH . ')$#i', $rel, $m)) {
        [$size, $width, $bytes] = count($photos) === 1
            ? ['og', STOREFRONT_OG_WIDTH, STOREFRONT_OG_MAX_BYTES]
            : ['mail', STOREFRONT_MAIL_WIDTH, STOREFRONT_MAIL_MAX_BYTES];
        $copy = BASE_PATH . '/uploads/cache/' . $size . '/' . $m[1] . '.jpg';
        if (storefront_make_jpeg($file, $copy, $width, $bytes)) {
            $file = $copy;
        }
    }
    $mime = @getimagesize($file)['mime'] ?? '';
    if (in_array($mime, ['image/jpeg', 'image/png'], true)) {
        $photo_srcs[] = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($file));
    }
}

// Determine deed title based on business type
$biz_type   = $business['business_type'] ?? 'product';
$deed_title = match($biz_type) {
    'service' => 'Certificate of Completion',
    'mixed'   => 'Deed of Acquisition',
    default   => 'Deed of Acquisition',
};
$items_heading = $biz_type === 'service' ? 'SERVICES RENDERED' : 'SUBJECT OF ACQUISITION';

$total = (float)$deed['total_amount'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title><?= e($deed['doc_number']) ?></title>
    <style>
        @page {
            margin: 32px 44px 44px 44px;
            size: A4 portrait;
        }
        body {
            font-family: Georgia, 'Times New Roman', serif;
            color: #1a1a1a;
            font-size: 11px;
            line-height: 1.5;
            background: #ffffff;
        }

        /* ── Utilities ── */
        .text-right  { text-align: right; }
        .text-center { text-align: center; }
        .text-left   { text-align: left; }
        .uppercase   { text-transform: uppercase; }
        .bold        { font-weight: bold; }
        .italic      { font-style: italic; }

        /* ── Top brand bar ── */
        .brand-bar {
            background-color: <?= $primary ?>;
            height: 5px;
            width: 100%;
            margin-bottom: 32px;
        }

        /* ── Document header ── */
        .doc-header-table {
            width: 100%;
            margin-bottom: 28px;
            border-bottom: 1px solid #d5cfc9;
            padding-bottom: 20px;
        }
        .logo-cell { width: 50%; vertical-align: top; }
        .logo-cell img { max-height: 56px; max-width: 160px; }
        .biz-name-text {
            font-size: 18px;
            font-weight: bold;
            color: <?= $primary ?>;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            font-family: Georgia, serif;
        }
        .meta-cell { width: 50%; vertical-align: top; text-align: right; }
        .deed-title-text {
            font-size: 17px;
            letter-spacing: 0.22em;
            color: <?= $primary ?>;
            text-transform: uppercase;
            font-family: Georgia, serif;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .doc-ref-text {
            font-size: 9px;
            letter-spacing: 0.18em;
            color: <?= $accent ?>;
            text-transform: uppercase;
            font-family: Arial, sans-serif;
        }

        /* ── Parties (issuing studio / acquired by) ── */
        .parties-table { width: 100%; margin-bottom: 26px; }
        .party-cell    { width: 50%; vertical-align: top; padding-right: 24px; }
        .party-cell:last-child { padding-right: 0; padding-left: 24px; border-left: 1px solid #e0dad4; }
        .party-label {
            font-size: 8px;
            letter-spacing: 0.28em;
            color: <?= $accent ?>;
            text-transform: uppercase;
            font-family: Arial, sans-serif;
            border-bottom: 1px solid <?= $primary ?>;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }
        .party-name {
            font-size: 14px;
            font-weight: bold;
            color: <?= $primary ?>;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 5px;
        }
        .party-detail {
            font-size: 10.5px;
            color: #4a4040;
            line-height: 1.6;
            font-family: Georgia, serif;
        }

        /* ── Subject section ── */
        .subject-label {
            font-size: 8px;
            letter-spacing: 0.28em;
            color: <?= $accent ?>;
            text-transform: uppercase;
            font-family: Arial, sans-serif;
            border-bottom: 1px solid <?= $primary ?>;
            padding-bottom: 5px;
            margin-bottom: 14px;
        }
        .subject-table { width: 100%; border: 1px solid <?= $primary ?>; margin-bottom: 26px; }
        .subject-image-cell { width: 36%; vertical-align: middle; text-align: center; background-color: #f9f7f4; border-right: 1px solid <?= $primary ?>; padding: 20px; }
        .subject-image-cell img { max-width: 100%; max-height: 150px; }
        .photo-grid { width: 100%; }
        .photo-grid td { width: 50%; padding: 3px; text-align: center; vertical-align: middle; }
        .photo-grid img { max-width: 100%; max-height: 90px; }
        .subject-story-cell { vertical-align: top; padding: 24px 28px; }
        .item-title-text {
            font-size: 15px;
            color: <?= $primary ?>;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .piece-list { font-size: 10.5px; color: #3a3030; line-height: 1.6; margin-bottom: 12px; font-family: Georgia, serif; }
        .piece-more { color: #9a9090; font-style: italic; }
        .provenance-text {
            font-size: 11px;
            color: #3a3030;
            line-height: 1.85;
            font-style: italic;
            font-family: Georgia, serif;
        }

        /* ── Financial table ── */
        .fin-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .fin-table thead th {
            background-color: <?= $primary ?>;
            color: #ffffff;
            font-size: 8.5px;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            padding: 10px 12px;
            font-family: Arial, sans-serif;
            font-weight: bold;
        }
        .fin-table tbody td {
            font-size: 11px;
            padding: 11px 12px;
            border-bottom: 1px solid #ede8e3;
            color: #2a2020;
            font-family: Georgia, serif;
        }
        .fin-table .total-row td {
            border-bottom: 2px solid <?= $primary ?>;
            border-top: 1px solid <?= $primary ?>;
            font-size: 13px;
            font-weight: bold;
            color: <?= $primary ?>;
            font-family: Arial, sans-serif;
            letter-spacing: 0.05em;
            padding-top: 12px;
            padding-bottom: 12px;
        }

        /* ── Footer ── */
        .footer-table { width: 100%; margin-top: 36px; }
        .tagline-cell {
            text-align: right;
            vertical-align: bottom;
            font-size: 9px;
            letter-spacing: 0.3em;
            color: <?= $primary ?>;
            text-transform: uppercase;
            font-family: Arial, sans-serif;
        }
        .nfelia-watermark {
            font-size: 7.5px;
            color: #cfc8c2;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            font-family: Arial, sans-serif;
            text-align: center;
            margin-top: 18px;
        }
    </style>
</head>
<body>

    <!-- Brand top bar -->
    <div class="brand-bar"></div>

    <!-- Header: Logo + Deed Title -->
    <table class="doc-header-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="logo-cell">
                <?php if ($logo_src): ?>
                    <img src="<?= $logo_src ?>" alt="<?= e($biz_name) ?>"><br>
                <?php else: ?>
                    <span class="biz-name-text"><?= e($biz_name) ?></span><br>
                <?php endif; ?>
                <span style="font-size:9px;color:#9a8f8a;font-family:Arial,sans-serif;letter-spacing:0.1em;">
                    <?= e($business['address'] ?? '') ?>
                </span>
            </td>
            <td class="meta-cell">
                <div class="deed-title-text"><?= e($deed_title) ?></div>
                <div class="doc-ref-text">Doc No: <?= e($deed['doc_number']) ?></div>
                <div class="doc-ref-text" style="margin-top:3px;">Date: <?= date('d F Y', strtotime($deed['issued_at'])) ?></div>
            </td>
        </tr>
    </table>

    <!-- Parties: Issuing Studio + Acquired By -->
    <table class="parties-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="party-cell">
                <div class="party-label">ISSUING PARTY</div>
                <div class="party-name"><?= e($biz_name) ?></div>
                <div class="party-detail">
                    <?php if ($business['address']): ?><?= nl2br(e($business['address'])) ?><br><?php endif; ?>
                    <?php if ($business['phone']): ?><?= e($business['phone']) ?><br><?php endif; ?>
                    <?php if ($business['email']): ?><?= e($business['email']) ?><?php endif; ?>
                </div>
            </td>
            <td class="party-cell">
                <div class="party-label"><?= $biz_type === 'service' ? 'CLIENT' : 'ACQUIRED BY' ?></div>
                <div class="party-name"><?= e($customer['full_name'] ?? 'Client') ?></div>
                <div class="party-detail">
                    <?php if (!empty($customer['business_name'])): ?><?= e($customer['business_name']) ?><br><?php endif; ?>
                    <?php if (!empty($customer['address'])): ?><?= nl2br(e($customer['address'])) ?><br><?php endif; ?>
                    <?php if (!empty($customer['phone'])): ?><?= e($customer['phone']) ?><br><?php endif; ?>
                    <?php if (!empty($customer['email'])): ?><?= e($customer['email']) ?><?php endif; ?>
                </div>
            </td>
        </tr>
    </table>

    <!-- Subject of Acquisition -->
    <div class="subject-label"><?= e($items_heading) ?></div>

    <?php
    $first_item = $items[0] ?? [];
    $first_name = $first_item['description'] ?? '';
    $has_provenance = !empty($deed['provenance_text']);
    $multi_items = count($items) > 1;

    // Several pieces: the heading counts them and a short list names them,
    // without the (Color, Size) the table below gives in full
    $pieces = [];
    $count  = 0;
    foreach ($items as $it) {
        $qty   = (float)($it['quantity'] ?? 1);
        $name  = trim((string)($it['description'] ?? ''));
        $short = trim((string)preg_replace('/\s*\([^()]*\)\s*$/', '', $name));
        $pieces[] = ($short !== '' ? $short : $name) . ($qty > 1 && $qty == floor($qty) ? ' × ' . (int)$qty : '');
        $count   += $qty >= 1 && $qty == floor($qty) ? (int)$qty : 1;
    }
    $words = [2 => 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve'];
    $heading = $multi_items
        ? ($words[$count] ?? (string)$count) . ' ' . ($biz_type === 'service' ? 'services' : 'pieces')
        : $first_name;
    $more   = max(0, count($pieces) - 4);
    $pieces = array_slice($pieces, 0, 4);
    ?>

    <?php if ($photo_srcs || ($is_creative && ($has_provenance || !$multi_items))): ?>
    <!-- Full luxury layout: editorial hero block -->
    <table class="subject-table" cellpadding="0" cellspacing="0">
        <tr>
            <?php if ($photo_srcs): /* The pieces themselves: one large, or two to a row */ ?>
            <td class="subject-image-cell">
                <?php if (count($photo_srcs) === 1): ?>
                    <img src="<?= $photo_srcs[0] ?>" alt="">
                <?php else: ?>
                    <table class="photo-grid" cellpadding="0" cellspacing="0">
                        <?php foreach (array_chunk($photo_srcs, 2) as $row): ?>
                        <tr>
                            <?php foreach ($row as $src): ?>
                            <td><img src="<?= $src ?>" alt=""></td>
                            <?php endforeach; ?>
                            <?php if (count($row) === 1): ?><td></td><?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </td>
            <?php elseif ($logo_src): /* Use logo as a stand-in visual if no product image */ ?>
            <td class="subject-image-cell">
                <div style="font-size:8px;letter-spacing:0.2em;color:<?= $accent ?>;text-transform:uppercase;font-family:Arial,sans-serif;margin-bottom:8px;">[ PIECE ]</div>
                <img src="<?= $logo_src ?>" alt="" style="max-height:100px;opacity:0.4;">
            </td>
            <?php endif; ?>
            <td class="subject-story-cell" <?= $logo_src ? '' : 'colspan="1"' ?>>
                <div class="item-title-text"><?= e($heading) ?></div>
                <?php if ($multi_items): ?>
                    <div class="piece-list">
                        <?php foreach ($pieces as $piece): ?><div><?= e($piece) ?></div><?php endforeach; ?>
                        <?php if ($more): ?><div class="piece-more">and <?= $more ?> more, listed below</div><?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($has_provenance): ?>
                    <p class="provenance-text"><?= nl2br(e($deed['provenance_text'])) ?></p>
                <?php elseif (!$multi_items): ?>
                    <p class="provenance-text" style="color:#bbb9b7;"><?= e($first_item['description'] ?? '') ?></p>
                <?php endif; ?>
            </td>
        </tr>
    </table>
    <?php else: ?>
    <!-- Clean professional layout for non-creative businesses -->
    <table style="width:100%;border:1px solid <?= $primary ?>;margin-bottom:26px;" cellpadding="0" cellspacing="0">
        <tr>
            <td style="padding:16px 20px;font-family:Georgia,serif;font-size:11px;color:#3a3030;line-height:1.7;">
                <?php if ($has_provenance): ?>
                    <?= nl2br(e($deed['provenance_text'])) ?>
                <?php else: ?>
                    <em style="color:#9a9090;">
                        This deed certifies the formal completion of the transaction detailed below
                        between <?= e($biz_name) ?> and <?= e($customer['full_name'] ?? 'Client') ?>.
                    </em>
                <?php endif; ?>
            </td>
        </tr>
    </table>
    <?php endif; ?>

    <!-- Financial Table -->
    <table class="fin-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th class="text-left">Description</th>
                <th class="text-center" style="width:60px;">Qty</th>
                <th class="text-right" style="width:130px;">Unit Value</th>
                <th class="text-right" style="width:130px;">Total Value</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $it): ?>
            <tr>
                <td><?= e($it['description'] ?? '') ?></td>
                <td class="text-center"><?= number_format((float)($it['quantity'] ?? 1), 0) ?></td>
                <td class="text-right"><?= money((float)($it['unit_price'] ?? 0), $currency) ?></td>
                <td class="text-right bold"><?= money((float)($it['subtotal'] ?? 0), $currency) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="3" class="text-right" style="letter-spacing:0.1em;">
                    TOTAL <?= $biz_type === 'service' ? 'SERVICE VALUE' : 'ACQUISITION VALUE' ?>
                </td>
                <td class="text-right"><?= money($total, $currency) ?></td>
            </tr>
        </tbody>
    </table>

    <!-- The signature (when signed) and the verify code -->
    <?php require BASE_PATH . '/views/partials/doc_signs_pdf.php'; ?>

    <!-- Tagline -->
    <table class="footer-table" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width:55%;"></td>
            <td class="tagline-cell" style="width:45%;">
                <?php if ($tagline): ?>
                    <div style="font-size:8.5px;letter-spacing:0.28em;color:<?= $accent ?>;text-transform:uppercase;margin-bottom:6px;font-family:Arial,sans-serif;">
                        <?= e($tagline) ?>
                    </div>
                <?php endif; ?>
                <div style="font-size:8px;letter-spacing:0.15em;color:<?= $primary ?>;text-transform:uppercase;font-family:Arial,sans-serif;">
                    <?= e($deed['currency_code'] ?? 'NGN') ?> · <?= date('Y') ?>
                </div>
            </td>
        </tr>
    </table>

    <div class="nfelia-watermark">Issued via Nfelia Workspace · nfelia.com · <?= e($deed['doc_number']) ?></div>

</body>
</html>
