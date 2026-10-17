-- Passkeys, and when each signed-in device was last used.
--
-- A passkey lets someone sign in, or unlock Nfelia after the 2-hour lock,
-- with their fingerprint, face or phone PIN instead of a password. The phone
-- keeps the private key; Nfelia stores only the public key, which cannot be
-- used to sign in. Each passkey belongs to one person: staff add their own.
--
-- sessions.last_active_at lets a "keep me signed in" device that comes back
-- after a while open on the lock instead of straight into the business.

CREATE TABLE IF NOT EXISTS `user_passkeys` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id`        INT          NOT NULL,
    `credential_id`  VARCHAR(512) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `public_key`     TEXT         NOT NULL,
    `sign_count`     INT UNSIGNED NOT NULL DEFAULT 0,
    `name`           VARCHAR(80)  NOT NULL,
    `transports`     VARCHAR(80)  NULL,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_used_at`   DATETIME     NULL,
    UNIQUE KEY `uniq_credential` (`credential_id`),
    KEY `idx_user` (`user_id`),
    CONSTRAINT `fk_passkeys_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `sessions`
    ADD COLUMN IF NOT EXISTS `last_active_at` DATETIME NULL AFTER `expires_at`;
