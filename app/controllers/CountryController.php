<?php
/**
 * Nfelia — Country Management Controller
 * Phase 3 — Multi-Country System
 *
 * Routes (all under /countries/):
 *   GET  /countries              — list all countries
 *   POST /countries/add          — add new country
 *   POST /countries/edit         — edit country (pricing, instructions, momo)
 *   POST /countries/toggle       — activate / deactivate
 *
 * Phase 3.4 — Payment routing helper: get_country_for_business()
 */
class CountryController {

    private $db;
    private const FOUNDER_BUSINESS_ID = 1;

    public function __construct() {
        if (!is_logged_in()) { redirect('login'); }
        $this->db = get_db();
        // Countries management is admin-only
        if ((int)auth_business_id() !== self::FOUNDER_BUSINESS_ID) {
            flash('error', 'Access denied.');
            redirect('dashboard');
            exit;
        }
    }

    /* ══════════════════════════════════════════
     *  LIST — GET /countries
     * ══════════════════════════════════════════ */
    public function index() {
        $user     = auth_user();
        $business = auth_business();

        $stmt = $this->db->query("
            SELECT c.*,
                   (SELECT COUNT(*) FROM businesses b WHERE b.country_id = c.id) AS business_count
            FROM countries c
            ORDER BY c.is_active DESC, c.name ASC
        ");
        $countries = $stmt->fetchAll();

        $tab       = 'countries';
        $title     = 'Admin — Countries';
        $view_file = BASE_PATH . '/views/admin/countries.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ══════════════════════════════════════════
     *  ADD — POST /countries/add
     * ══════════════════════════════════════════ */
    public function add() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('countries'); return; }
        csrf_verify();

        $name            = trim($_POST['name'] ?? '');
        $iso_code        = strtoupper(trim($_POST['iso_code'] ?? ''));
        $flag_emoji      = trim($_POST['flag_emoji'] ?? '');
        $currency_name   = trim($_POST['currency_name'] ?? '');
        $currency_code   = strtoupper(trim($_POST['currency_code'] ?? ''));
        $currency_symbol = trim($_POST['currency_symbol'] ?? '');
        $payment_method  = trim($_POST['payment_method'] ?? 'paystack');
        $payment_instructions = trim($_POST['payment_instructions'] ?? '');
        $momo_number     = trim($_POST['momo_number'] ?? '');
        $monthly_price   = (float)($_POST['monthly_price'] ?? 0);
        $extra_seat_price = (float)($_POST['extra_seat_price'] ?? 0);
        $trial_days      = max(1, (int)($_POST['trial_days'] ?? 14));
        $is_active       = isset($_POST['is_active']) ? 1 : 0;

        $valid_methods = ['paystack', 'mobile_money', 'stripe', 'manual', 'other'];
        if (!in_array($payment_method, $valid_methods)) $payment_method = 'other';

        if (!$name || strlen($iso_code) !== 2 || !$currency_code || !$currency_symbol) {
            flash('error', 'Name, ISO code (2 letters), currency code, and symbol are required.');
            redirect('countries');
            return;
        }

        try {
            $this->db->prepare("
                INSERT INTO countries
                    (name, iso_code, flag_emoji, currency_name, currency_code, currency_symbol,
                     payment_method, payment_instructions, momo_number,
                     monthly_price, extra_seat_price, trial_days, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $name, $iso_code, $flag_emoji ?: null,
                $currency_name, $currency_code, $currency_symbol,
                $payment_method,
                $payment_instructions ?: null,
                $momo_number ?: null,
                $monthly_price, $extra_seat_price,
                $trial_days, $is_active,
            ]);
            flash('success', "{$name} added successfully.");
        } catch (\PDOException $e) {
            if ($e->getCode() == 23000) {
                flash('error', "ISO code '{$iso_code}' already exists.");
            } else {
                error_log('[Nfelia] CountryController::add failed: ' . $e->getMessage());
                flash('error', 'Failed to add country. Please try again.');
            }
        }

        redirect('countries');
    }

