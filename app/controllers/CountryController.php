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
    }

    /**
     * Countries are managed in Nfelia Ops now (ops.nfelia.com/countries,
     * app/ops/controllers/OpsCountriesController.php). The old pages send
     * the Nfelia business there, and everyone else home.
     */
    public function index() { $this->moved(); }
    public function add()   { $this->moved(); }
    public function edit()  { $this->moved(); }
    public function toggle() { $this->moved(); }

    private function moved(): never {
        if ((int)auth_business_id() !== self::FOUNDER_BUSINESS_ID) {
            redirect('dashboard');
        }
        header('Location: ' . ops_url('countries'), true, 301);
        exit;
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