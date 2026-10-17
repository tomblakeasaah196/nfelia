<?php
/**
 * Nfelia — Enterprise Data Purge Cockpit & Asset Cleanse Engine
 * Relatable SME Codename: "Workspace Purge Engine"
 *
 * Capabilities:
 *   1. Surgical Single Strike & Dual Batch Scanner (Time/Date/Module filtered)
 *   2. Impact Dependency Tree Scanner (Cascading mother + child records & disk asset mapping)
 *   3. Double-Lock Authorization Destroyer (Account Login Password + Custom Purge PIN)
 *   4. Instant Disk Asset Unlinking (/uploads/...)
 *   5. Immutable Indestructible Audit Log Table
 *
 * @access Primary Workspace Owner Only (users.role = "owner")
 */
class PurgeController
{
    private $db;
    private $biz_id;

    public function __construct()
    {
        if (!is_logged_in()) {
            redirect("login");
        }
        if (!is_owner()) {
            flash(
                "error",
                "Strict Security Guard: Only the Primary Workspace Owner has access to the Purge Engine.",
            );
            redirect("dashboard");
        }
        $this->db = get_db();
        $this->biz_id = (int) auth_business_id();
        $this->init_purge_storage();
    }

    /**
     * Auto-bootstrap dedicated security and immutable log tables
     */
    private function init_purge_storage(): void
    {
        $sql_security = "
            CREATE TABLE IF NOT EXISTS purge_security_config (
                business_id INT PRIMARY KEY,
                pin_hash VARCHAR(255) NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB COMMENT='Stores custom double-lock PIN for hard deletions';
        ";
        $sql_logs = "
            CREATE TABLE IF NOT EXISTS purge_audit_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                business_id INT NOT NULL,
                user_id INT NOT NULL,
                admin_name VARCHAR(100) NOT NULL,
                admin_ip VARCHAR(45) NOT NULL,
                target_module VARCHAR(50) NOT NULL,
                purge_mode VARCHAR(50) NOT NULL,
                deleted_summary VARCHAR(255) NOT NULL,
                record_count INT NOT NULL DEFAULT 1,
                snapshot_json LONGTEXT NOT NULL,
                purged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_biz_time (business_id, purged_at)
            ) ENGINE=InnoDB COMMENT='Immutable non-repudiation purge ledger';
        ";
        try {
            $this->db->exec($sql_security);
            $this->db->exec($sql_logs);
        } catch (\Throwable $e) {
            error_log(
                "[PurgeEngine] Bootstrap storage failed: " . $e->getMessage(),
            );
        }
    }

    /**
     * MASTER UI COCKPIT
     * GET /purge
     */
    public function index(): void
    {
        $user = auth_user();
        $business = auth_business();

        $tab = trim($_GET["tab"] ?? "console"); // console | logs | config

        // Fetch security status
        $stmtSec = $this->db->prepare(
            "SELECT pin_hash FROM purge_security_config WHERE business_id = ?",
        );
        $stmtSec->execute([$this->biz_id]);
        $has_pin = (bool) $stmtSec->fetchColumn();

        // Always fetch the latest 100 immutable ledger entries so Alpine UI tab transitions load instantly
        $stmtL = $this->db->prepare(
            "SELECT * FROM purge_audit_logs WHERE business_id = ? ORDER BY id DESC LIMIT 100",
        );
        $stmtL->execute([$this->biz_id]);
        $purge_logs = $stmtL->fetchAll();

        $title = "Data Purge Engine";
        $view_file = BASE_PATH . "/views/purge/index.php";
        require_once BASE_PATH . "/views/layouts/app.php";
    }

