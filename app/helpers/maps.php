<?php
/**
 * Nfelia — Maps: where a shop is, for the map at the bottom of its pages
 *
 * The owner places a pin in Shop Studio (Sections › Map and directions): we
 * look their address up with Geoapify, or read the pin from a Google Maps
 * link they paste, and they drag it to the right spot. The pin is kept in
 * the shop's settings (the 'location' section), so it goes live on Publish.
 *
 * Shoppers see a picture of the map, made once by Geoapify's Static Maps
 * API, kept under uploads/maps/{business}/ and served by us from then on: no
 * map script and no third-party request on the shop, and the shop still
 * loads if Geoapify is down. "Get directions" opens Google Maps.
 *
 * .env:
 *   GEOAPIFY_API_KEY      for looking addresses up and making map pictures
 *                         (from the server). Without it there is no lookup and
 *                         no picture; a pin from a link still gives directions.
 *   GEOAPIFY_BROWSER_KEY  for the map Shop Studio shows while the pin is
 *                         placed (restrict it to nfelia.com); defaults to the
 *                         one above
 *   GEOAPIFY_API_URL, GEOAPIFY_MAPS_URL   only to point tests at a stand-in
 *
 * Map data © OpenStreetMap contributors, styles © OpenMapTiles; the free
 * plan asks for "Powered by Geoapify" with them. All three show on every map
 * picture and on Shop Studio's map.
 */

const MAPS_PICTURE_SIZE = [640, 360];   // CSS pixels; the picture is made at twice this
const MAPS_RETRY_AFTER  = 900;          // seconds before a failed picture is tried again

function maps_key(): string {
    return trim((string)($_ENV['GEOAPIFY_API_KEY'] ?? ''));
}

function maps_browser_key(): string {
    return trim((string)($_ENV['GEOAPIFY_BROWSER_KEY'] ?? '')) ?: maps_key();
}

function maps_api_url(): string {
    return rtrim((string)($_ENV['GEOAPIFY_API_URL'] ?? '') ?: 'https://api.geoapify.com', '/');
}

function maps_tiles_url(): string {
    return rtrim((string)($_ENV['GEOAPIFY_MAPS_URL'] ?? '') ?: 'https://maps.geoapify.com', '/');
}

/** Google Maps directions to a point; on a phone it opens the Maps app. */
function maps_directions_url(float $lat, float $lng): string {
    return 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode(maps_coord($lat) . ',' . maps_coord($lng));
}

function maps_coord(float $value): string {
    return rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
}

/**
 * A GET with short timeouts and a size limit. Returns [status, body,
 * Location header], or null when the request didn't complete.
 */
function maps_http_get(string $url, int $max_bytes = 2000000): ?array {
    if (!function_exists('curl_init')) {
        return null;
    }
    $body = '';
    $location = '';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; Nfelia/1.0; +https://nfelia.com)',
        CURLOPT_HTTPHEADER     => ['Accept-Language: en'],
        CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$location) {
            if (stripos($line, 'Location:') === 0) {
                $location = trim(substr($line, 9));
            }
            return strlen($line);
        },
        CURLOPT_WRITEFUNCTION  => function ($ch, $chunk) use (&$body, $max_bytes) {
            $body .= $chunk;
            return strlen($body) > $max_bytes ? 0 : strlen($chunk);   // stop reading past the limit
        },
    ]);
    if (str_starts_with($url, 'https://')) {
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
    }
    curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return $status > 0 ? [$status, $body, $location] : null;
}

/**
 * Places that match an address, best first: up to three, each
 * ['lat', 'lng', 'label']. Empty when there is no key or nothing matched.
 * $country is a two-letter code that keeps the search in one country.
 */
