<?php
/**
 * Nfelia — Subscription Middleware & Helpers
 * Include early in app bootstrap (after auth helpers).
 *
 * Provides:
 *  - check_subscription()      → loads & caches subscription state, auto-transitions
 *  - get_subscription_status() → returns cached subscription array
 *  - require_active_subscription() → blocks access if suspended/cancelled
 *  - is_subscription_active()  → boolean: can the user use the app fully?
 *  - subscription_banner()     → returns HTML banner string (or empty)
 *  - require_owner()           → blocks non-owner access to sensitive routes
 *  - can_add_staff()           → checks seat limits
 *  - get_seat_usage()          → returns [used, total]
 */

// ─── Cached subscription state ───────────────────────
$_subscription_cache = null;

/**
 * Load and cache the subscription for the current business.
 * Also handles automatic state transitions:
 *   - trial expired → suspended
 *   - past_due grace expired → suspended
 *
 * Call this once early in the request (after auth is established).
 */
function check_subscription(): ?array {
    global $_subscription_cache;

    if ($_subscription_cache !== null) {
        return $_subscription_cache;
    }

    if (!is_logged_in()) {
        return null;
    }

    $db     = get_db();
    $biz_id = auth_business_id();

    $stmt = $db->prepare("
        SELECT * FROM subscriptions WHERE business_id = ? LIMIT 1
    ");
    $stmt->execute([$biz_id]);
    $sub = $stmt->fetch();

    if (!$sub) {
        // No subscription record — create a trial one
        $stmt = $db->prepare("
            INSERT INTO subscriptions (business_id, plan, status, user_seats, trial_ends_at, created_at)
            VALUES (?, 'standard', 'trial', 1, DATE_ADD(NOW(), INTERVAL 14 DAY), NOW())
        ");
        $stmt->execute([$biz_id]);

        $stmt = $db->prepare("SELECT * FROM subscriptions WHERE business_id = ? LIMIT 1");
        $stmt->execute([$biz_id]);
        $sub = $stmt->fetch();
    }

    // ─── Automatic state transitions ──────────────────

    $now = new DateTime();
    $transitioned = false;

    // Trial expired → suspended
    if ($sub['status'] === 'trial' && $sub['trial_ends_at']) {
        $trial_end = new DateTime($sub['trial_ends_at']);
        if ($now > $trial_end) {
            $stmt = $db->prepare("
                UPDATE subscriptions SET status = 'suspended', updated_at = NOW()
                WHERE id = ? AND status = 'trial'
            ");
            $stmt->execute([$sub['id']]);
            $sub['status'] = 'suspended';
            $transitioned = true;

            // Notify owner
            _subscription_notify($db, $biz_id, 'trial_ending',
                'Your free trial has ended. Subscribe to continue using Nfelia.',
                'billing');
        }
    }

    // Past due → grace expired → suspended
    if ($sub['status'] === 'past_due' && $sub['grace_ends_at']) {
        $grace_end = new DateTime($sub['grace_ends_at']);
        if ($now > $grace_end) {
            $stmt = $db->prepare("
                UPDATE subscriptions SET status = 'suspended', updated_at = NOW()
                WHERE id = ? AND status = 'past_due'
            ");
            $stmt->execute([$sub['id']]);
            $sub['status'] = 'suspended';
            $transitioned = true;

            _subscription_notify($db, $biz_id, 'subscription_failed',
                'Your subscription has been suspended due to unpaid charges. Please update your payment method.',
                'billing');
        }
    }

    // ─── Compute helper fields ────────────────────────

    // Phase 4: pending_verification = MoMo receipt submitted, allow access
    $sub['_is_active']   = in_array($sub['status'], ['trial', 'active', 'past_due', 'pending_verification']);
    $sub['_is_trialing'] = $sub['status'] === 'trial';
    $sub['_is_past_due'] = $sub['status'] === 'past_due';
    $sub['_is_suspended'] = $sub['status'] === 'suspended';
    $sub['_is_cancelled'] = $sub['status'] === 'cancelled';

    // Trial days remaining
    $sub['_trial_days_left'] = 0;
    if ($sub['_is_trialing'] && $sub['trial_ends_at']) {
        $diff = $now->diff(new DateTime($sub['trial_ends_at']));
        $sub['_trial_days_left'] = $diff->invert ? 0 : $diff->days + 1; // +1 to include today
    }

    // Grace days remaining
    $sub['_grace_days_left'] = 0;
    if ($sub['_is_past_due'] && $sub['grace_ends_at']) {
        $diff = $now->diff(new DateTime($sub['grace_ends_at']));
        $sub['_grace_days_left'] = $diff->invert ? 0 : $diff->days + 1;
    }

    // Total available seats
    $sub['_total_seats'] = ($sub['user_seats'] ?? 1) + ($sub['extra_seats'] ?? 0);

    $_subscription_cache = $sub;
    return $sub;
}


/**
 * Get cached subscription (must call check_subscription() first).
 */
function get_subscription_status(): ?array {
    global $_subscription_cache;
    return $_subscription_cache;
}


/**
 * Can the business use operational modules?
 */
function is_subscription_active(): bool {
    $sub = check_subscription();
    return $sub ? $sub['_is_active'] : false;
}


/**
 * Block access if subscription is suspended or cancelled.
 * Allows: settings, billing routes always.
 */
function require_active_subscription(): void {
    $sub = check_subscription();
    if (!$sub) return;

    if ($sub['_is_suspended'] || $sub['_is_cancelled']) {
        $current_page = $_GET['page'] ?? '';
        $allowed = ['settings', 'paystack-initialize', 'paystack-callback', 'api/webhooks/paystack', 'logout'];

        // Allow settings and billing-related pages
        foreach ($allowed as $route) {
            if (str_starts_with($current_page, $route)) {
                return; // Let through
            }
        }

        // Block everything else — redirect to billing
        $_SESSION['flash_error'] = 'Your subscription is ' . $sub['status'] . '. Please update your billing to continue.';
        redirect('settings?tab=billing');
        exit;
    }
}


/**
 * Ensure current user is the business owner.
 */
function require_owner(): void {
    $user = auth_user();
    if (($user['role'] ?? 'staff') !== 'owner') {
        $_SESSION['flash_error'] = 'Only the account owner can access this section.';
        redirect('dashboard');
        exit;
    }
}


/**
 * Check if the business can add another staff member.
 */
function can_add_staff(): bool {
    $sub  = check_subscription();
    $used = get_seat_usage();
    return $used['used'] < $used['total'];
}


/**
 * Get current seat usage.
 * @return array{used: int, total: int}
 */
function get_seat_usage(): array {
    $sub    = check_subscription();
    $db     = get_db();
    $biz_id = auth_business_id();

    // PATCH: Query aligned with users table SSOT
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM users 
        WHERE business_id = ? AND is_active = 1
    ");
    $stmt->execute([$biz_id]);
    $used = (int)$stmt->fetchColumn();

    $total = $sub ? $sub['_total_seats'] : 1;

    return ['used' => $used, 'total' => $total];
}


/**
 * Generate the subscription warning banner HTML.
 * Returns empty string if no banner needed.
 */
function subscription_banner(): string {
    $sub = check_subscription();
    if (!$sub) return '';

    $html = '';

    // ─── Trial banner ────────────────────────────────
    if ($sub['_is_trialing']) {
        $days = $sub['_trial_days_left'];
        $urgency = $days <= 3 ? 'bg-amber-50 border-amber-300 text-amber-900' : 'bg-blue-50 border-blue-300 text-blue-900';
        $icon = $days <= 3
            ? '<svg class="w-5 h-5 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>'
            : '<svg class="w-5 h-5 text-blue-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';

        $day_text = $days === 1 ? '1 day' : $days . ' days';

        $html = '
        <div class="' . $urgency . ' border rounded-lg px-4 py-3 mx-4 mt-4 flex items-center justify-between gap-3 text-sm">
            <div class="flex items-center gap-2">
                ' . $icon . '
                <span><strong>Free trial:</strong> ' . $day_text . ' remaining. You have full access to all features.</span>
            </div>
            <a href="' . APP_URL . '/settings?tab=billing" class="shrink-0 px-3 py-1.5 bg-brand-plum text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-opacity">
                Subscribe Now
            </a>
        </div>';
    }

    // ─── Past due banner ─────────────────────────────
    if ($sub['_is_past_due']) {
        $days = $sub['_grace_days_left'];
        $day_text = $days === 1 ? '1 day' : $days . ' days';

        $html = '
        <div class="bg-amber-50 border border-amber-400 rounded-lg px-4 py-3 mx-4 mt-4 flex items-center justify-between gap-3 text-sm text-amber-900">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                <span><strong>Payment past due.</strong> Your access will be restricted in ' . $day_text . '. Please update your payment method.</span>
            </div>
            <a href="' . APP_URL . '/settings?tab=billing" class="shrink-0 px-3 py-1.5 bg-amber-600 text-white text-xs font-semibold rounded-lg hover:bg-amber-700 transition-colors">
                Update Payment
            </a>
        </div>';
    }

    // ─── Suspended banner ────────────────────────────
    if ($sub['_is_suspended']) {
        $html = '
        <div class="bg-red-50 border border-red-400 rounded-lg px-4 py-3 mx-4 mt-4 flex items-center justify-between gap-3 text-sm text-red-900">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                <span><strong>Account suspended.</strong> Your subscription has lapsed. Subscribe now to restore full access to your business.</span>
            </div>
            <a href="' . APP_URL . '/settings?tab=billing" class="shrink-0 px-3 py-1.5 bg-red-600 text-white text-xs font-semibold rounded-lg hover:bg-red-700 transition-colors">
                Reactivate
            </a>
        </div>';
    }

    // ─── Cancelled banner ────────────────────────────
    if ($sub['_is_cancelled']) {
        $html = '
        <div class="bg-gray-50 border border-gray-300 rounded-lg px-4 py-3 mx-4 mt-4 flex items-center justify-between gap-3 text-sm text-gray-700">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span><strong>Subscription cancelled.</strong> You can resubscribe anytime to restore access.</span>
            </div>
            <a href="' . APP_URL . '/settings?tab=billing" class="shrink-0 px-3 py-1.5 bg-brand-plum text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-opacity">
                Resubscribe
            </a>
        </div>';
    }

    return $html;
}


/**
 * Check if current user has owner role.
 */
function is_owner(): bool {
    $user = auth_user();
    return ($user['role'] ?? 'staff') === 'owner';
}


/**
 * Get role-restricted navigation items.
 * Staff members can't see: Settings (staff/billing tabs), P&L Reports.
 */
function get_nav_items(): array {
    $is_owner = is_owner();

    $items = [
        ['page' => 'dashboard',     'label' => 'Dashboard',       'icon' => 'home',          'always' => true],
        ['page' => 'products',      'label' => 'Products',        'icon' => 'products',      'always' => true],
        ['page' => 'receipts',      'label' => 'Receipts',        'icon' => 'receipts',      'always' => true],
        ['page' => 'invoices',      'label' => 'Invoices',        'icon' => 'invoices',      'always' => true],
        ['page' => 'expenses',      'label' => 'Expenses',        'icon' => 'expenses',      'always' => true],
        ['page' => 'debts',         'label' => 'Debts',           'icon' => 'debts',         'always' => true],
        ['page' => 'quotations',    'label' => 'Quotations',      'icon' => 'quotations',    'always' => true],
        ['page' => 'deliveries',    'label' => 'Deliveries',      'icon' => 'deliveries',    'always' => true],
        ['page' => 'reports',       'label' => 'Profit & Loss',   'icon' => 'reports',       'always' => false],
        ['page' => 'landing-page',  'label' => 'Landing Page',    'icon' => 'landing',       'always' => false],
        ['page' => 'settings',      'label' => 'Settings',        'icon' => 'settings',      'always' => true],
    ];

    // Filter by role
    if (!$is_owner) {
        $items = array_filter($items, fn($item) => $item['always']);
    }

    return array_values($items);
}


/**
 * Internal: create subscription notification.
 */
function _subscription_notify(PDO $db, int $biz_id, string $type, string $message, string $link_page = ''): void {
    try {
        $stmt = $db->prepare("
            INSERT INTO notifications (business_id, type, title, message, action_url, created_at) -- NFELIA AUDIT FIX C07 2026-06-03
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $title_map = [
            'trial_ending'           => 'Trial Ended',
            'trial_expiring_soon'     => 'Trial Ending Soon',
            'subscription_failed'  => 'Account Suspended',
            'subscription_activated'  => 'Subscription Active',
            'payment_failed'          => 'Payment Failed',
            'payment_success'         => 'Payment Received',
            'staff_added'             => 'New Team Member',
            'staff_removed'           => 'Team Member Removed',
        ];
        $title = $title_map[$type] ?? 'Subscription Update';
        $link  = $link_page ? APP_URL . '/' . $link_page : null;
        $stmt->execute([$biz_id, $type, $title, $message, $link]);
    } catch (Exception $e) {
        error_log("Nfelia subscription notification failed: " . $e->getMessage());
    }
}


/**
 * Cron helper: check all businesses for expiring trials.
 * Call daily via cron: php cron/check_trials.php
 */
function check_expiring_trials(): void {
    $db = get_db();

    // Notify at 3 days before trial end
    $stmt = $db->prepare("
        SELECT s.business_id, s.trial_ends_at
        FROM subscriptions s
        WHERE s.status = 'trial'
          AND s.trial_ends_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY)
          AND s.business_id NOT IN (
              SELECT business_id FROM notifications
              WHERE type = 'trial_expiring_soon'
              AND created_at > DATE_SUB(NOW(), INTERVAL 3 DAY)
          )
    ");
    $stmt->execute();
    $expiring = $stmt->fetchAll();

    foreach ($expiring as $row) {
        $days = (new DateTime())->diff(new DateTime($row['trial_ends_at']))->days + 1;
        $msg  = "Your free trial ends in {$days} day" . ($days !== 1 ? 's' : '') . ". Subscribe to keep your business running on Nfelia.";
        _subscription_notify($db, $row['business_id'], 'trial_expiring_soon', $msg, 'settings?tab=billing');
    }
}
