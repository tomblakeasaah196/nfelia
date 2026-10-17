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
 
// Load helpers
require_once __DIR__ . '/app/helpers/functions.php';
require_once __DIR__ . '/app/helpers/auth.php';
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

// --- GLOBAL EXCEPTION & FATAL ERROR HANDLER ---
// Start output buffering so we can erase half-loaded pages if a crash happens
ob_start();

set_exception_handler(function ($e) {
    // Silently log the exact error for your eyes only
    error_log("Nfelia Exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    // Erase anything that was already printed to the screen
    while (ob_get_level()) { ob_end_clean(); }
    
    // Serve the beautiful 500 page
    http_response_code(500);
    require __DIR__ . '/views/500.php';
    exit;
});

register_shutdown_function(function () {
    $error = error_get_last();
    // Catch fatal memory errors, parse errors, and strict type errors
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        while (ob_get_level()) { ob_end_clean(); }
        http_response_code(500);
        require __DIR__ . '/views/500.php';
        exit;
    }
});

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

// --- CRON ROUTE ---
if (preg_match('#^cron/expire/([a-zA-Z0-9]+)$#', $url, $m)) {
    require_once __DIR__ . '/app/controllers/PublicController.php';
    (new PublicController())->cron_expire($m[1]);
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

    require_once __DIR__ . "/app/controllers/{$class}.php";
    $ctrl = new $class();
    
    // Automatically translates hyphenated URLs to snake_case methods
    $method = str_replace('-', '_', $action);

    // is_callable, not method_exists: a private or protected helper must
    // never be reachable from a URL (calling one would fatal with a 500).
    if (is_callable([$ctrl, $method])) {
        $ctrl->$method($param);
    } else {
        $ctrl->index($param);
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
        
        // Management & Settings
        'reports'         => 'ReportController',  // profit_loss + profit_loss_pdf
        'orders'          => 'OrderController',
        'bookings'        => 'BookingController',
        'posts'           => 'PostController',
        'purge'           => 'PurgeController',
        'landing-page'    => 'LandingPageController',
        'settings'        => 'SettingsController',
        'account'         => 'AccountController',
        'referrals'        => 'ReferralController',
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

    require_once __DIR__ . "/app/controllers/{$class}.php";
    $ctrl = new $class();
    
    // Automatically translates hyphenated URLs to snake_case methods
    $method = str_replace('-', '_', $action);
    $method = $method ?: 'index';
 
    if (is_callable([$ctrl, $method])) {
        $ctrl->$method($param);
    } else {
        not_found();
    }
}
 
function dispatch_ajax(string $action, ?string $param): void {
    header('Content-Type: application/json');
    
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
        'webhooks'      => __DIR__ . '/api/webhooks/paystack.php',
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