-- The notification engine (app/helpers/notify.php).
--
-- notifications        one row per person now, so each person has their own
--                      read and unread. `event` names the kind (see
--                      notify_events()); rows from before this have no event
--                      and keep their old visibility (api/ajax/notifications.php).
--                      dedupe_key stops the same alert reaching the same
--                      person twice (a Paystack retry, a double tap).
-- notify_outbox        every push and email waiting to go, sent, or given up
--                      on, with why. Sent straight after the page answers;
--                      cron/planner.php retries what failed. Emails to
--                      shoppers have no bell row, so they carry their own
--                      dedupe_key.
-- notification_prefs   a person's Phone and Email choices per topic, only
--                      where they differ from the defaults.
-- users.hide_amounts   phone notifications say "Payment received" instead of
--                      the amount and the customer's name.
--
-- The choices people had already made carry over: switched-off money
-- alerts, reminders and morning emails. Low stock was off for everyone by
-- default, not by choice, so it starts on like every other alert.

ALTER TABLE `notifications`
    ADD COLUMN IF NOT EXISTS `event`      VARCHAR(60)  NULL DEFAULT NULL AFTER `type`,
    ADD COLUMN IF NOT EXISTS `data`       TEXT         NULL DEFAULT NULL AFTER `action_url`,
    ADD COLUMN IF NOT EXISTS `dedupe_key` VARCHAR(150) NULL DEFAULT NULL AFTER `data`,
    ADD COLUMN IF NOT EXISTS `read_at`    DATETIME     NULL DEFAULT NULL AFTER `is_read`,
    ADD UNIQUE INDEX IF NOT EXISTS `uniq_notifications_dedupe` (`user_id`, `dedupe_key`),
    ADD INDEX IF NOT EXISTS `idx_notifications_feed` (`user_id`, `id`),
    ADD INDEX IF NOT EXISTS `idx_notifications_unread` (`user_id`, `is_read`);

CREATE TABLE IF NOT EXISTS `notify_outbox` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id`     INT          NULL,
    `notification_id` INT          NULL,
    `user_id`         INT          NULL,
    `event`           VARCHAR(60)  NOT NULL,
    `channel`         ENUM('push','email') NOT NULL,
    `recipient`       VARCHAR(255) NULL COMMENT 'email address; push goes to every device of user_id',
    `recipient_name`  VARCHAR(255) NULL,
    `dedupe_key`      VARCHAR(150) NULL COMMENT 'emails with no bell row (to shoppers): never the same one twice',
    `payload`         MEDIUMTEXT   NOT NULL COMMENT 'JSON: the push message, or subject/template/vars/branding',
    `urgency`         VARCHAR(10)  NOT NULL DEFAULT 'normal',
    `status`          ENUM('pending','sending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
    `attempts`        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `next_attempt_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at`      DATETIME     NULL,
    `last_error`      VARCHAR(255) NULL,
    `sent_at`         DATETIME     NULL,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_outbox_dedupe` (`dedupe_key`),
    KEY `idx_outbox_due` (`status`, `next_attempt_at`),
    KEY `idx_outbox_notification` (`notification_id`),
    KEY `idx_outbox_business` (`business_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notification_prefs` (
    `user_id`    INT         NOT NULL,
    `topic`      VARCHAR(30) NOT NULL,
    `push`       TINYINT(1)  NOT NULL DEFAULT 1,
    `email`      TINYINT(1)  NOT NULL DEFAULT 0,
    `updated_at` TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `topic`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `hide_amounts` TINYINT(1) NOT NULL DEFAULT 0;

INSERT IGNORE INTO `notification_prefs` (`user_id`, `topic`, `push`, `email`)
    SELECT `id`, 'money', 0, 0 FROM `users` WHERE `notify_money` = 0;

INSERT IGNORE INTO `notification_prefs` (`user_id`, `topic`, `push`, `email`)
    SELECT `id`, 'reminders', `notify_reminders`, `reminder_email` FROM `users`
    WHERE `notify_reminders` = 0 OR `reminder_email` = 0;

INSERT IGNORE INTO `notification_prefs` (`user_id`, `topic`, `push`, `email`)
    SELECT `id`, 'morning', `notify_reminders`, `morning_email` FROM `users`
    WHERE `notify_reminders` = 0 OR `morning_email` = 0;
