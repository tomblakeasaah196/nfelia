<?php
/**
 * Nfelia — Debt Tracker Controller
 * Manages receivables (customers owe you) and payables (you owe suppliers).
 *
 * Debts can be:
 *   - Auto-created from overdue invoices (receivable, source='invoice')
 *   - Auto-created from unpaid expenses (payable, source='expense')
 *   - Manually created (receivable or payable, source='manual')
 *
 * Settlements track partial/full payments against debts.
 */
class DebtController {

    private $db;
    private $biz_id;

    public function __construct() {
        if (!is_logged_in()) { redirect('login'); }
        $this->db     = get_db();
        $this->biz_id = auth_business_id();
        require_boss('see the debts');
    }

    /* ──────────────────────────────────────────
     *  INDEX — Dashboard + Debt Listing
     * ────────────────────────────────────────── */
    public function index() {
        $user     = auth_user();
        $business = auth_business();
        $currency = $business['currency_symbol'] ?? '₦';

        $tab    = trim($_GET['tab'] ?? 'receivable');  // receivable | payable
        $filter = trim($_GET['filter'] ?? '');          // active | settling | cleared | written_off | overdue
        $search = trim($_GET['q'] ?? '');
        $sort   = trim($_GET['sort'] ?? 'newest');
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $per_page = 20;
        $offset   = ($page - 1) * $per_page;

        if (!in_array($tab, ['receivable', 'payable'])) $tab = 'receivable';

        // Sync overdue invoices → debt records (lazy check on page load)
        if ($tab === 'receivable') {
            $this->sync_overdue_invoices();
        }
        // Sync unpaid expenses → debt records
        if ($tab === 'payable') {
            $this->sync_unpaid_expenses();
        }

        $where  = ["d.business_id = ?", "d.type = ?"];
        $params = [$this->biz_id, $tab];

        if ($search !== '') {
            if ($tab === 'receivable') {
                $where[]  = "(d.description LIKE ? OR c.full_name LIKE ?)";
            } else {
                $where[]  = "(d.description LIKE ? OR s.full_name LIKE ?)";
            }
            $like     = "%{$search}%";
            $params[] = $like;
            $params[] = $like;
        }

        if ($filter !== '' && in_array($filter, ['active', 'settling', 'cleared', 'written_off'])) {
            $where[]  = "d.status = ?";
            $params[] = $filter;
        } elseif ($filter === 'overdue') {
            $where[] = "d.due_date IS NOT NULL AND d.due_date < CURDATE() AND d.status IN ('active', 'settling')";
        } elseif ($filter === '') {
            // Default: show active + settling only
            $where[] = "d.status IN ('active', 'settling')";
        }

        $where_sql = implode(' AND ', $where);

        $order = match($sort) {
            'newest'          => 'd.created_at DESC',
            'oldest'          => 'd.created_at ASC',
            'highest_balance' => 'd.balance DESC',
            'due_soonest'     => 'COALESCE(d.due_date, "9999-12-31") ASC',
            default           => 'd.created_at DESC',
        };

        // Count
        $count_sql = "SELECT COUNT(*) FROM debts d";
        if ($tab === 'receivable') {
            $count_sql .= " LEFT JOIN customers c ON c.id = d.customer_id";
        } else {
            $count_sql .= " LEFT JOIN suppliers s ON s.id = d.supplier_id";
        }
        $count_sql .= " WHERE {$where_sql}";
        $stmtC = $this->db->prepare($count_sql);
        $stmtC->execute($params);
        $total_items = (int)$stmtC->fetchColumn();
        $total_pages = max(1, ceil($total_items / $per_page));

        // Fetch
        if ($tab === 'receivable') {
            $fetch_sql = "
                SELECT d.*, c.full_name AS party_name, c.phone AS party_phone
                FROM debts d
                LEFT JOIN customers c ON c.id = d.customer_id AND c.business_id = d.business_id
                WHERE {$where_sql}
                ORDER BY {$order}
                LIMIT {$per_page} OFFSET {$offset}
            ";
        } else {
            $fetch_sql = "
                SELECT d.*, s.full_name AS party_name, s.phone AS party_phone
                FROM debts d
                LEFT JOIN suppliers s ON s.id = d.supplier_id AND s.business_id = d.business_id
                WHERE {$where_sql}
                ORDER BY {$order}
                LIMIT {$per_page} OFFSET {$offset}
            ";
        }
        $stmtQ = $this->db->prepare($fetch_sql);
        $stmtQ->execute($params);
        $debts = $stmtQ->fetchAll();

        // Dashboard summary (all debts, not just filtered)
        $stmtSum = $this->db->prepare("
            SELECT
                -- Receivables
                COALESCE(SUM(CASE WHEN type = 'receivable' AND status IN ('active','settling') THEN balance ELSE 0 END), 0) AS receivable_balance,
                SUM(CASE WHEN type = 'receivable' AND status IN ('active','settling') THEN 1 ELSE 0 END) AS receivable_count,
                COALESCE(SUM(CASE WHEN type = 'receivable' AND status IN ('active','settling') AND due_date IS NOT NULL AND due_date < CURDATE() THEN balance ELSE 0 END), 0) AS receivable_overdue,
                SUM(CASE WHEN type = 'receivable' AND status IN ('active','settling') AND due_date IS NOT NULL AND due_date < CURDATE() THEN 1 ELSE 0 END) AS receivable_overdue_count,
                -- Payables
                COALESCE(SUM(CASE WHEN type = 'payable' AND status IN ('active','settling') THEN balance ELSE 0 END), 0) AS payable_balance,
                SUM(CASE WHEN type = 'payable' AND status IN ('active','settling') THEN 1 ELSE 0 END) AS payable_count,
                COALESCE(SUM(CASE WHEN type = 'payable' AND status IN ('active','settling') AND due_date IS NOT NULL AND due_date < CURDATE() THEN balance ELSE 0 END), 0) AS payable_overdue,
                SUM(CASE WHEN type = 'payable' AND status IN ('active','settling') AND due_date IS NOT NULL AND due_date < CURDATE() THEN 1 ELSE 0 END) AS payable_overdue_count,
                -- Totals settled
                COALESCE(SUM(CASE WHEN type = 'receivable' THEN amount_settled ELSE 0 END), 0) AS total_received_settlements,
                COALESCE(SUM(CASE WHEN type = 'payable' THEN amount_settled ELSE 0 END), 0) AS total_paid_settlements
            FROM debts WHERE business_id = ?
        ");
        $stmtSum->execute([$this->biz_id]);
        $dashboard = $stmtSum->fetch();

        $title     = "Debt Tracker";
        $view_file = BASE_PATH . '/views/debts/index.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ──────────────────────────────────────────
     *  CREATE — Manual debt
     * ────────────────────────────────────────── */
    public function create() {
        $user     = auth_user();
        $business = auth_business();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            return $this->save_debt(null);
        }

        $debt    = [];
        $editing = false;
        $pre_type = trim($_GET['type'] ?? 'receivable');

        $title     = "Record New Debt";
        $view_file = BASE_PATH . '/views/debts/form.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ──────────────────────────────────────────
     *  VIEW — Debt Detail + Settlement History
     * ────────────────────────────────────────── */
    public function view($id = null) {
        $user     = auth_user();
        $business = auth_business();

        $debt = $this->find_debt($id);
        if (!$debt) { not_found(); return; }

        $currency = $business['currency_symbol'] ?? '₦';

        // Party info
        $party = null;
        if ($debt['type'] === 'receivable' && $debt['customer_id']) {
            $stmt = $this->db->prepare("SELECT * FROM customers WHERE id = ? AND business_id = ?");
            $stmt->execute([$debt['customer_id'], $this->biz_id]);
            $party = $stmt->fetch();
        } elseif ($debt['type'] === 'payable' && $debt['supplier_id']) {
            $stmt = $this->db->prepare("SELECT * FROM suppliers WHERE id = ? AND business_id = ?");
            $stmt->execute([$debt['supplier_id'], $this->biz_id]);
            $party = $stmt->fetch();
        }

        // Settlements
        $stmtS = $this->db->prepare("
            SELECT * FROM debt_settlements
            WHERE debt_id = ?
            ORDER BY settlement_date DESC, created_at DESC
        ");
        $stmtS->execute([$id]);
        $settlements = $stmtS->fetchAll();

        // Linked source document
        $source_doc = null;
        if ($debt['source'] === 'invoice' && $debt['invoice_id']) {
            $stmt = $this->db->prepare("SELECT id, doc_number, status, total, balance_due FROM invoices WHERE id = ? AND business_id = ?");
            $stmt->execute([$debt['invoice_id'], $this->biz_id]);
            $source_doc = $stmt->fetch();
        }

        // WhatsApp number
        $wa_number = '';
        if ($party) {
            $wa_number = $party['whatsapp'] ?? $party['phone'] ?? '';
            $wa_number = preg_replace('/[^0-9]/', '', $wa_number);
            if (str_starts_with($wa_number, '0')) $wa_number = '234' . substr($wa_number, 1);
        }

        $title     = "Debt — " . $debt['description'];
        $view_file = BASE_PATH . '/views/debts/view.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ──────────────────────────────────────────
     *  RECORD SETTLEMENT — POST/AJAX
     * ────────────────────────────────────────── */
    public function settle($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('debts'); return; }

        $debt = $this->find_debt($id);
        if (!$debt) {
            if (is_ajax()) { json_response(['error' => 'Debt not found'], 404); return; }
            not_found(); return;
        }

        if (is_ajax()) {
            // AJAX settlement
            $amount = (float)($_POST['amount'] ?? 0);
            $method = trim($_POST['method'] ?? '');
            $reference = trim($_POST['reference'] ?? '');
            $note   = trim($_POST['note'] ?? '');
            $date   = trim($_POST['settlement_date'] ?? date('Y-m-d'));
        } else {
            csrf_verify();
            $amount = (float)($_POST['amount'] ?? 0);
            $method = trim($_POST['method'] ?? '');
            $reference = trim($_POST['reference'] ?? '');
            $note   = trim($_POST['note'] ?? '');
            $date   = trim($_POST['settlement_date'] ?? date('Y-m-d'));
        }

        if ($amount <= 0) {
            if (is_ajax()) { json_response(['error' => 'Amount must be greater than zero'], 422); return; }
            flash('error', 'Amount must be greater than zero.');
            redirect('debts/view/' . $id);
            return;
        }

        // Cap at balance
        $amount = min($amount, (float)$debt['balance']);

        $this->db->beginTransaction();
        try {
            // NFELIA FIX — $business was undefined, reference column was missing from old schema
            $business = auth_business();
            // Insert settlement
            $this->db->prepare("
                INSERT INTO debt_settlements (debt_id, business_id, amount, settlement_date, method, reference, note)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $id, $this->biz_id, $amount, $date,
                $method ?: null, $reference ?: null, $note ?: null
            ]);

            // Update debt
            $new_settled = (float)$debt['amount_settled'] + $amount;
            $new_balance = max(0, (float)$debt['total_amount'] - $new_settled);
            $new_status  = $new_balance <= 0 ? 'cleared' : 'settling';

            $this->db->prepare("
                UPDATE debts SET amount_settled = ?, balance = ?, status = ? WHERE id = ?
            ")->execute([$new_settled, $new_balance, $new_status, $id]);

            // Update party financials
            if ($debt['type'] === 'receivable' && $debt['customer_id']) {
                // Customer paid us — increase their total_received, decrease outstanding
                $this->db->prepare("
                    UPDATE customers SET
                        total_received = total_received + ?,
                        outstanding_balance = GREATEST(0, outstanding_balance - ?)
                    WHERE id = ? AND business_id = ?
                ")->execute([$amount, $amount, $debt['customer_id'], $this->biz_id]);
            } elseif ($debt['type'] === 'payable' && $debt['supplier_id']) {
                // We paid supplier — increase their total_paid, decrease outstanding
                $this->db->prepare("
                    UPDATE suppliers SET
                        total_paid = total_paid + ?,
                        outstanding_balance = GREATEST(0, outstanding_balance - ?)
                    WHERE id = ? AND business_id = ?
                ")->execute([$amount, $amount, $debt['supplier_id'], $this->biz_id]);
            }

            // If linked to invoice and receivable cleared, update invoice status
            if ($debt['type'] === 'receivable' && $debt['invoice_id'] && $new_status === 'cleared') {
                $this->db->prepare("
                    UPDATE invoices SET status = 'paid', amount_paid = total, balance_due = 0
                    WHERE id = ? AND business_id = ? AND status IN ('overdue', 'partially_paid', 'sent')
                ")->execute([$debt['invoice_id'], $this->biz_id]);
            }

            $this->db->commit();

            if (is_ajax()) {
                json_response([
                    'success'        => true,
                    'new_settled'    => $new_settled,
                    'new_balance'    => $new_balance,
                    'new_status'     => $new_status,
                    'settlement_id'  => (int)$this->db->lastInsertId(),
                ]);
                return;
            }

            flash('success', money($amount, $business['currency_symbol'] ?? '₦') . ' settlement recorded.');
        } catch (\Exception $ex) {
            $this->db->rollBack();
            if (is_ajax()) { json_response(['error' => $ex->getMessage()], 500); return; }
            flash('error', 'Settlement failed: ' . $ex->getMessage());
        }

        redirect('debts/view/' . $id);
    }

    /* ──────────────────────────────────────────
     *  WRITE OFF
     * ────────────────────────────────────────── */
    public function write_off($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('debts'); return; }
        csrf_verify();

        $debt = $this->find_debt($id);
        if (!$debt) { not_found(); return; }

        $this->db->prepare("UPDATE debts SET status = 'written_off' WHERE id = ? AND business_id = ?")
                  ->execute([$id, $this->biz_id]);

        flash('success', 'Debt written off.');
        redirect('debts/view/' . $id);
    }

    /* ──────────────────────────────────────────
     *  DELETE
     * ────────────────────────────────────────── */
    public function delete($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('debts'); return; }
        csrf_verify();

        $debt = $this->find_debt($id);
        if (!$debt) { not_found(); return; }

        // Only allow deleting manual debts with no settlements
        if ($debt['source'] !== 'manual') {
            flash('error', 'Auto-generated debts cannot be deleted. Write them off instead.');
            redirect('debts/view/' . $id);
            return;
        }

        $stmtS = $this->db->prepare("SELECT COUNT(*) FROM debt_settlements WHERE debt_id = ?");
        $stmtS->execute([$id]);
        if ((int)$stmtS->fetchColumn() > 0) {
            flash('error', 'Cannot delete a debt with settlements. Write it off instead.');
            redirect('debts/view/' . $id);
            return;
        }

        $this->db->prepare("DELETE FROM debts WHERE id = ? AND business_id = ?")->execute([$id, $this->biz_id]);

        flash('success', 'Debt removed.');
        redirect('debts?tab=' . $debt['type']);
    }

    /* ──────────────────────────────────────────
     *  AJAX — Search customers
     * ────────────────────────────────────────── */
    public function search_customers() {
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) { json_response([]); return; }
        $stmt = $this->db->prepare("
            SELECT id, full_name, business_name, phone
            FROM customers WHERE business_id = ? AND deleted_at IS NULL AND is_active = 1
              AND (full_name LIKE ? OR business_name LIKE ? OR phone LIKE ?)
            ORDER BY full_name ASC LIMIT 10
        ");
        $like = "%{$q}%";
        $stmt->execute([$this->biz_id, $like, $like, $like]);
        json_response($stmt->fetchAll());
    }

    /* ──────────────────────────────────────────
     *  AJAX — Search suppliers
     * ────────────────────────────────────────── */
    public function search_suppliers() {
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) { json_response([]); return; }
        $stmt = $this->db->prepare("
            SELECT id, full_name, business_name, phone
            FROM suppliers WHERE business_id = ? AND deleted_at IS NULL AND is_active = 1
              AND (full_name LIKE ? OR business_name LIKE ? OR phone LIKE ?)
            ORDER BY full_name ASC LIMIT 10
        ");
        $like = "%{$q}%";
        $stmt->execute([$this->biz_id, $like, $like, $like]);
        json_response($stmt->fetchAll());
    }

    /* ══════════════════════════════════════════
     *  PRIVATE: save manual debt
     * ══════════════════════════════════════════ */
    private function save_debt(?int $id): void {
        $type         = trim($_POST['type'] ?? 'receivable');
        $description  = trim($_POST['description'] ?? '');
        $total_amount = (float)($_POST['total_amount'] ?? 0);
        $due_date     = trim($_POST['due_date'] ?? '') ?: null;
        $customer_id  = (int)($_POST['customer_id'] ?? 0) ?: null;
        $supplier_id  = (int)($_POST['supplier_id'] ?? 0) ?: null;
        $notes        = trim($_POST['notes'] ?? '');

        $errors = [];
        if (!in_array($type, ['receivable', 'payable'])) $errors[] = 'Invalid debt type.';
        if ($description === '') $errors[] = 'Description is required.';
        if ($total_amount <= 0) $errors[] = 'Amount must be greater than zero.';
        if ($type === 'receivable' && !owned_by_business($this->db, 'customers', $customer_id, $this->biz_id)) {
            $errors[] = 'Please select a customer.';
        }
        if ($type === 'payable' && !owned_by_business($this->db, 'suppliers', $supplier_id, $this->biz_id)) {
            $errors[] = 'Please select a supplier.';
        }

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            $debt     = $_POST;
            $editing  = false;
            $pre_type = $type;
            $user     = auth_user();
            $business = auth_business();
            $title    = "Record New Debt";
            $view_file = BASE_PATH . '/views/debts/form.php';
            require_once BASE_PATH . '/views/layouts/app.php';
            return;
        }

        $user_id = auth_user()['id'] ?? null;
        $this->db->prepare("
            INSERT INTO debts (business_id, user_id, type, customer_id, supplier_id, description,
                total_amount, amount_settled, balance, due_date, status, source, invoice_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, 'active', 'manual', NULL)
        ")->execute([
            $this->biz_id, $user_id, $type,
            $type === 'receivable' ? $customer_id : null,
            $type === 'payable' ? $supplier_id : null,
            $description, $total_amount, $total_amount,
            $due_date
        ]);

        $new_id = (int)$this->db->lastInsertId();

        // Update party financials
        if ($type === 'receivable' && $customer_id) {
            $this->db->prepare("UPDATE customers SET outstanding_balance = outstanding_balance + ? WHERE id = ? AND business_id = ?")
                      ->execute([$total_amount, $customer_id, $this->biz_id]);
        } elseif ($type === 'payable' && $supplier_id) {
            $this->db->prepare("UPDATE suppliers SET outstanding_balance = outstanding_balance + ? WHERE id = ? AND business_id = ?")
                      ->execute([$total_amount, $supplier_id, $this->biz_id]);
        }

        flash('success', 'Debt recorded.');
        redirect('debts/view/' . $new_id);
    }

    /* ══════════════════════════════════════════
     *  SYNC: Overdue invoices → Receivable debts
     * ══════════════════════════════════════════ */
    private function sync_overdue_invoices(): void {
        // Find overdue invoices without a matching debt record
        $stmt = $this->db->prepare("
            SELECT i.id, i.doc_number, i.customer_id, i.balance_due, i.due_date
            FROM invoices i
            LEFT JOIN debts d ON d.invoice_id = i.id AND d.type = 'receivable' AND d.business_id = ?
            WHERE i.business_id = ? AND i.status = 'overdue' AND i.balance_due > 0
              AND d.id IS NULL
        ");
        $stmt->execute([$this->biz_id, $this->biz_id]);
        $overdue = $stmt->fetchAll();

        foreach ($overdue as $inv) {
            $this->db->prepare("
                INSERT INTO debts (business_id, user_id, type, customer_id, description, total_amount, amount_settled, balance, due_date, status, source, invoice_id)
                VALUES (?, NULL, 'receivable', ?, ?, ?, 0, ?, ?, 'active', 'invoice', ?)
            ")->execute([
                $this->biz_id,
                $inv['customer_id'],
                'Overdue invoice ' . $inv['doc_number'],
                $inv['balance_due'],
                $inv['balance_due'],
                $inv['due_date'],
                $inv['id']
            ]);
        }
    }

    /* ══════════════════════════════════════════
     *  SYNC: Unpaid expenses → Payable debts
     * ══════════════════════════════════════════ */
    private function sync_unpaid_expenses(): void {
        // Find unpaid expenses with a supplier but no matching debt
        $stmt = $this->db->prepare("
            SELECT e.id, e.doc_number, e.supplier_id, e.balance_due, e.description
            FROM expenses e
            LEFT JOIN debts d ON d.source = 'expense'
                AND d.expense_id = e.id
                AND d.business_id = ?
            WHERE e.business_id = ? AND e.payment_status IN ('unpaid', 'partial')
              AND e.supplier_id IS NOT NULL AND e.balance_due > 0 AND e.deleted_at IS NULL
              AND d.id IS NULL
        ");
        $stmt->execute([$this->biz_id, $this->biz_id]);
        $unpaid = $stmt->fetchAll();

        foreach ($unpaid as $exp) {
            $this->db->prepare("
                INSERT INTO debts (business_id, user_id, type, supplier_id, description, total_amount, amount_settled, balance, status, source, expense_id)
                VALUES (?, NULL, 'payable', ?, ?, ?, 0, ?, 'active', 'expense', ?)
            ")->execute([
                $this->biz_id,
                $exp['supplier_id'],
                'Unpaid expense ' . ($exp['doc_number'] ?? '') . ' — ' . substr($exp['description'] ?? '', 0, 100),
                $exp['balance_due'],
                $exp['balance_due'],
                $exp['id']
            ]);
        }
    }

    /* ══════════════════════════════════════════
     *  PRIVATE HELPERS
     * ══════════════════════════════════════════ */

    private function find_debt($id): ?array {
        if (!$id) return null;
        $stmt = $this->db->prepare("SELECT * FROM debts WHERE id = ? AND business_id = ?");
        $stmt->execute([(int)$id, $this->biz_id]);
        return $stmt->fetch() ?: null;
    }
}