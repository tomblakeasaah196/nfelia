<?php
/**
 * Health (Admin and Support): is everything behind Nfelia working? The
 * cron, webhooks, emails and phone notifications, errors, the server.
 *
 *   GET  health                       every check (app/ops/monitor.php)
 *   GET  health/sends                 emails and pushes that failed or are being retried (?channel, ?status)
 *   POST health/sends/{id}/retry      try a failed one again now
 */
require_once BASE_PATH . '/app/ops/monitor.php';

class OpsHealthController
{
    private const PAGE = 50;

    public function index(): void
    {
        $db = get_db();
        $checks = ops_health($db);
        $groups = [];
        foreach ($checks as $c) {
            $groups[$c['group']][] = $c;
        }
        $beats = $db->query("SELECT * FROM ops_heartbeats ORDER BY name")->fetchAll();
        ops_render('health/index', ['groups' => $groups, 'state' => ops_health_state($checks), 'beats' => $beats], 'Health');
    }

    public function sends(): void
    {
        $db = get_db();
        $channel = in_array($_GET['channel'] ?? '', ['email', 'push'], true) ? $_GET['channel'] : '';
        $status  = in_array($_GET['status'] ?? '', ['failed', 'retrying', 'skipped'], true) ? $_GET['status'] : 'problems';
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $where = ["o.created_at > NOW() - INTERVAL 30 DAY"];
        $args  = [];
        if ($channel) { $where[] = 'o.channel = ?'; $args[] = $channel; }
        $where[] = match ($status) {
            'failed'   => "o.status = 'failed'",
            'retrying' => "o.status IN ('pending', 'sending') AND o.attempts > 0",
            'skipped'  => "o.status = 'skipped'",
            default    => "(o.status = 'failed' OR (o.status IN ('pending', 'sending') AND o.attempts > 0))",
        };
        $sql = "FROM notify_outbox o
                LEFT JOIN businesses b ON b.id = o.business_id
                LEFT JOIN users u ON u.id = o.user_id
                LEFT JOIN ops_staff s ON s.id = o.staff_id
                WHERE " . implode(' AND ', $where);
        $stmt = $db->prepare("SELECT COUNT(*) {$sql}");
        $stmt->execute($args);
        $total = (int)$stmt->fetchColumn();
        $pages = max(1, (int)ceil($total / self::PAGE));
        $page  = min($page, $pages);
        $stmt = $db->prepare("SELECT o.id, o.channel, o.event, o.status, o.attempts, o.last_error, o.created_at, o.next_attempt_at, o.recipient,
                                     o.business_id, b.name AS business_name, u.full_name AS user_name, s.full_name AS staff_name
                              {$sql} ORDER BY o.id DESC LIMIT " . self::PAGE . " OFFSET " . (($page - 1) * self::PAGE));
        $stmt->execute($args);
        ops_render('health/sends', ['rows' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages,
                                    'filter' => ['channel' => $channel, 'status' => $status]], 'Failed sends');
    }

    public function retry(string $id): void
    {
        $db = get_db();
        $stmt = $db->prepare("UPDATE notify_outbox SET status = 'pending', attempts = 0, next_attempt_at = NOW(), last_error = CONCAT('tried again from Ops: ', COALESCE(last_error, ''))
                              WHERE id = ? AND status IN ('failed', 'skipped')");
        $stmt->execute([(int)$id]);
        if ($stmt->rowCount()) {
            ops_audit('send.retry', 'Sent a failed email or notification again (#' . (int)$id . ')', ['type' => 'outbox', 'id' => (int)$id]);
            $done = notify_flush([(int)$id]);
            ops_flash($done['sent'] ? 'success' : 'error', $done['sent'] ? 'Sent.' : 'It failed again; the reason is in the list. It will be retried later as usual.');
        }
        ops_redirect(ops_back('health/sends'));
    }
}
