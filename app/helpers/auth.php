<?php
/**
 * Nfelia — Auth Helpers
 */
function auth_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function auth_business(): ?array {
    return $_SESSION['business'] ?? null;
}

function auth_business_id(): ?int {
    $id = $_SESSION['business']['id'] ?? null;
    return $id !== null ? (int)$id : null;
}

function is_logged_in(): bool {
    return isset($_SESSION['user']['id']);
}

function is_owner(): bool {
    return ($_SESSION['user']['role'] ?? '') === 'owner';
}

function login_user(array $user, array $business): void {
    $_SESSION['user']     = $user;
    $_SESSION['business'] = $business;
    session_regenerate_id(true);
}

function update_business_session(array $fields): void {
    if (!isset($_SESSION['business'])) return;
    foreach ($fields as $key => $value) {
        $_SESSION['business'][$key] = $value;
    }
}

function logout_user(): void {
    $_SESSION = [];
    session_destroy();
    if (isset($_COOKIE['nfelia_remember'])) {
        setcookie('nfelia_remember', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
}

function csrf_verify(): void {
    if (
        empty($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])
    ) {
        flash('error', 'Security session expired. Please try again.');
        redirect('login');
    }
}

function csrf_regenerate(): void {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
/**
 * Record one request against a rate-limit bucket and report whether it is
 * within $max per rolling $window_minutes. Refused requests are not recorded,
 * so the window reopens on its own once the oldest request ages out.
 */
function throttle_allows(string $bucket, string $key, int $max, int $window_minutes): bool {
    $db   = get_db();
    $hash = hash('sha256', strtolower(trim($key)));

    $stmt = $db->prepare("
        SELECT COUNT(*) FROM auth_throttle
        WHERE bucket = ? AND key_hash = ? AND created_at > NOW() - INTERVAL " . (int)$window_minutes . " MINUTE
    ");
    $stmt->execute([$bucket, $hash]);
    if ((int)$stmt->fetchColumn() >= $max) {
        return false;
    }

    $db->prepare("INSERT INTO auth_throttle (bucket, key_hash) VALUES (?, ?)")->execute([$bucket, $hash]);

    // Housekeeping: no window is longer than a day.
    if (random_int(1, 50) === 1) {
        $db->exec("DELETE FROM auth_throttle WHERE created_at < NOW() - INTERVAL 1 DAY");
    }
    return true;
}

/**
 * Issue a one-time link token ('reset' or 'signin') for a user. Only its
 * SHA-256 hash is stored; the raw token exists in the emailed link alone.
 */
function account_token_issue(int $user_id, string $purpose, int $ttl_minutes): string {
    $token = bin2hex(random_bytes(32));
    get_db()->prepare("
        INSERT INTO account_tokens (user_id, purpose, token_hash, expires_at)
        VALUES (?, ?, ?, NOW() + INTERVAL " . (int)$ttl_minutes . " MINUTE)
    ")->execute([$user_id, $purpose, hash('sha256', $token)]);
    return $token;
}

/**
 * The active user a live (unused, unexpired) token belongs to, with the
 * token's own id as token_id, or null.
 */
function account_token_find(?string $token, string $purpose): ?array {
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    $stmt = get_db()->prepare("
        SELECT t.id AS token_id, u.id, u.full_name, u.email
        FROM account_tokens t
        JOIN users u ON u.id = t.user_id
        WHERE t.token_hash = ? AND t.purpose = ?
          AND t.used_at IS NULL AND t.expires_at > NOW()
          AND u.is_active = 1
    ");
    $stmt->execute([hash('sha256', $token), $purpose]);
    return $stmt->fetch() ?: null;
}

/**
 * Mark a token used. False when another request already used it, so a link
 * can never be redeemed twice even by two simultaneous clicks.
 */
function account_token_consume(int $token_id): bool {
    $stmt = get_db()->prepare("UPDATE account_tokens SET used_at = NOW() WHERE id = ? AND used_at IS NULL");
    $stmt->execute([$token_id]);
    return $stmt->rowCount() === 1;
}

/**
 * Run $job after the response has reached the browser, on servers that
 * support it (LiteSpeed and PHP-FPM do). Sending SMTP mail takes a second or
 * more; doing it inline would let anyone tell from the response time whether
 * an address has an account. Elsewhere it still runs, just before the
 * response finishes. Call session_write_close() before redirecting, so the
 * next request is not held up waiting for the session lock.
 */
function after_response(callable $job): void {
    register_shutdown_function(function () use ($job) {
        while (ob_get_level()) { ob_end_flush(); }
        if (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        } elseif (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        $job();
    });
}
