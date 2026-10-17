<?php
/**
 * Nfelia — Landing Page Manager
 * Lets the business owner configure their public profile page.
 *
 * NFELIA UPDATE — Hero background media upload (image + GIF + video → MP4) — 2026-06-05
 */
class LandingPageController {

    private $db;
    private $biz_id;

    public function __construct() {
        if (!is_logged_in()) { redirect('login'); }
        $this->db     = get_db();
        $this->biz_id = auth_business_id();
    }

    /* ──────────────────────────────────────────
     *  SETTINGS — GET & POST
     * ────────────────────────────────────────── */
    public function settings() {
        $user     = auth_user();
        $business = auth_business();

        // Refresh business from DB to avoid stale session keys
        $stmtBiz = $this->db->prepare("SELECT * FROM businesses WHERE id = ?");
        $stmtBiz->execute([$this->biz_id]);
        $business = $stmtBiz->fetch();
        update_business_session((array)$business);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            return $this->save_settings();
        }

        // All active products for the picker
        $stmtP = $this->db->prepare("
            SELECT id, name, sell_price, is_service, image_path, category
            FROM products
            WHERE business_id = ? AND deleted_at IS NULL AND is_active = 1
            ORDER BY name ASC
        ");
        $stmtP->execute([$this->biz_id]);
        $all_products = $stmtP->fetchAll();

        // Current featured IDs
        $featured_ids = json_decode($business['featured_product_ids'] ?? '[]', true) ?: [];

        // The shop's own address once its link is live, a name still waiting
        // for review, and whether the one rename owners get is still unused
        $public_url   = $business['subdomain'] ? shop_url($business['subdomain']) : null;
        $shop_host    = $public_url ? $business['subdomain'] . '.' . shop_base_host() : null;
        $pending_name = $business['subdomain_status'] === 'pending' ? $business['subdomain_requested'] : null;
        $can_rename   = $public_url && (int)$business['subdomain_renames'] < 1;

        $title     = "Landing Page";
        $view_file = BASE_PATH . '/views/landing/settings.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ──────────────────────────────────────────
     *  AJAX — Hero media upload
     *  POST /landing-page/upload-hero
     *  Accepts: image/jpeg, image/png, image/webp, image/gif,
     *           video/mp4, video/quicktime, video/webm
     *  Returns: JSON { success, path, type, url }
     * ────────────────────────────────────────── */
    public function upload_hero(): void {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'POST only']);
            exit;
        }

        // Verify CSRF
        $token = $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(419);
            echo json_encode(['error' => 'CSRF mismatch']);
            exit;
        }

        if (empty($_FILES['hero_media']) || $_FILES['hero_media']['error'] !== UPLOAD_ERR_OK) {
            $err = $_FILES['hero_media']['error'] ?? 'no file';
            echo json_encode(['error' => 'Upload failed: ' . $err]);
            exit;
        }

        $file = $_FILES['hero_media'];

        // Size guard — 50MB max for video
        if ($file['size'] > 50 * 1024 * 1024) {
            echo json_encode(['error' => 'File too large. Maximum 50MB.']);
            exit;
        }

        $dest_dir  = BASE_PATH . '/uploads/heroes/' . $this->biz_id;
        $base_name = 'hero_' . time();

        $result = save_media($file, $dest_dir, $base_name);

        if (!$result) {
            echo json_encode(['error' => 'Processing failed. Check the file format and try again.']);
            exit;
        }

        // Make path relative (strip BASE_PATH prefix)
        $relative_path = ltrim(str_replace(BASE_PATH, '', $result['path']), '/');

        // Persist to DB + session immediately
        $this->db->prepare("UPDATE businesses SET hero_image_path = ? WHERE id = ?")
                  ->execute([$relative_path, $this->biz_id]);
        update_business_session(['hero_image_path' => $relative_path]);

        echo json_encode([
            'success' => true,
            'path'    => $relative_path,
            'type'    => $result['type'],             // 'image' or 'video'
            'url'     => APP_URL . '/' . $relative_path,
        ]);
        exit;
    }

    /* ──────────────────────────────────────────
     *  AJAX — Remove hero media
     *  POST /landing-page/remove-hero
     * ────────────────────────────────────────── */
    public function remove_hero(): void {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405); echo json_encode(['error' => 'POST only']); exit;
        }

        $token = $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(419); echo json_encode(['error' => 'CSRF mismatch']); exit;
        }

        $this->db->prepare("UPDATE businesses SET hero_image_path = NULL WHERE id = ?")
                  ->execute([$this->biz_id]);
        update_business_session(['hero_image_path' => null]);

        echo json_encode(['success' => true]);
        exit;
    }

    /* ──────────────────────────────────────────
     *  SAVE SETTINGS — POST
     * ────────────────────────────────────────── */
    private function save_settings(): void {
        $landing_enabled = isset($_POST['landing_enabled']) ? 1 : 0;
        $about_text      = trim($_POST['about_text'] ?? '');
        $youtube_url     = trim($_POST['youtube_url'] ?? '');
        $social_ig       = trim($_POST['social_instagram'] ?? '');
        $social_fb       = trim($_POST['social_facebook'] ?? '');
        $social_tt       = trim($_POST['social_tiktok'] ?? '');
        $social_tw       = trim($_POST['social_twitter'] ?? '');
        $featured_ids    = $_POST['featured_products'] ?? [];
        $delivery_info   = trim($_POST['delivery_info'] ?? '');

        // RFQ fields — checkboxes → JSON
        $rfq_keys   = ['quantity_range', 'budget', 'timeline', 'delivery_location', 'company_name'];
        $rfq_fields = [];
        foreach ($rfq_keys as $k) {
            if (isset($_POST['rfq_' . $k])) $rfq_fields[$k] = true;
        }
        $rfq_json = !empty($rfq_fields) ? json_encode($rfq_fields) : null;

        // Sanitize YouTube URL — extract video ID
        if ($youtube_url !== '' && !$this->is_valid_youtube($youtube_url)) {
            $youtube_url = '';
        }

        // Sanitize featured IDs — array of ints
        $featured_ids  = array_values(array_filter(array_map('intval', $featured_ids)));
        $featured_json = !empty($featured_ids) ? json_encode($featured_ids) : null;

        // Generate slug if not set
        $business = auth_business();
        $slug = $business['slug'];
        if (!$slug && $landing_enabled) {
            $slug = $this->generate_slug($business['name'] ?? $business['business_name'] ?? 'business');
        }

        $this->db->prepare("
            UPDATE businesses SET
                landing_enabled = ?,
                slug = ?,
                about_text = ?,
                youtube_url = ?,
                social_instagram = ?,
                social_facebook = ?,
                social_tiktok = ?,
                social_twitter = ?,
                featured_product_ids = ?,
                delivery_info = ?,
                rfq_fields = ?
            WHERE id = ?
        ")->execute([
            $landing_enabled,
            $slug,
            $about_text ?: null,
            $youtube_url ?: null,
            $social_ig ?: null,
            $social_fb ?: null,
            $social_tt ?: null,
            $social_tw ?: null,
            $featured_json,
            $delivery_info ?: null,
            $rfq_json,
            $this->biz_id,
        ]);

        flash('success', 'Landing page updated.');
        redirect('landing-page');
    }

    /* ── Helpers ── */

    private function is_valid_youtube(string $url): bool {
        return (bool)preg_match('/^(https?:\/\/)?(www\.)?(youtube\.com\/(watch\?v=|embed\/|shorts\/)|youtu\.be\/)[\w\-]+/', $url);
    }

    private function generate_slug(string $name): string {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        $base = $slug;
        $i = 1;
        while (true) {
            $stmt = $this->db->prepare("SELECT id FROM businesses WHERE slug = ? AND id != ?");
            $stmt->execute([$slug, $this->biz_id]);
            if (!$stmt->fetch()) break;
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}