<?php
/**
 * Nfelia — Dynamic Open Graph Image Generator
 * Generates a branded 1200x630 PNG for social media embeds.
 *
 * Usage:
 *   /og-image.php?biz=5              → Business card style
 *   /og-image.php?biz=5&doc=CHF-INV-001  → Invoice, receipt or quotation card
 *   /og-image.php?page=features/debts    → A page of nfelia.com's own site
 *                                          (site_image() in app/helpers/site.php)
 *
 * Place this at the web root (same level as index.php).
 * Requires GD extension (standard on most PHP hosts).
 */

// Served directly as a file, not through index.php, so it loads only what it
// uses: the database connection (config/database.php also reads .env).
require_once __DIR__ . '/config/database.php';

// ── A page of the site: ?page=<key> ──
// The page list says everything the card needs (app/helpers/site.php), so
// this needs no database and no session: only the config and the helpers
// that hold the list, which do nothing when loaded.
if (isset($_GET['page'])) {
    require_once __DIR__ . '/config/app.php';
    require_once __DIR__ . '/app/helpers/functions.php';
    require_once __DIR__ . '/app/helpers/site.php';
    og_page_card(is_string($_GET['page']) ? $_GET['page'] : '');
    exit;
}

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
        $stmtCount = $db->prepare("SELECT COUNT(*) FROM products WHERE business_id = ? AND deleted_at IS NULL AND is_active = 1 AND on_shop = 1");
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

/* ──────────────────────────────────────────
 *  SITE PAGE CARDS (?page=<key>)
 *  Nfelia's colours on cream: the mark and the name at the top, the page's
 *  label small, its title large, nfelia.com on a deep band at the foot.
 *  Kept in storage/og-cache by page, last change and words, so a page's
 *  card is drawn once and a change to it draws a new one.
 * ────────────────────────────────────────── */

function og_page_card(string $key): void {
    $version = 1;   // raise when the drawing changes, so every card is drawn again
    $page = strlen($key) <= 120 ? site_page($key) : null;
    if (!$page) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        echo 'No such page';
        return;
    }
    $kicker = trim((string)($page['og']['kicker'] ?? '')) ?: og_page_group_name($page);
    $title  = trim((string)($page['og']['title'] ?? '')) ?: (string)$page['crumb'];

    $dir  = __DIR__ . '/storage/og-cache';
    $file = $dir . '/page-' . md5($version . '|' . $key . '|' . $page['updated'] . '|' . $kicker . '|' . $title) . '.png';
    if (!is_file($file)) {
        $img = og_page_draw($kicker, $title);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $tmp = $file . '.' . getmypid() . '.tmp';
        if (!@imagepng($img, $tmp, 6) || !@rename($tmp, $file)) {
            @unlink($tmp);
            // Not kept (the folder is not writable): sent all the same
            header('Content-Type: image/png');
            header('Cache-Control: public, max-age=604800');
            imagepng($img);
            imagedestroy($img);
            return;
        }
        imagedestroy($img);
    }
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=604800');
    header('Content-Length: ' . filesize($file));
    readfile($file);
}

/** The small label for a page without its own: its part of the site. */
function og_page_group_name(array $page): string {
    return match ($page['group']) {
        'features'  => 'Features',
        'for'       => 'For your business',
        'countries' => 'Nfelia in ' . site_country($page['country'])['name'],
        'help'      => 'Help centre',
        'blog'      => 'Guides',
        'legal'     => 'Nfelia',
        default     => 'Nfelia',
    };
}

/** The TrueType fonts the cards are drawn with: [bold, regular], or nulls when there are none. */
function og_page_fonts(): array {
    foreach ([__DIR__ . '/vendor/dompdf/dompdf/lib/fonts', __DIR__ . '/assets/fonts', '/usr/share/fonts/truetype/dejavu', '/usr/share/fonts/truetype'] as $dir) {
        if (is_file($dir . '/DejaVuSans-Bold.ttf') && is_file($dir . '/DejaVuSans.ttf')) {
            return [$dir . '/DejaVuSans-Bold.ttf', $dir . '/DejaVuSans.ttf'];
        }
    }
    if (is_file('C:/Windows/Fonts/arialbd.ttf') && is_file('C:/Windows/Fonts/arial.ttf')) {
        return ['C:/Windows/Fonts/arialbd.ttf', 'C:/Windows/Fonts/arial.ttf'];
    }
    return [null, null];
}

/** A colour from '#RRGGBB' on an image. */
function og_page_colour(GdImage $img, string $hex): int {
    return imagecolorallocate($img, hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2)));
}

/** Width of a line of text in pixels. */
function og_page_width(float $size, string $font, string $text): int {
    $box = imagettfbbox($size, 0, $font, $text);
    return $box ? (int)($box[2] - $box[0]) : 0;
}

/** Text with extra space between the letters; returns where it ends. */
function og_page_spaced(GdImage $img, float $size, int $x, int $y, int $colour, string $font, string $text, float $spacing): int {
    $pen  = (float)$x;
    $pair = og_page_width($size, $font, 'HH');
    foreach (mb_str_split($text) as $ch) {
        if ($ch !== ' ') {
            imagettftext($img, $size, 0, (int)round($pen), $y, $colour, $font, $ch);
        }
        // A letter's own advance, side bearings included: measured between two H's
        $pen += og_page_width($size, $font, 'H' . $ch . 'H') - $pair + $spacing;
    }
    return (int)$pen;
}

