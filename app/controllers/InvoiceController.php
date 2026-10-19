<?php
/**
 * Nfelia — Invoice Controller ("Commercial Invoice")
 * Full lifecycle: CRUD, send (stock deduction), payment recording,
 * auto-receipts, overdue detection, debt tracking, cancellation (stock restore),
 * WhatsApp reminders, in-app notifications.
 */
class InvoiceController {

    private $db;
    private $biz_id;

    public function __construct() {
        if (!is_logged_in()) { redirect('login'); }
        $this->db     = get_db();
        $this->biz_id = auth_business_id();
    }

    /* ──────────────────────────────────────────
     *  LIST — all invoices with filters
     * ────────────────────────────────────────── */
    public function index() {
        $user     = auth_user();
        $business = auth_business();

        $search   = trim($_GET['q'] ?? '');
        $status   = trim($_GET['status'] ?? '');
        $sort     = trim($_GET['sort'] ?? 'newest');
        $page     = max(1, (int)($_GET['page'] ?? 1));
        $per_page = 20;
        $offset   = ($page - 1) * $per_page;

        // ── Auto-expire overdue invoices ──
        $this->db->prepare("
            UPDATE invoices SET status = 'overdue'
            WHERE business_id = ? AND status IN ('sent','partially_paid')
              AND due_date IS NOT NULL AND due_date < CURDATE()
        ")->execute([$this->biz_id]);

        // ── Auto-create debt records for newly overdue ──
        $this->create_overdue_debts();

        $where  = ["i.business_id = ?"];
        $params = [$this->biz_id];

        if ($search !== '') {
            $where[]  = "(i.doc_number LIKE ? OR c.full_name LIKE ?)";
            $like     = "%{$search}%";
            $params[] = $like;
            $params[] = $like;
        }
        if ($status === 'unpaid') {
            $where[] = "i.status IN ('sent','partially_paid','overdue')";
        } elseif ($status !== '' && in_array($status, ['draft','sent','partially_paid','paid','overdue','cancelled'])) {
            $where[]  = "i.status = ?";
            $params[] = $status;
        } else {
            $status = '';
        }

        $where_sql = implode(' AND ', $where);
        $order = match($sort) {
            'oldest'       => 'i.created_at ASC',
            'total_desc'   => 'i.total DESC',
            'total_asc'    => 'i.total ASC',
            'due_date'     => 'i.due_date ASC',
            'balance_desc' => 'i.balance_due DESC',
            default        => 'i.created_at DESC',
        };

        // Count
        $stmtC = $this->db->prepare("SELECT COUNT(*) FROM invoices i LEFT JOIN customers c ON c.id = i.customer_id WHERE {$where_sql}");
        $stmtC->execute($params);
        $total_items = (int)$stmtC->fetchColumn();
        $total_pages = max(1, ceil($total_items / $per_page));

        // Fetch
        $stmtQ = $this->db->prepare("
            SELECT i.*, c.full_name as customer_name, c.phone as customer_phone
            FROM invoices i
            LEFT JOIN customers c ON c.id = i.customer_id
            WHERE {$where_sql}
            ORDER BY {$order}
            LIMIT {$per_page} OFFSET {$offset}
        ");
        $stmtQ->execute($params);
        $invoices = $stmtQ->fetchAll();

        // Summary
        $stmtS = $this->db->prepare("
            SELECT
                COUNT(*)                                                           AS total_invoices,
                COALESCE(SUM(total), 0)                                            AS total_value,
                COALESCE(SUM(amount_paid), 0)                                      AS total_collected,
                COALESCE(SUM(balance_due), 0)                                      AS total_outstanding,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END)                  AS paid_count,
                SUM(CASE WHEN status IN ('sent','partially_paid') THEN 1 ELSE 0 END) AS active_count,
                SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END)               AS overdue_count,
                SUM(CASE WHEN status = 'overdue' THEN balance_due ELSE 0 END)      AS overdue_value,
                SUM(CASE WHEN status IN ('sent','partially_paid','overdue') THEN 1 ELSE 0 END)           AS unpaid_count,
                COALESCE(SUM(CASE WHEN status IN ('sent','partially_paid','overdue') THEN balance_due END), 0) AS owed,
                SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END)                 AS draft_count
            FROM invoices WHERE business_id = ?
        ");
        $stmtS->execute([$this->biz_id]);
        $summary = $stmtS->fetch();

        $title     = "Invoices";
        $view_file = BASE_PATH . '/views/invoices/index.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ──────────────────────────────────────────
     *  CREATE — invoice builder
     * ────────────────────────────────────────── */
    public function create() {
        $user     = auth_user();
        $business = auth_business();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            return $this->save_invoice(null);
        }

        $invoice   = [];
        $items     = [];
        $editing   = false;
        $customers = $this->get_customers();
        $banks     = $this->get_bank_accounts();

        $title     = "New Invoice";
        $view_file = BASE_PATH . '/views/invoices/form.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ──────────────────────────────────────────
     *  EDIT — update existing invoice
     * ────────────────────────────────────────── */
    public function edit($id = null) {
        $user     = auth_user();
        $business = auth_business();

        $invoice = $this->find_invoice($id);
        if (!$invoice) { not_found(); return; }

        if (!in_array($invoice['status'], ['draft', 'sent'])) {
            flash('error', 'This invoice can no longer be edited.');
            redirect('invoices/view/' . $id);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            return $this->save_invoice($id);
        }

        $stmtItems = $this->db->prepare("
            SELECT ii.*, p.name as product_name
            FROM invoice_items ii
            LEFT JOIN products p ON p.id = ii.product_id
            WHERE ii.invoice_id = ?
            ORDER BY ii.sort_order ASC
        ");
        $stmtItems->execute([$id]);
        $items = $stmtItems->fetchAll();

        $editing   = true;
        $customers = $this->get_customers();
        $banks     = $this->get_bank_accounts();

        $title     = "Edit — " . e($invoice['doc_number']);
        $view_file = BASE_PATH . '/views/invoices/form.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ──────────────────────────────────────────
     *  VIEW — invoice detail + actions + payment history
     * ────────────────────────────────────────── */
    public function view($id = null) {
        $user     = auth_user();
        $business = auth_business();

        $invoice = $this->find_invoice($id);
        if (!$invoice) { not_found(); return; }

        // Customer
        $stmtCust = $this->db->prepare("SELECT * FROM customers WHERE id = ? AND business_id = ?");
        $stmtCust->execute([$invoice['customer_id'], $this->biz_id]);
        $customer = $stmtCust->fetch();

        // Items
        $stmtItems = $this->db->prepare("
            SELECT ii.*, p.name as product_name, p.sku as product_sku, p.is_service
            FROM invoice_items ii
            LEFT JOIN products p ON p.id = ii.product_id
            WHERE ii.invoice_id = ?
            ORDER BY ii.sort_order ASC
        ");
        $stmtItems->execute([$id]);
        $items = $stmtItems->fetchAll();

        // Bank account
        $bank = null;
        if ($invoice['bank_account_id']) {
            $stmtBank = $this->db->prepare("SELECT * FROM bank_accounts WHERE id = ? AND business_id = ?");
            $stmtBank->execute([$invoice['bank_account_id'], $this->biz_id]);
            $bank = $stmtBank->fetch();
        }

        // Payment history
        $stmtPay = $this->db->prepare("
            SELECT p.*, r.doc_number as receipt_number, r.id as receipt_id
            FROM payments p
            LEFT JOIN receipts r ON r.payment_id = p.id
            WHERE p.invoice_id = ? AND p.business_id = ?
            ORDER BY p.payment_date DESC, p.id DESC
        ");
        $stmtPay->execute([$id, $this->biz_id]);
        $payments = $stmtPay->fetchAll();

        // Linked quotation
        $quotation = null;
        if ($invoice['quotation_id']) {
            $stmtQ = $this->db->prepare("SELECT id, doc_number FROM quotations WHERE id = ?");
            $stmtQ->execute([$invoice['quotation_id']]);
            $quotation = $stmtQ->fetch();
        }

        $public_url = APP_URL . '/doc/' . $invoice['token'];

        // A signed delivery note also counts as delivered.
        $stmtDn = $this->db->prepare("SELECT 1 FROM deliveries WHERE invoice_id = ? AND business_id = ? AND deleted_at IS NULL AND status IN ('delivered','signed_off') LIMIT 1");
        $stmtDn->execute([$id, $this->biz_id]);
        $delivered_by_note = (bool)$stmtDn->fetchColumn();

        // A job plan following this invoice, if any.
        $stmtJob = $this->db->prepare("
            SELECT p.id, p.title,
                   (SELECT COUNT(*) FROM tasks s WHERE s.parent_id = p.id AND s.deleted_at IS NULL) AS total,
                   (SELECT COUNT(*) FROM tasks s WHERE s.parent_id = p.id AND s.deleted_at IS NULL AND s.done_at IS NOT NULL) AS done
            FROM tasks p
            WHERE p.invoice_id = ? AND p.business_id = ? AND p.plan IS NOT NULL AND p.parent_id IS NULL AND p.deleted_at IS NULL
            ORDER BY p.id DESC LIMIT 1");
        $stmtJob->execute([$id, $this->biz_id]);
        $job_plan = $stmtJob->fetch() ?: null;

        $title     = e($invoice['doc_number']);
        $view_file = BASE_PATH . '/views/invoices/view.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ──────────────────────────────────────────
     *  DELETE — only drafts
     * ────────────────────────────────────────── */
    public function delete($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('invoices'); return; }
        csrf_verify();

        $invoice = $this->find_invoice($id);
        if (!$invoice) { not_found(); return; }

        if ($invoice['status'] !== 'draft') {
            flash('error', 'Only draft invoices can be deleted.');
            redirect('invoices/view/' . $id);
            return;
        }

        // Tasks and job plans made for it stop pointing at it (P8)
        $this->db->prepare("UPDATE tasks SET invoice_id = NULL, step_key = NULL WHERE invoice_id = ? AND business_id = ?")->execute([$id, $this->biz_id]);
        $this->db->prepare("DELETE FROM invoice_items WHERE invoice_id = ?")->execute([$id]);
        $this->db->prepare("DELETE FROM invoices WHERE id = ? AND business_id = ?")->execute([$id, $this->biz_id]);

        flash('success', $invoice['doc_number'] . ' deleted.');
        redirect('invoices');
    }

    /* ──────────────────────────────────────────
     *  SEND — mark sent + deduct stock + update customer
     * ────────────────────────────────────────── */
    public function send($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('invoices'); return; }
        csrf_verify();

        $invoice = $this->find_invoice($id);
        if (!$invoice || $invoice['status'] !== 'draft') {
            flash('error', 'Only draft invoices can be sent.');
            redirect('invoices');
            return;
        }

        $this->db->beginTransaction();
        try {
            // Claim it: a double tap finds it already sent, and moves nothing twice
            $stmtClaim = $this->db->prepare("UPDATE invoices SET status = 'sent' WHERE id = ? AND business_id = ? AND status = 'draft'");
            $stmtClaim->execute([$id, $this->biz_id]);
            if ($stmtClaim->rowCount() !== 1) {
                $this->db->rollBack();
                flash('error', 'This invoice has already been sent.');
                redirect('invoices/view/' . $id);
            }

            // The goods leave the shelf (services have no stock)
            stock_settle($this->db, $this->biz_id, 'invoice', (int)$id, invoice_stock_target($this->db, $this->biz_id, (int)$id),
                         'invoice ' . $invoice['doc_number']);
            customer_refresh_totals($this->db, $this->biz_id, (int)$invoice['customer_id']);

            $this->db->commit();
            flash('success', $invoice['doc_number'] . ' marked as sent. Stock deducted.');
            redirect('invoices/view/' . $id);

        } catch (\Throwable $e) {
            $this->db->rollBack();
            flash('error', 'Failed to send: ' . $e->getMessage());
            redirect('invoices/view/' . $id);
        }
    }

    /* ──────────────────────────────────────────
     *  RECORD PAYMENT — AJAX
     * ────────────────────────────────────────── */
    public function record_payment($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('invoices'); return; }

        $invoice = $this->find_invoice($id);
        if (!$invoice) {
            if (is_ajax()) { json_response(['error' => 'Invoice not found'], 404); }
            not_found(); return;
        }

        if (!in_array($invoice['status'], ['sent', 'partially_paid', 'overdue'])) {
            if (is_ajax()) { json_response(['error' => 'Payments cannot be recorded on this invoice'], 422); }
            flash('error', 'Payments cannot be recorded on this invoice.');
            redirect('invoices/view/' . $id);
            return;
        }

        csrf_verify();
        $user = auth_user();

        $amount       = (float)($_POST['amount'] ?? 0);
        $payment_date = trim($_POST['payment_date'] ?? date('Y-m-d'));
        $method       = trim($_POST['method'] ?? 'cash');
        $reference    = trim($_POST['reference'] ?? '');
        $note         = trim($_POST['note'] ?? '');

        $valid_methods = ['cash','bank_transfer','pos','mobile_money','cheque','other'];
        if (!in_array($method, $valid_methods)) $method = 'cash';
        $symbol = auth_business()['currency_symbol'] ?? '₦';

        $this->db->beginTransaction();
        try {
            // Locks the invoice, checks what is still owed, and keeps its debt
            // and the client's figures in step (app/helpers/invoices.php)
            $paid = invoice_record_payment($this->db, $this->biz_id, (int)$id, $amount, $payment_date, $method,
                                           $reference, $note, (int)$user['id'], $symbol);
            $payment_id  = $paid['payment_id'];
            $new_paid    = $paid['new_paid'];
            $new_balance = $paid['new_balance'];
            $new_status  = $paid['new_status'];
            $amount      = money2($amount);

            $this->db->commit();

            // Owners and admins hear about it, except whoever recorded it
            // (app/helpers/notify.php).
            $stmtWho = $this->db->prepare("SELECT full_name FROM customers WHERE id = ? AND business_id = ?");
            $stmtWho->execute([$invoice['customer_id'], $this->biz_id]);
            notify('payment.invoice', $this->biz_id, [
                'invoice_id' => (int)$id, 'invoice_number' => $invoice['doc_number'],
                'customer' => trim((string)$stmtWho->fetchColumn()), 'amount' => $amount,
                'method' => str_replace('_', ' ', $method), 'balance' => max(0, $new_balance),
            ], ['actor' => (int)$user['id'], 'dedupe' => (string)$payment_id]);

            $result = [
                'success'      => true,
                'payment_id'   => $payment_id,
                'new_status'   => $new_status,
                'amount_paid'  => $new_paid,
                'balance_due'  => max(0, $new_balance),
                'fully_paid'   => $new_balance <= 0,
                'receipt_url'  => APP_URL . '/receipts/from_payment/' . $payment_id,
            ];

            // The page reloads after an AJAX payment, so the message waits for it.
            flash('success', planner_money($amount, auth_business()['currency_symbol'] ?? '₦') . ' recorded.' . ($new_balance <= 0 ? ' The invoice is fully paid.' : ''));
            if (is_ajax()) { json_response($result); }
            redirect('invoices/view/' . $id);

        } catch (\DomainException $e) {
            $this->db->rollBack();
            if (is_ajax()) { json_response(['error' => $e->getMessage()], 422); }
            flash('error', $e->getMessage());
            redirect('invoices/view/' . $id);
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if (is_ajax()) { json_response(['error' => 'Failed: ' . $e->getMessage()], 500); }
            flash('error', 'Payment failed: ' . $e->getMessage());
            redirect('invoices/view/' . $id);
        }
    }

    /* ──────────────────────────────────────────
     *  REFUND — money given back, recorded as a minus payment
     * ────────────────────────────────────────── */
    public function record_refund($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('invoices'); return; }
        csrf_verify();
        $invoice = $this->find_invoice($id);
        if (!$invoice) { not_found(); return; }

        $method = trim($_POST['method'] ?? 'cash');
        if (!in_array($method, ['cash','bank_transfer','pos','mobile_money','cheque','other'], true)) $method = 'cash';
        $symbol = auth_business()['currency_symbol'] ?? '₦';
        $this->db->beginTransaction();
        try {
            $refund = invoice_record_refund($this->db, $this->biz_id, (int)$id, (float)($_POST['amount'] ?? 0),
                                            trim($_POST['refund_date'] ?? '') ?: date('Y-m-d'), $method,
                                            mb_substr(trim($_POST['note'] ?? ''), 0, 500), (int)auth_user()['id'], $symbol);
            $this->db->commit();
            flash('success', 'Refund of ' . money(money2((float)$_POST['amount']), $symbol) . ' recorded on ' . $invoice['doc_number'] . '.');
        } catch (\DomainException $e) {
            $this->db->rollBack();
            flash('error', $e->getMessage());
        }
        redirect('invoices/view/' . $id);
    }

    /* ──────────────────────────────────────────
     *  CANCEL — restore stock + log movements
     * ────────────────────────────────────────── */
    public function cancel($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('invoices'); return; }
        csrf_verify();

        $invoice = $this->find_invoice($id);
        if (!$invoice) { not_found(); return; }

        if ($invoice['status'] === 'cancelled') {
            flash('error', 'Invoice is already cancelled.');
            redirect('invoices/view/' . $id);
            return;
        }

        $this->db->beginTransaction();
        try {
            // Once, with nothing paid on it; puts back exactly the stock it
            // took off and closes its order and delivery (app/helpers/invoices.php)
            $problem = invoice_cancel($this->db, $this->biz_id, (int)$id, auth_business()['currency_symbol'] ?? '₦');
            if ($problem !== '') {
                $this->db->rollBack();
                flash('error', $problem);
                redirect('invoices/view/' . $id);
            }
            $this->db->commit();
            flash('success', $invoice['doc_number'] . ' cancelled. Stock restored.');
            redirect('invoices/view/' . $id);

        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            flash('error', 'Cancellation failed: ' . $e->getMessage());
            redirect('invoices/view/' . $id);
        }
    }

    /* ──────────────────────────────────────────
     *  SEND REMINDER — generate WhatsApp link
     * ────────────────────────────────────────── */
    public function send_reminder($id = null) {
        $invoice = $this->find_invoice($id);
        if (!$invoice) { not_found(); return; }

        $stmtCust = $this->db->prepare("SELECT * FROM customers WHERE id = ? AND business_id = ?");
        $stmtCust->execute([$invoice['customer_id'], $this->biz_id]);
        $customer = $stmtCust->fetch();

        $business = auth_business();
        $currency = $business['currency_symbol'] ?? '₦';

        $wa_number = $customer['whatsapp'] ?: $customer['phone'] ?: '';
        $wa_number = wa_digits($wa_number, auth_business());

        $days_overdue = '';
        if ($invoice['due_date']) {
            $diff = (int)((time() - strtotime($invoice['due_date'])) / 86400);
            if ($diff > 0) $days_overdue = " ({$diff} day" . ($diff > 1 ? 's' : '') . " past due)";
        }

        $public_url = APP_URL . '/doc/' . $invoice['token'];
        $wa_text = urlencode(
            "Hello " . ($customer['full_name'] ?? '') . ",\n\n"
            . "This is a friendly reminder regarding your outstanding invoice from *{$business['name']}*:\n\n"
            . "Invoice: *{$invoice['doc_number']}*\n"
            . "Total: *{$currency}" . number_format((float)$invoice['total'], 2) . "*\n"
            . "Balance Due: *{$currency}" . number_format((float)$invoice['balance_due'], 2) . "*" . $days_overdue . "\n"
            . ($invoice['due_date'] ? "Due Date: " . fmt_date($invoice['due_date'], 'd M Y') . "\n" : '')
            . "\nView invoice: {$public_url}\n\n"
            . "Kindly let us know once payment is made. Thank you!\n\n"
            . "_Sent via Nfelia — nfelia.com_"
        );

        $wa_link = "https://wa.me/{$wa_number}?text={$wa_text}";

        if (is_ajax()) {
            json_response(['wa_link' => $wa_link]);
        }

        // Redirect to WhatsApp
        header("Location: {$wa_link}");
        exit;
    }

    /* ──────────────────────────────────────────
     *  DELIVERY — set or change when the customer gets it
     * ────────────────────────────────────────── */
    public function set_delivery($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('invoices/view/' . (int)$id); }
        csrf_verify();
        $invoice = $this->find_invoice($id);
        if (!$invoice) { not_found(); return; }

        [$delivery, $error] = planner_delivery_from_post($_POST, $invoice);
        if ($error) {
            flash('error', $error);
            redirect('invoices/view/' . $invoice['id']);
        }
        $this->db->prepare("UPDATE invoices SET deliver_mode = ?, deliver_by = ?, deliver_show = ?, delivered_at = ? WHERE id = ? AND business_id = ?")
                 ->execute([$delivery['deliver_mode'], $delivery['deliver_by'], $delivery['deliver_show'], $delivery['delivered_at'], $invoice['id'], $this->biz_id]);
        // A job plan's delivery step follows the invoice's new date, with its
        // reminder and the job's finish date (P6)
        planner_move_delivery($this->db, $this->biz_id, (int)$invoice['id'], $delivery['deliver_by']);
        if ($delivery['delivered_at']) {
            planner_invoice_delivered($this->db, $this->biz_id, (int)$invoice['id'], false, (int)auth_user()['id']);
        }

        flash('success', $delivery['delivered_at'] ? 'Saved as delivered.' : 'Delivery date saved. It is on your Tasks & Calendar.');
        redirect('invoices/view/' . $invoice['id']);
    }

    /* ──────────────────────────────────────────
     *  DELIVERY — mark delivered, or undo
     * ────────────────────────────────────────── */
    public function mark_delivered($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('invoices/view/' . (int)$id); }
        csrf_verify();
        $invoice = $this->find_invoice($id);
        if (!$invoice) { not_found(); return; }

        // The promise, its delivery step and its job move together
        // (app/helpers/planner.php)
        $undo = !empty($_POST['undo']);
        planner_invoice_delivered($this->db, $this->biz_id, (int)$invoice['id'], $undo, (int)auth_user()['id']);

        flash('success', $undo ? 'Marked as not delivered yet.' : 'Marked delivered.');
        redirect('invoices/view/' . $invoice['id']);
    }

