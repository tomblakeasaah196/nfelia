-- A fresh start for a client (Ops > Clients > Fresh start, Admins only):
-- each wipe, who did it and why, the client's written request when there is
-- one, what went, and the copy of it kept for 30 days (app/ops/wipe.php).
CREATE TABLE IF NOT EXISTS `client_wipes` (
  `id`              int unsigned NOT NULL AUTO_INCREMENT,
  `business_id`     int NOT NULL,
  `staff_id`        int unsigned NOT NULL,
  `parts`           varchar(100) NOT NULL COMMENT 'What was picked, e.g. sales,stock,suppliers (WIPE_PARTS)',
  `reason`          varchar(1000) NOT NULL,
  `proof_path`      varchar(255) DEFAULT NULL COMMENT 'Their written request: storage/private/wipe_proofs/...',
  `counts`          text DEFAULT NULL COMMENT 'JSON: rows that went, per table',
  `copy_path`       varchar(255) DEFAULT NULL COMMENT 'storage/private/wipes/...zip; NULL once it is no longer kept',
  `copy_size`       int unsigned DEFAULT NULL,
  `copy_sha256`     char(64) DEFAULT NULL,
  `copy_expires_at` datetime DEFAULT NULL,
  `owner_told_at`   datetime DEFAULT NULL,
  `created_at`      datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_client_wipes_business` (`business_id`, `id`),
  KEY `idx_client_wipes_expiry` (`copy_expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- So php db/migrate.php skips this file once GitHub is back.
CREATE TABLE IF NOT EXISTS schema_migrations (
    version     VARCHAR(255) NOT NULL PRIMARY KEY,
    applied_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO schema_migrations (version) VALUES ('20261101090000_client_wipes');
