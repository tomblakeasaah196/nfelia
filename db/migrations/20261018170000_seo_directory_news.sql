-- Selling, step 5: being found.
--
-- businesses.directory_opt_in, city, city_slug
--     A shop can choose to be listed in Nfelia's public directory
--     (nfelia.com/shops/{city}/{category}). Set in Shop Studio and copied here
--     when the owner publishes, so the directory can filter without reading
--     every shop's settings. Only paid shops are ever listed.
--
-- shop_posts
--     News and offers a shop publishes: each has its own page
--     (name.nfelia.com/news/{id}-{title}), shows in the shop's news section and
--     in its sitemap. 'draft' posts are only seen by the owner.

ALTER TABLE `businesses`
    ADD COLUMN IF NOT EXISTS `directory_opt_in` TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `city` VARCHAR(60) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `city_slug` VARCHAR(60) NULL DEFAULT NULL;

CREATE INDEX IF NOT EXISTS `idx_businesses_directory` ON `businesses` (`directory_opt_in`, `city_slug`, `category`);

CREATE TABLE IF NOT EXISTS `shop_posts` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id`  INT          NOT NULL,
    `title`        VARCHAR(120) NOT NULL,
    `body`         TEXT         NOT NULL,
    `image_path`   VARCHAR(255) NULL,
    `status`       ENUM('draft','published') NOT NULL DEFAULT 'draft',
    `published_at` DATETIME     NULL,
    `created_by`   INT          NULL,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`   DATETIME     NULL,
    KEY `idx_shop_posts_live` (`business_id`, `status`, `published_at`),
    CONSTRAINT `fk_shop_posts_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
