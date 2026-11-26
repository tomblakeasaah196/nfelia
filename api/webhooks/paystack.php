<?php
// Paystack's webhook. Reached as /api/webhooks/paystack through index.php,
// which loads the app first (dispatch_ajax). Opened directly as a file
// (/api/webhooks/paystack.php) nothing is loaded, so it is not found here,
// instead of crashing on a missing function.
if (!function_exists('get_db')) {
    http_response_code(404);
    exit;
}
// Bypass auth, but load the controller and execute the webhook logic
require_once __DIR__ . '/../../app/controllers/PaystackController.php';
(new PaystackController())->webhook();
