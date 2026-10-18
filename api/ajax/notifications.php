<?php
/**
 * Nfelia — the bell, and each person's notification choices (JSON).
 *
 *   GET  count          unread, for the number on the bell
 *   GET  list           the newest (limit, max 30); text is raw, the page escapes it
 *   POST mark_read      {id} or {id: 'all'}
 *   GET  prefs          this person's choices (More > Notifications)
 *   POST prefs          {topics: {orders: {push, email}}, quiet_hours, quiet_from, quiet_to, hide_amounts}
 *   POST test           {channel: push | email}: send one to yourself, now
 *   POST resubscribe    the browser replaced its push subscription (sw.js);
 *                       the old endpoint proves which row it was
 *
 * Every POST carries the session's CSRF token in X-CSRF-Token (resubscribe
 * excepted: the service worker has no token, and the old endpoint, which
 * only the browser and the push service know, stands in for it).
 */

$action = $_GET['action'] ?? 'list';
$db = get_db();

if ($action === 'resubscribe') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['error' => 'Use POST.'], 405);
    }
    $in  = json_decode((string)file_get_contents('php://input'), true);
    $old = (string)($in['old_endpoint'] ?? '');
    $new = $in['subscription'] ?? [];
    $endpoint = (string)($new['endpoint'] ?? '');
    $p256dh   = (string)($new['keys']['p256dh'] ?? '');
    $auth     = (string)($new['keys']['auth'] ?? '');
    if ($old === '' || !preg_match('#^https://[^\s]{10,2000}$#', $endpoint)
        || strlen(b64url_decode($p256dh)) !== 65 || strlen(b64url_decode($auth)) !== 16) {
        json_response(['error' => 'Unusable subscription.'], 422);
    }
    $stmt = $db->prepare("UPDATE IGNORE push_subscriptions SET endpoint = ?, endpoint_hash = ?, p256dh = ?, auth = ?, failures = 0 WHERE endpoint_hash = ?");
    $stmt->execute([$endpoint, hash('sha256', $endpoint), $p256dh, $auth, hash('sha256', $old)]);
    json_response(['success' => $stmt->rowCount() === 1]);
}

if (!is_logged_in()) {
    json_response(['error' => 'You are signed out. Sign in again.', 'signedOut' => true], 401);
}

$biz_id  = (int)auth_business_id();
$user    = auth_user();
$user_id = (int)($user['id'] ?? 0);
[$mine, $mine_args] = notify_visible_sql($biz_id, $user_id, (string)($user['role'] ?? 'staff'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = json_decode((string)file_get_contents('php://input'), true);
    $in = is_array($in) ? $in : [];
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($in['csrf_token'] ?? ($_POST['csrf_token'] ?? ''));
    if ($token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', (string)$token)) {
        json_response(['error' => 'This page is out of date. Reload it and try again.'], 403);
    }
}

switch ($action) {

    case 'count':
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE {$mine} AND is_read = 0");
        $stmt->execute($mine_args);
        json_response(['success' => true, 'count' => (int)$stmt->fetchColumn()]);

    case 'list':
        $limit = max(1, min(30, (int)($_GET['limit'] ?? 15)));
        $stmt = $db->prepare("
            SELECT id, title, message, action_url, is_read, created_at
            FROM notifications WHERE {$mine}
            ORDER BY id DESC LIMIT {$limit}");
        $stmt->execute($mine_args);
        $now = planner_now()->getTimestamp();
        $out = array_map(function ($n) use ($now) {
            $secs = $now - (new DateTimeImmutable($n['created_at'], planner_tz()))->getTimestamp();
            return [
                'id'      => (int)$n['id'],
                'title'   => $n['title'],
                'message' => $n['message'],
                'url'     => 'notifications/open/' . (int)$n['id'],
                'is_read' => (int)$n['is_read'],
                'ago'     => $secs < 60 ? 'Just now' : ($secs < 3600 ? intdiv($secs, 60) . ' min ago'
                           : ($secs < 86400 ? intdiv($secs, 3600) . ' h ago' : date('j M, g:ia', strtotime($n['created_at'])))),
            ];
        }, $stmt->fetchAll());
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE {$mine} AND is_read = 0");
        $stmt->execute($mine_args);
        json_response(['success' => true, 'notifications' => $out, 'count' => (int)$stmt->fetchColumn()]);

    case 'mark_read':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['error' => 'Use POST.'], 405);
        }
        $id = $in['id'] ?? 'all';
        if ($id === 'all') {
            $db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE {$mine} AND is_read = 0")->execute($mine_args);
        } else {
            $db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE {$mine} AND id = ?")->execute([...$mine_args, (int)$id]);
        }
        json_response(['success' => true]);

    case 'prefs':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $allowed = notify_prefs($user_id)['topics'];
            foreach ((array)($in['topics'] ?? []) as $topic => $choice) {
                if (!isset($allowed[$topic]) || !is_array($choice)) continue;
                notify_set_pref($db, $user_id, (string)$topic,
                    array_key_exists('push', $choice) ? !empty($choice['push']) : null,
                    array_key_exists('email', $choice) ? !empty($choice['email']) : null);
            }
            $now  = notify_prefs($user_id);
            $time = fn($v, $fallback) => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string)$v) ? $v . ':00' : $fallback . ':00';
            $db->prepare("UPDATE users SET quiet_hours = ?, quiet_from = ?, quiet_to = ?, hide_amounts = ? WHERE id = ? AND business_id = ?")
               ->execute([
                   (int)(array_key_exists('quiet_hours', $in) ? !empty($in['quiet_hours']) : $now['quiet_hours']),
                   $time($in['quiet_from'] ?? '', $now['quiet_from']),
                   $time($in['quiet_to'] ?? '', $now['quiet_to']),
                   (int)(array_key_exists('hide_amounts', $in) ? !empty($in['hide_amounts']) : $now['hide_amounts']),
                   $user_id, $biz_id,
               ]);
        }
        json_response(['success' => true, 'prefs' => notify_prefs($user_id)]);

    case 'test':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['error' => 'Use POST.'], 405);
        }
        $channel = ($in['channel'] ?? 'push') === 'email' ? 'email' : 'push';
        if (!throttle_allows('push_test', (string)$user_id, 5, 60)) {
            json_response(['error' => 'That is enough tests for now. Try again in an hour.'], 429);
        }
        [$ok, $message] = notify_test($biz_id, $user_id, $channel);
        json_response($ok ? ['success' => true, 'message' => $message] : ['error' => $message], $ok ? 200 : 502);

    default:
        json_response(['error' => 'Invalid action requested.'], 400);
}
