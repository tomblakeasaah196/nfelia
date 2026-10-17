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
$exempt_routes = ['settings', 'account', 'logout', 'billing', 'momo', 'countries'];

if (!in_array($controller ?? '', $exempt_routes)) {
    $db   = get_db();
    $stmt = $db->prepare("SELECT status, trial_ends_at, current_period_end, grace_ends_at FROM subscriptions WHERE business_id = ?");
    $stmt->execute([auth_business_id()]);
    $sub  = $stmt->fetch();

    if ($sub) {
        $today = new DateTime();

        switch ($sub['status']) {

            case 'trial':
                if ($sub['trial_ends_at'] && $today > new DateTime($sub['trial_ends_at'])) {
                    flash('warning', 'Your free trial has ended. Subscribe to continue.');
                    redirect('settings?tab=billing');
                }
                break;

            case 'past_due':
                $grace_end = $sub['grace_ends_at']
                    ? new DateTime($sub['grace_ends_at'])
                    : (new DateTime($sub['current_period_end']))->modify('+3 days');

                if ($today > $grace_end) {
                    flash('error', 'Your payment failed and the grace period has ended. Please update billing.');
                    redirect('settings?tab=billing');
                }
                // Show warning banner within grace period
                $_SESSION['billing_warning'] = 'Payment failed. Access restricted in ' .
                    max(0, (int)$today->diff($grace_end)->days) . ' day(s). Please update billing.';
                break;

            case 'pending_verification':
                // User has submitted MoMo receipt — allow full access for up to 3 days
                // Admin reviews and either approves (→ active) or rejects (→ trial/past_due)
                $_SESSION['billing_warning'] = 'Your payment is under review. We\'ll notify you within 24 hours.';
                break;

            case 'suspended':
                flash('error', 'Your account is suspended. Please update your billing to restore access.');
                redirect('settings?tab=billing');
                break;

            case 'cancelled':
                flash('warning', 'Your subscription is cancelled. Resubscribe to continue.');
                redirect('settings?tab=billing');
                break;

            // 'active' falls through with no action
        }
    }
}
