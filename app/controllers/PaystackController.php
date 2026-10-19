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
            $amount  = $add * $prices['seat_now'];
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

        $book = (self::meta($data)['kind'] ?? '') === 'book';
        switch ($this->apply_payment((int)auth_business_id(), $data)) {
            case 'applied':
                flash('success', $book ? 'Payment received. We are making your Records Book now.' : 'Payment received. Thank you!');
                break;
            case 'already':
                flash('success', 'This payment is already on your account.');
                break;
            default:
                flash('error', 'That payment doesn\'t match what was asked for. Please contact support.');
        }
        unset($_SESSION['paystack_reference'], $_SESSION['paystack_extra_seats']);
        redirect($book ? 'records' : 'settings?tab=billing');
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
            errors_report('job', 'error', 'Paystack webhook refused: PAYSTACK_SECRET_KEY is not set');
            heartbeat_finish('paystack_webhook', false, 'refused: no secret key');
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
            // Someone posting to the webhook, or PAYSTACK_SECRET_KEY no longer matches the dashboard.
            errors_report('job', 'warning', 'Paystack webhook refused: the signature did not match');
            heartbeat_finish('paystack_webhook', false, 'refused: signature mismatch');
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
            errors_report('job', 'warning', 'Paystack webhook ' . $event_type . ': no business matches it');
            heartbeat_finish('paystack_webhook', true, $event_type . ': no business matches it');
            http_response_code(200);
            echo json_encode(['status' => 'ok', 'message' => 'Business not found']);
            exit;
        }

        // 3. Process event. A payment is keyed by its reference, shared with
        // the callback; anything else by event and code, so a subscription's
        // create, disable and failed-payment events never shadow each other.
        // Something that goes wrong is recorded in Nfelia Ops and answered
        // with a 500, so Paystack sends the event again (the claim is let go).
        if ($event_type === 'charge.success') {
            try {
                $result = $this->apply_payment($biz_id, $event_data);
            } catch (\Throwable $e) {
                $this->webhook_failed($e, $event_type, $biz_id);
            }
            heartbeat_finish('paystack_webhook', true, $event_type . ': ' . $result);
            http_response_code(200);
            echo json_encode(['status' => 'ok', 'message' => $result]);
            exit;
        }

        $key = substr($event_type . ':' . ($event_data['subscription_code'] ?? $event_data['invoice_code'] ?? $event_data['id'] ?? hash('sha256', $raw_body)), 0, 100);
        if (!$this->claim($biz_id, $event_type, $key, $event_data['amount'] ?? null, $event_data['status'] ?? $event_type, $event_data)) {
            http_response_code(200);
            echo json_encode(['status' => 'ok', 'message' => 'Already processed']);
            exit;
        }

        try {
            switch ($event_type) {
                case 'subscription.create':
                    $this->handle_subscription_create($biz_id, $event_data);
                    break;
                case 'subscription.disable':
                case 'subscription.not_renew':
                    $this->handle_subscription_disable($biz_id, $event_data);
                    break;
                case 'invoice.payment_failed':
                    $this->handle_payment_failed($biz_id, $event_data, $key);
                    break;
                default:
                    // Unknown event — logged by claim() only
                    break;
            }
        } catch (\Throwable $e) {
            $this->db->prepare("DELETE FROM subscription_history WHERE paystack_reference = ?")->execute([$key]);
            $this->webhook_failed($e, $event_type, $biz_id);
        }

        heartbeat_finish('paystack_webhook', true, $event_type);
        http_response_code(200);
        echo json_encode(['status' => 'ok']);
        exit;
    }

    /** A webhook event that went wrong: recorded in Ops, and Paystack is asked to send it again. */
    private function webhook_failed(\Throwable $e, string $event_type, int $biz_id): never {
        errors_report_throwable($e, ['job' => 'Paystack webhook ' . $event_type, 'business_id' => $biz_id]);
        heartbeat_finish('paystack_webhook', false, $event_type . ' failed; Paystack will send it again');
        http_response_code(500);
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

        $open_until = !empty($sub['current_period_end']) && $sub['current_period_end'] >= date('Y-m-d') ? (string)$sub['current_period_end'] : null;
        $until = $open_until ? date('j M Y', strtotime($open_until)) : null;
        $this->claim($biz_id, 'subscription.cancelled', 'cancel_' . $biz_id . '_' . time(), null, 'cancelled', ['reason' => $reason]);
        notify('billing.cancelled', $biz_id, ['access_until' => $open_until], ['dedupe' => 'cancel:' . date('Y-m-d H:i')]);

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

        $legacy_plan = $kind === null && !empty($data['plan'])
            && !empty(($this->get_subscription($biz_id) ?? [])['paystack_subscription_code']);
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
            } elseif ($kind === 'book') {
                // A Records Book (app/helpers/records.php): it can be made now
                $this->db->prepare("UPDATE records_books SET status = 'queued', payment_ref = ?, amount = ?, currency_code = ?
                                    WHERE id = ? AND business_id = ? AND status IN ('awaiting_payment', 'cancelled')")
                   ->execute([$reference, $amount, $currency, (int)($meta['book_id'] ?? 0), $biz_id]);
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

        if ($kind === 'book') {
            require_once BASE_PATH . '/app/helpers/records.php';
            records_build_later((int)($meta['book_id'] ?? 0));
            notify('records.paid', $biz_id, ['amount_text' => self::money_text($paid, $currency)], ['dedupe' => $reference]);
            return 'applied';
        }

        // Whether the shop is open follows the subscription
        storefront_purge($biz_id);

        if ($kind !== 'seats' && empty($meta['is_mid_cycle'])) {
            require_once __DIR__ . '/ReferralController.php';
            ReferralController::reward_referral($this->db, $biz_id);
        }

        // The owner hears it went through, and until when they're covered
        $sub     = $this->get_subscription($biz_id);
        notify('billing.paid', $biz_id, [
            'amount_text' => self::money_text($paid, $currency),
            'period_end'  => $kind === 'seats' ? null : ($sub['current_period_end'] ?? null),
        ], ['dedupe' => $reference]);

        // A card subscription from before now stops renewing by itself: each
        // month the owner pays in Billing and chooses who it covers.
        if ($legacy_plan && self::stop_card_renewals($this->db, $biz_id)) {
            notify('billing.renewals_stopped', $biz_id, [], ['dedupe' => 'renewals-stopped']);
        }
        return 'applied';
    }

    /**
     * Start paying for a Records Book by card (RecordsController::make):
     * [true, the Paystack page] or [false, what went wrong]. The book is
     * made once the payment is confirmed (apply_payment, kind 'book').
     */
    public static function start_book(PDO $db, array $user, int $biz_id, int $book_id, float $amount, string $currency): array {
        $subunits  = (int)round($amount * 100);
        $reference = 'NFELIA_' . $biz_id . '_' . bin2hex(random_bytes(12));
        $response  = self::api('POST', '/transaction/initialize', [
            'email'        => $user['email'],
            'amount'       => $subunits,
            'currency'     => $currency,
            'reference'    => $reference,
            'callback_url' => APP_URL . '/billing/callback',
            'metadata'     => json_encode(['kind' => 'book', 'book_id' => $book_id, 'business_id' => $biz_id, 'amount' => $subunits, 'currency' => $currency]),
        ]);
        if (!$response || !($response['status'] ?? false) || empty($response['data']['authorization_url'])) {
            return [false, (string)($response['message'] ?? 'the payment service did not answer.')];
        }
        return [true, (string)$response['data']['authorization_url']];
    }

    /** An amount in subunits (kobo) as people read it: ₦7,500. */
    private static function money_text(int $subunits, string $currency): string {
        $symbols = ['NGN' => '₦', 'GHS' => 'GH₵', 'KES' => 'KSh ', 'ZAR' => 'R', 'USD' => '$'];
        return ($symbols[$currency] ?? $currency . ' ') . number_format($subunits / 100, $subunits % 100 ? 2 : 0);
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
        $stmt = $this->db->prepare("
            UPDATE subscriptions SET status = 'cancelled', cancelled_at = NOW(),
                paystack_subscription_code = NULL, paystack_email_token = NULL, updated_at = NOW()
            WHERE business_id = ? AND status IN ('active', 'past_due')
        ");
        $stmt->execute([$biz_id]);
        if ($stmt->rowCount() !== 1) {
            return;
        }
        // Paid time left: they keep it. None: Nfelia is paused.
        $until = !empty($sub['current_period_end']) && $sub['current_period_end'] >= date('Y-m-d') ? (string)$sub['current_period_end'] : null;
        $key   = 'disabled:' . $data['subscription_code'] . ':' . ($sub['current_period_end'] ?? '');
        if ($until) {
            notify('billing.cancelled', $biz_id, ['access_until' => $until], ['dedupe' => $key]);
        } else {
            notify('billing.ended', $biz_id, [], ['dedupe' => $key]);
        }
    }

    private function handle_payment_failed(int $biz_id, array $data, string $key): void {
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

        notify('billing.failed', $biz_id, [], ['dedupe' => $key]);
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
