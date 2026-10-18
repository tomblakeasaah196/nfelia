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
        $stmtCust = $this->db->prepare("SELECT * FROM customers WHERE id = ?");
        $stmtCust->execute([$invoice['customer_id']]);
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

        $user = auth_user();

        $this->db->beginTransaction();
        try {
            // Update status
            $this->db->prepare("UPDATE invoices SET status = 'sent' WHERE id = ?")->execute([$id]);

            // Deduct stock for non-service items
            $stmtItems = $this->db->prepare("
                SELECT ii.*, p.is_service, p.stock_qty
                FROM invoice_items ii
                LEFT JOIN products p ON p.id = ii.product_id
                WHERE ii.invoice_id = ?
            ");
            $stmtItems->execute([$id]);

            foreach ($stmtItems->fetchAll() as $item) {
                if (!$item['product_id'] || $item['is_service']) continue;

                $qty_before = (float)$item['stock_qty'];
                $deduct     = (float)$item['quantity'];
                $qty_after  = $qty_before - $deduct;

                // Update product stock
                $this->db->prepare("UPDATE products SET stock_qty = ? WHERE id = ? AND business_id = ?")
                          ->execute([$qty_after, $item['product_id'], $this->biz_id]);

                // Log stock movement
                $this->db->prepare("
                    INSERT INTO stock_movements (business_id, product_id, user_id, type, quantity, stock_before, stock_after, unit_cost, reference_type, reference_id, note, movement_date)
                    VALUES (?, ?, ?, 'sale', ?, ?, ?, ?, 'invoice', ?, ?, CURDATE())
                ")->execute([
                    $this->biz_id, $item['product_id'], $user['id'],
                    $deduct, $qty_before, $qty_after,
                    $item['unit_price'], $id,
                    'Stock deducted for invoice ' . $invoice['doc_number']
                ]);
            }

            // Update customer's total_invoiced
            $this->db->prepare("
                UPDATE customers SET
                    total_invoiced = total_invoiced + ?,
                    outstanding_balance = outstanding_balance + ?
                WHERE id = ?
            ")->execute([$invoice['total'], $invoice['total'], $invoice['customer_id']]);

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

        // Validate
        $valid_methods = ['cash','bank_transfer','pos','mobile_money','cheque','other'];
        if ($amount <= 0) {
            $err = 'Payment amount must be greater than zero.';
            if (is_ajax()) { json_response(['error' => $err], 422); }
            flash('error', $err); redirect('invoices/view/' . $id); return;
        }
        if ($amount > (float)$invoice['balance_due']) {
            $err = 'Payment exceeds the remaining balance of ' . money((float)$invoice['balance_due'], $invoice['currency_symbol'] ?? '₦') . '.';
            if (is_ajax()) { json_response(['error' => $err], 422); }
            flash('error', $err); redirect('invoices/view/' . $id); return;
        }
        if (!in_array($method, $valid_methods)) $method = 'cash';

        $this->db->beginTransaction();
        try {
            // Read the balance again with the row held: two taps of Record
            // at once must not both pass the check above and overpay.
            $stmtLock = $this->db->prepare("SELECT amount_paid, total, balance_due FROM invoices WHERE id = ? AND business_id = ? FOR UPDATE");
            $stmtLock->execute([$id, $this->biz_id]);
            $fresh = $stmtLock->fetch();
            if (!$fresh || $amount > (float)$fresh['balance_due'] + 0.001) {
                $this->db->rollBack();
                $err = 'This payment was already recorded, or is more than what is left to pay. Reload the invoice.';
                if (is_ajax()) { json_response(['error' => $err], 409); }
                flash('error', $err); redirect('invoices/view/' . $id); return;
            }
            $invoice = array_merge($invoice, $fresh);

            // Insert payment
            $stmtPay = $this->db->prepare("
                INSERT INTO payments (business_id, invoice_id, user_id, amount, payment_date, method, reference, note)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtPay->execute([
                $this->biz_id, $id, $user['id'],
                $amount, $payment_date, $method,
                $reference ?: null, $note ?: null
            ]);
            $payment_id = (int)$this->db->lastInsertId();

            // Update invoice totals
            $new_paid    = (float)$invoice['amount_paid'] + $amount;
            $new_balance = (float)$invoice['total'] - $new_paid;
            $new_status  = $new_balance <= 0 ? 'paid' : 'partially_paid';

            $this->db->prepare("
                UPDATE invoices SET amount_paid = ?, balance_due = ?, status = ? WHERE id = ?
            ")->execute([$new_paid, max(0, $new_balance), $new_status, $id]);

            // Update customer financials
            $this->db->prepare("
                UPDATE customers SET
                    total_received = total_received + ?,
                    outstanding_balance = GREATEST(0, outstanding_balance - ?)
                WHERE id = ?
            ")->execute([$amount, $amount, $invoice['customer_id']]);

            // If overdue, update/clear the debt record
            if ($invoice['status'] === 'overdue' || $new_balance <= 0) {
                $this->update_debt_on_payment($id, $amount, $new_balance);
            }

            $this->db->commit();

            // Owners and admins hear about it, except whoever recorded it
            // (app/helpers/notify.php).
            $stmtWho = $this->db->prepare("SELECT full_name FROM customers WHERE id = ?");
            $stmtWho->execute([$invoice['customer_id']]);
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

        } catch (\Throwable $e) {
            $this->db->rollBack();
            if (is_ajax()) { json_response(['error' => 'Failed: ' . $e->getMessage()], 500); }
            flash('error', 'Payment failed: ' . $e->getMessage());
            redirect('invoices/view/' . $id);
        }
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

        $user = auth_user();

        $this->db->beginTransaction();
        try {
            // Restore stock if invoice was sent (stock was deducted)
            if (in_array($invoice['status'], ['sent', 'partially_paid', 'paid', 'overdue'])) {
                $stmtItems = $this->db->prepare("
                    SELECT ii.*, p.is_service, p.stock_qty
                    FROM invoice_items ii
                    LEFT JOIN products p ON p.id = ii.product_id
                    WHERE ii.invoice_id = ?
                ");
                $stmtItems->execute([$id]);

                foreach ($stmtItems->fetchAll() as $item) {
                    if (!$item['product_id'] || $item['is_service']) continue;

                    $qty_before = (float)$item['stock_qty'];
                    $restore    = (float)$item['quantity'];
                    $qty_after  = $qty_before + $restore;

                    $this->db->prepare("UPDATE products SET stock_qty = ? WHERE id = ? AND business_id = ?")
                              ->execute([$qty_after, $item['product_id'], $this->biz_id]);

                    $this->db->prepare("
                        INSERT INTO stock_movements (business_id, product_id, user_id, type, quantity, stock_before, stock_after, unit_cost, reference_type, reference_id, note, movement_date)
                        VALUES (?, ?, ?, 'return', ?, ?, ?, ?, 'invoice', ?, ?, CURDATE())
                    ")->execute([
                        $this->biz_id, $item['product_id'], $user['id'],
                        $restore, $qty_before, $qty_after,
                        $item['unit_price'], $id,
                        'Stock restored — invoice ' . $invoice['doc_number'] . ' cancelled'
                    ]);
                }

                // Reverse customer financials
                $this->db->prepare("
                    UPDATE customers SET
                        total_invoiced = GREATEST(0, total_invoiced - ?),
                        total_received = GREATEST(0, total_received - ?),
                        outstanding_balance = GREATEST(0, outstanding_balance - ?)
                    WHERE id = ?
                ")->execute([
                    $invoice['total'],
                    $invoice['amount_paid'],
                    $invoice['balance_due'],
                    $invoice['customer_id']
                ]);
            }

            // Mark cancelled
            $this->db->prepare("UPDATE invoices SET status = 'cancelled' WHERE id = ?")->execute([$id]);

            // Clear any debt records
            $this->db->prepare("
                UPDATE debts SET status = 'written_off', note = CONCAT(COALESCE(note,''), ' [Invoice cancelled]')
                WHERE invoice_id = ? AND business_id = ? AND status IN ('active','settling')
            ")->execute([$id, $this->biz_id]);

            $this->db->commit();
            flash('success', $invoice['doc_number'] . ' cancelled. Stock restored.');
            redirect('invoices/view/' . $id);

        } catch (\Throwable $e) {
            $this->db->rollBack();
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

        $stmtCust = $this->db->prepare("SELECT * FROM customers WHERE id = ?");
        $stmtCust->execute([$invoice['customer_id']]);
        $customer = $stmtCust->fetch();

        $business = auth_business();
        $currency = $business['currency_symbol'] ?? '₦';

        $wa_number = $customer['whatsapp'] ?: $customer['phone'] ?: '';
        $wa_number = preg_replace('/[^0-9]/', '', $wa_number);
        if (str_starts_with($wa_number, '0')) $wa_number = '234' . substr($wa_number, 1);

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
        // A job plan's delivery step follows the invoice's new date.
        $this->db->prepare("UPDATE tasks SET due_date = ? WHERE invoice_id = ? AND business_id = ? AND step_key = 'deliver' AND done_at IS NULL AND deleted_at IS NULL")
                 ->execute([$delivery['deliver_by'], $invoice['id'], $this->biz_id]);

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

        $undo = !empty($_POST['undo']);
        $this->db->prepare("UPDATE invoices SET delivered_at = " . ($undo ? 'NULL' : 'NOW()') . " WHERE id = ? AND business_id = ?")
                 ->execute([$invoice['id'], $this->biz_id]);
        $this->db->prepare("UPDATE tasks SET done_at = " . ($undo ? 'NULL' : 'COALESCE(done_at, NOW())') . " WHERE invoice_id = ? AND business_id = ? AND step_key = 'deliver' AND deleted_at IS NULL")
                 ->execute([$invoice['id'], $this->biz_id]);

        // Keep the job plan's own done state in step with its steps.
        $stmtP = $this->db->prepare("SELECT DISTINCT parent_id FROM tasks WHERE invoice_id = ? AND business_id = ? AND step_key = 'deliver' AND deleted_at IS NULL");
        $stmtP->execute([$invoice['id'], $this->biz_id]);
        foreach ($stmtP->fetchAll(PDO::FETCH_COLUMN) as $parent_id) {
            $left = $this->db->prepare("SELECT COUNT(*) FROM tasks WHERE parent_id = ? AND done_at IS NULL AND deleted_at IS NULL");
            $left->execute([$parent_id]);
            $this->db->prepare("UPDATE tasks SET done_at = " . ((int)$left->fetchColumn() === 0 ? 'COALESCE(done_at, NOW())' : 'NULL') . " WHERE id = ?")
                     ->execute([$parent_id]);
        }

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
        if ($customer_id <= 0) $errors[] = 'Please select a client.';
        if (empty($item_descs) || count(array_filter($item_descs, 'trim')) === 0) {
            $errors[] = 'At least one line item is required.';
        }
        // When the customer gets it: required on our side (Tasks & Calendar).
        [$delivery, $delivery_error] = planner_delivery_from_post($_POST, $id ? $this->find_invoice($id) : null);
        if ($delivery_error) $errors[] = $delivery_error;

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            $invoice   = $_POST;
            $items     = [];
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

            $line_total = $qty * $price;
            $disc_amt   = round($line_total * $disc_pct / 100, 2);
            $line_sub   = $line_total - $disc_amt;

            $subtotal += $line_sub;
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

        $subtotal_after_discount = $subtotal - $discount_amount;
        $tax_amount = round($subtotal_after_discount * $tax_rate / 100, 2);
        $total      = $subtotal_after_discount + $tax_amount;

        $this->db->beginTransaction();
        try {
            if ($id) {
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
                // A job plan's delivery step follows the invoice's date.
                $this->db->prepare("UPDATE tasks SET due_date = ? WHERE invoice_id = ? AND business_id = ? AND step_key = 'deliver' AND done_at IS NULL AND deleted_at IS NULL")
                         ->execute([$delivery['deliver_by'], $id, $this->biz_id]);

                $this->db->prepare("DELETE FROM invoice_items WHERE invoice_id = ?")->execute([$id]);
            } else {
                $this->db->prepare("CALL generate_doc_number(?, 'INV', @inv_num)")->execute([$this->biz_id]);
                $doc_number = $this->db->query("SELECT @inv_num")->fetchColumn();
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

            $this->db->commit();
            flash('success', 'Invoice saved.');
            redirect('invoices/view/' . $id);

        } catch (\Throwable $e) {
            $this->db->rollBack();
            flash('error', 'Save failed: ' . $e->getMessage());
            redirect('invoices');
        }
    }

    /* ══════════════════════════════════════════
     *  PRIVATE HELPERS
     * ══════════════════════════════════════════ */

    private function find_invoice($id): ?array {
        if (!$id) return null;
        $stmt = $this->db->prepare("SELECT * FROM invoices WHERE id = ? AND business_id = ?");
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
        // Find overdue invoices without a debt record yet
        $stmt = $this->db->prepare("
            SELECT i.id, i.doc_number, i.customer_id, i.balance_due, i.due_date, i.user_id,
                   c.full_name as customer_name, c.phone as customer_phone
            FROM invoices i
            LEFT JOIN customers c ON c.id = i.customer_id
            WHERE i.business_id = ? AND i.status = 'overdue'
              AND NOT EXISTS (SELECT 1 FROM debts d WHERE d.invoice_id = i.id AND d.business_id = i.business_id AND d.status IN ('active','settling','written_off'))
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

    /**
     * Update debt record when a payment is made on an overdue invoice.
     */
    private function update_debt_on_payment(int $invoice_id, float $amount, float $remaining_balance): void {
        $stmtDebt = $this->db->prepare("
            SELECT id, amount_settled FROM debts
            WHERE invoice_id = ? AND business_id = ? AND status IN ('active','settling')
            LIMIT 1
        ");
        $stmtDebt->execute([$invoice_id, $this->biz_id]);
        $debt = $stmtDebt->fetch();

        if ($debt) {
            $new_settled = (float)$debt['amount_settled'] + $amount;
            $new_status  = $remaining_balance <= 0 ? 'cleared' : 'settling';

            $this->db->prepare("
                UPDATE debts SET amount_settled = ?, balance = ?, status = ? WHERE id = ?
            ")->execute([$new_settled, max(0, $remaining_balance), $new_status, $debt['id']]);
        }
    }
}