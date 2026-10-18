-- Nfelia Ops (ops.nfelia.com): the Nfelia team's own place to look after
-- clients, errors and sales. Its people are not users of any business:
-- they sign in separately (app/ops/), with their own roles, and everything
-- they do is written to ops_audit.

CREATE TABLE IF NOT EXISTS `ops_staff` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `full_name`       VARCHAR(120) NOT NULL,
    `email`           VARCHAR(190) NOT NULL,
    `password_hash`   VARCHAR(255) NULL COMMENT 'null until the invite is accepted',
    `role`            ENUM('admin','support','sales') NOT NULL DEFAULT 'support',
    `is_active`       TINYINT(1)   NOT NULL DEFAULT 1,
    `session_epoch`   INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'raised to sign this person out everywhere',
    `invited_by`      INT UNSIGNED NULL,
    `last_signin_at`  DATETIME     NULL,
    `last_signin_ip`  VARCHAR(45)  NULL,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_ops_staff_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Passkeys for ops.nfelia.com only: a passkey made there cannot sign in to
-- a business, and a business passkey cannot sign in here.
CREATE TABLE IF NOT EXISTS `ops_passkeys` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `staff_id`      INT UNSIGNED NOT NULL,
    `credential_id` VARCHAR(512) NOT NULL,
    `public_key`    TEXT         NOT NULL,
    `sign_count`    INT UNSIGNED NOT NULL DEFAULT 0,
    `name`          VARCHAR(60)  NOT NULL,
    `transports`    VARCHAR(100) NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_used_at`  DATETIME     NULL,
    UNIQUE KEY `uniq_ops_passkey` (`credential_id`),
    KEY `idx_ops_passkey_staff` (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Invite and password-reset links. Only the SHA-256 of the token is kept;
-- the token itself exists in the emailed link alone. Each works once.
CREATE TABLE IF NOT EXISTS `ops_tokens` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `staff_id`    INT UNSIGNED NOT NULL,
    `purpose`     ENUM('invite','reset') NOT NULL,
    `token_hash`  CHAR(64)     NOT NULL,
    `expires_at`  DATETIME     NOT NULL,
    `used_at`     DATETIME     NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_ops_token` (`token_hash`),
    KEY `idx_ops_token_staff` (`staff_id`, `purpose`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Everything done in Ops, by whom, to what. Written, never changed.
CREATE TABLE IF NOT EXISTS `ops_audit` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `staff_id`    INT UNSIGNED NULL COMMENT 'null: Nfelia itself, or someone not signed in',
    `action`      VARCHAR(64)  NOT NULL,
    `summary`     VARCHAR(255) NOT NULL,
    `target_type` VARCHAR(32)  NULL,
    `target_id`   BIGINT       NULL,
    `business_id` INT          NULL COMMENT 'the client it concerns, if any',
    `data`        TEXT         NULL COMMENT 'JSON: before/after, reasons',
    `ip`          VARCHAR(45)  NULL,
    `user_agent`  VARCHAR(255) NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_ops_audit_time` (`created_at`),
    KEY `idx_ops_audit_staff` (`staff_id`, `created_at`),
    KEY `idx_ops_audit_business` (`business_id`, `created_at`),
    KEY `idx_ops_audit_action` (`action`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
