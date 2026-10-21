<?php
/**
 * Nfelia — Orders: every sale, from the shop or typed in (docs/ORDERS_HUB.md).
 *
 * The list, the order page (the heart of a sale: its invoice, payments and
 * receipts, delivery, tasks, messages and Log), New order (a quick sale too),
 * and everything done to an order from its page. The work itself is in
 * app/helpers/orders.php; this controller checks who may do it, calls it,
 * and says what happened in plain words.
 *
 * approve, reject and update_status are the shop order buttons from before
 * the order page; they stay so old links and the notification tests work.
 */
class OrderController
{
    private PDO $db;
    private int $biz_id;

    public function __construct()
    {
        if (!is_logged_in()) {
            redirect("login");
        }
        $this->db = get_db();
        $this->biz_id = (int) auth_business_id();
    }

    /* ──────────────────────────────────────────
     *  LIST
     *──────────────────────────────────────────*/
    public function index()
    {
        $user = auth_user();
        $business = auth_business();

        $search = mb_substr(trim((string) ($_GET["q"] ?? "")), 0, 100);
        $view = (string) ($_GET["view"] ?? "");
        if (!in_array($view, ["", "confirm", "progress", "balance", "done"], true)) {
            $view = "";
        }
        $page = max(1, (int) ($_GET["page"] ?? 1));
        $per_page = 20;

        $filter = ["q" => $search, "view" => $view];
        $counts = order_counts($this->db, $this->biz_id, $filter);
        $total_items = (int) ($counts["matching"] ?? 0);
        $total_pages = max(1, (int) ceil($total_items / $per_page));
        $page = min($page, $total_pages);
        $orders = order_rows($this->db, $this->biz_id, $filter, $per_page, ($page - 1) * $per_page);

        $title = "Orders";
        $view_file = BASE_PATH . "/views/orders/index.php";
        require_once BASE_PATH . "/views/layouts/app.php";
    }

    /* ──────────────────────────────────────────
     *  THE ORDER PAGE
     *──────────────────────────────────────────*/
    public function view($id = null)
    {
        $user = auth_user();
        $business = business_with_shop_link(auth_business());
        $order = order_find($this->db, $this->biz_id, (int) $id);
        if (!$order) {
            flash("error", "That order isn't here. It may have been removed.");
            redirect("orders");
        }
        $oid = (int) $order["id"];

        // What is on it, with what is in stock now: the shop doesn't show
        // stock, so the owner sees it here before confirming (S10)
        $stmt = $this->db->prepare(
            "SELECT oi.*, p.stock_qty, p.is_service, p.unit, p.image_path
             FROM order_items oi
             LEFT JOIN products p ON p.id = oi.product_id AND p.business_id = ? AND p.deleted_at IS NULL
             WHERE oi.order_id = ? ORDER BY oi.sort_order, oi.id",
        );
        $stmt->execute([$this->biz_id, $oid]);
        $lines = $stmt->fetchAll();

        $invoice = order_invoice($this->db, $this->biz_id, $order);
        $delivery = $this->delivery_of($order);
        $stage = order_stage($order, $delivery);
        $steps = order_steps($order, $delivery);
        $pay = order_pay_tag($invoice);
        $next = order_next_action($order, $invoice, $delivery);
        $payments = $this->payments_of($invoice);

        $stmt = $this->db->prepare("SELECT * FROM refunds WHERE order_id = ? AND business_id = ? AND status <> 'cancelled' ORDER BY id");
        $stmt->execute([$oid, $this->biz_id]);
        $refunds = $stmt->fetchAll();
        $refund_open = null;
        foreach ($refunds as $r) {
            if (in_array($r["status"], ["asked", "details_in"], true)) {
                $refund_open = $r;
            }
        }

        // Kept money: what this customer has with the shop, and what this
        // order kept that hasn't been used yet (it can still be given back)
        $customer = $this->customer_of($order);
        $kept_here = 0.0;
        $kept_total = 0.0;
        if ($customer && !(int) $customer["is_walk_in"]) {
            $kept_total = kept_balance($this->db, $this->biz_id, (int) $customer["id"]);
            $kept_here = kept_from_order($this->db, $this->biz_id, (int) $customer["id"], $oid);
        }

        $debt = null;
        if ($invoice) {
            $stmt = $this->db->prepare("SELECT * FROM debts WHERE business_id = ? AND invoice_id = ? AND type = 'receivable' AND status IN ('active','settling') ORDER BY id DESC LIMIT 1");
            $stmt->execute([$this->biz_id, (int) $invoice["id"]]);
            $debt = $stmt->fetch() ?: null;
        }

        // Its tasks: made for it, or for its invoice (job plans)
        $stmt = $this->db->prepare(
            "SELECT t.*, u.full_name AS assignee_name FROM tasks t
             LEFT JOIN users u ON u.id = t.assigned_to AND u.business_id = t.business_id
             WHERE t.business_id = ? AND t.deleted_at IS NULL AND t.parent_id IS NULL
               AND (t.order_id = ? OR (t.invoice_id IS NOT NULL AND t.invoice_id = ?))
             ORDER BY t.done_at IS NOT NULL, t.due_date IS NULL, t.due_date, t.id",
        );
        $stmt->execute([$this->biz_id, $oid, (int) ($invoice["id"] ?? 0)]);
        $tasks = $stmt->fetchAll();

        $events = order_events($this->db, $this->biz_id, $oid);
        $quote = null;
        if (!empty($order["quotation_id"])) {
            $stmt = $this->db->prepare("SELECT id, doc_number FROM quotations WHERE id = ? AND business_id = ?");
            $stmt->execute([(int) $order["quotation_id"], $this->biz_id]);
            $quote = $stmt->fetch() ?: null;
        }

        // The customer's own page: the shop's tracking page, else the invoice
        $track_url = shop_order_url($this->db, $order, $business);
        $customer_url = $this->customer_url($track_url, $invoice);
        $review = null;
        if ($track_url !== null) {
            $stmt = $this->db->prepare("SELECT * FROM reviews WHERE order_id = ?");
            $stmt->execute([$oid]);
            $review = $stmt->fetch() ?: null;   // looking never makes an invitation: moving to done does
        }

        $messages = $this->messages($order, $business, $invoice, $payments, $stage, $pay, $track_url, $customer_url, $customer);
        $is_boss = is_boss();

        // The mail server said the customer's email doesn't exist (app/helpers/bounces.php)
        require_once BASE_PATH . "/app/helpers/bounces.php";
        $email_bounced = bounce_at($this->db, $this->biz_id, $order["customer_email"] ?? null);

        $title = $order["order_number"];
        $view_file = BASE_PATH . "/views/orders/view.php";
        require_once BASE_PATH . "/views/layouts/app.php";
    }