/**
 * The title in as few lines as it fits, largest size first: at most three
 * lines, the last one cut with … if even the smallest size cannot hold it.
 * Returns [size, lines].
 */
function og_page_wrap(string $font, string $text, int $width): array {
    $words = preg_split('/\s+/u', trim($text)) ?: [''];
    foreach ([66, 60, 54, 48, 44, 40] as $size) {
        $lines = [];
        $line  = '';
        foreach ($words as $word) {
            $try = $line === '' ? $word : $line . ' ' . $word;
            if ($line !== '' && og_page_width($size, $font, $try) > $width) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $try;
            }
        }
        $lines[] = $line;
        $fits = count($lines) <= 3 && max(array_map(fn($l) => og_page_width($size, $font, $l), $lines)) <= $width;
        if ($fits) {
            return [$size, $lines];
        }
    }
    $lines = array_slice($lines, 0, 3);
    $last  = count($lines) - 1;
    while (og_page_width($size, $font, $lines[$last] . '…') > $width && mb_strlen($lines[$last]) > 1) {
        $lines[$last] = rtrim(mb_substr($lines[$last], 0, -1));
    }
    $lines[$last] .= '…';
    return [$size, $lines];
}

function og_page_draw(string $kicker, string $title): GdImage {
    $w = 1200;
    $h = 630;
    $burgundy = '#8B004A';
    $deep     = '#5E0032';
    $gold     = '#B69F5E';
    $ink      = '#1F0A14';
    $cream    = '#F7F3EA';

    // The shapes are drawn twice the size and halved, so their edges are smooth
    $big = imagecreatetruecolor($w * 2, $h * 2);
    imagefilledrectangle($big, 0, 0, $w * 2, $h * 2, og_page_colour($big, $cream));
    // A soft loop at the top right, like the thread around the mark
    imagefilledellipse($big, 2 * 1140, 2 * 10, 2 * 440, 2 * 440, og_page_colour($big, '#F0E2E4'));
    $ring = og_page_colour($big, $gold);
    for ($i = 0; $i < 6; $i++) {
        imageellipse($big, 2 * 1140, 2 * 10, 2 * 540 + $i, 2 * 540 + $i, $ring);
    }
    // The band at the foot, with a gold edge
    imagefilledrectangle($big, 0, 2 * 550, $w * 2, $h * 2, og_page_colour($big, $deep));
    imagefilledrectangle($big, 0, 2 * 550, $w * 2, 2 * 550 + 7, og_page_colour($big, $gold));

    $img = imagecreatetruecolor($w, $h);
    imagecopyresampled($img, $big, 0, 0, 0, 0, $w, $h, $w * 2, $h * 2);
    imagedestroy($big);
    imagealphablending($img, true);

    // The mark, when GD can read PNG
    $mark_path = __DIR__ . '/assets/img/nfelia-mark-email.png';
    $mark = function_exists('imagecreatefrompng') && is_file($mark_path) ? @imagecreatefrompng($mark_path) : false;
    $name_x = 80;
    if ($mark) {
        imagecopyresampled($img, $mark, 80, 66, 0, 0, 110, 80, imagesx($mark), imagesy($mark));
        imagedestroy($mark);
        $name_x = 204;
    }

    [$bold, $regular] = og_page_fonts();
    $c_burgundy = og_page_colour($img, $burgundy);
    $c_ink      = og_page_colour($img, $ink);
    $c_gold     = og_page_colour($img, $gold);
    $c_cream    = og_page_colour($img, $cream);

    if ($bold && function_exists('imagettftext')) {
        og_page_spaced($img, 28, $name_x, 120, $c_burgundy, $bold, 'NFELIA', 5);
        imagefilledrectangle($img, 80, 200, 150, 204, $c_gold);
        // The label, cut to one line
        $label = mb_strtoupper($kicker);
        while (og_page_width(20, $bold, $label) + mb_strlen($label) * 2 > 1000 && mb_strlen($label) > 1) {
            $label = rtrim(mb_substr($label, 0, -1));
        }
        og_page_spaced($img, 20, 80, 246, $c_burgundy, $bold, $label, 2);
        // The title
        [$size, $lines] = og_page_wrap($bold, $title, 1040);
        $y = 288 + $size;
        foreach ($lines as $line) {
            imagettftext($img, $size, 0, 78, (int)$y, $c_ink, $bold, $line);
            $y += $size * 1.2;
        }
        // The foot
        imagettftext($img, 26, 0, 80, 602, $c_cream, $bold, 'nfelia.com');
        $tag = site_org()['tagline'];
        $font = $regular ?: $bold;
        imagettftext($img, 19, 0, 1120 - og_page_width(19, $font, $tag), 600, $c_gold, $font, $tag);
    } else {
        // No TrueType font: GD's own letters, plain but readable
        imagestring($img, 5, $name_x, 96, 'NFELIA', $c_burgundy);
        imagefilledrectangle($img, 80, 200, 150, 204, $c_gold);
        imagestring($img, 4, 80, 230, mb_strtoupper(mb_substr($kicker, 0, 80)), $c_burgundy);
        $y = 280;
        foreach (array_slice(explode("\n", wordwrap($title, 60, "\n", true)), 0, 4) as $line) {
            imagestring($img, 5, 80, $y, $line, $c_ink);
            $y += 28;
        }
        imagestring($img, 5, 80, 585, 'nfelia.com', $c_cream);
    }
    return $img;
}