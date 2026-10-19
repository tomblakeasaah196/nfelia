<?php
/**
 * Nfelia — the bell, and each person's notification choices (JSON).
 *
 *   GET  count          unread, for the number on the bell, and the newest id (the banner)
 *   GET  list           the newest (limit, max 30); text is raw, the page escapes it
 *   POST mark_read      {id} or {id: 'all'}
 *   GET  prefs          this person's choices (More > Notifications)
 *   POST prefs          {topics: {orders: {push, email}}, quiet_hours, quiet_from, quiet_to, hide_amounts}
 *   POST test           {channel: push | email}: send one to yourself, now
 *   POST subscribe      this phone's push subscription (assets/js/push.js):
 *                       saved for the signed-in person; answers with a device
 *                       token the phone keeps
 *   POST unsubscribe    {endpoint}: this phone stops, for this person
 *   GET  device         this person's phones, and how the last phone alert went
 *   POST device_remove  {id}: forget one of their phones
 *   POST resubscribe    the browser replaced its push subscription (sw.js);
 *                       the old endpoint, or the device token, proves whose it is
 *
 * Every POST carries the session's CSRF token in X-CSRF-Token (resubscribe
 * excepted: the service worker has no session or token; the old endpoint,
 * which only the browser and the push service know, or the device token this
 * server signed, stands in for it).
 */

$action = $_GET['action'] ?? 'list';
$db = get_db();

