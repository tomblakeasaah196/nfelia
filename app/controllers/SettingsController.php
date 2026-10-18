<?php
/**
 * Nfelia — Settings Controller
 * Handles: Business Profile, User Profile, Staff Management, Billing.
 * Tabbed interface — all settings in one place.
 *
 * NFELIA FIX — Currency options now driven by countries table — 2026-06-04
 * NFELIA FIX — Billing pricing now dynamic per country — 2026-06-04
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

    public function __construct() {
        if (!is_logged_in()) { redirect('login'); }
        $this->db     = get_db();
        $this->biz_id = auth_business_id();
    }

    /**
     * Build currency options from the active countries table.
     * Returns ['₦' => '🇳🇬 ₦ — Nigerian Naira', 'FCFA' => '🇨🇲 FCFA — CFA Franc BEAC', ...]
     */
    private function build_currency_options(): array {
        require_once __DIR__ . '/CountryController.php';
        $active = CountryController::get_active_countries($this->db);
        $opts = [];
        foreach ($active as $c) {
            $flag  = $c['flag_emoji'] ?? '🌐';
            $sym   = $c['currency_symbol'];
            $label = $flag . ' ' . $sym . ' — ' . ($c['currency_name'] ?: $c['currency_code']);
            // Avoid duplicate symbols (e.g. two countries sharing $)
            if (!isset($opts[$sym])) {
                $opts[$sym] = $label;
            }
        }
        // Always ensure ₦ is present as a fallback
        if (!isset($opts['₦'])) {
            $opts['₦'] = '🇳🇬 ₦ — Nigerian Naira';
        }
        return $opts;
    }

    /* ══════════════════════════════════════════
     *  MAIN SETTINGS PAGE (tabbed)
     * ══════════════════════════════════════════ */
    public function index() {
        $user     = auth_user();
        $business = auth_business();

        // Refresh full business data from DB (session may not have all columns)
        $stmt = $this->db->prepare("SELECT * FROM businesses WHERE id = ?");
        $stmt->execute([$this->biz_id]);
        $fresh = $stmt->fetch();
        if ($fresh) {
            $_SESSION['business'] = array_merge($_SESSION['business'] ?? [], $fresh);
            $business = $_SESSION['business'];
        }

        $tab = trim($_GET['tab'] ?? 'business');

        if (!in_array($tab, ['business', 'profile', 'staff', 'billing'])) {
            $tab = 'business';
        }

        if ($tab === 'staff' && ($user['role'] ?? 'staff') !== 'owner') {
            $tab = 'profile';
        }

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

        // Payment history + country pricing for billing tab
        $payment_history = [];
        $min_extra_seats = 0;
        $country_pricing = null;
        if ($tab === 'billing') {
            $stmt = $this->db->prepare("
                SELECT * FROM subscription_history
                WHERE business_id = ? AND event_type IN ('charge.success', 'invoice.payment_failed', 'subscription.disable', 'manual_activation')
                ORDER BY created_at DESC LIMIT 20
            ");
            $stmt->execute([$this->biz_id]);
            $payment_history = $stmt->fetchAll();

            $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE business_id = ? AND is_active = 1");
            $stmt->execute([$this->biz_id]);
            $min_extra_seats = max(0, (int)$stmt->fetchColumn() - 1);

            // Load country-specific pricing for this business
            require_once __DIR__ . '/CountryController.php';
            $country_pricing = CountryController::get_country_for_business($this->db, $this->biz_id);
        }

        // Paying: prices, who a month can cover, when it would run, and any
        // Mobile Money receipt still being checked
        $prices = $team = $waiting_payment = null;
        $access = billing_access($subscription);
        [$next_start, $next_end] = billing_next_period($subscription);
        if ($tab === 'billing') {
            $prices = billing_prices($this->db, $this->biz_id);
            $team   = billing_team($this->db, $this->biz_id);
            $stmt   = $this->db->prepare("SELECT * FROM subscription_payments WHERE business_id = ? AND status = 'pending_verification' ORDER BY id DESC LIMIT 1");
            $stmt->execute([$this->biz_id]);
            $waiting_payment = $stmt->fetch() ?: null;
        }

        // Fallback pricing if no country is set
        if ($tab === 'billing' && !$country_pricing) {
            $country_pricing = [
                'currency_symbol'  => '₦',
                'currency_code'    => 'NGN',
                'monthly_price'    => 10000,
                'extra_seat_price' => 5000,
                'name'             => 'Nigeria',
                'flag_emoji'       => '🇳🇬',
            ];
        }

        $font_options     = self::FONT_OPTIONS;
        $currency_options = $this->build_currency_options();

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

        // Validate currency against active countries
        $valid_currencies = $this->build_currency_options();
        if (!array_key_exists($currency_symbol, $valid_currencies)) $currency_symbol = '₦';

        $logo_path = null;
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
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

        update_business_session($session_updates);
        storefront_purge((int)$this->biz_id);   // the shop shows the name, logo and contact details

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
                    $img_info = @getimagesizefromstring($raw_data);
                    if (!$img_info || !in_array($img_info['mime'], ['image/jpeg', 'image/png', 'image/webp'])) {
                        flash('error', 'Invalid image data. Please try again.');
                        redirect('settings?tab=profile');
                        return;
                    }

                    if (strlen($raw_data) > 512 * 1024) {
                        flash('error', 'Avatar too large. Max 512KB.');
                        redirect('settings?tab=profile');
                        return;
                    }

                    $dir = BASE_PATH . '/uploads/avatars/' . $this->biz_id;
                    if (!is_dir($dir)) mkdir($dir, 0755, true);

                    $filename = 'avatar_' . $user['id'] . '_' . time() . '.jpg';
                    $filepath = $dir . '/' . $filename;

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

        $stmt = $this->db->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $old_email = (string)$stmt->fetchColumn();

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

        // A changed sign-in email: the new address, the person's phones and
        // the OLD address all hear, so a stolen account is noticed.
        if (strcasecmp($old_email, $email) !== 0) {
            notify('security.email_changed', $this->biz_id, ['new_email' => $email], ['users' => [(int)$user['id']], 'dedupe' => (string)time()]);
            notify_email(null, $old_email, $full_name, 'Your Nfelia sign-in email was changed', 'notification', [
                'headline' => 'Your sign-in email was changed',
                'message'  => 'Your Nfelia account now signs in with ' . $email . ' instead of this address. If you didn’t do this, contact Nfelia support straight away.',
                'lines'    => ['New email' => $email, 'When' => planner_now()->format('j M Y, g:ia')],
                'button'   => 'Contact support',
                'open_url' => 'mailto:support@nfelia.com',
                'always'   => true,
            ], 'email-changed:' . $user['id'] . ':' . time(), 'security.email_changed_old');
        }

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
        // Told on every device and by email, in case it wasn't them.
        notify('security.password_changed', $this->biz_id, [], ['users' => [(int)$user['id']], 'dedupe' => (string)time()]);

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

        $stmt = $this->db->prepare("SELECT id, business_id, is_active, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $existing = $stmt->fetch();

        if ($existing && ((int)$existing['is_active'] || $existing['role'] === 'owner')) {
            if ((int)$existing['business_id'] === $this->biz_id) {
                flash('error', 'This email is already part of your team.');
            } else {
                flash('error', 'This email is already registered. Ask them to log in or use a different email.');
            }
            redirect('settings?tab=staff');
            return;
        }

        $role = in_array($_POST['staff_role'] ?? '', ['admin', 'staff']) ? $_POST['staff_role'] : 'staff';
        $this->db->beginTransaction();
        try {
            if ($existing && (int)$existing['business_id'] === $this->biz_id) {
                // Someone who was removed comes back: same login, new password
                $this->db->prepare("
                    UPDATE users SET full_name = ?, password_hash = ?, role = ?, is_active = 1
                    WHERE id = ? AND business_id = ?
                ")->execute([
                    $full_name, password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), $role,
                    (int)$existing['id'], $this->biz_id,
                ]);
            } else {
                if ($existing) {
                    free_removed_email($this->db, (int)$existing['id']);   // removed from another business
                }
                $this->db->prepare("
                    INSERT INTO users (business_id, full_name, email, password_hash, role, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ")->execute([
                    $this->biz_id, $full_name, $email,
                    password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
                    $role
                ]);
            }
            $member_id = $existing && (int)$existing['business_id'] === $this->biz_id ? (int)$existing['id'] : (int)$this->db->lastInsertId();
            $this->db->commit();

            // The rest of the team hears; the new member gets a welcome with
            // how to sign in (never the password: the owner tells them that).
            notify('team.joined', $this->biz_id, ['name' => $full_name, 'role' => $role, 'by' => planner_first_name($user['full_name'])],
                   ['actor' => (int)$user['id'], 'dedupe' => (string)$member_id]);
            $business = auth_business();
            notify_email(null, $email, $full_name, 'You’ve joined ' . $business['name'] . ' on Nfelia', 'notification', [
                'headline' => 'Welcome to ' . $business['name'],
                'message'  => planner_first_name($user['full_name']) . ' added you to their team on Nfelia as ' . ($role === 'admin' ? 'an admin' : 'staff')
                            . '. Sign in with this email address and the password ' . planner_first_name($user['full_name'])
                            . ' gives you, or choose “Email me a sign-in link”.',
                'lines'    => ['Business' => $business['name'], 'Your email' => $email],
                'button'   => 'Sign in to Nfelia',
                'open_url' => APP_URL . '/login',
                'always'   => true,
            ], 'staff-welcome:' . $member_id, 'team.welcome');

            flash('success', $full_name . ' added to your team. We emailed them how to sign in.');
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log('Add staff failed: ' . $e->getMessage());
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

        $this->db->prepare("UPDATE users SET is_active = 0 WHERE id = ? AND business_id = ?")->execute([$staff_id, $this->biz_id]);
        // Their phones stop getting the business's alerts, and "keep me
        // signed in" stops working for them.
        $this->db->prepare("DELETE FROM push_subscriptions WHERE user_id = ?")->execute([$staff_id]);
        $this->db->prepare("DELETE FROM sessions WHERE user_id = ?")->execute([$staff_id]);
        notify('team.removed', $this->biz_id, ['name' => $staff_member['full_name'], 'by' => planner_first_name($user['full_name'])],
               ['actor' => (int)$user['id'], 'dedupe' => $staff_id . ':' . time()]);

        flash('success', $staff_member['full_name'] . ' removed from your team.');
        redirect('settings?tab=staff');
    }

    /* ──────────────────────────────────────────
     *  STAFF: SEND PASSWORD RESET LINK — POST
     *  The link goes to the staff member's own inbox; the owner never sees
     *  or chooses their password.
     * ────────────────────────────────────────── */
    public function send_staff_reset($id = null) {
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
            flash('error', 'To change your own password, use Change Password on your profile.');
            redirect('settings?tab=staff');
            return;
        }

        $stmt = $this->db->prepare("
            SELECT id, full_name, email FROM users
            WHERE id = ? AND business_id = ? AND is_active = 1
        ");
        $stmt->execute([$staff_id, $this->biz_id]);
        $staff_member = $stmt->fetch();

        if (!$staff_member) {
            flash('error', 'Team member not found.');
            redirect('settings?tab=staff');
            return;
        }

        if (!throttle_allows('staff_reset', (string)$staff_id, 3, 60)) {
            flash('error', 'A reset link was already sent to ' . $staff_member['full_name'] . ' recently. Ask them to check their inbox and spam folder.');
            redirect('settings?tab=staff');
            return;
        }

        // A day rather than an hour: the staff member may not see it at once.
        $token = account_token_issue($staff_id, 'reset', 60 * 24);
        $sent  = send_branded_mail(
            $staff_member['email'], $staff_member['full_name'],
            'Password Reset — Nfelia Workspace',
            'password_reset',
            [
                'reset_url'     => APP_URL . '/reset-password/' . $token,
                'expires_label' => '24 hours',
                'requested_by'  => $user['full_name'] ?? 'Your workspace owner',
            ],
            ['name' => 'Nfelia', 'primary_color' => '#8B004A']
        );

        if ($sent) {
            flash('success', 'Reset link sent to ' . $staff_member['email'] . '.');
        } else {
            flash('error', 'The email could not be sent. Please try again shortly.');
        }
        redirect('settings?tab=staff');
    }

    /* ──────────────────────────────────────────
     *  GENERATE AUTO-LOGO SVG
     * ────────────────────────────────────────── */
    public function generate_logo() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('settings?tab=business'); return; }
        csrf_verify();

        $stmt = $this->db->prepare("SELECT name, primary_color, secondary_color FROM businesses WHERE id = ?");
        $stmt->execute([$this->biz_id]);
        $business = $stmt->fetch();

        $name      = $business['name'] ?? 'BIZ';
        $primary   = $business['primary_color'] ?? '#1a1a2e';
        $secondary = $business['secondary_color'] ?? '#c5a55a';

        $words = preg_split('/\s+/', trim($name));
        $initials = '';
        foreach ($words as $w) {
            $initials .= mb_strtoupper(mb_substr($w, 0, 1));
            if (mb_strlen($initials) >= 2) break;
        }
        if ($initials === '') $initials = 'N';

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

        $upload_dir = BASE_PATH . '/uploads/logos/' . $this->biz_id;
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $svg_path = $upload_dir . '/logo_autogen.svg';
        file_put_contents($svg_path, $svg);
        $relative_path = 'uploads/logos/' . $this->biz_id . '/logo_autogen.svg';

        $this->db->prepare("UPDATE businesses SET logo_path = ? WHERE id = ?")
                  ->execute([$relative_path, $this->biz_id]);
        update_business_session(['logo_path' => $relative_path]);
        storefront_purge((int)$this->biz_id);

        flash('success', 'Auto-logo generated from your business initials.');
        redirect('settings?tab=business');
    }

    /* ──────────────────────────────────────────
     *  STAFF: UPDATE ROLE — POST (AJAX)
     * ────────────────────────────────────────── */
    public function update_role($id = null) {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'POST only']); exit;
        }

        if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) {
            http_response_code(403);
            echo json_encode(['error' => 'Security session expired. Reload the page and try again.']); exit;
        }

        $user = auth_user();
        if (($user['role'] ?? 'staff') !== 'owner') {
            http_response_code(403);
            echo json_encode(['error' => 'Only the business owner can change roles.']); exit;
        }

        $staff_id = (int)$id;
        $new_role = trim($_POST['role'] ?? '');
        $valid_roles = ['admin', 'staff'];

        if (!in_array($new_role, $valid_roles)) {
            echo json_encode(['error' => 'Invalid role.']); exit;
        }

        if ($staff_id === (int)$user['id']) {
            echo json_encode(['error' => 'You cannot change your own role.']); exit;
        }

        $stmt = $this->db->prepare("SELECT id, role FROM users WHERE id = ? AND business_id = ? AND is_active = 1");
        $stmt->execute([$staff_id, $this->biz_id]);
        $member = $stmt->fetch();

        if (!$member) {
            echo json_encode(['error' => 'Team member not found.']); exit;
        }

        if ($member['role'] === 'owner') {
            echo json_encode(['error' => 'Cannot change the owner role.']); exit;
        }

        $this->db->prepare("UPDATE users SET role = ? WHERE id = ? AND business_id = ?")
                  ->execute([$new_role, $staff_id, $this->biz_id]);

        if ($member['role'] !== $new_role) {
            notify('security.role_changed', $this->biz_id, [
                'role' => $new_role === 'admin' ? 'an admin' : 'staff', 'by' => planner_first_name($user['full_name']),
                'business' => auth_business()['name'],
            ], ['users' => [$staff_id], 'dedupe' => $staff_id . ':' . $new_role . ':' . time()]);
        }

        echo json_encode(['success' => true, 'role' => $new_role]);
        exit;
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
