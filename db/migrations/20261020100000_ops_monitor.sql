-- Nfelia Ops, the monitor: every error, slow page and failed job, grouped,
-- with a status the team shares; the cron's heartbeat; and alerts to the
-- Ops team (their own bell and phones). See app/helpers/errors.php.

-- One row per distinct problem: the same message from the same place.
CREATE TABLE IF NOT EXISTS `error_groups` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `fingerprint`       CHAR(40)     NOT NULL,
    `kind`              ENUM('php','js','slow','log','job') NOT NULL,
    `level`             VARCHAR(20)  NOT NULL COMMENT 'fatal, error, warning, notice, deprecated, slow',
    `title`             VARCHAR(255) NOT NULL COMMENT 'the message, numbers and ids taken out',
    `message`           TEXT         NULL COMMENT 'the latest full message',
    `file`              VARCHAR(255) NULL,
    `line`              INT          NULL,
    `route`             VARCHAR(160) NULL COMMENT 'the page, ids taken out: orders/view/:id',
    `status`            ENUM('open','handling','fixed','ignored') NOT NULL DEFAULT 'open',
    `assigned_to`       INT UNSIGNED NULL,
    `status_by`         INT UNSIGNED NULL,
    `status_changed_at` DATETIME     NULL,
    `first_seen_at`     DATETIME     NOT NULL,
    `last_seen_at`      DATETIME     NOT NULL,
    `first_release`     VARCHAR(40)  NULL,
    `last_release`      VARCHAR(40)  NULL,
    `events`            INT UNSIGNED NOT NULL DEFAULT 0,
    `regressions`       INT UNSIGNED NOT NULL DEFAULT 0,
    `regressed_at`      DATETIME     NULL COMMENT 'came back after it was marked fixed',
    `spiked_at`         DATETIME     NULL,
    UNIQUE KEY `uniq_error_fingerprint` (`fingerprint`),
    KEY `idx_error_status` (`status`, `last_seen_at`),
    KEY `idx_error_kind` (`kind`, `last_seen_at`),
    KEY `idx_error_seen` (`last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Each time it happened (the latest 50 per problem, for 30 days), with who,
-- where, and the code shown to the person it happened to.
CREATE TABLE IF NOT EXISTS `error_events` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `group_id`    INT UNSIGNED NOT NULL,
    `ref`         CHAR(8)      NOT NULL COMMENT 'the error code a client can quote',
    `source`      VARCHAR(10)  NOT NULL COMMENT 'web, api, cron, browser, shop, log',
    `business_id` INT          NULL,
    `user_id`     INT          NULL,
    `method`      VARCHAR(8)   NULL,
    `path`        VARCHAR(255) NULL COMMENT 'tokens in it replaced by :token',
    `duration_ms` INT UNSIGNED NULL,
    `memory_mb`   SMALLINT UNSIGNED NULL,
    `message`     TEXT         NULL,
    `trace`       MEDIUMTEXT   NULL,
    `user_agent`  VARCHAR(255) NULL,
    `release_id`  VARCHAR(40)  NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_error_ref` (`ref`),
    KEY `idx_error_event_group` (`group_id`, `id`),
    KEY `idx_error_event_business` (`business_id`, `created_at`),
    KEY `idx_error_event_time` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `error_notes` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `group_id`   INT UNSIGNED NOT NULL,
    `staff_id`   INT UNSIGNED NULL COMMENT 'null: Nfelia itself (came back, spiking)',
    `body`       TEXT         NOT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_error_note_group` (`group_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- When each background job last ran, and how it went.
CREATE TABLE IF NOT EXISTS `ops_heartbeats` (
    `name`        VARCHAR(40)  NOT NULL PRIMARY KEY,
    `started_at`  DATETIME     NULL,
    `finished_at` DATETIME     NULL,
    `ok`          TINYINT(1)   NULL,
    `duration_ms` INT UNSIGNED NULL,
    `note`        VARCHAR(255) NULL,
    `runs`        INT UNSIGNED NOT NULL DEFAULT 0,
    `failures`    INT UNSIGNED NOT NULL DEFAULT 0,
    `last_failure_at` DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Small things Ops keeps between runs (how far the server log was read).
CREATE TABLE IF NOT EXISTS `ops_settings` (
    `name`       VARCHAR(100) NOT NULL PRIMARY KEY,
    `value`      TEXT         NULL,
    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The Ops team's own bell, one row per person.
CREATE TABLE IF NOT EXISTS `ops_alerts` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `staff_id`   INT UNSIGNED NOT NULL,
    `kind`       VARCHAR(40)  NOT NULL,
    `title`      VARCHAR(255) NOT NULL,
    `body`       VARCHAR(500) NULL,
    `url`        VARCHAR(255) NOT NULL COMMENT 'a path inside Ops',
    `dedupe_key` VARCHAR(150) NULL,
    `read_at`    DATETIME     NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_ops_alert` (`staff_id`, `dedupe_key`),
    KEY `idx_ops_alert_inbox` (`staff_id`, `read_at`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Phones and computers an Ops person turned alerts on for (ops-sw.js).
CREATE TABLE IF NOT EXISTS `ops_push_subscriptions` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `staff_id`      INT UNSIGNED NOT NULL,
    `endpoint`      TEXT         NOT NULL,
    `endpoint_hash` CHAR(64)     NOT NULL,
    `p256dh`        VARCHAR(255) NOT NULL,
    `auth`          VARCHAR(255) NOT NULL,
    `failures`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `last_sent_at`  DATETIME     NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_ops_push_endpoint` (`endpoint_hash`),
    KEY `idx_ops_push_staff` (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `ops_staff`
    ADD COLUMN IF NOT EXISTS `alert_push`   TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'errors and health to their phone',
    ADD COLUMN IF NOT EXISTS `digest_email` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'the morning summary by email';

-- An Ops person's push goes through the same outbox, retries and all.
ALTER TABLE `notify_outbox`
    ADD COLUMN IF NOT EXISTS `staff_id` INT UNSIGNED NULL AFTER `user_id`;
