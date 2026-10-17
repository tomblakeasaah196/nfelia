<?php
/**
 * Nfelia — Feedback Controller
 * Handles: submitting feedback (with optional image), checking triggers, dismissing prompts.
 */
class FeedbackController {

    private $db;

    public function __construct() {
        if (!is_logged_in()) { redirect('login'); }
        $this->db = get_db();
    }

    /**
     * POST — Submit feedback (AJAX)
     * Expects: rating, message (optional), trigger_action, image (optional file)
     */
    public function submit() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { json_response(['error' => 'Method not allowed'], 405); return; }
        csrf_verify();

        $user_id  = auth_user()['id'];
        $biz_id   = auth_business_id();
        $rating   = max(1, min(5, (int)($_POST['rating'] ?? 0)));
        $message  = trim($_POST['message'] ?? '');
        $trigger  = trim($_POST['trigger_action'] ?? 'manual');

        if ($rating < 1) {
            json_response(['error' => 'Please select a rating'], 422);
            return;
        }

        $allowed_triggers = ['first_invoice', 'first_delivery_signoff', 'first_receipt', 'first_quotation', 'manual'];
        if (!in_array($trigger, $allowed_triggers)) {
            $trigger = 'manual';
        }

        // Handle optional image upload
        $image_path = null;
        if (!empty($_FILES['feedback_image']['tmp_name']) && $_FILES['feedback_image']['error'] === UPLOAD_ERR_OK) {
            $image_path = $this->handle_image($_FILES['feedback_image']);
        }

        $stmt = $this->db->prepare("
            INSERT INTO feedback (business_id, user_id, trigger_action, rating, message, image_path, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$biz_id, $user_id, $trigger, $rating, $message ?: null, $image_path]);

        // Mark trigger as seen so it won't show again
        if ($trigger !== 'manual') {
            $this->mark_trigger_seen($user_id, $trigger);
        }

        json_response(['success' => true, 'message' => 'Thank you for your feedback!']);
    }

    /**
     * POST — Dismiss a feedback prompt without submitting (AJAX)
     */
    public function dismiss() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { json_response(['error' => 'Method not allowed'], 405); return; }
        csrf_verify();

        $user_id = auth_user()['id'];
        $trigger = trim($_POST['trigger_action'] ?? '');

        if ($trigger) {
            $this->mark_trigger_seen($user_id, $trigger);
        }

        json_response(['success' => true]);
    }

    /**
     * GET — Check if any feedback trigger should fire (AJAX, called from layout)
     * Returns trigger_action string or null.
     */
    public function check() {
        $user_id = auth_user()['id'];
        $biz_id  = auth_business_id();

        $pending = $this->get_pending_trigger($user_id, $biz_id);

        json_response(['trigger' => $pending]);
    }

    /* ── Helpers ────────────────────────────────── */

    private function mark_trigger_seen(int $user_id, string $trigger): void {
        try {
            $stmt = $this->db->prepare("
                INSERT IGNORE INTO feedback_triggers_seen (user_id, trigger_action, created_at)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$user_id, $trigger]);
        } catch (Exception $e) {
            // Non-critical
        }
    }

    /**
     * Determine if the user should see a feedback prompt.
     * Logic: check if the user did a trigger action AND hasn't been prompted yet.
     */
    private function get_pending_trigger(int $user_id, int $biz_id): ?string {
        // Get triggers already seen
        $stmt = $this->db->prepare("SELECT trigger_action FROM feedback_triggers_seen WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $seen = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Check first invoice
        if (!in_array('first_invoice', $seen)) {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM invoices WHERE business_id = ? LIMIT 1");
            $stmt->execute([$biz_id]);
            if ((int)$stmt->fetchColumn() >= 1) {
                return 'first_invoice';
            }
        }

        // Check first delivery sign-off
        if (!in_array('first_delivery_signoff', $seen)) {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM deliveries WHERE business_id = ? AND status = 'signed_off' LIMIT 1");
            $stmt->execute([$biz_id]);
            if ((int)$stmt->fetchColumn() >= 1) {
                return 'first_delivery_signoff';
            }
        }

        // Check first receipt
        if (!in_array('first_receipt', $seen)) {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM receipts WHERE business_id = ? LIMIT 1");
            $stmt->execute([$biz_id]);
            if ((int)$stmt->fetchColumn() >= 1) {
                return 'first_receipt';
            }
        }

        // Check first quotation
        if (!in_array('first_quotation', $seen)) {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM quotations WHERE business_id = ? LIMIT 1");
            $stmt->execute([$biz_id]);
            if ((int)$stmt->fetchColumn() >= 1) {
                return 'first_quotation';
            }
        }

        return null;
    }

    private function handle_image(array $file): ?string {
        $max_size = 2 * 1024 * 1024; // 2MB
        if ($file['size'] > $max_size) return null;

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

        if (!isset($allowed[$mime])) return null;

        $ext  = $allowed[$mime];
        $name = 'fb_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = 'uploads/feedback/' . $name;

        if (!is_dir('uploads/feedback')) {
            mkdir('uploads/feedback', 0755, true);
        }

        if (move_uploaded_file($file['tmp_name'], $dest)) {
            return $dest;
        }
        return null;
    }
}
