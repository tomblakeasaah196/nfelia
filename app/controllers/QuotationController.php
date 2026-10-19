<?php
/**
 * Nfelia — Quotation Controller ("Request for Engagement")
 * Full CRUD, line-item builder, status workflow, convert to invoice,
 * public shareable view, WhatsApp share, PDF-ready rendering.
 */
class QuotationController {

    private $db;
    private $biz_id;

    public function __construct() {
        if (!is_logged_in()) {
            redirect('login');
        }
        $this->db     = get_db();
        $this->biz_id = auth_business_id();
    }

    /* ──────────────────────────────────────────
     *  LIST — all quotations with filters
     * ────────────────────────────────────────── */
    public function index() {
        $user     = auth_user();
        $business = auth_business();

        // Filters
        $search  = trim($_GET['q'] ?? '');
        $status  = trim($_GET['status'] ?? '');
        $sort    = trim($_GET['sort'] ?? 'newest');
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $per_page = 20;
        $offset   = ($page - 1) * $per_page;

        $where  = ["q.business_id = ?"];
        $params = [$this->biz_id];

        if ($search !== '') {
            $where[]  = "(q.doc_number LIKE ? OR c.full_name LIKE ?)";
            $like     = "%{$search}%";
            $params[] = $like;
            $params[] = $like;
        }
        if ($status === 'waiting') {
            $where[] = "q.status IN ('sent','in_negotiation','pending_signature')";
        } elseif ($status !== '' && in_array($status, ['draft','sent','in_negotiation','pending_signature','accepted','declined','expired','converted'])) {
            $where[]  = "q.status = ?";
            $params[] = $status;
        } else {
            $status = '';
        }

        $where_sql = implode(' AND ', $where);
        $order = match($sort) {
            'oldest'     => 'q.created_at ASC',
            'total_asc'  => 'q.total ASC',
            'total_desc' => 'q.total DESC',
            default      => 'q.created_at DESC',
        };

        // Count
        $stmtC = $this->db->prepare("SELECT COUNT(*) FROM quotations q LEFT JOIN customers c ON c.id = q.customer_id WHERE {$where_sql}");
        $stmtC->execute($params);
        $total_items = (int)$stmtC->fetchColumn();
        $total_pages = max(1, ceil($total_items / $per_page));

        // Fetch
        $stmtQ = $this->db->prepare("
            SELECT q.*, c.full_name as customer_name, c.phone as customer_phone
            FROM quotations q
            LEFT JOIN customers c ON c.id = q.customer_id
            WHERE {$where_sql}
            ORDER BY {$order}
            LIMIT {$per_page} OFFSET {$offset}
        ");
        $stmtQ->execute($params);
        $quotations = $stmtQ->fetchAll();

        // Summary stats
        $stmtS = $this->db->prepare("
            SELECT
                COUNT(*)                                                      AS total_quotes,
                SUM(total)                                                    AS total_value,
                SUM(CASE WHEN status = 'accepted' THEN total ELSE 0 END)     AS accepted_value,
                SUM(CASE WHEN status IN ('draft','sent') THEN 1 ELSE 0 END)  AS pending_count,
                SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END)         AS accepted_count,
                SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END)          AS expired_count,
                SUM(CASE WHEN status IN ('sent','in_negotiation','pending_signature') THEN 1 ELSE 0 END)                      AS waiting_count,
                COALESCE(SUM(CASE WHEN status IN ('sent','in_negotiation','pending_signature') THEN total END), 0)            AS waiting_value,
                SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END)            AS draft_count,
                SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END)        AS converted_count
            FROM quotations WHERE business_id = ?
        ");
        $stmtS->execute([$this->biz_id]);
        $summary = $stmtS->fetch();

        $title     = "Quotations";
        // Quote requests from the shop still waiting on an answer.
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM quote_requests WHERE business_id = ? AND status IN ('new','reviewed')");
        $stmt->execute([$this->biz_id]);
        $open_requests = (int)$stmt->fetchColumn();

        $view_file = BASE_PATH . '/views/quotations/index.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ──────────────────────────────────────────
     *  CREATE — quotation builder
     * ────────────────────────────────────────── */
    public function create() {
        $user     = auth_user();
        $business = auth_business();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            return $this->save_quotation(null);
        }

        // GET — load form dependencies. ?customer=12 starts it for that
        // customer (a quote request's "Write a quote").
        $quotation = [];
        $customer_q = (int)($_GET['customer'] ?? 0);
        if ($customer_q) {
            $stmt = $this->db->prepare("SELECT id FROM customers WHERE id = ? AND business_id = ? AND deleted_at IS NULL");
            $stmt->execute([$customer_q, $this->biz_id]);
            if ($stmt->fetchColumn()) $quotation['customer_id'] = $customer_q;
        }
        $items     = [];
        $editing   = false;
        $customers = $this->get_customers();
        $banks     = $this->get_bank_accounts();

        $title     = "New Quotation";
        $view_file = BASE_PATH . '/views/quotations/form.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ──────────────────────────────────────────
     *  QUOTE REQUESTS — from the shop's "Ask for a quote"
     *  /quotations/requests (the list) and /quotations/requests/{id}
     *  (one, with what to do next). Their notification links here.
     * ────────────────────────────────────────── */
    public function requests($id = null) {
        $business = auth_business();
        $request = null;
        $customer_id = null;
        if ($id !== null) {
            $stmt = $this->db->prepare("SELECT * FROM quote_requests WHERE id = ? AND business_id = ?");
            $stmt->execute([(int)$id, $this->biz_id]);
            $request = $stmt->fetch();
            if (!$request) { not_found(); return; }
            // Opened: no longer new.
            if ($request['status'] === 'new') {
                $this->db->prepare("UPDATE quote_requests SET status = 'reviewed' WHERE id = ? AND status = 'new'")->execute([(int)$id]);
            }
            // The customer the request made or matched, for a quote ready addressed to them.
            $stmt = $this->db->prepare("SELECT id FROM customers WHERE business_id = ? AND phone = ? AND deleted_at IS NULL LIMIT 1");
            $stmt->execute([$this->biz_id, $request['phone']]);
            $customer_id = (int)$stmt->fetchColumn() ?: null;
        }

        $show = ($_GET['show'] ?? '') === 'done' ? 'done' : 'open';
        $stmt = $this->db->prepare("
            SELECT id, name, phone, message, status, created_at FROM quote_requests
            WHERE business_id = ? AND status " . ($show === 'done' ? "IN ('converted','archived')" : "IN ('new','reviewed')") . "
            ORDER BY status = 'new' DESC, id DESC LIMIT 100");
        $stmt->execute([$this->biz_id]);
        $requests = $stmt->fetchAll();

        $title     = $request ? 'Quote request from ' . $request['name'] : 'Quote requests';
        $view_file = BASE_PATH . '/views/quotations/requests.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /** Mark a quote request dealt with (a quote went out) or put it away. */
    public function request_status($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('quotations/requests'); }
        csrf_verify();
        $status = in_array($_POST['status'] ?? '', ['reviewed', 'converted', 'archived'], true) ? $_POST['status'] : 'reviewed';
        $this->db->prepare("UPDATE quote_requests SET status = ? WHERE id = ? AND business_id = ?")->execute([$status, (int)$id, $this->biz_id]);
        flash('success', $status === 'reviewed' ? 'Moved back to open requests.' : ($status === 'converted' ? 'Marked as quoted.' : 'Put away.'));
        redirect('quotations/requests' . ($status === 'reviewed' ? '/' . (int)$id : ''));
    }

    /* ──────────────────────────────────────────
     *  EDIT — update existing quotation
     * ────────────────────────────────────────── */
    public function edit($id = null) {
        $user     = auth_user();
        $business = auth_business();

        $quotation = $this->find_quotation($id);
        if (!$quotation) { not_found(); return; }

        // Only draft, sent, or in_negotiation can be edited
        if (!in_array($quotation['status'], ['draft', 'sent', 'in_negotiation'])) {
            flash('error', 'This quotation is locked and can no longer be edited.');
            redirect('quotations/view/' . $id);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            return $this->save_quotation($id);
        }

        // Load existing items
        $stmtItems = $this->db->prepare("
            SELECT qi.*, p.name as product_name
            FROM quotation_items qi
            LEFT JOIN products p ON p.id = qi.product_id
            WHERE qi.quotation_id = ?
            ORDER BY qi.sort_order ASC
        ");
        $stmtItems->execute([$id]);
        $items = $stmtItems->fetchAll();

        $editing   = true;
        $customers = $this->get_customers();
        $banks     = $this->get_bank_accounts();

        $title     = "Edit — " . e($quotation['doc_number']);
        $view_file = BASE_PATH . '/views/quotations/form.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ──────────────────────────────────────────
     *  VIEW — quotation detail + actions
     * ────────────────────────────────────────── */
    public function view($id = null) {
        $user     = auth_user();
        $business = auth_business();

        $quotation = $this->find_quotation($id);
        if (!$quotation) { not_found(); return; }

        // Customer
        $stmtCust = $this->db->prepare("SELECT * FROM customers WHERE id = ? AND business_id = ?");
        $stmtCust->execute([$quotation['customer_id'], $this->biz_id]);
        $customer = $stmtCust->fetch();

        // Items
        $stmtItems = $this->db->prepare("
            SELECT qi.*, p.name as product_name, p.sku as product_sku
            FROM quotation_items qi
            LEFT JOIN products p ON p.id = qi.product_id
            WHERE qi.quotation_id = ?
            ORDER BY qi.sort_order ASC
        ");
        $stmtItems->execute([$id]);
        $items = $stmtItems->fetchAll();

        // Bank account
        $bank = null;
        if ($quotation['bank_account_id']) {
            $stmtBank = $this->db->prepare("SELECT * FROM bank_accounts WHERE id = ? AND business_id = ?");
            $stmtBank->execute([$quotation['bank_account_id'], $this->biz_id]);
            $bank = $stmtBank->fetch();
        }

        // Public URL
        $public_url = APP_URL . '/doc/' . $quotation['token'];

        // Fetch negotiation history and comments
        $stmtAct = $this->db->prepare("
            SELECT qa.*, u.full_name 
            FROM quotation_activities qa
            LEFT JOIN users u ON u.id = qa.user_id
            WHERE qa.quotation_id = ? 
            ORDER BY qa.created_at ASC
        ");
        $stmtAct->execute([$id]);
        $activities = $stmtAct->fetchAll();

        // The order this quote became, if it did
        $stmtOrd = $this->db->prepare("SELECT id, order_number FROM orders WHERE quotation_id = ? AND business_id = ? ORDER BY id DESC LIMIT 1");
        $stmtOrd->execute([$id, $this->biz_id]);
        $quote_order = $stmtOrd->fetch() ?: null;

        $title     = e($quotation['doc_number']);
        $view_file = BASE_PATH . '/views/quotations/view.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ──────────────────────────────────────────
     *  DELETE — remove quotation
     * ────────────────────────────────────────── */
    public function delete($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('quotations'); return; }
        csrf_verify();

        $quotation = $this->find_quotation($id);
        if (!$quotation) { not_found(); return; }
        // A quote that made an order stays, as that order's record
        $stmtOrd = $this->db->prepare("SELECT order_number FROM orders WHERE quotation_id = ? AND business_id = ? LIMIT 1");
        $stmtOrd->execute([(int)$id, $this->biz_id]);
        if ($order_number = $stmtOrd->fetchColumn()) {
            flash('error', $quotation['doc_number'] . ' made order ' . $order_number . ', so it stays with that order.');
            redirect('quotations/view/' . (int)$id);
            return;
        }

        // Delete items first, then quotation
        $this->db->prepare("DELETE FROM quotation_items WHERE quotation_id = ?")->execute([$id]);
        $this->db->prepare("DELETE FROM quotations WHERE id = ? AND business_id = ?")->execute([$id, $this->biz_id]);

        flash('success', $quotation['doc_number'] . ' deleted.');
        redirect('quotations');
    }

    /* ──────────────────────────────────────────
     *  SEND — mark as sent
     * ────────────────────────────────────────── */
    public function send($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('quotations'); return; }
        csrf_verify();

        $quotation = $this->find_quotation($id);
        if (!$quotation || $quotation['status'] !== 'draft') {
            flash('error', 'Only draft quotations can be sent.');
            redirect('quotations');
            return;
        }

        $this->db->prepare("UPDATE quotations SET status = 'sent' WHERE id = ? AND business_id = ?")
                  ->execute([$id, $this->biz_id]);

        flash('success', $quotation['doc_number'] . ' marked as sent.');
        redirect('quotations/view/' . $id);
    }

    /* ──────────────────────────────────────────
 * UPDATE STATUS — Enforced State Machine
 * ────────────────────────────────────────── */
public function update_status($id = null) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('quotations'); return; }
    csrf_verify();

    $quotation = $this->find_quotation($id);
    if (!$quotation) { not_found(); return; }

    $new_status = trim($_POST['status'] ?? '');
    
    // The Strict State Machine Rules
    $allowed = [
        'draft'             => ['sent'],
        'sent'              => ['accepted', 'declined', 'expired', 'in_negotiation'],
        'in_negotiation'    => ['pending_signature', 'accepted', 'declined'],
        'pending_signature' => ['accepted', 'declined'],
    ];

    if (!isset($allowed[$quotation['status']]) || !in_array($new_status, $allowed[$quotation['status']])) {
        flash('error', 'Invalid status transition from ' . strtoupper($quotation['status']) . ' to ' . strtoupper($new_status));
        redirect('quotations/view/' . $id);
        return;
    }

    $this->db->beginTransaction();
    try {
        $this->db->prepare("UPDATE quotations SET status = ? WHERE id = ? AND business_id = ?")
                 ->execute([$new_status, $id, $this->biz_id]);

        // Log the internal team action to the audit trail
        $this->db->prepare("
            INSERT INTO quotation_activities 
            (business_id, quotation_id, actor_type, user_id, action, old_status, new_status, message) 
            VALUES (?, ?, 'business', ?, 'status_changed', ?, ?, 'Status manually updated by team.')
        ")->execute([
            $this->biz_id, $id, auth_user()['id'], 
            $quotation['status'], $new_status
        ]);

        $this->db->commit();
        flash('success', $quotation['doc_number'] . " updated to " . str_replace('_', ' ', $new_status) . ".");

    } catch (\Throwable $e) {
        $this->db->rollBack();
        flash('error', 'Failed to update status.');
        redirect('quotations/view/' . $id);
    }

    // Accepted: it becomes its order straight away (docs/ORDERS_HUB.md)
    if ($new_status === 'accepted') {
        try {
            $order = order_from_quote($this->db, $this->biz_id, (int)$id, (int)auth_user()['id']);
            flash('success', 'Accepted. Order ' . $order['order_number'] . ' is made, with its invoice.');
            redirect('orders/view/' . $order['id']);
        } catch (\DomainException $e) {
            flash('error', 'Accepted, but the order couldn\'t be made: ' . $e->getMessage());
        }
    }

    redirect('quotations/view/' . $id);
}

    /* ──────────────────────────────────────────
     * POST INTERNAL COMMENT (Negotiation)
     * ────────────────────────────────────────── */
    public function add_comment($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('quotations'); return; }
        csrf_verify();

        $quotation = $this->find_quotation($id);
        if (!$quotation) { not_found(); return; }

        $message = trim($_POST['message'] ?? '');
        if ($message === '') {
            flash('error', 'Message cannot be empty.');
            redirect('quotations/view/' . $id);
            return;
        }

        try {
            $this->db->prepare("
                INSERT INTO quotation_activities 
                (business_id, quotation_id, actor_type, user_id, action, message, old_status, new_status) 
                VALUES (?, ?, 'business', ?, 'comment_added', ?, ?, ?)
            ")->execute([
                $this->biz_id, 
                $id, 
                auth_user()['id'], 
                $message, 
                $quotation['status'], 
                $quotation['status'] // Status doesn't change just by replying
            ]);

            flash('success', 'Reply sent to client.');
        } catch (\Throwable $e) {
            flash('error', 'Failed to send message.');
        }

        redirect('quotations/view/' . $id);
    }

    /* ──────────────────────────────────────────
     *  INTERNAL AUTHORIZATION (Owner Signature)
     * ────────────────────────────────────────── */
    public function sign_internal($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('quotations'); return; }
        csrf_verify();

        $quotation = $this->find_quotation($id);
        if (!$quotation) { not_found(); return; }

        $signature_data = $_POST['owner_signature_data'] ?? '';
        $signed_by      = trim($_POST['owner_signed_by_name'] ?? '');

        if (empty($signature_data) || empty($signed_by)) {
            flash('error', 'Signature and name are required.');
            redirect('quotations/view/' . $id);
            return;
        }

        try {
            $this->db->prepare("
                UPDATE quotations 
                SET owner_signature_data = ?, owner_signed_by_name = ?, owner_signed_at = NOW()
                WHERE id = ? AND business_id = ?
            ")->execute([$signature_data, $signed_by, $id, $this->biz_id]);

            // Log the activity to the audit trail
            $this->db->prepare("
                INSERT INTO quotation_activities 
                (business_id, quotation_id, actor_type, user_id, action, message, old_status, new_status) 
                VALUES (?, ?, 'business', ?, 'quote_authorized', 'Internally authorized and signed by business owner.', ?, ?)
            ")->execute([
                $this->biz_id, $id, auth_user()['id'], 
                $quotation['status'], $quotation['status']
            ]);

            flash('success', 'Quotation officially authorized.');
        } catch (\Throwable $e) {
            flash('error', 'Failed to save signature.');
        }

        redirect('quotations/view/' . $id);
    }
    
    /* ──────────────────────────────────────────
     *  CONVERT TO INVOICE
     * ────────────────────────────────────────── */
    public function convert($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('quotations'); return; }
        csrf_verify();

        $quotation = $this->find_quotation($id);
        if (!$quotation) { not_found(); return; }

        // A quote becomes an order, never an invoice on its own: the order
        // makes its invoice, and every sale is found in Orders (docs/ORDERS_HUB.md)
        try {
            $order = order_from_quote($this->db, $this->biz_id, (int)$id, (int)auth_user()['id']);
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
            redirect('quotations/view/' . $id);
        }
        flash('success', $quotation['doc_number'] . ' is now order ' . $order['order_number'] . ', with its invoice.');
        redirect('orders/view/' . $order['id']);
    }

    /* ──────────────────────────────────────────
     *  AJAX — search products for line items
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

        $stmt = $this->db->prepare("
            INSERT INTO customers (business_id, full_name, phone, email)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$this->biz_id, $name, $phone ?: null, $email ?: null]);
        $cid = (int)$this->db->lastInsertId();

        json_response([
            'id'        => $cid,
            'full_name' => $name,
            'phone'     => $phone,
        ]);
    }

    /* ══════════════════════════════════════════
     *  PRIVATE: save / update quotation
     * ══════════════════════════════════════════ */
    private function save_quotation(?int $id): void {
        $user     = auth_user();
        $business = auth_business();

        $customer_id    = (int)($_POST['customer_id'] ?? 0);
        $bank_id        = (int)($_POST['bank_account_id'] ?? 0) ?: null;
        $issue_date     = trim($_POST['issue_date'] ?? date('Y-m-d'));
        $expiry_date    = trim($_POST['expiry_date'] ?? '') ?: null;
        $tax_rate        = max(0, min(100, (float)($_POST['tax_rate'] ?? 0)));   // M18
        $notes           = trim($_POST['notes'] ?? '');
        $discount_input  = (float)($_POST['discount_amount'] ?? 0);

        // Line items from POST arrays
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

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            // Keep what was typed, lines included; an edit keeps its own id (D1)
            $editing   = $id !== null;
            $quotation = array_merge($editing ? ($this->find_quotation($id) ?: []) : [], $_POST);
            $items     = posted_lines($_POST);
            $customers = $this->get_customers();
            $banks     = $this->get_bank_accounts();
            $title     = $editing ? "Edit Quotation" : "New Quotation";
            $view_file = BASE_PATH . '/views/quotations/form.php';
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

            // Each line rounded to kobo before adding up (M20)
            $line_total = money2($qty * $price);
            $disc_amt   = money2($line_total * $disc_pct / 100);
            $line_sub   = money2($line_total - $disc_amt);

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

        // NOW clamp the discount so it cannot exceed the calculated subtotal
        $subtotal        = money2($subtotal);
        $discount_amount = money2(max(0, min($discount_input, $subtotal)));
        $subtotal_after_discount = money2(max(0, $subtotal - $discount_amount));
        
        $tax_amount = money2($subtotal_after_discount * $tax_rate / 100);
        $total      = money2($subtotal_after_discount + $tax_amount);

        $this->db->beginTransaction();
        try {
            if ($id) {
                // Update existing
                $this->db->prepare("
                    UPDATE quotations SET
                        customer_id = ?, bank_account_id = ?, issue_date = ?, expiry_date = ?,
                        subtotal = ?, discount_amount = ?, tax_rate = ?, tax_amount = ?, total = ?,
                        notes = ?
                    WHERE id = ? AND business_id = ?
                ")->execute([
                    $customer_id, $bank_id, $issue_date, $expiry_date,
                    $subtotal, $discount_amount, $tax_rate, $tax_amount, $total,
                    $notes ?: null,
                    $id, $this->biz_id
                ]);

                // Delete old items, re-insert
                $this->db->prepare("DELETE FROM quotation_items WHERE quotation_id = ?")->execute([$id]);

                // NEW: Log the revision if it was actively being negotiated
                $old_status = $_POST['current_status'] ?? 'draft'; 
                if (in_array($old_status, ['sent', 'in_negotiation'])) {
                    $this->db->prepare("
                        INSERT INTO quotation_activities 
                        (business_id, quotation_id, actor_type, user_id, action, message, old_status, new_status) 
                        VALUES (?, ?, 'business', ?, 'quote_revised', 'Quotation pricing or terms were updated.', ?, ?)
                    ")->execute([
                        $this->biz_id, $id, auth_user()['id'], 
                        $old_status, $old_status
                    ]);
                }
            } else {
                // Generate doc number
                $doc_number = generate_doc_number($this->db, $this->biz_id, 'QUO');
                $token = generate_token();

                $this->db->prepare("
                    INSERT INTO quotations (business_id, customer_id, bank_account_id, user_id, doc_number, status, issue_date, expiry_date, subtotal, discount_amount, tax_rate, tax_amount, total, notes, token)
                    VALUES (?, ?, ?, ?, ?, 'draft', ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $this->biz_id, $customer_id, $bank_id, $user['id'],
                    $doc_number, $issue_date, $expiry_date,
                    $subtotal, $discount_amount, $tax_rate, $tax_amount, $total,
                    $notes ?: null, $token
                ]);
                $id = (int)$this->db->lastInsertId();
            }

            // Insert line items
            $stmtItem = $this->db->prepare("
                INSERT INTO quotation_items (quotation_id, product_id, description, quantity, unit_price, discount_percent, discount_amount, subtotal, sort_order)
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
            flash('success', 'Quotation saved.');
            redirect('quotations/view/' . $id);

        } catch (\Throwable $e) {
            $this->db->rollBack();
            flash('error', 'Save failed: ' . $e->getMessage());
            redirect('quotations');
        }
    }

    
    /* ══════════════════════════════════════════
     *  PRIVATE HELPERS
     * ══════════════════════════════════════════ */

    private function find_quotation($id): ?array {
        if (!$id) return null;
        $stmt = $this->db->prepare("SELECT * FROM quotations WHERE id = ? AND business_id = ?");
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
        SELECT * FROM bank_accounts 
        WHERE business_id = ? AND deleted_at IS NULL AND is_active = 1 
        ORDER BY is_default DESC, sort_order ASC
    ");
    $stmt->execute([$this->biz_id]);
    return $stmt->fetchAll();
}
}