    /**
     * AJAX CONFIG POST — Save Custom PIN
     * POST /purge/save_pin
     */
    public function save_pin(): void
    {
        if ($_SERVER["REQUEST_METHOD"] !== "POST" || !is_ajax()) {
            json_response(["error" => "Invalid method"], 405);
            return;
        }
        $pin = trim($_POST["pin"] ?? "");
        $password = $_POST["password"] ?? "";

        if (strlen($pin) < 4 || strlen($pin) > 8 || !ctype_digit($pin)) {
            json_response(
                ["error" => "Security PIN must be 4 to 8 digits"],
                422,
            );
            return;
        }

        // Verify account login password
        $user = auth_user();
        $stmtU = $this->db->prepare(
            "SELECT password_hash FROM users WHERE id = ? AND business_id = ?",
        );
        $stmtU->execute([(int) $user["id"], $this->biz_id]);
        $hash = $stmtU->fetchColumn();

        if (!$hash || !password_verify($password, $hash)) {
            json_response(
                ["error" => "Account login password verification failed"],
                401,
            );
            return;
        }

        $pin_hash = password_hash($pin, PASSWORD_DEFAULT);
        $stmtS = $this->db->prepare("
            INSERT INTO purge_security_config (business_id, pin_hash) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE pin_hash = VALUES(pin_hash)
        ");
        $stmtS->execute([$this->biz_id, $pin_hash]);

        json_response([
            "success" => true,
            "message" => "Purge Security PIN updated successfully.",
        ]);
    }

    /**
     * AJAX GET — SCAN IMPACT TREE & CANDIDATES
     * GET /purge/scan?module=quotations&mode=exact&q=QUO-001
     * GET /purge/scan?module=quotations&mode=batch&date=2026-06-26&time_start=10:00&time_end=12:00
     */
    public function scan(): void
    {
        if (!is_ajax()) {
            json_response(["error" => "AJAX only"], 405);
            return;
        }
        $module = trim($_GET["module"] ?? "quotations"); // quotations, invoices, receipts, deliveries, orders, products, customers
        $mode = trim($_GET["mode"] ?? "exact"); // exact | batch

        $valid_modules = [
            "quotations",
            "invoices",
            "receipts",
            "deliveries",
            "orders",
            "products",
            "customers",
        ];
        if (!in_array($module, $valid_modules)) {
            json_response(["error" => "Unsupported target module"], 400);
            return;
        }

        $candidates = [];
        if ($mode === "exact") {
            $q = trim($_GET["q"] ?? "");
            if ($q === "") {
                json_response(
                    ["error" => "Please enter a document string"],
                    422,
                );
                return;
            }
            $candidates = $this->find_exact_candidates($module, $q);
        } else {
            $date = trim($_GET["date"] ?? date("Y-m-d"));
            $t_start = trim($_GET["time_start"] ?? "00:00");
            $t_end = trim($_GET["time_end"] ?? "23:59");
            $candidates = $this->find_batch_candidates(
                $module,
                $date,
                $t_start,
                $t_end,
            );
        }

        if (empty($candidates)) {
            json_response([
                "success" => true,
                "candidates" => [],
                "count" => 0,
            ]);
            return;
        }

        // Build impact tree map for selected candidates
        $briefing = [];
        foreach ($candidates as $row) {
            $briefing[] = $this->map_impact_tree($module, $row);
        }

        json_response([
            "success" => true,
            "candidates" => $briefing,
            "count" => count($briefing),
        ]);
    }

    /**
     * AJAX POST — SURGICAL HARD PURGE DESTROYER
     * POST /purge/destroy
     */
    public function destroy(): void
    {
        if ($_SERVER["REQUEST_METHOD"] !== "POST" || !is_ajax()) {
            json_response(["error" => "POST required"], 405);
            return;
        }
        $module = trim($_POST["module"] ?? "");
        $ids_raw = $_POST["ids"] ?? "[]";
        $password = $_POST["password"] ?? "";
        $pin = trim($_POST["pin"] ?? "");

        $ids = array_filter(
            array_map("intval", json_decode($ids_raw, true) ?: []),
        );
        if (empty($ids) || !$module) {
            json_response(["error" => "No valid purge targets provided"], 422);
            return;
        }

        // 1. Verify Account Login Password
        $user = auth_user();
        $stmtU = $this->db->prepare(
            "SELECT password_hash, full_name FROM users WHERE id = ? AND business_id = ?",
        );
        $stmtU->execute([(int) $user["id"], $this->biz_id]);
        $user_row = $stmtU->fetch();

        if (
            !$user_row ||
            !password_verify($password, $user_row["password_hash"])
        ) {
            json_response(
                [
                    "error" =>
                        "Security Alert: Account login password verification failed.",
                ],
                401,
            );
            return;
        }

        // 2. Verify God Mode PIN
        $stmtPin = $this->db->prepare(
            "SELECT pin_hash FROM purge_security_config WHERE business_id = ?",
        );
        $stmtPin->execute([$this->biz_id]);
        $pin_hash = $stmtPin->fetchColumn();

        if (!$pin_hash) {
            json_response(
                [
                    "error" =>
                        "Please configure your Purge Security PIN in Tab 3 before performing hard purges.",
                ],
                403,
            );
            return;
        }
        if (!password_verify($pin, $pin_hash)) {
            json_response(
                ["error" => "Security Alert: Purge Security PIN is incorrect."],
                401,
            );
            return;
        }

        // 3. Collect deep dependency snapshot & unlink disk files
        $snapshot = [];
        $deleted_doc_strings = [];
        $unlinked_files = 0;

        try {
            $this->db->beginTransaction();

            foreach ($ids as $target_id) {
                $tree = $this->execute_cascade_delete($module, $target_id);
                if ($tree) {
                    $snapshot[] = $tree["snapshot"];
                    $deleted_doc_strings[] = $tree["identifier"];
                    $unlinked_files += $tree["files_unlinked"];
                }
            }

            if (empty($deleted_doc_strings)) {
                $this->db->rollBack();
                json_response(
                    [
                        "error" =>
                            "Records could not be found or were already purged.",
                    ],
                    404,
                );
                return;
            }

            // Write immutable non-repudiation log
            $admin_ip =
                $_SERVER["HTTP_X_FORWARDED_FOR"] ??
                ($_SERVER["REMOTE_ADDR"] ?? "Unknown");
            $summary =
                "Purged " .
                count($deleted_doc_strings) .
                " {$module}: " .
                implode(", ", array_slice($deleted_doc_strings, 0, 5)) .
                (count($deleted_doc_strings) > 5 ? "..." : "");

            $stmtLog = $this->db->prepare("
                INSERT INTO purge_audit_logs (business_id, user_id, admin_name, admin_ip, target_module, purge_mode, deleted_summary, record_count, snapshot_json, purged_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmtLog->execute([
                $this->biz_id,
                (int) $user["id"],
                $user_row["full_name"],
                $admin_ip,
                $module,
                count($ids) > 1 ? "Batch Wipe" : "Surgical Strike",
                $summary,
                count($deleted_doc_strings),
                json_encode([
                    "purged_records" => $snapshot,
                    "files_unlinked" => $unlinked_files,
                ]),
            ]);

            $this->db->commit();
            json_response([
                "success" => true,
                "message" =>
                    "Hard purge complete. Wiped " .
                    count($deleted_doc_strings) .
                    " records + unlinked {$unlinked_files} disk files.",
                "purged_count" => count($deleted_doc_strings),
            ]);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log("[PurgeEngine] Purge failure: " . $e->getMessage());
            json_response(
                [
                    "error" =>
                        "Purge aborted by database guard: " . $e->getMessage(),
                ],
                500,
            );
        }
    }

    /* ══════════════════════════════════════════
     *  INTERNAL TREE SCANNER & CASCADER HELPERS
     *══════════════════════════════════════════*/

    private function find_exact_candidates(string $module, string $q): array
    {
        $field = in_array($module, ["products", "customers"])
            ? ($module === "customers"
                ? "full_name"
                : "name")
            : "doc_number";
        if ($module === "orders") {
            $field = "order_number";
        }
        $stmt = $this->db->prepare(
            "SELECT * FROM {$module} WHERE business_id = ? AND ({$field} = ? OR {$field} LIKE ?) LIMIT 20",
        );
        $stmt->execute([$this->biz_id, $q, "%{$q}%"]);
        return $stmt->fetchAll();
    }

    private function find_batch_candidates(
        string $module,
        string $date,
        string $t_start,
        string $t_end,
    ): array {
        $time_field = in_array($module, ["orders", "products", "customers"])
            ? "created_at"
            : ($module === "deliveries"
                ? "created_at"
                : "issue_date");
        $sql = "SELECT * FROM {$module} WHERE business_id = ?";
        $params = [$this->biz_id];

        if (
            in_array($module, ["orders", "deliveries", "products", "customers"])
        ) {
            $sql .= " AND created_at >= ? AND created_at <= ?";
            $params[] = "{$date} {$t_start}:00";
            $params[] = "{$date} {$t_end}:59";
        } else {
            $sql .= " AND issue_date = ?";
            $params[] = $date;
        }
        $sql .= " ORDER BY id DESC LIMIT 50";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function map_impact_tree(string $module, array $row): array
    {
        $id = (int) $row["id"];
        $identifier =
            $row["doc_number"] ??
            ($row["order_number"] ??
                ($row["full_name"] ?? ($row["name"] ?? "ID #{$id}")));
        $amount =
            (float) ($row["total"] ??
                ($row["amount"] ?? ($row["sell_price"] ?? 0)));
        $status = $row["status"] ?? "Active";

        $tree = [
            "id" => $id,
            "identifier" => $identifier,
            "module" => ucfirst($module),
            "created" => $row["created_at"] ?? ($row["issue_date"] ?? ""),
            "amount" => $amount,
            "status" => $status,
            "children" => [],
            "disk_files" => [],
        ];

        // Map dependencies based on module
        if ($module === "quotations") {
            // Find child invoices
            $s = $this->db->prepare(
                "SELECT id, doc_number, total, status FROM invoices WHERE quotation_id = ? AND business_id = ?",
            );
            $s->execute([$id, $this->biz_id]);
            foreach ($s->fetchAll() as $c) {
                $tree["children"][] = [
                    "type" => "Invoice",
                    "id" => $c["id"],
                    "name" => $c["doc_number"],
                    "val" => (float) $c["total"],
                    "status" => $c["status"],
                ];
            }
        } elseif ($module === "invoices") {
            // Find child receipts
            $s = $this->db->prepare(
                "SELECT id, doc_number, amount FROM receipts WHERE invoice_id = ? AND business_id = ?",
            );
            $s->execute([$id, $this->biz_id]);
            foreach ($s->fetchAll() as $c) {
                $tree["children"][] = [
                    "type" => "Receipt",
                    "id" => $c["id"],
                    "name" => $c["doc_number"],
                    "val" => (float) $c["amount"],
                    "status" => "Paid",
                ];
            }
            // Find child deliveries
            $s2 = $this->db->prepare(
                "SELECT id, doc_number, status FROM deliveries WHERE invoice_id = ? AND business_id = ?",
            );
            $s2->execute([$id, $this->biz_id]);
            foreach ($s2->fetchAll() as $c) {
                $tree["children"][] = [
                    "type" => "Delivery Note",
                    "id" => $c["id"],
                    "name" => $c["doc_number"],
                    "val" => 0,
                    "status" => $c["status"],
                ];
            }
        } elseif ($module === "orders") {
            // Find physical proof
            if (!empty($row["payment_proof_path"])) {
                $tree["disk_files"][] = $row["payment_proof_path"];
            }
            if (!empty($row["invoice_id"])) {
                $tree["children"][] = [
                    "type" => "Linked Invoice",
                    "id" => $row["invoice_id"],
                    "name" => "INV #{$row["invoice_id"]}",
                    "val" => $amount,
                    "status" => "Linked",
                ];
            }
        } elseif ($module === "deliveries") {
            if (!empty($row["signoff_signature_path"])) {
                $tree["disk_files"][] = $row["signoff_signature_path"];
            }
        } elseif ($module === "products") {
            if (!empty($row["image_path"])) {
                $tree["disk_files"][] = $row["image_path"];
            }
        }

        return $tree;
    }

    private function execute_cascade_delete(
        string $module,
        int $target_id,
    ): ?array {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$module} WHERE id = ? AND business_id = ?",
        );
        $stmt->execute([$target_id, $this->biz_id]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $impact = $this->map_impact_tree($module, $row);
        $files_unlinked = 0;

        // Unlink physical disk files
        foreach ($impact["disk_files"] as $rel_path) {
            $full_path = BASE_PATH . "/" . ltrim($rel_path, "/");
            if (file_exists($full_path) && is_file($full_path)) {
                @unlink($full_path);
                $files_unlinked++;
            }
        }

        // Execute deep cascades
        if ($module === "quotations") {
            $this->db
                ->prepare("DELETE FROM quotation_items WHERE quotation_id = ?")
                ->execute([$target_id]);
            // Unlink child invoice fk
            $this->db
                ->prepare(
                    "UPDATE invoices SET quotation_id = NULL WHERE quotation_id = ?",
                )
                ->execute([$target_id]);
        } elseif ($module === "invoices") {
            $this->db
                ->prepare("DELETE FROM invoice_items WHERE invoice_id = ?")
                ->execute([$target_id]);
            // Delete child receipts
            $this->db
                ->prepare(
                    "DELETE FROM receipts WHERE invoice_id = ? AND business_id = ?",
                )
                ->execute([$target_id, $this->biz_id]);
            // Delete child deliveries & items
            $stmtD = $this->db->prepare(
                "SELECT id FROM deliveries WHERE invoice_id = ? AND business_id = ?",
            );
            $stmtD->execute([$target_id, $this->biz_id]);
            foreach ($stmtD->fetchAll() as $drow) {
                $this->db
                    ->prepare(
                        "DELETE FROM delivery_items WHERE delivery_id = ?",
                    )
                    ->execute([(int) $drow["id"]]);
            }
            $this->db
                ->prepare(
                    "DELETE FROM deliveries WHERE invoice_id = ? AND business_id = ?",
                )
                ->execute([$target_id, $this->biz_id]);
            // Unlink order references
            $this->db
                ->prepare(
                    "UPDATE orders SET invoice_id = NULL WHERE invoice_id = ?",
                )
                ->execute([$target_id]);
        } elseif ($module === "orders") {
            $this->db
                ->prepare("DELETE FROM order_items WHERE order_id = ?")
                ->execute([$target_id]);
        } elseif ($module === "deliveries") {
            $this->db
                ->prepare(
                    "DELETE FROM delivery_items WHERE delivery_id = ?",
                )
                ->execute([$target_id]);
            $this->db
                ->prepare(
                    "UPDATE orders SET delivery_id = NULL WHERE delivery_id = ?",
                )
                ->execute([$target_id]);
        } elseif ($module === "receipts") {
            $this->db
                ->prepare(
                    "UPDATE orders SET receipt_id = NULL WHERE receipt_id = ?",
                )
                ->execute([$target_id]);
        }

        // Finally delete the mother target row
        $stmtDel = $this->db->prepare(
            "DELETE FROM {$module} WHERE id = ? AND business_id = ?",
        );
        $stmtDel->execute([$target_id, $this->biz_id]);

        return [
            "identifier" => $impact["identifier"],
            "snapshot" => $row,
            "files_unlinked" => $files_unlinked,
        ];
    }
}