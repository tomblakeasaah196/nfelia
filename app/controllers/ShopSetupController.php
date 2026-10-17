<?php
/**
 * Nfelia — Shop setup
 *
 * Every shop chooses two things: its link (name.nfelia.com) and its look.
 * Owners are sent here at their next sign-in, and new owners straight after
 * onboarding, until both are done (see auth_check.php). Staff are never sent.
 *
 * GET  /shop-setup          the screen
 * GET  /shop-setup/check    live check for a name (JSON)
 * POST /shop-setup/save     save the link and the look
 * POST /shop-setup/rename   the one rename owners get, from Landing Page
 *
 * Names that look like a bank, telco, government body or an official account
 * wait for the founder in Admin → Shop names; everything else is live at once.
 */
class ShopSetupController {

    private $db;
    private $biz_id;

    public function __construct() {
        if (!is_logged_in()) { redirect('login'); }
        $this->db     = get_db();
        $this->biz_id = (int)auth_business_id();
    }

    /* ──────────────────────────────────────────
     *  THE SCREEN — GET
     * ────────────────────────────────────────── */
    public function index() {
        if (!is_owner()) { redirect('dashboard'); }
        $business = $this->business();

        $old    = $_SESSION['shop_setup_old'] ?? [];
        $errors = $old['errors'] ?? [];
        unset($_SESSION['shop_setup_old']);

        // The layout replaces $business with the session copy, so the view
        // only gets these
        $live_name    = $business['subdomain'];
        $pending_name = $business['subdomain_status'] === 'pending' ? $business['subdomain_requested'] : null;
        $was_rejected = $business['subdomain_status'] === 'rejected';
        $first_setup  = !$live_name || !$business['storefront_look'];
        $suggestions  = $live_name ? [] : shop_name_suggestions($business['name'], $this->biz_id);
        $prefill      = $old['name'] ?? ($pending_name ?? ($suggestions[0] ?? ''));
        $current_look = $old['look']
            ?? ($business['storefront_look'] ?: (STOREFRONT_LOOK_BY_CATEGORY[$business['category'] ?? ''] ?? 'atelier'));

        $title     = $first_setup ? 'Set up your shop' : 'Your shop\'s look';
        $view_file = BASE_PATH . '/views/shop_setup/index.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ──────────────────────────────────────────
     *  LIVE NAME CHECK — GET /shop-setup/check?name=
     * ────────────────────────────────────────── */
    public function check() {
        if (!is_owner()) { json_response(['error' => 'Only the owner can choose the shop link.'], 403); }
        $result = shop_name_check((string)($_GET['name'] ?? ''), $this->biz_id);
        if (in_array($result['state'], ['invalid', 'reserved', 'taken'], true)) {
            $seed = $result['state'] === 'invalid' ? $this->business()['name'] : $result['name'];
            $result['suggestions'] = shop_name_suggestions($seed, $this->biz_id);
        }
        json_response($result);
    }

    /* ──────────────────────────────────────────
     *  SAVE LINK AND LOOK — POST /shop-setup/save
     * ────────────────────────────────────────── */
    public function save() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('shop-setup'); }
        csrf_verify();
        if (!is_owner()) { redirect('dashboard'); }
        $business = $this->business();

        $look   = (string)($_POST['look'] ?? '');
        $errors = [];
        if (!isset(STOREFRONT_LOOKS[$look])) {
            $errors['look'] = 'Choose one of the ten looks.';
        }

        // A live link is changed from Landing Page, not here. Without one, the
        // name is checked again: the live check only helps while typing.
        $result = null;
        if ($business['subdomain'] === null) {
            $result = shop_name_check((string)($_POST['shop_name'] ?? ''), $this->biz_id);
            if (!in_array($result['state'], ['ok', 'review'], true)) {
                $errors['shop_name'] = $result['message'];
            }
        }

        if ($errors) {
            $_SESSION['shop_setup_old'] = ['name' => (string)($_POST['shop_name'] ?? ''), 'look' => $look, 'errors' => $errors];
            redirect('shop-setup');
        }

