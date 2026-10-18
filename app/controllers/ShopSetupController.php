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
 * GET  /shop-setup/preview  one look, full size, with the owner's own products
 * POST /shop-setup/save     save the link and the look
 * POST /shop-setup/rename   the one rename owners get, from Shop Studio
 *
 * Names that look like a bank, telco, government body or an official account
 * wait for the founder in Admin → Shop names; everything else is live at once.
 * A name given up in a rename stays with its shop for 90 days (shop_name_hold).
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

        // A live link is changed from Shop Studio, not here. Without one, the
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
                shop_name_release($this->biz_id, $result['name']);
            } elseif ($result && $result['state'] === 'review') {
                $this->request_review($business, $result['name']);
            }
            $this->db->prepare("UPDATE businesses SET storefront_look = ? WHERE id = ?")->execute([$look, $this->biz_id]);
            $this->apply_look($business, $look);
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
        storefront_purge($this->biz_id);

        if (!$result) {
            // Only the look changed: the owner came from Shop Studio
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
     *  ONE LOOK, FULL SIZE — GET /shop-setup/preview?look=buka
     *  For the setup screen: the shop drawn in that look with the owner's
     *  own products and words. Private and never cached; it can't take
     *  orders.
     * ────────────────────────────────────────── */
    public function preview() {
        if (!is_owner()) { redirect('dashboard'); }
        $look = (string)($_GET['look'] ?? '');
        if (!isset(STOREFRONT_LOOKS[$look])) {
            not_found();
        }
        $business = $this->business();
        $settings = storefront_settings($business, 'draft');
        $settings['look']    = $look;
        $settings['colours'] = ['set' => 'look'];
        $settings['font']    = '';

        header('Cache-Control: private, no-store');
        header('X-Robots-Tag: noindex, nofollow');
        storefront_render($business, $settings, 'look');
    }

    /* ──────────────────────────────────────────
     *  CHANGE THE LINK — POST /shop-setup/rename
     *  Owners get one rename after the first claim; then support does it.
     *  The old name stays with the shop for 90 days and sends visitors on
     *  to the new one; after that it is free for others.
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
                $this->db->beginTransaction();
                $this->db->prepare("
                    UPDATE businesses
                    SET subdomain = ?, subdomain_renames = subdomain_renames + 1, subdomain_status = 'active'
                    WHERE id = ?
                ")->execute([$result['name'], $this->biz_id]);
                shop_name_hold($this->biz_id, $business['subdomain']);
                shop_name_release($this->biz_id, $result['name']);
                $this->db->commit();
                storefront_purge($this->biz_id, [$business['subdomain']]);
                flash('success', 'Your shop link is now ' . $result['name'] . '.' . shop_base_host() . '. Your old link sends people to it for the next 90 days.');
            } else {
                $this->request_review($business, $result['name']);
                flash('warning', "We're checking " . $result['name'] . '.' . shop_base_host() . '. Your current link keeps working until then.');
            }
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            if ($e->getCode() !== '23000') { throw $e; }
            flash('error', 'Another shop took this name a moment ago. Try another one.');
        }
        redirect('landing-page');
    }

    /* ── Helpers ── */

    /**
     * Choosing a look here changes the shop straight away, as it always has:
     * Shop Studio's draft and the published settings both move to the new
     * look, with its own colours and fonts. Sections and words stay.
     */
    private function apply_look(array $business, string $look): void {
        foreach (['storefront_draft', 'storefront_published'] as $column) {
            if (empty($business[$column])) {
                continue;
            }
            $settings = storefront_normalise(json_decode((string)$business[$column], true) ?: [], $business);
            $settings['look']    = $look;
            $settings['colours'] = ['set' => 'look'];
            $settings['font']    = '';
            $this->db->prepare("UPDATE businesses SET {$column} = ? WHERE id = ?")
                     ->execute([json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $this->biz_id]);
        }
    }

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
