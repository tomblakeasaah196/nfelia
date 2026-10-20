-- Sign-in hardening.
--
-- 1. users.session_epoch: moved on when the password changes (or the
--    person asks to be signed out everywhere). Every session carries the
--    epoch it started with; one that no longer matches ends on its next
--    request (auth_still_member in app/helpers/auth.php).
--
-- 2. "Keep me signed in" tokens are kept as their SHA-256 from now on
--    (app/helpers/signin.php). Tokens saved before this were 128 hex
--    characters; a hash is 64, so the statement below is safe to run
--    again: it only ever touches a raw token. The code also accepts a raw
--    token until it has been rewritten, so nobody is signed out by this.

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `session_epoch` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `is_active`;

UPDATE `sessions` SET `session_token` = SHA2(`session_token`, 256)
 WHERE CHAR_LENGTH(`session_token`) = 128;

-- Expired "keep me signed in" rows were never removed; the cron does now
-- (cron/planner.php), and this clears the backlog.
DELETE FROM `sessions` WHERE `expires_at` < NOW() - INTERVAL 1 DAY;
