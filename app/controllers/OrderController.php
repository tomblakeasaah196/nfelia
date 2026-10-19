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
     *  APPROVE — the order is confirmed: invoice, stock, the shop payment
     *  and its receipt, the delivery note (order_confirm, app/helpers/orders.php)
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

        $order = order_find($this->db, $this->biz_id, (int) $id);
        if (!$order || !in_array($order["status"], ["pending", "pending_verification"], true)) {
            flash("error", "Order not found or cannot be approved.");
            redirect("orders");
        }

        // When the customer gets it goes on the new invoice and on Tasks & Calendar.
        [$delivery, $delivery_error] = planner_delivery_from_post($_POST);
        if ($delivery_error) {
            flash("error", $delivery_error);
            redirect("orders/view/{$id}");
        }

        // One transaction for everything approving does (order_confirm). It
        // starts by claiming the order, so a double tap or a retry finds it
        // already approved and nothing is ever made twice.
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
        try {
            order_confirm($this->db, $this->biz_id, (int) $order["id"], $this->get_current_user_id(), [
                "payment_received" => isset($_POST["payment_received"]) ? !empty($_POST["payment_received"]) : null,
                "pay_by"       => trim((string) ($_POST["pay_by"] ?? "")) ?: null,
                "deliver_mode" => $delivery["deliver_mode"],
                "deliver_by"   => $delivery["deliver_by"],
                "deliver_show" => $delivery["deliver_show"],
                "delivered_at" => $delivery["delivered_at"],
            ]);
            flash("success", "Order {$order["order_number"]} approved. Invoice created.");
        } catch (\Exception $e) {
            error_log("Order approve error: " . $e->getMessage());
            flash("error", $e instanceof \DomainException ? $e->getMessage() : "Error approving order: " . $e->getMessage());
        }

        redirect("orders/view/{$id}");
    }

    /* ──────────────────────────────────────────
     *  REJECT — turned down, and the shopper told why
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

        try {
            order_turn_down($this->db, $this->biz_id, (int) $id, $this->get_current_user_id(), (string) ($_POST["reason"] ?? ""));
            flash("info", "Order rejected.");
        } catch (\DomainException $e) {
            flash("error", "Order not found or cannot be rejected.");
        }
        redirect("orders/view/{$id}");
    }

    /* ──────────────────────────────────────────
     *  UPDATE STATUS — processing (still Confirmed: the shopper hears it is
     *  being prepared), fulfilled (done), cancelled
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
            "ready" => ["fulfilled", "cancelled"],
        ];
        $order = order_find($this->db, $this->biz_id, (int) $id);
        if (
            !$order ||
            !isset($allowed_transitions[$order["status"]]) ||
            !in_array($new_status, $allowed_transitions[$order["status"]], true)
        ) {
            flash("error", "Invalid status transition.");
            redirect("orders/view/{$id}");
        }

        $user_id = $this->get_current_user_id();
        try {
            if ($new_status === "processing") {
                // From the status the page showed, so a double tap tells the shopper once
                $moved = $this->db->prepare(
                    "UPDATE orders SET status = 'processing' WHERE id = ? AND business_id = ? AND status = 'approved'",
                );
                $moved->execute([(int) $id, $this->biz_id]);
                if ($moved->rowCount() === 1) {
                    order_log($this->db, $this->biz_id, (int) $id, "preparing", "Being prepared.", $user_id);
                    shop_order_tell($this->db, (int) $id, "preparing");
                }
            } elseif ($new_status === "fulfilled") {
                order_set_stage($this->db, $this->biz_id, (int) $id, "done", $user_id);
            } else {
                // Cancelling the order cancels its invoice too, which puts the
                // stock back and closes the delivery (S4). Money paid on it
                // needs to be given back first.
                $problem = order_cancel($this->db, $this->biz_id, (int) $id, $user_id);
                if ($problem !== "") {
                    throw new DomainException($problem);
                }
            }
        } catch (DomainException $e) {
            flash("error", $e->getMessage());
            redirect("orders/view/{$id}");
        } catch (Throwable $e) {
            error_log("Order status error: " . $e->getMessage());
            flash("error", "Could not update the order. Please try again.");
            redirect("orders/view/{$id}");
        }
        flash("success", "Order status updated to {$new_status}.");
        redirect("orders/view/{$id}");
    }

    private function get_current_user_id(): int
    {
        $u = auth_user();
        return (int) ($u["id"] ??
            ($_SESSION["user"]["id"] ?? ($_SESSION["user_id"] ?? 1)));
    }
}
