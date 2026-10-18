<?php
/**
 * Nfelia — sign-in, lock and passkey API (JSON).
 *
 *   GET  state            signed in? locked? seconds until the lock
 *   POST ping             the person is using the page: move the lock back
 *   POST unlock           unlock with the password
 *   POST passkey-options  {purpose: create | unlock | login}
 *   POST passkey-register a new passkey for the signed-in person
 *   POST passkey-unlock   unlock with a passkey
 *   POST passkey-login    sign in with a passkey (the sign-in page)
 *   POST passkey-remove   {id}
 *
 * Every POST carries the session's CSRF token in X-CSRF-Token. This endpoint
 * is left open while locked (index.php), so it checks the lock itself.
 */

$action = $_GET['action'] ?? '';
$in = json_decode((string)file_get_contents('php://input'), true);
$in = is_array($in) ? $in : [];

function auth_api_fail(string $message, int $code = 400, array $extra = []): void
{
    json_response(['ok' => false, 'message' => $message] + $extra, $code);
}

if ($action === 'state') {
    json_response([
        'ok'       => true,
        'signedIn' => is_logged_in(),
        'locked'   => lock_engaged(),
        'left'     => is_logged_in() ? lock_seconds_left() : 0,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    auth_api_fail('Use POST.', 405);
}
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if ($token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    auth_api_fail('This page is out of date. Reload it and try again.', 403);
}

$user = auth_user();
$need_open = function () use ($user): void {
    if (!$user) {
        auth_api_fail('You are signed out. Sign in again.', 401, ['signedOut' => true]);
    }
    if (lock_engaged()) {
        header('X-Nfelia-Locked: 1');
        auth_api_fail('Nfelia is locked. Unlock it to carry on.', 401, ['locked' => true]);
    }
};
$need_signed_in = function () use ($user): void {
    if (!$user) {
        auth_api_fail('You are signed out. Sign in again.', 401, ['signedOut' => true]);
    }
};

try {
    switch ($action) {

        case 'ping':
            $need_open();
            lock_touch();
            json_response(['ok' => true, 'left' => lock_seconds_left()]);

        case 'unlock':
            $need_signed_in();
            if (!throttle_allows('unlock', 'user:' . $user['id'], 8, 15)) {
                // Someone is guessing. End the session; the owner signs in again.
                if (isset($_COOKIE['nfelia_remember'])) {
                    get_db()->prepare("DELETE FROM sessions WHERE session_token = ?")->execute([$_COOKIE['nfelia_remember']]);
                }
                logout_user();
                auth_api_fail('Too many tries. You have been signed out; sign in again.', 429, ['signedOut' => true]);
            }
            $account = account_find('id', (int)$user['id']);
            if (!$account) {
                logout_user();
                auth_api_fail('This account is no longer active.', 401, ['signedOut' => true]);
            }
            if (!password_verify((string)($in['password'] ?? ''), $account['password_hash'])) {
                auth_api_fail('That password doesn’t match. Try again, or sign out and use Forgot password.');
            }
            lock_release();
            json_response(['ok' => true, 'left' => lock_seconds_left()]);

        case 'passkey-options':
            $purpose = (string)($in['purpose'] ?? '');
            if ($purpose === 'create') {
                $need_open();
                json_response(['ok' => true, 'options' => passkey_create_options($user)]);
            }
            if ($purpose === 'unlock') {
                $need_signed_in();
                if (!passkey_ids_for((int)$user['id'])) {
                    auth_api_fail('No passkey is set up for this account yet.');
                }
                json_response(['ok' => true, 'options' => passkey_get_options((int)$user['id'], 'unlock')]);
            }
            if ($purpose === 'login') {
                json_response(['ok' => true, 'options' => passkey_get_options(null, 'login')]);
            }
            auth_api_fail('Unknown request.');

        case 'passkey-register':
            $need_open();
            $added = passkey_register($user, $in);
            $where = $added['name'] === 'This device' ? 'this device' : 'this ' . $added['name'];
            json_response(['ok' => true, 'passkey' => $added, 'message' => 'Passkey added on ' . $where . '.']);

        case 'passkey-unlock':
            $need_signed_in();
            passkey_verify($in, 'unlock', (int)$user['id']);
            lock_release();
            json_response(['ok' => true, 'left' => lock_seconds_left()]);

        case 'passkey-login':
            if (!throttle_allows('passkey', 'ip:' . ($_SERVER['REMOTE_ADDR'] ?? ''), 30, 15)) {
                auth_api_fail('Too many tries from this network. Wait a few minutes and try again.', 429);
            }
            $row = passkey_verify($in, 'login', null);
            $account = account_find('id', (int)$row['user_id']);
            if (!$account) {
                auth_api_fail('This account is no longer active. Ask the business owner.', 403);
            }
            signin_start($account);
            if (!empty($in['remember'])) {
                signin_remember($account);
            }
            json_response(['ok' => true, 'redirect' => APP_URL . '/' . signin_destination($in['next'] ?? null)]);

        case 'passkey-remove':
            $need_open();
            $stmt = get_db()->prepare("DELETE FROM user_passkeys WHERE id = ? AND user_id = ?");
            $stmt->execute([(int)($in['id'] ?? 0), (int)$user['id']]);
            if ($stmt->rowCount() !== 1) {
                auth_api_fail('That passkey was already removed.', 404);
            }
            json_response(['ok' => true]);

        default:
            auth_api_fail('Unknown request.', 404);
    }
} catch (\lbuchs\WebAuthn\WebAuthnException $e) {
    error_log('Passkey check failed: ' . $e->getMessage());
    auth_api_fail('Your phone could not confirm it is you. Try again, or use your password.');
} catch (RuntimeException $e) {
    auth_api_fail($e->getMessage());
}
