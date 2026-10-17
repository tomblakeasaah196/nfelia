<?php
/**
 * Nfelia — Public Controller (No Authentication Required)
 *
 * Handles all public-facing routes:
 * /doc/{token} → Public document view (quotation, invoice, or receipt)
 * /delivery/{token} → Public delivery note (business view)
 * /sign/{signoff_token} → Recipient sign-off page (GET = form, POST = submit)
 * /biz/{slug} → Business landing page
 * /public/quote-request → AJAX quote request from landing page
 */
use Dompdf\Dompdf;
use Dompdf\Options;
class PublicController
{
    private $db;
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
        http_response_code(404);
        echo '<!DOCTYPE html><html><head><title>Document Not Found</title><script src="https://cdn.tailwindcss.com></script></head>';
        echo '<body class="min-h-screen flex items-center justify-center bg-gray-50">';
        echo '<div class="text-center"><h1 class="text-2xl font-bold text-gray-800 mb-2">Document Not Found</h1>';
        echo '<p class="text-sm text-gray-500">This link may have expired or is invalid.</p></div>';
        echo "</body></html>";
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
                $db->prepare(
                    "UPDATE quotations SET status = 'in_negotiation' WHERE id = ?",
                )->execute([
                    $quotation["id"],
                ]); /* NEW: Fire Notification to Business Owner */
                $db->prepare(
                    " INSERT INTO notifications (business_id, type, title, message, action_url, created_at) VALUES (?, 'general', ?, ?, ?, NOW()) ",
                )->execute([
                    $quotation["business_id"],
                    "New Client Comment",
                    "A client left a comment on Quotation " .
                    $quotation["doc_number"],
                    "/quotations/view/" . $quotation["id"],
                ]);
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
                ]); /* NEW: Fire Notification to Business Owner */
                $db->prepare(
                    " INSERT INTO notifications (business_id, type, title, message, action_url, created_at) VALUES (?, 'general', ?, ?, ?, NOW()) ",
                )->execute([
                    $quotation["business_id"],
                    "Proposal Accepted! 🎉",
                    $name .
                    " signed and accepted Quotation " .
                    $quotation["doc_number"],
                    "/quotations/view/" . $quotation["id"],
                ]);
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
            } else {
                throw new Exception("Invalid action requested.");
            }
            $db->commit();
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
            http_response_code(404);
            echo '<!DOCTYPE html><html><head><title>Not Found</title><script src="https://cdn.tailwindcss.com></script></head>';
            echo '<body class="min-h-screen flex items-center justify-center bg-gray-50">';
            echo '<div class="text-center"><h1 class="text-2xl font-bold text-gray-800 mb-2">Delivery Not Found</h1>';
            echo '<p class="text-sm text-gray-500">This link may have expired or is invalid.</p></div>';
            echo "</body></html>";
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
            http_response_code(404);
            echo '<!DOCTYPE html><html><head><title>Not Found</title><script src="https://cdn.tailwindcss.com></script></head>';
            echo '<body class="min-h-screen flex items-center justify-center bg-gray-50">';
            echo '<div class="text-center"><h1 class="text-2xl font-bold text-gray-800 mb-2">Link Not Found</h1>';
            echo '<p class="text-sm text-gray-500">This sign-off link may have expired or is invalid.</p></div>';
            echo "</body></html>";
            return;
        }
        $business = $this->get_business($delivery["business_id"]);
        $stmtItems = $this->db->prepare(
            " SELECT di.*, p.name AS product_name FROM delivery_items di LEFT JOIN products p ON p.id = di.product_id WHERE di.delivery_id = ? ",
        );
        $stmtItems->execute([$delivery["id"]]);
        $items = $stmtItems->fetchAll();
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
        $this->db
            ->prepare(
                " UPDATE deliveries SET status = 'signed_off', signoff_name = ?, signoff_signature_path = ?, signoff_timestamp = NOW(), signoff_ip = ?, actual_delivery = COALESCE(actual_delivery, NOW()) WHERE id = ? ",
            )
            ->execute([
                $signoff_name,
                $relative_path,
                $signoff_ip,
                $delivery["id"],
            ]); /* Create notification for business owner */
        try {
            $this->db
                ->prepare(
                    " INSERT INTO notifications (business_id, type, title, message, action_url, created_at) VALUES (?, 'delivery_signed', ?, ?, ?, NOW()) /* NFELIA AUDIT FIX C07 2026-06-03 */ ",
                )
                ->execute([
                    $delivery["business_id"],
                    "Delivery Signed Off",
                    $delivery["doc_number"] .
                    " was confirmed by " .
                    $signoff_name,
                    "/deliveries/view/" . $delivery["id"],
                ]);
        } catch (\Exception $e) {
            /* Non-critical — don't fail the sign-off if notification insert fails */
        }
        echo json_encode(["success" => true]);
    } /* ────────────────────────────────────────── * BUSINESS LANDING PAGE /biz/{slug} * *──────────────────────────────────────────*/
    public function business(string $slug): void
    {
        /* Find business by slug */ $stmt = $this->db->prepare(
            " SELECT * FROM businesses WHERE slug = ? AND landing_enabled = 1 LIMIT 1 ",
        );
        $stmt->execute([$slug]);
        $business = $stmt->fetch();
        if (!$business) {
            http_response_code(404);
            echo '<!DOCTYPE html><html><head><title>Not Found</title><script src="https://cdn.tailwindcss.com></script></head>';
            echo '<body class="min-h-screen flex items-center justify-center bg-gray-50">';
            echo '<div class="text-center"><h1 class="text-2xl font-bold text-gray-800 mb-2">Page Not Found</h1>';
            echo '<p class="text-sm text-gray-500">This business page doesn\'t exist or has been disabled.</p></div>';
            echo "</body></html>";
            return;
        } /* Featured product filter */
        $featured_ids =
            json_decode($business["featured_product_ids"] ?? "[]", true) ?: [];
        $featured_filter = "";
        $featured_params = [$business["id"]];
        if (!empty($featured_ids)) {
            $placeholders = implode(
                ",",
                array_fill(0, count($featured_ids), "?"),
            );
            $featured_filter = " AND id IN ({$placeholders})";
            $featured_params = array_merge($featured_params, $featured_ids);
        } /* Products (active, non-deleted, not services) */
        $stmtProd = $this->db->prepare(
            " SELECT id, name, description, category, image_path, sell_price, unit, is_service FROM products WHERE business_id = ? AND deleted_at IS NULL AND is_active = 1 AND is_service = 0{$featured_filter} ORDER BY category ASC, name ASC ",
        );
        $stmtProd->execute($featured_params);
        $products = $stmtProd->fetchAll(); /* Services (active, non-deleted, is_service = 1) */
        $stmtServ = $this->db->prepare(
            " SELECT id, name, description, category, image_path, sell_price, unit, is_service FROM products WHERE business_id = ? AND deleted_at IS NULL AND is_active = 1 AND is_service = 1{$featured_filter} ORDER BY category ASC, name ASC ",
        );
        $stmtServ->execute($featured_params);
        $services = $stmtServ->fetchAll(); /* YouTube embed ID */
        $youtube_id = null;
        if (!empty($business["youtube_url"])) {
            if (
                preg_match(
                    "/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([\w\-]{11})/",
                    $business["youtube_url"],
                    $ym,
                )
            ) {
                $youtube_id = $ym[1];
            }
        } /* Unique categories */
        $categories = [];
        foreach (array_merge($products, $services) as $item) {
            if (
                !empty($item["category"]) &&
                !in_array($item["category"], $categories)
            ) {
                $categories[] = $item["category"];
            }
        }
        sort($categories); /* Default bank account for payment info */
        $stmtBank = $this->db->prepare(
            " SELECT * FROM bank_accounts WHERE business_id = ? AND is_default = 1 ORDER BY sort_order ASC LIMIT 1 ",
        );
        $stmtBank->execute([$business["id"]]);
        $bank = $stmtBank->fetch(); /* If no default bank, grab any first one */
        if (!$bank) {
            $stmtBank2 = $this->db->prepare(
                " SELECT * FROM bank_accounts WHERE business_id = ? ORDER BY sort_order ASC LIMIT 1 ",
            );
            $stmtBank2->execute([$business["id"]]);
            $bank = $stmtBank2->fetch();
        } /* All bank accounts for the checkout payment-details selector */
        $stmtBanks = $this->db->prepare(
            "SELECT * FROM bank_accounts WHERE business_id = ? ORDER BY is_default DESC, sort_order ASC",
        );
        $stmtBanks->execute([$business["id"]]);
        $all_banks = $stmtBanks->fetchAll(); /* Product images (for gallery modal) */
        $stmtImgs = $this->db->prepare(
            " SELECT product_id, image_path, sort_order, is_cover FROM product_images WHERE business_id = ? ORDER BY sort_order ASC ",
        );
        $stmtImgs->execute([$business["id"]]);
        $all_product_images = [];
        foreach ($stmtImgs->fetchAll() as $img) {
            $all_product_images[$img["product_id"]][] = $img;
        } /* Product variant groups + options (for dropdowns) */
        $stmtVars = $this->db->prepare(
            " SELECT pvg.id as group_id, pvg.product_id, pvg.name as group_name, pvo.id as option_id, pvo.label as option_label FROM product_variant_groups pvg LEFT JOIN product_variant_options pvo ON pvo.variant_group_id = pvg.id WHERE pvg.business_id = ? ORDER BY pvg.product_id, pvg.sort_order, pvo.sort_order ",
        );
        $stmtVars->execute([$business["id"]]);
        $all_variants = [];
        foreach ($stmtVars->fetchAll() as $row) {
            $pid = $row["product_id"];
            $gid = $row["group_id"];
            if (!isset($all_variants[$pid][$gid])) {
                $all_variants[$pid][$gid] = [
                    "name" => $row["group_name"],
                    "options" => [],
                ];
            }
            if ($row["option_label"]) {
                $all_variants[$pid][$gid]["options"][] = $row["option_label"];
            }
        } /* RFQ fields config */
        $rfq_fields =
            json_decode($business["rfq_fields"] ?? "{}", true) ?:
            []; /* Render standalone view */
        require_once BASE_PATH . "/views/public/business.php";
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
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            http_response_code(405);
            json_response(["error" => "Method not allowed"], 405);
            return;
        }
        $business_id = (int) ($_POST["business_id"] ?? 0);
        $name = trim($_POST["name"] ?? "");
        $phone = trim($_POST["phone"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $message = trim($_POST["message"] ?? "");
        $company_name = trim($_POST["company_name"] ?? "");
        $quantity_range = trim($_POST["quantity_range"] ?? "");
        $budget = trim($_POST["budget"] ?? "");
        $timeline = trim($_POST["timeline"] ?? "");
        $delivery_location = trim(
            $_POST["delivery_location"] ?? "",
        ); /* Validate */
        if (
            $business_id <= 0 ||
            $name === "" ||
            $phone === "" ||
            $message === ""
        ) {
            json_response(
                ["error" => "Please fill in all required fields."],
                422,
            );
            return;
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
        } /* Verify business exists */
        $stmtBiz = $this->db->prepare(
            "SELECT id, name FROM businesses WHERE id = ?",
        );
        $stmtBiz->execute([$business_id]);
        $biz = $stmtBiz->fetch();
        if (!$biz) {
            json_response(["error" => "Invalid business."], 404);
            return;
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
            return;
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
        ]); /* Also create/update customer record so business owner sees them in CRM */
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
        json_response([
            "success" => true,
            "message" => "Quote request sent successfully.",
        ]);
    } /* ────────────────────────────────────────── * STOREFRONT — Cart Checkout * POST /public/cart-checkout * Handles both POP (proof of payment) and WhatsApp orders * *──────────────────────────────────────────*/
    public function cart_checkout(): void
    {
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            http_response_code(405);
            json_response(["error" => "Method not allowed"], 405);
            return;
        }
        $business_id = (int) ($_POST["business_id"] ?? 0);
        $channel = trim(
            $_POST["channel"] ?? "cart_pop",
        ); /* cart_pop | whatsapp */
        $fulfillment = trim($_POST["fulfillment_type"] ?? "pickup");
        $customer_name = trim($_POST["customer_name"] ?? "");
        $customer_phone = trim($_POST["customer_phone"] ?? "");
        $customer_email = trim($_POST["customer_email"] ?? "");
        $delivery_address = trim($_POST["delivery_address"] ?? "");
        $customer_note = trim($_POST["customer_note"] ?? "");
        $payment_method = trim($_POST["payment_method"] ?? "none");
        $payment_note = trim($_POST["payment_note"] ?? "");
        $items_json = $_POST["items"] ?? "[]"; /* Validate */
        if (
            $business_id <= 0 ||
            $customer_name === "" ||
            $customer_phone === ""
        ) {
            json_response(["error" => "Name and phone are required."], 422);
            return;
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
        } /* Verify business */
        $stmtBiz = $this->db->prepare(
            "SELECT id, name, phone, currency_symbol FROM businesses WHERE id = ?",
        );
        $stmtBiz->execute([$business_id]);
        $biz = $stmtBiz->fetch();
        if (!$biz) {
            json_response(["error" => "Invalid business."], 404);
            return;
        } /* Parse cart items */
        $items = json_decode($items_json, true);
        if (empty($items) || !is_array($items)) {
            json_response(["error" => "Cart is empty."], 422);
            return;
        } /* Rate limiting: max 10 orders per phone per business per day */
        try {
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
                return;
            }
        } catch (\Throwable $e) {
            /* orders table may not exist yet — skip rate-limit check, proceed to main try block */
            error_log("Rate-limit check skipped: " . $e->getMessage());
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
                "ORD-" .
                str_pad(
                    (string) $seq,
                    4,
                    "0",
                    STR_PAD_LEFT,
                ); /* Calculate totals */
            $subtotal = 0;
            $order_items = [];
            foreach ($items as $item) {
                $qty = max(1, (int) ($item["quantity"] ?? 1));
                $price = (float) ($item["unit_price"] ?? ($item["price"] ?? 0));
                $line_total = $qty * $price;
                $subtotal += $line_total;
                $order_items[] = [
                    "product_id" => (int) ($item["product_id"] ?? 0) ?: null,
                    "product_name" => trim(
                        $item["product_name"] ?? ($item["name"] ?? "Item"),
                    ),
                    "unit_price" => $price,
                    "quantity" => $qty,
                    "variant_selection" =>
                        trim($item["variant_selection"] ?? "") ?: null,
                    "item_note" => trim($item["item_note"] ?? "") ?: null,
                    "line_total" => $line_total,
                ];
            }
            $status =
                $channel === "whatsapp"
                    ? "pending"
                    : "pending_verification"; /* Insert order */
            $this->db
                ->prepare(
                    " INSERT INTO orders (business_id, order_number, channel, fulfillment_type, customer_name, customer_phone, customer_email, delivery_address, customer_note, payment_method, payment_proof_path, payment_note, currency_code, subtotal, total, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ",
                )
                ->execute([
                    $business_id,
                    $order_number,
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
                    "XAF",
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
                ]); /* Notification to business owner */
            $this->db
                ->prepare(
                    " INSERT INTO notifications (business_id, type, title, message, action_url, created_at) VALUES (?, 'general', ?, ?, ?, NOW()) ",
                )
                ->execute([
                    $business_id,
                    "New Order — " . $order_number,
                    $customer_name .
                    " placed an order for " .
                    $this->format_money($subtotal) .
                    " via " .
                    ($channel === "whatsapp"
                        ? "WhatsApp"
                        : "storefront checkout") .
                    ".",
                    "/orders/view/" . $order_id,
                ]);
            $this->db->commit();
            // A new shop order: the owner's and admins' phones buzz. Anyone
            // can place one, so at most 20 buzzes an hour; the bell gets all.
            if (throttle_allows("order_push", (string) $business_id, 20, 60)) {
                notify_money_in(
                    (int) $business_id, null,
                    "New order: " . notify_money_text((float) $subtotal, $biz),
                    $customer_name . " ordered on your shop" . ($channel === "whatsapp" ? " through WhatsApp." : "."),
                    "orders/view/" . $order_id,
                );
            }
            json_response([
                "success" => true,
                "order_id" => $order_id,
                "order_number" => $order_number,
                "total" => $subtotal,
                "channel" => $channel,
                "business_name" => $biz["name"],
                "business_phone" => $biz["phone"],
            ]);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log("Cart checkout failed: " . $e->getMessage());
            json_response(
                ["error" => "Order submission failed: " . $e->getMessage()],
                500,
            );
        }
    }
    private function format_money(float $amount): string
    {
        return number_format($amount, 0, ".", ",");
    } /* ────────────────────────────────────────── * CRON: Auto-Expire Quotations * Accessed via /cron/expire/{secret_key} * *──────────────────────────────────────────*/
    public function cron_expire($secret): void
    {
        /* SECURITY: Change this to a random 32-character string */ $expected_secret =
            "aB9xP2mQ7vL5kZ8wN3cJ6yH1tR4fD0gE";
        if ($secret !== $expected_secret) {
            http_response_code(403);
            exit("Forbidden: Invalid Cron Secret");
        }
        try {
            /* Expire all quotes across ALL businesses that are past due */ $stmt = $this->db->prepare(
                " UPDATE quotations SET status = 'expired' WHERE status = 'sent' AND expiry_date IS NOT NULL AND expiry_date < CURDATE() ",
            );
            $stmt->execute();
            $affected = $stmt->rowCount(); /* Optional: Log it for your own server records */
            error_log("CRON SUCCESS: Expired {$affected} overdue quotations.");
            echo "Success: {$affected} quotations expired.";
        } catch (\Throwable $e) {
            error_log("CRON ERROR: " . $e->getMessage());
            http_response_code(500);
            echo "Failed.";
        }
    }
}
