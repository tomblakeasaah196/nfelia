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
require_once __DIR__ . '/sales.php';

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

        // The monitor: errors, health, alerts
        ['GET',  'errors',                       'OpsErrorsController',  'index',            'errors'],
        ['POST', 'errors/bulk',                  'OpsErrorsController',  'bulk',             'errors'],
        ['POST', 'errors/export',                'OpsErrorsController',  'export',           'errors'],
        ['GET',  'errors/(\d+)',                 'OpsErrorsController',  'show',             'errors'],
        ['GET',  'errors/code/([A-Za-z0-9]{8})', 'OpsErrorsController',  'code',             'errors'],
        ['POST', 'errors/(\d+)/status',          'OpsErrorsController',  'status',           'errors'],
        ['POST', 'errors/(\d+)/assign',          'OpsErrorsController',  'assign',           'errors'],
        ['POST', 'errors/(\d+)/notes',           'OpsErrorsController',  'note',             'errors'],
        ['GET',  'health',                       'OpsHealthController',  'index',            'errors'],
        ['GET',  'health/sends',                 'OpsHealthController',  'sends',            'errors'],
        ['POST', 'health/sends/(\d+)/retry',     'OpsHealthController',  'retry',            'errors'],
        ['GET',  'alerts',                       'OpsAlertsController',  'index',            'signed_in'],
        ['GET',  'alerts/open/(\d+)',            'OpsAlertsController',  'open',             'signed_in'],
        ['POST', 'alerts/read-all',              'OpsAlertsController',  'read_all',         'signed_in'],
        ['POST', 'alerts/subscribe',             'OpsAlertsController',  'subscribe',        'signed_in'],
        ['POST', 'alerts/unsubscribe',           'OpsAlertsController',  'unsubscribe',      'signed_in'],
        ['POST', 'alerts/prefs',                 'OpsAlertsController',  'prefs',            'signed_in'],

        // Clients, and what the old founder admin page did
        ['GET',  'clients',                      'OpsClientsController', 'index',            'clients'],
        ['GET',  'clients/(\d+)',                'OpsClientsController', 'show',             'clients'],
        ['POST', 'clients/(\d+)/plan',           'OpsClientsController', 'plan',             'clients'],
        ['POST', 'clients/(\d+)/credit',         'OpsClientsController', 'credit',           'clients'],
        ['POST', 'clients/(\d+)/notes',          'OpsClientsController', 'note',             'clients'],
        ['POST', 'clients/(\d+)/view-as',        'OpsClientsController', 'view_as',          'clients'],
        ['GET',  'payments',                     'OpsPaymentsController', 'index',           'clients.act'],
        ['GET',  'payments/(\d+)/receipt',       'OpsPaymentsController', 'receipt',         'clients.act'],
        ['POST', 'payments/(\d+)/approve',       'OpsPaymentsController', 'approve',         'clients.act'],
        ['POST', 'payments/(\d+)/reject',        'OpsPaymentsController', 'reject',          'clients.act'],
        ['GET',  'shop-names',                   'OpsShopNamesController', 'index',          'clients.act'],
        ['POST', 'shop-names/(\d+)',             'OpsShopNamesController', 'decide',         'clients.act'],
        ['GET',  'support',                      'OpsSupportController', 'index',            'support'],
        ['GET',  'support/(\d+)',                'OpsSupportController', 'show',             'support'],
        ['POST', 'support/(\d+)/reply',          'OpsSupportController', 'reply',            'support'],
        ['GET',  'feedback',                     'OpsSupportController', 'feedback',         'support'],
        ['GET',  'reviews',                      'OpsSupportController', 'reviews',          'support'],
        ['POST', 'reviews/(\d+)',                'OpsSupportController', 'review',           'support'],
        ['GET',  'finance',                      'OpsFinanceController', 'index',            'finance'],
        ['POST', 'finance/expenses',             'OpsFinanceController', 'add_expense',      'finance'],
        ['POST', 'finance/expenses/(\d+)/delete', 'OpsFinanceController', 'delete_expense', 'finance'],
        ['GET',  'referrals',                    'OpsFinanceController', 'referrals',        'finance'],
        ['GET',  'countries',                    'OpsCountriesController', 'index',          'settings'],
        ['POST', 'countries',                    'OpsCountriesController', 'add',            'settings'],
        ['POST', 'countries/(\d+)',              'OpsCountriesController', 'edit',           'settings'],
        ['POST', 'countries/(\d+)/toggle',       'OpsCountriesController', 'toggle',         'settings'],

        // Sales: leads, the pipeline, follow-ups, campaigns
        ['GET',  'leads',                        'OpsLeadsController',   'index',            'sales'],
        ['POST', 'leads',                        'OpsLeadsController',   'add',              'sales'],
        ['GET',  'leads/(\d+)',                  'OpsLeadsController',   'show',             'sales'],
        ['POST', 'leads/(\d+)',                  'OpsLeadsController',   'update',           'sales'],
        ['POST', 'leads/(\d+)/stage',            'OpsLeadsController',   'stage',            'sales'],
        ['POST', 'leads/(\d+)/owner',            'OpsLeadsController',   'owner',            'sales'],
        ['POST', 'leads/(\d+)/follow-up',        'OpsLeadsController',   'follow_up',        'sales'],
        ['POST', 'leads/(\d+)/notes',            'OpsLeadsController',   'note',             'sales'],
        ['GET',  'campaigns',                    'OpsCampaignsController', 'index',          'campaigns'],
        ['GET',  'campaigns/new',                'OpsCampaignsController', 'create_form',    'campaigns'],
        ['POST', 'campaigns',                    'OpsCampaignsController', 'create',         'campaigns'],
        ['GET',  'campaigns/(\d+)',              'OpsCampaignsController', 'show',           'campaigns'],
        ['POST', 'campaigns/(\d+)',              'OpsCampaignsController', 'update',         'campaigns'],
        ['POST', 'campaigns/(\d+)/test',         'OpsCampaignsController', 'test',           'campaigns'],
        ['POST', 'campaigns/(\d+)/send',         'OpsCampaignsController', 'send',           'campaigns'],

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
    errors_context_set(['source' => 'ops']);
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
