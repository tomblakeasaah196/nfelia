-- One-time links for password resets and email sign-in, and a request log
-- used to rate-limit the public endpoints that send them.
--
-- Tokens are stored as SHA-256 hashes, never in the clear, so read access to
-- the database does not hand out working links. Expiry and use are tracked
-- per token, so a new request does not cancel a link already in someone's
-- inbox, and a used link can never be redeemed twice.
--
-- Replaces password_resets, which is no longer read or written. It is left in
-- place so this migration stays additive; drop it in a later migration.

CREATE TABLE IF NOT EXISTS `account_tokens` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id`     INT          NOT NULL,
    `purpose`     VARCHAR(16)  NOT NULL,
    `token_hash`  CHAR(64)     NOT NULL,
    `expires_at`  DATETIME     NOT NULL,
    `used_at`     DATETIME     NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_token_hash` (`token_hash`),
    KEY `idx_user_purpose` (`user_id`, `purpose`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `auth_throttle` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `bucket`      VARCHAR(32)  NOT NULL,
    `key_hash`    CHAR(64)     NOT NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_bucket_key_time` (`bucket`, `key_hash`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
