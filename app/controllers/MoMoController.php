<?php
/**
 * Nfelia — Mobile Money Controller (Phase 4)
 * Handles: MoMo checkout, receipt upload, admin review (approve/reject).
 *
 * Routes:
 *   GET  /momo/checkout          — Show MoMo payment screen (authenticated)
 *   POST /momo/submit            — Submit payment receipt (authenticated)
 *   GET  /admin/momo             — Admin: pending MoMo payments list
 *   POST /admin/momo/approve     — Approve a pending payment
 *   POST /admin/momo/reject      — Reject a pending payment (with reason)
 *
 * Files uploaded are stored OUTSIDE the webroot in /home/smartqaq/momo_receipts/
 * Only admins can view them via the /momo/receipt/{id} endpoint.
 */
class MoMoController {

    private $db;
    private $biz_id;
    private const FOUNDER_BUSINESS_ID = 1;

    /**
     * Path for receipt storage — reads from MOMO_RECEIPT_DIR env var.
     * Falls back to storage/momo_receipts/ (protected by .htaccess) if env not set.
     * For production: set MOMO_RECEIPT_DIR to a directory OUTSIDE public_html.
     */
    private function get_receipt_dir(): string {
        $dir = rtrim($_ENV['MOMO_RECEIPT_DIR'] ?? '', '/');
        if (!$dir || !is_dir($dir)) {
            $dir = BASE_PATH . '/storage/momo_receipts';
        }
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        return $dir . '/';
    }

    public function __construct() {
        if (!is_logged_in()) { redirect('login'); }
        $this->db     = get_db();
        $this->biz_id = auth_business_id();
    }

    /* ══════════════════════════════════════════
     *  CHECKOUT — GET /momo/checkout
     *  Shows the MoMo payment screen to the user.
     * ══════════════════════════════════════════ */
    public function checkout() {
        $user     = auth_user();
        $business = auth_business();

        require_once __DIR__ . '/CountryController.php';
        $country = CountryController::get_country_for_business($this->db, $this->biz_id);

        if (!$country || $country['payment_method'] !== 'mobile_money') {
            flash('error', 'Mobile Money is not available for your country.');
            redirect('settings?tab=billing');
            return;
        }

        // Get current subscription to know what they are paying for
        $stmt = $this->db->prepare("SELECT * FROM subscriptions WHERE business_id = ?");
        $stmt->execute([$this->biz_id]);
        $subscription = $stmt->fetch();
        $sub_status = $subscription['status'] ?? 'trial';

        $extra_seats = max(0, min(4, (int)($_GET['extra_seats'] ?? 0)));
        $amount_due  = $country['monthly_price'] + ($extra_seats * $country['extra_seat_price']);

        $title     = 'Complete Payment';
        $view_file = BASE_PATH . '/views/billing/momo_checkout.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ══════════════════════════════════════════
     *  SUBMIT — POST /momo/submit
     *  Accepts the receipt upload and saves payment record.
     * ══════════════════════════════════════════ */
    public function submit() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('momo/checkout'); return; }
        csrf_verify();

        $user     = auth_user();
        $business = auth_business();

        require_once __DIR__ . '/CountryController.php';
        $country = CountryController::get_country_for_business($this->db, $this->biz_id);

        if (!$country || $country['payment_method'] !== 'mobile_money') {
            flash('error', 'Mobile Money is not available for your account.');
            redirect('settings?tab=billing');
            return;
        }

        $extra_seats    = max(0, min(4, (int)($_POST['extra_seats'] ?? 0)));
        $reference_num  = trim($_POST['reference_number'] ?? '');
        $amount_due     = $country['monthly_price'] + ($extra_seats * $country['extra_seat_price']);

        // ── Receipt file upload (REQUIRED)
        if (empty($_FILES['receipt']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Please upload a screenshot or photo of your payment receipt.');
            redirect('momo/checkout');
            return;
        }

        $file    = $_FILES['receipt'];
        $tmp     = $file['tmp_name'];
        $ext_map = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
        ];

        // Server-side MIME validation (never trust browser-reported type)
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->file($tmp);

        if (!array_key_exists($detected, $ext_map)) {
            flash('error', 'Invalid file type. Please upload a JPG, PNG, WebP, or PDF receipt.');
            redirect('momo/checkout');
            return;
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            flash('error', 'Receipt file is too large. Maximum size is 5MB.');
            redirect('momo/checkout');
            return;
        }

        // Save to secure non-web-accessible directory
        $receipt_dir = $this->get_receipt_dir();
        if (!is_dir($receipt_dir)) {
            if (!@mkdir($receipt_dir, 0750, true)) {
                // Fall back to storage/ inside app directory (still protected by .htaccess)
                $receipt_dir = BASE_PATH . '/storage/momo_receipts/';
                if (!is_dir($receipt_dir)) mkdir($receipt_dir, 0750, true);
            }
        }