    /* ──────────────────────────────────────────
     *  AJAX — search products
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
        if ($p = phone_problem($phone)) { json_response(['error' => $p], 422); }   // D4

        $stmt = $this->db->prepare("INSERT INTO customers (business_id, full_name, phone, email) VALUES (?, ?, ?, ?)");
        $stmt->execute([$this->biz_id, $name, $phone ?: null, $email ?: null]);

        json_response([
            'id'        => (int)$this->db->lastInsertId(),
            'full_name' => $name,
            'phone'     => $phone,
        ]);
    }

    /* ══════════════════════════════════════════
     *  PRIVATE: save / update invoice
     * ══════════════════════════════════════════ */
    private function save_invoice(?int $id): void {
        $user     = auth_user();
        $business = auth_business();

        $customer_id     = (int)($_POST['customer_id'] ?? 0);
        $bank_id         = (int)($_POST['bank_account_id'] ?? 0) ?: null;
        $issue_date      = trim($_POST['issue_date'] ?? date('Y-m-d'));
        $due_date        = trim($_POST['due_date'] ?? '') ?: null;
        $tax_rate        = (float)($_POST['tax_rate'] ?? 0);
        $notes           = trim($_POST['notes'] ?? '');
        $payment_terms   = trim($_POST['payment_terms'] ?? '');
        $discount_amount = (float)($_POST['discount_amount'] ?? 0);

        $item_descs     = $_POST['item_description'] ?? [];
        $item_qtys      = $_POST['item_quantity'] ?? [];
        $item_prices    = $_POST['item_unit_price'] ?? [];
        $item_discounts = $_POST['item_discount'] ?? [];
        $item_products  = $_POST['item_product_id'] ?? [];

        // Validate
        $errors = [];
        if ($customer_id <= 0 || !owned_by_business($this->db, 'customers', $customer_id, $this->biz_id)) {
            $errors[] = 'Please select a client.';
        }
        if ($bank_id && !owned_by_business($this->db, 'bank_accounts', $bank_id, $this->biz_id)) {
            $errors[] = 'Please pick one of your bank accounts.';
        }
        if (empty($item_descs) || count(array_filter($item_descs, 'trim')) === 0) {
            $errors[] = 'At least one line item is required.';
        }
        // When the customer gets it: required on our side (Tasks & Calendar).
        [$delivery, $delivery_error] = planner_delivery_from_post($_POST, $id ? $this->find_invoice($id) : null);
        if ($delivery_error) $errors[] = $delivery_error;

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            // What was typed, over the saved invoice (so an edit keeps its id
            // and posts back to itself), with its lines as typed
            $invoice   = array_merge($id ? ($this->find_invoice($id) ?? []) : [], $_POST);
            $items     = posted_lines($_POST);
            $editing   = $id !== null;
            $customers = $this->get_customers();
            $banks     = $this->get_bank_accounts();
            $title     = $editing ? "Edit Invoice" : "New Invoice";
            $view_file = BASE_PATH . '/views/invoices/form.php';
            require_once BASE_PATH . '/views/layouts/app.php';
            return;
        }

