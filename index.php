<?php
/**
 * NFELIA — Front Controller
 * Every HTTP request enters here. Router dispatches to controllers.
 */
 
declare(strict_types=1);

// Load Composer Autoloader
require_once __DIR__ . '/vendor/autoload.php';
 
// Load core config
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/mail.php';

// Errors, slow pages and crashes are written down for the Nfelia team
// (Ops → Errors) from here on (app/helpers/errors.php).
require_once __DIR__ . '/app/helpers/errors.php';
errors_install('web');
 
// Load helpers
require_once __DIR__ . '/app/helpers/functions.php';
require_once __DIR__ . '/app/helpers/auth.php';
require_once __DIR__ . '/app/helpers/billing.php';
require_once __DIR__ . '/app/helpers/stock.php';
require_once __DIR__ . '/app/helpers/money.php';
require_once __DIR__ . '/app/helpers/invoices.php';
require_once __DIR__ . '/app/helpers/orders.php';
require_once __DIR__ . '/app/helpers/token.php';
require_once __DIR__ . '/app/helpers/mail.php';
require_once __DIR__ . '/app/helpers/webpush.php';
require_once __DIR__ . '/app/helpers/planner.php';
require_once __DIR__ . '/app/helpers/shop_links.php';
require_once __DIR__ . '/app/helpers/storefront.php';
require_once __DIR__ . '/app/helpers/cloudflare.php';
require_once __DIR__ . '/app/helpers/shop_orders.php';
require_once __DIR__ . '/app/helpers/bookings.php';
require_once __DIR__ . '/app/helpers/seo.php';
require_once __DIR__ . '/app/helpers/theme.php';
require_once __DIR__ . '/app/helpers/nav.php';
require_once __DIR__ . '/app/helpers/signin.php';
require_once __DIR__ . '/app/helpers/passkeys.php';
require_once __DIR__ . '/app/helpers/notify.php';
require_once __DIR__ . '/app/helpers/screens.php';
require_once __DIR__ . '/app/helpers/ui.php';
require_once __DIR__ . '/app/helpers/view_as.php';

// --- GLOBAL EXCEPTION & FATAL ERROR HANDLER ---
// Start output buffering so we can erase half-loaded pages if a crash happens
ob_start();

set_exception_handler(function ($e) {
    // Written down for the team, with a code the person can quote to support.
    $GLOBALS['nf_error_ref'] = errors_report_throwable($e, ['level' => 'error']);

    // Erase anything that was already printed to the screen
    while (ob_get_level()) { ob_end_clean(); }

    http_response_code(500);
    header('Cache-Control: no-store', true);   // an error is never cached (W11)
    if ($GLOBALS['nf_error_ref']) header('X-Nfelia-Error: ' . $GLOBALS['nf_error_ref']);
    if (is_ajax() || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'ok' => false, 'error_code' => $GLOBALS['nf_error_ref'],
                          'message' => 'Something went wrong on our side. Try again' . ($GLOBALS['nf_error_ref'] ? '; if it keeps happening, tell support the code ' . $GLOBALS['nf_error_ref'] : '') . '.']);
        exit;
    }
    require __DIR__ . '/views/500.php';
    exit;
});

register_shutdown_function(function () {
    // Catch fatal memory errors, parse errors, and strict type errors.
    // errors_on_shutdown() has already written it down (its shutdown runs
    // first) and kept it, since writing it down can replace error_get_last().
    $error = $GLOBALS['nf_fatal'] ?? error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR])) {
        while (ob_get_level()) { ob_end_clean(); }
        http_response_code(500);
        require __DIR__ . '/views/500.php';
        // No exit: what runs after the response (the team's alert about
        // this crash) is a later shutdown function, and exit would skip it.
    }
});

// The Ops tests break things on purpose to see them recorded
// (tests/ops/boom.php). Only on a test copy: tests/ is never deployed.
if (in_array(APP_ENV, ['local', 'testing'], true) && str_starts_with(trim($_GET['url'] ?? '', '/'), '__boom/') && is_file(__DIR__ . '/tests/ops/boom.php')) {
    require __DIR__ . '/tests/ops/boom.php';
}

