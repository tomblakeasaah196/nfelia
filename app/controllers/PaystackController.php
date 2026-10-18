<?php
/**
 * Nfelia — Paystack Subscription Controller
 *
 * NFELIA AUDIT FIX — C01 — Removed duplicate activate_subscription() method — 2026-06-03
 * NFELIA AUDIT FIX — C06 — Fixed idempotency check: event_reference -> paystack_reference — 2026-06-03
 * NFELIA AUDIT FIX — C07 — Fixed notify(): link -> action_url column — 2026-06-03
 * NFELIA AUDIT FIX — H07 — Removed "Paystack" from all user-visible notification copy — 2026-06-03
 */
class PaystackController {

    private $db;
    private string $secret_key;
    private string $public_key;
    private string $plan_code;
    private string $seat_plan_code;

    private const API_BASE = 'https://api.paystack.co';
    private const PLAN_AMOUNT = 1000000;  // 10,000 NGN in kobo
    private const SEAT_AMOUNT = 500000;   // 5,000 NGN per extra seat in kobo

    public function __construct() {
        $this->db         = get_db();
        $this->secret_key = $_ENV['PAYSTACK_SECRET_KEY'] ?? '';
        $this->public_key = $_ENV['PAYSTACK_PUBLIC_KEY'] ?? '';
        $this->plan_code  = $_ENV['PAYSTACK_PLAN_CODE'] ?? '';
        $this->seat_plan_code = $_ENV['PAYSTACK_SEAT_PLAN_CODE'] ?? '';
    }

    /* ══════════════════════════════════════════
     *  INITIALIZE — POST (authenticated)
     * ══════════════════════════════════════════ */
    public function initialize() {
        if (!is_logged_in()) { json_response(['error' => 'Unauthorized'], 401); return; }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { json_response(['error' => 'Method not allowed'], 405); return; }
        csrf_verify();

        $biz_id   = auth_business_id();
        $user     = auth_user();

        // NFELIA Phase 3.4 — Payment Method Router
        // Detect country and route to the correct payment handler
        require_once __DIR__ . '/CountryController.php';
        $country = CountryController::get_country_for_business($this->db, $biz_id);

        if ($country && $country['payment_method'] === 'mobile_money') {
            // Route to MoMo flow — pass extra_seats so checkout knows what to charge
            $extra_seats_req = max(0, min(4, (int)($_POST['extra_seats'] ?? 0)));
            json_response([
                'success'           => true,
                'authorization_url' => APP_URL . '/momo/checkout?extra_seats=' . $extra_seats_req,
            ]);
            return;
        }

        if ($country && $country['payment_method'] === 'manual') {
            json_response([
                'success'           => true,
                'authorization_url' => APP_URL . '/settings?tab=billing&manual=1',
            ]);
            return;
        }
        // Default: paystack (falls through to existing logic below)

        $sub = $this->get_subscription($biz_id);
        $status = $sub['status'] ?? 'trial';
        $saved_seats = (int)($sub['extra_seats'] ?? 0);
        $is_mid_cycle = ($status === 'active');

        $requested_total_extra = max(0, min(4, (int)($_POST['extra_seats'] ?? 0)));

        if ($is_mid_cycle) {
            $added_seats = $requested_total_extra - $saved_seats;
            if ($added_seats <= 0) {
                json_response(['error' => 'You must add at least 1 new seat to upgrade.'], 400); return;
            }
            $total_amount = $added_seats * self::SEAT_AMOUNT;
            $plan_code    = null; // No plan for mid-cycle seat additions
            $discount_kobo = 0;
        } else {
            $base_amount = self::PLAN_AMOUNT + ($requested_total_extra * self::SEAT_AMOUNT);
            require_once __DIR__ . '/ReferralController.php';
            $discount_kobo = ReferralController::get_available_discount($this->db, $biz_id);
            $total_amount  = max(10000, $base_amount - $discount_kobo);
            $plan_code     = ($requested_total_extra > 0) ? null : ($_ENV['PAYSTACK_PLAN_CODE'] ?? null);
        }

        $reference = 'NFELIA_' . $biz_id . '_' . bin2hex(random_bytes(12));

        $payload = [
            'email'        => $user['email'],
            'amount'       => $total_amount,
            'currency'     => 'NGN',
            'reference'    => $reference,
            'callback_url' => APP_URL . '/billing/callback',
            'plan'         => $plan_code,
            'metadata'     => json_encode([
                'business_id'   => $biz_id,
                'extra_seats'   => $requested_total_extra,
                'discount_kobo' => $discount_kobo,
                'is_mid_cycle'  => $is_mid_cycle,
            ]),
        ];

        if (!$payload['plan']) unset($payload['plan']);

        $response = $this->paystack_request('POST', '/transaction/initialize', $payload);

        if (!$response || !($response['status'] ?? false)) {
            json_response(['error' => $response['message'] ?? 'Failed to initialize payment.'], 500);
            return;
        }

        json_response([
            'success'           => true,
            'authorization_url' => $response['data']['authorization_url'],
        ]);
    }

