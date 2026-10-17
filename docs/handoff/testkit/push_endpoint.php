<?php
// A stand-in push service for cron_test.php:
//   PUSH_DIR=/some/dir php -S 127.0.0.1:8098 push_endpoint.php
// Saves every request it gets and answers by path, the way real push
// services do: /ok... 201 Created, /gone... 410 Gone, anything else 500.
$dir = getenv('PUSH_DIR') ?: sys_get_temp_dir() . '/push';
if (!is_dir($dir)) mkdir($dir, 0777, true);

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$n = count(glob($dir . '/*.json')) + 1;
file_put_contents($dir . '/' . $n . '.json', json_encode([
    'path'    => $path,
    'headers' => array_change_key_case(getallheaders(), CASE_LOWER),
    'body'    => base64_encode(file_get_contents('php://input')),
]));

http_response_code(str_starts_with($path, '/ok') ? 201 : (str_starts_with($path, '/gone') ? 410 : 500));
