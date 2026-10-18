-- Nfelia Ops, clients: everything the old founder admin page did now lives
-- at ops.nfelia.com (app/ops/controllers/OpsClientsController.php and
-- friends), plus a client's whole picture, notes, and "view as".

-- Opening a client's Nfelia to see what they see: read-only, for 30
-- minutes, with a reason, and the owner is told (security.view_as). The
-- link that starts it works once, for 2 minutes; only its hash is kept.
CREATE TABLE IF NOT EXISTS `ops_view_as` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `staff_id`    INT UNSIGNED NOT NULL,
    `business_id` INT          NOT NULL,
    `user_id`     INT          NOT NULL COMMENT 'whose view: the owner',
    `reason`      VARCHAR(255) NOT NULL,
    `token_hash`  CHAR(64)     NOT NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `started_at`  DATETIME     NULL,
    `ends_at`     DATETIME     NULL,
    `ended_at`    DATETIME     NULL,
    UNIQUE KEY `uniq_view_as_token` (`token_hash`),
    KEY `idx_view_as_business` (`business_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The team's notes on a client or a lead ("called, wants a demo Friday").
CREATE TABLE IF NOT EXISTS `ops_notes` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `subject_type` ENUM('business','lead') NOT NULL,
    `subject_id`   INT          NOT NULL,
    `staff_id`     INT UNSIGNED NULL,
    `body`         TEXT         NOT NULL,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_ops_notes_subject` (`subject_type`, `subject_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Who in Ops approved or turned down a mobile money payment (reviewed_by
-- was a user of the Nfelia business, which Ops no longer is).
ALTER TABLE `subscription_payments`
    ADD COLUMN IF NOT EXISTS `reviewed_by_staff` INT UNSIGNED NULL AFTER `reviewed_by`;