function maps_geocode(string $address, string $country = ''): array {
    $address = trim(preg_replace('/\s+/u', ' ', $address) ?? '');
    if ($address === '' || maps_key() === '') {
        return [];
    }
    $query = ['text' => mb_substr($address, 0, 300), 'format' => 'json', 'limit' => 3, 'apiKey' => maps_key()];
    if (preg_match('/^[a-z]{2}$/i', $country)) {
        $query['filter'] = 'countrycode:' . strtolower($country);
    }
    $answer = maps_http_get(maps_api_url() . '/v1/geocode/search?' . http_build_query($query), 500000);
    if (!$answer || $answer[0] !== 200) {
        error_log('[Nfelia] Address lookup failed: ' . ($answer[0] ?? 'no answer'));
        return [];
    }
    $found = [];
    foreach ((json_decode($answer[1], true)['results'] ?? []) as $place) {
        if (is_numeric($place['lat'] ?? null) && is_numeric($place['lon'] ?? null)) {
            $found[] = ['lat' => round((float)$place['lat'], 6), 'lng' => round((float)$place['lon'], 6),
                        'label' => mb_substr((string)($place['formatted'] ?? $address), 0, 200)];
        }
    }
    return $found;
}

/**
 * A pin read from what an owner pasted: a Google Maps or Apple Maps link,
 * a geo: link, or plain "6.4541, 3.3947". Returns ['lat', 'lng', 'zoom'
 * (or null)], or null when there is no pin in it. Nothing is fetched.
 */
function maps_parse_pin(string $text): ?array {
    $text = trim(rawurldecode(html_entity_decode(trim($text), ENT_QUOTES)));
    $num  = '(-?\d{1,3}(?:\.\d+)?)';
    $patterns = [
        '/!3d' . $num . '!4d' . $num . '/',                                                   // the place's own pin
        '/@' . $num . ',' . $num . '(?:,(\d+(?:\.\d+)?)z)?/',                                  // the map's centre
        '/[?&](?:q|query|ll|sll|daddr|destination|center|coordinate)=(?:loc:)?' . $num . ',\s*\+?' . $num . '/',
        '#/maps/(?:search|place|dir)/(?:[^/]*/)?' . $num . ',\s*\+?' . $num . '#',
        '/^geo:' . $num . ',' . $num . '/',
        '/^' . $num . '\s*,\s*' . $num . '$/',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $m)) {
            $lat = (float)$m[1];
            $lng = (float)$m[2];
            if (abs($lat) <= 90 && abs($lng) <= 180 && !($lat == 0.0 && $lng == 0.0)) {
                $zoom = isset($m[3]) && $m[3] !== '' ? (int)round((float)$m[3]) : null;
                return ['lat' => round($lat, 6), 'lng' => round($lng, 6), 'zoom' => $zoom];
            }
        }
    }
    return null;
}

/**
 * A pin from a pasted link, following short links (maps.app.goo.gl,
 * goo.gl/maps) to the full Google Maps address. Only Google's own hosts are
 * ever asked, over https, a few hops at most.
 */
function maps_resolve_pin(string $text): ?array {
    $pin = maps_parse_pin($text);
    if ($pin !== null) {
        return $pin;
    }
    $url = trim($text);
    for ($hop = 0; $hop < 5; $hop++) {
        // Google's short links, and google.com or a country's Google
        // (google.ng, google.co.uk, google.com.ng), on the standard port
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        $ok = str_starts_with($url, 'https://') && parse_url($url, PHP_URL_PORT) === null
           && preg_match('/^(maps\.app\.goo\.gl|goo\.gl|g\.co|(www\.|maps\.|consent\.)?google\.(com|[a-z]{2}|com?\.[a-z]{2}))$/', $host);
        if (!$ok) {
            return null;
        }
        // A consent page carries the real address in "continue"
        parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
        if (!empty($query['continue']) && is_string($query['continue'])) {
            $pin = maps_parse_pin($query['continue']);
            if ($pin !== null) {
                return $pin;
            }
        }
        $answer = maps_http_get($url, 400000);
        if (!$answer) {
            return null;
        }
        [$status, $body, $location] = $answer;
        if ($status >= 300 && $status < 400 && $location !== '') {
            $next = str_starts_with($location, '/') ? 'https://' . $host . $location : $location;
            $pin = maps_parse_pin($next);
            if ($pin !== null) {
                return $pin;
            }
            $url = $next;
            continue;
        }
        // Some answers are a page, not a redirect: look for the pin in it
        if (preg_match('/(?:center=|@|!3d)(-?\d{1,2}\.\d{3,})(?:%2C|,|!4d)(-?\d{1,3}\.\d{3,})/', $body, $m)) {
            return maps_parse_pin($m[1] . ',' . $m[2]);
        }
        return null;
    }
    return null;
}