        // Calculate totals
        $subtotal = 0;
        $line_items = [];
        for ($i = 0; $i < count($item_descs); $i++) {
            $desc = trim($item_descs[$i] ?? '');
            if ($desc === '') continue;

            $qty       = max(0.01, (float)($item_qtys[$i] ?? 1));
            $price     = max(0, (float)($item_prices[$i] ?? 0));
            $disc_pct  = max(0, min(100, (float)($item_discounts[$i] ?? 0)));
            $prod_id   = (int)($item_products[$i] ?? 0) ?: null;
            if ($prod_id && !owned_by_business($this->db, 'products', $prod_id, $this->biz_id)) $prod_id = null;

            $line_total = money2($qty * $price);
            $disc_amt   = money2($line_total * $disc_pct / 100);
            $line_sub   = money2($line_total - $disc_amt);

            $subtotal = money2($subtotal + $line_sub);
            $line_items[] = [
                'product_id'       => $prod_id,
                'description'      => $desc,
                'quantity'         => $qty,
                'unit_price'       => $price,
                'discount_percent' => $disc_pct,
                'discount_amount'  => $disc_amt,
                'subtotal'         => $line_sub,
                'sort_order'       => $i + 1,
            ];
        }

        // A discount can't be more than the lines, and tax is 0 to 100%
        $discount_amount         = money2(max(0, min($subtotal, $discount_amount)));
        $tax_rate                = max(0, min(100, $tax_rate));
        $subtotal_after_discount = money2($subtotal - $discount_amount);
        $tax_amount = money2($subtotal_after_discount * $tax_rate / 100);
        $total      = money2($subtotal_after_discount + $tax_amount);

