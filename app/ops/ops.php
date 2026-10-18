<?php
/**
 * Nfelia Ops: ops.nfelia.com, where the Nfelia team looks after clients,
 * errors and sales.
 *
 * The same code and database as the app, with its own door: its own people
 * (ops_staff, not users of any business), its own session cookie, its own
 * passkeys and an audit log of everything done here. Nothing in Ops trusts
 * a business session, and nothing in the app trusts an Ops one.
 *
 * ops.nfelia.com is a subdomain like a shop (name.nfelia.com), so the name
 * "ops" is reserved (SHOP_RESERVED_NAMES) and index.php sends this host here
 * before any shop is looked for. Shops are on the same site, so SameSite
 * cookies are no protection between them and Ops: every form and script
 * call here carries the CSRF token.
 */

const OPS_LABEL        = 'ops';
const OPS_SESSION      = '__Host-nfelia_ops';
const OPS_IDLE_SECONDS = 12 * 3600;     // unused this long: sign in again
const OPS_MAX_SECONDS  = 7 * 86400;     // and at least once a week regardless
const OPS_MIN_PASSWORD = 10;

const OPS_ROLES = [
    'admin'   => ['label' => 'Admin',   'hint' => 'Everything, including the team, money and the audit log.'],
    'support' => ['label' => 'Support', 'hint' => 'Clients, their plans and data, support tickets and errors.'],
    'sales'   => ['label' => 'Sales',   'hint' => 'Leads, the pipeline, campaigns, and clients to follow up.'],
];

/**
 * What each role may do. Admin may do everything.
 *   clients          see clients and their accounts
 *   clients.act      change a client's plan, approve payments and shop names
 *   clients.view_as  open a client's Nfelia read-only (logged, owner told)
 *   support          support tickets, feedback and reviews
 *   errors           the error monitor and system health
 *   exports          send a client their data
 *   sales            leads and the pipeline
 *   campaigns        email campaigns
 *   finance          Nfelia's own revenue, expenses and referrals
 *   settings         countries, prices and payment details
 *   team, audit      the Ops team, and everything done in Ops
 */
const OPS_PERMISSIONS = [
    'admin'   => ['*'],
    'support' => ['clients', 'clients.act', 'clients.view_as', 'support', 'errors', 'exports'],
    'sales'   => ['clients', 'sales', 'campaigns'],
];

/* ═══════════════════════════════════════════════════════════════════
 *  WHERE OPS LIVES
 * ═══════════════════════════════════════════════════════════════════ */

/** ops.nfelia.com (ops.localhost locally): ops + the app's own host. */
function ops_host(): string
{
    return OPS_LABEL . '.' . shop_base_host();
}

function ops_is_host(string $host): bool
{
    $host = rtrim(strtolower(preg_replace('/:\d+$/', '', trim($host))), '.');
    return $host !== '' && $host === ops_host();
}

/** https://ops.nfelia.com, with APP_URL's scheme and port. */
function ops_origin(): string
{
    $u = parse_url(APP_URL);
    return ($u['scheme'] ?? 'https') . '://' . ops_host() . (isset($u['port']) ? ':' . $u['port'] : '');
}

function ops_url(string $path = ''): string
{
    return ops_origin() . '/' . ltrim($path, '/');
}

/** The current page, as a path inside Ops ("errors/12?tab=events"). */
function ops_here(): string
{
    $path = trim((string)($_GET['url'] ?? ''), '/');
    $query = $_GET;
    unset($query['url']);
    return $path . ($query ? '?' . http_build_query($query) : '');
}

/** A path inside Ops that is safe to send someone to after sign-in. */
function ops_safe_next(?string $next): string
{
    $next = trim((string)$next);
    if ($next === '' || strlen($next) > 500 || preg_match('~^[a-z][a-z0-9+.-]*:|^//|\\\\|\.\.|[\x00-\x1f]~i', $next)) {
        return '';
    }
    $next = ltrim($next, '/');
    return preg_match('~^(signin|signout|invite|reset|forgot)\b~', $next) ? '' : $next;
}

/** The Ops page the browser came from, or $default. */
function ops_back(string $default = ''): string
{
    $ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
    if (!str_starts_with($ref, ops_origin() . '/')) {
        return $default;
    }
    return ops_safe_next(substr($ref, strlen(ops_origin()) + 1)) ?: $default;
}

/* ═══════════════════════════════════════════════════════════════════
 *  THE SESSION
 * ═══════════════════════════════════════════════════════════════════ */

function ops_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name(OPS_SESSION);
    session_start();
    if (empty($_SESSION['ops_csrf'])) {
        $_SESSION['ops_csrf'] = bin2hex(random_bytes(32));
    }
}

