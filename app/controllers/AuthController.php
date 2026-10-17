<?php
/**
 * Nfelia — Auth Controller
 * Handles Registration, Login, and Logout
 */

class AuthController {

    public function __construct() {
        // Initialize CSRF token if it doesn't exist using our new helper
        if (empty($_SESSION['csrf_token'])) {
            csrf_regenerate();
        }
    }

    /**
     * Display Landing Page / Login Form
     */
    public function index() {
        if (is_logged_in()) {
            redirect('dashboard');
        }
        require_once BASE_PATH . '/views/auth/login.php';
    }

    /**
     * Handle User Login
     */
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('login');
        }

        // CSRF Check using helper
        csrf_verify();

        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';

        $db = get_db();
        // PATCH: Select ALL business data explicitly
        $stmt = $db->prepare("
            SELECT u.*,
                   b.name as business_name, b.slug, b.doc_prefix,
                   b.primary_color, b.secondary_color, b.logo_path,
                   b.onboarding_complete, b.onboarding_step,
                   b.currency_symbol, b.font_pairing,
                   b.tagline, b.address, b.phone as business_phone, b.email as business_email,
                   b.business_type, b.email_verified_at
            FROM users u 
            JOIN businesses b ON b.id = u.business_id 
            WHERE u.email = ? AND u.is_active = 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($pass, $user['password_hash'])) {
            // PATCH: Perfectly map EVERY column to the session
            login_user(
                [
                    'id'          => $user['id'], 
                    'full_name'   => $user['full_name'], 
                    'email'       => $user['email'], 
                    'role'        => $user['role'],
                    'avatar_path' => $user['avatar_path'] // Fixes disappearing avatar
                ],
                [
                    'id'                  => $user['business_id'], 
                    'name'                => $user['business_name'], 
                    'slug'                => $user['slug'],
                    'doc_prefix'          => $user['doc_prefix'],
                    'primary_color'       => $user['primary_color'],
                    'secondary_color'     => $user['secondary_color'],
                    'logo_path'           => $user['logo_path'],
                    'tagline'             => $user['tagline'],         // Fixes missing details
                    'address'             => $user['address'],         // Fixes missing details
                    'phone'               => $user['business_phone'],  // Fixes missing details
                    'email'               => $user['business_email'],  // Fixes missing details
                    'onboarding_complete' => $user['onboarding_complete'],
                    'onboarding_step'     => $user['onboarding_step'],
                    'currency_symbol'     => $user['currency_symbol'],
                    'font_pairing'        => $user['font_pairing'],
                    'email_verified_at'   => $user['email_verified_at']
                ]
            );

            // Update last login
            $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);

            // Remember-me: create persistent session if checkbox is checked
            if (!empty($_POST['remember_me'])) {
                $remember_token = bin2hex(random_bytes(64));
                $expires_at     = date('Y-m-d H:i:s', strtotime('+30 days'));
                
                $db->prepare("
                    INSERT INTO sessions (user_id, business_id, session_token, ip_address, user_agent, expires_at)
                    VALUES (?, ?, ?, ?, ?, ?)
                ")->execute([
                    $user['id'],
                    $user['business_id'],
                    $remember_token,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $_SERVER['HTTP_USER_AGENT'] ?? null,
                    $expires_at
                ]);

                // Secure, HttpOnly, SameSite cookie — 30 days
                setcookie('nfelia_remember', $remember_token, [
                    'expires'  => strtotime('+30 days'),
                    'path'     => '/',
                    'secure'   => true,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            }

            redirect('dashboard');
        } else {
            flash('error', 'Invalid email or password combination.');
            redirect('login');
        }
    }

    /**
     * Handle Pilot Registration (The 3-Step Create)
     */
    public function register() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('login');
        }

        // CSRF Check using helper
        csrf_verify();

        $biz_name         = trim($_POST['business_name'] ?? '');
        $full_name        = trim($_POST['full_name'] ?? '');
        $email            = trim($_POST['email'] ?? '');
        $password         = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        if (empty($biz_name) || empty($full_name) || empty($email) || strlen($password) < 8) {
            flash('error', 'All fields are required. Password must be 8+ characters.');
            redirect('login');
        }

        if ($password !== $password_confirm) {
            flash('error', 'Passwords do not match. Please re-enter.');
            redirect('login');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Please provide a valid email address.');
            redirect('login');
        }

        $db = get_db();

        // 1. Check if email already exists
        $check = $db->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            flash('error', 'This email is already registered with a workspace.');
            redirect('login');
        }

        try {
            $db->beginTransaction();

            // 2. Create the Business & Ensure Unique Slug
            $slug      = slugify($biz_name);
            $base_slug = $slug;
            $counter   = 1;
            
            while (true) {
                $slugCheck = $db->prepare("SELECT id FROM businesses WHERE slug = ?");
                $slugCheck->execute([$slug]);
                if (!$slugCheck->fetch()) break; // Slug is free
                $slug = $base_slug . '-' . $counter;
                $counter++;
            }

            $prefix = derive_prefix($biz_name);
            
            $stmtBiz = $db->prepare("
                INSERT INTO businesses (name, slug, doc_prefix, email, primary_color, secondary_color) 
                VALUES (?, ?, ?, ?, '#8B004A', '#B69F5E')
            ");
            $stmtBiz->execute([$biz_name, $slug, $prefix, $email]);
            $business_id = $db->lastInsertId();

            // 3. Create the Owner User
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmtUser = $db->prepare("
                INSERT INTO users (business_id, full_name, email, password_hash, role, is_email_verified) 
                VALUES (?, ?, ?, ?, 'owner', 1)
            ");
            $stmtUser->execute([$business_id, $full_name, $email, $hash]);
            $user_id = $db->lastInsertId();

            // 4. Initialize the Subscription (14-day trial)
            $trial_expiry = date('Y-m-d', strtotime('+14 days'));
            $stmtSub = $db->prepare("
                INSERT INTO subscriptions (business_id, plan, status, trial_ends_at) 
                VALUES (?, 'trial', 'trial', ?)
            ");
            $stmtSub->execute([$business_id, $trial_expiry]);

            // 5. Initialize document number counters for all 4 document types
            $stmtCounters = $db->prepare("
                INSERT INTO document_counters (business_id, doc_type, last_number) VALUES
                (?, 'QUO', 0),
                (?, 'INV', 0),
                (?, 'REC', 0),
                (?, 'DEL', 0)
            ");
            $stmtCounters->execute([$business_id, $business_id, $business_id, $business_id]);

            // 6. Generate a unique referral code
            $referral_code = 'NF-' . strtoupper($prefix) . '-' . strtoupper(bin2hex(random_bytes(2)));
            $stmtRef = $db->prepare("
                INSERT INTO referrals (referrer_business_id, referral_code, status) 
                VALUES (?, ?, 'pending')
            ");
            $stmtRef->execute([$business_id, $referral_code]);

            $db->commit();

            // PATCH: Process Referral Code if present
            $ref_code = trim($_POST['ref_code'] ?? '');
            if (!empty($ref_code)) {
                require_once BASE_PATH . '/app/controllers/ReferralController.php'; // NFELIA AUDIT FIX H03 — 2026-06-03
                // Pass the new business ID and their email to enforce identity matching
                ReferralController::convert_referral($db, $ref_code, $business_id, $email);
            }

            // Auto-login after registration
            login_user(
                ['id' => $user_id, 'full_name' => $full_name, 'email' => $email, 'role' => 'owner'],
                [
                    'id'                  => $business_id, 
                    'name'                => $biz_name, 
                    'slug'                => $slug, 
                    'doc_prefix'          => $prefix, 
                    'primary_color'       => '#8B004A', 
                    'secondary_color'     => '#B69F5E', 
                    'logo_path'           => null, 
                    'onboarding_complete' => 0,
                    'onboarding_step'     => 1,
                    'currency_symbol'     => '₦',
                    'font_pairing'        => 'modern_sans'
                ]
            );

            // Send welcome + email verification
            try {
                $verify_token  = bin2hex(random_bytes(32));
                $expires_at    = date('Y-m-d H:i:s', strtotime('+48 hours'));
                $db->prepare("
                    INSERT INTO email_verifications (user_id, business_id, token, expires_at)
                    VALUES (?, ?, ?, ?)
                ")->execute([$user_id, $business_id, $verify_token, $expires_at]);

                $verify_url = APP_URL . '/verify-email/' . $verify_token;
                send_branded_mail(
                    $email, $full_name,
                    'Welcome to Nfelia — Verify Your Email',
                    'welcome',
                    [
                        'owner_name' => $full_name,
                        'biz_name'   => $biz_name,
                        'trial_days' => TRIAL_DAYS,
                        'verify_url' => $verify_url,
                    ],
                    [
                        'name'          => 'Nfelia',
                        'primary_color' => '#8B004A',
                        'tagline'       => 'Track everything. Carry nothing.',
                    ]
                );
            } catch (\Exception $mail_e) {
                error_log('[Nfelia] Welcome email failed: ' . $mail_e->getMessage());
                // Non-fatal — registration still succeeds
            }

            redirect('onboarding/step1');

        } catch (Exception $e) {
            $db->rollBack();
            
            error_log("Registration Failed: " . $e->getMessage());
            flash('error', 'System error during deployment. Please contact support.');
            redirect('login');
        }
    }
    
    /**
     * Forgot Password — 3-State Recovery Flow
     *
     * NFELIA FIX — Now supports ALL business users (staff, admin, owner) — 2026-06-05
     *
     * State 1: Enter business name → fuzzy match → find ALL users
     * State 2: Select your account from masked email list (NEW)
     * State 3: Type full email to verify → send reset link
     */
    /**
     * Issue a reset token for $email, mail the link, and send the visitor on
     * to the "Transmission Sent" screen. Always redirects; never returns.
     *
     * Shared by both entry points so the single-account and multi-account
     * paths cannot drift apart.
     */
    private function dispatch_reset(PDO $db, string $email): void {
        $token        = bin2hex(random_bytes(32));
        $hashed_token = hash('sha256', $token);

        $db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
        $db->prepare("INSERT INTO password_resets (email, token) VALUES (?, ?)")->execute([$email, $hashed_token]);

        $reset_link = APP_URL . "/reset-password/" . $token;

        $parts  = explode("@", $email);
        $prefix = $parts[0] ?? 'user';
        $domain = $parts[1] ?? 'domain.com';
        $masked = substr($prefix, 0, 3) . str_repeat('*', max(3, strlen($prefix) - 3));

        $_SESSION['recovery_masked'] = $masked . "@" . $domain;
        $_SESSION['recovery_domain'] = strtolower($domain);

        // Nothing identifying survives into the next request.
        unset($_SESSION['recovery_target_email'],
              $_SESSION['recovery_masked_hint'],
              $_SESSION['recovery_accounts']);

        send_branded_mail(
            $email, 'Team Member',
            'Password Reset — Nfelia Workspace',
            'password_reset',
            ['reset_url' => $reset_link],
            ['name' => 'Nfelia', 'primary_color' => '#8B004A']
        );

        session_write_close();
        redirect('recovery-sent');
    }

    public function forgot_password() {
        $db = get_db();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();

            // =========================================================
            // STATE 2: Account Selection (User picks their masked email)
            // =========================================================
            if (isset($_POST['select_account'])) {
                $idx = (int)($_POST['account_index'] ?? -1);
                $accounts = $_SESSION['recovery_accounts'] ?? [];

                if ($idx < 0 || $idx >= count($accounts) || empty($accounts[$idx]['email'])) {
                    flash('error', 'Invalid selection. Please try again.');
                    redirect('forgot-password');
                }

                // Send the reset straight away. The link only ever goes to the
                // address already on file, so an extra confirmation step added
                // friction without changing where the email lands.
                $this->dispatch_reset($db, $accounts[$idx]['email']);
            }

            // =========================================================
            // STATE 1: Identify Workspace (80% Fuzzy Name Match)
            // =========================================================
            if (isset($_POST['search_enterprise'])) {
                $search_name = strtolower(trim($_POST['enterprise_name'] ?? ''));

                // Find the matching business (not just owner — match by business name)
                $stmt = $db->query("
                    SELECT b.id, b.name 
                    FROM businesses b 
                    WHERE b.id IN (SELECT DISTINCT business_id FROM users WHERE is_active = 1)
                ");
                
                $matched_business_id = null;
                while ($row = $stmt->fetch()) {
                    similar_text($search_name, strtolower($row['name']), $percent);
                    if ($percent >= 80) {
                        $matched_business_id = (int)$row['id'];
                        break;
                    }
                }

                if ($matched_business_id) {
                    // Get ALL active users in this business
                    $stmtUsers = $db->prepare("
                        SELECT id, full_name, email, role 
                        FROM users 
                        WHERE business_id = ? AND is_active = 1
                        ORDER BY FIELD(role, 'owner', 'admin', 'staff'), full_name ASC
                    ");
                    $stmtUsers->execute([$matched_business_id]);
                    $users = $stmtUsers->fetchAll();

                    if (count($users) === 1) {
                        // Only one account in the workspace — nothing to choose.
                        $this->dispatch_reset($db, $users[0]['email']);
                    } else {
                        // Multiple users — build account list for State 2
                        $accounts = [];
                        foreach ($users as $u) {
                            $parts = explode("@", $u['email']);
                            $prefix_length = strlen($parts[0]);
                            $visible_chars = min(3, max(1, $prefix_length - 1));
                            $masked_name = substr($parts[0], 0, $visible_chars) . str_repeat('*', 5);

                            $accounts[] = [
                                'email'        => $u['email'],
                                'name'         => $u['full_name'],
                                'role'         => $u['role'],
                                'masked_email' => $masked_name . "@" . $parts[1],
                            ];
                        }
                        $_SESSION['recovery_accounts'] = $accounts;
                    }
                } else {
                    flash('error', 'Enterprise not found in the directory. Please check spelling.');
                }
                
                session_write_close();
                redirect('forgot-password');
            }
        }

        require_once BASE_PATH . '/views/auth/forgot-password.php';
    }

    /**
     * Display the "Transmission Sent" Screen
     */
    public function recovery_sent() {
        if (empty($_SESSION['recovery_masked'])) {
            redirect('login');
        }
        require_once BASE_PATH . '/views/auth/recovery-sent.php';
    }

    /**
     * Handle Password Reset Validation & Update
     */
    public function reset_password($token = null) {
        if (!$token && $_SERVER['REQUEST_METHOD'] === 'GET') {
            redirect('login');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $token    = $_POST['token'] ?? '';
            $password = $_POST['password'] ?? '';
            $confirm  = $_POST['password_confirm'] ?? '';

            if (strlen($password) < 8 || $password !== $confirm) {
                flash('error', 'Passkeys do not match or fail security protocols.');
                redirect("reset-password/$token");
            }

            $db = get_db();
            $hashed_token = hash('sha256', $token);

            $stmt = $db->prepare("SELECT email FROM password_resets WHERE token = ? AND created_at > NOW() - INTERVAL 1 HOUR");
            $stmt->execute([$hashed_token]);
            $reset = $stmt->fetch();

            if ($reset) {
                $new_hash = password_hash($password, PASSWORD_DEFAULT);
                $db->prepare("UPDATE users SET password_hash = ? WHERE email = ?")->execute([$new_hash, $reset['email']]);
                $db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$reset['email']]);

                flash('success', 'Security clearance updated. You may now access the workspace.');
                redirect('login');
            } else {
                flash('error', 'Recovery token invalid or expired. Please initiate a new request.');
                redirect('forgot-password');
            }
        }

        require_once BASE_PATH . '/views/auth/reset-password.php';
    }
    
    /**
     * Handle General Feedback from Landing Page (Prospects)
     * Records intent in general_feedback and alerts the Director.
     */
    public function public_feedback() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('login');
        }

        // CSRF Check using helper
        csrf_verify();

        // Capture and Cleanse Inputs
        $enterprise = trim($_POST['enterprise'] ?? '');
        $contact    = trim($_POST['contact'] ?? ''); // WhatsApp/Phone field
        $message    = trim($_POST['message'] ?? '');
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;

        // Basic Validation
        if (empty($enterprise) || empty($message)) {
            flash('error', 'Structural Error: Enterprise name and message are required.');
            redirect('login');
        }

        $db = get_db();

        try {
            // Start transaction to ensure both records are saved or none
            $db->beginTransaction();

            // Insert into General Feedback vault
            $stmt = $db->prepare("
                INSERT INTO general_feedback (enterprise_name, contact_info, message, ip_address) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$enterprise, $contact, $message, $ip_address]);

            // Generate Director Notification (Business ID 1)
            $admin_biz_id = 1; 
            $notif_title  = "New Structural Request";
            $notif_body   = "Prospect ($enterprise) sent a message: " . substr($message, 0, 150) . "...";
            
            $stmtNotif = $db->prepare("
                INSERT INTO notifications (business_id, type, title, message, is_read) 
                VALUES (?, 'general', ?, ?, 0)
            ");
            $stmtNotif->execute([$admin_biz_id, $notif_title, $notif_body]);

            // Finalize both inserts
            $db->commit();
            
            // Feedback to the User
            flash('success', 'Transmission Received. The Director has been alerted to your request.');
            redirect('login');
            
        } catch (Exception $e) {
            // Roll back if anything fails
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            
            error_log("General Feedback Error: " . $e->getMessage());
            flash('error', 'System failure during transmission. Please use direct support.');
            redirect('login');
        }
    }

    /**
     * Handle Logout
     */
    public function logout() {
        // CSRF Check for the POST logout submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
        }

        // Delete the sessions table row if remember-me was active
        if (isset($_COOKIE['nfelia_remember'])) {
            $db = get_db();
            $db->prepare("DELETE FROM sessions WHERE session_token = ?")
               ->execute([$_COOKIE['nfelia_remember']]);
        }

        logout_user();
        flash('success', 'Workspace session closed.');
        redirect('login');
    }

    /**
     * Handle email verification link click
     */
    public function verify_email($token = null) {
        if (!$token) { redirect('login'); return; }

        $db = get_db();
        $stmt = $db->prepare("
            SELECT ev.*, u.email FROM email_verifications ev
            JOIN users u ON u.id = ev.user_id
            WHERE ev.token = ? AND ev.expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $ev = $stmt->fetch();

        if (!$ev) {
            flash('error', 'Verification link is invalid or has expired. Please request a new one.');
            redirect('login');
            return;
        }

        // Mark business as email verified
        $db->prepare("UPDATE businesses SET email_verified_at = NOW() WHERE id = ?")
           ->execute([$ev['business_id']]);

        // Delete the used token
        $db->prepare("DELETE FROM email_verifications WHERE id = ?")->execute([$ev['id']]);

        // Update session
        if (is_logged_in()) {
            update_business_session(['email_verified_at' => date('Y-m-d H:i:s')]);
        }

        flash('success', 'Email verified. Your workspace is fully unlocked.');
        redirect(is_logged_in() ? 'dashboard' : 'login');
    }

    /**
     * Resend verification email (POST, authenticated)
     */
    public function resend_verification() {
        if (!is_logged_in()) { redirect('login'); return; }
        csrf_verify();

        $user     = auth_user();
        $business = auth_business();

        if (!empty($business['email_verified_at'])) {
            flash('success', 'Your email is already verified.');
            redirect('dashboard');
            return;
        }

        $db = get_db();
        // Delete any existing tokens for this user
        $db->prepare("DELETE FROM email_verifications WHERE user_id = ?")->execute([$user['id']]);

        $token      = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+48 hours'));
        $db->prepare("INSERT INTO email_verifications (user_id, business_id, token, expires_at) VALUES (?, ?, ?, ?)")
           ->execute([$user['id'], auth_business_id(), $token, $expires_at]);

        $verify_url = APP_URL . '/verify-email/' . $token;
        send_branded_mail(
            $user['email'], $user['full_name'],
            'Verify Your Email — Nfelia',
            'verification',
            ['owner_name' => $user['full_name'], 'verify_url' => $verify_url, 'expires_hours' => 48],
            $business
        );

        flash('success', 'Verification link sent to ' . $user['email'] . '. Check your inbox.');
        redirect('dashboard');
    }
}