    /* ──────────────────────────────────────────
     *  NEW ORDER, QUICK SALE, CHANGE ITEMS
     *──────────────────────────────────────────*/
    public function create()
    {
        $old = [];
        if (!empty($_GET["customer"])) {
            $c = $this->customer_choice((int) $_GET["customer"]);
            if ($c) {
                $old = ["customer_id" => $c["id"], "customer_name" => $c["name"], "customer_phone" => $c["phone"], "customer_email" => $c["email"]];
            }
        }
        $this->form("new", $old);
    }

    /** Quick sale is the same screen now: in the shop, paid, taken now is saved as one. */
    public function quick()
    {
        redirect("orders/create");
    }

    public function edit($id = null)
    {
        require_boss("change an order's items");
        $order = $this->order_or_back($id);
        $stage = order_stage($order, $this->delivery_of($order));
        if (!in_array($stage["key"], ["new", "confirmed", "ready"], true)) {
            $this->back($order, "error", "This order is " . strtolower($stage["words"]) . ", so its items can't change.");
        }
        if ($stage["key"] === "new" && !empty($order["payment_proof_path"])) {
            $this->back($order, "error", ORDER_EDIT_AFTER_PROOF);
        }
        $old = [
            "discount" => (float) $order["discount_amount"] > 0 ? $order["discount_amount"] : "",
            "items" => array_map(fn($l) => [
                "product_id" => $l["product_id"], "name" => $l["product_name"], "quantity" => fmt_qty((float) $l["quantity"]),
                "unit_price" => (float) $l["unit_price"], "note" => $l["item_note"], "variant" => (string) $l["variant_selection"],
            ], order_lines($this->db, (int) $order["id"])),
        ];
        $this->form("edit", $old, $order);
    }

    /** POST orders/save: a new order or a quick sale. */
    public function save()
    {
        $this->post_only("orders/create");
        if (form_once_used()) {
            flash("info", "That was already saved.");
            redirect("orders");
        }
        $mode = ($_POST["mode"] ?? "") === "quick" ? "quick" : "new";
        $in = $this->order_input($_POST, $mode);
        try {
            $order = order_create($this->db, $this->biz_id, $in, $this->user_id());
        } catch (DomainException $e) {
            $this->form("new", $_POST, null, $e->getMessage());
            return;
        }
        $symbol = $this->symbol();
        $invoice = order_invoice($this->db, $this->biz_id, $order);
        $paid = $invoice ? (float) $invoice["amount_paid"] : 0.0;
        $balance = $invoice ? (float) $invoice["balance_due"] : 0.0;
        flash("success", match (true) {
            $mode === "quick" => "Sold. " . planner_money($paid, $symbol) . " paid, receipt made.",
            $balance > 0 && $paid > 0 => "Order {$order["order_number"]} saved. The balance of " . planner_money($balance, $symbol) . " is in Debts.",
            $balance > 0 => "Order {$order["order_number"]} saved. " . planner_money($balance, $symbol) . " to pay later is in Debts.",
            default => "Order {$order["order_number"]} saved and paid. Receipt made.",
        });
        redirect("orders/view/{$order["id"]}");
    }

    /** POST orders/update/{id}: new items or discount. */
    public function update($id = null)
    {
        $this->post_only("orders/view/" . (int) $id);
        require_boss("change an order's items");
        $order = $this->order_or_back($id);
        $in = $this->order_input($_POST, "edit");
        try {
            $done = order_update_lines($this->db, $this->biz_id, (int) $order["id"], $in["items"], (float) $in["discount"], $this->user_id(), $this->symbol());
        } catch (DomainException $e) {
            $this->form("edit", $_POST, $order, $e->getMessage());
            return;
        }
        if ($done["overpaid"] > 0) {
            $this->back($order, "warning", "Items changed. " . planner_first_name($order["customer_name"]) . " paid " . planner_money($done["overpaid"], $this->symbol())
                . " more than the new total: give it back, or keep it for next time.");
        }
        $this->back($order, "success", "Items changed. The invoice now says " . planner_money($done["total"], $this->symbol()) . ".");
    }

    /* ──────────────────────────────────────────
     *  MOVING IT ON
     *──────────────────────────────────────────*/

