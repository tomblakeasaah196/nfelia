<?php
/**
 * Nfelia — Customer Controller (CRM Module)
 * Full CRUD + financial profile with aggregated history across
 * quotations, invoices, payments, receipts, and debts.
 */
class CustomerController {

    private $db;
    private $biz_id;

    public function __construct() {
        if (!is_logged_in()) { redirect('login'); }
        $this->db     = get_db();
        $this->biz_id = auth_business_id();
    }

    /* ──────────────────────────────────────────
     *  LIST — all customers with filters
     * ────────────────────────────────────────── */
    public function index() {
        $user     = auth_user();
        $business = auth_business();

        $search   = trim($_GET['q'] ?? '');
        $filter   = trim($_GET['filter'] ?? '');   // owing | clear | inactive
        $sort     = trim($_GET['sort'] ?? 'name');
        $page     = max(1, (int)($_GET['page'] ?? 1));
        $per_page = 20;
        $offset   = ($page - 1) * $per_page;

        $where  = ["c.business_id = ?", "c.deleted_at IS NULL", "c.is_walk_in = 0"];
        $params = [$this->biz_id];

        if ($search !== '') {
            $where[]  = "(c.full_name LIKE ? OR c.business_name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)";
            $like     = "%{$search}%";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($filter === 'owing') {
            $where[] = "c.outstanding_balance > 0";
        } elseif ($filter === 'clear') {
            $where[] = "c.outstanding_balance <= 0";
        } elseif ($filter === 'inactive') {
            $where[] = "c.is_active = 0";
        } else {
            // Default: show active only (unless specifically filtering inactive)
            if ($filter !== 'all') {
                $where[] = "c.is_active = 1";
            }
        }

        $where_sql = implode(' AND ', $where);
        $order = match($sort) {
            'name'             => 'c.full_name ASC',
            'newest'           => 'c.created_at DESC',
            'oldest'           => 'c.created_at ASC',
            'highest_invoiced' => 'c.total_invoiced DESC',
            'highest_owing'    => 'c.outstanding_balance DESC',
            'highest_received' => 'c.total_received DESC',
            default            => 'c.full_name ASC',
        };

        // Count
        $stmtC = $this->db->prepare("SELECT COUNT(*) FROM customers c WHERE {$where_sql}");
        $stmtC->execute($params);
        $total_items = (int)$stmtC->fetchColumn();
        $total_pages = max(1, ceil($total_items / $per_page));

        // Fetch
        $stmtQ = $this->db->prepare("
            SELECT c.*,
                   (SELECT COUNT(*) FROM invoices i WHERE i.customer_id = c.id AND i.status = 'overdue') AS overdue_invoices
            FROM customers c
            WHERE {$where_sql}
            ORDER BY {$order}
            LIMIT {$per_page} OFFSET {$offset}
        ");
        $stmtQ->execute($params);
        $customers = $stmtQ->fetchAll();

        // Summary (across all active customers)
        $stmtS = $this->db->prepare("
            SELECT
                COUNT(*)                                                     AS total_customers,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END)             AS active_count,
                COALESCE(SUM(total_invoiced), 0)                             AS total_invoiced,
                COALESCE(SUM(total_received), 0)                             AS total_received,
                COALESCE(SUM(outstanding_balance), 0)                        AS total_outstanding,
                SUM(CASE WHEN outstanding_balance > 0 THEN 1 ELSE 0 END)   AS owing_count
            FROM customers WHERE business_id = ? AND deleted_at IS NULL AND is_walk_in = 0
        ");
        $stmtS->execute([$this->biz_id]);
        $summary = $stmtS->fetch();

        $title     = "Clients";
        $view_file = BASE_PATH . '/views/customers/index.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ──────────────────────────────────────────
     *  CREATE
     * ────────────────────────────────────────── */
    public function create() {
        $user     = auth_user();
        $business = auth_business();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            return $this->save_customer(null);
        }

        $customer = [];
        $editing  = false;

        $title     = "New Customer";
        $view_file = BASE_PATH . '/views/customers/form.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ──────────────────────────────────────────
     *  EDIT
     * ────────────────────────────────────────── */
    public function edit($id = null) {
        $user     = auth_user();
        $business = auth_business();

        $customer = $this->find_customer($id);
        if (!$customer) { not_found(); return; }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            return $this->save_customer($id);
        }

        $editing = true;

        $title     = "Edit — " . $customer['full_name'];
        $view_file = BASE_PATH . '/views/customers/form.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ──────────────────────────────────────────
     *  VIEW — CRM Profile Page
     * ────────────────────────────────────────── */
    public function view($id = null) {
        $user     = auth_user();
        $business = auth_business();

        $customer = $this->find_customer($id);
        if (!$customer) { not_found(); return; }

        $currency = $business['currency_symbol'] ?? '₦';

        // Orders: every sale with this client, and money kept for their next one
        $orders = order_rows($this->db, $this->biz_id, ['customer_id' => (int)$id], 20);
        $kept   = kept_balance($this->db, $this->biz_id, (int)$id);

        // Quotations
        $stmtQ = $this->db->prepare("
            SELECT id, doc_number, status, issue_date, total
            FROM quotations
            WHERE customer_id = ? AND business_id = ?
            ORDER BY created_at DESC LIMIT 20
        ");
        $stmtQ->execute([$id, $this->biz_id]);
        $quotations = $stmtQ->fetchAll();

        // Invoices
        $stmtI = $this->db->prepare("
            SELECT id, doc_number, status, issue_date, due_date, total, amount_paid, balance_due
            FROM invoices
            WHERE customer_id = ? AND business_id = ?
            ORDER BY created_at DESC LIMIT 20
        ");
        $stmtI->execute([$id, $this->biz_id]);
        $invoices = $stmtI->fetchAll();

        // Payments (across all invoices)
        $stmtP = $this->db->prepare("
            SELECT p.id, p.amount, p.payment_date, p.method, p.reference,
                   i.doc_number as invoice_number, i.id as invoice_id
            FROM payments p
            JOIN invoices i ON i.id = p.invoice_id
            WHERE i.customer_id = ? AND p.business_id = ?
            ORDER BY p.payment_date DESC LIMIT 20
        ");
        $stmtP->execute([$id, $this->biz_id]);
        $payments = $stmtP->fetchAll();

        // Receipts
        $stmtR = $this->db->prepare("
            SELECT id, doc_number, issue_date, amount, method, invoice_id
            FROM receipts
            WHERE customer_id = ? AND business_id = ?
            ORDER BY created_at DESC LIMIT 20
        ");
        $stmtR->execute([$id, $this->biz_id]);
        $receipts = $stmtR->fetchAll();

        // Debts (receivable)
        $stmtD = $this->db->prepare("
            SELECT id, description, total_amount, amount_settled, balance, due_date, status, source, invoice_id
            FROM debts
            WHERE customer_id = ? AND business_id = ? AND type = 'receivable'
            ORDER BY created_at DESC LIMIT 20
        ");
        $stmtD->execute([$id, $this->biz_id]);
        $debts = $stmtD->fetchAll();

        // Activity stats
        $stmtStats = $this->db->prepare("
            SELECT
                (SELECT COUNT(*) FROM quotations WHERE customer_id = ? AND business_id = ?) AS quote_count,
                (SELECT COUNT(*) FROM invoices WHERE customer_id = ? AND business_id = ?) AS invoice_count,
                (SELECT COUNT(*) FROM invoices WHERE customer_id = ? AND business_id = ? AND status = 'overdue') AS overdue_count,
                (SELECT COUNT(*) FROM receipts WHERE customer_id = ? AND business_id = ?) AS receipt_count
        ");
        $stmtStats->execute([
            $id, $this->biz_id,
            $id, $this->biz_id,
            $id, $this->biz_id,
            $id, $this->biz_id,
        ]);
        $stats = $stmtStats->fetch();

        // Coming up with this customer: appointments, tasks and job steps
        // not yet done (Tasks & Calendar).
        $today    = planner_today();
        $upcoming = array_map(
            fn($t) => planner_task_item($t, $today, $business),
            array_filter(
                planner_fetch_tasks($this->db, $this->biz_id, ['customer_id' => (int)$id, 'from' => $today, 'to' => planner_add_days($today, 60), 'late' => true]),
                fn($t) => $t['done_at'] === null
            )
        );

        // WhatsApp number
        $wa_number = $customer['whatsapp'] ?: $customer['phone'] ?: '';
        $wa_number = wa_digits($wa_number, auth_business());

        $title     = $customer['full_name'];
        $view_file = BASE_PATH . '/views/customers/view.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ──────────────────────────────────────────
     *  DELETE — soft delete
     * ────────────────────────────────────────── */
    public function delete($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('customers'); return; }
        csrf_verify();

        $customer = $this->find_customer($id);
        if (!$customer) { not_found(); return; }

        // Soft delete
        $this->db->prepare("UPDATE customers SET deleted_at = NOW() WHERE id = ? AND business_id = ?")
                  ->execute([$id, $this->biz_id]);

        flash('success', $customer['full_name'] . ' removed.');
        redirect('customers');
    }

    /* ──────────────────────────────────────────
     *  TOGGLE ACTIVE — AJAX or POST
     * ────────────────────────────────────────── */
    public function toggle_active($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('customers'); return; }
        csrf_verify();

        $customer = $this->find_customer($id);
        if (!$customer) {
            if (is_ajax()) { json_response(['error' => 'Customer not found'], 404); }
            not_found(); return;
        }

        $new_status = $customer['is_active'] ? 0 : 1;
        $this->db->prepare("UPDATE customers SET is_active = ? WHERE id = ? AND business_id = ?")
                  ->execute([$new_status, $id, $this->biz_id]);

        if (is_ajax()) {
            json_response(['success' => true, 'is_active' => $new_status]);
        }

        flash('success', $customer['full_name'] . ($new_status ? ' reactivated.' : ' deactivated.'));
        redirect('customers/view/' . $id);
    }

    /* ══════════════════════════════════════════
     *  PRIVATE: save customer
     * ══════════════════════════════════════════ */
    private function save_customer(?int $id): void {
        $full_name     = trim($_POST['full_name'] ?? '');
        $business_name = trim($_POST['business_name'] ?? '');
        $phone         = trim($_POST['phone'] ?? '');
        $whatsapp      = trim($_POST['whatsapp'] ?? '');
        $email         = trim($_POST['email'] ?? '');
        $address       = trim($_POST['address'] ?? '');
        $notes         = trim($_POST['notes'] ?? '');

        // Validate
        $errors = [];
        if ($full_name === '') $errors[] = 'Full name is required.';
        if ($phone === '' && $email === '') $errors[] = 'Please provide at least a phone number or email.';
        if ($p = phone_problem($phone) ?? phone_problem($whatsapp)) $errors[] = $p;   // D4

        // Duplicate check (same name + phone within this business, excluding current record)
        if ($phone !== '') {
            $dup_sql = "SELECT id FROM customers WHERE business_id = ? AND phone = ? AND deleted_at IS NULL";
            $dup_params = [$this->biz_id, $phone];
            if ($id) {
                $dup_sql .= " AND id != ?";
                $dup_params[] = $id;
            }
            $stmtDup = $this->db->prepare($dup_sql);
            $stmtDup->execute($dup_params);
            if ($stmtDup->fetch()) {
                $errors[] = 'A customer with this phone number already exists.';
            }
        }

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            // Keep what was typed; an edit keeps its own id (D1)
            $editing  = $id !== null;
            $customer = array_merge($editing ? ($this->find_customer($id) ?: []) : [], $_POST);
            $user     = auth_user();
            $business = auth_business();
            $title    = $editing ? "Edit Customer" : "New Customer";
            $view_file = BASE_PATH . '/views/customers/form.php';
            require_once BASE_PATH . '/views/layouts/app.php';
            return;
        }

        if ($id) {
            $this->db->prepare("
                UPDATE customers SET
                    full_name = ?, business_name = ?, phone = ?, whatsapp = ?,
                    email = ?, address = ?, notes = ?
                WHERE id = ? AND business_id = ?
            ")->execute([
                $full_name, $business_name ?: null, $phone ?: null, $whatsapp ?: null,
                $email ?: null, $address ?: null, $notes ?: null,
                $id, $this->biz_id
            ]);
            flash('success', $full_name . ' updated.');
        } else {
            $this->db->prepare("
                INSERT INTO customers (business_id, full_name, business_name, phone, whatsapp, email, address, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $this->biz_id,
                $full_name, $business_name ?: null, $phone ?: null, $whatsapp ?: null,
                $email ?: null, $address ?: null, $notes ?: null
            ]);
            $id = (int)$this->db->lastInsertId();
            flash('success', $full_name . ' added.');
        }

        redirect('customers/view/' . $id);
    }

    /* ══════════════════════════════════════════
     *  PRIVATE HELPERS
     * ══════════════════════════════════════════ */

    private function find_customer($id): ?array {
        if (!$id) return null;
        $stmt = $this->db->prepare("SELECT * FROM customers WHERE id = ? AND business_id = ? AND deleted_at IS NULL");
        $stmt->execute([(int)$id, $this->biz_id]);
        return $stmt->fetch() ?: null;
    }
}