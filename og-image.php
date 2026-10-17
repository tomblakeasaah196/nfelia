<?php
/**
 * Nfelia — Dynamic Open Graph Image Generator
 * Generates a branded 1200x630 PNG for social media embeds.
 *
 * Usage:
 *   /og-image.php?biz=5              → Business card style
 *   /og-image.php?biz=5&doc=CHF-INV-001  → Invoice, receipt or quotation card
 *
 * Place this at the web root (same level as index.php).
 * Requires GD extension (standard on most PHP hosts).
 */

// Served directly as a file, not through index.php, so it loads only what it
// uses: the database connection (config/database.php also reads .env).
require_once __DIR__ . '/config/database.php';

$biz_id     = (int)($_GET['biz'] ?? 0);
$doc_number = trim($_GET['doc'] ?? '');

if ($biz_id <= 0) {
    http_response_code(400);
    exit('Missing biz parameter');
}

$db = get_db();

// Fetch business
$stmt = $db->prepare("SELECT name, tagline, category, primary_color, secondary_color, logo_path FROM businesses WHERE id = ?");
$stmt->execute([$biz_id]);
$biz = $stmt->fetch();

if (!$biz) {
    http_response_code(404);
    exit('Business not found');
}

// ── Document card: only for a real document of this business ──
// Anything else is ignored, so the query string can neither put its own text
// on an image served from nfelia.com nor add a cache file per made-up number.
$doc_card = null;
if ($doc_number !== '') {
    $doc_cards = [
        'invoices'   => ['INVOICE', 'Open this link to view and pay your invoice.'],
        'receipts'   => ['PAYMENT RECEIPT', 'Open this link to view and download your receipt.'],
        'quotations' => ['REQUEST FOR ENGAGEMENT', 'Open this link to view and download your quotation.'],
    ];
    foreach ($doc_cards as $table => $card) {
        $stmtDoc = $db->prepare("SELECT 1 FROM {$table} WHERE business_id = ? AND doc_number = ?");
        $stmtDoc->execute([$biz_id, $doc_number]);
        if ($stmtDoc->fetchColumn()) {
            $doc_card = $card;
            break;
        }
    }
    if (!$doc_card) {
        $doc_number = '';
    }
}

// ── Cache: serve cached file if exists and < 24 hours old ──
$cache_dir  = __DIR__ . '/storage/og-cache';
$cache_key  = md5($biz_id . '|' . $doc_number . '|' . $biz['name'] . '|' . $biz['primary_color']);
$cache_file = $cache_dir . '/' . $cache_key . '.png';

if (is_file($cache_file) && (time() - filemtime($cache_file)) < 86400) {
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=86400');
    readfile($cache_file);
    exit;
}

// ── Generate image ──
$w = 1200;
$h = 630;
$img = imagecreatetruecolor($w, $h);
imagealphablending($img, true);

// Parse brand colors
$pc = sscanf($biz['primary_color'] ?? '#8B004A', '#%02x%02x%02x');
$sc = sscanf($biz['secondary_color'] ?? '#B69F5E', '#%02x%02x%02x');

$color_primary   = imagecolorallocate($img, $pc[0], $pc[1], $pc[2]);
$color_secondary = imagecolorallocate($img, $sc[0], $sc[1], $sc[2]);
$color_white     = imagecolorallocate($img, 255, 255, 255);
$color_dark      = imagecolorallocate($img, 40, 20, 30);
$color_light_bg  = imagecolorallocate($img, 242, 239, 231); // #F2EFE7

// Background: gradient effect using two fills
imagefilledrectangle($img, 0, 0, $w, $h, $color_white);

// Top accent bar (brand color)
imagefilledrectangle($img, 0, 0, $w, 8, $color_primary);

// Left accent strip
imagefilledrectangle($img, 0, 0, 8, $h, $color_primary);

// Bottom bar (secondary color)
imagefilledrectangle($img, 0, $h - 60, $w, $h, $color_primary);

// ── Try to load a nice font, fall back to built-in ──
$font_bold   = null;
$font_normal = null;
$font_dirs = [
    __DIR__ . '/vendor/dompdf/dompdf/lib/fonts',   // installed with dompdf on every deploy
    __DIR__ . '/assets/fonts',
    '/usr/share/fonts/truetype/dejavu',
    '/usr/share/fonts/truetype',
    'C:/Windows/Fonts',
];

foreach ($font_dirs as $dir) {
    if (!$font_bold && is_file($dir . '/DejaVuSans-Bold.ttf')) {
        $font_bold = $dir . '/DejaVuSans-Bold.ttf';
    }
    if (!$font_normal && is_file($dir . '/DejaVuSans.ttf')) {
        $font_normal = $dir . '/DejaVuSans.ttf';
    }
    // Windows fallback
    if (!$font_bold && is_file($dir . '/arialbd.ttf')) {
        $font_bold = $dir . '/arialbd.ttf';
    }
    if (!$font_normal && is_file($dir . '/arial.ttf')) {
        $font_normal = $dir . '/arial.ttf';
    }
}

$use_ttf = $font_bold && $font_normal && function_exists('imagettftext');