if ($action === 'resubscribe') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['error' => 'Use POST.'], 405);
    }
    $in  = json_decode((string)file_get_contents('php://input'), true);
    $in  = is_array($in) ? $in : [];
    $old = (string)($in['old_endpoint'] ?? '');
    $new = is_array($in['subscription'] ?? null) ? $in['subscription'] : [];
    $endpoint = (string)($new['endpoint'] ?? '');
    $p256dh   = (string)($new['keys']['p256dh'] ?? '');
    $auth     = (string)($new['keys']['auth'] ?? '');
    if (!push_enabled() || !push_subscription_ok($endpoint, $p256dh, $auth)) {
        json_response(['error' => 'Unusable subscription.'], 422);
    }
    $saved = [];
    // The old endpoint names the rows: everyone who had alerts on that phone
    // gets the new subscription in its place.
    if ($old !== '') {
        $saved = push_device_renew($db, $old, $endpoint, $p256dh, $auth);
    }
    // No old rows (Chrome often gives no old subscription): the device
    // tokens the phone kept say whose phone it is.
    $tokens = is_array($in['tokens'] ?? null) ? $in['tokens'] : (isset($in['token']) ? [$in['token']] : []);
    foreach (array_slice(array_filter($tokens, 'is_string'), 0, PUSH_MAX_DEVICES) as $token) {
        $who = push_device_token_read($token);
        if (!$who || in_array($who['user_id'], $saved, true)) continue;
        $stmt = $db->prepare("SELECT id FROM users WHERE id = ? AND business_id = ? AND is_active = 1");
        $stmt->execute([$who['user_id'], $who['business_id']]);
        if (!$stmt->fetchColumn()) continue;
        push_device_save($db, $who['business_id'], $who['user_id'], $endpoint, $p256dh, $auth, null);
        $saved[] = $who['user_id'];
    }
    foreach (array_unique($saved) as $uid) {
        notify_push_revive($db, $uid);
    }
    if ($saved) {
        json_response(['success' => true, 'people' => count(array_unique($saved))]);
    }
    // Nothing to go on: at least don't lose a row that already names this endpoint.
    $stmt = $db->prepare("SELECT 1 FROM push_subscriptions WHERE endpoint_hash = ?");
    $stmt->execute([hash('sha256', $endpoint)]);
    json_response(['success' => (bool)$stmt->fetchColumn()]);
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
        $count = (int)$stmt->fetchColumn();
        // The newest id, so the page knows when something new has arrived
        // (assets/js/bell.js shows it as a banner).
        $stmt = $db->prepare("SELECT MAX(id) FROM notifications WHERE {$mine}");
        $stmt->execute($mine_args);
        $latest = (int)$stmt->fetchColumn();
        // The page's CSRF token too: an installed app can live for days,
        // past a sign-in elsewhere that changed it.
        json_response(['success' => true, 'count' => $count, 'latest' => $latest, 'csrf' => (string)($_SESSION['csrf_token'] ?? '')] + notify_phones_for_page($db, $user_id));

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
                'age'     => max(0, $secs),   // seconds old: the banner shows only what just arrived
                'ago'     => $secs < 60 ? 'Just now' : ($secs < 3600 ? intdiv($secs, 60) . ' min ago'
                           : ($secs < 86400 ? intdiv($secs, 3600) . ' h ago' : date('j M, g:ia', strtotime($n['created_at'])))),
            ];
        }, $stmt->fetchAll());
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE {$mine} AND is_read = 0");
        $stmt->execute($mine_args);
        $count = (int)$stmt->fetchColumn();
        $stmt = $db->prepare("SELECT MAX(id) FROM notifications WHERE {$mine}");
        $stmt->execute($mine_args);
        json_response(['success' => true, 'notifications' => $out, 'count' => $count, 'latest' => (int)$stmt->fetchColumn()] + notify_phones_for_page($db, $user_id));

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

    case 'subscribe':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['error' => 'Use POST.'], 405);
        }
        $endpoint = (string)($in['endpoint'] ?? '');
        $p256dh   = (string)($in['keys']['p256dh'] ?? '');
        $auth     = (string)($in['keys']['auth'] ?? '');
        if (!push_enabled()) {
            json_response(['error' => 'Phone notifications are not switched on for Nfelia yet.'], 409);
        }
        if (!push_subscription_ok($endpoint, $p256dh, $auth)) {
            json_response(['error' => 'This browser sent an unusable subscription. Try again.'], 422);
        }
        push_device_save($db, $biz_id, $user_id, $endpoint, $p256dh, $auth, (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        // Anything that was waiting for a phone goes now (after this answer).
        notify_push_revive($db, $user_id);
        json_response(['success' => true, 'message' => 'Notifications are on for this phone.',
                       'token' => push_device_token($user_id, $biz_id), 'hash' => hash('sha256', $endpoint)]);

    case 'unsubscribe':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['error' => 'Use POST.'], 405);
        }
        $hash = hash('sha256', (string)($in['endpoint'] ?? ''));
        $db->prepare("DELETE FROM push_subscriptions WHERE endpoint_hash = ? AND user_id = ?")->execute([$hash, $user_id]);
        // Others may still have alerts on this phone: then the browser keeps
        // its subscription (the page only drops it when nobody is left).
        $stmt = $db->prepare("SELECT COUNT(*) FROM push_subscriptions WHERE endpoint_hash = ?");
        $stmt->execute([$hash]);
        json_response(['success' => true, 'message' => 'Notifications are off for this phone.', 'others' => (int)$stmt->fetchColumn()]);

    case 'device':
        // Every phone with this person's alerts on, and how the last phone
        // alert went: the answer to "why didn't my phone buzz?".
        $stmt = $db->prepare("SELECT id, LEFT(endpoint_hash, 16) AS hash, user_agent, failures, last_sent_at, created_at
                              FROM push_subscriptions WHERE user_id = ? ORDER BY id DESC");
        $stmt->execute([$user_id]);
        $devices = array_map(fn($d) => [
            'id'        => (int)$d['id'],
            'hash'      => $d['hash'],
            'label'     => push_device_label($d['user_agent']),
            'added'     => date('j M', strtotime($d['created_at'])),
            'last_sent' => $d['last_sent_at'] ? notify_when_ago($d['last_sent_at']) : null,
            'failures'  => (int)$d['failures'],
        ], $stmt->fetchAll());
        $stmt = $db->prepare("SELECT o.event, o.status, o.attempts, o.last_error, o.created_at, o.sent_at, o.next_attempt_at, n.title
                              FROM notify_outbox o LEFT JOIN notifications n ON n.id = o.notification_id
                              WHERE o.user_id = ? AND o.channel = 'push' ORDER BY o.id DESC LIMIT 1");
        $stmt->execute([$user_id]);
        $last = null;
        if ($o = $stmt->fetch()) {
            $last = [
                'title'    => $o['title'] ?: $o['event'],
                'status'   => $o['status'],
                'when'     => notify_when_ago($o['sent_at'] ?: $o['created_at']),
                'attempts' => (int)$o['attempts'],
                'error'    => $o['last_error'],
                'words'    => notify_push_words($o),
            ];
        }
        json_response(['success' => true, 'enabled' => push_enabled(), 'devices' => $devices, 'last' => $last, 'dead' => push_dead_hashes($db)]);

    case 'device_remove':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['error' => 'Use POST.'], 405);
        }
        $db->prepare("DELETE FROM push_subscriptions WHERE id = ? AND user_id = ?")->execute([(int)($in['id'] ?? 0), $user_id]);
        json_response(['success' => true]);

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
