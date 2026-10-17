<?php
/**
 * Nfelia Application Configuration
 */

// --- PATCH: Session Security Hardening ---
ini_set('session.cookie_httponly', 1); // Prevents XSS from reading the cookie
ini_set('session.cookie_secure', 1);   // Forces cookies over HTTPS only
ini_set('session.use_strict_mode', 1); // Prevents session fixation
// -----------------------------------------

// Shops live on name.nfelia.com. A page on a subdomain may set cookies for
// the whole of nfelia.com, so the session cookie carries the __Host- prefix:
// browsers then accept it only from nfelia.com itself, over HTTPS, with no
// Domain attribute, and nothing on a shop subdomain can set or replace it.
// Renaming the cookie signs everyone out once; "keep me signed in" restores
// those who ticked it.
session_name('__Host-nfelia_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);

define('APP_NAME',    $_ENV['APP_NAME']    ?? 'Nfelia');
define('APP_URL',     rtrim($_ENV['APP_URL'] ?? 'https://nfelia.com', '/'));
define('APP_ENV',     $_ENV['APP_ENV']     ?? 'production');
define('APP_DEBUG',   ($_ENV['APP_DEBUG']  ?? 'false') === 'true');

// Document root for file paths
define('BASE_PATH',   dirname(__DIR__));
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('LOG_PATH',    BASE_PATH . '/storage/logs');

// Subscription pricing (in Kobo for Paystack — multiply NGN by 100)
define('PLAN_BASE_PRICE',       1000000);   // ₦10,000 in kobo
define('PLAN_EXTRA_SEAT_PRICE',  500000);   // ₦5,000 per extra seat
define('TRIAL_DAYS',             14);
define('MAX_SEATS',              5);
define('MAX_BANK_ACCOUNTS',      3);
define('MAX_PRODUCTS',           500);

// Web push (phone notifications). Generate the pair once with
// `php bin/vapid-keys.php`; leave empty and push stays switched off.
define('VAPID_PUBLIC_KEY',  trim($_ENV['VAPID_PUBLIC_KEY']  ?? ''));
define('VAPID_PRIVATE_KEY', trim($_ENV['VAPID_PRIVATE_KEY'] ?? ''));
define('VAPID_SUBJECT',     trim($_ENV['VAPID_SUBJECT']     ?? '') ?: 'mailto:' . ($_ENV['MAIL_FROM_ADDRESS'] ?? 'no-reply@nfelia.com'));

// Nigeria first. Every planner date (today, late, reminder times) is worked
// out in this zone, matching the database session (+01:00).
define('APP_TIMEZONE', 'Africa/Lagos');

// Document prefix for each type
define('DOC_TYPES', ['QUO', 'INV', 'REC', 'DEL']);

// Business categories
define('BUSINESS_CATEGORIES', [
    'fashion'          => 'Fashion & Apparel',
    'food_beverage'    => 'Food & Beverage',
    'tech_repair'      => 'Tech Repair',
    'distribution'     => 'Distribution & Logistics',
    'services'         => 'Professional Services',
    'wholesale_retail' => 'Wholesale & Retail',
    'creative'         => 'Creative & Media',
    'other'            => 'Other',
]);

// Font pairings for dynamic theming
define('FONT_PAIRINGS', [
    'corporate_serif' => [
        'heading' => 'EB Garamond',
        'body'    => 'Questrial',
    ],
    'modern_sans' => [
        'heading' => 'Inter',
        'body'    => 'DM Sans',
    ],
    'bold_display' => [
        'heading' => 'Syne',
        'body'    => 'Outfit',
    ],
    // These two are offered in Settings (SettingsController::FONT_OPTIONS) and
    // were missing here, so choosing them quietly showed Inter + DM Sans.
    'classic_tech' => [
        'heading' => 'Roboto Mono',
        'body'    => 'Roboto',
    ],
    'elegant_serif' => [
        'heading' => 'Playfair Display',
        'body'    => 'Lato',
    ],
]);