    /** POST orders/confirm/{id}: a new shop order is confirmed (its invoice is made). */
    public function confirm($id = null)
    {
        $this->post_only("orders/view/" . (int) $id);
        $order = $this->order_or_back($id);
        if (!in_array($order["status"], ["pending", "pending_verification"], true)) {
            $this->back($order, "info", "This order is already confirmed.");
        }
        $when = trim((string) ($_POST["deliver_by"] ?? ""));
        if ($when !== "" && !planner_valid_date($when)) {
            $this->back($order, "error", "Pick the day they get it.");
        }
        $pay_by = trim((string) ($_POST["pay_by"] ?? ""));
        try {
            order_confirm($this->db, $this->biz_id, (int) $order["id"], $this->user_id(), [
                "payment_received" => !empty($order["payment_proof_path"]) && !empty($_POST["payment_received"]),
                "deliver_mode" => $when !== "" ? "date" : null,
                "deliver_by" => $when !== "" ? $when : null,
                "deliver_show" => 1,
                "pay_by" => planner_valid_date($pay_by) ? $pay_by : null,
            ]);
        } catch (DomainException $e) {
            $this->back($order, "error", $e->getMessage());
        }
        $this->back($order, "success", "Order confirmed and its invoice made." . ($order["customer_email"] ? " " . planner_first_name($order["customer_name"]) . " gets an email." : ""));
    }

    /** POST orders/stage/{id}: to = ready | done | undo_done. */
    public function stage($id = null)
    {
        $this->post_only("orders/view/" . (int) $id);
        $order = $this->order_or_back($id);
        $to = (string) ($_POST["to"] ?? "");
        if (!in_array($to, ["ready", "done", "undo_done"], true)) {
            $this->back($order, "error", "That step isn't one an order can take.");
        }
        try {
            order_set_stage($this->db, $this->biz_id, (int) $order["id"], $to, $this->user_id());
        } catch (DomainException $e) {
            $this->back($order, "error", $e->getMessage());
        }
        $is_delivery = $order["fulfillment_type"] === "delivery";
        $this->back($order, "success", match ($to) {
            "ready" => $is_delivery ? "On its way." : "Ready for pickup.",
            "done" => $is_delivery ? "Delivered." : "Collected.",
            default => "Marked not done. The order is back to Confirmed.",
        });
    }

    /** POST orders/when/{id}: the day the customer gets it. */
    public function when($id = null)
    {
        $this->post_only("orders/view/" . (int) $id);
        $order = $this->order_or_back($id);
        try {
            order_set_when($this->db, $this->biz_id, (int) $order["id"], (string) ($_POST["deliver_by"] ?? ""), $this->user_id());
        } catch (DomainException $e) {
            $this->back($order, "error", $e->getMessage(), "delivery");
        }
        $this->back($order, "success", "Day saved. It is on your Tasks & Calendar.", "delivery");
    }

    /* ──────────────────────────────────────────
     *  MONEY
     *──────────────────────────────────────────*/

    /** POST orders/pay/{id}: money received, with its receipt. */
    public function pay($id = null)
    {
        $this->post_only("orders/view/" . (int) $id);
        $order = $this->order_or_back($id);
        if (form_once_used()) {
            $this->back($order, "info", "That payment was already saved.", "money");
        }
        $method = (string) ($_POST["method"] ?? "cash");
        if (!in_array($method, ["cash", "bank_transfer", "mobile_money", "pos", "other"], true)) {
            $method = "cash";
        }
        $date = (string) ($_POST["payment_date"] ?? "");
        $date = planner_valid_date($date) && $date <= planner_today() ? $date : planner_today();
        try {
            $done = order_record_payment($this->db, $this->biz_id, (int) $order["id"], $this->amount($_POST["amount"] ?? "0"),
                                         $date, $method, mb_substr(trim((string) ($_POST["reference"] ?? "")), 0, 100) ?: null, null,
                                         $this->user_id(), $this->symbol());
        } catch (DomainException $e) {
            $this->back($order, "error", $e->getMessage(), "money");
        }
        $this->back($order, "success", $done["new_balance"] > 0
            ? "Payment saved, receipt made. Balance " . planner_money($done["new_balance"], $this->symbol()) . "."
            : "Paid in full. Receipt made.", "money");
    }

    /** POST orders/pay_by/{id}: when the balance is due. */
    public function pay_by($id = null)
    {
        $this->post_only("orders/view/" . (int) $id);
        $order = $this->order_or_back($id);
        try {
            order_set_pay_by($this->db, $this->biz_id, (int) $order["id"], (string) ($_POST["pay_by"] ?? ""), $this->user_id());
        } catch (DomainException $e) {
            $this->back($order, "error", $e->getMessage(), "money");
        }
        $this->back($order, "success", "Pay-by day saved. Debts and your reminders follow it.", "money");
    }

    /** POST orders/keep/{id}: what was paid over the new total stays for the customer's next order. */
    public function keep($id = null)
    {
        $this->post_only("orders/view/" . (int) $id);
        require_boss("keep a customer's money");
        $order = $this->order_or_back($id);
        try {
            $kept = order_keep_overpaid($this->db, $this->biz_id, (int) $order["id"], $this->user_id(), $this->symbol());
        } catch (DomainException $e) {
            $this->back($order, "error", $e->getMessage(), "money");
        }
        $this->back($order, "success", $kept > 0
            ? planner_money($kept, $this->symbol()) . " kept for " . planner_first_name($order["customer_name"]) . "'s next order."
            : "Nothing was paid over, so there is nothing to keep.", "money");
    }

