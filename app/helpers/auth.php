<?php
/**
 * Nfelia — Auth Helpers
 */
function auth_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function auth_business(): ?array {
    return $_SESSION['business'] ?? null;
}

function auth_business_id(): ?int {
    $id = $_SESSION['business']['id'] ?? null;
    return $id !== null ? (int)$id : null;
}

function is_logged_in(): bool {
    return isset($_SESSION['user']['id']);
}

function is_owner(): bool {
    return ($_SESSION['user']['role'] ?? '') === 'owner';
}

function login_user(array $user, array $business): void {
    $_SESSION['user']     = $user;
    $_SESSION['business'] = $business;
    session_regenerate_id(true);
}

function update_business_session(array $fields): void {
    if (!isset($_SESSION['business'])) return;
    foreach ($fields as $key => $value) {
        $_SESSION['business'][$key] = $value;
    }
}

function logout_user(): void {
    $_SESSION = [];
    session_destroy();
    if (isset($_COOKIE['nfelia_remember'])) {
        setcookie('nfelia_remember', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
}

function csrf_verify(): void {
    if (
        empty($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])
    ) {
        flash('error', 'Security session expired. Please try again.');
        redirect('login');
    }
}

function csrf_regenerate(): void {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}