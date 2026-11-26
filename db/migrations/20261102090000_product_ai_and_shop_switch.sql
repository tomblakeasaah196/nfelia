-- Adding an item from a photo (app/helpers/product_ai.php), and "Show on my
-- shop" for each item.
--
-- AI: switched on for a business in Ops (Clients › AI writer, Admins only),
-- with how many photos it may use a week (100 unless Ops changes it). Each
-- photo sent is one row in ai_uses: 'pending' while Gemini answers, then
-- 'ok' (it counts) or 'failed' (it doesn't). A week starts on Monday.
--
-- Show on my shop: products.on_shop. Off, the item stays in the catalogue
-- and on invoices, quotes and orders, but the shop doesn't show it or take
-- orders for it. Hidden (is_active = 0) still takes it off everything.
-- Every item that exists now stays on the shop.
--
-- Safe to run twice.

ALTER TABLE `businesses`
    ADD COLUMN IF NOT EXISTS `ai_enabled` TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Can add items from a photo (switched on in Ops)',
    ADD COLUMN IF NOT EXISTS `ai_weekly_limit` SMALLINT UNSIGNED NOT NULL DEFAULT 100
        COMMENT 'Photos a week, from Monday';

ALTER TABLE `products`
    ADD COLUMN IF NOT EXISTS `on_shop` TINYINT(1) NOT NULL DEFAULT 1
        COMMENT 'Show on my shop: off keeps it in the catalogue but off the shop' AFTER `is_active`;

CREATE TABLE IF NOT EXISTS `ai_uses` (
  `id`          int unsigned NOT NULL AUTO_INCREMENT,
  `business_id` int NOT NULL,
  `user_id`     int DEFAULT NULL,
  `kind`        varchar(30) NOT NULL DEFAULT 'product_photo',
  `status`      enum('pending','ok','failed') NOT NULL DEFAULT 'pending' COMMENT 'Only ok counts towards the week',
  `lang`        char(2) NOT NULL DEFAULT 'en',
  `model`       varchar(60) DEFAULT NULL,
  `tokens_in`   int unsigned DEFAULT NULL,
  `tokens_out`  int unsigned DEFAULT NULL COMMENT 'Answer and thinking: both are paid as output',
  `ms`          int unsigned DEFAULT NULL,
  `problem`     varchar(255) DEFAULT NULL,
  `created_at`  datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_uses_business` (`business_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- So php db/migrate.php skips this file once GitHub is back.
CREATE TABLE IF NOT EXISTS schema_migrations (
    version     VARCHAR(255) NOT NULL PRIMARY KEY,
    applied_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO schema_migrations (version) VALUES ('20261102090000_product_ai_and_shop_switch');