        $this->db->beginTransaction();
        try {
            $locked = null;
            if ($id) {
                // Lock it while it changes; a sent invoice's goods are already
                // off the shelf, so its stock moves by the difference below
                $stmtLock = $this->db->prepare("SELECT status, doc_number, customer_id FROM invoices WHERE id = ? AND business_id = ? FOR UPDATE");
                $stmtLock->execute([$id, $this->biz_id]);
                $locked = $stmtLock->fetch();
                if (!$locked || !in_array($locked['status'], ['draft', 'sent'], true)) {
                    throw new \DomainException('This invoice can no longer be edited.');
                }

                $this->db->prepare("
                    UPDATE invoices SET
                        customer_id = ?, bank_account_id = ?, issue_date = ?, due_date = ?,
                        subtotal = ?, discount_amount = ?, tax_rate = ?, tax_amount = ?, total = ?,
                        balance_due = ? - amount_paid, payment_terms = ?, notes = ?,
                        deliver_mode = ?, deliver_by = ?, deliver_show = ?, delivered_at = ?
                    WHERE id = ? AND business_id = ?
                ")->execute([
                    $customer_id, $bank_id, $issue_date, $due_date,
                    $subtotal, $discount_amount, $tax_rate, $tax_amount, $total,
                    $total, $payment_terms ?: null, $notes ?: null,
                    $delivery['deliver_mode'], $delivery['deliver_by'], $delivery['deliver_show'], $delivery['delivered_at'],
                    $id, $this->biz_id
                ]);
                // A job plan's delivery step follows the invoice's date (P6)
                planner_move_delivery($this->db, $this->biz_id, (int)$id, $delivery['deliver_by']);

                $this->db->prepare("DELETE FROM invoice_items WHERE invoice_id = ?")->execute([$id]);
            } else {
                $doc_number = generate_doc_number($this->db, $this->biz_id, 'INV');
                $token = generate_token();

                $this->db->prepare("
                    INSERT INTO invoices (business_id, customer_id, bank_account_id, user_id, doc_number, status, issue_date, due_date, subtotal, discount_amount, tax_rate, tax_amount, total, amount_paid, balance_due, payment_terms, notes, token,
                                          deliver_mode, deliver_by, deliver_show, delivered_at)
                    VALUES (?, ?, ?, ?, ?, 'draft', ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $this->biz_id, $customer_id, $bank_id, $user['id'],
                    $doc_number, $issue_date, $due_date,
                    $subtotal, $discount_amount, $tax_rate, $tax_amount, $total,
                    $total, $payment_terms ?: null, $notes ?: null, $token,
                    $delivery['deliver_mode'], $delivery['deliver_by'], $delivery['deliver_show'], $delivery['delivered_at']
                ]);
                $id = (int)$this->db->lastInsertId();
            }

