-- notifications.type takes any name.
--
-- It was an ENUM, and two kinds of notification used names the list lacked:
-- 'trial_expiring_soon' (the warning three days before a free trial ends)
-- and 'referral_rewarded' (telling a business its referral just paid). In
-- strict mode each insert failed and was only logged, so no business ever
-- saw either one.
--
-- A plain string keeps every value already stored, including any production
-- holds that the schema dump lacks, which is why this is not a longer ENUM.
-- One statement: migrate.php runs the file in one exec(), which reports an
-- error only from the first statement.

ALTER TABLE `notifications`
    MODIFY `type` VARCHAR(50) NOT NULL DEFAULT 'general';
