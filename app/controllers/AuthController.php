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

        $db   = get_db();
        $user = $this->find_account('email', $email);

        if ($user && password_verify($pass, $user['password_hash'])) {
            $this->begin_session($user);

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
     * An active user joined with everything login_user() needs from their
     * business, looked up by 'email' or 'id'. Null when there is none.
     */
    private function find_account(string $column, string|int $value): ?array {
        $column = $column === 'id' ? 'u.id' : 'u.email';
        // PATCH: Select ALL business data explicitly
        $stmt = get_db()->prepare("
            SELECT u.*,
                   b.name as business_name, b.slug, b.doc_prefix,
                   b.primary_color, b.secondary_color, b.logo_path,
                   b.onboarding_complete, b.onboarding_step,
                   b.currency_symbol, b.font_pairing,
                   b.tagline, b.address, b.phone as business_phone, b.email as business_email,
                   b.business_type, b.email_verified_at
            FROM users u
            JOIN businesses b ON b.id = u.business_id
            WHERE {$column} = ? AND u.is_active = 1
        ");
        $stmt->execute([$value]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Sign in a row from find_account(). Shared by password login and the
     * emailed sign-in link so both put the same data in the session.
     */
    private function begin_session(array $user): void {
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
        get_db()->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);
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
     * Password recovery: ask for an email address and send a reset link.
     *
     * The answer is the same whether or not the address has an account, so
     * the form cannot be used to find out who is registered. Someone unsure
     * which address they signed up with can try each one; only an address
     * with an account receives anything.
     */
    public function forgot_password() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $this->send_account_link('reset');
        }

        require_once BASE_PATH . '/views/auth/forgot-password.php';
    }

    /**
     * "Email me a sign-in link": the same request flow as recovery, but the
     * link signs the person straight in instead of asking for a new password.
     */
    public function login_link() {
        if (is_logged_in()) {
            redirect('dashboard');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $this->send_account_link('signin');
        }

        require_once BASE_PATH . '/views/auth/login-link.php';
    }

    /**
     * Handle a reset or sign-in link request. Always ends on the same
     * "check your email" screen, and the mail goes out after the response,
     * so neither the page nor its timing reveals whether the address exists.
     */
    private function send_account_link(string $purpose): never {
        $back  = $purpose === 'reset' ? 'forgot-password' : 'login-link';
        $email = strtolower(trim($_POST['email'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Enter a valid email address.');
            redirect($back);
        }

        // Both limits count every request, account or not, so hitting one
        // says nothing about who is registered. The per-address limit stops
        // anyone flooding a person's inbox; reset and sign-in links share it.
        if (!throttle_allows('link_ip', $_SERVER['REMOTE_ADDR'] ?? '', 30, 60)) {
            flash('error', 'Too many requests from this network. Please try again in an hour.');
            redirect($back);
        }
        if (!throttle_allows('link_email', $email, 5, 60)) {
            flash('error', 'Several links were already sent to this address in the last hour. Use the most recent one, or try again later.');
            redirect($back);
        }

        $user = $this->find_account('email', $email);
        if ($user) {
            if ($purpose === 'reset') {
                $url = APP_URL . '/reset-password/' . account_token_issue((int)$user['id'], 'reset', 60);
                $subject  = 'Password Reset — Nfelia Workspace';
                $template = 'password_reset';
                $vars     = ['reset_url' => $url, 'expires_label' => '1 hour'];
            } else {
                $url = APP_URL . '/signin/' . account_token_issue((int)$user['id'], 'signin', 15);
                $subject  = 'Your sign-in link — Nfelia Workspace';
                $template = 'signin_link';
                $vars     = ['signin_url' => $url];
            }

            $to_email = $user['email'];
            $to_name  = $user['full_name'];
            after_response(fn() => send_branded_mail(
                $to_email, $to_name, $subject, $template, $vars,
                ['name' => 'Nfelia', 'primary_color' => '#8B004A']
            ));
        }

        $_SESSION['link_sent'] = ['email' => $email, 'purpose' => $purpose];
        session_write_close();
        redirect('recovery-sent');
    }

    /**
     * Display the "check your email" screen
     */
    public function recovery_sent() {
        if (empty($_SESSION['link_sent'])) {
            redirect('login');
        }
        require_once BASE_PATH . '/views/auth/recovery-sent.php';
    }

    /**
     * Handle Password Reset Validation & Update
     */
    public function reset_password($token = null) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $token    = $_POST['token'] ?? '';
            $password = $_POST['password'] ?? '';
            $confirm  = $_POST['password_confirm'] ?? '';

            $account = account_token_find($token, 'reset');
            if (!$account) {
                flash('error', 'Recovery token invalid or expired. Please initiate a new request.');
                redirect('forgot-password');
            }

            if (strlen($password) < 8 || $password !== $confirm) {
                flash('error', 'Passkeys do not match or fail security protocols.');
                redirect("reset-password/$token");
            }

            if (!account_token_consume((int)$account['token_id'])) {
                flash('error', 'Recovery token invalid or expired. Please initiate a new request.');
                redirect('forgot-password');
            }

            $db = get_db();
            $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([
                password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
                $account['id'],
            ]);

            // Close every other way in: links still sitting in the inbox, and
            // "keep me signed in" sessions on other devices. If someone else
            // had the account, the reset now locks them out.
            $db->prepare("UPDATE account_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL")
               ->execute([$account['id']]);
            $db->prepare("DELETE FROM sessions WHERE user_id = ?")->execute([$account['id']]);

            // Tell the owner of the address, so a reset they did not make
            // does not go unnoticed.
            $to_email = $account['email'];
            $to_name  = $account['full_name'];
            after_response(fn() => send_branded_mail(
                $to_email, $to_name,
                'Your Nfelia password was changed',
                'password_changed',
                ['recover_url' => APP_URL . '/forgot-password'],
                ['name' => 'Nfelia', 'primary_color' => '#8B004A']
            ));

            flash('success', 'Security clearance updated. You may now access the workspace.');
            session_write_close();
            redirect('login');
        }

        // Check the link up front, so an expired one fails before the person
        // has typed a new password twice.
        if (!account_token_find($token, 'reset')) {
            flash('error', 'Recovery token invalid or expired. Please initiate a new request.');
            redirect('forgot-password');
        }

        require_once BASE_PATH . '/views/auth/reset-password.php';
    }

    /**
     * Emailed sign-in link. Opening it only shows a "Sign in" button; the
     * sign-in itself takes a POST. Mail scanners open links in emails
     * automatically, and would otherwise use up the one-time token before
     * the person ever clicked it.
     */
    public function signin($token = null) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $account = account_token_find($_POST['token'] ?? '', 'signin');
            $user    = $account ? $this->find_account('id', (int)$account['id']) : null;

            if (!$user || !account_token_consume((int)$account['token_id'])) {
                flash('error', 'That sign-in link is invalid, expired or already used. Request a new one below.');
                redirect('login-link');
            }

            $this->begin_session($user);
            redirect('dashboard');
        }

        $account = account_token_find($token, 'signin');
        if (!$account) {
            flash('error', 'That sign-in link is invalid, expired or already used. Request a new one below.');
            redirect('login-link');
        }

        require_once BASE_PATH . '/views/auth/signin.php';
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