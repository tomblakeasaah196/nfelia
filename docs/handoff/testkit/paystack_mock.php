<?php
// A stand-in for the Paystack API, for the local test kits. Run it with
//   php -S 127.0.0.1:8098 docs/handoff/testkit/paystack_mock.php
// and point the app at it in .env: PAYSTACK_API_BASE=http://127.0.0.1:8098
//
// initialize   remembers the payment and sends the browser straight back to
//              the callback, as if the customer paid
// verify       answers "success" with what was initialized; a test can
//              change the answer in /tmp/paystack_mock/override.json
//              ({"<reference>": {"amount": 100, "status": "failed"}})
// subscription GET returns an email token; disable says yes
// Every call is appended to /tmp/paystack_mock/calls.log.
$dir = sys_get_temp_dir() . '/paystack_mock';
@mkdir($dir, 0777, true);
$path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];
file_put_contents("$dir/calls.log", json_encode(['method' => $method, 'path' => $path, 'body' => $body]) . "\n", FILE_APPEND);
header('Content-Type: application/json');

$store = fn($ref) => "$dir/tx_" . preg_replace('/[^A-Za-z0-9_]/', '', $ref) . '.json';

if ($method === 'POST' && $path === '/transaction/initialize') {
    file_put_contents($store($body['reference']), json_encode($body));
    $callback = $body['callback_url'] . '?reference=' . urlencode($body['reference']);
    echo json_encode(['status' => true, 'data' => ['authorization_url' => $callback, 'reference' => $body['reference']]]);
    exit;
}
if ($method === 'GET' && preg_match('#^/transaction/verify/(.+)$#', $path, $m)) {
    $ref = urldecode($m[1]);
    if (!is_file($store($ref))) {
        http_response_code(404);
        echo json_encode(['status' => false, 'message' => 'Transaction reference not found']);
        exit;
    }
    $tx   = json_decode(file_get_contents($store($ref)), true);
    $data = [
        'status'        => 'success',
        'reference'     => $ref,
        'amount'        => $tx['amount'],
        'currency'      => $tx['currency'] ?? 'NGN',
        'metadata'      => json_decode($tx['metadata'] ?? '{}', true),
        'customer'      => ['customer_code' => 'CUS_mock', 'email' => $tx['email']],
        'authorization' => ['authorization_code' => 'AUTH_mock'],
    ];
    $over = is_file("$dir/override.json") ? (json_decode(file_get_contents("$dir/override.json"), true)[$ref] ?? []) : [];
    echo json_encode(['status' => true, 'data' => array_merge($data, $over)]);
    exit;
}
if ($method === 'GET' && preg_match('#^/subscription/(.+)$#', $path, $m)) {
    echo json_encode(['status' => true, 'data' => ['subscription_code' => urldecode($m[1]), 'email_token' => 'tok_mock']]);
    exit;
}
if ($method === 'POST' && $path === '/subscription/disable') {
    echo json_encode(['status' => true, 'message' => 'Subscription disabled successfully']);
    exit;
}
http_response_code(404);
echo json_encode(['status' => false, 'message' => 'Not in the mock']);
