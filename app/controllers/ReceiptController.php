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
        if ((float)$payment['amount'] <= 0) {
            flash('error', 'A refund doesn\'t get a receipt.');
            redirect('invoices/view/' . $payment['invoice_id']);
        }

        // Generate receipt, once: the payment row is locked first, so a
        // double tap waits and then finds the receipt the first one made
        $this->db->beginTransaction();
        try {
            $this->db->prepare("SELECT id FROM payments WHERE id = ? AND business_id = ? FOR UPDATE")->execute([(int)$payment_id, $this->biz_id]);
            $stmtExist = $this->db->prepare("SELECT id FROM receipts WHERE payment_id = ? AND business_id = ?");
            $stmtExist->execute([$payment_id, $this->biz_id]);
            $existing = $stmtExist->fetch();
            if ($existing) {
                $this->db->rollBack();
                flash('info', 'Receipt already exists for this payment.');
                redirect('receipts/view/' . $existing['id']);
                return;
            }

            $doc_number = generate_doc_number($this->db, $this->biz_id, 'REC');
            $token = generate_token();

            // Balance left on the invoice right after this payment (not now:
            // later payments are not this receipt's business)
            $stmtPaid = $this->db->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = ? AND business_id = ? AND id <= ?");
            $stmtPaid->execute([$payment['invoice_id'], $this->biz_id, (int)$payment_id]);
            $balance_remaining = max(0, round((float)$payment['invoice_total'] - (float)$stmtPaid->fetchColumn(), 2));

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
     *  CREATE — every sale is an order now: a sale on the spot is a quick
     *  sale, which makes its order, invoice and receipt in one go
     *  (docs/ORDERS_HUB.md). Receipts for payments come with the payment.
     * ────────────────────────────────────────── */
    public function create() {
        redirect('orders/quick');
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
        $order = !empty($receipt['order_id']) ? order_find($this->db, $this->biz_id, (int)$receipt['order_id'])
               : ($receipt['invoice_id'] ? order_by_invoice($this->db, $this->biz_id, (int)$receipt['invoice_id']) : null);

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
        // Every payment on an order keeps its receipt (docs/ORDERS_HUB.md)
        $order = !empty($receipt['order_id']) ? order_find($this->db, $this->biz_id, (int)$receipt['order_id'])
            : ($receipt['invoice_id'] ? order_by_invoice($this->db, $this->biz_id, (int)$receipt['invoice_id']) : null);
        if ($order) {
            flash('error', $receipt['doc_number'] . ' is the receipt for a payment on order ' . $order['order_number'] . ', so it stays with its payment.');
            redirect('receipts/view/' . (int)$id);
            return;
        }

        // A receipt for an invoice payment is only its paperwork: the payment
        // stays on the invoice (and in the books). A direct sale's receipt is
        // the sale itself, so its stock goes back and its money comes off.
        $this->db->beginTransaction();
        try {
            if (!$receipt['invoice_id']) {
                stock_settle($this->db, $this->biz_id, 'receipt', (int)$id, [], 'receipt ' . $receipt['doc_number'] . ' deleted');
                $this->db->prepare("DELETE FROM receipt_items WHERE receipt_id = ?")->execute([$id]);
            }
            // Orders, bookings and deeds that showed this receipt stop linking to it
            foreach (['orders', 'bookings', 'deeds'] as $table) {
                $this->db->prepare("UPDATE {$table} SET receipt_id = NULL WHERE receipt_id = ? AND business_id = ?")->execute([$id, $this->biz_id]);
            }
            $this->db->prepare("DELETE FROM receipts WHERE id = ? AND business_id = ?")->execute([$id, $this->biz_id]);
            customer_refresh_totals($this->db, $this->biz_id, $receipt['customer_id'] ? (int)$receipt['customer_id'] : null);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log('Receipt delete error: ' . $e->getMessage());
            flash('error', 'Could not delete ' . $receipt['doc_number'] . '. Please try again.');
            redirect('receipts/view/' . (int)$id);
        }

        flash('success', $receipt['doc_number'] . ' deleted.');
        redirect('receipts');
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
}