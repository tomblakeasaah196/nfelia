<?php
/**
 * What makes a phone buzz, and when. Every alert also lands in the bell
 * (the notifications table); these rules only decide the buzz.
 *
 *   Money in    payments, receipts, paid and new shop orders: to the owner
 *               and admins who want it, never to the person who recorded it.
 *   Reminders   tasks at their time and the morning plan (cron/planner.php).
 *   Low stock   once a day, when something is running low (cron/planner.php).
 *   Quiet hours no buzz between quiet_from and quiet_to, Lagos time.
 *
 * Choices are per person: More > Notifications (api/ajax/notifications.php).
 */

/** The choices, as stored on users. */
const NOTIFY_FIELDS = ['notify_money', 'notify_reminders', 'notify_low_stock', 'quiet_hours', 'quiet_from', 'quiet_to', 'morning_email', 'reminder_email'];

/** Whether this person's quiet hours cover this moment. */
function notify_quiet(array $user, ?DateTimeImmutable $now = null): bool
{
    if (empty($user['quiet_hours'])) {
        return false;
    }
    $t    = ($now ?? planner_now())->format('H:i:s');
    $from = substr((string)$user['quiet_from'], 0, 8);
    $to   = substr((string)$user['quiet_to'], 0, 8);
    if ($from === $to) {
        return false;
    }
    // 21:00 to 07:00 runs across midnight; 13:00 to 15:00 does not.
    return $from < $to ? ($t >= $from && $t < $to) : ($t >= $from || $t < $to);
}

/**
 * Money came in. The phones of the owner and admins who want it buzz, except
 * whoever recorded it (they already know). Sent after the page has been
 * answered, so recording a payment never waits on a push service.
 */
function notify_money_in(int $biz_id, ?int $actor_id, string $title, string $body, string $url): void
{
    after_response(function () use ($biz_id, $actor_id, $title, $body, $url) {
        try {
            $db = get_db();
            $stmt = $db->prepare("
                SELECT id, quiet_hours, quiet_from, quiet_to FROM users
                WHERE business_id = ? AND is_active = 1 AND role IN ('owner', 'admin') AND notify_money = 1 AND id <> ?
            ");
            $stmt->execute([$biz_id, (int)$actor_id]);
            foreach ($stmt->fetchAll() as $person) {
                if (!notify_quiet($person)) {
                    push_to_user($db, (int)$person['id'], [
                        'title' => $title, 'body' => $body, 'url' => ltrim($url, '/'), 'tag' => 'money-' . substr(md5($url), 0, 12),
                    ], 'high');
                }
            }
        } catch (Throwable $e) {
            error_log('Nfelia money-in push: ' . $e->getMessage());
        }
    });
}

/** "₦35,000" in the business's own currency. */
function notify_money_text(float $amount, ?array $business = null): string
{
    $symbol = ($business ?? auth_business() ?? [])['currency_symbol'] ?? '₦';
    return $symbol . number_format($amount, fmod($amount, 1.0) == 0.0 ? 0 : 2);
}

/** One person's choices, with defaults for anything missing. */
function notify_prefs(int $user_id): array
{
    $stmt = get_db()->prepare("SELECT " . implode(', ', NOTIFY_FIELDS) . " FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch() ?: [];
    return [
        'notify_money'     => (bool)($row['notify_money'] ?? true),
        'notify_reminders' => (bool)($row['notify_reminders'] ?? true),
        'notify_low_stock' => (bool)($row['notify_low_stock'] ?? false),
        'quiet_hours'      => (bool)($row['quiet_hours'] ?? false),
        'quiet_from'       => substr((string)($row['quiet_from'] ?? '21:00'), 0, 5),
        'quiet_to'         => substr((string)($row['quiet_to'] ?? '07:00'), 0, 5),
        'morning_email'    => (bool)($row['morning_email'] ?? true),
        'reminder_email'   => (bool)($row['reminder_email'] ?? true),
    ];
}