// --- NFELIA OPS ---
// ops.nfelia.com is the Nfelia team's own place (app/ops/). It is looked for
// before shops, and "ops" is a reserved shop name, so no shop can take it.
require_once __DIR__ . '/app/ops/ops.php';
require_once __DIR__ . '/app/ops/alerts.php';   // the Ops team hears from the app (payments, tickets, shop names, leads)
require_once __DIR__ . '/app/ops/sales.php';
if (ops_is_host($_SERVER['HTTP_HOST'] ?? '')) {
    require_once __DIR__ . '/app/ops/router.php';
    ops_dispatch();
    exit;
}

// --- PHOTO COPIES ---
// Shops ask for WebP copies of their photos at a few widths. The first
// request for each copy lands here (the file isn't there yet) and makes it;
// from then on the web server sends the file itself.
if (str_starts_with(trim($_GET['url'] ?? '', '/'), 'uploads/cache/')) {
    storefront_serve_variant(trim($_GET['url'], '/'));
}

// --- SHOP LINKS ---
// name.nfelia.com is a shop. It is served before any session starts, so
// shoppers never get a cookie and their pages stay cacheable.
$shop_label = shop_label_from_host($_SERVER['HTTP_HOST'] ?? '');
if ($shop_label !== null) {
    require_once __DIR__ . '/app/controllers/PublicController.php';
    require_once __DIR__ . '/app/controllers/StorefrontController.php';
    (new StorefrontController())->handle($shop_label, trim($_GET['url'] ?? '', '/'));
    exit;
}

// --- BEING FOUND ---
// robots.txt, the sitemaps and the shop directory are the same for everyone:
// no session, no cookie, cacheable.
$seo_path = trim($_GET['url'] ?? '', '/');
if (in_array($seo_path, ['robots.txt', 'sitemap.xml', 'sitemap-pages.xml'], true)) {
    header('Cache-Control: public, max-age=3600, s-maxage=3600');
    if ($seo_path === 'robots.txt') {
        header('Content-Type: text/plain; charset=utf-8');
        echo main_robots();
    } else {
        header('Content-Type: application/xml; charset=utf-8');
        echo $seo_path === 'sitemap.xml' ? main_sitemap_index(get_db()) : main_pages_sitemap(get_db());
    }
    exit;
}
if (preg_match('#^shops(?:/([a-z0-9-]{1,60}))?(?:/([a-z0-9-]{1,60}))?$#', $seo_path, $m)) {
    require __DIR__ . '/views/directory/index.php';
    exit;
}

// Start session
session_start();

// A stopped cron cannot say so itself: now and then, a page asks for it
// once it has answered (app/ops/monitor.php).
if (random_int(1, 50) === 1) {
    after_response(function () {
        require_once __DIR__ . '/app/ops/monitor.php';
        ops_watchdog();
    });
}

// Parse the URL
$url = trim($_GET['url'] ?? '', '/');
$segments = $url ? explode('/', $url) : [];

// Visitors get the landing page at /. Signed-in users, and anyone holding a
// "keep me signed in" cookie, carry on to the dashboard below.
if ($url === '' && !is_logged_in() && !isset($_COOKIE[REMEMBER_COOKIE])) {
    if (empty($_SESSION['csrf_token'])) {
        csrf_regenerate();   // the feedback form on the page posts with it
    }
    require __DIR__ . '/views/home/index.php';
    exit;
}
$controller = $segments[0] ?? 'dashboard';
$action     = $segments[1] ?? 'index';
$param      = $segments[2] ?? null;

// Nfelia support looking at this business from Ops: read-only, short
// (app/helpers/view_as.php).
if ($controller === 'support-view') {
    if ($action === 'end' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verify();
        header('Location: ' . view_as_end());
        exit;
    }
    if ($problem = view_as_start((string)$action)) {
        $notice = ['title' => 'This link has expired', 'body' => $problem, 'status' => 410, 'action_url' => ops_url('clients'), 'action_label' => 'Back to Nfelia Ops'];
        require __DIR__ . '/views/public/notice.php';
        exit;
    }
    redirect('dashboard');
}
view_as_guard($url);

