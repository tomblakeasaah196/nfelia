<?php
/**
 * Nfelia — Settings Controller
 * Handles: Business Profile, User Profile, Staff Management, Billing.
 * Tabbed interface — all settings in one place.
 */
class SettingsController {

    private $db;
    private $biz_id;

    // Available font pairings (must match DB ENUM)
    public const FONT_OPTIONS = [
        'modern_sans'     => 'Inter + DM Sans (Modern Tech)',
        'corporate_serif' => 'EB Garamond + Questrial (Legal/Consulting)',
        'bold_display'    => 'Syne + Outfit (Creative Agency)',
        'classic_tech'    => 'Roboto Mono + Roboto (Developer/IT)',
        'elegant_serif'   => 'Playfair Display + Lato (Luxury/Retail)',
    ];

    public const CURRENCY_OPTIONS = [
        '₦' => '₦ — Nigerian Naira',
        '$' => '$ — US Dollar',
        '€' => '€ — Euro',
        '£' => '£ — British Pound',
        '¢' => '¢ — Ghanaian Cedi',
        'R' => 'R — South African Rand',
    ];

    public function __construct() {
        if (!is_logged_in()) { redirect('login'); }
        $this->db     = get_db();
        $this->biz_id = auth_business_id();
    }

    /* ══════════════════════════════════════════
     *  MAIN SETTINGS PAGE (tabbed)
     * ══════════════════════════════════════════ */
    public function index() {
        $user     = auth_user();
        $business = auth_business();

        // Refresh full business data from DB (session may not have all columns)
        // CRITICAL: We must update $_SESSION['business'] itself, because app.php
        // layout re-reads auth_business() for sidebar/header, overwriting our local $business.
        $stmt = $this->db->prepare("SELECT * FROM businesses WHERE id = ?");
        $stmt->execute([$this->biz_id]);
        $fresh = $stmt->fetch();
        if ($fresh) {
            $_SESSION['business'] = array_merge($_SESSION['business'] ?? [], $fresh);
            $business = $_SESSION['business'];
        }

        $tab      = trim($_GET['tab'] ?? 'business');

        if (!in_array($tab, ['business', 'profile', 'staff', 'billing'])) {
            $tab = 'business';
        }

        // Staff tab is owner-only
        if ($tab === 'staff' && ($user['role'] ?? 'staff') !== 'owner') {
            $tab = 'profile';
        }

        // Billing tab is owner-only
        if ($tab === 'billing' && ($user['role'] ?? 'staff') !== 'owner') {
            $tab = 'profile';
        }

        // Load subscription
        $subscription = $this->get_subscription();

        // Load staff for staff tab
        $staff = [];
        if ($tab === 'staff') {
            $stmt = $this->db->prepare("
                SELECT id, full_name, email, avatar_path, role, created_at, last_login_at
                FROM users
                WHERE business_id = ? AND is_active = 1
                ORDER BY role DESC, full_name ASC
            ");
            $stmt->execute([$this->biz_id]);
            $staff = $stmt->fetchAll();
        }

        // Payment history for billing tab
        $payment_history = [];
        $min_extra_seats = 0;
        if ($tab === 'billing') {
            $stmt = $this->db->prepare("
                SELECT * FROM subscription_history
                WHERE business_id = ? AND event_type IN ('charge.success', 'invoice.payment_failed', 'subscription.disable', 'manual_activation')
                ORDER BY created_at DESC LIMIT 20
            ");
            $stmt->execute([$this->biz_id]);
            $payment_history = $stmt->fetchAll();

            // Calculate minimum extra seats (can't go below current active staff - 1)
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE business_id = ? AND is_active = 1");
            $stmt->execute([$this->biz_id]);
            $min_extra_seats = max(0, (int)$stmt->fetchColumn() - 1);
        }

        $font_options     = self::FONT_OPTIONS;
        $currency_options = self::CURRENCY_OPTIONS;

        $title     = "Settings";
        $view_file = BASE_PATH . '/views/settings/index.php';
        require_once BASE_PATH . '/views/layouts/app.php';
    }

    /* ──────────────────────────────────────────
     *  UPDATE BUSINESS PROFILE — POST
     * ────────────────────────────────────────── */
    public function update_business() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('settings'); return; }
        csrf_verify();

        $user = auth_user();
        if (($user['role'] ?? 'staff') !== 'owner') {
            flash('error', 'Only the business owner can update business settings.');
            redirect('settings');
            return;
        }

        $name            = trim($_POST['business_name'] ?? '');
        $tagline         = trim($_POST['tagline'] ?? '');
        $phone           = trim($_POST['phone'] ?? '');
        $email           = trim($_POST['email'] ?? '');
        $address         = trim($_POST['address'] ?? '');
        $primary_color   = trim($_POST['primary_color'] ?? '#1a1a2e');
        $secondary_color = trim($_POST['secondary_color'] ?? '#c5a55a');
        $font_pairing    = trim($_POST['font_pairing'] ?? 'modern_sans');
        $currency_symbol = trim($_POST['currency_symbol'] ?? '₦');

        if ($name === '') {
            flash('error', 'Business name is required.');
            redirect('settings?tab=business');
            return;
        }

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $primary_color)) $primary_color = '#1a1a2e';
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $secondary_color)) $secondary_color = '#c5a55a';
        if (!array_key_exists($font_pairing, self::FONT_OPTIONS)) $font_pairing = 'modern_sans';
        if (!array_key_exists($currency_symbol, self::CURRENCY_OPTIONS)) $currency_symbol = '₦';

        $logo_path = null;
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            // PATCH: Align logo path with Onboarding (Tenant isolation)
            $dir = BASE_PATH . '/uploads/logos/' . $this->biz_id;
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            
            $filename = 'logo_' . time() . '.png';
            if (save_image($_FILES['logo'], $dir . '/' . $filename, 1024)) {
                $logo_path = 'uploads/logos/' . $this->biz_id . '/' . $filename;
            } else {
                flash('error', 'Logo upload failed. Max 1MB.');
                redirect('settings?tab=business');
                return;
            }
        }

        $remove_logo = isset($_POST['remove_logo']);

        $sql = "UPDATE businesses SET
                    name = ?, tagline = ?, phone = ?, email = ?,
                    address = ?, primary_color = ?, secondary_color = ?,
                    font_pairing = ?, currency_symbol = ?";
        $params = [$name, $tagline ?: null, $phone ?: null, $email ?: null,
                   $address ?: null, $primary_color, $secondary_color,
                   $font_pairing, $currency_symbol];

        $session_updates = [
            'name'            => $name,
            'primary_color'   => $primary_color,
            'secondary_color' => $secondary_color,
            'font_pairing'    => $font_pairing,
            'currency_symbol' => $currency_symbol,
            'tagline'         => $tagline, 
            'address'         => $address, 
            'phone'           => $phone, 
            'email'           => $email
        ];

        if ($logo_path) {
            $sql .= ", logo_path = ?";
            $params[] = $logo_path;
            $session_updates['logo_path'] = $logo_path;
        } elseif ($remove_logo) {
            $sql .= ", logo_path = NULL";
            $session_updates['logo_path'] = null;
        }

        $sql .= " WHERE id = ?";
        $params[] = $this->biz_id;

        $this->db->prepare($sql)->execute($params);

        // PATCH: Actually update the session so the UI reflects changes immediately
        update_business_session($session_updates);

        flash('success', 'Business profile updated.');
        redirect('settings?tab=business');
    }

    /* ──────────────────────────────────────────
     *  UPDATE USER PROFILE — POST
     * ────────────────────────────────────────── */
    public function update_profile() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('settings?tab=profile'); return; }
        csrf_verify();

        $user = auth_user();

        $full_name = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');

        if ($full_name === '' || $email === '') {
            flash('error', 'Name and email are required.');
            redirect('settings?tab=profile');
            return;
        }

        // Check email uniqueness
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ? AND id != ? AND is_active = 1");
        $stmt->execute([$email, $user['id']]);
        if ($stmt->fetch()) {
            flash('error', 'This email is already in use.');
            redirect('settings?tab=profile');
            return;
        }

        $avatar_path = null;
        $base64_data = $_POST['avatar_base64'] ?? '';

        // Priority 1: Handle Cropper.js Base64 Payload
        if (!empty($base64_data)) {
            $parts = explode(',', $base64_data);

            if (count($parts) === 2) {
                $raw_data = base64_decode($parts[1]);

                if ($raw_data !== false) {
                    // Validate that decoded data is actually an image
                    $img_info = @getimagesizefromstring($raw_data);
                    if (!$img_info || !in_array($img_info['mime'], ['image/jpeg', 'image/png', 'image/webp'])) {
                        flash('error', 'Invalid image data. Please try again.');
                        redirect('settings?tab=profile');
                        return;
                    }

                    // Reject oversized payloads (512KB decoded)
                    if (strlen($raw_data) > 512 * 1024) {
                        flash('error', 'Avatar too large. Max 512KB.');
                        redirect('settings?tab=profile');
                        return;
                    }

                    $dir = BASE_PATH . '/uploads/avatars/' . $this->biz_id;
                    if (!is_dir($dir)) mkdir($dir, 0755, true);

                    $filename = 'avatar_' . $user['id'] . '_' . time() . '.jpg';
                    $filepath = $dir . '/' . $filename;

                    // Write the validated image to the tenant directory
                    if (file_put_contents($filepath, $raw_data)) {
                        $avatar_path = 'uploads/avatars/' . $this->biz_id . '/' . $filename;
                    } else {
                        flash('error', 'Failed to save cropped avatar to the server.');
                        redirect('settings?tab=profile');
                        return;
                    }
                }
            }
        } 
        // Priority 2: Fallback to standard upload if JS failed
        elseif (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $dir = BASE_PATH . '/uploads/avatars/' . $this->biz_id;
            if (!is_dir($dir)) mkdir($dir, 0755, true);

            $filename = 'avatar_' . $user['id'] . '_' . time() . '.jpg';
            if (save_image($_FILES['avatar'], $dir . '/' . $filename, 512)) {
                $avatar_path = 'uploads/avatars/' . $this->biz_id . '/' . $filename;
            } else {
                flash('error', 'Avatar upload failed.');
                redirect('settings?tab=profile');
                return;
            }
        }

        $remove_avatar = isset($_POST['remove_avatar']);

        $sql    = "UPDATE users SET full_name = ?, email = ?";
        $params = [$full_name, $email];

        if ($avatar_path) {
            $sql .= ", avatar_path = ?";
            $params[] = $avatar_path;
        } elseif ($remove_avatar) {
            $sql .= ", avatar_path = NULL";
        }

        $sql .= " WHERE id = ?";
        $params[] = $user['id'];

        $this->db->prepare($sql)->execute($params);

        // Perfectly hydrate the session matrix
        $_SESSION['user']['full_name'] = $full_name;
        $_SESSION['user']['email']     = $email;
        if ($avatar_path) $_SESSION['user']['avatar_path'] = $avatar_path;
        if ($remove_avatar) unset($_SESSION['user']['avatar_path']);

        flash('success', 'Profile updated successfully.');
        redirect('settings?tab=profile');
    }

    /* ──────────────────────────────────────────
     *  CHANGE PASSWORD — POST
     * ────────────────────────────────────────── */
    public function change_password() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('settings?tab=profile'); return; }
        csrf_verify();

        $user = auth_user();

        $current  = $_POST['current_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if ($new_pass === '' || strlen($new_pass) < 8) {
            flash('error', 'New password must be at least 8 characters.');
            redirect('settings?tab=profile');
            return;
        }

        if ($new_pass !== $confirm) {
            flash('error', 'New passwords do not match.');
            redirect('settings?tab=profile');
            return;
        }

        // Verify current password
        $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($current, $hash)) {
            flash('error', 'Current password is incorrect.');
            redirect('settings?tab=profile');
            return;
        }

        $this->db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([
            password_hash($new_pass, PASSWORD_BCRYPT, ['cost' => 12]),
            $user['id']
        ]);

        flash('success', 'Password changed successfully.');
        redirect('settings?tab=profile');
    }

    /* ──────────────────────────────────────────
     *  STAFF: ADD MEMBER — POST
     * ────────────────────────────────────────── */
    public function add_staff() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('settings?tab=staff'); return; }
        csrf_verify();

        $user = auth_user();
        if (($user['role'] ?? 'staff') !== 'owner') {
            flash('error', 'Only the business owner can manage staff.');
            redirect('settings?tab=staff');
            return;
        }

        // Check seat limit
        $subscription = $this->get_subscription();
        $max_seats = ($subscription['user_seats'] ?? 1) + ($subscription['extra_seats'] ?? 0);

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE business_id = ? AND is_active = 1");
        $stmt->execute([$this->biz_id]);
        $current_staff = (int)$stmt->fetchColumn();

        if ($current_staff >= $max_seats) {
            flash('error', "You've reached your seat limit ({$max_seats}). Upgrade to add more team members.");
            redirect('settings?tab=staff');
            return;
        }

        $full_name = trim($_POST['staff_name'] ?? '');
        $email     = trim($_POST['staff_email'] ?? '');
        $password  = trim($_POST['staff_password'] ?? '');

        if ($full_name === '' || $email === '' || $password === '') {
            flash('error', 'Name, email, and password are required.');
            redirect('settings?tab=staff');
            return;
        }

        if (strlen($password) < 8) {
            flash('error', 'Password must be at least 8 characters.');
            redirect('settings?tab=staff');
            return;
        }

        // Check if email already exists
        $stmt = $this->db->prepare("SELECT id, business_id FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $existing = $stmt->fetch();

        if ($existing) {
            if ((int)$existing['business_id'] === $this->biz_id) {
                flash('error', 'This email is already part of your team.');
            } else {
                flash('error', 'This email is already registered. Ask them to log in or use a different email.');
            }
            redirect('settings?tab=staff');
            return;
        }

        $this->db->beginTransaction();
        try {
            // Create user linked directly to this business
            $this->db->prepare("
                INSERT INTO users (business_id, full_name, email, password_hash, role, created_at)
                VALUES (?, ?, ?, ?, 'staff', NOW())
            ")->execute([
                $this->biz_id, $full_name, $email,
                password_hash($password, PASSWORD_BCRYPT, ['cost' => 12])
            ]);

            // Notify
            $this->db->prepare("
                INSERT INTO notifications (business_id, type, title, message, action_url, created_at)
                VALUES (?, 'general', ?, ?, ?, NOW()) -- NFELIA AUDIT FIX C07 2026-06-03
            ")->execute([
                $this->biz_id,
                'New Team Member',
                $full_name . ' has been added to your team.',
                '/settings?tab=staff'
            ]);

            $this->db->commit();
            flash('success', $full_name . ' added to your team.');
        } catch (\Exception $e) {
            $this->db->rollBack();
            flash('error', 'Failed to add team member. Please try again.');
        }

        redirect('settings?tab=staff');
    }

    /* ──────────────────────────────────────────
     *  STAFF: REMOVE MEMBER — POST
     * ────────────────────────────────────────── */
    public function remove_staff($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('settings?tab=staff'); return; }
        csrf_verify();

        $user = auth_user();
        if (($user['role'] ?? 'staff') !== 'owner') {
            flash('error', 'Only the business owner can manage staff.');
            redirect('settings?tab=staff');
            return;
        }

        $staff_id = (int)$id;
        if ($staff_id === (int)$user['id']) {
            flash('error', 'You cannot remove yourself.');
            redirect('settings?tab=staff');
            return;
        }

        // Verify staff belongs to this business
        $stmt = $this->db->prepare("
            SELECT full_name FROM users
            WHERE id = ? AND business_id = ? AND is_active = 1
        ");
        $stmt->execute([$staff_id, $this->biz_id]);
        $staff_member = $stmt->fetch();

        if (!$staff_member) {
            flash('error', 'Team member not found.');
            redirect('settings?tab=staff');
            return;
        }

        // Soft deactivate (don't delete)
        $this->db->prepare("UPDATE users SET is_active = 0 WHERE id = ? AND business_id = ?")->execute([$staff_id, $this->biz_id]);

        flash('success', $staff_member['full_name'] . ' removed from your team.');
        redirect('settings?tab=staff');
    }

    /* ──────────────────────────────────────────
     *  GENERATE AUTO-LOGO SVG
     * ────────────────────────────────────────── */
    public function generate_logo() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('settings?tab=business'); return; }
        csrf_verify();

        // Fresh DB query — don't use stale session for colors/name
        $stmt = $this->db->prepare("SELECT name, primary_color, secondary_color FROM businesses WHERE id = ?");
        $stmt->execute([$this->biz_id]);
        $business = $stmt->fetch();

        $name      = $business['name'] ?? 'BIZ';
        $primary   = $business['primary_color'] ?? '#1a1a2e';
        $secondary = $business['secondary_color'] ?? '#c5a55a';

        // Extract initials (max 2 chars)
        $words = preg_split('/\s+/', trim($name));
        $initials = '';
        foreach ($words as $w) {
            $initials .= mb_strtoupper(mb_substr($w, 0, 1));
            if (mb_strlen($initials) >= 2) break;
        }
        if ($initials === '') $initials = 'N';

        // Generate SVG
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400" viewBox="0 0 400 400">
    <defs>
        <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" style="stop-color:{$primary};stop-opacity:1" />
            <stop offset="100%" style="stop-color:{$secondary};stop-opacity:1" />
        </linearGradient>
    </defs>
    <rect width="400" height="400" rx="80" fill="url(#bg)"/>
    <text x="200" y="200" text-anchor="middle" dominant-baseline="central"
          font-family="'Inter','Helvetica','Arial',sans-serif" font-weight="800"
          font-size="160" fill="white" letter-spacing="-5">{$initials}</text>
</svg>
SVG;

        // Tenant-isolated directory (matches update_business logo path)
        $upload_dir = BASE_PATH . '/uploads/logos/' . $this->biz_id;
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $svg_path = $upload_dir . '/logo_autogen.svg';
        file_put_contents($svg_path, $svg);
        $relative_path = 'uploads/logos/' . $this->biz_id . '/logo_autogen.svg';

        // Update business + refresh session
        $this->db->prepare("UPDATE businesses SET logo_path = ? WHERE id = ?")
                  ->execute([$relative_path, $this->biz_id]);
        update_business_session(['logo_path' => $relative_path]);

        flash('success', 'Auto-logo generated from your business initials.');
        redirect('settings?tab=business');
    }

    /* ══════════════════════════════════════════
     *  PRIVATE HELPERS
     * ══════════════════════════════════════════ */

    private function get_subscription(): ?array {
        $stmt = $this->db->prepare("SELECT * FROM subscriptions WHERE business_id = ?");
        $stmt->execute([$this->biz_id]);
        return $stmt->fetch() ?: null;
    }

}