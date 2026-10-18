<?php
/**
 * Telling the Ops team: their own bell (ops_alerts) and phones
 * (ops_push_subscriptions, through the same outbox and retries as the app's
 * notifications: notify_queue() with a staff_id).
 *
 * Who hears: everyone active whose role includes the permission (errors and
 * health: Admin and Support). The phone only for what needs someone now,
 * and only for people who left "Alerts on my phone" on.
 *
 * Loaded by app/helpers/errors.php when an error needs telling, and by Ops.
 */

require_once __DIR__ . '/ops.php';

/**
 * One alert to everyone it concerns. $dedupe makes it once per person
 * (the same problem coming back the same day is one alert). Never throws.
 *   $opts: permission (who hears, default errors), push (default true),
 *          email (also by email, for what can't wait: a payment to check)
 */
function ops_alert(PDO $db, string $kind, string $title, string $body, string $url, string $dedupe, array $opts = []): int
{
    $told = 0;
    try {
        $permission = $opts['permission'] ?? 'errors';
        $push       = (bool)($opts['push'] ?? true);
        $email      = (bool)($opts['email'] ?? false);
        $roles = array_keys(array_filter(OPS_PERMISSIONS, fn($p) => in_array('*', $p, true) || in_array($permission, $p, true)));
        $in    = implode(',', array_fill(0, count($roles), '?'));
        $stmt  = $db->prepare("SELECT id, full_name, email, alert_push FROM ops_staff WHERE is_active = 1 AND password_hash IS NOT NULL AND role IN ({$in})");
        $stmt->execute($roles);
        $add = $db->prepare("INSERT IGNORE INTO ops_alerts (staff_id, kind, title, body, url, dedupe_key) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($stmt->fetchAll() as $person) {
            $add->execute([$person['id'], $kind, mb_substr($title, 0, 255), mb_substr($body, 0, 500), mb_substr($url, 0, 255), mb_substr($dedupe, 0, 150)]);
            if ($add->rowCount() !== 1) {
                continue;
            }
            $told++;
            $alert_id = (int)$db->lastInsertId();
            if ($email) {
                notify_email(null, (string)$person['email'], (string)$person['full_name'], 'Urgent: ' . $title, 'ops_message', [
                    'headline' => $title, 'message' => $body, 'button' => 'Open it in Nfelia Ops', 'url' => ops_url('alerts/open/' . $alert_id),
                    'note' => 'You get this because your Ops role includes it.',
                ], 'ops-alert:' . $alert_id, 'ops.' . $kind);
            }
            if ($push && (int)$person['alert_push']) {
                notify_queue($db, [
                    'staff_id' => (int)$person['id'], 'event' => 'ops.' . $kind, 'channel' => 'push', 'urgency' => 'high', 'ttl' => 6 * 3600,
                    'payload'  => ['title' => $title, 'body' => $body, 'url' => ops_url('alerts/open/' . $alert_id), 'tag' => 'ops-' . $kind . '-' . md5($dedupe)],
                ]);
            }
        }
    } catch (Throwable $e) {
        error_log('Nfelia ops_alert ' . $kind . ': ' . $e->getMessage());
    }
    return $told;
}

/** An alert to one person (an error given to them), bell and phone. */
function ops_alert_one(PDO $db, int $staff_id, string $kind, string $title, string $body, string $url, string $dedupe): void
{
    try {
        $add = $db->prepare("INSERT IGNORE INTO ops_alerts (staff_id, kind, title, body, url, dedupe_key) VALUES (?, ?, ?, ?, ?, ?)");
        $add->execute([$staff_id, $kind, mb_substr($title, 0, 255), mb_substr($body, 0, 500), mb_substr($url, 0, 255), mb_substr($dedupe, 0, 150)]);
        $alert_id = $add->rowCount() === 1 ? (int)$db->lastInsertId() : 0;
        $person = $alert_id ? ops_staff_by_id($staff_id) : null;
        if ($person && (int)$person['alert_push']) {
            notify_queue($db, [
                'staff_id' => $staff_id, 'event' => 'ops.' . $kind, 'channel' => 'push', 'ttl' => 6 * 3600,
                'payload'  => ['title' => $title, 'body' => $body, 'url' => ops_url('alerts/open/' . $alert_id), 'tag' => 'ops-' . $kind . '-' . md5($url)],
            ]);
        }
    } catch (Throwable $e) {
        error_log('Nfelia ops_alert_one ' . $kind . ': ' . $e->getMessage());
    }
}

/**
 * A problem that is new, came back after being marked fixed, or is spiking.
 * Fatal and plain errors go to phones; warnings, notices and slow pages to
 * the bell only; deprecations and ignored problems to nobody.
 */
function ops_error_alert(PDO $db, array $group, string $why, ?string $ref, int $count = 0): void
{
    if ($group['status'] === 'ignored' || in_array($group['level'], ['notice', 'deprecated'], true)) {
        return;
    }
    $what = match ($group['kind']) {
        'js'   => 'Browser error',
        'slow' => 'Slow page',
        'job'  => 'Job failed',
        default => match ($group['level']) { 'fatal' => 'Crash', 'warning' => 'Warning', default => 'Error' },
    };
    $title = match ($why) {
        'back'  => 'Came back: ' . $what,
        'spike' => 'Spiking: ' . $what . ' (' . $count . ' in an hour)',
        default => 'New: ' . $what,
    };
    $where = $group['file'] ? ' · ' . basename($group['file']) . ($group['line'] ? ':' . $group['line'] : '') : '';
    $body  = mb_substr($group['title'], 0, 160) . $where;
    $phone = in_array($group['level'], ['fatal', 'error'], true) || $why === 'spike';
    ops_alert($db, 'error.' . $why, $title, $body, 'errors/' . (int)$group['id'] . ($ref ? '?ref=' . $ref : ''),
              'error-' . $why . ':' . $group['id'] . ':' . date('Y-m-d'), ['push' => $phone]);
}

/** Unread alerts for one person (the menu's count, the phone's badge). */
function ops_alerts_unread(PDO $db, int $staff_id): int
{
    $stmt = $db->prepare("SELECT COUNT(*) FROM ops_alerts WHERE staff_id = ? AND read_at IS NULL");
    $stmt->execute([$staff_id]);
    return (int)$stmt->fetchColumn();
}
