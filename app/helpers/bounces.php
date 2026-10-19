<?php
/**
 * Email addresses that bounce: the mail server said the address doesn't
 * exist (NfeliaSMTP::no_such_address() in app/helpers/mail.php). The outbox
 * gives up on that email at once, since another try can't help, and whoever
 * can fix the address hears once, by bell and phone (the address may be
 * their own):
 *
 *   someone in a business       their owner, to change it in Settings
 *   someone on the Nfelia team  the Ops admins, to change it in Team
 *   a shop's customer           the owner, on the order or booking it was
 *                               about, which offers a WhatsApp message
 *                               asking for the right address
 *
 * Anyone else (a lead, someone asking for records) is only noted in Ops.
 */

/** Tell whoever can fix the address an outbox email bounced from (notify_outcome()). Never throws. */
function bounce_tell(PDO $db, array $row): void
{
    try {
        $email = trim((string)$row['recipient']);
        if ($email === '') {
            return;
        }
        $key = strtolower($email);

        // Someone signed up to a business: the email's own person, or one with this address
        $stmt = $db->prepare("SELECT id, business_id, role, full_name FROM users WHERE "
                             . (!empty($row['user_id']) ? 'id = ?' : 'email = ?') . " AND is_active = 1 LIMIT 1");
        $stmt->execute([!empty($row['user_id']) ? (int)$row['user_id'] : $email]);
        if ($user = $stmt->fetch()) {
            notify('email.bounced', (int)$user['business_id'],
                   ['user_id' => (int)$user['id'], 'name' => $user['full_name'], 'email' => $email, 'self' => $user['role'] === 'owner'],
                   ['dedupe' => 'user:' . (int)$user['id'] . ':' . $key, 'channels' => ['push']]);
            return;
        }
        if (!empty($row['user_id'])) {
            return;   // someone who has left
        }

        // Someone on the Nfelia team
        $stmt = $db->prepare("SELECT full_name FROM ops_staff WHERE email = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$email]);
        if (($name = $stmt->fetchColumn()) !== false) {
            require_once BASE_PATH . '/app/ops/alerts.php';
            ops_alert($db, 'email.bounced', 'Emails to ' . $name . ' don’t arrive',
                      'The mail server says ' . $email . ' doesn’t exist. Change it in Team.', 'team', 'bounce:' . $key,
                      ['permission' => 'team']);
            return;
        }

        // A shop's customer, about an order or a booking
        $about = !empty($row['business_id']) ? bounce_about($db, $row) : null;
        if ($about) {
            notify('email.bounced_shopper', (int)$row['business_id'], $about + ['email' => $email],
                   ['dedupe' => $about['kind'] . ':' . $about[$about['kind'] . '_id'] . ':' . $key, 'channels' => ['push']]);
        }
    } catch (Throwable $e) {
        error_log('Nfelia bounce_tell #' . ($row['id'] ?? '?') . ': ' . $e->getMessage());
    }
}

/**
 * The order or booking a shop's email was about, from its outbox row: the
 * dedupe key names it (order:12:confirmed, order-received:12, booking:3:…,
 * refund:5:…). Anything else (a deed): their latest order with that address.
 */
function bounce_about(PDO $db, array $row): ?array
{
    $biz = (int)$row['business_id'];
    $key = (string)($row['dedupe_key'] ?? '');
    $order_id = $booking_id = 0;
    if (preg_match('/^order(?:-received)?:(\d+)/', $key, $m)) {
        $order_id = (int)$m[1];
    } elseif (preg_match('/^booking:(\d+)/', $key, $m)) {
        $booking_id = (int)$m[1];
    } elseif (preg_match('/^refund:(\d+)/', $key, $m)) {
        $stmt = $db->prepare("SELECT order_id FROM refunds WHERE id = ? AND business_id = ?");
        $stmt->execute([(int)$m[1], $biz]);
        $order_id = (int)$stmt->fetchColumn();
    } else {
        $stmt = $db->prepare("SELECT id FROM orders WHERE business_id = ? AND customer_email = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$biz, $row['recipient']]);
        $order_id = (int)$stmt->fetchColumn();
    }

    if ($order_id) {
        $stmt = $db->prepare("SELECT id, order_number, customer_name FROM orders WHERE id = ? AND business_id = ?");
        $stmt->execute([$order_id, $biz]);
        $o = $stmt->fetch();
        return $o ? ['kind' => 'order', 'order_id' => (int)$o['id'], 'number' => $o['order_number'], 'customer' => $o['customer_name']] : null;
    }
    if ($booking_id) {
        $stmt = $db->prepare("SELECT id, booking_number, customer_name FROM bookings WHERE id = ? AND business_id = ?");
        $stmt->execute([$booking_id, $biz]);
        $b = $stmt->fetch();
        return $b ? ['kind' => 'booking', 'booking_id' => (int)$b['id'], 'number' => $b['booking_number'], 'customer' => $b['customer_name']] : null;
    }
    return null;
}

/**
 * The customer's right address, typed on the order or booking it bounced
 * on: it goes on their record too, when the record had the one that bounced
 * (or none), so their next order uses it. Returns the address, or null when
 * it isn't one.
 */
function bounce_new_email(PDO $db, int $business_id, string $table, array $row, string $typed): ?string
{
    $email = trim($typed);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        return null;
    }
    $table = $table === 'bookings' ? 'bookings' : 'orders';
    $db->prepare("UPDATE {$table} SET customer_email = ? WHERE id = ? AND business_id = ?")->execute([$email, (int)$row['id'], $business_id]);
    if (!empty($row['customer_id'])) {
        $db->prepare("UPDATE customers SET email = ? WHERE id = ? AND business_id = ? AND (email IS NULL OR email = '' OR email = ?)")
           ->execute([$email, (int)$row['customer_id'], $business_id, (string)$row['customer_email']]);
    }
    return $email;
}

/**
 * Whether the last email this business sent to the address bounced: its
 * time, or null. A later email that went through clears it, and so does
 * changing the address.
 */
function bounce_at(PDO $db, int $business_id, ?string $email): ?string
{
    $email = trim((string)$email);
    if ($email === '') {
        return null;
    }
    $stmt = $db->prepare("SELECT status, last_error, created_at FROM notify_outbox
                          WHERE business_id = ? AND recipient = ? AND channel = 'email' AND status IN ('sent', 'failed')
                          ORDER BY id DESC LIMIT 1");
    $stmt->execute([$business_id, $email]);
    $last = $stmt->fetch();
    return $last && $last['status'] === 'failed' && str_starts_with((string)$last['last_error'], 'bounced') ? $last['created_at'] : null;
}