    /** POST orders/unkeep/{id}: give kept money back instead. */
    public function unkeep($id = null)
    {
        $this->post_only("orders/view/" . (int) $id);
        require_boss("give money back");
        $order = $this->order_or_back($id);
        $amount = order_unkeep($this->db, $this->biz_id, (int) $order["id"], $this->user_id(), $this->symbol());
        $this->back($order, $amount > 0 ? "success" : "info", $amount > 0
            ? "Not kept any more. Give the " . planner_money($amount, $this->symbol()) . " back from Money."
            : ((order_invoice($this->db, $this->biz_id, $order)["status"] ?? "") === "cancelled"
                ? "This order is cancelled, so the money stays kept for the next order."
                : "That money was already used on another order."), "money");
    }

    /** POST orders/use_kept/{id}: money kept for this customer pays this order. */
    public function use_kept($id = null)
    {
        $this->post_only("orders/view/" . (int) $id);
        $order = $this->order_or_back($id);
        try {
            $used = order_use_kept($this->db, $this->biz_id, (int) $order["id"], $this->user_id(), $this->symbol());
        } catch (DomainException $e) {
            $this->back($order, "error", $e->getMessage(), "money");
        }
        $this->back($order, $used > 0 ? "success" : "info", $used > 0
            ? planner_money($used, $this->symbol()) . " of kept money used. Receipt made."
            : "There was nothing kept to use.", "money");
    }

    /** POST orders/refund_ask/{id}: ask the customer where to send their money. */
    public function refund_ask($id = null)
    {
        $this->post_only("orders/view/" . (int) $id);
        require_boss("give money back");
        $order = $this->order_or_back($id);
        try {
            $refund = refund_ask($this->db, $this->biz_id, (int) $order["id"], $this->amount($_POST["amount"] ?? "0"),
                                 (string) ($_POST["reason"] ?? ""), $this->user_id());
        } catch (DomainException $e) {
            $this->back($order, "error", $e->getMessage(), "money");
        }
        $first = planner_first_name($order["customer_name"]) ?: "The customer";
        $this->back($order, "success", (int) $refund["asked_email"]
            ? "Email sent. {$first} taps the link and tells you where to send it. You can send it on WhatsApp too."
            : "{$first} has no email. Send the link on WhatsApp.", "money");
    }

    /** POST orders/refund/{id}: money given back, with its proof. */
    public function refund($id = null)
    {
        $this->post_only("orders/view/" . (int) $id);
        require_boss("give money back");
        $order = $this->order_or_back($id);
        if (form_once_used()) {
            $this->back($order, "info", "That refund was already saved.", "money");
        }
        $file = $_FILES["proof"] ?? null;
        try {
            $refund = refund_record($this->db, $this->biz_id, (int) $order["id"], [
                "amount" => $this->amount($_POST["amount"] ?? "0"),
                "method" => (string) ($_POST["method"] ?? ""),
                "reference" => (string) ($_POST["reference"] ?? ""),
                "reason" => (string) ($_POST["reason"] ?? ""),
                "refund_id" => (int) ($_POST["refund_id"] ?? 0) ?: null,
            ], is_array($file) ? $file : null, $this->user_id(), $this->symbol());
        } catch (DomainException $e) {
            $this->back($order, "error", $e->getMessage(), "money");
        }
        $this->back($order, "success", "Refund " . $refund["doc_number"] . " saved. Send " . (planner_first_name($order["customer_name"]) ?: "the customer") . " the slip.", "money");
    }

    /** POST orders/refund_off/{id}: the asked refund is not going ahead. */
    public function refund_off($id = null)
    {
        $this->post_only("orders/view/" . (int) $id);
        require_boss("give money back");
        $order = $this->order_or_back($id);
        $off = refund_call_off($this->db, $this->biz_id, (int) $order["id"], $this->user_id());
        $this->back($order, $off ? "success" : "info", $off ? "Refund called off." : "There was no refund waiting.", "money");
    }

    /** GET orders/refund_proof/{refund id}: the picture or PDF of a refund (owners and admins). */
    public function refund_proof($id = null)
    {
        require_boss("see refund proofs");
        $stmt = $this->db->prepare("SELECT proof_path FROM refunds WHERE id = ? AND business_id = ?");
        $stmt->execute([(int) $id, $this->biz_id]);
        send_private_proof($stmt->fetchColumn() ?: null);
    }

    /** POST orders/cancel/{id}. */
    public function cancel($id = null)
    {
        $this->post_only("orders/view/" . (int) $id);
        require_boss("cancel an order");
        $order = $this->order_or_back($id);
        $problem = order_cancel($this->db, $this->biz_id, (int) $order["id"], $this->user_id(), mb_substr(trim((string) ($_POST["reason"] ?? "")), 0, 300));
        if ($problem !== "") {
            $this->back($order, "error", $problem);
        }
        $this->back($order, "success", "Order cancelled. Its stock is back on the shelf.");
    }

    /** POST orders/turn_down/{id}: a new shop order that can't be taken. */
    public function turn_down($id = null)
    {
        $this->post_only("orders/view/" . (int) $id);
        $order = $this->order_or_back($id);
        try {
            order_turn_down($this->db, $this->biz_id, (int) $order["id"], $this->user_id(), (string) ($_POST["reason"] ?? ""));
        } catch (DomainException $e) {
            $this->back($order, "error", $e->getMessage());
        }
        $this->back($order, "success", "Order turned down." . ($order["customer_email"] ? " " . planner_first_name($order["customer_name"]) . " gets an email." : ""));
    }

