-- Normalise every table to utf8mb4_unicode_ci.
--
-- Production was converted by hand on 2026-10-17 after the move from MySQL
-- 8.0 to MariaDB 10.11; this migration exists so a fresh environment ends up
-- identical. Re-running it is harmless: CONVERT TO on a table already in the
-- target collation is a no-op in effect.
--
-- The original fault was config/database.php pinning utf8mb4_0900_ai_ci in
-- PDO::MYSQL_ATTR_INIT_COMMAND. That collation is MySQL 8 only, so every
-- connection threw SQLSTATE[HY000] [1273] and the whole app returned
-- "Database connection failed." Never pin a server-specific collation.

ALTER DATABASE `smartqaq_nfelia_core` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `document_counters`       CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `orders`                  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `order_items`             CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `password_resets`         CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `product_images`          CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `product_variant_groups`  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `product_variant_options` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `purge_audit_logs`        CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `purge_security_config`   CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `quotations`              CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `quotation_activities`    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `quotation_items`         CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
