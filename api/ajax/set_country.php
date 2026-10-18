<?php
/**
 * Nfelia — Set Country AJAX
 * Phase 3 — Stores the business's country in session + 30-day cookie.
 * Called from the country selector in the app (layout and More). The country
 * decides the currency and how the business pays Nfelia, so only the owner
 * may change it, with the page's CSRF token.
 *
 * NFELIA FIX — Also syncs currency_symbol to business DB + session — 2026-06-04
 */

header('Content-Type: application/json');

if (!is_logged_in()) {
    json_response(['error' => 'Sign in first.'], 401);
}
if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) {
    json_response(['error' => 'Security session expired. Reload the page and try again.'], 403);
}
if (!is_owner()) {
    json_response(['error' => 'Only the owner can change the business\'s country.'], 403);
}

$db         = get_db();
$country_id = (int)($_POST['country_id'] ?? 0);

if (!$country_id) {
    http_response_code(400);
    echo json_encode(['error' => 'country_id required']);
    exit;
}

// Verify country exists and is active
$stmt = $db->prepare(
    "SELECT id, name, iso_code, flag_emoji, currency_code, currency_symbol, payment_method
     FROM countries WHERE id = ? AND is_active = 1"
);
$stmt->execute([$country_id]);
$country = $stmt->fetch();

if (!$country) {
    http_response_code(404);
    echo json_encode(['error' => 'Country not found or inactive']);
    exit;
}

// Store in session
$_SESSION['selected_country'] = $country;

// Store in 30-day cookie
setcookie('nfelia_country', (string)$country_id, [
    'expires'  => time() + (30 * 24 * 3600),
    'path'     => '/',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);

// Update the business's country_id AND currency_symbol
$biz_id = auth_business_id();
$db->prepare("UPDATE businesses SET country_id = ?, currency_symbol = ?, updated_at = NOW() WHERE id = ?")
   ->execute([$country_id, $country['currency_symbol'], $biz_id]);
update_business_session([
    'country_id'      => $country_id,
    'currency_symbol' => $country['currency_symbol'],
]);

echo json_encode(['success' => true, 'country' => $country]);
exit;
