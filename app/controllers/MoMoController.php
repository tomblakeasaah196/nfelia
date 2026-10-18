<?php
/**
 * Nfelia — Mobile Money Controller (Phase 4)
 * Handles: MoMo checkout and receipt upload. The Nfelia team checks
 * receipts in Nfelia Ops → Payments to check (OpsPaymentsController).
 *
 * Routes:
 *   GET  /momo/checkout          — Show MoMo payment screen (authenticated)
 *   POST /momo/submit            — Submit payment receipt (authenticated)
 *   /momo/review, approve, reject, receipt — moved to Ops (redirect there)
 *
 * Files uploaded are stored OUTSIDE the webroot (MOMO_RECEIPT_DIR), and only
 * Ops shows them.
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
            $payment_id = (int)$this->db->lastInsertId();

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            @unlink($stored_path);
            error_log('[Nfelia] MoMo submit failed: ' . $e->getMessage());
            flash('error', 'A system error occurred. Please try again or contact support.');
            redirect($back);
            return;
        }

        // The Nfelia team hears at once, in Ops: the bell, phones and email,
        // marked urgent (Tom: treat every transfer as urgent). The owner gets
        // a confirmation (in the app and by email: billing always emails).
        $amount_text = $country['currency_symbol'] . number_format($amount_due, 0) . ' ' . $country['currency_code'];
        ops_alert(get_db(), 'payment', 'Mobile Money payment from ' . $business['name'],
                  $business['name'] . ' says they sent ' . $amount_text . ' by Mobile Money'
                      . ($reference_num !== '' ? ' (reference ' . $reference_num . ')' : '') . '. Check it and approve or turn it down.',
                  'payments#payment-' . $payment_id, 'payment:' . $payment_id, ['permission' => 'clients.act', 'email' => true]);
        notify('billing.momo_submitted', $this->biz_id, ['amount_text' => $amount_text], ['dedupe' => (string)$payment_id]);

        flash('success', 'Receipt sent. We are checking it now, and Nfelia opens the month as soon as it is approved.');
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

    /** The old review pages: the Nfelia business goes to Ops → Payments to check, everyone else home. */
    private function moved(): never {
        if ((int)$this->biz_id !== FOUNDER_BUSINESS_ID) {
            redirect('dashboard');
        }
        header('Location: ' . ops_url('payments'), true, 301);
        exit;
    }
}