    /** POST orders/email/{id}: the customer's right email, after the one on the order bounced. */
    public function email($id = null)
    {
        $this->post_only("orders/view/" . (int) $id);
        $order = $this->order_or_back($id);
        require_once BASE_PATH . "/app/helpers/bounces.php";
        $email = bounce_new_email($this->db, $this->biz_id, "orders", $order, (string) ($_POST["email"] ?? ""));
        if ($email === null) {
            $this->back($order, "error", "Type the email address in full, like name@gmail.com.", "email");
        }
        order_log($this->db, $this->biz_id, (int) $order["id"], "edited", "Email changed to " . $email . ".", $this->user_id());
        $this->back($order, "success", "Saved. Emails about this order now go to " . $email . ".", "email");
    }

    /* ──────────────────────────────────────────
     *  WHATSAPP, AND THE FORM'S CUSTOMER SEARCH
     *──────────────────────────────────────────*/

    /** GET orders/wa/{id}?m=key: a ready message goes on the Log, then opens in WhatsApp
     *  (or, asked by assets/js/wa.js, answers with the wa.me link as JSON: the page opens WhatsApp itself). */
    public function wa($id = null)
    {
        $order = $this->order_or_back($id);
        $business = business_with_shop_link(auth_business());
        $invoice = order_invoice($this->db, $this->biz_id, $order);
        $payments = $this->payments_of($invoice);
        $track_url = shop_order_url($this->db, $order, $business);
        $messages = $this->messages($order, $business, $invoice, $payments, order_stage($order, $this->delivery_of($order)),
                                    order_pay_tag($invoice), $track_url, $this->customer_url($track_url, $invoice), $this->customer_of($order));
        $key = (string) ($_GET["m"] ?? "");
        if (!isset($messages["list"][$key]) || $messages["phone"] === "") {
            $this->back($order, "error", "That message isn't ready for this order.");
        }
        $m = $messages["list"][$key];
        // Logged only for a tap in the app itself: not by Nfelia support looking
        // on (read-only), nor by a link followed from another site
        $same_site = ($_SERVER["HTTP_SEC_FETCH_SITE"] ?? "same-origin") === "same-origin";
        if ($same_site && !view_as_active()) {
            order_log($this->db, $this->biz_id, (int) $order["id"], "whatsapp", "WhatsApp: " . $m["label"] . ".", $this->user_id(), ["key" => $key]);
        }
        $wa_link = "https://wa.me/" . $messages["phone"] . "?text=" . rawurlencode($m["text"]);
        if (is_ajax()) {
            json_response(["wa_link" => $wa_link]);
        }
        header("Location: " . $wa_link);
        exit();
    }

    /** GET orders/customers?q=: customers for the form's search (JSON). */
    public function customers()
    {
        $q = mb_substr(trim((string) ($_GET["q"] ?? "")), 0, 60);
        $out = [];
        if ($q !== "") {
            $like = "%" . str_replace(["\\", "%", "_"], ["\\\\", "\%", "\_"], $q) . "%";
            $digits = preg_replace("/\D/", "", $q);
            $phone_like = strlen($digits) >= 3 ? "%" . substr($digits, -10) . "%" : $like;
            $stmt = $this->db->prepare(
                "SELECT id FROM customers
                 WHERE business_id = ? AND deleted_at IS NULL AND is_walk_in = 0
                   AND (full_name LIKE ? OR business_name LIKE ?
                        OR REGEXP_REPLACE(COALESCE(phone, ''), '[^0-9]', '') LIKE ? OR REGEXP_REPLACE(COALESCE(whatsapp, ''), '[^0-9]', '') LIKE ?)
                 ORDER BY full_name LIMIT 8",
            );
            $stmt->execute([$this->biz_id, $like, $like, $phone_like, $phone_like]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $cid) {
                $out[] = $this->customer_choice((int) $cid);
            }
        }
        json_response(["customers" => array_values(array_filter($out))]);
    }

    /* ──────────────────────────────────────────
     *  FROM BEFORE THE ORDER PAGE (old links, tests)
     *──────────────────────────────────────────*/

    /** GET orders/proof/{id}: the shopper's payment screenshot (private file). */
    public function proof($id = null)
    {
        $stmt = $this->db->prepare("SELECT payment_proof_path FROM orders WHERE id = ? AND business_id = ?");
        $stmt->execute([(int) $id, $this->biz_id]);
        send_private_file($stmt->fetchColumn() ?: null);
    }

    /** POST orders/approve/{id} with deliver_mode (and deliver_by): confirm, the old way. */
    public function approve($id = null)
    {
        $this->post_only("orders");
        $order = order_find($this->db, $this->biz_id, (int) $id);
        if (!$order || !in_array($order["status"], ["pending", "pending_verification"], true)) {
            flash("error", "Order not found or cannot be approved.");
            redirect("orders");
        }
        [$delivery, $delivery_error] = planner_delivery_from_post($_POST);
        if ($delivery_error) {
            flash("error", $delivery_error);
            redirect("orders/view/{$id}");
        }
        try {
            order_confirm($this->db, $this->biz_id, (int) $order["id"], $this->user_id(), [
                "payment_received" => isset($_POST["payment_received"]) ? !empty($_POST["payment_received"]) : null,
                "pay_by" => trim((string) ($_POST["pay_by"] ?? "")) ?: null,
                "deliver_mode" => $delivery["deliver_mode"],
                "deliver_by" => $delivery["deliver_by"],
                "deliver_show" => $delivery["deliver_show"],
                "delivered_at" => $delivery["delivered_at"],
            ]);
            flash("success", "Order {$order["order_number"]} approved. Invoice created.");
        } catch (Exception $e) {
            error_log("Order approve error: " . $e->getMessage());
            flash("error", $e instanceof DomainException ? $e->getMessage() : "Error approving order: " . $e->getMessage());
        }
        redirect("orders/view/{$id}");
    }

