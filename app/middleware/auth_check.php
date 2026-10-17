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
            SELECT s.*, u.id as user_id, u.full_name, u.email, u.role, u.avatar_path,
                   b.id as business_id, b.name as business_name,
                   b.slug, b.doc_prefix, b.primary_color, b.secondary_color,
                   b.logo_path, b.onboarding_complete, b.onboarding_step,
                   b.currency_symbol, b.font_pairing,
                   b.tagline, b.address, b.phone, b.email as business_email
            FROM sessions s
            JOIN users u ON u.id = s.user_id
            JOIN businesses b ON b.id = s.business_id
            WHERE s.session_token = :token AND s.expires_at > NOW()
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
    } else {
        flash('error', 'Please log in to continue.');
        redirect('login');
    }
}

// Redirect to onboarding if setup not complete
$business = auth_business();
if (!$business['onboarding_complete'] && !in_array($controller ?? '', ['onboarding', 'logout'])) {
    redirect('onboarding/step' . ($business['onboarding_step'] ?? 1));
}
