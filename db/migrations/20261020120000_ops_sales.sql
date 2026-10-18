-- Nfelia Ops, sales: leads and the pipeline, follow-ups, and email
-- campaigns (app/ops/sales.php).

-- Someone who might use Nfelia, or a client worth winning back. Some come
-- in by themselves (the home page's form, a sign-up that stalled in setup,
-- a trial about to end); the rest are entered by the team.
CREATE TABLE IF NOT EXISTS `leads` (
    `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name`                 VARCHAR(120) NOT NULL COMMENT 'the person',
    `business_name`        VARCHAR(160) NULL,
    `email`                VARCHAR(190) NULL,
    `phone`                VARCHAR(40)  NULL,
    `source`               VARCHAR(20)  NOT NULL DEFAULT 'manual' COMMENT 'website, signup, trial, manual, referral, event, social, walk_in',
    `stage`                ENUM('new','contacted','demo','trial','paying','lost') NOT NULL DEFAULT 'new',
    `owner_id`             INT UNSIGNED NULL COMMENT 'ops_staff: who follows it up',
    `business_id`          INT          NULL COMMENT 'their Nfelia business, once they have one',
    `feedback_id`          INT          NULL COMMENT 'general_feedback: the home page message it came from',
    `next_follow_up_at`    DATETIME     NULL,
    `follow_up_note`       VARCHAR(255) NULL,
    `follow_up_reminded_at` DATETIME    NULL,
    `last_contact_at`      DATETIME     NULL,
    `lost_reason`          VARCHAR(255) NULL,
    `created_by`           INT UNSIGNED NULL,
    `created_at`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `stage_changed_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_lead_business` (`business_id`),
    KEY `idx_lead_stage` (`stage`, `updated_at`),
    KEY `idx_lead_follow_up` (`next_follow_up_at`),
    KEY `idx_lead_owner` (`owner_id`, `stage`),
    KEY `idx_lead_email` (`email`),
    KEY `idx_lead_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- An email to a group of leads or clients.
CREATE TABLE IF NOT EXISTS `campaigns` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name`        VARCHAR(120) NOT NULL,
    `subject`     VARCHAR(160) NOT NULL,
    `body`        TEXT         NOT NULL COMMENT 'plain text; blank lines make paragraphs; {first_name} and {business}',
    `button_text` VARCHAR(60)  NULL,
    `button_url`  VARCHAR(255) NULL,
    `audience`    VARCHAR(500) NOT NULL COMMENT 'JSON: who it goes to',
    `status`      ENUM('draft','sending','sent') NOT NULL DEFAULT 'draft',
    `created_by`  INT UNSIGNED NULL,
    `sent_by`     INT UNSIGNED NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `sent_at`     DATETIME     NULL COMMENT 'when Send was pressed'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Each person a campaign goes to. The cron hands them to the outbox a few
-- at a time, so a big campaign never floods the mail server.
CREATE TABLE IF NOT EXISTS `campaign_sends` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `campaign_id` INT UNSIGNED NOT NULL,
    `email`       VARCHAR(190) NOT NULL,
    `name`        VARCHAR(160) NULL,
    `business`    VARCHAR(160) NULL,
    `lead_id`     INT UNSIGNED NULL,
    `business_id` INT          NULL,
    `token`       CHAR(32)     NOT NULL COMMENT 'for its unsubscribe link',
    `status`      ENUM('waiting','queued','sent','failed','skipped') NOT NULL DEFAULT 'waiting',
    `outbox_id`   BIGINT UNSIGNED NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `queued_at`   DATETIME     NULL,
    UNIQUE KEY `uniq_campaign_email` (`campaign_id`, `email`),
    KEY `idx_campaign_send_status` (`status`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Addresses that asked for no more campaigns (the link in every one).
-- Account, billing and security emails still go: they are not marketing.
CREATE TABLE IF NOT EXISTS `email_opt_outs` (
    `email`      VARCHAR(190) NOT NULL PRIMARY KEY,
    `campaign_id` INT UNSIGNED NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Messages already sent through the home page become leads.
INSERT INTO `leads` (`name`, `business_name`, `email`, `phone`, `source`, `stage`, `feedback_id`, `created_at`, `stage_changed_at`)
    SELECT LEFT(f.`enterprise_name`, 120), LEFT(f.`enterprise_name`, 160),
           IF(f.`contact_info` LIKE '%@%', LEFT(f.`contact_info`, 190), NULL),
           IF(f.`contact_info` NOT LIKE '%@%' AND f.`contact_info` <> '', LEFT(f.`contact_info`, 40), NULL),
           'website', 'new', f.`id`, f.`created_at`, f.`created_at`
    FROM `general_feedback` f
    WHERE NOT EXISTS (SELECT 1 FROM `leads` l WHERE l.`feedback_id` = f.`id`);
