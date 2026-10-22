<?php
/**
 * A page that isn't here: an old link, something deleted, a mistyped
 * address. Someone signed in sees it in their business's colour, with the
 * way back to their dashboard; anyone else sees Nfelia's, with the way to
 * Nfelia. A shop's own missing pages are StorefrontController's.
 * Shown by not_found() (index.php) and PublicController::index().
 */
if (!defined('BASE_PATH')) {
    // Shown by the web server itself (.htaccess ErrorDocument), outside the
    // app: nobody is signed in here, and Nfelia's page needs only these.
    define('BASE_PATH', dirname(__DIR__));
    define('APP_URL', '');   // links stay on this site
    require_once BASE_PATH . '/app/helpers/theme.php';
    function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
    function errors_script_tag(): string { return ''; }
    function is_logged_in(): bool { return false; }
}
$signed_in = is_logged_in();
$business  = $signed_in ? (auth_business() ?? []) : [];
$notice = [
    'status'       => 404,
    'shop_name'    => $business['name'] ?? null,
    'title'        => "This page isn't here",
    'body'         => $signed_in ? 'The link may be old, or what it opened has been deleted.' : 'The link may be old or mistyped.',
    'action_url'   => APP_URL . ($signed_in ? '/dashboard' : '/'),
    'action_label' => $signed_in ? 'Go to your dashboard' : 'Go to Nfelia',
    'colour'       => theme_tokens($business['primary_color'] ?? null, null)['accent'],
];
require BASE_PATH . '/views/public/notice.php';
