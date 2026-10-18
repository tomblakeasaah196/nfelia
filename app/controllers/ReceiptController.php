<?php
/**
 * Nfelia — Receipt Controller ("Payment Acknowledgement")
 * Two creation paths:
 *   1. From invoice payment — auto-populated from payment record
 *   2. Standalone POS-style — direct sale with full line items
 * Plus: list, view, delete, public view handled by PublicController.
 */
class ReceiptController {

    private $db;
    private $biz_id;

    public function __construct() {
        if (!is_logged_in()) { redirect('login'); }
        $this->db     = get_db();
        $this->biz_id = auth_business_id();
    }

    /* ──────────────────────────────────────────
     *  LIST — all receipts with filters
     * ────────────────────────────────────────── */
    public function index() {
        $user     = auth_user();
        $business = auth_business();

        $search   = trim($_GET['q'] ?? '');
        $type     = trim($_GET['type'] ?? '');   // invoice | standalone
        $sort     = trim($_GET['sort'] ?? 'newest');
        $page     = max(1, (int)($_GET['page'] ?? 1));
        $per_page = 20;
        $offset   = ($page - 1) * $per_page;

        $where  = ["r.business_id = ?"];
        $params = [$this->biz_id];

        if ($search !== '') {
            $where[]  = "(r.doc_number LIKE ? OR c.full_name LIKE ?)";
            $like     = "%{$search}%";
            $params[] = $like;
            $params[] = $like;
        }
        if ($type === 'invoice') {
            $where[] = "r.invoice_id IS NOT NULL";
        } elseif ($type === 'standalone') {
            $where[] = "r.invoice_id IS NULL";
        } else {
            $type = '';
        }

        $where_sql = implode(' AND ', $where);
        $order = match($sort) {
            'oldest'     => 'r.created_at ASC',
            'amount_desc'=> 'r.amount DESC',
            'amount_asc' => 'r.amount ASC',
            default      => 'r.created_at DESC',
        };

        // Count
        $stmtC = $this->db->prepare("SELECT COUNT(*) FROM receipts r LEFT JOIN customers c ON c.id = r.customer_id WHERE {$where_sql}");
        $stmtC->execute($params);
        $total_items = (int)$stmtC->fetchColumn();
        $total_pages = max(1, ceil($total_items / $per_page));

        // Fetch
        $stmtR = $this->db->prepare("
            SELECT r.*, c.full_name as customer_name, c.phone as customer_phone,
                   i.doc_number as invoice_number
            FROM receipts r
            LEFT JOIN customers c ON c.id = r.customer_id
            LEFT JOIN invoices i ON i.id = r.invoice_id
            WHERE {$where_sql}
            ORDER BY {$order}
            LIMIT {$per_page} OFFSET {$offset}
        ");
        $stmtR->execute($params);
        $receipts = $stmtR->fetchAll();

        // Summary
        $stmtS = $this->db->prepare("
            SELECT
                COUNT(*)                                      AS total_receipts,
                COALESCE(SUM(amount), 0)                      AS total_value,
                SUM(CASE WHEN invoice_id IS NOT NULL THEN 1 ELSE 0 END) AS invoice_count,
                SUM(CASE WHEN invoice_id IS NULL THEN 1 ELSE 0 END)     AS standalone_count,
                COALESCE(SUM(CASE WHEN invoice_id IS NULL THEN amount ELSE 0 END), 0) AS standalone_value,
                COALESCE(SUM(CASE WHEN issue_date >= ? THEN amount ELSE 0 END), 0)     AS month_value,
                SUM(CASE WHEN issue_date >= ? THEN 1 ELSE 0 END)                       AS month_count
            FROM receipts WHERE business_id = ?
        ");
        $month_start = substr(planner_today(), 0, 8) . '01';
        $stmtS->execute([$month_start, $month_start, $this->biz_id]);
        $summary = $stmtS->fetch();

        $title     = "Receipts";
        $view_file = BASE_PATH . '/views/receipts/index.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ──────────────────────────────────────────
     *  FROM PAYMENT — auto-generate receipt from payment record
     * ────────────────────────────────────────── */
    public function from_payment($payment_id = null) {
        if (!$payment_id) { not_found(); return; }

        $user     = auth_user();
        $business = auth_business();

        // Find payment
        $stmtPay = $this->db->prepare("
            SELECT p.*, i.doc_number as invoice_number, i.customer_id, i.total as invoice_total, i.balance_due
            FROM payments p
            LEFT JOIN invoices i ON i.id = p.invoice_id
            WHERE p.id = ? AND p.business_id = ?
        ");
        $stmtPay->execute([(int)$payment_id, $this->biz_id]);
        $payment = $stmtPay->fetch();

        if (!$payment) { not_found(); return; }

        // Check if receipt already exists for this payment
        $stmtExist = $this->db->prepare("SELECT id FROM receipts WHERE payment_id = ? AND business_id = ?");
        $stmtExist->execute([$payment_id, $this->biz_id]);
        $existing = $stmtExist->fetch();

        if ($existing) {
            flash('info', 'Receipt already exists for this payment.');
            redirect('receipts/view/' . $existing['id']);
            return;
        }

        // Generate receipt
        $this->db->beginTransaction();
        try {
            $this->db->prepare("CALL generate_doc_number(?, 'REC', @rec_num)")->execute([$this->biz_id]);
            $doc_number = $this->db->query("SELECT @rec_num")->fetchColumn();
            $token = generate_token();

            // Balance remaining after this payment
            $balance_remaining = max(0, (float)$payment['balance_due']);

            $this->db->prepare("
                INSERT INTO receipts (business_id, invoice_id, payment_id, customer_id, user_id, doc_number, issue_date, amount, balance_remaining, method, note, token)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $this->biz_id,
                $payment['invoice_id'],
                $payment_id,
                $payment['customer_id'],
                $user['id'],
                $doc_number,
                $payment['payment_date'],
                $payment['amount'],
                $balance_remaining,
                $payment['method'],
                $payment['note'] ?: 'Payment for invoice ' . $payment['invoice_number'],
                $token
            ]);
            $receipt_id = (int)$this->db->lastInsertId();

            $this->db->commit();
            flash('success', 'Receipt ' . $doc_number . ' generated.');
            redirect('receipts/view/' . $receipt_id);

        } catch (\Throwable $e) {
            $this->db->rollBack();
            flash('error', 'Failed to generate receipt: ' . $e->getMessage());
            redirect('invoices/view/' . $payment['invoice_id']);
        }
    }

    /* ──────────────────────────────────────────
     *  CREATE — standalone POS-style receipt
     * ────────────────────────────────────────── */
    public function create() {
        $user     = auth_user();
        $business = auth_business();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            return $this->save_standalone_receipt(null);
        }

        $receipt   = [];
        $items     = [];
        $editing   = false;
        $customers = $this->get_customers();

        $title     = "New Receipt — Direct Sale";
        $view_file = BASE_PATH . '/views/receipts/form.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ──────────────────────────────────────────
     *  VIEW — receipt detail
     * ────────────────────────────────────────── */
    public function view($id = null) {
        $user     = auth_user();
        $business = auth_business();

        $receipt = $this->find_receipt($id);
        if (!$receipt) { not_found(); return; }

        // Customer
        $customer = null;
        if ($receipt['customer_id']) {
            $stmtCust = $this->db->prepare("SELECT * FROM customers WHERE id = ? AND business_id = ?");
            $stmtCust->execute([$receipt['customer_id'], $this->biz_id]);
            $customer = $stmtCust->fetch();
        }

        // Linked invoice
        $invoice = null;
        if ($receipt['invoice_id']) {
            $stmtInv = $this->db->prepare("SELECT id, doc_number, total, amount_paid, balance_due, status FROM invoices WHERE id = ? AND business_id = ?");
            $stmtInv->execute([$receipt['invoice_id'], $this->biz_id]);
            $invoice = $stmtInv->fetch();
        }

        // Receipt items (for standalone POS receipts)
        $items = [];
        if (!$receipt['invoice_id']) {
            $stmtItems = $this->db->prepare("
                SELECT ri.*, p.name as product_name, p.sku as product_sku
                FROM receipt_items ri
                LEFT JOIN products p ON p.id = ri.product_id
                WHERE ri.receipt_id = ?
                ORDER BY ri.sort_order ASC
            ");
            $stmtItems->execute([$id]);
            $items = $stmtItems->fetchAll();
        }

        // Linked payment
        $payment = null;
        if ($receipt['payment_id']) {
            $stmtPay = $this->db->prepare("SELECT * FROM payments WHERE id = ?");
            $stmtPay->execute([$receipt['payment_id']]);
            $payment = $stmtPay->fetch();
        }

        $public_url = APP_URL . '/doc/' . $receipt['token'];
        $is_standalone = !$receipt['invoice_id'];

        $title     = e($receipt['doc_number']);
        $view_file = BASE_PATH . '/views/receipts/view.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ──────────────────────────────────────────
     *  DELETE — remove receipt
     * ────────────────────────────────────────── */
    public function delete($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('receipts'); return; }
        csrf_verify();

        $receipt = $this->find_receipt($id);
        if (!$receipt) { not_found(); return; }

        // Delete items if standalone
        if (!$receipt['invoice_id']) {
            $this->db->prepare("DELETE FROM receipt_items WHERE receipt_id = ?")->execute([$id]);
        }

        $this->db->prepare("DELETE FROM receipts WHERE id = ? AND business_id = ?")->execute([$id, $this->biz_id]);

        flash('success', $receipt['doc_number'] . ' deleted.');
        redirect('receipts');
    }

    /* ──────────────────────────────────────────
     *  AJAX — search products (for standalone form)
     * ────────────────────────────────────────── */
    public function search_products() {
        if (!is_ajax()) { http_response_code(403); exit; }
        $q = trim($_POST['q'] ?? '');
        if (strlen($q) < 1) { json_response(['results' => []]); }

        $stmt = $this->db->prepare("
            SELECT id, name, sku, sell_price, cost_price, stock_qty, unit, is_service
            FROM products
            WHERE business_id = ? AND deleted_at IS NULL AND is_active = 1
              AND (name LIKE ? OR sku LIKE ?)
            ORDER BY name ASC LIMIT 10
        ");
        $like = "%{$q}%";
        $stmt->execute([$this->biz_id, $like, $like]);

        $results = [];
        foreach ($stmt->fetchAll() as $p) {
            $results[] = [
                'id'         => (int)$p['id'],
                'name'       => $p['name'],
                'sku'        => $p['sku'],
                'sell_price' => (float)$p['sell_price'],
                'stock_qty'  => (float)$p['stock_qty'],
                'unit'       => $p['unit'],
                'is_service' => (int)$p['is_service'],
            ];
        }
        json_response(['results' => $results]);
    }

    /* ──────────────────────────────────────────
     *  AJAX — create customer inline
     * ────────────────────────────────────────── */
    public function create_customer() {
        if (!is_ajax() || $_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(403); exit; }
        csrf_verify();

        $name  = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($name === '') { json_response(['error' => 'Name is required'], 422); }

        $stmt = $this->db->prepare("INSERT INTO customers (business_id, full_name, phone, email) VALUES (?, ?, ?, ?)");
        $stmt->execute([$this->biz_id, $name, $phone ?: null, $email ?: null]);

        json_response([
            'id'        => (int)$this->db->lastInsertId(),
            'full_name' => $name,
            'phone'     => $phone,
        ]);
    }

    /* ══════════════════════════════════════════
     *  PRIVATE: save standalone receipt
     * ══════════════════════════════════════════ */
    private function save_standalone_receipt(?int $id): void {
        $user     = auth_user();
        $business = auth_business();

        $customer_id  = (int)($_POST['customer_id'] ?? 0) ?: null;
        $issue_date   = trim($_POST['issue_date'] ?? date('Y-m-d'));
        $method       = trim($_POST['method'] ?? 'cash');
        $note         = trim($_POST['note'] ?? '');

        $item_descs    = $_POST['item_description'] ?? [];
        $item_qtys     = $_POST['item_quantity'] ?? [];
        $item_prices   = $_POST['item_unit_price'] ?? [];
        $item_products = $_POST['item_product_id'] ?? [];

        $valid_methods = ['cash','bank_transfer','pos','mobile_money','cheque','other'];
        if (!in_array($method, $valid_methods)) $method = 'cash';

        // Validate
        $errors = [];
        if ($customer_id && !owned_by_business($this->db, 'customers', $customer_id, $this->biz_id)) {
            $errors[] = 'Please select one of your clients.';
        }
        if (empty($item_descs) || count(array_filter($item_descs, 'trim')) === 0) {
            $errors[] = 'At least one line item is required.';
        }

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            $receipt   = $_POST;
            $items     = [];
            $editing   = false;
            $customers = $this->get_customers();
            $title     = "New Receipt — Direct Sale";
            $view_file = BASE_PATH . '/views/receipts/form.php';
            require_once BASE_PATH . '/views/layouts/app.php';
            return;
        }

        // Calculate totals + build line items
        $total = 0;
        $line_items = [];
        for ($i = 0; $i < count($item_descs); $i++) {
            $desc = trim($item_descs[$i] ?? '');
            if ($desc === '') continue;

            $qty     = max(0.01, (float)($item_qtys[$i] ?? 1));
            $price   = max(0, (float)($item_prices[$i] ?? 0));
            $prod_id = (int)($item_products[$i] ?? 0) ?: null;
            if ($prod_id && !owned_by_business($this->db, 'products', $prod_id, $this->biz_id)) $prod_id = null;
            $sub     = round($qty * $price, 2);

            $total += $sub;
            $line_items[] = [
                'product_id'  => $prod_id,
                'description' => $desc,
                'quantity'    => $qty,
                'unit_price'  => $price,
                'subtotal'    => $sub,
                'sort_order'  => $i + 1,
            ];
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare("CALL generate_doc_number(?, 'REC', @rec_num)")->execute([$this->biz_id]);
            $doc_number = $this->db->query("SELECT @rec_num")->fetchColumn();
            $token = generate_token();

            $this->db->prepare("
                INSERT INTO receipts (business_id, invoice_id, payment_id, customer_id, user_id, doc_number, issue_date, amount, balance_remaining, method, note, token)
                VALUES (?, NULL, NULL, ?, ?, ?, ?, ?, 0, ?, ?, ?)
            ")->execute([
                $this->biz_id,
                $customer_id,
                $user['id'],
                $doc_number,
                $issue_date,
                $total,
                $method,
                $note ?: null,
                $token
            ]);
            $receipt_id = (int)$this->db->lastInsertId();

            // Insert line items
            $stmtItem = $this->db->prepare("
                INSERT INTO receipt_items (receipt_id, product_id, description, quantity, unit_price, subtotal, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($line_items as $li) {
                $stmtItem->execute([
                    $receipt_id, $li['product_id'], $li['description'],
                    $li['quantity'], $li['unit_price'], $li['subtotal'], $li['sort_order']
                ]);
            }

            // Deduct stock for non-service items (direct sale)
            foreach ($line_items as $li) {
                if (!$li['product_id']) continue;

                $stmtProd = $this->db->prepare("SELECT stock_qty, is_service FROM products WHERE id = ? AND business_id = ?");
                $stmtProd->execute([$li['product_id'], $this->biz_id]);
                $prod = $stmtProd->fetch();

                if (!$prod || $prod['is_service']) continue;

                $qty_before = (float)$prod['stock_qty'];
                $qty_after  = $qty_before - $li['quantity'];

                $this->db->prepare("UPDATE products SET stock_qty = ? WHERE id = ? AND business_id = ?")
                          ->execute([$qty_after, $li['product_id'], $this->biz_id]);

                $this->db->prepare("
                    INSERT INTO stock_movements (business_id, product_id, user_id, type, quantity, stock_before, stock_after, unit_cost, reference_type, reference_id, note, movement_date)
                    VALUES (?, ?, ?, 'sale', ?, ?, ?, ?, 'receipt', ?, ?, CURDATE())
                ")->execute([
                    $this->biz_id, $li['product_id'], $user['id'],
                    $li['quantity'], $qty_before, $qty_after,
                    $li['unit_price'], $receipt_id,
                    'Direct sale — receipt ' . $doc_number
                ]);
            }

            // Update customer financials if customer selected
            if ($customer_id) {
                $this->db->prepare("
                    UPDATE customers SET total_received = total_received + ? WHERE id = ? AND business_id = ?
                ")->execute([$total, $customer_id, $this->biz_id]);
            }

            $this->db->commit();

            // A sale paid on the spot is money in: owners and admins hear
            // about it, except whoever recorded it (app/helpers/notify.php).
            // A receipt for nothing is not news.
            if ((float)$total > 0) {
                $payer = '';
                if ($customer_id) {
                    $stmtWho = $this->db->prepare("SELECT full_name FROM customers WHERE id = ? AND business_id = ?");
                    $stmtWho->execute([$customer_id, $this->biz_id]);
                    $payer = trim((string)$stmtWho->fetchColumn());
                }
                notify('payment.receipt', $this->biz_id, [
                    'receipt_id' => $receipt_id, 'receipt_number' => $doc_number, 'payer' => $payer, 'amount' => (float)$total,
                ], ['actor' => (int)$user['id'], 'dedupe' => (string)$receipt_id]);
            }

            flash('success', 'Receipt ' . $doc_number . ' created.');
            redirect('receipts/view/' . $receipt_id);

        } catch (\Throwable $e) {
            $this->db->rollBack();
            flash('error', 'Save failed: ' . $e->getMessage());
            redirect('receipts');
        }
    }

    /* ══════════════════════════════════════════
     *  PRIVATE HELPERS
     * ══════════════════════════════════════════ */

    private function find_receipt($id): ?array {
        if (!$id) return null;
        $stmt = $this->db->prepare("SELECT * FROM receipts WHERE id = ? AND business_id = ?");
        $stmt->execute([(int)$id, $this->biz_id]);
        return $stmt->fetch() ?: null;
    }

    private function get_customers(): array {
        $stmt = $this->db->prepare("
            SELECT id, full_name, phone, email FROM customers
            WHERE business_id = ? AND deleted_at IS NULL AND is_active = 1
            ORDER BY full_name ASC
        ");
        $stmt->execute([$this->biz_id]);
        return $stmt->fetchAll();
    }
}