        $filename     = 'momo_' . $this->biz_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext_map[$detected];
        $full_path    = rtrim($receipt_dir, '/') . '/' . $filename;
        $stored_path  = $receipt_dir . $filename; // full path stored in DB for admin retrieval

        if (!move_uploaded_file($tmp, $full_path)) {
            flash('error', 'Failed to save receipt. Please try again or contact support.');
            redirect('momo/checkout');
            return;
        }

        $this->db->beginTransaction();
        try {
            // Get or create subscription record
            $stmt = $this->db->prepare("SELECT * FROM subscriptions WHERE business_id = ?");
            $stmt->execute([$this->biz_id]);
            $sub = $stmt->fetch();

            if (!$sub) {
                $trial_end = date('Y-m-d', strtotime('+' . $country['trial_days'] . ' days'));
                $this->db->prepare("
                    INSERT INTO subscriptions (business_id, plan, status, user_seats, extra_seats, country_id, payment_method, trial_ends_at)
                    VALUES (?, 'standard', 'pending_verification', 1, ?, ?, 'mobile_money', ?)
                ")->execute([$this->biz_id, $extra_seats, $country['id'], $trial_end]);
                $sub_id = $this->db->lastInsertId();
            } else {
                $sub_id = $sub['id'];
                // Log old status
                $this->log_status_change($this->biz_id, $sub['status'], 'pending_verification', null, 'MoMo receipt submitted by user');
                $this->db->prepare("
                    UPDATE subscriptions
                    SET status = 'pending_verification', extra_seats = ?, country_id = ?, payment_method = 'mobile_money', updated_at = NOW()
                    WHERE id = ?
                ")->execute([$extra_seats, $country['id'], $sub_id]);
            }

            // Save the payment submission record
            $this->db->prepare("
                INSERT INTO subscription_payments
                    (business_id, subscription_id, country_id, amount, currency_code,
                     payment_method, reference_number, receipt_path, status, submitted_at)
                VALUES (?, ?, ?, ?, ?, 'mobile_money', ?, ?, 'pending_verification', NOW())
            ")->execute([
                $this->biz_id, $sub_id, $country['id'],
                $amount_due, $country['currency_code'],
                $reference_num ?: null, $stored_path,
            ]);
            $payment_id = $this->db->lastInsertId();

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log('[Nfelia] MoMo submit failed: ' . $e->getMessage());
            flash('error', 'A system error occurred. Please try again or contact support.');
            redirect('momo/checkout');
            return;
        }

        // The Nfelia team is told there's a payment to check, and the owner
        // gets a confirmation (in the app and by email: billing always emails).
        $amount_text = $country['currency_symbol'] . number_format($amount_due, 0) . ' ' . $country['currency_code'];
        // The Nfelia team checks it in Ops → Payments to check.
        ops_alert(get_db(), 'payment', 'Mobile money payment to check', $business['name'] . ' sent ' . $amount_text . ' by mobile money.',
                  'payments#payment-' . (int)$payment_id, 'payment:' . (int)$payment_id, ['permission' => 'clients.act']);
        notify('billing.momo_submitted', $this->biz_id, ['amount_text' => $amount_text], ['dedupe' => (string)$payment_id]);

        flash('success', 'Payment receipt submitted! We\'ll verify and activate your subscription within 24 hours.');
        redirect('settings?tab=billing');
    }

    /* ══════════════════════════════════════════
     *  CHECKING PAYMENTS: in Nfelia Ops now
     *  (ops.nfelia.com/payments, app/ops/controllers/OpsPaymentsController.php)
     * ══════════════════════════════════════════ */
    public function review()  { $this->moved(); }
    public function approve() { $this->moved(); }
    public function reject()  { $this->moved(); }
    public function receipt($id = null) { $this->moved(); }

    /* ══════════════════════════════════════════
     *  PRIVATE HELPERS
     * ══════════════════════════════════════════ */

    private function moved(): never {
        if ((int)$this->biz_id !== self::FOUNDER_BUSINESS_ID) {
            redirect('dashboard');
        }
        header('Location: ' . ops_url('payments'), true, 301);
        exit;
    }

    private function log_status_change(int $biz_id, ?string $old, string $new, ?int $changed_by, string $notes = ''): void {
        try {
            $this->db->prepare("
                INSERT INTO subscription_status_log (business_id, old_status, new_status, changed_by, notes)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([$biz_id, $old, $new, $changed_by, $notes]);
        } catch (\Exception $e) {
            error_log('[Nfelia] Status log failed: ' . $e->getMessage());
        }
    }
}