        try {
            $this->db->beginTransaction();
            if ($result && $result['state'] === 'ok') {
                $this->db->prepare("
                    UPDATE businesses
                    SET subdomain = ?, subdomain_requested = NULL, subdomain_status = 'active', subdomain_requested_at = NULL
                    WHERE id = ?
                ")->execute([$result['name'], $this->biz_id]);
            } elseif ($result && $result['state'] === 'review') {
                $this->request_review($business, $result['name']);
            }
            $this->db->prepare("UPDATE businesses SET storefront_look = ? WHERE id = ?")->execute([$look, $this->biz_id]);
            $this->db->commit();
        } catch (PDOException $e) {
            $this->db->rollBack();
            // The unique indexes catch two shops taking the same name at once
            if ($e->getCode() === '23000') {
                $_SESSION['shop_setup_old'] = [
                    'name' => (string)($_POST['shop_name'] ?? ''), 'look' => $look,
                    'errors' => ['shop_name' => 'Another shop took this name a moment ago. Try another one.'],
                ];
                redirect('shop-setup');
            }
            throw $e;
        }

        unset($_SESSION['business']['shop_ready']);   // auth_check works it out again

        if (!$result) {
            // Only the look changed: the owner came from Landing Page
            flash('success', 'Your shop\'s look is saved.');
            redirect('landing-page');
        }
        if ($result['state'] === 'review') {
            flash('warning', "We're checking " . $result['name'] . '.' . shop_base_host() . '. Your shop goes live as soon as it is approved.');
        } else {
            flash('success', 'Your shop is ready at ' . $result['name'] . '.' . shop_base_host() . '.');
        }
        redirect('dashboard');
    }

    /* ──────────────────────────────────────────
     *  CHANGE THE LINK — POST /shop-setup/rename
     *  Owners get one rename after the first claim; then support does it.
     *  The old name stops working straight away and is free for others.
     * ────────────────────────────────────────── */
    public function rename() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('landing-page'); }
        csrf_verify();
        if (!is_owner()) { redirect('dashboard'); }
        $business = $this->business();

        if ($business['subdomain'] === null) {
            redirect('shop-setup');
        }
        if ((int)$business['subdomain_renames'] >= 1) {
            flash('error', "You've already changed your link once. Contact support to change it again.");
            redirect('landing-page');
        }
        if ($business['subdomain_status'] === 'pending') {
            flash('warning', "We're still checking the last name you asked for.");
            redirect('landing-page');
        }

        $result = shop_name_check((string)($_POST['shop_name'] ?? ''), $this->biz_id);
        if ($result['name'] === $business['subdomain']) {
            flash('warning', 'That is already your link.');
            redirect('landing-page');
        }
        if (!in_array($result['state'], ['ok', 'review'], true)) {
            flash('error', $result['message']);
            redirect('landing-page');
        }

        try {
            if ($result['state'] === 'ok') {
                $this->db->prepare("
                    UPDATE businesses
                    SET subdomain = ?, subdomain_renames = subdomain_renames + 1, subdomain_status = 'active'
                    WHERE id = ?
                ")->execute([$result['name'], $this->biz_id]);
                flash('success', 'Your shop link is now ' . $result['name'] . '.' . shop_base_host() . '.');
            } else {
                $this->request_review($business, $result['name']);
                flash('warning', "We're checking " . $result['name'] . '.' . shop_base_host() . '. Your current link keeps working until then.');
            }
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') { throw $e; }
            flash('error', 'Another shop took this name a moment ago. Try another one.');
        }
        redirect('landing-page');
    }

    /* ── Helpers ── */

    private function business(): array {
        $stmt = $this->db->prepare("SELECT * FROM businesses WHERE id = ?");
        $stmt->execute([$this->biz_id]);
        return $stmt->fetch();
    }

    /**
     * Park a name for the founder's approval and tell them about it. A live
     * link, if there is one, stays as it is until the decision.
     */
    private function request_review(array $business, string $name): void {
        $this->db->prepare("
            UPDATE businesses
            SET subdomain_requested = ?, subdomain_status = 'pending', subdomain_requested_at = NOW()
            WHERE id = ?
        ")->execute([$name, $this->biz_id]);

        if ($business['subdomain_requested'] !== $name) {
            shop_notify(
                SHOP_REVIEWER_BUSINESS_ID,
                'Shop name to review',
                $business['name'] . ' asked for ' . $name . '.' . shop_base_host() . '.',
                'admin/shop-names'
            );
        }
    }
}
