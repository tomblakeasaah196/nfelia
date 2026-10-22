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

/**
 * Re-read the signed-in person: false (and signed out) when they were removed
 * or switched off, so their seat is really free at once. What the session
 * keeps of them and of the business follows the database on every request
 * too: a role, name, email or photo, and the business's name, logo, colours
 * and currency, changed on another device or by someone else on the team,
 * show on the next page here, not only after signing in again.
 */
function auth_still_member(): bool {
    $db = get_db();
    // SELECT *, not a column list: the code can arrive a minute before the
    // migration that adds session_epoch, and that minute must not sign
    // everyone out.
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND business_id = ?");
    $stmt->execute([(int)(auth_user()['id'] ?? 0), (int)auth_business_id()]);
    $me = $stmt->fetch();
    if (!$me || !(int)$me['is_active']) {
        logout_user();
        return false;
    }
    // A changed password (or "sign out everywhere") moves the epoch on;
    // every session started before it ends here, on its next request.
    if ((int)($me['session_epoch'] ?? 0) !== (int)($_SESSION['user']['session_epoch'] ?? 0)) {
        logout_user();
        return false;
    }
    foreach (['role', 'full_name', 'email', 'avatar_path'] as $key) {
        $_SESSION['user'][$key] = $me[$key];
    }
    // Only what the session already holds of the business: Settings and
    // Shop Studio load the rest of the row themselves.
    $stmt = $db->prepare("SELECT * FROM businesses WHERE id = ?");
    $stmt->execute([(int)auth_business_id()]);
    if ($business = $stmt->fetch()) {
        update_business_session(array_intersect_key($business, $_SESSION['business'] ?? []));
    }
    return true;
}

/**
 * The password changed (or the person asked to be signed out everywhere):
 * every other session and every "keep me signed in" token of theirs stops
 * working. The session doing it carries on when $keep_current is true.
 */