if ($use_ttf) {
    // ── TTF path: beautiful text ──

    // Business name (large)
    $name = mb_strtoupper($biz['name']);
    $name_size = 42;
    // Shrink if name is too long
    $bbox = imagettfbbox($name_size, 0, $font_bold, $name);
    while (($bbox[2] - $bbox[0]) > ($w - 120) && $name_size > 20) {
        $name_size -= 2;
        $bbox = imagettfbbox($name_size, 0, $font_bold, $name);
    }
    imagettftext($img, $name_size, 0, 60, 160, $color_primary, $font_bold, $name);

    // Tagline or category
    $subtitle = $biz['tagline'] ?: ucfirst($biz['category'] ?? 'Business');
    if (mb_strlen($subtitle) > 80) $subtitle = mb_substr($subtitle, 0, 77) . '...';
    imagettftext($img, 18, 0, 60, 210, $color_dark, $font_normal, $subtitle);

    // Divider line
    imagefilledrectangle($img, 60, 240, 300, 243, $color_secondary);

    if ($doc_number !== '') {
        // ── Document card style ──
        imagettftext($img, 14, 0, 60, 290, $color_secondary, $font_bold, $doc_card[0]);
        imagettftext($img, 32, 0, 60, 345, $color_dark, $font_bold, $doc_number);
        imagettftext($img, 14, 0, 60, 385, $color_primary, $font_normal, $doc_card[1]);
    } else {
        // ── Business card style ──
        imagettftext($img, 14, 0, 60, 290, $color_secondary, $font_bold, 'PRODUCTS & SERVICES');

        // Count products
        $stmtCount = $db->prepare("SELECT COUNT(*) FROM products WHERE business_id = ? AND deleted_at IS NULL AND is_active = 1");
        $stmtCount->execute([$biz_id]);
        $prod_count = (int)$stmtCount->fetchColumn();

        if ($prod_count > 0) {
            imagettftext($img, 20, 0, 60, 335, $color_dark, $font_normal, $prod_count . ' item' . ($prod_count > 1 ? 's' : '') . ' available');
        }
        imagettftext($img, 14, 0, 60, 385, $color_primary, $font_normal, 'Browse our catalogue and request a quote.');
    }

    // ── Logo (if exists) ──
    if ($biz['logo_path'] && is_file(__DIR__ . '/' . $biz['logo_path'])) {
        $logo_path = __DIR__ . '/' . $biz['logo_path'];
        $ext = strtolower(pathinfo($logo_path, PATHINFO_EXTENSION));
        $logo = null;
        if ($ext === 'png') $logo = @imagecreatefrompng($logo_path);
        elseif ($ext === 'jpg' || $ext === 'jpeg') $logo = @imagecreatefromjpeg($logo_path);
        elseif ($ext === 'webp') $logo = @imagecreatefromwebp($logo_path);

        if ($logo) {
            $lw = imagesx($logo);
            $lh = imagesy($logo);
            $max = 180;
            $ratio = min($max / $lw, $max / $lh);
            $nw = (int)($lw * $ratio);
            $nh = (int)($lh * $ratio);
            $lx = $w - $nw - 80;
            $ly = 100;

            // White background circle for logo
            $pad = 20;
            imagefilledellipse($img, $lx + $nw / 2, $ly + $nh / 2, $nw + $pad * 2, $nh + $pad * 2, $color_light_bg);
            imagecopyresampled($img, $logo, $lx, $ly, 0, 0, $nw, $nh, $lw, $lh);
            imagedestroy($logo);
        }
    }

    // ── Nfelia branding in bottom bar ──
    imagettftext($img, 13, 0, 60, $h - 22, $color_white, $font_bold, 'Powered by Nfelia');
    imagettftext($img, 11, 0, $w - 340, $h - 22, $color_white, $font_normal, 'nfelia.com — Business Operations Platform');

} else {
    // ── Fallback: GD built-in fonts (no TTF available) ──
    $f5 = 5; // largest built-in font

    // Business name
    $name = mb_strtoupper($biz['name']);
    imagestring($img, $f5, 60, 140, $name, $color_primary);

    // Subtitle
    $subtitle = $biz['tagline'] ?: ucfirst($biz['category'] ?? 'Business');
    imagestring($img, 4, 60, 180, substr($subtitle, 0, 70), $color_dark);

    // Divider
    imagefilledrectangle($img, 60, 210, 300, 212, $color_secondary);

    if ($doc_number !== '') {
        imagestring($img, 3, 60, 240, $doc_card[0], $color_secondary);
        imagestring($img, $f5, 60, 275, $doc_number, $color_dark);
        imagestring($img, 3, 60, 310, $doc_card[1], $color_primary);
    } else {
        imagestring($img, 3, 60, 240, 'PRODUCTS & SERVICES', $color_secondary);
        imagestring($img, 3, 60, 275, 'Browse our catalogue and request a quote.', $color_primary);
    }

    // Nfelia branding
    imagestring($img, 3, 60, $h - 35, 'Powered by Nfelia — nfelia.com', $color_white);
}

// ── Output ──
// Cache it
if (!is_dir($cache_dir)) {
    @mkdir($cache_dir, 0755, true);
}
imagepng($img, $cache_file, 6);

// Serve
header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');
imagepng($img);
imagedestroy($img);