<?php
/**
 * Notifications: the full list behind the bell, and the one link every
 * alert uses, wherever it was seen (bell, phone, email).
 *
 *   GET  /notifications             everything, newest first (?unread=1, ?before=id)
 *   GET  /notifications/open/{id}   mark it read and open the exact page
 *   POST /notifications/read-all    mark everything read
 *
 * Signed out, /notifications/open/{id} goes through sign-in and comes
 * straight back here (app/middleware/auth_check.php keeps the address).
 */
class NotificationsController {

    private const PAGE = 30;

    private PDO $db;
    private array $user;
    private int $biz_id;

    public function __construct() {
        $this->db     = get_db();
        $this->user   = auth_user();
        $this->biz_id = (int)auth_business_id();
    }

    public function index() {
        [$where, $args] = notify_visible_sql($this->biz_id, (int)$this->user['id'], (string)$this->user['role']);
        $unread_only = !empty($_GET['unread']);
        $before      = max(0, (int)($_GET['before'] ?? 0));

        $sql = "SELECT id, event, type, title, message, action_url, is_read, created_at FROM notifications WHERE {$where}";
        if ($unread_only) $sql .= " AND is_read = 0";
        if ($before)      { $sql .= " AND id < ?"; $args[] = $before; }
        $stmt = $this->db->prepare($sql . " ORDER BY id DESC LIMIT " . (self::PAGE + 1));
        $stmt->execute($args);
        $rows = $stmt->fetchAll();

        $more = count($rows) > self::PAGE;
        $notifications = array_slice($rows, 0, self::PAGE);

        [$where, $args] = notify_visible_sql($this->biz_id, (int)$this->user['id'], (string)$this->user['role']);
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM notifications WHERE {$where} AND is_read = 0");
        $stmt->execute($args);
        $unread = (int)$stmt->fetchColumn();

        $title     = 'Notifications';
        $view_file = BASE_PATH . '/views/notifications/index.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /**
     * The link in every alert. Someone else's alert (a shared phone, or a
     * second account) says whose it is instead of showing a stranger's page.
     */
    public function open($id = null) {
        $id = (int)$id;
        [$where, $args] = notify_visible_sql($this->biz_id, (int)$this->user['id'], (string)$this->user['role']);
        $stmt = $this->db->prepare("SELECT id, action_url, is_read FROM notifications WHERE id = ? AND {$where}");
        $stmt->execute([$id, ...$args]);
        $row = $stmt->fetch();

        if (!$row) {
            $stmt = $this->db->prepare("
                SELECT n.business_id, u.full_name FROM notifications n LEFT JOIN users u ON u.id = n.user_id WHERE n.id = ?");
            $stmt->execute([$id]);
            $other = $stmt->fetch();
            if ($other && (int)$other['business_id'] !== $this->biz_id) {
                flash('warning', 'That alert belongs to another Nfelia account. Sign out and sign in to that account to open it.');
            } elseif ($other && $other['full_name']) {
                flash('warning', 'That alert was for ' . planner_first_name($other['full_name']) . '. You are signed in as ' . planner_first_name($this->user['full_name']) . '.');
            } else {
                flash('warning', 'That alert is no longer here.');
            }
            redirect('notifications');
        }

        if (!(int)$row['is_read']) {
            $this->db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ?")->execute([$id]);
        }
        $to = notify_safe_path($row['action_url']);
        redirect($to !== '' ? $to : 'notifications');
    }

    public function read_all() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('notifications');
        csrf_verify();
        [$where, $args] = notify_visible_sql($this->biz_id, (int)$this->user['id'], (string)$this->user['role']);
        $this->db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE {$where} AND is_read = 0")->execute($args);
        flash('success', 'All caught up.');
        redirect('notifications');
    }
}
