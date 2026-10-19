<?php
/**
 * Nfelia's QR codes: burgundy, with the Nfelia mark in the middle, for
 * anything a phone camera should be able to check (the cover of a Records
 * Book now; invoices and receipts next).
 *
 * The highest error correction (H, 30%) lets the mark sit over the middle
 * modules and the code still read; tests/ops/records_test.php reads every
 * code it makes back with the library's own reader to prove it.
 */

use chillerlan\QRCode\{QRCode, QROptions};
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\QRCode\Output\QRGdImagePNG;

const QR_INK = [139, 0, 74];        // brand burgundy #8B004A
const QR_DEEP = [94, 0, 50];        // deep #5E0032, for the three corner squares

/**
 * A QR code for $text as PNG bytes. $scale is the pixels per module (a
 * typical Records Book link makes a code about 37 modules wide, plus the
 * margin). $mark puts the Nfelia mark in the middle.
 */
function qr_png(string $text, int $scale = 8, bool $mark = true): string
{
    $options = new QROptions([
        'outputInterface' => QRGdImagePNG::class,
        'eccLevel'        => EccLevel::H,
        'scale'           => max(2, $scale),
        'outputBase64'    => false,
        'returnResource'  => true,
        'quietzoneSize'   => 2,
        'addLogoSpace'    => $mark,
        'logoSpaceWidth'  => $mark ? 11 : null,
        'logoSpaceHeight' => $mark ? 9 : null,
        'drawLightModules' => true,
        'moduleValues'    => [
            QRMatrix::M_FINDER_DARK    => QR_DEEP,
            QRMatrix::M_FINDER_DOT     => QR_DEEP,
            QRMatrix::M_ALIGNMENT_DARK => QR_DEEP,
            QRMatrix::M_DATA_DARK      => QR_INK,
            QRMatrix::M_TIMING_DARK    => QR_INK,
            QRMatrix::M_FORMAT_DARK    => QR_INK,
            QRMatrix::M_VERSION_DARK   => QR_INK,
            QRMatrix::M_DARKMODULE     => QR_INK,
            QRMatrix::M_SEPARATOR      => [255, 255, 255],
            QRMatrix::M_QUIETZONE      => [255, 255, 255],
            QRMatrix::M_LOGO           => [255, 255, 255],
            QRMatrix::M_NULL           => [255, 255, 255],
            QRMatrix::M_DATA           => [255, 255, 255],
            QRMatrix::M_FINDER         => [255, 255, 255],
            QRMatrix::M_ALIGNMENT      => [255, 255, 255],
            QRMatrix::M_TIMING         => [255, 255, 255],
            QRMatrix::M_FORMAT         => [255, 255, 255],
            QRMatrix::M_VERSION        => [255, 255, 255],
        ],
    ]);
    $qr = new QRCode($options);
    /** @var GdImage $image */
    $image = $qr->render($text);

    $file = BASE_PATH . '/assets/img/records/nfelia-mark.png';
    if ($mark && is_file($file) && ($logo = @imagecreatefrompng($file))) {
        // The space left for the mark, less a module of white all round
        $size = imagesx($image);
        $lw = ($options->logoSpaceWidth - 2) * $options->scale;
        $lh = ($options->logoSpaceHeight - 2) * $options->scale;
        $ratio = min($lw / imagesx($logo), $lh / imagesy($logo));
        $w = (int)round(imagesx($logo) * $ratio);
        $h = (int)round(imagesy($logo) * $ratio);
        imagealphablending($image, true);
        imagecopyresampled($image, $logo, (int)(($size - $w) / 2), (int)(($size - $h) / 2), 0, 0, $w, $h, imagesx($logo), imagesy($logo));
    }
    ob_start();
    imagepng($image, null, 9);
    return (string)ob_get_clean();
}

/** What a QR code in a PNG says, or null (the tests read back what they make). */
function qr_read(string $png): ?string
{
    try {
        return (string)(new QRCode())->readFromBlob($png);
    } catch (Throwable $e) {
        return null;
    }
}
