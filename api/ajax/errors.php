<?php
/**
 * POST /api/errors — a script error in someone's browser (assets/js/errors.js),
 * from the app, the landing page or a shop. Written down like any other error
 * (app/helpers/errors.php), as kind "js".
 *
 * Anyone can send one, so it is held to little: 16 KB, 60 a network in ten
 * minutes, and only errors from Nfelia's own pages and scripts. Browser
 * extensions, other sites' scripts and "Script error." (which says nothing)
 * are dropped. Always answers 204: the page never waits on it.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
$raw = (string)file_get_contents('php://input', false, null, 0, 16384);
$in  = json_decode($raw, true);
http_response_code(204);
header_remove('Content-Type');
if (!is_array($in) || !throttle_allows('js_errors', client_ip(), 60, 10)) {
    exit;
}

// Chrome says "Uncaught TypeError: …", Firefox "TypeError: …": one problem either way.
$message = trim(preg_replace('/^Uncaught\s+/', '', mb_substr((string)($in['message'] ?? ''), 0, 1000)) ?? '');
$file    = mb_substr((string)($in['file'] ?? ''), 0, 500);
$page    = mb_substr((string)($in['page'] ?? ''), 0, 1000);
$stack   = mb_substr((string)($in['stack'] ?? ''), 0, 8000);

// Ours: the app, a shop (name.<host>), Ops, or this machine when testing.
$base = shop_base_host();
$ours = function (string $url) use ($base): bool {
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    return $host !== '' && ($host === $base || str_ends_with($host, '.' . $base) || $host === 'www.' . $base);
};
if ($message === '' || preg_match('/^(Script error\.?|ResizeObserver loop)/i', $message)
    || !$ours($page)
    || ($file !== '' && !$ours($file))
    || preg_match('#(chrome|moz|safari(-web)?)-extension://|webkit-masked-url#i', $file . ' ' . $stack)) {
    exit;
}

$page_host = strtolower((string)parse_url($page, PHP_URL_HOST));
$shop      = shop_label_from_host($page_host);
$business  = $shop !== null ? shop_find_by_name($shop) : null;
[$path]    = errors_clean_path((string)parse_url($page, PHP_URL_PATH) . (parse_url($page, PHP_URL_QUERY) ? '?' . parse_url($page, PHP_URL_QUERY) : ''));
$script    = $file !== '' ? (string)parse_url($file, PHP_URL_PATH) : '/' . $path;

errors_context_set(['not_slow' => true]);
errors_capture([
    'kind'        => 'js',
    'level'       => 'error',
    'message'     => ($in['kind'] ?? '') === 'rejection' ? 'Unhandled promise: ' . $message : $message,
    'file'        => mb_substr($script, 0, 255) ?: null,
    'line'        => isset($in['line']) ? (int)$in['line'] : null,
    'trace'       => $stack !== '' ? $stack : null,
    'source'      => $shop !== null ? 'shop' : (ops_is_host($page_host) ? 'ops' : 'browser'),
    'business_id' => $shop !== null ? ($business ? (int)$business['id'] : null) : (auth_business_id() ?: null),
    'user_id'     => $shop !== null ? null : (auth_user()['id'] ?? null),
    'method'      => 'GET',
    'path'        => mb_substr(($shop !== null || ops_is_host($page_host) ? $page_host . ' ' : '') . '/' . $path, 0, 255),
]);
exit;