    /** POST orders/reject/{id}: turn down, the old way. */
    public function reject($id = null)
    {
        $this->post_only("orders");
        try {
            order_turn_down($this->db, $this->biz_id, (int) $id, $this->user_id(), (string) ($_POST["reason"] ?? ""));
            flash("info", "Order rejected.");
        } catch (DomainException $e) {
            flash("error", "Order not found or cannot be rejected.");
        }
        redirect("orders/view/{$id}");
    }

    /** POST orders/update_status/{id}: processing, fulfilled or cancelled, the old way. */
    public function update_status($id = null)
    {
        $this->post_only("orders");
        $new_status = trim($_POST["status"] ?? "");
        if ($new_status === "cancelled") {
            require_boss("cancel an order");   // as orders/cancel does
        }
        $allowed = [
            "approved" => ["processing", "fulfilled", "cancelled"],
            "processing" => ["fulfilled", "cancelled"],
            "ready" => ["fulfilled", "cancelled"],
        ];
        $order = order_find($this->db, $this->biz_id, (int) $id);
        if (!$order || !isset($allowed[$order["status"]]) || !in_array($new_status, $allowed[$order["status"]], true)) {
            flash("error", "Invalid status transition.");
            redirect("orders/view/{$id}");
        }
        $user_id = $this->user_id();
        try {
            if ($new_status === "processing") {
                // From the status the page showed, so a double tap tells the shopper once
                $moved = $this->db->prepare("UPDATE orders SET status = 'processing' WHERE id = ? AND business_id = ? AND status = 'approved'");
                $moved->execute([(int) $id, $this->biz_id]);
                if ($moved->rowCount() === 1) {
                    order_log($this->db, $this->biz_id, (int) $id, "preparing", "Being prepared.", $user_id);
                    shop_order_tell($this->db, (int) $id, "preparing");
                }
            } elseif ($new_status === "fulfilled") {
                order_set_stage($this->db, $this->biz_id, (int) $id, "done", $user_id);
            } else {
                $problem = order_cancel($this->db, $this->biz_id, (int) $id, $user_id);
                if ($problem !== "") {
                    throw new DomainException($problem);
                }
            }
        } catch (DomainException $e) {
            flash("error", $e->getMessage());
            redirect("orders/view/{$id}");
        }
        flash("success", "Order status updated to {$new_status}.");
        redirect("orders/view/{$id}");
    }

    /* ══════════════════════════════════════════
     *  INSIDE
     *══════════════════════════════════════════*/

    private function user_id(): int
    {
        return (int) (auth_user()["id"] ?? 0);
    }

    private function symbol(): string
    {
        return (string) (auth_business()["currency_symbol"] ?? "₦");
    }

    /** An amount as typed: "50,000" or "50000.50". */
    private function amount($typed): float
    {
        return (float) str_replace([",", " "], "", (string) $typed);
    }

