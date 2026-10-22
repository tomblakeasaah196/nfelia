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
            // The owner is told by billing_lifecycle() (cron/planner.php).
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

    // Total available seats, the trial's staff seat included (billing_seats)
    require_once __DIR__ . '/billing.php';
    $sub['_total_seats'] = billing_seats($sub);

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
        ['page' => 'landing-page',  'label' => 'Shop Studio',     'icon' => 'landing',       'always' => false],
        ['page' => 'settings',      'label' => 'Settings',        'icon' => 'settings',      'always' => true],
    ];

    // Filter by role
    if (!$is_owner) {
        $items = array_filter($items, fn($item) => $item['always']);
    }

    return array_values($items);
}


/**
 * The notes about every subscription, on each cron pass (cron/planner.php),
 * on the database's clock (Lagos time). Each note goes once (notify()'s
 * dedupe), and only for things that happened in the last week, so the first
 * run after a deploy doesn't dig up old history. Nothing here changes what a
 * business may use: billing_access() (app/helpers/billing.php) works that out
 * from the dates.
 *
 *   trial ending     3 days before trial_ends_at (the trial runs through
 *                    that whole day)
 *   trial ended      the day after
 *   month ending     3 days before a paid month ends (months no longer
 *                    renew by themselves)
 *   due              the month is over: BILLING_GRACE_DAYS left to pay (an
 *                    old card plan that renews itself is left to Paystack)
 *   ended            those days are over, a card's grace is over, or a
 *                    cancelled plan's paid time is
 *
 * Businesses already past their date when paid months started to expire
 * (expiry_exempt) hear nothing until their next payment.
 */
function billing_lifecycle(PDO $db): array {
    require_once __DIR__ . '/billing.php';
    $done = ['trial_ending' => 0, 'trial_ended' => 0, 'renewal_soon' => 0, 'due' => 0, 'ended' => 0];
    $owner_open = "EXISTS (SELECT 1 FROM businesses b WHERE b.id = s.business_id AND b.is_active = 1)";
    $month = "s.status = 'active' AND s.expiry_exempt = 0";
    $grace = (int)BILLING_GRACE_DAYS;

    $each = function (string $sql, callable $fn) use ($db) {
        foreach ($db->query($sql)->fetchAll() as $row) {
            try { $fn($row); } catch (Throwable $e) { error_log('Nfelia billing_lifecycle: ' . $e->getMessage()); }
        }
    };

    $done['trial_ending'] = check_expiring_trials();

    $each("SELECT s.business_id, s.trial_ends_at FROM subscriptions s
           WHERE s.status = 'trial' AND s.trial_ends_at < CURDATE() AND s.trial_ends_at >= CURDATE() - INTERVAL 7 DAY AND {$owner_open}",
        function ($r) use (&$done) {
            $done['trial_ended'] += (int)(bool)notify('billing.trial_ended', (int)$r['business_id'], [], ['dedupe' => 'trial-ended:' . $r['trial_ends_at']]);
        });

    $each("SELECT s.business_id, s.current_period_end, DATEDIFF(s.current_period_end, CURDATE()) AS days FROM subscriptions s
           WHERE {$month} AND s.current_period_end BETWEEN CURDATE() AND CURDATE() + INTERVAL 3 DAY AND {$owner_open}",
        function ($r) use (&$done) {
            $done['renewal_soon'] += (int)(bool)notify('billing.renewal_soon', (int)$r['business_id'],
                ['days' => (int)$r['days'], 'period_end' => $r['current_period_end']], ['dedupe' => 'renewal-soon:' . substr((string)$r['current_period_end'], 0, 10)]);
        });

    // An old card plan that still renews itself is left to Paystack: a
    // failed charge arrives as invoice.payment_failed (billing.failed).
    $each("SELECT s.business_id, s.current_period_end FROM subscriptions s
           WHERE {$month} AND s.paystack_subscription_code IS NULL
             AND s.current_period_end < CURDATE() AND s.current_period_end >= CURDATE() - INTERVAL {$grace} DAY AND {$owner_open}",
        function ($r) use (&$done) {
            $end = substr((string)$r['current_period_end'], 0, 10);
            $done['due'] += (int)(bool)notify('billing.due', (int)$r['business_id'],
                ['period_end' => $end, 'grace_ends' => billing_add_days($end, BILLING_GRACE_DAYS)], ['dedupe' => 'due:' . $end]);
        });

    $each("SELECT s.business_id, DATE(s.current_period_end) AS ended FROM subscriptions s
           WHERE {$month} AND s.current_period_end < CURDATE() - INTERVAL {$grace} DAY AND s.current_period_end >= CURDATE() - INTERVAL " . ($grace + 7) . " DAY AND {$owner_open}
           UNION ALL
           SELECT s.business_id, s.grace_ends_at FROM subscriptions s
           WHERE s.status = 'past_due' AND s.grace_ends_at < NOW() AND s.grace_ends_at >= NOW() - INTERVAL 7 DAY AND {$owner_open}
           UNION ALL
           SELECT s.business_id, s.current_period_end FROM subscriptions s
           WHERE s.status = 'cancelled' AND s.current_period_end < CURDATE() AND s.current_period_end >= CURDATE() - INTERVAL 7 DAY AND {$owner_open}",
        function ($r) use (&$done) {
            $done['ended'] += (int)(bool)notify('billing.ended', (int)$r['business_id'], [], ['dedupe' => 'ended:' . $r['ended']]);
        });

    return $done;
}


/**
 * The note for each business whose free trial ends within three days, once
 * per trial. Part of billing_lifecycle(); cron/check_trials.php runs it by
 * hand. Returns how many businesses were told.
 */
function check_expiring_trials(): int {
    $db = get_db();
    // trial_ends_at is a date, and the trial runs through the whole of it:
    // ending "in 3 days" means 3 calendar days from today.
    $stmt = $db->query("
        SELECT s.business_id, s.trial_ends_at, DATEDIFF(s.trial_ends_at, CURDATE()) AS days_left
        FROM subscriptions s
        JOIN businesses b ON b.id = s.business_id AND b.is_active = 1
        WHERE s.status = 'trial'
          AND s.trial_ends_at BETWEEN CURDATE() AND CURDATE() + INTERVAL 3 DAY
    ");
    $sent = 0;
    foreach ($stmt->fetchAll() as $row) {
        $sent += (int)(bool)notify('billing.trial_ending', (int)$row['business_id'],
            ['days' => (int)$row['days_left'], 'trial_ends' => $row['trial_ends_at']], ['dedupe' => 'trial-ending:' . $row['trial_ends_at']]);
    }
    return $sent;
}