            $stmtItem = $this->db->prepare("
                INSERT INTO invoice_items (invoice_id, product_id, description, quantity, unit_price, discount_percent, discount_amount, subtotal, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($line_items as $li) {
                $stmtItem->execute([
                    $id, $li['product_id'], $li['description'], $li['quantity'],
                    $li['unit_price'], $li['discount_percent'], $li['discount_amount'],
                    $li['subtotal'], $li['sort_order']
                ]);
            }

            $restocked = false;
            if ($locked && $locked['status'] === 'sent') {
                // Stock moves by exactly the difference from what it moved
                // before (app/helpers/stock.php), and a delivery nobody has
                // signed for yet lists the new lines
                $restocked = stock_settle($this->db, $this->biz_id, 'invoice', $id, invoice_stock_target($this->db, $this->biz_id, $id),
                                          'invoice ' . $locked['doc_number'] . ' edited');
                $this->refresh_open_deliveries($id);
            }
            customer_refresh_totals($this->db, $this->biz_id, $customer_id);
            if ($locked && (int)$locked['customer_id'] !== $customer_id) {
                customer_refresh_totals($this->db, $this->biz_id, (int)$locked['customer_id']);
            }

            $this->db->commit();
            flash('success', $restocked ? 'Invoice saved. Stock updated to match.' : 'Invoice saved.');
            redirect('invoices/view/' . $id);

        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            flash('error', $e instanceof \DomainException ? $e->getMessage() : 'Save failed: ' . $e->getMessage());
            redirect($id ? 'invoices/view/' . $id : 'invoices');
        }
    }

