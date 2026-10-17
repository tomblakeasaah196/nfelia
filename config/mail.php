<?php
/**
 * Nfelia Mail Configuration
 * Reads from .env — never hardcode credentials here
 */
define('MAIL_HOST',         $_ENV['MAIL_HOST']         ?? 'localhost');
define('MAIL_PORT',         (int)($_ENV['MAIL_PORT']   ?? 465));
define('MAIL_ENCRYPTION',   $_ENV['MAIL_ENCRYPTION']   ?? 'ssl');
define('MAIL_USERNAME',     $_ENV['MAIL_USERNAME']      ?? '');
define('MAIL_PASSWORD',     $_ENV['MAIL_PASSWORD']      ?? '');
define('MAIL_FROM_ADDRESS', $_ENV['MAIL_FROM_ADDRESS']  ?? 'no-reply@nfelia.com');
define('MAIL_FROM_NAME',    $_ENV['MAIL_FROM_NAME']     ?? 'Nfelia');

// SSL peer verification: auto-disable for localhost (safe), explicit control via .env
$auto_local = in_array(strtolower(MAIL_HOST), ['localhost', '127.0.0.1', '::1']);
define('MAIL_VERIFY_PEER', isset($_ENV['MAIL_VERIFY_PEER'])
    ? filter_var($_ENV['MAIL_VERIFY_PEER'], FILTER_VALIDATE_BOOLEAN)
    : !$auto_local
);