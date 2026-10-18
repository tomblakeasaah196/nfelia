<?php
/**
 * Nfelia — Referral Programme Controller
 * Each business gets a unique referral code. Share it, earn 25% off (₦2,500) when
 * the referred business makes their first payment.
 *
 * Flow: generate code → share → new biz registers with code → first payment → discount earned
 */
class ReferralController {

    private $db;
    private $biz_id;

    private const DISCOUNT_KOBO = 250000; // ₦2,500

    public function __construct() {
        if (!is_logged_in()) { redirect('login'); }
        $this->db     = get_db();
        $this->biz_id = auth_business_id();
    }

    /* ══════════════════════════════════════════
     *  MAIN VIEW — Referral dashboard
     * ══════════════════════════════════════════ */
    public function index() {
        $business = auth_business();
        $code     = self::code_for($this->db, $this->biz_id);

        // Stats
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*)                                    AS total_referrals,
                SUM(status = 'converted')                   AS converted,
                SUM(status = 'rewarded')                    AS rewarded,
                SUM(status = 'rewarded' AND discount_applied = 0) AS pending_discounts,
                SUM(CASE WHEN status = 'rewarded' AND discount_applied = 0 THEN discount_amount ELSE 0 END) AS pending_discount_total
            FROM referrals WHERE referrer_business_id = ?
        ");
        $stmt->execute([$this->biz_id]);
        $stats = $stmt->fetch();

