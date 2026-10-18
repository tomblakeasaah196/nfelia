<?php
/**
 * Nfelia — Dashboard Controller
 * The Command Center: financial summary, activity feed, omni-search.
 */
class DashboardController {

    private $db;
    private $biz_id;

    public function __construct() {
        if (!is_logged_in()) {
            redirect('login');
        }
        $this->db     = get_db();
        $this->biz_id = auth_business_id();
    }

    /**
     * Home: the day and the money at a glance (views/dashboard/index.php).
     */
    public function index() {
        $user     = auth_user();
        $business = auth_business();
        $now      = planner_now();
        $today    = $now->format('Y-m-d');
        $month    = $now->format('Y-m-01');

        // Invoices past their due date become overdue before anything is counted.
        $this->db->prepare("
            UPDATE invoices SET status = 'overdue'
            WHERE status IN ('sent','partially_paid') AND due_date < CURDATE() AND business_id = ?
        ")->execute([$this->biz_id]);

        // Money this month (owners and admins see it).
        $stmt = $this->db->prepare("SELECT * FROM v_dashboard_summary WHERE business_id = ?");
        $stmt->execute([$this->biz_id]);
        $summary = $stmt->fetch() ?: ['total_outstanding' => 0, 'total_expenses_this_month' => 0];
        // Money in this month: the same basis as the profit and loss (money_in)
        $money = [
            'in'    => money_in($this->db, $this->biz_id, $month, date('Y-m-t', strtotime($month))),
            'owed'  => (float)($summary['total_outstanding'] ?? 0),
            'spent' => (float)($summary['total_expenses_this_month'] ?? 0),
        ];

        // What needs someone: each count is the true count, each links to its list.
        $count = function (string $sql) {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$this->biz_id]);
            return $stmt->fetch(PDO::FETCH_NUM);
        };
        [$overdue_count, $overdue_value] = $count("SELECT COUNT(*), COALESCE(SUM(balance_due), 0) FROM invoices WHERE business_id = ? AND status = 'overdue'");
        [$orders_waiting]   = $count("SELECT COUNT(*) FROM orders WHERE business_id = ? AND status IN ('pending', 'pending_verification')");
        [$low_stock_count]  = $count("SELECT COUNT(*) FROM v_low_stock_products WHERE business_id = ?");
        [$deliveries_open]  = $count("SELECT COUNT(*) FROM deliveries WHERE business_id = ? AND deleted_at IS NULL AND status IN ('pending_dispatch', 'dispatched', 'in_transit')");   // D9
        [$quotes_accepted]  = $count("SELECT COUNT(*) FROM quotations WHERE business_id = ? AND status = 'accepted'");

        // Today, from Tasks and Calendar: late things first, then today's.
        $day   = planner_day_for_user($this->db, $user, $business, $today);
        $plan  = array_merge($day['late'], $day['today'], $day['soon']);

        // The latest invoices.
        $stmtRecent = $this->db->prepare("
            SELECT i.id, i.doc_number, i.status, i.total, i.issue_date, i.due_date, i.balance_due,
                   c.full_name as customer_name
            FROM invoices i
            LEFT JOIN customers c ON c.id = i.customer_id
            WHERE i.business_id = ?
            ORDER BY i.created_at DESC
            LIMIT 5
        ");
        $stmtRecent->execute([$this->biz_id]);
        $recent_invoices = $stmtRecent->fetchAll();

        $hour = (int)$now->format('G');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

        $title     = "Home";
        $view_file = BASE_PATH . '/views/dashboard/index.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /**
     * Global Omni-Search (AJAX)
     */
    public function search() {
        if (!is_ajax()) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }

        $q = trim($_POST['query'] ?? '');
        if (strlen($q) < 2) {
            json_response(['results' => []]);
        }

        $like_q  = "%{$q}%";
        $results = [];

        // Customers
        $stmtC = $this->db->prepare("
            SELECT id, full_name, phone
            FROM customers
            WHERE business_id = ? AND deleted_at IS NULL AND (full_name LIKE ? OR phone LIKE ?)
            LIMIT 3
        ");
        $stmtC->execute([$this->biz_id, $like_q, $like_q]);
        foreach ($stmtC->fetchAll() as $row) {
            $results[] = [
                'type' => 'Client',
                'text' => $row['full_name'],
                'sub'  => $row['phone'] ?: 'No phone',
                'url'  => APP_URL . '/customers/view/' . $row['id']
            ];
        }

        // Products
        $stmtP = $this->db->prepare("
            SELECT id, name, sku, sell_price, stock_qty, is_service
            FROM products
            WHERE business_id = ? AND deleted_at IS NULL AND (name LIKE ? OR sku LIKE ?)
            LIMIT 3
        ");
        $stmtP->execute([$this->biz_id, $like_q, $like_q]);
        foreach ($stmtP->fetchAll() as $row) {
            $results[] = [
                'type' => $row['is_service'] ? 'Service' : 'Stock',
                'text' => $row['name'],
                'sub'  => planner_money((float)$row['sell_price'], auth_business()['currency_symbol'] ?? '₦')
                        . ($row['is_service'] ? '' : ' · ' . (float)$row['stock_qty'] . ' in stock'),
                'url'  => APP_URL . '/products/view/' . $row['id']
            ];
        }

        // Invoices
        $stmtI = $this->db->prepare("
            SELECT i.id, i.doc_number, i.total, c.full_name as customer_name
            FROM invoices i
            LEFT JOIN customers c ON c.id = i.customer_id
            WHERE i.business_id = ? AND (i.doc_number LIKE ? OR c.full_name LIKE ?)
            LIMIT 3
        ");
        $stmtI->execute([$this->biz_id, $like_q, $like_q]);
        foreach ($stmtI->fetchAll() as $row) {
            $results[] = [
                'type' => 'Invoice',
                'text' => $row['doc_number'] . ' — ' . ($row['customer_name'] ?? ''),
                'sub'  => planner_money((float)$row['total'], auth_business()['currency_symbol'] ?? '₦'),
                'url'  => APP_URL . '/invoices/view/' . $row['id']
            ];
        }

        // Quotations
        $stmtQ = $this->db->prepare("
            SELECT q.id, q.doc_number, q.total, c.full_name as customer_name
            FROM quotations q
            LEFT JOIN customers c ON c.id = q.customer_id
            WHERE q.business_id = ? AND (q.doc_number LIKE ? OR c.full_name LIKE ?)
            LIMIT 2
        ");
        $stmtQ->execute([$this->biz_id, $like_q, $like_q]);
        foreach ($stmtQ->fetchAll() as $row) {
            $results[] = [
                'type' => 'Quote',
                'text' => $row['doc_number'] . ' — ' . ($row['customer_name'] ?? ''),
                'sub'  => planner_money((float)$row['total'], auth_business()['currency_symbol'] ?? '₦'),
                'url'  => APP_URL . '/quotations/view/' . $row['id']
            ];
        }

        json_response(['results' => $results]);
    }
}