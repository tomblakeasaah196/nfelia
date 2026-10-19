<?php
/**
 * Nfelia — Delivery & Dispatch Controller
 * Full lifecycle: create → dispatch → transit → deliver → sign-off
 * Supports invoice-linked and standalone deliveries.
 * E-signature via public sign-off page.
 */
class DeliveryController {

    private $db;
    private $biz_id;

    public const PROVIDERS = [
        'uber'           => 'Uber',
        'bolt'           => 'Bolt',
        'kwik'           => 'Kwik Delivery',
        'gig'            => 'GIG Logistics',
        'dhl'            => 'DHL',
        'private_rider'  => 'Private Rider',
        'self'           => 'Self Delivery',
        'other'          => 'Other',
    ];

    public const STATUSES = [
        'pending_dispatch' => ['label' => 'Pending Dispatch', 'color' => 'bg-gray-100 text-gray-700',    'icon' => 'clock'],
        'dispatched'       => ['label' => 'Dispatched',       'color' => 'bg-blue-100 text-blue-700',    'icon' => 'truck'],
        'in_transit'       => ['label' => 'In Transit',       'color' => 'bg-amber-100 text-amber-700',  'icon' => 'navigation'],
        'delivered'        => ['label' => 'Delivered',         'color' => 'bg-green-100 text-green-700',  'icon' => 'check-circle'],
        'signed_off'       => ['label' => 'Signed Off',       'color' => 'bg-purple-100 text-purple-700','icon' => 'edit-3'],
        'failed'           => ['label' => 'Failed',            'color' => 'bg-red-100 text-red-700',      'icon' => 'x-circle'],
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

        $search = trim($_GET['q'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $sort   = trim($_GET['sort'] ?? 'newest');
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $per_page = 20;
        $offset   = ($page - 1) * $per_page;

        $where  = ["d.business_id = ?", "d.deleted_at IS NULL"];
        $params = [$this->biz_id];

        if ($search !== '') {
            $where[]  = "(d.doc_number LIKE ? OR d.recipient_name LIKE ? OR d.recipient_phone LIKE ? OR d.tracking_number LIKE ?)";
            $like     = "%{$search}%";
            $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
        }

        if ($status !== '' && array_key_exists($status, self::STATUSES)) {
            $where[]  = "d.status = ?";
            $params[] = $status;
        }

        $where_sql = implode(' AND ', $where);
        $order = match($sort) {
            'newest'  => 'd.created_at DESC',
            'oldest'  => 'd.created_at ASC',
            'updated' => 'd.updated_at DESC',
            default   => 'd.created_at DESC',
        };

        // Count
        $stmtC = $this->db->prepare("SELECT COUNT(*) FROM deliveries d WHERE {$where_sql}");
        $stmtC->execute($params);
        $total_items = (int)$stmtC->fetchColumn();
        $total_pages = max(1, ceil($total_items / $per_page));

        // Fetch
        $stmtQ = $this->db->prepare("
            SELECT d.*, c.full_name AS customer_name, i.doc_number AS invoice_number
            FROM deliveries d
            LEFT JOIN customers c ON c.id = d.customer_id
            LEFT JOIN invoices i ON i.id = d.invoice_id
            WHERE {$where_sql}
            ORDER BY {$order}
            LIMIT {$per_page} OFFSET {$offset}
        ");
        $stmtQ->execute($params);
        $deliveries = $stmtQ->fetchAll();

        // Summary
        $stmtS = $this->db->prepare("
            SELECT
                COUNT(*) AS total_deliveries,
                SUM(CASE WHEN status = 'pending_dispatch' THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN status IN ('dispatched','in_transit') THEN 1 ELSE 0 END) AS active_count,
                SUM(CASE WHEN status IN ('delivered','signed_off') THEN 1 ELSE 0 END) AS completed_count,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed_count,
                SUM(CASE WHEN status = 'signed_off' THEN 1 ELSE 0 END) AS signed_count
            FROM deliveries WHERE business_id = ? AND deleted_at IS NULL
        ");
        $stmtS->execute([$this->biz_id]);
        $summary = $stmtS->fetch();

        $statuses  = self::STATUSES;
        $providers = self::PROVIDERS;

        $title     = "Deliveries";
        $view_file = BASE_PATH . '/views/deliveries/index.php';
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
            return $this->save_delivery(null);
        }

        $delivery = [];
        $editing  = false;
        $items    = [];

        // Pre-populate from invoice
        $pre_invoice = null;
        $pre_invoice_items = [];
        $invoice_id = (int)($_GET['invoice_id'] ?? 0);
        if ($invoice_id) {
            $stmt = $this->db->prepare("SELECT * FROM invoices WHERE id = ? AND business_id = ?");
            $stmt->execute([$invoice_id, $this->biz_id]);
            $pre_invoice = $stmt->fetch();

            // One live delivery per invoice (S3): open the one it has
            $live = $pre_invoice ? $this->live_delivery_for((int)$pre_invoice['id']) : null;
            if ($live) {
                flash('info', $pre_invoice['doc_number'] . ' already has delivery ' . $live['doc_number'] . '.');
                redirect('deliveries/view/' . $live['id']);
                return;
            }

            if ($pre_invoice) {
                $stmtItems = $this->db->prepare("
                    SELECT ii.description, ii.quantity, p.name AS product_name, p.id AS product_id
                    FROM invoice_items ii
                    LEFT JOIN products p ON p.id = ii.product_id
                    WHERE ii.invoice_id = ?
                ");
                $stmtItems->execute([$invoice_id]);
                $pre_invoice_items = $stmtItems->fetchAll();

                // Get customer info for recipient
                if ($pre_invoice['customer_id']) {
                    $stmtCust = $this->db->prepare("SELECT * FROM customers WHERE id = ? AND business_id = ?");
                    $stmtCust->execute([$pre_invoice['customer_id'], $this->biz_id]);
                    $pre_customer = $stmtCust->fetch();
                }
            }
        }

        $providers       = self::PROVIDERS;

        $title     = "New Delivery";
        $view_file = BASE_PATH . '/views/deliveries/form.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ──────────────────────────────────────────
     *  EDIT
     * ────────────────────────────────────────── */
    public function edit($id = null) {
        $user     = auth_user();
        $business = auth_business();

        $delivery = $this->find_delivery($id);
        if (!$delivery) { not_found(); return; }

        // Can only edit pending_dispatch or dispatched
        if (!in_array($delivery['status'], ['pending_dispatch', 'dispatched'])) {
            flash('error', 'Cannot edit a delivery that is already in transit or completed.');
            redirect('deliveries/view/' . $id);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            return $this->save_delivery($id);
        }

        $editing = true;

        // Load items
        $stmtI = $this->db->prepare("SELECT * FROM delivery_items WHERE delivery_id = ?");
        $stmtI->execute([$id]);
        $items = $stmtI->fetchAll();

        $pre_invoice = null;
        $pre_invoice_items = [];
        $pre_customer = null;
        $providers = self::PROVIDERS;
        $delivery['invoice_doc_number'] = $this->invoice_doc_number((int)($delivery['invoice_id'] ?? 0));

        $title     = "Edit — " . e($delivery['doc_number']);
        $view_file = BASE_PATH . '/views/deliveries/form.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ──────────────────────────────────────────
     *  VIEW — Delivery Detail
     * ────────────────────────────────────────── */
    public function view($id = null) {
        $user     = auth_user();
        $business = auth_business();

        $delivery = $this->find_delivery($id);
        if (!$delivery) { not_found(); return; }

        $currency = $business['currency_symbol'] ?? '₦';

        // Items
        $stmtI = $this->db->prepare("
            SELECT di.*, p.name AS product_name
            FROM delivery_items di
            LEFT JOIN products p ON p.id = di.product_id
            WHERE di.delivery_id = ?
        ");
        $stmtI->execute([$id]);
        $items = $stmtI->fetchAll();

        // Customer
        $customer = null;
        if ($delivery['customer_id']) {
            $stmt = $this->db->prepare("SELECT * FROM customers WHERE id = ? AND business_id = ?");
            $stmt->execute([$delivery['customer_id'], $this->biz_id]);
            $customer = $stmt->fetch();
        }

        // Invoice
        $invoice = null;
        if ($delivery['invoice_id']) {
            $stmt = $this->db->prepare("SELECT id, doc_number, total, status FROM invoices WHERE id = ? AND business_id = ?");
            $stmt->execute([$delivery['invoice_id'], $this->biz_id]);
            $invoice = $stmt->fetch();
        }

        // A local number takes the business's own country code (M23)
        $wa_number = wa_digits($delivery['recipient_phone'], auth_business());

        $statuses  = self::STATUSES;
        $providers = self::PROVIDERS;

        // The order it belongs to: its own order_id, the order pointing at it, or its invoice's
        $order = !empty($delivery['order_id']) ? order_find($this->db, $this->biz_id, (int)$delivery['order_id']) : null;
        if (!$order) {
            $stmt = $this->db->prepare("SELECT * FROM orders WHERE delivery_id = ? AND business_id = ? LIMIT 1");
            $stmt->execute([(int)$delivery['id'], $this->biz_id]);
            $order = $stmt->fetch() ?: ($delivery['invoice_id'] ? order_by_invoice($this->db, $this->biz_id, (int)$delivery['invoice_id']) : null);
        }

        $title     = e($delivery['doc_number']);
        $view_file = BASE_PATH . '/views/deliveries/view.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ──────────────────────────────────────────
     *  UPDATE STATUS — POST
     * ────────────────────────────────────────── */
    public function update_status($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('deliveries'); return; }
        csrf_verify();

        $delivery = $this->find_delivery($id);
        if (!$delivery) { not_found(); return; }

        $new_status = trim($_POST['status'] ?? '');
        if (!array_key_exists($new_status, self::STATUSES)) {
            flash('error', 'Invalid status.');
            redirect('deliveries/view/' . $id);
            return;
        }

        // Validate transitions
        $valid_transitions = [
            'pending_dispatch' => ['dispatched', 'failed'],
            'dispatched'       => ['in_transit', 'delivered', 'failed'],
            'in_transit'       => ['delivered', 'failed'],
            'delivered'        => ['signed_off'],
            'signed_off'       => [],
            'failed'           => ['pending_dispatch'], // retry
        ];

        if (!in_array($new_status, $valid_transitions[$delivery['status']] ?? [])) {
            flash('error', 'Invalid status transition from ' . $delivery['status'] . ' to ' . $new_status);
            redirect('deliveries/view/' . $id);
            return;
        }

        $updates = ["status = ?"];
        $params  = [$new_status];

        // Set timestamps based on status
        if ($new_status === 'dispatched' && !$delivery['dispatch_date']) {
            $updates[] = "dispatch_date = NOW()";
        }
        if ($new_status === 'delivered' && !$delivery['actual_delivery']) {
            $updates[] = "actual_delivery = NOW()";
        }

        // Trying again needs an invoice that is still live and has no other delivery
        if ($delivery['status'] === 'failed' && $delivery['invoice_id']) {
            $stmt = $this->db->prepare("SELECT status FROM invoices WHERE id = ? AND business_id = ?");
            $stmt->execute([$delivery['invoice_id'], $this->biz_id]);
            $live = $this->live_delivery_for((int)$delivery['invoice_id'], (int)$id);
            if ($stmt->fetchColumn() === 'cancelled' || $live) {
                flash('error', $live ? 'That invoice already has delivery ' . $live['doc_number'] . '.' : 'Its invoice was cancelled, so this delivery can\'t go out again.');
                redirect('deliveries/view/' . $id);
                return;
            }
        }

        $params[]  = $id;
        $params[]  = $this->biz_id;
        $params[]  = $delivery['status'];

        // Only from the state it was read in: a double tap, or a sign-off
        // meanwhile, changes nothing (and tells the shopper once)
        $set_sql = implode(', ', $updates);
        $moved = $this->db->prepare("UPDATE deliveries SET {$set_sql} WHERE id = ? AND business_id = ? AND status = ? AND deleted_at IS NULL");
        $moved->execute($params);
        if ($moved->rowCount() !== 1) {
            flash('error', 'This delivery changed meanwhile. Please check it and try again.');
            redirect('deliveries/view/' . $id);
            return;
        }

        // Delivered or signed for: the invoice's promise is kept (P2)
        if (in_array($new_status, ['delivered', 'signed_off'], true) && $delivery['invoice_id']) {
            planner_invoice_delivered($this->db, $this->biz_id, (int)$delivery['invoice_id'], false, (int)auth_user()['id']);
        }

        // Its order follows: on its way, or delivered (with the shopper told,
        // the review asked for and the Log written by app/helpers/orders.php)
        if (in_array($new_status, ['dispatched', 'in_transit', 'delivered', 'signed_off'], true)) {
            order_sync_from_delivery($this->db, $this->biz_id, (int)$id);
        }

        flash('success', 'Status updated to ' . self::STATUSES[$new_status]['label'] . '.');
        redirect('deliveries/view/' . $id);
    }

    /* ──────────────────────────────────────────
     *  DELETE — soft delete
     * ────────────────────────────────────────── */
    public function delete($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('deliveries'); return; }
        csrf_verify();

        $delivery = $this->find_delivery($id);
        if (!$delivery) { not_found(); return; }

        if (in_array($delivery['status'], ['in_transit', 'delivered', 'signed_off'])) {
            flash('error', 'Cannot delete an active or completed delivery.');
            redirect('deliveries/view/' . $id);
            return;
        }

        $this->db->prepare("UPDATE deliveries SET deleted_at = NOW() WHERE id = ? AND business_id = ?")
                  ->execute([$id, $this->biz_id]);

        flash('success', $delivery['doc_number'] . ' deleted.');
        redirect('deliveries');
    }

    /* ──────────────────────────────────────────
     *  SIGNATURE — the customer's signature image (private file)
     * ────────────────────────────────────────── */
    public function signature($id = null) {
        $delivery = $this->find_delivery($id);
        send_private_file($delivery['signoff_signature_path'] ?? null);
    }

    /* ──────────────────────────────────────────
     *  AJAX — Search invoices
     * ────────────────────────────────────────── */
    public function search_invoices() {
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) { json_response([]); return; }

        $stmt = $this->db->prepare("
            SELECT i.id, i.doc_number, i.total, i.status, c.full_name AS customer_name, c.id AS customer_id,
                   c.phone AS customer_phone, c.address AS customer_address
            FROM invoices i
            LEFT JOIN customers c ON c.id = i.customer_id
            WHERE i.business_id = ? AND i.status <> 'cancelled'
              AND (i.doc_number LIKE ? OR c.full_name LIKE ?)
            ORDER BY i.created_at DESC LIMIT 10
        ");
        $like = "%{$q}%";
        $stmt->execute([$this->biz_id, $like, $like]);
        json_response($stmt->fetchAll());
    }

    /* ──────────────────────────────────────────
     *  AJAX — Get invoice items
     * ────────────────────────────────────────── */
    public function get_invoice_items() {
        $invoice_id = (int)($_GET['invoice_id'] ?? 0);
        if (!$invoice_id) { json_response([]); return; }

        $stmt = $this->db->prepare("
            SELECT ii.description, ii.quantity, p.id AS product_id, p.name AS product_name
            FROM invoice_items ii
            LEFT JOIN products p ON p.id = ii.product_id
            WHERE ii.invoice_id = ? AND EXISTS (
                SELECT 1 FROM invoices WHERE id = ii.invoice_id AND business_id = ?
            )
        ");
        $stmt->execute([$invoice_id, $this->biz_id]);
        json_response($stmt->fetchAll());
    }

    /* ──────────────────────────────────────────
     *  AJAX — Search customers
     * ────────────────────────────────────────── */
    public function search_customers() {
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) { json_response([]); return; }
        $stmt = $this->db->prepare("
            SELECT id, full_name, phone, address
            FROM customers WHERE business_id = ? AND deleted_at IS NULL AND is_active = 1 AND is_walk_in = 0
              AND (full_name LIKE ? OR phone LIKE ?)
            ORDER BY full_name ASC LIMIT 10
        ");
        $like = "%{$q}%";
        $stmt->execute([$this->biz_id, $like, $like]);
        json_response($stmt->fetchAll());
    }

    /* ══════════════════════════════════════════
     *  PRIVATE: save delivery
     * ══════════════════════════════════════════ */
    private function save_delivery(?int $id): void {
        $invoice_id          = (int)($_POST['invoice_id'] ?? 0) ?: null;
        $customer_id         = (int)($_POST['customer_id'] ?? 0) ?: null;
        $recipient_name      = trim($_POST['recipient_name'] ?? '');
        $recipient_phone     = trim($_POST['recipient_phone'] ?? '');
        $delivery_address    = trim($_POST['delivery_address'] ?? '');
        $pickup_address      = trim($_POST['pickup_address'] ?? '');
        $provider            = trim($_POST['provider'] ?? '');
        if (!array_key_exists($provider, self::PROVIDERS)) $provider = '';
        $rider_name          = trim($_POST['rider_name'] ?? '');
        $rider_phone         = trim($_POST['rider_phone'] ?? '');
        $booking_reference   = trim($_POST['booking_reference'] ?? '');
        $estimated_delivery  = trim($_POST['estimated_delivery'] ?? '') ?: null;
        $special_instructions= trim($_POST['special_instructions'] ?? '');
        $notes               = trim($_POST['notes'] ?? '');
        $tracking_number     = trim($_POST['tracking_number'] ?? '');
        $tracking_url        = trim($_POST['tracking_url'] ?? '');

        // Items
        $item_descriptions = $_POST['item_description'] ?? [];
        $item_quantities   = $_POST['item_quantity'] ?? [];
        $item_units        = $_POST['item_unit'] ?? [];
        $item_product_ids  = $_POST['item_product_id'] ?? [];
        $item_notes        = $_POST['item_notes'] ?? [];

        // Validate
        $errors = [];
        if ($recipient_name === '') $errors[] = 'Recipient name is required.';
        if ($delivery_address === '') $errors[] = 'Delivery address is required.';
        if ($p = phone_problem($recipient_phone, 50) ?? phone_problem($rider_phone, 50)) $errors[] = $p;   // D4
        if ($invoice_id && !owned_by_business($this->db, 'invoices', $invoice_id, $this->biz_id)) {
            $errors[] = 'Please pick one of your invoices.';
            $invoice_id = null;
        }
        if ($invoice_id) {
            $live = $this->live_delivery_for($invoice_id, (int)$id);
            if ($live) {
                $errors[] = 'That invoice already has delivery ' . $live['doc_number'] . '.';
            } elseif ($this->invoice_status($invoice_id) === 'cancelled') {
                $errors[] = 'That invoice was cancelled.';
            }
        }
        if ($customer_id && !owned_by_business($this->db, 'customers', $customer_id, $this->biz_id)) {
            $errors[] = 'Please pick one of your clients.';
            $customer_id = null;
        }

        $valid_items = 0;
        foreach ($item_descriptions as $i => $desc) {
            if (trim($desc) !== '' && (float)($item_quantities[$i] ?? 0) > 0) $valid_items++;
        }
        if ($valid_items === 0) $errors[] = 'Add at least one item.';

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
            // Keep what was typed (D1): an edit keeps its own id and number
            $editing  = $id !== null;
            $delivery = array_merge($editing ? ($this->find_delivery($id) ?: []) : [], $_POST);
            $delivery['invoice_id'] = $invoice_id;
            $delivery['customer_id'] = $customer_id;
            $delivery['invoice_doc_number'] = $this->invoice_doc_number((int)$invoice_id);
            $items    = [];
            foreach ($item_descriptions as $i => $desc) {
                $items[] = [
                    'description' => (string)$desc,
                    'quantity'    => (float)($item_quantities[$i] ?? 1),
                    'unit'        => (string)($item_units[$i] ?? ''),
                    'product_id'  => (int)($item_product_ids[$i] ?? 0),
                    'notes'       => (string)($item_notes[$i] ?? ''),
                ];
            }
            $pre_invoice = null; $pre_invoice_items = []; $pre_customer = null;
            $user     = auth_user();
            $business = auth_business();
            $providers = self::PROVIDERS;
            $title    = $editing ? "Edit Delivery" : "New Delivery";
            $view_file = BASE_PATH . '/views/deliveries/form.php';
            require_once BASE_PATH . '/views/layouts/app.php';
            return;
        }

        // Wrap everything in a transaction to prevent partial saves / duplicates
        $this->db->beginTransaction();
        try {
            if ($invoice_id) {
                // Lock the invoice so two saves at once can't both add a delivery
                $this->db->prepare("SELECT id FROM invoices WHERE id = ? AND business_id = ? FOR UPDATE")->execute([$invoice_id, $this->biz_id]);
                $live = $this->live_delivery_for($invoice_id, (int)$id);
                if ($live) {
                    throw new DomainException('That invoice already has delivery ' . $live['doc_number'] . '.');
                }
            }
            if ($id) {
                // Delete old items
                $this->db->prepare("DELETE FROM delivery_items WHERE delivery_id = ?")->execute([$id]);

                $this->db->prepare("
                    UPDATE deliveries SET
                        invoice_id = ?, customer_id = ?, recipient_name = ?, recipient_phone = ?,
                        delivery_address = ?, pickup_address = ?, provider = ?, rider_name = ?,
                        rider_phone = ?, booking_reference = ?, estimated_delivery = ?,
                        special_instructions = ?, notes = ?, tracking_number = ?, tracking_url = ?
                    WHERE id = ? AND business_id = ?
                ")->execute([
                    $invoice_id, $customer_id, $recipient_name, $recipient_phone ?: null,
                    $delivery_address, $pickup_address ?: null, $provider ?: null, $rider_name ?: null,
                    $rider_phone ?: null, $booking_reference ?: null, $estimated_delivery,
                    $special_instructions ?: null, $notes ?: null, $tracking_number ?: null, $tracking_url ?: null,
                    $id, $this->biz_id
                ]);
            } else {
                $doc_number    = generate_doc_number($this->db, $this->biz_id, 'DEL');
                $token         = generate_token();
                $signoff_token = generate_token();

                $business = auth_business();
                if ($pickup_address === '') {
                    $pickup_address = $business['address'] ?? '';
                }

                $this->db->prepare("
                    INSERT INTO deliveries (
                        business_id, invoice_id, customer_id, doc_number, token, signoff_token,
                        recipient_name, recipient_phone, delivery_address, pickup_address,
                        provider, rider_name, rider_phone, booking_reference,
                        estimated_delivery, special_instructions, notes, tracking_number, tracking_url
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $this->biz_id, $invoice_id, $customer_id, $doc_number, $token, $signoff_token,
                    $recipient_name, $recipient_phone ?: null, $delivery_address, $pickup_address ?: null,
                    $provider ?: null, $rider_name ?: null, $rider_phone ?: null, $booking_reference ?: null,
                    $estimated_delivery, $special_instructions ?: null, $notes ?: null,
                    $tracking_number ?: null, $tracking_url ?: null
                ]);
                $id = (int)$this->db->lastInsertId();
            }

            // Insert items
            foreach ($item_descriptions as $i => $desc) {
                $desc       = trim($desc);
                $qty        = (float)($item_quantities[$i] ?? 0);
                $unit       = trim($item_units[$i] ?? '');
                $product_id = (int)($item_product_ids[$i] ?? 0) ?: null;
                if ($product_id && !owned_by_business($this->db, 'products', $product_id, $this->biz_id)) $product_id = null;
                $item_note  = trim($item_notes[$i] ?? '');

                if ($desc === '' || $qty <= 0) continue;

                $this->db->prepare("
                    INSERT INTO delivery_items (delivery_id, product_id, description, quantity, unit, notes)
                    VALUES (?, ?, ?, ?, ?, ?)
                ")->execute([$id, $product_id, $desc, $qty, $unit ?: null, $item_note ?: null]);
            }

            $this->db->commit();
            flash('success', ($doc_number ?? 'Delivery') . ' saved.');
        } catch (DomainException $ex) {
            $this->db->rollBack();
            flash('error', $ex->getMessage());
            redirect($id ? 'deliveries/view/' . $id : 'deliveries');
            return;
        } catch (\Exception $ex) {
            $this->db->rollBack();
            error_log('[Nfelia] DeliveryController::save failed: ' . $ex->getMessage());
            flash('error', 'Failed to save delivery. Please try again.');
            redirect('deliveries');
            return;
        }

        redirect('deliveries/view/' . $id);
    }

    /* ══════════════════════════════════════════
     *  PRIVATE HELPERS
     * ══════════════════════════════════════════ */

    /** The invoice's delivery that is not deleted or failed, other than $except. */
    private function live_delivery_for(int $invoice_id, int $except = 0): ?array {
        $stmt = $this->db->prepare("
            SELECT id, doc_number FROM deliveries
            WHERE invoice_id = ? AND business_id = ? AND id <> ? AND deleted_at IS NULL AND status <> 'failed'
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$invoice_id, $this->biz_id, $except]);
        return $stmt->fetch() ?: null;
    }

    private function invoice_status(int $invoice_id): ?string {
        $stmt = $this->db->prepare("SELECT status FROM invoices WHERE id = ? AND business_id = ?");
        $stmt->execute([$invoice_id, $this->biz_id]);
        return $stmt->fetchColumn() ?: null;
    }

    private function invoice_doc_number(int $invoice_id): string {
        if (!$invoice_id) return '';
        $stmt = $this->db->prepare("SELECT doc_number FROM invoices WHERE id = ? AND business_id = ?");
        $stmt->execute([$invoice_id, $this->biz_id]);
        return (string)($stmt->fetchColumn() ?: '');
    }

    private function find_delivery($id): ?array {
        if (!$id) return null;
        $stmt = $this->db->prepare("SELECT * FROM deliveries WHERE id = ? AND business_id = ? AND deleted_at IS NULL");
        $stmt->execute([(int)$id, $this->biz_id]);
        return $stmt->fetch() ?: null;
    }
}