<?php
/**
 * Your Ops alerts: the inbox, opening one (marked read, then its page),
 * and phone alerts on this device (ops-sw.js).
 *
 *   GET  alerts                 newest first (?before=id)
 *   GET  alerts/open/{id}       mark read, open its page (through sign-in when needed)
 *   POST alerts/read-all
 *   POST alerts/subscribe       this device's push subscription (JSON)
 *   POST alerts/unsubscribe
 *   POST alerts/prefs           phone alerts on or off, the morning email on or off
 */
require_once BASE_PATH . '/app/ops/alerts.php';

class OpsAlertsController
{
    private const PAGE = 40;
    private PDO $db;
    private array $me;

    public function __construct()
    {
        $this->db = get_db();
        $this->me = ops_staff();
    }

    public function index(): void
    {
        $before = (int)($_GET['before'] ?? 0);
        $stmt = $this->db->prepare("SELECT * FROM ops_alerts WHERE staff_id = ?" . ($before ? " AND id < " . $before : '') . " ORDER BY id DESC LIMIT " . (self::PAGE + 1));
        $stmt->execute([$this->me['id']]);
        $rows = $stmt->fetchAll();
        ops_render('alerts', ['rows' => array_slice($rows, 0, self::PAGE), 'more' => count($rows) > self::PAGE,
                              'unread' => ops_alerts_unread($this->db, (int)$this->me['id'])], 'Alerts');
    }

    public function open(string $id): void
    {
        $stmt = $this->db->prepare("SELECT * FROM ops_alerts WHERE id = ?");
        $stmt->execute([(int)$id]);
        $alert = $stmt->fetch();
        if (!$alert || (int)$alert['staff_id'] !== (int)$this->me['id']) {
            ops_render('message', ['heading' => 'This alert is someone else\'s', 'text' => 'It was sent to another person on the team. Your own alerts are under Alerts.'], 'Alert', 403);
        }
        $this->db->prepare("UPDATE ops_alerts SET read_at = COALESCE(read_at, NOW()) WHERE id = ?")->execute([$alert['id']]);
        ops_redirect(ops_safe_next($alert['url']));
    }

    public function read_all(): void
    {
        $this->db->prepare("UPDATE ops_alerts SET read_at = NOW() WHERE staff_id = ? AND read_at IS NULL")->execute([$this->me['id']]);
        ops_redirect('alerts');
    }

    public function subscribe(): void
    {
        $in = ops_input();
        $endpoint = (string)($in['endpoint'] ?? '');
        $p256dh   = (string)($in['keys']['p256dh'] ?? '');
        $auth     = (string)($in['keys']['auth'] ?? '');
        // Only real browser push services, as for the app (app/helpers/webpush.php)
        if (!push_endpoint_allowed($endpoint)) {
            ops_json(['ok' => false, 'message' => 'This browser gave an address phone alerts cannot use.'], 400);
        }
        // The keys must be what a browser makes (a 65-byte P-256 point and 16
        // bytes of auth), or every alert to this device would fail at encryption.
        if (!push_subscription_ok($endpoint, $p256dh, $auth)) {
            ops_json(['ok' => false, 'message' => 'This browser did not give what phone alerts need.'], 400);
        }
        $this->db->prepare("
            INSERT INTO ops_push_subscriptions (staff_id, endpoint, endpoint_hash, p256dh, auth) VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE staff_id = VALUES(staff_id), p256dh = VALUES(p256dh), auth = VALUES(auth), failures = 0
        ")->execute([$this->me['id'], $endpoint, hash('sha256', $endpoint), $p256dh, $auth]);
        // At most PUSH_MAX_DEVICES each: the oldest go first
        $this->db->prepare("DELETE FROM ops_push_subscriptions WHERE staff_id = ? AND id NOT IN (
                                SELECT id FROM (SELECT id FROM ops_push_subscriptions WHERE staff_id = ? ORDER BY id DESC LIMIT " . PUSH_MAX_DEVICES . ") newest)")
           ->execute([$this->me['id'], $this->me['id']]);
        ops_audit('alerts.device', 'Turned on alerts on a device (' . passkey_device_name((string)($_SERVER['HTTP_USER_AGENT'] ?? '')) . ')');
        ops_json(['ok' => true]);
    }

    public function unsubscribe(): void
    {
        $endpoint = (string)(ops_input()['endpoint'] ?? '');
        $this->db->prepare("DELETE FROM ops_push_subscriptions WHERE endpoint_hash = ? AND staff_id = ?")->execute([hash('sha256', $endpoint), $this->me['id']]);
        ops_json(['ok' => true]);
    }

    public function prefs(): void
    {
        $push   = !empty($_POST['alert_push']) ? 1 : 0;
        $digest = !empty($_POST['digest_email']) ? 1 : 0;
        $this->db->prepare("UPDATE ops_staff SET alert_push = ?, digest_email = ? WHERE id = ?")->execute([$push, $digest, $this->me['id']]);
        ops_flash('success', 'Saved.');
        ops_redirect('account#alerts');
    }
}