// The link at the foot of every campaign email (app/ops/sales.php). Opening
// it asks; the button unsubscribes, so mail scanners that open links don't.
if ($controller === 'unsubscribe') {
    [$send_id, $token] = array_pad(explode('-', (string)$action, 2), 2, '');
    $stmt = get_db()->prepare("SELECT * FROM campaign_sends WHERE id = ?");
    $stmt->execute([(int)$send_id]);
    $send = $stmt->fetch();
    if (!$send || !hash_equals((string)$send['token'], $token)) {
        $notice = ['title' => 'This link does not work', 'body' => 'It may have been copied wrongly. Reply to the email and we\'ll take you off the list ourselves.', 'status' => 404];
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        get_db()->prepare("INSERT IGNORE INTO email_opt_outs (email, campaign_id) VALUES (?, ?)")->execute([strtolower($send['email']), $send['campaign_id']]);
        $notice = ['title' => 'You\'re unsubscribed', 'body' => 'We won\'t send ' . $send['email'] . ' any more emails like that one. Emails about your own Nfelia account still come.', 'status' => 200];
    } else {
        $notice = ['title' => 'Unsubscribe?', 'body' => 'Stop emails like this from the Nfelia team to ' . $send['email'] . '. Emails about your own Nfelia account still come.', 'status' => 200,
                   'form_action' => APP_URL . '/unsubscribe/' . (int)$send['id'] . '-' . $token, 'form_label' => 'Unsubscribe'];
    }
    require __DIR__ . '/views/public/notice.php';
    exit;
}

// nfelia.com/verify: anyone checks a Records Book, or one record in it, and
// can ask the business for the full document (app/controllers/VerifyController.php).
if ($controller === 'verify') {
    require_once __DIR__ . '/app/controllers/VerifyController.php';
    (new VerifyController())->handle((string)$action, $param);
    exit;
}

// ---- ROUTE TABLE ----
// Public routes (no auth required)
$public_routes = ['login', 'register', 'verify', 'forgot-password', 'reset-password', 'recovery-sent', 'login-link', 'signin', 'biz', 'doc', 'sign', 'public-feedback', 'public', 'delivery', 'verify-email', 'deed'];
 
// API/AJAX routes
$ajax_routes = ['api'];
 
if (in_array($controller, $public_routes)) {
    // Public / unauthenticated routes
    dispatch_public($controller, $action, $param);
} elseif (in_array($controller, $ajax_routes)) {
    // AJAX endpoints (including Paystack Webhooks)
    dispatch_ajax($action, $param);
} else {
    // Authenticated routes — check session first
    require_once __DIR__ . '/app/middleware/auth_check.php';
    require_once __DIR__ . '/app/middleware/subscription_check.php';
    dispatch_app($controller, $action, $param);
}
 
// ---- DISPATCHER FUNCTIONS ----
 
/**
 * Whether a URL may call this controller method: public, not static, not a
 * constructor or other magic method, and given every value it needs. Anything
 * else is a 404, never a crash (D10). A controller with __call answers any
 * other name itself (AdminController sends old pages on to Ops).
 */
function route_callable(object $ctrl, string $method, ?string $param): bool {
    if ($method === '' || str_starts_with($method, '__')) {
        return false;
    }
    if (!method_exists($ctrl, $method)) {
        return method_exists($ctrl, '__call');
    }
    $rm = new ReflectionMethod($ctrl, $method);
    return $rm->isPublic() && !$rm->isStatic() && ($param !== null || $rm->getNumberOfRequiredParameters() === 0);
}

/** A controller file that isn't there is a 404 too. */
function route_controller(string $class): ?object {
    $file = __DIR__ . "/app/controllers/{$class}.php";
    if (!is_file($file)) {
        return null;
    }
    require_once $file;
    return new $class();
}

