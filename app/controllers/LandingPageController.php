<?php
/**
 * Nfelia — Shop Studio (route /landing-page, which used to be the Landing
 * Page screen)
 *
 * Owners change their shop's look, colours, fonts and sections, with a live
 * preview of the draft. Nothing reaches shoppers until Publish: the draft is
 * businesses.storefront_draft, what shoppers see is storefront_published,
 * and every publish is kept in storefront_versions (the last ten) so an
 * older one can be brought back into the draft.
 *
 * GET  /landing-page                   the Studio (views/studio/index.php)
 * GET  /landing-page/preview           the draft, drawn as the shop (private)
 * POST /landing-page/save-draft        JSON {settings} → the cleaned draft
 * POST /landing-page/publish           draft → shoppers, keeps a version
 * GET  /landing-page/versions          the last ten publishes (JSON)
 * POST /landing-page/restore-version   a version → the draft
 * POST /landing-page/upload-image      a hero photo or video for the draft
 * POST /landing-page/location-find     places matching an address, for the map's pin
 * POST /landing-page/location-link     the pin in a pasted Google Maps link
 * POST /landing-page/visibility        switch the whole shop on or off
 * POST /landing-page/sold-out          show items with no stock left as sold out, or not
 *
 * Every POST checks the CSRF token, sent as the X-CSRF-Token header or a
 * csrf_token field. The shop link itself is changed through ShopSetupController.
 */
class LandingPageController {

    private $db;
    private $biz_id;

