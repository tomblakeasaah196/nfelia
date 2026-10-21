<?php
/**
 * Loads the app the way index.php does, without routing or a session, so
 * tests can call helpers directly. Settings come from the checkout's .env;
 * real environment variables win over it (CI has no .env file).
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

define('NFELIA_TESTING', true);
$root = dirname(__DIR__);
if (is_file($root . '/vendor/autoload.php')) {
    require_once $root . '/vendor/autoload.php';
}
require_once $root . '/config/database.php';   // reads .env into $_ENV
foreach (['APP_ENV', 'APP_URL', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_ENCRYPTION',
          'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS', 'VAPID_PUBLIC_KEY', 'VAPID_PRIVATE_KEY', 'VAPID_SUBJECT', 'PAYSTACK_SECRET_KEY'] as $key) {
    if (getenv($key) !== false) {
        $_ENV[$key] = getenv($key);
    }
}
require_once $root . '/config/app.php';
require_once $root . '/config/mail.php';
foreach (['functions', 'auth', 'billing', 'stock', 'money', 'invoices', 'orders', 'signing', 'token', 'mail', 'webpush', 'planner', 'shop_links',
          'storefront', 'brand', 'maps', 'cloudflare', 'shop_orders', 'bookings', 'seo', 'theme', 'nav', 'signin', 'passkeys', 'notify', 'screens'] as $helper) {
    require_once $root . '/app/helpers/' . $helper . '.php';
}
require_once $root . '/app/helpers/errors.php';
require_once $root . '/app/helpers/view_as.php';
require_once $root . '/app/ops/ops.php';
require_once $root . '/app/ops/alerts.php';
require_once $root . '/app/ops/sales.php';
$_SERVER += ['REQUEST_METHOD' => 'GET', 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => parse_url(APP_URL, PHP_URL_HOST)];
