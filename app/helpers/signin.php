<?php
/**
 * Signing in, staying signed in, and the lock.
 *
 * Every way in (password, emailed link, passkey) ends in signin_start(), so
 * a session always holds the same things. A signed-in session locks after
 * LOCK_AFTER_SECONDS without use: pages show the lock instead of the
 * business and the API answers 401 "locked", until the person shows it is
 * them again with a passkey or their password (api/ajax/auth.php).
 */

/**
 * An active user joined with everything the session needs from their
 * business, looked up by 'email' or 'id'. Null when there is none.
 */
function account_find(string $column, string|int $value): ?array
{
    $column = $column === 'id' ? 'u.id' : 'u.email';
    $stmt = get_db()->prepare("
        SELECT u.*,
               b.name as business_name, b.slug, b.doc_prefix,
               b.primary_color, b.secondary_color, b.logo_path,
               b.onboarding_complete, b.onboarding_step,
               b.currency_symbol, b.font_pairing,
               b.tagline, b.address, b.phone as business_phone, b.email as business_email,
               b.business_type, b.email_verified_at
        FROM users u
        JOIN businesses b ON b.id = u.business_id
        WHERE {$column} = ? AND u.is_active = 1
    ");
    $stmt->execute([$value]);
    return $stmt->fetch() ?: null;
}

/** Sign in a row from account_find(). */
function signin_start(array $user): void
{
    login_user(
        [
            'id'          => $user['id'],
            'full_name'   => $user['full_name'],
            'email'       => $user['email'],
            'role'        => $user['role'],
            'avatar_path' => $user['avatar_path'],
        ],
        [
            'id'                  => $user['business_id'],
            'name'                => $user['business_name'],
            'slug'                => $user['slug'],
            'doc_prefix'          => $user['doc_prefix'],
            'primary_color'       => $user['primary_color'],
            'secondary_color'     => $user['secondary_color'],
            'logo_path'           => $user['logo_path'],
            'tagline'             => $user['tagline'],
            'address'             => $user['address'],
            'phone'               => $user['business_phone'],
            'email'               => $user['business_email'],
            'onboarding_complete' => $user['onboarding_complete'],
            'onboarding_step'     => $user['onboarding_step'],
            'currency_symbol'     => $user['currency_symbol'],
            'font_pairing'        => $user['font_pairing'],
            'business_type'       => $user['business_type'],
            'email_verified_at'   => $user['email_verified_at'],
        ]
    );
    get_db()->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);

    unset($_SESSION['locked']);
    $_SESSION['last_active'] = time();
    // The first app page after this offers a passkey and the home-screen
    // app (views/layouts/app.php, assets/js/lock.js and shell.js).
    $_SESSION['just_signed_in'] = true;
}

/** "Keep me signed in": a 30-day token for this device. */
function signin_remember(array $user): void
{
    $token = bin2hex(random_bytes(64));
    $db = get_db();
    $db->prepare("
        INSERT INTO sessions (user_id, business_id, session_token, ip_address, user_agent, expires_at, last_active_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ")->execute([
        $user['id'],
        $user['business_id'],
        $token,
        $_SERVER['REMOTE_ADDR'] ?? null,
        substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500) ?: null,
        date('Y-m-d H:i:s', strtotime('+30 days')),
    ]);
    $_SESSION['remember_id'] = (int)$db->lastInsertId();

    setcookie('nfelia_remember', $token, [
        'expires'  => strtotime('+30 days'),
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/** Locked: unused for too long, or locked earlier and not opened since. */
function lock_engaged(): bool
{
    if (!is_logged_in()) {
        return false;
    }
    if (!empty($_SESSION['locked'])) {
        return true;
    }
    $last = (int)($_SESSION['last_active'] ?? 0);
    if ($last > 0 && time() - $last > LOCK_AFTER_SECONDS) {
        $_SESSION['locked'] = true;
        return true;
    }
    return false;
}

/**
 * Someone is using Nfelia: the lock moves back. Requests the page makes on
 * its own (marked X-Nfelia-Background) do not count.
 */
function lock_touch(): void
{
    if (($_SERVER['HTTP_X_NFELIA_BACKGROUND'] ?? '') === '1') {
        return;
    }
    $_SESSION['last_active'] = time();
    // A kept device notes it too, at most every 5 minutes, so that when it
    // comes back after a while it opens on the lock.
    if (!empty($_SESSION['remember_id']) && time() - (int)($_SESSION['last_active_saved'] ?? 0) > 300) {
        get_db()->prepare("UPDATE sessions SET last_active_at = NOW() WHERE id = ?")->execute([(int)$_SESSION['remember_id']]);
        $_SESSION['last_active_saved'] = time();
    }
}

/** The person showed it is them: open the lock, with a fresh session id. */
function lock_release(): void
{
    unset($_SESSION['locked']);
    session_regenerate_id(true);
    $_SESSION['last_active'] = time();
    unset($_SESSION['last_active_saved']);
    lock_touch();
}

/** Seconds until the lock, for the page's own timer. */
function lock_seconds_left(): int
{
    $last = (int)($_SESSION['last_active'] ?? time());
    return max(0, LOCK_AFTER_SECONDS - (time() - $last));
}
