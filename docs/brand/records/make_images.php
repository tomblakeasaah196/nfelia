<?php
/**
 * The Nfelia pictures inside every Records Book, drawn from the logo:
 *   php docs/brand/records/make_images.php
 *
 *   assets/img/records/nfelia-lockup.png   the mark beside the NFELIA
 *                                          wordmark, for the top of the cover
 *   assets/img/records/nfelia-mark.png     the mark alone, small, for the
 *                                          middle of the QR code
 *
 * Both are cut from assets/img/nfelia-logo.png (1620 × 1620, transparent),
 * so the book always carries the logo as drawn. Run again if the logo changes.
 */
if (PHP_SAPI !== 'cli') { exit; }

$root = dirname(__DIR__, 3);
$logo = imagecreatefrompng($root . '/assets/img/nfelia-logo.png');
imagesavealpha($logo, true);

/** The smallest box around everything not transparent inside [x, y, w, h]. */
function trim_box(GdImage $img, int $x0, int $y0, int $w, int $h): array {
    $minx = $x0 + $w; $miny = $y0 + $h; $maxx = $x0; $maxy = $y0;
    for ($y = $y0; $y < $y0 + $h; $y += 2) {
        for ($x = $x0; $x < $x0 + $w; $x += 2) {
            if (((imagecolorat($img, $x, $y) >> 24) & 0x7F) < 100) {
                $minx = min($minx, $x); $maxx = max($maxx, $x);
                $miny = min($miny, $y); $maxy = max($maxy, $y);
            }
        }
    }
    return [$minx, $miny, $maxx - $minx + 2, $maxy - $miny + 2];
}

function canvas(int $w, int $h): GdImage {
    $c = imagecreatetruecolor($w, $h);
    imagealphablending($c, false);
    imagefill($c, 0, 0, imagecolorallocatealpha($c, 0, 0, 0, 127));
    imagesavealpha($c, true);
    imagealphablending($c, true);
    return $c;
}

$mark = trim_box($logo, 0, 120, 1620, 1100);    // the box and its thread
$word = trim_box($logo, 0, 1240, 1620, 200);    // NFELIA

// Lockup: the mark 180 px high, the wordmark beside it, centred on it.
$mh = 180;
$mw = (int)round($mark[2] * $mh / $mark[3]);
$wh = 52;
$ww = (int)round($word[2] * $wh / $word[3]);
$gap = 28;
$lock = canvas($mw + $gap + $ww, $mh);
imagecopyresampled($lock, $logo, 0, 0, $mark[0], $mark[1], $mw, $mh, $mark[2], $mark[3]);
imagecopyresampled($lock, $logo, $mw + $gap, (int)(($mh - $wh) / 2), $word[0], $word[1], $ww, $wh, $word[2], $word[3]);
imagepng($lock, $root . '/assets/img/records/nfelia-lockup.png', 9);

// The mark alone, 96 px high, on a white tile so it reads over QR modules.
$th = 96;
$tw = (int)round($mark[2] * $th / $mark[3]);
$tile = canvas($tw + 16, $th + 16);
imagefilledrectangle($tile, 0, 0, $tw + 15, $th + 15, imagecolorallocate($tile, 255, 255, 255));
imagecopyresampled($tile, $logo, 8, 8, $mark[0], $mark[1], $tw, $th, $mark[2], $mark[3]);
imagepng($tile, $root . '/assets/img/records/nfelia-mark.png', 9);

echo "lockup ", imagesx($lock), "x", imagesy($lock), ", mark ", imagesx($tile), "x", imagesy($tile), "\n";
