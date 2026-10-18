<?php
/**
 * Nfelia — Auth Controller
 * Handles Registration, Login, and Logout
 */

class AuthController {

    /** How long an email-verification link works. */
    private const VERIFY_LINK_HOURS = 48;

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
            redirect(signin_destination($_GET['next'] ?? null));
        }
        // A link that asks for sign-in first (?next=) is kept in the session
        // too, so the passkey and emailed-link ways in also come back to it.
        signin_next_keep($_GET['next'] ?? null);
        $signin_next = signin_next_valid($_SESSION['signin_next'] ?? '');
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

        $user = account_find('email', $email);

        if ($user && password_verify($pass, $user['password_hash'])) {
            signin_start($user);
            if (!empty($_POST['remember_me'])) {
                signin_remember($user);
            }

            redirect(signin_destination($_POST['next'] ?? null));
        } else {
            flash('error', 'Invalid email or password combination.');
            redirect(signin_url($_POST['next'] ?? ''));
        }
    }

    /**
     * Handle Pilot Registration (The 3-Step Create)
     */
    public function register() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            if (is_logged_in()) {
                redirect('dashboard');
            }
            require_once BASE_PATH . '/views/auth/register.php';
            return;
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
            $this->back_to_register();
        }

        if ($password !== $password_confirm) {
            flash('error', 'Passwords do not match. Please re-enter.');
            $this->back_to_register();
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Please provide a valid email address.');
            $this->back_to_register();
        }

        $db = get_db();

        // 1. Check if email already exists. A removed team member's old
        // login doesn't count: their address is freed for their own business.
        // Gmail ignores dots and anything after a +, so those count as one
        // address for a business owner (one free trial each).
        $check = $db->prepare("SELECT id, is_active, role FROM users WHERE email = ?");
        $check->execute([$email]);
        $taken = $check->fetch();
        if (($taken && ((int)$taken['is_active'] || $taken['role'] === 'owner')) || email_owner_exists($db, $email)) {
            flash('error', 'This email already has an account. Sign in instead, or use another email.');
            $this->back_to_register();
        }

        // A few new businesses a day from one connection is plenty; more
        // looks like someone collecting free trials.
        if (!throttle_allows('signup_ip', client_ip(), 5, 60 * 24)) {
            flash('error', 'Too many new accounts from this connection today. Please try again tomorrow, or WhatsApp us.');
            $this->back_to_register();
        }

        try {
            $db->beginTransaction();
            if ($taken) {
                free_removed_email($db, (int)$taken['id']);
            }

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

            // 4. Initialize the Subscription (free trial; the country chosen
            // during onboarding may set its own length)
            $trial_expiry = date('Y-m-d', strtotime('+' . TRIAL_DAYS . ' days'));
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

            // 6. The business's own referral code, kept for good
            require_once BASE_PATH . '/app/controllers/ReferralController.php';
            $db->prepare("UPDATE businesses SET referral_code = ? WHERE id = ?")
               ->execute([ReferralController::new_code($db), $business_id]);

            $db->commit();

            // PATCH: Process Referral Code if present
            $ref_code = trim($_POST['ref_code'] ?? '');
            if (!empty($ref_code)) {
                require_once BASE_PATH . '/app/controllers/ReferralController.php'; // NFELIA AUDIT FIX H03 — 2026-06-03
                // Pass the new business ID and their email to enforce identity matching
                ReferralController::convert_referral($db, $ref_code, $business_id, $email);
            }

            // Auto-login after registration, with the same session a sign-in
            // makes, so no page meets a missing field (D11)
            $account = account_find('id', (int)$user_id);
            if ($account) {
                signin_session($account);
            } else login_user(
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
                send_branded_mail(
                    $email, $full_name,
                    'Welcome to Nfelia — Verify Your Email',
                    'welcome',
                    [
                        'owner_name'     => $full_name,
                        'workspace_name' => $biz_name,
                        'trial_days'     => TRIAL_DAYS,
                        'verify_url'     => $this->verification_url((int)$user_id, (int)$business_id),
                        'expires_hours'  => self::VERIFY_LINK_HOURS,
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
            flash('error', 'Your account could not be created. Please try again, or message us on WhatsApp.');
            $this->back_to_register();
        }
    }

    /**
     * Return to the sign-up page with what the person typed (never the
     * passwords), so a mistake does not mean filling the form again.
     */
    private function back_to_register(): never {
        $_SESSION['register_old'] = [
            'full_name'     => trim($_POST['full_name'] ?? ''),
            'business_name' => trim($_POST['business_name'] ?? ''),
            'email'         => trim($_POST['email'] ?? ''),
            'ref_code'      => trim($_POST['ref_code'] ?? ''),
        ];
        redirect('register');
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
            redirect(signin_destination($_GET['next'] ?? null));
        }
        signin_next_keep($_GET['next'] ?? null);

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
        $back  = $purpose === 'reset' ? 'forgot-password' : signin_url($_POST['next'] ?? '', 'login-link');
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

        $user = account_find('email', $email);
        if ($user) {
            if ($purpose === 'reset') {
                $url = APP_URL . '/reset-password/' . account_token_issue((int)$user['id'], 'reset', 60);
                $subject  = 'Password Reset — Nfelia Workspace';
                $template = 'password_reset';
                $vars     = ['reset_url' => $url, 'expires_label' => '1 hour'];
            } else {
                // The link comes back to where they were going, even when it
                // is opened in another browser (the one the email app uses).
                $next = signin_next_valid($_POST['next'] ?? ($_SESSION['signin_next'] ?? ''));
                $url = APP_URL . '/signin/' . account_token_issue((int)$user['id'], 'signin', 15)
                     . ($next !== '' ? '?next=' . rawurlencode($next) : '');
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
            $user    = $account ? account_find('id', (int)$account['id']) : null;

            if (!$user || !account_token_consume((int)$account['token_id'])) {
                flash('error', 'That sign-in link is invalid, expired or already used. Request a new one below.');
                redirect(signin_url($_POST['next'] ?? '', 'login-link'));
            }

            signin_start($user);
            redirect(signin_destination($_POST['next'] ?? null));
        }

        $account = account_token_find($token, 'signin');
        if (!$account) {
            flash('error', 'That sign-in link is invalid, expired or already used. Request a new one below.');
            redirect(signin_url($_GET['next'] ?? '', 'login-link'));
        }

        require_once BASE_PATH . '/views/auth/signin.php';
    }
    
    /**
     * Handle General Feedback from Landing Page (Prospects)
     * Records intent in general_feedback and alerts the Director.
     */
    public function public_feedback() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('');
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
            flash('error', 'Please add your business name and a message.');
            redirect('');
        }

        // Anyone can post this form: a handful an hour from one network.
        if (!throttle_allows('prospect', (string)$ip_address, 5, 60)) {
            flash('error', 'Several messages came from your network just now. Please try again in an hour, or message us on WhatsApp.');
            redirect('');
        }

        $db = get_db();

        try {
            $stmt = $db->prepare("
                INSERT INTO general_feedback (enterprise_name, contact_info, message, ip_address) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$enterprise, $contact, $message, $ip_address]);
            $feedback_id = (int)$db->lastInsertId();

            // The Nfelia team hears about it, with the contact to reply on.
            // A lead for the Nfelia team's Sales, who are told (app/ops/sales.php).
            ops_lead_from_website((int)$feedback_id, $enterprise, $contact, $message);

            flash('success', 'Thank you. We got your message and will reply soon.');
            redirect('');
            
        } catch (Exception $e) {
            error_log("General Feedback Error: " . $e->getMessage());
            flash('error', 'Your message did not send. Please try again, or message us on WhatsApp.');
            redirect('');
        }
    }

    /**
     * Handle Logout
     */
    public function logout() {
        // Only the sign-out button (a POST with the CSRF token) signs out, so
        // another site can't sign someone out with a link or an image.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('dashboard');
        }
        csrf_verify();

        // This phone stops getting this person's notifications. The browser
        // keeps its subscription; whoever signs in next takes it over.
        if (isset($_COOKIE[PUSH_COOKIE]) && is_logged_in()) {
            get_db()->prepare("DELETE FROM push_subscriptions WHERE endpoint_hash = ? AND user_id = ?")
                    ->execute([(string)$_COOKIE[PUSH_COOKIE], (int)auth_user()['id']]);
        }

        // This phone stops getting this person's alerts (assets/js/bell.js
        // sends its subscription with the sign-out).
        if (!empty($_POST['push_endpoint']) && is_logged_in()) {
            get_db()->prepare("DELETE FROM push_subscriptions WHERE endpoint_hash = ? AND user_id = ?")
                    ->execute([hash('sha256', (string)$_POST['push_endpoint']), (int)auth_user()['id']]);
        }

        // Delete the sessions table row if remember-me was active
        if (isset($_COOKIE[REMEMBER_COOKIE])) {
            $db = get_db();
            $db->prepare("DELETE FROM sessions WHERE session_token = ?")
               ->execute([$_COOKIE[REMEMBER_COOKIE]]);
        }

        logout_user();
        session_start();   // a fresh session, so this message reaches the sign-in page
        flash('success', 'You’re signed out.');
        redirect('login');
    }

    /**
     * Handle email verification link click
     *
     * A link keeps working until it expires, and opening it again changes
     * nothing: mail scanners open links before people do, and people open
     * them twice. The session is left alone; is_email_verified() reads the
     * database on the next page, in whichever browser is signed in.
     */
    public function verify_email($token = null) {
        if (!$token) { redirect('login'); return; }

        $db = get_db();
        $stmt = $db->prepare("SELECT business_id FROM email_verifications WHERE token = ? AND expires_at > NOW()");
        $stmt->execute([$token]);
        $business_id = $stmt->fetchColumn();

        if (!$business_id) {
            if (is_logged_in() && is_email_verified()) {
                flash('success', 'Your email is already verified.');
                redirect('dashboard');
                return;
            }
            flash('error', 'This verification link is no longer valid. If your email is already verified, there is nothing more to do; if not, sign in and choose Resend Link.');
            redirect('login');
            return;
        }

        // COALESCE keeps the time of the first verification
        $db->prepare("UPDATE businesses SET email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?")
           ->execute([$business_id]);

        flash('success', 'Email verified. Your workspace is fully unlocked.');
        redirect(is_logged_in() ? 'dashboard' : 'login');
    }

    /**
     * Resend verification email (POST, authenticated, owner only)
     */
    public function resend_verification() {
        if (!is_logged_in()) { redirect('login'); return; }
        csrf_verify();

        // Verification stands for the owner's address. A staff member's own
        // link must not mark the business verified.
        if (!is_owner()) {
            redirect('dashboard');
            return;
        }

        // Ask the database, not the session: the link may have been opened
        // on another device.
        if (is_email_verified()) {
            flash('success', 'Your email is already verified.');
            redirect('dashboard');
            return;
        }

        $user = auth_user();
        $sent = send_branded_mail(
            $user['email'], $user['full_name'],
            'Verify Your Email — Nfelia',
            'verification',
            [
                'owner_name'    => $user['full_name'],
                'verify_url'    => $this->verification_url((int)$user['id'], auth_business_id()),
                'expires_hours' => self::VERIFY_LINK_HOURS,
            ],
            ['name' => 'Nfelia', 'primary_color' => '#8B004A']
        );

        if ($sent) {
            flash('success', 'Verification link sent to ' . $user['email'] . '. Check your inbox.');
        } else {
            flash('error', 'The verification email could not be sent. Please try again in a few minutes.');
        }
        redirect('dashboard');
    }

    /**
     * A new email-verification link for a business. Links already sent keep
     * working until they expire, so a resend never breaks the email someone
     * is about to open.
     */
    private function verification_url(int $user_id, int $business_id): string {
        $db = get_db();
        $db->exec("DELETE FROM email_verifications WHERE expires_at < NOW()");

        $token = bin2hex(random_bytes(32));
        $db->prepare("
            INSERT INTO email_verifications (user_id, business_id, token, expires_at)
            VALUES (?, ?, ?, NOW() + INTERVAL " . self::VERIFY_LINK_HOURS . " HOUR)
        ")->execute([$user_id, $business_id, $token]);

        return APP_URL . '/verify-email/' . $token;
    }
}