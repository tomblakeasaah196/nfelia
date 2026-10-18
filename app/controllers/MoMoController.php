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

            // Notify admin (business_id = 1) — in-app
            try {
                $this->db->prepare("
                    INSERT INTO notifications (business_id, type, title, message, action_url, created_at)
                    VALUES (1, 'momo_submitted', 'New MoMo Payment Pending', ?, ?, NOW())
                ")->execute([
                    e($business['name']) . ' submitted a payment receipt of ' .
                    $country['currency_symbol'] . number_format($amount_due, 0) . ' ' . $country['currency_code'] .
                    ' — awaiting your review.',
                    'momo/review'
                ]);
            } catch (\Exception $e) {
                error_log('[Nfelia] MoMo admin notification failed: ' . $e->getMessage());
            }

            // Confirmation email to the user
            $confirm_msg = "Hello " . e($user['full_name']) . ",\n\n" .
                "We've received your payment receipt submission of " .
                $country['currency_symbol'] . number_format($amount_due, 0) . " " . $country['currency_code'] . ".\n\n" .
                "Our team will review and confirm your subscription within 24 hours. You will receive a notification once it's approved.\n\n" .
                "Reference: " . ($reference_num ?: 'Not provided') . "\n" .
                "Submitted: " . date('d M Y, H:i') . "\n\n" .
                "Thank you for choosing Nfelia.\n\nThe Nfelia Team";
            send_mail($user['email'], $user['full_name'], 'Payment Submission Received — Nfelia', $confirm_msg);

        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log('[Nfelia] MoMo submit failed: ' . $e->getMessage());
            flash('error', 'A system error occurred. Please try again or contact support.');
            redirect('momo/checkout');
            return;
        }

        flash('success', 'Payment receipt submitted! We\'ll verify and activate your subscription within 24 hours.');
        redirect('settings?tab=billing');
    }

    /* ══════════════════════════════════════════
     *  ADMIN: PENDING REVIEW LIST — GET /admin/momo
     * ══════════════════════════════════════════ */
    public function review() {
        $this->guard_admin();

        $user     = auth_user();
        $business = auth_business();

        $stmt = $this->db->query("
            SELECT sp.*,
                   b.name AS business_name, b.slug,
                   u.full_name AS owner_name, u.email AS owner_email,
                   c.name AS country_name, c.currency_symbol, c.currency_code, c.flag_emoji
            FROM subscription_payments sp
            JOIN businesses b ON b.id = sp.business_id
            JOIN users u ON u.business_id = b.id AND u.role = 'owner'
            JOIN countries c ON c.id = sp.country_id
            WHERE sp.status = 'pending_verification'
            ORDER BY sp.submitted_at ASC
        ");
        $pending_payments = $stmt->fetchAll();

        // Also fetch recently reviewed (last 20)
        $stmtR = $this->db->query("
            SELECT sp.*,
                   b.name AS business_name,
                   u.full_name AS owner_name,
                   c.name AS country_name, c.currency_symbol, c.currency_code, c.flag_emoji,
                   ru.full_name AS reviewer_name
            FROM subscription_payments sp
            JOIN businesses b ON b.id = sp.business_id
            JOIN users u ON u.business_id = b.id AND u.role = 'owner'
            JOIN countries c ON c.id = sp.country_id
            LEFT JOIN users ru ON ru.id = sp.reviewed_by
            WHERE sp.status IN ('approved', 'rejected')
            ORDER BY sp.reviewed_at DESC
            LIMIT 20
        ");
        $reviewed_payments = $stmtR->fetchAll();

        $tab       = 'momo';
        $title     = 'Admin — MoMo Payments';
        $view_file = BASE_PATH . '/views/admin/momo_review.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ══════════════════════════════════════════
     *  ADMIN: APPROVE — POST /admin/momo/approve
     * ══════════════════════════════════════════ */
    public function approve() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['error' => 'Method not allowed'], 405); return;
        }
        csrf_verify();
        $this->guard_admin();

        $payment_id = (int)($_POST['payment_id'] ?? 0);
        if (!$payment_id) { json_response(['error' => 'Invalid payment ID'], 400); return; }

        $stmt = $this->db->prepare("
            SELECT sp.*, b.name AS biz_name,
                   u.full_name AS owner_name, u.email AS owner_email,
                   c.currency_symbol, c.currency_code
            FROM subscription_payments sp
            JOIN businesses b ON b.id = sp.business_id
            JOIN users u ON u.business_id = b.id AND u.role = 'owner'
            JOIN countries c ON c.id = sp.country_id
            WHERE sp.id = ? AND sp.status = 'pending_verification'
        ");
        $stmt->execute([$payment_id]);
        $payment = $stmt->fetch();

        if (!$payment) { json_response(['error' => 'Payment not found or already reviewed'], 404); return; }

        $reviewer_id = auth_user()['id'];

        $this->db->beginTransaction();
        try {
            // Mark payment as approved
            $this->db->prepare("
                UPDATE subscription_payments
                SET status = 'approved', reviewed_at = NOW(), reviewed_by = ?
                WHERE id = ?
            ")->execute([$reviewer_id, $payment_id]);

            // Activate the subscription
            $period_start = date('Y-m-d');
            $period_end   = date('Y-m-d', strtotime('+1 month'));
            $grace_end    = date('Y-m-d H:i:s', strtotime('+1 month +3 days'));

            $this->db->prepare("
                UPDATE subscriptions SET
                    status = 'active', plan = 'standard',
                    monthly_amount = ?,
                    current_period_start = ?, current_period_end = ?, grace_ends_at = ?,
                    last_payment_date = CURDATE(), last_payment_ref = ?,
                    trial_ends_at = NULL, cancelled_at = NULL, cancel_reason = NULL,
                    updated_at = NOW()
                WHERE business_id = ?
            ")->execute([
                $payment['amount'],
                $period_start, $period_end, $grace_end,
                'MOMO_' . $payment_id,
                $payment['business_id'],
            ]);

            // Log status change
            $this->log_status_change($payment['business_id'], 'pending_verification', 'active', $reviewer_id, 'MoMo payment approved');

            // In-app notification to the business
            $this->db->prepare("
                INSERT INTO notifications (business_id, type, title, message, action_url, created_at)
                VALUES (?, 'momo_approved', 'Payment Approved!', ?, ?, NOW())
            ")->execute([
                $payment['business_id'],
                'Your payment of ' . $payment['currency_symbol'] . number_format($payment['amount'], 0) .
                ' has been verified. Your Nfelia subscription is now active!',
                APP_URL . '/settings?tab=billing'
            ]);

            $this->db->commit();

            // Email the customer
            $msg = "Hello " . $payment['owner_name'] . ",\n\n" .
                "Your payment of " . $payment['currency_symbol'] . number_format($payment['amount'], 0) .
                " " . $payment['currency_code'] . " has been verified and approved.\n\n" .
                "Your Nfelia subscription is now ACTIVE. You have full access to all modules.\n\n" .
                "Subscription valid until: " . date('d M Y', strtotime($period_end)) . "\n\n" .
                "Thank you for your payment!\n\nThe Nfelia Team";
            send_mail($payment['owner_email'], $payment['owner_name'], 'Subscription Activated — Nfelia', $msg);

            json_response(['success' => true, 'message' => 'Payment approved and subscription activated.']);

        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log('[Nfelia] MoMo approve failed: ' . $e->getMessage());
            json_response(['error' => 'Failed to approve. Please try again.'], 500);
        }
    }

    /* ══════════════════════════════════════════
     *  ADMIN: REJECT — POST /admin/momo/reject
     * ══════════════════════════════════════════ */
    public function reject() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['error' => 'Method not allowed'], 405); return;
        }
        csrf_verify();
        $this->guard_admin();

        $payment_id = (int)($_POST['payment_id'] ?? 0);
        $reason     = trim($_POST['reason'] ?? '');

        if (!$payment_id) { json_response(['error' => 'Invalid payment ID'], 400); return; }
        if (!$reason)     { json_response(['error' => 'A rejection reason is required.'], 400); return; }

        $stmt = $this->db->prepare("
            SELECT sp.*, b.name AS biz_name,
                   u.full_name AS owner_name, u.email AS owner_email,
                   c.currency_symbol, c.currency_code
            FROM subscription_payments sp
            JOIN businesses b ON b.id = sp.business_id
            JOIN users u ON u.business_id = b.id AND u.role = 'owner'
            JOIN countries c ON c.id = sp.country_id
            WHERE sp.id = ? AND sp.status = 'pending_verification'
        ");
        $stmt->execute([$payment_id]);
        $payment = $stmt->fetch();

        if (!$payment) { json_response(['error' => 'Payment not found or already reviewed'], 404); return; }

        $reviewer_id = auth_user()['id'];

        $this->db->beginTransaction();
        try {
            $this->db->prepare("
                UPDATE subscription_payments
                SET status = 'rejected', rejection_reason = ?, reviewed_at = NOW(), reviewed_by = ?
                WHERE id = ?
            ")->execute([$reason, $reviewer_id, $payment_id]);

            // Revert subscription to previous status (trial or keep past_due)
            $stmt = $this->db->prepare("SELECT status FROM subscriptions WHERE business_id = ?");
            $stmt->execute([$payment['business_id']]);
            $sub = $stmt->fetch();
            $revert_to = in_array($sub['status'] ?? '', ['pending_verification']) ? 'trial' : ($sub['status'] ?? 'trial');

            $this->db->prepare("
                UPDATE subscriptions SET status = ?, updated_at = NOW() WHERE business_id = ?
            ")->execute([$revert_to, $payment['business_id']]);

            $this->log_status_change($payment['business_id'], 'pending_verification', $revert_to, $reviewer_id, 'MoMo payment rejected: ' . $reason);

            // In-app notification
            $this->db->prepare("
                INSERT INTO notifications (business_id, type, title, message, action_url, created_at)
                VALUES (?, 'momo_rejected', 'Payment Rejected', ?, ?, NOW())
            ")->execute([
                $payment['business_id'],
                'Your payment receipt could not be verified. Reason: ' . $reason . ' — Please resubmit.',
                APP_URL . '/momo/checkout'
            ]);

            $this->db->commit();

            // Email the customer
            $momo_link = APP_URL . '/momo/checkout';
            $msg = "Hello " . $payment['owner_name'] . ",\n\n" .
                "Unfortunately, we could not verify your recent payment of " .
                $payment['currency_symbol'] . number_format($payment['amount'], 0) .
                " " . $payment['currency_code'] . ".\n\n" .
                "Reason: " . $reason . "\n\n" .
                "Please resubmit your payment using the link below. Make sure your receipt clearly shows:\n" .
                "- Your name or phone number\n" .
                "- The exact amount sent\n" .
                "- The transaction reference number\n\n" .
                "Resubmit here: " . $momo_link . "\n\n" .
                "If you need help, contact us at support@nfelia.com\n\n" .
                "The Nfelia Team";
            send_mail($payment['owner_email'], $payment['owner_name'], 'Action Required: Payment Resubmission — Nfelia', $msg);

            json_response(['success' => true, 'message' => 'Payment rejected. Customer notified.']);

        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log('[Nfelia] MoMo reject failed: ' . $e->getMessage());
            json_response(['error' => 'Failed to reject. Please try again.'], 500);
        }
    }

    /* ══════════════════════════════════════════
     *  RECEIPT VIEW — GET /momo/receipt/{id}
     *  Streams the receipt file to admin only.
     * ══════════════════════════════════════════ */
    public function receipt($id = null) {
        $this->guard_admin();

        $stmt = $this->db->prepare("SELECT receipt_path FROM subscription_payments WHERE id = ?");
        $stmt->execute([(int)$id]);
        $row = $stmt->fetch();

        if (!$row || !$row['receipt_path'] || !file_exists($row['receipt_path'])) {
            http_response_code(404);
            echo 'Receipt file not found.';
            return;
        }

        $path  = $row['receipt_path'];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($path);
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

        if (!in_array($mime, $allowed_mimes)) {
            http_response_code(403);
            echo 'Invalid file type.';
            return;
        }

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: inline; filename="receipt_' . (int)$id . '"');
        readfile($path);
        exit;
    }

    /* ══════════════════════════════════════════
     *  PRIVATE HELPERS
     * ══════════════════════════════════════════ */

    private function guard_admin(): void {
        if (!is_platform_admin()) {
            flash('error', 'Access denied.');
            redirect('dashboard');
            exit;
        }
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