/**
 * The map picture for a pin, in the shop's colours: a light or dark map with
 * the pin in the accent colour. Made the first time it is asked for, then
 * served as files. Returns ['src', 'srcset', 'w', 'h'], or null when there
 * is no key or Geoapify can't make it just now (tried again a little later).
 */
function maps_picture(int $business_id, float $lat, float $lng, int $zoom, bool $dark, string $accent): ?array {
    $accent = sf_hex($accent) ?? '#1A1A1A';
    [$w, $h] = MAPS_PICTURE_SIZE;
    $style = $dark ? 'dark-matter-brown' : 'positron';
    $name  = substr(sha1(implode('|', [maps_coord($lat), maps_coord($lng), $zoom, $style, $accent, $w, $h])), 0, 16);
    $dir   = 'uploads/maps/' . $business_id;
    $large = $dir . '/map-' . $name . '-' . ($w * 2) . '.webp';
    $small = $dir . '/map-' . $name . '-' . $w . '.webp';
    $done  = fn() => ['src' => sf_media_url($large), 'srcset' => sf_media_url($small) . ' ' . $w . 'w, ' . sf_media_url($large) . ' ' . ($w * 2) . 'w',
                      'w' => $w, 'h' => $h];
    if (is_file(BASE_PATH . '/' . $large) && is_file(BASE_PATH . '/' . $small)) {
        return $done();
    }
    $failed = BASE_PATH . '/' . $dir . '/map-' . $name . '.failed';
    if (maps_key() === '' || (is_file($failed) && time() - (int)filemtime($failed) < MAPS_RETRY_AFTER) || !function_exists('imagewebp')) {
        return null;
    }
    if (!is_dir(BASE_PATH . '/' . $dir)) {
        @mkdir(BASE_PATH . '/' . $dir, 0755, true);
    }

    // The style we want, else a plain one that every account has
    $image = null;
    $point = maps_coord($lng) . ',' . maps_coord($lat);
    foreach (array_unique([$style, 'osm-bright']) as $try) {
        // As Geoapify's own examples write it: separators as they are, the
        // colour's # as %23, its hex in small letters (#CA861A is refused:
        // "marker[0][2] does not match any of the allowed types")
        $query = 'style=' . $try . '&width=' . $w . '&height=' . $h . '&scaleFactor=2'
               . '&center=lonlat:' . $point . '&zoom=' . $zoom
               . '&marker=lonlat:' . $point . ';type:material;color:%23' . strtolower(substr($accent, 1)) . ';size:x-large'
               . '&apiKey=' . rawurlencode(maps_key());
        $answer = maps_http_get(maps_tiles_url() . '/v1/staticmap?' . $query, 6000000);
        if ($answer && $answer[0] === 200 && ($image = @imagecreatefromstring($answer[1]))) {
            break;
        }
        // Geoapify says why in its JSON ("message"); keep it with the status
        $said = $answer ? trim((string)(json_decode($answer[1], true)['message'] ?? '')) : '';
        error_log('[Nfelia] Map picture failed (' . $try . '): ' . ($answer[0] ?? 'no answer') . ($said !== '' ? ': ' . mb_substr($said, 0, 200) : ''));
    }
    if (!$image) {
        @touch($failed);
        return null;
    }

    $ok = maps_save_webp($image, BASE_PATH . '/' . $large, $w * 2, $h * 2) && maps_save_webp($image, BASE_PATH . '/' . $small, $w, $h);
    imagedestroy($image);
    if (!$ok) {
        @touch($failed);
        return null;
    }
    @unlink($failed);
    return $done();
}

/** One WebP copy of the map at this size, written whole or not at all. */
function maps_save_webp(GdImage $image, string $path, int $w, int $h): bool {
    $copy = imagecreatetruecolor($w, $h);
    imagecopyresampled($copy, $image, 0, 0, 0, 0, $w, $h, imagesx($image), imagesy($image));
    $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
    $ok  = @imagewebp($copy, $tmp, 82);
    imagedestroy($copy);
    if (!$ok || !@rename($tmp, $path)) {
        @unlink($tmp);
        return false;
    }
    return true;
}
