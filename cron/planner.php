<?php
/**
 * Nfelia — Tasks & Calendar reminders. cPanel runs it every five minutes
 * with the crontab line below this comment.
 *
 * 1. Reminders that are due: a bell entry, a phone notification and, when
 *    the person wants it, an email. The task row is the queue: reminded_at
 *    is stamped before anything is sent, so a failure never repeats a
 *    reminder or holds up the rest. A reminder more than a day old (the first
 *    run after the cron is set up, or after the server was down) is stamped
 *    and dropped: "your 2pm appointment" is no use the next afternoon.
 *
 * 2. The morning plan, once per person per day between 07:00 and 11:00:
 *    late items, today's items and deliveries due in the next two days.
 *    A day with nothing in it sends nothing.
 *
 * Every time here is Lagos time from planner_now(), the same clock remind_at
 * was worked out with when the task was saved.
 */
//   */5 * * * * /usr/local/bin/php /home/smartqaq/public_html/nfelia.com/cron/planner.php >/dev/null 2>&1

// cron/ is copied into the public docroot: never run it from the web.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/helpers/mail.php';
require_once __DIR__ . '/../app/helpers/webpush.php';
require_once __DIR__ . '/../app/helpers/planner.php';

$db = get_db();

// One run at a time, even when one takes longer than five minutes.
if ((int)$db->query("SELECT GET_LOCK('nfelia_planner', 0)")->fetchColumn() !== 1) {
    echo "[planner] another run is busy, skipping\n";
    exit(0);
}

$now   = planner_now();
$now_s = $now->format('Y-m-d H:i:s');
$today = $now->format('Y-m-d');
$done  = ['reminders' => 0, 'stale' => 0, 'skipped' => 0, 'mornings' => 0, 'quiet' => 0, 'errors' => 0];

/** A business that can use the app right now (with its subscription), or null. */
function planner_cron_business(PDO $db, int $id, DateTimeImmutable $now): ?array {
    static $cache = [];
    if (!array_key_exists($id, $cache)) {
        $stmt = $db->prepare("
            SELECT b.*, s.status AS sub_status, s.trial_ends_at, s.current_period_end, s.grace_ends_at
            FROM businesses b LEFT JOIN subscriptions s ON s.business_id = b.id
            WHERE b.id = ?");
        $stmt->execute([$id]);
        $b = $stmt->fetch() ?: null;
        $cache[$id] = ($b && planner_business_can_use($b, $now)) ? $b : null;
    }
    return $cache[$id];
}

/** An active member of the business, or null. */
function planner_cron_user(PDO $db, int $id, int $biz_id): ?array {
    $stmt = $db->prepare("
        SELECT id, business_id, full_name, email, role, reminder_email, morning_email
        FROM users WHERE id = ? AND business_id = ? AND is_active = 1");
    $stmt->execute([$id, $biz_id]);
    return $stmt->fetch() ?: null;
}

/* ── 1. Reminders that are due ─────────────────────────────── */

$due = $db->prepare("
    SELECT id, business_id, remind_at, COALESCE(assigned_to, created_by) AS user_id
    FROM tasks
    WHERE remind_at <= ? AND reminded_at IS NULL AND done_at IS NULL AND deleted_at IS NULL
    ORDER BY remind_at, id
    LIMIT 200");
$due->execute([$now_s]);
$stamp = $db->prepare("UPDATE tasks SET reminded_at = ? WHERE id = ? AND reminded_at IS NULL");
$stale_before = $now->modify('-1 day')->format('Y-m-d H:i:s');

foreach ($due->fetchAll() as $row) {
    $stamp->execute([$now_s, $row['id']]);
    if ($stamp->rowCount() !== 1) continue;
    if ($row['remind_at'] < $stale_before) { $done['stale']++; continue; }

    try {
        $business = planner_cron_business($db, (int)$row['business_id'], $now);
        $user     = $business ? planner_cron_user($db, (int)$row['user_id'], (int)$business['id']) : null;
        $task     = $user ? (planner_fetch_tasks($db, (int)$business['id'], ['ids' => [(int)$row['id']]])[0] ?? null) : null;
        if (!$task) { $done['skipped']++; continue; }

        $it   = planner_task_item($task, $today, $business);
        $when = planner_reminder_when($it, $today);
        $line = implode(' · ', array_filter([$when, $it['subtitle']]));

        planner_notify($db, (int)$business['id'], (int)$user['id'], $it['title'], $line, 'tasks');
        push_to_user($db, (int)$user['id'], [
            'title' => $it['title'], 'body' => $line, 'url' => 'tasks', 'tag' => 'task-' . $it['task_id'],
        ], 'high');
        if ((int)$user['reminder_email'] === 1) {
            $subject = 'Reminder: ' . $it['title'];
            send_branded_mail($user['email'], $user['full_name'], $subject, 'task_reminder', [
                'subject' => $subject, 'item' => $it, 'when' => $when, 'open_url' => APP_URL . '/tasks',
            ], $business);
        }
        $done['reminders']++;
    } catch (Throwable $e) {
        $done['errors']++;
        error_log('Nfelia planner reminder ' . $row['id'] . ': ' . $e->getMessage());
    }
}

/* ── 2. The morning plan ───────────────────────────────────── */

$hour = (int)$now->format('G');
if ($hour >= 7 && $hour < 11) {
    $people = $db->prepare("
        SELECT id, business_id, full_name, email, role, morning_email
        FROM users u
        WHERE is_active = 1
          AND NOT EXISTS (SELECT 1 FROM planner_sends s WHERE s.user_id = u.id AND s.kind = 'morning' AND s.send_date = ?)
        ORDER BY id
        LIMIT 500");
    $people->execute([$today]);
    $claim = $db->prepare("INSERT IGNORE INTO planner_sends (user_id, kind, send_date) VALUES (?, 'morning', ?)");

    foreach ($people->fetchAll() as $user) {
        // Claimed first, whatever happens next, so nobody hears twice in a day.
        $claim->execute([$user['id'], $today]);
        if ($claim->rowCount() !== 1) continue;

        try {
            $business = planner_cron_business($db, (int)$user['business_id'], $now);
            if (!$business) { $done['skipped']++; continue; }

            $day     = planner_day_for_user($db, $user, $business, $today);
            $summary = planner_day_summary($day, $business['currency_symbol'] ?? '₦');
            if ($summary['count'] === 0) { $done['quiet']++; continue; }

            $title = 'Your day: ' . $summary['count'] . ' thing' . ($summary['count'] > 1 ? 's' : '') . ' today';
            planner_notify($db, (int)$business['id'], (int)$user['id'], $title, $summary['line'], 'tasks');
            push_to_user($db, (int)$user['id'], ['title' => $title, 'body' => $summary['line'], 'url' => 'tasks', 'tag' => 'morning']);
            if ((int)$user['morning_email'] === 1) {
                send_branded_mail($user['email'], $user['full_name'], $title, 'morning_plan', [
                    'subject' => $title, 'first_name' => planner_first_name($user['full_name']),
                    'day' => $day, 'summary' => $summary['line'], 'today' => $today, 'open_url' => APP_URL . '/tasks',
                ], $business);
            }
            $done['mornings']++;
        } catch (Throwable $e) {
            $done['errors']++;
            error_log('Nfelia planner morning plan for user ' . $user['id'] . ': ' . $e->getMessage());
        }
    }
}

$db->query("SELECT RELEASE_LOCK('nfelia_planner')");

echo '[planner] ' . $now->format('Y-m-d H:i') . ' ' . implode(' ', array_map(fn($k, $v) => "{$k}={$v}", array_keys($done), $done)) . "\n";
