<?php
// Serves the app the way .htaccess does, for `php -S` in the notification
// tests:  php -S 127.0.0.1:8099 -t . tests/notifications/router.php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = $_SERVER['DOCUMENT_ROOT'] . $path;
if ($path !== '/' && is_file($file)) return false;
$_GET['url'] = ltrim($path, '/');
chdir($_SERVER['DOCUMENT_ROOT']);
require $_SERVER['DOCUMENT_ROOT'] . '/index.php';