    public function __construct() {
        if (!is_logged_in()) { redirect('login'); }
        $this->db     = get_db();
        $this->biz_id = (int)auth_business_id();

        // The same people who see Shop Studio in the menu
        $role = strtolower(auth_user()['role'] ?? 'staff');
        if (!in_array($role, ['owner', 'admin'], true)) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' || str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
                json_response(['error' => 'Only the owner or an admin can change the shop.'], 403);
            }
            flash('error', 'Only the owner or an admin can change the shop.');
            redirect('dashboard');
        }
    }

    /* ──────────────────────────────────────────
     *  THE STUDIO — GET /landing-page
     * ────────────────────────────────────────── */
    public function settings() {
        $business = $this->business();
        update_business_session($business);

        $draft     = storefront_settings($business, 'draft');
        $published = $business['storefront_published'] ? storefront_settings($business, 'published') : null;

        $stmt = $this->db->prepare("
            SELECT id, name, sell_price, is_service, image_path, category
            FROM products
            WHERE business_id = ? AND deleted_at IS NULL AND is_active = 1
            ORDER BY name ASC
            LIMIT " . (int)MAX_PRODUCTS);
        $stmt->execute([$this->biz_id]);
        $products = $stmt->fetchAll();

        $public_url   = $business['subdomain'] ? shop_url($business['subdomain']) : null;
        $shop_host    = $public_url ? $business['subdomain'] . '.' . shop_base_host() : null;
        $pending_name = $business['subdomain_status'] === 'pending' ? $business['subdomain_requested'] : null;
        $can_rename   = $public_url && (int)$business['subdomain_renames'] < 1 && !$pending_name && is_owner();
        $unpublished  = $published === null || $published != $draft;
        $theme        = storefront_resolve($draft, $business);

        header('Cache-Control: private, no-store');
        require BASE_PATH . '/views/studio/index.php';
    }

    /* ──────────────────────────────────────────
     *  PREVIEW — GET /landing-page/preview
     *  The draft as shoppers would see it. Never cached, never indexed, and
     *  it can't place orders (storefront.js knows from $mode).
     * ────────────────────────────────────────── */
    public function preview() {
        $business = $this->business();
        header('Cache-Control: private, no-store');
        header('X-Robots-Tag: noindex, nofollow');
        // ?page= shows the shop's other pages: its list, contact page or news
        $kind = in_array($_GET['page'] ?? '', ['catalogue', 'contact', 'news'], true) ? $_GET['page'] : 'shop';
        $page = ['kind' => $kind] + ($kind === 'news' ? ['posts' => shop_posts($this->db, $this->biz_id, 60)] : []);
        storefront_render($business, storefront_settings($business, 'draft'), 'preview', 'live', $page);
    }

    /* ──────────────────────────────────────────
     *  SAVE THE DRAFT — POST /landing-page/save-draft
     *  Body: {"settings": {...}} as JSON. Answers with the settings as they
     *  were stored (cleaned, colours checked) and the colours the preview
     *  should show.
     * ────────────────────────────────────────── */
    public function save_draft() {
        $this->require_post();
        $raw = file_get_contents('php://input') ?: '';
        if (strlen($raw) > STOREFRONT_MAX_SETTINGS_BYTES) {
            json_response(['error' => 'That is more than a shop page can hold. Shorten some of the text and try again.'], 413);
        }
        $body = json_decode($raw, true);
        if (!is_array($body) || !is_array($body['settings'] ?? null)) {
            json_response(['error' => 'Nothing to save.'], 422);
        }

        $business = $this->business();
        $settings = storefront_normalise($body['settings'], $business);
        $settings = $this->keep_own_products($settings);
        $this->db->prepare("UPDATE businesses SET storefront_draft = ? WHERE id = ?")
                 ->execute([json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $this->biz_id]);

        json_response([
            'ok'          => true,
            'settings'    => $settings,
            'theme'       => $this->theme_for($settings),
            'unpublished' => $this->draft_differs($business, $settings),
        ]);
    }

    /* ──────────────────────────────────────────
     *  PUBLISH — POST /landing-page/publish
     *  The draft goes live, is kept as a version (the last ten stay), and
     *  Cloudflare is told to drop its copy of the shop.
     * ────────────────────────────────────────── */
    public function publish() {
        $this->require_post();
        $business = $this->business();
        $settings = $this->keep_own_products(storefront_settings($business, 'draft'));
        $json     = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->db->beginTransaction();
        try {
            // The directory listing and city are copied out, so the directory
            // (nfelia.com/shops) can filter shops without reading their settings
            $this->db->prepare("
                UPDATE businesses
                SET storefront_draft = ?, storefront_published = ?, storefront_published_at = NOW(), storefront_look = ?,
                    directory_opt_in = ?, city = ?, city_slug = ?
                WHERE id = ?
            ")->execute([$json, $json, $settings['look'], (int)$settings['seo']['directory'],
                         // Only a city from the list goes into the directory (W17)
                         sf_known_city($settings['seo']['city']) !== '' ? sf_known_city($settings['seo']['city']) : null,
                         sf_known_city($settings['seo']['city']) !== '' ? sf_city_slug(sf_known_city($settings['seo']['city'])) : null, $this->biz_id]);
            $this->db->prepare("INSERT INTO storefront_versions (business_id, settings, published_by) VALUES (?, ?, ?)")
                     ->execute([$this->biz_id, $json, (int)auth_user()['id']]);
            // Keep the ten newest
            $this->db->prepare("
                DELETE FROM storefront_versions
                WHERE business_id = ? AND id NOT IN (
                    SELECT id FROM (
                        SELECT id FROM storefront_versions WHERE business_id = ? ORDER BY id DESC LIMIT 10
                    ) AS newest
                )
            ")->execute([$this->biz_id, $this->biz_id]);
            $this->db->commit();
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log('[Nfelia] Shop Studio publish failed: ' . $e->getMessage());
            json_response(['error' => 'We couldn\'t publish just now. Your draft is safe; try again in a moment.'], 500);
        }

        update_business_session(['storefront_look' => $settings['look']]);
        // The map picture in the published colours, made before the shop's
        // cached pages are dropped, so shoppers never wait for it
        $location = sf_section($settings, 'location');
        if ($location && $location['on'] && $location['lat'] !== null && function_exists('after_response')) {
            $palette = storefront_resolve($settings, $business)['palette'];
            $biz_id  = $this->biz_id;
            after_response(fn() => maps_picture($biz_id, (float)$location['lat'], (float)$location['lng'], (int)$location['zoom'], $palette['dark'], $palette['accent']));
        }
        storefront_purge($this->biz_id);

        json_response([
            'ok'           => true,
            'published_at' => date('c'),
            'versions'     => $this->version_list(),
            'live'         => $business['subdomain'] !== null && (int)$business['landing_enabled'] === 1,
        ]);
    }

    /* ──────────────────────────────────────────
     *  VERSIONS — GET /landing-page/versions
     * ────────────────────────────────────────── */
    public function versions() {
        header('Cache-Control: private, no-store');
        json_response(['versions' => $this->version_list()]);
    }

    /* ──────────────────────────────────────────
     *  BRING A VERSION BACK — POST /landing-page/restore-version
     *  It becomes the draft; shoppers see it only after Publish.
     * ────────────────────────────────────────── */
    public function restore_version() {
        $this->require_post();
        $id = (int)($this->input()['id'] ?? 0);
        $stmt = $this->db->prepare("SELECT settings FROM storefront_versions WHERE id = ? AND business_id = ?");
        $stmt->execute([$id, $this->biz_id]);
        $json = $stmt->fetchColumn();
        if ($json === false) {
            json_response(['error' => 'That version is no longer kept. Only the last ten are.'], 404);
        }

        $business = $this->business();
        $settings = $this->keep_own_products(storefront_normalise(json_decode((string)$json, true) ?: [], $business));
        $this->db->prepare("UPDATE businesses SET storefront_draft = ? WHERE id = ?")
                 ->execute([json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $this->biz_id]);

        json_response([
            'ok'          => true,
            'settings'    => $settings,
            'theme'       => $this->theme_for($settings),
            'unpublished' => $this->draft_differs($business, $settings),
        ]);
    }

    /* ──────────────────────────────────────────
     *  HERO PHOTO OR VIDEO — POST /landing-page/upload-image
     *  Saved for the draft only: the live shop keeps its photo until the
     *  owner publishes. Photos (up to 20MB) are resized to 1920px; MP4 videos
     *  (up to 30MB, or less if the server takes less) are kept as they are.
     * ────────────────────────────────────────── */
    public function upload_image() {
        $this->require_post();
        // One honest limit, the smaller of 30MB and what the server takes (W18)
        $ini = trim((string)ini_get('upload_max_filesize'));
        $ini_bytes = (int)$ini * match (strtoupper(substr($ini, -1))) { 'G' => 1073741824, 'M' => 1048576, 'K' => 1024, default => 1 };
        $video_mb = (int)floor(min(30 * 1048576, $ini_bytes > 0 ? $ini_bytes : 30 * 1048576) / 1048576);
        $limits = 'Photos can be up to 20MB and videos up to ' . $video_mb . 'MB.';
        $file = $_FILES['media'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $too_big = in_array($file['error'] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
            json_response(['error' => $too_big ? 'That file is too big. ' . $limits : 'The upload didn\'t arrive. Try again.'], 422);
        }
        $mime     = (string)(mime_content_type($file['tmp_name']) ?: '');
        $is_photo = str_starts_with($mime, 'image/') && !str_ends_with($mime, 'gif');
        if ($file['size'] > ($is_photo ? 20 : $video_mb) * 1048576) {
            json_response(['error' => 'That file is too big. ' . $limits], 422);
        }

        $dir    = BASE_PATH . '/uploads/storefront/' . $this->biz_id;
        $result = save_media($file, $dir, 'hero_' . time() . '_' . bin2hex(random_bytes(3)));
        if (!$result) {
            json_response(['error' => 'Use a JPG, PNG or WebP photo, or an MP4 video.'], 422);
        }
        $rel = ltrim(str_replace(BASE_PATH, '', $result['path']), '/');
        json_response(['ok' => true, 'path' => $rel, 'url' => sf_media_url($rel), 'type' => $result['type']]);
    }

    /* ──────────────────────────────────────────
     *  MAP: FIND AN ADDRESS — POST /landing-page/location-find
     *  {address}: places that match it (Geoapify), best first, for the pin
     *  in Sections › Map and directions. The address is the business's own,
     *  from Settings; an owner who changes it here changes it there too.
     * ────────────────────────────────────────── */
    public function location_find() {
        $this->require_post();
        $address = sf_clean_text($this->input()['address'] ?? '', 500);
        if ($address === '') {
            json_response(['error' => 'Type or paste your address first.'], 422);
        }
        if (maps_key() === '') {
            json_response(['error' => 'Finding addresses isn\'t switched on yet. Paste a Google Maps link to your shop instead.'], 503);
        }
        $business = $this->business();
        $saved = false;
        if (is_owner() && $address !== trim((string)($business['address'] ?? ''))) {
            $this->db->prepare("UPDATE businesses SET address = ? WHERE id = ?")->execute([$address, $this->biz_id]);
            update_business_session(['address' => $address]);
            storefront_purge($this->biz_id);
            $saved = true;
        }
        $stmt = $this->db->prepare("SELECT c.iso_code FROM countries c JOIN businesses b ON b.country_id = c.id WHERE b.id = ?");
        $stmt->execute([$this->biz_id]);
        $places = maps_geocode($address, (string)($stmt->fetchColumn() ?: ''));
        if (!$places) {
            json_response(['error' => 'We couldn\'t find that address on the map. Add your area and town, or paste a Google Maps link to your shop.', 'saved' => $saved], 404);
        }
        json_response(['ok' => true, 'places' => $places, 'saved' => $saved]);
    }

    /* ──────────────────────────────────────────
     *  MAP: READ A LINK — POST /landing-page/location-link
     *  {link}: the pin in a Google Maps or Apple Maps link (short links are
     *  followed, to Google only), or in plain "6.45, 3.39".
     * ────────────────────────────────────────── */
    public function location_link() {
        $this->require_post();
        $link = trim((string)($this->input()['link'] ?? ''));
        if ($link === '' || mb_strlen($link) > 2000) {
            json_response(['error' => 'Paste the link to your shop from Google Maps.'], 422);
        }
        $pin = maps_resolve_pin($link);
        if ($pin === null) {
            json_response(['error' => 'We couldn\'t find a pin in that link. In Google Maps, tap your shop, then Share, then Copy link, and paste it here.'], 422);
        }
        json_response(['ok' => true, 'pin' => $pin]);
    }

    /* ──────────────────────────────────────────
     *  SHOP ON OR OFF — POST /landing-page/visibility
     * ────────────────────────────────────────── */
    public function visibility() {
        $this->require_post();
        $on = sf_bool($this->input()['on'] ?? false) ? 1 : 0;
        $this->db->prepare("UPDATE businesses SET landing_enabled = ? WHERE id = ?")->execute([$on, $this->biz_id]);
        update_business_session(['landing_enabled' => $on]);
        storefront_purge($this->biz_id);
        json_response(['ok' => true, 'on' => (bool)$on]);
    }

    /* ──────────────────────────────────────────
     *  SOLD OUT — POST /landing-page/sold-out
     *  "Show items as sold out when their stock runs out" (shop_sold_out()):
     *  goods with no stock left say so on the shop and can't be ordered.
     *  Saved straight away, like the shop's on/off switch.
     * ────────────────────────────────────────── */
    public function sold_out() {
        $this->require_post();
        $on = sf_bool($this->input()['on'] ?? false) ? 1 : 0;
        $this->db->prepare("UPDATE businesses SET shop_sold_out = ? WHERE id = ?")->execute([$on, $this->biz_id]);
        update_business_session(['shop_sold_out' => $on]);
        storefront_purge($this->biz_id);
        json_response(['ok' => true, 'on' => (bool)$on]);
    }

    /* ── Helpers ── */

    private function business(): array {
        $stmt = $this->db->prepare("SELECT * FROM businesses WHERE id = ?");
        $stmt->execute([$this->biz_id]);
        return $stmt->fetch();
    }

    /**
     * POST only, with the session's CSRF token in the X-CSRF-Token header or
     * a csrf_token field. Answers 419 otherwise, which Studio reads as
     * "reload the page".
     */
    private function require_post(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['error' => 'POST only.'], 405);
        }
        $token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''));
        if ($token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            json_response(['error' => 'Your session ran out. Reload the page to carry on; your draft is saved.'], 419);
        }
    }

    /**
     * Fields from a JSON body or an ordinary form post.
     */
    private function input(): array {
        $json = json_decode(file_get_contents('php://input') ?: '', true);
        return is_array($json) ? $json : $_POST;
    }

    /**
     * Product lists may only name this shop's own products.
     */
    private function keep_own_products(array $settings): array {
        $stmt = $this->db->prepare("SELECT id FROM products WHERE business_id = ? AND deleted_at IS NULL");
        $stmt->execute([$this->biz_id]);
        $own = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        foreach ($settings['sections'] as &$section) {
            foreach (['products', 'only', 'picks'] as $key) {
                if (isset($section[$key])) {
                    $section[$key] = array_values(array_intersect($section[$key], $own));
                }
            }
            if (isset($section['routine'])) {
                $section['routine'] = array_values(array_filter($section['routine'], fn($tag) => in_array($tag['id'], $own, true)));
            }
        }
        unset($section);
        return $settings;
    }

    private function draft_differs(array $business, array $draft): bool {
        return !$business['storefront_published'] || storefront_settings($business, 'published') != $draft;
    }

    /**
     * What Studio needs to repaint its preview: the custom properties, dark
     * or light, and the font pair with its stylesheet.
     */
    private function theme_for(array $settings): array {
        $theme = storefront_resolve($settings, $this->business());
        return [
            'vars'      => $theme['vars'],
            'dark'      => $theme['palette']['dark'],
            'pair'      => $theme['pair'],
            'fonts_url' => $theme['fonts_url'],
            'accent'    => $theme['palette']['accent'],
        ];
    }

    private function version_list(): array {
        $stmt = $this->db->prepare("
            SELECT v.id, v.published_at, v.settings, u.full_name
            FROM storefront_versions v
            LEFT JOIN users u ON u.id = v.published_by
            WHERE v.business_id = ?
            ORDER BY v.id DESC
            LIMIT 10
        ");
        $stmt->execute([$this->biz_id]);
        $looks = storefront_looks();
        $list  = [];
        foreach ($stmt->fetchAll() as $row) {
            $settings = json_decode((string)$row['settings'], true) ?: [];
            $list[] = [
                'id'           => (int)$row['id'],
                'published_at' => $row['published_at'],
                'when'         => date('j M Y, g:ia', strtotime($row['published_at'])),
                'look'         => $looks[$settings['look'] ?? '']['name'] ?? 'Unknown look',
                'by'           => (string)($row['full_name'] ?? ''),
            ];
        }
        return $list;
    }
}
