<?php
/**
 * Nfelia — Order Controller (Storefront Orders)
 * Manages orders placed via the public landing page cart/checkout.
 * Actions: index (list), view (detail), approve, reject, update_status.
 */
class OrderController
{
    private $db;
    private $biz_id;

    public function __construct()
    {
        if (!is_logged_in()) {
            redirect("login");
        }
        $this->db = get_db();
        $this->biz_id = auth_business_id();
    }

    /* ──────────────────────────────────────────
     *  LIST — all orders with filters
     *──────────────────────────────────────────*/
    public function index()
    {
        $user = auth_user();
        $business = auth_business();

        $search = trim($_GET["q"] ?? "");
        $status = trim($_GET["status"] ?? "");
        $channel = trim($_GET["channel"] ?? "");
        $sort = trim($_GET["sort"] ?? "newest");
        $page = max(1, (int) ($_GET["page"] ?? 1));
        $per_page = 20;
        $offset = ($page - 1) * $per_page;

        $where = ["o.business_id = ?"];
        $params = [$this->biz_id];

        if ($search !== "") {
            $where[] =
                "(o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_phone LIKE ?)";
            $like = "%{$search}%";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $valid_statuses = [
            "pending",
            "pending_verification",
            "approved",
            "rejected",
            "processing",
            "fulfilled",
            "cancelled",
        ];
        if ($status !== "" && in_array($status, $valid_statuses)) {
            $where[] = "o.status = ?";
            $params[] = $status;
        }

        if ($channel !== "" && in_array($channel, ["cart_pop", "whatsapp"])) {
            $where[] = "o.channel = ?";
            $params[] = $channel;
        }

        $where_sql = implode(" AND ", $where);
        $order = match ($sort) {
            "oldest" => "o.created_at ASC",
            "total_desc" => "o.total DESC",
            "total_asc" => "o.total ASC",
            default => "o.created_at DESC",
        };

        // Count
        $stmtC = $this->db->prepare(
            "SELECT COUNT(*) FROM orders o WHERE {$where_sql}",
        );
        $stmtC->execute($params);
        $total_items = (int) $stmtC->fetchColumn();
        $total_pages = max(1, ceil($total_items / $per_page));

        // Fetch
        $stmtQ = $this->db->prepare("
            SELECT o.*
            FROM orders o
            WHERE {$where_sql}
            ORDER BY {$order}
            LIMIT {$per_page} OFFSET {$offset}
        ");
        $stmtQ->execute($params);
        $orders = $stmtQ->fetchAll();

        // Summary stats
        $stmtS = $this->db->prepare("
            SELECT
                COUNT(*)                                                        AS total_orders,
                COALESCE(SUM(total), 0)                                         AS total_value,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END)            AS pending_count,
                SUM(CASE WHEN status = 'pending_verification' THEN 1 ELSE 0 END) AS verification_count,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END)           AS approved_count,
                SUM(CASE WHEN status IN ('processing','fulfilled') THEN 1 ELSE 0 END) AS completed_count,
                SUM(CASE WHEN status IN ('pending','pending_verification') THEN total ELSE 0 END) AS pending_value
            FROM orders WHERE business_id = ?
        ");
        $stmtS->execute([$this->biz_id]);
        $summary = $stmtS->fetch();

        $title = "Orders";
        $view_file = BASE_PATH . "/views/orders/index.php";
        require_once BASE_PATH . "/views/layouts/app.php";
    }

    /* ──────────────────────────────────────────
     *  VIEW — single order detail
     *──────────────────────────────────────────*/
    /* ──────────────────────────────────────────
     *  PROOF — the shopper's payment screenshot (private file)
     *──────────────────────────────────────────*/
    public function proof($id = null)
    {
        $stmt = $this->db->prepare(
            "SELECT payment_proof_path FROM orders WHERE id = ? AND business_id = ?",
        );
        $stmt->execute([(int) $id, $this->biz_id]);
        send_private_file($stmt->fetchColumn() ?: null);
    }

    public function view($id = null)
    {
        $user = auth_user();
        $business = auth_business();

        if (!$id) {
            redirect("orders");
        }

        $stmt = $this->db->prepare(
            "SELECT * FROM orders WHERE id = ? AND business_id = ?",
        );
        $stmt->execute([(int) $id, $this->biz_id]);
        $order = $stmt->fetch();

        if (!$order) {
            flash("error", "Order not found.");
            redirect("orders");
        }

        // Order items
        // With what is in stock now: the shop doesn't show stock, so the
        // owner sees it here before approving (S10)
        $stmtI = $this->db->prepare(
            "SELECT oi.*, p.stock_qty, p.is_service, p.unit
             FROM order_items oi
             LEFT JOIN products p ON p.id = oi.product_id AND p.business_id = ? AND p.deleted_at IS NULL
             WHERE oi.order_id = ? ORDER BY oi.id ASC",
        );
        $stmtI->execute([$this->biz_id, (int) $order["id"]]);
        $items = $stmtI->fetchAll();

        // Linked documents
        $invoice = null;
        $receipt = null;
        $delivery_doc = null;
        if ($order["invoice_id"]) {
            $s = $this->db->prepare(
                "SELECT id, doc_number, total, status FROM invoices WHERE id = ? AND business_id = ?",
            );
            $s->execute([(int) $order["invoice_id"], $this->biz_id]);
            $invoice = $s->fetch();
        }
        if ($order["receipt_id"]) {
            $s = $this->db->prepare(
                "SELECT id, doc_number, amount FROM receipts WHERE id = ? AND business_id = ?",
            );
            $s->execute([(int) $order["receipt_id"], $this->biz_id]);
            $receipt = $s->fetch();
            if ($receipt) {
                $receipt["status"] = "paid"; // Fallback for view templates
            }
        }
        if ($order["delivery_id"]) {
            $s = $this->db->prepare(
                "SELECT id, doc_number, status FROM deliveries WHERE id = ? AND business_id = ?",
            );
            $s->execute([(int) $order["delivery_id"], $this->biz_id]);
            $delivery_doc = $s->fetch();
        }

        // The shopper's tracking link and, once the order is done, its review.
        // The review's own link is the shopper's alone: the owner never sees
        // it, and asks for a review through the tracking page (W14).
        $business = business_with_shop_link($business);
        $track_url = shop_order_url($this->db, $order, $business);
        $review = null;
        if ($track_url !== null) {
            $s = $this->db->prepare("SELECT * FROM reviews WHERE order_id = ?");
            $s->execute([(int) $order["id"]]);
            $review = $s->fetch() ?: null;
            if (!$review) {
                $review = review_invite($this->db, (int) $order["id"]);
            }
        }
        $customer_wa = shop_whatsapp_digits(["phone" => $order["customer_phone"]]);

        $title = "Order {$order["order_number"]}";
        $view_file = BASE_PATH . "/views/orders/view.php";
        require_once BASE_PATH . "/views/layouts/app.php";
    }

    /* ──────────────────────────────────────────
     *  APPROVE — triggers invoice/receipt/delivery generation
     *──────────────────────────────────────────*/
    public function approve($id = null)
    {
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            redirect("orders");
        }
        csrf_verify();

        if (!$id) {
            redirect("orders");
        }

        $stmt = $this->db->prepare(
            "SELECT * FROM orders WHERE id = ? AND business_id = ? AND status IN ('pending','pending_verification')",
        );
        $stmt->execute([(int) $id, $this->biz_id]);
        $order = $stmt->fetch();

        if (!$order) {
            flash("error", "Order not found or cannot be approved.");
            redirect("orders");
        }

        // When the customer gets it goes on the new invoice and on Tasks & Calendar.
        [$delivery, $delivery_error] = planner_delivery_from_post($_POST);
        if ($delivery_error) {
            flash("error", $delivery_error);
            redirect("orders/view/{$id}");
        }

        // Get order items
        $stmtI = $this->db->prepare(
            "SELECT * FROM order_items WHERE order_id = ?",
        );
        $stmtI->execute([(int) $order["id"]]);
        $items = $stmtI->fetchAll();

        // One transaction for everything approving does. It starts by claiming
        // the order (a double tap or a retry finds it already approved), so
        // nothing is ever made twice.
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
        $this->db->beginTransaction();
        try {
            $stmtClaim = $this->db->prepare("
                UPDATE orders SET status = 'approved', reviewed_by = ?, reviewed_at = NOW()
                WHERE id = ? AND business_id = ? AND status IN ('pending','pending_verification')
            ");
            $stmtClaim->execute([$this->get_current_user_id(), (int) $order["id"], $this->biz_id]);
            if ($stmtClaim->rowCount() !== 1) {
                throw new \DomainException("This order has already been dealt with.");
            }

            // 1. The order's own customer (or one matched by phone, or a new one)
            $customer_id = $this->match_or_create_customer($order);

            // 2. Create invoice (sent: the goods are going out)
            $invoice_id = $this->create_invoice_from_order(
                $order,
                $items,
                $customer_id,
            );

            $this->db
                ->prepare("UPDATE invoices SET deliver_mode = ?, deliver_by = ?, deliver_show = ?, delivered_at = ? WHERE id = ? AND business_id = ?")
                ->execute([$delivery["deliver_mode"], $delivery["deliver_by"], $delivery["deliver_show"], $delivery["delivered_at"], $invoice_id, $this->biz_id]);

            // 2b. The goods leave the shelf, as when an invoice is sent
            stock_settle($this->db, $this->biz_id, "invoice", $invoice_id, invoice_stock_target($this->db, $this->biz_id, $invoice_id),
                         "shop order " . $order["order_number"]);

            // 3. Paid with proof: a payment on the invoice (so Home and the
            //    profit and loss count it) and its receipt
            $receipt_id = null;
            if (!empty($order["payment_proof_path"])) {
                $receipt_id = $this->create_receipt_from_order(
                    $order,
                    $invoice_id,
                    $customer_id,
                );
            }
            customer_refresh_totals($this->db, $this->biz_id, $customer_id);

            // 4. If fulfillment is delivery, create delivery note
            $delivery_id = null;
            if (($order["fulfillment_type"] ?? "") === "delivery") {
                $delivery_id = $this->create_delivery_from_order(
                    $order,
                    $items,
                    $invoice_id,
                    $customer_id,
                );
            }

            // 5. Update order
            $this->db->prepare("
                UPDATE orders SET customer_id = ?, invoice_id = ?, receipt_id = ?, delivery_id = ?
                WHERE id = ? AND business_id = ?
            ")->execute([$customer_id, $invoice_id, $receipt_id, $delivery_id, (int) $order["id"], $this->biz_id]);

            $this->db->commit();

            // Paid with proof: that is money in, for owners and admins other
            // than whoever approved it (app/helpers/notify.php).
            if ($receipt_id) {
                notify("payment.order", $this->biz_id, [
                    "order_id" => (int) $order["id"], "order_number" => $order["order_number"],
                    "customer" => $order["customer_name"], "amount" => (float) $order["total"],
                ], ["actor" => (int) $this->get_current_user_id(), "dedupe" => (string) $order["id"]]);
            }

            shop_order_tell($this->db, (int) $order["id"], "confirmed");
            flash("success", "Order {$order["order_number"]} approved. Invoice created.");
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Order approve error: " . $e->getMessage());
            flash("error", $e instanceof \DomainException ? $e->getMessage() : "Error approving order: " . $e->getMessage());
        }

        redirect("orders/view/{$id}");
    }

    /* ──────────────────────────────────────────
     *  REJECT
     *──────────────────────────────────────────*/
    public function reject($id = null)
    {
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            redirect("orders");
        }
        csrf_verify();

        if (!$id) {
            redirect("orders");
        }

        $stmt = $this->db->prepare("
            UPDATE orders SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW()
            WHERE id = ? AND business_id = ? AND status IN ('pending','pending_verification')
        ");
        $stmt->execute([
            $this->get_current_user_id(),
            (int) $id,
            $this->biz_id,
        ]);

        if ($stmt->rowCount()) {
            shop_order_tell($this->db, (int) $id, "turned_down", mb_substr(trim($_POST["reason"] ?? ""), 0, 300));
            flash("info", "Order rejected.");
        } else {
            flash("error", "Order not found or cannot be rejected.");
        }
        redirect("orders/view/{$id}");
    }

    /* ──────────────────────────────────────────
     *  UPDATE STATUS — for processing/fulfilled
     *──────────────────────────────────────────*/
    public function update_status($id = null)
    {
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            redirect("orders");
        }
        csrf_verify();

        $new_status = trim($_POST["status"] ?? "");
        $allowed_transitions = [
            "approved" => ["processing", "fulfilled", "cancelled"],
            "processing" => ["fulfilled", "cancelled"],
        ];

        // One transaction: cancelling the order cancels its invoice too, which
        // puts the stock back and closes the delivery (S4). An invoice with
        // money on it needs a refund first, as on the invoice itself.
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "SELECT status, invoice_id FROM orders WHERE id = ? AND business_id = ? FOR UPDATE",
            );
            $stmt->execute([(int) $id, $this->biz_id]);
            $order = $stmt->fetch();

            if (
                !$order ||
                !isset($allowed_transitions[$order["status"]]) ||
                !in_array($new_status, $allowed_transitions[$order["status"]])
            ) {
                throw new DomainException("Invalid status transition.");
            }

            // From the status the page showed, so a double tap tells the shopper once
            $moved = $this->db->prepare(
                "UPDATE orders SET status = ? WHERE id = ? AND business_id = ? AND status = ?",
            );
            $moved->execute([$new_status, (int) $id, $this->biz_id, $order["status"]]);

            if ($new_status === "cancelled" && !empty($order["invoice_id"])) {
                $problem = invoice_cancel($this->db, $this->biz_id, (int) $order["invoice_id"],
                                          (string) (auth_business()["currency_symbol"] ?? "₦"));
                if ($problem !== "" && $problem !== "Invoice is already cancelled.") {
                    throw new DomainException($problem);
                }
            }
            $this->db->commit();
        } catch (DomainException $e) {
            $this->db->rollBack();
            flash("error", $e->getMessage());
            redirect("orders/view/{$id}");
        } catch (Throwable $e) {
            $this->db->rollBack();
            error_log("Order status error: " . $e->getMessage());
            flash("error", "Could not update the order. Please try again.");
            redirect("orders/view/{$id}");
        }
        // A fulfilled order can be reviewed: the link shows on the order and
        // on the shopper's tracking page
        if ($new_status === "fulfilled") {
            review_invite($this->db, (int) $id);
            // Its invoice's delivery promise is kept (P2)
            if (!empty($order["invoice_id"])) {
                planner_invoice_delivered($this->db, $this->biz_id, (int) $order["invoice_id"], false, $this->get_current_user_id());
            }
        }
        if ($moved->rowCount() === 1) {
            shop_order_tell($this->db, (int) $id, match ($new_status) {
                "processing" => "preparing",
                "fulfilled"  => "delivered",
                default      => "cancelled",
            });
        }
        flash("success", "Order status updated to {$new_status}.");
        redirect("orders/view/{$id}");
    }

    /* ══════════════════════════════════════════
     *  PRIVATE HELPERS — Invoice/Receipt/Delivery creation
     *══════════════════════════════════════════*/

    private function get_current_user_id(): int
    {
        $u = auth_user();
        return (int) ($u["id"] ??
            ($_SESSION["user"]["id"] ?? ($_SESSION["user_id"] ?? 1)));
    }

    private function match_or_create_customer(array $order): int
    {
        // The customer the order was placed as, if they are still here
        if (!empty($order["customer_id"])) {
            $stmt = $this->db->prepare("SELECT id FROM customers WHERE id = ? AND business_id = ? AND deleted_at IS NULL");
            $stmt->execute([(int) $order["customer_id"], $this->biz_id]);
            if ($stmt->fetchColumn()) {
                return (int) $order["customer_id"];
            }
        }
        // Try matching by phone
        $stmt = $this->db->prepare(
            "SELECT id FROM customers WHERE business_id = ? AND phone = ? AND deleted_at IS NULL LIMIT 1",
        );
        $stmt->execute([$this->biz_id, $order["customer_phone"]]);
        $existing = $stmt->fetchColumn();
        if ($existing) {
            return (int) $existing;
        }

        // Create new customer (matching PublicController schema exactly)
        $this->db
            ->prepare(
                "
            INSERT INTO customers (business_id, full_name, phone, email, notes)
            VALUES (?, ?, ?, ?, ?)
        ",
            )
            ->execute([
                $this->biz_id,
                $order["customer_name"],
                $order["customer_phone"],
                $order["customer_email"] ?: null,
                "Address: " .
                ($order["delivery_address"] ?: "None") .
                " | Storefront Order: " .
                $order["order_number"],
            ]);
        return (int) $this->db->lastInsertId();
    }

    private function create_invoice_from_order(
        array $order,
        array $items,
        int $customer_id,
    ): int {
        $doc_number = generate_doc_number($this->db, $this->biz_id, "INV");
        $token = bin2hex(random_bytes(16));
        // Sent and unpaid; a paid order's payment is recorded on it next
        $balance = (float) $order["total"];
        $paid = 0;
        $inv_status = "sent";

        $this->db
            ->prepare(
                "
            INSERT INTO invoices (business_id, customer_id, user_id, doc_number, status, issue_date, due_date,
                subtotal, discount_amount, tax_rate, tax_amount, total, amount_paid, balance_due, notes, token, created_at)
            VALUES (?, ?, ?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 7 DAY),
                ?, 0, 0, 0, ?, ?, ?, ?, ?, NOW())
        ",
            )
            ->execute([
                $this->biz_id,
                $customer_id,
                $this->get_current_user_id(),
                $doc_number,
                $inv_status,
                $order["subtotal"],
                $order["total"],
                $paid,
                $balance,
                "Auto-generated from order {$order["order_number"]}",
                $token,
            ]);
        $invoice_id = (int) $this->db->lastInsertId();

        // Insert line items
        foreach ($items as $item) {
            $this->db
                ->prepare(
                    "
                INSERT INTO invoice_items (invoice_id, product_id, description, quantity, unit_price, discount_percent, discount_amount, subtotal)
                VALUES (?, ?, ?, ?, ?, 0, 0, ?)
            ",
                )
                ->execute([
                    $invoice_id,
                    $item["product_id"],
                    $item["product_name"] .
                    (!empty($item["variant_selection"])
                        ? " ({$item["variant_selection"]})"
                        : ""),
                    $item["quantity"],
                    $item["unit_price"],
                    $item["line_total"],
                ]);
        }

        return $invoice_id;
    }

    /**
     * Take the invoice's goods off stock, as sending an invoice does
     * (InvoiceController::send). It reads the invoice's own lines, the same
     * ones cancelling the invoice reads to put stock back, so the two always
     * match. Services have no stock and are skipped.
     */
    private function create_receipt_from_order(
        array $order,
        int $invoice_id,
        int $customer_id,
    ): int {
        $method = $order["payment_method"] ?? "bank_transfer";
        $valid_methods = [
            "cash",
            "bank_transfer",
            "pos",
            "mobile_money",
            "cheque",
            "other",
        ];
        if (!in_array($method, $valid_methods)) {
            $method = "other";
        }

        // The payment, on the invoice (it becomes paid)
        $paid = invoice_record_payment($this->db, $this->biz_id, $invoice_id, (float) $order["total"], date("Y-m-d"), $method,
                                       null, "Shop order {$order["order_number"]}, proof sent with the order.",
                                       (int) $this->get_current_user_id(), (string) (auth_business()["currency_symbol"] ?? "₦"));

        $doc_number = generate_doc_number($this->db, $this->biz_id, "REC");
        $token = bin2hex(random_bytes(16));
        $this->db
            ->prepare(
                "
            INSERT INTO receipts (business_id, customer_id, invoice_id, payment_id, user_id, doc_number, issue_date, amount, balance_remaining, method, note, token, created_at)
            VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?, 0, ?, ?, ?, NOW())
        ",
            )
            ->execute([
                $this->biz_id,
                $customer_id,
                $invoice_id,
                $paid["payment_id"],
                $this->get_current_user_id(),
                $doc_number,
                $order["total"],
                $method,
                "Auto-receipt from order {$order["order_number"]}. Payment proof sent with the order.",
                $token,
            ]);
        return (int) $this->db->lastInsertId();
    }

    private function create_delivery_from_order(
        array $order,
        array $items,
        int $invoice_id,
        int $customer_id,
    ): int {
        $doc_number = generate_doc_number($this->db, $this->biz_id, "DEL");

        $token = bin2hex(random_bytes(16));
        $signoff_token = bin2hex(random_bytes(16));
        $this->db
            ->prepare(
                "
            INSERT INTO deliveries (business_id, customer_id, invoice_id, doc_number, token, signoff_token,
                recipient_name, recipient_phone, delivery_address, notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ",
            )
            ->execute([
                $this->biz_id,
                $customer_id,
                $invoice_id,
                $doc_number,
                $token,
                $signoff_token,
                $order["customer_name"],
                $order["customer_phone"],
                $order["delivery_address"] ?: "Pickup",
                "Auto-delivery from order {$order["order_number"]}",
            ]);
        $delivery_id = (int) $this->db->lastInsertId();

        // The delivery's lines (delivery_items.delivery_id, as on every delivery screen)
        foreach ($items as $item) {
            $this->db
                ->prepare(
                    "
                INSERT INTO delivery_items (delivery_id, product_id, description, quantity)
                VALUES (?, ?, ?, ?)
            ",
                )
                ->execute([
                    $delivery_id,
                    $item["product_id"],
                    $item["product_name"] .
                    (!empty($item["variant_selection"])
                        ? " ({$item["variant_selection"]})"
                        : ""),
                    $item["quantity"],
                ]);
        }

        return $delivery_id;
    }
}
