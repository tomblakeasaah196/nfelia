-- Shop websites, reminders and the rest (docs/AUDIT_2026-10.md, PR 3).
--
-- tasks.repeat_day      the day of the month a monthly repeat was set for,
--                       so the 31st comes back as the 31st (or the month's
--                       last day) and doesn't drift to the 28th (P11).
-- reviews.ip, user_agent
--                       who posted a review, so the team can tell a shop
--                       writing its own reviews (W14).
--
-- Each statement is safe to run twice: migrate.php runs the file in one
-- exec(), which reports an error only from the first statement.

ALTER TABLE `tasks`
    ADD COLUMN IF NOT EXISTS `repeat_day` TINYINT UNSIGNED NULL DEFAULT NULL AFTER `repeat_every`;

UPDATE `tasks` SET repeat_day = DAY(due_date)
WHERE repeat_every = 'month' AND repeat_day IS NULL AND due_date IS NOT NULL;

ALTER TABLE `reviews`
    ADD COLUMN IF NOT EXISTS `ip` VARCHAR(45) NULL DEFAULT NULL AFTER `comment`,
    ADD COLUMN IF NOT EXISTS `user_agent` VARCHAR(255) NULL DEFAULT NULL AFTER `ip`;
