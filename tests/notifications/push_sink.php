<?php
/**
 * A stand-in push service for the notification tests, one per run:
 *   PUSH_DIR=/some/dir php -S 127.0.0.1:8098 push_sink.php
 * Saves every request it gets (path, headers, body) and answers by the
 * first part of the path, the way real push services answer:
 *   /ok…    201 Created          /gone…  410 Gone (the phone unsubscribed)
 *   /busy…  503 (try later)      /bad…   400 (refused)
 */
$dir = getenv('PUSH_DIR') ?: sys_get_temp_dir() . '/push';
if (!is_dir($dir)) mkdir($dir, 0777, true);

$path = (string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$lock = fopen($dir . '/.lock', 'c');
flock($lock, LOCK_EX);
$n = count(glob($dir . '/*.json')) + 1;
file_put_contents($dir . '/' . $n . '.json', json_encode([
    'path'    => $path,
    'headers' => array_change_key_case(getallheaders(), CASE_LOWER),
    'body'    => base64_encode(file_get_contents('php://input')),
]));
flock($lock, LOCK_UN);

http_response_code(match (true) {
    str_starts_with($path, '/ok')   => 201,
    str_starts_with($path, '/gone') => 410,
    str_starts_with($path, '/busy') => 503,
    default                         => 400,
});
