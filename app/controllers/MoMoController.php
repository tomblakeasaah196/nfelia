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
     *  CHECKOUT — GET /momo/checkout (the owner)
     *  ?kind=month&seats=N&covered=1,2  a month for the owner and those staff
     *  ?kind=seats&seats=N              N more seats in the month already paid
     * ══════════════════════════════════════════ */
    public function checkout() {
        $user     = auth_user();
        $business = auth_business();
        $this->guard_owner();

        $prices  = billing_prices($this->db, $this->biz_id);
        $country = $prices['country'];
        if (!$country || $prices['method'] !== 'mobile_money') {
            flash('error', 'Mobile Money is not available for your country.');
            redirect('settings?tab=billing');
            return;
        }
        if ($this->waiting_payment()) {
            flash('warning', 'We are still checking your last Mobile Money payment. You can send another once it is approved or turned down.');
            redirect('settings?tab=billing');
            return;
        }

        // Get current subscription to know what they are paying for
        $stmt = $this->db->prepare("SELECT * FROM subscriptions WHERE business_id = ?");
        $stmt->execute([$this->biz_id]);
        $subscription = $stmt->fetch();
        $sub_status = $subscription['status'] ?? 'trial';

        [$kind, $extra_seats, $covered, $amount_due] = $this->order($_GET, $subscription ?: null, $prices);
        if ($kind === null) {
            flash('error', 'Choose at least one new seat, up to ' . (MAX_SEATS - 1) . ' staff in all, for a month that is already paid.');
            redirect('settings?tab=billing');
            return;
        }

        $title     = 'Complete Payment';
        $view_file = BASE_PATH . '/views/billing/momo_checkout.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ══════════════════════════════════════════
     *  SUBMIT — POST /momo/submit (the owner)
     *  Saves the receipt for the founder to check. Nothing opens and no seats
     *  are added until it is approved (Tom's decision).
     * ══════════════════════════════════════════ */
    public function submit() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('momo/checkout'); return; }
        csrf_verify();
        $this->guard_owner();

        $user     = auth_user();
        $business = auth_business();

        $prices  = billing_prices($this->db, $this->biz_id);
        $country = $prices['country'];
        if (!$country || $prices['method'] !== 'mobile_money') {
            flash('error', 'Mobile Money is not available for your account.');
            redirect('settings?tab=billing');
            return;
        }
        if ($this->waiting_payment()) {
            flash('warning', 'We are still checking your last Mobile Money payment.');
            redirect('settings?tab=billing');
            return;
        }

        $stmt = $this->db->prepare("SELECT * FROM subscriptions WHERE business_id = ?");
        $stmt->execute([$this->biz_id]);
        $sub = $stmt->fetch() ?: null;
        [$kind, $extra_seats, $covered, $amount_due] = $this->order($_POST, $sub, $prices);
        if ($kind === null) {
            flash('error', 'Choose at least one new seat, for a month that is already paid.');
            redirect('settings?tab=billing');
            return;
        }
        $reference_num = mb_substr(trim($_POST['reference_number'] ?? ''), 0, 255);
        $back = 'momo/checkout?' . http_build_query(['kind' => $kind, 'seats' => $extra_seats, 'covered' => implode(',', $covered)]);

        // One transaction reference pays once
        if ($reference_num !== '') {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM subscription_payments WHERE reference_number = ? AND status IN ('pending_verification', 'approved')");
            $stmt->execute([$reference_num]);
            if ((int)$stmt->fetchColumn() > 0) {
                flash('error', 'That transaction reference has already been sent to us. Check it, or contact support.');
                redirect($back);
                return;
            }
        }
        if (!throttle_allows('momo_submit', (string)$this->biz_id, 5, 60 * 24)) {
            flash('error', 'Too many receipts today. Please contact support.');
            redirect('settings?tab=billing');
            return;
        }

        // ── Receipt file upload (REQUIRED)
        if (empty($_FILES['receipt']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Please upload a screenshot or photo of your payment receipt.');
            redirect($back);
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
            redirect($back);
            return;
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            flash('error', 'Receipt file is too large. Maximum size is 5MB.');
            redirect($back);
            return;
        }

        // Save to secure non-web-accessible directory
        $receipt_dir = $this->get_receipt_dir();
        $filename    = 'momo_' . $this->biz_id . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext_map[$detected];
        $stored_path = $receipt_dir . $filename; // full path stored in DB for admin retrieval

        if (!move_uploaded_file($tmp, $stored_path)) {
            flash('error', 'Failed to save receipt. Please try again or contact support.');
            redirect($back);
            return;
        }

        $this->db->beginTransaction();
        try {
            // Get or create subscription record (its status is left alone)
            if (!$sub) {
                $trial_end = date('Y-m-d', strtotime('+' . (int)$country['trial_days'] . ' days'));
                $this->db->prepare("
                    INSERT INTO subscriptions (business_id, plan, status, user_seats, extra_seats, country_id, payment_method, trial_ends_at)
                    VALUES (?, 'trial', 'trial', 1, 0, ?, 'mobile_money', ?)
                ")->execute([$this->biz_id, $country['id'], $trial_end]);
                $sub_id = $this->db->lastInsertId();
            } else {
                $sub_id = $sub['id'];
            }

            // Save the payment submission record
            $this->db->prepare("
                INSERT INTO subscription_payments
                    (business_id, subscription_id, country_id, amount, currency_code,
                     payment_method, kind, extra_seats, covered_user_ids, reference_number, receipt_path, status, submitted_at)
                VALUES (?, ?, ?, ?, ?, 'mobile_money', ?, ?, ?, ?, ?, 'pending_verification', NOW())
            ")->execute([
                $this->biz_id, $sub_id, $country['id'],
                $amount_due, $country['currency_code'],
                $kind, $extra_seats, $kind === 'month' ? implode(',', $covered) : null,
                $reference_num ?: null, $stored_path,
            ]);

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            @unlink($stored_path);
            error_log('[Nfelia] MoMo submit failed: ' . $e->getMessage());
            flash('error', 'A system error occurred. Please try again or contact support.');
            redirect($back);
            return;
        }

        // The founder hears at once: bell, phone and email, marked urgent
        $money = $country['currency_symbol'] . number_format($amount_due, 0) . ' ' . $country['currency_code'];
        billing_alert_founder($this->db,
            'Mobile Money payment from ' . $business['name'],
            $business['name'] . ' says they sent ' . $money . ' by Mobile Money'
                . ($reference_num !== '' ? ' (reference ' . $reference_num . ')' : '') . '. Check it and approve or turn it down.',
            'momo/review');

        // Confirmation email to the user
        $confirm_msg = "Hello " . $user['full_name'] . ",\n\n" .
            "We've received your payment receipt for " . $money . ".\n\n" .
            "We are checking it now. Nfelia opens the month as soon as it is approved, and we will tell you here and by email.\n\n" .
            "Reference: " . ($reference_num ?: 'Not provided') . "\n" .
            "Submitted: " . date('d M Y, H:i') . "\n\n" .
            "Thank you for choosing Nfelia.\n\nThe Nfelia Team";
        send_mail($user['email'], $user['full_name'], 'Payment Submission Received — Nfelia', $confirm_msg);

        flash('success', 'Receipt sent. We are checking it now, and Nfelia opens the month as soon as it is approved.');
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
            // Mark payment as approved, once: a second click (or a second
            // admin) finds it already reviewed
            $stmtOk = $this->db->prepare("
                UPDATE subscription_payments
                SET status = 'approved', reviewed_at = NOW(), reviewed_by = ?
                WHERE id = ? AND status = 'pending_verification'
            ");
            $stmtOk->execute([$reviewer_id, $payment_id]);
            if ($stmtOk->rowCount() !== 1) {
                $this->db->rollBack();
                json_response(['error' => 'Payment not found or already reviewed'], 409);
                return;
            }

            $stmt = $this->db->prepare("SELECT status FROM subscriptions WHERE business_id = ?");
            $stmt->execute([$payment['business_id']]);
            $old_status = $stmt->fetchColumn() ?: null;

            if (($payment['kind'] ?? 'month') === 'seats') {
                // More seats in the month already paid for
                $this->db->prepare("
                    UPDATE subscriptions SET extra_seats = LEAST(?, extra_seats + ?), last_payment_ref = ?, updated_at = NOW()
                    WHERE business_id = ?
                ")->execute([MAX_SEATS - 1, (int)$payment['extra_seats'], 'MOMO_' . $payment_id, $payment['business_id']]);
                $period_end = null;
            } else {
                // A month: it starts when the current paid month ends, and
                // staff the owner didn't tick are switched off. A receipt
                // sent before staff were ticked keeps everyone and the
                // seats it asked for then.
                if ($payment['covered_user_ids'] === null) {
                    $stmt = $this->db->prepare("SELECT extra_seats FROM subscriptions WHERE business_id = ?");
                    $stmt->execute([$payment['business_id']]);
                    $seats   = (int)$stmt->fetchColumn();
                    $covered = null;
                } else {
                    $seats   = (int)$payment['extra_seats'];
                    $covered = $payment['covered_user_ids'] === '' ? [] : array_map('intval', explode(',', $payment['covered_user_ids']));
                }
                [, $period_end] = billing_start_month($this->db, (int)$payment['business_id'], (float)$payment['amount'],
                    $seats, $covered, 'mobile_money', 'MOMO_' . $payment_id);
            }

            // Log status change
            $this->log_status_change($payment['business_id'], $old_status, 'active', $reviewer_id, 'MoMo payment approved');

            // In-app notification to the business
            $this->db->prepare("
                INSERT INTO notifications (business_id, type, title, message, action_url, created_at)
                VALUES (?, 'momo_approved', 'Payment Approved!', ?, ?, NOW())
            ")->execute([
                $payment['business_id'],
                'Your payment of ' . $payment['currency_symbol'] . number_format($payment['amount'], 0) .
                ' has been verified. ' . ($period_end ? 'Your month runs to ' . date('j M Y', strtotime($period_end)) . '.' : 'Your new seats are ready.'),
                APP_URL . '/settings?tab=billing'
            ]);

            $this->db->commit();

            // Whether the shop is open follows the subscription
            storefront_purge((int)$payment['business_id']);

            // Email the customer
            $msg = "Hello " . $payment['owner_name'] . ",\n\n" .
                "Your payment of " . $payment['currency_symbol'] . number_format($payment['amount'], 0) .
                " " . $payment['currency_code'] . " has been verified and approved.\n\n" .
                ($period_end
                    ? "Your Nfelia month is paid. It runs until " . date('d M Y', strtotime($period_end)) . ".\n\n"
                    : "Your new seats are ready to use.\n\n") .
                "Thank you for your payment!\n\nThe Nfelia Team";
            send_mail($payment['owner_email'], $payment['owner_name'], 'Payment Approved — Nfelia', $msg);

            json_response(['success' => true, 'message' => 'Payment approved.']);

        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
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
            $stmtNo = $this->db->prepare("
                UPDATE subscription_payments
                SET status = 'rejected', rejection_reason = ?, reviewed_at = NOW(), reviewed_by = ?
                WHERE id = ? AND status = 'pending_verification'
            ");
            $stmtNo->execute([$reason, $reviewer_id, $payment_id]);
            if ($stmtNo->rowCount() !== 1) {
                $this->db->rollBack();
                json_response(['error' => 'Payment not found or already reviewed'], 409);
                return;
            }

            // A receipt no longer changes the subscription, so there is
            // nothing to undo. One sent before that change left the
            // subscription "pending": it goes back to what it was before.
            $stmt = $this->db->prepare("SELECT status, trial_ends_at, current_period_end FROM subscriptions WHERE business_id = ?");
            $stmt->execute([$payment['business_id']]);
            $sub = $stmt->fetch();
            if (($sub['status'] ?? '') === 'pending_verification') {
                $stmt = $this->db->prepare("
                    SELECT old_status FROM subscription_status_log
                    WHERE business_id = ? AND new_status = 'pending_verification' AND old_status IS NOT NULL AND old_status <> 'pending_verification'
                    ORDER BY id DESC LIMIT 1
                ");
                $stmt->execute([$payment['business_id']]);
                $revert_to = $stmt->fetchColumn()
                    ?: (!empty($sub['current_period_end']) ? 'active' : (!empty($sub['trial_ends_at']) ? 'trial' : 'suspended'));

                $this->db->prepare("
                    UPDATE subscriptions SET status = ?, updated_at = NOW() WHERE business_id = ? AND status = 'pending_verification'
                ")->execute([$revert_to, $payment['business_id']]);

                $this->log_status_change($payment['business_id'], 'pending_verification', $revert_to, $reviewer_id, 'MoMo payment rejected: ' . $reason);
            }

            // In-app notification
            $this->db->prepare("
                INSERT INTO notifications (business_id, type, title, message, action_url, created_at)
                VALUES (?, 'momo_rejected', 'Payment Rejected', ?, ?, NOW())
            ")->execute([
                $payment['business_id'],
                'Your payment receipt could not be verified. Reason: ' . $reason . ' — Please pay again from Billing.',
                APP_URL . '/settings?tab=billing'
            ]);

            $this->db->commit();

            // Email the customer
            $momo_link = APP_URL . '/settings?tab=billing';
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
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
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

    /** Paying for Nfelia is the owner's. */
    private function guard_owner(): void {
        if (!is_owner()) {
            flash('error', 'Only the owner can pay for Nfelia.');
            redirect('dashboard');
        }
    }

    /** Is a receipt from this business still waiting to be checked? */
    private function waiting_payment(): bool {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM subscription_payments WHERE business_id = ? AND status = 'pending_verification'");
        $stmt->execute([$this->biz_id]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * What is being paid for, from the checkout link or form:
     * [kind, extra_seats, covered staff ids, amount], or kind null when it
     * makes no sense (seats for a month that isn't paid, or none to add).
     */
    private function order(array $in, ?array $sub, array $prices): array {
        if (($in['kind'] ?? 'month') === 'seats') {
            $add = max(0, min(MAX_SEATS - 1 - (int)($sub['extra_seats'] ?? 0), (int)($in['seats'] ?? 0)));
            if ($add < 1 || ($sub['status'] ?? '') !== 'active' || billing_access($sub) !== 'paid') {
                return [null, 0, [], 0];
            }
            return ['seats', $add, [], $add * $prices['seat']];
        }
        $covered = billing_covered_from_post($this->db, $this->biz_id, $in['covered'] ?? '');
        $seats   = max(count($covered), min(MAX_SEATS - 1, max(0, (int)($in['seats'] ?? 0))));
        return ['month', $seats, $covered, $prices['monthly'] + $seats * $prices['seat']];
    }

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
