<?php
/**
 * Nfelia — Supplier Controller
 * Full CRUD + financial profile with aggregated expense/purchase history.
 * Mirrors CustomerController structure.
 */
class SupplierController {

    private $db;
    private $biz_id;

    // Expense categories (shared constant)
    public const CATEGORIES = [
        'stock_purchase'  => 'Stock Purchase',
        'rent'            => 'Rent',
        'utilities'       => 'Utilities',
        'transport'       => 'Transport',
        'salary'          => 'Salary / Wages',
        'marketing'       => 'Marketing',
        'maintenance'     => 'Maintenance',
        'office_supplies' => 'Office Supplies',
        'miscellaneous'   => 'Miscellaneous',
    ];

    public function __construct() {
        if (!is_logged_in()) { redirect('login'); }
        $this->db     = get_db();
        $this->biz_id = auth_business_id();
    }

    /* ──────────────────────────────────────────
     *  LIST
     * ────────────────────────────────────────── */
    public function index() {
        $user     = auth_user();
        $business = auth_business();

        $search   = trim($_GET['q'] ?? '');
        $filter   = trim($_GET['filter'] ?? '');
        $sort     = trim($_GET['sort'] ?? 'name');
        $page     = max(1, (int)($_GET['page'] ?? 1));
        $per_page = 20;
        $offset   = ($page - 1) * $per_page;

        $where  = ["s.business_id = ?", "s.deleted_at IS NULL"];
        $params = [$this->biz_id];

        if ($search !== '') {
            $where[]  = "(s.full_name LIKE ? OR s.business_name LIKE ? OR s.phone LIKE ? OR s.email LIKE ?)";
            $like     = "%{$search}%";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($filter === 'owing') {
            $where[] = "s.outstanding_balance > 0";
        } elseif ($filter === 'clear') {
            $where[] = "s.outstanding_balance <= 0";
        } elseif ($filter === 'inactive') {
            $where[] = "s.is_active = 0";
        } else {
            if ($filter !== 'all') {
                $where[] = "s.is_active = 1";
            }
        }

        $where_sql = implode(' AND ', $where);
        $order = match($sort) {
            'name'              => 's.full_name ASC',
            'newest'            => 's.created_at DESC',
            'oldest'            => 's.created_at ASC',
            'highest_purchased' => 's.total_purchased DESC',
            'highest_owing'     => 's.outstanding_balance DESC',
            'highest_paid'      => 's.total_paid DESC',
            default             => 's.full_name ASC',
        };

        // Count
        $stmtC = $this->db->prepare("SELECT COUNT(*) FROM suppliers s WHERE {$where_sql}");
        $stmtC->execute($params);
        $total_items = (int)$stmtC->fetchColumn();
        $total_pages = max(1, ceil($total_items / $per_page));

        // Fetch
        $stmtQ = $this->db->prepare("
            SELECT s.*,
                   (SELECT COUNT(*) FROM expenses e WHERE e.supplier_id = s.id AND e.payment_status = 'unpaid' AND e.deleted_at IS NULL) AS unpaid_expenses
            FROM suppliers s
            WHERE {$where_sql}
            ORDER BY {$order}
            LIMIT {$per_page} OFFSET {$offset}
        ");
        $stmtQ->execute($params);
        $suppliers = $stmtQ->fetchAll();

        // Summary
        $stmtS = $this->db->prepare("
            SELECT
                COUNT(*)                                                       AS total_suppliers,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END)               AS active_count,
                COALESCE(SUM(total_purchased), 0)                              AS total_purchased,
                COALESCE(SUM(total_paid), 0)                                   AS total_paid,
                COALESCE(SUM(outstanding_balance), 0)                          AS total_outstanding,
                SUM(CASE WHEN outstanding_balance > 0 THEN 1 ELSE 0 END)     AS owing_count
            FROM suppliers WHERE business_id = ? AND deleted_at IS NULL
        ");
        $stmtS->execute([$this->biz_id]);
        $summary = $stmtS->fetch();

        $title     = "Suppliers";
        $view_file = BASE_PATH . '/views/suppliers/index.php';
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
            return $this->save_supplier(null);
        }

        $supplier = [];
        $editing  = false;

        $title     = "New Supplier";
        $view_file = BASE_PATH . '/views/suppliers/form.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ──────────────────────────────────────────
     *  EDIT
     * ────────────────────────────────────────── */
    public function edit($id = null) {
        $user     = auth_user();
        $business = auth_business();

        $supplier = $this->find_supplier($id);
        if (!$supplier) { not_found(); return; }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            return $this->save_supplier($id);
        }

        $editing = true;

        $title     = "Edit — " . $supplier['full_name'];
        $view_file = BASE_PATH . '/views/suppliers/form.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ──────────────────────────────────────────
     *  VIEW — Supplier Profile
     * ────────────────────────────────────────── */
    public function view($id = null) {
        $user     = auth_user();
        $business = auth_business();

        $supplier = $this->find_supplier($id);
        if (!$supplier) { not_found(); return; }

        $currency = $business['currency_symbol'] ?? '₦';

        // All expenses from this supplier
        $stmtE = $this->db->prepare("
            SELECT id, doc_number, category, description, amount, expense_date,
                   payment_method, payment_status, amount_paid, balance_due
            FROM expenses
            WHERE supplier_id = ? AND business_id = ? AND deleted_at IS NULL
            ORDER BY expense_date DESC LIMIT 30
        ");
        $stmtE->execute([$id, $this->biz_id]);
        $expenses = $stmtE->fetchAll();

        // Stock purchases (subset of expenses with category = stock_purchase)
        $stmtP = $this->db->prepare("
            SELECT e.id, e.doc_number, e.description, e.amount, e.expense_date,
                   e.payment_status, e.amount_paid, e.balance_due
            FROM expenses e
            WHERE e.supplier_id = ? AND e.business_id = ? AND e.category = 'stock_purchase'
                  AND e.deleted_at IS NULL
            ORDER BY e.expense_date DESC LIMIT 20
        ");
        $stmtP->execute([$id, $this->biz_id]);
        $purchases = $stmtP->fetchAll();

        // Debts (payable — what we owe this supplier)
        $stmtD = $this->db->prepare("
            SELECT id, description, total_amount, amount_settled, balance, due_date, status, source
            FROM debts
            WHERE supplier_id = ? AND business_id = ? AND type = 'payable'
            ORDER BY created_at DESC LIMIT 20
        ");
        $stmtD->execute([$id, $this->biz_id]);
        $debts = $stmtD->fetchAll();

        // Activity stats
        $stmtStats = $this->db->prepare("
            SELECT
                (SELECT COUNT(*) FROM expenses WHERE supplier_id = ? AND business_id = ? AND deleted_at IS NULL) AS expense_count,
                (SELECT COUNT(*) FROM expenses WHERE supplier_id = ? AND business_id = ? AND category = 'stock_purchase' AND deleted_at IS NULL) AS purchase_count,
                (SELECT COUNT(*) FROM expenses WHERE supplier_id = ? AND business_id = ? AND payment_status = 'unpaid' AND deleted_at IS NULL) AS unpaid_count
        ");
        $stmtStats->execute([
            $id, $this->biz_id,
            $id, $this->biz_id,
            $id, $this->biz_id,
        ]);
        $stats = $stmtStats->fetch();

        // WhatsApp number
        $wa_number = $supplier['whatsapp'] ?: $supplier['phone'] ?: '';
        $wa_number = wa_digits($wa_number, auth_business());

        $title     = $supplier['full_name'];
        $view_file = BASE_PATH . '/views/suppliers/view.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ──────────────────────────────────────────
     *  DELETE — soft delete
     * ────────────────────────────────────────── */
    public function delete($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('suppliers'); return; }
        csrf_verify();

        $supplier = $this->find_supplier($id);
        if (!$supplier) { not_found(); return; }

        $this->db->prepare("UPDATE suppliers SET deleted_at = NOW() WHERE id = ? AND business_id = ?")
                  ->execute([$id, $this->biz_id]);

        flash('success', $supplier['full_name'] . ' removed.');
        redirect('suppliers');
    }

    /* ──────────────────────────────────────────
     *  TOGGLE ACTIVE
     * ────────────────────────────────────────── */
    public function toggle_active($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('suppliers'); return; }
        csrf_verify();

        $supplier = $this->find_supplier($id);
        if (!$supplier) {
            if (is_ajax()) { json_response(['error' => 'Supplier not found'], 404); }
            not_found(); return;
        }

        $new_status = $supplier['is_active'] ? 0 : 1;
        $this->db->prepare("UPDATE suppliers SET is_active = ? WHERE id = ? AND business_id = ?")
                  ->execute([$new_status, $id, $this->biz_id]);

        if (is_ajax()) {
            json_response(['success' => true, 'is_active' => $new_status]);
        }

        flash('success', $supplier['full_name'] . ($new_status ? ' reactivated.' : ' deactivated.'));
        redirect('suppliers/view/' . $id);
    }

    /* ══════════════════════════════════════════
     *  PRIVATE: save supplier
     * ══════════════════════════════════════════ */
    private function save_supplier(?int $id): void {
        $full_name     = trim($_POST['full_name'] ?? '');
        $business_name = trim($_POST['business_name'] ?? '');
        $phone         = trim($_POST['phone'] ?? '');
        $whatsapp      = trim($_POST['whatsapp'] ?? '');
        $email         = trim($_POST['email'] ?? '');
        $address       = trim($_POST['address'] ?? '');
        $notes         = trim($_POST['notes'] ?? '');

        $errors = [];
        // A name is enough, as on the expense form; a phone, when given, is checked below
        if ($full_name === '') $errors[] = 'Supplier name is required.';
        if ($p = phone_problem($phone, 50) ?? phone_problem($whatsapp, 50)) $errors[] = $p;   // D4

        // Duplicate check (same phone within this business)
        if ($phone !== '') {
            $dup_sql = "SELECT id FROM suppliers WHERE business_id = ? AND phone = ? AND deleted_at IS NULL";
            $dup_params = [$this->biz_id, $phone];
            if ($id) {
                $dup_sql .= " AND id != ?";
                $dup_params[] = $id;
            }
            $stmtDup = $this->db->prepare($dup_sql);
            $stmtDup->execute($dup_params);
            if ($stmtDup->fetch()) {
                $errors[] = 'A supplier with this phone number already exists.';
            }
        }

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            // Keep what was typed; an edit keeps its own id (D1)
            $editing  = $id !== null;
            $supplier = array_merge($editing ? ($this->find_supplier($id) ?: []) : [], $_POST);
            $user     = auth_user();
            $business = auth_business();
            $title    = $editing ? "Edit Supplier" : "New Supplier";
            $view_file = BASE_PATH . '/views/suppliers/form.php';
            require_once BASE_PATH . '/views/layouts/app.php';
            return;
        }

        if ($id) {
            $this->db->prepare("
                UPDATE suppliers SET
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
                INSERT INTO suppliers (business_id, full_name, business_name, phone, whatsapp, email, address, notes, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
            ")->execute([
                $this->biz_id,
                $full_name, $business_name ?: null, $phone ?: null, $whatsapp ?: null,
                $email ?: null, $address ?: null, $notes ?: null
            ]);
            $id = (int)$this->db->lastInsertId();
            flash('success', $full_name . ' added.');
        }

        redirect('suppliers/view/' . $id);
    }

    /* ══════════════════════════════════════════
     *  PRIVATE HELPERS
     * ══════════════════════════════════════════ */

    private function find_supplier($id): ?array {
        if (!$id) return null;
        $stmt = $this->db->prepare("SELECT * FROM suppliers WHERE id = ? AND business_id = ? AND deleted_at IS NULL");
        $stmt->execute([(int)$id, $this->biz_id]);
        return $stmt->fetch() ?: null;
    }
}