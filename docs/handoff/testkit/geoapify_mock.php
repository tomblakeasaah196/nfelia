<?php
// A stand-in for Geoapify, for the local test kits. Run it with
//   php -S 127.0.0.1:8099 docs/handoff/testkit/geoapify_mock.php
// and point the app at it in .env:
//   GEOAPIFY_API_KEY=test-key
//   GEOAPIFY_API_URL=http://127.0.0.1:8099
//   GEOAPIFY_MAPS_URL=http://127.0.0.1:8099
//
// /v1/geocode/search   an address with "Lafenwa" or "Abeokuta" is found (one
//                      place); "Lekki" gives two to choose from; anything
//                      else, nothing
// /v1/staticmap        a PNG of the asked size (times scaleFactor), with a
//                      dot where the marker is; style "broken" fails, so the
//                      fallback style is tried
// /v1/tile/…           a plain map tile, for Shop Studio's map
// Every call is appended to /tmp/geoapify_mock/calls.log; a call without
// apiKey=test-key gets 401.
$dir = sys_get_temp_dir() . '/geoapify_mock';
@mkdir($dir, 0777, true);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
file_put_contents("$dir/calls.log", json_encode(['path' => $path, 'query' => $_SERVER['QUERY_STRING'] ?? '']) . "\n", FILE_APPEND);

if (($_GET['apiKey'] ?? '') !== 'test-key') {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($path === '/v1/geocode/search') {
    header('Content-Type: application/json');
    $text = strtolower((string)($_GET['text'] ?? ''));
    $results = [];
    if (str_contains($text, 'lafenwa') || str_contains($text, 'abeokuta')) {
        $results[] = ['lat' => 7.1651187, 'lon' => 3.3359874, 'formatted' => 'Lafenwa Road, Abeokuta, Ogun State, Nigeria', 'rank' => ['confidence' => 0.9]];
    } elseif (str_contains($text, 'lekki')) {
        $results[] = ['lat' => 6.4474, 'lon' => 3.4723, 'formatted' => 'Lekki Phase 1, Lagos, Nigeria', 'rank' => ['confidence' => 0.7]];
        $results[] = ['lat' => 6.4698, 'lon' => 3.5852, 'formatted' => 'Lekki, Eti-Osa, Lagos, Nigeria', 'rank' => ['confidence' => 0.6]];
    }
    echo json_encode(['results' => $results]);
    exit;
}

if ($path === '/v1/staticmap') {
    if (($_GET['style'] ?? '') === 'broken') {
        http_response_code(400);
        exit;
    }
    $scale = max(1, (int)($_GET['scaleFactor'] ?? 1));
    $w = (int)($_GET['width'] ?? 600) * $scale;
    $h = (int)($_GET['height'] ?? 400) * $scale;
    $dark = str_contains((string)($_GET['style'] ?? ''), 'dark');
    $im = imagecreatetruecolor($w, $h);
    imagefill($im, 0, 0, $dark ? imagecolorallocate($im, 38, 34, 30) : imagecolorallocate($im, 236, 234, 228));
    $road = $dark ? imagecolorallocate($im, 70, 64, 56) : imagecolorallocate($im, 255, 255, 255);
    for ($i = 0; $i < 9; $i++) {
        imagesetthickness($im, 6 * $scale);
        imageline($im, 0, (int)($h * $i / 8), $w, (int)($h * ($i + 3) / 8), $road);
        imageline($im, (int)($w * $i / 8), 0, (int)($w * ($i - 2) / 8), $h, $road);
    }
    // The marker's colour, from marker=lonlat:…;color:%23RRGGBB;…
    preg_match('/color:#?([0-9a-fA-F]{6})/', (string)($_GET['marker'] ?? ''), $m);
    $hex = $m[1] ?? 'CC0000';
    $pin = imagecolorallocate($im, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
    imagefilledellipse($im, (int)($w / 2), (int)($h / 2) - 14 * $scale, 30 * $scale, 30 * $scale, $pin);
    header('Content-Type: image/png');
    imagepng($im);
    exit;
}

if (preg_match('#^/v1/tile/[a-z-]+/\d+/\d+/\d+(@2x)?\.png$#', $path, $m)) {
    $size = empty($m[1]) ? 256 : 512;
    $im = imagecreatetruecolor($size, $size);
    imagefill($im, 0, 0, imagecolorallocate($im, 236, 234, 228));
    imagesetthickness($im, 4);
    imageline($im, 0, (int)($size * 0.4), $size, (int)($size * 0.6), imagecolorallocate($im, 255, 255, 255));
    imageline($im, (int)($size * 0.3), 0, (int)($size * 0.7), $size, imagecolorallocate($im, 255, 255, 255));
    header('Content-Type: image/png');
    imagepng($im);
    exit;
}

http_response_code(404);
