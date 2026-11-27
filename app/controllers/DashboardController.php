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
        // Money in and out this month: the same basis as the profit and loss
        // (money_in, money_out: what was paid, when it was paid)
        $month_end = date('Y-m-t', strtotime($month));
        $out       = money_out_by($this->db, $this->biz_id, $month, $month_end, 'category');
        $money = [
            'in'    => money_in($this->db, $this->biz_id, $month, $month_end),
            'owed'  => (float)($summary['total_outstanding'] ?? 0),
            'spent' => money2(array_sum($out)),
            'stock' => $out['stock_purchase'] ?? 0.0,
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

        // The latest orders: every sale starts as one.
        $recent_orders = order_rows($this->db, $this->biz_id, [], 5);
        // "Still owed" opens the orders with a balance, unless older invoices
        // without an order are owed too: then the unpaid invoices hold it all
        $stmt_loose = $this->db->prepare("
            SELECT COUNT(*) FROM invoices i
            WHERE i.business_id = ? AND i.status IN ('sent','partially_paid','overdue') AND i.balance_due > 0
              AND i.order_id IS NULL AND NOT EXISTS (SELECT 1 FROM orders o WHERE o.invoice_id = i.id AND o.business_id = i.business_id)");
        $stmt_loose->execute([$this->biz_id]);
        $owed_url = (int)$stmt_loose->fetchColumn() > 0 ? 'invoices?status=unpaid' : 'orders?view=balance';

        // The free trial's days left, for the owner, who pays: counted as
        // Billing counts them (none on the last day), from billing_access()
        $trial_days_left = null;
        if (is_owner()) {
            $stmt_sub = $this->db->prepare("SELECT * FROM subscriptions WHERE business_id = ?");
            $stmt_sub->execute([$this->biz_id]);
            $sub = $stmt_sub->fetch() ?: null;
            if ($sub && $sub['status'] === 'trial' && !empty($sub['trial_ends_at']) && billing_access($sub) === 'trial') {
                $trial_days_left = max(0, (int)round((strtotime(substr($sub['trial_ends_at'], 0, 10)) - strtotime(date('Y-m-d'))) / 86400));
            }
        }

        // "Get started": the first things that make Nfelia useful, until done
        // or hidden (app/helpers/first_steps.php)
        $first_steps = null;
        if (is_boss()) {
            require_once BASE_PATH . '/app/helpers/first_steps.php';
            $steps = first_steps($this->db, ['id' => $this->biz_id] + $business, (int)$user['id'], is_owner());
            if (first_steps_show($this->db, $steps, (int)$user['id'])) {
                $first_steps = $steps;
            }
        }

        $hour = (int)$now->format('G');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

        $title     = "Home";
        $view_file = BASE_PATH . '/views/dashboard/index.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /** POST dashboard/hide-first-steps: "Get started" no longer shows for this person. */
    public function hide_first_steps() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('dashboard');
        }
        csrf_verify();
        $this->db->prepare("UPDATE users SET first_steps_hidden_at = NOW() WHERE id = ?")->execute([(int)auth_user()['id']]);
        redirect('dashboard');
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
            WHERE business_id = ? AND deleted_at IS NULL AND is_walk_in = 0 AND (full_name LIKE ? OR phone LIKE ?)
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

        // Orders: by number, name or phone
        foreach (order_rows($this->db, $this->biz_id, ['q' => $q], 3) as $row) {
            $results[] = [
                'type' => 'Order',
                'text' => $row['order_number'] . ' — ' . ($row['customer_name'] ?: 'Walk-in customer'),
                'sub'  => planner_money((float)$row['total'], auth_business()['currency_symbol'] ?? '₦'),
                'url'  => APP_URL . '/orders/view/' . $row['id']
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