<?php
/**
 * Nfelia — Subscription Middleware
 * Sends a business whose trial or paid month (and its grace days) is over to
 * Billing. billing_access() in app/helpers/billing.php decides, the same
 * answer that closes the shop and stops reminders.
 *
 * NFELIA AUDIT FIX — C03 — Redirect target fixed from account/billing to settings?tab=billing — 2026-06-03
 */

// Routes that bypass subscription checks entirely: paying, settings, finishing
// sign-up (an expired trial must not bounce between onboarding and billing)
// and asking for help. Notifications stay open too: the alert that says the
// account is on hold must open (and be marked read) while everything else is
// shut. Your records too: a business's records are its own, paid up or not.
// The Nfelia business locks like any other: the team works in Nfelia Ops
// (ops.nfelia.com), not in it.
$exempt_routes = ['settings', 'account', 'logout', 'billing', 'momo', 'countries', 'onboarding',
                  'support', 'contact-support', 'user-guide', 'notifications', 'records'];

if (!in_array($controller ?? '', $exempt_routes)) {
    $db   = get_db();
    // SELECT *, not a column list: during a deploy the code arrives a minute
    // before the migration that adds expiry_exempt, and a missing column
    // must not stop every page
    $stmt = $db->prepare("SELECT * FROM subscriptions WHERE business_id = ?");
    $stmt->execute([auth_business_id()]);
    $sub  = $stmt->fetch() ?: null;

    // A Mobile Money receipt waiting for the Nfelia team unlocks nothing (Tom's
    // decision), but the owner is told it has arrived.
    $stmt = $db->prepare("SELECT COUNT(*) FROM subscription_payments WHERE business_id = ? AND status = 'pending_verification'");
    $stmt->execute([auth_business_id()]);
    $waiting = (int)$stmt->fetchColumn() > 0;

    unset($_SESSION['billing_warning']);
    switch (billing_access($sub)) {
        case 'locked':
            if (!is_owner()) {
                flash('warning', 'Nfelia is paused for this business until the owner pays for the month.');
            } elseif ($waiting) {
                flash('warning', 'We have your Mobile Money payment and are checking it now. Nfelia opens again as soon as it is approved.');
            } elseif (($sub['status'] ?? '') === 'trial') {
                flash('warning', 'Your free trial has ended. Pay for a month to carry on.');
            } elseif (($sub['status'] ?? '') === 'suspended') {
                flash('error', 'Your account is suspended. Pay for a month to open it again, or contact support.');
            } else {
                flash('warning', 'Your paid month has ended. Pay for the next month to open Nfelia and your website again.');
            }
            redirect('settings?tab=billing');

        case 'grace':
            $until = ($sub['status'] ?? '') === 'past_due' && !empty($sub['grace_ends_at'])
                ? substr($sub['grace_ends_at'], 0, 10)
                : billing_add_days((string)$sub['current_period_end'], BILLING_GRACE_DAYS);
            $_SESSION['billing_warning'] = $waiting
                ? 'We are checking your Mobile Money payment.'
                : 'Your paid month has ended. Pay by ' . date('j M', strtotime($until)) . ' to keep Nfelia and your website open.';
            break;

        default:
            if ($waiting) {
                $_SESSION['billing_warning'] = 'We are checking your Mobile Money payment. We will tell you as soon as it is approved.';
            } elseif (($sub['status'] ?? '') === 'cancelled' && !empty($sub['current_period_end'])) {
                $_SESSION['billing_warning'] = 'Your subscription is cancelled. You keep full access until '
                    . date('j M Y', strtotime((string)$sub['current_period_end'])) . '.';
            }
    }
}