function dispatch_public(string $controller, string $action, ?string $param): void {
    $map = [
        'login'           => 'AuthController',
        'register'        => 'AuthController',
        'verify'          => 'AuthController',
        'forgot-password' => 'AuthController',
        'reset-password'  => 'AuthController',
        'recovery-sent'   => 'AuthController',
        'login-link'      => 'AuthController',
        'signin'          => 'AuthController',
        'biz'             => 'PublicController',
        'doc'             => 'PublicController',
        'sign'            => 'PublicController',
        'delivery'        => 'PublicController', 
        'public'          => 'PublicController', 
        'public-feedback'  => 'AuthController',
        'verify-email'     => 'AuthController',
        'deed'             => 'DeedController',
    ];

    $class = $map[$controller] ?? null;
    if (!$class) { not_found(); return; }
    
    // Default methods for single-segment URLs
    $controller_default_methods = [
        'user-guide'      => 'guide',
        'contact-support' => 'contact',
        'landing-page'    => 'settings',
        'forgot-password' => 'forgot_password',
        'reset-password'  => 'reset_password',
        'recovery-sent'   => 'recovery_sent',
        'login-link'      => 'login_link',
        'signin'          => 'signin',
        'register'        => 'register',   // GET shows the sign-up page; POST creates the account
    ];
    
    if ($action === 'index' && isset($controller_default_methods[$controller])) {
        $action = $controller_default_methods[$controller];
    }

    // For single-segment POST routes (no action in URL), route to the method name.
    if ($action === 'index' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $controller;
    }

    // --- PATCH: PARAMETERIZED TOKEN ROUTING ---
    // If URL is /reset-password/TOKEN, then $controller='reset-password' and $action='TOKEN'
    // We intercept this, shift the action to $param, and force the correct method.
    $is_dynamic_route = in_array($controller, ['biz', 'doc', 'sign', 'delivery', 'reset-password', 'signin', 'deed', 'verify-email']);
    // For deed: 'download' is a real method, don't rewrite it
    $known_controller_methods = ['download', 'create', 'submit', 'approve', 'reject', 'review'];
    $action_is_not_method = (
        $action !== 'index' &&
        $action !== str_replace('-', '_', $controller) &&
        !in_array($action, $known_controller_methods)
    );

    if ($is_dynamic_route && $action_is_not_method) {
        $param  = $action; // The action is actually the URL token
        
        if ($controller === 'doc') {
            // NEW: Check for ?format=pdf in the URL for dompdf routing
            if (isset($_GET['format']) && $_GET['format'] === 'pdf') {
                $action = 'download_pdf';
            } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $action = 'document_action';
            } else {
                $action = 'document';
            }
        } elseif ($controller === 'reset-password') {
            $action = 'reset_password';
            } elseif ($controller === 'biz') {
                $action = 'business';   // PublicController::business()
            } elseif ($controller === 'sign') {
                $action = 'signoff';    // PublicController::signoff()
            } elseif ($controller === 'deed') {
                $action = 'deed';       // DeedController::deed($token)
            } elseif ($controller === 'verify-email') {
                $action = 'verify_email'; // AuthController::verify_email($token)
            } else {
                $action = $controller;
            }
    }
    // ------------------------------------------

    $ctrl = route_controller($class);
    if (!$ctrl) { not_found(); return; }
    
    // Automatically translates hyphenated URLs to snake_case methods
    $method = str_replace('-', '_', $action);

    // Only public, non-static methods with what they need: a helper, a
    // static function or a missing token must never be reachable from a URL
    if (route_callable($ctrl, $method, $param)) {
        $ctrl->$method($param);
    } elseif (route_callable($ctrl, 'index', $param)) {
        $ctrl->index($param);
    } else {
        not_found();
    }
}
 
