<?php
/**
 * Nfelia — Paystack Subscription Controller
 *
 * A card payment buys one month of Nfelia (or more seats in the month already
 * paid for). The owner chooses, each month, which staff the month covers;
 * app/helpers/billing.php starts the month. Nothing renews by itself: card
 * subscriptions made before this change are stopped after their next charge,
 * and the owner pays from Billing from then on.
 *
 * NFELIA AUDIT FIX — C01 — Removed duplicate activate_subscription() method — 2026-06-03
 * NFELIA AUDIT FIX — C06 — Fixed idempotency check: event_reference -> paystack_reference — 2026-06-03
 * NFELIA AUDIT FIX — C07 — Fixed notify(): link -> action_url column — 2026-06-03
 * NFELIA AUDIT FIX — H07 — Removed "Paystack" from all user-visible notification copy — 2026-06-03
 */
class PaystackController {

    private $db;
    private string $secret_key;

    public function __construct() {
        $this->db         = get_db();
        $this->secret_key = $_ENV['PAYSTACK_SECRET_KEY'] ?? '';
    }

    /* ══════════════════════════════════════════
     *  INITIALIZE — POST (the owner)
     *  kind=month: a month for the owner and the ticked staff (covered[]),
     *              with extra_seats seats in all (at least one per ticked person)
     *  kind=seats: add_seats more seats in the month already paid for
     * ══════════════════════════════════════════ */
    public function initialize() {
        if (!is_logged_in()) { json_response(['error' => 'Unauthorized'], 401); return; }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { json_response(['error' => 'Method not allowed'], 405); return; }
        csrf_verify();
        if (!is_owner()) { json_response(['error' => 'Only the owner can pay for Nfelia.'], 403); return; }

        $biz_id = auth_business_id();
        $user   = auth_user();
        $prices = billing_prices($this->db, $biz_id);
        $sub    = $this->get_subscription($biz_id);
        $kind   = ($_POST['kind'] ?? 'month') === 'seats' ? 'seats' : 'month';

        if ($kind === 'seats') {
            if (($sub['status'] ?? '') !== 'active' || billing_access($sub) !== 'paid') {
                json_response(['error' => 'More seats are added to a month that is already paid. Pay for a month first.'], 400); return;
            }
            $add = max(0, min(MAX_SEATS - 1 - (int)$sub['extra_seats'], (int)($_POST['add_seats'] ?? 0)));
            if ($add < 1) {
                json_response(['error' => 'Choose at least one new seat (up to ' . (MAX_SEATS - 1) . ' staff in all).'], 400); return;
            }
            $amount  = $add * $prices['seat'];
            $meta    = ['kind' => 'seats', 'add_seats' => $add];
            $covered = [];
        } else {
            $covered = billing_covered_from_post($this->db, $biz_id, $_POST['covered'] ?? []);
            $seats   = max(count($covered), min(MAX_SEATS - 1, max(0, (int)($_POST['extra_seats'] ?? 0))));
            $amount  = $prices['monthly'] + $seats * $prices['seat'];
            $meta    = ['kind' => 'month', 'extra_seats' => $seats, 'covered' => $covered];
        }

        // Mobile Money countries pay by transfer and upload the receipt
        if ($prices['method'] === 'mobile_money') {
            json_response([
                'success'           => true,
                'authorization_url' => APP_URL . '/momo/checkout?' . http_build_query([
                    'kind'    => $kind,
                    'seats'   => $kind === 'seats' ? $meta['add_seats'] : $meta['extra_seats'],
                    'covered' => implode(',', $covered),
                ]),
            ]);
            return;
        }
        if ($prices['method'] !== 'paystack') {
            json_response(['success' => true, 'authorization_url' => APP_URL . '/settings?tab=billing&manual=1']);
            return;
        }

        // A referral credit comes off a month paid in naira, once (see apply_payment)
        $referral = null;
        if ($kind === 'month' && $prices['currency'] === 'NGN') {
            require_once __DIR__ . '/ReferralController.php';
            $referral = ReferralController::available_credit($this->db, $biz_id);
        }
        $discount = $referral ? (int)$referral['discount_amount'] : 0;   // kobo
        $subunits = max(10000, (int)round($amount * 100) - $discount);

        $reference = 'NFELIA_' . $biz_id . '_' . bin2hex(random_bytes(12));
        $response  = self::api('POST', '/transaction/initialize', [
            'email'        => $user['email'],
            'amount'       => $subunits,
            'currency'     => $prices['currency'],
            'reference'    => $reference,
            'callback_url' => APP_URL . '/billing/callback',
            'metadata'     => json_encode($meta + [
                'business_id' => $biz_id,
                'amount'      => $subunits,
                'currency'    => $prices['currency'],
                'referral_id' => $referral['id'] ?? null,
            ]),
        ]);

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
     *  The reference is checked with Paystack, must belong to this business
     *  and is applied once, whoever (this page or the webhook) gets here first.
     * ══════════════════════════════════════════ */
    public function callback() {
        if (!is_logged_in()) { redirect('login'); return; }

        $reference = (string)($_GET['reference'] ?? $_GET['trxref'] ?? '');
        if ($reference === '') {
            flash('error', 'No payment reference found.');
            redirect('settings?tab=billing');
            return;
        }

        $response = self::api('GET', '/transaction/verify/' . rawurlencode($reference));
        if (!$response || !($response['status'] ?? false) || !is_array($response['data'] ?? null)) {
            flash('error', 'Payment verification failed. Please contact support.');
            redirect('settings?tab=billing');
            return;
        }
        $data = $response['data'];
        if (($data['status'] ?? '') !== 'success') {
            flash('error', 'Payment was not successful. Status: ' . ($data['status'] ?? 'unknown'));
            redirect('settings?tab=billing');
            return;
        }
        if ((int)(self::meta($data)['business_id'] ?? 0) !== (int)auth_business_id()) {
            flash('error', 'That payment was made for another account, so it can\'t be used here.');
            redirect('settings?tab=billing');
            return;
        }

        switch ($this->apply_payment((int)auth_business_id(), $data)) {
            case 'applied':
                flash('success', 'Payment received. Thank you!');
                break;
            case 'already':
                flash('success', 'This payment is already on your account.');
                break;
            default:
                flash('error', 'That payment doesn\'t match what was asked for. Please contact support.');
        }
        unset($_SESSION['paystack_reference'], $_SESSION['paystack_extra_seats']);
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

        // Without the secret key every signature would "match" an empty key
        if ($this->secret_key === '') {
            error_log('[Nfelia] Paystack webhook refused: PAYSTACK_SECRET_KEY is not set.');
            http_response_code(503);
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

        // 3. Process event. A payment is keyed by its reference, shared with
        // the callback; anything else by event and code, so a subscription's
        // create, disable and failed-payment events never shadow each other.
        if ($event_type === 'charge.success') {
            $result = $this->apply_payment($biz_id, $event_data);
            http_response_code(200);
            echo json_encode(['status' => 'ok', 'message' => $result]);
            exit;
        }

        $key = $event_type . ':' . ($event_data['subscription_code'] ?? $event_data['invoice_code'] ?? $event_data['id'] ?? hash('sha256', $raw_body));
        if (!$this->claim($biz_id, $event_type, substr($key, 0, 100), $event_data['amount'] ?? null, $event_data['status'] ?? $event_type, $event_data)) {
            http_response_code(200);
            echo json_encode(['status' => 'ok', 'message' => 'Already processed']);
            exit;
        }

        switch ($event_type) {
            case 'subscription.create':
                $this->handle_subscription_create($biz_id, $event_data);
                break;
            case 'subscription.disable':
            case 'subscription.not_renew':
                $this->handle_subscription_disable($biz_id, $event_data);
                break;
            case 'invoice.payment_failed':
                $this->handle_payment_failed($biz_id, $event_data);
                break;
            default:
                // Unknown event — logged by claim() only
                break;
        }

        http_response_code(200);
        echo json_encode(['status' => 'ok']);
        exit;
    }

    /* ══════════════════════════════════════════
     *  CANCEL SUBSCRIPTION — POST (the owner)
     *  Nfelia stays open to the end of the paid month (Tom's decision).
     * ══════════════════════════════════════════ */
    public function cancel() {
        if (!is_logged_in()) { redirect('login'); return; }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('settings?tab=billing'); return; }
        csrf_verify();

        if (!is_owner()) {
            flash('error', 'Only the business owner can manage billing.');
            redirect('settings?tab=billing');
            return;
        }

        $biz_id = auth_business_id();
        $sub    = $this->get_subscription($biz_id);

        if (!$sub || !in_array($sub['status'], ['active', 'past_due'], true)) {
            flash('error', 'No active subscription to cancel.');
            redirect('settings?tab=billing');
            return;
        }

        $reason = mb_substr(trim($_POST['cancel_reason'] ?? ''), 0, 255);
        self::stop_card_renewals($this->db, $biz_id);

        $this->db->prepare("
            UPDATE subscriptions SET
                status = 'cancelled',
                cancelled_at = NOW(),
                cancel_reason = ?
            WHERE business_id = ?
        ")->execute([$reason ?: null, $biz_id]);

        $until = !empty($sub['current_period_end']) && $sub['current_period_end'] >= date('Y-m-d')
            ? date('j M Y', strtotime($sub['current_period_end'])) : null;
        $this->claim($biz_id, 'subscription.cancelled', 'cancel_' . $biz_id . '_' . time(), null, 'cancelled', ['reason' => $reason]);
        $this->notify($biz_id, 'subscription_failed', 'Subscription Cancelled',
            $until ? "Your subscription is cancelled. Nfelia stays open until {$until}." : 'Your subscription is cancelled.',
            '/settings?tab=billing');

        flash('success', $until ? "Subscription cancelled. Nfelia and your website stay open until {$until}." : 'Subscription cancelled.');
        redirect('settings?tab=billing');
    }

    /* ══════════════════════════════════════════
     *  APPLYING A PAYMENT
     * ══════════════════════════════════════════ */

    /**
     * Apply a successful charge once: 'applied', 'already' (the callback or
     * the webhook got here first), or 'mismatch' (paid less, or in another
     * currency, than the payment we set up).
     */
    private function apply_payment(int $biz_id, array $data): string {
        $meta      = self::meta($data);
        $reference = (string)($data['reference'] ?? '');
        $paid      = (int)($data['amount'] ?? 0);
        $currency  = strtoupper((string)($data['currency'] ?? 'NGN'));
        $kind      = $meta['kind'] ?? null;

        if ($reference === '') {
            return 'mismatch';
        }
        if ($kind !== null && ($currency !== strtoupper((string)($meta['currency'] ?? '')) || $paid < (int)($meta['amount'] ?? PHP_INT_MAX))) {
            error_log("[Nfelia] Paystack payment {$reference} for business {$biz_id} does not match what was set up.");
            return 'mismatch';
        }

        $legacy_plan = $kind === null && !empty($data['plan']);
        $this->db->beginTransaction();
        try {
            if (!$this->claim($biz_id, 'charge.success', $reference, $paid, 'success', $data)) {
                $this->db->rollBack();
                return 'already';
            }
            $amount = $paid / 100;
            if ($kind === 'seats' || ($kind === null && !empty($meta['is_mid_cycle']))) {
                $add = $kind === 'seats' ? (int)($meta['add_seats'] ?? 0) : null;
                $this->db->prepare("
                    UPDATE subscriptions SET extra_seats = LEAST(?, " . ($add === null ? '?' : 'extra_seats + ?') . "),
                        paystack_customer_code = COALESCE(?, paystack_customer_code), last_payment_ref = ?, updated_at = NOW()
                    WHERE business_id = ?
                ")->execute([MAX_SEATS - 1, $add ?? (int)($meta['extra_seats'] ?? 0), $data['customer']['customer_code'] ?? null, $reference, $biz_id]);
            } elseif ($kind === 'month') {
                billing_start_month($this->db, $biz_id, $amount, (int)($meta['extra_seats'] ?? 0),
                    array_map('intval', (array)($meta['covered'] ?? [])), 'paystack', $reference);
                if (!empty($meta['referral_id'])) {
                    require_once __DIR__ . '/ReferralController.php';
                    ReferralController::use_credit($this->db, $biz_id, (int)$meta['referral_id']);
                }
            } else {
                // Set up before this change, or a card subscription that
                // Paystack renewed by itself: a month, with the seats it had
                $sub   = $this->get_subscription($biz_id);
                $seats = isset($meta['extra_seats']) ? (int)$meta['extra_seats'] : (int)($sub['extra_seats'] ?? 0);
                billing_start_month($this->db, $biz_id, $amount, $seats, null, 'paystack', $reference);
                if ((int)($meta['discount_kobo'] ?? 0) > 0) {
                    require_once __DIR__ . '/ReferralController.php';
                    ReferralController::apply_discount($this->db, $biz_id);
                }
            }
            $this->db->prepare("
                UPDATE subscriptions SET paystack_customer_code = COALESCE(?, paystack_customer_code),
                    paystack_authorization_code = COALESCE(?, paystack_authorization_code)
                WHERE business_id = ?
            ")->execute([$data['customer']['customer_code'] ?? null, $data['authorization']['authorization_code'] ?? null, $biz_id]);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[Nfelia] Paystack payment ' . $reference . ' failed to apply: ' . $e->getMessage());
            throw $e;
        }

        // Whether the shop is open follows the subscription
        storefront_purge($biz_id);

        if ($kind !== 'seats' && empty($meta['is_mid_cycle'])) {
            require_once __DIR__ . '/ReferralController.php';
            ReferralController::reward_referral($this->db, $biz_id);
        }

        $symbol = $currency === 'NGN' ? '₦' : $currency . ' ';
        $this->notify($biz_id, 'payment_received', 'Payment Confirmed',
            $symbol . number_format($paid / 100, 0) . ' received. Thank you!', '/settings?tab=billing');

        // A card subscription from before now stops renewing by itself: each
        // month the owner pays in Billing and chooses who it covers.
        if ($legacy_plan && self::stop_card_renewals($this->db, $biz_id)) {
            $this->notify($biz_id, 'subscription_activated', 'Monthly payments have changed',
                'Your card will not be charged automatically any more. Each month, pay in Billing and choose which staff the month covers.',
                '/settings?tab=billing');
        }
        return 'applied';
    }

    /**
     * Stop a card subscription Paystack would otherwise renew by itself (one
     * made before payments became month by month). True when there is none
     * left. Used when a business cancels, is suspended, or after the last
     * automatic renewal.
     */
    public static function stop_card_renewals(PDO $db, int $biz_id): bool {
        $stmt = $db->prepare("SELECT paystack_subscription_code, paystack_email_token FROM subscriptions WHERE business_id = ?");
        $stmt->execute([$biz_id]);
        $sub  = $stmt->fetch();
        $code = $sub['paystack_subscription_code'] ?? null;
        if (!$code) {
            return true;
        }
        $token = $sub['paystack_email_token'] ?? null;
        if (!$token) {
            $found = self::api('GET', '/subscription/' . rawurlencode($code));
            $token = $found['data']['email_token'] ?? null;
        }
        $done = $token && (self::api('POST', '/subscription/disable', ['code' => $code, 'token' => $token])['status'] ?? false);
        if (!$done) {
            error_log('[Nfelia] Could not stop the Paystack subscription ' . $code . ' for business ' . $biz_id);
            return false;
        }
        $db->prepare("UPDATE subscriptions SET paystack_subscription_code = NULL, paystack_email_token = NULL WHERE business_id = ?")
           ->execute([$biz_id]);
        return true;
    }

    /* ══════════════════════════════════════════
     *  WEBHOOK EVENT HANDLERS (card subscriptions made before)
     * ══════════════════════════════════════════ */

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

    /**
     * Paystack ended a card subscription. Only the one on file counts (not
     * one Nfelia stopped itself), and the month already paid still runs to
     * its end.
     */
    private function handle_subscription_disable(int $biz_id, array $data): void {
        $sub = $this->get_subscription($biz_id);
        if (!$sub || empty($data['subscription_code']) || $data['subscription_code'] !== ($sub['paystack_subscription_code'] ?? null)) {
            return;
        }
        $this->db->prepare("
            UPDATE subscriptions SET status = 'cancelled', cancelled_at = NOW(),
                paystack_subscription_code = NULL, paystack_email_token = NULL, updated_at = NOW()
            WHERE business_id = ? AND status IN ('active', 'past_due')
        ")->execute([$biz_id]);

        $this->notify($biz_id, 'subscription_failed', 'Subscription Ended',
            'Your card subscription has ended. Pay for the next month in Billing to keep using Nfelia.',
            '/settings?tab=billing');
    }

    private function handle_payment_failed(int $biz_id, array $data): void {
        $sub = $this->get_subscription($biz_id);

        if ($sub && $sub['status'] === 'active') {
            $this->db->prepare("
                UPDATE subscriptions SET
                    status = 'past_due',
                    grace_ends_at = DATE_ADD(NOW(), INTERVAL " . BILLING_GRACE_DAYS . " DAY),
                    updated_at = NOW()
                WHERE business_id = ? AND status = 'active'
            ")->execute([$biz_id]);
        }

        $this->notify($biz_id, 'subscription_failed', 'Payment Failed',
            'Your subscription payment failed. Please pay in Billing within ' . BILLING_GRACE_DAYS . ' days to keep Nfelia open.',
            '/settings?tab=billing');
    }

    /* ══════════════════════════════════════════
     *  PRIVATE HELPERS
     * ══════════════════════════════════════════ */

    private function get_subscription(int $biz_id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM subscriptions WHERE business_id = ?");
        $stmt->execute([$biz_id]);
        return $stmt->fetch() ?: null;
    }

    /** A charge's metadata as an array (Paystack may return it as a string). */
    private static function meta(array $data): array {
        $meta = $data['metadata'] ?? [];
        if (is_string($meta)) {
            $meta = json_decode($meta, true);
        }
        return is_array($meta) ? $meta : [];
    }

    private function resolve_business_id(array $data): ?int {
        $meta = self::meta($data);
        if (!empty($meta['business_id'])) {
            return (int)$meta['business_id'];
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

        $email = $data['customer']['email'] ?? null;
        if ($email) {
            $stmt = $this->db->prepare(
                "SELECT business_id FROM users WHERE email = ? AND is_active = 1 AND role = 'owner' LIMIT 1"
            );
            $stmt->execute([$email]);
            $row = $stmt->fetch();
            if ($row) return (int)$row['business_id'];
        }

        return null;
    }

    /**
     * Record an event under its key, once: false when that key was already
     * recorded (a duplicate delivery, or the callback and the webhook both
     * reporting one payment).
     */
    private function claim(int $biz_id, string $type, string $reference, $amount, string $status, $data): bool {
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
                (int)($amount ?? 0),
                $status,
                json_encode($data),
            ]);
            return true;
        } catch (\PDOException $e) {
            if ((string)$e->getCode() === '23000') {
                return false;
            }
            throw $e;
        }
    }

    // NFELIA AUDIT FIX — C07 — Fixed column name: link -> action_url — 2026-06-03
    private function notify(int $biz_id, string $type, string $title, string $message, string $link = ''): void {
        try {
            $this->db->prepare("
                INSERT INTO notifications (business_id, type, title, message, action_url, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ")->execute([$biz_id, $type, $title, $message, $link ?: null]);
        } catch (\Exception $e) {
            error_log('[Nfelia] notify() failed: ' . $e->getMessage());
        }
    }

    /**
     * Make an authenticated request to the Paystack API. PAYSTACK_API_BASE
     * points it elsewhere (a local stand-in for tests).
     */
    private static function api(string $method, string $endpoint, array $data = []): ?array {
        $url = rtrim($_ENV['PAYSTACK_API_BASE'] ?? 'https://api.paystack.co', '/') . $endpoint;

        $headers = [
            'Authorization: Bearer ' . ($_ENV['PAYSTACK_SECRET_KEY'] ?? ''),
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

        return is_array($parsed) ? $parsed : null;
    }
}