    /* ══════════════════════════════════════════
     *  EDIT — POST /countries/edit
     *  Updates pricing, instructions, MoMo number
     * ══════════════════════════════════════════ */
    public function edit() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('countries'); return; }
        csrf_verify();

        $id                   = (int)($_POST['country_id'] ?? 0);
        $payment_instructions = trim($_POST['payment_instructions'] ?? '');
        $momo_number          = trim($_POST['momo_number'] ?? '');
        $monthly_price        = (float)($_POST['monthly_price'] ?? 0);
        $extra_seat_price     = (float)($_POST['extra_seat_price'] ?? 0);
        $payment_method       = trim($_POST['payment_method'] ?? '');
        $trial_days           = max(1, (int)($_POST['trial_days'] ?? 14));
        $currency_symbol      = trim($_POST['currency_symbol'] ?? '');
        $flag_emoji           = trim($_POST['flag_emoji'] ?? '');

        if (!$id) {
            flash('error', 'Invalid country.');
            redirect('countries');
            return;
        }

        $valid_methods = ['paystack', 'mobile_money', 'stripe', 'manual', 'other'];
        if (!in_array($payment_method, $valid_methods)) $payment_method = null;

        $sets  = ["updated_at = NOW()"];
        $params = [];

        if ($payment_instructions !== '') { $sets[] = "payment_instructions = ?"; $params[] = $payment_instructions; }
        else                              { $sets[] = "payment_instructions = NULL"; }

        if ($momo_number !== '') { $sets[] = "momo_number = ?"; $params[] = $momo_number; }
        else                     { $sets[] = "momo_number = NULL"; }

        $sets[] = "monthly_price = ?";    $params[] = $monthly_price;
        $sets[] = "extra_seat_price = ?"; $params[] = $extra_seat_price;
        $sets[] = "trial_days = ?";       $params[] = $trial_days;

        if ($payment_method) { $sets[] = "payment_method = ?"; $params[] = $payment_method; }
        if ($currency_symbol) { $sets[] = "currency_symbol = ?"; $params[] = $currency_symbol; }
        if ($flag_emoji) { $sets[] = "flag_emoji = ?"; $params[] = $flag_emoji; }

        $params[] = $id;
        $this->db->prepare("UPDATE countries SET " . implode(', ', $sets) . " WHERE id = ?")
                  ->execute($params);

        flash('success', 'Country updated.');
        redirect('countries');
    }

    /* ══════════════════════════════════════════
     *  TOGGLE — POST /countries/toggle
     * ══════════════════════════════════════════ */
    public function toggle() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['error' => 'Method not allowed'], 405);
            return;
        }
        csrf_verify();

        $id = (int)($_POST['country_id'] ?? 0);
        if (!$id) { json_response(['error' => 'Invalid ID'], 400); return; }

        $stmt = $this->db->prepare("SELECT is_active, iso_code FROM countries WHERE id = ?");
        $stmt->execute([$id]);
        $country = $stmt->fetch();

        if (!$country) { json_response(['error' => 'Not found'], 404); return; }

        // Prevent deactivating Nigeria (core market)
        if ($country['iso_code'] === 'NG') {
            json_response(['error' => 'Nigeria cannot be deactivated — it is the primary market.'], 400);
            return;
        }

        $new_status = $country['is_active'] ? 0 : 1;
        $this->db->prepare("UPDATE countries SET is_active = ?, updated_at = NOW() WHERE id = ?")
                  ->execute([$new_status, $id]);

        json_response(['success' => true, 'is_active' => $new_status]);
    }

    /* ══════════════════════════════════════════
     *  STATIC HELPERS — used by PaystackController
     *  and MoMoController for payment routing
     * ══════════════════════════════════════════ */

    /**
     * Get the active country record for a business.
     * Falls back to Nigeria if not set.
     */
    public static function get_country_for_business(PDO $db, int $business_id): ?array {
        $stmt = $db->prepare("
            SELECT c.* FROM countries c
            INNER JOIN businesses b ON b.country_id = c.id
            WHERE b.id = ? AND c.is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$business_id]);
        $country = $stmt->fetch();

        if (!$country) {
            // Fall back to Nigeria
            $stmt = $db->prepare("SELECT * FROM countries WHERE iso_code = 'NG' AND is_active = 1 LIMIT 1");
            $stmt->execute();
            $country = $stmt->fetch();
        }

        return $country ?: null;
    }

    /**
     * Get all active countries (for the currency selector UI)
     */
    public static function get_active_countries(PDO $db): array {
        $stmt = $db->query("
            SELECT id, name, iso_code, flag_emoji, currency_name, currency_code, currency_symbol,
                   payment_method, monthly_price, extra_seat_price
            FROM countries WHERE is_active = 1 ORDER BY name ASC
        ");
        return $stmt->fetchAll();
    }
}