    /* ══════════════════════════════════════════
     *  CALLBACK — GET (after Paystack redirect)
     * ══════════════════════════════════════════ */
    public function callback() {
        if (!is_logged_in()) { redirect('login'); return; }

        $reference = $_GET['reference'] ?? $_GET['trxref'] ?? '';

        if ($reference === '') {
            flash('error', 'No payment reference found.');
            redirect('settings?tab=billing');
            return;
        }

        $response = $this->paystack_request('GET', '/transaction/verify/' . urlencode($reference));

        if (!$response || !($response['status'] ?? false)) {
            flash('error', 'Payment verification failed. Please contact support.');
            redirect('settings?tab=billing');
            return;
        }

        $data = $response['data'];

        if ($data['status'] !== 'success') {
            flash('error', 'Payment was not successful. Status: ' . $data['status']);
            redirect('settings?tab=billing');
            return;
        }

        $biz_id        = auth_business_id();
        $extra_seats   = (int)($data['metadata']['extra_seats'] ?? 0);
        $discount_kobo = (int)($data['metadata']['discount_kobo'] ?? 0);
        $is_mid_cycle  = (bool)($data['metadata']['is_mid_cycle'] ?? false);
        $amount_paid   = (int)($data['amount'] ?? 0);

        // The webhook may have handled this payment already, or this page was
        // refreshed: each payment is acted on once, by whichever comes first.
        if ($this->claim($biz_id, 'charge.success', $reference, $amount_paid, 'success', 'Payment verified via callback', $data)) {
            try {
                $this->activate_subscription(
                    $biz_id,
                    $amount_paid,
                    $extra_seats,
                    $data['authorization']['authorization_code'] ?? null,
                    $data['customer']['customer_code'] ?? null,
                    $reference,
                    $is_mid_cycle
                );

                require_once __DIR__ . '/ReferralController.php';
                if ($discount_kobo > 0) {
                    ReferralController::apply_discount($this->db, $biz_id);
                }
                ReferralController::reward_referral($this->db, $biz_id);
            } catch (\Throwable $e) {
                $this->unclaim($reference);   // the webhook, or a reload, can try again
                throw $e;
            }
            $this->tell_paid($biz_id, $amount_paid, (string)($data['currency'] ?? 'NGN'), $reference);
        }

        unset($_SESSION['paystack_reference'], $_SESSION['paystack_extra_seats']);

        flash('success', 'Payment successful! Your subscription is now active.');
        redirect('settings?tab=billing');
    }