/**
 * The person signed in to Ops, read from the database on every request: a
 * removed person, a new role or "sign out everywhere" takes effect at once.
 */
function ops_staff(): ?array
{
    static $staff = false;
    if ($staff !== false) {
        return $staff;
    }
    $staff = null;
    $id = (int)($_SESSION['ops_staff_id'] ?? 0);
    if ($id === 0) {
        return null;
    }
    $now = time();
    if ($now - (int)($_SESSION['ops_seen'] ?? 0) > OPS_IDLE_SECONDS || $now - (int)($_SESSION['ops_since'] ?? 0) > OPS_MAX_SECONDS) {
        ops_session_end();
        $_SESSION['ops_flash'] = ['type' => 'info', 'message' => 'You were signed out after a while away. Sign in again.'];
        return null;
    }
    $stmt = get_db()->prepare("SELECT * FROM ops_staff WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row || !(int)$row['is_active'] || (int)$row['session_epoch'] !== (int)($_SESSION['ops_epoch'] ?? 0) || $row['password_hash'] === null) {
        ops_session_end();
        return null;
    }
    $_SESSION['ops_seen'] = $now;
    return $staff = $row;
}

function ops_signin(array $staff, string $how): void
{
    session_regenerate_id(true);
    $_SESSION = [
        'ops_staff_id' => (int)$staff['id'],
        'ops_epoch'    => (int)$staff['session_epoch'],
        'ops_since'    => time(),
        'ops_seen'     => time(),
        'ops_csrf'     => bin2hex(random_bytes(32)),
    ];
    get_db()->prepare("UPDATE ops_staff SET last_signin_at = NOW(), last_signin_ip = ? WHERE id = ?")
        ->execute([ops_ip(), (int)$staff['id']]);
    ops_audit('signin', 'Signed in with ' . $how, ['staff' => (int)$staff['id']]);
}

/** Empty the session, keeping it usable for the sign-in page that follows. */
function ops_session_end(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['ops_csrf'] = bin2hex(random_bytes(32));
}

/** Sign this person out on every device: their sessions stop matching. */
function ops_bump_epoch(int $staff_id): void
{
    get_db()->prepare("UPDATE ops_staff SET session_epoch = session_epoch + 1 WHERE id = ?")->execute([$staff_id]);
}

/* ═══════════════════════════════════════════════════════════════════
 *  ROLES
 * ═══════════════════════════════════════════════════════════════════ */

function ops_can(string $permission, ?array $staff = null): bool
{
    $staff ??= ops_staff();
    if (!$staff) {
        return false;
    }
    $allowed = OPS_PERMISSIONS[$staff['role']] ?? [];
    return in_array('*', $allowed, true) || in_array($permission, $allowed, true);
}

function ops_role_label(string $role): string
{
    return OPS_ROLES[$role]['label'] ?? ucfirst($role);
}

/* ═══════════════════════════════════════════════════════════════════
 *  REQUESTS AND ANSWERS
 * ═══════════════════════════════════════════════════════════════════ */

function ops_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function ops_csrf(): string
{
    return (string)($_SESSION['ops_csrf'] ?? '');
}

function ops_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(ops_csrf()) . '">';
}

/** The token from a form field, or the X-CSRF-Token header (scripts). */
function ops_csrf_ok(): bool
{
    $sent = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return ops_csrf() !== '' && $sent !== '' && hash_equals(ops_csrf(), $sent);
}

function ops_flash(string $type, string $message): void
{
    $_SESSION['ops_flash'] = ['type' => $type, 'message' => $message];
}

function ops_take_flash(): ?array
{
    $f = $_SESSION['ops_flash'] ?? null;
    unset($_SESSION['ops_flash']);
    return $f;
}

function ops_redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : ops_url($path)));
    exit;
}