    private function post_only(string $back): void
    {
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            redirect($back);
        }
        csrf_verify();
    }

    private function order_or_back($id): array
    {
        $order = order_find($this->db, $this->biz_id, (int) $id);
        if (!$order) {
            flash("error", "That order isn't here. It may have been removed.");
            redirect("orders");
        }
        return $order;
    }

    /** Back to the order page, at a section when given, with a message. */
    private function back(array $order, string $type, string $message, string $at = ""): never
    {
        flash($type, $message);
        redirect("orders/view/" . (int) $order["id"] . ($at !== "" ? "#" . $at : ""));
    }

    private function delivery_of(array $order): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM deliveries WHERE business_id = ? AND deleted_at IS NULL AND (order_id = ? OR id = ?)
             ORDER BY status = 'failed', id DESC LIMIT 1",
        );
        $stmt->execute([$this->biz_id, (int) $order["id"], (int) ($order["delivery_id"] ?? 0)]);
        return $stmt->fetch() ?: null;
    }

    private function customer_of(array $order): ?array
    {
        if (empty($order["customer_id"])) {
            return null;
        }
        $stmt = $this->db->prepare("SELECT * FROM customers WHERE id = ? AND business_id = ?");
        $stmt->execute([(int) $order["customer_id"], $this->biz_id]);
        return $stmt->fetch() ?: null;
    }

    /** The invoice's payments and refunds, oldest first, each with its receipt (receipt_id, receipt_number, receipt_token). */
    private function payments_of(?array $invoice): array
    {
        if (!$invoice) {
            return [];
        }
        $stmt = $this->db->prepare(
            "SELECT p.*, r.id AS receipt_id, r.doc_number AS receipt_number, r.token AS receipt_token
             FROM payments p
             LEFT JOIN receipts r ON r.payment_id = p.id AND r.business_id = p.business_id
             WHERE p.invoice_id = ? AND p.business_id = ?
             ORDER BY p.payment_date, p.id",
        );
        $stmt->execute([(int) $invoice["id"], $this->biz_id]);
        return $stmt->fetchAll();
    }

    /** The customer's own page: the shop's tracking page, else the invoice. */
    private function customer_url(?string $track_url, ?array $invoice): ?string
    {
        return $track_url ?? ($invoice && !in_array($invoice["status"], ["draft", "cancelled"], true) ? APP_URL . "/doc/" . $invoice["token"] : null);
    }

    /** A customer as the form shows them: id, name, phone, email, address, kept. */
    private function customer_choice(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM customers WHERE id = ? AND business_id = ? AND deleted_at IS NULL AND is_walk_in = 0");
        $stmt->execute([$id, $this->biz_id]);
        $c = $stmt->fetch();
        if (!$c) {
            return null;
        }
        return [
            "id" => (int) $c["id"], "name" => (string) $c["full_name"], "phone" => (string) ($c["phone"] ?: ($c["whatsapp"] ?: "")),
            "email" => (string) ($c["email"] ?? ""), "address" => (string) ($c["address"] ?? ""),
            "kept" => kept_balance($this->db, $this->biz_id, (int) $c["id"]),
        ];
    }

    /**
     * What the form sent, as order_create() takes it. "Paid now" pays what
     * is left after kept money; "Part now" pays the amount typed.
     */
    private function order_input(array $p, string $mode): array
    {
        $items = [];
        foreach ((array) ($p["items"] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $items[] = [
                "product_id" => (int) ($row["product_id"] ?? 0) ?: null,
                "name" => (string) ($row["name"] ?? ""),
                // Rounded as the order stores them, so "Paid now" matches its total
                "quantity" => round($this->amount($row["quantity"] ?? "0"), 2),
                "unit_price" => round($this->amount($row["unit_price"] ?? "0"), 2),
                "note" => (string) ($row["note"] ?? ""),
                "variant" => (string) ($row["variant"] ?? ""),   // what the shopper chose (colour, size), kept through a change
            ];
        }
        $discount = $this->amount($p["discount"] ?? "0");
        if ($mode === "edit") {
            return ["items" => $items, "discount" => $discount];
        }

        $total = money2(max(0, array_sum(array_map(fn($i) => money2($i["quantity"] * $i["unit_price"]), $items)) - $discount));
        $customer_id = (int) ($p["customer_id"] ?? 0) ?: null;
        $use_kept = !empty($p["use_kept"]) && $customer_id;
        $kept = $use_kept ? min(kept_balance($this->db, $this->biz_id, $customer_id), $total) : 0.0;
        $pay = (string) ($p["pay"] ?? ($mode === "quick" ? "now" : "later"));
        $paid_now = match ($pay) {
            "now" => money2($total - $kept),
            "part" => $this->amount($p["paid_now"] ?? "0"),
            default => 0.0,
        };
        $fulfillment = (string) ($p["fulfillment"] ?? ($mode === "quick" ? "now" : "pickup"));

        return [
            "channel" => $mode === "quick" ? "quick" : "manual",
            "customer_id" => $customer_id,
            "customer_name" => $customer_id ? "" : (string) ($p["customer_name"] ?? ""),
            "customer_phone" => $customer_id ? "" : (string) ($p["customer_phone"] ?? ""),
            "customer_email" => $customer_id ? "" : (string) ($p["customer_email"] ?? ""),
            "items" => $items,
            "discount" => $discount,
            "fulfillment" => $fulfillment,
            "deliver_by" => $fulfillment === "now" ? "" : (string) ($p["deliver_by"] ?? ""),
            "delivery_address" => (string) ($p["delivery_address"] ?? ""),
            "paid_now" => $paid_now,
            "method" => (string) ($p["method"] ?? "cash"),
            "reference" => mb_substr(trim((string) ($p["reference"] ?? "")), 0, 100),
            "pay_by" => $pay === "now" ? "" : (string) ($p["pay_by"] ?? ""),
            "note" => (string) ($p["note"] ?? ""),
            "use_kept" => (bool) $use_kept,
            "deliver_show" => 1,
        ];
    }

    /** The New order / Change items screen. */
    private function form(string $mode, array $old, ?array $order = null, string $error = ""): void
    {
        $user = auth_user();
        $business = auth_business();
        $stmt = $this->db->prepare(
            "SELECT id, name, sell_price, stock_qty, unit, is_service, image_path FROM products
             WHERE business_id = ? AND is_active = 1 AND deleted_at IS NULL ORDER BY name LIMIT 1000",
        );
        $stmt->execute([$this->biz_id]);
        $products = array_map(fn($p) => [
            "id" => (int) $p["id"], "name" => (string) $p["name"], "price" => (float) $p["sell_price"],
            "stock" => (int) $p["is_service"] ? null : (float) $p["stock_qty"], "unit" => (string) ($p["unit"] ?? ""),
            "photo" => order_photo($p["image_path"] ?? null), "variants" => [],
        ], $stmt->fetchAll());

        // Attach each product's variant groups and options (with price changes),
        // so staff can pick a colour/size and the price follows.
        $by_id = [];
        foreach ($products as $k => $p) { $by_id[$p["id"]] = $k; }
        if ($by_id) {
            $vstmt = $this->db->prepare(
                "SELECT g.id AS gid, g.product_id, g.name AS gname, o.label, o.price_delta
                 FROM product_variant_groups g
                 JOIN product_variant_options o ON o.variant_group_id = g.id
                 WHERE g.business_id = ?
                 ORDER BY g.product_id, g.sort_order, g.id, o.sort_order, o.id",
            );
            $vstmt->execute([$this->biz_id]);
            $vgroups = [];
            foreach ($vstmt->fetchAll() as $r) {
                $pid = (int) $r["product_id"];
                $gid = (int) $r["gid"];
                $vgroups[$pid][$gid] ??= ["name" => (string) $r["gname"], "options" => []];
                $vgroups[$pid][$gid]["options"][] = ["label" => (string) $r["label"], "delta" => (float) $r["price_delta"]];
            }
            foreach ($vgroups as $pid => $gs) {
                if (isset($by_id[$pid])) { $products[$by_id[$pid]]["variants"] = array_values($gs); }
            }
        }

        $picked = !empty($old["customer_id"]) ? $this->customer_choice((int) $old["customer_id"]) : null;
        $form_mode = $mode;
        $title = $mode === "edit" ? "Change items" : "New order";
        $view_file = BASE_PATH . "/views/orders/form.php";
        require_once BASE_PATH . "/views/layouts/app.php";
    }

    /**
     * The ready WhatsApp messages for where the order stands. Each greets the
     * customer and names the order; a document leads with its own number.
     * Returns ['phone' => wa.me digits or '', 'list' => [key => [label, icon, text]]].
     */
    private function messages(array $order, array $business, ?array $invoice, array $payments, array $stage, array $pay,
                              ?string $track_url, ?string $customer_url, ?array $customer): array
    {
        $raw = (string) (($customer["whatsapp"] ?? "") ?: (($customer["phone"] ?? "") ?: ($order["customer_phone"] ?? "")));
        $walk_in = $customer && (int) $customer["is_walk_in"] === 1;
        $phone = $walk_in || $raw === "" ? "" : wa_digits($raw, $business);
        $first = planner_first_name($order["customer_name"]) ?: "there";
        $shop = (string) $business["name"];
        $num = (string) $order["order_number"];
        $symbol = (string) ($business["currency_symbol"] ?? "₦");
        $is_delivery = $order["fulfillment_type"] === "delivery";
        $link = $customer_url ? "\n\n" . ($track_url ? "Follow your order here: " : "See it here: ") . $customer_url : "";
        $when = $invoice && !empty($invoice["deliver_by"]) ? planner_when((string) $invoice["deliver_by"], planner_today()) : "";

        $list = [];
        if ($stage["key"] === "new") {
            $list["received"] = ["label" => "Order received", "icon" => "check",
                "text" => "Hello {$first}, this is {$shop}. We have your order {$num} and will confirm it with you shortly.{$link}"];
        }
        if (in_array($stage["key"], ["confirmed", "ready"], true)) {
            $list["confirmed"] = ["label" => "Order confirmed", "icon" => "check",
                "text" => "Hello {$first}, your order {$num} from {$shop} is confirmed"
                    . ($when !== "" ? ". It will be " . ($is_delivery ? "delivered " : "ready ") . $when : "") . ".{$link}"];
        }
        if ($stage["key"] === "ready") {
            $list["ready"] = $is_delivery
                ? ["label" => "On its way", "icon" => "delivery", "text" => "Hello {$first}, your order {$num} from {$shop} is on its way.{$link}"]
                : ["label" => "Ready for pickup", "icon" => "orders", "text" => "Hello {$first}, your order {$num} from {$shop} is ready. You can pick it up at our shop"
                    . (!empty($business["address"]) ? ": " . $business["address"] : "") . ".{$link}"];
        }
        if ($pay["balance"] > 0 && in_array($stage["key"], ["confirmed", "ready", "done"], true)) {
            $due = $invoice && !empty($invoice["due_date"]) ? " by " . date("D j M", strtotime((string) $invoice["due_date"])) : "";
            $list["balance"] = ["label" => "Balance reminder", "icon" => "money",
                "text" => "Hello {$first}, a friendly reminder from {$shop}: the balance on your order {$num} is "
                    . planner_money($pay["balance"], $symbol) . ($due !== "" ? ", due{$due}" : "") . ". Thank you!"
                    . ($invoice ? "\n\nInvoice " . $invoice["doc_number"] . ": " . APP_URL . "/doc/" . $invoice["token"] : "")];
        }
        if ($invoice && !in_array($invoice["status"], ["cancelled", "draft"], true)) {
            $list["invoice"] = ["label" => "Send the invoice", "icon" => "invoice",
                "text" => "Hello {$first}, here is invoice {$invoice["doc_number"]} for your order {$num} from {$shop}: " . APP_URL . "/doc/" . $invoice["token"]];
        }
        $last_receipt = null;
        foreach ($payments as $p) {
            if (!empty($p["receipt_token"]) && (float) $p["amount"] > 0 && $p["method"] !== "kept") {
                $last_receipt = $p;
            }
        }
        if ($last_receipt) {
            $list["receipt"] = ["label" => "Send the receipt", "icon" => "receipt",
                "text" => "Hello {$first}, thank you for your payment of " . planner_money((float) $last_receipt["amount"], $symbol)
                    . ". Here is receipt {$last_receipt["receipt_number"]} for your order {$num}: " . APP_URL . "/doc/" . $last_receipt["receipt_token"]];
        }
        $stmt = $this->db->prepare("SELECT * FROM refunds WHERE order_id = ? AND business_id = ? AND status = 'paid' ORDER BY id DESC LIMIT 1");
        $stmt->execute([(int) $order["id"], $this->biz_id]);
        if ($refund = $stmt->fetch()) {
            $list["refund"] = ["label" => "Send the refund slip", "icon" => "refund",
                "text" => "Hello {$first}, we have sent your " . planner_money((float) $refund["amount"], $symbol) . " refund for order {$num}."
                    . " Here is refund slip {$refund["doc_number"]}: " . refund_url((string) $refund["token"])];
        }
        if ($stage["key"] === "done") {
            $list["thanks"] = ["label" => "Say thank you", "icon" => "star",
                "text" => "Hello {$first}, thank you for your order {$num} from {$shop}."
                    . ($track_url ? " If you have a minute, tell us how it went: {$track_url}" : " We hope to see you again soon.")];
        }
        return ["phone" => $phone, "list" => $list];
    }
}