        // Referral list
        $stmt = $this->db->prepare("
            SELECT r.*, b.name AS referred_business_name
            FROM referrals r
            LEFT JOIN businesses b ON b.id = r.referred_business_id
            WHERE r.referrer_business_id = ?
            ORDER BY r.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$this->biz_id]);
        $referrals = $stmt->fetchAll();

        $referral_url = APP_URL . '/register?ref=' . urlencode($code);

        $title     = 'Referral Programme';
        $view_file = 'views/referrals/index.php';
        require_once 'views/layouts/app.php';
    }

    /* ══════════════════════════════════════════
     *  GENERATE NEW REFERRAL LINK — POST (AJAX)
     * ══════════════════════════════════════════ */
    public function create_link() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { json_response(['error' => 'Method not allowed'], 405); return; }
        csrf_verify();

        $code     = self::code_for($this->db, $this->biz_id);
        $email    = trim($_POST['referred_email'] ?? '');

        // Strict email validation if an email is provided
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(['error' => 'Invalid email address provided.'], 400); 
            return;
        }

        // PATCH: ON DUPLICATE KEY UPDATE prevents fatal crashes and refreshes the invite
        $stmt = $this->db->prepare("
            INSERT INTO referrals (referrer_business_id, referral_code, status, discount_amount, referred_email, expires_at, created_at)
            VALUES (?, ?, 'pending', ?, ?, DATE_ADD(NOW(), INTERVAL 90 DAY), NOW())
            ON DUPLICATE KEY UPDATE 
                expires_at = DATE_ADD(NOW(), INTERVAL 90 DAY),
                status = 'pending'
        ");
        $stmt->execute([$this->biz_id, $code, self::DISCOUNT_KOBO, $email ?: null]);

        json_response([
            'success' => true,
            'url'     => APP_URL . '/register?ref=' . urlencode($code),
            'message' => 'Referral link ready!'
        ]);
    }

    /* ══════════════════════════════════════════
     *  STATIC HELPERS (called from registration & payment flows)
     * ══════════════════════════════════════════ */

    /**
     * On registration: mark referral as converted.
     * Call from your registration controller when a new business is created.
     */
    public static function convert_referral(PDO $db, string $code, int $new_business_id, string $registering_email): void {
        
        // 1. First, try to find an exact email match (Strict Targeting)
        $stmt = $db->prepare("
            SELECT id FROM referrals
            WHERE referral_code = ? AND status = 'pending' AND referred_email = ? AND (expires_at IS NULL OR expires_at > NOW())
            LIMIT 1
        ");
        $stmt->execute([$code, $registering_email]);
        $ref = $stmt->fetch();

        // 2. Fallback: Find a generic pending link (where referred_email IS NULL)
        if (!$ref) {
            $stmt = $db->prepare("
                SELECT id FROM referrals
                WHERE referral_code = ? AND status = 'pending' AND referred_email IS NULL AND (expires_at IS NULL OR expires_at > NOW())
                ORDER BY created_at ASC LIMIT 1
            ");
            $stmt->execute([$code]);
            $ref = $stmt->fetch();
        }

        if ($ref) {
            // Found a valid pending invite, convert it
            $stmt = $db->prepare("
                UPDATE referrals SET status = 'converted', referred_business_id = ?, converted_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$new_business_id, $ref['id']]);
        } else {
            // 3. Spontaneous Conversion: They used a valid code, but no pending record existed.
            // Create a converted record directly to ensure the referrer gets paid.
            $stmt = $db->prepare("
                SELECT id FROM businesses WHERE referral_code = ? LIMIT 1
            ");
            $stmt->execute([$code]);
            $referrer = $stmt->fetch();

            if ($referrer) {
                $stmt = $db->prepare("
                    INSERT INTO referrals (referrer_business_id, referred_business_id, referral_code, status, discount_amount, converted_at, created_at)
                    VALUES (?, ?, ?, 'converted', ?, NOW(), NOW())
                ");
                $stmt->execute([$referrer['id'], $new_business_id, $code, self::DISCOUNT_KOBO]);
            }
        }
    }

    /**
     * On first payment: mark referral as rewarded.
     * Call from PaystackController::handle_charge_success() when a referred business pays.
     */
    public static function reward_referral(PDO $db, int $paying_business_id): void {
        $stmt = $db->prepare("
            SELECT r.id, r.referrer_business_id, r.discount_amount, b.name AS referred_name
            FROM referrals r LEFT JOIN businesses b ON b.id = r.referred_business_id
            WHERE r.referred_business_id = ? AND r.status = 'converted'
            LIMIT 1
        ");
        $stmt->execute([$paying_business_id]);
        $ref = $stmt->fetch();

        if ($ref) {
            // Once only, even when two payments arrive together.
            $stmt = $db->prepare("
                UPDATE referrals SET status = 'rewarded', rewarded_at = NOW() WHERE id = ? AND status = 'converted'
            ");
            $stmt->execute([$ref['id']]);
            if ($stmt->rowCount() !== 1) {
                return;
            }
            notify('referral.rewarded', (int)$ref['referrer_business_id'], [
                'referred' => $ref['referred_name'] ?: 'A business you invited',
                'reward'   => (float)$ref['discount_amount'] / 100,   // stored in kobo
            ], ['dedupe' => (string)$ref['id']]);
        }
    }

    /**
     * Check for available discount before billing.
     * Returns discount in kobo (0 if none).
     * Call from PaystackController before creating a charge.
     */
    public static function get_available_discount(PDO $db, int $business_id): int {
        $stmt = $db->prepare("
            SELECT id, discount_amount FROM referrals
            WHERE referrer_business_id = ? AND status = 'rewarded' AND discount_applied = 0
            ORDER BY rewarded_at ASC LIMIT 1
        ");
        $stmt->execute([$business_id]);
        $ref = $stmt->fetch();

        return $ref ? (int)$ref['discount_amount'] : 0;
    }

    /**
     * The oldest referral credit not used yet ([id, discount_amount in kobo]),
     * or null. PaystackController puts its id in the payment it sets up.
     */
    public static function available_credit(PDO $db, int $business_id): ?array {
        $stmt = $db->prepare("
            SELECT id, discount_amount FROM referrals
            WHERE referrer_business_id = ? AND status = 'rewarded' AND discount_applied = 0
            ORDER BY rewarded_at ASC LIMIT 1
        ");
        $stmt->execute([$business_id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Mark the credit a paid month used, once: a second payment set up with
     * the same credit finds it already used.
     */
    public static function use_credit(PDO $db, int $business_id, int $referral_id): bool {
        $stmt = $db->prepare("
            UPDATE referrals SET discount_applied = 1, discount_applied_at = NOW()
            WHERE id = ? AND referrer_business_id = ? AND status = 'rewarded' AND discount_applied = 0
        ");
        $stmt->execute([$referral_id, $business_id]);
        return $stmt->rowCount() === 1;
    }

    /**
     * The business's referral code, made once and kept: the link someone
     * shared last month must still work.
     */
    public static function code_for(PDO $db, int $business_id): string {
        $stmt = $db->prepare("SELECT referral_code FROM businesses WHERE id = ?");
        $stmt->execute([$business_id]);
        $code = (string)$stmt->fetchColumn();
        if ($code === '') {
            $code = self::new_code($db);
            $db->prepare("UPDATE businesses SET referral_code = ? WHERE id = ? AND referral_code IS NULL")->execute([$code, $business_id]);
            $stmt->execute([$business_id]);
            $code = (string)$stmt->fetchColumn();
        }
        if (is_logged_in() && auth_business_id() === $business_id) {
            update_business_session(['referral_code' => $code]);
        }
        return $code;
    }

    /**
     * Mark discount as applied after billing (payments set up before
     * use_credit existed).
     */
    public static function apply_discount(PDO $db, int $business_id): void {
        $stmt = $db->prepare("
            UPDATE referrals SET discount_applied = 1, discount_applied_at = NOW()
            WHERE referrer_business_id = ? AND status = 'rewarded' AND discount_applied = 0
            ORDER BY rewarded_at ASC LIMIT 1
        ");
        $stmt->execute([$business_id]);
    }

    /* ── Private helpers ─────────────────────── */

    public static function new_code(PDO $db): string {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0,O,1,I confusion
        do {
            $part1 = '';
            $part2 = '';
            for ($i = 0; $i < 3; $i++) $part1 .= $chars[random_int(0, strlen($chars) - 1)];
            for ($i = 0; $i < 4; $i++) $part2 .= $chars[random_int(0, strlen($chars) - 1)];
            $code = 'NF-' . $part1 . '-' . $part2;

            $stmt = $db->prepare("SELECT COUNT(*) FROM businesses WHERE referral_code = ?");
            $stmt->execute([$code]);
        } while ((int)$stmt->fetchColumn() > 0);

        return $code;
    }
}
