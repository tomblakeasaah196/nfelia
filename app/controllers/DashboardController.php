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
     * Load the Command Center Dashboard
     */
    public function index() {
        $user     = auth_user();
        $business = auth_business();

        // 1. Financial Summary (from view)
        $stmt = $this->db->prepare("SELECT * FROM v_dashboard_summary WHERE business_id = ?");
        $stmt->execute([$this->biz_id]);
        $summary = $stmt->fetch() ?: [
            'total_invoiced_this_month' => 0,
            'total_received_this_month' => 0,
            'total_outstanding'         => 0,
            'total_expenses_this_month' => 0
        ];

        // 2. Low Stock Count + Items
        $stmtStock = $this->db->prepare("SELECT * FROM v_low_stock_products WHERE business_id = ? ORDER BY units_needed DESC LIMIT 5");
        $stmtStock->execute([$this->biz_id]);
        $low_stock_items = $stmtStock->fetchAll();
        $low_stock_count = count($low_stock_items);

        // 3. Overdue Invoices Count
        // First, auto-update any newly overdue invoices
        $this->db->prepare("
            UPDATE invoices SET status = 'overdue'
            WHERE status IN ('sent','partially_paid') AND due_date < CURDATE() AND business_id = ?
        ")->execute([$this->biz_id]);

        $stmtOverdue = $this->db->prepare("SELECT COUNT(*) FROM v_overdue_invoices WHERE business_id = ?");
        $stmtOverdue->execute([$this->biz_id]);
        $overdue_count = (int) $stmtOverdue->fetchColumn();

        // 4. Pending Deliveries Count + Items
        $stmtDel = $this->db->prepare("
            SELECT id, doc_number, recipient_name, status
            FROM deliveries
            WHERE business_id = ? AND status IN ('pending_dispatch', 'dispatched', 'in_transit')
            ORDER BY created_at DESC LIMIT 5
        ");
        $stmtDel->execute([$this->biz_id]);
        $pending_delivery_items = $stmtDel->fetchAll();
        $pending_deliveries     = count($pending_delivery_items);

        // 5. Recent Invoices (last 7, with customer name)
        $stmtRecent = $this->db->prepare("
            SELECT i.id, i.doc_number, i.status, i.total, i.issue_date, i.balance_due,
                   c.full_name as customer_name
            FROM invoices i
            LEFT JOIN customers c ON c.id = i.customer_id
            WHERE i.business_id = ?
            ORDER BY i.created_at DESC
            LIMIT 7
        ");
        $stmtRecent->execute([$this->biz_id]);
        $recent_invoices = $stmtRecent->fetchAll();

        // 6. Net Position
        $net_position = (float)($summary['total_received_this_month'] ?? 0) - (float)($summary['total_expenses_this_month'] ?? 0);

        // 7. Time-based greeting
        $hour = (int) date('H');
        if ($hour < 12)     $greeting = "Good morning";
        elseif ($hour < 17) $greeting = "Good afternoon";
        else                $greeting = "Good evening";

        $title = "Command Center";

        // Render via layout shell
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
            SELECT id, name, sku
            FROM products
            WHERE business_id = ? AND deleted_at IS NULL AND (name LIKE ? OR sku LIKE ?)
            LIMIT 3
        ");
        $stmtP->execute([$this->biz_id, $like_q, $like_q]);
        foreach ($stmtP->fetchAll() as $row) {
            $results[] = [
                'type' => 'Stock',
                'text' => $row['name'],
                'sub'  => $row['sku'] ? 'SKU: ' . $row['sku'] : 'No SKU',
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
                'sub'  => money((float)$row['total']),
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
                'sub'  => money((float)$row['total']),
                'url'  => APP_URL . '/quotations/view/' . $row['id']
            ];
        }

        json_response(['results' => $results]);
    }
}