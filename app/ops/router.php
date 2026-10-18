<?php
/**
 * Every request to ops.nfelia.com comes here from index.php.
 *
 * Each route names its method, its pattern and who may use it:
 *   null        anyone (sign-in, invites, password resets)
 *   'signed_in' anyone signed in to Ops
 *   anything else, a permission (OPS_PERMISSIONS in app/ops/ops.php)
 * Every POST must carry the session's CSRF token. Nothing is matched by
 * guessing at method names: a URL reaches only what is listed here.
 */

require_once __DIR__ . '/ops.php';
require_once __DIR__ . '/passkeys.php';

function ops_routes(): array
{
    $t = '([A-Za-z0-9_-]{20,100})';
    return [
        // Signing in and out
        ['GET',  'signin',                       'OpsAuthController',    'signin_form',      null],
        ['POST', 'signin',                       'OpsAuthController',    'signin',           null],
        ['POST', 'signin/passkey/options',       'OpsAuthController',    'passkey_options',  null],
        ['POST', 'signin/passkey',               'OpsAuthController',    'passkey_signin',   null],
        ['POST', 'signout',                      'OpsAuthController',    'signout',          'signed_in'],
        ['GET',  "invite/{$t}",                  'OpsAuthController',    'invite_form',      null],
        ['POST', "invite/{$t}",                  'OpsAuthController',    'invite_accept',    null],
        ['GET',  'forgot',                       'OpsAuthController',    'forgot_form',      null],
        ['POST', 'forgot',                       'OpsAuthController',    'forgot',           null],
        ['GET',  "reset/{$t}",                   'OpsAuthController',    'reset_form',       null],
        ['POST', "reset/{$t}",                   'OpsAuthController',    'reset',            null],

        // Home, and your own account
        ['GET',  '',                             'OpsHomeController',    'index',            'signed_in'],
        ['GET',  'account',                      'OpsAccountController', 'index',            'signed_in'],
        ['POST', 'account/password',             'OpsAccountController', 'password',         'signed_in'],
        ['POST', 'account/signout-everywhere',   'OpsAccountController', 'signout_everywhere', 'signed_in'],
        ['POST', 'account/passkeys/options',     'OpsAccountController', 'passkey_options',  'signed_in'],
        ['POST', 'account/passkeys',             'OpsAccountController', 'passkey_add',      'signed_in'],
        ['POST', 'account/passkeys/(\d+)/remove', 'OpsAccountController', 'passkey_remove',  'signed_in'],

        // The team, and everything done in Ops
        ['GET',  'team',                         'OpsTeamController',    'index',            'team'],
        ['POST', 'team/invite',                  'OpsTeamController',    'invite',           'team'],
        ['POST', 'team/(\d+)/role',              'OpsTeamController',    'role',             'team'],
        ['POST', 'team/(\d+)/deactivate',        'OpsTeamController',    'deactivate',       'team'],
        ['POST', 'team/(\d+)/reactivate',        'OpsTeamController',    'reactivate',       'team'],
        ['POST', 'team/(\d+)/resend',            'OpsTeamController',    'resend',           'team'],
        ['GET',  'audit',                        'OpsAuditController',   'index',            'audit'],
    ];
}

function ops_dispatch(): void
{
    ops_headers();
    $path = trim((string)($_GET['url'] ?? ''), '/');

    // Never indexed, and no session for robots.
    if ($path === 'robots.txt') {
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\nDisallow: /\n";
        return;
    }

    ops_session_start();
    $method = $_SERVER['REQUEST_METHOD'] === 'POST' ? 'POST' : 'GET';

    $route = null;
    $args  = [];
    $path_known = false;
    foreach (ops_routes() as [$m, $pattern, $class, $action, $who]) {
        if (!preg_match('#^' . $pattern . '$#', $path, $match)) {
            continue;
        }
        $path_known = true;
        if ($m === $method) {
            $route = [$class, $action, $who];
            $args  = array_slice($match, 1);
            break;
        }
    }
    if (!$route) {
        if (!$path_known) {
            ops_not_found();
        }
        http_response_code(405);
        echo 'Method not allowed.';
        return;
    }
    [$class, $action, $who] = $route;
    $is_script = str_contains((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')
        || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

    if ($method === 'POST' && !ops_csrf_ok()) {
        if ($is_script) {
            ops_json(['ok' => false, 'message' => 'This page has expired. Reload it and try again.'], 419);
        }
        ops_flash('error', 'That form had expired. Try again.');
        ops_redirect(ops_back($who === null ? 'signin' : ''));
    }

    if ($who !== null) {
        $staff = ops_staff();
        if (!$staff) {
            if ($is_script) {
                ops_json(['ok' => false, 'signed_out' => true, 'message' => 'You are signed out of Nfelia Ops.'], 401);
            }
            if ($method === 'GET') {
                $_SESSION['ops_next'] = ops_here();
            }
            ops_redirect('signin');
        }
        if ($who !== 'signed_in' && !ops_can($who, $staff)) {
            if ($is_script) {
                ops_json(['ok' => false, 'message' => 'Your role does not include this.'], 403);
            }
            ops_forbidden();
        }
    }

    require_once __DIR__ . '/controllers/' . $class . '.php';
    (new $class())->$action(...$args);
}
