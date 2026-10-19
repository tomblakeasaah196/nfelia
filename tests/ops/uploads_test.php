<?php
/**
 * Nfelia — uploads: telling a file's type apart without the fileinfo
 * extension the host's PHP lacks (#1543), and saving photos as fast, light
 * JPEGs so the upload pages aren't slow (#1540, #1541).
 *
 *   bash tests/notifications/run.sh ops/uploads
 *
 * A unit check: it calls the helpers in app/helpers/functions.php directly
 * (tests/bootstrap.php loads them), touches no database, and cleans up after
 * itself. CI runs with fileinfo present, so nf_mime_from_header() — the
 * stand-in's own reading of the file — is tested on its own, as it runs on
 * the host where fileinfo is missing.
 */
require __DIR__ . '/lib.php';
t_guard();

$tmp = sys_get_temp_dir() . '/nf_uploads_test_' . bin2hex(random_bytes(4));
@mkdir($tmp, 0755, true);
register_shutdown_function(function () use ($tmp) {
    foreach (glob($tmp . '/*') ?: [] as $f) @unlink($f);
    @rmdir($tmp);
});

/* A real little file of each kind the uploads accept. */
$png = $tmp . '/a.png';  imagepng(imagecreatetruecolor(8, 8), $png);
$jpg = $tmp . '/a.jpg';  imagejpeg(imagecreatetruecolor(8, 8), $jpg);
$gif = $tmp . '/a.gif';  imagegif(imagecreatetruecolor(8, 8), $gif);
$pdf = $tmp . '/a.pdf';  file_put_contents($pdf, "%PDF-1.4\n% a tiny but honest PDF header\n");
$mp4 = $tmp . '/a.mp4';  file_put_contents($mp4, "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom");
$txt = $tmp . '/a.txt';  file_put_contents($txt, 'just words, not a file type we take');
$webp = $tmp . '/a.webp';
if (function_exists('imagewebp')) { @imagewebp(imagecreatetruecolor(8, 8), $webp); }

section('Type is read from the file itself, with no fileinfo extension (#1543)');

eq(nf_mime_from_header($png), 'image/png',       'a PNG is a PNG');
eq(nf_mime_from_header($jpg), 'image/jpeg',      'a JPEG is a JPEG');
eq(nf_mime_from_header($gif), 'image/gif',       'a GIF is a GIF');
eq(nf_mime_from_header($pdf), 'application/pdf', 'a PDF is a PDF, by its header');
eq(nf_mime_from_header($mp4), 'video/mp4',       'an MP4 video is an MP4, by its ftyp box');
eq(nf_mime_from_header($txt), '',                'plain text is nothing we take');
eq(nf_mime_from_header($tmp . '/not-here'), '',  'a file that is not there is nothing');
if (function_exists('imagewebp') && is_file($webp) && filesize($webp) > 0) {
    eq(nf_mime_from_header($webp), 'image/webp', 'a WebP is a WebP');
}

// The uploads call mime_content_type() (the real one when fileinfo is there,
// our stand-in when it is not); either way they get a type for what they take.
eq(mime_content_type($jpg), 'image/jpeg',      'mime_content_type() reads a JPEG');
eq(mime_content_type($pdf), 'application/pdf', 'mime_content_type() reads a PDF');

section('Hero and post photos are saved as light JPEGs, bounded in size (#1540, #1541)');

// A wide source, like a phone photo: 5200px is more than 3x the 1600 cap, so
// the fast pre-shrink path runs.
$big = imagecreatetruecolor(5200, 1000);
imagefilledrectangle($big, 0, 0, 5199, 999, imagecolorallocate($big, 120, 180, 220));
$bigpath = $tmp . '/big.jpg';
imagejpeg($big, $bigpath, 92);
imagedestroy($big);

$out = $tmp . '/out_post.jpg';
ok(save_image(['tmp_name' => $bigpath, 'size' => filesize($bigpath)], $out, 1600, 'jpg'), 'a big photo saves');
$info = @getimagesize($out);
eq($info['mime'] ?? '', 'image/jpeg', 'saved as a JPEG, not a heavy PNG');
ok(($info[0] ?? 99999) <= 1600, 'no wider than the 1600px cap (got ' . ($info[0] ?? '?') . ')');

// The hero path (save_media) keeps a photo as a .jpg now, not a .png.
$hero = save_media(['tmp_name' => $bigpath, 'size' => filesize($bigpath), 'type' => 'image/jpeg', 'error' => 0], $tmp, 'hero_test');
ok(is_array($hero) && ($hero['type'] ?? '') === 'image', 'save_media takes a hero photo');
ok(is_array($hero) && str_ends_with((string)($hero['path'] ?? ''), '.jpg'), 'and keeps it as a .jpg (got ' . ($hero['path'] ?? 'null') . ')');

done();