    /* ══════════════════════════════════════════
     *  WEBHOOK — POST (server-to-server)
     * ══════════════════════════════════════════ */
    public function webhook() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit;
        }

        $raw_body = file_get_contents('php://input');
        if (empty($raw_body)) {
            http_response_code(400);
            exit;
        }

        // 1. Verify HMAC-SHA512 signature
        $signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
        $expected  = hash_hmac('sha512', $raw_body, $this->secret_key);

        if (!hash_equals($expected, $signature)) {
            error_log('[Nfelia] Paystack webhook signature mismatch. Rejecting.');
            http_response_code(401);
            exit;
        }

        $event = json_decode($raw_body, true);
        if (!$event || empty($event['event'])) {
            http_response_code(400);
            exit;
        }

        $event_type = $event['event'];
        $event_data = $event['data'] ?? [];

        // 2. Resolve business
        $biz_id = $this->resolve_business_id($event_data);
        if (!$biz_id) {
            error_log('[Nfelia] Webhook: could not resolve business_id for event ' . $event_type);
            http_response_code(200);
            echo json_encode(['status' => 'ok', 'message' => 'Business not found']);
            exit;
        }

        // 3. Each event is acted on once. Paystack resends until it gets a
        // 200, sometimes two at a time, so the event is claimed (a unique
        // row in subscription_history) before anything is done with it.
        $reference = $this->event_key($event_type, $event_data, $raw_body);
        $claimed = $this->claim(
            $biz_id,
            $event_type,
            $reference,
            isset($event_data['amount']) ? (int)$event_data['amount'] : null,
            $event_data['status'] ?? $event_type,
            $event_data['gateway_response'] ?? $event_type,
            $event_data
        );
        if (!$claimed) {
            http_response_code(200);
            echo json_encode(['status' => 'ok', 'message' => 'Already processed']);
            exit;
        }

        // 4. Process event
        try {
            switch ($event_type) {
                case 'charge.success':
                    $this->handle_charge_success($biz_id, $event_data);
                    break;
                case 'subscription.create':
                    $this->handle_subscription_create($biz_id, $event_data);
                    break;
                case 'subscription.disable':
                case 'subscription.not_renew':
                    $this->handle_subscription_disable($biz_id, $event_data);
                    break;
                case 'invoice.payment_failed':
                    $this->handle_payment_failed($biz_id, $event_data, $reference);
                    break;
                default:
                    // Unknown event: recorded above, nothing to do
                    break;
            }
        } catch (\Throwable $e) {
            // Let go of the claim and answer 500: Paystack sends it again.
            $this->unclaim($reference);
            error_log('[Nfelia] Paystack webhook ' . $event_type . ' failed: ' . $e->getMessage());
            http_response_code(500);
            exit;
        }

        http_response_code(200);
        echo json_encode(['status' => 'ok']);
        exit;
    }

    /* ══════════════════════════════════════════
     *  CANCEL SUBSCRIPTION — POST (authenticated)
     * ══════════════════════════════════════════ */
    public function cancel() {
        if (!is_logged_in()) { redirect('login'); return; }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('settings?tab=billing'); return; }
        csrf_verify();

        $user = auth_user();
        if (($user['role'] ?? 'staff') !== 'owner') {
            flash('error', 'Only the business owner can manage billing.');
            redirect('settings?tab=billing');
            return;
        }

        $biz_id = auth_business_id();
        $sub    = $this->get_subscription($biz_id);

        if (!$sub || $sub['status'] === 'cancelled') {
            flash('error', 'No active subscription to cancel.');
            redirect('settings?tab=billing');
            return;
        }

        $reason = trim($_POST['cancel_reason'] ?? '');

        if ($sub['paystack_subscription_code'] && $sub['paystack_email_token']) {
            $this->paystack_request('POST', '/subscription/disable', [
                'code'  => $sub['paystack_subscription_code'],
                'token' => $sub['paystack_email_token'],
            ]);
        }

        $this->db->prepare("
            UPDATE subscriptions SET
                status = 'cancelled',
                cancelled_at = NOW(),
                cancel_reason = ?
            WHERE business_id = ?
        ")->execute([$reason ?: null, $biz_id]);

        $this->claim($biz_id, 'subscription.cancelled', 'cancel_' . $biz_id . '_' . time(), null, 'cancelled', 'Cancelled by owner: ' . ($reason ?: 'No reason given'), []);

        // They keep what they paid for (subscription_open() in functions.php).
        $until = $sub['current_period_end'] ?: $sub['trial_ends_at'];
        notify('billing.cancelled', $biz_id, ['access_until' => $until], ['dedupe' => 'cancel:' . date('Y-m-d H:i')]);

        flash('success', 'Subscription cancelled.' . ($until ? ' You keep full access until ' . date('j M Y', strtotime($until)) . '.' : ''));
        redirect('settings?tab=billing');
    }

    /* ══════════════════════════════════════════
     *  WEBHOOK EVENT HANDLERS
     * ══════════════════════════════════════════ */

    private function handle_charge_success(int $biz_id, array $data): void {
        $extra_seats   = (int)($data['metadata']['extra_seats'] ?? 0);
        $discount_kobo = (int)($data['metadata']['discount_kobo'] ?? 0);
        $is_mid_cycle  = (bool)($data['metadata']['is_mid_cycle'] ?? false);
        $auth_code     = $data['authorization']['authorization_code'] ?? null;
        $cust_code     = $data['customer']['customer_code'] ?? null;
        $amount_paid   = (int)($data['amount'] ?? 0);

        $this->activate_subscription($biz_id, $amount_paid, $extra_seats, $auth_code, $cust_code, $data['reference'] ?? null, $is_mid_cycle);

        if ($discount_kobo > 0) {
            require_once __DIR__ . '/ReferralController.php';
            ReferralController::apply_discount($this->db, $biz_id);
        }

        if (!$is_mid_cycle) {
            require_once __DIR__ . '/ReferralController.php';
            ReferralController::reward_referral($this->db, $biz_id);
        }

        $this->tell_paid($biz_id, $amount_paid, (string)($data['currency'] ?? 'NGN'), (string)($data['reference'] ?? ''));
    }

    /** The owner hears the payment went through, and until when they're covered. */
    private function tell_paid(int $biz_id, int $amount_kobo, string $currency, string $reference): void {
        $symbols = ['NGN' => '₦', 'GHS' => 'GH₵', 'KES' => 'KSh ', 'ZAR' => 'R', 'USD' => '$'];
        $amount  = ($symbols[strtoupper($currency)] ?? strtoupper($currency) . ' ') . number_format($amount_kobo / 100, $amount_kobo % 100 ? 2 : 0);
        $sub     = $this->get_subscription($biz_id);
        notify('billing.paid', $biz_id, ['amount_text' => $amount, 'period_end' => $sub['current_period_end'] ?? null],
               ['dedupe' => $reference !== '' ? $reference : 'paid:' . date('Y-m-d H:i')]);
    }

    private function activate_subscription(int $biz_id, int $amount_kobo, int $extra_seats, ?string $auth_code, ?string $cust_code, ?string $reference, bool $is_mid_cycle = false): void {
        $amount_ngn = $amount_kobo / 100;

        if ($is_mid_cycle) {
            $this->db->prepare("
                UPDATE subscriptions SET
                    extra_seats = ?,
                    paystack_authorization_code = COALESCE(?, paystack_authorization_code),
                    paystack_customer_code = COALESCE(?, paystack_customer_code),
                    last_payment_ref = COALESCE(?, last_payment_ref),
                    updated_at = NOW()
                WHERE business_id = ?
            ")->execute([$extra_seats, $auth_code, $cust_code, $reference, $biz_id]);
        } else {
            $period_start = date('Y-m-d');
            $period_end   = date('Y-m-d', strtotime('+1 month'));
            $grace_end    = date('Y-m-d H:i:s', strtotime('+1 month +3 days'));

            $this->db->prepare("
                INSERT INTO subscriptions
                    (business_id, plan, status, user_seats, extra_seats, monthly_amount,
                     current_period_start, current_period_end, grace_ends_at,
                     paystack_authorization_code, paystack_customer_code, last_payment_ref, last_payment_date)
                VALUES (?, 'standard', 'active', 1, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())
                ON DUPLICATE KEY UPDATE
                    plan = 'standard',
                    status = 'active',
                    extra_seats = VALUES(extra_seats),
                    monthly_amount = VALUES(monthly_amount),
                    current_period_start = VALUES(current_period_start),
                    current_period_end = VALUES(current_period_end),
                    grace_ends_at = VALUES(grace_ends_at),
                    paystack_authorization_code = COALESCE(VALUES(paystack_authorization_code), paystack_authorization_code),
                    paystack_customer_code = COALESCE(VALUES(paystack_customer_code), paystack_customer_code),
                    last_payment_ref = VALUES(last_payment_ref),
                    last_payment_date = CURDATE(),
                    trial_ends_at = NULL,
                    cancelled_at = NULL,
                    cancel_reason = NULL,
                    updated_at = NOW()
            ")->execute([
                $biz_id, $extra_seats, $amount_ngn,
                $period_start, $period_end, $grace_end,
                $auth_code, $cust_code, $reference,
            ]);
        }
    }

    private function handle_subscription_create(int $biz_id, array $data): void {
        $sub_code    = $data['subscription_code'] ?? null;
        $plan_code   = $data['plan']['plan_code'] ?? null;
        $email_token = $data['email_token'] ?? null;

        if ($sub_code) {
            $this->db->prepare("
                UPDATE subscriptions SET
                    paystack_subscription_code = ?,
                    paystack_plan_code = ?,
                    paystack_email_token = ?,
                    updated_at = NOW()
                WHERE business_id = ?
            ")->execute([$sub_code, $plan_code, $email_token, $biz_id]);
        }
    }

    private function handle_subscription_disable(int $biz_id, array $data): void {
        $sub  = $this->get_subscription($biz_id);
        $stmt = $this->db->prepare("
            UPDATE subscriptions SET status = 'cancelled', cancelled_at = COALESCE(cancelled_at, NOW()), updated_at = NOW()
            WHERE business_id = ? AND status != 'cancelled'
        ");
        $stmt->execute([$biz_id]);
        if ($stmt->rowCount() !== 1) {
            return;   // already cancelled here (the owner did it): they were told then
        }
        // Paid time left: they keep it. None: it has ended.
        $until = $sub['current_period_end'] ?? null;
        $key   = 'disabled:' . ($data['subscription_code'] ?? '') . ':' . $until;
        if ($until && planner_today() <= substr($until, 0, 10)) {
            notify('billing.cancelled', $biz_id, ['access_until' => $until], ['dedupe' => $key]);
        } else {
            notify('billing.ended', $biz_id, [], ['dedupe' => $key]);
        }
    }

    private function handle_payment_failed(int $biz_id, array $data, string $reference): void {
        $sub = $this->get_subscription($biz_id);

        if ($sub && $sub['status'] === 'active') {
            $this->db->prepare("
                UPDATE subscriptions SET
                    status = 'past_due',
                    grace_ends_at = DATE_ADD(NOW(), INTERVAL 3 DAY),
                    updated_at = NOW()
                WHERE business_id = ? AND status = 'active'
            ")->execute([$biz_id]);
        }

        notify('billing.failed', $biz_id, [], ['dedupe' => $reference]);
    }

    /* ══════════════════════════════════════════
     *  PRIVATE HELPERS
     * ══════════════════════════════════════════ */

    private function get_subscription(int $biz_id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM subscriptions WHERE business_id = ?");
        $stmt->execute([$biz_id]);
        return $stmt->fetch() ?: null;
    }

    private function resolve_business_id(array $data): ?int {
        if (!empty($data['metadata']['business_id'])) {
            return (int)$data['metadata']['business_id'];
        }

        $email = $data['customer']['email'] ?? null;
        if ($email) {
            $stmt = $this->db->prepare(
                "SELECT business_id FROM users WHERE email = ? AND is_active = 1 LIMIT 1"
            );
            $stmt->execute([$email]);
            $row = $stmt->fetch();
            if ($row) return (int)$row['business_id'];
        }

        $cust_code = $data['customer']['customer_code'] ?? null;
        if ($cust_code) {
            $stmt = $this->db->prepare(
                "SELECT business_id FROM subscriptions WHERE paystack_customer_code = ?"
            );
            $stmt->execute([$cust_code]);
            $row = $stmt->fetch();
            if ($row) return (int)$row['business_id'];
        }

        return null;
    }

    /**
     * Record a payment event, and claim it: true the first time this
     * reference is seen, false when it was already handled (a resent
     * webhook, the callback after the webhook, a refreshed page). If the
     * record can't be written for another reason the event is still handled,
     * as it always was.
     */
    private function claim(int $biz_id, string $type, string $reference, ?int $amount, string $status, string $description, $data): bool {
        $sub = $this->get_subscription($biz_id);
        try {
            $this->db->prepare("
                INSERT INTO subscription_history
                    (business_id, subscription_id, event_type, paystack_reference, amount, status, payload)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $biz_id,
                $sub['id'] ?? 0,
                $type,
                $reference,
                $amount ?? 0,
                $status,
                json_encode($data),
            ]);
            return true;
        } catch (\PDOException $e) {
            if ($e->getCode() == 23000) {
                return false;
            }
            error_log('[Nfelia] DB Error recording Paystack event: ' . $e->getMessage());
            return true;
        }
    }

    /** Let go of a claim when handling the event failed, so it can be tried again. */
    private function unclaim(string $reference): void {
        $this->db->prepare("DELETE FROM subscription_history WHERE paystack_reference = ?")->execute([$reference]);
    }

    /**
     * The same key every time Paystack sends the same event, and a
     * different one for different events: a payment's own reference, an
     * invoice's code, the event and the subscription's code, or else the
     * body itself (never the time, which made every resend look new).
     */
    private function event_key(string $type, array $d, string $raw_body): string {
        if ($type === 'charge.success' && !empty($d['reference'])) {
            $key = (string)$d['reference'];
        } elseif (!empty($d['invoice_code'])) {
            $key = $type . ':' . $d['invoice_code'];
        } elseif (!empty($d['subscription_code'])) {
            $key = $type . ':' . $d['subscription_code'];
        } elseif (!empty($d['id'])) {
            $key = $type . ':' . $d['id'];
        } else {
            $key = $type . ':' . hash('sha256', $raw_body);
        }
        return substr($key, 0, 100);
    }

    /**
     * Make an authenticated request to the Paystack API
     */
    private function paystack_request(string $method, string $endpoint, array $data = []): ?array {
        $url = self::API_BASE . $endpoint;

        $headers = [
            'Authorization: Bearer ' . $this->secret_key,
            'Content-Type: application/json',
            'Cache-Control: no-cache',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            error_log('[Nfelia] Paystack cURL error: ' . curl_error($ch));
            curl_close($ch);
            return null;
        }
        curl_close($ch);

        $parsed = json_decode($response, true);

        if ($http_code >= 400) {
            error_log("[Nfelia] Paystack API error ({$http_code}): " . ($parsed['message'] ?? $response));
        }

        return $parsed;
    }
}