    /* ══════════════════════════════════════════
     *  PRIVATE HELPERS
     * ══════════════════════════════════════════ */

    /**
     * Deliveries of this invoice that nobody has signed for yet list its lines
     * as they are now, so the customer signs for what they get.
     */
    private function refresh_open_deliveries(int $invoice_id): void {
        $stmt = $this->db->prepare("SELECT id FROM deliveries WHERE invoice_id = ? AND business_id = ? AND deleted_at IS NULL
                                    AND status IN ('pending_dispatch', 'dispatched', 'in_transit')");
        $stmt->execute([$invoice_id, $this->biz_id]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $delivery_id) {
            $this->db->prepare("DELETE FROM delivery_items WHERE delivery_id = ?")->execute([$delivery_id]);
            $this->db->prepare("
                INSERT INTO delivery_items (delivery_id, product_id, description, quantity)
                SELECT ?, product_id, description, quantity FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order
            ")->execute([$delivery_id, $invoice_id]);
        }
    }

    private function find_invoice($id): ?array {
        if (!$id) return null;
        $stmt = $this->db->prepare("SELECT * FROM invoices WHERE id = ? AND business_id = ?");
        $stmt->execute([(int)$id, $this->biz_id]);
        return $stmt->fetch() ?: null;
    }

    private function get_customers(): array {
        $stmt = $this->db->prepare("
            SELECT id, full_name, phone, email FROM customers
            WHERE business_id = ? AND deleted_at IS NULL AND is_active = 1 AND is_walk_in = 0
            ORDER BY full_name ASC
        ");
        $stmt->execute([$this->biz_id]);
        return $stmt->fetchAll();
    }

    private function get_bank_accounts(): array {
        $stmt = $this->db->prepare("
            SELECT * FROM bank_accounts WHERE business_id = ? ORDER BY is_default DESC, sort_order ASC
        ");
        $stmt->execute([$this->biz_id]);
        return $stmt->fetchAll();
    }

    /**
     * Create debt records for invoices that just became overdue. Called from
     * index() on each page load. The overdue alert itself comes from the
     * cron, every morning, whether or not anyone opens this list
     * (cron/planner.php).
     */
    private function create_overdue_debts(): void {
        // Overdue invoices that have never had a debt record: one written off
        // or cleared stays that way, and never comes back as a second debt
        $stmt = $this->db->prepare("
            SELECT i.id, i.doc_number, i.customer_id, i.balance_due, i.due_date, i.user_id,
                   c.full_name as customer_name, c.phone as customer_phone
            FROM invoices i
            LEFT JOIN customers c ON c.id = i.customer_id AND c.business_id = i.business_id
            WHERE i.business_id = ? AND i.status = 'overdue' AND i.balance_due > 0
              AND NOT EXISTS (SELECT 1 FROM debts d WHERE d.invoice_id = i.id AND d.business_id = i.business_id)
        ");
        $stmt->execute([$this->biz_id]);

        foreach ($stmt->fetchAll() as $inv) {
            // Create debt
            $this->db->prepare("
                INSERT INTO debts (business_id, customer_id, user_id, type, source, invoice_id, description, counterparty_name, counterparty_phone, total_amount, amount_settled, balance, due_date, status)
                VALUES (?, ?, ?, 'receivable', 'invoice', ?, ?, ?, ?, ?, 0, ?, ?, 'active')
            ")->execute([
                $this->biz_id, $inv['customer_id'], $inv['user_id'],
                $inv['id'],
                'Overdue invoice ' . $inv['doc_number'],
                $inv['customer_name'], $inv['customer_phone'],
                $inv['balance_due'], $inv['balance_due'], $inv['due_date']
            ]);
        }
    }
}
