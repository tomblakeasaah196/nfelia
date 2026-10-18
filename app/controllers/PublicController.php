<?php
/**
 * Nfelia — Public Controller (No Authentication Required)
 *
 * Handles all public-facing routes:
 * /doc/{token} → Public document view (quotation, invoice, or receipt)
 * /delivery/{token} → Public delivery note (business view)
 * /sign/{signoff_token} → Recipient sign-off page (GET = form, POST = submit)
 * /biz/{slug} → retired; shops now live at name.nfelia.com
 * /public/quote-request, /public/cart-checkout → retired with /biz (410)
 *
 * Shop pages, orders and quote requests are drawn here and served at the
 * shop's own address by StorefrontController, which extends this class.
 */
use Dompdf\Dompdf;
use Dompdf\Options;
class PublicController
{
    protected $db;
    public function __construct()
    {
        $this->db = get_db();
    }
    /** * Fallback for empty public routes (e.g. hitting /doc or /biz directly) */ public function index(): void
    {
        http_response_code(404);
        if (file_exists(BASE_PATH . "/views/404.php")) {
            require_once BASE_PATH . "/views/404.php";
        } else {
            echo "404 Not Found";
        }
    } /* ────────────────────────────────────────── * UNIFIED DOCUMENT VIEW /doc/{token} * Checks quotations, invoices, receipts by token * *──────────────────────────────────────────*/
    public function document(string $token): void
    {
        /* 1. Try quotation */ $stmt = $this->db->prepare(
            "SELECT * FROM quotations WHERE token = ? LIMIT 1",
        );
        $stmt->execute([$token]);
        $doc = $stmt->fetch();
        if ($doc) {
            $this->render_quotation($doc);
            return;
        } /* 2. Try invoice */
        $stmt = $this->db->prepare(
            "SELECT * FROM invoices WHERE token = ? LIMIT 1",
        );
        $stmt->execute([$token]);
        $doc = $stmt->fetch();
        if ($doc) {
            $this->render_invoice($doc);
            return;
        } /* 3. Try receipt */
        $stmt = $this->db->prepare(
            "SELECT * FROM receipts WHERE token = ? LIMIT 1",
        );
        $stmt->execute([$token]);
        $doc = $stmt->fetch();
        if ($doc) {
            $this->render_receipt($doc);
            return;
        } /* Not found */
        $this->notice([
            "title" => "We couldn't find this document",
            "body" => "The link may have expired. Ask the business to send it again.",
        ]);
    } /* ────────────────────────────────────────── * API: Client Portal Actions (No Auth Required) * *──────────────────────────────────────────*/
    public function document_action($token)
    {
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            http_response_code(405);
            exit();
        }
        $db = get_db(); /* Securely fetch quotation via unique token */
        $stmt = $db->prepare("SELECT * FROM quotations WHERE token = ?");
        $stmt->execute([$token]);
        $quotation = $stmt->fetch();
        if (!$quotation) {
            json_response(
                [
                    "success" => false,
                    "error" => "Document not found or access revoked.",
                ],
                404,
            );
        }
        $action = $_POST["action"] ?? "";
        $client_ip =
            $_SERVER[
                "REMOTE_ADDR"
            ]; /* Vital for non-repudiation of signatures */
        // A quote can be answered while it is open. Once accepted, declined,
        // expired or turned into an invoice, signing or declining again would
        // undo the business's records, so it is refused.
        $open = in_array($quotation["status"], ["sent", "in_negotiation", "pending_signature"], true);
        if (in_array($action, ["sign", "decline"], true) && !$open) {
            json_response(["success" => false, "error" => "This quote has already been answered or has expired. Ask the business for a new one."], 409);
        }
        if ($action === "comment" && !throttle_allows("quote_comment", (string)$quotation["id"], 20, 60)) {
            json_response(["success" => false, "error" => "Too many messages at once. Please wait a little, or call the business."], 429);
        }
        $tell = null;   // the alert for the business, sent once this is saved
        $stmtC = $db->prepare("SELECT full_name FROM customers WHERE id = ?");
        $stmtC->execute([(int)$quotation["customer_id"]]);
        $customer_name = trim((string)$stmtC->fetchColumn()) ?: "Your customer";
        try {
            $db->beginTransaction();
            if ($action === "comment") {
                $message = trim($_POST["message"] ?? "");
                if (!$message) {
                    throw new Exception("Message is required.");
                } /* Log activity */
                $stmtLog = $db->prepare(
                    " INSERT INTO quotation_activities (business_id, quotation_id, actor_type, action, message, old_status, new_status, meta_data) VALUES (?, ?, 'client', 'comment_added', ?, ?, 'in_negotiation', JSON_OBJECT('ip', ?)) ",
                );
                $stmtLog->execute([
                    $quotation["business_id"],
                    $quotation["id"],
                    $message,
                    $quotation["status"],
                    $client_ip,
                ]); /* Shift state */
                $comment_id = $db->lastInsertId();
                if ($open) {
                    $db->prepare(
                        "UPDATE quotations SET status = 'in_negotiation' WHERE id = ?",
                    )->execute([
                        $quotation["id"],
                    ]);
                }
                $tell = ["quote.comment", ["comment" => $message], ["dedupe" => "comment-" . $comment_id]];
            } elseif ($action === "sign") {
                $signature = $_POST["signature"] ?? ""; /* Base64 Canvas Data */
                $name = trim($_POST["name"] ?? "");
                if (!$signature || !$name) {
                    throw new Exception(
                        "Digital signature and name are legally required.",
                    );
                } /* Stamp the quotation with signature data */
                $stmtUpd = $db->prepare(
                    " UPDATE quotations SET status = 'accepted', signature_data = ?, signed_by_name = ?, signed_at = NOW(), client_ip = ? WHERE id = ? ",
                );
                $stmtUpd->execute([
                    $signature,
                    $name,
                    $client_ip,
                    $quotation["id"],
                ]); /* Log the signature event */
                $stmtLog = $db->prepare(
                    " INSERT INTO quotation_activities (business_id, quotation_id, actor_type, action, message, old_status, new_status) VALUES (?, ?, 'client', 'signed', 'Client signed and legally accepted the proposal.', ?, 'accepted') ",
                );
                $stmtLog->execute([
                    $quotation["business_id"],
                    $quotation["id"],
                    $quotation["status"],
                ]);
                $customer_name = $name;
                $tell = ["quote.accepted", ["total" => (float)($quotation["total"] ?? 0)], ["dedupe" => "accepted"]];
            } elseif ($action === "decline") {
                $reason = trim($_POST["reason"] ?? "");
                $db->prepare(
                    "UPDATE quotations SET status = 'declined' WHERE id = ?",
                )->execute([$quotation["id"]]);
                $stmtLog = $db->prepare(
                    " INSERT INTO quotation_activities (business_id, quotation_id, actor_type, action, message, old_status, new_status, meta_data) VALUES (?, ?, 'client', 'declined', ?, ?, 'declined', JSON_OBJECT('ip', ?)) ",
                );
                $stmtLog->execute([
                    $quotation["business_id"],
                    $quotation["id"],
                    $reason,
                    $quotation["status"],
                    $client_ip,
                ]);
                $tell = ["quote.declined", ["reason" => $reason], ["dedupe" => "declined"]];
            } else {
                throw new Exception("Invalid action requested.");
            }
            $db->commit();
            if ($tell) {
                [$event, $extra, $opts] = $tell;
                notify($event, (int)$quotation["business_id"], $extra + [
                    "quotation_id" => (int)$quotation["id"], "quotation_number" => $quotation["doc_number"], "customer" => $customer_name,
                ], ["dedupe" => $quotation["id"] . ":" . $opts["dedupe"]]);
            }
            json_response(["success" => true]);
        } catch (Exception $e) {
            $db->rollBack();
            json_response(
                ["success" => false, "error" => $e->getMessage()],
                400,
            );
        }
    }
    /* ── Render helpers ────────────────────── */ private function render_quotation(
        array $quotation,
    ): void {
        $business = $this->get_business($quotation["business_id"]);
        $customer = $quotation["customer_id"]
            ? $this->get_customer($quotation["customer_id"])
            : null;
        $stmtItems = $this->db->prepare(
            "SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY sort_order ASC",
        );
        $stmtItems->execute([$quotation["id"]]);
        $items = $stmtItems->fetchAll();
        $bank = $quotation["bank_account_id"]
            ? $this->get_bank($quotation["bank_account_id"])
            : null;
        $stmtAct = $this->db->prepare(
            "SELECT * FROM quotation_activities WHERE quotation_id = ? ORDER BY created_at ASC",
        );
        $stmtAct->execute([$quotation["id"]]);
        $activities = $stmtAct->fetchAll();
        require_once BASE_PATH . "/views/quotations/public.php";
    }
    private function render_invoice(array $invoice): void
    {
        $business = $this->get_business($invoice["business_id"]);
        $customer = $invoice["customer_id"]
            ? $this->get_customer($invoice["customer_id"])
            : null;
        $stmtItems = $this->db->prepare(
            " SELECT ii.*, p.sku as product_sku FROM invoice_items ii LEFT JOIN products p ON p.id = ii.product_id WHERE ii.invoice_id = ? ORDER BY ii.sort_order ASC ",
        );
        $stmtItems->execute([$invoice["id"]]);
        $items = $stmtItems->fetchAll();
        $bank = $invoice["bank_account_id"]
            ? $this->get_bank($invoice["bank_account_id"])
            : null;
        require_once BASE_PATH . "/views/invoices/public.php";
    }
    private function render_receipt(array $receipt): void
    {
        $business = $this->get_business($receipt["business_id"]);
        $customer = $receipt["customer_id"]
            ? $this->get_customer($receipt["customer_id"])
            : null; /* Line items (for standalone POS receipts) */
        $items = [];
        if (!$receipt["invoice_id"]) {
            $stmtItems = $this->db->prepare(
                " SELECT ri.*, p.sku as product_sku FROM receipt_items ri LEFT JOIN products p ON p.id = ri.product_id WHERE ri.receipt_id = ? ORDER BY ri.sort_order ASC ",
            );
            $stmtItems->execute([$receipt["id"]]);
            $items = $stmtItems->fetchAll();
        } /* Linked invoice (for reference display) */
        $invoice = null;
        if ($receipt["invoice_id"]) {
            $stmtInv = $this->db->prepare(
                "SELECT id, doc_number, total FROM invoices WHERE id = ?",
            );
            $stmtInv->execute([$receipt["invoice_id"]]);
            $invoice = $stmtInv->fetch();
        }
        require_once BASE_PATH . "/views/receipts/public.php";
    }
    private function get_business(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM businesses WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
    private function get_customer(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
    private function get_bank(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM bank_accounts WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    } /* ────────────────────────────────────────── * PUBLIC DELIVERY NOTE /delivery/{token} * *──────────────────────────────────────────*/
    public function delivery(string $token): void
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM deliveries WHERE token = ? AND deleted_at IS NULL LIMIT 1",
        );
        $stmt->execute([$token]);
        $delivery = $stmt->fetch();
        if (!$delivery) {
            $this->notice([
                "title" => "We couldn't find this delivery note",
                "body" => "The link may have expired. Ask the business to send it again.",
            ]);
            return;
        }
        $business = $this->get_business($delivery["business_id"]);
        $customer = $delivery["customer_id"]
            ? $this->get_customer($delivery["customer_id"])
            : null;
        $stmtItems = $this->db->prepare(
            " SELECT di.*, p.name AS product_name FROM delivery_items di LEFT JOIN products p ON p.id = di.product_id WHERE di.delivery_id = ? ",
        );
        $stmtItems->execute([$delivery["id"]]);
        $items = $stmtItems->fetchAll();
        /* The status and courier names live on DeliveryController, which the
           public routes don't load */
        require_once BASE_PATH . "/app/controllers/DeliveryController.php";
        $statuses = DeliveryController::STATUSES;
        $providers = DeliveryController::PROVIDERS;
        require_once BASE_PATH . "/views/deliveries/public.php";
    } /* ────────────────────────────────────────── * E-SIGNATURE SIGN-OFF /sign/{signoff_token} * GET → show sign-off form * POST → process signature submission * *──────────────────────────────────────────*/
    public function signoff(string $signoff_token): void
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM deliveries WHERE signoff_token = ? AND deleted_at IS NULL LIMIT 1",
        );
        $stmt->execute([$signoff_token]);
        $delivery = $stmt->fetch();
        if (!$delivery) {
            $this->notice([
                "title" => "This sign-off link doesn't work",
                "body" => "It may have expired or already been used. Ask the business for a new link.",
            ]);
            return;
        }
        $business = $this->get_business($delivery["business_id"]);
        $stmtItems = $this->db->prepare(
            " SELECT di.*, p.name AS product_name FROM delivery_items di LEFT JOIN products p ON p.id = di.product_id WHERE di.delivery_id = ? ",
        );
        $stmtItems->execute([$delivery["id"]]);
        $items = $stmtItems->fetchAll();
        require_once BASE_PATH . "/app/controllers/DeliveryController.php";
        $statuses = DeliveryController::STATUSES;
        $providers =
            DeliveryController::PROVIDERS; /* POST — process signature */
        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            $this->process_signoff($delivery, $business);
            return;
        } /* GET — show form */
        require_once BASE_PATH . "/views/deliveries/signoff.php";
    }
    /** * Process the e-signature submission (AJAX POST from sign-off page) */ private function process_signoff(
        array $delivery,
        array $business,
    ): void {
        header("Content-Type: application/json"); /* Already signed? */
        if ($delivery["status"] === "signed_off") {
            echo json_encode([
                "success" => false,
                "error" => "This delivery has already been signed off.",
            ]);
            return;
        }
        $signoff_name = trim($_POST["signoff_name"] ?? "");
        $signature_data = $_POST["signature_data"] ?? "";
        if ($signoff_name === "") {
            echo json_encode([
                "success" => false,
                "error" => "Please enter your name.",
            ]);
            return;
        }
        if (
            $signature_data === "" ||
            !str_starts_with($signature_data, "data:image/png;base64,")
        ) {
            echo json_encode([
                "success" => false,
                "error" => "Please draw your signature.",
            ]);
            return;
        } /* Save signature image */
        $upload_dir = BASE_PATH . "/uploads/delivery_signoffs";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $filename = $delivery["id"] . "_signoff.png";
        $filepath = $upload_dir . "/" . $filename;
        $relative_path =
            "uploads/delivery_signoffs/" .
            $filename; /* Decode base64 and save */
        $image_data = base64_decode(
            str_replace("data:image/png;base64,", "", $signature_data),
        );
        if ($image_data === false) {
            echo json_encode([
                "success" => false,
                "error" => "Invalid signature data.",
            ]);
            return;
        }
        file_put_contents($filepath, $image_data); /* Update delivery record */
        $signoff_ip =
            $_SERVER["HTTP_X_FORWARDED_FOR"] ?? ($_SERVER["REMOTE_ADDR"] ?? "");
        // Only one sign-off counts: a second tap finds it already signed.
        $signed = $this->db->prepare(
            " UPDATE deliveries SET status = 'signed_off', signoff_name = ?, signoff_signature_path = ?, signoff_timestamp = NOW(), signoff_ip = ?, actual_delivery = COALESCE(actual_delivery, NOW()) WHERE id = ? AND status <> 'signed_off' ",
        );
        $signed->execute([
            $signoff_name,
            $relative_path,
            $signoff_ip,
            $delivery["id"],
        ]);
        if ($signed->rowCount() !== 1) {
            echo json_encode([
                "success" => false,
                "error" => "This delivery has already been signed off.",
            ]);
            return;
        }
        notify("delivery.signed", (int) $delivery["business_id"], [
            "delivery_id" => (int) $delivery["id"], "delivery_number" => $delivery["doc_number"], "signer" => $signoff_name,
        ], ["dedupe" => (string) $delivery["id"]]);
        /* The order behind this delivery is done: the shopper can now review it */
        $review_link = null;
        try {
            $stmtOrder = $this->db->prepare(
                "SELECT id FROM orders WHERE delivery_id = ? AND business_id = ? LIMIT 1",
            );
            $stmtOrder->execute([$delivery["id"], $delivery["business_id"]]);
            $order_id = (int) $stmtOrder->fetchColumn();
            if ($order_id) {
                shop_order_tell($this->db, $order_id, "delivered");
            }
            $review = $order_id ? review_invite($this->db, $order_id) : null;
            if ($review && $review["status"] === "invited" && $business) {
                $review_link = review_url($business, $review);
            }
        } catch (\Throwable $e) {
            error_log("Review invite failed: " . $e->getMessage());
        }
        echo json_encode(["success" => true, "review_url" => $review_link]);
    } /* ────────────────────────────────────────── * OLD SHOP LINKS /biz/{slug} (retired) * *──────────────────────────────────────────*/
    public function business($slug = null): void
    {
        /* Every shop now has its own address, which its owner chooses at
           sign-in. Old /biz/ links are not redirected. */
        $this->notice([
            "title" => "This shop link no longer works",
            "body" =>
                "Shops on Nfelia now have their own address, like yourshop." .
                shop_base_host() .
                ". Ask the shop for its new link.",
        ]);
    } /* ────────────────────────────────────────── * SHOP PAGE name.nfelia.com (via StorefrontController) * $visibility is 'live' or 'unlisted': unlisted shops stay out of * search engines until their first payment * *──────────────────────────────────────────*/
    protected function render_shop(array $business, string $visibility): void
    {
        if ($visibility !== "live") {
            header("X-Robots-Tag: noindex, nofollow");
        }
        /* The page is the same for every shopper and sets no cookie, so
           Cloudflare may keep it for five minutes (browsers check back every
           time). Publishing or editing products purges it sooner. */
        header("Cache-Control: public, max-age=0, s-maxage=300");
        storefront_render(
            $business,
            storefront_settings($business, "published"),
            "live",
            $visibility,
        );
    } /* ────────────────────────────────────────── * API: Generate Server-Side PDF * GET /doc/{token}?format=pdf * *──────────────────────────────────────────*/
    public function download_pdf(string $token): void
    {
        if ($_SERVER["REQUEST_METHOD"] !== "GET") {
            http_response_code(405);
            exit();
        } /* Try each document type by token */
        $template = null;
        $filename = "Document.pdf";
        $html = ""; /* 1. Quotation? */
        $stmt = $this->db->prepare(
            "SELECT * FROM quotations WHERE token = ? LIMIT 1",
        );
        $stmt->execute([$token]);
        $quotation = $stmt->fetch();
        if ($quotation) {
            $business = $this->get_business($quotation["business_id"]);
            $customer = $quotation["customer_id"]
                ? $this->get_customer($quotation["customer_id"])
                : null;
            $bank = $quotation["bank_account_id"]
                ? $this->get_bank($quotation["bank_account_id"])
                : null;
            $stmtI = $this->db->prepare(
                "SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY sort_order ASC",
            );
            $stmtI->execute([$quotation["id"]]);
            $items = $stmtI->fetchAll();
            ob_start();
            require BASE_PATH . "/views/quotations/pdf_template.php";
            $html = ob_get_clean();
            $filename = "Quotation_" . $quotation["doc_number"] . ".pdf";
        } /* 2. Invoice? */
        if (!$html) {
            $stmt = $this->db->prepare(
                "SELECT * FROM invoices WHERE token = ? LIMIT 1",
            );
            $stmt->execute([$token]);
            $invoice = $stmt->fetch();
            if ($invoice) {
                $business = $this->get_business($invoice["business_id"]);
                $customer = $invoice["customer_id"]
                    ? $this->get_customer($invoice["customer_id"])
                    : null;
                $bank = $invoice["bank_account_id"]
                    ? $this->get_bank($invoice["bank_account_id"])
                    : null;
                $stmtI = $this->db->prepare(
                    " SELECT ii.*, p.sku as product_sku FROM invoice_items ii LEFT JOIN products p ON p.id = ii.product_id WHERE ii.invoice_id = ? ORDER BY ii.sort_order ASC ",
                );
                $stmtI->execute([$invoice["id"]]);
                $items = $stmtI->fetchAll(); /* Load payment history for the invoice */
                $stmtP = $this->db->prepare(
                    "SELECT * FROM payments WHERE invoice_id = ? ORDER BY payment_date ASC",
                );
                $stmtP->execute([$invoice["id"]]);
                $payments = $stmtP->fetchAll();
                ob_start();
                require BASE_PATH . "/views/invoices/pdf_template.php";
                $html = ob_get_clean();
                $filename = "Invoice_" . $invoice["doc_number"] . ".pdf";
            }
        } /* 3. Receipt? */
        if (!$html) {
            $stmt = $this->db->prepare(
                "SELECT * FROM receipts WHERE token = ? LIMIT 1",
            );
            $stmt->execute([$token]);
            $receipt = $stmt->fetch();
            if ($receipt) {
                $business = $this->get_business($receipt["business_id"]);
                $customer = $receipt["customer_id"]
                    ? $this->get_customer($receipt["customer_id"])
                    : null;
                $items = [];
                if (!$receipt["invoice_id"]) {
                    $stmtI = $this->db->prepare(
                        " SELECT ri.*, p.sku as product_sku FROM receipt_items ri LEFT JOIN products p ON p.id = ri.product_id WHERE ri.receipt_id = ? ORDER BY ri.sort_order ASC ",
                    );
                    $stmtI->execute([$receipt["id"]]);
                    $items = $stmtI->fetchAll();
                }
                $invoice = null;
                if ($receipt["invoice_id"]) {
                    $stmtInv = $this->db->prepare(
                        "SELECT id, doc_number, total FROM invoices WHERE id = ?",
                    );
                    $stmtInv->execute([$receipt["invoice_id"]]);
                    $invoice = $stmtInv->fetch();
                }
                ob_start();
                require BASE_PATH . "/views/receipts/pdf_template.php";
                $html = ob_get_clean();
                $filename = "Receipt_" . $receipt["doc_number"] . ".pdf";
            }
        }
        if (!$html) {
            not_found();
            return;
        } /* Configure dompdf */
        $options = new Options();
        $options->set("isHtml5ParserEnabled", true);
        $options->set("isRemoteEnabled", true);
        $options->set("defaultFont", "DejaVu Sans");
        $options->set("tempDir", BASE_PATH . "/storage/pdf");
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper("A4", "portrait");
        $dompdf->render();
        $dompdf->stream($filename, ["Attachment" => true]);
        exit();
    } /* ────────────────────────────────────────── * AJAX QUOTE REQUEST POST /public/quote-request * *──────────────────────────────────────────*/
    public function quote_request(): void
    {
        /* Retired with /biz: shops post to their own address now */
        json_response(
            [
                "error" =>
                    "This shop has moved to its own address. Reload the shop page and try again.",
            ],
            410,
        );
    }
    public function cart_checkout(): void
    {
        /* Retired with /biz: shops post to their own address now */
        json_response(
            [
                "error" =>
                    "This shop has moved to its own address. Reload the shop page and try again.",
            ],
            410,
        );
    } /* ────────────────────────────────────────── * QUOTE REQUEST POST name.nfelia.com/quote-request * The business comes from the shop link, never from the form * *──────────────────────────────────────────*/
    protected function send_quote_request(array $business): void
    {
        $business_id = (int) $business["id"];
        $name = trim($_POST["name"] ?? "");
        $phone = trim($_POST["phone"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $message = trim($_POST["message"] ?? "");
        $company_name = mb_substr(trim($_POST["company_name"] ?? ""), 0, 200);
        $quantity_range = mb_substr(trim($_POST["quantity_range"] ?? ""), 0, 200);
        $budget = mb_substr(trim($_POST["budget"] ?? ""), 0, 200);
        $timeline = mb_substr(trim($_POST["timeline"] ?? ""), 0, 200);
        $delivery_location = mb_substr(
            trim($_POST["delivery_location"] ?? ""),
            0,
            200,
        ); /* Validate */
        if ($name === "" || $phone === "" || $message === "") {
            json_response(
                ["error" => "Please fill in all required fields."],
                422,
            );
        }
        $problem = $this->contact_problem($name, $phone, $email);
        if ($problem !== null) {
            json_response(["error" => $problem], 422);
        }
        if (mb_strlen($message) > 5000) {
            json_response(
                ["error" => "Please keep your message under 5,000 characters."],
                422,
            );
        } /* Append extra RFQ fields to message */
        $extras = [];
        if ($company_name) {
            $extras[] = "Company: {$company_name}";
        }
        if ($quantity_range) {
            $extras[] = "Quantity: {$quantity_range}";
        }
        if ($budget) {
            $extras[] = "Budget: {$budget}";
        }
        if ($timeline) {
            $extras[] = "Timeline: {$timeline}";
        }
        if ($delivery_location) {
            $extras[] = "Delivery to: {$delivery_location}";
        }
        if (!empty($extras)) {
            $message .=
                "\n\n--- Additional Info ---\n" . implode("\n", $extras);
        } /* Rate limiting: max 5 requests per phone per business per day */
        $stmtRate = $this->db->prepare(
            " SELECT COUNT(*) FROM quote_requests WHERE business_id = ? AND phone = ? AND created_at >= CURDATE() ",
        );
        $stmtRate->execute([$business_id, $phone]);
        if ((int) $stmtRate->fetchColumn() >= 5) {
            json_response(
                [
                    "error" =>
                        "Too many requests today. Please try again tomorrow.",
                ],
                429,
            );
        } /* Store the request */
        $stmt = $this->db->prepare(
            " INSERT INTO quote_requests (business_id, name, phone, email, message, status, created_at) VALUES (?, ?, ?, ?, ?, 'new', NOW()) ",
        );
        $stmt->execute([
            $business_id,
            $name,
            $phone,
            $email ?: null,
            $message,
        ]);
        $request_id = (int) $this->db->lastInsertId();
        /* Also create/update customer record so business owner sees them in CRM */
        $stmtExist = $this->db->prepare(
            " SELECT id FROM customers WHERE business_id = ? AND phone = ? AND deleted_at IS NULL LIMIT 1 ",
        );
        $stmtExist->execute([$business_id, $phone]);
        $existing = $stmtExist->fetch();
        if (!$existing) {
            $this->db
                ->prepare(
                    " INSERT INTO customers (business_id, full_name, phone, email, notes) VALUES (?, ?, ?, ?, ?) ",
                )
                ->execute([
                    $business_id,
                    $name,
                    $phone,
                    $email ?: null,
                    "Auto-created from landing page quote request",
                ]);
        }
        // A sale waiting to happen: the team hears straight away, by phone
        // and email (quotations/requests/{id} shows it).
        notify("quote_request.new", $business_id, [
            "request_id" => $request_id, "name" => $name, "phone" => $phone, "email" => $email ?: "", "message" => $message,
        ], ["dedupe" => (string) $request_id]);
        json_response([
            "success" => true,
            "message" => "Quote request sent successfully.",
        ]);
    } /* ────────────────────────────────────────── * PLACE AN ORDER POST name.nfelia.com/checkout * Proof-of-payment and WhatsApp orders. Names and prices come from the * products table: the cart only says which products and how many, so * nobody can change a price by editing the page * *──────────────────────────────────────────*/
    protected function place_order(array $business): void
    {
        $business_id = (int) $business["id"];
        $channel = trim(
            $_POST["channel"] ?? "cart_pop",
        ); /* cart_pop | whatsapp */
        $fulfillment = trim($_POST["fulfillment_type"] ?? "pickup");
        $customer_name = trim($_POST["customer_name"] ?? "");
        $customer_phone = trim($_POST["customer_phone"] ?? "");
        $customer_email = trim($_POST["customer_email"] ?? "");
        $delivery_address = mb_substr(
            trim($_POST["delivery_address"] ?? ""),
            0,
            500,
        );
        $customer_note = mb_substr(trim($_POST["customer_note"] ?? ""), 0, 1000);
        $payment_method = trim($_POST["payment_method"] ?? "none");
        $payment_note = mb_substr(trim($_POST["payment_note"] ?? ""), 0, 1000);
        /* Validate */
        if ($customer_name === "" || $customer_phone === "") {
            json_response(["error" => "Name and phone are required."], 422);
        }
        $problem = $this->contact_problem(
            $customer_name,
            $customer_phone,
            $customer_email,
        );
        if ($problem !== null) {
            json_response(["error" => $problem], 422);
        }
        if (!in_array($channel, ["cart_pop", "whatsapp"])) {
            $channel = "cart_pop";
        }
        if (!in_array($fulfillment, ["delivery", "pickup"])) {
            $fulfillment = "pickup";
        }
        if (
            !in_array($payment_method, [
                "bank_transfer",
                "mobile_money",
                "none",
            ])
        ) {
            $payment_method = "none";
        } /* WhatsApp orders always have payment_method = none */
        if ($channel === "whatsapp") {
            $payment_method = "none";
        } /* Parse the cart: which products, how many, and the shopper's choices */
        $items = json_decode($_POST["items"] ?? "[]", true);
        $lines = [];
        foreach (is_array($items) ? array_slice($items, 0, 50) : [] as $item) {
            $product_id = is_array($item) ? (int) ($item["product_id"] ?? 0) : 0;
            if ($product_id <= 0) {
                continue;
            }
            $lines[] = [
                "product_id" => $product_id,
                "quantity" => max(1, min(999, (int) ($item["quantity"] ?? 1))),
                "variant_selection" =>
                    mb_substr(trim((string) ($item["variant_selection"] ?? "")), 0, 255) ?: null,
                "item_note" =>
                    mb_substr(trim((string) ($item["item_note"] ?? "")), 0, 255) ?: null,
            ];
        }
        if (empty($lines)) {
            json_response(["error" => "Cart is empty."], 422);
        } /* The real name and price of every product, from this shop only */
        $product_ids = array_values(array_unique(array_column($lines, "product_id")));
        $placeholders = implode(",", array_fill(0, count($product_ids), "?"));
        $stmtProducts = $this->db->prepare(
            "SELECT id, name, sell_price FROM products WHERE business_id = ? AND deleted_at IS NULL AND is_active = 1 AND id IN ({$placeholders})",
        );
        $stmtProducts->execute(array_merge([$business_id], $product_ids));
        $catalogue = [];
        foreach ($stmtProducts->fetchAll() as $product) {
            $catalogue[(int) $product["id"]] = $product;
        }
        $subtotal = 0;
        $order_items = [];
        foreach ($lines as $line) {
            $product = $catalogue[$line["product_id"]] ?? null;
            if (!$product) {
                continue; /* removed or hidden since the page was loaded */
            }
            $price = (float) $product["sell_price"];
            $line_total = $price * $line["quantity"];
            $subtotal += $line_total;
            $order_items[] = [
                "product_id" => $line["product_id"],
                "product_name" => mb_substr($product["name"], 0, 150),
                "unit_price" => $price,
                "quantity" => $line["quantity"],
                "variant_selection" => $line["variant_selection"],
                "item_note" => $line["item_note"],
                "line_total" => $line_total,
            ];
        }
        if (empty($order_items)) {
            json_response(
                [
                    "error" =>
                        "These items are no longer available. Reload the page to see what's in the shop now.",
                ],
                422,
            );
        } /* Rate limiting: max 10 orders per phone per business per day */
        $stmtRate = $this->db->prepare(
            " SELECT COUNT(*) FROM orders WHERE business_id = ? AND customer_phone = ? AND created_at >= CURDATE() ",
        );
        $stmtRate->execute([$business_id, $customer_phone]);
        if ((int) $stmtRate->fetchColumn() >= 10) {
            json_response(
                [
                    "error" =>
                        "Too many orders today. Please try again tomorrow.",
                ],
                429,
            );
        } /* Handle payment proof upload */
        $proof_path = null;
        if (
            $payment_method !== "none" &&
            !empty($_FILES["payment_proof"]["tmp_name"])
        ) {
            $dest_dir = BASE_PATH . "/uploads/order_proofs/" . $business_id;
            if (!is_dir($dest_dir)) {
                mkdir($dest_dir, 0755, true);
            }
            $filename = time() . "_" . bin2hex(random_bytes(4)) . ".png";
            $dest = $dest_dir . "/" . $filename;
            if (save_image($_FILES["payment_proof"], $dest, 1200)) {
                $proof_path =
                    "uploads/order_proofs/" . $business_id . "/" . $filename;
            }
        }
        $this->db->beginTransaction();
        $committed = false;
        try {
            /* Generate order number (ORD-0001) */
            $this->db
                ->prepare(
                    "UPDATE businesses SET order_number_seq = order_number_seq + 1 WHERE id = ?",
                )
                ->execute([$business_id]);
            $stmtSeq = $this->db->prepare(
                "SELECT order_number_seq FROM businesses WHERE id = ?",
            );
            $stmtSeq->execute([$business_id]);
            $seq = (int) $stmtSeq->fetchColumn();
            $order_number =
                "ORD-" . str_pad((string) $seq, 4, "0", STR_PAD_LEFT);
            $status =
                $channel === "whatsapp"
                    ? "pending"
                    : "pending_verification";
            /* The shopper's private tracking link: name.nfelia.com/order/{token} */
            $public_token = shop_order_token(); /* Insert order */
            $this->db
                ->prepare(
                    " INSERT INTO orders (business_id, order_number, public_token, channel, fulfillment_type, customer_name, customer_phone, customer_email, delivery_address, customer_note, payment_method, payment_proof_path, payment_note, currency_code, subtotal, total, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ",
                )
                ->execute([
                    $business_id,
                    $order_number,
                    $public_token,
                    $channel,
                    $fulfillment,
                    $customer_name,
                    $customer_phone,
                    $customer_email ?: null,
                    $delivery_address ?: null,
                    $customer_note ?: null,
                    $payment_method,
                    $proof_path,
                    $payment_note ?: null,
                    business_currency_code($business),
                    $subtotal,
                    $subtotal,
                    $status,
                ]);
            $order_id = (int) $this->db->lastInsertId(); /* Insert order items */
            $stmtItem = $this->db->prepare(
                " INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, variant_selection, item_note, line_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ",
            );
            foreach ($order_items as $oi) {
                $stmtItem->execute([
                    $order_id,
                    $oi["product_id"],
                    $oi["product_name"],
                    $oi["unit_price"],
                    $oi["quantity"],
                    $oi["variant_selection"],
                    $oi["item_note"],
                    $oi["line_total"],
                ]);
            } /* Match or create customer */
            $stmtExist = $this->db->prepare(
                "SELECT id FROM customers WHERE business_id = ? AND phone = ? AND deleted_at IS NULL LIMIT 1",
            );
            $stmtExist->execute([$business_id, $customer_phone]);
            $existing_cust = $stmtExist->fetch();
            $customer_id = null;
            if ($existing_cust) {
                $customer_id = (int) $existing_cust["id"];
            } else {
                $this->db
                    ->prepare(
                        "INSERT INTO customers (business_id, full_name, phone, email, notes) VALUES (?, ?, ?, ?, ?)",
                    )
                    ->execute([
                        $business_id,
                        $customer_name,
                        $customer_phone,
                        $customer_email ?: null,
                        "Auto-created from storefront order",
                    ]);
                $customer_id = (int) $this->db->lastInsertId();
            }
            $this->db
                ->prepare("UPDATE orders SET customer_id = ? WHERE id = ?")
                ->execute([
                    $customer_id,
                    $order_id,
                ]);
            $this->db->commit();
            $committed = true;
            // The whole team hears about it (staff without the amount), by
            // phone and email. Anyone can place an order, so past 30 in an
            // hour the rest wait quietly in the bell.
            notify("order.new", $business_id, [
                "order_id" => $order_id, "order_number" => $order_number, "customer" => $customer_name,
                "phone" => $customer_phone, "items" => array_sum(array_column($order_items, "quantity")),
                "total" => (float) $subtotal, "channel" => $channel, "fulfillment" => $fulfillment,
                "address" => $delivery_address, "note" => $customer_note,
            ], [
                "business" => $business, "dedupe" => (string) $order_id,
                "channels" => throttle_allows("order_alert", (string) $business_id, 30, 60) ? ["push", "email"] : [],
            ]);
            $order_url = $business["subdomain"]
                ? shop_url($business["subdomain"], "/order/" . $public_token)
                : null;
            /* The shopper's copy of the order and its link, sent after they have
               their answer, so the mail server never holds up the checkout */
            if ($customer_email !== "" && $order_url !== null) {
                $mail_vars = [
                    "first_name" => preg_split("/\s+/u", $customer_name)[0],
                    "shop_name" => (string) $business["name"],
                    "order_number" => $order_number,
                    "order_url" => $order_url,
                    "lines" => array_map(
                        fn($oi) => [
                            "name" => $oi["product_name"] . ($oi["variant_selection"] ? " (" . $oi["variant_selection"] . ")" : ""),
                            "quantity" => $oi["quantity"],
                            "total" => sf_money((float) $oi["line_total"], (string) ($business["currency_symbol"] ?? "")),
                        ],
                        $order_items,
                    ),
                    "total" => sf_money((float) $subtotal, (string) ($business["currency_symbol"] ?? "")),
                    "is_whatsapp" => $channel === "whatsapp",
                ];
                // Through the outbox, so a mail server that is down is tried again.
                notify_email($business_id, $customer_email, $customer_name, "Your order " . $order_number . " from " . $business["name"],
                    "order_received", $mail_vars, "order-received:" . $order_id, "shopper.order_received");
            }
            json_response([
                "success" => true,
                "order_number" => $order_number,
                "order_url" => $order_url,
                "total" => $subtotal,
                "channel" => $channel,
            ]);
        } catch (\Throwable $e) {
            error_log("Cart checkout failed: " . $e->getMessage());
            if ($committed) {
                // The order is placed; only something after it went wrong.
                // Saying it failed would make the shopper order twice.
                json_response(["success" => true, "order_number" => $order_number, "order_url" => null, "total" => $subtotal, "channel" => $channel]);
            }
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            json_response(
                [
                    "error" =>
                        "We couldn't place your order just now. Please try again, or order on WhatsApp.",
                ],
                500,
            );
        }
    }
    /**
     * Why a shopper's contact details can't be saved, or null when they can.
     * The limits match the customers table, where every order and quote
     * request also lands (its phone column holds 20 characters).
     */
    protected function contact_problem(
        string $name,
        string $phone,
        string $email,
    ): ?string {
        if (mb_strlen($name) > 100) {
            return "Please keep your name under 100 characters.";
        }
        $digits = preg_replace("/\D/", "", $phone);
        if (strlen($phone) > 20 || strlen($digits) < 7 || strlen($digits) > 15) {
            return "Enter a phone number we can reach you on, like 0803 123 4567.";
        }
        if (
            $email !== "" &&
            (strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL))
        ) {
            return "Check the email address, or leave it empty.";
        }
        return null;
    }
    /**
     * A small standalone page for links that don't lead anywhere: missing
     * documents, retired shop links, closed shops. Always noindex.
     */
    protected function notice(array $notice): void
    {
        require BASE_PATH . "/views/public/notice.php";
    }
}
