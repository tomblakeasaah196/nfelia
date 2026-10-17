-- Tasks & Calendar: the owner's to-do list, appointments and job plans,
-- plus a promised delivery date on invoices.
--
-- One table holds tasks, appointments and the steps of a job plan
-- (parent_id points at the plan). "Late" is never stored: it is worked out
-- when the list is read, from due_date and done_at.
--
-- Reminders use the row itself as the queue: remind_at is computed when the
-- item is saved, and cron/planner.php sends every row whose remind_at has
-- passed and stamps reminded_at, even when delivery fails, so one bad row
-- can never block the rest.
--
-- Dates Nfelia already knows (invoices due, debts, deliveries, quotes, shop
-- orders, low stock) are not copied here. They are read live, so they clear
-- by themselves when the money comes in or the goods go out.
-- planner_dismissals remembers the ones the owner ticked off by hand.

CREATE TABLE IF NOT EXISTS `tasks` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id`   INT          NOT NULL,
    `parent_id`     INT UNSIGNED NULL,
    `kind`          ENUM('task','appointment') NOT NULL DEFAULT 'task',
    `title`         VARCHAR(200) NOT NULL,
    `notes`         TEXT         NULL,
    `is_starred`    TINYINT(1)   NOT NULL DEFAULT 0,
    `due_date`      DATE         NULL,
    `due_time`      TIME         NULL,
    `end_time`      TIME         NULL,
    `customer_id`   INT          NULL,
    `invoice_id`    INT          NULL,
    `plan`          VARCHAR(20)  NULL,
    `step_key`      VARCHAR(20)  NULL,
    `repeat_every`  ENUM('day','week','month') NULL,
    `spawned_from`  INT UNSIGNED NULL,
    `remind_before` SMALLINT UNSIGNED NULL,
    `remind_at`     DATETIME     NULL,
    `reminded_at`   DATETIME     NULL,
    `assigned_to`   INT          NULL,
    `created_by`    INT          NOT NULL,
    `done_at`       DATETIME     NULL,
    `done_by`       INT          NULL,
    `sort_order`    SMALLINT     NOT NULL DEFAULT 0,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`    DATETIME     NULL,
    KEY `idx_tasks_business_due` (`business_id`, `deleted_at`, `due_date`),
    KEY `idx_tasks_parent` (`parent_id`),
    KEY `idx_tasks_customer` (`business_id`, `customer_id`),
    KEY `idx_tasks_invoice` (`invoice_id`),
    KEY `idx_tasks_remind` (`remind_at`, `reminded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `planner_dismissals` (
    `business_id`  INT         NOT NULL,
    `item_key`     VARCHAR(80) NOT NULL,
    `until_date`   DATE        NULL,
    `dismissed_by` INT         NULL,
    `created_at`   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`business_id`, `item_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per user per day for the morning plan, so a cron that runs every
-- five minutes still sends it once.
CREATE TABLE IF NOT EXISTS `planner_sends` (
    `user_id`    INT         NOT NULL,
    `kind`       VARCHAR(20) NOT NULL,
    `send_date`  DATE        NOT NULL,
    `created_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `kind`, `send_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Web push: one row per browser or installed app that said yes to
-- notifications. The endpoint can be long, so uniqueness is on its hash.
CREATE TABLE IF NOT EXISTS `push_subscriptions` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id`   INT          NOT NULL,
    `user_id`       INT          NOT NULL,
    `endpoint`      TEXT         NOT NULL,
    `endpoint_hash` CHAR(64)     NOT NULL,
    `p256dh`        VARCHAR(120) NOT NULL,
    `auth`          VARCHAR(40)  NOT NULL,
    `user_agent`    VARCHAR(255) NULL,
    `failures`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `last_sent_at`  DATETIME     NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_push_endpoint` (`endpoint_hash`),
    KEY `idx_push_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The promise made to the customer. deliver_mode is required on our side
-- for new invoices; deliver_show decides whether the customer sees it.
ALTER TABLE `invoices`
    ADD COLUMN IF NOT EXISTS `deliver_mode` ENUM('immediate','date') NULL AFTER `due_date`,
    ADD COLUMN IF NOT EXISTS `deliver_by`   DATE       NULL AFTER `deliver_mode`,
    ADD COLUMN IF NOT EXISTS `deliver_show` TINYINT(1) NOT NULL DEFAULT 0 AFTER `deliver_by`,
    ADD COLUMN IF NOT EXISTS `delivered_at` DATETIME   NULL AFTER `deliver_show`;

ALTER TABLE `invoices` ADD INDEX IF NOT EXISTS `idx_invoices_deliver` (`business_id`, `deliver_by`);

-- Per-person email choices for reminders. Phone notifications are chosen
-- per device, by subscribing.
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `morning_email`  TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS `reminder_email` TINYINT(1) NOT NULL DEFAULT 1;

-- Settings > Staff saves the role 'admin', but the column only allowed
-- owner and staff, so adding an admin failed. Widening an ENUM keeps every
-- existing value.
ALTER TABLE `users` MODIFY `role` ENUM('owner','admin','staff') NOT NULL DEFAULT 'owner';
