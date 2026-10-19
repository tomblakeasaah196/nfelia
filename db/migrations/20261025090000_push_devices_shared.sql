-- Phone notifications: one phone can hold alerts for several people.
--
-- A push subscription belongs to a browser, not a person. Until now one row
-- per endpoint meant a shared phone (or one person's second account)
-- followed whoever turned alerts on last, and the other person's pushes were
-- quietly skipped. Now the pair (endpoint, person) is what is unique: each
-- person who turned alerts on here keeps them until they turn them off.
--
-- push_dead remembers an endpoint the push service has declared gone
-- (404/410) or that can't be used, so a phone that still holds it makes a
-- new subscription instead of saving the dead one again and again.

ALTER TABLE `push_subscriptions`
    DROP INDEX IF EXISTS `uniq_push_endpoint`,
    ADD UNIQUE INDEX IF NOT EXISTS `uniq_push_endpoint_user` (`endpoint_hash`, `user_id`);

CREATE TABLE IF NOT EXISTS `push_dead` (
    `endpoint_hash` CHAR(64)    NOT NULL PRIMARY KEY,
    `reason`        VARCHAR(80) NOT NULL,
    `created_at`    DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The per-phone diagnostics (More > Notifications) and the revival of pushes
-- that waited for a phone read the outbox by person; the nightly clean-up
-- reads notifications by age. Neither had an index.
ALTER TABLE `notify_outbox`
    ADD INDEX IF NOT EXISTS `idx_outbox_user`  (`user_id`, `channel`, `id`),
    ADD INDEX IF NOT EXISTS `idx_outbox_staff` (`staff_id`, `channel`, `id`);

ALTER TABLE `notifications`
    ADD INDEX IF NOT EXISTS `idx_notifications_created` (`created_at`);