function session_epoch_bump(int $user_id, bool $keep_current = true): void {
    $db = get_db();
    $db->prepare("UPDATE users SET session_epoch = session_epoch + 1 WHERE id = ?")->execute([$user_id]);
    $stmt = $db->prepare("SELECT session_epoch FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $epoch = (int)$stmt->fetchColumn();
    $mine  = (int)(auth_user()['id'] ?? 0) === $user_id;
    if ($mine && $keep_current) {
        $_SESSION['user']['session_epoch'] = $epoch;
        session_regenerate_id(true);
        $keep_row = (int)($_SESSION['remember_id'] ?? 0);
        $db->prepare("DELETE FROM sessions WHERE user_id = ? AND id <> ?")->execute([$user_id, $keep_row]);
    } else {
        $db->prepare("DELETE FROM sessions WHERE user_id = ?")->execute([$user_id]);
    }
}

/**
 * Forget one "keep me signed in" token, as the browser sent it. Tokens are
 * kept hashed; a token saved before they were is matched as it is.
 */
function remember_forget(string $raw): void {
    if ($raw === '') return;
    get_db()->prepare("DELETE FROM sessions WHERE session_token IN (?, ?)")->execute([remember_hash($raw), $raw]);
}

/** How a "keep me signed in" token is kept: only its SHA-256, never the token. */
function remember_hash(string $raw): string {
    return hash('sha256', $raw);
}

/** Owner or admin: who the menu shows the money pages and the shop to. */
function is_boss(): bool {
    return in_array($_SESSION['user']['role'] ?? '', ['owner', 'admin'], true);
}

/**
 * The menu's rule, checked by the page itself, so typing the address doesn't
 * get round it. Anyone else goes back to Home (scripts get a 403).
 */
function require_boss(string $what = 'open that page'): void {
    if (is_boss()) {
        return;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || is_ajax() || str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
        json_response(['error' => 'Only the owner or an admin can ' . $what . '.'], 403);
    }
    flash('error', 'Only the owner or an admin can ' . $what . '.');
    redirect('dashboard');
}

/**
 * The owner of Nfelia's own business. The team's pages live in Nfelia Ops
 * (ops.nfelia.com) now, with its own accounts; this only lets that owner set
 * up Ops' first admin, and sends their old /admin links there
 * (AdminController).
 */
const FOUNDER_BUSINESS_ID = 1;
function is_platform_admin(): bool {
    return auth_business_id() === FOUNDER_BUSINESS_ID && is_owner();
}

/**
 * Whether the signed-in business has verified its email. The session copy
 * can only say yes: until it does, the database is asked, because the link
 * is often opened on another device (the phone the email went to) and this
 * session would never hear of it.
 */
function is_email_verified(): bool {
    if (!empty($_SESSION['business']['email_verified_at'])) {
        return true;
    }
    $stmt = get_db()->prepare("SELECT email_verified_at FROM businesses WHERE id = ?");
    $stmt->execute([auth_business_id()]);
    $verified_at = $stmt->fetchColumn();
    if (!$verified_at) {
        return false;
    }
    update_business_session(['email_verified_at' => $verified_at]);
    return true;
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
    foreach ([REMEMBER_COOKIE, 'nfelia_remember'] as $cookie) {
        if (isset($_COOKIE[$cookie])) {
            setcookie($cookie, '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'secure'   => true,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
    }
}

function csrf_verify(): void {
    // A form bigger than post_max_size arrives empty, token and all: the
    // file was too big, the session is fine. Say so, and go back to the page.
    $post_max = ini_bytes('post_max_size');
    if (empty($_POST) && $post_max > 0 && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > $post_max) {
        $mb      = upload_limit_mb();
        $message = 'Nothing was saved: that file is too big' . ($mb ? ' (the most is ' . $mb . ' MB)' : '') . '. Pick a smaller one and try again.';
        if (is_ajax() || str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
            json_response(['error' => $message], 413);
        }
        flash('error', $message);
        redirect(back_path());
    }
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
 * A one-time token for a form that records money (a payment, a refund, a
 * new order): a second tap on a slow network, or the browser sending it
 * again, finds the token used and saves nothing twice. Returns the hidden
 * field. A form posted without one still goes through.
 */
function form_once(): string {
    $t = 'f' . bin2hex(random_bytes(12));
    $_SESSION['form_once'] = array_slice(($_SESSION['form_once'] ?? []) + [$t => time()], -40, null, true);
    return '<input type="hidden" name="once" value="' . $t . '">';
}

/** True when this post's one-time token was already used; uses it otherwise. */
function form_once_used(): bool {
    $t = (string)($_POST['once'] ?? '');
    if ($t === '') return false;
    if (!isset($_SESSION['form_once'][$t])) return true;
    unset($_SESSION['form_once'][$t]);
    return false;
}
/**
 * The visitor's IP address. Behind Cloudflare, REMOTE_ADDR is Cloudflare's
 * edge, so its CF-Connecting-IP header is used, but only when the request
 * really came from Cloudflare (anyone can send the header straight to the
 * server).
 */
function client_ip(): string {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    $cf     = trim($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '');
    if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP) && ip_is_cloudflare($remote)) {
        return $cf;
    }
    return $remote;
}

/** Is this address one of Cloudflare's (https://www.cloudflare.com/ips/)? */
function ip_is_cloudflare(string $ip): bool {
    static $ranges = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
        '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
        '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
        '2a06:98c0::/29', '2c0f:f248::/32',
    ];
    $bin = @inet_pton($ip);
    if ($bin === false) return false;
    foreach ($ranges as $range) {
        [$net, $bits] = explode('/', $range);
        $net_bin = inet_pton($net);
        if (strlen($net_bin) !== strlen($bin)) continue;
        $bytes = intdiv((int)$bits, 8);
        $rest  = (int)$bits % 8;
        if (substr($bin, 0, $bytes) !== substr($net_bin, 0, $bytes)) continue;
        if ($rest === 0) return true;
        $mask = chr((0xFF << (8 - $rest)) & 0xFF);
        if (($bin[$bytes] & $mask) === ($net_bin[$bytes] & $mask)) return true;
    }
    return false;
}

/**
 * An email as Gmail delivers it: dots and anything after a + in the name
 * don't count, and googlemail.com is gmail.com. Other addresses are just
 * lower-cased.
 */
function email_key(string $email): string {
    $email = strtolower(trim($email));
    [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
    if (in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
        return str_replace('.', '', explode('+', $name, 2)[0]) . '@gmail.com';
    }
    return $email;
}

/** Does a business owner already sign in with this address, or a Gmail variant of it? */
function email_owner_exists(PDO $db, string $email): bool {
    $key = email_key($email);
    if (!str_ends_with($key, '@gmail.com')) {
        $stmt = $db->prepare("SELECT 1 FROM users WHERE role = 'owner' AND LOWER(email) = ? LIMIT 1");
        $stmt->execute([$key]);
        return (bool)$stmt->fetchColumn();
    }
    $stmt = $db->prepare("
        SELECT 1 FROM users
        WHERE role = 'owner' AND (LOWER(email) LIKE '%@gmail.com' OR LOWER(email) LIKE '%@googlemail.com')
          AND CONCAT(REPLACE(SUBSTRING_INDEX(SUBSTRING_INDEX(LOWER(email), '@', 1), '+', 1), '.', ''), '@gmail.com') = ?
        LIMIT 1
    ");
    $stmt->execute([$key]);
    return (bool)$stmt->fetchColumn();
}

/**
 * A removed team member's login keeps its row (their name stays on what they
 * did) but gives up the address, so the person can be added again or sign up
 * for their own business. Owners are never freed this way.
 */
function free_removed_email(PDO $db, int $user_id): void {
    $db->prepare("
        UPDATE users SET email = LEFT(CONCAT('removed.', id, '.', email), 255)
        WHERE id = ? AND is_active = 0 AND role <> 'owner'
    ")->execute([$user_id]);
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

/** How many requests a bucket has seen in the last $window_minutes, recording nothing. */
function throttle_count(string $bucket, string $key, int $window_minutes): int {
    $stmt = get_db()->prepare("
        SELECT COUNT(*) FROM auth_throttle
        WHERE bucket = ? AND key_hash = ? AND created_at > NOW() - INTERVAL " . (int)$window_minutes . " MINUTE
    ");
    $stmt->execute([$bucket, hash('sha256', strtolower(trim($key)))]);
    return (int)$stmt->fetchColumn();
}

/** Record one request against a bucket (a failed sign-in, say) without deciding anything. */
function throttle_hit(string $bucket, string $key): void {
    get_db()->prepare("INSERT INTO auth_throttle (bucket, key_hash) VALUES (?, ?)")
            ->execute([$bucket, hash('sha256', strtolower(trim($key)))]);
}

/** Forget a bucket's requests: the right password after a few wrong ones starts the count again. */
function throttle_clear(string $bucket, string $key): void {
    get_db()->prepare("DELETE FROM auth_throttle WHERE bucket = ? AND key_hash = ?")
            ->execute([$bucket, hash('sha256', strtolower(trim($key)))]);
}

/* ── Passwords ─────────────────────────────────────────────── */

const PASSWORD_MIN = 9;

/**
 * The rules a new password must meet, in the order the form lists them.
 * Each has a key (the form's checklist uses it), the words shown, and a
 * test. Letters and digits from any script count.
 */
function password_rules(): array {
    return [
        'length' => ['label' => 'At least ' . PASSWORD_MIN . ' characters',  'test' => fn(string $p) => mb_strlen($p) >= PASSWORD_MIN],
        'upper'  => ['label' => 'A capital letter (A to Z)',                 'test' => fn(string $p) => (bool)preg_match('/\p{Lu}/u', $p)],
        'number' => ['label' => 'A number (0 to 9)',                         'test' => fn(string $p) => (bool)preg_match('/\p{N}/u', $p)],
        'symbol' => ['label' => 'A symbol, like ! ? # or @',                 'test' => fn(string $p) => (bool)preg_match('/[^\p{L}\p{N}\s]/u', $p)],
    ];
}

/**
 * What is wrong with a new password, in one sentence, or null when it is
 * fine. $again is the "type it again" field when the form has one. Over
 * 200 characters is refused too: bcrypt reads only the first 72 bytes.
 */
function password_problem(string $password, ?string $again = null): ?string {
    if ($password === '') {
        return 'Choose a password.';
    }
    if (strlen($password) > 200) {
        return 'Use at most 200 characters.';
    }
    $missing = [];
    foreach (password_rules() as $rule) {
        if (!$rule['test']($password)) {
            $missing[] = lcfirst($rule['label']);
        }
    }
    if ($missing) {
        return 'Your password needs ' . implode(', ', $missing) . '.';
    }
    if ($again !== null && $again !== $password) {
        return 'The two passwords are not the same.';
    }
    return null;
}

/**
 * Has this password appeared in a known data breach? Asks Have I Been
 * Pwned with the first five characters of its SHA-1 only (k-anonymity: the
 * password never leaves the server, and the answer cannot be tied to it).
 * Returns true (found), false (not found) or null when the check could not
 * run (no network, a slow answer): then the password is not held up.
 */
function password_pwned(string $password): ?bool {
    if (!function_exists('curl_init') || ($_ENV['PASSWORD_BREACH_CHECK'] ?? 'on') === 'off') {
        return null;
    }
    $sha    = strtoupper(sha1($password));
    $prefix = substr($sha, 0, 5);
    $suffix = substr($sha, 5);
    $ch = curl_init('https://api.pwnedpasswords.com/range/' . $prefix);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT        => 4,
        CURLOPT_HTTPHEADER     => ['Add-Padding: true', 'User-Agent: Nfelia (password check)'],
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
    ]);
    $body   = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if (!is_string($body) || $status !== 200) {
        return null;
    }
    foreach (explode("\n", $body) as $line) {
        [$hash_suffix, $count] = array_pad(explode(':', trim($line), 2), 2, '0');
        if (strtoupper($hash_suffix) === $suffix) {
            return (int)$count > 0;   // padding lines carry a count of 0
        }
    }
    return false;
}

/**
 * Everything a new password must pass before it is saved: the rules, the
 * "type it again" field, and the breach check. One sentence, or null.
 */
function new_password_problem(string $password, ?string $again = null): ?string {
    if ($problem = password_problem($password, $again)) {
        return $problem;
    }
    if (password_pwned($password) === true) {
        return 'That password has appeared in a known data breach and could be guessed. Choose a different one.';
    }
    return null;
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
 * response finishes. The session is saved and released first, so the next
 * page (usually the redirect) never waits for the job.
 */
function after_response(callable $job): void {
    register_shutdown_function(function () use ($job) {
        while (ob_get_level()) { ob_end_flush(); }
        if (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        } elseif (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        // The person has their page; what follows (pushes, emails, a cache
        // purge) must not be cut short by the page's time limit or by the
        // browser going away.
        @ignore_user_abort(true);
        @set_time_limit(120);
        $job();
    });
}
