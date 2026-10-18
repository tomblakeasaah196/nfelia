<?php
/**
 * Nfelia support seeing a client's Nfelia exactly as its owner does, to
 * help them (started from Ops → Clients → View as, with a reason).
 *
 * Read-only and short: nothing can be saved, sent or deleted, and it ends
 * after VIEW_AS_MINUTES. The owner is told who looked and why (a security
 * alert: in the app, on their phone, and by email, which can't be switched
 * off), the reason stays in Ops' audit log, and looking leaves no trace in
 * the client's own data (no sign-in recorded, no device remembered).
 *
 *   GET  /support-view/{token}   the one-time link from Ops (2 minutes)
 *   POST /support-view/end       back to Ops
 */

const VIEW_AS_MINUTES = 30;

/** The view in progress in this session, or null. */
function view_as_active(): ?array
{
    return isset($_SESSION['view_as']['id']) ? $_SESSION['view_as'] : null;
}

/** Start from the link Ops made. Returns null, or what went wrong. */
function view_as_start(string $token): ?string
{
    $db = get_db();
    $stmt = $db->prepare("
        SELECT v.*, s.full_name AS staff_name, s.is_active AS staff_active, b.name AS business_name
        FROM ops_view_as v JOIN ops_staff s ON s.id = v.staff_id JOIN businesses b ON b.id = v.business_id
        WHERE v.token_hash = ? AND v.started_at IS NULL AND v.created_at > NOW() - INTERVAL 2 MINUTE
    ");
    $stmt->execute([hash('sha256', $token)]);
    $view = $stmt->fetch();
    if (!$view || !(int)$view['staff_active']) {
        return 'This link has expired. Start again from the client\'s page in Nfelia Ops.';
    }
    $start = $db->prepare("UPDATE ops_view_as SET started_at = NOW(), ends_at = NOW() + INTERVAL " . VIEW_AS_MINUTES . " MINUTE WHERE id = ? AND started_at IS NULL");
    $start->execute([$view['id']]);
    $user = account_find('id', (int)$view['user_id']);
    if ($start->rowCount() !== 1 || !$user || (int)$user['business_id'] !== (int)$view['business_id']) {
        return 'This link has already been used. Start again from the client\'s page in Nfelia Ops.';
    }

    // Whoever was signed in on this browser is signed out first.
    $_SESSION = [];
    signin_session($user);
    $_SESSION['last_active'] = time();
    $_SESSION['view_as'] = [
        'id'       => (int)$view['id'],
        'staff'    => $view['staff_name'],
        'business' => (int)$view['business_id'],
        'until'    => time() + VIEW_AS_MINUTES * 60,
    ];
    csrf_regenerate();

    notify('security.view_as', (int)$view['business_id'], ['staff' => $view['staff_name'], 'reason' => $view['reason']],
           ['users' => [(int)$view['user_id']], 'dedupe' => 'view-as:' . $view['id']]);
    return null;
}

/** End the view: recorded in Ops, signed out here. Returns the Ops page to go back to. */
function view_as_end(): string
{
    $view = view_as_active();
    if ($view) {
        get_db()->prepare("UPDATE ops_view_as SET ended_at = NOW() WHERE id = ? AND ended_at IS NULL")->execute([$view['id']]);
    }
    logout_user();
    return ops_url($view ? 'clients/' . $view['business'] : 'clients');
}

/**
 * On every request during a view: over time, it ends; anything that would
 * change something is refused (every POST, and the few links that act).
 */
function view_as_guard(string $route): void
{
    $view = view_as_active();
    if (!$view) {
        return;
    }
    $json = is_ajax() || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || str_starts_with($route, 'api/');
    // Over by the clock here, or ended in the database (by time, or from Ops).
    $stmt = get_db()->prepare("SELECT ends_at < NOW() OR ended_at IS NOT NULL FROM ops_view_as WHERE id = ?");
    $stmt->execute([$view['id']]);
    if (time() > (int)$view['until'] || (int)$stmt->fetchColumn() !== 0) {
        $back = view_as_end();
        if ($json) {
            json_response(['success' => false, 'ok' => false, 'message' => 'The support view has ended.'], 401);
        }
        header('Location: ' . $back);
        exit;
    }
    $_SESSION['last_active'] = time();   // never the lock: the view is short anyway

    $acts = preg_match('#(^|/)(logout|delete|remove|destroy|cancel|approve|reject|toggle|purge|mark-read|read-all|send|resend|duplicate|convert)(/|$)#', $route)
         || str_starts_with($route, 'notifications/open/') || str_starts_with($route, 'login-link') || str_starts_with($route, 'billing/');
    $writes = ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD';
    $reporting = $route === 'api/errors';   // a script error in this view is still worth knowing
    if (($writes || $acts) && !$reporting && $route !== 'support-view/end') {
        $message = 'Read-only: you are viewing ' . (auth_business()['name'] ?? 'this business') . ' as Nfelia support, so nothing can be changed.';
        if ($json) {
            json_response(['success' => false, 'ok' => false, 'read_only' => true, 'message' => $message], 403);
        }
        flash('warning', $message);
        $back = (string)($_SERVER['HTTP_REFERER'] ?? '');
        redirect(str_starts_with($back, APP_URL . '/') ? substr($back, strlen(APP_URL) + 1) : 'dashboard');
    }
}
