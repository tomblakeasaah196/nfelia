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
 * 3. Low stock, once a day from 09:00, for people who turned it on.
 *
 * 4. Quotes past their date: a sent quote turns Expired the day after its
 *    expiry date, so it stops counting as waiting on an answer.
 *
 * 5. Free trials and paid months that end within three days: a bell note for
 *    the business, once (check_expiring_trials() and check_ending_months() in
 *    app/helpers/subscription_helpers.php).
 *
 * The phone buzzes only for people who want it, and never in their quiet
 * hours (app/helpers/notify.php); the bell entry is always made.
 *
 * Every time here is Lagos time from planner_now(), the same clock remind_at
 * was worked out with when the task was saved.
 *
 * ea-php83 is the PHP 8.3 bin/deploy.sh uses; /usr/local/bin/php is the
 * server's default PHP, which may be older.
 */
//   */5 * * * * /usr/local/bin/ea-php83 /home/smartqaq/public_html/nfelia.com/cron/planner.php >/dev/null 2>&1

// cron/ is copied into the public docroot: never run it from the web.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/../app/helpers/functions.php';
require_once __DIR__ . '/../app/helpers/mail.php';
require_once __DIR__ . '/../app/helpers/webpush.php';
require_once __DIR__ . '/../app/helpers/planner.php';
require_once __DIR__ . '/../app/helpers/notify.php';
require_once __DIR__ . '/../app/helpers/subscription_helpers.php';   // never in web code: it redeclares is_owner()

$db = get_db();

// One run at a time, even when one takes longer than five minutes.
if ((int)$db->query("SELECT GET_LOCK('nfelia_planner', 0)")->fetchColumn() !== 1) {
    echo "[planner] another run is busy, skipping\n";
    exit(0);
}

$now   = planner_now();
$now_s = $now->format('Y-m-d H:i:s');
$today = $now->format('Y-m-d');
$done  = ['reminders' => 0, 'stale' => 0, 'skipped' => 0, 'mornings' => 0, 'quiet' => 0, 'low_stock' => 0, 'hushed' => 0,
          'quotes_expired' => 0, 'trial_notes' => 0, 'month_notes' => 0, 'errors' => 0];

/** Whether this person's phone may buzz now for a reminder or the morning plan. */
function planner_cron_may_buzz(array $user, DateTimeImmutable $now, array &$done): bool {
    if ((int)($user['notify_reminders'] ?? 1) !== 1) return false;
    if (notify_quiet($user, $now)) { $done['hushed']++; return false; }
    return true;
}

/** A business that can use the app right now (with its subscription), or null. */
function planner_cron_business(PDO $db, int $id, DateTimeImmutable $now): ?array {
    static $cache = [];
    if (!array_key_exists($id, $cache)) {
        $stmt = $db->prepare("
            SELECT b.*, s.status AS sub_status, s.trial_ends_at, s.current_period_end, s.grace_ends_at, s.expiry_exempt
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
        SELECT id, business_id, full_name, email, role, reminder_email, morning_email,
               notify_reminders, quiet_hours, quiet_from, quiet_to
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
        if (planner_cron_may_buzz($user, $now, $done)) {
            push_to_user($db, (int)$user['id'], [
                'title' => $it['title'], 'body' => $line, 'url' => 'tasks', 'tag' => 'task-' . $it['task_id'],
            ], 'high');
        }
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
        SELECT id, business_id, full_name, email, role, morning_email,
               notify_reminders, quiet_hours, quiet_from, quiet_to
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
            if (planner_cron_may_buzz($user, $now, $done)) {
                push_to_user($db, (int)$user['id'], ['title' => $title, 'body' => $summary['line'], 'url' => 'tasks', 'tag' => 'morning']);
            }
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

/* ── 3. Low stock ──────────────────────────────────────────── */

if ($hour >= 9 && $hour < 21) {
    $people = $db->prepare("
        SELECT id, business_id, quiet_hours, quiet_from, quiet_to
        FROM users u
        WHERE is_active = 1 AND notify_low_stock = 1
          AND NOT EXISTS (SELECT 1 FROM planner_sends s WHERE s.user_id = u.id AND s.kind = 'low_stock' AND s.send_date = ?)
        ORDER BY id
        LIMIT 500");
    $people->execute([$today]);
    $claim = $db->prepare("INSERT IGNORE INTO planner_sends (user_id, kind, send_date) VALUES (?, 'low_stock', ?)");
    // The three most urgent (empty shelves first), with how many in all.
    $low = $db->prepare("
        SELECT name, stock_qty, COUNT(*) OVER () AS n, SUM(stock_qty <= 0) OVER () AS out_n
        FROM v_low_stock_products WHERE business_id = ?
        ORDER BY stock_qty <= 0 DESC, units_needed DESC, name
        LIMIT 3");

    foreach ($people->fetchAll() as $user) {
        // Quiet now: try again on a later run today, once the quiet hours end.
        if (notify_quiet($user, $now)) { $done['hushed']++; continue; }
        $claim->execute([$user['id'], $today]);
        if ($claim->rowCount() !== 1) continue;
        try {
            $business = planner_cron_business($db, (int)$user['business_id'], $now);
            if (!$business) { $done['skipped']++; continue; }
            $low->execute([$business['id']]);
            $items = $low->fetchAll();
            if (!$items) continue;
            $n     = (int)$items[0]['n'];
            $out   = (int)$items[0]['out_n'];
            $title = $n . ($n === 1 ? ' item ' : ' items ')
                   . ($out === $n ? ($n === 1 ? 'is out of stock' : 'are out of stock')
                   : ($out === 0 ? ($n === 1 ? 'is running low' : 'are running low') : 'need restocking'));
            $names = array_map(fn($p) => $p['name'] . ((float)$p['stock_qty'] <= 0 ? ' (none left)' : ' (' . (float)$p['stock_qty'] . ' left)'), $items);
            $body  = implode(', ', $names) . ($n > 3 ? ', and ' . ($n - 3) . ' more.' : '.');
            planner_notify($db, (int)$business['id'], (int)$user['id'], $title, $body, 'products?stock=restock');
            push_to_user($db, (int)$user['id'], ['title' => $title, 'body' => $body, 'url' => 'products?stock=restock', 'tag' => 'low-stock']);
            $done['low_stock']++;
        } catch (Throwable $e) {
            $done['errors']++;
            error_log('Nfelia low stock for user ' . $user['id'] . ': ' . $e->getMessage());
        }
    }
}

/* ── 4. Quotes past their date ─────────────────────────────── */

// Every run, so the first run after midnight catches yesterday's; most runs
// change nothing. Only sent quotes: one the customer replied to, or is
// signing, is still being talked over.
try {
    $expire = $db->prepare("
        UPDATE quotations SET status = 'expired'
        WHERE status = 'sent' AND expiry_date IS NOT NULL AND expiry_date < ?");
    $expire->execute([$today]);
    $done['quotes_expired'] = $expire->rowCount();
} catch (Throwable $e) {
    $done['errors']++;
    error_log('Nfelia planner quote expiry: ' . $e->getMessage());
}

/* ── 5. Free trials about to end ───────────────────────────── */

try {
    $done['trial_notes'] = check_expiring_trials();
    $done['month_notes'] = check_ending_months();
} catch (Throwable $e) {
    $done['errors']++;
    error_log('Nfelia planner trial check: ' . $e->getMessage());
}

$db->query("SELECT RELEASE_LOCK('nfelia_planner')");

echo '[planner] ' . $now->format('Y-m-d H:i') . ' ' . implode(' ', array_map(fn($k, $v) => "{$k}={$v}", array_keys($done), $done)) . "\n";