function ops_json(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/** JSON body of a script's request (passkeys). */
function ops_input(): array
{
    static $in = null;
    return $in ??= (json_decode((string)file_get_contents('php://input'), true) ?: []);
}

/**
 * A page in the Ops frame. $vars become the view's variables. $view is a
 * path under views/ops/ without .php.
 */
function ops_render(string $view, array $vars = [], string $title = 'Nfelia Ops', int $status = 200): never
{
    http_response_code($status);
    extract($vars, EXTR_SKIP);
    $staff     = ops_staff();
    $flash     = ops_take_flash();
    $view_file = BASE_PATH . '/views/ops/' . $view . '.php';
    require BASE_PATH . '/views/ops/layout.php';
    exit;
}

/** A page on its own, without the menu (sign-in, invites). */
function ops_render_bare(string $view, array $vars = [], string $title = 'Nfelia Ops', int $status = 200): never
{
    http_response_code($status);
    extract($vars, EXTR_SKIP);
    $flash     = ops_take_flash();
    $view_file = BASE_PATH . '/views/ops/' . $view . '.php';
    require BASE_PATH . '/views/ops/bare.php';
    exit;
}

function ops_not_found(): never
{
    if (ops_staff()) {
        ops_render('message', ['heading' => 'Nothing here', 'text' => 'That page does not exist, or it has moved.'], 'Not found', 404);
    }
    ops_render_bare('message', ['heading' => 'Nothing here', 'text' => 'That page does not exist, or it has moved.'], 'Not found', 404);
}

function ops_forbidden(): never
{
    ops_render('message', ['heading' => 'Not for your role', 'text' => 'Your role in Ops does not include this. An admin can change your role under Team.'], 'Not allowed', 403);
}

/**
 * Nothing in Ops is cached, framed or indexed, and the browser is told to
 * send no Referer to other sites (links to clients' shops carry none).
 */
function ops_headers(): void
{
    header('Cache-Control: no-store');
    header('X-Frame-Options: DENY');
    // form-action also covers where a form's answer redirects: "View as" goes on to the app.
    $app = parse_url(APP_URL, PHP_URL_SCHEME) . '://' . parse_url(APP_URL, PHP_URL_HOST) . (parse_url(APP_URL, PHP_URL_PORT) ? ':' . parse_url(APP_URL, PHP_URL_PORT) : '');
    header("Content-Security-Policy: frame-ancestors 'none'; base-uri 'self'; form-action 'self' {$app}");
    header('X-Robots-Tag: noindex, nofollow');
    header('Referrer-Policy: same-origin');
    header('X-Content-Type-Options: nosniff');
}

/* ═══════════════════════════════════════════════════════════════════
 *  THE AUDIT LOG
 * ═══════════════════════════════════════════════════════════════════ */

/**
 * Write down something done in Ops. Never throws: a failed write is logged,
 * and the action it records has already happened.
 *   opts: staff (default: who is signed in), type, id, business, data
 */
function ops_audit(string $action, string $summary, array $opts = []): void
{
    try {
        $staff_id = array_key_exists('staff', $opts) ? $opts['staff'] : (ops_staff()['id'] ?? null);
        get_db()->prepare("
            INSERT INTO ops_audit (staff_id, action, summary, target_type, target_id, business_id, data, ip, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $staff_id !== null ? (int)$staff_id : null,
            mb_substr($action, 0, 64),
            mb_substr($summary, 0, 255),
            $opts['type'] ?? null,
            isset($opts['id']) ? (int)$opts['id'] : null,
            isset($opts['business']) ? (int)$opts['business'] : null,
            isset($opts['data']) ? json_encode($opts['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            ops_ip(),
            mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
    } catch (Throwable $e) {
        error_log('Nfelia ops_audit ' . $action . ': ' . $e->getMessage());
    }
}

/* ═══════════════════════════════════════════════════════════════════
 *  THE TEAM
 * ═══════════════════════════════════════════════════════════════════ */

function ops_staff_by_email(string $email): ?array
{
    $stmt = get_db()->prepare("SELECT * FROM ops_staff WHERE email = ?");
    $stmt->execute([strtolower(trim($email))]);
    return $stmt->fetch() ?: null;
}

function ops_staff_by_id(int $id): ?array
{
    $stmt = get_db()->prepare("SELECT * FROM ops_staff WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function ops_active_admins(): int
{
    return (int)get_db()->query("SELECT COUNT(*) FROM ops_staff WHERE role = 'admin' AND is_active = 1")->fetchColumn();
}

/**
 * A one-time link for an invite (72 hours) or a password reset (1 hour).
 * Earlier links for the same purpose stop working. Returns the token.
 */
function ops_token_issue(int $staff_id, string $purpose): string
{
    $db = get_db();
    $db->prepare("UPDATE ops_tokens SET used_at = NOW() WHERE staff_id = ? AND purpose = ? AND used_at IS NULL")
        ->execute([$staff_id, $purpose]);
    $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $hours = $purpose === 'invite' ? 72 : 1;
    $db->prepare("INSERT INTO ops_tokens (staff_id, purpose, token_hash, expires_at) VALUES (?, ?, ?, NOW() + INTERVAL {$hours} HOUR)")
        ->execute([$staff_id, $purpose, hash('sha256', $token)]);
    return $token;
}

/** The person a still-good link belongs to, without using it up. */
function ops_token_peek(string $token, string $purpose): ?array
{
    $stmt = get_db()->prepare("
        SELECT t.id AS token_id, s.* FROM ops_tokens t JOIN ops_staff s ON s.id = t.staff_id
        WHERE t.token_hash = ? AND t.purpose = ? AND t.used_at IS NULL AND t.expires_at > NOW() AND s.is_active = 1
    ");
    $stmt->execute([hash('sha256', $token), $purpose]);
    return $stmt->fetch() ?: null;
}

/** Use the link up. False when someone else used it first. */
function ops_token_use(int $token_id): bool
{
    $stmt = get_db()->prepare("UPDATE ops_tokens SET used_at = NOW() WHERE id = ? AND used_at IS NULL");
    $stmt->execute([$token_id]);
    return $stmt->rowCount() === 1;
}

/** Invite someone (or send them a fresh invite), by email. */
function ops_send_invite(array $staff, ?array $from): void
{
    $token = ops_token_issue((int)$staff['id'], 'invite');
    $id = notify_email(null, $staff['email'], $staff['full_name'], 'You\'re invited to Nfelia Ops', 'ops_invite', [
        'name'    => $staff['full_name'],
        'inviter' => $from['full_name'] ?? 'The Nfelia team',
        'role'    => ops_role_label($staff['role']),
        'url'     => ops_url('invite/' . $token),
    ], null, 'ops.invite');
    if ($id) notify_flush_later($id);
}

function ops_password_problem(string $password, string $again): ?string
{
    if (mb_strlen($password) < OPS_MIN_PASSWORD) {
        return 'Use at least ' . OPS_MIN_PASSWORD . ' characters.';
    }
    if ($password !== $again) {
        return 'The two passwords are not the same.';
    }
    return null;
}

/**
 * A time from the database as a timestamp. The database keeps Lagos time
 * (+01:00, config/database.php) and PHP may run in UTC, so strtotime()
 * alone would put everything an hour out.
 */
function ops_ts(string $db_time): int
{
    try {
        return (new DateTimeImmutable($db_time, new DateTimeZone(APP_TIMEZONE)))->getTimestamp();
    } catch (Throwable $e) {
        return 0;
    }
}

/** "3 min ago", "yesterday", "12 Oct". */
function ops_ago(?string $when): string
{
    if (!$when) return '—';
    $t = ops_ts($when);
    $s = time() - $t;
    return match (true) {
        $s < 60        => 'just now',
        $s < 3600      => intdiv($s, 60) . ' min ago',
        $s < 86400     => intdiv($s, 3600) . ' h ago',
        $s < 2 * 86400 => 'yesterday',
        $s < 7 * 86400 => intdiv($s, 86400) . ' days ago',
        default        => date('j M Y', strtotime($when)),
    };
}

/* ═══════════════════════════════════════════════════════════════════
 *  THE FRAME
 * ═══════════════════════════════════════════════════════════════════ */

/**
 * The menu, for this person's role: [group => [[path, label, icon, count]]].
 * A section appears only for those whose role includes it.
 */
function ops_nav(array $staff): array
{
    $db = get_db();
    $count = function (string $sql, array $args = []) use ($db): int {
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($args);
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;   // the menu never breaks a page
        }
    };
    $items = [
        'Overview' => [
            ['',       'Home',      'home',  null, 'signed_in'],
            ['alerts', 'Alerts',    'mail',  $count("SELECT COUNT(*) FROM ops_alerts WHERE staff_id = ? AND read_at IS NULL", [(int)$staff['id']]), 'signed_in'],
        ],
        'Monitor' => [
            ['errors', 'Errors',    'alert', ops_can('errors', $staff) ? $count("SELECT COUNT(*) FROM error_groups WHERE status = 'open' AND level IN ('fatal', 'error') AND kind <> 'slow'") : null, 'errors'],
            ['health', 'Health',    'pulse', null, 'errors'],
        ],
        'Clients' => [
            ['clients',    'Clients',      'shop',   null, 'clients'],
            ['payments',   'Payments to check', 'chart', ops_can('clients.act', $staff) ? $count("SELECT COUNT(*) FROM subscription_payments WHERE status = 'pending_verification'") : null, 'clients.act'],
            ['shop-names', 'Shop names',   'target', ops_can('clients.act', $staff) ? $count("SELECT COUNT(*) FROM businesses WHERE subdomain_status = 'pending' AND subdomain_requested IS NOT NULL") : null, 'clients.act'],
            ['support',    'Support',      'chat',   ops_can('support', $staff) ? $count("SELECT COUNT(*) FROM support_tickets WHERE status = 'open'") : null, 'support'],
            ['reviews',    'Reviews',      'eye',    null, 'support'],
            ['feedback',   'Feedback',     'mail',   null, 'support'],
        ],
        'Nfelia' => [
            ['finance',   'Finance',   'chart', null, 'finance'],
            ['referrals', 'Referrals', 'users', null, 'finance'],
            ['countries', 'Countries', 'home',  null, 'settings'],
            ['team',      'Team',      'users', null, 'team'],
            ['audit',     'Audit log', 'list',  null, 'audit'],
        ],
    ];
    $nav = [];
    foreach ($items as $group => $links) {
        $links = array_values(array_filter($links, fn($l) => $l[4] === 'signed_in' || ops_can($l[4], $staff)));
        if ($links) $nav[$group] = $links;
    }
    return $nav;
}

/** Whether a menu link is the page being shown (its section, for deeper pages). */
function ops_nav_current(string $path): bool
{
    $here = trim((string)($_GET['url'] ?? ''), '/');
    return $path === '' ? $here === '' : ($here === $path || str_starts_with($here, $path . '/'));
}

/** Initials for an avatar: "Tolu Bello" → "TB". */
function ops_initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    $first = mb_substr($parts[0] ?? '?', 0, 1);
    $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
    return mb_strtoupper($first . $last);
}

/** A line icon, drawn inline (no icon font to download). */
function ops_icon(string $name, string $label = ''): string
{
    $paths = [
        'home'    => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M10 21v-6h4v6"/>',
        'users'   => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.5 3.4-5.5 6.5-5.5s5.7 2 6.5 5.5"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8"/><path d="M18 14.8c1.8.7 3 2.5 3.5 5.2"/>',
        'list'    => '<path d="M9 6h12M9 12h12M9 18h12"/><circle cx="4.5" cy="6" r="1"/><circle cx="4.5" cy="12" r="1"/><circle cx="4.5" cy="18" r="1"/>',
        'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4.2-6 8-6s7 2 8 6"/>',
        'signout' => '<path d="M15 4h4v16h-4"/><path d="M10 8l-4 4 4 4"/><path d="M6 12h10"/>',
        'menu'    => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'close'   => '<path d="M6 6l12 12M18 6 6 18"/>',
        'search'  => '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2"/>',
        'key'     => '<circle cx="8" cy="15" r="4"/><path d="m11 12 9-9"/><path d="m16 7 3 3"/>',
        'plus'    => '<path d="M12 5v14M5 12h14"/>',
        'alert'   => '<path d="M12 3 2 20h20L12 3z"/><path d="M12 10v4"/><path d="M12 17.5v.01"/>',
        'shop'    => '<path d="M4 9h16l-1 11H5L4 9z"/><path d="M8 9V7a4 4 0 0 1 8 0v2"/>',
        'chart'   => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'chat'    => '<path d="M4 5h16v11H9l-5 4V5z"/>',
        'target'  => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4"/><circle cx="12" cy="12" r=".5"/>',
        'mail'    => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'pulse'   => '<path d="M3 12h4l3-7 4 14 3-7h4"/>',
        'chevron' => '<path d="m9 6 6 6-6 6"/>',
        'copy'    => '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3"/>',
        'eye'     => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'download'=> '<path d="M12 4v11"/><path d="m7 10 5 5 5-5"/><path d="M5 20h14"/>',
    ];
    $aria = $label !== '' ? ' role="img" aria-label="' . e($label) . '"' : ' aria-hidden="true"';
    return '<svg class="ops-icon" viewBox="0 0 24 24"' . $aria . '>' . ($paths[$name] ?? '') . '</svg>';
}

/** A file under the docroot, with its modification time so a new version is fetched. */
function ops_asset(string $path): string
{
    $file = BASE_PATH . '/' . ltrim($path, '/');
    return '/' . ltrim($path, '/') . (is_file($file) ? '?v=' . filemtime($file) : '');
}

/** A plan's state as a chip. */
function ops_plan_chip(?string $status): string
{
    [$label, $tone] = match ($status) {
        'active'               => ['Paying', 'ok'],
        'trial'                => ['Free trial', 'accent'],
        'past_due'             => ['Payment due', 'warn'],
        'pending_verification' => ['Being checked', 'warn'],
        'cancelled'            => ['Cancelled', 'muted'],
        'suspended'            => ['Suspended', 'bad'],
        default                => ['No plan', 'muted'],
    };
    return '<span class="ops-chip ops-chip-' . $tone . '">' . e($label) . '</span>';
}
