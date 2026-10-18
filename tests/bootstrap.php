<?php
/**
 * Loads the app the way index.php does, without routing or a session, so
 * tests can call helpers directly. Uses the .env of the checkout it is in.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

define('NFELIA_TESTING', true);
$root = dirname(__DIR__);
if (is_file($root . '/vendor/autoload.php')) {
    require_once $root . '/vendor/autoload.php';
}
require_once $root . '/config/database.php';
require_once $root . '/config/app.php';
require_once $root . '/config/mail.php';
foreach (['functions', 'auth', 'token', 'mail', 'webpush', 'planner', 'shop_links', 'storefront', 'cloudflare',
          'shop_orders', 'bookings', 'seo', 'theme', 'nav', 'signin', 'passkeys', 'notify', 'screens'] as $helper) {
    require_once $root . '/app/helpers/' . $helper . '.php';
}
$_SERVER += ['REQUEST_METHOD' => 'GET', 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => parse_url(APP_URL, PHP_URL_HOST)];