function dispatch_app(string $controller, string $action, ?string $param): void {
    $map = [
        // Core Modules
        'dashboard'       => 'DashboardController',
        'products'        => 'ProductController',
        'customers'       => 'CustomerController',
        'suppliers'       => 'SupplierController',
        'quotations'      => 'QuotationController',
        'invoices'        => 'InvoiceController',
        'receipts'        => 'ReceiptController',
        'deliveries'      => 'DeliveryController',
        'debts'           => 'DebtController',
        'expenses'        => 'ExpenseController',
        'feedback'        => 'FeedbackController',
        'tasks'           => 'TasksController',
        'more'            => 'MoreController',
        'notifications'   => 'NotificationsController',
        
        // Management & Settings
        'reports'         => 'ReportController',  // profit_loss + profit_loss_pdf
        'orders'          => 'OrderController',
        'bookings'        => 'BookingController',
        'posts'           => 'PostController',
        'purge'           => 'PurgeController',
        'landing-page'    => 'LandingPageController',
        'settings'        => 'SettingsController',
        'referrals'        => 'ReferralController',
        'records'         => 'RecordsController',   // Your records: the Records Book and the plain copy
        'resend-verification' => 'AuthController',
        
        // Admin & System
        'admin'           => 'AdminController',
        'countries'       => 'CountryController',  // Phase 3: Multi-country
        'billing'         => 'PaystackController',
        'momo'            => 'MoMoController',  // Phase 4: Mobile Money
        'deed'            => 'DeedController', // Deed of Acquisition
        'onboarding'      => 'OnboardingController',
        'shop-setup'      => 'ShopSetupController',
        'logout'          => 'AuthController',
        
        // Support Links
        'user-guide'      => 'SupportController',
        'contact-support' => 'SupportController',
        'support'         => 'SupportController'
    ];
 
    $class = $map[$controller] ?? null;
    if (!$class) { not_found(); return; }
    
    $controller_default_methods = [
        'user-guide'      => 'guide',
        'contact-support' => 'contact',
        'landing-page'    => 'settings',
        'admin'           => 'dashboard',
        'countries'       => 'index',
        'momo'            => 'checkout',
    ];
    
    if ($action === 'index' && isset($controller_default_methods[$controller])) {
        $action = $controller_default_methods[$controller];
    }
    
    // For single-segment POST routes (like /logout)
    if ($action === 'index' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $controller;
    }

    $ctrl = route_controller($class);
    if (!$ctrl) { not_found(); return; }
    
    // Automatically translates hyphenated URLs to snake_case methods
    $method = str_replace('-', '_', $action);
    $method = $method ?: 'index';
 
    if (route_callable($ctrl, $method, $param)) {
        $ctrl->$method($param);
    } else {
        not_found();
    }
}
 
function dispatch_ajax(string $action, ?string $param): void {
    header('Content-Type: application/json');

    // Locked (app/helpers/signin.php): nothing answers until it is unlocked,
    // except the sign-in and lock API itself and payment webhooks.
    // The service worker renewing a push subscription is not someone using
    // the app: it neither waits on the lock nor keeps it away.
    $sw_renewal = $action === 'notifications' && ($_GET['action'] ?? '') === 'resubscribe';
    if (!in_array($action, ['auth', 'webhooks', 'errors'], true) && !$sw_renewal && is_logged_in()) {
        if (!auth_still_member()) {
            json_response(['success' => false, 'message' => 'You no longer have access to this business.'], 401);
        }
        if (lock_engaged()) {
            header('X-Nfelia-Locked: 1');
            json_response(['success' => false, 'locked' => true, 'message' => 'Nfelia is locked. Unlock it to carry on.'], 401);
        }
        lock_touch();
    }

    // NFELIA Phase 3 — country selector AJAX
    if ($action === 'set-country') {
        require_once __DIR__ . '/api/ajax/set_country.php';
        exit;
    }

    $ajax_map = [
        'products'      => __DIR__ . '/api/ajax/products.php',
        'customers'     => __DIR__ . '/api/ajax/customers.php',
        'invoices'      => __DIR__ . '/api/ajax/invoices.php',
        'notifications' => __DIR__ . '/api/ajax/notifications.php',
        'auth'          => __DIR__ . '/api/ajax/auth.php',
        'webhooks'      => __DIR__ . '/api/webhooks/paystack.php',
        'errors'        => __DIR__ . '/api/ajax/errors.php',   // a script error in someone's browser
    ];
    
    $file = $ajax_map[$action] ?? null;
    if ($file && file_exists($file)) {
        require_once $file;
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found.']);
    }
}
 
function not_found(): void {
    http_response_code(404);
    include __DIR__ . '/views/404.php';
    exit;
}