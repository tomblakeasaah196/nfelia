-- Billing lockdown (docs/AUDIT_2026-10.md, PR 1).
--
-- subscriptions.expiry_exempt  1 for a business that was already past the end
--                              of its paid month when paid months started to
--                              expire (B1). Tom's decision: those are left as
--                              they are; the rule applies from their next
--                              payment, which sets it back to 0
--                              (billing_start_month in app/helpers/billing.php).
-- subscription_payments.kind   'month' (a month of Nfelia) or 'seats' (more
--                              seats in the month already paid for).
-- subscription_payments.extra_seats       seats beside the owner it pays for
--                              ('month') or adds ('seats').
-- subscription_payments.covered_user_ids  the staff the owner ticked for the
--                              month, comma-separated; everyone else is
--                              switched off when it is approved (B4).
--
-- Each statement is safe to run twice: migrate.php runs the file in one
-- exec(), which reports an error only from the first statement.

ALTER TABLE `subscriptions`
    ADD COLUMN IF NOT EXISTS `expiry_exempt` TINYINT(1) NOT NULL DEFAULT 0 AFTER `grace_ends_at`;

ALTER TABLE `subscription_payments`
    ADD COLUMN IF NOT EXISTS `kind` ENUM('month','seats') NOT NULL DEFAULT 'month' AFTER `payment_method`,
    ADD COLUMN IF NOT EXISTS `extra_seats` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `kind`,
    ADD COLUMN IF NOT EXISTS `covered_user_ids` VARCHAR(255) NULL DEFAULT NULL AFTER `extra_seats`;

UPDATE `subscriptions`
SET `expiry_exempt` = 1
WHERE `status` = 'active'
  AND `current_period_end` IS NOT NULL
  AND `current_period_end` < CURDATE();
