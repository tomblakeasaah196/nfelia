<?php
/**
 * Nfelia — Subscription Middleware
 * Blocks access for suspended/expired accounts.
 * Allows grace period: 3 days after billing failure.
 *
 * NFELIA AUDIT FIX — C03 — Redirect target fixed from account/billing to settings?tab=billing — 2026-06-03
 * NFELIA Phase 4   — Added pending_verification as passthrough status — 2026-06-03
 */

// Routes that bypass subscription checks entirely
// Notifications stay open too: the alert that says the account is on hold
// must open (and be marked read) even while everything else is shut.
$exempt_routes = ['settings', 'account', 'logout', 'billing', 'momo', 'countries', 'notifications'];

if (!in_array($controller ?? '', $exempt_routes)) {
    $db   = get_db();
    $stmt = $db->prepare("SELECT status, trial_ends_at, current_period_end, grace_ends_at FROM subscriptions WHERE business_id = ?");
    $stmt->execute([auth_business_id()]);
    $sub  = $stmt->fetch();

    if ($sub && !subscription_open($sub)) {
        // The same rule closes the shop and stops the cron's reminders
        // (subscription_open() in app/helpers/functions.php).
        [$level, $why] = match ($sub['status']) {
            'trial'     => ['warning', 'Your free trial has ended. Choose a plan to carry on. Everything you added is still here.'],
            'past_due'  => ['error', 'Your payment is overdue and the 3 extra days have passed. Pay to carry on.'],
            'cancelled' => ['warning', 'Your subscription has ended. Resubscribe to carry on where you left off.'],
            default     => ['error', 'Your account is on hold. Open billing to restore it, or contact Nfelia support.'],
        };
        flash($level, $why);
        redirect('settings?tab=billing');
    }

    // Still open, with something to know about. Worked out on every page,
    // so it goes away the moment the plan is paid.
    unset($_SESSION['billing_warning']);
    if ($sub) {
        $day = fn($d) => date('j M Y', strtotime((string)$d));
        if ($sub['status'] === 'past_due') {
            $grace = $sub['grace_ends_at'] ?: planner_add_days(substr((string)$sub['current_period_end'], 0, 10), 3);
            $left  = max(0, planner_days_between(planner_today(), substr((string)$grace, 0, 10)));
            $_SESSION['billing_warning'] = 'Your payment is due. Pay ' . ($left === 0 ? 'today' : 'within ' . $left . ' day' . ($left === 1 ? '' : 's'))
                . ' to keep using Nfelia.';
        } elseif ($sub['status'] === 'pending_verification') {
            $_SESSION['billing_warning'] = 'Your payment is being checked. We\'ll let you know within 24 hours.';
        } elseif ($sub['status'] === 'cancelled') {
            $_SESSION['billing_warning'] = 'Your subscription is cancelled. You keep full access until '
                . $day($sub['current_period_end'] ?: $sub['trial_ends_at']) . '.';
        }
    }
}
