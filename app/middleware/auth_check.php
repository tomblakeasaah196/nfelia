<?php
/**
 * Nfelia — Auth Middleware
 * Runs before every authenticated route.
 * Redirects to login if no valid session exists.
 *
 * NFELIA AUDIT FIX — C03 — Fixed remember-me session restore missing fields — 2026-06-03
 */

if (!is_logged_in()) {
    if (isset($_COOKIE['nfelia_remember'])) {
        $db    = get_db();
        $token = $_COOKIE['nfelia_remember'];
        $stmt  = $db->prepare("
            SELECT s.*, s.id as remember_row_id, TIMESTAMPDIFF(SECOND, s.last_active_at, NOW()) as idle_seconds,
                   u.id as user_id, u.full_name, u.email, u.role, u.avatar_path,
                   b.id as business_id, b.name as business_name,
                   b.slug, b.doc_prefix, b.primary_color, b.secondary_color,
                   b.logo_path, b.onboarding_complete, b.onboarding_step,
                   b.currency_symbol, b.font_pairing,
                   b.tagline, b.address, b.phone, b.email as business_email,
                   b.business_type, b.email_verified_at
            FROM sessions s
            JOIN users u ON u.id = s.user_id
            JOIN businesses b ON b.id = s.business_id
            WHERE s.session_token = :token AND s.expires_at > NOW()
              AND u.is_active = 1  -- a removed team member's cookie must stop working
        ");
        $stmt->execute([':token' => $token]);
        $session = $stmt->fetch();

        if ($session) {
            login_user(
                [
                    'id'          => $session['user_id'],
                    'full_name'   => $session['full_name'],
                    'email'       => $session['email'],
                    'role'        => $session['role'],
                    'avatar_path' => $session['avatar_path'],
                ],
                [
                    'id'                  => $session['business_id'],
                    'name'                => $session['business_name'],
                    'slug'                => $session['slug'],
                    'doc_prefix'          => $session['doc_prefix'],
                    'primary_color'       => $session['primary_color'],
                    'secondary_color'     => $session['secondary_color'],
                    'logo_path'           => $session['logo_path'],
                    'onboarding_complete' => $session['onboarding_complete'],
                    'onboarding_step'     => $session['onboarding_step'],
                    'currency_symbol'     => $session['currency_symbol'],
                    'font_pairing'        => $session['font_pairing'],
                    'tagline'             => $session['tagline'],
                    'address'             => $session['address'],
                    'phone'               => $session['phone'],
                    'email'               => $session['business_email'],
                    'business_type'       => $session['business_type'] ?? 'product',
                    'email_verified_at'   => $session['email_verified_at'] ?? null,
                ]
            );
            // Back after a while: open on the lock, not straight into the business.
            $_SESSION['remember_id'] = (int)$session['remember_row_id'];
            $idle = $session['idle_seconds'] === null ? PHP_INT_MAX : (int)$session['idle_seconds'];
            $_SESSION['last_active'] = time() - min($idle, LOCK_AFTER_SECONDS + 1);
            if ($idle > LOCK_AFTER_SECONDS) {
                $_SESSION['locked'] = true;
            }
        } else {
            // Expired or invalid cookie — clear it
            setcookie('nfelia_remember', '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'secure'   => true,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            flash('error', 'Session expired. Please log in again.');
            redirect('login');
        }
    } elseif (($_GET['source'] ?? '') === 'pwa') {
        // The installed app, opened while signed out: straight to sign-in,
        // which plays the opening. No scolding message on a fresh start.
        redirect('login?source=pwa');
    } else {
        flash('error', 'Please log in to continue.');
        redirect('login');
    }
}

// Every form on the page posts the session's CSRF token. A session restored
// from the remember-me cookie above is brand new and has none, which made
// the first form sent after a browser restart fail as "session expired".
if (empty($_SESSION['csrf_token'])) {
    csrf_regenerate();
}

// The lock (app/helpers/signin.php): after LOCK_AFTER_SECONDS unused, pages
// show the lock over an empty outline of the app, and requests from scripts
// get 401 "locked", until the person unlocks. Signing out always works.
if (($controller ?? '') !== 'logout' && lock_engaged()) {
    if (is_ajax() || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        header('X-Nfelia-Locked: 1');
        json_response(['success' => false, 'locked' => true, 'message' => 'Nfelia is locked. Unlock it to carry on.'], 401);
    }
    http_response_code(401);
    $locked_page = true;
    $title       = 'Locked';
    $view_file   = BASE_PATH . '/views/lock/outline.php';
    require BASE_PATH . '/views/layouts/app.php';
    exit;
}
lock_touch();

// Redirect to onboarding if setup not complete
$business = auth_business();
if (!$business['onboarding_complete'] && !in_array($controller ?? '', ['onboarding', 'logout'])) {
    redirect('onboarding/step' . ($business['onboarding_step'] ?? 1));
}
