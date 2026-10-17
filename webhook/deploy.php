<?php
/**
 * Deploy webhook — receives an authenticated POST from GitHub Actions and
 * runs bin/deploy.sh. Lives inside the docroot at webhook/deploy.php, so it
 * is reachable at https://nfelia.com/webhook/deploy.php.
 *
 * It is served directly rather than through index.php because the site's
 * front-controller rule only rewrites paths that are NOT real files.
 *
 * Security model:
 *   - POST only.
 *   - Requires an X-Deploy-Token header matching DEPLOY_WEBHOOK_SECRET in
 *     the docroot .env. That file is never committed and never deployed
 *     over, so the secret exists only on the server.
 *   - Constant-time comparison (hash_equals) to defeat timing attacks.
 *   - Every rejected call is logged with client IP and truncated UA.
 *   - No shell metacharacter ever originates from the request: the endpoint
 *     runs one fixed command, with escapeshellarg on its path.
 *   - Output streams line by line so the Actions log shows live progress;
 *     bin/deploy.sh also mirrors everything to /home/smartqaq/deploy-nfelia.log
 *     so nothing is lost if the caller disconnects.
 */

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

/* ---- 1. Method whitelist ------------------------------------------------ */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit("method not allowed\n");
}

/* ---- 2. Expected token from .env — never hardcoded, never from request --- */
$envPath = dirname(__DIR__) . '/.env';
if (!is_file($envPath)) {
    http_response_code(500);
    exit(".env not found\n");
}

$expected = '';
foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
    [$k, $v] = explode('=', $line, 2);
    if (trim($k) === 'DEPLOY_WEBHOOK_SECRET') {
        $expected = trim($v, " \t\n\r\0\x0B\"'");
        break;
    }
}

if ($expected === '' || strlen($expected) < 32) {
    http_response_code(500);
    exit("DEPLOY_WEBHOOK_SECRET missing or shorter than 32 chars in .env\n");
}

/* ---- 3. Verify the caller, constant time -------------------------------- */
$provided = $_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? '';
if (!is_string($provided) || !hash_equals($expected, $provided)) {
    error_log(sprintf(
        '[webhook/deploy] rejected: bad or missing X-Deploy-Token from %s (ua=%s)',
        $_SERVER['REMOTE_ADDR'] ?? '?',
        substr((string)($_SERVER['HTTP_USER_AGENT'] ?? '?'), 0, 80)
    ));
    http_response_code(401);
    exit("unauthorized\n");
}

/* ---- 4. Locate the script and confirm we can run it --------------------- */
$deployScript = '/home/smartqaq/repositories/nfelia/bin/deploy.sh';
if (!is_file($deployScript)) {
    http_response_code(500);
    exit("bin/deploy.sh not found at {$deployScript}\n");
}

$disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
if (in_array('popen', $disabled, true) || !function_exists('popen')) {
    http_response_code(500);
    exit("popen() is disabled in PHP — cannot run the deploy script\n");
}

/* ---- 5. Run it, streaming output back ----------------------------------- */
@set_time_limit(900);
while (ob_get_level()) { ob_end_flush(); }
ob_implicit_flush(true);

echo "[webhook] starting deploy at " . date('c') . "\n";

$cmd    = '/usr/bin/env bash ' . escapeshellarg($deployScript) . ' 2>&1';
$handle = popen($cmd, 'r');

if ($handle === false) {
    http_response_code(500);
    exit("[webhook] failed to start the deploy script\n");
}

while (($line = fgets($handle)) !== false) {
    echo $line;
}
$exitCode = pclose($handle);

/* ---- 6. Final marker. GitHub Actions greps for this exact line ----------- *
 * The HTTP 200 is sent before the deploy finishes (output is streamed), so
 * the status code alone proves nothing. Only this marker does.             */
if ($exitCode === 0) {
    echo "[webhook] DEPLOY OK\n";
} else {
    echo "[webhook] DEPLOY FAILED (exit {$exitCode})\n";
}
