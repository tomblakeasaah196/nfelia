<?php
/**
 * Nfelia — Expense Controller
 * CRUD for all business expenses including stock purchases.
 * Stock purchase expenses with line items restock inventory and log stock_movements.
 * Updates supplier cached financial fields.
 */
class ExpenseController {

    private $db;
    private $biz_id;

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

    public const PAYMENT_METHODS = [
        'cash'            => 'Cash',
        'bank_transfer'   => 'Bank Transfer',
        'card'            => 'Card',
        'mobile_money'    => 'Mobile Money',
        'pos'             => 'POS',
        'cheque'          => 'Cheque',
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
        $currency = $business['currency_symbol'] ?? '₦';

        $search   = trim($_GET['q'] ?? '');
        $category = trim($_GET['category'] ?? '');
        $status   = trim($_GET['status'] ?? '');
        $sort     = trim($_GET['sort'] ?? 'newest');
        $page     = max(1, (int)($_GET['page'] ?? 1));
        $per_page = 20;
        $offset   = ($page - 1) * $per_page;

        $where  = ["e.business_id = ?", "e.deleted_at IS NULL"];
        $params = [$this->biz_id];

        if ($search !== '') {
            $where[]  = "(e.description LIKE ? OR e.doc_number LIKE ? OR s.full_name LIKE ?)";
            $like     = "%{$search}%";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($category !== '' && array_key_exists($category, self::CATEGORIES)) {
            $where[]  = "e.category = ?";
            $params[] = $category;
        }

        if ($status !== '' && in_array($status, ['paid', 'partial', 'unpaid'])) {
            $where[]  = "e.payment_status = ?";
            $params[] = $status;
        }

        $where_sql = implode(' AND ', $where);
        $order = match($sort) {
            'newest'         => 'e.expense_date DESC, e.created_at DESC',
            'oldest'         => 'e.expense_date ASC, e.created_at ASC',
            'highest_amount' => 'e.amount DESC',
            'lowest_amount'  => 'e.amount ASC',
            default          => 'e.expense_date DESC, e.created_at DESC',
        };

        // Count
        $stmtC = $this->db->prepare("
            SELECT COUNT(*) FROM expenses e
            LEFT JOIN suppliers s ON s.id = e.supplier_id AND s.business_id = e.business_id
            WHERE {$where_sql}
        ");
        $stmtC->execute($params);
        $total_items = (int)$stmtC->fetchColumn();
        $total_pages = max(1, ceil($total_items / $per_page));

        // Fetch
        $stmtQ = $this->db->prepare("
            SELECT e.*, s.full_name AS supplier_name
            FROM expenses e
            LEFT JOIN suppliers s ON s.id = e.supplier_id AND s.business_id = e.business_id
            WHERE {$where_sql}
            ORDER BY {$order}
            LIMIT {$per_page} OFFSET {$offset}
        ");
        $stmtQ->execute($params);
        $expenses = $stmtQ->fetchAll();

        // Summary
        $stmtS = $this->db->prepare("
            SELECT
                COUNT(*)                                                         AS total_expenses,
                COALESCE(SUM(amount), 0)                                         AS total_amount,
                COALESCE(SUM(amount_paid), 0)                                    AS total_paid,
                COALESCE(SUM(balance_due), 0)                                    AS total_unpaid,
                SUM(CASE WHEN category = 'stock_purchase' THEN amount ELSE 0 END) AS stock_purchase_total,
                SUM(CASE WHEN category = 'stock_purchase' THEN 1 ELSE 0 END)     AS stock_purchase_count,
                SUM(CASE WHEN payment_status = 'unpaid' THEN 1 ELSE 0 END)       AS unpaid_count
            FROM expenses WHERE business_id = ? AND deleted_at IS NULL
        ");
        $stmtS->execute([$this->biz_id]);
        $summary = $stmtS->fetch();

        // Category breakdown for quick display
        $stmtCat = $this->db->prepare("
            SELECT category, COALESCE(SUM(amount), 0) AS cat_total, COUNT(*) AS cat_count
            FROM expenses WHERE business_id = ? AND deleted_at IS NULL
            GROUP BY category ORDER BY cat_total DESC
        ");
        $stmtCat->execute([$this->biz_id]);
        $category_breakdown = $stmtCat->fetchAll();

        $categories = self::CATEGORIES;

        $title     = "Expenses";
        $view_file = BASE_PATH . '/views/expenses/index.php';
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
            return $this->save_expense(null);
        }

        $expense = [];
        $editing = false;

        // Pre-select supplier if passed via query string
        $pre_supplier_id = (int)($_GET['supplier_id'] ?? 0);
        $pre_supplier = null;
        if ($pre_supplier_id) {
            $stmt = $this->db->prepare("SELECT id, full_name FROM suppliers WHERE id = ? AND business_id = ? AND deleted_at IS NULL");
            $stmt->execute([$pre_supplier_id, $this->biz_id]);
            $pre_supplier = $stmt->fetch();
        }

        $categories      = self::CATEGORIES;
        $payment_methods = self::PAYMENT_METHODS;

        $title     = "New Expense";
        $view_file = BASE_PATH . '/views/expenses/form.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ──────────────────────────────────────────
     *  EDIT
     * ────────────────────────────────────────── */
    public function edit($id = null) {
        $user     = auth_user();
        $business = auth_business();

        $expense = $this->find_expense($id);
        if (!$expense) { not_found(); return; }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            return $this->save_expense($id);
        }

        $editing = true;

        // Load existing items for stock purchases
        $items = [];
        if ($expense['category'] === 'stock_purchase') {
            $stmtI = $this->db->prepare("SELECT * FROM expense_items WHERE expense_id = ?");
            $stmtI->execute([$id]);
            $items = $stmtI->fetchAll();
        }

        // Supplier info
        $pre_supplier = null;
        if ($expense['supplier_id']) {
            $stmt = $this->db->prepare("SELECT id, full_name FROM suppliers WHERE id = ? AND business_id = ?");
            $stmt->execute([$expense['supplier_id'], $this->biz_id]);
            $pre_supplier = $stmt->fetch();
        }

        $categories      = self::CATEGORIES;
        $payment_methods = self::PAYMENT_METHODS;

        $title     = "Edit — " . e($expense['doc_number']);
        $view_file = BASE_PATH . '/views/expenses/form.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ──────────────────────────────────────────
     *  VIEW — Expense Detail
     * ────────────────────────────────────────── */
    public function view($id = null) {
        $user     = auth_user();
        $business = auth_business();

        $expense = $this->find_expense($id);
        if (!$expense) { not_found(); return; }

        $currency = $business['currency_symbol'] ?? '₦';

        // Supplier
        $supplier = null;
        if ($expense['supplier_id']) {
            $stmt = $this->db->prepare("SELECT * FROM suppliers WHERE id = ? AND business_id = ?");
            $stmt->execute([$expense['supplier_id'], $this->biz_id]);
            $supplier = $stmt->fetch();
        }

        // Line items (for stock purchases)
        $items = [];
        if ($expense['category'] === 'stock_purchase') {
            $stmtI = $this->db->prepare("
                SELECT ei.*, p.name AS product_name, p.sku AS product_sku
                FROM expense_items ei
                LEFT JOIN products p ON p.id = ei.product_id
                WHERE ei.expense_id = ?
            ");
            $stmtI->execute([$id]);
            $items = $stmtI->fetchAll();
        }

        $categories = self::CATEGORIES;

        $title     = e($expense['doc_number']);
        $view_file = BASE_PATH . '/views/expenses/view.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ──────────────────────────────────────────
     *  DELETE — soft delete
     * ────────────────────────────────────────── */
    public function delete($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('expenses'); return; }
        csrf_verify();

        $expense = $this->find_expense($id);
        if (!$expense) { not_found(); return; }

        // If stock purchase was paid, reverse stock movements
        if ($expense['category'] === 'stock_purchase') {
            $stmtI = $this->db->prepare("SELECT * FROM expense_items WHERE expense_id = ?");
            $stmtI->execute([$id]);
            $items = $stmtI->fetchAll();

            foreach ($items as $item) {
                if ($item['product_id']) {
                    // Reverse stock: subtract what was added
                    $this->db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND business_id = ?")
                              ->execute([$item['quantity'], $item['product_id'], $this->biz_id]);

                    // Log reversal movement
                    $this->db->prepare("
                        INSERT INTO stock_movements (business_id, product_id, type, quantity, reference_type, reference_id, note)
                        VALUES (?, ?, 'adjustment', ?, 'expense_delete', ?, 'Stock reversed — expense deleted')
                    ")->execute([$this->biz_id, $item['product_id'], -$item['quantity'], $id]);
                }
            }
        }

        // Reverse supplier financials
        if ($expense['supplier_id']) {
            $this->db->prepare("
                UPDATE suppliers SET
                    total_purchased = total_purchased - ?,
                    total_paid = total_paid - ?,
                    outstanding_balance = outstanding_balance - ?
                WHERE id = ? AND business_id = ?
            ")->execute([
                $expense['amount'],
                $expense['amount_paid'],
                $expense['balance_due'],
                $expense['supplier_id'],
                $this->biz_id
            ]);
        }

        // Soft delete
        $this->db->prepare("UPDATE expenses SET deleted_at = NOW() WHERE id = ? AND business_id = ?")
                  ->execute([$id, $this->biz_id]);

        flash('success', $expense['doc_number'] . ' deleted.');
        redirect('expenses');
    }

    /* ──────────────────────────────────────────
     *  AJAX — Search suppliers
     * ────────────────────────────────────────── */
    public function search_suppliers() {
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) { json_response([]); return; }

        $stmt = $this->db->prepare("
            SELECT id, full_name, business_name, phone
            FROM suppliers
            WHERE business_id = ? AND deleted_at IS NULL AND is_active = 1
              AND (full_name LIKE ? OR business_name LIKE ? OR phone LIKE ?)
            ORDER BY full_name ASC LIMIT 10
        ");
        $like = "%{$q}%";
        $stmt->execute([$this->biz_id, $like, $like, $like]);
        json_response($stmt->fetchAll());
    }

    /* ──────────────────────────────────────────
     *  AJAX — Create supplier inline
     * ────────────────────────────────────────── */
    public function create_supplier() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { json_response(['error' => 'POST only'], 405); return; }

        $name  = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($name === '') { json_response(['error' => 'Name required'], 422); return; }

        $this->db->prepare("INSERT INTO suppliers (business_id, full_name, phone, is_active) VALUES (?, ?, ?, 1)")
                  ->execute([$this->biz_id, $name, $phone ?: null]);

        $id = (int)$this->db->lastInsertId();
        json_response(['id' => $id, 'full_name' => $name, 'phone' => $phone]);
    }

    /* ──────────────────────────────────────────
     *  AJAX — Search products (for stock purchase items)
     * ────────────────────────────────────────── */
    public function search_products() {
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) { json_response([]); return; }

        $stmt = $this->db->prepare("
            SELECT id, name, sku, stock_quantity, cost_price, selling_price, type
            FROM products
            WHERE business_id = ? AND deleted_at IS NULL
              AND (name LIKE ? OR sku LIKE ?)
            ORDER BY name ASC LIMIT 10
        ");
        $like = "%{$q}%";
        $stmt->execute([$this->biz_id, $like, $like]);
        json_response($stmt->fetchAll());
    }

    /* ══════════════════════════════════════════
     *  PRIVATE: save expense
     * ══════════════════════════════════════════ */
    private function save_expense(?int $id): void {
        $category       = trim($_POST['category'] ?? 'miscellaneous');
        $description    = trim($_POST['description'] ?? '');
        $amount         = (float)($_POST['amount'] ?? 0);
        $expense_date   = trim($_POST['expense_date'] ?? date('Y-m-d'));
        $payment_method = trim($_POST['payment_method'] ?? '');
        $payment_status = trim($_POST['payment_status'] ?? 'paid');
        $amount_paid    = (float)($_POST['amount_paid'] ?? 0);
        $supplier_id    = (int)($_POST['supplier_id'] ?? 0) ?: null;
        $reference      = trim($_POST['reference'] ?? '');
        $notes          = trim($_POST['notes'] ?? '');

        // For stock purchases, items come as arrays
        $item_descriptions = $_POST['item_description'] ?? [];
        $item_quantities   = $_POST['item_quantity'] ?? [];
        $item_unit_costs   = $_POST['item_unit_cost'] ?? [];
        $item_product_ids  = $_POST['item_product_id'] ?? [];

        // Validate
        $errors = [];
        if ($description === '') $errors[] = 'Description is required.';
        if ($expense_date === '') $errors[] = 'Expense date is required.';
        if ($supplier_id && !owned_by_business($this->db, 'suppliers', $supplier_id, $this->biz_id)) {
            $errors[] = 'Please pick one of your suppliers.';
            $supplier_id = null;
        }

        if ($category === 'stock_purchase') {
            // Recalculate amount from line items
            $amount = 0;
            $valid_items = 0;
            foreach ($item_descriptions as $i => $desc) {
                $desc = trim($desc);
                $qty  = (float)($item_quantities[$i] ?? 0);
                $cost = (float)($item_unit_costs[$i] ?? 0);
                if ($desc !== '' && $qty > 0 && $cost > 0) {
                    $amount += $qty * $cost;
                    $valid_items++;
                }
            }
            if ($valid_items === 0) $errors[] = 'Add at least one item for stock purchase.';
        } else {
            if ($amount <= 0) $errors[] = 'Amount must be greater than zero.';
        }

        // Payment logic
        if ($payment_status === 'paid') {
            $amount_paid = $amount;
        } elseif ($payment_status === 'unpaid') {
            $amount_paid = 0;
        }
        // 'partial' uses the provided amount_paid
        $balance_due = max(0, $amount - $amount_paid);

        if (!array_key_exists($category, self::CATEGORIES)) $errors[] = 'Invalid category.';
        if (!in_array($payment_status, ['paid', 'partial', 'unpaid'])) $errors[] = 'Invalid payment status.';

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            $expense  = $_POST;
            $editing  = $id !== null;
            $user     = auth_user();
            $business = auth_business();
            $pre_supplier = null;
            if ($supplier_id) {
                $stmt = $this->db->prepare("SELECT id, full_name FROM suppliers WHERE id = ? AND business_id = ?");
                $stmt->execute([$supplier_id, $this->biz_id]);
                $pre_supplier = $stmt->fetch();
            }
            $categories      = self::CATEGORIES;
            $payment_methods = self::PAYMENT_METHODS;
            $items           = [];
            $title    = $editing ? "Edit Expense" : "New Expense";
            $view_file = BASE_PATH . '/views/expenses/form.php';
            require_once BASE_PATH . '/views/layouts/app.php';
            return;
        }

        $this->db->beginTransaction();
        try {
            if ($id) {
                // Reverse old supplier financials before update
                $old = $this->find_expense($id);
                if ($old && $old['supplier_id']) {
                    $this->db->prepare("
                        UPDATE suppliers SET
                            total_purchased = total_purchased - ?,
                            total_paid = total_paid - ?,
                            outstanding_balance = outstanding_balance - ?
                        WHERE id = ? AND business_id = ?
                    ")->execute([
                        $old['amount'], $old['amount_paid'], $old['balance_due'],
                        $old['supplier_id'], $this->biz_id
                    ]);
                }

                // Reverse old stock if was stock_purchase
                if ($old && $old['category'] === 'stock_purchase') {
                    $stmtOI = $this->db->prepare("SELECT * FROM expense_items WHERE expense_id = ?");
                    $stmtOI->execute([$id]);
                    foreach ($stmtOI->fetchAll() as $oi) {
                        if ($oi['product_id']) {
                            $this->db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND business_id = ?")
                                      ->execute([$oi['quantity'], $oi['product_id'], $this->biz_id]);
                        }
                    }
                    $this->db->prepare("DELETE FROM expense_items WHERE expense_id = ?")->execute([$id]);
                }

                $this->db->prepare("
                    UPDATE expenses SET
                        supplier_id = ?, category = ?, description = ?, amount = ?,
                        expense_date = ?, payment_method = ?, payment_status = ?,
                        amount_paid = ?, balance_due = ?, reference = ?, notes = ?
                    WHERE id = ? AND business_id = ?
                ")->execute([
                    $supplier_id, $category, $description, $amount,
                    $expense_date, $payment_method ?: null, $payment_status,
                    $amount_paid, $balance_due, $reference ?: null, $notes ?: null,
                    $id, $this->biz_id
                ]);
            } else {
                // Generate doc number
                $doc_number = generate_doc_number($this->db, $this->biz_id, 'EXP');

                $this->db->prepare("
                    INSERT INTO expenses (business_id, supplier_id, doc_number, category, description,
                        amount, expense_date, payment_method, payment_status, amount_paid, balance_due,
                        reference, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $this->biz_id, $supplier_id, $doc_number, $category, $description,
                    $amount, $expense_date, $payment_method ?: null, $payment_status,
                    $amount_paid, $balance_due, $reference ?: null, $notes ?: null
                ]);
                $id = (int)$this->db->lastInsertId();
            }

            // Insert stock purchase line items + restock
            if ($category === 'stock_purchase') {
                foreach ($item_descriptions as $i => $desc) {
                    $desc       = trim($desc);
                    $qty        = (float)($item_quantities[$i] ?? 0);
                    $unit_cost  = (float)($item_unit_costs[$i] ?? 0);
                    $product_id = (int)($item_product_ids[$i] ?? 0) ?: null;
                    if ($product_id && !owned_by_business($this->db, 'products', $product_id, $this->biz_id)) $product_id = null;

                    if ($desc === '' || $qty <= 0 || $unit_cost <= 0) continue;

                    $line_total = round($qty * $unit_cost, 2);

                    $this->db->prepare("
                        INSERT INTO expense_items (expense_id, product_id, description, quantity, unit_cost, total)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ")->execute([$id, $product_id, $desc, $qty, $unit_cost, $line_total]);

                    // Restock product
                    if ($product_id) {
                        $this->db->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ? AND business_id = ?")
                                  ->execute([$qty, $product_id, $this->biz_id]);

                        // Update cost price if provided
                        $this->db->prepare("UPDATE products SET cost_price = ? WHERE id = ? AND business_id = ? AND (cost_price IS NULL OR cost_price = 0)")
                                  ->execute([$unit_cost, $product_id, $this->biz_id]);

                        // Log stock movement
                        $this->db->prepare("
                            INSERT INTO stock_movements (business_id, product_id, type, quantity, reference_type, reference_id, note)
                            VALUES (?, ?, 'restock', ?, 'expense', ?, ?)
                        ")->execute([$this->biz_id, $product_id, $qty, $id, 'Restocked from ' . ($doc_number ?? $expense['doc_number'] ?? 'EXP')]);
                    }
                }
            }

            // Update supplier financials
            if ($supplier_id) {
                $this->db->prepare("
                    UPDATE suppliers SET
                        total_purchased = total_purchased + ?,
                        total_paid = total_paid + ?,
                        outstanding_balance = outstanding_balance + ?
                    WHERE id = ? AND business_id = ?
                ")->execute([$amount, $amount_paid, $balance_due, $supplier_id, $this->biz_id]);
            }

            $this->db->commit();
            flash('success', ($doc_number ?? $expense['doc_number'] ?? 'Expense') . ' saved.');
        } catch (\Exception $ex) {
            $this->db->rollBack();
            flash('error', 'Failed to save expense: ' . $ex->getMessage());
            redirect('expenses');
            return;
        }

        redirect('expenses/view/' . $id);
    }

    /* ══════════════════════════════════════════
     *  PRIVATE HELPERS
     * ══════════════════════════════════════════ */

    private function find_expense($id): ?array {
        if (!$id) return null;
        $stmt = $this->db->prepare("SELECT * FROM expenses WHERE id = ? AND business_id = ? AND deleted_at IS NULL");
        $stmt->execute([(int)$id, $this->biz_id]);
        return $stmt->fetch() ?: null;
    }
}